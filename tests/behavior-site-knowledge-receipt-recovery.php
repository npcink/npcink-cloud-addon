<?php
/** Post-acceptance persistence faults, not live WordPress/Cloud acceptance. */
declare(strict_types=1);

class ReceiptRecoveryCAS extends TypedOptionCAS {
	public int $receipt_conflicts = 0;
	public int $unsent_conflicts = 0;
	public bool $stale_cache = false;
	public function update( $table, $values, $where, $formats, $where_formats ): int {
		$next = unserialize( $values['option_value'] );
		$old = unserialize( $where['option_value'] );
		if ( ! empty( $old['_lifecycle']['owner'] ) && empty( $next['_lifecycle']['owner'] ) ) {
			$owner = $old['_lifecycle']['owner'];
			$entry = $next['_lifecycle']['scopes'][ $owner['scope'] ]['dispatches'][ $owner['key'] ];
			$field = 'not_sent' === $entry['send_state'] ? 'unsent_conflicts' : 'receipt_conflicts';
			if ( $this->$field > 0 ) {
				$this->$field--;
				$fresh = $GLOBALS['maca_options'][ $where['option_name'] ];
				$fresh['concurrent_intent'] = array( 'retained' => true );
				if ( $this->stale_cache ) {
					$GLOBALS['typed_option_cache_eviction'] = static function ( $key ) use ( $fresh, $where ) {
						if ( $where['option_name'] === $key ) {
							$GLOBALS['maca_options'][ $key ] = $fresh;
							unset( $GLOBALS['typed_option_cache_eviction'] );
						}
					};
				} else { $GLOBALS['maca_options'][ $where['option_name'] ] = $fresh; }
				return 0;
			}
		}
		return parent::update( $table, $values, $where, $formats, $where_formats );
	}
}

foreach ( array( false, true ) as $stale_cache ) {
	$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
	$db = new ReceiptRecoveryCAS(); $db->receipt_conflicts = 1; $db->stale_cache = $stale_cache; $GLOBALS['wpdb'] = $db;
	$result = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
	$entry = reset( typed_scope()['dispatches'] );
	maca_assert( ! is_wp_error( $result ) && 1 === count( $cloud->runs ) && empty( typed_state()['_lifecycle']['owner'] ) && ! empty( $entry['run_id'] ) && ! empty( typed_state()['concurrent_intent']['retained'] ), 'Receipt: a post-acceptance CAS collision merges fresh state, preserves concurrent intent, and saves the one Cloud run (stale cache=' . (int) $stale_cache . ').' );
}

$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
$db = new ReceiptRecoveryCAS(); $db->unsent_conflicts = 1; $GLOBALS['wpdb'] = $db;
$db->after_update = static function () { Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() ); };
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
maca_assert( ! $cloud->runs && empty( typed_state()['_lifecycle']['owner'] ) && ! empty( typed_state()['concurrent_intent']['retained'] ), 'Receipt: a proven unsent release retries CAS without HTTP or losing pending intent.' );
typed_tick( 5 );
maca_assert( 'excluded' === typed_scope()['sources']['post:42']['state'], 'Receipt: exclusion progresses after the unsent release collision.' );

// A preparing sender is cancellable even if its release retries all collided.
$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
$db = new ReceiptRecoveryCAS(); $db->unsent_conflicts = 4; $GLOBALS['wpdb'] = $db;
$claimed = array();
$db->after_update = static function () use ( &$claimed ) {
	$claimed = typed_state();
	Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() );
};
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
maca_assert( ! $cloud->runs && 'preparing' === typed_state()['_lifecycle']['owner']['phase'], 'Receipt: exhausted unsent release retries retain a provably preparing owner.' );
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
$late = $claimed; $late['_lifecycle']['owner']['phase'] = 'sending';
$send_gate = new ReflectionMethod( Npcink_Cloud_Site_Knowledge_Change_Bridge::class, 'compare_and_swap_delivery_state' ); $send_gate->setAccessible( true );
maca_assert( ! $send_gate->invoke( null, $claimed, $late ), 'Receipt: a resumed old PHP sender cannot pass its exact send gate after cancellation.' );
typed_tick( 6 );
maca_assert( 'excluded' === typed_scope()['sources']['post:42']['state'] && 1 === $cloud->delete_calls && ! array_filter( $cloud->mutations, static function ( $payload ) { return 'refresh' === $payload['input']['sync_mode']; } ), 'Receipt: later Cron safely cancels a preparing owner and finishes exclusion without sending the old producer.' );

