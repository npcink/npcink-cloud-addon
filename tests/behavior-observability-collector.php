<?php
/**
 * Behavior tests for the plugin observability collector.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

/**
 * Builds one metadata-only observability event.
 *
 * @param int $index Event index.
 * @return array<string,mixed>
 */
function maca_observability_event( int $index ): array {
	return array(
		'schema_version' => '2026-06-01',
		'plugin_slug'    => 'npcink-governance-core',
		'plugin_version' => '1.0.' . $index,
		'source'         => 'local',
		'event_kind'     => 'core.proposal.create',
		'event_id'       => 'evt_' . $index,
			'status'         => 'ok',
			'latency_ms'     => $index,
			'proposal_id'    => 'proposal_' . $index,
			'route'          => '/wp-json/npcink-governance-core/v1/' . str_repeat( 'long-route-', 40 ),
			'prompt'         => 'must not be collected',
			'raw_request'    => array( 'body' => 'must not be collected' ),
			'authorization'  => 'Bearer secret',
			'quality_contract' => 'editor_assist_quality.v1',
			'quality_session_id' => 'quality_session_' . $index,
			'task_key'       => 'content_summary',
			'object_scope_hash' => str_repeat( 'a', 64 ),
			'actor_scope_hash' => str_repeat( 'b', 64 ),
			'outcome'        => 'saved_exact_output',
			'outcome_confidence' => 'high',
			'save_kind'      => 'publish',
			'time_to_outcome_bucket' => 'under_1m',
			'generation_sequence' => 1,
			'content_storage' => 'omitted_metadata_only',
	);
}

maca_reset_test_state();
maca_seed_settings( false );
maca_set_monitoring_enabled( true );
Npcink_Cloud_Observability_Collector::capture_event( maca_observability_event( 1 ) );
maca_assert(
	0 === count( get_option( Npcink_Cloud_Observability_Collector::BUFFER_OPTION, array() ) ),
	'Behavior: observability capture is disabled until Cloud settings are verified.'
);

