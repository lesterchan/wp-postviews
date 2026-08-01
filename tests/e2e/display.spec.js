/**
 * What a visitor sees: the_views(), the [views] shortcode, the template that
 * decides the wording, and the wp_postviews_should_display filter that decides
 * whether a count appears at all.
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
	asGuest,
	installDisplayGate,
	installProbe,
	openTemplates,
	removeDisplayGate,
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
		removeDisplayGate();
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
		// No gate in place unless a test installs one: unfiltered, every count
		// is displayed, which is what the six display_* settings all defaulted
		// to before they were removed.
		removeDisplayGate();
	} );

	test.afterEach( async () => {
		removeDisplayGate();
		resetOptions();
	} );

	test( 'the fixture really is calling the template tag, on a page that shows a count', async ( {
		page,
	} ) => {
		setViews( post.id, 7 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			// Both probes, because the rest of this file tells them apart: the
			// plain one is what the gate can blank, the $always one is what
			// the admin column uses and the gate never touches.
			await expect( guest.locator( '#pv-views' ) ).toHaveText( '7 views' );
			await expect( guest.locator( '#pv-views-always' ) ).toHaveText( '7 views' );
		} );
	} );

	// The six contexts the retired Display Options matrix had a row for. Each is
	// now something the filter can decide, and the conditional tag it decides on
	// is the same one the setting was named after -- so a context the plugin
	// misidentifies shows up as this test failing rather than as a silent pass
	// somewhere else.
	const contexts = [
		[ 'is_home()', 'the front page', () => '/' ],
		[ 'is_single()', 'a single post', () => post.link ],
		[ 'is_page()', 'a page', () => staticPage.link ],
		// Query-var URLs, not pretty ones: the tests environment ships plain
		// permalinks, so /category/... is a 404 there.
		[ 'is_archive()', 'a category archive', () => `/?cat=${ categoryId }` ],
		[ 'is_search()', 'a search results page', () => '/?s=Findable' ],
		[ 'is_404()', 'a page that is none of the above', () => '/?p=999999999' ],
	];

	for ( const [ conditional, description, url ] of contexts ) {
		test( `the filter can hide the count on ${ description }`, async ( { page } ) => {
			await asGuest( page, {}, async ( guest ) => {
				// Nothing filtering it from beforeEach: something is there to lose.
				await guest.goto( url() );
				await expect( guest.locator( '#pv-views' ) ).not.toHaveText( '' );

				// The migration the Upgrade Notice describes: one conditional in
				// place of one row of the old matrix.
				installDisplayGate( `! ${ conditional }` );

				await guest.goto( url() );

				// Attached and empty, not absent. The theme still called
				// the_views(); the gate is what made it return nothing, and the
				// $always probe beside it proves the tag itself still works on
				// this very page.
				await expect( guest.locator( '#pv-views' ) ).toBeAttached();
				await expect( guest.locator( '#pv-views' ) ).toHaveText( '' );
				await expect( guest.locator( '#pv-views-always' ) ).not.toHaveText( '' );
			} );
		} );
	}

	test( 'the count is shown everywhere when nothing answers the filter', async ( { page } ) => {
		// The default the six removed settings all carried. A site that upgrades
		// and adds no filter sees its counts, including in the two places the
		// Upgrade Notice warns about.
		setViews( post.id, 5 );

		await asGuest( page, {}, async ( guest ) => {
			for ( const url of [ '/', post.link, `/?cat=${ categoryId }`, '/?s=Findable' ] ) {
				await guest.goto( url );
				await expect( guest.locator( '#pv-views' ) ).not.toHaveText( '' );
			}
		} );
	} );

	test( 'the filter can hide the count from guests and show it to an administrator', async ( {
		page,
	} ) => {
		// What "Display to registered users only" used to mean, in one line.
		installDisplayGate( 'is_user_logged_in()' );
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

	test( 'the [views] shortcode ignores the display filter', async ( { page, requestUtils } ) => {
		// Dropping the shortcode into a post is an explicit request for the
		// count, so unlike the theme call it is not something the filter gets a
		// say in. Asserting both on the same page proves the difference is the
		// shortcode and not the filter.
		const shortcodePost = await requestUtils.createPost( {
			title: uniqueTitle( 'Shortcode despite the gate' ),
			content: 'Count: [views]',
			status: 'publish',
		} );
		installDisplayGate( 'false' );
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
		await openTemplates( page );
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
