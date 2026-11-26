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

require_once __DIR__ . '/npcink-cloud-addon-playground-ai-context.php';
require_once __DIR__ . '/fixtures/site-knowledge-reconciliation.php';

add_action(
	'rest_api_init',
	static function (): void {
		foreach ( array( 'read', 'failure' ) as $kind ) {
			register_rest_route( 'npcink-cloud-addon-playground/v1', '/context-' . $kind, array(
				'methods' => 'GET', 'permission_callback' => '__return_true',
				'callback' => static function () use ( $kind ) {
					if ( 'read' === $kind ) {
						return rest_ensure_response( Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context() );
					}
					$failed = npcink_playground_context_ability( 'fixture/failure', static function () { return new WP_Error( 'fixture_failure', 'Controlled callback failure.' ); } );
					return $failed->execute( array() );
				},
			) );
		}
		register_rest_route(
			'npcink-cloud-addon-playground/v1',
			'/smoke',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
			'callback'            => 'npcink_cloud_addon_playground_smoke_response',
			)
		);
		register_rest_route(
			'npcink-cloud-addon-playground/v1',
			'/compatibility',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'permission_callback' => '__return_true',
				'callback'            => 'npcink_cloud_addon_playground_compatibility_response',
			)
		);
	}
);

/**
 * Returns bounded compatibility evidence for the disposable Playground lane.
 *
 * @return WP_REST_Response|WP_Error
 */
function npcink_cloud_addon_playground_compatibility_response() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	$ai_plugin_file = WP_PLUGIN_DIR . '/ai/ai.php';
	$ai_plugin_data = file_exists( $ai_plugin_file )
		? get_file_data( $ai_plugin_file, array( 'Version' => 'Version' ) )
		: array();
	$active_plugins = (array) get_option( 'active_plugins', array() );
	$active_plugin_names = array_values(
		array_filter(
			array_map(
				'sanitize_text_field',
				$active_plugins
			)
		)
	);
	$addon_active = is_plugin_active( 'npcink-cloud-addon/npcink-cloud-addon.php' );
	$ai_active = is_plugin_active( 'ai/ai.php' );
	$abilities_api_present = function_exists( 'wp_register_ability' )
		&& function_exists( 'wp_get_abilities' );
	$connector_runtime_present = function_exists( 'npcink_cloud_addon_execute_wordpress_ai_connector_runtime' );
	if ( ! $ai_active || ! $addon_active ) {
		return new WP_Error(
			'npcink_cloud_addon_playground_compatibility_pending',
			'Compatibility plugins are not active yet.',
			array( 'status' => 503 )
		);
	}

	return rest_ensure_response(
		array(
			'wordpress_version'          => (string) get_bloginfo( 'version' ),
			'php_version'                => PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION,
			'wordpress_ai_version'       => (string) ( $ai_plugin_data['Version'] ?? '' ),
			'wordpress_ai_active'        => $ai_active,
			'addon_active'               => $addon_active,
			'abilities_api_present'      => $abilities_api_present,
			'connector_runtime_present'  => $connector_runtime_present,
			'context_execution_checks'   => npcink_playground_verify_ai_context(),
			'active_plugin_names'        => $active_plugin_names,
		)
	);
}

/**
 * Returns bounded activation and fail-closed evidence for the disposable site.
 *
 * @return WP_REST_Response|WP_Error
 */
function npcink_cloud_addon_playground_smoke_response() {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
	if ( 'site_knowledge' === sanitize_key( (string) ( $_GET['mode'] ?? '' ) ) ) {
		return npcink_cloud_addon_playground_site_knowledge_response();
	}

	$active = is_plugin_active( 'npcink-cloud-addon/npcink-cloud-addon.php' );
	$public_api_present = function_exists( 'npcink_cloud_addon_is_configured' )
		&& function_exists( 'npcink_cloud_addon_get_connection_state' )
		&& function_exists( 'npcink_cloud_addon_pull_media_artifact' )
		&& function_exists( 'npcink_cloud_addon_acknowledge_media_artifact_delivery' );
	$concrete_client_seam_removed = ! function_exists( 'npcink_cloud_addon_runtime_client' );
	$connection_state = $public_api_present ? npcink_cloud_addon_get_connection_state() : array();
	$connection_state_safe = is_array( $connection_state )
		&& ! array_key_exists( 'secret', $connection_state )
		&& ! array_key_exists( 'site_id', $connection_state )
		&& ! array_key_exists( 'key_id', $connection_state );
	$configured = $public_api_present && npcink_cloud_addon_is_configured();
	$verified_runtime_client_available = false;
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

	// Run only on the disposable, initially disconnected smoke site.
	$generation = Npcink_Cloud_Authorization_State::begin();
	$connected = '' !== $generation && Npcink_Cloud_Addon_Settings::write_settings(
		array( 'base_url' => 'https://connection.example.test/', 'site_id' => 'fixture_site', 'key_id' => 'fixture_key', 'secret' => 'fixture_only' ),
		$generation
	);
	$settings = Npcink_Cloud_Addon_Settings::get_settings();
	Npcink_Cloud_Addon_Cleanup::delete_all( $settings );
	if ( ! $connected || 'fixture_site' !== $settings['site_id'] || Npcink_Cloud_Authorization_State::is_current( $generation ) ) {
		return new WP_Error( 'npcink_playground_connection_commit_failed', 'Playground did not commit and clear the fixture connection.', array( 'status' => 500 ) );
	}

	return rest_ensure_response(
		array(
			'plugin_active'                     => true,
			'public_api_present'                => true,
			'concrete_client_seam_removed'      => true,
			'connection_state_safe'             => true,
			'connection_commit_safe'            => true,
			'configured'                        => false,
			'verified_runtime_client_available' => false,
			'connector_marker_present'          => false,
			'write_posture'                     => 'connector_only_no_direct_wordpress_write',
		)
	);
}

/**
 * Exercises the real WordPress SQL reconciliation page without Cloud traffic.
 *
 * @return WP_REST_Response|WP_Error
 */
function npcink_cloud_addon_playground_site_knowledge_response() {
	$result = npcink_cloud_addon_reconciliation_fixture();
	return is_wp_error( $result ) ? $result : rest_ensure_response( array( 'site_knowledge_reconciliation' => true ) );
}
