#!/usr/bin/env node
/**
 * Opt-in browser evidence for the official WordPress AI 1.2.0 text surfaces.
 *
 * This smoke deliberately separates:
 * - UI review evidence: real editor controls, review modals, visible blocks, and screenshots.
 * - API/data-path evidence: Abilities responses, the explicit post-save request, and WP-CLI snapshots.
 *
 * It is not part of composer test:all. It accepts only a Local hostname and a
 * WordPress environment marked local/development, creates an isolated draft,
 * authenticates with short-lived WP-CLI cookies, and deletes the draft in finally.
 */

import { execFileSync } from 'node:child_process';
import { createHash, randomBytes } from 'node:crypto';
import { existsSync, mkdirSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, resolve } from 'node:path';
import { pathToFileURL } from 'node:url';

const AUTOSAVE_LOCK = 'npcink-cloud-addon-p5-b3-browser-proof';

function env(name, fallback = '') {
	return process.env[name] || fallback;
}

function pass(message) {
	console.log(`PASS: ${message}`);
}

function assert(condition, message) {
	if (!condition) {
		throw new Error(message);
	}
	pass(message);
}

function sha256(value) {
	return createHash('sha256').update(String(value)).digest('hex');
}

function normalizeEvidenceText(value) {
	return String(value || '').replace(/\s+/g, ' ').trim();
}

function ensureParent(filePath) {
	mkdirSync(dirname(filePath), { recursive: true });
}

function wpPath() {
	return env('WP_PATH', '/Users/muze/Local Sites/magick-ai/app/public');
}

function wpCli(args, options = {}) {
	const php = env('WP_CLI_PHP', `${process.env.HOME}/Library/Application Support/Local/lightning-services/php-8.5.3+1/bin/darwin-arm64/bin/php`);
	const wp = env('WP_CLI_BIN', '/opt/homebrew/bin/wp');
	const socket = env('WP_DB_SOCKET', `${process.env.HOME}/Library/Application Support/Local/run/NPb24Zg9g/mysql/mysqld.sock`);

	return execFileSync(
		php,
		[
			'-d',
			'display_errors=0',
			'-d',
			'error_reporting=8191',
			'-d',
			`mysqli.default_socket=${socket}`,
			wp,
			`--path=${wpPath()}`,
			'--no-color',
			...args,
		],
		{
			encoding: 'utf8',
			stdio: ['ignore', 'pipe', 'pipe'],
			...options,
		}
	).trim();
}

function parseJson(output, label) {
	try {
		return JSON.parse(output);
	} catch (error) {
		const candidate = String(output).split(/\r?\n/).reverse().find((line) => line.trim().startsWith('{'));
		if (candidate) {
			try {
				return JSON.parse(candidate);
			} catch (candidateError) {
				// The concise error below includes neither cookies nor request bodies.
			}
		}
		throw new Error(`${label} did not return JSON: ${error.message}`);
	}
}

function phpString(value) {
	return JSON.stringify(String(value));
}

function localBaseUrl(rawValue) {
	let parsed;
	try {
		parsed = new URL(String(rawValue).trim());
	} catch (error) {
		throw new Error(`WP_BASE_URL must be a valid URL: ${error.message}`);
	}

	const hostname = parsed.hostname.toLowerCase();
	const localHost = hostname === 'localhost'
		|| hostname === '127.0.0.1'
		|| hostname === '[::1]'
		|| hostname === '::1'
		|| hostname.endsWith('.local');
	assert(['http:', 'https:'].includes(parsed.protocol), 'Browser smoke accepts only HTTP(S).');
	assert(localHost, `Browser smoke rejects non-Local host ${hostname}.`);
	assert(!parsed.username && !parsed.password && !parsed.search && !parsed.hash, 'Browser smoke URL contains no credentials, query, or fragment.');
	assert(parsed.pathname === '/' || parsed.pathname === '', 'Browser smoke URL must be an origin without an application path.');

	return parsed.origin;
}

async function loadPlaywright() {
	try {
		return await import('playwright');
	} catch (error) {
		const require = createRequire(import.meta.url);
		const paths = String(process.env.NODE_PATH || '').split(':').filter(Boolean);
		try {
			const resolvedModule = require.resolve('playwright', { paths });
			const module = await import(pathToFileURL(resolvedModule).href);
			return module.chromium ? module : module.default;
		} catch (fallbackError) {
			throw new Error(`Playwright is unavailable. Install it or set NODE_PATH to the bundled runtime. ${fallbackError.message || error.message}`);
		}
	}
}

function preflight() {
	return parseJson(
		wpCli([
			'eval',
			`
require_once ABSPATH . 'wp-admin/includes/plugin.php';
$ai_file = WP_PLUGIN_DIR . '/ai/ai.php';
$ai_data = file_exists($ai_file) ? get_plugin_data($ai_file, false, false) : array();
$administrator = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC'));
echo wp_json_encode(array(
	'environment' => wp_get_environment_type(),
	'home_url' => home_url('/'),
	'wordpress_version' => get_bloginfo('version'),
	'ai_active' => is_plugin_active('ai/ai.php'),
	'ai_version' => (string) ($ai_data['Version'] ?? ''),
	'addon_loaded' => class_exists('Npcink_Cloud_Addon_Settings'),
	'addon_version' => defined('NPCINK_CLOUD_ADDON_VERSION') ? NPCINK_CLOUD_ADDON_VERSION : '',
	'addon_verified' => class_exists('Npcink_Cloud_Addon_Settings') && Npcink_Cloud_Addon_Settings::is_verified(),
	'connector_enabled' => class_exists('Npcink_Cloud_Addon_Settings') && Npcink_Cloud_Addon_Settings::is_wordpress_ai_connector_enabled(),
	'features' => array(
		'global' => (bool) get_option('wpai_features_enabled', false),
		'title_generation' => (bool) get_option('wpai_feature_title-generation_enabled', false),
		'summarization' => (bool) get_option('wpai_feature_summarization_enabled', false),
		'content_resizing' => (bool) get_option('wpai_feature_content-resizing_enabled', false),
	),
	'has_administrator' => !empty($administrator),
));
`,
		]),
		'WordPress preflight'
	);
}

