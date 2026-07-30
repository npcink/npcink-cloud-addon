<?php
/**
 * Behavior tests for Cloud entitlement summary caching.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
maca_load_addon_classes();
require_once MACA_TEST_ROOT . '/includes/class-cloud-settings-page.php';

/**
 * Returns a private method reflection without PHP 8.5 deprecation notices.
 *
 * @param class-string $class_name Class name.
 * @param string       $method_name Method name.
 * @return ReflectionMethod
 */
function maca_private_method( string $class_name, string $method_name ): ReflectionMethod {
	$method = new ReflectionMethod( $class_name, $method_name );
	if ( PHP_VERSION_ID < 80100 ) {
		$method->setAccessible( true );
	}

	return $method;
}

maca_reset_test_state();
maca_seed_settings( true );

$entitlement_response = array(
	'status' => 'ok',
	'data'   => array(
		'contract_version' => 'cloud-billing-entitlement-v1',
		'package'          => 'Pro',
		'package_tier'     => 'pro',
		'status'           => 'active',
		'period'           => array(
			'end_at' => '2026-07-01T00:00:00Z',
		),
		'entitlement'      => array(
			'usage_limits'         => array(
				'period'       => 'month',
				'max_runs'     => 100,
				'max_tokens'   => 2000,
				'max_cost_usd' => 25,
				'max_sites'    => 3,
			),
			'hosted_runtime_quota' => array(
				'max_active_runs'  => 2,
				'max_batch_items'  => 10,
				'execution_tiers'  => array( 'cloud' ),
			),
			'pro_cloud_runtime' => array(
				'feature_id' => 'nightly_site_inspection',
				'max_nightly_inspection_runs_per_period' => 10,
				'used_nightly_inspection_runs' => 2,
				'remaining_nightly_inspection_runs' => 8,
				'result_retention_days' => 14,
			),
		),
		'quota_summary'    => array(
			'ai_credit_usage_detail' => array(
				'summary'      => array(
					'used'      => 12.5,
					'limit'     => 100,
					'remaining' => 87.5,
					'unit'      => 'ai_credits',
					'status'    => 'ok',
				),
				'portal_paths' => array(
					'ai_credit_usage' => '/portal/usage',
				),
			),
		),
	),
);

$cached_summary = Npcink_Cloud_Entitlement_Summary::cache_summary_from_response( $entitlement_response );
$read_summary   = Npcink_Cloud_Entitlement_Summary::get_cached_summary();

maca_assert(
	! empty( $cached_summary['available'] )
	&& 'Pro' === (string) ( $cached_summary['package_label'] ?? '' )
	&& 'pro' === (string) ( $cached_summary['package_tier'] ?? '' )
	&& 'active' === (string) ( $cached_summary['entitlement_status'] ?? '' )
	&& 'cached' === (string) ( $read_summary['state'] ?? '' )
	&& 'Pro' === (string) ( $read_summary['package_label'] ?? '' )
	&& 'ai_credits' === (string) ( $read_summary['ai_credit_usage_detail']['summary']['unit'] ?? '' ),
	'Behavior: verification entitlement response can populate the short local summary cache.'
);

$entitlement_cache_key = (string) array_key_first( $GLOBALS['maca_transients'] );
$GLOBALS['maca_transients'][ $entitlement_cache_key ]['fresh_until'] = '2000-01-01 00:00:00 UTC';
$stale_summary = Npcink_Cloud_Entitlement_Summary::get_cached_summary();

maca_assert(
	'stale' === (string) ( $stale_summary['state'] ?? '' )
	&& ! empty( $stale_summary['available'] )
	&& ! empty( $stale_summary['stale'] )
	&& 'Pro' === (string) ( $stale_summary['package_label'] ?? '' ),
	'Behavior: an expired entitlement projection remains available for immediate stale-while-refresh display.'
);

