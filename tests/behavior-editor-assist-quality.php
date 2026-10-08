<?php
/**
 * Behavior tests for silent editor-assist quality correlation.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

if ( ! function_exists( 'wp_is_post_autosave' ) ) {
	function wp_is_post_autosave( int $post_id ) {
		return ( $GLOBALS['maca_editor_autosave_id'] ?? 0 ) === $post_id ? $post_id : false;
	}
}
if ( ! function_exists( 'wp_is_post_revision' ) ) {
	function wp_is_post_revision( int $post_id ) {
		return ( $GLOBALS['maca_editor_revision_id'] ?? 0 ) === $post_id ? $post_id : false;
	}
}

/**
 * Returns buffered editor-assist events.
 *
 * @return array<int,array<string,mixed>>
 */
function maca_editor_assist_events(): array {
	$events = get_option( Npcink_Cloud_Observability_Collector::BUFFER_OPTION, array() );

	return is_array( $events ) ? array_values( $events ) : array();
}

/**
 * Returns buffered customer journey events.
 *
 * @return array<int,array<string,mixed>>
 */
function maca_editor_assist_journey_events(): array {
	$events = get_option( Npcink_Cloud_Customer_Journey::BUFFER_OPTION, array() );

	return is_array( $events ) ? array_values( $events ) : array();
}

