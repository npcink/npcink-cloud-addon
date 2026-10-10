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
require_once __DIR__ . '/local-env.php';

/**
 * Resolves the AI plugin path from CLI args, environment, or local defaults.
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

	// Covers the AI_PLUGIN_PATH environment variable and scripts/.local-env.
	$env_path = npcink_cloud_addon_local_env_value( 'AI_PLUGIN_PATH' );
	if ( '' !== $env_path ) {
		return $env_path;
	}

	$candidate = dirname( $root ) . '/ai';
	if ( is_dir( $candidate ) ) {
		return $candidate;
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
 * Returns whether a file is a JavaScript-family file.
 *
 * Bundled JS is where build pipelines drop the explicit 'ai' domain literal
 * from wp.i18n calls, so the domain-less rescue pass only applies there.
 *
 * @param string $path File path.
 * @return bool
 */
function npcink_cloud_addon_ai_i18n_audit_is_js_file( string $path ): bool {
	$extension = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

	return in_array( $extension, array( 'js', 'jsx', 'mjs', 'cjs', 'ts', 'tsx' ), true );
}

/**
 * Extracts bundled wp.i18n source strings whose call form drops the explicit domain.
 *
 * These discoveries only rescue shim strings from stale_review; they never
 * join the missing review groups because the runtime domain cannot be
 * confirmed from the bundle alone.
 *
 * @param string $contents File contents.
 * @param string $relative Relative file path.
 * @param array<string,array<string,mixed>> $domainless Collected domain-less strings.
 * @return void
 */
function npcink_cloud_addon_ai_i18n_audit_extract_domainless( string $contents, string $relative, array &$domainless ): void {
	$single_arg_patterns = array(
		// __( 'Text' ) and member-call forms like wp.i18n.__( 'Text' ), no domain.
		'/\b__\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*\)/s',
		'/\b__\)\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*\)/s',
		// _x( 'Text', 'context' ), no domain.
		'/\b_x\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*,\s*([\'"])((?:\\\\.|(?!\3).)*?)\3\s*\)/s',
		'/\b_x\)\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*,\s*([\'"])((?:\\\\.|(?!\3).)*?)\3\s*\)/s',
	);

	foreach ( $single_arg_patterns as $pattern ) {
		if ( preg_match_all( $pattern, $contents, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				npcink_cloud_addon_ai_i18n_audit_add_string(
					$domainless,
					npcink_cloud_addon_ai_i18n_audit_decode_literal( (string) $match[2] ),
					$relative
				);
			}
		}
	}

	$plural_patterns = array(
		// _n( 'Singular', 'Plural', <count expression>, no domain literal: anchor
		// to the call close and reject a quoted fourth argument so domain-bearing
		// calls (including foreign domains) never join the domain-less set.
		'/\b_n\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*,\s*([\'"])((?:\\\\.|(?!\3).)*?)\3\s*,\s*(?![\'"])[^,()]*\s*\)/s',
		'/\b_n\)\s*\(\s*([\'"])((?:\\\\.|(?!\1).)*?)\1\s*,\s*([\'"])((?:\\\\.|(?!\3).)*?)\3\s*,\s*(?![\'"])[^,()]*\s*\)/s',
	);

	foreach ( $plural_patterns as $pattern ) {
		if ( preg_match_all( $pattern, $contents, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				npcink_cloud_addon_ai_i18n_audit_add_string(
					$domainless,
					npcink_cloud_addon_ai_i18n_audit_decode_literal( (string) $match[2] ),
					$relative
				);
				npcink_cloud_addon_ai_i18n_audit_add_string(
					$domainless,
					npcink_cloud_addon_ai_i18n_audit_decode_literal( (string) $match[4] ),
					$relative
				);
			}
		}
	}
}

/**
 * Finds a quoted literal occurrence of a shim string inside bundled build output.
 *
 * Bundles keep string literals escaped, so the decoded shim text is probed
 * alongside its common JS escape forms. Mixed unicode escapes stay unprobed;
 * the tier is a conservative safety net, not an exhaustive matcher.
 *
 * @param string $text Shim source string.
 * @param array<string,string> $build_contents Build-relative file path => contents.
 * @return string Relative path of the first quoted occurrence, or '' when absent.
 */
