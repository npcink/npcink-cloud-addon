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
}
