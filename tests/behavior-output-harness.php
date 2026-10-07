<?php
/**
 * Self-check for the fail-on-diagnostics guard installed by tests/helpers.php.
 *
 * Proves in throwaway subprocesses that an unexpected PHP warning fails the
 * process loudly, while an intentionally suppressed one keeps PHP semantics.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$helpers_path = var_export( __DIR__ . '/helpers.php', true );

$unsuppressed_snippet = 'require ' . $helpers_path . '; trigger_error( "maca diagnostics selfcheck", E_USER_WARNING ); echo "[unreached]\n";';
exec( escapeshellarg( PHP_BINARY ) . ' -d display_errors=0 -d error_reporting=E_ALL -r ' . escapeshellarg( $unsuppressed_snippet ) . ' 2>&1', $unsuppressed_output, $unsuppressed_status );
$unsuppressed_text = implode( "\n", $unsuppressed_output );

maca_assert(
	0 !== $unsuppressed_status
	&& false !== strpos( $unsuppressed_text, '[fail] unexpected PHP diagnostic' )
	&& false === strpos( $unsuppressed_text, '[unreached]' ),
	'Behavior: an unexpected PHP warning fails the test process loudly instead of leaving a green suite.'
);

$suppressed_snippet = 'require ' . $helpers_path . '; @trigger_error( "maca suppressed selfcheck", E_USER_WARNING ); echo "[continued]\n";';
exec( escapeshellarg( PHP_BINARY ) . ' -d display_errors=0 -d error_reporting=E_ALL -r ' . escapeshellarg( $suppressed_snippet ) . ' 2>&1', $suppressed_output, $suppressed_status );
$suppressed_text = implode( "\n", $suppressed_output );

maca_assert(
	0 === $suppressed_status
	&& false === strpos( $suppressed_text, '[fail]' )
	&& false !== strpos( $suppressed_text, '[continued]' ),
	'Behavior: an intentionally suppressed diagnostic keeps normal PHP semantics and does not fail the process.'
);
