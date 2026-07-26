<?php
/**
 * The six listing template tags: ordering, post type filtering, category and
 * tag scoping, the N/A fallback, and every %TOKEN%.
 *
 * @package WP-PostViews
 */

/**
 * Most and least viewed listings.
 */
class Test_PostViews_Listings extends PostViews_TestCase {

	/**
	 * Post IDs by title.
	 *
	 * @var array
	 */
	protected $ids = array();

	/**
	 * Category IDs.
	 *
	 * @var array
	 */
	protected $cats = array();

	/**
	 * Seed a known corpus.
	 *
	 * @return void
	 */
	public function set_up() {
		parent::set_up();

		$this->cats = array(
			'a' => self::factory()->category->create( array( 'name' => 'Alpha' ) ),
			'b' => self::factory()->category->create( array( 'name' => 'Beta' ) ),
		);

		$fixtures = array(
			array( 'Low Post', 'post', 5, 'a', 'red' ),
			array( 'Mid Post', 'post', 500, 'a', 'blue' ),
			array( 'High Post', 'post', 123456, 'b', 'red' ),
			array( 'Huge Post', 'post', 2500000, 'b', 'blue' ),
			array( 'Some Page', 'page', 42, null, '' ),
			array( 'Other Page', 'page', 7777, null, '' ),
		);

		foreach ( $fixtures as $fixture ) {
			list( $title, $type, $views, $cat, $tag ) = $fixture;

			$post_id = $this->make_post(
				array(
					'post_title'   => $title,
					'post_type'    => $type,
					'post_content' => 'Body of ' . $title . '.',
					'post_excerpt' => 'Excerpt of ' . $title . '.',
					'post_date'    => '2026-03-04 05:06:07',
				),
				$views
			);

			if ( $cat ) {
				wp_set_post_categories( $post_id, array( $this->cats[ $cat ] ) );
			}
			if ( $tag ) {
				wp_set_post_tags( $post_id, array( $tag ) );
			}

			$this->ids[ $title ] = $post_id;
		}

		// Never viewed: no meta row at all.
		$this->ids['No Meta'] = $this->make_post( array( 'post_title' => 'No Meta Post' ), null );

		$this->set_options( array( 'most_viewed_template' => '<li><a href="%POST_URL%">%POST_TITLE%</a></li>' ) );
	}

	/**
	 * Most viewed is descending.
	 *
	 * @return void
	 */
	public function test_most_viewed_is_descending() {
		$this->assertSame(
			array( 'Huge Post', 'High Post', 'Mid Post' ),
			$this->listed_titles( get_most_viewed( 'post', 3, 0, false ) )
		);
	}

	/**
	 * Least viewed is ascending.
	 *
	 * @return void
	 */
	public function test_least_viewed_is_ascending() {
		$this->assertSame(
			array( 'Low Post', 'Mid Post', 'High Post' ),
			$this->listed_titles( get_least_viewed( 'post', 3, 0, false ) )
		);
	}

	/**
	 * The mode argument filters by post type.
	 *
	 * @return void
	 */
	public function test_mode_filters_by_post_type() {
		$this->assertSame(
			array( 'Other Page', 'Some Page' ),
			$this->listed_titles( get_most_viewed( 'page', 5, 0, false ) )
		);
	}

	/**
	 * An empty mode, and 'both', span every post type.
	 *
	 * @return void
	 */
	public function test_empty_mode_and_both_span_every_type() {
		$expected = array( 'Huge Post', 'High Post', 'Other Page' );

		$this->assertSame( $expected, $this->listed_titles( get_most_viewed( '', 3, 0, false ) ) );
		$this->assertSame( $expected, $this->listed_titles( get_most_viewed( 'both', 3, 0, false ) ) );
	}

	/**
	 * An array of post types is accepted. Documented in the readme.
	 *
	 * @return void
	 */
	public function test_mode_accepts_an_array() {
		$this->assertSame(
			array( 'Other Page', 'Some Page' ),
			$this->listed_titles( get_most_viewed( array( 'page' ), 5, 0, false ) )
		);
	}

	/**
	 * The limit is respected.
	 *
	 * @return void
	 */
	public function test_limit_is_respected() {
		$this->assertCount( 2, $this->listed_titles( get_most_viewed( 'post', 2, 0, false ) ) );
	}

	/**
	 * A post that has never been viewed is not listed at zero.
	 *
	 * @return void
	 */
	public function test_unviewed_posts_are_excluded() {
		$this->assertStringNotContainsString( 'No Meta Post', get_most_viewed( 'post', 20, 0, false ) );
	}

	/**
	 * Category scoping, both directions.
	 *
	 * @return void
	 */
	public function test_category_scoping() {
		$this->assertSame(
			array( 'Huge Post', 'High Post' ),
			$this->listed_titles( get_most_viewed_category( $this->cats['b'], 'post', 5, 0, false ) )
		);
		$this->assertSame(
			array( 'Low Post', 'Mid Post' ),
			$this->listed_titles( get_least_viewed_category( $this->cats['a'], 'post', 5, 0, false ) )
		);
	}

