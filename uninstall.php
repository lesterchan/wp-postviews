<?php
/**
 * Uninstall WP-PostViews.
 *
 * Removes the settings rows and every `views` post meta row. The posts
 * themselves, and any other plugin's meta, are left alone.
 *
 * The worker is declared before it is called, and behind a function_exists()
 * guard, so that requiring this file more than once in a process re-runs the
 * dispatch rather than fataling on a redeclare. WordPress only ever requires
 * it once; the tests require it repeatedly, and they should be exercising this
 * file rather than a copy of it.
 *
 * @package WP-PostViews
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

// WordPress loads this file and nothing else of the plugin when the plugin is
// deleted, so the option list has to be pulled in explicitly. Reading it from
// the class the migration reads it from is what stops the two drifting apart.
require_once __DIR__ . '/includes/class-wp-postviews-options.php';

if ( ! function_exists( 'wp_postviews_uninstall_site' ) ) {
	/**
	 * Delete this plugin's data from the current site.
	 *
	 * Named rather than called uninstall(), which is what it was up to 1.78.1 -
	 * a global function on that name will collide with another plugin eventually.
	 *
	 * @return void
	 */
	function wp_postviews_uninstall_site() {
		foreach ( WP_PostViews_Options::all_option_names() as $option_name ) {
			delete_option( $option_name );
		}

		// The core helper rather than a DELETE against $wpdb->postmeta: it does
		// the same work and invalidates the post meta cache while it is at it.
		delete_post_meta_by_key( 'views' );
	}
}

if ( is_multisite() ) {
	// 'number' => 0 lifts WP_Site_Query's default cap of 100, which would
	// otherwise skip every site past the hundredth while reporting success.
	$wp_postviews_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $wp_postviews_site_ids as $wp_postviews_site_id ) {
		switch_to_blog( (int) $wp_postviews_site_id );
		wp_postviews_uninstall_site();
		restore_current_blog();
	}

	unset( $wp_postviews_site_ids, $wp_postviews_site_id );
} else {
	wp_postviews_uninstall_site();
}
