<?php
/**
 * Typed host approval denial, loaded only when the AI Client SDK is available.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Preserves error origin through WordPress's AI Client exception wrapper. */
final class Npcink_Cloud_WordPress_AI_Approval_Exception extends \WordPress\AiClient\Common\Exception\RuntimeException {}
