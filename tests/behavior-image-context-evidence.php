<?php
/**
 * Behavior tests for image context evidence transport.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

maca_load_addon_classes();

maca_seed_settings( true );

$client = new Npcink_Cloud_Runtime_Client( Npcink_Cloud_Addon_Settings::get_settings() );

$invalid_result = $client->request_image_context_evidence(
	array(
		'contract_version'       => 'image_context_evidence_request.v1',
		'write_posture'          => 'suggestion_only',
		'direct_wordpress_write' => true,
		'no_local_model'         => true,
		'no_media_write'         => true,
		'items'                  => array(),
	)
);
maca_assert(
	is_wp_error( $invalid_result ) && 'cloud_image_context_evidence_request_invalid' === $invalid_result->get_error_code(),
	'Behavior: image context evidence rejects request contracts that allow direct writes.'
);

$empty_result = $client->request_image_context_evidence(
	array(
		'contract_version'       => 'image_context_evidence_request.v1',
		'write_posture'          => 'suggestion_only',
		'direct_wordpress_write' => false,
		'no_local_model'         => true,
		'no_media_write'         => true,
		'items'                  => array(
			array(
				'attachment_id' => 101,
				'title'         => 'No URL',
			),
		),
	)
);
maca_assert(
	is_wp_error( $empty_result ) && 'cloud_image_context_evidence_request_empty' === $empty_result->get_error_code(),
	'Behavior: image context evidence requires a bounded media URL or thumbnail URL.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'run_id' => 'run_image_context_1',
			'data'   => array(
				'image_context_evidence' => array(
					'contract_version' => 'image_context_evidence.v1',
					'source'           => array(
						'provider_id'    => 'provider-secret-must-not-project',
						'model_id'       => 'vision-model-test',
						'instance_id'    => 'instance-secret-must-not-project',
						'evidence_basis' => 'hosted_vision_model',
					),
					'items'            => array(
						array(
							'attachment_id'    => 101,
							'visual_summary'   => 'A person standing beside a blue bicycle near a city storefront.',
							'scene'            => 'street storefront',
							'subject_tags'     => array( 'person', 'blue bicycle', 'storefront' ),
							'visible_text'     => array( 'OPEN' ),
							'alt_text_basis'   => array( 'Person', 'beside a blue bicycle' ),
							'caption_basis'    => 'A person and bicycle outside a storefront.',
							'confidence'       => 0.86,
							'uncertainty_flags' => array( 'small_visible_text' ),
							'requires_human_visual_check' => false,
							'direct_wordpress_write' => true,
						),
						array(
							'attachment_id'  => 999,
							'visual_summary' => 'Out-of-request image must be ignored.',
						),
					),
				),
			),
		)
	),
);

$evidence_result = $client->request_image_context_evidence(
	array(
		'contract_version'       => 'image_context_evidence_request.v1',
		'artifact_type'          => 'image_context_evidence_request',
		'runtime_owner'          => 'cloud_or_host_runtime',
		'write_posture'          => 'suggestion_only',
		'direct_wordpress_write' => false,
		'proposal_created'       => false,
		'execution_created'      => false,
		'no_local_model'         => true,
		'no_media_write'         => true,
		'items'                  => array(
			array(
				'attachment_id'            => 101,
				'title'                    => 'Street photo',
				'filename'                 => 'street-photo.jpg',
				'thumbnail_url'            => 'https://example.test/uploads/street-thumb.jpg',
				'url'                      => 'https://example.test/uploads/street.jpg',
				'mime_type'                => 'image/jpeg',
				'current_alt_status'       => 'missing',
				'current_caption_status'   => 'missing',
				'candidate_quality_flags'  => array( 'metadata_insufficient' ),
				'filtered_candidate_notes' => array( 'filtered_alt_title:too_generic' ),
			),
		),
	),
	'trace-image-context',
	'image-context-idempotency'
);
$evidence_request      = end( $GLOBALS['maca_http_requests'] );
$evidence_request_body = json_decode( (string) ( $evidence_request['args']['body'] ?? '' ), true );

maca_assert(
	is_array( $evidence_result )
	&& 'image_context_evidence.v1' === (string) ( $evidence_result['contract_version'] ?? '' )
	&& 1 === (int) ( $evidence_result['evidence_count'] ?? 0 )
	&& false === (bool) ( $evidence_result['direct_wordpress_write'] ?? true )
	&& false === (bool) ( $evidence_result['items'][0]['direct_wordpress_write'] ?? true )
	&& true === (bool) ( $evidence_result['items'][0]['needs_human_visual_check'] ?? false )
	&& true === (bool) ( $evidence_result['items'][0]['requires_human_visual_check'] ?? false )
	&& 'hosted_vision_model' === (string) ( $evidence_result['source'] ?? '' )
	&& 'Person; beside a blue bicycle' === (string) ( $evidence_result['items'][0]['alt_text_basis'] ?? '' )
	&& array( 'person', 'blue bicycle', 'storefront' ) === ( $evidence_result['items'][0]['subject_tags'] ?? array() )
	&& array( 'OPEN' ) === ( $evidence_result['items'][0]['visible_text'] ?? array() )
	&& array( 'small_visible_text' ) === ( $evidence_result['items'][0]['uncertainty_flags'] ?? array() )
	&& 0.86 === ( $evidence_result['items'][0]['confidence'] ?? null )
	&& false === strpos( wp_json_encode( $evidence_result ), 'provider-secret-must-not-project' )
	&& false === strpos( wp_json_encode( $evidence_result ), 'instance-secret-must-not-project' )
	&& 'vision-model-test' === (string) ( $evidence_result['model_id'] ?? '' ),
	'Behavior: image context evidence normalizes current Cloud fields as bounded suggestion-only no-write evidence.'
);

maca_assert(
	is_array( $evidence_request_body )
	&& 'npcink-cloud/image-context-evidence' === (string) ( $evidence_request_body['ability_name'] ?? '' )
	&& 'image_context_evidence_request.v1' === (string) ( $evidence_request_body['contract_version'] ?? '' )
	&& 'vision.ai' === (string) ( $evidence_request_body['profile_id'] ?? '' )
	&& 'image_context_evidence' === (string) ( $evidence_request_body['execution_kind'] ?? '' )
	&& false === (bool) ( $evidence_request_body['policy']['allow_fallback'] ?? true )
	&& 'bounded_media_urls_for_visual_context_only' === (string) ( $evidence_request_body['input']['image_context_evidence_request']['source_policy'] ?? '' )
	&& false === (bool) ( $evidence_request_body['input']['image_context_evidence_request']['direct_wordpress_write'] ?? true )
	&& true === (bool) ( $evidence_request_body['input']['image_context_evidence_request']['no_local_model'] ?? false ),
	'Behavior: image context evidence dispatches through bounded runtime execute payload only.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'image_context_evidence' => array(
					'contract_version' => 'image_context_evidence.v1',
					'items'            => array(
						array(
							'attachment_id'  => 101,
							'visual_summary' => 'A local artifact image.',
						),
					),
				),
			),
		)
	),
);
$artifact_result = $client->request_image_context_evidence(
	array(
		'contract_version'       => 'image_context_evidence_request.v1',
		'write_posture'          => 'suggestion_only',
		'direct_wordpress_write' => false,
		'no_local_model'         => true,
		'no_media_write'         => true,
		'items'                  => array(
			array(
				'attachment_id'     => 101,
				'source_artifact_id' => 'art_0123456789abcdef0123456789abcdef',
				'title'             => 'Local image',
				'filename'          => 'local.png',
				'mime_type'         => 'image/png',
			),
		),
	),
	'trace-image-context-artifact',
	'image-context-artifact-idempotency'
);
$artifact_request      = end( $GLOBALS['maca_http_requests'] );
$artifact_request_body = json_decode( (string) ( $artifact_request['args']['body'] ?? '' ), true );
$artifact_item         = $artifact_request_body['input']['image_context_evidence_request']['items'][0] ?? array();
maca_assert(
	is_array( $artifact_result )
	&& 'internal' === (string) ( $artifact_request_body['data_classification'] ?? '' )
	&& 'bounded_source_artifacts_for_visual_context_only' === (string) ( $artifact_request_body['input']['image_context_evidence_request']['source_policy'] ?? '' )
	&& 'art_0123456789abcdef0123456789abcdef' === (string) ( $artifact_item['source_artifact_id'] ?? '' )
	&& ! isset( $artifact_item['url'] )
	&& ! isset( $artifact_item['thumbnail_url'] ),
	'Behavior: image context evidence sends local images only as internal short-TTL artifact references.'
);

maca_reset_test_state();
maca_seed_settings( true );
$GLOBALS['maca_http_response_queue'][] = array(
	'response' => array( 'code' => 200 ),
	'body'     => wp_json_encode(
		array(
			'status' => 'ok',
			'data'   => array(
				'image_context_evidence' => array(
					'contract_version' => 'image_context_evidence.v1',
					'items'            => array(),
				),
			),
		)
	),
);
$empty_cloud_result = $client->request_image_context_evidence(
	array(
		'contract_version'       => 'image_context_evidence_request.v1',
		'write_posture'          => 'suggestion_only',
		'direct_wordpress_write' => false,
		'no_local_model'         => true,
		'no_media_write'         => true,
		'items'                  => array(
			array(
				'attachment_id' => 102,
				'url'           => 'https://example.test/uploads/empty.jpg',
			),
		),
	)
);
maca_assert(
	is_wp_error( $empty_cloud_result ) && 'cloud_image_context_evidence_empty' === $empty_cloud_result->get_error_code(),
	'Behavior: image context evidence fails closed when Cloud returns no usable evidence.'
);
