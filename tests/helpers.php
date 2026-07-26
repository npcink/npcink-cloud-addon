<?php
/**
 * Shared test helpers for Npcink Cloud Addon.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'MACA_TEST_ROOT' ) ) {
	define( 'MACA_TEST_ROOT', dirname( __DIR__ ) );
}

if ( ! defined( 'NPCINK_CLOUD_ADDON_VERSION' ) ) {
	define( 'NPCINK_CLOUD_ADDON_VERSION', '0.1.3-test' );
}

/**
 * Assertion helper.
 *
 * @param bool   $condition Condition.
 * @param string $message Message.
 * @return void
 */
function maca_assert( bool $condition, string $message ): void {
	if ( ! $condition ) {
		fwrite( STDERR, '[fail] ' . $message . "\n" );
		exit( 1 );
	}

	echo '[ok] ' . $message . "\n";
}

/**
 * Reads a repo file.
 *
 * @param string $path Path.
 * @return string
 */
function maca_read( string $path ): string {
	$contents = is_readable( $path ) ? file_get_contents( $path ) : false;

	return is_string( $contents ) ? $contents : '';
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	/**
	 * Minimal wp_parse_url polyfill for pure PHP behavior tests.
	 *
	 * @param string $url URL to parse.
	 * @param int    $component Specific component.
	 * @return mixed
	 */
	function wp_parse_url( string $url, int $component = -1 ) {
		return -1 === $component ? parse_url( $url ) : parse_url( $url, $component );
	}
}

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', MACA_TEST_ROOT . '/tests/wordpress-stub/' );
}

require_once MACA_TEST_ROOT . '/includes/class-cloud-outbound-policy.php';

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stub for behavior tests.
	 */
	class WP_Error {
		/**
		 * Error code.
		 *
		 * @var string
		 */
		private $code;

		/**
		 * Error message.
		 *
		 * @var string
		 */
		private $message;

		/**
		 * Error data.
		 *
		 * @var mixed
		 */
		private $data;

		/**
		 * Constructor.
		 *
		 * @param string $code Error code.
		 * @param string $message Error message.
		 * @param mixed  $data Error data.
		 */
		public function __construct( string $code = '', string $message = '', $data = null ) {
			$this->code = $code;
			$this->message = $message;
			$this->data = $data;
		}

		/**
		 * Gets the error code.
		 *
		 * @return string
		 */
		public function get_error_code(): string {
			return $this->code;
		}

		/**
		 * Gets the error message.
		 *
		 * @return string
		 */
		public function get_error_message(): string {
			return $this->message;
		}

		/**
		 * Gets the error data.
		 *
		 * @return mixed
		 */
		public function get_error_data() {
			return $this->data;
		}
	}
}

if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = '' ): string {
		return $text;
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $value ): bool {
		return $value instanceof WP_Error;
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ): string {
		return trim( preg_replace( '/[\x00-\x1F\x7F]/', '', wp_strip_all_tags( (string) $value ) ) );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $value ): string {
		return strtolower( preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $value ) );
	}
}

if ( ! function_exists( 'sanitize_file_name' ) ) {
	function sanitize_file_name( $value ): string {
		return preg_replace( '/[^A-Za-z0-9._-]/', '', basename( (string) $value ) );
	}
}

if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $value ): string {
		return filter_var( trim( (string) $value ), FILTER_SANITIZE_URL );
	}
}

if ( ! function_exists( 'absint' ) ) {
	function absint( $value ): int {
		return abs( (int) $value );
	}
}

if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $value ) {
		return json_encode( $value );
	}
}

if ( ! function_exists( 'wp_generate_uuid4' ) ) {
	function wp_generate_uuid4(): string {
		return '00000000-0000-4000-8000-' . substr( hash( 'sha256', (string) microtime( true ) . random_int( 1, PHP_INT_MAX ) ), 0, 12 );
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( string $value ): string {
		return rtrim( $value, '/' );
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return 'https://wordpress.example.test/' . ltrim( $path, '/' );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ): string {
		return strip_tags( (string) $value );
	}
}

$GLOBALS['maca_options'] = array();
$GLOBALS['maca_option_update_counts'] = array();
$GLOBALS['maca_transients'] = array();
$GLOBALS['maca_http_requests'] = array();
$GLOBALS['maca_http_response_queue'] = array();
$GLOBALS['maca_actions'] = array();
$GLOBALS['maca_filters'] = array();
$GLOBALS['maca_wp_salt'] = 'maca-test-auth-salt';

if ( ! function_exists( 'wp_salt' ) ) {
	function wp_salt( string $scheme = 'auth' ): string {
		return (string) ( $GLOBALS['maca_wp_salt'] ?? '' );
	}
}

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $name, $default = false ) {
		$GLOBALS['maca_option_read_counts'][ $name ] = absint( $GLOBALS['maca_option_read_counts'][ $name ] ?? 0 ) + 1;
		return array_key_exists( $name, $GLOBALS['maca_options'] ) ? $GLOBALS['maca_options'][ $name ] : $default;
	}
}

