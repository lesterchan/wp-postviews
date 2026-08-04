/**
 * The pre-2.0.0 migration, run the way a real site runs it.
 *
 * Activation does not fire when a plugin is merely updated -- a site that
 * updates from the Plugins screen never calls activate() -- so maybe_upgrade()
 * hangs off `init` at priority 1 instead. That is deliberate and it is what
 * makes this file different from its siblings': the migration does not wait for
 * an administrator to load a screen, because until it has run the plugin is
 * reading defaults over a row nothing has written and every *visitor* would be
 * looking at a stock template in the meantime. So the first test here drives it
 * from the front end, which no other suite in the collection can do.
 *
 * Every row is read *raw*. WP_PostViews_Options::all() merges over the
 * defaults, so it answers identically for a row holding the defaults and for no
 * row at all -- which is the §7.6.1 failure exactly: a set of rows read, deleted
 * and never written. Ask the database, not the plugin.
 *
 * The fixtures are the *shipped* settings rather than customised ones. A
 * customised row's migrated result differs from the defaults, so its write lands
 * whatever the read before it did.
 */

const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );
const {
	SETTINGS_URL,
	defaultOptions,
	installLegacyRows,
	installProbe,
	rawOptions,
	removeProbe,
	resetOptions,
	runningVersions,
	setVersionRow,
	survivingLegacyRows,
	uniqueTitle,
	versionRow,
	wpEval,
} = require( './helpers.js' );

/** The Dashboard: an ordinary admin request, which is what an update goes through. */
const DASHBOARD_URL = '/wp-admin/index.php';

/**
 * The settings row a stock 1.78.1 install carried.
 *
 * Built from the running defaults rather than transcribed, minus the two keys
 * 2.0.0 added for the WP-Stats settings this plugin used to read out of rows it
 * shared with six siblings.
 *
 * @param {Object} overrides Anything this particular site had changed.
 * @return {Object} A legacy settings row.
 */
function stockLegacyOptions( overrides = {} ) {
	const legacy = defaultOptions();

	delete legacy.stats_display;
	delete legacy.stats_most_limit;

	return { ...legacy, ...overrides };
}

