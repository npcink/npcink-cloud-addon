<?php
/** Deterministic acceptance regressions; no Provider calls or content writes. */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/wordpress-ai-client-stubs.php';
require_once dirname( __DIR__ ) . '/includes/class-cloud-wordpress-ai-connector.php';
require_once dirname( __DIR__ ) . '/scripts/wordpress-ai-provider-acceptance-functions.php';

if ( ! function_exists( 'parse_blocks' ) ) {
	function parse_blocks( $content ) {
		preg_match_all( '/<!-- wp:[^>]+-->/', (string) $content, $matches );
		return array_fill( 0, count( $matches[0] ), array() );
	}
}

$acceptance_smoke_source = (string) file_get_contents( dirname( __DIR__ ) . '/scripts/smoke-wordpress-ai-provider-acceptance.php' );
$quality_runner_source = (string) file_get_contents( dirname( __DIR__ ) . '/scripts/evaluate-wordpress-ai-provider-acceptance.sh' );
maca_assert(
	false !== strpos( $acceptance_smoke_source, "'contract_source'" )
	&& false !== strpos( $acceptance_smoke_source, "'contract_status'" )
	&& false !== strpos( $acceptance_smoke_source, "'verification_state'" )
	&& false !== strpos( $acceptance_smoke_source, "'input_fingerprint'" )
	&& false !== strpos( $acceptance_smoke_source, "'input_fields'" )
	&& false !== strpos( $acceptance_smoke_source, "'scenario_id'" )
	&& false !== strpos( $acceptance_smoke_source, 'classification-post-tag-existing-only' )
	&& false !== strpos( $acceptance_smoke_source, 'classification-category-existing-only' )
	&& false !== strpos( $acceptance_smoke_source, 'content-translation-blocks-en-us' )
	&& false !== strpos( $acceptance_smoke_source, 'slug-generation-mixed-language' )
	&& false !== strpos( $acceptance_smoke_source, "'quality_context'" )
	&& false !== strpos( $acceptance_smoke_source, 'cloud_run_id_from_response' )
	&& false !== strpos( $acceptance_smoke_source, 'current_cloud_run_id' )
	&& false !== strpos( $acceptance_smoke_source, "'commentmeta'" )
	&& false !== strpos( $acceptance_smoke_source, "'commentmeta_fingerprint'" )
	&& false !== strpos( $acceptance_smoke_source, 'WP_AI_ACCEPTANCE_ALLOW_COMMENT_METADATA_WRITE' )
	&& false !== strpos( $acceptance_smoke_source, "'preflight'" )
	&& false !== strpos( $acceptance_smoke_source, 'quota_exhausted' )
	&& false !== strpos( $acceptance_smoke_source, 'npcink_cloud_acceptance_quota_preflight' ),
	'Acceptance reports expose contract provenance and verification state for development diagnostics.'
);
maca_assert(
	false !== strpos( $quality_runner_source, 'acceptance:wp-ai-provider' )
	&& false !== strpos( $quality_runner_source, 'wordpress-ai-provider/evaluate.php' )
	&& false !== strpos( $quality_runner_source, 'wordpress-ai-provider/run.php' )
	&& false !== strpos( $quality_runner_source, 'capability-matrix.v1.json' )
	&& false !== strpos( $quality_runner_source, 'WP_AI_ACCEPTANCE_ABILITIES' )
	&& false !== strpos( $quality_runner_source, 'allow_partial=1' )
	&& false !== strpos( $quality_runner_source, 'WP_AI_ACCEPTANCE_INPUT' )
	&& false !== strpos( $quality_runner_source, 'human_review_required' ),
	'Combined WordPress AI acceptance runner applies the Eval Lab capability gate before quality review and preserves bounded partial diagnostics.'
);
$gate_probe_root = sys_get_temp_dir() . '/npcink-wp-ai-gate-' . bin2hex( random_bytes( 4 ) );
$gate_probe_eval = $gate_probe_root . '/eval-lab/wordpress-ai-provider';
mkdir( $gate_probe_eval, 0777, true );
$gate_probe_marker = $gate_probe_root . '/quality-called';
$gate_probe_stdout = $gate_probe_root . '/gate.json.stdout';
file_put_contents( $gate_probe_root . '/input.json', '{}' );
file_put_contents( $gate_probe_root . '/matrix.json', '{}' );
file_put_contents( $gate_probe_eval . '/run.php', "<?php fwrite(STDERR, 'synthetic gate failure\\n'); exit(7);" );
file_put_contents( $gate_probe_eval . '/evaluate.php', "<?php file_put_contents(" . var_export( $gate_probe_marker, true ) . ", 'called'); exit(0);" );
$gate_probe_command = 'NPCINK_EVAL_LAB_PATH=' . escapeshellarg( dirname( $gate_probe_eval ) )
	. ' WP_AI_ACCEPTANCE_INPUT=' . escapeshellarg( $gate_probe_root . '/input.json' )
	. ' WP_AI_ACCEPTANCE_MATRIX=' . escapeshellarg( $gate_probe_root . '/matrix.json' )
	. ' WP_AI_ACCEPTANCE_REPORT=' . escapeshellarg( $gate_probe_root . '/acceptance.json' )
	. ' WP_AI_ACCEPTANCE_GATE_REPORT=' . escapeshellarg( $gate_probe_root . '/gate.json' )
	. ' WP_AI_ACCEPTANCE_QUALITY_REPORT=' . escapeshellarg( $gate_probe_root . '/quality.json' )
	. ' bash ' . escapeshellarg( dirname( __DIR__ ) . '/scripts/evaluate-wordpress-ai-provider-acceptance.sh' )
	. ' >' . escapeshellarg( $gate_probe_root . '/runner.stdout' ) . ' 2>&1';
