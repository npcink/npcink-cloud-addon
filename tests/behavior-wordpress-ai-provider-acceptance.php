<?php
/** Deterministic acceptance regressions; no Provider calls or content writes. */
require_once __DIR__ . '/helpers.php';
require_once dirname( __DIR__ ) . '/scripts/wordpress-ai-provider-acceptance-functions.php';

$acceptance_smoke_source = (string) file_get_contents( dirname( __DIR__ ) . '/scripts/smoke-wordpress-ai-provider-acceptance.php' );
maca_assert(
	false !== strpos( $acceptance_smoke_source, "'contract_source'" )
	&& false !== strpos( $acceptance_smoke_source, "'contract_status'" )
	&& false !== strpos( $acceptance_smoke_source, "'verification_state'" )
	&& false !== strpos( $acceptance_smoke_source, "'input_fingerprint'" )
	&& false !== strpos( $acceptance_smoke_source, "'input_fields'" )
	&& false !== strpos( $acceptance_smoke_source, 'current_cloud_run_id' ),
	'Acceptance reports expose contract provenance and verification state for development diagnostics.'
);
$connector_source = (string) file_get_contents( dirname( __DIR__ ) . '/includes/class-cloud-wordpress-ai-connector.php' );
	maca_assert(
	false !== strpos( $connector_source, 'record_cloud_run_id' )
	&& false !== strpos( $connector_source, '$cloud_run_id' )
	&& false !== strpos( $connector_source, 'wp_generate_uuid4()' ),
	'Acceptance correlation records only a Cloud-provided run ID and keeps local result IDs separate.'
);
maca_assert( 'contract_drift' === npcink_cloud_acceptance_contract_status( new WP_Error( 'cloud_ai_task_schema_hash_mismatch', 'drift' ) ), 'Acceptance classifies schema drift separately from provider failures.' );

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
