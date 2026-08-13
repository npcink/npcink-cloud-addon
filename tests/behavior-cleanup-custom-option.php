<?php
/**
 * Isolated custom settings option cleanup regression test.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

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
