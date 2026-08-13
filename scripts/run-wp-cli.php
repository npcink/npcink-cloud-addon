<?php
/**
 * Runs WP-CLI for Composer release and localization commands.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/wp-cli-command.php';

try {
	$wp_cli_bin = npcink_cloud_addon_resolve_wp_cli();
} catch ( RuntimeException $exception ) {
	fwrite( STDERR, '[wp-cli] error: ' . $exception->getMessage() . PHP_EOL );
	exit( 1 );
}

$arguments = array_slice( $argv, 1 );
$command = array_merge( array( PHP_BINARY, $wp_cli_bin ), $arguments );
$escaped = implode( ' ', array_map( 'escapeshellarg', $command ) );
passthru( $escaped, $exit_code );
exit( $exit_code );
