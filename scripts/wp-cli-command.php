<?php
/**
 * Resolves the WP-CLI executable used by local and CI release tooling.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

/**
 * Return a readable WP-CLI script path.
 */
function npcink_cloud_addon_resolve_wp_cli(): string {
	$configured = trim( (string) getenv( 'WP_CLI_BIN' ) );
	if ( '' !== $configured ) {
		if ( is_file( $configured ) && is_readable( $configured ) ) {
			return $configured;
		}

		throw new RuntimeException( 'WP_CLI_BIN does not point to a readable file: ' . $configured );
	}

	$path_result = array();
	$path_status = 0;
	exec( 'command -v wp 2>/dev/null', $path_result, $path_status );
	$path_wp_cli = 0 === $path_status && isset( $path_result[0] ) ? trim( $path_result[0] ) : '';
	if ( '' !== $path_wp_cli && is_file( $path_wp_cli ) && is_readable( $path_wp_cli ) ) {
		return $path_wp_cli;
	}

	$legacy_path = '/tmp/wp-cli.phar';
	if ( is_file( $legacy_path ) && is_readable( $legacy_path ) ) {
		return $legacy_path;
	}

	throw new RuntimeException( 'WP-CLI was not found. Set WP_CLI_BIN or install wp in PATH.' );
}
