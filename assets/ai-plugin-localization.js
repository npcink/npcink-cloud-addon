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
}( window ) );
