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
	 * The registered widget, found by id_base rather than class name.
	 *
	 * @return WP_Widget|null
	 */
	protected function get_widget() {
		global $wp_widget_factory;

		foreach ( $wp_widget_factory->widgets as $widget ) {
			if ( 'views' === $widget->id_base ) {
				return $widget;
			}
		}

		return null;
	}

	/**
	 * The widget keeps its id_base, and therefore its option row.
	 *
	 * Changing it would orphan every configured widget on every site.
	 *
	 * @return void
	 */
	public function test_widget_option_name_is_unchanged() {
		$widget = $this->get_widget();

		$this->assertNotNull( $widget, 'No widget with id_base "views" is registered.' );
		$this->assertSame( 'widget_views', $widget->option_name );
	}

	/**
	 * The widget renders a listing inside the sidebar wrappers.
	 *
	 * @return void
	 */
	public function test_widget_renders() {
		$this->make_post(
			array(
				'post_title' => 'Top Page',
				'post_type'  => 'page',
			),
			7777
		);
		$this->set_options( array( 'most_viewed_template' => '<li>%POST_TITLE%</li>' ) );

		$widget = $this->get_widget();
		$output = $this->capture(
			function () use ( $widget ) {
				$widget->widget(
					array(
						'before_widget' => '<aside>',
						'after_widget'  => '</aside>',
						'before_title'  => '<h2>',
						'after_title'   => '</h2>',
					),
					array(
						'title' => 'Top Views',
						'type'  => 'most_viewed',
						'mode'  => 'page',
						'limit' => 2,
						'chars' => 0,
					)
				);
			}
		);

		$this->assertStringContainsString( '<aside>', $output );
		$this->assertStringContainsString( '<h2>Top Views</h2>', $output );
		$this->assertStringContainsString( '<li>Top Page</li>', $output );
		$this->assertStringContainsString( '</aside>', $output );
	}

	/**
	 * The category widget type scopes to its category IDs.
	 *
	 * @return void
	 */
	public function test_widget_category_scoping() {
		$wanted = self::factory()->category->create( array( 'name' => 'Wanted' ) );
		$in     = $this->make_post( array( 'post_title' => 'In Category' ), 500 );
		$this->make_post( array( 'post_title' => 'Out Of Category' ), 900 );
		wp_set_post_categories( $in, array( $wanted ) );

		$this->set_options( array( 'most_viewed_template' => '<li>%POST_TITLE%</li>' ) );

		$widget = $this->get_widget();
		$output = $this->capture(
			function () use ( $widget, $wanted ) {
				$widget->widget(
					array(
						'before_widget' => '',
						'after_widget'  => '',
						'before_title'  => '',
						'after_title'   => '',
					),
					array(
						'title'   => '',
						'type'    => 'most_viewed_category',
						'mode'    => 'post',
						'limit'   => 5,
						'chars'   => 0,
						'cat_ids' => (string) $wanted,
					)
				);
			}
		);

		$this->assertStringContainsString( 'In Category', $output );
		$this->assertStringNotContainsString( 'Out Of Category', $output );
	}

	/**
	 * A sparse instance, which is what the block widget editor and the
	 * customizer hand over, must render without notices.
	 *
	 * @return void
	 */
	public function test_widget_tolerates_a_sparse_instance() {
		$this->make_post( array(), 5 );
		$widget = $this->get_widget();

		$output = $this->capture(
			function () use ( $widget ) {
				$widget->widget(
					array(
						'before_widget' => '',
						'after_widget'  => '',
						'before_title'  => '',
						'after_title'   => '',
					),
					array( 'title' => 'Bare' )
				);
			}
		);

		$this->assertStringContainsString( '<ul>', $output );
	}

	/**
	 * The update() method saves without the hidden "submit" field.
	 *
	 * 1.78.1 bailed out unless that field was present, so the block widget
	 * editor and the customizer silently discarded every change.
	 *
	 * @return void
	 */
	public function test_widget_update_saves_without_the_submit_field() {
		$widget = $this->get_widget();

		$saved = $widget->update(
			array(
				'title'   => 'New <b>Title</b>',
				'type'    => 'least_viewed',
				'mode'    => 'post',
				'limit'   => '7',
				'chars'   => '30',
				'cat_ids' => '1,2',
			),
			array()
		);

		$this->assertSame( 'New Title', $saved['title'] );
		$this->assertSame( 'least_viewed', $saved['type'] );
		$this->assertSame( 7, $saved['limit'] );
		$this->assertSame( 30, $saved['chars'] );
		$this->assertSame( '1,2', $saved['cat_ids'] );
	}

	/**
	 * An unknown statistics type is rejected rather than stored.
	 *
	 * @return void
	 */
	public function test_widget_update_rejects_an_unknown_type() {
		$widget = $this->get_widget();

		$saved = $widget->update( array( 'type' => 'not_a_type' ), array( 'type' => 'most_viewed' ) );

		$this->assertSame( 'most_viewed', $saved['type'] );
	}
}
