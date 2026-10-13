import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { runInNewContext } from 'node:vm';

const source = readFileSync( new URL( '../assets/admin-entitlement.js', import.meta.url ), 'utf8' );

async function refreshFixture( { failure = false, deliveryFailure = false, state = 'fresh', missingLabel = false } = {} ) {
	const node = ( dataset = {}, classes = [] ) => {
		const classNames = new Set( classes );
		return {
			dataset, textContent: 'Not recently checked', hidden: false, disabled: false,
			classes: classNames,
			classList: {
				remove( ...values ) { values.forEach( value => classNames.delete( value ) ); },
				add( ...values ) { values.forEach( value => classNames.add( value ) ); },
			},
			setAttribute() {}, remove() { this.removed = true; },
		};
	};
	const summary = node();
	summary.textContent = 'Pro · cached';
	const retry = node();
	retry.addEventListener = ( event, handler ) => { retry.click = handler; };
	const warning = node( { npcinkEntitlementHealth: 'summary' } );
	const badge = node( { npcinkEntitlementHealth: 'badge' }, [ 'npcink-cloud-badge--warning' ] );
	const label = node( { npcinkEntitlementHealth: 'label', npcinkEntitlementFreshLabel: '最近可连接' }, [ 'npcink-cloud-overview-service--warning', 'fixture-modifier' ] );
	if ( missingLabel ) { delete label.dataset.npcinkEntitlementFreshLabel; }
	const container = node( { npcinkEntitlementState: 'stale' } );
	const selectors = { '[data-npcink-entitlement-summary]': summary, '[data-npcink-entitlement-retry]': retry };
	container.querySelector = ( selector ) => selectors[ selector ] || null;
	const healthNodes = [ warning, badge, label ];
	if ( deliveryFailure ) {
		healthNodes.forEach( item => { delete item.dataset.npcinkEntitlementHealth; } );
		label.textContent = 'Needs attention';
	}
	let requests = 0;
	runInNewContext( source, {
		URLSearchParams,
		document: {
			querySelector: () => container,
			querySelectorAll: ( selector ) => selector === '[data-npcink-entitlement-health]' ? healthNodes.filter( item => item.dataset.npcinkEntitlementHealth ) : [],
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
	assert.equal( f.label.classes.has( 'npcink-cloud-overview-service--ok' ), true );
	assert.equal( f.label.classes.has( 'npcink-cloud-overview-service--warning' ), false );
	assert.equal( f.label.classes.has( 'fixture-modifier' ), true );
	assert.equal( f.container.dataset.npcinkEntitlementState, 'fresh' );
	assert.equal( f.retry.hidden, true );
} );

for ( const scenario of [ { failure: true }, { state: 'stale' }, { state: null }, { deliveryFailure: true } ] ) {
	test( `refresh preserves warnings for ${ JSON.stringify( scenario ) }`, async () => {
		const f = await refreshFixture( scenario );
		assert.equal( f.warning.removed, undefined );
		assert.equal( f.badge.classes.has( 'npcink-cloud-badge--warning' ), true );
		assert.equal( f.label.textContent, scenario.deliveryFailure ? 'Needs attention' : 'Not recently checked' );
		if ( null === scenario.state ) {
			assert.equal( f.container.dataset.npcinkEntitlementState, 'stale' );
		}
		if ( scenario.failure ) {
			assert.equal( f.retry.hidden, false );
			assert.equal( f.summary.textContent, 'Pro · cached · 更新失败' );
		}
	} );
}

test( 'missing localized healthy label never displays undefined', async () => {
	const f = await refreshFixture( { missingLabel: true } );
	assert.equal( f.label.textContent, '' );
} );
