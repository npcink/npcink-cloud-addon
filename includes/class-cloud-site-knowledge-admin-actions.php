<?php
/**
 * Site Knowledge administrator action results.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Site_Knowledge_Admin_Actions' ) ) {
	/**
	 * Maps administrator intent to the existing Site Knowledge change bridge.
	 */
	final class Npcink_Cloud_Site_Knowledge_Admin_Actions {
		/** Default bounded batch for background media recognition. */
		private const MEDIA_RECOGNITION_BATCH_SIZE = 10;
		/**
		 * Requests one bounded public-content refresh.
		 *
		 * @return array<string,mixed>
		 */
		public static function request_public_refresh(): array {
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return self::result( false, 'not_verified', __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ), 'refresh' );
			}

			Npcink_Cloud_Site_Knowledge_Change_Bridge::buffer_recent_public_content();
			$status = Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
			if ( empty( $status['last_delivery_ok'] ) ) {
				$message = sanitize_text_field( (string) ( $status['last_delivery_error'] ?? '' ) );
				return self::result(
					false,
					'refresh_failed',
					'' !== $message ? $message : __( 'Site Knowledge refresh request failed.', 'npcink-cloud-addon' ),
					'refresh',
					0,
					0,
					0,
					sanitize_key( (string) ( $status['last_error_code'] ?? '' ) )
				);
			}

			$sent_count = absint( $status['last_sent_count'] ?? 0 );
			return self::result(
				true,
				'refresh_requested',
				sprintf(
					/* translators: %d: sent public content count. */
					__( 'Site Knowledge refresh requested. Public content items sent: %d.', 'npcink-cloud-addon' ),
					$sent_count
				),
				'refresh',
				$sent_count
			);
		}

		/**
		 * Runs the existing Toolbox media-index batches through the local bridge.
		 *
		 * @return array<string,mixed>
		 */
		public static function request_media_index_refresh( int $page = 1, int $per_page = 0, string $upload_scope = '' ): array {
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return self::result( false, 'not_verified', __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ), 'media_refresh' );
			}
			if ( ! function_exists( 'apply_filters' ) || ! has_filter( 'npcink_toolbox_refresh_site_media_index_batch' ) ) {
				return self::result( false, 'toolbox_unavailable', __( 'Please enable Npcink Workflow Toolbox before refreshing the media index.', 'npcink-cloud-addon' ), 'media_refresh' );
			}

			$page = max( 1, min( 10000, $page ) );
			$per_page = $per_page > 0 ? max( 1, min( 10, $per_page ) ) : self::MEDIA_RECOGNITION_BATCH_SIZE;
			$started_at = microtime( true );
			$batch_input = array( 'page' => $page, 'per_page' => $per_page );
			$upload_scope = preg_replace( '/[^A-Za-z0-9._:-]/', '', $upload_scope );
			if ( is_string( $upload_scope ) && '' !== $upload_scope ) {
				$batch_input['upload_scope'] = substr( $upload_scope, 0, 96 );
			}
			$batch = apply_filters( 'npcink_toolbox_refresh_site_media_index_batch', null, $batch_input );
			if ( is_wp_error( $batch ) ) {
				$message = $batch->get_error_message();
				if ( false !== stripos( $message, 'exceeded max active cloud runs' ) ) {
					$message = __( 'Another Cloud task is already running. Please wait for it to finish, then try again.', 'npcink-cloud-addon' );
				}
				return self::result( false, 'media_refresh_failed', $message, 'media_refresh', 0, 0, 0, sanitize_key( (string) $batch->get_error_code() ) );
			}
			if ( ! is_array( $batch ) ) {
				return self::result( false, 'toolbox_unavailable', __( 'Npcink Workflow Toolbox could not start media recognition.', 'npcink-cloud-addon' ), 'media_refresh' );
			}

			$indexed = absint( $batch['indexed_items'] ?? 0 );
			$evidence = absint( $batch['visual_evidence_items'] ?? 0 );
			$reused = absint( $batch['visual_evidence_reused_items'] ?? 0 );
			$submitted = absint( $batch['visual_evidence_submitted_items'] ?? 0 );
			$recognized = absint( $batch['visual_evidence_recognized_items'] ?? 0 );
			$screened = absint( $batch['screened_items'] ?? 0 );
			$total = absint( $batch['total'] ?? 0 );
			$run_id = sanitize_text_field( (string) ( $batch['visual_evidence_run_id'] ?? '' ) );
			$has_more = ! empty( $batch['has_more'] );
			$message = '' !== $run_id
				? sprintf(
					/* translators: %d: image count in the accepted Cloud batch. */
					__( 'Cloud accepted a media recognition batch containing %d images.', 'npcink-cloud-addon' ),
					$submitted
				)
				: sprintf(
					/* translators: 1: indexed image count, 2: visual evidence count. */
					__( 'Media index refreshed: %1$d images recognized, %2$d with visual evidence.', 'npcink-cloud-addon' ),
					$indexed,
					$evidence
				);
			if ( $has_more ) {
				$message .= ' ' . __( 'More images remain. Background recognition will continue automatically; no further click is needed.', 'npcink-cloud-addon' );
				if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
					$message .= ' ' . __( 'WordPress scheduled tasks are disabled on this site, so automatic continuation requires a server cron job that runs wp-cron.php.', 'npcink-cloud-addon' );
				}
			}
			$result = self::result( true, 'media_refresh_requested', $message, 'media_refresh', $submitted, $evidence, $has_more ? 1 : 0 );
			$result['page_count'] = $indexed;
			$result['reused_count'] = $reused;
			$result['recognized_count'] = $recognized;
			$result['screened_count'] = $screened;
			$result['total'] = $total;
			$result['duration_seconds'] = round( max( 0, microtime( true ) - $started_at ), 1 );
			$result['run_id'] = $run_id;
			$result['page'] = $page;
			$result['per_page'] = $per_page;
			$result['has_more'] = $has_more;
			$result['next_page'] = $has_more ? $page + 1 : 0;
			return $result;
		}

		/**
		 * Requests a refresh for one currently public article.
		 *
		 * @param int $post_id Public WordPress post id.
		 * @return array<string,mixed>
		 */
		public static function request_article_refresh( int $post_id ): array {
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return self::result( false, 'not_verified', __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ), 'article_refresh' );
			}
			if ( $post_id < 1 ) {
				return self::result( false, 'invalid_article', __( 'Choose a valid published article to refresh.', 'npcink-cloud-addon' ), 'article_refresh' );
			}

			$result = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( $post_id );
			if ( is_wp_error( $result ) ) {
				return self::result( false, 'article_refresh_failed', $result->get_error_message(), 'article_refresh', 0, 0, 0, sanitize_key( (string) $result->get_error_code() ) );
			}

			return self::result(
				true,
				'article_refresh_requested',
				__( 'Article refresh requested. Check its index status again after Cloud finishes processing.', 'npcink-cloud-addon' ),
				'article_refresh',
				1
			);
		}

		/**
		 * Requests one administrator index operation.
		 *
		 * @param string $operation Sanitized operation slug.
		 * @param string $confirmation Sanitized confirmation value.
		 * @return array<string,mixed>
		 */
		public static function request_index_operation( string $operation, string $confirmation = '' ): array {
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return self::result( false, 'not_verified', __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ), $operation );
			}
			if ( ! in_array( $operation, array( 'start', 'rebuild', 'delete' ), true ) ) {
				return self::result( false, 'unsupported_operation', __( 'The requested Site Knowledge index action is not supported.', 'npcink-cloud-addon' ), $operation );
			}
			if ( in_array( $operation, array( 'rebuild', 'delete' ), true ) && strtoupper( $confirmation ) !== strtoupper( $operation ) ) {
				return self::result( false, 'confirmation_required', __( 'Type the confirmation word before running this Site Knowledge index action.', 'npcink-cloud-addon' ), $operation );
			}

			$status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( $operation );
			if ( is_wp_error( $status ) ) {
				return self::result( false, 'bridge_error', $status->get_error_message(), $operation, 0, 0, 0, sanitize_key( (string) $status->get_error_code() ) );
			}

			$selected = is_array( $status ) ? absint( $status['last_index_action_selected_count'] ?? 0 ) : 0;
			$batch_count = is_array( $status ) ? absint( $status['last_index_action_batch_count'] ?? 0 ) : 0;
			switch ( $operation ) {
				case 'start':
					$code = 'indexing_scheduled';
					$message = sprintf(
						/* translators: 1: public content item count, 2: bounded delivery batch count. */
						__( 'Site Knowledge indexing delivery scheduled: %1$d public content items in %2$d batches.', 'npcink-cloud-addon' ),
						$selected,
						$batch_count
					);
					break;
				case 'rebuild':
					$code = 'rebuild_scheduled';
					$message = sprintf(
						/* translators: 1: public content item count, 2: bounded delivery batch count. */
						__( 'Site Knowledge rebuild delivery scheduled: %1$d public content items in %2$d batches.', 'npcink-cloud-addon' ),
						$selected,
						$batch_count
					);
					break;
				case 'delete':
					$code = 'delete_requested';
					$message = __( 'Site Knowledge index deletion requested. WordPress content was not changed.', 'npcink-cloud-addon' );
					$selected = 0;
					$batch_count = 0;
					break;
				default:
					$code = 'index_action_requested';
					$message = __( 'Site Knowledge index action requested.', 'npcink-cloud-addon' );
			}

			return self::result( true, $code, $message, $operation, 0, $selected, $batch_count );
		}

		/**
		 * Returns the fixed administrator action result shape.
		 *
		 * @return array<string,mixed>
		 */
		private static function result( bool $ok, string $code, string $message, string $operation, int $sent_count = 0, int $selected_count = 0, int $batch_count = 0, string $source_error_code = '' ): array {
			return array(
				'ok' => $ok,
				'code' => $code,
				'message' => $message,
				'operation' => $operation,
				'sent_count' => max( 0, $sent_count ),
				'selected_count' => max( 0, $selected_count ),
				'batch_count' => max( 0, $batch_count ),
				'source_error_code' => $source_error_code,
			);
		}
	}
}
