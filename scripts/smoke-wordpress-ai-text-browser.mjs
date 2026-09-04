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
import { existsSync, mkdirSync, unlinkSync, writeFileSync } from 'node:fs';
import { createRequire } from 'node:module';
import { dirname, isAbsolute, resolve } from 'node:path';
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

function parseCommandJson(output, label) {
	const text = String(output || '').trim();
	const start = text.indexOf('{');
	const end = text.lastIndexOf('}');
	if (start < 0 || end < start) {
		throw new Error(`${label} did not return a JSON object.`);
	}
	try {
		return JSON.parse(text.slice(start, end + 1));
	} catch (error) {
		throw new Error(`${label} returned invalid JSON: ${error.message}`);
	}
}

const PROVIDER_LEDGER_TASK_KEYS = [
	'title_generation',
	'content_summary',
	'content_rewrite',
];
const PROVIDER_LEDGER_IDENTIFIER = /^[a-z0-9][a-z0-9._-]{2,63}$/;

function exactObjectKeys(value, expectedKeys, label) {
	assert(value && typeof value === 'object' && !Array.isArray(value), `${label} is a JSON object.`);
	const actual = Object.keys(value).sort();
	const expected = [...expectedKeys].sort();
	assert(JSON.stringify(actual) === JSON.stringify(expected), `${label} contains only the reviewed fields.`);
}

function providerLedgerCommand(plan, args, label) {
	let output = '';
	try {
		output = execFileSync(
			'pnpm',
			['run', 'provider:call-ledger', ...args],
			{
				cwd: plan.ledger_repo,
				encoding: 'utf8',
				stdio: ['ignore', 'pipe', 'pipe'],
			}
		);
	} catch (error) {
		const stderr = String(error?.stderr || '').replace(/\s+/g, ' ').trim();
		throw new Error(`${label} failed closed${stderr ? `: ${stderr.slice(0, 500)}` : '.'}`);
	}
	return parseCommandJson(output, label);
}

function loadProviderLedgerPlan(rawPlan) {
	assert(rawPlan.trim() !== '', 'Real Provider quality validation requires WP_AI_TEXT_PROVIDER_LEDGER_PLAN before fixture creation.');
	let plan;
	try {
		plan = JSON.parse(rawPlan);
	} catch (error) {
		throw new Error(`WP_AI_TEXT_PROVIDER_LEDGER_PLAN is invalid JSON: ${error.message}`);
	}
	exactObjectKeys(plan, ['experiment_id', 'ledger_repo', 'dispatches'], 'Provider ledger plan');
	assert(PROVIDER_LEDGER_IDENTIFIER.test(plan.experiment_id), 'Provider ledger experiment_id uses the bounded identifier format.');
	assert(typeof plan.ledger_repo === 'string' && isAbsolute(plan.ledger_repo), 'Provider ledger_repo is an absolute path.');
	const ledgerRepo = resolve(plan.ledger_repo);
	assert(existsSync(`${ledgerRepo}/package.json`), 'Provider ledger repository contains package.json.');
	assert(existsSync(`${ledgerRepo}/scripts/provider_call_ledger.py`), 'Provider ledger repository contains the reviewed ledger command.');
	exactObjectKeys(plan.dispatches, PROVIDER_LEDGER_TASK_KEYS, 'Provider ledger dispatch map');
	const dispatchIds = new Set();
	const itemIds = new Set();
	const dispatches = {};
	for (const taskKey of PROVIDER_LEDGER_TASK_KEYS) {
		const dispatch = plan.dispatches[taskKey];
		exactObjectKeys(dispatch, ['item_id', 'dispatch_id'], `Provider ledger ${taskKey} dispatch`);
		assert(PROVIDER_LEDGER_IDENTIFIER.test(dispatch.item_id), `Provider ledger ${taskKey} item_id uses the bounded identifier format.`);
		assert(PROVIDER_LEDGER_IDENTIFIER.test(dispatch.dispatch_id), `Provider ledger ${taskKey} dispatch_id uses the bounded identifier format.`);
		assert(!itemIds.has(dispatch.item_id), `Provider ledger ${taskKey} item_id is unique within the run.`);
		assert(!dispatchIds.has(dispatch.dispatch_id), `Provider ledger ${taskKey} dispatch_id is unique within the run.`);
		itemIds.add(dispatch.item_id);
		dispatchIds.add(dispatch.dispatch_id);
		dispatches[taskKey] = {
			item_id: dispatch.item_id,
			dispatch_id: dispatch.dispatch_id,
		};
	}
	return {
		experiment_id: plan.experiment_id,
		ledger_repo: ledgerRepo,
		dispatches,
	};
}

