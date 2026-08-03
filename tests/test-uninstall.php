<?php
/**
 * The uninstall routine.
 *
 * The multisite branch is exercised for real in WP_PostViews_Multisite_Test,
 * which runs under WP_MULTISITE=1. The source level assertions below are kept
 * as a second line of defence, because the single site CI job is the one that
 * always runs: they cost nothing and they catch a regression even if the
 * multisite job is skipped or removed.
 *
 * @package WP-PostViews
 */

/**
 * Uninstall routine.
 */
class WP_PostViews_Uninstall_Test extends WP_PostViews_TestCase {

	/**
	 * The uninstall.php source, with comments stripped.
	 *
	 * Matching the raw file would report the fix as the bug: the comment
	 * explaining why wp_get_sites() is not used contains that very string.
	 *
	 * @return string
	 */
	protected function uninstall_source() {
		$source = '';

		foreach ( token_get_all( file_get_contents( dirname( __DIR__ ) . '/uninstall.php' ) ) as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			$source .= is_array( $token ) ? $token[1] : $token;
		}

		return $source;
	}

	/**
	 * The site query is uncapped and asks only for IDs.
	 *
	 * @return void
	 */
	public function test_site_query_is_uncapped() {
		$source = $this->uninstall_source();

		$this->assertMatchesRegularExpression(
			"/'number'\s*=>\s*0/",
			$source,
			"get_sites() defaults to 100 sites; 'number' => 0 lifts the cap."
		);
		$this->assertMatchesRegularExpression(
			"/'fields'\s*=>\s*'ids'/",
			$source,
			'Hydrating full WP_Site objects for a large network is wasted work.'
		);
	}

	/**
	 * The function deprecated in WP 4.6, and capped at 100 sites, is not called.
	 *
	 * @return void
	 */
	public function test_deprecated_function_is_not_called() {
		$this->assertStringNotContainsString(
			'wp_get_sites',
			$this->uninstall_source(),
			'wp_get_sites() is capped at 100 sites, so a larger network uninstalls in part.'
		);
	}

	/**
	 * The blog is restored inside the loop.
	 *
	 * Calling switch_to_blog() pushes onto a stack, so restoring once after the loop
	 * unwinds it by exactly one.
	 *
	 * @return void
	 */
	public function test_blog_is_restored_inside_the_loop() {
		$this->assertMatchesRegularExpression(
			'/switch_to_blog.*?restore_current_blog.*?\}/s',
			$this->uninstall_source(),
			'The restore sits inside the loop; once after it leaves the stack unwound by one.'
		);
	}

	/**
	 * No bare global function called uninstall().
	 *
	 * @return void
	 */
	public function test_no_generic_global_function() {
		$this->assertDoesNotMatchRegularExpression(
			'/function\s+uninstall\s*\(/',
			$this->uninstall_source(),
			'A global function called uninstall() will collide with another plugin eventually.'
		);
	}

	/**
	 * Uninstalling removes this plugin's data and nothing else.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_only_our_data() {
		if ( is_multisite() ) {
			$this->markTestSkipped( 'The network branch is covered by WP_PostViews_Multisite_Test.' );
		}

		$post_id = $this->make_post( array(), 500 );
		update_post_meta( $post_id, 'keep_me', 'do not delete' );
		update_option( 'widget_views', array( 'test' => 1 ) );
		update_option( WP_PostViews_Options::LEGACY_OPTION, array( 'count' => 1 ) );
		update_option( WP_PostViews_Options::LEGACY_VERSION, '1.78.1' );
		WP_PostViews_Options::update_markers();

		$this->run_uninstall();

		$this->assertFalse( get_option( WP_PostViews_Options::OPTION ), 'Uninstall deletes the settings row.' );
		$this->assertFalse( get_option( WP_PostViews_Options::VERSION ), 'Uninstall deletes the version row.' );
		$this->assertFalse( get_option( 'widget_views' ), 'Uninstall deletes the widget instance row.' );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'views', true ) );

		// An upgrade that never ran leaves the pre-2.0.0 rows on disk, so
		// uninstall has to clear those too.
		$this->assertFalse( get_option( WP_PostViews_Options::LEGACY_OPTION ), 'Uninstall deletes the legacy settings row.' );
		$this->assertFalse( get_option( WP_PostViews_Options::LEGACY_VERSION ), 'Uninstall deletes the legacy version row.' );

		$this->assertSame( 'do not delete', get_post_meta( $post_id, 'keep_me', true ) );
		$this->assertNotNull( get_post( $post_id ), 'Uninstall leaves the posts alone; only this plugin data goes.' );
	}

	/**
	 * The shared WP-Stats rows are left for the siblings still reading them.
	 *
	 * @return void
	 */
	public function test_uninstall_spares_the_shared_wp_stats_rows() {
		update_option( WP_PostViews_Options::LEGACY_STATS_DISPLAY, array( 'polls' => 1 ) );

		$this->run_uninstall();

		$this->assertSame(
			array( 'polls' => 1 ),
			get_option( WP_PostViews_Options::LEGACY_STATS_DISPLAY ),
			"Deleting one plugin must not reconfigure a sibling's WP-Stats block."
		);

		delete_option( WP_PostViews_Options::LEGACY_STATS_DISPLAY );
	}
}
