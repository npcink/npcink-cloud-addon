<?php
/** Deterministic delivery fixtures, NOT WordPress/MySQL or live Cloud acceptance. */
declare(strict_types=1);
require_once __DIR__ . '/helpers.php';
if ( ! function_exists( 'maca_add_public_post_fixture' ) ) {
	require_once __DIR__ . '/behavior-site-knowledge-change-bridge.php';
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	function wp_get_attachment_url( $id ) { return $GLOBALS['typed_media_urls'][ $id ] ?? false; }
}
if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $id, $key, $single = false ) { return $GLOBALS['typed_media_alt'][ $id ] ?? ''; }
}
if ( ! function_exists( 'get_comments' ) ) {
	function get_comments( $args ) { return $GLOBALS['typed_public_comments'][ $args['post_id'] ] ?? array(); }
}
if ( ! function_exists( 'get_comment_link' ) ) {
	function get_comment_link( $comment ) { return 'https://example.test/comment/' . $comment->comment_ID; }
}

/** Executes the REAL serialized-CAS call branch against isolated option storage. */
final class TypedOptionCAS {
	public string $options = 'fixture_options';
	public int $writes = 0;
	public $after_update = null;
	public function update( $table, $values, $where, $formats, $where_formats ): int {
		$current = get_option( $where['option_name'], array() );
		if ( serialize( $current ) !== $where['option_value'] ) { return 0; }
		$GLOBALS['maca_options'][ $where['option_name'] ] = unserialize( $values['option_value'] );
		$this->writes++;
		if ( $this->after_update ) { $callback = $this->after_update; $this->after_update = null; $callback(); }
		return 1;
	}
	public function delete( $table, $where, $formats ): int {
		if ( serialize( get_option( $where['option_name'], array() ) ) !== $where['option_value'] ) { return 0; }
		unset( $GLOBALS['maca_options'][ $where['option_name'] ] );
		$this->writes++;
		return 1;
	}
}

/** A record-only Cloud double: tests addon causality, never substitutes for B02. */
final class TypedCloudFixture {
	public array $runs = array();
	public array $results = array();
	public string $restore_result_fault = '';
	public array $keys = array();
	public array $sources = array();
	public array $comment_parents = array();
	public array $mutations = array();
	public array $headers = array();
	public bool $hold_producers = false;
	public bool $lose_next = false;
	public bool $malformed_presence = false;
	public bool $restore_empty = false;
	public bool $comments_enabled = false;
	public int $delete_calls = 0;
	public int $replays = 0;
	public $during_send = null;