function preflightProviderLedgerPlan(plan) {
	const status = providerLedgerCommand(
		plan,
		['status', '--experiment-id', plan.experiment_id],
		'Provider ledger status preflight'
	);
	assert(status.contract_version === 'npcink.provider_call_ledger.v1', 'Provider ledger status uses the reviewed contract.');
	assert(status.experiment_id === plan.experiment_id && status.status === 'open', 'Provider ledger experiment is open and matches the plan.');
	assert(status.items && typeof status.items === 'object', 'Provider ledger status exposes reserved items.');
	assert(Array.isArray(status.claims), 'Provider ledger status exposes the bounded claim list.');
	assert(Number.isInteger(status.claimed_calls) && Number.isInteger(status.remaining_calls), 'Provider ledger status exposes integer aggregate counters.');
	for (const taskKey of PROVIDER_LEDGER_TASK_KEYS) {
		const dispatch = plan.dispatches[taskKey];
		assert(status.items[dispatch.item_id], `Provider ledger reserves the ${taskKey} item.`);
		const existingClaim = status.claims.find((claim) => claim.dispatch_id === dispatch.dispatch_id);
		assert(!existingClaim || existingClaim.item_id === dispatch.item_id, `Provider ledger ${taskKey} dispatch is unused or an idempotent same-item replay.`);
		assert(existingClaim || status.items[dispatch.item_id].remaining_calls > 0, `Provider ledger ${taskKey} item has capacity before fixture creation.`);
	}
	return {
		contract_version: status.contract_version,
		experiment_id: status.experiment_id,
		claimed_calls: status.claimed_calls,
		remaining_calls: status.remaining_calls,
	};
}

function claimProviderLedgerDispatch(plan, taskKey) {
	const dispatch = plan.dispatches[taskKey];
	const receipt = providerLedgerCommand(
		plan,
		[
			'claim',
			'--experiment-id', plan.experiment_id,
			'--item-id', dispatch.item_id,
			'--dispatch-id', dispatch.dispatch_id,
		],
		`Provider ledger ${taskKey} claim`
	);
	assert(receipt.contract_version === 'npcink.provider_call_ledger.v1', `Provider ledger ${taskKey} claim uses the reviewed contract.`);
	assert(receipt.status === 'claimed', `Provider ledger ${taskKey} claim has claimed status.`);
	assert(receipt.experiment_id === plan.experiment_id, `Provider ledger ${taskKey} claim matches the experiment.`);
	assert(receipt.item_id === dispatch.item_id && receipt.dispatch_id === dispatch.dispatch_id, `Provider ledger ${taskKey} claim matches the planned dispatch.`);
	assert(typeof receipt.idempotent_replay === 'boolean', `Provider ledger ${taskKey} claim exposes an explicit replay flag.`);
	assert(
		Number.isInteger(receipt.experiment_claimed_calls)
		&& Number.isInteger(receipt.experiment_remaining_calls),
		`Provider ledger ${taskKey} claim exposes integer aggregate counters.`
	);
	assert(receipt.provider_dispatch_allowed === true, `Provider ledger authorizes the ${taskKey} Provider dispatch.`);
	return {
		task_key: taskKey,
		item_id: receipt.item_id,
		dispatch_id: receipt.dispatch_id,
		provider_dispatch_allowed: true,
		idempotent_replay: receipt.idempotent_replay === true,
		experiment_claimed_calls: receipt.experiment_claimed_calls,
		experiment_remaining_calls: receipt.experiment_remaining_calls,
	};
}

function readAiCreditSummary(label) {
	let lastError = null;
	for (let attempt = 1; attempt <= 5; attempt += 1) {
		try {
			const output = wpCli([
				'eval',
				`$response = function_exists( 'npcink_cloud_addon_get_toolbox_runtime_entitlement' )
	? npcink_cloud_addon_get_toolbox_runtime_entitlement( 'trace_credit_assertion_' . wp_generate_uuid4() )
	: new WP_Error( 'runtime_facade_unavailable' );
$summary = is_array( $response )
	? ( $response['data']['quota_summary']['ai_credit_usage_detail']['summary'] ?? array() )
	: array();
echo wp_json_encode(
	array(
		'used'      => (float) ( $summary['used'] ?? 0 ),
		'limit'     => (float) ( $summary['limit'] ?? 0 ),
		'remaining' => (float) ( $summary['remaining'] ?? 0 ),
		'unit'      => (string) ( $summary['unit'] ?? '' ),
	)
);`,
			]);
			const summary = parseJson(output, label);
			if (
				summary?.unit !== 'ai_credits'
				|| typeof summary?.used !== 'number'
				|| !Number.isFinite(summary.used)
				|| typeof summary?.limit !== 'number'
				|| !Number.isFinite(summary.limit)
				|| typeof summary?.remaining !== 'number'
				|| !Number.isFinite(summary.remaining)
			) {
				throw new Error(`${label} did not return the exact AI-credit summary contract.`);
			}
			return {
				used: Number(summary.used),
				limit: Number(summary.limit),
				remaining: Number(summary.remaining),
				unit: 'ai_credits',
			};
		} catch (error) {
			lastError = error;
			if (attempt < 5) {
				execFileSync('/bin/sleep', ['1']);
			}
		}
	}
	throw new Error(`${label} remained unavailable after bounded read-only retries: ${lastError?.message || lastError}`);
}

function phpString(value) {
	return JSON.stringify(String(value));
}

function fakeProviderOptionName(token) {
	return `npcink_cloud_addon_browser_fake_${token}`;
}

