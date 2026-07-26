<?php
/**
 * The uninstall routine.
 *
 * The multisite branch is exercised for real in Test_PostViews_Multisite,
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
class Test_PostViews_Uninstall extends PostViews_TestCase {

	/**
	 * The uninstall.php source, with comments stripped.
	 *
	 * Matching the raw file would report the fix as the bug: the comment
	 * explaining why wp_get_sites() was removed contains that very string.
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
	 * The function removed in WP 5.1 is not called.
	 *
	 * @return void
	 */
	public function test_removed_function_is_not_called() {
		$this->assertStringNotContainsString(
			'wp_get_sites',
			$this->uninstall_source(),
			'wp_get_sites() was removed in WP 5.1 and fatals on multisite uninstall.'
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
			$this->uninstall_source()
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
			$this->markTestSkipped( 'The network branch is covered by Test_PostViews_Multisite.' );
		}

		$post_id = $this->make_post( array(), 500 );
		update_post_meta( $post_id, 'keep_me', 'do not delete' );
		update_option( 'widget_views', array( 'test' => 1 ) );
		update_option( 'views_version', WP_POSTVIEWS_VERSION );

		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-postviews/wp-postviews.php' );
		}
		require dirname( __DIR__ ) . '/uninstall.php';

		$this->assertFalse( get_option( PostViews_Options::OPTION ) );
		$this->assertFalse( get_option( PostViews_Options::VERSION_OPTION ) );
		$this->assertFalse( get_option( 'widget_views' ) );
		$this->assertSame( '', (string) get_post_meta( $post_id, 'views', true ) );

		$this->assertSame( 'do not delete', get_post_meta( $post_id, 'keep_me', true ) );
		$this->assertNotNull( get_post( $post_id ) );
	}
}
