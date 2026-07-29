<?php
/**
 * The Settings API registration and the sanitize callback.
 *
 * The menu, the screen and the script it loads are WP_PostViews_Admin's, and are
 * tested in test-admin.php.
 *
 * @package WP-PostViews
 */

/**
 * Settings screen.
 */
class WP_PostViews_Settings_Test extends WP_PostViews_TestCase {

	/**
	 * Make sure register_setting() has run.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		WP_PostViews_Settings::register();
	}

	/**
	 * The option is registered against the group the form posts under.
	 *
	 * A mismatch here is the classic reason a Settings API save silently does
	 * nothing.
	 *
	 * @return void
	 */
	public function test_setting_is_registered_in_the_group() {
		$registered = get_registered_settings();

		$this->assertArrayHasKey( WP_PostViews_Options::OPTION, $registered );
		$this->assertSame( WP_PostViews_Settings::GROUP, $registered[ WP_PostViews_Options::OPTION ]['group'] );
	}

	/**
	 * All three sections are registered against the screen Admin renders.
	 *
	 * @return void
	 */
	public function test_sections_are_registered() {
		global $wp_settings_sections;

		$this->assertArrayHasKey( WP_PostViews_Admin::PAGE, $wp_settings_sections );

		$sections = $wp_settings_sections[ WP_PostViews_Admin::PAGE ];

		$this->assertArrayHasKey( WP_PostViews_Settings::SECTION_GENERAL, $sections );
		$this->assertArrayHasKey( WP_PostViews_Settings::SECTION_DISPLAY, $sections );
		$this->assertArrayHasKey( WP_PostViews_Settings::SECTION_WPSTATS, $sections );

		// The first section has no heading of its own, so the table follows the
		// h1 the way it always has.
		$this->assertSame( '', $sections[ WP_PostViews_Settings::SECTION_GENERAL ]['title'] );
	}

	/**
	 * Every section name is prefixed with the plugin slug.
	 *
	 * They are global keys in $wp_settings_sections, so views_general was one
	 * collision away from another plugin's section.
	 *
	 * @return void
	 */
	public function test_section_names_are_prefixed() {
		foreach ( array( WP_PostViews_Settings::SECTION_GENERAL, WP_PostViews_Settings::SECTION_DISPLAY, WP_PostViews_Settings::SECTION_WPSTATS ) as $section ) {
			$this->assertStringStartsWith( 'wp_postviews_', $section );
		}
	}

	/**
	 * The settings group is the settings row name.
	 *
	 * @return void
	 */
	public function test_the_group_is_the_option_name() {
		$this->assertSame( WP_PostViews_Options::OPTION, WP_PostViews_Settings::GROUP );
	}

	/**
	 * Every option key the screen owns is a registered field, not markup in
	 * render().
	 *
	 * @return void
	 */
	public function test_every_option_key_has_a_registered_field() {
		global $wp_settings_fields;

		$fields = $wp_settings_fields[ WP_PostViews_Admin::PAGE ];

		foreach ( array( 'count', 'exclude_bots', 'template', 'most_viewed_template' ) as $key ) {
			$this->assertArrayHasKey( $key, $fields[ WP_PostViews_Settings::SECTION_GENERAL ], "No registered field for {$key}." );
		}

		foreach ( $this->display_keys as $key ) {
			$this->assertArrayHasKey( $key, $fields[ WP_PostViews_Settings::SECTION_DISPLAY ], "No registered field for {$key}." );
		}

		foreach ( array( 'stats_display', 'stats_most_limit' ) as $key ) {
			$this->assertArrayHasKey( $key, $fields[ WP_PostViews_Settings::SECTION_WPSTATS ], "No registered field for {$key}." );
		}

		// The AJAX row exists only under a page cache, same as the markup.
		$this->assertSame(
			defined( 'WP_CACHE' ) && WP_CACHE,
			isset( $fields[ WP_PostViews_Settings::SECTION_GENERAL ]['use_ajax'] )
		);
	}

	/**
	 * The select fields hand core a label_for, so clicking the row label focuses
	 * the control.
	 *
	 * @return void
	 */
	public function test_select_fields_declare_their_label_target() {
		global $wp_settings_fields;

		$fields = $wp_settings_fields[ WP_PostViews_Admin::PAGE ];

		$this->assertSame( 'views-count', $fields[ WP_PostViews_Settings::SECTION_GENERAL ]['count']['args']['label_for'] );
		$this->assertSame( 'views-display_home', $fields[ WP_PostViews_Settings::SECTION_DISPLAY ]['display_home']['args']['label_for'] );

		// The template rows carry a variable list in the heading cell, which
		// cannot live inside a label element, so they bring their own.
		$this->assertArrayNotHasKey( 'label_for', $fields[ WP_PostViews_Settings::SECTION_GENERAL ]['template']['args'] );
		$this->assertStringContainsString(
			'<label for="views-template-template">',
			$fields[ WP_PostViews_Settings::SECTION_GENERAL ]['template']['title']
		);
	}

