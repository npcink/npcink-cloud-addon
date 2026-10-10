<?php
/**
 * Deterministic performance and startup safety guards for Npcink Cloud Addon.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_reset_test_state();
maca_load_addon_classes();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );

if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return basename( $file );
	}
}

// The plugin load evaluates includes/class-cloud-wordpress-ai-connector.php,
// whose provider bundle only declares behind AI client SDK class_exists
// guards; declare the shared empty stubs first so the bundle is available to
// behavior-wordpress-ai-connector-result.php in this shared process.
require_once __DIR__ . '/wordpress-ai-client-stubs.php';

require_once MACA_TEST_ROOT . '/npcink-cloud-addon.php';

maca_assert(
	array() === $GLOBALS['maca_http_requests'],
	'Performance guard: loading the plugin files performs zero outbound HTTP requests.'
);

npcink_cloud_addon_bootstrap();

$observability_hook = Npcink_Cloud_Observability_Collector::CRON_HOOK;
$site_knowledge_hook = Npcink_Cloud_Site_Knowledge_Change_Bridge::RECONCILE_HOOK;
maca_assert(
	array() === $GLOBALS['maca_http_requests']
	&& 1 === absint( $GLOBALS['maca_schedule_call_counts'][ $observability_hook ] ?? 0 )
	&& 1 === absint( $GLOBALS['maca_schedule_call_counts'][ $site_knowledge_hook ] ?? 0 )
	&& 'hourly' === wp_get_schedule( $observability_hook )
	&& 'hourly' === wp_get_schedule( $site_knowledge_hook ),
	'Performance guard: bootstrap performs zero HTTP and creates one hourly event per recurring job.'
);

$maintenance_reads_before = absint( $GLOBALS['maca_option_read_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION ] ?? 0 );
$buffer_reads_before = absint( $GLOBALS['maca_option_read_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION ] ?? 0 );
for ( $iteration = 0; $iteration < 25; $iteration++ ) {
	Npcink_Cloud_Observability_Collector::sync_schedule();
	Npcink_Cloud_Site_Knowledge_Change_Bridge::sync_schedule();
}

maca_assert(
	1 === absint( $GLOBALS['maca_schedule_call_counts'][ $observability_hook ] ?? 0 )
	&& 1 === absint( $GLOBALS['maca_schedule_call_counts'][ $site_knowledge_hook ] ?? 0 )
	&& $maintenance_reads_before === absint( $GLOBALS['maca_option_read_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION ] ?? 0 )
	&& $buffer_reads_before === absint( $GLOBALS['maca_option_read_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION ] ?? 0 )
	&& array() === $GLOBALS['maca_http_requests'],
	'Performance guard: repeated schedule synchronization is idempotent, network-free, and adds no delivery option reads.'
);

$GLOBALS['maca_scheduled_event_schedules'][ $observability_hook ] = 'twicedaily';
$GLOBALS['maca_scheduled_event_schedules'][ $site_knowledge_hook ] = 'twicedaily';
Npcink_Cloud_Observability_Collector::sync_schedule();
Npcink_Cloud_Site_Knowledge_Change_Bridge::sync_schedule();

maca_assert(
	2 === absint( $GLOBALS['maca_schedule_call_counts'][ $observability_hook ] ?? 0 )
	&& 2 === absint( $GLOBALS['maca_schedule_call_counts'][ $site_knowledge_hook ] ?? 0 )
	&& 'hourly' === wp_get_schedule( $observability_hook )
	&& 'hourly' === wp_get_schedule( $site_knowledge_hook ),
	'Performance guard: an incorrect recurring schedule is replaced once with the hourly contract.'
);

// Settings decryption is memoized per request: repeated reads must not
// re-fetch and re-decrypt the stored option.
$settings_option_name = Npcink_Cloud_Addon_Settings::option_name();
$settings_reads_before = absint( $GLOBALS['maca_option_read_counts'][ $settings_option_name ] ?? 0 );
for ( $iteration = 0; $iteration < 25; $iteration++ ) {
	Npcink_Cloud_Addon_Settings::get_settings();
}

maca_assert(
	$settings_reads_before === absint( $GLOBALS['maca_option_read_counts'][ $settings_option_name ] ?? 0 ),
	'Performance guard: repeated settings reads are served from the per-request cache.'
);

// Monitoring-off steady state: with the flush cron already absent, schedule
// synchronization must not churn option deletes on every request.
maca_set_monitoring_enabled( false );
unset( $GLOBALS['maca_scheduled_events'][ $observability_hook ], $GLOBALS['maca_scheduled_event_schedules'][ $observability_hook ] );
update_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array( 'seeded' => 'buffer' ), false );
Npcink_Cloud_Observability_Collector::sync_schedule();

maca_assert(
	array() === $GLOBALS['maca_http_requests']
	&& ! wp_next_scheduled( $observability_hook )
	&& array( 'seeded' => 'buffer' ) === get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ),
	'Performance guard: monitoring-off steady state performs no editor feedback cleanup.'
);

// The enabled-to-disabled transition is the one moment cleanup must run: the
// still-scheduled flush cron is cleared and the editor feedback buffer drops.
$GLOBALS['maca_scheduled_events'][ $observability_hook ] = time() + 3600;
$GLOBALS['maca_scheduled_event_schedules'][ $observability_hook ] = 'hourly';
Npcink_Cloud_Observability_Collector::sync_schedule();

maca_assert(
	array() === $GLOBALS['maca_http_requests']
	&& ! wp_next_scheduled( $observability_hook )
	&& array() === get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ),
	'Performance guard: the monitoring-off transition clears the flush cron and editor feedback buffer.'
);

maca_set_monitoring_enabled( true );
