<?php
/**
 * PostViews_Options: defaults, the runtime cache, and the 2.0.0 migration.
 *
 * @package WP-PostViews
 */

/**
 * Option storage.
 */
class Test_PostViews_Options extends PostViews_TestCase {

	/**
	 * Every documented key has a default.
	 *
	 * @return void
	 */
	public function test_defaults_cover_every_key() {
		$defaults = PostViews_Options::defaults();

		foreach ( array( 'count', 'exclude_bots', 'use_ajax', 'template', 'most_viewed_template' ) as $key ) {
			$this->assertArrayHasKey( $key, $defaults );
		}
		foreach ( $this->display_keys as $key ) {
			$this->assertArrayHasKey( $key, $defaults );
		}
	}

	/**
	 * The stored row is merged over the defaults, not swapped for them.
	 *
	 * @return void
	 */
	public function test_partial_row_is_merged_over_defaults() {
		update_option( PostViews_Options::OPTION, array( 'count' => 2 ) );

		$this->assertSame( 2, PostViews_Options::get_int( 'count' ) );
		$this->assertSame(
			PostViews_Options::default_template( 'template' ),
			PostViews_Options::get( 'template' ),
			'A key absent from the stored row should fall back to its default.'
		);
	}

	/**
	 * A corrupt row must not fatal.
	 *
	 * @return void
	 */
	public function test_non_array_row_falls_back_to_defaults() {
		update_option( PostViews_Options::OPTION, 'not an array' );

		$this->assertSame( PostViews_Options::defaults(), PostViews_Options::all() );
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
		$this->assertSame( 1, PostViews_Options::get_int( 'count' ), 'Priming the cache.' );

		update_option( PostViews_Options::OPTION, array_merge( PostViews_Options::all(), array( 'count' => 2 ) ) );

		$this->assertSame( 2, PostViews_Options::get_int( 'count' ) );
	}

	/**
	 * An add_option() call must invalidate too, for a site whose row was deleted.
	 *
	 * @return void
	 */
	public function test_add_option_invalidates_the_cache() {
		delete_option( PostViews_Options::OPTION );
		PostViews_Options::flush();
		PostViews_Options::all();

		add_option( PostViews_Options::OPTION, array( 'count' => 2 ) );

		$this->assertSame( 2, PostViews_Options::get_int( 'count' ) );
	}

	/**
	 * The migration unslashes a legacy template.
	 *
	 * @return void
	 */
	public function test_migration_unslashes_stored_templates() {
		update_option(
			PostViews_Options::OPTION,
			array_merge(
				PostViews_Options::defaults(),
				array(
					'template'             => "it\\'s %VIEW_COUNT% views",
					'most_viewed_template' => "<li>it\\'s %POST_TITLE%</li>",
				)
			)
		);
		delete_option( PostViews_Options::VERSION_OPTION );
		PostViews_Options::flush();

		PostViews_Options::maybe_upgrade();

		$this->assertSame( "it's %VIEW_COUNT% views", PostViews_Options::get( 'template' ) );
		$this->assertSame( "<li>it's %POST_TITLE%</li>", PostViews_Options::get( 'most_viewed_template' ) );
		$this->assertSame( WP_POSTVIEWS_VERSION, get_option( PostViews_Options::VERSION_OPTION ) );
	}

	/**
	 * The migration is gated on the version, not on the content.
	 *
	 * An already-migrated site whose template legitimately contains a
	 * backslash must keep it. Gating on "does it still look slashed" would
	 * strip it on every load.
	 *
	 * @return void
	 */
	public function test_migration_does_not_rerun_on_a_current_install() {
		update_option( PostViews_Options::VERSION_OPTION, WP_POSTVIEWS_VERSION );
		$this->set_options( array( 'template' => 'C:\\path %VIEW_COUNT%' ) );

		PostViews_Options::maybe_upgrade();

		$this->assertSame( 'C:\\path %VIEW_COUNT%', PostViews_Options::get( 'template' ) );
	}

