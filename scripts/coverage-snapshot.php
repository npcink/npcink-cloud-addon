<?php
/**
 * Optional coverage snapshot for the deterministic contract suite.
 *
 * Usage: composer run test:coverage
 *
 * This is a local inspection tool, not a CI gate: it exists so untested
 * branches can be found before they accumulate. Percentages need an
 * xdebug-compatible driver (xdebug or phpdbg); with pcov only covered-line
 * counts are reported. The three subprocess-sandbox tests in tests/run.php
 * are not part of the snapshot, and a failing suite aborts the snapshot
 * before any coverage output (tests/run.php exits on sandbox failure).
 *
 * @package NpcinkCloudAddon
 */

declare( strict_types=1 );

$has_xdebug_api = function_exists( 'xdebug_start_code_coverage' );
$has_pcov = extension_loaded( 'pcov' );

if ( ! $has_xdebug_api && ! $has_pcov ) {
	fwrite( STDERR, "[coverage] no driver available in this PHP build.\n" );
	fwrite( STDERR, "[coverage] install pcov (pecl install pcov) or xdebug, or use a phpdbg build with the xdebug-compatible coverage API.\n" );
	exit( 1 );
}

$root = dirname( __DIR__ );

if ( $has_pcov && ! $has_xdebug_api ) {
	\pcov\start();
	require $root . '/tests/run.php';
	$coverage = \pcov\collect();
	\pcov\stop();
	$total = 0;
	foreach ( $coverage as $file => $lines ) {
		if ( 0 === strpos( $file, $root . '/includes/' ) ) {
			// pcov maps also contain 0-hit lines; only positive hits are covered.
			$total += is_array( $lines ) ? count( array_filter( $lines, static function ( $count ): bool {
				return is_int( $count ) && $count > 0;
			} ) ) : 0;
		}
	}
	echo '[coverage] driver: pcov (covered lines only, no executable total)' . PHP_EOL;
	echo '[coverage] includes/ covered lines: ' . $total . PHP_EOL;
	return;
}

xdebug_start_code_coverage( XDEBUG_CC_UNUSED );
require $root . '/tests/run.php';
$coverage = xdebug_get_code_coverage();
xdebug_stop_code_coverage();

$rows = array();
$sum_covered = 0;
$sum_executable = 0;
foreach ( $coverage as $file => $lines ) {
	if ( 0 !== strpos( $file, $root . '/includes/' ) ) {
		continue;
	}
	$covered = 0;
	$executable = 0;
	foreach ( is_array( $lines ) ? $lines : array() as $count ) {
		// With XDEBUG_CC_UNUSED the map carries -1 for non-executable lines;
		// only counts >= 0 are executable (PHPUnit's xdebug driver does the same).
		if ( is_int( $count ) && $count >= 0 ) {
			$executable++;
			if ( $count > 0 ) {
				$covered++;
			}
		}
	}
	if ( 0 === $executable ) {
		continue;
	}
	$rows[] = array( substr( $file, strlen( $root ) + 1 ), $covered, $executable );
	$sum_covered += $covered;
	$sum_executable += $executable;
}

usort( $rows, static function ( array $a, array $b ): int {
	return $b[2] <=> $a[2];
} );

echo '[coverage] driver: xdebug-compatible — subprocess sandbox tests are not included' . PHP_EOL;
foreach ( $rows as $row ) {
	printf( "%-58s %6d / %-6d %5.1f%%\n", $row[0], $row[1], $row[2], 100 * $row[1] / $row[2] );
}
printf( "%-58s %6d / %-6d %5.1f%%\n", 'TOTAL (includes/)', $sum_covered, $sum_executable, $sum_executable > 0 ? 100 * $sum_covered / $sum_executable : 0.0 );
echo '[coverage] snapshot only — compare between runs manually; not a CI gate.' . PHP_EOL;