maca_reset_test_state();
maca_seed_settings( true );
Npcink_Cloud_Observability_Collector::capture_event( maca_observability_event( 2 ) );
maca_assert(
	0 === count( get_option( Npcink_Cloud_Observability_Collector::BUFFER_OPTION, array() ) ),
	'Behavior: observability capture is disabled until monitoring is explicitly enabled.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array(
				'accepted_count' => 1,
				'stored_count' => 1,
				'duplicate_count' => 0,
			),
		)
	),
);
$disabled_projection = Npcink_Cloud_Observability_Collector::project_monitoring_state( false );
$projection_request = $GLOBALS['maca_http_requests'][0] ?? array();
$projection_body = json_decode( (string) ( $projection_request['args']['body'] ?? '' ), true );
$projection_event = is_array( $projection_body['events'][0] ?? null ) ? $projection_body['events'][0] : array();
maca_assert(
	! empty( $disabled_projection['last_projection_ok'] )
	&& empty( $disabled_projection['last_projected_monitoring_enabled'] )
	&& 'wordpress_monitoring_state.v1' === (string) ( $projection_event['monitoring_state_contract'] ?? '' )
	&& false === ( $projection_event['monitoring_enabled'] ?? null )
	&& 'addon.monitoring.state_projected' === (string) ( $projection_event['event_kind'] ?? '' )
	&& 'omitted_metadata_only' === (string) ( $projection_event['content_storage'] ?? '' )
	&& ! array_key_exists( 'prompt', $projection_event )
	&& ! array_key_exists( 'user_id', $projection_event )
	&& ! array_key_exists( 'secret', $projection_event ),
	'Behavior: a verified disabled setting projects one versioned metadata-only state without enabling monitoring.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array(
				'accepted_count' => 1,
				'stored_count' => 1,
				'duplicate_count' => 0,
			),
		)
	),
);
$heartbeat = Npcink_Cloud_Observability_Collector::flush_buffer();
$heartbeat_request = $GLOBALS['maca_http_requests'][0] ?? array();
$heartbeat_body = json_decode( (string) ( $heartbeat_request['args']['body'] ?? '' ), true );
$heartbeat_events = is_array( $heartbeat_body['events'] ?? null ) ? $heartbeat_body['events'] : array();
maca_assert(
	1 === count( $heartbeat_events )
	&& 'addon.monitoring.state_projected' === (string) ( $heartbeat_events[0]['event_kind'] ?? '' )
	&& true === ( $heartbeat_events[0]['monitoring_enabled'] ?? null )
	&& ! empty( $heartbeat['last_projection_ok'] )
	&& 0 === absint( $heartbeat['last_sent_count'] ?? -1 )
	&& 1 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: the existing hourly flush refreshes enabled monitoring state without a second scheduler or request.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
Npcink_Cloud_Observability_Collector::capture_event( maca_observability_event( 3 ) );
$buffer = get_option( Npcink_Cloud_Observability_Collector::BUFFER_OPTION, array() );
maca_assert(
		1 === count( $buffer )
		&& 'npcink-governance-core' === (string) ( $buffer[0]['plugin_slug'] ?? '' )
		&& 200 === strlen( (string) ( $buffer[0]['route'] ?? '' ) )
		&& 'editor_assist_quality.v1' === (string) ( $buffer[0]['quality_contract'] ?? '' )
		&& 'content_summary' === (string) ( $buffer[0]['task_key'] ?? '' )
		&& 'omitted_metadata_only' === (string) ( $buffer[0]['content_storage'] ?? '' )
		&& ! array_key_exists( 'prompt', $buffer[0] )
	&& ! array_key_exists( 'raw_request', $buffer[0] )
	&& ! array_key_exists( 'authorization', $buffer[0] ),
	'Behavior: verified opt-in capture stores only allowed metadata fields.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
for ( $i = 1; $i <= 205; $i++ ) {
	Npcink_Cloud_Observability_Collector::capture_event( maca_observability_event( $i ) );
}
$buffer = get_option( Npcink_Cloud_Observability_Collector::BUFFER_OPTION, array() );
maca_assert(
	200 === count( $buffer )
	&& 'evt_6' === (string) ( $buffer[0]['event_id'] ?? '' )
	&& 'evt_205' === (string) ( $buffer[199]['event_id'] ?? '' ),
	'Behavior: observability buffer remains capped and keeps the newest events.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
for ( $i = 1; $i <= 3; $i++ ) {
	Npcink_Cloud_Observability_Collector::capture_event( maca_observability_event( $i ) );
}
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 500 ),
	'body'     => wp_json_encode(
		array(
			'status'     => 'error',
			'error_code' => 'signature.timestamp_stale',
			'message'    => 'X-Npcink-Timestamp header is outside the accepted time window.',
		)
	),
);
$failed = Npcink_Cloud_Observability_Collector::flush_buffer();
$buffer = get_option( Npcink_Cloud_Observability_Collector::BUFFER_OPTION, array() );
$failed_idempotency_key = (string) ( $GLOBALS['maca_http_requests'][0]['args']['headers']['Idempotency-Key'] ?? '' );
maca_assert(
	empty( $failed['last_upload_ok'] )
	&& 3 === count( $buffer )
	&& 3 === absint( $failed['buffer_count'] ?? 0 )
	&& false !== strpos( (string) ( $failed['last_upload_error'] ?? '' ), 'X-Npcink-Timestamp' ),
	'Behavior: failed observability upload keeps buffered events and records the upload error.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'accepted_count' => 2,
				'stored_count'    => 1,
				'duplicate_count' => 1,
			),
		)
	),
);
$successful = Npcink_Cloud_Observability_Collector::flush_buffer();
$buffer = get_option( Npcink_Cloud_Observability_Collector::BUFFER_OPTION, array() );
$retry_idempotency_key = (string) ( $GLOBALS['maca_http_requests'][1]['args']['headers']['Idempotency-Key'] ?? '' );
maca_assert(
	! empty( $successful['last_upload_ok'] )
	&& 1 === count( $buffer )
	&& 1 === absint( $successful['buffer_count'] ?? 0 )
	&& 2 === absint( $successful['total_uploaded'] ?? 0 )
	&& 2 === absint( $successful['last_sent_count'] ?? 0 )
	&& 1 === absint( $successful['last_stored_count'] ?? 0 )
	&& 1 === absint( $successful['last_duplicate_count'] ?? 0 )
	&& 2 === absint( $successful['total_sent'] ?? 0 )
	&& 1 === absint( $successful['total_stored'] ?? 0 )
	&& 1 === absint( $successful['total_duplicate'] ?? 0 )
	&& '' === (string) ( $successful['last_upload_error'] ?? '' )
	&& '' !== (string) ( $successful['last_uploaded_at'] ?? '' ),
	'Behavior: successful observability upload records sent, stored, and duplicate counts.'
);
maca_assert(
	'' !== $failed_idempotency_key
	&& $failed_idempotency_key === $retry_idempotency_key,
	'Behavior: an uncertain observability retry reuses the exact batch idempotency key.'
);
$status = Npcink_Cloud_Observability_Collector::get_status();
maca_assert(
	! empty( $status['last_upload_ok'] )
	&& 1 === absint( $status['buffer_count'] ?? 0 )
	&& 2 === absint( $status['total_uploaded'] ?? 0 )
	&& 2 === absint( $status['last_sent_count'] ?? 0 )
	&& 1 === absint( $status['last_stored_count'] ?? 0 )
	&& 1 === absint( $status['last_duplicate_count'] ?? 0 )
	&& 2 === absint( $status['total_sent'] ?? 0 )
	&& 1 === absint( $status['total_stored'] ?? 0 )
	&& 1 === absint( $status['total_duplicate'] ?? 0 )
	&& '' === (string) ( $status['last_upload_error'] ?? '' ),
	'Behavior: local monitoring status exposes accurate upload outcome, buffer count, and Cloud ingest totals.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
for ( $i = 1; $i <= 75; $i++ ) {
	Npcink_Cloud_Observability_Collector::capture_event( maca_observability_event( $i ) );
}
$GLOBALS['maca_http_response_queue'][] = static function ( string $url, array $args ): array {
	$body = json_decode( (string) ( $args['body'] ?? '' ), true );
	$events = is_array( $body['events'] ?? null ) ? $body['events'] : array();

	return array(
		'response' => array( 'code' => 200 ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'accepted_count' => count( $events ),
				),
			)
		),
	);
};
$bounded = Npcink_Cloud_Observability_Collector::flush_buffer();
$request = $GLOBALS['maca_http_requests'][0] ?? array();
$body = json_decode( (string) ( $request['args']['body'] ?? '' ), true );
$sent_events = is_array( $body['events'] ?? null ) ? $body['events'] : array();
$buffer = get_option( Npcink_Cloud_Observability_Collector::BUFFER_OPTION, array() );
maca_assert(
	50 === count( $sent_events )
	&& 26 === count( $buffer )
	&& 49 === absint( $bounded['total_uploaded'] ?? 0 )
	&& 'addon.monitoring.state_projected' === (string) ( $sent_events[49]['event_kind'] ?? '' )
	&& 1 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: observability flush keeps one bounded 50-item request including the current monitoring heartbeat.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
for ( $i = 1; $i <= 3; $i++ ) {
	Npcink_Cloud_Observability_Collector::capture_event( maca_observability_event( $i ) );
}
$GLOBALS['maca_http_response_queue'][] = static function ( string $url, array $args ): array {
	unset( $url, $args );
	Npcink_Cloud_Observability_Collector::capture_event( maca_observability_event( 4 ) );

	return array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode(
			array(
				'status' => 'ok',
				'data' => array( 'accepted_count' => 2 ),
			)
		),
	);
};
Npcink_Cloud_Observability_Collector::flush_buffer();
$buffer = get_option( Npcink_Cloud_Observability_Collector::BUFFER_OPTION, array() );
maca_assert(
	array( 'evt_3', 'evt_4' ) === array_column( $buffer, 'event_id' ),
	'Behavior: observability flush preserves events captured while HTTP is in flight.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'window'        => array(
					'hours'         => 24,
					'generated_at'  => '2026-06-03T00:00:00Z',
					'authorization' => 'Bearer secret',
				),
				'totals'        => array(
					'events_total' => 10,
					'error_total'  => 1,
					'success_rate' => 0.9,
					'payload_json' => array( 'raw' => 'blocked' ),
				),
				'plugins'       => array(
					array(
						'plugin_slug'    => 'npcink-governance-core',
						'events_total'   => 8,
						'error_total'    => 1,
						'success_rate'   => 0.875,
						'avg_latency_ms' => 12.5,
						'event_kinds'    => array(
							array(
								'event_kind'   => 'core.commit.preflight',
								'events_total' => 2,
								'raw_payload'  => 'blocked',
							),
						),
						'secret'         => 'blocked',
					),
				),
				'timeline'      => array(
					array(
						'bucket_start_at' => '2026-06-03T00:00:00Z',
						'bucket_end_at'   => '2026-06-03T01:00:00Z',
						'bucket_hours'    => 1,
						'events_total'    => 10,
						'raw_payload'     => 'blocked',
					),
				),
				'errors'        => array(
					array(
						'plugin_slug'  => 'npcink-governance-core',
						'event_kind'   => 'core.commit.preflight',
						'error_code'   => 'core.preflight.blocked',
						'count'        => 1,
						'last_seen_at' => '2026-06-03T00:00:00Z',
						'payload_json' => 'blocked',
					),
				),
				'recent_errors' => array(
					array(
						'error_code'    => 'core.preflight.blocked',
						'plugin_slug'   => 'npcink-governance-core',
						'event_kind'    => 'core.commit.preflight',
						'received_at'   => '2026-06-03T00:00:00Z',
						'raw_response'  => 'blocked',
						'status_detail' => '<b>blocked</b>',
					),
				),
				'health'        => array(
					'status'  => 'warning',
					'score'   => 80,
					'summary' => '<b>Needs review</b>',
					'reasons' => array( 'plugin_observability.error_rate_elevated' ),
					'state'   => array( 'operator_note' => 'blocked' ),
				),
				'attention'     => array(
					array(
						'attention_key'   => 'attention_1',
						'severity'        => 'warning',
						'code'            => 'plugin_observability.error_rate_elevated',
						'title'           => 'Elevated errors',
						'workflow_status' => 'active',
						'state'           => array( 'operator_note' => 'blocked' ),
					),
				),
				'digest'        => array(
					'period_label' => 'daily',
					'headline'     => 'Metadata only',
					'bullets'      => array( 'No raw payloads', '<b>No secrets</b>' ),
					'token'        => 'blocked',
				),
				'payload_json'  => array( 'raw' => 'blocked' ),
				'secret'        => 'blocked',
			),
		)
	),
);
$summary = Npcink_Cloud_Observability_Collector::refresh_summary();
$cached_summary = is_array( $summary['summary'] ?? null ) ? $summary['summary'] : array();
maca_assert(
	! empty( $summary['last_refresh_ok'] )
	&& 10 === absint( $cached_summary['totals']['events_total'] ?? 0 )
	&& 'npcink-governance-core' === (string) ( $cached_summary['plugins'][0]['plugin_slug'] ?? '' )
	&& 'core.commit.preflight' === (string) ( $cached_summary['plugins'][0]['event_kinds'][0]['event_kind'] ?? '' )
	&& 10 === absint( $cached_summary['timeline'][0]['events_total'] ?? 0 )
	&& 'core.preflight.blocked' === (string) ( $cached_summary['errors'][0]['error_code'] ?? '' )
	&& 'blocked' === (string) ( $cached_summary['recent_errors'][0]['status_detail'] ?? '' )
	&& 'warning' === (string) ( $cached_summary['health']['status'] ?? '' )
	&& 'attention_1' === (string) ( $cached_summary['attention'][0]['attention_key'] ?? '' )
	&& ! array_key_exists( 'payload_json', $cached_summary )
	&& ! array_key_exists( 'secret', $cached_summary )
	&& ! array_key_exists( 'authorization', $cached_summary['window'] ?? array() )
	&& ! array_key_exists( 'payload_json', $cached_summary['totals'] ?? array() )
	&& ! array_key_exists( 'secret', $cached_summary['plugins'][0] ?? array() )
	&& ! array_key_exists( 'raw_payload', $cached_summary['plugins'][0]['event_kinds'][0] ?? array() )
	&& ! array_key_exists( 'raw_payload', $cached_summary['timeline'][0] ?? array() )
	&& ! array_key_exists( 'payload_json', $cached_summary['errors'][0] ?? array() )
	&& ! array_key_exists( 'raw_response', $cached_summary['recent_errors'][0] ?? array() )
	&& ! array_key_exists( 'state', $cached_summary['health'] ?? array() )
	&& ! array_key_exists( 'state', $cached_summary['attention'][0] ?? array() )
	&& ! array_key_exists( 'token', $cached_summary['digest'] ?? array() ),
	'Behavior: Cloud summary refresh stores only sanitized allowlisted summary fields.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'contract_version'   => 'cloud_agent_feedback.v1',
				'window_hours'       => 24,
				'events_total'       => 4,
				'source_runtimes'    => array( 'site_knowledge', 'image_candidates' ),
				'low_quality_labels' => array(
					array(
						'label' => 'wrong_next_step',
						'count' => 2,
					),
				),
				'rejection_reasons'  => array(
					'unsupported_claim' => 1,
				),
				'prompt'             => 'must not be stored',
				'raw_request'        => array( 'body' => 'must not be stored' ),
			),
		)
	),
);
$agent_summary = Npcink_Cloud_Observability_Collector::refresh_agent_feedback_summary();
$status = Npcink_Cloud_Observability_Collector::get_status();
$cached_agent_summary = is_array( $status['agent_feedback_summary']['summary'] ?? null ) ? $status['agent_feedback_summary']['summary'] : array();
$agent_request = $GLOBALS['maca_http_requests'][0] ?? array();
maca_assert(
	! empty( $agent_summary['last_refresh_ok'] )
	&& false !== strpos( (string) ( $agent_request['url'] ?? '' ), '/v1/agent-feedback/summary?window_hours=24' )
	&& 4 === absint( $cached_agent_summary['events_total'] ?? 0 )
	&& in_array( 'site_knowledge', $cached_agent_summary['source_runtimes'] ?? array(), true )
	&& 'wrong_next_step' === (string) ( $cached_agent_summary['low_quality_labels'][0]['label'] ?? '' )
	&& 'unsupported_claim' === (string) ( $cached_agent_summary['rejection_reasons'][0]['label'] ?? '' )
	&& ! array_key_exists( 'prompt', $cached_agent_summary )
	&& ! array_key_exists( 'raw_request', $cached_agent_summary )
	&& false === (bool) ( $cached_agent_summary['production_mutation'] ?? true )
	&& 'wordpress_local' === (string) ( $cached_agent_summary['approval_truth'] ?? '' ),
	'Behavior: Agent feedback quality summary refresh is read-only, sanitized, and independent from monitoring upload opt-in.'
);
