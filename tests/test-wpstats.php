<?php
/**
 * The WP-Stats integration.
 *
 * WP-Stats is a separate plugin that may not be installed, so these drive the
 * one filter directly. The whole point of the contract is that neither plugin
 * reads the other's option row: this one decides whether to contribute by
 * reading nothing but its own settings, and the entry it returns has to match
 * the shape §13.1 pins down or WP-Stats silently skips it.
 *
 * @package WP-PostViews
 */

/**
 * The wp_stats_sections contribution.
 */
class WP_PostViews_WPStats_Test extends WP_PostViews_TestCase {

	/**
	 * The entry this plugin contributes, or null when it opted out.
	 *
	 * @return array|null
	 */
	protected function section() {
		$sections = WP_PostViews_WPStats::register_section( array() );

		return $sections['wp_postviews'] ?? null;
	}

	// --- registering ------------------------------------------------------

	/**
	 * The filter is the only thing the class hooks.
	 *
	 * A contributor supplies a title and a render callback and hooks nothing
	 * else. Hanging a listener on wp_stats_section_wp_postviews here would take
	 * the heading away from WP-Stats, which is what owns it.
	 *
	 * @return void
	 */
	public function test_only_the_sections_filter_is_hooked() {
		WP_PostViews_WPStats::init();

		$this->assertNotFalse(
			has_filter( 'wp_stats_sections', array( 'WP_PostViews_WPStats', 'register_section' ) ),
			'Nothing is attached to wp_stats_sections.'
		);
		$this->assertFalse( has_action( 'wp_stats_section_wp_postviews' ), 'A contributor must not render its own heading.' );
	}

	/**
	 * The entry is keyed by the slug with underscores.
	 *
	 * @return void
	 */
	public function test_the_entry_is_keyed_by_the_underscored_slug() {
		$sections = WP_PostViews_WPStats::register_section( array() );

		$this->assertSame( array( 'wp_postviews' ), array_keys( $sections ), 'The section must be keyed wp_postviews and nothing else.' );
	}

	/**
	 * The entry carries exactly the three documented keys, correctly typed.
	 *
	 * @return void
	 */
	public function test_the_entry_matches_the_documented_shape() {
		$section = $this->section();

		$this->assertIsArray( $section, 'The entry must be an array or WP-Stats skips it.' );
		$this->assertSame( array( 'title', 'priority', 'render' ), array_keys( $section ), 'The entry carries title, priority and render.' );
		$this->assertIsString( $section['title'], 'title must be a string.' );
		$this->assertNotSame( '', $section['title'], 'An empty title makes WP-Stats skip the entry.' );
		$this->assertIsInt( $section['priority'], 'priority must be an int.' );
		$this->assertTrue( is_callable( $section['render'] ), 'render must pass is_callable().' );
	}

	/**
	 * The render callback takes no arguments.
	 *
	 * WP-Stats calls it through call_user_func() with nothing, so a required
	 * parameter would be an ArgumentCountError on the stats page.
	 *
	 * @return void
	 */
	public function test_the_render_callback_takes_no_required_arguments() {
		$reflection = new ReflectionMethod( 'WP_PostViews_WPStats', 'render' );

		$this->assertSame( 0, $reflection->getNumberOfRequiredParameters(), 'render() must be callable with no arguments.' );
	}

	/**
	 * Whatever the filter was handed comes back untouched alongside our entry.
	 *
	 * Getting this wrong would wipe out every other plugin's section.
	 *
	 * @return void
	 */
	public function test_other_plugins_entries_are_preserved() {
		$sections = WP_PostViews_WPStats::register_section(
			array(
				'wp_polls' => array( 'title' => 'Polls' ),
			)
		);

		$this->assertArrayHasKey( 'wp_polls', $sections, 'A sibling section was dropped.' );
		$this->assertArrayHasKey( 'wp_postviews', $sections, 'This plugin entry is added alongside the others already there.' );
	}

	/**
	 * A non-array filter value does not fatal.
	 *
	 * @return void
	 */
	public function test_a_non_array_filter_value_is_tolerated() {
		$sections = WP_PostViews_WPStats::register_section( null );

		$this->assertArrayHasKey( 'wp_postviews', $sections, 'A non-array from an earlier filter is replaced rather than fatal.' );
	}

	/**
	 * Opting out contributes nothing at all.
	 *
	 * Not an entry with an empty body: WP-Stats would still draw a heading for
	 * that, and a site that switched the block off would see a bare title.
	 *
	 * @return void
	 */
	public function test_opting_out_contributes_nothing() {
		$this->set_options( array( 'stats_display' => false ) );

		$sections = WP_PostViews_WPStats::register_section( array( 'wp_polls' => array( 'title' => 'Polls' ) ) );

		$this->assertSame( array( 'wp_polls' ), array_keys( $sections ), 'An opted-out contributor must return $sections untouched.' );
	}

	/**
	 * A fresh install contributes, because the shipped default is on.
	 *
	 * Read through the options accessor, not get_option(): a row nothing has
	 * written yet must look like the default rather than like an opt-out.
	 *
	 * @return void
	 */
	public function test_an_unwritten_option_row_still_contributes() {
		delete_option( WP_PostViews_Options::OPTION );
		WP_PostViews_Options::flush();

		$this->assertNotNull( $this->section(), 'A fresh install must not look opted out.' );
	}

	// --- rendering --------------------------------------------------------

