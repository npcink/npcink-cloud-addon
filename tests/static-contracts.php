<?php
/**
 * Static contract tests for Npcink Cloud Addon.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

/**
 * Extracts one class method up to the next visible static method declaration.
 *
 * @param string $source Full PHP source.
 * @param string $signature Exact method signature.
 * @return string
 */
function maca_extract_class_method_source( string $source, string $signature ): string {
	$start = strpos( $source, $signature );
	if ( false === $start ) {
		return '';
	}

	$next_method = array();
	$matched = preg_match(
		'/\n\s+(?:public|protected|private) static function /',
		$source,
		$next_method,
		PREG_OFFSET_CAPTURE,
		$start + strlen( $signature )
	);
	$end = 1 === $matched ? (int) $next_method[0][1] : strlen( $source );

	return substr( $source, $start, $end - $start );
}

$root = MACA_TEST_ROOT;
$plugin_file = maca_read( $root . '/npcink-cloud-addon.php' );
$wordpress_org_readme = maca_read( $root . '/readme.txt' );
$pot = maca_read( $root . '/languages/npcink-cloud-addon.pot' );
$bootstrap = maca_read( $root . '/includes/bootstrap.php' );
$credential_store = maca_read( $root . '/includes/class-cloud-credential-store.php' );
$outbound_policy = maca_read( $root . '/includes/class-cloud-outbound-policy.php' );
$runtime_endpoint_policy = maca_read( $root . '/includes/class-cloud-runtime-endpoint-policy.php' );
$transport = maca_read( $root . '/includes/class-cloud-media-derivative-transport.php' );
$runtime_client = maca_read( $root . '/includes/class-cloud-runtime-client.php' );
$ai_task_contract = maca_read( $root . '/includes/class-cloud-ai-task-contract.php' );
$wordpress_ai_connector = maca_read( $root . '/includes/class-cloud-wordpress-ai-connector.php' );
$cloud_addon_localization = maca_read( $root . '/includes/class-cloud-addon-localization.php' );
$ai_plugin_localization = maca_read( $root . '/includes/class-ai-plugin-localization.php' );
$ai_plugin_localization_js = maca_read( $root . '/assets/ai-plugin-localization.js' );
$admin_entitlement_js = maca_read( $root . '/assets/admin-entitlement.js' );
$admin_site_knowledge_js = maca_read( $root . '/assets/admin-site-knowledge.js' );
$admin_css = maca_read( $root . '/assets/admin.css' );
$entitlement_summary = maca_read( $root . '/includes/class-cloud-entitlement-summary.php' );
$observability = maca_read( $root . '/includes/class-cloud-observability-collector.php' );
$site_knowledge_bridge = maca_read( $root . '/includes/class-cloud-site-knowledge-change-bridge.php' );
$site_knowledge_full_index_doc = maca_read( $root . '/docs/site-knowledge-full-index-delivery.md' );
$site_knowledge_runtime_bridge = maca_read( $root . '/includes/class-cloud-site-knowledge-runtime-bridge.php' );
$site_knowledge_admin_projection = maca_read( $root . '/includes/class-cloud-site-knowledge-admin-projection.php' );
$site_knowledge_admin_actions = maca_read( $root . '/includes/class-cloud-site-knowledge-admin-actions.php' );
$runtime_runs_presenter = maca_read( $root . '/includes/class-cloud-runtime-runs-presenter.php' );
$settings = maca_read( $root . '/includes/class-cloud-addon-settings.php' );
$settings_page = maca_read( $root . '/includes/class-cloud-settings-page.php' );
$refresh_site_knowledge_handler = maca_extract_class_method_source( $settings_page, 'public static function handle_refresh_site_knowledge(): void' );
$manage_site_knowledge_index_handler = maca_extract_class_method_source( $settings_page, 'public static function handle_manage_site_knowledge_index(): void' );
$boundary_doc = maca_read( $root . '/docs/cloud-addon-boundary.md' );
$runtime_contract = maca_read( $root . '/docs/cloud-runtime-client-contract.md' );
$adapter_doc = maca_read( $root . '/docs/adapter-integration-seam.md' );
$complexity_doc = maca_read( $root . '/docs/cloud-addon-complexity-budget.md' );
$test_helpers = maca_read( $root . '/tests/helpers.php' );
$test_runner = maca_read( $root . '/tests/run.php' );

$runtime_endpoint_policy_forbidden = array(
	'Npcink_Cloud_Runtime_Client', 'Npcink_Cloud_Outbound_Policy', 'wp_remote_', 'wp_safe_remote_', 'curl_',
	'WP_Error', '__(', '_x(', 'esc_html__(', 'hash_hmac', 'secret', 'signature', 'nonce', 'trace',
	'get_option(', 'update_option(', 'add_option(', 'delete_option(', 'get_transient(', 'set_transient(', 'delete_transient(',
	'add_action(', 'add_filter(', 'do_action(', 'apply_filters(', '$_GET', '$_POST', '$_REQUEST', '$_SERVER', '$_COOKIE', '$_FILES',
	'wp_json_encode(', 'json_encode(', 'json_decode(', 'payload', 'response', 'base_url',
);
$runtime_endpoint_policy_has_forbidden = false;
foreach ( $runtime_endpoint_policy_forbidden as $forbidden_policy_dependency ) {
	$runtime_endpoint_policy_has_forbidden = $runtime_endpoint_policy_has_forbidden
		|| false !== strpos( $runtime_endpoint_policy, $forbidden_policy_dependency );
}
$contract_reuse_readiness_doc = maca_read( $root . '/docs/cloud-addon-contract-reuse-readiness-2026-07-08.md' );
$cloud_bulk_article_doc = maca_read( $root . '/docs/cloud-bulk-article-run-seam.md' );
$admin_surface_standard = maca_read( $root . '/docs/admin-surface-standard.md' );
$admin_ui_simplification_doc = maca_read( $root . '/docs/cloud-addon-admin-ui-simplification-2026-07-02.md' );
$site_knowledge_vector_ops_doc = maca_read( $root . '/docs/site-knowledge-vector-operations.md' );
$public_onboarding_doc = maca_read( $root . '/docs/public-cloud-onboarding-checklist.md' );
$agents = maca_read( $root . '/AGENTS.md' );
$readme = maca_read( $root . '/README.md' );
$local_test_guide = maca_read( $root . '/docs/local-test-guide.md' );
$composer = maca_read( $root . '/composer.json' );
$ci_workflow = maca_read( $root . '/.github/workflows/ci.yml' );
$pr_body_workflow = maca_read( $root . '/.github/workflows/pr-body-contract.yml' );
$pr_template = maca_read( $root . '/.github/pull_request_template.md' );
$pr_publisher = maca_read( $root . '/scripts/publish-pr.sh' );
$composer_config = json_decode( $composer, true );
$boundary_guard = is_array( $composer_config )
	? (string) ( $composer_config['scripts']['check:boundary'] ?? '' )
	: '';
$boundary_write_functions = array(
	'wp_insert_post',
	'wp_update_post',
	'wp_insert_attachment',
	'wp_update_attachment_metadata',
	'update_post_meta',
	'wp_set_post_terms',
	'set_post_thumbnail',
	'media_handle_sideload',
);
$boundary_write_call_pattern = '\\b(?:' . implode( '|', $boundary_write_functions ) . ')\\s*\\(';
$expected_boundary_pattern = '/v1/runtime/workflows/' . 'runs|' . $boundary_write_call_pattern;
$eval_lab_proxy = maca_read( $root . '/scripts/eval-lab.sh' );
$ai_i18n_audit = maca_read( $root . '/scripts/audit-ai-plugin-localization.php' );
$wp_ai_smoke = maca_read( $root . '/scripts/smoke-wordpress-ai-abilities.php' );
$wp_ai_editor_smoke = maca_read( $root . '/scripts/smoke-wordpress-ai-editor.php' );
$wp_ai_text_browser_smoke = maca_read( $root . '/scripts/smoke-wordpress-ai-text-browser.mjs' );
$wp_ai_generation_eval = maca_read( $root . '/scripts/eval-wordpress-ai-generation-reference.php' );
$zh_cn_po = maca_read( $root . '/languages/npcink-cloud-addon-zh_CN.po' );
$uninstall = maca_read( $root . '/uninstall.php' );

$projection_require_position = strpos( $bootstrap, "require_once __DIR__ . '/class-cloud-site-knowledge-admin-projection.php';" );
$admin_actions_require_position = strpos( $bootstrap, "require_once __DIR__ . '/class-cloud-site-knowledge-admin-actions.php';" );
$runtime_presenter_require_position = strpos( $bootstrap, "require_once __DIR__ . '/class-cloud-runtime-runs-presenter.php';" );
$settings_page_require_position = strpos( $bootstrap, "require_once __DIR__ . '/class-cloud-settings-page.php';" );
$projection_forbidden_calls = array(
	'wp_remote_', 'wp_safe_remote_', 'get_option(', 'update_option(', 'add_option(', 'delete_option(',
	'get_transient(', 'set_transient(', 'delete_transient(', 'add_action(', 'add_filter(',
);
$projection_has_side_effect_call = false;
foreach ( $projection_forbidden_calls as $forbidden_call ) {
	$projection_has_side_effect_call = $projection_has_side_effect_call || false !== strpos( $site_knowledge_admin_projection, $forbidden_call );
}

maca_assert(
	false !== $projection_require_position
	&& false !== $settings_page_require_position
	&& $projection_require_position < $settings_page_require_position
	&& false !== strpos( $site_knowledge_admin_projection, 'final class Npcink_Cloud_Site_Knowledge_Admin_Projection' )
	&& false !== strpos( $site_knowledge_admin_projection, 'public static function build' )
	&& false !== strpos( $settings_page, 'return Npcink_Cloud_Site_Knowledge_Admin_Projection::build( $summary );' )
	&& ! $projection_has_side_effect_call,
	'Site Knowledge admin quota projection is loaded before the settings facade and remains transport-, persistence-, and hook-free.'
);

$admin_actions_forbidden_calls = array(
	'$_POST', '$_GET', '$_REQUEST', '$_SERVER', '$_COOKIE', '$_FILES', 'current_user_can(', 'check_admin_referer(', 'set_admin_notice(', 'redirect_to_page(', 'wp_safe_redirect(',
	'wp_remote_', 'wp_safe_remote_', 'get_option(', 'update_option(', 'add_option(', 'delete_option(',
	'get_transient(', 'set_transient(', 'delete_transient(', 'add_action(', 'add_filter(', 'Npcink_Cloud_Runtime_Client',
);
$admin_actions_has_forbidden_call = false;
foreach ( $admin_actions_forbidden_calls as $forbidden_call ) {
	$admin_actions_has_forbidden_call = $admin_actions_has_forbidden_call || false !== strpos( $site_knowledge_admin_actions, $forbidden_call );
}

maca_assert(
	false !== $admin_actions_require_position
	&& $admin_actions_require_position < $settings_page_require_position
	&& false !== strpos( $site_knowledge_admin_actions, 'final class Npcink_Cloud_Site_Knowledge_Admin_Actions' )
	&& false !== strpos( $site_knowledge_admin_actions, 'public static function request_public_refresh(): array' )
	&& false !== strpos( $site_knowledge_admin_actions, 'public static function request_index_operation( string $operation, string $confirmation = \'\' ): array' )
	&& ! $admin_actions_has_forbidden_call,
	'Site Knowledge administrator actions load before the settings facade and remain request-, transport-, persistence-, hook-, and Runtime Client-free.'
);

$runtime_presenter_forbidden_calls = array(
	'wp_remote_', 'wp_safe_remote_', 'get_option(', 'update_option(', 'add_option(', 'delete_option(', 'get_transient(', 'set_transient(', 'delete_transient(',
	'add_action(', 'add_filter(', '$_GET', '$_POST', '$_REQUEST', '$_SERVER', '$_COOKIE', '$_FILES', 'current_user_can(', 'check_admin_referer(', 'wp_create_nonce(', 'wp_verify_nonce(', 'wp_nonce_', 'wp_safe_redirect(', 'wp_redirect(', 'Npcink_Cloud_Runtime_Client', 'echo ', 'exit;',
);
$runtime_presenter_has_forbidden_call = false;
foreach ( $runtime_presenter_forbidden_calls as $forbidden_call ) {
	$runtime_presenter_has_forbidden_call = $runtime_presenter_has_forbidden_call || false !== strpos( $runtime_runs_presenter, $forbidden_call );
}
maca_assert(
	false !== $runtime_presenter_require_position && $runtime_presenter_require_position < $settings_page_require_position
	&& false !== strpos( $runtime_runs_presenter, 'final class Npcink_Cloud_Runtime_Runs_Presenter' )
	&& false !== strpos( $runtime_runs_presenter, 'public static function recent_rows' )
	&& false !== strpos( $runtime_runs_presenter, 'public static function detail' )
	&& false !== strpos( $runtime_runs_presenter, 'public static function normalize_run_id' )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Runtime_Runs_Presenter::recent_rows' )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Runtime_Runs_Presenter::detail' )
	&& false === strpos( $settings_page, 'function format_runtime_status_label' )
	&& false === strpos( $settings_page, 'function runtime_runs_from_response' )
	&& false === strpos( $settings_page, 'function normalize_run_id' ) && false === strpos( $settings_page, 'function runtime_scalar' ) && false === strpos( $settings_page, 'function runtime_pick' )
	&& ! $runtime_presenter_has_forbidden_call,
	'Runtime Runs presenter loads before the settings facade and remains read-only, side-effect-free response projection.'
);

$plugin_header_version = array();
$plugin_constant_version = array();
$stable_tag_version = array();

maca_assert(
	1 === preg_match( '/^ \* Version:\s+([0-9.]+)$/m', $plugin_file, $plugin_header_version )
	&& 1 === preg_match( "/define\\( 'NPCINK_CLOUD_ADDON_VERSION', '([0-9.]+)' \\);/", $plugin_file, $plugin_constant_version )
	&& 1 === preg_match( '/^Stable tag:\s+([0-9.]+)$/m', $wordpress_org_readme, $stable_tag_version )
	&& $plugin_header_version[1] === $plugin_constant_version[1]
	&& $plugin_header_version[1] === $stable_tag_version[1]
	&& false !== strpos( $pot, 'Project-Id-Version: Npcink Cloud Addon ' . $plugin_header_version[1] . '\\n' )
	&& false !== strpos( $zh_cn_po, 'Project-Id-Version: Npcink Cloud Addon ' . $plugin_header_version[1] . '\\n' ),
	'Plugin header, version constant, stable tag, POT, and zh_CN PO stay on the same release version.'
);

maca_assert(
	false !== strpos( $composer, '"eval:project:quality": "sh scripts/eval-lab.sh task=project_quality_gate' )
	&& false !== strpos( $eval_lab_proxy, 'NPCINK_EVAL_LAB_PATH' )
	&& false !== strpos( $eval_lab_proxy, 'composer eval:task -- "$@"' ),
	'Cloud Addon exposes optional eval-lab project quality gate through the task registry.'
);
maca_assert(
	false !== strpos( $composer, '"check:boundary": "sh -c' )
	&& false !== strpos( $boundary_guard, $expected_boundary_pattern )
	&& false !== strpos( $agents, $expected_boundary_pattern )
	&& false !== strpos( $ci_workflow, 'run: composer run check:boundary' )
	&& false !== strpos( $boundary_guard, '--glob "*.php"' )
	&& false !== strpos( $boundary_guard, '--glob "!build/**"' )
	&& false !== strpos( $composer, '"@check:boundary"' )
	&& false !== strpos( $composer, '"@ai:i18n:audit"' ),
	'Cloud Addon release scripts enforce the retired workflow-route and all forbidden WordPress write API boundaries across PHP source.'
);
maca_assert(
	false === strpos( $composer, '@eval:lab' )
	&& false === strpos( $composer, '@eval:project:quality' )
	&& false !== strpos( $eval_lab_proxy, 'composer "$SCRIPT" -- "$@"' ),
	'Cloud Addon default tests stay independent from eval-lab and the wrapper keeps legacy Composer compatibility.'
);
maca_assert(
	false === strpos( $composer . "\n" . $eval_lab_proxy, 'sk-' ),
	'Cloud Addon eval-lab integration does not contain committed provider keys.'
);

maca_assert(
	false !== strpos( $composer, '"smoke:wp-ai-editor":' )
	&& false !== strpos( $wp_ai_editor_smoke, '/wp-abilities/v1/abilities/ai/title-generation/run' )
	&& false !== strpos( $wp_ai_editor_smoke, '/wp-abilities/v1/abilities/ai/summarization/run' )
	&& false !== strpos( $wp_ai_editor_smoke, '/wp-abilities/v1/abilities/ai/content-resizing/run' )
	&& false === strpos( $wp_ai_editor_smoke, '/wp-abilities/v1/abilities/ai/excerpt-generation/run' )
	&& false === strpos( $wp_ai_editor_smoke, '/wp-abilities/v1/abilities/ai/meta-description/run' )
	&& false === strpos( $wp_ai_editor_smoke, '/wp-abilities/v1/abilities/ai/content-classification/run' )
	&& false !== strpos( $wp_ai_editor_smoke, "'action'  => 'rephrase'" )
	&& false !== strpos( $wp_ai_editor_smoke, 'selected whole core/paragraph block' )
	&& false !== strpos( $wp_ai_editor_smoke, 'npcink_cloud_addon_wp_ai_editor_smoke_snapshot' )
	&& false !== strpos( $wp_ai_editor_smoke, 'npcink_cloud_addon_wp_ai_editor_smoke_apply_accepted_suggestions' )
	&& false !== strpos( $wp_ai_editor_smoke, '"aiGeneratedSummary":true' )
	&& false !== strpos( $wp_ai_editor_smoke, 'NPCINK_SENTINEL_NON_TARGET_PARAGRAPH_DO_NOT_CHANGE_20260718' )
	&& false !== strpos( $wp_ai_editor_smoke, 'Cloud zero-write' )
	&& false !== strpos( $wp_ai_editor_smoke, 'Second local apply helper call was a no-op and created no revision.' )
	&& false !== strpos( $wp_ai_editor_smoke, '} finally {' )
	&& false !== strpos( $wp_ai_editor_smoke, "array( 'force' => true )" )
	&& false !== strpos( $wp_ai_editor_smoke, 'confirmed cleanup' )
	&& false !== strpos( $wp_ai_editor_smoke, "'status'  => 'draft'" )
	&& false === strpos( $wp_ai_editor_smoke, "'status'  => 'publish'" )
	&& false === strpos( $wp_ai_editor_smoke, '"status":"publish"' )
	&& false !== strpos( $local_test_guide, 'the selected whole `core/paragraph` block' )
	&& false !== strpos( $local_test_guide, 'does not simulate browser review or Core audit' )
	&& false !== strpos( $local_test_guide, 'force-deletes the temporary draft and confirms cleanup' ),
	'WordPress AI editor smoke covers exactly title, summary, and selected whole-paragraph rewrite, proves Cloud zero-write, applies accepted draft changes once, and cleans up.'
);

