<?php
/**
 * Media governance canary validation for the derivative transport.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Media_Governance_Validation' ) ) {
	/**
	 * Strictly validates Cloud media governance canary results and projects their bounded status. Static and instance-free; the shared exact-keys check and the minimum savings basis are public for the orchestration seam.
	 */
	final class Npcink_Cloud_Media_Governance_Validation {
		public const GOVERNANCE_MINIMUM_SAVINGS_BASIS_POINTS = 1500;
		private const GOVERNANCE_RESULT_CONTRACT_VERSION = 'media_governance_canary_result.v1';
		private const GOVERNANCE_MINIMUM_SOURCE_BYTES = 512000;

		/**
		 * Returns a strictly validated governance canary wrapper when present.
		 *
		 * @param array<string,mixed> $cloud_result Cloud run result envelope.
		 * @return array<string,mixed>|null|WP_Error
		 */
			public static function governance_canary_from_cloud_result( array $cloud_result ) {
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
		public static function auto_safe_skip_from_cloud_result( array $cloud_result ) {
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
			$reasons = Npcink_Cloud_Media_Plan_Projection::bounded_projection_warnings( $decision['decision_reasons'] ?? array() );
			$fact_reasons = Npcink_Cloud_Media_Plan_Projection::bounded_projection_warnings( $facts['decision_reasons'] ?? array() );
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
				'transform_facts'  => Npcink_Cloud_Media_Plan_Projection::sanitize_projection_value( $facts ),
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
			$reasons = Npcink_Cloud_Media_Plan_Projection::bounded_projection_warnings( $validation['reasons'] );
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
		public static function governance_canary_projection( array $canary ): array {
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
		 * Returns whether an array has exactly the expected string keys.
		 *
		 * @param array<string,mixed> $value Array under validation.
		 * @param array<int,string>   $expected_keys Expected keys.
		 * @return bool
		 */
		public static function has_exact_keys( array $value, array $expected_keys ): bool {
			return count( $expected_keys ) === count( $value )
				&& array() === array_diff( $expected_keys, array_keys( $value ) )
				&& array() === array_diff( array_keys( $value ), $expected_keys );
		}
	}
}