	/**
	 * Running the migration twice is a no-op the second time.
	 *
	 * @return void
	 */
	public function test_migration_is_idempotent() {
		$this->set_options( array( 'template' => "it\\'s" ) );
		delete_option( PostViews_Options::VERSION_OPTION );

		PostViews_Options::maybe_upgrade();
		$once = PostViews_Options::get( 'template' );

		PostViews_Options::maybe_upgrade();

		$this->assertSame( $once, PostViews_Options::get( 'template' ) );
	}

	/**
	 * Activation seeds the row without clobbering an existing one.
	 *
	 * @return void
	 */
	public function test_install_does_not_overwrite_existing_settings() {
		$this->set_options( array( 'count' => 2 ) );

		PostViews_Options::install();

		$this->assertSame( 2, PostViews_Options::get_int( 'count' ) );
	}

	/**
	 * Saving replaces the row and refills any missing key from the defaults.
	 *
	 * @return void
	 */
	public function test_save_replaces_and_backfills() {
		PostViews_Options::save( array( 'count' => 2 ) );

		$this->assertSame( 2, PostViews_Options::get_int( 'count' ) );
		$this->assertSame( PostViews_Options::default_template( 'template' ), PostViews_Options::get( 'template' ) );
		$this->assertSame( 2, (int) get_option( PostViews_Options::OPTION )['count'] );
	}

	/**
	 * The accessor returns the supplied fallback for a key that does not exist.
	 *
	 * @return void
	 */
	public function test_get_returns_the_fallback_for_an_unknown_key() {
		$this->assertSame( 'fallback', PostViews_Options::get( 'no_such_key', 'fallback' ) );
		$this->assertNull( PostViews_Options::get( 'no_such_key' ) );
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
		$this->assertSame( 2, PostViews_Options::get_int( 'count' ) );

		$this->set_options( array( 'count' => 'nonsense' ) );
		$this->assertSame( 0, PostViews_Options::get_int( 'count' ) );

		$this->assertSame( 0, PostViews_Options::get_int( 'no_such_key' ) );
	}

	/**
	 * Both templates have a non-empty default.
	 *
	 * @return void
	 */
	public function test_default_templates_are_present() {
		foreach ( array( 'template', 'most_viewed_template' ) as $key ) {
			$default = PostViews_Options::default_template( $key );

			$this->assertNotSame( '', $default );
			$this->assertStringContainsString( '%VIEW_COUNT%', $default );
		}

		$this->assertStringContainsString( '%POST_URL%', PostViews_Options::default_template( 'most_viewed_template' ) );
	}

	/**
	 * An unknown template key falls back to the single post template rather
	 * than returning nothing.
	 *
	 * @return void
	 */
	public function test_default_template_falls_back() {
		$this->assertSame(
			PostViews_Options::default_template( 'template' ),
			PostViews_Options::default_template( 'anything_else' )
		);
	}

	/**
	 * The migration leaves a corrupt row alone rather than fatal.
	 *
	 * @return void
	 */
	public function test_migration_survives_a_non_array_row() {
		update_option( PostViews_Options::OPTION, 'not an array' );
		delete_option( PostViews_Options::VERSION_OPTION );
		PostViews_Options::flush();

		PostViews_Options::maybe_upgrade();

		$this->assertSame( WP_POSTVIEWS_VERSION, get_option( PostViews_Options::VERSION_OPTION ) );
	}

	/**
	 * A template with no slashes is untouched by the migration.
	 *
	 * @return void
	 */
	public function test_migration_leaves_clean_templates_alone() {
		$this->set_options( array( 'template' => 'Plain %VIEW_COUNT%' ) );
		delete_option( PostViews_Options::VERSION_OPTION );
		PostViews_Options::flush();

		PostViews_Options::maybe_upgrade();

		$this->assertSame( 'Plain %VIEW_COUNT%', PostViews_Options::get( 'template' ) );
	}

	/**
	 * Activation records the version, so the migration does not run again.
	 *
	 * @return void
	 */
	public function test_install_records_the_version() {
		delete_option( PostViews_Options::VERSION_OPTION );

		PostViews_Options::install();

		$this->assertSame( WP_POSTVIEWS_VERSION, get_option( PostViews_Options::VERSION_OPTION ) );
	}
}
