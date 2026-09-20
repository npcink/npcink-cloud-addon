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

/**
 * Exercises the real WordPress SQL reconciliation page without Cloud traffic.
 *
 * @return WP_REST_Response|WP_Error
 */
function npcink_cloud_addon_playground_site_knowledge_response() {
	global $wpdb;
	$fixture_ids = array();
	$modified_gmt = '2026-08-25 00:00:00';
	for ( $index = 0; $index < 51; $index++ ) {
		$inserted = $wpdb->insert(
			$wpdb->posts,
			array(
				'post_author' => 1,
				'post_date' => $modified_gmt,
				'post_date_gmt' => $modified_gmt,
				'post_content' => 'Playground Site Knowledge fixture',
				'post_title' => 'Playground fixture ' . $index,
				'post_status' => 'publish',
				'post_name' => 'npcink-playground-fixture-' . $index,
				'post_modified' => $modified_gmt,
				'post_modified_gmt' => $modified_gmt,
				'post_type' => 'post',
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( false !== $inserted ) {
			$fixture_ids[] = (int) $wpdb->insert_id;
		}
	}

	$result = array( 'first_page' => 0, 'second_page' => 0, 'same_second_cursor' => false );
	try {
		$bridge = new ReflectionClass( 'Npcink_Cloud_Site_Knowledge_Change_Bridge' );
		$query = $bridge->getMethod( 'query_reconciliation_posts' );
		$query->setAccessible( true );
		$first_page = $query->invoke( null, array( 'modified_gmt' => $modified_gmt, 'post_id' => 0 ) );
		$last = end( $first_page );
		$cursor = array(
			'modified_gmt' => (string) ( $last->post_modified_gmt ?? '' ),
			'post_id' => (int) ( $last->ID ?? 0 ),
		);
		$second_page = $query->invoke( null, $cursor );
		$first_fixture_ids = array_intersect( $fixture_ids, array_map( static function ( $post ) { return (int) ( $post->ID ?? 0 ); }, $first_page ) );
		$second_fixture_ids = array_intersect( $fixture_ids, array_map( static function ( $post ) { return (int) ( $post->ID ?? 0 ); }, $second_page ) );
		$result = array(
			'first_page' => count( $first_page ),
			'second_page' => count( $second_page ),
			'first_fixture_page' => count( $first_fixture_ids ),
			'second_fixture_page' => count( $second_fixture_ids ),
			'same_second_cursor' => $cursor['modified_gmt'] === $modified_gmt && $cursor['post_id'] > 0,
		);
	} finally {
		foreach ( $fixture_ids as $fixture_id ) {
			$wpdb->delete( $wpdb->posts, array( 'ID' => $fixture_id ), array( '%d' ) );
		}
	}

	if ( 50 !== $result['first_fixture_page'] || 1 !== $result['second_fixture_page'] || ! $result['same_second_cursor'] ) {
		return new WP_Error( 'npcink_cloud_addon_playground_site_knowledge_failed', 'The real WordPress reconciliation page did not preserve the ordered cursor.', array( 'status' => 500, 'data' => $result ) );
	}

	return rest_ensure_response( array( 'site_knowledge_reconciliation' => true ) );
}