function assertReadiness(baseUrl, readiness) {
	assert(['local', 'development'].includes(readiness.environment), `WordPress environment is non-production (${readiness.environment}).`);
	assert(new URL(readiness.home_url).origin === baseUrl, 'WP_BASE_URL matches the Local WordPress home origin.');
	assert(readiness.ai_active && readiness.ai_version === '1.2.0', 'Official WordPress AI 1.2.0 is active.');
	assert(readiness.addon_loaded && readiness.addon_verified && readiness.connector_enabled, 'Verified Cloud Addon connector is enabled for WordPress AI.');
	assert(Object.values(readiness.features).every(Boolean), 'Global, title, summary, and content resizing WordPress AI features are enabled.');
	assert(readiness.has_administrator, 'A local administrator is available for the isolated fixture.');
}

function createFixture(token, fixtureText) {
	return parseJson(
		wpCli([
			'eval',
			`
$user_spec = (string) (getenv('WP_AI_SMOKE_USER') ?: '');
$user = null;
if ('' !== $user_spec) {
	$user = is_numeric($user_spec) ? get_user_by('id', absint($user_spec)) : get_user_by('login', $user_spec);
}
if (!$user) {
	$users = get_users(array('role' => 'administrator', 'number' => 1, 'orderby' => 'ID', 'order' => 'ASC'));
	$user = $users ? $users[0] : null;
}
if (!$user || !user_can($user, 'edit_posts')) {
	fwrite(STDERR, 'No editable administrator is available for the browser smoke.');
	exit(1);
}
$paragraph = static function (string $text): string {
	return '<!-- wp:paragraph -->' . "\n" . '<p>' . esc_html($text) . '</p>' . "\n" . '<!-- /wp:paragraph -->';
};
$content = implode("\n\n", array(
	$paragraph(${phpString(fixtureText.sentinelBefore)}),
	$paragraph(${phpString(fixtureText.targetOriginal)}),
	$paragraph(${phpString(fixtureText.sentinelAfter)}),
	$paragraph(${phpString(fixtureText.filler)}),
));
$post_id = wp_insert_post(array(
	'post_type' => 'post',
	'post_status' => 'draft',
	'post_author' => (int) $user->ID,
	'post_title' => ${phpString(`P5-B3 unsaved fixture ${token}`)},
	'post_content' => $content,
	'comment_status' => 'closed',
	'ping_status' => 'closed',
), true);
if (is_wp_error($post_id)) {
	fwrite(STDERR, $post_id->get_error_message());
	exit(1);
}
echo wp_json_encode(array('post_id' => (int) $post_id, 'author_id' => (int) $user->ID));
`,
		]),
		'Fixture creation'
	);
}

function databaseSnapshot(postId) {
	return parseJson(
		wpCli([
			'eval',
			`
$post_id = ${Number(postId)};
$post = get_post($post_id);
if (!$post instanceof WP_Post) {
	fwrite(STDERR, 'Temporary browser-smoke draft is missing.');
	exit(1);
}
$normalize = static function (string $html): string {
	$text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$text = preg_replace('/\\s+/u', ' ', $text);
	return trim((string) $text);
};
$blocks = parse_blocks((string) $post->post_content);
$top_level = array();
$summary_group_count = 0;
$summary_parts = array();
$resized_paragraph_count = 0;
$walk = static function (array $items, bool $inside_summary = false) use (&$walk, &$summary_group_count, &$summary_parts, &$resized_paragraph_count, $normalize): void {
	foreach ($items as $block) {
		if (!is_array($block) || empty($block['blockName'])) {
			continue;
		}
		$attributes = is_array($block['attrs'] ?? null) ? $block['attrs'] : array();
		$class_name = (string) ($attributes['className'] ?? '');
		$is_summary = 'core/group' === $block['blockName']
			&& (!empty($attributes['aiGeneratedSummary']) || false !== strpos($class_name, 'ai-summarization-summary'));
		if ($is_summary) {
			++$summary_group_count;
		}
		if ('core/paragraph' === $block['blockName'] && !empty($attributes['aiResized'])) {
			++$resized_paragraph_count;
		}
		if ('core/paragraph' === $block['blockName'] && ($inside_summary || $is_summary)) {
			$text = $normalize((string) ($block['innerHTML'] ?? ''));
			if ('' !== $text) {
				$summary_parts[] = $text;
			}
		}
		$walk(is_array($block['innerBlocks'] ?? null) ? $block['innerBlocks'] : array(), $inside_summary || $is_summary);
	}
};
$walk($blocks);
foreach ($blocks as $block) {
	if (!is_array($block) || empty($block['blockName'])) {
		continue;
	}
	$attributes = is_array($block['attrs'] ?? null) ? $block['attrs'] : array();
	$top_level[] = array(
		'name' => (string) $block['blockName'],
		'text' => $normalize((string) ($block['innerHTML'] ?? '')),
		'serialized_hash' => hash('sha256', serialize_block($block)),
		'ai_resized' => !empty($attributes['aiResized']),
		'ai_generated_summary' => !empty($attributes['aiGeneratedSummary']),
		'class_name' => (string) ($attributes['className'] ?? ''),
	);
}
$revision_ids = array_map('absint', array_keys(wp_get_post_revisions($post_id)));
sort($revision_ids, SORT_NUMERIC);
echo wp_json_encode(array(
	'title' => (string) $post->post_title,
	'content_hash' => hash('sha256', (string) $post->post_content),
	'status' => (string) $post->post_status,
	'modified_gmt' => (string) $post->post_modified_gmt,
	'revision_ids' => array_values($revision_ids),
	'summary_group_count' => $summary_group_count,
	'summary_text' => implode("\n", $summary_parts),
	'summary_meta' => (string) get_post_meta($post_id, 'ai_generated_summary', true),
	'resized_paragraph_count' => $resized_paragraph_count,
	'top_level' => $top_level,
));
`,
		]),
		'Database snapshot'
	);
}

