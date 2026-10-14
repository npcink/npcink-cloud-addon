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
