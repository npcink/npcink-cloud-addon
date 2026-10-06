<?php
/**
 * Shared base for the WordPress AI scene models.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_WordPress_AI_Scene_Model' ) ) {
	/**
	 * Holds the metadata/config plumbing and the shared prompt/result
	 * projection helpers of the scene-bound Cloud models.
	 */
	abstract class Npcink_Cloud_WordPress_AI_Scene_Model {

		/**
		 * Model metadata.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		protected $metadata;

		/**
		 * Provider metadata.
		 *
		 * @var \WordPress\AiClient\Providers\DTO\ProviderMetadata
		 */
		protected $provider_metadata;

		/**
		 * Model config.
		 *
		 * @var \WordPress\AiClient\Providers\Models\DTO\ModelConfig
		 */
		protected $config;

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
		 * Extracts text from a single user prompt.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return string
		 */
		protected function prompt_text( array $prompt ): string {
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
		protected function extract_text( array $response, string $expected_task ): string {
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
