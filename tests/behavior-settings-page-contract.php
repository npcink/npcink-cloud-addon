<?php
/**
 * Behavior contracts for the Cloud Addon settings-page facade.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

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

if ( ! function_exists( 'esc_attr_e' ) ) {
	function esc_attr_e( string $text, string $domain = '' ): void {
		echo esc_attr( __( $text, $domain ) );
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
	'add_attachment' => array( 'handle_media_attachment_changed', 10 ),
	'edit_attachment' => array( 'handle_media_attachment_changed', 10 ),
	'admin_menu' => array( 'add_menu_page', 50 ),
	'admin_enqueue_scripts' => array( 'enqueue_admin_assets', 10 ),
	'admin_post_npcink_cloud_addon_save' => array( 'handle_save', 10 ),
	'admin_post_npcink_cloud_addon_complete_auth' => array( 'handle_complete_auth', 10 ),
	'admin_post_npcink_cloud_addon_start_auth' => array( 'handle_start_auth', 10 ),
	'admin_post_npcink_cloud_addon_start_custom_auth' => array( 'handle_start_custom_auth', 10 ),
	'admin_post_npcink_cloud_addon_disconnect' => array( 'handle_disconnect', 10 ),
	'admin_post_npcink_cloud_addon_update_local_permission' => array( 'handle_update_local_permission', 10 ),
	'admin_post_npcink_cloud_addon_dismiss_monitoring_prompt' => array( 'handle_dismiss_monitoring_prompt', 10 ),
	'admin_post_npcink_cloud_addon_refresh_site_knowledge' => array( 'handle_refresh_site_knowledge', 10 ),
	'admin_post_npcink_cloud_addon_refresh_site_media_index' => array( 'handle_refresh_site_media_index', 10 ),
	'admin_post_npcink_cloud_addon_refresh_site_media_status' => array( 'handle_refresh_site_media_status', 10 ),
	'wp_ajax_npcink_cloud_addon_poll_site_media_status' => array( 'handle_poll_site_media_status', 10 ),
	'npcink_cloud_addon_continue_media_recognition' => array( 'process_media_recognition_plan', 10 ),
	'wp_ajax_npcink_cloud_addon_refresh_site_knowledge_status' => array( 'handle_refresh_site_knowledge_status', 10 ),
	'admin_post_npcink_cloud_addon_manage_site_knowledge_index' => array( 'handle_manage_site_knowledge_index', 10 ),
	'admin_post_npcink_cloud_addon_run_manual_readiness_test' => array( 'handle_run_manual_readiness_test', 10 ),
	'wp_ajax_npcink_cloud_addon_refresh_entitlement' => array( 'handle_refresh_entitlement', 10 ),
);

$registered_hook_names = array_keys( $GLOBALS['maca_actions'] );
sort( $registered_hook_names );
$expected_hook_names = array_keys( $expected_hooks );
sort( $expected_hook_names );

maca_assert(
	$expected_hook_names === $registered_hook_names,
	'Behavior: settings facade registers the complete, stable admin hook and action-name contract.'
);

foreach ( $expected_hooks as $hook_name => $hook_contract ) {
	list( $method, $priority ) = $hook_contract;
	$registration = $GLOBALS['maca_actions'][ $hook_name ][ $priority ][0] ?? array();
	$callback = $registration['callback'] ?? null;
	maca_assert(
		is_array( $callback )
		&& Npcink_Cloud_Settings_Page::class === ( $callback[0] ?? null )
		&& $method === ( $callback[1] ?? null )
		&& is_callable( $callback )
		&& 1 === (int) ( $registration['accepted_args'] ?? 0 ),
		'Behavior: settings hook remains callable: ' . $hook_name
	);
}

$authorization_url_builder = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'build_authorization_url_for_base_url' );
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

$authorization_exchange = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'exchange_authorization_code' );
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
	&& false !== strpos( $capacity_error->get_error_message(), 'Deactivate another active site' )
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
	&& false !== strpos( $expired_error->get_error_message(), 'Start the connection again from WordPress' )
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
	&& false !== strpos( $rendered, 'Free service and AI credits belong to the Cloud account selected during authorization' )
	&& $http_before_render === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: the representative unconfigured admin render explains account-owned Free service and performs zero outbound HTTP requests.'
);

$tab_url_builder = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'tab_url' );
$page_form_action_builder = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'page_form_action_url' );
$tab_url_builder->setAccessible( true );
$page_form_action_builder->setAccessible( true );
maca_assert(
	'https://wordpress.example.test/wp-admin/options-general.php?page=npcink-cloud-addon&tab=site_knowledge' === $tab_url_builder->invoke( null, 'site_knowledge' )
	&& 'https://wordpress.example.test/wp-admin/options-general.php' === $page_form_action_builder->invoke( null ),
	'Behavior: standalone settings links and GET forms stay on the registered options-general.php page.'
);

$settings_page_source = file_get_contents( MACA_TEST_ROOT . '/includes/class-cloud-settings-page.php' );
$permissions_script_source = file_get_contents( MACA_TEST_ROOT . '/assets/admin-permissions.js' );
maca_assert(
	is_string( $settings_page_source )
	&& is_string( $permissions_script_source )
	&& false !== strpos( $settings_page_source, 'set_local_permission_feedback' )
	&& false !== strpos( $settings_page_source, 'redirect_to_local_permission' )
	&& false !== strpos( $settings_page_source, 'data-npcink-local-permission-feedback' )
	&& false !== strpos( $permissions_script_source, "form.setAttribute( 'aria-busy', 'true' )" )
	&& false !== strpos( $permissions_script_source, 'scrollIntoView' ),
	'Behavior: local permission switches expose a saving state, row-level feedback, and focus restoration after redirect.'
);

maca_assert(
	false !== strpos( $settings_page_source, 'npcink-cloud-monitoring-consent' )
	&& false !== strpos( $settings_page_source, 'Allow anonymous diagnostics' )
	&& false !== strpos( $settings_page_source, 'Not now' )
	&& false !== strpos( $settings_page_source, 'maybe_prompt_for_monitoring_consent' ),
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
	&& false !== strpos( $consent_rendered, 'Cloud connection verified' )
	&& false !== strpos( $consent_rendered, 'Allow anonymous diagnostics' )
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
	false !== strpos( $inactive_rendered, 'Connected, activation required' )
	&& false !== strpos( $inactive_rendered, 'Activate this site in Cloud' )
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
	false !== strpos( $overview_rendered, 'Connection and service' )
	&& false !== strpos( $overview_rendered, 'Connected' )
	&& false !== strpos( $overview_rendered, 'npcink-cloud-overview-service' )
	&& false !== strpos( $overview_rendered, 'Open Cloud' )
	&& false === strpos( $overview_rendered, '<section class="npcink-cloud-summary">' )
	&& false === strpos( $overview_rendered, 'Last verification succeeded' )
	&& false === strpos( $overview_rendered, 'Current service' )
	&& false === strpos( $overview_rendered, 'A cached signed Cloud read is available.' )
	&& false !== strpos( $overview_rendered, 'Available knowledge documents' )
	&& false !== strpos( $overview_rendered, 'data-npcink-site-knowledge-usage' )
	&& false !== strpos( $overview_rendered, 'Features' )
	&& false !== strpos( $overview_rendered, 'Enable Site Knowledge' )
	&& false !== strpos( $overview_rendered, 'Privacy settings' )
	&& false !== strpos( $overview_rendered, 'Send anonymous diagnostics' )
	&& false === strpos( $overview_rendered, '>WordPress AI connector<' )
	&& false === strpos( $overview_rendered, '>Reference site content during generation<' )
	&& false !== strpos( $overview_rendered, 'AI credits shown here belong to the connected Cloud account' )
	&& $http_before_overview === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: the verified Overview owns the compact healthy connection summary and usage without a duplicate global card or Cloud HTTP.'
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
	&& isset( $tabs_with_site_knowledge['site_knowledge'] ),
	'Behavior: the Site Knowledge menu appears only while automatic Site Knowledge delivery is enabled.'
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

$advanced_renderer = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_advanced_page' );
if ( PHP_VERSION_ID < 80100 ) {
	$advanced_renderer->setAccessible( true );
}
ob_start();
$advanced_renderer->invoke(
	null,
	Npcink_Cloud_Addon_Settings::get_settings(),
	Npcink_Cloud_Addon_Settings::get_credential_state(),
	array( 'available' => false ),
	Npcink_Cloud_Observability_Collector::get_status(),
	true
);
$advanced_rendered = (string) ob_get_clean();
maca_assert(
	false !== strpos( $advanced_rendered, 'Service details' )
	&& false !== strpos( $advanced_rendered, 'Checks' )
	&& false !== strpos( $advanced_rendered, 'Connection management' )
	&& false === strpos( $advanced_rendered, 'Runtime runs' )
	&& false === strpos( $advanced_rendered, 'Cloud runtime runs' )
	&& false === strpos( $advanced_rendered, 'Inspect by run ID' )
	&& false === strpos( $advanced_rendered, 'Load recent runs' )
	&& false === strpos( $advanced_rendered, 'Read status' )
	&& false === strpos( $advanced_rendered, 'Read result' )
	&& false === strpos( $advanced_rendered, 'Request Cloud retry' ),
	'Behavior: Advanced keeps service, checks, and connection management while runtime run operations remain Cloud-only.'
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
	&& false !== strpos( $connection_management_rendered, 'Change Cloud account' )
	&& false !== strpos( $connection_management_rendered, 'button button-primary' )
	&& false !== strpos( $connection_management_rendered, 'Disconnect this site' )
	&& false !== strpos( $connection_management_rendered, 'The site and its data will remain in Cloud.' )
	&& false === strpos( $connection_management_rendered, 'Check connection' )
	&& false === strpos( $connection_management_rendered, 'Re-verify and refresh' )
	&& strpos( $connection_management_rendered, 'Change Cloud account' ) < strpos( $connection_management_rendered, 'Disconnect this site' )
	&& $http_before_connection_management === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: Connection Management presents one Cloud account action and separates the local destructive action without Cloud HTTP.'
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
		&& false !== strpos( $site_knowledge_rendered, 'Knowledge base status' )
		&& false === strpos( $site_knowledge_rendered, 'Manual update' )
		&& false === strpos( $site_knowledge_rendered, '<summary>Technical details</summary>' )
		&& false === strpos( $site_knowledge_rendered, 'npcink-cloud-site-knowledge-quota-detail' )
		&& false === strpos( $site_knowledge_rendered, 'data-npcink-site-knowledge-detail="chunks"' )
		&& false === strpos( $site_knowledge_rendered, 'title="More actions"' )
	&& false !== strpos( $site_knowledge_rendered, 'Knowledge base maintenance' )
	&& false !== strpos( $site_knowledge_rendered, 'https://cloud.example.test/portal/sites/site_test#site-knowledge' )
	&& false === strpos( $site_knowledge_rendered, 'Automatic updates on' )
	&& false === strpos( $site_knowledge_rendered, 'Change settings' )
	&& false === strpos( $site_knowledge_rendered, '/portal/site-knowledge' )
	&& false === strpos( $site_knowledge_rendered, 'Filter articles by index status' )
	&& false === strpos( $site_knowledge_rendered, 'View content list' )
	&& false === strpos( $site_knowledge_rendered, 'Pending content (' )
		&& false !== strpos( $site_knowledge_rendered, 'Start media recognition' )
		&& false !== strpos( $site_knowledge_rendered, 'data-npcink-site-media-status' )
		&& false !== strpos( $site_knowledge_rendered, 'data-npcink-site-media-progress' )
		&& false !== strpos( $site_knowledge_rendered, 'data-npcink-site-media-capacity' )
		&& false !== strpos( $site_knowledge_rendered, '320 used / 5,000 limit / 4,680 remaining' )
		&& false !== strpos( $site_knowledge_rendered, 'data-npcink-site-media-eta' )
		&& false !== strpos( $site_knowledge_rendered, 'View recognition details' )
		&& false === strpos( $site_knowledge_rendered, 'npcink-cloud-inline-info' )
		&& $http_before_site_knowledge === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: healthy Site Knowledge keeps maintenance and Cloud links, removes the duplicate Settings link, and hides manual recovery without Cloud HTTP.'
);

Npcink_Cloud_Entitlement_Summary::cache_summary_from_response(
	array(
		'data' => array(
			'package' => 'Free',
			'quota_summary' => array(
				'resource_limits' => array(
					array(
						'key' => 'media_images',
						'used' => 100,
						'limit' => 100,
						'remaining' => 0,
						'status' => 'limited',
						'unit' => 'image',
					),
				),
			),
		),
	),
	Npcink_Cloud_Addon_Settings::get_settings()
);
ob_start();
$site_knowledge_renderer->invoke(
	null,
	Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot(),
	Npcink_Cloud_Addon_Settings::get_settings(),
	true
);
$media_capacity_full_rendered = (string) ob_get_clean();
maca_assert(
	false !== strpos( $media_capacity_full_rendered, '100 used / 100 limit / 0 remaining' )
	&& false !== strpos( $media_capacity_full_rendered, 'Your plan image capacity is full.' ),
	'Behavior: a Cloud-reported full media capacity is shown as one actionable read-only warning without local quota calculation.'
);

maca_assert(
	false !== strpos( $settings_page_source, "check_ajax_referer( self::ACTION_POLL_SITE_MEDIA_STATUS, 'nonce' )" )
	&& false !== strpos( $settings_page_source, '$client->get_run( $run_id )' )
	&& false !== strpos( $settings_page_source, '$client->get_run_result( $run_id )' )
	&& false !== strpos( $settings_page_source, 'is_array( $run[\'data\'] ?? null )' )
	&& false !== strpos( $settings_page_source, 'self::resume_active_media_recognition_plan()' )
	&& strpos( $settings_page_source, 'self::resume_active_media_recognition_plan()' ) < strpos( $settings_page_source, 'Npcink_Cloud_Site_Knowledge_Admin_Actions::request_media_index_refresh( $page, $per_page )' )
	&& false === strpos( $settings_page_source, 'handle_poll_site_media_status(): void {\n\t\t\tself::handle_refresh_site_media_index' ),
	'Behavior: media progress polling is read-only, while repeated starts resume the durable plan before any new batch dispatch.'
);

$site_knowledge_processing = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
$site_knowledge_processing['buffer_count'] = 1;
$site_knowledge_processing['last_delivery_error'] = '';
$site_knowledge_processing['last_error_code'] = '';
$site_knowledge_processing['wp_cron_disabled'] = false;
ob_start();
$site_knowledge_renderer->invoke(
	null,
	$site_knowledge_processing,
	Npcink_Cloud_Addon_Settings::get_settings(),
	true
);
$site_knowledge_processing_rendered = (string) ob_get_clean();

maca_assert(
	false !== strpos( $site_knowledge_processing_rendered, 'Updating the knowledge base' )
	&& false === strpos( $site_knowledge_processing_rendered, 'Manual update' ),
	'Behavior: Site Knowledge hides the low-frequency manual action while automatic updates are in progress.'
);

$site_knowledge_attention = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
$site_knowledge_attention['buffer_count'] = 0;
$site_knowledge_attention['last_delivery_error'] = 'transport_failed';
$site_knowledge_attention['last_error_code'] = 'transport_failed';
ob_start();
$site_knowledge_renderer->invoke(
	null,
	$site_knowledge_attention,
	Npcink_Cloud_Addon_Settings::get_settings(),
	true
);
$site_knowledge_attention_rendered = (string) ob_get_clean();

maca_assert(
	false !== strpos( $site_knowledge_attention_rendered, 'Knowledge base update needs attention' )
	&& false !== strpos( $site_knowledge_attention_rendered, 'View advanced troubleshooting' )
	&& false !== strpos( $site_knowledge_attention_rendered, 'Update again' )
	&& false === strpos( $site_knowledge_attention_rendered, 'Manual update' )
	&& false === strpos( $site_knowledge_attention_rendered, 'Buffered public changes' )
	&& false === strpos( $site_knowledge_attention_rendered, '<summary>Technical details</summary>' ),
	'Behavior: Site Knowledge exposes one recovery update only after a local delivery failure and keeps technical tables off the default page.'
);

maca_reset_test_state();
maca_seed_settings( true );
set_transient(
	'npcink_cloud_addon_media_index_status',
	array(
		'state' => 'processing',
		'run_id' => 'run_media_page_2',
		'page' => 2,
		'next_page' => 3,
		'has_more' => true,
		'total' => 25,
		'indexed' => 17,
		'completed_before' => 17,
		'successful_before' => 16,
		'failed_before' => 0,
		'evidence_before' => 14,
		'duration_before' => 30,
		'batch_size' => 3,
		'reused_count' => 6,
		'screened_count' => 1,
	),
	DAY_IN_SECONDS
);
update_option(
	'npcink_cloud_addon_media_recognition_plan',
	array(
		'active' => true,
		'plan_id' => 'media_plan_partial_failure',
		'state' => 'processing',
		'current_run_id' => 'run_media_page_2',
		'current_page' => 2,
		'next_page' => 3,
		'per_page' => 10,
		'processed_count' => 10,
	),
	false
);
$GLOBALS['maca_http_response_queue'] = array(
	array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array( 'run_id' => 'run_media_page_2', 'status' => 'succeeded', 'error_code' => '' ) ) ),
	),
	array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode(
			array(
				'status' => 'ok',
				'data' => array(
					'run_id' => 'run_media_page_2',
					'status' => 'succeeded',
					'result' => array(
						'progress' => array( 'processed_items' => 3, 'successful_items' => 2, 'failed_items' => 1, 'total_items' => 3, 'percent' => 100, 'duration_seconds' => 45, 'items_per_minute' => 12 ),
						'items' => array_fill( 0, 2, array( 'visual_summary' => 'visual evidence' ) ),
					),
				),
			)
		),
	),
);
$media_status_refresher = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'refresh_media_index_status_projection' );
if ( PHP_VERSION_ID < 80100 ) {
	$media_status_refresher->setAccessible( true );
}
$media_status_projection = $media_status_refresher->invoke( null );
$media_partial_failure_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
maca_assert(
	is_array( $media_status_projection )
	&& 'error' === $media_status_projection['state']
	&& 'media_recognition_batch_partial_failure' === $media_status_projection['error_code']
	&& 10 === $media_status_projection['indexed']
	&& 25 === $media_status_projection['total']
	&& 40 === $media_status_projection['percent']
	&& 10 === $media_status_projection['successful']
	&& 1 === $media_status_projection['failed']
	&& 8 === $media_status_projection['evidence']
	&& 2 === $media_status_projection['page']
	&& 3 === $media_status_projection['next_page']
	&& empty( $media_partial_failure_plan['active'] )
	&& 2 === ( $media_partial_failure_plan['current_page'] ?? 0 )
	&& 10 === ( $media_partial_failure_plan['processed_count'] ?? 0 )
	&& 2 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: a terminal Cloud batch with failed items rolls back reusable and screened polling baselines to the committed page cursor.'
);

$media_plan_per_page = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'media_recognition_plan_per_page' );
if ( PHP_VERSION_ID < 80100 ) {
	$media_plan_per_page->setAccessible( true );
}
maca_assert(
	2 === $media_plan_per_page->invoke( null, array( 'per_page' => 2 ), array( 'batch_size' => 10 ) )
	&& 10 === $media_plan_per_page->invoke( null, array(), array( 'batch_size' => 10 ) ),
	'Behavior: automatic continuation keeps the active plan page size and only falls back to the existing batch size for legacy plans.'
);

/** Seeds one active media-recognition plan and its local status projection. */
function maca_seed_media_recognition_plan( array $status, array $plan = array() ): void {
	set_transient(
		'npcink_cloud_addon_media_index_status',
		array_merge(
			array(
				'state' => 'partial',
				'indexed' => 10,
				'total' => 30,
				'page' => 1,
				'next_page' => 2,
				'has_more' => true,
				'batch_size' => 10,
				'per_page' => 10,
			),
			$status
		),
		DAY_IN_SECONDS
	);
	update_option(
		'npcink_cloud_addon_media_recognition_plan',
		array_merge(
			array(
				'active' => true,
				'plan_id' => 'media_plan_test',
				'initiated_by' => 1,
				'state' => 'partial',
				'current_page' => 1,
				'next_page' => 2,
				'per_page' => 10,
				'processed_count' => 10,
			),
			$plan
		),
		false
	);
}

