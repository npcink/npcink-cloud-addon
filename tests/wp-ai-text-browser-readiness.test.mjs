import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import { runInNewContext } from 'node:vm';
import { execFileSync } from 'node:child_process';

// Exercise the actual readiness guard without importing the opt-in runner.
const source = readFileSync(new URL('../scripts/smoke-wordpress-ai-text-browser.mjs', import.meta.url), 'utf8');
const matrix = JSON.parse(readFileSync(new URL('./fixtures/wp-ai-compatibility-matrix.json', import.meta.url), 'utf8'));
const guard = source.slice(source.indexOf('function assertReadiness('), source.indexOf('\nfunction createFixture('));
const check = runInNewContext(`${guard}\nassertReadiness`, { assert, URL });
const ready = {
	environment: 'local', home_url: 'http://fixture.local/', ai_active: true,
	ai_version: '1.3.0', addon_loaded: true, addon_verified: true,
	connector_enabled: true, features: { global: true, title: true, summary: true, resizing: true },
	has_administrator: true,
	text_capability: {
		state: 'configured', reason_code: 'configured',
		configuration_state: 'configured', entitlement_state: 'configured',
	},
};

test('verified credentials cannot bypass missing, stale, or unavailable text capability', () => {
	for (const text_capability of [
		undefined, {},
		{ ...ready.text_capability, state: 'unavailable', configuration_state: 'unavailable', reason_code: 'no_eligible_model' },
		{ ...ready.text_capability, state: 'unknown', reason_code: 'snapshot_expired' },
		{ ...ready.text_capability, state: 'unknown', reason_code: 'refresh_failed' },
		{ ...ready.text_capability, entitlement_state: 'unavailable' },
	]) {
		assert.throws(() => check('http://fixture.local', { ...ready, text_capability }), /Cloud text capability/);
	}
	assert.throws(() => check('http://fixture.local', {
		...ready, text_capability: { state: 'unavailable', reason_code: 'no_eligible_model' },
	}), /reason=no_eligible_model/);
});

test('synthetic failure evidence requires the actual fixture transport attempt', () => {
	const code = source.slice(source.indexOf('function assertSyntheticTitleFailure('), source.indexOf('\nfunction readFakeProviderEvidence('));
	const validate = runInNewContext(`${code}\nassertSyntheticTitleFailure`, { assert });
	const event = { task: 'title_generation', outcome: 'provider_unavailable', transport_preempted: true };
	assert.doesNotThrow(() => validate({ title_calls: 1, events: [event] }));
	for (const evidence of [
		undefined, { title_calls: 0, events: [] },
		{ title_calls: 1, events: [null] },
		{ title_calls: 2, events: [event, event] },
		{ title_calls: 1, events: [{ ...event, task: 'content_summary' }] },
		{ title_calls: 1, events: [{ ...event, outcome: 'succeeded' }] },
		{ title_calls: 1, events: [{ ...event, transport_preempted: false }] },
	]) {
		assert.throws(() => validate(evidence), /unrelated ability error/);
	}
});

test('accepts only explicitly reviewed active AI versions', () => {
	for (const ai_version of ['1.2.0', '1.3.0']) {
		assert.doesNotThrow(() => check('http://fixture.local', { ...ready, ai_version }));
	}
	for (const ai_version of ['', '1.1.0', '1.3.1', '1.3.0-beta', '2.0.0']) {
		assert.throws(() => check('http://fixture.local', { ...ready, ai_version }), /WordPress AI/);
	}
	assert.throws(() => check('http://fixture.local', { ...ready, ai_active: false }), /WordPress AI/);
});

