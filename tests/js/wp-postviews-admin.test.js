/**
 * Tests for the settings screen script.
 *
 * One delegated listener on document handles every "Restore Default Template"
 * button, so the script is loaded into a page once and driven with real click
 * events rather than by calling anything.
 */
import { beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { adminL10n, loadScript, templateRow } from './helper-dom.js';

describe( 'wp-postviews settings screen', () => {
	beforeAll( () => {
		window.wpPostViewsL10n = adminL10n();

		loadScript( 'js/wp-postviews-admin.js' );
	} );

	beforeEach( () => {
		document.body.innerHTML =
			'<form>' +
			templateRow( 'template', 'views-template-template', 'CUSTOM VIEWS' ) +
			templateRow(
				'most_viewed_template',
				'views-template-most_viewed_template',
				'CUSTOM LISTING',
			) +
			'</form>';
	} );

	/**
	 * Click an element by selector.
	 *
	 * @param {string} selector CSS selector.
	 * @return {Element} The element clicked.
	 */
	function click( selector ) {
		const el = document.querySelector( selector );

		el.dispatchEvent(
			new window.MouseEvent( 'click', { bubbles: true, cancelable: true } ),
		);

		return el;
	}

	it( 'restores the default single post template', () => {
		click( '[data-postviews-reset="template"]' );

		expect( document.getElementById( 'views-template-template' ).value ).toBe(
			'%VIEW_COUNT% views',
		);
	} );

	it( 'restores the default most viewed template', () => {
		click( '[data-postviews-reset="most_viewed_template"]' );

		expect(
			document.getElementById( 'views-template-most_viewed_template' ).value,
		).toBe(
			'<li><a href="%POST_URL%">%POST_TITLE%</a> - %VIEW_COUNT% views</li>',
		);
	} );

	it( 'restores only the field the button names', () => {
		click( '[data-postviews-reset="template"]' );

		expect(
			document.getElementById( 'views-template-most_viewed_template' ).value,
		).toBe( 'CUSTOM LISTING' );
	} );

	it( 'leaves the field alone for an option key with no default', () => {
		const button = document.querySelector( '[data-postviews-reset="template"]' );
		button.dataset.postviewsReset = 'no_such_key';

		click( '[data-postviews-reset="no_such_key"]' );

		expect( document.getElementById( 'views-template-template' ).value ).toBe(
			'CUSTOM VIEWS',
		);
	} );

	it( 'leaves the field alone when the target does not exist', () => {
		const button = document.querySelector( '[data-postviews-reset="template"]' );
		button.dataset.postviewsTarget = 'views-template-missing';

		click( '[data-postviews-reset="template"]' );

		expect( document.getElementById( 'views-template-template' ).value ).toBe(
			'CUSTOM VIEWS',
		);
	} );

	it( 'ignores a click anywhere else on the form', () => {
		click( 'form' );

		expect( document.getElementById( 'views-template-template' ).value ).toBe(
			'CUSTOM VIEWS',
		);
	} );

	it( 'restores from a click on something inside the button', () => {
		const button = document.querySelector( '[data-postviews-reset="template"]' );
		button.innerHTML = '<span>Restore Default Template</span>';

		click( '[data-postviews-reset="template"] span' );

		expect( document.getElementById( 'views-template-template' ).value ).toBe(
			'%VIEW_COUNT% views',
		);
	} );

	it( 'writes the default in verbatim, without escaping the markup', () => {
		click( '[data-postviews-reset="most_viewed_template"]' );

		// The default arrives as data from wp_localize_script() rather than in an
		// attribute, which is the whole reason 2.0.0 stopped building an inline
		// onclick: the value never passes through a JavaScript string literal, so
		// there is no escaping step to get wrong.
		expect(
			document.getElementById( 'views-template-most_viewed_template' ).value,
		).toContain( '<a href="%POST_URL%">' );
	} );
} );
