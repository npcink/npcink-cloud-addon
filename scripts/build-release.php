<?php
/**
 * Builds and verifies a deterministic release ZIP from tracked distribution files.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

$root = dirname( __DIR__ );
$build_root = $root . '/build';
$stage_root = $build_root . '/npcink-cloud-addon';
$zip_path = $build_root . '/npcink-cloud-addon.zip';
$manifest_path = $build_root . '/npcink-cloud-addon.manifest.json';
$fixed_timestamp = 946684800;

/**
 * Fails release construction.
 *
 * @param string $message Failure detail.
 * @return void
 */
function npcink_release_fail( string $message ): void {
	fwrite( STDERR, '[release] error: ' . $message . PHP_EOL );
	exit( 1 );
}

/**
 * Removes a known build path recursively.
 *
 * @param string $path Build path.
 * @return void
 */
function npcink_release_remove_path( string $path ): void {
	if ( ! file_exists( $path ) && ! is_link( $path ) ) {
		return;
	}
	if ( is_file( $path ) || is_link( $path ) ) {
		unlink( $path );
		return;
	}
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $iterator as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $path );
}

$source_manifest_path = $root . '/release-manifest.txt';
$source_manifest = is_readable( $source_manifest_path ) ? file( $source_manifest_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES ) : false;
if ( ! is_array( $source_manifest ) ) {
	npcink_release_fail( 'release-manifest.txt is unavailable' );
}
$release_files = array_values( array_unique( array_map( 'trim', $source_manifest ) ) );
sort( $release_files, SORT_STRING );
if ( ! in_array( 'npcink-cloud-addon.php', $release_files, true ) || ! in_array( 'readme.txt', $release_files, true ) ) {
	npcink_release_fail( 'required plugin entry files are missing' );
}
foreach ( $release_files as $relative_path ) {
	if (
		'' === $relative_path
		|| str_starts_with( $relative_path, '/' )
		|| str_contains( $relative_path, '../' )
		|| ! is_file( $root . '/' . $relative_path )
		|| is_link( $root . '/' . $relative_path )
	) {
		npcink_release_fail( 'invalid release manifest path: ' . $relative_path );
	}
}

npcink_release_remove_path( $stage_root );
npcink_release_remove_path( $zip_path );
npcink_release_remove_path( $manifest_path );
if ( ! is_dir( $build_root ) && ! mkdir( $build_root, 0775, true ) ) {
	npcink_release_fail( 'could not create build directory' );
}

$manifest_files = array();
foreach ( $release_files as $relative_path ) {
	$source_path = $root . '/' . $relative_path;
	$destination_path = $stage_root . '/' . $relative_path;
	$destination_dir = dirname( $destination_path );
	if ( ! is_dir( $destination_dir ) && ! mkdir( $destination_dir, 0775, true ) ) {
		npcink_release_fail( 'could not create staging directory for ' . $relative_path );
	}
	if ( ! copy( $source_path, $destination_path ) ) {
		npcink_release_fail( 'could not stage ' . $relative_path );
	}
	chmod( $destination_path, 0644 );
	touch( $destination_path, $fixed_timestamp );
	$manifest_files[] = array(
		'path'   => 'npcink-cloud-addon/' . $relative_path,
		'bytes'  => filesize( $destination_path ),
		'sha256' => hash_file( 'sha256', $destination_path ),
	);
}

if ( ! class_exists( 'ZipArchive' ) ) {
	npcink_release_fail( 'PHP ZipArchive extension is required' );
}
$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	npcink_release_fail( 'could not create release ZIP' );
}
foreach ( $release_files as $relative_path ) {
	$archive_path = 'npcink-cloud-addon/' . $relative_path;
	if ( ! $zip->addFile( $stage_root . '/' . $relative_path, $archive_path ) ) {
		npcink_release_fail( 'could not add ' . $relative_path . ' to ZIP' );
	}
	if ( method_exists( $zip, 'setMtimeName' ) ) {
		$zip->setMtimeName( $archive_path, $fixed_timestamp );
	}
	if ( method_exists( $zip, 'setExternalAttributesName' ) ) {
		$zip->setExternalAttributesName( $archive_path, ZipArchive::OPSYS_UNIX, 0100644 << 16 );
	}
	if ( method_exists( $zip, 'setCompressionName' ) ) {
		$zip->setCompressionName( $archive_path, ZipArchive::CM_STORE );
	}
}
$zip->close();

$main_plugin = file_get_contents( $stage_root . '/npcink-cloud-addon.php' );
$readme = file_get_contents( $stage_root . '/readme.txt' );
if ( ! is_string( $main_plugin ) || ! preg_match( '/^ \* Version:\s*([^\r\n]+)/m', $main_plugin, $plugin_match ) ) {
	npcink_release_fail( 'plugin Version header is unavailable' );
}
if ( ! is_string( $readme ) || ! preg_match( '/^Stable tag:\s*([^\r\n]+)/mi', $readme, $readme_match ) ) {
	npcink_release_fail( 'readme Stable tag is unavailable' );
}
$plugin_version = trim( $plugin_match[1] );
$stable_tag = trim( $readme_match[1] );
if ( $plugin_version !== $stable_tag ) {
	npcink_release_fail( 'plugin Version and readme Stable tag do not match' );
}

$manifest = array(
	'contract_version' => 'npcink_cloud_addon_release_manifest.v1',
	'plugin_version'   => $plugin_version,
	'file_count'       => count( $manifest_files ),
	'files'            => $manifest_files,
	'zip_sha256'       => hash_file( 'sha256', $zip_path ),
);
$encoded_manifest = json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
if ( ! is_string( $encoded_manifest ) || false === file_put_contents( $manifest_path, $encoded_manifest . PHP_EOL ) ) {
	npcink_release_fail( 'could not write release manifest' );
}

echo '[release] zip=' . $zip_path . PHP_EOL;
echo '[release] manifest=' . $manifest_path . PHP_EOL;
echo '[release] sha256=' . $manifest['zip_sha256'] . PHP_EOL;
