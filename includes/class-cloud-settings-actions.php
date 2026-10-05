<?php
/**
 * Request handling for the Cloud Addon settings page.
 *
 * Owns the admin-post/wp-ajax flow (authorization, save-and-verify,
 * disconnect, local permissions, Site Knowledge operations, readiness
 * testing) and the transient state the render side projects. The render
 * class stays a projection: it reads this state and borrows nothing else.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Settings_Actions' ) ) {
	/**
	 * Settings request handlers and their shared transient state.
	 */
	final class Npcink_Cloud_Settings_Actions {
		public const ACTION_SAVE = 'npcink_cloud_addon_save';
		public const ACTION_COMPLETE_AUTH = 'npcink_cloud_addon_complete_auth';
		public const ACTION_START_AUTH = 'npcink_cloud_addon_start_auth';
		public const ACTION_START_CUSTOM_AUTH = 'npcink_cloud_addon_start_custom_auth';
		public const ACTION_DISCONNECT = 'npcink_cloud_addon_disconnect';
		public const ACTION_UPDATE_LOCAL_PERMISSION = 'npcink_cloud_addon_update_local_permission';
		public const ACTION_DISMISS_MONITORING_PROMPT = 'npcink_cloud_addon_dismiss_monitoring_prompt';
		public const ACTION_REFRESH_SITE_KNOWLEDGE = 'npcink_cloud_addon_refresh_site_knowledge';
		public const ACTION_REFRESH_SITE_KNOWLEDGE_STATUS = 'npcink_cloud_addon_refresh_site_knowledge_status';
		public const ACTION_MANAGE_SITE_KNOWLEDGE_INDEX = 'npcink_cloud_addon_manage_site_knowledge_index';
		public const ACTION_RUN_MANUAL_READINESS_TEST = 'npcink_cloud_addon_run_manual_readiness_test';
		public const ACTION_REFRESH_ENTITLEMENT = 'npcink_cloud_addon_refresh_entitlement';
		public const AUTH_STATE_TTL_SECONDS = 600;

		/**
		 * Returns and clears the saved admin notice for the current user.
		 *
		 * @return array<string,mixed>
		 */
		public static function get_admin_notice(): array {
			$notice = get_transient( self::notice_transient_key() );
			delete_transient( self::notice_transient_key() );

			return is_array( $notice ) ? $notice : array();
		}

		/**
		 * Refreshes the shared read-only entitlement projection for the admin UI.
		 *
		 * @return void
		 */
		public static function handle_refresh_entitlement(): void {
			if ( ! current_user_can( Npcink_Cloud_Settings_Page::MENU_CAPABILITY ) ) {
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
					'label' => Npcink_Cloud_Settings_Page::format_overview_entitlement( $summary, true ),
					'state' => sanitize_key( (string) ( $summary['state'] ?? 'fresh' ) ),
					'syncedAt' => sanitize_text_field( (string) ( $summary['synced_at'] ?? '' ) ),
					'metrics' => Npcink_Cloud_Settings_Page::get_overview_entitlement_metrics( $summary ),
				)
			);
		}

		/**
		 * Refreshes the Cloud-owned Site Knowledge usage projection.
		 *
		 * @return void
		 */
		public static function handle_refresh_site_knowledge_status(): void {
			if ( ! current_user_can( Npcink_Cloud_Settings_Page::MENU_CAPABILITY ) ) {
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

			$data = Npcink_Cloud_Settings_Page::get_site_knowledge_usage_projection( $summary );
			// The waiting count renders server-side, so return the refreshed
			// label too and let the page update it in place instead of reloading.
			$coverage = is_array( $summary['article_coverage'] ?? null ) ? $summary['article_coverage'] : array();
			$health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
			$waiting_count = max( absint( $health['buffer_count'] ?? 0 ), absint( $coverage['not_indexed_count'] ?? 0 ) );
			$data['waiting_count'] = $waiting_count;
			$data['waiting_label'] = $waiting_count > 0
				? sprintf(
					/* translators: %d: number of content updates waiting for automatic processing. */
					__( 'Updates waiting: %d', 'npcink-cloud-addon' ),
					$waiting_count
				)
				: '';
			wp_send_json_success( $data );
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
				self::set_admin_notice( 'error', __( 'The Cloud authorization request expired or is invalid. Start the connection again from WordPress.', 'npcink-cloud-addon' ) );
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
			if ( ! current_user_can( Npcink_Cloud_Settings_Page::MENU_CAPABILITY ) ) {
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

		/** Handles a bounded Cloud-owned Site Knowledge index operation. */
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
				self::set_admin_notice( 'warning', Npcink_Cloud_Settings_Page::format_readiness_detail( $result ) );
			}

			self::redirect_to_page( 'advanced', 'checks' );
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
							self::bound_admin_error_detail( (string) ( $summary['message'] ?? __( 'Unknown entitlement refresh result.', 'npcink-cloud-addon' ) ) )
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
					return;
				}
				self::set_admin_notice(
					'warning',
					__( 'The Cloud connection works, but this site is not active in Cloud yet. Activate the site in Npcink Cloud, then check the activation again here.', 'npcink-cloud-addon' )
				);
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
					self::bound_admin_error_detail( self::redact_sensitive_message( (string) $probe['live_message'] ) )
				);
			}
			if ( empty( $probe['auth_ok'] ) && ! empty( $probe['auth_message'] ) ) {
				$messages[] = sprintf(
					/* translators: %s: signed verification error. */
					__( 'Signed verification failed: %s', 'npcink-cloud-addon' ),
					self::bound_admin_error_detail( self::redact_sensitive_message( (string) $probe['auth_message'] ) )
				);
			}

			return '' !== implode( ' ', $messages )
				? sanitize_text_field( implode( ' ', $messages ) )
				: __( 'Cloud verification failed.', 'npcink-cloud-addon' );
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
		 * Returns the one-time monitoring consent prompt key for the current administrator.
		 *
		 * @return string
		 */
		private static function monitoring_consent_prompt_transient_key(): string {
			return 'npcink_cloud_monitoring_consent_' . absint( get_current_user_id() );
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
		 * Clears the one-time monitoring consent prompt.
		 *
		 * @return void
		 */
		private static function clear_monitoring_consent_prompt(): void {
			delete_transient( self::monitoring_consent_prompt_transient_key() );
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
		 * Returns and clears local permission row feedback.
		 *
		 * @return array<string,string>
		 */
		public static function get_local_permission_feedback(): array {
			$feedback = get_transient( self::local_permission_feedback_transient_key() );
			delete_transient( self::local_permission_feedback_transient_key() );

			return is_array( $feedback ) ? $feedback : array();
		}

		/**
		 * Returns the latest manual readiness result for this administrator.
		 *
		 * @return array<string,mixed>
		 */
		public static function get_manual_readiness_result(): array {
			$result = get_transient( self::manual_readiness_transient_key() );

			return is_array( $result ) ? $result : array();
		}

		/**
		 * Returns whether the one-time monitoring consent prompt is pending.
		 *
		 * @return bool
		 */
		public static function has_monitoring_consent_prompt(): bool {
			return (bool) get_transient( self::monitoring_consent_prompt_transient_key() );
		}

		/**
		 * Returns local permission switch definitions.
		 *
		 * @return array<string,array{label:string,description:string}>
		 */
		public static function get_local_permission_definitions(): array {
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
					'description' => __( 'Send metadata-only diagnostic events (feature steps, outcomes, timing, and error codes) to improve reliability. Never includes prompts, content, user or post IDs, emails, URLs, or credentials. Off by default.', 'npcink-cloud-addon' ),
					'more'        => __( 'Full detail: metadata-only events cover feature steps, outcomes, timing, and machine-readable error codes. Never sent: prompts, source or generated content, raw WordPress user or post IDs, emails, URLs, DOM data, credentials, or free-form error messages. Off by default; administrators can turn it off at any time.', 'npcink-cloud-addon' ),
				),
			);
		}

		/**
		 * Bounds a possibly long upstream failure text to one readable admin
		 * line; full detail stays in Cloud or the server log.
		 *
		 * @param string $detail Raw failure detail.
		 * @return string
		 */
		public static function bound_admin_error_detail( string $detail ): string {
			$detail = sanitize_text_field( $detail );
			if ( function_exists( 'mb_substr' ) ) {
				$bounded = mb_substr( $detail, 0, 200 );
			} else {
				// Multibyte-safe bound without mbstring via a UTF-8 regex slice;
				// wp_html_excerpt() strips a trailing partial sequence when the
				// regex cannot run, so the detail is never fully discarded.
				$matches = array();
				$matched = preg_match( '/\A.{0,200}/us', $detail, $matches );
				if ( 1 === $matched ) {
					$bounded = (string) $matches[0];
				} elseif ( function_exists( 'wp_html_excerpt' ) ) {
					$bounded = wp_html_excerpt( $detail, 200, '' );
				} else {
					$bounded = substr( $detail, 0, 200 );
				}
			}

			return $bounded === $detail ? $bounded : rtrim( $bounded ) . '…';
		}

		/**
		 * Returns the active Cloud Addon page URL.
		 *
		 * @return string
		 */
		public static function page_url(): string {
			$parent = defined( 'NPCINK_TOOLBOX_VERSION' ) ? 'admin.php' : 'options-general.php';
			return admin_url( $parent . '?page=' . Npcink_Cloud_Settings_Page::PAGE_SLUG );
		}

	}
}
