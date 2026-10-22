<?php
/**
 * Behavior tests for privacy-safe customer journey delivery.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

/**
 * Returns buffered journey events.
 *
 * @return array<int,array<string,mixed>>
 */
function maca_customer_journey_events(): array {
	$events = get_option( Npcink_Cloud_Customer_Journey::BUFFER_OPTION, array() );

	return is_array( $events ) ? array_values( $events ) : array();
}

maca_reset_test_state();
maca_seed_settings( true );
Npcink_Cloud_Customer_Journey::capture_generation(
	'content_summary',
	'started',
	'journey_session_monitoring_disabled'
);
maca_assert(
	array() === maca_customer_journey_events(),
	'Behavior: customer journey capture remains disabled until monitoring is explicitly enabled.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
Npcink_Cloud_Customer_Journey::capture_generation(
	'content_summary',
	'started',
	'journey_session_without_cohort'
);
$untagged_events = maca_customer_journey_events();
maca_assert(
	1 === count( $untagged_events )
	&& ! array_key_exists( 'cohort_id', $untagged_events[0] ),
	'Behavior: existing sites remain compatible and omit cohort metadata until an operator declares it locally.'
);

if ( ! defined( 'NPCINK_CLOUD_ADDON_CUSTOMER_JOURNEY_COHORT_ID' ) ) {
	define( 'NPCINK_CLOUD_ADDON_CUSTOMER_JOURNEY_COHORT_ID', 'human_value_cohort_20260819' );
}

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
$session_a = Npcink_Cloud_Customer_Journey::build_session_id( 'content_summary', array( 'post_id' => 42 ) );
$session_b = Npcink_Cloud_Customer_Journey::build_session_id( 'content_summary', array( 'post_id' => 42 ) );
Npcink_Cloud_Customer_Journey::capture_generation( 'content_summary', 'started', $session_a );
Npcink_Cloud_Customer_Journey::capture_generation(
	'content_summary',
	'succeeded',
	$session_a,
	120,
	'run_summary_1'
);
Npcink_Cloud_Customer_Journey::capture_generation_failure(
	'content_summary',
	$session_a,
	140,
	'provider_request_failed Private prompt and user@example.test'
);
$events = maca_customer_journey_events();
$encoded = wp_json_encode( $events );
maca_assert(
	$session_a === $session_b
	&& 3 === count( $events )
	&& 'wordpress_editor' === (string) ( $events[0]['surface'] ?? '' )
	&& 'human_value_cohort_20260819' === (string) ( $events[0]['cohort_id'] ?? '' )
	&& 'summary_generation' === (string) ( $events[0]['journey'] ?? '' )
	&& 'started' === (string) ( $events[0]['step'] ?? '' )
	&& 'succeeded' === (string) ( $events[1]['step'] ?? '' )
	&& 'provider' === (string) ( $events[2]['error_category'] ?? '' )
	&& 'unknown' === (string) ( $events[2]['error_code'] ?? '' )
	&& ! array_key_exists( 'message', $events[2] )
	&& ! array_key_exists( 'user_id', $events[2] )
	&& ! array_key_exists( 'post_id', $events[2] )
	&& is_string( $encoded )
	&& false === strpos( $encoded, 'Private prompt' )
	&& false === strpos( $encoded, 'user@example.test' ),
	'Behavior: one 30-minute editor session emits only the closed metadata contract and never arbitrary error text or identity.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 500 ),
	'body'     => wp_json_encode( array( 'status' => 'error', 'message' => 'temporary failure' ) ),
);
$failed = Npcink_Cloud_Customer_Journey::flush_buffer();
$failed_request = $GLOBALS['maca_http_requests'][0] ?? array();
$failed_key = (string) ( $failed_request['args']['headers']['Idempotency-Key'] ?? '' );
$failed_body = json_decode( (string) ( $failed_request['args']['body'] ?? '' ), true );
maca_assert(
	empty( $failed['ok'] )
	&& 3 === count( maca_customer_journey_events() )
	&& false !== strpos( (string) ( $failed_request['url'] ?? '' ), '/v1/customer-journey/events' )
	&& Npcink_Cloud_Customer_Journey::CONTRACT_VERSION === (string) ( $failed_body['contract_version'] ?? '' )
	&& 3 === count( (array) ( $failed_body['events'] ?? array() ) ),
	'Behavior: a failed journey upload keeps the exact cohort-tagged bounded batch for retry.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array() ) ),
);
$malformed = Npcink_Cloud_Customer_Journey::flush_buffer();
maca_assert(
	empty( $malformed['ok'] )
	&& false !== strpos( (string) ( $malformed['error'] ?? '' ), 'accepted event count' )
	&& empty( get_option( Npcink_Cloud_Customer_Journey::STATUS_OPTION, array() )['last_flush_ok'] )
	&& 3 === count( maca_customer_journey_events() ),
	'Behavior: a journey response without an accepted count cannot report success or remove buffered events.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array( 'accepted_count' => 'many' ) ) ),
);
$non_numeric = Npcink_Cloud_Customer_Journey::flush_buffer();
maca_assert(
	empty( $non_numeric['ok'] )
	&& 3 === count( maca_customer_journey_events() ),
	'Behavior: a non-numeric accepted count fails closed and retains every buffered journey event.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'accepted_count'  => 2,
				'stored_count'    => 1,
				'duplicate_count' => 1,
			),
		)
	),
);
$successful = Npcink_Cloud_Customer_Journey::flush_buffer();
$retry_request = end( $GLOBALS['maca_http_requests'] );
$retry_key = (string) ( is_array( $retry_request ) ? ( $retry_request['args']['headers']['Idempotency-Key'] ?? '' ) : '' );
maca_assert(
	! empty( $successful['ok'] )
	&& 2 === absint( $successful['sent_count'] ?? 0 )
	&& 1 === count( maca_customer_journey_events() )
	&& 4 === count( $GLOBALS['maca_http_requests'] )
	&& '' !== $failed_key
	&& $failed_key === $retry_key,
	'Behavior: a journey retry is content-addressed and removes only the accepted event prefix.'
);

