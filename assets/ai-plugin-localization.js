( function( window ) {
	var config = window.NpcinkCloudAiPluginLocalization || {};
	var wp = window.wp || {};

	if ( ! config.localeData || ! wp.i18n || ! wp.i18n.setLocaleData ) {
		return;
	}

	var applyLocaleData = function() {
		wp.i18n.setLocaleData( config.localeData, 'ai' );
	};

	// The official AI bundle may register its own locale data after this shim
	// has been enqueued. Reapply the same static compatibility map after the
	// current script queue so fixed notices keep their zh_CN translations while
	// dynamic Ability metadata remains untouched.
	applyLocaleData();
	if ( window.setTimeout ) {
		window.setTimeout( applyLocaleData, 0 );
	}
}( window ) );
