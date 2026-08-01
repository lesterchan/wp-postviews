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
	 * There are exactly two tabs, named for what they hold.
	 *
	 * @return void
	 */
	public function test_the_screen_has_two_tabs() {
		$this->assertSame(
			array(
				'settings'  => 'Settings',
				'templates' => 'Templates',
			),
			WP_PostViews_Settings::tabs()
		);
	}

	/**
	 * The tab comes from the request, and anything unrecognised is the first tab.
	 *
	 * A ?tab= nobody registered must not draw an empty screen.
	 *
	 * @return void
	 */
	public function test_the_current_tab_falls_back_to_the_first() {
		$this->assertSame( 'settings', WP_PostViews_Settings::current_tab() );

		$_GET['tab'] = 'templates';
		$this->assertSame( 'templates', WP_PostViews_Settings::current_tab() );

		$_GET['tab'] = 'nonsense';
		$this->assertSame( 'settings', WP_PostViews_Settings::current_tab() );

		unset( $_GET['tab'] );
	}

	/**
	 * Each tab is its own Settings API page, which is what stops one tab drawing
	 * the other's fields.
	 *
	 * @return void
	 */
	public function test_each_tab_is_its_own_settings_page() {
		$this->assertSame( 'wp-postviews-settings', WP_PostViews_Settings::tab_page( 'settings' ) );
		$this->assertSame( 'wp-postviews-templates', WP_PostViews_Settings::tab_page( 'templates' ) );
		$this->assertNotSame(
			WP_PostViews_Settings::tab_page( 'settings' ),
			WP_PostViews_Settings::tab_page( 'templates' )
		);
	}

	/**
	 * Every section is registered against the tab that owns it, and the retired
	 * one is registered nowhere.
	 *
	 * @return void
	 */
	public function test_sections_are_registered() {
		global $wp_settings_sections;

		$settings  = WP_PostViews_Settings::tab_page( 'settings' );
		$templates = WP_PostViews_Settings::tab_page( 'templates' );

		$this->assertArrayHasKey( $settings, $wp_settings_sections );
		$this->assertArrayHasKey( $templates, $wp_settings_sections );

		$this->assertArrayHasKey( WP_PostViews_Settings::SECTION_GENERAL, $wp_settings_sections[ $settings ] );
		$this->assertArrayHasKey( WP_PostViews_Settings::SECTION_WPSTATS, $wp_settings_sections[ $settings ] );
		$this->assertCount( 2, $wp_settings_sections[ $settings ] );

		$this->assertArrayHasKey( WP_PostViews_Settings::SECTION_TEMPLATES, $wp_settings_sections[ $templates ] );
		$this->assertCount( 1, $wp_settings_sections[ $templates ] );

		// Display Options is gone, and with it the only section this screen had
		// that restated what a theme already decides by where it calls
		// the_views().
		$this->assertArrayNotHasKey( 'wp_postviews_display', $wp_settings_sections[ $settings ] );
		$this->assertFalse( defined( 'WP_PostViews_Settings::SECTION_DISPLAY' ) );

		// Nothing is registered against the bare menu slug any more: a section
		// left there would draw on neither tab.
		$this->assertArrayNotHasKey( WP_PostViews_Admin::PAGE, $wp_settings_sections );

		// Every section is named for what its fields do, except the one that is
		// alone on its tab -- there the tab label is already the heading, and a
		// section repeating it would say Templates twice in a row.
		$this->assertSame( 'Counting', $wp_settings_sections[ $settings ][ WP_PostViews_Settings::SECTION_GENERAL ]['title'] );
		$this->assertSame( '', $wp_settings_sections[ $templates ][ WP_PostViews_Settings::SECTION_TEMPLATES ]['title'] );
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
		foreach ( array( WP_PostViews_Settings::SECTION_GENERAL, WP_PostViews_Settings::SECTION_TEMPLATES, WP_PostViews_Settings::SECTION_WPSTATS ) as $section ) {
			$this->assertStringStartsWith( 'wp_postviews_', $section );
		}
	}

	/**
	 * Both tabs post into one group, and so into one option row.
	 *
	 * The tabs split the drawing, never the storage. Two groups would mean two
	 * rows and a second sanitiser to keep in step.
	 *
	 * @return void
	 */
	public function test_the_group_is_the_option_name() {
		$this->assertSame( WP_PostViews_Options::OPTION, WP_PostViews_Settings::GROUP );

		$registered = get_registered_settings();
		$this->assertCount(
			1,
			array_filter(
				array_keys( $registered ),
				static function ( $name ) {
					return 0 === strpos( $name, 'wp_postviews' );
				}
			),
			'One register_setting() across both tabs.'
		);
	}

	/**
	 * Every option key the screen owns is a registered field, on the tab that
	 * owns it, not markup in render_page().
	 *
	 * @return void
	 */
	public function test_every_option_key_has_a_registered_field() {
		global $wp_settings_fields;

		$settings  = $wp_settings_fields[ WP_PostViews_Settings::tab_page( 'settings' ) ];
		$templates = $wp_settings_fields[ WP_PostViews_Settings::tab_page( 'templates' ) ];

		foreach ( array( 'count', 'exclude_bots' ) as $key ) {
			$this->assertArrayHasKey( $key, $settings[ WP_PostViews_Settings::SECTION_GENERAL ], "No registered field for {$key}." );
		}

		foreach ( array( 'stats_display', 'stats_most_limit' ) as $key ) {
			$this->assertArrayHasKey( $key, $settings[ WP_PostViews_Settings::SECTION_WPSTATS ], "No registered field for {$key}." );
		}

		foreach ( array( 'template', 'most_viewed_template' ) as $key ) {
			$this->assertArrayHasKey( $key, $templates[ WP_PostViews_Settings::SECTION_TEMPLATES ], "No registered field for {$key} on the Templates tab." );
		}

		// And nothing is registered for a retired key, on either tab.
		$registered = array();
		foreach ( array( $settings, $templates ) as $tab_fields ) {
			foreach ( $tab_fields as $section_fields ) {
				$registered = array_merge( $registered, array_keys( $section_fields ) );
			}
		}
		foreach ( WP_PostViews_Options::retired_keys() as $key ) {
			$this->assertNotContains( $key, $registered, "{$key} is retired and must have no field." );
		}

		// The AJAX row exists only under a page cache, same as the markup.
		$this->assertSame(
			defined( 'WP_CACHE' ) && WP_CACHE,
			isset( $settings[ WP_PostViews_Settings::SECTION_GENERAL ]['use_ajax'] )
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

		$settings  = $wp_settings_fields[ WP_PostViews_Settings::tab_page( 'settings' ) ];
		$templates = $wp_settings_fields[ WP_PostViews_Settings::tab_page( 'templates' ) ];

		$this->assertSame( 'views-count', $settings[ WP_PostViews_Settings::SECTION_GENERAL ]['count']['args']['label_for'] );
		$this->assertSame( 'views-exclude_bots', $settings[ WP_PostViews_Settings::SECTION_GENERAL ]['exclude_bots']['args']['label_for'] );

		// The template rows carry a variable list in the heading cell, which
		// cannot live inside a label element, so they bring their own.
		$this->assertArrayNotHasKey( 'label_for', $templates[ WP_PostViews_Settings::SECTION_TEMPLATES ]['template']['args'] );
		$this->assertStringContainsString(
			'<label for="views-template-template">',
			$templates[ WP_PostViews_Settings::SECTION_TEMPLATES ]['template']['title']
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
				'template'             => '<b>%VIEW_COUNT%</b> reads',
				'most_viewed_template' => '<li>%POST_TITLE%</li>',
			)
		);

		$this->assertSame( 2, WP_PostViews_Options::get_int( 'count' ) );
		$this->assertSame( 1, WP_PostViews_Options::get_int( 'exclude_bots' ) );
		$this->assertSame( 0, WP_PostViews_Options::get_int( 'use_ajax' ) );
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
	 * Out of range count values are clamped rather than stored.
	 *
	 * @return void
	 */
	public function test_count_values_are_clamped() {
		$this->assertSame( 2, WP_PostViews_Settings::sanitize( array( 'count' => '99' ) )['count'] );
		$this->assertSame( 0, WP_PostViews_Settings::sanitize( array( 'count' => '-4' ) )['count'] );
	}

	/**
	 * A retired key is dropped, whether it is posted or merely stored.
	 *
	 * The callback merges into the stored value so a key the screen did not
	 * render keeps what it had -- which is what use_ajax needs and exactly what
	 * would otherwise carry the six display_* rows forever.
	 *
	 * @return void
	 */
	public function test_retired_keys_are_dropped_by_the_sanitizer() {
		// Straight into the row, past save(), the way a site that upgraded from
		// 1.78.1 carries them.
		update_option(
			WP_PostViews_Options::OPTION,
			array_merge( WP_PostViews_Options::defaults(), array_fill_keys( WP_PostViews_Options::retired_keys(), 2 ) )
		);
		WP_PostViews_Options::flush();

		$sanitized = WP_PostViews_Settings::sanitize( array_fill_keys( WP_PostViews_Options::retired_keys(), '1' ) );

		foreach ( WP_PostViews_Options::retired_keys() as $key ) {
			$this->assertArrayNotHasKey( $key, $sanitized, $key . ' must not survive the sanitizer.' );
		}

		// The rest of the row is untouched by the drop.
		$this->assertSame( WP_PostViews_Options::defaults()['template'], $sanitized['template'] );
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
	 * A checkbox posts nothing at all when it is off, so the field pairs it with
	 * a hidden zero. That zero is what arrives here, and it must be believed --
	 * otherwise the section could never be switched off from the screen.
	 *
	 * @return void
	 */
	public function test_an_unticked_wp_stats_checkbox_turns_the_section_off() {
		$this->set_options( array( 'stats_display' => true ) );

		$sanitized = WP_PostViews_Settings::sanitize( array( 'stats_display' => '0' ) );

		$this->assertFalse( $sanitized['stats_display'] );
	}

	/**
	 * The unticked checkbox really does post, rather than relying on absence.
	 *
	 * The hidden zero is the whole reason the sanitiser can treat every key as
	 * absent-means-unchanged, which is what makes the two tabs safe. Losing it
	 * would not fail any assertion above: the screen would simply stop being
	 * able to turn the section off.
	 *
	 * @return void
	 */
	public function test_the_wp_stats_checkbox_is_paired_with_a_hidden_zero() {
		$html = $this->capture( array( 'WP_PostViews_Settings', 'field_stats_display' ) );

		$name = WP_PostViews_Options::OPTION . '[stats_display]';

		$this->assertStringContainsString( 'type="hidden" name="' . $name . '" value="0"', $html );

		// And the hidden field comes first, because PHP keeps the last value for
		// a repeated name -- the other order would pin it off permanently.
		$this->assertLessThan(
			strpos( $html, 'type="checkbox"' ),
			strpos( $html, 'type="hidden"' ),
			'The hidden zero must precede the checkbox.'
		);
	}

	/**
	 * Saving one tab leaves the other tab's values alone.
	 *
	 * The Settings API hands a sanitize callback only the fields the submitting
	 * form posted. Two tabs post disjoint sets, so a callback that returned what
	 * it was handed would empty whichever tab was not on screen. This is the
	 * assertion that catches it.
	 *
	 * @return void
	 */
	public function test_saving_one_tab_preserves_the_other() {
		$this->set_options(
			array(
				'count'                => 2,
				'exclude_bots'         => 1,
				'stats_display'        => true,
				'stats_most_limit'     => 25,
				'template'             => 'TEMPLATE ONE',
				'most_viewed_template' => 'TEMPLATE TWO',
			)
		);

		// The Templates tab posts its two fields and nothing else.
		$saved = WP_PostViews_Settings::sanitize(
			array(
				'template'             => 'EDITED ONE',
				'most_viewed_template' => 'EDITED TWO',
			)
		);

		$this->assertSame( 'EDITED ONE', $saved['template'] );
		$this->assertSame( 'EDITED TWO', $saved['most_viewed_template'] );
		$this->assertSame( 2, $saved['count'], 'The Settings tab must survive a Templates save.' );
		$this->assertSame( 1, $saved['exclude_bots'] );
		$this->assertSame( 25, $saved['stats_most_limit'] );
		$this->assertTrue( $saved['stats_display'], 'The WP-Stats checkbox must not read as unticked when its tab did not post.' );

		// And the other direction: the Settings tab posts its own fields, with
		// the checkbox unticked, and the templates are untouched.
		WP_PostViews_Options::save( $saved );

		$saved = WP_PostViews_Settings::sanitize(
			array(
				'count'            => '0',
				'exclude_bots'     => '0',
				'stats_display'    => '0',
				'stats_most_limit' => '5',
			)
		);

		$this->assertSame( 0, $saved['count'] );
		$this->assertFalse( $saved['stats_display'] );
		$this->assertSame( 5, $saved['stats_most_limit'] );
		$this->assertSame( 'EDITED ONE', $saved['template'], 'The Templates tab must survive a Settings save.' );
		$this->assertSame( 'EDITED TWO', $saved['most_viewed_template'] );
	}

	/**
	 * Dropping the retired keys does not fight with keeping the other tab's.
	 *
	 * Both rules live in the same callback and pull opposite ways: one says
	 * "keep everything this submission did not mention", the other says "drop
	 * these six whatever happens".
	 *
	 * @return void
	 */
	public function test_dropping_retired_keys_does_not_drop_the_other_tab() {
		update_option(
			WP_PostViews_Options::OPTION,
			array_merge(
				WP_PostViews_Options::defaults(),
				array_fill_keys( WP_PostViews_Options::retired_keys(), 2 ),
				array(
					'template'      => 'KEPT ONE',
					'stats_display' => true,
				)
			)
		);
		WP_PostViews_Options::flush();

		// A Settings tab save: no template field in sight.
		$saved = WP_PostViews_Settings::sanitize( array( 'count' => '0' ) );

		foreach ( WP_PostViews_Options::retired_keys() as $key ) {
			$this->assertArrayNotHasKey( $key, $saved, $key . ' is retired and must be dropped.' );
		}

		$this->assertSame( 'KEPT ONE', $saved['template'], 'A retired key going out must not take a live one with it.' );
		$this->assertTrue( $saved['stats_display'] );
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
