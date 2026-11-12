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

// The accepted single-image exception is a complete request, never a generic bypass.
$single_payload = array(
	'request_contract_version' => 'media_job_request.v1', 'operation' => 'image.transform.v1',
	'source_artifact_id' => 'art_' . str_repeat( '1', 32 ), 'result_ttl_minutes' => 30,
	'processing_purpose' => 'save_as_new.v1',
	'params' => array( 'mode' => 'manual', 'target_format' => 'webp', 'max_width' => 1200, 'quality' => 82, 'source_media_type' => 'image', 'resize_mode' => 'preserve' ),
);
foreach ( array( 'purpose', 'resize', 'crop', 'batch', 'governance', 'auto', 'extra', 'quality' ) as $invalid ) {
	$case = $single_payload;
	if ( 'purpose' === $invalid ) { $case['processing_purpose'] = 'unknown'; }
	if ( 'resize' === $invalid ) { $case['params']['resize_mode'] = 'fit'; }
	if ( 'crop' === $invalid ) { $case['params']['crop'] = array( 'type' => 'aspect_ratio' ); }
	if ( 'batch' === $invalid ) { $case['batch_context'] = array( 'item_count' => 1 ); }
	if ( 'governance' === $invalid ) { $case['governance'] = array(); }
	if ( 'auto' === $invalid ) { $case['params']['mode'] = 'auto_safe'; }
	if ( 'extra' === $invalid ) { $case['skip_pause'] = true; }
	if ( 'quality' === $invalid ) { $case['params']['quality'] = 0; }
	$before = count( $GLOBALS['maca_http_requests'] );
	maca_assert( ! Npcink_Cloud_Discoverability_Pause::is_save_as_new_media_job( $case ), 'Invalid single-image exception rejected: ' . $invalid );
	maca_assert( is_wp_error( $pause_client->create_media_job( $case ) ) && $before === count( $GLOBALS['maca_http_requests'] ), 'Invalid purpose cannot sign or send: ' . $invalid );
}
foreach ( array( 'compression', 'watermark' ) as $task ) {
	$case = $single_payload;
	if ( 'watermark' === $task ) {
		$case['params']['target_format'] = 'original';
		$case['params']['watermark'] = array( 'type' => 'text', 'text' => 'Test', 'position' => 'bottom_right', 'opacity' => 0.5 );
	}
	$GLOBALS['maca_http_response_queue'][] = array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array( 'run_id' => 'run_single' ) ) ) );
	$before = count( $GLOBALS['maca_http_requests'] );
	maca_assert( ! is_wp_error( $pause_client->create_media_job( $case ) ) && $before + 1 === count( $GLOBALS['maca_http_requests'] ), 'Bounded single-image transport is available: ' . $task );
}
$before = count( $GLOBALS['maca_http_requests'] );
$fit_ability = maca_ability_fixture();
$fit_ability['cloud_job_payload']['resize_mode'] = 'fit';
$fit_result = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response( $fit_ability, array( 'bytes' => base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+j2ioAAAAASUVORK5CYII=' ), 'filename' => 'source.png', 'mime_type' => 'image/png' ), '', '', array(), 'save_as_new.v1' );
maca_assert( is_wp_error( $fit_result ) && 'cloud_media_job_contract_invalid' === $fit_result->get_error_code() && $before === count( $GLOBALS['maca_http_requests'] ), 'Invalid bounded transport is rejected before source uploads.' );

$logo_ability = maca_ability_fixture();
$logo_ability['cloud_job_payload']['resize_mode'] = 'preserve';
$logo_ability['cloud_job_payload']['target_format'] = 'original';
$logo_ability['cloud_job_payload']['watermark'] = array( 'type' => 'image', 'position' => 'bottom_right', 'opacity' => 0.5 );
$logo_source = array( 'artifact_id' => 'art_' . str_repeat( '2', 32 ), 'expires_at' => maca_future_expiry() );
$logo_reference = array( 'artifact_id' => 'art_' . str_repeat( '3', 32 ), 'expires_at' => maca_future_expiry() );
$GLOBALS['maca_http_response_queue'][] = array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array( 'run_id' => 'run_logo', 'status' => 'queued' ) ) ) );
$logo_result = Npcink_Cloud_Media_Derivative_Transport::dispatch_from_ability_response( $logo_ability, $logo_source, '', '', $logo_reference, 'save_as_new.v1' );
maca_assert( ! is_wp_error( $logo_result ), ( is_wp_error( $logo_result ) ? $logo_result->get_error_code() . ': ' . $logo_result->get_error_message() . ' ' : '' ) . 'Image watermark passes the pre-upload predicate and real job transport.' );
$logo_http = end( $GLOBALS['maca_http_requests'] );
$logo_body = json_decode( $logo_http['args']['body'], true );
maca_assert( $logo_reference['artifact_id'] === $logo_body['watermark_artifact_id'] && 'save_as_new.v1' === $logo_body['processing_purpose'], 'Image watermark and source references survive bounded dispatch.' );
