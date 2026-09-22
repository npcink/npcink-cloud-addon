<?php
/**
 * Aggregate test runner for Npcink Cloud Addon.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

// Two sandboxes must stay in subprocesses because their process-global
// replacements cannot coexist with the shared-process tests below:
// - the alt-text handoff test replaces current_user_can/get_post and the two
//   plugin seam functions with signatures the real plugin code and the
//   settings-page and site-knowledge sandboxes cannot share;
// - the site-knowledge admin actions test substitutes the bridge class name,
//   which behavior-site-knowledge-change-bridge.php needs as the real class.
$alt_text_handoff_command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/behavior-wordpress-ai-alt-text-artifact-handoff.php' );
passthru( $alt_text_handoff_command, $alt_text_handoff_status );
if ( 0 !== $alt_text_handoff_status ) {
	exit( $alt_text_handoff_status );
}

$site_knowledge_admin_actions_command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/behavior-site-knowledge-admin-actions.php' );
passthru( $site_knowledge_admin_actions_command, $site_knowledge_admin_actions_status );
if ( 0 !== $site_knowledge_admin_actions_status ) {
	exit( $site_knowledge_admin_actions_status );
}

require __DIR__ . '/behavior-performance-guards.php';
require __DIR__ . '/behavior-public-api.php';
require __DIR__ . '/behavior-pr-body-contract.php';

require __DIR__ . '/static-contracts.php';
require __DIR__ . '/behavior-runtime-endpoint-policy.php';
require __DIR__ . '/behavior-content-format.php';
require __DIR__ . '/behavior-credential-store.php';
require __DIR__ . '/behavior-cleanup.php';
require __DIR__ . '/behavior-outbound-policy.php';
require __DIR__ . '/behavior-cloud-addon-localization.php';
require __DIR__ . '/behavior-wordpress-ai-connector-result.php';
require __DIR__ . '/behavior-wordpress-ai-failure-projection.php';
require __DIR__ . '/behavior-wordpress-ai-connector-registration.php';
require __DIR__ . '/behavior-wordpress-ai-request-log-bridge.php';
require __DIR__ . '/behavior-ai-plugin-localization.php';
require __DIR__ . '/behavior-ai-plugin-localization-audit.php';
require __DIR__ . '/behavior-entitlement-summary.php';
require __DIR__ . '/behavior-settings-page-contract.php';
require __DIR__ . '/behavior-ai-task-contract.php';
require __DIR__ . '/behavior-wordpress-ai-connector-runtime.php';
require __DIR__ . '/behavior-media-derivative.php';
require __DIR__ . '/behavior-media-governance.php';
require __DIR__ . '/behavior-image-context-evidence.php';
require __DIR__ . '/behavior-agent-feedback.php';
require __DIR__ . '/behavior-observability-collector.php';
require __DIR__ . '/behavior-customer-journey.php';
require __DIR__ . '/behavior-editor-assist-quality.php';
require __DIR__ . '/behavior-site-knowledge-change-bridge.php';
require __DIR__ . '/behavior-site-knowledge-runtime-bridge.php';

// Must stay last: it defines NPCINK_CLOUD_ADDON_OPTION_NAME for the whole
// process, and constants cannot be undefined, so any earlier run would make
// later settings and cleanup tests exercise the custom option name.
require __DIR__ . '/behavior-cleanup-custom-option.php';
