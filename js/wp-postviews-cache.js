/**
 * Records a view over admin-ajax.php.
 *
 * Enqueued only when a page cache is in use, where counting during wp_head
 * would record the request that generated the cached page and nothing after.
 *
 * On success a `postviews:updated` event is dispatched on `document` carrying
 * the new count, so a theme can write it into the page. That is what the
 * LiteSpeed Cache recipe in the readme uses; previously it told people to edit
 * this file, which every plugin update then overwrote.
 */
( function() {
	fetch( wpPostViewsL10n.ajaxUrl, {
		method: 'POST',
		credentials: 'same-origin',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'Cache-Control': 'no-cache',
		},
		body: new URLSearchParams( {
			action: 'wp_postviews',
			nonce: wpPostViewsL10n.nonce,
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
