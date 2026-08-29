<?php
/**
 * Signed Cloud runtime callback registration and receipt.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Runtime_Callback' ) ) {
	/** Registers one site callback and wakes the existing media continuation Cron. */
	final class Npcink_Cloud_Runtime_Callback {
		private const OPTION_NAME = 'npcink_cloud_addon_runtime_callback_registration';
		private const REST_NAMESPACE = 'npcink-cloud-addon/v1';
		private const REST_ROUTE = '/runtime-callbacks/terminal';
		private const EVENT = 'runtime.run.terminal';
		private const MEDIA_PLAN_OPTION = 'npcink_cloud_addon_media_recognition_plan';
		private const MEDIA_PLAN_CRON = 'npcink_cloud_addon_continue_media_recognition';
		private const RECEIPT_PREFIX = 'npcink_cloud_callback_receipt_';
		private const RECEIPT_TTL = DAY_IN_SECONDS;
		private const TIMESTAMP_TOLERANCE = 300;
		private const MAX_BODY_BYTES = 262144;
		private const PBKDF2_ITERATIONS = 210000;
		private const PBKDF2_SALT = 'npcink-ai-cloud-secret-hash-v2';

		/** Registers the public, signature-verified callback route. */
		public static function register(): void {
			add_action( 'rest_api_init', array( __CLASS__, 'register_rest_route' ) );
		}

		/** Registers the callback REST route without granting WordPress authority. */
		public static function register_rest_route(): void {
			register_rest_route(
				self::REST_NAMESPACE,
				self::REST_ROUTE,
				array(
					'methods' => 'POST',
					'callback' => array( __CLASS__, 'receive_terminal_callback' ),
					'permission_callback' => '__return_true',
				)
			);
		}

		/** Ensures Cloud has the current callback target before a background run starts. */
		public static function ensure_registered() {
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return new WP_Error( 'cloud_callback_unverified', __( 'Npcink Cloud is not verified.', 'npcink-cloud-addon' ) );
			}

			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			$callback_url = untrailingslashit( rest_url( self::REST_NAMESPACE . self::REST_ROUTE ) );
			$registration = self::get_registration();
			if ( is_wp_error( $registration ) ) {
				$registration = array();
			}
			$same_target = (string) ( $registration['site_id'] ?? '' ) === (string) ( $settings['site_id'] ?? '' )
				&& (string) ( $registration['callback_url'] ?? '' ) === $callback_url;
			if ( $same_target && ! empty( $registration['cloud_registered'] ) ) {
				return true;
			}

			if ( ! $same_target || empty( $registration['secret'] ) ) {
				$registration = array(
					'site_id' => (string) ( $settings['site_id'] ?? '' ),
					'registration_id' => 'runtime_registration_' . str_replace( '-', '', wp_generate_uuid4() ),
					'key_id' => 'runtime_callback_' . str_replace( '-', '', wp_generate_uuid4() ),
					'secret' => wp_generate_password( 64, false, false ),
					'callback_url' => $callback_url,
					'cloud_registered' => false,
				);
				$stored = self::store_registration( $registration );
				if ( is_wp_error( $stored ) ) {
					return $stored;
				}
			}

			$client = Npcink_Cloud_Runtime_Client_Factory::configured();
			if ( ! $client ) {
				return new WP_Error( 'cloud_callback_unconfigured', __( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ) );
			}
			$result = $client->register_terminal_callback(
				array(
					'contract_version' => 'runtime_terminal_callback_registration.v1',
					'enabled' => true,
					'callback_url' => (string) $registration['callback_url'],
					'key_id' => (string) $registration['key_id'],
					'secret' => (string) $registration['secret'],
					'registration_id' => (string) $registration['registration_id'],
				),
				'callback_register_' . hash( 'sha256', (string) $registration['registration_id'] )
			);
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$registration['cloud_registered'] = true;
			$registration['registered_at'] = time();
			$stored = self::store_registration( $registration );

			return is_wp_error( $stored ) ? $stored : true;
		}

		/** Best-effort Cloud deregistration before local credentials are removed. */
		public static function unregister(): void {
			$client = Npcink_Cloud_Runtime_Client_Factory::configured();
			if ( ! $client ) {
				return;
			}
			$client->register_terminal_callback(
				array(
					'contract_version' => 'runtime_terminal_callback_registration.v1',
					'enabled' => false,
				),
				'callback_disable_' . wp_generate_uuid4()
			);
		}

		/** Verifies one Cloud callback and only wakes the existing continuation hook. */
		public static function receive_terminal_callback( $request ) {
			$registration = self::get_registration();
			if ( is_wp_error( $registration ) ) {
				return self::error( 'cloud_callback_registration_unavailable', 'Callback registration is unavailable.', 401 );
			}
			$body = (string) $request->get_body();
			if ( '' === $body || strlen( $body ) > self::MAX_BODY_BYTES ) {
				return self::error( 'cloud_callback_body_invalid', 'Callback body is empty or too large.', 400 );
			}

			$headers = array(
				'event' => trim( (string) $request->get_header( 'x-npcink-cloud-event' ) ),
				'run_id' => trim( (string) $request->get_header( 'x-npcink-run-id' ) ),
				'site_id' => trim( (string) $request->get_header( 'x-npcink-site-id' ) ),
				'key_id' => trim( (string) $request->get_header( 'x-npcink-key-id' ) ),
				'timestamp' => trim( (string) $request->get_header( 'x-npcink-timestamp' ) ),
				'callback_id' => trim( (string) $request->get_header( 'x-npcink-callback-id' ) ),
				'signature' => strtolower( trim( (string) $request->get_header( 'x-npcink-signature' ) ) ),
				'traceparent' => strtolower( trim( (string) $request->get_header( 'traceparent' ) ) ),
			);
			$timestamp = ctype_digit( $headers['timestamp'] ) ? (int) $headers['timestamp'] : 0;
			if (
				self::EVENT !== $headers['event']
				|| (string) $registration['site_id'] !== $headers['site_id']
				|| (string) $registration['key_id'] !== $headers['key_id']
				|| 1 !== preg_match( '/^[A-Za-z0-9._:-]{1,191}$/', $headers['run_id'] )
				|| 1 !== preg_match( '/^runtime_delivery_[0-9a-f]{64}$/', $headers['callback_id'] )
				|| 1 !== preg_match( '/^00-[0-9a-f]{32}-[0-9a-f]{16}-[0-9a-f]{2}$/', $headers['traceparent'] )
				|| 1 !== preg_match( '/^[0-9a-f]{64}$/', $headers['signature'] )
				|| 0 === $timestamp
				|| abs( time() - $timestamp ) > self::TIMESTAMP_TOLERANCE
			) {
				return self::error( 'cloud_callback_headers_invalid', 'Callback headers are invalid.', 401 );
			}

			$payload = json_decode( $body, true );
			if ( ! is_array( $payload ) || self::EVENT !== (string) ( $payload['event'] ?? '' ) || $headers['run_id'] !== (string) ( $payload['run_id'] ?? '' ) || $headers['site_id'] !== (string) ( $payload['site_id'] ?? '' ) ) {
				return self::error( 'cloud_callback_payload_invalid', 'Callback payload does not match its signed headers.', 400 );
			}

			$canonical = self::build_canonical( $registration, $headers, $body );
			$secret_hash = 'pbkdf2_sha256$' . self::PBKDF2_ITERATIONS . '$'
				. hash_pbkdf2( 'sha256', (string) $registration['secret'], self::PBKDF2_SALT, self::PBKDF2_ITERATIONS );
			$expected = hash_hmac( 'sha256', $canonical, $secret_hash );
			if ( ! hash_equals( $expected, $headers['signature'] ) ) {
				return self::error( 'cloud_callback_signature_invalid', 'Callback signature is invalid.', 401 );
			}

			$plan = get_option( self::MEDIA_PLAN_OPTION, array() );
			$matches_active_plan = is_array( $plan )
				&& ! empty( $plan['active'] )
				&& $headers['run_id'] === (string) ( $plan['current_run_id'] ?? '' );
			if ( ! $matches_active_plan ) {
				return new WP_REST_Response( array( 'status' => 'ignored' ), 202 );
			}

			$receipt_key = self::RECEIPT_PREFIX . hash( 'sha256', $headers['callback_id'] );
			if ( false !== get_transient( $receipt_key ) ) {
				return new WP_REST_Response( array( 'status' => 'already_received' ), 200 );
			}

			wp_clear_scheduled_hook( self::MEDIA_PLAN_CRON );
			$scheduled = wp_schedule_single_event( time() + 1, self::MEDIA_PLAN_CRON );
			if ( false === $scheduled || is_wp_error( $scheduled ) ) {
				return self::error( 'cloud_callback_schedule_failed', 'Callback continuation could not be scheduled.', 503 );
			}
			set_transient( $receipt_key, time(), self::RECEIPT_TTL );

			return new WP_REST_Response( array( 'status' => 'scheduled' ), 202 );
		}

		/** Builds the exact canonical string signed by Cloud. */
		private static function build_canonical( array $registration, array $headers, string $body ): string {
			$parts = wp_parse_url( (string) $registration['callback_url'] );
			$path = is_array( $parts ) ? (string) ( $parts['path'] ?? '/' ) : '/';
			$query = is_array( $parts ) ? (string) ( $parts['query'] ?? '' ) : '';
			$route = '' === $query ? $path : $path . '?' . $query;

			return implode(
				"\n",
				array(
					'POST',
					$route,
					$headers['site_id'],
					$headers['key_id'],
					$headers['timestamp'],
					$headers['event'],
					$headers['callback_id'],
					$headers['traceparent'],
					hash( 'sha256', $body ),
				)
			);
		}

		/** Returns the decrypted registration without exposing it publicly. */
		private static function get_registration() {
			$stored = get_option( self::OPTION_NAME, array() );
			if ( ! is_array( $stored ) || ! is_array( $stored['credential_envelope'] ?? null ) ) {
				return new WP_Error( 'cloud_callback_registration_unavailable' );
			}
			$credentials = Npcink_Cloud_Credential_Store::decrypt( $stored['credential_envelope'] );
			if ( is_wp_error( $credentials ) ) {
				return $credentials;
			}

			return array(
				'site_id' => sanitize_text_field( (string) ( $stored['site_id'] ?? '' ) ),
				'registration_id' => sanitize_text_field( (string) ( $credentials['site_id'] ?? '' ) ),
				'key_id' => sanitize_text_field( (string) ( $credentials['key_id'] ?? '' ) ),
				'secret' => (string) ( $credentials['secret'] ?? '' ),
				'callback_url' => esc_url_raw( (string) ( $stored['callback_url'] ?? '' ) ),
				'cloud_registered' => ! empty( $stored['cloud_registered'] ),
				'registered_at' => absint( $stored['registered_at'] ?? 0 ),
			);
		}

		/** Stores only encrypted callback signing material. */
		private static function store_registration( array $registration ) {
			$envelope = Npcink_Cloud_Credential_Store::encrypt(
				array(
					'site_id' => (string) $registration['registration_id'],
					'key_id' => (string) $registration['key_id'],
					'secret' => (string) $registration['secret'],
				)
			);
			if ( is_wp_error( $envelope ) ) {
				return $envelope;
			}
			update_option(
				self::OPTION_NAME,
				array(
					'site_id' => sanitize_text_field( (string) $registration['site_id'] ),
					'callback_url' => esc_url_raw( (string) $registration['callback_url'] ),
					'cloud_registered' => ! empty( $registration['cloud_registered'] ),
					'registered_at' => absint( $registration['registered_at'] ?? 0 ),
					'credential_envelope' => $envelope,
				),
				false
			);

			return true;
		}

		/** Returns a bounded REST error without secret or provider detail. */
		private static function error( string $code, string $message, int $status ): WP_Error {
			return new WP_Error( $code, $message, array( 'status' => $status ) );
		}
	}
}
