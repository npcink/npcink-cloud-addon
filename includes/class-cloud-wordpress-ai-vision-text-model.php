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
	final class Npcink_Cloud_WordPress_AI_Vision_Text_Model implements
		\WordPress\AiClient\Providers\Models\Contracts\ModelInterface,
		\WordPress\AiClient\Providers\Models\TextGeneration\Contracts\TextGenerationModelInterface {
		/**
		 * Model metadata.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private $metadata;

		/**
		 * Provider metadata.
		 *
		 * @var \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		private $provider_metadata;

		/**
		 * Model config.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		private $config;

		/**
		 * Constructor.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata Model metadata.
		 * @param \WordPress\AiClient\Providers\DTO\ProviderMetadata     $provider_metadata Provider metadata.
		 */
		public function __construct(
			\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $metadata,
			\WordPress\AiClient\Providers\DTO\ProviderMetadata $provider_metadata
		) {
			$this->metadata          = $metadata;
			$this->provider_metadata = $provider_metadata;
			$this->config            = new \WordPress\AiClient\Providers\Models\DTO\ModelConfig();
		}

		/**
		 * Gets model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		public function metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return $this->metadata;
		}

		/**
		 * Gets provider metadata.
		 *
		 * @return \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		public function providerMetadata(): \WordPress\AiClient\Providers\DTO\ProviderMetadata {
			return $this->provider_metadata;
		}

		/**
		 * Sets model config.
		 *
		 * @param \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config Model config.
		 * @return void
		 */
		public function setConfig( \WordPress\AiClient\Providers\Models\DTO\ModelConfig $config ): void {
			$this->config = $config;
		}

		/**
		 * Gets model config.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		public function getConfig(): \WordPress\AiClient\Providers\Models\DTO\ModelConfig {
			return $this->config;
		}

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
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_not_supported', 'cloud_wp_ai_scene_not_supported' ) );
			}

			if ( 1 !== count( $prompt ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'chat_history_not_supported', 'cloud_wp_ai_chat_history_not_supported' ) );
			}

			if ( null !== $this->config->getFunctionDeclarations() || null !== $this->config->getWebSearch() ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'tools_not_supported', 'cloud_wp_ai_tools_not_supported' ) );
			}

			$text = $this->prompt_text( $prompt );
			if ( '' === $text ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'scene_input_required', 'cloud_wp_ai_scene_input_required' ) );
			}

			$attachment_id = Npcink_Cloud_WordPress_AI_Alt_Text_Handoff::attachment_id_from_ability_input( $ability_input );
			if ( is_wp_error( $attachment_id ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_attachment_error( $attachment_id ) );
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
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( (string) $response->get_error_code(), (string) ( $evidence['cloud_error_code'] ?? '' ), (string) $response->get_error_message() ) );
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
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'output_missing', 'cloud_wp_ai_alt_text_output_missing' ) );
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

		/**
		 * Extracts text from a single user prompt.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return string
		 */
		private function prompt_text( array $prompt ): string {
			$message = $prompt[0];
			if ( ! $message->getRole()->isUser() ) {
				return '';
			}

			$parts = array();
			foreach ( $message->getParts() as $part ) {
				$text = $part->getText();
				if ( null !== $text && '' !== trim( $text ) ) {
					$parts[] = trim( $text );
				}
			}

			return trim( implode( "\n\n", $parts ) );
		}

		/**
		 * Extracts text from the task-bound Cloud connector result.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @param string              $expected_task Expected WordPress operation task.
		 * @return string
		 */
		private function extract_text( array $response, string $expected_task ): string {
			$result             = is_array( $response['data']['result'] ?? null ) ? $response['data']['result'] : array();
			$operation_contract = is_array( $result['operation_contract'] ?? null ) ? $result['operation_contract'] : array();
			if (
				'cloud_connector_result.v1' !== (string) ( $result['contract_version'] ?? '' )
				|| true !== ( $result['suggestion_only'] ?? null )
				|| 'npcink-cloud-addon' !== (string) ( $result['connector_id'] ?? '' )
				|| 'wordpress_operation.v1' !== (string) ( $operation_contract['contract_version'] ?? '' )
				|| $expected_task !== (string) ( $operation_contract['task'] ?? '' )
			) {
				return '';
			}

			$output = is_array( $result['output'] ?? null ) ? $result['output'] : array();

			return is_string( $output['output_text'] ?? null ) ? trim( $output['output_text'] ) : '';
		}
	}
}