maca_reset_test_state();
maca_seed_settings( true );
Npcink_Cloud_Editor_Assist_Quality::record_generation(
	'ai/summarization',
	'content_summary',
	array(
		'context' => '42',
		'content' => 'Private source content',
	),
	'run_monitoring_disabled',
	'Private generated summary',
	120
);
maca_assert(
	array() === maca_editor_assist_events()
	&& array() === get_option( Npcink_Cloud_Editor_Assist_Quality::PENDING_OPTION, array() ),
	'Behavior: editor-assist quality tracking remains disabled until monitoring is explicitly enabled.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
Npcink_Cloud_Editor_Assist_Quality::record_generation(
	'ai/summarization',
	'content_summary',
	array(
		'context' => '42',
		'content' => 'Private source content',
	),
	'run_summary_1',
	'Generated summary text',
	120
);
Npcink_Cloud_Editor_Assist_Quality::record_generation(
	'ai/summarization',
	'content_summary',
	array(
		'context' => '42',
		'content' => 'Private source content',
	),
	'run_summary_2',
	'Better generated summary text',
	135
);
$events = maca_editor_assist_events();
$pending = get_option( Npcink_Cloud_Editor_Assist_Quality::PENDING_OPTION, array() );
$journey_events = maca_editor_assist_journey_events();
maca_assert(
	4 === count( $events )
	&& 'addon.editor_assist.generation.presented' === (string) ( $events[0]['event_kind'] ?? '' )
	&& 'addon.editor_assist.generation.superseded' === (string) ( $events[1]['event_kind'] ?? '' )
	&& 'addon.editor_assist.generation.presented' === (string) ( $events[2]['event_kind'] ?? '' )
	&& 'addon.editor_assist.generation.repeated' === (string) ( $events[3]['event_kind'] ?? '' )
	&& 2 === absint( $events[3]['generation_sequence'] ?? 0 )
	&& ( $events[0]['quality_session_id'] ?? '' ) === ( $events[3]['quality_session_id'] ?? '' )
	&& ( $events[0]['generation_id'] ?? '' ) !== ( $events[2]['generation_id'] ?? '' )
	&& array_key_exists( 'wordpress_ai_version', $events[0] )
	&& 'superseded' === (string) ( $events[1]['lifecycle_state'] ?? '' ),
	'Behavior: a short-window second generation emits one repeat signal in the same quality session.'
);
maca_assert(
	1 === count( $journey_events )
	&& 'summary_generation' === (string) ( $journey_events[0]['journey'] ?? '' )
	&& 'retried' === (string) ( $journey_events[0]['step'] ?? '' ),
	'Behavior: a repeated editor generation emits one matching customer-journey retry signal.'
);
maca_assert(
	2 === count( $pending )
	&& ! array_key_exists( 'output_text', $pending[0] )
	&& ! array_key_exists( 'content', $pending[0] )
	&& 64 === strlen( (string) ( $pending[0]['output_hash'] ?? '' ) ),
	'Behavior: pending local correlation keeps only keyed fingerprints and bounded metadata.'
);
$encoded_events = wp_json_encode( $events );
maca_assert(
	is_string( $encoded_events )
	&& false === strpos( $encoded_events, 'Private source content' )
	&& false === strpos( $encoded_events, 'Generated summary text' )
	&& false === strpos( $encoded_events, '"post_id"' )
	&& false === strpos( $encoded_events, '"actor_id"' ),
	'Behavior: Cloud-bound quality events omit source text, generated text, post IDs, and user IDs.'
);

$published_post = (object) array(
	'post_title'   => 'Existing title',
	'post_content' => 'Better generated summary text',
	'post_status'  => 'publish',
);
$GLOBALS['maca_editor_autosave_id'] = 42;
Npcink_Cloud_Editor_Assist_Quality::observe_post_save( 42, $published_post, true, null );
unset( $GLOBALS['maca_editor_autosave_id'] );
$GLOBALS['maca_editor_revision_id'] = 42;
Npcink_Cloud_Editor_Assist_Quality::observe_post_save( 42, $published_post, true, null );
unset( $GLOBALS['maca_editor_revision_id'] );
maca_assert(
	array() === get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() )
	&& $pending === get_option( Npcink_Cloud_Editor_Assist_Quality::PENDING_OPTION, array() ),
	'Behavior: autosaves and revisions neither report adoption nor consume pending correlation.'
);
Npcink_Cloud_Editor_Assist_Quality::observe_post_save( 42, $published_post, true, null );
$events = maca_editor_assist_events();
$outcome = $events[ count( $events ) - 1 ];
$journey_events = maca_editor_assist_journey_events();
maca_assert(
	'addon.editor_assist.outcome.observed' === (string) ( $outcome['event_kind'] ?? '' )
	&& 'saved_exact_output' === (string) ( $outcome['outcome'] ?? '' )
	&& 'high' === (string) ( $outcome['outcome_confidence'] ?? '' )
	&& 'publish' === (string) ( $outcome['save_kind'] ?? '' )
	&& array() === get_option( Npcink_Cloud_Editor_Assist_Quality::PENDING_OPTION, array() ),
	'Behavior: an explicit publish that contains a generated suggestion records high-confidence exact adoption.'
);
maca_assert(
	3 === count( $journey_events )
	&& 'accepted' === (string) ( $journey_events[1]['step'] ?? '' )
	&& 'save' === (string) ( $journey_events[2]['journey'] ?? '' )
	&& 'succeeded' === (string) ( $journey_events[2]['step'] ?? '' ),
	'Behavior: an exact adoption records generation acceptance before the explicit save succeeds.'
);

$native_buffer = get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() );
$native_payload = array_values( $native_buffer )[0] ?? array();
maca_assert(
	1 === count( $native_buffer )
	&& 'run_summary_2' === ( $native_payload['source_run_id'] ?? '' )
	&& ( $pending[1]['generation_id'] ?? '' ) === ( $native_payload['handoff_id'] ?? '' )
	&& 'accepted' === ( $native_payload['local_outcome'] ?? '' )
	&& array( 'exact_hash_match' ) === ( $native_payload['source_reason_codes'] ?? array() )
	&& empty( $GLOBALS['maca_http_requests'] ),
	'Behavior: exact adoption queues one native feedback associated with the matched run, without HTTP in the save hook.'
);
$native_json = wp_json_encode( $native_buffer );
foreach ( array( 'Private source content', 'Better generated summary text', 'output_hash', 'post_id', 'actor_id', 'source_score', 'operator_note' ) as $forbidden ) {
	maca_assert( false === strpos( (string) $native_json, $forbidden ), 'Behavior: native feedback omits private content, identity and subjective score: ' . $forbidden );
}
Npcink_Cloud_Editor_Assist_Quality::observe_post_save( 42, $published_post, true, null );
maca_assert( $native_buffer === get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ), 'Behavior: repeated post saves do not duplicate a resolved adoption.' );
Npcink_Cloud_Observability_Collector::capture_editor_adoption( $pending[1] );
maca_assert( $native_buffer === get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ), 'Behavior: repeated capture preserves the first payload and stable identity.' );

