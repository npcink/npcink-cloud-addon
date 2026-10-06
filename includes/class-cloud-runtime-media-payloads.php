<?php
/**
 * Runtime media upload and delivery-ack payload guards.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Runtime_Media_Payloads' ) ) {
	/**
	 * Builds and validates the bounded media payloads for the Runtime Client's
	 * media endpoints.
	 *
	 * Every method is static and instance-free: multipart assembly and exact
	 * upload/ack response validation run over the caller-provided payload,
	 * bytes, and format tables.
	 */
	final class Npcink_Cloud_Runtime_Media_Payloads {

		public const MEDIA_UPLOAD_FORMATS = array(
			'image/avif' => 'avif',
			'image/jpeg' => 'jpeg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);
		public const WP_AI_ALT_TEXT_UPLOAD_FORMATS = array(
			'image/jpeg' => 'jpeg',
			'image/png'  => 'png',
			'image/webp' => 'webp',
		);
		private const WP_AI_ALT_TEXT_MIN_ARTIFACT_TTL_SECONDS = 120;

		/**
		 * Builds the fixed two-part media upload body.
		 *
		 * @param array<string,mixed> $payload Upload request payload.
		 * @param string              $contents Image bytes.
		 * @param string              $filename Sanitized filename.
		 * @param string              $mime_type Allowed image MIME type.
		 * @return array{body:string,content_type:string}|WP_Error
		 */
		public static function build_media_upload_multipart_body( array $payload, string $contents, string $filename, string $mime_type ) {
			$encoded = wp_json_encode( $payload );
			if ( ! is_string( $encoded ) || '' === $encoded ) {
				return new WP_Error(
					'cloud_runtime_encode_failed',
					__( 'Cloud media upload request could not be encoded.', 'npcink-cloud-addon' )
				);
			}

			$boundary = 'npcink-cloud-addon-media-' . wp_generate_uuid4();
			$body = '--' . $boundary . "\r\n";
			$body .= "Content-Disposition: form-data; name=\"request\"\r\n";
			$body .= "Content-Type: application/json\r\n\r\n";
			$body .= $encoded . "\r\n";
			$body .= '--' . $boundary . "\r\n";
			$body .= 'Content-Disposition: form-data; name="file"; filename="' . $filename . "\"\r\n";
			$body .= 'Content-Type: ' . $mime_type . "\r\n\r\n";
			$body .= $contents . "\r\n";
			$body .= '--' . $boundary . "--\r\n";

			return array(
				'body'         => $body,
				'content_type' => 'multipart/form-data; boundary=' . $boundary,
			);
		}

		/**
		 * Validates one exact media_upload_result.v1 response.
		 *
		 * @param array<string,mixed> $response Decoded Cloud response.
		 * @param string              $mime_type Requested MIME type.
		 * @param string              $contents Uploaded bytes.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_media_upload_response( array $response, string $mime_type, string $contents ) {
			$result   = $response['data']['result'] ?? null;
			$artifact = is_array( $result ) ? ( $result['artifact'] ?? null ) : null;
			$expires_at = is_array( $artifact ) && is_string( $artifact['expires_at'] ?? null )
				? $artifact['expires_at']
				: '';
			$expires_timestamp = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $expires_at )
				? strtotime( $expires_at )
				: false;
			$expected_format = self::MEDIA_UPLOAD_FORMATS[ $mime_type ] ?? '';
			$result_keys = array( 'artifact_type', 'contract_version', 'artifact' );
			$artifact_keys = array( 'artifact_id', 'media_kind', 'status', 'content_type', 'format', 'width', 'height', 'filesize_bytes', 'checksum', 'expires_at', 'purged_at' );
			$is_valid = is_array( $result )
				&& count( $result_keys ) === count( $result )
				&& array() === array_diff( $result_keys, array_keys( $result ) )
				&& array() === array_diff( array_keys( $result ), $result_keys )
				&& 'media_upload_artifact' === ( $result['artifact_type'] ?? null )
				&& 'media_upload_result.v1' === ( $result['contract_version'] ?? null )
				&& is_array( $artifact )
				&& count( $artifact_keys ) === count( $artifact )
				&& array() === array_diff( $artifact_keys, array_keys( $artifact ) )
				&& array() === array_diff( array_keys( $artifact ), $artifact_keys )
				&& is_string( $artifact['artifact_id'] ?? null )
				&& 1 === preg_match( Npcink_Cloud_Runtime_Request_Guards::MEDIA_ARTIFACT_ID_PATTERN, $artifact['artifact_id'] )
				&& 'image' === ( $artifact['media_kind'] ?? null )
				&& 'available' === ( $artifact['status'] ?? null )
				&& null === ( $artifact['purged_at'] ?? null )
				&& $mime_type === ( $artifact['content_type'] ?? null )
				&& $expected_format === ( $artifact['format'] ?? null )
				&& is_int( $artifact['width'] ?? null )
				&& $artifact['width'] > 0
				&& is_int( $artifact['height'] ?? null )
				&& $artifact['height'] > 0
				&& is_int( $artifact['filesize_bytes'] ?? null )
				&& strlen( $contents ) === $artifact['filesize_bytes']
				&& is_string( $artifact['checksum'] ?? null )
				&& 'sha256:' . hash( 'sha256', $contents ) === $artifact['checksum']
				&& false !== $expires_timestamp
				&& $expires_timestamp > time();

			if ( ! $is_valid ) {
				return new WP_Error(
					'cloud_media_upload_artifact_invalid',
					__( 'Cloud returned an invalid media upload artifact.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			return $artifact;
		}

		/**
		 * Validates the exact Cloud transfer-only acknowledgement projection.
		 *
		 * @param array<string,mixed> $response Decoded Cloud response.
		 * @param string              $artifact_id Expected artifact id.
		 * @param array<string,mixed> $request Exact ACK request.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_media_delivery_ack_response( array $response, string $artifact_id, array $request ) {
			$data = $response['data'] ?? null;
			$keys = array(
				'contract_version',
				'delivery_id',
				'artifact_id',
				'status',
				'received_byte_size',
				'received_checksum',
				'byte_size_verified',
				'checksum_verified',
				'acknowledged_at',
				'artifact_expires_at',
				'idempotent_replay',
				'acknowledgement_scope',
			);
			$is_valid = is_array( $data )
				&& count( $keys ) === count( $data )
				&& array() === array_diff( $keys, array_keys( $data ) )
				&& array() === array_diff( array_keys( $data ), $keys )
				&& 'media_artifact_delivery_ack.v1' === ( $data['contract_version'] ?? null )
				&& (string) ( $request['delivery_id'] ?? '' ) === ( $data['delivery_id'] ?? null )
				&& $artifact_id === ( $data['artifact_id'] ?? null )
				&& 'acknowledged' === ( $data['status'] ?? null )
				&& (int) ( $request['received_byte_size'] ?? -1 ) === ( $data['received_byte_size'] ?? null )
				&& (string) ( $request['received_checksum'] ?? '' ) === ( $data['received_checksum'] ?? null )
				&& true === ( $data['byte_size_verified'] ?? null )
				&& true === ( $data['checksum_verified'] ?? null )
				&& is_string( $data['acknowledged_at'] ?? null )
				&& false !== self::strict_media_timestamp( (string) $data['acknowledged_at'] )
				&& is_string( $data['artifact_expires_at'] ?? null )
				&& false !== self::strict_media_timestamp( (string) $data['artifact_expires_at'] )
				&& is_bool( $data['idempotent_replay'] ?? null )
				&& 'verified_transfer_only' === ( $data['acknowledgement_scope'] ?? null );

			if ( ! $is_valid ) {
				return new WP_Error(
					'cloud_media_delivery_ack_response_invalid',
					__( 'Cloud returned an invalid media delivery acknowledgement.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			return $data;
		}

		/**
		 * Parses exact canonical UTC RFC3339 media timestamps.
		 *
		 * @param string $value Timestamp.
		 * @return int|false
		 */
		private static function strict_media_timestamp( string $value ) {
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
		 * Validates one Cloud alt-text upload artifact response.
		 *
		 * @param array<string,mixed> $response Decoded Cloud response.
		 * @param string              $mime_type Requested MIME type.
		 * @param string              $contents Uploaded image bytes.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_wordpress_ai_alt_text_upload_response( array $response, string $mime_type, string $contents ) {
			$result   = $response['data']['result'] ?? null;
			$artifact = is_array( $result ) ? ( $result['artifact'] ?? null ) : null;
			$expires_at = is_array( $artifact ) && is_string( $artifact['expires_at'] ?? null )
				? $artifact['expires_at']
				: '';
			$expires_timestamp = 1 === preg_match( '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $expires_at )
				? strtotime( $expires_at )
				: false;
			$expected_format = self::WP_AI_ALT_TEXT_UPLOAD_FORMATS[ $mime_type ];
			$is_valid = is_array( $result )
				&& 'media_upload_artifact' === ( $result['artifact_type'] ?? null )
				&& 'media_upload_result.v1' === ( $result['contract_version'] ?? null )
				&& is_array( $artifact )
				&& is_string( $artifact['artifact_id'] ?? null )
				&& 1 === preg_match( '/^art_[0-9a-f]{32}$/', $artifact['artifact_id'] )
				&& 'image' === ( $artifact['media_kind'] ?? null )
				&& 'available' === ( $artifact['status'] ?? null )
				&& $mime_type === ( $artifact['content_type'] ?? null )
				&& $expected_format === ( $artifact['format'] ?? null )
				&& is_int( $artifact['width'] ?? null )
				&& $artifact['width'] > 0
				&& is_int( $artifact['height'] ?? null )
				&& $artifact['height'] > 0
				&& is_int( $artifact['filesize_bytes'] ?? null )
				&& strlen( $contents ) === $artifact['filesize_bytes']
				&& is_string( $artifact['checksum'] ?? null )
				&& 'sha256:' . hash( 'sha256', $contents ) === $artifact['checksum']
				&& false !== $expires_timestamp
				&& $expires_timestamp > time() + self::WP_AI_ALT_TEXT_MIN_ARTIFACT_TTL_SECONDS;

			if ( ! $is_valid ) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_upload_artifact_invalid',
					__( 'Cloud returned an invalid WordPress AI alt-text source artifact.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			return array(
				'artifact_id'    => $artifact['artifact_id'],
				'media_kind'     => 'image',
				'status'         => 'available',
				'content_type'   => $mime_type,
				'format'         => $expected_format,
				'width'          => $artifact['width'],
				'height'         => $artifact['height'],
				'filesize_bytes' => $artifact['filesize_bytes'],
				'checksum'       => $artifact['checksum'],
				'expires_at'     => $expires_at,
			);
		}
	}
}
