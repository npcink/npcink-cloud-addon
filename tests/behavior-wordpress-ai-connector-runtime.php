<?php
/**
 * Behavior tests for the WordPress AI connector runtime seam.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

maca_seed_settings( true );
$client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );

$http_count_before_unverified_alt_text_upload = count( $GLOBALS['maca_http_requests'] );
maca_seed_settings( false );
$unverified_alt_text_upload = $client->upload_wordpress_ai_alt_text_source(
	array(
		'contents'  => 'bounded-image-bytes',
		'filename'  => 'source.jpg',
		'mime_type' => 'image/jpeg',
	)
);
maca_assert(
	is_wp_error( $unverified_alt_text_upload )
	&& 'cloud_runtime_unverified' === $unverified_alt_text_upload->get_error_code()
	&& $http_count_before_unverified_alt_text_upload === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: WordPress AI alt-text upload requires current Save-and-Verify state before reading transport input or sending HTTP.'
);
maca_seed_settings( true );

/**
 * Builds the single active WordPress operation request accepted by the addon.
 *
 * @param string              $task WordPress operation task.
 * @param array<string,mixed> $scene_request Task scene request.
 * @param array<string,mixed> $runtime_options Optional bounded runtime options.
 * @return array<string,mixed>
 */
function maca_wordpress_operation_request( string $task, array $scene_request, array $runtime_options = array() ): array {
	return array_merge(
		array(
			'contract_version'  => 'cloud_connector_runtime.v1',
			'operation_contract' => array(
				'contract_version' => 'wordpress_operation.v1',
				'task'             => $task,
				'request'          => $scene_request,
			),
		),
		$runtime_options
	);
}

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'run_id' => 'run_wp_ai_connector_1',
				'status' => 'succeeded',
				'result' => array(
					'contract_version'  => 'cloud_connector_result.v1',
					'suggestion_only'   => true,
					'connector_id'      => 'npcink-cloud-addon',
					'operation_contract' => array(
						'contract_version' => 'wordpress_operation.v1',
						'task'             => 'title_generation',
					),
					'output' => array( 'output_text' => 'Suggested title' ),
				),
			),
		)
	),
);

$result = $client->execute_wordpress_ai_connector_runtime(
	maca_wordpress_operation_request(
		'title_generation',
		array(
			'source_text'       => '<content>A concise article about the verified Cloud connector.</content>',
			'system_instruction' => 'Return one concise title.',
			'site_knowledge_reference' => array(
				'enabled' => true,
				'mode'    => 'site_title_style',
			),
		),
		array(
			'timeout_seconds' => 120,
			'retention_ttl'   => 999999,
			'retry_max'       => 9,
		)
	),
	'trace-wp-ai-connector',
	'wp-ai-idempotency'
);

$request      = end( $GLOBALS['maca_http_requests'] );
$request_body = json_decode( (string) ( $request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $result ) && 'run_wp_ai_connector_1' === (string) ( $result['data']['run_id'] ?? '' ),
	'Behavior: WordPress AI connector runtime returns the Cloud response for a supported scene task.'
);

maca_assert(
	is_array( $request_body )
	&& 'site_test' === (string) ( $request_body['site_id'] ?? '' )
	&& 'npcink-cloud/connector-runtime' === (string) ( $request_body['ability_name'] ?? '' )
	&& 'cloud_connector_runtime.v1' === (string) ( $request_body['contract_version'] ?? '' )
	&& 'editor' === (string) ( $request_body['channel'] ?? '' )
	&& 'text' === (string) ( $request_body['execution_kind'] ?? '' )
	&& 'inline' === (string) ( $request_body['execution_pattern'] ?? '' )
	&& 'internal' === (string) ( $request_body['data_classification'] ?? '' )
	&& 'result_only' === (string) ( $request_body['storage_mode'] ?? '' )
	&& 60 === (int) ( $request_body['timeout_seconds'] ?? 0 )
	&& 86400 === (int) ( $request_body['retention_ttl'] ?? 0 )
	&& 1 === (int) ( $request_body['retry_max'] ?? -1 )
	&& ! isset( $request_body['policy'] )
	&& 'https://wordpress.example.test' === (string) ( $request_body['input']['site_url'] ?? '' )
	&& 'wordpress' === (string) ( $request_body['input']['platform_kind'] ?? '' )
	&& 'npcink-cloud-addon' === (string) ( $request_body['input']['connector_id'] ?? '' )
	&& '0.1.3-test' === (string) ( $request_body['input']['connector_version'] ?? '' )
	&& true === (bool) ( $request_body['input']['suggestion_only'] ?? false )
	&& 'wordpress_operation.v1' === (string) ( $request_body['input']['operation_contract']['contract_version'] ?? '' )
	&& 'title_generation' === (string) ( $request_body['input']['operation_contract']['task'] ?? '' )
	&& '<content>A concise article about the verified Cloud connector.</content>' === (string) ( $request_body['input']['operation_contract']['request']['source_text'] ?? '' )
	&& 'Return one concise title.' === (string) ( $request_body['input']['operation_contract']['request']['system_instruction'] ?? '' )
	&& true === (bool) ( $request_body['input']['operation_contract']['request']['site_knowledge_reference']['enabled'] ?? false )
	&& 'site_title_style' === (string) ( $request_body['input']['operation_contract']['request']['site_knowledge_reference']['mode'] ?? '' )
	&& ! isset( $request_body['input']['operation_contract']['request']['prompt'] )
	&& ! isset( $request_body['input']['operation_contract']['request']['post_title'] )
	&& ! isset( $request_body['input']['operation_contract']['request']['post_excerpt'] ),
	'Behavior: WordPress AI connector runtime projects a scene-bound no-chat no-write Cloud payload and leaves provider fallback policy to Cloud.'
);

$personal_data_examples = array(
	'Contact the editor at writer@example.com before publishing.',
	'Call the editor at +1 415-555-2671 before publishing.',
	'The example identity number is 11010519491231002X.',
);
foreach ( $personal_data_examples as $index => $personal_data_example ) {
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'run_id' => 'run_wp_ai_connector_pii_' . $index,
					'status' => 'succeeded',
				),
			)
		),
	);
	$personal_data_result = $client->execute_wordpress_ai_connector_runtime(
		maca_wordpress_operation_request(
			'content_classification',
			array( 'prompt' => $personal_data_example )
		),
		'trace-wp-ai-pii-' . $index,
		'wp-ai-pii-' . $index
	);
	$personal_data_request = end( $GLOBALS['maca_http_requests'] );
	$personal_data_body    = json_decode( (string) ( $personal_data_request['args']['body'] ?? '' ), true );

	maca_assert(
		is_array( $personal_data_result )
		&& 'pii' === (string) ( $personal_data_body['data_classification'] ?? '' )
		&& 'no_store' === (string) ( $personal_data_body['storage_mode'] ?? '' )
		&& 0 === (int) ( $personal_data_body['retention_ttl'] ?? -1 ),
		'Behavior: WordPress AI connector runtime selects pii plus no_store for obvious personal data example ' . $index . '.'
	);
}