	/**
	 * The body is echoed, not returned.
	 *
	 * WP-Stats assembles the page under ob_start(), so a returned string is
	 * dropped without a word.
	 *
	 * @return void
	 */
	public function test_the_body_is_echoed_rather_than_returned() {
		$this->make_post( array(), 500 );

		$returned = 'unset';
		$echoed   = $this->capture(
			function () use ( &$returned ) {
				$returned = WP_PostViews_WPStats::render();
			}
		);

		$this->assertNull( $returned, 'render() must not return its markup.' );
		$this->assertNotSame( '', $echoed, 'render() echoed nothing.' );
	}

	/**
	 * The plugin does not echo its own section heading.
	 *
	 * @return void
	 */
	public function test_the_section_title_is_left_to_wp_stats() {
		$this->make_post( array(), 500 );

		$html = $this->capture( array( 'WP_PostViews_WPStats', 'render' ) );

		$this->assertStringNotContainsString(
			'<strong>' . $this->section()['title'] . '</strong>',
			$html,
			'WP-Stats echoes the title; a contributor must not repeat it.'
		);
	}

	/**
	 * The body reports the summed view count.
	 *
	 * @return void
	 */
	public function test_the_body_reports_the_total() {
		$this->make_post( array(), 1000 );
		$this->make_post( array(), 234 );

		$html = $this->capture( array( 'WP_PostViews_WPStats', 'render' ) );

		$this->assertStringContainsString( '<strong>1,234</strong>', $html );
		$this->assertStringContainsString( 'views were generated', $html );
	}

	/**
	 * A single view uses the singular form.
	 *
	 * @return void
	 */
	public function test_the_total_is_pluralised() {
		$this->make_post( array(), 1 );

		$html = $this->capture( array( 'WP_PostViews_WPStats', 'render' ) );

		$this->assertStringContainsString( 'view was generated', $html );
		$this->assertStringNotContainsString( 'views were generated', $html );
	}

	/**
	 * Both listings appear, each scoped to its own post type.
	 *
	 * @return void
	 */
	public function test_both_listings_are_scoped_to_their_post_type() {
		$this->set_options(
			array(
				'most_viewed_template' => '<li>%POST_TITLE%</li>',
				'stats_most_limit'     => 5,
			)
		);
		$this->make_post( array( 'post_title' => 'Popular Post' ), 900 );
		$this->make_post(
			array(
				'post_title' => 'A Page',
				'post_type'  => 'page',
			),
			950
		);

		$html = $this->capture( array( 'WP_PostViews_WPStats', 'render' ) );

		$this->assertStringContainsString( '5 Most Viewed Posts', $html );
		$this->assertStringContainsString( '5 Most Viewed Pages', $html );

		$posts_at = strpos( $html, 'Most Viewed Posts' );
		$pages_at = strpos( $html, 'Most Viewed Pages' );

		$posts_block = substr( $html, $posts_at, $pages_at - $posts_at );

		$this->assertStringContainsString( '<li>Popular Post</li>', $posts_block );
		$this->assertStringNotContainsString( 'A Page', $posts_block, 'A page reached the posts listing.' );
		$this->assertStringContainsString( '<li>A Page</li>', $html );
	}

	/**
	 * A limit of one uses the singular headings.
	 *
	 * @return void
	 */
	public function test_the_listing_headings_are_pluralised() {
		$this->set_options( array( 'stats_most_limit' => 1 ) );

		$html = $this->capture( array( 'WP_PostViews_WPStats', 'render' ) );

		$this->assertStringContainsString( '1 Most Viewed Post<', $html );
		$this->assertStringContainsString( '1 Most Viewed Page<', $html );
	}

	/**
	 * An empty listing says so rather than leaving a bare heading.
	 *
	 * @return void
	 */
	public function test_an_empty_listing_renders_a_placeholder() {
		$html = $this->capture( array( 'WP_PostViews_WPStats', 'render' ) );

		$this->assertStringContainsString( 'N/A', $html );
	}

	// --- the limit --------------------------------------------------------

	/**
	 * The limit comes from this plugin's own row.
	 *
	 * @return void
	 */
	public function test_the_limit_is_read_from_our_own_settings() {
		$this->set_options( array( 'stats_most_limit' => 25 ) );

		$this->assertSame( 25, WP_PostViews_WPStats::most_limit() );
	}

	/**
	 * A junk or zero limit floors at one rather than listing nothing.
	 *
	 * @dataProvider data_useless_limits
	 *
	 * @param mixed $stored Whatever the row holds.
	 * @return void
	 */
	public function test_a_useless_limit_floors_at_one( $stored ) {
		$this->set_options( array( 'stats_most_limit' => $stored ) );

		$this->assertSame( 1, WP_PostViews_WPStats::most_limit() );
	}

	/**
	 * Values a hand-edited or half-migrated row can hold.
	 *
	 * @return array
	 */
	public function data_useless_limits() {
		return array(
			'zero'         => array( 0 ),
			'negative'     => array( -5 ),
			'empty string' => array( '' ),
			'nonsense'     => array( 'nonsense' ),
		);
	}

	/**
	 * Nothing here reads the shared row WP-Stats used to own.
	 *
	 * The contract is that a contributor reads only its own settings. A leftover
	 * read of stats_display would make the block depend on which sibling
	 * migrated first, which is the failure §13.2 exists to prevent.
	 *
	 * @return void
	 */
	public function test_the_shared_wp_stats_row_is_never_read() {
		// Comments stripped, because the one mention of get_option in this file
		// is a comment explaining why it is not called. php_strip_whitespace()
		// removes comments as well as whitespace, so it leaves code only.
		$code = php_strip_whitespace( dirname( __DIR__ ) . '/includes/class-wp-postviews-wpstats.php' );

		$this->assertStringNotContainsString( 'get_option', $code, 'The WP-Stats contributor must go through WP_PostViews_Options.' );
	}
}