exec( $gate_probe_command, $gate_probe_output, $gate_probe_status );
$gate_probe_evidence = is_file( $gate_probe_stdout ) ? (string) file_get_contents( $gate_probe_stdout ) : '';
maca_assert(
	7 === $gate_probe_status
	&& false !== strpos( $gate_probe_evidence, 'synthetic gate failure' )
	&& ! is_file( $gate_probe_marker ),
	'Acceptance runner stops on a capability-gate failure, preserves gate diagnostics, and skips quality evaluation.'
);
@unlink( $gate_probe_root . '/input.json' );
@unlink( $gate_probe_root . '/matrix.json' );
@unlink( $gate_probe_root . '/acceptance.json' );
@unlink( $gate_probe_root . '/gate.json' );
@unlink( $gate_probe_root . '/gate.json.stdout' );
@unlink( $gate_probe_root . '/quality.json' );
@unlink( $gate_probe_root . '/runner.stdout' );
@unlink( $gate_probe_marker );
@unlink( $gate_probe_eval . '/run.php' );
@unlink( $gate_probe_eval . '/evaluate.php' );
@rmdir( $gate_probe_eval );
@rmdir( dirname( $gate_probe_eval ) );
@rmdir( $gate_probe_root );
maca_assert(
	false !== strpos( $acceptance_smoke_source, "null !== \$case['failure_code']" )
	&& false !== strpos( $acceptance_smoke_source, "npcink_cloud_acceptance_attach_failure_evidence( \$case, \$failure_evidence )" ),
	'Acceptance failure evidence is attached only to the failing case and normalizes absent run IDs.'
);
maca_assert(
	false !== strpos( $acceptance_smoke_source, "record_cloud_run_id( '' )" )
	&& false !== strpos( $acceptance_smoke_source, 'reset_runtime_failure_evidence' ),
	'Acceptance clears request-scoped run and failure evidence before every case.'
);

