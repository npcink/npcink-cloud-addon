<?php
/**
 * Unified, read-only WP-CLI acceptance report for the official WordPress AI
 * Ability surface and the Npcink Cloud provider projection.
 *
 * Run with:
 *   composer run acceptance:wp-ai-provider
 *
 * Optional real media/comment/image-generation coverage:
 *   WP_AI_ACCEPTANCE_COMMENT_ID=123 WP_AI_ACCEPTANCE_ALT_TEXT_ATTACHMENT_ID=456 \
 *   WP_AI_ACCEPTANCE_IMAGE_GENERATION=1 \
 *   composer run acceptance:wp-ai-provider
 *
 * A bounded subset can be run while diagnosing one ability:
 *   WP_AI_ACCEPTANCE_ABILITIES=ai/editorial-updates composer run acceptance:wp-ai-provider
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
	array( 'scenario_id' => 'excerpt-default', 'ability' => 'ai/excerpt-generation', 'input' => array( 'content' => 'A short article about reliable WordPress AI provider contracts.', 'context' => 'Keep it concise and factual.' ) ),
	array( 'scenario_id' => 'meta-description-default', 'ability' => 'ai/meta-description', 'input' => array( 'content' => 'A short article about reliable WordPress AI provider contracts.', 'title' => 'Provider contracts' ) ),
	array( 'scenario_id' => 'content-translation-en-us', 'ability' => 'ai/content-translation', 'input' => array( 'content' => '这是一个用于验证翻译能力的固定测试段落。', 'target_language' => 'en-us' ) ),
	array( 'scenario_id' => 'content-translation-blocks-en-us', 'ability' => 'ai/content-translation', 'input' => array( 'content' => '<!-- wp:paragraph --><p>这是第一个需要保留结构的段落。</p><!-- /wp:paragraph --><!-- wp:list --><ul><li>第一项</li><li>第二项</li></ul><!-- /wp:list -->', 'target_language' => 'en-us' ) ),
	array( 'scenario_id' => 'summarization-short', 'ability' => 'ai/summarization', 'input' => array( 'content' => 'This fixed article explains how a WordPress Ability reaches a hosted provider through a bounded connector contract.', 'context' => 'Keep the summary factual.', 'length' => 'short' ) ),
	array( 'scenario_id' => 'slug-generation-default', 'ability' => 'ai/slug-generation', 'input' => array( 'title' => 'WordPress AI provider compatibility guide', 'content' => 'A guide to stable provider contracts.', 'number_of_suggestions' => 3 ) ),
	array( 'scenario_id' => 'slug-generation-mixed-language', 'ability' => 'ai/slug-generation', 'input' => array( 'title' => 'TheBiz：双平台微信小程序与百度智能小程序解决方案', 'content' => '企业小程序双平台解决方案与部署说明。', 'number_of_suggestions' => 3 ) ),
	array( 'scenario_id' => 'content-resizing-short', 'ability' => 'ai/content-resizing', 'input' => array( 'content' => 'This fixed paragraph repeats the same idea and needs a shorter, clearer version for an article.', 'action' => 'shorten' ) ),
	array( 'scenario_id' => 'editorial-notes-readability-grammar', 'ability' => 'ai/editorial-notes', 'input' => array( 'block_type' => 'core/paragraph', 'block_content' => 'These sentence are hard to reads. It repeats the same point again and repeats the same point again.', 'review_types' => array( 'readability', 'grammar' ) ) ),
	array( 'scenario_id' => 'editorial-updates-with-notes', 'ability' => 'ai/editorial-updates', 'input' => array( 'block_type' => 'core/paragraph', 'block_content' => 'This paragraph needs a concise editorial update.', 'notes' => array( 'Make the paragraph clearer.' ) ) ),
	array( 'scenario_id' => 'classification-post-tag-existing-only', 'ability' => 'ai/content-classification', 'input' => array( 'content' => 'A practical guide to connecting WordPress AI abilities to a hosted provider.', 'taxonomy' => 'post_tag', 'strategy' => 'existing_only', 'max_suggestions' => 3 ) ),
	array( 'scenario_id' => 'classification-category-existing-only', 'ability' => 'ai/content-classification', 'input' => array( 'content' => 'A practical guide to connecting WordPress AI abilities to a hosted provider.', 'taxonomy' => 'category', 'strategy' => 'existing_only', 'max_suggestions' => 3 ) ),
	array( 'scenario_id' => 'image-prompt-generation-default', 'ability' => 'ai/image-prompt-generation', 'input' => array( 'content' => 'A calm editorial workspace showing a WordPress article moving through a reliable AI provider pipeline.', 'context' => 'Use a clean product illustration style.', 'style' => 'minimal, editorial, accessible contrast' ) ),
	array( 'scenario_id' => 'title-generation-default', 'ability' => 'ai/title-generation', 'input' => array( 'content' => 'A guide explaining how WordPress AI abilities connect to a cloud provider.' ) ),
);
$comment_id = absint( getenv( 'WP_AI_ACCEPTANCE_COMMENT_ID' ) ?: 0 );
if ( 0 < $comment_id ) {
	$cases[] = array( 'scenario_id' => 'comment-analysis-fixed', 'ability' => 'ai/comment-analysis', 'input' => array( 'comment_id' => $comment_id ) );
	$cases[] = array( 'scenario_id' => 'comment-reply-fixed', 'ability' => 'ai/suggest-reply', 'input' => array( 'comment_id' => $comment_id, 'tone' => 'friendly' ) );
}
$alt_text_attachment_id = absint( getenv( 'WP_AI_ACCEPTANCE_ALT_TEXT_ATTACHMENT_ID' ) ?: 0 );
if ( 0 < $alt_text_attachment_id ) {
	$cases[] = array(
		'scenario_id' => 'alt-text-generation-fixed',
		'ability'     => 'ai/alt-text-generation',
		'input'       => array(
			'attachment_id' => $alt_text_attachment_id,
			'context'       => 'Describe the main subject for an accessible media-library alt text suggestion.',
		),
	);
}
$image_generation_enabled = '1' === (string) ( getenv( 'WP_AI_ACCEPTANCE_IMAGE_GENERATION' ) ?: '' );
if ( $image_generation_enabled ) {
	$cases[] = array(
		'scenario_id' => 'image-generation-artifact-fixed',
		'ability'     => 'ai/image-generation',
		'input'       => array(
			'prompt'        => 'A simple editorial illustration of a WordPress article moving through a reliable AI provider pipeline.',
			'n'             => 1,
			'aspect_ratio' => '1:1',
			'resolution'   => 'medium',
		),
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
	$cases = array_values(
		array_filter(
			$cases,
			static function ( $case ) use ( $requested_abilities ) {
				return is_array( $case ) && in_array( (string) ( $case['ability'] ?? '' ), $requested_abilities, true );
			}
		)
	);
}

$report = array(
	'contract_version' => 'wordpress_ai_provider_acceptance.v1',
	'evidence_state'   => 'local_executed',
	'write_detected'   => null,
	'write_evidence_state' => 'not_measured',
	'generated_at'     => gmdate( 'c' ),
	'fixture_id'       => 'wordpress-ai-acceptance-fixed-v1',
	'optional_capabilities_skipped' => array(),
	'cases'            => array(),
);

foreach ( $cases as $case_definition ) {
	$ability = (string) ( $case_definition['ability'] ?? '' );
	$input = is_array( $case_definition['input'] ?? null ) ? $case_definition['input'] : array();
	$scenario_id = sanitize_key( (string) ( $case_definition['scenario_id'] ?? $ability ) );
	$contract = Npcink_Cloud_AI_Task_Contract::project_registered_ability( $ability );
	$input_json = wp_json_encode( $input, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION );
	$input_fields = array_keys( $input );
	sort( $input_fields );
	$case     = array(
		'scenario_id'        => $scenario_id,
		'ability'            => $ability,
		'input_fingerprint'  => false === $input_json ? null : 'sha256:' . hash( 'sha256', $input_json ),
		'input_fields'       => $input_fields,
		'ability_registered' => (bool) wp_get_ability( $ability ),
		'cloud_task'         => is_array( $contract ) ? (string) ( $contract['task'] ?? '' ) : null,
		'contract_version'   => is_array( $contract ) ? (string) ( $contract['contract_version'] ?? '' ) : null,
		'contract_source'    => is_array( $contract ) ? (string) ( $contract['contract_source'] ?? '' ) : null,
		'contract_status'    => npcink_cloud_acceptance_contract_status( $contract ),
		'verification_state' => is_array( $contract ) ? (string) ( $contract['verification_state'] ?? '' ) : null,
		'schema_hash'        => is_array( $contract ) ? (string) ( $contract['schema_hash'] ?? '' ) : null,
		'evidence_state'     => 'local_executed',
		'http_status'        => null,
		'output_shape_valid' => false,
		'provider_run_id'    => null,
		'write_detected'     => null,
		'write_evidence_state' => 'not_measured',
		'quality_status'     => 'failed',
		'failure_code'       => null,
		'output'             => null,
	);

	if ( is_wp_error( $contract ) ) {
		$case['failure_code'] = 'ability_contract_invalid';
		$report['cases'][]    = $case;
		continue;
	}

	if ( class_exists( 'Npcink_Cloud_WordPress_AI_Connector' ) ) {
		Npcink_Cloud_WordPress_AI_Connector::record_cloud_run_id( '' );
		if ( method_exists( 'Npcink_Cloud_WordPress_AI_Connector', 'reset_runtime_failure_evidence' ) ) {
			Npcink_Cloud_WordPress_AI_Connector::reset_runtime_failure_evidence();
		}
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
	$case['quality_context'] = npcink_cloud_acceptance_quality_context( $ability, $input, $data );
	$case['failure_code']  = $has_result ? null : ( $shape_valid ? 'empty_result' : npcink_cloud_acceptance_failure_code( $status, $data, $shape_valid ) );
	if ( npcink_cloud_acceptance_is_empty_taxonomy_error( $ability, $data ) ) {
		$case['failure_code'] = 'taxonomy_empty';
	}
	if ( $shape_valid ) {
		$case['failure_code'] = npcink_cloud_acceptance_quality_failure( $ability, $data, $input );
	}
	$case['quality_status'] = null === $case['failure_code'] ? 'passed' : 'failed';
	if ( is_array( $data ) ) {
		$case['provider_run_id'] = $data['run_id'] ?? ( $data['data']['run_id'] ?? null );
	}
	if ( empty( $case['provider_run_id'] ) && class_exists( 'Npcink_Cloud_WordPress_AI_Connector' ) ) {
		$case['provider_run_id'] = Npcink_Cloud_WordPress_AI_Connector::current_cloud_run_id() ?: null;
	}
	if ( null !== $case['failure_code'] && class_exists( 'Npcink_Cloud_WordPress_AI_Connector' ) && method_exists( 'Npcink_Cloud_WordPress_AI_Connector', 'current_runtime_failure_evidence' ) ) {
		$failure_evidence = Npcink_Cloud_WordPress_AI_Connector::current_runtime_failure_evidence();
		if ( is_array( $failure_evidence ) && ! empty( $failure_evidence ) ) {
			if ( empty( $case['provider_run_id'] ) ) {
				$case['provider_run_id'] = (string) ( $failure_evidence['run_id'] ?? '' ) ?: null;
			}
			$case['failure_stage'] = (string) ( $failure_evidence['error_stage'] ?? '' );
			$case['quality_reason'] = (string) ( $failure_evidence['quality_reason'] ?? '' );
			$case['cloud_error_code'] = (string) ( $failure_evidence['cloud_error_code'] ?? '' );
		}
	}
	$report['cases'][] = $case;
}
if ( 0 === $comment_id ) {
	$report['optional_capabilities_skipped'][] = array( 'ability' => 'ai/comment-analysis', 'reason' => 'set WP_AI_ACCEPTANCE_COMMENT_ID' );
	$report['optional_capabilities_skipped'][] = array( 'ability' => 'ai/suggest-reply', 'reason' => 'set WP_AI_ACCEPTANCE_COMMENT_ID' );
}
if ( 0 === $alt_text_attachment_id ) {
	$report['optional_capabilities_skipped'][] = array( 'ability' => 'ai/alt-text-generation', 'reason' => 'set WP_AI_ACCEPTANCE_ALT_TEXT_ATTACHMENT_ID' );
}
if ( ! $image_generation_enabled ) {
	$report['optional_capabilities_skipped'][] = array( 'ability' => 'ai/image-generation', 'reason' => 'set WP_AI_ACCEPTANCE_IMAGE_GENERATION=1' );
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
