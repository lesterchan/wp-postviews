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
}
