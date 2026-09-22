<?php
/**
 * Behavior tests for meaningful PR body validation.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

require_once MACA_TEST_ROOT . '/scripts/validate-pr-body.php';
$valid_body = <<<'MARKDOWN'
## Scope

Cloud Addon transport validation only.

## Cloud Addon Boundary

No control-plane or WordPress write ownership changes.

## Verification

`composer run test:all` passed.

## Risk

- Residual risk: Existing compatibility consumers still use the deprecated generic client.
- Rollback plan: Revert the scoped connector changes.
MARKDOWN;
$placeholder_body = <<<'MARKDOWN'
## Scope

- [ ] Placeholder

## Boundary

- [ ] Placeholder

## Verification

- [ ] Placeholder

## Risk

- Residual risk:
- Rollback plan:
MARKDOWN;

foreach ( array( 'valid' => $valid_body, 'placeholder' => $placeholder_body ) as $case => $body ) {
	$path = tempnam( sys_get_temp_dir(), 'maca-pr-body-' );
	maca_assert( is_string( $path ) && false !== file_put_contents( $path, $body ), 'Fixture: ' . $case . ' PR body is created.' );
	ob_start();
	$exit_code = npcink_cloud_addon_validate_pr_body_main( array( 'validate-pr-body.php', $path ) );
	ob_end_clean();
	unlink( $path );
	maca_assert(
		( 'valid' === $case && 0 === $exit_code ) || ( 'placeholder' === $case && 0 !== $exit_code ),
		'Behavior: PR body validator ' . ( 'valid' === $case ? 'accepts meaningful sections.' : 'rejects empty template placeholders.' )
	);
}
