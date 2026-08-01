<?php
/**
 * WP_PostViews_Options: the defaults, the accessors and the runtime cache.
 *
 * The 2.0.0 migration and the two upgrade markers have their own file.
 *
 * @package WP-PostViews
 */

/**
 * Option storage.
 */
class WP_PostViews_Options_Test extends WP_PostViews_TestCase {

	/**
	 * Every documented key has a default.
	 *
	 * @return void
	 */
	public function test_defaults_cover_every_key() {
		$defaults = WP_PostViews_Options::defaults();

		foreach ( array( 'count', 'exclude_bots', 'use_ajax', 'template', 'most_viewed_template', 'stats_display', 'stats_most_limit' ) as $key ) {
			$this->assertArrayHasKey( $key, $defaults );
		}
	}

	/**
	 * A retired key has no default, and is dropped by every write path.
	 *
	 * The six display_* keys were a per-context matrix; the gate is the
	 * wp_postviews_should_display filter now. A site upgrading carries them in
	 * its stored row, and save() is the path the 2.0.0 migration writes through,
	 * so they have to go there rather than only in the Settings API sanitiser --
	 * which is not even attached until 'admin_init'.
	 *
	 * @return void
	 */
	public function test_retired_keys_are_not_stored() {
		$defaults = WP_PostViews_Options::defaults();

		foreach ( WP_PostViews_Options::retired_keys() as $key ) {
			$this->assertArrayNotHasKey( $key, $defaults, $key . ' is retired and must have no default.' );
		}

		WP_PostViews_Options::save(
			array_merge(
				array_fill_keys( WP_PostViews_Options::retired_keys(), 2 ),
				array( 'template' => 'KEPT' )
			)
		);

		$stored = get_option( WP_PostViews_Options::OPTION );

		foreach ( WP_PostViews_Options::retired_keys() as $key ) {
			$this->assertArrayNotHasKey( $key, $stored, $key . ' must not survive a write.' );
		}

		$this->assertSame( 'KEPT', $stored['template'], 'Dropping the retired keys must not disturb the rest.' );
	}

	/**
	 * Every retired key really is one of the six display rows.
	 *
	 * @return void
	 */
	public function test_the_retired_keys_are_the_six_display_rows() {
		$this->assertSame(
			array( 'display_home', 'display_single', 'display_page', 'display_archive', 'display_search', 'display_other' ),
			WP_PostViews_Options::retired_keys()
		);
	}

	/**
	 * The WP-Stats block is on by default, and its limit is a sensible number.
	 *
	 * On, because the shared row it replaces defaulted to on. Shipping it off
	 * would take the views block away from every site that upgrades.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_block_defaults_to_on() {
		$defaults = WP_PostViews_Options::defaults();

		$this->assertTrue( $defaults['stats_display'], 'The WP-Stats section must be offered by default.' );
		$this->assertSame( 10, $defaults['stats_most_limit'] );
	}

	/**
	 * The settings row holds no upgrade marker, ever.
	 *
	 * The markers live in wp_postviews_version. A key called version, db_version
	 * or versions in here is the bug §2.1 was written to make impossible.
	 *
	 * @return void
	 */
	public function test_the_settings_row_carries_no_upgrade_marker() {
		foreach ( array( 'version', 'db_version', 'versions' ) as $key ) {
			$this->assertArrayNotHasKey( $key, WP_PostViews_Options::defaults(), $key . ' belongs in the marker row.' );
		}
	}

	/**
	 * The stored row is merged over the defaults, not swapped for them.
	 *
	 * @return void
	 */
	public function test_partial_row_is_merged_over_defaults() {
		update_option( WP_PostViews_Options::OPTION, array( 'count' => 2 ) );

		$this->assertSame( 2, WP_PostViews_Options::get_int( 'count' ) );
		$this->assertSame(
			WP_PostViews_Options::default_template( 'template' ),
			WP_PostViews_Options::get( 'template' ),
			'A key absent from the stored row should fall back to its default.'
		);
	}

	/**
	 * A corrupt row must not fatal.
	 *
	 * @return void
	 */
	public function test_non_array_row_falls_back_to_defaults() {
		update_option( WP_PostViews_Options::OPTION, 'not an array' );

		$this->assertSame( WP_PostViews_Options::defaults(), WP_PostViews_Options::all() );
	}

