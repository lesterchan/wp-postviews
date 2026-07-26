<?php
/**
 * The Views widget.
 *
 * The widget is looked up by id_base rather than by class name throughout:
 * the class was renamed from WP_Widget_PostViews in 2.0.0, but 'views' is what
 * names the widget_views option row and must never change.
 *
 * @package WP-PostViews
 */

/**
 * Widget rendering, saving and form.
 */
class Test_PostViews_Widget extends PostViews_TestCase {

	/**
	 * Standard sidebar wrappers.
	 *
	 * @var array
	 */
	protected $args = array(
		'before_widget' => '<aside>',
		'after_widget'  => '</aside>',
		'before_title'  => '<h2>',
		'after_title'   => '</h2>',
	);

	/**
	 * The registered widget instance.
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
	 * Render the widget and return its markup.
	 *
	 * @param array $instance Widget settings.
	 * @return string
	 */
	protected function render( $instance ) {
		$widget = $this->get_widget();

		return $this->capture(
			function () use ( $widget, $instance ) {
				$widget->widget( $this->args, $instance );
			}
		);
	}

	/**
	 * The widget is registered and keeps its option row.
	 *
	 * @return void
	 */
	public function test_widget_is_registered_with_a_stable_option_name() {
		$widget = $this->get_widget();

		$this->assertNotNull( $widget, 'No widget with id_base "views" is registered.' );
		$this->assertSame( 'widget_views', $widget->option_name );
	}

	/**
	 * Selective refresh is on, so the customizer does not reload the page.
	 *
	 * @return void
	 */
	public function test_widget_supports_selective_refresh() {
		$this->assertTrue( ! empty( $this->get_widget()->widget_options['customize_selective_refresh'] ) );
	}

	/**
	 * The sidebar wrappers and the title are emitted.
	 *
	 * @return void
	 */
	public function test_widget_renders_wrappers_and_title() {
		$this->make_post(
			array(
				'post_title' => 'Top Page',
				'post_type'  => 'page',
			),
			7777
		);
		$this->set_options( array( 'most_viewed_template' => '<li>%POST_TITLE%</li>' ) );

		$output = $this->render(
			array(
				'title' => 'Top Views',
				'type'  => 'most_viewed',
				'mode'  => 'page',
				'limit' => 2,
				'chars' => 0,
			)
		);

		$this->assertStringContainsString( '<aside>', $output );
		$this->assertStringContainsString( '<h2>Top Views</h2>', $output );
		$this->assertStringContainsString( '<ul>', $output );
		$this->assertStringContainsString( '<li>Top Page</li>', $output );
		$this->assertStringContainsString( '</ul>', $output );
		$this->assertStringContainsString( '</aside>', $output );
	}

	/**
	 * An empty title emits no heading at all, rather than an empty one.
	 *
	 * @return void
	 */
	public function test_empty_title_emits_no_heading() {
		$this->make_post( array(), 5 );

		$output = $this->render(
			array(
				'title' => '',
				'type'  => 'most_viewed',
			)
		);

		$this->assertStringNotContainsString( '<h2>', $output );
		$this->assertStringContainsString( '<ul>', $output );
	}

	/**
	 * A title carrying markup is escaped by the time it reaches the page.
	 *
	 * Two things escape it: core registers esc_html on the widget_title
	 * filter, and the widget escapes again at the point of output. Either
	 * alone is sufficient, so this asserts the end state rather than which of
	 * them did the work - removing the widget's own call does not make this
	 * fail, and it should not, because the title is still safe.
	 *
	 * @return void
	 */
	public function test_title_is_escaped() {
		$this->make_post( array(), 5 );

		$output = $this->render(
			array(
				'title' => '<script>alert(1)</script>',
				'type'  => 'most_viewed',
			)
		);

		$this->assertStringNotContainsString( '<script>', $output );
		$this->assertStringContainsString( '&lt;script&gt;', $output );
	}