maca_assert(
	false !== strpos( $composer, '"smoke:wp-ai-text-browser":' )
	&& false !== strpos( $composer, '"smoke:wp-ai-text-browser:preflight": "node scripts/smoke-wordpress-ai-text-browser.mjs --preflight-only"' )
	&& false !== strpos( $wp_ai_text_browser_smoke, "['-h', '--help'].includes(args[0])" )
	&& false !== strpos( $wp_ai_text_browser_smoke, "args[0] === '--preflight-only'" )
	&& false !== strpos( $wp_ai_text_browser_smoke, "contract: 'wordpress_ai_text_browser_preflight.v1'" )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'fixture_created: false' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'browser_started: false' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'provider_execution_attempted: false' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'wordpress_write_attempted: false' )
	&& false !== strpos( $wp_ai_text_browser_smoke, "readiness.ai_version === '1.2.0'" )
	&& false !== strpos( $wp_ai_text_browser_smoke, "['shorten', 'expand', 'rephrase']" )
	&& false === strpos( $wp_ai_text_browser_smoke, "'lengthen'" )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'pre_save_post_writes' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'explicit_save_writes' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'revision_delta' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'Browser smoke rejects non-Local host' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'Fresh editor startup overlays are dismissed before WordPress AI review.' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'destroyAuthSession' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'auth_session_destroyed' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'originalLabel === reviewLabels.original' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'suggestedLabel === reviewLabels.suggested' )
	&& false !== strpos( $wp_ai_text_browser_smoke, "getByRole('button', { name: reviewLabels.accept, exact: true })" )
	&& false !== strpos( $wp_ai_text_browser_smoke, "'serialized_hash' => hash('sha256', serialize_block(\$block))" )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'normalizeEvidenceText(finalSnapshot.summary_meta)' )
	&& false !== strpos( $wp_ai_text_browser_smoke, "env('WP_AI_TEXT_FAKE_PROVIDER') === '1'" )
	&& false !== strpos( $wp_ai_text_browser_smoke, "'pre_http_request'" )
	&& false !== strpos( $wp_ai_text_browser_smoke, "'data_classification'      =>" )
	&& false !== strpos( $wp_ai_text_browser_smoke, "'storage_mode'             =>" )
	&& false !== strpos( $wp_ai_text_browser_smoke, "'transport_preempted'      => true" )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'title_acceptance_evidence' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'content_fields_recorded: false' )
	&& false !== strpos( $wp_ai_text_browser_smoke, 'removeFakeProvider(fakeProvider)' )
	&& false !== strpos( $local_test_guide, 'official WordPress AI 1.2.0 plugin' )
	&& false !== strpos( $local_test_guide, 'composer run smoke:wp-ai-text-browser:preflight' )
	&& false !== strpos( $local_test_guide, 'It does not create a draft, start a' )
	&& false !== strpos( $local_test_guide, 'WP_AI_TEXT_FAKE_PROVIDER=1' )
	&& false !== strpos( $local_test_guide, 'It stores no prompt, post content, generated title, credentials, or' )
	&& false !== strpos( $local_test_guide, 'headers, dispatches no real Provider request' )
	&& false !== strpos( $local_test_guide, '`cloud_run_id` only inside metadata-only request context for correlation' )
	&& false !== strpos( $local_test_guide, 'proves zero post writes before the explicit Save/Update click' ),
	'Opt-in browser acceptance uses the official AI 1.2.0 UI, supports a local expiring fake Provider, preserves suggestion-only review, records content-free adoption evidence, and cleans up its local fixture.'
);

maca_assert(
	false !== strpos( $composer, '"eval:wp-ai-generation-reference": [' )
	&& false !== strpos( $composer, 'Composer\\\\Config::disableProcessTimeout' )
	&& false !== strpos( $wp_ai_generation_eval, "'posts_per_page' => 100" )
	&& false !== strpos( $wp_ai_generation_eval, "getenv( 'WP_AI_EVAL_POST_LIMIT' )" )
	&& false !== strpos( $wp_ai_generation_eval, "getenv( 'WP_AI_EVAL_OUTPUT_JSON' )" )
	&& false !== strpos( $wp_ai_generation_eval, "'ready_for_blind_judging'" )
	&& false !== strpos( $wp_ai_generation_eval, "'usable_pairs_gate_passed'" )
	&& false !== strpos( $wp_ai_generation_eval, "'strategy']       = 'existing_only'" )
	&& false !== strpos( $wp_ai_generation_eval, "'pre_option_' . Npcink_Cloud_Addon_Settings::option_name()" )
	&& false !== strpos( $wp_ai_generation_eval, 'finally' )
	&& false === strpos( $wp_ai_generation_eval, 'update_' . 'option' )
	&& false === strpos( $wp_ai_generation_eval, 'wp_insert_' . 'post' )
	&& false === strpos( $wp_ai_generation_eval, 'wp_update_' . 'post' )
	&& false === strpos( $wp_ai_generation_eval, 'wp_set_' . 'post_terms' ),
	'WordPress AI generation-reference collector auto-selects bounded public posts, writes an optional Eval Lab artifact, and remains suggestion-only.'
);

maca_assert(
	false !== strpos( $composer, '"ai:i18n:audit": "@php scripts/audit-ai-plugin-localization.php"' )
	&& false !== strpos( $ai_i18n_audit, 'AI_PLUGIN_PATH' )
	&& false !== strpos( $ai_i18n_audit, 'Npcink_Cloud_AI_Plugin_Localization::translations()' )
	&& false !== strpos( $ai_i18n_audit, 'Missing review groups' )
	&& false !== strpos( $ai_i18n_audit, 'fixed_ui_candidates' )
	&& false !== strpos( $ai_i18n_audit, 'dynamic_ability_metadata' )
	&& false !== strpos( $ai_i18n_audit, 'schema_or_json_fields' )
	&& false !== strpos( $ai_i18n_audit, 'long_prompt_copy' )
	&& false !== strpos( $ai_i18n_audit, 'Do not add dynamic ability names' )
	&& false === strpos( $ai_i18n_audit, 'npcink_cloud_addon_runtime_client' ),
	'AI plugin localization audit compares local ai-domain strings against the bounded shim without Cloud runtime.'
);

maca_assert(
	false !== strpos( $composer, '"pr:publish": "bash scripts/publish-pr.sh"' )
	&& false !== strpos( $pr_template, '## Scope' )
	&& false !== strpos( $pr_template, '## Cloud Addon Boundary' )
	&& false !== strpos( $pr_template, '## Verification' )
	&& false !== strpos( $pr_template, '## Risk' )
	&& false !== strpos( $pr_body_workflow, '"Scope":' )
	&& false !== strpos( $pr_body_workflow, '"Boundary":' )
	&& false !== strpos( $pr_body_workflow, '"Verification":' )
	&& false !== strpos( $pr_body_workflow, '"Risk":' )
	&& false !== strpos( $pr_publisher, "git status --porcelain" )
	&& false !== strpos( $pr_publisher, 'git merge-base --is-ancestor "origin/${base_branch}" HEAD' )
	&& false !== strpos( $pr_publisher, '--body-file "${body_path}"' )
	&& false !== strpos( $pr_publisher, '--auto --squash --match-head-commit "${head_sha}"' )
	&& false === strpos( $pr_publisher, '--delete-branch' )
	&& false !== strpos( $agents, 'composer pr:publish' )
	&& false !== strpos( $readme, 'composer pr:publish' ),
	'PR publishing reuses the checked-in body contract and requests protected squash auto-merge without deleting worktree branches.'
);

maca_assert(
	false !== strpos( $settings_page, "private const PARENT_MENU_SLUG = 'npcink-ai';" ),
	'Settings page targets the shared Npcink AI parent menu slug.'
);

maca_assert(
	false !== strpos( $settings_page, 'add_submenu_page' )
	&& false !== strpos( $settings_page, 'add_options_page' )
	&& false === strpos( $settings_page, 'ensure_parent_menu' )
	&& false === strpos( $settings_page, 'dashicons-superhero' )
	&& false !== strpos( $settings_page, 'render_overview_page' ),
	'Cloud Addon attaches to Toolbox navigation or falls back to Settings with a connector-only overview.'
);

maca_assert(
	false !== strpos( $settings_page, 'npcink-ai-tabs npcink-cloud-tabs' )
	&& false !== strpos( $settings_page, 'npcink-ai-tab-active' )
	&& false !== strpos( $settings_page, 'aria-current="page"' )
	&& false === strpos( $settings_page, 'nav-tab-wrapper' )
	&& false === strpos( $settings_page, 'nav-tab-active' )
	&& false !== strpos( $admin_css, '.npcink-ai-tabs' )
	&& false !== strpos( $admin_css, '.npcink-ai-tab-active' ),
	'Cloud Addon settings tabs use the shared Npcink AI tab visual standard instead of boxed WordPress nav tabs.'
);