$GLOBALS['maca_http_response_queue'][] = new WP_Error( 'transport_unavailable', 'Controlled retry fixture' );
Npcink_Cloud_Observability_Collector::flush_editor_feedback();
$failed_request = $GLOBALS['maca_http_requests'][0] ?? array();
maca_assert( $native_buffer === get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ), 'Behavior: failed native upload retains the original event.' );
$GLOBALS['maca_http_response_queue'][] = static function ( string $url, array $args ): array {
	unset( $url, $args );
	Npcink_Cloud_Observability_Collector::capture_editor_adoption(
		array( 'run_id' => 'run_captured_during_http', 'generation_id' => 'generation_late', 'task_key' => 'title_generation' )
	);
	return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'data' => array( 'status' => 'accepted' ) ) ) );
};
Npcink_Cloud_Observability_Collector::flush_editor_feedback();
$retry_request = $GLOBALS['maca_http_requests'][1] ?? array();
$remaining_native = array_values( get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ) );
maca_assert(
	str_ends_with( (string) ( $retry_request['url'] ?? '' ), '/v1/agent-feedback/events' )
	&& ( $failed_request['args']['headers']['Idempotency-Key'] ?? '' ) === ( $retry_request['args']['headers']['Idempotency-Key'] ?? '' )
	&& ( $failed_request['args']['body'] ?? '' ) === ( $retry_request['args']['body'] ?? '' )
	&& '' !== ( $retry_request['args']['headers']['X-Npcink-Signature'] ?? '' ),
	'Behavior: native retry uses the existing signed endpoint with identical payload and idempotency key.'
);
maca_assert( 1 === count( $remaining_native ) && 'run_captured_during_http' === $remaining_native[0]['source_run_id'], 'Behavior: successful upload preserves a new event captured while HTTP was in flight.' );
maca_set_monitoring_enabled( false );
$requests_before_pause = count( $GLOBALS['maca_http_requests'] );
Npcink_Cloud_Observability_Collector::flush_editor_feedback();
maca_assert(
	$requests_before_pause === count( $GLOBALS['maca_http_requests'] )
	&& array() === get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ),
	'Behavior: local opt-out discards pending native delivery without making HTTP requests.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
Npcink_Cloud_Editor_Assist_Quality::record_generation(
	'ai/title-generation',
	'title_generation',
	array( 'context' => '77' ),
	'run_title_1',
	'Generated title',
	80
);
$edited_post = (object) array(
	'post_title'   => 'Human edited title',
	'post_content' => 'Body',
	'post_status'  => 'draft',
);
Npcink_Cloud_Editor_Assist_Quality::observe_post_save( 77, $edited_post, true, null );
$events = maca_editor_assist_events();
$outcome = $events[ count( $events ) - 1 ];
$journey_events = maca_editor_assist_journey_events();
maca_assert(
	'saved_after_generation_unmatched' === (string) ( $outcome['outcome'] ?? '' )
	&& 'medium' === (string) ( $outcome['outcome_confidence'] ?? '' )
	&& 'save' === (string) ( $outcome['save_kind'] ?? '' ),
	'Behavior: a save without an exact fingerprint is recorded as an unmatched outcome, not a rejection claim.'
);
maca_assert(
	1 === count( $journey_events )
	&& 'save' === (string) ( $journey_events[0]['journey'] ?? '' )
	&& 'succeeded' === (string) ( $journey_events[0]['step'] ?? '' ),
	'Behavior: an edited save records save success without falsely claiming generation acceptance.'
);
maca_assert( array() === get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ), 'Behavior: unmatched edits never become native adopted/rejected feedback.' );

