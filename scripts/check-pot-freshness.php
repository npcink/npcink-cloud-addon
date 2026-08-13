<?php
/**
 * Checks that the committed POT matches current plugin PHP sources.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/wp-cli-command.php';

$root = dirname( __DIR__ );
$committed_path = $root . '/languages/npcink-cloud-addon.pot';
$temporary_path = tempnam( sys_get_temp_dir(), 'npcink-cloud-addon-pot-' );
if ( false === $temporary_path ) {
	fwrite( STDERR, "[i18n] error: could not create a temporary POT path\n" );
	exit( 1 );
}
try {
	$wp_cli_bin = npcink_cloud_addon_resolve_wp_cli();
} catch ( RuntimeException $exception ) {
	@unlink( $temporary_path );
	fwrite( STDERR, '[i18n] error: ' . $exception->getMessage() . PHP_EOL );
	exit( 1 );
}
$command = implode(
	' ',
	array(
		escapeshellarg( PHP_BINARY ),
		escapeshellarg( $wp_cli_bin ),
		'i18n',
		'make-pot',
		escapeshellarg( $root ),
		escapeshellarg( $temporary_path ),
		'--slug=npcink-cloud-addon',
		'--domain=npcink-cloud-addon',
		'--exclude=build,dist,sj,tests,scripts',
		'--skip-js',
	)
) . ' 2>&1';
$output = array();
$exit_code = 0;
exec( $command, $output, $exit_code );
if ( 0 !== $exit_code ) {
	@unlink( $temporary_path );
	fwrite( STDERR, implode( PHP_EOL, $output ) . PHP_EOL );
	exit( $exit_code );
}
$committed = is_readable( $committed_path ) ? file_get_contents( $committed_path ) : false;
$generated = file_get_contents( $temporary_path );
@unlink( $temporary_path );
if ( ! is_string( $committed ) || ! is_string( $generated ) ) {
	fwrite( STDERR, "[i18n] error: could not read POT data\n" );
	exit( 1 );
}
$normalize = static function ( string $pot ): string {
	$pot = preg_replace( '/"POT-Creation-Date: [^\\n]*\\n"/', '"POT-Creation-Date: NORMALIZED\\n"', $pot );
	return is_string( $pot ) ? str_replace( "\r\n", "\n", $pot ) : '';
};
if ( $normalize( $committed ) !== $normalize( $generated ) ) {
	fwrite( STDERR, "[i18n] error: POT is stale; run composer run i18n:refresh\n" );
	exit( 1 );
}
echo "[i18n] POT freshness: ok\n";
