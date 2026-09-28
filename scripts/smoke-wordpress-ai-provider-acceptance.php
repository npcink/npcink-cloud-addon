<?php
/**
 * Unified, read-only WP-CLI acceptance report for the official WordPress AI
 * Ability surface and the Npcink Cloud provider projection.
 *
 * Run with:
 *   composer run acceptance:wp-ai-provider
 *
 * Optional real media/comment coverage:
 *   WP_AI_ACCEPTANCE_COMMENT_ID=123 WP_AI_ACCEPTANCE_ALT_TEXT_ATTACHMENT_ID=456 \
 *   composer run acceptance:wp-ai-provider
 *
 * A bounded subset can be run while diagnosing one ability:
 *   WP_AI_ACCEPTANCE_ABILITIES=ai/editorial-updates composer run acceptance:wp-ai-provider
 *
 * Developer-only translation block evidence can be enabled with:
 *   WP_AI_ACCEPTANCE_DIAGNOSTICS=1 composer run acceptance:wp-ai-provider
 *
 * The optional diagnostic section contains block type, source length, status,
 * machine failure code, and Cloud run correlation only. It never changes the
 * official WordPress AI result or adds a user-facing editor surface.
 *
 * Without those variables, comment and vision cases are reported as skipped;
 * they are never represented as passing coverage.
 *
 * The script uses fixed inputs, never saves or publishes content, and emits a
 * machine-readable wordpress_ai_provider_acceptance.v1 report to stdout.
 *
 * @package NpcinkCloudAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "Run this file through WP-CLI eval-file inside a WordPress install.\n" );
	exit( 1 );
}

if ( ! function_exists( 'wp_get_ability' ) || ! class_exists( 'Npcink_Cloud_AI_Task_Contract' ) ) {
	fwrite( STDERR, "The WordPress Abilities API and Npcink Cloud task contract are required.\n" );
	exit( 1 );
}

function npcink_cloud_acceptance_set_user() {
	$user_spec = (string) ( getenv( 'WP_AI_ACCEPTANCE_USER' ) ?: '1' );
	$user      = is_numeric( $user_spec ) ? get_user_by( 'id', absint( $user_spec ) ) : get_user_by( 'login', $user_spec );
	if ( ! $user || empty( $user->ID ) ) {
		fwrite( STDERR, "Acceptance user not found. Set WP_AI_ACCEPTANCE_USER.\n" );
		exit( 1 );
	}
	wp_set_current_user( (int) $user->ID );
}

function npcink_cloud_acceptance_request( $ability, array $input ) {
	$request = new WP_REST_Request( 'POST', '/wp-abilities/v1/abilities/' . str_replace( '/', '/', $ability ) . '/run' );
	$request->set_header( 'content-type', 'application/json' );
	$request->set_body( wp_json_encode( array( 'input' => $input ) ) );
	return rest_do_request( $request );
}

require_once __DIR__ . '/wordpress-ai-provider-acceptance-functions.php';

/**
 * Executes one translation fixture and returns a content-free diagnostic row.
 *
 * @param array<string,mixed> $input Fixed block input.
 * @return array<string,mixed>
 */
function npcink_cloud_acceptance_translation_block_runner( array $input ) {
	$ability   = 'ai/content-translation';
	$response  = npcink_cloud_acceptance_request( $ability, $input );
	$status    = (int) $response->get_status();
	$data      = $response->get_data();
	$registered = wp_get_ability( $ability );
	$schema    = $registered ? $registered->get_output_schema() : array();
	$shape_valid = 200 === $status && npcink_cloud_acceptance_shape_valid( $schema, $data );
	$has_result  = $shape_valid && npcink_cloud_acceptance_has_result( $ability, $data );

	return array(
		'http_status'        => $status,
		'data'               => $data,
		'output_shape_valid' => $shape_valid,
		'non_empty_result'   => $has_result,
		'failure_code'       => $has_result ? null : ( $shape_valid ? 'empty_result' : npcink_cloud_acceptance_failure_code( $status, $data, $shape_valid ) ),
		'diagnostic_code'    => npcink_cloud_acceptance_diagnostic_code( $status, $data ),
		'provider_run_id'    => is_array( $data ) ? ( $data['run_id'] ?? ( $data['data']['run_id'] ?? null ) ) : null,
	);
}

