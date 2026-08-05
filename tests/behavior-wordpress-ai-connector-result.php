<?php
/**
 * Behavior tests for task-bound WordPress AI connector result parsing.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

namespace WordPress\AiClient\Providers {
	abstract class AbstractProvider {}
}

namespace WordPress\AiClient\Providers\Contracts {
	interface ProviderAvailabilityInterface {}
	interface ModelMetadataDirectoryInterface {}
}

namespace WordPress\AiClient\Providers\Models\Contracts {
	interface ModelInterface {}
}

namespace WordPress\AiClient\Providers\Models\TextGeneration\Contracts {
	interface TextGenerationModelInterface {}
}

namespace WordPress\AiClient\Providers\Models\ImageGeneration\Contracts {
	interface ImageGenerationModelInterface {}
}

namespace WordPress\AiClient\Common\Exception {
	class RuntimeException extends \RuntimeException {}
}

namespace WordPress\AI\Abilities\Suggest_Reply {
	final class Suggest_Reply {
		public function detected_scene_ability_name(): string {
			$model  = ( new \ReflectionClass( 'Npcink_Cloud_WordPress_AI_Text_Model' ) )->newInstanceWithoutConstructor();
			$method = new \ReflectionMethod( $model, 'detect_scene_ability_name' );
			$method->setAccessible( true );

			return (string) $method->invoke( $model );
		}
	}
}

namespace {
	require_once __DIR__ . '/helpers.php';

	maca_load_addon_classes();
	require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-connector.php';

	/**
	 * Invokes one private connector result parser without constructing AI Client DTOs.
	 *
	 * @param string              $model_class Model class name.
	 * @param array<string,mixed> $response Cloud response.
	 * @param string              $expected_task Expected WordPress task.
	 * @return string
	 */
	function maca_invoke_connector_result_parser( string $model_class, array $response, string $expected_task ): string {
		$model  = ( new \ReflectionClass( $model_class ) )->newInstanceWithoutConstructor();
		$parser = new \ReflectionMethod( $model_class, 'extract_text' );
		$parser->setAccessible( true );

		return (string) $parser->invoke( $model, $response, $expected_task );
	}

	$parser_models = array(
		'Npcink_Cloud_WordPress_AI_Text_Model'        => 'content_summary',
		'Npcink_Cloud_WordPress_AI_Vision_Text_Model' => 'alt_text_suggest',
	);
	$parser_successes  = true;
	$parser_rejections = true;
	foreach ( $parser_models as $parser_model => $parser_task ) {
		$valid_response = array(
			'data' => array(
				'result' => array(
					'contract_version'   => 'cloud_connector_result.v1',
					'suggestion_only'    => true,
					'connector_id'       => 'npcink-cloud-addon',
					'operation_contract' => array(
						'contract_version' => 'wordpress_operation.v1',
						'task'             => $parser_task,
					),
					'output'             => array( 'output_text' => 'Bound connector output.' ),
				),
			),
		);
		$parser_successes = $parser_successes
			&& 'Bound connector output.' === maca_invoke_connector_result_parser( $parser_model, $valid_response, $parser_task );

		$wrong_task = $valid_response;
		$wrong_task['data']['result']['operation_contract']['task'] = 'different_task';
		$wrong_result_contract = $valid_response;
		$wrong_result_contract['data']['result']['contract_version'] = 'other_result.v1';
		$write_capable = $valid_response;
		$write_capable['data']['result']['suggestion_only'] = false;
		$wrong_connector = $valid_response;
		$wrong_connector['data']['result']['connector_id'] = 'other-connector';
		$wrong_operation_contract = $valid_response;
		$wrong_operation_contract['data']['result']['operation_contract']['contract_version'] = 'other_operation.v1';
		$missing_operation_contract = $valid_response;
		unset( $missing_operation_contract['data']['result']['operation_contract'] );

		foreach ( array( $wrong_task, $wrong_result_contract, $write_capable, $wrong_connector, $wrong_operation_contract, $missing_operation_contract ) as $invalid_response ) {
			$parser_rejections = $parser_rejections
				&& '' === maca_invoke_connector_result_parser( $parser_model, $invalid_response, $parser_task );
		}
	}

	maca_assert(
		$parser_successes,
		'Behavior: text and vision connector parsers accept only the task-bound Cloud connector result envelope.'
	);
	maca_assert(
		$parser_rejections,
		'Behavior: text and vision connector parsers reject task, suggestion posture, connector, and operation contract mismatches.'
	);

	maca_assert(
		'ai/suggest-reply' === ( new \WordPress\AI\Abilities\Suggest_Reply\Suggest_Reply() )->detected_scene_ability_name(),
		'Behavior: the text connector recognizes the WordPress AI suggest reply ability as a bounded scene.'
	);

	maca_reset_test_state();
	maca_seed_settings( true );
	$image_bytes = base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true );
	maca_assert( is_string( $image_bytes ), 'Fixture: generated image artifact bytes decode.' );
	$image_artifact_id = 'art_' . str_repeat( 'a', 32 );
	$image_delivery_id = 'mdl_' . str_repeat( 'b', 32 );
	$image_checksum    = 'sha256:' . hash( 'sha256', $image_bytes );
	$image_expires_at  = gmdate( 'Y-m-d\TH:i:s\Z', time() + 1800 );
	$image_ack_deadline = gmdate( 'Y-m-d\TH:i:s\Z', time() + 600 );
	$image_result = array(
		'contract_version'     => 'image_generation_result.v1',
		'artifact_type'        => 'image_generation_artifacts',
		'operation'            => 'image.generate.v1',
		'artifacts'            => array(
			array(
				'artifact_id'        => $image_artifact_id,
				'artifact_reference' => array( 'artifact_id' => $image_artifact_id ),
				'status'             => 'available',
				'media_kind'         => 'image',
				'operation'          => 'image.generate.v1',
				'content_type'       => 'image/png',
				'format'             => 'png',
				'width'              => 1,
				'height'             => 1,
				'filesize_bytes'     => strlen( $image_bytes ),
				'checksum'           => $image_checksum,
				'expires_at'         => $image_expires_at,
				'purged_at'          => null,
			),
		),
		'suggestion_only'      => true,
		'requires_local_review' => true,
	);
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array(
			'Content-Type'                   => 'image/png',
			'Content-Length'                 => (string) strlen( $image_bytes ),
			'X-Npcink-Artifact-Id'           => $image_artifact_id,
			'X-Npcink-Artifact-Checksum'     => $image_checksum,
			'X-Npcink-Delivery-Id'           => $image_delivery_id,
			'X-Npcink-Delivery-Ack-Deadline' => $image_ack_deadline,
		),
		'body'     => $image_bytes,
	);
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'contract_version'      => 'media_artifact_delivery_ack.v1',
					'delivery_id'           => $image_delivery_id,
					'artifact_id'           => $image_artifact_id,
					'status'                => 'acknowledged',
					'received_byte_size'    => strlen( $image_bytes ),
					'received_checksum'     => $image_checksum,
					'byte_size_verified'    => true,
					'checksum_verified'     => true,
					'acknowledged_at'       => gmdate( 'Y-m-d\TH:i:s\Z' ),
					'artifact_expires_at'   => $image_expires_at,
					'idempotent_replay'     => false,
					'acknowledgement_scope' => 'verified_transfer_only',
				),
			)
		),
	);
	$image_model  = ( new \ReflectionClass( 'Npcink_Cloud_WordPress_AI_Image_Model' ) )->newInstanceWithoutConstructor();
	$image_parser = new \ReflectionMethod( $image_model, 'download_artifact_images' );
	$image_parser->setAccessible( true );
	$image_outputs = $image_parser->invoke( $image_model, $image_result, 'trace-image-result' );
	$image_pull_request = $GLOBALS['maca_http_requests'][0] ?? array();
	$image_ack_request  = $GLOBALS['maca_http_requests'][1] ?? array();
	maca_assert(
		array( array( 'b64_json' => base64_encode( $image_bytes ), 'mime_type' => 'image/png' ) ) === $image_outputs,
		'Behavior: the WordPress AI image connector converts a verified Cloud artifact into one inline preview candidate.'
	);
	maca_assert(
		str_ends_with( (string) ( $image_pull_request['url'] ?? '' ), '/v1/runtime/media/artifacts/' . $image_artifact_id . '/download' )
		&& 'GET' === ( $image_pull_request['args']['method'] ?? null )
		&& str_ends_with( (string) ( $image_ack_request['url'] ?? '' ), '/v1/runtime/media/artifacts/' . $image_artifact_id . '/delivery-ack' )
		&& 'POST' === ( $image_ack_request['args']['method'] ?? null ),
		'Behavior: image candidate recovery uses only the signed artifact pull and verified-transfer ACK endpoints.'
	);
	$connector_source = file_get_contents( MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-connector.php' );
	maca_assert(
		is_string( $connector_source )
		&& false === strpos( $connector_source, "\$result['images']" )
		&& false === strpos( $connector_source, "\$image['url']" ),
		'Behavior: the WordPress AI image connector has no legacy inline Base64 or URL result bypass.'
	);

	$invalid_image_results = array();
	$wrong_operation = $image_result;
	$wrong_operation['operation'] = 'image.transform.v1';
	$invalid_image_results[] = $wrong_operation;
	$legacy_inline_result = array(
		'images' => array(
			array(
				'b64_json' => base64_encode( $image_bytes ),
				'mime_type' => 'image/png',
			),
		),
	);
	$invalid_image_results[] = $legacy_inline_result;
	$too_many_candidates = $image_result;
	$too_many_candidates['artifacts'] = array_fill( 0, 5, $image_result['artifacts'][0] );
	$invalid_image_results[] = $too_many_candidates;
	$string_width = $image_result;
	$string_width['artifacts'][0]['width'] = '1';
	$invalid_image_results[] = $string_width;
	$impossible_expiry = $image_result;
	$impossible_expiry['artifacts'][0]['expires_at'] = '2027-02-31T12:00:00Z';
	$invalid_image_results[] = $impossible_expiry;
	$non_utc_expiry = $image_result;
	$non_utc_expiry['artifacts'][0]['expires_at'] = '2027-02-28T20:00:00+08:00';
	$invalid_image_results[] = $non_utc_expiry;
	$purged_artifact = $image_result;
	$purged_artifact['artifacts'][0]['purged_at'] = gmdate( 'Y-m-d\TH:i:s\Z' );
	$invalid_image_results[] = $purged_artifact;

	$invalid_results_rejected = true;
	foreach ( $invalid_image_results as $invalid_image_result ) {
		maca_reset_test_state();
		maca_seed_settings( true );
		try {
			$invalid_output = $image_parser->invoke( $image_model, $invalid_image_result, 'trace-invalid-image-result' );
			$invalid_results_rejected = $invalid_results_rejected
				&& array() === $invalid_output
				&& array() === $GLOBALS['maca_http_requests'];
		} catch ( \WordPress\AiClient\Common\Exception\RuntimeException $error ) {
			$invalid_results_rejected = $invalid_results_rejected
				&& array() === $GLOBALS['maca_http_requests'];
		}
	}
	maca_assert(
		$invalid_results_rejected,
		'Behavior: image candidate recovery rejects legacy inline output, wrong operations, excess candidates, coerced facts, invalid UTC timestamps, and purged artifacts before HTTP.'
	);

	maca_reset_test_state();
	maca_seed_settings( true );
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array(
			'Content-Type'                   => 'image/png',
			'Content-Length'                 => (string) strlen( $image_bytes ),
			'X-Npcink-Artifact-Id'           => $image_artifact_id,
			'X-Npcink-Artifact-Checksum'     => $image_checksum,
			'X-Npcink-Delivery-Id'           => $image_delivery_id,
			'X-Npcink-Delivery-Ack-Deadline' => $image_ack_deadline,
		),
		'body'     => $image_bytes,
	);
	$changed_expiry = gmdate( 'Y-m-d\TH:i:s\Z', time() + 1700 );
	$GLOBALS['maca_http_response_queue'][] = array(
		'response' => array( 'code' => 200 ),
		'headers'  => array( 'Content-Type' => 'application/json' ),
		'body'     => wp_json_encode(
			array(
				'status' => 'ok',
				'data'   => array(
					'contract_version'      => 'media_artifact_delivery_ack.v1',
					'delivery_id'           => $image_delivery_id,
					'artifact_id'           => $image_artifact_id,
					'status'                => 'acknowledged',
					'received_byte_size'    => strlen( $image_bytes ),
					'received_checksum'     => $image_checksum,
					'byte_size_verified'    => true,
					'checksum_verified'     => true,
					'acknowledged_at'       => gmdate( 'Y-m-d\TH:i:s\Z' ),
					'artifact_expires_at'   => $changed_expiry,
					'idempotent_replay'     => false,
					'acknowledgement_scope' => 'verified_transfer_only',
				),
			)
		),
	);
	$changed_expiry_rejected = false;
	try {
		$image_parser->invoke( $image_model, $image_result, 'trace-changed-expiry-ack' );
	} catch ( \WordPress\AiClient\Common\Exception\RuntimeException $error ) {
		$changed_expiry_rejected = str_contains( $error->getMessage(), 'acknowledgement is invalid' );
	}
	maca_assert(
		$changed_expiry_rejected && 2 === count( $GLOBALS['maca_http_requests'] ),
		'Behavior: image candidate recovery rejects an otherwise valid ACK that changes the artifact expiry binding.'
	);
}