$legacy_contract = $client->execute_wordpress_ai_connector_runtime(
	array(
		'contract_version' => 'wp_ai_connector_' . 'runtime.v1',
		'task'             => 'title_generation',
		'prompt'           => 'Legacy connector requests are not accepted.',
	)
);
maca_assert(
	is_wp_error( $legacy_contract ) && 'cloud_wp_ai_connector_contract_invalid' === $legacy_contract->get_error_code(),
	'Behavior: WordPress AI connector runtime rejects the removed legacy transport contract.'
);

$injected_reference = $client->execute_wordpress_ai_connector_runtime(
	maca_wordpress_operation_request(
		'title_generation',
		array(
			'source_text' => '<content>Suggest a title for this article.</content>',
			'site_knowledge_reference' => array(
				'enabled' => true,
				'mode'    => 'site_title_style',
				'titles'  => array( 'Injected title' ),
			),
		)
	)
);
maca_assert(
	is_wp_error( $injected_reference )
	&& 'cloud_wp_ai_connector_site_knowledge_reference_fields_not_allowed' === $injected_reference->get_error_code(),
	'Behavior: WordPress AI connector runtime rejects caller-supplied Site Knowledge titles.'
);

$reference_modes = array(
	'content_summary' => 'site_summary_style',
);
foreach ( $reference_modes as $reference_task => $reference_mode ) {
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode( array( 'status' => 'ok', 'run_id' => 'run_' . $reference_task ) ),
	);
	$reference_result = $client->execute_wordpress_ai_connector_runtime(
		maca_wordpress_operation_request(
			$reference_task,
			array(
				'source_text' => '<content>Run the bounded editor task.</content>',
				'site_knowledge_reference' => array(
					'enabled' => true,
					'mode'    => $reference_mode,
				),
			),
		)
	);
	$reference_request = end( $GLOBALS['maca_http_requests'] );
	$reference_body = json_decode( (string) ( $reference_request['args']['body'] ?? '' ), true );
	maca_assert(
		is_array( $reference_result )
		&& $reference_task === (string) ( $reference_body['input']['operation_contract']['task'] ?? '' )
		&& $reference_mode === (string) ( $reference_body['input']['operation_contract']['request']['site_knowledge_reference']['mode'] ?? '' ),
		'Behavior: WordPress AI connector runtime accepts the task-bound Site Knowledge reference mode for ' . $reference_task . '.'
	);
}

foreach ( array( 'excerpt_generation', 'meta_description', 'content_classification' ) as $unsupported_reference_task ) {
	$unsupported_task_reference = $client->execute_wordpress_ai_connector_runtime(
		maca_wordpress_operation_request(
			$unsupported_reference_task,
			array(
				'prompt' => 'Run the bounded editor task.',
				'site_knowledge_reference' => array( 'enabled' => true ),
			),
		)
	);
	maca_assert(
		is_wp_error( $unsupported_task_reference )
		&& 'cloud_wp_ai_connector_site_knowledge_reference_task_not_allowed' === $unsupported_task_reference->get_error_code(),
		'Behavior: WordPress AI generation reference stays disabled for ' . $unsupported_reference_task . ' until quality evidence exists.'
	);
}

$mismatched_reference = $client->execute_wordpress_ai_connector_runtime(
	maca_wordpress_operation_request(
		'content_summary',
		array(
			'source_text' => '<content>Summarize this article.</content>',
			'site_knowledge_reference' => array(
				'enabled' => true,
				'mode'    => 'site_title_style',
			),
		),
	)
);
maca_assert(
	is_wp_error( $mismatched_reference )
	&& 'cloud_wp_ai_connector_site_knowledge_reference_mode_invalid' === $mismatched_reference->get_error_code(),
	'Behavior: WordPress AI connector runtime rejects a Site Knowledge reference mode that does not match the editor task.'
);

$unsupported_reference = $client->execute_wordpress_ai_connector_runtime(
	maca_wordpress_operation_request(
		'content_rewrite',
		array(
			'source_text' => '<block-content>Rewrite this paragraph.</block-content>',
			'site_knowledge_reference' => array(
				'enabled' => true,
				'mode'    => 'site_title_style',
			),
		),
	)
);
maca_assert(
	is_wp_error( $unsupported_reference )
	&& 'cloud_wp_ai_connector_site_knowledge_reference_task_not_allowed' === $unsupported_reference->get_error_code(),
	'Behavior: WordPress AI connector runtime preserves the task-not-allowed error for unsupported reference tasks.'
);

$chat_shape = $client->execute_wordpress_ai_connector_runtime(
	maca_wordpress_operation_request(
		'content_summary',
		array(
			'source_text' => '<content>Summarize this article.</content>',
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => 'Let us chat.',
				),
			),
		)
	)
);
maca_assert(
	is_wp_error( $chat_shape ) && 'cloud_wp_ai_connector_chat_shape_not_allowed' === $chat_shape->get_error_code(),
	'Behavior: WordPress AI connector runtime rejects generic chat message shapes.'
);

$unknown_task = $client->execute_wordpress_ai_connector_runtime(
	maca_wordpress_operation_request( 'free_chat', array( 'prompt' => 'Talk about anything.' ) )
);
maca_assert(
	is_wp_error( $unknown_task ) && 'cloud_wp_ai_connector_task_not_allowed' === $unknown_task->get_error_code(),
	'Behavior: WordPress AI connector runtime rejects unsupported task surfaces.'
);

$large_source_text = $client->execute_wordpress_ai_connector_runtime(
	maca_wordpress_operation_request( 'content_rewrite', array( 'source_text' => str_repeat( 'x', 12001 ) ) )
);
maca_assert(
	is_wp_error( $large_source_text ) && 'cloud_wp_ai_connector_source_text_too_large' === $large_source_text->get_error_code(),
	'Behavior: WordPress AI connector runtime rejects oversized source text for rewrite tasks.'
);

$legacy_text_shape = $client->execute_wordpress_ai_connector_runtime(
	maca_wordpress_operation_request(
		'title_generation',
		array(
			'source_text' => '<content>Current article content.</content>',
			'prompt'      => 'Legacy prompt must not survive the reset.',
		)
	)
);
maca_assert(
	is_wp_error( $legacy_text_shape ) && 'cloud_wp_ai_connector_source_text_shape_invalid' === $legacy_text_shape->get_error_code(),
	'Behavior: WordPress AI text tasks reject legacy prompt and post-field compatibility shapes.'
);

