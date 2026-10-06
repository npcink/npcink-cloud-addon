<?php
/**
 * Image generation model with verified artifact delivery.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	interface_exists( 'WordPress\\AiClient\\Providers\\Models\\Contracts\\ModelInterface' )
	&& interface_exists( 'WordPress\\AiClient\\Providers\\Models\\ImageGeneration\\Contracts\\ImageGenerationModelInterface' )
	&& ! class_exists( 'Npcink_Cloud_WordPress_AI_Image_Model' )
) {
	/**
	 * Scene-gated image model that forwards text-to-image WordPress AI calls to Cloud.
	 */
	final class Npcink_Cloud_WordPress_AI_Image_Model implements
		\WordPress\AiClient\Providers\Models\Contracts\ModelInterface,
		\WordPress\AiClient\Providers\Models\ImageGeneration\Contracts\ImageGenerationModelInterface {
		private const ARTIFACT_ID_PATTERN = '/^art_[0-9a-f]{32}$/';
		private const DELIVERY_ID_PATTERN = '/^mdl_[0-9a-f]{32}$/';
		private const MAX_IMAGE_BYTES = 26214400;
		private const MAX_IMAGE_AGGREGATE_BYTES = 33554432;
		private const MAX_IMAGE_AXIS = 8192;
		private const MAX_IMAGE_AREA = 16777216;
		private const MAX_IMAGE_CANDIDATES = 4;
		private const IMAGE_ARTIFACT_KEYS = array(
			'artifact_id',
			'artifact_reference',
			'status',
			'media_kind',
			'operation',
			'content_type',
			'format',
			'width',
			'height',
			'filesize_bytes',
			'checksum',
			'expires_at',
		);

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
		 * Generates an image result through the bounded Cloud runtime seam.
		 *
		 * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt Prompt messages.
		 * @return \WordPress\AiClient\Results\DTO\GenerativeAiResult
		 */
		public function generateImageResult( array $prompt ): \WordPress\AiClient\Results\DTO\GenerativeAiResult {
			Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( '' );
			Npcink_Cloud_WordPress_AI_Connector::reset_runtime_failure_evidence();
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

			$request = array(
				'contract_version' => 'image_generation_request.v1',
				'task'             => 'image_generation',
				'prompt'           => $text,
				'n'                => $this->image_count(),
				'aspect_ratio'     => $this->aspect_ratio(),
				'resolution'       => 'medium',
				'timeout_seconds'  => 90,
				'retention_ttl'    => 86400,
			);

			$trace_id = 'trace_wp_ai_image_' . wp_generate_uuid4();
			$started  = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_start();
			$response = npcink_cloud_addon_execute_wordpress_ai_image_generation_runtime(
				$request,
				$trace_id,
				'wp_ai_image_' . wp_generate_uuid4()
			);
			$log_event = array(
					'type'             => 'image',
					'operation'        => 'npcink-cloud/generate-image',
					'task'             => 'image_generation',
					'contract_version' => 'image_generation_request.v1',
					'response'         => $response,
					'duration_ms'      => Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started ),
					'fallback_model_id' => Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID,
				);

			if ( is_wp_error( $response ) ) {
				$evidence = Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_from_wp_error( $response );
				$log_event['cloud_run_id'] = (string) ( $evidence['run_id'] ?? '' );
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( (string) $response->get_error_code(), (string) ( $evidence['cloud_error_code'] ?? '' ), (string) $response->get_error_message() ) );
			}

			$result     = $this->extract_result( is_array( $response ) ? $response : array() );
			try {
				$candidates = $this->extract_image_candidates( $result, $trace_id );
				if ( empty( $candidates ) ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'output_missing', 'cloud_wp_ai_image_output_missing' ) );
				}
			} catch ( \Throwable $error ) {
				$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
				Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( $cloud_run_id );
				Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_evidence( array(
					'run_id'         => $cloud_run_id,
					'error_stage'    => 'artifact_validation',
					'quality_reason' => 'image_output_invalid',
				) );
				$log_event['validation_error'] = new WP_Error( 'cloud_wp_ai_image_output_invalid', 'Cloud image output could not be downloaded or verified.' );
				$log_event['duration_ms'] = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started );
				Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
				throw $error;
			}

			$log_event['duration_ms'] = Npcink_Cloud_WordPress_AI_Connector::runtime_timer_elapsed_ms( $started );
			Npcink_Cloud_WordPress_AI_Connector::maybe_log_wordpress_ai_request_evidence( $log_event );
			$cloud_run_id = Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( $response );
			return new \WordPress\AiClient\Results\DTO\GenerativeAiResult(
				'' !== $cloud_run_id ? $cloud_run_id : wp_generate_uuid4(),
				$candidates,
				new \WordPress\AiClient\Results\DTO\TokenUsage( 0, 0, 0 ),
				$this->provider_metadata,
				$this->metadata,
				array(
					'contract_version'          => 'image_generation_result.v1',
					'task'                      => 'image_generation',
					'suggestion_only'           => true,
					'direct_wordpress_write'    => false,
					'model_id'                  => (string) ( $result['model_id'] ?? '' ),
					'provider_response_format'  => (string) ( $result['provider_response_format'] ?? '' ),
				)
			);
		}

		/**
		 * Extracts text from a single user prompt and rejects reference-image refinement.
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
				if ( null !== $part->getFile() ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'reference_image_not_supported', 'cloud_wp_ai_reference_image_not_supported' ) );
				}

				$text = $part->getText();
				if ( null !== $text && '' !== trim( $text ) ) {
					$parts[] = trim( $text );
				}
			}

			return trim( implode( "\n\n", $parts ) );
		}

		/**
		 * Returns the requested image candidate count.
		 *
		 * @return int
		 */
		private function image_count(): int {
			$count = $this->config->getCandidateCount();

			return min( 4, max( 1, null === $count ? 1 : (int) $count ) );
		}

		/**
		 * Returns a Cloud-supported aspect ratio.
		 *
		 * @return string
		 */
		private function aspect_ratio(): string {
			$aspect_ratio = $this->config->getOutputMediaAspectRatio();
			if ( is_string( $aspect_ratio ) && '' !== $aspect_ratio ) {
				return $aspect_ratio;
			}

			$orientation = $this->config->getOutputMediaOrientation();
			if ( null !== $orientation ) {
				if ( $orientation->isLandscape() ) {
					return '16:9';
				}
				if ( $orientation->isPortrait() ) {
					return '9:16';
				}
			}

			return '1:1';
		}

		/**
		 * Extracts the Cloud image result payload.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @return array<string,mixed>
		 */
		private function extract_result( array $response ): array {
			if ( isset( $response['data']['result'] ) && is_array( $response['data']['result'] ) ) {
				return $response['data']['result'];
			}
			if ( isset( $response['result'] ) && is_array( $response['result'] ) ) {
				return $response['result'];
			}
			if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
				return $response['data'];
			}

			return $response;
		}

		/**
		 * Extracts image candidates from a Cloud image generation response.
		 *
		 * @param array<string,mixed> $result Cloud result payload.
		 * @param string              $trace_id Runtime trace id.
		 * @return list<\WordPress\AiClient\Results\DTO\Candidate>
		 */
		private function extract_image_candidates( array $result, string $trace_id = '' ): array {
			$images = $this->download_artifact_images( $result, $trace_id );

			$candidates = array();
			foreach ( $images as $image ) {
				if ( ! is_array( $image ) ) {
					continue;
				}

				$mime_type = isset( $image['mime_type'] ) && is_string( $image['mime_type'] ) && '' !== $image['mime_type']
					? $image['mime_type']
					: 'image/png';
				$file_data = isset( $image['b64_json'] ) && is_string( $image['b64_json'] )
					? $image['b64_json']
					: '';

				if ( '' === $file_data ) {
					continue;
				}

				$file = new \WordPress\AiClient\Files\DTO\File( $file_data, $mime_type );
				$candidates[] = new \WordPress\AiClient\Results\DTO\Candidate(
					new \WordPress\AiClient\Messages\DTO\Message(
						\WordPress\AiClient\Messages\Enums\MessageRoleEnum::model(),
						array( new \WordPress\AiClient\Messages\DTO\MessagePart( $file ) )
					),
					\WordPress\AiClient\Results\Enums\FinishReasonEnum::stop()
				);
			}

			return $candidates;
		}

		/**
		 * Downloads, verifies, and acknowledges Cloud image-generation artifacts.
		 *
		 * This is verified transport only. It returns inline preview bytes and does
		 * not import, persist, approve, or write a WordPress media object.
		 *
		 * @param array<string,mixed> $result Cloud image result payload.
		 * @param string              $trace_id Runtime trace id.
		 * @return list<array{b64_json:string,mime_type:string}>
		 */
		private function download_artifact_images( array $result, string $trace_id ): array {
			if (
				'image_generation_result.v1' !== (string) ( $result['contract_version'] ?? '' )
				|| 'image_generation_artifacts' !== (string) ( $result['artifact_type'] ?? '' )
				|| 'image.generate.v1' !== (string) ( $result['operation'] ?? '' )
				|| true !== ( $result['suggestion_only'] ?? null )
				|| true !== ( $result['requires_local_review'] ?? null )
				|| ! is_array( $result['artifacts'] ?? null )
				|| count( $result['artifacts'] ) > self::MAX_IMAGE_CANDIDATES
			) {
				return array();
			}

			$aggregate_bytes = 0;
			foreach ( $result['artifacts'] as $artifact ) {
				$artifact_bytes = is_array( $artifact ) ? ( $artifact['filesize_bytes'] ?? null ) : null;
				if ( ! is_int( $artifact_bytes ) || $artifact_bytes < 1 || $artifact_bytes > self::MAX_IMAGE_BYTES ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_contract_invalid', 'cloud_wp_ai_image_artifact_invalid' ) );
				}
				$aggregate_bytes += $artifact_bytes;
				if ( $aggregate_bytes > self::MAX_IMAGE_AGGREGATE_BYTES ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_preview_limit', 'cloud_wp_ai_image_preview_limit' ) );
				}
			}

			$client = Npcink_Cloud_Media_Derivative_Transport::verified_client();
			if ( is_wp_error( $client ) ) {
				throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( (string) $client->get_error_code(), '', (string) $client->get_error_message() ) );
			}

			$images = array();
			foreach ( $result['artifacts'] as $artifact ) {
				if ( ! is_array( $artifact ) ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_contract_invalid', 'cloud_wp_ai_image_artifact_invalid' ) );
				}

				$artifact_contract = $artifact;
				if ( array_key_exists( 'purged_at', $artifact_contract ) ) {
					if ( null !== $artifact_contract['purged_at'] ) {
						throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_expired', 'cloud_wp_ai_image_artifact_purged' ) );
					}
					unset( $artifact_contract['purged_at'] );
				}
				$artifact_keys = array_keys( $artifact_contract );
				$reference     = is_array( $artifact['artifact_reference'] ?? null ) ? $artifact['artifact_reference'] : array();
				$artifact_id   = (string) ( $artifact['artifact_id'] ?? '' );
				$mime_type     = $this->normalize_image_mime_type( (string) ( $artifact['content_type'] ?? '' ) );
				$width         = $artifact['width'] ?? null;
				$height        = $artifact['height'] ?? null;
				$byte_size     = $artifact['filesize_bytes'] ?? null;
				$checksum      = strtolower( (string) ( $artifact['checksum'] ?? '' ) );
				$expires_at    = (string) ( $artifact['expires_at'] ?? '' );
				$expires_ts    = $this->strict_image_timestamp( $expires_at );

				if (
					array() !== array_diff( self::IMAGE_ARTIFACT_KEYS, $artifact_keys )
					|| array() !== array_diff( $artifact_keys, self::IMAGE_ARTIFACT_KEYS )
					|| 1 !== preg_match( self::ARTIFACT_ID_PATTERN, $artifact_id )
					|| array( 'artifact_id' ) !== array_keys( $reference )
					|| $artifact_id !== (string) ( $reference['artifact_id'] ?? '' )
					|| 'available' !== (string) ( $artifact['status'] ?? '' )
					|| 'image' !== (string) ( $artifact['media_kind'] ?? '' )
					|| 'image.generate.v1' !== (string) ( $artifact['operation'] ?? '' )
					|| '' === $mime_type
					|| $this->image_format_for_mime( $mime_type ) !== (string) ( $artifact['format'] ?? '' )
					|| ! is_int( $width )
					|| $width < 1
					|| ! is_int( $height )
					|| $height < 1
					|| $width > self::MAX_IMAGE_AXIS
					|| $height > self::MAX_IMAGE_AXIS
					|| ( $width * $height ) > self::MAX_IMAGE_AREA
					|| ! is_int( $byte_size )
					|| $byte_size < 1
					|| $byte_size > self::MAX_IMAGE_BYTES
					|| 1 !== preg_match( '/^sha256:[0-9a-f]{64}$/', $checksum )
					|| false === $expires_ts
					|| $expires_ts <= time()
				) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_contract_invalid', 'cloud_wp_ai_image_artifact_invalid' ) );
				}

				$download = $client->pull_media_artifact( $artifact_id, $trace_id );
				if ( is_wp_error( $download ) ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( (string) $download->get_error_code(), '', (string) $download->get_error_message() ) );
				}

				$contents      = is_string( $download['body'] ?? null ) ? $download['body'] : '';
				$response_mime = $this->normalize_image_mime_type( (string) ( $download['content_type'] ?? '' ) );
				$actual_checksum = 'sha256:' . hash( 'sha256', $contents );
				$delivery_id     = (string) ( $download['delivery_id'] ?? '' );
				$ack_deadline_at = (string) ( $download['delivery_ack_deadline'] ?? '' );
				$ack_deadline    = $this->strict_image_timestamp( $ack_deadline_at );
				$image_info      = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $contents ) : false;
				$decoded_mime    = is_array( $image_info ) ? $this->normalize_image_mime_type( (string) ( $image_info['mime'] ?? '' ) ) : '';

				if (
					'' === $contents
					|| $mime_type !== $response_mime
					|| $mime_type !== $decoded_mime
					|| $byte_size !== strlen( $contents )
					|| $byte_size !== absint( $download['content_length'] ?? 0 )
					|| $checksum !== $actual_checksum
					|| $artifact_id !== (string) ( $download['artifact_id'] ?? '' )
					|| $checksum !== strtolower( (string) ( $download['artifact_checksum'] ?? '' ) )
					|| ! is_array( $image_info )
					|| $width !== absint( $image_info[0] ?? 0 )
					|| $height !== absint( $image_info[1] ?? 0 )
					|| 1 !== preg_match( self::DELIVERY_ID_PATTERN, $delivery_id )
					|| false === $ack_deadline
					|| $ack_deadline <= time()
					|| $ack_deadline > $expires_ts
				) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_verification_failed', 'cloud_wp_ai_image_artifact_verification_failed' ) );
				}

				$ack = $client->acknowledge_media_artifact_delivery(
					$artifact_id,
					array(
						'contract_version'      => 'media_artifact_delivery_ack.v1',
						'delivery_id'           => $delivery_id,
						'received_byte_size'    => $byte_size,
						'received_checksum'     => $checksum,
					),
					$trace_id
				);
				if ( is_wp_error( $ack ) ) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_runtime_failure( (string) $ack->get_error_code(), '', (string) $ack->get_error_message() ) );
				}
				$acknowledged_at = $this->strict_image_timestamp( (string) ( $ack['acknowledged_at'] ?? '' ) );
				$ack_expires_at  = $this->strict_image_timestamp( (string) ( $ack['artifact_expires_at'] ?? '' ) );
				if (
					$artifact_id !== (string) ( $ack['artifact_id'] ?? '' )
					|| $delivery_id !== (string) ( $ack['delivery_id'] ?? '' )
					|| $byte_size !== ( $ack['received_byte_size'] ?? null )
					|| $checksum !== (string) ( $ack['received_checksum'] ?? '' )
					|| true !== ( $ack['byte_size_verified'] ?? null )
					|| true !== ( $ack['checksum_verified'] ?? null )
					|| false === $acknowledged_at
					|| $acknowledged_at > $ack_deadline
					|| false === $ack_expires_at
					|| $ack_expires_at !== $expires_ts
					|| (string) ( $ack['artifact_expires_at'] ?? '' ) !== $expires_at
				) {
					throw new \WordPress\AiClient\Common\Exception\RuntimeException( Npcink_Cloud_WordPress_AI_Connector::user_facing_connector_error( 'artifact_ack_invalid', 'cloud_wp_ai_image_delivery_ack_invalid' ) );
				}

				$images[] = array(
					'b64_json' => base64_encode( $contents ),
					'mime_type' => $mime_type,
				);
			}

			return $images;
		}

		/**
		 * Normalizes supported generated-image MIME types.
		 *
		 * @param string $mime_type Raw MIME type.
		 * @return string
		 */
		private function normalize_image_mime_type( string $mime_type ): string {
			$mime_type = strtolower( trim( explode( ';', $mime_type, 2 )[0] ) );

			return in_array( $mime_type, array( 'image/jpeg', 'image/png', 'image/webp' ), true ) ? $mime_type : '';
		}

		/**
		 * Maps a supported generated-image MIME type to its Cloud format.
		 *
		 * @param string $mime_type Normalized MIME type.
		 * @return string
		 */
		private function image_format_for_mime( string $mime_type ): string {
			return array(
				'image/jpeg' => 'jpeg',
				'image/png'  => 'png',
				'image/webp' => 'webp',
			)[ $mime_type ] ?? '';
		}

		/**
		 * Parses exact canonical UTC RFC3339 image-artifact timestamps.
		 *
		 * @param string $value Timestamp value.
		 * @return int|false
		 */
		private function strict_image_timestamp( string $value ) {
			$utc     = new \DateTimeZone( 'UTC' );
			$formats = array(
				'!Y-m-d\TH:i:s\Z'   => 'Y-m-d\TH:i:s\Z',
				'!Y-m-d\TH:i:s.u\Z' => 'Y-m-d\TH:i:s.u\Z',
				'!Y-m-d\TH:i:sP'    => 'Y-m-d\TH:i:sP',
				'!Y-m-d\TH:i:s.uP'  => 'Y-m-d\TH:i:s.uP',
			);

			foreach ( $formats as $parse_format => $roundtrip_format ) {
				$timestamp = \DateTimeImmutable::createFromFormat( $parse_format, $value, $utc );
				$errors    = \DateTimeImmutable::getLastErrors();
				if (
					false !== $timestamp
					&& ( false === $errors || ( 0 === $errors['warning_count'] && 0 === $errors['error_count'] ) )
					&& $value === $timestamp->format( $roundtrip_format )
					&& '+00:00' === $timestamp->format( 'P' )
				) {
					return $timestamp->getTimestamp();
				}
			}

			return false;
		}
	}
}
