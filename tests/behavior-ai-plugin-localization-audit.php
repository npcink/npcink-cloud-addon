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
esc_html__( 'Content Wizard', 'ai' );
esc_html__( 'Content of the demo object.', 'ai' );
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
(0,t._n)("%d Item selected","%d Items selected",d);
(0,i._x)("Edit %s (has errors)","field");
(0,wp.i18n.__)("Loading suggestions");
(0,t._n)("High","High",d,"other-domain");
JS
);

file_put_contents(
	$fixture_root . '/build-scripts/admin/no-ai-chunk.js',
	<<<'JS'
var who=(0,t.__)("User");
JS
);

file_put_contents(
	$fixture_root . '/build-scripts/features/image-generation.js',
	<<<'JS'
(0,wp.i18n.__)("Outpaint the image to create a wider panoramic view. Expand the scene outward in all directions to fill the empty transparent border while preserving the original style, lighting, colors, and perspective. Continue textures, structures, and environmental elements naturally so the extension blends with the original image.","ai");
JS
);

mkdir( $fixture_root . '/build/routes', 0777, true );
file_put_contents(
	$fixture_root . '/build/routes/content.min.js',
	<<<'JS'
var wpai={density:[["Compact","compact"],["Comfortable","comfortable"]]};
JS
);

require_once MACA_TEST_ROOT . '/scripts/audit-ai-plugin-localization.php';

ob_start();
$status = npcink_cloud_addon_ai_i18n_audit_main(
	array( 'audit-ai-plugin-localization.php', '--path=' . $fixture_root ),
	MACA_TEST_ROOT
);
$report = (string) ob_get_clean();

// Detail section headers start at a line beginning; the summary list at the
// top of the report prefixes the same names with "- ".
$fixed_ui_offset = strpos( $report, "\nfixed_ui_candidates:" );
$ability_offset  = strpos( $report, "\nability_error_notices:" );
$schema_offset   = strpos( $report, "\nschema_or_json_fields:" );
$prompt_offset   = strpos( $report, "\nlong_prompt_copy:" );
$label_offset    = strpos( $report, '"Content Wizard"' );
$prose_offset    = strpos( $report, '"Content of the demo object."' );
$stale_offset      = strpos( $report, "\nstale_review:" );
$domainless_offset = strpos( $report, "\ndomainless_rescue:" );
$literal_offset    = strpos( $report, "\nliteral_only_rescue:" );
$notes_offset      = strpos( $report, "\nReview notes:" );
$stale_region      = substr( $report, (int) $stale_offset, (int) $domainless_offset - (int) $stale_offset );
$domainless_region = substr( $report, (int) $domainless_offset, (int) $literal_offset - (int) $domainless_offset );
$literal_region    = substr( $report, (int) $literal_offset, (int) $notes_offset - (int) $literal_offset );

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
	&& false !== $fixed_ui_offset
	&& false !== $ability_offset
	&& false !== $schema_offset
	&& false !== $prompt_offset
	&& false !== $label_offset
	&& $label_offset > $fixed_ui_offset
	&& $label_offset < $ability_offset
	&& false !== $prose_offset
	&& $prose_offset > $schema_offset
	&& $prose_offset < $prompt_offset
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

maca_assert(
	0 === $status
		&& false !== $stale_offset
		&& false !== $domainless_offset
		&& false !== $literal_offset
		&& false !== $notes_offset
		&& $stale_offset < $domainless_offset
		&& $domainless_offset < $literal_offset
		&& $literal_offset < $notes_offset
		&& false !== strpos( $report, 'Domain-less bundle rescue:' )
		&& false !== strpos( $report, 'Build literal rescue:' )
	// Domain-less bundled calls rescue their shim strings from stale_review.
	&& false !== strpos( $domainless_region, '"%d Item selected"' )
	&& false !== strpos( $domainless_region, '"%d Items selected"' )
	&& false !== strpos( $domainless_region, '"Edit %s (has errors)"' )
	&& false !== strpos( $domainless_region, '"Loading suggestions"' )
	&& false !== strpos( $domainless_region, 'files: build-scripts/admin/page.js' )
	&& false === strpos( $stale_region, '"%d Item selected"' )
	&& false === strpos( $stale_region, '"%d Items selected"' )
	&& false === strpos( $stale_region, '"Edit %s (has errors)"' )
	&& false === strpos( $stale_region, '"Loading suggestions"' )
	// A domain-less call in a chunk without any 'ai' substring is still collected.
	&& false !== strpos( $domainless_region, '"User"' )
	&& false === strpos( $stale_region, '"User"' )
	// Bare quoted literals inside build/ output rescue their shim strings separately.
	&& false !== strpos( $literal_region, '"Comfortable"' )
	&& false !== strpos( $literal_region, 'files: build/routes/content.min.js' )
	&& false === strpos( $stale_region, '"Comfortable"' )
	// A shim string whose only occurrence carries a foreign domain stays stale.
	&& false !== strpos( $stale_region, '"High"' )
	&& false === strpos( $domainless_region, '"High"' )
	// A shim string absent from the fixture entirely is still reported stale.
	&& false !== strpos( $stale_region, '"AI Status"' ),
	'AI plugin localization audit rescues shim strings that only survive as bundled domain-less calls or bare build literals instead of reporting them stale.'
);

npcink_cloud_addon_ai_i18n_audit_test_rm_dir( $fixture_root );
