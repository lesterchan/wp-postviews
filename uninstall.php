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

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

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
	// 'number' => 0 lifts WP_Site_Query's default cap of 100. Without it a
	// network with more than a hundred sites silently keeps its options and
	// its views meta on every site after the hundredth, and uninstall still
	// reports success. wp_get_sites(), which the pre-2.0.0 ternary fell back
	// to, has been deprecated since WP 4.6 and is capped at 100 the same way.
	//
	// restore_current_blog() belongs inside the loop: switch_to_blog() pushes
	// onto a stack, so restoring once at the end unwinds it by exactly one.
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
