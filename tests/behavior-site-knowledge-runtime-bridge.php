<?php
/**
 * Behavior tests for the Toolbox Site Knowledge runtime bridge.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

$GLOBALS['maca_runtime_posts'] = array();

if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( array $args = array() ): array {
		return array_slice( array_keys( $GLOBALS['maca_runtime_posts'] ), 0, absint( $args['posts_per_page'] ?? 1001 ) );
	}
}

if ( ! function_exists( 'get_post' ) ) {
	function get_post( int $post_id ) {
		return $GLOBALS['maca_runtime_posts'][ $post_id ] ?? null;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	function get_the_title( int $post_id ): string {
		$post = get_post( $post_id );
		return is_object( $post ) ? (string) ( $post->post_title ?? '' ) : '';
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( int $post_id ): string {
		return 'https://example.test/?p=' . absint( $post_id );
	}
}

maca_load_addon_classes();

/**
 * Returns a valid Site Knowledge runtime payload fixture.
 *
 * @return array<string,mixed>
 */
function maca_site_knowledge_runtime_payload(): array {
	return array(
		'ability_name'        => 'npcink-cloud/site-knowledge-search',
		'contract_version'    => 'site_knowledge_search.v1',
		'execution_pattern'   => 'inline',
		'input'               => array(
			'contract_version' => 'site_knowledge_search.v1',
			'query'            => 'internal links for launch checklist',
			'intent'           => 'internal_link_candidates',
			'max_results'      => 5,
			'write_posture'    => 'suggestion_only',
		),
		'data_classification' => 'public_site_content',
		'storage_mode'        => 'result_only',
		'retention_ttl'       => 86400,
		'timeout_seconds'     => 20,
		'retry_max'           => 0,
		'policy'              => array(
			'allow_fallback' => true,
		),
	);
}

/**
 * Returns a Site Knowledge sync runtime payload fixture.
 *
 * @param string $sync_mode Sync mode.
 * @return array<string,mixed>
 */
function maca_site_knowledge_sync_runtime_payload( string $sync_mode = 'refresh' ): array {
	return array(
		'ability_name'        => 'npcink-cloud/site-knowledge-sync',
		'contract_version'    => 'site_knowledge_sync.v1',
		'execution_pattern'   => 'whole_run_offload',
		'input'               => array(
			'contract_version' => 'site_knowledge_sync.v1',
			'sync_mode'        => $sync_mode,
			'post_ids'         => array( 123 ),
			'max_posts'        => 1,
			'documents'        => array(),
			'write_posture'    => 'suggestion_only',
		),
		'data_classification' => 'public_site_content',
		'storage_mode'        => 'result_only',
		'retention_ttl'       => 86400,
		'timeout_seconds'     => 60,
		'retry_max'           => 1,
		'policy'              => array(
			'allow_fallback' => true,
		),
	);
}

maca_reset_test_state();
Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register();
maca_assert(
	! empty( $GLOBALS['maca_filters']['npcink_toolbox_site_knowledge_cloud_request'] ),
	'Behavior: Site Knowledge runtime bridge registers the Toolbox Cloud request filter.'
);

maca_assert(
	isset( $GLOBALS['maca_filters']['npcink_toolbox_cloud_addon_verified'] )
	&& isset( $GLOBALS['maca_filters']['npcink_toolbox_site_knowledge_transport_enabled'] ),
	'Behavior: Addon registers read-only Toolbox readiness projections without continuation ownership.'
);