function npcink_cloud_acceptance_diagnostics_enabled() {
	return in_array( strtolower( trim( (string) getenv( 'WP_AI_ACCEPTANCE_DIAGNOSTICS' ) ) ), array( '1', 'true', 'yes', 'on' ), true );
}

/** @return array<int,array<string,mixed>> */
function npcink_cloud_acceptance_translation_fixture_blocks() {
	$raw = (string) getenv( 'WP_AI_ACCEPTANCE_TRANSLATION_BLOCKS_JSON' );
	$blocks = '' !== trim( $raw ) ? json_decode( $raw, true ) : null;
	if ( is_array( $blocks ) && ! empty( $blocks ) ) {
		return array_values( array_filter( $blocks, 'is_array' ) );
	}

	return array(
		array( 'block_type' => 'core/heading', 'content' => 'WordPress 翻译验收标题' ),
		array( 'block_type' => 'core/paragraph', 'content' => '这是一个足够长的固定翻译验收段落。' ),
		array( 'block_type' => 'core/paragraph', 'content' => '短' ),
		array( 'block_type' => 'core/image', 'content' => '不参与区块翻译的图片说明' ),
		array( 'block_type' => 'core/list', 'content' => '不参与普通文本翻译的列表' ),
		array( 'block_type' => 'core/list-item', 'content' => '不参与普通文本翻译的列表项' ),
		array( 'block_type' => 'core/gallery', 'content' => '不参与普通文本翻译的画廊' ),
		array( 'block_type' => 'core/heading', 'content' => '功能特点' ),
	);
}

function npcink_cloud_acceptance_wordpress_state() {
	global $wpdb;
	if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_row' ) ) {
		return null;
	}
	$row = $wpdb->get_row( "SELECT COUNT(*) AS posts, MAX(ID) AS max_post_id, MAX(post_modified_gmt) AS max_modified FROM {$wpdb->posts}", ARRAY_A );
	$meta = $wpdb->get_row( "SELECT COUNT(*) AS postmeta, MAX(meta_id) AS max_meta_id FROM {$wpdb->postmeta}", ARRAY_A );
	return array(
		'posts' => (int) ( $row['posts'] ?? 0 ),
		'max_post_id' => (int) ( $row['max_post_id'] ?? 0 ),
		'max_modified' => (string) ( $row['max_modified'] ?? '' ),
		'postmeta' => (int) ( $meta['postmeta'] ?? 0 ),
		'max_meta_id' => (int) ( $meta['max_meta_id'] ?? 0 ),
	);
}

npcink_cloud_acceptance_set_user();
$wordpress_state_before = npcink_cloud_acceptance_wordpress_state();

