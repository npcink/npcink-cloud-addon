<?php
/**
 * Behavior contracts for the Runtime Client endpoint policy.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
maca_load_addon_classes();

$allowed_requests = array(
	array( 'POST', '/v1/runtime/execute' ),
	array( 'POST', '/v1/runtime/media/uploads' ),
	array( 'POST', '/v1/runtime/media/jobs' ),
	array( 'GET', '/v1/entitlements/current' ),
	array( 'GET', '/v1/runs/nightly-inspection/recent?limit=5' ),
	array( 'GET', '/v1/runs/run_1' ),
	array( 'GET', '/v1/runs/run_1/result' ),
	array( 'POST', '/v1/runs/run_1/retry' ),
	array( 'GET', '/v1/runtime/media/artifacts/art_0123456789abcdef0123456789abcdef/download' ),
	array( 'POST', '/v1/runtime/media/artifacts/art_0123456789abcdef0123456789abcdef/delivery-ack' ),
	array( 'POST', '/v1/observability/plugin-events' ),
	array( 'POST', '/v1/agent-feedback/events' ),
	array( 'GET', '/v1/agent-feedback/summary?window_hours=24' ),
	array( 'POST', '/v1/customer-journey/events' ),
	array( 'GET', '/v1/customer-journey/summary?window_hours=24&cohort_id=pilot_1' ),
	array( ' get ', '/v1/observability/plugin-summary?window_hours=24' ),
	array( 'GET', '/v1/entitlements/current?return_url=https://example.test/a/b' ),
	array( 'GET', '/v1/entitlements/current?return_url=https%3A%2F%2Fexample.test%2Fa%2Fb' ),
);
foreach ( $allowed_requests as $request ) {
	maca_assert(
		Npcink_Cloud_Runtime_Endpoint_Policy::allows( $request[0], $request[1] ),
		'Behavior: endpoint policy allows named runtime contract path ' . $request[0] . ' ' . $request[1]
	);
}

$denied_requests = array(
	array( 'GET', 'https://cloud.example.test/v1/entitlements/current' ),
	array( 'GET', '//cloud.example.test/v1/entitlements/current' ),
	array( 'GET', 'v1/entitlements/current' ),
	array( 'POST', '/v1//runtime/execute' ),
	array( 'GET', '/v1/entitlements/current#fragment' ),
	array( 'GET', '/v1/runs/./result' ),
	array( 'GET', '/v1/runs/../result' ),
	array( 'GET', '/v1/runs/%2e/result' ),
	array( 'GET', '/v1/runs/%2E%2E/result' ),
	array( 'GET', '/v1/runs/run%2Fresult' ),
	array( 'GET', '/v1/runs/run%252fresult' ),
	array( 'GET', '/v1/runs/run%5Cresult' ),
	array( 'GET', '/v1/runs/https://example.test/a/b' ),
	array( 'GET', '/v1/runs/https%3A%2F%2Fexample.test%2Fa%2Fb' ),
	array( 'POST', '/v1/entitlements/current' ),
	array( 'GET', '/v1/runtime/media/uploads' ),
	array( 'POST', '/v1/runtime/media/uploads/source' ),
	array( 'POST', '/v1/runtime/media-derivatives' ),
	array( 'GET', '/v1/runtime/artifacts/art_0123456789abcdef0123456789abcdef/download' ),
	array( 'GET', '/v1/runtime/media/artifacts/art_0123456789abcdef0123456789abcdeg/download' ),
	array( 'GET', '/v1/runtime/media/artifacts/art_0123456789ABCDEF0123456789ABCDEF/download' ),
	array( 'GET', '/v1/runtime/media/artifacts/art_0123456789abcdef0123456789abcdef/download?token=forbidden' ),
	array( 'POST', '/v1/runtime/media/artifacts/art_0123456789abcdef0123456789abcdef/delivery-ack?trace=forbidden' ),
	array( 'GET', '/v1/stats/profiles/profile_1' ),
	array( 'GET', '/v1/stats/instances/instance_1' ),
	array( 'GET', '/v1/runs/nightly-inspection/recent/result' ),
	array( 'GET', '/v1/unlisted' ),
);
foreach ( $denied_requests as $request ) {
	maca_assert(
		! Npcink_Cloud_Runtime_Endpoint_Policy::allows( $request[0], $request[1] ),
		'Behavior: endpoint policy rejects out-of-contract path ' . $request[0] . ' ' . $request[1]
	);
}

maca_reset_test_state();
maca_seed_settings( true );
$client = new Npcink_Cloud_Runtime_Client();
foreach ( array( 'request', 'request_raw' ) as $request_method_name ) {
	$request_method = new ReflectionMethod( Npcink_Cloud_Runtime_Client::class, $request_method_name );
	$request_method->setAccessible( true );
	$result = $request_method->invoke( $client, 'GET', 'https://attacker.example/v1/entitlements/current' );
	maca_assert(
		is_wp_error( $result )
		&& 'cloud_runtime_endpoint_not_allowed' === $result->get_error_code()
		&& 'This Cloud endpoint is not allowed by the Cloud Addon runtime contract.' === $result->get_error_message()
		&& 403 === (int) ( $result->get_error_data()['status'] ?? 0 ),
		'Behavior: Runtime Client owns the endpoint rejection error for ' . $request_method_name . '.'
	);
}
maca_assert( array() === $GLOBALS['maca_http_requests'], 'Behavior: rejected endpoints never reach outbound HTTP.' );
