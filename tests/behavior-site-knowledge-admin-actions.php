<?php
/**
 * Behavior tests for Site Knowledge administrator action results.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if ( ! class_exists( 'Npcink_Cloud_Site_Knowledge_Change_Bridge' ) ) {
	/** Controlled bridge double; real transport behavior has separate coverage. */
	final class Npcink_Cloud_Site_Knowledge_Change_Bridge {
		public static int $buffer_calls = 0;
		public static int $flush_calls = 0;
		public static array $index_calls = array();
		public static array $article_calls = array();
		public static array $flush_result = array();
		public static $index_result = array();
		public static $article_result = array();

		public static function buffer_recent_public_content(): void {
			self::$buffer_calls++;
		}

		public static function flush_buffer(): array {
			self::$flush_calls++;
			return self::$flush_result;
		}

		public static function request_manual_index_operation( string $operation ) {
			self::$index_calls[] = $operation;
			if ( 'delete' === $operation ) {
				$GLOBALS['maca_http_requests'][] = array( 'operation' => 'delete' );
			}
			return self::$index_result;
		}

		public static function request_public_post_refresh( int $post_id ) {
			self::$article_calls[] = $post_id;
			return self::$article_result;
		}
	}
}

maca_load_addon_classes();

/** Resets the controlled action boundary. */
function maca_reset_site_knowledge_admin_actions( bool $verified = true ): void {
	maca_reset_test_state();
	maca_seed_settings( $verified );
	Npcink_Cloud_Site_Knowledge_Change_Bridge::$buffer_calls = 0;
	Npcink_Cloud_Site_Knowledge_Change_Bridge::$flush_calls = 0;
	Npcink_Cloud_Site_Knowledge_Change_Bridge::$index_calls = array();
	Npcink_Cloud_Site_Knowledge_Change_Bridge::$article_calls = array();
	Npcink_Cloud_Site_Knowledge_Change_Bridge::$flush_result = array();
	Npcink_Cloud_Site_Knowledge_Change_Bridge::$index_result = array();
	Npcink_Cloud_Site_Knowledge_Change_Bridge::$article_result = array();
}

/** Returns whether the result has the fixed public shape. */
function maca_has_site_knowledge_admin_result_shape( array $result ): bool {
	return array( 'ok', 'code', 'message', 'operation', 'sent_count', 'selected_count', 'batch_count', 'source_error_code' ) === array_keys( $result );
}

maca_reset_site_knowledge_admin_actions( false );
$unverified = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_public_refresh();
$unverified_index = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_index_operation( 'invalid', '' );
maca_assert(
	maca_has_site_knowledge_admin_result_shape( $unverified )
	&& 'not_verified' === $unverified['code']
	&& 'Cloud Addon settings are not verified.' === $unverified['message']
	&& 'not_verified' === $unverified_index['code']
	&& 0 === Npcink_Cloud_Site_Knowledge_Change_Bridge::$buffer_calls
	&& array() === Npcink_Cloud_Site_Knowledge_Change_Bridge::$index_calls,
	'Behavior: unverified Site Knowledge administrator actions fail before bridge work with the fixed result shape.'
);

maca_reset_site_knowledge_admin_actions();
Npcink_Cloud_Site_Knowledge_Change_Bridge::$flush_result = array( 'last_delivery_ok' => true, 'last_sent_count' => 7 );
$refresh = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_public_refresh();
maca_assert(
	! empty( $refresh['ok'] )
	&& 'refresh_requested' === $refresh['code']
	&& 7 === $refresh['sent_count']
	&& 'Site Knowledge refresh requested. Public content items sent: 7.' === $refresh['message']
	&& 1 === Npcink_Cloud_Site_Knowledge_Change_Bridge::$buffer_calls
	&& 1 === Npcink_Cloud_Site_Knowledge_Change_Bridge::$flush_calls,
	'Behavior: public refresh preserves the bridge count and administrator success copy.'
);

maca_reset_site_knowledge_admin_actions();
Npcink_Cloud_Site_Knowledge_Change_Bridge::$flush_result = array(
	'last_delivery_ok' => false,
	'last_delivery_error' => ' Delivery failed. ',
	'last_error_code' => 'delivery_failed_retry_scheduled',
);
$refresh_failed = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_public_refresh();
maca_assert(
	empty( $refresh_failed['ok'] )
	&& 'refresh_failed' === $refresh_failed['code']
	&& 'Delivery failed.' === $refresh_failed['message']
	&& 'delivery_failed_retry_scheduled' === $refresh_failed['source_error_code'],
	'Behavior: public refresh preserves sanitized bridge failure detail and source error code.'
);

maca_reset_site_knowledge_admin_actions();
Npcink_Cloud_Site_Knowledge_Change_Bridge::$flush_result = array( 'last_delivery_ok' => false );
$refresh_fallback = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_public_refresh();
maca_assert(
	'Site Knowledge refresh request failed.' === $refresh_fallback['message'],
	'Behavior: public refresh preserves the administrator fallback failure copy.'
);

maca_reset_site_knowledge_admin_actions();
$invalid_article = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_article_refresh( 0 );
maca_assert(
	'invalid_article' === $invalid_article['code']
	&& array() === Npcink_Cloud_Site_Knowledge_Change_Bridge::$article_calls,
	'Behavior: invalid single-article refresh stops before transport.'
);

maca_reset_site_knowledge_admin_actions();
$article_refresh = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_article_refresh( 88 );
maca_assert(
	! empty( $article_refresh['ok'] )
	&& 'article_refresh_requested' === $article_refresh['code']
	&& 1 === $article_refresh['sent_count']
	&& array( 88 ) === Npcink_Cloud_Site_Knowledge_Change_Bridge::$article_calls,
	'Behavior: one published article refresh maps to one bounded bridge call.'
);

