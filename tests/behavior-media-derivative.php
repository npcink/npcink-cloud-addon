<?php
/**
 * Behavior tests for the artifact-referenced media delivery contract.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

/**
 * Returns one exact Cloud 12-field media derivative descriptor.
 *
 * @param string $artifact_id Artifact id.
 * @param string $contents Image bytes.
 * @param string $expires_at Expiry.
 * @return array<string,mixed>
 */
function maca_cloud_derivative_artifact( string $artifact_id, string $contents, string $expires_at ): array {
	$checksum = hash( 'sha256', $contents );

	return array(
		'artifact_id'        => $artifact_id,
		'artifact_reference' => array( 'artifact_id' => $artifact_id ),
		'expires_at'         => $expires_at,
		'suggested_filename' => 'media-derivative-png-' . substr( $checksum, 0, 8 ) . '.png',
		'filename_basis'     => array(
			'owner'                          => 'wordpress_write_ability_final',
			'strategy'                       => 'format_checksum',
			'final_sanitize_unique_required' => true,
		),
		'mime_type'          => 'image/png',
		'format'             => 'png',
		'width'              => 1,
		'height'             => 1,
		'filesize_bytes'     => strlen( $contents ),
		'checksum'           => 'sha256:' . $checksum,
		'processing_warnings' => array(),
	);
}

/**
 * Returns one exact raw run-result HTTP response.
 *
 * @param array<string,mixed> $artifact Cloud descriptor.
 * @return array<string,mixed>
 */
function maca_cloud_run_result_response( array $artifact ): array {
	return array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'run_id'     => 'run_media_1',
					'status'     => 'succeeded',
					'job_type'   => 'generate_optimized_media_derivative',
					'created_at' => '2026-07-16T00:00:00+00:00',
					'updated_at' => '2026-07-16T00:00:01+00:00',
					'result'     => array(
						'artifact_type'    => 'media_derivative_artifact',
						'contract_version' => 'media_derivative_result.v1',
						'workflow_metadata' => array( 'operation' => 'image.transform.v1' ),
						'artifact'         => $artifact,
					),
				),
			)
		),
	);
}

/**
 * Returns one exact media governance canary run-result response.
 *
 * @param string              $status Canary result status.
 * @param array<string,mixed> $artifact Qualified derivative artifact.
 * @param array<int,string>   $reasons Skip reasons.
 * @return array<string,mixed>
 */
function maca_governance_canary_run_result_response( string $status, array $artifact = array(), array $reasons = array() ): array {
	$source_sha256 = str_repeat( 'a', 64 );
	$source_bytes = 1000000;
	$output_bytes = 'ready' === $status ? (int) ( $artifact['filesize_bytes'] ?? 700000 ) : 900000;
	$savings_bytes = max( 0, $source_bytes - $output_bytes );
	$qualified = 'ready' === $status;
	$derivative = null;
	if ( $qualified ) {
		$derivative = array(
			'artifact_type'    => 'media_derivative_artifact',
			'contract_version' => 'media_derivative_result.v1',
			'workflow_metadata' => array( 'operation' => 'image.transform.v1' ),
			'artifact'         => $artifact,
		);
	}

	return array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'run_id'     => 'run_media_governance_1',
					'status'     => 'succeeded',
					'job_type'   => 'generate_optimized_media_derivative',
					'created_at' => '2026-08-31T00:00:00+00:00',
					'updated_at' => '2026-08-31T00:00:01+00:00',
					'result'     => array(
						'contract_version'      => 'media_governance_canary_result.v1',
						'artifact_type'         => 'media_governance_canary_preview',
						'status'                => $status,
						'candidate'             => array(
							'candidate_id'      => 'mgc_0123456789abcdef01234567',
							'snapshot_id'       => 'scan_20260831',
							'source_sha256'     => 'sha256:' . $source_sha256,
							'evidence_revision' => 'refs_20260831',
						),
						'source'                => array(
							'artifact_id'    => 'art_' . str_repeat( '1', 32 ),
							'format'         => 'jpeg',
							'mime_type'      => 'image/jpeg',
							'width'          => 800,
							'height'         => 600,
							'filesize_bytes' => $source_bytes,
							'checksum'       => 'sha256:' . $source_sha256,
						),
						'validation'            => array(
							'source_checksum_matches'      => true,
							'dimensions_unchanged'         => true,
							'output_smaller'               => true,
							'source_bytes'                 => $source_bytes,
							'output_bytes'                 => $output_bytes,
							'savings_bytes'                => $savings_bytes,
							'savings_basis_points'         => intdiv( $savings_bytes * 10000, $source_bytes ),
							'minimum_savings_basis_points' => 1500,
							'qualified'                    => $qualified,
							'reasons'                      => $reasons,
						),
						'derivative'            => $derivative,
						'preview_only'          => true,
						'retain_originals'      => true,
						'write_posture'         => $qualified ? 'artifact_only' : 'no_artifact',
						'direct_wordpress_write' => false,
					),
				),
			)
		),
	);
}

/**
 * Returns one exact raw status HTTP response without a result field.
 *
 * @param string              $run_id Run id.
 * @param string              $status Lifecycle status.
 * @param array<string,mixed> $data_error Data-level lifecycle error facts.
 * @param array<string,mixed> $envelope_error Envelope-level lifecycle error facts.
 * @return array<string,mixed>
 */
function maca_cloud_run_status_response( string $run_id, string $status, array $data_error = array(), array $envelope_error = array() ): array {
	$data = array_merge(
		array(
			'run_id'     => $run_id,
			'status'     => $status,
			'job_type'   => 'generate_optimized_media_derivative',
			'created_at' => '2026-07-16T00:00:00+00:00',
			'updated_at' => '2026-07-16T00:00:01+00:00',
		),
		$data_error
	);

	return array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array_merge(
				array(
					'status' => 'ok',
					'data'   => $data,
				),
				$envelope_error
			)
		),
	);
}

/**
 * Queues one exact pull response.
 *
 * @param array<string,mixed> $artifact Local 11-field artifact.
 * @param string              $contents Image bytes.
 * @param string              $delivery_id Delivery id.
 * @param string              $ack_deadline Ack deadline.
 * @return void
 */
