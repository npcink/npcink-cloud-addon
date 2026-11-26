<?php
/**
 * Offline regression for per-request DNS validation and transport binding.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

// Substitute the PHP resolver in this subprocess only, before the policy's
// local-development filter. The production path needs no test-specific hook.
if ( function_exists( 'dns_get_record' ) || function_exists( 'gethostbynamel' ) || function_exists( 'curl_version' ) || function_exists( 'curl_setopt' ) ) {
	maca_assert( false, 'Run the DNS fixture from tests/run.php with the resolver and cURL substitutes.' );
} else {
	function dns_get_record( string $host, int $type ): array {
		$GLOBALS['maca_dns_queries'][] = array( $host, $type );
		return array_shift( $GLOBALS['maca_dns_answers'] );
	}

	function gethostbynamel( string $host ): array {
		return array();
	}

	function curl_version(): array {
		return array( 'version_number' => $GLOBALS['maca_curl_version'] ?? 0x073B00 );
	}

	function curl_setopt( $handle, int $option, $value ): bool {
		$GLOBALS['maca_curl_options'][ $option ] = $value;
		return $option !== ( $GLOBALS['maca_curl_rejected_option'] ?? null );
	}
}

defined( 'CURLOPT_PROXY' ) || define( 'CURLOPT_PROXY', 10004 );
defined( 'CURLOPT_RESOLVE' ) || define( 'CURLOPT_RESOLVE', 10203 );

final class Maca_DNS_Curl {
	public static function test( array $capabilities ): bool {
		return ! empty( $capabilities['ssl'] ) && ( $GLOBALS['maca_curl_available'] ?? true );
	}
}

final class Maca_DNS_Capability {
	public const SSL = 'ssl';
}

final class Maca_DNS_Requests_Exception extends RuntimeException {
	public function __construct( string $message, string $type ) {
		parent::__construct( $message );
	}
}

class_alias( Maca_DNS_Curl::class, 'WpOrg\Requests\Transport\Curl' );
class_alias( Maca_DNS_Capability::class, 'WpOrg\Requests\Capability' );
class_alias( Maca_DNS_Requests_Exception::class, 'WpOrg\Requests\Exception' );

/** Exercise the order of the WordPress Requests hooks without sending HTTP. */
function maca_dns_transport( string $url, array $args ): array|WP_Error {
	$hooks   = new class() {
		public $before_send;
		public function register( string $name, callable $callback ): void {
			maca_assert( 'curl.before_send' === $name, 'Binding is installed on the chosen request only.' );
			$this->before_send = $callback;
		}
	};
	$options = array( 'hooks' => $hooks, 'verify' => true, 'verifyname' => true, 'proxy' => $GLOBALS['maca_dns_proxy'] ?? false );
	$headers = empty( $GLOBALS['maca_dns_drop_marker'] ) ? $args['headers'] : array( 'Accept' => 'application/json' );
	try {
		foreach ( $GLOBALS['maca_actions']['requests-requests.before_request'][PHP_INT_MAX] ?? array() as $entry ) {
			$prepare = $entry['callback'];
			$unrelated = array();
			$unused    = array();
			$prepare( 'https://unrelated.example.test/', $unrelated, null, 'GET', $unused );
			$prepare( $url, $headers, null, 'GET', $options );
		}
		maca_assert( 'WpOrg\Requests\Transport\Curl' === $options['transport'] && false === $options['follow_redirects'], 'Request selects cURL without redirects before sending.' );
		maca_assert( array( 'Accept' => 'application/json' ) === $headers, 'The scoped marker is removed before transport.' );
		( $hooks->before_send )( null );
		$GLOBALS['maca_dns_dispatches'][] = $url;
	} catch ( Maca_DNS_Requests_Exception $exception ) {
		return new WP_Error( 'http_request_failed', $exception->getMessage() );
	}
	return array( 'headers' => array( 'Content-Type' => 'application/json' ), 'body' => '{}' );
}

maca_reset_test_state();
$GLOBALS['maca_wp_environment_type'] = 'production';
$GLOBALS['maca_dns_queries']         = array();
$GLOBALS['maca_dns_dispatches']      = array();
$public_answers                    = array( array( 'ip' => '1.1.1.1' ), array( 'ipv6' => '2606:4700:4700::1111' ) );
$GLOBALS['maca_dns_answers']         = array( $public_answers, array() );
$GLOBALS['maca_http_response_queue'][] = 'maca_dns_transport';

$host = 'cloud-dns-fixture.example.test';
$url  = 'https://' . $host . ':8443/v1/status';
$args = array( 'method' => 'GET', 'headers' => array( 'Accept' => 'application/json' ) );

$first = Npcink_Cloud_Outbound_Policy::request_json( $url, $args );
maca_assert( ! is_wp_error( $first ) && 1 === count( $GLOBALS['maca_http_requests'] ), 'Behavior: a public DNS answer allows the first mocked Cloud request.' );
maca_assert(
	array( $host . ':8443:1.1.1.1,[2606:4700:4700::1111]' ) === $GLOBALS['maca_curl_options'][CURLOPT_RESOLVE]
	&& '' === $GLOBALS['maca_curl_options'][CURLOPT_PROXY]
	&& array( $url ) === $GLOBALS['maca_dns_dispatches'],
	'Behavior: the original HTTPS URL is retained and cURL receives only the approved IPv4 and IPv6 peers for its port.'
);

$second = Npcink_Cloud_Outbound_Policy::request_json( $url, $args );
$query  = array( $host, DNS_A | DNS_AAAA );
maca_assert( array( $query, $query ) === $GLOBALS['maca_dns_queries'], 'Behavior: each outbound request looks up the same hostname independently.' );
maca_assert(
	is_wp_error( $second )
	&& 'cloud_outbound_target_not_allowed' === $second->get_error_code()
	&& 1 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: a later empty DNS answer fails closed without dispatching another request.'
);

foreach ( array( 'maca_curl_available' => false, 'maca_curl_version' => 0x073A00, 'maca_dns_proxy' => true, 'maca_dns_drop_marker' => true, 'maca_curl_rejected_option' => CURLOPT_RESOLVE ) as $option => $value ) {
	$GLOBALS[$option] = $value;
	$GLOBALS['maca_dns_answers'][] = $public_answers;
	$GLOBALS['maca_http_response_queue'][] = 'maca_dns_transport';
	$result = Npcink_Cloud_Outbound_Policy::request_json( $url, $args );
	maca_assert(
		is_wp_error( $result ) && 'cloud_outbound_pinned_transport_unavailable' === $result->get_error_code()
		&& array( $url ) === $GLOBALS['maca_dns_dispatches'],
		'Behavior: the request fails before sending if address binding cannot be enforced (' . $option . ').'
	);
	maca_assert( empty( $GLOBALS['maca_actions']['requests-requests.before_request'][PHP_INT_MAX] ), 'Behavior: the HTTP hook is removed even after a rejected dispatch.' );
	unset( $GLOBALS[$option] );
}

$GLOBALS['maca_dns_answers'][] = array_merge( $public_answers, array( array( 'ip' => '10.0.0.8' ) ) );
$requests_before               = count( $GLOBALS['maca_http_requests'] );
$mixed                         = Npcink_Cloud_Outbound_Policy::request_json( $url, $args );
maca_assert( is_wp_error( $mixed ) && 'cloud_outbound_target_not_allowed' === $mixed->get_error_code() && $requests_before === count( $GLOBALS['maca_http_requests'] ), 'Behavior: a mixed address set is rejected as a whole before WordPress dispatch.' );