	public function queue(): void { $GLOBALS['maca_http_response_queue'][] = array( $this, 'request' ); }
	public function request( $url, $args ) {
		$this->queue();
		if ( $this->during_send ) { $callback = $this->during_send; $this->during_send = null; $callback(); }
		if ( 'GET' === $args['method'] ) {
			if ( 'result' === basename( $url ) ) { return $this->response( $this->results[ basename( dirname( $url ) ) ] ?? array() ); }
			$id = basename( $url );
			return $this->response( $this->runs[ $id ] ?? array() );
		}
		$payload = json_decode( $args['body'], true );
		$input = $payload['input'];
		if ( 'site_knowledge_status.v1' === $payload['contract_version'] ) {
			$rows = array();
			foreach ( $input['source_items'] as $item ) {
				$present = isset( $this->sources[ $item['source_type'] . ':' . $item['source_id'] ] );
				$associated = 'media' === $item['source_type'] ? 0 : count( array_filter( $this->comment_parents, static function ( $parent ) use ( $item ) { return $parent === $item['source_id']; } ) );
				$rows[] = array_merge( $item, array( 'document_present' => $present, 'stored_chunk_count' => $present ? 1 : 0, 'public_document_present' => $present, 'public_chunk_count' => $present ? 1 : 0, 'associated_comment_document_count' => $associated, 'associated_comment_stored_chunk_count' => $associated ) );
			}
			return $this->response( array( 'result' => array( 'contract_version' => 'site_knowledge_status.v1', 'comments_enabled' => $this->comments_enabled, 'source_presence' => array( 'status' => 'complete', 'storage_scope' => 'relational_index', 'observed_at' => gmdate( 'c' ), 'requested_count' => count( $rows ), 'items' => $this->malformed_presence ? array() : $rows ) ) ) );
		}
		$key = $args['headers']['Idempotency-Key'] ?? '';
		maca_assert( '' !== $key, 'Typed fixture receives the signed transport idempotency header.' );
		if ( isset( $this->keys[ $key ] ) ) {
			$this->replays++;
			return $this->response( array( 'run_id' => $this->keys[ $key ], 'idempotent_replay' => true ) );
		}
		$id = 'typed-run-' . ( count( $this->runs ) + 1 );
		$this->keys[ $key ] = $id;
		$this->headers[] = $args['headers'];
		$this->mutations[] = $payload;
		$delete = 'delete' === $input['sync_mode'];
		$status = ! $delete && $this->hold_producers ? 'running' : 'succeeded';
		$this->runs[ $id ] = array( 'run_id' => $id, 'site_id' => Npcink_Cloud_Addon_Settings::get_settings()['site_id'], 'ability_name' => 'npcink-cloud/site-knowledge-sync', 'contract_version' => 'site_knowledge_sync.v1', 'idempotency_key' => $key, 'execution_kind' => 'knowledge', 'provider_id' => 'site_knowledge', 'model_id' => 'site-knowledge-managed', 'instance_id' => 'cloud-runtime', 'status' => $status, 'error_code' => null, 'finished_at' => 'succeeded' === $status ? gmdate( 'c' ) : null );
		$count = count( $input['documents'] ?? array() ) + count( $input['media_items'] ?? array() ) + count( $input['comments'] ?? array() );
		$sync = array( 'sync_mode' => $input['sync_mode'], 'accepted_documents' => $count, 'indexed_documents' => $count, 'unchanged_documents' => 0, 'failed_documents' => 0, 'skipped_documents' => 0, 'skipped_due_to_quota' => 0 );
		if ( ! $delete && $this->restore_result_fault ) { $sync[ $this->restore_result_fault ] = 1; }
		$this->results[ $id ] = array( 'run_id' => $id, 'status' => $status, 'result' => array( 'run_id' => $id, 'contract_version' => 'site_knowledge_sync.v1', 'status' => 'completed', 'sync' => $sync ) );
		if ( $delete ) {
			$this->delete_calls++;
			foreach ( $input['source_items'] as $item ) { unset( $this->sources[ $item['source_type'] . ':' . $item['source_id'] ] ); if ( 'media' !== $item['source_type'] ) { $this->comment_parents = array_filter( $this->comment_parents, static function ( $parent ) use ( $item ) { return $parent !== $item['source_id']; } ); } }
		} elseif ( 'succeeded' === $status && ! $this->restore_empty ) {
			foreach ( $input['documents'] ?? array() as $doc ) { $this->sources[ $doc['post_type'] . ':' . $doc['post_id'] ] = true; }
			foreach ( $input['media_items'] ?? array() as $media ) { $this->sources[ 'media:' . $media['attachment_id'] ] = true; }
			foreach ( $input['comments'] ?? array() as $comment ) { $this->comment_parents[ $comment['comment_id'] ] = $comment['post_id']; }
		}
		if ( $this->lose_next ) { $this->lose_next = false; return new WP_Error( 'fixture_response_lost', 'Fixture response lost' ); }
		return $this->response( array( 'run_id' => $id, 'idempotent_replay' => false ) );
	}
	public function response( $data ): array { return array( 'response' => array( 'code' => 200 ), 'body' => wp_json_encode( array( 'status' => 'ok', 'data' => $data ) ) ); }
}

function typed_reset( bool $fresh = true ): TypedCloudFixture {
	maca_reset_site_knowledge_bridge_state();
	unset( $GLOBALS['wpdb'] );
	if ( $fresh ) { Npcink_Cloud_Site_Knowledge_Change_Bridge::sync_schedule(); }
	maca_seed_settings( true );
	$GLOBALS['wpdb'] = new TypedOptionCAS();
	$GLOBALS['typed_media_urls'] = array();
	$GLOBALS['typed_media_alt'] = array();
	$GLOBALS['typed_public_comments'] = array();
	$cloud = new TypedCloudFixture(); $cloud->queue(); return $cloud;
}
function typed_state(): array { return get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() ); }
function typed_scope(): array { return typed_state()['_lifecycle']['scopes'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::lifecycle_scope() ] ?? array(); }
function typed_selection( string $type = 'post', int $id = 42 ): array { return array( array( 'source_type' => $type, 'source_id' => $id ) ); }
function typed_tick( int $count = 1 ): void { for ( $i = 0; $i < $count; $i++ ) { Npcink_Cloud_Site_Knowledge_Change_Bridge::process_source_operations(); } }
function typed_image( int $id = 501, int $parent = 42 ): void {
	maca_add_public_post_fixture( $id, 'attachment' );
	$post = $GLOBALS['maca_posts'][ $id ]; $post->post_status = 'inherit'; $post->post_parent = $parent; $post->post_mime_type = 'image/jpeg';
	$GLOBALS['typed_media_urls'][ $id ] = 'https://example.test/image-' . $id . '.jpg';
	$GLOBALS['typed_media_alt'][ $id ] = 'A public landscape';
}

