<?php
/**
 * WP-CLI entry point for the disposable MySQL reconciliation smoke.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'NPCINK_RECONCILIATION_SMOKE' ) || ! NPCINK_RECONCILIATION_SMOKE ) {
	exit( 1 );
}

global $wpdb;
$db_version = (string) $wpdb->get_var( 'SELECT VERSION()' );
if ( ! str_starts_with( $db_version, '8.4.' ) || is_file( WP_CONTENT_DIR . '/db.php' ) ) {
	WP_CLI::error( 'Expected native WordPress wpdb on the disposable MySQL 8.4 database.' );
}

require_once dirname( __DIR__ ) . '/playground/mu-plugins/fixtures/site-knowledge-reconciliation.php';
$result = npcink_cloud_addon_reconciliation_fixture();
if ( is_wp_error( $result ) ) {
	WP_CLI::error( $result->get_error_message() . ' ' . wp_json_encode( $result->get_error_data() ) );
}

WP_CLI::success( sprintf( 'WordPress %s / MySQL %s verified ordered Site Knowledge reconciliation across 51 same-second rows and a newer row.', get_bloginfo( 'version' ), $db_version ) );
