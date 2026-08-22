/**
 * Shared helpers for the script tests.
 */
import { readFileSync } from 'node:fs';

/**
 * Evaluate one of the plugin's scripts in the current jsdom page.
 *
 * Each script is an IIFE with no exports, so it is loaded the way a browser
 * would rather than imported. The l10n object has to exist on window *before*
 * this runs: both scripts read it as they evaluate.
 *
 * The admin script attaches a delegated listener to document, so evaluate it
 * once per test file -- a second evaluation adds a second listener and every
 * handler then fires twice. The cache script does its work immediately and
 * attaches nothing, so that one is evaluated once per test.
 *
 * @param {string} name Path relative to the plugin root.
 */
export function loadScript( name ) {
	const src = readFileSync( new URL( '../../' + name, import.meta.url ), 'utf8' );

	new Function( src )();
}

/**
 * The localisation object wp_localize_script() puts on the settings screen.
 *
 * @return {Object} l10n object.
 */
export function adminL10n() {
	return {
		defaults: {
			template: '%VIEW_COUNT% views',
			most_viewed_template:
				'<li><a href="%POST_URL%">%POST_TITLE%</a> - %VIEW_COUNT% views</li>',
		},
	};
}

/**
 * The localisation object wp_localize_script() puts on a cached front end page.
 *
 * @return {Object} l10n object.
 */
export function cacheL10n() {
	return {
		ajaxUrl: 'https://example.com/wp-admin/admin-ajax.php',
		nonce: 'abc123',
		postId: 42,
	};
}

/**
 * The markup WP_PostViews_Settings::field_template() emits for one row.
 *
 * The pairing between the button's two data attributes and the field id is the
 * whole contract between the PHP and the script, so the fixture spells it out
 * rather than reaching for something shorter.
 *
 * @param {string} key  Option key the button restores.
 * @param {string} id   Id of the field it writes into.
 * @param {string} text Current contents of that field.
 * @return {string} Markup.
 */
export function templateRow( key, id, text ) {
	return (
		'<input type="text" id="' + id + '" value="' + text + '" />' +
		'<button type="button" class="button" data-postviews-reset="' + key + '"' +
		' data-postviews-target="' + id + '">Restore Default Template</button>'
	);
}