$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
foreach ( array( array(), 'all', array( array( 'source_type' => 'text', 'source_id' => 42 ) ), array( array( 'source_type' => 'post', 'source_id' => '42' ) ), array( array( 'source_type' => 'post', 'source_id' => true ) ), array( array( 'source_type' => 'post', 'source_id' => -42 ) ), array( array( 'source_type' => 'post', 'source_id' => 42, 'site_id' => 'other' ) ), array_fill( 0, 101, typed_selection()[0] ) ) as $invalid ) {
	$result = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', $invalid );
	maca_assert( is_wp_error( $result ) && 0 === count( $cloud->mutations ), 'K01-02 strict selectors fail before dispatch, including pre-dedup overflow.' );
}
$cloud->sources = array( 'post:42' => true, 'media:42' => true, 'page:43' => true );
$request = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() );
maca_assert( ! is_wp_error( $request ) && 'pending_delete' === typed_scope()['sources']['post:42']['state'] && ! $cloud->mutations, 'K02-01 pending is CAS-durable before any HTTP.' );
typed_tick( 4 );
maca_assert( 'excluded' === typed_scope()['sources']['post:42']['state'] && ! isset( $cloud->sources['post:42'] ) && isset( $cloud->sources['media:42'], $cloud->sources['page:43'] ), 'ST-01 typed fresh absence plus successful known run transitions to excluded; mock sentinels survive.' );
$delete_input = $cloud->mutations[0]['input'];
maca_assert( array( 'source_items', 'contract_version', 'write_posture', 'direct_wordpress_write', 'sync_mode' ) === array_values( array_intersect( array( 'source_items', 'contract_version', 'write_posture', 'direct_wordpress_write', 'sync_mode' ), array_keys( $delete_input ) ) ) && ! isset( $delete_input['post_ids'], $delete_input['documents'] ), 'K01-04 typed deletion sends no broad legacy selectors.' );
$overlap = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() );
maca_assert( is_wp_error( $overlap ), 'K02-06 overlap cannot create another approval or widen scope.' );
$restore = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'restore', typed_selection() );
maca_assert( ! is_wp_error( $restore ) && 'restore_pending' === typed_scope()['sources']['post:42']['state'], 'ST-03 explicit restore retains suppression until fresh public chunks.' );
typed_tick( 7 );
maca_assert( ! isset( typed_scope()['sources']['post:42'] ) && isset( $cloud->sources['post:42'] ), 'ST-03 restore completes only after successful refresh and fresh public document/chunks.' );

$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
$cloud->hold_producers = true;
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
$old_id = array_key_first( $cloud->runs );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() );
typed_tick( 3 );
maca_assert( 1 === $cloud->delete_calls && 'pending_delete' === typed_scope()['sources']['post:42']['state'], 'K02-02 old running writer stays pending after D0.' );
$cloud->sources['post:42'] = true;
$cloud->runs[ $old_id ]['status'] = 'succeeded'; $cloud->runs[ $old_id ]['finished_at'] = gmdate( 'c' );
typed_tick( 1 );
$cloud->lose_next = true; typed_tick( 1 ); typed_tick( 7 );
maca_assert( 2 === $cloud->delete_calls && $cloud->replays >= 1 && 'excluded' === typed_scope()['sources']['post:42']['state'] && ! isset( $cloud->sources['post:42'] ), 'K02-03/04 old writer reinserts, one distinct C1 really deletes; lost C1 response replays same run without C2.' );
$delete_keys = array();
foreach ( typed_scope()['dispatches'] as $key => $entry ) { if ( 'exclude' === $entry['purpose'] ) { $delete_keys[] = $key; } }
maca_assert( count( $delete_keys ) === 2 && $delete_keys[0] !== $delete_keys[1], 'K02-03 D0 and C1 use stable distinct keys over the same approved selection.' );

foreach ( array( 'runtime.operator_stale_running_failed', 'runtime.repair.auto_fail_stale_running', 'runtime.cancelled', 'unknown.failure', 'site_knowledge.looks_plausible_but_unknown' ) as $error ) {
	$cloud = typed_reset(); maca_add_public_post_fixture( 42 ); $cloud->hold_producers = true;
	Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
	$old_id = array_key_first( $cloud->runs ); $cloud->runs[ $old_id ]['status'] = 'failed'; $cloud->runs[ $old_id ]['error_code'] = $error; $cloud->runs[ $old_id ]['finished_at'] = gmdate( 'c' );
	Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() ); typed_tick( 5 );
	maca_assert( 'pending_delete' === typed_scope()['sources']['post:42']['state'] && 1 === $cloud->delete_calls, 'K02-05 ' . $error . ' is not a product writer fence, despite generic executor fields.' );
}
$cloud = typed_reset(); maca_add_public_post_fixture( 42 ); $cloud->hold_producers = true;
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
$old_id = array_key_first( $cloud->runs ); $cloud->runs[ $old_id ]['status'] = 'failed'; $cloud->runs[ $old_id ]['error_code'] = 'site_knowledge.zilliz_upsert_failed'; $cloud->runs[ $old_id ]['finished_at'] = gmdate( 'c' );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() ); typed_tick( 8 );
maca_assert( 'excluded' === typed_scope()['sources']['post:42']['state'] && 2 === $cloud->delete_calls, 'K02-05 whitelisted normal executor failure can leave partial writes; still requires C1.' );

