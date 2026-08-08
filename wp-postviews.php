<?php
/**
 * Plugin Name: WP-PostViews
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Enables you to display how many times a post/page had been viewed.
 * Version: 2.0.0
 * Requires at least: 6.8
 * Requires PHP: 8.2
 * Author: Lester 'GaMerZ' Chan
 * Author URI: https://lesterchan.net
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wp-postviews
 * Domain Path: /languages
 *
 * @package WP-PostViews
 */

/*
	Copyright 2026  Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-PostViews version. Compared against the 'plugin' marker in wp_postviews_version.
 */
define( 'WP_POSTVIEWS_VERSION', '2.0.0' );

/**
 * Schema counter. Compared against the 'db' marker in wp_postviews_version.
 *
 * Starts at 1: the plugin owns no table, and the row it did keep before 2.0.0
 * held a plugin version string rather than a counter, so there is no earlier
 * number an install could be told it had gone backwards from.
 */
define( 'WP_POSTVIEWS_DB_VERSION', '1' );

/**
 * Plugin slug, text domain and menu slug.
 */
define( 'WP_POSTVIEWS_SLUG', 'wp-postviews' );

/**
 * WP-PostViews main file.
 */
define( 'WP_POSTVIEWS_MAIN_FILE', __FILE__ );

/**
 * Filesystem path of the plugin directory, with a trailing slash.
 */
define( 'WP_POSTVIEWS_DIR', plugin_dir_path( __FILE__ ) );

/**
 * URL of the plugin directory, with a trailing slash.
 */
define( 'WP_POSTVIEWS_URL', plugin_dir_url( __FILE__ ) );

// Classes. Required at file load because the activation hook and the option
// accessor are both reached before any action fires.
require_once WP_POSTVIEWS_DIR . 'includes/class-wp-postviews-options.php';
require_once WP_POSTVIEWS_DIR . 'includes/class-wp-postviews-display.php';
require_once WP_POSTVIEWS_DIR . 'includes/class-wp-postviews-query.php';
require_once WP_POSTVIEWS_DIR . 'includes/class-wp-postviews-counter.php';
require_once WP_POSTVIEWS_DIR . 'includes/class-wp-postviews-blocks.php';
require_once WP_POSTVIEWS_DIR . 'includes/class-wp-postviews-core.php';
require_once WP_POSTVIEWS_DIR . 'includes/class-wp-postviews-api.php';
require_once WP_POSTVIEWS_DIR . 'includes/class-wp-postviews-widget.php';
require_once WP_POSTVIEWS_DIR . 'includes/class-wp-postviews-admin.php';
require_once WP_POSTVIEWS_DIR . 'includes/class-wp-postviews-settings.php';
require_once WP_POSTVIEWS_DIR . 'includes/class-wp-postviews-wpstats.php';
require_once WP_POSTVIEWS_DIR . 'includes/template-tags.php';

WP_PostViews_Options::init();
WP_PostViews_Display::init();
WP_PostViews_Counter::init();
WP_PostViews_Blocks::init();
WP_PostViews_Core::init();
new WP_PostViews_API();
WP_PostViews_Core::register_command();
WP_PostViews_Admin::init();
WP_PostViews_Settings::init();

// Loaded and initialised unconditionally. WP-Stats may not be installed, in
// which case nothing fires wp_stats_sections and this is inert - there is no
// class_exists() probing between the two plugins.
WP_PostViews_WPStats::init();

add_action(
	'widgets_init',
	function () {
		register_widget( 'WP_PostViews_Widget' );
	}
);

// register_activation_hook() has to be called while the plugin file is being
// loaded, which is why this is here rather than inside a class initialiser.
register_activation_hook( __FILE__, 'wp_postviews_activate' );

/**
 * Seed the options row, on this site or across the network.
 *
 * @param bool $network_wide Whether the plugin is being activated network wide.
 * @return void
 */
function wp_postviews_activate( $network_wide ) {
	if ( is_multisite() && $network_wide ) {
		// get_sites(), not the wp_get_sites() this used to call: that one has
		// been deprecated since WP 4.6 and returns only the first 100 sites.
		// 'number' => 0 lifts WP_Site_Query's own default cap of 100 too.
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			WP_PostViews_Options::install();
			restore_current_blog();
		}

		return;
	}

	WP_PostViews_Options::install();
}