function maca_queue_media_pull( array $artifact, string $contents, string $delivery_id, string $ack_deadline ): void {
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array(
			'Content-Type'                   => $artifact['mime_type'],
			'Content-Length'                 => (string) strlen( $contents ),
			'X-Npcink-Artifact-Id'           => $artifact['artifact_id'],
			'X-Npcink-Artifact-Checksum'     => 'sha256:' . hash( 'sha256', $contents ),
			'X-Npcink-Delivery-Id'           => $delivery_id,
			'X-Npcink-Delivery-Ack-Deadline' => $ack_deadline,
		),
		'body'     => $contents,
	);
}

/**
 * Queues one exact ACK response.
 *
 * @param array<string,mixed> $artifact Local artifact.
 * @param string              $contents Received bytes.
 * @param string              $delivery_id Delivery id.
 * @param string              $acknowledged_at Acknowledgement time.
 * @param string              $artifact_expires_at Artifact expiry.
 * @return void
 */
function maca_queue_media_ack( array $artifact, string $contents, string $delivery_id, string $acknowledged_at, string $artifact_expires_at ): void {
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'contract_version'     => 'media_artifact_delivery_ack.v1',
					'delivery_id'          => $delivery_id,
					'artifact_id'          => $artifact['artifact_id'],
					'status'               => 'acknowledged',
					'received_byte_size'   => strlen( $contents ),
					'received_checksum'    => 'sha256:' . hash( 'sha256', $contents ),
					'byte_size_verified'   => true,
					'checksum_verified'    => true,
					'acknowledged_at'      => $acknowledged_at,
					'artifact_expires_at'  => $artifact_expires_at,
					'idempotent_replay'    => false,
					'acknowledgement_scope' => 'verified_transfer_only',
				),
			)
		),
	);
}

/**
 * Queues one successful media upload and derivative job dispatch.
 *
 * @param array<string,mixed> $upload_result Exact media upload result.
 * @param string              $suffix Stable test suffix.
 * @return void
 */
function maca_queue_media_dispatch_success( array $upload_result, string $suffix ): void {
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'run_id'            => 'run_upload_' . $suffix,
					'status'            => 'succeeded',
					'trace_id'          => 'trace-upload-' . $suffix,
					'idempotent_replay' => false,
					'result'            => $upload_result,
				),
			)
		),
	);
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'run_id'            => 'run_media_' . $suffix,
					'status'            => 'queued',
					'trace_id'          => 'trace-dispatch-' . $suffix,
					'idempotent_replay' => false,
					'result'            => array(),
				),
			)
		),
	);
}

$png = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZQmcAAAAASUVORK5CYII=', true );
maca_assert( is_string( $png ), 'Behavior: media test image fixture decodes.' );

maca_reset_test_state();
maca_seed_settings( false );
$unverified = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	maca_ability_fixture(),
	array(
		'artifact_id' => 'art_' . str_repeat( '1', 32 ),
		'expires_at'  => maca_future_expiry(),
	)
);
maca_assert(
	is_wp_error( $unverified ) && 'cloud_runtime_unverified' === $unverified->get_error_code(),
	'Behavior: media dispatch fails closed before verified Cloud credentials.'
);

maca_reset_test_state();
maca_seed_settings( true );
$source_artifact_id = 'art_' . str_repeat( '1', 32 );
$upload_result = array(
	'artifact_type'    => 'media_upload_artifact',
	'contract_version' => 'media_upload_result.v1',
	'artifact'         => array(
		'artifact_id'    => $source_artifact_id,
		'media_kind'     => 'image',
		'status'         => 'available',
		'content_type'   => 'image/png',
		'format'         => 'png',
		'width'          => 1,
		'height'         => 1,
		'filesize_bytes' => strlen( $png ),
		'checksum'       => 'sha256:' . hash( 'sha256', $png ),
		'expires_at'     => maca_future_expiry(),
		'purged_at'      => null,
	),
);
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'headers'  => array( 'Content-Type' => 'application/json' ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'run_id'            => 'run_upload_1',
				'status'            => 'succeeded',
				'trace_id'          => 'trace-upload',
				'idempotent_replay' => false,
				'result'            => $upload_result,
			),
		)
	),
);
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'headers'  => array( 'Content-Type' => 'application/json' ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'run_id'            => 'run_media_1',
				'status'            => 'queued',
				'trace_id'          => 'trace-dispatch',
				'idempotent_replay' => false,
				'result'            => array(),
			),
		)
	),
);
$dispatch = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	maca_ability_fixture(),
	array(
		'bytes'     => $png,
		'filename'  => 'source.png',
		'mime_type' => 'image/png',
	),
	'trace-dispatch',
	'operation-identity'
);
$upload_request = $GLOBALS['maca_http_requests'][0] ?? array();
$job_request    = $GLOBALS['maca_http_requests'][1] ?? array();
$job_body       = json_decode( (string) ( $job_request['args']['body'] ?? '' ), true );
maca_assert(
	is_array( $dispatch )
	&& 2 === count( $GLOBALS['maca_http_requests'] )
	&& array( 'run_id', 'status', 'job_type', 'created_at', 'updated_at', 'artifact', 'warnings', 'error' ) === array_keys( $dispatch )
	&& 'run_media_1' === ( $dispatch['run_id'] ?? null )
	&& 'queued' === ( $dispatch['status'] ?? null )
	&& array() === ( $dispatch['artifact'] ?? null )
	&& str_ends_with( (string) ( $upload_request['url'] ?? '' ), '/v1/runtime/media/uploads' )
	&& false !== strpos( (string) ( $upload_request['args']['body'] ?? '' ), 'name="file"; filename="source.png"' )
	&& str_ends_with( (string) ( $job_request['url'] ?? '' ), '/v1/runtime/media/jobs' )
	&& array( 'request_contract_version', 'operation', 'source_artifact_id', 'params', 'result_ttl_minutes' ) === array_keys( $job_body )
	&& 'media_job_request.v1' === ( $job_body['request_contract_version'] ?? null )
	&& 'image.transform.v1' === ( $job_body['operation'] ?? null )
	&& $source_artifact_id === ( $job_body['source_artifact_id'] ?? null )
	&& 'image' === ( $job_body['params']['source_media_type'] ?? null )
	&& ! isset( $job_body['source'], $job_body['cloud_job_payload'], $job_body['ttl_minutes'] ),
	'Behavior: exact11 available image upload and artifact-referenced media job use the exact new resources.'
);

