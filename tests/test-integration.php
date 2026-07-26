<?php
/**
 * Integration surface: the REST field, the admin column and its sorting, the
 * v_sortby query vars, the publish hook and the widget.
 *
 * @package WP-PostViews
 */

/**
 * Everything that hangs off a WordPress integration point.
 */
class Test_PostViews_Integration extends PostViews_TestCase {

	/**
	 * The REST response carries an integer views field.
	 *
	 * @return void
	 */
	public function test_rest_field_is_present_and_typed() {
		$post_id = $this->make_post( array(), 500 );

		$data = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id ) )->get_data();

		$this->assertArrayHasKey( 'views', $data );
		$this->assertIsInt( $data['views'] );
		$this->assertSame( 500, $data['views'] );
	}

	/**
	 * A post with no meta row reports zero rather than an empty string.
	 *
	 * @return void
	 */
	public function test_rest_field_defaults_to_zero() {
		$post_id = $this->make_post( array(), null );

		$data = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id ) )->get_data();

		$this->assertSame( 0, $data['views'] );
	}

	/**
	 * _fields=views works, which is what the readme documents.
	 *
	 * @return void
	 */
	public function test_rest_field_survives_a_fields_request() {
		$this->make_post( array(), 500 );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts' );
		$request->set_param( '_fields', 'id,views' );
		$data = rest_do_request( $request )->get_data();

		$this->assertArrayHasKey( 'views', $data[0] );
	}

	/**
	 * Both list tables gain a Views column, and it is sortable.
	 *
	 * @return void
	 */
	public function test_admin_columns() {
		$this->assertSame( 'Views', apply_filters( 'manage_posts_columns', array() )['views'] );
		$this->assertSame( 'Views', apply_filters( 'manage_pages_columns', array() )['views'] );
		$this->assertSame( 'views', apply_filters( 'manage_edit-post_sortable_columns', array() )['views'] );
		$this->assertSame( 'views', apply_filters( 'manage_edit-page_sortable_columns', array() )['views'] );
	}

	/**
	 * The column renders even when the display matrix says "never".
	 *
	 * The matrix governs the front end only. Letting it apply here blanked the
	 * admin column, which is what went wrong in 1.65.
	 *
	 * @return void
	 */
	public function test_admin_column_ignores_the_display_matrix() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options(
			array_merge(
				array_fill_keys( $this->display_keys, 2 ),
				array( 'template' => '%VIEW_COUNT% views' )
			)
		);
		$this->set_context( array(), $post_id );

		$cell = $this->capture(
			function () use ( $post_id ) {
				$this->fire( 'manage_posts_custom_column', array( 'views', $post_id ) );
			}
		);

		$this->assertSame( '500 views', trim( $cell ) );
	}

	/**
	 * Another column name renders nothing.
	 *
	 * @return void
	 */
	public function test_admin_column_ignores_other_columns() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_context( array(), $post_id );

		$cell = $this->capture(
			function () use ( $post_id ) {
				$this->fire( 'manage_posts_custom_column', array( 'title', $post_id ) );
			}
		);

		$this->assertSame( '', trim( $cell ) );
	}

	/**
	 * Clicking the column header sorts numerically on the meta value.
	 *
	 * @return void
	 */
	public function test_admin_orderby_views() {
		set_current_screen( 'edit-post' );

		$query = new WP_Query();
		$query->set( 'orderby', 'views' );
		$this->fire( 'pre_get_posts', array( $query ) );

		$this->assertSame( 'views', $query->get( 'meta_key' ) );
		$this->assertSame( 'meta_value_num', $query->get( 'orderby' ) );

		set_current_screen( 'front' );
	}

	/**
	 * Both query vars are public.
	 *
	 * @return void
	 */
	public function test_query_vars_are_registered() {
		$vars = apply_filters( 'query_vars', array() );

		$this->assertContains( 'v_sortby', $vars );
		$this->assertContains( 'v_orderby', $vars );
	}

	/**
	 * ?v_sortby=views sorts in both directions.
	 *
	 * @return void
	 */
	public function test_v_sortby_orders_by_views() {
		$this->make_post( array( 'post_title' => 'Low' ), 5 );
		$this->make_post( array( 'post_title' => 'Mid' ), 500 );
		$this->make_post( array( 'post_title' => 'High' ), 123456 );

		$GLOBALS['wp_query']->set( 'v_orderby', 'desc' );
		$query = new WP_Query(
			array(
				'v_sortby'            => 'views',
				'post_type'           => 'post',
				'posts_per_page'      => 3,
				'ignore_sticky_posts' => true,
			)
		);
		$this->assertSame( array( 'High', 'Mid', 'Low' ), wp_list_pluck( $query->posts, 'post_title' ) );

		$GLOBALS['wp_query']->set( 'v_orderby', 'asc' );
		$query = new WP_Query(
			array(
				'v_sortby'            => 'views',
				'post_type'           => 'post',
				'posts_per_page'      => 3,
				'ignore_sticky_posts' => true,
			)
		);
		$this->assertSame( array( 'Low', 'Mid', 'High' ), wp_list_pluck( $query->posts, 'post_title' ) );
	}

	/**
	 * An invalid direction is validated away rather than reaching SQL.
	 *
	 * @return void
	 */
	public function test_invalid_v_orderby_falls_back_to_descending() {
		$this->make_post( array( 'post_title' => 'Low' ), 5 );
		$this->make_post( array( 'post_title' => 'High' ), 123456 );

		$GLOBALS['wp_query']->set( 'v_orderby', 'asc; DROP TABLE wp_posts' );
		$query = new WP_Query(
			array(
				'v_sortby'            => 'views',
				'post_type'           => 'post',
				'posts_per_page'      => 1,
				'ignore_sticky_posts' => true,
			)
		);

		$this->assertSame( array( 'High' ), wp_list_pluck( $query->posts, 'post_title' ) );
		$this->assertGreaterThan( 0, (int) wp_count_posts( 'post' )->publish );
	}

	/**
	 * The sorting filters come back off afterwards.
	 *
	 * They are global, so leaving them attached would join every later query
	 * on the request to postmeta and silently drop unviewed posts.
	 *
	 * @return void
	 */
	public function test_sorting_filters_are_detached_again() {
		$this->make_post( array( 'post_title' => 'Viewed' ), 5 );
		$unviewed = $this->make_post( array( 'post_title' => 'Unviewed' ), null );

		$GLOBALS['wp_query']->set( 'v_orderby', 'desc' );
		new WP_Query(
			array(
				'v_sortby'            => 'views',
				'post_type'           => 'post',
				'ignore_sticky_posts' => true,
			)
		);

		$GLOBALS['wp_query']->set( 'v_orderby', '' );
		$plain = new WP_Query(
			array(
				'post_type'           => 'post',
				'posts_per_page'      => 50,
				'ignore_sticky_posts' => true,
			)
		);

		$this->assertContains( $unviewed, wp_list_pluck( $plain->posts, 'ID' ) );
	}

	/**
	 * Publishing seeds a zero count; a draft gets nothing.
	 *
	 * @return void
	 */
	public function test_publishing_seeds_the_meta() {
		$published = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$draft     = self::factory()->post->create( array( 'post_status' => 'draft' ) );

		$this->assertSame( '0', get_post_meta( $published, 'views', true ) );
		$this->assertSame( '', get_post_meta( $draft, 'views', true ) );
	}

	/**
	 * Republishing must not reset a real count.
	 *
	 * @return void
	 */
	public function test_republishing_does_not_reset_the_count() {
		$post_id = $this->make_post( array(), 500 );

		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'publish',
			)
		);

		$this->assertSame( '500', get_post_meta( $post_id, 'views', true ) );
	}

	/**
	 * The direction is matched case insensitively.
	 *
	 * The readme tells people to "replace DESC with ASC", so an uppercase
	 * value is documented usage. Up to 1.78.1 it compared against lowercase
	 * only, so ?v_orderby=ASC silently fell through to descending - the exact
	 * opposite of what the reader asked for.
	 *
	 * @dataProvider data_orderby_casing
	 *
	 * @param string $orderby  Value of the v_orderby query var.
	 * @param array  $expected Expected post titles, in order.
	 * @return void
	 */
	public function test_v_orderby_is_case_insensitive( $orderby, $expected ) {
		$this->make_post( array( 'post_title' => 'Low' ), 5 );
		$this->make_post( array( 'post_title' => 'High' ), 123456 );

		$GLOBALS['wp_query']->set( 'v_orderby', $orderby );
		$query = new WP_Query(
			array(
				'v_sortby'            => 'views',
				'post_type'           => 'post',
				'posts_per_page'      => 2,
				'ignore_sticky_posts' => true,
			)
		);

		$this->assertSame( $expected, wp_list_pluck( $query->posts, 'post_title' ) );
	}

	/**
	 * Directions in the casings a reader might copy out of the readme.
	 *
	 * @return array
	 */
	public function data_orderby_casing() {
		return array(
			'lowercase asc'  => array( 'asc', array( 'Low', 'High' ) ),
			'uppercase ASC'  => array( 'ASC', array( 'Low', 'High' ) ),
			'mixed Asc'      => array( 'Asc', array( 'Low', 'High' ) ),
			'padded asc'     => array( '  asc  ', array( 'Low', 'High' ) ),
			'uppercase DESC' => array( 'DESC', array( 'High', 'Low' ) ),
			'absent'         => array( '', array( 'High', 'Low' ) ),
		);
	}

	/**
	 * The three SQL clause filters contribute what the sort needs.
	 *
	 * @return void
	 */
	public function test_sql_clause_filters() {
		global $wpdb;

		$this->assertStringContainsString( 'AS views', PostViews_Core::posts_fields( 'existing' ) );
		$this->assertStringStartsWith( 'existing', PostViews_Core::posts_fields( 'existing' ) );

		$this->assertStringContainsString( 'LEFT JOIN ' . $wpdb->postmeta, PostViews_Core::posts_join( 'existing' ) );
		$this->assertStringStartsWith( 'existing', PostViews_Core::posts_join( 'existing' ) );

		$this->assertStringContainsString( "meta_key = 'views'", PostViews_Core::posts_where( 'existing' ) );
		$this->assertStringStartsWith( 'existing', PostViews_Core::posts_where( 'existing' ) );
	}

	/**
	 * The ORDER BY clause admits only the two directions.
	 *
	 * It is interpolated rather than bound, because neither an identifier nor
	 * a sort direction can be a prepared parameter.
	 *
	 * @return void
	 */
	public function test_orderby_clause_admits_only_two_directions() {
		foreach ( array( 'asc; DROP TABLE wp_posts', "asc'", 'RAND()', 'nonsense', '' ) as $hostile ) {
			$GLOBALS['wp_query']->set( 'v_orderby', $hostile );

			$this->assertSame( ' views desc', PostViews_Core::posts_orderby( 'existing' ) );
		}

		$GLOBALS['wp_query']->set( 'v_orderby', 'asc' );
		$this->assertSame( ' views asc', PostViews_Core::posts_orderby( 'existing' ) );
	}

	/**
	 * A revision is not given a view count of its own.
	 *
	 * @return void
	 */
	public function test_revisions_are_not_seeded() {
		$parent      = $this->make_post( array(), 500 );
		$revision_id = self::factory()->post->create(
			array(
				'post_type'   => 'revision',
				'post_status' => 'inherit',
				'post_parent' => $parent,
			)
		);

		delete_post_meta( $revision_id, 'views' );
		PostViews_Core::seed_views_meta( $revision_id );

		$this->assertSame( '', get_post_meta( $revision_id, 'views', true ) );
	}

	/**
	 * Publishing a page seeds it too.
	 *
	 * @return void
	 */
	public function test_publishing_a_page_seeds_the_meta() {
		$page_id = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
			)
		);

		$this->assertSame( '0', get_post_meta( $page_id, 'views', true ) );
	}

	/**
	 * The REST field is registered on posts only.
	 *
	 * Pages have never carried it. Pinned so that adding it later is a
	 * deliberate change rather than an accident of refactoring.
	 *
	 * @return void
	 */
	public function test_rest_field_is_not_on_pages() {
		$page_id = $this->make_post( array( 'post_type' => 'page' ), 500 );

		$data = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/pages/' . $page_id ) )->get_data();

		$this->assertArrayNotHasKey( 'views', $data );
	}

	/**
	 * The REST field is read only and advertises its schema.
	 *
	 * @return void
	 */
	public function test_rest_field_schema() {
		$schema = rest_get_server()->get_routes()['/wp/v2/posts']['0']['args'] ?? array();
		$this->assertArrayNotHasKey( 'views', $schema, 'views must not be writable over REST.' );

		$post_id = $this->make_post( array(), 500 );
		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post_id );
		$request->set_param( 'context', 'edit' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		$this->assertArrayHasKey( 'views', rest_do_request( $request )->get_data() );
	}

	/**
	 * The REST callback coerces whatever the meta holds to an integer.
	 *
	 * @return void
	 */
	public function test_rest_callback_coerces_to_int() {
		$post_id = $this->make_post( array(), 0 );
		update_post_meta( $post_id, 'views', 'nonsense' );

		$this->assertSame( 0, PostViews_Core::rest_get_views( array( 'id' => $post_id ) ) );
	}

	/**
	 * Admin sorting leaves other columns alone.
	 *
	 * @return void
	 */
	public function test_admin_sorting_ignores_other_columns() {
		set_current_screen( 'edit-post' );

		$query = new WP_Query();
		$query->set( 'orderby', 'title' );
		$this->fire( 'pre_get_posts', array( $query ) );

		$this->assertSame( 'title', $query->get( 'orderby' ) );
		$this->assertSame( '', $query->get( 'meta_key' ) );

		set_current_screen( 'front' );
	}

	/**
	 * Admin sorting does not reach front end queries.
	 *
	 * @return void
	 */
	public function test_admin_sorting_does_not_apply_on_the_front_end() {
		set_current_screen( 'front' );

		$query = new WP_Query();
		$query->set( 'orderby', 'views' );
		$this->fire( 'pre_get_posts', array( $query ) );

		$this->assertSame( 'views', $query->get( 'orderby' ), 'The front end query should be untouched.' );
		$this->assertSame( '', $query->get( 'meta_key' ) );
	}

	/**
	 * The query vars are added to whatever is already registered.
	 *
	 * @return void
	 */
	public function test_query_vars_are_appended() {
		$vars = PostViews_Core::query_vars( array( 'existing_var' ) );

		$this->assertContains( 'existing_var', $vars );
		$this->assertContains( 'v_sortby', $vars );
		$this->assertContains( 'v_orderby', $vars );
	}
}
