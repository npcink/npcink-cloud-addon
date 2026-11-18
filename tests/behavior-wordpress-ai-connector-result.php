<?php
/**
 * Behavior tests for task-bound WordPress AI connector result parsing.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

namespace WordPress\AiClient\Common\Exception {
	class RuntimeException extends \RuntimeException {}
}

namespace WordPress\AI {
	function normalize_content( string $content ): string {
		$GLOBALS['maca_normalized_source'] = $content;
		return html_entity_decode( strip_tags( $content ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}
}

namespace {
	require_once __DIR__ . '/helpers.php';

	// SDK stubs first: the connector provider bundle declares only behind
	// class_exists guards on these shared empty classes.
	require_once __DIR__ . '/wordpress-ai-client-stubs.php';
	maca_load_addon_classes();
	if ( ! function_exists( 'npcink_cloud_addon_upload_toolbox_site_media_visual_source' ) ) {
		function npcink_cloud_addon_upload_toolbox_site_media_visual_source( array $file, string $trace_id = '', string $idempotency_key = '' ) {
			$client = Npcink_Cloud_Runtime_Client_Factory::configured();
			return $client ? $client->upload_media_artifact( $file, $trace_id, $idempotency_key ) : new \WP_Error( 'cloud_runtime_unconfigured', 'Npcink Cloud is not configured.' );
		}
	}
	if ( ! function_exists( 'npcink_cloud_addon_execute_wordpress_ai_connector_runtime' ) ) {
		function npcink_cloud_addon_execute_wordpress_ai_connector_runtime( array $request, string $trace_id = '', string $idempotency_key = '' ) {
			$client = Npcink_Cloud_Runtime_Client_Factory::configured();
			return $client ? $client->execute_wordpress_ai_connector_runtime( $request, $trace_id, $idempotency_key ) : new \WP_Error( 'cloud_runtime_unconfigured', 'Npcink Cloud is not configured.' );
		}
	}
	require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-connector.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-scene-model.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-text-model.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-vision-text-model.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-image-model.php';

	if ( ! function_exists( 'get_post' ) ) {
		/**
		 * Provides the post fixture needed for context-key projection coverage.
		 *
		 * @param int $post_id Post ID.
		 * @return object|null
		 */
		function get_post( int $post_id ) {
			if ( isset( $GLOBALS['maca_posts'][ $post_id ] ) ) {
				return $GLOBALS['maca_posts'][ $post_id ];
			}
			if ( isset( $GLOBALS['maca_runtime_posts'][ $post_id ] ) ) {
				return $GLOBALS['maca_runtime_posts'][ $post_id ];
			}
			return 321 === $post_id
				? (object) array(
					'post_title'   => 'Context-backed title',
					'post_content' => 'Context-backed content.',
				)
				: null;
		}
	}

	/**
	 * Invokes one private connector result parser without constructing AI Client DTOs.
	 *
	 * @param string              $model_class Model class name.
	 * @param array<string,mixed> $response Cloud response.
	 * @param string              $expected_task Expected WordPress task.
	 * @return string
	 */
	function maca_invoke_connector_result_parser( string $model_class, array $response, string $expected_task ): string {
		$model  = ( new \ReflectionClass( $model_class ) )->newInstanceWithoutConstructor();
		$parser = new \ReflectionMethod( $model_class, 'extract_text' );
		$parser->setAccessible( true );

		return (string) $parser->invoke( $model, $response, $expected_task );
	}

	if ( ! class_exists( 'Maca_Test_Image_Config' ) ) {
		/** Minimal image config used to exercise the model transport seam. */
		final class Maca_Test_Image_Config {
			public function getFunctionDeclarations() {
				return null;
			}

			public function getWebSearch() {
				return null;
			}

			public function getCandidateCount() {
				return 1;
			}

			public function getOutputMediaAspectRatio() {
				return null;
			}

			public function getOutputMediaOrientation() {
				return null;
			}
		}
	}

	$parser_models = array(
		'Npcink_Cloud_WordPress_AI_Text_Model'        => 'content_summary',
		'Npcink_Cloud_WordPress_AI_Vision_Text_Model' => 'alt_text_suggest',
	);
	$alt_response = array( 'data' => array( 'result' => array(
		'contract_version' => 'cloud_connector_result.v1', 'suggestion_only' => true, 'connector_id' => 'npcink-cloud-addon',
		'operation_contract' => array( 'contract_version' => 'wordpress_operation.v1', 'task' => 'alt_text_suggest' ),
	) ) );
	foreach ( array(
		'{ "contractversion": "imagecontextevidence.v1", "artifacttype": "imagecontextevidence", "items": [ { "attachmentid": "20240628153729", "visualsummary": "红色太阳正在海平面上落下，天空被染成橙红色。',
		'{"alt_text":"A garden."}', '["A garden."]', '```json {"alt_text":"A garden."}',
		'<p>A garden.</p>', "A garden.\nMore explanation.", "Broken UTF-8 \xB1", str_repeat( '字', 241 ),
	) as $bad_alt ) {
		$alt_response['data']['result']['output']['output_text'] = $bad_alt;
		maca_assert( '' === maca_invoke_connector_result_parser( 'Npcink_Cloud_WordPress_AI_Vision_Text_Model', $alt_response, 'alt_text_suggest' ), 'Vision rejects the captured truncated evidence JSON, structured output, markup, multiline and oversized ALT before host adoption.' );
	}
	foreach ( array( 'Sunset over the sea.', '蓝绿色编辑器模块围绕文档图标的插画。', str_repeat( '字', 240 ) ) as $good_alt ) {
		$alt_response['data']['result']['output']['output_text'] = $good_alt;
		maca_assert( $good_alt === maca_invoke_connector_result_parser( 'Npcink_Cloud_WordPress_AI_Vision_Text_Model', $alt_response, 'alt_text_suggest' ), 'Vision preserves bounded plain text in English and Chinese.' );
	}
	$parser_successes  = true;
	$parser_rejections = true;
	foreach ( $parser_models as $parser_model => $parser_task ) {
		$valid_response = array(
			'data' => array(
				'result' => array(
					'contract_version'   => 'cloud_connector_result.v1',
					'suggestion_only'    => true,
					'connector_id'       => 'npcink-cloud-addon',
					'operation_contract' => array(
						'contract_version' => 'wordpress_operation.v1',
						'task'             => $parser_task,
					),
					'output'             => array( 'output_text' => 'Bound connector output.' ),
				),
			),
		);
		$parser_successes = $parser_successes
			&& 'Bound connector output.' === maca_invoke_connector_result_parser( $parser_model, $valid_response, $parser_task );

		$wrong_task = $valid_response;
		$wrong_task['data']['result']['operation_contract']['task'] = 'different_task';
		$wrong_result_contract = $valid_response;
		$wrong_result_contract['data']['result']['contract_version'] = 'other_result.v1';
		$write_capable = $valid_response;
		$write_capable['data']['result']['suggestion_only'] = false;
		$wrong_connector = $valid_response;
		$wrong_connector['data']['result']['connector_id'] = 'other-connector';
		$wrong_operation_contract = $valid_response;
		$wrong_operation_contract['data']['result']['operation_contract']['contract_version'] = 'other_operation.v1';
		$missing_operation_contract = $valid_response;
		unset( $missing_operation_contract['data']['result']['operation_contract'] );

		foreach ( array( $wrong_task, $wrong_result_contract, $write_capable, $wrong_connector, $wrong_operation_contract, $missing_operation_contract ) as $invalid_response ) {
			$parser_rejections = $parser_rejections
				&& '' === maca_invoke_connector_result_parser( $parser_model, $invalid_response, $parser_task );
		}
	}

	maca_assert(
		$parser_successes,
		'Behavior: text and vision connector parsers accept only the task-bound Cloud connector result envelope.'
	);
	maca_assert(
		$parser_rejections,
		'Behavior: text and vision connector parsers reject task, suggestion posture, connector, and operation contract mismatches.'
	);

	$projection_model = ( new \ReflectionClass( 'Npcink_Cloud_WordPress_AI_Text_Model' ) )->newInstanceWithoutConstructor();
	maca_reset_test_state();
	Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context( 'ai/alt-text-generation', array( 'attachment_id' => 123 ) );
	$alt_text_rejected = false;
	try {
		$projection_model->generateTextResult( array() );
	} catch ( \WordPress\AiClient\Common\Exception\RuntimeException $error ) {
		$alt_text_rejected = true;
	}
	maca_assert( $alt_text_rejected && empty( $GLOBALS['maca_http_requests'] ), 'Text-only provider rejects ALT generation before HTTP; attachment input must use the vision provider.' );
	Npcink_Cloud_WordPress_AI_Connector::reset_wordpress_ai_ability_context();
	$projection_method = new \ReflectionMethod( 'Npcink_Cloud_WordPress_AI_Text_Model', 'project_short_text_scene_request' );
	$projection_method->setAccessible( true );
	$excerpt_projection = $projection_method->invoke(
		$projection_model,
		'excerpt_generation',
		'Generate a concise excerpt.',
		array( 'content' => '本文介绍 WordPress AI 连接器的运行边界。' )
	);
	$meta_projection = $projection_method->invoke(
		$projection_model,
		'meta_description',
		'Generate an SEO description.',
		array( 'content' => '本文介绍 WordPress AI 连接器的运行边界。', 'title' => '连接器运行边界' )
	);
	$context_meta_projection = $projection_method->invoke(
		$projection_model,
		'meta_description',
		'Generate an SEO description.',
		array( 'context' => 321 )
	);
	$missing_projection = $projection_method->invoke(
		$projection_model,
		'excerpt_generation',
		'Generate an excerpt from the supplied content.',
		array()
	);
	$html_content = '<p data-layout="' . str_repeat( 'x', 25000 ) . '">引入资源。</p><p>标记 data-intro / data-step，再调用 introJs().start()。</p>';
	$html_projection = $projection_method->invoke( $projection_model, 'excerpt_generation', 'Official excerpt instruction.', array( 'content' => $html_content ) );
	maca_assert(
		'引入资源。标记 data-intro / data-step，再调用 introJs().start()。' === $html_projection['source_text']
			&& $html_content === ( $GLOBALS['maca_normalized_source'] ?? null )
			&& 'Official excerpt instruction.' === $html_projection['system_instruction'],
		'Behavior: excerpt projection delegates HTML normalization to WordPress AI without truncating later workflow steps or changing the prompt.'
	);
	$GLOBALS['maca_posts'][ 322 ] = (object) array( 'post_title' => '字段说明', 'post_content' => '<p>Context-backed <code>data-step</code> content.</p>' );
	$html_meta = $projection_method->invoke( $projection_model, 'meta_description', 'Official meta instruction.', array( 'context' => 322 ) );
	maca_assert(
		"Title:\n字段说明\n\nContent:\nContext-backed data-step content." === $html_meta['source_text'],
		'Behavior: context-backed meta descriptions use the same upstream content normalization.'
	);
	maca_assert(
		'本文介绍 WordPress AI 连接器的运行边界。' === (string) ( $excerpt_projection['source_text'] ?? '' )
			&& 'Generate a concise excerpt.' === (string) ( $excerpt_projection['system_instruction'] ?? '' )
			&& ! isset( $excerpt_projection['prompt'] ),
		'Behavior: excerpt generation projects validated content as source text and preserves the official instruction separately.'
	);
	maca_assert(
		"Title:\n连接器运行边界\n\nContent:\n本文介绍 WordPress AI 连接器的运行边界。" === (string) ( $meta_projection['source_text'] ?? '' )
			&& 'Generate an SEO description.' === (string) ( $meta_projection['system_instruction'] ?? '' ),
		'Behavior: meta description generation keeps the validated title and content together as grounded source text.'
	);
	maca_assert(
		"Title:\nContext-backed title\n\nContent:\nContext-backed content." === (string) ( $context_meta_projection['source_text'] ?? '' ),
		'Behavior: meta description generation resolves both title and content from the Ability context post reference.'
	);
	maca_assert(
		'Generate an excerpt from the supplied content.' === (string) ( $missing_projection['prompt'] ?? '' )
			&& ! isset( $missing_projection['source_text'] ),
		'Behavior: short-text projection keeps the original prompt when the Ability provides no content.'
	);

	$editor_validator = new ReflectionMethod( $projection_model, 'valid_editor_output' );
	$editor_validator->setAccessible( true );
	foreach ( array( 'Product Features', '产品功能概览' ) as $label ) {
		maca_assert( $editor_validator->invoke( $projection_model, 'block_container_label_generation', $label ), 'Block labels accept concise plain text in the source language.' );
	}
	foreach ( array( 'Content', "Features\nExplanation", '<b>Features</b>', str_repeat( 'x', 81 ) ) as $label ) {
		maca_assert( ! $editor_validator->invoke( $projection_model, 'block_container_label_generation', $label ), 'Block labels reject generic, multiline, markup or unbounded output.' );
	}
	maca_assert( $editor_validator->invoke( $projection_model, 'revision_change_summary', '{"summary":"Added an approval step.","changes":["Requires administrator approval."]}' ), 'Revision summary accepts the model response before host-owned revision fields are attached.' );
	foreach ( array( 'Plain text', '{"summary":"","changes":[]}', '{"summary":"Changed.","changes":[42]}', '{"summary":"Changed.","changes":[],"unexpected":999}' ) as $summary ) {
		maca_assert( ! $editor_validator->invoke( $projection_model, 'revision_change_summary', $summary ), 'Revision summary rejects malformed content and undeclared fields.' );
	}

	maca_assert( $editor_validator->invoke( $projection_model, 'revision_change_summary', '{"summary":"Changed.","changes":[],"revision_id":999,"truncated":true}' ), 'Public-schema revision fields are accepted only for the official parser to discard and recompute from local revisions.' );
	maca_reset_test_state();
	maca_seed_settings( true );
	$image_bytes = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
	maca_assert( is_string( $image_bytes ), 'Fixture: generated image artifact bytes decode.' );
	$image_artifact_id = 'art_' . str_repeat( 'a', 32 );
	$image_delivery_id = 'mdl_' . str_repeat( 'b', 32 );
	$image_checksum    = 'sha256:' . hash( 'sha256', $image_bytes );
	$image_expires_at  = gmdate( 'Y-m-d\TH:i:s\Z', time() + 1800 );
	$image_ack_deadline = gmdate( 'Y-m-d\TH:i:s\Z', time() + 600 );
	// Production Python ISO timestamps include microseconds and a UTC offset.
	$image_expires_at = gmdate( 'Y-m-d\TH:i:s', time() + 1800 ) . '.702090+00:00';
	$image_ack_deadline = $image_expires_at;
	$image_result = array(
		'contract_version'     => 'image_generation_result.v1',
		'artifact_type'        => 'image_generation_artifacts',
		'operation'            => 'image.generate.v1',
		'artifacts'            => array(
			array(
				'artifact_id'        => $image_artifact_id,
				'artifact_reference' => array( 'artifact_id' => $image_artifact_id ),
				'status'             => 'available',
				'media_kind'         => 'image',
				'operation'          => 'image.generate.v1',
				'content_type'       => 'image/png',
				'format'             => 'png',
				'width'              => 1,
				'height'             => 1,
				'filesize_bytes'     => strlen( $image_bytes ),
				'checksum'           => $image_checksum,
				'expires_at'         => $image_expires_at,
				'purged_at'          => null,
			),
		),
		'suggestion_only'      => true,
		'requires_local_review' => true,
	);
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array(
			'Content-Type'                   => 'image/png',
			'Content-Length'                 => (string) strlen( $image_bytes ),
			'X-Npcink-Artifact-Id'           => $image_artifact_id,
			'X-Npcink-Artifact-Checksum'     => $image_checksum,
			'X-Npcink-Delivery-Id'           => $image_delivery_id,
			'X-Npcink-Delivery-Ack-Deadline' => $image_ack_deadline,
		),
		'body'     => $image_bytes,
	);
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'contract_version'      => 'media_artifact_delivery_ack.v1',
					'delivery_id'           => $image_delivery_id,
					'artifact_id'           => $image_artifact_id,
					'status'                => 'acknowledged',
					'received_byte_size'    => strlen( $image_bytes ),
					'received_checksum'     => $image_checksum,
					'byte_size_verified'    => true,
					'checksum_verified'     => true,
					'acknowledged_at'       => gmdate( 'Y-m-d\TH:i:s\Z' ),
					'artifact_expires_at'   => $image_expires_at,
					'idempotent_replay'     => false,
					'acknowledgement_scope' => 'verified_transfer_only',
				),
			)
		),
	);
	$image_http_queue = $GLOBALS['maca_http_response_queue'];
	$image_model  = ( new \ReflectionClass( 'Npcink_Cloud_WordPress_AI_Image_Model' ) )->newInstanceWithoutConstructor();
	$image_parser = new \ReflectionMethod( $image_model, 'download_artifact_images' );
	$image_parser->setAccessible( true );
	$image_outputs = $image_parser->invoke( $image_model, $image_result, 'trace-image-result' );
	$image_pull_request = $GLOBALS['maca_http_requests'][0] ?? array();
	$image_ack_request  = $GLOBALS['maca_http_requests'][1] ?? array();
	maca_assert(
		array( array( 'b64_json' => base64_encode( $image_bytes ), 'mime_type' => 'image/png' ) ) === $image_outputs,
		'Behavior: the WordPress AI image connector converts a verified Cloud artifact into one inline preview candidate.'
	);
	maca_assert(
		str_ends_with( (string) ( $image_pull_request['url'] ?? '' ), '/v1/runtime/media/artifacts/' . $image_artifact_id . '/download' )
		&& 'GET' === ( $image_pull_request['args']['method'] ?? null )
		&& str_ends_with( (string) ( $image_ack_request['url'] ?? '' ), '/v1/runtime/media/artifacts/' . $image_artifact_id . '/delivery-ack' )
		&& 'POST' === ( $image_ack_request['args']['method'] ?? null ),
		'Behavior: image candidate recovery uses only the signed artifact pull and verified-transfer ACK endpoints.'
	);

	// Exercise the full WordPress AI File -> source upload -> artifact-only edit
	// runtime path. The result remains a candidate; no WordPress write is made.
	maca_reset_test_state();
	maca_seed_settings( true );
	$edit_upload_artifact = array(
		'artifact_id'    => 'art_' . str_repeat( 'c', 32 ),
		'media_kind'     => 'image',
		'status'         => 'available',
		'content_type'   => 'image/png',
		'format'         => 'png',
		'width'          => 1,
		'height'         => 1,
		'filesize_bytes' => strlen( $image_bytes ),
		'checksum'       => 'sha256:' . hash( 'sha256', $image_bytes ),
		'expires_at'     => gmdate( 'Y-m-d\TH:i:s', time() + 1800 ) . '.702090+00:00',
		'purged_at'      => null,
	);
	$edit_artifact_id = $edit_upload_artifact['artifact_id'];
	$edit_delivery_id = 'mdl_' . str_repeat( 'd', 32 );
	$edit_expires_at  = $edit_upload_artifact['expires_at'];
	$edit_result = $image_result;
	$edit_result['operation'] = 'image.edit.v1';
	$edit_result['artifacts'][0] = array_merge(
		$edit_result['artifacts'][0],
		array(
			'artifact_id'        => $edit_artifact_id,
			'operation'          => 'image.edit.v1',
			'artifact_reference' => array( 'artifact_id' => $edit_artifact_id ),
			'filesize_bytes'     => strlen( $image_bytes ),
			'checksum'           => $edit_upload_artifact['checksum'],
			'expires_at'         => $edit_expires_at,
		)
	);
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'result' => array(
						'artifact_type'    => 'media_upload_artifact',
						'contract_version' => 'media_upload_result.v1',
						'artifact'         => $edit_upload_artifact,
					),
				),
			)
		),
	);
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'run_id' => 'run_wp_ai_image_edit_1',
					'status' => 'succeeded',
					'result' => array( 'contract_version' => 'cloud_connector_result.v1', 'output' => $edit_result ),
				),
			)
		),
	);
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array(
			'Content-Type'                   => 'image/png',
			'Content-Length'                 => (string) strlen( $image_bytes ),
			'X-Npcink-Artifact-Id'           => $edit_artifact_id,
			'X-Npcink-Artifact-Checksum'     => $edit_upload_artifact['checksum'],
			'X-Npcink-Delivery-Id'            => $edit_delivery_id,
			'X-Npcink-Delivery-Ack-Deadline' => $edit_expires_at,
		),
		'body'     => $image_bytes,
	);
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'contract_version'      => 'media_artifact_delivery_ack.v1',
					'delivery_id'           => $edit_delivery_id,
					'artifact_id'           => $edit_artifact_id,
					'status'                => 'acknowledged',
					'received_byte_size'    => strlen( $image_bytes ),
					'received_checksum'     => $edit_upload_artifact['checksum'],
					'byte_size_verified'    => true,
					'checksum_verified'     => true,
					'acknowledged_at'       => gmdate( 'Y-m-d\TH:i:s\Z' ),
					'artifact_expires_at'   => $edit_expires_at,
					'idempotent_replay'     => false,
					'acknowledgement_scope' => 'verified_transfer_only',
				),
			)
		),
	);
	$edit_model = ( new \ReflectionClass( 'Npcink_Cloud_WordPress_AI_Image_Model' ) )->newInstanceWithoutConstructor();
	$edit_config_property = new \ReflectionProperty( $edit_model, 'config' );
	$edit_config_property->setAccessible( true );
	$edit_config_property->setValue( $edit_model, new Maca_Test_Image_Config() );
	$edit_file = new \WordPress\AiClient\Files\DTO\File( base64_encode( $image_bytes ), 'image/png' );
	$edit_prompt = new \WordPress\AiClient\Messages\DTO\Message(
		\WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
		array(
			new \WordPress\AiClient\Messages\DTO\MessagePart( 'Remove the background.' ),
			new \WordPress\AiClient\Messages\DTO\MessagePart( $edit_file ),
		)
	);
	$edit_result = $edit_model->generateImageResult( array( $edit_prompt ) );
	$edit_upload_request  = $GLOBALS['maca_http_requests'][0] ?? array();
	$edit_runtime_request = $GLOBALS['maca_http_requests'][1] ?? array();
	$edit_upload_body     = (string) ( $edit_upload_request['args']['body'] ?? '' );
	$edit_runtime_body    = json_decode( (string) ( $edit_runtime_request['args']['body'] ?? '' ), true );
	maca_assert(
		$edit_result instanceof \WordPress\AiClient\Results\DTO\GenerativeAiResult
		&& 1 === count( $edit_result->candidates )
		&& false !== strpos( $edit_upload_body, $image_bytes )
		&& 'image_editing' === (string) ( $edit_runtime_body['input']['operation_contract']['task'] ?? '' )
		&& $edit_artifact_id === (string) ( $edit_runtime_body['input']['operation_contract']['request']['source_artifact_id'] ?? '' )
		&& 'Remove the background.' === (string) ( $edit_runtime_body['input']['operation_contract']['request']['prompt'] ?? '' )
		&& false === strpos( (string) ( $edit_runtime_request['args']['body'] ?? '' ), base64_encode( $image_bytes ) )
		&& str_ends_with( (string) ( $GLOBALS['maca_http_requests'][2]['url'] ?? '' ), '/v1/runtime/media/artifacts/' . $edit_artifact_id . '/download' )
		&& str_ends_with( (string) ( $GLOBALS['maca_http_requests'][3]['url'] ?? '' ), '/v1/runtime/media/artifacts/' . $edit_artifact_id . '/delivery-ack' ),
		'Behavior: a WordPress AI reference File is uploaded once, the edit runtime carries only source_artifact_id, and the result returns through verified candidate delivery without a WordPress write.'
	);
	maca_reset_test_state();
	$remote_prompt = new \WordPress\AiClient\Messages\DTO\Message(
		\WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
		array( new \WordPress\AiClient\Messages\DTO\MessagePart( 'Edit the reference.' ), new \WordPress\AiClient\Messages\DTO\MessagePart( new \WordPress\AiClient\Files\DTO\File( 'https://example.test/private.png', 'image/png' ) ) )
	);
	$remote_rejected = false;
	try {
		$edit_model->generateImageResult( array( $remote_prompt ) );
	} catch ( \Throwable $error ) {
		$remote_rejected = true;
	}
	maca_assert( $remote_rejected && empty( $GLOBALS['maca_http_requests'] ), 'Behavior: an SDK remote File has no inline bytes and is rejected without fetching its URL.' );
	maca_reset_test_state();
	maca_assert( array() === $image_parser->invoke( $edit_model, $image_result, '', 'image.edit.v1' ) && empty( $GLOBALS['maca_http_requests'] ), 'Behavior: an edit request never downloads or acknowledges a generation-typed result.' );
	$stage_property = new \ReflectionProperty( $image_model, 'delivery_stage' );
	$stage_property->setAccessible( true );
	foreach ( array( 'artifact_download', 'artifact_byte_verification', 'artifact_delivery_ack', 'artifact_ack_verification', 'artifact_contract' ) as $failed_stage ) {
		maca_reset_test_state();
		maca_seed_settings( true );
		$replay_queue = $image_http_queue;
		$replay_result = $image_result;
		if ( 'artifact_download' === $failed_stage ) {
			$replay_queue[0] = new \WP_Error( 'http_request_failed', 'private download detail' );
		} elseif ( 'artifact_byte_verification' === $failed_stage ) {
			$replay_queue[0]['body'] = 'corrupt bytes';
		} elseif ( 'artifact_delivery_ack' === $failed_stage ) {
			$replay_queue[1] = new \WP_Error( 'http_request_failed', 'private ack detail' );
		} elseif ( 'artifact_ack_verification' === $failed_stage ) {
			$ack_body = json_decode( $replay_queue[1]['body'], true );
			$ack_body['data']['artifact_expires_at'] = gmdate( 'Y-m-d\TH:i:s\Z', time() + 3600 );
			$replay_queue[1]['body'] = wp_json_encode( $ack_body );
		} else {
			$replay_result['artifacts'][] = array_merge( $image_result['artifacts'][0], array( 'content_type' => 'application/json' ) );
		}
		$GLOBALS['maca_http_response_queue'] = $replay_queue;
		$replay_failed = false;
		try {
			$image_parser->invoke( $image_model, $replay_result, 'trace-delivery-stage' );
		} catch ( \Throwable $error ) {
			$replay_failed = true;
		}
		$expected_requests = in_array( $failed_stage, array( 'artifact_download', 'artifact_byte_verification' ), true ) ? 1 : 2;
		maca_assert( $replay_failed && $failed_stage === $stage_property->getValue( $image_model ) && $expected_requests === count( $GLOBALS['maca_http_requests'] ), 'Delivery failures retain their precise stage; invalid bytes never send a delivery acknowledgement.' );
	}
	maca_reset_test_state();
	maca_seed_settings( true );
	$connector_source = file_get_contents( MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-connector.php' );
	$image_model_source = file_get_contents( MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-image-model.php' );
	$legacy_bypass_scope = ( is_string( $connector_source ) ? $connector_source : '' )
		. ( is_string( $image_model_source ) ? $image_model_source : '' );
	maca_assert(
		is_string( $connector_source )
		&& is_string( $image_model_source )
		&& false === strpos( $legacy_bypass_scope, "\$result['images']" )
		&& false === strpos( $legacy_bypass_scope, "\$image['url']" ),
		'Behavior: the WordPress AI image connector has no legacy inline Base64 or URL result bypass.'
	);

	$invalid_image_results = array();
	$wrong_operation = $image_result;
	$wrong_operation['operation'] = 'image.transform.v1';
	$invalid_image_results[] = $wrong_operation;
	$legacy_inline_result = array(
		'images' => array(
			array(
				'b64_json' => base64_encode( $image_bytes ),
				'mime_type' => 'image/png',
			),
		),
	);
	$invalid_image_results[] = $legacy_inline_result;
	$too_many_candidates = $image_result;
	$too_many_candidates['artifacts'] = array_fill( 0, 5, $image_result['artifacts'][0] );
	$invalid_image_results[] = $too_many_candidates;
	$string_width = $image_result;
	$string_width['artifacts'][0]['width'] = '1';
	$invalid_image_results[] = $string_width;
	$impossible_expiry = $image_result;
	$impossible_expiry['artifacts'][0]['expires_at'] = '2027-02-31T12:00:00Z';
	$invalid_image_results[] = $impossible_expiry;
	$non_utc_expiry = $image_result;
	$non_utc_expiry['artifacts'][0]['expires_at'] = '2027-02-28T20:00:00+08:00';
	$invalid_image_results[] = $non_utc_expiry;
	$purged_artifact = $image_result;
	$purged_artifact['artifacts'][0]['purged_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );
	$invalid_image_results[] = $purged_artifact;
	$aggregate_too_large = $image_result;
	$aggregate_too_large['artifacts'] = array_fill( 0, 2, $image_result['artifacts'][0] );
	$aggregate_too_large['artifacts'][0]['filesize_bytes'] = 16777217;
	$aggregate_too_large['artifacts'][1]['filesize_bytes'] = 16777216;
	$invalid_image_results[] = $aggregate_too_large;

	$expired_artifact = $image_result;
	$expired_artifact['artifacts'][0]['expires_at'] = gmdate( 'Y-m-d\\TH:i:s\\Z', time() - 60 );
	maca_reset_test_state();
	maca_seed_settings( true );
	$expired_artifact_code = '';
	try {
		$image_parser->invoke( $image_model, $expired_artifact, 'trace-expired-image-result' );
	} catch ( \WordPress\AiClient\Common\Exception\RuntimeException $error ) {
		$expired_artifact_code = maca_ai_error_text( $error );
	}
	maca_assert(
		false !== strpos( $expired_artifact_code, 'cloud_wp_ai_image_artifact_purged' )
			&& array() === $GLOBALS['maca_http_requests'],
		'Behavior: a structurally valid but expired image artifact reports the stable expired-artifact code before HTTP.'
	);

	$invalid_results_rejected = true;
	foreach ( $invalid_image_results as $invalid_image_result ) {
		maca_reset_test_state();
		maca_seed_settings( true );
		try {
			$invalid_output = $image_parser->invoke( $image_model, $invalid_image_result, 'trace-invalid-image-result' );
			$invalid_results_rejected = $invalid_results_rejected
				&& array() === $invalid_output
				&& array() === $GLOBALS['maca_http_requests'];
		} catch ( \WordPress\AiClient\Common\Exception\RuntimeException $error ) {
			$invalid_results_rejected = $invalid_results_rejected
				&& array() === $GLOBALS['maca_http_requests'];
		}
	}
	maca_assert(
		$invalid_results_rejected,
		'Behavior: image candidate recovery rejects legacy inline output, wrong operations, excess candidates, aggregate byte amplification, coerced facts, invalid UTC timestamps, and purged artifacts before HTTP.'
	);

	maca_reset_test_state();
	maca_seed_settings( true );
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array(
			'Content-Type'                   => 'image/png',
			'Content-Length'                 => (string) strlen( $image_bytes ),
			'X-Npcink-Artifact-Id'           => $image_artifact_id,
			'X-Npcink-Artifact-Checksum'     => $image_checksum,
			'X-Npcink-Delivery-Id'           => $image_delivery_id,
			'X-Npcink-Delivery-Ack-Deadline' => $image_ack_deadline,
		),
		'body'     => $image_bytes,
	);
	$changed_expiry = gmdate( 'Y-m-d\TH:i:s\Z', time() + 1700 );
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'contract_version'      => 'media_artifact_delivery_ack.v1',
					'delivery_id'           => $image_delivery_id,
					'artifact_id'           => $image_artifact_id,
					'status'                => 'acknowledged',
					'received_byte_size'    => strlen( $image_bytes ),
					'received_checksum'     => $image_checksum,
					'byte_size_verified'    => true,
					'checksum_verified'     => true,
					'acknowledged_at'       => gmdate( 'Y-m-d\TH:i:s\Z' ),
					'artifact_expires_at'   => $changed_expiry,
					'idempotent_replay'     => false,
					'acknowledgement_scope' => 'verified_transfer_only',
				),
			)
		),
	);
	$changed_expiry_rejected = false;
	try {
		$image_parser->invoke( $image_model, $image_result, 'trace-changed-expiry-ack' );
	} catch ( \WordPress\AiClient\Common\Exception\RuntimeException $error ) {
		$changed_expiry_rejected = str_contains( maca_ai_error_text( $error ), 'cloud_wp_ai_image_delivery_ack_invalid' );
	}
	maca_assert(
		$changed_expiry_rejected && 2 === count( $GLOBALS['maca_http_requests'] ),
		'Behavior: image candidate recovery rejects an otherwise valid ACK that changes the artifact expiry binding.'
	);


	maca_assert(
		str_contains( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( 'cloud_upstream_error', 'cloud_upstream_error:poll', 'database connection pool exhausted' ), 'did not succeed' )
		&& 'cloud_upstream_error-poll' === ( get_option( Npcink_Cloud_WordPress_AI_Connector::LAST_AI_ERROR_OPTION, array() )['code'] ?? '' )
		&& str_contains( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( 'http_request_failed', '', 'curl error 28' ), 'could not be reached' )
		&& false === strpos( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'runtime_failed', 'cloud_upstream_error:poll', 'detail https://example.test/signed?token=abc' ), 'example.test' )
		&& false === strpos( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'runtime_failed', 'cloud_upstream_error:poll', 'detail' ), '(' ),
		'Behavior: runtime failure classification keys off structured codes with a readable code-stage separator, and connector detail strips request URLs.'
	);

	$plain_cases = array(
		array( 'cloud_insufficient_credits', 'cloud_insufficient_credits', 'run out' ),
		array( 'cloud_quota_exhausted', '', 'run out' ),
		array( 'cloud_credential_expired', '', 'Reconnect' ),
		array( 'cloud_site_inactive', '', 'turned off' ),
		array( 'cloud_key_inactive', '', 'turned off' ),
		array( 'cloud_rate_limited', '', 'Wait a minute' ),
		array( 'commercial.quota_exceeded', 'commercial.quota_exceeded', 'run out' ),
		array( 'cloud_runtime_error', 'commercial.quota_exceeded', 'run out' ),
		array( 'provider.quota_exhausted', '', 'temporarily unavailable' ),
		array( 'cloud_runtime_error', 'provider.rate_limited', 'temporarily unavailable' ),
		array( 'provider.rate_limited', 'provider.rate_limited', 'temporarily unavailable' ),
		array( 'cloud_service_unavailable', '', 'temporarily unavailable' ),
	);
Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( 'cloud_runtime_error', 'commercial.quota_exceeded:entitlement', 'raw' );
maca_assert(
	'commercial.quota_exceeded-entitlement' === ( get_option( Npcink_Cloud_WordPress_AI_Connector::LAST_AI_ERROR_OPTION, array() )['code'] ?? '' )
	&& 'provider.rate_limited' === Npcink_Cloud_WordPress_AI_Connector::sanitize_error_code( 'Provider.Rate_Limited !' )
	&& false === strpos( (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-cloud-settings-page.php' ), "sanitize_key( (string) \$last_ai_error" ),
	'Behavior: the saved last AI error keeps the dots of Cloud codes (commercial.quota_exceeded), so Copy information shows the real code.'
);
	$plain_ok = true;
	foreach ( $plain_cases as $plain_case ) {
		$plain_message = Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( $plain_case[0], $plain_case[1], 'raw upstream detail' );
		$plain_ok = $plain_ok && str_contains( $plain_message, $plain_case[2] ) && false === strpos( $plain_message, 'cloud_' ) && false === strpos( $plain_message, 'raw upstream' ) && false === strpos( $plain_message, '(' );
	}
	$last_ai_error = get_option( Npcink_Cloud_WordPress_AI_Connector::LAST_AI_ERROR_OPTION, array() );
	maca_assert(
		$plain_ok
		&& 'cloud_service_unavailable' === ( $last_ai_error['code'] ?? '' )
		&& false === strpos( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( 'cloud_upstream_error', '', '' ), 'cloud_upstream_error' ),
		'Behavior: common Cloud AI errors map to one plain sentence plus the next step; the raw code is kept only for Copy information.'
	);

	maca_assert(
		Npcink_Cloud_WordPress_AI_Connector::response_indicates_in_progress_run( array( 'status' => 'running' ) )
		&& Npcink_Cloud_WordPress_AI_Connector::response_indicates_in_progress_run( array( 'data' => array( 'status' => 'queued' ) ) )
		&& Npcink_Cloud_WordPress_AI_Connector::response_indicates_in_progress_run( array( 'data' => array( 'result' => array( 'status' => 'submitted' ) ) ) )
		&& ! Npcink_Cloud_WordPress_AI_Connector::response_indicates_in_progress_run( array( 'status' => 'succeeded' ) )
		&& ! Npcink_Cloud_WordPress_AI_Connector::response_indicates_in_progress_run( array( 'status' => 'ok' ) )
		&& ! Npcink_Cloud_WordPress_AI_Connector::response_indicates_in_progress_run( array() )
		&& false === strpos( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'runtime_run_in_progress', 'cloud_wp_ai_run_in_progress' ), 'did not return usable output' )
		&& false !== strpos( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'runtime_run_in_progress', 'cloud_wp_ai_run_in_progress' ), 'still processing' ),
		'Behavior: an in-progress run reports a wait hint instead of an output-missing retry prompt.'
	);
}
