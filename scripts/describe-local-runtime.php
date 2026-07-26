<?php
/**
 * Reports the Git worktree actually mounted as Npcink Cloud Addon in a local WordPress site.
 *
 * Usage: composer run local:runtime -- /absolute/path/to/wordpress
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

/**
 * Runs a Git command against the resolved plugin worktree.
 *
 * @param string $worktree Resolved plugin worktree.
 * @param string $arguments Git arguments.
 * @return array{output:string,status:int}
 */
function npcink_cloud_addon_local_runtime_git( string $worktree, string $arguments ): array {
	$output = array();
	$status = 0;
	exec(
		'git -C ' . escapeshellarg( $worktree ) . ' ' . $arguments . ' 2>/dev/null',
		$output,
		$status
	);

	return array(
		'output' => trim( implode( "\n", $output ) ),
		'status' => $status,
	);
}

$site_path = $argv[1] ?? getenv( 'WP_PATH' ) ?: '';
if ( ! is_string( $site_path ) || '' === $site_path ) {
	fwrite( STDERR, "Usage: composer run local:runtime -- /absolute/path/to/wordpress\n" );
	exit( 1 );
}

$site_root = realpath( $site_path );
if ( false === $site_root || ! is_dir( $site_root ) ) {
	fwrite( STDERR, "Local WordPress path does not exist: {$site_path}\n" );
	exit( 1 );
}

$plugin_link = $site_root . '/wp-content/plugins/npcink-cloud-addon';
$worktree    = realpath( $plugin_link );
if ( false === $worktree || ! is_dir( $worktree ) ) {
	fwrite( STDERR, "Npcink Cloud Addon is not installed at: {$plugin_link}\n" );
	exit( 1 );
}

$branch = npcink_cloud_addon_local_runtime_git( $worktree, 'branch --show-current' );
$head   = npcink_cloud_addon_local_runtime_git( $worktree, 'rev-parse --short HEAD' );
$status = npcink_cloud_addon_local_runtime_git( $worktree, 'status --short --branch' );

echo json_encode(
	array(
		'site_root'     => $site_root,
		'plugin_link'   => $plugin_link,
		'plugin_target' => $worktree,
		'is_symlink'    => is_link( $plugin_link ),
		'branch'        => 0 === $branch['status'] ? $branch['output'] : null,
		'head'          => 0 === $head['status'] ? $head['output'] : null,
		'git_status'    => 0 === $status['status'] ? $status['output'] : null,
	),
	JSON_UNESCAPED_SLASHES
) . PHP_EOL;
