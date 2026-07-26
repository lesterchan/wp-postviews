<?php
/**
 * The WP-Stats integration.
 *
 * WP-Stats is a separate plugin that may not be installed, so these run
 * against the four filters directly. The options they read - stats_display and
 * stats_mostlimit - belong to that plugin, which is why every accessor has to
 * cope with them being absent or the wrong shape.
 *
 * @package WP-PostViews
 */

/**
 * WP-Stats panels.
 */
class Test_PostViews_Admin extends PostViews_TestCase {

	/**
	 * Clean up the foreign options between tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		delete_option( 'stats_display' );
		delete_option( 'stats_mostlimit' );

		parent::tear_down();
	}

	/**
	 * Turn on some WP-Stats toggles.
	 *
	 * @param array $keys  Toggle names to enable.
	 * @param int   $limit Value for stats_mostlimit.
	 * @return void
	 */
	protected function enable_stats( $keys, $limit = 10 ) {
		update_option( 'stats_display', array_fill_keys( $keys, 1 ) );
		update_option( 'stats_mostlimit', $limit );
	}

	/**
	 * The four filters are attached once plugins have loaded.
	 *
	 * @return void
	 */
	public function test_filters_are_registered() {
		PostViews_Admin::register_wp_stats();

		foreach ( array( 'wp_stats_page_admin_plugins', 'wp_stats_page_admin_most', 'wp_stats_page_plugins', 'wp_stats_page_most' ) as $hook ) {
			$this->assertNotFalse( has_filter( $hook ), "Nothing is attached to {$hook}." );
		}
	}

	/**
	 * The options screen checkbox is unticked by default.
	 *
	 * @return void
	 */
	public function test_admin_general_checkbox_unchecked() {
		$html = PostViews_Admin::wp_stats_admin_general( '' );

		$this->assertStringContainsString( 'name="stats_display[]"', $html );
		$this->assertStringContainsString( 'value="views"', $html );
		$this->assertStringContainsString( 'id="wpstats_views"', $html );
		$this->assertStringNotContainsString( 'checked', $html );
	}

	/**
	 * It is ticked once WP-Stats has the toggle on.
	 *
	 * @return void
	 */
	public function test_admin_general_checkbox_checked() {
		$this->enable_stats( array( 'views' ) );

		$this->assertStringContainsString( "checked='checked'", PostViews_Admin::wp_stats_admin_general( '' ) );
	}

	/**
	 * Each filter appends to what it was given rather than replacing it.
	 *
	 * Getting this wrong would wipe out every other plugin's WP-Stats panel.
	 *
	 * @return void
	 */
	public function test_filters_append_to_existing_content() {
		$this->enable_stats( array( 'views', 'viewed_most_post' ) );
		$this->make_post( array(), 500 );

		foreach ( array( 'wp_stats_admin_general', 'wp_stats_admin_most', 'wp_stats_general', 'wp_stats_most' ) as $method ) {
			$out = PostViews_Admin::$method( 'EXISTING' );
			$this->assertStringStartsWith( 'EXISTING', $out, "{$method}() dropped the content it was handed." );
		}
	}

	/**
	 * The most viewed checkboxes carry the configured limit.
	 *
	 * @return void
	 */
	public function test_admin_most_checkboxes_use_the_limit() {
		$this->enable_stats( array( 'viewed_most_post' ), 25 );

		$html = PostViews_Admin::wp_stats_admin_most( '' );

		$this->assertStringContainsString( 'id="wpstats_viewed_most_post"', $html );
		$this->assertStringContainsString( 'id="wpstats_viewed_most_page"', $html );
		$this->assertStringContainsString( '25 Most Viewed Post', $html );
		$this->assertStringContainsString( '25 Most Viewed Page', $html );
	}

	/**
	 * The totals panel reports the summed view count.
	 *
	 * @return void
	 */
	public function test_general_panel_reports_the_total() {
		$this->enable_stats( array( 'views' ) );
		$this->make_post( array(), 1000 );
		$this->make_post( array(), 234 );

		$html = PostViews_Admin::wp_stats_general( '' );

		$this->assertStringContainsString( '<strong>1,234</strong>', $html );
		$this->assertStringContainsString( 'views were generated', $html );
	}

	/**
	 * A single view uses the singular form.
	 *
	 * @return void
	 */
	public function test_general_panel_is_pluralised() {
		$this->enable_stats( array( 'views' ) );
		$this->make_post( array(), 1 );

		$html = PostViews_Admin::wp_stats_general( '' );

		$this->assertStringContainsString( 'view was generated', $html );
		$this->assertStringNotContainsString( 'views were generated', $html );
	}

