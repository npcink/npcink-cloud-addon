( function () {
	'use strict';
	const config = window.NpcinkCloudEditorErrorActions;
	const data = window.wp && window.wp.data;
	if ( ! config || ! data || ! Array.isArray( config.rules ) ) {
		return;
	}
	const rules = config.rules.map( ( rule ) => ( { ...rule, messages: new Set( [ ...rule.messages, rule.message ] ) } ) );
	let updating = false;
	const enhance = () => {
		if ( updating ) {
			return;
		}
		const notices = data.select( 'core/notices' ).getNotices();
		const pending = notices.map( ( notice ) => ( { notice, rule: rules.find( ( rule ) => rule.messages.has( notice.content ) ) } ) )
			.filter( ( { notice, rule } ) => rule && notice.status === 'error' &&
				! ( notice.actions || [] ).some( ( action ) => rule.url && action.url === rule.url ) &&
				( notice.content !== rule.message || !! rule.url ) );
		if ( ! pending.length ) {
			return;
		}
		updating = true;
		try {
			pending.forEach( ( { notice, rule } ) => {
				const actions = [ ...( notice.actions || [] ) ];
				if ( rule.url ) {
					actions.push( {
						label: rule.label,
						url: rule.url,
						onClick: ( event ) => {
							event.preventDefault();
							window.open( rule.url, '_blank', 'noopener,noreferrer' );
						},
					} );
				}
				// Retain identity, existing actions and dismiss behavior. Do not
				// repeat the screen-reader announcement when only adding navigation.
				data.dispatch( 'core/notices' ).createNotice( notice.status, rule.message, {
					...notice, actions, speak: false,
				} );
			} );
		} finally {
			updating = false;
		}
	};
	data.subscribe( enhance, 'core/notices' );
	enhance();
}() );
