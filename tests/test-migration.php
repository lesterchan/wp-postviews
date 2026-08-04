<?php
/**
 * The 2.0.0 migration and the two upgrade markers.
 *
 * Four rows are folded in and deleted: views_options and views_version, which
 * were named after the "views" noun rather than the plugin, and stats_display
 * and stats_mostlimit, which WP-Stats and five siblings wrote into at once.
 * Those last two are the interesting half. Every one of the seven plugins
 * deletes them, so only the first to upgrade ever sees them, and the tests
 * below pin down that an absent row means the block stays on rather than
 * silently disappearing.
 *
 * @package WP-PostViews
 */

/**
 * Option row migration and the version markers.
 */
class WP_PostViews_Migration_Test extends WP_PostViews_TestCase {

	/**
	 * Put the site back into its pre-2.0.0 shape.
	 *
	 * @param array $legacy_options What views_options held.
	 * @param mixed $legacy_version What views_version held, or null for absent.
	 * @return void
	 */
	protected function stage_legacy_install( $legacy_options = array(), $legacy_version = null ) {
		delete_option( WP_PostViews_Options::OPTION );
		delete_option( WP_PostViews_Options::VERSION );

		update_option( WP_PostViews_Options::LEGACY_OPTION, array_merge( WP_PostViews_Options::defaults(), $legacy_options ) );

		if ( null === $legacy_version ) {
			delete_option( WP_PostViews_Options::LEGACY_VERSION );
		} else {
			update_option( WP_PostViews_Options::LEGACY_VERSION, $legacy_version );
		}

		WP_PostViews_Options::flush();
	}

	/**
	 * Clear the rows the migration deletes, so one test cannot seed the next.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_option( WP_PostViews_Options::LEGACY_OPTION );
		delete_option( WP_PostViews_Options::LEGACY_VERSION );
		delete_option( WP_PostViews_Options::LEGACY_STATS_DISPLAY );
		delete_option( WP_PostViews_Options::LEGACY_STATS_MOST_LIMIT );

		parent::tear_down();
	}

	// --- the row rename ---------------------------------------------------

	/**
	 * A site on the shipped settings still gets its row written.
	 *
	 * `stage_legacy_install()` with no arguments seeds views_options with
	 * exactly `defaults()`, which is the commonest install there is and the only
	 * fixture that can see §7.6.1 -- every test that passes an override produces
	 * a result differing from the defaults, and such a result is written
	 * whatever happened on the way in.
	 *
	 * The setting is registered first, because that is the arrangement an update
	 * through the Plugins screen actually creates and the one WP-CLI never does.
	 * With the `default_option_wp_postviews_options` filter live, an absent row
	 * reads back as the defaults, so `get_int()` and every other merging reader
	 * answer identically whether or not the migration wrote anything. Reading
	 * the row raw, with the two-argument `get_option()`, is the only way to tell
	 * the two apart -- which is the point of this test and the reason the
	 * assertions elsewhere in this file were given their second argument.
	 *
	 * @return void
	 */
	public function test_a_stock_legacy_install_still_gets_its_row_written() {
		$this->stage_legacy_install();

		WP_PostViews_Settings::register();

		$this->assertFalse( get_option( WP_PostViews_Options::OPTION, false ), 'The fixture is only pre-migration if the prefixed row is genuinely absent.' );

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$stored = get_option( WP_PostViews_Options::OPTION, false );

		$this->assertIsArray( $stored, 'The migration must write the prefixed row even when its result equals the shipped defaults.' );
		$this->assertFalse( get_option( WP_PostViews_Options::LEGACY_OPTION ), 'And views_options is deleted, having actually been folded in.' );
	}

	/**
	 * The settings arrive under the new name and the old rows are gone.
	 *
	 * @return void
	 */
	public function test_the_settings_move_to_the_prefixed_row_and_the_old_ones_go() {
		$this->stage_legacy_install( array( 'count' => 2 ), '1.78.1' );

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertSame( 2, WP_PostViews_Options::get_int( 'count' ), 'The stored setting did not survive the rename.' );
		$this->assertIsArray( get_option( WP_PostViews_Options::OPTION, false ), 'wp_postviews_options was not written.' );
		$this->assertFalse( get_option( WP_PostViews_Options::LEGACY_OPTION ), 'views_options was left behind.' );
		$this->assertFalse( get_option( WP_PostViews_Options::LEGACY_VERSION ), 'views_version was left behind.' );
	}

