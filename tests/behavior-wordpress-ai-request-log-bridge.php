<?php
/**
 * Behavior tests for the optional WordPress AI request log bridge.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

namespace WordPress\AI\Logging {
	/**
	 * Stub AI request log manager for pure PHP behavior tests.
	 */
	class AI_Request_Log_Manager {
		/**
		 * Initializes the log store.
		 *
		 * @return void
		 */
		public function init(): void {
			$GLOBALS['maca_wpai_request_log_inits'] = (int) ( $GLOBALS['maca_wpai_request_log_inits'] ?? 0 ) + 1;
		}

		/**
		 * Captures a log record.
		 *
		 * @param array<string,mixed> $data Log data.
		 * @return string|false
		 */
		public function log( array $data ) {
			// Match WordPress AI 1.3.0: modality is not a supported log type.
			if ( ! in_array( $data['type'] ?? '', array( 'ai_client', 'mcp_tool', 'ability' ), true ) ) {
				return false;
			}
			$GLOBALS['maca_wpai_request_logs'][] = $data;

			return (string) count( $GLOBALS['maca_wpai_request_logs'] );
		}
	}
}

namespace {
	require_once __DIR__ . '/helpers.php';

	maca_load_addon_classes();
	require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-connector.php';

	$GLOBALS['maca_wpai_request_logs'] = array();
	$GLOBALS['maca_wpai_request_log_inits'] = 0;

	maca_reset_test_state();
	update_option( 'wpai_features_enabled', '1', false );
	update_option( 'wpai_feature_ai-request-logging_enabled', '1', false );

	Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence(
		array(
			'type'             => 'image',
			'operation'        => 'npcink-cloud/generate-image',
			'task'             => 'image_generation',
			'contract_version' => 'image_generation_request.v1',
			'response'         => array(
				'run_id' => 'run_image_1',
				'data'   => array(
					'result' => array(
						'model_id' => 'cloud-image-model',
						'provider_metadata' => array(
							'id' => 'cloud-provider',
						),
						'images' => array(
							array(
								'b64_json' => str_repeat( 'a', 64 ),
							),
						),
					),
				),
			),
			'duration_ms'      => 1234,
			'fallback_model_id' => Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID,
		)
	);

	$log     = $GLOBALS['maca_wpai_request_logs'][0] ?? array();
	$context = is_array( $log['context'] ?? null ) ? $log['context'] : array();

	maca_assert(
		1 === count( $GLOBALS['maca_wpai_request_logs'] )
		&& 'ai_client' === (string) ( $log['type'] ?? '' )
		&& 'image' === (string) ( $context['modality'] ?? '' )
		&& 'success' === (string) ( $log['status'] ?? '' )
		&& 'npcink-cloud/generate-image:image_generation' === (string) ( $log['operation'] ?? '' )
		&& 'cloud-provider' === (string) ( $log['provider'] ?? '' )
		&& 'cloud-image-model' === (string) ( $log['model'] ?? '' )
		&& 1234 === (int) ( $log['duration_ms'] ?? 0 )
		&& 'run_image_1' === (string) ( $context['cloud_run_id'] ?? '' )
		&& true === (bool) ( $context['suggestion_only'] ?? false )
		&& false === (bool) ( $context['direct_wordpress_write'] ?? true )
		&& ! isset( $context['input_preview'] )
		&& ! isset( $context['output_preview'] )
		&& false === strpos( wp_json_encode( $log ), str_repeat( 'a', 64 ) ),
		'Behavior: WordPress AI request log bridge writes metadata-only Cloud image run evidence.'
	);

	$text_input_sentinel  = 'PRIVATE_TEXT_INPUT_MUST_NOT_REACH_REQUEST_LOG';
	$text_output_sentinel = 'PRIVATE_TEXT_OUTPUT_MUST_NOT_REACH_REQUEST_LOG';
	Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence(
		array(
			'type'                       => 'text',
			'operation'                  => 'npcink-cloud/connector-runtime',
			'task'                       => 'content_summary',
			'contract_version'           => 'cloud_connector_runtime.v1',
			'operation_contract_version' => 'wordpress_operation.v1',
			'input'                      => array( 'source_text' => $text_input_sentinel ),
			'response'                   => array(
				'data' => array(
					'run_id' => 'run_text_summary_1',
					'result' => array(
						'provider_id' => 'cloud-text-provider',
						'model_id'    => 'cloud-text-model',
						'output'      => array( 'output_text' => $text_output_sentinel ),
					),
				),
			),
			'duration_ms'                => 321,
			'fallback_model_id'          => Npcink_Cloud_WordPress_AI_Connector::MODEL_ID,
		)
	);

