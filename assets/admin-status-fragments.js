( function () {
	'use strict';

	window.npcinkCloudApplyStatusFragments = ( fragments ) => {
		fragments = fragments || {};
		const connection = document.querySelector( '[data-npcink-connection-summary]' );
		if ( connection && typeof fragments.connection === 'string' ) {
			connection.innerHTML = fragments.connection;
		}
		const service = document.querySelector( '.npcink-cloud-overview-service' );
		const badge = document.querySelector( '[data-npcink-service-connection-badge]' );
		if ( service && fragments.service ) {
			service.textContent = fragments.service.label || '';
			service.className = 'npcink-cloud-overview-service npcink-cloud-overview-service--' + ( fragments.service.severity || 'pending' );
			if ( badge ) {
				badge.classList.toggle( 'npcink-cloud-badge--warning', fragments.service.severity === 'warning' );
				badge.classList.toggle( 'npcink-cloud-badge--ok', fragments.service.severity !== 'warning' );
			}
		}
	};
}() );
