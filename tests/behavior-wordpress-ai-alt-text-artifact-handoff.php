<?php
/**
 * Behavior tests for the WordPress AI alt-text attachment artifact handoff.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

namespace WordPress\AiClient\Providers {
	abstract class AbstractProvider {}
}

namespace WordPress\AiClient\Providers\Contracts {
	interface ProviderAvailabilityInterface {}
	interface ModelMetadataDirectoryInterface {}
}

namespace WordPress\AiClient\Providers\Models\Contracts {
	interface ModelInterface {}
}

namespace WordPress\AiClient\Providers\Models\TextGeneration\Contracts {
	interface TextGenerationModelInterface {}
}

namespace WordPress\AiClient\Providers\Models\ImageGeneration\Contracts {
	interface ImageGenerationModelInterface {}
}

namespace {
	require_once __DIR__ . '/helpers.php';

	if ( ! function_exists( 'sanitize_mime_type' ) ) {
		function sanitize_mime_type( $value ): string {
			return strtolower( trim( (string) $value ) );
		}
	}

	$GLOBALS['maca_alt_text_attachments'] = array();
	$GLOBALS['maca_alt_text_permissions'] = array();
	$GLOBALS['maca_alt_text_replace_on_mime'] = array();
	$GLOBALS['maca_alt_text_client'] = null;

	function current_user_can( string $capability, int $attachment_id ): bool {
		return 'edit_post' === $capability && true === ( $GLOBALS['maca_alt_text_permissions'][ $attachment_id ] ?? false );
	}

	function get_post( int $attachment_id ) {
		return $GLOBALS['maca_alt_text_attachments'][ $attachment_id ]['post'] ?? null;
	}

	function get_attached_file( int $attachment_id ) {
		return $GLOBALS['maca_alt_text_attachments'][ $attachment_id ]['file'] ?? false;
	}

	function wp_upload_dir(): array {
		return array( 'basedir' => (string) $GLOBALS['maca_alt_text_upload_basedir'] );
	}

	function get_post_mime_type( int $attachment_id ): string {
		if ( isset( $GLOBALS['maca_alt_text_replace_on_mime'][ $attachment_id ] ) ) {
			$replacement = (string) $GLOBALS['maca_alt_text_replace_on_mime'][ $attachment_id ];
			$path = (string) ( $GLOBALS['maca_alt_text_attachments'][ $attachment_id ]['file'] ?? '' );
			unset( $GLOBALS['maca_alt_text_replace_on_mime'][ $attachment_id ] );
			unlink( $path );
			file_put_contents( $path, $replacement );
		}
		return (string) ( $GLOBALS['maca_alt_text_attachments'][ $attachment_id ]['mime'] ?? '' );
	}

	function get_post_meta( int $attachment_id, string $key, bool $single = false ): string {
		unset( $key, $single );
		return (string) ( $GLOBALS['maca_alt_text_attachments'][ $attachment_id ]['alt'] ?? '' );
	}

	function get_locale(): string {
		return 'en_US';
	}

	function npcink_cloud_addon_upload_wordpress_ai_alt_text_source( array $file, string $trace_id = '', string $idempotency_key = '' ) {
		if ( ! is_object( $GLOBALS['maca_alt_text_client'] ) ) {
			return new WP_Error( 'cloud_wp_ai_alt_text_verified_client_required' );
		}
		return $GLOBALS['maca_alt_text_client']->upload_wordpress_ai_alt_text_source( $file, $trace_id, $idempotency_key );
	}

	function npcink_cloud_addon_execute_wordpress_ai_connector_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
		if ( ! is_object( $GLOBALS['maca_alt_text_client'] ) ) {
			return new WP_Error( 'cloud_wp_ai_alt_text_verified_client_required' );
		}
		return $GLOBALS['maca_alt_text_client']->execute_wordpress_ai_connector_runtime( $request, $trace_id, $idempotency_key );
	}

	final class Maca_Alt_Text_Client_Stub {
		/** @var list<array<string,mixed>> */
		public $calls = array();

		/** @var array<string,mixed>|WP_Error */
		public $upload_result;

		public function __construct() {
			$this->upload_result = array( 'artifact_id' => 'art_0123456789abcdef0123456789abcdef' );
		}

		public function upload_wordpress_ai_alt_text_source( array $file, string $trace_id, string $idempotency_key ) {
			$this->calls[] = array(
				'method'          => 'upload',
				'file'            => $file,
				'trace_id'        => $trace_id,
				'idempotency_key' => $idempotency_key,
			);
			return $this->upload_result;
		}

		public function execute_wordpress_ai_connector_runtime( array $request, string $trace_id, string $idempotency_key ): array {
			$this->calls[] = array(
				'method'          => 'execute',
				'request'         => $request,
				'trace_id'        => $trace_id,
				'idempotency_key' => $idempotency_key,
			);
			return array(
				'data' => array(
					'run_id' => 'run_alt_text_1',
					'result' => array(
						'contract_version' => 'cloud_connector_result.v1',
						'suggestion_only' => true,
						'connector_id' => 'npcink-cloud-addon',
						'operation_contract' => array(
							'contract_version' => 'wordpress_operation.v1',
							'task' => 'alt_text_suggest',
						),
						'output' => array( 'output_text' => 'A blue ceramic mug.' ),
					),
				),
			);
		}
	}

	require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-connector.php';

	$fixture_root = sys_get_temp_dir() . '/npcink-alt-text-' . bin2hex( random_bytes( 6 ) );
	$upload_root  = $fixture_root . '/uploads';
	mkdir( $upload_root, 0700, true );
	$GLOBALS['maca_alt_text_upload_basedir'] = $upload_root;

	/**
	 * Seeds one attachment fixture.
	 *
	 * @param int    $id Attachment ID.
	 * @param string $path File path.
	 * @param string $stored_mime Stored MIME.
	 * @param string $post_type Post type.
	 * @param bool   $allowed Permission result.
	 * @return void
	 */
	function maca_seed_alt_text_attachment( int $id, string $path, string $stored_mime = 'image/png', string $post_type = 'attachment', bool $allowed = true ): void {
		$GLOBALS['maca_alt_text_permissions'][ $id ] = $allowed;
		$GLOBALS['maca_alt_text_attachments'][ $id ] = array(
			'file' => $path,
			'mime' => $stored_mime,
			'alt'  => 'Existing alt',
			'post' => (object) array(
				'post_type'    => $post_type,
				'post_title'   => 'Blue mug',
				'post_excerpt' => 'On a white table',
			),
		);
	}

	$png_bytes = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR42mP8z8BQDwAFgwJ/l8r2GQAAAABJRU5ErkJggg==', true );
	$gif_bytes = base64_decode( 'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==', true );
	$source_path = $upload_root . '/blue-mug.png';
	file_put_contents( $source_path, $png_bytes );
	maca_seed_alt_text_attachment( 123, $source_path );
	$client = new Maca_Alt_Text_Client_Stub();
	$GLOBALS['maca_alt_text_client'] = $client;
	$result = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::dispatch( 123, 'Generate accessible alt text.' );

	$upload_call  = $client->calls[0] ?? array();
	$execute_call = $client->calls[1] ?? array();
	$scene_request = $execute_call['request']['operation_contract']['request'] ?? array();
	maca_assert(
		is_array( $result )
		&& array( 'upload', 'execute' ) === array_column( $client->calls, 'method' )
		&& $upload_call['trace_id'] === $execute_call['trace_id']
		&& $upload_call['idempotency_key'] !== $execute_call['idempotency_key'],
		'Behavior: authorized alt-text attachment upload precedes execute with one trace and distinct idempotency keys.'
	);
	maca_assert(
		array( 'contents', 'filename', 'mime_type' ) === array_keys( $upload_call['file'] )
		&& 'blue-mug.png' === $upload_call['file']['filename']
		&& 'image/png' === $upload_call['file']['mime_type']
		&& file_get_contents( $source_path ) === $upload_call['file']['contents'],
		'Behavior: the upload descriptor contains only bounded local bytes, filename, and detected MIME.'
	);
	maca_assert(
		array( 'source_artifact_id', 'prompt', 'filename', 'title', 'existing_alt', 'existing_caption', 'locale' ) === array_keys( $scene_request )
		&& 'art_0123456789abcdef0123456789abcdef' === $scene_request['source_artifact_id']
		&& false === strpos( wp_json_encode( $scene_request ), 'image_url' )
		&& false === strpos( wp_json_encode( $scene_request ), 'mime_type' )
		&& false === strpos( wp_json_encode( $scene_request ), 'scene_gate' )
		&& false === strpos( wp_json_encode( $scene_request ), 'base64' ),
		'Behavior: execute receives only the source artifact id and bounded text context.'
	);

	$long_context = 'X' . str_repeat( '这是关于无障碍旅行规划的文章上下文。', 40 ) . 'FULL_ARTICLE_END_SENTINEL';
	$upstream_prompt = 'Generate alt text for this image. Ensure the alt text you return matches the language of the content in the <additional-context> tag.'
		. "\n\n<additional-context>" . $long_context . '</additional-context>';
	$long_prompt_client = new Maca_Alt_Text_Client_Stub();
	$GLOBALS['maca_alt_text_client'] = $long_prompt_client;
	$long_prompt_result = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::dispatch( 123, $upstream_prompt );
	$long_execute_call  = $long_prompt_client->calls[1] ?? array();
	$bounded_prompt     = (string) ( $long_execute_call['request']['operation_contract']['request']['prompt'] ?? '' );
	$bounded_characters = array();
	$bounded_length     = function_exists( 'mb_strlen' )
		? mb_strlen( $bounded_prompt )
		: preg_match_all( '/./us', $bounded_prompt, $bounded_characters );
	maca_assert(
		is_array( $long_prompt_result )
		&& array( 'upload', 'execute' ) === array_column( $long_prompt_client->calls, 'method' )
		&& 1 === preg_match( '//u', $bounded_prompt )
		&& 500 === $bounded_length
		&& str_starts_with( $bounded_prompt, 'Generate alt text for this image.' )
		&& false !== strpos( $bounded_prompt, 'matches the language of the content' )
		&& false !== strpos( $bounded_prompt, '<additional-context>' )
		&& false !== strpos( $bounded_prompt, '无障碍旅行规划' )
		&& false === strpos( $bounded_prompt, 'FULL_ARTICLE_END_SENTINEL' ),
		'Behavior: the real upstream alt-text prompt keeps its goal, language hint, and bounded context while upload and execute continue normally.'
	);
	$connector_source = maca_read( MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-connector.php' );
	maca_assert(
		false === strpos( $connector_source, 'wp_' . 'insert_post' )
		&& false === strpos( $connector_source, 'wp_' . 'update_post' )
		&& false === strpos( $connector_source, 'wp_' . 'update_attachment_metadata' ),
		'Behavior: the alt-text handoff exposes no WordPress write call.'
	);

	foreach ( array( true, false, 123.0, 123.5, '123x', '', null ) as $invalid_attachment_id ) {
		$normalized_id = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::attachment_id_from_ability_input(
			array( 'attachment_id' => $invalid_attachment_id )
		);
		maca_assert( is_wp_error( $normalized_id ), 'Behavior: bool, float, empty, and non-digit attachment IDs are rejected.' );
	}
	maca_assert(
		123 === Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::attachment_id_from_ability_input( array( 'attachment_id' => '123' ) ),
		'Behavior: a positive digit-only attachment ID string normalizes to an integer.'
	);
	$GLOBALS['maca_alt_text_client'] = null;
	$unverified_dispatch = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::dispatch( 123, 'Generate alt text.' );
	maca_assert(
		is_wp_error( $unverified_dispatch )
		&& 'cloud_wp_ai_alt_text_verified_client_required' === $unverified_dispatch->get_error_code(),
		'Behavior: the handoff requires a verified runtime client before reading the local attachment.'
	);
	Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context(
		'ai/title-generation',
		array( 'attachment_id' => 999 )
	);
	maca_assert(
		! Npcink_Cloud_WordPress_AI_Connector::has_alt_text_ability_context(),
		'Behavior: unrelated WordPress abilities cannot open the alt-text attachment context.'
	);
	Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context(
		'ai/alt-text-generation',
		array( 'attachment_id' => 123 )
	);
	$captured_context = Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context();
	maca_assert(
		array( 'attachment_id' => 123 ) === $captured_context
		&& ! Npcink_Cloud_WordPress_AI_Connector::has_alt_text_ability_context()
		&& array() === Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context(),
		'Behavior: the validated alt-text ability context is explicit, name-gated, and one-shot.'
	);

	$failure_cases = array();
	$failure_cases['missing attachment'] = array( 0, new Maca_Alt_Text_Client_Stub() );
	$failure_cases['permission denied'] = array( 124, new Maca_Alt_Text_Client_Stub() );
	maca_seed_alt_text_attachment( 124, $source_path, 'image/png', 'attachment', false );
	$failure_cases['not attachment'] = array( 125, new Maca_Alt_Text_Client_Stub() );
	maca_seed_alt_text_attachment( 125, $source_path, 'image/png', 'post', true );
	$outside_path = $fixture_root . '/outside.png';
	file_put_contents( $outside_path, $png_bytes );
	$failure_cases['outside uploads'] = array( 126, new Maca_Alt_Text_Client_Stub() );
	maca_seed_alt_text_attachment( 126, $outside_path );
	$png_path = $upload_root . '/mismatch.png';
	file_put_contents( $png_path, $png_bytes );
	$failure_cases['mismatched mime'] = array( 127, new Maca_Alt_Text_Client_Stub() );
	maca_seed_alt_text_attachment( 127, $png_path, 'image/jpeg' );
	$gif_path = $upload_root . '/unsupported.gif';
	file_put_contents( $gif_path, $gif_bytes );
	$failure_cases['unsupported mime'] = array( 128, new Maca_Alt_Text_Client_Stub() );
	maca_seed_alt_text_attachment( 128, $gif_path, 'image/gif' );
	$large_path = $upload_root . '/too-large.jpg';
	file_put_contents( $large_path, str_repeat( 'x', 8388609 ) );
	$failure_cases['oversized source'] = array( 129, new Maca_Alt_Text_Client_Stub() );
	maca_seed_alt_text_attachment( 129, $large_path, 'image/jpeg' );
	$changed_path = $upload_root . '/changed.png';
	file_put_contents( $changed_path, $png_bytes );
	$failure_cases['file replaced after validation'] = array( 130, new Maca_Alt_Text_Client_Stub() );
	maca_seed_alt_text_attachment( 130, $changed_path );
	$GLOBALS['maca_alt_text_replace_on_mime'][ 130 ] = strrev( $png_bytes );

	foreach ( $failure_cases as $label => $case ) {
		$GLOBALS['maca_alt_text_client'] = $case[1];
		$failed = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::dispatch( $case[0], 'Generate alt text.' );
		maca_assert(
			is_wp_error( $failed ) && array() === $case[1]->calls,
			'Behavior: ' . $label . ' fails before Cloud upload or execute.'
		);
	}

	$url_client = new Maca_Alt_Text_Client_Stub();
	$GLOBALS['maca_alt_text_client'] = $url_client;
	try {
		Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::dispatch( 'https://cdn.example.test/image.jpg', 'Generate alt text.' );
		$url_rejected = false;
	} catch ( \TypeError $error ) {
		$url_rejected = true;
	}
	maca_assert( $url_rejected && array() === $url_client->calls, 'Behavior: external URL input is not an accepted handoff shape.' );

	$upload_error_client = new Maca_Alt_Text_Client_Stub();
	$upload_error_client->upload_result = new WP_Error( 'upload_failed', 'Upload failed.' );
	$GLOBALS['maca_alt_text_client'] = $upload_error_client;
	$upload_error = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::dispatch( 123, 'Generate alt text.' );
	maca_assert(
		is_wp_error( $upload_error ) && array( 'upload' ) === array_column( $upload_error_client->calls, 'method' ),
		'Behavior: upload failure prevents runtime execute.'
	);

	$invalid_artifact_client = new Maca_Alt_Text_Client_Stub();
	$invalid_artifact_client->upload_result = array( 'artifact_id' => 'artifact-invalid' );
	$GLOBALS['maca_alt_text_client'] = $invalid_artifact_client;
	$invalid_artifact = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::dispatch( 123, 'Generate alt text.' );
	maca_assert(
		is_wp_error( $invalid_artifact ) && array( 'upload' ) === array_column( $invalid_artifact_client->calls, 'method' ),
		'Behavior: invalid Cloud artifact identity prevents runtime execute.'
	);

	foreach ( array( $source_path, $outside_path, $png_path, $gif_path, $large_path, $changed_path ) as $fixture_file ) {
		unlink( $fixture_file );
	}
	rmdir( $upload_root );
	rmdir( $fixture_root );
}