function fakeProviderPluginSource(token, optionName, expiresAt) {
	const quotedToken = phpString(token);
	const quotedOptionName = phpString(optionName);

	return `<?php
/**
 * Disposable fake-provider transport for one local browser-smoke fixture.
 *
 * This file is generated and removed by smoke-wordpress-ai-text-browser.mjs.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter(
	'pre_http_request',
	static function ( $preempt, array $parsed_args, string $url ) {
		$environment = wp_get_environment_type();
		$host        = strtolower( (string) wp_parse_url( home_url( '/' ), PHP_URL_HOST ) );
		$is_local    = in_array( $environment, array( 'local', 'development' ), true )
			&& (
				in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true )
				|| ( strlen( $host ) > 6 && '.local' === substr( $host, -6 ) )
			);
		if ( ! $is_local ) {
			return $preempt;
		}

		$option_name = ${quotedOptionName};
		$state       = get_option( $option_name, array() );
		if ( ! is_array( $state ) || ${quotedToken} !== (string) ( $state['token'] ?? '' ) ) {
			return $preempt;
		}
		if ( time() > ${Number(expiresAt)} ) {
			delete_option( $option_name );
			return $preempt;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		if ( '/v1/runtime/execute' !== $path ) {
			return $preempt;
		}

		$payload   = json_decode( (string) ( $parsed_args['body'] ?? '' ), true );
		$input     = is_array( $payload['input'] ?? null ) ? $payload['input'] : array();
		$operation = is_array( $input['operation_contract'] ?? null ) ? $input['operation_contract'] : array();
		$request   = is_array( $operation['request'] ?? null ) ? $operation['request'] : array();
		$task      = sanitize_key( (string) ( $operation['task'] ?? '' ) );
		if (
			'npcink-cloud/connector-runtime' !== (string) ( $payload['ability_name'] ?? '' )
			|| 'wordpress' !== (string) ( $input['platform_kind'] ?? '' )
			|| 'npcink-cloud-addon' !== (string) ( $input['connector_id'] ?? '' )
		) {
			return $preempt;
		}

		$source_text = is_string( $request['source_text'] ?? null ) ? (string) $request['source_text'] : '';
		if ( false === strpos( $source_text, ${quotedToken} ) ) {
			return new WP_Error(
				'npcink_browser_fake_fixture_mismatch',
				'The disposable fake provider rejects non-fixture WordPress AI requests.'
			);
		}

		$events = is_array( $state['events'] ?? null ) ? $state['events'] : array();
		$state['title_calls'] = absint( $state['title_calls'] ?? 0 );
		if ( 'title_generation' === $task ) {
			++$state['title_calls'];
		}
		$outcome = 'succeeded';
		if ( 'title_generation' === $task && 1 === $state['title_calls'] ) {
			$outcome = 'provider_unavailable';
		}
		$reference = is_array( $request['site_knowledge_reference'] ?? null )
			? $request['site_knowledge_reference']
			: array();
		$events[] = array(
			'sequence'                 => count( $events ) + 1,
			'task'                     => $task,
			'outcome'                  => $outcome,
			'data_classification'      => (string) ( $payload['data_classification'] ?? '' ),
			'storage_mode'             => (string) ( $payload['storage_mode'] ?? '' ),
			'suggestion_only'          => true === ( $input['suggestion_only'] ?? null ),
			'site_reference_requested' => true === ( $reference['enabled'] ?? null ),
			'transport_preempted'      => true,
		);
		$state['events'] = array_slice( $events, -12 );
		update_option( $option_name, $state, false );

		if ( 'provider_unavailable' === $outcome ) {
			return array(
				'headers'  => array( 'content-type' => 'application/json' ),
				'body'     => wp_json_encode(
					array(
						'status' => 'error',
						'error'  => array(
							'code'    => 'provider_unavailable',
							'message' => 'Synthetic provider unavailable for the first bounded attempt.',
						),
					)
				),
				'response' => array( 'code' => 503, 'message' => 'Service Unavailable' ),
				'cookies'  => array(),
				'filename' => null,
			);
		}

		$outputs = array(
			'title_generation' => 2 === $state['title_calls']
				? '受控 Fake Provider 标题建议 A ${token}'
				: '受控 Fake Provider 标题建议 B ${token}',
			'content_summary'  => '这是一个经过受控 Fake Provider 验证的编辑工作流摘要。',
			'content_rewrite'  => 'P5B3-TARGET-${token} 已被清晰改写，同时保留原始的实践含义。',
		);
		if ( ! isset( $outputs[ $task ] ) ) {
			return new WP_Error(
				'npcink_browser_fake_task_not_supported',
				'The disposable fake provider accepts only the bounded text-smoke tasks.'
			);
		}

		$sequence = count( $events );
		return array(
			'headers'  => array( 'content-type' => 'application/json' ),
			'body'     => wp_json_encode(
				array(
					'status' => 'ok',
					'data'   => array(
						'run_id' => 'run_browser_fake_${token}_' . $sequence,
						'status' => 'succeeded',
						'result' => array(
							'contract_version'   => 'cloud_connector_result.v1',
							'suggestion_only'    => true,
							'connector_id'       => 'npcink-cloud-addon',
							'operation_contract' => array(
								'contract_version' => 'wordpress_operation.v1',
								'task'             => $task,
							),
							'output' => array( 'output_text' => $outputs[ $task ] ),
						),
					),
				)
			),
			'response' => array( 'code' => 200, 'message' => 'OK' ),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	1,
	3
);
`;
}

