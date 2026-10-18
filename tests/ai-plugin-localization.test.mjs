import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import vm from 'node:vm';
import { fileURLToPath } from 'node:url';

const asset = readFileSync(new URL('../assets/ai-plugin-localization.js', import.meta.url), 'utf8');
const root = fileURLToPath(new URL('..', import.meta.url));
const translations = JSON.parse(execFileSync('php', ['-r',
	`define('ABSPATH', '/'); require $argv[1]; echo json_encode(Npcink_Cloud_AI_Plugin_Localization::translations());`,
	`${root}includes/class-ai-plugin-localization.php`], { encoding: 'utf8' }));
const bundled = JSON.parse(execFileSync('php', ['-r',
	`define('ABSPATH', '/'); require $argv[1]; echo json_encode(Npcink_Cloud_AI_Plugin_Localization::bundled_ui_fallback());`,
	`${root}includes/class-ai-plugin-localization.php`], { encoding: 'utf8' }));

function run(exceptions = bundled) {
	const hooks = new Map();
	const registrations = [];
	vm.runInNewContext(asset, { window: {
		NpcinkCloudAiPluginLocalization: {
			localeData: Object.fromEntries(Object.entries(translations).map(([key, value]) => [key, [value]])),
			bundledUiFallback: exceptions,
		},
		wp: {
			i18n: {
				getLocaleData: () => ({ 'Generate Image': ['官方图片译文'] }),
				setLocaleData: (data, domain) => registrations.push({ data, domain }),
			},
			hooks: { addFilter: (name, namespace, callback) => hooks.set(name, callback) },
		},
	} });
	return { hooks, registrations };
}

test('shipped JS translates exact bundled plurals and contexts', () => {
	const { hooks, registrations } = run();
	assert.equal(Object.keys(bundled).length, 16);
	for (const [single, plural] of [
		['%d Item selected', '%d Items selected'],
		['%d field needs attention', '%d fields need attention'],
	]) {
		for (const count of [1, 4]) {
			const result = hooks.get('i18n.ngettext')(count === 1 ? single : plural, single, plural, count, 'default');
			assert.equal(result.replace('%d', count), bundled[single].replace('%d', count));
		}
	}
	for (const [source, context] of [
		['Comfortable', 'Density option for DataView layout'],
		['Balanced', 'Density option for DataView layout'],
		['Compact', 'Density option for DataView layout'],
		['View options', 'View is used as a noun'],
		['Edit %s (has errors)', 'field'],
	]) {
		assert.equal(hooks.get('i18n.gettext_with_context')(source, source, context), bundled[source]);
	}
	for (const source of ['Sort by', 'Order', 'Density', 'Items per page', 'Properties', 'Add filter', 'No results']) {
		assert.equal(hooks.get('i18n.gettext')(source, source, 'default'), bundled[source]);
	}
	assert.equal(registrations.length, 1);
	assert.equal(registrations[0].domain, 'ai');
	assert.equal(registrations[0].data['Generate Image'], undefined);
});

test('exceptions preserve official translations, foreign domains, contexts and unrelated strings', () => {
	const { hooks } = run();
	const plural = hooks.get('i18n.ngettext');
	assert.equal(plural('官方译文', '%d Item selected', '%d Items selected', 3), '官方译文');
	assert.equal(plural('%d Items selected', '%d Item selected', '%d Items selected', 3, 'other'), '%d Items selected');
	assert.equal(plural('Items', 'Item', 'Items', 3), 'Items');
	assert.equal(plural('%d Item selected', '%d Item selected', 'Unexpected plural', 3), '%d Item selected');
	const plain = hooks.get('i18n.gettext');
	assert.equal(plain('官方译文', 'Order'), '官方译文');
	assert.equal(plain('Order', 'Order', 'other'), 'Order');
	assert.equal(plain('Unrelated', 'Unrelated'), 'Unrelated');
	const contextual = hooks.get('i18n.gettext_with_context');
	assert.equal(contextual('官方译文', 'Comfortable', 'Density option for DataView layout'), '官方译文');
	assert.equal(contextual('Comfortable', 'Comfortable', 'other'), 'Comfortable');
	assert.equal(contextual('Comfortable', 'Comfortable', 'Density option for DataView layout', 'other'), 'Comfortable');
	assert.equal(contextual('Edit %s', 'Edit %s', 'field'), 'Edit %s');
	assert.equal(run({}).hooks.size, 0);
});
