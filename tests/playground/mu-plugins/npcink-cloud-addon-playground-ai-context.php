<?php
/**
 * Native Ability/REST context checks; no provider execution or content writes.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

/** @return WP_Ability Temporary in-memory Ability, never registered or persisted. */
function npcink_playground_context_ability( string $name, callable $callback ): WP_Ability {
	return new WP_Ability( $name, array(
		'label' => 'Context fixture', 'description' => 'Disposable context fixture', 'category' => 'site',
		'input_schema' => array( 'type' => 'object' ), 'output_schema' => array( 'type' => 'boolean' ),
		'permission_callback' => '__return_true', 'execute_callback' => $callback,
	) );
}

/** @return array<string,bool> Native execution evidence, including legacy REST failure cleanup. */
function npcink_playground_verify_ai_context(): array {
	$checks = array();
	$outer = npcink_playground_context_ability( 'fixture/outer', static function () use ( &$checks ): bool {
		foreach ( array( 'read', 'failure' ) as $kind ) {
			$request = new WP_REST_Request( 'GET', '/npcink-cloud-addon-playground/v1/context-' . $kind );
			$response = rest_do_request( $request );
			$checks[ $kind . '_status' ] = ( 'read' === $kind ? 200 : 500 ) === $response->get_status();
			$checks[ $kind . '_restores_caller' ] = 'fixture/outer' === ( Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context()['ability_id'] ?? '' );
			if ( 'read' === $kind ) {
				$checks['rest_isolates_caller'] = array() === $response->get_data();
			}
		}
		return true;
	} );
	$checks['outer_success'] = true === $outer->execute( array() );
	$inner = npcink_playground_context_ability( 'ai/alt-text-generation', static function () use ( &$checks ): bool {
		$checks['inner_attachment'] = array( 'attachment_id' => 456 ) === Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context();
		$checks['inner_one_use'] = array() === Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context();
		return true;
	} );
	$alt = npcink_playground_context_ability( 'ai/alt-text-generation', static function () use ( $inner, &$checks ): bool {
		$checks['inner_success'] = true === $inner->execute( array( 'attachment_id' => 456 ) );
		$checks['outer_attachment'] = array( 'attachment_id' => 123 ) === Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context();
		$checks['outer_one_use'] = array() === Npcink_Cloud_WordPress_AI_Connector::consume_alt_text_ability_context();
		return true;
	} );
	$checks['alt_success'] = true === $alt->execute( array( 'attachment_id' => 123 ) );
	$checks['no_leaked_context'] = array() === Npcink_Cloud_WordPress_AI_Connector::current_text_ability_context();
	return $checks;
}