foreach ( array( 'malformed', 'restore_empty', 'legacy' ) as $condition ) {
	$cloud = typed_reset( 'legacy' !== $condition ); maca_add_public_post_fixture( 42 );
	$cloud->malformed_presence = 'malformed' === $condition;
	Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() ); typed_tick( 5 );
	if ( 'restore_empty' === $condition ) {
		$cloud->restore_empty = true;
		Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'restore', typed_selection() ); typed_tick( 7 );
	}
	maca_assert( 'excluded' !== typed_scope()['sources']['post:42']['state'] && isset( typed_scope()['sources']['post:42'] ), 'K03-03/ST-04 ' . $condition . ' does not clear suppression from cache, terminal status or an empty buffer.' );
}

$cloud = typed_reset(); maca_add_public_post_fixture( 42 ); typed_image();
$media = Npcink_Cloud_Site_Knowledge_Change_Bridge::public_source( typed_selection( 'media', 501 )[0] );
maca_assert( is_array( $media ) && isset( $media['attachment_id'], $media['mime_type'], $media['url'], $media['media_fingerprint'] ) && ! isset( $media['visual_summary'] ), 'K01-06 real image metadata and stable fingerprint are produced without fabricated visual content.' );
foreach ( array( 'private', 'password', 'trash', 'unparented', 'mime', 'no_text' ) as $bad ) {
	$saved_parent = clone $GLOBALS['maca_posts'][42]; $saved_image = clone $GLOBALS['maca_posts'][501]; $saved_alt = $GLOBALS['typed_media_alt'][501];
	if ( 'private' === $bad ) { $GLOBALS['maca_posts'][42]->post_status = 'private'; }
	if ( 'password' === $bad ) { $GLOBALS['maca_posts'][42]->post_password = 'fixture'; }
	if ( 'trash' === $bad ) { $GLOBALS['maca_posts'][501]->post_status = 'trash'; }
	if ( 'unparented' === $bad ) { $GLOBALS['maca_posts'][501]->post_parent = 0; }
	if ( 'mime' === $bad ) { $GLOBALS['maca_posts'][501]->post_mime_type = 'video/mp4'; }
	if ( 'no_text' === $bad ) { $GLOBALS['maca_posts'][501]->post_title = ''; $GLOBALS['maca_posts'][501]->post_excerpt = ''; $GLOBALS['maca_posts'][501]->post_content = ''; $GLOBALS['typed_media_alt'][501] = ''; }
	maca_assert( null === Npcink_Cloud_Site_Knowledge_Change_Bridge::public_source( typed_selection( 'media', 501 )[0] ), 'K01-05 rejects image ' . $bad . ' rather than treating inherit/URL as permission.' );
	$GLOBALS['maca_posts'][42] = $saved_parent; $GLOBALS['maca_posts'][501] = $saved_image; $GLOBALS['typed_media_alt'][501] = $saved_alt;
}
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_attachment_change( 501 ); maca_run_site_knowledge_flush();
$image_input = $cloud->mutations[0]['input'];
maca_assert( isset( $image_input['media_items'] ) && ! isset( $image_input['post_ids'], $image_input['documents'] ), 'K01-08 actual image event uses media_items-only incremental refresh.' );
$cloud = typed_reset(); maca_add_public_post_fixture( 42 ); typed_image();
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'rebuild' );
$full = maca_legacy_full_cursor();
maca_assert( 2 === count( $full['typed_batches'] ) && 'media' === $full['typed_batches'][1][0]['source_type'] && array( 42 ) === $full['post_ids'], 'K01-07 full typed cursor retains images after the first clear without putting them in post_ids.' );

// Serialized CAS stale-owner path; the test branch models conditional SQL, not real MySQL.
$cas = new ReflectionMethod( Npcink_Cloud_Site_Knowledge_Change_Bridge::class, 'compare_and_swap_delivery_state' ); $cas->setAccessible( true );
$old = typed_state(); $new = $old; $new['_lifecycle']['test_version'] = 1;
maca_assert( $cas->invoke( null, $old, $new ) && ! $cas->invoke( null, $old, $old ), 'K02-01 exact serialized compare rejects a stale option writer.' );
$before_cleanup = typed_state(); Npcink_Cloud_Addon_Cleanup::delete_all( Npcink_Cloud_Addon_Settings::get_settings() );
maca_assert( $before_cleanup === typed_state(), 'K02-06 disconnect cleanup retains old namespace records and unresolved writer identities.' );
unset( $GLOBALS['wpdb'] );


// Restoration cannot be inferred from pre-existing rows plus a successful but partial run.
foreach ( array( 'failed_documents', 'skipped_documents', 'skipped_due_to_quota' ) as $fault ) {
	$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
	Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() ); typed_tick( 4 );
	$cloud->sources['post:42'] = true;
	$cloud->restore_result_fault = $fault;
	Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'restore', typed_selection() ); typed_tick( 7 );
	maca_assert( 'restore_pending' === typed_scope()['sources']['post:42']['state'], 'ST-04 ' . $fault . ' keeps suppression even with fresh public rows and succeeded run.' );
}

