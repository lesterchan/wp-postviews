<?php
/**
 * Shared fixtures and helpers for the WP-PostViews suite.
 *
 * @package WP-PostViews
 */

/**
 * Base class: resets the option row and the runtime cache between tests, and
 * provides the query-context helpers the display tests need.
 */
abstract class PostViews_TestCase extends WP_UnitTestCase {

	/**
	 * Every display context key, so a test can switch them all off at once.
	 *
	 * @var array
	 */
	protected $display_keys = array(
		'display_home',
		'display_single',
		'display_page',
		'display_archive',
		'display_search',
		'display_other',
	);

	/**
	 * Reset to a known state.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		update_option( PostViews_Options::OPTION, PostViews_Options::defaults() );
		PostViews_Options::flush();

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh) PHPUnit/1.0';
		$_COOKIE                    = array();
	}

	/**
	 * Clear anything that leaks across tests.
	 *
	 * @return void
	 */
	public function tear_down() {
		unset( $_SERVER['HTTP_USER_AGENT'] );
		$_COOKIE = array();
		$_POST   = array();
		PostViews_Options::flush();

		// phpunit.xml.dist turns backupGlobals off, so $wp_scripts survives
		// between tests. Left alone, one test that enqueues makes the next
		// test's "nothing was enqueued" assertion pass or fail for the wrong
		// reason.
		foreach ( array( 'wp-postviews-cache', 'wp-postviews-admin' ) as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_dequeue_script( $handle );
				wp_deregister_script( $handle );
			}
		}

		parent::tear_down();
	}

	/**
	 * Merge values into the option row.
	 *
	 * @param array $overrides Values to set.
	 * @return void
	 */
	protected function set_options( $overrides ) {
		update_option( PostViews_Options::OPTION, array_merge( PostViews_Options::all(), $overrides ) );
	}

	/**
	 * Fake a main query context.
	 *
	 * Conditionals like is_home() and is_single() delegate to the global WP_Query,
	 * so setting the flags there is enough for the display helpers.
	 *
	 * @param array    $flags   Conditional flags to switch on.
	 * @param int|null $post_id Post to make current, if any.
	 * @return void
	 */
	protected function set_context( $flags = array(), $post_id = null ) {
		global $wp_query, $post;

		foreach ( array( 'is_home', 'is_single', 'is_page', 'is_archive', 'is_search', 'is_singular', 'is_preview', 'is_front_page' ) as $flag ) {
			$wp_query->$flag = false;
		}
		foreach ( $flags as $flag ) {
			$wp_query->$flag = true;
		}

		if ( null !== $post_id ) {
			$post                        = get_post( $post_id );
			$wp_query->post              = $post;
			$wp_query->queried_object    = $post;
			$wp_query->queried_object_id = $post_id;
			setup_postdata( $post );
		}
	}

	/**
	 * Create a published post with a known view count.
	 *
	 * @param array $args  Overrides for wp_insert_post().
	 * @param int   $views View count to store. Null leaves the meta absent.
	 * @return int Post ID.
	 */
	protected function make_post( $args = array(), $views = 0 ) {
		$post_id = self::factory()->post->create(
			array_merge(
				array(
					'post_title'   => 'A Post',
					'post_content' => 'Body.',
					'post_excerpt' => 'Excerpt.',
					'post_status'  => 'publish',
					'post_type'    => 'post',
				),
				$args
			)
		);

		if ( null === $views ) {
			delete_post_meta( $post_id, 'views' );
		} else {
			update_post_meta( $post_id, 'views', $views );
		}

		return $post_id;
	}

	/**
	 * Fire only the plugin's own callbacks on a hook.
	 *
	 * Firing do_action( 'wp_head' ) would drag in the whole of core's head output.
	 * Resolving each callback back to the file it was declared in and running
	 * only the ones inside the plugin keeps the test about the plugin.
	 *
	 * @param string $hook Hook name.
	 * @param array  $args Arguments to pass.
	 * @return int Number of callbacks fired.
	 */
	protected function fire( $hook, $args = array() ) {
		global $wp_filter;

		if ( ! isset( $wp_filter[ $hook ] ) ) {
			return 0;
		}

		$fired = 0;
		foreach ( $wp_filter[ $hook ]->callbacks as $callbacks ) {
			foreach ( $callbacks as $callback ) {
				$function = $callback['function'];

				try {
					if ( is_array( $function ) ) {
						$reflection = new ReflectionMethod( $function[0], $function[1] );
					} elseif ( is_string( $function ) && false !== strpos( $function, '::' ) ) {
						$reflection = new ReflectionMethod( $function );
					} elseif ( $function instanceof Closure || ( is_string( $function ) && function_exists( $function ) ) ) {
						$reflection = new ReflectionFunction( $function );
					} else {
						continue;
					}
				} catch ( ReflectionException $e ) {
					continue;
				}

				if ( false === strpos( (string) $reflection->getFileName(), 'wp-postviews' ) ) {
					continue;
				}

				call_user_func_array( $function, $args );
				++$fired;
			}
		}

		return $fired;
	}

	/**
	 * Fire the plugin's wp_head callbacks and report the change in view count.
	 *
	 * @param int $post_id Post to measure.
	 * @return int Delta.
	 */
	protected function hit( $post_id ) {
		$before = (int) get_post_meta( $post_id, 'views', true );

		ob_start();
		$this->fire( 'wp_head' );
		ob_end_clean();

		return (int) get_post_meta( $post_id, 'views', true ) - $before;
	}

	/**
	 * Capture what a callable echoes.
	 *
	 * @param callable $callback Callable to run.
	 * @return string
	 */
	protected function capture( $callback ) {
		ob_start();
		$callback();

		return ob_get_clean();
	}

	/**
	 * The anchor text of every link in a rendered listing.
	 *
	 * @param string $html Rendered listing.
	 * @return array
	 */
	protected function listed_titles( $html ) {
		preg_match_all( '/>([^<]+)<\/a>/', $html, $matches );

		return $matches[1];
	}
}