	/**
	 * A write from outside the plugin must be visible immediately.
	 *
	 * Every read went straight to get_option() before 2.0.0, so anything that
	 * wrote the row - another plugin, WP-CLI - took effect in the same request.
	 * The runtime cache added in 2.0.0 would break that without invalidation,
	 * and it did until the Playground audit caught it.
	 *
	 * @return void
	 */
	public function test_external_write_invalidates_the_cache() {
		$this->assertSame( 1, WP_PostViews_Options::get_int( 'count' ), 'Priming the cache.' );

		update_option( WP_PostViews_Options::OPTION, array_merge( WP_PostViews_Options::all(), array( 'count' => 2 ) ) );

		$this->assertSame( 2, WP_PostViews_Options::get_int( 'count' ) );
	}

	/**
	 * An add_option() call must invalidate too, for a site whose row was deleted.
	 *
	 * @return void
	 */
	public function test_add_option_invalidates_the_cache() {
		delete_option( WP_PostViews_Options::OPTION );
		WP_PostViews_Options::flush();
		WP_PostViews_Options::all();

		add_option( WP_PostViews_Options::OPTION, array( 'count' => 2 ) );

		$this->assertSame( 2, WP_PostViews_Options::get_int( 'count' ) );
	}

	/**
	 * Activation seeds the row without clobbering an existing one.
	 *
	 * @return void
	 */
	public function test_install_does_not_overwrite_existing_settings() {
		$this->set_options( array( 'count' => 2 ) );

		WP_PostViews_Options::install();

		$this->assertSame( 2, WP_PostViews_Options::get_int( 'count' ) );
	}

	/**
	 * Saving replaces the row and refills any missing key from the defaults.
	 *
	 * @return void
	 */
	public function test_save_replaces_and_backfills() {
		WP_PostViews_Options::save( array( 'count' => 2 ) );

		$this->assertSame( 2, WP_PostViews_Options::get_int( 'count' ) );
		$this->assertSame( WP_PostViews_Options::default_template( 'template' ), WP_PostViews_Options::get( 'template' ) );
		$this->assertSame( 2, (int) get_option( WP_PostViews_Options::OPTION )['count'] );
	}

	/**
	 * The accessor returns the supplied fallback for a key that does not exist.
	 *
	 * @return void
	 */
	public function test_get_returns_the_fallback_for_an_unknown_key() {
		$this->assertSame( 'fallback', WP_PostViews_Options::get( 'no_such_key', 'fallback' ) );
		$this->assertNull( WP_PostViews_Options::get( 'no_such_key' ) );
	}

	/**
	 * The integer accessor coerces whatever is stored, including junk.
	 *
	 * Values arrive as strings from the settings form and as integers from
	 * activation, and the counter compares them with ===.
	 *
	 * @return void
	 */
	public function test_get_int_coerces() {
		$this->set_options( array( 'count' => '2' ) );
		$this->assertSame( 2, WP_PostViews_Options::get_int( 'count' ) );

		$this->set_options( array( 'count' => 'nonsense' ) );
		$this->assertSame( 0, WP_PostViews_Options::get_int( 'count' ) );

		$this->assertSame( 0, WP_PostViews_Options::get_int( 'no_such_key' ) );
	}

	/**
	 * Both templates have a non-empty default.
	 *
	 * @return void
	 */
	public function test_default_templates_are_present() {
		foreach ( array( 'template', 'most_viewed_template' ) as $key ) {
			$default = WP_PostViews_Options::default_template( $key );

			$this->assertNotSame( '', $default );
			$this->assertStringContainsString( '%VIEW_COUNT%', $default );
		}

		$this->assertStringContainsString( '%POST_URL%', WP_PostViews_Options::default_template( 'most_viewed_template' ) );
	}

	/**
	 * An unknown template key falls back to the single post template rather
	 * than returning nothing.
	 *
	 * @return void
	 */
	public function test_default_template_falls_back() {
		$this->assertSame(
			WP_PostViews_Options::default_template( 'template' ),
			WP_PostViews_Options::default_template( 'anything_else' )
		);
	}
}
