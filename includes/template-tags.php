<?php
/**
 * Public template tags.
 *
 * These are the plugin's API. Every one of them is documented in the readme,
 * and themes call them guarded by function_exists(), so their names and
 * signatures must not change. They are thin wrappers over the classes.
 *
 * Three helpers that used to be global functions here are not: 1.78.1 also
 * exported should_views_be_displayed(), postviews_round_number() and
 * snippet_text(), none of which were documented and the last of which squatted
 * on a very generic name. They are WP_PostViews_Display::should_be_displayed(),
 * ::round_number() and ::snippet_text() as of 2.0.0.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'the_views' ) ) {
	/**
	 * Display the view count for the current post.
	 *
	 * @param bool   $display Echo when true, return when false.
	 * @param string $prefix  Prepended to the rendered template.
	 * @param string $postfix Appended to the rendered template.
	 * @param bool   $always  Ignore the display options.
	 * @return string|void
	 */
	function the_views( $display = true, $prefix = '', $postfix = '', $always = false ) {
		return WP_PostViews_Display::the_views( $display, $prefix, $postfix, $always );
	}
}

if ( ! function_exists( 'get_totalviews' ) ) {
	/**
	 * Display the total number of views across every post.
	 *
	 * @param bool $display Echo when true, return when false.
	 * @return int|void
	 */
	function get_totalviews( $display = true ) {
		return WP_PostViews_Display::get_totalviews( $display );
	}
}

if ( ! function_exists( 'get_most_viewed' ) ) {
	/**
	 * List the most viewed posts.
	 *
	 * @param string|array $mode    Post type(s). '' or 'both' for every type.
	 * @param int          $limit   Maximum number of posts.
	 * @param int          $chars   Truncate titles to this length. 0 disables.
	 * @param bool         $display Echo when true, return when false.
	 * @return string|void
	 */
	function get_most_viewed( $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostViews_Query::output(
			array(
				'mode'  => $mode,
				'limit' => $limit,
				'chars' => $chars,
				'order' => 'desc',
			),
			$display
		);
	}
}

if ( ! function_exists( 'get_least_viewed' ) ) {
	/**
	 * List the least viewed posts.
	 *
	 * @param string|array $mode    Post type(s). '' or 'both' for every type.
	 * @param int          $limit   Maximum number of posts.
	 * @param int          $chars   Truncate titles to this length. 0 disables.
	 * @param bool         $display Echo when true, return when false.
	 * @return string|void
	 */
	function get_least_viewed( $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostViews_Query::output(
			array(
				'mode'  => $mode,
				'limit' => $limit,
				'chars' => $chars,
				'order' => 'asc',
			),
			$display
		);
	}
}

if ( ! function_exists( 'get_most_viewed_category' ) ) {
	/**
	 * List the most viewed posts in one or more categories.
	 *
	 * @param int|array    $category_id Category ID(s).
	 * @param string|array $mode        Post type(s). '' or 'both' for every type.
	 * @param int          $limit       Maximum number of posts.
	 * @param int          $chars       Truncate titles to this length. 0 disables.
	 * @param bool         $display     Echo when true, return when false.
	 * @return string|void
	 */
	function get_most_viewed_category( $category_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostViews_Query::output(
			array(
				'mode'     => $mode,
				'limit'    => $limit,
				'chars'    => $chars,
				'order'    => 'desc',
				'category' => $category_id,
			),
			$display
		);
	}
}

if ( ! function_exists( 'get_least_viewed_category' ) ) {
	/**
	 * List the least viewed posts in one or more categories.
	 *
	 * @param int|array    $category_id Category ID(s).
	 * @param string|array $mode        Post type(s). '' or 'both' for every type.
	 * @param int          $limit       Maximum number of posts.
	 * @param int          $chars       Truncate titles to this length. 0 disables.
	 * @param bool         $display     Echo when true, return when false.
	 * @return string|void
	 */
	function get_least_viewed_category( $category_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostViews_Query::output(
			array(
				'mode'     => $mode,
				'limit'    => $limit,
				'chars'    => $chars,
				'order'    => 'asc',
				'category' => $category_id,
			),
			$display
		);
	}
}

if ( ! function_exists( 'get_most_viewed_tag' ) ) {
	/**
	 * List the most viewed posts carrying one or more tags.
	 *
	 * @param int|array    $tag_id  Tag ID(s).
	 * @param string|array $mode    Post type(s). '' or 'both' for every type.
	 * @param int          $limit   Maximum number of posts.
	 * @param int          $chars   Truncate titles to this length. 0 disables.
	 * @param bool         $display Echo when true, return when false.
	 * @return string|void
	 */
	function get_most_viewed_tag( $tag_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostViews_Query::output(
			array(
				'mode'  => $mode,
				'limit' => $limit,
				'chars' => $chars,
				'order' => 'desc',
				'tag'   => $tag_id,
			),
			$display
		);
	}
}

if ( ! function_exists( 'get_least_viewed_tag' ) ) {
	/**
	 * List the least viewed posts carrying one or more tags.
	 *
	 * @param int|array    $tag_id  Tag ID(s).
	 * @param string|array $mode    Post type(s). '' or 'both' for every type.
	 * @param int          $limit   Maximum number of posts.
	 * @param int          $chars   Truncate titles to this length. 0 disables.
	 * @param bool         $display Echo when true, return when false.
	 * @return string|void
	 */
	function get_least_viewed_tag( $tag_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true ) {
		return WP_PostViews_Query::output(
			array(
				'mode'  => $mode,
				'limit' => $limit,
				'chars' => $chars,
				'order' => 'asc',
				'tag'   => $tag_id,
			),
			$display
		);
	}
}