$alt_text_contents    = "bounded-jpeg-bytes\0for-signing";
$alt_text_artifact_id = 'art_0123456789abcdef0123456789abcdef';
$alt_text_checksum    = 'sha256:' . hash( 'sha256', $alt_text_contents );
$alt_text_artifact    = array(
	'artifact_id'    => $alt_text_artifact_id,
	'media_kind'     => 'image',
	'status'         => 'available',
	'content_type'   => 'image/jpeg',
	'format'         => 'jpeg',
	'width'          => 640,
	'height'         => 480,
	'filesize_bytes' => strlen( $alt_text_contents ),
	'checksum'       => $alt_text_checksum,
	'expires_at'     => gmdate( 'c', time() + 1800 ),
);
$alt_text_upload_response = static function ( array $artifact, array $result_overrides = array() ) {
	$result = array(
		'artifact_type'    => 'media_upload_artifact',
		'contract_version' => 'media_upload_result.v1',
		'artifact'         => $artifact,
	);
	foreach ( $result_overrides as $field => $value ) {
		if ( null === $value ) {
			unset( $result[ $field ] );
			continue;
		}
		$result[ $field ] = $value;
	}

	return array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'result' => $result,
				),
			)
		),
	);
};

$GLOBALS['maca_http_response_queue'][] = $alt_text_upload_response( $alt_text_artifact );
$alt_text_uploaded_artifact = $client->upload_wordpress_ai_alt_text_source(
	array(
		'contents'  => $alt_text_contents,
		'filename'  => 'blue-mug.jpg',
		'mime_type' => 'image/jpeg',
	),
	'trace-wp-ai-alt-text-upload',
	'wp-ai-alt-text-upload-idempotency'
);
$alt_text_upload_request = end( $GLOBALS['maca_http_requests'] );
$alt_text_upload_headers = is_array( $alt_text_upload_request['args']['headers'] ?? null ) ? $alt_text_upload_request['args']['headers'] : array();
$alt_text_upload_body    = (string) ( $alt_text_upload_request['args']['body'] ?? '' );
$alt_text_content_type   = (string) ( $alt_text_upload_headers['Content-Type'] ?? '' );
$alt_text_boundary       = str_starts_with( $alt_text_content_type, 'multipart/form-data; boundary=' )
	? substr( $alt_text_content_type, strlen( 'multipart/form-data; boundary=' ) )
	: '';
$alt_text_request_part = array();
preg_match(
	'/Content-Disposition: form-data; name="request"\r\nContent-Type: application\/json\r\n\r\n(\{[^\r]+\})\r\n--/',
	$alt_text_upload_body,
	$alt_text_request_part
);
$alt_text_upload_json = json_decode( (string) ( $alt_text_request_part[1] ?? '' ), true );
$alt_text_upload_canonical = implode(
	"\n",
	array(
		'POST',
		'/v1/runtime/media/uploads',
		'site_test',
		'key_test',
		(string) ( $alt_text_upload_headers['X-Npcink-Timestamp'] ?? '' ),
		(string) ( $alt_text_upload_headers['X-Npcink-Nonce'] ?? '' ),
		'wp-ai-alt-text-upload-idempotency',
		(string) ( $alt_text_upload_headers['traceparent'] ?? '' ),
		hash( 'sha256', $alt_text_upload_body ),
	)
);

maca_assert(
	is_array( $alt_text_uploaded_artifact )
	&& array_keys( $alt_text_artifact ) === array_keys( $alt_text_uploaded_artifact )
	&& $alt_text_artifact === $alt_text_uploaded_artifact,
	'Behavior: WordPress AI alt-text upload returns only the validated bounded artifact descriptor.'
);

maca_assert(
	'https://cloud.example.test/v1/runtime/media/uploads' === (string) ( $alt_text_upload_request['url'] ?? '' )
	&& 'POST' === (string) ( $alt_text_upload_request['args']['method'] ?? '' )
	&& '' !== $alt_text_boundary
	&& 1 === substr_count( $alt_text_upload_body, 'name="request"' )
	&& 1 === substr_count( $alt_text_upload_body, 'name="file"' )
	&& false === strpos( $alt_text_upload_body, 'name="source_file"' )
	&& false === strpos( $alt_text_upload_body, 'name="watermark_file"' )
	&& false !== strpos( $alt_text_upload_body, 'name="file"; filename="blue-mug.jpg"' )
	&& false !== strpos( $alt_text_upload_body, "Content-Type: image/jpeg\r\n\r\n" . $alt_text_contents . "\r\n--" . $alt_text_boundary . '--' )
	&& is_array( $alt_text_upload_json )
	&& array( 'request_contract_version', 'media_kind', 'ttl_minutes' ) === array_keys( $alt_text_upload_json )
	&& 'media_upload_request.v1' === (string) $alt_text_upload_json['request_contract_version']
	&& 'image' === (string) $alt_text_upload_json['media_kind']
	&& 30 === $alt_text_upload_json['ttl_minutes'],
	'Behavior: WordPress AI alt-text upload sends exact request and file multipart parts with the fixed short-TTL contract.'
);

maca_assert(
	hash_equals(
		hash_hmac( 'sha256', $alt_text_upload_canonical, 'secret_test' ),
		(string) ( $alt_text_upload_headers['X-Npcink-Signature'] ?? '' )
	),
	'Behavior: WordPress AI alt-text upload signature covers the exact multipart body sent over HTTP.'
);

foreach ( array( 'image/png' => 'png', 'image/webp' => 'webp' ) as $mapped_mime_type => $mapped_format ) {
	$mapped_contents = 'bounded-' . $mapped_format . '-bytes';
	$mapped_artifact = array_merge(
		$alt_text_artifact,
		array(
			'content_type'   => $mapped_mime_type,
			'format'         => $mapped_format,
			'filesize_bytes' => strlen( $mapped_contents ),
			'checksum'       => 'sha256:' . hash( 'sha256', $mapped_contents ),
		)
	);
	$GLOBALS['maca_http_response_queue'][] = $alt_text_upload_response( $mapped_artifact );
	$mapped_upload_result = $client->upload_wordpress_ai_alt_text_source(
		array(
			'contents'  => $mapped_contents,
			'filename'  => 'source.' . $mapped_format,
			'mime_type' => $mapped_mime_type,
		)
	);
	maca_assert(
		is_array( $mapped_upload_result )
		&& $mapped_mime_type === (string) $mapped_upload_result['content_type']
		&& $mapped_format === (string) $mapped_upload_result['format'],
		'Behavior: WordPress AI alt-text upload preserves the strict ' . $mapped_format . ' MIME-to-format mapping.'
	);
}

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( array( 'status' => 'ok', 'data' => array( 'result' => array() ) ) ),
);
$missing_upload_artifact = $client->upload_wordpress_ai_alt_text_source(
	array(
		'contents'  => $alt_text_contents,
		'filename'  => 'blue-mug.jpg',
		'mime_type' => 'image/jpeg',
	)
);
maca_assert(
	is_wp_error( $missing_upload_artifact )
	&& 'cloud_wp_ai_alt_text_upload_artifact_invalid' === $missing_upload_artifact->get_error_code()
	&& false === strpos( $missing_upload_artifact->get_error_message(), $alt_text_contents ),
	'Behavior: WordPress AI alt-text upload rejects a missing data.result.artifact without exposing source bytes.'
);

