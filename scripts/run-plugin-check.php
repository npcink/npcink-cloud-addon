<?php
/**
 * Runs Plugin Check and fails when output contains any error finding.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/wp-cli-command.php';

$wp_path = getenv( 'WP_PATH' ) ?: '/Users/muze/Local Sites/magick-ai/app/public';
try {
	$wp_cli_bin = npcink_cloud_addon_resolve_wp_cli();
} catch ( RuntimeException $exception ) {
	fwrite( STDERR, '[plugin-check] error: ' . $exception->getMessage() . PHP_EOL );
	exit( 1 );
}
$default_wp_cli_php = '/Users/muze/Library/Application Support/Local/lightning-services/php-8.5.3+1/bin/darwin-arm64/bin/php';
$wp_cli_php = getenv( 'WP_CLI_PHP' ) ?: ( is_file( $default_wp_cli_php ) ? $default_wp_cli_php : PHP_BINARY );
$default_socket = '/Users/muze/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock';
$socket = getenv( 'WP_DB_SOCKET' ) ?: ( file_exists( $default_socket ) ? $default_socket : '' );
$plugin_target = getenv( 'PLUGIN_CHECK_TARGET' ) ?: '';
$temporary_plugin_dir = '';
$remove_temporary_plugin = static function () use ( &$temporary_plugin_dir ): void {
	if ( '' === $temporary_plugin_dir || ! is_dir( $temporary_plugin_dir ) ) {
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $temporary_plugin_dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $temporary_plugin_dir );
	$temporary_plugin_dir = '';
};
register_shutdown_function( $remove_temporary_plugin );
if ( '' === $plugin_target ) {
	$zip_path = dirname( __DIR__ ) . '/build/npcink-cloud-addon.zip';
	$plugins_dir = rtrim( $wp_path, '/\\' ) . '/wp-content/plugins';
	$temporary_slug = 'npcink-cloud-addon-release-check-' . getmypid();
	$temporary_plugin_dir = $plugins_dir . '/' . $temporary_slug;
	if ( ! is_file( $zip_path ) || ! is_dir( $plugins_dir ) || file_exists( $temporary_plugin_dir ) ) {
		fwrite( STDERR, "[plugin-check] error: final ZIP or isolated plugin-check directory is unavailable\n" );
		exit( 1 );
	}
	if ( ! mkdir( $temporary_plugin_dir, 0775, true ) ) {
		fwrite( STDERR, "[plugin-check] error: could not create isolated plugin-check directory\n" );
		exit( 1 );
	}
	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path ) ) {
		fwrite( STDERR, "[plugin-check] error: final release ZIP is unreadable\n" );
		exit( 1 );
	}
	for ( $index = 0; $index < $zip->numFiles; $index++ ) {
		$name = $zip->getNameIndex( $index );
		if ( ! is_string( $name ) || str_ends_with( $name, '/' ) ) {
			continue;
		}
		$prefix = 'npcink-cloud-addon/';
		if ( ! str_starts_with( $name, $prefix ) || str_contains( $name, '../' ) ) {
			fwrite( STDERR, '[plugin-check] error: unsafe final ZIP entry: ' . $name . PHP_EOL );
			exit( 1 );
		}
		$relative_path = substr( $name, strlen( $prefix ) );
		$destination = $temporary_plugin_dir . '/' . $relative_path;
		$destination_dir = dirname( $destination );
		if ( ! is_dir( $destination_dir ) && ! mkdir( $destination_dir, 0775, true ) ) {
			fwrite( STDERR, "[plugin-check] error: could not prepare final ZIP entry\n" );
			exit( 1 );
		}
		$contents = $zip->getFromIndex( $index );
		if ( ! is_string( $contents ) || false === file_put_contents( $destination, $contents ) ) {
			fwrite( STDERR, "[plugin-check] error: could not unpack final ZIP entry\n" );
			exit( 1 );
		}
	}
	$zip->close();
	$plugin_target = $temporary_slug;
}
$command = array( $wp_cli_php );
if ( '' !== $socket ) {
	$command[] = '-d';
	$command[] = 'mysqli.default_socket=' . $socket;
}
$command = array_merge(
	$command,
	array(
		$wp_cli_bin,
		'--path=' . $wp_path,
		'--no-color',
		'plugin',
		'check',
		$plugin_target,
		'--format=strict-table',
		'--slug=npcink-cloud-addon',
		'--exclude-directories=tests,.git,.github,vendor,node_modules,build,dist,sj,scripts',
		'--exclude-files=.gitignore,.distignore,AGENTS.md,README.md,composer.json,composer.lock,phpcs.xml,phpcs.xml.dist,phpstan.neon,phpstan.neon.dist',
	)
);
$escaped = implode( ' ', array_map( 'escapeshellarg', $command ) ) . ' 2>&1';
$output = array();
$exit_code = 0;
exec( $escaped, $output, $exit_code );
$text = implode( PHP_EOL, $output );
if ( 0 !== $exit_code ) {
	echo $text . PHP_EOL;
	fwrite( STDERR, "[plugin-check] error: strict Plugin Check failed\n" );
	exit( 1 );
}
if ( 1 === preg_match( '/(^|[|\s])ERROR([|\s]|$)/mi', $text ) ) {
	echo $text . PHP_EOL;
	fwrite( STDERR, "[plugin-check] error findings detected\n" );
	exit( 1 );
}
echo "[plugin-check] strict result: ok\n";
