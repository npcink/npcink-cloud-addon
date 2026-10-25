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
		private const MEDIA_EVIDENCE_IDS_CACHE_TTL_SECONDS = 604800;
		private const MAX_MEDIA_EVIDENCE_IDS = 1000;
		private const STATUS_REFRESH_LOCK_TTL_SECONDS = 15;
		private const MAX_STATUS_POST_IDS = 1000;
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
			add_filter( 'npcink_toolbox_media_fingerprint_scan_evidence_attachment_ids', array( __CLASS__, 'media_fingerprint_scan_evidence_attachment_ids' ), 10, 2 );
			add_filter( 'npcink_toolbox_cloud_addon_verified', array( __CLASS__, 'toolbox_cloud_verified' ) );
			add_filter( 'npcink_toolbox_site_knowledge_transport_enabled', array( __CLASS__, 'toolbox_site_knowledge_transport_enabled' ) );
		}

		/** Projects connection readiness without owning Toolbox scheduling. */
		public static function toolbox_cloud_verified(): bool {
			return Npcink_Cloud_Addon_Settings::is_verified();
		}

		/** Projects explicit Site Knowledge transport readiness only. */
		public static function toolbox_site_knowledge_transport_enabled(): bool {
			return Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled();
		}

		/**
		 * Returns bounded attachment IDs observed in Cloud visual-evidence
		 * projections. This is a read-only cache and never dispatches Cloud.
		 *
		 * @param mixed $ids Existing filter value.
		 * @param mixed $limit Requested maximum IDs.
		 * @return array<int,int>
		 */
		public static function media_fingerprint_scan_evidence_attachment_ids( $ids, $limit = 100 ): array {
			$limit = max( 1, min( self::MAX_MEDIA_EVIDENCE_IDS, absint( $limit ) ) );
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() || ! Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled() ) {
				return array_values( array_unique( array_filter( array_map( 'absint', (array) $ids ) ) ) );
			}
			$cached = get_transient( self::media_evidence_ids_cache_key() );
			$values = array_merge( (array) $ids, is_array( $cached ) ? $cached : array() );
			$normalized = array();
			foreach ( $values as $value ) {
				$attachment_id = absint( $value );
				if ( $attachment_id > 0 && ! in_array( $attachment_id, $normalized, true ) ) {
					$normalized[] = $attachment_id;
				}
			}

			return array_slice( $normalized, 0, $limit );
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

			$result = 'site_knowledge_sync.v1' === $contract_version
				? Npcink_Cloud_Site_Knowledge_Change_Bridge::dispatch_mutation( $payload )
				: $client->execute_runtime( $payload, $trace_id, $idempotency_key );
			if ( is_wp_error( $result ) || ! is_array( $result ) ) {
				return $result;
			}

			$result = self::attach_cloud_boundary_projection( $result, $contract_version );
			if ( 'site_knowledge_status.v1' === $contract_version ) {
				self::remember_media_evidence_attachment_ids( self::runtime_result_payload( $result ) );
			}

			return $result;
		}

		/**
		 * Retains only bounded attachment identities from a status response.
		 *
		 * @param array<string,mixed> $source Site Knowledge status payload.
		 * @return void
		 */
		private static function remember_media_evidence_attachment_ids( array $source ): void {
			if ( ! Npcink_Cloud_Addon_Settings::is_verified() || ! Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled() ) {
				return;
			}
			$items = is_array( $source['media_evidence_items'] ?? null ) ? $source['media_evidence_items'] : array();
			$observed = array();
			foreach ( $items as $item ) {
				if ( ! is_array( $item ) ) {
					continue;
				}
				$attachment_id = absint( $item['attachment_id'] ?? 0 );
				if ( $attachment_id > 0 && ! in_array( $attachment_id, $observed, true ) ) {
					$observed[] = $attachment_id;
				}
			}
			if ( empty( $observed ) ) {
				return;
			}

			$existing = get_transient( self::media_evidence_ids_cache_key() );
			$values = array_merge( is_array( $existing ) ? $existing : array(), $observed );
			$normalized = array();
			foreach ( $values as $value ) {
				$attachment_id = absint( $value );
				if ( $attachment_id > 0 && ! in_array( $attachment_id, $normalized, true ) ) {
					$normalized[] = $attachment_id;
				}
			}

			set_transient(
				self::media_evidence_ids_cache_key(),
				array_slice( $normalized, 0, self::MAX_MEDIA_EVIDENCE_IDS ),
				self::MEDIA_EVIDENCE_IDS_CACHE_TTL_SECONDS
			);
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

			$post_id_selection = self::local_public_post_ids();
			$post_ids = $post_id_selection['post_ids'];
			$result = null;
			try {
				$result = self::dispatch_runtime(
					array(
						'ability_name' => 'npcink-cloud/site-knowledge-status',
						'contract_version' => 'site_knowledge_status.v1',
						'execution_pattern' => 'inline',
						'input' => array(
							'contract_version' => 'site_knowledge_status.v1',
							'include_coverage' => true,
							'post_ids' => $post_ids,
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

			if ( is_wp_error( $result ) ) {
				$summary = self::unavailable_status_summary( 'unavailable' );
				$data = $result->get_error_data();
				$data = is_array( $data ) ? $data : array();
				$code = $data['cloud_error_code'] ?? '';
				$code = is_string( $code ) && '' !== $code ? $code : $result->get_error_code();
				$summary['error_code'] = preg_match( '/^[a-z][a-z0-9_.-]{0,95}$/D', $code ) ? $code : 'cloud_status_read_failed';
				return $summary;
			}
			if ( ! is_array( $result ) ) {
				return self::unavailable_status_summary( 'not_returned' );
			}

			$summary = self::normalize_status_summary( $result, $post_ids, $post_id_selection['has_more'] );
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
		private static function normalize_status_summary( array $result, array $compared_post_ids = array(), bool $has_more = false ): array {
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
			$compared_post_ids = array_values( array_unique( array_filter( array_map( 'absint', $compared_post_ids ) ) ) );
			$indexed_post_ids_source = $coverage['indexed_post_ids'] ?? null;
			if (
				! empty( $compared_post_ids )
				&& (
					! is_array( $indexed_post_ids_source )
					|| count( $compared_post_ids ) !== absint( $coverage['indexed_post_ids_requested'] ?? 0 )
				)
			) {
				return self::unavailable_status_summary( 'not_returned' );
			}
			$indexed_post_ids = array_values( array_intersect(
				$compared_post_ids,
				array_values( array_unique( array_filter( array_map( 'absint', is_array( $indexed_post_ids_source ) ? $indexed_post_ids_source : array() ) ) ) )
			) );

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
				'article_coverage' => array(
					'compared_post_ids' => $compared_post_ids,
					'indexed_post_ids' => $indexed_post_ids,
					'compared_count' => count( $compared_post_ids ),
					'indexed_count' => count( $indexed_post_ids ),
					'not_indexed_count' => max( 0, count( $compared_post_ids ) - count( $indexed_post_ids ) ),
					'has_more' => $has_more,
				),
				'synced_at' => gmdate( 'Y-m-d H:i:s' ) . ' UTC',
				'fresh_until' => gmdate( 'Y-m-d H:i:s', time() + self::STATUS_FRESHNESS_TTL_SECONDS ) . ' UTC',
			);
		}

		/**
		 * Builds current local display rows from one retained Cloud comparison.
		 *
		 * @param array<string,mixed> $summary Retained Site Knowledge status summary.
		 * @return array<int,array<string,mixed>>
		 */
		public static function article_index_statuses( array $summary ): array {
			$coverage = is_array( $summary['article_coverage'] ?? null ) ? $summary['article_coverage'] : array();
			$compared_post_ids = is_array( $coverage['compared_post_ids'] ?? null ) ? $coverage['compared_post_ids'] : array();
			$indexed_post_ids = array_fill_keys(
				array_values( array_unique( array_filter( array_map( 'absint', is_array( $coverage['indexed_post_ids'] ?? null ) ? $coverage['indexed_post_ids'] : array() ) ) ) ),
				true
			);
			$rows = array();

			if ( ! function_exists( 'get_post' ) ) {
				return $rows;
			}

			foreach ( array_slice( array_values( array_unique( array_filter( array_map( 'absint', $compared_post_ids ) ) ) ), 0, self::MAX_STATUS_POST_IDS ) as $post_id ) {
				$post = get_post( $post_id );
				if ( ! self::is_public_article( $post ) ) {
					continue;
				}

				$title = function_exists( 'get_the_title' ) ? (string) get_the_title( $post_id ) : (string) ( $post->post_title ?? '' );
				$url = function_exists( 'get_permalink' ) ? (string) get_permalink( $post_id ) : '';
				$rows[] = array(
					'post_id' => $post_id,
					'title' => sanitize_text_field( $title ),
					'url' => esc_url_raw( $url ),
					'modified_gmt' => sanitize_text_field( (string) ( $post->post_modified_gmt ?? '' ) ),
					'status' => isset( $indexed_post_ids[ $post_id ] ) ? 'indexed' : 'not_indexed',
				);
			}

			return $rows;
		}

		/**
		 * Selects the most recently modified public posts/pages for Cloud comparison.
		 *
		 * @return array{post_ids:array<int,int>,has_more:bool}
		 */
		private static function local_public_post_ids(): array {
			if ( ! function_exists( 'get_posts' ) ) {
				return array( 'post_ids' => array(), 'has_more' => false );
			}

			$post_ids = get_posts(
				array(
					'post_type' => array( 'post', 'page' ),
					'post_status' => 'publish',
					'posts_per_page' => self::MAX_STATUS_POST_IDS + 1,
					'orderby' => 'modified',
					'order' => 'DESC',
					'fields' => 'ids',
					'no_found_rows' => true,
				)
			);
			$post_ids = is_array( $post_ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $post_ids ) ) ) ) : array();

			return array(
				'post_ids' => array_slice( $post_ids, 0, self::MAX_STATUS_POST_IDS ),
				'has_more' => count( $post_ids ) > self::MAX_STATUS_POST_IDS,
			);
		}

		/**
		 * Returns whether a local object is a currently public article/page.
		 *
		 * @param mixed $post WordPress post object.
		 * @return bool
		 */
		private static function is_public_article( $post ): bool {
			return is_object( $post )
				&& 'publish' === sanitize_key( (string) ( $post->post_status ?? '' ) )
				&& in_array( sanitize_key( (string) ( $post->post_type ?? '' ) ), array( 'post', 'page' ), true );
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
		 * Builds the credential-scoped media evidence ID projection key.
		 *
		 * @return string
		 */
		private static function media_evidence_ids_cache_key(): string {
			return self::status_cache_key() . '_media_evidence_ids';
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

		/** Fresh, selector-complete SQL evidence; never consult the summary transient. */
		public static function read_source_presence( array $items ) {
			$items = Npcink_Cloud_Site_Knowledge_Change_Bridge::normalize_source_selection( $items );
			if ( is_wp_error( $items ) ) {
				return $items;
			}
			$result = self::dispatch_runtime( array(
				'ability_name' => 'npcink-cloud/site-knowledge-status',
				'contract_version' => 'site_knowledge_status.v1',
				'execution_pattern' => 'inline',
				'input' => array( 'contract_version' => 'site_knowledge_status.v1', 'source_items' => $items, 'write_posture' => 'suggestion_only', 'direct_wordpress_write' => false ),
				'timeout_seconds' => 20, 'retry_max' => 0,
			), 'npcink-cloud/site-knowledge-status', 'site_knowledge_status.v1' );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$source = self::runtime_result_payload( $result );
			$presence = $source['source_presence'] ?? array();
			if ( 'complete' !== ( $presence['status'] ?? '' ) || 'relational_index' !== ( $presence['storage_scope'] ?? '' ) || count( $items ) !== ( $presence['requested_count'] ?? null ) || ! is_string( $presence['observed_at'] ?? null ) || false === strtotime( $presence['observed_at'] ) || abs( time() - (int) strtotime( $presence['observed_at'] ) ) > 300 || ! is_array( $presence['items'] ?? null ) || count( $presence['items'] ) !== count( $items ) ) {
				return new WP_Error( 'cloud_site_knowledge_presence_incomplete' );
			}
			$expected = array();
			foreach ( $items as $item ) {
				$expected[ $item['source_type'] . ':' . $item['source_id'] ] = true;
			}
			$rows = array();
			foreach ( $presence['items'] as $row ) {
				if ( ! is_array( $row ) || ! is_string( $row['source_type'] ?? null ) || ! is_int( $row['source_id'] ?? null ) ) {
					return new WP_Error( 'cloud_site_knowledge_presence_incomplete' );
				}
				$key = $row['source_type'] . ':' . $row['source_id'];
				if ( ! isset( $expected[ $key ] ) || isset( $rows[ $key ] ) || ! is_bool( $row['document_present'] ?? null ) || ! is_bool( $row['public_document_present'] ?? null ) ) {
					return new WP_Error( 'cloud_site_knowledge_presence_incomplete' );
				}
				foreach ( array( 'stored_chunk_count', 'public_chunk_count', 'associated_comment_document_count', 'associated_comment_stored_chunk_count' ) as $field ) {
					if ( ! is_int( $row[ $field ] ?? null ) || $row[ $field ] < 0 ) {
						return new WP_Error( 'cloud_site_knowledge_presence_incomplete' );
					}
				}
				if ( ( $row['public_document_present'] && ! $row['document_present'] ) || $row['public_chunk_count'] > $row['stored_chunk_count'] || ( $row['public_chunk_count'] > 0 && ! $row['public_document_present'] ) || ( 'media' === $row['source_type'] && ( $row['associated_comment_document_count'] > 0 || $row['associated_comment_stored_chunk_count'] > 0 ) ) ) {
					return new WP_Error( 'cloud_site_knowledge_presence_inconsistent' );
				}
				$rows[ $key ] = array_intersect_key( $row, array_flip( array( 'source_type', 'source_id', 'document_present', 'public_document_present', 'stored_chunk_count', 'public_chunk_count', 'associated_comment_document_count', 'associated_comment_stored_chunk_count' ) ) );
			}
			return array( 'items' => $rows, 'observed_at' => $presence['observed_at'], 'comments_enabled' => is_array( $source['coverage'] ?? null ) && is_bool( $source['coverage']['comments_enabled'] ?? null ) ? $source['coverage']['comments_enabled'] : null );
		}

		/** Cloud66 executor markers plus exact identity and explicit failure provenance. */
		public static function read_writer_witness( array $entry ) {
			$id = $entry['run_id'] ?? '';
			if ( ! is_string( $id ) || ! preg_match( '/\A[A-Za-z0-9._:-]+\z/', $id ) ) {
				return new WP_Error( 'cloud_site_knowledge_writer_identity_unknown' );
			}
			$client = new Npcink_Cloud_Runtime_Client();
			$response = $client->get_run( $id, 'trace_site_knowledge_witness_' . wp_generate_uuid4() );
			if ( is_wp_error( $response ) || ! is_array( $response ) ) {
				return new WP_Error( 'cloud_site_knowledge_writer_unknown' );
			}
			$run = $response['data']['run'] ?? $response['data'] ?? $response;
			$settings = Npcink_Cloud_Addon_Settings::get_settings();
			if ( ! is_array( $run ) || $id !== ( $run['run_id'] ?? '' ) || ( $settings['site_id'] ?? '' ) !== ( $run['site_id'] ?? '' ) || 'npcink-cloud/site-knowledge-sync' !== ( $run['ability_name'] ?? '' ) || 'site_knowledge_sync.v1' !== ( $run['contract_version'] ?? '' ) || $entry['key'] !== ( $run['idempotency_key'] ?? '' ) || 'knowledge' !== ( $run['execution_kind'] ?? '' ) || 'site_knowledge' !== ( $run['provider_id'] ?? '' ) || 'site-knowledge-managed' !== ( $run['model_id'] ?? '' ) || 'cloud-runtime' !== ( $run['instance_id'] ?? '' ) || empty( $run['finished_at'] ) || false === strtotime( (string) $run['finished_at'] ) ) {
				return new WP_Error( 'cloud_site_knowledge_writer_unknown' );
			}
			$status = $run['status'] ?? '';
			$error = $run['error_code'] ?? '';
			// These are emitted only by SiteKnowledgeService contract/backend raises
			// caught after execute unwinds in Cloud66 _execute_site_knowledge_run.
			$normal_failure = in_array( $error, array(
				'site_knowledge.source_items_invalid', 'site_knowledge.source_identity_conflict',
				'site_knowledge.source_selector_conflict', 'site_knowledge.zilliz_probe_failed',
				'site_knowledge.zilliz_delete_failed', 'site_knowledge.zilliz_upsert_failed',
				'site_knowledge.embedding_provider_missing', 'site_knowledge.embedding_budget_context_missing', 'site_knowledge.embedding_invalid',
				'site_knowledge.embedding_dimension_mismatch',
			), true );
			if ( 'succeeded' === $status && '' === $error ) {
				return array( 'run_id' => $id, 'status' => $status, 'succeeded' => true );
			}
			if ( 'failed' === $status && $normal_failure ) {
				return array( 'run_id' => $id, 'status' => $status, 'succeeded' => false, 'error_code' => $error );
			}
			return new WP_Error( 'cloud_site_knowledge_writer_unknown' );
		}

		/** A terminal run may still report skipped/failed items: require every approved input. */
		public static function read_restore_result( string $run_id, array $payload ) {
			$client = new Npcink_Cloud_Runtime_Client();
			$response = $client->get_run_result( $run_id, 'trace_site_knowledge_restore_' . wp_generate_uuid4() );
			if ( is_wp_error( $response ) || ! is_array( $response ) ) {
				return new WP_Error( 'cloud_site_knowledge_restore_result_unknown' );
			}
			$data = $response['data'] ?? $response;
			$result = $data['result'] ?? array();
			$sync = $result['sync'] ?? array();
			if ( $run_id !== ( $data['run_id'] ?? '' ) || 'succeeded' !== ( $data['status'] ?? '' ) || $run_id !== ( $result['run_id'] ?? '' ) || 'site_knowledge_sync.v1' !== ( $result['contract_version'] ?? '' ) || 'completed' !== ( $result['status'] ?? '' ) || 'refresh' !== ( $sync['sync_mode'] ?? '' ) ) {
				return new WP_Error( 'cloud_site_knowledge_restore_result_unknown' );
			}
			foreach ( array( 'accepted_documents', 'indexed_documents', 'unchanged_documents', 'failed_documents', 'skipped_documents', 'skipped_due_to_quota' ) as $field ) {
				if ( ! is_int( $sync[ $field ] ?? null ) || $sync[ $field ] < 0 ) {
					return new WP_Error( 'cloud_site_knowledge_restore_result_unknown' );
				}
			}
			$input = $payload['input'];
			$expected = count( $input['documents'] ?? array() ) + count( $input['comments'] ?? array() ) + count( $input['media_items'] ?? array() );
			if ( $expected < 1 || $expected !== $sync['accepted_documents'] || $expected !== $sync['indexed_documents'] + $sync['unchanged_documents'] || $sync['failed_documents'] || $sync['skipped_documents'] || $sync['skipped_due_to_quota'] ) {
				return new WP_Error( 'cloud_site_knowledge_restore_incomplete' );
			}
			return true;
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