maca_assert(
	false !== strpos( $bootstrap, 'plugin_action_links_' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_filter_plugin_action_links' )
	&& false !== strpos( $bootstrap, 'menu_page_url' )
	&& false !== strpos( $bootstrap, 'options-general.php?page=npcink-cloud-addon' ),
	'Plugin screen exposes a Settings shortcut to the registered Cloud Addon page or standalone Settings fallback.'
);

maca_assert(
	false !== strpos( $bootstrap, 'class-cloud-credential-store.php' )
	&& false !== strpos( $credential_store, "ALGORITHM_SODIUM = 'sodium_secretbox'" )
	&& false !== strpos( $credential_store, "ALGORITHM_OPENSSL = 'aes-256-gcm'" )
	&& false !== strpos( $credential_store, "wp_salt( 'auth' )" )
	&& false !== strpos( $settings, "'sanitize_callback' => array( __CLASS__, 'sanitize_option_value' )" )
	&& false !== strpos( $settings, "unset( \$settings['site_id'], \$settings['key_id'], \$settings['secret'] )" ),
	'Cloud signing credentials use authenticated encrypted option storage and every Settings API write emits the at-rest envelope.'
);

maca_assert(
	false !== strpos( $bootstrap, 'npcink_cloud_addon_get_manual_readiness_result' )
	&& false !== strpos( $bootstrap, 'does not create runtime work, queues, registries' )
	&& false !== strpos( $runtime_client, 'manual_readiness_test' )
	&& false !== strpos( $runtime_client, "'cloud_addon_readiness_result.v1'" )
	&& false !== strpos( $runtime_client, "'manual_test_action' => 'probe_connectivity'" )
	&& false !== strpos( $runtime_client, "'connector_slot' => 'npcink_cloud_runtime'" )
	&& false !== strpos( $runtime_client, 'classify_connector_diagnostic_category' )
	&& false !== strpos( $runtime_client, "'connector_diagnostic_category' => \$connector_diagnostic_category" )
	&& false !== strpos( $runtime_client, "'credential_slot_readiness' => \$credential_slot_readiness" )
	&& false !== strpos( $runtime_client, "'signed_transport_status' => \$signed_transport_status" )
	&& false !== strpos( $runtime_client, "'service_liveness_status' => \$service_liveness_status" )
	&& false !== strpos( $runtime_client, "'bounded_status' => \$status" )
	&& false !== strpos( $runtime_client, "'owner_label' => \$owner_label" )
	&& false !== strpos( $runtime_client, "'next_safe_action' => \$next_action" )
	&& false !== strpos( $runtime_client, "'copyable_support_facts' => \$support_facts" )
	&& false !== strpos( $runtime_client, "'diagnostic_panel_groups' => \$diagnostic_panel_groups" )
	&& false !== strpos( $runtime_client, "'diagnostic_panel_group' => \$group" )
	&& false !== strpos( $runtime_client, "'visibility' => 'administrator_only'" )
	&& false !== strpos( $runtime_client, "'write_posture' => 'read_only'" )
	&& false !== strpos( $settings_page, "ACTION_RUN_MANUAL_READINESS_TEST = 'npcink_cloud_addon_run_manual_readiness_test'" )
	&& false !== strpos( $settings_page, "admin_post_' . self::ACTION_RUN_MANUAL_READINESS_TEST" )
	&& false !== strpos( $settings_page, 'handle_run_manual_readiness_test' )
	&& false !== strpos( $settings_page, 'render_manual_readiness_test_form' )
	&& false !== strpos( $settings_page, 'get_manual_readiness_result' )
	&& false !== strpos( $settings_page, 'Run readiness test' )
	&& false !== strpos( $settings_page, 'Readiness result' )
	&& false === strpos( $settings_page, 'Readiness support facts' )
	&& false === strpos( $settings_page, 'data-diagnostic-panel-group' )
	&& false === strpos( $admin_css, '.npcink-cloud-diagnostic-group' )
	&& false === strpos( $settings_page, '$readiness = ( new Npcink_Cloud_Runtime_Client( $settings ) )->manual_readiness_test();' )
	&& false === strpos( $runtime_client, "'secret' => (string) ( \$this->config['secret']" ),
	'Manual readiness test exposes a bounded non-secret result shape through an explicit admin action without queue, registry, approval, or WordPress write ownership.'
);

maca_assert(
	false !== strpos( $plugin_file, 'Text Domain:       npcink-cloud-addon' )
	&& false !== strpos( $plugin_file, 'Domain Path:       /languages' )
	&& false !== strpos( $pot, 'X-Domain: npcink-cloud-addon' )
	&& false !== strpos( $zh_cn_po, 'Language: zh_CN' )
	&& false === strpos( $bootstrap, 'load_plugin_textdomain' ),
	'Plugin declares the npcink-cloud-addon text domain and ships generated language files.'
);

maca_assert(
	false !== strpos( $zh_cn_po, 'msgstr "Cloud 基础 URL"' )
	&& false !== strpos( $zh_cn_po, 'msgstr "托管运行时"' )
	&& false !== strpos( $zh_cn_po, 'msgstr "高级与排查"' )
	&& false !== strpos( $zh_cn_po, 'msgstr "技术投递详情"' )
	&& false !== strpos( $zh_cn_po, 'msgstr "更多本地授权"' )
	&& false !== strpos( $zh_cn_po, 'msgstr "Cloud 错误分类"' )
	&& false !== strpos( $pot, 'Cloud credentials could not be stored or read securely.' )
	&& false !== strpos( $zh_cn_po, 'msgstr "无法安全存储或读取 Cloud 凭据。请检查 WordPress 安全盐后重新连接此站点。"' )
	&& false !== strpos( $zh_cn_po, 'msgstr "无法安全存储 Cloud 凭据。现有连接未更改。请检查 WordPress 安全盐并重新连接。"' ),
	'Chinese localization translates fixed Cloud Addon admin terminology without translating dynamic metadata.'
);

maca_assert(
	false !== strpos( $bootstrap, 'class-cloud-media-derivative-transport.php' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_verified_runtime_client' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_dispatch_media_derivative_cloud_request' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_request_image_context_evidence' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_execute_wordpress_ai_connector_runtime' )
	&& false !== strpos( $bootstrap, 'array $watermark_artifact = array()' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_build_media_derivative_proposal_payload' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_get_media_derivative_run' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_get_media_derivative_run_result' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_public_media_derivative_cloud_projection' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_build_media_derivative_optimization_payload' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_receive_media_derivative_artifact' )
	&& false === strpos( $bootstrap, 'npcink_cloud_addon_download_media_derivative_artifact' ),
	'Bootstrap exposes verified runtime and exact media delivery helpers without the legacy preview download seam.'
);

maca_assert(
	false !== strpos( $runtime_client, 'function execute_wordpress_ai_connector_runtime' )
	&& false !== strpos( $runtime_client, 'normalize_wordpress_ai_connector_request' )
	&& false !== strpos( $runtime_client, "CLOUD_CONNECTOR_RUNTIME_CONTRACT = 'cloud_connector_runtime.v1'" )
	&& false !== strpos( $runtime_client, "WORDPRESS_OPERATION_CONTRACT = 'wordpress_operation.v1'" )
	&& false !== strpos( $runtime_client, "'ability_name'        => 'npcink-cloud/connector-runtime'" )
	&& false !== strpos( $runtime_client, "'channel'             => 'editor'" )
	&& false !== strpos( $runtime_client, "'execution_kind'      => \$is_alt_text ? 'vision' : 'text'" )
	&& false !== strpos( $runtime_client, "'site_id'             => \$site_id" )
	&& false !== strpos( $runtime_client, "'site_url'           => \$site_url" )
	&& false !== strpos( $runtime_client, "'platform_kind'      => 'wordpress'" )
	&& false !== strpos( $runtime_client, "'connector_id'       => 'npcink-cloud-addon'" )
	&& false !== strpos( $runtime_client, "'connector_version'  => \$connector_version" )
	&& false !== strpos( $runtime_client, "'suggestion_only'    => true" )
	&& false !== strpos( $runtime_client, "array( 'prompt', 'post_title', 'post_excerpt' )" )
	&& false !== strpos( $runtime_client, "\$scene_request['source_text']" )
	&& false !== strpos( $runtime_client, 'WP_AI_CONNECTOR_FORBIDDEN_KEYS' )
	&& false !== strpos( $runtime_client, "'credentials'" )
	&& false !== strpos( $runtime_client, "'api_key'" )
	&& false !== strpos( $runtime_client, "'messages'" )
	&& false !== strpos( $runtime_client, "'conversation_id'" )
	&& false !== strpos( $runtime_client, "'tool_calls'" )
	&& false !== strpos( $runtime_client, "'stream'" )
	&& false === strpos( $runtime_client, 'wp_ai_connector_' . 'runtime.v1' )
	&& false === strpos( $runtime_client, 'npcink-cloud/wp-ai-' . 'connector' ),
	'Runtime client exposes the bounded cross-platform connector envelope and rejects legacy WordPress text shapes.'
);

maca_assert(
	false !== strpos( $runtime_client, 'function upload_wordpress_ai_alt_text_source' )
	&& false !== strpos( $runtime_client, "'/v1/runtime/media/uploads'" )
	&& false !== strpos( $runtime_client, 'WP_AI_ALT_TEXT_MAX_UPLOAD_BYTES = 8388608' )
	&& false !== strpos( $runtime_client, 'WP_AI_ALT_TEXT_MIN_ARTIFACT_TTL_SECONDS = 120' )
	&& false !== strpos( $runtime_client, "Npcink_Cloud_Addon_Settings::is_verified()" )
	&& false !== strpos( $runtime_client, "'request_contract_version' => 'media_upload_request.v1'" )
	&& false !== strpos( $runtime_client, "'media_kind'              => 'image'" )
	&& false !== strpos( $runtime_client, "'ttl_minutes'             => 30" )
	&& false !== strpos( $runtime_client, 'build_media_upload_multipart_body' )
	&& false !== strpos( $runtime_client, 'name="file"; filename="' )
	&& false !== strpos( $runtime_client, "'/^art_[0-9a-f]{32}$/'" )
	&& false !== strpos( $runtime_client, "'sha256:' . hash( 'sha256', \$contents )" )
	&& false !== strpos( $runtime_client, "'artifact_id'    => \$artifact['artifact_id']" )
	&& false !== strpos( $runtime_client, "array( 'source_artifact_id', 'prompt', 'filename', 'title', 'existing_alt', 'existing_caption', 'locale', 'max_tokens' )" )
	&& false !== strpos( $runtime_client, "'data_classification' => 'internal'" )
	&& false === strpos( $runtime_client, 'WP_AI_CONNECTOR_ALT_TEXT_MAX_REQUEST_BYTES' ),
	'Runtime client keeps WordPress AI editor input internal and alt-text upload and execution on the dedicated Artifact-id-only contract.'
);

maca_assert(
	false !== strpos( $bootstrap, 'npcink_cloud_addon_project_ai_task_contract' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_execute_registered_ai_task_runtime' )
	&& false !== strpos( $bootstrap, 'is_site_knowledge_generation_reference_enabled' )
	&& false !== strpos( $ai_task_contract, "VERSION = 'ai_task_contract.v1'" )
	&& false !== strpos( $ai_task_contract, 'project_registered_ability' )
	&& false !== strpos( $ai_task_contract, "'write_posture'        => 'suggestion_only'" )
	&& false === strpos( $ai_task_contract, 'array_is_list' )
	&& false === strpos( $ai_task_contract, 'register_rest_route' )
	&& false === strpos( $ai_task_contract, 'update_option' ),
	'Registered AI task projection reuses Ability truth and the generation-reference opt-in without adding a registry, REST route, or local persistence.'
);

maca_assert(
	false !== strpos( $bootstrap, 'class-cloud-wordpress-ai-connector.php' )
	&& false !== strpos( $bootstrap, 'Npcink_Cloud_WordPress_AI_Connector::register()' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_execute_wordpress_ai_image_generation_runtime' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_execute_toolbox_image_generation_runtime' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_execute_toolbox_audio_generation_runtime' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_execute_toolbox_site_ops_cloud_analysis_runtime' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_execute_toolbox_web_search_runtime' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_execute_toolbox_image_source_runtime' )
	&& false !== strpos( $wordpress_ai_connector, "CONNECTOR_ID = 'npcink-cloud'" )
	&& false !== strpos( $wordpress_ai_connector, "CONNECTOR_NAME = 'Npcink Cloud'" )
	&& false !== strpos( $wordpress_ai_connector, "IMAGE_MODEL_ID = 'npcink-cloud-scene-image'" )
	&& false !== strpos( $wordpress_ai_connector, "type'           => 'ai_provider'" )
	&& false !== strpos( $wordpress_ai_connector, "method'       => 'api_key'" )
	&& false !== strpos( $wordpress_ai_connector, "SETTING_NAME = 'npcink_cloud_addon_wp_ai_connector_connected'" )
	&& false !== strpos( $wordpress_ai_connector, "show_in_rest' => false" )
	&& false !== strpos( $wordpress_ai_connector, 'enqueue_connectors_page_assets' )
	&& false !== strpos( $wordpress_ai_connector, "if ( 'options-connectors' !== \$hook_suffix )" )
	&& false !== strpos( $wordpress_ai_connector, 'wp_enqueue_style' )
	&& false !== strpos( $admin_css, 'connector-item--npcink-cloud-addon button.components-button' )
	&& false !== strpos( $wordpress_ai_connector, 'Npcink_Cloud_Addon_Settings::is_wordpress_ai_connector_enabled()' )
	&& false === strpos( $wordpress_ai_connector, "get_option( 'secret'" ),
	'WordPress connector registration projects verified opt-in Cloud settings into one fixed status-only Npcink Cloud card without exposing stored secrets.'
);

maca_assert(
	false !== strpos( $runtime_client, 'execute_toolbox_audio_generation_runtime' )
	&& false !== strpos( $runtime_client, 'TOOLBOX_AUDIO_GENERATION_ALLOWED_INTENTS' )
	&& false !== strpos( $runtime_client, "'article_narration'" )
	&& false !== strpos( $runtime_client, "'article_audio_summary'" )
	&& false !== strpos( $runtime_client, "'channel'             => 'toolbox_audio_generation'" )
	&& false !== strpos( $runtime_client, "'ability_name'        => 'npcink-toolbox/generate-audio'" )
	&& false !== strpos( $runtime_client, "'storage_mode'        => 'result_only'" )
	&& false !== strpos( $runtime_client, "'direct_wordpress_write' => false" )
	&& false !== strpos( $runtime_client, "'allow_fallback' => false" ),
	'Runtime client exposes a bounded Toolbox audio generation transport without media import, metadata writes, or fallback provider control.'
);

maca_assert(
	false !== strpos( $runtime_client, 'execute_toolbox_site_ops_cloud_analysis_runtime' )
	&& false !== strpos( $runtime_client, 'TOOLBOX_SITE_OPS_CLOUD_ANALYSIS_CONTRACT' )
	&& false !== strpos( $runtime_client, "'channel'             => 'toolbox_site_ops_cloud_analysis'" )
	&& false !== strpos( $runtime_client, "'ability_name'        => 'npcink-toolbox/analyze-site-ops'" )
	&& false !== strpos( $runtime_client, "'execution_pattern'   => 'whole_run_offload'" )
	&& false !== strpos( $runtime_client, "'storage_mode'        => 'result_only'" )
	&& false !== strpos( $runtime_client, "\$request['direct_wordpress_write'] ?? true" )
	&& false !== strpos( $runtime_client, "\$request['core_proposal_created'] ?? true" )
	&& false !== strpos( $runtime_client, "'allow_fallback' => false" ),
	'Runtime client exposes a bounded Toolbox Site Ops Cloud analysis transport without proposal, scheduler, or WordPress write ownership.'
);

maca_assert(
	false !== strpos( $runtime_client, 'execute_toolbox_web_search_runtime' )
	&& false !== strpos( $runtime_client, "TOOLBOX_WEB_SEARCH_CONTRACT = 'web_search.v1'" )
	&& false !== strpos( $runtime_client, "'channel'             => 'toolbox_web_search'" )
	&& false !== strpos( $runtime_client, "'ability_name'        => 'npcink-cloud/web-search'" )
	&& false !== strpos( $runtime_client, "'execution_kind'      => 'web_search'" )
	&& false !== strpos( $runtime_client, "'source_extraction_preview'" )
	&& false !== strpos( $runtime_client, "'write_posture']          = 'suggestion_only'" )
	&& false !== strpos( $runtime_client, "'direct_wordpress_write'] = false" )
	&& false !== strpos( $runtime_client, "'allow_fallback' => true" ),
	'Runtime client exposes a bounded Toolbox web search transport without local search keys, proposal ownership, or WordPress writes.'
);

maca_assert(
	false !== strpos( $runtime_client, 'execute_toolbox_image_source_runtime' )
	&& false !== strpos( $runtime_client, "TOOLBOX_IMAGE_SOURCE_CONTRACT = 'image_source_cloud_request.v1'" )
	&& false !== strpos( $runtime_client, "'channel'             => 'toolbox_image_source'" )
	&& false !== strpos( $runtime_client, "'ability_name'        => 'npcink-toolbox/search-image-source'" )
	&& false !== strpos( $runtime_client, "'execution_kind'      => 'image_source'" )
	&& false !== strpos( $runtime_client, "'candidate_contract']     = 'image_candidate.v1'" )
	&& false !== strpos( $runtime_client, "'direct_wordpress_write'] = false" )
	&& false !== strpos( $runtime_client, "'allow_fallback' => true" ),
	'Runtime client exposes a bounded Toolbox image-source transport without media import, featured-image writes, or attribution writes.'
);

maca_assert(
	false !== strpos( $bootstrap, 'class-ai-plugin-localization.php' )
	&& false !== strpos( $bootstrap, 'Npcink_Cloud_AI_Plugin_Localization::register()' )
	&& false !== strpos( $ai_plugin_localization, "private const AI_TEXT_DOMAIN = 'ai'" )
	&& false !== strpos( $ai_plugin_localization, "add_filter( 'gettext'" )
	&& false !== strpos( $ai_plugin_localization, "add_action( 'admin_enqueue_scripts'" )
	&& false !== strpos( $ai_plugin_localization, 'wp_localize_script' )
	&& false !== strpos( $ai_plugin_localization_js, 'wp.i18n.setLocaleData' )
	&& false !== strpos( $ai_plugin_localization, "'Generate Image' => '生成图片'" )
	&& false !== strpos( $ai_plugin_localization, "'Generate featured image' => '生成特色图片'" )
	&& false !== strpos( $ai_plugin_localization, "'Brush size' => '画笔大小'" )
	&& false !== strpos( $ai_plugin_localization, "'Replace Item' => '替换项目'" )
	&& false !== strpos( $ai_plugin_localization, "'Connector Approval' => '连接器审批'" )
	&& false !== strpos( $ai_plugin_localization, "'Configure an AI provider' => '配置 AI 提供方'" )
	&& false !== strpos( $ai_plugin_localization, "'Analyze Sentiment and Toxicity' => '分析情绪和毒性'" )
	&& false !== strpos( $ai_plugin_localization, "'Sentiment' => '情绪'" )
	&& false !== strpos( $ai_plugin_localization, "'Generate excerpt' => '生成文章摘要'" )
	&& false !== strpos( $ai_plugin_localization, "'Generate Summary' => '生成摘要区块'" )
	&& false !== strpos( $ai_plugin_localization, "'Editorial Notes will be available when the post content has at least %d characters.' => '文章内容至少达到 %d 个字符后可生成编辑建议。'" )
	&& false !== strpos( $ai_plugin_localization, "'Meta Description generation will be available when the post content has at least %d characters.' => '文章内容至少达到 %d 个字符后可生成 SEO 描述。'" )
	&& false !== strpos( $ai_plugin_localization, "'Content Classification will be available when the post content has at least %d characters.' => '文章内容至少达到 %d 个字符后可使用内容分类建议。'" )
	&& false !== strpos( $ai_plugin_localization, "'Last 24 Hours' => '最近 24 小时'" )
	&& false !== strpos( $ai_plugin_localization, "'Request Details' => '请求详情'" )
	&& false !== strpos( $ai_plugin_localization, "'AI Status' => 'AI 状态'" )
	&& false !== strpos( $ai_plugin_localization, "'Provider / Model' => '提供方 / 模型'" )
	&& false !== strpos( $ai_plugin_localization, "'No AI connectors are currently registered. Configure a connector first.' => '当前没有已注册的 AI 连接器。请先配置连接器。'" )
	&& false !== strpos( $ai_plugin_localization, "'The \"%1\$s\" AI connector has not been approved for use by \"%2\$s\".' => '“%1\$s” AI 连接器尚未获准供“%2\$s”使用。'" )
	&& false !== strpos( $ai_plugin_localization, "'Reset to default' => '重置为默认值'" )
	&& false !== strpos( $ai_plugin_localization, "'Taxonomy strategy' => '分类策略'" )
	&& false !== strpos( $ai_plugin_localization, "'Alt Text' => '替代文本'" )
	&& false !== strpos( $ai_plugin_localization, "'Base64 Image Import' => 'Base64 图片导入'" )
	&& false !== strpos( $ai_plugin_localization, "'Token Range' => 'Token 范围'" )
	&& false !== strpos( $ai_plugin_localization, "'Input Preview' => '输入预览'" )
	&& false !== strpos( $ai_plugin_localization, "'Log ID copied to clipboard.' => '日志 ID 已复制到剪贴板。'" )
	&& false !== strpos( $ai_plugin_localization, "'Generated image output' => '生成的图片输出'" )
	&& false !== strpos( $ai_plugin_localization, "'Pending requests' => '待处理请求'" )
	&& false !== strpos( $ai_plugin_localization, "'Review requests' => '查看请求'" )
	&& false !== strpos( $ai_plugin_localization, "'Total Abilities' => '能力总数'" )
	&& false !== strpos( $ai_plugin_localization, "'Invoke Ability' => '调用能力'" )
	&& false !== strpos( $ai_plugin_localization, "'Raw Data' => '原始数据'" )
	&& false === strpos( $ai_plugin_localization, 'npcink_cloud_addon_runtime_client' ),
	'AI plugin localization is a bounded admin-only ai-domain compatibility shim and does not call Cloud runtime.'
);

maca_assert(
	false !== strpos( $wordpress_ai_connector, 'class Npcink_Cloud_WordPress_AI_Provider' )
	&& false !== strpos( $wordpress_ai_connector, 'class Npcink_Cloud_WordPress_AI_Text_Model' )
	&& false !== strpos( $wordpress_ai_connector, 'class Npcink_Cloud_WordPress_AI_Vision_Text_Model' )
	&& false !== strpos( $wordpress_ai_connector, 'class Npcink_Cloud_WordPress_AI_Image_Model' )
	&& false !== strpos( $wordpress_ai_connector, 'ImageGenerationModelInterface' )
	&& false !== strpos( $wordpress_ai_connector, "VISION_MODEL_ID = 'npcink-cloud-scene-vision'" )
	&& false !== strpos( $wordpress_ai_connector, 'CapabilityEnum::imageGeneration()' )
	&& false !== strpos( $wordpress_ai_connector, 'wpai_preferred_image_models' )
	&& false !== strpos( $wordpress_ai_connector, 'wpai_preferred_vision_models' )
	&& false !== strpos( $wordpress_ai_connector, 'class Npcink_Cloud_WordPress_AI_Alt_Text_Handoff' )
	&& false !== strpos( $wordpress_ai_connector, 'upload_wordpress_ai_alt_text_source' )
	&& false !== strpos( $wordpress_ai_connector, "add_action( 'wp_before_execute_ability'" )
	&& false !== strpos( $wordpress_ai_connector, "'ai/alt-text-generation' === \$ability_name" )
	&& false !== strpos( $wordpress_ai_connector, 'consume_alt_text_ability_context' )
	&& false !== strpos( $wordpress_ai_connector, 'fstat( $handle )' )
	&& false !== strpos( $wordpress_ai_connector, 'getimagesizefromstring( $contents )' )
	&& false === strpos( $wordpress_ai_connector, 'WordPress\\AI\\Abilities\\Image\\Alt_Text_Generation' )
	&& false !== strpos( $wordpress_ai_connector, "'source_artifact_id' => \$artifact_id" )
	&& false !== strpos( $wordpress_ai_connector, 'requires a local WordPress attachment' )
	&& false !== strpos( $wordpress_ai_connector, "'task'             => 'alt_text_suggest'" )
	&& false !== strpos( $wordpress_ai_connector, 'npcink_cloud_addon_execute_wordpress_ai_image_generation_runtime' )
	&& false !== strpos( $wordpress_ai_connector, 'does not support reference image refinement yet' )
	&& false !== strpos( $wordpress_ai_connector, 'detect_scene_ability_name' )
	&& false !== strpos( $wordpress_ai_connector, 'WordPress\\\\AI\\\\Abilities\\\\Title_Generation\\\\Title_Generation' )
	&& false !== strpos( $wordpress_ai_connector, 'Npcink Cloud AI connector only accepts known WordPress AI ability scene calls' )
	&& false !== strpos( $wordpress_ai_connector, 'does not support chat history' )
	&& false !== strpos( $wordpress_ai_connector, 'does not support tools or web search' )
	&& false !== strpos( $wordpress_ai_connector, "method_exists( \$client, 'execute_wordpress_ai_connector_runtime' )" )
	&& false !== strpos( $wordpress_ai_connector, '$client->execute_wordpress_ai_connector_runtime(' )
	&& false !== strpos( $wordpress_ai_connector, "\$scene_input['source_text'] = \$text" )
	&& false !== strpos( $wordpress_ai_connector, "'cloud_connector_result.v1'" )
	&& false !== strpos( $wordpress_ai_connector, "\$response['data']['result']" )
	&& false !== strpos( $wordpress_ai_connector, "true !== ( \$result['suggestion_only'] ?? null )" )
	&& false !== strpos( $wordpress_ai_connector, "'npcink-cloud-addon' !== (string) ( \$result['connector_id'] ?? '' )" )
	&& false !== strpos( $wordpress_ai_connector, "'wordpress_operation.v1' !== (string) ( \$operation_contract['contract_version'] ?? '' )" )
	&& false !== strpos( $wordpress_ai_connector, "\$expected_task !== (string) ( \$operation_contract['task'] ?? '' )" )
	&& false !== strpos( $wordpress_ai_connector, "\$output['output_text']" )
	&& false !== strpos( $wordpress_ai_connector, "'response_format'    => \$this->response_format_hint( \$task )" )
	&& false !== strpos( $wordpress_ai_connector, 'function response_format_hint' )
	&& false === strpos( $wordpress_ai_connector, "'output_schema'      =>" )
	&& false === strpos( $wordpress_ai_connector, 'wp_ai_connector_' . 'result.v1' )
	&& false === strpos( $wordpress_ai_connector, 'chat/completions' )
	&& false === strpos( $wordpress_ai_connector, 'OpenAiCompatible' ),
	'AI Client provider is scene-gated to known WordPress AI abilities and does not expose an OpenAI-compatible chat proxy or deep schema payload.'
);

maca_assert(
	false !== strpos( $composer, '"smoke:wp-ai-abilities"' )
	&& is_readable( $root . '/scripts/smoke-wordpress-ai-abilities.php' )
	&& false !== strpos( $wp_ai_smoke, "'ai/summarization'" )
	&& false !== strpos( $wp_ai_smoke, "'ai/meta-description'" )
	&& false !== strpos( $wp_ai_smoke, "'ai/alt-text-generation'" )
	&& false !== strpos( $wp_ai_smoke, 'WP_AI_SMOKE_IMAGE' )
	&& false !== strpos( $wp_ai_smoke, 'WP_AI_SMOKE_ALT_TEXT_ATTACHMENT_ID' )
	&& false !== strpos( $wp_ai_smoke, "'attachment_id' => \$alt_text_attachment_id" )
	&& false === strpos( $wp_ai_smoke, 'WP_AI_SMOKE_ALT_TEXT_URL' )
	&& false === strpos( $wp_ai_smoke, '/wp-abilities/v1/abilities/ai/image-import/run' ),
	'WordPress AI smoke gate verifies discovery and bounded runs without default media writes.'
);

maca_assert(
	false !== strpos( $wordpress_ai_connector, 'maybe_log_wordpress_ai_request_evidence' )
	&& false !== strpos( $wordpress_ai_connector, 'AI_Request_Log_Manager' )
	&& false !== strpos( $wordpress_ai_connector, "'cloud_run_id'               => \$run_id" )
	&& false !== strpos( $wordpress_ai_connector, "'suggestion_only'            => true" )
	&& false !== strpos( $wordpress_ai_connector, "'direct_wordpress_write'     => false" )
	&& false !== strpos( $wordpress_ai_connector, 'omitted_metadata_only' )
	&& false !== strpos( $wordpress_ai_connector, "'operation'                  => 'npcink-cloud/connector-runtime'" )
	&& false !== strpos( $wordpress_ai_connector, "'operation_contract_version' => 'wordpress_operation.v1'" )
	&& false !== strpos( $wordpress_ai_connector, "'channel'                    => 'editor'" )
	&& false !== strpos( $wordpress_ai_connector, "'connector_id'               => 'npcink-cloud-addon'" )
	&& false === strpos( $wordpress_ai_connector, "'input_preview'" )
	&& false === strpos( $wordpress_ai_connector, "'output_preview'" ),
	'WordPress AI request log bridge is metadata-only and does not persist prompt or output previews.'
);

maca_assert(
	false !== strpos( $readme, 'WordPress AI Connector Runtime' )
	&& false !== strpos( $readme, 'OpenAI-compatible provider' )
	&& false !== strpos( $readme, '`cloud_connector_runtime.v1` envelope' )
	&& false !== strpos( $readme, '`wordpress_operation.v1` contract' )
	&& false !== strpos( $readme, '`response.data.result.output.output_text`' )
	&& false !== strpos( $readme, 'npcink_cloud_addon_execute_toolbox_image_generation_runtime()' )
	&& false !== strpos( $readme, 'npcink_cloud_addon_execute_toolbox_site_ops_cloud_analysis_runtime()' )
	&& false !== strpos( $readme, 'npcink_cloud_addon_execute_toolbox_web_search_runtime()' )
	&& false !== strpos( $readme, 'npcink_cloud_addon_execute_toolbox_image_source_runtime()' )
	&& false !== strpos( $readme, 'Toolbox to normalize into `image_candidate.v1`' )
	&& false !== strpos( $readme, 'scene-gated text,' )
	&& false !== strpos( $readme, '`npcink-cloud-scene-vision`' )
	&& false !== strpos( $readme, 'reference-image refinement' )
	&& false !== strpos( $runtime_contract, 'WordPress AI Connector Runtime' )
	&& false !== strpos( $runtime_contract, 'generic chat provider' )
	&& false !== strpos( $runtime_contract, '`contract_version=cloud_connector_runtime.v1`' )
	&& false !== strpos( $runtime_contract, '`input.operation_contract.contract_version=wordpress_operation.v1`' )
	&& false !== strpos( $runtime_contract, '`response.data.result.contract_version=cloud_connector_result.v1`' )
	&& false !== strpos( $runtime_contract, 'image_generation_request.v1' )
	&& false !== strpos( $runtime_contract, 'execute_toolbox_image_generation_runtime()' )
	&& false !== strpos( $runtime_contract, 'execute_toolbox_site_ops_cloud_analysis_runtime()' )
	&& false !== strpos( $runtime_contract, 'execute_toolbox_web_search_runtime()' )
	&& false !== strpos( $runtime_contract, 'execute_toolbox_image_source_runtime()' )
	&& false !== strpos( $runtime_contract, 'channel=toolbox_image_generation' )
	&& false !== strpos( $runtime_contract, 'channel=toolbox_site_ops_cloud_analysis' )
	&& false !== strpos( $runtime_contract, 'channel=toolbox_web_search' )
	&& false !== strpos( $runtime_contract, 'channel=toolbox_image_source' )
	&& false !== strpos( $runtime_contract, 'scene wrapper' )
	&& false !== strpos( $runtime_contract, 'registers a bounded `wpai_preferred_vision_models` override' )
	&& false !== strpos( $runtime_contract, '`wp_before_execute_ability`' )
	&& false !== strpos( $runtime_contract, 'future public' )
	&& false !== strpos( $runtime_contract, 'does not support reference-image refinement' )
	&& false !== strpos( $runtime_contract, 'Direct free-form `wp_ai_client_prompt()`' )
	&& false !== strpos( $adapter_doc, 'WordPress AI Connector Flow' )
	&& false !== strpos( $adapter_doc, '`cloud_connector_runtime.v1`' )
	&& false !== strpos( $adapter_doc, '`wordpress_operation.v1`' )
	&& false !== strpos( $adapter_doc, '`cloud_connector_result.v1`' )
	&& false !== strpos( $adapter_doc, 'must not expose an OpenAI-compatible endpoint' )
	&& false !== strpos( $adapter_doc, 'image provider proxy' )
	&& false !== strpos( $adapter_doc, 'human chat' ),
	'Docs describe the WordPress AI connector seam as scene-bound runtime only.'
);

maca_assert(
	false !== strpos( $transport, 'Npcink_Cloud_Addon_Settings::is_verified()' )
	&& false !== strpos( $transport, "'cloud_runtime_unverified'" ),
	'Media derivative dispatch fails closed until Cloud credentials verify.'
);

maca_assert(
	false !== strpos( $runtime_client, 'function upload_media_artifact' )
	&& false !== strpos( $runtime_client, 'function create_media_job' )
	&& false !== strpos( $runtime_client, "'/v1/runtime/media/uploads'" )
	&& false !== strpos( $runtime_client, "'/v1/runtime/media/jobs'" )
	&& false !== strpos( $runtime_client, 'media_upload_request.v1' )
	&& false !== strpos( $runtime_client, 'media_job_request.v1' )
	&& false !== strpos( $runtime_client, "'checksum', 'expires_at', 'purged_at'" )
	&& false !== strpos( $runtime_client, "null === ( \$artifact['purged_at'] ?? null )" )
	&& false === strpos( $runtime_client, '/v1/runtime/media-derivatives' ),
	'Runtime client hard-cuts media work to exact11 available uploads followed by artifact-referenced jobs.'
);

maca_assert(
	false !== strpos( $runtime_client, 'function request_image_context_evidence' )
	&& false !== strpos( $runtime_client, 'normalize_image_context_evidence_request' )
	&& false !== strpos( $runtime_client, 'normalize_image_context_evidence_response' )
	&& false !== strpos( $runtime_client, "'ability_name'        => 'npcink-cloud/image-context-evidence'" )
	&& false !== strpos( $runtime_client, "'profile_id'          => 'vision.ai'" )
	&& false !== strpos( $runtime_client, "'execution_kind'      => 'image_context_evidence'" )
	&& false !== strpos( $runtime_client, "'expected_response_contract' => 'image_context_evidence.v1'" )
	&& false !== strpos( $runtime_client, "'no_local_model'             => true" )
	&& false !== strpos( $runtime_client, "'no_media_write'             => true" ),
	'Runtime client exposes bounded image context evidence transport through the hosted runtime contract.'
);

maca_assert(
	false !== strpos( $readme, 'Image Context Evidence Transport' )
	&& false !== strpos( $readme, 'image_context_evidence_request.v1' )
	&& false !== strpos( $readme, 'image_context_evidence.v1' )
	&& false !== strpos( $readme, 'does not run a local vision model' )
	&& false !== strpos( $runtime_contract, 'request_image_context_evidence(array $image_context_evidence_request' )
	&& false !== strpos( $runtime_contract, 'bounded_media_urls_for_visual_context_only' )
	&& false !== strpos( $runtime_contract, 'not a local image recognition model' )
	&& false !== strpos( $boundary_doc, 'Image context evidence transport must use the existing' )
	&& false !== strpos( $boundary_doc, 'not add a local image recognition model' ),
	'Docs describe image context evidence as bounded runtime transport, not local vision or write ownership.'
);

maca_assert(
	false !== strpos( $runtime_client, 'function pull_media_artifact' )
	&& false !== strpos( $runtime_client, 'function acknowledge_media_artifact_delivery' )
	&& false !== strpos( $runtime_client, "'/v1/runtime/media/artifacts/'" )
	&& false !== strpos( $runtime_client, "'/delivery-ack'" )
	&& false !== strpos( $runtime_client, 'request_raw' )
	&& false !== strpos( $runtime_client, 'MAX_DOWNLOAD_BYTES = 26214400' )
	&& false !== strpos( $transport, 'receive_artifact' )
	&& false !== strpos( $transport, 'media_artifact_verified_transfer.v1' )
	&& false !== strpos( $transport, 'cloud_media_derivative_artifact_mime_mismatch' )
	&& false !== strpos( $transport, 'Derivative artifact checksum does not match the downloaded bytes.' )
	&& false === strpos( $runtime_client, '/v1/runtime/artifacts/' ),
	'Runtime client and transport expose canonical signed pull, independent verification, and transfer-only ACK.'
);

maca_assert(
	false !== strpos( $transport, 'upload_media_artifact' )
	&& false !== strpos( $transport, 'create_media_job' )
	&& false !== strpos( $transport, 'get_run_projection' )
	&& false !== strpos( $transport, 'get_run_result_projection' )
	&& false !== strpos( $transport, 'public_cloud_projection' )
	&& false !== strpos( $transport, 'build_media_optimization_payload' )
	&& false !== strpos( $transport, 'build_media_job_params' )
	&& false !== strpos( $transport, 'build_media_job_request' )
	&& false !== strpos( $transport, 'normalize_upload_file_descriptor' )
	&& false !== strpos( $transport, 'normalize_required_artifact_reference' ),
	'Media derivative transport shapes strict Cloud requests, projections, and Core-ready optimization payloads from ability contracts and host artifacts.'
);

maca_assert(
	false !== strpos( $transport, 'cloud_media_derivative_watermark_plan_missing' )
	&& false !== strpos( $transport, 'cloud_media_derivative_watermark_source_conflict' )
	&& false !== strpos( $transport, 'cloud_media_derivative_watermark_source_missing' )
	&& false !== strpos( $transport, 'cloud_media_derivative_source_mode_conflict' )
	&& false !== strpos( $transport, 'Watermark artifact transport requires a watermark plan' )
	&& false !== strpos( $transport, 'Watermark plans require a watermark upload or artifact id' )
	&& false !== strpos( $transport, 'Text watermark plans must not include a watermark upload or artifact id' )
	&& false !== strpos( $transport, "'type'       => 'text'" )
	&& false !== strpos( $transport, 'sanitize_crop_payload' )
	&& false !== strpos( $transport, "'crop'" )
	&& false !== strpos( $transport, 'sanitize_watermark_color' )
	&& false !== strpos( $transport, 'array $watermark_artifact = array()' )
	&& false !== strpos( $transport, 'sanitize_watermark_payload' )
	&& false !== strpos( $transport, "'watermark_file'" ),
	'Watermark transport requires a local ability plan and rejects missing or mixed source modes.'
);

maca_assert(
	false !== strpos( $transport, 'contains_forbidden_secret_fields' )
	&& false !== strpos( $transport, "'credentials'" )
	&& false !== strpos( $transport, "'authorization'" )
	&& false !== strpos( $transport, "'signed_headers'" )
	&& false !== strpos( $transport, "'x_magick_signature'" ),
	'Media derivative ability payload is checked for credentials, Authorization, and signed headers.'
);

maca_assert(
	false !== strpos( $transport, 'cloud_media_derivative_artifact_expired' )
	&& false !== strpos( $transport, 'cloud_media_derivative_artifact_id_invalid' )
	&& false !== strpos( $transport, 'cloud_media_derivative_artifact_binding_mismatch' )
	&& false !== strpos( $transport, 'cloud_media_derivative_artifact_checksum_mismatch' )
	&& false !== strpos( $transport, 'Expired Cloud artifacts cannot be adopted.' )
	&& false !== strpos( $transport, 'return $timestamp <= time();' ),
	'Expired, unbound, or mismatched Cloud artifacts are rejected before local adoption payloads are built.'
);

maca_assert(
	false !== strpos( $transport, 'cloud_media_derivative_replace_original_requested' )
	&& false !== strpos( $transport, "'replace_original_default'      => false" )
	&& false !== strpos( $transport, "'default_action'    => 'preview_only'" ),
	'Media derivative proposal payload defaults to preview-only and does not replace the original file.'
);

maca_assert(
	false !== strpos( $transport, "'final_write_owner' => 'local_wordpress_host'" )
	&& false !== strpos( $transport, "'wordpress_write_included'      => false" )
	&& false !== strpos( $transport, "'attachment_metadata_write_included' => false" ),
	'Media derivative proposal payload declares local WordPress host as final write owner.'
);

maca_assert(
	false === strpos( $transport, 'wp_insert_' . 'post' )
	&& false === strpos( $transport, 'wp_update_' . 'post' )
	&& false === strpos( $transport, 'update_attached_file' )
	&& false === strpos( $transport, 'wp_update_attachment_metadata' )
	&& false === strpos( $transport, 'update_post_meta' ),
	'Media derivative transport does not perform WordPress writes or attachment metadata updates.'
);

maca_assert(
	false !== strpos( $runtime_client, "'POST', '/v1/runtime/execute'" )
	&& false !== strpos( $runtime_client, "'/v1/runtime/media/uploads'" )
	&& false !== strpos( $runtime_client, "'/v1/runtime/media/jobs'" )
	&& false !== strpos( $runtime_client, "'/v1/runtime/media/artifacts/'" )
	&& false !== strpos( $runtime_client, "'GET', '/v1/runs/'" )
	&& false !== strpos( $runtime_client, 'get_recent_nightly_inspection_runs' )
	&& false !== strpos( $runtime_client, "'GET', '/v1/runs/nightly-inspection/recent?limit='" )
	&& false !== strpos( $runtime_client, 'public function retry_run' )
	&& false !== strpos( $runtime_client, "rawurlencode( \$run_id ) . '/retry'" )
	&& false !== strpos( $runtime_client, 'private function request' )
	&& 2 === substr_count( $runtime_client, 'Npcink_Cloud_Runtime_Endpoint_Policy::allows' )
	&& false === strpos( $runtime_client, 'is_allowed_request_path' )
	&& false !== strpos( $runtime_client, "'cloud_runtime_endpoint_not_allowed'" )
	&& false === strpos( $runtime_endpoint_policy, 'cloud_runtime_endpoint_not_allowed' )
	&& ! $runtime_endpoint_policy_has_forbidden
	&& false !== strpos( $runtime_endpoint_policy, 'public static function allows' )
	&& false !== strpos( $runtime_endpoint_policy, 'private static function path_only' )
	&& false !== strpos( $runtime_endpoint_policy, "'/v1/runtime/media/uploads'" )
	&& false !== strpos( $runtime_endpoint_policy, '#^/v1/runs/[A-Za-z0-9._:-]+(?:/result)?$#' )
	&& strpos( $runtime_endpoint_policy, "'/v1/runs/nightly-inspection/recent'" )
		< strpos( $runtime_endpoint_policy, '#^/v1/runs/[A-Za-z0-9._:-]+(?:/result)?$#' )
	&& false !== strpos( $runtime_endpoint_policy, '#^/v1/runs/[A-Za-z0-9._:-]+/retry$#' )
	&& false !== strpos( $runtime_endpoint_policy, '#^/v1/runtime/media/artifacts/art_[0-9a-f]{32}/download$#' )
	&& false !== strpos( $runtime_endpoint_policy, '#^/v1/runtime/media/artifacts/art_[0-9a-f]{32}/delivery-ack$#' )
	&& false === strpos( $runtime_endpoint_policy, '/v1/stats/' )
	&& false === strpos( $runtime_client, 'function get_profile_stats' )
	&& false === strpos( $runtime_client, 'function get_instance_stats' )
	&& false !== strpos( $bootstrap, 'class-cloud-runtime-endpoint-policy.php' )
	&& false !== strpos( $bootstrap, 'class-cloud-runtime-client.php' )
	&& strpos( $bootstrap, 'class-cloud-runtime-endpoint-policy.php' )
		< strpos( $bootstrap, 'class-cloud-runtime-client.php' )
	&& false !== strpos( $runtime_client, 'MAX_JSON_RESPONSE_BYTES = 1048576' )
	&& false !== strpos( $runtime_client, 'limit_response_size' )
	&& false === strpos( $transport, '/v1/runtime/workflows/' . 'runs' )
	&& false === strpos( $transport, '/v1/artifacts' ),
	'Runtime client keeps Cloud calls on named allowlisted contract surfaces.'
);

maca_assert(
	false === strpos( $runtime_client, 'Npcink_Cloud_Runtime_Artifact_Url_Normalizer' )
	&& false === strpos( $bootstrap, 'class-cloud-runtime-artifact-url-normalizer.php' )
	&& false === strpos( $test_helpers, 'class-cloud-runtime-artifact-url-normalizer.php' )
	&& false === strpos( $test_runner, 'behavior-runtime-artifact-url-normalizer.php' )
	&& ! file_exists( $root . '/includes/class-cloud-runtime-artifact-url-normalizer.php' )
	&& ! file_exists( $root . '/tests/behavior-runtime-artifact-url-normalizer.php' )
	,
	'Legacy runtime artifact URL normalization and compatibility tests are removed.'
);

maca_assert(
	false !== strpos( $entitlement_summary, 'normalize_pro_cloud_runtime' )
	&& false !== strpos( $entitlement_summary, 'get_cached_summary' )
	&& false !== strpos( $entitlement_summary, "'pro_cloud_runtime'" )
	&& false !== strpos( $entitlement_summary, "'nightly_site_inspection_runs'" )
	&& false !== strpos( $entitlement_summary, "'local_billing_truth' => false" )
	&& false !== strpos( $entitlement_summary, "'contract_reuse'" )
	&& false !== strpos( $entitlement_summary, "'toolbox_role' => 'product_surface'" )
	&& false !== strpos( $entitlement_summary, "'core_role' => 'proposal_handoff'" )
	&& false !== strpos( $entitlement_summary, "'adapter_role' => 'execution_profiles'" )
	&& false !== strpos( $entitlement_summary, "'toolkit_role' => 'ability_contracts'" )
	&& false !== strpos( $entitlement_summary, "'adds_registry' => false" )
	&& false !== strpos( $entitlement_summary, "'adds_scheduler_truth' => false" )
	&& false !== strpos( $entitlement_summary, "'adds_approval_store' => false" )
	&& false !== strpos( $entitlement_summary, "'adds_queue' => false" )
	&& false !== strpos( $entitlement_summary, "'adds_write_executor' => false" )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Entitlement_Summary::get_cached_summary()' )
	&& false !== strpos( $settings_page, 'render_pro_cloud_runtime_summary' )
	&& false !== strpos( $settings_page, 'render_runtime_runs' )
	&& false === strpos( $settings_page, 'Batch limit' )
	&& false !== strpos( $settings_page, 'Retention' )
	&& false === strpos( $settings_page, 'Quota exhausted' )
	&& false === strpos( $settings_page, 'format_runtime_integer_projection' )
	&& false !== strpos( $settings_page, 'format_runtime_days_projection' )
	&& false === strpos( $settings_page, 'format_runtime_boolean_projection' )
	&& false === strpos( $settings_page, 'format_runtime_quota_projection' )
	&& false !== strpos( $entitlement_summary, "'reported' => \$reported" )
	&& false !== strpos( $entitlement_summary, 'normalize_optional_absint' )
	&& false !== strpos( $entitlement_summary, 'normalize_runtime_boolean' )
	&& false === strpos( $settings_page, 'Cloud-owned Nightly Inspection run status, result reads, and bounded retry requests. This troubleshooting section creates no local queue, scheduler, proposal, approval record, or WordPress write.' )
	&& false === strpos( $settings_page, 'Cloud owns run state, retry processing, retention, and usage detail.' )
	&& false === strpos( $settings_page, 'Contract reuse' )
	&& false !== strpos( $settings_page, 'get_recent_nightly_inspection_runs( 5' )
	&& false !== strpos( $settings_page, 'get_run_result( $run_id' )
	&& false !== strpos( $settings_page, 'Request Cloud retry' )
	&& false !== strpos( $settings_page, 'npcink-cloud-run-detail-actions' )
	&& false !== strpos( $admin_css, '.npcink-cloud-run-detail-actions' )
	&& false !== strpos( $settings_page, 'self::ACTION_RETRY_RUNTIME_RUN' )
	&& false !== strpos( $settings_page, 'Cloud did not return Runtime Runs entitlement for this site yet.' )
	&& false !== strpos( $settings_page, 'Run status, result reads, and retry controls appear after Cloud reports the runtime entitlement.' )
	&& false !== strpos( $settings_page, "local_queue_created'     => false" )
	&& false === strpos( $settings_page, 'This addon does not own billing truth, scheduling, queues, or WordPress writes.' ),
	'Entitlement summary and Troubleshooting runtime section preserve Pro Cloud Runtime detail as read-only/Cloud-owned projection without local billing, scheduler, queue, proposal, or write truth.'
);

maca_assert(
	false !== strpos( $settings_page, "add_action( 'wp_ajax_' . self::ACTION_REFRESH_ENTITLEMENT" )
	&& false !== strpos( $settings_page, 'check_ajax_referer( self::ACTION_REFRESH_ENTITLEMENT' )
	&& false !== strpos( $settings_page, 'current_user_can( self::MENU_CAPABILITY )' )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Entitlement_Summary::refresh' )
	&& false !== strpos( $settings_page, 'format_overview_entitlement' )
	&& false !== strpos( $settings_page, 'Loading plan and entitlement…' )
	&& false !== strpos( $settings_page, 'data-npcink-entitlement-retry' )
	&& false !== strpos( $entitlement_summary, 'REFRESH_LOCK_TTL_SECONDS' )
	&& false !== strpos( $entitlement_summary, 'add_option( $lock_key' )
	&& false !== strpos( $entitlement_summary, 'REFRESH_FAILURE_BACKOFF_SECONDS' )
	&& false !== strpos( $entitlement_summary, 'decorate_cached_summary' )
	&& false !== strpos( $admin_entitlement_js, "refresh( 'auto' )" )
	&& false !== strpos( $admin_entitlement_js, "refresh( 'retry' )" )
	&& false !== strpos( $admin_css, '.npcink-cloud-entitlement__retry[hidden]' ),
	'Entitlement summary auto-loads through one nonce- and capability-protected read action, retains stale data, and exposes only an inline failure retry.'
);

maca_assert(
	false !== strpos( $entitlement_summary, 'normalize_credit_usage_detail' )
	&& false !== strpos( $entitlement_summary, "'local_addon_policy' => sanitize_key" )
	&& false !== strpos( $entitlement_summary, "'summary_and_link_only'" )
	&& false !== strpos( $entitlement_summary, "'credit_ledger_url'" )
	&& false !== strpos( $settings_page, 'get_overview_entitlement_metrics' )
	&& false !== strpos( $settings_page, 'format_overview_package_label' )
	&& false !== strpos( $settings_page, "'Free plan'" )
	&& false !== strpos( $settings_page, 'Available credits' )
	&& false !== strpos( $settings_page, 'Runtime allowance' )
	&& false !== strpos( $settings_page, 'data-npcink-entitlement-progress' )
	&& false !== strpos( $admin_entitlement_js, 'updateMetrics' )
	&& false !== strpos( $admin_entitlement_js, "metricContainer.title = metric.available && metric.tooltip ? metric.tooltip : ''" )
	&& false !== strpos( $admin_css, '.npcink-cloud-entitlement-progress' )
	&& false !== strpos( $settings_page, 'role="progressbar"' )
	&& false !== strpos( $settings_page, 'npcink-cloud-metric-actions--empty' )
	&& false !== strpos( $settings_page, 'data-npcink-entitlement-metric-value' )
	&& false !== strpos( $settings_page, 'data-npcink-entitlement-metric-status' )
	&& false !== strpos( $admin_entitlement_js, "progress.style.setProperty( '--npcink-cloud-progress', percent + '%' )" )
	&& false !== strpos( $admin_css, 'grid-template-columns: minmax(0, 1fr) 112px minmax(140px, 180px) 64px' )
	&& false !== strpos( $admin_css, 'font-variant-numeric: tabular-nums' )
	&& false !== strpos( $admin_css, '.npcink-cloud-segmented-progress' )
	&& false !== strpos( $admin_css, 'repeating-linear-gradient' )
	&& false !== strpos( $admin_css, '.npcink-cloud-overview-status th' )
	&& false === strpos( $settings_page, "format_credit_amount( \$remaining, \$unit )" )
	&& false !== strpos( $settings_page, 'Used %1$s credits; remaining %2$s credits; limit %3$s credits.' )
	&& false !== strpos( $settings_page, 'npcink-cloud-section-heading' )
	&& false !== strpos( $settings_page, 'View credit details in Cloud' )
	&& false !== strpos( $settings_page, 'Entitlement details' )
	&& false !== strpos( $settings_page, 'Credit period' )
	&& false !== strpos( $settings_page, 'Active run limit' )
	&& false === strpos( $settings_page, 'render_credit_usage_summary' )
	&& false === strpos( $settings_page, '<h3><?php esc_html_e( \'AI Credit Usage\'' )
	&& false === strpos( $settings_page, '%1$s used / %2$s limit / %3$s remaining' )
	&& false === strpos( $settings_page, "esc_html_e( 'Used credits'" )
	&& false === strpos( $settings_page, "'recent_items'" ),
	'Cloud Addon puts common credit and runtime allowance metrics on Overview, moves low-frequency parameters to service detail, and avoids duplicate summaries.'
);

maca_assert(
	false !== strpos( $cloud_bulk_article_doc, 'Rejected Product Language' )
	&& false !== strpos( $cloud_bulk_article_doc, 'Cloud writing assistant' )
	&& false !== strpos( $cloud_bulk_article_doc, 'Cloud article generator' )
	&& false !== strpos( $cloud_bulk_article_doc, 'hosted article drafting connector' )
	&& false !== strpos( $cloud_bulk_article_doc, 'Cloud connector, service detail, entitlement, health, and diagnostics language' ),
	'Cloud bulk article seam rejects writing-product language and keeps addon copy on service detail.'
);

maca_assert(
	false === strpos( $transport, 'media_derivative_cloud_runtime.v1' )
	&& false === strpos( $transport, 'build_runtime_payload' )
	&& false !== strpos( $transport, 'cloud_media_derivative_target_format_missing' )
	&& false !== strpos( $transport, 'cloud_media_derivative_max_width_missing' )
	&& false !== strpos( $transport, 'cloud_media_derivative_quality_missing' )
	&& false !== strpos( $transport, 'cloud_media_derivative_source_media_type_invalid' )
	&& false !== strpos( $transport, 'cloud_media_derivative_artifact_mime_invalid' )
	&& false !== strpos( $transport, 'Original media metrics are incomplete.' )
	&& false !== strpos( $transport, 'Derivative media metrics are incomplete.' )
	&& false !== strpos( $transport, 'filesize( $real_path )' )
	&& false !== strpos( $transport, 'is_allowed_upload_file_path' )
	&& false !== strpos( $transport, 'wp_upload_dir' )
	&& false !== strpos( $transport, 'sys_get_temp_dir' )
	&& false !== strpos( $transport, 'MAX_UPLOAD_BYTES = 26214400' ),
	'Media derivative transport has no legacy execute payload builder, requires ability-provided derivative fields, validates media types, warns on incomplete metrics, and preflights upload size.'
);

maca_assert(
	false === strpos( $readme, 'request(string $method' )
	&& false === strpos( $runtime_contract, 'request(string $method' )
	&& false !== strpos( $runtime_contract, 'upload_media_artifact(array $file, string $trace_id = \'\', string $idempotency_key = \'\')' )
	&& false !== strpos( $runtime_contract, 'create_media_job(array $payload, string $trace_id = \'\', string $idempotency_key = \'\')' )
	&& false !== strpos( $runtime_contract, 'pull_media_artifact(string $artifact_id, string $trace_id = \'\')' )
	&& false !== strpos( $runtime_contract, 'acknowledge_media_artifact_delivery(string $artifact_id, array $payload, string $trace_id = \'\', string $idempotency_key = \'\')' )
	&& false !== strpos( $readme, 'low-level signed request method is private and endpoint-allowlisted' )
	&& false !== strpos( $runtime_contract, 'must enforce the endpoint allowlist' )
	&& false !== strpos( $runtime_contract, 'must not be exposed as a generic public Cloud proxy' ),
	'Raw Cloud request helper is no longer documented as a public generic endpoint proxy.'
);

maca_assert(
	false !== strpos( $settings, 'invalid_cloud_base_url' )
	&& false !== strpos( $settings, 'Npcink_Cloud_Outbound_Policy::normalize_base_url' )
	&& false !== strpos( $outbound_policy, "'https' === \$scheme" )
	&& false !== strpos( $outbound_policy, 'local_requests_allowed' )
	&& false !== strpos( $outbound_policy, 'host_resolves_publicly' )
	&& false !== strpos( $outbound_policy, "'redirection'" )
	&& false !== strpos( $outbound_policy, 'wp_safe_remote_request' )
	&& false !== strpos( $outbound_policy, 'MAX_AUTH_RESPONSE_BYTES = 65536' )
	&& false !== strpos( $runtime_client, 'Npcink_Cloud_Outbound_Policy::request_json' )
	&& false !== strpos( $runtime_client, 'Npcink_Cloud_Outbound_Policy::request_raw' )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Outbound_Policy::request_json' )
	&& false === strpos( $runtime_client, 'wp_remote_request(' )
	&& false === strpos( $runtime_client, 'wp_remote_get(' )
	&& false === strpos( $settings_page, 'wp_remote_post(' ),
	'Cloud Base URL normalization requires HTTPS except local development hosts.'
);

maca_assert(
	false !== strpos( $boundary_doc, 'POST /v1/runtime/media/jobs' )
	&& false !== strpos( $boundary_doc, 'GET /v1/runtime/media/artifacts/{artifact_id}/download' )
	&& false !== strpos( $boundary_doc, 'POST /v1/runtime/media/artifacts/{artifact_id}/delivery-ack' )
	&& false !== strpos( $boundary_doc, 'logo registry' )
	&& false !== strpos( $boundary_doc, 'watermark plan' )
	&& false !== strpos( $adapter_doc, 'Expired Cloud artifacts must not be adopted.' )
	&& false !== strpos( $adapter_doc, 'final_write_owner=local_wordpress_host' )
	&& false !== strpos( $adapter_doc, 'watermark_artifact' )
	&& false !== strpos( $agents, 'POST /v1/runtime/media/jobs' )
	&& false !== strpos( $agents, 'GET /v1/runtime/media/artifacts/{artifact_id}/download' )
	&& false !== strpos( $agents, 'POST /v1/runtime/media/artifacts/{artifact_id}/delivery-ack' )
	&& false !== strpos( $agents, 'POST /v1/agent-feedback/events' ),
	'Boundary, adapter integration, and AGENTS docs describe derivative transport ownership.'
);

maca_assert(
	false !== strpos( $runtime_client, 'function send_observability_events' )
	&& false !== strpos( $runtime_client, "'POST'" )
	&& false !== strpos( $runtime_client, "'/v1/observability/plugin-events'" )
	&& false !== strpos( $runtime_client, 'function send_agent_feedback_event' )
	&& false !== strpos( $runtime_client, "'/v1/agent-feedback/events'" )
	&& false !== strpos( $runtime_client, 'function get_agent_feedback_summary' )
	&& false !== strpos( $runtime_client, "'/v1/agent-feedback/summary?window_hours='" )
	&& false !== strpos( $runtime_client, 'function get_observability_summary' )
	&& false !== strpos( $runtime_client, "'GET'" )
	&& false !== strpos( $runtime_client, "'/v1/observability/plugin-summary?window_hours='" )
	&& false !== strpos( $runtime_contract, 'send_observability_events()' )
	&& false !== strpos( $runtime_contract, 'send_agent_feedback_event()' )
	&& false !== strpos( $runtime_contract, 'get_agent_feedback_summary()' )
	&& false !== strpos( $runtime_contract, 'get_observability_summary()' )
	&& false !== strpos( $agents, 'POST /v1/observability/plugin-events' )
	&& false !== strpos( $agents, 'POST /v1/agent-feedback/events' )
	&& false !== strpos( $agents, 'GET /v1/agent-feedback/summary' )
	&& false !== strpos( $boundary_doc, 'POST /v1/observability/plugin-events' )
	&& false !== strpos( $boundary_doc, 'POST /v1/agent-feedback/events' )
	&& false !== strpos( $boundary_doc, 'GET /v1/agent-feedback/summary' )
	&& false !== strpos( $boundary_doc, 'GET /v1/observability/plugin-summary' ),
	'Observability and Agent feedback transport endpoints are explicitly allowed by code and docs.'
);

maca_assert(
	false !== strpos( $observability, 'AGENT_SUMMARY_OPTION' )
	&& false !== strpos( $observability, 'refresh_agent_feedback_summary' )
	&& false !== strpos( $observability, 'get_agent_feedback_summary( 24' )
	&& false !== strpos( $observability, 'sanitize_agent_feedback_summary_payload' )
	&& false === strpos( $settings_page, 'Monitoring & Quality' )
	&& false === strpos( $settings_page, 'Refresh monitoring and quality' )
	&& false === strpos( $settings_page, 'ACTION_REFRESH_MONITORING' )
	&& false === strpos( $settings_page, 'function handle_refresh_monitoring' )
	&& false === strpos( $settings_page, 'Agent quality events' )
	&& false === strpos( $settings_page, 'function has_monitoring_detail' )
	&& false === strpos( $settings_page, 'function render_monitoring_advanced_diagnostics' )
	&& false === strpos( $settings_page, 'function render_agent_feedback_quality_lists' )
	&& false !== strpos( $settings_page, 'Monitoring needs attention' ),
	'Cloud Addon keeps Agent quality transport available without copying Cloud quality detail or refresh controls into wp-admin.'
);

maca_assert(
	false !== strpos( $observability, 'BUFFER_OPTION' )
	&& false !== strpos( $observability, 'npcink_cloud_addon_observability_buffer' )
	&& false !== strpos( $observability, 'MAX_BUFFER_ITEMS = 200' )
	&& false !== strpos( $observability, 'MAX_BATCH_ITEMS = 50' )
	&& false === strpos( $observability, 'QUEUE_OPTION' )
	&& false === strpos( $observability, 'MAX_QUEUE_ITEMS' ),
	'Observability uses a bounded delivery buffer rather than queue ownership terms.'
);

maca_assert(
	false === strpos( $bootstrap . $settings . $observability . $site_knowledge_bridge . $site_knowledge_runtime_bridge . $runtime_client, 'dbDelta(' )
	&& false === strpos( $bootstrap . $settings . $observability . $site_knowledge_bridge . $site_knowledge_runtime_bridge . $runtime_client, 'CREATE TABLE' )
	&& false !== strpos( $boundary_doc, 'Local Persistence Boundary' )
	&& false !== strpos( $boundary_doc, 'local delivery durability only' )
	&& false !== strpos( $boundary_doc, 'not queue truth, run truth, billing truth, indexing truth, approval truth, or audit truth' )
	&& false !== strpos( $boundary_doc, 'must not create WordPress custom tables' )
	&& false !== strpos( $boundary_doc, 'that state belongs in Cloud service storage' ),
	'Cloud Addon keeps local persistence to bounded options/buffers and leaves durable queue, run, billing, indexing, and diagnostics storage to Cloud.'
);

maca_assert(
	false !== strpos( $observability, 'Npcink_Cloud_Addon_Settings::is_monitoring_enabled()' )
	&& false !== strpos( $observability, 'wp_schedule_event' )
	&& false !== strpos( $agents, 'Bounded observability buffering and WP-Cron flushing are allowed only' ),
	'Observability capture and flush are gated by verified opt-in monitoring.'
);

maca_assert(
	false !== strpos( $bootstrap, 'class-cloud-site-knowledge-change-bridge.php' )
	&& false !== strpos( $bootstrap, 'class-cloud-site-knowledge-runtime-bridge.php' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_site_knowledge_change_bridge_health' )
	&& false !== strpos( $bootstrap, 'npcink_cloud_addon_dispatch_site_knowledge_runtime' )
	&& false !== strpos( $bootstrap, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::register()' )
	&& false !== strpos( $bootstrap, 'Npcink_Cloud_Site_Knowledge_Runtime_Bridge::register()' )
	&& false !== strpos( $bootstrap, 'site_knowledge_change_bridge_status.v1' )
	&& false !== strpos( $bootstrap, '`change_bridge` status projection' )
	&& false !== strpos( $bootstrap, 'prefer `buffer_count`' )
	&& false !== strpos( $readme, 'npcink_cloud_addon_site_knowledge_change_bridge_health(): array' ),
	'Bootstrap exposes Cloud Addon Site Knowledge change bridge health and runtime dispatch seams.'
);

maca_assert(
	false !== strpos( $site_knowledge_runtime_bridge, 'npcink_toolbox_site_knowledge_cloud_request' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'npcink-cloud/site-knowledge-search' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'npcink-cloud/site-knowledge-status' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'npcink-cloud/site-knowledge-sync' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'site_knowledge_search.v1' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'site_knowledge_status.v1' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'site_knowledge_sync.v1' )
	&& false !== strpos( $site_knowledge_runtime_bridge, "\$input['write_posture'] ?? ''" )
	&& false !== strpos( $site_knowledge_runtime_bridge, "'suggestion_only'" )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'cloud_site_knowledge_sync_mode_not_allowed' )
	&& false !== strpos( $site_knowledge_runtime_bridge, "'refresh' !== sanitize_key" )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'site_knowledge_cloud_boundary' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'normalize_ownership_map' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'normalize_truth_boundaries' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'cloud_is_wordpress_control_plane' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'cloud_creates_wordpress_writes' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'execute_runtime' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'MAX_RUNTIME_PAYLOAD_BYTES = 900000' ),
	'Site Knowledge runtime bridge accepts only known Toolbox ability contracts and forwards suggestion-only public refresh payloads through runtime execute.'
);

maca_assert(
	false !== strpos( $site_knowledge_runtime_bridge, 'function get_cached_status_summary' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'function refresh_status_summary' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'max_indexed_documents_per_site' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'max_indexed_chunks_per_site' )
	&& false !== strpos( $site_knowledge_runtime_bridge, "\$result['data']['result']" )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'STATUS_FRESHNESS_TTL_SECONDS' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'get_transient' )
	&& false !== strpos( $site_knowledge_runtime_bridge, 'set_transient' )
	&& false === strpos( $site_knowledge_runtime_bridge, '/v1/site-knowledge' ),
	'Site Knowledge usage reuses the existing status contract and retains only a bounded read-only cache without adding an addon-owned API.'
);

maca_assert(
	false === strpos( $site_knowledge_runtime_bridge, 'register_rest_route' )
	&& false === strpos( $site_knowledge_runtime_bridge, 'wp_insert_' . 'post' )
	&& false === strpos( $site_knowledge_runtime_bridge, 'wp_update_' . 'post' )
	&& false === strpos( $site_knowledge_runtime_bridge, 'update_post_meta' )
	&& false === strpos( $site_knowledge_runtime_bridge, 'ActionScheduler' )
	&& false === strpos( $site_knowledge_runtime_bridge, 'as_enqueue' )
	&& false !== strpos( $boundary_doc, 'Toolbox Site Knowledge runtime transport must use the existing' )
	&& false !== strpos( $runtime_contract, 'dispatch_site_knowledge_runtime' )
	&& false !== strpos( $readme, 'Site Knowledge Runtime Bridge' ),
	'Site Knowledge runtime bridge stays transport-only without REST routes, queues, WordPress writes, or local index lifecycle ownership.'
);

maca_assert(
	false !== strpos( $site_knowledge_bridge, 'BUFFER_OPTION' )
	&& false !== strpos( $site_knowledge_bridge, 'npcink_cloud_addon_site_knowledge_change_buffer' )
		&& false !== strpos( $site_knowledge_bridge, 'MAX_BUFFER_ITEMS = 500' )
		&& false !== strpos( $site_knowledge_bridge, 'MAX_BATCH_ITEMS = 25' )
		&& false !== strpos( $site_knowledge_bridge, 'Npcink_Cloud_Addon_Settings::is_verified()' )
		&& false !== strpos( $settings, 'is_site_knowledge_delivery_enabled' )
		&& false !== strpos( $settings, "'site_knowledge_delivery_enabled'" )
		&& false === strpos( $site_knowledge_bridge, 'QUEUE_OPTION' )
		&& false === strpos( $site_knowledge_bridge, 'MAX_QUEUE_ITEMS' ),
		'Site Knowledge change bridge uses bounded delivery buffer language instead of queue ownership terms and waits for verified Cloud settings plus local delivery consent.'
	);

	maca_assert(
		false !== strpos( $site_knowledge_bridge, "'status' => \$bridge_status" )
		&& false !== strpos( $site_knowledge_bridge, "STATUS_CONTRACT = 'site_knowledge_change_bridge_status.v1'" )
		&& false !== strpos( $site_knowledge_bridge, "'preferred_status_field' => 'change_bridge'" )
		&& false !== strpos( $site_knowledge_bridge, "'preferred_count_field' => 'buffer_count'" )
		&& false !== strpos( $site_knowledge_bridge, "HEALTH_DETAIL_CONTRACT = 'site_knowledge_bridge_health.v1'" )
		&& false !== strpos( $site_knowledge_bridge, "'health_detail_version' => self::HEALTH_DETAIL_CONTRACT" )
		&& false !== strpos( $site_knowledge_bridge, "'delivery_enabled' => \$delivery_enabled" )
		&& false !== strpos( $site_knowledge_bridge, "'delivery_attempts' => absint" )
		&& false !== strpos( $site_knowledge_bridge, "'buffer_semantics' => 'bounded_delivery_buffer'" )
		&& false !== strpos( $site_knowledge_bridge, "'buffer_truth' => 'local_delivery_durability_only'" )
		&& false !== strpos( $site_knowledge_bridge, "'delivery_truth_owner' => 'cloud_addon'" )
		&& false !== strpos( $site_knowledge_bridge, "'index_execution_owner' => 'cloud_service'" )
		&& false !== strpos( $site_knowledge_bridge, "'freshness_policy_owner' => 'cloud_service'" )
		&& false !== strpos( $site_knowledge_bridge, "'diagnostics_detail_owner' => 'cloud_service'" )
		&& false !== strpos( $site_knowledge_bridge, "'disabled'" )
		&& false !== strpos( $site_knowledge_bridge, "'error'" )
		&& false !== strpos( $site_knowledge_bridge, "'last_delivery_at'" )
	&& false !== strpos( $site_knowledge_bridge, "'last_success_at'" )
	&& false !== strpos( $site_knowledge_bridge, "'last_error_code'" )
	&& false !== strpos( $site_knowledge_bridge, "'last_error_at'" )
	&& false !== strpos( $site_knowledge_bridge, "'legacy_toolbox_fallback' => false" ),
	'Site Knowledge change bridge exposes stable health fields for Toolbox without re-enabling the Toolbox legacy fallback.'
);

maca_assert(
	false !== strpos( $site_knowledge_bridge, 'transition_post_status' )
	&& false !== strpos( $site_knowledge_bridge, "add_action( 'save_post'" )
	&& false !== strpos( $site_knowledge_bridge, 'transition_comment_status' )
	&& false !== strpos( $site_knowledge_bridge, 'comment_post' )
	&& false !== strpos( $site_knowledge_bridge, 'edit_comment' )
	&& false !== strpos( $site_knowledge_bridge, 'trashed_comment' ),
	'Site Knowledge change bridge watches public post/page and approved comment changes.'
);

maca_assert(
	false !== strpos( $site_knowledge_bridge, 'request_site_knowledge_sync' )
	&& false !== strpos( $site_knowledge_bridge, 'request_manual_index_operation' )
	&& false !== strpos( $site_knowledge_bridge, "'site_knowledge_sync.v1'" )
	&& false !== strpos( $site_knowledge_bridge, 'MAINTENANCE_OPTION' )
	&& false !== strpos( $site_knowledge_bridge, 'flush_full_index_delivery' )
	&& false !== strpos( $site_knowledge_bridge, 'is_active_full_index_delivery' )
	&& false !== strpos( $site_knowledge_bridge, 'add_option( self::MAINTENANCE_OPTION' )
	&& false !== strpos( $site_knowledge_bridge, 'compare_and_swap_full_index_delivery_cursor' )
	&& false !== strpos( $site_knowledge_bridge, "'operation_source' => 'admin_' . \$operation" )
	&& false !== strpos( $site_knowledge_bridge, "\$is_first_rebuild_batch = 'rebuild' === \$operation && 0 === \$batch_index;" )
	&& false !== strpos( $site_knowledge_bridge, "\$is_first_rebuild_batch ? array() : \$batch" )
	&& false !== strpos( $site_knowledge_bridge, "'sync_mode' => \$sync_mode" )
	&& false !== strpos( $site_knowledge_bridge, "array( 'refresh', 'rebuild', 'delete' )" )
	&& false !== strpos( $site_knowledge_bridge, "'delete' !== \$operation && ! self::is_enabled()" )
	&& false !== strpos( $site_knowledge_bridge, 'Site Knowledge delivery is disabled locally. Enable delivery before starting or rebuilding the index.' )
	&& false !== strpos( $site_knowledge_bridge, "'write_posture' => 'suggestion_only'" )
	&& false !== strpos( $site_knowledge_bridge, "'direct_wordpress_write' => false" )
	&& false !== strpos( $site_knowledge_bridge, 'execute_runtime' )
	&& false !== strpos( $boundary_doc, 'Cloud remains the' )
	&& false !== strpos( $boundary_doc, 'executor and index lifecycle owner' )
	&& false !== strpos( $boundary_doc, 'verified administrator intent' )
	&& false !== strpos( $site_knowledge_full_index_doc, 'does not create a local index job, queue,' )
	&& false !== strpos( $site_knowledge_full_index_doc, 'Cloud continues to own queued execution, index lifecycle, vector storage,' ),
	'Site Knowledge change bridge forwards bounded public refresh and administrator delivery intents to Cloud runtime only.'
);

maca_assert(
	false !== strpos( $site_knowledge_bridge, 'wp_schedule_single_event' )
	&& false !== strpos( $site_knowledge_bridge, 'wp_schedule_event' )
	&& false !== strpos( $site_knowledge_bridge, 'MAX_DELIVERY_ATTEMPTS' )
	&& false !== strpos( $site_knowledge_bridge, 'retry_or_drop_buffer' )
	&& false !== strpos( $agents, 'Bounded Site Knowledge change buffering, WP-Cron flushing, local delivery' )
	&& false !== strpos( $agents, 'consent, and explicit administrator delivery intents for Cloud-owned index' ),
	'Site Knowledge change bridge has bounded delivery attempts and a low-frequency reconciliation safety net.'
);

maca_assert(
	false === strpos( $site_knowledge_bridge, 'wp_insert_' . 'post' )
	&& false === strpos( $site_knowledge_bridge, 'wp_update_' . 'post' )
	&& false === strpos( $site_knowledge_bridge, 'update_post_meta' )
	&& false === strpos( $site_knowledge_bridge, 'register_rest_route' )
	&& false === strpos( $site_knowledge_bridge, 'ActionScheduler' )
	&& false === strpos( $site_knowledge_bridge, 'as_enqueue' )
	&& false !== strpos( $site_knowledge_bridge, "'index_lifecycle_owner' => 'cloud_service'" )
	&& false !== strpos( $site_knowledge_bridge, "'scheduler_truth' => false" )
	&& false !== strpos( $site_knowledge_bridge, "'workflow_truth' => false" )
	&& false !== strpos( $site_knowledge_bridge, "'wordpress_write_included' => false" ),
	'Site Knowledge change bridge does not introduce WordPress writes, REST control routes, Action Scheduler, or local index lifecycle ownership.'
);

maca_assert(
	false !== strpos( $observability, "'plugin_slug'" )
	&& false !== strpos( $observability, "'event_kind'" )
	&& false !== strpos( $observability, "'proposal_id'" )
	&& false !== strpos( $observability, "'correlation_id'" )
	&& false !== strpos( $observability, "'latency_ms'" )
	&& false === strpos( $observability, "'prompt'" )
	&& false === strpos( $observability, "'content'" )
	&& false === strpos( $observability, "'raw_request'" )
	&& false === strpos( $observability, "'raw_response'" )
	&& false === strpos( $observability, "'authorization'" )
	&& false === strpos( $observability, "'cookie'" )
	&& false === strpos( $observability, "'nonce'" )
	&& false === strpos( $observability, "'secret'" ),
	'Observability event normalization is metadata-only and excludes sensitive/raw payload fields.'
);

maca_assert(
	false !== strpos( $settings_page, 'Buffered events' )
	&& false !== strpos( $settings_page, 'buffer_count' )
	&& false !== strpos( $settings_page, "DATETIME_DISPLAY_FORMAT = 'Y-m-d H:i:s'" )
	&& false !== strpos( $settings_page, 'format_datetime_value' )
	&& false !== strpos( $settings_page, 'wp_date( self::DATETIME_DISPLAY_FORMAT, $timestamp )' )
	&& false !== strpos( $settings_page, '$show_connection_meta = ! $is_verified || $is_custom_base_url' )
	&& false === strpos( $settings_page, "self::format_datetime_value( (string) ( \$monitoring['last_uploaded_at'] ?? '' ) )" )
	&& false === strpos( $settings_page, "self::format_datetime_value( (string) ( \$summary['synced_at'] ?? '' ) )" )
	&& false !== strpos( $boundary_doc, 'Cloud observability summaries are read-only dashboard projections' )
	&& false !== strpos( $runtime_contract, 'must not be treated as Core audit truth' ),
	'Monitoring UI and docs keep observability as dashboard projection, not governance truth.'
);

maca_assert(
		false !== strpos( $settings_page, "\$default = \$is_verified ? 'permissions' : 'connect';" )
		&& false !== strpos( $settings_page, 'function should_show_unverified_advanced_tab' )
		&& false !== strpos( $settings_page, 'self::should_show_unverified_advanced_tab( $state )' )
		&& false !== strpos( $settings_page, "'permissions'    => __( 'Overview'" )
		&& false === strpos( $settings_page, "'status'         => __( 'Status'" )
		&& false !== strpos( $settings_page, "'site_knowledge' => __( 'Site Knowledge'" )
		&& false === strpos( $settings_page, "'diagnostics'    => __( 'Troubleshooting'" )
		&& false !== strpos( $settings_page, "'connect'  => __( 'Connect'" )
		&& false !== strpos( $settings_page, "'advanced'       => __( 'Advanced and troubleshooting'" )
		&& false !== strpos( $settings_page, 'Connect this site' )
		&& false !== strpos( $settings_page, "'permissions' === \$active_tab" )
		&& false !== strpos( $settings_page, "'site_knowledge' === \$active_tab" )
		&& false !== strpos( $settings_page, "'advanced' === \$active_tab" )
	&& false !== strpos( $settings_page, "in_array( \$requested, array( 'runtime_runs', 'diagnostics' ), true )" )
	&& false !== strpos( $settings_page, "in_array( \$requested, array( 'details', 'status' ), true )" )
	&& false !== strpos( $settings_page, 'function render_advanced_page' )
	&& false !== strpos( $settings_page, 'function render_runtime_runs' )
	&& false !== strpos( $settings_page, 'function diagnostics_view_from_request' )
	&& false === strpos( $settings_page, 'function status_view_from_request' )
	&& false === strpos( $settings_page, 'function connection_view_from_request' )
	&& false !== strpos( $settings_page, 'Advanced and troubleshooting sections' )
	&& false !== strpos( $settings_page, "'service'    => __( 'Service details'" )
	&& false !== strpos( $settings_page, "'checks'     => __( 'Checks'" )
	&& false !== strpos( $settings_page, "'runs'       => __( 'Runtime runs'" )
	&& false === strpos( $settings_page, "'capabilities' => __( 'Capability notes'" )
	&& false === strpos( $settings_page, 'Status sections' )
	&& false !== strpos( $settings_page, "'connection' => __( 'Connection recovery'" )
	&& false === strpos( $settings_page, 'Read-only connection and service status. Product actions, approvals, and WordPress writes stay outside this addon.' )
	&& false !== strpos( $settings_page, 'Cloud runtime runs' )
	&& false !== strpos( $settings_page, 'Open Cloud status detail' )
	&& false !== strpos( $settings_page, 'Cloud connection' )
	&& false === strpos( $settings_page, "__( 'Cloud liveness'" )
	&& false === strpos( $settings_page, "__( 'Signed Cloud read'" )
	&& false !== strpos( $settings_page, 'Load recent runs' )
	&& false !== strpos( $settings_page, 'Open Cloud run detail' )
	&& false !== strpos( $settings_page, 'Service details' )
	&& false !== strpos( $settings_page, 'Cloud API Key' )
	&& false === strpos( $settings_page, 'Split signing credentials are not displayed' )
	&& false === strpos( $settings_page, 'Platform Models and provider readiness' )
	&& false === strpos( $settings_page, 'Cloud-owned capability notes' )
	&& false === strpos( $settings_page, 'npcink-cloud-capability-list' )
	&& false === strpos( $settings_page, 'npcink-cloud-capability-header' )
	&& false === strpos( $settings_page, 'npcink-cloud-capability-icon' )
	&& false === strpos( $settings_page, 'npcink-cloud-capability-popover' )
	&& false !== strpos( $settings_page, 'aria-describedby' )
	&& false === strpos( $settings_page, 'function render_capability_note' )
	&& false === strpos( $settings_page, '<details class="npcink-cloud-capability-detail">' )
	&& false === strpos( $admin_css, '.npcink-cloud-capability-item' )
	&& false === strpos( $admin_css, '.npcink-cloud-capability-popover' )
	&& false === strpos( $admin_css, '.npcink-cloud-capability-detail:focus' )
	&& false !== strpos( $admin_css, 'max-width: 1120px' )
	&& false !== strpos( $admin_css, 'max-width: 1040px' )
	&& false === strpos( $admin_css, '.npcink-cloud-disclosure' )
	&& false === strpos( $settings_page, 'Only local connector summaries are shown here.' )
	&& false !== strpos( $settings, 'is_wordpress_ai_connector_enabled' )
	&& false !== strpos( $settings, "'wordpress_ai_connector_enabled'" )
	&& false !== strpos( $settings, 'is_site_knowledge_generation_reference_enabled' )
	&& false !== strpos( $settings, "'site_knowledge_generation_reference_enabled'" )
	&& false !== strpos( $wordpress_ai_connector, "'site_knowledge_reference'" )
	&& false !== strpos( $wordpress_ai_connector, "'site_title_style'" )
	&& false !== strpos( $wordpress_ai_connector, "'site_summary_style'" )
	&& false === strpos( $wordpress_ai_connector, "'site_excerpt_style'" )
	&& false === strpos( $wordpress_ai_connector, "'site_meta_style'" )
	&& false === strpos( $wordpress_ai_connector, "'site_taxonomy_history'" )
	&& false !== strpos( $runtime_client, 'normalize_wordpress_ai_site_knowledge_reference' )
	&& false !== strpos( $settings_page, "ACTION_UPDATE_LOCAL_PERMISSION = 'npcink_cloud_addon_update_local_permission'" )
	&& false !== strpos( $settings_page, "admin_post_' . self::ACTION_UPDATE_LOCAL_PERMISSION" )
	&& false !== strpos( $settings_page, 'function handle_update_local_permission' )
	&& false !== strpos( $settings_page, 'check_admin_referer( self::ACTION_UPDATE_LOCAL_PERMISSION )' )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Addon_Settings::write_settings( $settings );' )
	&& false !== strpos( $settings_page, "'site_knowledge_delivery_enabled' => array(" )
	&& false !== strpos( $settings_page, "if ( 'site_knowledge_delivery_enabled' === \$permission )" )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::sync_schedule()' )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::resume_pending_delivery()' )
	&& false === strpos( $settings_page, 'ACTION_UPDATE_SITE_KNOWLEDGE_DELIVERY' )
	&& false === strpos( $settings_page, 'npcink_cloud_addon_update_site_knowledge_delivery' )
	&& false === strpos( $settings_page, 'function handle_update_site_knowledge_delivery' )
	&& false !== strpos( $settings_page, 'function render_local_permissions' )
	&& false !== strpos( $settings_page, 'function render_local_permission_switch' )
	&& false !== strpos( $settings_page, 'self::render_local_permissions( $settings, $is_verified );' )
	&& false !== strpos( $settings_page, "self::redirect_to_page( 'permissions' );" )
	&& false !== strpos( $settings_page, 'Local permissions' )
	&& false !== strpos( $settings_page, 'WordPress AI connector' )
	&& false !== strpos( $settings_page, 'Allow WordPress AI to use Npcink Cloud.' )
	&& false !== strpos( $settings_page, 'Send public content changes to Cloud Site Knowledge.' )
	&& false !== strpos( $settings_page, 'Reference site content during generation' )
	&& false === strpos( $settings_page, 'AI generation reference' )
	&& false !== strpos( $settings_page, 'Use indexed public articles as generation context.' )
	&& false !== strpos( $settings_page, 'Upload metadata-only plugin monitoring events.' )
	&& false !== strpos( $settings_page, 'More local permissions' )
	&& false === strpos( $settings_page, 'npcink-cloud-local-permission--dependent' )
	&& false === strpos( $admin_css, '.npcink-cloud-local-permission--dependent' )
	&& false !== strpos( $settings_page, 'onchange="this.form.submit();"' )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_WordPress_AI_Connector::sync_connected_marker()' )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Observability_Collector::sync_schedule()' )
	&& false !== strpos( $admin_css, '.npcink-cloud-local-permissions' )
	&& false !== strpos( $admin_css, '.npcink-ai-switch__track' )
	&& false !== strpos( $settings_page, 'class="npcink-ai-switch__input"' )
	&& false !== strpos( $settings_page, 'Manual connection fallback' )
	&& false === strpos( $settings_page, 'Local connection fallback and last verification failure.' )
	&& false !== strpos( $settings_page, '<details class="npcink-cloud-advanced-detail">' )
	&& false !== strpos( $settings_page, '<h3><?php esc_html_e( \'Connection status\'' )
	&& false === strpos( $settings_page, '<h3><?php esc_html_e( \'Manual connection fallback\'' )
	&& false === strpos( $settings_page, '<strong><?php esc_html_e( \'Advanced raw status\'' )
	&& false === strpos( $settings_page, '<strong><?php esc_html_e( \'Manual connection fallback\'' )
	&& false === strpos( $settings_page, '<h3><?php esc_html_e( \'Advanced raw status\'' )
	&& false === strpos( $settings_page, 'Credit policy' )
	&& false === strpos( $settings_page, 'Runtime local truth' )
	&& false !== strpos( $settings_page, 'function render_status_account_usage' )
	&& false !== strpos( $settings_page, 'function render_status_monitoring_quality' )
	&& false === strpos( $settings_page, 'function render_status_monitoring_diagnostics' )
	&& false === strpos( $settings_page, 'function render_monitoring_advanced_diagnostics' )
	&& false !== strpos( $settings_page, 'No additional entitlement parameters were returned by Cloud.' )
	&& false === strpos( $settings_page, 'Monitoring and quality projections are not available yet.' )
	&& false === strpos( $settings_page, 'Monitoring diagnostics are not available yet.' )
	&& false === strpos( $settings_page, 'function render_details_panel' )
	&& false === strpos( $settings_page, 'function has_entitlement_detail' )
	&& false !== strpos( $settings_page, 'function render_overview_page' )
	&& false !== strpos( $settings_page, 'format_monitoring_overview' )
	&& false !== strpos( $settings_page, 'format_site_knowledge_overview' )
	&& false !== strpos( $settings_page, 'Plan and entitlement' )
	&& false !== strpos( $settings_page, 'Re-verify and refresh' )
	&& 2 === substr_count( $settings_page, 'self::render_reverify_form( $settings );' )
	&& false !== strpos( $admin_css, '.npcink-cloud-summary__actions > form' )
	&& false !== strpos( $admin_css, '.npcink-cloud-section-heading .npcink-cloud-verify-form' )
	&& false !== strpos( $admin_css, '.npcink-cloud-section-heading .npcink-cloud-summary__actions' )
	&& false === strpos( $settings_page, 'Only local connector summaries are shown here.' )
	&& false === strpos( $settings_page, 'This troubleshooting section creates no local queue, scheduler, proposal, approval record, or WordPress write.' )
		&& false === strpos( $settings_page, 'Refresh Cloud summary' )
		&& false === strpos( $settings_page, '<h3><?php esc_html_e( \'Entitlement Summary\'' )
		&& false !== strpos( $settings_page, '<h3><?php esc_html_e( \'Entitlement details\'' )
		&& false === strpos( $settings_page, '<h3><?php esc_html_e( \'Site Knowledge\'' )
		&& false !== strpos( $settings_page, '<h2 class="screen-reader-text"><?php esc_html_e( \'Site Knowledge\'' )
		&& false === strpos( $settings_page, '<h3><?php esc_html_e( \'Monitoring & Quality\'' )
		&& false !== strpos( $settings_page, '<h3><?php esc_html_e( \'Monitoring needs attention\'' )
	&& false !== strpos( $settings_page, "self::redirect_to_page( 'status' );" )
	&& false !== strpos( $settings_page, "self::redirect_to_page( 'advanced', 'checks' );" )
	&& false !== strpos( $settings_page, "self::redirect_to_page( 'advanced', 'runs' );" )
	&& false === strpos( $settings_page, "'monitoring'  =>" ),
	'Settings page defaults to connect before verification, opens a compact overview after verification, keeps advanced detail behind one entry, and gives Site Knowledge a dedicated tab.'
);

