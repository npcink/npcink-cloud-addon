<?php
/** Deterministic acceptance regressions; no Provider calls or content writes. */
require_once __DIR__ . '/helpers.php';
require_once dirname( __DIR__ ) . '/scripts/wordpress-ai-provider-acceptance-functions.php';

maca_assert( 'task_not_completed' === npcink_cloud_acceptance_quality_failure( 'ai/editorial-updates', "Please provide the original paragraph you'd like revised." ), 'Acceptance rejects the observed request-for-source reply.' );
maca_assert( null === npcink_cloud_acceptance_quality_failure( 'ai/editorial-updates', 'The revised paragraph is clearer.' ), 'Acceptance keeps an ordinary completed edit eligible for review.' );
maca_assert( 'empty_result' === npcink_cloud_acceptance_quality_failure( 'ai/editorial-notes', array( 'suggestions' => array() ) ), 'Acceptance rejects empty suggestions for the deliberately defective fixture.' );
maca_assert( 'slug_format_invalid' === npcink_cloud_acceptance_quality_failure( 'ai/slug-generation', array( 'slugs' => array( 'valid-slug', 'Invalid slug' ) ) ), 'Acceptance checks every slug, not only the first.' );
maca_assert( null === npcink_cloud_acceptance_quality_failure( 'ai/slug-generation', array( 'slugs' => array( 'first-slug', 'second-slug' ) ) ), 'Acceptance allows valid slug candidates.' );
maca_assert( 'target_language_mismatch' === npcink_cloud_acceptance_quality_failure( 'ai/content-translation', '这是未翻译的固定样本。' ), 'Acceptance rejects untranslated Chinese for the English target fixture.' );
maca_assert( false === npcink_cloud_acceptance_shape_valid( array(), array() ), 'Acceptance fails closed without an output schema.' );

maca_assert( 'slug_format_invalid' === npcink_cloud_acceptance_quality_failure( 'ai/slug-generation', array( 'slugs' => array( '1', '2' ) ) ), 'Acceptance rejects numeric-only slug candidates.' );
maca_assert( 'task_not_completed' === npcink_cloud_acceptance_quality_failure( 'ai/editorial-updates', 'No paragraph was provided for review. Please paste the text you want refined.' ), 'Acceptance rejects missing-source editorial replies.' );
maca_assert( 'classification_suggestion_invalid' === npcink_cloud_acceptance_quality_failure( 'ai/content-classification', array( 'suggestions' => array( array( 'term' => '' ) ) ) ), 'Acceptance rejects empty classification terms.' );
maca_assert( 'classification_confidence_invalid' === npcink_cloud_acceptance_quality_failure( 'ai/content-classification', array( 'suggestions' => array( array( 'term' => 'WordPress', 'confidence' => 1.5 ) ) ) ), 'Acceptance rejects out-of-range classification confidence.' );
maca_assert( null === npcink_cloud_acceptance_quality_failure( 'ai/content-classification', array( 'suggestions' => array( array( 'term' => 'WordPress', 'confidence' => 0.9 ) ) ) ), 'Acceptance allows bounded classification suggestions.' );

maca_assert(
	'output_quality_rejected' === npcink_cloud_acceptance_translation_block_status(
		502,
		array( 'code' => 'provider.output_quality_rejected', 'data' => array( 'output_quality_reason' => 'translation_structure_drift' ) ),
		false,
		false
	),
	'Acceptance distinguishes Cloud translation quality rejection from a generic request failure.'
);
maca_assert(
	'provider_failed' === npcink_cloud_acceptance_translation_block_status( 502, array( 'code' => 'provider.timeout' ), false, false ),
	'Acceptance distinguishes provider execution failure from output validation failure.'
);
$translation_diagnostics = npcink_cloud_acceptance_translation_block_diagnostics(
	array(
		array( 'block_type' => 'core/paragraph', 'content' => '这是一个足够长的段落。' ),
		array( 'block_type' => 'core/paragraph', 'content' => '短' ),
		array( 'block_type' => 'core/image', 'content' => '媒体说明不参与翻译。' ),
		array( 'block_type' => 'core/heading', 'content' => '另一个标题' ),
		array( 'block_type' => 'core/heading', 'content' => '功能特点' ),
		array( 'block_type' => 'core/list', 'content' => '列表不走普通文本翻译。' ),
		array( 'block_type' => 'core/list-item', 'content' => '列表项不走普通文本翻译。' ),
		array( 'block_type' => 'core/gallery', 'content' => '画廊不走普通文本翻译。' ),
	),
	static function ( array $input ) {
		if ( false !== strpos( (string) $input['content'], '另一个' ) ) {
			return array(
				'http_status'        => 502,
				'data'               => array( 'code' => 'provider.output_quality_rejected', 'data' => array( 'output_quality_reason' => 'translation_untranslated_source' ) ),
				'output_shape_valid' => false,
				'non_empty_result'   => false,
				'provider_run_id'    => 'run_rejected',
			);
		}
		return array(
			'http_status'        => 200,
			'data'               => 'A translated paragraph.',
			'output_shape_valid' => true,
			'non_empty_result'   => true,
			'provider_run_id'    => 'run_translated',
		);
	},
	'en-us',
	5
);
maca_assert(
	8 === $translation_diagnostics['summary']['total_blocks']
	&& 2 === $translation_diagnostics['summary']['eligible_blocks']
	&& 1 === $translation_diagnostics['summary']['translated']
	&& 2 === $translation_diagnostics['summary']['skipped_too_short']
	&& 4 === $translation_diagnostics['summary']['unsupported_block']
	&& 1 === $translation_diagnostics['summary']['output_quality_rejected']
	&& 'unsupported_block_type' === $translation_diagnostics['blocks'][2]['failure_code']
	&& 'below_minimum_length' === $translation_diagnostics['blocks'][4]['failure_code']
	&& 'translation_untranslated_source' === $translation_diagnostics['blocks'][3]['diagnostic_code']
	&& ! isset( $translation_diagnostics['blocks'][0]['content'] ),
	'Acceptance reports block-level translation states without retaining source content.'
);

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
maca_assert( 'mapping_current' === npcink_cloud_acceptance_contract_status( array( 'verification_state' => 'mapping_current' ) ), 'Acceptance preserves a current contract status.' );
$contract_drift = new WP_Error( 'cloud_ai_task_schema_hash_mismatch', 'drift' );
maca_assert( 'contract_drift' === npcink_cloud_acceptance_contract_status( $contract_drift ), 'Acceptance distinguishes contract drift before Cloud execution.' );
