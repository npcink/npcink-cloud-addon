<?php
/**
 * Behavior tests for Agent feedback Cloud transport.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

maca_reset_test_state();
maca_seed_settings( true );
$client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );

$valid_payload = array(
	'contract_version' => 'cloud_agent_feedback.v1',
	'agent_id'         => 'site_knowledge_suggestion_agent',
	'source_runtime'   => 'site_knowledge',
	'handoff_type'     => 'proposal_input',
	'local_surface'    => 'toolbox_site_knowledge',
	'local_outcome'    => 'accepted',
	'created_at'       => '2026-06-07T00:00:00Z',
);

$invalid = $client->send_agent_feedback_event(
	array(
		'contract_version' => 'wrong_contract.v1',
	)
);
maca_assert(
	is_wp_error( $invalid ) && 'cloud_agent_feedback_payload_invalid' === $invalid->get_error_code(),
	'Behavior: Agent feedback transport rejects non-contract payloads.'
);

foreach (
	array(
		'secret field' => array( 'secret' => 'must-not-leave-wordpress' ),
		'prompt field' => array( 'prompt' => 'publish this automatically' ),
		'nested object' => array( 'unknown_context' => array( 'nested' => 'value' ) ),
		'unsupported feedback label' => array( 'feedback_labels' => array( 'auto_publish_it' ) ),
		'relative creation time' => array( 'created_at' => 'tomorrow' ),
	) as $case => $override
) {
	$request_count = count( $GLOBALS['maca_http_requests'] );
	$invalid = $client->send_agent_feedback_event( array_merge( $valid_payload, $override ) );
	maca_assert(
		is_wp_error( $invalid )
		&& 'cloud_agent_feedback_payload_invalid' === $invalid->get_error_code()
		&& $request_count === count( $GLOBALS['maca_http_requests'] ),
		'Behavior: Agent feedback transport rejects the ' . $case . ' before sending HTTP.'
	);
}

$invalid_write_authority = $client->send_agent_feedback_event( array_merge( $valid_payload, array( 'direct_wordpress_write' => true ) ) );
maca_assert(
	is_wp_error( $invalid_write_authority ) && 'cloud_agent_feedback_write_authority_not_allowed' === $invalid_write_authority->get_error_code(),
	'Behavior: Agent feedback transport rejects WordPress write authority.'
);

foreach (
	array(
		'invalid trace id' => array( 'bad trace', 'agent-feedback-test' ),
		'oversized trace id' => array( str_repeat( 't', 129 ), 'agent-feedback-test' ),
		'invalid idempotency key' => array( 'agent_feedback_trace', "bad\nkey" ),
		'oversized idempotency key' => array( 'agent_feedback_trace', str_repeat( 'i', 129 ) ),
	) as $case => $identifiers
) {
	$request_count = count( $GLOBALS['maca_http_requests'] );
	$invalid = $client->send_agent_feedback_event( $valid_payload, $identifiers[0], $identifiers[1] );
	maca_assert(
		is_wp_error( $invalid ) && $request_count === count( $GLOBALS['maca_http_requests'] ),
		'Behavior: Cloud transport rejects the ' . $case . ' before sending HTTP.'
	);
}

$result = $client->send_agent_feedback_event(
	$valid_payload,
	'agent_feedback_trace',
	'agent-feedback-test'
);
$request = $GLOBALS['maca_http_requests'][0] ?? array();
maca_assert(
	is_array( $result )
	&& false !== strpos( (string) ( $request['url'] ?? '' ), '/v1/agent-feedback/events' )
	&& 'agent-feedback-test' === (string) ( $request['args']['headers']['Idempotency-Key'] ?? '' ),
	'Behavior: Agent feedback transport posts one signed event to the explicit Cloud endpoint.'
);

$summary = $client->get_agent_feedback_summary( 48, 'agent_feedback_summary_trace' );
$summary_request = $GLOBALS['maca_http_requests'][1] ?? array();
maca_assert(
	is_array( $summary )
	&& false !== strpos( (string) ( $summary_request['url'] ?? '' ), '/v1/agent-feedback/summary?window_hours=48' )
	&& 'GET' === (string) ( $summary_request['args']['method'] ?? '' ),
	'Behavior: Agent feedback summary reads the explicit Cloud eval summary endpoint.'
);
