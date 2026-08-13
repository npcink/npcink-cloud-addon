<?php
/**
 * Addon-owned local lifecycle cleanup.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Addon_Cleanup' ) ) {
	/**
	 * Removes only local state owned by the Cloud Addon connector.
	 */
	final class Npcink_Cloud_Addon_Cleanup {
		/**
		 * Deletes settings, credential-scoped caches, buffers, and cron hooks.
		 *
		 * Settings must be read before this method so dynamic keys remain known.
		 *
		 * @param array<string,mixed> $settings Decrypted settings snapshot.
		 * @return void
		 */
		public static function delete_all( array $settings ): void {
			$option_name = defined( 'NPCINK_CLOUD_ADDON_OPTION_NAME' ) && '' !== (string) NPCINK_CLOUD_ADDON_OPTION_NAME
				? (string) NPCINK_CLOUD_ADDON_OPTION_NAME
				: 'npcink_cloud_addon_settings';
			$credential_seed = implode(
				'|',
				array(
					(string) ( $settings['base_url'] ?? '' ),
					(string) ( $settings['site_id'] ?? '' ),
					(string) ( $settings['key_id'] ?? '' ),
				)
			);
			$entitlement_key = 'npcink_cloud_entitlement_' . md5( $credential_seed );
			$site_knowledge_status_key = 'npcink_cloud_site_knowledge_status_' . md5( $credential_seed );

			delete_transient( $entitlement_key );
			delete_transient( $entitlement_key . '_refresh_failure' );
			delete_option( $entitlement_key . '_refresh_lock' );
			delete_transient( $site_knowledge_status_key );
			delete_option( $site_knowledge_status_key . '_lock' );

			foreach (
				array(
					$option_name,
					'npcink_cloud_addon_wp_ai_connector_connected',
					'npcink_cloud_addon_observability_buffer',
					'npcink_cloud_addon_observability_status',
					'npcink_cloud_addon_observability_summary',
					'npcink_cloud_addon_agent_feedback_summary',
					'npcink_cloud_addon_editor_assist_pending',
					'npcink_cloud_addon_site_knowledge_change_buffer',
					'npcink_cloud_addon_site_knowledge_change_status',
					'npcink_cloud_addon_site_knowledge_maintenance_cursor',
				) as $owned_option
			) {
				delete_option( $owned_option );
			}

			foreach (
				array(
					'npcink_cloud_addon_flush_observability',
					'npcink_cloud_addon_flush_site_knowledge_changes',
					'npcink_cloud_addon_reconcile_site_knowledge_changes',
				) as $cron_hook
			) {
				wp_clear_scheduled_hook( $cron_hook );
			}
		}
	}
}
