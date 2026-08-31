<?php
/**
 * Cloud addon settings page.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Settings_Page' ) ) {
	/**
	 * Renders Npcink > Cloud Addon and handles save-and-verify.
	 */
	final class Npcink_Cloud_Settings_Page {
		private const PARENT_MENU_SLUG = 'npcink-ai';
		private const PAGE_SLUG = 'npcink-cloud-addon';
		private const MENU_CAPABILITY = 'manage_options';
		private const ACTION_SAVE = 'npcink_cloud_addon_save';
		private const ACTION_COMPLETE_AUTH = 'npcink_cloud_addon_complete_auth';
		private const ACTION_START_AUTH = 'npcink_cloud_addon_start_auth';
		private const ACTION_START_CUSTOM_AUTH = 'npcink_cloud_addon_start_custom_auth';
		private const ACTION_DISCONNECT = 'npcink_cloud_addon_disconnect';
		private const ACTION_UPDATE_LOCAL_PERMISSION = 'npcink_cloud_addon_update_local_permission';
		private const ACTION_DISMISS_MONITORING_PROMPT = 'npcink_cloud_addon_dismiss_monitoring_prompt';
		private const ACTION_REFRESH_SITE_KNOWLEDGE = 'npcink_cloud_addon_refresh_site_knowledge';
		private const ACTION_REFRESH_SITE_MEDIA_INDEX = 'npcink_cloud_addon_refresh_site_media_index';
		private const ACTION_REFRESH_SITE_MEDIA_STATUS = 'npcink_cloud_addon_refresh_site_media_status';
		private const ACTION_POLL_SITE_MEDIA_STATUS = 'npcink_cloud_addon_poll_site_media_status';
		private const ACTION_REFRESH_SITE_KNOWLEDGE_STATUS = 'npcink_cloud_addon_refresh_site_knowledge_status';
		private const ACTION_MANAGE_SITE_KNOWLEDGE_INDEX = 'npcink_cloud_addon_manage_site_knowledge_index';
		private const ACTION_RUN_MANUAL_READINESS_TEST = 'npcink_cloud_addon_run_manual_readiness_test';
		private const ACTION_REFRESH_ENTITLEMENT = 'npcink_cloud_addon_refresh_entitlement';
		private const DATETIME_DISPLAY_FORMAT = 'Y-m-d H:i:s';
		private const AUTH_STATE_TTL_SECONDS = 600;
		private const MEDIA_STATUS_TRANSIENT = 'npcink_cloud_addon_media_index_status';
		private const MEDIA_PLAN_OPTION = 'npcink_cloud_addon_media_recognition_plan';
		private const MEDIA_PLAN_CRON = 'npcink_cloud_addon_continue_media_recognition';
		private const MEDIA_PLAN_LOCK = 'npcink_cloud_addon_media_recognition_plan_lock';
		private const MEDIA_PLAN_TRANSPORT_RETRY_LIMIT = 3;

		/**
		 * Registers admin hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'init', array( __CLASS__, 'ensure_media_recognition_plan_schedule' ) );
			add_action( 'add_attachment', array( __CLASS__, 'handle_media_attachment_changed' ) );
			add_action( 'edit_attachment', array( __CLASS__, 'handle_media_attachment_changed' ) );
			add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 50 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
			add_action( 'admin_post_' . self::ACTION_SAVE, array( __CLASS__, 'handle_save' ) );
			add_action( 'admin_post_' . self::ACTION_COMPLETE_AUTH, array( __CLASS__, 'handle_complete_auth' ) );
			add_action( 'admin_post_' . self::ACTION_START_AUTH, array( __CLASS__, 'handle_start_auth' ) );
			add_action( 'admin_post_' . self::ACTION_START_CUSTOM_AUTH, array( __CLASS__, 'handle_start_custom_auth' ) );
			add_action( 'admin_post_' . self::ACTION_DISCONNECT, array( __CLASS__, 'handle_disconnect' ) );
			add_action( 'admin_post_' . self::ACTION_UPDATE_LOCAL_PERMISSION, array( __CLASS__, 'handle_update_local_permission' ) );
			add_action( 'admin_post_' . self::ACTION_DISMISS_MONITORING_PROMPT, array( __CLASS__, 'handle_dismiss_monitoring_prompt' ) );
			add_action( 'admin_post_' . self::ACTION_REFRESH_SITE_KNOWLEDGE, array( __CLASS__, 'handle_refresh_site_knowledge' ) );
			add_action( 'admin_post_' . self::ACTION_REFRESH_SITE_MEDIA_INDEX, array( __CLASS__, 'handle_refresh_site_media_index' ) );
			add_action( 'admin_post_' . self::ACTION_REFRESH_SITE_MEDIA_STATUS, array( __CLASS__, 'handle_refresh_site_media_status' ) );
			add_action( 'wp_ajax_' . self::ACTION_POLL_SITE_MEDIA_STATUS, array( __CLASS__, 'handle_poll_site_media_status' ) );
			add_action( self::MEDIA_PLAN_CRON, array( __CLASS__, 'process_media_recognition_plan' ) );
			add_action( 'wp_ajax_' . self::ACTION_REFRESH_SITE_KNOWLEDGE_STATUS, array( __CLASS__, 'handle_refresh_site_knowledge_status' ) );
			add_action( 'admin_post_' . self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX, array( __CLASS__, 'handle_manage_site_knowledge_index' ) );
			add_action( 'admin_post_' . self::ACTION_RUN_MANUAL_READINESS_TEST, array( __CLASS__, 'handle_run_manual_readiness_test' ) );
			add_action( 'wp_ajax_' . self::ACTION_REFRESH_ENTITLEMENT, array( __CLASS__, 'handle_refresh_entitlement' ) );
		}

		/**
		 * Queues one automatic rescan through the existing durable media plan.
		 *
		 * Attachment hooks never call Cloud inline. Repeated events for the same
		 * attachment collapse into one current or pending rescan marker.
		 *
		 * @param int $attachment_id Changed attachment id.
		 * @return void
		 */
		public static function handle_media_attachment_changed( int $attachment_id ): void {
			if (
				$attachment_id <= 0
				|| ! Npcink_Cloud_Addon_Settings::is_verified()
				|| ! Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled()
				|| ( function_exists( 'wp_attachment_is_image' ) && ! wp_attachment_is_image( $attachment_id ) )
			) {
				return;
			}

			$plan = self::get_media_recognition_plan();
			$user_id = get_current_user_id();
			if ( ! empty( $plan['active'] ) ) {
				$current_ids = self::normalize_media_rescan_attachment_ids( $plan['rescan_attachment_ids'] ?? array() );
				$pending_ids = self::normalize_media_rescan_attachment_ids( $plan['pending_rescan_attachment_ids'] ?? array() );
				if ( in_array( $attachment_id, $current_ids, true ) || in_array( $attachment_id, $pending_ids, true ) ) {
					return;
				}

				$pending_ids[] = $attachment_id;
				$plan['pending_rescan_attachment_ids'] = self::normalize_media_rescan_attachment_ids( $pending_ids );
				if ( $user_id > 0 ) {
					$plan['pending_rescan_initiated_by'] = $user_id;
				}
				$plan['updated_at'] = current_time( 'mysql' );
				self::set_media_recognition_plan( $plan );
				self::schedule_media_recognition_plan( 30 );
				return;
			}
			$plan_state = sanitize_key( (string) ( $plan['state'] ?? '' ) );
			if ( ! empty( $plan ) && ! in_array( $plan_state, array( '', 'not_started', 'complete' ), true ) ) {
				$pending_ids = self::normalize_media_rescan_attachment_ids( $plan['pending_rescan_attachment_ids'] ?? array() );
				if ( ! in_array( $attachment_id, $pending_ids, true ) ) {
					$pending_ids[] = $attachment_id;
					$plan['pending_rescan_attachment_ids'] = self::normalize_media_rescan_attachment_ids( $pending_ids );
					if ( $user_id > 0 ) {
						$plan['pending_rescan_initiated_by'] = $user_id;
					}
					$plan['updated_at'] = current_time( 'mysql' );
					self::set_media_recognition_plan( $plan );
				}
				return;
			}

			self::start_media_recognition_rescan( $plan, array( $attachment_id ), $user_id );
		}

		/**
		 * Enqueues admin assets for the Cloud Addon pages.
		 *
		 * @param string $hook_suffix Admin hook suffix.
		 * @return void
		 */
		public static function enqueue_admin_assets( string $hook_suffix ): void {
			$is_cloud_page = false !== strpos( $hook_suffix, self::PAGE_SLUG ) || false !== strpos( $hook_suffix, self::PARENT_MENU_SLUG );
			if ( ! $is_cloud_page ) {
				return;
			}

			wp_enqueue_style(
				'npcink-cloud-addon-admin',
				plugins_url( 'assets/admin.css', NPCINK_CLOUD_ADDON_FILE ),
				array(),
				NPCINK_CLOUD_ADDON_VERSION
			);

			if ( false === strpos( $hook_suffix, self::PAGE_SLUG ) ) {
				return;
			}

			wp_enqueue_script(
				'npcink-cloud-addon-admin-entitlement',
				plugins_url( 'assets/admin-entitlement.js', NPCINK_CLOUD_ADDON_FILE ),
				array(),
				NPCINK_CLOUD_ADDON_VERSION,
				true
			);
			wp_localize_script(
				'npcink-cloud-addon-admin-entitlement',
				'npcinkCloudEntitlement',
				array(
					'action' => self::ACTION_REFRESH_ENTITLEMENT,
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( self::ACTION_REFRESH_ENTITLEMENT ),
					'failedLabel' => __( 'Plan and entitlement are temporarily unavailable.', 'npcink-cloud-addon' ),
					'updateFailedLabel' => __( 'Update failed', 'npcink-cloud-addon' ),
				)
			);

			wp_enqueue_script(
				'npcink-cloud-addon-admin-site-knowledge',
				plugins_url( 'assets/admin-site-knowledge.js', NPCINK_CLOUD_ADDON_FILE ),
				array(),
				NPCINK_CLOUD_ADDON_VERSION,
				true
			);
			wp_localize_script(
				'npcink-cloud-addon-admin-site-knowledge',
				'npcinkCloudSiteKnowledge',
				array(
					'action' => self::ACTION_REFRESH_SITE_KNOWLEDGE_STATUS,
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( self::ACTION_REFRESH_SITE_KNOWLEDGE_STATUS ),
					'failedLabel' => __( 'Site Knowledge usage is temporarily unavailable.', 'npcink-cloud-addon' ),
					'updateFailedLabel' => __( 'Update failed', 'npcink-cloud-addon' ),
					'mediaAction' => self::ACTION_POLL_SITE_MEDIA_STATUS,
					'mediaNonce' => wp_create_nonce( self::ACTION_POLL_SITE_MEDIA_STATUS ),
					'mediaPollInterval' => 10000,
					'estimatingLabel' => __( 'Estimating', 'npcink-cloud-addon' ),
					'completedSpeedLabel' => __( 'Not applicable after completion', 'npcink-cloud-addon' ),
					'processingLabel' => __( 'Processing', 'npcink-cloud-addon' ),
					'imagesPerMinuteLabel' => __( 'images/minute', 'npcink-cloud-addon' ),
					'imagesLabel' => __( 'images', 'npcink-cloud-addon' ),
					'mediaPollFailedLabel' => __( 'Progress could not be refreshed automatically. Use Refresh progress to try again.', 'npcink-cloud-addon' ),
				)
			);

			wp_enqueue_script(
				'npcink-cloud-addon-admin-permissions',
				plugins_url( 'assets/admin-permissions.js', NPCINK_CLOUD_ADDON_FILE ),
				array(),
				NPCINK_CLOUD_ADDON_VERSION,
				true
			);
			wp_localize_script(
				'npcink-cloud-addon-admin-permissions',
				'npcinkCloudPermissions',
				array(
					'savingLabel' => __( 'Saving…', 'npcink-cloud-addon' ),
				)
			);
		}

		/**
		 * Refreshes the shared read-only entitlement projection for the admin UI.
		 *
		 * @return void
		 */
		public static function handle_refresh_entitlement(): void {
			if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
				wp_send_json_error(
					array( 'message' => __( 'You do not have permission to refresh Cloud entitlement.', 'npcink-cloud-addon' ) ),
					403
				);
			}

			check_ajax_referer( self::ACTION_REFRESH_ENTITLEMENT, 'nonce' );

			$state = Npcink_Cloud_Addon_Settings::get_credential_state();
			if ( empty( $state['verified'] ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Verify the Cloud connection before reading plan and entitlement.', 'npcink-cloud-addon' ) ),
					409
				);
			}

			$mode = isset( $_POST['mode'] ) ? sanitize_key( wp_unslash( $_POST['mode'] ) ) : 'auto';
			$summary = Npcink_Cloud_Entitlement_Summary::refresh( 'retry' !== $mode );
			if ( empty( $summary['available'] ) ) {
				wp_send_json_error(
					array(
						'message' => __( 'Plan and entitlement are temporarily unavailable.', 'npcink-cloud-addon' ),
						'state' => sanitize_key( (string) ( $summary['state'] ?? 'unavailable' ) ),
					),
					503
				);
			}

			wp_send_json_success(
				array(
					'label' => self::format_overview_entitlement( $summary, true ),
					'state' => sanitize_key( (string) ( $summary['state'] ?? 'fresh' ) ),
					'syncedAt' => sanitize_text_field( (string) ( $summary['synced_at'] ?? '' ) ),
					'metrics' => self::get_overview_entitlement_metrics( $summary ),
				)
			);
		}

		/**
		 * Refreshes the Cloud-owned Site Knowledge usage projection.
		 *
		 * @return void
		 */
		public static function handle_refresh_site_knowledge_status(): void {
			if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
				wp_send_json_error(
					array( 'message' => __( 'You do not have permission to refresh Site Knowledge usage.', 'npcink-cloud-addon' ) ),
					403
				);
			}

			check_ajax_referer( self::ACTION_REFRESH_SITE_KNOWLEDGE_STATUS, 'nonce' );

			if ( ! Npcink_Cloud_Addon_Settings::is_verified() || ! Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled() ) {
				wp_send_json_error(
					array( 'message' => __( 'Enable Site Knowledge delivery before reading Cloud index usage.', 'npcink-cloud-addon' ) ),
					409
				);
			}

			$summary = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::refresh_status_summary();
			if ( empty( $summary['available'] ) ) {
				wp_send_json_error(
					array( 'message' => __( 'Site Knowledge usage is temporarily unavailable.', 'npcink-cloud-addon' ) ),
					503
				);
			}

			wp_send_json_success( self::get_site_knowledge_usage_projection( $summary ) );
		}

		/**
		 * Adds the settings page.
		 *
		 * @return void
		 */
		public static function add_menu_page(): void {
			if ( self::has_parent_menu() ) {
				add_submenu_page(
					self::PARENT_MENU_SLUG,
					__( 'Npcink Cloud Addon', 'npcink-cloud-addon' ),
					__( 'Cloud Addon', 'npcink-cloud-addon' ),
					self::MENU_CAPABILITY,
					self::PAGE_SLUG,
					array( __CLASS__, 'render' ),
					50
				);
				return;
			}

			add_options_page(
				__( 'Npcink Cloud Addon', 'npcink-cloud-addon' ),
				__( 'Npcink Cloud Addon', 'npcink-cloud-addon' ),
				self::MENU_CAPABILITY,
				self::PAGE_SLUG,
				array( __CLASS__, 'render' )
			);
		}

		/**
		 * Returns whether another Npcink plugin already created the parent menu.
		 *
		 * @return bool
		 */
		private static function has_parent_menu(): bool {
			global $menu;

			foreach ( (array) $menu as $item ) {
				if ( isset( $item[2] ) && self::PARENT_MENU_SLUG === $item[2] ) {
					return true;
				}
			}

			return false;
		}

		/**
		 * Handles save-and-verify.
		 *
		 * @return void
		 */
		public static function handle_save(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_SAVE );

			$was_verified = Npcink_Cloud_Addon_Settings::is_verified();
			$base_url = isset( $_POST['base_url'] ) ? sanitize_text_field( wp_unslash( $_POST['base_url'] ) ) : '';
			$api_key  = isset( $_POST['api_key'] ) ? sanitize_text_field( wp_unslash( $_POST['api_key'] ) ) : '';
			$timeout  = isset( $_POST['timeout'] ) ? absint( wp_unslash( $_POST['timeout'] ) ) : 8;
			$monitoring_enabled = ! empty( $_POST['monitoring_enabled'] );
			$site_knowledge_delivery_enabled = ! empty( $_POST['site_knowledge_delivery_enabled'] );
			$site_knowledge_generation_reference_enabled = ! empty( $_POST['site_knowledge_generation_reference_enabled'] );
			$wordpress_ai_connector_enabled = ! empty( $_POST['wordpress_ai_connector_enabled'] );

			$payload = array(
				'base_url'           => $base_url,
				'api_key'            => $api_key,
				'timeout'            => $timeout,
				'monitoring_enabled' => $monitoring_enabled,
				'site_knowledge_delivery_enabled' => $site_knowledge_delivery_enabled,
				'site_knowledge_generation_reference_enabled' => $site_knowledge_generation_reference_enabled,
				'wordpress_ai_connector_enabled' => $wordpress_ai_connector_enabled,
			);

			$settings = Npcink_Cloud_Addon_Settings::build_settings_from_admin_payload( $payload );
			if ( is_wp_error( $settings ) ) {
				self::set_admin_notice( 'error', $settings->get_error_message() );
				self::redirect_to_page();
			}

			self::persist_and_verify_settings( $settings, __( 'Cloud settings saved and verified.', 'npcink-cloud-addon' ) );
			self::maybe_prompt_for_monitoring_consent( $was_verified );
			self::redirect_to_page();
		}

		/**
		 * Handles the Cloud Portal authorization callback.
		 *
		 * @return void
		 */
		public static function handle_complete_auth(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			$was_verified = Npcink_Cloud_Addon_Settings::is_verified();
			$raw_state = filter_input( INPUT_GET, 'state', FILTER_UNSAFE_RAW );
			$raw_code = filter_input( INPUT_GET, 'code', FILTER_UNSAFE_RAW );
			$state = is_string( $raw_state ) ? sanitize_text_field( wp_unslash( $raw_state ) ) : '';
			$code  = is_string( $raw_code ) ? sanitize_text_field( wp_unslash( $raw_code ) ) : '';
			$auth_state = self::consume_authorization_state( $state );
			if ( empty( $auth_state ) || '' === $code ) {
				self::set_admin_notice( 'error', __( 'Cloud authorization expired or is invalid. Start the connection again.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'status' );
			}

			$base_url = (string) ( $auth_state['base_url'] ?? '' );
			$exchange = self::exchange_authorization_code( $base_url, $code, $state );
			if ( is_wp_error( $exchange ) ) {
				self::set_admin_notice( 'error', $exchange->get_error_message() );
				self::redirect_to_page( 'status' );
			}

			$settings = Npcink_Cloud_Addon_Settings::build_settings_from_admin_payload(
				array(
					'base_url' => $base_url,
					'api_key'  => (string) ( $exchange['cloud_api_key'] ?? '' ),
					'timeout'  => (int) ( Npcink_Cloud_Addon_Settings::get_settings()['timeout'] ?? 8 ),
					'activation_state' => (string) ( $exchange['activation_state'] ?? '' ),
					'activation_reason' => (string) ( $exchange['activation_reason'] ?? '' ),
				)
			);
			if ( is_wp_error( $settings ) ) {
				self::set_admin_notice( 'error', $settings->get_error_message() );
				self::redirect_to_page( 'status' );
			}

			if ( 'inactive' === (string) ( $exchange['activation_state'] ?? '' ) ) {
				if ( ! Npcink_Cloud_Addon_Settings::write_settings( $settings ) ) {
					self::set_admin_notice(
						'error',
						__( 'Cloud credentials could not be stored securely. The existing connection was not changed. Check the WordPress security salts and reconnect.', 'npcink-cloud-addon' )
					);
				} else {
					self::set_admin_notice(
						'warning',
						__( 'Cloud connection completed. This site is bound but not active because no active-site slot was available. Activate it in Npcink Cloud, then verify the connection here.', 'npcink-cloud-addon' )
					);
				}
				self::redirect_to_page( 'status' );
			}

			self::persist_and_verify_settings( $settings, __( 'Cloud connection completed and verified.', 'npcink-cloud-addon' ) );
			self::maybe_prompt_for_monitoring_consent( $was_verified );

			self::redirect_to_page( 'permissions' );
		}

		/**
		 * Starts authorization against the configured/default Cloud endpoint.
		 *
		 * @return void
		 */
		public static function handle_start_auth(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_START_AUTH );
			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			self::redirect_to_cloud_authorization( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) );
		}

		/**
		 * Starts authorization against an administrator-supplied Cloud endpoint.
		 *
		 * @return void
		 */
		public static function handle_start_custom_auth(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_START_CUSTOM_AUTH );

			$base_url = isset( $_POST['self_hosted_base_url'] )
				? sanitize_text_field( wp_unslash( $_POST['self_hosted_base_url'] ) )
				: '';
			if ( '' === trim( $base_url ) ) {
				self::set_admin_notice( 'error', __( 'Enter a Cloud Base URL before starting self-hosted authorization.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'connect' );
			}

			$settings = Npcink_Cloud_Addon_Settings::build_settings_from_admin_payload(
				array(
					'base_url' => $base_url,
				)
			);
			if ( is_wp_error( $settings ) ) {
				self::set_admin_notice( 'error', $settings->get_error_message() );
				self::redirect_to_page( 'connect' );
			}

			$normalized_base_url = (string) ( $settings['base_url'] ?? '' );
			if ( '' === $normalized_base_url ) {
				self::set_admin_notice( 'error', __( 'Cloud Base URL must use HTTPS unless it points to localhost or 127.0.0.1.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'connect' );
			}

			self::redirect_to_cloud_authorization( $normalized_base_url );
		}

		/**
		 * Redirects to one validated Cloud authorization host.
		 *
		 * @param string $base_url Normalized Cloud base URL.
		 * @return void
		 */
		private static function redirect_to_cloud_authorization( string $base_url ): void {
			$authorization_url  = esc_url_raw( self::build_authorization_url_for_base_url( $base_url ) );
			$authorization_host = wp_parse_url( $authorization_url, PHP_URL_HOST );
			if ( ! is_string( $authorization_host ) || '' === trim( $authorization_host ) ) {
				self::set_admin_notice( 'error', __( 'Cloud Base URL must use HTTPS unless it points to localhost or 127.0.0.1.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'connect' );
			}

			$authorization_host = strtolower( $authorization_host );
			$allow_cloud_host   = static function ( array $hosts ) use ( $authorization_host ): array {
				$hosts[] = $authorization_host;
				return array_values( array_unique( $hosts ) );
			};

			add_filter( 'allowed_redirect_hosts', $allow_cloud_host );
			wp_safe_redirect( $authorization_url, 302, 'Npcink Cloud Addon' );
			remove_filter( 'allowed_redirect_hosts', $allow_cloud_host );
			exit;
		}

		/**
		 * Persists settings and immediately updates the verified state.
		 *
		 * @param array<string,mixed> $settings Settings payload.
		 * @param string              $success_message Success notice.
		 * @return void
		 */
		private static function persist_and_verify_settings( array $settings, string $success_message ): void {
			$current = Npcink_Cloud_Addon_Settings::get_settings();
			$same_connection = self::same_connection_credentials( $current, $settings );
			if ( ! Npcink_Cloud_Addon_Settings::can_store_settings( $settings )
				|| ( $same_connection && ! Npcink_Cloud_Addon_Settings::write_settings( $settings ) ) ) {
				self::set_admin_notice(
					'error',
					__( 'Cloud credentials could not be stored securely. The existing connection was not changed. Check the WordPress security salts and reconnect.', 'npcink-cloud-addon' )
				);
				return;
			}

			$client = new Npcink_Cloud_Runtime_Client( $settings );
			$probe = $client->probe_connectivity();
			if ( ! empty( $probe['ok'] ) ) {
				$settings['verified'] = true;
				$settings['verified_at'] = gmdate( 'Y-m-d H:i:s' ) . ' UTC';
				$settings['last_verification_error'] = '';
				$settings['activation_state'] = 'active';
				$settings['activation_reason'] = '';
				if ( ! Npcink_Cloud_Addon_Settings::write_settings( $settings ) ) {
					self::set_admin_notice(
						'error',
						__( 'Cloud credentials could not be stored securely. The existing connection was not changed. Check the WordPress security salts and reconnect.', 'npcink-cloud-addon' )
					);
					return;
				}
				Npcink_Cloud_Observability_Collector::sync_schedule();
				Npcink_Cloud_Observability_Collector::project_monitoring_state(
					! empty( $settings['monitoring_enabled'] )
				);
				$summary = is_array( $probe['entitlement_response'] ?? null ) && ! empty( $probe['entitlement_response'] )
					? Npcink_Cloud_Entitlement_Summary::cache_summary_from_response( $probe['entitlement_response'], $settings )
					: Npcink_Cloud_Entitlement_Summary::refresh();
				if ( empty( $summary['available'] ) ) {
					self::set_admin_notice(
						'warning',
						sprintf(
							/* translators: %s: entitlement refresh message. */
							__( 'Cloud settings verified, but entitlement summary could not refresh: %s', 'npcink-cloud-addon' ),
							(string) ( $summary['message'] ?? __( 'Unknown entitlement refresh result.', 'npcink-cloud-addon' ) )
						)
					);
					return;
				}

				self::set_admin_notice( 'success', $success_message );
				return;
			}

			$message = self::format_probe_failure_message( $probe );
			if ( ! $same_connection ) {
				self::set_admin_notice(
					'error',
					sprintf(
						/* translators: %s: Cloud connectivity error. */
						__( 'The replacement Cloud connection could not be verified, so the existing connection was kept: %s', 'npcink-cloud-addon' ),
						$message
					)
				);
				return;
			}
			if ( 'auth.site_inactive' === (string) ( $probe['auth_error_code'] ?? '' ) ) {
				$inactive_settings = Npcink_Cloud_Addon_Settings::get_settings();
				$inactive_settings['verified'] = false;
				$inactive_settings['verified_at'] = '';
				$inactive_settings['last_verification_error'] = '';
				$inactive_settings['activation_state'] = 'inactive';
				$inactive_settings['activation_reason'] = 'cloud_site_inactive';
				if ( ! Npcink_Cloud_Addon_Settings::write_settings( $inactive_settings ) ) {
					self::set_admin_notice( 'error', __( 'Cloud verification completed, but the activation state could not be stored securely.', 'npcink-cloud-addon' ) );
				}
				return;
			}
			$verification = Npcink_Cloud_Addon_Settings::mark_verification_result( false, $message );
			if ( is_wp_error( $verification ) ) {
				self::set_admin_notice( 'error', $verification->get_error_message() );
				return;
			}
			Npcink_Cloud_Observability_Collector::sync_schedule();
			// The connection summary always renders the persisted verification
			// failure, so a redirect notice would duplicate the same message.
		}

		/**
		 * Compares only connection-defining values without exposing them.
		 *
		 * @param array<string,mixed> $current Current settings.
		 * @param array<string,mixed> $candidate Candidate settings.
		 * @return bool
		 */
		private static function same_connection_credentials( array $current, array $candidate ): bool {
			foreach ( array( 'base_url', 'site_id', 'key_id', 'secret' ) as $key ) {
				if ( (string) ( $current[ $key ] ?? '' ) !== (string) ( $candidate[ $key ] ?? '' ) ) {
					return false;
				}
			}

			return true;
		}

		/**
		 * Handles local Cloud connection disconnect.
		 *
		 * @return void
		 */
		public static function handle_disconnect(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

				check_admin_referer( self::ACTION_DISCONNECT );

				$settings = Npcink_Cloud_Addon_Settings::get_settings();
				Npcink_Cloud_Runtime_Callback::unregister();
				Npcink_Cloud_Addon_Cleanup::delete_all( $settings );

			self::set_admin_notice(
				'success',
				__( 'Cloud connection disconnected locally. Stored credentials and addon-owned buffers were cleared.', 'npcink-cloud-addon' )
			);
			self::redirect_to_page( 'status' );
		}

		/**
		 * Handles one local permission switch.
		 *
		 * @return void
		 */
		public static function handle_update_local_permission(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_UPDATE_LOCAL_PERMISSION );

			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				self::set_admin_notice( 'error', __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'status' );
			}

			$permission = isset( $_POST['permission'] ) ? sanitize_key( wp_unslash( $_POST['permission'] ) ) : '';
			$definitions = self::get_local_permission_definitions();
			if ( ! isset( $definitions[ $permission ] ) ) {
				self::set_admin_notice( 'error', __( 'The requested local permission is not supported.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'status' );
			}

			$enabled = ! empty( $_POST['enabled'] );
			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			$settings[ $permission ] = $enabled;
			if ( 'monitoring_enabled' === $permission ) {
				self::clear_monitoring_consent_prompt();
			}
			if ( 'site_knowledge_delivery_enabled' === $permission ) {
				$settings['site_knowledge_generation_reference_enabled'] = $enabled;
			}
			if ( ! Npcink_Cloud_Addon_Settings::write_settings( $settings ) ) {
				self::set_local_permission_feedback(
					'error',
					__( 'The local permission could not be saved securely. No permission or background delivery state was changed.', 'npcink-cloud-addon' ),
					$permission
				);
				self::redirect_to_local_permission( $permission );
			}
			self::sync_local_permission_effects( $permission );

			self::set_local_permission_feedback(
				'success',
				sprintf(
					/* translators: 1: local permission label, 2: enabled or disabled state. */
					__( '%1$s %2$s.', 'npcink-cloud-addon' ),
					(string) $definitions[ $permission ]['label'],
					$enabled ? __( 'enabled', 'npcink-cloud-addon' ) : __( 'disabled', 'npcink-cloud-addon' )
				),
				$permission
			);
			self::redirect_to_local_permission( $permission );
		}

		/**
		 * Dismisses the one-time monitoring consent prompt after connection.
		 *
		 * @return void
		 */
		public static function handle_dismiss_monitoring_prompt(): void {
			if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_DISMISS_MONITORING_PROMPT );
			self::clear_monitoring_consent_prompt();
			self::redirect_to_page( 'permissions' );
		}

		/**
		 * Handles a manual bounded Site Knowledge public content refresh request.
		 *
		 * @return void
		 */
		public static function handle_refresh_site_knowledge(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_REFRESH_SITE_KNOWLEDGE );

			$result = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_public_refresh();
			self::set_admin_notice( ! empty( $result['ok'] ) ? 'success' : 'error', (string) $result['message'] );
			self::redirect_to_page( 'site_knowledge' );
		}

		/** Handles the bounded local media recognition refresh. */
		public static function handle_refresh_site_media_index(): void {
			if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_REFRESH_SITE_MEDIA_INDEX );
			$current_user_id = get_current_user_id();
			$current = self::get_media_index_status();
			$current_state = sanitize_key( (string) ( $current['state'] ?? 'not_started' ) );
			if ( self::resume_active_media_recognition_plan( $current_user_id ) ) {
				self::set_admin_notice( 'warning', __( 'Media recognition is already in progress. Please wait for it to finish.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'site_knowledge' );
				return;
			}

			$is_continuation = in_array( $current_state, array( 'partial', 'error' ), true );
			$plan = self::get_media_recognition_plan();
			if ( $is_continuation || in_array( sanitize_key( (string) ( $plan['state'] ?? '' ) ), array( 'paused', 'error' ), true ) ) {
				self::resume_paused_media_recognition_plan( $plan, $current, $current_user_id );
				self::set_admin_notice( 'success', __( 'Media recognition retry is scheduled. Background processing will continue automatically; no further click is needed.', 'npcink-cloud-addon' ) );
			} else {
				self::start_media_recognition_rescan( $plan, array(), $current_user_id, true );
				self::set_admin_notice( 'success', __( 'Media recognition is scheduled. Background processing will start automatically; no further click is needed.', 'npcink-cloud-addon' ) );
			}
			self::redirect_to_page( 'site_knowledge' );
		}

		/** Continues one active media plan after the previous Cloud run settles. */
		public static function process_media_recognition_plan(): void {
			// WP-Cron can overlap on busy sites; only one callback may advance the cursor.
			$lock_started = time();
			$lock_token = $lock_started . ':' . str_replace( '.', '', uniqid( '', true ) );
			if ( ! add_option( self::MEDIA_PLAN_LOCK, $lock_token, '', false ) ) {
				$existing_lock_started = self::media_recognition_plan_lock_started_at( get_option( self::MEDIA_PLAN_LOCK, 0 ) );
				if ( $existing_lock_started > 0 && $existing_lock_started + 600 > time() ) {
					return;
				}
				delete_option( self::MEDIA_PLAN_LOCK );
				if ( ! add_option( self::MEDIA_PLAN_LOCK, $lock_token, '', false ) ) {
					return;
				}
			}
			register_shutdown_function( static function () use ( $lock_token ): void { self::release_media_recognition_plan_lock( $lock_token, true ); } );
			$advanced_normally = false;
			try {
				self::advance_media_recognition_plan();
				$advanced_normally = true;
			} finally {
				self::release_media_recognition_plan_lock( $lock_token, ! $advanced_normally );
			}
		}

		/** Restores the single continuation event for an active orphaned plan. */
		public static function ensure_media_recognition_plan_schedule(): void {
			$plan = self::get_media_recognition_plan();
			if ( empty( $plan['active'] ) || false !== wp_next_scheduled( self::MEDIA_PLAN_CRON ) ) {
				return;
			}

			$lock_started = self::media_recognition_plan_lock_started_at( get_option( self::MEDIA_PLAN_LOCK, 0 ) );
			if ( $lock_started > 0 && $lock_started + 600 > time() ) {
				return;
			}

			$delay = 60;
			$next_eligible_at = strtotime( (string) ( $plan['next_eligible_at'] ?? '' ) );
			if ( false !== $next_eligible_at && $next_eligible_at > time() ) {
				$delay = max( 60, $next_eligible_at - time() );
			}
			self::schedule_media_recognition_plan( $delay );
		}

		/** Advances a plan while the caller holds the single-site lock. */
		private static function advance_media_recognition_plan(): void {
			$plan = self::get_media_recognition_plan();
			if ( empty( $plan['active'] ) ) {
				return;
			}
			if ( ! empty( $plan['next_eligible_at'] ) && strtotime( (string) $plan['next_eligible_at'] ) > time() ) {
				self::schedule_media_recognition_plan( max( 60, strtotime( (string) $plan['next_eligible_at'] ) - time() ) );
				return;
			}
			$status = self::get_media_index_status();
			if ( self::pause_media_recognition_plan_after_transport_retry_limit( $plan, $status ) ) {
				return;
			}
			if ( in_array( (string) ( $status['state'] ?? '' ), array( 'processing', 'waiting_next_day' ), true ) && ! empty( $status['run_id'] ) ) {
				$refreshed = self::refresh_media_index_status_projection();
				if ( is_wp_error( $refreshed ) ) {
					self::schedule_media_recognition_plan( 60 );
					return;
				}
				$status = $refreshed;
				$plan = self::get_media_recognition_plan();
				if ( ! empty( $status['next_eligible_at'] ) && strtotime( (string) $status['next_eligible_at'] ) > time() ) {
					$plan['next_eligible_at'] = (string) $status['next_eligible_at'];
					$plan['state'] = 'waiting_next_day';
					self::set_media_recognition_plan( $plan );
					self::schedule_media_recognition_plan( max( 60, strtotime( (string) $status['next_eligible_at'] ) - time() ) );
					return;
				}
				if ( 'processing' === (string) ( $status['state'] ?? '' ) ) {
					$plan['state'] = 'processing';
					unset( $plan['next_eligible_at'], $plan['pause_reason'] );
					self::set_media_recognition_plan( $plan );
					self::schedule_media_recognition_plan( 60 );
					return;
				}
			}
			$state = sanitize_key( (string) ( $status['state'] ?? 'not_started' ) );
			if ( 'complete' === $state ) {
				if ( self::restart_pending_media_recognition_rescan( $plan ) ) {
					return;
				}
				$plan['active'] = false;
				$plan['state'] = 'complete';
				self::set_media_recognition_plan( $plan );
				return;
			}
			if ( 'error' === $state ) {
				$plan['active'] = false;
				$plan['state'] = 'error';
				$plan['pause_reason'] = sanitize_key( (string) ( $status['error_code'] ?? 'run_error' ) );
				self::set_media_recognition_plan( $plan );
				return;
			}
			if ( 'partial' !== $state ) {
				self::schedule_media_recognition_plan( 60 );
				return;
			}

			$page = max( 1, absint( $status['next_page'] ?? $plan['next_page'] ?? 0 ) );
			$previous_user_id = get_current_user_id();
			$plan_user_id = absint( $plan['initiated_by'] ?? 0 );
			if ( $plan_user_id > 0 && function_exists( 'wp_set_current_user' ) ) {
				wp_set_current_user( $plan_user_id );
			}
			try {
				$per_page = self::media_recognition_plan_per_page( $plan, $status );
				Npcink_Cloud_Runtime_Callback::ensure_registered();
				$result = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_media_index_refresh( $page, $per_page, (string) ( $plan['upload_attempt_id'] ?? $plan['plan_id'] ?? '' ) );
			} finally {
				if ( function_exists( 'wp_set_current_user' ) ) {
					wp_set_current_user( $previous_user_id );
				}
			}
			if ( empty( $result['ok'] ) ) {
				$reason = sanitize_key( (string) ( $result['source_error_code'] ?? $result['code'] ?? 'dispatch_failed' ) );
				if ( self::is_retryable_media_recognition_transport_failure( $reason ) ) {
					$retry = self::media_recognition_transport_retry_state( $plan, $status, $reason );
					self::set_media_index_status( $retry['status'] );
					self::set_media_recognition_plan( $retry['plan'] );
					if ( empty( $retry['exhausted'] ) ) {
						self::schedule_media_recognition_plan( $retry['delay'] );
						self::record_media_recognition_event( 'retry_scheduled', 'retrying', '', 0, 0, 0, $reason );
					} else {
						self::record_media_recognition_event( 'failed', 'failed', '', 0, 0, 0, $reason );
					}
					return;
				}
				$plan['state'] = 'paused';
				$plan['active'] = false;
				$plan['pause_reason'] = $reason;
				unset( $plan['next_eligible_at'] );
				$status['state'] = 'error';
				$status['error_code'] = $reason;
				$status['error'] = self::media_recognition_error_message( $reason, (string) ( $result['message'] ?? '' ) );
				$status['updated_at'] = current_time( 'mysql' );
				self::set_media_index_status( $status );
				self::set_media_recognition_plan( $plan );
				return;
			}
			$run_id = sanitize_text_field( (string) ( $result['run_id'] ?? '' ) );
			$total = absint( $result['total'] ?? $status['total'] ?? 0 );
			$has_more = ! empty( $result['has_more'] );
			$batch_size = absint( $result['sent_count'] ?? 0 );
			$batch_reused_count = absint( $result['reused_count'] ?? 0 );
			$batch_screened_count = absint( $result['screened_count'] ?? 0 );
			$batch_recognized_count = absint( $result['recognized_count'] ?? 0 );
			$batch_evidence_count = absint( $result['selected_count'] ?? 0 );
			$per_page = max( 1, absint( $result['per_page'] ?? $per_page ) );
			$previous_indexed = absint( $status['indexed'] ?? 0 );
			$previous_successful = absint( $status['successful'] ?? 0 );
			$previous_evidence = absint( $status['evidence'] ?? 0 );
			$previous_reused_count = absint( $status['reused_count'] ?? 0 );
			$previous_recognized_count = absint( $status['recognized_count'] ?? 0 );
			$previous_screened_count = absint( $status['screened_count'] ?? 0 );
			$polling_completed_before = min( $total, $previous_indexed + $batch_reused_count + $batch_screened_count );
			$polling_successful_before = $previous_successful + $batch_reused_count;
			$polling_evidence_before = $previous_evidence + $batch_reused_count;
			$page_count = absint( $result['page_count'] ?? 0 );
			$processed = '' !== $run_id ? $polling_completed_before : min( $total, $previous_indexed + $page_count );
			$status = array_merge( $status, array(
				'state' => '' !== $run_id ? 'processing' : ( $has_more ? 'partial' : 'complete' ),
				'indexed' => $processed,
				'completed_before' => '' !== $run_id ? $polling_completed_before : $previous_indexed,
				'batch_size' => $batch_size,
				'reused_count' => $previous_reused_count + $batch_reused_count,
				'recognized_count' => $previous_recognized_count + ( '' === $run_id ? $batch_recognized_count : 0 ),
				'screened_count' => $previous_screened_count + $batch_screened_count,
				'batch_reused_count' => '' !== $run_id ? $batch_reused_count : 0,
				'batch_screened_count' => '' !== $run_id ? $batch_screened_count : 0,
				'per_page' => $per_page,
				'successful' => '' !== $run_id ? $polling_successful_before : $previous_successful + $batch_reused_count + $batch_recognized_count,
				'successful_before' => '' !== $run_id ? $polling_successful_before : $previous_successful,
				'evidence' => '' !== $run_id ? $polling_evidence_before : $previous_evidence + $batch_evidence_count,
				'evidence_before' => '' !== $run_id ? $polling_evidence_before : $previous_evidence,
				'page' => $page,
				'next_page' => $has_more ? max( $page + 1, absint( $result['next_page'] ?? 0 ) ) : 0,
				'has_more' => $has_more,
				'total' => $total,
				'percent' => $total > 0 ? min( 100, (int) floor( $processed / $total * 100 ) ) : 0,
				'duration_seconds' => max( 0, (float) ( $status['duration_seconds'] ?? 0 ) ) + max( 0, (float) ( $result['duration_seconds'] ?? 0 ) ),
				'duration_before' => max( 0, (float) ( $status['duration_seconds'] ?? 0 ) ),
				'run_id' => $run_id,
				'projected_run_id' => '',
				'terminal_event_recorded' => false,
				'error_code' => '',
				'error' => '',
				'updated_at' => current_time( 'mysql' ),
			) );
			self::set_media_index_status( $status );
			$plan = self::get_media_recognition_plan();
			$plan['state'] = '' !== $run_id ? 'processing' : ( $has_more ? 'partial' : 'complete' );
			$plan['current_run_id'] = $run_id;
			$plan['current_page'] = $page;
			$plan['next_page'] = $has_more ? max( $page + 1, absint( $result['next_page'] ?? 0 ) ) : 0;
			$plan['per_page'] = $per_page;
			unset( $plan['pause_reason'] );
			unset( $plan['next_eligible_at'] );
			$plan['total_estimate'] = $total;
			$plan['processed_count'] = $processed;
			$plan['updated_at'] = current_time( 'mysql' );
			unset( $plan['transport_retry_count'], $plan['last_transport_error_code'] );
			self::set_media_recognition_plan( $plan );
			if ( 'complete' === $plan['state'] ) {
				if ( self::restart_pending_media_recognition_rescan( $plan ) ) {
					return;
				}
				$plan['active'] = false;
				self::set_media_recognition_plan( $plan );
			} else {
				self::schedule_media_recognition_plan( 30 );
			}
		}

		/** Reads an existing media recognition Cloud run without creating a task. */
		public static function handle_refresh_site_media_status(): void {
			if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}
			check_admin_referer( self::ACTION_REFRESH_SITE_MEDIA_STATUS );
			$status = self::refresh_media_index_status_projection();
			if ( is_wp_error( $status ) ) {
				self::set_admin_notice( 'error', __( 'Media recognition progress could not be refreshed. The current task is unchanged; try again shortly.', 'npcink-cloud-addon' ) );
				self::redirect_to_page( 'site_knowledge' );
				return;
			}
			if ( 'error' === sanitize_key( (string) ( $status['state'] ?? '' ) ) ) {
				self::set_admin_notice( 'error', (string) ( $status['error'] ?? __( 'Cloud media recognition did not complete. Retry this batch later.', 'npcink-cloud-addon' ) ) );
			} else {
				self::set_admin_notice( 'success', __( 'Media recognition progress refreshed.', 'npcink-cloud-addon' ) );
			}
			self::redirect_to_page( 'site_knowledge' );
		}

		/** Polls an existing media task for the status table. */
		public static function handle_poll_site_media_status(): void {
			if ( ! current_user_can( self::MENU_CAPABILITY ) ) {
				wp_send_json_error( array( 'message' => __( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) ), 403 );
			}
			check_ajax_referer( self::ACTION_POLL_SITE_MEDIA_STATUS, 'nonce' );
			$status = self::refresh_media_index_status_projection();
			if ( is_wp_error( $status ) ) {
				wp_send_json_error( array( 'message' => __( 'Progress could not be refreshed automatically. Use Refresh progress to try again.', 'npcink-cloud-addon' ) ), 502 );
			}
			wp_send_json_success( $status );
		}

		/** @return array<string,mixed>|WP_Error */
		private static function refresh_media_index_status_projection() {
			$status = self::get_media_index_status();
			$run_id = sanitize_text_field( (string) ( $status['run_id'] ?? '' ) );
			if ( '' === $run_id ) {
				return new WP_Error( 'cloud_media_recognition_run_missing', __( 'There is no active media recognition task to refresh.', 'npcink-cloud-addon' ) );
			}
			if ( $run_id === sanitize_text_field( (string) ( $status['projected_run_id'] ?? '' ) ) ) {
				return $status;
			}
			$client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
			$run = $client->get_run( $run_id );
			if ( is_wp_error( $run ) ) {
				if ( self::is_terminal_media_run_read_error( $run ) ) {
					return self::mark_media_index_projection_error( $status, $run );
				}
				return $run;
			}
			$run_data = is_array( $run['data'] ?? null ) ? $run['data'] : $run;
			$run_status = sanitize_key( (string) ( $run_data['status'] ?? 'processing' ) );
			$run_lifecycle = is_array( $run_data['run_lifecycle'] ?? null ) ? $run_data['run_lifecycle'] : array();
			$run_result = $client->get_run_result( $run_id );
			$terminal_success = in_array( $run_status, array( 'succeeded', 'success', 'completed' ), true );
			$terminal_error = in_array( $run_status, array( 'failed', 'error', 'canceled' ), true );
			if ( $terminal_success && is_wp_error( $run_result ) && self::is_terminal_media_run_read_error( $run_result ) ) {
				return self::mark_media_index_projection_error( $status, $run_result );
			}
			if ( $terminal_success && is_wp_error( $run_result ) ) {
				return $run_result;
			}
			$run_result_data = is_array( $run_result ) && is_array( $run_result['data'] ?? null ) ? $run_result['data'] : ( is_array( $run_result ) ? $run_result : array() );
			$run_result_payload = is_array( $run_result_data['result'] ?? null ) ? $run_result_data['result'] : array();
			$progress = is_array( $run_result_payload['progress'] ?? null ) ? $run_result_payload['progress'] : array();
			$completed_before = absint( $status['completed_before'] ?? $status['indexed'] ?? 0 );
			$batch_reused_count = absint( $status['batch_reused_count'] ?? $status['reused_count'] ?? 0 );
			$batch_screened_count = absint( $status['batch_screened_count'] ?? $status['screened_count'] ?? 0 );
			$committed_before = max( 0, $completed_before - $batch_reused_count - $batch_screened_count );
			$successful_committed_before = max( 0, absint( $status['successful_before'] ?? 0 ) - $batch_reused_count );
			$evidence_committed_before = max( 0, absint( $status['evidence_before'] ?? 0 ) - $batch_reused_count );
			$batch_processed = absint( $progress['processed_items'] ?? $progress['processed_documents'] ?? 0 );
			$batch_total = max( absint( $status['batch_size'] ?? 0 ), absint( $progress['total_items'] ?? $progress['total_documents'] ?? 0 ) );
			$total = max( absint( $status['total'] ?? 0 ), $completed_before + absint( $progress['total_items'] ?? $progress['total_documents'] ?? 0 ) );
			$status['indexed'] = min( $total, $completed_before + $batch_processed );
			$status['total'] = $total;
			$status['percent'] = $total > 0 ? min( 100, (int) floor( $status['indexed'] / $total * 100 ) ) : 0;
			$status['successful'] = absint( $status['successful_before'] ?? 0 ) + absint( $progress['successful_items'] ?? 0 );
			$status['failed'] = absint( $status['failed_before'] ?? 0 ) + absint( $progress['failed_items'] ?? 0 );
			$status['items_per_minute'] = max( 0, (float) ( $progress['items_per_minute'] ?? 0 ) );
			$status['duration_seconds'] = max( 0, (float) ( $status['duration_before'] ?? 0 ) ) + max( 0, (float) ( $progress['duration_seconds'] ?? 0 ) );
			if ( $status['items_per_minute'] > 0 && $batch_processed < $batch_total ) {
				$remaining_seconds = (int) ceil( ( $batch_total - $batch_processed ) / $status['items_per_minute'] * 60 );
				$status['eta_at'] = gmdate( 'c', time() + max( 1, $remaining_seconds ) );
			} else {
				$status['eta_at'] = $terminal_success ? sanitize_text_field( (string) ( $progress['eta_at'] ?? '' ) ) : '';
			}
			if ( $terminal_success ) {
				$items = is_array( $run_result_payload['items'] ?? null ) ? $run_result_payload['items'] : array();
				if ( empty( $progress ) && ! empty( $items ) ) {
					$batch_processed = count( $items );
					$status['indexed'] = min( $total, $completed_before + $batch_processed );
					$status['successful'] = absint( $status['successful_before'] ?? 0 ) + $batch_processed;
					$status['percent'] = $total > 0 ? min( 100, (int) floor( $status['indexed'] / $total * 100 ) ) : 0;
				}
				$batch_evidence = count( array_filter( $items, static function ( $item ): bool { return is_array( $item ) && '' !== trim( (string) ( $item['visual_summary'] ?? $item['alt_text_basis'] ?? '' ) ); } ) );
				$status['evidence'] = absint( $status['evidence_before'] ?? 0 ) + $batch_evidence;
				if ( absint( $progress['failed_items'] ?? 0 ) > 0 ) {
					$status['indexed'] = $committed_before;
					$status['successful'] = $successful_committed_before;
					$status['evidence'] = $evidence_committed_before;
					$status['reused_count'] = max( 0, absint( $status['reused_count'] ?? 0 ) - $batch_reused_count );
					$status['screened_count'] = max( 0, absint( $status['screened_count'] ?? 0 ) - $batch_screened_count );
					$status['percent'] = $total > 0 ? min( 100, (int) floor( $status['indexed'] / $total * 100 ) ) : 0;
					$status['state'] = 'error';
					$status['error_code'] = 'media_recognition_batch_partial_failure';
				} else {
					$status['recognized_count'] = absint( $status['recognized_count'] ?? 0 ) + absint( $progress['successful_items'] ?? $batch_processed );
					$status['state'] = ! empty( $status['has_more'] ) && $status['indexed'] < $total ? 'partial' : 'complete';
				}
				$status['batch_reused_count'] = 0;
				$status['batch_screened_count'] = 0;
			} elseif ( $terminal_error ) {
				$status['indexed'] = $committed_before;
				$status['successful'] = $successful_committed_before;
				$status['evidence'] = $evidence_committed_before;
				$status['reused_count'] = max( 0, absint( $status['reused_count'] ?? 0 ) - $batch_reused_count );
				$status['screened_count'] = max( 0, absint( $status['screened_count'] ?? 0 ) - $batch_screened_count );
				$status['batch_reused_count'] = 0;
				$status['batch_screened_count'] = 0;
				$status['percent'] = $total > 0 ? min( 100, (int) floor( $status['indexed'] / $total * 100 ) ) : 0;
				$status['state'] = 'error';
			} else {
				$status['state'] = 'processing';
			}
			$status['updated_at'] = current_time( 'mysql' );
			$worker_eligible_at = sanitize_text_field( (string) ( $run_lifecycle['worker_eligible_at'] ?? ( $run_data['worker_eligible_at'] ?? '' ) ) );
			if ( 'error' !== $status['state'] && '' !== $worker_eligible_at && strtotime( $worker_eligible_at ) > time() ) {
				$status['next_eligible_at'] = $worker_eligible_at;
				$status['state'] = 'waiting_next_day';
			} else {
				unset( $status['next_eligible_at'] );
			}
			if ( 'error' === $status['state'] ) {
				if ( empty( $status['error_code'] ) ) {
					$status['error_code'] = sanitize_key( (string) ( $run_data['error_code'] ?? '' ) );
				}
				$status['error'] = self::media_recognition_error_message( $status['error_code'] );
			}
			if ( in_array( $status['state'], array( 'complete', 'partial', 'error' ), true ) && empty( $status['terminal_event_recorded'] ) ) {
				self::record_media_recognition_event(
					'error' === $status['state'] ? 'failed' : 'completed',
					$status['state'],
					$run_id,
					$batch_processed,
					absint( $progress['failed_items'] ?? ( 'error' === $status['state'] ? ( $status['batch_size'] ?? 0 ) : 0 ) ),
					(int) round( max( 0, (float) ( $progress['duration_seconds'] ?? 0 ) ) * 1000 ),
					(string) ( $status['error_code'] ?? '' )
				);
				$status['terminal_event_recorded'] = true;
			}
			if ( in_array( $status['state'], array( 'complete', 'partial', 'error' ), true ) ) {
				$status['projected_run_id'] = $run_id;
			}
			self::set_media_index_status( $status );
			if ( 'error' === $status['state'] ) {
				$plan = self::get_media_recognition_plan();
				if ( ! empty( $plan['active'] ) ) {
					$plan['active'] = false;
					$plan['state'] = 'error';
					$plan['pause_reason'] = sanitize_key( (string) ( $status['error_code'] ?? 'run_error' ) );
					self::set_media_recognition_plan( $plan );
				}
			}
			return $status;
		}

		/** Returns whether a Cloud read proves that the saved run can no longer be resumed. */
		private static function is_terminal_media_run_read_error( WP_Error $error ): bool {
			$data = $error->get_error_data();
			$http_status = is_array( $data ) ? absint( $data['cloud_http_status'] ?? $data['status'] ?? 0 ) : 0;
			$cloud_error_code = is_array( $data ) ? sanitize_key( (string) ( $data['cloud_error_code'] ?? '' ) ) : '';
			return in_array( $http_status, array( 404, 410 ), true )
				|| false !== strpos( $cloud_error_code, 'run_not_found' )
				|| false !== strpos( $cloud_error_code, 'result_expired' );
		}

		/** Projects an unavailable Cloud run into a retryable local terminal state. */
		private static function mark_media_index_projection_error( array $status, WP_Error $error ): array {
			$data = $error->get_error_data();
			$cloud_error_code = is_array( $data ) ? sanitize_key( (string) ( $data['cloud_error_code'] ?? '' ) ) : '';
			$error_code = '' !== $cloud_error_code ? $cloud_error_code : sanitize_key( (string) $error->get_error_code() );
			$status['state'] = 'error';
			$status['error_code'] = $error_code;
			$status['error'] = self::media_recognition_error_message( $error_code );
			$status['updated_at'] = current_time( 'mysql' );
			if ( empty( $status['terminal_event_recorded'] ) ) {
				self::record_media_recognition_event(
					'failed',
					'error',
					(string) ( $status['run_id'] ?? '' ),
					0,
					absint( $status['batch_size'] ?? 0 ),
					0,
					$error_code
				);
				$status['terminal_event_recorded'] = true;
			}
			self::set_media_index_status( $status );
			return $status;
		}

		/**
		 * Handles an administrator-requested Site Knowledge index operation.
		 *
		 * @return void
		 */
		public static function handle_manage_site_knowledge_index(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
			}

			check_admin_referer( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX );

			$operation = isset( $_POST['site_knowledge_index_action'] ) ? sanitize_key( wp_unslash( $_POST['site_knowledge_index_action'] ) ) : '';
			$confirmation = isset( $_POST['site_knowledge_confirmation'] ) ? sanitize_text_field( wp_unslash( $_POST['site_knowledge_confirmation'] ) ) : '';
			$result = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_index_operation( $operation, $confirmation );
			self::set_admin_notice( ! empty( $result['ok'] ) ? 'success' : 'error', (string) $result['message'] );
			self::redirect_to_page( 'site_knowledge' );
		}

			/**
			 * Handles an explicit administrator-triggered connector readiness test.
			 *
			 * @return void
			 */
			public static function handle_run_manual_readiness_test(): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
				}

				check_admin_referer( self::ACTION_RUN_MANUAL_READINESS_TEST );

				$settings = Npcink_Cloud_Addon_Settings::get_settings();
				$result = ( new Npcink_Cloud_Runtime_Client( $settings ) )->manual_readiness_test();
				self::set_manual_readiness_result( $result );

				$status = sanitize_key( (string) ( $result['bounded_status'] ?? $result['status'] ?? '' ) );
				if ( 'ready' === $status ) {
					self::set_admin_notice( 'success', __( 'Manual readiness test completed. Connector is ready.', 'npcink-cloud-addon' ) );
				} else {
					self::set_admin_notice( 'warning', self::format_readiness_detail( $result ) );
				}

				self::redirect_to_page( 'advanced', 'checks' );
			}

		/**
		 * Renders the settings page.
		 *
		 * @return void
		 */
		public static function render(): void {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}

			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			$state = Npcink_Cloud_Addon_Settings::get_credential_state();
			$entitlement = Npcink_Cloud_Entitlement_Summary::get_cached_summary();
			$monitoring = Npcink_Cloud_Observability_Collector::get_status();
			$site_knowledge = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
			$is_verified = ! empty( $state['verified'] );
			$active_tab = self::get_active_tab( $is_verified, $state );
			?>
			<div class="wrap npcink-cloud-addon">
				<h1><?php esc_html_e( 'Npcink Cloud Addon', 'npcink-cloud-addon' ); ?></h1>
				<p><?php esc_html_e( 'Cloud connector status and access settings for this WordPress site.', 'npcink-cloud-addon' ); ?></p>
				<?php self::render_admin_notice(); ?>

				<?php self::render_connection_summary( $settings, $state, $entitlement, $site_knowledge ); ?>
				<?php self::render_tab_navigation( $active_tab, $is_verified, $state ); ?>

				<?php if ( 'connect' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2><?php esc_html_e( 'Connect this site', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_cloud_authorization_panel( $settings, $state ); ?>
					</section>
				<?php elseif ( 'permissions' === $active_tab ) : ?>
					<?php self::render_overview_page( $settings, $state, $entitlement, $monitoring, $site_knowledge, $is_verified ); ?>
				<?php elseif ( 'site_knowledge' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2 class="screen-reader-text"><?php esc_html_e( 'Site Knowledge', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_site_knowledge_summary( $site_knowledge, $settings, $is_verified ); ?>
					</section>
				<?php elseif ( 'advanced' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2 class="screen-reader-text"><?php esc_html_e( 'Advanced and troubleshooting', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_advanced_page( $settings, $state, $entitlement, $monitoring, $is_verified ); ?>
					</section>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Returns the active settings tab.
		 *
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @param array<string,mixed> $state Credential state.
		 * @return string
		 */
		private static function get_active_tab( bool $is_verified, array $state ): string {
			$tabs = self::get_tab_labels( $is_verified, $state );
			$default = $is_verified ? 'permissions' : 'connect';
			$raw_tab = filter_input( INPUT_GET, 'tab', FILTER_UNSAFE_RAW );
			$requested = is_string( $raw_tab ) ? sanitize_key( wp_unslash( $raw_tab ) ) : '';
			if ( $is_verified && in_array( $requested, array( 'details', 'status' ), true ) ) {
				$requested = 'permissions';
			}
			if ( $is_verified && in_array( $requested, array( 'runtime_runs', 'diagnostics' ), true ) ) {
				$requested = 'advanced';
			}

			return isset( $tabs[ $requested ] ) ? $requested : $default;
		}

		/**
		 * Returns available tab labels.
		 *
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @param array<string,mixed> $state Credential state.
		 * @return array<string,string>
		 */
		private static function get_tab_labels( bool $is_verified, array $state = array() ): array {
			if ( $is_verified ) {
				$tabs = array(
					'permissions'    => __( 'Overview', 'npcink-cloud-addon' ),
					'advanced'       => __( 'Advanced and troubleshooting', 'npcink-cloud-addon' ),
				);
				if ( Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled() ) {
					$tabs = array(
						'permissions'    => $tabs['permissions'],
						'site_knowledge' => __( 'Site Knowledge', 'npcink-cloud-addon' ),
						'advanced'       => $tabs['advanced'],
					);
				}

				return $tabs;
			}

			$tabs = array(
				'connect'  => __( 'Connect', 'npcink-cloud-addon' ),
			);
			if ( self::should_show_unverified_advanced_tab( $state ) ) {
				$tabs['advanced'] = __( 'Connection management', 'npcink-cloud-addon' );
			}

			return $tabs;
		}

		/**
		 * Determines whether unverified local troubleshooting should be visible.
		 *
		 * @param array<string,mixed> $state Credential state.
		 * @return bool
		 */
		private static function should_show_unverified_advanced_tab( array $state ): bool {
			return ! empty( $state['configured'] )
				|| '' !== (string) ( $state['last_verification_error'] ?? '' )
				|| in_array( (string) ( $state['code'] ?? '' ), array( 'activation_required', 'configured_unverified', 'verification_failed' ), true );
		}

		/**
		 * Renders settings tab navigation.
		 *
		 * @param string $active_tab Active tab slug.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @param array<string,mixed> $state Credential state.
		 * @return void
		 */
		private static function render_tab_navigation( string $active_tab, bool $is_verified, array $state ): void {
			$tabs = self::get_tab_labels( $is_verified, $state );
			?>
			<nav class="npcink-ai-tabs npcink-cloud-tabs" aria-label="<?php esc_attr_e( 'Cloud addon sections', 'npcink-cloud-addon' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<?php
					$url = add_query_arg(
						array(
							'tab'  => $slug,
						),
						self::page_url()
					);
					$is_active = $active_tab === $slug;
					?>
					<a
						class="npcink-ai-tab<?php echo $is_active ? ' npcink-ai-tab-active' : ''; ?>"
						href="<?php echo esc_url( $url ); ?>"
						<?php echo $is_active ? 'aria-current="page"' : ''; ?>
					>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php
		}

		/**
		 * Renders page-local secondary navigation.
		 *
		 * @param string               $active_view Active view slug.
		 * @param array<string,string> $views View labels.
		 * @param string               $parent_tab Parent tab slug.
		 * @param string               $label Navigation label.
		 * @return void
		 */
		private static function render_secondary_tab_navigation( string $active_view, array $views, string $parent_tab, string $label ): void {
			?>
			<nav class="npcink-ai-tabs npcink-cloud-secondary-tabs" aria-label="<?php echo esc_attr( $label ); ?>">
				<?php foreach ( $views as $slug => $view_label ) : ?>
					<?php
					$url = add_query_arg(
						array(
							'tab'  => sanitize_key( $parent_tab ),
							'view' => sanitize_key( $slug ),
						),
						self::page_url()
					);
					$is_active = $active_view === $slug;
					?>
					<a
						class="npcink-ai-tab<?php echo $is_active ? ' npcink-ai-tab-active' : ''; ?>"
						href="<?php echo esc_url( $url ); ?>"
						<?php echo $is_active ? 'aria-current="page"' : ''; ?>
					>
						<?php echo esc_html( $view_label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>
			<?php
		}

		/**
		 * Builds an admin URL for a settings tab.
		 *
		 * @param string $tab Tab slug.
		 * @return string
		 */
		private static function tab_url( string $tab ): string {
			return add_query_arg(
				array(
					'tab'  => sanitize_key( $tab ),
				),
				self::page_url()
			);
		}

		/**
		 * Builds an admin URL for one tab subview.
		 *
		 * @param string $tab Tab slug.
		 * @param string $view Subview slug.
		 * @return string
		 */
		private static function tab_view_url( string $tab, string $view ): string {
			return add_query_arg(
				array(
					'tab'  => sanitize_key( $tab ),
					'view' => sanitize_key( $view ),
				),
				self::page_url()
			);
		}

		/**
		 * Returns the requested Site Knowledge subview.
		 *
		 * @return string
		 */
		private static function site_knowledge_view_from_request(): string {
			$raw = filter_input( INPUT_GET, 'view', FILTER_UNSAFE_RAW );
			$view = is_string( $raw ) ? sanitize_key( wp_unslash( $raw ) ) : '';

			return in_array( $view, array( 'overview', 'index' ), true ) ? $view : 'overview';
		}

		/**
		 * Returns the requested diagnostics subview.
		 *
		 * @return string
		 */
		private static function diagnostics_view_from_request(): string {
			$raw = filter_input( INPUT_GET, 'view', FILTER_UNSAFE_RAW );
			$view = is_string( $raw ) ? sanitize_key( wp_unslash( $raw ) ) : '';

			return in_array( $view, array( 'service', 'checks', 'connection' ), true ) ? $view : 'service';
		}

		/**
		 * Builds a Cloud Portal URL for authorizing this WordPress site.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return string
		 */
		private static function build_authorization_url( array $settings ): string {
			$base_url = Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings );
			return self::build_authorization_url_for_base_url( $base_url );
		}

		/**
		 * Builds a Cloud Portal URL for one normalized Cloud base URL.
		 *
		 * @param string $base_url Normalized Cloud base URL.
		 * @return string
		 */
		private static function build_authorization_url_for_base_url( string $base_url ): string {
			$state = self::create_authorization_state( $base_url );
			$return_url = add_query_arg(
				array(
					'action' => self::ACTION_COMPLETE_AUTH,
					'state'  => $state,
				),
				admin_url( 'admin-post.php' )
			);

			return add_query_arg(
				array(
					'connect'    => 'wordpress-addon',
					'site_url'   => home_url( '/' ),
					'site_name'  => get_bloginfo( 'name' ),
					'return_url' => rawurlencode( $return_url ),
					'state'      => $state,
				),
				untrailingslashit( $base_url ) . '/portal'
			);
		}

		/**
		 * Creates a short-lived local authorization state.
		 *
		 * @param string $base_url Cloud base URL.
		 * @return string
		 */
		private static function create_authorization_state( string $base_url ): string {
			$state = wp_generate_password( 32, false, false );
			set_transient(
				self::authorization_state_transient_name( $state ),
				array(
					'base_url' => $base_url,
					'created'  => time(),
				),
				self::AUTH_STATE_TTL_SECONDS
			);

			return $state;
		}

		/**
		 * Consumes a short-lived local authorization state.
		 *
		 * @param string $state Authorization state.
		 * @return array<string,mixed>
		 */
		private static function consume_authorization_state( string $state ): array {
			$state = trim( $state );
			if ( '' === $state ) {
				return array();
			}

			$name = self::authorization_state_transient_name( $state );
			$value = get_transient( $name );
			delete_transient( $name );

			return is_array( $value ) ? $value : array();
		}

		/**
		 * Returns the transient name for an authorization state.
		 *
		 * @param string $state Authorization state.
		 * @return string
		 */
		private static function authorization_state_transient_name( string $state ): string {
			return 'npcink_cloud_auth_' . hash( 'sha256', $state );
		}

		/**
		 * Exchanges a Cloud one-time authorization code for a customer API key.
		 *
		 * @param string $base_url Cloud base URL.
		 * @param string $code     One-time authorization code.
		 * @param string $state    Local authorization state.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function exchange_authorization_code( string $base_url, string $code, string $state ) {
			$response = Npcink_Cloud_Outbound_Policy::request_json(
				untrailingslashit( $base_url ) . '/portal/v1/addon-connections/exchange',
				array(
					'method'  => 'POST',
					'timeout' => 12,
					'headers' => array(
						'Content-Type' => 'application/json',
						'Accept'       => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'code'  => $code,
							'state' => $state,
						)
					),
				),
				Npcink_Cloud_Outbound_Policy::MAX_AUTH_RESPONSE_BYTES
			);
			if ( is_wp_error( $response ) ) {
				return new WP_Error(
					'cloud_authorization_exchange_failed',
					sprintf(
						/* translators: %s: request error message. */
						__( 'Cloud authorization exchange failed: %s', 'npcink-cloud-addon' ),
						$response->get_error_message()
					)
				);
			}

			$status = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			$data = is_array( $body ) && is_array( $body['data'] ?? null ) ? $body['data'] : array();
			$cloud_api_key = (string) ( $data['cloud_api_key'] ?? '' );
			if ( $status < 200 || $status >= 300 ) {
				return new WP_Error(
					'cloud_authorization_exchange_failed',
					self::format_authorization_exchange_error( is_array( $body ) ? $body : array() )
				);
			}
			if ( '' === $cloud_api_key ) {
				return new WP_Error(
					'cloud_authorization_exchange_failed',
					__( 'Cloud authorization exchange did not return a valid connection key.', 'npcink-cloud-addon' )
				);
			}

			$activation_state = sanitize_key( (string) ( $data['activation_state'] ?? 'active' ) );
			if ( ! in_array( $activation_state, array( 'active', 'inactive' ), true ) ) {
				return new WP_Error(
					'cloud_authorization_exchange_failed',
					__( 'Cloud authorization exchange returned an invalid activation state.', 'npcink-cloud-addon' )
				);
			}

			return array(
				'cloud_api_key' => $cloud_api_key,
				'activation_state' => $activation_state,
				'activation_required' => ! empty( $data['activation_required'] ),
				'activation_reason' => sanitize_key( (string) ( $data['activation_reason'] ?? '' ) ),
			);
		}

		/**
		 * Formats a bounded, actionable Cloud authorization exchange error.
		 *
		 * @param array<string,mixed> $body Cloud error envelope.
		 * @return string
		 */
		private static function format_authorization_exchange_error( array $body ): string {
			$error_code = preg_replace(
				'/[^a-zA-Z0-9._-]/',
				'',
				(string) ( $body['error_code'] ?? '' )
			);
			$error_code = is_string( $error_code ) ? substr( $error_code, 0, 191 ) : '';

			switch ( $error_code ) {
				case 'service.site_limit_exceeded':
					return sprintf(
						/* translators: %s: stable Cloud error code. */
						__( 'The Cloud account has reached its active-site limit. Deactivate another active site in Npcink Cloud or upgrade the account plan, then start the connection again. (%s)', 'npcink-cloud-addon' ),
						$error_code
					);
				case 'service.site_bind_limit_exceeded':
					return sprintf(
						/* translators: %s: stable Cloud error code. */
						__( 'The Cloud account has reached its connected-site limit. Remove an unused site in Npcink Cloud, then start the connection again. (%s)', 'npcink-cloud-addon' ),
						$error_code
					);
				case 'service.wordpress_addon_connection_code_invalid':
				case 'service.wordpress_addon_connection_code_expired':
				case 'service.wordpress_addon_connection_state_invalid':
				case 'service.wordpress_addon_connection_payload_invalid':
					return sprintf(
						/* translators: %s: stable Cloud error code. */
						__( 'The Cloud authorization request expired or is invalid. Start the connection again from WordPress. (%s)', 'npcink-cloud-addon' ),
						$error_code
					);
				default:
					if ( '' !== $error_code ) {
						return sprintf(
							/* translators: %s: stable Cloud error code. */
							__( 'Cloud authorization exchange failed. Start the connection again or contact support with this error code: %s', 'npcink-cloud-addon' ),
							$error_code
						);
					}
			}

			return __( 'Cloud authorization exchange did not return a valid connection key.', 'npcink-cloud-addon' );
		}

		/**
		 * Renders the default connector summary.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param array<string,mixed> $site_knowledge Site Knowledge delivery health.
		 * @return void
		 */
		private static function render_connection_summary( array $settings, array $state, array $entitlement, array $site_knowledge ): void {
			$severity = sanitize_html_class( (string) ( $state['severity'] ?? 'inactive' ) );
			$is_verified = ! empty( $state['verified'] );
			$is_configured = ! empty( $state['configured'] );
			$service_health = self::get_current_service_health( $is_verified, $entitlement, $site_knowledge );
			$service_needs_attention = $is_verified && 'warning' === (string) $service_health['severity'];
			if ( $is_verified && ! $service_needs_attention ) {
				return;
			}
			$display_base_url = $is_configured
				? (string) $settings['base_url']
				: Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings );
			$summary_label = $service_needs_attention ? (string) $service_health['label'] : (string) $state['label'];
			$summary_message = $service_needs_attention ? (string) $service_health['message'] : (string) $state['message'];
			?>
			<section class="npcink-cloud-summary">
				<div class="npcink-cloud-summary__header">
					<div>
						<p class="npcink-cloud-summary__state">
							<span class="npcink-cloud-badge npcink-cloud-badge--<?php echo esc_attr( $service_needs_attention ? 'warning' : $severity ); ?>"><?php echo esc_html( $summary_label ); ?></span>
						</p>
						<p class="npcink-cloud-summary__message"><?php echo esc_html( $summary_message ); ?></p>
					</div>
					<?php if ( $is_configured ) : ?>
						<?php self::render_connection_actions( $settings, $is_verified, $service_needs_attention ); ?>
					<?php endif; ?>
				</div>
				<?php if ( $is_configured && ! $is_verified ) : ?>
					<div class="npcink-cloud-summary__grid">
						<div class="npcink-cloud-summary__item">
							<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Cloud Base URL', 'npcink-cloud-addon' ); ?></span>
							<span class="npcink-cloud-summary__value"><?php echo esc_html( self::format_setting_value( $display_base_url, __( 'Not set', 'npcink-cloud-addon' ) ) ); ?></span>
						</div>
						<div class="npcink-cloud-summary__item">
							<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Last verified', 'npcink-cloud-addon' ); ?></span>
							<span class="npcink-cloud-summary__value"><?php echo esc_html( self::format_datetime_value( (string) $settings['verified_at'], __( 'Never', 'npcink-cloud-addon' ) ) ); ?></span>
						</div>
					</div>
				<?php endif; ?>
			</section>
			<?php
		}

		/**
		 * Returns local permission switch definitions.
		 *
		 * @return array<string,array{label:string,description:string}>
		 */
		private static function get_local_permission_definitions(): array {
			return array(
				'wordpress_ai_connector_enabled' => array(
					'label'       => __( 'WordPress AI connector', 'npcink-cloud-addon' ),
					'description' => __( 'Allow WordPress AI to use Npcink Cloud. Enabled by default after connection; turn it off in Overview when needed.', 'npcink-cloud-addon' ),
				),
				'site_knowledge_delivery_enabled' => array(
					'label'       => __( 'Enable Site Knowledge', 'npcink-cloud-addon' ),
					'description' => __( 'Keep public posts and pages updated automatically so AI can reference them.', 'npcink-cloud-addon' ),
				),
				'site_knowledge_generation_reference_enabled' => array(
					'label'       => __( 'Reference site content during generation', 'npcink-cloud-addon' ),
					'description' => __( 'Use indexed public articles as generation context.', 'npcink-cloud-addon' ),
				),
				'monitoring_enabled' => array(
					'label'       => __( 'Send anonymous diagnostics', 'npcink-cloud-addon' ),
					'description' => __( 'Send metadata-only events about feature steps, outcomes, timing, and machine-readable error codes to help diagnose failures and improve reliability. This does not send prompts, source or generated content, raw WordPress user or post IDs, email addresses, URLs, DOM data, credentials, or free-form error messages. Off by default; administrators can turn it off at any time.', 'npcink-cloud-addon' ),
				),
			);
		}

		/**
		 * Synchronizes local side effects after a permission change.
		 *
		 * @param string $permission Permission key.
		 * @return void
		 */
		private static function sync_local_permission_effects( string $permission ): void {
			if ( 'wordpress_ai_connector_enabled' === $permission ) {
				Npcink_Cloud_WordPress_AI_Connector::sync_connected_marker();
				return;
			}

			if ( 'site_knowledge_delivery_enabled' === $permission ) {
				Npcink_Cloud_Site_Knowledge_Change_Bridge::sync_schedule();
				Npcink_Cloud_Site_Knowledge_Change_Bridge::resume_pending_delivery();
				return;
			}

			if ( 'monitoring_enabled' === $permission ) {
				Npcink_Cloud_Observability_Collector::sync_schedule();
				Npcink_Cloud_Observability_Collector::project_monitoring_state(
					Npcink_Cloud_Addon_Settings::is_monitoring_enabled()
				);
			}
		}

		/**
		 * Renders top-level local permission switches.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @param bool                $service_needs_attention Whether cached service state needs attention.
		 * @return void
		 */
		private static function render_local_permissions( array $settings, bool $is_verified ): void {
			if ( ! $is_verified ) {
				return;
			}

			$feedback = self::get_local_permission_feedback();
			?>
			<section id="npcink-cloud-feature-settings" class="npcink-cloud-local-permissions" aria-labelledby="npcink-cloud-local-permissions-title">
				<div class="npcink-cloud-local-permissions__header">
					<h2 id="npcink-cloud-local-permissions-title"><?php esc_html_e( 'Features', 'npcink-cloud-addon' ); ?></h2>
				</div>
				<div class="npcink-cloud-local-permissions__list">
					<?php $definitions = self::get_local_permission_definitions(); ?>
					<?php self::render_local_permission_switch( 'site_knowledge_delivery_enabled', $definitions['site_knowledge_delivery_enabled'], ! empty( $settings['site_knowledge_delivery_enabled'] ), $feedback ); ?>
				</div>
				<details class="npcink-cloud-advanced-detail npcink-cloud-local-permissions__more"<?php echo 'monitoring_enabled' === (string) ( $feedback['permission'] ?? '' ) ? ' open' : ''; ?>>
					<summary><?php esc_html_e( 'Privacy settings', 'npcink-cloud-addon' ); ?></summary>
					<div class="npcink-cloud-advanced-detail__body">
						<?php self::render_local_permission_switch( 'monitoring_enabled', $definitions['monitoring_enabled'], ! empty( $settings['monitoring_enabled'] ), $feedback ); ?>
					</div>
				</details>
			</section>
			<?php
		}

		/**
		 * Renders one local permission switch.
		 *
		 * @param string                             $permission Permission key.
		 * @param array{label:string,description:string} $definition Permission copy.
		 * @param bool                               $enabled Whether the switch is enabled.
		 * @param array<string,string>               $feedback Latest permission feedback.
		 * @return void
		 */
		private static function render_local_permission_switch( string $permission, array $definition, bool $enabled, array $feedback = array() ): void {
			$input_id = 'npcink-cloud-local-permission-' . sanitize_html_class( str_replace( '_', '-', $permission ) );
			$is_feedback_target = $permission === (string) ( $feedback['permission'] ?? '' );
			?>
			<form id="<?php echo esc_attr( $input_id . '-form' ); ?>" class="npcink-cloud-local-permission" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" data-npcink-local-permission<?php echo $is_feedback_target ? ' data-npcink-local-permission-focus' : ''; ?>>
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( self::ACTION_UPDATE_LOCAL_PERMISSION ) ); ?>" />
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_UPDATE_LOCAL_PERMISSION ); ?>" />
				<input type="hidden" name="permission" value="<?php echo esc_attr( $permission ); ?>" />
				<input type="hidden" name="enabled" value="0" data-npcink-local-permission-value />
				<label class="npcink-cloud-local-permission__control" for="<?php echo esc_attr( $input_id ); ?>">
					<span class="npcink-ai-switch">
						<input
							type="checkbox"
							class="npcink-ai-switch__input"
							id="<?php echo esc_attr( $input_id ); ?>"
							name="enabled"
							value="1"
							<?php checked( $enabled ); ?>
						/>
						<span class="npcink-ai-switch__track" aria-hidden="true">
							<span class="npcink-ai-switch__thumb"></span>
						</span>
					</span>
					<span class="npcink-cloud-local-permission__copy">
						<span class="npcink-cloud-local-permission__title"><?php echo esc_html( $definition['label'] ); ?></span>
						<span class="npcink-cloud-local-permission__description"><?php echo esc_html( $definition['description'] ); ?></span>
					</span>
					<span class="npcink-cloud-local-permission__state"><?php echo $enabled ? esc_html__( 'enabled', 'npcink-cloud-addon' ) : esc_html__( 'disabled', 'npcink-cloud-addon' ); ?></span>
				</label>
				<span class="npcink-cloud-local-permission__progress" role="status" aria-live="polite" data-npcink-local-permission-progress hidden></span>
				<?php if ( $is_feedback_target && '' !== (string) ( $feedback['message'] ?? '' ) ) : ?>
					<p class="npcink-cloud-local-permission__feedback npcink-cloud-local-permission__feedback--<?php echo esc_attr( sanitize_key( (string) ( $feedback['type'] ?? 'error' ) ) ); ?>" role="status" tabindex="-1" data-npcink-local-permission-feedback><?php echo esc_html( (string) $feedback['message'] ); ?></p>
				<?php endif; ?>
				<noscript>
					<button type="submit" class="button button-secondary"><?php esc_html_e( 'Save', 'npcink-cloud-addon' ); ?></button>
				</noscript>
			</form>
			<?php
		}

		/**
		 * Renders connection-level actions.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @param bool                $service_needs_attention Whether cached service state needs attention.
		 * @return void
		 */
		private static function render_connection_actions( array $settings, bool $is_verified, bool $service_needs_attention = false ): void {
			$activation_required = 'inactive' === sanitize_key( (string) ( $settings['activation_state'] ?? '' ) );
			?>
			<div class="npcink-cloud-summary__actions">
				<?php if ( $activation_required ) : ?>
					<a class="button button-primary" href="<?php echo esc_url( untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Activate this site in Cloud', 'npcink-cloud-addon' ); ?></a>
					<?php self::render_reverify_form( $settings, __( 'Check activation again', 'npcink-cloud-addon' ) ); ?>
				<?php elseif ( $is_verified ) : ?>
					<?php if ( $service_needs_attention ) : ?>
						<?php self::render_reverify_form( $settings ); ?>
					<?php endif; ?>
					<a class="button button-secondary" href="<?php echo esc_url( untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud sites', 'npcink-cloud-addon' ); ?></a>
				<?php else : ?>
					<?php self::render_reverify_form( $settings ); ?>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Builds a cached, read-only service-health projection.
		 *
		 * @param bool                $is_verified Whether credentials were verified.
		 * @param array<string,mixed> $entitlement Cached entitlement summary.
		 * @param array<string,mixed> $site_knowledge Site Knowledge delivery health.
		 * @return array{severity:string,label:string,message:string}
		 */
		private static function get_current_service_health( bool $is_verified, array $entitlement, array $site_knowledge ): array {
			if ( ! $is_verified ) {
				return array( 'severity' => 'inactive', 'label' => __( 'Not checked', 'npcink-cloud-addon' ), 'message' => '' );
			}

			if ( '' !== (string) ( $site_knowledge['last_delivery_error'] ?? '' ) ) {
				return array(
					'severity' => 'warning',
					'label' => __( 'Needs attention', 'npcink-cloud-addon' ),
					'message' => __( 'A recent Site Knowledge delivery could not reach or authenticate with Cloud. Re-check the connection or open Cloud for service detail.', 'npcink-cloud-addon' ),
				);
			}

			$entitlement_state = sanitize_key( (string) ( $entitlement['state'] ?? '' ) );
			if ( ! empty( $entitlement['available'] ) ) {
				return array( 'severity' => 'ok', 'label' => __( 'Recently reachable', 'npcink-cloud-addon' ), 'message' => __( 'A cached signed Cloud read is available.', 'npcink-cloud-addon' ) );
			}
			if ( in_array( $entitlement_state, array( 'unavailable', 'refreshing' ), true ) ) {
				return array( 'severity' => 'warning', 'label' => __( 'Temporarily unavailable', 'npcink-cloud-addon' ), 'message' => __( 'The latest signed Cloud read did not complete. Re-check the connection or try again later.', 'npcink-cloud-addon' ) );
			}

			return array( 'severity' => 'pending', 'label' => __( 'Not recently checked', 'npcink-cloud-addon' ), 'message' => __( 'Run a connection check when you need current service confirmation.', 'npcink-cloud-addon' ) );
		}

		/**
		 * Renders low-frequency connection management actions.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return void
		 */
		private static function render_connection_management( array $settings ): void {
			if ( empty( $settings['base_url'] ) ) {
				return;
			}
			?>
			<h3><?php esc_html_e( 'Connection management', 'npcink-cloud-addon' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Change the connected Cloud account in Cloud.', 'npcink-cloud-addon' ); ?></p>
			<div class="npcink-cloud-summary__actions npcink-cloud-summary__actions--start">
				<?php if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) : ?>
					<?php self::render_reverify_form( $settings, __( 'Check connection', 'npcink-cloud-addon' ) ); ?>
				<?php endif; ?>
				<?php self::render_authorization_form( __( 'Change Cloud account', 'npcink-cloud-addon' ), Npcink_Cloud_Addon_Settings::is_verified() ? 'button button-primary' : 'button button-secondary' ); ?>
			</div>
			<div class="npcink-cloud-connection-danger">
				<h4><?php esc_html_e( 'Disconnect this site', 'npcink-cloud-addon' ); ?></h4>
				<p class="description"><?php esc_html_e( 'Cloud features will stop on this WordPress site and its local connection data will be cleared. The site and its data will remain in Cloud.', 'npcink-cloud-addon' ); ?></p>
				<?php self::render_disconnect_form(); ?>
			</div>
			<?php
		}

		/**
		 * Renders the folded manual recovery form.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return void
		 */
		private static function render_manual_connection_disclosure( array $settings ): void {
			?>
			<details class="npcink-cloud-advanced-detail">
				<summary><?php esc_html_e( 'Manual connection fallback', 'npcink-cloud-addon' ); ?></summary>
				<div class="npcink-cloud-advanced-detail__body">
					<p class="description"><?php esc_html_e( 'Use only for recovery or local debugging when Cloud authorization is unavailable.', 'npcink-cloud-addon' ); ?></p>
					<?php self::render_settings_form( $settings ); ?>
				</div>
			</details>
			<?php
		}

		/**
		 * Renders a compact re-verification action.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return void
		 */
		private static function render_reverify_form( array $settings, string $label = '' ): void {
			?>
			<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( self::ACTION_SAVE ) ); ?>" />
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>" />
				<input type="hidden" name="base_url" value="<?php echo esc_attr( (string) $settings['base_url'] ); ?>" />
				<input type="hidden" name="api_key" value="" />
				<input type="hidden" name="timeout" value="<?php echo esc_attr( (string) $settings['timeout'] ); ?>" />
				<input type="hidden" name="monitoring_enabled" value="<?php echo esc_attr( ! empty( $settings['monitoring_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="site_knowledge_delivery_enabled" value="<?php echo esc_attr( ! empty( $settings['site_knowledge_delivery_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="site_knowledge_generation_reference_enabled" value="<?php echo esc_attr( ! empty( $settings['site_knowledge_generation_reference_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="wordpress_ai_connector_enabled" value="<?php echo esc_attr( ! empty( $settings['wordpress_ai_connector_enabled'] ) ? '1' : '0' ); ?>" />
				<button type="submit" class="button button-secondary"><?php echo esc_html( '' !== $label ? $label : __( 'Check connection', 'npcink-cloud-addon' ) ); ?></button>
			</form>
			<?php
		}

		/**
		 * Renders a local disconnect action.
		 *
		 * @return void
		 */
		private static function render_disconnect_form(): void {
			?>
			<form class="npcink-cloud-disconnect-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( self::ACTION_DISCONNECT ) ); ?>" />
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_DISCONNECT ); ?>" />
				<button
					type="submit"
					class="button button-secondary npcink-cloud-button-danger"
					onclick="return confirm('<?php echo esc_js( __( 'Disconnect this WordPress site? Cloud features will stop here and local connection data will be cleared. The site and its data will remain in Cloud.', 'npcink-cloud-addon' ) ); ?>');"
				>
					<?php esc_html_e( 'Disconnect this site', 'npcink-cloud-addon' ); ?>
				</button>
			</form>
			<?php
		}

		/**
		 * Renders connector settings.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return void
		 */
		private static function render_settings_form( array $settings ): void {
			$base_url = Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings );
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width: 860px;">
				<?php wp_nonce_field( self::ACTION_SAVE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_SAVE ); ?>" />
				<input type="hidden" name="monitoring_enabled" value="<?php echo esc_attr( ! empty( $settings['monitoring_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="site_knowledge_delivery_enabled" value="<?php echo esc_attr( ! empty( $settings['site_knowledge_delivery_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="site_knowledge_generation_reference_enabled" value="<?php echo esc_attr( ! empty( $settings['site_knowledge_generation_reference_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="wordpress_ai_connector_enabled" value="<?php echo esc_attr( ! empty( $settings['wordpress_ai_connector_enabled'] ) ? '1' : '0' ); ?>" />
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="npcink-cloud-base-url"><?php esc_html_e( 'Cloud Base URL', 'npcink-cloud-addon' ); ?></label>
							</th>
							<td>
								<input
									type="url"
									class="regular-text code"
									id="npcink-cloud-base-url"
									name="base_url"
									value="<?php echo esc_attr( $base_url ); ?>"
									placeholder="<?php echo esc_attr( Npcink_Cloud_Addon_Settings::get_default_base_url() ); ?>"
									required
								/>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="npcink-cloud-api-key"><?php esc_html_e( 'Recovery Cloud API Key', 'npcink-cloud-addon' ); ?></label>
							</th>
							<td>
								<input
									type="password"
									class="regular-text code"
									id="npcink-cloud-api-key"
									name="api_key"
									value=""
									autocomplete="new-password"
									placeholder="<?php echo esc_attr__( 'Paste a Cloud-issued mak1_ recovery key', 'npcink-cloud-addon' ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Leave blank to keep the stored connection key. Use Change Cloud account for normal account changes.', 'npcink-cloud-addon' ); ?></p>
								<p class="description"><?php esc_html_e( 'This fallback accepts only a Cloud-issued mak1_ wrapper and never displays the stored value.', 'npcink-cloud-addon' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="npcink-cloud-timeout"><?php esc_html_e( 'Timeout', 'npcink-cloud-addon' ); ?></label>
							</th>
							<td>
								<input
									type="number"
									id="npcink-cloud-timeout"
									name="timeout"
									min="5"
									max="60"
									step="1"
									value="<?php echo esc_attr( (string) $settings['timeout'] ); ?>"
								/>
								<span><?php esc_html_e( 'seconds', 'npcink-cloud-addon' ); ?></span>
							</td>
						</tr>
					</tbody>
				</table>
				<?php submit_button( __( 'Save and Verify', 'npcink-cloud-addon' ) ); ?>
			</form>
			<?php
		}

		/**
		 * Renders the compact verified overview.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_overview_page( array $settings, array $state, array $entitlement, array $monitoring, array $site_knowledge, bool $is_verified ): void {
			if ( ! $is_verified ) {
				self::render_cloud_authorization_panel( $settings, $state );
				return;
			}
			$monitoring_needs_attention = '' !== (string) ( $monitoring['last_upload_error'] ?? '' );
			$site_knowledge_needs_attention = '' !== (string) ( $site_knowledge['last_delivery_error'] ?? '' )
				|| '' !== (string) ( $site_knowledge['last_error_code'] ?? '' );
			$entitlement_state = sanitize_key( (string) ( $entitlement['state'] ?? '' ) );
			$show_entitlement_retry = $is_verified && in_array( $entitlement_state, array( 'unavailable', 'refreshing' ), true );
			$service_health = self::get_current_service_health( $is_verified, $entitlement, $site_knowledge );
			$entitlement_metrics = self::get_overview_entitlement_metrics( $entitlement );
			$credit_metric = is_array( $entitlement_metrics['credits'] ?? null ) ? $entitlement_metrics['credits'] : array();
			$runtime_metric = is_array( $entitlement_metrics['runtime'] ?? null ) ? $entitlement_metrics['runtime'] : array();
			$site_knowledge_delivery_enabled = ! empty( $site_knowledge['delivery_enabled'] );
			$site_knowledge_usage = $site_knowledge_delivery_enabled
				? self::get_site_knowledge_usage_projection( Npcink_Cloud_Site_Knowledge_Runtime_Bridge::get_cached_status_summary() )
				: self::get_site_knowledge_usage_projection( array() );
			$show_site_knowledge_retry = in_array( (string) ( $site_knowledge_usage['state'] ?? '' ), array( 'unavailable', 'refreshing' ), true );
			$media_overview = self::get_media_index_status();
			$media_overview_total = absint( $media_overview['eligible_total'] ?? 0 );
			$media_overview_processed = absint( $media_overview['eligible_processed'] ?? 0 );
			$media_overview_percent = absint( $media_overview['display_percent'] ?? 0 );
			$media_overview_state = sanitize_key( (string) ( $media_overview['state'] ?? 'not_started' ) );
			?>
			<section class="npcink-cloud-section npcink-cloud-tab-panel">
				<h2 class="screen-reader-text"><?php esc_html_e( 'Overview', 'npcink-cloud-addon' ); ?></h2>
				<div class="npcink-cloud-section-heading">
					<h3><?php esc_html_e( 'Connection and service', 'npcink-cloud-addon' ); ?></h3>
					<a class="button button-secondary" href="<?php echo esc_url( untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud', 'npcink-cloud-addon' ); ?></a>
				</div>
				<table class="widefat striped npcink-cloud-overview-status">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Connection', 'npcink-cloud-addon' ); ?></th>
						<td>
							<span class="npcink-cloud-badge npcink-cloud-badge--ok"><?php esc_html_e( 'Connected', 'npcink-cloud-addon' ); ?></span>
							<span class="npcink-cloud-overview-service npcink-cloud-overview-service--<?php echo esc_attr( (string) $service_health['severity'] ); ?>"><?php echo esc_html( (string) $service_health['label'] ); ?></span>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Plan and entitlement', 'npcink-cloud-addon' ); ?></th>
						<td class="npcink-cloud-entitlement" data-npcink-entitlement-state="<?php echo esc_attr( $entitlement_state ); ?>">
							<span data-npcink-entitlement-summary aria-live="polite"><?php echo esc_html( self::format_overview_entitlement( $entitlement, $is_verified ) ); ?></span>
							<span class="spinner npcink-cloud-entitlement__spinner" aria-hidden="true"></span>
							<button type="button" class="button-link npcink-cloud-entitlement__retry" data-npcink-entitlement-retry<?php echo $show_entitlement_retry ? '' : ' hidden'; ?>><?php esc_html_e( 'Retry', 'npcink-cloud-addon' ); ?></button>
						</td>
					</tr>
					<tr data-npcink-entitlement-metric="credits"<?php echo empty( $credit_metric['available'] ) ? ' hidden' : ''; ?>>
						<th scope="row"><?php esc_html_e( 'Available AI credits', 'npcink-cloud-addon' ); ?></th>
						<td>
							<div class="npcink-cloud-entitlement-metric"<?php echo ! empty( $credit_metric['tooltip'] ) ? ' title="' . esc_attr( (string) $credit_metric['tooltip'] ) . '"' : ''; ?>>
								<span class="npcink-cloud-metric-value" data-npcink-entitlement-metric-value><?php echo esc_html( (string) ( $credit_metric['value_label'] ?? $credit_metric['label'] ?? '' ) ); ?></span>
								<span class="npcink-cloud-metric-status" data-npcink-entitlement-metric-status<?php echo empty( $credit_metric['status_label'] ) ? ' hidden' : ''; ?>><?php echo esc_html( (string) ( $credit_metric['status_label'] ?? '' ) ); ?></span>
								<span
									class="npcink-cloud-segmented-progress npcink-cloud-entitlement-progress"
									data-npcink-entitlement-progress
									role="progressbar"
									aria-label="<?php esc_attr_e( 'Remaining AI credits percentage', 'npcink-cloud-addon' ); ?>"
									aria-valuemin="0"
									aria-valuemax="100"
									aria-valuenow="<?php echo esc_attr( (string) ( $credit_metric['percent'] ?? 0 ) ); ?>"
									style="--npcink-cloud-progress: <?php echo esc_attr( (string) max( 0, min( 100, (float) ( $credit_metric['percent'] ?? 0 ) ) ) ); ?>%;"
									<?php echo null === ( $credit_metric['percent'] ?? null ) ? ' hidden' : ''; ?>
								></span>
								<span class="npcink-cloud-metric-actions npcink-cloud-metric-actions--empty" aria-hidden="true"></span>
							</div>
						</td>
					</tr>
					<?php if ( $site_knowledge_delivery_enabled ) : ?>
					<tr data-npcink-site-knowledge-usage-row>
						<th scope="row"><?php esc_html_e( 'Available knowledge documents', 'npcink-cloud-addon' ); ?></th>
						<td
							class="npcink-cloud-site-knowledge-usage"
							data-npcink-site-knowledge-usage
							data-npcink-site-knowledge-state="<?php echo esc_attr( (string) ( $site_knowledge_usage['state'] ?? 'not_refreshed' ) ); ?>"
							<?php echo ! empty( $site_knowledge_usage['tooltip'] ) ? ' title="' . esc_attr( (string) $site_knowledge_usage['tooltip'] ) . '"' : ''; ?>
						>
							<div class="npcink-cloud-site-knowledge-usage__main">
								<span class="npcink-cloud-metric-value" data-npcink-site-knowledge-usage-value aria-live="polite"><?php echo esc_html( (string) ( $site_knowledge_usage['value_label'] ?? $site_knowledge_usage['label'] ?? __( 'Loading Site Knowledge usage…', 'npcink-cloud-addon' ) ) ); ?></span>
								<span class="npcink-cloud-metric-status" data-npcink-site-knowledge-usage-status<?php echo empty( $site_knowledge_usage['status_label'] ) ? ' hidden' : ''; ?>><?php echo esc_html( (string) ( $site_knowledge_usage['status_label'] ?? '' ) ); ?></span>
								<span
									class="npcink-cloud-segmented-progress npcink-cloud-site-knowledge-progress npcink-cloud-site-knowledge-progress--<?php echo esc_attr( (string) ( $site_knowledge_usage['severity'] ?? 'ok' ) ); ?>"
									data-npcink-site-knowledge-progress
									role="progressbar"
									aria-label="<?php esc_attr_e( 'Remaining knowledge document percentage', 'npcink-cloud-addon' ); ?>"
									aria-valuemin="0"
									aria-valuemax="100"
									aria-valuenow="<?php echo esc_attr( (string) ( $site_knowledge_usage['percent'] ?? 0 ) ); ?>"
									style="--npcink-cloud-progress: <?php echo esc_attr( (string) max( 0, min( 100, (float) ( $site_knowledge_usage['percent'] ?? 0 ) ) ) ); ?>%;"
									<?php echo empty( $site_knowledge_usage['available'] ) ? ' hidden' : ''; ?>
								></span>
								<span
									class="npcink-cloud-metric-actions"
									data-npcink-site-knowledge-actions
									<?php echo $show_site_knowledge_retry || in_array( (string) ( $site_knowledge_usage['state'] ?? '' ), array( 'not_refreshed', 'stale' ), true ) ? '' : ' hidden'; ?>
								>
									<span class="spinner npcink-cloud-site-knowledge-usage__spinner" aria-hidden="true"></span>
									<button type="button" class="button-link npcink-cloud-site-knowledge-usage__retry" data-npcink-site-knowledge-retry<?php echo $show_site_knowledge_retry ? '' : ' hidden'; ?>><?php esc_html_e( 'Retry', 'npcink-cloud-addon' ); ?></button>
								</span>
							</div>
						</td>
					</tr>
					<?php endif; ?>
					<tr data-npcink-site-media-overview>
						<th scope="row"><?php esc_html_e( 'Site media recognition', 'npcink-cloud-addon' ); ?></th>
						<td>
							<div class="npcink-cloud-entitlement-metric">
								<span class="npcink-cloud-metric-value" data-npcink-site-media-overview-value><?php echo $media_overview_total > 0 ? esc_html( sprintf(
									/* translators: 1: processed image count, 2: total image count. */
									__( '%1$d / %2$d images', 'npcink-cloud-addon' ),
									$media_overview_processed,
									$media_overview_total
								) ) : esc_html__( 'Not started', 'npcink-cloud-addon' ); ?></span>
								<span class="npcink-cloud-metric-status" data-npcink-site-media-overview-status><?php echo $media_overview_total > 0 ? esc_html( $media_overview_percent . '%' ) : ''; ?></span>
								<span class="npcink-cloud-segmented-progress npcink-cloud-site-media-overview-progress npcink-cloud-site-media-overview-progress--<?php echo esc_attr( $media_overview_state ); ?>" role="progressbar" aria-label="<?php esc_attr_e( 'Site media recognition completion', 'npcink-cloud-addon' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $media_overview_percent ); ?>" style="--npcink-cloud-progress: <?php echo esc_attr( (string) $media_overview_percent ); ?>%;"></span>
								<span class="npcink-cloud-metric-actions npcink-cloud-metric-actions--empty" aria-hidden="true"></span>
							</div>
						</td>
					</tr>
					<tr data-npcink-entitlement-metric="runtime"<?php echo empty( $runtime_metric['available'] ) ? ' hidden' : ''; ?>>
						<th scope="row"><?php esc_html_e( 'Runtime allowance', 'npcink-cloud-addon' ); ?></th>
						<td data-npcink-entitlement-metric-label><?php echo esc_html( (string) ( $runtime_metric['label'] ?? '' ) ); ?></td>
					</tr>
					<?php if ( $monitoring_needs_attention ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Monitoring needs attention', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_monitoring_overview( $monitoring ) ); ?></td>
						</tr>
					<?php endif; ?>
					<?php if ( $site_knowledge_needs_attention ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Site Knowledge needs attention', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_site_knowledge_overview( $site_knowledge ) ); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
				</table>
				<p class="description"><?php esc_html_e( 'AI credits shown here belong to the connected Cloud account. Disconnecting, removing, or changing this WordPress site does not transfer those AI credits.', 'npcink-cloud-addon' ); ?></p>
			</section>
			<?php self::render_monitoring_consent_prompt( $settings, $is_verified ); ?>
			<?php self::render_local_permissions( $settings, $is_verified ); ?>
			<?php
		}

		/**
		 * Renders the one-time monitoring consent prompt after first verification.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_monitoring_consent_prompt( array $settings, bool $is_verified ): void {
			if ( ! $is_verified || ! empty( $settings['monitoring_enabled'] ) || ! self::has_monitoring_consent_prompt() ) {
				return;
			}
			?>
			<dialog class="npcink-cloud-monitoring-consent" open role="dialog" aria-modal="true" aria-labelledby="npcink-cloud-monitoring-consent-title">
				<div class="npcink-cloud-monitoring-consent__body">
					<h2 id="npcink-cloud-monitoring-consent-title"><?php esc_html_e( 'Cloud connection verified', 'npcink-cloud-addon' ); ?></h2>
					<p><?php esc_html_e( 'Would you like to help improve reliability by sharing anonymous usage and error diagnostics from this WordPress site?', 'npcink-cloud-addon' ); ?></p>
					<p class="description"><?php esc_html_e( 'Only feature steps, outcomes, timing, versions, and machine-readable error codes are sent. Prompts, source or generated content, user or post identifiers, URLs, credentials, and request headers are never sent.', 'npcink-cloud-addon' ); ?></p>
					<div class="npcink-cloud-monitoring-consent__actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_UPDATE_LOCAL_PERMISSION ); ?>" />
							<input type="hidden" name="permission" value="monitoring_enabled" />
							<input type="hidden" name="enabled" value="1" />
							<?php wp_nonce_field( self::ACTION_UPDATE_LOCAL_PERMISSION ); ?>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Allow anonymous diagnostics', 'npcink-cloud-addon' ); ?></button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_DISMISS_MONITORING_PROMPT ); ?>" />
							<?php wp_nonce_field( self::ACTION_DISMISS_MONITORING_PROMPT ); ?>
							<button type="submit" class="button button-secondary"><?php esc_html_e( 'Not now', 'npcink-cloud-addon' ); ?></button>
						</form>
					</div>
				</div>
			</dialog>
			<?php
		}

		/**
		 * Renders bounded Cloud service detail and troubleshooting.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_advanced_page( array $settings, array $state, array $entitlement, array $monitoring, bool $is_verified ): void {
			if ( ! $is_verified ) {
				self::render_advanced_information( $state );
				self::render_connection_management( $settings );
				self::render_manual_connection_disclosure( $settings );
				return;
			}

			$active_view = self::diagnostics_view_from_request();
			self::render_secondary_tab_navigation(
				$active_view,
				array(
					'service'    => __( 'Service details', 'npcink-cloud-addon' ),
					'checks'     => __( 'Checks', 'npcink-cloud-addon' ),
					'connection' => __( 'Connection management', 'npcink-cloud-addon' ),
				),
				'advanced',
				__( 'Advanced and troubleshooting sections', 'npcink-cloud-addon' )
			);

			if ( 'checks' === $active_view ) {
				self::render_diagnostic_checks( $settings, $state, $entitlement, $is_verified );
				return;
			}

			if ( 'connection' === $active_view ) {
				self::render_advanced_information( $state );
				self::render_connection_management( $settings );
				self::render_manual_connection_disclosure( $settings );
				return;
			}

			self::render_status_account_usage( $entitlement );
			self::render_status_monitoring_quality( $monitoring );
		}

		/**
		 * Renders bounded Cloud service checks.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_diagnostic_checks( array $settings, array $state, array $entitlement, bool $is_verified ): void {
			$runtime = ! empty( $entitlement['available'] ) && is_array( $entitlement['pro_cloud_runtime'] ?? null ) ? $entitlement['pro_cloud_runtime'] : array();
			$readiness = self::get_manual_readiness_result();
			$site_knowledge = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
			$site_knowledge_needs_attention = ! empty( $site_knowledge['wp_cron_disabled'] )
				|| '' !== (string) ( $site_knowledge['last_delivery_error'] ?? '' )
				|| '' !== (string) ( $site_knowledge['last_error_code'] ?? '' );
			$connection_detail = sprintf(
				/* translators: 1: last verification time, 2: signed read status. */
				__( 'Last checked: %1$s · Signed read: %2$s', 'npcink-cloud-addon' ),
				self::format_datetime_value( (string) ( $settings['verified_at'] ?? '' ), __( 'Never', 'npcink-cloud-addon' ) ),
				self::format_entitlement_availability( $entitlement, $is_verified )
			);
			?>
			<div class="npcink-cloud-section-heading">
				<h3><?php esc_html_e( 'Checks', 'npcink-cloud-addon' ); ?></h3>
				<div class="npcink-cloud-summary__actions">
					<?php self::render_manual_readiness_test_form(); ?>
					<a class="button button-secondary" href="<?php echo esc_url( untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud status detail', 'npcink-cloud-addon' ); ?></a>
				</div>
			</div>
			<p class="description"><?php esc_html_e( 'Run the bounded connection checks or open Cloud for service detail.', 'npcink-cloud-addon' ); ?></p>
			<table class="widefat striped" style="max-width: 980px;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Check', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Detail', 'npcink-cloud-addon' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php self::render_diagnostic_row( __( 'Credentials', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $state['configured'] ), __( 'saved', 'npcink-cloud-addon' ), __( 'missing', 'npcink-cloud-addon' ) ), self::format_setting_value( (string) ( $settings['base_url'] ?? '' ), __( 'Not set', 'npcink-cloud-addon' ) ) ); ?>
					<?php self::render_diagnostic_row( __( 'Cloud connection', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $state['verified'] ), __( 'verified', 'npcink-cloud-addon' ), __( 'not verified', 'npcink-cloud-addon' ) ), $connection_detail ); ?>
					<?php self::render_diagnostic_row( __( 'Hosted Runtime', 'npcink-cloud-addon' ), self::diagnostic_status( ! empty( $runtime['reported'] ), __( 'reported', 'npcink-cloud-addon' ), __( 'not returned', 'npcink-cloud-addon' ) ), self::format_hosted_runtime_diagnostic_detail( $runtime ) ); ?>
					<?php if ( ! empty( $readiness ) ) : ?>
						<?php self::render_diagnostic_row( __( 'Readiness result', 'npcink-cloud-addon' ), self::format_readiness_status( $readiness ), self::format_readiness_detail( $readiness ) ); ?>
					<?php endif; ?>
					</tbody>
				</table>
				<?php if ( $site_knowledge_needs_attention ) : ?>
					<?php self::render_site_knowledge_bridge_health_detail( $site_knowledge ); ?>
				<?php endif; ?>
				<?php
			}

		/**
		 * Renders one diagnostics row.
		 *
		 * @param string $label Row label.
		 * @param string $status Row status.
		 * @param string $detail Row detail.
		 * @return void
		 */
		private static function render_diagnostic_row( string $label, string $status, string $detail ): void {
			?>
			<tr>
				<th scope="row"><?php echo esc_html( $label ); ?></th>
				<td><?php echo esc_html( self::format_empty( $status ) ); ?></td>
				<td><?php echo esc_html( self::format_empty( $detail ) ); ?></td>
			</tr>
			<?php
		}

		/**
		 * Renders the explicit manual readiness test action.
		 *
		 * @return void
		 */
		private static function render_manual_readiness_test_form(): void {
			?>
			<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::ACTION_RUN_MANUAL_READINESS_TEST ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_RUN_MANUAL_READINESS_TEST ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Run readiness test', 'npcink-cloud-addon' ); ?></button>
			</form>
			<?php
		}

		/**
		 * Formats a boolean diagnostics status.
		 *
		 * @param bool   $ok Positive status.
		 * @param string $ok_label Positive label.
		 * @param string $fail_label Negative label.
		 * @return string
		 */
		private static function diagnostic_status( bool $ok, string $ok_label, string $fail_label ): string {
			return $ok ? $ok_label : $fail_label;
		}

		/**
		 * Formats the bounded readiness status.
		 *
		 * @param array<string,mixed> $readiness Readiness result.
		 * @return string
		 */
		private static function format_readiness_status( array $readiness ): string {
			if ( empty( $readiness ) ) {
				return __( 'not run', 'npcink-cloud-addon' );
			}

			return self::format_readiness_token( (string) ( $readiness['bounded_status'] ?? $readiness['status'] ?? 'unavailable' ) );
		}

		/**
		 * Formats a bounded readiness token without exposing arbitrary upstream text.
		 *
		 * @param string $token Bounded status, owner, or next-action token.
		 * @return string
		 */
		private static function format_readiness_token( string $token ): string {
			$token = sanitize_key( $token );
			switch ( $token ) {
			case 'ready':
				return __( 'Ready', 'npcink-cloud-addon' );
			case 'failed':
				return __( 'Failed', 'npcink-cloud-addon' );
			case 'not_configured':
				return __( 'Not configured', 'npcink-cloud-addon' );
			case 'partial':
				return __( 'Partial', 'npcink-cloud-addon' );
			case 'not_run':
				return __( 'not run', 'npcink-cloud-addon' );
			case 'cloud_addon':
				return __( 'Cloud Addon', 'npcink-cloud-addon' );
			case 'cloud':
				return __( 'Cloud', 'npcink-cloud-addon' );
			case 'operator':
				return __( 'Operator', 'npcink-cloud-addon' );
			case 'continue':
				return __( 'continue', 'npcink-cloud-addon' );
			case 'retry_test':
				return __( 'Retry', 'npcink-cloud-addon' );
			case 'check_cloud_status':
				return __( 'Check Cloud status', 'npcink-cloud-addon' );
			case 'open_settings':
				return __( 'Open settings', 'npcink-cloud-addon' );
			case 'unavailable':
			default:
				return __( 'unavailable', 'npcink-cloud-addon' );
		}
		}

		/**
		 * Formats the bounded readiness owner and next action.
		 *
		 * @param array<string,mixed> $readiness Readiness result.
		 * @return string
		 */
		private static function format_readiness_detail( array $readiness ): string {
			if ( empty( $readiness ) ) {
				return __( 'Use Run readiness test to execute the liveness and signed-read checks.', 'npcink-cloud-addon' );
			}

			$owner = self::format_readiness_token( (string) ( $readiness['owner_label'] ?? 'cloud_addon' ) );
			$next_action = self::format_readiness_token( (string) ( $readiness['next_safe_action'] ?? $readiness['next_action'] ?? 'retry_test' ) );
			$blocked = sanitize_text_field( (string) ( $readiness['blocked_reason'] ?? '' ) );

			if ( '' !== $blocked ) {
				return sprintf(
					/* translators: 1: owner label, 2: next action, 3: blocked reason. */
					__( 'Owner: %1$s. Next safe action: %2$s. Blocked reason: %3$s', 'npcink-cloud-addon' ),
					$owner,
					$next_action,
					$blocked
				);
			}

			return sprintf(
				/* translators: 1: owner label, 2: next action. */
				__( 'Owner: %1$s. Next safe action: %2$s.', 'npcink-cloud-addon' ),
				$owner,
				$next_action
			);
		}

		/**
		 * Formats hosted runtime diagnostic detail.
		 *
		 * @param array<string,mixed> $runtime Pro Cloud Runtime summary.
		 * @return string
		 */
		private static function format_hosted_runtime_diagnostic_detail( array $runtime ): string {
			if ( empty( $runtime['reported'] ) ) {
				return __( 'Cloud entitlement did not return hosted runtime detail. Re-verify or open Cloud status detail.', 'npcink-cloud-addon' );
			}

			return __( 'Cloud reported the hosted runtime entitlement.', 'npcink-cloud-addon' );
		}

		/**
		 * Renders the default Cloud authorization entry.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @return void
		 */
		private static function render_cloud_authorization_panel( array $settings, array $state ): void {
			$base_url = Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings );
			?>
			<div class="npcink-cloud-connect-context" aria-label="<?php esc_attr_e( 'Connection context', 'npcink-cloud-addon' ); ?>">
				<div class="npcink-cloud-connect-context__item">
					<span class="npcink-cloud-connect-context__label"><?php esc_html_e( 'Connection', 'npcink-cloud-addon' ); ?></span>
					<strong class="npcink-cloud-connect-context__value"><?php echo esc_html( (string) ( $state['label'] ?? '' ) ); ?></strong>
				</div>
				<div class="npcink-cloud-connect-context__item">
					<span class="npcink-cloud-connect-context__label"><?php esc_html_e( 'Cloud', 'npcink-cloud-addon' ); ?></span>
					<code class="npcink-cloud-connect-context__value"><?php echo esc_html( $base_url ); ?></code>
				</div>
				<div class="npcink-cloud-connect-context__item">
					<span class="npcink-cloud-connect-context__label"><?php esc_html_e( 'Current site', 'npcink-cloud-addon' ); ?></span>
					<span class="npcink-cloud-connect-context__value"><?php echo esc_html( home_url( '/' ) ); ?></span>
				</div>
				</div>
				<div class="npcink-cloud-connect-actions">
					<?php self::render_authorization_form( __( 'Add this site in Npcink Cloud', 'npcink-cloud-addon' ), 'button button-primary button-hero' ); ?>
				<p class="description"><?php esc_html_e( 'Cloud will create or activate this site connection and return here with a one-time authorization code.', 'npcink-cloud-addon' ); ?></p>
				<p class="description"><?php esc_html_e( 'After connection, the WordPress AI connector is enabled by default. You can turn it off later in Overview under Local permissions.', 'npcink-cloud-addon' ); ?></p>
				<p class="description"><a href="https://cloud.npc.ink/terms/en/privacy.html" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Review Cloud privacy and data retention information', 'npcink-cloud-addon' ); ?></a></p>
				<p class="description"><?php esc_html_e( 'Free service and AI credits belong to the Cloud account selected during authorization, not this site. The same account may reconnect at any time; changing to another account is subject to the removal and cooldown requirements shown by Cloud.', 'npcink-cloud-addon' ); ?></p>
			</div>
			<details class="npcink-cloud-endpoint-advanced">
				<summary>
					<span><?php esc_html_e( 'Advanced connection', 'npcink-cloud-addon' ); ?></span>
					<small><?php esc_html_e( 'Self-hosted Cloud endpoint', 'npcink-cloud-addon' ); ?></small>
				</summary>
				<div class="npcink-cloud-endpoint-advanced__body">
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::ACTION_START_CUSTOM_AUTH ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_START_CUSTOM_AUTH ); ?>" />
						<label for="npcink-cloud-self-hosted-base-url"><?php esc_html_e( 'Cloud Base URL', 'npcink-cloud-addon' ); ?></label>
						<div class="npcink-cloud-endpoint-advanced__controls">
							<input
								type="url"
								class="regular-text code"
								id="npcink-cloud-self-hosted-base-url"
								name="self_hosted_base_url"
								value="<?php echo esc_attr( $base_url ); ?>"
								placeholder="<?php echo esc_attr( Npcink_Cloud_Addon_Settings::get_default_base_url() ); ?>"
								required
							/>
							<button type="submit" class="button button-secondary" formtarget="_blank"><?php esc_html_e( 'Authorize with this endpoint', 'npcink-cloud-addon' ); ?></button>
						</div>
					</form>
					<p class="description"><?php esc_html_e( 'For compatible Npcink Cloud deployments only. Cloud still owns site activation and key issuance.', 'npcink-cloud-addon' ); ?></p>
					<p class="description"><?php esc_html_e( 'This does not manage Cloud sites, keys, billing, models, router, workflows, or runtime policy.', 'npcink-cloud-addon' ); ?></p>
				</div>
			</details>
			<?php
		}

		/**
		 * Renders a click-time authorization form so the short-lived state is fresh.
		 *
		 * @param string $label Button label.
		 * @param string $class_name Button classes.
		 * @return void
		 */
		private static function render_authorization_form( string $label, string $class_name ): void {
			?>
			<form class="npcink-cloud-authorization-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" target="_blank">
				<?php wp_nonce_field( self::ACTION_START_AUTH ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_START_AUTH ); ?>" />
				<button type="submit" class="<?php echo esc_attr( $class_name ); ?>"><?php echo esc_html( $label ); ?></button>
			</form>
			<?php
		}

		/**
		 * Renders Site Knowledge connector status and manual refresh transport.
		 *
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @param array<string,mixed> $settings Stored settings.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_site_knowledge_summary( array $site_knowledge, array $settings, bool $is_verified ): void {
			if ( ! $is_verified ) {
				?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'Site Knowledge delivery starts after the connector verifies successfully.', 'npcink-cloud-addon' ); ?></p>
				<?php
				return;
			}

			$base_url = untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) );
			$site_id = trim( (string) ( $settings['site_id'] ?? '' ) );
			$cloud_site_knowledge_url = $base_url . '/portal';
			if ( '' !== $site_id ) {
				$cloud_site_knowledge_url .= '/sites/' . rawurlencode( $site_id ) . '#site-knowledge';
			}
			$delivery_enabled = ! empty( $site_knowledge['delivery_enabled'] );
			$active_view = self::site_knowledge_view_from_request();
			$last_delivery_error = (string) ( $site_knowledge['last_delivery_error'] ?? '' );
			$status_summary = $delivery_enabled ? Npcink_Cloud_Site_Knowledge_Runtime_Bridge::get_cached_status_summary() : array();
			$cloud_usage = $delivery_enabled
				? self::get_site_knowledge_usage_projection( $status_summary )
				: self::get_site_knowledge_usage_projection( array() );
			$coverage = is_array( $status_summary['article_coverage'] ?? null ) ? $status_summary['article_coverage'] : array();
			$quota_skipped_count = absint( $status_summary['skipped_due_to_quota'] ?? 0 );
			$buffer_count = absint( $site_knowledge['buffer_count'] ?? 0 );
			$waiting_count = max( $buffer_count, absint( $coverage['not_indexed_count'] ?? 0 ) );
			$maintenance_active = 'idle' !== (string) ( $site_knowledge['maintenance_status'] ?? 'idle' );
			$local_delivery_needs_attention = ! empty( $site_knowledge['wp_cron_disabled'] )
				|| ! empty( $site_knowledge['reconcile_overdue'] )
				|| '' !== $last_delivery_error
				|| '' !== (string) ( $site_knowledge['last_error_code'] ?? '' );
			$capacity_needs_attention = $quota_skipped_count > 0;
			$update_in_progress = $maintenance_active || $waiting_count > 0;
			$media_status = self::get_media_index_status();
			$media_plan = self::get_media_recognition_plan();
			$media_state_labels = array(
				'not_started' => __( 'Not started', 'npcink-cloud-addon' ),
				'processing' => __( 'Recognizing images', 'npcink-cloud-addon' ),
				'complete' => __( 'Completed', 'npcink-cloud-addon' ),
				'partial' => __( 'Partially completed', 'npcink-cloud-addon' ),
				'error' => __( 'Recognition incomplete', 'npcink-cloud-addon' ),
				'waiting_next_day' => __( 'Waiting for background processing', 'npcink-cloud-addon' ),
			);
			$media_state = sanitize_key( (string) ( $media_status['state'] ?? 'not_started' ) );
			if ( 'waiting_next_day' === (string) ( $media_plan['state'] ?? '' ) ) {
				$media_state = 'waiting_next_day';
			}
			$media_state_label = $media_state_labels[ $media_state ] ?? $media_state_labels['not_started'];
			$media_inventory_total = absint( $media_status['inventory_total'] ?? $media_status['total'] ?? 0 );
			$media_total = absint( $media_status['eligible_total'] ?? 0 );
			$media_processed = absint( $media_status['eligible_processed'] ?? 0 );
			$media_excluded = absint( $media_status['excluded_count'] ?? 0 );
			$media_without_evidence = absint( $media_status['without_evidence_count'] ?? 0 );
			$media_breakdown_available = ! empty( $media_status['count_breakdown_available'] );
			$media_recognized = absint( $media_status['recognized_count'] ?? 0 );
			$media_reused = absint( $media_status['reused_count'] ?? 0 );
			$media_evidence = absint( $media_status['evidence'] ?? 0 );
			$media_percent = absint( $media_status['display_percent'] ?? 0 );
			$media_rate = max( 0, (float) ( $media_status['items_per_minute'] ?? 0 ) );
			$media_eta = sanitize_text_field( (string) ( $media_status['eta_at'] ?? '' ) );
			$media_error = sanitize_text_field( (string) ( $media_status['error'] ?? '' ) );
			$media_entitlement = Npcink_Cloud_Entitlement_Summary::get_cached_summary();
			$media_runtime_quota = is_array( $media_entitlement['hosted_runtime_quota'] ?? null ) ? $media_entitlement['hosted_runtime_quota'] : array();
			$media_credit_detail = is_array( $media_entitlement['ai_credit_usage_detail'] ?? null ) ? $media_entitlement['ai_credit_usage_detail'] : array();
			$media_credit_summary = is_array( $media_credit_detail['summary'] ?? null ) ? $media_credit_detail['summary'] : array();
			$media_capacity = is_array( $media_entitlement['media_image_capacity'] ?? null ) ? $media_entitlement['media_image_capacity'] : array();
			$media_active_limit = absint( $media_runtime_quota['max_active_runs'] ?? 0 );
			$media_batch_limit = absint( $media_runtime_quota['max_batch_items'] ?? 0 );
			?>
					<div class="npcink-cloud-site-knowledge-consent npcink-cloud-site-knowledge-consent--readonly">
						<div class="npcink-cloud-site-knowledge-consent__control" aria-describedby="npcink-cloud-site-knowledge-delivery-summary npcink-cloud-site-knowledge-delivery-status">
							<span class="npcink-cloud-site-knowledge-consent__copy">
							<span class="npcink-cloud-site-knowledge-consent__title"><?php esc_html_e( 'Site Knowledge', 'npcink-cloud-addon' ); ?></span>
							<span id="npcink-cloud-site-knowledge-delivery-summary" class="npcink-cloud-site-knowledge-consent__description"><?php esc_html_e( 'AI can reference your public posts and pages. WordPress content and search engine settings are not changed.', 'npcink-cloud-addon' ); ?></span>
							</span>
						</div>
					</div>
					<?php
			if ( 'index' === $active_view ) {
						?>
						<p><a href="<?php echo esc_url( self::tab_url( 'site_knowledge' ) ); ?>">&larr; <?php esc_html_e( 'Back to Site Knowledge', 'npcink-cloud-addon' ); ?></a></p>
						<?php
						self::render_site_knowledge_index_operations( $delivery_enabled );
						return;
					}
					?>
					<section class="npcink-cloud-site-knowledge-summary" aria-labelledby="npcink-cloud-site-knowledge-status-title">
						<div class="npcink-cloud-site-knowledge-summary__heading">
			<h3 id="npcink-cloud-site-knowledge-status-title"><?php esc_html_e( 'Knowledge base status', 'npcink-cloud-addon' ); ?></h3>
							<?php if ( $local_delivery_needs_attention ) : ?>
								<p class="npcink-cloud-site-knowledge-summary__result npcink-cloud-site-knowledge-summary__result--warning"><?php esc_html_e( 'Knowledge base update needs attention', 'npcink-cloud-addon' ); ?></p>
									<p class="description"><?php echo ! empty( $site_knowledge['reconcile_overdue'] ) ? esc_html__( 'Automatic updates are delayed. Check the site scheduler in advanced troubleshooting.', 'npcink-cloud-addon' ) : esc_html__( 'The system will keep trying automatically.', 'npcink-cloud-addon' ); ?></p>
									<p class="npcink-cloud-site-knowledge-summary__support"><a href="<?php echo esc_url( self::tab_view_url( 'advanced', 'checks' ) ); ?>"><?php esc_html_e( 'View advanced troubleshooting', 'npcink-cloud-addon' ); ?></a></p>
									<?php self::render_site_knowledge_refresh_form( __( 'Update again', 'npcink-cloud-addon' ) ); ?>
							<?php elseif ( $capacity_needs_attention ) : ?>
								<p class="npcink-cloud-site-knowledge-summary__result npcink-cloud-site-knowledge-summary__result--warning"><?php esc_html_e( 'Some content is outside the knowledge base limit', 'npcink-cloud-addon' ); ?></p>
								<p class="description"><?php echo esc_html( sprintf( /* translators: %d: content count skipped by Cloud quota. */ __( '%d public items were not included. Review the plan details in Cloud.', 'npcink-cloud-addon' ), $quota_skipped_count ) ); ?></p>
							<?php elseif ( $update_in_progress ) : ?>
							<p class="npcink-cloud-site-knowledge-summary__result"><?php esc_html_e( 'Updating the knowledge base', 'npcink-cloud-addon' ); ?></p>
							<p class="description"><?php echo esc_html( sprintf(
								/* translators: %d: number of content updates waiting for automatic processing. */
								__( 'Updates waiting: %d', 'npcink-cloud-addon' ),
								$waiting_count
							) ); ?></p>
						<?php elseif ( ! empty( $status_summary['available'] ) ) : ?>
							<p class="npcink-cloud-site-knowledge-summary__result npcink-cloud-site-knowledge-summary__result--success"><?php esc_html_e( 'All public content is up to date', 'npcink-cloud-addon' ); ?></p>
						<?php else : ?>
							<p class="npcink-cloud-site-knowledge-summary__result"><?php esc_html_e( 'Automatic updates are ready', 'npcink-cloud-addon' ); ?></p>
							<p class="description"><?php esc_html_e( 'Updates will appear here after public content changes.', 'npcink-cloud-addon' ); ?></p>
						<?php endif; ?>
						<?php if ( '' !== (string) ( $site_knowledge['last_delivery_at'] ?? '' ) ) : ?>
							<p class="npcink-cloud-site-knowledge-summary__time"><?php echo esc_html( sprintf(
								/* translators: %s: date and time of the most recent knowledge base update. */
								__( 'Last updated: %s', 'npcink-cloud-addon' ),
									self::format_compact_datetime_value( (string) $site_knowledge['last_delivery_at'] )
							) ); ?></p>
						<?php endif; ?>
						</div>
						</section>
						<section class="npcink-cloud-site-knowledge-summary" aria-labelledby="npcink-cloud-site-media-index-title">
							<div class="npcink-cloud-site-knowledge-summary__heading">
								<h3 id="npcink-cloud-site-media-index-title"><?php esc_html_e( 'Site media recognition', 'npcink-cloud-addon' ); ?></h3>
								<p class="npcink-cloud-site-knowledge-summary__result" data-npcink-site-media-state-label><?php echo esc_html( $media_state_label ); ?></p>
								<table class="widefat striped npcink-cloud-site-media-status" aria-label="<?php esc_attr_e( 'Site media recognition status', 'npcink-cloud-addon' ); ?>" data-npcink-site-media-status data-state="<?php echo esc_attr( $media_state ); ?>">
									<tbody>
											<tr><th scope="row"><?php esc_html_e( 'Images', 'npcink-cloud-addon' ); ?></th><td data-npcink-site-media-images><?php echo $media_total > 0 ? esc_html( sprintf(
												/* translators: 1: processed image count, 2: total image count. */
												__( '%1$d of %2$d', 'npcink-cloud-addon' ),
												$media_processed,
												$media_total
											) ) : esc_html__( 'Not available yet', 'npcink-cloud-addon' ); ?></td></tr>
											<tr><th scope="row"><?php esc_html_e( 'Plan image capacity', 'npcink-cloud-addon' ); ?></th><td data-npcink-site-media-capacity><?php echo ! empty( $media_capacity['available'] ) ? esc_html( sprintf(
												/* translators: 1: used image capacity, 2: image capacity limit, 3: remaining image capacity. */
												__( '%1$s used / %2$s limit / %3$s remaining', 'npcink-cloud-addon' ),
												self::format_entitlement_number( $media_capacity['used'] ?? 0 ),
												self::format_entitlement_number( $media_capacity['limit'] ?? 0 ),
												self::format_entitlement_number( $media_capacity['remaining'] ?? 0 )
											) ) : esc_html__( 'Not available yet', 'npcink-cloud-addon' ); ?></td></tr>
											<tr><th scope="row"><?php esc_html_e( 'Progress', 'npcink-cloud-addon' ); ?></th><td>
												<?php if ( 'error' === $media_state && 0 === $media_total ) : ?>
													<span data-npcink-site-media-progress-label><?php esc_html_e( 'Waiting to retry', 'npcink-cloud-addon' ); ?></span>
												<?php else : ?>
														<span class="npcink-cloud-segmented-progress npcink-cloud-site-media-progress<?php echo 'processing' === $media_state && 0 === $media_percent ? ' npcink-cloud-progress--indeterminate' : ''; ?>" data-npcink-site-media-progress role="progressbar" aria-label="<?php esc_attr_e( 'Site media recognition completion', 'npcink-cloud-addon' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $media_percent ); ?>" style="--npcink-cloud-progress: <?php echo esc_attr( (string) $media_percent ); ?>%;"></span>
													<span data-npcink-site-media-progress-label><?php echo $media_percent > 0 ? esc_html( $media_percent . '%' ) : ( 'processing' === $media_state ? esc_html__( 'Processing', 'npcink-cloud-addon' ) : esc_html__( 'Not started', 'npcink-cloud-addon' ) ); ?></span>
												<?php endif; ?>
											</td></tr>
										</tbody>
										</table>
										<details class="npcink-cloud-site-media-details">
											<summary><?php esc_html_e( 'View recognition details', 'npcink-cloud-addon' ); ?></summary>
											<table class="widefat striped npcink-cloud-site-media-status npcink-cloud-site-media-status--details" aria-label="<?php esc_attr_e( 'Site media recognition details', 'npcink-cloud-addon' ); ?>">
												<tbody>
													<tr><th scope="row"><?php esc_html_e( 'Visual evidence', 'npcink-cloud-addon' ); ?></th><td data-npcink-site-media-evidence><?php echo $media_evidence > 0 || in_array( $media_state, array( 'complete', 'partial' ), true ) ? esc_html( (string) $media_evidence ) : esc_html__( 'Not available yet', 'npcink-cloud-addon' ); ?></td></tr>
											<tr><th scope="row"><?php esc_html_e( 'Succeeded / failed', 'npcink-cloud-addon' ); ?></th><td data-npcink-site-media-outcomes><?php echo esc_html( sprintf( '%1$d / %2$d', absint( $media_status['successful'] ?? 0 ), absint( $media_status['failed'] ?? 0 ) ) ); ?></td></tr>
											<tr><th scope="row"><?php esc_html_e( 'Newly recognized / reused', 'npcink-cloud-addon' ); ?></th><td><?php echo $media_breakdown_available ? esc_html( sprintf( '%1$d / %2$d', $media_recognized, $media_reused ) ) : esc_html__( 'Not available for an earlier plan', 'npcink-cloud-addon' ); ?></td></tr>
											<tr><th scope="row"><?php esc_html_e( 'Excluded locally', 'npcink-cloud-addon' ); ?></th><td><?php echo esc_html( (string) $media_excluded ); ?></td></tr>
											<tr><th scope="row"><?php esc_html_e( 'Processed without visual evidence', 'npcink-cloud-addon' ); ?></th><td><?php echo esc_html( (string) $media_without_evidence ); ?></td></tr>
											<tr><th scope="row"><?php esc_html_e( 'Media library inventory', 'npcink-cloud-addon' ); ?></th><td><?php echo esc_html( sprintf( /* translators: %d: image attachment count. */ __( '%d image attachments', 'npcink-cloud-addon' ), $media_inventory_total ) ); ?></td></tr>
											<tr><th scope="row"><?php esc_html_e( 'Current speed', 'npcink-cloud-addon' ); ?></th><td data-npcink-site-media-speed><?php echo in_array( $media_state, array( 'processing', 'waiting_next_day' ), true ) && $media_rate > 0 ? esc_html( sprintf(
												/* translators: %s: processed images per minute. */
												__( '%s images/minute', 'npcink-cloud-addon' ),
												number_format_i18n( $media_rate, 1 )
											) ) : esc_html( in_array( $media_state, array( 'complete', 'partial' ), true ) ? __( 'Not applicable after completion', 'npcink-cloud-addon' ) : __( 'Estimating', 'npcink-cloud-addon' ) ); ?></td></tr>
													<tr><th scope="row"><?php esc_html_e( 'Estimated batch completion', 'npcink-cloud-addon' ); ?></th><td data-npcink-site-media-eta><?php echo in_array( $media_state, array( 'complete', 'partial' ), true ) ? esc_html__( 'This batch is complete', 'npcink-cloud-addon' ) : ( '' !== $media_eta ? esc_html( self::format_compact_datetime_value( $media_eta ) ) : ( 'processing' === $media_state ? esc_html__( 'Estimating', 'npcink-cloud-addon' ) : esc_html__( 'Not available yet', 'npcink-cloud-addon' ) ) ); ?></td></tr>
										<tr><th scope="row"><?php esc_html_e( 'Plan', 'npcink-cloud-addon' ); ?></th><td><?php echo '' !== trim( (string) ( $media_entitlement['package_label'] ?? '' ) ) ? esc_html( (string) $media_entitlement['package_label'] ) : esc_html__( 'Not available yet', 'npcink-cloud-addon' ); ?></td></tr>
										<tr><th scope="row"><?php esc_html_e( 'Available AI credits', 'npcink-cloud-addon' ); ?></th><td><?php echo isset( $media_credit_summary['remaining'] ) && null !== $media_credit_summary['remaining'] ? esc_html( self::format_entitlement_number( $media_credit_summary['remaining'] ) ) : esc_html__( 'Not available yet', 'npcink-cloud-addon' ); ?></td></tr>
											<tr><th scope="row"><?php esc_html_e( 'Runtime limits', 'npcink-cloud-addon' ); ?></th><td><?php echo $media_active_limit > 0 && $media_batch_limit > 0 ? esc_html( sprintf(
												/* translators: 1: concurrent Cloud task limit, 2: image limit per batch. */
												__( '%1$d concurrent task(s); up to %2$d images per batch', 'npcink-cloud-addon' ),
												$media_active_limit,
												$media_batch_limit
											) ) : esc_html__( 'Not available yet', 'npcink-cloud-addon' ); ?></td></tr>
												</tbody>
											</table>
										</details>
								<p class="description npcink-cloud-site-knowledge-summary__result--warning" role="status" data-npcink-site-media-poll-error hidden></p>
								<p class="description"><?php esc_html_e( 'Recognize local images so the editor can find them by meaning. Existing media and WordPress content are not changed.', 'npcink-cloud-addon' ); ?></p>
					<?php if ( 'waiting_next_day' === $media_state ) : ?>
						<p class="description"><?php esc_html_e( 'Recognition will continue automatically during the next eligible processing window.', 'npcink-cloud-addon' ); ?></p>
					<?php elseif ( ! empty( $media_capacity['available'] ) && 'limited' === (string) ( $media_capacity['status'] ?? '' ) ) : ?>
						<p class="description npcink-cloud-site-knowledge-summary__result--warning"><?php esc_html_e( 'Your plan image capacity is full. Existing recognized images can still be refreshed, but new images require available capacity.', 'npcink-cloud-addon' ); ?></p>
					<?php elseif ( in_array( (string) ( $media_plan['state'] ?? '' ), array( 'paused', 'error' ), true ) && '' !== $media_error ) : ?>
						<p class="description npcink-cloud-site-knowledge-summary__result--warning"><?php echo esc_html( $media_error ); ?></p>
					<?php elseif ( 'partial' === $media_state && empty( $media_plan['active'] ) ) : ?>
						<p class="description npcink-cloud-site-knowledge-summary__result--warning"><?php esc_html_e( 'An earlier batch is complete, but automatic continuation has not been started. Click Continue once to start the background plan; no further clicks are needed after that.', 'npcink-cloud-addon' ); ?></p>
					<?php elseif ( 'partial' === $media_state && ! empty( $media_plan['active'] ) ) : ?>
						<p class="description"><?php esc_html_e( 'More images remain. Background recognition will continue automatically; no further click is needed.', 'npcink-cloud-addon' ); ?></p>
					<?php elseif ( in_array( $media_state, array( 'complete', 'partial' ), true ) ) : ?>
						<?php if ( ( $media_excluded > 0 || $media_without_evidence > 0 ) && 'complete' === $media_state ) : ?>
							<p class="description"><?php echo esc_html( sprintf(
		/* translators: 1: eligible image count processed, 2: image count with visual evidence, 3: locally excluded image count, 4: processed images without visual evidence. */
		__( 'Processed: %1$d eligible images; visual evidence: %2$d; excluded locally: %3$d; without visual evidence: %4$d.', 'npcink-cloud-addon' ),
								$media_processed,
								absint( $media_status['evidence'] ?? 0 ),
								$media_excluded,
								$media_without_evidence
							) ); ?></p>
						<?php else : ?>
							<p class="description"><?php echo esc_html( sprintf(
								/* translators: 1: recognized image count, 2: image count with visual evidence. */
								__( 'Recognized: %1$d images; visual evidence: %2$d.', 'npcink-cloud-addon' ),
								absint( $media_status['indexed'] ?? 0 ),
								absint( $media_status['evidence'] ?? 0 )
							) ); ?></p>
						<?php endif; ?>
				<?php elseif ( 'processing' === $media_state ) : ?>
					<p class="description"><?php esc_html_e( 'Statistics will update when recognition finishes.', 'npcink-cloud-addon' ); ?></p>
					<p class="description"><?php esc_html_e( 'Estimated completion time will appear after Cloud starts processing.', 'npcink-cloud-addon' ); ?></p>
					<?php elseif ( 'error' === $media_state ) : ?>
						<p class="description"><?php esc_html_e( 'This batch did not complete. The last confirmed progress is shown above.', 'npcink-cloud-addon' ); ?></p>
				<?php endif; ?>
				<?php if ( in_array( $media_state, array( 'complete', 'partial' ), true ) && $media_total > 0 ) : ?>
						<p class="description"><?php echo esc_html( sprintf(
							/* translators: 1: processed eligible image count, 2: eligible image count, 3: elapsed Cloud processing seconds. */
							__( 'Processed %1$d of %2$d eligible images. Cloud processing time: %3$s seconds.', 'npcink-cloud-addon' ),
							$media_processed,
							$media_total,
							number_format_i18n( (float) ( $media_status['duration_seconds'] ?? 0 ), 1 )
						) ); ?></p>
					<p class="description"><?php echo esc_html( 'partial' === $media_state ? sprintf(
						/* translators: %d: number of images remaining after this batch. */
						__( 'This batch is complete. %d images remain.', 'npcink-cloud-addon' ),
						max( 0, $media_total - $media_processed )
					) : ( $media_excluded > 0 ? sprintf(
						/* translators: %d: number of images not included in recognition. */
						__( 'Recognition finished. %d images were not included because they did not meet the current image requirements.', 'npcink-cloud-addon' ),
						$media_excluded
					) : __( 'All images have been recognized.', 'npcink-cloud-addon' ) ) ); ?></p>
				<?php endif; ?>
								<?php if ( empty( $media_plan['active'] ) ) : ?>
									<?php self::render_site_media_index_refresh_form( $media_state ); ?>
								<?php endif; ?>
								<?php self::render_site_media_status_refresh_form(); ?>
							</div>
						</section>
						<div class="npcink-cloud-site-knowledge-links">
							<a href="<?php echo esc_url( self::tab_view_url( 'site_knowledge', 'index' ) ); ?>"><?php esc_html_e( 'Knowledge base maintenance', 'npcink-cloud-addon' ); ?></a>
							<a href="<?php echo esc_url( $cloud_site_knowledge_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View Cloud details', 'npcink-cloud-addon' ); ?></a>
						</div>
					<?php if ( ! $delivery_enabled ) : ?>
						<p class="description npcink-cloud-site-knowledge-disabled-note"><?php esc_html_e( 'Delivery is off; refresh controls and routine delivery rows are hidden.', 'npcink-cloud-addon' ); ?></p>
							<?php if ( $local_delivery_needs_attention ) : ?>
								<p><a href="<?php echo esc_url( self::tab_view_url( 'advanced', 'checks' ) ); ?>"><?php esc_html_e( 'View advanced troubleshooting', 'npcink-cloud-addon' ); ?></a></p>
							<?php endif; ?>
						<?php
						return;
						endif;
						?>
							<?php self::render_site_knowledge_article_coverage( $status_summary ); ?>
						<span hidden data-npcink-site-knowledge-refresh data-npcink-site-knowledge-state="<?php echo esc_attr( (string) ( $cloud_usage['state'] ?? 'not_refreshed' ) ); ?>"></span>
					<?php
		}

		/** Renders the recovery-only full knowledge base update action. */
		private static function render_site_knowledge_refresh_form( string $label ): void {
			?>
			<div class="npcink-cloud-site-knowledge-summary__action">
				<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( self::ACTION_REFRESH_SITE_KNOWLEDGE ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REFRESH_SITE_KNOWLEDGE ); ?>" />
					<button type="submit" class="button button-secondary"><?php echo esc_html( $label ); ?></button>
				</form>
			</div>
			<?php
		}

		/** Renders the operator-triggered media recognition action. */
		private static function render_site_media_index_refresh_form( string $state = 'not_started' ): void {
			$disabled = in_array( $state, array( 'processing', 'waiting_next_day' ), true );
			$labels = array(
				'not_started' => __( 'Start media recognition', 'npcink-cloud-addon' ),
				'processing' => __( 'Recognition in progress', 'npcink-cloud-addon' ),
				'partial' => __( 'Continue recognizing remaining images', 'npcink-cloud-addon' ),
				'error' => __( 'Retry this batch', 'npcink-cloud-addon' ),
				'complete' => __( 'Check for new images', 'npcink-cloud-addon' ),
				'waiting_next_day' => __( 'Waiting for background processing', 'npcink-cloud-addon' ),
			);
			$label = $labels[ $state ] ?? $labels['not_started'];
			?>
			<div class="npcink-cloud-site-knowledge-summary__action">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( self::ACTION_REFRESH_SITE_MEDIA_INDEX ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REFRESH_SITE_MEDIA_INDEX ); ?>" />
					<button type="submit" class="button button-secondary" <?php disabled( $disabled ); ?>><?php echo esc_html( $label ); ?></button>
				</form>
			</div>
			<?php
		}

		private static function render_site_media_status_refresh_form(): void {
			$status = self::get_media_index_status();
			if ( empty( $status['run_id'] ) || 'processing' !== (string) ( $status['state'] ?? '' ) ) {
				return;
			}
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block; margin-left:8px;">
				<?php wp_nonce_field( self::ACTION_REFRESH_SITE_MEDIA_STATUS ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REFRESH_SITE_MEDIA_STATUS ); ?>" />
				<button type="submit" class="button-link"><?php esc_html_e( 'Refresh progress', 'npcink-cloud-addon' ); ?></button>
			</form>
			<?php
		}

		/** Returns whether an uncertain transport result may be safely replayed. */
		private static function is_retryable_media_recognition_transport_failure( string $error_code ): bool {
			return 'cloud_runtime_request_failed' === sanitize_key( $error_code );
		}

		/** Adds stable user-facing counts without changing persisted execution truth. */
		private static function decorate_media_recognition_display_metrics( array $status ): array {
			$state = sanitize_key( (string) ( $status['state'] ?? 'not_started' ) );
			$inventory_total = absint( $status['total'] ?? 0 );
			$indexed = min( $inventory_total, absint( $status['indexed'] ?? 0 ) );
			$has_breakdown = 'v1' === sanitize_key( (string) ( $status['count_breakdown_version'] ?? '' ) );
			$screened = absint( $status['screened_count'] ?? 0 );
			if ( 'complete' === $state && $inventory_total > $indexed ) {
				$screened = max( $screened, $inventory_total - $indexed );
			}
			$screened = min( $inventory_total, $screened );
			$eligible_total = max( 0, $inventory_total - $screened );
			$eligible_processed = max( 0, min( $eligible_total, $indexed - min( $indexed, $screened ) ) );
			if ( 'complete' === $state ) {
				$eligible_processed = $eligible_total;
			}
			$evidence = min( $eligible_processed, absint( $status['evidence'] ?? 0 ) );

			$status['inventory_total'] = $inventory_total;
			$status['eligible_total'] = $eligible_total;
			$status['eligible_processed'] = $eligible_processed;
			$status['excluded_count'] = $screened;
			$status['without_evidence_count'] = max( 0, $eligible_processed - $evidence - absint( $status['failed'] ?? 0 ) );
			$status['display_percent'] = $eligible_total > 0 ? min( 100, (int) floor( $eligible_processed / $eligible_total * 100 ) ) : ( 'complete' === $state ? 100 : 0 );
			$status['count_breakdown_available'] = $has_breakdown;

			return $status;
		}

		/** Stops an older active plan that already reached the transport retry limit. */
		private static function pause_media_recognition_plan_after_transport_retry_limit( array $plan, array $status ): bool {
			$retry_count = absint( $plan['transport_retry_count'] ?? 0 );
			if ( $retry_count < self::MEDIA_PLAN_TRANSPORT_RETRY_LIMIT || ! empty( $status['run_id'] ) ) {
				return false;
			}

			$reason = sanitize_key( (string) ( $plan['last_transport_error_code'] ?? 'cloud_runtime_request_failed' ) );
			$plan['active'] = false;
			$plan['state'] = 'paused';
			$plan['pause_reason'] = $reason;
			$plan['error_code'] = $reason;
			$plan['error'] = '';
			$plan['updated_at'] = current_time( 'mysql' );
			unset( $plan['next_eligible_at'] );

			$status['state'] = 'error';
			$status['error_code'] = $reason;
			$status['error'] = '';
			$status['retry_reason'] = $reason;
			$status['transport_retry_count'] = $retry_count;
			$status['updated_at'] = current_time( 'mysql' );
			self::set_media_index_status( $status );
			self::set_media_recognition_plan( $plan );
			self::record_media_recognition_event( 'failed', 'failed', '', 0, 0, 0, $reason );

			return true;
		}

		/** Builds the unchanged-cursor state for one bounded transport replay. */
		private static function media_recognition_transport_retry_state( array $plan, array $status, string $error_code ): array {
			$retry_count = min( 1000, absint( $plan['transport_retry_count'] ?? 0 ) + 1 );
			$delay = min( 15 * MINUTE_IN_SECONDS, MINUTE_IN_SECONDS * ( 2 ** min( 4, $retry_count - 1 ) ) );
			$exhausted = $retry_count >= self::MEDIA_PLAN_TRANSPORT_RETRY_LIMIT;
			$error_code = sanitize_key( $error_code );

			$plan['active'] = ! $exhausted;
			$plan['state'] = $exhausted ? 'paused' : 'partial';
			$plan['transport_retry_count'] = $retry_count;
			$plan['last_transport_error_code'] = $error_code;
			$plan['error_code'] = $exhausted ? $error_code : '';
			$plan['error'] = '';
			$plan['updated_at'] = current_time( 'mysql' );
			unset( $plan['next_eligible_at'] );
			if ( $exhausted ) {
				$plan['pause_reason'] = $error_code;
			} else {
				unset( $plan['pause_reason'] );
			}

			$status['state'] = $exhausted ? 'error' : 'partial';
			$status['error_code'] = $exhausted ? $error_code : '';
			$status['error'] = '';
			$status['transport_retry_count'] = $retry_count;
			$status['retry_reason'] = $error_code;
			$status['updated_at'] = current_time( 'mysql' );

			return array(
				'plan' => $plan,
				'status' => $status,
				'delay' => $delay,
				'exhausted' => $exhausted,
			);
		}

		private static function media_recognition_error_message( string $error_code, string $fallback = '' ): string {
			$error_code = strtolower( trim( $error_code ) );
			if ( false !== strpos( $error_code, 'media_capacity_exhausted' ) ) {
				return __( 'Your plan image capacity is full. Increase the media image limit or remove no-longer-needed Cloud media evidence before continuing.', 'npcink-cloud-addon' );
			}
			if ( false !== strpos( $error_code, 'commercial_quota_exceeded' ) || false !== strpos( $error_code, 'commercial.quota_exceeded' ) ) {
				return __( 'Available AI credits are insufficient. Review the plan or add credits before continuing.', 'npcink-cloud-addon' );
			}
			if ( false !== strpos( $error_code, 'batch_exceeds_daily_limit' ) ) {
				return __( 'This batch exceeds the platform daily media recognition limit. Reduce the batch or adjust the limit in Cloud administration.', 'npcink-cloud-addon' );
			}
			if ( false !== strpos( $error_code, 'background_disabled' ) ) {
				return __( 'Background media recognition is disabled in Cloud administration.', 'npcink-cloud-addon' );
			}
			if ( false !== strpos( $error_code, 'provider_not_configured' ) || false !== strpos( $error_code, 'profile_unavailable' ) ) {
				return __( 'The configured image recognition model is not available. Verify the vision.ai runtime profile in Cloud administration.', 'npcink-cloud-addon' );
			}
			if ( false !== strpos( $error_code, 'provider_quota' ) || false !== strpos( $error_code, 'provider.quota' ) ) {
				return __( 'The image recognition provider quota is exhausted. Check the provider account before retrying.', 'npcink-cloud-addon' );
			}
			if ( false !== strpos( $error_code, 'timeout' ) ) {
				return __( 'Image recognition timed out. Retry this batch later.', 'npcink-cloud-addon' );
			}
			if ( false !== strpos( $error_code, 'site_knowledge_projection_failed' ) ) {
				return __( 'Images were recognized, but Cloud could not update the media search index. Retry this batch later.', 'npcink-cloud-addon' );
			}
			if ( false !== strpos( $error_code, 'run_not_found' ) || false !== strpos( $error_code, 'result_expired' ) ) {
				return __( 'This recognition task is no longer available. Retry this batch to continue.', 'npcink-cloud-addon' );
			}
			if ( false !== strpos( $error_code, 'active_cloud_runs' ) || false !== stripos( $fallback, 'exceeded max active cloud runs' ) ) {
				return __( 'Another Cloud task is already running. Please wait for it to finish, then try again.', 'npcink-cloud-addon' );
			}

			return '' !== trim( $fallback ) ? sanitize_text_field( $fallback ) : __( 'Cloud media recognition did not complete. Retry this batch later.', 'npcink-cloud-addon' );
		}

		/** Records one metadata-only media recognition lifecycle event when monitoring is enabled. */
		private static function record_media_recognition_event( string $event, string $status, string $run_id, int $processed, int $failed, int $latency_ms, string $error_code = '' ): void {
			if ( ! class_exists( 'Npcink_Cloud_Observability_Collector' ) ) {
				return;
			}
			Npcink_Cloud_Observability_Collector::capture_event(
				array(
					'schema_version' => '2026-08-27',
					'plugin_slug' => 'npcink-cloud-addon',
					'plugin_version' => defined( 'NPCINK_CLOUD_ADDON_VERSION' ) ? (string) NPCINK_CLOUD_ADDON_VERSION : '',
					'source' => 'local',
					'event_kind' => 'addon.media_recognition.' . sanitize_key( $event ),
					'status' => sanitize_key( $status ),
					'error_code' => sanitize_key( $error_code ),
					'latency_ms' => max( 0, $latency_ms ),
					'ability_id' => 'npcink-cloud/image-context-evidence',
					'correlation_id' => sanitize_text_field( $run_id ),
					'executed_count' => max( 0, $processed ),
					'failed_count' => max( 0, $failed ),
					'content_storage' => 'omitted_metadata_only',
				)
			);
		}

		/** Returns the short-lived media recognition status projection. */
		private static function get_media_index_status(): array {
			$status = get_transient( self::MEDIA_STATUS_TRANSIENT );
			if ( is_array( $status ) ) {
				return self::decorate_media_recognition_display_metrics( self::localize_media_recognition_status_error( $status ) );
			}

			$plan = self::get_media_recognition_plan();
			if ( empty( $plan['plan_id'] ) ) {
				return self::decorate_media_recognition_display_metrics( array( 'state' => 'not_started', 'indexed' => 0, 'evidence' => 0 ) );
			}

			return self::decorate_media_recognition_display_metrics( self::localize_media_recognition_status_error( array(
				'state' => sanitize_key( (string) ( $plan['state'] ?? 'not_started' ) ),
				'run_id' => sanitize_text_field( (string) ( $plan['current_run_id'] ?? '' ) ),
				'page' => max( 1, absint( $plan['current_page'] ?? 1 ) ),
				'next_page' => absint( $plan['next_page'] ?? 0 ),
				'per_page' => min( 10, absint( $plan['per_page'] ?? 0 ) ),
				'batch_size' => min( 10, absint( $plan['batch_size'] ?? $plan['per_page'] ?? 0 ) ),
				'has_more' => array_key_exists( 'has_more', $plan ) ? ! empty( $plan['has_more'] ) : absint( $plan['next_page'] ?? 0 ) > 0,
				'total' => absint( $plan['total_estimate'] ?? 0 ),
				'indexed' => absint( $plan['processed_count'] ?? 0 ),
				'completed_before' => absint( $plan['completed_before'] ?? $plan['processed_count'] ?? 0 ),
				'successful' => absint( $plan['successful_count'] ?? 0 ),
				'successful_before' => absint( $plan['successful_before'] ?? $plan['successful_count'] ?? 0 ),
				'failed' => absint( $plan['failed_count'] ?? 0 ),
				'failed_before' => absint( $plan['failed_before'] ?? $plan['failed_count'] ?? 0 ),
				'evidence' => absint( $plan['evidence_count'] ?? 0 ),
				'evidence_before' => absint( $plan['evidence_before'] ?? $plan['evidence_count'] ?? 0 ),
				'recognized_count' => absint( $plan['recognized_count'] ?? 0 ),
				'reused_count' => absint( $plan['reused_count'] ?? 0 ),
				'screened_count' => absint( $plan['screened_count'] ?? 0 ),
				'count_breakdown_version' => sanitize_key( (string) ( $plan['count_breakdown_version'] ?? '' ) ),
				'batch_reused_count' => absint( $plan['batch_reused_count'] ?? 0 ),
				'batch_screened_count' => absint( $plan['batch_screened_count'] ?? 0 ),
				'duration_seconds' => max( 0, (float) ( $plan['duration_seconds'] ?? 0 ) ),
				'duration_before' => max( 0, (float) ( $plan['duration_before'] ?? $plan['duration_seconds'] ?? 0 ) ),
				'percent' => min( 100, absint( $plan['percent'] ?? 0 ) ),
				'items_per_minute' => max( 0, (float) ( $plan['items_per_minute'] ?? 0 ) ),
				'eta_at' => sanitize_text_field( (string) ( $plan['eta_at'] ?? '' ) ),
				'next_eligible_at' => sanitize_text_field( (string) ( $plan['next_eligible_at'] ?? '' ) ),
				'error_code' => sanitize_key( (string) ( $plan['error_code'] ?? $plan['pause_reason'] ?? '' ) ),
				'error' => sanitize_text_field( (string) ( $plan['error'] ?? '' ) ),
				'terminal_event_recorded' => ! empty( $plan['terminal_event_recorded'] ),
				'projected_run_id' => sanitize_text_field( (string) ( $plan['projected_run_id'] ?? '' ) ),
				'updated_at' => sanitize_text_field( (string) ( $plan['updated_at'] ?? '' ) ),
			) ) );
		}

		/** Resolves persisted media error codes in the current request locale. */
		private static function localize_media_recognition_status_error( array $status ): array {
			$error_code = sanitize_key( (string) ( $status['error_code'] ?? '' ) );
			$status['error_code'] = $error_code;
			if ( '' !== $error_code ) {
				$status['error'] = self::media_recognition_error_message( $error_code );
			} else {
				$status['error'] = sanitize_text_field( (string) ( $status['error'] ?? '' ) );
			}

			return $status;
		}

		/** @return array<string,mixed> */
		private static function get_media_recognition_plan(): array {
			$plan = get_option( self::MEDIA_PLAN_OPTION, array() );
			return is_array( $plan ) ? $plan : array();
		}

		/** @param array<string,mixed> $plan */
		private static function set_media_recognition_plan( array $plan ): void {
			update_option( self::MEDIA_PLAN_OPTION, $plan, false );
		}

		/**
		 * Starts a fresh inventory pass while retaining only bounded trigger ids.
		 *
		 * The trigger ids are diagnostic/idempotency markers. Inventory and
		 * attachment truth continue to come from WordPress through Toolbox.
		 *
		 * @param array<string,mixed> $previous_plan Previous durable plan.
		 * @param array<int,int>      $attachment_ids Attachment ids that requested the pass.
		 * @param int                 $initiated_by WordPress user id captured at the hook.
		 * @return void
		 */
		private static function start_media_recognition_rescan( array $previous_plan, array $attachment_ids, int $initiated_by, bool $allow_full_inventory = false ): void {
			$attachment_ids = self::normalize_media_rescan_attachment_ids( $attachment_ids );
			if ( empty( $attachment_ids ) && ! $allow_full_inventory ) {
				return;
			}

			$per_page = max( 1, min( 10, absint( $previous_plan['per_page'] ?? 10 ) ) );
			$now = current_time( 'mysql' );
			$plan = array(
				'active' => true,
				'plan_id' => 'media_plan_' . wp_generate_uuid4(),
				'upload_attempt_id' => 'media_upload_attempt_' . wp_generate_uuid4(),
				'initiated_by' => max( 0, $initiated_by ),
				'started_at' => $now,
				'updated_at' => $now,
				'state' => 'partial',
				'current_run_id' => '',
				'current_page' => 0,
				'next_page' => 1,
				'per_page' => $per_page,
				'processed_count' => 0,
				'successful_count' => 0,
				'failed_count' => 0,
				'evidence_count' => 0,
				'recognized_count' => 0,
				'reused_count' => 0,
				'screened_count' => 0,
				'count_breakdown_version' => 'v1',
				'duration_seconds' => 0,
				'percent' => 0,
				'rescan_attachment_ids' => $attachment_ids,
				'pending_rescan_attachment_ids' => array(),
			);
			self::set_media_recognition_plan( $plan );
			self::set_media_index_status(
				array(
					'state' => 'partial',
					'indexed' => 0,
					'completed_before' => 0,
					'successful' => 0,
					'successful_before' => 0,
					'failed' => 0,
					'failed_before' => 0,
					'evidence' => 0,
					'evidence_before' => 0,
					'recognized_count' => 0,
					'reused_count' => 0,
					'screened_count' => 0,
					'count_breakdown_version' => 'v1',
					'batch_reused_count' => 0,
					'batch_screened_count' => 0,
					'batch_size' => 0,
					'per_page' => $per_page,
					'page' => 0,
					'next_page' => 1,
					'has_more' => true,
					'total' => 0,
					'percent' => 0,
					'duration_seconds' => 0,
					'run_id' => '',
					'error_code' => '',
					'error' => '',
					'updated_at' => $now,
				)
			);
			self::schedule_media_recognition_plan( 30 );
		}

		/** Starts one pending inventory pass after the current pass completes. */
		private static function restart_pending_media_recognition_rescan( array $plan ): bool {
			$pending_ids = self::normalize_media_rescan_attachment_ids( $plan['pending_rescan_attachment_ids'] ?? array() );
			if ( empty( $pending_ids ) ) {
				return false;
			}

			self::start_media_recognition_rescan(
				$plan,
				$pending_ids,
				absint( $plan['pending_rescan_initiated_by'] ?? $plan['initiated_by'] ?? 0 )
			);
			return true;
		}

		/** @return array<int,int> */
		private static function normalize_media_rescan_attachment_ids( $attachment_ids ): array {
			if ( ! is_array( $attachment_ids ) ) {
				return array();
			}

			return array_slice(
				array_values( array_unique( array_filter( array_map( 'absint', $attachment_ids ) ) ) ),
				0,
				100
			);
		}

		/** Keeps a repeated administrator request attached to the existing plan. */
		private static function resume_active_media_recognition_plan( int $initiated_by = 0 ): bool {
			$plan = self::get_media_recognition_plan();
			if ( empty( $plan['active'] ) ) {
				return false;
			}
			if ( $initiated_by > 0 ) {
				$plan['initiated_by'] = $initiated_by;
				$plan['updated_at'] = current_time( 'mysql' );
				self::set_media_recognition_plan( $plan );
			}

			$delay = 30;
			$next_eligible_at = strtotime( (string) ( $plan['next_eligible_at'] ?? '' ) );
			if ( false !== $next_eligible_at && $next_eligible_at > time() ) {
				$delay = max( 60, $next_eligible_at - time() );
			}
			self::schedule_media_recognition_plan( $delay );
			return true;
		}

		/** Restores an inactive failed plan at the same cursor for one Cron-owned retry. */
		private static function resume_paused_media_recognition_plan( array $plan, array $status, int $initiated_by ): void {
			$page = max( 1, absint( $status['page'] ?? $plan['current_page'] ?? 1 ) );
			$per_page = self::media_recognition_plan_per_page( $plan, $status );
			$per_page = $per_page > 0 ? $per_page : 10;
			$now = current_time( 'mysql' );
			$plan['active'] = true;
			$plan['initiated_by'] = max( 0, $initiated_by );
			$plan['state'] = 'partial';
			$plan['current_run_id'] = '';
			$plan['upload_attempt_id'] = 'media_upload_attempt_' . wp_generate_uuid4();
			$plan['current_page'] = $page;
			$plan['next_page'] = $page;
			$plan['per_page'] = $per_page;
			$plan['updated_at'] = $now;
			unset( $plan['transport_retry_count'], $plan['last_transport_error_code'], $plan['pause_reason'], $plan['next_eligible_at'], $plan['error_code'], $plan['error'] );
			$status['state'] = 'partial';
			$status['page'] = $page;
			$status['next_page'] = $page;
			$status['per_page'] = $per_page;
			$status['has_more'] = true;
			$status['run_id'] = '';
			$status['error_code'] = '';
			$status['error'] = '';
			$status['updated_at'] = $now;
			unset( $status['transport_retry_count'], $status['retry_reason'], $status['next_eligible_at'] );
			self::set_media_recognition_plan( $plan );
			self::set_media_index_status( $status );
			self::schedule_media_recognition_plan( 15 );
		}

		/** Copies bounded status fields into the existing durable plan option. */
		private static function merge_media_recognition_plan_progress( array $plan, array $status ): array {
			$plan['state'] = sanitize_key( (string) ( $status['state'] ?? $plan['state'] ?? 'not_started' ) );
			$plan['current_run_id'] = sanitize_text_field( (string) ( $status['run_id'] ?? $plan['current_run_id'] ?? '' ) );
			$plan['current_page'] = max( 1, absint( $status['page'] ?? $plan['current_page'] ?? 1 ) );
			$plan['next_page'] = absint( $status['next_page'] ?? $plan['next_page'] ?? 0 );
			$plan['per_page'] = min( 10, absint( $status['per_page'] ?? $plan['per_page'] ?? 0 ) );
			$plan['batch_size'] = min( 10, absint( $status['batch_size'] ?? $plan['batch_size'] ?? 0 ) );
			if ( array_key_exists( 'has_more', $status ) ) {
				$plan['has_more'] = ! empty( $status['has_more'] );
			}
			$plan['total_estimate'] = absint( $status['total'] ?? $plan['total_estimate'] ?? 0 );
			$plan['processed_count'] = absint( $status['indexed'] ?? $plan['processed_count'] ?? 0 );
			$plan['completed_before'] = absint( $status['completed_before'] ?? $plan['completed_before'] ?? $plan['processed_count'] );
			$plan['successful_count'] = absint( $status['successful'] ?? $plan['successful_count'] ?? 0 );
			$plan['successful_before'] = absint( $status['successful_before'] ?? $plan['successful_before'] ?? $plan['successful_count'] );
			$plan['failed_count'] = absint( $status['failed'] ?? $plan['failed_count'] ?? 0 );
			$plan['failed_before'] = absint( $status['failed_before'] ?? $plan['failed_before'] ?? $plan['failed_count'] );
			$plan['evidence_count'] = absint( $status['evidence'] ?? $plan['evidence_count'] ?? 0 );
			$plan['evidence_before'] = absint( $status['evidence_before'] ?? $plan['evidence_before'] ?? $plan['evidence_count'] );
			$plan['recognized_count'] = absint( $status['recognized_count'] ?? $plan['recognized_count'] ?? 0 );
			$plan['reused_count'] = absint( $status['reused_count'] ?? $plan['reused_count'] ?? 0 );
			$plan['screened_count'] = absint( $status['screened_count'] ?? $plan['screened_count'] ?? 0 );
			$plan['count_breakdown_version'] = sanitize_key( (string) ( $status['count_breakdown_version'] ?? $plan['count_breakdown_version'] ?? '' ) );
			$plan['batch_reused_count'] = absint( $status['batch_reused_count'] ?? $plan['batch_reused_count'] ?? 0 );
			$plan['batch_screened_count'] = absint( $status['batch_screened_count'] ?? $plan['batch_screened_count'] ?? 0 );
			$plan['duration_seconds'] = max( 0, (float) ( $status['duration_seconds'] ?? $plan['duration_seconds'] ?? 0 ) );
			$plan['duration_before'] = max( 0, (float) ( $status['duration_before'] ?? $plan['duration_before'] ?? $plan['duration_seconds'] ) );
			$plan['percent'] = min( 100, absint( $status['percent'] ?? $plan['percent'] ?? 0 ) );
			$plan['items_per_minute'] = max( 0, (float) ( $status['items_per_minute'] ?? $plan['items_per_minute'] ?? 0 ) );
			$plan['eta_at'] = sanitize_text_field( (string) ( $status['eta_at'] ?? $plan['eta_at'] ?? '' ) );
			$plan['error_code'] = sanitize_key( (string) ( $status['error_code'] ?? $plan['error_code'] ?? '' ) );
			$plan['error'] = sanitize_text_field( (string) ( $status['error'] ?? $plan['error'] ?? '' ) );
			$plan['terminal_event_recorded'] = ! empty( $status['terminal_event_recorded'] );
			$plan['projected_run_id'] = sanitize_text_field( (string) ( $status['projected_run_id'] ?? $plan['projected_run_id'] ?? '' ) );
			$plan['updated_at'] = sanitize_text_field( (string) ( $status['updated_at'] ?? $plan['updated_at'] ?? current_time( 'mysql' ) ) );
			if ( ! empty( $status['next_eligible_at'] ) ) {
				$plan['next_eligible_at'] = sanitize_text_field( (string) $status['next_eligible_at'] );
			} else {
				unset( $plan['next_eligible_at'] );
			}

			return $plan;
		}

		/** Keeps an active inventory cursor bound to the batch size that created it. */
		private static function media_recognition_plan_per_page( array $plan, array $status ): int {
			foreach ( array( $plan['per_page'] ?? 0, $status['per_page'] ?? 0, $status['batch_size'] ?? 0 ) as $candidate ) {
				$candidate = absint( $candidate );
				if ( $candidate > 0 ) {
					return min( 10, $candidate );
				}
			}

			return 0;
		}

		private static function schedule_media_recognition_plan( int $delay ): void {
			if ( ! function_exists( 'wp_schedule_single_event' ) ) {
				return;
			}
			if ( false === wp_next_scheduled( self::MEDIA_PLAN_CRON ) ) {
				wp_schedule_single_event( time() + max( 15, $delay ), self::MEDIA_PLAN_CRON );
			}
		}

		/** @param array<string,mixed> $status Status projection. */
		private static function set_media_index_status( array $status ): void {
			$status['error_code'] = sanitize_key( (string) ( $status['error_code'] ?? '' ) );
			if ( '' !== $status['error_code'] ) {
				// Error codes are durable; user-facing translations belong to the current request locale.
				$status['error'] = '';
			}
			set_transient( self::MEDIA_STATUS_TRANSIENT, $status, DAY_IN_SECONDS );
			$plan = self::get_media_recognition_plan();
			if ( ! empty( $plan['plan_id'] ) ) {
				self::set_media_recognition_plan( self::merge_media_recognition_plan_progress( $plan, $status ) );
			}
		}

		/**
		 * Renders the bounded article-level Cloud index comparison.
		 *
		 * @param array<string,mixed> $summary Retained Site Knowledge status summary.
		 * @return void
		 */
		private static function render_site_knowledge_article_coverage( array $summary ): void {
			$coverage = is_array( $summary['article_coverage'] ?? null ) ? $summary['article_coverage'] : array();
			$rows = array_values(
				array_filter(
					Npcink_Cloud_Site_Knowledge_Runtime_Bridge::article_index_statuses( $summary ),
					static function ( array $row ): bool {
						return 'not_indexed' === (string) ( $row['status'] ?? '' );
					}
				)
			);
			$pending_count = absint( $coverage['not_indexed_count'] ?? 0 );
			if ( empty( $summary['available'] ) || $pending_count < 1 || empty( $rows ) ) {
				?><span hidden data-npcink-site-knowledge-article-coverage></span><?php
				return;
			}
			$visible_rows = array_slice( $rows, 0, 50 );
			?>
			<section class="npcink-cloud-site-knowledge-coverage" data-npcink-site-knowledge-article-coverage>
				<div class="npcink-cloud-section-heading npcink-cloud-site-knowledge-coverage__heading">
					<h3><?php echo esc_html( sprintf(
						/* translators: %d: number of public posts or pages waiting for knowledge base processing. */
						__( 'Pending content (%d)', 'npcink-cloud-addon' ),
						$pending_count
					) ); ?></h3>
				</div>
				<?php if ( count( $rows ) > count( $visible_rows ) ) : ?>
					<p class="description"><?php esc_html_e( 'Showing the first 50 pending items.', 'npcink-cloud-addon' ); ?></p>
				<?php endif; ?>
				<?php self::render_site_knowledge_article_table( $visible_rows ); ?>
			</section>
			<?php
		}

		/** Renders one page of article-level status rows. */
		private static function render_site_knowledge_article_table( array $rows ): void {
			?>
			<div class="npcink-cloud-site-knowledge-article-table-wrap">
				<table class="widefat striped npcink-cloud-site-knowledge-article-table">
					<thead><tr>
						<th scope="col"><?php esc_html_e( 'Article', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Last modified', 'npcink-cloud-addon' ); ?></th>
					</tr></thead>
					<tbody>
					<?php foreach ( $rows as $row ) :
						$title = '' !== (string) ( $row['title'] ?? '' ) ? (string) $row['title'] : __( '(no title)', 'npcink-cloud-addon' );
					?>
						<tr>
							<td><a href="<?php echo esc_url( (string) ( $row['url'] ?? '' ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $title ); ?></a></td>
							<td><?php echo esc_html( self::format_datetime_value( (string) ( $row['modified_gmt'] ?? '' ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<?php
		}

		/**
		 * Renders read-only Site Knowledge bridge health detail.
		 *
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @return void
		 */
		private static function render_site_knowledge_bridge_health_detail( array $site_knowledge ): void {
			$wp_cron_disabled = ! empty( $site_knowledge['wp_cron_disabled'] );
			$last_error_code = (string) ( $site_knowledge['last_error_code'] ?? '' );
			?>
				<h4><?php esc_html_e( 'Knowledge base delivery', 'npcink-cloud-addon' ); ?></h4>
			<table class="widefat striped npcink-cloud-site-knowledge-status npcink-cloud-site-knowledge-health-detail">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last success', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['last_success_at'] ?? '' ) ) ); ?></td>
					</tr>
					<?php if ( '' !== $last_error_code ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last error code', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( $last_error_code ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last error time', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['last_error_at'] ?? '' ) ) ); ?></td>
					</tr>
					<?php endif; ?>
					<?php if ( $wp_cron_disabled ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'WP-Cron disabled', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo $wp_cron_disabled ? esc_html__( 'yes', 'npcink-cloud-addon' ) : esc_html__( 'no', 'npcink-cloud-addon' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Manual flush command', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( self::format_empty( (string) ( $site_knowledge['wp_cli_command'] ?? $site_knowledge['cron_command'] ?? '' ) ) ); ?></code></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Renders administrator Site Knowledge index operations.
		 *
		 * @param bool $delivery_enabled Whether local delivery is enabled.
		 * @return void
		 */
		private static function render_site_knowledge_index_operations( bool $delivery_enabled ): void {
			?>
			<section class="npcink-cloud-site-knowledge-index-panel" aria-labelledby="npcink-cloud-site-knowledge-index-title">
				<h3 id="npcink-cloud-site-knowledge-index-title"><?php esc_html_e( 'Index operations', 'npcink-cloud-addon' ); ?></h3>
				<p class="description"><?php esc_html_e( 'Use only for initial indexing, rebuilds, or explicit Cloud index cleanup.', 'npcink-cloud-addon' ); ?></p>
				<?php if ( ! $delivery_enabled ) : ?>
					<p class="description npcink-cloud-site-knowledge-disabled-note"><?php esc_html_e( 'Site Knowledge delivery is disabled locally. Enable delivery before starting or rebuilding the index.', 'npcink-cloud-addon' ); ?></p>
				<?php endif; ?>
				<div class="npcink-cloud-index-actions">
					<details class="npcink-cloud-inline-note npcink-cloud-index-actions__note">
						<summary>
							<span aria-hidden="true" class="npcink-cloud-inline-note__icon">!</span>
							<?php esc_html_e( 'These actions send intent only; WordPress content is not changed.', 'npcink-cloud-addon' ); ?>
						</summary>
						<p><?php esc_html_e( 'These actions send local administrator delivery intent and bounded public WordPress content for Cloud-owned Site Knowledge operations. Cloud performs indexing, rebuild, deletion, and diagnostics; WordPress content is not changed.', 'npcink-cloud-addon' ); ?></p>
					</details>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>" />
						<input type="hidden" name="site_knowledge_index_action" value="start" />
						<p><strong><?php esc_html_e( 'Start indexing', 'npcink-cloud-addon' ); ?></strong></p>
						<div class="npcink-cloud-index-action__controls">
							<button type="submit" class="button button-secondary" <?php disabled( ! $delivery_enabled ); ?>><?php esc_html_e( 'Start indexing', 'npcink-cloud-addon' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Send a public post and page manifest.', 'npcink-cloud-addon' ); ?></p>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>" />
						<input type="hidden" name="site_knowledge_index_action" value="rebuild" />
						<p><strong><?php esc_html_e( 'Rebuild index', 'npcink-cloud-addon' ); ?></strong></p>
						<div class="npcink-cloud-index-action__controls">
							<input type="text" name="site_knowledge_confirmation" placeholder="<?php esc_attr_e( 'Type REBUILD', 'npcink-cloud-addon' ); ?>" />
							<button type="submit" class="button button-secondary" <?php disabled( ! $delivery_enabled ); ?>><?php esc_html_e( 'Rebuild index', 'npcink-cloud-addon' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Ask Cloud to clear and rebuild the site index.', 'npcink-cloud-addon' ); ?></p>
					</form>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>" />
						<input type="hidden" name="site_knowledge_index_action" value="delete" />
						<p><strong><?php esc_html_e( 'Delete site index', 'npcink-cloud-addon' ); ?></strong></p>
						<div class="npcink-cloud-index-action__controls">
							<input type="text" name="site_knowledge_confirmation" placeholder="<?php esc_attr_e( 'Type DELETE', 'npcink-cloud-addon' ); ?>" />
							<button type="submit" class="button button-secondary npcink-cloud-button-danger"><?php esc_html_e( 'Delete site index', 'npcink-cloud-addon' ); ?></button>
						</div>
						<p class="description"><?php esc_html_e( 'Ask Cloud to delete the site index only.', 'npcink-cloud-addon' ); ?></p>
					</form>
				</div>
			</section>
			<?php
		}

		/**
		 * Renders read-only account and usage projections for the Status tab.
		 *
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @return void
		 */
		private static function render_status_account_usage( array $entitlement ): void {
			$ai_credit_usage_detail = is_array( $entitlement['ai_credit_usage_detail'] ?? null ) ? $entitlement['ai_credit_usage_detail'] : array();
			$credit_period = is_array( $ai_credit_usage_detail['period'] ?? null ) ? $ai_credit_usage_detail['period'] : array();
			$usage_limits = is_array( $entitlement['usage_limits'] ?? null ) ? $entitlement['usage_limits'] : array();
			$runtime_quota = is_array( $entitlement['hosted_runtime_quota'] ?? null ) ? $entitlement['hosted_runtime_quota'] : array();
			$links = is_array( $entitlement['links'] ?? null ) ? $entitlement['links'] : array();
			$credit_url = esc_url( (string) ( $links['ai_credit_ledger_url'] ?? ( $links['ai_credit_usage_url'] ?? '' ) ) );
			$rows = array();

			if ( '' !== (string) ( $entitlement['renews_at'] ?? '' ) ) {
				$rows[] = array( __( 'Renews', 'npcink-cloud-addon' ), self::format_datetime_value( (string) $entitlement['renews_at'] ) );
			}

			$period_start = (string) ( $credit_period['start_at'] ?? '' );
			$period_end = (string) ( $credit_period['end_at'] ?? '' );
			if ( '' !== $period_start || '' !== $period_end ) {
				$rows[] = array(
					__( 'AI credit period', 'npcink-cloud-addon' ),
					sprintf(
						/* translators: 1: credit period start, 2: credit period end. */
						__( '%1$s to %2$s', 'npcink-cloud-addon' ),
						self::format_datetime_value( $period_start, __( 'unavailable', 'npcink-cloud-addon' ) ),
						self::format_datetime_value( $period_end, __( 'unavailable', 'npcink-cloud-addon' ) )
					)
				);
			}

			foreach (
				array(
					'max_runs' => __( 'Run limit', 'npcink-cloud-addon' ),
					'max_tokens' => __( 'Token limit', 'npcink-cloud-addon' ),
					'max_sites' => __( 'Site limit', 'npcink-cloud-addon' ),
					'max_active_runs' => __( 'Active run limit', 'npcink-cloud-addon' ),
					'max_batch_items' => __( 'Batch item limit', 'npcink-cloud-addon' ),
				) as $key => $label
			) {
				$source = in_array( $key, array( 'max_active_runs', 'max_batch_items' ), true ) ? $runtime_quota : $usage_limits;
				$value = $source[ $key ] ?? 0;
				if ( is_numeric( $value ) && (float) $value > 0 ) {
					$rows[] = array( $label, self::format_entitlement_number( $value ) );
				}
			}

			$max_cost = $usage_limits['max_cost_usd'] ?? 0;
			if ( is_numeric( $max_cost ) && (float) $max_cost > 0 ) {
				$rows[] = array(
					__( 'Cost limit', 'npcink-cloud-addon' ),
					sprintf(
						/* translators: %s: USD cost limit. */
						__( '%s USD', 'npcink-cloud-addon' ),
						self::format_entitlement_number( $max_cost )
					)
				);
			}

			$execution_tiers = is_array( $runtime_quota['execution_tiers'] ?? null ) ? $runtime_quota['execution_tiers'] : array();
			if ( ! empty( $execution_tiers ) ) {
				$rows[] = array( __( 'Execution tiers', 'npcink-cloud-addon' ), implode( ', ', $execution_tiers ) );
			}

			if ( empty( $rows ) && '' === $credit_url ) {
				?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'No additional entitlement parameters were returned by Cloud.', 'npcink-cloud-addon' ); ?></p>
				<?php
				return;
			}
			?>
			<div class="npcink-cloud-section-heading">
				<h3><?php esc_html_e( 'Entitlement details', 'npcink-cloud-addon' ); ?></h3>
				<?php if ( '' !== $credit_url ) : ?>
					<a class="button button-secondary" href="<?php echo esc_url( $credit_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'View AI credit details in Cloud', 'npcink-cloud-addon' ); ?></a>
				<?php endif; ?>
			</div>
			<?php if ( ! empty( $rows ) ) : ?>
				<table class="widefat striped" style="max-width: 860px;">
					<tbody>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<th scope="row"><?php echo esc_html( (string) $row[0] ); ?></th>
								<td><?php echo esc_html( (string) $row[1] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
			<?php
		}

		/**
		 * Renders local monitoring upload problems for the service detail tab.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return void
		 */
		private static function render_status_monitoring_quality( array $monitoring ): void {
			if ( absint( $monitoring['buffer_count'] ?? 0 ) < 1 && '' === (string) ( $monitoring['last_upload_error'] ?? '' ) ) {
				return;
			}

			?>
			<h3><?php esc_html_e( 'Monitoring needs attention', 'npcink-cloud-addon' ); ?></h3>
			<?php
			self::render_monitoring_summary( $monitoring );
		}

		/**
		 * Renders monitoring status.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return void
		 */
		private static function render_monitoring_summary( array $monitoring ): void {
			$is_enabled = ! empty( $monitoring['enabled'] );
			$last_upload_error = (string) ( $monitoring['last_upload_error'] ?? '' );
			?>
				<table class="widefat striped" style="max-width: 860px;">
					<tbody>
						<tr>
							<th scope="row"><?php esc_html_e( 'Collection', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo ! empty( $monitoring['enabled'] ) ? esc_html__( 'enabled', 'npcink-cloud-addon' ) : esc_html__( 'disabled', 'npcink-cloud-addon' ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Buffered events', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( (string) absint( $monitoring['buffer_count'] ?? 0 ) ); ?></td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Last upload status', 'npcink-cloud-addon' ); ?></th>
							<td>
								<?php
								$has_upload_state = '' !== (string) ( $monitoring['last_uploaded_at'] ?? '' )
									|| '' !== (string) ( $monitoring['last_upload_error'] ?? '' );
								if ( ! $has_upload_state ) {
									echo esc_html__( 'never', 'npcink-cloud-addon' );
								} else {
									echo ! empty( $monitoring['last_upload_ok'] ) ? esc_html__( 'ok', 'npcink-cloud-addon' ) : esc_html__( 'failed', 'npcink-cloud-addon' );
								}
								?>
							</td>
						</tr>
						<?php if ( '' !== $last_upload_error ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Last upload error', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( $last_upload_error ); ?></td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
					<?php
			}

		/**
		 * Renders low-frequency connector details.
		 *
		 * @param array<string,mixed> $state Credential state.
		 * @return void
		 */
		private static function render_advanced_information( array $state ): void {
			$last_failure = (string) ( $state['last_verification_error'] ?? '' );
			if ( '' === $last_failure ) {
				return;
			}
			?>
			<h3><?php esc_html_e( 'Connection status', 'npcink-cloud-addon' ); ?></h3>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloud error classification', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( (string) $state['code'] ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last failure', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( $last_failure ); ?></td>
					</tr>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Formats a probe failure message.
		 *
		 * @param array<string,mixed> $probe Probe payload.
		 * @return string
		 */
		private static function format_probe_failure_message( array $probe ): string {
			if ( 'auth.site_inactive' === (string) ( $probe['auth_error_code'] ?? '' ) ) {
				return __( 'The site is connected, but Cloud service is not active yet. Activate this site in Npcink Cloud, then check activation again here.', 'npcink-cloud-addon' );
			}
			$messages = array();
			if ( empty( $probe['live_ok'] ) && ! empty( $probe['live_message'] ) ) {
				$messages[] = sprintf(
					/* translators: %s: liveness error. */
					__( 'Live check failed: %s', 'npcink-cloud-addon' ),
					self::redact_sensitive_message( (string) $probe['live_message'] )
				);
			}
			if ( empty( $probe['auth_ok'] ) && ! empty( $probe['auth_message'] ) ) {
				$messages[] = sprintf(
					/* translators: %s: signed verification error. */
					__( 'Signed verification failed: %s', 'npcink-cloud-addon' ),
					self::redact_sensitive_message( (string) $probe['auth_message'] )
				);
			}

			return '' !== implode( ' ', $messages )
				? sanitize_text_field( implode( ' ', $messages ) )
				: __( 'Cloud verification failed.', 'npcink-cloud-addon' );
		}

		/**
		 * Redacts connection credentials from operator-facing failure text.
		 *
		 * @param string $message Raw message.
		 * @return string
		 */
		private static function redact_sensitive_message( string $message ): string {
			$message = preg_replace( '/mak1_[A-Za-z0-9_-]+/', '[redacted]', $message );
			$message = preg_replace( '/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [redacted]', (string) $message );

			return sanitize_text_field( (string) $message );
		}

		/**
		 * Stores an admin notice for the redirected request.
		 *
		 * @param string $type Notice type.
		 * @param string $message Notice message.
		 * @return void
		 */
		private static function set_admin_notice( string $type, string $message ): void {
			set_transient(
				self::notice_transient_key(),
				array(
					'type' => sanitize_key( $type ),
					'message' => self::redact_sensitive_message( $message ),
				),
				60
			);
		}

		/**
		 * Stores feedback for one local permission row.
		 *
		 * @param string $type Notice type.
		 * @param string $message Notice message.
		 * @param string $permission Permission key.
		 * @return void
		 */
		private static function set_local_permission_feedback( string $type, string $message, string $permission ): void {
			set_transient(
				self::local_permission_feedback_transient_key(),
				array(
					'type' => sanitize_key( $type ),
					'message' => self::redact_sensitive_message( $message ),
					'permission' => sanitize_key( $permission ),
				),
				60
			);
		}

		/**
		 * Returns and clears local permission row feedback.
		 *
		 * @return array<string,string>
		 */
		private static function get_local_permission_feedback(): array {
			$feedback = get_transient( self::local_permission_feedback_transient_key() );
			delete_transient( self::local_permission_feedback_transient_key() );

			return is_array( $feedback ) ? $feedback : array();
		}

		/**
		 * Stores the latest manual readiness result for this administrator.
		 *
		 * @param array<string,mixed> $result Readiness result.
		 * @return void
		 */
		private static function set_manual_readiness_result( array $result ): void {
			set_transient(
				self::manual_readiness_transient_key(),
				$result,
				10 * MINUTE_IN_SECONDS
			);
		}

		/**
		 * Returns the latest manual readiness result for this administrator.
		 *
		 * @return array<string,mixed>
		 */
		private static function get_manual_readiness_result(): array {
			$result = get_transient( self::manual_readiness_transient_key() );

			return is_array( $result ) ? $result : array();
		}

		/**
		 * Renders and clears the saved admin notice.
		 *
		 * @return void
		 */
		private static function render_admin_notice(): void {
			$notice = get_transient( self::notice_transient_key() );
			delete_transient( self::notice_transient_key() );
			if ( ! is_array( $notice ) || empty( $notice['message'] ) ) {
				return;
			}

			$type = sanitize_key( (string) ( $notice['type'] ?? '' ) );
			if ( ! in_array( $type, array( 'success', 'warning', 'error' ), true ) ) {
				$type = 'error';
			}
			?>
			<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
				<p><?php echo esc_html( (string) $notice['message'] ); ?></p>
			</div>
			<?php
		}

		/**
		 * Returns a notice transient key for the current user.
		 *
		 * @return string
		 */
		private static function notice_transient_key(): string {
			return 'npcink_cloud_notice_' . absint( get_current_user_id() );
		}

		/**
		 * Returns a local permission feedback transient key for the current user.
		 *
		 * @return string
		 */
		private static function local_permission_feedback_transient_key(): string {
			return 'npcink_cloud_permission_feedback_' . absint( get_current_user_id() );
		}

		/**
		 * Returns a manual readiness result transient key for the current user.
		 *
		 * @return string
		 */
		private static function manual_readiness_transient_key(): string {
			return 'npcink_cloud_readiness_' . absint( get_current_user_id() );
		}

		/**
		 * Marks the first successful connection for one-time monitoring consent.
		 *
		 * @param bool $was_verified Whether the site was already verified before this request.
		 * @return void
		 */
		private static function maybe_prompt_for_monitoring_consent( bool $was_verified ): void {
			if ( $was_verified || ! Npcink_Cloud_Addon_Settings::is_verified() || Npcink_Cloud_Addon_Settings::is_monitoring_enabled() ) {
				return;
			}

			set_transient( self::monitoring_consent_prompt_transient_key(), true, DAY_IN_SECONDS );
		}

		/**
		 * Returns whether the one-time monitoring consent prompt is pending.
		 *
		 * @return bool
		 */
		private static function has_monitoring_consent_prompt(): bool {
			return (bool) get_transient( self::monitoring_consent_prompt_transient_key() );
		}

		/**
		 * Clears the one-time monitoring consent prompt.
		 *
		 * @return void
		 */
		private static function clear_monitoring_consent_prompt(): void {
			delete_transient( self::monitoring_consent_prompt_transient_key() );
		}

		/**
		 * Returns the one-time monitoring consent prompt key for the current administrator.
		 *
		 * @return string
		 */
		private static function monitoring_consent_prompt_transient_key(): string {
			return 'npcink_cloud_monitoring_consent_' . absint( get_current_user_id() );
		}

		/**
		 * Redirects back to the page.
		 *
		 * @param string $view Optional tab subview.
		 * @return void
		 */
		private static function redirect_to_page( string $tab = '', string $view = '' ): void {
			$url = self::page_url();
			if ( '' !== $tab ) {
				$url = add_query_arg( 'tab', sanitize_key( $tab ), $url );
			}
			if ( '' !== $view ) {
				$url = add_query_arg( 'view', sanitize_key( $view ), $url );
			}

			wp_safe_redirect( $url );
			exit;
		}

		/**
		 * Redirects to and identifies one local permission row.
		 *
		 * @param string $permission Permission key.
		 * @return void
		 */
		private static function redirect_to_local_permission( string $permission ): void {
			$url = add_query_arg(
				array(
					'tab' => 'permissions',
					'permission' => sanitize_key( $permission ),
				),
				self::page_url()
			);

			wp_safe_redirect( $url );
			exit;
		}

		/**
		 * Returns the active Cloud Addon page URL.
		 *
		 * @return string
		 */
		private static function page_url(): string {
			$parent = defined( 'NPCINK_TOOLBOX_VERSION' ) ? 'admin.php' : 'options-general.php';
			return admin_url( $parent . '?page=' . self::PAGE_SLUG );
		}

		/**
		 * Returns the base admin endpoint for GET forms targeting this page.
		 *
		 * @return string
		 */
		private static function page_form_action_url(): string {
			return admin_url( defined( 'NPCINK_TOOLBOX_VERSION' ) ? 'admin.php' : 'options-general.php' );
		}

		/**
		 * Formats entitlement availability for default display.
		 *
		 * @param array<string,mixed> $summary Entitlement summary.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return string
		 */
		private static function format_entitlement_availability( array $summary, bool $is_verified ): string {
			if ( ! $is_verified ) {
				return __( 'Not checked', 'npcink-cloud-addon' );
			}

			if ( ! empty( $summary['available'] ) ) {
				return __( 'available', 'npcink-cloud-addon' );
			}

			$state = sanitize_key( (string) ( $summary['state'] ?? '' ) );
			if ( 'not_refreshed' === $state ) {
				return __( 'not refreshed', 'npcink-cloud-addon' );
			}

			if ( 'not_configured' === $state ) {
				return __( 'not configured', 'npcink-cloud-addon' );
			}

			if ( 'unavailable' === $state ) {
				return __( 'read failed', 'npcink-cloud-addon' );
			}

			return __( 'unavailable', 'npcink-cloud-addon' );
		}

		/**
		 * Formats one compact entitlement state without duplicate fallback labels.
		 *
		 * @param array<string,mixed> $summary Entitlement summary.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return string
		 */
		private static function format_overview_entitlement( array $summary, bool $is_verified ): string {
			if ( ! $is_verified ) {
				return __( 'Not checked', 'npcink-cloud-addon' );
			}

			$state = sanitize_key( (string) ( $summary['state'] ?? '' ) );
			if ( 'not_refreshed' === $state ) {
				return __( 'Loading plan and entitlement…', 'npcink-cloud-addon' );
			}

			if ( empty( $summary['available'] ) ) {
				return __( 'Plan and entitlement are temporarily unavailable.', 'npcink-cloud-addon' );
			}

			$package_label = self::format_overview_package_label( $summary );
			if ( '' === $package_label ) {
				return __( 'Available', 'npcink-cloud-addon' );
			}

			return $package_label . ' · ' . __( 'available', 'npcink-cloud-addon' );
		}

		/**
		 * Maps known package tiers to product copy while preserving unknown Cloud labels.
		 *
		 * @param array<string,mixed> $summary Entitlement summary.
		 * @return string
		 */
		private static function format_overview_package_label( array $summary ): string {
			$labels = array(
				'free' => __( 'Free plan', 'npcink-cloud-addon' ),
				'pro'  => __( 'Pro plan', 'npcink-cloud-addon' ),
			);
			$package_label = trim( (string) ( $summary['package_label'] ?? '' ) );
			$label_key = sanitize_key( $package_label );
			if ( '' !== $package_label ) {
				return $labels[ $label_key ] ?? $package_label;
			}

			$tier = sanitize_key( (string) ( $summary['package_tier'] ?? '' ) );

			return $labels[ $tier ] ?? '';
		}

		/**
		 * Builds the small set of frequently used entitlement metrics for Overview.
		 *
		 * @param array<string,mixed> $summary Entitlement summary.
		 * @return array<string,array<string,mixed>>
		 */
		private static function get_overview_entitlement_metrics( array $summary ): array {
			$credits = array(
				'available' => false,
				'label' => '',
				'value_label' => '',
				'status_label' => '',
				'percent' => null,
				'tooltip' => '',
			);
			$ai_credit_usage_detail = is_array( $summary['ai_credit_usage_detail'] ?? null ) ? $summary['ai_credit_usage_detail'] : array();
			$credit_summary = is_array( $ai_credit_usage_detail['summary'] ?? null ) ? $ai_credit_usage_detail['summary'] : array();
			$remaining = $credit_summary['remaining'] ?? null;
			if ( ! empty( $ai_credit_usage_detail['available'] ) && is_numeric( $remaining ) ) {
				$remaining_label = self::format_credit_amount( $remaining );
				$limit = is_numeric( $credit_summary['limit'] ?? null ) ? (float) $credit_summary['limit'] : 0.0;
				$credits['available'] = true;
				$credits['value_label'] = $remaining_label;
				if ( $limit > 0 ) {
					$limit_label = self::format_credit_amount( $limit );
					$used_label = self::format_credit_amount( is_numeric( $credit_summary['used'] ?? null ) ? $credit_summary['used'] : max( 0, $limit - (float) $remaining ) );
					$percent = (int) round( max( 0, min( 100, ( (float) $remaining / $limit ) * 100 ) ) );
					$credits['value_label'] = $remaining_label . ' / ' . $limit_label;
					$credits['status_label'] = sprintf(
						/* translators: %d: remaining percentage. */
						__( '%d%% remaining', 'npcink-cloud-addon' ),
						$percent
					);
					$credits['label'] = sprintf(
						/* translators: 1: remaining credits, 2: credit limit, 3: remaining percentage. */
						__( '%1$s / %2$s · %3$d%% remaining', 'npcink-cloud-addon' ),
						$remaining_label,
						$limit_label,
						$percent
					);
					$credits['percent'] = $percent;
					$credits['tooltip'] = sprintf(
						/* translators: 1: used credits, 2: remaining credits, 3: credit limit. */
						__( 'Used %1$s AI credits; remaining %2$s AI credits; limit %3$s AI credits.', 'npcink-cloud-addon' ),
						$used_label,
						$remaining_label,
						$limit_label
					);
				} else {
					$credits['label'] = sprintf(
						/* translators: %s: remaining credits. */
						__( '%s remaining', 'npcink-cloud-addon' ),
						$remaining_label
					);
				}
			}

			$runtime = array(
				'available' => false,
				'label' => '',
			);
			$runtime_detail = is_array( $summary['pro_cloud_runtime'] ?? null ) ? $summary['pro_cloud_runtime'] : array();
			$runtime_limit = absint( $runtime_detail['max_nightly_inspection_runs_per_period'] ?? 0 );
			if ( ! empty( $runtime_detail['reported'] ) && $runtime_limit > 0 && is_numeric( $runtime_detail['remaining_nightly_inspection_runs'] ?? null ) ) {
				$runtime_remaining = min( $runtime_limit, absint( $runtime_detail['remaining_nightly_inspection_runs'] ) );
				$runtime['available'] = true;
				$runtime['label'] = sprintf(
					/* translators: 1: remaining runtime runs, 2: runtime run limit. */
					__( '%1$d of %2$d runs remaining', 'npcink-cloud-addon' ),
					$runtime_remaining,
					$runtime_limit
				);
			}

			return array(
				'credits' => $credits,
				'runtime' => $runtime,
			);
		}

		/**
		 * Builds a bounded display projection from Cloud-owned Site Knowledge quota truth.
		 *
		 * @param array<string,mixed> $summary Normalized Cloud status summary.
		 * @return array<string,mixed>
		 */
		private static function get_site_knowledge_usage_projection( array $summary ): array {
			return Npcink_Cloud_Site_Knowledge_Admin_Projection::build( $summary );
		}

		/**
		 * Formats monitoring state for the compact default panel.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return string
		 */
		private static function format_monitoring_overview( array $monitoring ): string {
			$state = ! empty( $monitoring['enabled'] )
				? __( 'enabled', 'npcink-cloud-addon' )
				: __( 'disabled', 'npcink-cloud-addon' );
			$buffer_count = absint( $monitoring['buffer_count'] ?? 0 );

			return sprintf(
				/* translators: 1: monitoring state, 2: buffered event count. */
				__( '%1$s, %2$d buffered', 'npcink-cloud-addon' ),
				$state,
				$buffer_count
			);
		}

		/**
		 * Formats Site Knowledge bridge state for compact status rows.
		 *
		 * @param array<string,mixed> $site_knowledge Site Knowledge bridge status.
		 * @return string
		 */
		private static function format_site_knowledge_overview( array $site_knowledge ): string {
			$status = sanitize_key( (string) ( $site_knowledge['status'] ?? '' ) );
			if ( '' === $status ) {
				$status = ! empty( $site_knowledge['verified'] ) ? 'idle' : 'unverified';
			}
			$buffer_count = absint( $site_knowledge['buffer_count'] ?? 0 );

			if ( $buffer_count > 0 && in_array( $status, array( 'pending', 'queued' ), true ) ) {
				return sprintf(
					/* translators: %d: buffered public change count. */
					__( '%d public changes awaiting delivery', 'npcink-cloud-addon' ),
					$buffer_count
				);
			}

			return sprintf(
				/* translators: 1: bridge status, 2: buffered public change count. */
				__( '%1$s, %2$d public changes buffered', 'npcink-cloud-addon' ),
				self::format_site_knowledge_status_label( $status ),
				$buffer_count
			);
		}

		/**
		 * Formats a Site Knowledge bridge status for the local admin surface.
		 *
		 * @param string $status Raw bridge status.
		 * @return string
		 */
		private static function format_site_knowledge_status_label( string $status ): string {
			$labels = array(
				'idle'           => __( 'idle', 'npcink-cloud-addon' ),
				'not_configured' => __( 'not configured', 'npcink-cloud-addon' ),
				'unverified'     => __( 'unverified', 'npcink-cloud-addon' ),
				'disabled'       => __( 'disabled', 'npcink-cloud-addon' ),
				'error'          => __( 'error', 'npcink-cloud-addon' ),
				'pending'        => __( 'pending', 'npcink-cloud-addon' ),
				'queued'         => __( 'queued', 'npcink-cloud-addon' ),
				'ok'             => __( 'ok', 'npcink-cloud-addon' ),
			);

			return $labels[ $status ] ?? self::format_empty( $status );
		}

		/**
		 * Formats a setting value with a field-specific fallback.
		 *
		 * @param string $value Value.
		 * @param string $fallback Fallback text.
		 * @return string
		 */
		private static function format_setting_value( string $value, string $fallback ): string {
			return '' !== $value ? $value : $fallback;
		}

		/**
		 * Formats an empty display value.
		 *
		 * @param string $value Value.
		 * @return string
		 */
		private static function format_empty( string $value ): string {
			return '' !== $value ? $value : __( 'unavailable', 'npcink-cloud-addon' );
		}

		/**
		 * Formats a Cloud credit amount for summary display.
		 *
		 * @param mixed $value Credit amount.
		 * @return string
		 */
		private static function format_credit_amount( $value ): string {
			if ( null === $value || '' === $value ) {
				return __( 'unavailable', 'npcink-cloud-addon' );
			}

			$amount = round( is_numeric( $value ) ? (float) $value : 0.0, 2 );
			$decimals = floor( $amount ) === $amount
				? 0
				: ( floor( $amount * 10 ) === $amount * 10 ? 1 : 2 );
			$formatted = function_exists( 'number_format_i18n' )
				? number_format_i18n( $amount, $decimals )
				: number_format( $amount, $decimals, '.', ',' );

			return $formatted;
		}

		/**
		 * Formats one positive entitlement limit without adding local units or truth.
		 *
		 * @param mixed $value Numeric entitlement projection.
		 * @return string
		 */
		private static function format_entitlement_number( $value ): string {
			$number = is_numeric( $value ) ? (float) $value : 0.0;
			if ( function_exists( 'number_format_i18n' ) ) {
				return number_format_i18n( $number, floor( $number ) === $number ? 0 : 2 );
			}

			return number_format( $number, floor( $number ) === $number ? 0 : 2, '.', ',' );
		}

		/**
		 * Formats a stored UTC datetime for the site's WordPress timezone.
		 *
		 * @param string $value    UTC datetime string.
		 * @param string $fallback Fallback text.
		 * @return string
		 */
		private static function format_datetime_value( string $value, string $fallback = '' ): string {
			$value = trim( $value );
			if ( '' === $value ) {
				return '' !== $fallback ? $fallback : __( 'unavailable', 'npcink-cloud-addon' );
			}

			$has_timezone = (bool) preg_match( '/(?:Z|UTC|[+-]\d{2}:?\d{2})$/i', $value );
			$timestamp    = strtotime( $has_timezone ? $value : $value . ' UTC' );
			if ( false === $timestamp ) {
				return $value;
			}

			if ( function_exists( 'wp_date' ) ) {
				return wp_date( self::DATETIME_DISPLAY_FORMAT, $timestamp );
			}

			if ( function_exists( 'date_i18n' ) ) {
				return date_i18n( self::DATETIME_DISPLAY_FORMAT, $timestamp, true );
			}

			return gmdate( self::DATETIME_DISPLAY_FORMAT, $timestamp );
		}

		/** Formats a stored UTC datetime without low-value seconds for summary UI. */
		private static function format_compact_datetime_value( string $value ): string {
			$value = trim( $value );
			if ( '' === $value ) {
				return self::format_datetime_value( $value );
			}
			$has_timezone = (bool) preg_match( '/(?:Z|UTC|[+-]\d{2}:?\d{2})$/i', $value );
			$timestamp = strtotime( $has_timezone ? $value : $value . ' UTC' );
			if ( false === $timestamp ) {
				return $value;
			}

			return function_exists( 'wp_date' ) ? wp_date( 'Y-m-d H:i', $timestamp ) : gmdate( 'Y-m-d H:i', $timestamp );
		}

		/** Reads the acquisition timestamp from current token locks and legacy numeric locks. */
		private static function media_recognition_plan_lock_started_at( $lock_value ): int {
			if ( ! preg_match( '/^(\d+)/', (string) $lock_value, $matches ) ) {
				return 0;
			}

			return absint( $matches[1] );
		}

		/** Releases only this callback's lock and recovers an interrupted active plan. */
		private static function release_media_recognition_plan_lock( string $lock_token, bool $schedule_recovery ): void {
			if ( $lock_token !== (string) get_option( self::MEDIA_PLAN_LOCK, '' ) ) {
				return;
			}

			delete_option( self::MEDIA_PLAN_LOCK );
			if ( $schedule_recovery && ! empty( self::get_media_recognition_plan()['active'] ) ) {
				self::schedule_media_recognition_plan( 60 );
			}
		}

	}
}
