<?php
/**
 * Behavior tests for the Site Knowledge change bridge.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();
require_once MACA_TEST_ROOT . '/includes/class-cloud-site-knowledge-change-bridge.php';

$GLOBALS['maca_comments'] = array();
$GLOBALS['maca_posts'] = array();
$GLOBALS['maca_scheduled_events'] = array();
$GLOBALS['maca_post_terms'] = array();

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( int $post_id, string $taxonomy, array $args = array() ): array {
		unset( $args );
		return $GLOBALS['maca_post_terms'][ $post_id ][ $taxonomy ] ?? array();
	}
}

if ( ! function_exists( 'get_posts' ) ) {
	/**
	 * Minimal get_posts stub for bridge behavior tests.
	 *
	 * @param array<string,mixed> $args Query args.
	 * @return array<int,int>
	 */
	function get_posts( array $args = array() ): array {
		$limit = absint( $args['posts_per_page'] ?? 50 );
		$date_query = is_array( $args['date_query'][0] ?? null ) ? $args['date_query'][0] : array();
		$modified_after = strtotime( (string) ( $date_query['after'] ?? '' ) );
		$ids   = array();
		foreach ( $GLOBALS['maca_posts'] as $post_id => $post ) {
			if ( 'publish' !== (string) ( $post->post_status ?? '' ) ) {
				continue;
			}
			$post_modified = strtotime( (string) ( $post->post_modified_gmt ?? '' ) . ' UTC' );
			if ( false !== $modified_after && false !== $post_modified && $post_modified <= $modified_after ) {
				continue;
			}
			$ids[] = absint( $post_id );
		}
		return array_slice( $ids, 0, max( 1, $limit ) );
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * Minimal get_post stub for bridge behavior tests.
	 *
	 * @param int $post_id Post id.
	 * @return object|null
	 */
	function get_post( int $post_id ) {
		return $GLOBALS['maca_posts'][ $post_id ] ?? null;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * Minimal get_the_title stub for bridge behavior tests.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	function get_the_title( int $post_id ): string {
		$post = get_post( $post_id );
		return is_object( $post ) ? (string) ( $post->post_title ?? '' ) : '';
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * Minimal get_permalink stub for bridge behavior tests.
	 *
	 * @param int $post_id Post id.
	 * @return string
	 */
	function get_permalink( int $post_id ): string {
		return 'https://example.test/?p=' . absint( $post_id );
	}
}

if ( ! function_exists( 'get_comment' ) ) {
	/**
	 * Minimal get_comment stub for bridge behavior tests.
	 *
	 * @param int $comment_id Comment id.
	 * @return object|null
	 */
	function get_comment( int $comment_id ) {
		return $GLOBALS['maca_comments'][ $comment_id ] ?? null;
	}
}

if ( ! function_exists( 'wp_next_scheduled' ) ) {
	/**
	 * Minimal scheduler lookup stub.
	 *
	 * @param string $hook Hook name.
	 * @return int|false
	 */
	function wp_next_scheduled( string $hook ) {
		return $GLOBALS['maca_scheduled_events'][ $hook ] ?? false;
	}
}

if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	/**
	 * Minimal single-event scheduling stub.
	 *
	 * @param int    $timestamp Event timestamp.
	 * @param string $hook Hook name.
	 * @return bool
	 */
	function wp_schedule_single_event( int $timestamp, string $hook ): bool {
		$GLOBALS['maca_scheduled_events'][ $hook ] = $timestamp;
		return true;
	}
}

if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	/**
	 * Minimal scheduled-hook clearing stub.
	 *
	 * @param string $hook Hook name.
	 * @return void
	 */
	function wp_clear_scheduled_hook( string $hook ): void {
		unset( $GLOBALS['maca_scheduled_events'][ $hook ] );
	}
}

/**
 * Resets Site Knowledge bridge behavior state.
 *
 * @return void
 */
function maca_reset_site_knowledge_bridge_state(): void {
	maca_reset_test_state();
	$GLOBALS['maca_comments'] = array();
	$GLOBALS['maca_posts'] = array();
	$GLOBALS['maca_scheduled_events'] = array();
	$GLOBALS['maca_post_terms'] = array();
}

/**
 * Adds a public post/page fixture.
 *
 * @param int    $post_id Post id.
 * @param string $type Post type.
 * @return void
 */
function maca_add_public_post_fixture( int $post_id, string $type = 'post' ): void {
	$GLOBALS['maca_posts'][ $post_id ] = (object) array(
		'ID'                => $post_id,
		'post_type'         => $type,
		'post_status'       => 'publish',
		'post_password'     => '',
		'post_title'        => 'Public fixture ' . $post_id,
		'post_excerpt'      => 'Excerpt ' . $post_id,
		'post_content'      => 'Public content for Site Knowledge ' . $post_id,
		'post_modified_gmt' => '2026-06-30 00:00:00',
	);
}

/**
 * Queues deterministic inline Cloud successes for Site Knowledge delivery.
 *
 * @param int $count Response count.
 * @return void
 */
function maca_queue_site_knowledge_inline_success( int $count = 1 ): void {
	for ( $index = 0; $index < $count; $index++ ) {
		$GLOBALS['maca_http_response_queue'][] = array(
			'response' => array( 'code' => 200 ),
			'body' => wp_json_encode(
				array(
					'status' => 'ok',
					'data' => array( 'status' => 'succeeded' ),
				)
			),
		);
	}
}

/**
 * Simulates WordPress consuming one scheduled single Cron event before callback.
 *
 * @return array<string,mixed>
 */
function maca_run_site_knowledge_flush(): array {
	unset(
		$GLOBALS['maca_scheduled_events'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK ],
		$GLOBALS['maca_scheduled_event_schedules'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK ]
	);

	return Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
}

