<?php
/**
 * Regression: signed provider calls obey the official approval store before transport.
 *
 * @package NpcinkCloudAddon
 */
declare(strict_types=1);

namespace WordPress\AI\Experiments\Connector_Approval {
	final class Connector_Approval {
		public function is_enabled(): bool { return ! empty( $GLOBALS['approval_enabled'] ); }
	}
}
namespace WordPress\AI\Connector_Approval {
	final class Caller_Identifier {
		public function identify( array $infrastructure ): ?array {
			$GLOBALS['approval_infrastructure'] = $infrastructure;
			return $GLOBALS['approval_caller'] ?? null;
		}
	}
	final class Approvals_Store {
		public function is_approved( string $caller, string $connector ): bool {
			$GLOBALS['approval_checked'] = array( $caller, $connector );
			return ! empty( $GLOBALS['approval_allowed'] );
		}
		public function record_pending( array $caller, string $connector ): void {
			$GLOBALS['approval_pending'][] = array( $caller['basename'], $connector );
		}
	}
}
namespace WordPress\AiClient\Common\Exception {
	class RuntimeException extends \RuntimeException {}
}
namespace {
	require_once __DIR__ . '/helpers.php';
	require_once __DIR__ . '/wordpress-ai-client-stubs.php';
	maca_load_addon_classes();
	require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-connector.php';
	require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-scene-model.php';
	foreach ( array( 'text', 'vision-text', 'image' ) as $model ) {
		require_once MACA_TEST_ROOT . '/includes/class-cloud-wordpress-ai-' . $model . '-model.php';
	}
	function plugin_basename( string $file ): string { return 'npcink-cloud-addon/npcink-cloud-addon.php'; }
	maca_reset_test_state();
	maca_assert( null === Npcink_Cloud_WordPress_AI_Approval::check(), 'Disabled host approval leaves existing provider access unchanged.' );
	$GLOBALS['approval_enabled'] = true;
	maca_assert( null === Npcink_Cloud_WordPress_AI_Approval::check(), 'Core/CLI without an extension caller follows the official allowance.' );
	$GLOBALS['approval_caller'] = array( 'type' => 'plugin', 'basename' => 'ai/ai.php', 'name' => 'AI' );
	foreach ( array( 'Text' => 'generateTextResult', 'Vision_Text' => 'generateTextResult', 'Image' => 'generateImageResult' ) as $kind => $method ) {
		$model = ( new ReflectionClass( 'Npcink_Cloud_WordPress_AI_' . $kind . '_Model' ) )->newInstanceWithoutConstructor();
		$denied = false;
		try {
			$model->$method( array() );
		} catch ( \WordPress\AiClient\Common\Exception\RuntimeException $error ) {
			$denied = str_contains( $error->getMessage(), 'pending connector approval' );
		}
		maca_assert( $denied && empty( $GLOBALS['maca_http_requests'] ), $kind . ' provider denies an unapproved caller before upload, signing or HTTP.' );
	}
	maca_assert( 3 === count( $GLOBALS['approval_pending'] ) && array( 'ai/ai.php', 'npcink-cloud' ) === $GLOBALS['approval_checked'], 'Denials use the official caller/connector pair and pending store.' );
	$GLOBALS['approval_allowed'] = true;
	maca_assert( null === Npcink_Cloud_WordPress_AI_Approval::check() && 3 === count( $GLOBALS['approval_pending'] ), 'Approval in the host store immediately permits the same caller without another pending entry.' );
	$GLOBALS['approval_allowed'] = false;
	maca_assert( is_wp_error( Npcink_Cloud_WordPress_AI_Approval::check() ), 'Revoking host approval takes effect without cached permission truth.' );
	$rest_error = Npcink_Cloud_WordPress_AI_Approval::explain_blocked_rest_error( new WP_Error( 'prompt_builder_error', 'SDK wrapper' ) );
	maca_assert( 'wpai_connector_not_approved' === $rest_error->get_error_code() && 403 === $rest_error->get_error_data()['status'], 'REST retains the native approval code and 403 status across the AI Client wrapper.' );
	$unrelated = new WP_Error( 'other_error', 'Unrelated' );
	maca_assert( $unrelated === Npcink_Cloud_WordPress_AI_Approval::explain_blocked_rest_error( $unrelated ), 'Approval denial does not replace a later unrelated REST error.' );
	$GLOBALS['approval_enabled'] = false;
	maca_assert( null === Npcink_Cloud_WordPress_AI_Approval::check(), 'Turning off the host feature removes its provider restriction.' );
}
