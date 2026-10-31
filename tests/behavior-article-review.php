<?php
/** Fixed, read-only article review transport behavior. */
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
maca_load_addon_classes();
maca_reset_test_state();
maca_seed_settings( true );

$blocks = array( array( 'b1', 'body', '中文AI工具。😀' ) );
$canonical = json_encode( array( 'article_review_cloud_request.v1', 'zh-CN', array( 'language' ), $blocks ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS );
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
$expected_input = $request;
unset( $expected_input['contract_version'] );
maca_assert( $payload['input'] === $expected_input, 'Article review preserves the exact bounded editor snapshot.' );
maca_assert( 'npcink-toolbox/review-article' === $payload['ability_name'] && 'article_review_cloud_request.v1' === $payload['contract_version'], 'Article review uses the fixed Cloud ability and request contract.' );
maca_assert( 'pii' === $payload['data_classification'] && 'no_store' === $payload['storage_mode'] && 0 === $payload['retry_max'] && 0 === $payload['retention_ttl'] && false === $payload['policy']['allow_fallback'], 'Article review is inline, no-store, no-retry, and fallback-disabled.' );

// Validate primitive types, list identity and non-BMP boundaries before transport.
$invalid_requests = array();
foreach ( array( 'contract_version', 'language', 'offset_unit', 'snapshot_sha256' ) as $key ) {
	$bad = $request; $bad[ $key ] = array(); $invalid_requests[] = $bad;
}
$bad = $request; $bad['blocks'] = array( 'named' => $request['blocks'][0] ); $invalid_requests[] = $bad;
$bad = $request; $bad['blocks'][] = $request['blocks'][0]; $invalid_requests[] = $bad;
$bad = $request; $bad['blocks'][0]['text'] = "\xFF"; $invalid_requests[] = $bad;
$bad = $request; $bad['blocks'][0]['text'] = " \t\n"; $invalid_requests[] = $bad;
$bad = $request; $bad['blocks'][0]['text'] = "\u{00a0}\u{3000}"; $invalid_requests[] = $bad;
foreach ( $invalid_requests as $bad ) {
	maca_assert( is_wp_error( Npcink_Cloud_Runtime_Request_Guards::normalize_toolbox_article_review_request( $bad ) ), 'Strict snapshot rejects malformed primitives, lists, duplicate refs, UTF-8 and empty text.' );
}
foreach ( array( str_repeat( '😀', 800 ), "原文  保留。\n<x>\u{2028}\u{2029}" ) as $text ) {
	$good = $request;
	$good['blocks'][0]['text'] = $text;
	$canonical = json_encode( array( 'article_review_cloud_request.v1', 'zh-CN', array( 'language' ), array( array( 'b1', 'body', $text ) ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS );
	$good['snapshot_sha256'] = hash( 'sha256', $canonical );
	$normalized = Npcink_Cloud_Runtime_Request_Guards::normalize_toolbox_article_review_request( $good );
	maca_assert( ! is_wp_error( $normalized ) && $normalized['input']['blocks'][0]['text'] === $text && ! isset( $normalized['input']['contract_version'] ), 'Exact Unicode text and 1600 UTF-16 boundary match the strict Cloud input shape.' );
}
$good['blocks'][0]['text'] = str_repeat( '😀', 801 );
$canonical = json_encode( array( 'article_review_cloud_request.v1', 'zh-CN', array( 'language' ), array( array( 'b1', 'body', $good['blocks'][0]['text'] ) ) ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS );
$good['snapshot_sha256'] = hash( 'sha256', $canonical );
$too_large = Npcink_Cloud_Runtime_Request_Guards::normalize_toolbox_article_review_request( $good );
maca_assert( is_wp_error( $too_large ) && 'cloud_toolbox_article_review_text_too_large' === $too_large->get_error_code(), 'Non-BMP text above 1600 UTF-16 units is rejected by the size guard.' );

// Check selection is canonical and hash-bound; old language-only callers remain valid.
foreach ( array( array( 'logic' ), array( 'content_risk' ), array( 'language', 'logic', 'content_risk' ) ) as $checks ) {
    $expanded = $request;
    $expanded['checks'] = $checks;
    $expanded['snapshot_sha256'] = hash( 'sha256', json_encode( array( 'article_review_cloud_request.v1', 'zh-CN', $checks, $blocks ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_LINE_TERMINATORS ) );
    $normalized = Npcink_Cloud_Runtime_Request_Guards::normalize_toolbox_article_review_request( $expanded );
    maca_assert( ! is_wp_error( $normalized ) && $normalized['input']['checks'] === $checks && 'no_store' === $normalized['storage_mode'], 'Extended checks retain exact canonical identity and privacy policy.' );
}
foreach ( array( array(), array( 'logic', 'language' ), array( 'language', 'language' ), array( 'unknown' ), array( array() ), array( 'named' => 'logic' ) ) as $checks ) {
    $bad = $request; $bad['checks'] = $checks;
    $error = Npcink_Cloud_Runtime_Request_Guards::normalize_toolbox_article_review_request( $bad );
    maca_assert( is_wp_error( $error ) && 'cloud_toolbox_article_review_contract_invalid' === $error->get_error_code(), 'Invalid check selection fails its contract guard before hash validation or signing.' );
}
