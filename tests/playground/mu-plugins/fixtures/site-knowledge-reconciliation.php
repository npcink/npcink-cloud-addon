<?php
/**
 * Shared WordPress reconciliation fixture. Run only on disposable sites.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

/** @return true|WP_Error */
function npcink_cloud_addon_reconciliation_fixture() {
	global $wpdb;
	$fixture_ids = array();
	$modified = '2001-01-01 00:00:00';
	$newer = '2001-01-01 00:00:01';
	$status_option = Npcink_Cloud_Site_Knowledge_Change_Bridge::STATUS_OPTION;
	$old_status = get_option( $status_option, null );
	$insert = static function ( string $time, string $status, string $type ) use ( $wpdb, &$fixture_ids ): int {
		$inserted = $wpdb->insert(
			$wpdb->posts,
			array(
				'post_author'           => 1,
				'post_date'             => $time,
				'post_date_gmt'         => $time,
				'post_content'          => 'Site Knowledge reconciliation fixture',
				'post_title'            => 'Site Knowledge fixture',
				'post_excerpt'          => '',
				'post_status'           => $status,
				'to_ping'               => '',
				'pinged'                => '',
				'post_modified'         => $time,
				'post_modified_gmt'     => $time,
				'post_content_filtered' => '',
				'post_type'             => $type,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
		if ( 1 !== $inserted ) {
			throw new RuntimeException( 'Could not insert a Site Knowledge fixture row.' );
		}
		$post_id = (int) $wpdb->insert_id;
		$fixture_ids[] = $post_id;
		return $post_id;
	};
	try {
		// The newer row has the lowest ID; the second page must still include it.
		$newer_id = $insert( $newer, 'publish', 'page' );
		$insert( $modified, 'draft', 'post' );
		$insert( $modified, 'publish', 'fixture_unknown' );
		$same_second_ids = array();
		for ( $index = 0; $index < 50; $index++ ) {
			$same_second_ids[] = $insert( $modified, 'publish', 'post' );
		}
		$attachment_id = $insert( $modified, 'inherit', 'attachment' );

		$bridge = new ReflectionClass( Npcink_Cloud_Site_Knowledge_Change_Bridge::class );
		$read_cursor = $bridge->getMethod( 'get_reconciliation_cursor' );
		$query = $bridge->getMethod( 'query_reconciliation_posts' );
		if ( PHP_VERSION_ID < 80100 ) {
			$read_cursor->setAccessible( true );
			$query->setAccessible( true );
		}
		update_option( $status_option, array( 'last_delivered_at' => '2002-01-01T00:00:00+00:00' ), false );
		$initial_cursor = $read_cursor->invoke( null );
		$first_page = $query->invoke( null, $initial_cursor );
		$last = end( $first_page );
		$cursor = array( 'modified_gmt' => (string) ( $last->post_modified_gmt ?? '' ), 'post_id' => (int) ( $last->ID ?? 0 ) );
		$second_page = $query->invoke( null, $cursor );
		$post_id = static function ( $post ): int { return (int) $post->ID; };
		$first_page_ids = array_map( $post_id, $first_page );
		$second_page_ids = array_map( $post_id, $second_page );

		if ( array( 'modified_gmt' => '', 'post_id' => 0 ) !== $initial_cursor
			|| $same_second_ids !== $first_page_ids
			|| $modified !== $cursor['modified_gmt']
			|| array( $attachment_id, $newer_id ) !== array_values( array_intersect( $second_page_ids, $fixture_ids ) )
		) {
			return new WP_Error( 'npcink_cloud_addon_reconciliation_failed', 'The WordPress reconciliation query did not preserve the ordered cursor.', array( 'status' => 500, 'first_page_ids' => $first_page_ids, 'second_page_ids' => $second_page_ids ) );
		}

		return true;
	} finally {
		if ( null === $old_status ) {
			delete_option( $status_option );
		} else {
			update_option( $status_option, $old_status, false );
		}
		foreach ( $fixture_ids as $fixture_id ) {
			$wpdb->delete( $wpdb->posts, array( 'ID' => $fixture_id ), array( '%d' ) );
		}
	}
}