$cases = array(
	'ai/excerpt-generation'  => array( 'content' => 'A short article about reliable WordPress AI provider contracts.', 'length' => 'short' ),
	'ai/meta-description'    => array( 'content' => 'A short article about reliable WordPress AI provider contracts.', 'title' => 'Provider contracts' ),
	'ai/content-translation' => array( 'content' => '这是一个用于验证翻译能力的固定测试段落。', 'target_language' => 'en-us' ),
	'ai/summarization'       => array( 'content' => 'This fixed article explains how a WordPress Ability reaches a hosted provider through a bounded connector contract.', 'context' => 'Keep the summary factual.', 'length' => 'short' ),
	'ai/slug-generation'     => array( 'title' => 'WordPress AI provider compatibility guide', 'content' => 'A guide to stable provider contracts.' ),
	'ai/content-resizing'    => array( 'content' => 'This fixed paragraph repeats the same idea and needs a shorter, clearer version for an article.', 'action' => 'shorten' ),
	'ai/editorial-notes'     => array( 'block_type' => 'core/paragraph', 'block_content' => 'These sentence are hard to reads. It repeats the same point again and repeats the same point again.', 'review_types' => array( 'readability', 'grammar' ) ),
	'ai/editorial-updates'   => array( 'block_type' => 'core/paragraph', 'block_content' => 'This paragraph needs a concise editorial update.', 'notes' => array( 'Make the paragraph clearer.' ) ),
	'ai/content-classification' => array( 'content' => 'A practical guide to connecting WordPress AI abilities to a hosted provider.', 'taxonomy' => 'post_tag', 'strategy' => 'existing_only', 'max_suggestions' => 3 ),
	'ai/image-prompt-generation' => array( 'content' => 'A calm editorial workspace showing a WordPress article moving through a reliable AI provider pipeline.', 'context' => 'Use a clean product illustration style.', 'style' => 'minimal, editorial, accessible contrast' ),
	'ai/title-generation'    => array( 'content' => 'A guide explaining how WordPress AI abilities connect to a cloud provider.' ),
);
$comment_id = absint( getenv( 'WP_AI_ACCEPTANCE_COMMENT_ID' ) ?: 0 );
if ( 0 < $comment_id ) {
	$cases['ai/comment-analysis'] = array( 'comment_id' => $comment_id );
	$cases['ai/suggest-reply']    = array( 'comment_id' => $comment_id, 'tone' => 'friendly' );
}
$alt_text_attachment_id = absint( getenv( 'WP_AI_ACCEPTANCE_ALT_TEXT_ATTACHMENT_ID' ) ?: 0 );
if ( 0 < $alt_text_attachment_id ) {
	$cases['ai/alt-text-generation'] = array(
		'attachment_id' => $alt_text_attachment_id,
		'context'       => 'Describe the main subject for an accessible media-library alt text suggestion.',
	);
}
$requested_abilities = array_values(
	array_filter(
		array_map( 'trim', explode( ',', (string) ( getenv( 'WP_AI_ACCEPTANCE_ABILITIES' ) ?: '' ) ) ),
		static function ( $ability ) {
			return '' !== $ability;
		}
	)
);
if ( ! empty( $requested_abilities ) ) {
	$cases = array_intersect_key( $cases, array_flip( $requested_abilities ) );
}

$report = array(
	'contract_version' => 'wordpress_ai_provider_acceptance.v1',
	'evidence_state'   => 'local_executed',
	'write_detected'   => null,
	'write_evidence_state' => 'not_measured',
	'generated_at'     => gmdate( 'c' ),
	'optional_capabilities_skipped' => array(),
	'cases'            => array(),
);

