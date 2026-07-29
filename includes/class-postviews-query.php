<?php
/**
 * The most/least viewed listings.
 *
 * Up to 1.78.1 this was six near-identical copies of the same forty lines -
 * most/least, unscoped/by category/by tag - which is why a fix to the token
 * substitution had to be made six times and twice got made five times. They
 * are one query builder and one renderer here; the six template tags remain as
 * thin wrappers in template-tags.php because themes call them by name.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds and renders the view count listings.
 */
class PostViews_Query {

	/**
	 * Run a listing and render it.
	 *
	 * @param array $args {
	 *     Listing arguments.
	 *
	 *     @type string|array $mode     Post type(s). '' or 'both' means any type.
	 *     @type int          $limit    Maximum number of posts.
	 *     @type int          $chars    Truncate titles to this many characters. 0 disables.
	 *     @type string       $order    'asc' for least viewed, 'desc' for most viewed.
	 *     @type int|array    $category Category ID(s) to scope to, if any.
	 *     @type int|array    $tag      Tag ID(s) to scope to, if any.
	 * }
	 * @return string
	 */
	public static function render( $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'mode'     => '',
				'limit'    => 10,
				'chars'    => 0,
				'order'    => 'desc',
				'category' => null,
				'tag'      => null,
			)
		);

		$query_args = array(
			'post_type'      => ( empty( $args['mode'] ) || 'both' === $args['mode'] ) ? 'any' : $args['mode'],
			'posts_per_page' => (int) $args['limit'],
			'orderby'        => 'meta_value_num',
			'order'          => $args['order'],
			// A meta_key filter is what excludes posts that have never been
			// viewed. Removing it would list them all at zero.
			'meta_key'       => 'views', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- The meta key is what excludes never-viewed posts; without it the listing returns everything at zero.
		);

		if ( null !== $args['category'] ) {
			$query_args['category__in'] = (array) $args['category'];
		}
		if ( null !== $args['tag'] ) {
			$query_args['tag__in'] = (array) $args['tag'];
		}

		$query  = new WP_Query( $query_args );
		$output = '';

		if ( ! $query->have_posts() ) {
			return '<li>' . __( 'N/A', 'wp-postviews' ) . '</li>' . "\n";
		}

		while ( $query->have_posts() ) {
			$query->the_post();
			$output .= self::render_item( (int) $args['chars'] );
		}

		wp_reset_postdata();

		return $output;
	}

	/**
	 * Substitute every %TOKEN% for the post currently in the loop.
	 *
	 * @param int $chars Truncate the title to this many characters. 0 disables.
	 * @return string
	 */
	protected static function render_item( $chars ) {
		$post_views = get_post_meta( get_the_ID(), 'views', true );

		$post_title = get_the_title();
		if ( $chars > 0 ) {
			$post_title = PostViews_Display::snippet_text( $post_title, $chars );
		}

		// The first category, or 0 for a post type that has none.
		$categories       = get_the_category();
		$post_category_id = empty( $categories ) ? 0 : $categories[0]->term_id;

		$tokens = array(
			'%VIEW_COUNT%'         => number_format_i18n( $post_views ),
			'%VIEW_COUNT_ROUNDED%' => PostViews_Display::round_number( $post_views ),
			'%POST_TITLE%'         => $post_title,
			'%POST_EXCERPT%'       => get_the_excerpt(),
			'%POST_CONTENT%'       => get_the_content(),
			'%POST_URL%'           => get_permalink(),
			'%POST_DATE%'          => get_the_time( get_option( 'date_format' ) ),
			'%POST_TIME%'          => get_the_time( get_option( 'time_format' ) ),
			'%POST_THUMBNAIL%'     => (string) get_the_post_thumbnail( null, 'thumbnail' ),
			// Returns false when the post has no thumbnail.
			'%POST_THUMBNAIL_URL%' => (string) get_the_post_thumbnail_url( null, 'thumbnail' ),
			'%POST_CATEGORY_ID%'   => $post_category_id,
			'%POST_AUTHOR%'        => get_the_author(),
		);

		return str_replace(
			array_keys( $tokens ),
			array_values( $tokens ),
			(string) PostViews_Options::get( 'most_viewed_template', '' )
		);
	}

	/**
	 * Render a listing, echoing or returning it.
	 *
	 * Every public listing tag ends here, so the echo/return convention they
	 * have always had is implemented once.
	 *
	 * @param array $args    Arguments for self::render().
	 * @param bool  $display Echo when true, return when false.
	 * @return string|void
	 */
	public static function output( $args, $display ) {
		$output = self::render( $args );

		if ( ! $display ) {
			return $output;
		}

		// The template is HTML by design and is run through wp_kses_post() when
		// it is saved, so it is echoed as-is.
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Stored template, filtered through wp_kses_post() by PostViews_Settings on save.
	}
}