test('declares stable and upstream-warning compatibility lanes without widening the Cloud contract', () => {
	assert.equal(matrix.contract_version, 'wordpress_ai_compatibility_matrix.v1');
	assert.deepEqual(matrix.lanes.map((lane) => lane.lane_id), [
		'stable-primary', 'stable-regression', 'upstream-warning',
	]);
	const primary = matrix.lanes.find((lane) => lane.lane_id === 'stable-primary');
	const regression = matrix.lanes.find((lane) => lane.lane_id === 'stable-regression');
	const warning = matrix.lanes.find((lane) => lane.lane_id === 'upstream-warning');
	assert.equal(primary.wordpress, '7.0.4');
	assert.equal(regression.wordpress, '7.1.1');
	assert.equal(warning.wordpress, '7.1.1');
	assert.deepEqual(primary.wordpress_ai_versions, ['1.3.0']);
	assert.deepEqual(regression.wordpress_ai_versions, ['1.2.0']);
	assert.deepEqual(primary.wordpress_ai_urls, ['https://api.github.com/repos/WordPress/ai/releases/assets/519975984']);
	assert.deepEqual(regression.wordpress_ai_urls, ['https://api.github.com/repos/WordPress/ai/releases/assets/477122229']);
	assert.equal(warning.gate, 'non_blocking_warning');
	assert.equal(warning.wordpress_ai_versions[0], 'develop');
	assert.deepEqual(warning.wordpress_ai_urls, ['https://github.com/WordPress/ai/archive/refs/heads/develop.zip']);
	assert.deepEqual(primary.php_versions, ['8.0', '8.2', '8.4']);
});

test('version compatibility does not bypass site or connection safeguards', () => {
	for (const patch of [
		{ environment: 'production' }, { home_url: 'http://another.local/' },
		{ addon_loaded: false }, { addon_verified: false }, { connector_enabled: false },
		{ features: { ...ready.features, summary: false } }, { has_administrator: false },
	]) {
		assert.throws(() => check('http://fixture.local', { ...ready, ...patch }));
	}
});

function phpJson(code) {
	return JSON.parse(execFileSync(process.env.TEST_PHP || 'php', ['-r', code], { encoding: 'utf8' }));
}

test('summary persistence reads the exact reviewed version key without legacy fallback', () => {
	const code = source.slice(source.indexOf('function summaryMetaKey('), source.indexOf('\nfunction databaseSnapshot('));
	const key = runInNewContext(`${code}\nsummaryMetaKey`);
	assert.equal(key('1.2.0'), 'ai_generated_summary');
	assert.equal(key('1.3.0'), 'wpai_generated_summary');
	assert.throws(() => key('1.3.1'));
});

const fakeSource = source.slice(source.indexOf('function fakeProviderPluginSource('), source.indexOf('\nfunction installFakeProvider('));
const generateFake = runInNewContext(`${fakeSource}\nfakeProviderPluginSource`, {
	phpString: (value) => `'${value.replaceAll("'", "\\'")}'`,
});

test('fake transport blocks uploads and never falls through to paid calls after expiry', () => {
	for (const expired of [false, true]) {
		const php = generateFake('fixture', 'fake_option', expired ? 1 : 4102444800).replace(/^<\?php/, '');
		const result = phpJson(`
define('ABSPATH', '/test/');
class WP_Error { public function __construct(public $code, public $message) {} }
function wp_get_environment_type() { return 'local'; }
function home_url($path) { return 'http://fixture.local/'; }
function wp_parse_url($url, $part) { return parse_url($url, $part); }
function get_option($name, $default) { return array('token' => 'fixture'); }
function add_filter($name, $callback, $priority, $args) { $GLOBALS['filter'] = $callback; }
${php}
if (false !== $GLOBALS['filter'](false, array(), 'http://cloud.test/health/live')) {
	throw new RuntimeException('Unrelated health traffic must not be blocked.');
}
$results = array();
	foreach (array('/v1/observability/plugin-events', '/v1/customer-journey/events', '/v1/runtime/execute') as $path) {
	if (${expired ? 'true' : 'false'} || str_contains($path, 'plugin-events') || str_contains($path, 'customer-journey')) {
		$value = $GLOBALS['filter'](false, array(), 'http://cloud.test' . $path);
		$results[] = $value instanceof WP_Error ? $value->code : 'NETWORK_LEAK';
	}
}
echo json_encode($results);
`);
		assert.deepEqual(result, expired
			? ['npcink_browser_fake_expired', 'npcink_browser_fake_expired', 'npcink_browser_fake_expired']
			: ['npcink_browser_fake_upload_isolated', 'npcink_browser_fake_upload_isolated']);
	}
});

