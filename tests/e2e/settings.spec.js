/**
 * Settings > WP-PostViews, and what each row stores.
 *
 * A setting that saves but does nothing, and a setting that does something but
 * will not save, are the two failures a screenshot cannot tell apart. The
 * effect half of each row is proved elsewhere -- counting.spec.js for the
 * counting rows, display.spec.js for the template and the display filter -- so
 * this file is about the other half: that what the form posted is what the
 * option row ends up holding, through the sanitiser, and that reloading the
 * screen shows it back.
 *
 * The screen is two tabs over one option row, which is the failure mode this
 * file has to watch: the Settings API hands the sanitiser only the fields the
 * submitting form posted, so saving one tab is the moment the other tab's
 * values are most likely to disappear.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	COUNT,
	clearAllViews,
	openSettings,
	openTemplates,
	option,
	resetOptions,
	saveSettings,
	setOptions,
	setViews,
	uniqueTitle,
	wpEvalJson,
} = require( './helpers.js' );

/**
 * The sections WP-Stats would collect on this install, keyed by contributor.
 *
 * WP-Stats is not installed here, and it does not need to be: the whole
 * contract between the two plugins is one filter. Firing it is exactly what
 * WP-Stats does, so this is the far end of the stats_display toggle rather than
 * a stand-in for it.
 *
 * @return {Object} The filtered sections.
 */
function statsSections() {
	return wpEvalJson( 'apply_filters( "wp_stats_sections", array() )' );
}

/**
 * The markup WP-PostViews' own section would contribute.
 *
 * Captured through an output buffer because the contract says the callback
 * echoes: WP-Stats assembles its page inside one, so a section that returned
 * its markup would be dropped without a word.
 *
 * @return {string} The section body.
 */
function statsSectionBody() {
	return wpEvalJson(
		'( function () { ob_start(); WP_PostViews_WPStats::render(); return ob_get_clean(); } )()',
	);
}

