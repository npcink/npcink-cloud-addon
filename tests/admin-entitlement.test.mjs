import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync( new URL( '../assets/admin-entitlement.js', import.meta.url ), 'utf8' );
const fragmentsSource = readFileSync( new URL( '../assets/admin-status-fragments.js', import.meta.url ), 'utf8' );

async function refreshFixture( { failure = false, deliveryFailure = false, state = 'fresh', missingFragments = false, severity = null, renderFailure = false } = {} ) {
	const node = ( dataset = {}, classes = [] ) => {
		const classNames = new Set( classes );
		const element = {
			dataset, textContent: 'Not recently checked', hidden: false, disabled: false,
			classes: classNames, className: '',
			classList: {
				remove( ...values ) { values.forEach( value => classNames.delete( value ) ); },
				add( ...values ) { values.forEach( value => classNames.add( value ) ); },
				toggle( value, enabled ) { enabled ? classNames.add( value ) : classNames.delete( value ); },
			},
			setAttribute() {}, remove() { this.removed = true; },
		};
		element.classList.remove = ( ...values ) => {
			values.forEach( value => classNames.delete( value ) );
			element.className = [ 'npcink-cloud-overview-service', ...classNames ].join( ' ' );
		};
		element.classList.add = ( ...values ) => {
			values.forEach( value => classNames.add( value ) );
			element.className = [ 'npcink-cloud-overview-service', ...classNames ].join( ' ' );
		};
		element.classList.toggle = ( value, enabled ) => {
			enabled ? classNames.add( value ) : classNames.delete( value );
		};
		return element;
	};
	const summary = node();
	summary.textContent = 'Pro · cached';
	const retry = node();
	retry.addEventListener = ( event, handler ) => { retry.click = handler; };
	const warning = node();
	warning.innerHTML = 'Cached warning';
	const badge = node( {}, [ 'npcink-cloud-badge--warning' ] );
	const label = node();
	label.className = 'npcink-cloud-overview-service npcink-cloud-overview-service--warning';
	label.textContent = 'Before refresh';
	const container = node( { npcinkEntitlementState: 'stale' } );
	const selectors = { '[data-npcink-entitlement-summary]': summary, '[data-npcink-entitlement-retry]': retry };
	container.querySelector = ( selector ) => selectors[ selector ] || null;
	const documentNodes = {
		'[data-npcink-entitlement-state]': container,
		'[data-npcink-connection-summary]': warning,
		'[data-npcink-service-connection-badge]': badge,
		'.npcink-cloud-overview-service': label,
	};
	let requests = 0;
	const context = {
		URLSearchParams,
		document: {
			querySelector: ( selector ) => documentNodes[ selector ] || null,
			querySelectorAll: () => [],
		},
		window: {
			npcinkCloudEntitlement: { ajaxUrl: '/ajax', action: 'refresh', nonce: 'fixture', updateFailedLabel: '更新失败' },
			fetch: async () => {
				requests++;
				if ( failure ) { throw new Error( 'Network error' ); }
				let fragmentSeverity = 'warning';
				let fragmentLabel = 'Not recently checked';
				let fragmentConnection = 'Server warning';
				if ( deliveryFailure ) {
					fragmentLabel = 'Needs attention';
				}
				if ( 'fresh' === state && ! deliveryFailure ) {
					fragmentSeverity = 'ok';
					fragmentLabel = '最近可连接';
					fragmentConnection = '';
				}
				if ( severity ) { fragmentSeverity = severity; }
				const statusFragments = missingFragments ? undefined : {
					connection: fragmentConnection,
					service: { severity: fragmentSeverity, label: fragmentLabel },
				};
				return { ok: true, json: async () => ( { success: true, data: { label: 'Pro · available', state, status_fragments: statusFragments } } ) };
			},
		},
	};
	runInNewContext( fragmentsSource, context );
	if ( renderFailure ) { context.window.npcinkCloudApplyStatusFragments = () => { throw new Error( 'render failed' ); }; }
	runInNewContext( source, context );
	await new Promise( resolve => setImmediate( resolve ) );
	return { warning, badge, label, summary, retry, container, requests };
}

test( 'fresh signed read clears entitlement warning and updates localized service badges', async () => {
	const f = await refreshFixture();
	assert.equal( f.requests, 1 );
	assert.equal( f.warning.innerHTML, '' );
	assert.equal( f.badge.classes.has( 'npcink-cloud-badge--warning' ), false );
	assert.equal( f.badge.classes.has( 'npcink-cloud-badge--ok' ), true );
	assert.equal( f.label.textContent, '最近可连接' );
	assert.equal( f.label.className, 'npcink-cloud-overview-service npcink-cloud-overview-service--ok' );
	assert.equal( f.container.dataset.npcinkEntitlementState, 'fresh' );
	assert.equal( f.retry.hidden, true );
} );

for ( const scenario of [ { failure: true }, { state: 'stale' }, { state: null }, { deliveryFailure: true } ] ) {
	test( `refresh preserves warnings for ${ JSON.stringify( scenario ) }`, async () => {
		const f = await refreshFixture( scenario );
		assert.equal( f.warning.innerHTML, scenario.failure ? 'Cached warning' : 'Server warning' );
		assert.equal( f.badge.classes.has( 'npcink-cloud-badge--warning' ), true );
		let expectedLabel = 'Not recently checked';
		if ( scenario.failure ) { expectedLabel = 'Before refresh'; }
		if ( scenario.deliveryFailure ) { expectedLabel = 'Needs attention'; }
		assert.equal( f.label.textContent, expectedLabel );
		if ( null === scenario.state ) {
			assert.equal( f.container.dataset.npcinkEntitlementState, 'stale' );
		}
		if ( scenario.failure ) {
			assert.equal( f.retry.hidden, false );
			assert.equal( f.summary.textContent, 'Pro · cached · 更新失败' );
		}
	} );
}

test( 'missing status fragments preserve the previous warning and service label', async () => {
	const f = await refreshFixture( { missingFragments: true } );
	assert.equal( f.label.textContent, 'Before refresh' );
	assert.equal( f.warning.innerHTML, 'Cached warning' );
	assert.equal( f.summary.textContent, 'Pro · available' );
	assert.equal( f.retry.hidden, true );
} );

test( 'unknown service severity fails closed without showing a healthy badge', async () => {
	const f = await refreshFixture( { severity: 'unexpected' } );
	assert.equal( f.label.className, 'npcink-cloud-overview-service npcink-cloud-overview-service--pending' );
	assert.equal( f.badge.classes.has( 'npcink-cloud-badge--warning' ), false );
	assert.equal( f.badge.classes.has( 'npcink-cloud-badge--ok' ), true );
} );

test( 'fragment render failure does not invalidate a successful entitlement read', async () => {
	const f = await refreshFixture( { renderFailure: true } );
	assert.equal( f.summary.textContent, 'Pro · available' );
	assert.equal( f.container.dataset.npcinkEntitlementState, 'fresh' );
	assert.equal( f.retry.hidden, true );
} );
