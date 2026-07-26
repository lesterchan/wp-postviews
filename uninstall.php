<?php
/*
 * Uninstall plugin
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit();
}

if ( is_multisite() ) {
	// 'number' => 0 lifts WP_Site_Query's default cap of 100. Without it a
	// network with more than a hundred sites silently keeps its options and
	// its views meta on every site after the hundredth, and uninstall still
	// reports success. wp_get_sites(), which the old ternary fell back to,
	// was removed in WP 5.1 and fatals outright.
	$site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		postviews_uninstall_site();
		restore_current_blog();
	}
} else {
	postviews_uninstall_site();
}

function postviews_uninstall_site() {
	global $wpdb;

	$option_names = array( 'views_options', 'widget_views_most_viewed', 'widget_views' );

	foreach ( $option_names as $option_name ) {
		delete_option( $option_name );
	}

	$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => 'views' ) );
}