	/**
	 * Nothing is emitted when the toggle is off.
	 *
	 * @return void
	 */
	public function test_general_panel_respects_its_toggle() {
		$this->make_post( array(), 500 );

		$this->assertSame( '', PostViews_Admin::wp_stats_general( '' ) );
	}

	/**
	 * The most viewed panel lists posts, and only when enabled.
	 *
	 * @return void
	 */
	public function test_most_panel_lists_posts() {
		$this->enable_stats( array( 'viewed_most_post' ), 5 );
		$this->set_options( array( 'most_viewed_template' => '<li>%POST_TITLE%</li>' ) );
		$this->make_post( array( 'post_title' => 'Popular Post' ), 900 );
		$this->make_post(
			array(
				'post_title' => 'A Page',
				'post_type'  => 'page',
			),
			950
		);

		$html = PostViews_Admin::wp_stats_most( '' );

		$this->assertStringContainsString( '5 Most Viewed Post', $html );
		$this->assertStringContainsString( '<li>Popular Post</li>', $html );
		// The page panel is a separate toggle and was left off.
		$this->assertStringNotContainsString( 'A Page', $html );
		$this->assertStringNotContainsString( 'Most Viewed Page', $html );
	}

	/**
	 * The page panel scopes to pages.
	 *
	 * @return void
	 */
	public function test_most_panel_scopes_pages() {
		$this->enable_stats( array( 'viewed_most_page' ), 5 );
		$this->set_options( array( 'most_viewed_template' => '<li>%POST_TITLE%</li>' ) );
		$this->make_post( array( 'post_title' => 'Popular Post' ), 900 );
		$this->make_post(
			array(
				'post_title' => 'A Page',
				'post_type'  => 'page',
			),
			950
		);

		$html = PostViews_Admin::wp_stats_most( '' );

		$this->assertStringContainsString( '<li>A Page</li>', $html );
		$this->assertStringNotContainsString( 'Popular Post', $html );
	}

	/**
	 * Both panels can be on at once.
	 *
	 * @return void
	 */
	public function test_most_panel_renders_both() {
		$this->enable_stats( array( 'viewed_most_post', 'viewed_most_page' ), 5 );
		$this->set_options( array( 'most_viewed_template' => '<li>%POST_TITLE%</li>' ) );
		$this->make_post( array( 'post_title' => 'Popular Post' ), 900 );
		$this->make_post(
			array(
				'post_title' => 'A Page',
				'post_type'  => 'page',
			),
			950
		);

		$html = PostViews_Admin::wp_stats_most( '' );

		$this->assertStringContainsString( '<li>Popular Post</li>', $html );
		$this->assertStringContainsString( '<li>A Page</li>', $html );
	}

	/**
	 * A missing or malformed stats_display must not fatal.
	 *
	 * WP-Stats may simply not be installed, in which case get_option() returns
	 * false and the pre-2.0.0 code raised "Trying to access array offset on
	 * value of type bool" on every admin page WP-Stats hooked.
	 *
	 * @dataProvider data_broken_stats_display
	 *
	 * @param mixed $value Whatever stats_display holds.
	 * @return void
	 */
	public function test_survives_a_missing_or_broken_stats_display( $value ) {
		if ( null === $value ) {
			delete_option( 'stats_display' );
		} else {
			update_option( 'stats_display', $value );
		}
		$this->make_post( array(), 500 );

		$this->assertSame( '', PostViews_Admin::wp_stats_general( '' ) );
		$this->assertSame( '', PostViews_Admin::wp_stats_most( '' ) );
		$this->assertIsString( PostViews_Admin::wp_stats_admin_general( '' ) );
		$this->assertIsString( PostViews_Admin::wp_stats_admin_most( '' ) );
	}

	/**
	 * Shapes stats_display can be in when WP-Stats is absent or half set up.
	 *
	 * @return array
	 */
	public function data_broken_stats_display() {
		return array(
			'absent'       => array( null ),
			'false'        => array( false ),
			'empty string' => array( '' ),
			'a string'     => array( 'nonsense' ),
			'empty array'  => array( array() ),
			'wrong keys'   => array( array( 'something_else' => 1 ) ),
		);
	}

	/**
	 * A missing stats_mostlimit degrades to zero rather than warning.
	 *
	 * @return void
	 */
	public function test_survives_a_missing_stats_mostlimit() {
		update_option( 'stats_display', array( 'viewed_most_post' => 1 ) );
		delete_option( 'stats_mostlimit' );
		$this->make_post( array(), 500 );

		$this->assertIsString( PostViews_Admin::wp_stats_admin_most( '' ) );
		$this->assertIsString( PostViews_Admin::wp_stats_most( '' ) );
	}
}
