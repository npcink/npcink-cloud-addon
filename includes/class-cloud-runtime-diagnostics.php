<?php
/**
 * Runtime connector readiness diagnostics.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Runtime_Diagnostics' ) ) {
	/**
	 * Projects connectivity probes into bounded non-secret readiness diagnostics.
	 *
	 * Every method is static and instance-free: projections run over the probe
	 * array and the caller's configuration snapshot. Secret-bearing values are
	 * only read to redact them from support text.
	 */
	final class Npcink_Cloud_Runtime_Diagnostics {
		private const MAX_ERROR_MESSAGE_CHARS = 4096;

		/**
		 * Builds a bounded non-secret readiness result for diagnostics.
		 *
		 * @param array<string,mixed> $probe Connectivity probe result.
		 * @param array<string,mixed> $config Client configuration snapshot.
		 * @param bool                $is_configured Whether the client credential slots are complete.
		 * @return array<string,mixed>
		 */
		public static function build_readiness_result( array $probe, array $config, bool $is_configured ): array {
			$status = 'failed';
			$owner_label = 'cloud_addon';
			$blocked_reason = '';
			$next_action = 'retry_test';
			$base_url = untrailingslashit( (string) ( $config['base_url'] ?? '' ) );
			$base_url_present = '' !== $base_url;
			$site_id_present = '' !== (string) ( $config['site_id'] ?? '' );
			$key_id_present = '' !== (string) ( $config['key_id'] ?? '' );
			$secret_present = '' !== (string) ( $config['secret'] ?? '' );
			$credential_slots_complete = $base_url_present && $site_id_present && $key_id_present && $secret_present;
			$credential_slot_readiness = 'not_configured';
			if ( $credential_slots_complete ) {
				$credential_slot_readiness = 'ready';
			} elseif ( $base_url_present || $site_id_present || $key_id_present || $secret_present ) {
				$credential_slot_readiness = 'partial';
			}
			$service_liveness_status = $base_url_present ? ( ! empty( $probe['live_ok'] ) ? 'ready' : 'unavailable' ) : 'not_configured';
			$signed_transport_status = 'not_configured';
			if ( 'ready' === $credential_slot_readiness ) {
				if ( 'ready' !== $service_liveness_status ) {
					$signed_transport_status = 'unavailable';
				} elseif ( ! empty( $probe['auth_ok'] ) ) {
					$signed_transport_status = 'ready';
				} else {
					$signed_transport_status = 'failed';
				}
			}
			$connector_diagnostic_category = self::classify_connector_diagnostic_category(
				$base_url_present,
				$credential_slot_readiness,
				$service_liveness_status,
				$signed_transport_status
			);

			if ( ! $is_configured ) {
				$status = 'not_configured';
				$owner_label = 'operator';
				$blocked_reason = __( 'Cloud settings are incomplete.', 'npcink-cloud-addon' );
				$next_action = 'open_settings';
			} elseif ( ! empty( $probe['ok'] ) ) {
				$status = 'ready';
				$owner_label = 'cloud_addon';
				$blocked_reason = '';
				$next_action = 'continue';
			} elseif ( empty( $probe['live_ok'] ) ) {
				$status = 'unavailable';
				$owner_label = 'cloud';
				$blocked_reason = self::redact_support_text( (string) ( $probe['live_message'] ?? '' ), $config );
				$next_action = 'check_cloud_status';
			} else {
				$status = 'failed';
				$owner_label = 'cloud';
				$blocked_reason = self::redact_support_text( (string) ( $probe['auth_message'] ?? '' ), $config );
				$next_action = 'retry_test';
			}

			if ( '' === $blocked_reason && 'ready' !== $status ) {
				$blocked_reason = __( 'Connector readiness could not be verified.', 'npcink-cloud-addon' );
			}

			$host = '' !== $base_url ? sanitize_text_field( (string) wp_parse_url( $base_url, PHP_URL_HOST ) ) : '';
			$support_facts = array(
				'contract_version' => 'cloud_addon_readiness_result.v1',
				'connector_slot' => 'npcink_cloud_runtime',
				'connector_diagnostic_category' => $connector_diagnostic_category,
				'credential_slot_readiness' => $credential_slot_readiness,
				'signed_transport_status' => $signed_transport_status,
				'service_liveness_status' => $service_liveness_status,
				'base_url_host' => '' !== $host ? $host : 'not_set',
				'base_url_present' => $base_url_present ? 'yes' : 'no',
				'site_id_present' => $site_id_present ? 'yes' : 'no',
				'key_id_present' => $key_id_present ? 'yes' : 'no',
				'signing_secret_slot_present' => $secret_present ? 'yes' : 'no',
				'signing_credentials_complete' => $credential_slots_complete ? 'yes' : 'no',
				'timeout_seconds' => (string) max( 5, absint( $config['timeout'] ?? 8 ) ),
				'live_ok' => ! empty( $probe['live_ok'] ) ? 'yes' : 'no',
				'signed_read_ok' => ! empty( $probe['auth_ok'] ) ? 'yes' : 'no',
				'signed_read_endpoint' => 'GET /v1/entitlements/current',
				'write_posture' => 'read_only',
			);
			$diagnostic_panel_groups = self::build_diagnostic_panel_groups(
				$probe,
				$support_facts,
				$credential_slot_readiness,
				$service_liveness_status,
				$signed_transport_status,
				$config
			);

			return array(
				'contract_version' => 'cloud_addon_readiness_result.v1',
				'manual_test_action' => 'probe_connectivity',
				'connector_slot' => 'npcink_cloud_runtime',
				'connector_diagnostic_category' => $connector_diagnostic_category,
				'credential_slot_readiness' => $credential_slot_readiness,
				'signed_transport_status' => $signed_transport_status,
				'service_liveness_status' => $service_liveness_status,
				'status' => $status,
				'bounded_status' => $status,
				'owner_label' => $owner_label,
				'blocked_reason' => sanitize_text_field( $blocked_reason ),
				'next_action' => $next_action,
				'next_safe_action' => $next_action,
				'support_facts' => $support_facts,
				'copyable_support_facts' => $support_facts,
				'diagnostic_panel_groups' => $diagnostic_panel_groups,
				'write_posture' => 'read_only',
				'tested_at' => gmdate( 'c' ),
			);
		}

		/**
		 * Projects one readiness result into bounded operator diagnostic groups.
		 *
		 * @param array<string,mixed> $probe Connectivity probe result.
		 * @param array<string,string> $support_facts Bounded support facts.
		 * @param string               $credential_status Credential-slot status.
		 * @param string               $liveness_status Service liveness status.
		 * @param string               $signed_status Signed transport status.
		 * @param array<string,mixed>  $config Client configuration snapshot used for secret redaction.
		 * @return array<int,array<string,mixed>>
		 */
		private static function build_diagnostic_panel_groups(
			array $probe,
			array $support_facts,
			string $credential_status,
			string $liveness_status,
			string $signed_status,
			array $config
		): array {
			$configuration_status = 'ready' === $credential_status ? 'ready' : 'not_configured';
			$configuration_reason = 'ready' === $configuration_status ? '' : __( 'Cloud settings are incomplete.', 'npcink-cloud-addon' );
			$liveness_reason = 'ready' === $liveness_status ? '' : self::redact_support_text( (string) ( $probe['live_message'] ?? '' ), $config );
			$signed_reason = 'ready' === $signed_status ? '' : self::redact_support_text( (string) ( $probe['auth_message'] ?? $probe['live_message'] ?? '' ), $config );

			return array(
				self::build_diagnostic_panel_group(
					'local_configuration',
					'credential_slot_readiness',
					$configuration_status,
					'operator',
					$configuration_reason,
					array(
						'base_url_host' => $support_facts['base_url_host'],
						'base_url_present' => $support_facts['base_url_present'],
						'site_id_present' => $support_facts['site_id_present'],
						'key_id_present' => $support_facts['key_id_present'],
						'signing_secret_slot_present' => $support_facts['signing_secret_slot_present'],
						'signing_credentials_complete' => $support_facts['signing_credentials_complete'],
					),
					'ready' === $configuration_status ? 'continue' : 'open_settings'
				),
				self::build_diagnostic_panel_group(
					'cloud_connectivity',
					'service_liveness',
					$liveness_status,
					'cloud',
					$liveness_reason,
					array(
						'base_url_host' => $support_facts['base_url_host'],
						'service_liveness_status' => $support_facts['service_liveness_status'],
						'live_ok' => $support_facts['live_ok'],
					),
					self::diagnostic_next_safe_action( $liveness_status )
				),
				self::build_diagnostic_panel_group(
					'signed_transport',
					'signed_entitlement_read',
					$signed_status,
					'cloud_addon',
					$signed_reason,
					array(
						'connector_slot' => $support_facts['connector_slot'],
						'signed_transport_status' => $support_facts['signed_transport_status'],
						'signed_read_ok' => $support_facts['signed_read_ok'],
						'signed_read_endpoint' => $support_facts['signed_read_endpoint'],
					),
					self::diagnostic_next_safe_action( $signed_status )
				),
				self::build_diagnostic_panel_group(
					'entitlement_readiness',
					'entitlement_readiness',
					$signed_status,
					'cloud',
					$signed_reason,
					array(
						'signed_read_endpoint' => $support_facts['signed_read_endpoint'],
						'signed_read_ok' => $support_facts['signed_read_ok'],
						'write_posture' => $support_facts['write_posture'],
					),
					self::diagnostic_next_safe_action( $signed_status )
				),
				self::build_diagnostic_panel_group(
					'support_facts',
					'bounded_support_facts',
					'ready',
					'cloud_addon',
					'',
					$support_facts,
					'continue'
				),
			);
		}

		/**
		 * Builds one bounded diagnostic group.
		 *
		 * @param string               $group Group identifier.
		 * @param string               $category Diagnostic category.
		 * @param string               $status Bounded status.
		 * @param string               $owner_label Owning system or operator.
		 * @param string               $blocked_reason Bounded blocked reason.
		 * @param array<string,string> $safe_support_facts Non-secret support facts.
		 * @param string               $next_safe_action Next safe operator action.
		 * @return array<string,mixed>
		 */
		private static function build_diagnostic_panel_group(
			string $group,
			string $category,
			string $status,
			string $owner_label,
			string $blocked_reason,
			array $safe_support_facts,
			string $next_safe_action
		): array {
			if ( 'ready' !== $status && '' === $blocked_reason ) {
				$blocked_reason = __( 'Connector readiness could not be verified.', 'npcink-cloud-addon' );
			}

			return array(
				'diagnostic_panel_group' => $group,
				'diagnostic_category' => $category,
				'severity' => self::diagnostic_severity( $status ),
				'owner_label' => $owner_label,
				'bounded_status' => $status,
				'blocked_reason' => sanitize_text_field( $blocked_reason ),
				'safe_support_facts' => $safe_support_facts,
				'next_safe_action' => $next_safe_action,
				'visibility' => 'administrator_only',
				'write_posture' => 'read_only',
			);
		}

		/**
		 * Maps one bounded status to the small admin severity vocabulary.
		 *
		 * @param string $status Bounded status.
		 * @return string
		 */
		private static function diagnostic_severity( string $status ): string {
			if ( 'ready' === $status ) {
				return 'ok';
			}

			if ( 'failed' === $status ) {
				return 'error';
			}

			return 'not_configured' === $status ? 'inactive' : 'warning';
		}

		/**
		 * Returns the bounded next action for signed-read-derived groups.
		 *
		 * @param string $status Bounded status.
		 * @return string
		 */
		private static function diagnostic_next_safe_action( string $status ): string {
			if ( 'ready' === $status ) {
				return 'continue';
			}

			if ( 'not_configured' === $status ) {
				return 'open_settings';
			}

			return 'unavailable' === $status ? 'check_cloud_status' : 'retry_test';
		}

		/**
		 * Classifies connector readiness into one bounded operator diagnostic bucket.
		 *
		 * @param bool   $base_url_present Whether the Cloud Base URL slot is present.
		 * @param string $credential_slot_readiness Credential-slot readiness.
		 * @param string $service_liveness_status Service liveness status.
		 * @param string $signed_transport_status Signed-read transport status.
		 * @return string
		 */
		private static function classify_connector_diagnostic_category(
			bool $base_url_present,
			string $credential_slot_readiness,
			string $service_liveness_status,
			string $signed_transport_status
		): string {
			if ( ! $base_url_present && 'not_configured' === $credential_slot_readiness ) {
				return 'not_configured';
			}

			if ( in_array( $credential_slot_readiness, array( 'partial', 'not_configured' ), true ) ) {
				return 'credential_missing';
			}

			if ( 'unavailable' === $service_liveness_status || 'unavailable' === $signed_transport_status ) {
				return 'cloud_unavailable';
			}

			if ( 'failed' === $signed_transport_status ) {
				return 'signed_transport_failed';
			}

			if ( 'ready' === $service_liveness_status && 'ready' === $signed_transport_status ) {
				return 'ready';
			}

			return 'unknown';
		}

		/**
		 * Redacts sensitive token shapes from readiness support text.
		 *
		 * @param string              $message Raw support message.
		 * @param array<string,mixed> $config Client configuration snapshot used for secret redaction.
		 * @return string
		 */
		public static function redact_support_text( string $message, array $config ): string {
			foreach ( array( 'secret', 'authorization' ) as $sensitive_config_key ) {
				$sensitive_value = $config[ $sensitive_config_key ] ?? '';
				if ( is_string( $sensitive_value ) && strlen( $sensitive_value ) >= 8 ) {
					$message = str_replace( $sensitive_value, '[redacted]', $message );
				}
			}

			$message = preg_replace(
				"~(?<![A-Za-z0-9_-])[A-Za-z0-9_-]*(?:authorizations?|api[_-]?keys?|provider[_-]?keys?|tokens?|credentials?|cookies?|passwords?|secrets?|signatures?|nonces?)\\s*[:=]\\s*(?:\"[^\"]*\"|'[^']*'|[A-Za-z][A-Za-z0-9_-]*\\s+[^\\s,;]+|[^\\s,;]+)~i",
				'[redacted]',
				$message
			);
			$message = preg_replace( '/mak1_[A-Za-z0-9_-]+/', '[redacted]', $message );
			$message = preg_replace( '/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [redacted]', (string) $message );
			$message = preg_replace( '/secret[_-]?[A-Za-z0-9._:-]*/i', '[redacted]', (string) $message );

			$message = sanitize_text_field( (string) $message );
			if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
				return mb_strlen( $message, 'UTF-8' ) > self::MAX_ERROR_MESSAGE_CHARS
					? mb_substr( $message, 0, self::MAX_ERROR_MESSAGE_CHARS, 'UTF-8' ) . '…'
					: $message;
			}

			return strlen( $message ) > self::MAX_ERROR_MESSAGE_CHARS
				? substr( $message, 0, self::MAX_ERROR_MESSAGE_CHARS ) . '...'
				: $message;
		}
	}
}
