/**
 * The admin surface: the Views column on the post and page list tables, the
 * sorting it offers, the settings screen's own script, and the capability that
 * gates the lot.
 *
 * The column is the one place the plugin renders a count with the display
 * matrix deliberately switched off, so the tests here pin that: 1.65 shipped
 * with the matrix applied to the column too, and every site that had chosen
 * "Don't display" lost the numbers from wp-admin as well.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	DISPLAY,
	PAGES_URL,
	POSTS_URL,
	SETTINGS_URL,
	clearAllViews,
	openSettings,
	option,
	resetOptions,
	saveSettings,
	setOptions,
	setViews,
	views,
} = require( './helpers.js' );

/**
 * The Views cell of one row of the list table.
 *
 * @param {import('@playwright/test').Page} page  Page showing a list table.
 * @param {string}                          title Post title identifying the row.
 * @return {import('@playwright/test').Locator} The cell.
 */
function viewsCell( page, title ) {
	return page.locator( `#the-list tr:has-text("${ title }") td.views` );
}

/**
 * The Views column header at the top of the list table.
 *
 * Scoped to the thead on purpose: core repeats every header in a tfoot, so an
 * unscoped role query matches both and Playwright's strict mode refuses to
 * guess which one was meant.
 *
 * @param {import('@playwright/test').Page} page Page showing a list table.
 * @return {import('@playwright/test').Locator} The header cell.
 */
function viewsHeader( page ) {
	return page.locator( 'thead' ).getByRole( 'columnheader', { name: 'Views' } );
}

