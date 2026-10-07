<?php
/**
 * Local media source validation for the derivative transport.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Media_Source_Validation' ) ) {
	/**
	 * Validates local upload descriptors (bounded size, dimensions, path containment, exact mime agreement) before the transport dispatches Cloud media jobs. Static and instance-free.
	 */
	final class Npcink_Cloud_Media_Source_Validation {
		private const ALLOWED_UPLOAD_MIME_TYPES = array( 'image/avif', 'image/jpeg', 'image/png', 'image/webp' );

		/**
		 * Normalizes an upload file descriptor.
		 *
		 * @param array<string,mixed> $descriptor Local upload descriptor.
		 * @param string              $field_name Multipart field name.
		 * @return array<string,string>|WP_Error
		 */
		public static function normalize_upload_file_descriptor( array $descriptor, string $field_name ) {
			$source_fields = array();
			foreach ( array( 'path', 'bytes', 'content' ) as $source_field ) {
				if ( array_key_exists( $source_field, $descriptor ) ) {
					$source_fields[] = $source_field;
				}
			}

			if (
				empty( $source_fields )
				&& ( array_key_exists( 'artifact_id', $descriptor ) || array_key_exists( 'expires_at', $descriptor ) )
			) {
				return array();
			}

			$allowed_fields = array( 'path', 'bytes', 'content', 'filename', 'mime_type' );
			$unknown_fields = array_values( array_diff( array_keys( $descriptor ), $allowed_fields ) );
			if ( ! empty( $unknown_fields ) ) {
				return new WP_Error(
					'cloud_media_derivative_upload_descriptor_unknown_field',
					__( 'Media upload descriptors contain an unknown field.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'field'  => (string) $unknown_fields[0],
					)
				);
			}

			if ( count( $source_fields ) > 1 ) {
				return new WP_Error(
					'cloud_media_derivative_upload_descriptor_ambiguous_source',
					__( 'Media upload descriptors require exactly one byte source.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'fields' => $source_fields,
					)
				);
			}

			if ( empty( $source_fields ) ) {
				return array();
			}

			if (
				( 'bytes' === $source_fields[0] && ! is_string( $descriptor['bytes'] ) )
				|| ( 'content' === $source_fields[0] && ! is_string( $descriptor['content'] ) )
			) {
				return new WP_Error(
					'cloud_media_derivative_upload_descriptor_invalid_source',
					__( 'Media upload descriptors require the direct byte source to be a string.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'field'  => $source_fields[0],
					)
				);
			}

			$contents = '';
			if ( 'bytes' === $source_fields[0] && is_string( $descriptor['bytes'] ) ) {
				$contents = (string) $descriptor['bytes'];
			} elseif ( 'content' === $source_fields[0] && is_string( $descriptor['content'] ) ) {
				$contents = (string) $descriptor['content'];
			} elseif ( 'path' === $source_fields[0] ) {
				$path = sanitize_text_field( (string) $descriptor['path'] );
				if ( '' === $path ) {
					return new WP_Error(
						'cloud_media_derivative_upload_file_unreadable',
						__( 'Media derivative upload file is not readable.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				$real_path = realpath( $path );
				if ( ! is_string( $real_path ) || ! is_file( $real_path ) || ! is_readable( $real_path ) ) {
					return new WP_Error(
						'cloud_media_derivative_upload_file_unreadable',
						__( 'Media derivative upload file is not readable.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				if ( ! self::is_allowed_upload_file_path( $real_path ) ) {
					return new WP_Error(
						'cloud_media_derivative_upload_file_path_not_allowed',
						__( 'Media derivative upload files must come from the WordPress uploads directory or a local temporary directory.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				$size = filesize( $real_path );
				if ( false !== $size && $size > Npcink_Cloud_Media_Artifact_Verification::MAX_UPLOAD_BYTES ) {
					return new WP_Error(
						'cloud_media_derivative_upload_file_too_large',
						__( 'Media derivative upload file exceeds the Cloud size limit.', 'npcink-cloud-addon' ),
						array( 'status' => 413 )
					);
				}
				$read = file_get_contents( $real_path );
				$contents = is_string( $read ) ? $read : '';
			}

			if ( '' === $contents ) {
				return new WP_Error(
					'cloud_media_derivative_upload_file_empty',
					__( 'Media derivative upload file is empty.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $contents ) > Npcink_Cloud_Media_Artifact_Verification::MAX_UPLOAD_BYTES ) {
				return new WP_Error(
					'cloud_media_derivative_upload_file_too_large',
					__( 'Media derivative upload file exceeds the Cloud size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			$declared_mime = Npcink_Cloud_Media_Artifact_Verification::normalize_media_type( (string) ( $descriptor['mime_type'] ?? '' ) );
			if ( ! in_array( $declared_mime, self::ALLOWED_UPLOAD_MIME_TYPES, true ) ) {
				return new WP_Error(
					'cloud_media_derivative_upload_mime_not_allowed',
					__( 'Media derivative uploads require an allowed image mime type.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$image_info = function_exists( 'getimagesizefromstring' ) ? @getimagesizefromstring( $contents ) : false;
			$detected_mime = is_array( $image_info ) ? Npcink_Cloud_Media_Artifact_Verification::normalize_media_type( (string) ( $image_info['mime'] ?? '' ) ) : '';
			$width = is_array( $image_info ) ? (int) ( $image_info[0] ?? 0 ) : 0;
			$height = is_array( $image_info ) ? (int) ( $image_info[1] ?? 0 ) : 0;
			if ( ! is_array( $image_info ) || $declared_mime !== $detected_mime ) {
				return new WP_Error(
					'cloud_media_derivative_upload_image_invalid',
					__( 'Media derivative upload bytes do not match the declared image mime type.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if (
				$width <= 0
				|| $height <= 0
				|| $width > Npcink_Cloud_Media_Artifact_Verification::MAX_IMAGE_DIMENSION
				|| $height > Npcink_Cloud_Media_Artifact_Verification::MAX_IMAGE_DIMENSION
				|| ( $width * $height ) > Npcink_Cloud_Media_Artifact_Verification::MAX_IMAGE_PIXELS
			) {
				return new WP_Error(
					'cloud_media_derivative_upload_dimensions_invalid',
					__( 'Media derivative upload dimensions exceed the local image limits.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			return array(
				'field_name' => sanitize_key( $field_name ),
				'filename'   => sanitize_file_name( (string) ( $descriptor['filename'] ?? $field_name ) ),
				'mime_type'  => $declared_mime,
				'contents'   => $contents,
			);
		}

		/**
		 * Returns whether a local upload path is in an approved local media/temp directory.
		 *
		 * @param string $path Real local file path.
		 * @return bool
		 */
		private static function is_allowed_upload_file_path( string $path ): bool {
			$allowed_dirs = array();
			if ( function_exists( 'wp_upload_dir' ) ) {
				$upload_dir = wp_upload_dir();
				if ( is_array( $upload_dir ) && ! empty( $upload_dir['basedir'] ) ) {
					$allowed_dirs[] = (string) $upload_dir['basedir'];
				}
			}
			if ( function_exists( 'get_temp_dir' ) ) {
				$allowed_dirs[] = (string) get_temp_dir();
			}
			$allowed_dirs[] = sys_get_temp_dir();

			foreach ( array_unique( array_filter( $allowed_dirs ) ) as $dir ) {
				$real_dir = realpath( $dir );
				if ( ! is_string( $real_dir ) || '' === $real_dir ) {
					continue;
				}
				$real_dir = rtrim( $real_dir, DIRECTORY_SEPARATOR );
				if ( $path === $real_dir || 0 === strpos( $path, $real_dir . DIRECTORY_SEPARATOR ) ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Returns whether a descriptor includes a local upload source.
		 *
		 * @param array<string,mixed> $descriptor Artifact or upload descriptor.
		 * @return bool
		 */
		public static function descriptor_has_upload_file( array $descriptor ): bool {
			return array_key_exists( 'bytes', $descriptor )
				|| array_key_exists( 'content', $descriptor )
				|| array_key_exists( 'path', $descriptor );
		}

		/**
		 * Rejects removed media upload descriptor aliases.
		 *
		 * @param array<string,mixed> $descriptor Artifact or upload descriptor.
		 * @return true|WP_Error
		 */
		public static function validate_upload_descriptor_legacy_fields( array $descriptor ) {
			foreach ( array( 'file_path', 'tmp_name', 'name' ) as $legacy_field ) {
				if ( array_key_exists( $legacy_field, $descriptor ) ) {
					return new WP_Error(
						'cloud_media_derivative_upload_descriptor_legacy_field',
						__( 'Legacy media upload descriptor fields are not accepted.', 'npcink-cloud-addon' ),
						array(
							'status' => 400,
							'field'  => $legacy_field,
						)
					);
				}
			}

			return true;
		}

		/**
		 * Returns whether a descriptor includes a Cloud artifact id.
		 *
		 * @param array<string,mixed> $descriptor Artifact or upload descriptor.
		 * @return bool
		 */
		public static function descriptor_has_artifact_id( array $descriptor ): bool {
			return '' !== sanitize_text_field( (string) ( $descriptor['artifact_id'] ?? '' ) );
		}

		/**
		 * Normalizes a required Cloud artifact id reference for runtime processing.
		 *
		 * @param array<string,mixed> $artifact Artifact descriptor.
		 * @param string              $role Artifact role.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function normalize_required_artifact_reference( array $artifact, string $role ) {
			$artifact_id = sanitize_text_field( (string) ( $artifact['artifact_id'] ?? '' ) );
			$expires_at  = sanitize_text_field( (string) ( $artifact['expires_at'] ?? '' ) );
			if ( 1 !== preg_match( '/^art_[0-9a-f]{32}$/', $artifact_id ) ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_id_invalid',
					__( 'Media derivative runtime artifacts require a canonical artifact id.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( '' === $expires_at || Npcink_Cloud_Media_Artifact_Verification::is_expired( $expires_at ) ) {
				return new WP_Error(
					'cloud_media_derivative_artifact_expired',
					__( 'Media derivative runtime artifacts require a valid future expiry.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			return array(
				'artifact_id' => $artifact_id,
				'expires_at'  => $expires_at,
				'role'        => sanitize_key( $role ),
			);
		}
	}
}
