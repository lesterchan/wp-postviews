<?php
/**
 * The REST API routes.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Exposes counting a view over the REST API.
 *
 * The namespace is the bare noun `postviews/v1` rather than the plugin slug. A
 * `wp-` prefix is a wordpress.org directory convention for keeping one plugin's
 * download page apart from another's; it says nothing about what the plugin
 * should call the things it registers. Another plugin can claim the same bare
 * noun and WordPress will not detect it; that is the accepted trade.
 *
 * ## There is deliberately no route for *reading* a count
 *
 * This plugin already publishes the count over REST, and in a better place:
 * `WP_PostViews_Core::register_rest_field()` adds a read-only `views` field to
 * the core post resource, with a schema, in the `view`, `edit` and `embed`
 * contexts. A client asking `/wp/v2/posts/<id>` gets the count **with the post**
 * rather than making a second request for it, and a client listing posts gets
 * every count in the same response.
 *
 * A `GET postviews/v1/post/<id>` would therefore be a slower second way to
 * learn something already answered, and two ways to read one number is how they
 * drift. **What the core field cannot do is write** -- it is `readonly` and
 * ought to be, since a view is an event rather than an editable property. That
 * write is the only thing this namespace exists for.
 *
 * ## The counting setting is honoured, and that is not optional
 *
 * `WP_PostViews_Counter::using_ajax()` is false unless the site has a page
 * cache *and* has turned the AJAX counting path on. When it is false the view
 * has already been counted during `wp_head`, so a route that counted anyway
 * would record every view twice on every ordinary site. The AJAX endpoint
 * returns early on the same check for the same reason.
 */
class WP_PostViews_API {

	/**
	 * The REST namespace every route is registered under.
	 *
	 * @var string
	 */
	const REST_NAMESPACE = 'postviews/v1';

	/**
	 * Register the routes once the REST API is up.
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register the plugin's routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'post/(?P<id>\d+)/view',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'view' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'    => array(
						'required' => true,
					),
					'nonce' => array(
						'required'    => true,
						'description' => __( 'The wp_postviews_nonce the counting script is given.', 'wp-postviews' ),
					),
				),
			)
		);
	}

	/**
	 * Count a view of a post.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function view( $request ) {
		$post_id = (int) $request['id'];

		// The same nonce the AJAX endpoint checks, and the same one the counting
		// script is handed. It is a site-wide nonce rather than a per-post one,
		// which is what this plugin has always used; narrowing it here would
		// break the script that already holds the wide one.
		if ( ! wp_verify_nonce( (string) $request['nonce'], 'wp_postviews_nonce' ) ) {
			return new WP_Error(
				'wp_postviews_bad_nonce',
				__( 'Failed To Verify Referrer', 'wp-postviews' ),
				array( 'status' => 403 )
			);
		}

		// Counting here while wp_head is also counting would double every view
		// on the site. See the class docblock.
		if ( ! WP_PostViews_Counter::using_ajax() ) {
			return new WP_Error(
				'wp_postviews_counting_not_deferred',
				__( 'This site counts views while the page renders, so a view cannot be counted separately.', 'wp-postviews' ),
				array( 'status' => 403 )
			);
		}

		$views = WP_PostViews_Counter::record( $post_id );

		if ( null === $views ) {
			return new WP_Error(
				'wp_postviews_no_such_post',
				__( 'Invalid Post ID.', 'wp-postviews' ),
				array( 'status' => 404 )
			);
		}

		return rest_ensure_response(
			array(
				'post_id' => $post_id,
				'views'   => $views,
			)
		);
	}
}
