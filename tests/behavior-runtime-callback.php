<?php
/**
 * Behavior contracts for signed Cloud runtime callbacks.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if ( ! function_exists( 'register_rest_route' ) ) {
	function register_rest_route( string $namespace, string $route, array $args ): bool {
		$GLOBALS['maca_rest_routes'][ $namespace . $route ] = $args;
		return true;
	}
}

if ( ! function_exists( 'rest_url' ) ) {
	function rest_url( string $path = '' ): string {
		return 'https://wordpress.example.test/wp-json/' . ltrim( $path, '/' );
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	final class WP_REST_Response {
		/** @var mixed */
		private $data;
		/** @var int */
		private $status;

		public function __construct( $data = null, int $status = 200 ) {
			$this->data = $data;
			$this->status = $status;
		}

		public function get_data() {
			return $this->data;
		}

		public function get_status(): int {
			return $this->status;
		}
	}
}

/** Minimal immutable callback request fixture. */
final class Maca_Runtime_Callback_Request {
	/** @var string */
	private $body;
	/** @var array<string,string> */
	private $headers;

	/** @param array<string,string> $headers */
	public function __construct( string $body, array $headers ) {
		$this->body = $body;
		$this->headers = array_change_key_case( $headers, CASE_LOWER );
	}

	public function get_body(): string {
		return $this->body;
	}

	public function get_header( string $name ): string {
		return (string) ( $this->headers[ strtolower( $name ) ] ?? '' );
	}
}

/** Stores one encrypted callback registration through the production seam. */
function maca_seed_runtime_callback_registration(): array {
	$registration = array(
		'site_id' => 'site_test',
		'registration_id' => 'runtime_registration_test',
		'key_id' => 'runtime_callback_test',
		'secret' => 'callback-secret-test',
		'callback_url' => rest_url( 'npcink-cloud-addon/v1/runtime-callbacks/terminal' ),
		'cloud_registered' => true,
		'registered_at' => time(),
	);
	$store = new ReflectionMethod( Npcink_Cloud_Runtime_Callback::class, 'store_registration' );
	if ( PHP_VERSION_ID < 80100 ) {
		$store->setAccessible( true );
	}
	maca_assert( true === $store->invoke( null, $registration ), 'Fixture: callback registration is stored through the encrypted production seam.' );

	return $registration;
}

/** Builds a callback request signed exactly like the Cloud HTTP dispatcher. */
function maca_runtime_callback_request( array $registration, string $run_id, string $callback_id, ?int $timestamp = null, array $payload_overrides = array(), string $signature_override = '' ): Maca_Runtime_Callback_Request {
	$timestamp_value = (string) ( $timestamp ?? time() );
	$event = 'runtime.run.terminal';
	$traceparent = '00-' . str_repeat( '1', 32 ) . '-' . str_repeat( '2', 16 ) . '-01';
	$payload = array_merge(
		array(
			'event' => $event,
			'run_id' => $run_id,
			'site_id' => (string) $registration['site_id'],
		),
		$payload_overrides
	);
	$body = (string) wp_json_encode( $payload );
	$url = wp_parse_url( (string) $registration['callback_url'] );
	$path = is_array( $url ) ? (string) ( $url['path'] ?? '/' ) : '/';
	$query = is_array( $url ) ? (string) ( $url['query'] ?? '' ) : '';
	$route = '' === $query ? $path : $path . '?' . $query;
	$canonical = implode(
		"\n",
		array(
			'POST',
			$route,
			(string) $registration['site_id'],
			(string) $registration['key_id'],
			$timestamp_value,
			$event,
			$callback_id,
			$traceparent,
			hash( 'sha256', $body ),
		)
	);
	$derived = 'pbkdf2_sha256$210000$'
		. hash_pbkdf2( 'sha256', (string) $registration['secret'], 'npcink-ai-cloud-secret-hash-v2', 210000 );
	$signature = '' !== $signature_override ? $signature_override : hash_hmac( 'sha256', $canonical, $derived );

	return new Maca_Runtime_Callback_Request(
		$body,
		array(
			'x-npcink-cloud-event' => $event,
			'x-npcink-run-id' => $run_id,
			'x-npcink-site-id' => (string) $registration['site_id'],
			'x-npcink-key-id' => (string) $registration['key_id'],
			'x-npcink-timestamp' => $timestamp_value,
			'x-npcink-callback-id' => $callback_id,
			'x-npcink-signature' => $signature,
			'traceparent' => $traceparent,
		)
	);
}