$invalid_upload_result_markers = array(
	'missing artifact type'      => array( 'artifact_type' => null ),
	'incorrect artifact type'    => array( 'artifact_type' => 'generic_artifact' ),
	'missing contract version'   => array( 'contract_version' => null ),
	'incorrect contract version' => array( 'contract_version' => 'media_upload_result.v2' ),
);
foreach ( $invalid_upload_result_markers as $case => $result_overrides ) {
	$GLOBALS['maca_http_response_queue'][] = $alt_text_upload_response( $alt_text_artifact, $result_overrides );
	$invalid_marker_result = $client->upload_wordpress_ai_alt_text_source(
		array(
			'contents'  => $alt_text_contents,
			'filename'  => 'blue-mug.jpg',
			'mime_type' => 'image/jpeg',
		)
	);
	maca_assert(
		is_wp_error( $invalid_marker_result )
		&& 'cloud_wp_ai_alt_text_upload_artifact_invalid' === $invalid_marker_result->get_error_code()
		&& false === strpos( $invalid_marker_result->get_error_message(), $alt_text_contents ),
		'Behavior: WordPress AI alt-text upload rejects ' . $case . ' without exposing source bytes.'
	);
}

$invalid_alt_text_uploads = array(
	'missing field'       => array( 'contents' => 'x', 'filename' => 'x.jpg' ),
	'unknown field'       => array( 'contents' => 'x', 'filename' => 'x.jpg', 'mime_type' => 'image/jpeg', 'path' => '/tmp/x.jpg' ),
	'empty contents'      => array( 'contents' => '', 'filename' => 'x.jpg', 'mime_type' => 'image/jpeg' ),
	'non-string contents' => array( 'contents' => array(), 'filename' => 'x.jpg', 'mime_type' => 'image/jpeg' ),
	'oversized contents'  => array( 'contents' => str_repeat( 'x', 8388609 ), 'filename' => 'x.jpg', 'mime_type' => 'image/jpeg' ),
	'unsafe filename'     => array( 'contents' => 'x', 'filename' => '///', 'mime_type' => 'image/jpeg' ),
	'filename type'       => array( 'contents' => 'x', 'filename' => 123, 'mime_type' => 'image/jpeg' ),
	'filename limit'      => array( 'contents' => 'x', 'filename' => str_repeat( 'x', 157 ) . '.jpg', 'mime_type' => 'image/jpeg' ),
	'mime type'           => array( 'contents' => 'x', 'filename' => 'x.gif', 'mime_type' => 'image/gif' ),
);
$http_count_before_invalid_uploads = count( $GLOBALS['maca_http_requests'] );
foreach ( $invalid_alt_text_uploads as $case => $invalid_upload ) {
	$invalid_upload_result = $client->upload_wordpress_ai_alt_text_source( $invalid_upload );
	maca_assert(
		is_wp_error( $invalid_upload_result ),
		'Behavior: WordPress AI alt-text upload rejects ' . $case . ' without exposing source bytes.'
	);
}
maca_assert(
	$http_count_before_invalid_uploads === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: invalid WordPress AI alt-text uploads never reach outbound HTTP.'
);

$mismatched_alt_text_artifacts = array(
	'artifact id'  => array( 'artifact_id' => 'artifact_bad' ),
	'media kind'   => array( 'media_kind' => 'audio' ),
	'status'       => array( 'status' => 'pending' ),
	'content type' => array( 'content_type' => 'image/png' ),
	'format'       => array( 'format' => 'png' ),
	'width'        => array( 'width' => 0 ),
	'height'       => array( 'height' => '480' ),
	'file size'    => array( 'filesize_bytes' => strlen( $alt_text_contents ) + 1 ),
	'checksum'     => array( 'checksum' => hash( 'sha256', $alt_text_contents ) ),
	'expiry'       => array( 'expires_at' => gmdate( 'c', time() - 1 ) ),
	'expiry window' => array( 'expires_at' => gmdate( 'c', time() + 30 ) ),
	'expiry shape' => array( 'expires_at' => 'tomorrow' ),
);
foreach ( $mismatched_alt_text_artifacts as $case => $artifact_override ) {
	$GLOBALS['maca_http_response_queue'][] = $alt_text_upload_response( array_merge( $alt_text_artifact, $artifact_override ) );
	$mismatched_upload_result = $client->upload_wordpress_ai_alt_text_source(
		array(
			'contents'  => $alt_text_contents,
			'filename'  => 'blue-mug.jpg',
			'mime_type' => 'image/jpeg',
		)
	);
	maca_assert(
		is_wp_error( $mismatched_upload_result )
		&& 'cloud_wp_ai_alt_text_upload_artifact_invalid' === $mismatched_upload_result->get_error_code()
		&& false === strpos( $mismatched_upload_result->get_error_message(), $alt_text_contents ),
		'Behavior: WordPress AI alt-text upload rejects artifact ' . $case . ' mismatch without exposing source bytes.'
	);
}

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array( 'run_id' => 'run_wp_ai_alt_text_1' ),
		)
	),
);
$alt_text_result = $client->execute_wordpress_ai_connector_runtime(
	maca_wordpress_operation_request(
		'alt_text_suggest',
		array(
			'source_artifact_id' => $alt_text_uploaded_artifact['artifact_id'],
			'prompt'              => 'Generate accessible alt text for this media item.',
			'filename'            => 'blue-mug.jpg',
			'title'               => 'Blue mug',
			'existing_alt'        => '',
			'existing_caption'    => '',
			'locale'              => 'en_US',
			'max_tokens'          => 96,
		),
		array( 'timeout_seconds' => 90 )
	),
	'trace-wp-ai-alt-text',
	'wp-ai-alt-text-idempotency'
);
$alt_text_request      = end( $GLOBALS['maca_http_requests'] );
$alt_text_request_body = json_decode( (string) ( $alt_text_request['args']['body'] ?? '' ), true );
$alt_text_scene_request = $alt_text_request_body['input']['operation_contract']['request'] ?? null;

maca_assert(
	is_array( $alt_text_result )
	&& 'run_wp_ai_alt_text_1' === (string) ( $alt_text_result['data']['run_id'] ?? '' )
	&& is_array( $alt_text_scene_request )
	&& array( 'source_artifact_id', 'prompt', 'filename', 'title', 'existing_alt', 'existing_caption', 'locale', 'max_tokens' ) === array_keys( $alt_text_scene_request )
	&& $alt_text_artifact_id === (string) $alt_text_scene_request['source_artifact_id']
	&& 'internal' === (string) ( $alt_text_request_body['data_classification'] ?? '' )
	&& 'vision' === (string) ( $alt_text_request_body['execution_kind'] ?? '' )
	&& true === (bool) ( $alt_text_request_body['input']['suggestion_only'] ?? false ),
	'Behavior: WordPress AI alt-text execution forwards only the Artifact id and bounded allowed fields as internal suggestion-only vision input.'
);

