<?php
/**
 * Npcink Cloud availability probe for the WordPress AI PHP client.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if (
	interface_exists( 'WordPress\\AiClient\\Providers\\Contracts\\ProviderAvailabilityInterface' )
	&& ! class_exists( 'Npcink_Cloud_WordPress_AI_Availability' )
) {
	/**
	 * Availability is driven by Save and Verify plus local connector exposure consent.
	 */
	final class Npcink_Cloud_WordPress_AI_Availability implements \WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface {
		/**
		 * Checks whether verified Cloud settings may be exposed to WordPress AI.
		 *
		 * @return bool
		 */
		public function isConfigured(): bool {
			return class_exists( 'Npcink_Cloud_Addon_Settings' )
				&& Npcink_Cloud_Addon_Settings::is_wordpress_ai_connector_enabled();
		}
	}
}
