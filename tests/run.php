<?php
/**
 * Aggregate test runner for Npcink Cloud Addon.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

$performance_guard_command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/behavior-performance-guards.php' );
passthru( $performance_guard_command, $performance_guard_status );
if ( 0 !== $performance_guard_status ) {
	exit( $performance_guard_status );
}

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

$custom_cleanup_command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/behavior-cleanup-custom-option.php' );
passthru( $custom_cleanup_command, $custom_cleanup_status );
if ( 0 !== $custom_cleanup_status ) {
	exit( $custom_cleanup_status );
}

$public_api_command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/behavior-public-api.php' );
passthru( $public_api_command, $public_api_status );
if ( 0 !== $public_api_status ) {
	exit( $public_api_status );
}

$pr_body_command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/behavior-pr-body-contract.php' );
passthru( $pr_body_command, $pr_body_status );
if ( 0 !== $pr_body_status ) {
	exit( $pr_body_status );
}

require __DIR__ . '/static-contracts.php';
require __DIR__ . '/behavior-runtime-endpoint-policy.php';
require __DIR__ . '/behavior-runtime-runs-presenter.php';
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
require __DIR__ . '/behavior-image-context-evidence.php';
require __DIR__ . '/behavior-agent-feedback.php';
require __DIR__ . '/behavior-observability-collector.php';
require __DIR__ . '/behavior-editor-assist-quality.php';
require __DIR__ . '/behavior-site-knowledge-change-bridge.php';
require __DIR__ . '/behavior-site-knowledge-runtime-bridge.php';
