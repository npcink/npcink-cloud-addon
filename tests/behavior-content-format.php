<?php
/** Bounded formatting transport, with no real network or WordPress writes. */
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
maca_load_addon_classes();
maca_reset_test_state();
maca_seed_settings( true );
$client = Npcink_Cloud_Runtime_Client_Factory::configured();
$source = "<!-- wp:paragraph -->\r\n<p>中文AI工具。</p>\r\n<!-- /wp:paragraph -->";
$request = array( 'content' => $source, 'format' => 'html', 'source_sha256' => hash( 'sha256', $source ) );
foreach ( array(
	array_merge( $request, array( 'provider_id' => 'forbidden' ) ),
	array_merge( $request, array( 'format' => 'markdown' ) ),
	array_merge( $request, array( 'source_sha256' => str_repeat( '0', 64 ) ) ),
	array_merge( $request, array( 'content' => '' ) ),
	array_merge( $request, array( 'content' => str_repeat( 'x', 100001 ) ) ),
	array_merge( $request, array( 'content' => "\xFF" ) ),
) as $invalid ) {
	maca_assert( is_wp_error( $client->execute_toolbox_content_format_runtime( $invalid ) ), 'Formatting rejects invalid input before transport.' );
}
maca_assert( 0 === count( $GLOBALS['maca_http_requests'] ), 'Invalid formatting input sends no HTTP request.' );
for ( $i = 0; $i < 2; ++$i ) {
	$GLOBALS['maca_http_response_queue'][] = array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array( 'result' => array() ) ) ) );
	$client->execute_toolbox_content_format_runtime( $request );
}
$http = $GLOBALS['maca_http_requests'];
$payload = json_decode( $http[0]['args']['body'], true );
maca_assert( $payload['input'] === $request, 'Formatting preserves exact UTF-8 and CRLF source bytes.' );
maca_assert( 'no_store' === $payload['storage_mode'] && 'inline' === $payload['execution_pattern'] && 0 === $payload['retry_max'] && 0 === $payload['retention_ttl'], 'Formatting is inline with no retention or retries.' );
maca_assert( 'npcink-toolbox/format-content' === $payload['ability_name'] && 'content_format_request.v2' === $payload['contract_version'] && 'content-format.managed' === $payload['profile_id'] && 'pii' === $payload['data_classification'], 'Formatting uses the fixed Cloud scenario.' );
maca_assert( $http[0]['args']['headers']['Idempotency-Key'] !== $http[1]['args']['headers']['Idempotency-Key'], 'Each manual formatting request gets fresh idempotency.' );