maca_reset_site_knowledge_bridge_state();
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted(
	101,
	1,
	array( 'comment_post_ID' => 701 )
);
maca_assert(
	array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() ),
	'Behavior: Site Knowledge comment bridge is disabled until Cloud settings are configured.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( false );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted(
	1001,
	1,
	array( 'comment_post_ID' => 7001 )
);
$health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
maca_assert(
	array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() )
	&& false === (bool) ( $health['enabled'] ?? true )
	&& true === (bool) ( $health['configured'] ?? false )
	&& false === (bool) ( $health['verified'] ?? true )
	&& 'unverified' === (string) ( $health['status'] ?? '' ),
	'Behavior: Site Knowledge comment bridge waits for verified Cloud settings before buffering.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted(
	102,
	0,
	array( 'comment_post_ID' => 702 )
);
maca_assert(
	array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() ),
	'Behavior: Site Knowledge comment bridge ignores unapproved new comments.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted(
	103,
	1,
	array( 'comment_post_ID' => 703 )
);
$buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
$status = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION, array() );
$health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
maca_assert(
	is_array( $buffer )
	&& array( 703 ) === array_map( 'absint', (array) ( $buffer['post_ids'] ?? array() ) )
	&& 703 === absint( $status['last_post_id'] ?? 0 )
	&& 1 === absint( $status['buffer_count'] ?? 0 )
	&& 1 === absint( $health['buffer_count'] ?? 0 )
	&& 'site_knowledge_change_bridge_status.v1' === (string) ( $health['status_contract'] ?? '' )
	&& 'change_bridge' === (string) ( $health['preferred_status_field'] ?? '' )
	&& 'buffer_count' === (string) ( $health['preferred_count_field'] ?? '' )
	&& 'bounded_delivery_buffer' === (string) ( $health['buffer_semantics'] ?? '' )
	&& 'local_delivery_durability_only' === (string) ( $health['buffer_truth'] ?? '' )
		&& 'cloud_addon' === (string) ( $health['delivery_truth_owner'] ?? '' )
		&& 'cloud_service' === (string) ( $health['index_execution_owner'] ?? '' )
		&& 'local_wordpress_host' === (string) ( $health['ownership']['source_content_owner'] ?? '' )
		&& 'cloud_addon' === (string) ( $health['ownership']['delivery_bridge_owner'] ?? '' )
		&& 'cloud_service' === (string) ( $health['ownership']['vector_storage_owner'] ?? '' )
		&& 'local_wordpress_host' === (string) ( $health['ownership']['final_write_owner'] ?? '' )
		&& true === (bool) ( $health['truth_boundaries']['cloud_is_index_truth'] ?? false )
		&& false === (bool) ( $health['truth_boundaries']['cloud_is_wordpress_control_plane'] ?? true )
		&& false === (bool) ( $health['truth_boundaries']['cloud_creates_wordpress_writes'] ?? true )
		&& 'suggestion_only' === (string) ( $health['write_posture'] ?? '' )
		&& false === (bool) ( $health['wordpress_write_included'] ?? true )
	&& true === (bool) ( $health['enabled'] ?? false )
	&& 'queued' === (string) ( $health['status'] ?? '' )
	&& false === (bool) ( $health['legacy_toolbox_fallback'] ?? true )
	&& false !== wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK ),
	'Behavior: approved new comment data buffers the parent post for suggestion-only Site Knowledge refresh.'
);

