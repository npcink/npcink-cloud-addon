<?php
/**
 * Behavior tests for unified addon-owned local cleanup.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();
maca_reset_test_state();
maca_seed_settings( true );

$settings = Npcink_Cloud_Addon_Settings::get_settings();
$seed = implode( '|', array( $settings['base_url'], $settings['site_id'], $settings['key_id'] ) );
$entitlement_key = 'npcink_cloud_entitlement_' . md5( $seed );
$site_knowledge_key = 'npcink_cloud_site_knowledge_status_' . md5( $seed );

foreach (
	array(
		'npcink_cloud_addon_wp_ai_connector_connected',
		'npcink_cloud_addon_observability_buffer',
		'npcink_cloud_addon_observability_status',
		'npcink_cloud_addon_observability_summary',
		'npcink_cloud_addon_agent_feedback_summary',
		'npcink_cloud_addon_editor_assist_pending',
		'npcink_cloud_addon_site_knowledge_change_buffer',
		'npcink_cloud_addon_site_knowledge_change_status',
		'npcink_cloud_addon_site_knowledge_maintenance_cursor',
		$entitlement_key . '_refresh_lock',
		$site_knowledge_key . '_lock',
	) as $option_name
) {
	$GLOBALS['maca_options'][ $option_name ] = 'owned-state';
}
$GLOBALS['maca_transients'][ $entitlement_key ] = array( 'cached' => true );
$GLOBALS['maca_transients'][ $entitlement_key . '_refresh_failure' ] = array( 'message' => 'failed' );
$GLOBALS['maca_transients'][ $site_knowledge_key ] = array( 'cached' => true );
foreach (
	array(
		'npcink_cloud_addon_flush_observability',
		'npcink_cloud_addon_flush_site_knowledge_changes',
		'npcink_cloud_addon_reconcile_site_knowledge_changes',
	) as $hook
) {
	$GLOBALS['maca_scheduled_events'][ $hook ] = time() + 60;
}

Npcink_Cloud_Addon_Cleanup::delete_all( $settings );

maca_assert(
	array() === $GLOBALS['maca_options']
	&& array() === $GLOBALS['maca_transients']
	&& array() === $GLOBALS['maca_scheduled_events'],
	'Behavior: unified disconnect cleanup removes settings, connector marker, credential-scoped caches, buffers, locks, and cron hooks.'
);
