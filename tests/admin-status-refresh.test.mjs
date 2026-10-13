import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// Minimal DOM seams for the shipped controllers; PHP tests cover escaped markup.
function node(textContent = '') {
	return {
		textContent, hidden: false, disabled: false, dataset: {}, listeners: {},
		classList: { toggle() {}, remove() {}, add() {} },
		setAttribute() {}, querySelector() { return null; },
		closest() { return null; }, addEventListener(type, listener) { this.listeners[type] = listener; },
	};
}
const settle = () => new Promise((resolve) => setImmediate(resolve));
function run(asset, nodes, config, responses, DOMParser) {
	let calls = 0;
	const document = { querySelector: (selector) => nodes[selector] || null, querySelectorAll: () => [] };
	const window = { ...config, fetch: async () => {
		calls++;
		const response = responses.shift();
		if (response instanceof Error) throw response;
		return { ok: true, json: async () => response };
	} };
	vm.runInNewContext(readFileSync(new URL('../assets/' + asset, import.meta.url), 'utf8'), { window, document, URLSearchParams, DOMParser });
	return () => calls;
}
const config = { ajaxUrl: '/admin-ajax.php', action: 'read', nonce: 'test', failedLabel: '暂时无法读取。', updateFailedLabel: '更新失败' };

test('entitlement refresh clears stale banner and retains the latest successful value on a later failure', async () => {
	const container = node();
	container.dataset.npcinkEntitlementState = 'stale';
	const summary = node('Old plan');
	const retry = node();
	container.querySelector = (selector) => ({ '[data-npcink-entitlement-summary]': summary, '[data-npcink-entitlement-retry]': retry }[selector] || null);
	const connection = node();
	connection.innerHTML = 'Old stale warning';
	const service = node('Old status');
	const nodes = { '[data-npcink-entitlement-state]': container, '[data-npcink-connection-summary]': connection, '.npcink-cloud-overview-service': service };
	const calls = run('admin-entitlement.js', nodes, { npcinkCloudEntitlement: config }, [
		{ success: true, data: { label: 'New plan', state: 'fresh', status_fragments: { connection: '', service: { label: 'Recently reachable', severity: 'ok' } } } },
		new Error('Failed to fetch'),
	]);
	await settle();
	assert.equal(connection.innerHTML, '');
	assert.equal(service.textContent, 'Recently reachable');
	assert.equal(retry.hidden, true);
	retry.listeners.click();
	await settle();
	assert.equal(summary.textContent, 'New plan · 更新失败');
	assert.equal(retry.hidden, false);
	assert.equal(calls(), 2);
});

test('knowledge read failure offers a Chinese retry; retry replaces the whole old status and hides recovery controls', async () => {
	const controller = node();
	controller.dataset.npcinkSiteKnowledgeState = 'stale';
	const waiting = node('Updates waiting: 1000');
	const retry = node();
	retry.hidden = true;
	const readStatus = node();
	readStatus.hidden = true;
	const connection = node();
	connection.innerHTML = 'Old warning';
	const knowledge = node('Updating and old recovery controls');
	const newRetry = node();
	newRetry.hidden = true;
	const replacement = node('All public content is up to date');
	replacement.querySelector = (selector) => selector === '[data-npcink-site-knowledge-status-retry]' ? newRetry : null;
	const nodes = {
		'[data-npcink-site-knowledge-refresh]': controller,
		'[data-npcink-site-knowledge-waiting]': waiting,
		'[data-npcink-site-knowledge-status-retry]': retry,
		'[data-npcink-site-knowledge-read-status]': readStatus,
		'[data-npcink-connection-summary]': connection,
		'.npcink-cloud-site-knowledge-summary': knowledge,
	};
	knowledge.replaceWith = (value) => { nodes['.npcink-cloud-site-knowledge-summary'] = value; };
	class Parser {
		parseFromString(html, type) {
			assert.equal(html, 'Escaped current server markup');
			assert.equal(type, 'text/html');
			return { querySelector: () => replacement };
		}
	}
	const calls = run('admin-site-knowledge.js', nodes, { npcinkCloudSiteKnowledge: config }, [
		new Error('Failed to fetch'),
		{ success: true, data: { available: true, state: 'fresh', waiting_count: 0, waiting_label: '', status_fragments: { connection: '', knowledge: 'Escaped current server markup' } } },
	], Parser);
	await settle();
	assert.equal(readStatus.textContent, '暂时无法读取。');
	assert.equal(retry.hidden, false);
	assert.equal(waiting.hidden, true);
	assert.equal(connection.innerHTML, 'Old warning');
	retry.listeners.click();
	await settle();
	assert.equal(nodes['.npcink-cloud-site-knowledge-summary'], replacement);
	assert.equal(connection.innerHTML, '');
	assert.equal(newRetry.hidden, true);
	assert.equal(newRetry.disabled, false);
	assert.equal(typeof newRetry.listeners.click, 'function');
	assert.equal(calls(), 2);
});
