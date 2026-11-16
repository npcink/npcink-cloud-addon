<?php
/**
 * Official WordPress AI approval adapter for signed Cloud provider calls.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Uses the host's approval truth; never keeps an Addon permission matrix.
 */
final class Npcink_Cloud_WordPress_AI_Approval {
	/** @var WP_Error|null Last provider denial, for the host REST error projection. */
	private static $blocked = null;
	/**
	 * Checks only AI Client provider execution, before uploads/signing/HTTP.
	 *
	 * The official HTTP guard cannot match transformed HMAC credentials. Use its
	 * own caller identifier and store instead. Unrelated Cloud reads and Site
	 * Knowledge transport do not pass through this provider-only adapter.
	 *
	 * @return WP_Error|null
	 */
	public static function check(): ?WP_Error {
		self::$blocked = null;
		if ( ! class_exists( '\WordPress\AI\Experiments\Connector_Approval\Connector_Approval' )
			|| ! class_exists( '\WordPress\AI\Connector_Approval\Caller_Identifier' )
			|| ! class_exists( '\WordPress\AI\Connector_Approval\Approvals_Store' ) ) {
			return null;
		}
		$feature = new \WordPress\AI\Experiments\Connector_Approval\Connector_Approval();
		if ( ! apply_filters( 'wpai_features_enabled', true ) || ! $feature->is_enabled() ) {
			return null;
		}
		$identifier = new \WordPress\AI\Connector_Approval\Caller_Identifier();
		$caller = $identifier->identify( array( 'plugin:' . explode( '/', plugin_basename( dirname( __DIR__ ) . '/npcink-cloud-addon.php' ) )[0] ) );
		if ( null === $caller ) {
			return null; // Match the host's allowance for core/CLI without an extension caller.
		}
		$store = new \WordPress\AI\Connector_Approval\Approvals_Store();
		$connector_id = Npcink_Cloud_WordPress_AI_Connector::CONNECTOR_ID;
		if ( $store->is_approved( $caller['basename'], $connector_id ) ) {
			return null;
		}
		$store->record_pending( $caller, $connector_id );
		self::$blocked = new WP_Error(
			'wpai_connector_not_approved',
			__( 'Npcink Cloud is pending connector approval. Ask a site administrator to approve it under Tools > Connector Approvals.', 'npcink-cloud-addon' ),
			array( 'status' => 403, 'connector_id' => $connector_id, 'caller' => $caller )
		);
		return self::$blocked;
	}

	/**
	 * Preserves the approval code/status across the AI Client exception boundary.
	 *
	 * @param mixed $response Host callback result.
	 * @return mixed
	 */
	public static function explain_blocked_rest_error( $response ) {
		$blocked = self::$blocked;
		self::$blocked = null;
		return null !== $blocked && is_wp_error( $response ) ? $blocked : $response;
	}
}