maca_load_addon_classes();
maca_reset_test_state();
$GLOBALS['maca_rest_routes'] = array();
Npcink_Cloud_Runtime_Callback::register_rest_route();
$route = $GLOBALS['maca_rest_routes']['npcink-cloud-addon/v1/runtime-callbacks/terminal'] ?? array();
maca_assert(
	'POST' === ( $route['methods'] ?? '' )
	&& array( Npcink_Cloud_Runtime_Callback::class, 'receive_terminal_callback' ) === ( $route['callback'] ?? null )
	&& '__return_true' === ( $route['permission_callback'] ?? '' ),
	'Behavior: callback route is public only at the WordPress permission layer and delegates authorization to signature verification.'
);

$registration = maca_seed_runtime_callback_registration();
$stored = get_option( 'npcink_cloud_addon_runtime_callback_registration', array() );
maca_assert(
	is_array( $stored['credential_envelope'] ?? null )
	&& false === strpos( serialize( $stored ), (string) $registration['secret'] )
	&& false === strpos( serialize( $stored ), (string) $registration['key_id'] ),
	'Behavior: callback signing credentials are independently encrypted and never stored in plaintext.'
);

$run_id = 'run_callback_test';
$callback_id = 'runtime_delivery_' . str_repeat( 'a', 64 );
update_option(
	'npcink_cloud_addon_media_recognition_plan',
	array( 'active' => true, 'plan_id' => 'media_plan_test', 'current_run_id' => $run_id ),
	false
);
$scheduled = Npcink_Cloud_Runtime_Callback::receive_terminal_callback(
	maca_runtime_callback_request( $registration, $run_id, $callback_id )
);
maca_assert(
	$scheduled instanceof WP_REST_Response
	&& 202 === $scheduled->get_status()
	&& array( 'status' => 'scheduled' ) === $scheduled->get_data()
	&& 1 === ( $GLOBALS['maca_schedule_call_counts']['npcink_cloud_addon_continue_media_recognition'] ?? 0 ),
	'Behavior: a valid Cloud signature schedules exactly one immediate existing media-continuation event.'
);

$duplicate = Npcink_Cloud_Runtime_Callback::receive_terminal_callback(
	maca_runtime_callback_request( $registration, $run_id, $callback_id )
);
maca_assert(
	$duplicate instanceof WP_REST_Response
	&& 200 === $duplicate->get_status()
	&& array( 'status' => 'already_received' ) === $duplicate->get_data()
	&& 1 === ( $GLOBALS['maca_schedule_call_counts']['npcink_cloud_addon_continue_media_recognition'] ?? 0 ),
	'Behavior: duplicate callback delivery is acknowledged without scheduling duplicate continuation work.'
);

maca_reset_test_state();
$registration = maca_seed_runtime_callback_registration();
$invalid = Npcink_Cloud_Runtime_Callback::receive_terminal_callback(
	maca_runtime_callback_request( $registration, $run_id, 'runtime_delivery_' . str_repeat( 'b', 64 ), null, array(), str_repeat( '0', 64 ) )
);
maca_assert(
	$invalid instanceof WP_Error
	&& 'cloud_callback_signature_invalid' === $invalid->get_error_code()
	&& empty( $GLOBALS['maca_scheduled_events'] ),
	'Behavior: an invalid callback signature fails closed before Cron scheduling.'
);

$stale = Npcink_Cloud_Runtime_Callback::receive_terminal_callback(
	maca_runtime_callback_request( $registration, $run_id, 'runtime_delivery_' . str_repeat( 'c', 64 ), time() - 301 )
);
maca_assert(
	$stale instanceof WP_Error
	&& 'cloud_callback_headers_invalid' === $stale->get_error_code()
	&& empty( $GLOBALS['maca_scheduled_events'] ),
	'Behavior: a stale callback timestamp fails closed before Cron scheduling.'
);

