<?php
/**
 * Recording a view.
 *
 * Two paths, and which one runs depends on WP_CACHE. Without a page cache the
 * count is incremented directly during wp_head. With one, that would only ever
 * record the request that generated the cached page, so a small script posts
 * to admin-ajax.php instead.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Decides whether a request counts, and records it.
 */
class WP_PostViews_Counter {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wp_head', array( __CLASS__, 'process' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		// The action name carries the plugin prefix. A bare "postviews" is the
		// kind of generic noun any plugin could have claimed, and neither
		// WordPress nor admin-ajax.php detects the collision.
		add_action( 'wp_ajax_wp_postviews', array( __CLASS__, 'ajax_increment' ) );
		add_action( 'wp_ajax_nopriv_wp_postviews', array( __CLASS__, 'ajax_increment' ) );
	}

	/**
	 * User agent fragments treated as robots.
	 *
	 * Matched case-insensitively as substrings. Some entries are far broader
	 * than their name suggests - 'spider' on its own will match an ordinary
	 * browser whose user agent happens to contain it - but the list has been
	 * this way for years and narrowing it would silently change the counts on
	 * every site that has bot exclusion turned on.
	 *
	 * @return array
	 */
	public static function bots() {
		return array(
			'Google Bot'    => 'google',
			'MSN'           => 'msnbot',
			'Alex'          => 'ia_archiver',
			'Lycos'         => 'lycos',
			'Ask Jeeves'    => 'jeeves',
			'Altavista'     => 'scooter',
			'AllTheWeb'     => 'fast-webcrawler',
			'Inktomi'       => 'slurp@inktomi',
			'Turnitin.com'  => 'turnitinbot',
			'Technorati'    => 'technorati',
			'Yahoo'         => 'yahoo',
			'Findexa'       => 'findexa',
			'NextLinks'     => 'findlinks',
			'Gais'          => 'gaisbo',
			'WiseNut'       => 'zyborg',
			'WhoisSource'   => 'surveybot',
			'Bloglines'     => 'bloglines',
			'BlogSearch'    => 'blogsearch',
			'PubSub'        => 'pubsub',
			'Syndic8'       => 'syndic8',
			'RadioUserland' => 'userland',
			'Gigabot'       => 'gigabot',
			'Become.com'    => 'become.com',
			'Baidu'         => 'baiduspider',
			'so.com'        => '360spider',
			'Sogou'         => 'spider',
			'soso.com'      => 'sosospider',
			'Yandex'        => 'yandex',
			'Ahrefs'        => 'AhrefsBot',
			'Bing'          => 'bingbot',
			'Apple'         => 'applebot',
			'GitCrawler'    => 'GitCrawlerBot',
			'Bytedance'     => 'Bytespider',
			'webmeup'       => 'BLEXBot',
		);
	}

	/**
	 * Whether this request should be counted against a post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function should_count( $post_id ) {
		$should_count = false;
		$user_id      = get_current_user_id();

		switch ( WP_PostViews_Options::get_int( 'count' ) ) {
			case 0: // Everyone.
				$should_count = true;
				break;
			case 1: // Guests only. The cookie catches a cached page served to
				// someone whose session is still live.
				if ( empty( $_COOKIE[ USER_COOKIE ] ) && 0 === $user_id ) {
					$should_count = true;
				}
				break;
			case 2: // Registered users only.
				if ( $user_id > 0 ) {
					$should_count = true;
				}
				break;
		}

		if ( $should_count && 1 === WP_PostViews_Options::get_int( 'exclude_bots' ) && self::is_bot() ) {
			$should_count = false;
		}

		/**
		 * Filters whether the current request increments the view count.
		 *
		 * @param bool $should_count Whether to count this request.
		 * @param int  $post_id      The post being viewed.
		 */
		return apply_filters( 'postviews_should_count', $should_count, $post_id );
	}

	/**
	 * Whether the requesting user agent is on the robot list.
	 *
	 * @return bool
	 */
	protected static function is_bot() {
		$useragent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '';

		if ( '' === $useragent ) {
			return false;
		}

		foreach ( self::bots() as $lookfor ) {
			if ( false !== stripos( $useragent, $lookfor ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the AJAX path is in use, rather than counting during wp_head.
	 *
	 * @return bool
	 */
	public static function using_ajax() {
		if ( ! defined( 'WP_CACHE' ) || ! WP_CACHE ) {
			return false;
		}

		return 0 !== WP_PostViews_Options::get_int( 'use_ajax' );
	}

	/**
	 * The post this request is a view of, or null.
	 *
	 * @return WP_Post|null
	 */
	protected static function current_post() {
		// Read into a local rather than through `global $post`. Historically
		// this global could arrive as a bare ID and the fix was to normalise it
		// in place, but the loop global belongs to the request, not to this
		// plugin: writing a WP_Post back over an ID another component chose to
		// store is exactly the kind of action at a distance nobody can trace.
		$post = $GLOBALS['post'] ?? null;

		if ( is_int( $post ) ) {
			$post = get_post( $post );
		}

		if ( ! $post instanceof WP_Post ) {
			return null;
		}

		if ( wp_is_post_revision( $post ) ) {
			return null;
		}

		if ( ! is_single() && ! is_page() ) {
			return null;
		}

		return $post;
	}

	/**
	 * Count the view during wp_head, when there is no page cache in the way.
	 *
	 * @return void
	 */
	public static function process() {
		if ( is_preview() ) {
			return;
		}

		$post = self::current_post();
		if ( null === $post ) {
			return;
		}

		if ( self::using_ajax() ) {
			return;
		}

		if ( ! self::should_count( (int) $post->ID ) ) {
			return;
		}

		self::increment( (int) $post->ID, 'postviews_increment_views' );
	}

	/**
	 * Enqueue the counting script when the AJAX path is in use.
	 *
	 * @return void
	 */
	public static function enqueue() {
		if ( ! self::using_ajax() ) {
			return;
		}

		$post = self::current_post();
		if ( null === $post ) {
			return;
		}

		if ( ! self::should_count( (int) $post->ID ) ) {
			return;
		}

		wp_enqueue_script(
			'wp-postviews-cache',
			WP_POSTVIEWS_URL . 'js/wp-postviews-cache.js',
			array(),
			WP_POSTVIEWS_VERSION,
			true
		);
		wp_localize_script(
			'wp-postviews-cache',
			'wpPostViewsL10n',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wp_postviews_nonce' ),
				'postId'  => (int) $post->ID,
			)
		);
	}

	/**
	 * The admin-ajax.php endpoint the cache script posts to.
	 *
	 * The nonce is checked, but it is worth being honest about what that buys:
	 * for a logged out visitor the nonce is derived from a shared anonymous
	 * session, so anyone can mint one. It stops a naive cross-site POST and
	 * nothing more. The real guard is that the only thing this can do is add
	 * one to a counter on a post that already exists.
	 *
	 * @return void
	 */
	public static function ajax_increment() {
		check_ajax_referer( 'wp_postviews_nonce', 'nonce' );

		if ( ! self::using_ajax() ) {
			return;
		}

		if ( empty( $_POST['postviews_id'] ) ) {
			return;
		}

		$post_id = (int) sanitize_key( wp_unslash( $_POST['postviews_id'] ) );

		// update_post_meta() creates a row whether or not the post exists, so
		// without this check a logged out visitor can walk the ID space and
		// grow wp_postmeta without bound.
		if ( $post_id <= 0 || ! get_post_status( $post_id ) ) {
			return;
		}

		$post_views = self::increment( $post_id, 'postviews_increment_views_ajax' );

		wp_send_json_success( array( 'views' => $post_views ) );
	}

	/**
	 * Add one to a post's view count.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $hook    Action fired with the new count.
	 * @return int The new count.
	 */
	protected static function increment( $post_id, $hook ) {
		$post_views = (int) get_post_meta( $post_id, 'views', true ) + 1;

		update_post_meta( $post_id, 'views', $post_views );

		/**
		 * Fires after a view has been recorded.
		 *
		 * @param int $post_views The new view count.
		 */
		do_action( $hook, $post_views );

		return $post_views;
	}
}
