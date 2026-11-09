<?php
/** Local source search/list contracts; no live WordPress or Cloud mutation. */
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

// Source-management queries must search the site, not filter a recent-100 cache.
require_once MACA_TEST_ROOT . '/includes/class-cloud-site-knowledge-admin-projection.php';
$selection_options_before = $GLOBALS['maca_options'];
$selection_posts_before = $GLOBALS['maca_posts'];
$GLOBALS['maca_posts'] = array();
for ( $id = 1; $id <= 130; $id++ ) { maca_add_public_post_fixture( $id ); }
$GLOBALS['maca_posts'][130]->post_title = '历史说明 <script>alert(1)</script>';
$GLOBALS['maca_posts'][129]->post_title = '历史说明（草稿）'; $GLOBALS['maca_posts'][129]->post_status = 'draft';
$GLOBALS['maca_posts'][128]->post_title = '历史说明（加密）'; $GLOBALS['maca_posts'][128]->post_password = 'password';
$selection_http_before = count( $GLOBALS['maca_http_requests'] );
$selection_queries_before = count( $GLOBALS['maca_post_queries'] ?? array() );
$initial_selection = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( '', 1, false );
maca_assert( empty( $initial_selection['items'] ) && count( $GLOBALS['maca_post_queries'] ) === $selection_queries_before, 'Source search: initial empty search performs no WordPress query.' );
$older_selection = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( '历史说明', 1, false );
maca_assert( array( 'post:130' ) === array_keys( $older_selection['items'] ), 'Source search: an older match beyond the first 100 is found; drafts and protected content stay out.' );
$first_selection = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( 'Public fixture', 1, false );
$second_selection = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( 'Public fixture', 2, false );
maca_assert( 20 === count( $first_selection['items'] ) && $first_selection['has_next'] && 20 === count( $second_selection['items'] ) && ! array_intersect_key( $first_selection['items'], $second_selection['items'] ), 'Source search: bounded result pages do not overlap.' );
$GLOBALS['maca_posts'][127]->post_content = 'OnlyBodyNeedle';
$GLOBALS['maca_posts'][126]->post_title = '123';
$GLOBALS['maca_posts'][125]->fixture_categories = array( 7 ); $GLOBALS['maca_posts'][125]->fixture_tags = array( 9 );
$GLOBALS['maca_posts'][124]->fixture_categories = array( 7 ); $GLOBALS['maca_posts'][124]->fixture_tags = array( 8 );
$GLOBALS['maca_posts'][123]->fixture_categories = array( 6 ); $GLOBALS['maca_posts'][123]->fixture_tags = array( 9 );
$title_only = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( 'OnlyBodyNeedle', 1, false );
$exact_id = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( '#125', 1, false );
$numeric_name = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( '123', 1, false );
$taxonomy_only = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( '', 1, false, 7, 9 );
$combined = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( '#124', 1, false, 7, 9 );
maca_assert( empty( $title_only['items'] ) && array( 'post:125' ) === array_keys( $exact_id['items'] ) && isset( $numeric_name['items']['post:126'] ) && isset( $numeric_name['items']['post:123'] ), 'Source search: names match titles only, #ID is exact, and numeric titles remain searchable.' );
maca_assert( array( 'post:125' ) === array_keys( $taxonomy_only['items'] ) && empty( $combined['items'] ), 'Source filters: category and tag intersect, can search without a name, and also constrain exact-ID searches.' );
foreach ( array( '#0', '#0125', '#-1', '#125x', '#128', '#129', '#999999999999999999999999999' ) as $invalid_id ) {
	maca_assert( empty( Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( $invalid_id, 1, false )['items'] ), 'Source search: invalid or overflowing #ID never falls back to a broad title search.' );
}
$selection_sources = array();
for ( $id = 1; $id <= 25; $id++ ) { $selection_sources['post:' . $id] = array( 'source_type' => 'post', 'source_id' => $id, 'state' => 2 === $id ? 'pending_delete' : ( 3 === $id ? 'restore_pending' : 'excluded' ) ); }
$selection_sources['post:999'] = array( 'source_type' => 'post', 'source_id' => 999, 'state' => 'excluded' );
$GLOBALS['maca_options'][Npcink_Cloud_Site_Knowledge_Change_Bridge::MAINTENANCE_OPTION] = array( '_lifecycle' => array( 'scopes' => array( Npcink_Cloud_Site_Knowledge_Change_Bridge::lifecycle_scope() => array( 'sources' => $selection_sources ) ) ) );
$source_summary_renderer = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_site_knowledge_summary' );
$source_summary_renderer->setAccessible( true );
ob_start(); $source_summary_renderer->invoke( null, Npcink_Cloud_Site_Knowledge_Change_Bridge::health_snapshot(), Npcink_Cloud_Addon_Settings::get_settings(), true ); $counted_sources_html = (string) ob_get_clean();
maca_assert( false !== strpos( $counted_sources_html, '24 excluded' ) && false !== strpos( $counted_sources_html, '2 pending' ) && false === strpos( $counted_sources_html, '26 excluded' ), 'Exclusion entry: confirmed exclusions and in-flight exclude/restore intents have separate counts.' );
$excluded_first = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( '', 1, true );
$excluded_second = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( '', 2, true );
maca_assert( 20 === count( $excluded_first['items'] ) && $excluded_first['has_next'] && 6 === count( $excluded_second['items'] ) && ! $excluded_second['has_next'] && ! $excluded_first['items']['post:2']['selectable'] && ! $excluded_first['items']['post:3']['selectable'] && ! $excluded_second['items']['post:999']['selectable'] && $excluded_second['items']['post:999']['unavailable'], 'Excluded list: paginates all local exclusions, keeps pending and missing content visible, and disables invalid restores.' );
$suppressed_id = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( '#1', 1, false );
maca_assert( empty( $suppressed_id['items'] ), 'An exact ID cannot re-offer content already excluded or being processed.' );
$filtered_selection = Npcink_Cloud_Site_Knowledge_Admin_Projection::source_selection_page( 'Public fixture', 1, false );
maca_assert( ! array_intersect_key( $filtered_selection['items'], $selection_sources ) && $selection_http_before === count( $GLOBALS['maca_http_requests'] ), 'Source search: suppressed items are omitted and all list reads make zero Cloud HTTP requests.' );
$selection_query_before = $_GET;
$source_manager_renderer = new ReflectionMethod( Npcink_Cloud_Settings_Page::class, 'render_source_delivery_utility' );
$source_manager_renderer->setAccessible( true );
$_GET = array( 'view' => 'knowledge_search', 'source_search' => '历史说明' );
ob_start(); $source_manager_renderer->invoke( null ); $source_search_html = (string) ob_get_clean();
maca_assert( false === strpos( $source_search_html, '<script>alert(1)</script>' ) && false !== strpos( $source_search_html, '&lt;script&gt;alert(1)&lt;/script&gt;' ) && false !== strpos( $source_search_html, 'name="site_knowledge_scope"' ) && false !== strpos( $source_search_html, 'name="_wpnonce"' ) && false !== strpos( $source_search_html, 'name="site_knowledge_index_action" value="exclude"' ) && false !== strpos( $source_search_html, 'data-npcink-source-confirmed' ) && false === strpos( $source_search_html, 'name="site_knowledge_confirmation"' ), 'Source search: untrusted titles are escaped and exclusion retains the scoped nonce and explicit confirmation.' );
$_GET = array( 'view' => 'knowledge_search', 'source_search' => '#125', 'source_category' => '7', 'source_tag' => '9' );
ob_start(); $source_manager_renderer->invoke( null ); $source_filters_html = (string) ob_get_clean();
maca_assert( false !== strpos( $source_filters_html, 'class="npcink-cloud-source-filters" open' ) && false !== strpos( $source_filters_html, 'name="source_category" value="7"' ) && false !== strpos( $source_filters_html, 'name="source_tag" value="9"' ) && false !== strpos( $source_filters_html, 'Category and tag filters apply to posts only.' ) && false !== strpos( $source_filters_html, 'source_search=%23125' ), 'Active filters are disclosed and preserved in the action form.' );
$_GET = array( 'view' => 'knowledge_excluded' );
ob_start(); $source_manager_renderer->invoke( null ); $source_excluded_html = (string) ob_get_clean();
maca_assert( false !== strpos( $source_excluded_html, 'Excluded (24)' ) && false !== strpos( $source_excluded_html, ' · 2 pending' ), 'Excluded tab separates confirmed exclusions from pending removal and restore.' );
$source_excluded_dom = new DOMDocument(); @$source_excluded_dom->loadHTML( $source_excluded_html ); $source_excluded_xpath = new DOMXPath( $source_excluded_dom );
maca_assert( 2 === $source_excluded_xpath->query( '//input[@name="site_knowledge_sources[]" and @disabled]' )->length && false !== strpos( $source_excluded_html, 'Checking removal' ) && false !== strpos( $source_excluded_html, 'Checking restore' ) && false !== strpos( $source_excluded_html, 'name="site_knowledge_index_action" value="restore"' ) && false === strpos( $source_excluded_html, 'name="site_knowledge_confirmation"' ) && false === strpos( $source_excluded_html, 'data-npcink-source-confirmed' ), 'Excluded view: pending actions keep their actual status, cannot be selected again, and confirmed exclusions offer restore.' );
maca_set_site_knowledge_delivery_enabled( false );
ob_start(); $source_manager_renderer->invoke( null ); $source_disabled_html = (string) ob_get_clean();
$source_disabled_dom = new DOMDocument(); @$source_disabled_dom->loadHTML( $source_disabled_html ); $source_disabled_xpath = new DOMXPath( $source_disabled_dom );
maca_assert( 20 === $source_disabled_xpath->query( '//input[@name="site_knowledge_sources[]" and @disabled]' )->length && false !== strpos( $source_disabled_html, 'Enable Site Knowledge on Overview before restoring content.' ) && false === strpos( $source_disabled_html, 'name="site_knowledge_confirmation"' ) && $selection_http_before === count( $GLOBALS['maca_http_requests'] ), 'Excluded view: disabled delivery explains unavailable restore, hides the confirmation form, and makes zero Cloud requests.' );
$_GET = array( 'tab' => 'permissions', 'view' => 'knowledge_excluded' );
ob_start(); Npcink_Cloud_Settings_Page::render(); $source_full_page_html = (string) ob_get_clean();
maca_assert( false === strpos( $source_full_page_html, 'data-npcink-connection-summary' ) && false !== strpos( $source_full_page_html, 'npcink-cloud-source-manager' ), 'Nested source page: Overview-only connection refresh has no mount here and cannot insert an unrelated status banner.' );
$_GET = $selection_query_before;
$GLOBALS['maca_options'] = $selection_options_before;
$GLOBALS['maca_posts'] = $selection_posts_before;
