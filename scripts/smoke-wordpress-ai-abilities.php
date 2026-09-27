<?php
/**
 * Local WP-CLI smoke gate for the WordPress AI connector surface.
 *
 * Run with:
 * composer run smoke:wp-ai-abilities
 *
 * Optional:
 * WP_AI_SMOKE_USER=1 WP_AI_SMOKE_IMAGE=1 composer run smoke:wp-ai-abilities
 * WP_AI_SMOKE_ALT_TEXT_ATTACHMENT_ID=123 composer run smoke:wp-ai-abilities
 *
 * @package NpcinkCloudAddon
 */

if ( ! defined( 'ABSPATH' ) ) {
	fwrite( STDERR, "[fail] Run this file through WP-CLI eval-file inside a WordPress install.\n" );
	exit( 1 );
}

/**
 * Prints a passing smoke assertion.
 *
 * @param string $message Message.
 * @return void
 */
function npcink_cloud_addon_wp_ai_smoke_ok( string $message ): void {
	fwrite( STDOUT, '[ok] ' . $message . "\n" );
}

/**
 * Prints a failing smoke assertion and exits.
 *
 * @param string $message Message.
 * @return void
 */
function npcink_cloud_addon_wp_ai_smoke_fail( string $message ): void {
	fwrite( STDERR, '[fail] ' . $message . "\n" );
	exit( 1 );
}

/**
 * Selects the smoke user from WP_AI_SMOKE_USER.
 *
 * @return void
 */
function npcink_cloud_addon_wp_ai_smoke_set_user(): void {
	$user_spec = (string) ( getenv( 'WP_AI_SMOKE_USER' ) ?: '1' );
	$user      = false;

	if ( is_numeric( $user_spec ) ) {
		$user = get_user_by( 'id', absint( $user_spec ) );
		if ( ! $user ) {
			$user = get_user_by( 'login', $user_spec );
		}
	} else {
		$user = get_user_by( 'login', $user_spec );
	}

	if ( ! $user || empty( $user->ID ) ) {
		npcink_cloud_addon_wp_ai_smoke_fail( 'Smoke user not found. Set WP_AI_SMOKE_USER to an administrator login or ID.' );
	}

	wp_set_current_user( (int) $user->ID );
}

/**
 * Runs an Abilities REST request in-process.
 *
 * @param string              $method HTTP method.
 * @param string              $route REST route.
 * @param array<string,mixed> $body JSON body.
 * @param array<string,mixed> $params Query params.
 * @return WP_REST_Response
 */
function npcink_cloud_addon_wp_ai_smoke_rest_request( string $method, string $route, array $body = array(), array $params = array() ) {
	$request = new WP_REST_Request( $method, $route );
	foreach ( $params as $key => $value ) {
		$request->set_param( $key, $value );
	}
	if ( ! empty( $body ) ) {
		$request->set_header( 'content-type', 'application/json' );
		$request->set_body( wp_json_encode( $body ) );
	}

	return rest_do_request( $request );
}

/**
 * Returns response data or fails with a concise REST error.
 *
 * @param WP_REST_Response $response REST response.
 * @param string           $label Assertion label.
 * @param int              $expected_status Expected status.
 * @return mixed
 */
function npcink_cloud_addon_wp_ai_smoke_data( $response, string $label, int $expected_status = 200 ) {
	$status = (int) $response->get_status();
	$data   = $response->get_data();
	if ( $expected_status !== $status ) {
		$error = is_array( $data ) ? (string) ( $data['message'] ?? wp_json_encode( $data ) ) : (string) $data;
		npcink_cloud_addon_wp_ai_smoke_fail( $label . ' returned HTTP ' . $status . ': ' . substr( $error, 0, 300 ) );
	}

	return $data;
}

npcink_cloud_addon_wp_ai_smoke_set_user();

$ability_names = array(
	'ai/summarization',
	'ai/meta-description',
	'ai/alt-text-generation',
	'ai/image-generation',
	'ai/image-import',
);

$abilities_response = npcink_cloud_addon_wp_ai_smoke_rest_request(
	'GET',
	'/wp-abilities/v1/abilities',
	array(),
	array( 'per_page' => 100 )
);
$abilities_data     = npcink_cloud_addon_wp_ai_smoke_data( $abilities_response, 'Ability discovery' );
$discovered         = array();

