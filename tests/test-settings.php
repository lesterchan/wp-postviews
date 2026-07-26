<?php
/**
 * The Settings API registration and its sanitize callback.
 *
 * @package WP-PostViews
 */

/**
 * Settings screen.
 */
class Test_PostViews_Settings extends PostViews_TestCase {

	/**
	 * Make sure register_setting() has run.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		PostViews_Settings::register();
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

		$this->assertArrayHasKey( PostViews_Options::OPTION, $registered );
		$this->assertSame( PostViews_Settings::GROUP, $registered[ PostViews_Options::OPTION ]['group'] );
	}

	/**
	 * A full round trip through update_option(), which is what options.php
	 * does, keeps every submitted value.
	 *
	 * @return void
	 */
	public function test_save_round_trip() {
		update_option(
			PostViews_Options::OPTION,
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

		$this->assertSame( 2, PostViews_Options::get_int( 'count' ) );
		$this->assertSame( 1, PostViews_Options::get_int( 'exclude_bots' ) );
		$this->assertSame( 0, PostViews_Options::get_int( 'use_ajax' ) );
		$this->assertSame( 1, PostViews_Options::get_int( 'display_home' ) );
		$this->assertSame( 2, PostViews_Options::get_int( 'display_single' ) );
		$this->assertSame( '<b>%VIEW_COUNT%</b> reads', PostViews_Options::get( 'template' ) );
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

		$sanitized = PostViews_Settings::sanitize( array( 'template' => 'X' ) );

		$this->assertSame( 2, (int) $sanitized['count'] );
		$this->assertSame( 'X', $sanitized['template'] );
	}

	/**
	 * Out of range display values are clamped rather than stored.
	 *
	 * @return void
	 */
	public function test_display_values_are_clamped() {
		$sanitized = PostViews_Settings::sanitize(
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
		$sanitized = PostViews_Settings::sanitize(
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
		$sanitized = PostViews_Settings::sanitize(
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
		$sanitized = PostViews_Settings::sanitize(
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

		$this->assertSame( PostViews_Options::all(), PostViews_Settings::sanitize( 'nonsense' ) );
	}

	/**
	 * Saving does not leave the runtime cache stale.
	 *
	 * @return void
	 */
	public function test_saving_refreshes_the_cache() {
		PostViews_Options::all();

		update_option( PostViews_Options::OPTION, array_merge( PostViews_Options::all(), array( 'template' => 'FRESH' ) ) );

		$this->assertSame( 'FRESH', PostViews_Options::get( 'template' ) );
	}

	/**
	 * The screen is registered under Settings.
	 *
	 * @return void
	 */
	public function test_menu_is_registered() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		global $submenu;
		$submenu = array();

		PostViews_Settings::add_menu();

		$slugs = wp_list_pluck( $submenu['options-general.php'] ?? array(), 2 );
		$this->assertContains( PostViews_Settings::SLUG, $slugs );

		set_current_screen( 'front' );
	}

	/**
	 * The screen renders nothing for a user without the capability.
	 *
	 * WordPress already gates the menu via add_options_page(), but the callback is a public
	 * static method and should not rely on that alone.
	 *
	 * @return void
	 */
	public function test_render_requires_the_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );

		$this->assertSame(
			'',
			$this->capture(
				function () {
					PostViews_Settings::render();
				}
			)
		);
	}

	/**
	 * An administrator gets the form.
	 *
	 * @return void
	 */
	public function test_render_outputs_the_form() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$html = $this->capture(
			function () {
				PostViews_Settings::render();
			}
		);

		$this->assertStringContainsString( 'action="options.php"', $html );
		$this->assertStringContainsString( PostViews_Settings::GROUP, $html );
		$this->assertStringContainsString( 'views-template-template', $html );
		$this->assertStringContainsString( 'views-template-most_viewed_template', $html );

		// Every option key the screen owns has a field.
		foreach ( array_merge( array( 'count', 'exclude_bots' ), $this->display_keys ) as $key ) {
			$this->assertStringContainsString( PostViews_Options::OPTION . '[' . $key . ']', $html, "No field for {$key}." );
		}
	}

	/**
	 * The reset buttons carry the data attributes the script keys off.
	 *
	 * The pairing between the button and its field is the whole contract
	 * between the markup and postviews-admin.js.
	 *
	 * @return void
	 */
	public function test_render_emits_the_reset_buttons() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$html = $this->capture(
			function () {
				PostViews_Settings::render();
			}
		);

		$this->assertStringContainsString( 'data-postviews-reset="template"', $html );
		$this->assertStringContainsString( 'data-postviews-target="views-template-template"', $html );
		$this->assertStringContainsString( 'data-postviews-reset="most_viewed_template"', $html );
		$this->assertStringContainsString( 'data-postviews-target="views-template-most_viewed_template"', $html );

		// The inline handlers 2.0.0 removed must not come back.
		$this->assertStringNotContainsString( 'onclick', $html );
	}

	/**
	 * A stored template cannot break out of the field it is rendered into.
	 *
	 * @return void
	 */
	public function test_render_escapes_the_stored_templates() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		$this->set_options( array( 'template' => '" onmouseover="alert(1)' ) );

		$html = $this->capture(
			function () {
				PostViews_Settings::render();
			}
		);

		$this->assertStringNotContainsString( 'onmouseover="alert(1)"', $html );
		$this->assertStringContainsString( '&quot;', $html );
	}

	/**
	 * The admin script is enqueued with its defaults and without jQuery.
	 *
	 * @return void
	 */
	public function test_admin_script_is_enqueued_and_localised() {
		PostViews_Settings::enqueue_scripts();

		$this->assertTrue( wp_script_is( 'wp-postviews-admin', 'enqueued' ) );
		$this->assertSame( array(), wp_scripts()->registered['wp-postviews-admin']->deps );

		$data = (string) wp_scripts()->get_data( 'wp-postviews-admin', 'data' );
		$this->assertStringContainsString( 'postviewsAdminL10n', $data );
		$this->assertStringContainsString( '%VIEW_COUNT%', $data );
		$this->assertStringContainsString( '%POST_URL%', $data );
	}

	/**
	 * The localised defaults are the same strings the reset button restores.
	 *
	 * @return void
	 */
	public function test_localised_defaults_match_the_option_defaults() {
		PostViews_Settings::enqueue_scripts();

		$data = (string) wp_scripts()->get_data( 'wp-postviews-admin', 'data' );
		preg_match( '/postviewsAdminL10n = (\{.*\});/', $data, $matches );
		$decoded = json_decode( $matches[1], true );

		$this->assertSame( PostViews_Options::default_template( 'template' ), $decoded['defaults']['template'] );
		$this->assertSame( PostViews_Options::default_template( 'most_viewed_template' ), $decoded['defaults']['most_viewed_template'] );
	}

	/**
	 * The AJAX row is only offered when a page cache is in play.
	 *
	 * @return void
	 */
	public function test_ajax_row_is_shown_under_wp_cache() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$html = $this->capture(
			function () {
				PostViews_Settings::render();
			}
		);

		$hidden_field = 'name="' . PostViews_Options::OPTION . '[use_ajax]" value="0"';

		if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
			// A visible select, and no hidden field forcing the value off.
			$this->assertStringContainsString( 'id="views-use_ajax"', $html );
			$this->assertStringNotContainsString( $hidden_field, $html );
		} else {
			// No select; a hidden field pins it off, because with no page
			// cache the setting has no effect either way.
			$this->assertStringNotContainsString( 'id="views-use_ajax"', $html );
			$this->assertStringContainsString( $hidden_field, $html );
		}
	}
}
