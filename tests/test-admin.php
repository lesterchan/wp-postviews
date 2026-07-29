<?php
/**
 * The admin surface WP_PostViews_Admin owns: the one menu entry, the screen
 * behind it, the script that screen loads, and the Views column.
 *
 * §4.2 splits the settings screen in two -- Admin owns the menu and the screens,
 * WP_PostViews_Settings owns register_setting(), the sections, the fields and the
 * sanitiser -- so the assertions follow the same line. What the form contains is
 * tested in test-settings.php; that it is reachable, gated and drawn is tested
 * here.
 *
 * @package WP-PostViews
 */

/**
 * The menu, the settings screen and the admin script.
 */
class Test_PostViews_Admin extends WP_PostViews_TestCase {

	/**
	 * Make sure the sections and fields the screen draws are registered.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		WP_PostViews_Settings::register();
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

		WP_PostViews_Admin::add_menu();

		$slugs = wp_list_pluck( $submenu['options-general.php'] ?? array(), 2 );
		$this->assertContains( WP_PostViews_Admin::PAGE, $slugs );

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
					WP_PostViews_Admin::render_page();
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
				WP_PostViews_Admin::render_page();
			}
		);

		$this->assertStringContainsString( 'action="options.php"', $html );
		$this->assertStringContainsString( WP_PostViews_Settings::GROUP, $html );
		$this->assertStringContainsString( 'views-template-template', $html );
		$this->assertStringContainsString( 'views-template-most_viewed_template', $html );

		// Every option key the screen owns has a field.
		foreach ( array_merge( array( 'count', 'exclude_bots' ), $this->display_keys ) as $key ) {
			$this->assertStringContainsString( WP_PostViews_Options::OPTION . '[' . $key . ']', $html, "No field for {$key}." );
		}
	}
	/**
	 * The rows are drawn by do_settings_sections(), so the tables, the heading
	 * and the row order all come from the registered sections.
	 *
	 * @return void
	 */
	public function test_render_draws_the_registered_sections() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$html = $this->capture(
			function () {
				WP_PostViews_Admin::render_page();
			}
		);

		// One table per section, and nothing hand written alongside them.
		$this->assertSame( 2, substr_count( $html, 'class="form-table"' ) );

		// The second section's title, emitted by core from the registration.
		// Matched loosely: WordPress 7.0 gives the heading an id attribute, 6.0
		// does not.
		$this->assertMatchesRegularExpression( '#<h2[^>]*>Display Options</h2>#', $html );

		// Its callback ran.
		$this->assertStringContainsString( '<code>the_views()</code>', $html );

		// Counting rows come before the display matrix.
		$this->assertLessThan( strpos( $html, 'id="views-display_home"' ), strpos( $html, 'id="views-count"' ) );

		// The variable list still sits in the template row's heading cell.
		$this->assertStringContainsString( '<code>%POST_THUMBNAIL_URL%</code>', $html );
	}
	/**
	 * The reset buttons carry the data attributes the script keys off.
	 *
	 * The pairing between the button and its field is the whole contract
	 * between the markup and js/wp-postviews-admin.js.
	 *
	 * @return void
	 */
	public function test_render_emits_the_reset_buttons() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$html = $this->capture(
			function () {
				WP_PostViews_Admin::render_page();
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
				WP_PostViews_Admin::render_page();
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
		WP_PostViews_Admin::enqueue_scripts();

		$this->assertTrue( wp_script_is( 'wp-postviews-admin', 'enqueued' ) );
		$this->assertSame( array(), wp_scripts()->registered['wp-postviews-admin']->deps );

		$data = (string) wp_scripts()->get_data( 'wp-postviews-admin', 'data' );
		$this->assertStringContainsString( 'wpPostViewsL10n', $data );
		$this->assertStringContainsString( '%VIEW_COUNT%', $data );
		$this->assertStringContainsString( '%POST_URL%', $data );
	}
	/**
	 * The localised defaults are the same strings the reset button restores.
	 *
	 * @return void
	 */
	public function test_localised_defaults_match_the_option_defaults() {
		WP_PostViews_Admin::enqueue_scripts();

		$data = (string) wp_scripts()->get_data( 'wp-postviews-admin', 'data' );
		preg_match( '/wpPostViewsL10n = (\{.*\});/', $data, $matches );
		$decoded = json_decode( $matches[1], true );

		$this->assertSame( WP_PostViews_Options::default_template( 'template' ), $decoded['defaults']['template'] );
		$this->assertSame( WP_PostViews_Options::default_template( 'most_viewed_template' ), $decoded['defaults']['most_viewed_template'] );
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
				WP_PostViews_Admin::render_page();
			}
		);

		$hidden_field = 'name="' . WP_PostViews_Options::OPTION . '[use_ajax]" value="0"';

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

	/**
	 * The capability every gate reads goes through one filter.
	 *
	 * @return void
	 */
	public function test_the_capability_is_filterable() {
		$this->assertSame( 'manage_options', WP_PostViews_Admin::capability() );

		add_filter(
			'wp_postviews_capability',
			static function () {
				return 'edit_theme_options';
			}
		);

		$this->assertSame( 'edit_theme_options', WP_PostViews_Admin::capability() );
	}

	/**
	 * The filter is told what is being gated.
	 *
	 * @return void
	 */
	public function test_the_capability_filter_receives_its_context() {
		$seen = null;

		add_filter(
			'wp_postviews_capability',
			static function ( $capability, $context ) use ( &$seen ) {
				$seen = $context;

				return $capability;
			},
			10,
			2
		);

		WP_PostViews_Admin::capability();

		$this->assertSame( 'settings', $seen );
	}

	/**
	 * The menu entry is gated on the filtered capability, not a literal.
	 *
	 * @return void
	 */
	public function test_the_menu_uses_the_filtered_capability() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

		add_filter(
			'wp_postviews_capability',
			static function () {
				return 'edit_theme_options';
			}
		);

		global $submenu;
		$submenu = array();

		WP_PostViews_Admin::add_menu();

		$entry = null;
		foreach ( $submenu['options-general.php'] ?? array() as $row ) {
			if ( WP_PostViews_Admin::PAGE === $row[2] ) {
				$entry = $row;
			}
		}

		$this->assertNotNull( $entry, 'The menu entry was not registered.' );
		$this->assertSame( 'edit_theme_options', $entry[1] );

		set_current_screen( 'front' );
	}

	/**
	 * The screen draws the WP-Stats rows too.
	 *
	 * @return void
	 */
	public function test_render_draws_the_wp_stats_rows() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$html = $this->capture( array( 'WP_PostViews_Admin', 'render_page' ) );

		$this->assertStringContainsString( 'id="views-stats_display"', $html );
		$this->assertStringContainsString( 'id="views-stats_most_limit"', $html );
		$this->assertStringContainsString( 'WP-Stats Options', $html );
	}

	/**
	 * The screen carries no inline style, width, valign or align attribute.
	 *
	 * §4.4 allows core classes and nothing else. Every one of these used to be in
	 * the hand written table 2.0.0 replaced.
	 *
	 * @return void
	 */
	public function test_render_uses_core_classes_and_no_inline_attributes() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$html = $this->capture( array( 'WP_PostViews_Admin', 'render_page' ) );

		foreach ( array( 'style=', 'valign=', 'align=', 'width=' ) as $attribute ) {
			$this->assertStringNotContainsString( $attribute, $html, $attribute . ' has no business on a settings screen.' );
		}

		$this->assertSame( 1, substr_count( $html, '<h1>' ), 'One h1 per screen.' );
	}
}