$governance_ability = maca_ability_fixture();
$governance_ability['cloud_job_payload']['governance'] = array(
	'contract_version'               => 'media_governance_canary.v1',
	'candidate_id'                   => 'mgc_0123456789abcdef01234567',
	'snapshot_id'                    => 'scan_20260831',
	'source_sha256'                  => 'sha256:' . str_repeat( 'a', 64 ),
	'evidence_revision'              => 'refs_20260831',
	'minimum_savings_basis_points'   => 1500,
	'require_dimensions_unchanged'   => true,
	'skip_if_not_beneficial'         => true,
	'retain_originals'               => true,
);
$governance_ability['cloud_job_payload']['batch_context'] = array(
	'batch_id'   => 'media-governance-canary',
	'item_index' => 1,
	'item_count' => 10,
	'chunk_size' => 10,
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'headers'  => array( 'Content-Type' => 'application/json' ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'run_id'            => 'run_media_governance_dispatch',
				'status'            => 'queued',
				'trace_id'          => 'trace-governance-dispatch',
				'idempotent_replay' => false,
				'result'            => array(),
			),
		)
	),
);
$governance_dispatch = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	$governance_ability,
	array(
		'artifact_id' => 'art_' . str_repeat( '1', 32 ),
		'expires_at'  => maca_future_expiry(),
	),
	'trace-governance-dispatch',
	'governance-dispatch'
);
$governance_job_request = $GLOBALS['maca_http_requests'][0] ?? array();
$governance_job_body = json_decode( (string) ( $governance_job_request['args']['body'] ?? '' ), true );
maca_assert(
	is_array( $governance_dispatch )
	&& 1 === count( $GLOBALS['maca_http_requests'] )
	&& array( 'request_contract_version', 'operation', 'source_artifact_id', 'params', 'batch_context', 'governance', 'result_ttl_minutes' ) === array_keys( $governance_job_body )
	&& 'webp' === ( $governance_job_body['params']['target_format'] ?? null )
	&& 'preserve' === ( $governance_job_body['params']['resize_mode'] ?? null )
	&& 10 === ( $governance_job_body['batch_context']['item_count'] ?? null )
	&& 1500 === ( $governance_job_body['governance']['minimum_savings_basis_points'] ?? null )
	&& ! isset( $governance_job_body['params']['crop'], $governance_job_body['params']['watermark'], $governance_job_body['watermark_artifact_id'] ),
	'Behavior: governance canary dispatch adds only the strict preserve-WebP batch and evidence extensions.'
);

foreach ( array( 'item_count', 'crop', 'watermark', 'format', 'unknown_governance_field' ) as $invalid_governance_case ) {
	maca_reset_test_state();
	maca_seed_settings( true );
	$invalid_governance_ability = $governance_ability;
	if ( 'item_count' === $invalid_governance_case ) {
		$invalid_governance_ability['cloud_job_payload']['batch_context']['item_count'] = 11;
	} elseif ( 'crop' === $invalid_governance_case ) {
		$invalid_governance_ability['cloud_job_payload']['crop'] = array( 'aspect_ratio' => '1:1' );
	} elseif ( 'watermark' === $invalid_governance_case ) {
		$invalid_governance_ability['cloud_job_payload']['watermark'] = array( 'type' => 'text', 'text' => 'preview' );
	} elseif ( 'format' === $invalid_governance_case ) {
		$invalid_governance_ability['cloud_job_payload']['target_format'] = 'jpeg';
	} else {
		$invalid_governance_ability['cloud_job_payload']['governance']['unknown'] = true;
	}
	$invalid_governance_dispatch = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
		$invalid_governance_ability,
		array(
			'artifact_id' => 'art_' . str_repeat( '1', 32 ),
			'expires_at'  => maca_future_expiry(),
		)
	);
	maca_assert(
		is_wp_error( $invalid_governance_dispatch ) && array() === $GLOBALS['maca_http_requests'],
		'Behavior: invalid governance canary ' . $invalid_governance_case . ' fails before Cloud transport.'
	);
}

$legacy_upload_descriptor_cases = array(
	'file_path' => array(
		'file_path' => '/tmp/legacy-source.png',
		'filename'  => 'source.png',
		'mime_type' => 'image/png',
	),
	'tmp_name'  => array(
		'tmp_name'  => '/tmp/legacy-source.png',
		'filename'  => 'source.png',
		'mime_type' => 'image/png',
	),
	'name'      => array(
		'bytes'     => $png,
		'name'      => 'legacy-source.png',
		'mime_type' => 'image/png',
	),
);
foreach ( $legacy_upload_descriptor_cases as $legacy_field => $legacy_descriptor ) {
	maca_reset_test_state();
	maca_seed_settings( true );
	$legacy_upload = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
		maca_ability_fixture(),
		$legacy_descriptor,
		'trace-legacy-upload-' . $legacy_field,
		'legacy-upload-' . $legacy_field
	);
	maca_assert(
		is_wp_error( $legacy_upload )
		&& 'cloud_media_derivative_upload_descriptor_legacy_field' === $legacy_upload->get_error_code()
		&& 0 === count( $GLOBALS['maca_http_requests'] ),
		'Behavior: legacy media upload descriptor field ' . $legacy_field . ' fails closed before Cloud transport.'
	);
}

maca_reset_test_state();
maca_seed_settings( true );
$unknown_upload = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
	maca_ability_fixture(),
	array(
		'bytes'        => $png,
		'filename'     => 'source.png',
		'mime_type'    => 'image/png',
		'unknown_hint' => 'ignored-before-b5',
	),
	'trace-unknown-upload',
	'unknown-upload'
);
maca_assert(
	is_wp_error( $unknown_upload )
	&& 'cloud_media_derivative_upload_descriptor_unknown_field' === $unknown_upload->get_error_code()
	&& 0 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: unknown media upload descriptor fields fail closed before Cloud transport.'
);

foreach (
	array(
		'corrupt image bytes' => array(
			'bytes'     => 'not-an-image',
			'filename'  => 'corrupt.png',
			'mime_type' => 'image/png',
		),
		'false declared mime' => array(
			'bytes'     => $png,
			'filename'  => 'false-jpeg.jpg',
			'mime_type' => 'image/jpeg',
		),
		'unsupported declared mime' => array(
			'bytes'     => $png,
			'filename'  => 'source.gif',
			'mime_type' => 'image/gif',
		),
	) as $case => $invalid_descriptor
) {
	maca_reset_test_state();
	maca_seed_settings( true );
	$invalid_upload = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
		maca_ability_fixture(),
		$invalid_descriptor,
		'trace-invalid-upload-' . sanitize_key( $case ),
		'invalid-upload-' . sanitize_key( $case )
	);
	maca_assert(
		is_wp_error( $invalid_upload ) && 0 === count( $GLOBALS['maca_http_requests'] ),
		'Behavior: ' . $case . ' fail closed before Cloud media upload.'
	);
}

