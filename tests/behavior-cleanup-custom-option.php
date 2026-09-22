<?php
/**
 * Isolated custom settings option cleanup regression test.
 *
 * Must run as its own PHP process: it pins NPCINK_CLOUD_ADDON_OPTION_NAME to
 * a custom value, while shared-process tests already hold the default
 * constant because behavior-performance-guards.php loads the plugin file.
 * tests/run.php invokes this file in a subprocess; the guard below fails
 * loudly instead of silently exercising the default option name if this file
 * is ever included into a process that already defines the constant.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( defined( 'NPCINK_CLOUD_ADDON_OPTION_NAME' ) ) {
	fwrite( STDERR, '[fail] NPCINK_CLOUD_ADDON_OPTION_NAME is already defined; run this test in an isolated PHP process (tests/run.php does).' . "\n" );
	exit( 1 );
}

define( 'NPCINK_CLOUD_ADDON_OPTION_NAME', 'npcink_cloud_addon_custom_settings' );
require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();
maca_reset_test_state();
maca_seed_settings( true );
$settings = Npcink_Cloud_Addon_Settings::get_settings();
Npcink_Cloud_Addon_Cleanup::delete_all( $settings );

maca_assert(
	! array_key_exists( 'npcink_cloud_addon_custom_settings', $GLOBALS['maca_options'] )
	&& ! array_key_exists( 'npcink_cloud_addon_settings', $GLOBALS['maca_options'] ),
	'Behavior: unified cleanup honors the configured custom settings option name.'
);
