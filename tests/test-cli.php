<?php
/**
 * Tests for the `wp postviews` WP-CLI command.
 *
 * @package WP-PostViews
 */

/**
 * The command reads and never writes, so what is worth pinning is that it reads
 * the right posts in the right order, reports a site nobody has read as an
 * answer rather than a failure, and has not quietly grown a way to set a count
 * -- there is no screen anywhere in the plugin that edits one, and a view count
 * that can be typed is no longer a record of anything.
 */
class WP_PostViews_CLI_Test extends WP_PostViews_TestCase {

	/**
	 * Clears everything the stand-in recorded for the previous test.
	 */
	public function set_up() {
		parent::set_up();

		WP_CLI::$successes     = array();
		WP_CLI::$warnings      = array();
		WP_CLI::$logs          = array();
		WP_CLI::$confirmations = array();
		WP_CLI::$commands      = array();
		WP_CLI::$items         = array();
	}

	/**
	 * Runs one subcommand the way WP-CLI would.
	 *
	 * @param string $subcommand Method to call.
	 * @param array  $args       Positional arguments.
	 * @param array  $assoc_args Associative arguments.
	 * @return void
	 */
	protected function run_command( $subcommand, $args = array(), $assoc_args = array() ) {
		$command = new WP_PostViews_Command();
		$command->$subcommand( $args, $assoc_args );
	}

	/**
	 * The rows the last format_items() call was given.
	 *
	 * @return array
	 */
	protected function listed_rows() {
		$this->assertNotEmpty( WP_CLI::$items, 'The command formatted a table.' );

		$last = end( WP_CLI::$items );

		return $last['items'];
	}

	// --- registration ----------------------------------------------------

	/**
	 * The command registers under the bare noun, not the plugin slug.
	 *
	 * @return void
	 */
	public function test_the_command_registers_as_postviews() {
		if ( ! defined( 'WP_CLI' ) ) {
			define( 'WP_CLI', true );
		}

		WP_PostViews::register_command();

		$this->assertArrayHasKey( 'postviews', WP_CLI::$commands, 'The command is registered as `wp postviews`.' );
		$this->assertSame( 'WP_PostViews_Command', WP_CLI::$commands['postviews'], 'WP_PostViews_Command is what handles it.' );
		$this->assertArrayNotHasKey( 'wp-postviews', WP_CLI::$commands, 'The plugin slug is not also claimed as a command.' );
	}

	/**
	 * The command offers no way to write a count.
	 *
	 * @return void
	 */
	public function test_the_command_cannot_set_a_count() {
		$methods = get_class_methods( 'WP_PostViews_Command' );

		$this->assertNotEmpty( $methods, 'The command declares subcommands at all, so the check below means something.' );

		foreach ( array( 'set', 'update', 'reset', 'delete', 'increment' ) as $forbidden ) {
			$this->assertNotContains(
				$forbidden,
				$methods,
				'No screen in this plugin edits a view count, so neither does the command: ' . $forbidden . '.'
			);
		}
	}

	// --- list ------------------------------------------------------------

	/**
	 * Listing returns the viewed posts, most read first.
	 *
	 * @return void
	 */
	public function test_list_returns_posts_most_viewed_first() {
		$quiet = $this->make_post( array(), 3 );
		$busy  = $this->make_post( array(), 40 );

		$this->run_command( 'list_' );

		$ids = wp_list_pluck( $this->listed_rows(), 'id' );

		$this->assertSame( $busy, $ids[0], 'The most read post is listed first.' );
		$this->assertContains( $quiet, $ids, 'And the quieter one is listed too.' );
	}

	/**
	 * Ordering is numeric, not alphabetical.
	 *
	 * A meta value is a string, so an ordinary sort puts 9 after 100. This is
	 * the assertion that would catch the meta_query losing its NUMERIC type.
	 *
	 * @return void
	 */
	public function test_list_orders_numerically() {
		$nine    = $this->make_post( array(), 9 );
		$hundred = $this->make_post( array(), 100 );

		$this->run_command( 'list_' );

		$ids = wp_list_pluck( $this->listed_rows(), 'id' );

		$this->assertSame( $hundred, $ids[0], '100 views outranks 9, rather than sorting as text.' );
		$this->assertSame( $nine, $ids[1], 'And 9 follows it.' );
	}

	/**
	 * A post nobody has read is left out.
	 *
	 * @return void
	 */
	public function test_list_leaves_out_unviewed_posts() {
		$viewed   = $this->make_post( array(), 5 );
		$unviewed = $this->make_post( array(), 0 );

		$this->run_command( 'list_' );

		$ids = wp_list_pluck( $this->listed_rows(), 'id' );

		$this->assertContains( $viewed, $ids, 'The viewed post is listed.' );
		$this->assertNotContains( $unviewed, $ids, 'A post seeded at zero views is not listed as having been read.' );
	}

	/**
	 * The limit is honoured.
	 *
	 * @return void
	 */
	public function test_list_honours_the_limit() {
		$this->make_post( array(), 30 );
		$this->make_post( array(), 20 );
		$this->make_post( array(), 10 );

		$this->run_command( 'list_', array(), array( 'limit' => 2 ) );

		$this->assertCount( 2, $this->listed_rows(), 'Only as many posts as were asked for.' );
	}

	/**
	 * A site nobody has read says so rather than printing an empty table.
	 *
	 * @return void
	 */
	public function test_list_with_nothing_viewed_is_not_an_error() {
		$this->run_command( 'list_' );

		$this->assertNotEmpty( WP_CLI::$successes, 'Finding nothing is reported on the success channel.' );
		$this->assertEmpty( WP_CLI::$items, 'No table is printed when there is nothing to put in it.' );
	}

	// --- get -------------------------------------------------------------

	/**
	 * Getting a post prints its count.
	 *
	 * @return void
	 */
	public function test_get_prints_the_count() {
		$post_id = $this->make_post( array(), 17 );

		$this->run_command( 'get', array( $post_id ) );

		$this->assertContains( '17', WP_CLI::$logs, 'The count is printed.' );
	}

	/**
	 * Reading a count records no view.
	 *
	 * @return void
	 */
	public function test_get_does_not_count_as_a_view() {
		$post_id = $this->make_post( array(), 17 );

		$this->run_command( 'get', array( $post_id ) );

		$this->assertSame( 17, (int) get_post_meta( $post_id, 'views', true ), 'Asking how many views a post has does not make it one more.' );
	}

	/**
	 * An id that matches no post stops the command.
	 *
	 * @return void
	 */
	public function test_get_errors_on_an_unknown_post() {
		$this->expectException( RuntimeException::class );

		$this->run_command( 'get', array( 123456 ) );
	}
}
