import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import vm from 'node:vm';

const asset = readFileSync(new URL('../assets/editor-error-actions.js', import.meta.url), 'utf8');
function setup(url = 'https://example.test/wp-admin/admin.php?page=npcink-cloud-addon&tab=checks&support=ai-error#npcink-cloud-copy-troubleshooting', extraRules = []) {
	let notices = [];
	let listener;
	let writes = 0;
	const opened = [];
	const config = { messages: ['legacy failure'], message: 'precise next steps', label: 'Open troubleshooting', url };
	const data = {
		select: () => ({ getNotices: () => notices }),
		dispatch: () => ({ createNotice: (status, content, options) => {
			writes++;
			notices = notices.map(n => n.id === options.id ? { ...options, status, content } : n);
			listener();
		} }),
		subscribe: fn => { listener = fn; },
	};
	vm.runInNewContext(asset, { window: { NpcinkCloudEditorErrorActions: {rules:[config, ...extraRules]}, wp: { data }, open: (...args) => opened.push(args) } });
	return { config, opened, set: list => { notices = list; listener(); }, read: () => notices, writes: () => writes, notify: () => listener() };
}

test('adds one native action to exact Cloud failures, preserves dismiss/actions and opens only on click', () => {
	const app = setup();
	const dismiss = () => {};
	app.set([{ id: 'ai-excerpt', status: 'error', content: 'legacy failure', isDismissible: true, onDismiss: dismiss, actions: [{label:'Existing action',url:'/existing'}] }]);
	const notice = app.read()[0];
	assert.equal(notice.content, app.config.message);
	assert.equal(notice.id, 'ai-excerpt');
	assert.equal(notice.onDismiss, dismiss);
	assert.equal(notice.isDismissible, true);
	assert.equal(notice.speak, false);
	assert.equal(notice.actions.length, 2);
	assert.equal(app.opened.length, 0);
	let prevented = false;
	notice.actions[1].onClick({preventDefault: () => { prevented = true; }});
	assert.equal(prevented, true);
	assert.deepEqual(app.opened[0], [app.config.url, '_blank', 'noopener,noreferrer']);
	app.notify();
	assert.equal(app.writes(), 1);
	app.set([{id:'ai-excerpt',status:'error',content:app.config.message,actions:[]}]);
	assert.equal(app.read()[0].actions.length, 1, 'new failure with reused id gets its action');
});

test('leaves unrelated errors, successes and notice removal alone', () => {
	const app = setup();
	app.set([{id:'other',status:'error',content:'another plugin error'}, {id:'success',status:'success',content:'legacy failure'}]);
	assert.equal(app.writes(), 0);
	app.set([]);
	assert.equal(app.read().length, 0);
});

test('users without settings permission get administrator guidance without a dead link', () => {
	const app = setup('');
	app.set([{id:'ai',status:'error',content:'legacy failure',actions:[]}]);
	assert.equal(app.read()[0].content, app.config.message);
	assert.equal(app.read()[0].actions.length, 0);
	app.notify();
	assert.equal(app.writes(), 1);
});

test('simultaneous connection, billing and diagnostic errors retain reasons and distinct destinations', () => {
	const app = setup(undefined, [
		{messages:['unauthorized'],message:'unauthorized',label:'Check connection',url:'/checks#actions'},
		{messages:['credits used up'],message:'credits used up',label:'Plans and credits',url:'/portal/billing'},
	]);
	app.set([
		{id:'auth',status:'error',content:'unauthorized',actions:[]},
		{id:'credits',status:'error',content:'credits used up',actions:[]},
		{id:'generic',status:'error',content:'legacy failure',actions:[]},
		{id:'running',status:'error',content:'still processing; wait',actions:[]},
	]);
	assert.deepEqual(app.read().map(n=>n.content),['unauthorized','credits used up',app.config.message,'still processing; wait']);
	assert.deepEqual(app.read().map(n=>Array.from(n.actions,a=>a.url)),[['/checks#actions'],['/portal/billing'],[app.config.url],[]]);
	app.notify();
	assert.equal(app.writes(), 3);
	app.read()[1].actions[0].onClick({preventDefault(){}});
	assert.deepEqual(app.opened[0],['/portal/billing','_blank','noopener,noreferrer']);
});

test('permission handoff is idempotent for each family and retains unrelated existing actions', () => {
	const message = 'unauthorized; ask administrator';
	const app = setup('', [{messages:['unauthorized'],message,label:'',url:''}]);
	app.set([{id:'auth',status:'error',content:'unauthorized',actions:[{label:'Other help',url:'/other'}]}]);
	assert.equal(app.read()[0].content,message);
	assert.equal(app.read()[0].actions.length,1);
	assert.equal(app.read()[0].actions[0].url,'/other');
	assert.equal(app.read()[0].actions[0].label,'Other help');
	app.notify();
	assert.equal(app.writes(),1);
});