$ambiguous_upload_descriptor_cases = array(
	'bytes-content' => array(
		'bytes'     => $png,
		'content'   => $png,
		'filename'  => 'source.png',
		'mime_type' => 'image/png',
	),
	'bytes-path'    => array(
		'bytes'     => $png,
		'path'      => '/tmp/ambiguous-source.png',
		'filename'  => 'source.png',
		'mime_type' => 'image/png',
	),
	'content-path'  => array(
		'content'   => $png,
		'path'      => '/tmp/ambiguous-source.png',
		'filename'  => 'source.png',
		'mime_type' => 'image/png',
	),
);
foreach ( $ambiguous_upload_descriptor_cases as $case => $ambiguous_descriptor ) {
	maca_reset_test_state();
	maca_seed_settings( true );
	$ambiguous_upload = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
		maca_ability_fixture(),
		$ambiguous_descriptor,
		'trace-ambiguous-upload-' . $case,
		'ambiguous-upload-' . $case
	);
	maca_assert(
		is_wp_error( $ambiguous_upload )
		&& 'cloud_media_derivative_upload_descriptor_ambiguous_source' === $ambiguous_upload->get_error_code()
		&& 0 === count( $GLOBALS['maca_http_requests'] ),
		'Behavior: media upload descriptor source combination ' . $case . ' fails closed as ambiguous.'
	);
}

$canonical_path = tempnam( sys_get_temp_dir(), 'maca-media-' );
maca_assert(
	is_string( $canonical_path ) && false !== file_put_contents( $canonical_path, $png ),
	'Behavior: canonical path upload fixture is created in the approved temporary directory.'
);
$canonical_upload_descriptor_cases = array(
	'content' => array(
		'content'   => $png,
		'filename'  => 'content-source.png',
		'mime_type' => 'image/png',
	),
	'path'    => array(
		'path'      => $canonical_path,
		'filename'  => 'path-source.png',
		'mime_type' => 'image/png',
	),
);
foreach ( $canonical_upload_descriptor_cases as $canonical_source => $canonical_descriptor ) {
	maca_reset_test_state();
	maca_seed_settings( true );
	maca_queue_media_dispatch_success( $upload_result, $canonical_source );
	$canonical_dispatch = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response(
		maca_ability_fixture(),
		$canonical_descriptor,
		'trace-canonical-upload-' . $canonical_source,
		'canonical-upload-' . $canonical_source
	);
	$canonical_upload_request = $GLOBALS['maca_http_requests'][0] ?? array();
	maca_assert(
		is_array( $canonical_dispatch )
		&& 'queued' === ( $canonical_dispatch['status'] ?? null )
		&& 2 === count( $GLOBALS['maca_http_requests'] )
		&& false !== strpos(
			(string) ( $canonical_upload_request['args']['body'] ?? '' ),
			'filename="' . $canonical_source . '-source.png"'
		),
		'Behavior: canonical ' . $canonical_source . ' media upload descriptor still dispatches successfully.'
	);
}
if ( is_string( $canonical_path ) && is_file( $canonical_path ) ) {
	unlink( $canonical_path );
}

$upload_nonce = (string) ( $upload_request['args']['headers']['X-Npcink-Nonce'] ?? '' );
$job_nonce    = (string) ( $job_request['args']['headers']['X-Npcink-Nonce'] ?? '' );
maca_assert(
	'' !== $upload_nonce
	&& '' !== $job_nonce
	&& $upload_nonce !== $job_nonce
	&& ( $upload_request['args']['headers']['Idempotency-Key'] ?? '' ) !== ( $job_request['args']['headers']['Idempotency-Key'] ?? '' ),
	'Behavior: upload and job POST calls use fresh nonces and resource-specific idempotency keys.'
);

maca_reset_test_state();
maca_seed_settings( true );
$invalid_upload_lifecycle_artifacts = array(
	'legacy exact10 without purged_at' => static function ( array $artifact ) {
		unset( $artifact['purged_at'] );
		return $artifact;
	},
	'non-null purged_at while available' => static function ( array $artifact ) {
		$artifact['purged_at'] = gmdate( 'c', time() );
		return $artifact;
	},
	'unknown lifecycle alias' => static function ( array $artifact ) {
		$artifact['purged'] = false;
		return $artifact;
	},
);
foreach ( $invalid_upload_lifecycle_artifacts as $case => $mutate_artifact ) {
	$invalid_upload_result             = $upload_result;
	$invalid_upload_result['artifact'] = $mutate_artifact( $invalid_upload_result['artifact'] );
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'run_id'            => 'run_upload_invalid',
					'status'            => 'succeeded',
					'trace_id'          => 'trace-upload-invalid',
					'idempotent_replay' => false,
					'result'            => $invalid_upload_result,
				),
			)
		),
	);
	$invalid_upload_client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
	$invalid_upload = $invalid_upload_client->upload_media_artifact(
		array(
			'contents'  => $png,
			'filename'  => 'source.png',
			'mime_type' => 'image/png',
		),
		'trace-upload-invalid',
		'upload-invalid-' . sanitize_key( $case )
	);
	maca_assert(
		is_wp_error( $invalid_upload )
		&& 'cloud_media_upload_artifact_invalid' === $invalid_upload->get_error_code(),
		'Behavior: media upload exact11 validation rejects ' . $case . '.'
	);
}

maca_reset_test_state();
maca_seed_settings( true );
foreach ( array( 'run_media_repeat_1', 'run_media_repeat_2' ) as $repeat_run_id ) {
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'run_id'            => $repeat_run_id,
					'status'            => 'queued',
					'trace_id'          => 'trace-repeat',
					'idempotent_replay' => false,
					'result'            => array(),
				),
			)
		),
	);
}
$repeat_client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
$repeat_client->create_media_job( $job_body, 'trace-repeat', 'same-operation-identity' );
$repeat_client->create_media_job( $job_body, 'trace-repeat', 'same-operation-identity' );
$repeat_nonce_one = (string) ( $GLOBALS['maca_http_requests'][0]['args']['headers']['X-Npcink-Nonce'] ?? '' );
$repeat_nonce_two = (string) ( $GLOBALS['maca_http_requests'][1]['args']['headers']['X-Npcink-Nonce'] ?? '' );
maca_assert(
	'' !== $repeat_nonce_one
	&& '' !== $repeat_nonce_two
	&& $repeat_nonce_one !== $repeat_nonce_two
	&& 'same-operation-identity' === ( $GLOBALS['maca_http_requests'][0]['args']['headers']['Idempotency-Key'] ?? null )
	&& 'same-operation-identity' === ( $GLOBALS['maca_http_requests'][1]['args']['headers']['Idempotency-Key'] ?? null ),
	'Behavior: repeated POSTs with the same trace and idempotency key still receive fresh independent nonces.'
);