maca_reset_test_state();
Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register();
maca_assert(
	false === apply_filters( 'npcink_toolbox_cloud_addon_verified', false )
	&& false === apply_filters( 'npcink_toolbox_site_knowledge_transport_enabled', false )
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: unverified settings project disabled scan readiness without Cloud traffic.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_site_knowledge_delivery_enabled( false );
Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register();
maca_assert(
	true === apply_filters( 'npcink_toolbox_cloud_addon_verified', false )
	&& false === apply_filters( 'npcink_toolbox_site_knowledge_transport_enabled', false ),
	'Behavior: verified connection alone does not enable Site Knowledge scan readiness.'
);

maca_set_site_knowledge_delivery_enabled( true );
maca_assert(
	true === apply_filters( 'npcink_toolbox_site_knowledge_transport_enabled', false )
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: explicit Site Knowledge delivery projects readiness without owning or starting a scan.'
);

maca_reset_test_state();
Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register();
$unconfigured = apply_filters(
	'npcink_toolbox_site_knowledge_cloud_request',
	null,
	maca_site_knowledge_runtime_payload(),
	'npcink-cloud/site-knowledge-search',
	'site_knowledge_search.v1'
);
maca_assert(
	null === $unconfigured && array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge runtime bridge lets Toolbox fail closed when Cloud settings are missing.'
);

maca_reset_test_state();
maca_seed_settings( true );
Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register();
$handled = apply_filters(
	'npcink_toolbox_site_knowledge_cloud_request',
	null,
	maca_site_knowledge_runtime_payload(),
	'npcink-cloud/site-knowledge-search',
	'site_knowledge_search.v1'
);
$request = $GLOBALS['maca_http_requests'][0] ?? array();
$body = json_decode( (string) ( $request['args']['body'] ?? '' ), true );
$body = is_array( $body ) ? $body : array();
maca_assert(
	is_array( $handled )
	&& false !== strpos( (string) ( $request['url'] ?? '' ), '/v1/runtime/execute' )
	&& 'POST' === (string) ( $request['args']['method'] ?? '' )
	&& 'npcink-cloud/site-knowledge-search' === (string) ( $body['ability_name'] ?? '' )
	&& 'site_knowledge_search.v1' === (string) ( $body['contract_version'] ?? '' )
	&& 'suggestion_only' === (string) ( $body['input']['write_posture'] ?? '' ),
	'Behavior: Site Knowledge runtime bridge forwards valid Toolbox requests through signed runtime execute only.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_runtime_posts'] = array(
	101 => (object) array( 'post_status' => 'publish', 'post_type' => 'post', 'post_title' => 'Indexed article', 'post_modified_gmt' => '2026-08-20 08:00:00' ),
	102 => (object) array( 'post_status' => 'publish', 'post_type' => 'page', 'post_title' => 'Missing page', 'post_modified_gmt' => '2026-08-19 08:00:00' ),
);
$GLOBALS['maca_posts'] = $GLOBALS['maca_runtime_posts'];
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array(
				'contract_version' => 'site_knowledge_status.v1',
				'ownership' => array(
					'source_content_owner' => 'local_wordpress_host',
					'delivery_bridge_owner' => 'cloud_addon',
					'index_execution_owner' => 'cloud_service',
					'index_lifecycle_owner' => 'cloud_service',
					'freshness_policy_owner' => 'cloud_service',
					'diagnostics_detail_owner' => 'cloud_service',
					'vector_storage_owner' => 'cloud_service',
					'embedding_execution_owner' => 'cloud_service',
					'approval_owner' => 'local_wordpress_host',
					'final_write_owner' => 'local_wordpress_host',
					'wordpress_write_owner' => 'local_wordpress_host',
				),
				'truth_boundaries' => array(
					'cloud_is_index_truth' => true,
					'cloud_is_freshness_truth' => true,
					'cloud_is_diagnostics_truth' => true,
					'cloud_is_wordpress_control_plane' => false,
					'cloud_creates_wordpress_writes' => false,
					'cloud_owns_local_approval' => false,
					'cloud_owns_ability_registry' => false,
					'cloud_owns_workflow_registry' => false,
				),
			),
		)
	),
);
$status_payload = maca_site_knowledge_runtime_payload();
$status_payload['ability_name'] = 'npcink-cloud/site-knowledge-status';
$status_payload['contract_version'] = 'site_knowledge_status.v1';
$status_payload['input']['contract_version'] = 'site_knowledge_status.v1';
$status_result = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	$status_payload,
	'npcink-cloud/site-knowledge-status',
	'site_knowledge_status.v1'
);
$first_status_request = $GLOBALS['maca_http_requests'][0] ?? array();
$first_status_key = (string) ( $first_status_request['args']['headers']['Idempotency-Key'] ?? '' );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array(
				'contract_version' => 'site_knowledge_status.v1',
			),
		)
	),
);
$second_status_result = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	$status_payload,
	'npcink-cloud/site-knowledge-status',
	'site_knowledge_status.v1'
);
$second_status_request = $GLOBALS['maca_http_requests'][1] ?? array();
$second_status_key = (string) ( $second_status_request['args']['headers']['Idempotency-Key'] ?? '' );
maca_assert(
	is_array( $status_result )
	&& is_array( $second_status_result )
	&& '' !== $first_status_key
	&& '' !== $second_status_key
	&& $first_status_key !== $second_status_key
	&& 'site_knowledge_status.v1' === (string) ( $status_result['site_knowledge_cloud_boundary']['contract_version'] ?? '' )
	&& 'cloud_service' === (string) ( $status_result['site_knowledge_cloud_boundary']['ownership']['index_execution_owner'] ?? '' )
	&& 'local_wordpress_host' === (string) ( $status_result['site_knowledge_cloud_boundary']['ownership']['final_write_owner'] ?? '' )
	&& true === (bool) ( $status_result['site_knowledge_cloud_boundary']['truth_boundaries']['cloud_is_index_truth'] ?? false )
	&& false === (bool) ( $status_result['site_knowledge_cloud_boundary']['truth_boundaries']['cloud_is_wordpress_control_plane'] ?? true )
	&& false === (bool) ( $status_result['site_knowledge_cloud_boundary']['truth_boundaries']['cloud_creates_wordpress_writes'] ?? true ),
	'Behavior: Site Knowledge runtime bridge preserves read-only Cloud boundary detail and uses fresh status idempotency keys.'
);

