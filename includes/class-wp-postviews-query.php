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
class WP_PostViews_Query {

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
		// Cast, the way WP_PostViews_Display does. The meta key is unprefixed and
		// therefore unprotected, so anyone who can edit a post can set it to a
		// string through the Custom Fields box -- and both tokens below are built
		// eagerly whether or not the template uses them, so on PHP 8 a single
		// such post turns every listing that includes it into an uncaught
		// TypeError from number_format_i18n() or from the division.
		$post_views = (int) get_post_meta( get_the_ID(), 'views', true );

		/*
		 * Escaped here rather than left to snippet_text(). Until 2.0.0 the only
		 * escaping a title received was the htmlentities() inside that call,
		 * which runs when $chars > 0 -- so escaping was a side effect of
		 * truncation, and truncation is off by default. A post titled with a
		 * script tag went to the page as one, including into the title="..."
		 * attribute the stock template puts it in.
		 *
		 * The order matters: truncate first, then escape, or the entities count
		 * towards the character limit and a title can be cut mid-entity.
		 */
		$post_title = get_the_title();
		if ( $chars > 0 ) {
			$post_title = WP_PostViews_Display::snippet_text( $post_title, $chars );
		} else {
			$post_title = esc_html( $post_title );
		}

		// The first category, or 0 for a post type that has none.
		$categories       = get_the_category();
		$post_category_id = empty( $categories ) ? 0 : $categories[0]->term_id;

		/*
		 * Three of these are markup on purpose -- the content, the excerpt and
		 * the thumbnail's <img> -- and escaping them would print the tags. The
		 * rest are values a person typed, so they are escaped here, at the one
		 * place they enter the template, rather than left to whoever writes the
		 * template to remember.
		 */
		$tokens = array(
			'%VIEW_COUNT%'         => number_format_i18n( $post_views ),
			'%VIEW_COUNT_ROUNDED%' => WP_PostViews_Display::round_number( $post_views ),
			'%POST_TITLE%'         => $post_title,
			'%POST_EXCERPT%'       => get_the_excerpt(),
			'%POST_CONTENT%'       => get_the_content(),
			'%POST_URL%'           => esc_url( get_permalink() ),
			'%POST_DATE%'          => get_the_time( get_option( 'date_format' ) ),
			'%POST_TIME%'          => get_the_time( get_option( 'time_format' ) ),
			'%POST_THUMBNAIL%'     => (string) get_the_post_thumbnail( null, 'thumbnail' ),
			// Returns false when the post has no thumbnail.
			'%POST_THUMBNAIL_URL%' => esc_url( (string) get_the_post_thumbnail_url( null, 'thumbnail' ) ),
			'%POST_CATEGORY_ID%'   => $post_category_id,
			// A display name is user supplied on any site that lets people register.
			'%POST_AUTHOR%'        => esc_html( get_the_author() ),
		);

		return str_replace(
			array_keys( $tokens ),
			array_values( $tokens ),
			(string) WP_PostViews_Options::get( 'most_viewed_template', '' )
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
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Stored template, filtered through wp_kses_post() by WP_PostViews_Settings on save.
	}
}
