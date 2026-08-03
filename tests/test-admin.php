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
class WP_PostViews_Admin_Test extends WP_PostViews_TestCase {

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
	 * Leave no tab selected for the next test.
	 *
	 * @return void
	 */
	public function tear_down() {
		unset( $_GET['tab'] );

		parent::tear_down();
	}

	/**
	 * Render one tab of the settings screen as an administrator.
	 *
	 * @param string $tab Tab slug. The first tab when omitted.
	 * @return string
	 */
	protected function render_tab( $tab = 'settings' ) {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$_GET['tab'] = $tab;

		return $this->capture( array( 'WP_PostViews_Admin', 'render_page' ) );
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
		$this->assertContains( WP_PostViews_Admin::PAGE, $slugs, 'The screen is registered under its own slug.' );

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
			),
			'Without the capability the screen renders nothing.'
		);
	}

	/**
	 * An administrator gets the form.
	 *
	 * @return void
	 */
	public function test_render_outputs_the_form() {
		$html = $this->render_tab();

		$this->assertStringContainsString( 'action="options.php"', $html, 'The form posts to options.php, so core handles the save.' );
		$this->assertStringContainsString( WP_PostViews_Settings::GROUP, $html, 'Naming the settings group the setting is registered in.' );

		// Every option key this tab owns has a field.
		foreach ( array( 'count', 'exclude_bots', 'stats_display', 'stats_most_limit' ) as $key ) {
			$this->assertStringContainsString( WP_PostViews_Options::OPTION . '[' . $key . ']', $html, "No field for {$key}." );
		}

		// And none for a key the plugin retired.
		foreach ( WP_PostViews_Options::retired_keys() as $key ) {
			$this->assertStringNotContainsString( WP_PostViews_Options::OPTION . '[' . $key . ']', $html, "{$key} is retired and must not be on the screen." );
		}
	}

	/**
	 * The Templates tab draws the template fields, and only those.
	 *
	 * @return void
	 */
	public function test_render_outputs_the_templates_tab() {
		$html = $this->render_tab( 'templates' );

		$this->assertStringContainsString( 'action="options.php"', $html, 'The templates tab posts to options.php too.' );
		$this->assertStringContainsString( 'views-template-template', $html, 'And renders the view count template field.' );
		$this->assertStringContainsString( 'views-template-most_viewed_template', $html, 'And the listing template field.' );

		// The variable list sits in the template row's heading cell.
		$this->assertStringContainsString( '<code>%POST_THUMBNAIL_URL%</code>', $html, 'With the token list, so the fields can be filled in without the readme.' );

		// One table, and none of the Settings tab's rows.
		$this->assertSame( 1, substr_count( $html, 'class="form-table"' ), 'There is one form table on the tab, not one per field.' );
		foreach ( array( 'count', 'exclude_bots', 'stats_display', 'stats_most_limit' ) as $key ) {
			$this->assertStringNotContainsString( 'id="views-' . $key . '"', $html, "{$key} belongs to the Settings tab." );
		}
	}

	/**
	 * The two tabs draw disjoint sets of fields.
	 *
	 * The whole point of registering each tab as its own Settings API page: one
	 * page string for both would put every field on both tabs.
	 *
	 * @return void
	 */
	public function test_the_tabs_draw_disjoint_fields() {
		$settings  = $this->render_tab( 'settings' );
		$templates = $this->render_tab( 'templates' );

		$this->assertStringContainsString( 'id="views-count"', $settings, 'The settings tab draws the counting field.' );
		$this->assertStringNotContainsString( 'id="views-count"', $templates, 'And the templates tab does not.' );

		$this->assertStringContainsString( 'id="views-template-template"', $templates, 'The templates tab draws the template field.' );
		$this->assertStringNotContainsString( 'id="views-template-template"', $settings, 'And the settings tab does not, so a save of one cannot blank the other.' );
	}

	/**
	 * The tab nav is on both tabs, marks the one being shown, and links stay
	 * inside this one screen.
	 *
	 * @return void
	 */
	public function test_the_tab_nav() {
		foreach ( array( 'settings', 'templates' ) as $tab ) {
			$html = $this->render_tab( $tab );

			$this->assertStringContainsString( 'class="nav-tab-wrapper"', $html, 'The screen carries the core tab nav wrapper.' );
			$this->assertMatchesRegularExpression( '#>\s*Settings\s*</a>#', $html, 'The Settings tab is offered.' );
			$this->assertMatchesRegularExpression( '#>\s*Templates\s*</a>#', $html, 'The Templates tab is offered.' );

			// One active tab, and it is this one.
			$this->assertSame( 1, substr_count( $html, 'nav-tab-active' ), 'Exactly one tab is active.' );
			$this->assertMatchesRegularExpression(
				'#tab=' . $tab . '[^>]*class="nav-tab nav-tab-active"#',
				$html,
				"The {$tab} tab is not the active one."
			);

			// §4.1: tabs on one page, never a second submenu entry.
			$this->assertStringContainsString( 'options-general.php?page=' . WP_PostViews_Admin::PAGE . '&#038;tab=settings', $html, 'The Settings tab links to itself with its tab argument.' );
			$this->assertStringContainsString( 'options-general.php?page=' . WP_PostViews_Admin::PAGE . '&#038;tab=templates', $html, 'The Templates tab links to itself with its tab argument.' );
		}
	}

	/**
	 * Saving comes back to the tab it was saved from.
	 *
	 * The options.php handler redirects to _wp_http_referer, so without one
	 * naming the tab a save on Templates would answer on Settings.
	 *
	 * @return void
	 */
	public function test_the_form_carries_the_tab_through_the_save() {
		$html = $this->render_tab( 'templates' );

		$this->assertMatchesRegularExpression(
			'#name="_wp_http_referer" value="[^"]*page=' . WP_PostViews_Admin::PAGE . '[^"]*tab=templates"#',
			$html,
			'The referer field carries the tab, so a save returns to the tab it was made on.'
		);

		// It comes after the one settings_fields() emits, because the last value
		// for a repeated name is the one PHP keeps.
		$offsets = array();
		preg_match_all( '#name="_wp_http_referer"#', $html, $matches, PREG_OFFSET_CAPTURE );
		foreach ( $matches[0] as $match ) {
			$offsets[] = $match[1];
		}

		$this->assertCount( 2, $offsets, 'settings_fields() emits one and the screen adds its own.' );
		$this->assertLessThan( $offsets[1], strpos( $html, 'name="_wpnonce"' ), 'The nonce is emitted inside the form, before the fields it protects.' );
	}

	/**
	 * The screen prints no settings notice of its own.
	 *
	 * Core's wp-admin/admin-header.php loads options-head.php for every child
	 * of options-general.php, and that calls settings_errors() already. A second
	 * call here would show "Settings saved." twice.
	 *
	 * @return void
	 */
	public function test_the_screen_does_not_print_the_notice_twice() {
		add_settings_error( 'general', 'settings_updated', 'Settings saved.', 'success' );

		$html = $this->render_tab();

		$this->assertStringNotContainsString( 'Settings saved.', $html, 'The screen does not print the notice core has already printed.' );
	}

	/**
	 * The screen says what it is, in the form the other settings screens use.
	 *
	 * @return void
	 */
	public function test_render_names_the_screen() {
		foreach ( array( 'settings', 'templates' ) as $tab ) {
			$html = $this->render_tab( $tab );

			$this->assertStringContainsString( '<h1>Post Views Settings</h1>', $html, 'The screen is named Settings, per the collection heading rule.' );
			$this->assertStringNotContainsString( 'Post Views Options', $html, 'The old Options heading is gone rather than merely hidden.' );
		}
	}

	/**
	 * The page title matches the heading, and the menu entry is the plugin name.
	 *
	 * §4.1: the sidebar carries the WP- prefix so the family sorts together; the
	 * page title and the h1 say what the screen is and never carry it.
	 *
	 * @return void
	 */
	public function test_the_menu_titles() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		set_current_screen( 'dashboard' );

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
		$this->assertSame( 'WP-PostViews', $entry[0], 'The sidebar carries the plugin name.' );
		$this->assertSame( 'Post Views Settings', $entry[3], 'The page title says what the screen is.' );

		set_current_screen( 'front' );
	}

	/**
	 * The rows are drawn by do_settings_sections(), so the tables, the heading
	 * and the row order all come from the registered sections.
	 *
	 * @return void
	 */
	public function test_render_draws_the_registered_sections() {
		$html = $this->render_tab();

		// One table per section, and nothing hand written alongside them.
		$this->assertSame( 2, substr_count( $html, 'class="form-table"' ), 'do_settings_sections() draws one table per section.' );

		// The second section title, emitted by core from the registration.
		// Matched loosely: WordPress gives a settings heading an id attribute and
		// the attribute has not always been there.
		$this->assertMatchesRegularExpression( '#<h2[^>]*>WP-Stats</h2>#', $html, 'The WP-Stats section is drawn on the settings tab.' );

		// Display Options is gone, and so is the paragraph that introduced it.
		$this->assertDoesNotMatchRegularExpression( '#<h2[^>]*>Display Options</h2>#', $html, 'The withdrawn Display Options section is not drawn.' );

		// Its callback ran.
		$this->assertStringContainsString( 'These settings do nothing without WP-Stats.', $html, 'The registered section description is drawn with its fields.' );

		// Counting rows, then WP-Stats.
		$this->assertLessThan( strpos( $html, 'id="views-stats_display"' ), strpos( $html, 'id="views-count"' ), 'The count field is drawn before the WP-Stats one, which is the registered order.' );
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
		$html = $this->render_tab( 'templates' );

		$this->assertStringContainsString( 'data-postviews-reset="template"', $html, 'The view count template has a reset button.' );
		$this->assertStringContainsString( 'data-postviews-target="views-template-template"', $html, 'Naming the field it resets.' );
		$this->assertStringContainsString( 'data-postviews-reset="most_viewed_template"', $html, 'The listing template has one too.' );
		$this->assertStringContainsString( 'data-postviews-target="views-template-most_viewed_template"', $html, 'Naming its own field.' );

		// The inline handlers 2.0.0 removed must not come back.
		$this->assertStringNotContainsString( 'onclick', $html, 'And neither is an inline handler.' );
	}

	/**
	 * A stored template cannot break out of the field it is rendered into.
	 *
	 * @return void
	 */
	public function test_render_escapes_the_stored_templates() {
		$this->set_options( array( 'template' => '" onmouseover="alert(1)' ) );

		$html = $this->render_tab( 'templates' );

		$this->assertStringNotContainsString( 'onmouseover="alert(1)"', $html, 'A stored template cannot carry a handler onto the screen.' );
		$this->assertStringContainsString( '&quot;', $html, 'It is escaped for the attribute it is printed into.' );
	}

	/**
	 * The admin script is enqueued with its defaults and without jQuery.
	 *
	 * @return void
	 */
	public function test_admin_script_is_enqueued_and_localised() {
		WP_PostViews_Admin::enqueue_scripts();

		$this->assertTrue( wp_script_is( 'wp-postviews-admin', 'enqueued' ), 'The admin script is enqueued on this screen.' );
		$this->assertSame( array(), wp_scripts()->registered['wp-postviews-admin']->deps, 'The admin script declares no dependencies, so nothing is pulled in behind it.' );

		$data = (string) wp_scripts()->get_data( 'wp-postviews-admin', 'data' );
		$this->assertStringContainsString( 'wpPostViewsL10n', $data, 'The localised object is attached under the name the script reads.' );
		$this->assertStringContainsString( '%VIEW_COUNT%', $data, 'Carrying the view count token.' );
		$this->assertStringContainsString( '%POST_URL%', $data, 'And the post URL token, which is what the reset buttons restore.' );
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

		$this->assertSame( WP_PostViews_Options::default_template( 'template' ), $decoded['defaults']['template'], 'The localised view count default is the option default.' );
		$this->assertSame( WP_PostViews_Options::default_template( 'most_viewed_template' ), $decoded['defaults']['most_viewed_template'], 'And so is the listing default, so a reset really restores.' );
	}

	/**
	 * The AJAX row is only offered when a page cache is in play.
	 *
	 * @return void
	 */
	public function test_ajax_row_is_shown_under_wp_cache() {
		$html = $this->render_tab();

		$hidden_field = 'name="' . WP_PostViews_Options::OPTION . '[use_ajax]" value="0"';

		if ( defined( 'WP_CACHE' ) && WP_CACHE ) {
			// A visible select, and no hidden field forcing the value off.
			$this->assertStringContainsString( 'id="views-use_ajax"', $html, 'Under WP_CACHE the AJAX row is offered.' );
			$this->assertStringNotContainsString( $hidden_field, $html, 'With no hidden field, because the real one is on screen.' );
		} else {
			// No select; a hidden field pins it off, because with no page
			// cache the setting has no effect either way.
			$this->assertStringNotContainsString( 'id="views-use_ajax"', $html, 'Without WP_CACHE the row is not offered.' );
			$this->assertStringContainsString( $hidden_field, $html, 'And a hidden field carries the stored value, so saving cannot blank it.' );
		}

		// Either way the pin belongs to the tab that owns the row. On Templates
		// it would be a field from another tab riding along on every save.
		$this->assertStringNotContainsString( $hidden_field, $this->render_tab( 'templates' ), 'The other tab carries no hidden copy of it.' );
	}

	/**
	 * The capability every gate reads goes through one filter.
	 *
	 * @return void
	 */
	public function test_the_capability_is_filterable() {
		$this->assertSame( 'manage_options', WP_PostViews_Admin::capability(), 'The default capability is manage_options.' );

		add_filter(
			'wp_postviews_capability',
			static function () {
				return 'edit_theme_options';
			}
		);

		$this->assertSame( 'edit_theme_options', WP_PostViews_Admin::capability(), 'And a filter can replace it.' );
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

		$this->assertSame( 'settings', $seen, 'The filter is told which screen is asking.' );
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
		$this->assertSame( 'edit_theme_options', $entry[1], 'And the menu is registered with the filtered capability, not the default.' );

		set_current_screen( 'front' );
	}

	/**
	 * The screen draws the WP-Stats rows too.
	 *
	 * @return void
	 */
	public function test_render_draws_the_wp_stats_rows() {
		$html = $this->render_tab();

		$this->assertStringContainsString( 'id="views-stats_display"', $html, 'The WP-Stats toggle is drawn.' );
		$this->assertStringContainsString( 'id="views-stats_most_limit"', $html, 'The row limit.' );
		$this->assertStringContainsString( 'WP-Stats', $html, 'And the section says what they are for.' );
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
		foreach ( array( 'settings', 'templates' ) as $tab ) {
			$html = $this->render_tab( $tab );

			foreach ( array( 'style=', 'valign=', 'align=', 'width=' ) as $attribute ) {
				$this->assertStringNotContainsString( $attribute, $html, $attribute . ' has no business on a settings screen.' );
			}

			$this->assertSame( 1, substr_count( $html, '<h1>' ), 'One h1 per screen.' );
		}
	}
}
