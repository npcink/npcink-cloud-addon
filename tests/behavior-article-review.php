<?php
/** Fixed, read-only article review transport behavior. */
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
maca_load_addon_classes();
maca_reset_test_state();
maca_seed_settings( true );

$blocks = array( array( 'b1', 'body', '中文AI工具。😀' ) );
$canonical = json_encode( array( 'article_review_cloud_request.v1', 'zh-CN', array( 'language' ), $blocks ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$request = array(
	'contract_version' => 'article_review_cloud_request.v1',
	'snapshot_sha256' => hash( 'sha256', (string) $canonical ),
	'language'       => 'zh-CN',
	'checks'         => array( 'language' ),
	'offset_unit'    => 'utf16_code_units',
	'blocks'         => array( array( 'block_ref' => 'b1', 'kind' => 'body', 'text' => '中文AI工具。😀' ) ),
);
$client = Npcink_Cloud_Runtime_Client_Factory::configured();

foreach ( array( array_merge( $request, array( 'replacement' => 'x' ) ), array_merge( $request, array( 'snapshot_sha256' => str_repeat( '0', 64 ) ) ), array_merge( $request, array( 'offset_unit' => 'characters' ) ) ) as $invalid ) {
	maca_assert( is_wp_error( $client->execute_toolbox_article_review_runtime( $invalid ) ), 'Article review rejects unsupported or mismatched request fields before transport.' );
}
maca_assert( 0 === count( $GLOBALS['maca_http_requests'] ), 'Invalid article review input sends no HTTP request.' );

$GLOBALS['maca_http_response_queue'][] = array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array( 'result' => array() ) ) ) );
$client->execute_toolbox_article_review_runtime( $request );
$payload = json_decode( $GLOBALS['maca_http_requests'][0]['args']['body'], true );
maca_assert( $payload['input'] === $request, 'Article review preserves the exact bounded editor snapshot.' );
maca_assert( 'npcink-toolbox/review-article' === $payload['ability_name'] && 'article_review_cloud_request.v1' === $payload['contract_version'], 'Article review uses the fixed Cloud ability and request contract.' );
maca_assert( 'pii' === $payload['data_classification'] && 'no_store' === $payload['storage_mode'] && 0 === $payload['retry_max'] && 0 === $payload['retention_ttl'] && false === $payload['policy']['allow_fallback'], 'Article review is inline, no-store, no-retry, and fallback-disabled.' );
