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

maca_reset_test_state();
Npcink_Cloud_Settings_Page::register();

$expected_hooks = array(
	'admin_menu' => array( 'add_menu_page', 50 ),
	'admin_enqueue_scripts' => array( 'enqueue_admin_assets', 10 ),
	'admin_post_npcink_cloud_addon_save' => array( 'handle_save', 10 ),
	'admin_post_npcink_cloud_addon_complete_auth' => array( 'handle_complete_auth', 10 ),
	'admin_post_npcink_cloud_addon_start_custom_auth' => array( 'handle_start_custom_auth', 10 ),
	'admin_post_npcink_cloud_addon_disconnect' => array( 'handle_disconnect', 10 ),
	'admin_post_npcink_cloud_addon_update_local_permission' => array( 'handle_update_local_permission', 10 ),
	'admin_post_npcink_cloud_addon_refresh_site_knowledge' => array( 'handle_refresh_site_knowledge', 10 ),
	'wp_ajax_npcink_cloud_addon_refresh_site_knowledge_status' => array( 'handle_refresh_site_knowledge_status', 10 ),
	'admin_post_npcink_cloud_addon_manage_site_knowledge_index' => array( 'handle_manage_site_knowledge_index', 10 ),
	'admin_post_npcink_cloud_addon_retry_runtime_run' => array( 'handle_retry_runtime_run', 10 ),
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

maca_reset_test_state();
$http_before_render = count( $GLOBALS['maca_http_requests'] );
ob_start();
Npcink_Cloud_Settings_Page::render();
$rendered = (string) ob_get_clean();

maca_assert(
	false !== strpos( $rendered, 'Npcink Cloud Addon' )
	&& false !== strpos( $rendered, 'Add this site in Npcink Cloud' )
	&& false !== strpos( $rendered, 'Free service and credits belong to the Cloud account selected during authorization' )
	&& $http_before_render === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: the representative unconfigured admin render explains account-owned Free service and performs zero outbound HTTP requests.'
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
maca_seed_settings( true );
$http_before_overview = count( $GLOBALS['maca_http_requests'] );
ob_start();
Npcink_Cloud_Settings_Page::render();
$overview_rendered = (string) ob_get_clean();

maca_assert(
	false !== strpos( $overview_rendered, 'Available knowledge documents' )
	&& false !== strpos( $overview_rendered, 'data-npcink-site-knowledge-usage' )
	&& false !== strpos( $overview_rendered, 'Credits shown here belong to the connected Cloud account' )
	&& $http_before_overview === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: the verified Overview keeps account ownership beside usage without Cloud HTTP.'
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
	false !== strpos( $connection_management_rendered, 'Local disconnect only clears this WordPress site' )
	&& false !== strpos( $connection_management_rendered, 'This does not release the site in Cloud or start the cross-account cooldown' )
	&& $http_before_connection_management === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: Connection Management states that local disconnect neither releases Cloud ownership nor starts cooldown, without Cloud HTTP.'
);

$site_knowledge_renderer = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_site_knowledge_summary' );
if ( PHP_VERSION_ID < 80100 ) {
	$site_knowledge_renderer->setAccessible( true );
}

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
	&& false !== strpos( $site_knowledge_rendered, 'npcink-cloud-site-knowledge-quota-detail' )
	&& false !== strpos( $site_knowledge_rendered, 'data-npcink-site-knowledge-detail="chunks"' )
	&& $http_before_site_knowledge === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: Site Knowledge keeps refresh and low-frequency quota detail without duplicating the Overview quota or calling Cloud.'
);
