<?php
/**
 * Behavior tests for the AI plugin localization audit command.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Recursively removes a directory and its contents without shelling out.
 *
 * @param string $dir Directory path.
 * @return void
 */
function npcink_cloud_addon_ai_i18n_audit_test_rm_dir( string $dir ): void {
	if ( ! is_dir( $dir ) ) {
		return;
	}

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		if ( $item->isDir() && ! $item->isLink() ) {
			rmdir( $item->getPathname() );
		} else {
			unlink( $item->getPathname() );
		}
	}

	rmdir( $dir );
}

$fixture_root = sys_get_temp_dir() . '/npcink-cloud-addon-ai-i18n-audit-' . getmypid();
npcink_cloud_addon_ai_i18n_audit_test_rm_dir( $fixture_root );
mkdir( $fixture_root . '/includes', 0777, true );
mkdir( $fixture_root . '/includes/Abilities/Demo', 0777, true );
mkdir( $fixture_root . '/build-scripts/admin', 0777, true );
mkdir( $fixture_root . '/build-scripts/features', 0777, true );

file_put_contents(
	$fixture_root . '/includes/settings.php',
	<<<'PHP'
<?php
esc_html__( 'Generate Image', 'ai' );
esc_html__( 'New Audit Button', 'ai' );
esc_html_e( 'Echoed Audit Label', 'ai' );
esc_html_e( 'Ignore Default Domain', 'default' );
esc_html__( 'Ignore Default Domain', 'default' );
_n( 'One audit result', 'Many audit results', $count, 'ai' );
PHP
);

file_put_contents(
	$fixture_root . '/includes/Abilities/Demo/Demo.php',
	<<<'PHP'
<?php
esc_html__( 'Demo ability label', 'ai' );
esc_html__( 'The ID of the demo object.', 'ai' );
esc_html__( 'Demo ability failed. Please ensure it is configured.', 'ai' );
PHP
);

file_put_contents(
	$fixture_root . '/build-scripts/admin/page.js',
	<<<'JS'
(0,wp.i18n.__)("JS Audit Label","ai");
(0,wp.i18n.__)("Unicode ellipsis\u2026","ai");
(0,wp.i18n.__)("\u2014 Unicode default \u2014","ai");
(0,wp.i18n.__)("Ignored JS Label","default");
(0,t._n)("First audit image.","First audit images.",d,"ai");
(0,t._n)("Second audit image.","Second audit images.",d,"ai");
JS
);

file_put_contents(
	$fixture_root . '/build-scripts/features/image-generation.js',
	<<<'JS'
(0,wp.i18n.__)("Outpaint the image to create a wider panoramic view. Expand the scene outward in all directions to fill the empty transparent border while preserving the original style, lighting, colors, and perspective. Continue textures, structures, and environmental elements naturally so the extension blends with the original image.","ai");
JS
);

require_once MACA_TEST_ROOT . '/scripts/audit-ai-plugin-localization.php';

ob_start();
$status = npcink_cloud_addon_ai_i18n_audit_main(
	array( 'audit-ai-plugin-localization.php', '--path=' . $fixture_root ),
	MACA_TEST_ROOT
);
$report = (string) ob_get_clean();

maca_assert(
	0 === $status
	&& false !== strpos( $report, 'WordPress AI plugin localization audit' )
	&& false !== strpos( $report, 'Missing strings' )
	&& false !== strpos( $report, 'Fixed UI review candidates' )
	&& false !== strpos( $report, 'Missing review groups' )
	&& false !== strpos( $report, 'fixed_ui_candidates:' )
	&& false !== strpos( $report, 'ability_error_notices:' )
	&& false !== strpos( $report, 'dynamic_ability_metadata:' )
	&& false !== strpos( $report, 'schema_or_json_fields:' )
	&& false !== strpos( $report, 'long_prompt_copy:' )
	&& false !== strpos( $report, 'stale_review:' )
	&& false !== strpos( $report, '"New Audit Button"' )
	&& false !== strpos( $report, '"Echoed Audit Label"' )
	&& false !== strpos( $report, '"One audit result"' )
	&& false !== strpos( $report, '"Many audit results"' )
	&& false !== strpos( $report, '"JS Audit Label"' )
	&& false !== strpos( $report, '"First audit image."' )
	&& false !== strpos( $report, '"First audit images."' )
	&& false !== strpos( $report, '"Second audit image."' )
	&& false !== strpos( $report, '"Second audit images."' )
	&& false !== strpos( $report, '"Demo ability label"' )
	&& false !== strpos( $report, '"Demo ability failed. Please ensure it is configured."' )
	&& false !== strpos( $report, '"The ID of the demo object."' )
	&& false !== strpos( $report, '"Outpaint the image to create a wider panoramic view.' )
	&& false !== strpos( $report, '"Unicode ellipsis…"' )
	&& false !== strpos( $report, '"— Unicode default —"' )
	&& false === strpos( $report, 'Unicode ellipsisu2026' )
	&& false === strpos( $report, 'u2014 Unicode default u2014' )
	&& false === strpos( $report, 'Ignore Default Domain' )
	&& false === strpos( $report, 'Ignored JS Label' ),
	'AI plugin localization audit reports missing ai-domain strings, covers echo and minified plural forms, and ignores other domains.'
);

npcink_cloud_addon_ai_i18n_audit_test_rm_dir( $fixture_root );
