<?php
/**
 * Guards the documentation index, checkout portability, and PHPStan budget.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$docs_root = MACA_TEST_ROOT . '/docs';
$docs_index = maca_read( $docs_root . '/README.md' );
preg_match_all( '/(?:^|[`(])([A-Za-z0-9_.-]+\.md)(?=[`)])/m', $docs_index, $indexed_names );
$missing_docs = array_diff(
	array_map( 'basename', glob( $docs_root . '/*.md' ) ?: array() ),
	array( 'README.md' ),
	$indexed_names[1]
);
maca_assert( empty( $missing_docs ), 'Static: top-level Markdown documents appear in the index. Missing: ' . implode( ', ', $missing_docs ) );

$documentation = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $docs_root, FilesystemIterator::SKIP_DOTS ) );
$markdown_paths = array( MACA_TEST_ROOT . '/AGENTS.md', MACA_TEST_ROOT . '/README.md' );
foreach ( $documentation as $doc ) {
	if ( $doc->isFile() && 'md' === $doc->getExtension() ) {
		$markdown_paths[] = $doc->getPathname();
	}
}
$machine_specific = array();
foreach ( $markdown_paths as $path ) {
	if ( preg_match( '~/(?:Users|home)/[A-Za-z0-9_.-]+/(?:gitee/|\.codex/|Local Sites/)~', maca_read( $path ) ) ) {
		$machine_specific[] = substr( $path, strlen( MACA_TEST_ROOT ) + 1 );
	}
}
maca_assert( empty( $machine_specific ), 'Static: documentation uses portable checkout references. Nonportable: ' . implode( ', ', $machine_specific ) );

$baseline = maca_read( MACA_TEST_ROOT . '/phpstan-baseline.neon' );
$entry_count = preg_match_all( '/^\h+-(?:\h+.*)?$/m', $baseline );
preg_match_all( '/^\h+count:\h+([1-9][0-9]*)\h*$/m', $baseline, $baseline_counts );
$occurrence_count = array_sum( $baseline_counts[1] );
maca_assert(
	false !== $entry_count
	&& $entry_count === count( $baseline_counts[1] )
	&& $entry_count <= 38 && $occurrence_count <= 42,
	'Static: PHPStan baseline stays within 38 bounded entries and 42 suppressed occurrences; found ' . $entry_count . ' / ' . $occurrence_count . '.'
);
