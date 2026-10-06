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
		private const REQUEST_CONTRACT_VERSION = 'media_derivative_cloud_request.v1';
		private const PROPOSAL_CONTRACT_VERSION = 'media_derivative_cloud_proposal.v1';
		private const GOVERNANCE_REQUEST_CONTRACT_VERSION = 'media_governance_canary.v1';
		private const GOVERNANCE_RESULT_CONTRACT_VERSION = 'media_governance_canary_result.v1';
		private const GOVERNANCE_MINIMUM_SAVINGS_BASIS_POINTS = 1500;
		private const GOVERNANCE_MAX_ITEMS = 10;
			private const GOVERNANCE_MINIMUM_SOURCE_BYTES = 512000;
			private const AUTO_SAFE_PROFILE = 'auto_safe.v1';
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

			$contract = self::extract_contract_data( $ability_response );
			$validated = self::validate_request_contract( $contract );
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

			$canary = self::governance_canary_from_cloud_result( $result );
			if ( is_wp_error( $canary ) ) {
				return $canary;
			}
				if ( is_array( $canary ) && 'skipped' === $canary['status'] ) {
					$projection['governance_canary'] = self::governance_canary_projection( $canary );
					return $projection;
				}
				$auto_safe_skip = self::auto_safe_skip_from_cloud_result( $result );
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
				$projection['governance_canary'] = self::governance_canary_projection( $canary );
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
			$warnings = self::bounded_projection_warnings( $data['warnings'] ?? array() );
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
			$error_code = self::bounded_projection_text(
				$data['error_code'] ?? ( $cloud_response['error_code'] ?? '' ),
				self::MAX_STATUS_ERROR_CODE_BYTES
			);
			$error_message = self::bounded_projection_text(
				$data['error_message'] ?? ( $cloud_response['error_message'] ?? '' ),
				self::MAX_STATUS_ERROR_MESSAGE_BYTES
			);
			if ( '' === $error_message && '' !== $error_code ) {
				$error_message = self::bounded_projection_text(
					$cloud_response['message'] ?? '',
					self::MAX_STATUS_ERROR_MESSAGE_BYTES
				);
			}
			$error_stage = sanitize_key(
				self::bounded_projection_text(
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
		 * Projects a bounded warning list from one status response.
		 *
		 * @param mixed $warnings Cloud warnings.
		 * @return array<int,string>
		 */
		private static function bounded_projection_warnings( $warnings ): array {
			if ( ! is_array( $warnings ) ) {
				return array();
			}

			$projection = array();
			foreach ( array_slice( $warnings, 0, Npcink_Cloud_Media_Artifact_Verification::MAX_PROCESSING_WARNINGS ) as $warning ) {
				if ( ! is_string( $warning ) ) {
					continue;
				}
					$value = self::bounded_projection_text( $warning, Npcink_Cloud_Media_Artifact_Verification::MAX_PROCESSING_WARNING_BYTES );
				if ( '' !== $value ) {
					$projection[] = $value;
				}
			}

			return array_values( array_unique( $projection ) );
		}

		/**
		 * Sanitizes and byte-bounds one public projection string.
		 *
		 * @param mixed $value Raw value.
		 * @param int   $max_bytes Maximum byte length.
		 * @return string
		 */
		private static function bounded_projection_text( $value, int $max_bytes ): string {
			$text = sanitize_text_field( is_scalar( $value ) ? (string) $value : '' );
			if ( strlen( $text ) <= $max_bytes ) {
				return $text;
			}

			return substr( $text, 0, $max_bytes );
		}

		/**
		 * Returns a strictly validated governance canary wrapper when present.
		 *
		 * @param array<string,mixed> $cloud_result Cloud run result envelope.
		 * @return array<string,mixed>|null|WP_Error
		 */
			private static function governance_canary_from_cloud_result( array $cloud_result ) {
			$data = is_array( $cloud_result['data'] ?? null ) ? $cloud_result['data'] : array();
			$result = is_array( $data['result'] ?? null ) ? $data['result'] : array();

				return self::normalize_governance_canary_result( $result );
			}

			/**
			 * Returns a bounded auto-safe skip decision when Cloud intentionally publishes no artifact.
			 *
			 * @param array<string,mixed> $cloud_result Cloud run result envelope.
			 * @return array<string,mixed>|null|WP_Error
			 */
		private static function auto_safe_skip_from_cloud_result( array $cloud_result ) {
			$data = is_array( $cloud_result['data'] ?? null ) ? $cloud_result['data'] : array();
			$result = is_array( $data['result'] ?? null ) ? $data['result'] : array();
			if ( 'media_derivative_result.v3' !== (string) ( $result['contract_version'] ?? '' ) || 'skipped' !== (string) ( $result['status'] ?? '' ) ) {
				return null;
			}
			if (
				! self::has_exact_keys( $result, array( 'artifact_type', 'contract_version', 'status', 'workflow_metadata', 'artifact', 'decision' ) )
				|| 'media_derivative_artifact' !== (string) ( $result['artifact_type'] ?? '' )
				|| null !== ( $result['artifact'] ?? null )
				|| ! is_array( $result['workflow_metadata'] ?? null )
				|| ! is_array( $result['decision'] ?? null )
			) {
				return new WP_Error( 'cloud_media_derivative_skip_contract_invalid', __( 'Skipped automatic optimization results require the exact media_derivative_result.v3 decision envelope.', 'npcink-cloud-addon' ), array( 'status' => 502 ) );
			}

			$decision = $result['decision'];
			$facts    = is_array( $decision['transform_facts'] ?? null ) ? $decision['transform_facts'] : array();
			$fact_keys = array(
				'source_checksum', 'output_checksum', 'source_format', 'output_format', 'source_mime_type',
				'output_mime_type', 'source_width', 'source_height', 'output_width', 'output_height',
				'source_filesize_bytes', 'output_filesize_bytes', 'source_frame_count', 'output_frame_count',
				'source_has_alpha', 'output_has_alpha', 'alpha_preserved', 'decodable', 'crop_applied',
				'watermark_applied', 'resize_applied', 'encoding_mode', 'savings_basis_points',
				'optimization_profile', 'source_class', 'effective_quality', 'quality_metric', 'quality_score',
				'quality_threshold', 'color_profile_normalized', 'qualified', 'decision_reasons',
			);
			$reasons = self::bounded_projection_warnings( $decision['decision_reasons'] ?? array() );
			$fact_reasons = self::bounded_projection_warnings( $facts['decision_reasons'] ?? array() );
			$allowed_reasons = array( 'transparent_pixels_changed', 'quality_threshold_not_met', 'minimum_savings_not_met', 'output_not_smaller', 'color_profile_normalization_failed' );
			if (
				! self::has_exact_keys( $decision, array( 'qualified', 'decision_reasons', 'transform_facts' ) )
				|| false !== $decision['qualified']
				|| ! self::has_exact_keys( $facts, $fact_keys )
				|| false !== ( $facts['qualified'] ?? null )
				|| 'auto_safe.v1' !== (string) ( $facts['optimization_profile'] ?? '' )
				|| empty( $reasons )
				|| $reasons !== $fact_reasons
				|| array() !== array_diff( $reasons, $allowed_reasons )
				|| (int) ( $facts['source_filesize_bytes'] ?? 0 ) <= 0
				|| (int) ( $facts['output_filesize_bytes'] ?? 0 ) <= 0
				|| (int) ( $facts['savings_basis_points'] ?? -1 ) < 0
				|| (int) ( $facts['savings_basis_points'] ?? 10001 ) > 10000
			) {
				return new WP_Error( 'cloud_media_derivative_skip_decision_invalid', __( 'Skipped automatic optimization results require bounded decision facts.', 'npcink-cloud-addon' ), array( 'status' => 502 ) );
			}

			return array(
				'status'           => 'skipped',
				'qualified'        => false,
				'decision_reasons' => $reasons,
				'transform_facts'  => self::sanitize_projection_value( $facts ),
			);
		}

		/**
		 * Validates the additive governance result without changing ordinary results.
		 *
		 * @param array<string,mixed> $result Cloud result object.
		 * @return array<string,mixed>|null|WP_Error
		 */
		public static function normalize_governance_canary_result( array $result ) {
			if ( self::GOVERNANCE_RESULT_CONTRACT_VERSION !== (string) ( $result['contract_version'] ?? '' ) ) {
				return null;
			}

			$expected_keys = array( 'contract_version', 'artifact_type', 'status', 'candidate', 'source', 'validation', 'derivative', 'preview_only', 'retain_originals', 'write_posture', 'direct_wordpress_write' );
			if ( ! self::has_exact_keys( $result, $expected_keys ) ) {
				return self::governance_result_error( 'Media governance canary results require the exact wrapper fields.' );
			}
			if (
				'media_governance_canary_preview' !== (string) $result['artifact_type']
				|| ! in_array( $result['status'], array( 'ready', 'skipped' ), true )
				|| true !== $result['preview_only']
				|| true !== $result['retain_originals']
				|| false !== $result['direct_wordpress_write']
			) {
				return self::governance_result_error( 'Media governance canary posture is invalid.' );
			}

			$candidate = is_array( $result['candidate'] ) ? $result['candidate'] : array();
			$source = is_array( $result['source'] ) ? $result['source'] : array();
			$validation = is_array( $result['validation'] ) ? $result['validation'] : array();
			if (
				! self::has_exact_keys( $candidate, array( 'candidate_id', 'snapshot_id', 'source_sha256', 'evidence_revision' ) )
				|| ! self::has_exact_keys( $source, array( 'artifact_id', 'format', 'mime_type', 'width', 'height', 'filesize_bytes', 'checksum' ) )
				|| ! self::has_exact_keys( $validation, array( 'source_checksum_matches', 'dimensions_unchanged', 'output_smaller', 'source_bytes', 'output_bytes', 'savings_bytes', 'savings_basis_points', 'minimum_savings_basis_points', 'qualified', 'reasons' ) )
			) {
				return self::governance_result_error( 'Media governance canary evidence fields are invalid.' );
			}

			$candidate_id = sanitize_text_field( (string) $candidate['candidate_id'] );
			$snapshot_id = sanitize_text_field( (string) $candidate['snapshot_id'] );
			$evidence_revision = sanitize_text_field( (string) $candidate['evidence_revision'] );
			$source_sha256 = Npcink_Cloud_Media_Artifact_Verification::normalize_sha256( (string) $candidate['source_sha256'] );
			$source_checksum = Npcink_Cloud_Media_Artifact_Verification::normalize_sha256( (string) $source['checksum'] );
			if (
				1 !== preg_match( '/^mgc_[0-9a-f]{24}$/', $candidate_id )
				|| '' === $snapshot_id
				|| strlen( $snapshot_id ) > 160
				|| '' === $evidence_revision
				|| strlen( $evidence_revision ) > 160
				|| '' === $source_sha256
				|| $source_sha256 !== $source_checksum
				|| true !== $validation['source_checksum_matches']
			) {
				return self::governance_result_error( 'Media governance candidate and source evidence do not match.' );
			}

			$source_bytes = is_int( $validation['source_bytes'] ) ? $validation['source_bytes'] : -1;
			$output_bytes = is_int( $validation['output_bytes'] ) ? $validation['output_bytes'] : -1;
			$savings_bytes = is_int( $validation['savings_bytes'] ) ? $validation['savings_bytes'] : -1;
			$savings_basis_points = is_int( $validation['savings_basis_points'] ) ? $validation['savings_basis_points'] : -1;
			$reasons = self::bounded_projection_warnings( $validation['reasons'] );
			$source_format = sanitize_key( (string) $source['format'] );
			$source_mime_type = Npcink_Cloud_Media_Artifact_Verification::normalize_media_type( (string) $source['mime_type'] );
			$source_width = is_int( $source['width'] ) ? $source['width'] : 0;
			$source_height = is_int( $source['height'] ) ? $source['height'] : 0;
			$allowed_reasons = array( 'source_format_not_supported', 'output_not_smaller', 'below_minimum_source_bytes', 'minimum_savings_not_met', 'dimensions_changed' );
			if (
				! is_bool( $validation['dimensions_unchanged'] )
				|| ! is_bool( $validation['output_smaller'] )
				|| ! is_bool( $validation['qualified'] )
				|| ! is_array( $validation['reasons'] )
				|| count( $reasons ) !== count( $validation['reasons'] )
				|| array() !== array_diff( $reasons, $allowed_reasons )
				|| self::GOVERNANCE_MINIMUM_SAVINGS_BASIS_POINTS !== $validation['minimum_savings_basis_points']
				|| $source_bytes !== (int) $source['filesize_bytes']
				|| '' === $source_format
				|| '' === $source_mime_type
				|| $source_width <= 0
				|| $source_height <= 0
				|| $source_bytes <= 0
				|| $output_bytes <= 0
				|| $savings_bytes !== max( 0, $source_bytes - $output_bytes )
				|| $savings_basis_points !== max( 0, intdiv( $savings_bytes * 10000, $source_bytes ) )
			) {
				return self::governance_result_error( 'Media governance validation evidence is inconsistent.' );
			}

			$ready = 'ready' === $result['status'];
			if (
				$ready !== $validation['qualified']
				|| $validation['output_smaller'] !== ( $output_bytes < $source_bytes )
				|| ( $ready && $savings_basis_points < self::GOVERNANCE_MINIMUM_SAVINGS_BASIS_POINTS )
				|| ( $ready && $source_bytes <= self::GOVERNANCE_MINIMUM_SOURCE_BYTES )
				|| ( $ready && ! in_array( $source_format, array( 'jpg', 'jpeg', 'png' ), true ) )
				|| ( $ready && ! in_array( $source_mime_type, array( 'image/jpeg', 'image/png' ), true ) )
				|| ( $ready && ! empty( $reasons ) )
				|| ( ! $ready && empty( $reasons ) )
				|| ( $ready && 'artifact_only' !== $result['write_posture'] )
				|| ( ! $ready && 'no_artifact' !== $result['write_posture'] )
				|| ( $ready && ! is_array( $result['derivative'] ) )
				|| ( ! $ready && null !== $result['derivative'] )
			) {
				return self::governance_result_error( 'Media governance canary qualification state is inconsistent.' );
			}
			if ( $ready ) {
				$derivative = $result['derivative'];
					$expected_derivative_keys = array( 'artifact_type', 'contract_version', 'status', 'workflow_metadata', 'artifact' );
				if (
					! self::has_exact_keys( $derivative, $expected_derivative_keys )
					|| 'media_derivative_artifact' !== (string) $derivative['artifact_type']
						|| 'media_derivative_result.v3' !== (string) $derivative['contract_version']
						|| 'qualified' !== (string) $derivative['status']
					|| ! is_array( $derivative['workflow_metadata'] )
					|| ! is_array( $derivative['artifact'] )
				) {
					return self::governance_result_error( 'Qualified media governance canaries require the exact derivative result envelope.' );
				}
				$artifact = Npcink_Cloud_Media_Artifact_Verification::normalize_artifact_descriptor( $derivative['artifact'], 'derivative' );
				if ( is_wp_error( $artifact ) ) {
					return $artifact;
				}
				if (
					'webp' !== $artifact['format']
					|| 'image/webp' !== $artifact['mime_type']
					|| $source_width !== $artifact['width']
					|| $source_height !== $artifact['height']
					|| $output_bytes !== $artifact['filesize_bytes']
					|| true !== $validation['dimensions_unchanged']
				) {
					return self::governance_result_error( 'Qualified media governance derivative facts do not match the validation evidence.' );
				}
			}

			$result['candidate']['source_sha256'] = 'sha256:' . $source_sha256;
			$result['source']['checksum'] = 'sha256:' . $source_checksum;
			$result['validation']['reasons'] = $reasons;

			return $result;
		}

		/**
		 * Builds the bounded public canary evidence projection.
		 *
		 * @param array<string,mixed> $canary Validated canary result.
		 * @return array<string,mixed>
		 */
		private static function governance_canary_projection( array $canary ): array {
			return array(
				'contract_version' => self::GOVERNANCE_RESULT_CONTRACT_VERSION,
				'status'           => $canary['status'],
				'candidate'        => $canary['candidate'],
				'source'           => $canary['source'],
				'validation'       => $canary['validation'],
				'preview_only'     => true,
				'retain_originals' => true,
				'write_posture'    => $canary['write_posture'],
			);
		}

		/**
		 * Returns one fail-closed governance result error.
		 *
		 * @param string $message Error message.
		 * @return WP_Error
		 */
		private static function governance_result_error( string $message ): WP_Error {
			unset( $message );
			return new WP_Error(
				'cloud_media_governance_canary_result_invalid',
				__( 'Cloud media governance canary evidence failed strict validation.', 'npcink-cloud-addon' ),
				array( 'status' => 502 )
			);
		}

		/**
		 * Builds a local-host proposal payload from a Cloud result artifact.
		 *
		 * This method does not store a proposal and does not mutate WordPress. The
		 * returned payload is intended for Core proposal/preflight intake.
		 *
		 * @param array<string,mixed> $ability_response Ability response envelope.
		 * @param array<string,mixed> $cloud_result Cloud run result envelope.
		 * @param array<string,mixed> $derivative_artifact Downloaded or downloadable Cloud derivative artifact descriptor.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function build_local_proposal_payload( array $ability_response, array $cloud_result, array $derivative_artifact ) {
			$contract = self::extract_contract_data( $ability_response );
			$validated = self::validate_request_contract( $contract );
			if ( is_wp_error( $validated ) ) {
				return $validated;
			}

			$cloud_artifact = Npcink_Cloud_Media_Artifact_Verification::normalize_artifact_descriptor( $derivative_artifact, 'derivative' );
			if ( is_wp_error( $cloud_artifact ) ) {
				return $cloud_artifact;
			}

			$cloud_data = self::extract_cloud_data( $cloud_result );
			if ( is_wp_error( $cloud_data ) ) {
				return $cloud_data;
			}
			$binding_valid = self::validate_derivative_artifact_binding( $cloud_data, $cloud_artifact );
			if ( is_wp_error( $binding_valid ) ) {
				return $binding_valid;
			}
			$artifact = Npcink_Cloud_Media_Artifact_Verification::local_proposal_artifact( $cloud_artifact );

			$original = self::normalize_media_metrics( $contract['cloud_job_payload']['source_asset'] ?? array() );
			$derivative = self::normalize_media_metrics(
				array_merge(
					is_array( $cloud_data['artifact'] ?? null ) ? $cloud_data['artifact'] : array(),
					$cloud_artifact
				)
			);
			$warnings = self::sanitize_string_list( $contract['cloud_job_payload']['warnings'] ?? array() );
			$warnings = array_merge( $warnings, self::sanitize_string_list( $cloud_data['warnings'] ?? array() ) );
			$warnings = self::append_metric_warnings( $warnings, $original, $derivative );

			return array(
				'contract_version'  => self::PROPOSAL_CONTRACT_VERSION,
				'proposal_kind'     => 'media_derivative_cloud_artifact',
				'attachment_id'     => absint( $contract['attachment_id'] ?? 0 ),
				'final_write_owner' => 'local_wordpress_host',
				'approval_required' => true,
				'adoption_allowed'  => true,
				'default_action'    => 'preview_only',
				'actions'           => array(
					'preview' => true,
					'record'  => 'requires_local_host_approval',
					'replace' => 'requires_local_host_approval',
					'rollback' => 'requires_local_host_approval',
				),
				'original'          => $original,
				'derivative'        => $derivative,
				'savings_estimate'  => self::build_savings_estimate( $original, $derivative ),
				'warnings'          => array_values( array_unique( $warnings ) ),
				'artifact'          => $artifact,
				'cloud_result'      => self::sanitize_cloud_result_summary( $cloud_data ),
				'local_adoption'    => array(
					'owner'                         => 'local_wordpress_host',
					'final_write_owner'             => 'local_wordpress_host',
					'approval_required'             => true,
					'wordpress_write_included'      => false,
					'replace_original_default'      => false,
					'attachment_metadata_write_included' => false,
				),
			);
		}

		/**
		 * Builds a Core from-plan media optimization payload for local adapters.
		 *
		 * This does not create, approve, preflight, or execute a proposal.
		 *
		 * @param array<string,mixed> $ability_response Ability response envelope.
		 * @param array<string,mixed> $cloud_result Cloud run result envelope.
		 * @param array<string,mixed> $derivative_artifact Cloud derivative artifact descriptor.
		 * @param array<string,mixed> $media_details_input Reviewed media metadata input.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function build_media_optimization_payload( array $ability_response, array $cloud_result, array $derivative_artifact, array $media_details_input ) {
			if ( empty( $derivative_artifact ) ) {
				$derivative_artifact = Npcink_Cloud_Media_Artifact_Verification::artifact_from_cloud_result( $cloud_result );
			}

			$proposal_payload = self::build_local_proposal_payload( $ability_response, $cloud_result, $derivative_artifact );
			if ( is_wp_error( $proposal_payload ) ) {
				return $proposal_payload;
			}

			$ability_data = is_array( $ability_response['data'] ?? null ) ? $ability_response['data'] : array();
			if ( is_array( $ability_data['content_reference_repairs_preview'] ?? null ) && ! is_array( $proposal_payload['content_reference_repairs_preview'] ?? null ) ) {
				$proposal_payload['content_reference_repairs_preview'] = $ability_data['content_reference_repairs_preview'];
			}

			$optimization_plan = self::media_optimization_plan_from_derivative_payload( $proposal_payload, $media_details_input );
			$response_payload  = array(
				'contract_version'        => 'media_derivative_cloud_optimization_payload.v1',
				'proposal_payload'        => $proposal_payload,
				'media_optimization_plan' => $optimization_plan,
				'core_proposal_required'  => true,
				'commit_execution'        => false,
				'proposal_ready'          => true === (bool) ( $optimization_plan['proposal_ready'] ?? false ),
				'preferred_core_route'    => 'POST /proposals/from-plan',
				'required_plan_ability_id' => 'npcink-abilities-toolkit/build-media-optimization-plan',
			);

			if ( is_array( $optimization_plan['write_actions'] ?? null ) && count( (array) $optimization_plan['write_actions'] ) >= 2 ) {
				$response_payload['from_plan_request'] = array(
					'plan_ability_id' => 'npcink-abilities-toolkit/build-media-optimization-plan',
					'plan'            => $optimization_plan,
				);
				$response_payload['next_step'] = 'POST /proposals/from-plan with from_plan_request for one Core batch proposal.';
			} else {
				$response_payload['next_step'] = 'Provide reviewed media_details_input, then build the media derivative optimization payload again and submit the returned from_plan_request to Core; do not split one optimize-media intent into two proposals.';
			}

			return $response_payload;
		}

		/**
		 * Builds the Core from-plan media optimization payload shape.
		 *
		 * @param array<string,mixed> $proposal_payload Cloud derivative proposal payload.
		 * @param array<string,mixed> $media_details_input Reviewed metadata action input.
		 * @return array<string,mixed>
		 */
		private static function media_optimization_plan_from_derivative_payload( array $proposal_payload, array $media_details_input ): array {
			$attachment_id  = absint( $proposal_payload['attachment_id'] ?? 0 );
			$artifact       = is_array( $proposal_payload['artifact'] ?? null ) ? $proposal_payload['artifact'] : array();
			$original       = is_array( $proposal_payload['original'] ?? null ) ? $proposal_payload['original'] : array();
			$derivative     = is_array( $proposal_payload['derivative'] ?? null ) ? $proposal_payload['derivative'] : array();
			$metadata_input = self::sanitize_media_details_plan_input( $attachment_id, $media_details_input );

			$metadata_preview = array(
				'before' => array(),
				'after'  => array_diff_key( $metadata_input, array( 'attachment_id' => true ) ),
			);
			$derivative_preview = array(
				'before' => array(
					'mime_type'      => sanitize_text_field( (string) ( $original['mime_type'] ?? '' ) ),
					'width'          => absint( $original['width'] ?? 0 ),
					'height'         => absint( $original['height'] ?? 0 ),
					'filesize_bytes' => absint( $original['filesize_bytes'] ?? 0 ),
				),
				'after'  => array(
					'artifact_id'    => sanitize_text_field( (string) ( $artifact['artifact_id'] ?? '' ) ),
					'mime_type'      => sanitize_text_field( (string) ( $derivative['mime_type'] ?? ( $artifact['mime_type'] ?? '' ) ) ),
					'width'          => absint( $derivative['width'] ?? ( $artifact['width'] ?? 0 ) ),
					'height'         => absint( $derivative['height'] ?? ( $artifact['height'] ?? 0 ) ),
					'filesize_bytes' => absint( $derivative['filesize_bytes'] ?? ( $artifact['filesize_bytes'] ?? 0 ) ),
				),
			);
			$content_reference_repairs_preview = array();
			if ( is_array( $proposal_payload['content_reference_repairs_preview'] ?? null ) ) {
				$content_reference_repairs_preview = $proposal_payload['content_reference_repairs_preview'];
			} elseif ( is_array( $proposal_payload['derivative_preview']['content_reference_repairs'] ?? null ) ) {
				$content_reference_repairs_preview = $proposal_payload['derivative_preview']['content_reference_repairs'];
			} elseif ( is_array( $derivative['content_reference_repairs'] ?? null ) ) {
				$content_reference_repairs_preview = $derivative['content_reference_repairs'];
			}
			if ( ! empty( $content_reference_repairs_preview ) ) {
				$derivative_preview['content_reference_repairs'] = $content_reference_repairs_preview;
			}

			$plan = array(
				'artifact_type'      => 'media_optimization_plan',
				'version'            => 1,
				'batch_id'           => 'media_optimization_' . $attachment_id . '_' . gmdate( 'Ymd_His' ),
				'attachment_id'      => $attachment_id,
				'optimization_goal'  => 'image_seo_and_derivative_adoption',
				'requires_approval'  => true,
				'dry_run'            => true,
				'commit_execution'   => false,
				'proposal_mode'      => 'batch',
				'batch_approval'     => true,
				'action_count'       => 0,
				'action_ids'         => array(),
				'target_ability_ids' => array(),
				'metadata_preview'   => $metadata_preview,
				'derivative_preview' => $derivative_preview,
				'content_reference_repairs_preview' => $content_reference_repairs_preview,
				'preview'            => array(),
				'write_actions'      => array(),
				'requires_input'     => array(),
				'proposal_ready'     => false,
				'risk'               => array(
					'level'  => 'medium',
					'reason' => 'One attachment metadata update and one reviewed Cloud derivative adoption share one Core approval.',
				),
			);

			if ( $attachment_id <= 0 || empty( $artifact['artifact_id'] ) ) {
				$plan['requires_input'][] = 'valid_derivative_proposal_payload';
				return $plan;
			}

			if ( count( $metadata_input ) <= 1 ) {
				$plan['requires_input'][] = 'media_details_input';
				return $plan;
			}

			$derivative_input = array(
				'attachment_id'       => $attachment_id,
				'derivative_artifact' => $artifact,
			);
			$current_mime    = sanitize_text_field( (string) ( $original['mime_type'] ?? '' ) );
			$derivative_mime = sanitize_text_field( (string) ( $derivative['mime_type'] ?? ( $artifact['mime_type'] ?? '' ) ) );
			if ( '' !== $current_mime ) {
				$derivative_input['expected_current_mime_type'] = $current_mime;
			}
			if ( '' !== $derivative_mime ) {
				$derivative_input['expected_derivative_mime_type'] = $derivative_mime;
			}
			if ( ! empty( $content_reference_repairs_preview ) ) {
				$derivative_input['expected_content_reference_post_ids'] = array_slice(
					array_values(
						array_unique(
							array_filter(
								array_map(
									static function ( $repair ) {
										return absint( is_array( $repair ) ? ( $repair['post_id'] ?? 0 ) : 0 );
									},
									(array) ( $content_reference_repairs_preview['repairs'] ?? array() )
								)
							)
						)
					),
					0,
					50
				);
				$derivative_input['expected_content_reference_post_count'] = absint( $content_reference_repairs_preview['post_count'] ?? 0 );
				$derivative_input['expected_content_reference_replacement_count'] = absint( $content_reference_repairs_preview['replacement_count'] ?? 0 );
			}

			$write_actions = array(
				self::plan_action( 'update_media_details_' . $attachment_id, 'npcink-abilities-toolkit/update-media-details', $metadata_input, 'medium', 'Apply reviewed media SEO and source metadata as part of one media optimization approval.' ),
				self::plan_action( 'adopt_cloud_media_derivative_' . $attachment_id, 'npcink-abilities-toolkit/adopt-cloud-media-derivative', $derivative_input, 'medium', 'Adopt the reviewed Cloud derivative artifact as the attachment main file after Core approval.' ),
			);
			$action_ids = array_values(
				array_map(
					static function ( $action ) {
						return is_array( $action ) ? sanitize_key( (string) ( $action['action_id'] ?? '' ) ) : '';
					},
					$write_actions
				)
			);
			$target_ability_ids = array_values(
				array_unique(
					array_filter(
						array_map(
							static function ( $action ) {
								return is_array( $action ) ? sanitize_text_field( (string) ( $action['target_ability_id'] ?? '' ) ) : '';
							},
							$write_actions
						)
					)
				)
			);
			$plan['write_actions']      = $write_actions;
			$plan['action_count']       = count( $plan['write_actions'] );
			$plan['action_ids']         = $action_ids;
			$plan['target_ability_ids'] = $target_ability_ids;
			$plan['proposal_ready']     = true;
			$plan['preview'][]          = array(
				'attachment_id'    => $attachment_id,
				'before'           => array(
					'metadata'   => array(),
					'derivative' => $derivative_preview['before'],
				),
				'after_suggestion' => array(
					'metadata'   => $metadata_preview['after'],
					'derivative' => $derivative_preview['after'],
				),
				'action_ids'         => $action_ids,
				'target_ability_ids' => $target_ability_ids,
			);

			return $plan;
		}

		/**
		 * Sanitizes update-media-details input for a generated plan.
		 *
		 * @param int                 $attachment_id Attachment id.
		 * @param array<string,mixed> $input Raw metadata input.
		 * @return array<string,mixed>
		 */
		private static function sanitize_media_details_plan_input( int $attachment_id, array $input ): array {
			$output = array( 'attachment_id' => $attachment_id );
			foreach ( array( 'title', 'alt', 'caption', 'description', 'source_page_url', 'photographer_name', 'attribution_text', 'copyright_notice' ) as $field ) {
				if ( array_key_exists( $field, $input ) && '' !== (string) $input[ $field ] ) {
					$output[ $field ] = 'source_page_url' === $field ? esc_url_raw( (string) $input[ $field ] ) : sanitize_text_field( (string) $input[ $field ] );
				}
			}
			if ( array_key_exists( 'source_type', $input ) ) {
				$source_type = sanitize_key( (string) $input['source_type'] );
				if ( in_array( $source_type, array( 'owned', 'ai_generated', 'stock', 'external', 'test' ), true ) ) {
					$output['source_type'] = $source_type;
				}
			}
			return $output;
		}

		/**
		 * Builds one local-host plan action.
		 *
		 * @param string              $action_id Action id.
		 * @param string              $ability_id Target ability id.
		 * @param array<string,mixed> $input Target ability input.
		 * @param string              $risk Risk.
		 * @param string              $reason Reason.
		 * @return array<string,mixed>
		 */
		private static function plan_action( string $action_id, string $ability_id, array $input, string $risk, string $reason ): array {
			$input['dry_run'] = true;
			$input['commit']  = false;
			return array(
				'action_id'         => sanitize_key( $action_id ),
				'target_ability_id' => sanitize_text_field( $ability_id ),
				'input'             => $input,
				'requires_approval' => true,
				'commit_execution'  => false,
				'required_scopes'   => array( 'media.write' ),
				'risk'              => sanitize_key( $risk ),
				'reason'            => sanitize_text_field( $reason ),
				'requires_input'    => array(),
				'proposal_ready'    => true,
			);
		}

		/**
		 * Sanitizes bounded Cloud projection values recursively.
		 *
		 * @param mixed $value Raw value.
		 * @return mixed
		 */
		private static function sanitize_projection_value( $value ) {
			if ( is_array( $value ) ) {
				$clean = array();
				foreach ( $value as $key => $item ) {
					$clean[ is_int( $key ) ? $key : sanitize_key( (string) $key ) ] = self::sanitize_projection_value( $item );
				}
				return $clean;
			}

			if ( is_bool( $value ) || is_int( $value ) || is_float( $value ) || null === $value ) {
				return $value;
			}

			return sanitize_text_field( (string) $value );
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
		 * Extracts ability response data.
		 *
		 * @param array<string,mixed> $ability_response Ability response envelope.
		 * @return array<string,mixed>
		 */
		private static function extract_contract_data( array $ability_response ): array {
			if ( is_array( $ability_response['data'] ?? null ) ) {
				return $ability_response['data'];
			}

			return $ability_response;
		}

		/**
		 * Extracts Cloud response data.
		 *
		 * @param array<string,mixed> $cloud_result Cloud response envelope.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function extract_cloud_data( array $cloud_result ) {
			$expected_keys = array( 'run_id', 'status', 'job_type', 'created_at', 'updated_at', 'artifact', 'warnings', 'error' );
			$actual_keys = array_keys( $cloud_result );
			$canary_projection = $cloud_result['governance_canary'] ?? null;
			if ( is_array( $canary_projection ) ) {
				$actual_keys = array_values( array_diff( $actual_keys, array( 'governance_canary' ) ) );
			}
			if (
				count( $expected_keys ) !== count( $actual_keys )
				|| array() !== array_diff( $expected_keys, $actual_keys )
				|| array() !== array_diff( $actual_keys, $expected_keys )
				|| ! is_array( $cloud_result['artifact'] ?? null )
			) {
				return new WP_Error(
					'cloud_media_derivative_projection_invalid',
					__( 'Local media derivative proposal building requires the exact Addon run-result projection.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			$artifact = Npcink_Cloud_Media_Artifact_Verification::normalize_artifact_descriptor( $cloud_result['artifact'], 'derivative' );
			if ( is_wp_error( $artifact ) ) {
				return $artifact;
			}
			$data = $cloud_result;
			unset( $data['governance_canary'] );
			$data['artifact'] = $artifact;

			return $data;
		}

		/**
		 * Validates the local ability contract before any Cloud dispatch.
		 *
		 * @param array<string,mixed> $contract Ability contract data.
		 * @return true|WP_Error
		 */
		private static function validate_request_contract( array $contract ) {
			if ( self::REQUEST_CONTRACT_VERSION !== (string) ( $contract['request_contract_version'] ?? '' ) ) {
				return new WP_Error(
					'cloud_media_derivative_contract_invalid',
					__( 'Media derivative request contract version is invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( empty( $contract['readonly'] ) || empty( $contract['proposal_only'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_contract_not_readonly',
					__( 'Media derivative request must be read-only and proposal-only.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( 'local_wordpress_host' !== (string) ( $contract['local_adoption']['final_write_owner'] ?? '' ) ) {
				return new WP_Error(
					'cloud_media_derivative_write_owner_invalid',
					__( 'Media derivative final write owner must remain the local WordPress host.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( ! empty( $contract['local_adoption']['wordpress_write_included'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_wordpress_write_present',
					__( 'Media derivative request must not include WordPress writes.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( self::contains_forbidden_secret_fields( $contract ) ) {
				return new WP_Error(
					'cloud_media_derivative_credentials_present',
					__( 'Media derivative ability payload must not include credentials or signed headers.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$job_payload = is_array( $contract['cloud_job_payload'] ?? null ) ? $contract['cloud_job_payload'] : array();
			if ( 'generate_optimized_media_derivative' !== (string) ( $job_payload['job_type'] ?? '' ) ) {
				return new WP_Error(
					'cloud_media_derivative_job_type_invalid',
					__( 'Cloud media derivative job type is invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( ! empty( $job_payload['requested_derivative']['replace_original'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_replace_original_requested',
					__( 'Media derivative jobs must not request original file replacement.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return true;
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
				if ( $auto_safe && self::AUTO_SAFE_PROFILE !== $optimization_profile ) {
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
					$params['optimization_profile'] = self::AUTO_SAFE_PROFILE;
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
				! self::has_exact_keys( $governance, array( 'contract_version', 'candidate_id', 'snapshot_id', 'source_sha256', 'evidence_revision', 'minimum_savings_basis_points', 'require_dimensions_unchanged', 'skip_if_not_beneficial', 'retain_originals' ) )
				|| ! self::has_exact_keys( $batch, array( 'batch_id', 'item_index', 'item_count', 'chunk_size' ) )
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
				|| self::GOVERNANCE_MINIMUM_SAVINGS_BASIS_POINTS !== $governance['minimum_savings_basis_points']
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
					'minimum_savings_basis_points'   => self::GOVERNANCE_MINIMUM_SAVINGS_BASIS_POINTS,
					'require_dimensions_unchanged'   => true,
					'skip_if_not_beneficial'         => true,
					'retain_originals'               => true,
				),
			);
		}

		/**
		 * Returns whether an array has exactly the expected string keys.
		 *
		 * @param array<string,mixed> $value Array under validation.
		 * @param array<int,string>   $expected_keys Expected keys.
		 * @return bool
		 */
		private static function has_exact_keys( array $value, array $expected_keys ): bool {
			return count( $expected_keys ) === count( $value )
				&& array() === array_diff( $expected_keys, array_keys( $value ) )
				&& array() === array_diff( array_keys( $value ), $expected_keys );
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

		/**
		 * Recursively detects credential or signed-header fields.
		 *
		 * @param mixed $value Payload value.
		 * @return bool
		 */
		private static function contains_forbidden_secret_fields( $value ): bool {
			if ( ! is_array( $value ) ) {
				return false;
			}

			$forbidden = array(
				'api_key',
				'authorization',
				'credentials',
				'key_id',
				'secret',
				'signed_headers',
				'signature',
				'token',
				'x_magick_key_id',
				'x_magick_signature',
				'x_magick_site_id',
			);

			foreach ( $value as $key => $item ) {
				$normalized_key = strtolower( str_replace( '-', '_', (string) $key ) );
				if ( in_array( $normalized_key, $forbidden, true ) ) {
					return true;
				}
				if ( self::contains_forbidden_secret_fields( $item ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Normalizes media metrics for proposal display.
		 *
		 * @param mixed $metrics Raw metrics.
		 * @return array<string,mixed>
		 */
		private static function normalize_media_metrics( $metrics ): array {
			$metrics = is_array( $metrics ) ? $metrics : array();
			$raw_mime_type = (string) ( $metrics['mime_type'] ?? '' );

			return array(
				'width'          => absint( $metrics['width'] ?? 0 ),
				'height'         => absint( $metrics['height'] ?? 0 ),
				'filesize_bytes' => absint( $metrics['filesize_bytes'] ?? $metrics['size_bytes'] ?? 0 ),
				'mime_type'      => Npcink_Cloud_Media_Artifact_Verification::normalize_media_type( $raw_mime_type ),
			);
		}

		/**
		 * Validates that the adopted artifact matches the Cloud result summary.
		 *
		 * @param array<string,mixed> $cloud_data Cloud result data.
		 * @param array<string,mixed> $artifact Normalized derivative artifact.
		 * @return true|WP_Error
		 */
		private static function validate_derivative_artifact_binding( array $cloud_data, array $artifact ) {
			$result_artifact = is_array( $cloud_data['artifact'] ?? null ) ? $cloud_data['artifact'] : array();
			foreach ( array( 'artifact_id', 'expires_at', 'mime_type', 'format', 'width', 'height', 'filesize_bytes', 'checksum' ) as $field ) {
				if ( ! array_key_exists( $field, $result_artifact ) || $result_artifact[ $field ] !== ( $artifact[ $field ] ?? null ) ) {
					return new WP_Error(
						'cloud_media_derivative_artifact_binding_mismatch',
						__( 'Derivative artifact facts do not match the Cloud result.', 'npcink-cloud-addon' ),
						array( 'status' => 409 )
					);
				}
			}
			if ( Npcink_Cloud_Media_Artifact_Verification::normalize_sha256( (string) $result_artifact['checksum'] ) !== Npcink_Cloud_Media_Artifact_Verification::normalize_sha256( (string) $artifact['checksum'] ) ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_checksum_mismatch',
					__( 'Derivative artifact checksum does not match the Cloud result.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			return true;
		}

		/**
		 * Adds explicit warnings when proposal comparison metrics are incomplete.
		 *
		 * @param array<int,string>    $warnings Existing warnings.
		 * @param array<string,mixed> $original Original metrics.
		 * @param array<string,mixed> $derivative Derivative metrics.
		 * @return array<int,string>
		 */
		private static function append_metric_warnings( array $warnings, array $original, array $derivative ): array {
			if ( ! self::has_complete_media_metrics( $original ) ) {
				$warnings[] = __( 'Original media metrics are incomplete.', 'npcink-cloud-addon' );
			}
			if ( ! self::has_complete_media_metrics( $derivative ) ) {
				$warnings[] = __( 'Derivative media metrics are incomplete.', 'npcink-cloud-addon' );
			}

			return $warnings;
		}

		/**
		 * Returns whether media comparison metrics have the fields needed by UI.
		 *
		 * @param array<string,mixed> $metrics Normalized metrics.
		 * @return bool
		 */
		private static function has_complete_media_metrics( array $metrics ): bool {
			return absint( $metrics['width'] ?? 0 ) > 0
				&& absint( $metrics['height'] ?? 0 ) > 0
				&& absint( $metrics['filesize_bytes'] ?? 0 ) > 0
				&& '' !== (string) ( $metrics['mime_type'] ?? '' );
		}

		/**
		 * Builds a conservative byte savings estimate.
		 *
		 * @param array<string,mixed> $original Original metrics.
		 * @param array<string,mixed> $derivative Derivative metrics.
		 * @return array<string,mixed>
		 */
		private static function build_savings_estimate( array $original, array $derivative ): array {
			$original_bytes = absint( $original['filesize_bytes'] ?? 0 );
			$derivative_bytes = absint( $derivative['filesize_bytes'] ?? 0 );
			$saved_bytes = max( 0, $original_bytes - $derivative_bytes );
			$ratio = $original_bytes > 0 ? $saved_bytes / $original_bytes : 0;

			return array(
				'original_bytes'   => $original_bytes,
				'derivative_bytes' => $derivative_bytes,
				'saved_bytes'      => $saved_bytes,
				'percent'          => round( $ratio * 100, 2 ),
			);
		}

		/**
		 * Sanitizes a compact Cloud result summary.
		 *
		 * @param array<string,mixed> $cloud_data Cloud data.
		 * @return array<string,mixed>
		 */
		private static function sanitize_cloud_result_summary( array $cloud_data ): array {
			$artifact = is_array( $cloud_data['artifact'] ?? null ) ? $cloud_data['artifact'] : array();

			return array(
				'run_id' => sanitize_text_field( (string) ( $cloud_data['run_id'] ?? '' ) ),
				'status' => sanitize_key( (string) ( $cloud_data['status'] ?? '' ) ),
				'warnings' => self::sanitize_string_list( $cloud_data['warnings'] ?? array() ),
				'artifact_id' => sanitize_text_field( (string) ( $artifact['artifact_id'] ?? '' ) ),
			);
		}

		/**
		 * Sanitizes a string list.
		 *
		 * @param mixed $items Raw list.
		 * @return array<int,string>
		 */
		private static function sanitize_string_list( $items ): array {
			$items = is_array( $items ) ? $items : array();
			$normalized = array();
			foreach ( $items as $item ) {
				$value = sanitize_text_field( (string) $item );
				if ( '' !== $value ) {
					$normalized[] = $value;
				}
			}

			return array_values( array_unique( $normalized ) );
		}
	}
}