Npcink_Cloud_WordPress_AI_Connector::reset_runtime_failure_evidence();
$runtime_evidence = Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_from_wp_error(
	new WP_Error(
		'cloud_wp_ai_connector_request_too_large',
		'bounded runtime failure',
		array(
			'cloud_error_code' => 'cloud_wp_ai_connector_request_too_large',
			'cloud_error_data' => array(
				'run_id'        => 'run_acceptance_123',
				'error_stage'   => 'runtime',
				'quality_reason' => "请求过大\xB1仍应保留可诊断信息",
			),
		)
	)
);
$runtime_report_evidence = Npcink_Cloud_WordPress_AI_Connector::current_runtime_failure_evidence();
maca_assert(
	'run_acceptance_123' === ( $runtime_evidence['run_id'] ?? null )
	&& 'cloud_wp_ai_connector_request_too_large' === ( $runtime_evidence['cloud_error_code'] ?? null )
	&& 'runtime' === ( $runtime_evidence['error_stage'] ?? null )
	&& '' !== ( $runtime_report_evidence['quality_reason'] ?? '' )
	&& false !== json_encode( $runtime_report_evidence, JSON_UNESCAPED_UNICODE ),
	'Runtime failure evidence preserves the Cloud run ID, dotted code, stage, and valid UTF-8 diagnostics.'
);
maca_assert(
	'validation/failed[retry]' === Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_from_wp_error(
		new WP_Error(
			'cloud_wp_ai_connector_diagnostic',
			'bounded runtime failure',
			array(
				'cloud_error_data' => array( 'error_stage' => 'validation/failed[retry]' ),
			)
		)
	)['error_stage'],
	'Runtime failure evidence preserves common punctuation in Cloud diagnostic stages.'
);
maca_assert( '中文诊断' === wp_check_invalid_utf8( '中文诊断' ), 'Pure-PHP UTF-8 guard preserves valid non-ASCII diagnostics.' );
maca_assert(
	'run_nested_123' === Npcink_Cloud_WordPress_AI_Connector::cloud_run_id_from_response( array( 'data' => array( 'result' => array( 'run_id' => 'run_nested_123' ) ) ) ),
	'Cloud run ID extraction accepts the nested runtime result envelope used by the connector log bridge.'
);
Npcink_Cloud_WordPress_AI_Connector::reset_runtime_failure_evidence();
Npcink_Cloud_WordPress_AI_Connector::record_runtime_failure_evidence( array( 'quality_reason' => array( 'nested' => true ) ) );
maca_assert(
	array() === Npcink_Cloud_WordPress_AI_Connector::current_runtime_failure_evidence(),
	'Runtime failure evidence ignores non-scalar diagnostic fields without emitting array-cast warnings.'
);
$evidence_case = npcink_cloud_acceptance_attach_failure_evidence(
	array(
		'provider_run_id' => null,
		'failure_stage'   => null,
		'quality_reason'  => null,
		'cloud_error_code' => null,
	),
	array( 'run_id' => 'run_case_123', 'error_stage' => 'output_validation' )
);
maca_assert(
	'run_case_123' === $evidence_case['provider_run_id']
	&& 'output_validation' === $evidence_case['failure_stage']
	&& null === $evidence_case['quality_reason']
	&& null === $evidence_case['cloud_error_code'],
	'Acceptance failure evidence uses null for absent optional fields.'
);
maca_assert( 'contract_drift' === npcink_cloud_acceptance_contract_status( new WP_Error( 'cloud_ai_task_schema_hash_mismatch', 'drift' ) ), 'Acceptance classifies schema drift separately from provider failures.' );

