<?php
/**
 * Ephemeral Playground-only assertions for the Cloud Addon.
 *
 * This file is mounted as an MU plugin by scripts/smoke-playground.sh. It is
 * never part of the release package and exposes only non-secret booleans from
 * a disposable local WordPress instance.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

add_action(
	'rest_api_init',
	static function (): void {
		register_rest_route(
			'npcink-cloud-addon-playground/v1',
			'/smoke',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => 'npcink_cloud_addon_playground_smoke_response',
			)
		);
	}
);

/**
 * Returns bounded activation and fail-closed evidence for the disposable site.
 *
 * @return WP_REST_Response|WP_Error
 */
function npcink_cloud_addon_playground_smoke_response() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';

	$active = is_plugin_active( 'npcink-cloud-addon/npcink-cloud-addon.php' );
	$public_api_present = function_exists( 'npcink_cloud_addon_is_configured' )
		&& function_exists( 'npcink_cloud_addon_get_connection_state' )
		&& function_exists( 'npcink_cloud_addon_verified_runtime_client' );
	$concrete_client_seam_removed = ! function_exists( 'npcink_cloud_addon_runtime_client' );
	$connection_state = $public_api_present ? npcink_cloud_addon_get_connection_state() : array();
	$connection_state_safe = is_array( $connection_state )
		&& ! array_key_exists( 'secret', $connection_state )
		&& ! array_key_exists( 'site_id', $connection_state )
		&& ! array_key_exists( 'key_id', $connection_state );
	$configured = $public_api_present && npcink_cloud_addon_is_configured();
	$verified_runtime_client_available = $public_api_present && null !== npcink_cloud_addon_verified_runtime_client();
	$connector_marker_present = '' !== (string) get_option( 'npcink_cloud_addon_wp_ai_connector_connected', '' );

	if ( ! $active || ! $public_api_present || ! $concrete_client_seam_removed || ! $connection_state_safe || $configured || $verified_runtime_client_available || $connector_marker_present ) {
		return new WP_Error(
			'npcink_cloud_addon_playground_smoke_failed',
			'Npcink Cloud Addon did not preserve its expected default fail-closed state.',
			array(
				'status' => 500,
				'data'   => array(
					'plugin_active'                     => $active,
					'public_api_present'                => $public_api_present,
					'concrete_client_seam_removed'      => $concrete_client_seam_removed,
					'connection_state_safe'             => $connection_state_safe,
					'configured'                        => $configured,
					'verified_runtime_client_available' => $verified_runtime_client_available,
					'connector_marker_present'          => $connector_marker_present,
				),
			)
		);
	}

	return rest_ensure_response(
		array(
			'plugin_active'                     => true,
			'public_api_present'                => true,
			'concrete_client_seam_removed'      => true,
			'connection_state_safe'             => true,
			'configured'                        => false,
			'verified_runtime_client_available' => false,
			'connector_marker_present'          => false,
			'write_posture'                     => 'connector_only_no_direct_wordpress_write',
		)
	);
}