	/**
	 * Escaping the title twice does not double encode it.
	 *
	 * This is the assertion that has teeth. Three callbacks run on
	 * widget_title before the widget sees the string: wptexturize turns the
	 * ampersand into the numeric entity &#038;, then convert_chars, then
	 * esc_html. The widget escapes once more at the point of output, which is
	 * a no-op only because esc_html() passes $double_encode = false. Swap it
	 * for something that does double encode - htmlspecialchars() with its own
	 * defaults, say - and every ampersand in every widget title on the site
	 * renders as "&amp;#038;".
	 *
	 * @return void
	 */
	public function test_title_is_not_double_encoded() {
		$this->make_post( array(), 5 );

		$output = $this->render(
			array(
				'title' => 'Tom & Jerry',
				'type'  => 'most_viewed',
			)
		);

		$this->assertStringContainsString( '<h2>Tom &#038; Jerry</h2>', $output );
		$this->assertStringNotContainsString( '&amp;#038;', $output );
	}

	/**
	 * The widget_title filter applies, as it does for every core widget.
	 *
	 * @return void
	 */
	public function test_widget_title_filter_applies() {
		$this->make_post( array(), 5 );
		add_filter(
			'widget_title',
			function () {
				return 'Filtered';
			}
		);

		$this->assertStringContainsString( '<h2>Filtered</h2>', $this->render( array( 'title' => 'Original' ) ) );
	}

	/**
	 * Each statistics type selects the right listing.
	 *
	 * @dataProvider data_statistics_types
	 *
	 * @param string $type     Widget statistics type.
	 * @param string $expected Title that should lead the list.
	 * @param string $excluded Title that should not appear first.
	 * @return void
	 */
	public function test_statistics_types( $type, $expected, $excluded ) {
		$category = self::factory()->category->create( array( 'name' => 'Scoped' ) );

		$low  = $this->make_post( array( 'post_title' => 'Low' ), 5 );
		$high = $this->make_post( array( 'post_title' => 'High' ), 900 );
		wp_set_post_categories( $low, array( $category ) );
		wp_set_post_categories( $high, array( $category ) );
		$this->make_post( array( 'post_title' => 'Uncategorised Middle' ), 400 );

		$this->set_options( array( 'most_viewed_template' => '<li>%POST_TITLE%</li>' ) );

		$output = $this->render(
			array(
				'title'   => '',
				'type'    => $type,
				'mode'    => 'post',
				'limit'   => 1,
				'chars'   => 0,
				'cat_ids' => (string) $category,
			)
		);

		$this->assertStringContainsString( '<li>' . $expected . '</li>', $output );
		$this->assertStringNotContainsString( '<li>' . $excluded . '</li>', $output );
	}

	/**
	 * The four types the form offers.
	 *
	 * @return array
	 */
	public function data_statistics_types() {
		return array(
			'most viewed'              => array( 'most_viewed', 'High', 'Low' ),
			'least viewed'             => array( 'least_viewed', 'Low', 'High' ),
			'most viewed by category'  => array( 'most_viewed_category', 'High', 'Uncategorised Middle' ),
			'least viewed by category' => array( 'least_viewed_category', 'Low', 'Uncategorised Middle' ),
		);
	}

	/**
	 * Several comma separated category IDs are honoured.
	 *
	 * @return void
	 */
	public function test_multiple_category_ids() {
		$first  = self::factory()->category->create( array( 'name' => 'First' ) );
		$second = self::factory()->category->create( array( 'name' => 'Second' ) );

		$in_first  = $this->make_post( array( 'post_title' => 'In First' ), 100 );
		$in_second = $this->make_post( array( 'post_title' => 'In Second' ), 200 );
		wp_set_post_categories( $in_first, array( $first ) );
		wp_set_post_categories( $in_second, array( $second ) );
		$this->make_post( array( 'post_title' => 'In Neither' ), 300 );

		$this->set_options( array( 'most_viewed_template' => '<li>%POST_TITLE%</li>' ) );

		$output = $this->render(
			array(
				'title'   => '',
				'type'    => 'most_viewed_category',
				'mode'    => 'post',
				'limit'   => 10,
				'chars'   => 0,
				'cat_ids' => $first . ',' . $second,
			)
		);

		$this->assertStringContainsString( 'In First', $output );
		$this->assertStringContainsString( 'In Second', $output );
		$this->assertStringNotContainsString( 'In Neither', $output );
	}