$format_overview_entitlement = maca_private_method( Npcink_Cloud_Settings_Page::class, 'format_overview_entitlement' );
$overview_metrics_method = maca_private_method( Npcink_Cloud_Settings_Page::class, 'get_overview_entitlement_metrics' );
$site_knowledge_usage_method = maca_private_method( Npcink_Cloud_Settings_Page::class, 'get_site_knowledge_usage_projection' );
$normalize_cloud_entitlement = maca_private_method( Npcink_Cloud_Entitlement_Summary::class, 'normalize_cloud_entitlement' );
$overview_metrics = $overview_metrics_method->invoke( null, $read_summary );
$site_knowledge_input = array(
	'state' => 'fresh',
	'available' => true,
	'quota_status' => 'ok',
	'indexed_documents' => 599,
	'max_documents' => 10000,
	'remaining_documents' => 9401,
	'document_percent' => 6,
	'warning_ratio' => 0.85,
	'last_sync_at' => '2026-07-15 00:00:00 UTC',
);
$site_knowledge_direct = Npcink_Cloud_Site_Knowledge_Admin_Projection::build( $site_knowledge_input );
$site_knowledge_usage = $site_knowledge_usage_method->invoke( null, $site_knowledge_input );
$missing_overview_metrics = $overview_metrics_method->invoke(
	null,
	array(
		'ai_credit_usage_detail' => array( 'available' => false ),
		'pro_cloud_runtime' => array( 'reported' => false ),
	)
);
maca_assert(
	'Loading plan and entitlement…' === $format_overview_entitlement->invoke(
		null,
		array(
			'state' => 'not_refreshed',
			'available' => false,
		),
		true
	)
	&& 'Pro plan · available' === $format_overview_entitlement->invoke( null, $read_summary, true )
	&& 'Free plan · available' === $format_overview_entitlement->invoke( null, array( 'available' => true, 'package_label' => 'Free' ), true )
	&& 'Enterprise · available' === $format_overview_entitlement->invoke( null, array( 'available' => true, 'package_label' => 'Enterprise' ), true )
	&& 'Enterprise · available' === $format_overview_entitlement->invoke( null, array( 'available' => true, 'package_label' => 'Enterprise', 'package_tier' => 'pro' ), true )
	&& ! empty( $overview_metrics['credits']['available'] )
	&& 88 === (int) ( $overview_metrics['credits']['percent'] ?? -1 )
	&& '87.5 / 100' === (string) ( $overview_metrics['credits']['value_label'] ?? '' )
	&& '88% remaining' === (string) ( $overview_metrics['credits']['status_label'] ?? '' )
	&& '87.5 / 100 · 88% remaining' === (string) ( $overview_metrics['credits']['label'] ?? '' )
	&& 'Used 12.5 AI credits; remaining 87.5 AI credits; limit 100 AI credits.' === (string) ( $overview_metrics['credits']['tooltip'] ?? '' )
	&& ! empty( $site_knowledge_usage['available'] )
	&& '9,401 / 10,000' === (string) ( $site_knowledge_usage['value_label'] ?? '' )
	&& '94% remaining' === (string) ( $site_knowledge_usage['status_label'] ?? '' )
	&& 94 === (int) ( $site_knowledge_usage['percent'] ?? -1 )
	&& 'Indexed 599 documents; remaining 9,401 documents; limit 10,000 documents.' === (string) ( $site_knowledge_usage['tooltip'] ?? '' )
	&& '2026-07-15 00:00:00' === (string) ( $site_knowledge_direct['details']['lastSync']['value'] ?? '' )
	&& $site_knowledge_direct === $site_knowledge_usage
	&& ! empty( $overview_metrics['runtime']['available'] )
	&& '8 of 10 runs remaining' === (string) ( $overview_metrics['runtime']['label'] ?? '' )
	&& empty( $missing_overview_metrics['credits']['available'] )
	&& empty( $missing_overview_metrics['runtime']['available'] ),
	'Behavior: direct Site Knowledge projection matches the settings facade while overview copy avoids duplicate fallbacks.'
);

