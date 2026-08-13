<?php
/**
 * Validates the repository PR body contract and rejects placeholder-only sections.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

$body_path = $argv[1] ?? '';
$body = '-' === $body_path ? stream_get_contents( STDIN ) : ( is_readable( $body_path ) ? file_get_contents( $body_path ) : false );
if ( ! is_string( $body ) ) {
	fwrite( STDERR, "PR body is unreadable.\n" );
	exit( 1 );
}
$required = array(
	'Scope'        => '/^#{1,6}\s+.*\bscope\b/im',
	'Boundary'     => '/^#{1,6}\s+.*\bboundary\b/im',
	'Verification' => '/^#{1,6}\s+.*\bverification\b/im',
	'Risk'         => '/^#{1,6}\s+.*\brisk\b/im',
);
$sections = array();
preg_match_all( '/^#{1,6}\s+(.+?)\s*$\R(.*?)(?=^#{1,6}\s+|\z)/ims', $body, $matches, PREG_SET_ORDER );
foreach ( $matches as $match ) {
	$title = trim( $match[1] );
	foreach ( $required as $name => $pattern ) {
		if ( 1 === preg_match( $pattern, '# ' . $title ) ) {
			$sections[ $name ] = trim( $match[2] );
		}
	}
}
foreach ( array_keys( $required ) as $name ) {
	$content = (string) ( $sections[ $name ] ?? '' );
	$content = preg_replace( '/<!--.*?-->/s', '', $content );
	$content = is_string( $content ) ? trim( $content ) : '';
	$meaningful = preg_replace( '/(?m)^\s*-\s*\[\s\]\s*.*$|(?m)^\s*-\s*(Residual risk|Rollback plan):\s*$/i', '', $content );
	if ( '' === trim( (string) $meaningful ) ) {
		fwrite( STDERR, 'PR body section requires meaningful content: ' . $name . PHP_EOL );
		exit( 1 );
	}
}
$risk = (string) $sections['Risk'];
if ( ! preg_match( '/Residual risk:[^\r\n]*\S/i', $risk ) || ! preg_match( '/Rollback plan:[^\r\n]*\S/i', $risk ) ) {
	fwrite( STDERR, "PR Risk must include non-empty Residual risk and Rollback plan values.\n" );
	exit( 1 );
}
echo "PR body contract: ok\n";