for ( $native_index = 0; $native_index < 105; ++$native_index ) {
	Npcink_Cloud_Observability_Collector::capture_editor_adoption(
		array( 'run_id' => 'run_bounded_' . $native_index, 'generation_id' => 'generation_bounded_' . $native_index, 'task_key' => 'title_generation' )
	);
}
maca_assert( 100 === count( get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ) ), 'Behavior: native delivery cannot grow past its 100-event bound.' );
for ( $native_index = 0; $native_index < 5; ++$native_index ) {
	$GLOBALS['maca_http_response_queue'][] = array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'data' => array( 'accepted_for_eval' => true ) ) ) );
}
$before_bounded_flush = count( $GLOBALS['maca_http_requests'] );
Npcink_Cloud_Observability_Collector::flush_editor_feedback();
maca_assert(
	$before_bounded_flush + 5 === count( $GLOBALS['maca_http_requests'] )
	&& 95 === count( get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ) ),
	'Behavior: one cron round sends only five native events and leaves the remainder for later.'
);

// Exercise stale state, accidental extra fields, and a changed site binding.
foreach ( array( 'expired', 'private_field', 'different_site' ) as $invalid_kind ) {
	$invalid_payload = $native_payload;
	if ( 'expired' === $invalid_kind ) {
		$invalid_payload['created_at'] = gmdate( 'c', time() - 2 * DAY_IN_SECONDS );
	} elseif ( 'private_field' === $invalid_kind ) {
		$invalid_payload['operator_note'] = 'Private note must never leave automatic telemetry';
	} else {
		$invalid_payload['site_id'] = 'site_other';
	}
	update_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array( array_keys( $native_buffer )[0] => $invalid_payload ), false );
	$count_before_flush = count( $GLOBALS['maca_http_requests'] );
	Npcink_Cloud_Observability_Collector::flush_editor_feedback();
	maca_assert(
		$count_before_flush === count( $GLOBALS['maca_http_requests'] )
		&& array() === get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ),
		'Behavior: obsolete or private native delivery state is discarded without HTTP: ' . $invalid_kind
	);
}

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
Npcink_Cloud_Editor_Assist_Quality::record_generation(
	'ai/content-resizing',
	'content_rewrite',
	array( 'post_id' => 91 ),
	'run_rewrite_1',
	'Rewritten paragraph',
	90
);
$pending = get_option( Npcink_Cloud_Editor_Assist_Quality::PENDING_OPTION, array() );
$pending[0]['generated_at'] = time() - HOUR_IN_SECONDS - 1;
update_option( Npcink_Cloud_Editor_Assist_Quality::PENDING_OPTION, $pending, false );
Npcink_Cloud_Editor_Assist_Quality::expire_pending();
$events = maca_editor_assist_events();
$outcome = $events[ count( $events ) - 1 ];
$journey_events = maca_editor_assist_journey_events();
maca_assert(
	'addon.editor_assist.outcome.expired' === (string) ( $outcome['event_kind'] ?? '' )
	&& 'expired_without_save' === (string) ( $outcome['outcome'] ?? '' )
	&& 'none' === (string) ( $outcome['save_kind'] ?? '' )
	&& array() === get_option( Npcink_Cloud_Editor_Assist_Quality::PENDING_OPTION, array() ),
	'Behavior: stale pending sessions emit one bounded no-save signal and are removed.'
);
maca_assert(
	1 === count( $journey_events )
	&& 'save' === (string) ( $journey_events[0]['journey'] ?? '' )
	&& 'abandoned' === (string) ( $journey_events[0]['step'] ?? '' ),
	'Behavior: an expired editor correlation emits one bounded abandoned-save journey signal.'
);

Npcink_Cloud_Editor_Assist_Quality::record_generation(
	'ai/title-generation',
	'title_generation',
	array( 'context' => '92' ),
	'run_title_cleanup',
	'Cleanup title',
	70
);
Npcink_Cloud_Observability_Collector::delete_data();
maca_assert(
	array() === get_option( Npcink_Cloud_Editor_Assist_Quality::PENDING_OPTION, array() ),
	'Behavior: observability disconnect cleanup removes local editor-assist correlation state.'
);
