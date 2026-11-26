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
$legacy_entitlement_key = 'npcink_cloud_entitlement_' . md5( $seed );
$entitlement_cache_key_method = new ReflectionMethod( 'Npcink_Cloud_Entitlement_Summary', 'cache_key' );
if ( PHP_VERSION_ID < 80100 ) {
	$entitlement_cache_key_method->setAccessible( true );
}
$entitlement_key = $entitlement_cache_key_method->invoke( null, $settings );
$site_knowledge_key = 'npcink_cloud_site_knowledge_status_' . md5( $seed );
$delivery_state_option = 'npcink_cloud_addon_site_knowledge_maintenance_cursor';
$reconciliation_option = 'npcink_cloud_addon_site_knowledge_reconciliation_cursor';
$delivery_state = array(
	'_lifecycle' => array(
		'owner'  => array( 'phase' => 'sending', 'scope' => 'fixture-scope', 'key' => 'post:42' ),
		'scopes' => array(
			'fixture-scope' => array( 'sources' => array(), 'operations' => array(), 'dispatches' => array() ),
		),
	),
);

foreach (
	array(
		'npcink_cloud_addon_wp_ai_connector_connected',
		'npcink_cloud_addon_observability_buffer',
		'npcink_cloud_addon_observability_status',
		'npcink_cloud_addon_observability_summary',
		'npcink_cloud_addon_customer_journey_buffer',
		'npcink_cloud_addon_customer_journey_status',
		'npcink_cloud_addon_agent_feedback_summary',
		'npcink_cloud_addon_editor_feedback_buffer',
		'npcink_cloud_addon_editor_assist_pending',
		'npcink_cloud_addon_site_knowledge_change_buffer',
		'npcink_cloud_addon_site_knowledge_change_status',
		$reconciliation_option,
		$entitlement_key . '_refresh_lock',
		$site_knowledge_key . '_lock',
	) as $option_name
) {
	$GLOBALS['maca_options'][ $option_name ] = 'owned-state';
}
$GLOBALS['maca_options'][ $delivery_state_option ] = $delivery_state;
$GLOBALS['maca_options']['npcink_cloud_addon_monitoring_prompt_answered'] = true;
$GLOBALS['maca_options']['npcink_cloud_addon_last_ai_error'] = array( 'code' => 'fixture-error' );
$GLOBALS['maca_transients']['npcink_cloud_addon_first_connection'] = 1;
$GLOBALS['maca_transients'][ $entitlement_key ] = array( 'cached' => true );
$GLOBALS['maca_transients'][ $entitlement_key . '_refresh_failure' ] = array( 'message' => 'failed' );
$GLOBALS['maca_transients'][ $legacy_entitlement_key ] = array( 'pre_schema' => true );
$GLOBALS['maca_transients'][ $legacy_entitlement_key . '_refresh_failure' ] = array( 'message' => 'failed' );
$GLOBALS['maca_transients'][ $site_knowledge_key ] = array( 'cached' => true );
$GLOBALS['maca_transients'][ $site_knowledge_key . '_media_evidence_ids' ] = array( 501, 502 );
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
	array( $delivery_state_option => $delivery_state, 'npcink_cloud_addon_monitoring_prompt_answered' => true ) === $GLOBALS['maca_options']
	&& array() === $GLOBALS['maca_transients']
	&& array() === $GLOBALS['maca_scheduled_events'],
	'Behavior: disconnect retains the Site Knowledge delivery envelope and answered monitoring prompt, but removes credentials, diagnostics, first-connection state, buffers, locks, and cron hooks.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_options'][ $delivery_state_option ] = $delivery_state;
$GLOBALS['maca_options'][ $reconciliation_option ] = array( 'modified_gmt' => '2026-11-25 00:00:00', 'post_id' => 42 );
$GLOBALS['maca_options']['npcink_cloud_addon_monitoring_prompt_answered'] = true;
$GLOBALS['maca_options']['npcink_cloud_addon_auth_generation'] = str_repeat( 'a', 32 );
$GLOBALS['maca_options']['npcink_cloud_addon_last_ai_error'] = 'fixture-error';
$GLOBALS['maca_transients']['npcink_cloud_addon_first_connection'] = true;
define( 'WP_UNINSTALL_PLUGIN', 'npcink-cloud-addon/npcink-cloud-addon.php' );
require dirname( __DIR__ ) . '/uninstall.php';

maca_assert(
	array() === $GLOBALS['maca_options'] && array() === $GLOBALS['maca_transients'],
	'Behavior: the uninstall entrypoint removes the Site Knowledge delivery envelope, reconciliation cursor, and remaining test addon state.'
);

maca_reset_test_state();
$GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan'] = array(
	'active' => true,
	'state'  => 'processing',
);
$GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan_lock'] = 'legacy-lock';
$GLOBALS['maca_transients']['npcink_cloud_addon_media_index_status'] = array( 'state' => 'processing' );
$GLOBALS['maca_scheduled_events']['npcink_cloud_addon_continue_media_recognition'] = time() + 60;

Npcink_Cloud_Addon_Cleanup::delete_inactive_legacy_media_continuation();

maca_assert(
	isset( $GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan'] )
	&& isset( $GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan_lock'] )
	&& isset( $GLOBALS['maca_transients']['npcink_cloud_addon_media_index_status'] )
	&& isset( $GLOBALS['maca_scheduled_events']['npcink_cloud_addon_continue_media_recognition'] )
	&& ! isset( $GLOBALS['maca_options']['npcink_cloud_addon_media_continuation_cleanup_0_2_0'] ),
	'Behavior: bootstrap preserves active legacy media continuation state and does not mark cleanup complete.'
);

maca_reset_test_state();
$GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan'] = array(
	'active' => false,
	'state'  => 'complete',
);
$GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan_lock'] = 'legacy-lock';
$GLOBALS['maca_transients']['npcink_cloud_addon_media_index_status'] = array( 'state' => 'complete' );
$GLOBALS['maca_scheduled_events']['npcink_cloud_addon_continue_media_recognition'] = time() + 60;

Npcink_Cloud_Addon_Cleanup::delete_inactive_legacy_media_continuation();

maca_assert(
	! isset( $GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan'] )
	&& ! isset( $GLOBALS['maca_options']['npcink_cloud_addon_media_recognition_plan_lock'] )
	&& ! isset( $GLOBALS['maca_transients']['npcink_cloud_addon_media_index_status'] )
	&& ! isset( $GLOBALS['maca_scheduled_events']['npcink_cloud_addon_continue_media_recognition'] )
	&& 'complete' === ( $GLOBALS['maca_options']['npcink_cloud_addon_media_continuation_cleanup_0_2_0'] ?? '' ),
	'Behavior: bootstrap removes inactive legacy media continuation state once and records completion.'
);