$buffer_writes = absint( $GLOBALS['maca_option_update_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION ] ?? 0 );
$status_writes = absint( $GLOBALS['maca_option_update_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION ] ?? 0 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted(
	103,
	1,
	array( 'comment_post_ID' => 703 )
);
maca_assert(
	$buffer_writes === absint( $GLOBALS['maca_option_update_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION ] ?? 0 )
	&& $status_writes === absint( $GLOBALS['maca_option_update_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION ] ?? 0 ),
	'Behavior: duplicate Site Knowledge changes do not rewrite buffer or status options.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
$GLOBALS['maca_comments'][104] = (object) array(
	'comment_ID'      => 104,
	'comment_post_ID' => 704,
);
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted( 104, 1, null );
$buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
$status = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION, array() );
maca_assert(
	is_array( $buffer )
	&& array( 704 ) === array_map( 'absint', (array) ( $buffer['post_ids'] ?? array() ) )
	&& 704 === absint( $status['last_post_id'] ?? 0 ),
	'Behavior: approved new comment fallback lookup buffers the parent post.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_status_transition(
	'approved',
	'hold',
	(object) array( 'comment_post_ID' => 705 )
);
$buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
maca_assert(
	is_array( $buffer )
	&& array( 705 ) === array_map( 'absint', (array) ( $buffer['post_ids'] ?? array() ) ),
	'Behavior: comment approval transition buffers the parent post.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
$GLOBALS['maca_comments'][105] = (object) array(
	'comment_ID'       => 105,
	'comment_post_ID'  => 715,
	'comment_approved' => '0',
);
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_edited_comment( 105, array( 'comment_post_ID' => 715 ) );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_edited_comment( 999, array( 'comment_post_ID' => 799 ) );
maca_assert(
	array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() ),
	'Behavior: unapproved or unavailable comments cannot trigger an edited-comment refresh.'
);

$GLOBALS['maca_comments'][106] = (object) array(
	'comment_ID'       => 106,
	'comment_post_ID'  => 716,
	'comment_approved' => '1',
);
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_edited_comment( 106, null );
$buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
maca_assert(
	array( 716 ) === array_map( 'absint', (array) ( $buffer['post_ids'] ?? array() ) ),
	'Behavior: approved comment edits refresh their public parent post.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
$GLOBALS['maca_comments'][107] = (object) array(
	'comment_ID'       => 107,
	'comment_post_ID'  => 717,
	'comment_approved' => '1',
);
Npcink_Cloud_Site_Knowledge_Change_Bridge::capture_comment_removal_context( 107 );
unset( $GLOBALS['maca_comments'][107] );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_removed_comment( 107 );
$buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
maca_assert(
	array( 717 ) === array_map( 'absint', (array) ( $buffer['post_ids'] ?? array() ) ),
	'Behavior: approved comment deletion uses the captured pre-delete parent and approval state.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
$GLOBALS['maca_comments'][108] = (object) array(
	'comment_ID'       => 108,
	'comment_post_ID'  => 718,
	'comment_approved' => 'spam',
);
Npcink_Cloud_Site_Knowledge_Change_Bridge::capture_comment_removal_context( 108 );
unset( $GLOBALS['maca_comments'][108] );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_removed_comment( 108 );
maca_assert(
	array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() ),
	'Behavior: deleting a non-public comment does not refresh Site Knowledge.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 719 );
$GLOBALS['maca_posts'][720] = (object) array(
	'ID'            => 720,
	'post_type'     => 'post',
	'post_status'   => 'draft',
	'post_password' => '',
);
maca_add_public_post_fixture( 721 );
$GLOBALS['maca_posts'][721]->post_password = 'protected';
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_removed_post( 720 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_removed_post( 721 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_removed_post( 719 );
$buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
maca_assert(
	array( 719 ) === array_map( 'absint', (array) ( $buffer['post_ids'] ?? array() ) ),
	'Behavior: only previously public allowed posts emit removal hints; drafts and password-protected posts are ignored.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_set_site_knowledge_delivery_enabled( false );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_comment_posted(
	106,
	1,
	array( 'comment_post_ID' => 706 )
);
$health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
maca_assert(
	array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() )
	&& false === (bool) ( $health['enabled'] ?? true )
	&& false === (bool) ( $health['delivery_enabled'] ?? true )
	&& 'disabled' === (string) ( $health['status'] ?? '' ),
	'Behavior: disabled Site Knowledge delivery consent stops automatic public content buffering.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 710 );
maca_add_public_post_fixture( 711 );
$GLOBALS['maca_posts'][710]->post_modified_gmt = '2026-06-30 00:00:00';
$GLOBALS['maca_posts'][711]->post_modified_gmt = '2026-07-02 00:00:00';
update_option(
	Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION,
	array(
		'last_delivery_ok' => true,
		'last_delivered_at' => '2026-07-01T00:00:00+00:00',
	),
	false
);
Npcink_Cloud_Site_Knowledge_Change_Bridge::buffer_recent_public_content();
$reconciled_buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
maca_assert(
	array( 711 ) === array_values( $reconciled_buffer['post_ids'] ?? array() ),
	'Behavior: hourly Site Knowledge reconciliation buffers only public content modified after the last successful delivery.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
for ( $post_id = 800; $post_id <= 850; $post_id++ ) {
	maca_add_public_post_fixture( $post_id );
	$GLOBALS['maca_posts'][ $post_id ]->post_modified_gmt = sprintf( '2026-07-10 00:00:%02d', $post_id - 800 );
}
Npcink_Cloud_Site_Knowledge_Change_Bridge::buffer_recent_public_content();
$first_reconcile_buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
Npcink_Cloud_Site_Knowledge_Change_Bridge::buffer_recent_public_content();
$second_reconcile_buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
$reconcile_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::RECONCILIATION_OPTION, array() );
maca_assert(
	50 === count( (array) ( $first_reconcile_buffer['post_ids'] ?? array() ) )
	&& 51 === count( (array) ( $second_reconcile_buffer['post_ids'] ?? array() ) )
	&& 850 === absint( $reconcile_cursor['post_id'] ?? 0 ),
	'Behavior: reconciliation advances a durable ordered cursor across more than one page instead of limiting each run to the newest 50 changes.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
update_option(
	Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION,
	array(
		'post_ids' => array( 707 ),
		'attempts' => 2,
	),
	false
);
update_option(
	Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION,
	array(
		'last_delivery_ok' => false,
		'last_delivery_error' => 'Cloud active run limit reached',
		'last_error_code' => 'delivery_failed_retry_scheduled',
		'last_error_at' => '2026-07-06T00:00:00+00:00',
	),
	false
);
$error_health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
maca_assert(
	'error' === (string) ( $error_health['status'] ?? '' )
	&& 'site_knowledge_bridge_health.v1' === (string) ( $error_health['health_detail_version'] ?? '' )
	&& 2 === absint( $error_health['delivery_attempts'] ?? 0 )
	&& 3 === absint( $error_health['max_delivery_attempts'] ?? 0 )
	&& 'delivery_failed_retry_scheduled' === (string) ( $error_health['last_error_code'] ?? '' )
	&& '2026-07-06T00:00:00+00:00' === (string) ( $error_health['last_error_at'] ?? '' )
	&& false === (bool) ( $error_health['scheduler_truth'] ?? true )
	&& false === (bool) ( $error_health['workflow_truth'] ?? true )
	&& 'cloud_service' === (string) ( $error_health['freshness_policy_owner'] ?? '' )
	&& 'cloud_service' === (string) ( $error_health['diagnostics_detail_owner'] ?? '' ),
	'Behavior: Site Knowledge bridge health exposes retry and error detail without becoming lifecycle truth.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 706 );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array() ) ),
);
$single_refresh = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 706 );
$single_refresh_body = json_decode( (string) ( $GLOBALS['maca_http_requests'][0]['args']['body'] ?? '' ), true );
maca_assert(
	is_array( $single_refresh )
	&& array( 706 ) === ( $single_refresh_body['input']['post_ids'] ?? array() )
	&& 'article_refresh' === (string) ( $single_refresh_body['input']['operation_source'] ?? '' )
	&& 1 === count( (array) ( $single_refresh_body['input']['documents'] ?? array() ) ),
	'Behavior: single-article refresh sends exactly one currently public document through the existing transport.'
);

$GLOBALS['maca_posts'][706]->post_status = 'draft';
$request_count_before_draft = count( $GLOBALS['maca_http_requests'] );
$draft_refresh = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_public_post_refresh( 706 );
maca_assert(
	is_wp_error( $draft_refresh )
	&& 'cloud_site_knowledge_article_not_public' === $draft_refresh->get_error_code()
	&& $request_count_before_draft === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: single-article refresh rejects non-public content without Cloud traffic.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 708 );
update_option(
	Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION,
	array(
		'post_ids' => array( 708 ),
		'attempts' => 0,
	),
	false
);
$GLOBALS['maca_http_response_queue'][] = new WP_Error( 'http_request_failed', 'Connection outcome was uncertain.' );
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
$first_change_key = (string) ( $GLOBALS['maca_http_requests'][0]['args']['headers']['Idempotency-Key'] ?? '' );
maca_add_public_post_fixture( 709 );
$GLOBALS['maca_http_response_queue'][] = static function ( string $url, array $args ): array {
	unset( $url, $args );
	$GLOBALS['maca_posts'][708]->post_content = 'Changed while the original Site Knowledge payload was in flight.';
	Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_saved_post( 708, $GLOBALS['maca_posts'][708] );
	Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_saved_post( 709, $GLOBALS['maca_posts'][709] );

	return array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode( array( 'status' => 'ok', 'data' => array() ) ),
	);
};
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
$retry_change_key = (string) ( $GLOBALS['maca_http_requests'][1]['args']['headers']['Idempotency-Key'] ?? '' );
$change_buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
maca_assert(
	'' !== $first_change_key
	&& $first_change_key === $retry_change_key
	&& array( 708, 709 ) === (array) ( $change_buffer['post_ids'] ?? array() ),
	'Behavior: an uncertain Site Knowledge retry preserves same-id content changes and new ids buffered during HTTP.'
);
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
$changed_payload_key = (string) ( $GLOBALS['maca_http_requests'][2]['args']['headers']['Idempotency-Key'] ?? '' );
$change_buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
maca_assert(
	$changed_payload_key !== $retry_change_key
	&& array() === (array) ( $change_buffer['post_ids'] ?? array() ),
	'Behavior: changed Site Knowledge content receives a new key and clears only after that version is delivered.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
for ( $post_id = 8000; $post_id <= 8025; $post_id++ ) {
	maca_add_public_post_fixture( $post_id );
}
update_option(
	Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION,
	array(
		'post_ids' => range( 8000, 8025 ),
		'attempts' => 2,
	),
	false
);
$GLOBALS['maca_http_response_queue'][] = new WP_Error( 'http_request_failed', 'Final bounded attempt failed.' );
$exhausted = Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
$exhausted_buffer = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
$exhausted_health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
maca_assert(
	'delivery_attempts_exhausted' === (string) ( $exhausted['last_error_code'] ?? '' )
	&& array( 8025 ) === (array) ( $exhausted_buffer['post_ids'] ?? array() )
	&& 0 === absint( $exhausted_buffer['attempts'] ?? 0 )
	&& 25 === absint( $exhausted_health['dropped_count'] ?? 0 )
	&& '' !== (string) ( $exhausted_health['last_dropped_at'] ?? '' ),
	'Behavior: exhausting one Site Knowledge batch preserves later buffered work.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
for ( $post_id = 9000; $post_id <= 9500; $post_id++ ) {
	maca_add_public_post_fixture( $post_id );
	Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_saved_post( $post_id, $GLOBALS['maca_posts'][ $post_id ] );
}
$overflow_health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
maca_assert(
	500 === absint( $overflow_health['buffer_count'] ?? 0 )
	&& 1 === absint( $overflow_health['dropped_count'] ?? 0 ),
	'Behavior: bounded Site Knowledge buffer overflow counts the oldest change notification that can no longer be retained.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_set_site_knowledge_delivery_enabled( false );
maca_add_public_post_fixture( 750 );
$disabled_start = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
maca_assert(
	is_wp_error( $disabled_start )
	&& 'cloud_site_knowledge_delivery_disabled' === $disabled_start->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: disabled Site Knowledge delivery consent blocks administrator start indexing transport.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_set_site_knowledge_delivery_enabled( false );
maca_add_public_post_fixture( 751 );
$disabled_rebuild = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'rebuild' );
maca_assert(
	is_wp_error( $disabled_rebuild )
	&& 'cloud_site_knowledge_delivery_disabled' === $disabled_rebuild->get_error_code()
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: disabled Site Knowledge delivery consent blocks administrator rebuild transport.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_set_site_knowledge_delivery_enabled( false );
$disabled_delete_status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'delete' );
$disabled_delete_request = $GLOBALS['maca_http_requests'][0] ?? array();
$disabled_delete_body = json_decode( (string) ( $disabled_delete_request['args']['body'] ?? '' ), true );
$disabled_delete_body = is_array( $disabled_delete_body ) ? $disabled_delete_body : array();
maca_assert(
	is_array( $disabled_delete_status )
	&& 'delete' === (string) ( $disabled_delete_body['input']['sync_mode'] ?? '' )
	&& 'admin_delete' === (string) ( $disabled_delete_body['input']['operation_source'] ?? '' ),
	'Behavior: disabled Site Knowledge delivery consent still allows explicit delete index cleanup.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 801 );
maca_add_public_post_fixture( 802, 'page' );
$GLOBALS['maca_post_terms'][801] = array(
	'category' => array( 'Cloud Runtime', 'WordPress AI' ),
	'post_tag' => array( 'Site Knowledge', 'Writing' ),
);
$start_status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
$start_http_after_queue = count( $GLOBALS['maca_http_requests'] );
$start_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
$start_health = Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot();
maca_queue_site_knowledge_inline_success();
maca_run_site_knowledge_flush();
$start_request = $GLOBALS['maca_http_requests'][0] ?? array();
$start_body = json_decode( (string) ( $start_request['args']['body'] ?? '' ), true );
$start_body = is_array( $start_body ) ? $start_body : array();
$start_documents = (array) ( $start_body['input']['documents'] ?? array() );
$first_start_document = is_array( $start_documents[0] ?? null ) ? $start_documents[0] : array();
maca_assert(
	is_array( $start_status )
	&& 0 === $start_http_after_queue
	&& 'queued' === (string) ( $start_cursor['status'] ?? '' )
	&& 'start' === (string) ( $start_cursor['operation'] ?? '' )
	&& 'admin_start' === (string) ( $start_cursor['operation_source'] ?? '' )
	&& 1 === absint( $start_cursor['batch_count'] ?? 0 )
	&& 'queued' === (string) ( $start_health['status'] ?? '' )
	&& 'queued' === (string) ( $start_health['maintenance_status'] ?? '' )
	&& false !== strpos( (string) ( $start_request['url'] ?? '' ), '/v1/runtime/execute' )
	&& 'refresh' === (string) ( $start_body['input']['sync_mode'] ?? '' )
	&& 'admin_start' === (string) ( $start_body['input']['operation_source'] ?? '' )
	&& 2 === count( $start_documents )
	&& 'publish' === (string) ( $first_start_document['post_status'] ?? '' )
	&& 'Public content for Site Knowledge 801' === (string) ( $first_start_document['content_excerpt'] ?? '' )
	&& array( 'Cloud Runtime', 'WordPress AI' ) === (array) ( $first_start_document['taxonomies']['category'] ?? array() )
	&& array( 'Site Knowledge', 'Writing' ) === (array) ( $first_start_document['taxonomies']['post_tag'] ?? array() )
	&& ! array_key_exists( 'status', $first_start_document )
	&& ! array_key_exists( 'body', $first_start_document )
	&& ! array_key_exists( 'comments', $first_start_document )
	&& 'start' === (string) ( $start_status['last_index_action'] ?? '' )
	&& 'queued' === (string) ( $start_status['last_index_action_status'] ?? '' )
	&& 2 === absint( $start_status['last_index_action_selected_count'] ?? 0 )
	&& 0 === absint( $start_status['last_index_action_sent_count'] ?? 99 )
	&& array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() ),
	'Behavior: administrator start queues zero-HTTP bounded delivery, then sends the canonical public document contract through Cron.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 901 );
$rebuild_status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'rebuild' );
$rebuild_http_after_queue = count( $GLOBALS['maca_http_requests'] );
maca_queue_site_knowledge_inline_success();
maca_run_site_knowledge_flush();
$rebuild_request = $GLOBALS['maca_http_requests'][0] ?? array();
$rebuild_body = json_decode( (string) ( $rebuild_request['args']['body'] ?? '' ), true );
$rebuild_body = is_array( $rebuild_body ) ? $rebuild_body : array();
maca_assert(
	is_array( $rebuild_status )
	&& 0 === $rebuild_http_after_queue
	&& 'rebuild' === (string) ( $rebuild_body['input']['sync_mode'] ?? '' )
	&& 'admin_rebuild' === (string) ( $rebuild_body['input']['operation_source'] ?? '' )
	&& 'suggestion_only' === (string) ( $rebuild_body['input']['write_posture'] ?? '' )
	&& false === (bool) ( $rebuild_body['input']['direct_wordpress_write'] ?? true )
	&& 'rebuild' === (string) ( $rebuild_status['last_index_action'] ?? '' )
	&& 0 === absint( $rebuild_status['last_index_action_sent_count'] ?? 99 ),
	'Behavior: administrator rebuild queues first, then forwards only public manifests and no-write posture to Cloud.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
for ( $post_id = 10000; $post_id < 10605; $post_id++ ) {
	maca_add_public_post_fixture( $post_id );
	$GLOBALS['maca_posts'][ $post_id ]->post_content = str_repeat( '中', 2000 );
}
$full_rebuild_status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'rebuild' );
$full_rebuild_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
$full_rebuild_http_increments = array();
maca_queue_site_knowledge_inline_success( 4 );
for ( $batch_index = 0; $batch_index < 4; $batch_index++ ) {
	$request_count = count( $GLOBALS['maca_http_requests'] );
	$full_rebuild_final_status = maca_run_site_knowledge_flush();
	$full_rebuild_http_increments[] = count( $GLOBALS['maca_http_requests'] ) - $request_count;
}
$full_rebuild_bodies = array_map(
	static function ( array $request ): array {
		$body = json_decode( (string) ( $request['args']['body'] ?? '' ), true );
		return is_array( $body ) ? $body : array();
	},
	$GLOBALS['maca_http_requests']
);
$full_rebuild_document_ids = array();
$full_rebuild_body_sizes = array();
foreach ( $full_rebuild_bodies as $body ) {
	$full_rebuild_body_sizes[] = strlen( (string) wp_json_encode( $body ) );
	foreach ( (array) ( $body['input']['documents'] ?? array() ) as $document ) {
		$full_rebuild_document_ids[] = absint( $document['post_id'] ?? 0 );
	}
}
maca_assert(
	is_array( $full_rebuild_status )
	&& array() === $GLOBALS['maca_http_response_queue']
	&& 4 === absint( $full_rebuild_cursor['batch_count'] ?? 0 )
	&& array( 1, 1, 1, 1 ) === $full_rebuild_http_increments
	&& 4 === count( $full_rebuild_bodies )
	&& 'rebuild' === (string) ( $full_rebuild_bodies[0]['input']['sync_mode'] ?? '' )
	&& array() === (array) ( $full_rebuild_bodies[0]['input']['post_ids'] ?? array() )
	&& 200 === count( (array) ( $full_rebuild_bodies[0]['input']['documents'] ?? array() ) )
	&& 'refresh' === (string) ( $full_rebuild_bodies[1]['input']['sync_mode'] ?? '' )
	&& 200 === count( (array) ( $full_rebuild_bodies[1]['input']['post_ids'] ?? array() ) )
	&& 'refresh' === (string) ( $full_rebuild_bodies[2]['input']['sync_mode'] ?? '' )
	&& 200 === count( (array) ( $full_rebuild_bodies[2]['input']['post_ids'] ?? array() ) )
	&& 'refresh' === (string) ( $full_rebuild_bodies[3]['input']['sync_mode'] ?? '' )
	&& 5 === count( (array) ( $full_rebuild_bodies[3]['input']['post_ids'] ?? array() ) )
	&& 605 === count( $full_rebuild_document_ids )
	&& 605 === count( array_unique( $full_rebuild_document_ids ) )
	&& max( $full_rebuild_body_sizes ) < 900000
	&& 0 === absint( $full_rebuild_status['last_index_action_sent_count'] ?? 99 )
	&& 605 === absint( $full_rebuild_final_status['last_index_action_sent_count'] ?? 0 )
	&& 'completed' === (string) ( $full_rebuild_final_status['last_index_action_status'] ?? '' )
	&& 4 === absint( $full_rebuild_final_status['last_index_action_batch_count'] ?? 0 ),
	'Behavior: administrator rebuild covers the bounded full public corpus with at most one Cloud request per Cron invocation.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 10999 );
wp_schedule_single_event( time() + 180, Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK );
$expedite_started_at = time();
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
$expedited_flush = wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK );
maca_assert(
	false !== $expedited_flush
	&& (int) $expedited_flush <= $expedite_started_at + 2
	&& 2 === absint( $GLOBALS['maca_schedule_call_counts'][ Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK ] ?? 0 )
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: an administrator full-index request advances a later debounce event without synchronous Cloud HTTP.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 11000 );
$preserved_flush = time() + 180;
wp_schedule_single_event( $preserved_flush, Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK );
$GLOBALS['maca_schedule_single_failures_remaining'] = 1;
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
maca_assert(
	$preserved_flush === wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK )
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: a failed attempt to advance the flush restores the existing scheduled event.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 11001 );
$active_status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
$active_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
$overlap_rebuild = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'rebuild' );
$overlap_delete = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'delete' );
Npcink_Cloud_Site_Knowledge_Change_Bridge::maybe_schedule_automatic_rebuild(
	array(
		'maintenance' => array(
			'action' => 'full_sync',
			'automatic' => true,
			'status' => 'awaiting_site',
			'request_id' => 'skm_overlap_automatic',
		),
	)
);
maca_assert(
	is_array( $active_status )
	&& is_wp_error( $overlap_rebuild )
	&& 'cloud_site_knowledge_delivery_in_progress' === $overlap_rebuild->get_error_code()
	&& is_wp_error( $overlap_delete )
	&& 'cloud_site_knowledge_delivery_in_progress' === $overlap_delete->get_error_code()
	&& $active_cursor === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() )
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: active full-index delivery cannot be overwritten by manual or automatic operations.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
for ( $post_id = 11010; $post_id <= 11210; $post_id++ ) {
	maca_add_public_post_fixture( $post_id );
}
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
$stale_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
$replacement_cursor = array(
	'status' => 'queued',
	'request_id' => 'newer_admin_request',
	'operation' => 'rebuild',
	'operation_source' => 'admin_rebuild',
	'post_ids' => array( 11010 ),
	'next_batch' => 0,
	'batch_count' => 1,
	'attempts' => 0,
	'created_at' => gmdate( 'c' ),
);
$GLOBALS['maca_http_response_queue'][] = static function () use ( $replacement_cursor ): array {
	delete_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION );
	add_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, $replacement_cursor, '', false );
	wp_schedule_single_event( time() + 1, Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK );

	return array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode(
			array(
				'status' => 'ok',
				'data' => array( 'status' => 'succeeded' ),
			)
		),
	);
};
$stale_flush_status = maca_run_site_knowledge_flush();
maca_assert(
	2 === absint( $stale_cursor['batch_count'] ?? 0 )
	&& $replacement_cursor === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() )
	&& false !== wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK )
	&& 0 === absint( $stale_flush_status['last_index_action_sent_count'] ?? 0 ),
	'Behavior: a stale Cron callback cannot overwrite a newer full-index cursor after Cloud returns.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 11101 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
$GLOBALS['maca_http_response_queue'][] = new WP_Error( 'http_request_failed', 'Retry this exact batch.' );
maca_run_site_knowledge_flush();
$first_manual_key = (string) ( $GLOBALS['maca_http_requests'][0]['args']['headers']['Idempotency-Key'] ?? '' );
maca_queue_site_knowledge_inline_success();
$manual_retry_status = maca_run_site_knowledge_flush();
$second_manual_key = (string) ( $GLOBALS['maca_http_requests'][1]['args']['headers']['Idempotency-Key'] ?? '' );
maca_assert(
	'' !== $first_manual_key
	&& $first_manual_key === $second_manual_key
	&& 'completed' === (string) ( $manual_retry_status['last_index_action_status'] ?? '' )
	&& array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() ),
	'Behavior: uncertain manual full-index retries reuse the exact batch idempotency key.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 111015 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array( 'run_id' => 'Run.Manual_Mixed-Case.1' ),
		)
	),
);
maca_run_site_knowledge_flush();
$accepted_run_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array(
				'run' => array( 'status' => 'submitted' ),
			),
		)
	),
);
$submitted_poll_status = maca_run_site_knowledge_flush();
$submitted_poll_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
$submitted_poll_request = $GLOBALS['maca_http_requests'][1] ?? array();
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array(
				'run' => array( 'status' => 'running' ),
			),
		)
	),
);
$running_poll_status = maca_run_site_knowledge_flush();
$running_poll_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
$running_poll_scheduled = wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK );
$running_poll_request = $GLOBALS['maca_http_requests'][2] ?? array();
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array(
				'run' => array( 'status' => 'completed' ),
			),
		)
	),
);
$running_poll_final_status = maca_run_site_knowledge_flush();
maca_assert(
	'Run.Manual_Mixed-Case.1' === (string) ( $accepted_run_cursor['pending_run_id'] ?? '' )
	&& 'Run.Manual_Mixed-Case.1' === (string) ( $submitted_poll_cursor['pending_run_id'] ?? '' )
	&& 1 === absint( $submitted_poll_cursor['poll_generation'] ?? 0 )
	&& 'delivering' === (string) ( $submitted_poll_status['last_index_action_status'] ?? '' )
	&& 'https://cloud.example.test/v1/runs/Run.Manual_Mixed-Case.1' === (string) ( $submitted_poll_request['url'] ?? '' )
	&& 'Run.Manual_Mixed-Case.1' === (string) ( $running_poll_cursor['pending_run_id'] ?? '' )
	&& 2 === absint( $running_poll_cursor['poll_generation'] ?? 0 )
	&& '' !== (string) ( $running_poll_cursor['last_polled_at'] ?? '' )
	&& false !== $running_poll_scheduled
	&& 'delivering' === (string) ( $running_poll_status['last_index_action_status'] ?? '' )
	&& 'https://cloud.example.test/v1/runs/Run.Manual_Mixed-Case.1' === (string) ( $running_poll_request['url'] ?? '' )
	&& 'completed' === (string) ( $running_poll_final_status['last_index_action_status'] ?? '' )
	&& array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() )
	&& 4 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: submitted and running Cloud states preserve a mixed-case run id until the bounded poll completes.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 1110151 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
