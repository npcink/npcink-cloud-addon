<?php
/**
 * Behavior tests for silent editor-assist quality correlation.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

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
	3 === count( $events )
	&& 'addon.editor_assist.generation.completed' === (string) ( $events[0]['event_kind'] ?? '' )
	&& 'addon.editor_assist.generation.completed' === (string) ( $events[1]['event_kind'] ?? '' )
	&& 'addon.editor_assist.generation.repeated' === (string) ( $events[2]['event_kind'] ?? '' )
	&& 2 === absint( $events[2]['generation_sequence'] ?? 0 )
	&& ( $events[0]['quality_session_id'] ?? '' ) === ( $events[2]['quality_session_id'] ?? '' ),
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
