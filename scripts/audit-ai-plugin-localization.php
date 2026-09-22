<?php
/**
 * Audits the local WordPress AI plugin strings against the bounded zh_CN shim.
 *
 * @package NpcinkCloudAddon
 */

declare(strict_types=1);

$root = dirname( __DIR__ );

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', $root . '/wordpress-stub/' );
}
if ( ! defined( 'NPCINK_CLOUD_ADDON_FILE' ) ) {
	define( 'NPCINK_CLOUD_ADDON_FILE', $root . '/npcink-cloud-addon.php' );
}
if ( ! defined( 'NPCINK_CLOUD_ADDON_VERSION' ) ) {
	define( 'NPCINK_CLOUD_ADDON_VERSION', 'audit' );
}

require_once $root . '/includes/class-ai-plugin-localization.php';

/**
 * Resolves the AI plugin path from CLI args, env, or local defaults.
 *
 * @param array<int,string> $argv CLI arguments.
 * @param string            $root Repository root.
 * @return string
 */
function npcink_cloud_addon_ai_i18n_audit_resolve_path( array $argv, string $root ): string {
	foreach ( $argv as $arg ) {
		if ( 0 === strpos( $arg, '--path=' ) ) {
			return substr( $arg, 7 );
		}
	}

	$env_path = getenv( 'AI_PLUGIN_PATH' );
	if ( is_string( $env_path ) && '' !== $env_path ) {
		return $env_path;
	}

	$candidates = array(
		dirname( $root ) . '/ai',
		'/Users/muze/Local Sites/magick-ai/app/public/wp-content/plugins/ai',
	);

	foreach ( $candidates as $candidate ) {
		if ( is_dir( $candidate ) ) {
			return $candidate;
		}
	}

	return '';
}

/**
 * Decodes a simple PHP/JS quoted string payload.
 *
 * @param string $value Raw literal content without quotes.
 * @return string
 */
function npcink_cloud_addon_ai_i18n_audit_decode_literal( string $value ): string {
	$value = (string) preg_replace_callback(
		'/\\\\u([0-9a-fA-F]{4})/',
		static function ( array $matches ): string {
			return html_entity_decode( '&#x' . $matches[1] . ';', ENT_QUOTES, 'UTF-8' );
		},
		$value
	);

	return stripcslashes( $value );
}

/**
 * Adds a discovered source string.
 *
 * @param array<string,array<string,mixed>> $strings Collected strings.
 * @param string                            $text Source text.
 * @param string                            $file Relative file.
 * @return void
 */
function npcink_cloud_addon_ai_i18n_audit_add_string( array &$strings, string $text, string $file ): void {
	if ( '' === $text ) {
		return;
	}

	if ( ! isset( $strings[ $text ] ) ) {
		$strings[ $text ] = array(
			'files' => array(),
		);
	}

	if ( ! in_array( $file, $strings[ $text ]['files'], true ) ) {
		$strings[ $text ]['files'][] = $file;
	}
}

/**
 * Extracts ai-domain source strings from PHP and built JS files.
 *
 * @param string $contents File contents.
 * @param string $relative Relative file path.
 * @param array<string,array<string,mixed>> $strings Collected strings.
 * @return void
 */
function npcink_cloud_addon_ai_i18n_audit_extract_strings( string $contents, string $relative, array &$strings ): void {
	$single_arg_patterns = array(
		'/\b(?:__|esc_html__|esc_attr__|_e|esc_html_e|esc_attr_e)\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*,\s*([\'"])ai\3/s',
		'/\b_x\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*,.*?,\s*([\'"])ai\3/s',
		'/\b__\)\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*,\s*([\'"])ai\3/s',
		'/\b_x\)\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*,.*?,\s*([\'"])ai\3/s',
	);

	foreach ( $single_arg_patterns as $pattern ) {
		if ( preg_match_all( $pattern, $contents, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				npcink_cloud_addon_ai_i18n_audit_add_string(
					$strings,
					npcink_cloud_addon_ai_i18n_audit_decode_literal( (string) $match[2] ),
					$relative
				);
			}
		}
	}

	$plural_patterns = array(
		'/\b_n\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*,\s*([\'"])((?:\\\\.|(?!\3).)*?)\3\s*,.*?,\s*([\'"])ai\5/s',
		'/\b_n\)\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*,\s*([\'"])((?:\\\\.|(?!\3).)*?)\3\s*,.*?,\s*([\'"])ai\5/s',
	);

	foreach ( $plural_patterns as $pattern ) {
		if ( preg_match_all( $pattern, $contents, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				npcink_cloud_addon_ai_i18n_audit_add_string(
					$strings,
					npcink_cloud_addon_ai_i18n_audit_decode_literal( (string) $match[2] ),
					$relative
				);
				npcink_cloud_addon_ai_i18n_audit_add_string(
					$strings,
					npcink_cloud_addon_ai_i18n_audit_decode_literal( (string) $match[4] ),
					$relative
				);
			}
		}
	}
}