	/**
	 * A key the legacy row never held falls back to its default.
	 *
	 * @return void
	 */
	public function test_a_key_absent_from_the_legacy_row_takes_its_default() {
		delete_option( WP_PostViews_Options::OPTION );
		delete_option( WP_PostViews_Options::VERSION );
		update_option( WP_PostViews_Options::LEGACY_OPTION, array( 'count' => 2 ) );
		WP_PostViews_Options::flush();

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertSame( 2, WP_PostViews_Options::get_int( 'count' ), 'A key the legacy row never held takes its default.' );
		$this->assertSame( WP_PostViews_Options::default_template( 'template' ), WP_PostViews_Options::get( 'template' ), 'Templates included.' );
	}

	/**
	 * A corrupt legacy row leaves the defaults standing rather than fatal.
	 *
	 * @return void
	 */
	public function test_a_non_array_legacy_row_is_ignored() {
		delete_option( WP_PostViews_Options::OPTION );
		delete_option( WP_PostViews_Options::VERSION );
		update_option( WP_PostViews_Options::LEGACY_OPTION, 'not an array' );
		WP_PostViews_Options::flush();

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertSame( 1, WP_PostViews_Options::get_int( 'count' ), 'A legacy row that is not an array is ignored rather than merged.' );
		$this->assertSame( WP_POSTVIEWS_VERSION, WP_PostViews_Options::markers()['plugin'], 'And the install is still stamped, so the migration does not run again.' );
	}

	// --- the markers ------------------------------------------------------

	/**
	 * The marker row holds exactly 'plugin' and 'db'.
	 *
	 * @return void
	 */
	public function test_the_marker_row_holds_exactly_plugin_and_db() {
		$this->stage_legacy_install();

		WP_PostViews_Options::maybe_upgrade();

		$markers = get_option( WP_PostViews_Options::VERSION );

		$this->assertIsArray( $markers, 'The marker row should be an array.' );
		$this->assertSame( array( 'plugin', 'db' ), array_keys( $markers ), 'The marker row carries exactly two keys.' );
		$this->assertSame( WP_POSTVIEWS_VERSION, $markers['plugin'], 'The marker row holds the plugin version.' );
		$this->assertSame( WP_POSTVIEWS_DB_VERSION, $markers['db'], 'And the schema version, and nothing else.' );
	}

	/**
	 * A partial or corrupt marker row reads as two empty strings.
	 *
	 * @return void
	 */
	public function test_the_markers_are_normalised() {
		update_option( WP_PostViews_Options::VERSION, 'nonsense' );
		$this->assertSame(
			array(
				'plugin' => '',
				'db'     => '',
			),
			WP_PostViews_Options::markers(),
			'A missing marker row normalises to two empty strings, not to nothing.'
		);

		update_option( WP_PostViews_Options::VERSION, array( 'plugin' => '1.78.1' ) );
		$this->assertSame(
			array(
				'plugin' => '1.78.1',
				'db'     => '',
			),
			WP_PostViews_Options::markers(),
			'And a partial one keeps what it has and fills in the rest.'
		);
	}

	/**
	 * A site already on this version is left entirely alone.
	 *
	 * @return void
	 */
	public function test_a_current_install_is_not_migrated_again() {
		WP_PostViews_Options::update_markers();
		$this->set_options( array( 'template' => 'C:\\path %VIEW_COUNT%' ) );
		update_option( WP_PostViews_Options::LEGACY_OPTION, array( 'count' => 2 ) );

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertSame( 'C:\\path %VIEW_COUNT%', WP_PostViews_Options::get( 'template' ), 'A backslash the user wanted was stripped.' );
		$this->assertSame( 1, WP_PostViews_Options::get_int( 'count' ), 'The legacy row was read on a current install.' );
	}

	/**
	 * Running the migration twice changes nothing the second time.
	 *
	 * @return void
	 */
	public function test_the_migration_is_idempotent() {
		$this->stage_legacy_install( array( 'template' => "it\\'s %VIEW_COUNT%" ), '1.78.1' );

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();
		$once = WP_PostViews_Options::all();

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertSame( $once, WP_PostViews_Options::all(), 'Running the migration twice leaves the same settings.' );
	}

