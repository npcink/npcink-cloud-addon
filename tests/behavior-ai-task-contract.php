<?php
/**
 * Behavior tests for registered Ability task projections.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if ( ! function_exists( 'wp_get_ability' ) ) {
	function wp_get_ability( string $name ) {
		return $GLOBALS['maca_abilities'][ $name ] ?? null;
	}
}

final class Maca_AI_Task_Test_Ability {
	private string $name;
	private array $meta;
	private array $output_schema;

	public function __construct( string $name, array $meta, array $output_schema ) {
		$this->name          = $name;
		$this->meta          = $meta;
		$this->output_schema = $output_schema;
	}

	public function get_name(): string {
		return $this->name;
	}

	public function get_meta(): array {
		return $this->meta;
	}

	public function get_output_schema(): array {
		return $this->output_schema;
	}
}

maca_load_addon_classes();

$GLOBALS['maca_abilities']['ai/image-prompt-generation'] = new Maca_AI_Task_Test_Ability(
	'ai/image-prompt-generation',
	array(),
	array( 'type' => 'string' )
);
$image_contract = Npcink_Cloud_AI_Task_Contract::project_registered_ability( 'ai/image-prompt-generation' );

$GLOBALS['maca_abilities']['ai/alt-text-generation'] = new Maca_AI_Task_Test_Ability(
	'ai/alt-text-generation',
	array( 'type' => 'object', 'properties' => array( 'attachment_id' => array( 'type' => 'integer' ) ) ),
	array(
		'type'       => 'object',
		'properties' => array(
			'alt_text'      => array( 'type' => 'string' ),
			'is_decorative' => array( 'type' => 'boolean' ),
		),
	)
);
$alt_text_contract = Npcink_Cloud_AI_Task_Contract::project_registered_ability( 'ai/alt-text-generation' );
maca_assert(
	is_array( $alt_text_contract )
	&& 'alt_text_suggest' === (string) ( $alt_text_contract['task'] ?? '' )
	&& 0 === strpos( (string) ( $alt_text_contract['schema_hash'] ?? '' ), 'sha256:' )
	&& 'suggestion_only' === (string) ( $alt_text_contract['write_posture'] ?? '' ),
	'Behavior: alt-text generation reuses the registered Ability schema contract.'
);
maca_assert(
	is_array( $image_contract )
	&& 'image_prompt_generation' === (string) ( $image_contract['task'] ?? '' )
	&& 'generation' === (string) ( $image_contract['task_family'] ?? '' )
	&& 'suggestion_only' === (string) ( $image_contract['write_posture'] ?? '' ),
	'Behavior: the text-only ai-wp-admin image prompt ability receives a bounded compatibility task projection.'
);

$GLOBALS['maca_abilities']['ai/title-generation'] = new Maca_AI_Task_Test_Ability(
	'ai/title-generation',
	array(),
	array( 'type' => 'object', 'properties' => array( 'title' => array( 'type' => 'string' ) ) )
);
$title_contract = Npcink_Cloud_AI_Task_Contract::project_registered_ability( 'ai/title-generation' );
maca_assert(
	is_array( $title_contract )
	&& 'ai_task_contract.v1' === (string) ( $title_contract['contract_version'] ?? '' )
	&& 'title_generation' === (string) ( $title_contract['task'] ?? '' )
	&& 'generation' === (string) ( $title_contract['task_family'] ?? '' )
	&& is_array( $title_contract['input_schema'] ?? null )
	&& 0 === strpos( (string) ( $title_contract['schema_hash'] ?? '' ), 'sha256:' )
	&& 'suggestion_only' === (string) ( $title_contract['write_posture'] ?? '' ),
	'Behavior: ai-wp-admin title generation receives a bounded compatibility task projection.'
);

$GLOBALS['maca_abilities']['ai/suggest-reply'] = new Maca_AI_Task_Test_Ability(
	'ai/suggest-reply',
	array(),
	array( 'type' => 'string' )
);
$suggest_reply_contract = Npcink_Cloud_AI_Task_Contract::project_registered_ability( 'ai/suggest-reply' );
maca_assert(
	is_array( $suggest_reply_contract )
	&& 'comment_reply_suggest' === (string) ( $suggest_reply_contract['task'] ?? '' )
	&& 'generation' === (string) ( $suggest_reply_contract['task_family'] ?? '' )
	&& array( 'current_content' ) === ( $suggest_reply_contract['context_requirements'] ?? null )
	&& 'suggestion_only' === (string) ( $suggest_reply_contract['write_posture'] ?? '' ),
	'Behavior: ai-wp-admin suggest reply receives a bounded suggestion-only comment reply task projection.'
);

$canonical_schema = array(
	'input_schema'  => array( 'properties' => array( 'body' => array( 'type' => 'string' ), 'title' => array( 'type' => 'string' ) ), 'type' => 'object' ),
	'output_schema' => array( 'type' => 'string', 'minLength' => 1 ),
);
$canonical_contract = array(
	'contract_version' => 'ai_task_contract.v1',
	'ability_name' => 'ai/canonical-hash',
	'ability_id' => 'ai/canonical-hash',
	'contract_source' => 'wordpress_abilities_api',
	'verification_state' => 'mapping_current',
	'task' => 'canonical_hash',
	'task_family' => 'generation',
	'input_schema' => $canonical_schema['input_schema'],
	'output_schema' => $canonical_schema['output_schema'],
);
$canonical_json = '{"input_schema":{"properties":{"body":{"type":"string"},"title":{"type":"string"}},"type":"object"},"output_schema":{"minLength":1,"type":"string"}}';
$canonical_contract['schema_hash'] = 'sha256:' . hash( 'sha256', $canonical_json );
$first_hash = Npcink_Cloud_AI_Task_Contract::normalize( $canonical_contract );
$canonical_contract['input_schema'] = array( 'type' => 'object', 'properties' => array( 'title' => array( 'type' => 'string' ), 'body' => array( 'type' => 'string' ) ) );
$canonical_contract['output_schema'] = array( 'minLength' => 1, 'type' => 'string' );
$canonical_contract['schema_hash'] = is_array( $first_hash ) ? $first_hash['schema_hash'] : '';
$reordered_hash = Npcink_Cloud_AI_Task_Contract::normalize( $canonical_contract );
maca_assert( is_array( $first_hash ) && is_array( $reordered_hash ) && $first_hash['schema_hash'] === $reordered_hash['schema_hash'], 'Behavior: schema hashes remain stable when object key order changes.' );

$GLOBALS['maca_abilities']['ai/slug-generation'] = new Maca_AI_Task_Test_Ability(
	'ai/slug-generation',
	array(),
	array( 'type' => 'object', 'properties' => array( 'slugs' => array( 'type' => 'array' ) ) )
);
$slug_contract = Npcink_Cloud_AI_Task_Contract::project_registered_ability( 'ai/slug-generation' );
maca_assert(
	is_array( $slug_contract )
	&& 'slug_generation' === (string) ( $slug_contract['task'] ?? '' )
	&& in_array( 'json_object', $slug_contract['constraints'] ?? array(), true ),
	'Behavior: slug generation keeps its structured Ability output contract.'
);

$GLOBALS['maca_abilities']['ai/editorial-notes'] = new Maca_AI_Task_Test_Ability(
	'ai/editorial-notes',
	array(),
	array(
		'type'       => 'object',
		'properties' => array( 'suggestions' => array( 'type' => 'array' ) ),
	)
);
$editorial_notes_contract = Npcink_Cloud_AI_Task_Contract::project_registered_ability( 'ai/editorial-notes' );
maca_assert(
	is_array( $editorial_notes_contract )
	&& 'editorial_notes' === (string) ( $editorial_notes_contract['task'] ?? '' )
	&& in_array( 'json_object', $editorial_notes_contract['constraints'] ?? array(), true ),
	'Behavior: editorial notes preserves the structured Ability result instead of falling back to summary text.'
);

$GLOBALS['maca_abilities']['example/seo-headline'] = new Maca_AI_Task_Test_Ability(
	'example/seo-headline',
	array(
		'npcink_ai_task_contract' => array(
			'task'                 => 'seo_headline',
			'task_family'          => 'generation',
			'context_requirements' => array( 'current_content', 'site_style_profile' ),
			'constraints'          => array( 'single_value', 'source_grounded', 'no_new_numbers' ),
		),
	),
	array( 'type' => 'string' )
);
$custom_contract = Npcink_Cloud_AI_Task_Contract::project_registered_ability( 'example/seo-headline' );
maca_assert(
	is_array( $custom_contract )
	&& 'seo_headline' === (string) ( $custom_contract['task'] ?? '' )
	&& array( 'type' => 'string' ) === ( $custom_contract['output_schema'] ?? null ),
	'Behavior: a second plugin can publish a task through Ability metadata without an addon task registration.'
);

$invalid_contract = Npcink_Cloud_AI_Task_Contract::normalize(
	array(
		'contract_version'     => 'ai_task_contract.v1',
		'ability_name'         => 'example/unsafe',
		'task'                 => 'unsafe',
		'task_family'          => 'chat',
		'context_requirements' => array( 'current_content' ),
		'constraints'          => array(),
	)
);
maca_assert(
	is_wp_error( $invalid_contract ) && 'cloud_ai_task_contract_identity_invalid' === $invalid_contract->get_error_code(),
	'Behavior: task projections fail closed on an unsupported open-ended task family.'
);

$mismatched_hash_contract              = $custom_contract;
$mismatched_hash_contract['schema_hash'] = 'sha256:' . str_repeat( '0', 64 );
$mismatched_hash_result                = Npcink_Cloud_AI_Task_Contract::normalize( $mismatched_hash_contract );
maca_assert(
	is_wp_error( $mismatched_hash_result ) && 'cloud_ai_task_schema_hash_mismatch' === $mismatched_hash_result->get_error_code(),
	'Behavior: task projections fail closed when the schema hash does not match the projected schemas.'
);

$stale_contract = $custom_contract;
$stale_contract['verification_state'] = 'contract_drift';
$stale_result = Npcink_Cloud_AI_Task_Contract::normalize( $stale_contract );
maca_assert(
	is_wp_error( $stale_result ) && 'cloud_ai_task_contract_not_current' === $stale_result->get_error_code(),
	'Behavior: a drifted contract stops before the Cloud request.'
);

maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode( array( 'status' => 'ok', 'run_id' => 'run_custom_task' ) ),
);
$client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );
$result = $client->execute_wordpress_ai_connector_runtime(
	array(
		'contract_version'  => 'cloud_connector_runtime.v1',
		'operation_contract' => array(
			'contract_version' => 'wordpress_operation.v1',
			'task'             => 'seo_headline',
			'request'          => array(
				'task_contract' => $custom_contract,
				'prompt'        => 'Write one accurate headline.',
			),
		),
	)
);
$request      = end( $GLOBALS['maca_http_requests'] );
$request_body = json_decode( (string) ( $request['args']['body'] ?? '' ), true );
maca_assert(
	is_array( $result )
	&& 'cloud_connector_runtime.v1' === (string) ( $request_body['contract_version'] ?? '' )
	&& 'wordpress_operation.v1' === (string) ( $request_body['input']['operation_contract']['contract_version'] ?? '' )
	&& 'seo_headline' === (string) ( $request_body['input']['operation_contract']['task'] ?? '' )
	&& 'example/seo-headline' === (string) ( $request_body['input']['operation_contract']['request']['task_contract']['ability_name'] ?? '' )
	&& ! isset( $request_body['input']['operation_contract']['request']['site_knowledge_reference'] ),
	'Behavior: the generic connector transports a registered task projection without adding unproven generation reference context.'
);
