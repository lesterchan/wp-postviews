<?php
/**
 * Tests for the `postviews/v1` REST route.
 *
 * @package WP-PostViews
 */

/**
 * One route, and the absence of a second one is as much the design as the route
 * is.
 *
 * Reading a count is already answered by the core post resource, because
 * WP_PostViews_Core::register_rest_field() adds a read-only `views` field to it.
 * A `GET postviews/v1/post/<id>` would be a slower second way to learn the same
 * number, and two ways to read one number is how they drift. So there is a test
 * below asserting the core field still works and a test asserting no read route
 * was added -- the second is what stops somebody "completing" the namespace.
 */
class WP_PostViews_REST_API_Test extends WP_PostViews_TestCase {

	/**
	 * Boots the REST server the way core's own REST tests do.
	 */
	public function set_up() {
		parent::set_up();

		global $wp_rest_server;

		$wp_rest_server = new WP_REST_Server();
		do_action( 'rest_api_init', $wp_rest_server );
	}

	/**
	 * Tears the REST server back down so it cannot leak into another test.
	 */
	public function tear_down() {
		global $wp_rest_server;

		$wp_rest_server = null;

		parent::tear_down();
	}

	/**
	 * Dispatch a request against the routes under test.
	 *
	 * @param string $method HTTP method.
	 * @param string $route  Full route path.
	 * @param array  $params Body or query parameters.
	 * @return WP_REST_Response
	 */
	protected function request( $method, $route, $params = array() ) {
		$request = new WP_REST_Request( $method, $route );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return rest_get_server()->dispatch( $request );
	}

	/**
	 * Turn the deferred counting path on, which is what the route requires.
	 *
	 * @return void
	 */
	protected function defer_counting() {
		if ( ! defined( 'WP_CACHE' ) ) {
			define( 'WP_CACHE', true );
		}

		$this->set_options( array( 'use_ajax' => 1 ) );
	}

	// --- registration ----------------------------------------------------

	/**
	 * The route registers under the bare noun, not the plugin slug.
	 *
	 * @return void
	 */
	public function test_the_namespace_is_the_bare_noun() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/postviews/v1', $routes, 'The namespace is postviews/v1.' );
		$this->assertArrayNotHasKey( '/wp-postviews/v1', $routes, 'The plugin slug is not also claimed as a namespace.' );
		$this->assertSame( 'postviews/v1', WP_PostViews_API::REST_NAMESPACE, 'And the constant agrees with what was registered.' );
	}

	/**
	 * The namespace carries the write and nothing else.
	 *
	 * @return void
	 */
	public function test_the_namespace_carries_no_read_route() {
		$routes = rest_get_server()->get_routes();

		$this->assertArrayHasKey( '/postviews/v1/post/(?P<id>\d+)/view', $routes, 'Counting a view is routed.' );

		$own = array_filter(
			array_keys( $routes ),
			function ( $route ) {
				return 0 === strpos( $route, '/postviews/v1' );
			}
		);

		// The namespace root is registered by core for every namespace, so two
		// entries is the whole surface: the root and the one write.
		$this->assertCount( 2, $own, 'Reading a count belongs to the core post resource, so this namespace adds only the write.' );
	}

	/**
	 * Reading a count is answered by the core post resource.
	 *
	 * @return void
	 */
	public function test_the_core_post_resource_still_carries_the_count() {
		$post_id = $this->make_post( array(), 12 );

		$response = $this->request( 'GET', '/wp/v2/posts/' . $post_id );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status(), 'The post is served.' );
		$this->assertArrayHasKey( 'views', $data, 'And carries the view count as a field.' );
		$this->assertSame( 12, $data['views'], 'Which is the stored count.' );
	}

	// --- counting --------------------------------------------------------

	/**
	 * A view is counted when the site defers counting.
	 *
	 * @return void
	 */
	public function test_a_view_is_counted() {
		$this->defer_counting();

		$post_id = $this->make_post( array(), 4 );

		$response = $this->request(
			'POST',
			'/postviews/v1/post/' . $post_id . '/view',
			array( 'nonce' => wp_create_nonce( 'wp_postviews_nonce' ) )
		);

		$this->assertSame( 200, $response->get_status(), 'The view is counted.' );
		$this->assertSame( 5, $response->get_data()['views'], 'And the new count comes back.' );
		$this->assertSame( 5, (int) get_post_meta( $post_id, 'views', true ), 'Which is what was stored.' );
	}

	/**
	 * Without the nonce nothing is counted.
	 *
	 * @return void
	 */
	public function test_a_view_without_the_nonce_is_refused() {
		$this->defer_counting();

		$post_id = $this->make_post( array(), 4 );

		$response = $this->request(
			'POST',
			'/postviews/v1/post/' . $post_id . '/view',
			array( 'nonce' => 'not-the-nonce' )
		);

		$this->assertSame( 403, $response->get_status(), 'A bad nonce is refused.' );
		$this->assertSame( 4, (int) get_post_meta( $post_id, 'views', true ), 'And the count did not move.' );
	}

	/**
	 * A site that counts during the render refuses to count again here.
	 *
	 * This is the assertion that matters most: without it the route would
	 * double every view on every site without a page cache, because wp_head has
	 * already counted the same visit.
	 *
	 * @return void
	 */
	public function test_a_site_counting_during_the_render_refuses_the_route() {
		$this->set_options( array( 'use_ajax' => 0 ) );

		$post_id = $this->make_post( array(), 4 );

		$response = $this->request(
			'POST',
			'/postviews/v1/post/' . $post_id . '/view',
			array( 'nonce' => wp_create_nonce( 'wp_postviews_nonce' ) )
		);

		$this->assertSame( 403, $response->get_status(), 'Counting twice is refused rather than done.' );
		$this->assertSame( 4, (int) get_post_meta( $post_id, 'views', true ), 'And the count did not move.' );
	}

	/**
	 * An id naming no post is a 404, and writes no meta row.
	 *
	 * Core's update_post_meta() creates a row whether or not the post exists, so
	 * without the guard a logged-out caller could walk the id space and grow
	 * wp_postmeta without bound.
	 *
	 * @return void
	 */
	public function test_an_unknown_post_is_refused_and_writes_nothing() {
		$this->defer_counting();

		$response = $this->request(
			'POST',
			'/postviews/v1/post/123456/view',
			array( 'nonce' => wp_create_nonce( 'wp_postviews_nonce' ) )
		);

		$this->assertSame( 404, $response->get_status(), 'An id matching no post is refused.' );
		$this->assertSame( '', get_post_meta( 123456, 'views', true ), 'And no meta row was created for it.' );
	}

	// --- the AJAX endpoint it sits beside --------------------------------

	/**
	 * The AJAX action stays registered, because sites are still calling it.
	 *
	 * @return void
	 */
	public function test_the_ajax_endpoint_is_still_registered() {
		$this->assertNotFalse( has_action( 'wp_ajax_wp_postviews' ), 'The logged-in AJAX action survives the REST route.' );
		$this->assertNotFalse( has_action( 'wp_ajax_nopriv_wp_postviews' ), 'And so does the logged-out one.' );
	}
}