maca_reset_site_knowledge_admin_actions();
Npcink_Cloud_Site_Knowledge_Change_Bridge::$article_result = new WP_Error( 'not_public', 'Only public content is allowed.' );
$article_failure = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_article_refresh( 89 );
maca_assert(
	'article_refresh_failed' === $article_failure['code']
	&& 'not_public' === $article_failure['source_error_code']
	&& 'Only public content is allowed.' === $article_failure['message'],
	'Behavior: single-article refresh preserves a bounded bridge failure.'
);

maca_reset_site_knowledge_admin_actions();
$unsupported = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_index_operation( 'unknown', '' );
$confirmation = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_index_operation( 'rebuild', 'wrong' );
maca_assert(
	'unsupported_operation' === $unsupported['code']
	&& 'The requested Site Knowledge index action is not supported.' === $unsupported['message']
	&& 'confirmation_required' === $confirmation['code']
	&& 'Type the confirmation word before running this Site Knowledge index action.' === $confirmation['message']
	&& array() === Npcink_Cloud_Site_Knowledge_Change_Bridge::$index_calls,
	'Behavior: unsupported and unconfirmed index actions stop before bridge work.'
);

foreach (
	array(
		'start' => array( 'indexing_scheduled', 'Site Knowledge indexing delivery scheduled: 401 public content items in 3 batches.' ),
		'rebuild' => array( 'rebuild_scheduled', 'Site Knowledge rebuild delivery scheduled: 401 public content items in 3 batches.' ),
	) as $operation => $expectation
) {
	maca_reset_site_knowledge_admin_actions();
	Npcink_Cloud_Site_Knowledge_Change_Bridge::$index_result = array( 'last_index_action_selected_count' => 401, 'last_index_action_batch_count' => 3 );
	$result = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_index_operation( $operation, $operation );
	maca_assert(
		! empty( $result['ok'] )
		&& $expectation[0] === $result['code']
		&& $expectation[1] === $result['message']
		&& 401 === $result['selected_count']
		&& 3 === $result['batch_count']
		&& array() === $GLOBALS['maca_http_requests'],
		'Behavior: administrator ' . $operation . ' keeps zero synchronous HTTP and returns queued counts.'
	);
}

maca_reset_site_knowledge_admin_actions();
Npcink_Cloud_Site_Knowledge_Change_Bridge::$index_result = array( 'last_index_action_selected_count' => 99, 'last_index_action_batch_count' => 1 );
$delete = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_index_operation( 'delete', 'DELETE' );
maca_assert(
	'delete_requested' === $delete['code']
	&& 'Site Knowledge index deletion requested. WordPress content was not changed.' === $delete['message']
	&& 0 === $delete['selected_count']
	&& 0 === $delete['batch_count']
	&& 1 === count( $GLOBALS['maca_http_requests'] )
	&& 'delete' === $GLOBALS['maca_http_requests'][0]['operation'],
	'Behavior: administrator delete preserves the existing bridge-owned transport and reports no WordPress counts.'
);

maca_reset_site_knowledge_admin_actions();
Npcink_Cloud_Site_Knowledge_Change_Bridge::$index_result = new WP_Error( 'cloud_site_knowledge_delivery_in_progress', 'A delivery is already in progress.' );
$bridge_error = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_index_operation( 'start', '' );
maca_assert(
	'bridge_error' === $bridge_error['code']
	&& 'cloud_site_knowledge_delivery_in_progress' === $bridge_error['source_error_code']
	&& 'A delivery is already in progress.' === $bridge_error['message'],
	'Behavior: index action WP_Error preserves the bridge message and source error code.'
);

maca_reset_site_knowledge_admin_actions();
$GLOBALS['maca_media_batch_inputs'] = array();
add_filter(
	'npcink_toolbox_refresh_site_media_index_batch',
	static function ( $value, array $input ) {
		unset( $value );
		$GLOBALS['maca_media_batch_inputs'][] = $input;
		return array(
			'indexed_items' => 10,
			'visual_evidence_items' => 0,
			'visual_evidence_reused_items' => 5,
			'visual_evidence_submitted_items' => 4,
			'screened_items' => 1,
			'total' => 42,
			'has_more' => true,
			'visual_evidence_run_id' => 'run_media_page_3',
		);
	},
	10,
	10
);
$media_batch = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_media_index_refresh( 3 );
maca_assert(
	! empty( $media_batch['ok'] )
	&& 3 === $media_batch['page']
	&& 4 === $media_batch['next_page']
	&& true === $media_batch['has_more']
	&& 'run_media_page_3' === $media_batch['run_id']
	&& 4 === $media_batch['sent_count']
	&& 10 === $media_batch['page_count']
	&& 5 === $media_batch['reused_count']
	&& 1 === $media_batch['screened_count']
	&& array( array( 'page' => 3, 'per_page' => 10 ) ) === $GLOBALS['maca_media_batch_inputs'],
	'Behavior: media recognition distinguishes the logical page, reusable evidence, screened items, and newly submitted Cloud work.'
);

$media_batch_with_plan_size = Npcink_Cloud_Site_Knowledge_Admin_Actions::request_media_index_refresh( 4, 7, 'media_plan_retry-123' );
maca_assert(
	7 === $media_batch_with_plan_size['per_page']
	&& array( 'page' => 4, 'per_page' => 7, 'upload_scope' => 'media_plan_retry-123' ) === $GLOBALS['maca_media_batch_inputs'][1],
	'Behavior: an active media plan preserves its bounded page size and passes only its stable upload-attempt scope to Toolbox.'
);
