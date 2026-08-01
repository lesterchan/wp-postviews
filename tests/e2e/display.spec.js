/**
 * What a visitor sees: the_views(), the [views] shortcode, the template that
 * decides the wording, and the display matrix that decides who sees a count on
 * which kind of page.
 *
 * Nothing here would render at all without the probe mu-plugin. The plugin's
 * front end is a set of template tags a theme is expected to call, and
 * twentytwentyone calls none of them, so the probe is standing in for the
 * theme -- see installProbe() in helpers.js.
 *
 * Every assertion about a rendered number sets the stored count immediately
 * before navigating. The AJAX counting path records a view *after* the page has
 * been built, so an earlier visit in the same test would otherwise decide what
 * the next one prints.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	DISPLAY,
	asGuest,
	installProbe,
	openSettings,
	removeProbe,
	resetOptions,
	saveSettings,
	setOptions,
	setViews,
	uniqueTitle,
} = require( './helpers.js' );

test.describe( 'Displaying a view count', () => {
	let post;
	let staticPage;
	let categoryId;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		installProbe();

		const category = await requestUtils.rest( {
			method: 'POST',
			path: '/wp/v2/categories',
			data: { name: uniqueTitle( 'Display category' ) },
		} );
		categoryId = category.id;

		post = await requestUtils.createPost( {
			title: uniqueTitle( 'Displayable post' ),
			content: 'Findable body text.',
			status: 'publish',
			categories: [ categoryId ],
		} );

		staticPage = await requestUtils.createPage( {
			title: uniqueTitle( 'Displayable page' ),
			content: 'A page.',
			status: 'publish',
		} );
	} );

	test.afterAll( async () => {
		removeProbe();
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
		// Everything visible by default, so a matrix test only has to change the
		// one row it is about.
		setOptions( {
			display_home: 0,
			display_single: 0,
			display_page: 0,
			display_archive: 0,
			display_search: 0,
			display_other: 0,
		} );
	} );

	test.afterEach( async () => {
		resetOptions();
	} );

	test( 'the fixture really is calling the template tag, on a page that shows a count', async ( {
		page,
	} ) => {
		setViews( post.id, 7 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			// Both probes, because the rest of this file tells them apart: the
			// plain one is what the matrix can blank, the $always one is what
			// the admin column uses and the matrix never touches.
			await expect( guest.locator( '#pv-views' ) ).toHaveText( '7 views' );
			await expect( guest.locator( '#pv-views-always' ) ).toHaveText( '7 views' );
		} );
	} );

	// One row of the matrix per context. Each sets its own row to "never" and
	// leaves the others alone, so a context the plugin misidentifies shows up
	// as this test failing rather than as a silent pass somewhere else.
	const contexts = [
		[ 'display_home', 'the front page', () => '/' ],
		[ 'display_single', 'a single post', () => post.link ],
		[ 'display_page', 'a page', () => staticPage.link ],
		// Query-var URLs, not pretty ones: the tests environment ships plain
		// permalinks, so /category/... is a 404 there.
		[ 'display_archive', 'a category archive', () => `/?cat=${ categoryId }` ],
		[ 'display_search', 'a search results page', () => '/?s=Findable' ],
		[ 'display_other', 'a page that is none of the above', () => '/?p=999999999' ],
	];

	for ( const [ key, description, url ] of contexts ) {
		test( `"${ key }" governs whether a count appears on ${ description }`, async ( {
			page,
		} ) => {
			await asGuest( page, {}, async ( guest ) => {
				// On by default from beforeEach: something is there to lose.
				await guest.goto( url() );
				await expect( guest.locator( '#pv-views' ) ).not.toHaveText( '' );

				setOptions( { [ key ]: parseInt( DISPLAY.never, 10 ) } );

				await guest.goto( url() );

				// Attached and empty, not absent. The theme still called
				// the_views(); the matrix is what made it return nothing, and
				// the $always probe beside it proves the tag itself still works
				// on this very page.
				await expect( guest.locator( '#pv-views' ) ).toBeAttached();
				await expect( guest.locator( '#pv-views' ) ).toHaveText( '' );
				await expect( guest.locator( '#pv-views-always' ) ).not.toHaveText( '' );
			} );
		} );
	}

	test( '"Display to registered users only" hides the count from a guest and shows it to an administrator', async ( {
		page,
	} ) => {
		setOptions( { display_single: parseInt( DISPLAY.registeredOnly, 10 ) } );
		setViews( post.id, 12 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			await expect( guest.locator( '#pv-views' ) ).toHaveText( '' );
		} );

		// The other half, in the same test. "The guest saw nothing" on its own
		// passes just as well with the plugin deactivated.
		setViews( post.id, 12 );
		await page.goto( post.link );
		await expect( page.locator( '#pv-views' ) ).toHaveText( '12 views' );
	} );

	test( 'the prefix and postfix wrap the rendered template', async ( { page } ) => {
		setViews( post.id, 3 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			await expect( guest.locator( '#pv-views-fix' ) ).toHaveText( '[3 views]' );
		} );
	} );

	test( 'the [views] shortcode renders the count for the post it sits in', async ( {
		page,
		requestUtils,
	} ) => {
		const shortcodePost = await requestUtils.createPost( {
			title: uniqueTitle( 'Shortcode post' ),
			content: 'Seen [views] so far.',
			status: 'publish',
		} );
		setViews( shortcodePost.id, 99 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( shortcodePost.link );

			await expect( guest.locator( '.entry-content' ) ).toContainText( 'Seen 99 views so far.' );
		} );
	} );

	test( 'the [views] shortcode ignores the display matrix', async ( { page, requestUtils } ) => {
		// Dropping the shortcode into a post is an explicit request for the
		// count, so unlike the theme call it is not something the matrix gets a
		// say in. Asserting both on the same page proves the difference is the
		// shortcode and not the settings.
		const shortcodePost = await requestUtils.createPost( {
			title: uniqueTitle( 'Shortcode despite matrix' ),
			content: 'Count: [views]',
			status: 'publish',
		} );
		setOptions( { display_single: parseInt( DISPLAY.never, 10 ) } );
		setViews( shortcodePost.id, 44 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( shortcodePost.link );

			await expect( guest.locator( '.entry-content' ) ).toContainText( 'Count: 44 views' );
			await expect( guest.locator( '#pv-views' ) ).toHaveText( '' );
		} );
	} );

	test( 'the [views] shortcode with an id renders that other post\'s count', async ( {
		page,
		requestUtils,
	} ) => {
		setViews( post.id, 61 );

		const hostPost = await requestUtils.createPost( {
			title: uniqueTitle( 'Shortcode with an id' ),
			content: `Elsewhere: [views id="${ post.id }"]`,
			status: 'publish',
		} );
		setViews( hostPost.id, 0 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( hostPost.link );

			await expect( guest.locator( '.entry-content' ) ).toContainText( 'Elsewhere: 61 views' );
		} );
	} );

	test( 'the Views Template setting decides the wording on the front end', async ( { page } ) => {
		await openSettings( page );
		await page.locator( '#views-template-template' ).fill( 'Read %VIEW_COUNT% times' );
		await saveSettings( page );

		setViews( post.id, 5 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			await expect( guest.locator( '#pv-views' ) ).toHaveText( 'Read 5 times' );
		} );
	} );

	test( '%VIEW_COUNT_ROUNDED% abbreviates a large count', async ( { page } ) => {
		setOptions( { template: '%VIEW_COUNT_ROUNDED% views' } );
		setViews( post.id, 1234 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			// The unit is chosen from the rounded value, which is what keeps
			// 999,950 from printing as "1000K".
			await expect( guest.locator( '#pv-views' ) ).toHaveText( '1.2K views' );
		} );
	} );

	test( '%VIEW_COUNT_ROUNDED% climbs through K, M and B', async ( { page } ) => {
		setOptions( { template: '%VIEW_COUNT_ROUNDED%' } );

		for ( const [ count, rendered ] of [
			[ 999, '999' ],
			[ 1234567, '1.2M' ],
			[ 1234567890, '1.2B' ],
		] ) {
			setViews( post.id, count );

			await asGuest( page, {}, async ( guest ) => {
				await guest.goto( post.link );
				await expect( guest.locator( '#pv-views' ) ).toHaveText( rendered );
			} );
		}
	} );

	test( 'the count a visitor sees survives a page refresh, i.e. it came from storage', async ( {
		page,
	} ) => {
		setViews( post.id, 20 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			await expect( guest.locator( '#pv-views' ) ).toHaveText( '20 views' );

			// The AJAX path recorded that visit after the page was built, so
			// the next render is the proof the number is read back from the
			// meta row rather than held in the page.
			await guest.reload();
			await expect( guest.locator( '#pv-views' ) ).toHaveText( '21 views' );
		} );
	} );
} );
