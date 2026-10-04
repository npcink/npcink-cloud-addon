<?php
/**
 * Optional per-developer local environment defaults.
 *
 * Reads KEY=VALUE lines from scripts/.local-env (gitignored) so repository
 * tooling stays free of machine-specific paths. Real environment variables
 * always win over values in that file.
 *
 * @package NpcinkCloudAddon
 */

declare( strict_types=1 );

if ( ! defined( 'NPCINK_CLOUD_ADDON_LOCAL_ENV' ) ) {
	define( 'NPCINK_CLOUD_ADDON_LOCAL_ENV', true );

	/**
	 * Parses scripts/.local-env into a value map.
	 *
	 * Supports # comments, bare values, and single- or double-quoted values.
	 * Returns an empty array when the file is absent or unreadable.
	 *
	 * @return array<string,string>
	 */
	function npcink_cloud_addon_local_env(): array {
		static $values = null;
		if ( is_array( $values ) ) {
			return $values;
		}

		$values = array();
		$file = __DIR__ . '/.local-env';
		if ( ! is_file( $file ) || ! is_readable( $file ) ) {
			return $values;
		}

		$lines = file( $file, FILE_IGNORE_NEW_LINES );
		foreach ( is_array( $lines ) ? $lines : array() as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || '#' === $line[0] ) {
				continue;
			}
			$position = strpos( $line, '=' );
			if ( false === $position ) {
				continue;
			}
			$key = trim( substr( $line, 0, $position ) );
			$value = trim( substr( $line, $position + 1 ) );
			$length = strlen( $value );
			if ( $length >= 2 && $value[0] === $value[ $length - 1 ] && ( '"' === $value[0] || "'" === $value[0] ) ) {
				$value = substr( $value, 1, -1 );
			}
			if ( '' !== $key && 1 === preg_match( '/^[A-Za-z_][A-Za-z0-9_]*$/', $key ) ) {
				$values[ $key ] = $value;
			}
		}

		return $values;
	}

	/**
	 * Resolves one variable: real environment first, then scripts/.local-env.
	 *
	 * @param string $key Variable name.
	 * @return string Resolved value, or '' when neither source defines it.
	 */
	function npcink_cloud_addon_local_env_value( string $key ): string {
		$from_environment = getenv( $key );
		if ( is_string( $from_environment ) && '' !== $from_environment ) {
			return $from_environment;
		}

		$values = npcink_cloud_addon_local_env();
		return isset( $values[ $key ] ) ? $values[ $key ] : '';
	}
}
