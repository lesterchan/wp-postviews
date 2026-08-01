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

/** What every post this file creates is called, so they can be told apart. */
const FIXTURE_PREFIX = 'Sortable';

/**
 * The titles of this file's own fixture posts, in the order the loop rendered
 * them.
 *
 * Filtered rather than taken whole. These tests are about the *order* the query
 * vars produce, and reading every article on the front page made that order
 * depend on nothing else in the suite having left a published post behind --
 * which is not something a spec can promise about a shared install, and is how
 * a hostile fixture from security.spec.js once decided this file's assertions.
 *
 * @param {import('@playwright/test').Page} page Page showing the loop.
 * @return {import('@playwright/test').Locator} The fixture titles, in order.
 */
function fixtureTitles( page ) {
	return page.locator( 'main article .entry-title' ).filter( { hasText: FIXTURE_PREFIX } );
}

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
		//
		// The dates are stated rather than left to the clock. Three REST
		// creates in a row land inside the same second, WP_Query's default is
		// "ORDER BY post_date DESC" with no tiebreak, and MySQL is then free to
		// return the rows in whatever order it likes -- in practice primary-key
		// order, which is the exact reverse of the intended one. So the two
		// tests that read the *unsorted* front page failed, and did so while
		// the plugin's own sort was working perfectly.
		unloved = await requestUtils.createPost( {
			title: 'Sortable unloved post',
			content: 'Barely read.',
			status: 'publish',
			date: '2020-01-01T00:00:00',
		} );
		middling = await requestUtils.createPost( {
			title: 'Sortable middling post',
			content: 'Read sometimes.',
			status: 'publish',
			date: '2020-01-02T00:00:00',
		} );
		popular = await requestUtils.createPost( {
			title: 'Sortable popular post',
			content: 'Widely read.',
			status: 'publish',
			date: '2020-01-03T00:00:00',
		} );
	} );

	test.afterAll( async ( { requestUtils } ) => {
		// Taken away again rather than left for the next file. beforeAll clears
		// the install before it builds this fixture; clearing after it as well
		// is what stops these three deciding somebody else's ordering.
		await requestUtils.deleteAllPosts();
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
		await expect( fixtureTitles( page ) ).toHaveText( [
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

		await expect( fixtureTitles( page ) ).toHaveText( [
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

		await expect( fixtureTitles( page ).first() ).toHaveText(
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

		await expect( fixtureTitles( page ).first() ).toHaveText(
			'Sortable unloved post',
		);
	} );

	test( 'the sort is not left switched on for the queries that follow it', async ( { page } ) => {
		// posts_fields, posts_join, posts_where and posts_orderby are global
		// filters. Leaving them attached after the sorted query would join
		// every later query on the request to postmeta, so the honest check is
		// that an ordinary page after a sorted one still renders normally.
		await page.goto( '/?v_sortby=views&v_orderby=asc' );
		await expect( fixtureTitles( page ) ).toHaveCount( 3 );

		await page.goto( '/' );
		await expect( fixtureTitles( page ) ).toHaveText( [
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