$invalid_pending_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
$invalid_pending_cursor['pending_run_id'] = 'Run/ABC';
update_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, $invalid_pending_cursor, false );
$invalid_pending_status = maca_run_site_knowledge_flush();
$invalid_pending_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
maca_assert(
	array() === $GLOBALS['maca_http_requests']
	&& 'retrying' === (string) ( $invalid_pending_cursor['status'] ?? '' )
	&& 1 === absint( $invalid_pending_cursor['attempts'] ?? 0 )
	&& ! isset( $invalid_pending_cursor['pending_run_id'] )
	&& 'full_index_delivery_retry_scheduled' === (string) ( $invalid_pending_status['last_error_code'] ?? '' ),
	'Behavior: an invalid pending run id fails closed instead of being rewritten and queried.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 1110152 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array( 'run_id' => 'Run/ABC' ),
		)
	),
);
$invalid_response_status = maca_run_site_knowledge_flush();
$invalid_response_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
maca_assert(
	1 === count( $GLOBALS['maca_http_requests'] )
	&& 'retrying' === (string) ( $invalid_response_cursor['status'] ?? '' )
	&& 1 === absint( $invalid_response_cursor['attempts'] ?? 0 )
	&& ! isset( $invalid_response_cursor['pending_run_id'] )
	&& 'full_index_delivery_retry_scheduled' === (string) ( $invalid_response_status['last_error_code'] ?? '' ),
	'Behavior: an explicit invalid Cloud run id cannot complete or advance a full-index batch.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 111016 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
$bounded_run_acceptance = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array( 'run_id' => 'run_manual_never_finishes' ),
		)
	),
);
$bounded_running_response = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode(
		array(
			'status' => 'ok',
			'data' => array(
				'run' => array( 'status' => 'running' ),
			),
		)
	),
);
$GLOBALS['maca_http_response_queue'][] = $bounded_run_acceptance;
maca_run_site_knowledge_flush();
$max_run_polls = absint( Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot()['max_run_polls'] ?? 0 );
for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
	$never_finishes_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
	$never_finishes_cursor['poll_generation'] = max( 0, $max_run_polls - 1 );
	update_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, $never_finishes_cursor, false );
	$GLOBALS['maca_http_response_queue'][] = $bounded_running_response;
	$never_finishes_status = maca_run_site_knowledge_flush();
	if ( $attempt < 3 ) {
		$GLOBALS['maca_http_response_queue'][] = $bounded_run_acceptance;
		maca_run_site_knowledge_flush();
	}
}
$never_finishes_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
maca_assert(
	$max_run_polls > 0
	&& 'blocked' === (string) ( $never_finishes_cursor['status'] ?? '' )
	&& 3 === absint( $never_finishes_cursor['attempts'] ?? 0 )
	&& 'full_index_delivery_blocked' === (string) ( $never_finishes_status['last_error_code'] ?? '' )
	&& false === wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK )
	&& 6 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: a Cloud run that never finishes consumes bounded polling attempts and stops without another scheduled request.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 11102 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