maca_reset_test_state();
maca_seed_settings( true );
$quota_response = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array(
				'result' => array(
					'contract_version' => 'site_knowledge_status.v1',
					'maintenance' => array(
						'contract_version' => 'site_knowledge_maintenance.v1',
						'status' => 'awaiting_site',
						'action' => 'full_sync',
						'automatic' => true,
						'request_id' => 'skm_1234567890abcdef',
						'target_embedding_space_id' => 'siliconflow:BAAI/bge-m3',
					),
					'coverage' => array(
						'indexed_post_ids' => array( 101, 999999 ),
						'indexed_post_ids_requested' => 2,
						'indexed_posts' => 2340,
						'indexed_chunks' => 15200,
						'truncated_documents' => 4,
						'last_sync_at' => '2026-07-13 03:04:05 UTC',
						'quota' => array(
							'status' => 'ok',
							'indexed_documents' => 2340,
							'indexed_chunks' => 15200,
							'max_indexed_documents_per_site' => 10000,
							'max_indexed_chunks_per_site' => 60000,
							'max_sync_documents_per_run' => 500,
							'max_sync_chunks_per_run' => 4000,
							'warning_ratio' => 0.85,
							'document_utilization' => 0.234,
							'skipped_documents' => 3,
							'skipped_due_to_quota' => 1,
						),
					),
				),
			),
		)
	),
);
$GLOBALS['maca_http_response_queue'][] = $quota_response;
$quota_summary = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::refresh_status_summary();
$quota_request = $GLOBALS['maca_http_requests'][0] ?? array();
$quota_body = json_decode( (string) ( $quota_request['args']['body'] ?? '' ), true );
$quota_body = is_array( $quota_body ) ? $quota_body : array();
$request_count_before_cache_read = count( $GLOBALS['maca_http_requests'] );
$cached_quota_summary = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::get_cached_status_summary();
$article_statuses = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::article_index_statuses( $cached_quota_summary );
maca_assert(
	! empty( $quota_summary['available'] )
	&& 2340 === (int) ( $quota_summary['indexed_documents'] ?? 0 )
	&& 10000 === (int) ( $quota_summary['max_documents'] ?? 0 )
	&& 7660 === (int) ( $quota_summary['remaining_documents'] ?? 0 )
	&& 23 === (int) ( $quota_summary['document_percent'] ?? 0 )
	&& 60000 === (int) ( $quota_summary['max_chunks'] ?? 0 )
	&& 500 === (int) ( $quota_summary['max_sync_documents'] ?? 0 )
	&& 4000 === (int) ( $quota_summary['max_sync_chunks'] ?? 0 )
	&& 4 === (int) ( $quota_summary['truncated_documents'] ?? 0 )
	&& 1 === (int) ( $quota_summary['skipped_due_to_quota'] ?? 0 )
	&& 'awaiting_site' === (string) ( $quota_summary['maintenance']['status'] ?? '' )
	&& 'full_sync' === (string) ( $quota_summary['maintenance']['action'] ?? '' )
	&& true === (bool) ( $quota_summary['maintenance']['automatic'] ?? false )
	&& 'skm_1234567890abcdef' === (string) ( $quota_summary['maintenance']['request_id'] ?? '' )
	&& 'npcink-cloud/site-knowledge-status' === (string) ( $quota_body['ability_name'] ?? '' )
	&& 'site_knowledge_status.v1' === (string) ( $quota_body['contract_version'] ?? '' )
	&& 'suggestion_only' === (string) ( $quota_body['input']['write_posture'] ?? '' )
	&& false === (bool) ( $quota_body['input']['direct_wordpress_write'] ?? true )
	&& true === (bool) ( $quota_body['input']['include_coverage'] ?? false )
	&& array( 101, 102 ) === ( $quota_body['input']['post_ids'] ?? array() )
	&& 1 === (int) ( $quota_summary['article_coverage']['indexed_count'] ?? 0 )
	&& 1 === (int) ( $quota_summary['article_coverage']['not_indexed_count'] ?? 0 )
	&& 'indexed' === (string) ( $article_statuses[0]['status'] ?? '' )
	&& 'not_indexed' === (string) ( $article_statuses[1]['status'] ?? '' )
	&& ! empty( $cached_quota_summary['available'] )
	&& $request_count_before_cache_read === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: Site Knowledge quota refresh reads bounded Cloud truth, caches it, and never fetches Cloud during the cached page projection.'
);

