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
		private const ACTION_REFRESH_SITE_KNOWLEDGE_STATUS = 'npcink_cloud_addon_refresh_site_knowledge_status';
		private const ACTION_MANAGE_SITE_KNOWLEDGE_INDEX = 'npcink_cloud_addon_manage_site_knowledge_index';
		private const ACTION_RETRY_RUNTIME_RUN = 'npcink_cloud_addon_retry_runtime_run';
		private const ACTION_RUN_MANUAL_READINESS_TEST = 'npcink_cloud_addon_run_manual_readiness_test';
		private const ACTION_REFRESH_ENTITLEMENT = 'npcink_cloud_addon_refresh_entitlement';
		private const DATETIME_DISPLAY_FORMAT = 'Y-m-d H:i:s';
		private const AUTH_STATE_TTL_SECONDS = 600;

		/**
		 * Registers admin hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
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
			add_action( 'wp_ajax_' . self::ACTION_REFRESH_SITE_KNOWLEDGE_STATUS, array( __CLASS__, 'handle_refresh_site_knowledge_status' ) );
			add_action( 'admin_post_' . self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX, array( __CLASS__, 'handle_manage_site_knowledge_index' ) );
			add_action( 'admin_post_' . self::ACTION_RETRY_RUNTIME_RUN, array( __CLASS__, 'handle_retry_runtime_run' ) );
			add_action( 'admin_post_' . self::ACTION_RUN_MANUAL_READINESS_TEST, array( __CLASS__, 'handle_run_manual_readiness_test' ) );
			add_action( 'wp_ajax_' . self::ACTION_REFRESH_ENTITLEMENT, array( __CLASS__, 'handle_refresh_entitlement' ) );
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
			if ( 'site_knowledge_delivery_enabled' === $permission && ! $enabled ) {
				$settings['site_knowledge_generation_reference_enabled'] = false;
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
			 * Handles a bounded Cloud-owned retry request for a known runtime run.
			 *
			 * @return void
			 */
			public static function handle_retry_runtime_run(): void {
				if ( ! current_user_can( 'manage_options' ) ) {
					wp_die( esc_html__( 'You do not have permission to manage Npcink Cloud settings.', 'npcink-cloud-addon' ) );
				}

				check_admin_referer( self::ACTION_RETRY_RUNTIME_RUN );

				if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
					self::set_admin_notice( 'error', __( 'Cloud Addon settings are not verified.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'advanced', 'runs' );
				}

				$run_id = isset( $_POST['runtime_run_id'] ) ? Npcink_Cloud_Runtime_Runs_Presenter::normalize_run_id( sanitize_text_field( wp_unslash( $_POST['runtime_run_id'] ) ) ) : '';
				if ( '' === $run_id ) {
					self::set_admin_notice( 'error', __( 'Enter a Cloud run ID before requesting retry.', 'npcink-cloud-addon' ) );
					self::redirect_to_page( 'advanced', 'runs' );
				}

				$settings = Npcink_Cloud_Addon_Settings::get_settings();
				$client = new Npcink_Cloud_Runtime_Client( $settings );
				$result = $client->retry_run(
					$run_id,
					array(
						'retry_context' => array(
							'source'                  => 'cloud_addon_diagnostics_tab',
							'operator_requested'      => true,
							'direct_wordpress_write'  => false,
							'local_queue_created'     => false,
							'local_scheduler_created' => false,
						),
					),
					'trace_cloud_addon_runtime_retry_' . wp_generate_uuid4(),
					'cloud-addon-runtime-retry-' . substr( md5( $run_id . '|' . microtime( true ) ), 0, 24 )
				);
				if ( is_wp_error( $result ) ) {
					self::set_admin_notice( 'error', $result->get_error_message() );
					self::redirect_to_page( 'advanced', 'runs' );
				}

				$data = is_array( $result['data'] ?? null ) ? $result['data'] : ( is_array( $result ) ? $result : array() );
				$retry_run = is_array( $data['retry_run'] ?? null ) ? $data['retry_run'] : array();
				$new_run_id = Npcink_Cloud_Runtime_Runs_Presenter::normalize_run_id( (string) ( $retry_run['run_id'] ?? $data['run_id'] ?? '' ) );
				self::set_admin_notice(
					'success',
					'' !== $new_run_id
						? sprintf(
							/* translators: %s: Cloud retry run id. */
							__( 'Cloud retry requested. New run ID: %s. Cloud remains the run-state owner.', 'npcink-cloud-addon' ),
							$new_run_id
						)
						: __( 'Cloud retry requested. Cloud remains the run-state owner.', 'npcink-cloud-addon' )
				);

				$url = add_query_arg(
					array_filter(
						array(
							'tab'              => 'advanced',
							'view'             => 'runs',
							'runtime_view'     => '' !== $new_run_id ? 'status' : '',
							'runtime_run_id'   => $new_run_id,
						)
					),
					self::page_url()
				);
				wp_safe_redirect( $url );
				exit;
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
				return array(
					'permissions'    => __( 'Overview', 'npcink-cloud-addon' ),
					'site_knowledge' => __( 'Site Knowledge', 'npcink-cloud-addon' ),
					'advanced'       => __( 'Advanced and troubleshooting', 'npcink-cloud-addon' ),
				);
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
		 * Builds a Runtime runs troubleshooting URL.
		 *
		 * @param string $view Runtime view.
		 * @param string $run_id Optional run id.
		 * @return string
		 */
		private static function runtime_tab_url( string $view, string $run_id ): string {
			$args = array(
				'page'         => self::PAGE_SLUG,
				'tab'          => 'advanced',
				'view'         => 'runs',
				'runtime_view' => sanitize_key( $view ),
			);
			if ( '' !== $run_id ) {
				$args['runtime_run_id'] = Npcink_Cloud_Runtime_Runs_Presenter::normalize_run_id( $run_id );
			}

			return add_query_arg( $args, self::page_url() );
		}

		/**
		 * Returns the requested runtime view.
		 *
		 * @return string
		 */
		private static function runtime_view_from_request(): string {
			$raw = filter_input( INPUT_GET, 'runtime_view', FILTER_UNSAFE_RAW );
			$view = is_string( $raw ) ? sanitize_key( wp_unslash( $raw ) ) : '';

			return in_array( $view, array( 'recent', 'status', 'result' ), true ) ? $view : '';
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
			if ( '' !== self::runtime_view_from_request() ) {
				return 'runs';
			}

			$raw = filter_input( INPUT_GET, 'view', FILTER_UNSAFE_RAW );
			$view = is_string( $raw ) ? sanitize_key( wp_unslash( $raw ) ) : '';

			return in_array( $view, array( 'service', 'checks', 'runs', 'connection' ), true ) ? $view : 'service';
		}

		/**
		 * Returns the requested Cloud run id.
		 *
		 * @return string
		 */
		private static function runtime_run_id_from_request(): string {
			$raw = filter_input( INPUT_GET, 'runtime_run_id', FILTER_UNSAFE_RAW );

			return is_string( $raw ) ? Npcink_Cloud_Runtime_Runs_Presenter::normalize_run_id( wp_unslash( $raw ) ) : '';
		}

		/**
		 * Formats an optional runtime retention-day projection.
		 *
		 * @param array<string,mixed> $runtime Runtime entitlement projection.
		 * @param string              $key Projection key.
		 * @return string
		 */
		private static function format_runtime_days_projection( array $runtime, string $key ): string {
			if ( ! array_key_exists( $key, $runtime ) || ! is_numeric( $runtime[ $key ] ) ) {
				return self::format_empty( '' );
			}

			return sprintf(
				/* translators: %d: retention days. */
				__( '%d days', 'npcink-cloud-addon' ),
				absint( $runtime[ $key ] )
			);
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
			$display_base_url = $is_configured
				? (string) $settings['base_url']
				: Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings );
			$is_custom_base_url = untrailingslashit( $display_base_url ) !== untrailingslashit( Npcink_Cloud_Addon_Settings::get_default_base_url() );
			$show_connection_meta = ! $is_verified || $is_custom_base_url;
			?>
			<section class="npcink-cloud-summary">
				<div class="npcink-cloud-summary__header">
					<div>
						<p class="npcink-cloud-summary__state">
							<span class="npcink-cloud-badge npcink-cloud-badge--<?php echo esc_attr( $severity ); ?>"><?php echo esc_html( (string) $state['label'] ); ?></span>
						</p>
						<p class="npcink-cloud-summary__message"><?php echo esc_html( (string) $state['message'] ); ?></p>
					</div>
					<?php if ( $is_configured ) : ?>
						<?php self::render_connection_actions( $settings, $is_verified, 'warning' === (string) $service_health['severity'] ); ?>
					<?php endif; ?>
				</div>
				<?php if ( $is_verified ) : ?>
					<div class="npcink-cloud-summary__grid npcink-cloud-summary__grid--health">
						<div class="npcink-cloud-summary__item">
							<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Credentials', 'npcink-cloud-addon' ); ?></span>
							<span class="npcink-cloud-summary__value"><?php esc_html_e( 'Last verification succeeded', 'npcink-cloud-addon' ); ?></span>
						</div>
						<div class="npcink-cloud-summary__item">
							<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Current service', 'npcink-cloud-addon' ); ?></span>
							<span class="npcink-cloud-summary__value npcink-cloud-summary__value--<?php echo esc_attr( (string) $service_health['severity'] ); ?>"><?php echo esc_html( (string) $service_health['label'] ); ?></span>
							<span class="description"><?php echo esc_html( (string) $service_health['message'] ); ?></span>
						</div>
					</div>
				<?php endif; ?>
				<?php if ( $is_configured && $show_connection_meta ) : ?>
					<div class="npcink-cloud-summary__grid">
						<?php if ( ! $is_verified || $is_custom_base_url ) : ?>
							<div class="npcink-cloud-summary__item">
								<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Cloud Base URL', 'npcink-cloud-addon' ); ?></span>
								<span class="npcink-cloud-summary__value"><?php echo esc_html( self::format_setting_value( $display_base_url, __( 'Not set', 'npcink-cloud-addon' ) ) ); ?></span>
							</div>
						<?php endif; ?>
						<?php if ( ! $is_verified ) : ?>
							<div class="npcink-cloud-summary__item">
								<span class="npcink-cloud-summary__label"><?php esc_html_e( 'Last verified', 'npcink-cloud-addon' ); ?></span>
								<span class="npcink-cloud-summary__value"><?php echo esc_html( self::format_datetime_value( (string) $settings['verified_at'], __( 'Never', 'npcink-cloud-addon' ) ) ); ?></span>
							</div>
						<?php endif; ?>
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
					'label'       => __( 'Site Knowledge delivery', 'npcink-cloud-addon' ),
					'description' => __( 'Send public content changes to Cloud Site Knowledge.', 'npcink-cloud-addon' ),
				),
				'site_knowledge_generation_reference_enabled' => array(
					'label'       => __( 'Reference site content during generation', 'npcink-cloud-addon' ),
					'description' => __( 'Use indexed public articles as generation context.', 'npcink-cloud-addon' ),
				),
				'monitoring_enabled' => array(
					'label'       => __( 'Usage and error diagnostics', 'npcink-cloud-addon' ),
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
			<section class="npcink-cloud-local-permissions" aria-labelledby="npcink-cloud-local-permissions-title">
				<div class="npcink-cloud-local-permissions__header">
					<h2 id="npcink-cloud-local-permissions-title"><?php esc_html_e( 'Local permissions', 'npcink-cloud-addon' ); ?></h2>
				</div>
				<div class="npcink-cloud-local-permissions__list">
					<?php $definitions = self::get_local_permission_definitions(); ?>
					<?php foreach ( array( 'wordpress_ai_connector_enabled', 'site_knowledge_delivery_enabled' ) as $permission ) : ?>
						<?php self::render_local_permission_switch( $permission, $definitions[ $permission ], ! empty( $settings[ $permission ] ), $feedback ); ?>
					<?php endforeach; ?>
					<?php if ( ! empty( $settings['site_knowledge_delivery_enabled'] ) ) : ?>
						<?php self::render_local_permission_switch( 'site_knowledge_generation_reference_enabled', $definitions['site_knowledge_generation_reference_enabled'], ! empty( $settings['site_knowledge_generation_reference_enabled'] ), $feedback ); ?>
					<?php endif; ?>
				</div>
				<details class="npcink-cloud-advanced-detail npcink-cloud-local-permissions__more"<?php echo 'monitoring_enabled' === (string) ( $feedback['permission'] ?? '' ) ? ' open' : ''; ?>>
					<summary><?php esc_html_e( 'More local permissions', 'npcink-cloud-addon' ); ?></summary>
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
			<h3><?php esc_html_e( 'Cloud connection actions', 'npcink-cloud-addon' ); ?></h3>
			<p class="description"><?php esc_html_e( 'Use Cloud for account-level connection changes. Local disconnect only clears this WordPress site; it does not release the site in Cloud or start the cross-account cooldown.', 'npcink-cloud-addon' ); ?></p>
			<div class="npcink-cloud-summary__actions npcink-cloud-summary__actions--start">
				<?php if ( Npcink_Cloud_Addon_Settings::is_verified() ) : ?>
					<?php self::render_reverify_form( $settings ); ?>
				<?php endif; ?>
					<?php self::render_authorization_form( __( 'Change connection in Cloud', 'npcink-cloud-addon' ), 'button button-secondary' ); ?>
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
				<button type="submit" class="button button-secondary"><?php echo esc_html( '' !== $label ? $label : __( 'Re-verify and refresh', 'npcink-cloud-addon' ) ); ?></button>
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
					onclick="return confirm('<?php echo esc_js( __( 'Disconnect this site locally? Stored Cloud credentials and addon-owned buffers will be cleared from this WordPress site only. This does not release the site in Cloud or start the cross-account cooldown. Use the Cloud portal to remove the site or change its account.', 'npcink-cloud-addon' ) ); ?>');"
				>
					<?php esc_html_e( 'Disconnect locally', 'npcink-cloud-addon' ); ?>
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
								<p class="description"><?php esc_html_e( 'Leave blank to keep the stored connection key. Use Change connection in Cloud for normal account changes.', 'npcink-cloud-addon' ); ?></p>
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
			$monitoring_needs_attention = absint( $monitoring['buffer_count'] ?? 0 ) > 0
				|| '' !== (string) ( $monitoring['last_upload_error'] ?? '' );
			$site_knowledge_needs_attention = absint( $site_knowledge['buffer_count'] ?? 0 ) > 0
				|| '' !== (string) ( $site_knowledge['last_delivery_error'] ?? '' );
			$entitlement_state = sanitize_key( (string) ( $entitlement['state'] ?? '' ) );
			$show_entitlement_retry = $is_verified && in_array( $entitlement_state, array( 'unavailable', 'refreshing' ), true );
			$entitlement_metrics = self::get_overview_entitlement_metrics( $entitlement );
			$credit_metric = is_array( $entitlement_metrics['credits'] ?? null ) ? $entitlement_metrics['credits'] : array();
			$runtime_metric = is_array( $entitlement_metrics['runtime'] ?? null ) ? $entitlement_metrics['runtime'] : array();
			$site_knowledge_delivery_enabled = ! empty( $site_knowledge['delivery_enabled'] );
			$site_knowledge_usage = $site_knowledge_delivery_enabled
				? self::get_site_knowledge_usage_projection( Npcink_Cloud_Site_Knowledge_Runtime_Bridge::get_cached_status_summary() )
				: self::get_site_knowledge_usage_projection( array() );
			$show_site_knowledge_retry = in_array( (string) ( $site_knowledge_usage['state'] ?? '' ), array( 'unavailable', 'refreshing' ), true );
			?>
			<section class="npcink-cloud-section npcink-cloud-tab-panel">
				<h2 class="screen-reader-text"><?php esc_html_e( 'Overview', 'npcink-cloud-addon' ); ?></h2>
				<div class="npcink-cloud-section-heading">
					<h3><?php esc_html_e( 'Service summary', 'npcink-cloud-addon' ); ?></h3>
					<a class="button button-secondary" href="<?php echo esc_url( self::tab_view_url( 'advanced', 'service' ) ); ?>"><?php esc_html_e( 'View service details', 'npcink-cloud-addon' ); ?></a>
				</div>
				<table class="widefat striped npcink-cloud-overview-status">
				<tbody>
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
					'runs'       => __( 'Runtime runs', 'npcink-cloud-addon' ),
					'connection' => __( 'Connection recovery', 'npcink-cloud-addon' ),
				),
				'advanced',
				__( 'Advanced and troubleshooting sections', 'npcink-cloud-addon' )
			);

			if ( 'runs' === $active_view ) {
				self::render_runtime_runs( $settings, $state, $entitlement, $is_verified );
				return;
			}

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
		 * Renders Cloud-owned runtime run detail and recovery entry.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Entitlement summary.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_runtime_runs( array $settings, array $state, array $entitlement, bool $is_verified ): void {
			if ( ! $is_verified ) {
				self::render_cloud_authorization_panel( $settings, $state );
				return;
			}

			$runtime = ! empty( $entitlement['available'] ) && is_array( $entitlement['pro_cloud_runtime'] ?? null ) ? $entitlement['pro_cloud_runtime'] : array();
			$run_detail_url = untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal/runs?feature=nightly_site_inspection';
			?>
			<div class="npcink-cloud-section-heading">
				<h3><?php esc_html_e( 'Cloud runtime runs', 'npcink-cloud-addon' ); ?></h3>
				<div class="npcink-cloud-summary__actions">
					<?php if ( ! empty( $runtime['reported'] ) ) : ?>
						<a class="button button-secondary" href="<?php echo esc_url( self::runtime_tab_url( 'recent', '' ) ); ?>"><?php esc_html_e( 'Load recent runs', 'npcink-cloud-addon' ); ?></a>
					<?php endif; ?>
					<a class="button button-secondary" href="<?php echo esc_url( $run_detail_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud run detail', 'npcink-cloud-addon' ); ?></a>
				</div>
			</div>
			<?php
			if ( empty( $runtime['reported'] ) ) {
				?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'Cloud did not return Runtime Runs entitlement for this site yet.', 'npcink-cloud-addon' ); ?></p>
				<p class="description"><?php esc_html_e( 'Run status, result reads, and retry controls appear after Cloud reports the runtime entitlement.', 'npcink-cloud-addon' ); ?></p>
				<?php
				return;
			}

			$view = self::runtime_view_from_request();
			$run_id = self::runtime_run_id_from_request();
			$client = new Npcink_Cloud_Runtime_Client( $settings );
			?>
			<?php self::render_pro_cloud_runtime_summary( $entitlement ); ?>

			<details class="npcink-cloud-advanced-detail">
				<summary><?php esc_html_e( 'Inspect by run ID', 'npcink-cloud-addon' ); ?></summary>
				<div class="npcink-cloud-advanced-detail__body">
					<form method="get" action="<?php echo esc_url( self::page_form_action_url() ); ?>" style="max-width: 860px; margin: 8px 0;">
						<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG ); ?>" />
						<input type="hidden" name="tab" value="advanced" />
						<input type="hidden" name="view" value="runs" />
						<label for="npcink-cloud-runtime-run-id"><strong><?php esc_html_e( 'Cloud run ID', 'npcink-cloud-addon' ); ?></strong></label>
						<input id="npcink-cloud-runtime-run-id" class="regular-text code" type="text" name="runtime_run_id" value="<?php echo esc_attr( $run_id ); ?>" placeholder="run_..." />
						<button class="button" type="submit" name="runtime_view" value="status"><?php esc_html_e( 'Read status', 'npcink-cloud-addon' ); ?></button>
						<button class="button" type="submit" name="runtime_view" value="result"><?php esc_html_e( 'Read result', 'npcink-cloud-addon' ); ?></button>
					</form>
				</div>
			</details>

			<?php
			if ( 'recent' === $view ) {
				self::render_runtime_recent_runs( $client );
			}
			if ( in_array( $view, array( 'status', 'result' ), true ) && '' !== $run_id ) {
				self::render_runtime_run_detail( $client, $run_id, $view );
			}
		}

		/**
		 * Renders recent Nightly Inspection runs from Cloud.
		 *
		 * @param Npcink_Cloud_Runtime_Client $client Runtime client.
		 * @return void
		 */
		private static function render_runtime_recent_runs( Npcink_Cloud_Runtime_Client $client ): void {
			$response = $client->get_recent_nightly_inspection_runs( 5, 'trace_cloud_addon_runtime_recent_' . wp_generate_uuid4() );
			if ( is_wp_error( $response ) ) {
				?>
				<p class="npcink-cloud-empty"><?php echo esc_html( $response->get_error_message() ); ?></p>
				<?php
				return;
			}

			$runs = Npcink_Cloud_Runtime_Runs_Presenter::recent_rows( is_array( $response ) ? $response : array() );
			?>
			<h3><?php esc_html_e( 'Recent runs', 'npcink-cloud-addon' ); ?></h3>
			<?php if ( empty( $runs ) ) : ?>
				<p class="npcink-cloud-empty"><?php esc_html_e( 'Cloud returned no recent Nightly Inspection runs for this site.', 'npcink-cloud-addon' ); ?></p>
				<?php return; ?>
			<?php endif; ?>
			<table class="widefat striped" style="max-width: 980px;">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Run', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Status', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Result', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Updated', 'npcink-cloud-addon' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Action', 'npcink-cloud-addon' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( array_slice( $runs, 0, 5 ) as $run ) : ?>
						<?php $run_id = (string) ( $run['run_id'] ?? '' ); ?>
						<tr>
							<th scope="row"><code><?php echo esc_html( self::format_empty( $run_id ) ); ?></code></th>
							<td><?php echo esc_html( (string) ( $run['status_label'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( (string) ( $run['result_status_label'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( self::format_datetime_value( (string) ( $run['updated_at'] ?? '' ) ) ); ?></td>
							<td>
								<?php if ( '' !== $run_id ) : ?>
									<a href="<?php echo esc_url( self::runtime_tab_url( 'status', $run_id ) ); ?>"><?php esc_html_e( 'Inspect', 'npcink-cloud-addon' ); ?></a>
								<?php else : ?>
									<?php echo esc_html( self::format_empty( '' ) ); ?>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Renders status or result detail for one Cloud run.
		 *
		 * @param Npcink_Cloud_Runtime_Client $client Runtime client.
		 * @param string                      $run_id Cloud run id.
		 * @param string                      $view Requested view.
		 * @return void
		 */
		private static function render_runtime_run_detail( Npcink_Cloud_Runtime_Client $client, string $run_id, string $view ): void {
			$response = 'result' === $view
				? $client->get_run_result( $run_id, 'trace_cloud_addon_runtime_result_' . wp_generate_uuid4() )
				: $client->get_run( $run_id, 'trace_cloud_addon_runtime_status_' . wp_generate_uuid4() );
			?>
			<h3><?php echo esc_html( 'result' === $view ? __( 'Run result', 'npcink-cloud-addon' ) : __( 'Run status', 'npcink-cloud-addon' ) ); ?></h3>
			<?php
			if ( is_wp_error( $response ) ) {
				?>
				<p class="npcink-cloud-empty"><?php echo esc_html( $response->get_error_message() ); ?></p>
				<?php
				return;
			}

			$detail = Npcink_Cloud_Runtime_Runs_Presenter::detail( is_array( $response ) ? $response : array() );
			$error_code = (string) ( $detail['error_code'] ?? '' );
			?>
			<table class="widefat striped" style="max-width: 980px;">
				<tbody>
					<?php self::render_diagnostic_row( __( 'Run ID', 'npcink-cloud-addon' ), (string) ( $detail['run_id'] ?? '' ), __( 'Cloud run identifier.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Run status', 'npcink-cloud-addon' ), (string) ( $detail['status_label'] ?? '' ), __( 'Cloud-owned run state.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Result status', 'npcink-cloud-addon' ), (string) ( $detail['result_status_label'] ?? '' ), __( 'Result availability from Cloud.', 'npcink-cloud-addon' ) ); ?>
					<?php if ( '' !== $error_code ) : ?>
						<?php self::render_diagnostic_row( __( 'Error code', 'npcink-cloud-addon' ), $error_code, __( 'Cloud error classification.', 'npcink-cloud-addon' ) ); ?>
					<?php endif; ?>
					<?php self::render_diagnostic_row( __( 'Started', 'npcink-cloud-addon' ), self::format_datetime_value( (string) ( $detail['started_at'] ?? '' ) ), __( 'Displayed in the WordPress site timezone.', 'npcink-cloud-addon' ) ); ?>
					<?php self::render_diagnostic_row( __( 'Finished', 'npcink-cloud-addon' ), self::format_datetime_value( (string) ( $detail['finished_at'] ?? '' ) ), __( 'Displayed in the WordPress site timezone.', 'npcink-cloud-addon' ) ); ?>
				</tbody>
			</table>
			<div class="npcink-cloud-summary__actions npcink-cloud-summary__actions--start npcink-cloud-run-detail-actions">
				<?php if ( ! empty( $detail['has_result'] ) ) : ?>
					<a class="button button-secondary" href="<?php echo esc_url( self::runtime_tab_url( 'result', $run_id ) ); ?>"><?php esc_html_e( 'Read result', 'npcink-cloud-addon' ); ?></a>
				<?php endif; ?>
				<?php if ( ! empty( $detail['retryable'] ) ) : ?>
					<?php self::render_runtime_retry_form( $run_id ); ?>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Renders a nonce-protected Cloud retry request form.
		 *
		 * @param string $run_id Cloud run id.
		 * @return void
		 */
		private static function render_runtime_retry_form( string $run_id ): void {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( self::ACTION_RETRY_RUNTIME_RUN ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_RETRY_RUNTIME_RUN ); ?>" />
				<input type="hidden" name="runtime_run_id" value="<?php echo esc_attr( $run_id ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Request Cloud retry', 'npcink-cloud-addon' ); ?></button>
			</form>
			<?php
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
			$delivery_enabled = ! empty( $site_knowledge['delivery_enabled'] );
			$active_view = self::site_knowledge_view_from_request();
			$last_delivery_error = (string) ( $site_knowledge['last_delivery_error'] ?? '' );
			$cloud_usage = $delivery_enabled
				? self::get_site_knowledge_usage_projection( Npcink_Cloud_Site_Knowledge_Runtime_Bridge::get_cached_status_summary() )
				: self::get_site_knowledge_usage_projection( array() );
			$show_technical_detail = ! empty( $site_knowledge['wp_cron_disabled'] )
				|| '' !== $last_delivery_error
				|| '' !== (string) ( $site_knowledge['last_error_code'] ?? '' );
			?>
					<div class="npcink-cloud-site-knowledge-consent npcink-cloud-site-knowledge-consent--readonly">
						<div class="npcink-cloud-site-knowledge-consent__control" aria-describedby="npcink-cloud-site-knowledge-delivery-summary npcink-cloud-site-knowledge-delivery-status">
							<span class="npcink-cloud-site-knowledge-consent__copy">
								<span class="npcink-cloud-site-knowledge-consent__title"><?php esc_html_e( 'Site Knowledge delivery', 'npcink-cloud-addon' ); ?></span>
								<span id="npcink-cloud-site-knowledge-delivery-summary" class="npcink-cloud-site-knowledge-consent__description"><?php esc_html_e( 'Allow public content-change delivery and explicit administrator delivery intent. WordPress content is not changed.', 'npcink-cloud-addon' ); ?></span>
							</span>
						</div>
						<details class="npcink-cloud-inline-info npcink-cloud-site-knowledge-consent__info">
							<summary aria-label="<?php esc_attr_e( 'Site Knowledge delivery details', 'npcink-cloud-addon' ); ?>">
								<span aria-hidden="true">i</span>
							</summary>
							<div class="npcink-cloud-inline-info__content">
								<p><?php esc_html_e( 'Cloud owns indexing, rebuild, deletion, freshness policy, and diagnostics.', 'npcink-cloud-addon' ); ?></p>
								<p><?php esc_html_e( 'Turning delivery off does not delete existing Cloud index data.', 'npcink-cloud-addon' ); ?></p>
								<p><?php esc_html_e( 'Routine refresh, start, and rebuild controls are hidden or disabled while delivery is off.', 'npcink-cloud-addon' ); ?></p>
								<p><?php esc_html_e( 'Cloud index cleanup remains a separate explicit action.', 'npcink-cloud-addon' ); ?></p>
							</div>
						</details>
						<span id="npcink-cloud-site-knowledge-delivery-status" class="npcink-cloud-site-knowledge-consent__state">
							<?php echo $delivery_enabled ? esc_html__( 'enabled', 'npcink-cloud-addon' ) : esc_html__( 'disabled', 'npcink-cloud-addon' ); ?>
						</span>
						<a class="button button-secondary" href="<?php echo esc_url( self::tab_url( 'permissions' ) ); ?>"><?php esc_html_e( 'Change in Overview', 'npcink-cloud-addon' ); ?></a>
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
					<div class="npcink-cloud-section-heading npcink-cloud-site-knowledge-heading">
						<h3><?php esc_html_e( 'Overview', 'npcink-cloud-addon' ); ?></h3>
						<div class="npcink-cloud-summary__actions">
							<?php if ( $delivery_enabled ) : ?>
								<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
									<?php wp_nonce_field( self::ACTION_REFRESH_SITE_KNOWLEDGE ); ?>
									<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION_REFRESH_SITE_KNOWLEDGE ); ?>" />
									<button type="submit" class="button button-primary"><?php esc_html_e( 'Request public content refresh', 'npcink-cloud-addon' ); ?></button>
								</form>
							<?php endif; ?>
							<a class="button button-secondary npcink-cloud-secondary-action" href="<?php echo esc_url( self::tab_view_url( 'site_knowledge', 'index' ) ); ?>"><?php esc_html_e( 'Manage index', 'npcink-cloud-addon' ); ?></a>
							<a class="npcink-cloud-text-link npcink-cloud-secondary-action" href="<?php echo esc_url( $base_url . '/portal/site-knowledge' ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Cloud Site Knowledge', 'npcink-cloud-addon' ); ?></a>
						</div>
					</div>
					<?php if ( ! $delivery_enabled ) : ?>
						<p class="description npcink-cloud-site-knowledge-disabled-note"><?php esc_html_e( 'Delivery is off; refresh controls and routine delivery rows are hidden.', 'npcink-cloud-addon' ); ?></p>
						<?php if ( $show_technical_detail ) : ?>
							<details class="npcink-cloud-advanced-detail">
								<summary><?php esc_html_e( 'Technical delivery details', 'npcink-cloud-addon' ); ?></summary>
								<div class="npcink-cloud-advanced-detail__body">
									<?php self::render_site_knowledge_bridge_health_detail( $site_knowledge ); ?>
								</div>
							</details>
						<?php endif; ?>
						<?php
						return;
					endif;
					?>
					<table class="widefat striped npcink-cloud-site-knowledge-status">
						<tbody>
							<tr>
								<th scope="row"><?php esc_html_e( 'Delivery', 'npcink-cloud-addon' ); ?></th>
								<td><?php echo $delivery_enabled ? esc_html__( 'enabled', 'npcink-cloud-addon' ) : esc_html__( 'disabled locally', 'npcink-cloud-addon' ); ?></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Buffered public changes', 'npcink-cloud-addon' ); ?></th>
								<td><?php echo esc_html( (string) absint( $site_knowledge['buffer_count'] ?? 0 ) ); ?></td>
							</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Last delivery', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['last_delivery_at'] ?? '' ) ) ); ?></td>
						</tr>
						<?php if ( 'idle' !== (string) ( $site_knowledge['maintenance_status'] ?? 'idle' ) ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Full-index delivery progress', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_site_knowledge_progress( $site_knowledge ) ); ?></td>
						</tr>
						<?php endif; ?>
						<?php if ( absint( $site_knowledge['dropped_count'] ?? 0 ) > 0 ) : ?>
						<tr>
							<th scope="row"><?php esc_html_e( 'Changes no longer buffered', 'npcink-cloud-addon' ); ?></th>
							<td><?php echo esc_html( self::format_site_knowledge_dropped_changes( $site_knowledge ) ); ?></td>
						</tr>
						<?php endif; ?>
							<?php if ( '' !== $last_delivery_error ) : ?>
							<tr>
								<th scope="row"><?php esc_html_e( 'Last error', 'npcink-cloud-addon' ); ?></th>
								<td><?php self::render_site_knowledge_error_cell( $last_delivery_error ); ?></td>
							</tr>
							<?php endif; ?>
						</tbody>
					</table>
					<span hidden data-npcink-site-knowledge-refresh data-npcink-site-knowledge-state="<?php echo esc_attr( (string) ( $cloud_usage['state'] ?? 'not_refreshed' ) ); ?>"></span>
					<details class="npcink-cloud-advanced-detail">
						<summary><?php esc_html_e( 'Technical delivery details', 'npcink-cloud-addon' ); ?></summary>
						<div class="npcink-cloud-advanced-detail__body">
							<?php self::render_site_knowledge_bridge_health_detail( $site_knowledge ); ?>
							<?php self::render_site_knowledge_cloud_quota_detail( $cloud_usage ); ?>
							<?php self::render_site_knowledge_retrieval_acceptance( $cloud_usage ); ?>
						</div>
					</details>
			<?php
		}

		/**
		 * Renders the read-only Cloud automatic retrieval acceptance projection.
		 *
		 * @param array<string,mixed> $usage Site Knowledge status summary.
		 * @return void
		 */
		private static function render_site_knowledge_retrieval_acceptance( array $usage ): void {
			$acceptance = is_array( $usage['retrieval_acceptance'] ?? null ) ? $usage['retrieval_acceptance'] : array();
			?>
			<div class="npcink-cloud-site-knowledge-automatic-acceptance" data-npcink-site-knowledge-acceptance>
				<h4><?php esc_html_e( 'Platform automatic retrieval validation', 'npcink-cloud-addon' ); ?></h4>
				<p><strong data-npcink-site-knowledge-acceptance-status><?php echo esc_html( (string) ( $acceptance['label'] ?? __( 'Waiting for automatic validation', 'npcink-cloud-addon' ) ) ); ?></strong></p>
				<p class="description"><?php esc_html_e( 'Cloud runs this automatically after an index rebuild or full index publication. No site-admin action is required.', 'npcink-cloud-addon' ); ?></p>
				<p class="description" data-npcink-site-knowledge-acceptance-time<?php echo empty( $acceptance['verified_label'] ) ? ' hidden' : ''; ?>><?php echo esc_html( (string) ( $acceptance['verified_label'] ?? '' ) ); ?></p>
			</div>
			<?php
		}

		/**
		 * Renders low-frequency Cloud-owned Site Knowledge quota detail.
		 *
		 * The document quota stays in the Overview service summary and is not repeated here.
		 *
		 * @param array<string,mixed> $usage Bounded display projection.
		 * @return void
		 */
		private static function render_site_knowledge_cloud_quota_detail( array $usage ): void {
			$details = is_array( $usage['details'] ?? null ) ? $usage['details'] : array();
			?>
			<h4><?php esc_html_e( 'Cloud index details', 'npcink-cloud-addon' ); ?></h4>
			<table class="widefat striped npcink-cloud-site-knowledge-status npcink-cloud-site-knowledge-quota-detail">
				<tbody>
					<?php foreach ( array( 'chunks', 'sync', 'truncated', 'skipped', 'lastSync' ) as $key ) :
						$detail = is_array( $details[ $key ] ?? null ) ? $details[ $key ] : array();
					?>
					<tr data-npcink-site-knowledge-detail="<?php echo esc_attr( $key ); ?>"<?php echo empty( $detail['available'] ) ? ' hidden' : ''; ?>>
						<th scope="row" data-npcink-site-knowledge-detail-label><?php echo esc_html( (string) ( $detail['label'] ?? '' ) ); ?></th>
						<td data-npcink-site-knowledge-detail-value><?php echo esc_html( (string) ( $detail['value'] ?? '' ) ); ?></td>
					</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
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
			<h4><?php esc_html_e( 'Bridge health detail', 'npcink-cloud-addon' ); ?></h4>
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
		 * Renders read-only Pro Cloud Runtime entitlement detail.
		 *
		 * @param array<string,mixed> $summary Entitlement summary.
		 * @return void
		 */
		private static function render_pro_cloud_runtime_summary( array $summary ): void {
			$runtime = is_array( $summary['pro_cloud_runtime'] ?? null ) ? $summary['pro_cloud_runtime'] : array();
			if ( empty( $summary['available'] ) || empty( $runtime['reported'] ) ) {
				return;
			}
			?>
			<h3><?php esc_html_e( 'Pro Cloud Runtime', 'npcink-cloud-addon' ); ?></h3>
			<table class="widefat striped" style="max-width: 860px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Retention', 'npcink-cloud-addon' ); ?></th>
						<td>
							<?php
							echo esc_html( self::format_runtime_days_projection( $runtime, 'result_retention_days' ) );
							?>
						</td>
					</tr>
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
		 * Formats bounded full-index delivery progress without claiming index truth.
		 *
		 * @param array<string,mixed> $site_knowledge Site Knowledge health.
		 * @return string
		 */
		private static function format_site_knowledge_progress( array $site_knowledge ): string {
			$status = sanitize_key( (string) ( $site_knowledge['maintenance_status'] ?? 'idle' ) );
			$labels = array(
				'queued' => __( 'Waiting to deliver', 'npcink-cloud-addon' ),
				'awaiting_site' => __( 'Waiting for this site', 'npcink-cloud-addon' ),
				'delivering' => __( 'Delivering', 'npcink-cloud-addon' ),
				'retrying' => __( 'Retry scheduled', 'npcink-cloud-addon' ),
				'blocked' => __( 'Blocked after retries', 'npcink-cloud-addon' ),
			);
			$label = $labels[ $status ] ?? self::format_site_knowledge_status_label( $status );
			$completed = absint( $site_knowledge['maintenance_completed_batches'] ?? 0 );
				$total = absint( $site_knowledge['maintenance_total_batches'] ?? 0 );
				if ( $total > 0 ) {
					/* translators: 1: completed delivery batches, 2: total delivery batches. */
					$label .= ' · ' . sprintf( __( '%1$d of %2$d batches', 'npcink-cloud-addon' ), min( $completed, $total ), $total );
				}
				$next = (string) ( $site_knowledge['next_flush_at'] ?? '' );
				if ( in_array( $status, array( 'queued', 'retrying' ), true ) && '' !== $next ) {
					/* translators: %s: formatted date and time of the next delivery attempt. */
					$label .= ' · ' . sprintf( __( 'Next attempt: %s', 'npcink-cloud-addon' ), self::format_datetime_value( $next ) );
			}

			return $label;
		}

		/**
		 * Formats delivery-buffer loss with a safe recovery action.
		 *
		 * @param array<string,mixed> $site_knowledge Site Knowledge health.
		 * @return string
		 */
		private static function format_site_knowledge_dropped_changes( array $site_knowledge ): string {
			return sprintf(
				/* translators: %d: count of change notifications no longer retained. */
				_n(
					'%d change notification is no longer in the local delivery buffer. Request a public content refresh to reconcile it.',
					'%d change notifications are no longer in the local delivery buffer. Request a public content refresh to reconcile them.',
					absint( $site_knowledge['dropped_count'] ?? 0 ),
					'npcink-cloud-addon'
				),
				absint( $site_knowledge['dropped_count'] ?? 0 )
			);
		}

		/**
		 * Renders the Site Knowledge error row with a localized summary.
		 *
		 * @param string $error Raw Cloud/runtime error text.
		 * @return void
		 */
		private static function render_site_knowledge_error_cell( string $error ): void {
			$error = trim( $error );
			if ( '' === $error ) {
				echo esc_html( self::format_empty( '' ) );
				return;
			}

			$summary = self::format_site_knowledge_error_summary( $error );
			?>
			<div class="npcink-cloud-error-summary"><?php echo esc_html( $summary ); ?></div>
			<?php if ( $summary !== $error ) : ?>
				<details class="npcink-cloud-inline-note npcink-cloud-error-detail">
					<summary><?php esc_html_e( 'Show original Cloud error', 'npcink-cloud-addon' ); ?></summary>
					<code><?php echo esc_html( $error ); ?></code>
				</details>
			<?php endif; ?>
			<?php
		}

		/**
		 * Maps known dynamic Cloud/runtime errors to local admin summaries.
		 *
		 * @param string $error Raw Cloud/runtime error text.
		 * @return string
		 */
		private static function format_site_knowledge_error_summary( string $error ): string {
			$normalized = strtolower( $error );
			if ( false !== strpos( $normalized, 'curl error 6' ) || false !== strpos( $normalized, 'could not resolve host' ) ) {
				return __( 'Cloud host could not be resolved. Check the configured Cloud Base URL, then re-check the connection.', 'npcink-cloud-addon' );
			}
			if ( false !== strpos( $normalized, 'curl error 7' ) || false !== strpos( $normalized, 'connection refused' ) || false !== strpos( $normalized, 'failed to connect' ) ) {
				return __( 'Cloud could not be reached. Check the Cloud Base URL and service availability, then try again.', 'npcink-cloud-addon' );
			}
			if ( false !== strpos( $normalized, 'timed out' ) || false !== strpos( $normalized, 'timeout' ) ) {
				return __( 'The Cloud request timed out. Try again later or re-check the connection.', 'npcink-cloud-addon' );
			}
			if ( false !== strpos( $normalized, 'unauthorized' ) || false !== strpos( $normalized, 'forbidden' ) || false !== strpos( $normalized, 'invalid signature' ) ) {
				return __( 'Cloud rejected the saved credential. Reconnect this site in Npcink Cloud.', 'npcink-cloud-addon' );
			}

			if ( false !== strpos( $normalized, 'personal data' ) && false !== strpos( $normalized, 'data_classification=pii' ) ) {
				return __( 'The request appears to contain personal data. Cloud requires data_classification=pii before delivery.', 'npcink-cloud-addon' );
			}

			if ( preg_match( "/exceeded max active cloud runs '?([0-9]+)'?/", $normalized, $matches ) ) {
				return sprintf(
					/* translators: %d: maximum active Cloud run count. */
					__( 'Cloud active run limit reached (%d). Wait for the current run to finish, then try again.', 'npcink-cloud-addon' ),
					absint( $matches[1] )
				);
			}

			return $error;
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

	}
}
