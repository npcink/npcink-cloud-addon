<?php
/**
 * Cloud entitlement summary.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Entitlement_Summary' ) ) {
	/**
	 * Reads a compact, read-only entitlement projection from Cloud.
	 */
	final class Npcink_Cloud_Entitlement_Summary {
		private const FRESHNESS_TTL_SECONDS = 300;
		private const CACHE_STORAGE_TTL_SECONDS = 86400;
		private const REFRESH_LOCK_TTL_SECONDS = 15;
		private const REFRESH_FAILURE_BACKOFF_SECONDS = 30;

		/**
		 * Returns the entitlement summary.
		 *
		 * @param bool $force_refresh Whether to bypass cache.
		 * @return array<string,mixed>
		 */
		public static function get_summary( bool $force_refresh = false ): array {
			$state = Npcink_Cloud_Addon_Settings::get_credential_state();
			if ( empty( $state['configured'] ) ) {
				return self::unavailable_summary(
					'not_configured',
					__( 'Configure Cloud settings before reading entitlement.', 'npcink-cloud-addon' )
				);
			}
			if ( empty( $state['verified'] ) ) {
				return self::unavailable_summary(
					'unverified',
					__( 'Entitlement is unavailable until Cloud settings verify successfully.', 'npcink-cloud-addon' )
				);
			}

			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			$cache_key = self::cache_key( $settings );
			if ( ! $force_refresh ) {
				$cached = get_transient( $cache_key );
				if ( is_array( $cached ) ) {
					$cached = self::decorate_cached_summary( $cached );
					if ( empty( $cached['stale'] ) ) {
						return $cached;
					}
				}
			}

			$client = new Npcink_Cloud_Runtime_Client( $settings );
			$result = $client->get_current_entitlement( 'trace_cloud_entitlement_' . wp_generate_uuid4() );
			if ( is_wp_error( $result ) ) {
				return self::unavailable_summary( 'unavailable', $result->get_error_message() );
			}

			$data = self::extract_data( $result );
			return self::cache_cloud_entitlement( $data, $settings );
		}

		/**
		 * Returns the cached entitlement summary without making a Cloud request.
		 *
		 * @return array<string,mixed>
		 */
		public static function get_cached_summary(): array {
			$state = Npcink_Cloud_Addon_Settings::get_credential_state();
			if ( empty( $state['configured'] ) ) {
				return self::unavailable_summary(
					'not_configured',
					__( 'Configure Cloud settings before reading entitlement.', 'npcink-cloud-addon' )
				);
			}
			if ( empty( $state['verified'] ) ) {
				return self::unavailable_summary(
					'unverified',
					__( 'Entitlement is unavailable until Cloud settings verify successfully.', 'npcink-cloud-addon' )
				);
			}

			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			$cached = get_transient( self::cache_key( $settings ) );
			if ( is_array( $cached ) ) {
				return self::decorate_cached_summary( $cached );
			}

			return self::unavailable_summary(
				'not_refreshed',
				__( 'The latest Cloud summary is not available yet. Check the connection to try again.', 'npcink-cloud-addon' )
			);
		}

		/**
		 * Forces entitlement refresh.
		 *
		 * @return array<string,mixed>
		 */
		public static function refresh( bool $respect_failure_backoff = false ): array {
			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			$cache_key = self::cache_key( $settings );
			$lock_key = $cache_key . '_refresh_lock';
			$failure_key = $cache_key . '_refresh_failure';

			if ( $respect_failure_backoff ) {
				$recent_failure = get_transient( $failure_key );
				if ( is_array( $recent_failure ) ) {
					return self::unavailable_summary(
						'unavailable',
						(string) ( $recent_failure['message'] ?? __( 'Plan and entitlement are temporarily unavailable.', 'npcink-cloud-addon' ) )
					);
				}
			}

			if ( ! self::acquire_refresh_lock( $lock_key ) ) {
				return self::unavailable_summary(
					'refreshing',
					__( 'Plan and entitlement are already being refreshed.', 'npcink-cloud-addon' )
				);
			}

			try {
				$summary = self::get_summary( true );
			} finally {
				delete_option( $lock_key );
			}

			if ( empty( $summary['available'] ) ) {
				set_transient(
					$failure_key,
					array(
						'message' => __( 'Plan and entitlement are temporarily unavailable.', 'npcink-cloud-addon' ),
					),
					self::REFRESH_FAILURE_BACKOFF_SECONDS
				);
			} else {
				delete_transient( $failure_key );
			}

			return $summary;
		}

		/**
		 * Caches a Cloud entitlement response already read by a signed verification request.
		 *
		 * @param array<string,mixed> $response Cloud response envelope or data.
		 * @param array<string,mixed> $settings Settings used to build the cache key.
		 * @return array<string,mixed>
		 */
		public static function cache_summary_from_response( array $response, array $settings = array() ): array {
			$settings = empty( $settings ) ? Npcink_Cloud_Addon_Settings::get_settings() : $settings;
			$data = self::extract_data( $response );

			return self::cache_cloud_entitlement( $data, $settings );
		}

		/**
		 * Deletes the cached entitlement projection for a settings payload.
		 *
		 * @param array<string,mixed> $settings Settings used to build the cache key.
		 * @return void
		 */
		public static function delete_cached_summary( array $settings = array() ): void {
			$settings = empty( $settings ) ? Npcink_Cloud_Addon_Settings::get_settings() : $settings;
			delete_transient( self::cache_key( $settings ) );
		}

		/**
		 * Extracts data from common Cloud envelopes.
		 *
		 * @param array<string,mixed> $response Cloud response.
		 * @return array<string,mixed>
		 */
		private static function extract_data( array $response ): array {
			if ( is_array( $response['data'] ?? null ) ) {
				return $response['data'];
			}

			return $response;
		}

		/**
		 * Builds a transient key without including secrets.
		 *
		 * @param array<string,mixed> $settings Settings.
		 * @return string
		 */
		private static function cache_key( array $settings ): string {
			$seed = implode(
				'|',
				array(
					(string) ( $settings['base_url'] ?? '' ),
					(string) ( $settings['site_id'] ?? '' ),
					(string) ( $settings['key_id'] ?? '' ),
				)
			);

			return 'npcink_cloud_entitlement_' . md5( $seed );
		}

		/**
		 * Marks a retained entitlement projection as fresh or stale.
		 *
		 * @param array<string,mixed> $cached Cached entitlement summary.
		 * @return array<string,mixed>
		 */
		private static function decorate_cached_summary( array $cached ): array {
			$fresh_until = strtotime( (string) ( $cached['fresh_until'] ?? '' ) );
			$is_stale = false === $fresh_until || $fresh_until <= time();
			$cached['state'] = $is_stale ? 'stale' : 'cached';
			$cached['available'] = true;
			$cached['stale'] = $is_stale;

			return $cached;
		}

		/**
		 * Acquires one short database-backed refresh lock across PHP requests.
		 *
		 * @param string $lock_key Refresh lock option key.
		 * @return bool
		 */
		private static function acquire_refresh_lock( string $lock_key ): bool {
			$locked_at = absint( get_option( $lock_key, 0 ) );
			if ( $locked_at > 0 && $locked_at + self::REFRESH_LOCK_TTL_SECONDS > time() ) {
				return false;
			}

			if ( $locked_at > 0 ) {
				delete_option( $lock_key );
			}

			return add_option( $lock_key, time(), '', false );
		}

		/**
		 * Normalizes Cloud entitlement for local display.
		 *
		 * @param array<string,mixed> $data Cloud response data.
		 * @param array<string,mixed> $settings Addon settings.
		 * @return array<string,mixed>
		 */
		private static function normalize_cloud_entitlement( array $data, array $settings ): array {
			$entitlement = is_array( $data['entitlement'] ?? null ) ? $data['entitlement'] : array();
			$period = is_array( $data['period'] ?? null ) ? $data['period'] : array();
			$quota = is_array( $entitlement['hosted_runtime_quota'] ?? null ) ? $entitlement['hosted_runtime_quota'] : array();
			$usage_limits = is_array( $entitlement['usage_limits'] ?? null ) ? $entitlement['usage_limits'] : array();
			$pro_cloud_runtime = is_array( $entitlement['pro_cloud_runtime'] ?? null ) ? $entitlement['pro_cloud_runtime'] : array();
			$quota_summary = is_array( $data['quota_summary'] ?? null ) ? $data['quota_summary'] : array();
			$ai_credit_usage_detail = self::normalize_ai_credit_usage_detail( $quota_summary['ai_credit_usage_detail'] ?? array() );

			return array(
				'state' => 'fresh',
				'available' => true,
				'stale' => false,
				'message' => __( 'Entitlement summary synced.', 'npcink-cloud-addon' ),
				'contract_version' => sanitize_text_field( (string) ( $data['contract_version'] ?? '' ) ),
				'package_label' => sanitize_text_field( (string) ( $data['package'] ?? $data['package_label'] ?? '' ) ),
				'package_tier' => sanitize_key( (string) ( $data['package_tier'] ?? $entitlement['package_tier'] ?? '' ) ),
				'entitlement_status' => sanitize_key( (string) ( $data['status'] ?? $entitlement['status'] ?? '' ) ),
				'renews_at' => sanitize_text_field( (string) ( $period['end_at'] ?? $entitlement['renews_at'] ?? '' ) ),
				'usage_limits' => self::normalize_usage_limits( $usage_limits ),
				'hosted_runtime_quota' => array(
					'max_active_runs' => absint( $quota['max_active_runs'] ?? 0 ),
					'max_batch_items' => absint( $quota['max_batch_items'] ?? 0 ),
					'execution_tiers' => self::sanitize_string_list( $quota['execution_tiers'] ?? array() ),
				),
				'pro_cloud_runtime' => self::normalize_pro_cloud_runtime( $pro_cloud_runtime ),
				'ai_credit_usage_detail' => $ai_credit_usage_detail,
				'links' => self::build_portal_links( $settings, is_array( $ai_credit_usage_detail['portal_paths'] ?? null ) ? $ai_credit_usage_detail['portal_paths'] : array() ),
				'synced_at' => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
				'fresh_until' => gmdate( 'Y-m-d H:i:s', time() + self::FRESHNESS_TTL_SECONDS ) . ' UTC',
			);
		}

		/**
		 * Normalizes and caches a Cloud entitlement data payload.
		 *
		 * @param array<string,mixed> $data Cloud entitlement data.
		 * @param array<string,mixed> $settings Addon settings.
		 * @return array<string,mixed>
		 */
		private static function cache_cloud_entitlement( array $data, array $settings ): array {
			$settings = Npcink_Cloud_Addon_Settings::normalize_settings( $settings );
			$summary = self::normalize_cloud_entitlement( $data, $settings );
			set_transient( self::cache_key( $settings ), $summary, self::CACHE_STORAGE_TTL_SECONDS );

			return $summary;
		}

		/**
		 * Normalizes Cloud-owned AI credit usage detail for summary-only local display.
		 *
		 * The addon intentionally drops recent_items and ledger detail. The Cloud portal
		 * remains the owner of usage explanation, credit ledger, and billing history.
		 *
		 * @param mixed $detail Raw AI credit usage detail.
		 * @return array<string,mixed>
		 */
		private static function normalize_ai_credit_usage_detail( $detail ): array {
			$detail = is_array( $detail ) ? $detail : array();
			$summary = is_array( $detail['summary'] ?? null ) ? $detail['summary'] : array();
			$period = is_array( $detail['period'] ?? null ) ? $detail['period'] : array();
			$portal_paths = is_array( $detail['portal_paths'] ?? null ) ? $detail['portal_paths'] : array();

			$remaining = array_key_exists( 'remaining', $summary ) && null !== $summary['remaining']
				? (float) $summary['remaining']
				: null;
			$unit = sanitize_key( (string) ( $summary['unit'] ?? '' ) );

			return array(
				'available' => 'ai_credits' === $unit && ( ! empty( $summary ) || ! empty( $portal_paths ) ),
				'surface' => sanitize_key( (string) ( $detail['surface'] ?? '' ) ),
				'default_visibility' => sanitize_key( (string) ( $detail['default_visibility'] ?? '' ) ),
				'local_addon_policy' => sanitize_key( (string) ( $detail['local_addon_policy'] ?? '' ) ),
				'generated_at' => sanitize_text_field( (string) ( $detail['generated_at'] ?? '' ) ),
				'period' => array(
					'start_at' => sanitize_text_field( (string) ( $period['start_at'] ?? '' ) ),
					'end_at' => sanitize_text_field( (string) ( $period['end_at'] ?? '' ) ),
				),
				'summary' => array(
					'used' => (float) ( $summary['used'] ?? 0 ),
					'limit' => (float) ( $summary['limit'] ?? 0 ),
					'remaining' => $remaining,
					'status' => sanitize_key( (string) ( $summary['status'] ?? '' ) ),
					'unit' => $unit,
					'rate_version' => sanitize_text_field( (string) ( $summary['rate_version'] ?? '' ) ),
				),
				'portal_paths' => array(
					'ai_credit_usage' => sanitize_text_field( (string) ( $portal_paths['ai_credit_usage'] ?? '' ) ),
					'ai_credit_ledger' => sanitize_text_field( (string) ( $portal_paths['ai_credit_ledger'] ?? '' ) ),
				),
			);
		}

		/**
		 * Normalizes Pro Cloud Runtime entitlement detail for local display.
		 *
		 * This is a read-only projection. It must not become a local billing ledger,
		 * quota engine, scheduler truth, or WordPress write owner.
		 *
		 * @param mixed $runtime Raw Pro Cloud Runtime payload.
		 * @return array<string,mixed>
		 */
		private static function normalize_pro_cloud_runtime( $runtime ): array {
			$runtime = is_array( $runtime ) ? $runtime : array();
			$reported = ! empty( $runtime );
			$local_truth = is_array( $runtime['local_truth'] ?? null ) ? $runtime['local_truth'] : array();
			$max_runs = absint( $runtime['max_nightly_inspection_runs_per_period'] ?? 0 );
			$used_runs = absint( $runtime['used_nightly_inspection_runs'] ?? 0 );
			$remaining_runs = array_key_exists( 'remaining_nightly_inspection_runs', $runtime )
				? absint( $runtime['remaining_nightly_inspection_runs'] )
				: ( $max_runs > 0 ? max( 0, $max_runs - $used_runs ) : 0 );

			return array(
				'reported' => $reported,
				'contract_version' => sanitize_text_field( (string) ( $runtime['contract_version'] ?? 'pro-cloud-runtime-entitlement-v1' ) ),
				'feature_id' => sanitize_key( (string) ( $runtime['feature_id'] ?? 'nightly_site_inspection' ) ),
				'execution_pattern' => sanitize_key( (string) ( $runtime['execution_pattern'] ?? 'whole_run_offload' ) ),
				'meter_key' => sanitize_key( (string) ( $runtime['meter_key'] ?? 'nightly_site_inspection_runs' ) ),
				'limit_enforced' => ! empty( $runtime['limit_enforced'] ),
				'max_nightly_inspection_runs_per_period' => $max_runs,
				'used_nightly_inspection_runs' => $used_runs,
				'remaining_nightly_inspection_runs' => $remaining_runs,
				'quota_exhausted' => self::normalize_runtime_boolean( $runtime['quota_exhausted'] ?? false ) || ( $max_runs > 0 && $used_runs >= $max_runs ),
				'max_batch_items' => self::normalize_optional_absint( $runtime, 'max_batch_items' ),
				'result_retention_days' => self::normalize_optional_absint( $runtime, 'result_retention_days' ),
				'payload_modes' => self::sanitize_string_list( $runtime['payload_modes'] ?? array() ),
				'cloud_role' => sanitize_key( (string) ( $runtime['cloud_role'] ?? 'runtime_detail' ) ),
				'local_truth' => array(
					'schedule_owner' => sanitize_text_field( (string) ( $local_truth['schedule_owner'] ?? 'npcink-local-automation-runtime' ) ),
					'runtime_owner' => sanitize_text_field( (string) ( $local_truth['runtime_owner'] ?? 'npcink-local-automation-runtime' ) ),
					'final_write_path' => sanitize_key( (string) ( $local_truth['final_write_path'] ?? 'core_proposal_required' ) ),
					'direct_wordpress_write' => false,
				),
				'contract_reuse' => array(
					'cloud_role' => 'runtime_detail',
					'toolbox_role' => 'product_surface',
					'core_role' => 'proposal_handoff',
					'adapter_role' => 'execution_profiles',
					'toolkit_role' => 'ability_contracts',
					'addon_role' => 'signed_transport',
					'adds_registry' => false,
					'adds_scheduler_truth' => false,
					'adds_approval_store' => false,
					'adds_queue' => false,
					'adds_write_executor' => false,
				),
				'local_billing_truth' => false,
			);
		}

		/**
		 * Normalizes an optional positive integer projection.
		 *
		 * @param array<string,mixed> $source Source payload.
		 * @param string              $key Field key.
		 * @return int|null
		 */
		private static function normalize_optional_absint( array $source, string $key ): ?int {
			if ( ! array_key_exists( $key, $source ) || ! is_numeric( $source[ $key ] ) ) {
				return null;
			}

			return absint( $source[ $key ] );
		}

		/**
		 * Normalizes explicit boolean-like Cloud fields without treating "false" as true.
		 *
		 * @param mixed $value Raw boolean projection.
		 * @return bool
		 */
		private static function normalize_runtime_boolean( $value ): bool {
			if ( is_bool( $value ) ) {
				return $value;
			}
			if ( is_int( $value ) || is_float( $value ) ) {
				return 1 === (int) $value;
			}
			if ( is_string( $value ) ) {
				return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
			}

			return false;
		}

		/**
		 * Normalizes usage limits.
		 *
		 * @param mixed $usage_limits Raw usage limits.
		 * @return array<string,mixed>
		 */
		private static function normalize_usage_limits( $usage_limits ): array {
			$usage_limits = is_array( $usage_limits ) ? $usage_limits : array();

			return array(
				'period' => sanitize_key( (string) ( $usage_limits['period'] ?? '' ) ),
				'max_runs' => (float) ( $usage_limits['max_runs'] ?? 0 ),
				'max_tokens' => (float) ( $usage_limits['max_tokens'] ?? 0 ),
				'max_cost_usd' => (float) ( $usage_limits['max_cost_usd'] ?? 0 ),
				'max_sites' => absint( $usage_limits['max_sites'] ?? 0 ),
			);
		}

		/**
		 * Builds bounded Cloud detail links.
		 *
		 * @param array<string,mixed> $settings Addon settings.
		 * @return array<string,string>
		 */
		private static function build_portal_links( array $settings, array $portal_paths = array() ): array {
			$base_url = untrailingslashit( esc_url_raw( (string) ( $settings['base_url'] ?? '' ) ) );
			if ( '' === $base_url ) {
				return array();
			}

			$links = array(
				'billing_url' => esc_url_raw( $base_url . '/portal/billing' ),
			);
			$ai_credit_usage_path = sanitize_text_field( (string) ( $portal_paths['ai_credit_usage'] ?? '' ) );
			$ai_credit_ledger_path = sanitize_text_field( (string) ( $portal_paths['ai_credit_ledger'] ?? '' ) );
			if ( '' !== $ai_credit_usage_path ) {
				$links['ai_credit_usage_url'] = self::build_portal_url( $base_url, $ai_credit_usage_path );
			}
			if ( '' !== $ai_credit_ledger_path ) {
				$links['ai_credit_ledger_url'] = self::build_portal_url( $base_url, $ai_credit_ledger_path );
			}

			return $links;
		}

		/**
		 * Builds an absolute Cloud portal URL from a base URL and path.
		 *
		 * @param string $base_url Cloud base URL.
		 * @param string $path Relative or absolute portal path.
		 * @return string
		 */
		private static function build_portal_url( string $base_url, string $path ): string {
			$path = trim( $path );
			if ( '' === $path ) {
				$path = '/portal/usage';
			}
			if ( preg_match( '#^https?://#i', $path ) ) {
				return esc_url_raw( $path );
			}

			return esc_url_raw( $base_url . '/' . ltrim( $path, '/' ) );
		}

		/**
		 * Sanitizes a string list.
		 *
		 * @param mixed $items Raw list.
		 * @return array<int,string>
		 */
		private static function sanitize_string_list( $items ): array {
			$items = is_array( $items ) ? $items : array();
			$normalized = array();
			foreach ( $items as $item ) {
				$value = sanitize_text_field( (string) $item );
				if ( '' !== $value ) {
					$normalized[] = $value;
				}
			}

			return array_values( array_unique( $normalized ) );
		}

		/**
		 * Builds an unavailable summary.
		 *
		 * @param string $state State code.
		 * @param string $message Operator message.
		 * @return array<string,mixed>
		 */
		private static function unavailable_summary( string $state, string $message ): array {
			return array(
				'state' => sanitize_key( $state ),
				'available' => false,
				'stale' => false,
				'message' => sanitize_text_field( $message ),
				'contract_version' => '',
				'package_label' => '',
				'package_tier' => '',
				'entitlement_status' => '',
				'renews_at' => '',
				'usage_limits' => array(),
				'hosted_runtime_quota' => array(),
				'pro_cloud_runtime' => self::normalize_pro_cloud_runtime( array() ),
				'ai_credit_usage_detail' => self::normalize_ai_credit_usage_detail( array() ),
				'links' => array(),
				'synced_at' => '',
				'fresh_until' => '',
			);
		}
	}
}