test.describe( 'The pre-2.0.0 upgrade', () => {
	test.afterEach( async () => {
		// Back to a current install: markers stamped, settings at a fresh
		// install's, no legacy rows anywhere. Every other spec in this suite
		// starts from that, and this is the only file that takes it apart.
		wpEval(
			`delete_option( WP_PostViews_Options::LEGACY_OPTION );
			delete_option( WP_PostViews_Options::LEGACY_VERSION );
			delete_option( WP_PostViews_Options::LEGACY_STATS_DISPLAY );
			delete_option( WP_PostViews_Options::LEGACY_STATS_MOST_LIMIT );
			echo '<<<done>>>';`,
		);
		setVersionRow( runningVersions() );
		resetOptions();
	} );

	test( 'a front-end request migrates a stock 1.78.1 install', async ( { page } ) => {
		// The fixture is asserted from what the seeding call itself saw, not from
		// a second one. maybe_upgrade() runs on `init`, which a WP-CLI request
		// reaches too -- ask again through another `wp eval` and the rows have
		// already moved, and the request below would have nothing left to do.
		const before = installLegacyRows( stockLegacyOptions() );

		expect( before.legacy ).toContain( 'views_options' );
		expect( before.options ).toBe( false );
		expect( before.version ).toBe( false );

		// The front end, not the Dashboard. Hooking `init` rather than
		// admin_init is what stops a site's visitors seeing the stock template
		// until somebody happens to log in, and it is the one thing about this
		// migration that no other suite in the collection can assert.
		await page.goto( '/' );

		const stored = rawOptions();

		// Written, not merely readable through the defaults.
		expect( stored ).not.toBe( false );
		expect( stored.template ).toBe( defaultOptions().template );

		// Both old rows gone rather than left to rot, and both markers stamped
		// in one write.
		expect( survivingLegacyRows() ).not.toContain( 'views_options' );
		expect( survivingLegacyRows() ).not.toContain( 'views_version' );
		expect( versionRow() ).toEqual( runningVersions() );
	} );

	test( 'a customised row keeps its template, and it reaches the page', async ( {
		page,
		requestUtils,
	} ) => {
		const template = '%VIEW_COUNT% reads of this post';

		installLegacyRows( stockLegacyOptions( { template, count: 0 } ) );

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		expect( stored.template ).toBe( template );
		expect( stored.count ).toBe( 0 );

		// Present is not alive. The migrated template has to be the one a
		// visitor is shown -- through the probe mu-plugin, which stands in for a
		// theme calling the_views().
		installProbe();

		const post = await requestUtils.createPost( {
			title: uniqueTitle( 'Read after the upgrade' ),
			content: 'Body.',
			status: 'publish',
		} );

		await page.goto( post.link );

		await expect( page.locator( '#pv-views' ) ).toContainText( 'reads of this post' );

		removeProbe();
	} );

	test( 'a template stored slashed is unslashed exactly once', async ( { page } ) => {
		// Up to 1.78.1 the settings screen wrote $_POST straight through without
		// wp_unslash(), so a template holding an apostrophe was stored with a
		// backslash in front of it and every read path stripped one back off.
		// 2.0.0 unslashes on save, so the rows already on disk have to be
		// corrected once -- and only once, because a template holding a Windows
		// path would otherwise lose its separator on the second pass.
		installLegacyRows(
			stockLegacyOptions( { template: "It\\'s been viewed %VIEW_COUNT% times" } ),
			{ version: '1.78.1' },
		);

		await page.goto( DASHBOARD_URL );

		expect( rawOptions().template ).toBe( "It's been viewed %VIEW_COUNT% times" );
	} );

	test( 'a row an earlier 2.0.0 build wrote is not unslashed a second time', async ( {
		page,
	} ) => {
		// The other half of the rule above, and the reason the unslashing is
		// gated on the legacy version marker rather than on "does this look
		// slashed". An install that ran a development 2.0.0 already stores its
		// templates unslashed, so a backslash in one of those is a backslash the
		// owner typed -- a Windows path, in the case this was written for -- and
		// stripping it a second time is the bug the gate exists to prevent.
		//
		// Held in a constant and asserted against itself, because the claim is
		// that nothing touched it. Two hand-escaped copies of a string this full
		// of backslashes are two chances to write the escaping differently and
		// then read the difference as a finding -- which is exactly what the
		// first run of this test did.
		const windowsPath = 'Path C:\\\\views\\\\%VIEW_COUNT%';

		installLegacyRows( stockLegacyOptions( { template: windowsPath } ), {
			version: '2.0.0',
		} );

		await page.goto( DASHBOARD_URL );

		expect( rawOptions().template ).toBe( windowsPath );
	} );

	test( "this plugin's share of the WP-Stats rows is folded in and the rows deleted", async ( {
		page,
	} ) => {
		// The two rows as the last of the seven plugins to save the WP-Stats
		// screen left them: a flag per plugin, and a shared row saying how many
		// entries a "most" list carries.
		installLegacyRows( stockLegacyOptions(), {
			statsDisplay: { postviews: 0, polls: 1 },
			statsMostLimit: 4,
		} );

		await page.goto( DASHBOARD_URL );

		const stored = rawOptions();

		expect( stored.stats_display ).toBe( false );
		expect( stored.stats_most_limit ).toBe( 4 );

		// Deleted by the migration that folded them in -- and by nothing else.
		// §13.2 splits the two jobs: uninstall must leave them alone, because up
		// to six siblings that have not upgraded are still reading them.
		expect( survivingLegacyRows() ).not.toContain( 'stats_display' );
		expect( survivingLegacyRows() ).not.toContain( 'stats_mostlimit' );
	} );

	test( 'an absent shared row means on, not off', async ( { page } ) => {
		// A sibling upgraded first and took the row with it. Reading that as a
		// deliberate opt-out would take this plugin's section off the WP-Stats
		// page of any site that updated a sibling first, with nothing to say why.
		const before = installLegacyRows( stockLegacyOptions() );

		expect( before.legacy ).not.toContain( 'stats_display' );

		await page.goto( DASHBOARD_URL );

		expect( rawOptions().stats_display ).toBe( true );
	} );

	test( 'an install already on this version is left alone', async ( { page } ) => {
		// A legacy row that should never be read, alongside markers saying the
		// upgrade has already happened. maybe_upgrade() returning early is what
		// keeps every request from being an option write, and the proof it
		// returned early is that this row survives untouched.
		// Stamped in the same call that writes the row: with the markers already
		// current, the WP-CLI request doing the writing cannot migrate it on the
		// way in, and neither can the browser.
		wpEval(
			`update_option( WP_PostViews_Options::VERSION, array(
				'plugin' => WP_POSTVIEWS_VERSION,
				'db'     => WP_POSTVIEWS_DB_VERSION,
			) );
			update_option( WP_PostViews_Options::LEGACY_OPTION, array( 'template' => 'Never read' ) );
			echo '<<<done>>>';`,
		);

		await page.goto( DASHBOARD_URL );

		expect( survivingLegacyRows() ).toContain( 'views_options' );
		expect( rawOptions().template ).not.toBe( 'Never read' );
	} );

	test( 'the settings screen is reachable after all of it', async ( { page } ) => {
		await page.goto( SETTINGS_URL );

		await expect( page.getByRole( 'heading', { name: 'Post Views Settings' } ) ).toBeVisible();
	} );
} );
