<?php
/**
 * Runtime endpoint allowlist policy.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Runtime_Endpoint_Policy' ) ) {
	/**
	 * Validates paths before the Runtime Client signs and sends them.
	 */
	final class Npcink_Cloud_Runtime_Endpoint_Policy {
		/**
		 * Returns whether a method and relative path are in the runtime contract.
		 *
		 * @param string $method HTTP method.
		 * @param string $path Relative path with optional query.
		 * @return bool
		 */
		public static function allows( string $method, string $path ): bool {
			$method = strtoupper( trim( $method ) );

			if ( '' === $path || $path !== trim( $path ) || '/' !== $path[0] ) {
				return false;
			}
			if ( false !== strpos( $path, '#' ) ) {
				return false;
			}

			$path_only = self::path_only( $path );
			if ( false !== strpos( $path_only, '//' ) || 1 === preg_match( '#%(?:25)*(?:2f|5c)#i', $path_only ) ) {
				return false;
			}
			foreach ( explode( '/', $path_only ) as $segment ) {
				$decoded_segment = rawurldecode( $segment );
				if ( '.' === $decoded_segment || '..' === $decoded_segment ) {
					return false;
				}
			}

			$exact_paths = array(
				'POST' => array(
					'/v1/runtime/execute',
					'/v1/runtime/media/uploads',
					'/v1/runtime/media/jobs',
					'/v1/customer-journey/events',
					'/v1/observability/plugin-events',
					'/v1/agent-feedback/events',
				),
				'GET'  => array(
					'/v1/entitlements/current',
					'/v1/runs/nightly-inspection/recent',
					'/v1/customer-journey/summary',
					'/v1/agent-feedback/summary',
					'/v1/observability/plugin-summary',
				),
			);
			if ( isset( $exact_paths[ $method ] ) && in_array( $path_only, $exact_paths[ $method ], true ) ) {
				return true;
			}

			if ( 'GET' === $method && 1 === preg_match( '#^/v1/runs/[A-Za-z0-9._:-]+(?:/result)?$#', $path_only ) ) {
				return true;
			}
			if ( 'POST' === $method && 1 === preg_match( '#^/v1/runs/[A-Za-z0-9._:-]+/retry$#', $path_only ) ) {
				return true;
			}
			if ( 'GET' === $method && $path === $path_only && 1 === preg_match( '#^/v1/runtime/media/artifacts/art_[0-9a-f]{32}/download$#', $path_only ) ) {
				return true;
			}
			if ( 'POST' === $method && $path === $path_only && 1 === preg_match( '#^/v1/runtime/media/artifacts/art_[0-9a-f]{32}/delivery-ack$#', $path_only ) ) {
				return true;
			}
			return false;
		}

		/**
		 * Returns the path portion without interpreting the input as an absolute URL.
		 *
		 * @param string $path Relative path with optional query.
		 * @return string
		 */
		private static function path_only( string $path ): string {
			$query_position = strpos( $path, '?' );

			return false === $query_position ? $path : substr( $path, 0, $query_position );
		}
	}
}