$invalid_alt_text_scenes = array(
	'unknown field'       => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'unknown' => 'x' ),
	'legacy url'          => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'url' => 'https://example.test/x.jpg' ),
	'image url'           => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'image_url' => 'https://example.test/x.jpg' ),
	'thumbnail url'       => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'thumbnail_url' => 'https://example.test/x.jpg' ),
	'mime type'           => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'mime_type' => 'image/jpeg' ),
	'data'                => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'data' => 'bytes' ),
	'base64'              => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'base64' => 'eA==' ),
	'scene gate'          => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'scene_gate' => true ),
	'artifact type'       => array( 'source_artifact_id' => 123, 'prompt' => 'Alt text.' ),
	'artifact format'     => array( 'source_artifact_id' => 'artifact_bad', 'prompt' => 'Alt text.' ),
	'prompt type'         => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => array() ),
	'empty prompt'        => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => '  ' ),
	'prompt limit'        => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => str_repeat( 'x', 501 ) ),
	'filename type'       => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'filename' => 1 ),
	'filename limit'      => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'filename' => str_repeat( 'x', 161 ) ),
	'title limit'         => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'title' => str_repeat( 'x', 161 ) ),
	'existing alt limit'  => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'existing_alt' => str_repeat( 'x', 241 ) ),
	'caption limit'       => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'existing_caption' => str_repeat( 'x', 241 ) ),
	'locale limit'        => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'locale' => str_repeat( 'x', 33 ) ),
	'max tokens bool'     => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'max_tokens' => true ),
	'max tokens string'   => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'max_tokens' => '32' ),
	'max tokens low'      => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'max_tokens' => 0 ),
	'max tokens high'     => array( 'source_artifact_id' => $alt_text_artifact_id, 'prompt' => 'Alt text.', 'max_tokens' => 97 ),
);
$http_count_before_invalid_scenes = count( $GLOBALS['maca_http_requests'] );
foreach ( $invalid_alt_text_scenes as $case => $invalid_scene ) {
	$invalid_scene_result = $client->execute_wordpress_ai_connector_runtime(
		maca_wordpress_operation_request( 'alt_text_suggest', $invalid_scene )
	);
	maca_assert( is_wp_error( $invalid_scene_result ), 'Behavior: WordPress AI alt-text execution rejects ' . $case . '.' );
}
maca_assert(
	$http_count_before_invalid_scenes === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: invalid WordPress AI alt-text execution requests never reach outbound HTTP.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_wp_ai_image_1',
			'data'   => array(
				'result' => array(
					'artifact_type'          => 'image_generation_artifacts',
					'contract_version'       => 'image_generation_result.v1',
					'suggestion_only'        => true,
					'requires_local_review'  => true,
					'artifacts'              => array(
						array(
							'artifact_id'        => 'art_wp_ai_image_1',
							'artifact_reference' => array( 'artifact_id' => 'art_wp_ai_image_1' ),
							'status'             => 'available',
							'content_type'       => 'image/png',
						),
					),
				),
			),
		)
	),
);

$image_result = $client->execute_wordpress_ai_image_generation_runtime(
	array(
		'contract_version' => 'image_generation_request.v1',
		'task'             => 'image_generation',
		'prompt'           => 'A clean product image of a blue ceramic mug.',
		'n'                => 2,
		'aspect_ratio'     => '16:9',
		'resolution'       => 'medium',
		'timeout_seconds'  => 120,
	),
	'trace-wp-ai-image',
	'wp-ai-image-idempotency'
);
$image_request      = end( $GLOBALS['maca_http_requests'] );
$image_request_body = json_decode( (string) ( $image_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $image_result ) && 'run_wp_ai_image_1' === (string) ( $image_result['run_id'] ?? '' ),
	'Behavior: WordPress AI image generation runtime returns the Cloud response for a supported image scene task.'
);

maca_assert(
	is_array( $image_request_body )
	&& 'npcink-cloud/generate-image' === (string) ( $image_request_body['ability_name'] ?? '' )
	&& 'image_generation_request.v1' === (string) ( $image_request_body['contract_version'] ?? '' )
	&& 'wordpress_ai_connector' === (string) ( $image_request_body['channel'] ?? '' )
	&& 'image_generation' === (string) ( $image_request_body['execution_kind'] ?? '' )
	&& 'inline' === (string) ( $image_request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $image_request_body['storage_mode'] ?? '' )
	&& 90 === (int) ( $image_request_body['timeout_seconds'] ?? 0 )
	&& 90 === (int) ( $image_request['args']['timeout'] ?? 0 )
	&& false === (bool) ( $image_request_body['policy']['allow_fallback'] ?? true )
	&& 'wordpress_ai_connector' === (string) ( $image_request_body['input']['source_surface'] ?? '' )
	&& 'npcink-cloud' === (string) ( $image_request_body['input']['connector_id'] ?? '' )
	&& 'image_generation' === (string) ( $image_request_body['input']['task'] ?? '' )
	&& ! isset( $image_request_body['input']['response_format'] )
	&& '16:9' === (string) ( $image_request_body['input']['aspect_ratio'] ?? '' )
	&& 2 === (int) ( $image_request_body['input']['n'] ?? 0 ),
	'Behavior: WordPress AI image generation runtime projects a bounded Cloud image-generation payload.'
);

$image_provider_media_field = $client->execute_wordpress_ai_image_generation_runtime(
	array(
		'contract_version' => 'image_generation_request.v1',
		'task'             => 'image_generation',
		'prompt'           => 'A clean product image.',
		'response_format'  => 'url',
	)
);
maca_assert(
	is_wp_error( $image_provider_media_field )
	&& 'cloud_wp_ai_image_generation_provider_media_field_forbidden' === $image_provider_media_field->get_error_code(),
	'Behavior: WordPress AI image generation rejects caller-selected provider media response formats.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_toolbox_image_1',
			'data'   => array(
				'result' => array(
					'artifact_type'          => 'image_generation_artifacts',
					'contract_version'       => 'image_generation_result.v1',
					'suggestion_only'        => true,
					'requires_local_review'  => true,
					'artifacts'              => array(
						array(
							'artifact_id'        => 'art_toolbox_image_1',
							'artifact_reference' => array( 'artifact_id' => 'art_toolbox_image_1' ),
							'status'             => 'available',
							'content_type'       => 'image/png',
						),
					),
				),
			),
		)
	),
);

$toolbox_image_result = $client->execute_toolbox_image_generation_runtime(
	array(
		'contract_version' => 'image_generation_request.v1',
		'task'             => 'image_generation',
		'prompt'           => 'A cinematic featured image for a WordPress article.',
		'n'                => 3,
		'aspect_ratio'     => '16:9',
		'resolution'       => 'high',
		'source_surface'   => 'toolbox_featured_image',
		'timeout_seconds'  => 120,
	),
	'trace-toolbox-image',
	'toolbox-image-idempotency'
);
$toolbox_image_request      = end( $GLOBALS['maca_http_requests'] );
$toolbox_image_request_body = json_decode( (string) ( $toolbox_image_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $toolbox_image_result ) && 'run_toolbox_image_1' === (string) ( $toolbox_image_result['run_id'] ?? '' ),
	'Behavior: Toolbox image generation runtime returns the Cloud response for a supported candidate request.'
);

