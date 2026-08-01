/**
 * Shared steps for the WP-PostViews end-to-end suite.
 *
 * Three things make this plugin awkward to drive from a browser, and all three
 * are answered here rather than in every spec.
 *
 * The count lives in a post meta row called `views`, not on the page. What a
 * visitor sees is a template with %VIEW_COUNT% substituted into it, so a test
 * that only reads the page cannot tell "the counter incremented" from "the
 * template happened to say 1". Every assertion about counting therefore reads
 * the meta row back through wpEval().
 *
 * The tests environment sets WP_CACHE, which is what decides *how* the count is
 * recorded: with a cache in play the plugin will not count during wp_head --
 * that would only ever record the request that built the cached page -- and
 * enqueues js/wp-postviews-cache.js to post to admin-ajax.php instead. So the
 * default path here is the AJAX one, and waitForCount()/countedViews() exist to
 * wait for it rather than guessing at a delay.
 *
 * And the plugin's front end is template tags. the_views(), get_most_viewed()
 * and friends are what a theme calls; twentytwentyone calls none of them, so
 * nothing this plugin renders would ever appear on a page. installProbe() drops
 * a mu-plugin that calls them from wp_footer, which is the theme integration
 * these tests stand in for.
 */

const { execFileSync } = require( 'child_process' );
const path = require( 'path' );

const { expect } = require( '@wordpress/e2e-test-utils-playwright' );

/** The plugin root, which is where wp-env reads .wp-env.json from. */
const PLUGIN_ROOT = path.join( __dirname, '../..' );

const SETTINGS_URL = '/wp-admin/options-general.php?page=wp-postviews';
const POSTS_URL = '/wp-admin/edit.php';
const PAGES_URL = '/wp-admin/edit.php?post_type=page';

/** Values the "Count Views From:" select stores. */
const COUNT = {
	everyone: '0',
	guestsOnly: '1',
	registeredOnly: '2',
};

/** Values every row of the display matrix stores. */
const DISPLAY = {
	everyone: '0',
	registeredOnly: '1',
	never: '2',
};

/** A user agent from the plugin's own robot list, for the bot exclusion tests. */
const BOT_USER_AGENT = 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)';

/**
 * Run PHP inside the tests environment and hand back what it printed.
 *
 * The code is base64'd rather than passed as itself: the payloads the security
 * spec stores are quotes, angle brackets and a script tag, which is exactly the
 * sort of string that arrives at the other end subtly different, and a fixture
 * that is not the payload byte for byte proves nothing about escaping it.
 *
 * @param {string} code PHP to evaluate, without an opening tag.
 * @return {string} Whatever the code echoed between its markers.
 */
function wpEval( code ) {
	const encoded = Buffer.from( code, 'utf8' ).toString( 'base64' );

	const output = execFileSync(
		'npx',
		[
			'--yes',
			'@wordpress/env',
			'run',
			'tests-cli',
			'wp',
			'eval',
			`eval( base64_decode( '${ encoded }' ) );`,
		],
		{ cwd: PLUGIN_ROOT, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] },
	);

	// wp-env prints its own progress around the command's output, so the code
	// wraps what it wants to return in markers rather than the caller trying to
	// tell the two apart by position.
	const matched = output.match( /<<<([\s\S]*?)>>>/ );

	return matched ? matched[ 1 ] : '';
}

/**
 * Run PHP and read back a JSON value, so types survive the round trip.
 *
 * echo turns true into "1" and false into "", which is the difference these
 * tests care about most for the stats_display checkbox.
 *
 * @param {string} expression PHP expression to encode and return.
 * @return {*} The decoded value.
 */
function wpEvalJson( expression ) {
	return JSON.parse( wpEval( `echo '<<<' . wp_json_encode( ${ expression } ) . '>>>';` ) );
}

/**
 * One setting, as WP_PostViews_Options hands it to the plugin's own code.
 *
 * Read through the accessor rather than get_option() on purpose: the accessor
 * merges the defaults in, which is what every read path in the plugin sees, and
 * a raw read of a key the form has never written would say "absent" where the
 * plugin says "the default".
 *
 * @param {string} key Option key.
 * @return {*} The stored value.
 */
function option( key ) {
	return wpEvalJson( `WP_PostViews_Options::get( '${ key }' )` );
}

/**
 * Write settings straight into the option row.
 *
 * For arranging a precondition, never for asserting one: a setting a test is
 * actually about goes in through the settings screen, so the sanitiser between
 * the form and the row is exercised by the test that depends on it.
 *
 * @param {Object} values Keys to overwrite; everything else keeps its value.
 * @return {void}
 */
