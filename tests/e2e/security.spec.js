/**
 * The stored XSS regressions, in a real browser.
 *
 * Two kinds of stored value reach a page here, and they are defended
 * differently, so they are tested differently.
 *
 * The two templates are HTML by design -- a site owner is meant to put markup
 * in them -- and the defence is wp_kses_post() on the way in. So the tests for
 * those drive the settings form with the payloads in it and then check the
 * front end, which is the path a real attack would have to take. wp_kses_post
 * legitimately keeps a bare <img>; what it must not keep is anything that runs,
 * so the assertion is `img[onerror]` count 0 and the sentinel undefined, not
 * "no image".
 *
 * A post title is the other kind: it is not the plugin's own value, it arrives
 * through %POST_TITLE% in the most viewed template, and the plugin's own
 * escaping for it is snippet_text()'s htmlentities(). Those payloads go into
 * the row unsanitised -- the row a compromised install already has -- because
 * sanitising on the way in is the assumption under test rather than a step to
 * reproduce.
 *
 * The assertion is the same everywhere and it has two halves: the sentinel the
 * payload would set must never become defined, and the payload's text must
 * survive on the page. Escaping that ate the value entirely passes the first
 * half and is its own bug.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	POSTS_URL,
	asGuest,
	clearAllViews,
	installProbe,
	openSettings,
	option,
	removeProbe,
	resetOptions,
	saveSettings,
	setOptions,
	setViews,
	uniqueTitle,
	wpEval,
} = require( './helpers.js' );

const SCRIPT_PAYLOAD = '<script>window.__pwned = 1;</script>';
const IMG_PAYLOAD = '<img src=x onerror="window.__pwned = 1">';
const ATTR_PAYLOAD = '" onmouseover="window.__pwned = 1';

/**
 * Whether any payload managed to run.
 *
 * @param {import('@playwright/test').Page} target Page to ask.
 * @return {Promise<boolean>} True if the sentinel was set.
 */
function pwned( target ) {
	return target.evaluate( () => window.__pwned === 1 );
}

/**
 * Rewrite a post's title straight in the posts table.
 *
 * Not through REST: the whole point is a title that never met a sanitiser, and
 * wp_insert_post() runs one for any user without unfiltered_html.
 *
 * @param {number} postId Post to rewrite.
 * @param {string} title  The title to store, byte for byte.
 * @return {void}
 */
function setRawTitle( postId, title ) {
	const encoded = Buffer.from( title, 'utf8' ).toString( 'base64' );

	wpEval(
		`global $wpdb;
		$wpdb->update(
			$wpdb->posts,
			array( 'post_title' => base64_decode( '${ encoded }' ) ),
			array( 'ID' => ${ postId } ),
			array( '%s' ),
			array( '%d' )
		);
		clean_post_cache( ${ postId } );
		echo '<<<done>>>';`,
	);
}

test.describe( 'Hostile values in the templates', () => {
	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
		installProbe();
	} );

	test.afterAll( async () => {
		removeProbe();
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
	} );

	test.afterEach( async () => {
		resetOptions();
	} );

	test( 'the fixture really is a template the settings screen has sanitised', async ( {
		page,
	} ) => {
		// Everything in this describe leans on wp_kses_post() having run on the
		// way in. If the sanitiser were ever dropped from the setting the
		// stored value would come back as typed, and the front-end tests below
		// would be asserting about a defence that was not there.
		await openSettings( page );
		await page.locator( '#views-template-template' ).fill( `%VIEW_COUNT% ${ IMG_PAYLOAD }` );
		await saveSettings( page );

		expect( option( 'template' ) ).toContain( '<img' );
		expect( option( 'template' ) ).not.toContain( 'onerror' );
	} );

	test( 'a script tag typed into the Views Template never reaches a visitor', async ( {
		page,
		requestUtils,
	} ) => {
		await openSettings( page );
		await page
			.locator( '#views-template-template' )
			.fill( `%VIEW_COUNT% views ${ SCRIPT_PAYLOAD }` );
		await saveSettings( page );

		const post = await requestUtils.createPost( {
			title: uniqueTitle( 'Hostile template post' ),
			content: 'Body.',
			status: 'publish',
		} );
		setViews( post.id, 5 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			await expect( guest.locator( '#pv-views' ) ).toContainText( '5 views' );
			expect( await pwned( guest ) ).toBe( false );
			await expect( guest.locator( '#pv-views script' ) ).toHaveCount( 0 );
			// As text, not merely absent: a sanitiser that ate the whole
			// template would pass the check above and leave the site with no
			// view counts at all.
			await expect( guest.locator( '#pv-views' ) ).toContainText( 'window.__pwned' );
		} );
	} );

	test( 'an onerror image typed into the Views Template loses its handler and keeps its image', async ( {
		page,
		requestUtils,
	} ) => {
		await openSettings( page );
		await page.locator( '#views-template-template' ).fill( `%VIEW_COUNT% ${ IMG_PAYLOAD }` );
		await saveSettings( page );

		const post = await requestUtils.createPost( {
			title: uniqueTitle( 'Hostile image post' ),
			content: 'Body.',
			status: 'publish',
		} );
		setViews( post.id, 6 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			// The image itself is legitimate -- wp_kses_post allows one, and a
			// template is meant to hold markup. The property that matters is
			// that nothing runs.
			expect( await pwned( guest ) ).toBe( false );
			await expect( guest.locator( '#pv-views img[onerror]' ) ).toHaveCount( 0 );
			await expect( guest.locator( '#pv-views img' ) ).toHaveCount( 1 );
		} );
	} );

	test( 'an attribute breakout in the Most Viewed Template does not become a handler', async ( {
		page,
		requestUtils,
	} ) => {
		await openSettings( page );
		await page
			.locator( '#views-template-most_viewed_template' )
			.fill( `<li><a href="%POST_URL%${ ATTR_PAYLOAD }">%POST_TITLE%</a></li>` );
		await saveSettings( page );

		const post = await requestUtils.createPost( {
			title: uniqueTitle( 'Breakout listing post' ),
			content: 'Body.',
			status: 'publish',
		} );
		setViews( post.id, 7 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( '/' );

			expect( await pwned( guest ) ).toBe( false );
			await expect( guest.locator( '#pv-most a[onmouseover]' ) ).toHaveCount( 0 );

			// Hover it, because an attribute breakout that survived would only
			// fire on the event it named.
			await guest.locator( '#pv-most a' ).first().hover();
			expect( await pwned( guest ) ).toBe( false );
		} );
	} );

	test( 'a hostile template stored straight into the row still edits as text', async ( {
		page,
	} ) => {
		// The row a compromised install has: written past the sanitiser
		// entirely. The settings screen has to be able to show an administrator
		// what is in it without running it, or cleaning up the mess means
		// hand-editing the database.
		setOptions( {
			template: `%VIEW_COUNT% ${ SCRIPT_PAYLOAD }`,
			most_viewed_template: `<li>${ IMG_PAYLOAD } ${ ATTR_PAYLOAD }</li>`,
		} );

		await openSettings( page );

		expect( await pwned( page ) ).toBe( false );
		await expect( page.locator( '#wpbody img[onerror]' ) ).toHaveCount( 0 );
		await expect( page.locator( '#wpbody [onmouseover]' ) ).toHaveCount( 0 );

		// And the field holds the stored value itself, so pressing Save does
		// not silently rewrite what the row says.
		await expect( page.locator( '#views-template-template' ) ).toHaveValue(
			`%VIEW_COUNT% ${ SCRIPT_PAYLOAD }`,
		);
		await expect( page.locator( '#views-template-most_viewed_template' ) ).toHaveValue(
			`<li>${ IMG_PAYLOAD } ${ ATTR_PAYLOAD }</li>`,
		);
	} );
} );