// Every executor identity field matters, independently of a terminal label.
foreach ( array( 'run_id', 'site_id', 'ability_name', 'contract_version', 'idempotency_key', 'execution_kind', 'provider_id', 'model_id', 'instance_id', 'finished_at' ) as $field ) {
	$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
	Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
	$entry = reset( typed_scope()['dispatches'] );
	$cloud->runs[ $entry['run_id'] ][ $field ] = 'wrong';
	maca_assert( is_wp_error( Npcink_Cloud_Site_Knowledge_Runtime_Bridge::read_writer_witness( $entry ) ), 'K02-05 mismatched writer ' . $field . ' cannot certify quiescence.' );
}

// Claim->intent->send interleave: pending wins before the HTTP linearization point.
$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
$GLOBALS['wpdb']->after_update = static function () { Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() ); };
$result = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
maca_assert( is_wp_error( $result ) && ! $cloud->mutations && 'pending_delete' === typed_scope()['sources']['post:42']['state'] && empty( typed_state()['_lifecycle']['owner'] ), 'K02-01 concurrent intent after claim suppresses HTTP and releases only a proven unsent owner.' );
typed_tick( 5 );
maca_assert( 'excluded' === typed_scope()['sources']['post:42']['state'], 'ST-02 positively unsent producer is not replayed as a new writer.' );

// Toolbox/public facade and alternate document spellings share the final suppression guard.
$cloud = typed_reset(); maca_add_public_post_fixture( 42 ); typed_image();
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', array_merge( typed_selection(), typed_selection( 'media', 501 ) ) );
$payload_builder = new ReflectionMethod( Npcink_Cloud_Site_Knowledge_Change_Bridge::class, 'operation_payload' ); $payload_builder->setAccessible( true );
$payload = $payload_builder->invoke( null, array( 'sync_mode' => 'refresh', 'post_ids' => array( '42', '501' ), 'documents' => array( array( 'post_id' => 501, 'source_type' => 'media', 'title' => 'Excluded image' ), array( 'post_id' => 42, 'comment_id' => 3, 'comment_status' => 'approved', 'content_excerpt' => 'Excluded parent comment' ), array( 'post_id' => 42, 'post_type' => 'post', 'content_excerpt' => 'Excluded post' ) ) ) );
Npcink_Cloud_Site_Knowledge_Runtime_Bridge::handle_toolbox_request( null, $payload, 'npcink-cloud/site-knowledge-sync', 'site_knowledge_sync.v1' );
$guarded = $cloud->mutations[0]['input'];
maca_assert( empty( $guarded['documents'] ) && empty( $guarded['post_ids'] ), 'K02-01 public Toolbox facade suppresses misplaced image/comment documents and numeric-string selectors before dispatch.' );

// Credential namespace switch neither transfers suppression nor erases old writer records.
$old_scope = Npcink_Cloud_Site_Knowledge_Change_Bridge::lifecycle_scope();
$old_records = typed_scope();
$settings = Npcink_Cloud_Addon_Settings::get_settings();
$settings['site_id'] = 'site_other'; Npcink_Cloud_Addon_Settings::write_settings( $settings );
maca_assert( $old_scope !== Npcink_Cloud_Site_Knowledge_Change_Bridge::lifecycle_scope() && ! Npcink_Cloud_Site_Knowledge_Change_Bridge::source_lifecycle_summary()['items'] && $old_records === typed_state()['_lifecycle']['scopes'][ $old_scope ], 'K02-06 switched site gets no old tombstone and retains the entire old credential namespace.' );

// Many known old runs are checked in bounded groups before one compensation.
$cloud = typed_reset(); maca_add_public_post_fixture( 42 ); $cloud->hold_producers = true;
for ( $i = 0; $i < 9; $i++ ) { $GLOBALS['maca_posts'][42]->post_content = 'Old writer ' . $i; Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 ); }
foreach ( $cloud->runs as &$run ) { $run['status'] = 'succeeded'; $run['finished_at'] = gmdate( 'c' ); } unset( $run );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() );
typed_tick( 2 );
$op = reset( typed_scope()['operations'] );
maca_assert( 4 === count( $op['old_terminal'] ) && 1 === $cloud->delete_calls, 'K02-05 first observation persists at most four old terminal witnesses without premature C1.' );
typed_tick( 8 );
maca_assert( 2 === $cloud->delete_calls && 'excluded' === typed_scope()['sources']['post:42']['state'], 'K02-05 all nine old writers finish before one distinct compensation.' );

// Poll budget is a stopping bound, never authority to discard an unknown writer.
$cloud = typed_reset(); maca_add_public_post_fixture( 42 ); $cloud->hold_producers = true;
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
$old_entry = reset( typed_scope()['dispatches'] );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() ); typed_tick( 125 );
$op = reset( typed_scope()['operations'] );
maca_assert( 'blocked' === $op['status'] && $old_entry === typed_scope()['dispatches'][ $old_entry['key'] ] && 'pending_delete' === typed_scope()['sources']['post:42']['state'] && 1 === $cloud->delete_calls, 'K02-02 exhaustion preserves run ID, exact payload/key and suppression without C2.' );

