<?php
/**
 * Behavior tests for public API compatibility and non-secret state projection.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_reset_test_state();
if ( ! defined( 'NPCINK_CLOUD_ADDON_FILE' ) ) {
	define( 'NPCINK_CLOUD_ADDON_FILE', MACA_TEST_ROOT . '/npcink-cloud-addon.php' );
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}
require_once MACA_TEST_ROOT . '/includes/bootstrap.php';
maca_seed_settings( true );

$state = npcink_cloud_addon_get_connection_state();
maca_assert(
	true === ( $state['configured'] ?? null )
	&& true === ( $state['verified'] ?? null )
	&& true === ( $state['site_knowledge_delivery_enabled'] ?? null )
	&& true === ( $state['wordpress_ai_connector_enabled'] ?? null )
	&& ! array_key_exists( 'secret', $state )
	&& ! array_key_exists( 'site_id', $state )
	&& ! array_key_exists( 'key_id', $state ),
	'Behavior: public connection state exposes local status and permissions without credentials or identifiers.'
);

maca_assert(
	is_array( npcink_cloud_addon_get_settings() )
	&& ! function_exists( 'npcink_cloud_addon_runtime_client' )
	&& Npcink_Cloud_Runtime_Client_Factory::configured() instanceof Npcink_Cloud_Runtime_Client,
	'Behavior: deprecated raw settings remain callable while the concrete runtime client seam is removed from the public API.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array( 'contract_version' => 'customer_journey_summary.v1' ),
		)
	),
);
$summary = npcink_cloud_addon_get_customer_journey_summary( 48, 'pilot_1' );
$summary_request = $GLOBALS['maca_http_requests'][0] ?? array();
maca_assert(
	! is_wp_error( $summary )
	&& false !== strpos( (string) ( $summary_request['url'] ?? '' ), '/v1/customer-journey/summary?window_hours=48&cohort_id=pilot_1' )
	&& 'GET' === (string) ( $summary_request['args']['method'] ?? '' ),
	'Behavior: the manual customer-journey helper performs one bounded signed read without adding an analytics UI.'
);
