<?php
/**
 * Counting: the three "count views from" modes, bot exclusion, the
 * postviews_should_count filter and the AJAX endpoint.
 *
 * @package WP-PostViews
 */

/**
 * Recording a view.
 */
class Test_PostViews_Counter extends PostViews_TestCase {

	/**
	 * The post under test.
	 *
	 * @var int
	 */
	protected $post_id;

	/**
	 * An editor to sign in as.
	 *
	 * @var int
	 */
	protected $editor;

	/**
	 * Seed a post and a user.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->post_id = $this->make_post( array(), 500 );
		$this->editor  = self::factory()->user->create( array( 'role' => 'editor' ) );

		// WP_CACHE is on in the test environment so the AJAX endpoint can be
		// exercised at all; use_ajax=0 selects the wp_head path instead, which
		// is what the counting tests below drive.
		$this->set_options( array( 'use_ajax' => 0 ) );
	}

	/**
	 * Sign in, or out, the way a real request would look.
	 *
	 * @param bool $logged_in Whether to sign in.
	 * @return void
	 */
	protected function sign_in( $logged_in ) {
		wp_set_current_user( $logged_in ? $this->editor : 0 );
		$_COOKIE = array();

		if ( $logged_in ) {
			// The guests-only mode treats the logged-in cookie as proof of a
			// registered visitor, independently of the current user ID, so that
			// it still works on a cached page.
			$_COOKIE[ USER_COOKIE ] = 'editor|123|abc';
		}
	}

