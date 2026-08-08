<?php
/**
 * Plugin Name:       Npcink Cloud Addon
 * Description:       Cloud connector for Npcink hosted runtime access, signing, health checks, and entitlement summaries.
 * Version:           0.1.4
 * Requires at least: 7.0
 * Requires PHP:      8.0
 * Author:            Npcink
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       npcink-cloud-addon
 * Domain Path:       /languages
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! defined( 'NPCINK_CLOUD_ADDON_FILE' ) ) {
	define( 'NPCINK_CLOUD_ADDON_FILE', __FILE__ );
}

if ( ! defined( 'NPCINK_CLOUD_ADDON_DIR' ) ) {
	define( 'NPCINK_CLOUD_ADDON_DIR', __DIR__ );
}

if ( ! defined( 'NPCINK_CLOUD_ADDON_VERSION' ) ) {
	define( 'NPCINK_CLOUD_ADDON_VERSION', '0.1.4' );
}

if ( ! defined( 'NPCINK_CLOUD_ADDON_OPTION_NAME' ) ) {
	define( 'NPCINK_CLOUD_ADDON_OPTION_NAME', 'npcink_cloud_addon_settings' );
}

require_once __DIR__ . '/includes/bootstrap.php';
