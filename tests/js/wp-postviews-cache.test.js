/**
 * Tests for the cached-page counting script.
 *
 * It posts the view as soon as it evaluates and attaches nothing, so each test
 * stands up its own fetch stub and then loads the script.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { cacheL10n, loadScript } from './helper-dom.js';

describe( 'wp-postviews cached page counter', () => {
	beforeEach( () => {
		window.wpPostViewsL10n = cacheL10n();
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	/**
	 * Stub fetch with a resolved JSON body and load the script.
	 *
	 * @param {Object} body What admin-ajax.php answered with.
	 * @return {Promise} Resolves once the script's promise chain has settled.
	 */
	function run( body ) {
		window.fetch = vi.fn( () =>
			Promise.resolve( { json: () => Promise.resolve( body ) } ),
		);

		loadScript( 'js/wp-postviews-cache.js' );

		return Promise.resolve();
	}

	/**
	 * The body of the one request the script made, parsed.
	 *
	 * @return {URLSearchParams} Posted fields.
	 */
	function postedFields() {
		return new URLSearchParams( window.fetch.mock.calls[ 0 ][ 1 ].body.toString() );
	}

	it( 'posts to the localised admin-ajax URL', async () => {
		await run( { success: true, data: { views: 43 } } );

		expect( window.fetch ).toHaveBeenCalledTimes( 1 );
		expect( window.fetch.mock.calls[ 0 ][ 0 ] ).toBe(
			'https://example.com/wp-admin/admin-ajax.php',
		);
	} );

	it( 'sends the field names the endpoint reads', async () => {
		await run( { success: true, data: { views: 43 } } );

		const fields = postedFields();

		// Rename any one of these and counting stops, silently, on every cached
		// page. The action carries the plugin prefix rather than a bare
		// "postviews", which any plugin could have claimed.
		expect( fields.get( 'action' ) ).toBe( 'wp_postviews' );
		expect( fields.get( 'nonce' ) ).toBe( 'abc123' );
		expect( fields.get( 'postviews_id' ) ).toBe( '42' );
	} );

	it( 'posts with the session cookie and without caching the response', async () => {
		await run( { success: true, data: { views: 43 } } );

		const options = window.fetch.mock.calls[ 0 ][ 1 ];

		expect( options.method ).toBe( 'POST' );
		expect( options.credentials ).toBe( 'same-origin' );
		expect( options.headers[ 'Cache-Control' ] ).toBe( 'no-cache' );
	} );

	it( 'announces the new count so a theme can write it into the page', async () => {
		const seen = [];
		document.addEventListener( 'postviews:updated', ( event ) =>
			seen.push( event.detail ),
		);

		await run( { success: true, data: { views: 43 } } );
		await vi.waitFor( () => expect( seen ).toHaveLength( 1 ) );

		expect( seen[ 0 ] ).toEqual( { views: 43, postId: 42 } );
	} );

	it( 'announces nothing when the endpoint reports failure', async () => {
		const seen = [];
		document.addEventListener( 'postviews:updated', () => seen.push( 1 ) );

		await run( { success: false } );
		await Promise.resolve();

		expect( seen ).toHaveLength( 0 );
	} );

	it( 'announces nothing when the response carries no data', async () => {
		const seen = [];
		document.addEventListener( 'postviews:updated', () => seen.push( 1 ) );

		await run( { success: true } );
		await Promise.resolve();

		expect( seen ).toHaveLength( 0 );
	} );

	it( 'survives an empty response body', async () => {
		const seen = [];
		document.addEventListener( 'postviews:updated', () => seen.push( 1 ) );

		await run( null );
		await Promise.resolve();

		expect( seen ).toHaveLength( 0 );
	} );

	it( 'logs rather than throwing when the request fails outright', async () => {
		const logged = vi.spyOn( console, 'log' ).mockImplementation( () => {} );

		window.fetch = vi.fn( () => Promise.reject( new Error( 'offline' ) ) );

		loadScript( 'js/wp-postviews-cache.js' );

		await vi.waitFor( () => expect( logged ).toHaveBeenCalled() );

		expect( logged.mock.calls[ 0 ][ 0 ] ).toBe( 'WP-PostViews' );
	} );
} );
