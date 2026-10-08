<?php
/**
 * Controlled native-feedback smoke in an already verified Local WordPress.
 * Run with scripts/wp-cli-local.sh scripts/smoke-editor-native-feedback.php.
 * Option writes are shadowed in memory; native HTTP is intercepted locally.
 * No generation, post save, paid call or Cloud feedback fixture is submitted.
 *
 * @package NpcinkCloudAddon
 */

if ( ! defined( 'ABSPATH' ) || ! Npcink_Cloud_Addon_Settings::is_monitoring_enabled() ) {
	throw new RuntimeException( 'This smoke requires an active, verified, opted-in Local Addon.' );
}

$options = array(
	Npcink_Cloud_Observability_Collector::BUFFER_OPTION,
	Npcink_Cloud_Observability_Collector::STATUS_OPTION,
	Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION,
	Npcink_Cloud_Customer_Journey::BUFFER_OPTION,
	Npcink_Cloud_Editor_Assist_Quality::PENDING_OPTION,
);
$shadow = array_fill_keys( $options, array() );
$filters = array();
foreach ( $options as $option ) {
	$read = static function () use ( &$shadow, $option ): array { return $shadow[ $option ]; };
	$write = static function ( $next, $previous ) use ( &$shadow, $option ) {
		$shadow[ $option ] = $next;
		return $previous;
	};
	add_filter( 'pre_option_' . $option, $read );
	add_filter( 'pre_update_option_' . $option, $write, 10, 2 );
	$filters[] = array( 'pre_option_' . $option, $read );
	$filters[] = array( 'pre_update_option_' . $option, $write );
}
$requests = array();
$unexpected_requests = 0;
$intercept = static function ( $pre, array $args, string $url ) use ( &$requests, &$unexpected_requests ) {
	if ( ! str_ends_with( $url, '/v1/agent-feedback/events' ) ) {
		++$unexpected_requests;
		return new WP_Error( 'controlled_unexpected_http', 'Controlled smoke forbids unexpected outbound HTTP.' );
	}
	$requests[] = $args;
	if ( 1 === count( $requests ) ) {
		return new WP_Error( 'controlled_native_retry', 'Controlled local fixture' );
	}
	return array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'content-type' => 'application/json' ),
		'body'     => wp_json_encode( array( 'data' => array( 'accepted_for_eval' => true ) ) ),
	);
};
add_filter( 'pre_http_request', $intercept, 10, 3 );

try {
	$run_id = 'run_controlled_native_' . wp_generate_uuid4();
	$text = 'Controlled private native adoption fixture';
	Npcink_Cloud_Editor_Assist_Quality::record_generation( 'ai/title-generation', 'title_generation', array( 'post_id' => 2147483646 ), $run_id, $text, 0 );
	$post = (object) array( 'post_title' => $text, 'post_content' => '', 'post_status' => 'draft' );
	Npcink_Cloud_Editor_Assist_Quality::observe_post_save( 2147483646, $post, true );
	$buffer = get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() );
	$payload = array_values( $buffer )[0] ?? array();
	if ( 1 !== count( $buffer ) || $run_id !== ( $payload['source_run_id'] ?? '' ) || array() !== $requests ) {
		throw new RuntimeException( 'Controlled adoption association or deferred delivery failed.' );
	}
	Npcink_Cloud_Observability_Collector::flush_editor_feedback();
	if ( $buffer !== get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ) ) {
		throw new RuntimeException( 'Failed native delivery did not preserve its payload.' );
	}
	Npcink_Cloud_Observability_Collector::flush_editor_feedback();
	if (
		2 !== count( $requests )
		|| 0 !== $unexpected_requests
		|| $requests[0]['body'] !== $requests[1]['body']
		|| $requests[0]['headers']['Idempotency-Key'] !== $requests[1]['headers']['Idempotency-Key']
		|| empty( $requests[1]['headers']['X-Npcink-Signature'] )
		|| array() !== get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() )
		|| false !== strpos( $requests[1]['body'], $text )
		|| false !== strpos( $requests[1]['body'], 'post_id' )
	) {
		throw new RuntimeException(
			'Controlled native smoke failed: ' . wp_json_encode(
				array(
					'attempts' => count( $requests ),
					'same_body' => ( $requests[0]['body'] ?? null ) === ( $requests[1]['body'] ?? null ),
					'same_key' => ( $requests[0]['headers']['Idempotency-Key'] ?? null ) === ( $requests[1]['headers']['Idempotency-Key'] ?? null ),
					'signed' => ! empty( $requests[1]['headers']['X-Npcink-Signature'] ),
					'remaining' => count( get_option( Npcink_Cloud_Observability_Collector::EDITOR_FEEDBACK_OPTION, array() ) ),
					'private_text' => false !== strpos( (string) ( $requests[1]['body'] ?? '' ), $text ),
					'private_id' => false !== strpos( (string) ( $requests[1]['body'] ?? '' ), 'post_id' ),
				)
			)
		);
	}
	echo wp_json_encode( array( 'status' => 'passed', 'mode' => 'controlled_local_wordpress', 'http_mode' => 'intercepted', 'signed_attempts' => 2, 'provider_calls' => 0, 'cloud_events_submitted' => 0, 'post_writes' => 0, 'persistent_option_writes' => 0 ) ) . PHP_EOL;
} finally {
	remove_filter( 'pre_http_request', $intercept, 10 );
	foreach ( $filters as $filter ) {
		remove_filter( $filter[0], $filter[1], 10 );
	}
}
