import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

// Minimal DOM seams for the shipped controllers; PHP tests cover escaped markup.
function node(textContent = '') {
	const classes = new Set();
	const element = {
		textContent, hidden: false, disabled: false, dataset: {}, listeners: {},
		classes, className: '',
		classList: {
			toggle(name, enabled) { enabled ? classes.add(name) : classes.delete(name); },
			remove(...names) { names.forEach((name) => classes.delete(name)); },
			add(...names) { names.forEach((name) => classes.add(name)); },
		},
		setAttribute() {}, querySelector() { return null; },
		closest() { return null; }, addEventListener(type, listener) { this.listeners[type] = listener; },
	};
	element.classList.remove = (...names) => { names.forEach((name) => classes.delete(name)); element.className = [ 'npcink-cloud-overview-service', ...classes ].join(' '); };
	element.classList.add = (...names) => { names.forEach((name) => classes.add(name)); element.className = [ 'npcink-cloud-overview-service', ...classes ].join(' '); };
	return element;
}
const settle = () => new Promise((resolve) => setImmediate(resolve));
function run(asset, nodes, config, responses, DOMParser) {
	let calls = 0;
	const requests = [];
	const document = { querySelector: (selector) => nodes[selector] || null, querySelectorAll: () => [] };
	const window = { ...config, fetch: async (url, options) => {
		calls++;
		requests.push({ url, options });
		const response = responses.shift();
		if (response instanceof Error) throw response;
		return { ok: true, json: async () => response };
	} };
	const context = { window, document, URLSearchParams, DOMParser };
	vm.runInNewContext(readFileSync(new URL('../assets/admin-status-fragments.js', import.meta.url), 'utf8'), context);
	vm.runInNewContext(readFileSync(new URL('../assets/' + asset, import.meta.url), 'utf8'), context);
	return { calls: () => calls, requests };
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
	const result = run('admin-entitlement.js', nodes, { npcinkCloudEntitlement: config }, [
		{ success: true, data: { label: 'New plan', state: 'fresh', status_fragments: { connection: '', service: { label: 'Recently reachable', severity: 'ok' } } } },
		new Error('Failed to fetch'),
	]);
	await settle();
	assert.equal(connection.innerHTML, '');
	assert.equal(service.textContent, 'Recently reachable');
	assert.equal(service.className, 'npcink-cloud-overview-service npcink-cloud-overview-service--ok');
	assert.equal(retry.hidden, true);
	retry.listeners.click();
	await settle();
	assert.equal(summary.textContent, 'New plan · 更新失败');
	assert.equal(retry.hidden, false);
	assert.equal(result.calls(), 2);
	assert.match(result.requests[1].options.body, /mode=retry/);
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
	const replacementReadStatus = node();
	replacementReadStatus.hidden = true;
	const replacementWaiting = node('Updates waiting: 0');
	replacementWaiting.hidden = true;
	replacement.querySelector = (selector) => {
		if ( '[data-npcink-site-knowledge-status-retry]' === selector ) { return newRetry; }
		if ( '[data-npcink-site-knowledge-read-status]' === selector ) { return replacementReadStatus; }
		if ( '[data-npcink-site-knowledge-waiting]' === selector ) { return replacementWaiting; }
		return null;
	};
	const nodes = {
		'[data-npcink-site-knowledge-refresh]': controller,
		'[data-npcink-site-knowledge-waiting]': waiting,
		'[data-npcink-site-knowledge-status-retry]': retry,
		'[data-npcink-site-knowledge-read-status]': readStatus,
		'[data-npcink-connection-summary]': connection,
		'.npcink-cloud-site-knowledge-summary': knowledge,
	};
	let replaced = false;
	knowledge.replaceWith = (value) => {
		replaced = true;
		nodes['.npcink-cloud-site-knowledge-summary'] = value;
		nodes['[data-npcink-site-knowledge-status-retry]'] = newRetry;
		nodes['[data-npcink-site-knowledge-read-status]'] = replacementReadStatus;
		nodes['[data-npcink-site-knowledge-waiting]'] = replacementWaiting;
	};
	class Parser {
		parseFromString(html, type) {
			assert.equal(html, 'Escaped current server markup');
			assert.equal(type, 'text/html');
			return { querySelector: () => replacement };
		}
	}
	const result = run('admin-site-knowledge.js', nodes, { npcinkCloudSiteKnowledge: config }, [
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
	assert.equal(replaced, true);
	assert.equal(connection.innerHTML, '');
	assert.equal(newRetry.hidden, true);
	assert.equal(replacementReadStatus.hidden, true);
	assert.equal(replacementReadStatus.textContent, '');
	assert.equal(replacementWaiting.hidden, true);
	assert.equal(newRetry.disabled, false);
	assert.equal(typeof newRetry.listeners.click, 'function');
	assert.equal(result.calls(), 2);
	assert.match(result.requests[1].options.body, /action=read/);
});