for ( $attempt = 1; $attempt <= 3; $attempt++ ) {
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode(
			array(
				'status' => 'ok',
				'data' => array( 'run_id' => 'run_manual_poll_failure' ),
			)
		),
	);
	$GLOBALS['maca_http_response_queue'][] = new WP_Error( 'http_request_failed', 'Cloud run status is temporarily unavailable.' );
	maca_run_site_knowledge_flush();
	$poll_failure_status = maca_run_site_knowledge_flush();
}
$poll_failure_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
$poll_failure_post_keys = array_map(
	static fn( array $request ): string => (string) ( $request['args']['headers']['Idempotency-Key'] ?? '' ),
	array_values(
		array_filter(
			$GLOBALS['maca_http_requests'],
			static fn( array $request ): bool => 'POST' === (string) ( $request['args']['method'] ?? '' )
		)
	)
);
maca_assert(
	'blocked' === (string) ( $poll_failure_cursor['status'] ?? '' )
	&& 3 === absint( $poll_failure_cursor['attempts'] ?? 0 )
	&& 'full_index_delivery_blocked' === (string) ( $poll_failure_status['last_error_code'] ?? '' )
	&& 1 === count( array_unique( $poll_failure_post_keys ) )
	&& false === wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK ),
	'Behavior: alternating accepted runs and failed status polls stop after the bounded full-index retry limit.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 11201 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