maca_assert(
	false !== strpos( $admin_surface_standard, 'Verified admin navigation should stay at three top-level entries' )
	&& false !== strpos( $admin_surface_standard, '`Overview`: compact plan plus attention-only connector rows' )
	&& false !== strpos( $admin_surface_standard, '`Advanced and troubleshooting`: service detail, checks, runtime runs' )
	&& false !== strpos( $admin_surface_standard, 'Do not surface internal enum fields such as credit policy or runtime local truth' )
	&& false !== strpos( $admin_surface_standard, 'Do not copy Cloud observability aggregates, Agent quality breakdowns' )
	&& false !== strpos( $admin_surface_standard, 'Do not reintroduce separate `Status`, `Troubleshooting`, `Connection' )
	&& false !== strpos( $admin_surface_standard, 'avoid nested disclosure controls inside another disclosure' )
	&& false !== strpos( $admin_ui_simplification_doc, 'Cloud Addon Admin UI Simplification Closeout' )
	&& false !== strpos( $admin_ui_simplification_doc, 'Fold legacy `Details` into `Status`' )
	&& false !== strpos( $admin_ui_simplification_doc, 'dynamic ability metadata' )
	&& false !== strpos( $admin_ui_simplification_doc, 'no nested `<details>`' )
	&& false !== strpos( $admin_ui_simplification_doc, 'Cloud remains the owner of runtime detail' ),
	'Admin UI simplification closeout documents the current tab model, detail hierarchy, localization boundary, and Cloud ownership boundary.'
);

	maca_assert(
		false !== strpos( $settings_page, "ACTION_REFRESH_SITE_KNOWLEDGE = 'npcink_cloud_addon_refresh_site_knowledge'" )
		&& false !== strpos( $settings_page, "ACTION_MANAGE_SITE_KNOWLEDGE_INDEX = 'npcink_cloud_addon_manage_site_knowledge_index'" )
		&& false !== strpos( $settings_page, "admin_post_' . self::ACTION_REFRESH_SITE_KNOWLEDGE" )
		&& false !== strpos( $settings_page, "admin_post_' . self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX" )
		&& false !== strpos( $settings_page, 'function handle_refresh_site_knowledge' )
		&& false !== strpos( $settings_page, 'function handle_manage_site_knowledge_index' )
		&& false !== strpos( $refresh_site_knowledge_handler, 'current_user_can( \'manage_options\' )' )
		&& false !== strpos( $refresh_site_knowledge_handler, 'check_admin_referer( self::ACTION_REFRESH_SITE_KNOWLEDGE )' )
		&& false !== strpos( $refresh_site_knowledge_handler, 'Npcink_Cloud_Site_Knowledge_Admin_Actions::request_public_refresh()' )
		&& false !== strpos( $refresh_site_knowledge_handler, "self::redirect_to_page( 'site_knowledge' )" )
		&& false === strpos( $refresh_site_knowledge_handler, 'Npcink_Cloud_Site_Knowledge_Admin_Actions::request_index_operation' )
		&& false !== strpos( $manage_site_knowledge_index_handler, 'current_user_can( \'manage_options\' )' )
		&& false !== strpos( $manage_site_knowledge_index_handler, 'check_admin_referer( self::ACTION_MANAGE_SITE_KNOWLEDGE_INDEX )' )
		&& false !== strpos( $manage_site_knowledge_index_handler, "sanitize_key( wp_unslash( \$_POST['site_knowledge_index_action'] ) )" )
		&& false !== strpos( $manage_site_knowledge_index_handler, "sanitize_text_field( wp_unslash( \$_POST['site_knowledge_confirmation'] ) )" )
		&& false !== strpos( $manage_site_knowledge_index_handler, 'Npcink_Cloud_Site_Knowledge_Admin_Actions::request_index_operation( $operation, $confirmation )' )
		&& false !== strpos( $manage_site_knowledge_index_handler, "self::redirect_to_page( 'site_knowledge' )" )
		&& false === strpos( $manage_site_knowledge_index_handler, 'Npcink_Cloud_Runtime_Client' )
		&& false !== strpos( $settings_page, "site_knowledge_delivery_enabled" )
		&& false !== strpos( $settings_page, 'Site Knowledge delivery' )
		&& false !== strpos( $settings_page, 'Delivery is off; refresh controls and routine delivery rows are hidden.' )
		&& false === strpos( $settings_page, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::buffer_recent_public_content()' )
		&& false === strpos( $settings_page, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer()' )
		&& false !== strpos( $settings_page, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::sync_schedule()' )
		&& false !== strpos( $settings_page, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::resume_pending_delivery()' )
		&& false === strpos( $settings_page, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation' )
		&& false !== strpos( $site_knowledge_admin_actions, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::buffer_recent_public_content()' )
		&& false !== strpos( $site_knowledge_admin_actions, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::flush_buffer()' )
		&& false !== strpos( $site_knowledge_admin_actions, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::request_manual_index_operation( $operation )' )
	&& false !== strpos( $settings_page, 'Request public content refresh' )
	&& false !== strpos( $settings_page, 'Start indexing' )
	&& false !== strpos( $settings_page, 'Rebuild index' )
	&& false !== strpos( $settings_page, 'Delete site index' )
	&& false !== strpos( $settings_page, 'site_knowledge_confirmation' )
	&& false !== strpos( $settings_page, 'Open Cloud Site Knowledge' )
	&& false !== strpos( $settings_page, '<h3><?php esc_html_e( \'Overview\'' )
	&& false !== strpos( $settings_page, '<h2 class="screen-reader-text"><?php esc_html_e( \'Site Knowledge\'' )
	&& false !== strpos( $settings_page, 'function render_secondary_tab_navigation' )
	&& false !== strpos( $settings_page, 'function site_knowledge_view_from_request' )
	&& false !== strpos( $settings_page, 'npcink-cloud-secondary-tabs' )
	&& false !== strpos( $settings_page, "self::tab_view_url( 'site_knowledge', 'index' )" )
	&& false !== strpos( $settings_page, 'Manage index' )
	&& false !== strpos( $settings_page, 'Back to Site Knowledge' )
	&& false !== strpos( $settings_page, 'npcink-cloud-site-knowledge-consent__copy' )
	&& false !== strpos( $settings_page, 'npcink-cloud-site-knowledge-consent__control' )
	&& false !== strpos( $settings_page, 'npcink-cloud-site-knowledge-consent--readonly' )
	&& false !== strpos( $settings_page, "self::tab_url( 'permissions' )" )
	&& false !== strpos( $settings_page, 'Change in Overview' )
	&& false === strpos( $settings_page, 'npcink-cloud-site-knowledge-tab-delivery-enabled' )
	&& false !== strpos( $settings_page, 'Allow public content-change delivery and explicit administrator delivery intent. WordPress content is not changed.' )
	&& false !== strpos( $settings_page, 'Site Knowledge delivery details' )
	&& false !== strpos( $settings_page, 'Cloud owns indexing, rebuild, deletion, freshness policy, and diagnostics.' )
	&& false !== strpos( $settings_page, 'Cloud index cleanup remains a separate explicit action.' )
	&& false !== strpos( $settings_page, 'npcink-cloud-inline-info' )
	&& false !== strpos( $admin_css, '.npcink-cloud-inline-info' )
	&& false !== strpos( $settings_page, 'function format_site_knowledge_status_label' )
	&& false !== strpos( $settings_page, "'idle'" )
	&& false !== strpos( $settings_page, "=> __( 'idle'" )
	&& false !== strpos( $settings_page, "'queued'" )
	&& false !== strpos( $settings_page, "=> __( 'queued'" )
	&& false !== strpos( $settings_page, "__( '%d public changes awaiting delivery'" )
	&& false !== strpos( $settings_page, "in_array( \$status, array( 'pending', 'queued' ), true )" )
	&& false !== strpos( $settings_page, 'function render_site_knowledge_error_cell' )
	&& false !== strpos( $settings_page, 'Show original Cloud error' )
	&& false !== strpos( $settings_page, 'data_classification=pii' )
	&& false !== strpos( $settings_page, 'Cloud active run limit reached' )
	&& false === strpos( $settings_page, 'Transport only; Cloud owns indexing detail.' )
	&& false !== strpos( $settings_page, 'function render_site_knowledge_bridge_health_detail' )
	&& false !== strpos( $settings_page, 'Technical delivery details' )
	&& 2 === substr_count( $settings_page, "<?php esc_html_e( 'Technical delivery details'" )
	&& false !== strpos( $settings_page, 'Bridge health detail' )
	&& false === strpos( $settings_page, 'Health contract' )
	&& false === strpos( $settings_page, 'Delivery attempts' )
	&& false === strpos( $settings_page, 'Next reconcile' )
	&& false !== strpos( $settings_page, 'Manual flush command' )
	&& false === strpos( $settings_page, 'function render_site_knowledge_boundary_truth_detail' )
	&& false === strpos( $settings_page, 'function format_site_knowledge_owner_label' )
	&& false === strpos( $settings_page, 'This detail is local connector health only; Cloud remains the owner of indexing, freshness policy, collection lifecycle, and diagnostics.' )
	&& false !== strpos( $settings_page, 'render_site_knowledge_index_operations' )
	&& false !== strpos( $settings_page, 'Index operations' )
	&& false !== strpos( $settings_page, 'Use only for initial indexing, rebuilds, or explicit Cloud index cleanup.' )
	&& false !== strpos( $settings_page, 'Cloud index cleanup' )
	&& false !== strpos( $settings_page, 'These actions send intent only; WordPress content is not changed.' )
	&& false !== strpos( $settings_page, 'These actions send local administrator delivery intent and bounded public WordPress content for Cloud-owned Site Knowledge operations.' )
	&& false === strpos( $settings_page, '<th scope="row"><?php esc_html_e( \'AI generation reference\'' )
	&& false === strpos( $settings_page, '<th scope="row"><?php esc_html_e( \'Connector state\'' )
	&& false === strpos( $settings_page, '<th scope="row"><?php esc_html_e( \'Next flush\'' )
	&& false !== strpos( $settings_page, '$show_technical_detail' )
	&& false === strpos( $settings_page, 'site_knowledge_index_policy' )
	&& false === strpos( $settings_page, 'collection_lifecycle_owner' ),
	'Settings page exposes bounded Site Knowledge delivery status and administrator delivery intents without local lifecycle ownership.'
);

maca_assert(
	false !== strpos( $settings_page, "ACTION_REFRESH_SITE_KNOWLEDGE_STATUS = 'npcink_cloud_addon_refresh_site_knowledge_status'" )
	&& false !== strpos( $settings_page, "wp_ajax_' . self::ACTION_REFRESH_SITE_KNOWLEDGE_STATUS" )
	&& false !== strpos( $settings_page, 'function handle_refresh_site_knowledge_status' )
	&& false !== strpos( $settings_page, "current_user_can( self::MENU_CAPABILITY )" )
	&& false !== strpos( $settings_page, "check_ajax_referer( self::ACTION_REFRESH_SITE_KNOWLEDGE_STATUS, 'nonce' )" )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Addon_Settings::is_site_knowledge_delivery_enabled()' )
	&& false !== strpos( $settings_page, 'Available knowledge documents' )
	&& false !== strpos( $settings_page, 'data-npcink-site-knowledge-progress' )
	&& false !== strpos( $settings_page, 'data-npcink-site-knowledge-usage-value' )
	&& false !== strpos( $settings_page, 'data-npcink-site-knowledge-usage-status' )
	&& false !== strpos( $settings_page, '%1$s / %2$s · %3$d%% remaining' )
	&& false === strpos( $settings_page, '%1$s / %2$s · %3$d%% used' )
	&& false !== strpos( $settings_page, 'function render_site_knowledge_cloud_quota_detail' )
	&& 1 === substr_count( $settings_page, "esc_html_e( 'Available knowledge documents'" )
	&& false !== strpos( $admin_site_knowledge_js, "'not_refreshed' === initialState || 'stale' === initialState" )
	&& false !== strpos( $admin_site_knowledge_js, 'data-npcink-site-knowledge-detail' )
	&& false !== strpos( $admin_site_knowledge_js, "progress.setAttribute( 'aria-valuenow', String( percent ) )" )
	&& false !== strpos( $settings_page, 'class="npcink-cloud-metric-actions"' )
	&& false !== strpos( $settings_page, 'data-npcink-site-knowledge-actions' )
	&& false !== strpos( $admin_site_knowledge_js, 'actions.hidden = ! loading' )
	&& false !== strpos( $admin_css, '.npcink-cloud-metric-actions[hidden]' )
	&& false !== strpos( $admin_css, '.npcink-cloud-site-knowledge-progress--warning' )
	&& false !== strpos( $admin_css, '.npcink-cloud-site-knowledge-progress--error' ),
	'Site Knowledge shows one auto-refreshed document quota with visible numbers and keeps lower-frequency Cloud quota fields in technical detail.'
);

maca_assert(
	false !== strpos( $site_knowledge_vector_ops_doc, 'require' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, '`manage_options`' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'verified Cloud settings' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, '`Enable Site Knowledge delivery` setting is local delivery consent only' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, '`site_knowledge_sync.v1` with `sync_mode=refresh`' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, '`sync_mode=rebuild`' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, '`sync_mode=delete`' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'Cloud deletes the site index' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'Delete site' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'index remains available as an explicit cleanup path' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'short-lived, read-only article-usage summary' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'unchanged.' ),
	'Site Knowledge vector operations doc records permissions and local administrator delivery intent transport.'
);

maca_assert(
	false !== strpos( $site_knowledge_vector_ops_doc, 'published posts and pages' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'approved comments' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'must not contain' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'drafts, private posts, password-protected posts' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'provider credentials, API keys' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'Cloud Site Knowledge remains the owner for embedding, vector storage' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'site_knowledge_change_bridge_status.v1' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'expose it as' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, '`change_bridge`' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, '`buffer_count`' )
	&& false !== strpos( $site_knowledge_vector_ops_doc, 'not vector queue truth' ),
	'Site Knowledge vector operations doc records public content admission and Cloud index lifecycle ownership.'
);

maca_assert(
	false !== strpos( $admin_surface_standard, 'Toolbox no longer owns Cloud Checks or Troubleshooting Checks' )
	&& false !== strpos( $admin_surface_standard, 'entry for those Cloud connection and service-status details' )
	&& false !== strpos( $boundary_doc, 'Toolbox no longer owns basic Cloud Checks / Troubleshooting Checks' )
	&& false !== strpos( $boundary_doc, 'Missing Cloud service contracts must be shown' )
	&& false !== strpos( $boundary_doc, 'connected or Cloud-owned rather than simulated locally' )
	&& false !== strpos( $runtime_contract, 'The Cloud Addon `Advanced and troubleshooting > Checks` section reuses the existing connection state' )
	&& false !== strpos( $runtime_contract, 'The Cloud Addon `Advanced and troubleshooting > Runtime runs` section may use the existing' )
	&& false !== strpos( $runtime_contract, 'Nightly Site Inspection run quota, remaining runs, batch limits' )
	&& false !== strpos( $runtime_contract, 'must not submit scheduled reviews, rebuild Toolbox local snapshots' )
	&& false !== strpos( $boundary_doc, 'Bounded Nightly Inspection runtime run detail' )
	&& false !== strpos( $boundary_doc, '`Advanced and troubleshooting > Runtime runs` section' )
	&& false !== strpos( $boundary_doc, 'must not submit scheduled reviews, reconstruct Toolbox snapshots' )
	&& false !== strpos( $admin_surface_standard, 'compact package and availability fields plus one combined credit usage row' )
	&& false !== strpos( $boundary_doc, 'compact Nightly Inspection availability/retention plus recent/status/result' )
	&& false !== strpos( $runtime_contract, 'If no addon read contract exists' )
	&& false !== strpos( $readme, 'replacement for the old Toolbox Cloud Checks / Troubleshooting Checks entry' )
	&& false !== strpos( $readme, '`Advanced and troubleshooting > Runtime runs` is the low-frequency home for Nightly Inspection Cloud run' )
	&& false !== strpos( $readme, 'default entitlement projection is limited to nightly-run availability and retention' )
	&& false !== strpos( $readme, 'contract reuse detail' )
	&& false !== strpos( $readme, 'Toolbox owns product buttons' )
	&& false !== strpos( $readme, 'Core owns proposal handoff' )
	&& false !== strpos( $readme, 'Adapter owns execution profiles' )
	&& false !== strpos( $readme, 'Toolkit owns ability contracts' )
	&& false !== strpos( $runtime_contract, 'The normalized projection includes `contract_reuse`' )
	&& false !== strpos( $runtime_contract, '`adds_registry`, `adds_scheduler_truth`, `adds_approval_store`, `adds_queue`' )
	&& false !== strpos( $runtime_contract, 'and `adds_write_executor` false' )
	&& false === strpos( $settings_page, 'register_rest_route' )
	&& false === strpos( $settings_page, 'developer-readonly' )
	&& false === strpos( $settings_page, 'Developer diagnostics route' ),
	'Diagnostics documentation and UI keep the Toolbox Cloud Checks replacement bounded to addon status/detail without Developer routes.'
);

maca_assert(
	false !== strpos( $settings, "LOCAL_DEFAULT_BASE_URL = 'http://localhost:8010/'" )
	&& false !== strpos( $settings, "PRODUCTION_DEFAULT_BASE_URL = 'https://cloud.npc.ink/'" )
	&& false !== strpos( $settings, 'function get_default_base_url' )
	&& false !== strpos( $settings_page, "ACTION_COMPLETE_AUTH = 'npcink_cloud_addon_complete_auth'" )
	&& false !== strpos( $settings_page, "ACTION_START_CUSTOM_AUTH = 'npcink_cloud_addon_start_custom_auth'" )
	&& false !== strpos( $settings_page, "admin_post_' . self::ACTION_COMPLETE_AUTH" )
	&& false !== strpos( $settings_page, "admin_post_' . self::ACTION_START_CUSTOM_AUTH" )
	&& false !== strpos( $settings_page, 'function build_authorization_url' )
	&& false !== strpos( $settings_page, 'function build_authorization_url_for_base_url' )
	&& false !== strpos( $settings_page, "'connect'    => 'wordpress-addon'" )
	&& false !== strpos( $settings_page, '/portal/v1/addon-connections/exchange' )
	&& false !== strpos( $settings_page, 'Add this site in Npcink Cloud' )
	&& false !== strpos( $settings_page, 'persist_and_verify_settings' )
	&& false !== strpos( $settings_page, 'Cloud connection completed and verified.' ),
	'Settings page defaults to Cloud-side site authorization, exchanges the callback key, and verifies the saved connection immediately.'
);

maca_assert(
	false !== strpos( $settings_page, 'function handle_start_custom_auth' )
	&& false !== strpos( $settings_page, "check_admin_referer( self::ACTION_START_CUSTOM_AUTH )" )
	&& false !== strpos( $settings_page, "current_user_can( 'manage_options' )" )
	&& false !== strpos( $settings_page, "\$_POST['self_hosted_base_url']" )
	&& false !== strpos( $settings_page, "build_settings_from_admin_payload(\n\t\t\t\tarray(\n\t\t\t\t\t'base_url' => \$base_url," )
	&& false !== strpos( $settings_page, 'function redirect_to_cloud_authorization' )
	&& false !== strpos( $settings_page, "wp_parse_url( \$authorization_url, PHP_URL_HOST )" )
	&& false !== strpos( $settings_page, "add_filter( 'allowed_redirect_hosts', \$allow_cloud_host )" )
	&& false !== strpos( $settings_page, "wp_safe_redirect( \$authorization_url, 302, 'Npcink Cloud Addon' )" )
	&& false !== strpos( $settings_page, "remove_filter( 'allowed_redirect_hosts', \$allow_cloud_host )" )
	&& false === strpos( $settings_page, 'wp_redirect(' )
	&& false !== strpos( $settings_page, 'class="npcink-cloud-connect-context"' )
	&& false !== strpos( $settings_page, 'target="_blank" rel="noopener noreferrer"' )
	&& false !== strpos( $settings_page, '<details class="npcink-cloud-endpoint-advanced">' )
	&& false !== strpos( $settings_page, 'Advanced connection' )
	&& false !== strpos( $settings_page, 'Self-hosted Cloud endpoint' )
	&& false !== strpos( $settings_page, 'Authorize with this endpoint' )
	&& false !== strpos( $settings_page, 'formtarget="_blank"' )
	&& false !== strpos( $settings_page, 'This does not manage Cloud sites, keys, billing, models, router, workflows, or runtime policy.' )
	&& false !== strpos( $admin_css, '.npcink-cloud-connect-context' )
	&& false !== strpos( $admin_css, '.npcink-cloud-endpoint-advanced' )
	&& false !== strpos( $admin_css, 'max-width: 720px' )
	&& false !== strpos( $admin_css, 'border-left: 3px solid #dcdcde' )
	&& false !== strpos( $admin_surface_standard, 'folded `Advanced connection /' )
	&& false !== strpos( $admin_surface_standard, 'must not save partial credentials before' )
	&& false !== strpos( $admin_surface_standard, 'open in a new browser tab' )
	&& false !== strpos( $admin_surface_standard, 'must not manage Cloud sites, keys, billing, models' ),
	'Self-hosted Cloud endpoint authorization stays folded, nonce-protected, endpoint-only, and outside Cloud object management.'
);

maca_assert(
	false !== strpos( $zh_cn_po, 'msgid "Advanced connection"' )
	&& false !== strpos( $zh_cn_po, 'msgstr "高级连接"' )
	&& false !== strpos( $zh_cn_po, 'msgid "Self-hosted Cloud endpoint"' )
	&& false !== strpos( $zh_cn_po, 'msgstr "自托管 Cloud 端点"' )
	&& false !== strpos( $zh_cn_po, 'msgid "Authorize with this endpoint"' )
	&& false !== strpos( $zh_cn_po, 'msgstr "使用此端点授权"' )
	&& false !== strpos( $zh_cn_po, 'msgstr "仅用于兼容的 Npcink Cloud 部署。Cloud 仍负责站点激活和密钥签发。"' )
	&& false !== strpos( $zh_cn_po, 'msgstr "这里不管理 Cloud 站点、密钥、账单、模型、路由器、工作流或运行时策略。"' ),
	'Self-hosted endpoint connection copy has zh_CN translations for the visible admin UI.'
);

maca_assert(
	false === strpos( $zh_cn_po, "msgstr \"\"\n\n#:" ),
	'Chinese localization has no empty fixed-string translations.'
);

maca_assert(
	false !== strpos( $bootstrap, "require_once __DIR__ . '/class-cloud-addon-localization.php';" )
	&& false !== strpos( $bootstrap, 'Npcink_Cloud_Addon_Localization::register();' )
	&& false !== strpos( $cloud_addon_localization, "TEXT_DOMAIN = 'npcink-cloud-addon'" )
	&& false !== strpos( $cloud_addon_localization, "add_filter( 'gettext', array( __CLASS__, 'filter_gettext' ), 20, 3 )" )
	&& false !== strpos( $cloud_addon_localization, '$translation !== $text' )
	&& false !== strpos( $cloud_addon_localization, "'Advanced connection' => '高级连接'" )
	&& false !== strpos( $cloud_addon_localization, "'Local permissions' => '本地授权'" )
	&& false !== strpos( $cloud_addon_localization, "'Advanced and troubleshooting' => '高级与排查'" )
	&& false !== strpos( $cloud_addon_localization, "'Technical delivery details' => '技术投递详情'" )
	&& false !== strpos( $cloud_addon_localization, "'Site Knowledge' => '站点知识库'" )
	&& false !== strpos( $cloud_addon_localization, "'Allow WordPress AI to use Npcink Cloud.' =>" )
	&& false !== strpos( $cloud_addon_localization, "'More local permissions' => '更多本地授权'" )
	&& false !== strpos( $cloud_addon_localization, "'Bridge health detail' => '桥接健康详情'" )
	&& false !== strpos( $cloud_addon_localization, "'Manual flush command' => '手动刷新命令'" )
	&& false === strpos( $cloud_addon_localization, 'npcink_cloud_addon_runtime_client' )
	&& false === strpos( $cloud_addon_localization, 'wp_remote_' ),
	'Addon zh_CN fallback localization is fixed-string, domain-scoped, and transport-free.'
);

maca_assert(
	false !== strpos( $settings_page, "sanitize_text_field( wp_unslash( \$_POST['runtime_run_id'] ) )" ),
	'Runtime retry admin action sanitizes the submitted run ID before retry dispatch.'
);

maca_assert(
	false !== strpos( $runtime_client, '$successful_envelope_statuses' )
	&& false !== strpos( $runtime_client, "'ready'" )
	&& false !== strpos( $runtime_client, "'submitted'" )
	&& false !== strpos( $runtime_client, '! in_array( $envelope_status, $successful_envelope_statuses, true )' ),
	'Runtime client accepts Cloud runtime success lifecycle statuses instead of requiring only ok.'
);

maca_assert(
	false !== strpos( $public_onboarding_doc, 'https://cloud.npc.ink/' )
	&& false !== strpos( $public_onboarding_doc, '/portal/sites' )
	&& false !== strpos( $public_onboarding_doc, '/portal/v1/addon-connections/exchange' )
	&& false !== strpos( $public_onboarding_doc, 'must not become a Cloud operations console or a second control plane' )
	&& false !== strpos( $public_onboarding_doc, 'must not send prompts, generated content' )
	&& false !== strpos( $readme, 'docs/public-cloud-onboarding-checklist.md' ),
	'Public Cloud onboarding checklist keeps production default, Portal authorization, metadata-only monitoring, and addon boundary checks explicit.'
);

maca_assert(
	false !== strpos( $settings_page, "ACTION_DISCONNECT = 'npcink_cloud_addon_disconnect'" )
	&& false !== strpos( $settings_page, "admin_post_' . self::ACTION_DISCONNECT" )
	&& false !== strpos( $settings_page, 'function handle_disconnect' )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Entitlement_Summary::delete_cached_summary' )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Addon_Settings::delete_settings()' )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Observability_Collector::delete_data()' )
	&& false !== strpos( $settings_page, 'Npcink_Cloud_Site_Knowledge_Change_Bridge::delete_data()' )
	&& false !== strpos( $settings_page, 'Change connection in Cloud' )
	&& false !== strpos( $settings_page, 'Open Cloud sites' )
	&& false !== strpos( $settings_page, 'function render_connection_actions( array $settings, bool $is_verified )' )
	&& false !== strpos( $settings_page, 'if ( $is_verified )' )
	&& false !== strpos( $settings_page, 'Disconnect locally' )
	&& false === strpos( $settings_page, 'Site ID' )
	&& false === strpos( $settings_page, 'Key ID' )
	&& false !== strpos( $settings_page, 'Recovery Cloud API Key' )
	&& false !== strpos( $settings_page, 'Cloud-issued mak1_ wrapper' )
	&& false === strpos( $settings_page, 'JSON key' )
	&& false === strpos( $settings_page, 'revoke' ),
	'Settings page exposes Cloud-side change links and local disconnect actions without showing split credential identifiers or key-management controls.'
);

maca_assert(
	false !== strpos( $entitlement_summary, 'function delete_cached_summary' )
	&& false !== strpos( $entitlement_summary, 'delete_transient( self::cache_key( $settings ) )' ),
	'Entitlement summary cache can be cleared when the local Cloud connection is disconnected.'
);

maca_assert(
	false !== strpos( $admin_surface_standard, 'Time Display' )
	&& false !== strpos( $admin_surface_standard, 'WordPress site timezone' )
	&& false !== strpos( $admin_surface_standard, 'Y-m-d H:i:s' )
	&& false !== strpos( $admin_surface_standard, 'Do not print raw UTC strings' ),
	'Admin surface standard documents WordPress-time display for human-facing timestamps.'
);

maca_assert(
	false !== strpos( $complexity_doc, 'security and boundary complexity' )
	&& false !== strpos( $complexity_doc, 'product-control complexity' )
	&& false !== strpos( $complexity_doc, 'Is this transport/detail, or is it control/write truth?' )
	&& false !== strpos( $complexity_doc, 'tests/static-contracts.php' )
	&& false !== strpos( $complexity_doc, 'tests/behavior-media-derivative.php' ),
	'Complexity budget document records what complexity is worth keeping and where tests belong.'
);

maca_assert(
	false !== strpos( $uninstall, "delete_option( 'npcink_cloud_addon_agent_feedback_summary' )" )
	&& false !== strpos( $uninstall, "delete_option( 'npcink_cloud_addon_site_knowledge_maintenance_cursor' )" ),
	'Uninstall removes all addon-owned bounded summary and Site Knowledge maintenance state.'
);

foreach ( array( 'npcink-governance-core', 'npcink-abilities-toolkit', 'npcink-ai-client-adapter', 'npcink-workflow-toolbox', 'npcink-cloud-addon', 'npcink-ai-cloud' ) as $contract_reuse_repo_name ) {
	maca_assert(
		false !== strpos( $contract_reuse_readiness_doc, $contract_reuse_repo_name ),
		'Cloud Addon contract reuse readiness includes repo: ' . $contract_reuse_repo_name
	);
}

foreach ( array( 'Cloud Addon Contract Reuse Readiness', 'signed_transport', 'ability_contracts', 'proposal_handoff', 'execution_profiles', 'product_surface', 'runtime_detail', 'Reference-Plugin Learning', 'Jetpack', 'Site Kit by Google', 'WP Mail SMTP', 'Health Check & Troubleshooting', 'WordPress Application Passwords', 'No new Cloud Addon endpoint', 'workflow runtime', 'scheduler truth', 'WordPress write executor is needed for this pass', 'contract_reuse', 'mak1_{base64url(json)}', 'POST /v1/runtime/execute', 'POST /v1/runtime/media/uploads', 'POST /v1/runtime/media/jobs', 'GET /v1/runs/{run_id}', 'GET /v1/runs/{run_id}/result', 'GET /v1/runs/nightly-inspection/recent', 'POST /v1/runs/{run_id}/retry', 'GET /v1/runtime/media/artifacts/{artifact_id}/download', 'POST /v1/runtime/media/artifacts/{artifact_id}/delivery-ack', 'GET /v1/entitlements/current', 'POST /v1/observability/plugin-events', 'GET /v1/observability/plugin-summary', 'POST /v1/agent-feedback/events', 'GET /v1/agent-feedback/summary', 'site_knowledge_change_bridge_status.v1', 'cloud_connector_runtime.v1', 'wordpress_operation.v1', 'cloud_connector_result.v1', 'image_context_evidence_request.v1', 'cloud_agent_feedback.v1', 'Stop and write a boundary note or ADR', 'generic Cloud proxy routes', 'raw request/response', 'provider credentials', 'npcink-ai-cloud', 'composer run test:all' ) as $required_contract_reuse_text ) {
	maca_assert(
		false !== strpos( $contract_reuse_readiness_doc, $required_contract_reuse_text ),
		'Cloud Addon contract reuse readiness preserves: ' . $required_contract_reuse_text
	);
}

maca_assert(
	false !== strpos( $readme, 'docs/cloud-addon-reference-notes-2026-07.md' )
	&& false !== strpos( $readme, 'docs/cloud-addon-contract-reuse-readiness-2026-07-08.md' ),
	'README links Cloud Addon reference-plugin learning notes and contract reuse readiness.'
);
