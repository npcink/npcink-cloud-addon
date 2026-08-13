<?php
/**
 * Internal runtime client construction.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Runtime_Client_Factory' ) ) {
	/**
	 * Creates configured transport clients for addon-owned facade functions.
	 *
	 * This class is an implementation detail and is not a public integration
	 * seam. External consumers must use the scenario-specific facade functions.
	 */
	final class Npcink_Cloud_Runtime_Client_Factory {
		/**
		 * Returns a configured runtime client, or null when credentials are incomplete.
		 *
		 * @return Npcink_Cloud_Runtime_Client|null
		 */
		public static function configured(): ?Npcink_Cloud_Runtime_Client {
			if ( ! Npcink_Cloud_Addon_Settings::is_configured() ) {
				return null;
			}

			return new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
		}
	}
}