function npcink_cloud_addon_ai_i18n_audit_find_build_literal( string $text, array $build_contents ): string {
	$length = strlen( $text );
	if ( 0 === $length ) {
		return '';
	}

	$variants = array_unique(
		array(
			$text,
			addslashes( $text ),
			(string) addcslashes( $text, "\n\r\t\v\f" ),
		)
	);

	foreach ( $build_contents as $relative => $contents ) {
		foreach ( $variants as $variant ) {
			$offset    = 0;
			$variant_length = strlen( $variant );
			while ( false !== ( $position = strpos( $contents, $variant, $offset ) ) ) {
				$end    = $position + $variant_length;
				$before = $position > 0 ? $contents[ $position - 1 ] : '';
				$after  = $end < strlen( $contents ) ? $contents[ $end ] : '';
				if ( ( '"' === $before || "'" === $before ) && $before === $after ) {
					return (string) $relative;
				}
				$offset = $position + 1;
			}
		}
	}

	return '';
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

	// "Content" also starts feature labels such as "Content Translation",
	// so only lowercase prose like "Content of the post." is schema copy.
	if (
		preg_match( '/^(The|Whether|Optional|Additional|Current|Cursor|Maximum|Filter|Order|Sort|URL|ID|Attachment|Post|Prompt|Generated|Imported|Information|Confidence|Toxicity)\b/', $text )
		|| 1 === preg_match( '/^Content\s+[a-z]/', $text )
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
	$domainless   = array();
	$build_contents = array();

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
		if ( ! is_string( $contents ) ) {
			continue;
		}

		$relative = ltrim( substr( $path, strlen( $plugin_path ) ), DIRECTORY_SEPARATOR );

		// Domain-less bundled calls and bare build literals do not imply an
		// 'ai' substring (the dropped domain literal is what carried it), so
		// collect them before the 'ai' fast-path filter below.
		if ( npcink_cloud_addon_ai_i18n_audit_is_js_file( $path ) ) {
			npcink_cloud_addon_ai_i18n_audit_extract_domainless( $contents, $relative, $domainless );
			if ( 0 === strpos( $relative, 'build' . DIRECTORY_SEPARATOR ) ) {
				$build_contents[ $relative ] = $contents;
			}
		}

		// The 'ai' fast path stays valid for the domain-bearing forms only.
		if ( false === strpos( $contents, 'ai' ) ) {
			continue;
		}

		npcink_cloud_addon_ai_i18n_audit_extract_strings( $contents, $relative, $found );
	}

	ksort( $found );
	$found_keys = array_keys( $found );
	$missing    = array_values( array_diff( $found_keys, $known ) );
	$stale_raw  = array_values( array_diff( $known, $found_keys ) );
	$groups     = npcink_cloud_addon_ai_i18n_audit_group_missing( $missing, $found );

	$stale               = array();
	$domainless_rescued  = array();
	$literal_rescued     = array();
	$literal_files       = array();

	foreach ( $stale_raw as $text ) {
		if ( isset( $domainless[ $text ] ) ) {
			$domainless_rescued[] = $text;
			continue;
		}

		$literal_file = npcink_cloud_addon_ai_i18n_audit_find_build_literal( $text, $build_contents );
		if ( '' !== $literal_file ) {
			$literal_rescued[]        = $text;
			$literal_files[ $text ]   = $literal_file;
			continue;
		}

		$stale[] = $text;
	}

	sort( $stale );
	sort( $domainless_rescued );
	sort( $literal_rescued );

	echo "WordPress AI plugin localization audit\n";
	echo 'AI plugin path: ' . $plugin_path . "\n";
	echo 'Discovered ai-domain strings: ' . count( $found_keys ) . "\n";
	echo 'Shim translations: ' . count( $known ) . "\n";
	echo 'Missing strings: ' . count( $missing ) . "\n";
	echo 'Fixed UI review candidates: ' . count( $groups['fixed_ui_candidates'] ) . "\n";
	echo 'Possibly stale shim strings: ' . count( $stale ) . "\n";
	echo 'Domain-less bundle rescue: ' . count( $domainless_rescued ) . "\n";
	echo 'Build literal rescue: ' . count( $literal_rescued ) . "\n\n";

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

	echo "\ndomainless_rescue:\n";
	if ( empty( $domainless_rescued ) ) {
		echo "- none\n";
	} else {
		foreach ( $domainless_rescued as $text ) {
			npcink_cloud_addon_ai_i18n_audit_print_entry( $text, $domainless, $known );
		}
	}

	echo "\nliteral_only_rescue:\n";
	if ( empty( $literal_rescued ) ) {
		echo "- none\n";
	} else {
		foreach ( $literal_rescued as $text ) {
			echo '- "' . $text . '"';
			if ( isset( $literal_files[ $text ] ) ) {
				echo "\n  files: " . $literal_files[ $text ];
			}
			echo "\n";
		}
	}

	echo "\nReview notes:\n";
	echo "- Do not add dynamic ability names, descriptions, schema labels, JSON keys, slugs, provider ids, or model ids to this addon.\n";
	echo "- Add approved fixed UI strings to Npcink_Cloud_AI_Plugin_Localization::translations() with behavior coverage.\n";
	echo "- Rescue groups are informational: the shim string is still alive in the plugin, but only through bundled calls without an explicit 'ai' domain or as bare build literals.\n";

	$fail_on_missing = getenv( 'AI_I18N_AUDIT_FAIL_ON_MISSING' );

	return ! empty( $missing ) && '1' === $fail_on_missing ? 1 : 0;
}

if ( isset( $argv ) && is_array( $argv ) && realpath( (string) $argv[0] ) === __FILE__ ) {
	exit( npcink_cloud_addon_ai_i18n_audit_main( $argv, dirname( __DIR__ ) ) );
}