$legacy_contract_data = $entitlement_response['data'];
$legacy_contract_data['quota_summary']['credit_usage_detail'] = $legacy_contract_data['quota_summary']['ai_credit_usage_detail'];
unset( $legacy_contract_data['quota_summary']['ai_credit_usage_detail'] );
$wrong_unit_data = $entitlement_response['data'];
$wrong_unit_data['quota_summary']['ai_credit_usage_detail']['summary']['unit'] = 'credit';
$combined_credit_data = $entitlement_response['data'];
$combined_credit_data['quota_summary']['ai_credit_usage_detail']['summary'] = array(
	'used'         => 740,
	'limit'        => 10300,
	'remaining'    => 9560,
	'unit'         => 'ai_credits',
	'status'       => 'ok',
	'rate_version' => 'ai-credit-ledger-v2',
);
$legacy_contract_summary = $normalize_cloud_entitlement->invoke( null, $legacy_contract_data, array() );
$wrong_unit_summary = $normalize_cloud_entitlement->invoke( null, $wrong_unit_data, array() );
$combined_credit_summary = $normalize_cloud_entitlement->invoke( null, $combined_credit_data, array() );
maca_assert(
	empty( $legacy_contract_summary['ai_credit_usage_detail']['available'] )
	&& empty( $wrong_unit_summary['ai_credit_usage_detail']['available'] ),
	'Behavior: entitlement projection rejects the legacy credit key and non-ai_credits unit instead of applying compatibility defaults.'
);
maca_assert(
	740.0 === (float) ( $combined_credit_summary['ai_credit_usage_detail']['summary']['used'] ?? 0 )
	&& 10300.0 === (float) ( $combined_credit_summary['ai_credit_usage_detail']['summary']['limit'] ?? 0 )
	&& 9560.0 === (float) ( $combined_credit_summary['ai_credit_usage_detail']['summary']['remaining'] ?? 0 ),
	'Behavior: Addon preserves Cloud used, limit, and remaining after combined grant, adjustment, and consumption ledger events without recalculating billing truth.'
);

$site_knowledge_projection_cases = array(
	'unavailable' => array( 'input' => array( 'state' => 'unavailable' ), 'available' => false, 'severity' => 'ok' ),
	'near_limit' => array(
		'input' => array( 'state' => 'fresh', 'available' => true, 'quota_status' => 'near_limit', 'indexed_documents' => 90, 'max_documents' => 100, 'remaining_documents' => 10 ),
		'available' => true,
		'severity' => 'warning',
	),
	'limited' => array(
		'input' => array( 'state' => 'fresh', 'available' => true, 'quota_status' => 'limited', 'indexed_documents' => 100, 'max_documents' => 100, 'remaining_documents' => 0 ),
		'available' => true,
		'severity' => 'error',
	),
	'invalid_last_sync' => array(
		'input' => array( 'state' => 'fresh', 'available' => true, 'indexed_documents' => 1, 'max_documents' => 100, 'remaining_documents' => 99, 'last_sync_at' => 'not-a-date' ),
		'available' => true,
		'severity' => 'ok',
		'last_sync_at' => 'not-a-date',
	),
);
foreach ( $site_knowledge_projection_cases as $case_name => $case ) {
	$projection = Npcink_Cloud_Site_Knowledge_Admin_Projection::build( $case['input'] );
	$expected_last_sync = (string) ( $case['last_sync_at'] ?? '' );
	maca_assert(
		(bool) $case['available'] === ! empty( $projection['available'] )
		&& (string) $case['severity'] === (string) ( $projection['severity'] ?? '' )
		&& ( '' === $expected_last_sync || ( ! empty( $projection['details']['lastSync']['available'] ) && $expected_last_sync === (string) ( $projection['details']['lastSync']['value'] ?? '' ) ) ),
		'Behavior: Site Knowledge projection handles ' . $case_name . ' without changing bounded availability, severity, or timestamp fallback.'
	);
}

maca_reset_test_state();
maca_seed_settings( true );
$cache_key_method = maca_private_method( Npcink_Cloud_Entitlement_Summary::class, 'cache_key' );
$refresh_lock_key = $cache_key_method->invoke( null, Npcink_Cloud_Addon_Settings::get_settings() ) . '_refresh_lock';
$GLOBALS['maca_options'][ $refresh_lock_key ] = time();
$locked_refresh = Npcink_Cloud_Entitlement_Summary::refresh( false );

maca_assert(
	'refreshing' === (string) ( $locked_refresh['state'] ?? '' )
	&& 0 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: concurrent entitlement refreshes share one short cross-request lock.'
);