function installFakeProvider(token) {
	const optionName = fakeProviderOptionName(token);
	const expiresAt = Math.floor(Date.now() / 1000) + 600;
	const muPluginDir = resolve(wpPath(), 'wp-content/mu-plugins');
	const pluginPath = resolve(muPluginDir, `npcink-cloud-addon-browser-fake-${token}.php`);
	assert(pluginPath.startsWith(`${muPluginDir}/`), 'Disposable fake-provider plugin stays inside the Local mu-plugins directory.');
	assert(!existsSync(pluginPath), 'Disposable fake-provider plugin path is unused before setup.');
	mkdirSync(muPluginDir, { recursive: true });
	writeFileSync(pluginPath, fakeProviderPluginSource(token, optionName, expiresAt), { encoding: 'utf8', mode: 0o600, flag: 'wx' });
	wpCli([
		'eval',
		`update_option(${phpString(optionName)}, array('token'=>${phpString(token)}, 'title_calls'=>0, 'events'=>array()), false); echo wp_json_encode(array('active'=>${phpString(token)} === (string) (get_option(${phpString(optionName)}, array())['token'] ?? '')));`,
	]);
	assert(existsSync(pluginPath), 'Disposable fake-provider plugin is installed for this fixture only.');

	return { optionName, pluginPath, expiresAt };
}

function readFakeProviderEvidence(fakeProvider) {
	return parseJson(
		wpCli([
			'eval',
			`echo wp_json_encode(get_option(${phpString(fakeProvider.optionName)}, array()));`,
		]),
		'Fake-provider scalar evidence'
	);
}

function readQualityCorrelationEvidence(postId) {
	return parseJson(
		wpCli([
			'eval',
			`
$post_id = ${Number(postId)};
$expected_scopes = array();
foreach (array('title_generation', 'content_summary', 'content_rewrite') as $task_key) {
	$expected_scopes[$task_key] = hash_hmac('sha256', $post_id . '|' . $task_key, wp_salt('auth'));
}
$events = (array) get_option(Npcink_Cloud_Observability_Collector::BUFFER_OPTION, array());
$pending = (array) get_option(Npcink_Cloud_Editor_Assist_Quality::PENDING_OPTION, array());
$kind_counts = array();
$task_counts = array();
$outcome_counts = array();
$outcome_by_task = array();
$session_ids = array();
$forbidden_fields = array();
$invalid_content_storage = 0;
$event_total = 0;
$pending_count = 0;
foreach ($events as $event) {
	if (
		!is_array($event)
		|| 'editor_assist_quality.v1' !== (string) ($event['quality_contract'] ?? '')
	) {
		continue;
	}
	$task = (string) ($event['task_key'] ?? '');
	if (($expected_scopes[$task] ?? '') !== (string) ($event['object_scope_hash'] ?? '')) {
		continue;
	}
	++$event_total;
	$kind = (string) ($event['event_kind'] ?? '');
	$outcome = (string) ($event['outcome'] ?? '');
	$kind_counts[$kind] = ($kind_counts[$kind] ?? 0) + 1;
	$task_counts[$task] = ($task_counts[$task] ?? 0) + 1;
	if ('' !== $outcome) {
		$outcome_counts[$outcome] = ($outcome_counts[$outcome] ?? 0) + 1;
		$outcome_by_task[$task][$outcome] = ($outcome_by_task[$task][$outcome] ?? 0) + 1;
	}
	$session_ids[(string) ($event['quality_session_id'] ?? '')] = true;
	if ('omitted_metadata_only' !== (string) ($event['content_storage'] ?? '')) {
		++$invalid_content_storage;
	}
	foreach (array('prompt', 'content', 'output_text', 'post_id', 'actor_id', 'user_id', 'secret', 'authorization') as $field) {
		if (array_key_exists($field, $event)) {
			$forbidden_fields[$field] = true;
		}
	}
}
foreach ($pending as $record) {
	if (is_array($record) && $post_id === absint($record['post_id'] ?? 0)) {
		++$pending_count;
	}
}
ksort($kind_counts);
ksort($task_counts);
ksort($outcome_counts);
ksort($outcome_by_task);
foreach ($outcome_by_task as &$task_outcomes) {
	ksort($task_outcomes);
}
unset($task_outcomes);
ksort($forbidden_fields);
echo wp_json_encode(array(
	'event_total' => $event_total,
	'session_total' => count($session_ids),
	'pending_count' => $pending_count,
	'kind_counts' => $kind_counts,
	'task_counts' => $task_counts,
	'outcome_counts' => $outcome_counts,
	'outcome_by_task' => $outcome_by_task,
	'invalid_content_storage' => $invalid_content_storage,
	'forbidden_fields' => array_keys($forbidden_fields),
));
`,
		]),
		'Editor-assist quality correlation evidence'
	);
}

