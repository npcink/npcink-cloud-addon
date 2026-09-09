<?php
/** Read-only evidence check for natural customer-journey Cron delivery. */
if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
	fwrite( STDERR, "This script must run through WP-CLI.\n" );
	exit( 1 );
}

$hook   = 'npcink_cloud_addon_flush_observability';
$expected_site_url = getenv( 'WP_EXPECTED_SITE_URL' ) ?: '';
$buffer = get_option( 'npcink_cloud_addon_customer_journey_buffer', array() );
$status = get_option( 'npcink_cloud_addon_observability_status', array() );
$next   = wp_next_scheduled( $hook );
$events = array();
foreach ( is_array( $buffer ) ? array_slice( $buffer, 0, 20 ) : array() as $event ) {
	if ( ! is_array( $event ) ) {
		continue;
	}
	$events[] = array(
		'event_id'    => sanitize_text_field( (string) ( $event['event_id'] ?? '' ) ),
		'run_id'      => sanitize_text_field( (string) ( $event['run_id'] ?? '' ) ),
		'journey'     => sanitize_key( (string) ( $event['journey'] ?? '' ) ),
		'step'        => sanitize_key( (string) ( $event['step'] ?? '' ) ),
		'occurred_at' => sanitize_text_field( (string) ( $event['occurred_at'] ?? '' ) ),
	);
}
$count = is_array( $buffer ) ? count( $buffer ) : 0;
$enabled = class_exists( 'Npcink_Cloud_Addon_Settings' ) && method_exists( 'Npcink_Cloud_Addon_Settings', 'is_monitoring_enabled' )
	? (bool) Npcink_Cloud_Addon_Settings::is_monitoring_enabled() : null;
$state = $count > 0 ? 'real_event_pending_or_unclassified' : 'pending_no_real_event';
if ( ! $enabled ) {
	$state = 'monitoring_disabled_or_unverified';
} elseif ( false === $next ) {
	$state = 'cron_not_scheduled';
}
$output = array(
	'observed_at' => gmdate( 'c' ),
	'site_url' => (string) get_option( 'siteurl', '' ),
	'disable_wp_cron' => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
	'monitoring_enabled' => $enabled,
	'verification_state' => $state,
	'journey_buffer' => array( 'count' => $count, 'events' => $events ),
	'cron' => array(
		'hook' => $hook,
		'scheduled' => false !== $next,
		'next_utc' => false === $next ? null : gmdate( 'c', $next ),
		'schedule' => false === $next ? null : wp_get_schedule( $hook ),
	),
	'last_upload' => array(
		'ok' => ! empty( $status['last_upload_ok'] ),
		'at' => sanitize_text_field( (string) ( $status['last_uploaded_at'] ?? '' ) ),
		'sent_count' => absint( $status['last_sent_count'] ?? 0 ),
		'stored_count' => absint( $status['last_stored_count'] ?? 0 ),
		'duplicate_count' => absint( $status['last_duplicate_count'] ?? 0 ),
		'total_sent' => absint( $status['total_sent'] ?? 0 ),
		'error' => sanitize_text_field( (string) ( $status['last_upload_error'] ?? '' ) ),
	),
	'next_action' => $count > 0
		? 'Wait for natural WP-Cron, then compare the same event_id/run_id with Cloud receipt.'
		: 'Use the site normally to create one real event; do not run Cron or flush manually.',
);
if ( '' !== $expected_site_url && untrailingslashit( (string) $output['site_url'] ) !== untrailingslashit( $expected_site_url ) ) {
	WP_CLI::error( 'Wrong WordPress target: expected ' . $expected_site_url . ', got ' . $output['site_url'] . '. Set WP_DB_SOCKET for the intended Local site.' );
}
WP_CLI::log( (string) wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) );