delete_option( $refresh_lock_key );
$GLOBALS['maca_http_response_queue'][] = new WP_Error( 'cloud_unavailable', 'Cloud unavailable.' );
$failed_refresh = Npcink_Cloud_Entitlement_Summary::refresh( true );
$backed_off_refresh = Npcink_Cloud_Entitlement_Summary::refresh( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body' => wp_json_encode( $entitlement_response ),
);
$manual_retry = Npcink_Cloud_Entitlement_Summary::refresh( false );

maca_assert(
	empty( $failed_refresh['available'] )
	&& empty( $backed_off_refresh['available'] )
	&& 2 === count( $GLOBALS['maca_http_requests'] )
	&& ! empty( $manual_retry['available'] )
	&& 'Pro' === (string) ( $manual_retry['package_label'] ?? '' ),
	'Behavior: automatic entitlement refresh backs off after failure while an explicit retry may recover immediately.'
);

$runtime_normalizer = maca_private_method( Npcink_Cloud_Entitlement_Summary::class, 'normalize_pro_cloud_runtime' );
$runtime_not_reported = $runtime_normalizer->invoke( null, array() );
$runtime_without_optional_fields = $runtime_normalizer->invoke(
	null,
	array(
		'max_nightly_inspection_runs_per_period' => 10,
		'used_nightly_inspection_runs'           => 2,
		'quota_exhausted'                       => 'false',
	)
);
$runtime_with_optional_fields = $runtime_normalizer->invoke(
	null,
	array(
		'max_nightly_inspection_runs_per_period' => 10,
		'used_nightly_inspection_runs'           => 10,
		'max_batch_items'                       => '25',
		'result_retention_days'                 => '14',
	)
);

$format_runtime_days = maca_private_method( Npcink_Cloud_Settings_Page::class, 'format_runtime_days_projection' );

maca_assert(
	is_array( $runtime_without_optional_fields )
	&& empty( $runtime_not_reported['reported'] )
	&& ! empty( $runtime_without_optional_fields['reported'] )
	&& null === ( $runtime_without_optional_fields['max_batch_items'] ?? null )
	&& null === ( $runtime_without_optional_fields['result_retention_days'] ?? null )
	&& false === (bool) ( $runtime_without_optional_fields['quota_exhausted'] ?? true )
	&& 'runtime_detail' === (string) ( $runtime_without_optional_fields['contract_reuse']['cloud_role'] ?? '' )
	&& 'product_surface' === (string) ( $runtime_without_optional_fields['contract_reuse']['toolbox_role'] ?? '' )
	&& 'proposal_handoff' === (string) ( $runtime_without_optional_fields['contract_reuse']['core_role'] ?? '' )
	&& 'execution_profiles' === (string) ( $runtime_without_optional_fields['contract_reuse']['adapter_role'] ?? '' )
	&& 'ability_contracts' === (string) ( $runtime_without_optional_fields['contract_reuse']['toolkit_role'] ?? '' )
	&& false === (bool) ( $runtime_without_optional_fields['contract_reuse']['adds_registry'] ?? true )
	&& false === (bool) ( $runtime_without_optional_fields['contract_reuse']['adds_scheduler_truth'] ?? true )
	&& false === (bool) ( $runtime_without_optional_fields['contract_reuse']['adds_approval_store'] ?? true )
	&& false === (bool) ( $runtime_without_optional_fields['contract_reuse']['adds_queue'] ?? true )
	&& false === (bool) ( $runtime_without_optional_fields['contract_reuse']['adds_write_executor'] ?? true )
	&& 'unavailable' === $format_runtime_days->invoke( null, $runtime_without_optional_fields, 'result_retention_days' )
	&& is_array( $runtime_with_optional_fields )
	&& true === (bool) ( $runtime_with_optional_fields['quota_exhausted'] ?? false )
	&& '14 days' === $format_runtime_days->invoke( null, $runtime_with_optional_fields, 'result_retention_days' ),
	'Behavior: Pro Cloud Runtime projection preserves unavailable optional fields, contract reuse boundaries, and quota exhaustion strictly.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( $entitlement_response ),
);