	// --- the templates ----------------------------------------------------

	/**
	 * A slashed legacy template is unslashed once.
	 *
	 * @return void
	 */
	public function test_a_legacy_template_is_unslashed() {
		$this->stage_legacy_install(
			array(
				'template'             => "it\\'s %VIEW_COUNT% views",
				'most_viewed_template' => "<li>it\\'s %POST_TITLE%</li>",
			),
			'1.78.1'
		);

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertSame( "it's %VIEW_COUNT% views", WP_PostViews_Options::get( 'template' ), 'A slashed view count template is unslashed once.' );
		$this->assertSame( "<li>it's %POST_TITLE%</li>", WP_PostViews_Options::get( 'most_viewed_template' ), 'And so is the listing template.' );
	}

	/**
	 * A template with no slashes in it comes through untouched.
	 *
	 * @return void
	 */
	public function test_a_clean_template_is_left_alone() {
		$this->stage_legacy_install( array( 'template' => 'Plain %VIEW_COUNT%' ), '1.78.1' );

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertSame( 'Plain %VIEW_COUNT%', WP_PostViews_Options::get( 'template' ), 'A template with nothing to unslash is left exactly as it was.' );
	}

	/**
	 * An install that already ran a 2.0.0 build is not stripped a second time.
	 *
	 * The unslash is a one-time correction. Applied twice it eats a backslash
	 * the user genuinely wanted -- a Windows path in a template loses its
	 * separator -- so it consults the legacy marker rather than guessing from
	 * the content, which cannot tell the two cases apart.
	 *
	 * @return void
	 */
	public function test_an_already_unslashed_template_survives_the_row_rename() {
		$this->stage_legacy_install( array( 'template' => 'C:\\path %VIEW_COUNT%' ), '2.0.0' );

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertSame( 'C:\\path %VIEW_COUNT%', WP_PostViews_Options::get( 'template' ), 'A backslash that is part of the text survives the row rename rather than being eaten.' );
	}

	// --- the shared WP-Stats rows -----------------------------------------

	/**
	 * An absent stats_display row means the block stays on.
	 *
	 * This is §13.2. All seven contributing plugins delete the shared row, so
	 * whichever the site updates first takes it away and the other six find
	 * nothing to read. Treating that as an opt-out would make the views block
	 * vanish from the stats page of every site that updated WP-Stats first,
	 * silently and with nothing in any log to explain it.
	 *
	 * @return void
	 */
	public function test_an_absent_shared_row_leaves_the_block_on() {
		$this->stage_legacy_install( array(), '1.78.1' );
		delete_option( WP_PostViews_Options::LEGACY_STATS_DISPLAY );

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertTrue( WP_PostViews_Options::get( 'stats_display' ), 'A sibling deleting the shared row must not switch this block off.' );
	}

	/**
	 * Both shapes the shared row is found in are read correctly.
	 *
	 * The row is an array keyed per plugin, not a boolean. A bare (bool) cast
	 * turns any non-empty array true, which switches back on a block the owner
	 * deliberately turned off -- the map shape below with 'views' => 0 is
	 * exactly that case.
	 *
	 * @dataProvider data_shared_stats_display_shapes
	 *
	 * @param mixed $stored   Whatever stats_display holds.
	 * @param bool  $expected Whether the views block should end up on.
	 * @return void
	 */
	public function test_every_shape_of_the_shared_row_is_read( $stored, $expected ) {
		$this->stage_legacy_install( array(), '1.78.1' );
		update_option( WP_PostViews_Options::LEGACY_STATS_DISPLAY, $stored );

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertSame( $expected, WP_PostViews_Options::get( 'stats_display' ), 'Every shape the shared row has taken is read.' );
	}

	/**
	 * The shapes stats_display has been found in on real installs.
	 *
	 * @return array
	 */
	public function data_shared_stats_display_shapes() {
		return array(
			'map, on'                   => array( array( 'views' => 1 ), true ),
			'map, off'                  => array( array( 'views' => 0 ), false ),
			'map, another plugin only'  => array( array( 'polls' => 1 ), false ),
			'list, on'                  => array( array( 'views' ), true ),
			'list, another plugin only' => array( array( 'polls', 'ratings' ), false ),
			'empty array'               => array( array(), false ),
			'a bare truthy scalar'      => array( '1', true ),
			'a bare falsy scalar'       => array( '0', false ),
		);
	}

