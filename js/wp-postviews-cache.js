/**
 * Records a view over admin-ajax.php.
 *
 * Enqueued only under a page cache, where counting in wp_head would record the
 * request that generated the cached page and nothing after. On success it
 * dispatches `postviews:updated` on document with the new count, which is what
 * the readme's cache recipe listens for.
 */
( function() {
	'use strict';

	fetch( wpPostViewsL10n.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Cache-Control': 'no-cache',
		},
		body: new URLSearchParams( {
			action: 'wp_postviews',
			_ajax_nonce: wpPostViewsL10n.nonce,
			postviews_id: wpPostViewsL10n.postId,
		} ),
	} )
		.then( function( response ) {
			return response.json();
		} )
		.then( function( data ) {
			if ( ! data || ! data.success || ! data.data ) {
				return;
			}

			document.dispatchEvent(
				new CustomEvent( 'postviews:updated', {
					detail: {
						views: data.data.views,
						postId: wpPostViewsL10n.postId,
					},
				} ),
			);
		} )
		.catch( function( error ) {
			// eslint-disable-next-line no-console
			console.log( 'WP-PostViews', error );
		} );
}() );
