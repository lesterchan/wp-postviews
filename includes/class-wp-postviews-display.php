<?php
/**
 * Rendering the view count for a single post: the_views(), the [views]
 * shortcode, the wp_postviews_should_display gate, and the two number helpers
 * the templates use.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Single post view count output.
 */
class WP_PostViews_Display {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_shortcode( 'views', array( __CLASS__, 'shortcode' ) );
	}

	/**
	 * Render the view count for the current post.
	 *
	 * @param bool   $display Echo when true, return when false.
	 * @param string $prefix  Prepended to the rendered template.
	 * @param string $postfix Appended to the rendered template.
	 * @param bool   $always  Ignore the display gate. Used by the admin column.
	 * @return string|void
	 */
	public static function the_views( $display = true, $prefix = '', $postfix = '', $always = false ) {
		if ( ! $always && ! self::should_be_displayed() ) {
			return $display ? null : '';
		}

		$output = $prefix . self::render_count_template( get_the_ID() ) . $postfix;

		/**
		 * Filters the rendered view count for a single post.
		 *
		 * Called the_views up to 1.78.1, which was generic enough to belong to
		 * nobody.
		 *
		 * @since 2.0.0
		 *
		 * @param string $output The rendered template, prefix and postfix included.
		 */
		$output = apply_filters( 'wp_postviews_the_views', $output );

		if ( ! $display ) {
			return $output;
		}

		// The template is HTML by design and is run through wp_kses_post() when
		// it is saved, so it is echoed as-is.
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Stored template, filtered through wp_kses_post() by WP_PostViews_Settings on save.
	}

	/**
	 * The [views] shortcode.
	 *
	 * Deliberately ignores the wp_postviews_should_display gate: dropping the
	 * shortcode into a post is an explicit request for the count, not a themed
	 * sidebar the gate is there to govern.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$attributes = shortcode_atts( array( 'id' => 0 ), $atts );

		return self::render_views( $attributes['id'] );
	}

	/**
	 * Render one post's view count.
	 *
	 * The renderer the `[views]` shortcode and the `wp-postviews/views` block
	 * share. Neither calls the other: the shortcode parses shortcode
	 * attributes, the block gets a typed `id` from block.json, and both then
	 * arrive here with an id and nothing else. Keeping that one step in one
	 * place is what stops the two entry points drifting into rendering
	 * different markup for the same post.
	 *
	 * Zero means the post being rendered, which is what an attributeless
	 * `[views]` has always meant, so it has to mean the same for an
	 * attributeless block or the two disagree about their own default.
	 *
	 * **This reads a count and never records one.** Counting is
	 * WP_PostViews_Counter's job and it hangs off `wp_head` or a POST to
	 * `admin-ajax.php`; a block previewing itself through
	 * /wp/v2/block-renderer/ reaches neither, so an author arranging a post
	 * cannot inflate its figures. Do not add an increment here for the benefit
	 * of some future caller: it would count once per shortcode on the page as
	 * well.
	 *
	 * Like the shortcode, it ignores the wp_postviews_should_display gate.
	 *
	 * @param int $id Post id. Zero means the post currently being rendered.
	 * @return string
	 */
	public static function render_views( $id ) {
		$id = (int) $id;

		if ( 0 === $id ) {
			$id = get_the_ID();
		}

		/** This filter is documented in includes/class-wp-postviews-display.php */
		return apply_filters( 'wp_postviews_the_views', self::render_count_template( $id ) );
	}

	/**
	 * Substitute the count tokens into the single post template.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function render_count_template( $post_id ) {
		$post_views = (int) get_post_meta( $post_id, 'views', true );

		return str_replace(
			array( '%VIEW_COUNT%', '%VIEW_COUNT_ROUNDED%' ),
			array( number_format_i18n( $post_views ), self::round_number( $post_views ) ),
			(string) WP_PostViews_Options::get( 'template', '' )
		);
	}

	/**
	 * Whether a count should be shown on the page currently being rendered.
	 *
	 * Up to 2.0.0 this read a six-row matrix - home, single, page, archive,
	 * search, other, each of them "everyone / registered users only / never" -
	 * which is a settings screen full of rows restating what a theme already
	 * decided by where it called the_views(). Anything the matrix could express
	 * a filter expresses in one line, and anything it could not - a post type, a
	 * category, a specific template - it never could.
	 *
	 * The method stays. The 2.0.0 Upgrade Notice names it as the replacement for
	 * the old global should_views_be_displayed(), and a release does not get to
	 * break a promise it makes in the same release.
	 *
	 * Originally contributed as should_views_be_displayed() by David Potter.
	 *
	 * @return bool
	 */
	public static function should_be_displayed() {
		/**
		 * Filters whether the view count is shown on the page being rendered.
		 *
		 * Replaces the display_home, display_single, display_page,
		 * display_archive, display_search and display_other settings. Return
		 * false to hide the count; the conditional tags are all available, so
		 * `is_archive()` here does what "Don't display on archive pages" did.
		 *
		 * The [views] shortcode and the admin Views column do not consult this:
		 * both are explicit requests for the number.
		 *
		 * @since 2.0.0
		 *
		 * @param bool $should_be_displayed Whether to show the count.
		 */
		return (bool) apply_filters( 'wp_postviews_should_display', true );
	}

	/**
	 * Abbreviate a number to K, M or B.
	 *
	 * The unit is chosen from the *rounded* value, not the raw one. Choosing it
	 * from the raw number lets rounding tip the result out of range: 999,950
	 * over a thousand rounds to 1000.0, which used to print as "1000K" instead
	 * of "1M".
	 *
	 * @param int $number    The number to abbreviate.
	 * @param int $min_value Below this the number is returned in full.
	 * @param int $decimal   Decimal places to keep.
	 * @return string
	 */
	public static function round_number( $number, $min_value = 1000, $decimal = 1 ) {
		if ( $number < $min_value ) {
			return number_format_i18n( $number );
		}

		$units = array(
			1000       => 'K',
			1000000    => 'M',
			1000000000 => 'B',
		);

		foreach ( $units as $divisor => $suffix ) {
			$rounded = round( $number / $divisor, $decimal );
			if ( $rounded < 1000 ) {
				return $rounded . $suffix;
			}
		}

		return round( $number / 1000000000, $decimal ) . 'B';
	}

	/**
	 * Truncate a title to a character count and HTML-encode it.
	 *
	 * The multibyte branch used to be gated on MB_OVERLOAD_STRING, which PHP
	 * 8.0 removed along with mbstring function overloading. Every title
	 * therefore went through substr(), which cuts on bytes: a CJK title chopped
	 * mid-character became invalid UTF-8 and htmlentities() returned an empty
	 * string, so the title vanished entirely.
	 *
	 * @param string $text   The title.
	 * @param int    $length Maximum length in characters. 0 disables truncation.
	 * @return string
	 */
	public static function snippet_text( $text, $length = 0 ) {
		$charset = get_option( 'blog_charset' );
		$text    = html_entity_decode( (string) $text, ENT_QUOTES, $charset );

		if ( function_exists( 'mb_strlen' ) && function_exists( 'mb_substr' ) ) {
			$too_long = mb_strlen( $text, $charset ) > $length;
			$trimmed  = $too_long ? mb_substr( $text, 0, $length, $charset ) : $text;
		} else {
			$too_long = strlen( $text ) > $length;
			$trimmed  = $too_long ? substr( $text, 0, $length ) : $text;
		}

		return htmlentities( $trimmed, ENT_COMPAT, $charset ) . ( $too_long ? '...' : '' );
	}

	/**
	 * Total views across every post.
	 *
	 * @param bool $display Echo when true, return when false.
	 * @return int|void
	 */
	public static function get_totalviews( $display = true ) {
		global $wpdb;

		$total_views = (int) $wpdb->get_var( "SELECT SUM(meta_value+0) FROM $wpdb->postmeta WHERE meta_key = 'views'" );

		if ( ! $display ) {
			return $total_views;
		}

		echo esc_html( number_format_i18n( $total_views ) );
	}
}
