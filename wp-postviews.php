<?php
/**
 * Plugin Name: WP-PostViews
 * Plugin URI: https://lesterchan.net/portfolio/programming/php/
 * Description: Enables you to display how many times a post/page had been viewed.
 * Version: 2.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
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
	Copyright 2026 Lester Chan  (email : lesterchan@gmail.com)

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
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


// Version.
define( 'WP_POSTVIEWS_VERSION', '2.0.0' );
define( 'WP_POSTVIEWS_MAIN_FILE', __FILE__ );

// Classes. Required at file load because the activation hook and the option
// accessor are both reached before any action fires.
require_once __DIR__ . '/includes/class-postviews-options.php';
require_once __DIR__ . '/includes/class-postviews-display.php';
require_once __DIR__ . '/includes/class-postviews-query.php';
require_once __DIR__ . '/includes/class-postviews-counter.php';
require_once __DIR__ . '/includes/class-postviews-core.php';
require_once __DIR__ . '/includes/class-postviews-widget.php';
require_once __DIR__ . '/includes/class-postviews-admin.php';
require_once __DIR__ . '/includes/class-postviews-settings.php';
require_once __DIR__ . '/includes/template-tags.php';

PostViews_Options::init();
PostViews_Display::init();
PostViews_Counter::init();
PostViews_Core::init();
PostViews_Admin::init();
PostViews_Settings::init();

add_action(
	'widgets_init',
	function () {
		register_widget( 'PostViews_Widget' );
	}
);

// register_activation_hook() has to be called while the plugin file is being
// loaded, which is why this is here rather than inside a class initialiser.
register_activation_hook( __FILE__, 'postviews_activate' );

/**
 * Seed the options row, on this site or across the network.
 *
 * @param bool $network_wide Whether the plugin is being activated network wide.
 * @return void
 */
function postviews_activate( $network_wide ) {
	if ( is_multisite() && $network_wide ) {
		// wp_get_sites() was removed in WP 5.1. 'number' => 0 lifts
		// WP_Site_Query's default cap of 100 sites.
		$site_ids = get_sites(
			array(
				'fields' => 'ids',
				'number' => 0,
			)
		);

		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			PostViews_Options::install();
			restore_current_blog();
		}

		return;
	}

	PostViews_Options::install();
}