	/**
	 * Junk in the category field is discarded rather than reaching the query.
	 *
	 * @return void
	 */
	public function test_category_ids_are_sanitised() {
		$this->make_post( array( 'post_title' => 'Anything' ), 5 );
		$this->set_options( array( 'most_viewed_template' => '<li>%POST_TITLE%</li>' ) );

		$output = $this->render(
			array(
				'title'   => '',
				'type'    => 'most_viewed_category',
				'mode'    => 'post',
				'limit'   => 5,
				'chars'   => 0,
				'cat_ids' => "1' OR 1=1 --,abc",
			)
		);

		$this->assertIsString( $output );
		$this->assertStringContainsString( '<ul>', $output );
	}

	/**
	 * The chars setting truncates the titles the widget lists.
	 *
	 * @return void
	 */
	public function test_chars_truncates_widget_titles() {
		$this->make_post( array( 'post_title' => 'An Extremely Long Post Title' ), 500 );
		$this->set_options( array( 'most_viewed_template' => '<li>%POST_TITLE%</li>' ) );

		$output = $this->render(
			array(
				'title' => '',
				'type'  => 'most_viewed',
				'mode'  => 'post',
				'limit' => 1,
				'chars' => 6,
			)
		);

		$this->assertStringContainsString( '<li>An Ext...</li>', $output );
	}

	/**
	 * An empty listing still renders the list wrapper and the N/A item.
	 *
	 * @return void
	 */
	public function test_empty_listing_renders_na() {
		$output = $this->render(
			array(
				'title' => 'Nothing',
				'type'  => 'most_viewed',
				'mode'  => 'post',
			)
		);

		$this->assertStringContainsString( '<ul>', $output );
		$this->assertStringContainsString( 'N/A', $output );
	}

	/**
	 * A sparse instance renders without notices.
	 *
	 * This is what the block based widget editor and the customizer hand over,
	 * and it warned on six undefined keys before 2.0.0.
	 *
	 * @return void
	 */
	public function test_sparse_instance_renders_cleanly() {
		$this->make_post( array(), 5 );

		$output = $this->render( array( 'title' => 'Bare' ) );

		$this->assertStringContainsString( '<ul>', $output );
	}

	/**
	 * A completely empty instance renders without notices too.
	 *
	 * @return void
	 */
	public function test_empty_instance_renders_cleanly() {
		$this->make_post( array(), 5 );

		$this->assertStringContainsString( '<ul>', $this->render( array() ) );
	}

