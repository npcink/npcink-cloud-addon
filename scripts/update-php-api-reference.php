<?php
/**
 * Regenerates the bounded PHP API reference; included by the contract suite in check mode.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

require_once dirname( __DIR__ ) . '/tests/helpers.php';
if ( ! defined( 'NPCINK_CLOUD_ADDON_FILE' ) ) {
	define( 'NPCINK_CLOUD_ADDON_FILE', MACA_TEST_ROOT . '/npcink-cloud-addon.php' );
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}
require_once MACA_TEST_ROOT . '/includes/bootstrap.php';

( static function (): void {
	$direct = __FILE__ === realpath( $_SERVER['SCRIPT_FILENAME'] ?? '' );
	$write = $direct && array( '--write' ) === array_slice( $_SERVER['argv'] ?? array(), 1 );
	$prefix = 'npcink_cloud_addon_';
	$facades = array(
		'is_configured',
		'get_connection_state',
		'get_manual_readiness_result',
		'get_customer_journey_summary',
		'pull_media_artifact',
		'acknowledge_media_artifact_delivery',
		'dispatch_media_derivative_cloud_request',
		'get_media_derivative_run',
		'get_media_derivative_run_result',
		'media_derivative_run_id',
		'public_media_derivative_cloud_projection',
		'media_derivative_artifact_from_cloud_result',
		'build_media_derivative_proposal_payload',
		'build_media_derivative_optimization_payload',
		'receive_media_derivative_artifact',
		'request_image_context_evidence',
		'execute_wordpress_ai_connector_runtime',
		'execute_wordpress_ai_image_generation_runtime',
		'project_ai_task_contract',
		'execute_registered_ai_task_runtime',
		'execute_toolbox_image_generation_runtime',
		'execute_toolbox_audio_generation_runtime',
		'execute_toolbox_site_ops_cloud_analysis_runtime',
		'execute_toolbox_media_governance_audit_runtime',
		'execute_toolbox_web_search_runtime',
		'execute_toolbox_content_format_runtime',
		'execute_toolbox_code_preview_runtime',
		'execute_toolbox_article_review_runtime',
		'execute_toolbox_image_source_runtime',
		'execute_toolbox_content_support_runtime',
		'execute_toolbox_site_helper_runtime',
		'submit_toolbox_nightly_inspection',
		'get_toolbox_nightly_inspection_recent_runs',
		'get_toolbox_runtime_run',
		'get_toolbox_runtime_run_result',
		'retry_toolbox_nightly_inspection',
		'get_toolbox_runtime_entitlement',
		'upload_toolbox_site_media_visual_source',
		'send_agent_feedback_event',
		'get_agent_feedback_summary',
		'dispatch_site_knowledge_runtime',
		'site_knowledge_change_bridge_health',
	);
	$deprecated = array( 'get_settings' );
	$internal = array(
		'validate_toolbox_runtime_payload',
		'upload_wordpress_ai_alt_text_source',
		'bootstrap',
		'maybe_dismiss_activation_notice',
		'render_activation_notice',
		'filter_plugin_action_links',
	);

	$known = array_merge( $facades, $deprecated, $internal );
	$actual = array();
	foreach ( get_defined_functions()['user'] as $name ) {
		if ( str_starts_with( $name, $prefix ) && in_array( ( new ReflectionFunction( $name ) )->getFileName(), array( MACA_TEST_ROOT . '/includes/bootstrap.php', MACA_TEST_ROOT . '/includes/class-cloud-runtime-client-factory.php' ), true ) ) {
			$actual[] = substr( $name, strlen( $prefix ) );
		}
	}
	sort( $known );
	sort( $actual );
	maca_assert( $known === $actual, 'Classify each bootstrap function as supported, deprecated, or internal. Missing: ' . implode( ', ', array_diff( $actual, $known ) ) . '; stale: ' . implode( ', ', array_diff( $known, $actual ) ) );

	$signature = static function ( ReflectionFunctionAbstract $function ): string {
		$parameters = array();
		foreach ( $function->getParameters() as $parameter ) {
			$type = $parameter->getType();
			$declaration = ( null === $type ? '' : $type . ' ' ) . ( $parameter->isPassedByReference() ? '&' : '' ) . ( $parameter->isVariadic() ? '...' : '' ) . '$' . $parameter->getName();
			if ( $parameter->isDefaultValueAvailable() ) {
				$default = $parameter->getDefaultValue();
				$declaration .= ' = ' . ( $parameter->isDefaultValueConstant() ? $parameter->getDefaultValueConstantName() : ( array() === $default ? '[]' : var_export( $default, true ) ) );
			}
			$parameters[] = $declaration;
		}
		$return_type = $function->getReturnType();
		return $function->getName() . '(' . implode( ', ', $parameters ) . ')' . ( null === $return_type ? '' : ': ' . $return_type );
	};
	$function_signature = static function ( string $name ) use ( $prefix, $signature ): string {
		return $signature( new ReflectionFunction( $prefix . $name ) );
	};
	$client = new ReflectionClass( Npcink_Cloud_Runtime_Client::class );
	$client_methods = $client->getMethods( ReflectionMethod::IS_PUBLIC );
	usort( $client_methods, static fn ( ReflectionMethod $a, ReflectionMethod $b ): int => strcmp( $a->getName(), $b->getName() ) );
	$sections = array(
		'facades' => array_map( $function_signature, $facades ),
		'deprecated' => array_map( $function_signature, $deprecated ),
		'transport' => array_map( $signature, $client_methods ),
	);

	foreach ( array( 'README.md', 'docs/cloud-runtime-client-contract.md' ) as $document ) {
		$path = MACA_TEST_ROOT . '/' . $document;
		$original = maca_read( $path );
		$updated = $original;
		foreach ( $sections as $section => $lines ) {
			$start = '<!-- php-api:' . $section . ' -->';
			$end = '<!-- /php-api:' . $section . ' -->';
			$block = $start . "\n```php\n" . implode( "\n", $lines ) . "\n```\n" . $end;
			$updated = preg_replace_callback( '~' . preg_quote( $start, '~' ) . '.*?' . preg_quote( $end, '~' ) . '~s', static fn (): string => $block, $updated, -1, $count );
			maca_assert( 1 === $count && is_string( $updated ), $document . ' contains exactly one PHP reference block: ' . $section );
		}
		if ( $write && $updated !== $original ) {
			maca_assert( strlen( $updated ) === file_put_contents( $path, $updated ), 'Regenerate PHP reference in ' . $document );
		} else {
			maca_assert( $updated === $original, $document . ' matches PHP declarations; regenerate with php scripts/update-php-api-reference.php --write.' );
		}
	}
} )();