maca_add_public_post_fixture( 11202 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::handle_saved_post( 11202, $GLOBALS['maca_posts'][11202] );
maca_queue_site_knowledge_inline_success();
maca_run_site_knowledge_flush();
$buffer_after_full_index = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::BUFFER_OPTION, array() );
maca_assert(
	array( 11202 ) === (array) ( $buffer_after_full_index['post_ids'] ?? array() )
	&& false !== wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK ),
	'Behavior: completing a full-index cursor wakes ordinary public changes buffered while Cloud delivery was in flight.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 11301 );
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
maca_set_site_knowledge_delivery_enabled( false );
Npcink_Cloud_Site_Knowledge_Change_Bridge::sync_schedule();
$paused_flush = wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK );
maca_set_site_knowledge_delivery_enabled( true );
Npcink_Cloud_Site_Knowledge_Change_Bridge::sync_schedule();
Npcink_Cloud_Site_Knowledge_Change_Bridge::resume_pending_delivery();
maca_assert(
	false === $paused_flush
	&& false !== wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK )
	&& array() === $GLOBALS['maca_http_requests'],
	'Behavior: disabling delivery pauses a full-index cursor and enabling delivery schedules it to resume without HTTP in settings save.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
for ( $post_id = 12000; $post_id < 12405; $post_id++ ) {
	maca_add_public_post_fixture( $post_id );
}
for ( $run_number = 1; $run_number <= 3; $run_number++ ) {
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode(
			array(
				'status' => 'ok',
				'data' => array( 'run_id' => 'run_automatic_' . $run_number ),
			)
		),
	);
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'body' => wp_json_encode(
			array(
				'status' => 'ok',
				'data' => array( 'status' => 'succeeded' ),
			)
		),
	);
}
Npcink_Cloud_Site_Knowledge_Change_Bridge::maybe_schedule_automatic_rebuild(
	array(
		'maintenance' => array(
			'contract_version' => 'site_knowledge_maintenance.v1',
			'status' => 'awaiting_site',
			'action' => 'full_sync',
			'automatic' => true,
			'request_id' => 'skm_automatic_123',
			'target_embedding_space_id' => 'siliconflow:BAAI/bge-m3',
		),
	)
);
$automatic_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer();
$automatic_bodies = array_map(
	static function ( array $request ): array {
		$body = json_decode( (string) ( $request['args']['body'] ?? '' ), true );
		return is_array( $body ) ? $body : array();
	},
	array_values(
		array_filter(
			$GLOBALS['maca_http_requests'],
			static fn( array $request ): bool => 'POST' === (string) ( $request['args']['method'] ?? '' )
		)
	)
);
$automatic_status_requests = array_values(
	array_filter(
		$GLOBALS['maca_http_requests'],
		static fn( array $request ): bool => 'GET' === (string) ( $request['args']['method'] ?? '' )
	)
);
$automatic_idempotency_keys = array_map(
	static fn( array $request ): string => (string) ( $request['args']['headers']['Idempotency-Key'] ?? '' ),
	array_values(
		array_filter(
			$GLOBALS['maca_http_requests'],
			static fn( array $request ): bool => 'POST' === (string) ( $request['args']['method'] ?? '' )
		)
	)
);
maca_assert(
	3 === absint( $automatic_cursor['batch_count'] ?? 0 )
	&& 3 === count( $automatic_bodies )
	&& 3 === count( $automatic_status_requests )
	&& array(
		'site_knowledge_automatic_skm_automatic_123_0',
		'site_knowledge_automatic_skm_automatic_123_1',
		'site_knowledge_automatic_skm_automatic_123_2',
	) === $automatic_idempotency_keys
	&& 'automatic_rebuild' === (string) ( $automatic_bodies[0]['input']['operation_source'] ?? '' )
	&& 'rebuild' === (string) ( $automatic_bodies[0]['input']['sync_mode'] ?? '' )
	&& 'full_sync' === (string) ( $automatic_bodies[0]['input']['maintenance']['action'] ?? '' )
	&& 'skm_automatic_123' === (string) ( $automatic_bodies[0]['input']['maintenance']['request_id'] ?? '' )
	&& 0 === absint( $automatic_bodies[0]['input']['maintenance']['batch_index'] ?? 99 )
	&& false === (bool) ( $automatic_bodies[0]['input']['maintenance']['is_final'] ?? true )
	&& 'refresh' === (string) ( $automatic_bodies[2]['input']['sync_mode'] ?? '' )
	&& 2 === absint( $automatic_bodies[2]['input']['maintenance']['batch_index'] ?? 0 )
	&& true === (bool) ( $automatic_bodies[2]['input']['maintenance']['is_final'] ?? false )
	&& array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() ),
	'Behavior: Cloud maintenance intent becomes an automatic bounded full-sync cursor and completes without a site-admin action.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
