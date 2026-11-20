<?php
/**
 * Regression for read-only title recovery after an ambiguous HTTP dispatch.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

namespace WordPress\AiClient\Common\Exception {
	class RuntimeException extends \RuntimeException {}
}

namespace WordPress\AiClient\Messages\DTO {
	final class ModelMessage {
		public function __construct( public array $parts ) {}
	}
}

namespace {
	require_once __DIR__ . '/helpers.php';
	require_once __DIR__ . '/wordpress-ai-client-stubs.php';
	function plugin_basename( string $file ): string { return basename( $file ); }
	function get_post( int $id ) { return null; }
	function wp_get_ability( string $name ): object {
		return new class( $name ) {
			public function __construct( private string $name ) {}
			public function get_name(): string { return $this->name; }
			public function get_meta(): array { return array(); }
			public function get_input_schema(): array { return array( 'type' => 'object' ); }
			public function get_output_schema(): array { return array( 'type' => 'string' ); }
		};
	}
	define( 'NPCINK_CLOUD_ADDON_FILE', MACA_TEST_ROOT . '/npcink-cloud-addon.php' );
	require_once MACA_TEST_ROOT . '/includes/bootstrap.php';
	maca_seed_settings( true );

	$type = new \ReflectionClass( Npcink_Cloud_WordPress_AI_Text_Model::class );
	$model = $type->newInstanceWithoutConstructor();
	$config = $type->getProperty( 'config' );
	$config->setAccessible( true );
	$config->setValue( $model, new class {
		public function getFunctionDeclarations() { return null; }
		public function getWebSearch() { return null; }
		public function getCandidateCount(): int { return 1; }
		public function getMaxTokens(): int { return 192; }
		public function getTemperature() { return null; }
		public function getSystemInstruction() { return null; }
	} );
	$generate = static function ( int $draft = 321 ) use ( $model ) {
		$input = array( 'context' => $draft );
		$ability = 'ai/title-generation';
		Npcink_Cloud_WordPress_AI_Connector::begin_wordpress_ai_ability_context( $ability, $input );
		try {
			return $model->generateTextResult( array( new \WordPress\AiClient\Messages\DTO\Message(
				\WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
				array( new \WordPress\AiClient\Messages\DTO\MessagePart( 'Draft source.' ) )
			) ) );
		} catch ( \WordPress\AiClient\Common\Exception\RuntimeException $error ) {
			return $error->getMessage();
		} finally {
			Npcink_Cloud_WordPress_AI_Connector::end_wordpress_ai_ability_context( $ability, $input, null );
		}
	};
	$queue = static function ( int $status, array $envelope ): void {
		$GLOBALS['maca_http_response_queue'][] = array( 'response' => array( 'code' => $status ), 'body' => wp_json_encode( $envelope ) );
	};
	$success = static function ( string $run ): array {
		return array( 'status' => 'ok', 'data' => array( 'status' => 'succeeded', 'run_id' => $run, 'result' => array(
			'contract_version'   => 'cloud_connector_result.v1',
			'suggestion_only'    => true,
			'connector_id'       => 'npcink-cloud-addon',
			'operation_contract' => array( 'contract_version' => 'wordpress_operation.v1', 'task' => 'title_generation' ),
			'output'             => array( 'output_text' => 'Recovered title' ),
		) ) );
	};

	$GLOBALS['maca_http_response_queue'][] = new \WP_Error( 'http_request_failed', 'Fixture transport timeout' );
	$notice = $generate();
	$key = $GLOBALS['maca_http_requests'][0]['args']['headers']['Idempotency-Key'];
	maca_assert( is_string( $notice ) && str_contains( $notice, 'check the original request' ) && array( $key ) === array_values( $GLOBALS['maca_transients'] ), 'Ambiguous dispatch shows a nonterminal notice and stores only the original key.' );

	$queue( 404, array( 'status' => 'error', 'error_code' => 'runtime.run_not_found' ) );
	maca_assert( $notice === $generate(), 'Missing receipt does not authorize a new execution.' );
	$receipt = array( 'status' => 'running', 'run_id' => 'run_original', 'idempotency_key' => $key );
	$queue( 200, array( 'status' => 'ok', 'data' => $receipt ) );
	maca_assert( $notice === $generate(), 'Running receipt retains the original request for the next click.' );

	$queue( 200, $success( 'run_other_draft' ) );
	maca_assert( 'run_other_draft' === $generate( 322 )->id, 'Identical inputs in a different draft cannot recover the earlier draft result.' );

	$receipt['status'] = 'succeeded';
	$queue( 200, array( 'status' => 'ok', 'data' => $receipt ) );
	$queue( 200, $success( 'run_original' ) );
	$result = $generate();
	$requests = $GLOBALS['maca_http_requests'];
	maca_assert(
		$result instanceof \WordPress\AiClient\Results\DTO\GenerativeAiResult
		&& 'run_original' === $result->id && 'Recovered title' === $result->candidates[0]->message->parts[0]->getText()
		&& empty( $GLOBALS['maca_transients'] )
		&& array( 'POST', 'GET', 'GET', 'POST', 'GET', 'GET' ) === array_map( static fn ( array $request ): string => $request['args']['method'], $requests )
		&& str_ends_with( $requests[4]['url'], '/by-idempotency/' . $key )
		&& str_ends_with( $requests[5]['url'], '/run_original/result' ),
		'Editor recovers the original title and run ID through signed reads, with no second POST for that draft.'
	);

	$queue( 504, array( 'status' => 'error', 'error_code' => 'provider.timeout', 'data' => array( 'run_id' => 'run_failed' ) ) );
	maca_assert( is_string( $generate() ) && empty( $GLOBALS['maca_transients'] ), 'A definite Cloud Provider failure does not enter transport recovery.' );

	foreach ( array( 409 => 'runtime.result_not_ready', 410 => 'runtime.result_expired' ) as $status => $code ) {
		$GLOBALS['maca_http_response_queue'][] = new \WP_Error( 'http_request_failed', 'Fixture transport timeout' );
		maca_assert( $notice === $generate(), 'An unconfirmed dispatch starts a new recovery window.' );
		$request = end( $GLOBALS['maca_http_requests'] );
		$receipt['idempotency_key'] = $request['args']['headers']['Idempotency-Key'];
		$before = count( $GLOBALS['maca_http_requests'] );
		$queue( 200, array( 'status' => 'ok', 'data' => $receipt ) );
		$queue( $status, array( 'status' => 'error', 'error_code' => $code ) );
		$unavailable = $generate();
		maca_assert(
			is_string( $unavailable ) && str_contains( $unavailable, 'no longer available' )
			&& empty( $GLOBALS['maca_transients'] ) && $before + 2 === count( $GLOBALS['maca_http_requests'] ),
			'A successful run with no retained result reports unavailability without a new dispatch.'
		);
	}
}