	/**
	 * Saving works without the hidden "submit" field.
	 *
	 * 1.78.1 bailed out unless that field was present, so the block widget
	 * editor and the customizer silently discarded every change.
	 *
	 * @return void
	 */
	public function test_update_saves_without_the_submit_field() {
		$saved = $this->get_widget()->update(
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
	 * An unknown statistics type is rejected, keeping the stored one.
	 *
	 * @return void
	 */
	public function test_update_rejects_an_unknown_type() {
		$saved = $this->get_widget()->update( array( 'type' => 'not_a_type' ), array( 'type' => 'most_viewed' ) );

		$this->assertSame( 'most_viewed', $saved['type'] );
	}

	/**
	 * Keys the submission omits keep their previous values.
	 *
	 * @return void
	 */
	public function test_update_preserves_omitted_keys() {
		$saved = $this->get_widget()->update(
			array( 'title' => 'Only The Title' ),
			array(
				'limit' => 42,
				'type'  => 'least_viewed',
			)
		);

		$this->assertSame( 'Only The Title', $saved['title'] );
		$this->assertSame( 42, $saved['limit'] );
		$this->assertSame( 'least_viewed', $saved['type'] );
	}

	/**
	 * Numeric fields are coerced, and junk becomes zero rather than a string.
	 *
	 * @return void
	 */
	public function test_update_coerces_numeric_fields() {
		$saved = $this->get_widget()->update(
			array(
				'limit' => 'abc',
				'chars' => '12abc',
			),
			array()
		);

		$this->assertSame( 0, $saved['limit'] );
		$this->assertSame( 12, $saved['chars'] );
	}

	/**
	 * Category IDs are normalised to a comma separated list of integers.
	 *
	 * @return void
	 */
	public function test_update_normalises_category_ids() {
		$saved = $this->get_widget()->update( array( 'cat_ids' => '3, 4 ,abc' ), array() );

		$this->assertSame( '3,4,0', $saved['cat_ids'] );
	}

	/**
	 * The form renders every field, with values escaped into the attributes.
	 *
	 * @return void
	 */
	public function test_form_renders_every_field() {
		$widget = $this->get_widget();
		$widget->_set( 1 );

		$html = $this->capture(
			function () use ( $widget ) {
				$widget->form( array() );
			}
		);

		foreach ( array( 'title', 'type', 'mode', 'limit', 'chars', 'cat_ids' ) as $field ) {
			$this->assertStringContainsString( $widget->get_field_name( $field ), $html, "The {$field} field is missing." );
		}

		foreach ( array( 'least_viewed', 'least_viewed_category', 'most_viewed', 'most_viewed_category' ) as $type ) {
			$this->assertStringContainsString( 'value="' . $type . '"', $html );
		}
	}

	/**
	 * The form emits no empty optgroup.
	 *
	 * 1.78.1 had a bare <optgroup>&nbsp;</optgroup> as a spacer, which is
	 * invalid markup.
	 *
	 * @return void
	 */
	public function test_form_emits_no_empty_optgroup() {
		$widget = $this->get_widget();
		$widget->_set( 1 );

		$html = $this->capture(
			function () use ( $widget ) {
				$widget->form( array() );
			}
		);

		$this->assertStringNotContainsString( '<optgroup', $html );
	}

	/**
	 * A stored value containing quotes cannot break out of its attribute.
	 *
	 * @return void
	 */
	public function test_form_escapes_stored_values() {
		$widget = $this->get_widget();
		$widget->_set( 1 );

		$html = $this->capture(
			function () use ( $widget ) {
				$widget->form( array( 'title' => '" onfocus="alert(1)' ) );
			}
		);

		$this->assertStringNotContainsString( 'onfocus="alert(1)"', $html );
		$this->assertStringContainsString( '&quot;', $html );
	}

	/**
	 * The saved type is preselected when the form reopens.
	 *
	 * @return void
	 */
	public function test_form_preselects_the_saved_type() {
		$widget = $this->get_widget();
		$widget->_set( 1 );

		$html = $this->capture(
			function () use ( $widget ) {
				$widget->form( array( 'type' => 'least_viewed_category' ) );
			}
		);

		$this->assertMatchesRegularExpression( '/value="least_viewed_category"\s+selected/', $html );
	}

	/**
	 * The post type dropdown lists the public types.
	 *
	 * @return void
	 */
	public function test_form_lists_public_post_types() {
		$widget = $this->get_widget();
		$widget->_set( 1 );

		$html = $this->capture(
			function () use ( $widget ) {
				$widget->form( array() );
			}
		);

		$this->assertStringContainsString( 'value="post"', $html );
		$this->assertStringContainsString( 'value="page"', $html );
	}
}
