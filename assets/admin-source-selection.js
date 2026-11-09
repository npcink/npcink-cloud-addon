( function () {
	'use strict';

	document.querySelectorAll( '[data-npcink-source-selection]' ).forEach( ( form ) => {
		const feedback = form.querySelector( '[data-npcink-source-feedback]' );
		const confirmed = form.querySelector( '[data-npcink-source-confirmed]' );
		form.addEventListener( 'change', () => {
			if ( feedback ) { feedback.hidden = true; }
		} );
		form.addEventListener( 'submit', ( event ) => {
			const count = form.querySelectorAll( 'input[name="site_knowledge_sources[]"]:checked:not(:disabled)' ).length;
			if ( confirmed ) { confirmed.value = ''; }
			if ( ! count ) {
				event.preventDefault();
				if ( feedback ) {
					feedback.textContent = form.dataset.selectionRequired;
					feedback.hidden = false;
				}
				return;
			}
			if ( confirmed ) {
				if ( ! window.confirm( form.dataset.confirmMessage.replace( '%d', String( count ) ) ) ) {
					event.preventDefault();
					return;
				}
				confirmed.value = '1';
			}
		} );
	} );
}() );
