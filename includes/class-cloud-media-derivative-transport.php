<?php
/**
 * Media derivative Cloud transport helper.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Media_Derivative_Transport' ) ) {
	/**
	 * Converts local media derivative request contracts into signed Cloud jobs.
	 */
	final class Npcink_Cloud_Media_Derivative_Transport {
		private const GOVERNANCE_REQUEST_CONTRACT_VERSION = 'media_governance_canary.v1';
		private const GOVERNANCE_MAX_ITEMS = 10;
		private const MAX_STATUS_ERROR_CODE_BYTES = 128;
		private const MAX_STATUS_ERROR_MESSAGE_BYTES = 500;
		private const MAX_STATUS_ERROR_STAGE_BYTES = 64;

		/**
		 * Dispatches a Cloud derivative job from an abilities-side request contract.
		 *
		 * @param array<string,mixed> $ability_response Ability response envelope.
		 * @param array<string,mixed> $source_artifact Short TTL source artifact descriptor supplied by the local host.
		 * @param string              $trace_id Optional trace id.
		 * @param string              $idempotency_key Optional idempotency key.
		 * @param array<string,mixed> $watermark_artifact Optional short TTL watermark artifact or upload descriptor.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function dispatch_from_ability_response( array $ability_response, array $source_artifact, string $trace_id = '', string $idempotency_key = '', array $watermark_artifact = array() ) {
			$client = self::verified_client();
			if ( is_wp_error( $client ) ) {
				return $client;
			}

			$contract = Npcink_Cloud_Media_Plan_Projection::extract_contract_data( $ability_response );
			$validated = Npcink_Cloud_Media_Plan_Projection::validate_request_contract( $contract );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}

			$source_descriptor_validation = Npcink_Cloud_Media_Source_Validation::validate_upload_descriptor_legacy_fields( $source_artifact );
			if ( is_wp_error( $source_descriptor_validation ) ) {
				return $source_descriptor_validation;
			}

			if ( Npcink_Cloud_Media_Source_Validation::descriptor_has_upload_file( $source_artifact ) && Npcink_Cloud_Media_Source_Validation::descriptor_has_artifact_id( $source_artifact ) ) {
				return new WP_Error(
					'cloud_media_derivative_source_mode_conflict',
					__( 'Source upload and source artifact id cannot be sent together.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$source_upload = Npcink_Cloud_Media_Source_Validation::normalize_upload_file_descriptor( $source_artifact, 'source_file' );
			if ( is_wp_error( $source_upload ) ) {
				return $source_upload;
			}

			$source_reference = array();
			if ( empty( $source_upload ) ) {
				$source_reference = Npcink_Cloud_Media_Source_Validation::normalize_required_artifact_reference( $source_artifact, 'source' );
				if ( is_wp_error( $source_reference ) ) {
					return $source_reference;
				}
			}

			$watermark_upload = array();
			$watermark_reference = array();
			if ( ! empty( $watermark_artifact ) ) {
				$watermark_descriptor_validation = Npcink_Cloud_Media_Source_Validation::validate_upload_descriptor_legacy_fields( $watermark_artifact );
				if ( is_wp_error( $watermark_descriptor_validation ) ) {
					return $watermark_descriptor_validation;
				}
				if ( Npcink_Cloud_Media_Source_Validation::descriptor_has_upload_file( $watermark_artifact ) && Npcink_Cloud_Media_Source_Validation::descriptor_has_artifact_id( $watermark_artifact ) ) {
					return new WP_Error(
						'cloud_media_derivative_watermark_source_conflict',
						__( 'Watermark upload and watermark artifact id cannot be sent together.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				$watermark_upload = Npcink_Cloud_Media_Source_Validation::normalize_upload_file_descriptor( $watermark_artifact, 'watermark_file' );
				if ( is_wp_error( $watermark_upload ) ) {
					return $watermark_upload;
				}
				if ( empty( $watermark_upload ) ) {
					$watermark_reference = Npcink_Cloud_Media_Source_Validation::normalize_required_artifact_reference( $watermark_artifact, 'watermark' );
					if ( is_wp_error( $watermark_reference ) ) {
						return $watermark_reference;
					}
				}
			}

			$media_params = self::build_media_job_params(
				$contract,
				! empty( $watermark_upload ) || ! empty( $watermark_reference )
			);
			if ( is_wp_error( $media_params ) ) {
				return $media_params;
			}
			$governance_context = self::build_governance_request_context(
				$contract,
				! empty( $watermark_upload ) || ! empty( $watermark_reference )
			);
			if ( is_wp_error( $governance_context ) ) {
				return $governance_context;
			}

			$base_idempotency_key = '' !== $idempotency_key ? $idempotency_key : 'media_derivative_' . wp_generate_uuid4();
			if ( ! empty( $source_upload ) ) {
				unset( $source_upload['field_name'] );
				$uploaded_source = $client->upload_media_artifact(
					$source_upload,
					$trace_id,
					self::media_idempotency_key( $base_idempotency_key, 'source_upload' )
				);
				if ( is_wp_error( $uploaded_source ) ) {
					return $uploaded_source;
				}
				$source_reference = $uploaded_source;
			}
			if ( ! empty( $watermark_upload ) ) {
				unset( $watermark_upload['field_name'] );
				$uploaded_watermark = $client->upload_media_artifact(
					$watermark_upload,
					$trace_id,
					self::media_idempotency_key( $base_idempotency_key, 'watermark_upload' )
				);
				if ( is_wp_error( $uploaded_watermark ) ) {
					return $uploaded_watermark;
				}
				$watermark_reference = $uploaded_watermark;
			}

			$media_payload = self::build_media_job_request(
				$media_params,
				$source_reference,
				$watermark_reference,
				$governance_context
			);
			if ( is_wp_error( $media_payload ) ) {
				return $media_payload;
			}

			$result = $client->create_media_job(
				$media_payload,
				$trace_id,
				self::media_idempotency_key( $base_idempotency_key, 'job' )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return self::public_cloud_projection( $result );
		}

		/**
		 * Reads and projects one Cloud media derivative run.
		 *
		 * @param string $run_id Cloud run id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function get_run_projection( string $run_id, string $trace_id = '' ) {
			$client = self::verified_client();
			if ( is_wp_error( $client ) ) {
				return $client;
			}

			$result = $client->get_run( $run_id, $trace_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return self::public_cloud_projection( $result );
		}

		/**
		 * Reads and projects one Cloud media derivative run result.
		 *
		 * @param string $run_id Cloud run id.
		 * @param string $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function get_run_result_projection( string $run_id, string $trace_id = '' ) {
			$client = self::verified_client();
			if ( is_wp_error( $client ) ) {
				return $client;
			}

			$result = $client->get_run_result( $run_id, $trace_id );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$projection = self::public_cloud_projection( $result );
			if ( is_wp_error( $projection ) ) {
				return $projection;
			}

			$canary = Npcink_Cloud_Media_Governance_Validation::governance_canary_from_cloud_result( $result );
			if ( is_wp_error( $canary ) ) {
				return $canary;
			}
				if ( is_array( $canary ) && 'skipped' === $canary['status'] ) {
					$projection['governance_canary'] = Npcink_Cloud_Media_Governance_Validation::governance_canary_projection( $canary );
					return $projection;
				}
				$auto_safe_skip = Npcink_Cloud_Media_Governance_Validation::auto_safe_skip_from_cloud_result( $result );
				if ( is_wp_error( $auto_safe_skip ) ) {
					return $auto_safe_skip;
				}
				if ( is_array( $auto_safe_skip ) ) {
					$projection['optimization'] = $auto_safe_skip;
					return $projection;
				}

			$artifact = Npcink_Cloud_Media_Artifact_Verification::artifact_from_cloud_result( $result );
			if ( is_wp_error( $artifact ) ) {
				return $artifact;
			}

			$projection['artifact'] = $artifact;
			if ( is_array( $canary ) ) {
				$projection['governance_canary'] = Npcink_Cloud_Media_Governance_Validation::governance_canary_projection( $canary );
			}
			if ( empty( $projection['warnings'] ) && is_array( $artifact['processing_warnings'] ?? null ) ) {
				$projection['warnings'] = $artifact['processing_warnings'];
			}

			return $projection;
		}

		/**
		 * Extracts a run id from the exact local public projection.
		 *
		 * @param array<string,mixed> $cloud_response Cloud response.
		 * @return string
		 */
		public static function run_id( array $cloud_response ): string {
			return sanitize_text_field( (string) ( $cloud_response['run_id'] ?? '' ) );
		}

		/**
		 * Returns a bounded status-only projection for local channel adapters.
		 *
		 * Status resources never carry result artifacts. A succeeded status still
		 * requires a separate result read before the artifact can be consumed.
		 *
		 * @param array<string,mixed> $cloud_response Cloud response.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function public_cloud_projection( array $cloud_response ) {
			if ( ! is_array( $cloud_response['data'] ?? null ) ) {
				return new WP_Error(
					'cloud_media_derivative_status_contract_invalid',
					__( 'Cloud media derivative statuses require a data envelope.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			$data     = $cloud_response['data'];
			$run_id   = sanitize_text_field( (string) ( $data['run_id'] ?? '' ) );
			$status   = sanitize_key( (string) ( $data['status'] ?? '' ) );
			if ( '' === $run_id || ! in_array( $status, array( 'queued', 'running', 'succeeded', 'failed', 'canceled' ), true ) ) {
				return new WP_Error(
					'cloud_media_derivative_status_contract_invalid',
					__( 'Cloud media derivative statuses require a run id and canonical lifecycle status.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}
			$warnings = Npcink_Cloud_Media_Plan_Projection::bounded_projection_warnings( $data['warnings'] ?? array() );
			$error    = self::bounded_status_error_projection( $cloud_response, $data );

			return array(
				'run_id'     => $run_id,
				'status'     => $status,
				'job_type'   => sanitize_key( (string) ( $data['job_type'] ?? '' ) ),
				'created_at' => sanitize_text_field( (string) ( $data['created_at'] ?? '' ) ),
				'updated_at' => sanitize_text_field( (string) ( $data['updated_at'] ?? '' ) ),
				'artifact'   => array(),
				'warnings'   => $warnings,
				'error'      => $error,
			);
		}

		/**
		 * Projects bounded lifecycle error facts without consuming result data.
		 *
		 * @param array<string,mixed> $cloud_response Cloud response envelope.
		 * @param array<string,mixed> $data Cloud response data.
		 * @return array<string,string>
		 */
		private static function bounded_status_error_projection( array $cloud_response, array $data ): array {
			$error_code = Npcink_Cloud_Media_Plan_Projection::bounded_projection_text(
				$data['error_code'] ?? ( $cloud_response['error_code'] ?? '' ),
				self::MAX_STATUS_ERROR_CODE_BYTES
			);
			$error_message = Npcink_Cloud_Media_Plan_Projection::bounded_projection_text(
				$data['error_message'] ?? ( $cloud_response['error_message'] ?? '' ),
				self::MAX_STATUS_ERROR_MESSAGE_BYTES
			);
			if ( '' === $error_message && '' !== $error_code ) {
				$error_message = Npcink_Cloud_Media_Plan_Projection::bounded_projection_text(
					$cloud_response['message'] ?? '',
					self::MAX_STATUS_ERROR_MESSAGE_BYTES
				);
			}
			$error_stage = sanitize_key(
				Npcink_Cloud_Media_Plan_Projection::bounded_projection_text(
					$data['error_stage'] ?? ( $cloud_response['error_stage'] ?? '' ),
					self::MAX_STATUS_ERROR_STAGE_BYTES
				)
			);

			if ( '' === $error_code && '' === $error_message && '' === $error_stage ) {
				return array();
			}

			return array(
				'error_code'    => $error_code,
				'error_message' => $error_message,
				'error_stage'   => $error_stage,
			);
		}

		/**
		 * Returns a verified runtime client or a fail-closed error.
		 *
		 * @return Npcink_Cloud_Runtime_Client|WP_Error
		 */
		public static function verified_client() {
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return new WP_Error(
					'cloud_runtime_unverified',
					__( 'Npcink Cloud credentials must verify before dispatching media derivative jobs.', 'npcink-cloud-addon' ),
					array( 'status' => 403 )
				);
			}

			return new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
		}

		/**
		 * Validates and builds artifact-independent image.transform.v1 parameters.
		 *
		 * @param array<string,mixed> $contract Ability contract data.
		 * @param bool                $has_watermark_source Whether the host supplied a watermark artifact/upload.
		 * @return array<string,mixed>|WP_Error
		 */
			private static function build_media_job_params( array $contract, bool $has_watermark_source ) {
			$job_payload = is_array( $contract['cloud_job_payload'] ?? null ) ? $contract['cloud_job_payload'] : array();
			$requested = is_array( $job_payload['requested_derivative'] ?? null ) ? $job_payload['requested_derivative'] : array();
			$watermark = is_array( $job_payload['watermark'] ?? null ) ? $job_payload['watermark'] : array();
				$watermark_type = sanitize_key( (string) ( $watermark['type'] ?? 'image' ) );
				$optimization_mode = sanitize_key( (string) ( $job_payload['optimization_mode'] ?? 'manual' ) );
				if ( ! in_array( $optimization_mode, array( 'manual', 'auto_safe' ), true ) ) {
					return new WP_Error( 'cloud_media_derivative_optimization_mode_invalid', __( 'Media derivative optimization mode is invalid.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
				}
				$auto_safe = 'auto_safe' === $optimization_mode;
				$optimization_profile = sanitize_text_field( (string) ( $job_payload['optimization_profile'] ?? '' ) );
				if ( $auto_safe && Npcink_Cloud_Media_Governance_Validation::AUTO_SAFE_PROFILE !== $optimization_profile ) {
					return new WP_Error( 'cloud_media_derivative_auto_safe_profile_invalid', __( 'Automatic safe optimization requires the current fixed policy version.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
				}
			if ( ! in_array( $watermark_type, array( 'image', 'text' ), true ) ) {
				$watermark_type = 'image';
			}

			if ( $has_watermark_source && empty( $watermark ) ) {
				return new WP_Error(
					'cloud_media_derivative_watermark_plan_missing',
					__( 'Watermark artifact transport requires a watermark plan in the ability response.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( 'text' === $watermark_type && ( $has_watermark_source || ! empty( $watermark['artifact_id'] ) ) ) {
				return new WP_Error(
					'cloud_media_derivative_watermark_source_conflict',
					__( 'Text watermark plans must not include a watermark upload or artifact id.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( 'image' === $watermark_type && ! empty( $watermark ) && ! $has_watermark_source ) {
				return new WP_Error(
					'cloud_media_derivative_watermark_source_missing',
					__( 'Watermark plans require a watermark upload or artifact id before Cloud dispatch.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

				$target_format = sanitize_key( (string) ( $job_payload['target_format'] ?? $requested['format'] ?? '' ) );
				if ( $auto_safe && 'webp' !== $target_format ) {
					return new WP_Error( 'cloud_media_derivative_auto_safe_format_invalid', __( 'Automatic safe optimization requires WebP output.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
				}
			if ( ! in_array( $target_format, array( 'webp', 'avif', 'jpeg', 'png', 'original' ), true ) ) {
				return new WP_Error(
					'cloud_media_derivative_target_format_missing',
					__( 'Media derivative request must include a bounded target format from the ability response.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$max_width = absint( $job_payload['max_width'] ?? $requested['max_width'] ?? 0 );
			if ( $max_width <= 0 ) {
				return new WP_Error(
					'cloud_media_derivative_max_width_missing',
					__( 'Media derivative request must include max_width from the ability response.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

				$quality = absint( $job_payload['quality'] ?? $requested['quality'] ?? 0 );
				if ( ! $auto_safe && $quality <= 0 ) {
				return new WP_Error(
					'cloud_media_derivative_quality_missing',
					__( 'Media derivative request must include quality from the ability response.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

				$params = array(
					'mode'              => $optimization_mode,
					'target_format'     => $target_format,
					'max_width'         => max( 1, min( 10000, $max_width ) ),
					'source_media_type' => 'image',
				);
				$resize_mode = sanitize_key( (string) ( $job_payload['resize_mode'] ?? $requested['resize_mode'] ?? ( $auto_safe ? 'preserve' : 'fit' ) ) );
				if ( ! in_array( $resize_mode, array( 'fit', 'preserve' ), true ) ) {
					return new WP_Error( 'cloud_media_derivative_resize_mode_invalid', __( 'Media derivative resize mode is invalid.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
				}
				$params['resize_mode'] = $resize_mode;
				if ( $auto_safe ) {
					if ( 1920 !== $max_width || array_key_exists( 'quality', $job_payload ) || array_key_exists( 'quality', $requested ) || ! empty( $watermark ) || $has_watermark_source || ! empty( $job_payload['crop'] ) ) {
						return new WP_Error( 'cloud_media_derivative_auto_safe_contract_invalid', __( 'Automatic safe optimization does not accept quality, crop, watermark, or custom width parameters.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
					}
					$params['optimization_profile'] = Npcink_Cloud_Media_Governance_Validation::AUTO_SAFE_PROFILE;
				} else {
					$params['quality'] = max( 1, min( 100, $quality ) );
				}
				if ( is_array( $job_payload['governance'] ?? null ) ) {
					$params['resize_mode'] = 'preserve';
			}
			$raw_source_media_type = (string) ( $job_payload['source_media_type'] ?? '' );
			$source_media_type = Npcink_Cloud_Media_Artifact_Verification::normalize_media_type( $raw_source_media_type, true );
			if ( '' !== trim( $raw_source_media_type ) && '' === $source_media_type ) {
				return new WP_Error(
					'cloud_media_derivative_source_media_type_invalid',
					__( 'Media derivative source media type must be a supported image type.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( is_array( $job_payload['crop'] ?? null ) && ! empty( $job_payload['crop'] ) ) {
				$params['crop'] = self::sanitize_crop_payload( $job_payload['crop'] );
			}
			if ( ! empty( $watermark ) ) {
				$params['watermark'] = self::sanitize_watermark_payload( $watermark );
				unset( $params['watermark']['artifact_id'] );
			}

			return $params;
		}

		/**
		 * Validates the optional governance canary request extension.
		 *
		 * @param array<string,mixed> $contract Ability contract data.
		 * @param bool                $has_watermark_source Whether a watermark source was supplied.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function build_governance_request_context( array $contract, bool $has_watermark_source ) {
			$job_payload = is_array( $contract['cloud_job_payload'] ?? null ) ? $contract['cloud_job_payload'] : array();
			if ( ! array_key_exists( 'governance', $job_payload ) ) {
				return array();
			}

			$governance = is_array( $job_payload['governance'] ) ? $job_payload['governance'] : array();
			$batch = is_array( $job_payload['batch_context'] ?? null ) ? $job_payload['batch_context'] : array();
			$requested = is_array( $job_payload['requested_derivative'] ?? null ) ? $job_payload['requested_derivative'] : array();
			$target_format = sanitize_key( (string) ( $job_payload['target_format'] ?? $requested['format'] ?? '' ) );
			if (
				! Npcink_Cloud_Media_Governance_Validation::has_exact_keys( $governance, array( 'contract_version', 'candidate_id', 'snapshot_id', 'source_sha256', 'evidence_revision', 'minimum_savings_basis_points', 'require_dimensions_unchanged', 'skip_if_not_beneficial', 'retain_originals' ) )
				|| ! Npcink_Cloud_Media_Governance_Validation::has_exact_keys( $batch, array( 'batch_id', 'item_index', 'item_count', 'chunk_size' ) )
			) {
				return new WP_Error(
					'cloud_media_governance_canary_contract_invalid',
					__( 'Media governance canaries require exact governance and batch context fields.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$candidate_id = sanitize_text_field( (string) $governance['candidate_id'] );
			$snapshot_id = sanitize_text_field( (string) $governance['snapshot_id'] );
			$evidence_revision = sanitize_text_field( (string) $governance['evidence_revision'] );
			$source_sha256 = Npcink_Cloud_Media_Artifact_Verification::normalize_sha256( (string) $governance['source_sha256'] );
			$item_index = absint( $batch['item_index'] );
			$item_count = absint( $batch['item_count'] );
			$chunk_size = absint( $batch['chunk_size'] );
			$batch_id = sanitize_text_field( (string) $batch['batch_id'] );
			if (
				self::GOVERNANCE_REQUEST_CONTRACT_VERSION !== $governance['contract_version']
				|| 1 !== preg_match( '/^mgc_[0-9a-f]{24}$/', $candidate_id )
				|| '' === $snapshot_id
				|| strlen( $snapshot_id ) > 160
				|| '' === $evidence_revision
				|| strlen( $evidence_revision ) > 160
				|| '' === $source_sha256
				|| Npcink_Cloud_Media_Governance_Validation::GOVERNANCE_MINIMUM_SAVINGS_BASIS_POINTS !== $governance['minimum_savings_basis_points']
				|| true !== $governance['require_dimensions_unchanged']
				|| true !== $governance['skip_if_not_beneficial']
				|| true !== $governance['retain_originals']
				|| 'webp' !== $target_format
				|| $has_watermark_source
				|| ! empty( $job_payload['watermark'] )
				|| ! empty( $job_payload['crop'] )
				|| '' === $batch_id
				|| strlen( $batch_id ) > 128
				|| $item_count < 1
				|| $item_count > self::GOVERNANCE_MAX_ITEMS
				|| $item_index < 1
				|| $item_index > $item_count
				|| $chunk_size < 1
				|| $chunk_size > self::GOVERNANCE_MAX_ITEMS
			) {
				return new WP_Error(
					'cloud_media_governance_canary_contract_invalid',
					__( 'Media governance canaries require WebP preserve previews, no crop or watermark, and at most ten items.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return array(
				'batch_context' => array(
					'batch_id'    => $batch_id,
					'item_index'  => $item_index,
					'item_count'  => $item_count,
					'chunk_size'  => $chunk_size,
				),
				'governance' => array(
					'contract_version'               => self::GOVERNANCE_REQUEST_CONTRACT_VERSION,
					'candidate_id'                   => $candidate_id,
					'snapshot_id'                    => $snapshot_id,
					'source_sha256'                  => 'sha256:' . $source_sha256,
					'evidence_revision'              => $evidence_revision,
					'minimum_savings_basis_points'   => Npcink_Cloud_Media_Governance_Validation::GOVERNANCE_MINIMUM_SAVINGS_BASIS_POINTS,
					'require_dimensions_unchanged'   => true,
					'skip_if_not_beneficial'         => true,
					'retain_originals'               => true,
				),
			);
		}

		/**
		 * Builds one exact artifact-referenced media_job_request.v1 body.
		 *
		 * @param array<string,mixed> $params Validated operation parameters.
		 * @param array<string,mixed> $source_reference Uploaded or supplied source artifact.
		 * @param array<string,mixed> $watermark_reference Optional watermark artifact.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function build_media_job_request( array $params, array $source_reference, array $watermark_reference, array $governance_context = array() ) {
			$source_artifact_id = sanitize_text_field( (string) ( $source_reference['artifact_id'] ?? '' ) );
			if ( 1 !== preg_match( '/^art_[0-9a-f]{32}$/', $source_artifact_id ) ) {
				return new WP_Error(
					'cloud_media_derivative_source_artifact_id_invalid',
					__( 'Media derivative jobs require a canonical source artifact id.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$payload = array(
				'request_contract_version' => 'media_job_request.v1',
				'operation'                => 'image.transform.v1',
				'source_artifact_id'       => $source_artifact_id,
			);
			if ( ! empty( $watermark_reference ) ) {
				$watermark_artifact_id = sanitize_text_field( (string) ( $watermark_reference['artifact_id'] ?? '' ) );
				if ( 1 !== preg_match( '/^art_[0-9a-f]{32}$/', $watermark_artifact_id ) ) {
					return new WP_Error(
						'cloud_media_derivative_watermark_artifact_id_invalid',
						__( 'Media derivative image watermarks require a canonical artifact id.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				$payload['watermark_artifact_id'] = $watermark_artifact_id;
			}
			$payload['params']             = $params;
			if ( ! empty( $governance_context ) ) {
				$payload['batch_context'] = $governance_context['batch_context'];
				$payload['governance']    = $governance_context['governance'];
			}
			$payload['result_ttl_minutes'] = 30;

			return $payload;
		}

		/**
		 * Sanitizes Cloud crop options for bounded aspect-ratio derivative processing.
		 *
		 * @param array<string,mixed> $crop Crop payload.
		 * @return array<string,string>
		 */
		private static function sanitize_crop_payload( array $crop ): array {
			$aspect_ratio = trim( sanitize_text_field( (string) ( $crop['aspect_ratio'] ?? '16:9' ) ) );
			if ( 1 !== preg_match( '/^([1-9][0-9]{0,2}):([1-9][0-9]{0,2})$/', $aspect_ratio, $matches ) ) {
				$aspect_ratio = '16:9';
			} else {
				$aspect_ratio = max( 1, min( 100, absint( $matches[1] ) ) ) . ':' . max( 1, min( 100, absint( $matches[2] ) ) );
			}

			$position = sanitize_key( (string) ( $crop['position'] ?? 'center' ) );
			if ( ! in_array( $position, array( 'top_left', 'top', 'top_right', 'left', 'center', 'right', 'bottom_left', 'bottom', 'bottom_right' ), true ) ) {
				$position = 'center';
			}

			return array(
				'type'         => 'aspect_ratio',
				'aspect_ratio' => $aspect_ratio,
				'position'     => $position,
			);
		}

		/**
		 * Sanitizes Cloud watermark options without creating a logo registry.
		 *
		 * @param array<string,mixed> $watermark Watermark payload.
		 * @return array<string,mixed>
		 */
		private static function sanitize_watermark_payload( array $watermark ): array {
			$type = sanitize_key( (string) ( $watermark['type'] ?? 'image' ) );
			if ( ! in_array( $type, array( 'image', 'text' ), true ) ) {
				$type = 'image';
			}

			if ( 'text' === $type ) {
				$text = sanitize_text_field( (string) ( $watermark['text'] ?? 'AI' ) );
				if ( '' === $text ) {
					$text = 'AI';
				}
				$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 64 ) : substr( $text, 0, 64 );

				return array(
					'type'       => 'text',
					'text'       => $text,
					'position'   => sanitize_key( (string) ( $watermark['position'] ?? 'bottom_right' ) ),
					'opacity'    => is_numeric( $watermark['opacity'] ?? null ) ? max( 0.0, min( 1.0, (float) $watermark['opacity'] ) ) : 0.75,
					'font_size'  => max( 8, min( 256, absint( $watermark['font_size'] ?? 48 ) ) ),
					'color'      => self::sanitize_watermark_color( $watermark['color'] ?? '#FFFFFF', '#FFFFFF' ),
					'background' => self::sanitize_watermark_color( $watermark['background'] ?? 'rgba(0,0,0,0.35)', 'rgba(0,0,0,0.35)' ),
					'margin_px'  => max( 0, min( 1000, absint( $watermark['margin_px'] ?? 24 ) ) ),
				);
			}

			$sanitized = array(
				'type'          => 'image',
				'position'      => sanitize_key( (string) ( $watermark['position'] ?? 'bottom_right' ) ),
				'opacity'       => is_numeric( $watermark['opacity'] ?? null ) ? max( 0.0, min( 1.0, (float) $watermark['opacity'] ) ) : 0.75,
				'scale_percent' => max( 1, min( 100, absint( $watermark['scale_percent'] ?? 18 ) ) ),
				'margin_px'     => max( 0, min( 1000, absint( $watermark['margin_px'] ?? 24 ) ) ),
			);
			$artifact_id = sanitize_text_field( (string) ( $watermark['artifact_id'] ?? '' ) );
			if ( '' !== $artifact_id ) {
				$sanitized['artifact_id'] = $artifact_id;
			}

			return $sanitized;
		}

		/**
		 * Sanitizes a text watermark color token for Cloud transport.
		 *
		 * @param mixed  $value Raw color.
		 * @param string $default Default color.
		 * @return string
		 */
		private static function sanitize_watermark_color( $value, string $default ): string {
			$color = trim( sanitize_text_field( (string) $value ) );
			if ( 'transparent' === strtolower( $color ) ) {
				return 'transparent';
			}
			if ( 1 === preg_match( '/^#[0-9A-Fa-f]{3}([0-9A-Fa-f]{3})?$/', $color ) ) {
				return strtoupper( $color );
			}
			if ( 1 === preg_match( '/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})(?:\s*,\s*(0|1|0?\.\d+))?\s*\)$/', $color, $matches ) ) {
				$r     = max( 0, min( 255, (int) $matches[1] ) );
				$g     = max( 0, min( 255, (int) $matches[2] ) );
				$b     = max( 0, min( 255, (int) $matches[3] ) );
				$alpha = isset( $matches[4] ) && '' !== $matches[4] ? max( 0, min( 1, (float) $matches[4] ) ) : null;

				return null === $alpha
					? sprintf( 'rgb(%d,%d,%d)', $r, $g, $b )
					: sprintf( 'rgba(%d,%d,%d,%s)', $r, $g, $b, rtrim( rtrim( sprintf( '%.3F', $alpha ), '0' ), '.' ) );
			}

			return $default;
		}

		/**
		 * Derives bounded resource-specific idempotency while every request uses a fresh nonce.
		 *
		 * @param string $base Caller operation identity.
		 * @param string $stage Media resource stage.
		 * @return string
		 */
		private static function media_idempotency_key( string $base, string $stage ): string {
			return 'media_' . sanitize_key( $stage ) . '_' . substr( hash( 'sha256', $base . '|' . $stage ), 0, 40 );
		}
	}
}