function setOptions( values ) {
	const encoded = Buffer.from( JSON.stringify( values ), 'utf8' ).toString( 'base64' );

	wpEval(
		`$values = json_decode( base64_decode( '${ encoded }' ), true );
		WP_PostViews_Options::save( array_merge( WP_PostViews_Options::all(), $values ) );
		echo '<<<done>>>';`,
	);
}

/**
 * Put every setting back to what a fresh install has.
 *
 * @return {void}
 */
function resetOptions() {
	wpEval( 'WP_PostViews_Options::save( WP_PostViews_Options::defaults() ); echo \'<<<done>>>\';' );
}

/**
 * The view count stored against a post.
 *
 * @param {number} postId Post to read.
 * @return {number} The stored count.
 */
function views( postId ) {
	return parseInt( wpEval( `echo '<<<' . (int) get_post_meta( ${ postId }, 'views', true ) . '>>>';` ), 10 );
}

/**
 * Set a post's view count, so a listing has something to order by.
 *
 * @param {number} postId Post to write.
 * @param {number} count  Count to store.
 * @return {void}
 */
function setViews( postId, count ) {
	wpEval( `update_post_meta( ${ postId }, 'views', ${ count } ); echo '<<<done>>>';` );
}

/**
 * The mu-plugin that stands in for a theme's calls to the template tags.
 *
 * Everything WP-PostViews renders on a front end comes from a template tag a
 * theme is expected to call. twentytwentyone calls none of them, so without
 * this the front end has nothing of the plugin's on it at all and the display
 * matrix -- which only the_views() consults -- could not be tested.
 *
 * wp_footer rather than the_content, because it fires on every kind of page:
 * the matrix has a separate setting for home, single, page, archive and search,
 * and the_content does not run on all of them.
 *
 * Two probes for the same tag on purpose. #pv-views is the ordinary call, which
 * the matrix can blank; #pv-views-always passes $always, which is how the admin
 * column calls it. The element is emitted either way, so a test can tell "the
 * matrix suppressed the count" from "the theme call never happened" -- which an
 * absent element cannot.
 */
const PROBE_SOURCE = `<?php
/**
 * Plugin Name: WP-PostViews E2E probe
 * Description: Calls the template tags from wp_footer, standing in for a theme.
 */
add_action(
	'wp_footer',
	function () {
		echo '<div id="pv-views">' . the_views( false ) . '</div>';
		echo '<div id="pv-views-always">' . the_views( false, '', '', true ) . '</div>';
		echo '<div id="pv-views-fix">' . the_views( false, '[', ']', true ) . '</div>';
		echo '<div id="pv-total">' . get_totalviews( false ) . '</div>';
		echo '<ul id="pv-most">' . get_most_viewed( '', 5, 0, false ) . '</ul>';
		echo '<ul id="pv-most-posts">' . get_most_viewed( 'post', 5, 0, false ) . '</ul>';
		echo '<ul id="pv-most-chars">' . get_most_viewed( '', 5, 12, false ) . '</ul>';
		echo '<ul id="pv-least">' . get_least_viewed( '', 5, 0, false ) . '</ul>';
		echo '<ul id="pv-most-cat">' . get_most_viewed_category( (int) get_option( 'pv_e2e_cat', 0 ), '', 5, 0, false ) . '</ul>';
		echo '<ul id="pv-least-cat">' . get_least_viewed_category( (int) get_option( 'pv_e2e_cat', 0 ), '', 5, 0, false ) . '</ul>';
		echo '<ul id="pv-most-tag">' . get_most_viewed_tag( (int) get_option( 'pv_e2e_tag', 0 ), '', 5, 0, false ) . '</ul>';
		echo '<ul id="pv-least-tag">' . get_least_viewed_tag( (int) get_option( 'pv_e2e_tag', 0 ), '', 5, 0, false ) . '</ul>';
	},
	5
);
`;

/**
 * Install the probe mu-plugin.
 *
 * @return {void}
 */
function installProbe() {
	const encoded = Buffer.from( PROBE_SOURCE, 'utf8' ).toString( 'base64' );

	wpEval(
		`if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			mkdir( WPMU_PLUGIN_DIR, 0777, true );
		}
		file_put_contents( WPMU_PLUGIN_DIR . '/wp-postviews-e2e-probe.php', base64_decode( '${ encoded }' ) );
		echo '<<<done>>>';`,
	);
}

/**
 * Remove the probe mu-plugin and the two options it reads.
 *
 * @return {void}
 */
function removeProbe() {
	wpEval(
		`$file = WPMU_PLUGIN_DIR . '/wp-postviews-e2e-probe.php';
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
		delete_option( 'pv_e2e_cat' );
		delete_option( 'pv_e2e_tag' );
		echo '<<<done>>>';`,
	);
}

