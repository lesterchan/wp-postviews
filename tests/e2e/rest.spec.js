/**
 * The REST route.
 *
 * One route, under `postviews/v1`, and the absence of a second one is as much
 * the design as the route is: reading a count is already answered by a
 * read-only `views` field on the core post resource, so a client asking
 * /wp/v2/posts/<id> gets the post and its count together.
 *
 * The PHPUnit suite already dispatches this through WP_REST_Server, so what is
 * worth testing here is what only the HTTP layer decides: that a visitor who is
 * not logged in can report a view -- which is who reads a cached page, and who
 * a dispatcher test cannot impersonate -- and that the AJAX endpoint it sits
 * beside still answers.
 *
 * Every test that mints a nonce lives in the logged-out block below, and that
 * is not tidiness. `wp eval` runs with nobody logged in, so the nonce it makes
 * belongs to user 0; the `request` fixture inherits `use.storageState` from
 * playwright.config.js and is the administrator unless a block clears it. Mint
 * as one user and verify as another and every such test fails 403, which reads
 * as a broken endpoint rather than a broken fixture.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { setOptions, setViews, uniqueTitle, views, wpEval } = require( './helpers.js' );

/** Every route lives under this namespace. */
const NAMESPACE = '/postviews/v1';

/**
 * The site-wide nonce the counting script is given.
 *
 * @return {string} The nonce, for user 0.
 */
function countingNonce() {
	return wpEval( `echo '<<<' . wp_create_nonce( 'wp_postviews_nonce' ) . '>>>';` );
}

test.describe( 'The REST route', () => {
	let postId;

	test.beforeEach( async ( { requestUtils } ) => {
		const post = await requestUtils.createPost( {
			title: uniqueTitle( 'REST view' ),
			content: 'Body.',
			status: 'publish',
		} );

		postId = post.id;
		setViews( postId, 0 );

		// The route counts only where the site defers counting, which means a
		// page cache plus the setting. WP_CACHE is on in the test container's
		// wp-config, so the setting is the half a test can arrange.
		setOptions( { use_ajax: 1 } );
	} );

	test( 'the fixture really is the namespace this plugin registered', async ( {
		requestUtils,
	} ) => {
		const index = await requestUtils.rest( { path: '/' } );

		expect( index.namespaces ).toContain( 'postviews/v1' );
		expect( index.namespaces ).not.toContain( 'wp-postviews/v1' );
	} );

	test( 'reading a count is the core post resource, not a route here', async ( {
		requestUtils,
	} ) => {
		setViews( postId, 12 );

		const post = await requestUtils.rest( { path: `/wp/v2/posts/${ postId }` } );

		// The whole reason this namespace carries no read route: the count
		// arrives with the post, in one request, already schema'd.
		expect( post.views ).toBe( 12 );
	} );

	// A cached page is served to whoever asks, and the visitor reporting the
	// view is usually logged out -- so that is the path worth exercising over
	// HTTP, and it is also the only one where a user-0 nonce verifies.
	test.describe( 'as a visitor who is not logged in', () => {
		test.use( { storageState: { cookies: [], origins: [] } } );

		test( 'the fixture really is logged out', async ( { request } ) => {
			const me = await request.get( '/index.php?rest_route=/wp/v2/users/me' );

			expect( me.status() ).toBe( 401 );
		} );

		test( 'a logged-out visitor can report a view', async ( { request } ) => {
			const nonce = countingNonce();

			expect( nonce ).not.toBe( '' );

			const response = await request.post(
				`/index.php?rest_route=${ NAMESPACE }/post/${ postId }/view`,
				{ form: { nonce } },
			);

			expect( response.status() ).toBe( 200 );
			expect( views( postId ) ).toBe( 1 );
		} );

		test( 'a view without the nonce is refused and counts nothing', async ( {
			request,
		} ) => {
			const response = await request.post(
				`/index.php?rest_route=${ NAMESPACE }/post/${ postId }/view`,
				{ form: { nonce: 'not-the-nonce' } },
			);

			expect( response.status() ).toBe( 403 );
			expect( views( postId ) ).toBe( 0 );
		} );

		test( 'a site counting during the render refuses the route', async ( {
			request,
		} ) => {
			// Without this guard the route would double every view on every site
			// without a page cache, because wp_head has already counted the visit.
			setOptions( { use_ajax: 0 } );

			const response = await request.post(
				`/index.php?rest_route=${ NAMESPACE }/post/${ postId }/view`,
				{ form: { nonce: countingNonce() } },
			);

			expect( response.status() ).toBe( 400 );
			expect( views( postId ) ).toBe( 0 );
		} );

		test( 'the AJAX endpoint it sits beside still answers', async ( { request } ) => {
			// Kept on purpose: the counting script every cached site already
			// serves posts to it. If this ever 404s, the route above stopped
			// being an addition and became a replacement.
			const response = await request.post( '/wp-admin/admin-ajax.php', {
				form: {
					action: 'wp_postviews',
					postviews_id: String( postId ),
					nonce: countingNonce(),
				},
			} );

			expect( response.status() ).toBe( 200 );
		} );
	} );
} );