foreach ( $cases as $ability => $input ) {
	$contract = Npcink_Cloud_AI_Task_Contract::project_registered_ability( $ability );
	$case     = array(
		'ability'            => $ability,
		'ability_registered' => (bool) wp_get_ability( $ability ),
		'cloud_task'         => is_array( $contract ) ? (string) ( $contract['task'] ?? '' ) : null,
		'contract_source'    => is_array( $contract ) ? (string) ( $contract['contract_source'] ?? '' ) : null,
		'contract_status'    => is_array( $contract ) ? (string) ( $contract['verification_state'] ?? '' ) : ( is_wp_error( $contract ) ? ( false !== strpos( $contract->get_error_code(), 'schema' ) || false !== strpos( $contract->get_error_code(), 'contract' ) ? 'contract_drift' : 'unsupported' ) : null ),
		'verification_state' => is_array( $contract ) ? (string) ( $contract['verification_state'] ?? '' ) : null,
		'contract_version'   => is_array( $contract ) ? (string) ( $contract['contract_version'] ?? '' ) : null,
		'schema_hash'       => is_array( $contract ) ? (string) ( $contract['schema_hash'] ?? '' ) : null,
		'evidence_state'     => 'local_executed',
		'http_status'        => null,
		'output_shape_valid' => false,
		'provider_run_id'    => null,
		'write_detected'     => null,
		'write_evidence_state' => 'not_measured',
		'quality_status'     => 'failed',
		'failure_code'       => null,
		'diagnostic_code'    => null,
		'output'             => null,
	);

	if ( is_wp_error( $contract ) ) {
		$case['failure_code'] = 'ability_contract_invalid';
		$report['cases'][]    = $case;
		continue;
	}

	$response            = npcink_cloud_acceptance_request( $ability, $input );
	$status              = (int) $response->get_status();
	$data                = $response->get_data();
	$registered = wp_get_ability( $ability );
	$schema = $registered ? $registered->get_output_schema() : array();
	$shape_valid = 200 === $status && npcink_cloud_acceptance_shape_valid( $schema, $data );
	$has_result          = $shape_valid && npcink_cloud_acceptance_has_result( $ability, $data );
	$case['http_status']  = $status;
	$case['output_shape_valid'] = $shape_valid;
	$case['non_empty_result'] = $has_result;
	$case['output']       = $data;
	$case['failure_code']  = $has_result ? null : ( $shape_valid ? 'empty_result' : npcink_cloud_acceptance_failure_code( $status, $data, $shape_valid ) );
	$case['diagnostic_code'] = npcink_cloud_acceptance_diagnostic_code( $status, $data );
	if ( $shape_valid ) {
		$case['failure_code'] = npcink_cloud_acceptance_quality_failure( $ability, $data );
	}
	$case['quality_status'] = null === $case['failure_code'] ? 'passed' : 'failed';
	if ( is_array( $data ) ) {
		$case['provider_run_id'] = $data['run_id'] ?? ( $data['data']['run_id'] ?? null );
	}
	$report['cases'][] = $case;
}

if ( npcink_cloud_acceptance_diagnostics_enabled() ) {
	if ( isset( $cases['ai/content-translation'] ) ) {
		$report['translation_block_diagnostics'] = npcink_cloud_acceptance_translation_block_diagnostics(
			npcink_cloud_acceptance_translation_fixture_blocks(),
			'npcink_cloud_acceptance_translation_block_runner',
			(string) ( $cases['ai/content-translation']['target_language'] ?? 'en-us' ),
			5
		);
	} else {
		$report['translation_block_diagnostics'] = array(
			'contract_version' => 'wordpress_ai_translation_block_diagnostics.v1',
			'source'           => 'official_wordpress_ai_content_translation_projection',
			'status'           => 'skipped',
			'reason'           => 'ai/content-translation not selected by WP_AI_ACCEPTANCE_ABILITIES',
		);
	}
}
if ( 0 === $comment_id ) {
	$report['optional_capabilities_skipped'][] = array( 'ability' => 'ai/comment-analysis', 'reason' => 'set WP_AI_ACCEPTANCE_COMMENT_ID' );
	$report['optional_capabilities_skipped'][] = array( 'ability' => 'ai/suggest-reply', 'reason' => 'set WP_AI_ACCEPTANCE_COMMENT_ID' );
}
if ( 0 === $alt_text_attachment_id ) {
	$report['optional_capabilities_skipped'][] = array( 'ability' => 'ai/alt-text-generation', 'reason' => 'set WP_AI_ACCEPTANCE_ALT_TEXT_ATTACHMENT_ID' );
}

$wordpress_state_after = npcink_cloud_acceptance_wordpress_state();
if ( is_array( $wordpress_state_before ) && is_array( $wordpress_state_after ) ) {
	$report['write_detected'] = $wordpress_state_before !== $wordpress_state_after;
	$report['write_evidence_state'] = $report['write_detected'] ? 'content_state_changed' : 'content_state_unchanged';
	foreach ( $report['cases'] as &$case ) {
		$case['write_detected'] = $report['write_detected'];
		$case['write_evidence_state'] = $report['write_evidence_state'];
	}
	unset( $case );
}

$report = npcink_cloud_acceptance_finalize_report( $report );
fwrite( STDOUT, wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . "\n" );
// Unknown write evidence cannot close acceptance even when fixed quality checks pass.
exit( 'local_verified' === $report['evidence_state'] ? 0 : 2 );