/**
 * Point the probe's "by category" and "by tag" listings at a term.
 *
 * @param {string} which  Either 'cat' or 'tag'.
 * @param {number} termId Term to scope the listing to.
 * @return {void}
 */
function probeTerm( which, termId ) {
	wpEval( `update_option( 'pv_e2e_${ which }', ${ termId } ); echo '<<<done>>>';` );
}

/**
 * Delete every `views` meta row.
 *
 * The listings and the total are site-wide, so a spec that asserts on either
 * has to start from a known set of counts rather than from whatever the last
 * run left behind.
 *
 * @return {void}
 */
function clearAllViews() {
	wpEval(
		`global $wpdb;
		$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => 'views' ) );
		echo '<<<done>>>';`,
	);
}

/**
 * The mu-plugin that answers the wp_postviews_should_count filter with false.
 *
 * The filter is the plugin's documented way for a site to have the last word on
 * whether a request counts, and there is no screen for it -- so the only way to
 * exercise it from a browser is to put a listener in place the way a site owner
 * would.
 */
const VETO_SOURCE = `<?php
/**
 * Plugin Name: WP-PostViews E2E count veto
 * Description: Answers wp_postviews_should_count with false, for one test.
 */
add_filter( 'wp_postviews_should_count', '__return_false', 99 );
`;

/**
 * Install the mu-plugin that vetoes counting.
 *
 * @return {void}
 */
function installCountVeto() {
	const encoded = Buffer.from( VETO_SOURCE, 'utf8' ).toString( 'base64' );

	wpEval(
		`if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
			mkdir( WPMU_PLUGIN_DIR, 0777, true );
		}
		file_put_contents( WPMU_PLUGIN_DIR . '/wp-postviews-e2e-veto.php', base64_decode( '${ encoded }' ) );
		echo '<<<done>>>';`,
	);
}

/**
 * Remove it again.
 *
 * @return {void}
 */
function removeCountVeto() {
	wpEval(
		`$file = WPMU_PLUGIN_DIR . '/wp-postviews-e2e-veto.php';
		if ( file_exists( $file ) ) {
			unlink( $file );
		}
		echo '<<<done>>>';`,
	);
}

/**
 * Whether any postmeta row exists for a post id under the views key.
 *
 * Different from views(), which reads 0 for both "counted zero times" and "no
 * row at all". The AJAX endpoint's guard against walking the id space is
 * exactly about the second one.
 *
 * @param {number} postId Post id to look for.
 * @return {number} How many rows there are.
 */
function viewsRowCount( postId ) {
	return parseInt(
		wpEval(
			`global $wpdb;
			echo '<<<' . (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = 'views'", ${ postId } ) ) . '>>>';`,
		),
		10,
	);
}

/**
 * Attach a featured image to a post, so the thumbnail tokens have something to
 * substitute.
 *
 * Built server-side rather than uploaded from here: the tokens only need an
 * attachment the post points at, and a real file on this machine would be one
 * more thing for the suite to carry around.
 *
 * @param {number} postId Post to give a thumbnail to.
 * @return {void}
 */
function setThumbnail( postId ) {
	wpEval(
		`$uploads = wp_upload_dir();
		$path    = trailingslashit( $uploads['path'] ) . 'wp-postviews-e2e.png';
		// The shortest legal PNG there is: one transparent pixel.
		file_put_contents( $path, base64_decode( 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==' ) );
		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => 'WP-PostViews E2E pixel',
				'post_status'    => 'inherit',
			),
			$path,
			${ postId }
		);
		set_post_thumbnail( ${ postId }, $attachment_id );
		echo '<<<' . $attachment_id . '>>>';`,
	);
}

/**
 * Put one Views widget in twentytwentyone's sidebar.
 *
 * Straight into widget_views and sidebars_widgets rather than through the
 * widgets screen: that screen is the block editor's Legacy Widget wrapper,
 * driving which would test Gutenberg rather than this plugin. The instance and
 * the sidebar entry are exactly what the screen would have written, and the
 * widget's own form is covered by tests/test-widget.php.
 *
 * Always slot 9, so a second call replaces the widget rather than stacking
 * another one beside it.
 *
 * @param {Object} instance Widget settings: title, type, mode, limit, chars, cat_ids.
 * @return {void}
 */
function addViewsWidget( instance ) {
	const encoded = Buffer.from( JSON.stringify( instance ), 'utf8' ).toString( 'base64' );

	wpEval(
		`$instance = json_decode( base64_decode( '${ encoded }' ), true );
		update_option( 'widget_views', array( 9 => $instance, '_multiwidget' => 1 ) );
		$sidebars = (array) get_option( 'sidebars_widgets', array() );
		$sidebars['sidebar-1'] = array( 'views-9' );
		update_option( 'sidebars_widgets', $sidebars );
		echo '<<<done>>>';`,
	);
}