$quota_exhausted = npcink_cloud_acceptance_quota_preflight_from_response(
	array(
		'data' => array(
			'quota_summary' => array(
				'ai_credit_usage_detail' => array(
					'summary' => array( 'used' => 301, 'limit' => 301, 'remaining' => 0, 'unit' => 'ai_credits' ),
				),
			),
		),
	)
);
$quota_available = npcink_cloud_acceptance_quota_preflight_from_response(
	array(
		'data' => array(
			'quota_summary' => array(
				'ai_credit_usage_detail' => array(
					'summary' => array( 'used' => 10, 'limit' => 100, 'remaining' => 90, 'unit' => 'ai_credits' ),
				),
			),
		),
	)
);
$quota_invalid = npcink_cloud_acceptance_quota_preflight_from_response(
	array(
		'data' => array(
			'quota_summary' => array(
				'ai_credit_usage_detail' => array(
					'summary' => array( 'used' => 10, 'limit' => 100, 'remaining' => 90, 'unit' => 'credits' ),
				),
			),
		),
	)
);
maca_assert( 'quota_exhausted' === $quota_exhausted['state'] && 0.0 === $quota_exhausted['remaining'], 'Acceptance preflight detects exhausted AI credits from the bounded entitlement summary.' );
maca_assert( 'available' === $quota_available['state'] && 90.0 === $quota_available['remaining'], 'Acceptance preflight allows Provider calls when bounded AI credits remain.' );
maca_assert( 'unavailable' === $quota_invalid['state'] && 'invalid_entitlement_summary' === $quota_invalid['error_code'], 'Acceptance preflight treats an invalid entitlement unit as unknown without exposing raw data.' );
$quota_error = npcink_cloud_acceptance_quota_preflight_from_response( new WP_Error( 'cloud_runtime_unconfigured', 'private diagnostics', array( 'secret' => 'must-not-appear' ) ) );
maca_assert( 'unavailable' === $quota_error['state'] && 'cloud_runtime_unconfigured' === $quota_error['error_code'] && false === strpos( json_encode( $quota_error ), 'must-not-appear' ), 'Acceptance preflight retains only the error code, never raw error messages or payloads.' );
$quota_missing = npcink_cloud_acceptance_quota_preflight_from_response( array() );
maca_assert( 'unavailable' === $quota_missing['state'] && null === $quota_missing['remaining'], 'Acceptance preflight does not infer exhausted quota from a missing summary.' );
$quota_non_numeric = npcink_cloud_acceptance_quota_preflight_from_response(
	array( 'data' => array( 'quota_summary' => array( 'ai_credit_usage_detail' => array( 'summary' => array( 'used' => 'n/a', 'limit' => 100, 'remaining' => 90, 'unit' => 'ai_credits' ) ) ) ) )
);
maca_assert( 'unavailable' === $quota_non_numeric['state'] && 'invalid_entitlement_summary' === $quota_non_numeric['error_code'], 'Acceptance preflight rejects non-numeric entitlement totals.' );
$quota_unknown_remaining = npcink_cloud_acceptance_quota_preflight_from_response(
	array( 'data' => array( 'quota_summary' => array( 'ai_credit_usage_detail' => array( 'summary' => array( 'used' => 10, 'limit' => 100, 'remaining' => null, 'unit' => 'ai_credits' ) ) ) ) )
);
maca_assert( 'unavailable' === $quota_unknown_remaining['state'] && 'entitlement_remaining_unknown' === $quota_unknown_remaining['error_code'], 'Acceptance preflight distinguishes an unknown remaining balance from malformed totals.' );

// Run the actual WP-CLI script in an isolated PHP host. Any Ability execution
// exits immediately, so the assertion proves the preflight bypasses transport.
$quota_probe_file = tempnam( sys_get_temp_dir(), 'wp-ai-quota-probe-' );
$quota_probe_source = <<<'PHP'
<?php
require $argv[1] . '/tests/helpers.php';
function get_user_by( $field, $value ) { return (object) array( 'ID' => 1 ); }
function wp_set_current_user( $id ) {}
function wp_get_ability( $ability ) { return true; }
function rest_do_request( $request ) { fwrite( STDERR, 'unexpected Ability execution' ); exit(42); }
class Npcink_Cloud_AI_Task_Contract {
    public static function project_registered_ability( $ability ) {
        if ( 'ai/title-generation' === $ability ) { return new WP_Error( 'contract_drift' ); }
        return array( 'task' => 'text', 'contract_version' => 'v1', 'verification_state' => 'mapping_current' );
    }
}
function npcink_cloud_addon_get_toolbox_runtime_entitlement( $trace_id ) {
    return array( 'data' => array( 'quota_summary' => array( 'ai_credit_usage_detail' => array(
        'summary' => array( 'used' => 301, 'limit' => 301, 'remaining' => 0, 'unit' => 'ai_credits' )
    ) ) ) );
}
foreach ( array( 'WP_AI_ACCEPTANCE_ABILITIES', 'WP_AI_ACCEPTANCE_COMMENT_ID', 'WP_AI_ACCEPTANCE_ALT_TEXT_ATTACHMENT_ID', 'WP_AI_ACCEPTANCE_IMAGE_GENERATION' ) as $key ) { putenv( $key ); }
require $argv[1] . '/scripts/smoke-wordpress-ai-provider-acceptance.php';
PHP;
file_put_contents( $quota_probe_file, $quota_probe_source );
exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $quota_probe_file ) . ' ' . escapeshellarg( dirname( __DIR__ ) ), $quota_probe_lines, $quota_probe_status );
unlink( $quota_probe_file );
$quota_probe_report = json_decode( implode( "\n", $quota_probe_lines ), true );
$quota_probe_cases = $quota_probe_report['cases'] ?? array();
$quota_blocked_cases = array_filter( $quota_probe_cases, static function ( $case ) { return 'quota_exhausted' === ( $case['failure_code'] ?? null ); } );
maca_assert( 2 === $quota_probe_status && 14 === count( $quota_probe_cases ) && 13 === count( $quota_blocked_cases ), 'An exhausted-quota batch skips every Provider call but still detects a contract failure.' );
maca_assert( 'not_executed' === $quota_probe_cases[0]['execution_state'] && 'not_evaluated' === $quota_probe_cases[0]['quality_status'] && null === $quota_probe_cases[0]['http_status'] && null === $quota_probe_cases[0]['cloud_error_code'] && null === $quota_probe_cases[0]['provider_run_id'], 'Preflight-blocked evidence never invents HTTP, Cloud execution, or quality results.' );