function samePersistedSnapshot(left, right) {
	return left.title === right.title
		&& left.content_hash === right.content_hash
		&& left.status === right.status
		&& left.modified_gmt === right.modified_gmt
		&& left.summary_meta === right.summary_meta
		&& JSON.stringify(left.revision_ids) === JSON.stringify(right.revision_ids);
}

function deleteFixture(postId) {
	return parseJson(
		wpCli([
			'eval',
			`$post_id=${Number(postId)}; $deleted=wp_delete_post($post_id, true); echo wp_json_encode(array('deleted'=>(bool)$deleted && null === get_post($post_id)));`,
		]),
		'Fixture cleanup'
	);
}

function authCookies(baseUrl, userId) {
	const authentication = parseJson(
		wpCli([
			'eval',
			`
$user = get_user_by('id', ${Number(userId)});
if (!$user) { fwrite(STDERR, 'Browser-smoke user no longer exists.'); exit(1); }
$expiration = time() + (30 * MINUTE_IN_SECONDS);
$manager = WP_Session_Tokens::get_instance($user->ID);
$token = $manager->create($expiration);
echo wp_json_encode(array(
	'user_id' => (int) $user->ID,
	'session_token' => $token,
	'cookies' => array(
		array('name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie($user->ID, $expiration, 'auth', $token)),
		array('name' => SECURE_AUTH_COOKIE, 'value' => wp_generate_auth_cookie($user->ID, $expiration, 'secure_auth', $token)),
		array('name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie($user->ID, $expiration, 'logged_in', $token))
	)
));
`,
		]),
		'WordPress authentication cookie creation'
	);
	const { hostname, protocol } = new URL(baseUrl);

	return {
		cookies: (authentication.cookies || []).map((cookie) => ({
			name: String(cookie.name || ''),
			value: String(cookie.value || ''),
			domain: hostname,
			path: '/',
			httpOnly: true,
			secure: protocol === 'https:',
			sameSite: 'Lax',
		})).filter((cookie) => cookie.name && cookie.value),
		session: {
			userId: Number(authentication.user_id || 0),
			token: String(authentication.session_token || ''),
		},
	};
}

function destroyAuthSession(session) {
	return parseJson(
		wpCli([
			'eval',
			`
$user_id = ${Number(session.userId)};
$token = ${phpString(session.token)};
$manager = WP_Session_Tokens::get_instance($user_id);
$manager->destroy($token);
echo wp_json_encode(array('destroyed' => empty($manager->get($token))));
`,
		]),
		'WordPress authentication session cleanup'
	);
}

async function waitForVisibleLocator(page, locators, label, timeoutMs = 30000) {
	const deadline = Date.now() + timeoutMs;
	while (Date.now() < deadline) {
		for (const locator of locators) {
			const count = await locator.count().catch(() => 0);
			for (let index = 0; index < count; index += 1) {
				const candidate = locator.nth(index);
				if (await candidate.isVisible().catch(() => false)) {
					return candidate;
				}
			}
		}
		await page.waitForTimeout(100);
	}
	throw new Error(`Timed out waiting for visible ${label}.`);
}

async function waitForCondition(page, predicate, label, timeoutMs = 30000) {
	const deadline = Date.now() + timeoutMs;
	while (Date.now() < deadline) {
		if (await predicate()) {
			return;
		}
		await page.waitForTimeout(100);
	}
	throw new Error(`Timed out waiting for ${label}.`);
}

async function dismissEditorOverlays(page) {
	for (let index = 0; index < 6; index += 1) {
		const guides = page.locator('.components-guide, .edit-post-welcome-guide');
		let visibleGuide = false;
		for (let guideIndex = 0; guideIndex < await guides.count().catch(() => 0); guideIndex += 1) {
			visibleGuide = visibleGuide || await guides.nth(guideIndex).isVisible().catch(() => false);
		}
		const overlays = page.locator('.components-modal__screen-overlay');
		let visibleOverlay = null;
		for (let overlayIndex = 0; overlayIndex < await overlays.count().catch(() => 0); overlayIndex += 1) {
			const candidate = overlays.nth(overlayIndex);
			if (await candidate.isVisible().catch(() => false)) {
				visibleOverlay = candidate;
				break;
			}
		}
		if (!visibleGuide && visibleOverlay === null) {
			return true;
		}
		if (visibleOverlay) {
			const headerButtons = visibleOverlay.locator('.components-modal__header button');
			for (let buttonIndex = 0; buttonIndex < await headerButtons.count().catch(() => 0); buttonIndex += 1) {
				const button = headerButtons.nth(buttonIndex);
				if (await button.isVisible().catch(() => false)) {
					await button.click();
					await page.waitForTimeout(200);
					visibleOverlay = null;
					break;
				}
			}
		}
		if (visibleOverlay) {
			await page.keyboard.press('Escape').catch(() => {});
		}
		await page.waitForTimeout(200);
	}
	return false;
}

async function lockAutosaving(page) {
	return page.evaluate((lockName) => {
		const dispatch = window.wp?.data?.dispatch?.('core/editor');
		if (!dispatch || typeof dispatch.lockPostAutosaving !== 'function') {
			return false;
		}
		dispatch.lockPostAutosaving(lockName);
		return true;
	}, AUTOSAVE_LOCK);
}

async function unlockAutosaving(page) {
	return page.evaluate((lockName) => {
		const dispatch = window.wp?.data?.dispatch?.('core/editor');
		if (!dispatch || typeof dispatch.unlockPostAutosaving !== 'function') {
			return false;
		}
		dispatch.unlockPostAutosaving(lockName);
		return true;
	}, AUTOSAVE_LOCK);
}