/**
 * Returns whether a file should be scanned.
 *
 * @param string $path File path.
 * @return bool
 */
function npcink_cloud_addon_ai_i18n_audit_should_scan_file( string $path ): bool {
	$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

	return in_array( $extension, array( 'php', 'js', 'jsx', 'mjs', 'cjs', 'ts', 'tsx' ), true );
}

/**
 * Finds near source matches for a missing string.
 *
 * @param string        $missing Missing source.
 * @param array<string> $known Known source strings.
 * @return array<int,string>
 */
function npcink_cloud_addon_ai_i18n_audit_near_matches( string $missing, array $known ): array {
	$near = array();
	foreach ( $known as $candidate ) {
		$max_length = max( strlen( $missing ), strlen( $candidate ) );
		if ( 0 === $max_length ) {
			continue;
		}

		$distance = levenshtein( $missing, $candidate );
		$score    = 1 - ( $distance / $max_length );
		if ( $score >= 0.78 && $missing !== $candidate ) {
			$near[] = $candidate;
		}
	}

	return array_slice( $near, 0, 3 );
}

/**
 * Returns whether any discovered file path starts with a prefix.
 *
 * @param array<int,string> $files File paths.
 * @param array<int,string> $prefixes Path prefixes.
 * @return bool
 */
function npcink_cloud_addon_ai_i18n_audit_file_starts_with( array $files, array $prefixes ): bool {
	foreach ( $files as $file ) {
		foreach ( $prefixes as $prefix ) {
			if ( 0 === strpos( $file, $prefix ) ) {
				return true;
			}
		}
	}

	return false;
}

/**
 * Classifies a missing source string for review.
 *
 * @param string            $text Source text.
 * @param array<int,string> $files Discovered source files.
 * @return string
 */
function npcink_cloud_addon_ai_i18n_audit_classify_missing( string $text, array $files ): string {
	if (
		strlen( $text ) > 220
		|| false !== strpos( $text, 'red marking is only an annotation' )
		|| false !== strpos( $text, 'Outpaint the image' )
		|| false !== strpos( $text, 'professional studio product photo' )
	) {
		return 'long_prompt_copy';
	}

	if (
		preg_match( '/^(The|Whether|Optional|Additional|Current|Cursor|Maximum|Filter|Order|Sort|URL|ID|Attachment|Content|Post|Prompt|Generated|Imported|Information|Confidence|Toxicity)\b/', $text )
		|| false !== strpos( $text, 'schema' )
		|| false !== strpos( $text, 'JSON' )
		|| false !== strpos( $text, 'base64' )
	) {
		return 'schema_or_json_fields';
	}

	// Admin-facing failure notices inside ability files are fixed UI copy,
	// not ability metadata. Surface them separately for review instead of
	// dropping them into the never-translate group below.
	if (
		npcink_cloud_addon_ai_i18n_audit_file_starts_with(
			$files,
			array(
				'includes/Abilities/',
				'includes/Features/',
			)
		)
		&& preg_match( '/\b(failed|could not be generated)\b/i', $text )
		&& false !== strpos( $text, 'Please' )
	) {
		return 'ability_error_notices';
	}

	if (
		npcink_cloud_addon_ai_i18n_audit_file_starts_with(
			$files,
			array(
				'includes/Abilities/',
				'includes/Features/',
				'includes/Experiments/Example_Experiment/',
			)
		)
	) {
		return 'dynamic_ability_metadata';
	}

	return 'fixed_ui_candidates';
}

/**
 * Groups missing source strings by review category.
 *
 * @param array<int,string>                 $missing Missing source strings.
 * @param array<string,array<string,mixed>> $found Discovered strings.
 * @return array<string,array<int,string>>
 */
function npcink_cloud_addon_ai_i18n_audit_group_missing( array $missing, array $found ): array {
	$groups = array(
		'fixed_ui_candidates'      => array(),
		'ability_error_notices'    => array(),
		'dynamic_ability_metadata' => array(),
		'schema_or_json_fields'    => array(),
		'long_prompt_copy'         => array(),
	);

	foreach ( $missing as $text ) {
		$files    = $found[ $text ]['files'] ?? array();
		$category = npcink_cloud_addon_ai_i18n_audit_classify_missing( $text, $files );
		$groups[ $category ][] = $text;
	}

	return $groups;
}