maca_assert( 'task_not_completed' === npcink_cloud_acceptance_quality_failure( 'ai/editorial-updates', "Please provide the original paragraph you'd like revised." ), 'Acceptance rejects the observed request-for-source reply.' );
maca_assert( null === npcink_cloud_acceptance_quality_failure( 'ai/editorial-updates', 'The revised paragraph is clearer.' ), 'Acceptance keeps an ordinary completed edit eligible for review.' );
maca_assert( 'editorial_empty' === npcink_cloud_acceptance_quality_failure( 'ai/editorial-notes', array( 'suggestions' => array() ) ), 'Acceptance distinguishes empty editorial suggestions from runtime failure.' );
maca_assert( 'taxonomy_empty' === npcink_cloud_acceptance_quality_failure( 'ai/content-classification', array( 'suggestions' => array() ) ), 'Acceptance distinguishes empty taxonomy suggestions from runtime failure.' );
maca_assert( npcink_cloud_acceptance_is_empty_taxonomy_error( 'ai/content-classification', array( 'code' => 'no_results', 'message' => 'No taxonomy suggestions were generated.' ) ) && 'taxonomy_empty' === npcink_cloud_acceptance_quality_failure( 'ai/content-classification', array( 'code' => 'no_results' ) ), 'Acceptance classifies the official no-results taxonomy response as an empty semantic result.' );
maca_assert( 'slug_format_invalid' === npcink_cloud_acceptance_quality_failure( 'ai/slug-generation', array( 'slugs' => array( 'valid-slug', 'Invalid slug' ) ) ), 'Acceptance checks every slug, not only the first.' );
maca_assert( null === npcink_cloud_acceptance_quality_failure( 'ai/slug-generation', array( 'slugs' => array( 'first-slug', 'second-slug' ) ) ), 'Acceptance allows valid slug candidates.' );
maca_assert( 'slug_duplicate' === npcink_cloud_acceptance_quality_failure( 'ai/slug-generation', array( 'slugs' => array( 'same-slug', 'same-slug' ) ) ), 'Acceptance rejects duplicate slug candidates.' );
maca_assert( 'target_language_mismatch' === npcink_cloud_acceptance_quality_failure( 'ai/content-translation', '这是未翻译的固定样本。' ), 'Acceptance rejects untranslated Chinese for the English target fixture.' );
maca_assert( false === npcink_cloud_acceptance_shape_valid( array(), array() ), 'Acceptance fails closed without an output schema.' );

