( function( window ) {
	var config = window.NpcinkCloudAiPluginLocalization || {};
	var wp = window.wp || {};

	if ( ! config.localeData || ! wp.i18n || ! wp.i18n.setLocaleData ) {
		return;
	}

	// Stand down per string: entries an official ai-domain language pack has
	// already registered win, and this shim fills only the remaining gaps.
	const existing = ( wp.i18n.getLocaleData && wp.i18n.getLocaleData( 'ai' ) ) || {};
	const fallback = {};

	for ( const key of Object.keys( config.localeData ) ) {
		if ( ! existing[ key ] ) {
			fallback[ key ] = config.localeData[ key ];
		}
	}

	wp.i18n.setLocaleData( fallback, 'ai' );
	// DataViews bundled by AI uses default-domain strings. Never register the
	// full AI dictionary in that domain. PHP supplies only the reviewed keys
	// and only on the AI settings and request-log screens.
	const bundled = config.bundledUiFallback || {};
	if ( ! wp.hooks || ! wp.hooks.addFilter || ! Object.keys( bundled ).length ) {
		return;
	}
	const isDefault = ( domain ) => ! domain || domain === 'default';
	wp.hooks.addFilter( 'i18n.gettext', 'npcink/ai-bundled-ui',
		( translation, source, domain ) => {
			const supported = [ 'Sort by', 'Order', 'Density', 'Items per page', 'Properties', 'Add filter', 'No results' ].includes( source );
			return isDefault( domain ) && supported && bundled[ source ] && translation === source ?
				bundled[ source ] : translation;
		} );
	wp.hooks.addFilter( 'i18n.ngettext', 'npcink/ai-bundled-ui',
		( translation, single, plural, number, domain ) => {
			const supported = ( single === '%d Item selected' && plural === '%d Items selected' ) ||
				( single === '%d field needs attention' && plural === '%d fields need attention' );
			return isDefault( domain ) && supported && bundled[ single ] &&
				( translation === single || translation === plural ) ? bundled[ single ] : translation;
		} );
	wp.hooks.addFilter( 'i18n.gettext_with_context', 'npcink/ai-bundled-ui',
		( translation, source, context, domain ) => {
			const supported = ( [ 'Comfortable', 'Balanced', 'Compact' ].includes( source ) && context === 'Density option for DataView layout' ) ||
				( source === 'View options' && context === 'View is used as a noun' ) ||
				( source === 'Edit %s (has errors)' && context === 'field' );
			return isDefault( domain ) && supported && bundled[ source ] && translation === source ?
				bundled[ source ] : translation;
		} );

}( window ) );