	/**
	 * The result is stored as a bool, not as whatever the old row held.
	 *
	 * @return void
	 */
	public function test_the_migrated_toggle_is_stored_as_a_bool() {
		$this->stage_legacy_install( array(), '1.78.1' );
		update_option( WP_PostViews_Options::LEGACY_STATS_DISPLAY, array( 'views' => '1' ) );

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertIsBool( WP_PostViews_Options::get( 'stats_display' ), 'The migrated toggle is stored as a bool, not the string it arrived as.' );
	}

	/**
	 * The shared limit becomes this plugin's own, floored at one.
	 *
	 * @return void
	 */
	public function test_the_shared_limit_is_taken_over() {
		$this->stage_legacy_install( array(), '1.78.1' );
		update_option( WP_PostViews_Options::LEGACY_STATS_MOST_LIMIT, 25 );

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertSame( 25, WP_PostViews_Options::get_int( 'stats_most_limit' ), 'The shared limit is taken over.' );
	}

	/**
	 * An absent shared limit leaves the shipped default in place.
	 *
	 * @return void
	 */
	public function test_an_absent_shared_limit_keeps_the_default() {
		$this->stage_legacy_install( array(), '1.78.1' );
		delete_option( WP_PostViews_Options::LEGACY_STATS_MOST_LIMIT );

		WP_PostViews_Options::maybe_upgrade();
		WP_PostViews_Options::flush();

		$this->assertSame( 10, WP_PostViews_Options::get_int( 'stats_most_limit' ), 'And with none to take over the default stands.' );
	}

	/**
	 * Both shared rows are deleted once they have been folded in.
	 *
	 * @return void
	 */
	public function test_the_shared_rows_are_deleted() {
		$this->stage_legacy_install( array(), '1.78.1' );
		update_option( WP_PostViews_Options::LEGACY_STATS_DISPLAY, array( 'views' => 1 ) );
		update_option( WP_PostViews_Options::LEGACY_STATS_MOST_LIMIT, 25 );

		WP_PostViews_Options::maybe_upgrade();

		$this->assertFalse( get_option( WP_PostViews_Options::LEGACY_STATS_DISPLAY ), 'stats_display was left behind.' );
		$this->assertFalse( get_option( WP_PostViews_Options::LEGACY_STATS_MOST_LIMIT ), 'stats_mostlimit was left behind.' );
	}

	/**
	 * Uninstall does not take the shared rows with it.
	 *
	 * They were never this plugin's alone. A sibling that has not upgraded yet
	 * is still reading them, and deleting one plugin must not reconfigure five
	 * others.
	 *
	 * @return void
	 */
	public function test_the_shared_rows_are_not_this_plugins_to_uninstall() {
		$names = WP_PostViews_Options::all_option_names();

		$this->assertNotContains( WP_PostViews_Options::LEGACY_STATS_DISPLAY, $names, 'The shared display row is not on the uninstall list.' );
		$this->assertNotContains( WP_PostViews_Options::LEGACY_STATS_MOST_LIMIT, $names, 'Nor the shared limit row, because they are not this plugin to delete.' );
	}

	// --- activation -------------------------------------------------------

	/**
	 * Activation seeds the row and records the markers in one go.
	 *
	 * @return void
	 */
	public function test_activation_seeds_the_row_and_the_markers() {
		delete_option( WP_PostViews_Options::OPTION );
		delete_option( WP_PostViews_Options::VERSION );
		WP_PostViews_Options::flush();

		WP_PostViews_Options::install();

		$this->assertIsArray( get_option( WP_PostViews_Options::OPTION, false ), 'Activation seeds a settings row rather than leaving the option absent.' );
		$this->assertSame( WP_POSTVIEWS_VERSION, WP_PostViews_Options::markers()['plugin'], 'Activation stamps the plugin version.' );
		$this->assertSame( WP_POSTVIEWS_DB_VERSION, WP_PostViews_Options::markers()['db'], 'And the schema version.' );
	}
}
