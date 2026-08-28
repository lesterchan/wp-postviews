/**
 * Settings > PostViews. One delegated listener for the Restore Default Template
 * buttons; the defaults arrive from wp_localize_script(), so nothing is escaped
 * into a JavaScript string literal.
 */
( function() {
	'use strict';

	document.addEventListener( 'click', function( event ) {
		const button = event.target.closest( '[data-postviews-reset]' );

		if ( ! button ) {
			return;
		}

		const key = button.dataset.postviewsReset;
		const field = document.getElementById( button.dataset.postviewsTarget );

		if (
			! field ||
			typeof wpPostViewsL10n === 'undefined' ||
			typeof wpPostViewsL10n.defaults[ key ] === 'undefined'
		) {
			return;
		}

		field.value = wpPostViewsL10n.defaults[ key ];
	} );
}() );