// Actual full cursor execution clears once, then sends image-only incremental batches.
$cloud = typed_reset(); maca_add_public_post_fixture( 42 ); typed_image();
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'rebuild' );
for ( $i = 0; $i < 6; $i++ ) { maca_run_site_knowledge_flush(); }
$inputs = array_column( $cloud->mutations, 'input' );
maca_assert( count( $inputs ) === 2 && 'rebuild' === $inputs[0]['sync_mode'] && 'refresh' === $inputs[1]['sync_mode'] && isset( $inputs[1]['media_items'] ) && ! isset( $inputs[1]['post_ids'], $inputs[1]['documents'] ) && array() === maca_legacy_full_cursor(), 'K01-07 actual full cursor finishes text clear then pure-image refresh on stable guarded transport.' );

// Pressure retirement: >500 actual distinct producer dispatches, preserving unresolved early writer.
$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
$cloud->hold_producers = true; Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
$unknown = reset( typed_scope()['dispatches'] ); $cloud->hold_producers = false;
$first_retired = null; $max_bytes = 0;
for ( $i = 0; $i < 520; $i++ ) {
	$GLOBALS['maca_posts'][42]->post_content = 'Continuous delivery ' . $i;
	$result = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
	maca_assert( ! is_wp_error( $result ), 'Lifetime: actual producer dispatch succeeds at sequence ' . $i );
	if ( 0 === $i ) { $entries = typed_scope()['dispatches']; $first_retired = end( $entries ); }
	$max_bytes = max( $max_bytes, strlen( serialize( typed_state() ) ) );
}
maca_assert( count( $cloud->mutations ) === 521 && count( typed_scope()['dispatches'] ) < 400 && $unknown === typed_scope()['dispatches'][ $unknown['key'] ] && ! isset( typed_scope()['dispatches'][ $first_retired['key'] ] ) && $max_bytes <= 4194304, 'Lifetime: >500 real fixture producers retire only terminal records, retain unknown exact bytes and stay under total envelope budget.' );
fwrite( STDOUT, '[metric] producer_lifetime=' . json_encode( array( 'actual_mutations' => count( $cloud->mutations ), 'retained_dispatches' => count( typed_scope()['dispatches'] ), 'max_serialized_bytes' => $max_bytes, 'final_serialized_bytes' => strlen( serialize( typed_state() ) ) ) ) . PHP_EOL );
$before_replay = count( $cloud->mutations );
$result = Npcink_Cloud_Site_Knowledge_Change_Bridge::dispatch_mutation( $first_retired['payload'] );
maca_assert( ! is_wp_error( $result ) && $cloud->replays === 1 && count( $cloud->mutations ) === $before_replay && end( typed_scope()['dispatches'] )['key'] === $first_retired['key'], 'Lifetime: retired identical producer reuses exact deterministic key, Cloud replay causes no new execution.' );

// >100 actual completed delete/restore cycles: no permanent operation cap, no old-run reuse after restore.
$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
for ( $i = 0; $i < 105; $i++ ) {
	$delete = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() ); typed_tick( 5 );
	$restore = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'restore', typed_selection() ); typed_tick( 6 );
	maca_assert( ! is_wp_error( $delete ) && ! is_wp_error( $restore ) && ! isset( typed_scope()['sources']['post:42'] ), 'Lifetime: completed exclude/restore cycle succeeds at sequence ' . $i );
}
maca_assert( count( typed_scope()['operations'] ) === 1 && count( typed_scope()['dispatches'] ) <= 1 && 105 === $cloud->delete_calls, 'Lifetime: >100 operations retire completed intent/payload history while tombstones govern the next approval.' );
fwrite( STDOUT, '[metric] operation_lifetime=' . json_encode( array( 'cycles' => 105, 'actual_mutations' => count( $cloud->mutations ), 'retained_operations' => count( typed_scope()['operations'] ), 'retained_dispatches' => count( typed_scope()['dispatches'] ), 'serialized_bytes' => strlen( serialize( typed_state() ) ) ) ) . PHP_EOL );
$before_producer = count( $cloud->mutations ); Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
$key_after_restore = end( typed_scope()['dispatches'] )['key'];
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() ); typed_tick( 7 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'restore', typed_selection() ); typed_tick( 7 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 );
maca_assert( $key_after_restore !== end( typed_scope()['dispatches'] )['key'] && count( $cloud->mutations ) > $before_producer + 3, 'Lifetime: completed restore explicitly advances producer generation, preventing reuse of a pre-delete success.' );
unset( $GLOBALS['wpdb'] );


