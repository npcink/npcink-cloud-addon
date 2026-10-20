<?php
/** Default release availability: no outbound execution, preserved ordinary transport. */
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
maca_load_addon_classes();
maca_reset_test_state();
maca_seed_settings( true );
$pause_client = Npcink_Cloud_Runtime_Client_Factory::configured();
$policy = new ReflectionProperty( Npcink_Cloud_Discoverability_Pause::class, 'paused_runtime_features' );
$policy->setAccessible( true );
$release_policy = $policy->getValue();
foreach ( $release_policy as $feature => $markers ) {
	foreach ( $markers as $marker ) {
		foreach ( array( 'ability_name', 'execution_kind', 'contract_version', 'profile_id' ) as $field ) {
			$result = $pause_client->execute_runtime( array( $field => $marker ) );
			maca_assert( is_wp_error( $result ) && 'cloud_feature_paused' === $result->get_error_code(), 'Paused runtime marker is rejected: ' . $marker );
		}
	}
}
$paused_dispatch = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response( array(), array() );
maca_assert( is_wp_error( $paused_dispatch ) && 'cloud_feature_paused' === $paused_dispatch->get_error_code(), 'Media derivatives pause before source uploads.' );
$paused_plan = Npcink_Cloud_Media_Plan_Projection::build_media_optimization_payload( array(), array(), array(), array() );
maca_assert( is_wp_error( $paused_plan ) && 'cloud_feature_paused' === $paused_plan->get_error_code(), 'New media optimization plans are paused.' );
$retry = $pause_client->retry_run( 'run_retained', array() );
maca_assert( is_wp_error( $retry ) && 'cloud_feature_paused' === $retry->get_error_code(), 'Old inspection retries remain paused.' );
maca_assert( array() === $GLOBALS['maca_http_requests'], 'Paused executions and retries make zero HTTP requests.' );
$GLOBALS['maca_http_response_queue'][] = array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array() ) ) );
$pause_client->get_run( 'run_retained' );
maca_assert( 1 === count( $GLOBALS['maca_http_requests'] ), 'Historical run reads remain available.' );
$GLOBALS['maca_http_response_queue'][] = array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array() ) ) );
$pause_client->execute_runtime( array( 'ability_name' => 'ordinary-title' ) );
maca_assert( 2 === count( $GLOBALS['maca_http_requests'] ), 'Ordinary runtime transport remains available.' );

maca_assert(
	! Npcink_Cloud_Discoverability_Pause::is_admin_surface_paused( 'wordpress_ai_generation_tests' ),
	'Default release policy exposes WordPress AI generation-test entry points (P0 unpause).'
);
maca_assert(
	! Npcink_Cloud_Discoverability_Pause::is_admin_surface_paused( 'unknown_surface' ),
	'Unknown admin surfaces are not treated as paused.'
);