$resume_media_plan = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'resume_active_media_recognition_plan' );
if ( PHP_VERSION_ID < 80100 ) {
	$resume_media_plan->setAccessible( true );
}

maca_reset_test_state();
maca_seed_settings( false );
Npcink_Cloud_Settings_Page::handle_media_attachment_changed( 701 );
maca_assert(
	empty( $GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan'] )
	&& empty( $GLOBALS['maca_scheduled_events'] ),
	'Behavior: attachment changes do not create background recognition work until Cloud settings are verified.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_site_knowledge_delivery_enabled( false );
Npcink_Cloud_Settings_Page::handle_media_attachment_changed( 701 );
$GLOBALS['maca_non_image_attachment_ids'] = array( 702 );
Npcink_Cloud_Settings_Page::handle_media_attachment_changed( 702 );
maca_assert(
	empty( $GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan'] )
	&& empty( $GLOBALS['maca_scheduled_events'] ),
	'Behavior: disabled Site Knowledge delivery and non-image attachments never start automatic media recognition.'
);

maca_reset_test_state();
maca_seed_settings( true );
Npcink_Cloud_Settings_Page::handle_media_attachment_changed( 701 );
$automatic_media_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
$automatic_media_status = get_transient( 'npcink_cloud_addon_media_index_status' );
maca_assert(
	! empty( $automatic_media_plan['active'] )
	&& 'partial' === ( $automatic_media_plan['state'] ?? '' )
	&& 1 === ( $automatic_media_plan['next_page'] ?? 0 )
	&& array( 701 ) === ( $automatic_media_plan['rescan_attachment_ids'] ?? array() )
	&& array() === ( $automatic_media_plan['pending_rescan_attachment_ids'] ?? null )
	&& is_array( $automatic_media_status )
	&& 'partial' === ( $automatic_media_status['state'] ?? '' )
	&& isset( $GLOBALS['maca_scheduled_events']['npcink_cloud_addon_continue_media_recognition'] )
	&& empty( $GLOBALS['maca_http_requests'] ),
	'Behavior: a verified attachment change queues one fresh inventory pass through the existing media-plan Cron without inline Cloud traffic.'
);

Npcink_Cloud_Settings_Page::handle_media_attachment_changed( 701 );
$deduplicated_media_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
maca_assert(
	array( 701 ) === ( $deduplicated_media_plan['rescan_attachment_ids'] ?? array() )
	&& array() === ( $deduplicated_media_plan['pending_rescan_attachment_ids'] ?? null ),
	'Behavior: repeated add/edit attachment hooks for the same image do not queue a second rescan.'
);

Npcink_Cloud_Settings_Page::handle_media_attachment_changed( 702 );
$pending_media_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
maca_assert(
	1 === ( $pending_media_plan['current_page'] ?? 0 )
	&& 1 === ( $pending_media_plan['next_page'] ?? 0 )
	&& array( 702 ) === ( $pending_media_plan['pending_rescan_attachment_ids'] ?? array() ),
	'Behavior: a different attachment change during an active pass records one pending rescan without moving the current cursor.'
);

set_transient(
	'npcink_cloud_addon_media_index_status',
	array( 'state' => 'complete', 'has_more' => false, 'next_page' => 0, 'total' => 71, 'indexed' => 71 ),
	DAY_IN_SECONDS
);
Npcink_Cloud_Settings_Page::process_media_recognition_plan();
$restarted_media_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
maca_assert(
	! empty( $restarted_media_plan['active'] )
	&& 'partial' === ( $restarted_media_plan['state'] ?? '' )
	&& 1 === ( $restarted_media_plan['current_page'] ?? 0 )
	&& 1 === ( $restarted_media_plan['next_page'] ?? 0 )
	&& array( 702 ) === ( $restarted_media_plan['rescan_attachment_ids'] ?? array() )
	&& array() === ( $restarted_media_plan['pending_rescan_attachment_ids'] ?? null )
	&& isset( $GLOBALS['maca_scheduled_events']['npcink_cloud_addon_continue_media_recognition'] ),
	'Behavior: completing the current pass atomically starts one pending attachment rescan through the same plan and Cron.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_seed_media_recognition_plan( array() );
maca_assert(
	true === $resume_media_plan->invoke( null )
	&& isset( $GLOBALS['maca_scheduled_events']['npcink_cloud_addon_continue_media_recognition'] )
	&& empty( $GLOBALS['maca_http_requests'] ),
	'Behavior: repeating the start intent for an active plan schedules its existing continuation without creating another Cloud batch.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_seed_media_recognition_plan( array() );
$GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan_lock'] = time();
Npcink_Cloud_Settings_Page::process_media_recognition_plan();
$locked_media_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
maca_assert(
	! empty( $locked_media_plan['active'] )
	&& 'partial' === $locked_media_plan['state']
	&& isset( $GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan_lock'] )
	&& empty( $GLOBALS['maca_scheduled_events'] ),
	'Behavior: the site-scoped media-plan lock prevents overlapping Cron callbacks from advancing or rescheduling the same cursor.'
);

maca_reset_test_state();
maca_seed_settings( true );
$already_deferred_until = gmdate( 'c', time() + HOUR_IN_SECONDS );
maca_seed_media_recognition_plan(
	array(),
	array( 'state' => 'waiting_next_day', 'next_eligible_at' => $already_deferred_until )
);
$GLOBALS['maca_media_plan_dispatches'] = 0;
add_filter(
	'npcink_toolbox_refresh_site_media_index_batch',
	static function ( $value, array $input ) {
		unset( $value, $input );
		$GLOBALS['maca_media_plan_dispatches']++;
		return array();
	},
	10,
	2
);
Npcink_Cloud_Settings_Page::process_media_recognition_plan();
$deferred_schedule = absint( $GLOBALS['maca_scheduled_events']['npcink_cloud_addon_continue_media_recognition'] ?? 0 );
maca_assert(
	0 === $GLOBALS['maca_media_plan_dispatches']
	&& abs( $deferred_schedule - strtotime( $already_deferred_until ) ) <= 2,
	'Behavior: a plan with a future Cloud eligibility time only reschedules itself and never submits another batch early.'
);

maca_reset_test_state();
maca_seed_settings( true );
$cloud_eligible_at = gmdate( 'c', time() + HOUR_IN_SECONDS );
maca_seed_media_recognition_plan(
	array( 'state' => 'processing', 'run_id' => 'run_media_deferred' ),
	array( 'state' => 'processing', 'current_run_id' => 'run_media_deferred' )
);
$GLOBALS['maca_http_response_queue'] = array(
	array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array( 'run_id' => 'run_media_deferred', 'status' => 'queued', 'run_lifecycle' => array( 'worker_eligible_at' => $cloud_eligible_at ) ) ) ),
	),
	array(
		'response' => array( 'code' => 409 ),
		'body' => wp_json_encode( array( 'status' => 'error', 'error_code' => 'run_result_not_ready', 'message' => 'Run result is not ready yet.' ) ),
	),
);
Npcink_Cloud_Settings_Page::process_media_recognition_plan();
$waiting_media_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
$waiting_media_status = get_transient( 'npcink_cloud_addon_media_index_status' );
ob_start();
$site_knowledge_renderer->invoke(
	null,
	Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot(),
	Npcink_Cloud_Addon_Settings::get_settings(),
	true
);
$waiting_media_rendered = (string) ob_get_clean();
maca_assert(
	'waiting_next_day' === ( $waiting_media_plan['state'] ?? '' )
	&& $cloud_eligible_at === ( $waiting_media_plan['next_eligible_at'] ?? '' )
	&& 'waiting_next_day' === ( $waiting_media_status['state'] ?? '' )
	&& $cloud_eligible_at === ( $waiting_media_status['next_eligible_at'] ?? '' )
	&& 2 === count( $GLOBALS['maca_http_requests'] )
	&& abs( absint( $GLOBALS['maca_scheduled_events']['npcink_cloud_addon_continue_media_recognition'] ?? 0 ) - strtotime( $cloud_eligible_at ) ) <= 2,
	'Behavior: a Cloud-deferred run enters waiting_next_day and preserves the exact Cloud worker eligibility timestamp.'
);
maca_assert(
	false !== strpos( $waiting_media_rendered, 'Waiting for background processing' )
	&& false !== strpos( $waiting_media_rendered, 'Recognition will continue automatically during the next eligible processing window.' )
	&& 1 === preg_match( '/<button[^>]*disabled=["\'][^"\']*["\'][^>]*>Waiting for background processing<\/button>/', $waiting_media_rendered )
	&& false === strpos( $waiting_media_rendered, '>Recognizing images<' ),
	'Behavior: a Cloud-deferred run is presented as a non-clickable wait for its next eligible processing window rather than inventing a quota reason.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_seed_media_recognition_plan(
	array( 'state' => 'waiting_next_day', 'run_id' => 'run_media_resumed' ),
	array( 'state' => 'waiting_next_day', 'current_run_id' => 'run_media_resumed', 'next_eligible_at' => gmdate( 'c', time() - MINUTE_IN_SECONDS ) )
);
$GLOBALS['maca_http_response_queue'] = array(
	array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array( 'run_id' => 'run_media_resumed', 'status' => 'running' ) ) ),
	),
	array(
		'response' => array( 'code' => 409 ),
		'body' => wp_json_encode( array( 'status' => 'error', 'error_code' => 'run_result_not_ready', 'message' => 'Run result is not ready yet.' ) ),
	),
);
Npcink_Cloud_Settings_Page::process_media_recognition_plan();
$resumed_media_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
maca_assert(
	'processing' === ( $resumed_media_plan['state'] ?? '' )
	&& ! isset( $resumed_media_plan['next_eligible_at'] )
	&& 2 === count( $GLOBALS['maca_http_requests'] )
	&& isset( $GLOBALS['maca_scheduled_events']['npcink_cloud_addon_continue_media_recognition'] ),
	'Behavior: an eligible deferred run returns to processing and keeps polling the same Cloud run without submitting a new batch.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_seed_media_recognition_plan( array() );
$GLOBALS['maca_media_plan_inputs'] = array();
add_filter(
	'npcink_toolbox_refresh_site_media_index_batch',
	static function ( $value, array $input ) {
		unset( $value );
		$GLOBALS['maca_media_plan_inputs'][] = $input;
		return array(
			'indexed_items' => 10,
			'visual_evidence_items' => 0,
			'total' => 30,
			'has_more' => true,
			'visual_evidence_run_id' => 'run_media_page_2',
		);
	},
	10,
	2
);
Npcink_Cloud_Settings_Page::process_media_recognition_plan();
$advanced_media_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
$advanced_media_status = get_transient( 'npcink_cloud_addon_media_index_status' );
maca_assert(
	array( array( 'page' => 2, 'per_page' => 10 ) ) === $GLOBALS['maca_media_plan_inputs']
	&& 'processing' === ( $advanced_media_plan['state'] ?? '' )
	&& 'run_media_page_2' === ( $advanced_media_plan['current_run_id'] ?? '' )
	&& 2 === ( $advanced_media_plan['current_page'] ?? 0 )
	&& 3 === ( $advanced_media_plan['next_page'] ?? 0 )
	&& 'processing' === ( $advanced_media_status['state'] ?? '' )
	&& 'run_media_page_2' === ( $advanced_media_status['run_id'] ?? '' )
	&& isset( $GLOBALS['maca_scheduled_events']['npcink_cloud_addon_continue_media_recognition'] ),
	'Behavior: a partial plan submits exactly one next bounded batch, advances the cursor once, and schedules read-only polling.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_seed_media_recognition_plan( array( 'state' => 'complete', 'has_more' => false, 'next_page' => 0 ) );
Npcink_Cloud_Settings_Page::process_media_recognition_plan();
$completed_media_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
maca_assert(
	empty( $completed_media_plan['active'] )
	&& 'complete' === ( $completed_media_plan['state'] ?? '' )
	&& ! isset( $GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan_lock'] )
	&& empty( $GLOBALS['maca_scheduled_events'] ),
	'Behavior: a complete media projection closes the active plan and releases the single-site lock without another dispatch.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_seed_media_recognition_plan(
	array( 'state' => 'error', 'error_code' => 'provider_failed', 'page' => 3, 'next_page' => 4 ),
	array( 'current_page' => 3, 'next_page' => 4 )
);
Npcink_Cloud_Settings_Page::process_media_recognition_plan();
$failed_media_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
maca_assert(
	empty( $failed_media_plan['active'] )
	&& 'error' === ( $failed_media_plan['state'] ?? '' )
	&& 'provider_failed' === ( $failed_media_plan['pause_reason'] ?? '' )
	&& 3 === ( $failed_media_plan['current_page'] ?? 0 )
	&& 4 === ( $failed_media_plan['next_page'] ?? 0 )
	&& empty( $GLOBALS['maca_scheduled_events'] ),
	'Behavior: a terminal Cloud error stops automatic continuation without advancing or discarding the current cursor.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_seed_media_recognition_plan( array() );
add_filter(
	'npcink_toolbox_refresh_site_media_index_batch',
	static function () {
		return new WP_Error(
			'cloud_media_recognition_batch_exceeds_daily_limit',
			'The recognition batch is larger than the configured daily limit.'
		);
	},
	10,
	2
);
Npcink_Cloud_Settings_Page::process_media_recognition_plan();
$oversized_media_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
$oversized_media_status = get_transient( 'npcink_cloud_addon_media_index_status' );
maca_assert(
	empty( $oversized_media_plan['active'] )
	&& 'paused' === ( $oversized_media_plan['state'] ?? '' )
	&& 'cloud_media_recognition_batch_exceeds_daily_limit' === ( $oversized_media_plan['pause_reason'] ?? '' )
	&& ! isset( $oversized_media_plan['next_eligible_at'] )
	&& 'error' === ( $oversized_media_status['state'] ?? '' )
	&& false !== strpos( (string) ( $oversized_media_status['error'] ?? '' ), 'Reduce the batch or adjust the limit' )
	&& empty( $GLOBALS['maca_scheduled_events'] ),
	'Behavior: a batch permanently larger than the daily limit pauses for administrator action instead of retrying every day.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_seed_media_recognition_plan( array() );
add_filter(
	'npcink_toolbox_refresh_site_media_index_batch',
	static function () {
		return new WP_Error( 'cloud_provider_quota_exhausted', 'Provider quota exhausted.' );
	},
	10,
	2
);
Npcink_Cloud_Settings_Page::process_media_recognition_plan();
$provider_paused_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
$provider_paused_status = get_transient( 'npcink_cloud_addon_media_index_status' );
maca_assert(
	empty( $provider_paused_plan['active'] )
	&& 'paused' === ( $provider_paused_plan['state'] ?? '' )
	&& 'cloud_provider_quota_exhausted' === ( $provider_paused_plan['pause_reason'] ?? '' )
	&& 'error' === ( $provider_paused_status['state'] ?? '' )
	&& false !== strpos( (string) ( $provider_paused_status['error'] ?? '' ), 'provider quota is exhausted' )
	&& empty( $GLOBALS['maca_scheduled_events'] ),
	'Behavior: a non-daily dispatch failure pauses the plan at the current cursor and requires administrator recovery.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_seed_media_recognition_plan( array() );
add_filter(
	'npcink_toolbox_refresh_site_media_index_batch',
	static function () {
		return new WP_Error( 'media_recognition.media_capacity_exhausted', 'Media capacity exhausted.' );
	},
	10,
	2
);
Npcink_Cloud_Settings_Page::process_media_recognition_plan();
$capacity_paused_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
$capacity_paused_status = get_transient( 'npcink_cloud_addon_media_index_status' );
maca_assert(
	empty( $capacity_paused_plan['active'] )
	&& 'paused' === ( $capacity_paused_plan['state'] ?? '' )
	&& false !== strpos( (string) ( $capacity_paused_plan['pause_reason'] ?? '' ), 'media_capacity_exhausted' )
	&& false !== strpos( (string) ( $capacity_paused_status['error'] ?? '' ), 'plan image capacity is full' )
	&& empty( $GLOBALS['maca_scheduled_events'] ),
	'Behavior: Cloud media-capacity exhaustion pauses the current cursor and returns one actionable local message.'
);

maca_reset_test_state();
maca_seed_settings( true );
set_transient(
	'npcink_cloud_addon_media_index_status',
	array(
		'state' => 'processing',
		'run_id' => 'run_media_result_pending',
		'page' => 2,
		'next_page' => 3,
		'has_more' => true,
		'total' => 25,
		'indexed' => 10,
		'completed_before' => 10,
		'batch_size' => 10,
	),
	DAY_IN_SECONDS
);
$GLOBALS['maca_http_response_queue'] = array(
	array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array( 'run_id' => 'run_media_result_pending', 'status' => 'succeeded' ) ) ),
	),
	array(
		'response' => array( 'code' => 409 ),
		'body' => wp_json_encode( array( 'status' => 'error', 'error_code' => 'run_result_not_ready', 'message' => 'Run result is not ready yet.' ) ),
	),
);
$media_result_pending = $media_status_refresher->invoke( null );
$media_result_pending_status = get_transient( 'npcink_cloud_addon_media_index_status' );
maca_assert(
	is_wp_error( $media_result_pending )
	&& is_array( $media_result_pending_status )
	&& 'processing' === $media_result_pending_status['state']
	&& 10 === $media_result_pending_status['indexed']
	&& 2 === $media_result_pending_status['page']
	&& 3 === $media_result_pending_status['next_page'],
	'Behavior: a succeeded run with a temporarily unavailable result stays on the current batch and retries read-only status polling.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_seed_media_recognition_plan(
	array(
		'state' => 'processing',
		'run_id' => 'run_media_durable',
		'page' => 2,
		'next_page' => 3,
		'indexed' => 10,
		'completed_before' => 10,
	),
	array(
		'state' => 'processing',
		'current_run_id' => 'run_media_durable',
		'current_page' => 2,
		'next_page' => 3,
		'batch_size' => 10,
		'total_estimate' => 30,
		'processed_count' => 10,
		'completed_before' => 10,
		'successful_count' => 10,
		'successful_before' => 10,
		'failed_count' => 0,
		'failed_before' => 0,
		'evidence_count' => 8,
		'evidence_before' => 8,
	)
);
delete_transient( 'npcink_cloud_addon_media_index_status' );
$GLOBALS['maca_http_response_queue'] = array(
	array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array( 'run_id' => 'run_media_durable', 'status' => 'running' ) ) ),
	),
	array(
		'response' => array( 'code' => 409 ),
		'body' => wp_json_encode( array( 'status' => 'error', 'error_code' => 'run_result_not_ready', 'message' => 'Run result is not ready yet.' ) ),
	),
);
Npcink_Cloud_Settings_Page::process_media_recognition_plan();
$durable_media_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
$durable_media_status = get_transient( 'npcink_cloud_addon_media_index_status' );
maca_assert(
	2 === count( $GLOBALS['maca_http_requests'] )
	&& ! empty( $durable_media_plan['active'] )
	&& 'processing' === ( $durable_media_plan['state'] ?? '' )
	&& 'run_media_durable' === ( $durable_media_plan['current_run_id'] ?? '' )
	&& 2 === ( $durable_media_plan['current_page'] ?? 0 )
	&& 10 === ( $durable_media_plan['processed_count'] ?? 0 )
	&& is_array( $durable_media_status )
	&& 'run_media_durable' === ( $durable_media_status['run_id'] ?? '' )
	&& ! empty( $durable_media_status['has_more'] )
	&& isset( $GLOBALS['maca_scheduled_events']['npcink_cloud_addon_continue_media_recognition'] ),
	'Behavior: an expired status transient is rebuilt from the durable plan, including legacy next-page evidence, and polling resumes the same Cloud run without dispatching another batch.'
);