async function openDocumentSidebar(page) {
	await page.evaluate(() => {
		for (const [storeName, target] of [
			['core/edit-post', 'edit-post/document'],
			['core/interface', 'core/edit-post/document'],
			['core/editor', 'edit-post/document'],
		]) {
			try {
				const dispatch = window.wp?.data?.dispatch?.(storeName);
				if (dispatch && typeof dispatch.openGeneralSidebar === 'function') {
					dispatch.openGeneralSidebar(target);
					return;
				}
			} catch (error) {
				// WordPress editor versions do not expose every candidate store.
			}
		}
	});
}

async function editorState(page, targetClientId = '') {
	return page.evaluate(({ clientId }) => {
		const editor = window.wp.data.select('core/editor');
		const blockEditor = window.wp.data.select('core/block-editor');
		const blocks = blockEditor.getBlocks();
		const strip = (html) => {
			const node = document.createElement('div');
			node.innerHTML = String(html || '');
			return String(node.textContent || '').replace(/\s+/g, ' ').trim();
		};
		const summaries = blocks.filter((block) => block.name === 'core/group' && block.attributes?.aiGeneratedSummary === true);
		const summaryText = summaries.flatMap((block) => block.innerBlocks || [])
			.map((block) => strip(block.attributes?.content || ''))
			.filter(Boolean)
			.join('\n');
		const target = clientId ? blockEditor.getBlock(clientId) : null;

		return {
			title: String(editor.getEditedPostAttribute('title') || ''),
			dirty: Boolean(editor.isEditedPostDirty()),
			summaryCount: summaries.length,
			summaryText,
			targetContent: String(target?.attributes?.content || ''),
			targetText: strip(target?.attributes?.content || ''),
			targetAiResized: target?.attributes?.aiResized === true,
		};
	}, { clientId: targetClientId });
}

async function selectTargetParagraph(page, marker) {
	return page.evaluate((targetMarker) => {
		const select = window.wp.data.select('core/block-editor');
		const target = select.getBlocks().find((block) => block.name === 'core/paragraph' && String(block.attributes?.content || '').includes(targetMarker));
		if (!target) {
			throw new Error('The exact fixture target paragraph block was not found.');
		}
		window.wp.data.dispatch('core/block-editor').selectBlock(target.clientId);
		return target.clientId;
	}, marker);
}

function requestRestPath(urlValue) {
	try {
		const parsed = new URL(urlValue);
		return decodeURIComponent(parsed.searchParams.get('rest_route') || parsed.pathname);
	} catch (error) {
		return '';
	}
}

function abilityKind(urlValue) {
	const path = requestRestPath(urlValue);
	for (const kind of ['title-generation', 'summarization', 'content-resizing']) {
		if (path.includes(`/ai/${kind}/run`)) {
			return kind;
		}
	}
	return '';
}

function isFixtureWrite(urlValue, method, postId) {
	if (!['POST', 'PUT', 'PATCH', 'DELETE'].includes(String(method).toUpperCase())) {
		return false;
	}
	const path = requestRestPath(urlValue);
	return new RegExp(`/wp/v2/posts/${Number(postId)}(?:/|$)`).test(path);
}

function abilityAction(request) {
	try {
		const payload = JSON.parse(request.postData() || '{}');
		const action = String(payload?.input?.action || '');
		return ['shorten', 'expand', 'rephrase'].includes(action) ? action : '';
	} catch (error) {
		return '';
	}
}

async function visibleMenuItems(page, editorFrame) {
	const menus = [
		page.locator('.components-dropdown-menu__menu:visible').last(),
		editorFrame.locator('.components-dropdown-menu__menu:visible').last(),
	];
	const deadline = Date.now() + 15000;
	while (Date.now() < deadline) {
		for (const menu of menus) {
			if (!await menu.isVisible().catch(() => false)) {
				continue;
			}
			const items = menu.locator('.components-menu-item__button, [role="menuitem"]');
			const visible = [];
			const count = await items.count();
			for (let index = 0; index < count; index += 1) {
				if (await items.nth(index).isVisible().catch(() => false)) {
					visible.push(items.nth(index));
				}
			}
			if (visible.length >= 3) {
				return visible;
			}
		}
		await page.waitForTimeout(100);
	}
	throw new Error('Content resizing menu did not expose its three pinned WordPress AI 1.2.0 controls.');
}

async function captureDiagnostics(page, screenshotPath, abilityResponses, preSaveWrites, error) {
	ensureParent(screenshotPath);
	await page.screenshot({ path: screenshotPath, fullPage: true }).catch(() => {});
	const bodyText = await page.locator('body').innerText({ timeout: 2000 }).catch(() => '');
	console.error(`FAIL: diagnostic screenshot=${screenshotPath}`);
	console.error(`FAIL: current URL=${page.url()}`);
	console.error(`FAIL: ability responses=${JSON.stringify(abilityResponses)}`);
	console.error(`FAIL: pre-save fixture writes=${JSON.stringify(preSaveWrites)}`);
	console.error(`FAIL: visible text sample=${bodyText.replace(/\s+/g, ' ').trim().slice(0, 1000)}`);
	console.error(`FAIL: ${error?.message || String(error)}`);
}

function usage() {
	return `Usage: node scripts/smoke-wordpress-ai-text-browser.mjs [option]

Options:
  --preflight-only  Verify the local WordPress, AI plugin, Addon connector, and feature prerequisites.
                    Does not create a draft, start a browser, or invoke an AI provider.
  -h, --help        Show this help without connecting to WordPress.

With no option, the script runs the complete opt-in browser acceptance.`;
}

function parseCliMode(args) {
	if (args.length === 0) {
		return 'full';
	}
	if (args.length === 1 && ['-h', '--help'].includes(args[0])) {
		return 'help';
	}
	if (args.length === 1 && args[0] === '--preflight-only') {
		return 'preflight';
	}
	console.error(`FAIL: unsupported argument ${args.join(' ')}`);
	console.error(usage());
	process.exit(2);
}

