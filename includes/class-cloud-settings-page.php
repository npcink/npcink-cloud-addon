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
		public const PAGE_SLUG = 'npcink-cloud-addon';
		public const MENU_CAPABILITY = 'manage_options';
		private const DATETIME_DISPLAY_FORMAT = 'Y-m-d H:i:s';

		/**
		 * Registers admin hooks.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'admin_menu', array( __CLASS__, 'add_menu_page' ), 50 );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
			add_action( 'admin_post_' . Npcink_Cloud_Settings_Actions::ACTION_SAVE, array( Npcink_Cloud_Settings_Actions::class, 'handle_save' ) );
			add_action( 'admin_post_' . Npcink_Cloud_Settings_Actions::ACTION_COMPLETE_AUTH, array( Npcink_Cloud_Settings_Actions::class, 'handle_complete_auth' ) );
			add_action( 'admin_post_' . Npcink_Cloud_Settings_Actions::ACTION_START_AUTH, array( Npcink_Cloud_Settings_Actions::class, 'handle_start_auth' ) );
			add_action( 'admin_post_' . Npcink_Cloud_Settings_Actions::ACTION_START_CUSTOM_AUTH, array( Npcink_Cloud_Settings_Actions::class, 'handle_start_custom_auth' ) );
			add_action( 'admin_post_' . Npcink_Cloud_Settings_Actions::ACTION_DISCONNECT, array( Npcink_Cloud_Settings_Actions::class, 'handle_disconnect' ) );
			add_action( 'admin_post_' . Npcink_Cloud_Settings_Actions::ACTION_CHANGE_ACCOUNT, array( Npcink_Cloud_Settings_Actions::class, 'handle_change_account' ) );
			add_action( 'admin_post_' . Npcink_Cloud_Settings_Actions::ACTION_UPDATE_LOCAL_PERMISSION, array( Npcink_Cloud_Settings_Actions::class, 'handle_update_local_permission' ) );
			add_action( 'admin_post_' . Npcink_Cloud_Settings_Actions::ACTION_DISMISS_MONITORING_PROMPT, array( Npcink_Cloud_Settings_Actions::class, 'handle_dismiss_monitoring_prompt' ) );
			add_action( 'admin_post_' . Npcink_Cloud_Settings_Actions::ACTION_REFRESH_SITE_KNOWLEDGE, array( Npcink_Cloud_Settings_Actions::class, 'handle_refresh_site_knowledge' ) );
			add_action( 'wp_ajax_' . Npcink_Cloud_Settings_Actions::ACTION_REFRESH_SITE_KNOWLEDGE_STATUS, array( Npcink_Cloud_Settings_Actions::class, 'handle_refresh_site_knowledge_status' ) );
			add_action( 'admin_post_' . Npcink_Cloud_Settings_Actions::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX, array( Npcink_Cloud_Settings_Actions::class, 'handle_manage_site_knowledge_index' ) );
			add_action( 'admin_post_' . Npcink_Cloud_Settings_Actions::ACTION_RUN_MANUAL_READINESS_TEST, array( Npcink_Cloud_Settings_Actions::class, 'handle_run_manual_readiness_test' ) );
			add_action( 'wp_ajax_' . Npcink_Cloud_Settings_Actions::ACTION_REFRESH_ENTITLEMENT, array( Npcink_Cloud_Settings_Actions::class, 'handle_refresh_entitlement' ) );
		}

		/**
		 * Directory name under wp-content/plugins (the Local symlink name).
		 *
		 * Never derive this from realpath() / __FILE__: git worktree folders
		 * (e.g. .worktrees/addon-recommendation-v2) are not under WP_PLUGIN_DIR,
		 * and plugins_url( $path, $file ) will then emit
		 * /wp-content/plugins/Users/.../ (broken asset URL, 404).
		 *
		 * @return string
		 */
		private static function plugins_dir_slug(): string {
			return 'npcink-cloud-addon';
		}

		/**
		 * Absolute admin-asset URL under /wp-content/plugins/{slug}/...
		 *
		 * Uses the single-argument plugins_url() form so the URL never depends
		 * on NPCINK_CLOUD_ADDON_FILE realpath mapping.
		 *
		 * @param string $relative_under_plugin Path relative to the plugin root, e.g. 'assets' + '/admin.css'.
		 * @return string
		 */
		private static function asset_url( string $relative_under_plugin ): string {
			return plugins_url( self::plugins_dir_slug() . '/' . ltrim( $relative_under_plugin, '/' ) );
		}

		/**
		 * Enqueues admin assets for the Cloud Addon pages.
		 *
		 * @param string $hook_suffix Admin hook suffix.
		 * @return void
		 */
		public static function enqueue_admin_assets( string $hook_suffix ): void {
			$page = self::request_query_arg( 'page' );
			// Only this addon's screen — not every Npcink AI submenu (avoids dual admin.css with Core).
			$is_cloud_page = self::PAGE_SLUG === $page || false !== strpos( $hook_suffix, self::PAGE_SLUG );
			if ( ! $is_cloud_page ) {
				return;
			}

			$style_path = NPCINK_CLOUD_ADDON_DIR . '/assets/admin.css';
			if ( is_readable( $style_path ) ) {
				wp_enqueue_style( 'dashicons' );
				wp_enqueue_style(
					'npcink-cloud-addon-admin',
					self::asset_url( 'assets/admin.css' ),
					array( 'dashicons' ),
					NPCINK_CLOUD_ADDON_VERSION . '.' . (string) filemtime( $style_path )
				);
			}

			if ( false === strpos( $hook_suffix, self::PAGE_SLUG ) && self::PAGE_SLUG !== $page ) {
				return;
			}

			wp_enqueue_script(
				'npcink-cloud-addon-admin-status-fragments',
				self::asset_url( 'assets/admin-status-fragments.js' ),
				array(),
				NPCINK_CLOUD_ADDON_VERSION,
				true
			);

			wp_enqueue_script(
				'npcink-cloud-addon-admin-entitlement',
				self::asset_url( 'assets/admin-entitlement.js' ),
				array( 'npcink-cloud-addon-admin-status-fragments' ),
				NPCINK_CLOUD_ADDON_VERSION,
				true
			);
			wp_localize_script(
				'npcink-cloud-addon-admin-entitlement',
				'npcinkCloudEntitlement',
				array(
					'action' => Npcink_Cloud_Settings_Actions::ACTION_REFRESH_ENTITLEMENT,
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( Npcink_Cloud_Settings_Actions::ACTION_REFRESH_ENTITLEMENT ),
					'failedLabel' => __( 'Plan and entitlement are temporarily unavailable.', 'npcink-cloud-addon' ),
					'updateFailedLabel' => __( 'Update failed', 'npcink-cloud-addon' ),
				)
			);

			wp_enqueue_script(
				'npcink-cloud-addon-admin-site-knowledge',
				self::asset_url( 'assets/admin-site-knowledge.js' ),
				array( 'npcink-cloud-addon-admin-status-fragments' ),
				NPCINK_CLOUD_ADDON_VERSION,
				true
			);
			wp_localize_script(
				'npcink-cloud-addon-admin-site-knowledge',
				'npcinkCloudSiteKnowledge',
				array(
					'action' => Npcink_Cloud_Settings_Actions::ACTION_REFRESH_SITE_KNOWLEDGE_STATUS,
					'ajaxUrl' => admin_url( 'admin-ajax.php' ),
					'nonce' => wp_create_nonce( Npcink_Cloud_Settings_Actions::ACTION_REFRESH_SITE_KNOWLEDGE_STATUS ),
					'failedLabel' => __( 'Site Knowledge usage is temporarily unavailable.', 'npcink-cloud-addon' ),
					'updateFailedLabel' => __( 'Update failed', 'npcink-cloud-addon' ),
				)
			);

			wp_enqueue_script(
				'npcink-cloud-addon-admin-permissions',
				self::asset_url( 'assets/admin-permissions.js' ),
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

			$gen_js = NPCINK_CLOUD_ADDON_DIR . '/assets/admin-generation-tests.js';
			if ( is_readable( $gen_js ) ) {
				if ( function_exists( 'wp_enqueue_media' ) ) {
					wp_enqueue_media();
				}
				wp_enqueue_script(
					'npcink-cloud-addon-admin-generation-tests',
					self::asset_url( 'assets/admin-generation-tests.js' ),
					array( 'jquery' ),
					NPCINK_CLOUD_ADDON_VERSION . '.' . (string) filemtime( $gen_js ),
					true
				);
				wp_localize_script(
					'npcink-cloud-addon-admin-generation-tests',
					'npcinkCloudGenerationTests',
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'nonce' => wp_create_nonce( 'ai_ability_explorer_invoke' ),
						'explorerUrl' => admin_url( 'tools.php?page=ai-abilities-explorer' ),
						'strings' => array(
							'generate' => __( 'Generate', 'npcink-cloud-addon' ),
							'generating' => __( 'Generating…', 'npcink-cloud-addon' ),
							'generated' => __( 'Generated', 'npcink-cloud-addon' ),
							'success' => __( 'Generated', 'npcink-cloud-addon' ),
							'viewRaw' => __( 'View raw response', 'npcink-cloud-addon' ),
							'noReadable' => __( 'Generated, but no plain text result was returned.', 'npcink-cloud-addon' ),
							'error' => __( 'Generation failed. Try again, or open Ability Explorer for details.', 'npcink-cloud-addon' ),
							'errorNetwork' => __( 'Could not reach the site. Check your connection and try again.', 'npcink-cloud-addon' ),
							'errorPermission' => __( 'You need administrator access to run this test.', 'npcink-cloud-addon' ),
							'errorMissingAbility' => __( 'This generation ability is not available. Open Ability Explorer or check the WordPress AI plugin.', 'npcink-cloud-addon' ),
							'errorValidation' => __( 'Check the form fields and try again.', 'npcink-cloud-addon' ),
							'errorSession' => __( 'This page expired. Refresh and try again.', 'npcink-cloud-addon' ),
							'testText' => __( 'Test text', 'npcink-cloud-addon' ),
							'testImage' => __( 'Test image', 'npcink-cloud-addon' ),
							'testVision' => __( 'Test vision', 'npcink-cloud-addon' ),
							'content' => __( 'Content', 'npcink-cloud-addon' ),
							'prompt' => __( 'Prompt', 'npcink-cloud-addon' ),
							'imageUrl' => __( 'Image URL', 'npcink-cloud-addon' ),
							'context' => __( 'Context', 'npcink-cloud-addon' ),
							'imageUrlHelp' => __( 'Public image URL or data URI.', 'npcink-cloud-addon' ),
							'chipAltConciseLabel' => __( 'Concise', 'npcink-cloud-addon' ),
							'chipAltConciseValue' => __( 'Keep the alt text concise and specific.', 'npcink-cloud-addon' ),
							'chipAltSubjectLabel' => __( 'Main subject', 'npcink-cloud-addon' ),
							'chipAltSubjectValue' => __( 'Focus on the main subject and skip minor background details.', 'npcink-cloud-addon' ),
							'chipAltScreenReaderLabel' => __( 'Screen reader', 'npcink-cloud-addon' ),
							'chipAltScreenReaderValue' => __( 'Write the alt text for screen reader users.', 'npcink-cloud-addon' ),
							'chipAltNoDecorLabel' => __( 'Not decorative', 'npcink-cloud-addon' ),
							'chipAltNoDecorValue' => __( 'Treat this as informative, not decorative.', 'npcink-cloud-addon' ),
							'chipImageSimpleLabel' => __( 'Simple scene', 'npcink-cloud-addon' ),
							'chipImageSimpleValue' => __( 'A simple, clean illustration with a plain background.', 'npcink-cloud-addon' ),
							'chipImagePhotoLabel' => __( 'Photo style', 'npcink-cloud-addon' ),
							'chipImagePhotoValue' => __( 'A realistic photograph with natural lighting.', 'npcink-cloud-addon' ),
							'chipImageNoTextLabel' => __( 'No text', 'npcink-cloud-addon' ),
							'chipImageNoTextValue' => __( 'Do not include any text or logos in the image.', 'npcink-cloud-addon' ),
							'defaultContent' => __( 'WordPress AI can draft a short title from this sample paragraph about cloud-connected generation.', 'npcink-cloud-addon' ),
							'defaultPrompt' => __( 'A simple watercolor illustration of a notebook and a fountain pen on a wooden desk.', 'npcink-cloud-addon' ),
							'selectMedia' => __( 'Select from media library', 'npcink-cloud-addon' ),
							'mediaFrameTitle' => __( 'Select an image', 'npcink-cloud-addon' ),
							'mediaFrameButton' => __( 'Use this image', 'npcink-cloud-addon' ),
							'mediaUnavailable' => __( 'Media library is unavailable on this screen.', 'npcink-cloud-addon' ),
						),
					)
				);
			}
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
				<p class="description npcink-cloud-subtitle"><?php esc_html_e( 'Connect this site to Npcink Cloud to use AI features.', 'npcink-cloud-addon' ); ?></p>
				<?php self::render_admin_notice(); ?>

				<div data-npcink-connection-summary><?php if ( $is_verified ) { self::render_connection_summary( $settings, $state, $entitlement, $site_knowledge ); } ?></div>
				<?php if ( $is_verified ) : // Before verification the status card is the whole page; no single-tab bar. ?>
					<?php self::render_tab_navigation( $active_tab, $is_verified, $state ); ?>
				<?php elseif ( 'connection' === $active_tab ) : ?>
					<p class="npcink-cloud-back-link"><a href="<?php echo esc_url( self::tab_url( 'connect' ) ); ?>">&larr; <?php esc_html_e( 'Back', 'npcink-cloud-addon' ); ?></a></p>
				<?php endif; ?>

				<?php if ( 'connect' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2 class="screen-reader-text"><?php esc_html_e( 'Connect this site', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_cloud_authorization_panel( $settings, $state ); ?>
					</section>
				<?php elseif ( 'permissions' === $active_tab ) : ?>
					<?php self::render_overview_page( $settings, $state, $entitlement, $monitoring, $site_knowledge, $is_verified ); ?>
				<?php elseif ( 'checks' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2 class="screen-reader-text"><?php esc_html_e( 'Checks', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_diagnostic_checks( $settings, $state, $entitlement, $is_verified ); ?>
					</section>
				<?php elseif ( 'connection' === $active_tab ) : ?>
					<section class="npcink-cloud-section npcink-cloud-tab-panel">
						<h2 class="screen-reader-text"><?php esc_html_e( 'Connection', 'npcink-cloud-addon' ); ?></h2>
						<?php self::render_connection_page( $settings, $state, $is_verified, $entitlement, $site_knowledge ); ?>
					</section>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Returns escaped status markup for authenticated admin refreshes without Cloud requests.
		 * @return array<string,mixed>
		 */
		public static function get_admin_status_fragments( bool $include_knowledge = false ): array {
			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			$state = Npcink_Cloud_Addon_Settings::get_credential_state();
			$health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
			$entitlement = Npcink_Cloud_Entitlement_Summary::get_cached_summary();
			ob_start();
			self::render_connection_summary( $settings, $state, $entitlement, $health );
			$fragments = array( 'connection' => (string) ob_get_clean() );
			$fragments['service'] = self::get_current_service_health( ! empty( $state['verified'] ), $entitlement, $health );
			if ( $include_knowledge ) {
				ob_start();
				self::render_site_knowledge_summary( $health, $settings, ! empty( $state['verified'] ) );
				$fragments['knowledge'] = (string) ob_get_clean();
			}
			return $fragments;
		}

		/**
		 * Reads one sanitized admin query argument.
		 *
		 * Uses the request query bag rather than filter_input() so CLI/behavior
		 * tests that mutate the query array stay consistent with browser deep links.
		 *
		 * @param string $key Query key.
		 * @return string
		 */
		private static function request_query_arg( string $key ): string {
			$query = isset( $GLOBALS['_GET'] ) && is_array( $GLOBALS['_GET'] ) ? $GLOBALS['_GET'] : array();
			if ( ! isset( $query[ $key ] ) ) {
				return '';
			}

			return sanitize_key( wp_unslash( (string) $query[ $key ] ) );
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
			$requested = self::request_query_arg( 'tab' );
			$requested = self::normalize_legacy_tab_request( $requested, $is_verified );

			return isset( $tabs[ $requested ] ) ? $requested : $default;
		}

		/**
		 * Maps retired tab / advanced&view= / site_knowledge deep links onto the current top-level tabs.
		 *
		 * @param string $requested Raw tab slug.
		 * @param bool   $is_verified Whether the connector has verified credentials.
		 * @return string
		 */
		private static function normalize_legacy_tab_request( string $requested, bool $is_verified ): string {
			if ( '' === $requested ) {
				return '';
			}
			if ( in_array( $requested, array( 'details', 'status', 'site_knowledge' ), true ) ) {
				return 'permissions';
			}
			if ( in_array( $requested, array( 'runtime_runs', 'diagnostics' ), true ) ) {
				return 'checks';
			}
			if ( 'advanced' !== $requested ) {
				return $requested;
			}
			if ( ! $is_verified ) {
				return 'connection';
			}
			$view = self::diagnostics_view_from_request();
			if ( 'checks' === $view ) {
				return 'checks';
			}
			if ( 'connection' === $view ) {
				return 'connection';
			}

			// Legacy advanced&view=service (and bare advanced) → Overview (entitlement).
			return 'permissions';
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
					'permissions' => __( 'Overview', 'npcink-cloud-addon' ),
					'checks'      => __( 'Checks', 'npcink-cloud-addon' ),
					'connection'  => __( 'Connection', 'npcink-cloud-addon' ),
				);
			}

			$tabs = array(
				'connect' => __( 'Connect', 'npcink-cloud-addon' ),
			);
			if ( self::should_show_unverified_advanced_tab( $state ) ) {
				// "Connect" and "Connection" both read 连接 in Chinese; label the
				// unverified troubleshooting tab distinctly so only one 连接 shows.
				$tabs['connection'] = __( 'Advanced settings', 'npcink-cloud-addon' );
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
						Npcink_Cloud_Settings_Actions::page_url()
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
				Npcink_Cloud_Settings_Actions::page_url()
			);
		}

		/**
		 * Returns the requested diagnostics subview.
		 *
		 * @return string
		 */
		private static function diagnostics_view_from_request(): string {
			$raw = self::request_query_arg( 'view' );

			return in_array( $raw, array( 'service', 'checks', 'connection' ), true ) ? $raw : 'service';
		}

		/**
		 * Legacy advanced&view= labels retained for deep-link documentation/tests.
		 * Service details now live on Overview; checks/connection are top-level tabs.
		 *
		 * @return array<string,string>
		 */
		private static function advanced_section_labels(): array {
			return array(
				'service'    => __( 'Service details', 'npcink-cloud-addon' ),
				'checks'     => __( 'Checks', 'npcink-cloud-addon' ),
				'connection' => __( 'Connection', 'npcink-cloud-addon' ),
			);
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
			<section id="npcink-cloud-connection-summary" class="npcink-cloud-summary">
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
					<details class="npcink-cloud-summary__more">
						<summary><?php esc_html_e( 'More information', 'npcink-cloud-addon' ); ?></summary>
						<p class="description"><?php esc_html_e( 'Service address', 'npcink-cloud-addon' ); ?><?php esc_html_e( ': ', 'npcink-cloud-addon' ); ?><code><?php echo esc_html( self::format_setting_value( $display_base_url, __( 'Not set', 'npcink-cloud-addon' ) ) ); ?></code></p>
						<p class="description"><?php esc_html_e( 'Last checked', 'npcink-cloud-addon' ); ?><?php esc_html_e( ': ', 'npcink-cloud-addon' ); ?><?php echo esc_html( self::format_datetime_value( (string) $settings['verified_at'], __( 'Never', 'npcink-cloud-addon' ) ) ); ?></p>
						<?php self::render_reverify_form( $settings, __( 'Check again', 'npcink-cloud-addon' ) ); ?>
					</details>
				<?php endif; ?>
			</section>
			<?php
		}

		/**
		 * Renders top-level local permission switches.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_local_permissions( array $settings, bool $is_verified ): void {
			if ( ! $is_verified ) {
				return;
			}

			$feedback = Npcink_Cloud_Settings_Actions::get_local_permission_feedback();
			?>
			<section id="npcink-cloud-feature-settings" class="npcink-cloud-local-permissions" aria-labelledby="npcink-cloud-local-permissions-title">
				<div class="npcink-cloud-section-title npcink-cloud-local-permissions__header">
					<h2 id="npcink-cloud-local-permissions-title"><?php esc_html_e( 'Features', 'npcink-cloud-addon' ); ?></h2>
				</div>
				<div class="npcink-cloud-local-permissions__list">
					<?php $definitions = Npcink_Cloud_Settings_Actions::get_local_permission_definitions(); ?>
					<?php self::render_local_permission_switch( 'site_knowledge_delivery_enabled', $definitions['site_knowledge_delivery_enabled'], ! empty( $settings['site_knowledge_delivery_enabled'] ), $feedback ); ?>
				</div>
				<div class="npcink-cloud-local-permissions__privacy">
					<h3 class="npcink-cloud-local-permissions__subtitle"><?php esc_html_e( 'Privacy settings', 'npcink-cloud-addon' ); ?></h3>
					<?php self::render_local_permission_switch( 'monitoring_enabled', $definitions['monitoring_enabled'], ! empty( $settings['monitoring_enabled'] ), $feedback ); ?>
				</div>
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
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( Npcink_Cloud_Settings_Actions::ACTION_UPDATE_LOCAL_PERMISSION ) ); ?>" />
				<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_UPDATE_LOCAL_PERMISSION ); ?>" />
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
						<?php if ( '' !== trim( (string) ( $definition['description'] ?? '' ) ) ) : ?><span class="description npcink-cloud-local-permission__desc"><?php echo esc_html( (string) $definition['description'] ); ?></span><?php endif; ?>
					</span>
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
		 * Cloud Portal URL for the connected (or effective) base URL.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return string
		 */
		private static function portal_url( array $settings ): string {
			return untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal';
		}

		/**
		 * Renders the single Portal exit: text button when healthy, icon when an alert CTA is present.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param string              $variant  "button" (labeled) or "icon" (dashicons-external).
		 * @return void
		 */
		private static function render_portal_link( array $settings, string $variant = 'button' ): void {
			$url   = self::portal_url( $settings );
			$label = __( 'Open Npcink Cloud', 'npcink-cloud-addon' );
			if ( 'icon' === $variant ) {
				?>
				<a
					class="button button-secondary npcink-cloud-icon-button"
					href="<?php echo esc_url( $url ); ?>"
					target="_blank"
					rel="noopener noreferrer"
					aria-label="<?php echo esc_attr( $label ); ?>"
					title="<?php echo esc_attr( $label ); ?>"
				><span class="dashicons dashicons-external" aria-hidden="true"></span></a>
				<?php
				return;
			}
			?>
			<a class="button button-secondary" href="<?php echo esc_url( $url ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $label ); ?></a>
			<?php
		}

		/**
		 * Renders connection-level actions for the top summary banner.
		 *
		 * Product Site Knowledge recovery stays on Overview;
		 * this surface keeps connection CTAs only.
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
					<a class="button button-primary" href="<?php echo esc_url( self::portal_url( $settings ) ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Npcink Cloud', 'npcink-cloud-addon' ); ?></a>
				<?php elseif ( $is_verified ) : ?>
					<?php if ( $service_needs_attention ) : ?>
						<?php self::render_reverify_form( $settings ); ?>
					<?php endif; ?>
					<?php self::render_portal_link( $settings, 'icon' ); ?>
				<?php elseif ( '' !== (string) ( $settings['last_verification_error'] ?? '' ) ) : ?>
					<?php self::render_reverify_form( $settings, __( 'Check again', 'npcink-cloud-addon' ), 'button button-primary' ); ?>
				<?php else : ?>
					<?php self::render_authorization_form( __( 'Reconnect', 'npcink-cloud-addon' ), 'button button-primary' ); ?>
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

			$recovery_in_progress = self::is_site_knowledge_recovery_in_progress( $site_knowledge );
			// Dropped content is an Overview notice — keep this banner for connection/auth only.
			if ( ! $recovery_in_progress && ( '' !== (string) ( $site_knowledge['last_delivery_error'] ?? '' ) || '' !== (string) ( $site_knowledge['last_error_code'] ?? '' ) ) ) {
				return array(
					'severity' => 'warning',
					'label' => __( 'Needs attention', 'npcink-cloud-addon' ),
					'message' => __( 'A recent Site Knowledge delivery could not reach or authenticate with Cloud. Re-check the connection or open Cloud for service detail.', 'npcink-cloud-addon' ),
				);
			}

			$entitlement_state = sanitize_key( (string) ( $entitlement['state'] ?? '' ) );
			if ( ! empty( $entitlement['stale'] ) ) {
				return array(
					'severity' => 'warning',
					'label' => __( 'Not recently checked', 'npcink-cloud-addon' ),
					'message' => __( 'The cached Cloud read is out of date. Re-check the connection to confirm current service status.', 'npcink-cloud-addon' ),
				);
			}
			if ( ! empty( $entitlement['available'] ) ) {
				return array( 'severity' => 'ok', 'label' => __( 'Recently reachable', 'npcink-cloud-addon' ), 'message' => __( 'A cached signed Cloud read is available.', 'npcink-cloud-addon' ) );
			}
			if ( in_array( $entitlement_state, array( 'unavailable', 'refreshing' ), true ) ) {
				return array( 'severity' => 'warning', 'label' => __( 'Temporarily unavailable', 'npcink-cloud-addon' ), 'message' => __( 'The latest signed Cloud read did not complete. Re-check the connection or try again later.', 'npcink-cloud-addon' ) );
			}

			return array( 'severity' => 'pending', 'label' => __( 'Not recently checked', 'npcink-cloud-addon' ), 'message' => __( 'Run a connection check when you need current service confirmation.', 'npcink-cloud-addon' ) );
		}

		/** Keeps historical drops in diagnostics while their recovery is being delivered. */
		private static function is_site_knowledge_recovery_in_progress( array $site_knowledge ): bool {
			$maintenance = (string) ( $site_knowledge['maintenance_status'] ?? '' );
			$operation = (string) ( $site_knowledge['maintenance_operation'] ?? 'rebuild' );
			$maintenance_active = array_key_exists( 'maintenance_active', $site_knowledge )
				? ! empty( $site_knowledge['maintenance_active'] )
				: in_array( $maintenance, array( 'queued', 'delivering', 'retrying' ), true );
			return 0 < absint( $site_knowledge['dropped_count'] ?? 0 )
				&& $maintenance_active
				&& empty( $site_knowledge['wp_cron_disabled'] )
				&& empty( $site_knowledge['reconcile_overdue'] )
				&& in_array( $operation, array( 'start', 'rebuild' ), true );
		}

		/**
		 * Renders low-frequency connection management actions.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return void
		 */
		private static function render_connection_management( array $settings, bool $service_needs_attention = false, bool $close_card = false ): void {
			if ( empty( $settings['base_url'] ) ) {
				return;
			}
			?>
			<h3 class="screen-reader-text"><?php esc_html_e( 'Connection management', 'npcink-cloud-addon' ); ?></h3>
			<div class="npcink-cloud-summary__actions npcink-cloud-summary__actions--start">
				<?php if ( Npcink_Cloud_Addon_Settings::is_verified() && $service_needs_attention ) : ?>
					<?php self::render_reverify_form( $settings, __( 'Check connection', 'npcink-cloud-addon' ) ); ?>
				<?php endif; ?>
				<?php self::render_change_account_form( 'button button-secondary' ); ?>
			</div>
			<p class="description"><?php esc_html_e( 'Switching accounts disconnects this site first.', 'npcink-cloud-addon' ); ?></p>
			<?php if ( $close_card ) : ?>
				</div>
				<?php self::render_connection_more( $settings ); ?>
			<?php endif; ?>
			<div class="npcink-cloud-connection-danger">
				<h4><?php esc_html_e( 'Disconnect this site', 'npcink-cloud-addon' ); ?></h4>
				<p class="description"><?php esc_html_e( 'AI features will stop on this site. Your data stays in Npcink Cloud.', 'npcink-cloud-addon' ); ?></p>
				<?php self::render_disconnect_form(); ?>
			</div>
			<?php
		}

		/**
		 * Renders a non-secret projection of the current Cloud connection identity.
		 *
		 * Shows status, endpoint, last verified time, and plan label only.
		 * Does not expose site_id, key_id, or secret identifiers.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param array<string,mixed> $entitlement Cached entitlement summary.
		 * @return void
		 */
		private static function render_connection_identity( array $settings, array $state, array $entitlement ): void {
			$configured = ! empty( $state['configured'] );
			if ( ( ! $configured && empty( $settings['base_url'] ) ) || ( $configured && empty( $state['verified'] ) ) ) {
				return; // Top summary already shows the single status line.
			}

			$base_url = $configured
				? (string) ( $settings['base_url'] ?? '' )
				: Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings );
			$verified_at = self::format_datetime_value( (string) ( $settings['verified_at'] ?? '' ), __( 'Never', 'npcink-cloud-addon' ) );
			$package_label = self::format_overview_package_label( $entitlement );
			?>
			<section class="npcink-cloud-connection-identity" aria-label="<?php esc_attr_e( 'Connected Cloud identity', 'npcink-cloud-addon' ); ?>">
				<?php self::render_status_line( (string) ( $state['label'] ?? '' ), 'ok' ); ?>
				<p class="npcink-cloud-card__meta">
					<?php esc_html_e( 'Last checked', 'npcink-cloud-addon' ); ?><?php esc_html_e( ': ', 'npcink-cloud-addon' ); ?><?php echo esc_html( $verified_at ); ?>
					<?php if ( '' !== $package_label ) : ?>
						&middot; <?php esc_html_e( 'Plan', 'npcink-cloud-addon' ); ?><?php esc_html_e( ': ', 'npcink-cloud-addon' ); ?><?php echo esc_html( $package_label ); ?>
					<?php endif; ?>
				</p>
			</section>
			<?php
		}

		/**
		 * Renders the folded non-essential connection details below the card.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return void
		 */
		private static function render_connection_more( array $settings ): void {
			?>
			<details class="npcink-cloud-connection-identity__more npcink-cloud-fold">
				<summary><?php esc_html_e( 'More information', 'npcink-cloud-addon' ); ?></summary>
				<p class="description"><?php esc_html_e( 'Free credits belong to the account you chose when connecting, not to this site.', 'npcink-cloud-addon' ); ?></p>
				<p class="description"><?php esc_html_e( 'Service address', 'npcink-cloud-addon' ); ?><?php esc_html_e( ': ', 'npcink-cloud-addon' ); ?><code><?php echo esc_html( self::format_setting_value( (string) ( $settings['base_url'] ?? '' ), __( 'Not set', 'npcink-cloud-addon' ) ) ); ?></code></p>
				<?php self::render_portal_link( $settings ); ?>
			</details>
			<?php
		}

		/**
		 * Renders the single status sentence used at the top of each card.
		 *
		 * @param string $text Status text.
		 * @param string $tone ok|warn|bad|muted.
		 * @return void
		 */
		private static function render_status_line( string $text, string $tone ): void {
			?>
			<p class="npcink-cloud-card__status"><span class="npcink-cloud-status-dot npcink-cloud-status-dot--<?php echo esc_attr( $tone ); ?>" aria-hidden="true"></span><strong><?php echo esc_html( $text ); ?></strong></p>
			<?php
		}

		/**
		 * Renders the change-account action that clears local credentials then starts Cloud auth.
		 *
		 * @param string $class_name Button classes.
		 * @return void
		 */
		private static function render_change_account_form( string $class_name ): void {
			?>
			<form class="npcink-cloud-change-account-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( Npcink_Cloud_Settings_Actions::ACTION_CHANGE_ACCOUNT ) ); ?>" />
				<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_CHANGE_ACCOUNT ); ?>" />
				<input type="hidden" name="js_confirmation" value="0" />
				<button
					type="submit"
					class="<?php echo esc_attr( $class_name ); ?>"
					onclick="if (!window.confirm('<?php echo esc_js( __( 'Change account? This site will be disconnected first, then Npcink Cloud opens so you can pick an account. Cancel keeps everything as it is.', 'npcink-cloud-addon' ) ); ?>')) { return false; } this.form.elements.namedItem('js_confirmation').value = '1'; return true;"
				>
					<?php esc_html_e( 'Change account', 'npcink-cloud-addon' ); ?>
				</button>
				<noscript>
					<p class="description"><strong><?php esc_html_e( 'Please confirm before changing the account.', 'npcink-cloud-addon' ); ?></strong></p>
					<label>
						<input type="checkbox" name="noscript_confirmation" value="1" required />
						<?php esc_html_e( 'I understand this site will be disconnected first.', 'npcink-cloud-addon' ); ?>
					</label>
				</noscript>
			</form>
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
			<details class="npcink-cloud-advanced-detail npcink-cloud-manual-fallback npcink-cloud-fold npcink-cloud-fold--bottom">
				<summary><?php esc_html_e( 'Advanced settings', 'npcink-cloud-addon' ); ?> <small><?php esc_html_e( 'Usually not needed', 'npcink-cloud-addon' ); ?></small></summary>
				<div class="npcink-cloud-advanced-detail__body">
					<p class="description"><?php esc_html_e( 'Only use this if support asks you to, or if connecting through Npcink Cloud does not work.', 'npcink-cloud-addon' ); ?></p>
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
		private static function render_reverify_form( array $settings, string $label = '', string $class_name = 'button button-secondary' ): void {
			?>
			<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( Npcink_Cloud_Settings_Actions::ACTION_SAVE ) ); ?>" />
				<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_SAVE ); ?>" />
				<input type="hidden" name="base_url" value="<?php echo esc_attr( (string) $settings['base_url'] ); ?>" />
				<input type="hidden" name="api_key" value="" />
				<input type="hidden" name="timeout" value="<?php echo esc_attr( (string) $settings['timeout'] ); ?>" />
				<input type="hidden" name="monitoring_enabled" value="<?php echo esc_attr( ! empty( $settings['monitoring_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="site_knowledge_delivery_enabled" value="<?php echo esc_attr( ! empty( $settings['site_knowledge_delivery_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="site_knowledge_generation_reference_enabled" value="<?php echo esc_attr( ! empty( $settings['site_knowledge_generation_reference_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="wordpress_ai_connector_enabled" value="<?php echo esc_attr( ! empty( $settings['wordpress_ai_connector_enabled'] ) ? '1' : '0' ); ?>" />
				<button type="submit" class="<?php echo esc_attr( $class_name ); ?>"><?php echo esc_html( '' !== $label ? $label : __( 'Check connection', 'npcink-cloud-addon' ) ); ?></button>
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
				<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( Npcink_Cloud_Settings_Actions::ACTION_DISCONNECT ) ); ?>" />
				<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_DISCONNECT ); ?>" />
				<input type="hidden" name="js_confirmation" value="0" />
				<button
					type="submit"
					class="button button-secondary npcink-cloud-button-danger"
					onclick="if (!window.confirm('<?php echo esc_js( __( 'Disconnect this site? AI features will stop here. Your data stays in Npcink Cloud.', 'npcink-cloud-addon' ) ); ?>')) { return false; } this.form.elements.namedItem('js_confirmation').value = '1'; return true;"
				>
					<?php esc_html_e( 'Disconnect this site', 'npcink-cloud-addon' ); ?>
				</button>
				<noscript>
					<p class="description"><strong><?php esc_html_e( 'Confirm local disconnect before submitting.', 'npcink-cloud-addon' ); ?></strong></p>
					<label>
						<input type="checkbox" name="noscript_confirmation" value="1" required />
						<?php esc_html_e( 'I understand that this clears only the WordPress-side connection and addon buffers.', 'npcink-cloud-addon' ); ?>
					</label>
				</noscript>
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
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="max-width: 720px;">
				<?php wp_nonce_field( Npcink_Cloud_Settings_Actions::ACTION_SAVE ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_SAVE ); ?>" />
				<input type="hidden" name="monitoring_enabled" value="<?php echo esc_attr( ! empty( $settings['monitoring_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="site_knowledge_delivery_enabled" value="<?php echo esc_attr( ! empty( $settings['site_knowledge_delivery_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="site_knowledge_generation_reference_enabled" value="<?php echo esc_attr( ! empty( $settings['site_knowledge_generation_reference_enabled'] ) ? '1' : '0' ); ?>" />
				<input type="hidden" name="wordpress_ai_connector_enabled" value="<?php echo esc_attr( ! empty( $settings['wordpress_ai_connector_enabled'] ) ? '1' : '0' ); ?>" />
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row">
								<label for="npcink-cloud-base-url"><?php esc_html_e( 'Service address', 'npcink-cloud-addon' ); ?></label>
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
								<label for="npcink-cloud-api-key"><?php esc_html_e( 'Recovery code', 'npcink-cloud-addon' ); ?></label>
							</th>
							<td>
								<input
									type="password"
									class="regular-text code"
									id="npcink-cloud-api-key"
									name="api_key"
									value=""
									autocomplete="new-password"
									placeholder="<?php echo esc_attr__( 'Paste the recovery code from Npcink Cloud', 'npcink-cloud-addon' ); ?>"
								/>
								<p class="description"><?php esc_html_e( 'Leave blank to keep it unchanged.', 'npcink-cloud-addon' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="npcink-cloud-timeout"><?php esc_html_e( 'Wait time', 'npcink-cloud-addon' ); ?></label>
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
				<?php submit_button( __( 'Save', 'npcink-cloud-addon' ), 'secondary' ); ?>
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
			$monitoring_needs_attention = ! empty( $monitoring['enabled'] ) && '' !== (string) ( $monitoring['last_upload_error'] ?? '' );
			$entitlement_state = sanitize_key( (string) ( $entitlement['state'] ?? '' ) );
			$show_entitlement_retry = in_array( $entitlement_state, array( 'unavailable', 'refreshing' ), true );
			$service_health = self::get_current_service_health( $is_verified, $entitlement, $site_knowledge );
			// Connection/auth SK failures stay on the top summary; product drops use Overview notice only.
			$sk_connection_on_summary = 'warning' === (string) ( $service_health['severity'] ?? '' )
				&& ( '' !== (string) ( $site_knowledge['last_delivery_error'] ?? '' ) || '' !== (string) ( $site_knowledge['last_error_code'] ?? '' ) );
			$site_knowledge_needs_attention = ! self::is_site_knowledge_recovery_in_progress( $site_knowledge )
				&& (
					0 < absint( $site_knowledge['dropped_count'] ?? 0 )
					|| ( ! $sk_connection_on_summary && ( '' !== (string) ( $site_knowledge['last_delivery_error'] ?? '' ) || '' !== (string) ( $site_knowledge['last_error_code'] ?? '' ) ) )
				);
			$entitlement_metrics = self::get_overview_entitlement_metrics( $entitlement );
			$credit_metric = is_array( $entitlement_metrics['credits'] ?? null ) ? $entitlement_metrics['credits'] : array();
			$runtime_metric = is_array( $entitlement_metrics['runtime'] ?? null ) ? $entitlement_metrics['runtime'] : array();
			$site_knowledge_delivery_enabled = ! empty( $site_knowledge['delivery_enabled'] );
			$site_knowledge_usage = $site_knowledge_delivery_enabled
				? self::get_site_knowledge_usage_projection( Npcink_Cloud_Site_Knowledge_Runtime_Bridge::get_cached_status_summary() )
				: self::get_site_knowledge_usage_projection( array() );
			$show_site_knowledge_retry = in_array( (string) ( $site_knowledge_usage['state'] ?? '' ), array( 'unavailable', 'refreshing' ), true );
			$media_capacity = is_array( $entitlement['media_image_capacity'] ?? null ) ? $entitlement['media_image_capacity'] : array();
			$media_capacity_available = ! empty( $media_capacity['available'] );
			$media_capacity_limit = max( 0, (float) ( $media_capacity['limit'] ?? 0 ) );
			$media_capacity_remaining = max( 0, (float) ( $media_capacity['remaining'] ?? 0 ) );
			$media_capacity_unlimited = $media_capacity_available && $media_capacity_limit <= 0;
			$media_capacity_percent = $media_capacity_available && ! $media_capacity_unlimited
				? (int) round( min( 100, ( $media_capacity_remaining / $media_capacity_limit ) * 100 ) )
				: 0;
			?>
			<section class="npcink-cloud-section npcink-cloud-tab-panel npcink-cloud-overview">
				<h2 class="screen-reader-text"><?php esc_html_e( 'Overview', 'npcink-cloud-addon' ); ?></h2>
				<?php $topup_url = (string) ( is_array( $entitlement['links'] ?? null ) ? ( $entitlement['links']['billing_url'] ?? '' ) : '' ); ?>
				<?php if ( ! empty( $credit_metric['low'] ) ) : ?>
					<div class="card npcink-cloud-card npcink-cloud-overview-status npcink-cloud-low-balance">
						<?php self::render_status_line( __( 'AI credits are running low.', 'npcink-cloud-addon' ), 'warn' ); ?>
						<?php if ( '' !== $topup_url ) : ?>
							<p class="npcink-cloud-low-balance__action"><a href="<?php echo esc_url( $topup_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Top up on Npcink Cloud', 'npcink-cloud-addon' ); ?></a></p>
						<?php endif; ?>
					</div>
				<?php else : ?>
				<div class="card npcink-cloud-card npcink-cloud-overview-status"><?php self::render_status_line( __( 'Connected. AI features are ready to use.', 'npcink-cloud-addon' ), 'ok' ); ?></div>
				<?php endif; ?>
				<?php self::render_generation_test_links( true ); ?>
				<div class="npcink-cloud-overview-entitlements" aria-labelledby="npcink-cloud-entitlements-title">
					<div class="npcink-cloud-section-title">
						<h2 id="npcink-cloud-entitlements-title"><?php esc_html_e( 'Plan and credits', 'npcink-cloud-addon' ); ?></h2>
					</div>
					<div class="npcink-cloud-overview-panel">
					<?php
					// Keep entitlement refresh hooks for admin-entitlement.js without showing
					// connection title, Portal icon, Connected badge, or plan chip.
					?>
					<?php
					// Keep hooks for admin-entitlement.js; surface Retry when the signed read failed.
					$entitlement_attention = $show_entitlement_retry;
					?>
					<div class="npcink-cloud-entitlement<?php echo $entitlement_attention ? ' npcink-cloud-entitlement--attention' : ' screen-reader-text'; ?>" data-npcink-entitlement-state="<?php echo esc_attr( $entitlement_state ); ?>">
						<span data-npcink-entitlement-summary aria-live="polite"><?php echo esc_html( self::format_overview_entitlement( $entitlement, $is_verified ) ); ?></span>
						<span class="spinner npcink-cloud-entitlement__spinner" aria-hidden="true"></span>
						<button type="button" class="button-link npcink-cloud-icon-button npcink-cloud-entitlement__retry" data-npcink-entitlement-retry aria-label="<?php echo esc_attr__( 'Retry', 'npcink-cloud-addon' ); ?>" title="<?php echo esc_attr__( 'Retry', 'npcink-cloud-addon' ); ?>"<?php echo $show_entitlement_retry ? '' : ' hidden'; ?>><span class="dashicons dashicons-update" aria-hidden="true"></span></button>
						<?php if ( $entitlement_attention ) : ?>
							<a class="npcink-cloud-entitlement__fix-link" href="#npcink-cloud-connection-summary"><?php esc_html_e( 'Check connection', 'npcink-cloud-addon' ); ?></a>
						<?php endif; ?>
					</div>
					<div class="npcink-cloud-overview-panel__metrics">
						<div class="npcink-cloud-stat" data-npcink-entitlement-metric="credits"<?php echo empty( $credit_metric['available'] ) ? ' hidden' : ''; ?>>
							<span class="npcink-cloud-stat__label"><?php esc_html_e( 'Available AI credits', 'npcink-cloud-addon' ); ?></span>
							<span class="npcink-cloud-stat__value npcink-cloud-entitlement-metric npcink-cloud-metric-value" data-npcink-entitlement-metric-value<?php echo ! empty( $credit_metric['tooltip'] ) ? ' title="' . esc_attr( (string) $credit_metric['tooltip'] ) . '"' : ''; ?>><?php echo esc_html( (string) ( $credit_metric['value_label'] ?? $credit_metric['label'] ?? '' ) ); ?></span>
							<span class="npcink-cloud-metric-status" data-npcink-entitlement-metric-status hidden></span>
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
						</div>
						<?php if ( $site_knowledge_delivery_enabled ) : ?>
						<div class="npcink-cloud-stat" data-npcink-site-knowledge-usage-row>
							<span class="npcink-cloud-stat__label"><?php esc_html_e( 'Available knowledge documents', 'npcink-cloud-addon' ); ?></span>
							<div
								class="npcink-cloud-site-knowledge-usage"
								data-npcink-site-knowledge-usage
								data-npcink-site-knowledge-state="<?php echo esc_attr( (string) ( $site_knowledge_usage['state'] ?? 'not_refreshed' ) ); ?>"
								<?php echo ! empty( $site_knowledge_usage['tooltip'] ) ? ' title="' . esc_attr( (string) $site_knowledge_usage['tooltip'] ) . '"' : ''; ?>
							>
								<div class="npcink-cloud-site-knowledge-usage__main">
									<span class="npcink-cloud-stat__value npcink-cloud-metric-value" data-npcink-site-knowledge-usage-value aria-live="polite"><?php echo esc_html( (string) ( $site_knowledge_usage['value_label'] ?? $site_knowledge_usage['label'] ?? __( 'Loading Site Knowledge usage…', 'npcink-cloud-addon' ) ) ); ?></span>
									<span class="npcink-cloud-metric-status" data-npcink-site-knowledge-usage-status hidden></span>
									<span
										class="npcink-cloud-metric-actions"
										data-npcink-site-knowledge-actions
										<?php echo $show_site_knowledge_retry || in_array( (string) ( $site_knowledge_usage['state'] ?? '' ), array( 'not_refreshed', 'stale' ), true ) ? '' : ' hidden'; ?>
									>
										<span class="spinner npcink-cloud-site-knowledge-usage__spinner" aria-hidden="true"></span>
										<button type="button" class="button-link npcink-cloud-icon-button npcink-cloud-site-knowledge-usage__retry" data-npcink-site-knowledge-retry aria-label="<?php echo esc_attr__( 'Retry', 'npcink-cloud-addon' ); ?>" title="<?php echo esc_attr__( 'Retry', 'npcink-cloud-addon' ); ?>"<?php echo $show_site_knowledge_retry ? '' : ' hidden'; ?>><span class="dashicons dashicons-update" aria-hidden="true"></span></button>
									</span>
								</div>
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
							</div>
						</div>
						<?php endif; ?>
						<div class="npcink-cloud-stat" data-npcink-media-image-capacity>
							<span class="npcink-cloud-stat__label"><?php esc_html_e( 'Available images', 'npcink-cloud-addon' ); ?></span>
							<span class="npcink-cloud-stat__value npcink-cloud-metric-value"><?php echo $media_capacity_unlimited ? esc_html__( 'Unlimited', 'npcink-cloud-addon' ) : ( $media_capacity_available ? esc_html( sprintf(
								/* translators: 1: remaining image capacity, 2: image capacity limit. */
								__( '%1$s / %2$s', 'npcink-cloud-addon' ),
								self::format_entitlement_number( $media_capacity_remaining ),
								self::format_entitlement_number( $media_capacity_limit )
							) ) : esc_html__( 'Not available yet', 'npcink-cloud-addon' ) ); ?></span>
							<?php if ( $media_capacity_available && ! $media_capacity_unlimited ) : ?>
								<span class="npcink-cloud-segmented-progress npcink-cloud-entitlement-progress" role="progressbar" aria-label="<?php esc_attr_e( 'Remaining image recognition capacity percentage', 'npcink-cloud-addon' ); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo esc_attr( (string) $media_capacity_percent ); ?>" style="--npcink-cloud-progress: <?php echo esc_attr( (string) $media_capacity_percent ); ?>%;"></span>
							<?php endif; ?>
						</div>
						<div class="npcink-cloud-stat" data-npcink-entitlement-metric="runtime"<?php echo empty( $runtime_metric['available'] ) ? ' hidden' : ''; ?>>
							<span class="npcink-cloud-stat__label"><?php esc_html_e( 'Runtime allowance', 'npcink-cloud-addon' ); ?></span>
							<span class="npcink-cloud-stat__value" data-npcink-entitlement-metric-label><?php echo esc_html( (string) ( $runtime_metric['label'] ?? '' ) ); ?></span>
						</div>
					</div>
					<?php if ( '' !== (string) ( $credit_metric['usage_line'] ?? '' ) ) : ?>
						<p class="description npcink-cloud-usage-line"><?php echo esc_html( (string) $credit_metric['usage_line'] ); ?></p>
					<?php endif; ?>
					<?php if ( $monitoring_needs_attention || $site_knowledge_needs_attention ) : ?>
					<div class="npcink-cloud-overview-panel__notices">
						<?php if ( $monitoring_needs_attention ) : ?>
							<p class="npcink-cloud-overview-notice npcink-cloud-overview-notice--warning"><strong><?php esc_html_e( 'Monitoring needs attention', 'npcink-cloud-addon' ); ?></strong> <?php echo esc_html( self::format_monitoring_overview( $monitoring ) ); ?> <a href="#npcink-cloud-monitoring-state"><?php esc_html_e( 'View upload detail', 'npcink-cloud-addon' ); ?></a></p>
						<?php endif; ?>
						<?php if ( $site_knowledge_needs_attention ) : ?>
							<p class="npcink-cloud-overview-notice npcink-cloud-overview-notice--warning"><strong><?php esc_html_e( 'Site Knowledge needs attention', 'npcink-cloud-addon' ); ?></strong> <?php echo esc_html( self::format_site_knowledge_overview( $site_knowledge ) ); ?> <a href="#npcink-cloud-site-knowledge"><?php esc_html_e( 'View Site Knowledge status', 'npcink-cloud-addon' ); ?></a></p>
						<?php endif; ?>
					</div>
					<?php endif; ?>
					</div>
					<?php self::render_status_account_usage( $entitlement ); ?>
				</div>
				<?php self::render_status_monitoring_quality( $monitoring ); ?>
				<?php self::render_site_knowledge_summary( $site_knowledge, $settings, $is_verified ); ?>
				<?php self::render_local_permissions( $settings, $is_verified ); ?>
			</section>
			<?php self::render_monitoring_consent_prompt( $settings, $is_verified ); ?>
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
			if ( ! $is_verified || ! empty( $settings['monitoring_enabled'] ) || ! Npcink_Cloud_Settings_Actions::has_monitoring_consent_prompt() ) {
				return;
			}
			?>
			<dialog class="npcink-cloud-monitoring-consent" open role="dialog" aria-modal="true" aria-labelledby="npcink-cloud-monitoring-consent-title">
				<div class="npcink-cloud-monitoring-consent__body">
					<h2 id="npcink-cloud-monitoring-consent-title"><?php esc_html_e( 'Help us improve?', 'npcink-cloud-addon' ); ?></h2>
					<p><?php esc_html_e( 'Share anonymous error reports so we can fix problems faster.', 'npcink-cloud-addon' ); ?></p>
					<details class="npcink-cloud-fold"><summary><?php esc_html_e( 'What is sent', 'npcink-cloud-addon' ); ?></summary><p class="description"><?php esc_html_e( 'We only send which feature ran, whether it worked, how long it took, and an error code. We never send your content, users, links, or passwords.', 'npcink-cloud-addon' ); ?></p></details>
					<div class="npcink-cloud-monitoring-consent__actions">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_UPDATE_LOCAL_PERMISSION ); ?>" />
							<input type="hidden" name="permission" value="monitoring_enabled" />
							<input type="hidden" name="enabled" value="1" />
							<?php wp_nonce_field( Npcink_Cloud_Settings_Actions::ACTION_UPDATE_LOCAL_PERMISSION ); ?>
							<button type="submit" class="button button-primary"><?php esc_html_e( 'Yes, share', 'npcink-cloud-addon' ); ?></button>
						</form>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_DISMISS_MONITORING_PROMPT ); ?>" />
							<?php wp_nonce_field( Npcink_Cloud_Settings_Actions::ACTION_DISMISS_MONITORING_PROMPT ); ?>
							<button type="submit" class="button button-secondary"><?php esc_html_e( 'Not now', 'npcink-cloud-addon' ); ?></button>
						</form>
					</div>
				</div>
			</dialog>
			<?php
		}

		/**
		 * Renders the Connection top-level tab (management + optional manual recovery).
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @param array<string,mixed> $state Credential state.
		 * @param bool                $is_verified Whether the connector has verified credentials.
		 * @return void
		 */
		private static function render_connection_page( array $settings, array $state, bool $is_verified, array $entitlement = array(), array $site_knowledge = array() ): void {
			self::render_advanced_information( $state );
			$service_health = self::get_current_service_health( $is_verified, $entitlement, $site_knowledge );
			$service_needs_attention = $is_verified && 'warning' === (string) ( $service_health['severity'] ?? '' );
			$in_card = $is_verified && ! empty( $settings['base_url'] );
			if ( $in_card ) {
				echo '<div class="card npcink-cloud-card">';
			}
			self::render_connection_identity( $settings, $state, $entitlement );
			self::render_connection_management( $settings, $service_needs_attention, $in_card );
			self::render_manual_connection_disclosure( $settings );
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
			$readiness = Npcink_Cloud_Settings_Actions::get_manual_readiness_result();
			$site_knowledge = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
			$site_knowledge_needs_attention = ! empty( $site_knowledge['wp_cron_disabled'] )
				|| '' !== (string) ( $site_knowledge['last_delivery_error'] ?? '' )
				|| '' !== (string) ( $site_knowledge['last_error_code'] ?? '' )
				|| 0 < absint( $site_knowledge['dropped_count'] ?? 0 );
			$connection_detail = sprintf(
				/* translators: 1: last verification time, 2: signed read status. */
				__( 'Last checked: %1$s · Signed read: %2$s', 'npcink-cloud-addon' ),
				self::format_datetime_value( (string) ( $settings['verified_at'] ?? '' ), __( 'Never', 'npcink-cloud-addon' ) ),
				self::format_entitlement_availability( $entitlement, $is_verified )
			);
			$credentials_detail = self::format_setting_value( (string) ( $settings['base_url'] ?? '' ), __( 'Not set', 'npcink-cloud-addon' ) );
			$credentials_ok = ! empty( $state['configured'] );
			$connection_ok = ! empty( $state['verified'] );
			$portal_status_url = untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal';
			$readiness_status = sanitize_key( (string) ( $readiness['bounded_status'] ?? $readiness['status'] ?? '' ) );
			$readiness_failed = ! empty( $readiness ) && 'ready' !== $readiness_status;
			// Plain status projected only from Cloud-reported state already stored locally.
			if ( ! $credentials_ok ) {
				$simple_tone = 'warn';
				$simple_message = __( 'This site is not connected yet.', 'npcink-cloud-addon' );
				$simple_hint = __( 'Connect it to Npcink Cloud to use AI features.', 'npcink-cloud-addon' );
			} elseif ( ! $connection_ok ) {
				$simple_tone = 'warn';
				$simple_message = __( 'The connection needs to be confirmed again.', 'npcink-cloud-addon' );
				$simple_hint = __( 'Click the button below and confirm in Npcink Cloud to restore it.', 'npcink-cloud-addon' );
			} elseif ( $readiness_failed || 'unavailable' === sanitize_key( (string) ( $entitlement['state'] ?? '' ) ) ) {
				$simple_tone = 'bad';
				$simple_message = __( 'The service has a problem we cannot fix automatically.', 'npcink-cloud-addon' );
				$simple_hint = __( 'Please contact support and include the information under More information.', 'npcink-cloud-addon' );
			} else {
				$simple_tone = 'ok';
				$simple_message = __( 'Everything is working. AI features are ready to use.', 'npcink-cloud-addon' );
				$simple_hint = sprintf(
					/* translators: %s: last check time. */
					__( 'Last checked: %s', 'npcink-cloud-addon' ),
					self::format_datetime_value( (string) ( $settings['verified_at'] ?? '' ), __( 'Never', 'npcink-cloud-addon' ) )
				);
			}
			?>
			<div class="card npcink-cloud-card npcink-cloud-checks-simple npcink-cloud-checks-simple--<?php echo esc_attr( $simple_tone ); ?>">
				<?php self::render_status_line( $simple_message, $simple_tone ); ?>
				<div class="npcink-cloud-summary__actions npcink-cloud-summary__actions--start">
					<?php if ( 'warn' === $simple_tone ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( self::tab_url( 'connection' ) ); ?>"><?php esc_html_e( 'Reconnect', 'npcink-cloud-addon' ); ?></a>
					<?php elseif ( 'bad' === $simple_tone ) : ?>
						<a class="button button-primary" href="<?php echo esc_url( $portal_status_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Contact support', 'npcink-cloud-addon' ); ?></a>
					<?php else : ?>
						<?php self::render_reverify_form( $settings, __( 'Check again', 'npcink-cloud-addon' ) ); ?>
					<?php endif; ?>
				</div>
				<p class="description"><?php echo esc_html( $simple_hint ); ?></p>
			</div>
			<details class="npcink-cloud-advanced-detail npcink-cloud-checks-more npcink-cloud-fold" id="npcink-cloud-checks-more">
				<summary><?php esc_html_e( 'More information (for contacting support)', 'npcink-cloud-addon' ); ?></summary>
				<div class="npcink-cloud-advanced-detail__body">
				<p><button type="button" class="button button-secondary" data-copied-label="<?php echo esc_attr__( 'Copied', 'npcink-cloud-addon' ); ?>" onclick="var b=this,d=b.closest('.npcink-cloud-advanced-detail__body'),n=d.querySelectorAll('details'),o=[];n.forEach(function(x){o.push(x.open);x.open=true;});var t=d.innerText.replace(b.innerText,'').trim();n.forEach(function(x,i){x.open=o[i];});var done=function(){b.textContent=b.getAttribute('data-copied-label');};if(navigator.clipboard&&window.isSecureContext){navigator.clipboard.writeText(t).then(done);}else{var a=document.createElement('textarea');a.value=t;document.body.appendChild(a);a.select();document.execCommand('copy');a.remove();done();}"><?php esc_html_e( 'Copy information', 'npcink-cloud-addon' ); ?></button></p>
			<?php if ( $readiness_failed ) : ?>
				<p class="npcink-cloud-overview-notice npcink-cloud-overview-notice--warning"><?php echo wp_kses( self::format_readiness_detail_markup( $readiness, $settings ), self::allowed_inline_action_html() ); ?></p>
			<?php endif; ?>
			<dl class="npcink-cloud-kv npcink-cloud-checks-table">
				<?php
				$hosted_runtime_ok = ! empty( $runtime['available'] );
				self::render_diagnostic_row( __( 'Connection', 'npcink-cloud-addon' ), $connection_ok ? __( 'Normal', 'npcink-cloud-addon' ) : __( 'Not connected', 'npcink-cloud-addon' ), $credentials_detail );
				// Shown on the card already; kept visually hidden so Copy information still includes it.
				?><div class="npcink-cloud-kv__row screen-reader-text"><dt><?php esc_html_e( 'Last checked', 'npcink-cloud-addon' ); ?></dt><dd><?php echo esc_html( self::format_datetime_value( (string) ( $settings['verified_at'] ?? '' ), __( 'Never', 'npcink-cloud-addon' ) ) ); ?></dd></div><?php
				$last_ai_error = get_option( Npcink_Cloud_WordPress_AI_Connector::LAST_AI_ERROR_OPTION, array() );
				if ( is_array( $last_ai_error ) && '' !== sanitize_key( (string) ( $last_ai_error['code'] ?? '' ) ) ) :
					// Raw AI error code: kept visually hidden so it only appears in Copy information.
					?><div class="npcink-cloud-kv__row screen-reader-text"><dt><?php esc_html_e( 'Last AI error code', 'npcink-cloud-addon' ); ?></dt><dd><?php echo esc_html( sanitize_key( (string) $last_ai_error['code'] ) . ' (' . self::format_datetime_value( (string) ( $last_ai_error['at'] ?? '' ), __( 'Never', 'npcink-cloud-addon' ) ) . ')' ); ?></dd></div><?php
				endif;
				self::render_diagnostic_row( __( 'Reading credits', 'npcink-cloud-addon' ), self::format_entitlement_availability( $entitlement, $is_verified ), '' );
				self::render_diagnostic_row( __( 'Nightly site check', 'npcink-cloud-addon' ), $hosted_runtime_ok ? __( 'Normal', 'npcink-cloud-addon' ) : __( 'Paused', 'npcink-cloud-addon' ), '' );
				self::render_capability_diagnostics();
				if ( ! empty( $readiness ) ) {
					self::render_diagnostic_row( __( 'Full check', 'npcink-cloud-addon' ), self::format_readiness_status( $readiness ), '' );
				}
				?>
			</dl>
			<div class="npcink-cloud-summary__actions npcink-cloud-summary__actions--start" id="npcink-cloud-run-readiness-test">
				<?php self::render_manual_readiness_test_form(); ?>
				<span class="description"><?php esc_html_e( 'or', 'npcink-cloud-addon' ); ?><a href="<?php echo esc_url( $portal_status_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'open Npcink Cloud', 'npcink-cloud-addon' ); ?></a><?php esc_html_e( 'to see details.', 'npcink-cloud-addon' ); ?></span>
			</div>
			<?php if ( $site_knowledge_needs_attention ) : ?>
				<?php self::render_site_knowledge_bridge_health_detail( $site_knowledge ); ?>
			<?php endif; ?>
				</div>
			</details>
			<?php self::render_generation_test_links(); ?>
			<?php
		}

		/** Opens host-owned tests and logs without executing requests on page load. */
		private static function render_generation_test_links( bool $guidance_only = false ): void {
			$pages = $GLOBALS['_registered_pages'] ?? array();
			$explorer_available = ! empty( $pages['tools_page_ai-abilities-explorer'] ) && function_exists( 'wp_get_ability' );
			$logs_available = ! empty( $pages['tools_page_ai-request-logs'] );
			$active = $explorer_available || ! empty( $pages['settings_page_ai-wp-admin'] ) || ( function_exists( 'is_plugin_active' ) && is_plugin_active( 'ai/ai.php' ) );
			if ( ! $guidance_only && Npcink_Cloud_Discoverability_Pause::is_admin_surface_paused( 'wordpress_ai_generation_tests' ) ) {
				return;
			}
			if ( ! $active ) {
				$installed = defined( 'WP_PLUGIN_DIR' ) && is_file( WP_PLUGIN_DIR . '/ai/ai.php' );
				$url = $installed ? admin_url( 'plugins.php?plugin_status=inactive' ) : admin_url( 'plugin-install.php?tab=plugin-information&plugin=ai' );
				?>
				<p class="description npcink-cloud-generation-tests-guidance" data-npcink-ai-plugin-guidance>
					<?php esc_html_e( 'Install the official WordPress AI plugin to use generation tests.', 'npcink-cloud-addon' ); ?>
					<a class="npcink-cloud-text-action" href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $installed ? __( 'Activate the WordPress AI plugin', 'npcink-cloud-addon' ) : __( 'Install the WordPress AI plugin', 'npcink-cloud-addon' ) ); ?></a>
				</p>
				<?php
				return;
			}
			if ( $guidance_only ) {
				return;
			}
			$modal_tests = array();
			if ( $explorer_available ) {
				foreach ( array( 'ai/title-generation' => __( 'Test text', 'npcink-cloud-addon' ), 'ai/image-generation' => __( 'Test image', 'npcink-cloud-addon' ), 'ai/alt-text-generation' => __( 'Test vision', 'npcink-cloud-addon' ) ) as $ability => $label ) {
					if ( wp_get_ability( $ability ) ) {
						$modal_tests[ $ability ] = $label;
					}
				}
			}
			$links = array();
			if ( $logs_available ) {
				$links[] = array( 'href' => admin_url( 'tools.php?page=ai-request-logs' ), 'label' => __( 'Request log', 'npcink-cloud-addon' ) );
			}
			if ( ! $modal_tests ) {
				$links[] = array( 'href' => admin_url( 'options-general.php?page=ai-wp-admin' ), 'label' => __( 'AI settings', 'npcink-cloud-addon' ) );
			}
			$explorer_url = $explorer_available ? admin_url( 'tools.php?page=ai-abilities-explorer' ) : '';
			?>
			<details class="npcink-cloud-advanced-detail npcink-cloud-generation-tests npcink-cloud-fold npcink-cloud-fold--bottom">
				<summary><?php esc_html_e( 'Advanced', 'npcink-cloud-addon' ); ?> <small><?php esc_html_e( 'WordPress AI generation tests', 'npcink-cloud-addon' ); ?></small></summary>
				<div class="npcink-cloud-advanced-detail__body">
					<p class="description"><?php esc_html_e( 'May use credits. Cloud picks the model.', 'npcink-cloud-addon' ); ?></p>
					<p class="npcink-cloud-generation-tests__actions">
						<?php $sep = false; foreach ( $modal_tests as $ability => $label ) : ?>
							<?php if ( $sep ) : ?><span class="npcink-cloud-generation-tests__sep" aria-hidden="true"> · </span><?php endif; $sep = true; ?>
							<button type="button" class="npcink-cloud-text-action" data-npcink-gen-open="<?php echo esc_attr( $ability ); ?>"><?php echo esc_html( $label ); ?></button>
						<?php endforeach; ?>
						<?php foreach ( $links as $link ) : ?>
							<?php if ( $sep ) : ?><span class="npcink-cloud-generation-tests__sep" aria-hidden="true"> · </span><?php endif; $sep = true; ?>
							<a class="npcink-cloud-text-action" href="<?php echo esc_url( (string) $link['href'] ); ?>"><?php echo esc_html( (string) $link['label'] ); ?></a>
						<?php endforeach; ?>
					</p>
				</div>
			</details>
			<?php if ( $modal_tests ) : ?>
			<dialog id="npcink-cloud-gen-test-dialog" class="npcink-cloud-gen-test-dialog" role="dialog" aria-modal="true" aria-labelledby="npcink-cloud-gen-test-title">
				<div class="npcink-cloud-gen-test-dialog__body">
					<div class="npcink-cloud-gen-test-dialog__header"><h2 id="npcink-cloud-gen-test-title" data-npcink-gen-title></h2><button type="button" class="npcink-cloud-gen-test-dialog__close" data-npcink-gen-close aria-label="<?php esc_attr_e( 'Close', 'npcink-cloud-addon' ); ?>">×</button></div>
					<div data-npcink-gen-fields class="npcink-cloud-gen-test-dialog__fields"></div>
					<p class="npcink-cloud-gen-test-dialog__actions"><span class="npcink-cloud-gen-test-dialog__actions-main"><button type="button" class="button button-primary" data-npcink-gen-run><?php esc_html_e( 'Generate', 'npcink-cloud-addon' ); ?></button> <button type="button" class="button" data-npcink-gen-media hidden><?php esc_html_e( 'Select from media library', 'npcink-cloud-addon' ); ?></button></span><?php if ( $explorer_url ) : ?><a class="npcink-cloud-text-action npcink-cloud-gen-test-dialog__advanced" data-npcink-gen-explorer href="<?php echo esc_url( $explorer_url ); ?>"><?php esc_html_e( 'Advanced: Open Ability Explorer', 'npcink-cloud-addon' ); ?></a><?php endif; ?></p>
					<p class="npcink-cloud-gen-test-dialog__status" data-npcink-gen-status hidden></p>
					<div class="npcink-cloud-gen-test-dialog__result" data-npcink-gen-result hidden></div>
				</div>
			</dialog>
			<?php endif; ?>
			<?php
		}

		/**
		 * Shows cached configuration evidence separately from connectivity.
		 *
		 * @return void
		 */
		private static function render_capability_diagnostics(): void {
			$snapshot = Npcink_Cloud_Entitlement_Summary::get_wordpress_ai_capabilities();
			$labels = array(
				'text_generation' => __( 'Write text', 'npcink-cloud-addon' ),
				'image_generation' => __( 'Create images', 'npcink-cloud-addon' ),
				'vision' => __( 'Understand images', 'npcink-cloud-addon' ),
			);
			$words = array(
				'configured' => __( 'Ready to use', 'npcink-cloud-addon' ),
				'unavailable' => __( 'Not available right now', 'npcink-cloud-addon' ),
			);
			foreach ( $labels as $key => $label ) {
				$item = $snapshot['capabilities'][ $key ];
				$word = $words[ $item['state'] ] ?? __( 'Check again to refresh', 'npcink-cloud-addon' );
				// Keep Cloud's short reason code for support; no prose jargon by default.
				$code = 'configured' === $item['state'] ? '' : sanitize_key( (string) $item['reason_code'] );
				self::render_diagnostic_row( $label, $word, $code );
			}
		}

		/**
		 * Renders one diagnostics row.
		 *
		 * @param string $label Row label.
		 * @param string $status Row status.
		 * @param string $detail Optional short code shown after the status.
		 * @return void
		 */
		private static function render_diagnostic_row( string $label, string $status, string $detail = '' ): void {
			?>
			<div class="npcink-cloud-kv__row"><dt><?php echo esc_html( $label ); ?></dt><dd><?php echo esc_html( self::format_empty( $status ) ); ?><?php if ( '' !== $detail ) : ?> <code><?php echo esc_html( $detail ); ?></code><?php endif; ?></dd></div>
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
				<?php wp_nonce_field( Npcink_Cloud_Settings_Actions::ACTION_RUN_MANUAL_READINESS_TEST ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_RUN_MANUAL_READINESS_TEST ); ?>" />
				<button type="submit" class="button button-secondary"><?php esc_html_e( 'Run a full check', 'npcink-cloud-addon' ); ?></button>
			</form>
			<?php
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
		public static function format_readiness_detail( array $readiness ): string {
			if ( empty( $readiness ) ) {
				return __( 'Use Run readiness test to execute the liveness and signed-read checks.', 'npcink-cloud-addon' );
			}

			$owner_token = sanitize_key( (string) ( $readiness['owner_label'] ?? 'cloud_addon' ) );
			$next_action_token = sanitize_key( (string) ( $readiness['next_safe_action'] ?? $readiness['next_action'] ?? 'retry_test' ) );
			$owner = self::format_readiness_token( $owner_token );
			$next_action = self::format_readiness_token( $next_action_token );
			$blocked = sanitize_text_field( (string) ( $readiness['blocked_reason'] ?? '' ) );
			$status = sanitize_key( (string) ( $readiness['bounded_status'] ?? $readiness['status'] ?? '' ) );

			if ( 'ready' === $status && 'continue' === $next_action_token && '' === $blocked ) {
				return __( 'All checks passed. No action is needed.', 'npcink-cloud-addon' );
			}

			if ( '' !== $blocked ) {
				return sprintf(
					/* translators: 1: next action label or link, 2: owner label, 3: blocked reason. */
					__( 'The readiness check did not pass. Next step: %1$s (handled by %2$s). Reason: %3$s', 'npcink-cloud-addon' ),
					$next_action,
					$owner,
					$blocked
				);
			}

			return sprintf(
				/* translators: 1: next action label or link, 2: owner label. */
				__( 'Next step: %1$s (handled by %2$s).', 'npcink-cloud-addon' ),
				$next_action,
				$owner
			);
		}
		/**
		 * Allowed tags for inline repair links in checks / readiness copy.
		 *
		 * @return array<string,array<string,bool>>
		 */
		private static function allowed_inline_action_html(): array {
			return array(
				'a' => array(
					'href'   => true,
					'target' => true,
					'rel'    => true,
					'class'  => true,
				),
			);
		}

		/**
		 * Formats readiness detail with clickable next-step links.
		 *
		 * @param array<string,mixed> $readiness Readiness result.
		 * @param array<string,mixed> $settings Stored settings for Portal URL.
		 * @return string Escaped HTML (safe for wp_kses with allowed_inline_action_html).
		 */
		public static function format_readiness_detail_markup( array $readiness, array $settings = array() ): string {
			if ( empty( $readiness ) ) {
				return esc_html__( 'Use Run readiness test to execute the liveness and signed-read checks.', 'npcink-cloud-addon' );
			}

			$owner_token = sanitize_key( (string) ( $readiness['owner_label'] ?? 'cloud_addon' ) );
			$next_action_token = sanitize_key( (string) ( $readiness['next_safe_action'] ?? $readiness['next_action'] ?? 'retry_test' ) );
			$owner = self::format_readiness_token( $owner_token );
			$blocked = sanitize_text_field( (string) ( $readiness['blocked_reason'] ?? '' ) );
			$status = sanitize_key( (string) ( $readiness['bounded_status'] ?? $readiness['status'] ?? '' ) );

			if ( 'ready' === $status && 'continue' === $next_action_token && '' === $blocked ) {
				return esc_html__( 'All checks passed. No action is needed.', 'npcink-cloud-addon' );
			}

			$portal_url = untrailingslashit( Npcink_Cloud_Addon_Settings::get_effective_base_url( $settings ) ) . '/portal';
			if ( 'retry_test' === $next_action_token ) {
				$action_markup = '<a href="#npcink-cloud-run-readiness-test">' . esc_html__( 'Retry', 'npcink-cloud-addon' ) . '</a>';
			} elseif ( 'open_settings' === $next_action_token ) {
				$action_markup = '<a href="' . esc_url( self::tab_url( 'connection' ) ) . '">' . esc_html__( 'Open Connection', 'npcink-cloud-addon' ) . '</a>';
			} elseif ( 'check_cloud_status' === $next_action_token ) {
				$action_markup = '<a href="' . esc_url( $portal_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Check Cloud status', 'npcink-cloud-addon' ) . '</a>';
			} else {
				$action_markup = esc_html( self::format_readiness_token( $next_action_token ) );
			}

			if ( '' !== $blocked ) {
				return sprintf(
					/* translators: 1: next action label or link, 2: owner label, 3: blocked reason. */
					__( 'The readiness check did not pass. Next step: %1$s (handled by %2$s). Reason: %3$s', 'npcink-cloud-addon' ),
					$action_markup,
					esc_html( $owner ),
					esc_html( $blocked )
				);
			}

			return sprintf(
				/* translators: 1: next action label or link, 2: owner label. */
				__( 'Next step: %1$s (handled by %2$s).', 'npcink-cloud-addon' ),
				$action_markup,
				esc_html( $owner )
			);
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
			<?php $configured = ! empty( $state['configured'] ); ?>
			<div class="card npcink-cloud-card">
				<?php if ( $configured ) : ?>
					<?php self::render_status_line( (string) ( $state['label'] ?? '' ), 'error' === (string) ( $state['severity'] ?? '' ) ? 'bad' : 'warn' ); ?>
					<?php self::render_connection_actions( $settings, false ); ?>
					<p class="description"><?php echo esc_html( (string) ( $state['message'] ?? '' ) ); ?></p>
				<?php else : ?>
					<?php self::render_status_line( __( 'This site is not connected yet.', 'npcink-cloud-addon' ), 'muted' ); ?>
					<div class="npcink-cloud-connect-actions">
						<?php self::render_authorization_form( __( 'Add this site in Npcink Cloud', 'npcink-cloud-addon' ), 'button button-primary button-hero' ); ?>
					</div>
					<p class="description"><?php esc_html_e( 'You will go to Npcink Cloud to confirm, then come back here automatically.', 'npcink-cloud-addon' ); ?></p>
				<?php endif; ?>
			</div>
			<details class="npcink-cloud-connect-more npcink-cloud-fold" aria-label="<?php esc_attr_e( 'Connection context', 'npcink-cloud-addon' ); ?>">
				<summary><?php esc_html_e( 'More information', 'npcink-cloud-addon' ); ?></summary>
				<?php if ( $configured ) : ?>
					<p class="description"><?php esc_html_e( 'Last checked', 'npcink-cloud-addon' ); ?><?php esc_html_e( ': ', 'npcink-cloud-addon' ); ?><?php echo esc_html( self::format_datetime_value( (string) ( $settings['verified_at'] ?? '' ), __( 'Never', 'npcink-cloud-addon' ) ) ); ?></p>
				<?php endif; ?>
				<p class="description"><?php esc_html_e( 'This site', 'npcink-cloud-addon' ); ?><?php esc_html_e( ': ', 'npcink-cloud-addon' ); ?><?php echo esc_html( home_url( '/' ) ); ?></p>
				<p class="description"><?php esc_html_e( 'Service address', 'npcink-cloud-addon' ); ?><?php esc_html_e( ': ', 'npcink-cloud-addon' ); ?><code><?php echo esc_html( $base_url ); ?></code></p>
				<p class="description"><?php esc_html_e( 'Once connected, AI features turn on automatically. You can change them in Overview at any time.', 'npcink-cloud-addon' ); ?></p>
				<p class="description"><?php esc_html_e( 'Free credits belong to the account you choose when connecting, not to this site. To switch to another account later, follow the steps shown in Npcink Cloud.', 'npcink-cloud-addon' ); ?></p>
				<p class="description"><a href="<?php echo esc_url( self::privacy_policy_url() ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Review Cloud privacy and data retention information', 'npcink-cloud-addon' ); ?></a></p>
				<?php if ( $configured && '' !== (string) ( $state['last_verification_error'] ?? '' ) ) : ?>
					<p class="description"><?php esc_html_e( 'Details for support', 'npcink-cloud-addon' ); ?><?php esc_html_e( ': ', 'npcink-cloud-addon' ); ?><code><?php echo esc_html( Npcink_Cloud_Settings_Actions::bound_admin_error_detail( (string) $state['last_verification_error'] ) ); ?></code></p>
				<?php elseif ( $configured ) : ?>
					<?php self::render_reverify_form( $settings, 'activation_required' === (string) ( $state['code'] ?? '' ) ? __( 'Check activation again', 'npcink-cloud-addon' ) : __( 'Check again', 'npcink-cloud-addon' ) ); ?>
				<?php endif; ?>
			</details>
			<details class="npcink-cloud-endpoint-advanced npcink-cloud-fold npcink-cloud-fold--bottom">
				<summary>
					<span><?php esc_html_e( 'Advanced settings', 'npcink-cloud-addon' ); ?></span>
					<small><?php esc_html_e( 'Usually not needed', 'npcink-cloud-addon' ); ?></small>
				</summary>
				<div class="npcink-cloud-endpoint-advanced__body">
					<?php if ( self::should_show_unverified_advanced_tab( $state ) ) : ?>
						<p><a href="<?php echo esc_url( self::tab_url( 'connection' ) ); ?>"><?php esc_html_e( 'Change account, disconnect, or connect manually', 'npcink-cloud-addon' ); ?></a></p>
					<?php endif; ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
						<?php wp_nonce_field( Npcink_Cloud_Settings_Actions::ACTION_START_CUSTOM_AUTH ); ?>
						<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_START_CUSTOM_AUTH ); ?>" />
						<label for="npcink-cloud-self-hosted-base-url"><?php esc_html_e( 'Service address', 'npcink-cloud-addon' ); ?></label>
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
			<form class="npcink-cloud-authorization-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( Npcink_Cloud_Settings_Actions::ACTION_START_AUTH ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_START_AUTH ); ?>" />
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
			$last_delivery_error = (string) ( $site_knowledge['last_delivery_error'] ?? '' );
			$status_summary = $delivery_enabled ? Npcink_Cloud_Site_Knowledge_Runtime_Bridge::get_cached_status_summary() : array();
			$cloud_status_current = ! empty( $status_summary['available'] ) && empty( $status_summary['stale'] );
			$cloud_usage = $delivery_enabled
				? self::get_site_knowledge_usage_projection( $status_summary )
				: self::get_site_knowledge_usage_projection( array() );
			$coverage = is_array( $status_summary['article_coverage'] ?? null ) ? $status_summary['article_coverage'] : array();
			$quota_skipped_count = absint( $status_summary['skipped_due_to_quota'] ?? 0 );
			$buffer_count = absint( $site_knowledge['buffer_count'] ?? 0 );
			$waiting_count = max( $buffer_count, absint( $coverage['not_indexed_count'] ?? 0 ) );
			$maintenance_active = 'idle' !== (string) ( $site_knowledge['maintenance_status'] ?? 'idle' );
			$dropped_count = absint( $site_knowledge['dropped_count'] ?? 0 );
			$recovery_in_progress = self::is_site_knowledge_recovery_in_progress( $site_knowledge );
			$local_delivery_needs_attention = ! $recovery_in_progress && ( ! empty( $site_knowledge['wp_cron_disabled'] )
				|| ! empty( $site_knowledge['reconcile_overdue'] )
				|| 0 < $dropped_count
				|| '' !== $last_delivery_error
				|| '' !== (string) ( $site_knowledge['last_error_code'] ?? '' ) );
			$capacity_needs_attention = $quota_skipped_count > 0;
			$update_in_progress = $maintenance_active || $waiting_count > 0;
			if ( $local_delivery_needs_attention || $capacity_needs_attention ) {
				$badge_class = 'pending';
				$badge_label = __( 'Needs attention', 'npcink-cloud-addon' );
			} elseif ( $update_in_progress ) {
				$badge_class = 'info';
				$badge_label = __( 'Updating', 'npcink-cloud-addon' );
			} elseif ( $cloud_status_current ) {
				$badge_class = 'ok';
				$badge_label = __( 'Up to date', 'npcink-cloud-addon' );
			} else {
				$badge_class = 'muted';
				$badge_label = __( 'Ready', 'npcink-cloud-addon' );
			}
			// The initial full-index manifest is the bootstrap for existing
			// content; offer it once, while the index has never been built.
			$show_start_indexing = $delivery_enabled
				&& $cloud_status_current
				&& ! $update_in_progress
				&& ! $local_delivery_needs_attention
				&& ! $capacity_needs_attention
				&& 0 === absint( $status_summary['indexed_documents'] ?? 0 );
			if ( $show_start_indexing ) {
				$badge_class = 'muted';
				$badge_label = __( 'Ready to index', 'npcink-cloud-addon' );
			}
			?>
			<?php
			$scope_hint = __( 'AI can reference your public posts and pages. WordPress content and search engine settings are not changed.', 'npcink-cloud-addon' );
			// Blocked / failed attention still offers Sync; only active delivery shows Syncing.
			$sync_busy = $recovery_in_progress
				|| ( $update_in_progress && ! $local_delivery_needs_attention && ! $capacity_needs_attention );
			$show_sync_action = $delivery_enabled && ! $show_start_indexing;
			?>
			<section id="npcink-cloud-site-knowledge" class="npcink-cloud-site-knowledge-summary" aria-labelledby="npcink-cloud-site-knowledge-status-title">
						<div class="npcink-cloud-section-heading npcink-cloud-site-knowledge-heading npcink-cloud-section-title">
							<div class="npcink-cloud-site-knowledge-heading__title">
								<h2 id="npcink-cloud-site-knowledge-status-title"><?php esc_html_e( 'Knowledge base', 'npcink-cloud-addon' ); ?></h2>
								<?php if ( $delivery_enabled ) : ?>
								<span class="npcink-cloud-badge npcink-cloud-badge--<?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_label ); ?></span>
								<?php endif; ?>
							</div>
							<div class="npcink-cloud-summary__actions">
								<?php if ( $show_sync_action ) : ?>
									<?php if ( $sync_busy ) : ?>
										<button type="button" class="button button-secondary npcink-cloud-primary-action" disabled><?php esc_html_e( 'Syncing', 'npcink-cloud-addon' ); ?></button>
									<?php else : ?>
										<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
											<?php wp_nonce_field( Npcink_Cloud_Settings_Actions::ACTION_REFRESH_SITE_KNOWLEDGE ); ?>
											<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_REFRESH_SITE_KNOWLEDGE ); ?>" />
											<button type="submit" class="button button-secondary npcink-cloud-primary-action"><?php esc_html_e( 'Sync', 'npcink-cloud-addon' ); ?></button>
										</form>
									<?php endif; ?>
								<?php endif; ?>
								<?php if ( $delivery_enabled ) : ?>
								<a
									class="button button-secondary npcink-cloud-secondary-action"
									href="<?php echo esc_url( $cloud_site_knowledge_url ); ?>"
									target="_blank"
									rel="noopener noreferrer"
									title="<?php echo esc_attr( $scope_hint ); ?>"
								><?php esc_html_e( 'Open Npcink Cloud', 'npcink-cloud-addon' ); ?></a>
								<?php endif; ?>
							</div>
						</div>
						<?php if ( $local_delivery_needs_attention ) : ?>
							<?php if ( 0 < $dropped_count ) : ?>
								<p class="description npcink-cloud-site-knowledge-summary__detail"><?php echo esc_html( sprintf(
									/* translators: %d: number of dropped content updates. */
									__( '%d content updates could not be delivered and were dropped. Use Sync to include them again.', 'npcink-cloud-addon' ),
									$dropped_count
								) ); ?></p>
								<p class="npcink-cloud-site-knowledge-summary__support"><a href="#npcink-cloud-site-knowledge"><?php esc_html_e( 'Sync above', 'npcink-cloud-addon' ); ?></a>
									· <a href="<?php echo esc_url( self::tab_url( 'checks' ) . '#npcink-cloud-sk-delivery' ); ?>"><?php esc_html_e( 'View delivery detail', 'npcink-cloud-addon' ); ?></a></p>
							<?php elseif ( ! empty( $site_knowledge['reconcile_overdue'] ) || ! empty( $site_knowledge['wp_cron_disabled'] ) ) : ?>
								<p class="description npcink-cloud-site-knowledge-summary__detail"><?php esc_html_e( 'Automatic updates are delayed. Check the site scheduler in Checks.', 'npcink-cloud-addon' ); ?></p>
								<p class="npcink-cloud-site-knowledge-summary__support"><a href="<?php echo esc_url( self::tab_url( 'checks' ) . '#npcink-cloud-sk-delivery' ); ?>"><?php esc_html_e( 'View checks', 'npcink-cloud-addon' ); ?></a></p>
							<?php else : ?>
								<p class="description npcink-cloud-site-knowledge-summary__detail"><?php
									$error_code = (string) ( $site_knowledge['last_error_code'] ?? '' );
									if ( '' !== $last_delivery_error ) {
										echo esc_html( $last_delivery_error );
									} elseif ( '' !== $error_code ) {
										echo esc_html( sprintf(
											/* translators: %s: machine-readable delivery error code. */
											__( 'Delivery failed (%s). Re-check the Cloud connection, then sync if needed.', 'npcink-cloud-addon' ),
											$error_code
										) );
									} else {
										esc_html_e( 'Delivery needs attention. Re-check the Cloud connection.', 'npcink-cloud-addon' );
									}
								?></p>
								<p class="npcink-cloud-site-knowledge-summary__support"><a href="#npcink-cloud-connection-summary"><?php esc_html_e( 'Check connection', 'npcink-cloud-addon' ); ?></a>
									· <a href="<?php echo esc_url( self::tab_url( 'checks' ) . '#npcink-cloud-sk-delivery' ); ?>"><?php esc_html_e( 'View delivery detail', 'npcink-cloud-addon' ); ?></a></p>
							<?php endif; ?>
						<?php elseif ( $capacity_needs_attention ) : ?>
							<p class="description npcink-cloud-site-knowledge-summary__detail"><?php echo esc_html( sprintf(
								/* translators: %d: content count skipped by Cloud quota. */
								__( '%d public items were not included. Review the plan details in Cloud.', 'npcink-cloud-addon' ),
								$quota_skipped_count
							) ); ?>
								<a href="<?php echo esc_url( $cloud_site_knowledge_url ); ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e( 'Open Npcink Cloud', 'npcink-cloud-addon' ); ?></a>
							</p>
						<?php elseif ( $recovery_in_progress ) : ?>
							<p class="description npcink-cloud-site-knowledge-summary__detail"><?php esc_html_e( 'Public content is being re-sent to Cloud. The warning will clear when delivery finishes.', 'npcink-cloud-addon' ); ?></p>
						<?php endif; ?>
						<?php if ( $show_start_indexing ) : ?>
						<div class="npcink-cloud-site-knowledge-summary__action">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<?php wp_nonce_field( Npcink_Cloud_Settings_Actions::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>
								<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX ); ?>" />
								<input type="hidden" name="site_knowledge_index_action" value="start" />
								<button type="submit" class="button button-primary"><?php esc_html_e( 'Start indexing', 'npcink-cloud-addon' ); ?></button>
							</form>
							<p class="description"><?php esc_html_e( 'Send a public post and page manifest so existing content enters the knowledge base.', 'npcink-cloud-addon' ); ?></p>
						</div>
						<?php endif; ?>
						<?php
						$has_waiting_meta = $waiting_count > 0;
						$has_last_updated_meta = '' !== (string) ( $site_knowledge['last_delivery_at'] ?? '' );
						?>
						<p class="npcink-cloud-site-knowledge-summary__meta"<?php echo ( $has_waiting_meta || $has_last_updated_meta ) ? '' : ' hidden'; ?>>
							<span data-npcink-site-knowledge-waiting<?php echo $has_waiting_meta ? '' : ' hidden'; ?>><?php echo esc_html( sprintf(
								/* translators: %d: number of content updates waiting for automatic processing. */
								__( 'Updates waiting: %d', 'npcink-cloud-addon' ),
								$waiting_count
							) ); ?></span>
							<span class="npcink-cloud-site-knowledge-summary__meta-sep" data-npcink-site-knowledge-meta-sep<?php echo ( $has_waiting_meta && $has_last_updated_meta ) ? '' : ' hidden'; ?> aria-hidden="true"> · </span>
							<?php if ( $has_last_updated_meta ) : ?>
								<span data-npcink-site-knowledge-last-updated><?php echo esc_html( sprintf(
									/* translators: %s: date and time of the most recent knowledge base update. */
									__( 'Last updated: %s', 'npcink-cloud-addon' ),
									self::format_compact_datetime_value( (string) $site_knowledge['last_delivery_at'] )
								) ); ?></span>
							<?php endif; ?>
						</p>

						<p hidden data-npcink-site-knowledge-read-status role="status"></p>
						<button hidden type="button" class="button button-secondary npcink-cloud-icon-button" data-npcink-site-knowledge-status-retry aria-label="<?php echo esc_attr__( 'Retry', 'npcink-cloud-addon' ); ?>" title="<?php echo esc_attr__( 'Retry', 'npcink-cloud-addon' ); ?>"><span class="dashicons dashicons-update" aria-hidden="true"></span></button>
					</section>
				<?php if ( ! $delivery_enabled ) : ?>
					<p class="description npcink-cloud-site-knowledge-disabled-note"><?php esc_html_e( 'Site Knowledge is off.', 'npcink-cloud-addon' ); ?></p>
						<?php if ( $local_delivery_needs_attention ) : ?>
							<p><a href="<?php echo esc_url( self::tab_url( 'checks' ) . '#npcink-cloud-sk-delivery' ); ?>"><?php esc_html_e( 'View checks', 'npcink-cloud-addon' ); ?></a></p>
						<?php endif; ?>
					<?php
					return;
					endif;
					?>
					<span hidden data-npcink-site-knowledge-refresh data-npcink-site-knowledge-state="<?php echo esc_attr( (string) ( $cloud_usage['state'] ?? 'not_refreshed' ) ); ?>"></span>
		<?php
		}

		/** Renders the recovery-only full knowledge base update action. */
		private static function render_site_knowledge_refresh_form( string $label, string $button_class = 'button button-secondary' ): void {
			?>
			<div class="npcink-cloud-site-knowledge-summary__action">
				<form class="npcink-cloud-verify-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( Npcink_Cloud_Settings_Actions::ACTION_REFRESH_SITE_KNOWLEDGE ); ?>
					<input type="hidden" name="action" value="<?php echo esc_attr( Npcink_Cloud_Settings_Actions::ACTION_REFRESH_SITE_KNOWLEDGE ); ?>" />
					<button type="submit" class="<?php echo esc_attr( $button_class ); ?>"><?php echo esc_html( $label ); ?></button>
				</form>
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
			$dropped_count = absint( $site_knowledge['dropped_count'] ?? 0 );
			?>
				<h4 id="npcink-cloud-sk-delivery"><?php esc_html_e( 'Knowledge base delivery', 'npcink-cloud-addon' ); ?></h4>
			<table class="widefat striped npcink-cloud-site-knowledge-status npcink-cloud-site-knowledge-health-detail">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last success', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_datetime_value( (string) ( $site_knowledge['last_success_at'] ?? '' ) ) ); ?></td>
					</tr>
					<?php if ( 0 < $dropped_count ) : ?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Dropped updates', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( sprintf(
							/* translators: 1: dropped update count, 2: drop time. */
							__( '%1$d (last at %2$s)', 'npcink-cloud-addon' ),
							$dropped_count,
							self::format_datetime_value( (string) ( $site_knowledge['last_dropped_at'] ?? '' ) )
						) ); ?></td>
					</tr>
					<?php endif; ?>
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
						<td><?php echo esc_html__( 'yes', 'npcink-cloud-addon' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Manual flush command', 'npcink-cloud-addon' ); ?></th>
						<td><code><?php echo esc_html( self::format_empty( (string) ( $site_knowledge['wp_cli_command'] ?? $site_knowledge['cron_command'] ?? '' ) ) ); ?></code></td>
					</tr>
					<?php endif; ?>
				</tbody>
			</table>
			<div class="npcink-cloud-site-knowledge-summary__support npcink-cloud-sk-delivery-actions">
				<?php if ( 0 < $dropped_count ) : ?>
					<?php self::render_site_knowledge_refresh_form( __( 'Sync', 'npcink-cloud-addon' ), 'button button-secondary npcink-cloud-primary-action' ); ?>
				<?php endif; ?>
				<p>
					<a href="<?php echo esc_url( Npcink_Cloud_Settings_Actions::page_url() . '#npcink-cloud-site-knowledge' ); ?>"><?php esc_html_e( 'Open Knowledge base on Overview', 'npcink-cloud-addon' ); ?></a>
					<?php if ( '' !== $last_error_code || '' !== (string) ( $site_knowledge['last_delivery_error'] ?? '' ) ) : ?>
						· <a href="#npcink-cloud-connection-summary"><?php esc_html_e( 'Check connection', 'npcink-cloud-addon' ); ?></a>
					<?php endif; ?>
				</p>
			</div>
			<?php
		}

		/**
		 * Renders administrator Site Knowledge index operations.
		 *
		 * @param bool $delivery_enabled Whether local delivery is enabled.
		 * @return void
		 */
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
			$core_rows = array();
			$more_rows = array();

			if ( '' !== (string) ( $entitlement['renews_at'] ?? '' ) ) {
				$core_rows[] = array( __( 'Credits reset on', 'npcink-cloud-addon' ), self::format_date_value( (string) $entitlement['renews_at'] ) );
			}

			$period_start = (string) ( $credit_period['start_at'] ?? '' );
			$period_end = (string) ( $credit_period['end_at'] ?? '' );
			if ( '' !== $period_start || '' !== $period_end ) {
				$core_rows[] = array(
					__( 'Current credit cycle', 'npcink-cloud-addon' ),
					sprintf(
						/* translators: 1: credit period start, 2: credit period end. */
						__( '%1$s to %2$s', 'npcink-cloud-addon' ),
						self::format_date_value( $period_start, __( 'unavailable', 'npcink-cloud-addon' ) ),
						self::format_date_value( $period_end, __( 'unavailable', 'npcink-cloud-addon' ) )
					)
				);
			}

			foreach (
				array(
					'max_runs' => __( 'Run limit', 'npcink-cloud-addon' ),
					'max_tokens' => __( 'Text usage limit', 'npcink-cloud-addon' ),
					'max_sites' => __( 'Sites you can connect', 'npcink-cloud-addon' ),
					'max_active_runs' => __( 'Tasks at the same time', 'npcink-cloud-addon' ),
					'max_batch_items' => __( 'Items per batch', 'npcink-cloud-addon' ),
				) as $key => $label
			) {
				$source = in_array( $key, array( 'max_active_runs', 'max_batch_items' ), true ) ? $runtime_quota : $usage_limits;
				$value = $source[ $key ] ?? 0;
				if ( is_numeric( $value ) && (float) $value > 0 ) {
					$more_rows[] = array( $label, self::format_entitlement_number( $value ) );
				}
			}

			$max_cost = $usage_limits['max_cost_usd'] ?? 0;
			if ( is_numeric( $max_cost ) && (float) $max_cost > 0 ) {
				$more_rows[] = array(
					__( 'Spending limit', 'npcink-cloud-addon' ),
					sprintf(
						/* translators: %s: USD cost limit. */
						__( '%s USD', 'npcink-cloud-addon' ),
						self::format_entitlement_number( $max_cost )
					)
				);
			}

			if ( empty( $core_rows ) && empty( $more_rows ) ) {
				return;
			}

			$ownership_note = __( 'AI credits belong to your Npcink Cloud account, not this site.', 'npcink-cloud-addon' );
			?>
			<div class="npcink-cloud-entitlement-block">
				<span class="screen-reader-text"><?php echo esc_html( $ownership_note ); ?></span>
				<?php if ( ! empty( $core_rows ) ) : ?>
				<div class="npcink-cloud-entitlement-grid" role="list">
					<?php foreach ( $core_rows as $row ) : ?>
						<div class="npcink-cloud-entitlement-grid__item" role="listitem">
							<span class="npcink-cloud-entitlement-grid__label"><?php echo esc_html( (string) $row[0] ); ?></span>
							<span class="npcink-cloud-entitlement-grid__value"><?php echo esc_html( (string) $row[1] ); ?></span>
						</div>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<?php if ( ! empty( $more_rows ) ) : ?>
				<details class="npcink-cloud-advanced-detail npcink-cloud-entitlement-more npcink-cloud-fold">
					<summary><?php esc_html_e( 'More limits', 'npcink-cloud-addon' ); ?></summary>
					<div class="npcink-cloud-advanced-detail__body">
						<div class="npcink-cloud-entitlement-grid" role="list">
							<?php foreach ( $more_rows as $row ) : ?>
								<div class="npcink-cloud-entitlement-grid__item" role="listitem">
									<span class="npcink-cloud-entitlement-grid__label"><?php echo esc_html( (string) $row[0] ); ?></span>
									<span class="npcink-cloud-entitlement-grid__value"><?php echo esc_html( (string) $row[1] ); ?></span>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</details>
				<?php endif; ?>
			</div>
			<?php
		}

		/**
		 * Renders local monitoring upload problems for the service detail tab.
		 *
		 * @param array<string,mixed> $monitoring Monitoring status.
		 * @return void
		 */
		private static function render_status_monitoring_quality( array $monitoring ): void {
			$last_upload_error = (string) ( $monitoring['last_upload_error'] ?? '' );
			// Show when a failure is recorded, or when monitoring is enabled
			// and buffered events exist. A deliberate opt-out hides routine
			// buffer state but never hides a recorded upload failure.
			if ( '' === $last_upload_error
				&& ( empty( $monitoring['enabled'] ) || absint( $monitoring['buffer_count'] ?? 0 ) < 1 ) ) {
				return;
			}

			?>
			<h3 id="npcink-cloud-monitoring-state"><?php echo '' !== $last_upload_error ? esc_html__( 'Monitoring needs attention', 'npcink-cloud-addon' ) : esc_html__( 'Monitoring upload state', 'npcink-cloud-addon' ); ?></h3>
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
				<table class="widefat striped" style="max-width: 720px;">
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
			<h3 class="screen-reader-text"><?php esc_html_e( 'Connection status', 'npcink-cloud-addon' ); ?></h3>
			<table class="widefat striped">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Cloud error classification', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( self::format_connection_classification( (string) $state['code'] ) ); ?> <code><?php echo esc_html( (string) $state['code'] ); ?></code></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last failure', 'npcink-cloud-addon' ); ?></th>
						<td><?php echo esc_html( Npcink_Cloud_Settings_Actions::bound_admin_error_detail( $last_failure ) ); ?></td>
					</tr>
				</tbody>
			</table>
			<?php
		}

		/**
		 * Translates a stable connection classification code into an
		 * administrator-facing sentence.
		 *
		 * @param string $code Stable classification code.
		 * @return string
		 */
		private static function format_connection_classification( string $code ): string {
			switch ( sanitize_key( $code ) ) {
				case 'not_configured':
					return __( 'The Cloud connection is not configured yet.', 'npcink-cloud-addon' );
				case 'configured_valid':
					return __( 'The Cloud connection is healthy.', 'npcink-cloud-addon' );
				case 'activation_required':
					return __( 'This site still needs to be activated in Cloud.', 'npcink-cloud-addon' );
				case 'configured_unavailable':
					return __( 'Cloud could not be reached or refused the connection.', 'npcink-cloud-addon' );
				case 'configured_unverified':
					return __( 'The Cloud credentials have not been verified yet.', 'npcink-cloud-addon' );
			}

			return __( 'The last Cloud verification did not complete.', 'npcink-cloud-addon' );
		}

		/**
		 * Renders and clears the saved admin notice.
		 *
		 * @return void
		 */
		private static function render_admin_notice(): void {
			$notice = Npcink_Cloud_Settings_Actions::get_admin_notice();
			if ( empty( $notice['message'] ) ) {
				return;
			}

			$type = sanitize_key( (string) ( $notice['type'] ?? '' ) );
			if ( ! in_array( $type, array( 'success', 'info', 'warning', 'error' ), true ) ) {
				$type = 'error';
			}
			?>
			<div class="notice notice-<?php echo esc_attr( $type ); ?> is-dismissible">
				<p><?php echo esc_html( (string) $notice['message'] ); ?></p>
			</div>
			<?php
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
		public static function format_overview_entitlement( array $summary, bool $is_verified ): string {
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

			// Compact plan chip — drop trailing "available" (metrics already show capacity).
			return $package_label;
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
		public static function get_overview_entitlement_metrics( array $summary ): array {
			$credits = array(
				'available' => false,
				'label' => '',
				'value_label' => '',
				'status_label' => '',
				'percent' => null,
				'tooltip' => '',
				'usage_line' => '',
				'low' => false,
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
						$credits['label'] = sprintf(
						/* translators: 1: remaining credits, 2: credit limit, 3: remaining percentage. */
						__( '%1$s / %2$s · %3$d%% remaining', 'npcink-cloud-addon' ),
						$remaining_label,
						$limit_label,
						$percent
					);
					$credits['percent'] = $percent;
					// Plain usage line: only when Cloud reports "used" itself (no WP-side estimates).
					if ( is_numeric( $credit_summary['used'] ?? null ) ) {
						$credits['usage_line'] = sprintf(
							/* translators: %s: credits used this period. */
							__( 'This period: %s credits used.', 'npcink-cloud-addon' ),
							self::format_credit_amount( $credit_summary['used'] )
						);
					}
					// Low-balance reminder: strictly from Cloud's remaining/limit projection.
					$credits['low'] = ( (float) $remaining / $limit ) < 0.1;
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
			// Align with entitlement pause/available: hide Nightly quota on Overview when paused.
			if (
				! empty( $runtime_detail['available'] )
				&& ! empty( $runtime_detail['reported'] )
				&& $runtime_limit > 0
				&& is_numeric( $runtime_detail['remaining_nightly_inspection_runs'] ?? null )
			) {
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
		public static function get_site_knowledge_usage_projection( array $summary ): array {
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
			$dropped_count = absint( $site_knowledge['dropped_count'] ?? 0 );
			if ( $dropped_count > 0 ) {
				return sprintf(
					/* translators: %d: dropped content update count. */
					__( '%d content updates were dropped', 'npcink-cloud-addon' ),
					$dropped_count
				);
			}

			$error_code = sanitize_key( (string) ( $site_knowledge['last_error_code'] ?? '' ) );
			$last_delivery_error = sanitize_text_field( (string) ( $site_knowledge['last_delivery_error'] ?? '' ) );
			if ( '' !== $error_code || '' !== $last_delivery_error ) {
				if ( '' !== $error_code ) {
					return sprintf(
						/* translators: %s: machine-readable delivery error code. */
						__( 'Delivery error: %s', 'npcink-cloud-addon' ),
						$error_code
					);
				}

				return $last_delivery_error;
			}

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

		/**
		 * Formats a stored UTC datetime as a calendar date (no visible midnight time).
		 *
		 * Used for entitlement renew / credit-period rows where Cloud sends day boundaries.
		 *
		 * @param string $value    UTC datetime string.
		 * @param string $fallback Fallback text.
		 * @return string
		 */
		private static function format_date_value( string $value, string $fallback = '' ): string {
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
				return wp_date( 'Y-m-d', $timestamp );
			}

			if ( function_exists( 'date_i18n' ) ) {
				return date_i18n( 'Y-m-d', $timestamp, true );
			}

			return gmdate( 'Y-m-d', $timestamp );
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

		/**
		 * Returns the fixed Cloud privacy URL for the current administrator locale.
		 *
		 * @return string
		 */
		private static function privacy_policy_url(): string {
			$locale = function_exists( 'determine_locale' )
				? (string) determine_locale()
				: ( function_exists( 'get_locale' ) ? (string) get_locale() : '' );
			$locale = strtolower( str_replace( '-', '_', $locale ) );
			$path = ( 'zh' === $locale || 0 === strpos( $locale, 'zh_' ) ) ? 'zh' : 'en';

			return 'https://cloud.npc.ink/terms/' . $path . '/privacy.html';
		}

	}
}