maca_assert(
	is_array( $toolbox_image_request_body )
	&& 'npcink-cloud/generate-image' === (string) ( $toolbox_image_request_body['ability_name'] ?? '' )
	&& 'image_generation_request.v1' === (string) ( $toolbox_image_request_body['contract_version'] ?? '' )
	&& 'toolbox_image_generation' === (string) ( $toolbox_image_request_body['channel'] ?? '' )
	&& 'image_generation' === (string) ( $toolbox_image_request_body['execution_kind'] ?? '' )
	&& 'inline' === (string) ( $toolbox_image_request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $toolbox_image_request_body['storage_mode'] ?? '' )
	&& 90 === (int) ( $toolbox_image_request_body['timeout_seconds'] ?? 0 )
	&& false === (bool) ( $toolbox_image_request_body['policy']['allow_fallback'] ?? true )
	&& 'toolbox_featured_image' === (string) ( $toolbox_image_request_body['input']['source_surface'] ?? '' )
	&& 'npcink-cloud-addon' === (string) ( $toolbox_image_request_body['input']['connector_id'] ?? '' )
	&& 'image_generation' === (string) ( $toolbox_image_request_body['input']['task'] ?? '' )
	&& ! isset( $toolbox_image_request_body['input']['response_format'] )
	&& '16:9' === (string) ( $toolbox_image_request_body['input']['aspect_ratio'] ?? '' )
	&& 3 === (int) ( $toolbox_image_request_body['input']['n'] ?? 0 ),
	'Behavior: Toolbox image generation runtime projects a transport-only Cloud image-generation payload.'
);

$toolbox_image_provider_media_field = $client->execute_toolbox_image_generation_runtime(
	array(
		'contract_version' => 'image_generation_request.v1',
		'task'             => 'image_generation',
		'prompt'           => 'A clean featured-image candidate.',
		'response_format'  => 'b64_json',
	)
);
maca_assert(
	is_wp_error( $toolbox_image_provider_media_field )
	&& 'cloud_toolbox_image_generation_provider_media_field_forbidden' === $toolbox_image_provider_media_field->get_error_code(),
	'Behavior: Toolbox image generation rejects caller-selected provider media response formats.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_toolbox_audio_1',
			'data'   => array(
				'result' => array(
					'artifact_type'            => 'audio_generation_candidates',
					'contract_version'         => 'audio_generation_result.v1',
					'provider_response_format' => 'url',
					'direct_wordpress_write'   => false,
					'audios'                   => array(
						array(
							'url'    => 'https://cdn.example.test/toolbox-audio.mp3',
							'format' => 'mp3',
						),
					),
				),
			),
		)
	),
);

$toolbox_audio_result = $client->execute_toolbox_audio_generation_runtime(
	array(
		'contract_version'  => 'audio_generation_request.v1',
		'intent'            => 'article_audio_summary',
		'text'              => 'A reviewed spoken summary script for the current WordPress article.',
		'summary_text'      => 'A reviewed spoken summary script for the current WordPress article.',
		'script'            => 'A reviewed spoken summary script for the current WordPress article.',
		'voice_id'          => 'voice_editorial',
		'format'            => 'mp3',
		'source_surface'    => 'toolbox_article_audio_candidates',
		'user_instruction'  => 'Use a calm editorial tone.',
		'audio_preferences' => array( 'tone' => 'calm' ),
		'timeout_seconds'   => 120,
	),
	'trace-toolbox-audio',
	'toolbox-audio-idempotency'
);
$toolbox_audio_request      = end( $GLOBALS['maca_http_requests'] );
$toolbox_audio_request_body = json_decode( (string) ( $toolbox_audio_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $toolbox_audio_result ) && 'run_toolbox_audio_1' === (string) ( $toolbox_audio_result['run_id'] ?? '' ),
	'Behavior: Toolbox audio generation runtime returns the Cloud response for a supported candidate request.'
);

maca_assert(
	is_array( $toolbox_audio_request_body )
	&& 'npcink-toolbox/generate-audio' === (string) ( $toolbox_audio_request_body['ability_name'] ?? '' )
	&& 'audio_generation_request.v1' === (string) ( $toolbox_audio_request_body['contract_version'] ?? '' )
	&& 'toolbox_audio_generation' === (string) ( $toolbox_audio_request_body['channel'] ?? '' )
	&& 'audio_generation' === (string) ( $toolbox_audio_request_body['execution_kind'] ?? '' )
	&& 'inline' === (string) ( $toolbox_audio_request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $toolbox_audio_request_body['storage_mode'] ?? '' )
	&& 90 === (int) ( $toolbox_audio_request_body['timeout_seconds'] ?? 0 )
	&& false === (bool) ( $toolbox_audio_request_body['policy']['allow_fallback'] ?? true )
	&& 'toolbox_article_audio_candidates' === (string) ( $toolbox_audio_request_body['input']['source_surface'] ?? '' )
	&& 'npcink-cloud-addon' === (string) ( $toolbox_audio_request_body['input']['connector_id'] ?? '' )
	&& 'article_audio_summary' === (string) ( $toolbox_audio_request_body['input']['intent'] ?? '' )
	&& 'mp3' === (string) ( $toolbox_audio_request_body['input']['format'] ?? '' )
	&& 'url' === (string) ( $toolbox_audio_request_body['input']['response_format'] ?? '' )
	&& false === (bool) ( $toolbox_audio_request_body['input']['review']['direct_wordpress_write'] ?? true ),
	'Behavior: Toolbox audio generation runtime projects a transport-only Cloud audio-generation payload.'
);

$audio_chat_shape = $client->execute_toolbox_audio_generation_runtime(
	array(
		'contract_version' => 'audio_generation_request.v1',
		'intent'           => 'article_narration',
		'text'             => 'Narrate this article.',
		'messages'         => array(
			array(
				'role'    => 'user',
				'content' => 'Chat with me.',
			),
		),
	)
);
maca_assert(
	is_wp_error( $audio_chat_shape ) && 'cloud_toolbox_audio_generation_shape_not_allowed' === $audio_chat_shape->get_error_code(),
	'Behavior: Toolbox audio generation runtime rejects generic chat message shapes.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_toolbox_site_ops_1',
			'data'   => array(
				'status' => 'succeeded',
				'result' => array(
					'contract_version'       => 'site_ops_cloud_analysis_result.v1',
					'direct_wordpress_write' => false,
					'priority_queue'         => array(
						array( 'finding_id' => 'finding_media_alt_gap' ),
					),
				),
			),
		)
	),
);