test('fake transport records exact customer-journey event ownership for cleanup', () => {
	const php = generateFake('fixture', 'fake_option', 4102444800, 2).replace(/^<\?php/, '');
	const result = phpJson(`
define('ABSPATH', '/test/');
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name, $value, $autoload) { $GLOBALS['options'][$name] = $value; }
function add_filter($name, $callback, $priority, $args) { $GLOBALS['filters'][$name] = $callback; }
function wp_get_environment_type() { return 'local'; }
function home_url($path) { return 'http://fixture.local/'; }
function wp_parse_url($url, $part) { return parse_url($url, $part); }
class WP_Error { public function __construct(public $code, public $message) {} }
$GLOBALS['options'] = array('fake_option' => array(
	'token' => 'fixture',
	'journey_baseline_event_ids' => array('baseline-event'),
	'journey_event_ids' => array(),
));
${php}
$journey_filter = $GLOBALS['filters']['pre_update_option_npcink_cloud_addon_customer_journey_buffer'];
$journey_filter(
	array(
		array('event_id' => 'baseline-event'),
		array('event_id' => 'owned-event-without-run-id'),
		array('event_id' => 'owned-event-with-run-id', 'run_id' => 'run_browser_fake_fixture_1'),
	),
	array(),
);
echo json_encode($GLOBALS['options']['fake_option']['journey_event_ids']);
`);
	assert.deepEqual(result, ['owned-event-without-run-id', 'owned-event-with-run-id']);
});

test('fixture cleanup removes only owned records, preserves absent options and is idempotent', () => {
	const cleanupSource = source.slice(source.indexOf('function fixtureQualityCleanupSource('), source.indexOf('\nfunction cleanupFixtureQuality('));
	const generateCleanup = runInNewContext(`${cleanupSource}\nfixtureQualityCleanupSource`, { assert, Number });
	assert.throws(() => generateCleanup(0));
	assert.throws(() => generateCleanup('42'));
	const php = generateCleanup(42);
	const result = phpJson(`
class Npcink_Cloud_Observability_Collector { const BUFFER_OPTION = 'events'; }
class Npcink_Cloud_Editor_Assist_Quality { const PENDING_OPTION = 'pending'; }
function wp_salt($kind) { return 'test-salt'; }
function get_option($name, $default = false) { return $GLOBALS['options'][$name] ?? $default; }
function update_option($name, $value, $autoload) { $GLOBALS['options'][$name] = $value; }
function wp_json_encode($value) { return json_encode($value); }
$foreign = array('quality_contract' => 'editor_assist_quality.v2', 'object_scope_hash' => 'other');
$options = array('events' => array(
	array('quality_contract' => 'editor_assist_quality.v2', 'object_scope_hash' => hash_hmac('sha256', '42|title_generation', 'test-salt')),
	$foreign,
	array('event_kind' => 'unrelated'),
), 'pending' => array(array('post_id' => 42), array('post_id' => 43)));
ob_start(); ${php} $first = json_decode(ob_get_clean(), true);
ob_start(); ${php} $second = json_decode(ob_get_clean(), true);
$preserved = $options;
$options = array();
ob_start(); ${php} ob_end_clean();
echo json_encode(array('first' => $first, 'second' => $second, 'preserved' => $preserved, 'absent' => count($options)));
`);
	assert.deepEqual(result.first, { events: 1, pending: 1 });
	assert.deepEqual(result.second, { events: 0, pending: 0 });
	assert.equal(result.preserved.events.length, 2);
	assert.deepEqual(result.preserved.pending, [{ post_id: 43 }]);
	assert.equal(result.absent, 0);
});