test.describe( 'Hostile values in a post title', () => {
	let hostile;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
		installProbe();
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// The hostile post is deleted here as well as recreated per test. It
		// carries a script tag in its title, so left behind it is not just a
		// stray row: it changes what every later spec's front page contains.
		await requestUtils.deleteAllPosts();
		removeProbe();
		resetOptions();
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		resetOptions();
		clearAllViews();

		// A post per test, and the previous one gone, so one test's hostile
		// fixture is never another's starting point.
		await requestUtils.deleteAllPosts();

		hostile = await requestUtils.createPost( {
			title: uniqueTitle( 'Hostile title' ),
			content: 'Body.',
			status: 'publish',
		} );
		setRawTitle( hostile.id, `Hostile ${ SCRIPT_PAYLOAD } ${ IMG_PAYLOAD }` );
		setViews( hostile.id, 42 );
	} );

	test( 'the fixture really is an unsanitised title in the posts table', () => {
		// Straight out of the row rather than through get_the_title(), so this
		// is the byte-for-byte payload and not something a filter has already
		// tidied up.
		const stored = wpEval(
			`global $wpdb;
			echo '<<<' . $wpdb->get_var( $wpdb->prepare( "SELECT post_title FROM {$wpdb->posts} WHERE ID = %d", ${ hostile.id } ) ) . '>>>';`,
		);

		expect( stored ).toContain( '<script>window.__pwned = 1;</script>' );
		expect( stored ).toContain( 'onerror' );
	} );

	test( 'the listing renders a hostile title as text when it truncates it', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( '/' );

			/*
			 * Scoped to the plugin's own container, not to the page.
			 *
			 * The front page runs the theme's loop, and the theme prints this
			 * post's title with the_title(), which does not escape: markup in a
			 * post title is a WordPress feature, not a bug in this plugin. So
			 * the page-global sentinel is set here by core's output no matter
			 * what WP-PostViews does, and asserting on it would be asserting
			 * about WordPress.
			 *
			 * What this spec is about is what get_most_viewed() renders, so the
			 * assertions are about that element: nothing executable inside it,
			 * and the payload still present as text. A value eaten entirely
			 * would pass the two counts on its own.
			 */
			const listing = guest.locator( '#pv-most-chars' );

			// #pv-most-chars is the probe that passes a character limit, which
			// is what routes the title through snippet_text() -- the plugin's
			// own escaping for this value, and what the widget does by default.
			await expect( listing.locator( 'script' ) ).toHaveCount( 0 );
			await expect( listing.locator( 'img' ) ).toHaveCount( 0 );
			await expect( listing ).toContainText( 'Hostile' );
			await expect( listing ).toContainText( 'window.__pwned' );
		} );
	} );

	test( 'the posts list table shows the hostile row without running it', async ( { page } ) => {
		await page.goto( POSTS_URL );

		expect( await pwned( page ) ).toBe( false );
		await expect( page.locator( '#the-list img[onerror]' ) ).toHaveCount( 0 );
		// The Views column beside it still renders, so the row is on screen
		// rather than having been dropped.
		await expect( page.locator( '#the-list td.views' ).first() ).toHaveText( '42 views' );
	} );
} );
