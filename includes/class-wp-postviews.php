<?php
/**
 * Plugin bootstrap.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * The wiring that belongs to no component: activation, the widget, the REST
 * routes and the WP-CLI command.
 *
 * The components register their own hooks from their init() calls in the main
 * file; this class carries only what none of them owns.
 */
class WP_PostViews {

	/**
	 * Wire up everything that is not a component's own.
	 *
	 * @return void
	 */
	public static function init() {
		// Must be registered at file-load time, which is when this runs.
		register_activation_hook( WP_POSTVIEWS_MAIN_FILE, array( __CLASS__, 'activate' ) );

		new WP_PostViews_API();

		self::register_command();

		add_action( 'widgets_init', array( __CLASS__, 'register_widget' ) );
	}

	/**
	 * Register the widget.
	 *
	 * @return void
	 */
	public static function register_widget() {
		register_widget( 'WP_PostViews_Widget' );
	}

	/**
	 * Register the WP-CLI command.
	 *
	 * The class file is required here rather than at plugin load because it
	 * extends WP_CLI_Command, which only exists when WP-CLI is the one running
	 * WordPress. Requiring it unconditionally is a fatal error on every web
	 * request.
	 *
	 * @return void
	 */
	public static function register_command() {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		require_once WP_POSTVIEWS_DIR . 'includes/class-wp-postviews-command.php';

		WP_CLI::add_command( 'postviews', 'WP_PostViews_Command' );
	}

	/**
	 * Seed the options row, on this site or across the network.
	 *
	 * @param bool $network_wide Whether the plugin is being activated network wide.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
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
}