$cloud = typed_reset(); maca_add_public_post_fixture( 42, 'page' ); $cloud->comments_enabled = true;
$GLOBALS['typed_public_comments'][42] = array( (object) array( 'comment_ID' => 9, 'comment_post_ID' => 42, 'comment_approved' => '1', 'comment_content' => 'Approved public comment', 'comment_date_gmt' => '2026-10-25 00:00:00', 'comment_author_email' => 'private@example.test' ) );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection( 'page' ) ); typed_tick( 5 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'restore', typed_selection( 'page' ) ); typed_tick( 7 );
$input = end( $cloud->mutations )['input'];
maca_assert( ! isset( typed_scope()['sources']['page:42'] ) && 'page' === $input['documents'][0]['post_type'] && 42 === $input['comments'][0]['post_id'] && ! isset( $input['comments'][0]['comment_author_email'] ) && 42 === $cloud->comment_parents[9], 'K01-08 restore retains real page type and approved comment association without private author fields.' );
$completed = end( typed_scope()['operations'] ); $completed_entry = end( typed_scope()['dispatches'] );
maca_assert( ! isset( $completed['restore_payloads'], $completed_entry['payload'] ) && isset( $completed_entry['fingerprint'], $completed_entry['key'], $completed_entry['run_id'] ), 'Lifetime: completed restore immediately drops body snapshots and preserves minimal replay evidence.' );

// Full and operation references protect terminal writers from pressure collection too.
$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
for ( $i = 0; $i < 399; $i++ ) { $GLOBALS['maca_posts'][42]->post_content = 'Protected writer ' . $i; Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 42 ); }
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() );
$protected = typed_scope()['dispatches'];
typed_tick( 1 );
maca_assert( count( typed_scope()['dispatches'] ) === 400 && array_intersect_key( typed_scope()['dispatches'], $protected ) === $protected, 'Lifetime: active operation protects all 399 old terminal producer envelopes from pressure retirement.' );

// Byte pressure uses real retained payloads. Unresolved attempts never age out to fit a new write.
$cloud = typed_reset(); maca_add_public_post_fixture( 42 ); $cloud->hold_producers = true;
$large = str_repeat( 'x', 700000 ); $accepted = 0; $blocked = false;
for ( $i = 0; $i < 8; $i++ ) {
	$payload = $payload_builder->invoke( null, array( 'sync_mode' => 'refresh', 'documents' => array( array( 'post_id' => 42, 'post_type' => 'post', 'post_status' => 'publish', 'content_excerpt' => $large . $i ) ) ) );
	$result = Npcink_Cloud_Site_Knowledge_Change_Bridge::dispatch_mutation( $payload );
	if ( is_wp_error( $result ) ) { $blocked = true; break; }
	$accepted++;
}
maca_assert( $blocked && $accepted >= 4 && count( typed_scope()['dispatches'] ) === $accepted && count( $cloud->mutations ) === $accepted && strlen( serialize( typed_state() ) ) <= 4194304, 'Lifetime: 4 MiB total serialized cap refuses a new send while retaining every unresolved 700 KB payload/key/run.' );
fwrite( STDOUT, '[metric] unresolved_byte_pressure=' . json_encode( array( 'accepted_mutations' => $accepted, 'payload_text_bytes' => 700000, 'serialized_bytes' => strlen( serialize( typed_state() ) ), 'budget' => 4194304 ) ) . PHP_EOL );
unset( $GLOBALS['wpdb'] );

require_once MACA_TEST_ROOT . '/includes/class-cloud-addon-localization.php';
if ( ! function_exists( 'is_admin' ) ) { function is_admin(): bool { return (bool) ( $GLOBALS['maca_is_admin'] ?? false ); } }
if ( ! function_exists( 'determine_locale' ) ) { function determine_locale(): string { return (string) ( $GLOBALS['maca_locale'] ?? 'en_US' ); } }
$GLOBALS['maca_is_admin'] = true; $GLOBALS['maca_locale'] = 'zh_CN';
foreach ( array(
	'Choose knowledge sources' => '选择知识库内容',
	'Exclude selected sources from Cloud and automatic delivery, or explicitly restore excluded sources. WordPress content and files stay unchanged.' => '从 Cloud 移除所选内容并停止自动投递，或明确恢复已排除内容。WordPress 内容和文件不会改变。',
	'Select up to 100 sources' => '最多选择 100 项内容',
	'Action' => '操作',
	'Exclude selected sources' => '排除所选内容',
	'Restore selected sources' => '恢复所选内容',
	'Type EXCLUDE or RESTORE to confirm the selected action' => '输入 EXCLUDE 或 RESTORE 确认所选操作',
	'Confirm selected sources' => '确认所选内容',
	'Waiting for complete Cloud evidence. Automatic delivery remains suppressed for these sources.' => '正在等待完整的 Cloud 核对结果。这些内容仍暂停自动投递。',
	'Confirm the selected sources and current site before continuing.' => '请确认所选内容和当前站点后继续。',
	'Select between 1 and 100 public sources.' => '请选择 1 至 100 项公开内容。',
	'Request saved. Sources stay excluded from automatic delivery while Cloud evidence is checked.' => '请求已保存。核对 Cloud 结果期间，这些内容仍暂停自动投递。',
	'Site Knowledge needs verification before this request can continue.' => '站点知识库需要核对后才能继续此请求。',
	'Checking removal' => '正在核对移除结果',
	'Excluded' => '已排除',
	'Checking restore' => '正在核对恢复结果',
	'Automatic delivery' => '自动投递',
 ) as $source => $translated ) {
	maca_assert( $translated === Npcink_Cloud_Addon_Localization::filter_gettext( $source, $source, 'npcink-cloud-addon' ) && 'Existing translation' === Npcink_Cloud_Addon_Localization::filter_gettext( 'Existing translation', $source, 'npcink-cloud-addon' ) && $source === Npcink_Cloud_Addon_Localization::filter_gettext( $source, $source, 'other-domain' ), 'Fixed copy: batch fallback preserves existing translations and other domains: ' . $source );
}


