<?php
/**
 * Runs a bounded, read-only local attachment image-context evidence pilot.
 *
 * Usage:
 * wp eval-file scripts/eval-local-image-context-artifact-pilot.php 20 --user=<administrator>
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this script through WP-CLI eval-file.\n" );
	exit( 1 );
}

if ( ! current_user_can( 'upload_files' ) ) {
	fwrite( STDERR, "The selected WP-CLI user must have upload_files capability.\n" );
	exit( 1 );
}

if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
	fwrite( STDERR, "Npcink Cloud Addon settings must pass Save and Verify.\n" );
	exit( 1 );
}

$requested_limit = isset( $args[0] ) ? absint( $args[0] ) : 20;
$limit           = min( 20, max( 1, $requested_limit ) );
$client          = function_exists( 'npcink_cloud_addon_runtime_client' )
	? npcink_cloud_addon_runtime_client()
	: null;

if ( ! $client instanceof Npcink_Cloud_Runtime_Client ) {
	fwrite( STDERR, "Npcink Cloud Addon must be configured and verified.\n" );
	exit( 1 );
}

$upload_dir  = wp_get_upload_dir();
$upload_root = realpath( (string) ( $upload_dir['basedir'] ?? '' ) );
if ( false === $upload_root ) {
	fwrite( STDERR, "WordPress uploads directory is unavailable.\n" );
	exit( 1 );
}

$attachment_ids = get_posts(
	array(
		'post_type'      => 'attachment',
		'post_status'    => 'inherit',
		'post_mime_type' => array( 'image/jpeg', 'image/png', 'image/webp' ),
		'posts_per_page' => 200,
		'orderby'        => 'ID',
		'order'          => 'ASC',
		'fields'         => 'ids',
	)
);

$items     = array();
$skipped   = array();
$trace_id  = 'media_artifact_pilot_' . wp_generate_uuid4();
$site_seed = untrailingslashit( home_url( '/' ) );

foreach ( $attachment_ids as $attachment_id ) {
	$attachment_id = absint( $attachment_id );
	if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
		$skipped['permission'] = ( $skipped['permission'] ?? 0 ) + 1;
		continue;
	}

	$metadata = wp_get_attachment_metadata( $attachment_id );
	$width    = is_array( $metadata ) ? absint( $metadata['width'] ?? 0 ) : 0;
	$height   = is_array( $metadata ) ? absint( $metadata['height'] ?? 0 ) : 0;
	if ( 256 > $width || 256 > $height ) {
		$skipped['dimensions'] = ( $skipped['dimensions'] ?? 0 ) + 1;
		continue;
	}

	$path      = get_attached_file( $attachment_id );
	$real_path = is_string( $path ) ? realpath( $path ) : false;
	if (
		false === $real_path
		|| ! is_file( $real_path )
		|| ! is_readable( $real_path )
		|| ( $upload_root !== $real_path && 0 !== strpos( $real_path, trailingslashit( $upload_root ) ) )
	) {
		$skipped['file'] = ( $skipped['file'] ?? 0 ) + 1;
		continue;
	}

	$file_size = filesize( $real_path );
	if ( false === $file_size || 0 >= $file_size || 8 * MB_IN_BYTES < $file_size ) {
		$skipped['size'] = ( $skipped['size'] ?? 0 ) + 1;
		continue;
	}

	$contents = file_get_contents( $real_path );
	if ( ! is_string( $contents ) || strlen( $contents ) !== $file_size ) {
		$skipped['read'] = ( $skipped['read'] ?? 0 ) + 1;
		continue;
	}

	$mime_type = wp_get_image_mime( $real_path );
	if (
		! is_string( $mime_type )
		|| ! in_array( $mime_type, array( 'image/jpeg', 'image/png', 'image/webp' ), true )
		|| $mime_type !== (string) get_post_mime_type( $attachment_id )
	) {
		$skipped['mime'] = ( $skipped['mime'] ?? 0 ) + 1;
		continue;
	}

	$upload_key = 'image_context_upload_' . substr(
		hash( 'sha256', $site_seed . '|' . $attachment_id . '|' . hash( 'sha256', $contents ) ),
		0,
		40
	);
	$artifact   = $client->upload_media_artifact(
		array(
			'contents'  => $contents,
			'filename'  => basename( $real_path ),
			'mime_type' => $mime_type,
		),
		$trace_id,
		$upload_key
	);
	unset( $contents );

	if ( is_wp_error( $artifact ) ) {
		fwrite(
			STDERR,
			wp_json_encode(
				array(
					'status'        => 'error',
					'stage'         => 'upload',
					'attachment_id' => $attachment_id,
					'error_code'    => $artifact->get_error_code(),
					'message'       => $artifact->get_error_message(),
				),
				JSON_UNESCAPED_SLASHES
			) . "\n"
		);
		exit( 1 );
	}

	$items[] = array(
		'attachment_id'          => $attachment_id,
		'source_artifact_id'     => (string) ( $artifact['artifact_id'] ?? '' ),
		'title'                  => get_the_title( $attachment_id ),
		'filename'               => basename( $real_path ),
		'mime_type'              => $mime_type,
		'current_alt_status'     => '' === trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ) ? 'missing' : 'present',
		'current_caption_status' => '' === trim( (string) wp_get_attachment_caption( $attachment_id ) ) ? 'missing' : 'present',
	);

	if ( count( $items ) >= $limit ) {
		break;
	}
}

if ( empty( $items ) ) {
	fwrite( STDERR, "No eligible local image attachments were found.\n" );
	exit( 1 );
}

$results = array();
foreach ( array_chunk( $items, 10 ) as $batch_index => $batch ) {
	$artifact_ids = wp_list_pluck( $batch, 'source_artifact_id' );
	$request_key  = 'image_context_evidence_' . substr(
		hash( 'sha256', $site_seed . '|' . implode( '|', $artifact_ids ) ),
		0,
		40
	);
	$result       = $client->request_image_context_evidence(
		array(
			'contract_version'       => 'image_context_evidence_request.v1',
			'write_posture'          => 'suggestion_only',
			'direct_wordpress_write' => false,
			'no_local_model'         => true,
			'no_media_write'         => true,
			'items'                  => $batch,
		),
		$trace_id,
		$request_key
	);
	if ( is_wp_error( $result ) ) {
		fwrite(
			STDERR,
			wp_json_encode(
				array(
					'status'      => 'error',
					'stage'       => 'vision',
					'batch'       => $batch_index + 1,
					'error_code'  => $result->get_error_code(),
					'message'     => $result->get_error_message(),
				),
				JSON_UNESCAPED_SLASHES
			) . "\n"
		);
		exit( 1 );
	}
	$results[] = $result;
}

echo wp_json_encode(
	array(
		'contract_version' => 'local_image_context_artifact_pilot.v1',
		'status'           => 'complete',
		'selected_count'   => count( $items ),
		'batch_count'      => count( $results ),
		'skipped'          => $skipped,
		'trace_id'         => $trace_id,
		'results'          => $results,
		'wordpress_writes' => false,
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
) . "\n";
