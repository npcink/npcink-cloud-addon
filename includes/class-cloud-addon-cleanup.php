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
		 * Removes retired media continuation state only when no plan is active.
		 */
		public static function delete_inactive_legacy_media_continuation(): void {
			$cleanup_marker = 'npcink_cloud_addon_media_continuation_cleanup_0_2_0';
			if ( get_option( $cleanup_marker, false ) ) {
				return;
			}

			$legacy_plan = get_option( 'npcink_cloud_addon_media_recognition_plan', array() );
			$legacy_state = is_array( $legacy_plan ) ? sanitize_key( (string) ( $legacy_plan['state'] ?? '' ) ) : '';
			$legacy_active = is_array( $legacy_plan ) && ! empty( $legacy_plan['active'] );
			if ( $legacy_active || in_array( $legacy_state, array( 'active', 'processing', 'retrying', 'queued' ), true ) ) {
				return;
			}

			delete_option( 'npcink_cloud_addon_media_recognition_plan' );
			delete_option( 'npcink_cloud_addon_media_recognition_plan_lock' );
			delete_transient( 'npcink_cloud_addon_media_index_status' );
			wp_clear_scheduled_hook( 'npcink_cloud_addon_continue_media_recognition' );
			update_option( $cleanup_marker, 'complete', false );
		}

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
			delete_transient( $site_knowledge_status_key . '_media_evidence_ids' );
			delete_option( $site_knowledge_status_key . '_lock' );

			foreach (
				array(
					$option_name,
					'npcink_cloud_addon_wp_ai_connector_connected',
					'npcink_cloud_addon_observability_buffer',
					'npcink_cloud_addon_observability_status',
					'npcink_cloud_addon_observability_summary',
					'npcink_cloud_addon_agent_feedback_summary',
					'npcink_cloud_addon_customer_journey_buffer',
					'npcink_cloud_addon_editor_assist_pending',
					'npcink_cloud_addon_site_knowledge_change_buffer',
					'npcink_cloud_addon_site_knowledge_change_status',
					'npcink_cloud_addon_site_knowledge_maintenance_cursor',
					'npcink_cloud_addon_media_recognition_plan',
					'npcink_cloud_addon_media_recognition_plan_lock',
					'npcink_cloud_addon_media_continuation_cleanup_0_2_0',
					'npcink_cloud_addon_runtime_callback_registration',
				) as $owned_option
			) {
				delete_option( $owned_option );
			}

			foreach (
				array(
					'npcink_cloud_addon_flush_observability',
					'npcink_cloud_addon_flush_site_knowledge_changes',
					'npcink_cloud_addon_reconcile_site_knowledge_changes',
					'npcink_cloud_addon_continue_media_recognition',
				) as $cron_hook
			) {
				wp_clear_scheduled_hook( $cron_hook );
			}
		}
	}
}
