<?php
/**
 * The WP-Stats integration.
 *
 * WP-Stats collects the sections it renders by firing one filter, and each
 * contributing plugin answers with an entry keyed by its own slug. Nothing here
 * reads WP-Stats' option row and WP-Stats reads none of ours, so a section can
 * never be rendered for a plugin that is not installed, and switching the toggle
 * off on Settings > PostViews cannot affect any other plugin's section.
 *
 * Up to 1.78.1 the two plugins met in a bare stats_display row that seven
 * plugins wrote into at once, and WP-Stats had to know the names of all three
 * panels this plugin owned. Those three collapse into one question - does the
 * stats page show a views section at all - because WP-Stats collects whole
 * sections now rather than individual panels.
 *
 * Loaded unconditionally. With WP-Stats absent the filter is never fired and
 * this class simply does nothing, which is why there is no class_exists()
 * probing between the two plugins.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Contributes the WP-PostViews section to the WP-Stats page.
 */
class WP_PostViews_WPStats {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'wp_stats_sections', array( __CLASS__, 'register_section' ) );
	}

	/**
	 * Add the WP-PostViews section, unless the site has opted out.
	 *
	 * @param array $sections Sections keyed by plugin slug with underscores.
	 * @return array
	 */
	public static function register_section( $sections ) {
		$sections = (array) $sections;

		// Read through the option accessor rather than get_option() so an
		// install whose row has not been written yet gets the shipped default,
		// which is on. A raw read would see nothing and call that an opt-out.
		if ( ! WP_PostViews_Options::get( 'stats_display' ) ) {
			// Opted out: contribute nothing, rather than an entry with an empty
			// body that WP-Stats would still draw a heading for.
			return $sections;
		}

		$sections['wp_postviews'] = array(
			'title'    => __( 'Views', 'wp-postviews' ),
			'priority' => 10,
			'render'   => array( __CLASS__, 'render' ),
		);

		return $sections;
	}

	/**
	 * Echo the body of the WP-PostViews section.
	 *
	 * The section's own heading is WP-Stats' to print: its listener on
	 * wp_stats_section_wp_postviews echoes the title and then calls this, which
	 * is what lets a theme take one plugin's block over without touching the
	 * others. Echoing rather than returning matters too - WP-Stats assembles the
	 * page inside an output buffer, so a returned string would be dropped
	 * without a word.
	 *
	 * @return void
	 */
	public static function render() {
		$limit = self::most_limit();
		$total = WP_PostViews_Display::get_totalviews( false );

		echo '<ul>' . "\n";
		printf(
			'<li>%s</li>' . "\n",
			wp_kses_post(
				sprintf(
					/* translators: %s: The number of views generated. */
					_n( '<strong>%s</strong> view was generated.', '<strong>%s</strong> views were generated.', $total, 'wp-postviews' ),
					esc_html( number_format_i18n( $total ) )
				)
			)
		);
		echo '</ul>' . "\n";

		self::render_list(
			/* translators: %s: number of posts. */
			sprintf( _n( '%s Most Viewed Post', '%s Most Viewed Posts', $limit, 'wp-postviews' ), number_format_i18n( $limit ) ),
			'post',
			$limit
		);

		self::render_list(
			/* translators: %s: number of pages. */
			sprintf( _n( '%s Most Viewed Page', '%s Most Viewed Pages', $limit, 'wp-postviews' ), number_format_i18n( $limit ) ),
			'page',
			$limit
		);
	}

	/**
	 * One titled listing inside the section.
	 *
	 * @param string $heading   Heading for the listing.
	 * @param string $post_type Post type to list.
	 * @param int    $limit     How many entries to list.
	 * @return void
	 */
	private static function render_list( $heading, $post_type, $limit ) {
		printf( '<p><strong>%s</strong></p>' . "\n", esc_html( $heading ) );
		echo '<ul>' . "\n";
		WP_PostViews_Query::output(
			array(
				'mode'  => $post_type,
				'limit' => $limit,
				'order' => 'desc',
			),
			true
		);
		echo '</ul>' . "\n";
	}

	/**
	 * How many entries the "most viewed" listings show.
	 *
	 * @return int
	 */
	public static function most_limit() {
		return max( 1, (int) WP_PostViews_Options::get( 'stats_most_limit' ) );
	}
}
