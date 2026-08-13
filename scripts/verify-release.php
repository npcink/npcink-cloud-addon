<?php
/**
 * Verifies final release ZIP contents against its generated manifest.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$zip_path = $root . '/build/npcink-cloud-addon.zip';
$manifest_path = $root . '/build/npcink-cloud-addon.manifest.json';
$manifest = is_readable( $manifest_path ) ? json_decode( (string) file_get_contents( $manifest_path ), true ) : null;
if ( ! is_array( $manifest ) || ! is_file( $zip_path ) ) {
	fwrite( STDERR, "[release] error: build the release before verification\n" );
	exit( 1 );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path ) ) {
	fwrite( STDERR, "[release] error: release ZIP is unreadable\n" );
	exit( 1 );
}
$expected = array();
foreach ( $manifest['files'] ?? array() as $file ) {
	if ( ! is_array( $file ) || ! is_string( $file['path'] ?? null ) ) {
		continue;
	}
	$expected[ $file['path'] ] = $file;
}
$actual_names = array();
for ( $index = 0; $index < $zip->numFiles; $index++ ) {
	$name = $zip->getNameIndex( $index );
	if ( ! is_string( $name ) || str_ends_with( $name, '/' ) ) {
		continue;
	}
	if ( str_contains( $name, '../' ) || ! str_starts_with( $name, 'npcink-cloud-addon/' ) ) {
		fwrite( STDERR, '[release] error: unsafe ZIP path: ' . $name . PHP_EOL );
		exit( 1 );
	}
	$actual_names[] = $name;
	$contents = $zip->getFromIndex( $index );
	if ( ! isset( $expected[ $name ] ) || ! is_string( $contents ) || hash( 'sha256', $contents ) !== (string) $expected[ $name ]['sha256'] ) {
		fwrite( STDERR, '[release] error: unexpected or mismatched ZIP file: ' . $name . PHP_EOL );
		exit( 1 );
	}
}
$zip->close();
sort( $actual_names, SORT_STRING );
$expected_names = array_keys( $expected );
sort( $expected_names, SORT_STRING );
if ( $actual_names !== $expected_names || hash_file( 'sha256', $zip_path ) !== (string) ( $manifest['zip_sha256'] ?? '' ) ) {
	fwrite( STDERR, "[release] error: ZIP manifest does not match final artifact\n" );
	exit( 1 );
}
foreach ( array( '.git', '.github', 'tests', 'scripts', 'build', 'dist', 'sj', 'reasonix.toml' ) as $forbidden ) {
	foreach ( $actual_names as $name ) {
		if ( str_contains( $name, '/' . $forbidden . '/' ) || str_ends_with( $name, '/' . $forbidden ) ) {
			fwrite( STDERR, '[release] error: forbidden release path: ' . $name . PHP_EOL );
			exit( 1 );
		}
	}
}
echo '[release] verified_files=' . count( $actual_names ) . PHP_EOL;
echo '[release] sha256=' . $manifest['zip_sha256'] . PHP_EOL;