	/**
	 * A full round trip through update_option(), which is what options.php
	 * does, keeps every submitted value.
	 *
	 * @return void
	 */
	public function test_save_round_trip() {
		update_option(
			WP_PostViews_Options::OPTION,
			array(
				'count'                => '2',
				'exclude_bots'         => '1',
				'use_ajax'             => '0',
				'display_home'         => '1',
				'display_single'       => '2',
				'template'             => '<b>%VIEW_COUNT%</b> reads',
				'most_viewed_template' => '<li>%POST_TITLE%</li>',
			)
		);

		$this->assertSame( 2, WP_PostViews_Options::get_int( 'count' ) );
		$this->assertSame( 1, WP_PostViews_Options::get_int( 'exclude_bots' ) );
		$this->assertSame( 0, WP_PostViews_Options::get_int( 'use_ajax' ) );
		$this->assertSame( 1, WP_PostViews_Options::get_int( 'display_home' ) );
		$this->assertSame( 2, WP_PostViews_Options::get_int( 'display_single' ) );
		$this->assertSame( '<b>%VIEW_COUNT%</b> reads', WP_PostViews_Options::get( 'template' ) );
	}

	/**
	 * A key the form did not render keeps its stored value.
	 *
	 * The use_ajax select is only shown when WP_CACHE is on, so a naive
	 * callback that returned its input would wipe it on every save.
	 *
	 * @return void
	 */
	public function test_absent_keys_are_preserved() {
		$this->set_options( array( 'count' => 2 ) );

		$sanitized = WP_PostViews_Settings::sanitize( array( 'template' => 'X' ) );

		$this->assertSame( 2, (int) $sanitized['count'] );
		$this->assertSame( 'X', $sanitized['template'] );
	}

	/**
	 * Out of range display values are clamped rather than stored.
	 *
	 * @return void
	 */
	public function test_display_values_are_clamped() {
		$sanitized = WP_PostViews_Settings::sanitize(
			array(
				'count'        => '99',
				'display_home' => '-4',
			)
		);

		$this->assertSame( 2, $sanitized['count'] );
		$this->assertSame( 0, $sanitized['display_home'] );
	}

	/**
	 * Booleans are normalised to 0 or 1.
	 *
	 * @return void
	 */
	public function test_boolean_values_are_normalised() {
		$sanitized = WP_PostViews_Settings::sanitize(
			array(
				'exclude_bots' => '1',
				'use_ajax'     => '0',
			)
		);

		$this->assertSame( 1, $sanitized['exclude_bots'] );
		$this->assertSame( 0, $sanitized['use_ajax'] );
	}

	/**
	 * Templates are filtered through kses, so a script tag cannot be stored.
	 *
	 * @return void
	 */
	public function test_templates_are_kses_filtered() {
		$sanitized = WP_PostViews_Settings::sanitize(
			array( 'template' => '<script>alert(1)</script><b>%VIEW_COUNT%</b>' )
		);

		$this->assertStringNotContainsString( '<script>', $sanitized['template'] );
		$this->assertStringContainsString( '<b>%VIEW_COUNT%</b>', $sanitized['template'] );
	}

	/**
	 * An inline event handler is stripped from a template.
	 *
	 * @return void
	 */
	public function test_templates_reject_inline_handlers() {
		$sanitized = WP_PostViews_Settings::sanitize(
			array( 'most_viewed_template' => '<li onclick="steal()">%POST_TITLE%</li>' )
		);

		$this->assertStringNotContainsString( 'onclick', $sanitized['most_viewed_template'] );
	}

	/**
	 * Garbage input leaves the stored settings alone.
	 *
	 * @return void
	 */
	public function test_non_array_input_is_ignored() {
		$this->set_options( array( 'count' => 2 ) );

		$this->assertSame( WP_PostViews_Options::all(), WP_PostViews_Settings::sanitize( 'nonsense' ) );
	}

	/**
	 * Saving does not leave the runtime cache stale.
	 *
	 * @return void
	 */
	public function test_saving_refreshes_the_cache() {
		WP_PostViews_Options::all();

		update_option( WP_PostViews_Options::OPTION, array_merge( WP_PostViews_Options::all(), array( 'template' => 'FRESH' ) ) );

		$this->assertSame( 'FRESH', WP_PostViews_Options::get( 'template' ) );
	}

	/**
	 * The WP-Stats toggle is stored as a bool, whatever the checkbox posted.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_toggle_is_stored_as_a_bool() {
		$sanitized = WP_PostViews_Settings::sanitize( array( 'stats_display' => '1' ) );

		$this->assertTrue( $sanitized['stats_display'] );
	}

	/**
	 * An unticked WP-Stats checkbox switches the section off.
	 *
	 * A checkbox posts nothing at all when it is off, so this is the one row the
	 * callback reads as absent-means-off. Reading it as absent-means-unchanged
	 * would make the section impossible to switch off from the screen.
	 *
	 * @return void
	 */
	public function test_an_unticked_wp_stats_checkbox_turns_the_section_off() {
		$this->set_options( array( 'stats_display' => true ) );

		$sanitized = WP_PostViews_Settings::sanitize( array( 'template' => 'X' ) );

		$this->assertFalse( $sanitized['stats_display'] );
	}

	/**
	 * The most viewed limit is an integer of at least one.
	 *
	 * @return void
	 */
	public function test_the_most_viewed_limit_is_floored_at_one() {
		$this->assertSame( 25, WP_PostViews_Settings::sanitize( array( 'stats_most_limit' => '25' ) )['stats_most_limit'] );
		$this->assertSame( 1, WP_PostViews_Settings::sanitize( array( 'stats_most_limit' => '0' ) )['stats_most_limit'] );
		$this->assertSame( 1, WP_PostViews_Settings::sanitize( array( 'stats_most_limit' => 'nonsense' ) )['stats_most_limit'] );
	}
}