function removeFakeProvider(fakeProvider) {
	let optionDeleted = false;
	try {
		const result = parseJson(
			wpCli([
				'eval',
				`delete_option(${phpString(fakeProvider.optionName)}); echo wp_json_encode(array('deleted'=>false === get_option(${phpString(fakeProvider.optionName)}, false)));`,
			]),
			'Fake-provider option cleanup'
		);
		optionDeleted = result.deleted === true;
	} finally {
		if (existsSync(fakeProvider.pluginPath)) {
			unlinkSync(fakeProvider.pluginPath);
		}
	}

	return { optionDeleted, pluginDeleted: !existsSync(fakeProvider.pluginPath) };
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
	'monitoring_enabled' => class_exists('Npcink_Cloud_Addon_Settings') && Npcink_Cloud_Addon_Settings::is_monitoring_enabled(),
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

With no option, the script runs the complete opt-in browser acceptance.
Set WP_AI_TEXT_EXPECT_CREDIT_DELTA=8 to add the deterministic signed-entitlement
before/after assertion for the three real Provider calls.
Set WP_AI_TEXT_VALIDATE_PROVIDER_QUALITY=1 for a real-Provider technical checkpoint
that fails before dispatch unless metadata-only monitoring is enabled and then
verifies the three generated suggestions and their three local-save outcomes.
This mode also requires WP_AI_TEXT_PROVIDER_LEDGER_PLAN with one bounded ledger
experiment and unique title_generation, content_summary, and content_rewrite dispatches.
This automated checkpoint does not prove real-editor acceptance.`;
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
		const providerLedgerValidation = env('WP_AI_TEXT_VALIDATE_PROVIDER_QUALITY') === '1';
		let providerLedgerEvidence = { validated: false };
		if (providerLedgerValidation) {
			assert(
				readiness.monitoring_enabled === true,
				'Real quality-correlation validation requires explicit metadata-only monitoring before any Provider dispatch.'
			);
			const plan = loadProviderLedgerPlan(env('WP_AI_TEXT_PROVIDER_LEDGER_PLAN'));
			const ledgerStatus = preflightProviderLedgerPlan(plan);
			providerLedgerEvidence = {
				validated: true,
				contract_version: ledgerStatus.contract_version,
				experiment_id: ledgerStatus.experiment_id,
				claimed_calls: ledgerStatus.claimed_calls,
				remaining_calls: ledgerStatus.remaining_calls,
				content_fields_recorded: false,
			};
		}
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
			provider_call_ledger_evidence: providerLedgerEvidence,
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
const fakeProviderMode = env('WP_AI_TEXT_FAKE_PROVIDER') === '1';
const qualityValidationMode = env('WP_AI_TEXT_VALIDATE_QUALITY') === '1';
const providerQualityValidationMode = env('WP_AI_TEXT_VALIDATE_PROVIDER_QUALITY') === '1';
const providerLedgerPlanRaw = env('WP_AI_TEXT_PROVIDER_LEDGER_PLAN');
const qualityEvidenceMode = qualityValidationMode || providerQualityValidationMode;
const expectedCreditDeltaRaw = env('WP_AI_TEXT_EXPECT_CREDIT_DELTA');
const expectedCreditDelta = expectedCreditDeltaRaw === '' ? 0 : Number(expectedCreditDeltaRaw);
const creditAssertionMode = expectedCreditDeltaRaw !== '';
if (
	creditAssertionMode
	&& expectedCreditDelta !== 8
) {
	console.error('FAIL: WP_AI_TEXT_EXPECT_CREDIT_DELTA supports only the reviewed value 8.');
	process.exit(2);
}
if (qualityValidationMode && providerQualityValidationMode) {
	console.error('FAIL: Select only one quality-correlation validation mode.');
	process.exit(2);
}
const providerLedgerPlan = providerQualityValidationMode
	? loadProviderLedgerPlan(providerLedgerPlanRaw)
	: null;

const token = randomBytes(6).toString('hex');
const creditMeteringPadding = creditAssertionMode
	? ` ${'The bounded metering fixture keeps enough editorial context to stabilize the title and summary input token bucket without changing the selected rewrite paragraph. '.repeat(24).trim()}`
	: '';
const fixtureText = {
	sentinelBefore: `P5B3-BEFORE-${token} remains exactly unchanged. This paragraph is a non-target sentinel for the browser proof.`,
	targetOriginal: `P5B3-TARGET-${token} is the selected whole paragraph block. Rephrase this sentence clearly while preserving its practical meaning.`,
	sentinelAfter: `P5B3-AFTER-${token} remains exactly unchanged. This paragraph proves that an adjacent block is not rewritten.`,
	filler: `P5B3-FILLER-${token} remains exactly unchanged. The temporary draft describes a small editorial workflow in enough detail for title and summary generation. An editor reviews a Cloud suggestion inside WordPress, accepts only the selected whole paragraph block, and then performs one normal local save. The browser proof separates suggestion generation from local persistence so that Cloud never appears to own the WordPress write. This additional context intentionally keeps the content above the official WordPress AI minimum character threshold.${creditMeteringPadding}`,
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
let fakeProvider = null;
let fakeProviderEvidence = null;
let fakeProviderCleanup = { optionDeleted: false, pluginDeleted: false };
let qualityCorrelationEvidence = null;
let titleFlowStartedAt = 0;
let titleFirstSuggestionAt = 0;
let titleInsertedAt = 0;
let titleSaveCompletedAt = 0;
let titleRegenerationCount = 0;
let titleEditedBeforeInsert = false;
let creditBefore = null;
let creditAfter = null;
let providerLedgerPreflight = null;
const providerLedgerClaims = [];
const abilityResponses = [];
const preSaveWrites = [];
const saveWrites = [];

try {
	baseUrl = localBaseUrl(env('WP_BASE_URL', 'https://magick-ai.local'));
	const readiness = preflight();
	assertReadiness(baseUrl, readiness);
	if (qualityValidationMode) {
		assert(fakeProviderMode, 'Quality-correlation validation requires fake-provider mode.');
		assert(readiness.monitoring_enabled === true, 'Quality-correlation validation requires verified metadata-only monitoring.');
	}
	if (providerQualityValidationMode) {
		assert(!fakeProviderMode, 'Real quality-correlation validation requires configured Cloud Provider mode.');
		assert(
			readiness.monitoring_enabled === true,
			'Real quality-correlation validation requires explicit metadata-only monitoring before any Provider dispatch.'
		);
		providerLedgerPreflight = preflightProviderLedgerPlan(providerLedgerPlan);
	}
	if (creditAssertionMode) {
		assert(!fakeProviderMode, 'AI-credit delta validation requires configured Cloud Provider mode.');
		creditBefore = readAiCreditSummary('AI-credit baseline');
		pass(`AI-credit baseline is used=${creditBefore.used}, limit=${creditBefore.limit}, remaining=${creditBefore.remaining}.`);
	}
	if (fakeProviderMode) {
		fakeProvider = installFakeProvider(token);
		pass('Fake-provider mode is active without a real Provider dispatch.');
	}

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
	titleFlowStartedAt = Date.now();
	if (providerQualityValidationMode) {
		providerLedgerClaims.push(claimProviderLedgerDispatch(providerLedgerPlan, 'title_generation'));
	}
	await titleGenerate.click();
	if (fakeProviderMode) {
		await waitForCondition(
			page,
			async () => abilityResponses.some((entry) => entry.kind === 'title-generation' && entry.status >= 400),
			'first synthetic title failure response',
			15000
		);
		const errorNotice = await waitForVisibleLocator(
			page,
			[page.locator('.components-notice.is-error'), editorFrame.locator('.components-notice.is-error')],
			'provider failure notice'
		);
		assert((await errorNotice.innerText()).trim().length > 0, 'Failure recovery evidence: the first synthetic Provider failure is visible to the editor.');
		assert(samePersistedSnapshot(initialSnapshot, databaseSnapshot(postId)), 'Failure recovery evidence: the failed title attempt leaves the stored draft unchanged.');
		await waitForCondition(page, async () => !(await titleGenerate.isDisabled().catch(() => true)), 'title retry control');
		await titleGenerate.click();
	}
	const titleModal = await waitForVisibleLocator(
		page,
		[page.locator('.ai-title-generation-modal'), editorFrame.locator('.ai-title-generation-modal')],
		'Title suggestion review modal',
		90000
	);
	const titleInsertLabel = await titleModal.evaluate(() => window.wp.i18n.__('Insert', 'ai'));
	const titleTextarea = titleModal.locator('textarea').first();
	await waitForCondition(page, async () => (await titleTextarea.inputValue().catch(() => '')).trim().length > 0, 'generated title text', 15000);
	titleFirstSuggestionAt = Date.now();
	const firstGeneratedTitle = (await titleTextarea.inputValue()).trim();
	assert(firstGeneratedTitle.length > 0, 'UI review evidence: Title suggestion contains generated text before Insert.');
	assert(samePersistedSnapshot(initialSnapshot, databaseSnapshot(postId)), 'Data-path evidence: title generation returned while post fields and revisions remained unchanged before Insert.');
	let acceptedTitle = firstGeneratedTitle;
	if (fakeProviderMode) {
		const regenerateLabel = await titleModal.evaluate(() => window.wp.i18n.__('Regenerate', 'ai'));
		const regenerateButton = titleModal.getByRole('button', { name: regenerateLabel, exact: true });
		assert(await regenerateButton.isVisible() && !(await regenerateButton.isDisabled()), 'Recovery evidence: Regenerate is available after the successful retry.');
		await regenerateButton.click();
		await waitForCondition(
			page,
			async () => {
				const candidate = (await titleTextarea.inputValue().catch(() => '')).trim();
				return candidate.length > 0 && candidate !== firstGeneratedTitle;
			},
			'regenerated title text',
			15000
		);
		titleRegenerationCount = 1;
		const regeneratedTitle = (await titleTextarea.inputValue()).trim();
		acceptedTitle = `${regeneratedTitle}（人工编辑）`;
		await titleTextarea.fill(acceptedTitle);
		titleEditedBeforeInsert = true;
		assert((await titleTextarea.inputValue()).trim() === acceptedTitle, 'Adoption evidence: the editor changes the regenerated title before Insert.');
		assert(samePersistedSnapshot(initialSnapshot, databaseSnapshot(postId)), 'Data-path evidence: Regenerate and title editing still cause no WordPress write.');
	}
	const titleInsert = titleModal.getByRole('button', { name: titleInsertLabel, exact: true });
	assert(await titleInsert.isVisible(), 'UI review evidence: the localized Insert control is visible in the title review modal.');
	await titleInsert.click();
	titleInsertedAt = Date.now();
	await titleModal.waitFor({ state: 'hidden', timeout: 15000 });
	const titleAppliedState = await editorState(page);
	assert(titleAppliedState.title === acceptedTitle && titleAppliedState.dirty, 'UI review evidence: Insert applies the reviewed title only to dirty editor state.');
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
	if (providerQualityValidationMode) {
		providerLedgerClaims.push(claimProviderLedgerDispatch(providerLedgerPlan, 'content_summary'));
	}
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
	if (providerQualityValidationMode) {
		providerLedgerClaims.push(claimProviderLedgerDispatch(providerLedgerPlan, 'content_rewrite'));
	}
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
		!/(?:Both rephrasings|Both versions|如果你愿意|以下是|下面是)/i.test(suggestedReviewText),
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
	titleSaveCompletedAt = Date.now();
	assert(saveWrites.length >= 1, 'API evidence: normal Save/Update issued a WordPress post REST write.');
	assert(preSaveWrites.length === 0, 'API evidence: no autosave or post write raced ahead of the explicit Save/Update request.');
	assert(await unlockAutosaving(page), 'Fixture autosave lock is released only after the explicit Save/Update completes.');
	autosaveLocked = false;

	const finalSnapshot = databaseSnapshot(postId);
	const finalParagraphs = finalSnapshot.top_level.filter((block) => block.name === 'core/paragraph');
	assert(finalSnapshot.status === 'draft', 'Persistence evidence: the normal local save preserves draft status.');
	assert(finalSnapshot.title === acceptedTitle, 'Persistence evidence: saved title equals the reviewed Title suggestion.');
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
	if (fakeProviderMode) {
		fakeProviderEvidence = readFakeProviderEvidence(fakeProvider);
		const fakeEvents = Array.isArray(fakeProviderEvidence.events) ? fakeProviderEvidence.events : [];
		const titleEvents = fakeEvents.filter((entry) => entry.task === 'title_generation');
		console.log(`FAKE_PROVIDER_SCALAR_EVENTS=${JSON.stringify(fakeEvents)}`);
		assert(
			titleEvents.length === 3
			&& titleEvents[0].outcome === 'provider_unavailable'
			&& titleEvents.slice(1).every((entry) => entry.outcome === 'succeeded'),
			'Fake-provider evidence: title failure, retry, and regenerate consumed exactly three bounded attempts.'
		);
		assert(
			fakeEvents.length === 5
			&& fakeEvents.every((entry) => entry.transport_preempted === true)
			&& fakeEvents.every((entry) => entry.data_classification === 'internal')
			&& fakeEvents.every((entry) => entry.storage_mode === 'result_only')
			&& fakeEvents.every((entry) => entry.suggestion_only === true),
			'Fake-provider evidence: all three editor tasks remain internal, result-only, suggestion-only, and network-preempted.'
		);
	}
	if (qualityEvidenceMode) {
		qualityCorrelationEvidence = readQualityCorrelationEvidence(postId);
	}
	if (qualityValidationMode) {
		assert(
			qualityCorrelationEvidence.event_total === 8
			&& qualityCorrelationEvidence.session_total === 3
			&& qualityCorrelationEvidence.pending_count === 0,
			'Quality evidence: four successful generations resolve into three complete editor sessions.'
		);
		assert(
			qualityCorrelationEvidence.kind_counts?.['addon.editor_assist.generation.completed'] === 4
			&& qualityCorrelationEvidence.kind_counts?.['addon.editor_assist.generation.repeated'] === 1
			&& qualityCorrelationEvidence.kind_counts?.['addon.editor_assist.outcome.observed'] === 3,
			'Quality evidence: completed, Regenerate, and local-save event counts are complete.'
		);
		assert(
			qualityCorrelationEvidence.task_counts?.title_generation === 4
			&& qualityCorrelationEvidence.task_counts?.content_summary === 2
			&& qualityCorrelationEvidence.task_counts?.content_rewrite === 2
			&& qualityCorrelationEvidence.outcome_by_task?.title_generation?.saved_after_generation_unmatched === 1
			&& qualityCorrelationEvidence.outcome_by_task?.content_summary?.saved_exact_output === 1
			&& qualityCorrelationEvidence.outcome_by_task?.content_rewrite?.saved_exact_output === 1,
			'Quality evidence: title editing, summary adoption, and rewrite adoption are classified by task.'
		);
		assert(
			qualityCorrelationEvidence.invalid_content_storage === 0
			&& Array.isArray(qualityCorrelationEvidence.forbidden_fields)
			&& qualityCorrelationEvidence.forbidden_fields.length === 0,
			'Quality evidence: Cloud-bound correlation contains only metadata and omits content and local identities.'
		);
	}
	if (providerQualityValidationMode) {
		assert(
			qualityCorrelationEvidence.event_total === 6
			&& qualityCorrelationEvidence.session_total === 3
			&& qualityCorrelationEvidence.pending_count === 0,
			'Real quality evidence: three successful generations resolve into three complete editor sessions.'
		);
		assert(
			qualityCorrelationEvidence.kind_counts?.['addon.editor_assist.generation.completed'] === 3
			&& (qualityCorrelationEvidence.kind_counts?.['addon.editor_assist.generation.repeated'] || 0) === 0
			&& qualityCorrelationEvidence.kind_counts?.['addon.editor_assist.outcome.observed'] === 3,
			'Real quality evidence: each generated suggestion has one metadata-only local-save outcome.'
		);
		assert(
			qualityCorrelationEvidence.task_counts?.title_generation === 2
			&& qualityCorrelationEvidence.task_counts?.content_summary === 2
			&& qualityCorrelationEvidence.task_counts?.content_rewrite === 2
			&& qualityCorrelationEvidence.outcome_by_task?.title_generation?.saved_exact_output === 1
			&& qualityCorrelationEvidence.outcome_by_task?.content_summary?.saved_exact_output === 1
			&& qualityCorrelationEvidence.outcome_by_task?.content_rewrite?.saved_exact_output === 1,
			'Real quality evidence: title, summary, and rewrite adoption are classified independently.'
		);
		assert(
			qualityCorrelationEvidence.invalid_content_storage === 0
			&& Array.isArray(qualityCorrelationEvidence.forbidden_fields)
			&& qualityCorrelationEvidence.forbidden_fields.length === 0,
			'Real quality evidence: Cloud-bound correlation contains only metadata and omits content and local identities.'
		);
	}
	if (qualityEvidenceMode) {
		qualityCorrelationEvidence = {
			validated: true,
			mode: providerQualityValidationMode ? 'configured_cloud_provider' : 'local_fake_provider',
			...qualityCorrelationEvidence,
		};
	}
	if (creditAssertionMode) {
		creditAfter = readAiCreditSummary('AI-credit result');
		const usedDelta = creditAfter.used - creditBefore.used;
		const remainingDelta = creditAfter.remaining - creditBefore.remaining;
		assert(
			usedDelta === expectedCreditDelta,
			`AI-credit evidence: used increases by exactly ${expectedCreditDelta}.`
		);
		assert(
			remainingDelta === -expectedCreditDelta,
			`AI-credit evidence: remaining decreases by exactly ${expectedCreditDelta}.`
		);
		assert(
			creditAfter.limit === creditBefore.limit,
			'AI-credit evidence: the finite-period limit stays unchanged during consumption.'
		);
	}

	machineSummary = {
		contract: 'p5_b3_wordpress_ai_text_browser.v1',
		execution_mode: fakeProviderMode ? 'local_fake_provider' : 'configured_cloud_provider',
		site_origin: baseUrl,
		environment: readiness.environment,
		versions: {
			wordpress: readiness.wordpress_version,
			wordpress_ai: readiness.ai_version,
			cloud_addon: readiness.addon_version,
		},
		ui_review_evidence: {
			title_suggestion_inserted: true,
			title_suggestion_sha256: sha256(acceptedTitle),
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
		title_acceptance_evidence: {
			failed_attempts: abilityResponses.filter((entry) => entry.kind === 'title-generation' && entry.status >= 400).length,
			successful_attempts: abilityResponses.filter((entry) => entry.kind === 'title-generation' && entry.status >= 200 && entry.status < 300).length,
			regeneration_count: titleRegenerationCount,
			edited_before_insert: titleEditedBeforeInsert,
			inserted: true,
			saved: true,
			outcome: titleEditedBeforeInsert ? 'edited_then_saved' : 'inserted_then_saved',
			time_to_first_suggestion_ms: Math.max(0, titleFirstSuggestionAt - titleFlowStartedAt),
			insert_to_save_ms: Math.max(0, titleSaveCompletedAt - titleInsertedAt),
		},
		quality_correlation_evidence: qualityEvidenceMode ? qualityCorrelationEvidence : {
			validated: false,
		},
		ai_credit_evidence: creditAssertionMode ? {
			validated: true,
			expected_delta: expectedCreditDelta,
			before: creditBefore,
			after: creditAfter,
			used_delta: creditAfter.used - creditBefore.used,
			remaining_delta: creditAfter.remaining - creditBefore.remaining,
		} : {
			validated: false,
		},
		provider_call_ledger_evidence: providerQualityValidationMode ? {
			validated: true,
			contract_version: providerLedgerPreflight.contract_version,
			experiment_id: providerLedgerPreflight.experiment_id,
			preflight_claimed_calls: providerLedgerPreflight.claimed_calls,
			preflight_remaining_calls: providerLedgerPreflight.remaining_calls,
			claims: providerLedgerClaims,
			content_fields_recorded: false,
		} : {
			validated: false,
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
	if (fakeProvider) {
		try {
			fakeProviderCleanup = removeFakeProvider(fakeProvider);
			if (!fakeProviderCleanup.optionDeleted || !fakeProviderCleanup.pluginDeleted) {
				throw new Error('Disposable fake-provider state was not fully removed.');
			}
			pass('Disposable fake-provider plugin and scalar option were removed and verified absent.');
		} catch (cleanupError) {
			failure = failure || cleanupError;
			console.error(`FAIL: fake-provider cleanup: ${cleanupError.message || cleanupError}`);
		}
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
	machineSummary.fake_provider_evidence = {
		enabled: fakeProviderMode,
		events: fakeProviderMode && Array.isArray(fakeProviderEvidence?.events) ? fakeProviderEvidence.events : [],
		content_fields_recorded: false,
		option_deleted: fakeProviderMode ? fakeProviderCleanup.optionDeleted : true,
		plugin_deleted: fakeProviderMode ? fakeProviderCleanup.pluginDeleted : true,
	};
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
