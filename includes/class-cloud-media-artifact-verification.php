<?php
/**
 * Media artifact verification for the derivative transport.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Media_Artifact_Verification' ) ) {
	/**
	 * Verifies Cloud derivative artifact descriptors and drives the verified pull-and-ack transfer. Static and instance-free; the shared upload caps and scalar normalizers are public so the source-validation and transport seams reuse them.
	 */
	final class Npcink_Cloud_Media_Artifact_Verification {
		public const MAX_UPLOAD_BYTES = 26214400;
		public const MAX_IMAGE_DIMENSION = 8192;
		public const MAX_IMAGE_PIXELS = 16777216;
		public const MAX_PROCESSING_WARNINGS = 20;
		public const MAX_PROCESSING_WARNING_BYTES = 200;
		private const MAX_SUGGESTED_FILENAME_BYTES = 120;

		private const MIME_BY_FORMAT = array(
			'avif' => 'image/avif',
			'jpeg' => 'image/jpeg',
			'png'  => 'image/png',
			'webp' => 'image/webp',
		);

		/**
		 * Infers a derivative artifact descriptor from a Cloud result.
		 *
		 * @param array<string,mixed> $cloud_result Cloud result.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function artifact_from_cloud_result( array $cloud_result ) {
			$data   = is_array( $cloud_result['data'] ?? null ) ? $cloud_result['data'] : array();
			$result = is_array( $data['result'] ?? null ) ? $data['result'] : array();
			$canary = Npcink_Cloud_Media_Governance_Validation::normalize_governance_canary_result( $result );
			if ( is_wp_error( $canary ) ) {
				return $canary;
			}
			if ( is_array( $canary ) ) {
				if ( 'skipped' === $canary['status'] ) {
					return new WP_Error(
						'cloud_media_governance_canary_has_no_artifact',
						__( 'Skipped media governance canaries do not publish derivative artifacts.', 'npcink-cloud-addon' ),
						array( 'status' => 409 )
					);
				}
				$result = $canary['derivative'];
			}
				$expected_result_keys = array( 'artifact_type', 'contract_version', 'workflow_metadata', 'artifact' );
				$expected_result_keys[] = 'status';
			if (
				count( $expected_result_keys ) !== count( $result )
				|| array() !== array_diff( $expected_result_keys, array_keys( $result ) )
				|| array() !== array_diff( array_keys( $result ), $expected_result_keys )
				|| 'media_derivative_artifact' !== (string) ( $result['artifact_type'] ?? '' )
					|| 'media_derivative_result.v3' !== (string) ( $result['contract_version'] ?? '' )
					|| 'qualified' !== (string) ( $result['status'] ?? '' )
				|| ! is_array( $result['workflow_metadata'] ?? null )
				|| ! is_array( $result['artifact'] ?? null )
			) {
				return new WP_Error(
					'cloud_media_derivative_result_contract_invalid',
						__( 'Cloud media derivative results require the exact qualified media_derivative_result.v3 artifact envelope.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			return self::normalize_artifact_descriptor( $result['artifact'], 'derivative' );
		}

		/**
		 * Receives, independently verifies, and acknowledges a Cloud derivative artifact.
		 *
		 * ACK proves only verified transfer. This helper never persists, approves,
		 * imports, adopts, or writes the artifact into WordPress.
		 *
		 * @param array<string,mixed> $derivative_artifact Exact local 12-field proposal artifact.
		 * @param string              $trace_id Optional trace id.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function receive_artifact( array $derivative_artifact, string $trace_id = '' ) {
			$client = Npcink_Cloud_Media_Derivative_Transport::verified_client();
			if ( is_wp_error( $client ) ) {
				return $client;
			}

			$artifact = self::normalize_local_proposal_artifact( $derivative_artifact );
			if ( is_wp_error( $artifact ) ) {
				return $artifact;
			}

			$download = $client->pull_media_artifact(
				(string) $artifact['artifact_id'],
				$trace_id
			);
			if ( is_wp_error( $download ) ) {
				return $download;
			}

			$contents = is_string( $download['body'] ?? null ) ? $download['body'] : '';
			if ( '' === $contents ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_empty',
					__( 'Cloud derivative artifact download returned no bytes.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			$artifact_mime = (string) $artifact['mime_type'];
			$response_mime = self::normalize_response_mime_type( (string) ( $download['content_type'] ?? '' ) );
			if ( $artifact_mime !== $response_mime ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_mime_mismatch',
					__( 'Cloud derivative artifact mime type does not match the descriptor.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			$expected_size = (int) $artifact['filesize_bytes'];
			if ( $expected_size !== (int) ( $download['content_length'] ?? 0 ) || $expected_size !== strlen( $contents ) ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_size_mismatch',
					__( 'Derivative artifact byte size does not match the descriptor and signed response.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			$actual_sha256 = hash( 'sha256', $contents );
			$checksum = 'sha256:' . $actual_sha256;
			if ( $actual_sha256 !== (string) $artifact['sha256'] ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_checksum_mismatch',
					__( 'Derivative artifact checksum does not match the downloaded bytes.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}
			if (
				(string) $artifact['artifact_id'] !== (string) ( $download['artifact_id'] ?? '' )
				|| $checksum !== (string) ( $download['artifact_checksum'] ?? '' )
			) {
				return new WP_Error(
					'cloud_media_derivative_artifact_header_mismatch',
					__( 'Signed media response headers do not match the requested artifact.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			$delivery_id = sanitize_text_field( (string) ( $download['delivery_id'] ?? '' ) );
			$ack_deadline_at = sanitize_text_field( (string) ( $download['delivery_ack_deadline'] ?? '' ) );
			$ack_deadline_timestamp = self::strict_timestamp( $ack_deadline_at );
			if (
				1 !== preg_match( '/^mdl_[0-9a-f]{32}$/', $delivery_id )
				|| false === $ack_deadline_timestamp
				|| $ack_deadline_timestamp <= time()
				|| $ack_deadline_timestamp > (int) strtotime( (string) $artifact['expires_at'] )
			) {
				return new WP_Error(
					'cloud_media_derivative_delivery_headers_invalid',
					__( 'Cloud media delivery headers are missing or invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			$image_info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $contents ) : false;
			$decoded_mime = is_array( $image_info ) ? self::normalize_media_type( (string) ( $image_info['mime'] ?? '' ) ) : '';
			$decoded_width = is_array( $image_info ) ? (int) ( $image_info[0] ?? 0 ) : 0;
			$decoded_height = is_array( $image_info ) ? (int) ( $image_info[1] ?? 0 ) : 0;
			if (
				! is_array( $image_info )
				|| $artifact_mime !== $decoded_mime
				|| (int) $artifact['width'] !== $decoded_width
				|| (int) $artifact['height'] !== $decoded_height
			) {
				return new WP_Error(
					'cloud_media_derivative_artifact_decode_mismatch',
					__( 'Derivative artifact decode facts do not match the Cloud descriptor.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			$delivery_ack = $client->acknowledge_media_artifact_delivery(
				(string) $artifact['artifact_id'],
				array(
					'contract_version'   => 'media_artifact_delivery_ack.v1',
					'delivery_id'        => $delivery_id,
					'received_byte_size' => $expected_size,
					'received_checksum'  => $checksum,
				),
				$trace_id,
				'media_delivery_ack_' . wp_generate_uuid4()
			);
			if ( is_wp_error( $delivery_ack ) ) {
				return $delivery_ack;
			}
			$acknowledged_timestamp = self::strict_timestamp( (string) ( $delivery_ack['acknowledged_at'] ?? '' ) );
			$ack_expiry_timestamp   = self::strict_timestamp( (string) ( $delivery_ack['artifact_expires_at'] ?? '' ) );
			$descriptor_expiry      = self::strict_timestamp( (string) $artifact['expires_at'] );
			if (
				(string) ( $delivery_ack['artifact_id'] ?? '' ) !== (string) $artifact['artifact_id']
				|| (string) ( $delivery_ack['delivery_id'] ?? '' ) !== $delivery_id
				|| (int) ( $delivery_ack['received_byte_size'] ?? 0 ) !== $expected_size
				|| (string) ( $delivery_ack['received_checksum'] ?? '' ) !== $checksum
				|| true !== ( $delivery_ack['byte_size_verified'] ?? null )
				|| true !== ( $delivery_ack['checksum_verified'] ?? null )
				|| false === $acknowledged_timestamp
				|| $acknowledged_timestamp > $ack_deadline_timestamp
				|| false === $ack_expiry_timestamp
				|| $ack_expiry_timestamp <= time()
				|| $ack_expiry_timestamp <= $acknowledged_timestamp
				|| false === $descriptor_expiry
				|| (string) ( $delivery_ack['artifact_expires_at'] ?? '' ) !== (string) $artifact['expires_at']
				|| $ack_expiry_timestamp !== $descriptor_expiry
			) {
				return new WP_Error(
					'cloud_media_derivative_delivery_ack_binding_invalid',
					__( 'Cloud media delivery acknowledgement does not bind to the verified transfer.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			$transfer_evidence = array(
				'contract_version'      => 'media_artifact_verified_transfer.v1',
				'artifact_id'           => (string) $artifact['artifact_id'],
				'delivery_id'           => $delivery_id,
				'received_byte_size'    => $expected_size,
				'received_checksum'     => $checksum,
				'byte_size_verified'    => true,
				'checksum_verified'     => true,
				'content_type_verified' => true,
				'image_decoded'         => true,
				'dimensions_verified'   => true,
				'ack_deadline_at'       => $ack_deadline_at,
			);

			return array(
				'artifact_id'    => (string) $artifact['artifact_id'],
				'contents'       => $contents,
				'mime_type'      => $artifact_mime,
				'width'          => $decoded_width,
				'height'         => $decoded_height,
				'filesize_bytes' => $expected_size,
				'sha256'         => $actual_sha256,
				'expires_at'     => (string) $delivery_ack['artifact_expires_at'],
				'transfer_evidence' => $transfer_evidence,
				'delivery_ack'   => $delivery_ack,
			);
		}

		/**
		 * Normalizes an artifact descriptor and rejects expired artifacts.
		 *
		 * @param array<string,mixed> $artifact Artifact descriptor.
		 * @param string              $role Artifact role.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_artifact_descriptor( array $artifact, string $role ) {
			unset( $role );
			$expected_keys = array(
				'artifact_id',
				'artifact_reference',
				'expires_at',
				'suggested_filename',
				'filename_basis',
				'mime_type',
				'format',
				'width',
				'height',
				'filesize_bytes',
				'checksum',
				'processing_warnings',
				'transform_facts',
			);
			if (
				count( $expected_keys ) !== count( $artifact )
				|| array() !== array_diff( $expected_keys, array_keys( $artifact ) )
				|| array() !== array_diff( array_keys( $artifact ), $expected_keys )
			) {
				return new WP_Error(
					'cloud_media_derivative_artifact_contract_invalid',
				__( 'Media derivative artifacts require the exact 13-field Cloud descriptor.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			$expires_at = sanitize_text_field( (string) ( $artifact['expires_at'] ?? '' ) );
			if ( false === self::strict_timestamp( $expires_at ) ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_expiry_missing',
					__( 'Media derivative artifact expiry must be a strict ISO-8601 timestamp.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( self::is_expired( $expires_at ) ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_expired',
					__( 'Expired Cloud artifacts cannot be adopted.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			$artifact_id = sanitize_text_field( (string) ( $artifact['artifact_id'] ?? '' ) );
			if ( 1 !== preg_match( '/^art_[0-9a-f]{32}$/', $artifact_id ) ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_id_invalid',
					__( 'Derivative Cloud artifacts require a canonical artifact id.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			$artifact_reference = $artifact['artifact_reference'] ?? null;
			if ( ! is_array( $artifact_reference ) || 1 !== count( $artifact_reference ) || ! array_key_exists( 'artifact_id', $artifact_reference ) || $artifact_id !== ( $artifact_reference['artifact_id'] ?? null ) ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_reference_invalid',
					__( 'Media derivative artifact reference must bind to the canonical artifact id.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}
			$filename_basis = $artifact['filename_basis'] ?? null;
			if (
				! is_array( $filename_basis )
				|| 3 !== count( $filename_basis )
				|| array() !== array_diff( array( 'owner', 'strategy', 'final_sanitize_unique_required' ), array_keys( $filename_basis ) )
				|| array() !== array_diff( array_keys( $filename_basis ), array( 'owner', 'strategy', 'final_sanitize_unique_required' ) )
				|| 'wordpress_write_ability_final' !== ( $filename_basis['owner'] ?? null )
				|| 'format_checksum' !== ( $filename_basis['strategy'] ?? null )
				|| true !== ( $filename_basis['final_sanitize_unique_required'] ?? null )
			) {
				return new WP_Error(
					'cloud_media_derivative_filename_basis_invalid',
					__( 'Media derivative filename basis is invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}
			$suggested_filename = (string) ( $artifact['suggested_filename'] ?? '' );
			if ( '' === $suggested_filename || sanitize_file_name( $suggested_filename ) !== $suggested_filename || strlen( $suggested_filename ) > self::MAX_SUGGESTED_FILENAME_BYTES ) {
				return new WP_Error(
					'cloud_media_derivative_suggested_filename_invalid',
					__( 'Media derivative suggested filename is invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			$mime_type = self::normalize_media_type( (string) ( $artifact['mime_type'] ?? '' ) );
			$format    = sanitize_key( (string) ( $artifact['format'] ?? '' ) );
			$mime_by_format = self::MIME_BY_FORMAT;
			if ( '' === $mime_type || ! isset( $mime_by_format[ $format ] ) || $mime_type !== $mime_by_format[ $format ] ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_mime_invalid',
					__( 'Media derivative artifact MIME and format must agree.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}
			$width = $artifact['width'] ?? null;
			$height = $artifact['height'] ?? null;
			$filesize_bytes = $artifact['filesize_bytes'] ?? null;
			$checksum = (string) ( $artifact['checksum'] ?? '' );
			$warnings = $artifact['processing_warnings'] ?? null;
			$transform_facts = $artifact['transform_facts'] ?? null;
			if (
				! is_int( $width ) || $width <= 0 || $width > self::MAX_IMAGE_DIMENSION
				|| ! is_int( $height ) || $height <= 0 || $height > self::MAX_IMAGE_DIMENSION
				|| $width * $height > self::MAX_IMAGE_PIXELS
				|| ! is_int( $filesize_bytes ) || $filesize_bytes <= 0 || $filesize_bytes > self::MAX_UPLOAD_BYTES
				|| 1 !== preg_match( '/^sha256:[0-9a-f]{64}$/', $checksum )
				|| ! is_array( $warnings ) || count( $warnings ) > self::MAX_PROCESSING_WARNINGS
				|| ! is_array( $transform_facts )
			) {
				return new WP_Error(
					'cloud_media_derivative_artifact_facts_invalid',
					__( 'Media derivative artifact facts are invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}
			foreach ( $warnings as $warning ) {
				if ( ! is_string( $warning ) || strlen( $warning ) > self::MAX_PROCESSING_WARNING_BYTES ) {
					return new WP_Error(
						'cloud_media_derivative_artifact_warnings_invalid',
						__( 'Media derivative processing warnings are invalid.', 'npcink-cloud-addon' ),
						array( 'status' => 502 )
					);
				}
			}

			return array(
				'artifact_id'    => $artifact_id,
				'artifact_reference' => array( 'artifact_id' => $artifact_id ),
				'expires_at'     => $expires_at,
				'suggested_filename' => $suggested_filename,
				'filename_basis' => $filename_basis,
				'mime_type'      => $mime_type,
				'format'         => $format,
				'width'          => $width,
				'height'         => $height,
				'filesize_bytes' => $filesize_bytes,
				'checksum'       => $checksum,
				'processing_warnings' => array_values( array_map( 'sanitize_text_field', $warnings ) ),
				'transform_facts' => $transform_facts,
			);
		}

		/**
		 * Projects the Cloud descriptor into the minimal local Toolkit artifact contract.
		 *
		 * @param array<string,mixed> $artifact Strict Cloud descriptor.
		 * @return array<string,mixed>
		 */
		public static function local_proposal_artifact( array $artifact ): array {
			return array(
				'artifact_id'    => (string) $artifact['artifact_id'],
				'expires_at'     => (string) $artifact['expires_at'],
				'mime_type'      => (string) $artifact['mime_type'],
				'format'         => (string) $artifact['format'],
				'width'          => (int) $artifact['width'],
				'height'         => (int) $artifact['height'],
				'filesize_bytes' => (int) $artifact['filesize_bytes'],
				'sha256'         => self::normalize_sha256( (string) $artifact['checksum'] ),
				'suggested_filename' => (string) $artifact['suggested_filename'],
				'filename_basis' => $artifact['filename_basis'],
				'processing_warnings' => $artifact['processing_warnings'],
				'transform_facts' => $artifact['transform_facts'],
			);
		}

		/**
		 * Validates the exact local 12-field artifact passed through Core/Toolkit.
		 *
		 * @param array<string,mixed> $artifact Local proposal artifact.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function normalize_local_proposal_artifact( array $artifact ) {
			$expected_keys = array(
				'artifact_id',
				'expires_at',
				'mime_type',
				'format',
				'width',
				'height',
				'filesize_bytes',
				'sha256',
				'suggested_filename',
				'filename_basis',
				'processing_warnings',
				'transform_facts',
			);
			if (
				count( $expected_keys ) !== count( $artifact )
				|| array() !== array_diff( $expected_keys, array_keys( $artifact ) )
				|| array() !== array_diff( array_keys( $artifact ), $expected_keys )
			) {
				return new WP_Error(
					'cloud_media_derivative_local_artifact_contract_invalid',
					__( 'Local media derivative artifacts require the exact 12-field proposal contract.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$artifact_id = sanitize_text_field( (string) $artifact['artifact_id'] );
			$expires_at  = sanitize_text_field( (string) $artifact['expires_at'] );
			$mime_type   = self::normalize_media_type( (string) $artifact['mime_type'] );
			$format      = sanitize_key( (string) $artifact['format'] );
			$sha256      = (string) $artifact['sha256'];
			$mime_by_format = self::MIME_BY_FORMAT;
			if (
				1 !== preg_match( '/^art_[0-9a-f]{32}$/', $artifact_id )
				|| false === self::strict_timestamp( $expires_at )
				|| self::is_expired( $expires_at )
				|| ! isset( $mime_by_format[ $format ] )
				|| $mime_by_format[ $format ] !== $mime_type
				|| ! is_int( $artifact['width'] ) || $artifact['width'] <= 0 || $artifact['width'] > self::MAX_IMAGE_DIMENSION
				|| ! is_int( $artifact['height'] ) || $artifact['height'] <= 0 || $artifact['height'] > self::MAX_IMAGE_DIMENSION
				|| $artifact['width'] * $artifact['height'] > self::MAX_IMAGE_PIXELS
				|| ! is_int( $artifact['filesize_bytes'] ) || $artifact['filesize_bytes'] <= 0 || $artifact['filesize_bytes'] > self::MAX_UPLOAD_BYTES
				|| 1 !== preg_match( '/^[0-9a-f]{64}$/', $sha256 )
			) {
				return new WP_Error(
					'cloud_media_derivative_local_artifact_facts_invalid',
					__( 'Local media derivative artifact facts are invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			$suggested_filename = (string) $artifact['suggested_filename'];
			$filename_basis = $artifact['filename_basis'];
			$warnings = $artifact['processing_warnings'];
			$transform_facts = $artifact['transform_facts'];
			if (
				'' === $suggested_filename
				|| sanitize_file_name( $suggested_filename ) !== $suggested_filename
				|| strlen( $suggested_filename ) > self::MAX_SUGGESTED_FILENAME_BYTES
				|| ! is_array( $filename_basis )
				|| 3 !== count( $filename_basis )
				|| array() !== array_diff( array( 'owner', 'strategy', 'final_sanitize_unique_required' ), array_keys( $filename_basis ) )
				|| array() !== array_diff( array_keys( $filename_basis ), array( 'owner', 'strategy', 'final_sanitize_unique_required' ) )
				|| 'wordpress_write_ability_final' !== ( $filename_basis['owner'] ?? null )
				|| 'format_checksum' !== ( $filename_basis['strategy'] ?? null )
				|| true !== ( $filename_basis['final_sanitize_unique_required'] ?? null )
				|| ! is_array( $warnings ) || count( $warnings ) > self::MAX_PROCESSING_WARNINGS
				|| ! is_array( $transform_facts )
			) {
				return new WP_Error(
					'cloud_media_derivative_local_artifact_metadata_invalid',
					__( 'Local media derivative artifact metadata is invalid.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			foreach ( $warnings as $warning ) {
				if ( ! is_string( $warning ) || strlen( $warning ) > self::MAX_PROCESSING_WARNING_BYTES ) {
					return new WP_Error(
						'cloud_media_derivative_local_artifact_metadata_invalid',
						__( 'Local media derivative processing warnings are invalid.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
			}

			return array(
				'artifact_id'    => $artifact_id,
				'expires_at'     => $expires_at,
				'mime_type'      => $mime_type,
				'format'         => $format,
				'width'          => $artifact['width'],
				'height'         => $artifact['height'],
				'filesize_bytes' => $artifact['filesize_bytes'],
				'sha256'         => $sha256,
				'suggested_filename' => $suggested_filename,
				'filename_basis' => $filename_basis,
				'processing_warnings' => array_values( array_map( 'sanitize_text_field', $warnings ) ),
				'transform_facts' => $transform_facts,
			);
		}

		/**
		 * Checks whether an ISO-like timestamp is expired.
		 *
		 * @param string $expires_at Expiry timestamp.
		 * @return bool
		 */
		public static function is_expired( string $expires_at ): bool {
			$timestamp = self::strict_timestamp( $expires_at );
			if ( false === $timestamp ) {
				return true;
			}

			return $timestamp <= time();
		}

		/**
		 * Parses exact canonical UTC RFC3339 without normalizing invalid dates.
		 *
		 * @param string $value Timestamp.
		 * @return int|false
		 */
		private static function strict_timestamp( string $value ) {
			$utc = new DateTimeZone( 'UTC' );
			$formats = array(
				'!Y-m-d\TH:i:s\Z'   => 'Y-m-d\TH:i:s\Z',
				'!Y-m-d\TH:i:sP'    => 'Y-m-d\TH:i:sP',
				'!Y-m-d\TH:i:s.u\Z' => 'Y-m-d\TH:i:s.u\Z',
				'!Y-m-d\TH:i:s.uP'  => 'Y-m-d\TH:i:s.uP',
			);

			foreach ( $formats as $parse_format => $roundtrip_format ) {
				$timestamp = DateTimeImmutable::createFromFormat( $parse_format, $value, $utc );
				$errors    = DateTimeImmutable::getLastErrors();
				if (
					false === $timestamp
					|| ( is_array( $errors ) && ( $errors['warning_count'] > 0 || $errors['error_count'] > 0 ) )
					|| 0 !== $timestamp->getOffset()
					|| $value !== $timestamp->format( $roundtrip_format )
				) {
					continue;
				}

				return $timestamp->getTimestamp();
			}

			return false;
		}

		/**
		 * Normalizes supported media derivative image mime types.
		 *
		 * @param string $mime_type Raw mime type.
		 * @param bool   $allow_generic_image Whether the generic image type is accepted.
		 * @return string
		 */
		public static function normalize_media_type( string $mime_type, bool $allow_generic_image = false ): string {
			$mime_type = strtolower( trim( sanitize_text_field( $mime_type ) ) );
			if ( $allow_generic_image && 'image' === $mime_type ) {
				return 'image';
			}

			$allowed = array(
				'image/avif',
				'image/gif',
				'image/jpeg',
				'image/png',
				'image/webp',
			);

			return in_array( $mime_type, $allowed, true ) ? $mime_type : '';
		}

		/**
		 * Normalizes a response Content-Type header into a supported image mime.
		 *
		 * @param string $content_type Raw Content-Type header.
		 * @return string
		 */
		private static function normalize_response_mime_type( string $content_type ): string {
			$content_type = trim( explode( ';', $content_type )[0] ?? '' );

			return self::normalize_media_type( $content_type );
		}

		/**
		 * Normalizes a SHA-256 digest.
		 *
		 * @param string $value Raw digest.
		 * @return string
		 */
		public static function normalize_sha256( string $value ): string {
			$value = strtolower( trim( $value ) );
			if ( 0 === strpos( $value, 'sha256:' ) ) {
				$value = substr( $value, 7 );
			}

			return preg_match( '/^[a-f0-9]{64}$/', $value ) ? $value : '';
		}
	}
}