maca_assert( 'slug_format_invalid' === npcink_cloud_acceptance_quality_failure( 'ai/slug-generation', array( 'slugs' => array( '1', '2' ) ) ), 'Acceptance rejects numeric-only slug candidates.' );
maca_assert( 'task_not_completed' === npcink_cloud_acceptance_quality_failure( 'ai/editorial-updates', 'No paragraph was provided for review. Please paste the text you want refined.' ), 'Acceptance rejects missing-source editorial replies.' );
maca_assert( 'classification_suggestion_invalid' === npcink_cloud_acceptance_quality_failure( 'ai/content-classification', array( 'suggestions' => array( array( 'term' => '' ) ) ) ), 'Acceptance rejects empty classification terms.' );
maca_assert( 'classification_confidence_invalid' === npcink_cloud_acceptance_quality_failure( 'ai/content-classification', array( 'suggestions' => array( array( 'term' => 'WordPress', 'confidence' => 1.5 ) ) ) ), 'Acceptance rejects out-of-range classification confidence.' );
maca_assert( null === npcink_cloud_acceptance_quality_failure( 'ai/content-classification', array( 'suggestions' => array( array( 'term' => 'WordPress', 'confidence' => 0.9 ) ) ), array( 'strategy' => 'existing_only', 'max_suggestions' => 3 ) ), 'Acceptance allows bounded classification suggestions.' );
maca_assert( 'classification_too_many' === npcink_cloud_acceptance_quality_failure( 'ai/content-classification', array( 'suggestions' => array( array( 'term' => 'One' ), array( 'term' => 'Two' ) ) ), array( 'strategy' => 'existing_only', 'max_suggestions' => 1 ) ), 'Acceptance rejects classification output above the requested suggestion limit.' );
maca_assert( 'classification_new_term' === npcink_cloud_acceptance_quality_failure( 'ai/content-classification', array( 'suggestions' => array( array( 'term' => 'New term', 'is_new' => true ) ) ), array( 'strategy' => 'existing_only', 'max_suggestions' => 3 ) ), 'Acceptance rejects new taxonomy terms when existing-only strategy is requested.' );
maca_assert( 'alt_text_missing' === npcink_cloud_acceptance_quality_failure( 'ai/alt-text-generation', array( 'alt_text' => '', 'is_decorative' => false ) ), 'Acceptance rejects a non-decorative image without alternative text.' );
maca_assert( 'image_prompt_empty' === npcink_cloud_acceptance_quality_failure( 'ai/image-prompt-generation', '' ), 'Acceptance rejects an empty image prompt.' );
$image_artifact = array(
	'contract_version'      => 'image_generation_result.v1',
	'artifact_type'         => 'image_generation_artifacts',
	'operation'             => 'image.generate.v1',
	'suggestion_only'       => true,
	'requires_local_review' => true,
	'artifacts'             => array(
		array(
			'artifact_id'        => 'art_' . str_repeat( 'c', 32 ),
			'artifact_reference' => array( 'artifact_id' => 'art_' . str_repeat( 'c', 32 ) ),
			'status'             => 'available',
			'media_kind'         => 'image',
			'operation'          => 'image.generate.v1',
			'content_type'       => 'image/png',
			'width'              => 1024,
			'height'             => 1024,
			'filesize_bytes'     => 2048,
			'checksum'           => 'sha256:' . str_repeat( 'd', 64 ),
		),
	),
);
maca_assert( null === npcink_cloud_acceptance_quality_failure( 'ai/image-generation', $image_artifact ), 'Acceptance allows bounded image-generation Artifact results.' );
$image_artifact['artifacts'][0]['b64_json'] = 'inline-must-fail';
maca_assert( 'image_generation_artifact_invalid' === npcink_cloud_acceptance_quality_failure( 'ai/image-generation', $image_artifact ), 'Acceptance rejects inline image-generation media.' );
$media_context = npcink_cloud_acceptance_quality_context( 'ai/alt-text-generation', array( 'attachment_id' => 123, 'context' => 'Describe the subject.' ), array( 'alt_text' => 'A subject.', 'is_decorative' => false ) );
maca_assert( 'attachment' === ( $media_context['media_input_kind'] ?? null ) && true === ( $media_context['has_context'] ?? false ), 'Acceptance records media input posture without exposing the image or URL.' );
$quality_context = npcink_cloud_acceptance_quality_context(
	'ai/content-translation',
	array( 'content' => '<!-- wp:paragraph --><p>One</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->', 'target_language' => 'en-us' ),
	'<!-- wp:paragraph --><p>One</p><!-- /wp:paragraph --><!-- wp:paragraph --><p>Two</p><!-- /wp:paragraph -->'
);
maca_assert( 'en-us' === ( $quality_context['target_language'] ?? null ) && 'gutenberg_blocks' === ( $quality_context['structure_kind'] ?? null ) && 2 === (int) ( $quality_context['expected_block_count'] ?? 0 ) && 2 === (int) ( $quality_context['translated_block_count'] ?? 0 ), 'Acceptance records target language and block-count evidence without recording article text.' );
$missing_block_context = npcink_cloud_acceptance_quality_context( 'ai/content-translation', array( 'content' => '<!-- wp:paragraph --><p>One</p><!-- /wp:paragraph -->', 'target_language' => 'en-us' ), 'Translated text without block markers.' );
maca_assert( 1 === (int) ( $missing_block_context['expected_block_count'] ?? 0 ) && 0 === (int) ( $missing_block_context['translated_block_count'] ?? -1 ), 'Acceptance records a missing translated block marker as zero blocks instead of unknown.' );
$plain_quality_context = npcink_cloud_acceptance_quality_context( 'ai/content-translation', array( 'content' => '这是一个普通文本段落。', 'target_language' => 'en-us' ), 'This is a plain text paragraph.' );
maca_assert( 'plain_text' === ( $plain_quality_context['structure_kind'] ?? null ) && ! array_key_exists( 'expected_block_count', $plain_quality_context ), 'Acceptance distinguishes plain-text translation from block-structured translation.' );