$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'accepted_count'  => 1,
				'stored_count'    => 1,
				'duplicate_count' => 0,
			),
		)
	),
);
$drain = Npcink_Cloud_Customer_Journey::flush_buffer();
$journey_status_after_real = get_option( Npcink_Cloud_Customer_Journey::STATUS_OPTION, array() );
$GLOBALS['maca_scheduled_events'][ Npcink_Cloud_Observability_Collector::CRON_HOOK ] = time() + 3600;
maca_assert(
	! empty( $drain['ok'] )
	&& 0 === count( maca_customer_journey_events() )
	&& 3 === absint( $journey_status_after_real['total_sent'] ?? 0 )
	&& '' !== (string) ( $journey_status_after_real['last_real_upload_at'] ?? '' )
	&& 'real_events_uploaded' === Npcink_Cloud_Customer_Journey::verification_state(),
	'Behavior: after successful journey upload empties the buffer, verification_state stays real_events_uploaded (not pending_no_real_event).'
);

$empty_flush = Npcink_Cloud_Customer_Journey::flush_buffer();
$journey_status_after_empty = get_option( Npcink_Cloud_Customer_Journey::STATUS_OPTION, array() );
maca_assert(
	! empty( $empty_flush['ok'] )
	&& 0 === absint( $empty_flush['sent_count'] ?? 0 )
	&& 0 === absint( $journey_status_after_empty['last_sent_count'] ?? 0 )
	&& 3 === absint( $journey_status_after_empty['total_sent'] ?? 0 )
	&& (string) ( $journey_status_after_real['last_real_upload_at'] ?? '' ) === (string) ( $journey_status_after_empty['last_real_upload_at'] ?? '' )
	&& 'real_events_uploaded' === Npcink_Cloud_Customer_Journey::verification_state(),
	'Behavior: a later empty successful flush must not wipe the durable real-upload signal or revert verification_state.'
);

maca_reset_test_state();
maca_seed_settings( true );
maca_set_monitoring_enabled( true );
$GLOBALS['maca_scheduled_events'][ Npcink_Cloud_Observability_Collector::CRON_HOOK ] = time() + 3600;
maca_assert(
	'pending_no_real_event' === Npcink_Cloud_Customer_Journey::verification_state(),
	'Behavior: sites that never buffered a real journey event still report pending_no_real_event.'
);

Npcink_Cloud_Customer_Journey::capture_generation(
	'content_summary',
	'started',
	'journey_session_pending_inspect'
);
maca_assert(
	1 === count( maca_customer_journey_events() )
	&& 'real_event_pending_or_unclassified' === Npcink_Cloud_Customer_Journey::verification_state(),
	'Behavior: a buffered real journey event reports real_event_pending_or_unclassified before upload.'
);

$client = new Npcink_Cloud_Runtime_Client();
$before_invalid = count( $GLOBALS['maca_http_requests'] );
$invalid = $client->get_customer_journey_summary( 24, 'invalid cohort/value' );
maca_assert(
	is_wp_error( $invalid )
	&& 'cloud_customer_journey_cohort_invalid' === $invalid->get_error_code()
	&& $before_invalid === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: an invalid journey cohort fails closed before outbound HTTP.'
);
