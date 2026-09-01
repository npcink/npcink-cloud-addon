<?php
/**
 * Behavior tests for metadata-only media governance audit transport.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

/**
 * Returns one exact audit request fixture.
 *
 * @return array<string,mixed>
 */
function maca_media_governance_audit_fixture(): array {
	return array(
		'contract_version' => 'media_governance_audit_request.v1',
		'snapshot'         => array(
			'snapshot_id'       => 'mga-snapshot-20260831',
			'captured_at'       => '2026-08-31T12:00:00+08:00',
			'inventory_complete' => true,
			'capacity'          => array( 'uploads_bytes' => 1000000 ),
			'coverage'          => array(
				'complete' => true,
				'sources'  => array( 'attachment_meta', 'post_content' ),
			),
			'items'             => array(
				array(
					'item_id'          => 'attachment:123',
					'source_sha256'     => 'sha256:' . str_repeat( 'a', 64 ),
					'filesize_bytes'    => 1000000,
					'format'            => 'jpeg',
					'width'             => 1600,
					'height'            => 900,
					'animated'          => false,
					'reference_state'   => 'referenced',
					'evidence_revision' => 'attachment-123-rev-1',
					'evidence_sources'  => array( 'attachment_meta', 'post_content' ),
				),
			),
		),
	);
}

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'headers'  => array( 'Content-Type' => 'application/json' ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'run_id' => 'run_media_governance_audit',
				'status' => 'succeeded',
				'result' => array(
					'contract_version'      => 'media_governance_audit.v1',
					'artifact_type'         => 'media_governance_audit',
					'status'                => 'ready',
					'write_posture'         => 'read_only',
					'direct_wordpress_write' => false,
				),
			),
		)
	),
);
$client = Npcink_Cloud_Runtime_Client_Factory::configured();
$result = $client ? $client->execute_toolbox_media_governance_audit_runtime( maca_media_governance_audit_fixture(), 'trace-media-governance', 'media-governance-audit' ) : null;
$request = $GLOBALS['maca_http_requests'][0] ?? array();
$body = json_decode( (string) ( $request['args']['body'] ?? '' ), true );
maca_assert(
	is_array( $result )
	&& 1 === count( $GLOBALS['maca_http_requests'] )
	&& str_ends_with( (string) ( $request['url'] ?? '' ), '/v1/runtime/execute' )
	&& 'npcink-toolbox/audit-media-governance' === ( $body['ability_name'] ?? null )
	&& 'inline' === ( $body['execution_pattern'] ?? null )
	&& 'internal' === ( $body['data_classification'] ?? null )
	&& false === ( $body['policy']['allow_fallback'] ?? null )
	&& array( 'contract_version', 'snapshot' ) === array_keys( $body['input'] ?? array() )
	&& ! isset( $body['input']['path'], $body['input']['bytes'], $body['input']['replace_file'] ),
	'Behavior: media governance audit sends one exact metadata-only inline runtime request.'
);

foreach ( array( 'unknown', 'replace_file', 'too_many_items' ) as $invalid_case ) {
	maca_reset_test_state();
	maca_seed_settings( true );
	$invalid = maca_media_governance_audit_fixture();
	if ( 'too_many_items' === $invalid_case ) {
		$invalid['snapshot']['items'] = array_fill( 0, 501, $invalid['snapshot']['items'][0] );
	} else {
		$invalid[ $invalid_case ] = true;
	}
	$client = Npcink_Cloud_Runtime_Client_Factory::configured();
	$invalid_result = $client ? $client->execute_toolbox_media_governance_audit_runtime( $invalid ) : null;
	maca_assert(
		is_wp_error( $invalid_result ) && array() === $GLOBALS['maca_http_requests'],
		'Behavior: invalid media governance audit ' . $invalid_case . ' fails before outbound HTTP.'
	);
}