maca_reset_test_state();
maca_seed_settings( true );
set_transient(
	'npcink_cloud_addon_media_index_status',
	array(
		'state' => 'processing',
		'run_id' => 'run_media_expired',
		'page' => 2,
		'next_page' => 3,
		'has_more' => true,
		'total' => 25,
		'indexed' => 10,
		'completed_before' => 10,
		'batch_size' => 10,
	),
	DAY_IN_SECONDS
);
$GLOBALS['maca_http_response_queue'] = array(
	array(
		'response' => array( 'code' => 404 ),
		'body' => wp_json_encode( array( 'status' => 'error', 'error_code' => 'run_not_found', 'message' => "run 'run_media_expired' was not found" ) ),
	),
);
$media_run_expired = $media_status_refresher->invoke( null );
$media_run_expired_status = get_transient( 'npcink_cloud_addon_media_index_status' );
maca_assert(
	is_array( $media_run_expired )
	&& 'error' === $media_run_expired['state']
	&& 'run_not_found' === $media_run_expired['error_code']
	&& 'This recognition task is no longer available. Retry this batch to continue.' === $media_run_expired['error']
	&& is_array( $media_run_expired_status )
	&& 'error' === $media_run_expired_status['state']
	&& 10 === $media_run_expired_status['indexed']
	&& 2 === $media_run_expired_status['page']
	&& 3 === $media_run_expired_status['next_page'],
	'Behavior: an unavailable saved Cloud run becomes a localized retryable terminal state without advancing the batch cursor.'
);

maca_reset_test_state();
maca_seed_settings( true );
set_transient(
	'npcink_cloud_addon_media_index_status',
	array(
		'state' => 'processing',
		'run_id' => 'run_media_batch_eta',
		'page' => 2,
		'next_page' => 3,
		'has_more' => true,
		'total' => 40,
		'indexed' => 10,
		'completed_before' => 10,
		'batch_size' => 10,
	),
	DAY_IN_SECONDS
);
$GLOBALS['maca_http_response_queue'] = array(
	array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array( 'run_id' => 'run_media_batch_eta', 'status' => 'running' ) ) ),
	),
	array(
		'response' => array( 'code' => 409 ),
		'body' => wp_json_encode( array( 'status' => 'error', 'error_code' => 'run_result_not_ready', 'message' => 'Run result is not ready yet.' ) ),
	),
);
$media_batch_eta = $media_status_refresher->invoke( null );
maca_assert(
	is_array( $media_batch_eta )
	&& 'processing' === $media_batch_eta['state']
	&& '' === $media_batch_eta['eta_at'],
	'Behavior: a running batch without Cloud progress keeps ETA unknown instead of inventing a site-wide completion time.'
);