	/**
	 * The count mode matrix.
	 *
	 * @dataProvider data_count_modes
	 *
	 * @param int  $mode      0 everyone, 1 guests only, 2 registered only.
	 * @param bool $logged_in Whether a user is signed in.
	 * @param int  $expected  Expected increment.
	 * @return void
	 */
	public function test_count_modes( $mode, $logged_in, $expected ) {
		$this->sign_in( $logged_in );
		$this->set_options( array( 'count' => $mode ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		$this->assertSame( $expected, $this->hit( $this->post_id ) );
	}

	/**
	 * Mode and login state, with the expected increment.
	 *
	 * @return array
	 */
	public function data_count_modes() {
		return array(
			'everyone, anonymous'        => array( 0, false, 1 ),
			'everyone, logged in'        => array( 0, true, 1 ),
			'guests only, anonymous'     => array( 1, false, 1 ),
			'guests only, logged in'     => array( 1, true, 0 ),
			'registered only, anonymous' => array( 2, false, 0 ),
			'registered only, logged in' => array( 2, true, 1 ),
		);
	}

	/**
	 * Pages are counted as well as posts.
	 *
	 * @return void
	 */
	public function test_pages_are_counted() {
		$page_id = $this->make_post( array( 'post_type' => 'page' ), 42 );
		$this->set_options( array( 'count' => 0 ) );
		$this->set_context( array( 'is_page', 'is_singular' ), $page_id );

		$this->assertSame( 1, $this->hit( $page_id ) );
	}

	/**
	 * Only single views count.
	 *
	 * @dataProvider data_non_single_contexts
	 *
	 * @param string $flag Query flag describing the context.
	 * @return void
	 */
	public function test_non_single_contexts_do_not_count( $flag ) {
		$this->set_options( array( 'count' => 0 ) );
		$this->set_context( array( $flag ), $this->post_id );

		$this->assertSame( 0, $this->hit( $this->post_id ) );
	}

	/**
	 * Contexts that are not a single post view.
	 *
	 * @return array
	 */
	public function data_non_single_contexts() {
		return array(
			'home'    => array( 'is_home' ),
			'archive' => array( 'is_archive' ),
			'search'  => array( 'is_search' ),
		);
	}

	/**
	 * A preview must not count. Regression from 1.73.
	 *
	 * @return void
	 */
	public function test_previews_do_not_count() {
		$this->set_options( array( 'count' => 0 ) );
		$this->set_context( array( 'is_single', 'is_singular', 'is_preview' ), $this->post_id );

		$this->assertSame( 0, $this->hit( $this->post_id ) );
	}

	/**
	 * The first view of a post with no meta row records 1.
	 *
	 * @return void
	 */
	public function test_first_view_of_an_unviewed_post() {
		$post_id = $this->make_post( array(), null );
		$this->set_options( array( 'count' => 0 ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$this->assertSame( 1, $this->hit( $post_id ) );
		$this->assertSame( '1', get_post_meta( $post_id, 'views', true ) );
	}

	/**
	 * Known robots are excluded.
	 *
	 * @dataProvider data_bot_user_agents
	 *
	 * @param string $user_agent User agent to send.
	 * @return void
	 */
	public function test_bots_are_excluded( $user_agent ) {
		$this->set_options(
			array(
				'count'        => 0,
				'exclude_bots' => 1,
			)
		);
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		$_SERVER['HTTP_USER_AGENT'] = $user_agent;

		$this->assertSame( 0, $this->hit( $this->post_id ) );
	}

	/**
	 * User agents the list must catch.
	 *
	 * The last entry is not a robot. The list matches a bare "spider"
	 * substring, which is broad enough to catch an ordinary browser; it is
	 * pinned here rather than fixed, because narrowing it would silently
	 * change the counts on every site with bot exclusion turned on.
	 *
	 * @return array
	 */
	public function data_bot_user_agents() {
		return array(
			'bingbot'       => array( 'Mozilla/5.0 (compatible; bingbot/2.0; +http://www.bing.com/bingbot.htm)' ),
			'googlebot'     => array( 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)' ),
			'ahrefs'        => array( 'Mozilla/5.0 (compatible; AhrefsBot/7.0; +http://ahrefs.com/robot/)' ),
			'bytespider'    => array( 'Mozilla/5.0 (compatible; Bytespider; spider-feedback@bytedance.com)' ),
			'baiduspider'   => array( 'Mozilla/5.0 (compatible; Baiduspider/2.0)' ),
			'yandex'        => array( 'Mozilla/5.0 (compatible; YandexBot/3.0)' ),
			'blexbot'       => array( 'Mozilla/5.0 (compatible; BLEXBot/1.0)' ),
			'applebot'      => array( 'Applebot/0.1' ),
			'bare "spider"' => array( 'MySpiderBrowser/3.0' ),
		);
	}

	/**
	 * An ordinary browser still counts with exclusion on.
	 *
	 * @return void
	 */
	public function test_ordinary_browser_counts_with_exclusion_on() {
		$this->set_options(
			array(
				'count'        => 0,
				'exclude_bots' => 1,
			)
		);
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		$this->assertSame( 1, $this->hit( $this->post_id ) );
	}

	/**
	 * A request with no User-Agent header must not warn or be treated as a bot.
	 *
	 * @return void
	 */
	public function test_missing_user_agent_counts() {
		$this->set_options(
			array(
				'count'        => 0,
				'exclude_bots' => 1,
			)
		);
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		unset( $_SERVER['HTTP_USER_AGENT'] );

		$this->assertSame( 1, $this->hit( $this->post_id ) );
	}

	/**
	 * The filter can veto a view, and receives the post ID.
	 *
	 * @return void
	 */
	public function test_should_count_filter_can_veto() {
		$this->set_options( array( 'count' => 0 ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		$seen = 0;
		add_filter(
			'postviews_should_count',
			function ( $should, $post_id ) use ( &$seen ) {
				$seen = $post_id;

				return false;
			},
			10,
			2
		);

		$this->assertSame( 0, $this->hit( $this->post_id ) );
		$this->assertSame( $this->post_id, $seen );
	}

	/**
	 * The filter can force a view the settings would have skipped.
	 *
	 * @return void
	 */
	public function test_should_count_filter_can_force() {
		$this->sign_in( false );
		$this->set_options( array( 'count' => 2 ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		add_filter( 'postviews_should_count', '__return_true' );

		$this->assertSame( 1, $this->hit( $this->post_id ) );
	}

	/**
	 * The increment action fires with the new count.
	 *
	 * @return void
	 */
	public function test_increment_action_fires() {
		$this->set_options( array( 'count' => 0 ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		$fired = null;
		add_action(
			'postviews_increment_views',
			function ( $views ) use ( &$fired ) {
				$fired = $views;
			}
		);

		$this->hit( $this->post_id );

		$this->assertSame( 501, $fired );
	}

	/**
	 * The AJAX endpoint records a view and reports the new count.
	 *
	 * @return void
	 */
	public function test_ajax_endpoint_increments() {
		$response = $this->call_ajax( (string) $this->post_id );

		$this->assertSame( 1, $response['delta'] );
		$this->assertTrue( $response['json']['success'] );
		$this->assertSame( 501, $response['json']['data']['views'] );
	}

	/**
	 * The AJAX endpoint refuses input it should refuse.
	 *
	 * @dataProvider data_rejected_ajax_ids
	 *
	 * @param string $postviews_id Value posted as the post ID.
	 * @return void
	 */
	public function test_ajax_endpoint_rejects( $postviews_id ) {
		$this->assertSame( 0, $this->call_ajax( $postviews_id )['delta'] );
	}

	/**
	 * Post IDs the endpoint must not act on.
	 *
	 * @return array
	 */
	public function data_rejected_ajax_ids() {
		return array(
			'missing'     => array( null ),
			'zero'        => array( '0' ),
			'non numeric' => array( 'abc' ),
			'negative'    => array( '-5' ),
		);
	}

	/**
	 * A post ID that does not exist must not create an orphan meta row.
	 *
	 * Calling update_post_meta() will happily create one, so without an existence
	 * check a logged out visitor could walk the ID space and grow wp_postmeta
	 * without bound.
	 *
	 * @return void
	 */
	public function test_ajax_endpoint_creates_no_orphan_meta() {
		$ghost = 999999;

		$this->call_ajax( (string) $ghost );

		$this->assertSame( '', (string) get_post_meta( $ghost, 'views', true ) );
	}

	/**
	 * With AJAX counting turned off the endpoint is a no-op, so a stale cached
	 * page cannot keep incrementing after the setting changed.
	 *
	 * @return void
	 */
	public function test_ajax_endpoint_is_inert_when_disabled() {
		$this->assertSame( 0, $this->call_ajax( (string) $this->post_id, false )['delta'] );
	}

	/**
	 * A bad nonce records nothing.
	 *
	 * @return void
	 */
	public function test_ajax_endpoint_requires_a_nonce() {
		$response = $this->call_ajax( (string) $this->post_id, true, 'not-a-nonce' );

		$this->assertSame( 0, $response['delta'] );
		$this->assertNotNull( $response['died'] );
	}

	/**
	 * The cache script is enqueued when the AJAX path is in use.
	 *
	 * @return void
	 */
	public function test_enqueue_registers_the_cache_script() {
		$this->set_options(
			array(
				'count'    => 0,
				'use_ajax' => 1,
			)
		);
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		$this->fire( 'wp_enqueue_scripts' );

		$this->assertTrue( wp_script_is( 'wp-postviews-cache', 'enqueued' ) );

		$data = (string) wp_scripts()->get_data( 'wp-postviews-cache', 'data' );
		$this->assertStringContainsString( '"post_id":"' . $this->post_id . '"', $data );
		$this->assertStringContainsString( 'admin-ajax.php', $data );
		$this->assertStringContainsString( '"nonce"', $data );
	}

	/**
	 * The cache script does not pull in jQuery.
	 *
	 * Dropping that dependency is one of the things 2.0.0 was for, and it is
	 * easy to reintroduce by copying an enqueue call from another plugin.
	 *
	 * @return void
	 */
	public function test_cache_script_has_no_jquery_dependency() {
		$this->set_options(
			array(
				'count'    => 0,
				'use_ajax' => 1,
			)
		);
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		$this->fire( 'wp_enqueue_scripts' );

		$this->assertSame( array(), wp_scripts()->registered['wp-postviews-cache']->deps );
	}

	/**
	 * Nothing is enqueued when the admin has turned AJAX counting off.
	 *
	 * @return void
	 */
	public function test_nothing_is_enqueued_when_ajax_is_off() {
		$this->set_options(
			array(
				'count'    => 0,
				'use_ajax' => 0,
			)
		);
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		$this->fire( 'wp_enqueue_scripts' );

		$this->assertFalse( wp_script_is( 'wp-postviews-cache', 'enqueued' ) );
	}

	/**
	 * Nothing is enqueued on a page that would not be counted anyway.
	 *
	 * @return void
	 */
	public function test_nothing_is_enqueued_off_a_single_post() {
		$this->set_options(
			array(
				'count'    => 0,
				'use_ajax' => 1,
			)
		);
		$this->set_context( array( 'is_archive' ), $this->post_id );

		$this->fire( 'wp_enqueue_scripts' );

		$this->assertFalse( wp_script_is( 'wp-postviews-cache', 'enqueued' ) );
	}

	/**
	 * Nothing is enqueued when the count mode excludes this visitor, so a
	 * cached page does not carry a request that the endpoint would reject.
	 *
	 * @return void
	 */
	public function test_nothing_is_enqueued_when_the_visitor_is_not_counted() {
		$this->sign_in( false );
		$this->set_options(
			array(
				'count'    => 2,
				'use_ajax' => 1,
			)
		);
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		$this->fire( 'wp_enqueue_scripts' );

		$this->assertFalse( wp_script_is( 'wp-postviews-cache', 'enqueued' ) );
	}

	/**
	 * The bot list is well formed.
	 *
	 * @return void
	 */
	public function test_bot_list_is_well_formed() {
		$bots = PostViews_Counter::bots();

		$this->assertNotEmpty( $bots );

		foreach ( $bots as $name => $fragment ) {
			$this->assertIsString( $fragment, "Bot {$name} has a non-string fragment." );
			$this->assertNotSame( '', trim( $fragment ), "Bot {$name} has an empty fragment, which would match everything." );
		}

		$fragments = array_map( 'strtolower', array_values( $bots ) );
		$this->assertSame(
			array_values( array_unique( $fragments ) ),
			$fragments,
			'A duplicated fragment is a dead entry.'
		);
	}

	/**
	 * Matching is case insensitive, because user agents are not consistent.
	 *
	 * @dataProvider data_bot_casing
	 *
	 * @param string $user_agent User agent to send.
	 * @return void
	 */
	public function test_bot_matching_is_case_insensitive( $user_agent ) {
		$this->set_options(
			array(
				'count'        => 0,
				'exclude_bots' => 1,
			)
		);
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		$_SERVER['HTTP_USER_AGENT'] = $user_agent;

		$this->assertSame( 0, $this->hit( $this->post_id ) );
	}

	/**
	 * The same bot in three different casings.
	 *
	 * @return array
	 */
	public function data_bot_casing() {
		return array(
			'lower' => array( 'bingbot/2.0' ),
			'upper' => array( 'BINGBOT/2.0' ),
			'mixed' => array( 'BingBot/2.0' ),
		);
	}

	/**
	 * Bot exclusion only applies when it is switched on.
	 *
	 * @return void
	 */
	public function test_bots_are_counted_when_exclusion_is_off() {
		$this->set_options(
			array(
				'count'        => 0,
				'exclude_bots' => 0,
			)
		);
		$this->set_context( array( 'is_single', 'is_singular' ), $this->post_id );

		$_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (compatible; bingbot/2.0)';

		$this->assertSame( 1, $this->hit( $this->post_id ) );
	}

	/**
	 * A bare post ID in the $post global is still counted.
	 *
	 * Some themes assign an ID rather than a WP_Post to that global. The
	 * counter has always coped with it, and the branch is easy to drop when
	 * tidying the code.
	 *
	 * @return void
	 */
	public function test_a_bare_post_id_in_the_global_is_handled() {
		$this->set_options( array( 'count' => 0 ) );
		$this->set_context( array( 'is_single', 'is_singular' ) );

		$GLOBALS['post'] = $this->post_id;

		$this->assertSame( 1, $this->hit( $this->post_id ) );
		$this->assertInstanceOf( WP_Post::class, $GLOBALS['post'], 'The global should have been normalised to a WP_Post.' );
	}

	/**
	 * A junk $post global is ignored rather than fatal.
	 *
	 * @return void
	 */
	public function test_a_junk_post_global_is_ignored() {
		$this->set_options( array( 'count' => 0 ) );
		$this->set_context( array( 'is_single', 'is_singular' ) );

		$GLOBALS['post'] = null;

		$before = (int) get_post_meta( $this->post_id, 'views', true );
		ob_start();
		$this->fire( 'wp_head' );
		ob_end_clean();

		$this->assertSame( $before, (int) get_post_meta( $this->post_id, 'views', true ) );
	}

	/**
	 * A revision is never counted.
	 *
	 * @return void
	 */
	public function test_revisions_are_not_counted() {
		$revision_id = self::factory()->post->create(
			array(
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $this->post_id,
			)
		);

		$this->set_options( array( 'count' => 0 ) );
		$this->set_context( array( 'is_single', 'is_singular' ) );
		$GLOBALS['post'] = get_post( $revision_id );

		$before = (int) get_post_meta( $revision_id, 'views', true );
		ob_start();
		$this->fire( 'wp_head' );
		ob_end_clean();

		$this->assertSame( $before, (int) get_post_meta( $revision_id, 'views', true ) );
	}

	/**
	 * The AJAX path needs both a page cache and the setting.
	 *
	 * @return void
	 */
	public function test_using_ajax_requires_both_conditions() {
		$this->set_options( array( 'use_ajax' => 1 ) );
		$this->assertTrue( PostViews_Counter::using_ajax() );

		$this->set_options( array( 'use_ajax' => 0 ) );
		$this->assertFalse( PostViews_Counter::using_ajax() );
	}

	/**
	 * Drive the nopriv endpoint.
	 *
	 * Both wp_send_json_success() and check_ajax_referer() end in wp_die(), so
	 * the die handler is swapped for one that throws.
	 *
	 * @param string|null $postviews_id Value to post as the post ID.
	 * @param bool        $use_ajax     Whether AJAX counting is enabled.
	 * @param string|null $nonce        Nonce to send. Null mints a valid one.
	 * @return array
	 */
	protected function call_ajax( $postviews_id, $use_ajax = true, $nonce = null ) {
		if ( ! defined( 'DOING_AJAX' ) ) {
			define( 'DOING_AJAX', true );
		}

		// The endpoint only does anything when a page cache is in play, which
		// is why .wp-env.json defines WP_CACHE for the test environment.
		$this->assertTrue(
			defined( 'WP_CACHE' ) && WP_CACHE,
			'WP_CACHE must be on for the AJAX counting path. Check the config block in .wp-env.json.'
		);
		$this->set_options( array( 'use_ajax' => $use_ajax ? 1 : 0 ) );

		add_filter(
			'wp_die_ajax_handler',
			function () {
				return function ( $message ) {
					throw new WPDieException( is_scalar( $message ) ? (string) $message : 'wp_die' );
				};
			}
		);

		$_POST = array(
			'action' => 'postviews',
			'nonce'  => null === $nonce ? wp_create_nonce( 'wp_postviews_nonce' ) : $nonce,
		);
		if ( null !== $postviews_id ) {
			$_POST['postviews_id'] = $postviews_id;
		}
		$_REQUEST = $_POST;

		$before = (int) get_post_meta( $this->post_id, 'views', true );
		$died   = null;

		ob_start();
		try {
			do_action( 'wp_ajax_nopriv_postviews' );
		} catch ( WPDieException $e ) {
			$died = $e->getMessage();
		}
		$body = ob_get_clean();

		return array(
			'body'  => $body,
			'json'  => json_decode( $body, true ),
			'died'  => $died,
			'delta' => (int) get_post_meta( $this->post_id, 'views', true ) - $before,
		);
	}
}