$toolbox_site_ops_result = $client->execute_toolbox_site_ops_cloud_analysis_runtime(
	array(
		'artifact_type'              => 'site_ops_cloud_analysis_request',
		'contract_version'           => 'site_ops_cloud_analysis_request.v1',
		'expected_result_contract'   => 'site_ops_cloud_analysis_result.v1',
		'cloud_role'                => 'runtime_detail',
		'execution_pattern'          => 'whole_run_offload',
		'write_posture'              => 'suggestion_only',
		'direct_wordpress_write'     => false,
		'core_proposal_created'      => false,
		'profile_id'                 => 'site-ops-analysis.managed',
		'timeout_seconds'            => 120,
		'input'                      => array(
			'local_summary'  => array( 'finding_count' => 3 ),
			'local_findings' => array(
				array(
					'id'             => 'finding_media_alt_gap',
					'issue_type'     => 'media',
					'write_boundary' => 'core_handoff_candidate',
				),
			),
		),
		'safety'                     => array(
			'cloud_is_runtime_detail_only' => true,
			'direct_wordpress_write'       => false,
		),
	),
	'trace-toolbox-site-ops',
	'toolbox-site-ops-idempotency'
);
$toolbox_site_ops_request      = end( $GLOBALS['maca_http_requests'] );
$toolbox_site_ops_request_body = json_decode( (string) ( $toolbox_site_ops_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $toolbox_site_ops_result ) && 'run_toolbox_site_ops_1' === (string) ( $toolbox_site_ops_result['run_id'] ?? '' ),
	'Behavior: Toolbox Site Ops Cloud analysis runtime returns the Cloud response for a supported detail request.'
);

maca_assert(
	is_array( $toolbox_site_ops_request_body )
	&& 'npcink-toolbox/analyze-site-ops' === (string) ( $toolbox_site_ops_request_body['ability_name'] ?? '' )
	&& 'site_ops_cloud_analysis_request.v1' === (string) ( $toolbox_site_ops_request_body['contract_version'] ?? '' )
	&& 'toolbox_site_ops_cloud_analysis' === (string) ( $toolbox_site_ops_request_body['channel'] ?? '' )
	&& 'site_ops_cloud_analysis' === (string) ( $toolbox_site_ops_request_body['execution_kind'] ?? '' )
	&& 'whole_run_offload' === (string) ( $toolbox_site_ops_request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $toolbox_site_ops_request_body['storage_mode'] ?? '' )
	&& 90 === (int) ( $toolbox_site_ops_request_body['timeout_seconds'] ?? 0 )
	&& 0 === (int) ( $toolbox_site_ops_request_body['retry_max'] ?? -1 )
	&& false === (bool) ( $toolbox_site_ops_request_body['policy']['allow_fallback'] ?? true )
	&& 'site_ops_cloud_analysis_request.v1' === (string) ( $toolbox_site_ops_request_body['input']['contract_version'] ?? '' )
	&& 'site_ops_cloud_analysis_result.v1' === (string) ( $toolbox_site_ops_request_body['input']['expected_result_contract'] ?? '' )
	&& 'runtime_detail' === (string) ( $toolbox_site_ops_request_body['input']['cloud_role'] ?? '' )
	&& 'suggestion_only' === (string) ( $toolbox_site_ops_request_body['input']['write_posture'] ?? '' )
	&& false === (bool) ( $toolbox_site_ops_request_body['input']['direct_wordpress_write'] ?? true )
	&& false === (bool) ( $toolbox_site_ops_request_body['input']['core_proposal_created'] ?? true ),
	'Behavior: Toolbox Site Ops Cloud analysis runtime projects transport-only runtime detail without local writes.'
);

$site_ops_chat_shape = $client->execute_toolbox_site_ops_cloud_analysis_runtime(
	array(
		'contract_version'         => 'site_ops_cloud_analysis_request.v1',
		'expected_result_contract' => 'site_ops_cloud_analysis_result.v1',
		'cloud_role'              => 'runtime_detail',
		'execution_pattern'        => 'whole_run_offload',
		'write_posture'            => 'suggestion_only',
		'direct_wordpress_write'   => false,
		'core_proposal_created'    => false,
		'input'                    => array( 'local_summary' => array() ),
		'messages'                 => array(
			array(
				'role'    => 'user',
				'content' => 'Chat with me.',
			),
		),
	)
);
maca_assert(
	is_wp_error( $site_ops_chat_shape ) && 'cloud_toolbox_site_ops_cloud_analysis_shape_not_allowed' === $site_ops_chat_shape->get_error_code(),
	'Behavior: Toolbox Site Ops Cloud analysis runtime rejects generic chat message shapes.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_toolbox_web_search_1',
			'data'   => array(
				'result' => array(
					'artifact_type'          => 'web_search_results',
					'contract_version'       => 'web_search_result.v1',
					'direct_wordpress_write' => false,
					'results'                => array(
						array(
							'title'   => 'Example result',
							'url'     => 'https://example.test/research',
							'snippet' => 'A bounded search result.',
						),
					),
				),
			),
		)
	),
);

$toolbox_web_search_result = $client->execute_toolbox_web_search_runtime(
	array(
		'contract_version' => 'web_search.v1',
		'query'            => 'WordPress editorial workflow research',
		'intent'           => 'writing_context',
		'max_results'      => 4,
		'recency_days'     => 14,
		'timeout_seconds'  => 90,
	),
	'trace-toolbox-web-search',
	'toolbox-web-search-idempotency'
);
$toolbox_web_search_request      = end( $GLOBALS['maca_http_requests'] );
$toolbox_web_search_request_body = json_decode( (string) ( $toolbox_web_search_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $toolbox_web_search_result ) && 'run_toolbox_web_search_1' === (string) ( $toolbox_web_search_result['run_id'] ?? '' ),
	'Behavior: Toolbox web search runtime returns the Cloud response for a supported query request.'
);

maca_assert(
	is_array( $toolbox_web_search_request_body )
	&& 'npcink-cloud/web-search' === (string) ( $toolbox_web_search_request_body['ability_name'] ?? '' )
	&& 'web_search.v1' === (string) ( $toolbox_web_search_request_body['contract_version'] ?? '' )
	&& 'toolbox_web_search' === (string) ( $toolbox_web_search_request_body['channel'] ?? '' )
	&& 'web_search' === (string) ( $toolbox_web_search_request_body['execution_kind'] ?? '' )
	&& 'inline' === (string) ( $toolbox_web_search_request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $toolbox_web_search_request_body['storage_mode'] ?? '' )
	&& 60 === (int) ( $toolbox_web_search_request_body['timeout_seconds'] ?? 0 )
	&& true === (bool) ( $toolbox_web_search_request_body['policy']['allow_fallback'] ?? false )
	&& 'web_search.v1' === (string) ( $toolbox_web_search_request_body['input']['contract_version'] ?? '' )
	&& 'writing_context' === (string) ( $toolbox_web_search_request_body['input']['intent'] ?? '' )
	&& 4 === (int) ( $toolbox_web_search_request_body['input']['max_results'] ?? 0 )
	&& 'suggestion_only' === (string) ( $toolbox_web_search_request_body['input']['write_posture'] ?? '' )
	&& false === (bool) ( $toolbox_web_search_request_body['input']['direct_wordpress_write'] ?? true ),
	'Behavior: Toolbox web search runtime projects transport-only search evidence without WordPress writes.'
);

