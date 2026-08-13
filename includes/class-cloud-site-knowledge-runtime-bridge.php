<?php
/**
 * Site Knowledge runtime bridge for Toolbox.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'Npcink_Cloud_Site_Knowledge_Runtime_Bridge' ) ) {
	/**
	 * Validates and forwards bounded Toolbox Site Knowledge runtime requests.
	 */
	final class Npcink_Cloud_Site_Knowledge_Runtime_Bridge {
		private const MAX_RUNTIME_PAYLOAD_BYTES = 900000;
		private const STATUS_FRESHNESS_TTL_SECONDS = 300;
		private const STATUS_CACHE_TTL_SECONDS = 86400;
		private const STATUS_REFRESH_LOCK_TTL_SECONDS = 15;
		private const ALLOWED_CONTRACTS = array(
			'npcink-cloud/site-knowledge-search' => 'site_knowledge_search.v1',
			'npcink-cloud/site-knowledge-status' => 'site_knowledge_status.v1',
			'npcink-cloud/site-knowledge-sync'   => 'site_knowledge_sync.v1',
		);
		private const ALLOWED_EXECUTION_PATTERNS = array(
			'npcink-cloud/site-knowledge-search' => array( 'inline' ),
			'npcink-cloud/site-knowledge-status' => array( 'inline' ),
			'npcink-cloud/site-knowledge-sync'   => array( 'whole_run_offload' ),
		);
		private const FORBIDDEN_KEYS = array(
			'api_key',
			'authorization',
			'cookie',
			'credentials',
			'nonce',
			'password',
			'secret',
			'token',
			'x_magick_signature',
			'x_npcink_signature',
		);

		/**
		 * Registers the Toolbox Site Knowledge Cloud request filter.
		 *
		 * @return void
		 */
		public static function register(): void {
			add_filter( 'npcink_toolbox_site_knowledge_cloud_request', array( __CLASS__, 'handle_toolbox_request' ), 10, 4 );
		}

		/**
		 * Handles Toolbox Site Knowledge Cloud requests when this addon can do so.
		 *
		 * @param mixed               $handled Existing filter result.
		 * @param array<string,mixed> $runtime_payload Toolbox runtime payload.
		 * @param string              $ability_name Ability name.
		 * @param string              $contract_version Contract version.
		 * @return mixed
		 */
		public static function handle_toolbox_request( $handled, array $runtime_payload, string $ability_name, string $contract_version ) {
			if ( null !== $handled || ! self::is_site_knowledge_request( $ability_name, $contract_version ) ) {
				return $handled;
			}

			if ( ! Npcink_Cloud_Addon_Settings::is_configured() ) {
				return null;
			}

			return self::dispatch_runtime( $runtime_payload, $ability_name, $contract_version );
		}

		/**
		 * Dispatches a bounded Site Knowledge runtime payload.
		 *
		 * This is transport only. Cloud owns Site Knowledge vector/index
		 * lifecycle, while the local WordPress host owns review and writes.
		 *
		 * @param array<string,mixed> $runtime_payload Runtime execute payload.
		 * @param string              $ability_name Optional ability name.
		 * @param string              $contract_version Optional contract version.
		 * @return array<string,mixed>|WP_Error
		 */
		public static function dispatch_runtime( array $runtime_payload, string $ability_name = '', string $contract_version = '' ) {
			$ability_name     = '' !== $ability_name ? $ability_name : sanitize_text_field( (string) ( $runtime_payload['ability_name'] ?? '' ) );
			$contract_version = '' !== $contract_version ? $contract_version : sanitize_text_field( (string) ( $runtime_payload['contract_version'] ?? '' ) );

			$payload = self::normalize_runtime_payload( $runtime_payload, $ability_name, $contract_version );
			if ( is_wp_error( $payload ) ) {
				return $payload;
			}

			$client = Npcink_Cloud_Addon_Settings::is_configured()
				? new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() )
				: null;
			if ( ! $client ) {
				return new WP_Error(
					'cloud_site_knowledge_runtime_unconfigured',
					__( 'Npcink Cloud is not configured.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$operation_id = wp_generate_uuid4();
			$trace_id = 'trace_site_knowledge_toolbox_' . $operation_id;
			$idempotency_key = 'site_knowledge_' . str_replace( '.', '_', $contract_version ) . '_' . $operation_id;

			$result = $client->execute_runtime( $payload, $trace_id, $idempotency_key );
			if ( is_wp_error( $result ) || ! is_array( $result ) ) {
				return $result;
			}

			return self::attach_cloud_boundary_projection( $result, $contract_version );
		}

		/**
		 * Returns the retained Cloud-owned Site Knowledge status projection.
		 *
		 * This never makes a Cloud request during page rendering.
		 *
		 * @return array<string,mixed>
		 */
		public static function get_cached_status_summary(): array {
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return self::unavailable_status_summary( 'unverified' );
			}
			if ( ! Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled() ) {
				return self::unavailable_status_summary( 'disabled' );
			}

			$cached = get_transient( self::status_cache_key() );
			if ( ! is_array( $cached ) ) {
				return self::unavailable_status_summary( 'not_refreshed' );
			}

			$fresh_until = strtotime( (string) ( $cached['fresh_until'] ?? '' ) );
			$cached['available'] = true;
			$cached['stale'] = false === $fresh_until || $fresh_until <= time();
			$cached['state'] = ! empty( $cached['stale'] ) ? 'stale' : 'cached';

			return $cached;
		}

		/**
		 * Refreshes one bounded, read-only Site Knowledge quota projection.
		 *
		 * @return array<string,mixed>
		 */
		public static function refresh_status_summary(): array {
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() ) {
				return self::unavailable_status_summary( 'unverified' );
			}
			if ( ! Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled() ) {
				return self::unavailable_status_summary( 'disabled' );
			}

			$lock_key = self::status_cache_key() . '_lock';
			$locked_at = absint( get_option( $lock_key, 0 ) );
			if ( $locked_at > 0 && $locked_at + self::STATUS_REFRESH_LOCK_TTL_SECONDS > time() ) {
				return self::unavailable_status_summary( 'refreshing' );
			}
			if ( $locked_at > 0 ) {
				delete_option( $lock_key );
			}
			if ( ! add_option( $lock_key, time(), '', false ) ) {
				return self::unavailable_status_summary( 'refreshing' );
			}

			$result = null;
			try {
				$result = self::dispatch_runtime(
					array(
						'ability_name' => 'npcink-cloud/site-knowledge-status',
						'contract_version' => 'site_knowledge_status.v1',
						'execution_pattern' => 'inline',
						'input' => array(
							'contract_version' => 'site_knowledge_status.v1',
							'include_coverage' => false,
							'write_posture' => 'suggestion_only',
							'direct_wordpress_write' => false,
						),
						'data_classification' => 'public_site_content',
						'storage_mode' => 'result_only',
						'retention_ttl' => DAY_IN_SECONDS,
						'timeout_seconds' => 20,
						'retry_max' => 0,
						'policy' => array( 'allow_fallback' => true ),
					),
					'npcink-cloud/site-knowledge-status',
					'site_knowledge_status.v1'
				);
			} finally {
				delete_option( $lock_key );
			}

			if ( is_wp_error( $result ) || ! is_array( $result ) ) {
				return self::unavailable_status_summary( 'unavailable' );
			}

			$summary = self::normalize_status_summary( $result );
			if ( empty( $summary['available'] ) ) {
				return $summary;
			}

			set_transient( self::status_cache_key(), $summary, self::STATUS_CACHE_TTL_SECONDS );
			if ( function_exists( 'do_action' ) ) {
				do_action( 'npcink_cloud_site_knowledge_status_refreshed', $summary );
			}

			return $summary;
		}

		/**
		 * Normalizes the Cloud status response to bounded numeric quota fields.
		 *
		 * @param array<string,mixed> $result Cloud runtime result.
		 * @return array<string,mixed>
		 */
		private static function normalize_status_summary( array $result ): array {
			$source = self::runtime_result_payload( $result );
			$coverage = is_array( $source['coverage'] ?? null ) ? $source['coverage'] : array();
			$quota = is_array( $coverage['quota'] ?? null ) ? $coverage['quota'] : array();
			$max_documents = absint( $quota['max_indexed_documents_per_site'] ?? 0 );
			$indexed_documents = absint( $quota['indexed_documents'] ?? ( $coverage['indexed_posts'] ?? 0 ) );
			if ( $max_documents < 1 ) {
				return self::unavailable_status_summary( 'not_returned' );
			}

			$document_utilization = is_numeric( $quota['document_utilization'] ?? null )
				? (float) $quota['document_utilization']
				: $indexed_documents / $max_documents;
			$maintenance_source = is_array( $source['maintenance'] ?? null ) ? $source['maintenance'] : array();
			$maintenance_status = sanitize_key( (string) ( $maintenance_source['status'] ?? 'not_required' ) );
			$maintenance_action = sanitize_key( (string) ( $maintenance_source['action'] ?? 'none' ) );
			$maintenance = array(
				'contract_version' => sanitize_text_field( (string) ( $maintenance_source['contract_version'] ?? '' ) ),
				'status' => in_array( $maintenance_status, array( 'not_required', 'awaiting_site', 'delivering', 'blocked' ), true ) ? $maintenance_status : 'not_required',
				'action' => 'full_sync' === $maintenance_action ? 'full_sync' : 'none',
				'automatic' => ! empty( $maintenance_source['automatic'] ),
				'request_id' => sanitize_key( (string) ( $maintenance_source['request_id'] ?? '' ) ),
				'target_embedding_space_id' => sanitize_text_field( (string) ( $maintenance_source['target_embedding_space_id'] ?? '' ) ),
				'completed_batches' => absint( $maintenance_source['completed_batches'] ?? 0 ),
				'total_batches' => absint( $maintenance_source['total_batches'] ?? 0 ),
				'last_error_code' => sanitize_key( (string) ( $maintenance_source['last_error_code'] ?? '' ) ),
			);
			$acceptance_source = is_array( $source['retrieval_acceptance'] ?? null ) ? $source['retrieval_acceptance'] : array();
			$acceptance_status = sanitize_key( (string) ( $acceptance_source['status'] ?? 'pending' ) );
			$retrieval_acceptance = array(
				'status' => in_array( $acceptance_status, array( 'pending', 'passed', 'no_hit', 'failed', 'not_applicable' ), true ) ? $acceptance_status : 'pending',
				'automatic' => ! empty( $acceptance_source['automatic'] ),
				'reason' => sanitize_key( (string) ( $acceptance_source['reason'] ?? '' ) ),
				'index_revision' => sanitize_text_field( (string) ( $acceptance_source['index_revision'] ?? '' ) ),
				'verified_at' => sanitize_text_field( (string) ( $acceptance_source['verified_at'] ?? '' ) ),
				'result_count' => absint( $acceptance_source['result_count'] ?? 0 ),
				'error_code' => sanitize_key( (string) ( $acceptance_source['error_code'] ?? '' ) ),
			);

			return array(
				'state' => 'fresh',
				'available' => true,
				'stale' => false,
				'status' => sanitize_key( (string) ( $source['status'] ?? $quota['status'] ?? '' ) ),
				'quota_status' => sanitize_key( (string) ( $quota['status'] ?? '' ) ),
				'indexed_documents' => $indexed_documents,
				'max_documents' => $max_documents,
				'remaining_documents' => max( 0, $max_documents - $indexed_documents ),
				'document_percent' => (int) round( max( 0, min( 100, $document_utilization * 100 ) ) ),
				'indexed_chunks' => absint( $quota['indexed_chunks'] ?? ( $coverage['indexed_chunks'] ?? 0 ) ),
				'max_chunks' => absint( $quota['max_indexed_chunks_per_site'] ?? 0 ),
				'max_sync_documents' => absint( $quota['max_sync_documents_per_run'] ?? 0 ),
				'max_sync_chunks' => absint( $quota['max_sync_chunks_per_run'] ?? 0 ),
				'truncated_documents' => absint( $coverage['truncated_documents'] ?? 0 ),
				'skipped_documents' => absint( $quota['skipped_documents'] ?? 0 ),
				'skipped_due_to_quota' => absint( $quota['skipped_due_to_quota'] ?? 0 ),
				'last_sync_at' => sanitize_text_field( (string) ( $coverage['last_sync_at'] ?? '' ) ),
				'warning_ratio' => is_numeric( $quota['warning_ratio'] ?? null ) ? (float) $quota['warning_ratio'] : 0.85,
				'maintenance' => $maintenance,
				'retrieval_acceptance' => $retrieval_acceptance,
				'synced_at' => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
				'fresh_until' => gmdate( 'Y-m-d H:i:s', time() + self::STATUS_FRESHNESS_TTL_SECONDS ) . ' UTC',
			);
		}

		/**
		 * Returns an unavailable status summary without exposing Cloud errors.
		 *
		 * @param string $state Bounded state.
		 * @return array<string,mixed>
		 */
		private static function unavailable_status_summary( string $state ): array {
			return array(
				'state' => sanitize_key( $state ),
				'available' => false,
				'stale' => false,
			);
		}

		/**
		 * Builds a credential-scoped cache key without including the secret.
		 *
		 * @return string
		 */
		private static function status_cache_key(): string {
			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			$seed = implode(
				'|',
				array(
					(string) ( $settings['base_url'] ?? '' ),
					(string) ( $settings['site_id'] ?? '' ),
					(string) ( $settings['key_id'] ?? '' ),
				)
			);

			return 'npcink_cloud_site_knowledge_status_' . md5( $seed );
		}

		/**
		 * Returns whether an ability/contract pair belongs to Site Knowledge.
		 *
		 * @param string $ability_name Ability name.
		 * @param string $contract_version Contract version.
		 * @return bool
		 */
		private static function is_site_knowledge_request( string $ability_name, string $contract_version ): bool {
			return isset( self::ALLOWED_CONTRACTS[ $ability_name ] )
				&& self::ALLOWED_CONTRACTS[ $ability_name ] === $contract_version;
		}

		/**
		 * Normalizes and validates a Site Knowledge runtime execute payload.
		 *
		 * @param array<string,mixed> $payload Runtime payload.
		 * @param string              $ability_name Ability name.
		 * @param string              $contract_version Contract version.
		 * @return array<string,mixed>|WP_Error
		 */
		private static function normalize_runtime_payload( array $payload, string $ability_name, string $contract_version ) {
			if ( ! isset( self::ALLOWED_CONTRACTS[ $ability_name ] ) || self::ALLOWED_CONTRACTS[ $ability_name ] !== $contract_version ) {
				return new WP_Error(
					'cloud_site_knowledge_contract_not_allowed',
					__( 'Site Knowledge runtime requests require a supported ability and contract pair.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$execution_pattern = sanitize_key( (string) ( $payload['execution_pattern'] ?? '' ) );
			if ( ! in_array( $execution_pattern, self::ALLOWED_EXECUTION_PATTERNS[ $ability_name ], true ) ) {
				return new WP_Error(
					'cloud_site_knowledge_execution_pattern_not_allowed',
					__( 'Site Knowledge runtime requests require the expected execution pattern.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$input = is_array( $payload['input'] ?? null ) ? $payload['input'] : array();
			if (
				$contract_version !== (string) ( $input['contract_version'] ?? '' )
				|| 'suggestion_only' !== (string) ( $input['write_posture'] ?? '' )
				|| true === (bool) ( $input['direct_wordpress_write'] ?? false )
			) {
				return new WP_Error(
					'cloud_site_knowledge_request_invalid',
					__( 'Site Knowledge runtime requests must be suggestion-only and no-write.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			if (
				'npcink-cloud/site-knowledge-sync' === $ability_name
				&& 'refresh' !== sanitize_key( (string) ( $input['sync_mode'] ?? '' ) )
			) {
				return new WP_Error(
					'cloud_site_knowledge_sync_mode_not_allowed',
					__( 'Site Knowledge sync transport only accepts public refresh requests. Rebuild, delete, and collection lifecycle operations belong in Cloud Site Knowledge.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}

			$forbidden_key = self::find_forbidden_key( $payload );
			if ( '' !== $forbidden_key ) {
				return new WP_Error(
					'cloud_site_knowledge_sensitive_key_not_allowed',
					__( 'Site Knowledge runtime requests must not include credentials or signing fields.', 'npcink-cloud-addon' ),
					array(
						'status' => 400,
						'key'    => $forbidden_key,
					)
				);
			}

			$payload['ability_name']        = $ability_name;
			$payload['contract_version']    = $contract_version;
			$payload['data_classification'] = 'public_site_content';
			$payload['storage_mode']        = 'result_only';
			$payload['input']               = $input;

			$encoded = wp_json_encode( $payload );
			if ( ! is_string( $encoded ) || '' === $encoded ) {
				return new WP_Error(
					'cloud_site_knowledge_payload_encode_failed',
					__( 'Site Knowledge runtime payload could not be encoded.', 'npcink-cloud-addon' ),
					array( 'status' => 400 )
				);
			}
			if ( strlen( $encoded ) > self::MAX_RUNTIME_PAYLOAD_BYTES ) {
				return new WP_Error(
					'cloud_site_knowledge_payload_too_large',
					__( 'Site Knowledge runtime payload exceeds the transport size limit.', 'npcink-cloud-addon' ),
					array( 'status' => 413 )
				);
			}

			return $payload;
		}

		/**
		 * Adds a stable Site Knowledge Cloud boundary projection when Cloud returns it.
		 *
		 * @param array<string,mixed> $result Cloud runtime result.
		 * @param string              $contract_version Expected Site Knowledge contract.
		 * @return array<string,mixed>
		 */
		private static function attach_cloud_boundary_projection( array $result, string $contract_version ): array {
			$source = self::runtime_result_payload( $result );

			$ownership = self::normalize_ownership_map( is_array( $source['ownership'] ?? null ) ? $source['ownership'] : array() );
			$truth_boundaries = self::normalize_truth_boundaries( is_array( $source['truth_boundaries'] ?? null ) ? $source['truth_boundaries'] : array() );
			if ( array() === $ownership && array() === $truth_boundaries ) {
				return $result;
			}

			$result['site_knowledge_cloud_boundary'] = array(
				'contract_version' => sanitize_text_field( (string) ( $source['contract_version'] ?? $contract_version ) ),
				'ownership' => $ownership,
				'truth_boundaries' => $truth_boundaries,
			);

			return $result;
		}

		/**
		 * Extracts the Site Knowledge payload from current and legacy runtime shapes.
		 *
		 * @param array<string,mixed> $result Cloud runtime response.
		 * @return array<string,mixed>
		 */
		private static function runtime_result_payload( array $result ): array {
			if ( is_array( $result['data']['result'] ?? null ) ) {
				return $result['data']['result'];
			}
			if ( is_array( $result['result'] ?? null ) ) {
				return $result['result'];
			}
			if ( is_array( $result['data'] ?? null ) ) {
				return $result['data'];
			}

			return $result;
		}

		/**
		 * Normalizes the Cloud-returned Site Knowledge ownership map.
		 *
		 * @param array<string,mixed> $ownership Raw ownership map.
		 * @return array<string,string>
		 */
		private static function normalize_ownership_map( array $ownership ): array {
			$allowed_keys = array(
				'source_content_owner',
				'delivery_bridge_owner',
				'index_execution_owner',
				'index_lifecycle_owner',
				'freshness_policy_owner',
				'diagnostics_detail_owner',
				'vector_storage_owner',
				'embedding_execution_owner',
				'approval_owner',
				'final_write_owner',
				'wordpress_write_owner',
			);
			$normalized = array();

			foreach ( $allowed_keys as $key ) {
				$value = sanitize_key( (string) ( $ownership[ $key ] ?? '' ) );
				if ( '' !== $value ) {
					$normalized[ $key ] = $value;
				}
			}

			return $normalized;
		}

		/**
		 * Normalizes the Cloud-returned Site Knowledge truth boundaries.
		 *
		 * @param array<string,mixed> $truth_boundaries Raw truth boundary map.
		 * @return array<string,bool>
		 */
		private static function normalize_truth_boundaries( array $truth_boundaries ): array {
			$allowed_keys = array(
				'cloud_is_index_truth',
				'cloud_is_freshness_truth',
				'cloud_is_diagnostics_truth',
				'cloud_is_wordpress_control_plane',
				'cloud_creates_wordpress_writes',
				'cloud_owns_local_approval',
				'cloud_owns_ability_registry',
				'cloud_owns_workflow_registry',
			);
			$normalized = array();

			foreach ( $allowed_keys as $key ) {
				if ( array_key_exists( $key, $truth_boundaries ) ) {
					$normalized[ $key ] = self::normalize_bool( $truth_boundaries[ $key ] );
				}
			}

			return $normalized;
		}

		/**
		 * Normalizes bool-like Cloud response fields.
		 *
		 * @param mixed $value Raw value.
		 * @return bool
		 */
		private static function normalize_bool( $value ): bool {
			if ( is_bool( $value ) ) {
				return $value;
			}
			if ( is_string( $value ) ) {
				return in_array( strtolower( trim( $value ) ), array( '1', 'true', 'yes', 'on' ), true );
			}

			return (bool) $value;
		}

		/**
		 * Finds a forbidden key in a nested payload.
		 *
		 * @param mixed $value Raw value.
		 * @return string
		 */
		private static function find_forbidden_key( $value ): string {
			if ( ! is_array( $value ) ) {
				return '';
			}

			foreach ( $value as $key => $item ) {
				$normalized_key = sanitize_key( str_replace( '-', '_', (string) $key ) );
				if ( in_array( $normalized_key, self::FORBIDDEN_KEYS, true ) ) {
					return $normalized_key;
				}
				$nested = self::find_forbidden_key( $item );
				if ( '' !== $nested ) {
					return $nested;
				}
			}

			return '';
		}
	}
}
