<?php
/**
 * Local alt-text source handoff into the Cloud runtime.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	! class_exists( 'Npcink_Cloud_WordPress_AI_Alt_Text_Handoff' )
) {
	/**
	 * Bounded local attachment handoff for WordPress AI alt text generation.
	 */
	final class Npcink_Cloud_WordPress_AI_Alt_Text_Handoff {
		private const MAX_SOURCE_BYTES = 8388608;
		private const ALLOWED_MIME_TYPES = array( 'image/jpeg', 'image/png', 'image/webp' );

		/**
		 * Extracts one positive attachment ID from the WordPress AI ability input.
		 *
		 * @param array<string,mixed> $input Ability input.
		 * @return int|WP_Error
		 */
		public static function attachment_id_from_ability_input( array $input ) {
			if ( ! array_key_exists( 'attachment_id', $input ) ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_required', 'WordPress AI alt text generation requires a local WordPress attachment.' );
			}

			$value = $input['attachment_id'];
			if (
				( ! is_int( $value ) && ! is_string( $value ) )
				|| ( is_string( $value ) && 1 !== preg_match( '/^[0-9]+$/', $value ) )
				|| ( is_int( $value ) && 1 > $value )
			) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_required', 'WordPress AI alt text generation requires a local WordPress attachment.' );
			}

			$attachment_id = absint( $value );
			if ( 0 >= $attachment_id ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_required', 'WordPress AI alt text generation requires a local WordPress attachment.' );
			}

			return $attachment_id;
		}

		/**
		 * Uploads one authorized local attachment and executes the suggestion-only scene.
		 *
		 * @param int         $attachment_id Attachment ID.
		 * @param string      $prompt Alt-text prompt.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function dispatch( int $attachment_id, string $prompt ) {
			// Fail fast on missing transport before any local file I/O.
			if ( ! function_exists( 'npcink_cloud_addon_upload_wordpress_ai_alt_text_source' ) || ! function_exists( 'npcink_cloud_addon_execute_wordpress_ai_connector_runtime' ) ) {
				return new WP_Error( 'cloud_wp_ai_alt_text_verified_client_required', __( 'WordPress AI alt text generation requires verified Npcink Cloud settings.', 'npcink-cloud-addon' ), array( 'status' => 503 ) );
			}

			// Project the local task contract before reading or uploading any bytes.
			$task_contract = function_exists( 'npcink_cloud_addon_project_ai_task_contract' )
				? npcink_cloud_addon_project_ai_task_contract( 'ai/alt-text-generation' )
				: null;
			if ( is_wp_error( $task_contract ) ) {
				return $task_contract;
			}
			if ( null !== $task_contract && ! is_array( $task_contract ) ) {
				return new WP_Error( 'cloud_wp_ai_alt_text_task_contract_invalid', __( 'Npcink Cloud could not project the alt-text Ability contract.', 'npcink-cloud-addon' ), array( 'status' => 500 ) );
			}

			$source = self::local_source( $attachment_id, $prompt );
			if ( is_wp_error( $source ) ) {
				return $source;
			}

			$trace_id = 'trace_wp_ai_vision_' . wp_generate_uuid4();
			$artifact = npcink_cloud_addon_upload_wordpress_ai_alt_text_source(
				$source['file'],
				$trace_id,
				'wp_ai_vision_upload_' . wp_generate_uuid4()
			);
			unset( $source['file'] );
			if ( is_wp_error( $artifact ) ) {
				return $artifact;
			}
			$artifact_id = is_array( $artifact ) && is_string( $artifact['artifact_id'] ?? null )
				? $artifact['artifact_id']
				: '';
			if ( 1 !== preg_match( '/^art_[0-9a-f]{32}$/', $artifact_id ) ) {
				return new WP_Error(
					'cloud_wp_ai_alt_text_artifact_invalid',
					__( 'Npcink Cloud did not return a valid source artifact for alt text generation.', 'npcink-cloud-addon' ),
					array( 'status' => 502 )
				);
			}

			$request = array(
				'contract_version'   => 'cloud_connector_runtime.v1',
				'profile_id'         => 'vision.ai',
				'operation_contract' => array(
					'contract_version' => 'wordpress_operation.v1',
					'task'             => 'alt_text_suggest',
					'request'          => array(
						'source_artifact_id' => $artifact_id,
						'prompt'             => $source['prompt'],
						'filename'           => $source['filename'],
						'title'              => $source['title'],
						'existing_alt'       => $source['existing_alt'],
						'existing_caption'   => $source['existing_caption'],
						'locale'             => $source['locale'],
					),
				),
				'timeout_seconds'    => 60,
				'retention_ttl'      => 86400,
				'retry_max'          => 0,
			);
			if ( is_array( $task_contract ) ) {
				$request['operation_contract']['request'] = array( 'task_contract' => $task_contract ) + $request['operation_contract']['request'];
			}

			return npcink_cloud_addon_execute_wordpress_ai_connector_runtime(
				$request,
				$trace_id,
				'wp_ai_vision_execute_' . wp_generate_uuid4()
			);
		}

		/**
		 * Validates and reads one local WordPress attachment without exposing its path.
		 *
		 * @param int    $attachment_id Attachment ID.
		 * @param string $prompt Alt-text prompt.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function local_source( int $attachment_id, string $prompt ) {
			$prompt = trim( $prompt );
			if ( 0 >= $attachment_id || '' === $prompt ) {
				return self::source_error( 'cloud_wp_ai_alt_text_input_invalid', 'WordPress AI alt text generation requires one bounded attachment prompt.' );
			}
			$prompt = self::bounded_text( $prompt, 500 );
			if ( ! current_user_can( 'edit_post', $attachment_id ) ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_forbidden', 'You are not allowed to use this attachment for Cloud alt text generation.', 403 );
			}

			$attachment = get_post( $attachment_id );
			if ( ! is_object( $attachment ) || 'attachment' !== (string) ( $attachment->post_type ?? '' ) ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_invalid', 'WordPress AI alt text generation requires a local media attachment.' );
			}

			$file_path  = get_attached_file( $attachment_id );
			$upload_dir = wp_upload_dir();
			$real_path  = is_string( $file_path ) ? realpath( $file_path ) : false;
			$real_base  = is_array( $upload_dir ) && is_string( $upload_dir['basedir'] ?? null ) ? realpath( $upload_dir['basedir'] ) : false;
			if (
				false === $real_path
				|| false === $real_base
				|| 0 !== strpos( $real_path, rtrim( $real_base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR )
				|| ! is_file( $real_path )
				|| ! is_readable( $real_path )
			) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_file_invalid', 'The local attachment file is unavailable for Cloud alt text generation.' );
			}

			$path_stat = @stat( $real_path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- A raced file removal fails closed below.
			$size      = is_array( $path_stat ) ? (int) ( $path_stat['size'] ?? 0 ) : 0;
			$is_regular_file = is_array( $path_stat )
				&& 0100000 === ( (int) ( $path_stat['mode'] ?? 0 ) & 0170000 );
			if ( ! $is_regular_file || 0 >= $size || $size > self::MAX_SOURCE_BYTES ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_size_invalid', 'The local attachment exceeds the Cloud alt text source size limit.', 413 );
			}

			$stored_mime = sanitize_mime_type( (string) get_post_mime_type( $attachment_id ) );

			$handle = @fopen( $real_path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.PHP.NoSilencedErrors.Discouraged -- Reads one locally authorized, size-bounded attachment; a raced removal fails closed below.
			if ( false === $handle ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_read_failed', 'The local attachment could not be read for Cloud alt text generation.' );
			}

			$handle_stat  = fstat( $handle );
			$stat_matches = is_array( $handle_stat );
			foreach ( array( 'dev', 'ino', 'mode', 'size' ) as $stat_field ) {
				if ( ! $stat_matches || (int) ( $path_stat[ $stat_field ] ?? -1 ) !== (int) ( $handle_stat[ $stat_field ] ?? -2 ) ) {
					$stat_matches = false;
					break;
				}
			}
			if ( ! $stat_matches ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the rejected local attachment handle immediately.
				fclose( $handle );
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_file_changed', 'The local attachment changed before it could be read for Cloud alt text generation.', 409 );
			}

			$contents    = '';
			$read_failed = false;
			while ( ! feof( $handle ) && strlen( $contents ) <= self::MAX_SOURCE_BYTES ) {
				$remaining = self::MAX_SOURCE_BYTES + 1 - strlen( $contents );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Bounded loop prevents partial reads and caps bytes at MAX+1.
				$chunk = fread( $handle, min( 8192, $remaining ) );
				if ( false === $chunk || ( '' === $chunk && ! feof( $handle ) ) ) {
					$read_failed = true;
					break;
				}
				$contents .= $chunk;
			}
			$reached_eof = feof( $handle );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closes the bounded local attachment handle immediately.
			fclose( $handle );
			if ( $read_failed || ! $reached_eof || '' === $contents || strlen( $contents ) !== $size ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_read_failed', 'The local attachment could not be read for Cloud alt text generation.' );
			}

			$image_info = @getimagesizefromstring( $contents ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid image bytes are expected to fail closed below.
			$detected_mime = is_array( $image_info ) ? sanitize_mime_type( (string) ( $image_info['mime'] ?? '' ) ) : '';
			if ( $stored_mime !== $detected_mime || ! in_array( $detected_mime, self::ALLOWED_MIME_TYPES, true ) ) {
				return self::source_error( 'cloud_wp_ai_alt_text_attachment_mime_invalid', 'The local attachment image type is not supported for Cloud alt text generation.' );
			}

			// Keep real editor images inside small vision models' context budgets.
			// The original attachment remains untouched; only the transient artifact
			// sent to Cloud is bounded to 768px on its longest edge.
			if ( is_array( $image_info ) && max( absint( $image_info[0] ?? 0 ), absint( $image_info[1] ?? 0 ) ) > 768 && function_exists( 'wp_get_image_editor' ) ) {
				$editor = wp_get_image_editor( $real_path );
				if ( ! is_wp_error( $editor ) && ! is_wp_error( $editor->resize( 768, 768, false ) ) ) {
					$temp_path = function_exists( 'wp_tempnam' ) ? wp_tempnam( $real_path ) : tempnam( sys_get_temp_dir(), 'npcink-alt-' );
					$saved = is_string( $temp_path ) && '' !== $temp_path ? $editor->save( $temp_path, $detected_mime ) : false;
					$saved_path = is_array( $saved ) ? (string) ( $saved['path'] ?? '' ) : '';
					if ( '' !== $saved_path && ! is_wp_error( $saved ) && is_readable( $saved_path ) ) {
						$bounded_contents = file_get_contents( $saved_path );
						$bounded_info = is_string( $bounded_contents ) ? @getimagesizefromstring( $bounded_contents ) : false;
						if ( is_array( $bounded_info ) && ( $bounded_info['mime'] ?? '' ) === $detected_mime && strlen( $bounded_contents ) <= self::MAX_SOURCE_BYTES ) {
							$contents = $bounded_contents;
						}
					}
					foreach ( array( $temp_path, $saved_path ) as $temporary_file ) {
						if ( is_string( $temporary_file ) && '' !== $temporary_file && is_file( $temporary_file ) ) {
							wp_delete_file( $temporary_file );
						}
					}
				}
			}

			return array(
				'file'             => array(
					'contents'  => $contents,
					'filename'  => self::bounded_filename( basename( $real_path ) ),
					'mime_type' => $detected_mime,
				),
				'prompt'           => $prompt,
				'filename'         => self::bounded_filename( basename( $real_path ) ),
				'title'            => self::bounded_text( sanitize_text_field( (string) ( $attachment->post_title ?? '' ) ), 160 ),
				'existing_alt'     => self::bounded_text( sanitize_text_field( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) ), 240 ),
				'existing_caption' => self::bounded_text( sanitize_text_field( (string) ( $attachment->post_excerpt ?? '' ) ), 240 ),
				'locale'           => self::bounded_text( function_exists( 'get_locale' ) ? sanitize_text_field( (string) get_locale() ) : '', 32 ),
			);
		}

		/** @return WP_Error */
		private static function source_error( string $code, string $message, int $status = 400 ): WP_Error {
			$translated_messages = array(
				'cloud_wp_ai_alt_text_attachment_required'     => __( 'WordPress AI alt text generation requires a local WordPress attachment.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_input_invalid'           => __( 'WordPress AI alt text generation requires one bounded attachment prompt.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_forbidden'    => __( 'You are not allowed to use this attachment for Cloud alt text generation.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_invalid'      => __( 'WordPress AI alt text generation requires a local media attachment.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_file_invalid' => __( 'The local attachment file is unavailable for Cloud alt text generation.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_size_invalid' => __( 'The local attachment exceeds the Cloud alt text source size limit.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_read_failed'  => __( 'The local attachment could not be read for Cloud alt text generation.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_file_changed' => __( 'The local attachment changed before it could be read for Cloud alt text generation.', 'npcink-cloud-addon' ),
				'cloud_wp_ai_alt_text_attachment_mime_invalid' => __( 'The local attachment image type is not supported for Cloud alt text generation.', 'npcink-cloud-addon' ),
			);

			return new WP_Error( $code, $translated_messages[ $code ] ?? $message, array( 'status' => $status ) );
		}

		/**
		 * Bounds a sanitized filename by truncating its stem while preserving its extension.
		 *
		 * @param string $filename Raw filename.
		 * @param int    $max_chars Maximum total characters.
		 * @return string
		 */
		private static function bounded_filename( string $filename, int $max_chars = 160 ): string {
			$sanitized = sanitize_file_name( $filename );
			if ( '' === $sanitized || strlen( $sanitized ) <= $max_chars ) {
				return $sanitized;
			}

			$stem      = $sanitized;
			$extension = '';
			$dot_position = strrpos( $sanitized, '.' );
			if ( false !== $dot_position && $dot_position > 0 ) {
				$stem      = substr( $sanitized, 0, $dot_position );
				$extension = substr( $sanitized, $dot_position );
			}

			if ( strlen( $extension ) >= $max_chars ) {
				// A pathological extension cannot fit beside any stem; bound the whole name.
				return self::byte_safe_cut( $sanitized, $max_chars );
			}

			return self::byte_safe_cut( $stem, $max_chars - strlen( $extension ) ) . $extension;
		}

		/**
		 * Cuts a UTF-8 string by bytes without splitting a multibyte codepoint.
		 *
		 * @param string $value Raw value.
		 * @param int    $max_bytes Maximum bytes.
		 * @return string
		 */
		private static function byte_safe_cut( string $value, int $max_bytes ): string {
			$max_bytes = max( 0, $max_bytes );
			if ( function_exists( 'mb_strcut' ) ) {
				return mb_strcut( $value, 0, $max_bytes, 'UTF-8' );
			}

			return substr( $value, 0, $max_bytes );
		}

		private static function bounded_text( string $value, int $max_length ): string {
			if ( 0 >= $max_length || '' === $value ) {
				return '';
			}
			if ( function_exists( 'mb_substr' ) ) {
				return mb_substr( $value, 0, $max_length );
			}

			$matches = array();
			$matched = preg_match( '/\A.{0,' . $max_length . '}/us', $value, $matches );

			return 1 === $matched && is_string( $matches[0] ?? null ) ? $matches[0] : '';
		}
	}
}