maca_reset_test_state();
maca_seed_settings( true );
$incomplete_quota_body = json_decode( (string) $quota_response['body'], true );
$incomplete_quota_body['data']['result']['coverage']['indexed_post_ids_requested'] = 1;
$quota_response['body'] = wp_json_encode( $incomplete_quota_body );
$GLOBALS['maca_http_response_queue'][] = $quota_response;
$incomplete_quota_summary = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::refresh_status_summary();
maca_assert(
	empty( $incomplete_quota_summary['available'] )
	&& 'not_returned' === (string) ( $incomplete_quota_summary['state'] ?? '' )
	&& array() === Npcink_Cloud_Site_Knowledge_Runtime_Bridge::article_index_statuses( $incomplete_quota_summary ),
	'Behavior: Site Knowledge article coverage fails closed when Cloud does not confirm the complete local comparison manifest.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_runtime_posts'] = array();
$GLOBALS['maca_posts'] = array();
maca_set_site_knowledge_delivery_enabled( false );
$disabled_quota_summary = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::refresh_status_summary();
maca_assert(
	empty( $disabled_quota_summary['available'] )
	&& 'disabled' === (string) ( $disabled_quota_summary['state'] ?? '' )
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge quota projection fails closed without Cloud traffic when local delivery is disabled.'
);

maca_reset_test_state();
maca_seed_settings( true );
Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register();
$ignored = apply_filters(
	'npcink_toolbox_site_knowledge_cloud_request',
	null,
	maca_site_knowledge_runtime_payload(),
	'npcink-cloud/other-runtime',
	'other_runtime.v1'
);
maca_assert(
	null === $ignored && array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge runtime bridge ignores non-Site-Knowledge ability requests.'
);