maca_reset_test_state();
maca_seed_settings( true );
foreach ( array( 'queued', 'running', 'succeeded' ) as $index => $status ) {
	$GLOBALS['maca_http_response_queue'][] = maca_cloud_run_status_response( 'run_status_' . ( $index + 1 ), $status );
}
$status_projections = array(
	Npcink_Cloud_Media_Derivative_Transport::get_run_projection( 'run_status_1', 'trace-status-queued' ),
	Npcink_Cloud_Media_Derivative_Transport::get_run_projection( 'run_status_2', 'trace-status-running' ),
	Npcink_Cloud_Media_Derivative_Transport::get_run_projection( 'run_status_3', 'trace-status-succeeded' ),
);
foreach ( $status_projections as $index => $status_projection ) {
	maca_assert(
		is_array( $status_projection )
		&& array( 'run_id', 'status', 'job_type', 'created_at', 'updated_at', 'artifact', 'warnings', 'error' ) === array_keys( $status_projection )
		&& array() === $status_projection['artifact']
		&& array() === $status_projection['error']
		&& array( 'queued', 'running', 'succeeded' )[ $index ] === $status_projection['status'],
		'Behavior: queued, running, and succeeded status reads remain exact public8 projections without result artifacts.'
	);
}

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = maca_cloud_run_status_response(
	'run_status_failed',
	'failed',
	array(
		'error_code'    => 'provider.timeout',
		'error_message' => 'Provider timed out.',
		'error_stage'   => 'provider',
	)
);
$GLOBALS['maca_http_response_queue'][] = maca_cloud_run_status_response(
	'run_status_canceled',
	'canceled',
	array(),
	array(
		'error_code'    => 'runtime.canceled',
		'error_message' => 'Run canceled before execution completed.',
		'error_stage'   => 'runtime',
	)
);
$failed_projection   = Npcink_Cloud_Media_Derivative_Transport::get_run_projection( 'run_status_failed', 'trace-status-failed' );
$canceled_projection = Npcink_Cloud_Media_Derivative_Transport::get_run_projection( 'run_status_canceled', 'trace-status-canceled' );
maca_assert(
	is_array( $failed_projection )
	&& 'failed' === ( $failed_projection['status'] ?? null )
	&& array() === ( $failed_projection['artifact'] ?? null )
	&& array(
		'error_code'    => 'provider.timeout',
		'error_message' => 'Provider timed out.',
		'error_stage'   => 'provider',
	) === ( $failed_projection['error'] ?? null ),
	'Behavior: failed status remains public8 and preserves bounded data-level lifecycle errors.'
);
maca_assert(
	is_array( $canceled_projection )
	&& 'canceled' === ( $canceled_projection['status'] ?? null )
	&& array() === ( $canceled_projection['artifact'] ?? null )
	&& array(
		'error_code'    => 'runtime.canceled',
		'error_message' => 'Run canceled before execution completed.',
		'error_stage'   => 'runtime',
	) === ( $canceled_projection['error'] ?? null ),
	'Behavior: canceled status remains public8 and preserves bounded envelope-level lifecycle errors.'
);

maca_reset_test_state();
maca_seed_settings( true );
$artifact_id       = 'art_' . str_repeat( 'a', 32 );
$descriptor_expiry = gmdate( 'Y-m-d\TH:i:s\Z', time() + 3600 );
$cloud_artifact    = maca_cloud_derivative_artifact( $artifact_id, $png, $descriptor_expiry );
$GLOBALS['maca_http_response_queue'][] = maca_cloud_run_result_response( $cloud_artifact );
$projection = Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( 'run_media_1', 'trace-result' );
maca_assert(
	is_array( $projection )
	&& array( 'run_id', 'status', 'job_type', 'created_at', 'updated_at', 'artifact', 'warnings', 'error' ) === array_keys( $projection )
	&& $cloud_artifact === $projection['artifact']
	&& ! isset( $projection['derivative'] ),
	'Behavior: raw data.result is accepted only as exact media_derivative_result.v1 and projected once.'
);

$proposal = Npcink_Cloud_Media_Derivative_Transport::build_local_proposal_payload(
	maca_ability_fixture(),
	$projection,
	$projection['artifact']
);
$proposal_artifact = is_array( $proposal ) ? ( $proposal['artifact'] ?? array() ) : array();
maca_assert(
	is_array( $proposal )
	&& array( 'artifact_id', 'expires_at', 'mime_type', 'format', 'width', 'height', 'filesize_bytes', 'sha256', 'suggested_filename', 'filename_basis', 'processing_warnings' ) === array_keys( $proposal_artifact )
	&& hash( 'sha256', $png ) === ( $proposal_artifact['sha256'] ?? null )
	&& ! isset( $proposal_artifact['checksum'], $proposal_artifact['artifact_reference'] )
	&& 'local_wordpress_host' === ( $proposal['final_write_owner'] ?? null ),
	'Behavior: exact Addon projection builds an exact 11-field local proposal artifact without Cloud-only fields.'
);

$canary_cloud_artifact = maca_cloud_derivative_artifact(
	'art_' . str_repeat( 'c', 32 ),
	str_repeat( 'w', 700000 ),
	maca_future_expiry()
);
$canary_cloud_artifact['suggested_filename'] = 'media-governance-canary.webp';
$canary_cloud_artifact['mime_type'] = 'image/webp';
$canary_cloud_artifact['format'] = 'webp';
$canary_cloud_artifact['width'] = 800;
$canary_cloud_artifact['height'] = 600;

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = maca_governance_canary_run_result_response( 'ready', $canary_cloud_artifact );
$ready_canary_result = Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( 'run_media_governance_1', 'trace-governance-ready' );
$ready_canary_proposal = is_array( $ready_canary_result )
	? Npcink_Cloud_Media_Derivative_Transport::build_local_proposal_payload(
		maca_ability_fixture(),
		$ready_canary_result,
		$ready_canary_result['artifact']
	)
	: $ready_canary_result;