function runPreflightOnly() {
	try {
		const preflightBaseUrl = localBaseUrl(env('WP_BASE_URL', 'https://magick-ai.local'));
		const readiness = preflight();
		assertReadiness(preflightBaseUrl, readiness);
		const summary = {
			contract: 'wordpress_ai_text_browser_preflight.v1',
			mode: 'preflight_only',
			site_origin: preflightBaseUrl,
			environment: readiness.environment,
			versions: {
				wordpress: readiness.wordpress_version,
				wordpress_ai: readiness.ai_version,
				cloud_addon: readiness.addon_version,
			},
			fixture_created: false,
			browser_started: false,
			provider_execution_attempted: false,
			wordpress_write_attempted: false,
		};
		console.log(`WP_AI_TEXT_BROWSER_PREFLIGHT=${JSON.stringify(summary)}`);
		pass(`WordPress AI text browser preflight completed at ${preflightBaseUrl}.`);
		process.exit(0);
	} catch (error) {
		console.error(`FAIL: WordPress AI text browser preflight: ${error.message || error}`);
		process.exit(1);
	}
}

const cliMode = parseCliMode(process.argv.slice(2));
if (cliMode === 'help') {
	console.log(usage());
	process.exit(0);
}
if (cliMode === 'preflight') {
	runPreflightOnly();
}

const artifactDir = resolve(env('WP_AI_TEXT_ARTIFACT_DIR', '/tmp/npcink-cloud-addon-p5-b3'));
const reviewScreenshotPath = resolve(env('WP_AI_TEXT_REVIEW_SCREENSHOT', `${artifactDir}/wordpress-ai-text-review.png`));
const savedScreenshotPath = resolve(env('WP_AI_TEXT_SAVED_SCREENSHOT', `${artifactDir}/wordpress-ai-text-saved.png`));
const failureScreenshotPath = resolve(env('WP_AI_TEXT_FAILURE_SCREENSHOT', `${artifactDir}/wordpress-ai-text-failure.png`));
const summaryPath = env('WP_AI_TEXT_SUMMARY_PATH', '');

const token = randomBytes(6).toString('hex');
const fixtureText = {
	sentinelBefore: `P5B3-BEFORE-${token} remains exactly unchanged. This paragraph is a non-target sentinel for the browser proof.`,
	targetOriginal: `P5B3-TARGET-${token} is the selected whole paragraph block. Rephrase this sentence clearly while preserving its practical meaning.`,
	sentinelAfter: `P5B3-AFTER-${token} remains exactly unchanged. This paragraph proves that an adjacent block is not rewritten.`,
	filler: `P5B3-FILLER-${token} remains exactly unchanged. The temporary draft describes a small editorial workflow in enough detail for title and summary generation. An editor reviews a Cloud suggestion inside WordPress, accepts only the selected whole paragraph block, and then performs one normal local save. The browser proof separates suggestion generation from local persistence so that Cloud never appears to own the WordPress write. This additional context intentionally keeps the content above the official WordPress AI minimum character threshold.`,
};

let baseUrl = '';
let browser = null;
let page = null;
let postId = 0;
let autosaveLocked = false;
let manualSaveStarted = false;
let authSession = null;
let authSessionDestroyed = false;
let failure = null;
let machineSummary = null;
let cleanupDeleted = false;
const abilityResponses = [];
const preSaveWrites = [];
const saveWrites = [];