maca_reset_test_state();
maca_seed_settings( true );
$invalid_payload = maca_site_knowledge_runtime_payload();
$invalid_payload['input']['write_posture'] = 'direct_write';
$invalid = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	$invalid_payload,
	'npcink-cloud/site-knowledge-search',
	'site_knowledge_search.v1'
);
maca_assert(
	is_wp_error( $invalid )
	&& 'cloud_site_knowledge_request_invalid' === $invalid->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge runtime bridge rejects non-suggestion-only payloads without forwarding.'
);

maca_reset_test_state();
maca_seed_settings( true );
$bad_contract = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	maca_site_knowledge_runtime_payload(),
	'npcink-cloud/site-knowledge-search',
	'site_knowledge_sync.v1'
);
maca_assert(
	is_wp_error( $bad_contract )
	&& 'cloud_site_knowledge_contract_not_allowed' === $bad_contract->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge runtime bridge rejects unsupported ability and contract pairs.'
);

maca_reset_test_state();
maca_seed_settings( true );
$secret_payload = maca_site_knowledge_runtime_payload();
$secret_payload['input']['credentials'] = 'should-not-forward';
$secret_result = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	$secret_payload,
	'npcink-cloud/site-knowledge-search',
	'site_knowledge_search.v1'
);
maca_assert(
	is_wp_error( $secret_result )
	&& 'cloud_site_knowledge_sensitive_key_not_allowed' === $secret_result->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge runtime bridge rejects credential-like keys before transport.'
);

maca_reset_test_state();
maca_seed_settings( true );
$rebuild_sync = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	maca_site_knowledge_sync_runtime_payload( 'rebuild' ),
	'npcink-cloud/site-knowledge-sync',
	'site_knowledge_sync.v1'
);
$delete_sync = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	maca_site_knowledge_sync_runtime_payload( 'delete' ),
	'npcink-cloud/site-knowledge-sync',
	'site_knowledge_sync.v1'
);
maca_assert(
	is_wp_error( $rebuild_sync )
	&& 'cloud_site_knowledge_sync_mode_not_allowed' === $rebuild_sync->get_error_code()
	&& is_wp_error( $delete_sync )
	&& 'cloud_site_knowledge_sync_mode_not_allowed' === $delete_sync->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: Site Knowledge runtime bridge rejects rebuild and delete sync modes before transport.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array(
				'result' => array(
					'contract_version' => 'site_knowledge_status.v1',
					'media_evidence_items' => array(
						array( 'attachment_id' => 501, 'visual_evidence' => array( 'visual_summary' => 'must not be cached' ) ),
						array( 'attachment_id' => '502' ),
						array( 'attachment_id' => 501 ),
						array( 'attachment_id' => 0 ),
					),
				),
			),
		)
	),
);
$media_status_payload = maca_site_knowledge_runtime_payload();
$media_status_payload['ability_name'] = 'npcink-cloud/site-knowledge-status';
$media_status_payload['contract_version'] = 'site_knowledge_status.v1';
$media_status_payload['input']['contract_version'] = 'site_knowledge_status.v1';
$media_status_result = Npcink_Cloud_Site_Knowledge_Runtime_Bridge::dispatch_runtime(
	$media_status_payload,
	'npcink-cloud/site-knowledge-status',
	'site_knowledge_status.v1'
);
Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register();
$projected_media_ids = apply_filters(
	'npcink_toolbox_media_fingerprint_scan_evidence_attachment_ids',
	array( 503, 501 ),
	3
);
$media_cache_keys = array_values(
	array_filter(
		array_keys( $GLOBALS['maca_transients'] ),
		static fn( $key ): bool => false !== strpos( (string) $key, '_media_evidence_ids' )
	)
);
$media_cache = ! empty( $media_cache_keys ) ? $GLOBALS['maca_transients'][ $media_cache_keys[0] ] : array();
maca_assert(
	is_array( $media_status_result )
	&& array( 503, 501, 502 ) === $projected_media_ids
	&& 1 === count( $media_cache_keys )
	&& array( 501, 502 ) === (array) $media_cache
	&& false === in_array( 'must not be cached', (array) $media_cache, true )
	&& 1 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: Site Knowledge status responses retain only bounded, credential-scoped media evidence attachment IDs for Toolbox scans without caching visual evidence or issuing a read request.'
);
