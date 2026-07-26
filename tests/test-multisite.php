<?php
/**
 * Multisite behaviour: network activation and uninstall across a network.
 *
 * These only run under WP_MULTISITE=1 (see bin/test.sh --multisite). Up to
 * 2.0.0 this whole surface was unverified, and it held three separate bugs:
 * a call to wp_get_sites(), removed in WP 5.1; get_sites() left at its
 * default cap of 100 sites; and restore_current_blog() outside the loop.
 * All three fail silently - uninstall still reports success while leaving
 * data behind - which is exactly why they survived so long.
 *
 * @package WP-PostViews
 */

/**
 * Network activation and uninstall.
 */
class Test_PostViews_Multisite extends PostViews_TestCase {

	/**
	 * Skip the whole class on a single site install.
	 *
	 * @return void
	 */
	public function set_up() {
		if ( ! is_multisite() ) {
			$this->markTestSkipped( 'Requires a multisite install. Run bin/test.sh --multisite.' );
		}

		parent::set_up();
	}

	/**
	 * Create sites and seed each with plugin data.
	 *
	 * @param int $count How many extra sites to create.
	 * @return array Site IDs, including the main site.
	 */
	protected function seed_network( $count = 3 ) {
		$site_ids = array( get_current_blog_id() );

		for ( $i = 0; $i < $count; $i++ ) {
			$site_ids[] = self::factory()->blog->create();
		}

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );

			update_option( PostViews_Options::OPTION, PostViews_Options::defaults() );
			update_option( PostViews_Options::VERSION_OPTION, WP_POSTVIEWS_VERSION );
			update_option( 'widget_views', array( 'seeded' => 1 ) );
			PostViews_Options::flush();

			$post_id = self::factory()->post->create( array( 'post_status' => 'publish' ) );
			update_post_meta( $post_id, 'views', 500 );
			update_post_meta( $post_id, 'keep_me', 'belongs to someone else' );

			restore_current_blog();
		}

		return $site_ids;
	}

	/**
	 * Network activation seeds the option on every site.
	 *
	 * @return void
	 */
	public function test_network_activation_seeds_every_site() {
		$site_ids = array( get_current_blog_id() );
		for ( $i = 0; $i < 3; $i++ ) {
			$site_ids[] = self::factory()->blog->create();
		}

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			delete_option( PostViews_Options::OPTION );
			PostViews_Options::flush();
			restore_current_blog();
		}

		postviews_activate( true );

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			PostViews_Options::flush();

			$this->assertIsArray(
				get_option( PostViews_Options::OPTION ),
				"Site {$site_id} was not seeded by network activation."
			);

			restore_current_blog();
		}
	}

	/**
	 * Activating on a single site does not touch the rest of the network.
	 *
	 * @return void
	 */
	public function test_single_site_activation_leaves_other_sites_alone() {
		$other = self::factory()->blog->create();

		switch_to_blog( $other );
		delete_option( PostViews_Options::OPTION );
		restore_current_blog();

		postviews_activate( false );

		switch_to_blog( $other );
		$stored = get_option( PostViews_Options::OPTION );
		restore_current_blog();

		$this->assertFalse( $stored, 'A per-site activation should not seed other sites.' );
	}

	/**
	 * The site query is uncapped and asks only for IDs.
	 *
	 * Asserted at runtime by intercepting the query rather than by grepping
	 * the source. get_sites() defaults to 100 sites, so a network larger than
	 * that silently keeps its data on every site past the hundredth - and
	 * building a 101 site fixture to prove it would be far slower than
	 * reading the arguments the query was actually given.
	 *
	 * @return void
	 */
	public function test_uninstall_queries_sites_without_a_cap() {
		$this->seed_network( 2 );

		$captured = array();
		add_action(
			'pre_get_sites',
			function ( $query ) use ( &$captured ) {
				$captured[] = $query->query_vars;
			}
		);

		$this->run_uninstall();

		$this->assertNotEmpty( $captured, 'uninstall.php never queried the site list.' );

		$vars = $captured[0];
		$this->assertSame( 0, (int) $vars['number'], 'get_sites() was left at its default cap of 100 sites.' );
		$this->assertSame( 'ids', $vars['fields'], 'Only the site IDs are needed.' );
	}

	/**
	 * Uninstalling clears the plugin's data from every site in the network.
	 *
	 * @return void
	 */
	public function test_uninstall_clears_every_site() {
		$site_ids = $this->seed_network( 3 );

		$this->run_uninstall();

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );
			PostViews_Options::flush();

			$this->assertFalse( get_option( PostViews_Options::OPTION ), "views_options survived on site {$site_id}." );
			$this->assertFalse( get_option( PostViews_Options::VERSION_OPTION ), "views_version survived on site {$site_id}." );
			$this->assertFalse( get_option( 'widget_views' ), "widget_views survived on site {$site_id}." );

			$this->assertSame(
				0,
				$this->count_views_meta(),
				"views post meta survived on site {$site_id}."
			);

			restore_current_blog();
		}
	}

	/**
	 * Uninstalling leaves other plugins' data alone on every site.
	 *
	 * @return void
	 */
	public function test_uninstall_spares_other_data_on_every_site() {
		$site_ids = $this->seed_network( 2 );

		$this->run_uninstall();

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( $site_id );

			global $wpdb;
			$kept = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'keep_me'" );
			$this->assertSame( 1, $kept, "Another plugin's meta was deleted on site {$site_id}." );

			$this->assertGreaterThan( 0, (int) wp_count_posts( 'post' )->publish, "Posts were deleted on site {$site_id}." );

			restore_current_blog();
		}
	}

	/**
	 * The blog stack is left unwound and the original site is current.
	 *
	 * Calling switch_to_blog() pushes onto a stack. Restoring once after the
	 * loop rather than once per iteration leaves the stack short by one, so
	 * whatever runs next operates against the last site visited instead of
	 * the one it thinks it is on.
	 *
	 * @return void
	 */
	public function test_uninstall_unwinds_the_blog_stack() {
		$original = get_current_blog_id();
		$this->seed_network( 3 );

		$this->run_uninstall();

		$this->assertFalse( ms_is_switched(), 'The blog stack was left switched.' );
		$this->assertSame( $original, get_current_blog_id(), 'The original site is no longer current.' );
	}

	/**
	 * Every site is visited, not just the first or the current one.
	 *
	 * @return void
	 */
	public function test_uninstall_visits_every_site() {
		$site_ids = $this->seed_network( 3 );

		$visited = array();
		add_action(
			'switch_blog',
			function ( $new_blog_id ) use ( &$visited ) {
				$visited[] = (int) $new_blog_id;
			}
		);

		$this->run_uninstall();

		foreach ( $site_ids as $site_id ) {
			$this->assertContains( (int) $site_id, $visited, "Site {$site_id} was never switched to." );
		}
	}

	/**
	 * Count the views meta rows on the current site.
	 *
	 * @return int
	 */
	protected function count_views_meta() {
		global $wpdb;

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'views'" );
	}

	/**
	 * Execute uninstall.php, the real file, every time.
	 *
	 * The script guards its function declaration with function_exists() and
	 * declares it before dispatching, so requiring it repeatedly re-runs the
	 * dispatch instead of fataling on a redeclare. That matters here: a helper
	 * that reimplemented the loop would be testing the copy, and the copy
	 * cannot carry the bug.
	 *
	 * @return void
	 */
	protected function run_uninstall() {
		if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
			define( 'WP_UNINSTALL_PLUGIN', 'wp-postviews/wp-postviews.php' );
		}

		require dirname( __DIR__ ) . '/uninstall.php';
	}
}