$good = array( 'quality_status' => 'passed', 'write_detected' => false );
$bad = array( 'quality_status' => 'failed', 'write_detected' => false );
$mixed = npcink_cloud_acceptance_finalize_report( array( 'write_detected' => false, 'cases' => array( $good, $bad ) ) );
maca_assert( 'local_failed' === $mixed['evidence_state'] && 1 === $mixed['passed'] && 1 === $mixed['failed'], 'Acceptance fails the batch when one ability fails.' );
maca_assert( 'local_verified' === $mixed['cases'][0]['evidence_state'] && 'local_failed' === $mixed['cases'][1]['evidence_state'], 'Acceptance preserves a passing ability when a different ability fails.' );
$unknown = npcink_cloud_acceptance_finalize_report( array( 'cases' => array( array( 'quality_status' => 'passed' ) ) ) );
maca_assert( 'local_executed' === $unknown['evidence_state'] && 'local_executed' === $unknown['cases'][0]['evidence_state'], 'Acceptance does not verify a result without write evidence.' );
$failed_unknown = npcink_cloud_acceptance_finalize_report( array( 'cases' => array( array( 'quality_status' => 'failed' ) ) ) );
maca_assert( 'local_failed' === $failed_unknown['evidence_state'], 'Acceptance preserves known failure even when write evidence is missing.' );
$write = npcink_cloud_acceptance_finalize_report( array( 'write_detected' => true, 'cases' => array( array( 'quality_status' => 'passed', 'write_detected' => true ) ) ) );
maca_assert( 'local_failed' === $write['evidence_state'] && 'local_failed' === $write['cases'][0]['evidence_state'], 'Acceptance rejects writes even when output quality passes.' );
$empty = npcink_cloud_acceptance_finalize_report( array( 'write_detected' => false, 'cases' => array() ) );
maca_assert( 'local_failed' === $empty['evidence_state'] && 'failed' === $empty['quality_status'], 'Acceptance cannot pass an empty capability list.' );
$all_good = npcink_cloud_acceptance_finalize_report( array( 'write_detected' => false, 'cases' => array( $good, $good ) ) );
maca_assert( 'local_verified' === $all_good['evidence_state'] && 2 === $all_good['passed'], 'Acceptance verifies the batch only when every case has passing evidence.' );
Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( '' );
Npcink_Cloud_WordPress_AI_Connector::reset_runtime_failure_evidence();