update_option(
	Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION,
	array(
		'status' => 'blocked',
		'request_id' => 'skm_blocked_123',
		'post_ids' => array( 12001 ),
		'next_batch' => 0,
		'batch_count' => 1,
		'attempts' => 3,
	),
	false
);
Npcink_Cloud_Site_Knowledge_Change_Bridge::maybe_schedule_automatic_rebuild(
	array(
		'maintenance' => array(
			'contract_version' => 'site_knowledge_maintenance.v1',
			'status' => 'blocked',
			'action' => 'full_sync',
			'automatic' => true,
			'request_id' => 'skm_blocked_123',
			'target_embedding_space_id' => 'siliconflow:BAAI/bge-m3',
		),
	)
);
$blocked_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
maca_assert(
	'blocked' === (string) ( $blocked_cursor['status'] ?? '' )
	&& 3 === absint( $blocked_cursor['attempts'] ?? 0 )
	&& false === wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK ),
	'Behavior: refreshing the same blocked maintenance request preserves the bounded retry stop.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
maca_add_public_post_fixture( 10901 );
$GLOBALS['maca_posts'][10901]->post_title = 'Contact editor@example.test or 138 0013 8000';
$GLOBALS['maca_posts'][10901]->post_content = 'Public guide https://example.test/private-path email editor@example.test phone 138 0013 8000 useful ending.';
Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'start' );
maca_queue_site_knowledge_inline_success();
maca_run_site_knowledge_flush();
$redacted_request = $GLOBALS['maca_http_requests'][0] ?? array();
$redacted_body = json_decode( (string) ( $redacted_request['args']['body'] ?? '' ), true );
$redacted_document = (array) ( $redacted_body['input']['documents'][0] ?? array() );
maca_assert(
	false === strpos( (string) ( $redacted_document['title'] ?? '' ), '@' )
	&& false === strpos( (string) ( $redacted_document['content_excerpt'] ?? '' ), 'https://' )
	&& false === strpos( (string) ( $redacted_document['content_excerpt'] ?? '' ), '@' )
	&& false === strpos( (string) ( $redacted_document['content_excerpt'] ?? '' ), '138 0013 8000' )
	&& 'https://example.test/?p=10901' === (string) ( $redacted_document['url'] ?? '' ),
	'Behavior: public Site Knowledge manifests remove incidental contact data while preserving the canonical public post URL.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
$delete_status = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'delete' );
$delete_request = $GLOBALS['maca_http_requests'][0] ?? array();
$delete_body = json_decode( (string) ( $delete_request['args']['body'] ?? '' ), true );
$delete_body = is_array( $delete_body ) ? $delete_body : array();
maca_assert(
	is_array( $delete_status )
	&& 'delete' === (string) ( $delete_body['input']['sync_mode'] ?? '' )
	&& 'admin_delete' === (string) ( $delete_body['input']['operation_source'] ?? '' )
	&& array() === (array) ( $delete_body['input']['documents'] ?? array() )
	&& false === (bool) ( $delete_body['input']['direct_wordpress_write'] ?? true )
	&& 'delete' === (string) ( $delete_status['last_index_action'] ?? '' ),
	'Behavior: administrator delete request asks Cloud to remove the site index without WordPress writes.'
);

