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
