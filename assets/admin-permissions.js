( function() {
	'use strict';

	var config = window.npcinkCloudPermissions || {};
	var forms = document.querySelectorAll( '[data-npcink-local-permission]' );

	forms.forEach( function( form ) {
		var checkbox = form.querySelector( '.npcink-ai-switch__input' );
		var hiddenValue = form.querySelector( '[data-npcink-local-permission-value]' );
		var progress = form.querySelector( '[data-npcink-local-permission-progress]' );

		if ( ! checkbox || ! hiddenValue ) {
			return;
		}

		checkbox.addEventListener( 'change', function() {
			hiddenValue.value = checkbox.checked ? '1' : '0';
			checkbox.removeAttribute( 'name' );
			checkbox.disabled = true;
			form.classList.add( 'is-saving' );
			form.setAttribute( 'aria-busy', 'true' );

			if ( progress ) {
				progress.hidden = false;
				progress.textContent = config.savingLabel || 'Saving…';
			}

			form.submit();
		} );
	} );

	var focusForm = document.querySelector( '[data-npcink-local-permission-focus]' );
	if ( focusForm ) {
		var feedback = focusForm.querySelector( '[data-npcink-local-permission-feedback]' );
		var control = focusForm.querySelector( '.npcink-ai-switch__input' );
		var focusTarget = feedback || control;

		if ( focusTarget ) {
			focusTarget.focus( { preventScroll: true } );
			focusTarget.scrollIntoView( { block: 'center', behavior: 'smooth' } );
		}
	}
}() );
