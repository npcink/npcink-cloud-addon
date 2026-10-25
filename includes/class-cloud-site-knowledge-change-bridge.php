<?php
/**
 * Site Knowledge public content change bridge.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Site_Knowledge_Change_Bridge' ) ) {
	/**
	 * Notifies Cloud Site Knowledge about bounded public content changes.
	 */
	final class Npcink_Cloud_Site_Knowledge_Change_Bridge {
		public const BUFFER_OPTION = 'npcink_cloud_addon_site_knowledge_change_buffer';
		public const STATUS_OPTION = 'npcink_cloud_addon_site_knowledge_change_status';
		public const MAINTENANCE_OPTION = 'npcink_cloud_addon_site_knowledge_maintenance_cursor';
		public const RECONCILIATION_OPTION = 'npcink_cloud_addon_site_knowledge_reconciliation_cursor';
		public const FLUSH_HOOK = 'npcink_cloud_addon_flush_site_knowledge_changes';
		public const RECONCILE_HOOK = 'npcink_cloud_addon_reconcile_site_knowledge_changes';

		private const STATUS_CONTRACT = 'site_knowledge_change_bridge_status.v1';
		private const HEALTH_DETAIL_CONTRACT = 'site_knowledge_bridge_health.v1';
		private const DEFAULT_POST_TYPES = array( 'post', 'page' );
		private const MAX_BUFFER_ITEMS = 500;
		private const MAX_BATCH_ITEMS = 25;
		private const DEBOUNCE_SECONDS = 180;
		private const RETRY_SECONDS = 300;
			private const MAX_DELIVERY_ATTEMPTS = 3;
			private const MAX_RUN_POLLS = 120;
			private const RECONCILE_POSTS = 50;
			private const MANUAL_INDEX_POSTS = 200;
			private const MANUAL_MAX_POSTS = 10000;
			private const MAX_DOCUMENT_CHARS = 1800;
		private const MAX_TAXONOMY_TERMS = 10;
		/** @var array<int,array{post_id:int,approved:bool}> */
		private static array $removed_comment_context = array();
		/** @var array<int,bool> */
		private static array $public_posts_pending_removal = array();
		/**
		 * Post documents memoized for the duration of one delivery run, so the
		 * pre-send fingerprint and the sent payload share one build. Cleared at
		 * delivery entry points and before post-send freshness verification.
		 *
		 * @var array<int,array<string,mixed>>
		 */
		private static array $post_document_memo = array();

		/**
		 * Registers content change hooks and delivery cron hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'transition_post_status', array( __CLASS__, 'handle_post_status_transition' ), 10, 3 );
			add_action( 'save_post', array( __CLASS__, 'handle_saved_post' ), 20, 2 );
			add_action( 'trashed_post', array( __CLASS__, 'handle_removed_post' ), 10, 1 );
			add_action( 'before_delete_post', array( __CLASS__, 'handle_removed_post' ), 10, 1 );
			add_action( 'transition_comment_status', array( __CLASS__, 'handle_comment_status_transition' ), 10, 3 );
			add_action( 'comment_post', array( __CLASS__, 'handle_comment_posted' ), 20, 3 );
			add_action( 'edit_comment', array( __CLASS__, 'handle_edited_comment' ), 20, 2 );
			add_action( 'trash_comment', array( __CLASS__, 'capture_comment_removal_context' ), 5, 1 );
			add_action( 'delete_comment', array( __CLASS__, 'capture_comment_removal_context' ), 5, 1 );
			add_action( 'trashed_comment', array( __CLASS__, 'handle_removed_comment' ), 10, 1 );
			add_action( 'deleted_comment', array( __CLASS__, 'handle_removed_comment' ), 10, 1 );
			add_action( self::FLUSH_HOOK, array( __CLASS__, 'flush_buffer' ) );
			add_action( self::RECONCILE_HOOK, array( __CLASS__, 'buffer_recent_public_content' ) );
			add_action( 'npcink_cloud_site_knowledge_status_refreshed', array( __CLASS__, 'maybe_schedule_automatic_rebuild' ) );

			add_action( 'add_attachment', array( __CLASS__, 'handle_attachment_change' ) );
			add_action( 'edit_attachment', array( __CLASS__, 'handle_attachment_change' ) );
			add_action( 'updated_post_meta', array( __CLASS__, 'handle_attachment_meta_change' ), 10, 3 );
			add_action( 'added_post_meta', array( __CLASS__, 'handle_attachment_meta_change' ), 10, 3 );
			self::sync_schedule();
		}

		/**
		 * Returns whether the bridge can deliver through verified Cloud settings.
		 *
		 * @return bool
		 */
		public static function is_enabled(): bool {
			return Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled();
		}

		/**
		 * Returns local bridge status for other plugins.
		 *
		 * @return array<string,mixed>
		 */
		public static function health_snapshot(): array {
			$buffer = self::get_buffer();
			$status = self::get_status();
			$maintenance = self::get_maintenance_cursor();
			$next_flush = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::FLUSH_HOOK ) : false;
			$next_reconcile = function_exists( 'wp_next_scheduled' ) ? wp_next_scheduled( self::RECONCILE_HOOK ) : false;
			$reconcile_overdue = false !== $next_reconcile && (int) $next_reconcile < ( time() - ( 2 * HOUR_IN_SECONDS ) );
			$configured = Npcink_Cloud_Addon_Settings::is_configured();
			$verified = Npcink_Cloud_Addon_Settings::is_verified();
			$delivery_enabled = self::is_enabled();
			$buffer_count = count( $buffer['post_ids'] );
			$last_success_at = ! empty( $status['last_delivery_ok'] ) ? sanitize_text_field( (string) ( $status['last_delivered_at'] ?? '' ) ) : '';
			$has_error = empty( $status['last_delivery_ok'] ) && ( '' !== (string) ( $status['last_delivery_error'] ?? '' ) || '' !== (string) ( $status['last_error_code'] ?? '' ) );
			$full_index_active = self::is_active_full_index_delivery( $maintenance );
			$bridge_status = ! $configured ? 'not_configured' : ( ! $verified ? 'unverified' : ( ! $delivery_enabled ? 'disabled' : ( $has_error ? 'error' : ( $buffer_count > 0 || $full_index_active ? 'queued' : 'idle' ) ) ) );

			return array(
				'owner' => 'cloud_addon',
				'mode' => 'site_knowledge_change_bridge',
				'status_contract' => self::STATUS_CONTRACT,
				'preferred_status_field' => 'change_bridge',
				'preferred_count_field' => 'buffer_count',
				'health_detail_version' => self::HEALTH_DETAIL_CONTRACT,
				'enabled' => $delivery_enabled,
				'delivery_enabled' => $delivery_enabled,
				'configured' => $configured,
				'verified' => $verified,
				'status' => $bridge_status,
				'buffer_count' => $buffer_count,
				'buffer_semantics' => 'bounded_delivery_buffer',
				'buffer_truth' => 'local_delivery_durability_only',
				'delivery_attempts' => absint( $buffer['attempts'] ?? 0 ),
				'max_buffer_items' => self::MAX_BUFFER_ITEMS,
				'batch_size' => self::MAX_BATCH_ITEMS,
				'max_delivery_attempts' => self::MAX_DELIVERY_ATTEMPTS,
				'max_run_polls' => self::MAX_RUN_POLLS,
				'last_delivery_ok' => ! empty( $status['last_delivery_ok'] ),
				'last_delivered_at' => sanitize_text_field( (string) ( $status['last_delivered_at'] ?? '' ) ),
				'last_delivery_at' => sanitize_text_field( (string) ( $status['last_delivered_at'] ?? '' ) ),
				'last_success_at' => $last_success_at,
				'last_delivery_error' => sanitize_text_field( (string) ( $status['last_delivery_error'] ?? '' ) ),
				'last_error_code' => sanitize_key( (string) ( $status['last_error_code'] ?? '' ) ),
				'last_error_at' => sanitize_text_field( (string) ( $status['last_error_at'] ?? '' ) ),
				'last_changed_at' => sanitize_text_field( (string) ( $status['last_changed_at'] ?? '' ) ),
				'last_post_id' => absint( $status['last_post_id'] ?? 0 ),
				'last_sent_count' => absint( $status['last_sent_count'] ?? 0 ),
					'total_sent' => absint( $status['total_sent'] ?? 0 ),
					'dropped_count' => absint( $status['dropped_count'] ?? 0 ),
					'last_dropped_at' => sanitize_text_field( (string) ( $status['last_dropped_at'] ?? '' ) ),
				'last_index_action' => sanitize_key( (string) ( $status['last_index_action'] ?? '' ) ),
				'last_index_action_at' => sanitize_text_field( (string) ( $status['last_index_action_at'] ?? '' ) ),
				'last_index_action_status' => sanitize_key( (string) ( $status['last_index_action_status'] ?? '' ) ),
				'last_index_action_selected_count' => absint( $status['last_index_action_selected_count'] ?? 0 ),
				'last_index_action_sent_count' => absint( $status['last_index_action_sent_count'] ?? 0 ),
				'last_index_action_batch_count' => absint( $status['last_index_action_batch_count'] ?? 0 ),
				'maintenance_status' => sanitize_key( (string) ( $maintenance['status'] ?? 'idle' ) ),
				'maintenance_operation' => sanitize_key( (string) ( $maintenance['operation'] ?? '' ) ),
				'maintenance_active' => $full_index_active,
				'maintenance_completed_batches' => absint( $maintenance['next_batch'] ?? 0 ),
				'maintenance_total_batches' => absint( $maintenance['batch_count'] ?? 0 ),
				'next_flush_at' => false === $next_flush ? '' : gmdate( 'c', (int) $next_flush ),
				'next_reconcile_at' => false === $next_reconcile ? '' : gmdate( 'c', (int) $next_reconcile ),
				'reconcile_overdue' => $reconcile_overdue,
				'last_reconciled_at' => sanitize_text_field( (string) ( $status['last_reconciled_at'] ?? '' ) ),
				'wp_cron_disabled' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
				'cron_command' => 'wp cron event run ' . self::FLUSH_HOOK,
				'wp_cli_command' => 'wp cron event run ' . self::FLUSH_HOOK,
				'write_posture' => 'suggestion_only',
				'cloud_runtime_contract' => 'site_knowledge_sync.v1',
				'transport_owner' => 'cloud_addon',
				'delivery_truth_owner' => 'cloud_addon',
				'index_execution_owner' => 'cloud_service',
				'index_lifecycle_owner' => 'cloud_service',
				'freshness_policy_owner' => 'cloud_service',
				'diagnostics_detail_owner' => 'cloud_service',
				'ownership' => array(
					'source_content_owner' => 'local_wordpress_host',
					'delivery_bridge_owner' => 'cloud_addon',
					'index_execution_owner' => 'cloud_service',
					'index_lifecycle_owner' => 'cloud_service',
					'freshness_policy_owner' => 'cloud_service',
					'diagnostics_detail_owner' => 'cloud_service',
					'vector_storage_owner' => 'cloud_service',
					'embedding_execution_owner' => 'cloud_service',
					'approval_owner' => 'local_wordpress_host',
					'final_write_owner' => 'local_wordpress_host',
					'wordpress_write_owner' => 'local_wordpress_host',
				),
				'truth_boundaries' => array(
					'cloud_is_index_truth' => true,
					'cloud_is_freshness_truth' => true,
					'cloud_is_diagnostics_truth' => true,
					'cloud_is_wordpress_control_plane' => false,
					'cloud_creates_wordpress_writes' => false,
					'cloud_owns_local_approval' => false,
					'cloud_owns_ability_registry' => false,
					'cloud_owns_workflow_registry' => false,
				),
				'legacy_toolbox_fallback' => false,
				'scheduler_truth' => false,
				'workflow_truth' => false,
				'wordpress_write_included' => false,
			);
		}

		/**
		 * Deletes addon-owned Site Knowledge bridge state.
		 *
		 * @return void
		 */
		public static function delete_data(): void {
			delete_option( self::BUFFER_OPTION );
			delete_option( self::STATUS_OPTION );
			if ( empty( self::delivery_state()['_lifecycle'] ) ) {
				delete_option( self::MAINTENANCE_OPTION );
			}
			delete_option( self::RECONCILIATION_OPTION );
			wp_clear_scheduled_hook( self::FLUSH_HOOK );
			wp_clear_scheduled_hook( self::RECONCILE_HOOK );
		}

		/**
		 * Handles post status changes.
		 *
		 * @param string $new_status New status.
		 * @param string $old_status Old status.
		 * @param mixed  $post Post object.
		 * @return void
		 */
		public static function handle_post_status_transition( string $new_status, string $old_status, $post ): void {
			$post_id = self::post_id_from_value( $post );
			$post_type = is_object( $post ) ? (string) ( $post->post_type ?? '' ) : '';
			$post_password = is_object( $post ) ? (string) ( $post->post_password ?? '' ) : '';
			$was_public = self::is_public_post( (object) array( 'ID' => $post_id, 'post_type' => $post_type, 'post_status' => $old_status, 'post_password' => $post_password ) );
			$is_public = self::is_public_post( (object) array( 'ID' => $post_id, 'post_type' => $post_type, 'post_status' => $new_status, 'post_password' => $post_password ) );
			if ( $was_public && ! $is_public ) {
				self::$public_posts_pending_removal[ $post_id ] = true;
			}
			if ( $post_id <= 0 ) {
				return;
			}

			if ( $was_public || $is_public ) {
				self::buffer_post_ids( array( $post_id ) );
			}
		}

		/**
		 * Handles saved public posts and pages.
		 *
		 * @param int   $post_id Post id.
		 * @param mixed $post Post object.
		 * @return void
		 */
		public static function handle_saved_post( int $post_id, $post ): void {
			if ( self::is_public_post( $post ) ) {
				self::buffer_post_ids( array( $post_id ) );
			}
		}

		/**
		 * Handles removed public posts and pages.
		 *
		 * @param int $post_id Post id.
		 * @return void
		 */
		public static function handle_removed_post( int $post_id ): void {
			$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
			if ( self::is_public_post( $post ) || ! empty( self::$public_posts_pending_removal[ $post_id ] ) ) {
				self::buffer_post_ids( array( $post_id ) );
			}
			unset( self::$public_posts_pending_removal[ $post_id ] );
		}

		/**
		 * Handles comment approval transitions.
		 *
		 * @param string $new_status New status.
		 * @param string $old_status Old status.
		 * @param mixed  $comment Comment object.
		 * @return void
		 */
		public static function handle_comment_status_transition( string $new_status, string $old_status, $comment ): void {
			$post_id = self::post_id_from_comment( $comment );
			if ( $post_id <= 0 ) {
				return;
			}

			if ( 'approved' === $new_status || 'approved' === $old_status || '1' === $new_status || '1' === $old_status ) {
				self::buffer_post_ids( array( $post_id ) );
			}
		}

		/**
		 * Handles newly posted approved comments.
		 *
		 * @param int        $comment_id Comment id.
		 * @param int|string $comment_approved Approval state.
		 * @param mixed      $commentdata Comment data.
		 * @return void
		 */
		public static function handle_comment_posted( int $comment_id, $comment_approved, $commentdata = null ): void {
			if ( 1 !== (int) $comment_approved ) {
				return;
			}

			$post_id = is_array( $commentdata ) ? absint( $commentdata['comment_post_ID'] ?? 0 ) : 0;
			if ( $post_id <= 0 && function_exists( 'get_comment' ) ) {
				$post_id = self::post_id_from_comment( get_comment( $comment_id ) );
			}

			if ( $post_id > 0 ) {
				self::buffer_post_ids( array( $post_id ) );
			}
		}

		/**
		 * Handles approved comment edits.
		 *
		 * @param int   $comment_id Comment id.
		 * @param mixed $data Comment data.
		 * @return void
		 */
		public static function handle_edited_comment( int $comment_id, $data = null ): void {
			$comment = function_exists( 'get_comment' ) ? get_comment( $comment_id ) : null;
			if ( ! is_object( $comment ) || ! in_array( (string) ( $comment->comment_approved ?? '' ), array( '1', 'approved' ), true ) ) {
				return;
			}
			$post_id = is_array( $data ) ? absint( $data['comment_post_ID'] ?? 0 ) : 0;
			if ( $post_id <= 0 ) {
				$post_id = self::post_id_from_comment( $comment );
			}

			if ( $post_id > 0 ) {
				self::buffer_post_ids( array( $post_id ) );
			}
		}

		/**
		 * Captures a comment's public state before trash or permanent deletion.
		 *
		 * @param int $comment_id Comment id.
		 * @return void
		 */
		public static function capture_comment_removal_context( int $comment_id ): void {
			$comment = function_exists( 'get_comment' ) ? get_comment( $comment_id ) : null;
			if ( is_object( $comment ) ) {
				self::$removed_comment_context[ $comment_id ] = array(
					'post_id' => self::post_id_from_comment( $comment ),
					'approved' => in_array( (string) ( $comment->comment_approved ?? '' ), array( '1', 'approved' ), true ),
				);
			}
		}

		/**
		 * Handles a removed comment using its pre-removal context.
		 *
		 * @param int $comment_id Comment id.
		 * @return void
		 */
		public static function handle_removed_comment( int $comment_id ): void {
			$context = self::$removed_comment_context[ $comment_id ] ?? array();
			if ( ! empty( $context['approved'] ) && absint( $context['post_id'] ?? 0 ) > 0 ) {
				self::buffer_post_ids( array( absint( $context['post_id'] ) ) );
			}
			unset( self::$removed_comment_context[ $comment_id ] );
		}

		/**
		 * Buffers recent public content for low-frequency reconciliation.
		 *
		 * @return void
		 */
		public static function buffer_recent_public_content(): void {
			if ( ! self::is_enabled() || ! function_exists( 'get_posts' ) ) {
				return;
			}
			if ( class_exists( 'Npcink_Cloud_Site_Knowledge_Runtime_Bridge' ) ) {
				Npcink_Cloud_Site_Knowledge_Runtime_Bridge::refresh_status_summary();
			}

			$cursor = self::get_reconciliation_cursor();
			$posts = self::query_reconciliation_posts( $cursor );
			if ( empty( $posts ) ) {
				update_option( self::STATUS_OPTION, array_merge( self::get_status(), array( 'last_reconciled_at' => gmdate( 'c' ) ) ), false );
				return;
			}

			$post_ids = array();
			$last_post = array();
			$buffered_ids = array_fill_keys( self::get_buffer()['post_ids'], true );
			$available = max( 0, self::MAX_BUFFER_ITEMS - count( $buffered_ids ) );
			foreach ( $posts as $post ) {
				if ( is_scalar( $post ) && function_exists( 'get_post' ) ) {
					$post = get_post( absint( $post ) );
				}
				$post_id = absint( is_object( $post ) ? ( $post->ID ?? 0 ) : ( $post['ID'] ?? 0 ) );
				$modified_gmt = sanitize_text_field( (string) ( is_object( $post ) ? ( $post->post_modified_gmt ?? '' ) : ( $post['post_modified_gmt'] ?? '' ) ) );
				if ( $post_id < 1 || '' === $modified_gmt ) {
					continue;
				}
				if ( $modified_gmt < (string) ( $cursor['modified_gmt'] ?? '' ) || ( $modified_gmt === (string) ( $cursor['modified_gmt'] ?? '' ) && $post_id <= absint( $cursor['post_id'] ?? 0 ) ) ) {
					continue;
				}
				// Stop at the first unretained row so the cursor cannot skip it.
				if ( ! isset( $buffered_ids[ $post_id ] ) ) {
					if ( $available < 1 ) {
						break;
					}
					$buffered_ids[ $post_id ] = true;
					--$available;
				}
				$post_ids[] = $post_id;
				$last_post = array( 'modified_gmt' => $modified_gmt, 'post_id' => $post_id );
			}

			if ( empty( $post_ids ) ) {
				self::schedule_flush( self::DEBOUNCE_SECONDS );
				return;
			}
			if ( ! self::buffer_post_ids( $post_ids, false ) || empty( $last_post ) ) {
				return;
			}

			update_option( self::RECONCILIATION_OPTION, $last_post, false );
			update_option( self::STATUS_OPTION, array_merge( self::get_status(), array( 'last_reconciled_at' => gmdate( 'c' ) ) ), false );
		}

		/**
		 * Sends one currently public post/page through the existing refresh transport.
		 *
		 * @param int $post_id Public WordPress post id.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function request_public_post_refresh( int $post_id ) {
			self::$post_document_memo = array();

			if ( ! self::is_enabled() ) {
				return new WP_Error( 'cloud_site_knowledge_delivery_disabled', __( 'Site Knowledge delivery is disabled locally.', 'npcink-cloud-addon' ) );
			}

			$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
			if ( ! self::is_public_post( $post ) ) {
				return new WP_Error( 'cloud_site_knowledge_article_not_public', __( 'Only published posts and pages can be refreshed in Site Knowledge.', 'npcink-cloud-addon' ) );
			}

			return self::request_site_knowledge_sync( 'refresh', array( $post_id ), 'article_refresh', array( $post_id ) );
		}

		/**
		 * Sends one bounded batch of changed public content to Cloud.
		 *
		 * @return array<string,mixed>
		 */
		public static function flush_buffer(): array {
			self::$post_document_memo = array();

			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return self::record_delivery_result( false, 0, __( 'Connect this site first.', 'npcink-cloud-addon' ), 'cloud_addon_unverified' );
			}

			if ( self::process_source_operations() ) {
				return self::get_status();
			}
			if ( ! self::is_enabled() ) {
				return self::record_delivery_result( false, 0, __( 'Site Knowledge delivery is disabled locally.', 'npcink-cloud-addon' ), 'cloud_site_knowledge_delivery_disabled' );
			}

			$maintenance_result = self::flush_full_index_delivery();
			if ( null !== $maintenance_result ) {
				return $maintenance_result;
			}

			$buffer = self::get_buffer();
			if ( empty( $buffer['post_ids'] ) ) {
				return self::record_delivery_result( true, 0, '' );
			}

			$post_ids = array_slice( $buffer['post_ids'], 0, self::MAX_BATCH_ITEMS );
			$sent_fingerprints = self::delivery_fingerprints( $post_ids );
			$result = self::request_site_knowledge_sync( 'refresh', $post_ids, 'change_bridge' );
			if ( is_wp_error( $result ) ) {
				return self::retry_or_drop_buffer( $buffer, $post_ids, $sent_fingerprints, $result->get_error_message() );
			}

			$latest_buffer = self::get_buffer();
			$remaining = self::remaining_after_delivery( $latest_buffer['post_ids'], $post_ids, $sent_fingerprints );
			self::save_buffer( $remaining, 0 );
			if ( ! empty( $remaining ) ) {
				self::schedule_flush( self::RETRY_SECONDS );
			}

			return self::record_delivery_result( true, count( $post_ids ), '' );
		}

		/**
		 * Queues one administrator-requested index operation for bounded delivery.
		 *
		 * @param string $operation Operation: start, rebuild, or delete.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function request_manual_index_operation( string $operation ) {
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return new WP_Error(
					'cloud_site_knowledge_unverified',
					__( 'Connect this site first.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$operation = sanitize_key( $operation );
			if ( ! in_array( $operation, array( 'start', 'rebuild', 'delete' ), true ) ) {
				return new WP_Error(
					'cloud_site_knowledge_index_action_not_allowed',
					__( 'The requested Site Knowledge index action is not supported.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			if ( 'delete' !== $operation && ! self::is_enabled() ) {
				return new WP_Error(
					'cloud_site_knowledge_delivery_disabled',
					__( 'Site Knowledge delivery is disabled locally. Enable delivery before starting or rebuilding the index.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$cursor = self::get_maintenance_cursor();
			if ( self::is_active_full_index_delivery( $cursor ) ) {
				return new WP_Error(
					'cloud_site_knowledge_delivery_in_progress',
					__( 'A Site Knowledge full-index delivery is already in progress.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}

			if ( 'delete' === $operation ) {
				$cursor = array(
					'status' => 'delivering',
					'request_id' => sanitize_key( wp_generate_uuid4() ),
					'operation' => 'delete',
					'operation_source' => 'admin_delete',
					'post_ids' => array(),
					'next_batch' => 0,
					'batch_count' => 0,
					'attempts' => 0,
					'created_at' => gmdate( 'c' ),
				);
				if ( ! self::claim_full_index_delivery_cursor( $cursor ) ) {
					return new WP_Error(
						'cloud_site_knowledge_delivery_in_progress',
						__( 'A Site Knowledge full-index delivery is already in progress.', 'npcink-cloud-addon' ),
						array( 'status' => 409 )
					);
				}
				$result = self::request_site_knowledge_sync( 'delete', array(), 'admin_delete' );
				$released = self::compare_and_swap_full_index_delivery_cursor( $cursor, null );
				if ( ! $released && self::get_maintenance_cursor() === $cursor ) {
					self::schedule_flush( 1 );
				}
				if ( is_wp_error( $result ) ) {
					self::record_manual_operation_result( $operation, false, 0, $result->get_error_message(), $result->get_error_code() );
					return $result;
				}

				return self::record_manual_operation_result( $operation, true, 0, '', '', 1 );
			}

			$post_ids = self::all_public_post_ids();
			$typed_batches = self::full_typed_batches( $post_ids );
			if ( empty( $typed_batches ) ) {
				return new WP_Error(
					'cloud_site_knowledge_no_public_content',
					__( 'No public posts or pages were found for Site Knowledge indexing.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$batch_count = max( 1, count( $typed_batches ) );
			$cursor = array(
				'status' => 'queued',
				'request_id' => sanitize_key( wp_generate_uuid4() ),
				'operation' => $operation,
				'operation_source' => 'admin_' . $operation,
				'post_ids' => $post_ids,
				'typed_batches' => $typed_batches,
				'next_batch' => 0,
				'batch_count' => $batch_count,
				'attempts' => 0,
				'created_at' => gmdate( 'c' ),
			);
			if ( ! self::claim_full_index_delivery_cursor( $cursor ) ) {
				return new WP_Error(
					'cloud_site_knowledge_delivery_in_progress',
					__( 'A Site Knowledge full-index delivery is already in progress.', 'npcink-cloud-addon' ),
					array( 'status' => 409 )
				);
			}
			self::schedule_flush( 1 );

			return self::record_manual_operation_queued( $operation, count( $post_ids ), $batch_count );
		}

		/**
		 * Re-schedules an active start/rebuild cursor after a scheduler warning.
		 *
		 * @return bool Whether an active content delivery was rescheduled.
		 */
		public static function reschedule_active_full_index_delivery(): bool {
			$cursor = self::get_maintenance_cursor();
			if ( ! self::is_active_full_index_delivery( $cursor ) || ! in_array( (string) ( $cursor['operation'] ?? '' ), array( 'start', 'rebuild' ), true ) ) {
				return false;
			}

			self::schedule_flush( 1 );

			return true;
		}

		/**
		 * Starts or resumes a Cloud-requested full public-content delivery.
		 *
		 * The cursor is delivery durability only. Cloud remains the index lifecycle
		 * truth and WordPress remains the public content source.
		 *
		 * @param array<string,mixed> $summary Bounded Cloud status summary.
		 * @return void
		 */
		public static function maybe_schedule_automatic_rebuild( array $summary ): void {
			if ( ! self::is_enabled() || ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return;
			}
			$maintenance = is_array( $summary['maintenance'] ?? null ) ? $summary['maintenance'] : array();
			$request_id = sanitize_key( (string) ( $maintenance['request_id'] ?? '' ) );
			$status = sanitize_key( (string) ( $maintenance['status'] ?? '' ) );
			if (
				'full_sync' !== (string) ( $maintenance['action'] ?? '' )
				|| empty( $maintenance['automatic'] )
				|| '' === $request_id
				|| ! in_array( $status, array( 'awaiting_site', 'delivering', 'blocked' ), true )
			) {
				return;
			}

			$cursor = self::get_maintenance_cursor();
			if ( $request_id === (string) ( $cursor['request_id'] ?? '' ) ) {
				if ( 'blocked' === (string) ( $cursor['status'] ?? '' ) ) {
					return;
				}
				self::schedule_flush( 1 );
				return;
			}
			if ( self::is_active_full_index_delivery( $cursor ) ) {
				return;
			}

			$post_ids = self::all_public_post_ids();
			$typed_batches = self::full_typed_batches( $post_ids );
			$batch_count = max( 1, count( $typed_batches ) );
			$cursor = array(
				'status' => 'queued',
				'request_id' => $request_id,
				'operation' => 'rebuild',
				'operation_source' => 'automatic_rebuild',
				'target_embedding_space_id' => sanitize_text_field( (string) ( $maintenance['target_embedding_space_id'] ?? '' ) ),
				'post_ids' => $post_ids,
				'typed_batches' => $typed_batches,
				'next_batch' => 0,
				'batch_count' => $batch_count,
				'attempts' => 0,
				'created_at' => gmdate( 'c' ),
			);
			if ( ! self::claim_full_index_delivery_cursor( $cursor ) ) {
				return;
			}
			self::schedule_flush( 1 );
		}

		/**
		 * Sends one bounded full-index batch when a delivery cursor is active.
		 *
		 * @return array<string,mixed>|null
		 */
		private static function flush_full_index_delivery(): ?array {
			$cursor = self::get_maintenance_cursor();
			$request_id = sanitize_key( (string) ( $cursor['request_id'] ?? '' ) );
			if ( '' === $request_id ) {
				return null;
			}
			$raw_operation = sanitize_key( (string) ( $cursor['operation'] ?? '' ) );
			$raw_operation_source = sanitize_key( (string) ( $cursor['operation_source'] ?? '' ) );
			if ( 'delete' === $raw_operation && 'admin_delete' === $raw_operation_source ) {
				if ( self::compare_and_swap_full_index_delivery_cursor( $cursor, null ) ) {
					return null;
				}
				if ( self::get_maintenance_cursor() !== $cursor ) {
					return self::get_status();
				}
				self::schedule_flush( self::RETRY_SECONDS );

				return self::record_delivery_result(
					false,
					0,
					__( 'Site Knowledge delete cleanup is waiting for a local retry.', 'npcink-cloud-addon' ),
					'delete_cursor_cleanup_retry_scheduled'
				);
			}
			if ( 'blocked' === sanitize_key( (string) ( $cursor['status'] ?? '' ) ) ) {
				return self::record_delivery_result(
					false,
					0,
					__( 'Site Knowledge full-index delivery is blocked after bounded retries.', 'npcink-cloud-addon' ),
					'full_index_delivery_blocked'
				);
			}

			$operation = sanitize_key( (string) ( $cursor['operation'] ?? 'rebuild' ) );
			$operation = in_array( $operation, array( 'start', 'rebuild' ), true ) ? $operation : 'rebuild';
			$operation_source = sanitize_key( (string) ( $cursor['operation_source'] ?? 'automatic_rebuild' ) );
			$operation_source = in_array( $operation_source, array( 'admin_start', 'admin_rebuild', 'automatic_rebuild' ), true )
				? $operation_source
				: 'automatic_rebuild';

			$post_ids = is_array( $cursor['post_ids'] ?? null )
				? array_values( array_filter( array_map( 'absint', $cursor['post_ids'] ) ) )
				: array();
			$batch_index = absint( $cursor['next_batch'] ?? 0 );
			$batch_count = max( 1, absint( $cursor['batch_count'] ?? 1 ) );
			if ( $batch_index >= $batch_count ) {
				$expected_cursor = $cursor;
				$cursor['status'] = 'completed';
				if ( ! self::compare_and_swap_full_index_delivery_cursor( $expected_cursor, null ) ) {
					return self::get_status();
				}
				self::schedule_buffer_after_full_index();
				// This recovery branch also finalizes a completed full-index
				// delivery, so it must clear dropped changes like the main path.
				self::clear_dropped_changes();
				$status = self::record_delivery_result( true, 0, '' );

				return self::record_manual_operation_progress( $cursor, 'completed', 0, $status );
			}

			$typed_batch = $cursor['typed_batches'][ $batch_index ] ?? array();
			$batch = empty( $typed_batch ) ? array_slice( $post_ids, $batch_index * self::MANUAL_INDEX_POSTS, self::MANUAL_INDEX_POSTS ) : array_column( $typed_batch, 'source_id' );
			$raw_pending_run_id = $cursor['pending_run_id'] ?? '';
			$pending_run_id = is_string( $raw_pending_run_id )
				? self::normalize_cloud_run_id( $raw_pending_run_id )
				: '';
			if ( '' !== $raw_pending_run_id && '' === $pending_run_id ) {
				return self::record_full_index_delivery_failure(
					$cursor,
					__( 'Cloud Site Knowledge run identifier is invalid.', 'npcink-cloud-addon' )
				);
			}
			if ( '' !== $pending_run_id ) {
				$client = new Npcink_Cloud_Runtime_Client();
				$run = $client->get_run( $pending_run_id, 'trace_site_knowledge_full_index_status_' . wp_generate_uuid4() );
				if ( is_wp_error( $run ) ) {
					return self::record_full_index_delivery_failure( $cursor, $run->get_error_message() );
				}
				$run_status = self::cloud_run_status( is_array( $run ) ? $run : array() );
				if ( in_array( $run_status, array( 'submitted', 'queued', 'running', 'processing', 'pending' ), true ) ) {
					$poll_generation = absint( $cursor['poll_generation'] ?? 0 ) + 1;
					if ( $poll_generation >= self::MAX_RUN_POLLS ) {
						return self::record_full_index_delivery_failure(
							$cursor,
							__( 'Cloud Site Knowledge run did not finish within the bounded polling window.', 'npcink-cloud-addon' )
						);
					}
					$expected_cursor = $cursor;
					$cursor['status'] = 'delivering';
					$cursor['poll_generation'] = $poll_generation;
					$cursor['last_polled_at'] = gmdate( 'c' );
					if ( ! self::compare_and_swap_full_index_delivery_cursor( $expected_cursor, $cursor ) ) {
						return self::get_status();
					}
					self::schedule_flush( 30 );
					$status = self::record_delivery_result( true, 0, '' );
					return self::record_manual_operation_progress( $cursor, 'delivering', 0, $status );
				}
				if ( ! in_array( $run_status, array( 'succeeded', 'success', 'completed' ), true ) ) {
					return self::record_full_index_delivery_failure(
						$cursor,
						__( 'Cloud Site Knowledge maintenance run failed.', 'npcink-cloud-addon' )
					);
				}

				return self::complete_full_index_delivery_batch( $cursor, count( $batch ) );
			}

			$is_first_rebuild_batch = 'rebuild' === $operation && 0 === $batch_index;
			$sync_mode = $is_first_rebuild_batch ? 'rebuild' : 'refresh';
			$is_final = $batch_index === $batch_count - 1;
			$maintenance = array();
			if ( 'automatic_rebuild' === $operation_source ) {
				$maintenance = array(
					'action' => 'full_sync',
					'request_id' => $request_id,
					'batch_index' => $batch_index,
					'batch_count' => $batch_count,
					'is_final' => $is_final,
				);
			}
			$result = self::request_site_knowledge_sync(
				$sync_mode,
				$is_first_rebuild_batch ? array() : $batch,
				$operation_source,
				$batch,
				'site_knowledge_' . ( 'automatic_rebuild' === $operation_source ? 'automatic' : $operation_source ) . '_' . $request_id . '_' . $batch_index,
				$maintenance,
				$typed_batch
			);
			if ( is_wp_error( $result ) ) {
				return self::record_full_index_delivery_failure( $cursor, $result->get_error_message() );
			}

			$result = is_array( $result ) ? $result : array();
			$run_id = self::cloud_run_id( $result );
			if ( self::has_cloud_run_id( $result ) && '' === $run_id ) {
				return self::record_full_index_delivery_failure(
					$cursor,
					__( 'Cloud Site Knowledge run identifier is invalid.', 'npcink-cloud-addon' )
				);
			}
			if ( '' !== $run_id ) {
				$expected_cursor = $cursor;
				$cursor['pending_run_id'] = $run_id;
				$cursor['status'] = 'delivering';
				$cursor['poll_generation'] = 0;
				unset( $cursor['last_polled_at'] );
				if ( ! self::compare_and_swap_full_index_delivery_cursor( $expected_cursor, $cursor ) ) {
					return self::get_status();
				}
				self::schedule_flush( 30 );
				$status = self::record_delivery_result( true, 0, '' );
				return self::record_manual_operation_progress( $cursor, 'delivering', 0, $status );
			}

			return self::complete_full_index_delivery_batch( $cursor, count( $batch ) );
		}

		/**
		 * Advances the local delivery cursor only after Cloud completed one run.
		 *
		 * @param array<string,mixed> $cursor Delivery cursor.
		 * @param int                 $sent Sent public document count.
		 * @return array<string,mixed>
		 */
		private static function complete_full_index_delivery_batch( array $cursor, int $sent ): array {
			$expected_cursor = $cursor;
			$batch_index = absint( $cursor['next_batch'] ?? 0 );
			$batch_count = max( 1, absint( $cursor['batch_count'] ?? 1 ) );
			$is_final = $batch_index === $batch_count - 1;
			$cursor['next_batch'] = $batch_index + 1;
			$cursor['attempts'] = 0;
			$cursor['status'] = $is_final ? 'completed' : 'delivering';
			unset( $cursor['pending_run_id'] );
			if ( $is_final ) {
				if ( ! self::compare_and_swap_full_index_delivery_cursor( $expected_cursor, null ) ) {
					return self::get_status();
				}
				self::schedule_buffer_after_full_index();
				// A completed full-index delivery has re-sent every public item,
				// so previously dropped changes no longer need an attention row.
				self::clear_dropped_changes();
			} else {
				if ( ! self::compare_and_swap_full_index_delivery_cursor( $expected_cursor, $cursor ) ) {
					return self::get_status();
				}
				self::schedule_flush( 1 );
			}

			$status = self::record_delivery_result( true, $sent, '' );

			return self::record_manual_operation_progress( $cursor, $is_final ? 'completed' : 'delivering', $sent, $status );
		}

		/**
		 * Records a bounded full-index delivery retry without dropping the cursor.
		 *
		 * @param array<string,mixed> $cursor Delivery cursor.
		 * @param string              $error Error message.
		 * @return array<string,mixed>
		 */
		private static function record_full_index_delivery_failure( array $cursor, string $error ): array {
			$expected_cursor = $cursor;
			$attempts = absint( $cursor['attempts'] ?? 0 ) + 1;
			$cursor['attempts'] = $attempts;
			$cursor['status'] = $attempts >= self::MAX_DELIVERY_ATTEMPTS ? 'blocked' : 'retrying';
			unset( $cursor['pending_run_id'] );
			if ( ! self::compare_and_swap_full_index_delivery_cursor( $expected_cursor, $cursor ) ) {
				return self::get_status();
			}
			if ( $attempts < self::MAX_DELIVERY_ATTEMPTS ) {
				self::schedule_flush( self::RETRY_SECONDS );
			}

			$status = self::record_delivery_result(
				false,
				0,
				$error,
				$attempts >= self::MAX_DELIVERY_ATTEMPTS ? 'full_index_delivery_blocked' : 'full_index_delivery_retry_scheduled'
			);

			return self::record_manual_operation_progress( $cursor, (string) $cursor['status'], 0, $status );
		}

			/**
			 * Reads a bounded Cloud run identifier from the standard envelope.
			 *
			 * @param array<string,mixed> $response Cloud response.
			 * @return string
			 */
			private static function cloud_run_id( array $response ): string {
				$data = is_array( $response['data'] ?? null ) ? $response['data'] : array();
				$value = array_key_exists( 'run_id', $data ) ? $data['run_id'] : ( $response['run_id'] ?? '' );

				return is_string( $value ) ? self::normalize_cloud_run_id( $value ) : '';
			}

			/**
			 * Determines whether the Cloud envelope explicitly contains a run id.
			 *
			 * @param array<string,mixed> $response Cloud response.
			 * @return bool
			 */
			private static function has_cloud_run_id( array $response ): bool {
				$data = is_array( $response['data'] ?? null ) ? $response['data'] : array();

				return array_key_exists( 'run_id', $data ) || array_key_exists( 'run_id', $response );
			}

			/**
			 * Normalizes a Cloud run id without changing its case.
			 *
			 * @param string $value Raw Cloud run id.
			 * @return string
			 */
			private static function normalize_cloud_run_id( string $value ): string {
				return 1 === preg_match( '/\A[A-Za-z0-9._:-]+\z/', $value ) ? $value : '';
			}

			/**
			 * Reads a bounded Cloud run status from the standard envelope.
			 *
			 * @param array<string,mixed> $response Cloud response.
			 * @return string
			 */
			private static function cloud_run_status( array $response ): string {
				$data = is_array( $response['data'] ?? null ) ? $response['data'] : array();
				$run = is_array( $data['run'] ?? null ) ? $data['run'] : array();

				return sanitize_key( (string) ( $run['status'] ?? $data['status'] ?? $response['status'] ?? '' ) );
			}

		/**
		 * Keeps reconcile cron aligned with Cloud configuration.
		 *
		 * @return void
		 */
			public static function sync_schedule(): void {
			if ( ! function_exists( 'wp_next_scheduled' ) ) {
				return;
			}

				if ( self::is_enabled() ) {
				$next_reconcile = wp_next_scheduled( self::RECONCILE_HOOK );
				if (
					false !== $next_reconcile
					&& function_exists( 'wp_get_schedule' )
					&& 'hourly' !== wp_get_schedule( self::RECONCILE_HOOK )
					&& function_exists( 'wp_clear_scheduled_hook' )
				) {
					wp_clear_scheduled_hook( self::RECONCILE_HOOK );
					$next_reconcile = false;
				}
					if ( false === $next_reconcile && function_exists( 'wp_schedule_event' ) ) {
						wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::RECONCILE_HOOK );
					}
					return;
			}

			self::initialize_delivery_coverage();
			if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( self::FLUSH_HOOK );
				wp_clear_scheduled_hook( self::RECONCILE_HOOK );
			}
		}

		/**
		 * Resumes paused delivery after an explicit local permission change.
		 *
		 * This is intentionally separate from bootstrap schedule synchronization so
		 * ordinary WordPress requests do not read two usually absent options.
		 *
		 * @return void
		 */
		public static function resume_pending_delivery(): void {
			if ( ! self::is_enabled() ) {
				return;
			}

			$cursor = self::get_maintenance_cursor();
			$buffer = self::get_buffer();
			if ( self::is_active_full_index_delivery( $cursor ) || ! empty( $buffer['post_ids'] ) ) {
				self::schedule_flush( 1 );
			}
		}

		/**
		 * Buffers changed post ids.
		 *
		 * @param array<int,int> $post_ids Post ids.
		 * @param bool $allow_eviction Whether live changes may replace older buffered ids.
		 * @return bool Whether every change was retained in the bounded buffer.
		 */
		private static function buffer_post_ids( array $post_ids, bool $allow_eviction = true ): bool {
			if ( ! self::is_enabled() ) {
				return false;
			}

			$clean = array_values( array_filter( array_map( 'absint', $post_ids ) ) );
			$sources = self::source_lifecycle_summary()['items'];
			$clean = array_values( array_filter( $clean, static function ( $id ) use ( $sources ) {
				$post = get_post( $id );
				$type = 'attachment' === ( $post->post_type ?? '' ) ? 'media' : ( $post->post_type ?? '' );
				return ! isset( $sources[ $type . ':' . $id ] );
			} ) );
			if ( empty( $clean ) ) {
				return true;
			}

			$buffer = self::get_buffer();
			$merged = array_values( array_unique( array_merge( $buffer['post_ids'], $clean ) ) );
			$dropped = 0;
			if ( count( $merged ) > self::MAX_BUFFER_ITEMS ) {
					// Re-read capacity here too: reconciliation must never evict live changes.
					if ( ! $allow_eviction ) {
						self::schedule_flush( self::DEBOUNCE_SECONDS );
						return false;
					}
					$dropped = count( $merged ) - self::MAX_BUFFER_ITEMS;
					$merged = array_slice( $merged, -1 * self::MAX_BUFFER_ITEMS );
					self::record_dropped_changes( $dropped );
			}
			if ( $merged === array_values( $buffer['post_ids'] ) ) {
				self::schedule_flush( self::DEBOUNCE_SECONDS );
				return true;
			}

			self::save_buffer( $merged, absint( $buffer['attempts'] ?? 0 ) );
			if ( ! $allow_eviction && ! empty( array_diff( $merged, self::get_buffer()['post_ids'] ) ) ) {
				return false;
			}
			update_option(
				self::STATUS_OPTION,
				array_merge(
					self::get_status(),
					array(
						'last_changed_at' => gmdate( 'c' ),
						'last_post_id' => (int) end( $clean ),
						'buffer_count' => count( $merged ),
					)
				),
				false
			);
			self::schedule_flush( self::DEBOUNCE_SECONDS );

			return $dropped < 1;
		}

		/**
		 * Reads the durable reconciliation cursor.
		 *
		 * @return array{modified_gmt:string,post_id:int}
		 */
		private static function get_reconciliation_cursor(): array {
			$cursor = get_option( self::RECONCILIATION_OPTION, array() );
			$cursor = is_array( $cursor ) ? $cursor : array();
			$modified_gmt = sanitize_text_field( (string) ( $cursor['modified_gmt'] ?? '' ) );

			return array(
				'modified_gmt' => $modified_gmt,
				'post_id' => '' === $modified_gmt ? 0 : absint( $cursor['post_id'] ?? 0 ),
			);
		}

		/**
		 * Returns one stable ascending reconciliation page.
		 *
		 * The SQL path preserves the `(modified_gmt, ID)` watermark across rows
		 * sharing the same WordPress modification second. The fallback keeps the
		 * lightweight behavior tests usable outside a loaded WordPress runtime.
		 *
		 * @param array{modified_gmt:string,post_id:int} $cursor Cursor.
		 * @return array<int,mixed>
		 */
		private static function query_reconciliation_posts( array $cursor ): array {
			global $wpdb;
			$modified_gmt = (string) ( $cursor['modified_gmt'] ?? '' );
			$post_id = absint( $cursor['post_id'] ?? 0 );
			$post_types = array_merge( self::post_types(), array( 'attachment' ) );

			if ( isset( $wpdb ) && is_object( $wpdb ) && method_exists( $wpdb, 'prepare' ) && method_exists( $wpdb, 'get_results' ) && ! empty( $post_types ) ) {
				$type_placeholders = implode( ', ', array_fill( 0, count( $post_types ), '%s' ) );
				$sql = "SELECT ID, post_modified_gmt FROM {$wpdb->posts} WHERE post_status IN (%s, %s) AND post_type IN ({$type_placeholders})";
				$params = array_merge( array( 'publish', 'inherit' ), $post_types );
				if ( '' !== $modified_gmt ) {
					$sql .= ' AND (post_modified_gmt > %s OR (post_modified_gmt = %s AND ID > %d))';
					$params = array_merge( $params, array( $modified_gmt, $modified_gmt, $post_id ) );
				}
				$sql .= ' ORDER BY post_modified_gmt ASC, ID ASC LIMIT %d';
				$params[] = self::RECONCILE_POSTS;
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- placeholders and values are assembled immediately above.
				$prepared = $wpdb->prepare( $sql, $params );
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- bounded reconciliation read uses the prepared cursor query above.
				$results = $wpdb->get_results( $prepared );

				return is_array( $results ) ? $results : array();
			}

			$args = array(
				'post_type' => $post_types,
				'post_status' => array( 'publish', 'inherit' ),
				'posts_per_page' => self::RECONCILE_POSTS,
				'orderby' => 'modified ID',
				'order' => 'ASC',
				'fields' => 'all',
				'no_found_rows' => true,
			);
			if ( '' !== $modified_gmt ) {
				$args['date_query'] = array( array( 'column' => 'post_modified_gmt', 'after' => $modified_gmt, 'inclusive' => true ) );
			}

			$results = get_posts( $args );

			return is_array( $results ) ? $results : array();
		}

		/**
		 * Requests a Cloud Site Knowledge refresh for changed posts.
		 *
			 * @param array<int,int>      $post_ids Cloud delete/refresh post ids.
			 * @param string              $operation_source Bounded delivery source.
			 * @param array<int,int>|null $document_post_ids Public documents to include.
			 * @param string              $idempotency_key Stable batch idempotency key.
		 * @return array<string,mixed>|WP_Error
		 */
			private static function request_site_knowledge_sync( string $sync_mode, array $post_ids, string $operation_source = 'change_bridge', ?array $document_post_ids = null, string $idempotency_key = '', array $maintenance = array(), array $typed_batch = array() ) {
				$sync_mode = sanitize_key( $sync_mode );
				if ( ! in_array( $sync_mode, array( 'refresh', 'rebuild', 'delete' ), true ) ) {
					return new WP_Error(
						'cloud_site_knowledge_sync_mode_not_allowed',
						__( 'Site Knowledge sync mode must be refresh, rebuild, or delete.', 'npcink-cloud-addon' ),
						array( 'status' => 400 )
					);
				}
				$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
				$document_post_ids = is_array( $document_post_ids ) ? $document_post_ids : $post_ids;
				$document_post_ids = array_values( array_unique( array_filter( array_map( 'absint', $document_post_ids ) ) ) );
				$media_ids = array_column( array_filter( $typed_batch, static function ( $item ) { return 'media' === $item['source_type']; } ), 'source_id' );
				$post_ids = array_values( array_filter( $post_ids, static function ( $id ) use ( $media_ids ) {
					return ! in_array( $id, $media_ids, true ) && 'attachment' !== ( get_post( $id )->post_type ?? '' );
				} ) );
				$limit    = 'change_bridge' === $operation_source ? self::MAX_BATCH_ITEMS : self::MANUAL_INDEX_POSTS;
				$documents = 'delete' === $sync_mode ? array() : self::collect_documents( $document_post_ids, $limit );
				$payload = array(
					'ability_name' => 'npcink-cloud/site-knowledge-sync',
					'contract_version' => 'site_knowledge_sync.v1',
					'execution_pattern' => 'whole_run_offload',
					'input' => array(
						'contract_version' => 'site_knowledge_sync.v1',
						'sync_mode' => $sync_mode,
						'operation_source' => sanitize_key( $operation_source ),
						'post_ids' => $post_ids,
						'max_posts' => $limit,
						'documents' => $documents,
						'write_posture' => 'suggestion_only',
						'direct_wordpress_write' => false,
					),
					'data_classification' => 'public_site_content',
					'storage_mode' => 'result_only',
					'retention_ttl' => DAY_IN_SECONDS,
					'timeout_seconds' => 60,
					'retry_max' => 1,
					'policy' => array(
						'allow_fallback' => true,
					),
				);
				$media = array();
				$sources = self::source_lifecycle_summary()['items'];
				foreach ( $document_post_ids as $id ) {
					if ( isset( $sources['media:' . $id] ) ) { continue; }
					$post = get_post( $id );
					if ( is_object( $post ) && 'attachment' === $post->post_type ) {
						$item = self::public_source( array( 'source_type' => 'media', 'source_id' => $id ) );
						if ( $item ) { $media[] = $item; }
					}
				}
				if ( $media ) {
					$payload['input']['media_items'] = $media;
					$payload['input']['post_ids'] = array_values( array_filter( $post_ids, static function ( $id ) { return 'attachment' !== ( get_post( $id )->post_type ?? '' ); } ) );
					if ( ! $documents ) {
						unset( $payload['input']['post_ids'], $payload['input']['documents'] );
					} elseif ( 'refresh' === $sync_mode ) {
						// A normal mixed buffer splits before dispatch, preserving pure-media replacement.
						$image_payload = $payload;
						unset( $image_payload['input']['post_ids'], $image_payload['input']['documents'] );
						$image_result = self::dispatch_mutation( $image_payload );
						if ( is_wp_error( $image_result ) ) { return $image_result; }
						unset( $payload['input']['media_items'] );
					}
				}
				if ( ! empty( $maintenance ) ) {
					$payload['input']['maintenance'] = $maintenance;
				}
				if ( '' === $idempotency_key && 'change_bridge' === $operation_source ) {
					$idempotency_key = self::change_batch_idempotency_key( $payload['input'] );
				}

				return self::dispatch_mutation( $payload, $idempotency_key );
			}

			/**
			 * Builds a stable key for one exact change-bridge payload.
			 *
			 * Content changes produce a new payload and therefore a new key, while an
			 * uncertain retry of the same payload remains safe to deduplicate in Cloud.
			 *
			 * @param array<string,mixed> $input Bounded Site Knowledge input.
			 * @return string
			 */
			private static function change_batch_idempotency_key( array $input ): string {
				$encoded = wp_json_encode( $input );
				if ( ! is_string( $encoded ) || '' === $encoded ) {
					return 'site_knowledge_change_' . wp_generate_uuid4();
				}

				return 'site_knowledge_change_' . substr( hash( 'sha256', $encoded ), 0, 32 );
			}

		/**
		 * Collects bounded public document manifests.
		 *
		 * @param array<int,int> $post_ids Post ids.
		 * @return array<int,array<string,mixed>>
		 */
			private static function collect_documents( array $post_ids, int $limit = self::MAX_BATCH_ITEMS ): array {
				if ( ! function_exists( 'get_post' ) ) {
					return array();
				}

				$documents = array();
				foreach ( array_slice( array_values( array_unique( array_map( 'absint', $post_ids ) ) ), 0, max( 1, $limit ) ) as $post_id ) {
					$post = get_post( $post_id );
					if ( ! self::is_public_post( $post ) || isset( self::source_lifecycle_summary()['items'][ $post->post_type . ':' . $post_id ] ) ) {
						continue;
				}

				$documents[] = self::post_document( $post );
			}

				return $documents;
			}

			/**
			 * Returns bounded public post/page ids for manual index operations.
			 *
			 * @return array<int,int>
			 */
			private static function all_public_post_ids(): array {
				if ( ! function_exists( 'get_posts' ) ) {
					return array();
				}

				$posts = get_posts(
					array(
						'post_type' => self::post_types(),
						'post_status' => 'publish',
						'posts_per_page' => self::MANUAL_MAX_POSTS,
						'orderby' => 'modified',
						'order' => 'DESC',
						'fields' => 'ids',
						'no_found_rows' => true,
					)
				);

				return is_array( $posts )
					? array_slice( array_values( array_unique( array_filter( array_map( 'absint', $posts ) ) ) ), 0, self::MANUAL_MAX_POSTS )
					: array();
			}

		/**
		 * Builds one public post/page manifest.
		 *
		 * @param mixed $post Post object.
		 * @return array<string,mixed>
		 */
		private static function post_document( $post ): array {
			$post_id = self::post_id_from_value( $post );
			if ( $post_id > 0 && isset( self::$post_document_memo[ $post_id ] ) ) {
				return self::$post_document_memo[ $post_id ];
			}

			$delivery_evidence = array();
			$content = self::bounded_text( (string) ( $post->post_content ?? '' ), self::MAX_DOCUMENT_CHARS, $delivery_evidence );

			$document = array(
				'id' => 'wp_post_' . $post_id,
				'source' => 'wordpress',
				'kind' => 'post',
				'post_id' => $post_id,
				'post_type' => sanitize_key( (string) ( $post->post_type ?? '' ) ),
				'post_status' => sanitize_key( (string) ( $post->post_status ?? '' ) ),
				'title' => self::post_title( $post ),
				'url' => self::post_url( $post_id ),
				'modified_gmt' => sanitize_text_field( (string) ( $post->post_modified_gmt ?? '' ) ),
				'excerpt' => self::bounded_text( (string) ( $post->post_excerpt ?? '' ), 300 ),
				'content_excerpt' => $content,
				'delivery_evidence' => $delivery_evidence,
				'taxonomies' => self::post_taxonomies( $post_id ),
			);

			if ( $post_id > 0 ) {
				self::$post_document_memo[ $post_id ] = $document;
			}

			return $document;
		}

		/**
		 * Collects bounded existing WordPress category and tag names for index metadata.
		 *
		 * @param int $post_id Post id.
		 * @return array<string,array<int,string>>
		 */
		private static function post_taxonomies( int $post_id ): array {
			$taxonomies = array(
				'category' => array(),
				'post_tag' => array(),
			);
			if ( ! function_exists( 'wp_get_post_terms' ) ) {
				return $taxonomies;
			}

			foreach ( array_keys( $taxonomies ) as $taxonomy ) {
				$terms = wp_get_post_terms( $post_id, $taxonomy, array( 'fields' => 'names' ) );
				if ( is_wp_error( $terms ) || ! is_array( $terms ) ) {
					continue;
				}
				$names = array();
				foreach ( $terms as $term_name ) {
					$name = self::bounded_text( (string) $term_name, 50 );
					if ( '' === $name || in_array( $name, $names, true ) ) {
						continue;
					}
					$names[] = $name;
					if ( count( $names ) >= self::MAX_TAXONOMY_TERMS ) {
						break;
					}
				}
				$taxonomies[ $taxonomy ] = $names;
			}

			return $taxonomies;
		}

		/**
		 * Captures the exact local document versions represented by one batch.
		 *
		 * @param array<int,int> $post_ids Post ids.
		 * @return array<int,string>
		 */
		private static function delivery_fingerprints( array $post_ids ): array {
			$fingerprints = array();
			foreach ( $post_ids as $post_id ) {
				$post_id = absint( $post_id );
				if ( $post_id > 0 ) {
					$fingerprints[ $post_id ] = self::delivery_fingerprint( $post_id );
				}
			}

			return $fingerprints;
		}

		/**
		 * Returns a non-secret fingerprint of the public document sent to Cloud.
		 *
		 * @param int $post_id Post id.
		 * @return string
		 */
		private static function delivery_fingerprint( int $post_id ): string {
			$post = function_exists( 'get_post' ) ? get_post( $post_id ) : null;
			if ( is_object( $post ) && 'attachment' === $post->post_type ) {
				// ALT and other delivered media metadata can change without a post revision.
				$document = self::public_source( array( 'source_type' => 'media', 'source_id' => $post_id ) ) ?? array( 'post_id' => $post_id, 'missing' => true );
			} else {
				$document = self::is_public_post( $post ) ? self::post_document( $post ) : array( 'post_id' => $post_id, 'missing' => true );
			}
			// Observational counters do not create a new semantic content revision.
			unset( $document['delivery_evidence'] );
			$encoded = wp_json_encode( $document );
			if ( ! is_string( $encoded ) || '' === $encoded ) {
				return 'unencodable_' . wp_generate_uuid4();
			}

			return hash( 'sha256', $encoded );
		}

		/**
		 * Removes only post versions that still match the delivered batch.
		 *
		 * @param array<int,int>    $buffered_post_ids Latest buffered post ids.
		 * @param array<int,int>    $attempted_post_ids Attempted batch ids.
		 * @param array<int,string> $sent_fingerprints Sent document fingerprints.
		 * @return array<int,int>
		 */
		private static function remaining_after_delivery( array $buffered_post_ids, array $attempted_post_ids, array $sent_fingerprints ): array {
			// The verification below must observe post state as of after the
			// remote delivery, not the memoized pre-send documents.
			self::$post_document_memo = array();

			$attempted_lookup = array_fill_keys( array_map( 'absint', $attempted_post_ids ), true );
			$remaining = array();
			foreach ( $buffered_post_ids as $post_id ) {
				$post_id = absint( $post_id );
				if ( $post_id <= 0 ) {
					continue;
				}
				if ( empty( $attempted_lookup[ $post_id ] ) ) {
					$remaining[] = $post_id;
					continue;
				}

				$sent_fingerprint = (string) ( $sent_fingerprints[ $post_id ] ?? '' );
				// A separate PHP writer cannot invalidate this request's object cache.
				if ( function_exists( 'wp_cache_delete' ) ) {
					wp_cache_delete( $post_id, 'posts' );
					wp_cache_delete( $post_id, 'post_meta' );
				}
				$current_fingerprint = self::delivery_fingerprint( $post_id );
				if ( '' === $sent_fingerprint || ! hash_equals( $sent_fingerprint, $current_fingerprint ) ) {
					$remaining[] = $post_id;
				}
			}

			return array_values( array_unique( $remaining ) );
		}

		/**
		 * Retries or drops the buffered batch after bounded delivery attempts.
		 *
		 * @param array<string,mixed> $buffer Buffer payload.
		 * @param array<int,int>      $attempted_post_ids Attempted batch ids.
		 * @param array<int,string>   $sent_fingerprints Sent document fingerprints.
		 * @param string              $error Error message.
		 * @return array<string,mixed>
		 */
		private static function retry_or_drop_buffer( array $buffer, array $attempted_post_ids, array $sent_fingerprints, string $error ): array {
			$latest_buffer = self::get_buffer();
			$attempts = max( absint( $buffer['attempts'] ?? 0 ), absint( $latest_buffer['attempts'] ?? 0 ) ) + 1;
			if ( $attempts >= self::MAX_DELIVERY_ATTEMPTS ) {
				$remaining = self::remaining_after_delivery( $latest_buffer['post_ids'], $attempted_post_ids, $sent_fingerprints );
				$dropped = max( 0, count( $latest_buffer['post_ids'] ) - count( $remaining ) );
				self::save_buffer( $remaining, 0 );
				self::record_dropped_changes( $dropped );
				if ( ! empty( $remaining ) ) {
					self::schedule_flush( self::RETRY_SECONDS );
				}
				return self::record_delivery_result( false, 0, $error, 'delivery_attempts_exhausted' );
			}

			self::save_buffer( $latest_buffer['post_ids'], $attempts );
			self::schedule_flush( self::RETRY_SECONDS );

			return self::record_delivery_result( false, 0, $error, 'delivery_failed_retry_scheduled' );
		}

		/**
		 * Schedules one flush, advancing an existing later event when needed.
		 *
		 * @param int $delay Delay in seconds.
		 * @return void
		 */
		private static function schedule_flush( int $delay ): void {
			if ( ! function_exists( 'wp_next_scheduled' ) || ! function_exists( 'wp_schedule_single_event' ) ) {
				return;
			}

			$timestamp = time() + max( 1, $delay );
			$next = wp_next_scheduled( self::FLUSH_HOOK );
			if ( false === $next ) {
				wp_schedule_single_event( $timestamp, self::FLUSH_HOOK );
				return;
			}

			if ( (int) $next > $timestamp && function_exists( 'wp_clear_scheduled_hook' ) ) {
				wp_clear_scheduled_hook( self::FLUSH_HOOK );
				$scheduled = wp_schedule_single_event( $timestamp, self::FLUSH_HOOK );
				if ( false === $scheduled || is_wp_error( $scheduled ) ) {
					wp_schedule_single_event( (int) $next, self::FLUSH_HOOK );
				}
			}
		}

		/**
		 * Records latest delivery result.
		 *
		 * @param bool   $ok Whether Cloud accepted the request.
		 * @param int    $sent Sent post count.
		 * @param string $error Error message.
		 * @return array<string,mixed>
		 */
		private static function record_delivery_result( bool $ok, int $sent, string $error, string $error_code = '' ): array {
			$buffer = self::get_buffer();
			$status = array_merge(
				self::get_status(),
				array(
					'last_delivery_ok' => $ok,
					'last_delivered_at' => $ok ? gmdate( 'c' ) : (string) ( self::get_status()['last_delivered_at'] ?? '' ),
					'last_delivery_error' => $ok ? '' : sanitize_text_field( $error ),
					'last_error_code' => $ok ? '' : sanitize_key( $error_code ),
					'last_error_at' => $ok ? '' : gmdate( 'c' ),
					'last_sent_count' => $ok ? max( 0, $sent ) : 0,
					'total_sent' => absint( self::get_status()['total_sent'] ?? 0 ) + ( $ok ? max( 0, $sent ) : 0 ),
					'buffer_count' => count( $buffer['post_ids'] ),
				)
			);
			update_option( self::STATUS_OPTION, $status, false );

			return $status;
		}

		/**
		 * Records bounded delivery changes that could no longer be retained.
		 *
		 * @param int $count Dropped change count.
		 * @return void
		 */
		private static function record_dropped_changes( int $count ): void {
			if ( $count < 1 ) {
				return;
			}

			$status = self::get_status();
			$status['dropped_count'] = absint( $status['dropped_count'] ?? 0 ) + $count;
			$status['last_dropped_at'] = gmdate( 'c' );
			update_option( self::STATUS_OPTION, $status, false );
		}

		/**
		 * Clears the dropped-change attention fact after a successful full-index
		 * delivery has re-sent all public content.
		 *
		 * @return void
		 */
		private static function clear_dropped_changes(): void {
			$status = self::get_status();
			if ( empty( $status['dropped_count'] ) && '' === (string) ( $status['last_dropped_at'] ?? '' ) ) {
				return;
			}

			$status['dropped_count'] = 0;
			$status['last_dropped_at'] = '';
			update_option( self::STATUS_OPTION, $status, false );
		}

		/**
		 * Records the latest administrator index action.
		 *
		 * @param string $operation Operation slug.
		 * @param bool   $ok Whether Cloud accepted the request.
		 * @param int    $sent Public post count sent.
		 * @param string $error Error message.
		 * @param string $error_code Error code.
		 * @param int    $batch_count Number of bounded Cloud delivery batches.
		 * @return array<string,mixed>
		 */
		private static function record_manual_operation_result( string $operation, bool $ok, int $sent, string $error, string $error_code = '', int $batch_count = 1 ): array {
			$status = self::record_delivery_result( $ok, $sent, $error, $error_code );
			$status = array_merge(
				$status,
				array(
					'last_index_action' => sanitize_key( $operation ),
					'last_index_action_at' => gmdate( 'c' ),
					'last_index_action_status' => $ok ? 'completed' : 'failed',
					'last_index_action_selected_count' => max( 0, $sent ),
					'last_index_action_sent_count' => max( 0, $sent ),
					'last_index_action_batch_count' => max( 0, $batch_count ),
				)
			);
			update_option( self::STATUS_OPTION, $status, false );

			return $status;
		}

		/**
		 * Records a manual full-index request without claiming delivery completed.
		 *
		 * @param string $operation Operation slug.
		 * @param int    $selected Public post count selected for delivery.
		 * @param int    $batch_count Number of bounded delivery batches.
		 * @return array<string,mixed>
		 */
		private static function record_manual_operation_queued( string $operation, int $selected, int $batch_count ): array {
			$status = array_merge(
				self::get_status(),
				array(
					'last_index_action' => sanitize_key( $operation ),
					'last_index_action_at' => gmdate( 'c' ),
					'last_index_action_status' => 'queued',
					'last_index_action_selected_count' => max( 0, $selected ),
					'last_index_action_sent_count' => 0,
					'last_index_action_batch_count' => max( 1, $batch_count ),
				)
			);
			update_option( self::STATUS_OPTION, $status, false );

			return $status;
		}

		/**
		 * Updates shallow administrator progress for a manual delivery cursor.
		 *
		 * @param array<string,mixed> $cursor Delivery cursor.
		 * @param string              $state Progress state.
		 * @param int                 $sent_delta Newly completed public documents.
		 * @param array<string,mixed> $status Latest delivery status.
		 * @return array<string,mixed>
		 */
		private static function record_manual_operation_progress( array $cursor, string $state, int $sent_delta, array $status ): array {
			$operation_source = sanitize_key( (string) ( $cursor['operation_source'] ?? '' ) );
			if ( ! in_array( $operation_source, array( 'admin_start', 'admin_rebuild' ), true ) ) {
				return $status;
			}

			$operation = sanitize_key( (string) ( $cursor['operation'] ?? '' ) );
			$status = array_merge(
				$status,
				array(
					'last_index_action' => in_array( $operation, array( 'start', 'rebuild' ), true ) ? $operation : 'rebuild',
					'last_index_action_status' => sanitize_key( $state ),
					'last_index_action_selected_count' => count( (array) ( $cursor['post_ids'] ?? array() ) ),
					'last_index_action_sent_count' => $sent_delta > 0
						? min(
							count( (array) ( $cursor['post_ids'] ?? array() ) ),
							absint( $cursor['next_batch'] ?? 0 ) * self::MANUAL_INDEX_POSTS
						)
						: absint( $status['last_index_action_sent_count'] ?? 0 ),
					'last_index_action_batch_count' => max( 1, absint( $cursor['batch_count'] ?? 1 ) ),
				)
			);
			update_option( self::STATUS_OPTION, $status, false );

			return $status;
		}

		/**
		 * Resumes ordinary change delivery after a full-index cursor completes.
		 *
		 * @return void
		 */
		private static function schedule_buffer_after_full_index(): void {
			$buffer = self::get_buffer();
			if ( ! empty( $buffer['post_ids'] ) ) {
				self::schedule_flush( 1 );
			}
		}

		/**
		 * Returns buffered post ids and attempts.
		 *
		 * @return array<string,mixed>
		 */
		private static function get_buffer(): array {
			$value = get_option( self::BUFFER_OPTION, array() );
			$value = is_array( $value ) ? $value : array();
			$post_ids = isset( $value['post_ids'] ) && is_array( $value['post_ids'] ) ? $value['post_ids'] : $value;

			return array(
				'post_ids' => array_values( array_filter( array_map( 'absint', is_array( $post_ids ) ? $post_ids : array() ) ) ),
				'attempts' => absint( $value['attempts'] ?? 0 ),
			);
		}

		/**
		 * Saves the local delivery buffer.
		 *
		 * @param array<int,int> $post_ids Post ids.
		 * @param int            $attempts Delivery attempts.
		 * @return void
		 */
		private static function save_buffer( array $post_ids, int $attempts ): void {
			$post_ids = array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) );
			if ( empty( $post_ids ) ) {
				delete_option( self::BUFFER_OPTION );
				return;
			}

			update_option(
				self::BUFFER_OPTION,
				array(
					'post_ids' => $post_ids,
					'attempts' => max( 0, $attempts ),
					'updated_at' => gmdate( 'c' ),
				),
				false
			);
		}

		/**
		 * Returns raw status.
		 *
		 * @return array<string,mixed>
		 */
		private static function get_status(): array {
			$status = get_option( self::STATUS_OPTION, array() );

			return is_array( $status ) ? $status : array();
		}

		/**
		 * Replaces only the matching legacy full cursor, retaining durable typed records.
		 *
		 * @return bool
		 */
		private static function compare_and_swap_full_index_delivery_cursor( array $expected, ?array $replacement ): bool {
			$current = self::delivery_state();
			$full = $current;
			unset( $full['_lifecycle'] );
			if ( $full !== $expected ) {
				return false;
			}
			$next = $replacement ?? array();
			if ( isset( $current['_lifecycle'] ) ) {
				$next['_lifecycle'] = $current['_lifecycle'];
			}
			return self::compare_and_swap_delivery_state( $current, empty( $next ) ? null : $next );
		}

		/** One durable envelope shares the existing maintenance option and SQL CAS. */
		private static function delivery_state(): array {
			$value = get_option( self::MAINTENANCE_OPTION, array() );
			return is_array( $value ) ? $value : array();
		}

		public static function lifecycle_scope(): string {
			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			return hash( 'sha256', implode( '|', array( (string) ( $settings['base_url'] ?? '' ), (string) ( $settings['site_id'] ?? '' ), (string) ( $settings['key_id'] ?? '' ) ) ) );
		}

		/** Strict typed identities, bounded BEFORE deduplication; never absint(). */
		public static function normalize_source_selection( $items ) {
			if ( ! is_array( $items ) || array_values( $items ) !== $items || count( $items ) < 1 || count( $items ) > 100 ) {
				return self::lifecycle_error( 'selection_invalid' );
			}
			$normalized = array();
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) || count( $item ) !== 2 || ! isset( $item['source_type'], $item['source_id'] ) || ! in_array( $item['source_type'], array( 'post', 'page', 'media' ), true ) || ! is_int( $item['source_id'] ) || $item['source_id'] < 1 ) {
					return self::lifecycle_error( 'selection_invalid' );
				}
				$normalized[ $item['source_type'] . ':' . $item['source_id'] ] = array( 'source_type' => $item['source_type'], 'source_id' => $item['source_id'] );
			}
			ksort( $normalized );
			return array_values( $normalized );
		}

		private static function lifecycle_error( string $code ): WP_Error {
			return new WP_Error( 'cloud_site_knowledge_' . $code, __( 'Site Knowledge needs verification before this request can continue.', 'npcink-cloud-addon' ), array( 'status' => 409 ) );
		}

		/** Metadata only; never exposes retained public payloads or credentials. */
		public static function source_lifecycle_summary(): array {
			$state = self::delivery_state();
			$scope = $state['_lifecycle']['scopes'][ self::lifecycle_scope() ] ?? array();
			return array( 'items' => $scope['sources'] ?? array(), 'operations' => array_map( static function ( $op ) { return array_intersect_key( $op, array_flip( array( 'id', 'action', 'status', 'reason', 'polls' ) ) ); }, $scope['operations'] ?? array() ) );
		}

		private static function source_key( array $item ): string {
			return $item['source_type'] . ':' . $item['source_id'];
		}

		/** Pending intent may coexist with an HTTP owner; it never steals that owner. */
		public static function request_source_operation( string $action, $selection ) {
			$items = self::normalize_source_selection( $selection );
			if ( is_wp_error( $items ) || ! in_array( $action, array( 'exclude', 'restore' ), true ) ) {
				return is_wp_error( $items ) ? $items : self::lifecycle_error( 'action_invalid' );
			}
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() || ( 'restore' === $action && ! self::is_enabled() ) ) {
				return self::lifecycle_error( 'not_verified' );
			}
			foreach ( $items as $item ) {
				if ( ! self::public_source( $item ) ) {
					return self::lifecycle_error( 'source_not_public' );
				}
			}
			$before = self::delivery_state();
			$next = $before;
			$namespace = self::lifecycle_scope();
			$scope = $next['_lifecycle']['scopes'][ $namespace ] ?? array( 'sources' => array(), 'operations' => array(), 'dispatches' => array(), 'legacy_unknown' => empty( $before['_lifecycle']['coverage_started_without_credentials'] ) );
			// Completed intents may retire; pending/blocked identities never age out.
			foreach ( $scope['operations'] as $operation_id => $completed ) {
				if ( 'completed' !== $completed['status'] ) { continue; }
				unset( $scope['operations'][ $operation_id ] );
				foreach ( $scope['dispatches'] as $dispatch_key => $entry ) {
					if ( $operation_id === ( $entry['operation'] ?? '' ) && 'producer' !== $entry['purpose'] ) { unset( $scope['dispatches'][ $dispatch_key ] ); }
				}
			}
			$added_sources = count( array_filter( $items, static function ( $item ) use ( $scope ) { return ! isset( $scope['sources'][ self::source_key( $item ) ] ); } ) );
			if ( count( $scope['operations'] ) >= 100 || count( $scope['sources'] ) + $added_sources > 500 || count( $next['_lifecycle']['scopes'] ?? array() ) >= 8 && ! isset( $next['_lifecycle']['scopes'][ $namespace ] ) ) {
				return self::lifecycle_error( 'durability_full' );
			}
			foreach ( $items as $item ) {
				$prior = $scope['sources'][ self::source_key( $item ) ]['state'] ?? '';
				if ( ( 'restore' === $action && 'excluded' !== $prior ) || ( 'exclude' === $action && '' !== $prior ) ) {
					return self::lifecycle_error( 'source_operation_overlap' );
				}
			}
			$id = sanitize_key( wp_generate_uuid4() );
			$set_hash = hash( 'sha256', (string) wp_json_encode( $items ) );
			$legacy = self::get_maintenance_cursor();
			if ( 'restore' === $action && ! empty( $legacy ) ) { return self::lifecycle_error( 'full_delivery_unresolved' ); }
			$old = array_keys( array_filter( $scope['dispatches'], static function ( $entry ) { return 'producer' === ( $entry['purpose'] ?? '' ); } ) );
			$op = array( 'id' => $id, 'action' => $action, 'items' => $items, 'set_hash' => $set_hash, 'status' => 'pending', 'polls' => 0, 'old' => $old, 'legacy_unknown' => ( ! empty( $legacy ) && ! isset( $legacy['typed_batches'] ) ) || ! empty( $scope['legacy_unknown'] ) );
			// Pre-upgrade cursor dispatches cannot be reconstructed from an empty buffer.
			// Preserve the exact old cursor rather than asserting its worker is gone.
			if ( ! empty( $legacy ) ) {
				$op['legacy_cursor'] = $legacy;
			}
			$op['restores'] = 'restore' === $action ? array_values( array_unique( array_column( array_intersect_key( $scope['sources'], array_flip( array_map( array( self::class, 'source_key' ), $items ) ) ), 'operation' ) ) ) : array();
			foreach ( $items as $item ) {
				$scope['sources'][ self::source_key( $item ) ] = array( 'source_type' => $item['source_type'], 'source_id' => $item['source_id'], 'state' => 'restore' === $action ? 'restore_pending' : 'pending_delete', 'operation' => $id );
			}
			$scope['operations'][ $id ] = $op;
			$next['_lifecycle']['scopes'][ $namespace ] = $scope;
			if ( strlen( serialize( $next ) ) > 4194304 ) { return self::lifecycle_error( 'durability_full' ); }
			if ( ! self::compare_and_swap_delivery_state( $before, $next ) ) {
				return self::lifecycle_error( 'owner_changed' );
			}
			self::schedule_flush( 1 );
			return array( 'operation_id' => $id, 'status' => 'pending', 'selected_count' => count( $items ) );
		}

		/** Every mutation path registers immutable payload/key BEFORE HTTP. */
		public static function dispatch_mutation( array $payload, string $key = '', string $purpose = 'producer', string $operation = '' ) {
			self::retire_terminal_deliveries();
			$before = self::delivery_state();
			$namespace = self::lifecycle_scope();
			$scope = $before['_lifecycle']['scopes'][ $namespace ] ?? array( 'sources' => array(), 'operations' => array(), 'dispatches' => array(), 'legacy_unknown' => empty( $before['_lifecycle']['coverage_started_without_credentials'] ) );
			if ( ! empty( $before['_lifecycle']['owner'] ) ) {
				return self::lifecycle_error( 'dispatch_owned' );
			}
			if ( ! isset( $before['_lifecycle']['scopes'][ $namespace ] ) && count( $before['_lifecycle']['scopes'] ?? array() ) >= 8 ) { return self::lifecycle_error( 'durability_full' ); }
			if ( 'producer' === $purpose ) {
				$payload = self::suppress_payload( $payload, $scope['sources'] );
				if ( is_wp_error( $payload ) ) {
					return $payload;
				}
			} elseif ( ! isset( $scope['operations'][ $operation ] ) ) {
				return self::lifecycle_error( 'operation_missing' );
			}
			$encoded = wp_json_encode( $payload );
			if ( ! is_string( $encoded ) || '' === $encoded || strlen( $encoded ) > 900000 ) {
				return self::lifecycle_error( 'payload_invalid' );
			}
			$fingerprint = hash( 'sha256', $encoded );
			$key = '' !== $key ? $key : 'site_knowledge_delivery_' . hash( 'sha256', $namespace . '|' . ( $scope['producer_generation'] ?? '' ) . '|' . $fingerprint );
			if ( 'producer' === $purpose && ! empty( $scope['producer_generation'] ) && 0 === strpos( $key, 'site_knowledge_change_' ) ) {
				$key = 'site_knowledge_change_' . hash( 'sha256', $scope['producer_generation'] . '|' . $key );
			}
			if ( ! preg_match( '/\A[A-Za-z0-9._:-]{1,200}\z/', $key ) ) {
				return self::lifecycle_error( 'key_invalid' );
			}
			$entry = $scope['dispatches'][ $key ] ?? array();
			if ( ! empty( $entry ) && $entry['fingerprint'] !== $fingerprint ) {
				return self::lifecycle_error( 'payload_changed' );
			}
			if ( empty( $entry ) && count( $scope['dispatches'] ) >= 500 ) {
				return self::lifecycle_error( 'durability_full' );
			}
			if ( ! empty( $entry['run_id'] ) ) {
				return array( 'data' => array( 'run_id' => $entry['run_id'], 'status' => 'accepted' ) );
			}
			$token = wp_generate_uuid4();
			$next = $before;
			$scope['dispatches'][ $key ] = array_merge( $entry, array( 'payload' => $payload, 'fingerprint' => $fingerprint, 'key' => $key, 'purpose' => $entry['purpose'] ?? $purpose, 'operation' => $entry['operation'] ?? $operation, 'send_state' => 'sending' ) );
			$next['_lifecycle']['scopes'][ $namespace ] = $scope;
			$next['_lifecycle']['owner'] = array( 'token' => $token, 'scope' => $namespace, 'key' => $key );
			if ( strlen( serialize( $next ) ) > 4194304 ) { return self::lifecycle_error( 'durability_full' ); }
			if ( ! self::compare_and_swap_delivery_state( $before, $next ) ) {
				return self::lifecycle_error( 'owner_changed' );
			}
			// This CAS is the dispatch linearization point: a concurrent intent can
			// retain this writer, but cannot erase it or authorize a new send owner.
			if ( self::delivery_state() !== $next ) {
				self::release_unsent_owner( $token, $namespace, $key );
				return self::lifecycle_error( 'owner_changed' );
			}
			$client = new Npcink_Cloud_Runtime_Client();
			$result = $client->execute_runtime( $payload, 'trace_site_knowledge_delivery_' . wp_generate_uuid4(), $key );
			$current = self::delivery_state();
			if ( ( $current['_lifecycle']['owner']['token'] ?? '' ) === $token ) {
				$after = $current;
				$record = &$after['_lifecycle']['scopes'][ $namespace ]['dispatches'][ $key ];
				$record['send_state'] = 'uncertain';
				if ( is_array( $result ) ) {
					$run_id = self::cloud_run_id( $result );
					if ( '' !== $run_id ) {
						$record['run_id'] = $run_id;
						$record['send_state'] = 'accepted';
					}
				}
				unset( $record, $after['_lifecycle']['owner'] );
				// Concurrent pending writes may win this CAS. Never discard the owner
				// on a failed persistence attempt; it remains visibly blocked.
				self::compare_and_swap_delivery_state( $current, $after );
			}
			return $result;
		}

		/** At pressure only, retire up to eight positively fenced unreferenced producers. */
		private static function retire_terminal_deliveries(): void {
			$before = self::delivery_state();
			$namespace = self::lifecycle_scope();
			$scope = $before['_lifecycle']['scopes'][ $namespace ] ?? array();
			if ( empty( $scope['dispatches'] ) || ! empty( $before['_lifecycle']['owner'] ) || ( count( $scope['dispatches'] ) < 400 && strlen( serialize( $before ) ) < 2097152 ) ) { return; }
			$full_request = (string) ( self::get_maintenance_cursor()['request_id'] ?? '' );
			$protected = array();
			foreach ( $scope['operations'] as $op ) {
				if ( 'completed' !== $op['status'] ) { $protected = array_merge( $protected, $op['old'] ); }
			}
			$keys = array_keys( $scope['dispatches'] );
			$start = (int) ( $scope['retire_cursor'] ?? 0 ) % count( $keys );
			$checked = 0;
			for ( $offset = 0; $offset < count( $keys ) && $checked < 8; $offset++ ) {
				$key = $keys[ ( $start + $offset ) % count( $keys ) ];
				$entry = $scope['dispatches'][ $key ];
				if ( 'producer' !== $entry['purpose'] || in_array( $key, $protected, true ) || ( '' !== $full_request && false !== strpos( $key, '_' . $full_request . '_' ) ) ) { continue; }
				$checked++;
				if ( 'not_sent' === ( $entry['send_state'] ?? '' ) || ! is_wp_error( Npcink_Cloud_Site_Knowledge_Runtime_Bridge::read_writer_witness( $entry ) ) ) { unset( $scope['dispatches'][ $key ] ); }
			}
			$scope['retire_cursor'] = ( $start + $offset ) % count( $keys );
			$next = $before;
			$next['_lifecycle']['scopes'][ $namespace ] = $scope;
			self::compare_and_swap_delivery_state( $before, $next );
		}

		private static function release_unsent_owner( string $token, string $namespace, string $key ): void {
			$state = self::delivery_state();
			if ( ( $state['_lifecycle']['owner']['token'] ?? '' ) !== $token ) {
				return;
			}
			$next = $state;
			$next['_lifecycle']['scopes'][ $namespace ]['dispatches'][ $key ]['send_state'] = 'not_sent';
			unset( $next['_lifecycle']['owner'] );
			self::compare_and_swap_delivery_state( $state, $next );
		}

		private static function suppress_payload( array $payload, array $sources ) {
			$input = $payload['input'] ?? array();
			if ( empty( $sources ) ) {
				return $payload;
			}
			if ( 'refresh' !== ( $input['sync_mode'] ?? '' ) ) {
				// A broad delete/rebuild must not race a bounded pending operation.
				foreach ( $sources as $source ) {
					if ( 'excluded' !== $source['state'] ) {
						return self::lifecycle_error( 'pending_sources' );
					}
				}
			}
			foreach ( array( 'documents', 'media_items', 'comments' ) as $field ) {
				if ( ! isset( $input[ $field ] ) || ! is_array( $input[ $field ] ) ) {
					continue;
				}
				$input[ $field ] = array_values( array_filter( $input[ $field ], static function ( $item ) use ( $field, $sources ) {
					if ( ! is_array( $item ) ) {
						return false;
					}
					$media = 'media_items' === $field || isset( $item['attachment_id'] ) || 'media' === strtolower( trim( (string) ( $item['source_type'] ?? '' ) ) ) || 'attachment' === strtolower( trim( (string) ( $item['post_type'] ?? '' ) ) );
					if ( $media ) {
						return ! isset( $sources[ 'media:' . (int) ( $item['attachment_id'] ?? $item['post_id'] ?? 0 ) ] );
					}
					$id = (int) ( $item['post_id'] ?? 0 );
					$comment = 'comments' === $field || isset( $item['comment_id'] ) || isset( $item['comment_status'] );
					if ( $comment ) {
						return ! isset( $sources[ 'post:' . $id ] ) && ! isset( $sources[ 'page:' . $id ] );
					}
					$type = strtolower( trim( (string) ( $item['post_type'] ?? 'post' ) ) );
					return ! isset( $sources[ $type . ':' . $id ] );
				} ) );
			}
			if ( isset( $input['post_ids'] ) && is_array( $input['post_ids'] ) ) {
				$input['post_ids'] = array_values( array_filter( $input['post_ids'], static function ( $id ) use ( $sources ) {
					// Legacy numeric cleanup is broad: don't let it erase protected media.
					foreach ( $sources as $source ) {
						if ( $source['source_id'] === (int) $id ) {
							return false;
						}
					}
					return true;
				} ) );
			}
			if ( 'delete' === ( $input['sync_mode'] ?? '' ) && ! empty( $payload['input']['post_ids'] ) && empty( $input['post_ids'] ) ) { return self::lifecycle_error( 'empty_delete_after_suppression' ); }
			$payload['input'] = $input;
			return $payload;
		}

		/** Advances at most one operation per existing Cron callback. */
		public static function process_source_operations(): bool {
			$state = self::delivery_state();
			$scope = $state['_lifecycle']['scopes'][ self::lifecycle_scope() ] ?? array();
			foreach ( $scope['operations'] ?? array() as $op ) {
				if ( 'pending' !== $op['status'] ) {
					continue;
				}
				$next = $op;
				$next['polls']++;
				if ( $next['polls'] > self::MAX_RUN_POLLS ) {
					$next['status'] = 'blocked';
					$next['reason'] = 'poll_limit_writer_retained';
				} else {
					$outcome = self::advance_source_operation( $op, $scope );
					if ( is_wp_error( $outcome ) ) {
						$next['reason'] = $outcome->get_error_code();
					} else {
						$next = array_merge( $next, $outcome );
					}
				}
				self::store_source_operation( $op, $next );
				$remaining = self::source_lifecycle_summary()['operations'];
				if ( array_filter( $remaining, static function ( $operation ) { return 'pending' === $operation['status']; } ) ) {
					self::schedule_flush( 30 );
				}
				return true;
			}
			return false;
		}

		private static function store_source_operation( array $expected, array $next ): bool {
			$before = self::delivery_state();
			$namespace = self::lifecycle_scope();
			if ( ( $before['_lifecycle']['scopes'][ $namespace ]['operations'][ $expected['id'] ] ?? null ) !== $expected ) {
				return false;
			}
			$after = $before;
			if ( 'completed' === $next['status'] ) {
				unset( $next['restore_payloads'], $next['legacy_cursor'] );
				foreach ( $after['_lifecycle']['scopes'][ $namespace ]['dispatches'] as &$entry ) {
					if ( $expected['id'] === ( $entry['operation'] ?? '' ) || isset( $next['old_terminal'][ $entry['key'] ] ) ) { unset( $entry['payload'] ); }
				}
				unset( $entry );
			}
			$after['_lifecycle']['scopes'][ $namespace ]['operations'][ $expected['id'] ] = $next;
			if ( strlen( serialize( $after ) ) > 4194304 ) {
				$next = array_merge( $expected, array( 'status' => 'blocked', 'reason' => 'durability_full_writer_retained' ) );
				$after['_lifecycle']['scopes'][ $namespace ]['operations'][ $expected['id'] ] = $next;
			}
			foreach ( $expected['items'] as $item ) {
				$key = self::source_key( $item );
				$source = &$after['_lifecycle']['scopes'][ $namespace ]['sources'][ $key ];
				if ( ( $source['operation'] ?? '' ) !== $expected['id'] ) {
					return false;
				}
				if ( 'completed' === $next['status'] && 'restore' === $next['action'] ) {
					unset( $after['_lifecycle']['scopes'][ $namespace ]['sources'][ $key ] );
				} elseif ( 'completed' === $next['status'] ) {
					$source['state'] = 'excluded';
				}
				unset( $source );
			}
			if ( 'completed' === $next['status'] && 'restore' === $next['action'] ) {
				$after['_lifecycle']['scopes'][ $namespace ]['producer_generation'] = $expected['id'];
			}
			return self::compare_and_swap_delivery_state( $before, $after );
		}

		private static function operation_payload( array $input ): array {
			return array( 'ability_name' => 'npcink-cloud/site-knowledge-sync', 'contract_version' => 'site_knowledge_sync.v1', 'execution_pattern' => 'whole_run_offload', 'input' => array_merge( $input, array( 'contract_version' => 'site_knowledge_sync.v1', 'write_posture' => 'suggestion_only', 'direct_wordpress_write' => false ) ), 'data_classification' => 'public_site_content', 'storage_mode' => 'result_only', 'retention_ttl' => DAY_IN_SECONDS, 'timeout_seconds' => 60, 'retry_max' => 0, 'policy' => array( 'allow_fallback' => true ) );
		}

		private static function lifecycle_key( array $op, string $phase ): string {
			return 'site_knowledge_source_' . hash( 'sha256', self::lifecycle_scope() . '|' . $op['id'] . '|' . $op['set_hash'] . '|' . $phase );
		}

		/** Returns a real Cloud writer witness, or durably replays a lost response. */
		private static function operation_run( array $op, array $scope, string $phase, array $payload ) {
			$key = self::lifecycle_key( $op, $phase );
			$entry = $scope['dispatches'][ $key ] ?? array();
			if ( empty( $entry['run_id'] ) ) {
				$result = self::dispatch_mutation( $payload, $key, $op['action'], $op['id'] );
				return is_wp_error( $result ) ? $result : self::lifecycle_error( 'awaiting_run' );
			}
			return Npcink_Cloud_Site_Knowledge_Runtime_Bridge::read_writer_witness( $entry );
		}

		private static function advance_source_operation( array $op, array $scope ) {
			if ( 'restore' === $op['action'] ) {
				return self::advance_source_restore( $op, $scope );
			}
			$payload = self::operation_payload( array( 'sync_mode' => 'delete', 'source_items' => $op['items'] ) );
			$d0 = self::operation_run( $op, $scope, 'd0', $payload );
			if ( is_wp_error( $d0 ) || ! $d0['succeeded'] ) {
				return is_wp_error( $d0 ) ? $d0 : self::lifecycle_error( 'delete_failed' );
			}
			if ( ! empty( $op['legacy_unknown'] ) ) {
				return self::lifecycle_error( 'legacy_writer_identity_unknown' );
			}
			$known = $op['old_terminal'] ?? array();
			$checked = 0;
			foreach ( $op['old'] as $key ) {
				if ( isset( $known[ $key ] ) ) { continue; }
				if ( $checked++ >= 4 ) { return array( 'old_terminal' => $known, 'reason' => 'checking_old_writers' ); }
				$entry = $scope['dispatches'][ $key ] ?? array();
				if ( 'not_sent' === ( $entry['send_state'] ?? '' ) ) {
					$known[ $key ] = array( 'not_sent' => true );
					continue;
				}
				if ( empty( $entry['run_id'] ) ) {
					if ( ! empty( $entry['payload'] ) ) { self::dispatch_mutation( $entry['payload'], $key, 'old_replay', $op['id'] ); }
					return array( 'old_terminal' => $known, 'reason' => 'old_writer_identity_unknown' );
				}
				$witness = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::read_writer_witness( $entry );
				if ( is_wp_error( $witness ) ) { return array( 'old_terminal' => $known, 'reason' => $witness->get_error_code() ); }
				$known[ $key ] = array( 'run_id' => $witness['run_id'], 'status' => $witness['status'] );
			}
			$terminal = array_values( array_filter( $known, static function ( $witness ) { return isset( $witness['run_id'] ); } ) );
			usort( $terminal, static function ( $a, $b ) { return strcmp( $a['run_id'], $b['run_id'] ); } );
			if ( ! empty( $terminal ) ) {
				$phase = 'c1_' . hash( 'sha256', (string) wp_json_encode( $terminal ) );
				if ( isset( $op['c1'] ) && $op['c1'] !== $phase ) {
					return self::lifecycle_error( 'terminal_set_changed' );
				}
				if ( ! isset( $op['c1'] ) ) {
					return array( 'c1' => $phase, 'terminal_writers' => $terminal, 'old_terminal' => $known );
				}
				$c1 = self::operation_run( $op, $scope, $phase, $payload );
				if ( is_wp_error( $c1 ) || ! $c1['succeeded'] ) {
					return is_wp_error( $c1 ) ? $c1 : self::lifecycle_error( 'compensation_failed' );
				}
			}
			$presence = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::read_source_presence( $op['items'] );
			if ( is_wp_error( $presence ) ) {
				return $presence;
			}
			foreach ( $presence['items'] as $row ) {
				if ( $row['document_present'] || $row['stored_chunk_count'] || $row['associated_comment_document_count'] || $row['associated_comment_stored_chunk_count'] ) {
					return self::lifecycle_error( 'source_still_present' );
				}
			}
			return array( 'status' => 'completed', 'reason' => '', 'observed_at' => $presence['observed_at'] );
		}

		private static function advance_source_restore( array $op, array $scope ) {
			foreach ( $op['items'] as $item ) {
				if ( ! self::public_source( $item ) || ! self::is_enabled() ) {
					return self::lifecycle_error( 'restore_not_indexable' );
				}
			}
			if ( ! isset( $op['restore_payloads'] ) ) {
				$presence = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::read_source_presence( $op['items'] );
				if ( is_wp_error( $presence ) || ! is_bool( $presence['comments_enabled'] ?? null ) ) {
					return self::lifecycle_error( 'comments_configuration_unknown' );
				}
				$payloads = self::source_restore_payloads( $op['items'], $presence['comments_enabled'] );
				return is_wp_error( $payloads ) ? $payloads : array( 'restore_payloads' => $payloads, 'restore_comments_enabled' => $presence['comments_enabled'] );
			}
			foreach ( $op['restore_payloads'] as $index => $payload ) {
				$witness = self::operation_run( $op, $scope, 'restore_' . $index, $payload );
				if ( is_wp_error( $witness ) || ! $witness['succeeded'] ) {
					return is_wp_error( $witness ) ? $witness : self::lifecycle_error( 'restore_failed' );
				}
				$result = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::read_restore_result( $witness['run_id'], $payload );
				if ( is_wp_error( $result ) ) { return $result; }
			}
			$presence = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::read_source_presence( $op['items'] );
			if ( is_wp_error( $presence ) ) {
				return $presence;
			}
			if ( $op['restore_comments_enabled'] !== $presence['comments_enabled'] ) { return self::lifecycle_error( 'comments_configuration_changed' ); }
			$comments = array();
			foreach ( $op['restore_payloads'] as $payload ) {
				foreach ( $payload['input']['comments'] ?? array() as $comment ) { $comments[ $comment['post_id'] ] = 1 + ( $comments[ $comment['post_id'] ] ?? 0 ); }
			}
			foreach ( $presence['items'] as $row ) {
				if ( ! $row['public_document_present'] || $row['public_chunk_count'] < 1 ) {
					return self::lifecycle_error( 'restore_public_chunks_missing' );
				}
				$expected_comments = $comments[ $row['source_id'] ] ?? 0;
				if ( 'media' !== $row['source_type'] && ( $row['associated_comment_document_count'] < $expected_comments || $row['associated_comment_stored_chunk_count'] < $expected_comments ) ) { return self::lifecycle_error( 'restore_comments_missing' ); }
			}
			return array( 'status' => 'completed', 'reason' => '', 'observed_at' => $presence['observed_at'] );
		}

		/** Real WordPress public identity; attachment inherit alone is never proof. */
		public static function public_source( array $item ) {
			$post = get_post( $item['source_id'] );
			if ( 'media' !== $item['source_type'] ) {
				unset( self::$post_document_memo[ $item['source_id'] ] );
				return self::is_public_post( $post ) && $post->post_type === $item['source_type'] ? self::post_document( $post ) : null;
			}
			if ( ! is_object( $post ) || 'attachment' !== $post->post_type || ! in_array( $post->post_status, array( 'inherit', 'publish' ), true ) || ! empty( $post->post_password ) || ! in_array( $post->post_mime_type, array( 'image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif' ), true ) || ! self::is_public_post( get_post( (int) $post->post_parent ) ) ) {
				return null;
			}
			$url = wp_get_attachment_url( $item['source_id'] );
			$alt = (string) get_post_meta( $item['source_id'], '_wp_attachment_image_alt', true );
			$title = self::bounded_text( (string) $post->post_title, 500 );
			$caption = self::bounded_text( (string) $post->post_excerpt, 1000 );
			$description = self::bounded_text( (string) $post->post_content, self::MAX_DOCUMENT_CHARS );
			if ( ! is_string( $url ) || ! preg_match( '#\Ahttps?://#', $url ) || '' === trim( $title . $alt . $caption . $description ) ) {
				return null;
			}
			$media = array( 'attachment_id' => $item['source_id'], 'mime_type' => $post->post_mime_type, 'url' => $url, 'title' => $title, 'alt' => self::bounded_text( $alt, 1000 ), 'caption' => $caption, 'description' => $description, 'modified_gmt' => $post->post_modified_gmt );
			$encoded = wp_json_encode( $media );
			if ( ! is_string( $encoded ) ) {
				return null;
			}
			$media['media_fingerprint'] = hash( 'sha256', $encoded );
			return $media;
		}

		private static function source_restore_payloads( array $items, bool $comments_enabled ) {
			$documents = array();
			$media = array();
			$comments = array();
			foreach ( $items as $item ) {
				$source = self::public_source( $item );
				if ( ! $source ) {
					return self::lifecycle_error( 'restore_not_indexable' );
				}
				if ( 'media' === $item['source_type'] ) {
					$media[] = $source;
					continue;
				}
				$documents[] = $source;
				if ( $comments_enabled ) {
					if ( ! function_exists( 'get_comments' ) ) {
						return self::lifecycle_error( 'comments_unavailable' );
					}
					foreach ( get_comments( array( 'post_id' => $item['source_id'], 'status' => 'approve', 'type' => 'comment', 'number' => 201 ) ) as $comment ) {
						if ( (int) $comment->comment_post_ID !== $item['source_id'] || ! in_array( (string) $comment->comment_approved, array( '1', 'approve', 'approved' ), true ) ) {
							return self::lifecycle_error( 'comments_invalid' );
						}
						$comments[] = array( 'comment_id' => (int) $comment->comment_ID, 'post_id' => $item['source_id'], 'comment_status' => 'approved', 'content_excerpt' => self::bounded_text( (string) $comment->comment_content, self::MAX_DOCUMENT_CHARS ), 'url' => get_comment_link( $comment ), 'created_gmt' => $comment->comment_date_gmt );
					}
				}
			}
			if ( count( $comments ) + count( $documents ) > self::MANUAL_INDEX_POSTS ) {
				return self::lifecycle_error( 'comments_delivery_limit' );
			}
			$payloads = array();
			if ( $documents ) {
				$payloads[] = self::operation_payload( array( 'sync_mode' => 'refresh', 'documents' => $documents, 'comments' => $comments ) );
			}
			if ( $media ) {
				$payloads[] = self::operation_payload( array( 'sync_mode' => 'refresh', 'media_items' => $media ) );
			}
			foreach ( $payloads as $payload ) {
				$encoded = wp_json_encode( $payload );
				if ( ! is_string( $encoded ) || strlen( $encoded ) > 900000 ) { return self::lifecycle_error( 'restore_payload_limit' ); }
			}
			return $payloads;
		}

		/** Public typed snapshot, with image-only batches after text batches. */
		private static function full_typed_batches( array $post_ids ): array {
			$posts = array();
			$sources = self::source_lifecycle_summary()['items'];
			foreach ( $post_ids as $id ) {
				$post = get_post( $id );
				if ( self::is_public_post( $post ) && ! isset( $sources[ $post->post_type . ':' . $id ] ) ) {
					$posts[] = array( 'source_type' => $post->post_type, 'source_id' => $id );
				}
			}
			$media = array();
			$ids = get_posts( array( 'post_type' => 'attachment', 'post_status' => array( 'inherit', 'publish' ), 'posts_per_page' => self::MANUAL_MAX_POSTS, 'fields' => 'ids', 'orderby' => 'ID', 'order' => 'ASC', 'no_found_rows' => true ) );
			$sources = self::source_lifecycle_summary()['items'];
			foreach ( $ids as $id ) {
				$item = array( 'source_type' => 'media', 'source_id' => (int) $id );
				if ( count( $posts ) + count( $media ) >= self::MANUAL_MAX_POSTS ) {
					break;
				}
				if ( ! isset( $sources[ self::source_key( $item ) ] ) && self::public_source( $item ) ) {
					$media[] = $item;
				}
			}
			return array_merge( array_chunk( $posts, self::MANUAL_INDEX_POSTS ), array_chunk( $media, self::MANUAL_INDEX_POSTS ) );
		}

		public static function handle_attachment_change( int $id ): void {
			$item = array( 'source_type' => 'media', 'source_id' => $id );
			if ( self::is_enabled() && self::public_source( $item ) ) {
				self::buffer_post_ids( array( $id ) );
			}
		}

		public static function handle_attachment_meta_change( $meta_id, $id, $key ): void {
			if ( '_wp_attachment_image_alt' === $key ) {
				self::handle_attachment_change( (int) $id );
			}
		}

		public static function selection_candidates(): array {
			$sources = self::source_lifecycle_summary()['items'];
			$candidates = array();
			if ( ! function_exists( 'get_posts' ) || ! function_exists( 'get_post' ) ) { return $candidates; }
			foreach ( $sources as $key => $source ) {
				$post = get_post( $source['source_id'] );
				$candidates[ $key ] = array_merge( $source, array( 'title' => is_object( $post ) ? (string) $post->post_title : $key ) );
			}
			$ids = get_posts( array( 'post_type' => array( 'post', 'page', 'attachment' ), 'post_status' => array( 'publish', 'inherit' ), 'posts_per_page' => 100, 'fields' => 'ids', 'orderby' => 'modified', 'order' => 'DESC' ) );
			foreach ( $ids as $id ) {
				if ( count( $candidates ) >= 100 ) { break; }
				$post = get_post( $id );
				$type = 'attachment' === ( $post->post_type ?? '' ) ? 'media' : ( $post->post_type ?? '' );
				$item = array( 'source_type' => $type, 'source_id' => (int) $id );
				if ( self::public_source( $item ) && ! isset( $sources[ self::source_key( $item ) ] ) ) {
					$candidates[ self::source_key( $item ) ] = array_merge( $item, array( 'title' => (string) $post->post_title, 'state' => '' ) );
				}
			}
			return array_slice( $candidates, 0, 100, true );
		}

		private static function initialize_delivery_coverage(): void {
			if ( Npcink_Cloud_Addon_Settings::is_configured() ) { return; }
			$state = self::delivery_state();
			if ( empty( $state ) ) {
				self::compare_and_swap_delivery_state( $state, array( '_lifecycle' => array( 'coverage_started_without_credentials' => true ) ) );
			}
		}

		private static function get_maintenance_cursor(): array {
			$cursor = self::delivery_state();
			unset( $cursor['_lifecycle'] );
			return $cursor;
		}

		/**
		 * Claims the existing cursor option without overwriting active delivery.
		 *
		 * `add_option()` gives the normal empty-cursor path an atomic database claim.
		 * A blocked or otherwise inactive cursor may be replaced by an explicit new
		 * request without adding a second lock option.
		 *
		 * @param array<string,mixed> $cursor New delivery cursor.
		 * @return bool
		 */
		private static function claim_full_index_delivery_cursor( array $cursor ): bool {
			$before = self::delivery_state();
			$sources = $before['_lifecycle']['scopes'][ self::lifecycle_scope() ]['sources'] ?? array();
			foreach ( $sources as $source ) {
				if ( 'excluded' !== $source['state'] ) { return false; }
			}
			$current = $before;
			unset( $current['_lifecycle'] );
			if ( self::is_active_full_index_delivery( $current ) ) { return false; }
			if ( isset( $before['_lifecycle'] ) ) { $cursor['_lifecycle'] = $before['_lifecycle']; }
			return self::compare_and_swap_delivery_state( $before, $cursor );
		}

		/**
		 * Replaces or deletes exactly the cursor version read by the caller.
		 *
		 * The real WordPress path uses one conditional SQL write so a stale Cron
		 * callback cannot overwrite a newer administrator request. The narrow
		 * fallback keeps isolated behavior tests deterministic without `$wpdb`.
		 *
		 * @param array<string,mixed>      $expected Cursor version previously read.
		 * @param array<string,mixed>|null $replacement Replacement cursor, or null to delete.
		 * @return bool
		 */
		private static function compare_and_swap_delivery_state( array $expected, ?array $replacement ): bool {
			global $wpdb;
			if ( null !== $replacement && strlen( serialize( $replacement ) ) > 4194304 && strlen( serialize( $replacement ) ) > strlen( serialize( $expected ) ) ) { return false; }
			if ( array() === $expected && null !== $replacement && add_option( self::MAINTENANCE_OPTION, $replacement, '', false ) ) {
				return true;
			}

			if (
				isset( $wpdb )
				&& is_object( $wpdb )
				&& isset( $wpdb->options )
				&& is_string( $wpdb->options )
				&& method_exists( $wpdb, 'delete' )
				&& method_exists( $wpdb, 'update' )
			) {
				$expected_value = serialize( $expected );
				if ( null === $replacement ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Exact option-value matching provides the atomic compare-and-swap contract.
					$result = $wpdb->delete(
						$wpdb->options,
						array(
							'option_name' => self::MAINTENANCE_OPTION,
							'option_value' => $expected_value,
						),
						array( '%s', '%s' )
					);
				} else {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Exact option-value matching provides the atomic compare-and-swap contract.
					$result = $wpdb->update(
						$wpdb->options,
						array( 'option_value' => serialize( $replacement ) ),
						array(
							'option_name' => self::MAINTENANCE_OPTION,
							'option_value' => $expected_value,
						),
						array( '%s' ),
						array( '%s', '%s' )
					);
				}

				$changed = 1 === (int) $result;
				if ( $changed && function_exists( 'wp_cache_delete' ) ) {
					wp_cache_delete( self::MAINTENANCE_OPTION, 'options' );
				}

				return $changed;
			}

			if ( self::delivery_state() !== $expected ) {
				return false;
			}

			if ( null === $replacement ) {
				return delete_option( self::MAINTENANCE_OPTION );
			}

			return update_option( self::MAINTENANCE_OPTION, $replacement, false );
		}

		/**
		 * Returns whether one bounded full-index delivery currently owns the cursor.
		 *
		 * @param array<string,mixed> $cursor Delivery cursor.
		 * @return bool
		 */
		private static function is_active_full_index_delivery( array $cursor ): bool {
			$request_id = sanitize_key( (string) ( $cursor['request_id'] ?? '' ) );
			$status = sanitize_key( (string) ( $cursor['status'] ?? '' ) );

			return '' !== $request_id && in_array( $status, array( 'queued', 'delivering', 'retrying' ), true );
		}

		/**
		 * Returns allowed public post types.
		 *
		 * @return array<int,string>
		 */
		private static function post_types(): array {
			$post_types = apply_filters( 'npcink_cloud_addon_site_knowledge_post_types', self::DEFAULT_POST_TYPES );
			$post_types = is_array( $post_types ) ? $post_types : self::DEFAULT_POST_TYPES;
			$post_types = array_values( array_unique( array_filter( array_map( 'sanitize_key', $post_types ) ) ) );

			return empty( $post_types ) ? self::DEFAULT_POST_TYPES : $post_types;
		}

		/**
		 * Returns whether a post object is public and allowed.
		 *
		 * @param mixed $post Post object.
		 * @return bool
		 */
		private static function is_public_post( $post ): bool {
			$post_id = self::post_id_from_value( $post );
			$post_type = sanitize_key( (string) ( $post->post_type ?? '' ) );
			$post_status = sanitize_key( (string) ( $post->post_status ?? '' ) );
			$post_password = (string) ( $post->post_password ?? '' );

			return $post_id > 0
				&& 'publish' === $post_status
				&& '' === $post_password
				&& 'attachment' !== $post_type
				&& in_array( $post_type, self::post_types(), true );
		}

		/**
		 * Extracts post id from a post-like value.
		 *
		 * @param mixed $post Post-like value.
		 * @return int
		 */
		private static function post_id_from_value( $post ): int {
			if ( is_object( $post ) ) {
				return absint( $post->ID ?? 0 );
			}

			return absint( $post );
		}

		/**
		 * Extracts parent post id from a comment-like value.
		 *
		 * @param mixed $comment Comment-like value.
		 * @return int
		 */
		private static function post_id_from_comment( $comment ): int {
			if ( is_object( $comment ) ) {
				return absint( $comment->comment_post_ID ?? 0 );
			}

			return 0;
		}

		/**
		 * Returns a bounded plain text value.
		 *
		 * @param string $value Raw text.
		 * @param int    $limit Character limit.
		 * @return string
		 */
		private static function bounded_text( string $value, int $limit, ?array &$delivery_evidence = null ): string {
			if ( function_exists( 'strip_shortcodes' ) ) {
				$value = strip_shortcodes( $value );
			}
			$value = wp_strip_all_tags( $value );
			$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$value = preg_replace( '~(?:https?://|www\.)\S+~iu', ' ', $value );
			$value = preg_replace( '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/iu', ' ', is_string( $value ) ? $value : '' );
			$value = preg_replace( '/(?:\+?\d[\s().-]*){7,}/u', ' ', is_string( $value ) ? $value : '' );
			$value = preg_replace( '/\s+/u', ' ', is_string( $value ) ? $value : '' );
			$value = trim( is_string( $value ) ? $value : '' );

			$source_bytes = strlen( $value );
			$source_chars = null !== $delivery_evidence ? preg_match_all( '/./us', $value ) : 0;
			if ( $source_bytes > $limit ) {
				$value = substr( $value, 0, $limit );
				while ( '' !== $value && 1 !== preg_match( '//u', $value ) ) {
					$value = substr( $value, 0, -1 );
				}
			}
			if ( null !== $delivery_evidence ) {
				$delivery_evidence = array(
					'contract_version' => 'site_knowledge_delivery_evidence.v1',
					'projection_version' => 'wordpress_public_text_bytes.v1',
					'length_basis' => 'sanitized_public_text_before_byte_limit',
					'source_bytes' => $source_bytes,
					'source_chars' => (int) $source_chars,
					'delivered_bytes' => strlen( $value ),
					'delivered_chars' => (int) preg_match_all( '/./us', $value ),
					'max_bytes' => $limit,
					'truncated' => $source_bytes > strlen( $value ),
				);
			}

			return $value;
		}

		/**
		 * Returns a sanitized post title.
		 *
		 * @param mixed $post Post object.
		 * @return string
		 */
		private static function post_title( $post ): string {
			if ( function_exists( 'get_the_title' ) ) {
				return self::bounded_text( (string) get_the_title( self::post_id_from_value( $post ) ), 200 );
			}

			return self::bounded_text( (string) ( $post->post_title ?? '' ), 200 );
		}

		/**
		 * Returns a public post URL.
		 *
		 * @param int $post_id Post id.
		 * @return string
		 */
		private static function post_url( int $post_id ): string {
			if ( function_exists( 'get_permalink' ) ) {
				return esc_url_raw( (string) get_permalink( $post_id ) );
			}

			return '';
		}
	}
}
