<?php
/**
 * Output for a single post: the_views(), the display matrix, the [views]
 * shortcode, and the two number helpers the templates rely on.
 *
 * @package WP-PostViews
 */

/**
 * Single post output.
 */
class Test_PostViews_Display extends PostViews_TestCase {

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
	 * The the_views filter can rewrite the output.
	 *
	 * @return void
	 */
	public function test_the_views_filter_applies() {
		$post_id = $this->make_post( array(), 7 );
		$this->set_options( array( 'template' => '%VIEW_COUNT%' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		add_filter(
			'the_views',
			function ( $output ) {
				return '<b>' . $output . '</b>';
			}
		);

		$this->assertSame( '<b>7</b>', the_views( false ) );
	}

	/**
	 * Every context and visibility combination.
	 *
	 * @dataProvider data_display_matrix
	 *
	 * @param string $key       Option key under test.
	 * @param array  $flags     Query flags describing the context.
	 * @param int    $mode      0 everyone, 1 logged in only, 2 nobody.
	 * @param bool   $logged_in Whether a user is signed in.
	 * @param string $expected  Expected output.
	 * @return void
	 */
	public function test_display_matrix( $key, $flags, $mode, $logged_in, $expected ) {
		$post_id = $this->make_post( array(), 500 );

		wp_set_current_user( $logged_in ? self::factory()->user->create( array( 'role' => 'editor' ) ) : 0 );

		$this->set_options(
			array_merge(
				array_fill_keys( $this->display_keys, 2 ),
				array(
					$key       => $mode,
					'template' => 'X',
				)
			)
		);
		$this->set_context( $flags, $post_id );

		$this->assertSame( $expected, the_views( false ) );
	}

	/**
	 * Context, mode and login state, with the expected visibility.
	 *
	 * @return array
	 */
	public function data_display_matrix() {
		$contexts = array(
			'display_home'    => array( 'is_home' ),
			'display_single'  => array( 'is_single', 'is_singular' ),
			'display_page'    => array( 'is_page', 'is_singular' ),
			'display_archive' => array( 'is_archive' ),
			'display_search'  => array( 'is_search' ),
			'display_other'   => array(),
		);

		$cases = array();
		foreach ( $contexts as $key => $flags ) {
			foreach ( array( 0, 1, 2 ) as $mode ) {
				foreach ( array( false, true ) as $logged_in ) {
					$visible = ( 0 === $mode || ( 1 === $mode && $logged_in ) );

					$cases[ sprintf( '%s mode %d %s', $key, $mode, $logged_in ? 'logged in' : 'anonymous' ) ] = array(
						$key,
						$flags,
						$mode,
						$logged_in,
						$visible ? 'X' : '',
					);
				}
			}
		}

		return $cases;
	}

	/**
	 * $always bypasses the matrix. The admin column depends on it, and it
	 * regressed once before, in 1.65.
	 *
	 * @return void
	 */
	public function test_always_bypasses_the_display_matrix() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options(
			array_merge(
				array_fill_keys( $this->display_keys, 2 ),
				array( 'template' => 'X' )
			)
		);
		$this->set_context( array( 'is_home' ), $post_id );

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
		$this->assertSame( '日本語のタ...', PostViews_Display::snippet_text( '日本語のタイトルです', 5 ) );
	}

	/**
	 * ASCII truncation, and the cases that must not be truncated.
	 *
	 * @return void
	 */
	public function test_snippet_text_ascii() {
		$this->assertSame( 'Other...', PostViews_Display::snippet_text( 'Other Page', 5 ) );
		$this->assertSame( 'Other Page', PostViews_Display::snippet_text( 'Other Page', 50 ) );
		$this->assertSame( 'Other Page', PostViews_Display::snippet_text( 'Other Page', 10 ) );
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
	 * The shortcode ignores the display matrix: it is an explicit request.
	 *
	 * @return void
	 */
	public function test_shortcode_ignores_the_display_matrix() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options(
			array_merge(
				array_fill_keys( $this->display_keys, 2 ),
				array( 'template' => '%VIEW_COUNT% views' )
			)
		);
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

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
		$this->assertSame( 'Tom &amp; Jerry', PostViews_Display::snippet_text( 'Tom &amp; Jerry', 50 ) );
		$this->assertSame( 'Tom &amp; Jerry', PostViews_Display::snippet_text( 'Tom & Jerry', 50 ) );
	}

	/**
	 * An entity counts as the character it represents, not its markup.
	 *
	 * @return void
	 */
	public function test_snippet_text_counts_decoded_characters() {
		// "Tom & Jerry" is 11 characters once decoded, so a limit of 5 cuts it.
		$this->assertSame( 'Tom &amp;...', PostViews_Display::snippet_text( 'Tom &amp; Jerry', 5 ) );
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
		$this->assertSame( '...', PostViews_Display::snippet_text( 'Anything', 0 ) );
	}

	/**
	 * Calling round_number() directly, including the arguments the template
	 * path never varies.
	 *
	 * @return void
	 */
	public function test_round_number_arguments() {
		$this->assertSame( '2.5M', PostViews_Display::round_number( 2500000 ) );
		// A higher minimum keeps the number in full for longer.
		$this->assertSame( '5,000', PostViews_Display::round_number( 5000, 10000 ) );
		// More decimal places.
		$this->assertSame( '1.23K', PostViews_Display::round_number( 1234, 1000, 2 ) );
		// No decimal places.
		$this->assertSame( '1K', PostViews_Display::round_number( 1234, 1000, 0 ) );
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
	 * When the matrix hides the count, echoing produces nothing at all.
	 *
	 * @return void
	 */
	public function test_the_views_echoes_nothing_when_hidden() {
		$post_id = $this->make_post( array(), 500 );
		$this->set_options(
			array_merge(
				array_fill_keys( $this->display_keys, 2 ),
				array( 'template' => 'SHOULD NOT APPEAR' )
			)
		);
		$this->set_context( array( 'is_home' ), $post_id );

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
	 * A theme filtering the_views sees the finished string, wrappers and all.
	 *
	 * @return void
	 */
	public function test_the_views_filter_sees_prefix_and_postfix() {
		$post_id = $this->make_post( array(), 5 );
		$this->set_options( array( 'template' => 'X' ) );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$seen = null;
		add_filter(
			'the_views',
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
