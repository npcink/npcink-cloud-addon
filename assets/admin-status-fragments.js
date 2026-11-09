( function () {
	'use strict';

	window.npcinkCloudApplyStatusFragments = ( fragments ) => {
		fragments = fragments || {};
		const connection = document.querySelector( '[data-npcink-connection-summary]' );
		if ( connection && typeof fragments.connection === 'string' ) {
			// Server-escaped markup from the nonce-protected status read; it is the
			// same renderer used for the initial page load. Never assign untrusted HTML here.
			connection.innerHTML = fragments.connection;
		}
		const usageFragments = {
			credit_attention: '[data-npcink-credit-attention]',
			capability_attention: '[data-npcink-capability-attention]',
			account_usage: '[data-npcink-account-usage]',
			account_cycle: '[data-npcink-account-cycle]',
			image_capacity: '[data-npcink-image-capacity-value]',
		};
		Object.entries( usageFragments ).forEach( ( [ key, selector ] ) => {
			const target = document.querySelector( selector );
			if ( target && typeof fragments[ key ] === 'string' ) {
				target.innerHTML = fragments[ key ];
			}
		} );
		const ready = document.querySelector( '[data-npcink-overview-ready]' );
		if ( ready && fragments.service && 'object' === typeof fragments.service ) {
			const hasAttention = [ 'credit_attention', 'capability_attention' ].some( ( key ) => {
				const target = document.querySelector( usageFragments[ key ] );
				return !!( target && target.innerHTML.trim() );
			} );
			ready.hidden = 'ok' !== fragments.service.severity || hasAttention;
		}
		const service = document.querySelector( '.npcink-cloud-overview-service' );
		const badge = document.querySelector( '[data-npcink-service-connection-badge]' );
		const severities = [ 'ok', 'warning', 'pending', 'inactive' ];
		if ( service && fragments.service && 'object' === typeof fragments.service ) {
			const severity = severities.includes( fragments.service.severity ) ? fragments.service.severity : 'pending';
			service.textContent = fragments.service.label || '';
			service.classList.remove( ...severities.map( ( value ) => 'npcink-cloud-overview-service--' + value ) );
			service.classList.add( 'npcink-cloud-overview-service--' + severity );
			if ( badge ) {
				badge.classList.toggle( 'npcink-cloud-badge--warning', 'warning' === severity );
				badge.classList.toggle( 'npcink-cloud-badge--ok', 'warning' !== severity );
			}
		}
	};
}() );
