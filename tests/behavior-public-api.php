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
	&& npcink_cloud_addon_runtime_client() instanceof Npcink_Cloud_Runtime_Client,
	'Behavior: deprecated raw settings and concrete client compatibility seams remain callable for existing integrations.'
);
