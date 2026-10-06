<?php
/**
 * Npcink Cloud model metadata directory for the WordPress AI PHP client.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	interface_exists( 'WordPress\\AiClient\\Providers\\Contracts\\ModelMetadataDirectoryInterface' )
	&& ! class_exists( 'Npcink_Cloud_WordPress_AI_Model_Metadata_Directory' )
) {
	/**
	 * Fixed model directory for the Npcink Cloud scene text model.
	 */
	final class Npcink_Cloud_WordPress_AI_Model_Metadata_Directory implements \WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface {
		/**
		 * Lists available model metadata.
		 *
		 * @return list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata>
		 */
		public function listModelMetadata(): array {
			$models = array();
			foreach ( array( Npcink_Cloud_WordPress_AI_Connector::MODEL_ID, Npcink_Cloud_WordPress_AI_Connector::VISION_MODEL_ID, Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID ) as $model_id ) {
				if ( $this->hasModelMetadata( $model_id ) ) {
					$models[] = $this->getModelMetadata( $model_id );
				}
			}
			return $models;
		}

		/**
		 * Checks if metadata exists for a model.
		 *
		 * @param string $model_id Model id.
		 * @return bool
		 */
		public function hasModelMetadata( string $model_id ): bool {
			return Npcink_Cloud_WordPress_AI_Connector::is_model_available( $model_id );
		}

		/**
		 * Gets metadata for a model.
		 *
		 * @param string $model_id Model id.
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		public function getModelMetadata( string $model_id ): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			if ( ! $this->hasModelMetadata( $model_id ) ) {
				throw new \WordPress\AiClient\Common\Exception\InvalidArgumentException( 'Npcink Cloud model metadata not found.' );
			}

			if ( Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID === $model_id ) {
				return $this->image_model_metadata();
			}
			if ( Npcink_Cloud_WordPress_AI_Connector::VISION_MODEL_ID === $model_id ) {
				return $this->vision_model_metadata();
			}

			return $this->text_model_metadata();
		}

		/**
		 * Builds the fixed model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private function text_model_metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
				Npcink_Cloud_WordPress_AI_Connector::MODEL_ID,
				'Npcink Cloud Scene Text',
				array(
					\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
				),
				array(
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::inputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::text() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::text() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMimeType(),
						array( 'text/plain', 'application/json' )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputSchema() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::systemInstruction() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::candidateCount() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::maxTokens() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::temperature() ),
				)
			);
		}

		/**
		 * Builds the fixed vision text model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private function vision_model_metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
				Npcink_Cloud_WordPress_AI_Connector::VISION_MODEL_ID,
				'Npcink Cloud Scene Vision',
				array(
					\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration(),
				),
				array(
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::inputModalities(),
						array(
							array(
								\WordPress\AiClient\Messages\Enums\ModalityEnum::text(),
								\WordPress\AiClient\Messages\Enums\ModalityEnum::image(),
							),
						)
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::text() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMimeType(),
						array( 'text/plain' )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::systemInstruction() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::maxTokens() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::temperature() ),
				)
			);
		}

		/**
		 * Builds the fixed image generation model metadata.
		 *
		 * @return \WordPress\AiClient\Providers\Models\DTO\ModelMetadata
		 */
		private function image_model_metadata(): \WordPress\AiClient\Providers\Models\DTO\ModelMetadata {
			return new \WordPress\AiClient\Providers\Models\DTO\ModelMetadata(
				Npcink_Cloud_WordPress_AI_Connector::IMAGE_MODEL_ID,
				'Npcink Cloud Scene Image',
				array(
					\WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::imageGeneration(),
				),
				array(
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::inputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::text() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputModalities(),
						array( array( \WordPress\AiClient\Messages\Enums\ModalityEnum::image() ) )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputFileType(),
						array(
							\WordPress\AiClient\Files\Enums\FileTypeEnum::inline(),
							\WordPress\AiClient\Files\Enums\FileTypeEnum::remote(),
						)
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption(
						\WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMimeType(),
						array( 'image/png', 'image/jpeg', 'image/webp' )
					),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::candidateCount() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMediaAspectRatio() ),
					new \WordPress\AiClient\Providers\Models\DTO\SupportedOption( \WordPress\AiClient\Providers\Models\Enums\OptionEnum::outputMediaOrientation() ),
				)
			);
		}
	}
}