maca_assert(
	is_array( $ready_canary_result )
	&& array( 'run_id', 'status', 'job_type', 'created_at', 'updated_at', 'artifact', 'warnings', 'error', 'governance_canary' ) === array_keys( $ready_canary_result )
	&& 'ready' === ( $ready_canary_result['governance_canary']['status'] ?? null )
	&& true === ( $ready_canary_result['governance_canary']['validation']['qualified'] ?? null )
	&& $canary_cloud_artifact === ( $ready_canary_result['artifact'] ?? null )
	&& is_array( $ready_canary_proposal )
	&& 'local_wordpress_host' === ( $ready_canary_proposal['final_write_owner'] ?? null )
	&& true === ( $ready_canary_proposal['approval_required'] ?? null ),
	'Behavior: qualified governance wrapper is strictly unwrapped into the existing local proposal handoff.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = maca_governance_canary_run_result_response( 'skipped', array(), array( 'minimum_savings_not_met' ) );
$skipped_canary_result = Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( 'run_media_governance_1', 'trace-governance-skipped' );
maca_assert(
	is_array( $skipped_canary_result )
	&& array() === ( $skipped_canary_result['artifact'] ?? null )
	&& 'skipped' === ( $skipped_canary_result['governance_canary']['status'] ?? null )
	&& array( 'minimum_savings_not_met' ) === ( $skipped_canary_result['governance_canary']['validation']['reasons'] ?? null )
	&& 1 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: skipped governance wrapper exposes bounded reasons and never exposes or pulls an artifact.'
);

$skipped_canary_raw = json_decode( (string) maca_governance_canary_run_result_response( 'skipped', array(), array( 'minimum_savings_not_met' ) )['body'], true );
$skipped_canary_artifact = Npcink_Cloud_Media_Derivative_Transport::artifact_from_cloud_result( $skipped_canary_raw );
maca_assert(
	is_wp_error( $skipped_canary_artifact )
	&& 'cloud_media_governance_canary_has_no_artifact' === $skipped_canary_artifact->get_error_code(),
	'Behavior: skipped governance canaries cannot enter artifact receive, ACK, or proposal paths.'
);

foreach ( array( 'candidate_checksum', 'unknown_wrapper_field', 'qualified_without_derivative' ) as $invalid_canary_case ) {
	maca_reset_test_state();
	maca_seed_settings( true );
	$invalid_canary_response = maca_governance_canary_run_result_response( 'ready', $canary_cloud_artifact );
	$invalid_canary_body = json_decode( (string) $invalid_canary_response['body'], true );
	if ( 'candidate_checksum' === $invalid_canary_case ) {
		$invalid_canary_body['data']['result']['candidate']['source_sha256'] = 'sha256:' . str_repeat( 'b', 64 );
	} elseif ( 'unknown_wrapper_field' === $invalid_canary_case ) {
		$invalid_canary_body['data']['result']['unknown'] = true;
	} else {
		$invalid_canary_body['data']['result']['derivative'] = null;
	}
	$invalid_canary_response['body'] = wp_json_encode( $invalid_canary_body );
	$GLOBALS['maca_http_response_queue'][] = $invalid_canary_response;
	$invalid_canary_result = Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( 'run_media_governance_1', 'trace-invalid-canary' );
	maca_assert(
		is_wp_error( $invalid_canary_result )
		&& 'cloud_media_governance_canary_result_invalid' === $invalid_canary_result->get_error_code(),
		'Behavior: malformed governance canary ' . $invalid_canary_case . ' fails closed.'
	);
}

maca_reset_test_state();
maca_seed_settings( true );
$oversized_cloud_artifact = $cloud_artifact;
$oversized_cloud_artifact['width'] = 8193;
$GLOBALS['maca_http_response_queue'][] = maca_cloud_run_result_response( $oversized_cloud_artifact );
$oversized_cloud_result = Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( 'run_media_1', 'trace-oversized-dimension' );
maca_assert(
	is_wp_error( $oversized_cloud_result )
	&& 'cloud_media_derivative_artifact_facts_invalid' === $oversized_cloud_result->get_error_code(),
	'Behavior: the Cloud 12-field descriptor rejects a single axis above 8192 pixels.'
);

maca_reset_test_state();
maca_seed_settings( true );
$oversized_area_cloud_artifact = $cloud_artifact;
$oversized_area_cloud_artifact['width']  = 4097;
$oversized_area_cloud_artifact['height'] = 4096;
$GLOBALS['maca_http_response_queue'][] = maca_cloud_run_result_response( $oversized_area_cloud_artifact );
$oversized_area_cloud_result = Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( 'run_media_1', 'trace-oversized-area' );
maca_assert(
	is_wp_error( $oversized_area_cloud_result )
	&& 'cloud_media_derivative_artifact_facts_invalid' === $oversized_area_cloud_result->get_error_code(),
	'Behavior: the Cloud 12-field descriptor rejects an image area above 16777216 pixels.'
);

maca_reset_test_state();
maca_seed_settings( true );
$boundary_cloud_artifact = $cloud_artifact;
$boundary_cloud_artifact['width']  = 8192;
$boundary_cloud_artifact['height'] = 2048;
$GLOBALS['maca_http_response_queue'][] = maca_cloud_run_result_response( $boundary_cloud_artifact );
$boundary_cloud_result = Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( 'run_media_1', 'trace-boundary-area' );
$boundary_proposal = is_array( $boundary_cloud_result )
	? Npcink_Cloud_Media_Derivative_Transport::build_local_proposal_payload(
		maca_ability_fixture(),
		$boundary_cloud_result,
		$boundary_cloud_result['artifact']
	)
	: $boundary_cloud_result;
maca_assert(
	is_array( $boundary_cloud_result )
	&& is_array( $boundary_proposal )
	&& 8192 === ( $boundary_proposal['artifact']['width'] ?? null )
	&& 2048 === ( $boundary_proposal['artifact']['height'] ?? null ),
	'Behavior: exact 8192-axis and 16777216-area boundary facts pass Cloud12 to local11 projection.'
);

maca_reset_test_state();
maca_seed_settings( true );
$impossible_expiry_artifact = $cloud_artifact;
$impossible_expiry_artifact['expires_at'] = '2027-02-31T00:00:00Z';
$GLOBALS['maca_http_response_queue'][] = maca_cloud_run_result_response( $impossible_expiry_artifact );
$impossible_expiry_result = Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( 'run_media_1', 'trace-impossible-expiry' );
maca_assert(
	is_wp_error( $impossible_expiry_result )
	&& 'cloud_media_derivative_artifact_expiry_missing' === $impossible_expiry_result->get_error_code(),
	'Behavior: impossible calendar dates fail strict artifact timestamp validation.'
);

maca_reset_test_state();
maca_seed_settings( true );
$non_utc_expiry_artifact = $cloud_artifact;
$non_utc_expiry_artifact['expires_at'] = '2030-01-01T08:00:00+08:00';
$GLOBALS['maca_http_response_queue'][] = maca_cloud_run_result_response( $non_utc_expiry_artifact );
$non_utc_expiry_result = Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( 'run_media_1', 'trace-non-utc-expiry' );
maca_assert(
	is_wp_error( $non_utc_expiry_result )
	&& 'cloud_media_derivative_artifact_expiry_missing' === $non_utc_expiry_result->get_error_code(),
	'Behavior: non-UTC RFC3339 offsets fail the canonical UTC artifact contract.'
);

maca_reset_test_state();
maca_seed_settings( true );
$utc_offset_expiry_artifact = $cloud_artifact;
$utc_offset_expiry_artifact['expires_at'] = gmdate( 'Y-m-d\TH:i:s', time() + 3600 ) . '+00:00';
$GLOBALS['maca_http_response_queue'][] = maca_cloud_run_result_response( $utc_offset_expiry_artifact );
$utc_offset_expiry_result = Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( 'run_media_1', 'trace-utc-offset-expiry' );
maca_assert(
	is_array( $utc_offset_expiry_result )
	&& $utc_offset_expiry_artifact === ( $utc_offset_expiry_result['artifact'] ?? null ),
	'Behavior: the Cloud canonical +00:00 UTC RFC3339 form remains accepted without date normalization.'
);

maca_reset_test_state();
maca_seed_settings( true );
$oversized_local_artifact = $proposal_artifact;
$oversized_local_artifact['width'] = 8193;
$oversized_local_receive = Npcink_Cloud_Media_Derivative_Transport::receive_artifact( $oversized_local_artifact, 'trace-local-oversized-dimension' );
maca_assert(
	is_wp_error( $oversized_local_receive )
	&& 'cloud_media_derivative_local_artifact_facts_invalid' === $oversized_local_receive->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: the local11 receive boundary rejects an axis above 8192 before pull or ACK.'
);

maca_reset_test_state();
maca_seed_settings( true );
$oversized_area_local_artifact = $proposal_artifact;
$oversized_area_local_artifact['width']  = 4097;
$oversized_area_local_artifact['height'] = 4096;
$oversized_area_local_receive = Npcink_Cloud_Media_Derivative_Transport::receive_artifact( $oversized_area_local_artifact, 'trace-local-oversized-area' );
maca_assert(
	is_wp_error( $oversized_area_local_receive )
	&& 'cloud_media_derivative_local_artifact_facts_invalid' === $oversized_area_local_receive->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: the local11 receive boundary rejects an area above 16777216 before pull or ACK.'
);

maca_reset_test_state();
maca_seed_settings( true );
$excess_warning_artifact = $proposal_artifact;
$excess_warning_artifact['processing_warnings'] = array_fill( 0, 21, 'bounded_warning' );
$excess_warning_receive = Npcink_Cloud_Media_Derivative_Transport::receive_artifact( $excess_warning_artifact, 'trace-excess-warnings' );
maca_assert(
	is_wp_error( $excess_warning_receive )
	&& 'cloud_media_derivative_local_artifact_metadata_invalid' === $excess_warning_receive->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: local artifacts exceeding the Toolkit 20-warning limit fail before pull or ACK.'
);

maca_reset_test_state();
maca_seed_settings( true );
$delivery_id       = 'mdl_' . str_repeat( 'b', 32 );
$ack_deadline      = gmdate( 'Y-m-d\TH:i:s\Z', time() + 900 );
$acknowledged_at   = gmdate( 'Y-m-d\TH:i:s\Z', time() + 1 );
$preserved_expiry  = (string) $proposal_artifact['expires_at'];
maca_queue_media_pull( $proposal_artifact, $png, $delivery_id, $ack_deadline );
maca_queue_media_ack( $proposal_artifact, $png, $delivery_id, $acknowledged_at, $preserved_expiry );
$received = Npcink_Cloud_Media_Derivative_Transport::receive_artifact( $proposal_artifact, 'trace-receive' );
$pull_request = $GLOBALS['maca_http_requests'][0] ?? array();
$ack_request  = $GLOBALS['maca_http_requests'][1] ?? array();
maca_assert(
	is_array( $received )
	&& array( 'artifact_id', 'contents', 'mime_type', 'width', 'height', 'filesize_bytes', 'sha256', 'expires_at', 'transfer_evidence', 'delivery_ack' ) === array_keys( $received )
	&& $png === $received['contents']
	&& $preserved_expiry === $received['expires_at']
	&& 'media_artifact_verified_transfer.v1' === ( $received['transfer_evidence']['contract_version'] ?? null )
	&& true === ( $received['transfer_evidence']['image_decoded'] ?? null )
	&& 'verified_transfer_only' === ( $received['delivery_ack']['acknowledgement_scope'] ?? null ),
	'Behavior: exact 11-field proposal artifact produces exact 10-field verified receive output while preserving expiry.'
);
maca_assert(
	str_ends_with( (string) ( $pull_request['url'] ?? '' ), '/v1/runtime/media/artifacts/' . $artifact_id . '/download' )
	&& 'GET' === ( $pull_request['args']['method'] ?? null )
	&& '' !== ( $pull_request['args']['headers']['X-Npcink-Nonce'] ?? '' )
	&& ! isset( $pull_request['args']['headers']['Idempotency-Key'] )
	&& str_ends_with( (string) ( $ack_request['url'] ?? '' ), '/v1/runtime/media/artifacts/' . $artifact_id . '/delivery-ack' )
	&& 'POST' === ( $ack_request['args']['method'] ?? null )
	&& '' !== ( $ack_request['args']['headers']['X-Npcink-Nonce'] ?? '' )
	&& '' !== ( $ack_request['args']['headers']['Idempotency-Key'] ?? '' )
	&& ( $pull_request['args']['headers']['X-Npcink-Nonce'] ?? '' ) !== ( $ack_request['args']['headers']['X-Npcink-Nonce'] ?? '' ),
	'Behavior: signed media pull and ACK use canonical paths, fresh nonces, and ACK-only idempotency.'
);

maca_reset_test_state();
maca_seed_settings( true );
$legacy_local_artifact = $proposal_artifact;
$legacy_local_artifact['checksum'] = 'sha256:' . $legacy_local_artifact['sha256'];
$legacy_receive = Npcink_Cloud_Media_Derivative_Transport::receive_artifact( $legacy_local_artifact );
maca_assert(
	is_wp_error( $legacy_receive )
	&& 'cloud_media_derivative_local_artifact_contract_invalid' === $legacy_receive->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: receive rejects legacy checksum or extra-field artifact shapes before HTTP.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_queue_media_pull( $proposal_artifact, $png, $delivery_id, $ack_deadline );
maca_queue_media_ack(
	$proposal_artifact,
	$png,
	$delivery_id,
	$acknowledged_at,
	gmdate( 'Y-m-d\TH:i:s\Z', strtotime( $proposal_artifact['expires_at'] ) + 60 )
);
$extended_ack = Npcink_Cloud_Media_Derivative_Transport::receive_artifact( $proposal_artifact, 'trace-extended-ack' );
maca_assert(
	is_wp_error( $extended_ack ) && 'cloud_media_derivative_delivery_ack_binding_invalid' === $extended_ack->get_error_code(),
	'Behavior: receive rejects ACK evidence that extends the proposal artifact expiry.'
);

maca_reset_test_state();
maca_seed_settings( true );
$shortened_ack_expiry = gmdate( 'Y-m-d\TH:i:s\Z', time() + 300 );
maca_queue_media_pull( $proposal_artifact, $png, $delivery_id, $ack_deadline );
maca_queue_media_ack( $proposal_artifact, $png, $delivery_id, $acknowledged_at, $shortened_ack_expiry );
$shortened_ack = Npcink_Cloud_Media_Derivative_Transport::receive_artifact( $proposal_artifact, 'trace-shortened-ack' );
maca_assert(
	is_wp_error( $shortened_ack ) && 'cloud_media_derivative_delivery_ack_binding_invalid' === $shortened_ack->get_error_code(),
	'Behavior: receive rejects ACK evidence that shortens the original local11 artifact expiry.'
);

maca_reset_test_state();
maca_seed_settings( true );
$late_ack_deadline     = gmdate( 'Y-m-d\TH:i:s\Z', time() + 60 );
$late_acknowledged_at  = gmdate( 'Y-m-d\TH:i:s\Z', time() + 59 );
$late_preserved_expiry = (string) $proposal_artifact['expires_at'];
maca_queue_media_pull( $proposal_artifact, $png, $delivery_id, $late_ack_deadline );
maca_queue_media_ack(
	$proposal_artifact,
	$png,
	$delivery_id,
	$late_acknowledged_at,
	$late_preserved_expiry
);
$late_expiry_ack = Npcink_Cloud_Media_Derivative_Transport::receive_artifact( $proposal_artifact, 'trace-late-expiry-ack' );
maca_assert(
	is_array( $late_expiry_ack )
	&& $late_preserved_expiry === ( $late_expiry_ack['expires_at'] ?? null )
	&& strtotime( $late_preserved_expiry ) > strtotime( $late_acknowledged_at ) + 360,
	'Behavior: a late valid ACK preserves an artifact expiry more than six minutes after acknowledgement.'
);

maca_reset_test_state();
maca_seed_settings( true );
$ack_request = array(
	'contract_version'   => 'media_artifact_delivery_ack.v1',
	'delivery_id'        => $delivery_id,
	'received_byte_size' => strlen( $png ),
	'received_checksum'  => 'sha256:' . hash( 'sha256', $png ),
);
$client = Npcink_Cloud_Media_Derivative_Transport::verified_client();
maca_queue_media_ack( $proposal_artifact, $png, $delivery_id, '2027-02-31T12:00:00Z', (string) $proposal_artifact['expires_at'] );
$invalid_calendar_ack = is_wp_error( $client ) ? $client : $client->acknowledge_media_artifact_delivery( $artifact_id, $ack_request, 'trace-invalid-calendar-ack', 'invalid-calendar-ack' );
maca_assert(
	is_wp_error( $invalid_calendar_ack ) && 'cloud_media_delivery_ack_response_invalid' === $invalid_calendar_ack->get_error_code(),
	'Behavior: the direct ACK12 normalizer rejects impossible calendar timestamps.'
);

maca_reset_test_state();
maca_seed_settings( true );
$client = Npcink_Cloud_Media_Derivative_Transport::verified_client();
maca_queue_media_ack( $proposal_artifact, $png, $delivery_id, $acknowledged_at, '2099-01-01T08:00:00+08:00' );
$non_utc_ack = is_wp_error( $client ) ? $client : $client->acknowledge_media_artifact_delivery( $artifact_id, $ack_request, 'trace-non-utc-ack', 'non-utc-ack' );
maca_assert(
	is_wp_error( $non_utc_ack ) && 'cloud_media_delivery_ack_response_invalid' === $non_utc_ack->get_error_code(),
	'Behavior: the direct ACK12 normalizer rejects non-UTC RFC3339 timestamps.'
);

maca_reset_test_state();
maca_seed_settings( true );
$bad_decode = str_repeat( 'x', strlen( $png ) );
maca_queue_media_pull( $proposal_artifact, $bad_decode, $delivery_id, $ack_deadline );
$decode_rejected = Npcink_Cloud_Media_Derivative_Transport::receive_artifact( $proposal_artifact, 'trace-bad-decode' );
maca_assert(
	is_wp_error( $decode_rejected )
	&& in_array( $decode_rejected->get_error_code(), array( 'cloud_media_derivative_artifact_checksum_mismatch', 'cloud_media_derivative_artifact_decode_mismatch' ), true )
	&& 1 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: corrupt bytes fail before delivery ACK is sent.'
);

maca_reset_test_state();
maca_seed_settings( true );
$invalid_result = maca_cloud_run_result_response( $cloud_artifact );
$invalid_body   = json_decode( (string) $invalid_result['body'], true );
$invalid_body['data']['result']['derivative'] = $invalid_body['data']['result']['artifact'];
unset( $invalid_body['data']['result']['artifact'] );
$invalid_result['body'] = wp_json_encode( $invalid_body );
$GLOBALS['maca_http_response_queue'][] = $invalid_result;
$legacy_result = Npcink_Cloud_Media_Derivative_Transport::get_run_result_projection( 'run_media_1' );
maca_assert(
	is_wp_error( $legacy_result ) && 'cloud_media_derivative_result_contract_invalid' === $legacy_result->get_error_code(),
	'Behavior: legacy derivative result keys are not consumed.'
);
