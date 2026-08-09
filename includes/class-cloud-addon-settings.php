<?php
/**
 * Cloud addon settings registry.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Addon_Settings' ) ) {
	/**
	 * Owns the addon-local Cloud credential settings.
	 */
	final class Npcink_Cloud_Addon_Settings {
		private const DEFAULT_TIMEOUT = 8;
		private const MIN_TIMEOUT = 5;
		private const MAX_TIMEOUT = 60;
		private const LOCAL_DEFAULT_BASE_URL = 'http://localhost:8010/';
		private const PRODUCTION_DEFAULT_BASE_URL = 'https://cloud.npc.ink/';

		/**
		 * Registers WordPress settings metadata hook.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		}

		/**
		 * Registers the option schema with WordPress.
		 *
		 * @return void
		 */
		public static function register_setting(): void {
			register_setting(
				'npcink_cloud_addon',
				self::option_name(),
				array(
					'type' => 'array',
					'sanitize_callback' => array( __CLASS__, 'sanitize_option_value' ),
					'default' => self::stored_defaults(),
					'show_in_rest' => false,
				)
			);
		}

		/**
		 * Returns the option name.
		 *
		 * @return string
		 */
		public static function option_name(): string {
			$option_name = defined( 'NPCINK_CLOUD_ADDON_OPTION_NAME' )
				? (string) NPCINK_CLOUD_ADDON_OPTION_NAME
				: 'npcink_cloud_addon_settings';

			return '' !== $option_name ? $option_name : 'npcink_cloud_addon_settings';
		}

		/**
		 * Returns normalized settings.
		 *
		 * @return array<string,mixed>
		 */
		public static function get_settings(): array {
			$stored = get_option( self::option_name(), false );
			$stored = is_array( $stored ) ? $stored : array();

			return self::settings_from_stored_option( $stored );
		}

		/**
		 * Returns the default Cloud base URL for the current environment.
		 *
		 * @return string
		 */
		public static function get_default_base_url(): string {
			$default = self::PRODUCTION_DEFAULT_BASE_URL;
			if ( self::is_local_wordpress_environment() ) {
				$default = self::LOCAL_DEFAULT_BASE_URL;
			}
			if ( defined( 'NPCINK_CLOUD_ADDON_DEFAULT_BASE_URL' ) && '' !== trim( (string) NPCINK_CLOUD_ADDON_DEFAULT_BASE_URL ) ) {
				$default = (string) NPCINK_CLOUD_ADDON_DEFAULT_BASE_URL;
			}

			/**
			 * Filters the default Npcink Cloud base URL used by the authorization entry.
			 *
			 * @param string $default Default Cloud base URL.
			 */
			$filtered = apply_filters( 'npcink_cloud_addon_default_base_url', $default );
			$normalized = self::normalize_base_url( is_string( $filtered ) ? $filtered : $default );

			return '' !== $normalized ? $normalized : self::PRODUCTION_DEFAULT_BASE_URL;
		}

		/**
		 * Returns whether the current WordPress site looks like local development.
		 *
		 * @return bool
		 */
		private static function is_local_wordpress_environment(): bool {
			return Npcink_Cloud_Outbound_Policy::local_requests_allowed();
		}

		/**
		 * Returns the stored base URL or the environment default.
		 *
		 * @param array<string,mixed> $settings Stored settings.
		 * @return string
		 */
		public static function get_effective_base_url( array $settings = array() ): string {
			$settings = empty( $settings ) ? self::get_settings() : self::normalize_settings( $settings );
			$stored = (string) ( $settings['base_url'] ?? '' );

			return '' !== $stored ? $stored : self::get_default_base_url();
		}

		/**
		 * Returns whether credentials are complete.
		 *
		 * @return bool
		 */
		public static function is_configured(): bool {
			$settings = self::get_settings();

			return '' !== (string) $settings['base_url']
				&& '' !== (string) $settings['site_id']
				&& '' !== (string) $settings['key_id']
				&& '' !== (string) $settings['secret'];
		}

		/**
		 * Returns whether the last save-and-verify passed.
		 *
		 * @return bool
		 */
		public static function is_verified(): bool {
			$settings = self::get_settings();

			return self::is_configured() && ! empty( $settings['verified'] );
		}

		/**
		 * Returns whether monitoring collection may run.
		 *
		 * @return bool
		 */
			public static function is_monitoring_enabled(): bool {
				$settings = self::get_settings();

				return self::is_verified() && ! empty( $settings['monitoring_enabled'] );
			}

			/**
			 * Returns whether Site Knowledge public content delivery may run.
			 *
			 * @return bool
			 */
			public static function is_site_knowledge_delivery_enabled(): bool {
				$settings = self::get_settings();

				return self::is_verified() && ! empty( $settings['site_knowledge_delivery_enabled'] );
			}

			/**
			 * Returns whether WordPress AI title generation may use Site Knowledge style context.
			 *
			 * @return bool
			 */
			public static function is_site_knowledge_generation_reference_enabled(): bool {
				$settings = self::get_settings();

				return self::is_verified() && ! empty( $settings['site_knowledge_generation_reference_enabled'] );
			}

			/**
			 * Returns whether verified Cloud settings may be exposed to the WordPress AI plugin.
			 *
			 * @return bool
			 */
			public static function is_wordpress_ai_connector_enabled(): bool {
				$settings = self::get_settings();

				return self::is_verified() && ! empty( $settings['wordpress_ai_connector_enabled'] );
			}

		/**
		 * Returns a compact credential state for local surfaces.
		 *
		 * @return array<string,mixed>
		 */
		public static function get_credential_state(): array {
			$settings = self::get_settings();
			$configured = self::is_configured();
			$verified = $configured && ! empty( $settings['verified'] );
			$has_any_values = self::has_any_values( $settings );
			$last_error = sanitize_text_field( (string) $settings['last_verification_error'] );
			$activation_state = sanitize_key( (string) ( $settings['activation_state'] ?? '' ) );

			if ( ! $configured ) {
				return array(
					'code' => 'not_configured',
					'label' => __( 'Not configured', 'npcink-cloud-addon' ),
					'message' => $has_any_values
						? __( 'Cloud settings are incomplete. Reconnect this site in Npcink Cloud to issue a new connection key.', 'npcink-cloud-addon' )
						: __( 'Authorize this site in Npcink Cloud to create the connection.', 'npcink-cloud-addon' ),
					'configured' => false,
					'verified' => false,
					'verified_at' => '',
					'last_verification_error' => '',
					'severity' => 'inactive',
				);
			}

			if ( $verified ) {
				return array(
					'code' => 'configured_valid',
					'label' => __( 'Verified', 'npcink-cloud-addon' ),
					'message' => __( 'Cloud settings are saved and verified.', 'npcink-cloud-addon' ),
					'configured' => true,
					'verified' => true,
					'verified_at' => sanitize_text_field( (string) $settings['verified_at'] ),
					'last_verification_error' => '',
					'severity' => 'ok',
				);
			}

			if ( 'inactive' === $activation_state ) {
				return array(
					'code' => 'activation_required',
					'label' => __( 'Connected, activation required', 'npcink-cloud-addon' ),
					'message' => __( 'This site is bound and its connection credential is stored, but Cloud runtime service is inactive. Activate it in Npcink Cloud, then verify the connection here.', 'npcink-cloud-addon' ),
					'configured' => true,
					'verified' => false,
					'verified_at' => '',
					'last_verification_error' => '',
					'severity' => 'pending',
				);
			}

			return array(
				'code' => '' !== $last_error ? 'configured_unavailable' : 'configured_unverified',
				'label' => '' !== $last_error ? __( 'Unavailable', 'npcink-cloud-addon' ) : __( 'Pending verification', 'npcink-cloud-addon' ),
				'message' => '' !== $last_error ? $last_error : __( 'Cloud settings are saved but have not passed verification.', 'npcink-cloud-addon' ),
				'configured' => true,
				'verified' => false,
				'verified_at' => '',
				'last_verification_error' => $last_error,
				'severity' => '' !== $last_error ? 'error' : 'pending',
			);
		}

		/**
		 * Builds settings from admin POST payload without persisting.
		 *
		 * @param array<string,mixed> $payload Raw admin payload.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function build_settings_from_admin_payload( array $payload ) {
			$existing = self::get_settings();
			$next = $existing;

			if ( array_key_exists( 'base_url', $payload ) ) {
				$base_url = self::normalize_base_url( (string) $payload['base_url'] );
				if ( '' !== trim( (string) $payload['base_url'] ) && '' === $base_url ) {
					return new WP_Error(
						'invalid_cloud_base_url',
						__( 'Cloud Base URL must use HTTPS unless it points to localhost or 127.0.0.1.', 'npcink-cloud-addon' )
					);
				}
				if ( $base_url !== (string) $next['base_url'] ) {
					$next['verified'] = false;
					$next['verified_at'] = '';
					$next['last_verification_error'] = '';
				}
				$next['base_url'] = $base_url;
			}

			if ( array_key_exists( 'timeout', $payload ) ) {
				$next['timeout'] = self::normalize_timeout( $payload['timeout'] );
			}

			if ( array_key_exists( 'monitoring_enabled', $payload ) ) {
				$next['monitoring_enabled'] = ! empty( $payload['monitoring_enabled'] );
			}

			if ( array_key_exists( 'site_knowledge_delivery_enabled', $payload ) ) {
				$next['site_knowledge_delivery_enabled'] = ! empty( $payload['site_knowledge_delivery_enabled'] );
			}

			if ( array_key_exists( 'site_knowledge_generation_reference_enabled', $payload ) ) {
				$next['site_knowledge_generation_reference_enabled'] = ! empty( $payload['site_knowledge_generation_reference_enabled'] );
			}

			if ( array_key_exists( 'wordpress_ai_connector_enabled', $payload ) ) {
				$next['wordpress_ai_connector_enabled'] = ! empty( $payload['wordpress_ai_connector_enabled'] );
			}

			if ( array_key_exists( 'activation_state', $payload ) ) {
				$activation_state = sanitize_key( (string) $payload['activation_state'] );
				$next['activation_state'] = in_array( $activation_state, array( 'active', 'inactive' ), true )
					? $activation_state
					: '';
			}

			if ( array_key_exists( 'activation_reason', $payload ) ) {
				$next['activation_reason'] = sanitize_key( (string) $payload['activation_reason'] );
			}

			$api_key = array_key_exists( 'api_key', $payload ) ? trim( (string) $payload['api_key'] ) : '';
			if ( '' !== $api_key ) {
				$parsed = self::parse_api_key( $api_key );
				if ( is_wp_error( $parsed ) ) {
					return $parsed;
				}

				$next['site_id'] = (string) $parsed['site_id'];
				$next['key_id'] = (string) $parsed['key_id'];
				$next['secret'] = (string) $parsed['secret'];
				$next['verified'] = false;
				$next['verified_at'] = '';
				$next['last_verification_error'] = '';
			}

			return self::normalize_settings( $next );
		}

		/**
		 * Saves normalized settings.
		 *
		 * @param array<string,mixed> $settings Settings payload.
		 * @return bool
		 */
		public static function write_settings( array $settings ): bool {
			$normalized = self::normalize_settings( $settings );
			$current_stored = get_option( self::option_name(), array() );
			$current_stored = is_array( $current_stored ) ? $current_stored : array();
			if ( self::has_unreadable_credential_envelope( $current_stored ) && ! self::has_complete_credentials( $normalized ) ) {
				return false;
			}

			$current = self::get_settings();
			if ( $current === $normalized ) {
				return true;
			}

			$stored = self::build_stored_option( $normalized );
			if ( is_wp_error( $stored ) ) {
				return false;
			}

			return false !== update_option( self::option_name(), $stored, false );
		}

		/**
		 * Sanitizes Settings API writes into the encrypted at-rest shape.
		 *
		 * Invalid or unencryptable input returns the existing stored option so a
		 * failed security operation never replaces usable credentials.
		 *
		 * @param mixed $value Candidate option value.
		 * @return array<string,mixed>
		 */
		public static function sanitize_option_value( $value ): array {
			$value = is_array( $value ) ? $value : array();
			$current_stored = get_option( self::option_name(), array() );
			$current_stored = is_array( $current_stored ) ? $current_stored : array();

			if ( array_key_exists( 'credential_envelope', $value ) ) {
				$credentials = empty( $value['credential_envelope'] )
					? array( 'site_id' => '', 'key_id' => '', 'secret' => '' )
					: Npcink_Cloud_Credential_Store::decrypt( $value['credential_envelope'] );
				if ( is_wp_error( $credentials ) ) {
					self::add_storage_error( $credentials );
					return $current_stored;
				}

				$normalized = self::normalize_settings( array_merge( $value, $credentials ) );
				$stored = self::build_stored_option( $normalized, $value['credential_envelope'] );
			} else {
				$normalized = self::normalize_settings( $value );
				if ( self::has_unreadable_credential_envelope( $current_stored ) && ! self::has_complete_credentials( $normalized ) ) {
					self::add_settings_storage_error();
					return $current_stored;
				}
				if ( ! self::has_any_credentials( $normalized ) ) {
					$current = self::get_settings();
					$normalized['site_id'] = (string) $current['site_id'];
					$normalized['key_id'] = (string) $current['key_id'];
					$normalized['secret'] = (string) $current['secret'];
				}
				$stored = self::build_stored_option( $normalized );
			}

			if ( is_wp_error( $stored ) ) {
				self::add_storage_error( $stored );
				return $current_stored;
			}

			return $stored;
		}

		/**
		 * Marks the latest verification result.
		 *
		 * @param bool   $verified Whether verification passed.
		 * @param string $message Verification error message.
		 * @return array<string,mixed>
		 */
		public static function mark_verification_result( bool $verified, string $message = '' ): array {
			$settings = self::get_settings();
			$settings['verified'] = $verified;
			$settings['verified_at'] = $verified ? gmdate( 'Y-m-d H:i:s' ) . ' UTC' : '';
			$settings['last_verification_error'] = $verified ? '' : sanitize_text_field( $message );
			if ( $verified ) {
				$settings['activation_state'] = 'active';
				$settings['activation_reason'] = '';
			}
			self::write_settings( $settings );

			return self::get_settings();
		}

		/**
		 * Normalizes settings payload.
		 *
		 * @param mixed $settings Raw settings.
		 * @return array<string,mixed>
		 */
		public static function normalize_settings( $settings ): array {
			$settings = is_array( $settings ) ? $settings : array();

			return array(
				'base_url' => self::normalize_base_url( (string) ( $settings['base_url'] ?? '' ) ),
				'site_id' => self::normalize_identifier( (string) ( $settings['site_id'] ?? '' ) ),
				'key_id' => self::normalize_identifier( (string) ( $settings['key_id'] ?? '' ) ),
				'secret' => self::normalize_secret( (string) ( $settings['secret'] ?? '' ) ),
				'timeout' => self::normalize_timeout( $settings['timeout'] ?? self::DEFAULT_TIMEOUT ),
				'verified' => ! empty( $settings['verified'] ),
				'verified_at' => sanitize_text_field( (string) ( $settings['verified_at'] ?? '' ) ),
				'last_verification_error' => sanitize_text_field( (string) ( $settings['last_verification_error'] ?? '' ) ),
				'activation_state' => in_array( sanitize_key( (string) ( $settings['activation_state'] ?? '' ) ), array( 'active', 'inactive' ), true )
					? sanitize_key( (string) $settings['activation_state'] )
					: '',
				'activation_reason' => sanitize_key( (string) ( $settings['activation_reason'] ?? '' ) ),
				'monitoring_enabled' => ! empty( $settings['monitoring_enabled'] ),
				'site_knowledge_delivery_enabled' => array_key_exists( 'site_knowledge_delivery_enabled', $settings )
					? ! empty( $settings['site_knowledge_delivery_enabled'] )
					: false,
				'site_knowledge_generation_reference_enabled' => ! empty( $settings['site_knowledge_generation_reference_enabled'] ),
				'wordpress_ai_connector_enabled' => array_key_exists( 'wordpress_ai_connector_enabled', $settings )
					? ! empty( $settings['wordpress_ai_connector_enabled'] )
					: true,
			);
		}

		/**
		 * Removes all addon-owned settings.
		 *
		 * @return void
		 */
		public static function delete_settings(): void {
			delete_option( self::option_name() );
		}

		/**
		 * Returns default settings.
		 *
		 * @return array<string,mixed>
		 */
		private static function defaults(): array {
			return array(
				'base_url' => '',
				'site_id' => '',
				'key_id' => '',
				'secret' => '',
				'timeout' => self::DEFAULT_TIMEOUT,
				'verified' => false,
				'verified_at' => '',
				'last_verification_error' => '',
				'activation_state' => '',
				'activation_reason' => '',
				'monitoring_enabled' => false,
				'site_knowledge_delivery_enabled' => false,
				'site_knowledge_generation_reference_enabled' => false,
				'wordpress_ai_connector_enabled' => true,
			);
		}

		/**
		 * Returns the safe WordPress option default.
		 *
		 * @return array<string,mixed>
		 */
		private static function stored_defaults(): array {
			$stored = self::defaults();
			unset( $stored['site_id'], $stored['key_id'], $stored['secret'] );
			$stored['credential_envelope'] = array();

			return $stored;
		}

		/**
		 * Builds public settings from the authenticated stored option.
		 *
		 * Legacy plaintext credential fields are deliberately ignored. Missing,
		 * tampered, or undecryptable envelopes fail closed as unconfigured.
		 *
		 * @param array<string,mixed> $stored Stored option.
		 * @return array<string,mixed>
		 */
		private static function settings_from_stored_option( array $stored ): array {
			$non_secret = $stored;
			unset( $non_secret['site_id'], $non_secret['key_id'], $non_secret['secret'], $non_secret['credential_envelope'] );
			$settings = self::normalize_settings( $non_secret );

			$envelope = $stored['credential_envelope'] ?? null;
			if ( ! is_array( $envelope ) || empty( $envelope ) ) {
				$settings['verified'] = false;
				$settings['verified_at'] = '';
				return $settings;
			}

			$credentials = Npcink_Cloud_Credential_Store::decrypt( $envelope );
			if ( is_wp_error( $credentials ) ) {
				$settings['verified'] = false;
				$settings['verified_at'] = '';
				return $settings;
			}

			$settings['site_id'] = self::normalize_identifier( $credentials['site_id'] );
			$settings['key_id'] = self::normalize_identifier( $credentials['key_id'] );
			$settings['secret'] = self::normalize_secret( $credentials['secret'] );

			return $settings;
		}

		/**
		 * Builds the authenticated WordPress option shape.
		 *
		 * @param array<string,mixed> $settings Public settings shape.
		 * @param mixed               $existing_envelope Optional authenticated envelope to retain.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function build_stored_option( array $settings, $existing_envelope = null ) {
			$settings = self::normalize_settings( $settings );
			$credentials = array(
				'site_id' => (string) $settings['site_id'],
				'key_id'  => (string) $settings['key_id'],
				'secret'  => (string) $settings['secret'],
			);

			$envelope = $existing_envelope;
			if ( ! is_array( $envelope ) ) {
				$envelope = self::has_any_credentials( $credentials )
					? Npcink_Cloud_Credential_Store::encrypt( $credentials )
					: array();
			}
			if ( is_wp_error( $envelope ) ) {
				return $envelope;
			}

			unset( $settings['site_id'], $settings['key_id'], $settings['secret'] );
			$settings['credential_envelope'] = $envelope;

			return $settings;
		}

		/**
		 * Returns whether any credential slot is populated.
		 *
		 * @param array<string,mixed> $credentials Credential payload.
		 * @return bool
		 */
		private static function has_any_credentials( array $credentials ): bool {
			return '' !== (string) ( $credentials['site_id'] ?? '' )
				|| '' !== (string) ( $credentials['key_id'] ?? '' )
				|| '' !== (string) ( $credentials['secret'] ?? '' );
		}

		/**
		 * Returns whether every signing credential slot is populated.
		 *
		 * @param array<string,mixed> $credentials Credential payload.
		 * @return bool
		 */
		private static function has_complete_credentials( array $credentials ): bool {
			return '' !== (string) ( $credentials['site_id'] ?? '' )
				&& '' !== (string) ( $credentials['key_id'] ?? '' )
				&& '' !== (string) ( $credentials['secret'] ?? '' );
		}

		/**
		 * Returns whether a stored credential envelope cannot be authenticated.
		 *
		 * @param array<string,mixed> $stored Stored option.
		 * @return bool
		 */
		private static function has_unreadable_credential_envelope( array $stored ): bool {
			$envelope = $stored['credential_envelope'] ?? null;
			return is_array( $envelope )
				&& ! empty( $envelope )
				&& is_wp_error( Npcink_Cloud_Credential_Store::decrypt( $envelope ) );
		}

		/**
		 * Reports a generic Settings API storage error without secret material.
		 *
		 * @param WP_Error $error Storage error.
		 * @return void
		 */
		private static function add_storage_error( WP_Error $error ): void {
			if ( function_exists( 'add_settings_error' ) ) {
				add_settings_error(
					self::option_name(),
					$error->get_error_code(),
					$error->get_error_message(),
					'error'
				);
			}
		}

		/**
		 * Reports an unreadable-envelope Settings API error.
		 *
		 * @return void
		 */
		private static function add_settings_storage_error(): void {
			self::add_storage_error(
				new WP_Error(
					'cloud_credential_authentication_failed',
					__( 'Cloud credentials could not be stored or read securely. Reconnect this site after checking the WordPress security salts.', 'npcink-cloud-addon' )
				)
			);
		}

		/**
		 * Returns whether settings contain any meaningful Cloud value.
		 *
		 * @param array<string,mixed> $settings Settings payload.
		 * @return bool
		 */
		private static function has_any_values( array $settings ): bool {
			return '' !== (string) ( $settings['base_url'] ?? '' )
				|| '' !== (string) ( $settings['site_id'] ?? '' )
				|| '' !== (string) ( $settings['key_id'] ?? '' )
				|| '' !== (string) ( $settings['secret'] ?? '' );
		}

		/**
		 * Parses a customer-facing API key into signing credentials.
		 *
		 * Supported format:
		 * - mak1_{base64url(json)}
		 *
		 * @param string $api_key Raw Cloud API Key.
		 * @return array<string,string>|WP_Error
		 */
		private static function parse_api_key( string $api_key ) {
			$api_key = trim( $api_key );
			if ( '' === $api_key ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Cloud API Key cannot be empty.', 'npcink-cloud-addon' )
				);
			}

			if ( 0 !== strpos( $api_key, 'mak1_' ) ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Manual Cloud API Key recovery must use a Cloud-issued mak1_ wrapper. Reconnect this site in Npcink Cloud to issue a new key.', 'npcink-cloud-addon' )
				);
			}

			$decoded = self::base64url_decode( substr( $api_key, 5 ) );
			if ( false === $decoded || '' === $decoded ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Cloud API Key could not be decoded.', 'npcink-cloud-addon' )
				);
			}

			$decoded_payload = json_decode( $decoded, true );
			if ( ! is_array( $decoded_payload ) ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Cloud API Key format is invalid. Use a key issued by Npcink Cloud.', 'npcink-cloud-addon' )
				);
			}

			$site_id = self::normalize_identifier( (string) ( $decoded_payload['site_id'] ?? '' ) );
			$key_id = self::normalize_identifier( (string) ( $decoded_payload['key_id'] ?? '' ) );
			$secret = self::normalize_secret( (string) ( $decoded_payload['secret'] ?? '' ) );

			if ( '' === $site_id || '' === $key_id || '' === $secret ) {
				return new WP_Error(
					'invalid_cloud_api_key',
					__( 'Cloud API Key wrapper is missing required signing data.', 'npcink-cloud-addon' )
				);
			}

			return array(
				'site_id' => $site_id,
				'key_id' => $key_id,
				'secret' => $secret,
			);
		}

		/**
		 * Normalizes Cloud base URL.
		 *
		 * @param string $base_url Raw base URL.
		 * @return string
		 */
		private static function normalize_base_url( string $base_url ): string {
			return Npcink_Cloud_Outbound_Policy::normalize_base_url( $base_url );
		}

		/**
		 * Normalizes Cloud identifiers.
		 *
		 * @param string $value Raw identifier.
		 * @return string
		 */
		private static function normalize_identifier( string $value ): string {
			$value = sanitize_text_field( $value );
			$value = preg_replace( '/[^A-Za-z0-9._:-]/', '', $value );

			return is_string( $value ) ? trim( $value ) : '';
		}

		/**
		 * Normalizes stored secret.
		 *
		 * @param string $secret Raw secret.
		 * @return string
		 */
		private static function normalize_secret( string $secret ): string {
			return trim( $secret );
		}

		/**
		 * Normalizes timeout seconds.
		 *
		 * @param mixed $timeout Raw timeout.
		 * @return int
		 */
		private static function normalize_timeout( $timeout ): int {
			$timeout = absint( $timeout );
			if ( $timeout < self::MIN_TIMEOUT ) {
				return self::MIN_TIMEOUT;
			}
			if ( $timeout > self::MAX_TIMEOUT ) {
				return self::MAX_TIMEOUT;
			}

			return $timeout;
		}

		/**
		 * Decodes a base64url payload.
		 *
		 * @param string $encoded Encoded value.
		 * @return string|false
		 */
		private static function base64url_decode( string $encoded ) {
			$encoded = strtr( $encoded, '-_', '+/' );
			$padding = strlen( $encoded ) % 4;
			if ( 0 !== $padding ) {
				$encoded .= str_repeat( '=', 4 - $padding );
			}

			return base64_decode( $encoded, true );
		}
	}
}