maca_reset_site_knowledge_bridge_state();
maca_seed_settings( true );
$GLOBALS['wpdb'] = new class() {
	public string $options = 'wp_options';

	public function delete(): int {
		return 0;
	}

	public function update(): int {
		return 0;
	}
};
maca_queue_site_knowledge_inline_success();
$delete_with_failed_release = Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( 'delete' );
$orphaned_delete_cursor = get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() );
$delete_cleanup_scheduled = wp_next_scheduled( Npcink_Cloud_Site_Knowledge_Change_Bridge::FLUSH_HOOK );
unset( $GLOBALS['wpdb'] );
$delete_cleanup_status = maca_run_site_knowledge_flush();
maca_assert(
	is_array( $delete_with_failed_release )
	&& 'delete' === (string) ( $orphaned_delete_cursor['operation'] ?? '' )
	&& 'admin_delete' === (string) ( $orphaned_delete_cursor['operation_source'] ?? '' )
	&& false !== $delete_cleanup_scheduled
	&& 1 === count( $GLOBALS['maca_http_requests'] )
	&& array() === get_option( Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION, array() )
	&& true === (bool) ( $delete_cleanup_status['last_delivery_ok'] ?? false ),
	'Behavior: a failed manual-delete cursor release retries local cleanup without starting a rebuild or another Cloud request.'
);
