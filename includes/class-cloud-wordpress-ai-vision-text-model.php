<?php
/**
 * Vision text model for the WordPress AI PHP client.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	interface_exists( 'WordPress\\AiClient\\Providers\\Models\\Contracts\\ModelInterface' )
	&& interface_exists( 'WordPress\\AiClient\\Providers\\Models\\TextGeneration\\Contracts\\TextGenerationModelInterface' )
	&& ! class_exists( 'Npcink_Cloud_WordPress_AI_Vision_Text_Model' )
) {
	/**
	 * Scene-gated vision text model for WordPress AI alt text generation.
	 */
	final class Npcink_Cloud_WordPress_AI_Vision_Text_Model extends Npcink_Cloud_WordPress_AI_Scene_Model implements
		\WordPress\AiClient\Providers\Models\Contracts\ModelInterface,
		\WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface {

		/**
		 * Generates alt text through the bounded Cloud vision runtime seam.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult
		 */
		public function generateTextResult( array $prompt ): \WordPress\AiClient\Results\DTO\GenerativeAiResult {
			// Clear a prior ability's evidence before any validation or runtime call.
			Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( '' );
			Npcink_Cloud_WordPress_AI_Connector::reset_runtime_failure_evidence();
			$ability_input = Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context();
			if ( array() === $ability_input ) {
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_not_supported', 'cloud_wp_ai_scene_not_supported' ) );
			}

			if ( 1 !== count( $prompt ) ) {
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'chat_history_not_supported', 'cloud_wp_ai_chat_history_not_supported' ) );
			}

			if ( null !== $this->config->getFunctionDeclarations() || null !== $this->config->getWebSearch() ) {
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'tools_not_supported', 'cloud_wp_ai_tools_not_supported' ) );
			}

			$text = $this->prompt_text( $prompt );
			if ( '' === $text ) {
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_input_required', 'cloud_wp_ai_scene_input_required' ) );
			}

			$attachment_id = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::attachment_id_from_ability_input( $ability_input );
			if ( is_wp_error( $attachment_id ) ) {
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_attachment_error( $attachment_id ) );
			}

			$started  = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_start();
			$response = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::dispatch( $attachment_id, $text );
			$log_event = array(
					'type'                       => 'vision',
					'operation'                  => 'npcink-cloud/connector-runtime',
					'task'                       => 'alt_text_suggest',
					'contract_version'           => 'cloud_connector_runtime.v1',
					'operation_contract_version' => 'wordpress_operation.v1',
					'response'                   => $response,
					'duration_ms'                => Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started ),
					'fallback_model_id'          => Npcink_Cloud_WordPress_AI_Connector::VISION_MODEL_ID,
				);

			if ( is_wp_error( $response ) ) {
				$evidence = Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_from_wp_error( $response );
				$log_event['cloud_run_id'] = (string) ( $evidence['run_id'] ?? '' );
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( (string) $response->get_error_code(), (string) ( $evidence['cloud_error_code'] ?? '' ), (string) $response->get_error_message() ) );
			}

			$output_text = $this->extract_text( is_array( $response ) ? $response : array(), 'alt_text_suggest' );
			if ( '' === $output_text ) {
				$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
				Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( $cloud_run_id );
				Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_evidence( array(
					'run_id'         => $cloud_run_id,
					'error_stage'    => 'output_validation',
					'quality_reason' => 'output_missing',
				) );
				$log_event['validation_error'] = new WP_Error( 'cloud_wp_ai_output_missing', 'Cloud response did not include valid alt text output.' );
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				self::throw_user_facing( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'output_missing', 'cloud_wp_ai_alt_text_output_missing' ) );
			}

			Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
			$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
			Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( $cloud_run_id );
			$run_id = '' !== $cloud_run_id ? $cloud_run_id : wp_generate_uuid4();
			return new \WordPress\AiClient\Results\DTO\GenerativeAiResult(
				$run_id,
				array(
					new \WordPress\AiClient\Results\DTO\Candidate(
						new \WordPress\AiClient\Messages\DTO\ModelMessage(
							array( new \WordPress\AiClient\Messages\DTO\MessagePart( $output_text ) )
						),
						\WordPress\AiClient\Results\Enums\FinishReasonEnum::stop()
					),
				),
				new \WordPress\AiClient\Results\DTO\TokenUsage( 0, 0, 0 ),
				$this->provider_metadata,
				$this->metadata,
				array(
					'contract_version'       => 'cloud_connector_result.v1',
					'task'                   => 'alt_text_suggest',
					'suggestion_only'        => true,
					'direct_wordpress_write' => false,
				)
			);
		}
	}
}
