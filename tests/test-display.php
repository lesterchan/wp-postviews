<?php
/**
 * Output for a single post: the_views(), the wp_postviews_should_display gate,
 * the [views] shortcode, and the two number helpers the templates rely on.
 *
 * @package WP-PostViews
 */

/**
 * Single post output.
 */
class WP_PostViews_Display_Test extends WP_PostViews_TestCase {

	/**
	 * The template tag substitutes the count.
	 *
	 * @return void
	 */
	public function test_the_views_substitutes_view_count() {
		$post_id = $this->make_post( array(), 123456 );
		$this->set_options( array( 'template' => '%VIEW_COUNT% views' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$this->assertSame( '123,456 views', the_views( false ) );
	}

	/**
	 * Prefix and postfix wrap the rendered template.
	 *
	 * @return void
	 */
	public function test_the_views_honours_prefix_and_postfix() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options( array( 'template' => '%VIEW_COUNT%' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$this->assertSame( '[500]', the_views( false, '[', ']' ) );
	}

	/**
	 * Echoing and returning produce the same string.
	 *
	 * @return void
	 */
	public function test_the_views_echoes_what_it_returns() {
		$post_id = $this->make_post( array(), 42 );
		$this->set_options( array( 'template' => '%VIEW_COUNT% views' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$this->assertSame(
			the_views( false ),
			$this->capture(
				function () {
					the_views();
				}
			)
		);
	}

	/**
	 * A post with no meta row reads as zero, not empty.
	 *
	 * @return void
	 */
	public function test_missing_meta_renders_as_zero() {
		$post_id = $this->make_post( array(), null );
		$this->set_options( array( 'template' => '%VIEW_COUNT% views' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$this->assertSame( '0 views', the_views( false ) );
	}

	/**
	 * The wp_postviews_the_views filter can rewrite the output.
	 *
	 * @return void
	 */
	public function test_the_views_filter_applies() {
		$post_id = $this->make_post( array(), 7 );
		$this->set_options( array( 'template' => '%VIEW_COUNT%' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		add_filter(
			'wp_postviews_the_views',
			function ( $output ) {
				return '<b>' . $output . '</b>';
			}
		);

		$this->assertSame( '<b>7</b>', the_views( false ) );
	}

	/**
	 * Nothing hides the count unless something asks for it to be hidden.
	 *
	 * The six display_* settings this replaced all defaulted to "everyone", so
	 * an unfiltered install showing the count on every context is the behaviour
	 * being kept, not a new one.
	 *
	 * @dataProvider data_contexts
	 *
	 * @param array $flags Query flags describing the context.
	 * @return void
	 */
	public function test_the_count_is_displayed_in_every_context_by_default( $flags ) {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options( array( 'template' => 'X' ) );
		$this->set_context( $flags, $post_id );

		$this->assertSame( 'X', the_views( false ) );
	}

	/**
	 * The filter hides the count, in every context.
	 *
	 * @dataProvider data_contexts
	 *
	 * @param array $flags Query flags describing the context.
	 * @return void
	 */
	public function test_the_should_display_filter_hides_the_count( $flags ) {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options( array( 'template' => 'X' ) );
		$this->set_context( $flags, $post_id );
		$this->hide_views();

		$this->assertSame( '', the_views( false ) );
	}

	/**
	 * The six contexts the retired matrix had a row for each.
	 *
	 * @return array
	 */
	public function data_contexts() {
		return array(
			'home'    => array( array( 'is_home' ) ),
			'single'  => array( array( 'is_single', 'is_singular' ) ),
			'page'    => array( array( 'is_page', 'is_singular' ) ),
			'archive' => array( array( 'is_archive' ) ),
			'search'  => array( array( 'is_search' ) ),
			'other'   => array( array() ),
		);
	}

	/**
	 * The filter can decide per context, which is the whole of what the matrix
	 * did and is the migration path the Upgrade Notice describes.
	 *
	 * @return void
	 */
	public function test_the_should_display_filter_can_decide_per_context() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options( array( 'template' => 'X' ) );

		add_filter(
			'wp_postviews_should_display',
			static function () {
				return ! is_archive();
			}
		);

		$this->set_context( array( 'is_archive' ), $post_id );
		$this->assertSame( '', the_views( false ) );

		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );
		$this->assertSame( 'X', the_views( false ) );
	}

	/**
	 * Whatever the filter returns is read as a boolean.
	 *
	 * A filter returning 0, '' or null is a theme saying "no", and must not
	 * become a PHP notice or a truthy string.
	 *
	 * @return void
	 */
	public function test_the_should_display_filter_is_cast_to_bool() {
		add_filter( 'wp_postviews_should_display', '__return_zero' );
		$this->assertFalse( WP_PostViews_Display::should_be_displayed(), 'A falsy filter value is cast to false rather than returned raw.' );

		remove_filter( 'wp_postviews_should_display', '__return_zero' );
		add_filter(
			'wp_postviews_should_display',
			static function () {
				return 'yes';
			}
		);
		$this->assertTrue( WP_PostViews_Display::should_be_displayed(), 'A truthy filter value is cast to true rather than returned raw.' );
	}

	/**
	 * The should_be_displayed() method stays public.
	 *
	 * The 2.0.0 Upgrade Notice names it as the replacement for the old global
	 * should_views_be_displayed(), so removing it would break a promise made in
	 * the release that made it.
	 *
	 * @return void
	 */
	public function test_should_be_displayed_is_still_a_public_method() {
		$method = new ReflectionMethod( 'WP_PostViews_Display', 'should_be_displayed' );

		$this->assertTrue( $method->isPublic(), 'should_be_displayed() is still public; the Upgrade Notice names it as the replacement.' );
		$this->assertTrue( $method->isStatic(), 'should_be_displayed() is still static, as the Upgrade Notice documents it.' );
		$this->assertTrue( WP_PostViews_Display::should_be_displayed(), 'Unfiltered, the answer is yes.' );
	}

	/**
	 * $always bypasses the gate. The admin column depends on it, and it
	 * regressed once before, in 1.65.
	 *
	 * @return void
	 */
	public function test_always_bypasses_the_display_gate() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options( array( 'template' => 'X' ) );
		$this->set_context( array( 'is_home' ), $post_id );
		$this->hide_views();

		$this->assertSame( 'X', the_views( false, '', '', true ) );
		$this->assertSame( '', the_views( false ) );
	}

	/**
	 * The rounding boundaries.
	 *
	 * @dataProvider data_rounded_counts
	 *
	 * @param int    $count    Raw view count.
	 * @param string $expected Rendered abbreviation.
	 * @return void
	 */
	public function test_view_count_rounded( $count, $expected ) {
		$post_id = $this->make_post( array(), $count );
		$this->set_options( array( 'template' => '%VIEW_COUNT_ROUNDED%' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$this->assertSame( $expected, the_views( false ) );
	}

	/**
	 * Counts either side of each unit boundary.
	 *
	 * The three at the top are the regression: the unit used to be chosen from
	 * the raw number, so rounding could tip the result to "1000K" or "1000M".
	 *
	 * @return array
	 */
	public function data_rounded_counts() {
		return array(
			'999,950 promotes to M'     => array( 999950, '1M' ),
			'999,999,999 promotes to B' => array( 999999999, '1B' ),
			'999,499 stays in K'        => array( 999499, '999.5K' ),
			'below the threshold'       => array( 999, '999' ),
			'exactly one K'             => array( 1000, '1K' ),
			'one decimal place'         => array( 7777, '7.8K' ),
			'hundreds of thousands'     => array( 123456, '123.5K' ),
			'millions'                  => array( 2500000, '2.5M' ),
			'zero'                      => array( 0, '0' ),
		);
	}

	/**
	 * Title truncation counts characters, not bytes.
	 *
	 * The multibyte branch used to be gated on MB_OVERLOAD_STRING, removed in
	 * PHP 8.0, so substr() cut mid-character and htmlentities() then rejected
	 * the invalid UTF-8 and returned an empty string.
	 *
	 * @return void
	 */
	public function test_snippet_text_is_multibyte_safe() {
		$this->assertSame( '日本語のタ...', WP_PostViews_Display::snippet_text( '日本語のタイトルです', 5 ) );
	}

	/**
	 * ASCII truncation, and the cases that must not be truncated.
	 *
	 * @return void
	 */
	public function test_snippet_text_ascii() {
		$this->assertSame( 'Other...', WP_PostViews_Display::snippet_text( 'Other Page', 5 ) );
		$this->assertSame( 'Other Page', WP_PostViews_Display::snippet_text( 'Other Page', 50 ) );
		$this->assertSame( 'Other Page', WP_PostViews_Display::snippet_text( 'Other Page', 10 ) );
	}

	/**
	 * The shortcode reads the current post.
	 *
	 * @return void
	 */
	public function test_shortcode_uses_the_current_post() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options( array( 'template' => '%VIEW_COUNT% views' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$this->assertSame( '500 views', do_shortcode( '[views]' ) );
		$this->assertSame( '500 views', do_shortcode( '[views id="0"]' ) );
	}

	/**
	 * The shortcode can target another post.
	 *
	 * @return void
	 */
	public function test_shortcode_targets_another_post() {
		$current = $this->make_post( array(), 500 );
		$other   = $this->make_post( array( 'post_title' => 'Other' ), 123456 );
		$this->set_options( array( 'template' => '%VIEW_COUNT% views' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $current );

		$this->assertSame( '123,456 views', do_shortcode( '[views id="' . $other . '"]' ) );
	}

	/**
	 * The shortcode ignores the display gate: it is an explicit request.
	 *
	 * @return void
	 */
	public function test_shortcode_ignores_the_display_gate() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options( array( 'template' => '%VIEW_COUNT% views' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );
		$this->hide_views();

		$this->assertSame( '500 views', do_shortcode( '[views]' ) );
	}

	/**
	 * The get_totalviews() tag sums every row.
	 *
	 * @return void
	 */
	public function test_get_totalviews() {
		$this->make_post( array(), 5 );
		$this->make_post( array(), 500 );
		$this->make_post( array(), 1000 );

		$this->assertSame( 1505, get_totalviews( false ) );
		$this->assertSame(
			'1,505',
			$this->capture(
				function () {
					get_totalviews();
				}
			)
		);
	}

	/**
	 * Entities in a title survive truncation as entities.
	 *
	 * The helper decodes then re-encodes, so a title stored with an
	 * ampersand comes back encoded rather than raw.
	 *
	 * @return void
	 */
	public function test_snippet_text_round_trips_entities() {
		$this->assertSame( 'Tom &amp; Jerry', WP_PostViews_Display::snippet_text( 'Tom &amp; Jerry', 50 ) );
		$this->assertSame( 'Tom &amp; Jerry', WP_PostViews_Display::snippet_text( 'Tom & Jerry', 50 ) );
	}

	/**
	 * An entity counts as the character it represents, not its markup.
	 *
	 * @return void
	 */
	public function test_snippet_text_counts_decoded_characters() {
		// "Tom & Jerry" is 11 characters once decoded, so a limit of 5 cuts it.
		$this->assertSame( 'Tom &amp;...', WP_PostViews_Display::snippet_text( 'Tom &amp; Jerry', 5 ) );
	}

	/**
	 * A zero length limit truncates everything to the ellipsis.
	 *
	 * The listing tags treat 0 as "do not truncate" and never reach this, but
	 * the helper is public and should not warn.
	 *
	 * @return void
	 */
	public function test_snippet_text_with_a_zero_limit() {
		$this->assertSame( '...', WP_PostViews_Display::snippet_text( 'Anything', 0 ) );
	}

	/**
	 * Calling round_number() directly, including the arguments the template
	 * path never varies.
	 *
	 * @return void
	 */
	public function test_round_number_arguments() {
		$this->assertSame( '2.5M', WP_PostViews_Display::round_number( 2500000 ) );
		// A higher minimum keeps the number in full for longer.
		$this->assertSame( '5,000', WP_PostViews_Display::round_number( 5000, 10000 ) );
		// More decimal places.
		$this->assertSame( '1.23K', WP_PostViews_Display::round_number( 1234, 1000, 2 ) );
		// No decimal places.
		$this->assertSame( '1K', WP_PostViews_Display::round_number( 1234, 1000, 0 ) );
	}

	/**
	 * The total is zero, not empty, when nothing has been viewed.
	 *
	 * SUM() over no rows returns NULL, which used to surface as an empty
	 * string in the WP-Stats panel.
	 *
	 * @return void
	 */
	public function test_get_totalviews_with_no_rows() {
		global $wpdb;
		$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key = 'views'" );

		$this->assertSame( 0, get_totalviews( false ) );
		$this->assertSame(
			'0',
			$this->capture(
				function () {
					get_totalviews();
				}
			)
		);
	}

	/**
	 * When the gate hides the count, echoing produces nothing at all.
	 *
	 * @return void
	 */
	public function test_the_views_echoes_nothing_when_hidden() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options( array( 'template' => 'SHOULD NOT APPEAR' ) );
		$this->set_context( array( 'is_home' ), $post_id );
		$this->hide_views();

		$this->assertSame(
			'',
			$this->capture(
				function () {
					the_views();
				}
			)
		);
	}

	/**
	 * The prefix and postfix are inside what the filter receives.
	 *
	 * A theme filtering wp_postviews_the_views sees the finished string, wrappers and all.
	 *
	 * @return void
	 */
	public function test_the_views_filter_sees_prefix_and_postfix() {
		$post_id = $this->make_post( array(), 5 );
		$this->set_options( array( 'template' => 'X' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$seen = null;
		add_filter(
			'wp_postviews_the_views',
			function ( $output ) use ( &$seen ) {
				$seen = $output;
				return $output;
			}
		);

		the_views( false, '[', ']' );

		$this->assertSame( '[X]', $seen );
	}

	/**
	 * An empty template renders as an empty string rather than warning.
	 *
	 * @return void
	 */
	public function test_an_empty_template_renders_empty() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options( array( 'template' => '' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$this->assertSame( '', the_views( false ) );
	}

	/**
	 * A template with no tokens is passed through untouched.
	 *
	 * @return void
	 */
	public function test_a_template_without_tokens_is_literal() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options( array( 'template' => 'Hits!' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$this->assertSame( 'Hits!', the_views( false ) );
	}

	/**
	 * The shortcode on a post ID that does not exist renders zero.
	 *
	 * @return void
	 */
	public function test_shortcode_with_a_nonexistent_id() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options( array( 'template' => '%VIEW_COUNT% views' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$this->assertSame( '0 views', do_shortcode( '[views id="999999"]' ) );
	}

	/**
	 * A non-numeric shortcode id degrades to the current post.
	 *
	 * @return void
	 */
	public function test_shortcode_with_a_non_numeric_id() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options( array( 'template' => '%VIEW_COUNT% views' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$this->assertSame( '500 views', do_shortcode( '[views id="abc"]' ) );
	}
}