// Exhaust the request-local retries. A later Cron read must recover from Cloud.
$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
$db = new ReceiptRecoveryCAS(); $db->receipt_conflicts = 4; $GLOBALS['wpdb'] = $db;
$result = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
maca_assert( is_wp_error( $result ) && 'cloud_site_knowledge_receipt_not_saved' === $result->get_error_code() && ! empty( typed_state()['_lifecycle']['owner'] ), 'Receipt: exhausted persistence attempts never report complete success or discard the owner.' );
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
$entry = reset( typed_scope()['dispatches'] );
maca_assert( empty( typed_state()['_lifecycle']['owner'] ) && ! empty( $entry['run_id'] ) && 1 === count( $cloud->runs ) && 0 === $cloud->replays, 'Receipt: Cron saves an existing terminal receipt without executing or replaying a mutation.' );

// Simulate a fresh PHP request: the durable option survives, the response does not.
$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
$GLOBALS['maca_http_response_queue'] = array( static function ( $url, $args ) use ( $cloud ) {
	$cloud->request( $url, $args );
	throw new RuntimeException( 'fixture request ended before local receipt persistence' );
} );
try { Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 ); }
catch ( RuntimeException $error ) { /* The request-local result is intentionally gone. */ }
$interrupted = typed_state();
maca_assert( ! empty( $interrupted['_lifecycle']['owner'] ) && 1 === count( $cloud->runs ), 'Receipt: fixture really leaves an accepted dispatch without a local receipt.' );

// All identity fields and exit evidence remain mandatory on the recovery path.
$id = array_key_first( $cloud->runs ); $valid_run = $cloud->runs[ $id ];
foreach ( array( 'run_id', 'site_id', 'ability_name', 'contract_version', 'idempotency_key', 'execution_kind', 'provider_id', 'model_id', 'instance_id', 'finished_at' ) as $field ) {
	$cloud->runs[ $id ] = $valid_run; $cloud->runs[ $id ][ $field ] = 'mismatch';
	$result = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
	maca_assert( is_wp_error( $result ) && typed_state() === $interrupted && 1 === count( $cloud->runs ), 'Receipt: mismatched ' . $field . ' cannot release an interrupted owner.' );
}
foreach ( array( 'running', 'failed', 'absent', 'unavailable' ) as $fault ) {
	$cloud->runs[ $id ] = $valid_run;
	if ( 'running' === $fault ) { $cloud->runs[ $id ]['status'] = 'running'; $cloud->runs[ $id ]['finished_at'] = null; }
	if ( 'failed' === $fault ) { $cloud->runs[ $id ]['status'] = 'failed'; $cloud->runs[ $id ]['error_code'] = 'runtime.orphan_auto_fail'; }
	if ( 'absent' === $fault ) { unset( $cloud->keys[ $valid_run['idempotency_key'] ] ); }
	if ( 'unavailable' === $fault ) { $GLOBALS['maca_http_response_queue'] = array( new WP_Error( 'fixture_offline' ) ); }
	$result = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
	maca_assert( is_wp_error( $result ) && typed_state() === $interrupted && 1 === count( $cloud->runs ), 'Receipt: ' . $fault . ' keeps the writer and payload; no timeout unlock or new POST.' );
	$cloud->keys[ $valid_run['idempotency_key'] ] = $id;
	$GLOBALS['maca_http_response_queue'] = array(); $cloud->queue();
}
$cloud->runs[ $id ] = $valid_run;
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
maca_assert( empty( typed_state()['_lifecycle']['owner'] ) && 1 === count( $cloud->runs ) && 0 === $cloud->replays, 'Receipt: a later request recovers after process interruption using only the durable option and Cloud read.' );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() ); typed_tick( 8 );
maca_assert( 'excluded' === typed_scope()['sources']['post:42']['state'], 'Receipt: exclusion still requires its delete/fence/fresh-presence path after receipt recovery.' );