$client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
$probe  = $client->probe_connectivity();
$readiness = is_array( $probe['readiness_result'] ?? null ) ? $probe['readiness_result'] : array();
$readiness_support_facts = is_array( $readiness['copyable_support_facts'] ?? null ) ? $readiness['copyable_support_facts'] : array();
$readiness_groups = is_array( $readiness['diagnostic_panel_groups'] ?? null ) ? $readiness['diagnostic_panel_groups'] : array();
$readiness_groups_by_id = array_column( $readiness_groups, null, 'diagnostic_panel_group' );
$probe_live_request = $GLOBALS['maca_http_requests'][0] ?? array();
$probe_signed_request = $GLOBALS['maca_http_requests'][1] ?? array();

maca_assert(
	! empty( $probe['ok'] )
	&& is_array( $probe['entitlement_response'] ?? null )
	&& 'cloud_addon_readiness_result.v1' === (string) ( $readiness['contract_version'] ?? '' )
	&& 'probe_connectivity' === (string) ( $readiness['manual_test_action'] ?? '' )
	&& 'ready' === (string) ( $readiness['status'] ?? '' )
	&& 'ready' === (string) ( $readiness['bounded_status'] ?? '' )
	&& 'cloud_addon' === (string) ( $readiness['owner_label'] ?? '' )
	&& 'continue' === (string) ( $readiness['next_safe_action'] ?? '' )
	&& 'read_only' === (string) ( $readiness['write_posture'] ?? '' )
	&& 'npcink_cloud_runtime' === (string) ( $readiness['connector_slot'] ?? '' )
	&& 'ready' === (string) ( $readiness['connector_diagnostic_category'] ?? '' )
	&& 'ready' === (string) ( $readiness['credential_slot_readiness'] ?? '' )
	&& 'ready' === (string) ( $readiness['service_liveness_status'] ?? '' )
	&& 'ready' === (string) ( $readiness['signed_transport_status'] ?? '' )
	&& 'ready' === (string) ( $readiness_support_facts['connector_diagnostic_category'] ?? '' )
	&& 'ready' === (string) ( $readiness_support_facts['credential_slot_readiness'] ?? '' )
	&& 'yes' === (string) ( $readiness_support_facts['base_url_present'] ?? '' )
	&& 'yes' === (string) ( $readiness_support_facts['signing_secret_slot_present'] ?? '' )
	&& 5 === count( $readiness_groups )
	&& 'ok' === (string) ( $readiness_groups_by_id['local_configuration']['severity'] ?? '' )
	&& 'cloud_addon' === (string) ( $readiness_groups_by_id['signed_transport']['owner_label'] ?? '' )
	&& 'ready' === (string) ( $readiness_groups_by_id['entitlement_readiness']['bounded_status'] ?? '' )
	&& 'administrator_only' === (string) ( $readiness_groups_by_id['support_facts']['visibility'] ?? '' )
	&& 'read_only' === (string) ( $readiness_groups_by_id['support_facts']['write_posture'] ?? '' )
	&& 'Pro' === (string) ( $probe['entitlement_response']['data']['package'] ?? '' )
	&& 2 === count( $GLOBALS['maca_http_requests'] )
	&& false !== strpos( (string) ( $probe_live_request['url'] ?? '' ), '/health/live' )
	&& false !== strpos( (string) ( $probe_signed_request['url'] ?? '' ), '/v1/entitlements/current' ),
	'Behavior: connectivity verification runs liveness plus signed entitlement read and exposes a bounded ready result for cache reuse.'
);

maca_reset_test_state();
$not_configured_client = new Npcink_Cloud_Runtime_Client( array() );
$not_configured = $not_configured_client->manual_readiness_test();
$not_configured_support_facts = is_array( $not_configured['copyable_support_facts'] ?? null ) ? $not_configured['copyable_support_facts'] : array();
$not_configured_groups = is_array( $not_configured['diagnostic_panel_groups'] ?? null ) ? array_column( $not_configured['diagnostic_panel_groups'], null, 'diagnostic_panel_group' ) : array();

