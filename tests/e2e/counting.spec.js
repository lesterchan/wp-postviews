/**
 * Counting a view, and the settings that decide whose views count.
 *
 * The far end here is a post meta row, not anything on the page, so every test
 * reads `views` back out of the database. A rendered "1 views" proves only that
 * the template says 1; the row is what the admin column, the listings and the
 * WP-Stats section all read.
 *
 * Which code path does the counting depends on WP_CACHE, and the tests
 * environment sets it, so the AJAX path is the default here and the wp_head
 * path is reached by switching "Use AJAX To Update Views" off.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	BOT_USER_AGENT,
	COUNT,
	asGuest,
	countedViews,
	counterScript,
	installCountVeto,
	option,
	removeCountVeto,
	resetOptions,
	setOptions,
	setViews,
	uniqueTitle,
	views,
	viewsRowCount,
	watchForCount,
	wpEvalJson,
} = require( './helpers.js' );

test.describe( 'Counting a view', () => {
	let post;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
	} );

	test.beforeEach( async ( { requestUtils } ) => {
		resetOptions();

		// A post of its own per test, so one test's count can never be another
		// test's starting point.
		post = await requestUtils.createPost( {
			title: uniqueTitle( 'Counted post' ),
			content: 'Something to look at.',
			status: 'publish',
		} );
	} );

	test.afterEach( async () => {
		resetOptions();
	} );

	test( 'the fixture really is the AJAX path: WP_CACHE is on and use_ajax is the default', () => {
		// Everything below leans on this. If the tests environment ever stopped
		// setting WP_CACHE the plugin would count during wp_head instead, the
		// counting script would never be enqueued, and half these tests would
		// be asserting on a code path that was not running.
		expect( wpEvalJson( 'defined( "WP_CACHE" ) && WP_CACHE' ) ).toBe( true );
		expect( option( 'use_ajax' ) ).toBe( 1 );
		expect( wpEvalJson( 'WP_PostViews_Counter::using_ajax()' ) ).toBe( true );
	} );

	test( 'a newly published post starts at zero rather than at nothing', async () => {
		// publish_post seeds the meta row, which is what keeps a never-viewed
		// post out of the most/least viewed listings' meta_key filter -- and
		// what makes "0" render instead of an empty string.
		expect( views( post.id ) ).toBe( 0 );
	} );

	test( 'a guest viewing the post increments the stored count', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await watchForCount( guest );
			await guest.goto( post.link );

			// The count the endpoint sent back, and then the row itself: the
			// response could be right while the write never happened.
			expect( await countedViews( guest ) ).toBe( 1 );
		} );

		expect( views( post.id ) ).toBe( 1 );
	} );

	test( 'two visits count twice', async ( { page } ) => {
		await asGuest( page, {}, async ( guest ) => {
			await watchForCount( guest );
			await guest.goto( post.link );
			await countedViews( guest );

			await guest.goto( post.link );
			expect( await countedViews( guest ) ).toBe( 2 );
		} );

		expect( views( post.id ) ).toBe( 2 );
	} );

	test( '"Guests Only" does not count a logged in administrator', async ( { page } ) => {
		// The shipped default, and the reason the suite's own admin page cannot
		// be used to test counting at all.
		expect( option( 'count' ) ).toBe( 1 );

		await page.goto( post.link );

		// The script is only enqueued for a request that will be counted, so
		// its absence is the plugin's decision rather than a race with a fetch
		// that has not arrived yet.
		await expect( counterScript( page ) ).toHaveCount( 0 );
		expect( views( post.id ) ).toBe( 0 );
	} );

	test( '"Registered Users Only" counts the administrator and not a guest', async ( { page } ) => {
		setOptions( { count: parseInt( COUNT.registeredOnly, 10 ) } );

		await watchForCount( page );
		await page.goto( post.link );
		expect( await countedViews( page ) ).toBe( 1 );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );
			await expect( counterScript( guest ) ).toHaveCount( 0 );
		} );

		// Both halves in one test: "the guest was not counted" on its own would
		// pass with the plugin switched off entirely.
		expect( views( post.id ) ).toBe( 1 );
	} );

	test( '"Everyone" counts a guest and an administrator alike', async ( { page } ) => {
		setOptions( { count: parseInt( COUNT.everyone, 10 ) } );

		await asGuest( page, {}, async ( guest ) => {
			await watchForCount( guest );
			await guest.goto( post.link );
			await countedViews( guest );
		} );

		await watchForCount( page );
		await page.goto( post.link );
		expect( await countedViews( page ) ).toBe( 2 );

		expect( views( post.id ) ).toBe( 2 );
	} );

	test( 'excluding bots drops a request from the robot list and keeps an ordinary one', async ( {
		page,
	} ) => {
		setOptions( { count: parseInt( COUNT.everyone, 10 ), exclude_bots: 1 } );

		await asGuest( page, { userAgent: BOT_USER_AGENT }, async ( bot ) => {
			await bot.goto( post.link );
			await expect( counterScript( bot ) ).toHaveCount( 0 );
		} );

		expect( views( post.id ) ).toBe( 0 );

		// The other direction, in the same test: an ordinary visitor is still
		// counted, so the setting is excluding bots rather than everybody.
		await asGuest( page, {}, async ( guest ) => {
			await watchForCount( guest );
			await guest.goto( post.link );
			await countedViews( guest );
		} );

		expect( views( post.id ) ).toBe( 1 );
	} );

	test( 'the same bot is counted when the exclusion is off', async ( { page } ) => {
		setOptions( { count: parseInt( COUNT.everyone, 10 ), exclude_bots: 0 } );

		await asGuest( page, { userAgent: BOT_USER_AGENT }, async ( bot ) => {
			await watchForCount( bot );
			await bot.goto( post.link );
			expect( await countedViews( bot ) ).toBe( 1 );
		} );

		expect( views( post.id ) ).toBe( 1 );
	} );

	test( 'switching AJAX off counts during wp_head instead, with no script on the page', async ( {
		page,
	} ) => {
		setOptions( { count: parseInt( COUNT.everyone, 10 ), use_ajax: 0 } );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( post.link );

			// No counting script, because there is nothing left for it to do:
			// the count was already recorded while the page was being built.
			await expect( counterScript( guest ) ).toHaveCount( 0 );
		} );

		expect( views( post.id ) ).toBe( 1 );
	} );

	test( 'a page is counted the same way a post is', async ( { page, requestUtils } ) => {
		setOptions( { count: parseInt( COUNT.everyone, 10 ) } );

		const staticPage = await requestUtils.createPage( {
			title: uniqueTitle( 'Counted page' ),
			content: 'A page, not a post.',
			status: 'publish',
		} );

		await asGuest( page, {}, async ( guest ) => {
			await watchForCount( guest );
			await guest.goto( staticPage.link );
			await countedViews( guest );
		} );

		expect( views( staticPage.id ) ).toBe( 1 );
	} );

	test( 'the front page is not counted against whichever post it listed last', async ( {
		page,
	} ) => {
		setOptions( { count: parseInt( COUNT.everyone, 10 ) } );

		await asGuest( page, {}, async ( guest ) => {
			await guest.goto( '/' );

			// current_post() requires is_single() or is_page(), so a listing has
			// nothing to count against however many posts it happens to show.
			await expect( counterScript( guest ) ).toHaveCount( 0 );
		} );

		expect( views( post.id ) ).toBe( 0 );
	} );

	test( 'an existing count is added to rather than replaced', async ( { page } ) => {
		setOptions( { count: parseInt( COUNT.everyone, 10 ) } );
		setViews( post.id, 41 );

		await asGuest( page, {}, async ( guest ) => {
			await watchForCount( guest );
			await guest.goto( post.link );
			expect( await countedViews( guest ) ).toBe( 42 );
		} );

		expect( views( post.id ) ).toBe( 42 );
	} );

	test( 'a preview is not counted, while the same post published is', async ( {
		page,
		requestUtils,
	} ) => {
		setOptions( { count: parseInt( COUNT.registeredOnly, 10 ) } );

		// A preview is the author looking at their own draft; counting it would
		// give a post views before anybody else had seen it.
		//
		// It has to be a *draft*. "?p=<published post>&preview=true" does not
		// reach the preview path at all: redirect_canonical() unsets
		// is_preview for a post that is already public, strips the parameter
		// and 301s to "?p=<id>", so what renders is the ordinary single post
		// and the counting script is correctly enqueued. The test used to read
		// that as the plugin counting a preview. A draft is not publicly
		// viewable, so nothing is stripped, the request stays a preview, and
		// this is the only version of the URL that puts should_count()'s
		// is_preview() branch under test.
		const draft = await requestUtils.createPost( {
			title: uniqueTitle( 'Previewed post' ),
			content: 'Not published yet.',
			status: 'draft',
		} );

		// Built from the id rather than from draft.link, because a draft's link
		// is the permalink it *would* get once published rather than one that
		// resolves now. ?p= is the form that reaches a post whatever the site's
		// permalink structure is, which is what this needs and all it needs.
		await page.goto( `/?p=${ draft.id }&preview=true` );
		await expect( counterScript( page ) ).toHaveCount( 0 );
		expect( views( draft.id ) ).toBe( 0 );

		// The same post, published, so the difference between the two halves is
		// the preview rather than the settings or the post.
		// Through rest() rather than an updatePost() helper: this version of
		// @wordpress/e2e-test-utils-playwright has createPost but no update.
		const published = await requestUtils.rest( {
			method: 'POST',
			path: `/wp/v2/posts/${ draft.id }`,
			data: { status: 'publish' },
		} );

		await watchForCount( page );
		await page.goto( published.link );
		expect( await countedViews( page ) ).toBe( 1 );
	} );

	test( 'the wp_postviews_should_count filter can veto a request that would count', async ( {
		page,
	} ) => {
		setOptions( { count: parseInt( COUNT.everyone, 10 ) } );

		installCountVeto();
		try {
			await asGuest( page, {}, async ( guest ) => {
				await guest.goto( post.link );
				await expect( counterScript( guest ) ).toHaveCount( 0 );
			} );
			expect( views( post.id ) ).toBe( 0 );
		} finally {
			removeCountVeto();
		}

		// And without the filter the very same visit counts, which is what
		// makes the half above about the filter rather than about the settings.
		await asGuest( page, {}, async ( guest ) => {
			await watchForCount( guest );
			await guest.goto( post.link );
			await countedViews( guest );
		} );
		expect( views( post.id ) ).toBe( 1 );
	} );

	test( 'the AJAX endpoint refuses a request without a valid nonce', async ( { page } ) => {
		setOptions( { count: parseInt( COUNT.everyone, 10 ) } );

		await asGuest( page, {}, async ( guest ) => {
			await watchForCount( guest );
			await guest.goto( post.link );
			await countedViews( guest );

			const nonce = await guest.evaluate( () => window.wpPostViewsL10n.nonce );

			const refused = await guest.request.post( '/wp-admin/admin-ajax.php', {
				form: { action: 'wp_postviews', nonce: 'not-a-nonce', postviews_id: post.id },
			} );
			expect( refused.status() ).toBe( 403 );

			// The same request with the page's own nonce, so the rejection
			// above is the nonce check and not the endpoint being broken.
			const accepted = await guest.request.post( '/wp-admin/admin-ajax.php', {
				form: { action: 'wp_postviews', nonce, postviews_id: post.id },
			} );
			expect( accepted.ok() ).toBe( true );
		} );

		// One visit plus one accepted POST; the refused one left no trace.
		expect( views( post.id ) ).toBe( 2 );
	} );

	test( 'the AJAX endpoint creates no meta row for a post id that does not exist', async ( {
		page,
	} ) => {
		setOptions( { count: parseInt( COUNT.everyone, 10 ) } );

		await asGuest( page, {}, async ( guest ) => {
			await watchForCount( guest );
			await guest.goto( post.link );
			await countedViews( guest );

			const nonce = await guest.evaluate( () => window.wpPostViewsL10n.nonce );

			// update_post_meta() happily creates a row for an id nothing owns,
			// so without the get_post_status() guard a logged out visitor could
			// walk the id space and grow wp_postmeta without bound.
			await guest.request.post( '/wp-admin/admin-ajax.php', {
				form: { action: 'wp_postviews', nonce, postviews_id: 987654321 },
			} );
		} );

		expect( viewsRowCount( 987654321 ) ).toBe( 0 );
		// And the real post still counted, so the guard is rejecting the bogus
		// id rather than the endpoint being switched off.
		expect( views( post.id ) ).toBe( 1 );
	} );
} );