	$text_log     = $GLOBALS['maca_wpai_request_logs'][1] ?? array();
	$text_context = is_array( $text_log['context'] ?? null ) ? $text_log['context'] : array();
	maca_assert(
		2 === count( $GLOBALS['maca_wpai_request_logs'] )
		&& 'ai_client' === (string) ( $text_log['type'] ?? '' )
		&& 'text' === (string) ( $text_context['modality'] ?? '' )
		&& 'npcink-cloud/connector-runtime:content_summary' === (string) ( $text_log['operation'] ?? '' )
		&& 'cloud-text-provider' === (string) ( $text_log['provider'] ?? '' )
		&& 'cloud-text-model' === (string) ( $text_log['model'] ?? '' )
		&& 'run_text_summary_1' === (string) ( $text_context['cloud_run_id'] ?? '' )
		&& true === (bool) ( $text_context['suggestion_only'] ?? false )
		&& false === (bool) ( $text_context['direct_wordpress_write'] ?? true )
		&& 'omitted_metadata_only' === (string) ( $text_context['content_storage'] ?? '' )
		&& ! isset( $text_context['input_preview'] )
		&& ! isset( $text_context['output_preview'] )
		&& false === strpos( wp_json_encode( $text_log ), $text_input_sentinel )
		&& false === strpos( wp_json_encode( $text_log ), $text_output_sentinel ),
		'Behavior: WordPress AI text success keeps nested cloud_run_id as metadata-only correlation evidence.'
	);

	update_option( 'wpai_feature_ai-request-logging_enabled', '', false );
	Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence(
		array(
			'type'        => 'text',
			'task'        => 'content_summary',
			'response'    => array( 'run_id' => 'run_disabled' ),
			'duration_ms' => 1,
		)
	);

	maca_assert(
		2 === count( $GLOBALS['maca_wpai_request_logs'] ),
		'Behavior: WordPress AI request log bridge respects the AI request logging feature flag.'
	);

	update_option( 'wpai_feature_ai-request-logging_enabled', '1', false );
	Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence(
		array(
			'type'                       => 'text',
			'operation'                  => 'npcink-cloud/connector-runtime',
			'task'                       => 'content_summary',
			'contract_version'           => 'cloud_connector_runtime.v1',
			'operation_contract_version' => 'wordpress_operation.v1',
			'response'                   => new WP_Error( 'cloud_runtime_failed', 'Provider timeout while generating text output.' ),
			'duration_ms'                => 456,
			'fallback_model_id'          => Npcink_Cloud_WordPress_AI_Connector::MODEL_ID,
		)
	);

	$error_log = $GLOBALS['maca_wpai_request_logs'][2] ?? array();
	$error_context = is_array( $error_log['context'] ?? null ) ? $error_log['context'] : array();
	maca_assert(
		3 === count( $GLOBALS['maca_wpai_request_logs'] )
		&& 'ai_client' === (string) ( $error_log['type'] ?? '' )
		&& 'error' === (string) ( $error_log['status'] ?? '' )
		&& 'npcink-cloud/connector-runtime:content_summary' === (string) ( $error_log['operation'] ?? '' )
		&& 'Provider timeout while generating text output.' === (string) ( $error_log['error_message'] ?? '' )
		&& 'npcink-cloud' === (string) ( $error_log['provider'] ?? '' )
		&& Npcink_Cloud_WordPress_AI_Connector::MODEL_ID === (string) ( $error_log['model'] ?? '' )
		&& 'cloud_connector_runtime.v1' === (string) ( $error_context['contract_version'] ?? '' )
		&& 'wordpress_operation.v1' === (string) ( $error_context['operation_contract_version'] ?? '' )
		&& 'editor' === (string) ( $error_context['channel'] ?? '' )
		&& 'npcink-cloud-addon' === (string) ( $error_context['connector_id'] ?? '' ),
		'Behavior: WordPress AI request log bridge records bounded Cloud runtime errors without request content.'
	);

	Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence(
		array(
			'type'     => 'vision',
			'task'     => 'alt_text_suggest',
			'response' => array( 'run_id' => 'run_vision_1' ),
		)
	);
	$vision_log = $GLOBALS['maca_wpai_request_logs'][3] ?? array();
	maca_assert(
		4 === count( $GLOBALS['maca_wpai_request_logs'] )
		&& 'ai_client' === ( $vision_log['type'] ?? '' )
		&& 'vision' === ( $vision_log['context']['modality'] ?? '' )
		&& 'run_vision_1' === ( $vision_log['context']['cloud_run_id'] ?? '' ),
		'Behavior: vision uses the official AI Client log type and preserves modality in context.'
	);

	Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence(
		array(
			'type' => 'image',
			'response' => array( 'run_id' => 'run_invalid_image' ),
			'validation_error' => new \WP_Error( 'invalid_output', 'Image verification failed.' ),
		)
	);
	$invalid_log = $GLOBALS['maca_wpai_request_logs'][4] ?? array();
	maca_assert(
		'error' === ( $invalid_log['status'] ?? '' )
		&& 'run_invalid_image' === ( $invalid_log['context']['cloud_run_id'] ?? '' )
		&& 'Image verification failed.' === ( $invalid_log['error_message'] ?? '' ),
		'Behavior: an HTTP success with invalid output is logged as an error and retains Cloud correlation.'
	);
}
