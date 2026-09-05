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
