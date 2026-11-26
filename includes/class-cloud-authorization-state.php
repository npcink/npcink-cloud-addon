<?php
/**
 * Short-lived WordPress state for the Cloud Portal connection callback.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Npcink_Cloud_Authorization_State {
	public const TTL_SECONDS = 600;
	private const GENERATION_OPTION = 'npcink_cloud_addon_auth_generation';

	public static function invalidate_pending(): void {
		// Do not enumerate transient keys: they can live in an external object cache.
		delete_option( self::GENERATION_OPTION );
	}

	public static function issue( string $base_url, string $flow ): string {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return '';
		}

		$generation = get_option( self::GENERATION_OPTION );
		if ( false === $generation ) {
			$generation = wp_generate_password( 32, false, false );
			if ( ! add_option( self::GENERATION_OPTION, $generation, '', false ) ) {
				$generation = get_option( self::GENERATION_OPTION );
			}
		}
		if ( ! is_string( $generation ) || '' === $generation ) {
			return '';
		}

		$state = wp_generate_password( 32, false, false );
		$stored = set_transient(
			self::transient_name( $state ),
			array(
				'base_url'   => $base_url,
				'flow'       => $flow,
				'user_id'    => $user_id,
				'generation' => $generation,
			),
			self::TTL_SECONDS
		);

		return $stored ? $state : '';
	}

	/** @return array<string,mixed> */
	public static function consume( string $state ): array {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 || 1 !== preg_match( '/^[A-Za-z0-9]{32}$/D', $state ) ) {
			return array();
		}

		$name = self::transient_name( $state );
		$value = get_transient( $name );
		if ( ! is_array( $value ) || $user_id !== ( $value['user_id'] ?? null ) ) {
			return array();
		}
		$generation = get_option( self::GENERATION_OPTION );
		if ( ! is_string( $generation ) || '' === $generation || $generation !== ( $value['generation'] ?? null ) ) {
			return array();
		}

		return delete_transient( $name ) ? $value : array();
	}

	private static function transient_name( string $state ): string {
		return 'npcink_cloud_auth_' . hash( 'sha256', $state );
	}
}