try {
	baseUrl = localBaseUrl(env('WP_BASE_URL', 'https://magick-ai.local'));
	const readiness = preflight();
	assertReadiness(baseUrl, readiness);

	const fixture = createFixture(token, fixtureText);
	postId = Number(fixture.post_id || 0);
	assert(postId > 0, 'Temporary draft fixture was created through WP-CLI.');
	const initialSnapshot = databaseSnapshot(postId);
	const initialParagraphs = initialSnapshot.top_level.filter((block) => block.name === 'core/paragraph');
	assert(initialSnapshot.summary_group_count === 0 && initialSnapshot.resized_paragraph_count === 0, 'Fixture begins without generated summary or resized paragraph markers.');
	assert(initialParagraphs.length === 4, 'Fixture begins with exactly four serialized top-level paragraph blocks.');

	const { chromium } = await loadPlaywright();
	const launchOptions = { headless: process.env.HEADLESS !== '0' };
	if (process.env.BROWSER_EXECUTABLE) {
		launchOptions.executablePath = process.env.BROWSER_EXECUTABLE;
	} else if (existsSync('/Applications/Google Chrome.app/Contents/MacOS/Google Chrome')) {
		launchOptions.executablePath = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
	}
	browser = await chromium.launch(launchOptions);
	const context = await browser.newContext({ ignoreHTTPSErrors: true });
	const authentication = authCookies(baseUrl, fixture.author_id);
	authSession = authentication.session;
	assert(authSession.userId > 0 && authSession.token.length >= 8 && authentication.cookies.length === 3, 'One short-lived WordPress session backs all three browser authentication cookies.');
	await context.addCookies(authentication.cookies);
	page = await context.newPage();
	page.on('response', (response) => {
		const kind = abilityKind(response.url());
		if (kind) {
			const request = response.request();
			abilityResponses.push({
				kind,
				status: response.status(),
				method: request.method(),
				action: kind === 'content-resizing' ? abilityAction(request) : '',
			});
		}
	});
	page.on('request', (request) => {
		if (!isFixtureWrite(request.url(), request.method(), postId)) {
			return;
		}
		const evidence = { method: request.method(), path: requestRestPath(request.url()) };
		if (manualSaveStarted) {
			saveWrites.push(evidence);
		} else {
			preSaveWrites.push(evidence);
		}
	});

	await page.goto(`${baseUrl}/wp-admin/post.php?post=${postId}&action=edit`, { waitUntil: 'domcontentloaded', timeout: 45000 });
	assert(!page.url().includes('wp-login.php'), 'WP-CLI cookies open the real block editor without a login redirect.');
	await page.waitForFunction(() => Boolean(window.wp?.data?.select?.('core/editor')?.getCurrentPostId?.()), null, { timeout: 30000 });
	assert(await dismissEditorOverlays(page), 'Fresh editor startup overlays are dismissed before WordPress AI review.');
	autosaveLocked = await lockAutosaving(page);
	assert(autosaveLocked, 'Test fixture autosaving is locked until the explicit Save/Update action.');

	const editorFrame = page.frameLocator('iframe[name="editor-canvas"], iframe.wp-block-editor-iframe__iframe').first();
	await waitForVisibleLocator(
		page,
		[editorFrame.locator('.ai-title-toolbar-wrapper'), page.locator('.ai-title-toolbar-wrapper')],
		'WordPress AI title-toolbar wrapper'
	);
	const titleInput = await waitForVisibleLocator(
		page,
		[
			editorFrame.locator('.ai-title-toolbar-wrapper .editor-post-title__input'),
			page.locator('.ai-title-toolbar-wrapper .editor-post-title__input'),
		],
		'post title input'
	);
	await titleInput.focus();
	await titleInput.click();
	const titleGenerate = await waitForVisibleLocator(
		page,
		[editorFrame.locator('.ai-title-toolbar-container button'), page.locator('.ai-title-toolbar-container button')],
		'WordPress AI title-generation control'
	);
	assert(!(await titleGenerate.isDisabled()), 'Title-generation control is enabled for the fixture content.');
	await titleGenerate.click();
	const titleModal = await waitForVisibleLocator(
		page,
		[page.locator('.ai-title-generation-modal'), editorFrame.locator('.ai-title-generation-modal')],
		'Title suggestion review modal',
		90000
	);
	const titleInsertLabel = await titleModal.evaluate(() => window.wp.i18n.__('Insert', 'ai'));
	const titleTextarea = titleModal.locator('textarea').first();
	await waitForCondition(page, async () => (await titleTextarea.inputValue().catch(() => '')).trim().length > 0, 'generated title text', 15000);
	const generatedTitle = (await titleTextarea.inputValue()).trim();
	assert(generatedTitle.length > 0, 'UI review evidence: Title suggestion contains generated text before Insert.');
	assert(samePersistedSnapshot(initialSnapshot, databaseSnapshot(postId)), 'Data-path evidence: title generation returned while post fields and revisions remained unchanged before Insert.');
	const titleInsert = titleModal.getByRole('button', { name: titleInsertLabel, exact: true });
	assert(await titleInsert.isVisible(), 'UI review evidence: the localized Insert control is visible in the title review modal.');
	await titleInsert.click();
	await titleModal.waitFor({ state: 'hidden', timeout: 15000 });
	const titleAppliedState = await editorState(page);
	assert(titleAppliedState.title === generatedTitle && titleAppliedState.dirty, 'UI review evidence: Insert applies the reviewed title only to dirty editor state.');
	assert(samePersistedSnapshot(initialSnapshot, databaseSnapshot(postId)), 'Data-path evidence: Insert caused no WordPress write before normal save.');

	await openDocumentSidebar(page);
	let summaryButton;
	try {
		summaryButton = await waitForVisibleLocator(page, [page.locator('.ai-summarization-plugin-button')], 'Generate Summary button', 5000);
	} catch (error) {
		const settingsToggle = await waitForVisibleLocator(
			page,
			[page.locator('button[aria-label*="Settings"], button[aria-label*="设置"]')],
			'editor Settings toggle'
		);
		await settingsToggle.click();
		summaryButton = await waitForVisibleLocator(page, [page.locator('.ai-summarization-plugin-button')], 'Generate Summary button');
	}
	assert(!(await summaryButton.isDisabled()), 'Generate Summary is enabled for the fixture content.');
	await summaryButton.click();
	await page.waitForFunction(() => {
		const blocks = window.wp?.data?.select?.('core/block-editor')?.getBlocks?.() || [];
		return blocks.filter((block) => block.name === 'core/group' && block.attributes?.aiGeneratedSummary === true).length === 1;
	}, null, { timeout: 90000 });
	const summaryBlock = await waitForVisibleLocator(
		page,
		[editorFrame.locator('.ai-summarization-summary'), page.locator('.ai-summarization-summary')],
		'generated summary block'
	);
	assert((await summaryBlock.innerText()).trim().length > 0, 'UI review evidence: one generated summary block is visible in the editor.');
	const summaryAppliedState = await editorState(page);
	assert(summaryAppliedState.summaryCount === 1 && summaryAppliedState.summaryText.length > 0, 'UI review evidence: editor data contains one non-empty summary.');
	assert(samePersistedSnapshot(initialSnapshot, databaseSnapshot(postId)), 'Data-path evidence: summary generation changed editor state but not the stored post or revisions.');

	const targetClientId = await selectTargetParagraph(page, `P5B3-TARGET-${token}`);
	const targetBlock = await waitForVisibleLocator(
		page,
		[editorFrame.locator(`[data-block="${targetClientId}"]`), page.locator(`[data-block="${targetClientId}"]`)],
		'selected target paragraph block'
	);
	await targetBlock.click();
	const resizeButton = await waitForVisibleLocator(
		page,
		[
			page.locator('button:has(.ai-content-resizing-toolbar__icon)'),
			editorFrame.locator('button:has(.ai-content-resizing-toolbar__icon)'),
		],
		'Resize Content toolbar control'
	);
	await resizeButton.click();
	const resizingMenuItems = await visibleMenuItems(page, editorFrame);
	let rephraseControl = null;
	for (const item of resizingMenuItems) {
		const translatedRephrase = await item.evaluate(() => window.wp.i18n.__('Rephrase', 'ai'));
		const itemText = (await item.innerText().catch(() => '')).trim();
		const accessibleLabel = (await item.getAttribute('aria-label').catch(() => '') || '').trim();
		if (itemText === translatedRephrase || accessibleLabel === translatedRephrase) {
			rephraseControl = item;
			break;
		}
	}
	assert(rephraseControl !== null, 'UI review evidence: the current resizing dropdown exposes the localized Rephrase control.');
	await rephraseControl.click();
	const resizeModal = await waitForVisibleLocator(
		page,
		[page.locator('.ai-content-resizing-modal'), editorFrame.locator('.ai-content-resizing-modal')],
		'Original/Suggested replacement modal'
	);
	const resizePanels = resizeModal.locator('.ai-content-resizing-modal__panel');
	assert(await resizePanels.count() === 2, 'UI review evidence: the official replacement modal exposes exactly two review panels.');
	const originalPanelContainer = resizePanels.first();
	const suggestedPanelContainer = resizePanels.nth(1);
	const originalPanel = originalPanelContainer.locator('.ai-content-resizing-modal__text--original');
	const suggestedPanel = suggestedPanelContainer.locator('.ai-content-resizing-modal__text:not(.ai-content-resizing-modal__loading)');
	await waitForCondition(page, async () => (await suggestedPanel.innerText().catch(() => '')).trim().length > 0, 'rephrased Suggested content', 90000);
	const originalLabel = (await originalPanelContainer.locator('.ai-content-resizing-modal__label span').first().textContent() || '').trim();
	const suggestedLabel = (await suggestedPanelContainer.locator('.ai-content-resizing-modal__label span').first().textContent() || '').trim();
	const originalAriaLabel = (await originalPanelContainer.getAttribute('aria-label') || '').trim();
	const suggestedAriaLabel = (await suggestedPanelContainer.getAttribute('aria-label') || '').trim();
	const reviewLabels = await resizeModal.evaluate(() => ({
		original: window.wp.i18n.__('Original', 'ai'),
		suggested: window.wp.i18n.__('Suggested', 'ai'),
		originalContent: window.wp.i18n.__('Original content', 'ai'),
		suggestedContent: window.wp.i18n.__('Suggested content', 'ai'),
		accept: window.wp.i18n.__('Accept', 'ai'),
	}));
	const originalReviewText = (await originalPanel.innerText()).replace(/\s+/g, ' ').trim();
	const suggestedReviewText = (await suggestedPanel.innerText()).replace(/\s+/g, ' ').trim();
	assert(
		originalLabel === reviewLabels.original
		&& suggestedLabel === reviewLabels.suggested
		&& originalAriaLabel === reviewLabels.originalContent
		&& suggestedAriaLabel === reviewLabels.suggestedContent,
		'UI review evidence: panels expose the exact localized Original and Suggested semantics.'
	);
	assert(originalReviewText.includes(`P5B3-TARGET-${token}`), 'UI review evidence: Original panel is the selected whole core/paragraph block.');
	assert(suggestedReviewText.length > 0 && suggestedReviewText !== originalReviewText, 'UI review evidence: Suggested panel shows a distinct rephrased paragraph before Accept.');
	assert(
		!/(?:\sOR\s|Both rephrasings|Both versions|如果你愿意|以下是|下面是)/i.test(suggestedReviewText),
		'Quality evidence: Suggested contains one direct rewrite without alternatives or explanatory boilerplate.'
	);
	assert(samePersistedSnapshot(initialSnapshot, databaseSnapshot(postId)), 'Data-path evidence: Rephrase returned while post fields and revisions remained unchanged before Accept.');
	ensureParent(reviewScreenshotPath);
	await page.screenshot({ path: reviewScreenshotPath, fullPage: true });
	pass(`UI review screenshot captured at ${reviewScreenshotPath}.`);
	const acceptButton = resizeModal.getByRole('button', { name: reviewLabels.accept, exact: true });
	assert(await acceptButton.isVisible() && !(await acceptButton.isDisabled()), 'UI review evidence: the exact localized Accept action is visible and enabled.');
	await acceptButton.click();
	await resizeModal.waitFor({ state: 'hidden', timeout: 15000 });
	const acceptedState = await editorState(page, targetClientId);
	assert(acceptedState.targetAiResized && acceptedState.targetText === suggestedReviewText, 'UI review evidence: Accept changes only the selected paragraph block in editor state.');
	assert(samePersistedSnapshot(initialSnapshot, databaseSnapshot(postId)), 'Data-path evidence: accepted title, summary, and paragraph remain unsaved until the explicit local save.');
	assert(preSaveWrites.length === 0, 'API evidence: no fixture post/autosave REST write occurred before explicit Save/Update.');

	await waitForCondition(
		page,
		async () => ['title-generation', 'summarization', 'content-resizing'].every((kind) => abilityResponses.some((entry) => entry.kind === kind && entry.status >= 200 && entry.status < 300)),
		'three successful WordPress Abilities responses',
		15000
	);
	assert(
		abilityResponses.some((entry) => entry.kind === 'content-resizing' && entry.action === 'rephrase'),
		'API evidence: the content-resizing request used input.action=rephrase without recording source text.'
	);
	const saveButton = await waitForVisibleLocator(
		page,
		[
			page.locator('.editor-post-save-draft'),
			page.getByRole('button', { name: /^(Save draft|Save|Update|保存草稿|保存|更新)$/i }),
		],
		'normal Save/Update button'
	);
	assert(preSaveWrites.length === 0, 'API evidence: autosaving remains locked immediately before the explicit Save/Update click.');
	manualSaveStarted = true;
	await saveButton.click();
	await page.waitForFunction(() => {
		const editor = window.wp?.data?.select?.('core/editor');
		const saveFailed = typeof editor?.didPostSaveRequestFail === 'function' ? editor.didPostSaveRequestFail() : false;
		return editor && !editor.isSavingPost() && !editor.isEditedPostDirty() && !saveFailed;
	}, null, { timeout: 45000 });
	assert(saveWrites.length >= 1, 'API evidence: normal Save/Update issued a WordPress post REST write.');
	assert(preSaveWrites.length === 0, 'API evidence: no autosave or post write raced ahead of the explicit Save/Update request.');
	assert(await unlockAutosaving(page), 'Fixture autosave lock is released only after the explicit Save/Update completes.');
	autosaveLocked = false;

	const finalSnapshot = databaseSnapshot(postId);
	const finalParagraphs = finalSnapshot.top_level.filter((block) => block.name === 'core/paragraph');
	assert(finalSnapshot.status === 'draft', 'Persistence evidence: the normal local save preserves draft status.');
	assert(finalSnapshot.title === generatedTitle, 'Persistence evidence: saved title equals the reviewed Title suggestion.');
	assert(finalSnapshot.summary_group_count === 1 && finalSnapshot.summary_text.length > 0, 'Persistence evidence: saved content contains one unique non-empty summary block.');
	assert(
		normalizeEvidenceText(finalSnapshot.summary_meta) === normalizeEvidenceText(summaryAppliedState.summaryText)
		&& normalizeEvidenceText(finalSnapshot.summary_text) === normalizeEvidenceText(summaryAppliedState.summaryText),
		'Persistence evidence: saved summary meta and block content equal the reviewed summary.'
	);
	assert(finalSnapshot.resized_paragraph_count === 1, 'Persistence evidence: exactly one saved paragraph carries the resize marker.');
	assert(finalParagraphs.length === 4, 'Persistence evidence: the four original top-level paragraph slots remain intact.');
	assert(
		finalParagraphs[0].text === fixtureText.sentinelBefore
		&& finalParagraphs[2].text === fixtureText.sentinelAfter
		&& finalParagraphs[3].text === fixtureText.filler
		&& finalParagraphs[0].serialized_hash === initialParagraphs[0].serialized_hash
		&& finalParagraphs[2].serialized_hash === initialParagraphs[2].serialized_hash
		&& finalParagraphs[3].serialized_hash === initialParagraphs[3].serialized_hash,
		'Persistence evidence: every non-target sentinel block remains byte-for-text unchanged.'
	);
	assert(
		finalParagraphs[1].text === acceptedState.targetText
		&& finalParagraphs[1].text !== fixtureText.targetOriginal
		&& finalParagraphs[1].serialized_hash !== initialParagraphs[1].serialized_hash,
		'Persistence evidence: only the target core/paragraph contains the accepted rewrite.'
	);
	assert(finalSnapshot.revision_ids.length > initialSnapshot.revision_ids.length, 'Persistence evidence: the explicit save creates revision evidence after the no-write review phase.');
	ensureParent(savedScreenshotPath);
	await page.screenshot({ path: savedScreenshotPath, fullPage: true });
	pass(`Saved editor screenshot captured at ${savedScreenshotPath}.`);

	machineSummary = {
		contract: 'p5_b3_wordpress_ai_text_browser.v1',
		site_origin: baseUrl,
		environment: readiness.environment,
		versions: {
			wordpress: readiness.wordpress_version,
			wordpress_ai: readiness.ai_version,
			cloud_addon: readiness.addon_version,
		},
		ui_review_evidence: {
			title_suggestion_inserted: true,
			title_suggestion_sha256: sha256(generatedTitle),
			summary_visible: true,
			summary_sha256: sha256(summaryAppliedState.summaryText),
			selected_block_rephrase_reviewed: true,
			selected_block_rephrase_sha256: sha256(acceptedState.targetText),
			review_screenshot: reviewScreenshotPath,
			saved_screenshot: savedScreenshotPath,
		},
		api_data_path_evidence: {
			ability_responses: abilityResponses,
			pre_save_post_writes: preSaveWrites.length,
			explicit_save_writes: saveWrites.length,
			initial_content_sha256: initialSnapshot.content_hash,
			final_content_sha256: finalSnapshot.content_hash,
			revision_delta: finalSnapshot.revision_ids.length - initialSnapshot.revision_ids.length,
		},
		persistence_evidence: {
			title_saved: true,
			summary_group_count: finalSnapshot.summary_group_count,
			resized_paragraph_count: finalSnapshot.resized_paragraph_count,
			non_target_sentinels_unchanged: true,
		},
		fixture: { post_id: postId, deleted: false },
	};
} catch (error) {
	failure = error;
	if (page) {
		await captureDiagnostics(page, failureScreenshotPath, abilityResponses, preSaveWrites, error);
	}
} finally {
	if (page && autosaveLocked) {
		await unlockAutosaving(page).catch(() => false);
	}
	if (browser) {
		await browser.close().catch(() => {});
	}
	if (authSession) {
		try {
			const sessionCleanup = destroyAuthSession(authSession);
			authSessionDestroyed = sessionCleanup.destroyed === true;
			if (!authSessionDestroyed) {
				throw new Error('Temporary WordPress authentication session still exists after cleanup.');
			}
			pass('Temporary WordPress authentication session was destroyed and verified absent.');
		} catch (cleanupError) {
			failure = failure || cleanupError;
			console.error(`FAIL: authentication session cleanup: ${cleanupError.message || cleanupError}`);
		}
	}
	if (postId > 0) {
		try {
			const cleanup = deleteFixture(postId);
			cleanupDeleted = cleanup.deleted === true;
			if (!cleanupDeleted) {
				throw new Error(`Temporary draft ${postId} still exists after cleanup.`);
			}
			pass(`Temporary draft ${postId} was force-deleted and verified absent.`);
		} catch (cleanupError) {
			failure = failure || cleanupError;
			console.error(`FAIL: fixture cleanup: ${cleanupError.message || cleanupError}`);
		}
	}
}

if (failure) {
	console.error(`FAIL: WordPress AI text browser smoke: ${failure.message || failure}`);
	process.exitCode = 1;
} else {
	machineSummary.fixture.deleted = cleanupDeleted;
	machineSummary.fixture.auth_session_destroyed = authSessionDestroyed;
	const encodedSummary = JSON.stringify(machineSummary);
	if (summaryPath) {
		const resolvedSummaryPath = resolve(summaryPath);
		ensureParent(resolvedSummaryPath);
		writeFileSync(resolvedSummaryPath, `${JSON.stringify(machineSummary, null, 2)}\n`, 'utf8');
		pass(`Machine-readable summary written to ${resolvedSummaryPath}.`);
	}
	console.log(`P5_B3_WORDPRESS_AI_TEXT_SUMMARY=${encodedSummary}`);
	pass(`WordPress AI text browser smoke completed at ${baseUrl}.`);
}
