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
	&& false !== strpos( $acceptance_smoke_source, 'current_cloud_run_id' ),
	'Acceptance reports expose contract provenance and verification state for development diagnostics.'
);
maca_assert(
	false !== strpos( $quality_runner_source, 'acceptance:wp-ai-provider' )
	&& false !== strpos( $quality_runner_source, 'wordpress-ai-provider/evaluate.php' )
	&& false !== strpos( $quality_runner_source, 'WP_AI_ACCEPTANCE_INPUT' )
	&& false !== strpos( $quality_runner_source, 'human_review_required' ),
	'Combined WordPress AI acceptance runner preserves the WP-CLI, Eval Lab, offline fixture, and review-state boundaries.'
);
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