test.describe( 'The settings screen', () => {
	test.beforeEach( async () => {
		resetOptions();
	} );

	test.afterEach( async () => {
		// Everything this file changes lives in one option row, so one reset
		// puts the whole screen back for the next test.
		resetOptions();
	} );

	test( 'the fixture really is a cached install, so the AJAX row is on the screen', async ( {
		page,
	} ) => {
		// The use_ajax row is only registered when WP_CACHE is set, and this
		// environment sets it. Without that the row below would be absent and
		// its test would be asserting on a field that was never meant to be
		// there.
		await openSettings( page );

		await expect( page.locator( '#views-use_ajax' ) ).toBeVisible();
		await expect( page.locator( 'input[name="wp_postviews_options[use_ajax]"]' ) ).toHaveCount( 0 );
	} );

	for ( const [ name, value ] of Object.entries( COUNT ) ) {
		test( `"Count Views From" stores the ${ name } choice`, async ( { page } ) => {
			await openSettings( page );

			await page.locator( '#views-count' ).selectOption( value );
			await saveSettings( page );

			// The counting path reads this as an int, so the stored value is
			// what decides behaviour; a selected option that never lands there
			// changes nothing.
			expect( option( 'count' ) ).toBe( parseInt( value, 10 ) );

			await openSettings( page );
			await expect( page.locator( '#views-count' ) ).toHaveValue( value );
		} );
	}

	test( '"Exclude Bot Views" stores yes and no', async ( { page } ) => {
		await openSettings( page );
		await page.locator( '#views-exclude_bots' ).selectOption( '1' );
		await saveSettings( page );
		expect( option( 'exclude_bots' ) ).toBe( 1 );

		await openSettings( page );
		await page.locator( '#views-exclude_bots' ).selectOption( '0' );
		await saveSettings( page );
		expect( option( 'exclude_bots' ) ).toBe( 0 );
	} );

	test( '"Use AJAX To Update Views" stores yes and no', async ( { page } ) => {
		await openSettings( page );
		await page.locator( '#views-use_ajax' ).selectOption( '0' );
		await saveSettings( page );
		expect( option( 'use_ajax' ) ).toBe( 0 );

		await openSettings( page );
		await page.locator( '#views-use_ajax' ).selectOption( '1' );
		await saveSettings( page );
		expect( option( 'use_ajax' ) ).toBe( 1 );
	} );

	test( 'the retired Display Options rows are gone from the screen and from the row', async ( {
		page,
	} ) => {
		const retired = [
			'display_home',
			'display_single',
			'display_page',
			'display_archive',
			'display_search',
			'display_other',
		];

		// On neither tab.
		for ( const tab of [ 'settings', 'templates' ] ) {
			await openSettings( page, tab );

			for ( const key of retired ) {
				await expect( page.locator( `#views-${ key }` ) ).toHaveCount( 0 );
			}

			await expect( page.getByRole( 'heading', { name: 'Display Options' } ) ).toHaveCount( 0 );
		}

		// And a value written straight into the row -- which is what a site
		// upgrading from 1.78.1 carries -- is dropped rather than kept.
		setOptions( Object.fromEntries( retired.map( ( key ) => [ key, 2 ] ) ) );

		const stored = wpEvalJson( 'get_option( "wp_postviews_options" )' );

		for ( const key of retired ) {
			expect( stored ).not.toHaveProperty( key );
		}
	} );

	test( 'the screen has a Settings tab and a Templates tab, and each draws its own fields', async ( {
		page,
	} ) => {
		await openSettings( page );

		await expect( page.locator( '.nav-tab-wrapper' ) ).toBeVisible();
		await expect( page.locator( '#views-count' ) ).toBeVisible();
		await expect( page.locator( '#views-template-template' ) ).toHaveCount( 0 );

		// Reached by clicking, not only by URL: a nav that renders and does not
		// navigate looks identical in a screenshot.
		await page.locator( '.nav-tab', { hasText: 'Templates' } ).click();

		await expect( page.locator( '#views-template-template' ) ).toBeVisible();
		await expect( page.locator( '#views-count' ) ).toHaveCount( 0 );
		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( /Templates/ );
	} );

	test( 'saving one tab leaves the other tab alone', async ( { page } ) => {
		// The trap: the sanitiser is handed only what this form posted, so a
		// Templates save is the moment the counting rows would silently revert.
		await openSettings( page );
		await page.locator( '#views-count' ).selectOption( COUNT.registeredOnly );
		await page.locator( '#views-exclude_bots' ).selectOption( '1' );
		await page.locator( '#views-stats_most_limit' ).fill( '17' );
		await saveSettings( page );

		await openTemplates( page );
		await page.locator( '#views-template-template' ).fill( 'Tabbed %VIEW_COUNT%' );
		await saveSettings( page );

		expect( option( 'template' ) ).toBe( 'Tabbed %VIEW_COUNT%' );
		expect( option( 'count' ) ).toBe( parseInt( COUNT.registeredOnly, 10 ) );
		expect( option( 'exclude_bots' ) ).toBe( 1 );
		expect( option( 'stats_most_limit' ) ).toBe( 17 );
		expect( option( 'stats_display' ) ).toBe( true );

		// And back the other way.
		await openSettings( page );
		await page.locator( '#views-count' ).selectOption( COUNT.everyone );
		await saveSettings( page );

		expect( option( 'count' ) ).toBe( parseInt( COUNT.everyone, 10 ) );
		expect( option( 'template' ) ).toBe( 'Tabbed %VIEW_COUNT%' );
	} );

	test( 'saving comes back to the tab it was saved from, with the notice on it', async ( {
		page,
	} ) => {
		await openTemplates( page );
		await page.locator( '#views-template-template' ).fill( 'Still here %VIEW_COUNT%' );
		await saveSettings( page );

		// settings_errors() prints on this tab, not only on the first one, and
		// the browser is still on Templates rather than back on Settings.
		await expect( page.locator( '.nav-tab-active' ) ).toHaveText( /Templates/ );
		await expect( page.locator( '#views-template-template' ) ).toHaveValue(
			'Still here %VIEW_COUNT%',
		);
		expect( page.url() ).toContain( 'tab=templates' );
	} );

	test( 'the Views Template field stores what is typed into it', async ( { page } ) => {
		await openTemplates( page );

		await page.locator( '#views-template-template' ).fill( '%VIEW_COUNT% reads' );
		await saveSettings( page );

		expect( option( 'template' ) ).toBe( '%VIEW_COUNT% reads' );

		await openTemplates( page );
		await expect( page.locator( '#views-template-template' ) ).toHaveValue( '%VIEW_COUNT% reads' );
	} );

	test( 'the Most Viewed Template textarea stores multi-line markup', async ( { page } ) => {
		const markup = '<li>\n\t<a href="%POST_URL%">%POST_TITLE%</a> (%VIEW_COUNT%)\n</li>';

		await openTemplates( page );
		await page.locator( '#views-template-most_viewed_template' ).fill( markup );
		await saveSettings( page );

		// trim() on the way in is the only thing the sanitiser does to the
		// outside of the value, so the inner newlines and tab must survive.
		//
		// The line endings are normalised on the way out of the comparison, not
		// on the way in: a textarea posts CRLF whatever was typed into it --
		// that is the HTML form specification, not this plugin -- so asserting
		// on LF would be asserting that WordPress rewrites what the browser
		// sent, which it does not and should not.
		expect( option( 'most_viewed_template' ).replace( /\r\n/g, '\n' ) ).toBe( markup );
	} );

	test( 'a template is stored unslashed, so an apostrophe stays an apostrophe', async ( {
		page,
	} ) => {
		// Up to 1.78.1 the screen wrote $_POST through without wp_unslash(), so
		// this arrived as "reader\'s" and every read path had to strip it again.
		await openTemplates( page );
		await page.locator( '#views-template-template' ).fill( "%VIEW_COUNT% reader's views" );
		await saveSettings( page );

		expect( option( 'template' ) ).toBe( "%VIEW_COUNT% reader's views" );
	} );

	test( 'the stats section checkbox turns off and back on', async ( { page } ) => {
		await openSettings( page );
		await expect( page.locator( '#views-stats_display' ) ).toBeChecked();

		// An unticked checkbox posts nothing at all, so the field pairs it with
		// a hidden zero. Without that the sanitiser could not tell "unticked"
		// from "the Templates tab was saved", and the box could be ticked and
		// never unticked.
		await expect(
			page.locator( 'input[type="hidden"][name="wp_postviews_options[stats_display]"]' ),
		).toHaveCount( 1 );

		await page.locator( '#views-stats_display' ).uncheck();
		await saveSettings( page );

		expect( option( 'stats_display' ) ).toBe( false );
		await openSettings( page );
		await expect( page.locator( '#views-stats_display' ) ).not.toBeChecked();

		await page.locator( '#views-stats_display' ).check();
		await saveSettings( page );
		expect( option( 'stats_display' ) ).toBe( true );
	} );

	test( 'the stats section is offered to WP-Stats only while the toggle is on', async () => {
		// Both directions in one test. "No section" on its own passes with the
		// plugin deactivated; the on half is what proves the toggle is what
		// decides rather than the filter never being answered at all.
		setOptions( { stats_display: true } );
		expect( Object.keys( statsSections() ) ).toContain( 'wp_postviews' );

		setOptions( { stats_display: false } );
		expect( Object.keys( statsSections() ) ).not.toContain( 'wp_postviews' );

		// Switched off it contributes *nothing*, rather than an entry with an
		// empty body that WP-Stats would still draw a heading for.
		setOptions( { stats_display: true } );
		expect( statsSections().wp_postviews.title ).toBe( 'Views' );
	} );

	test( 'the offered section carries the total and one listing per post type', async ( {
		requestUtils,
	} ) => {
		await requestUtils.deleteAllPosts();
		await requestUtils.deleteAllPages();
		clearAllViews();

		const post = await requestUtils.createPost( {
			title: uniqueTitle( 'Stats section post' ),
			content: 'Body.',
			status: 'publish',
		} );
		const staticPage = await requestUtils.createPage( {
			title: uniqueTitle( 'Stats section page' ),
			content: 'Body.',
			status: 'publish',
		} );
		setViews( post.id, 30 );
		setViews( staticPage.id, 12 );

		setOptions( { stats_display: true, stats_most_limit: 10 } );

		const body = statsSectionBody();

		// The total is site-wide, and the two listings are the reason the
		// section exists at all -- a heading with nothing under it would look
		// identical on the page.
		expect( body ).toContain( '42' );
		expect( body ).toContain( 'Most Viewed Post' );
		expect( body ).toContain( 'Most Viewed Page' );
		expect( body ).toContain( 'Stats section post' );
		expect( body ).toContain( 'Stats section page' );
	} );

	test( 'the entry limit decides how long each most viewed listing is', async ( {
		requestUtils,
	} ) => {
		await requestUtils.deleteAllPosts();
		clearAllViews();

		for ( let index = 0; index < 3; index++ ) {
			const post = await requestUtils.createPost( {
				title: `Limited stats post ${ index }`,
				content: 'Body.',
				status: 'publish',
			} );
			setViews( post.id, 100 - index );
		}

		setOptions( { stats_display: true, stats_most_limit: 2 } );

		const body = statsSectionBody();

		// Two of the three, and the two with the highest counts: a limit that
		// was read but ignored and one that was never read look the same on a
		// listing that happens to be short.
		expect( body ).toContain( 'Limited stats post 0' );
		expect( body ).toContain( 'Limited stats post 1' );
		expect( body ).not.toContain( 'Limited stats post 2' );
	} );

	test( 'the number of entries in each most viewed list stores a number', async ( { page } ) => {
		await openSettings( page );

		await page.locator( '#views-stats_most_limit' ).fill( '25' );
		await saveSettings( page );

		expect( option( 'stats_most_limit' ) ).toBe( 25 );

		await openSettings( page );
		await expect( page.locator( '#views-stats_most_limit' ) ).toHaveValue( '25' );
	} );

	test( 'a zero entry limit is floored at one rather than stored as zero', async ( { page } ) => {
		await openSettings( page );

		// The field carries min="1", so this goes in past the browser's own
		// validation to reach the server-side floor -- which is the one that
		// matters, because a stored 0 would make every listing empty.
		await page.locator( '#views-stats_most_limit' ).evaluate( ( el ) => {
			el.removeAttribute( 'min' );
			el.value = '0';
		} );
		await saveSettings( page );

		expect( option( 'stats_most_limit' ) ).toBe( 1 );
	} );

	test( 'a choice outside the three the screen offers is clamped rather than stored', async ( {
		page,
	} ) => {
		await openSettings( page );

		// Posted past the select's own options, which is all a hand-built
		// request has to do. The counting switch has three cases and no
		// default, so an out-of-range value stored verbatim would mean nothing
		// is ever counted again.
		await page.locator( '#views-count' ).evaluate( ( el ) => {
			el.insertAdjacentHTML( 'beforeend', '<option value="9">nine</option>' );
			el.value = '9';
		} );
		await saveSettings( page );

		expect( option( 'count' ) ).toBe( 2 );
	} );

	test( 'saving shows the confirmation notice', async ( { page } ) => {
		await openSettings( page );

		// options.php registers "Settings saved." under the general slug, and a
		// settings page that scopes settings_errors() to its own slug filters
		// it out. Its presence is the proof this page does not do that.
		await saveSettings( page );
	} );

	test( 'saving one section leaves the rest of the row alone', async ( { page } ) => {
		setOptions( { template: 'left alone %VIEW_COUNT%', stats_most_limit: 7 } );

		await openSettings( page );
		await page.locator( '#views-count' ).selectOption( COUNT.everyone );
		await saveSettings( page );

		// The sanitiser merges into the stored value rather than replacing it,
		// which is what lets a key this form did not render -- including every
		// key on the other tab -- keep what it had.
		expect( option( 'count' ) ).toBe( 0 );
		expect( option( 'template' ) ).toBe( 'left alone %VIEW_COUNT%' );
		expect( option( 'stats_most_limit' ) ).toBe( 7 );
	} );

	test( 'the screen lists the tokens each template accepts', async ( { page } ) => {
		await openTemplates( page );

		// The token list is the only documentation a site owner has for what
		// may go in these fields, and it is markup in a settings-field title,
		// which is the sort of thing that quietly disappears.
		await expect( page.locator( 'th:has(label[for="views-template-template"]) code' ) ).toHaveText(
			[ '%VIEW_COUNT%', '%VIEW_COUNT_ROUNDED%' ],
		);
		await expect(
			page.locator( 'th:has(label[for="views-template-most_viewed_template"]) code' ),
		).toContainText( [ '%POST_TITLE%' ] );
	} );
} );
