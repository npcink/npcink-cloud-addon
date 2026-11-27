<?php
/**
 * Internal runtime client construction.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'npcink_cloud_addon_execute_toolbox_code_preview_runtime' ) ) {
	/**
	 * Signs one deterministic, transient Toolbox Code block preview request.
	 *
	 * @param array<string,mixed> $input Exact code, language and source SHA-256.
	 * @return array<string,mixed>|WP_Error
	 */
	function npcink_cloud_addon_execute_toolbox_code_preview_runtime( array $input ) {
		$keys = array_keys( $input );
		sort( $keys );
		$code = $input['code'] ?? null;
		if ( array( 'code', 'language', 'source_sha256' ) !== $keys || ! is_string( $code ) || '' === trim( $code )
			|| strlen( $code ) > 16384 || false !== strpos( $code, "\0" ) || 1 !== preg_match( '//u', $code )
			|| ! in_array( $input['language'], array( 'javascript', 'css', 'json' ), true )
			|| hash( 'sha256', $code ) !== $input['source_sha256'] ) {
			return new WP_Error( 'cloud_code_preview_invalid', __( 'Invalid content formatting request.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
		}
		$client = Npcink_Cloud_Runtime_Client_Factory::configured();
		if ( ! $client ) {
			return new WP_Error( 'cloud_runtime_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ), array( 'status' => 400 ) );
		}
		return $client->execute_runtime(
			array(
				'ability_name' => 'npcink-toolbox/format-code-block',
				'ability_family' => 'text',
				'contract_version' => 'code_format_request.v1',
				'execution_kind' => 'content_format',
				'profile_id' => 'content-format.managed',
				'channel' => 'editor',
				'execution_pattern' => 'inline',
				'storage_mode' => 'no_store',
				'data_classification' => 'pii',
				'timeout_seconds' => 10,
				'input' => $input,
			)
		);
	}
}

if ( ! class_exists( 'Npcink_Cloud_Runtime_Client_Factory' ) ) {
	/**
	 * Creates configured transport clients for addon-owned facade functions.
	 *
	 * This class is an implementation detail and is not a public integration
	 * seam. External consumers must use the scenario-specific facade functions.
	 */
	final class Npcink_Cloud_Runtime_Client_Factory {
		/**
		 * Returns a configured runtime client, or null when credentials are incomplete.
		 *
		 * @return Npcink_Cloud_Runtime_Client|null
		 */
		public static function configured(): ?Npcink_Cloud_Runtime_Client {
			if ( ! Npcink_Cloud_Addon_Settings::is_configured() ) {
				return null;
			}

			return new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
		}
	}
}
