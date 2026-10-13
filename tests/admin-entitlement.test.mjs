import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync( new URL( '../assets/admin-entitlement.js', import.meta.url ), 'utf8' );

async function refreshFixture( { failure = false, deliveryFailure = false, state = 'fresh' } = {} ) {
	const node = ( dataset = {}, classes = [] ) => ( {
		dataset, textContent: 'Not recently checked', hidden: false, disabled: false,
		classes: new Set( classes ),
		classList: {
			remove( value ) { this.owner.classes.delete( value ); },
			add( value ) { this.owner.classes.add( value ); },
		},
		setAttribute() {}, remove() { this.removed = true; },
	} );
	const summary = node();
	summary.textContent = 'Pro · cached';
	const retry = node();
	retry.addEventListener = ( event, handler ) => { retry.click = handler; };
	const warning = node( { npcinkEntitlementHealth: 'summary' } );
	const badge = node( { npcinkEntitlementHealth: 'badge' }, [ 'npcink-cloud-badge--warning' ] );
	badge.classList.owner = badge;
	const label = node( { npcinkEntitlementHealth: 'label', npcinkEntitlementFreshLabel: '最近可连接' } );
	const container = node( { npcinkEntitlementState: 'stale' } );
	container.querySelector = ( selector ) => selector.includes( '-summary' ) ? summary : selector.includes( '-retry' ) ? retry : null;
	let requests = 0;
	runInNewContext( source, {
		URLSearchParams,
		document: {
			querySelector: () => container,
			querySelectorAll: ( selector ) => selector === '[data-npcink-entitlement-health]' && ! deliveryFailure ? [ warning, badge, label ] : [],
		},
		window: {
			npcinkCloudEntitlement: { ajaxUrl: '/ajax', action: 'refresh', nonce: 'fixture', updateFailedLabel: '更新失败' },
			fetch: async () => {
				requests++;
				if ( failure ) { throw new Error( 'Network error' ); }
				return { ok: true, json: async () => ( { success: true, data: { label: 'Pro · available', state } } ) };
			},
		},
	} );
	await new Promise( resolve => setImmediate( resolve ) );
	return { warning, badge, label, summary, retry, container, requests };
}

test( 'fresh signed read clears entitlement warning and updates localized service badges', async () => {
	const f = await refreshFixture();
	assert.equal( f.requests, 1 );
	assert.equal( f.warning.removed, true );
	assert.equal( f.badge.classes.has( 'npcink-cloud-badge--warning' ), false );
	assert.equal( f.badge.classes.has( 'npcink-cloud-badge--ok' ), true );
	assert.equal( f.label.textContent, '最近可连接' );
	assert.equal( f.label.className, 'npcink-cloud-overview-service npcink-cloud-overview-service--ok' );
	assert.equal( f.container.dataset.npcinkEntitlementState, 'fresh' );
	assert.equal( f.retry.hidden, true );
} );

for ( const scenario of [ { failure: true }, { state: 'stale' }, { deliveryFailure: true } ] ) {
	test( `refresh preserves warnings for ${ JSON.stringify( scenario ) }`, async () => {
		const f = await refreshFixture( scenario );
		assert.equal( f.warning.removed, undefined );
		assert.equal( f.badge.classes.has( 'npcink-cloud-badge--warning' ), true );
		assert.equal( f.label.textContent, 'Not recently checked' );
		if ( scenario.failure ) {
			assert.equal( f.retry.hidden, false );
			assert.equal( f.summary.textContent, 'Pro · cached · 更新失败' );
		}
	} );
}
