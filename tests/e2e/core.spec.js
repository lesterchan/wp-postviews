/**
 * The two surfaces that are neither counting nor the plugin's own markup: the
 * ?v_sortby= query vars, which reorder the theme's own loop, and the `views`
 * field the plugin adds to the REST post resource.
 *
 * Both are public API. The query vars are documented for themes that want a
 * "most read" archive without calling a template tag, and the REST field is
 * what a headless front end reads. Neither renders anything of the plugin's, so
 * nothing in the other spec files would notice if either stopped working.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const { clearAllViews, resetOptions, setViews, views } = require( './helpers.js' );

test.describe( 'Query vars and the REST field', () => {
	let popular;
	let middling;
	let unloved;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();

		// Sequentially, because the theme's default ordering is by date and the
		// tests below have to be able to tell "sorted by views" from "left in
		// whatever order the fixtures happened to land in". Created newest
		// last, so date order is the exact reverse of view order.
		unloved = await requestUtils.createPost( {
			title: 'Sortable unloved post',
			content: 'Barely read.',
			status: 'publish',
		} );
		middling = await requestUtils.createPost( {
			title: 'Sortable middling post',
			content: 'Read sometimes.',
			status: 'publish',
		} );
		popular = await requestUtils.createPost( {
			title: 'Sortable popular post',
			content: 'Widely read.',
			status: 'publish',
		} );
	} );

	test.afterAll( async () => {
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
		clearAllViews();
		setViews( popular.id, 300 );
		setViews( middling.id, 200 );
		setViews( unloved.id, 100 );
	} );

	test( 'the fixture really is in the opposite order by date and by views', async ( { page } ) => {
		expect( [ views( popular.id ), views( middling.id ), views( unloved.id ) ] ).toEqual( [
			300, 200, 100,
		] );

		// The unsorted front page, so the tests below are demonstrably changing
		// the order rather than agreeing with one that was already there.
		await page.goto( '/' );
		await expect( page.locator( 'main article .entry-title' ) ).toHaveText( [
			'Sortable popular post',
			'Sortable middling post',
			'Sortable unloved post',
		] );
	} );

	test( '?v_sortby=views&v_orderby=asc reorders the theme\'s own loop', async ( { page } ) => {
		setViews( popular.id, 300 );
		setViews( middling.id, 200 );
		setViews( unloved.id, 100 );

		await page.goto( '/?v_sortby=views&v_orderby=asc' );

		await expect( page.locator( 'main article .entry-title' ) ).toHaveText( [
			'Sortable unloved post',
			'Sortable middling post',
			'Sortable popular post',
		] );
	} );

	test( '?v_orderby=desc puts the most viewed first', async ( { page } ) => {
		// Date order happens to agree with this one, so the previous test is
		// the one that proves the sort is doing anything; this one is here
		// because desc is a separate branch of posts_orderby().
		setViews( unloved.id, 900 );

		await page.goto( '/?v_sortby=views&v_orderby=desc' );

		await expect( page.locator( 'main article .entry-title' ).first() ).toHaveText(
			'Sortable unloved post',
		);
	} );

	test( 'an unrecognised v_orderby falls back to descending rather than reaching SQL', async ( {
		page,
	} ) => {
		setViews( unloved.id, 900 );

		// The direction is interpolated into ORDER BY, where prepare() cannot
		// bind it, so it is validated against a fixed pair instead. Anything
		// else has to become "desc" -- and the page has to still render.
		await page.goto( '/?v_sortby=views&v_orderby=asc%3B+DROP' );

		await expect( page.locator( 'main article .entry-title' ).first() ).toHaveText(
			'Sortable unloved post',
		);
	} );

	test( 'the sort is not left switched on for the queries that follow it', async ( { page } ) => {
		// posts_fields, posts_join, posts_where and posts_orderby are global
		// filters. Leaving them attached after the sorted query would join
		// every later query on the request to postmeta, so the honest check is
		// that an ordinary page after a sorted one still renders normally.
		await page.goto( '/?v_sortby=views&v_orderby=asc' );
		await expect( page.locator( 'main article .entry-title' ) ).toHaveCount( 3 );

		await page.goto( '/' );
		await expect( page.locator( 'main article .entry-title' ) ).toHaveText( [
			'Sortable popular post',
			'Sortable middling post',
			'Sortable unloved post',
		] );
	} );

	test( 'the REST post resource carries the stored view count', async ( { requestUtils } ) => {
		const record = await requestUtils.rest( { path: `/wp/v2/posts/${ popular.id }` } );

		expect( record.views ).toBe( 300 );

		// And it moves with the row rather than being frozen at publish time.
		setViews( popular.id, 301 );
		const again = await requestUtils.rest( { path: `/wp/v2/posts/${ popular.id }` } );
		expect( again.views ).toBe( 301 );
	} );
} );
