<?php
/**
 * Uninstall cleanup for Npcink Cloud Addon.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

require_once __DIR__ . '/includes/class-cloud-credential-store.php';
require_once __DIR__ . '/includes/class-cloud-addon-settings.php';
require_once __DIR__ . '/includes/class-cloud-addon-cleanup.php';

$npcink_cloud_addon_uninstall_settings = Npcink_Cloud_Addon_Settings::get_settings();
Npcink_Cloud_Addon_Cleanup::delete_all( $npcink_cloud_addon_uninstall_settings );