$cloud = typed_reset(); maca_add_public_post_fixture( 42 ); typed_image();
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_attachment_change( 501 );
$GLOBALS['maca_posts'][501]->post_status = 'trash';
maca_run_site_knowledge_flush();
$input = end( $cloud->mutations )['input'];
maca_assert( empty( $input['media_items'] ) && ! in_array( 501, $input['post_ids'] ?? array(), true ), 'K01-05 image losing public eligibility before dispatch never falls back to legacy numeric post_ids.' );
unset( $GLOBALS['wpdb'] );


$cloud = typed_reset(); maca_add_public_post_fixture( 42 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'exclude', typed_selection() );
$result = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'rebuild' );
maca_assert( is_wp_error( $result ) && array() === maca_legacy_full_cursor() && ! $cloud->mutations, 'REG-01 pending intent prevents a new full snapshot/clear in the same serialized CAS.' );
typed_tick( 5 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'rebuild' );
// A fully excluded corpus may have no rebuild candidates; add a second public source.
maca_add_public_post_fixture( 43 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'rebuild' );
$result = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_source_operation( 'restore', typed_selection() );
maca_assert( is_wp_error( $result ) && 'excluded' === typed_scope()['sources']['post:42']['state'], 'REG-01 unresolved full delivery prevents restore from racing a later site clear.' );
unset( $GLOBALS['wpdb'] );

// Old HTTP completion must acknowledge only the exact media revision it sent.
foreach ( array( 'alt', 'title', 'caption', 'description', 'url' ) as $changed_field ) {
	$cloud = typed_reset(); maca_add_public_post_fixture( 42 ); typed_image();
	$before_media = Npcink_Cloud_Site_Knowledge_Change_Bridge::public_source( typed_selection( 'media', 501 )[0] );
	Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_attachment_change( 501 );
	$during_buffer = array();
	$cloud->during_send = static function () use ( $changed_field, &$during_buffer ): void {
		if ( 'alt' === $changed_field ) {
			$GLOBALS['typed_media_alt'][501] = 'New public ALT edited during the old HTTP request';
			Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_attachment_meta_change( 1, 501, '_wp_attachment_image_alt' );
		} else {
			if ( 'url' === $changed_field ) { $GLOBALS['typed_media_urls'][501] = 'https://example.test/replaced-image.jpg'; }
			else {
				$post_field = array( 'title' => 'post_title', 'caption' => 'post_excerpt', 'description' => 'post_content' )[ $changed_field ];
				$GLOBALS['maca_posts'][501]->$post_field = 'New public ' . $changed_field . ' edited during the old HTTP request';
			}
			Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_attachment_change( 501 );
		}
		$during_buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
	};
	maca_run_site_knowledge_flush();
	$new_media = Npcink_Cloud_Site_Knowledge_Change_Bridge::public_source( typed_selection( 'media', 501 )[0] );
	$after_buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
	maca_assert( $before_media === $cloud->mutations[0]['input']['media_items'][0] && array( 501 ) === $during_buffer['post_ids'], 'Media revision: old immutable payload is sent while the real ' . $changed_field . ' hook buffers the newer revision.' );
	maca_assert( array( 501 ) === ( $after_buffer['post_ids'] ?? array() ), 'Media revision: old HTTP completion preserves the newer ' . $changed_field . ' revision in the bounded buffer.' );
	maca_run_site_knowledge_flush();
	$after_buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
	$keys = array_keys( $cloud->keys );
	maca_assert( 2 === count( $cloud->mutations ) && $new_media === $cloud->mutations[1]['input']['media_items'][0] && $before_media['media_fingerprint'] !== $new_media['media_fingerprint'] && $keys[0] !== $keys[1] && empty( $after_buffer['post_ids'] ), 'Media revision: next bounded flush sends the new ' . $changed_field . ' value with its own semantic key and then clears that revision.' );
	maca_run_site_knowledge_flush();
	maca_assert( 2 === count( $cloud->mutations ), 'Media revision: unchanged ' . $changed_field . ' does not create a third dispatch.' );
}

$cloud = typed_reset(); maca_add_public_post_fixture( 42 ); typed_image();
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_attachment_change( 501 );
$cloud->during_send = static function (): void {
	Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_attachment_meta_change( 1, 501, '_wp_attachment_image_alt' );
};
maca_run_site_knowledge_flush();
$after_buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
maca_run_site_knowledge_flush();
maca_assert( 1 === count( $cloud->mutations ) && empty( $after_buffer['post_ids'] ), 'Media revision: an in-flight hook with unchanged actual metadata is acknowledged normally without redundant delivery.' );
unset( $GLOBALS['wpdb'] );
