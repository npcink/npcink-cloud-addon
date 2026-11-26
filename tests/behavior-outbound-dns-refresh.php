<?php
/**
 * Offline regression for repeated outbound hostname validation.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

// Substitute the PHP resolver in this subprocess only, before the policy's
// local-development filter. The production path needs no test-specific hook.
if ( function_exists( 'dns_get_record' ) || function_exists( 'gethostbynamel' ) ) {
	maca_assert( false, 'Run the DNS refresh fixture with dns_get_record and gethostbynamel disabled.' );
} else {
	function dns_get_record( string $host, int $type ): array {
		$GLOBALS['maca_dns_queries'][] = array( $host, $type );
		return array_shift( $GLOBALS['maca_dns_answers'] );
	}

	function gethostbynamel( string $host ): array {
		return array();
	}
}

maca_reset_test_state();
$GLOBALS['maca_wp_environment_type'] = 'production';
$GLOBALS['maca_dns_queries']         = array();
$GLOBALS['maca_dns_answers']         = array( array( array( 'ip' => '1.1.1.1' ) ), array() );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'headers'  => array( 'Content-Type' => 'application/json' ),
	'body'     => '{}',
);

$host = 'cloud-dns-fixture.example.test';
$url  = 'https://' . $host . '/health/live';
$args = array( 'method' => 'GET' );

$first = Npcink_Cloud_Outbound_Policy::request_json( $url, $args );
maca_assert( ! is_wp_error( $first ) && 1 === count( $GLOBALS['maca_http_requests'] ), 'Behavior: a public DNS answer allows the first mocked Cloud request.' );

$second = Npcink_Cloud_Outbound_Policy::request_json( $url, $args );
$query  = array( $host, DNS_A | DNS_AAAA );
maca_assert( array( $query, $query ) === $GLOBALS['maca_dns_queries'], 'Behavior: each outbound request looks up the same hostname independently.' );
maca_assert(
	is_wp_error( $second )
	&& 'cloud_outbound_target_not_allowed' === $second->get_error_code()
	&& 1 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: a later empty DNS answer fails closed without dispatching another request.'
);