if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $name, $value, bool $autoload = true ): bool {
		$GLOBALS['maca_option_update_counts'][ $name ] = absint( $GLOBALS['maca_option_update_counts'][ $name ] ?? 0 ) + 1;
		$GLOBALS['maca_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'add_option' ) ) {
	function add_option( string $name, $value = '', string $deprecated = '', $autoload = null ): bool {
		if ( array_key_exists( $name, $GLOBALS['maca_options'] ) ) {
			return false;
		}

		$GLOBALS['maca_options'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( string $name ): bool {
		$existed = array_key_exists( $name, $GLOBALS['maca_options'] );
		unset( $GLOBALS['maca_options'][ $name ] );
		return $existed;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( string $name ) {
		return array_key_exists( $name, $GLOBALS['maca_transients'] ) ? $GLOBALS['maca_transients'][ $name ] : false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( string $name, $value, int $expiration = 0 ): bool {
		$GLOBALS['maca_transients'][ $name ] = $value;
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( string $name ): void {
		unset( $GLOBALS['maca_transients'][ $name ] );
	}
}

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['maca_actions'][ $hook_name ][ $priority ][] = array(
			'callback'      => $callback,
			'accepted_args' => $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook_name, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['maca_filters'][ $hook_name ][ $priority ][] = array(
			'callback'      => $callback,
			'accepted_args' => $accepted_args,
		);
		return true;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook_name, $value, ...$args ) {
		if ( empty( $GLOBALS['maca_filters'][ $hook_name ] ) || ! is_array( $GLOBALS['maca_filters'][ $hook_name ] ) ) {
			return $value;
		}

		ksort( $GLOBALS['maca_filters'][ $hook_name ] );
		foreach ( $GLOBALS['maca_filters'][ $hook_name ] as $callbacks ) {
			foreach ( $callbacks as $entry ) {
				$callback = $entry['callback'];
				$accepted_args = max( 1, absint( $entry['accepted_args'] ?? 1 ) );
				$value = call_user_func_array( $callback, array_slice( array_merge( array( $value ), $args ), 0, $accepted_args ) );
			}
		}

		return $value;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	function get_current_user_id(): int {
		return 1;
	}
}

if ( ! function_exists( 'register_setting' ) ) {
	function register_setting(): void {}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( string $hook, array $args = array() ) {
		unset( $args );
		return $GLOBALS['maca_scheduled_events'][ $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_get_schedule' ) ) {
	function wp_get_schedule( string $hook, array $args = array() ) {
		unset( $args );
		return $GLOBALS['maca_scheduled_event_schedules'][ $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( int $timestamp, string $recurrence, string $hook, array $args = array() ): bool {
		unset( $args );
		$GLOBALS['maca_scheduled_events'][ $hook ] = $timestamp;
		$GLOBALS['maca_scheduled_event_schedules'][ $hook ] = $recurrence;
		$GLOBALS['maca_schedule_call_counts'][ $hook ] = absint( $GLOBALS['maca_schedule_call_counts'][ $hook ] ?? 0 ) + 1;
		return true;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( int $timestamp, string $hook, array $args = array() ): bool {
		unset( $args );
		$GLOBALS['maca_schedule_call_counts'][ $hook ] = absint( $GLOBALS['maca_schedule_call_counts'][ $hook ] ?? 0 ) + 1;
		if ( absint( $GLOBALS['maca_schedule_single_failures_remaining'] ?? 0 ) > 0 ) {
			--$GLOBALS['maca_schedule_single_failures_remaining'];
			return false;
		}
		$GLOBALS['maca_scheduled_events'][ $hook ] = $timestamp;
		$GLOBALS['maca_scheduled_event_schedules'][ $hook ] = 'single';
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( string $hook, array $args = array() ): void {
		unset( $args );
		unset( $GLOBALS['maca_scheduled_events'][ $hook ], $GLOBALS['maca_scheduled_event_schedules'][ $hook ] );
	}
}

if ( ! function_exists( 'wp_remote_request' ) ) {
	function wp_remote_request( string $url, array $args = array() ) {
		$GLOBALS['maca_http_requests'][] = array(
			'url'  => $url,
			'args' => $args,
		);
		if ( str_ends_with( $url, '/health/live' ) ) {
			return array(
				'response' => array( 'code' => 200 ),
				'headers'  => array( 'Content-Type' => 'application/json' ),
				'body'     => wp_json_encode( array( 'message' => 'ok' ) ),
			);
		}

		if ( ! empty( $GLOBALS['maca_http_response_queue'] ) ) {
			$response = array_shift( $GLOBALS['maca_http_response_queue'] );
			if ( is_callable( $response ) ) {
				$response = $response( $url, $args );
			}

			if ( is_array( $response ) && ! array_key_exists( 'headers', $response ) ) {
				$response['headers'] = array( 'Content-Type' => 'application/json' );
			}

			return $response;
		}

		return array(
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode(
				array(
					'status' => 'ok',
					'data'   => array(
						'run_id' => 'run_media_1',
					),
				)
			),
		);
	}
}

if ( ! function_exists( 'wp_safe_remote_request' ) ) {
	function wp_safe_remote_request( string $url, array $args = array() ) {
		return wp_remote_request( $url, $args );
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_array( $value ) ? array_map( 'wp_unslash', $value ) : stripslashes( (string) $value );
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( string $url, array $args = array() ): array {
		$GLOBALS['maca_http_requests'][] = array(
			'url'  => $url,
			'args' => $args,
		);

		return array(
			'response' => array( 'code' => 200 ),
			'headers'  => array( 'Content-Type' => 'application/json' ),
			'body'     => wp_json_encode( array( 'message' => 'ok' ) ),
		);
	}
}

if ( ! function_exists( 'wp_remote_retrieve_header' ) ) {
	function wp_remote_retrieve_header( array $response, string $name ): string {
		$headers = is_array( $response['headers'] ?? null ) ? $response['headers'] : array();
		foreach ( $headers as $key => $value ) {
			if ( strtolower( (string) $key ) === strtolower( $name ) ) {
				return is_scalar( $value ) ? (string) $value : '';
			}
		}

		return '';
	}
}

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	function wp_get_environment_type(): string {
		return (string) ( $GLOBALS['maca_wp_environment_type'] ?? 'local' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( array $response ): int {
		return absint( $response['response']['code'] ?? 0 );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( array $response ): string {
		return (string) ( $response['body'] ?? '' );
	}
}

/**
 * Loads addon classes needed by behavior tests.
 *
 * @return void
 */
function maca_load_addon_classes(): void {
	require_once MACA_TEST_ROOT . '/includes/class-cloud-credential-store.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-outbound-policy.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-runtime-endpoint-policy.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-addon-settings.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-ai-task-contract.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-runtime-client.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-entitlement-summary.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-media-derivative-transport.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-observability-collector.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-editor-assist-quality.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-site-knowledge-runtime-bridge.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-site-knowledge-admin-projection.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-site-knowledge-admin-actions.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-runtime-runs-presenter.php';
}

/**
 * Seeds Cloud addon settings for behavior tests.
 *
 * @param bool   $verified Whether settings are verified.
 * @param string $base_url Cloud base URL.
 * @return void
 */
function maca_seed_settings( bool $verified, string $base_url = 'https://cloud.example.test' ): void {
	Npcink_Cloud_Addon_Settings::write_settings(
		array(
		'base_url' => $base_url,
		'site_id' => 'site_test',
		'key_id' => 'key_test',
		'secret' => 'secret_test',
		'timeout' => 8,
		'verified' => $verified,
		'verified_at' => $verified ? '2026-06-03 00:00:00 UTC' : '',
		'last_verification_error' => '',
		'monitoring_enabled' => false,
		'site_knowledge_delivery_enabled' => true,
		'site_knowledge_generation_reference_enabled' => false,
		'wordpress_ai_connector_enabled' => true,
		)
	);
}

/**
 * Enables or disables the addon monitoring setting in test storage.
 *
 * @param bool $enabled Whether monitoring is enabled.
 * @return void
 */
function maca_set_monitoring_enabled( bool $enabled ): void {
	$settings = Npcink_Cloud_Addon_Settings::get_settings();
	$settings['monitoring_enabled'] = $enabled;
	Npcink_Cloud_Addon_Settings::write_settings( $settings );
}

/**
 * Enables or disables Site Knowledge delivery in test storage.
 *
 * @param bool $enabled Whether delivery is enabled.
 * @return void
 */
function maca_set_site_knowledge_delivery_enabled( bool $enabled ): void {
	$settings = Npcink_Cloud_Addon_Settings::get_settings();
	$settings['site_knowledge_delivery_enabled'] = $enabled;
	Npcink_Cloud_Addon_Settings::write_settings( $settings );
}

/**
 * Enables or disables Site Knowledge references for WordPress AI generation.
 *
 * @param bool $enabled Whether generation references are enabled.
 * @return void
 */
function maca_set_site_knowledge_generation_reference_enabled( bool $enabled ): void {
	$settings = Npcink_Cloud_Addon_Settings::get_settings();
	$settings['site_knowledge_generation_reference_enabled'] = $enabled;
	Npcink_Cloud_Addon_Settings::write_settings( $settings );
}

/**
 * Enables or disables WordPress AI connector exposure in test storage.
 *
 * @param bool $enabled Whether connector exposure is enabled.
 * @return void
 */
function maca_set_wordpress_ai_connector_enabled( bool $enabled ): void {
	$settings = Npcink_Cloud_Addon_Settings::get_settings();
	$settings['wordpress_ai_connector_enabled'] = $enabled;
	Npcink_Cloud_Addon_Settings::write_settings( $settings );
}

/**
 * Resets addon behavior test state.
 *
 * @return void
 */
function maca_reset_test_state(): void {
	$GLOBALS['maca_options'] = array();
	$GLOBALS['maca_option_update_counts'] = array();
	$GLOBALS['maca_option_read_counts'] = array();
	$GLOBALS['maca_transients'] = array();
	$GLOBALS['maca_http_requests'] = array();
	$GLOBALS['maca_http_response_queue'] = array();
	$GLOBALS['maca_actions'] = array();
	$GLOBALS['maca_filters'] = array();
	$GLOBALS['maca_scheduled_events'] = array();
	$GLOBALS['maca_scheduled_event_schedules'] = array();
	$GLOBALS['maca_schedule_call_counts'] = array();
	$GLOBALS['maca_schedule_single_failures_remaining'] = 0;
	$GLOBALS['maca_wp_salt'] = 'maca-test-auth-salt';
	$GLOBALS['maca_wp_environment_type'] = 'local';
	maca_register_default_outbound_filters();
}

/**
 * Registers deterministic public DNS results for behavior-test hostnames.
 *
 * @return void
 */
function maca_register_default_outbound_filters(): void {
	add_filter(
		'npcink_cloud_addon_resolved_host_ips',
		static function ( $ips, string $host ): array {
			if ( str_ends_with( $host, '.example.test' ) ) {
				return array( '1.1.1.1' );
			}

			return is_array( $ips ) ? $ips : array();
		},
		10,
		2
	);
}

maca_register_default_outbound_filters();

/**
 * Returns a valid local ability response fixture.
 *
 * @return array<string,mixed>
 */
function maca_ability_fixture(): array {
	return array(
		'request_contract_version' => 'media_derivative_cloud_request.v1',
		'readonly' => true,
		'proposal_only' => true,
		'attachment_id' => 123,
		'local_adoption' => array(
			'final_write_owner' => 'local_wordpress_host',
			'wordpress_write_included' => false,
		),
		'cloud_job_payload' => array(
			'job_type' => 'generate_optimized_media_derivative',
			'target_format' => 'webp',
			'max_width' => 1200,
			'quality' => 82,
			'source_media_type' => 'image/jpeg',
			'source_asset' => array(
				'width' => 1600,
				'height' => 900,
				'filesize_bytes' => 400000,
				'mime_type' => 'image/jpeg',
			),
		),
	);
}

/**
 * Returns a future expiry timestamp.
 *
 * @return string
 */
function maca_future_expiry(): string {
	return gmdate( 'c', time() + 3600 );
}
