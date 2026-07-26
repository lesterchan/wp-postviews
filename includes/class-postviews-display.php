<?php
/**
 * Rendering the view count for a single post: the_views(), the [views]
 * shortcode, the display matrix that decides who sees a count on which kind of
 * page, and the two number helpers the templates use.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Single post view count output.
 */
class PostViews_Display {

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
	 * @param bool   $always  Ignore the display matrix. Used by the admin column.
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
		 * @param string $output The rendered template.
		 */
		$output = apply_filters( 'the_views', $output );

		if ( ! $display ) {
			return $output;
		}

		// The template is HTML by design and is run through wp_kses_post() when
		// it is saved, so it is echoed as-is.
		echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * The [views] shortcode.
	 *
	 * Deliberately ignores the display matrix: dropping the shortcode into a
	 * post is an explicit request for the count, not a themed sidebar the
	 * matrix is there to govern.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$attributes = shortcode_atts( array( 'id' => 0 ), $atts );
		$id         = (int) $attributes['id'];

		if ( 0 === $id ) {
			$id = get_the_ID();
		}

		/** This filter is documented in includes/class-postviews-display.php */
		return apply_filters( 'the_views', self::render_count_template( $id ) );
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
			(string) PostViews_Options::get( 'template', '' )
		);
	}

	/**
	 * Whether a count should be shown on the page currently being rendered.
	 *
	 * Each context has its own setting: 0 shows it to everyone, 1 only to
	 * logged in users, 2 to nobody.
	 *
	 * Originally contributed as should_views_be_displayed() by David Potter.
	 *
	 * @return bool
	 */
	public static function should_be_displayed() {
		if ( is_home() ) {
			$key = 'display_home';
		} elseif ( is_single() ) {
			$key = 'display_single';
		} elseif ( is_page() ) {
			$key = 'display_page';
		} elseif ( is_archive() ) {
			$key = 'display_archive';
		} elseif ( is_search() ) {
			$key = 'display_search';
		} else {
			$key = 'display_other';
		}

		$display_option = PostViews_Options::get_int( $key );

		return 0 === $display_option || ( 1 === $display_option && is_user_logged_in() );
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

		$total_views = (int) $wpdb->get_var( "SELECT SUM(meta_value+0) FROM $wpdb->postmeta WHERE meta_key = 'views'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $display ) {
			return $total_views;
		}

		echo esc_html( number_format_i18n( $total_views ) );
	}
}
