<?php
/**
 * Independent WordPress requests against the disposable native MySQL fixture.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'WP_CLI' ) || ! WP_CLI || ! defined( 'NPCINK_RECONCILIATION_SMOKE' ) || ! NPCINK_RECONCILIATION_SMOKE ) {
	exit( 1 );
}

$assert = static function ( bool $ok, string $case ): void {
	if ( ! $ok ) {
		WP_CLI::error( $case );
	}
};
$candidate = static function ( string $account ): array {
	return array(
		'base_url' => 'https://connection.example.test/',
		'site_id'  => 'fixture_' . $account,
		'key_id'   => 'fixture_key',
		'secret'   => 'fixture_only_not_a_cloud_credential',
	);
};

if ( defined( 'NPCINK_CONNECTION_FIXTURE_STEP' ) ) {
	if ( 'disconnect' === NPCINK_CONNECTION_FIXTURE_STEP ) {
		Npcink_Cloud_Addon_Cleanup::delete_all( Npcink_Cloud_Addon_Settings::get_settings() );
		Npcink_Cloud_Addon_Settings::restore_disconnected_base_url( 'https://connection.example.test/' );
		$assert( ! Npcink_Cloud_Addon_Settings::is_configured(), 'Independent request did not disconnect.' );
	} elseif ( 'reconnect' === NPCINK_CONNECTION_FIXTURE_STEP ) {
		$generation = Npcink_Cloud_Authorization_State::begin();
		$assert( '' !== $generation && Npcink_Cloud_Addon_Settings::write_settings( $candidate( 'replacement' ), $generation ), 'Independent request did not reconnect.' );
	}
	return;
}

$run_worker = static function ( string $action ) use ( $assert ): void {
	$code = sprintf( 'define( "NPCINK_CONNECTION_FIXTURE_STEP", %s ); require %s;', var_export( $action, true ), var_export( __FILE__, true ) );
	$runner = dirname( __DIR__, 2 ) . '/scripts/run-wp-cli.php';
	$process = proc_open( array( PHP_BINARY, $runner, '--path=' . ABSPATH, '--skip-packages', 'eval', $code ), array(), $pipes );
	$assert( is_resource( $process ) && 0 === proc_close( $process ), 'Failed to complete independent WordPress request.' );
};

foreach ( array( 'disconnect', 'reconnect' ) as $action ) {
	Npcink_Cloud_Addon_Cleanup::delete_all( Npcink_Cloud_Addon_Settings::get_settings() );
	$generation = Npcink_Cloud_Authorization_State::begin();
	$assert( Npcink_Cloud_Authorization_State::is_current( $generation ), 'Could not start an attempt.' );
	// Pause after the old request read and approved the generation, but before
	// wpdb sends the conditional UPDATE. No shared PHP or WordPress cache.
	$paused = false;
	$before_commit = static function ( string $sql ) use ( &$paused, $action, $run_worker ): string {
		if ( ! $paused && str_starts_with( $sql, 'UPDATE ' ) && str_contains( $sql, 'HEX(option_value)' ) ) {
			$paused = true;
			$run_worker( $action );
		}
		return $sql;
	};
	add_filter( 'query', $before_commit );
	$committed = Npcink_Cloud_Addon_Settings::write_settings( $candidate( 'old' ), $generation );
	remove_filter( 'query', $before_commit );
	$assert( $paused && ! $committed, 'A superseded attempt wrote credentials after independent ' . $action . '.' );
	$settings = Npcink_Cloud_Addon_Settings::get_settings();
	$assert( ( 'reconnect' === $action ? 'fixture_replacement' : '' ) === $settings['site_id'], 'The independent request did not retain its result.' );
}

$generation = Npcink_Cloud_Authorization_State::begin();
$assert( '' !== $generation && Npcink_Cloud_Addon_Settings::write_settings( $candidate( 'normal' ), $generation ), 'An uninterrupted commit did not succeed.' );
$assert( 'fixture_normal' === Npcink_Cloud_Addon_Settings::get_settings()['site_id'], 'The committed settings were not visible.' );
$run_worker( 'disconnect' );
$assert( ! Npcink_Cloud_Authorization_State::is_current( $generation ) && ! Npcink_Cloud_Addon_Settings::write_settings( $candidate( 'normal' ), $generation ), 'Disconnect after commit did not fence subsequent writes.' );

WP_CLI::success( 'Native MySQL retained disconnect and replacement results across independent WordPress processes; uninterrupted credential commit succeeded.' );
