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
	private const GENERATION_FIELD = '_auth_generation';

	public static function issue( string $base_url, string $flow ): string {
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return '';
		}

		$generation = self::begin();
		if ( '' === $generation ) {
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
		$generation = $value['generation'] ?? null;
		if ( ! is_string( $generation ) || ! self::is_current( $generation ) ) {
			return array();
		}

		return delete_transient( $name ) ? $value : array();
	}

	public static function is_current( string $generation ): bool {
		return '' !== $generation && $generation === ( self::stored_settings()[ self::GENERATION_FIELD ] ?? null );
	}

	/** Starts a new connection attempt without modifying the existing credentials. */
	public static function begin(): string {
		$before = self::stored_settings();
		$generation = wp_generate_password( 32, false, false );
		$after = $before ?? array();
		$after[ self::GENERATION_FIELD ] = $generation;
		return self::replace( $before, $after ) ? $generation : '';
	}

	/**
	 * Saves an encrypted settings payload only while this attempt owns the row.
	 *
	 * @param string              $generation Connection attempt identifier.
	 * @param array<string,mixed> $stored Encrypted settings payload.
	 */
	public static function commit( string $generation, array $stored ): bool {
		$before = self::stored_settings();
		if ( '' === $generation || null === $before || $generation !== ( $before[ self::GENERATION_FIELD ] ?? null ) ) {
			return false;
		}
		$stored[ self::GENERATION_FIELD ] = $generation;
		return self::replace( $before, $stored );
	}

	/** Applies a settings-only edit to an existing row, never recreating it. */
	public static function update_existing( callable $change ): bool {
		$before = self::stored_settings();
		if ( null === $before ) {
			return false;
		}
		$after = $change( $before );
		if ( ! is_array( $after ) ) {
			return false;
		}
		if ( $after !== $before ) {
			unset( $after[ self::GENERATION_FIELD ] );
		}
		return self::replace( $before, $after );
	}

	/**
	 * Restores a disconnected endpoint only if no newer attempt has created a row.
	 *
	 * @param array<string,mixed> $stored Credential-free settings payload.
	 */
	public static function restore_endpoint( array $stored ): void {
		self::replace( null, $stored );
	}

	/** @return array<string,mixed>|null */
	private static function stored_settings(): ?array {
		global $wpdb;
		$name = Npcink_Cloud_Addon_Settings::option_name();
		if ( $wpdb instanceof wpdb ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Authorization cannot trust a request-local option snapshot.
			$stored = maybe_unserialize( $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name = %s', $wpdb->options, $name ) ) );
		} else {
			$stored = get_option( $name, false );
		}
		return is_array( $stored ) ? $stored : null;
	}

	/**
	 * Tests the exact settings version in the same statement that writes it.
	 *
	 * @param array<string,mixed>|null $before Expected row, or null for insert-only.
	 * @param array<string,mixed>      $after Replacement row.
	 */
	private static function replace( ?array $before, array $after ): bool {
		global $wpdb;
		$name = Npcink_Cloud_Addon_Settings::option_name();
		if ( $before === $after ) {
			return true;
		}
		if ( $wpdb instanceof wpdb ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery -- The Options API cannot perform these conditional writes.
			$changed = null === $before
				? 1 === $wpdb->query( $wpdb->prepare( 'INSERT IGNORE INTO %i (option_name, option_value, autoload) VALUES (%s, %s, %s)', $wpdb->options, $name, serialize( $after ), 'off' ) )
				: 1 === $wpdb->query( $wpdb->prepare( 'UPDATE %i SET option_value = %s WHERE option_name = %s AND HEX(option_value) = HEX(%s)', $wpdb->options, serialize( $after ), $name, serialize( $before ) ) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery
			wp_cache_delete( $name, 'options' );
			wp_cache_delete( 'alloptions', 'options' );
			wp_cache_delete( 'notoptions', 'options' );
		} else {
			// The standalone behavior harness uses in-memory options, not wpdb.
			$changed = self::stored_settings() === $before && update_option( $name, $after, false );
		}
		Npcink_Cloud_Addon_Settings::invalidate_settings_cache();
		return $changed;
	}

	private static function transient_name( string $state ): string {
		return 'npcink_cloud_auth_' . hash( 'sha256', $state );
	}
}