	/**
	 * Tag scoping, both directions.
	 *
	 * @return void
	 */
	public function test_tag_scoping() {
		$red  = get_term_by( 'slug', 'red', 'post_tag' );
		$blue = get_term_by( 'slug', 'blue', 'post_tag' );

		$this->assertSame(
			array( 'High Post', 'Low Post' ),
			$this->listed_titles( get_most_viewed_tag( $red->term_id, 'post', 5, 0, false ) )
		);
		$this->assertSame(
			array( 'Mid Post', 'Huge Post' ),
			$this->listed_titles( get_least_viewed_tag( $blue->term_id, 'post', 5, 0, false ) )
		);
	}

	/**
	 * An empty result set renders the N/A list item.
	 *
	 * @return void
	 */
	public function test_empty_result_renders_na() {
		$empty = self::factory()->category->create( array( 'name' => 'Empty' ) );

		$this->assertSame(
			'<li>N/A</li>' . "\n",
			get_most_viewed_category( $empty, 'post', 5, 0, false )
		);
	}

	/**
	 * Echoing and returning agree.
	 *
	 * @return void
	 */
	public function test_display_true_echoes_what_display_false_returns() {
		$this->assertSame(
			get_most_viewed( 'page', 1, 0, false ),
			$this->capture(
				function () {
					get_most_viewed( 'page', 1 );
				}
			)
		);
	}

	/**
	 * Every advertised token substitutes.
	 *
	 * @return void
	 */
	public function test_every_token_substitutes() {
		$tokens = array(
			'VIEW_COUNT',
			'VIEW_COUNT_ROUNDED',
			'POST_TITLE',
			'POST_EXCERPT',
			'POST_CONTENT',
			'POST_URL',
			'POST_DATE',
			'POST_TIME',
			'POST_THUMBNAIL',
			'POST_THUMBNAIL_URL',
			'POST_CATEGORY_ID',
			'POST_AUTHOR',
		);

		$template = '';
		foreach ( $tokens as $token ) {
			$template .= "[$token:%$token%]";
		}
		$this->set_options( array( 'most_viewed_template' => $template ) );

		$output  = get_most_viewed( 'page', 1, 0, false );
		$post_id = $this->ids['Other Page'];

		$expected = array(
			'VIEW_COUNT'         => '7,777',
			'VIEW_COUNT_ROUNDED' => '7.8K',
			'POST_TITLE'         => 'Other Page',
			'POST_EXCERPT'       => 'Excerpt of Other Page.',
			'POST_CONTENT'       => 'Body of Other Page.',
			'POST_URL'           => get_permalink( $post_id ),
			'POST_DATE'          => get_the_time( get_option( 'date_format' ), $post_id ),
			'POST_TIME'          => get_the_time( get_option( 'time_format' ), $post_id ),
			// No featured image, so both thumbnail tokens render empty rather
			// than the literal false that get_the_post_thumbnail_url() returns.
			'POST_THUMBNAIL'     => '',
			'POST_THUMBNAIL_URL' => '',
			'POST_CATEGORY_ID'   => '0',
		);

		foreach ( $expected as $token => $value ) {
			$this->assertStringContainsString( "[$token:$value]", $output, "%$token% did not render as expected." );
		}

		$this->assertStringNotContainsString( '%POST_', $output, 'A token was left unsubstituted.' );
	}

	/**
	 * The category token reports the post's first category.
	 *
	 * @return void
	 */
	public function test_post_category_id_token() {
		$this->set_options( array( 'most_viewed_template' => '[%POST_CATEGORY_ID%]' ) );

		$this->assertSame(
			'[' . $this->cats['b'] . ']',
			get_most_viewed_category( $this->cats['b'], 'post', 1, 0, false )
		);
	}

	/**
	 * The chars argument truncates titles.
	 *
	 * @return void
	 */
	public function test_chars_truncates_titles() {
		$this->set_options( array( 'most_viewed_template' => '[%POST_TITLE%]' ) );

		$this->assertSame( '[Other...]', get_most_viewed( 'page', 1, 5, false ) );
		$this->assertSame( '[Other Page]', get_most_viewed( 'page', 1, 0, false ) );
		$this->assertSame( '[Other Page]', get_most_viewed( 'page', 1, 50, false ) );
	}

	/**
	 * A multibyte title survives truncation inside a listing.
	 *
	 * @return void
	 */
	public function test_multibyte_title_survives_truncation() {
		$this->make_post( array( 'post_title' => '日本語のタイトルです' ), 999999999 );
		$this->set_options( array( 'most_viewed_template' => '[%POST_TITLE%]' ) );

		$this->assertSame( '[日本語のタ...]', get_most_viewed( 'post', 1, 5, false ) );
	}
}