if ( is_array( $abilities_data ) ) {
	foreach ( $abilities_data as $ability ) {
		if ( is_array( $ability ) && isset( $ability['name'] ) ) {
			$discovered[] = (string) $ability['name'];
		}
	}
}

foreach ( $ability_names as $ability_name ) {
	if ( ! in_array( $ability_name, $discovered, true ) ) {
		npcink_cloud_addon_wp_ai_smoke_fail( $ability_name . ' is not discoverable on the first Abilities REST page.' );
	}
	npcink_cloud_addon_wp_ai_smoke_ok( $ability_name . ' is discoverable on the first Abilities REST page.' );
}

$summary_data = npcink_cloud_addon_wp_ai_smoke_data(
	npcink_cloud_addon_wp_ai_smoke_rest_request(
		'POST',
		'/wp-abilities/v1/abilities/ai/summarization/run',
		array(
			'input' => array(
				'content' => 'Codex smoke verifies that the Npcink Cloud connector can run a bounded WordPress AI text ability.',
				'length'  => 'short',
			),
		)
	),
	'Summarization run'
);
npcink_cloud_addon_wp_ai_smoke_ok( 'Summarization run returned ' . strlen( wp_json_encode( $summary_data ) ) . ' bytes of JSON.' );

$meta_data = npcink_cloud_addon_wp_ai_smoke_data(
	npcink_cloud_addon_wp_ai_smoke_rest_request(
		'POST',
		'/wp-abilities/v1/abilities/ai/meta-description/run',
		array(
			'input' => array(
				'content' => 'Codex smoke verifies the Npcink Cloud connector through a bounded WordPress AI meta description ability.',
				'title'   => 'Connector smoke',
			),
		)
	),
	'Meta description run'
);
npcink_cloud_addon_wp_ai_smoke_ok( 'Meta description run returned ' . strlen( wp_json_encode( $meta_data ) ) . ' bytes of JSON.' );

if ( '1' === (string) getenv( 'WP_AI_SMOKE_IMAGE' ) ) {
	$image_data = npcink_cloud_addon_wp_ai_smoke_data(
		npcink_cloud_addon_wp_ai_smoke_rest_request(
			'POST',
			'/wp-abilities/v1/abilities/ai/image-generation/run',
			array(
				'input' => array(
					'prompt' => 'A simple flat vector illustration of a blue ceramic mug on a white table.',
				),
			)
		),
		'Image generation run'
	);
	npcink_cloud_addon_wp_ai_smoke_ok( 'Image generation run returned ' . strlen( wp_json_encode( $image_data ) ) . ' bytes of JSON.' );
} else {
	npcink_cloud_addon_wp_ai_smoke_ok( 'Image generation run skipped. Set WP_AI_SMOKE_IMAGE=1 to exercise the provider.' );
}

$alt_text_attachment_id = absint( getenv( 'WP_AI_SMOKE_ALT_TEXT_ATTACHMENT_ID' ) ?: 0 );
if ( 0 < $alt_text_attachment_id ) {
	$alt_text_data = npcink_cloud_addon_wp_ai_smoke_data(
		npcink_cloud_addon_wp_ai_smoke_rest_request(
			'POST',
			'/wp-abilities/v1/abilities/ai/alt-text-generation/run',
			array(
				'input' => array(
					'attachment_id' => $alt_text_attachment_id,
					'context'       => 'Codex smoke verifies the Npcink Cloud connector through the WordPress AI alt text ability.',
				),
			)
		),
		'Alt text run'
	);
	npcink_cloud_addon_wp_ai_smoke_ok( 'Alt text run returned ' . strlen( wp_json_encode( $alt_text_data ) ) . ' bytes of JSON.' );
} else {
	npcink_cloud_addon_wp_ai_smoke_ok( 'Alt text run skipped. Set WP_AI_SMOKE_ALT_TEXT_ATTACHMENT_ID to an editable local image attachment ID to exercise the vision provider.' );
}
npcink_cloud_addon_wp_ai_smoke_ok( 'Image import run skipped because it writes media; discovery is covered.' );