update_option(
	'npcink_cloud_addon_media_recognition_plan',
	array( 'active' => true, 'plan_id' => 'media_plan_test', 'current_run_id' => $run_id ),
	false
);
$schedule_retry_callback_id = 'runtime_delivery_' . str_repeat( 'f', 64 );
$GLOBALS['maca_schedule_single_failures_remaining'] = 1;
$schedule_failed = Npcink_Cloud_Runtime_Callback::receive_terminal_callback(
	maca_runtime_callback_request( $registration, $run_id, $schedule_retry_callback_id )
);
maca_assert(
	$schedule_failed instanceof WP_Error
	&& 'cloud_callback_schedule_failed' === $schedule_failed->get_error_code()
	&& 503 === ( $schedule_failed->get_error_data()['status'] ?? 0 )
	&& empty( $GLOBALS['maca_scheduled_events'] )
	&& empty( $GLOBALS['maca_transients'] ),
	'Behavior: a failed Cron write returns a retryable response without consuming the callback receipt.'
);
$schedule_retried = Npcink_Cloud_Runtime_Callback::receive_terminal_callback(
	maca_runtime_callback_request( $registration, $run_id, $schedule_retry_callback_id )
);
maca_assert(
	$schedule_retried instanceof WP_REST_Response
	&& 202 === $schedule_retried->get_status()
	&& array( 'status' => 'scheduled' ) === $schedule_retried->get_data()
	&& 2 === ( $GLOBALS['maca_schedule_call_counts']['npcink_cloud_addon_continue_media_recognition'] ?? 0 ),
	'Behavior: Cloud can retry the same signed delivery after a transient Cron scheduling failure.'
);

maca_reset_test_state();
$registration = maca_seed_runtime_callback_registration();

$mismatched = Npcink_Cloud_Runtime_Callback::receive_terminal_callback(
	maca_runtime_callback_request( $registration, $run_id, 'runtime_delivery_' . str_repeat( 'd', 64 ), null, array( 'run_id' => 'run_payload_mismatch' ) )
);
maca_assert(
	$mismatched instanceof WP_Error
	&& 'cloud_callback_payload_invalid' === $mismatched->get_error_code()
	&& empty( $GLOBALS['maca_scheduled_events'] ),
	'Behavior: callback payload and signed-header run identities must match.'
);

$unrelated = Npcink_Cloud_Runtime_Callback::receive_terminal_callback(
	maca_runtime_callback_request( $registration, 'run_unrelated', 'runtime_delivery_' . str_repeat( 'e', 64 ) )
);
maca_assert(
	$unrelated instanceof WP_REST_Response
	&& 202 === $unrelated->get_status()
	&& array( 'status' => 'ignored' ) === $unrelated->get_data()
	&& empty( $GLOBALS['maca_scheduled_events'] ),
	'Behavior: a valid terminal callback for an unrelated run is ignored without changing the active plan.'
);

update_option(
	'npcink_cloud_addon_media_recognition_plan',
	array( 'active' => true, 'plan_id' => 'media_plan_test', 'current_run_id' => 'run_unrelated' ),
	false
);
$retried_after_plan_commit = Npcink_Cloud_Runtime_Callback::receive_terminal_callback(
	maca_runtime_callback_request( $registration, 'run_unrelated', 'runtime_delivery_' . str_repeat( 'e', 64 ) )
);
maca_assert(
	$retried_after_plan_commit instanceof WP_REST_Response
	&& 202 === $retried_after_plan_commit->get_status()
	&& array( 'status' => 'scheduled' ) === $retried_after_plan_commit->get_data()
	&& 1 === ( $GLOBALS['maca_schedule_call_counts']['npcink_cloud_addon_continue_media_recognition'] ?? 0 ),
	'Behavior: an early callback ignored before plan commit remains eligible for a later signed retry.'
);