/**
 * Prints one audit string entry.
 *
 * @param string                            $text Source text.
 * @param array<string,array<string,mixed>> $found Discovered strings.
 * @param array<int,string>                 $known Known source strings.
 * @return void
 */
function npcink_cloud_addon_ai_i18n_audit_print_entry( string $text, array $found, array $known ): void {
	$files = $found[ $text ]['files'] ?? array();
	echo '- "' . $text . "\"\n";
	if ( ! empty( $files ) ) {
		echo '  files: ' . implode( ', ', array_slice( $files, 0, 3 ) ) . "\n";
	}
	$near = npcink_cloud_addon_ai_i18n_audit_near_matches( $text, $known );
	if ( ! empty( $near ) ) {
		echo '  near: ' . implode( ' | ', $near ) . "\n";
	}
}

/**
 * Runs the audit and returns the process exit code.
 *
 * @param array<int,string> $argv CLI arguments.
 * @param string            $root Repository root.
 * @return int
 */
function npcink_cloud_addon_ai_i18n_audit_main( array $argv, string $root ): int {
	$plugin_path = npcink_cloud_addon_ai_i18n_audit_resolve_path( $argv, $root );
	if ( '' === $plugin_path || ! is_dir( $plugin_path ) ) {
		fwrite( STDERR, "AI plugin path not found. Set AI_PLUGIN_PATH=/path/to/wp-content/plugins/ai or pass --path=/path/to/ai.\n" );
		return 2;
	}

	$plugin_path  = rtrim( realpath( $plugin_path ) ?: $plugin_path, DIRECTORY_SEPARATOR );
	$translations = Npcink_Cloud_AI_Plugin_Localization::translations();
	$known        = array_keys( $translations );
	$found        = array();

	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $plugin_path, FilesystemIterator::SKIP_DOTS )
	);

	foreach ( $iterator as $file ) {
		if ( ! $file instanceof SplFileInfo || ! $file->isFile() ) {
			continue;
		}

		$path = $file->getPathname();
		if ( ! npcink_cloud_addon_ai_i18n_audit_should_scan_file( $path ) ) {
			continue;
		}

		$contents = file_get_contents( $path );
		if ( ! is_string( $contents ) || false === strpos( $contents, 'ai' ) ) {
			continue;
		}

		$relative = ltrim( substr( $path, strlen( $plugin_path ) ), DIRECTORY_SEPARATOR );
		npcink_cloud_addon_ai_i18n_audit_extract_strings( $contents, $relative, $found );
	}

	ksort( $found );
	$found_keys = array_keys( $found );
	$missing    = array_values( array_diff( $found_keys, $known ) );
	$stale      = array_values( array_diff( $known, $found_keys ) );
	$groups     = npcink_cloud_addon_ai_i18n_audit_group_missing( $missing, $found );

	echo "WordPress AI plugin localization audit\n";
	echo 'AI plugin path: ' . $plugin_path . "\n";
	echo 'Discovered ai-domain strings: ' . count( $found_keys ) . "\n";
	echo 'Shim translations: ' . count( $known ) . "\n";
	echo 'Missing strings: ' . count( $missing ) . "\n";
	echo 'Fixed UI review candidates: ' . count( $groups['fixed_ui_candidates'] ) . "\n";
	echo 'Possibly stale shim strings: ' . count( $stale ) . "\n\n";

	echo "Missing review groups:\n";
	foreach ( $groups as $category => $items ) {
		echo '- ' . $category . ': ' . count( $items ) . "\n";
	}

	foreach ( $groups as $category => $items ) {
		echo "\n" . $category . ":\n";
		if ( empty( $items ) ) {
			echo "- none\n";
		} else {
			foreach ( $items as $text ) {
				npcink_cloud_addon_ai_i18n_audit_print_entry( $text, $found, $known );
			}
		}
	}

	echo "\nstale_review:\n";
	if ( empty( $stale ) ) {
		echo "- none\n";
	} else {
		foreach ( $stale as $text ) {
			echo '- "' . $text . "\"\n";
		}
	}

	echo "\nReview notes:\n";
	echo "- Do not add dynamic ability names, descriptions, schema labels, JSON keys, slugs, provider ids, or model ids to this addon.\n";
	echo "- Add approved fixed UI strings to Npcink_Cloud_AI_Plugin_Localization::translations() with behavior coverage.\n";

	$fail_on_missing = getenv( 'AI_I18N_AUDIT_FAIL_ON_MISSING' );

	return ! empty( $missing ) && '1' === $fail_on_missing ? 1 : 0;
}

if ( isset( $argv ) && is_array( $argv ) && realpath( (string) $argv[0] ) === __FILE__ ) {
	exit( npcink_cloud_addon_ai_i18n_audit_main( $argv, dirname( __DIR__ ) ) );
}