/**
 * Empty the sidebar again.
 *
 * @return {void}
 */
function removeViewsWidget() {
	wpEval(
		`delete_option( 'widget_views' );
		$sidebars = (array) get_option( 'sidebars_widgets', array() );
		$sidebars['sidebar-1'] = array();
		update_option( 'sidebars_widgets', $sidebars );
		echo '<<<done>>>';`,
	);
}

/**
 * Open the settings screen.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once the screen is up.
 */
async function openSettings( page ) {
	await page.goto( SETTINGS_URL );

	await expect( page.getByRole( 'heading', { name: 'Post Views Options' } ) ).toBeVisible();
}

/**
 * Save the settings form and wait for WordPress to confirm it.
 *
 * @param {import('@playwright/test').Page} page Page under test.
 * @return {Promise<void>} Resolves once "Settings saved." is on screen.
 */
async function saveSettings( page ) {
	await page.getByRole( 'button', { name: 'Save Changes' } ).click();

	// The notice rather than the redirect: options.php sends the browser back
	// whether or not anything was written, so arriving here again says nothing.
	await expect( page.locator( '.settings-error, .notice-success' ).first() ).toContainText(
		'Settings saved.',
	);
}

/**
 * Do something in a browser nobody is logged in to.
 *
 * The suite's own page carries the administrator session, and "Guests Only" --
 * which is the shipped default for who gets counted -- means that page is
 * exactly the one request the plugin will not count. Anything about a visitor
 * needs a context of its own.
 *
 * @param {import('@playwright/test').Page} page    Page under test, for its browser.
 * @param {Object}                          options Extra newContext options, e.g. a user agent.
 * @param {Function}                        run     Called with the guest page.
 * @return {Promise<void>} Resolves once the guest context has been closed.
 */
async function asGuest( page, options, run ) {
	const context = await page.context().browser().newContext( {
		...options,
		storageState: undefined,
	} );

	try {
		await run( await context.newPage() );
	} finally {
		await context.close();
	}
}

/**
 * Arrange to hear about the AJAX count before the page has loaded.
 *
 * js/wp-postviews-cache.js dispatches postviews:updated on document once
 * admin-ajax.php has answered. Registering the listener from an init script is
 * the only way to be sure it is in place before the script runs; a listener
 * added after page.goto() has resolved is racing the fetch.
 *
 * @param {import('@playwright/test').Page} target Page that is about to navigate.
 * @return {Promise<void>} Resolves once the init script is registered.
 */
function watchForCount( target ) {
	return target.addInitScript( () => {
		window.__postviewsCounted = new Promise( ( resolve, reject ) => {
			document.addEventListener( 'postviews:updated', ( event ) => resolve( event.detail ) );
			setTimeout( () => reject( new Error( 'postviews:updated never fired' ) ), 15000 );
		} );
	} );
}

/**
 * The count admin-ajax.php sent back, once it has.
 *
 * @param {import('@playwright/test').Page} target Page watchForCount() was called on.
 * @return {Promise<number>} The new view count.
 */
async function countedViews( target ) {
	const detail = await target.evaluate( () => window.__postviewsCounted );

	return detail.views;
}

/**
 * The counting script, which is only enqueued for a request that will be
 * counted.
 *
 * Its absence is the deterministic signal that the plugin decided not to count
 * this visitor -- far better than waiting to see whether a number fails to
 * change, which passes just as well when the request simply has not arrived
 * yet.
 *
 * @param {import('@playwright/test').Page} target Page to look at.
 * @return {import('@playwright/test').Locator} The script element, if any.
 */
function counterScript( target ) {
	return target.locator( 'script[src*="wp-postviews-cache.js"]' );
}

/**
 * A title no earlier run can have used.
 *
 * @param {string} base What the title should say.
 * @return {string} That, plus enough to tell this run from the last.
 */
function uniqueTitle( base ) {
	return `${ base } ${ Date.now().toString( 36 ) }`;
}

module.exports = {
	BOT_USER_AGENT,
	COUNT,
	DISPLAY,
	PAGES_URL,
	POSTS_URL,
	SETTINGS_URL,
	addViewsWidget,
	asGuest,
	clearAllViews,
	countedViews,
	counterScript,
	installCountVeto,
	installProbe,
	openSettings,
	option,
	probeTerm,
	removeCountVeto,
	removeProbe,
	removeViewsWidget,
	resetOptions,
	saveSettings,
	setOptions,
	setThumbnail,
	setViews,
	uniqueTitle,
	views,
	viewsRowCount,
	watchForCount,
	wpEval,
	wpEvalJson,
};
