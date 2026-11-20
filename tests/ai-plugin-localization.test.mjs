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

test('shipped JS registers AI 1.5 settings copy without widening default-domain exceptions', () => {
	const { hooks, registrations } = run();
	const { data, domain } = registrations[0];
	assert.equal(domain, 'ai');
	for (const [source, translated] of [
		['Block Container Label Generation', '容器区块标签生成'],
		['Revision Change Summary', '修订变更摘要'],
		['Runs automatically', '自动运行'],
		['When %s runs and what it sends', '%s何时运行、发送哪些内容'],
		['When you click Apply Editorial Updates, which also saves the post', '点击“应用编辑更新”时，同时会保存文章'],
	]) {
		assert.deepEqual(Array.from(data[source]), [translated]);
		assert.equal(hooks.get('i18n.gettext')(source, source, 'default'), source);
	}
	assert.equal(Object.keys(bundled).length, 19);
});

test('shipped JS translates exact bundled plurals and contexts', () => {
	const { hooks, registrations } = run();
	assert.equal(Object.keys(bundled).length, 19);
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
		['Filter', 'verb'],
		['<div>Page</div>%1$s<div>of %2$d</div>', 'paging'],
	]) {
		assert.equal(hooks.get('i18n.gettext_with_context')(source, source, context), bundled[source]);
	}
	for (const source of ['Sort by', 'Order', 'Density', 'Items per page', 'Properties', 'Add filter', 'No results', 'Actions']) {
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
	assert.equal(contextual('Filter', 'Filter', 'noun'), 'Filter');
	const paging = '<div>Page</div>%1$s<div>of %2$d</div>';
	assert.equal(contextual(paging, paging, 'other'), paging);
	assert.equal(contextual(paging, paging, 'paging', 'other'), paging);
	assert.equal(contextual('官方分页译文', paging, 'paging'), '官方分页译文');
	assert.equal(plain('Filter', 'Filter'), 'Filter');
	assert.equal(run({}).hooks.size, 0);
});


test('AI 1.5 editor and operator notices run through the shipped asset with placeholders intact', () => {
 const { registrations } = run();
 const data = registrations[0].data;
 for (const [source, translated] of [
  ["AI Client", "AI 客户端"],
  ["Audio", "音频"],
  ["Embeddings", "向量嵌入"],
  ["Image", "图片"],
  ["MCP Tool", "MCP 工具"],
  ["Metadata", "元数据"],
  ["Success", "成功"],
  ["Text", "文本"],
  ["Timeout", "超时"],
  ["Applies pending editorial Notes and saves the post. Save your current edits first.", "应用待处理的编辑建议并保存文章。请先保存当前修改。"],
  ["For example: shorter, focus on the practical steps. Recent suggestions are avoided automatically.", "例如：再简短一些，突出操作步骤。系统会避开最近生成的候选标题。"],
  ["No usable block content was returned. The original block is unchanged.", "未返回可用的区块内容，原区块保持不变。请在“编辑建议”侧栏检查该建议后重试。"],
  ["Open Notes", "打开编辑建议"],
  ["Save your current edits and wait for saving to finish before applying Editorial Updates.", "请先点击编辑器右上角的“保存”，等待保存完成后，再点击“应用编辑更新”。"],
  ["Saved blocks: %1$d; failed blocks: %2$d. Failed Notes remain pending. Review them in the Notes sidebar before trying again. %3$s", "已保存 %1$d 个区块，%2$d 个区块失败。失败建议仍待处理，请在“编辑建议”侧栏查看后重试。原因：%3$s"],
  ["This repeats a recent title. Add a different angle in the title guidance and regenerate.", "本次标题与最近候选重复。请在“标题修改要求”中换个角度，再点击“重新生成”。"],
  ["Title guidance (optional)", "标题修改要求（可选）"],
  ['Generate Block Labels', '生成区块标签'],
  ['Change summary', '变更摘要'],
  ['Generated by AI. Check the changes before relying on this summary.', '此摘要由 AI 生成，使用前请核对实际变更。'],
  ['%d blocks were not translated because the editor changed while translating.', '翻译期间编辑器内容发生变化，已跳过 %d 个区块，保留你的修改。'],
  ['Successfully deleted %s log entries.', '已成功删除 %s 条日志。'],
  ['Choose which abilities are exposed to AI agents through the MCP server. Abilities keep their default visibility unless changed here.', '选择通过 MCP 服务器向 AI 代理开放的能力；未在此修改的能力保留默认可见性。'],
 ]) {
  assert.deepEqual(Array.from(data[source]), [translated]);
 }
 const tokens = (text) => (text.match(/%(?:[0-9]+\$)?[sd]/g) || []).sort();
 for (const [source, translated] of Object.entries(translations)) {
  assert.deepEqual(tokens(translated), tokens(source), `Placeholder contract: ${source}`);
 }
});