$source_url = 'https://developer.wordpress.org/news/2026/04/whats-new-for-developers-april-2026/';
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_toolbox_source_extraction_1',
			'data'   => array(
				'result' => array(
					'artifact_type'          => 'source_extraction_preview',
					'contract_version'       => 'source_extraction_preview.v1',
					'requested_url'          => $source_url,
					'resolved_url'           => $source_url,
					'url_match'              => 'matched',
					'direct_wordpress_write' => false,
				),
			),
		)
	),
);

$toolbox_source_extraction_result = $client->execute_toolbox_web_search_runtime(
	array(
		'contract_version' => 'web_search.v1',
		'query'            => $source_url,
		'source_url'       => $source_url,
		'intent'           => 'source_extraction_preview',
		'max_results'      => 1,
		'recency_days'     => 0,
	),
	'trace-toolbox-source-extraction',
	'toolbox-source-extraction-idempotency'
);
$toolbox_source_extraction_request      = end( $GLOBALS['maca_http_requests'] );
$toolbox_source_extraction_request_body = json_decode( (string) ( $toolbox_source_extraction_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $toolbox_source_extraction_result )
	&& 'run_toolbox_source_extraction_1' === (string) ( $toolbox_source_extraction_result['run_id'] ?? '' )
	&& 'source_extraction_preview' === (string) ( $toolbox_source_extraction_request_body['input']['intent'] ?? '' )
	&& $source_url === (string) ( $toolbox_source_extraction_request_body['input']['query'] ?? '' )
	&& $source_url === (string) ( $toolbox_source_extraction_request_body['input']['source_url'] ?? '' )
	&& 1 === (int) ( $toolbox_source_extraction_request_body['input']['max_results'] ?? 0 )
	&& 0 === (int) ( $toolbox_source_extraction_request_body['input']['recency_days'] ?? -1 )
	&& false === (bool) ( $toolbox_source_extraction_request_body['input']['direct_wordpress_write'] ?? true ),
	'Behavior: Toolbox exact source extraction intent and source URL survive Addon normalization without WordPress writes.'
);

$web_search_chat_shape = $client->execute_toolbox_web_search_runtime(
	array(
		'contract_version' => 'web_search.v1',
		'query'            => 'Research this.',
		'messages'         => array(
			array(
				'role'    => 'user',
				'content' => 'Chat with me.',
			),
		),
	)
);
maca_assert(
	is_wp_error( $web_search_chat_shape ) && 'cloud_toolbox_web_search_shape_not_allowed' === $web_search_chat_shape->get_error_code(),
	'Behavior: Toolbox web search runtime rejects generic chat message shapes.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_toolbox_image_source_1',
			'data'   => array(
				'result' => array(
					'artifact_type'          => 'image_source_candidates',
					'contract_version'       => 'image_source_candidates.v1',
					'direct_wordpress_write' => false,
					'images'                 => array(
						array(
							'id'  => 'image-source-1',
							'url' => 'https://images.example.test/photo.jpg',
						),
					),
				),
			),
		)
	),
);

$toolbox_image_source_result = $client->execute_toolbox_image_source_runtime(
	array(
		'contract_version'    => 'image_source_cloud_request.v1',
		'query'               => 'Editorial office desk',
		'provider'            => 'unsplash',
		'per_page'            => 12,
		'latency_mode'        => 'fast_first',
		'candidate_contract'  => 'image_candidate.v1',
		'timeout_seconds'     => 90,
		'data_classification' => 'public_reference_media',
	),
	'trace-toolbox-image-source',
	'toolbox-image-source-idempotency'
);
$toolbox_image_source_request      = end( $GLOBALS['maca_http_requests'] );
$toolbox_image_source_request_body = json_decode( (string) ( $toolbox_image_source_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $toolbox_image_source_result ) && 'run_toolbox_image_source_1' === (string) ( $toolbox_image_source_result['run_id'] ?? '' ),
	'Behavior: Toolbox image-source runtime returns the Cloud response for a supported candidate request.'
);

maca_assert(
	is_array( $toolbox_image_source_request_body )
	&& 'npcink-toolbox/search-image-source' === (string) ( $toolbox_image_source_request_body['ability_name'] ?? '' )
	&& 'image_source_cloud_request.v1' === (string) ( $toolbox_image_source_request_body['contract_version'] ?? '' )
	&& 'toolbox_image_source' === (string) ( $toolbox_image_source_request_body['channel'] ?? '' )
	&& 'image_source' === (string) ( $toolbox_image_source_request_body['execution_kind'] ?? '' )
	&& 'inline' === (string) ( $toolbox_image_source_request_body['execution_pattern'] ?? '' )
	&& 'result_only' === (string) ( $toolbox_image_source_request_body['storage_mode'] ?? '' )
	&& 60 === (int) ( $toolbox_image_source_request_body['timeout_seconds'] ?? 0 )
	&& true === (bool) ( $toolbox_image_source_request_body['policy']['allow_fallback'] ?? false )
	&& 'image_source_cloud_request.v1' === (string) ( $toolbox_image_source_request_body['input']['contract_version'] ?? '' )
	&& 'unsplash' === (string) ( $toolbox_image_source_request_body['input']['provider'] ?? '' )
	&& 'cloud' === (string) ( $toolbox_image_source_request_body['input']['provider_origin'] ?? '' )
	&& 'fast_first' === (string) ( $toolbox_image_source_request_body['input']['latency_mode'] ?? '' )
	&& 'image_candidate.v1' === (string) ( $toolbox_image_source_request_body['input']['candidate_contract'] ?? '' )
	&& 'suggestion_only' === (string) ( $toolbox_image_source_request_body['input']['write_posture'] ?? '' )
	&& false === (bool) ( $toolbox_image_source_request_body['input']['direct_wordpress_write'] ?? true ),
	'Behavior: Toolbox image-source runtime projects transport-only source candidates without media writes.'
);

$image_source_chat_shape = $client->execute_toolbox_image_source_runtime(
	array(
		'contract_version' => 'image_source_cloud_request.v1',
		'query'            => 'Find source images.',
		'messages'         => array(
			array(
				'role'    => 'user',
				'content' => 'Chat with me.',
			),
		),
	)
);
maca_assert(
	is_wp_error( $image_source_chat_shape ) && 'cloud_toolbox_image_source_shape_not_allowed' === $image_source_chat_shape->get_error_code(),
	'Behavior: Toolbox image-source runtime rejects generic chat message shapes.'
);
