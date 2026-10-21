<?php
/**
 * Behavior contracts for the Cloud Addon settings-page facade.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if ( ! function_exists( 'determine_locale' ) ) {
	function determine_locale(): string {
		return (string) ( $GLOBALS['maca_locale'] ?? 'en_US' );
	}
}

if ( ! function_exists( 'wp_get_ability' ) ) {
	function wp_get_ability( string $name ) {
		return $GLOBALS['maca_abilities'][ $name ] ?? null;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return 'manage_options' === $capability;
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $value ): string {
		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $value ): string {
		return esc_html( $value );
	}
}

if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $value ): string {
		return esc_attr( $value );
	}
}

if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = '' ): string {
		return esc_html( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	function esc_html_e( string $text, string $domain = '' ): void {
		echo esc_html__( $text, $domain );
	}
}

if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( string $text, string $domain = '' ): string {
		return esc_attr( __( $text, $domain ) );
	}
}

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( string $text, string $domain = '' ): void {
		echo esc_attr__( $text, $domain );
	}
}

if ( ! function_exists( 'disabled' ) ) {
	function disabled( $disabled, $current = true, bool $display = true ): string {
		$result = $disabled == $current ? ' disabled="disabled"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'sanitize_html_class' ) ) {
	function sanitize_html_class( string $value ): string {
		return preg_replace( '/[^A-Za-z0-9_-]/', '', $value );
	}
}

if ( ! function_exists( 'admin_url' ) ) {
	function admin_url( string $path = '' ): string {
		return 'https://wordpress.example.test/wp-admin/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	function add_query_arg( $key, $value = null, $url = null ): string {
		if ( is_array( $key ) ) {
			$args = $key;
			$base = (string) $value;
		} else {
			$args = array( (string) $key => $value );
			$base = (string) $url;
		}

		$query_parts = array();
		foreach ( $args as $arg_key => $arg_value ) {
			$query_parts[] = rawurlencode( (string) $arg_key ) . '=' . (string) $arg_value;
		}
		$query = implode( '&', $query_parts );
		return '' === $query ? $base : $base . ( false === strpos( $base, '?' ) ? '?' : '&' ) . $query;
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show = '' ): string {
		return 'name' === $show ? 'Npcink Test Site' : '';
	}
}

if ( ! function_exists( 'wp_generate_password' ) ) {
	function wp_generate_password( int $length = 12, bool $special_chars = true, bool $extra_special_chars = false ): string {
		unset( $special_chars, $extra_special_chars );
		return str_repeat( 'a', max( 1, $length ) );
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	function wp_nonce_field( string $action = '-1', string $name = '_wpnonce' ): void {
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="nonce-' . esc_attr( $action ) . '" />';
	}
}

if ( ! function_exists( 'submit_button' ) ) {
	function submit_button( string $text = 'Save Changes', string $type = 'primary', string $name = 'submit', bool $wrap = true, $other_attributes = null ): void {
		unset( $type, $name, $other_attributes );
		$html = '<button type="submit" class="button button-primary">' . esc_html( $text ) . '</button>';
		echo $wrap ? '<p class="submit">' . $html . '</p>' : $html;
	}
}

if ( ! function_exists( 'wp_create_nonce' ) ) {
	function wp_create_nonce( string $action = '-1' ): string {
		return 'nonce-' . $action;
	}
}

if ( ! function_exists( 'checked' ) ) {
	function checked( $checked, $current = true, bool $display = true ): string {
		$result = $checked == $current ? ' checked="checked"' : '';
		if ( $display ) {
			echo $result;
		}
		return $result;
	}
}

if ( ! function_exists( 'esc_js' ) ) {
	function esc_js( $value ): string {
		return addslashes( (string) $value );
	}
}

maca_load_addon_classes();
require_once MACA_TEST_ROOT . '/includes/class-cloud-site-knowledge-change-bridge.php';
require_once MACA_TEST_ROOT . '/includes/class-cloud-settings-page.php';
require_once MACA_TEST_ROOT . '/includes/class-cloud-settings-actions.php';

$readiness_token_formatter = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'format_readiness_token' );
if ( PHP_VERSION_ID < 80100 ) {
	$readiness_token_formatter->setAccessible( true );
}

maca_assert(
	'Ready' === $readiness_token_formatter->invoke( null, 'ready' )
	&& 'continue' === $readiness_token_formatter->invoke( null, 'continue' )
	&& 'Cloud Addon' === $readiness_token_formatter->invoke( null, 'cloud_addon' )
	&& 'unavailable' === $readiness_token_formatter->invoke( null, 'unexpected-upstream-token' ),
	'Behavior: readiness token formatting translates the bounded vocabulary and fails closed for unknown tokens.'
);

maca_reset_test_state();
Npcink_Cloud_Settings_Page::register();

$expected_hooks = array(
	'admin_menu' => array( Npcink_Cloud_Settings_Page::class, 'add_menu_page', 50 ),
	'admin_enqueue_scripts' => array( Npcink_Cloud_Settings_Page::class, 'enqueue_admin_assets', 10 ),
	'admin_post_npcink_cloud_addon_save' => array( Npcink_Cloud_Settings_Actions::class, 'handle_save', 10 ),
	'admin_post_npcink_cloud_addon_complete_auth' => array( Npcink_Cloud_Settings_Actions::class, 'handle_complete_auth', 10 ),
	'admin_post_npcink_cloud_addon_start_auth' => array( Npcink_Cloud_Settings_Actions::class, 'handle_start_auth', 10 ),
	'admin_post_npcink_cloud_addon_start_custom_auth' => array( Npcink_Cloud_Settings_Actions::class, 'handle_start_custom_auth', 10 ),
	'admin_post_npcink_cloud_addon_disconnect' => array( Npcink_Cloud_Settings_Actions::class, 'handle_disconnect', 10 ),
	'admin_post_npcink_cloud_addon_change_account' => array( Npcink_Cloud_Settings_Actions::class, 'handle_change_account', 10 ),
	'admin_post_npcink_cloud_addon_update_local_permission' => array( Npcink_Cloud_Settings_Actions::class, 'handle_update_local_permission', 10 ),
	'admin_post_npcink_cloud_addon_dismiss_monitoring_prompt' => array( Npcink_Cloud_Settings_Actions::class, 'handle_dismiss_monitoring_prompt', 10 ),
	'admin_post_npcink_cloud_addon_refresh_site_knowledge' => array( Npcink_Cloud_Settings_Actions::class, 'handle_refresh_site_knowledge', 10 ),
	'wp_ajax_npcink_cloud_addon_refresh_site_knowledge_status' => array( Npcink_Cloud_Settings_Actions::class, 'handle_refresh_site_knowledge_status', 10 ),
	'admin_post_npcink_cloud_addon_manage_site_knowledge_index' => array( Npcink_Cloud_Settings_Actions::class, 'handle_manage_site_knowledge_index', 10 ),
	'admin_post_npcink_cloud_addon_run_manual_readiness_test' => array( Npcink_Cloud_Settings_Actions::class, 'handle_run_manual_readiness_test', 10 ),
	'wp_ajax_npcink_cloud_addon_refresh_entitlement' => array( Npcink_Cloud_Settings_Actions::class, 'handle_refresh_entitlement', 10 ),
);

$registered_hook_names = array_keys( $GLOBALS['maca_actions'] );
sort( $registered_hook_names );
$expected_hook_names = array_keys( $expected_hooks );
sort( $expected_hook_names );

maca_assert(
	$expected_hook_names === $registered_hook_names,
	'Behavior: settings facade registers the complete, stable admin hook and action-name contract.'
);

$settings_page_source = (string) file_get_contents( MACA_TEST_ROOT . '/includes/class-cloud-settings-page.php' );
maca_assert(
	false === strpos( $settings_page_source, "add_action( 'npcink_cloud_addon_continue_media_recognition'" )
	&& false === strpos( $settings_page_source, "add_action( 'add_attachment'" ),
	'Behavior: Addon settings no longer owns media continuation hooks.'
);

foreach ( $expected_hooks as $hook_name => $hook_contract ) {
	list( $hook_class, $method, $priority ) = $hook_contract;
	$registration = $GLOBALS['maca_actions'][ $hook_name ][ $priority ][0] ?? array();
	$callback = $registration['callback'] ?? null;
	maca_assert(
		is_array( $callback )
		&& $hook_class === ( $callback[0] ?? null )
		&& $method === ( $callback[1] ?? null )
		&& is_callable( $callback )
		&& 1 === (int) ( $registration['accepted_args'] ?? 0 ),
		'Behavior: settings hook remains callable: ' . $hook_name
	);
}

$authorization_url_builder = new ReflectionMethod( Npcink_Cloud_Settings_Actions::class, 'build_authorization_url_for_base_url' );
if ( PHP_VERSION_ID < 80100 ) {
	$authorization_url_builder->setAccessible( true );
}

$authorization_url = (string) $authorization_url_builder->invoke( null, 'https://cloud.example.test/' );
$parse_query_pairs = static function ( string $query ): array {
	$pairs = array();
	foreach ( explode( '&', $query ) as $pair ) {
		list( $raw_key, $raw_value ) = array_pad( explode( '=', $pair, 2 ), 2, '' );
		$key = rawurldecode( $raw_key );
		$pairs[ $key ][] = rawurldecode( $raw_value );
	}

	return $pairs;
};

$authorization_query = $parse_query_pairs( (string) wp_parse_url( $authorization_url, PHP_URL_QUERY ) );
$top_level_states = $authorization_query['state'] ?? array();
$return_urls = $authorization_query['return_url'] ?? array();
$return_url = 1 === count( $return_urls ) ? (string) $return_urls[0] : '';
$callback_query = $parse_query_pairs( (string) wp_parse_url( $return_url, PHP_URL_QUERY ) );
$callback_states = $callback_query['state'] ?? array();

maca_assert(
	1 === count( $top_level_states )
	&& 1 === count( $return_urls ),
	'Behavior: Cloud authorization URL has exactly one top-level state and one encoded return URL.'
);

maca_assert(
	array( 'npcink_cloud_addon_complete_auth' ) === ( $callback_query['action'] ?? array() )
	&& 1 === count( $callback_states )
	&& (string) $top_level_states[0] === (string) $callback_states[0],
	'Behavior: authorization return URL keeps the matching state and the complete-auth callback action.'
);

$authorization_exchange = new ReflectionMethod( Npcink_Cloud_Settings_Actions::class, 'exchange_authorization_code' );
if ( PHP_VERSION_ID < 80100 ) {
	$authorization_exchange->setAccessible( true );
}

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 409 ),
	'headers'  => array( 'Content-Type' => 'application/json' ),
	'body'     => wp_json_encode(
		array(
			'status'     => 'error',
			'error_code' => 'service.site_limit_exceeded',
			'message'    => 'site limit exceeded',
			'data'       => array(),
		)
	),
);
$capacity_error = $authorization_exchange->invoke(
	null,
	'https://cloud.example.test',
	'one-time-code',
	'local-state'
);
maca_assert(
	is_wp_error( $capacity_error )
	&& false !== strpos( $capacity_error->get_error_message(), 'Turn off another site' )
	&& false !== strpos( $capacity_error->get_error_message(), 'service.site_limit_exceeded' ),
	'Behavior: addon authorization surfaces the actionable Cloud active-site limit instead of hiding it as a missing key.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'headers'  => array( 'Content-Type' => 'application/json' ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'cloud_api_key'       => 'mak1_' . rtrim(
					strtr(
						base64_encode(
							wp_json_encode(
								array(
									'site_id' => 'site_inactive',
									'key_id'  => 'key_inactive',
									'secret'  => 'secret_inactive',
								)
							)
						),
						'+/',
						'-_'
					),
					'='
				),
				'activation_state'    => 'inactive',
				'activation_required' => true,
				'activation_reason'   => 'active_site_limit_reached',
			),
		)
	),
);
$inactive_exchange = $authorization_exchange->invoke(
	null,
	'https://cloud.example.test',
	'inactive-code',
	'local-state'
);
maca_assert(
	is_array( $inactive_exchange )
	&& 'inactive' === (string) ( $inactive_exchange['activation_state'] ?? '' )
	&& true === (bool) ( $inactive_exchange['activation_required'] ?? false )
	&& 'active_site_limit_reached' === (string) ( $inactive_exchange['activation_reason'] ?? '' )
	&& '' !== (string) ( $inactive_exchange['cloud_api_key'] ?? '' ),
	'Behavior: a quota-full Cloud exchange still returns the valid connection key and bounded inactive state.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 403 ),
	'headers'  => array( 'Content-Type' => 'application/json' ),
	'body'     => wp_json_encode(
		array(
			'status'     => 'error',
			'error_code' => 'service.wordpress_addon_connection_code_expired',
			'message'    => 'expired',
			'data'       => array(),
		)
	),
);
$expired_error = $authorization_exchange->invoke(
	null,
	'https://cloud.example.test',
	'expired-code',
	'local-state'
);
maca_assert(
	is_wp_error( $expired_error )
	&& false !== strpos( $expired_error->get_error_message(), 'Click Connect again' )
	&& false !== strpos( $expired_error->get_error_message(), 'service.wordpress_addon_connection_code_expired' ),
	'Behavior: addon authorization preserves a non-secret Cloud error code and gives an expired-flow recovery action.'
);

maca_reset_test_state();
$http_before_render = count( $GLOBALS['maca_http_requests'] );
ob_start();
Npcink_Cloud_Settings_Page::render();
$rendered = (string) ob_get_clean();

maca_assert(
	false !== strpos( $rendered, 'Npcink Cloud Addon' )
	&& false !== strpos( $rendered, 'Add this site in Npcink Cloud' )
	&& false !== strpos( $rendered, 'npcink_cloud_addon_start_auth' )
	&& false !== strpos( $rendered, 'Free credits belong to the account you choose when connecting' )
	&& $http_before_render === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: the representative unconfigured admin render explains account-owned Free service and performs zero outbound HTTP requests.'
);

$tab_url_builder = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'tab_url' );
$page_form_action_builder = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'page_form_action_url' );
$tab_url_builder->setAccessible( true );
$page_form_action_builder->setAccessible( true );
maca_assert(
	'https://wordpress.example.test/wp-admin/options-general.php?page=npcink-cloud-addon&tab=checks' === $tab_url_builder->invoke( null, 'checks' )
	&& 'https://wordpress.example.test/wp-admin/options-general.php' === $page_form_action_builder->invoke( null ),
	'Behavior: standalone settings links and GET forms stay on the registered options-general.php page.'
);

$settings_page_source = file_get_contents( MACA_TEST_ROOT . '/includes/class-cloud-settings-page.php' );
$settings_actions_source = file_get_contents( MACA_TEST_ROOT . '/includes/class-cloud-settings-actions.php' );
$permissions_script_source = file_get_contents( MACA_TEST_ROOT . '/assets/admin-permissions.js' );
maca_assert(
	is_string( $settings_page_source )
	&& is_string( $settings_actions_source )
	&& is_string( $permissions_script_source )
	&& false !== strpos( $settings_actions_source, 'set_local_permission_feedback' )
	&& false !== strpos( $settings_actions_source, 'redirect_to_local_permission' )
	&& false !== strpos( $settings_page_source, 'data-npcink-local-permission-feedback' )
	&& false !== strpos( $permissions_script_source, "form.setAttribute( 'aria-busy', 'true' )" )
	&& false !== strpos( $permissions_script_source, 'scrollIntoView' ),
	'Behavior: local permission switches expose a saving state, row-level feedback, and focus restoration after redirect.'
);

$set_admin_notice = new ReflectionMethod( Npcink_Cloud_Settings_Actions::class, 'set_admin_notice' );
$set_local_permission_feedback = new ReflectionMethod( Npcink_Cloud_Settings_Actions::class, 'set_local_permission_feedback' );
if ( PHP_VERSION_ID < 80100 ) {
	$set_admin_notice->setAccessible( true );
	$set_local_permission_feedback->setAccessible( true );
}
maca_reset_test_state();
$set_admin_notice->invoke( null, 'success', 'Settings saved.' );
$notice_key = 'npcink_cloud_notice_' . get_current_user_id();
$notice_expiration = (int) ( $GLOBALS['maca_transient_expirations'][ $notice_key ] ?? 0 );
$notice = Npcink_Cloud_Settings_Actions::get_admin_notice();
$set_local_permission_feedback->invoke( null, 'error', 'Permission failed.', 'wordpress_ai_connector' );
$permission_feedback_key = 'npcink_cloud_permission_feedback_' . get_current_user_id();
$permission_feedback_expiration = (int) ( $GLOBALS['maca_transient_expirations'][ $permission_feedback_key ] ?? 0 );
$permission_feedback = Npcink_Cloud_Settings_Actions::get_local_permission_feedback();
maca_assert(
	60 === $notice_expiration
	&& 'success' === (string) ( $notice['type'] ?? '' )
	&& false === get_transient( $notice_key )
	&& 60 === $permission_feedback_expiration
	&& 'wordpress_ai_connector' === (string) ( $permission_feedback['permission'] ?? '' )
	&& false === get_transient( $permission_feedback_key ),
	'Behavior: admin notices and local permission feedback retain a 60-second TTL and are consumed once.'
);

$set_admin_notice->invoke( null, 'info', 'Recovery scheduled.' );
$render_admin_notice = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_admin_notice' );
if ( PHP_VERSION_ID < 80100 ) {
	$render_admin_notice->setAccessible( true );
}
ob_start();
$render_admin_notice->invoke( null );
$scheduled_notice_html = (string) ob_get_clean();
maca_assert(
	false !== strpos( $scheduled_notice_html, 'notice-info' ) && false === strpos( $scheduled_notice_html, 'notice-error' ),
	'Behavior: scheduled recovery renders an informational notice instead of success or failure styling.'
);

maca_assert(
	false !== strpos( $settings_page_source, 'npcink-cloud-monitoring-consent' )
	&& false !== strpos( $settings_page_source, 'Yes, share' )
	&& false !== strpos( $settings_page_source, 'Not now' )
	&& false !== strpos( $settings_actions_source, 'maybe_prompt_for_monitoring_consent' ),
	'Behavior: first verified connection offers one explicit metadata-only monitoring consent prompt.'
);

maca_reset_test_state();
maca_seed_settings( true );
set_transient( 'npcink_cloud_monitoring_consent_' . get_current_user_id(), true, DAY_IN_SECONDS );
ob_start();
Npcink_Cloud_Settings_Page::render();
$consent_rendered = (string) ob_get_clean();
maca_assert(
	false !== strpos( $consent_rendered, 'role="dialog"' )
	&& false !== strpos( $consent_rendered, 'Help us improve?' )
	&& false !== strpos( $consent_rendered, 'Yes, share' )
	&& false !== strpos( $consent_rendered, 'Not now' ),
	'Behavior: a pending first-connection consent is rendered as a bounded confirmation dialog without Cloud HTTP.'
);

maca_reset_test_state();
maca_seed_settings( false );
$failed_settings = Npcink_Cloud_Addon_Settings::get_settings();
$failed_settings['last_verification_error'] = 'Cloud connection failed once.';
Npcink_Cloud_Addon_Settings::write_settings( $failed_settings );
ob_start();
Npcink_Cloud_Settings_Page::render();
$failed_rendered = (string) ob_get_clean();

maca_assert(
	1 === substr_count( $failed_rendered, 'Cloud connection failed once.' ),
	'Behavior: the unverified connection page renders its persisted Cloud failure exactly once.'
);

maca_reset_test_state();
maca_seed_settings( false );
$inactive_render_settings = Npcink_Cloud_Addon_Settings::get_settings();
$inactive_render_settings['activation_state'] = 'inactive';
$inactive_render_settings['activation_reason'] = 'cloud_site_inactive';
Npcink_Cloud_Addon_Settings::write_settings( $inactive_render_settings );
ob_start();
Npcink_Cloud_Settings_Page::render();
$inactive_rendered = (string) ob_get_clean();

maca_assert(
	false !== strpos( $inactive_rendered, 'This site needs to be turned on in Npcink Cloud' )
	&& false !== strpos( $inactive_rendered, 'Open Npcink Cloud' )
	&& false !== strpos( $inactive_rendered, 'Check activation again' )
	&& false === strpos( $inactive_rendered, 'Signed verification failed' ),
	'Behavior: an inactive bound site renders activation recovery actions without calling it a signature failure.'
);

maca_reset_test_state();
maca_seed_settings( true );
$http_before_overview = count( $GLOBALS['maca_http_requests'] );
ob_start();
Npcink_Cloud_Settings_Page::render();
$overview_rendered = (string) ob_get_clean();

maca_assert(
	false === strpos( $overview_rendered, 'Connection and service' )
	&& false === strpos( $overview_rendered, '>Connected<' )
	&& false === strpos( $overview_rendered, 'npcink-cloud-overview-heading' )
	&& false === strpos( $overview_rendered, 'npcink-cloud-overview-panel__header' )
	&& false === strpos( $overview_rendered, 'npcink-cloud-overview-panel__plan' )
	&& false !== strpos( $overview_rendered, 'data-npcink-entitlement-state' )
	&& false !== strpos( $overview_rendered, 'data-npcink-entitlement-summary' )
	&& false === strpos( $overview_rendered, 'Recently reachable' )
	&& false === strpos( $overview_rendered, ' · available' )
	&& false === strpos( $overview_rendered, '<section class="npcink-cloud-summary">' )
	&& false === strpos( $overview_rendered, 'Last verification succeeded' )
	&& false === strpos( $overview_rendered, 'Current service' )
	&& false === strpos( $overview_rendered, 'A cached signed Cloud read is available.' )
	&& false !== strpos( $overview_rendered, 'Available knowledge documents' )
	&& false !== strpos( $overview_rendered, 'data-npcink-site-knowledge-usage' )
	&& false !== strpos( $overview_rendered, 'npcink-cloud-site-knowledge-summary' )
	&& false !== strpos( $overview_rendered, 'id="npcink-cloud-site-knowledge"' )
	&& false !== strpos( $overview_rendered, 'Plan and credits' )
	&& false !== strpos( $overview_rendered, 'id="npcink-cloud-entitlements-title"' )
	&& false !== strpos( $overview_rendered, 'Knowledge base' )
	&& false === strpos( $overview_rendered, 'Knowledge base status' )
	&& false !== strpos( $overview_rendered, 'Features' )
	&& false !== strpos( $overview_rendered, 'Enable Site Knowledge' )
	&& false !== strpos( $overview_rendered, 'Privacy settings' )
	&& false !== strpos( $overview_rendered, 'Send anonymous diagnostics' )
	&& false === strpos( $overview_rendered, '>WordPress AI connector<' )
	&& false === strpos( $overview_rendered, '>Reference site content during generation<' )
	&& false !== strpos( $overview_rendered, 'screen-reader-text' )
	&& false === strpos( $overview_rendered, 'Monitoring needs attention' )
	&& false === strpos( $overview_rendered, 'tab=site_knowledge' )
	&& $http_before_overview === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: the verified Overview owns usage, embedded Site Knowledge status, and Features without a duplicate global card or Cloud HTTP.'
);

$tab_labels_renderer = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'get_tab_labels' );
if ( PHP_VERSION_ID < 80100 ) {
	$tab_labels_renderer->setAccessible( true );
}
maca_set_site_knowledge_delivery_enabled( false );
$tabs_without_site_knowledge = $tab_labels_renderer->invoke(
	null,
	true,
	Npcink_Cloud_Addon_Settings::get_credential_state()
);
maca_set_site_knowledge_delivery_enabled( true );
$tabs_with_site_knowledge = $tab_labels_renderer->invoke(
	null,
	true,
	Npcink_Cloud_Addon_Settings::get_credential_state()
);
maca_assert(
	! isset( $tabs_without_site_knowledge['site_knowledge'] )
	&& ! isset( $tabs_with_site_knowledge['site_knowledge'] )
	&& isset( $tabs_with_site_knowledge['permissions'] )
	&& isset( $tabs_with_site_knowledge['checks'] )
	&& isset( $tabs_with_site_knowledge['connection'] )
	&& ! isset( $tabs_with_site_knowledge['advanced'] ),
	'Behavior: verified navigation is Overview, Checks, and Connection without a Site Knowledge or Advanced tab.'
);

update_option(
	Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION,
	array(
		'post_ids' => range( 1, 50 ),
		'attempts' => 0,
	),
	false
);
ob_start();
Npcink_Cloud_Settings_Page::render();
$buffered_overview_rendered = (string) ob_get_clean();
maca_assert(
	false === strpos( $buffered_overview_rendered, 'Site Knowledge needs attention' )
	&& false === strpos( $buffered_overview_rendered, 'public changes awaiting delivery' ),
	'Behavior: routine Site Knowledge delivery buffering stays automatic and does not appear as an Overview warning.'
);
delete_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION );

$get_tab_labels = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'get_tab_labels' );
$get_active_tab = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'get_active_tab' );
$checks_renderer = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_diagnostic_checks' );
$connection_renderer = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_connection_page' );
if ( PHP_VERSION_ID < 80100 ) {
	$get_tab_labels->setAccessible( true );
	$get_active_tab->setAccessible( true );
	$checks_renderer->setAccessible( true );
	$connection_renderer->setAccessible( true );
}
$verified_tabs = $get_tab_labels->invoke( null, true, array() );
maca_assert(
	array( 'permissions', 'checks', 'connection' ) === array_keys( $verified_tabs )
	&& 'Overview' === $verified_tabs['permissions']
	&& 'Checks' === $verified_tabs['checks']
	&& 'Connection' === $verified_tabs['connection']
	&& false === array_key_exists( 'site_knowledge', $verified_tabs )
	&& false === array_key_exists( 'advanced', $verified_tabs ),
	'Behavior: verified navigation is Overview, Checks, and Connection without Advanced.'
);

$_GET = array();
maca_assert(
	'permissions' === $get_active_tab->invoke( null, true, array( 'verified' => true ) ),
	'Behavior: default verified tab is Overview.'
);
$_GET['tab'] = 'advanced';
$_GET['view'] = 'service';
maca_assert(
	'permissions' === $get_active_tab->invoke( null, true, array( 'verified' => true ) ),
	'Behavior: legacy advanced&view=service deep-links to Overview.'
);
$_GET['view'] = 'checks';
maca_assert(
	'checks' === $get_active_tab->invoke( null, true, array( 'verified' => true ) ),
	'Behavior: legacy advanced&view=checks deep-links to Checks.'
);
$_GET['view'] = 'connection';
maca_assert(
	'connection' === $get_active_tab->invoke( null, true, array( 'verified' => true ) ),
	'Behavior: legacy advanced&view=connection deep-links to Connection.'
);
unset( $_GET['view'] );
$_GET['tab'] = 'site_knowledge';
maca_assert(
	'permissions' === $get_active_tab->invoke( null, true, array( 'verified' => true ) ),
	'Behavior: legacy tab=site_knowledge deep-links to Overview.'
);
unset( $_GET['tab'] );

ob_start();
$checks_renderer->invoke(
	null,
	Npcink_Cloud_Addon_Settings::get_settings(),
	Npcink_Cloud_Addon_Settings::get_credential_state(),
	array( 'available' => false ),
	true
);
$checks_rendered = (string) ob_get_clean();
maca_assert(
	false !== strpos( $checks_rendered, 'npcink-cloud-checks-simple' )
	&& strpos( $checks_rendered, 'npcink-cloud-checks-simple' ) < strpos( $checks_rendered, 'npcink-cloud-checks-more' )
	&& false === strpos( $checks_rendered, 'npcink-cloud-checks-more" open' )
	&& false !== strpos( $checks_rendered, 'Copy information' )
	&& false !== strpos( $checks_rendered, 'npcink-cloud-kv' )
	&& false !== strpos( $checks_rendered, 'Reading credits' )
	&& false !== strpos( $checks_rendered, 'Nightly site check' )
	&& false !== strpos( $checks_rendered, 'Run a full check' )
	&& false === strpos( $checks_rendered, '<table' )
	&& false === strpos( $checks_rendered, 'Change account' )
	&& false === strpos( $checks_rendered, 'npcink-cloud-secondary-tabs' )
	&& 1 >= substr_count( $checks_rendered, 'button-primary' )
	&& (
		false !== strpos( $checks_rendered, 'WordPress AI generation tests' )
		|| false !== strpos( $checks_rendered, 'Install the WordPress AI plugin' )
		|| false !== strpos( $checks_rendered, 'Activate the WordPress AI plugin' )
	),
	'Behavior: Checks keeps credentials visible, pairs readiness hint with the run button, folds Hosted Runtime into Check details, and exposes generation tests or AI plugin guidance.'
);

ob_start();
$connection_renderer->invoke(
	null,
	Npcink_Cloud_Addon_Settings::get_settings(),
	Npcink_Cloud_Addon_Settings::get_credential_state(),
	true
);
$connection_rendered = (string) ob_get_clean();
maca_assert(
	false !== strpos( $connection_rendered, 'Change account' )
	&& false !== strpos( $connection_rendered, 'Connected Cloud identity' )
	&& false === strpos( $connection_rendered, 'npcink-cloud-checks-table' )
	&& false !== strpos( $connection_rendered, 'screen-reader-text' )
	&& false !== strpos( $connection_rendered, 'Connection management' )
	&& false === strpos( $connection_rendered, '<h3>Connection management</h3>' )
	&& false !== strpos( $connection_rendered, 'npcink-cloud-manual-fallback' )
	&& false === strpos( $connection_rendered, 'npcink-cloud-manual-fallback" open' )
	&& false === strpos( $connection_rendered, 'npcink-cloud-manual-fallback open' )
	&& false !== strpos( $connection_rendered, 'Only use this if support asks you to, or if connecting through Npcink Cloud does not work.' )
	&& strpos( $connection_rendered, '<summary>' ) < strpos( $connection_rendered, 'Service address' )
	&& false === strpos( $connection_rendered, 'npcink-cloud-secondary-tabs' ),
	'Behavior: Connection tab renders identity plus connection management without secondary tabs or checks.'
);

// Overview includes entitlement details after the merge.
ob_start();
Npcink_Cloud_Settings_Page::render();
$overview_with_entitlement = (string) ob_get_clean();
maca_assert(
	false === strpos( $overview_with_entitlement, 'No additional entitlement parameters were returned by Cloud.' )
	&& false === strpos( $overview_with_entitlement, 'View AI credit details in Cloud' )
	&& false !== strpos( $overview_with_entitlement, 'Plan and credits' )
	&& (
		false === strpos( $overview_with_entitlement, 'npcink-cloud-entitlement-block' )
		|| false !== strpos( $overview_with_entitlement, 'npcink-cloud-overview-entitlements' )
	)
	&& (
		false === strpos( $overview_with_entitlement, 'npcink-cloud-entitlement-more' )
		|| (
			false !== strpos( $overview_with_entitlement, 'More limits' )
			&& false === strpos( $overview_with_entitlement, 'npcink-cloud-entitlement-more" open' )
			&& false === strpos( $overview_with_entitlement, 'npcink-cloud-entitlement-more open' )
		)
	),
	'Behavior: Overview skips empty entitlement copy; extra limits fold under More limits when present.'
);


$connection_identity_renderer = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_connection_identity' );
if ( PHP_VERSION_ID < 80100 ) {
	$connection_identity_renderer->setAccessible( true );
}
ob_start();
$connection_identity_renderer->invoke(
	null,
	Npcink_Cloud_Addon_Settings::get_settings(),
	Npcink_Cloud_Addon_Settings::get_credential_state(),
	array(
		'package_label' => 'free',
		'available' => true,
	)
);
$connection_identity_rendered = (string) ob_get_clean();
maca_assert(
	false !== strpos( $connection_identity_rendered, 'Connected Cloud identity' )
	&& false !== strpos( $connection_identity_rendered, 'Last checked' )
	&& false !== strpos( $connection_identity_rendered, 'Plan' )
	&& false === strpos( $connection_identity_rendered, 'Site ID' )
	&& false === strpos( $connection_identity_rendered, 'Key ID' ),
	'Behavior: Connection identity projects status, endpoint, verification time, and plan without split credential identifiers.'
);

$connection_management_renderer = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_connection_management' );
if ( PHP_VERSION_ID < 80100 ) {
	$connection_management_renderer->setAccessible( true );
}

$http_before_connection_management = count( $GLOBALS['maca_http_requests'] );
ob_start();
$connection_management_renderer->invoke(
	null,
	Npcink_Cloud_Addon_Settings::get_settings()
);
$connection_management_rendered = (string) ob_get_clean();

maca_assert(
	false !== strpos( $connection_management_rendered, 'Connection management' )
	&& false !== strpos( $connection_management_rendered, 'Change account' )
	&& false !== strpos( $connection_management_rendered, 'npcink_cloud_addon_change_account' )
	&& false !== strpos( $connection_management_rendered, 'button button-secondary' )
	&& false !== strpos( $connection_management_rendered, 'Disconnect this site' )
	&& false !== strpos( $connection_management_rendered, 'Your data stays in Npcink Cloud.' )
		&& false !== strpos( $connection_management_rendered, 'js_confirmation' )
		&& false !== strpos( $connection_management_rendered, 'noscript_confirmation' )
		&& false !== strpos( $connection_management_rendered, 'Confirm local disconnect before submitting.' )
		&& false !== strpos( $connection_management_rendered, 'Please confirm before changing the account.' )
	&& false === strpos( $connection_management_rendered, 'Check connection' )
	&& false === strpos( $connection_management_rendered, 'Re-verify and refresh' )
	&& strpos( $connection_management_rendered, 'Change account' ) < strpos( $connection_management_rendered, 'Disconnect this site' )
	&& $http_before_connection_management === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: Connection Management presents one Cloud account action and separates the local destructive action without Cloud HTTP.'
);

ob_start();
$connection_management_renderer->invoke(
	null,
	Npcink_Cloud_Addon_Settings::get_settings(),
	true
);
$connection_management_attention = (string) ob_get_clean();
maca_assert(
	false !== strpos( $connection_management_attention, 'Check connection' )
	&& false !== strpos( $connection_management_attention, 'Change account' ),
	'Behavior: Connection Management reuses Check connection when service needs attention.'
);

$settings_page_source = (string) file_get_contents( MACA_TEST_ROOT . '/includes/class-cloud-settings-page.php' );
maca_assert(
	false === strpos( $settings_page_source, 'render_site_knowledge_article_coverage' )
		&& false === strpos( $settings_page_source, 'data-npcink-site-knowledge-article-coverage' ),
	'Behavior: Site Knowledge settings do not render the retired article coverage anchor.'
);

$privacy_policy_url_method = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'privacy_policy_url' );
if ( PHP_VERSION_ID < 80100 ) {
	$privacy_policy_url_method->setAccessible( true );
}
	$GLOBALS['maca_locale'] = 'zh_CN';
$zh_privacy_policy_url = (string) $privacy_policy_url_method->invoke( null );
$GLOBALS['maca_locale'] = 'en_US';
$en_privacy_policy_url = (string) $privacy_policy_url_method->invoke( null );
maca_assert(
	'https://cloud.npc.ink/terms/zh/privacy.html' === $zh_privacy_policy_url
		&& 'https://cloud.npc.ink/terms/en/privacy.html' === $en_privacy_policy_url,
	'Behavior: the Cloud privacy link follows the fixed Chinese or English administrator locale.'
);
$GLOBALS['maca_locale'] = 'en_US';

$disconnect_confirmation_method = new ReflectionMethod( Npcink_Cloud_Settings_Actions::class, 'has_disconnect_confirmation' );
if ( PHP_VERSION_ID < 80100 ) {
	$disconnect_confirmation_method->setAccessible( true );
}
$_POST = array();
$missing_disconnect_confirmation = (bool) $disconnect_confirmation_method->invoke( null );
$_POST = array( 'js_confirmation' => '1' );
$js_disconnect_confirmation = (bool) $disconnect_confirmation_method->invoke( null );
$_POST = array( 'noscript_confirmation' => '1' );
$noscript_disconnect_confirmation = (bool) $disconnect_confirmation_method->invoke( null );
$_POST = array();
maca_assert(
	! $missing_disconnect_confirmation && $js_disconnect_confirmation && $noscript_disconnect_confirmation,
	'Behavior: disconnect requires an explicit confirmation marker from either the JS or noscript form path.'
);

$site_knowledge_renderer = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_site_knowledge_summary' );
if ( PHP_VERSION_ID < 80100 ) {
	$site_knowledge_renderer->setAccessible( true );
}
Npcink_Cloud_Entitlement_Summary::cache_summary_from_response(
	array(
		'data' => array(
			'package' => 'Pro',
			'quota_summary' => array(
				'resource_limits' => array(
					array(
						'key' => 'media_images',
						'used' => 320,
						'limit' => 5000,
						'remaining' => 4680,
						'status' => 'ok',
						'unit' => 'image',
					),
				),
			),
		),
	),
	Npcink_Cloud_Addon_Settings::get_settings()
);

$http_before_site_knowledge = count( $GLOBALS['maca_http_requests'] );
ob_start();
$site_knowledge_renderer->invoke(
	null,
	Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot(),
	Npcink_Cloud_Addon_Settings::get_settings(),
	true
);
$site_knowledge_rendered = (string) ob_get_clean();

maca_assert(
	false === strpos( $site_knowledge_rendered, 'Available knowledge documents' )
	&& false !== strpos( $site_knowledge_rendered, 'data-npcink-site-knowledge-refresh' )
		&& false !== strpos( $site_knowledge_rendered, 'id="npcink-cloud-site-knowledge"' )
		&& false !== strpos( $site_knowledge_rendered, 'Knowledge base' )
		&& false === strpos( $site_knowledge_rendered, 'Knowledge base status' )
		&& false !== strpos( $site_knowledge_rendered, '<h2 id="npcink-cloud-site-knowledge-status-title">Knowledge base' )
		&& false !== strpos( $site_knowledge_rendered, 'Open Npcink Cloud' )
		&& false !== strpos( $site_knowledge_rendered, '>Sync<' )
		&& false !== strpos( $site_knowledge_rendered, 'npcink-cloud-site-knowledge-heading__title' )
		&& false === strpos( $site_knowledge_rendered, 'npcink-cloud-site-knowledge-summary__status' )
		&& false === strpos( $site_knowledge_rendered, 'npcink-cloud-info-hint' )
		&& false === strpos( $site_knowledge_rendered, 'Updating ·' )
		&& false === strpos( $site_knowledge_rendered, 'screen-reader-text">Knowledge base' )
		&& false === strpos( $site_knowledge_rendered, 'Updates will appear here after public content changes.' )
		&& false === strpos( $site_knowledge_rendered, 'Manual update' )
		&& false === strpos( $site_knowledge_rendered, '<summary>Technical details</summary>' )
		&& false === strpos( $site_knowledge_rendered, 'npcink-cloud-site-knowledge-quota-detail' )
		&& false === strpos( $site_knowledge_rendered, 'data-npcink-site-knowledge-detail="chunks"' )
		&& false === strpos( $site_knowledge_rendered, 'title="More actions"' )
	&& false !== strpos( $site_knowledge_rendered, 'https://cloud.example.test/portal/sites/site_test#site-knowledge' )
	&& false === strpos( $site_knowledge_rendered, 'Automatic updates on' )
	&& false === strpos( $site_knowledge_rendered, 'Change settings' )
	&& false === strpos( $site_knowledge_rendered, '/portal/site-knowledge' )
	&& false === strpos( $site_knowledge_rendered, 'Filter articles by index status' )
	&& false === strpos( $site_knowledge_rendered, 'View content list' )
	&& false === strpos( $site_knowledge_rendered, 'Pending content (' )
		&& false === strpos( $site_knowledge_rendered, 'Start media recognition' )
		&& false === strpos( $site_knowledge_rendered, 'data-npcink-site-media-status' )
		&& false === strpos( $site_knowledge_rendered, 'data-npcink-site-media-progress' )
		&& false === strpos( $site_knowledge_rendered, 'data-npcink-site-media-capacity' )
		&& false === strpos( $site_knowledge_rendered, '320 used / 5,000 limit / 4,680 remaining' )
		&& false === strpos( $site_knowledge_rendered, 'npcink-cloud-site-media-progress-head' )
		&& false === strpos( $site_knowledge_rendered, 'data-npcink-site-media-eta' )
		&& false === strpos( $site_knowledge_rendered, 'View recognition details' )
		&& false === strpos( $site_knowledge_rendered, 'npcink-cloud-inline-info' )
		&& $http_before_site_knowledge === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: healthy Site Knowledge keeps maintenance and Cloud links, removes the duplicate Settings link, and hides manual recovery without Cloud HTTP.'
);

$status_cache_key = 'npcink_cloud_site_knowledge_status_' . md5(
	implode(
		'|',
		array(
			(string) Npcink_Cloud_Addon_Settings::get_settings()['base_url'],
			(string) Npcink_Cloud_Addon_Settings::get_settings()['site_id'],
			(string) Npcink_Cloud_Addon_Settings::get_settings()['key_id'],
		)
	)
);
$GLOBALS['maca_transients'][ $status_cache_key ] = array(
	'available' => true,
	'state' => 'fresh',
	'indexed_documents' => 0,
	'max_documents' => 10000,
	'remaining_documents' => 10000,
	'document_percent' => 0,
	'quota_status' => 'ok',
	'fresh_until' => gmdate( 'Y-m-d H:i:s', time() + 1800 ) . ' UTC',
);
ob_start();
$site_knowledge_renderer->invoke(
	null,
	Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot(),
	Npcink_Cloud_Addon_Settings::get_settings(),
	true
);
$empty_index_rendered = (string) ob_get_clean();
maca_assert(
	false !== strpos( $empty_index_rendered, 'Start indexing' )
	&& false === strpos( $empty_index_rendered, 'Knowledge base maintenance' )
	&& false === strpos( $empty_index_rendered, 'Rebuild index' )
	&& false === strpos( $empty_index_rendered, 'Delete site index' ),
	'Behavior: an unbuilt knowledge base offers the initial indexing bootstrap only, with rebuild and delete lifecycle intents left to Cloud.'
);

$GLOBALS['maca_transients'][ $status_cache_key ]['indexed_documents'] = 500;
$GLOBALS['maca_transients'][ $status_cache_key ]['remaining_documents'] = 9500;
ob_start();
$site_knowledge_renderer->invoke(
	null,
	Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot(),
	Npcink_Cloud_Addon_Settings::get_settings(),
	true
);
$built_index_rendered = (string) ob_get_clean();
maca_assert(
	false === strpos( $built_index_rendered, 'Start indexing' )
	&& false !== strpos( $built_index_rendered, 'Up to date' )
	&& false === strpos( $built_index_rendered, 'Ready to index' ),
	'Behavior: the initial indexing bootstrap disappears once the knowledge base has been built.'
);

$dropped_status = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
$dropped_status['dropped_count'] = 3;
$dropped_status['last_dropped_at'] = gmdate( 'c' );
$dropped_status['last_delivery_error'] = '';
$dropped_status['last_error_code'] = '';
ob_start();
$site_knowledge_renderer->invoke(
	null,
	$dropped_status,
	Npcink_Cloud_Addon_Settings::get_settings(),
	true
);
$dropped_rendered = (string) ob_get_clean();
maca_assert(
	false !== strpos( $dropped_rendered, 'Needs attention' )
	&& false !== strpos( $dropped_rendered, '3 content updates could not be delivered and were dropped. Use Sync to include them again.' )
	&& false !== strpos( $dropped_rendered, '>Sync<' )
	&& false !== strpos( $dropped_rendered, 'Sync above' )
	&& false === strpos( $dropped_rendered, 'Update again' )
	&& false === strpos( $dropped_rendered, 'Updating ·' )
	&& false === strpos( $dropped_rendered, 'The system will keep trying automatically.' ),
	'Behavior: dropped content changes surface one attention row with Sync recovery instead of a false auto-retry promise.'
);

foreach ( array( 'queued', 'delivering', 'retrying', 'blocked' ) as $maintenance_state ) {
	$recovering_status = $dropped_status;
	$recovering_status['maintenance_status'] = $maintenance_state;
	$recovering_status['maintenance_active'] = in_array( $maintenance_state, array( 'queued', 'delivering', 'retrying' ), true );
	$recovering_status['maintenance_operation'] = 'rebuild';
	// Queuing preserves the previous failure until the first delivery runs.
	$recovering_status['last_delivery_error'] = 'queued' === $maintenance_state ? 'Previous failure' : '';
	ob_start();
	$site_knowledge_renderer->invoke( null, $recovering_status, Npcink_Cloud_Addon_Settings::get_settings(), true );
	$recovery_html = (string) ob_get_clean();
	$is_recovering = in_array( $maintenance_state, array( 'queued', 'delivering', 'retrying' ), true );
	maca_assert(
		$is_recovering
			? false !== strpos( $recovery_html, 'Public content is being re-sent to Cloud.' ) && false !== strpos( $recovery_html, 'Syncing' ) && false === strpos( $recovery_html, '>Sync<' ) && false === strpos( $recovery_html, 'were dropped' )
			: false !== strpos( $recovery_html, 'Needs attention' ) && false !== strpos( $recovery_html, '>Sync<' ),
		'Behavior: recovery progress replaces historical-drop warnings while retrying or blocked delivery keeps recovery visible: ' . $maintenance_state
	);
}

$dropped_status['dropped_count'] = 0;
$dropped_status['last_dropped_at'] = '';
ob_start();
$site_knowledge_renderer->invoke(
	null,
	$dropped_status,
	Npcink_Cloud_Addon_Settings::get_settings(),
	true
);
$dropped_cleared_rendered = (string) ob_get_clean();
maca_assert(
	false === strpos( $dropped_cleared_rendered, 'were dropped' )
	&& false === strpos( $dropped_cleared_rendered, 'Update again' )
	&& false !== strpos( $dropped_cleared_rendered, 'Up to date' )
	&& false === strpos( $dropped_cleared_rendered, 'All public content is up to date' ),
	'Behavior: the dropped-change recovery entry hides again once the dropped fact is cleared.'
);

$test_links = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_generation_test_links' );
$test_links->setAccessible( true );
$GLOBALS['_registered_pages'] = array( 'tools_page_ai-abilities-explorer' => true, 'tools_page_ai-request-logs' => true );
$previous_abilities = $GLOBALS['maca_abilities'] ?? array();
$GLOBALS['maca_abilities'] = array( 'ai/title-generation' => new stdClass() );
$http_before = count( $GLOBALS['maca_http_requests'] );
ob_start();
$test_links->invoke( null );
$test_links_html = (string) ob_get_clean();
maca_assert(
	str_contains( $test_links_html, 'WordPress AI generation tests' )
	&& str_contains( $test_links_html, 'npcink-cloud-generation-tests' )
	&& str_contains( $test_links_html, '<details' )
	&& ! str_contains( $test_links_html, 'npcink-cloud-generation-tests" open' )
	&& ! str_contains( $test_links_html, 'npcink-cloud-generation-tests open' )
	&& str_contains( $test_links_html, 'data-npcink-gen-open="ai/title-generation"' )
	&& ! str_contains( $test_links_html, 'data-npcink-gen-open="ai/image-generation"' )
	&& str_contains( $test_links_html, 'npcink-cloud-gen-test-dialog' )
	&& str_contains( $test_links_html, 'ai-request-logs' )
	&& str_contains( $test_links_html, 'May use credits' )
	&& str_contains( $test_links_html, 'Test text' )
	&& str_contains( $test_links_html, 'npcink-cloud-gen-test-dialog__advanced' )
	&& str_contains( $test_links_html, 'Advanced: Open Ability Explorer' )
	&& str_contains( $test_links_html, 'page=ai-abilities-explorer' )
	&& ! str_contains( $test_links_html, 'npcink-cloud-generation-tests__advanced' )
	&& substr_count( $test_links_html, 'data-npcink-gen-close' ) === 1
	&& ! str_contains( $test_links_html, 'npcink-cloud-gen-test-dialog__corner' )
	&& str_contains( $test_links_html, 'npcink-cloud-text-action' )
	&& ! str_contains( $test_links_html, 'button button-secondary' )
	&& ! str_contains( $test_links_html, 'target="_blank"' )
	&& $http_before === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: generation tests fold by default, open in-page modal actions for registered abilities, keep Advanced Explorer only inside the modal action row, disclose cost, stay same-window, and perform zero requests on render.'
);

// Re-pause only this surface to prove the guard path still works without ACTIVE=false.
$admin_surfaces = new ReflectionProperty( Npcink_Cloud_Discoverability_Pause::class, 'paused_admin_surfaces' );
$admin_surfaces->setAccessible( true );
$previous_admin_surfaces = $admin_surfaces->getValue();
$admin_surfaces->setValue( null, array( 'wordpress_ai_generation_tests' => true ) );
ob_start();
$test_links->invoke( null );
$re_paused_html = (string) ob_get_clean();
maca_assert(
	'' === trim( $re_paused_html )
	&& $http_before === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: wordpress_ai_generation_tests can still be paused alone without flipping ACTIVE.'
);
$admin_surfaces->setValue( null, $previous_admin_surfaces );

$GLOBALS['_registered_pages'] = array();
ob_start();
$test_links->invoke( null );
$missing_on_checks = (string) ob_get_clean();
maca_assert(
	str_contains( $missing_on_checks, 'Install the WordPress AI plugin' )
	|| str_contains( $missing_on_checks, 'Activate the WordPress AI plugin' ),
	'Behavior: unpaused Checks surface shows missing-plugin guidance when host AI tools are absent.'
);
$GLOBALS['_registered_pages'] = array();
ob_start();
$test_links->invoke( null, true );
$missing_plugin_guidance = (string) ob_get_clean();
maca_assert(
	! str_contains( $missing_plugin_guidance, 'tools.php?' )
	&& str_contains( $missing_plugin_guidance, 'plugin=ai' )
	&& str_contains( $missing_plugin_guidance, 'Install the WordPress AI plugin' )
	&& ! str_contains( $missing_plugin_guidance, 'options-general.php?page=ai-wp-admin' ),
	'Behavior: missing host plugin leads to its official installation page, without broken settings links.'
);
$GLOBALS['_registered_pages'] = array( 'settings_page_ai-wp-admin' => true );
ob_start();
$test_links->invoke( null, true );
$active_guidance_html = (string) ob_get_clean();
maca_assert( '' === trim( $active_guidance_html ), 'Behavior: the overview hides missing-plugin guidance when the official AI settings page is registered.' );
$GLOBALS['_registered_pages'] = array();
$GLOBALS['maca_abilities'] = $previous_abilities;

// Dropped-content recovery uses the existing Site Knowledge update action.
maca_reset_test_state();
maca_seed_settings( true );
$render_summary = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_connection_summary' );
$render_overview = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_overview_page' );
if ( PHP_VERSION_ID < 80100 ) {
	$render_summary->setAccessible( true );
	$render_overview->setAccessible( true );
}
foreach ( array( array(), array( 'dropped_count' => 1 ), array( 'last_delivery_error' => 'Failed' ), array( 'last_error_code' => 'fixture_failed' ) ) as $delivery ) {
	$settings = Npcink_Cloud_Addon_Settings::get_settings();
	$state = Npcink_Cloud_Addon_Settings::get_credential_state();
	$entitlement = array( 'available' => true, 'stale' => true, 'state' => 'stale' );
	$http_before = count( $GLOBALS['maca_http_requests'] );
	ob_start();
	$render_summary->invoke( null, $settings, $state, $entitlement, $delivery );
	$summary_html = (string) ob_get_clean();
	ob_start();
	$render_overview->invoke( null, $settings, $state, $entitlement, array(), $delivery, true );
	$overview_html = (string) ob_get_clean();
	$dropped = 0 < absint( $delivery['dropped_count'] ?? 0 );
	$sk_auth = '' !== (string) ( $delivery['last_delivery_error'] ?? '' ) || '' !== (string) ( $delivery['last_error_code'] ?? '' );
	if ( empty( $delivery ) ) {
		maca_assert(
			false !== strpos( $summary_html, 'out of date' )
			&& false !== strpos( $summary_html, 'name="action" value="npcink_cloud_addon_save"' )
			&& false === strpos( $overview_html, 'Site Knowledge needs attention' )
			&& $http_before === count( $GLOBALS['maca_http_requests'] ),
			'Behavior: stale warning renders without Cloud reads and dropped content exposes the Site Knowledge recovery action.'
		);
	} elseif ( $dropped ) {
		// Product drops: Overview notice only; summary still shows independent stale connection.
		maca_assert(
			false !== strpos( $summary_html, 'out of date' )
			&& false !== strpos( $summary_html, 'name="action" value="npcink_cloud_addon_save"' )
			&& false === strpos( $summary_html, 'name="action" value="npcink_cloud_addon_refresh_site_knowledge"' )
			&& false !== strpos( $overview_html, 'Site Knowledge needs attention' )
			&& false !== strpos( $overview_html, 'View Site Knowledge status' )
			&& $http_before === count( $GLOBALS['maca_http_requests'] ),
			'Behavior: stale warning renders without Cloud reads and dropped content exposes the Site Knowledge recovery action.'
		);
	} else {
		// Connection/auth SK failure: top summary only (not duplicated as Overview notice).
		maca_assert(
			false !== strpos( $summary_html, 'Needs attention' )
			&& false !== strpos( $summary_html, 'name="action" value="npcink_cloud_addon_save"' )
			&& false === strpos( $overview_html, 'Site Knowledge needs attention' )
			&& false !== strpos( $summary_html, 'dashicons-external' )
			&& $http_before === count( $GLOBALS['maca_http_requests'] ),
			'Behavior: stale warning renders without Cloud reads and dropped content exposes the Site Knowledge recovery action.'
		);
	}
}

foreach ( array( 'queued', 'delivering', 'blocked' ) as $maintenance_state ) {
	$delivery = array( 'dropped_count' => 11, 'maintenance_status' => $maintenance_state );
	ob_start();
	$render_summary->invoke( null, $settings, $state, array( 'available' => true, 'stale' => false ), $delivery );
	$summary_html = (string) ob_get_clean();
	ob_start();
	$render_overview->invoke( null, $settings, $state, array( 'available' => true, 'stale' => false ), array(), $delivery, true );
	$overview_html = (string) ob_get_clean();
	// Dropped-content attention lives on Overview / Site Knowledge, not the connection summary.
	maca_assert(
		'' === $summary_html
		&& ( 'blocked' === $maintenance_state
			? false !== strpos( $overview_html, 'Site Knowledge needs attention' )
			: false === strpos( $overview_html, 'Site Knowledge needs attention' ) ),
		'Behavior: healthy recovery hides the page-level historical warning, and a blocked recovery restores it: ' . $maintenance_state
	);
}

ob_start();
$render_summary->invoke( null, $settings, $state, array( 'available' => true, 'stale' => true ), array( 'dropped_count' => 11, 'maintenance_status' => 'delivering' ) );
$stale_recovery_summary = (string) ob_get_clean();
maca_assert(
	false !== strpos( $stale_recovery_summary, 'out of date' )
	&& false !== strpos( $stale_recovery_summary, 'Check connection' )
	&& false === strpos( $stale_recovery_summary, 'Update again' ) && false === strpos( $stale_recovery_summary, '>Sync<' ),
	'Behavior: independent stale-connection evidence keeps its connection action while dropped-content recovery is already running.'
);

// AJAX status refreshes must update whole projections without hiding independent failures.
maca_reset_test_state();
maca_seed_settings( true );
Npcink_Cloud_Entitlement_Summary::cache_summary_from_response( array( 'data' => array( 'package' => 'Pro' ) ), Npcink_Cloud_Addon_Settings::get_settings() );
$GLOBALS['maca_transients'][ $status_cache_key ] = array(
	'available' => true, 'indexed_documents' => 500, 'max_documents' => 10000,
	'article_coverage' => array( 'not_indexed_count' => 0 ),
	'fresh_until' => gmdate( 'Y-m-d H:i:s', time() + 1800 ) . ' UTC',
);
$http_before_fragments = count( $GLOBALS['maca_http_requests'] );
$healthy_fragments = Npcink_Cloud_Settings_Page::get_admin_status_fragments( true );
maca_assert(
	'' === trim( $healthy_fragments['connection'] )
	&& false !== strpos( $healthy_fragments['knowledge'], 'Up to date' )
	&& false === strpos( $healthy_fragments['knowledge'], 'All public content is up to date' )
	&& false !== strpos( $healthy_fragments['knowledge'], '>Sync<' )
	&& false !== strpos( $healthy_fragments['knowledge'], 'Open Npcink Cloud' )
	&& false === strpos( $healthy_fragments['knowledge'], 'Update again' )
	&& false === strpos( $healthy_fragments['knowledge'], 'npcink-cloud-info-hint' )
	&& false !== strpos( $healthy_fragments['knowledge'], 'data-npcink-site-knowledge-status-retry' )
	&& $http_before_fragments === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: refreshed healthy fragments clear historical recovery controls and reuse escaped renderers with zero extra Cloud requests.'
);
update_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION, array( 'dropped_count' => 3 ), false );
$failed_fragments = Npcink_Cloud_Settings_Page::get_admin_status_fragments( true );
maca_assert(
	'' === trim( $failed_fragments['connection'] )
	&& false !== strpos( $failed_fragments['knowledge'], '>Sync<' )
	&& false === strpos( $failed_fragments['knowledge'], 'Update again' )
	&& 'ok' === $failed_fragments['service']['severity']
	&& false !== strpos( $failed_fragments['knowledge'], 'were dropped' ),
	'Behavior: a fresh Cloud read does not erase an independent local dropped-delivery warning.'
);
update_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION, array(), false );
$GLOBALS['maca_transients'][ $status_cache_key ]['fresh_until'] = gmdate( 'Y-m-d H:i:s', time() - 10 ) . ' UTC';
$stale_fragments = Npcink_Cloud_Settings_Page::get_admin_status_fragments( true );
maca_assert(
	false === strpos( $stale_fragments['knowledge'], 'All public content is up to date' )
	&& false === strpos( $stale_fragments['knowledge'], 'npcink-cloud-badge--ok' ),
	'Behavior: expired Cloud knowledge evidence cannot claim that all public content is current.'
);

$usage_with_used = Npcink_Cloud_Settings_Page::get_overview_entitlement_metrics( array( 'ai_credit_usage_detail' => array( 'available' => true, 'summary' => array( 'limit' => 20000, 'remaining' => 18170, 'used' => 1830 ) ) ) );
$usage_without_used = Npcink_Cloud_Settings_Page::get_overview_entitlement_metrics( array( 'ai_credit_usage_detail' => array( 'available' => true, 'summary' => array( 'limit' => 20000, 'remaining' => 18170 ) ) ) );
$usage_unavailable = Npcink_Cloud_Settings_Page::get_overview_entitlement_metrics( array() );
maca_assert(
	false !== strpos( (string) $usage_with_used['credits']['usage_line'], '1,830' )
	&& false !== strpos( (string) $usage_with_used['credits']['usage_line'], '18,170' )
	&& '' === (string) $usage_without_used['credits']['usage_line']
	&& '' === (string) $usage_unavailable['credits']['usage_line']
	&& false === stripos( (string) $usage_with_used['credits']['usage_line'], 'about' )
	&& false !== strpos( maca_read( dirname( __DIR__ ) . '/includes/class-cloud-settings-page.php' ), 'npcink-cloud-usage-line' ),
	'Behavior: Overview usage line uses only Cloud-reported used/remaining credits, never estimates, and hides when unavailable.'
);