maca_assert(
	'cloud_addon_readiness_result.v1' === (string) ( $not_configured['contract_version'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured['status'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured['bounded_status'] ?? '' )
	&& 'operator' === (string) ( $not_configured['owner_label'] ?? '' )
	&& 'open_settings' === (string) ( $not_configured['next_safe_action'] ?? '' )
	&& 'read_only' === (string) ( $not_configured['write_posture'] ?? '' )
	&& 'npcink_cloud_runtime' === (string) ( $not_configured['connector_slot'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured['connector_diagnostic_category'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured['credential_slot_readiness'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured['service_liveness_status'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured['signed_transport_status'] ?? '' )
	&& 'not_configured' === (string) ( $not_configured_support_facts['connector_diagnostic_category'] ?? '' )
	&& 'no' === (string) ( $not_configured_support_facts['base_url_present'] ?? '' )
	&& 'no' === (string) ( $not_configured_support_facts['signing_secret_slot_present'] ?? '' )
	&& 'inactive' === (string) ( $not_configured_groups['local_configuration']['severity'] ?? '' )
	&& 'open_settings' === (string) ( $not_configured_groups['signed_transport']['next_safe_action'] ?? '' )
	&& '' !== (string) ( $not_configured_groups['signed_transport']['blocked_reason'] ?? '' )
	&& 0 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: manual readiness test returns a bounded not_configured result without a signed Cloud request.'
);

maca_reset_test_state();
$partial_client = new Npcink_Cloud_Runtime_Client(
	array(
		'base_url' => 'https://cloud.example.test',
	)
);
$partial = $partial_client->manual_readiness_test();
$partial_support_facts = is_array( $partial['copyable_support_facts'] ?? null ) ? $partial['copyable_support_facts'] : array();
$partial_groups = is_array( $partial['diagnostic_panel_groups'] ?? null ) ? array_column( $partial['diagnostic_panel_groups'], null, 'diagnostic_panel_group' ) : array();

maca_assert(
	'not_configured' === (string) ( $partial['status'] ?? '' )
	&& 'operator' === (string) ( $partial['owner_label'] ?? '' )
	&& 'credential_missing' === (string) ( $partial['connector_diagnostic_category'] ?? '' )
	&& 'partial' === (string) ( $partial['credential_slot_readiness'] ?? '' )
	&& 'ready' === (string) ( $partial['service_liveness_status'] ?? '' )
	&& 'not_configured' === (string) ( $partial['signed_transport_status'] ?? '' )
	&& 'credential_missing' === (string) ( $partial_support_facts['connector_diagnostic_category'] ?? '' )
	&& 'yes' === (string) ( $partial_support_facts['base_url_present'] ?? '' )
	&& 'no' === (string) ( $partial_support_facts['signing_credentials_complete'] ?? '' )
	&& 'not_configured' === (string) ( $partial_groups['local_configuration']['bounded_status'] ?? '' )
	&& 'ready' === (string) ( $partial_groups['cloud_connectivity']['bounded_status'] ?? '' )
	&& 1 === count( $GLOBALS['maca_http_requests'] ),
	'Behavior: manual readiness test classifies partial connector credentials as credential_missing without a signed Cloud request.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 403 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'error',
			'message' => 'Signed read rejected for Bearer secret_test and mak1_sensitive.',
		)
	),
);

$failed_client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
$failed = $failed_client->manual_readiness_test();
$failed_json = wp_json_encode( $failed );
$failed_support_facts = is_array( $failed['copyable_support_facts'] ?? null ) ? $failed['copyable_support_facts'] : array();
$failed_groups = is_array( $failed['diagnostic_panel_groups'] ?? null ) ? array_column( $failed['diagnostic_panel_groups'], null, 'diagnostic_panel_group' ) : array();
$failed_live_request = $GLOBALS['maca_http_requests'][0] ?? array();
$failed_signed_request = $GLOBALS['maca_http_requests'][1] ?? array();

maca_assert(
	'failed' === (string) ( $failed['status'] ?? '' )
	&& 'failed' === (string) ( $failed['bounded_status'] ?? '' )
	&& 'cloud' === (string) ( $failed['owner_label'] ?? '' )
	&& 'retry_test' === (string) ( $failed['next_safe_action'] ?? '' )
	&& 'read_only' === (string) ( $failed['write_posture'] ?? '' )
	&& 'npcink_cloud_runtime' === (string) ( $failed['connector_slot'] ?? '' )
	&& 'signed_transport_failed' === (string) ( $failed['connector_diagnostic_category'] ?? '' )
	&& 'ready' === (string) ( $failed['credential_slot_readiness'] ?? '' )
	&& 'ready' === (string) ( $failed['service_liveness_status'] ?? '' )
	&& 'failed' === (string) ( $failed['signed_transport_status'] ?? '' )
	&& 'npcink_cloud_runtime' === (string) ( $failed_support_facts['connector_slot'] ?? '' )
	&& 'signed_transport_failed' === (string) ( $failed_support_facts['connector_diagnostic_category'] ?? '' )
	&& 'failed' === (string) ( $failed_support_facts['signed_transport_status'] ?? '' )
	&& 'yes' === (string) ( $failed_support_facts['site_id_present'] ?? '' )
	&& 'yes' === (string) ( $failed_support_facts['key_id_present'] ?? '' )
	&& 'yes' === (string) ( $failed_support_facts['signing_secret_slot_present'] ?? '' )
	&& 'yes' === (string) ( $failed_support_facts['signing_credentials_complete'] ?? '' )
	&& 'error' === (string) ( $failed_groups['signed_transport']['severity'] ?? '' )
	&& 'retry_test' === (string) ( $failed_groups['entitlement_readiness']['next_safe_action'] ?? '' )
	&& false === strpos( (string) $failed_json, 'secret_test' )
	&& false === strpos( (string) $failed_json, 'mak1_sensitive' )
	&& false === strpos( (string) $failed_json, 'Bearer secret' )
	&& 2 === count( $GLOBALS['maca_http_requests'] )
	&& false !== strpos( (string) ( $failed_live_request['url'] ?? '' ), '/health/live' )
	&& false !== strpos( (string) ( $failed_signed_request['url'] ?? '' ), '/v1/entitlements/current' ),
	'Behavior: manual readiness test explicitly runs liveness plus signed entitlement read and returns failed support facts without exposing secrets.'
);

maca_reset_test_state();
maca_seed_settings( false );
$settings = Npcink_Cloud_Addon_Settings::get_settings();
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( $entitlement_response ),
);

$method = maca_private_method( Npcink_Cloud_Settings_Page::class, 'persist_and_verify_settings' );
$method->invoke( null, $settings, 'Verified.' );
$post_verify_summary = Npcink_Cloud_Entitlement_Summary::get_cached_summary();
$post_verify_urls = array_map(
	static function ( array $request ): string {
		return (string) ( $request['url'] ?? '' );
	},
	$GLOBALS['maca_http_requests']
);

maca_assert(
	Npcink_Cloud_Addon_Settings::is_verified()
	&& 3 === count( $GLOBALS['maca_http_requests'] )
	&& 1 === count( array_filter( $post_verify_urls, static fn( string $url ): bool => false !== strpos( $url, '/v1/entitlements/current' ) ) )
	&& 1 === count( array_filter( $post_verify_urls, static fn( string $url ): bool => false !== strpos( $url, '/v1/observability/plugin-events' ) ) )
	&& ! empty( $post_verify_summary['available'] )
	&& 'Pro' === (string) ( $post_verify_summary['package_label'] ?? '' ),
	'Behavior: re-verify reuses the entitlement response and adds only one monitoring-state projection.'
);

maca_reset_test_state();
maca_seed_settings( true );
$settings = Npcink_Cloud_Addon_Settings::get_settings();
$GLOBALS['maca_http_response_queue'][] = new WP_Error( 'cloud_unavailable', 'Cloud unavailable.' );

$method->invoke( null, $settings, 'Verified.' );
$failed_state = Npcink_Cloud_Addon_Settings::get_credential_state();

maca_assert(
	'configured_unavailable' === (string) ( $failed_state['code'] ?? '' )
	&& false !== strpos( (string) ( $failed_state['message'] ?? '' ), 'Cloud unavailable.' )
	&& false === get_transient( 'npcink_cloud_notice_1' ),
	'Behavior: verification failure remains visible in the connection summary without a duplicate redirect notice.'
);
