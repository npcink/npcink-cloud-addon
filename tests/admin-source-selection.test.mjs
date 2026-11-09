import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

function setup({ count = 0, exclude = true, accept = false } = {}) {
	const confirmed = exclude ? { value: '' } : null;
	const feedback = { hidden: true, textContent: '' };
	const listeners = {};
	const prompts = [];
	const form = {
		dataset: { selectionRequired: '请先选择内容。', confirmMessage: '将排除 %d 项，可恢复。' },
		querySelector: (selector) => selector === '[data-npcink-source-confirmed]' ? confirmed : feedback,
		querySelectorAll: () => Array(count).fill({}),
		addEventListener: (name, listener) => { listeners[name] = listener; },
	};
	vm.runInNewContext(readFileSync(new URL('../assets/admin-source-selection.js', import.meta.url), 'utf8'), {
		document: { querySelectorAll: () => [form] },
		window: { confirm: (message) => { prompts.push(message); return accept; } },
	});
	return { confirmed, feedback, prompts, listeners, submit() {
		let prevented = false;
		listeners.submit({ preventDefault: () => { prevented = true; } });
		return prevented;
	} };
}

test('no selection stays on the page, explains what to do and opens no confirmation', () => {
	const ui = setup();
	assert.equal(ui.submit(), true);
	assert.equal(ui.feedback.hidden, false);
	assert.equal(ui.feedback.textContent, '请先选择内容。');
	assert.equal(ui.prompts.length, 0);
	ui.listeners.change();
	assert.equal(ui.feedback.hidden, true);
});

test('canceling exclusion preserves selection and sends no confirmation', () => {
	const ui = setup({ count: 3, accept: false });
	assert.equal(ui.submit(), true);
	assert.deepEqual(ui.prompts, ['将排除 3 项，可恢复。']);
	assert.equal(ui.confirmed.value, '');
});

test('one accepted confirmation includes the exact selected count', () => {
	const ui = setup({ count: 2, accept: true });
	assert.equal(ui.submit(), false);
	assert.deepEqual(ui.prompts, ['将排除 2 项，可恢复。']);
	assert.equal(ui.confirmed.value, '1');
});

test('restore submits selected content without another confirmation', () => {
	const ui = setup({ count: 1, exclude: false });
	assert.equal(ui.submit(), false);
	assert.equal(ui.prompts.length, 0);
});
