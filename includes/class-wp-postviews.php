<?php
/**
 * Plugin bootstrap.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Boots the plugin: the components, the activation hook, the widget, the REST
 * routes and the WP-CLI command.
 */
class WP_PostViews {

	/**
	 * Wire up every component, and everything that is not a component's own.
	 *
	 * @return void
	 */
	public static function init() {
		// Must be registered at file-load time, which is when this runs.
		register_activation_hook( WP_POSTVIEWS_MAIN_FILE, array( __CLASS__, 'activate' ) );

		WP_PostViews_Options::init();
		WP_PostViews_Display::init();
		WP_PostViews_Counter::init();
		WP_PostViews_Blocks::init();
		WP_PostViews_Core::init();
		WP_PostViews_Admin::init();
		WP_PostViews_Settings::init();

		// Initialised unconditionally. WP-Stats may not be installed, in which
		// case nothing fires wp_stats_sections and this is inert - there is no
		// class_exists() probing between the two plugins.
		WP_PostViews_WPStats::init();

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
	 * @param bool $network_wide Whether the plugin is being activated network-wide.
	 * @return void
	 */
	public static function activate( $network_wide = false ) {
		if ( is_multisite() && $network_wide ) {
			// 'number' => 0 lifts WP_Site_Query's default cap of 100, which would otherwise skip every site past the hundredth while reporting success.
			$site_ids = get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			);

			foreach ( $site_ids as $site_id ) {
				switch_to_blog( (int) $site_id );
				WP_PostViews_Options::install();
				// Inside the loop: switch_to_blog() pushes onto a stack, so restoring once after the loop unwinds it by exactly one.
				restore_current_blog();
			}

			return;
		}

		WP_PostViews_Options::install();
	}
}