test.describe( 'The admin column and screen', () => {
	let popular;
	let unloved;
	let staticPage;

	test.beforeAll( async ( { requestUtils } ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();

		popular = await requestUtils.createPost( {
			title: 'Column popular post',
			content: 'Widely read.',
			status: 'publish',
		} );
		unloved = await requestUtils.createPost( {
			title: 'Column unloved post',
			content: 'Barely read.',
			status: 'publish',
		} );
		staticPage = await requestUtils.createPage( {
			title: 'Column page',
			content: 'A page.',
			status: 'publish',
		} );
	} );

	test.afterAll( async () => {
		resetOptions();
	} );

	test.beforeEach( async () => {
		resetOptions();
		clearAllViews();
		setViews( popular.id, 500 );
		setViews( unloved.id, 4 );
		setViews( staticPage.id, 77 );
	} );

	test.afterEach( async () => {
		resetOptions();
	} );

	test( 'the fixture really is two posts whose stored counts differ', () => {
		// Every sorting assertion below leans on this, and on the gap being
		// wide enough that a stray view cannot close it.
		expect( views( popular.id ) ).toBe( 500 );
		expect( views( unloved.id ) ).toBe( 4 );
	} );

	test( 'the posts list table carries a Views column showing the stored count', async ( {
		page,
	} ) => {
		await page.goto( POSTS_URL );

		await expect( viewsHeader( page ) ).toBeVisible();
		await expect( viewsCell( page, 'Column popular post' ) ).toHaveText( '500 views' );
		await expect( viewsCell( page, 'Column unloved post' ) ).toHaveText( '4 views' );
	} );

	test( 'the pages list table carries the same column', async ( { page } ) => {
		await page.goto( PAGES_URL );

		await expect( viewsHeader( page ) ).toBeVisible();
		await expect( viewsCell( page, 'Column page' ) ).toHaveText( '77 views' );
	} );

	test( 'the column still shows a count when the display matrix says nobody sees one', async ( {
		page,
	} ) => {
		// The 1.65 regression. The matrix governs the front end; letting it
		// reach the column blanked wp-admin for every site that had turned the
		// counts off for visitors.
		setOptions( {
			display_home: parseInt( DISPLAY.never, 10 ),
			display_single: parseInt( DISPLAY.never, 10 ),
			display_page: parseInt( DISPLAY.never, 10 ),
			display_archive: parseInt( DISPLAY.never, 10 ),
			display_search: parseInt( DISPLAY.never, 10 ),
			display_other: parseInt( DISPLAY.never, 10 ),
		} );

		await page.goto( POSTS_URL );

		await expect( viewsCell( page, 'Column popular post' ) ).toHaveText( '500 views' );
	} );

	test( 'the column renders through the Views Template like the front end does', async ( {
		page,
	} ) => {
		setOptions( { template: 'seen %VIEW_COUNT% times' } );

		await page.goto( POSTS_URL );

		await expect( viewsCell( page, 'Column popular post' ) ).toHaveText( 'seen 500 times' );
	} );

	test( 'the Views column header sorts the posts list both ways', async ( { page } ) => {
		await page.goto( POSTS_URL );

		// Through the header link the plugin's sortable_columns filter created,
		// rather than a hand-built orderby URL: the link existing at all is
		// half of what this test is for.
		await viewsHeader( page ).getByRole( 'link' ).click();

		await expect( page.locator( '#the-list tr .row-title' ) ).toHaveText( [
			'Column unloved post',
			'Column popular post',
		] );

		await viewsHeader( page ).getByRole( 'link' ).click();

		await expect( page.locator( '#the-list tr .row-title' ) ).toHaveText( [
			'Column popular post',
			'Column unloved post',
		] );
	} );

	test( 'the settings screen sits under Settings and is reachable from the menu', async ( {
		page,
	} ) => {
		await page.goto( '/wp-admin/index.php' );

		// The submenu is only drawn out on hover, so this is the same sequence
		// a person performs. §4.1 puts the one screen under Settings rather
		// than giving the plugin a top-level menu, and the URL below is the
		// assertion that it really landed there.
		await page.locator( '#menu-settings' ).hover();
		await page
			.locator( '#menu-settings' )
			.getByRole( 'link', { name: 'WP-PostViews', exact: true } )
			.click();

		await expect( page.getByRole( 'heading', { name: 'Post Views Options' } ) ).toBeVisible();
		expect( page.url() ).toContain( 'options-general.php?page=wp-postviews' );
	} );

	test( 'Restore Default Template puts the shipped wording back in the field and saves it', async ( {
		page,
	} ) => {
		setOptions( { template: 'something else entirely' } );

		await openSettings( page );
		await expect( page.locator( '#views-template-template' ) ).toHaveValue(
			'something else entirely',
		);

		await page
			.locator( '[data-postviews-reset="template"][data-postviews-target="views-template-template"]' )
			.click();

		await expect( page.locator( '#views-template-template' ) ).toHaveValue( '%VIEW_COUNT% views' );

		// The button only edits the field, so the far end is only reached once
		// the form is saved -- which is the difference between a control that
		// looks right and one that does something.
		await saveSettings( page );
		expect( option( 'template' ) ).toBe( '%VIEW_COUNT% views' );
	} );

	test( 'Restore Default Template restores the most viewed template independently', async ( {
		page,
	} ) => {
		setOptions( { template: 'kept as is', most_viewed_template: '<li>replaced</li>' } );

		await openSettings( page );
		await page
			.locator(
				'[data-postviews-reset="most_viewed_template"][data-postviews-target="views-template-most_viewed_template"]',
			)
			.click();

		await expect( page.locator( '#views-template-most_viewed_template' ) ).toHaveValue(
			/%POST_URL%/,
		);
		// The other field is untouched, so the button is bound to its own key
		// rather than resetting everything on the screen.
		await expect( page.locator( '#views-template-template' ) ).toHaveValue( 'kept as is' );
	} );

	test( 'a subscriber gets no WP-PostViews entry and no screen, and an administrator gets both', async ( {
		page,
		requestUtils,
	} ) => {
		// Both directions on purpose. "The subscriber sees nothing" passes with
		// the plugin deactivated; the administrator half is what proves the
		// gate is the capability and not a missing page.
		await page.goto( '/wp-admin/index.php' );
		await expect( page.locator( '#adminmenu' ) ).toContainText( 'WP-PostViews' );

		await page.goto( SETTINGS_URL );
		await expect( page.getByRole( 'heading', { name: 'Post Views Options' } ) ).toBeVisible();

		await requestUtils
			.rest( {
				method: 'POST',
				path: '/wp/v2/users',
				data: {
					username: 'postviews_subscriber',
					email: 'postviews_subscriber@example.com',
					password: 'correct-horse-battery-staple',
					roles: [ 'subscriber' ],
				},
			} )
			.catch( () => {} ); // Already there from an earlier run.

		const context = await page.context().browser().newContext( { storageState: undefined } );
		const other = await context.newPage();

		await other.goto( '/wp-login.php' );

		// wp-login.php focuses and selects #user_login on a 200ms timer, so
		// that a visitor can start typing. Filling across that moment puts the
		// password into the username box: Playwright focuses #user_pass, the
		// timer takes focus back and selects what is there, and the typed text
		// replaces the selection. Waiting for the timer's own effect is the
		// signal that it has already fired.
		await expect( other.locator( '#user_login' ) ).toBeFocused();

		await other.locator( '#user_login' ).fill( 'postviews_subscriber' );
		await other.locator( '#user_pass' ).fill( 'correct-horse-battery-staple' );
		await other.locator( '#wp-submit' ).click();
		await expect( other.locator( '#wpadminbar' ) ).toBeVisible();

		await other.goto( '/wp-admin/index.php' );
		await expect( other.locator( '#adminmenu' ).getByText( 'WP-PostViews' ) ).toHaveCount( 0 );

		await other.goto( SETTINGS_URL );
		await expect( other.locator( 'body' ) ).toContainText( /not allowed to access this page/ );

		await context.close();
	} );
} );
