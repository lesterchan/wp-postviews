<?php
/**
 * The admin surface: the one menu entry, the screen behind it, and the Views
 * column on the post and page list tables.
 *
 * This class owns the menu and the screens. WP_PostViews_Settings owns
 * register_setting(), the sections, the field callbacks and the sanitiser, and
 * WP_PostViews_WPStats owns the block this plugin contributes to the WP-Stats
 * page. Settings is the only surface a site owner manages anything on, so §4.1
 * puts it under Settings with add_options_page() rather than giving the plugin a
 * top-level menu: the Views column belongs to core's post list table, not to a
 * screen of ours.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * The menu, the settings screen and the Views column.
 */
class WP_PostViews_Admin {

	/**
	 * Menu and screen slug.
	 *
	 * @var string
	 */
	const PAGE = 'wp-postviews';

	/**
	 * Capability the screen is gated on, read through the wp_postviews_capability filter.
	 *
	 * @var string
	 */
	const CAPABILITY = 'manage_options';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );

		add_filter( 'manage_posts_columns', array( __CLASS__, 'add_column' ) );
		add_filter( 'manage_pages_columns', array( __CLASS__, 'add_column' ) );
		add_action( 'manage_posts_custom_column', array( __CLASS__, 'render_column' ) );
		add_action( 'manage_pages_custom_column', array( __CLASS__, 'render_column' ) );

		add_filter( 'manage_edit-post_sortable_columns', array( __CLASS__, 'sortable_column' ) );
		add_filter( 'manage_edit-page_sortable_columns', array( __CLASS__, 'sortable_column' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'sort_by_column' ) );
	}

	/**
	 * Add the Views column.
	 *
	 * @param array $columns List table columns.
	 * @return array
	 */
	public static function add_column( $columns ) {
		$columns['views'] = __( 'Views', 'wp-postviews' );

		return $columns;
	}

	/**
	 * Render the Views cell.
	 *
	 * $always is true because the wp_postviews_should_display gate governs the
	 * front end only. Letting it apply here blanked the admin column for anyone
	 * who had chosen "Don't display", which is what went wrong in 1.65, and a
	 * filter written for the front end would do it again.
	 *
	 * @param string $column_name The column being rendered.
	 * @return void
	 */
	public static function render_column( $column_name ) {
		if ( 'views' !== $column_name ) {
			return;
		}

		WP_PostViews_Display::the_views( true, '', '', true );
	}

	/**
	 * Mark the Views column sortable.
	 *
	 * @param array $columns Sortable columns.
	 * @return array
	 */
	public static function sortable_column( $columns ) {
		$columns['views'] = 'views';

		return $columns;
	}

	/**
	 * Apply the sort when the Views column header is clicked.
	 *
	 * @param WP_Query $query The query being prepared.
	 * @return void
	 */
	public static function sort_by_column( $query ) {
		if ( ! is_admin() ) {
			return;
		}

		if ( 'views' !== $query->get( 'orderby' ) ) {
			return;
		}

		$query->set( 'meta_key', 'views' );
		$query->set( 'orderby', 'meta_value_num' );
	}

	/**
	 * The capability the screen is gated on.
	 *
	 * Read through one filter so a site that hands the settings screen to a
	 * custom role changes it in a single place, rather than having to find every
	 * current_user_can() call in the plugin.
	 *
	 * @param string $context What is being gated. Only 'settings' so far.
	 * @return string
	 */
	public static function capability( $context = 'settings' ) {
		/**
		 * Filters the capability required to reach a WP-PostViews screen.
		 *
		 * @since 2.0.0
		 *
		 * @param string $capability The required capability.
		 * @param string $context    What is being gated.
		 */
		return apply_filters( 'wp_postviews_capability', self::CAPABILITY, $context );
	}

	/**
	 * Add the Settings submenu entry.
	 *
	 * @return void
	 */
	public static function add_menu() {
		$hook = add_options_page(
			__( 'Post Views Settings', 'wp-postviews' ),
			__( 'WP-PostViews', 'wp-postviews' ),
			self::capability(),
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);

		add_action( 'load-' . $hook, array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Load the screen's script.
	 *
	 * @return void
	 */
	public static function enqueue() {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue the "Restore Default Template" behaviour.
	 *
	 * The defaults are handed over as data rather than being written into an
	 * inline onclick attribute, which is what 1.78.1 did. That attribute ran
	 * the translated default through esc_js( esc_attr() ) into a JavaScript
	 * string literal, which is the kind of construction these plugins have
	 * historically hidden their escaping bugs in.
	 *
	 * @return void
	 */
	public static function enqueue_scripts() {
		wp_enqueue_script(
			'wp-postviews-admin',
			WP_POSTVIEWS_URL . 'js/wp-postviews-admin.js',
			array(),
			WP_POSTVIEWS_VERSION,
			true
		);
		wp_localize_script(
			'wp-postviews-admin',
			'wpPostViewsL10n',
			array(
				'defaults' => array(
					'template'             => WP_PostViews_Options::default_template( 'template' ),
					'most_viewed_template' => WP_PostViews_Options::default_template( 'most_viewed_template' ),
				),
			)
		);
	}

	/**
	 * Render the settings screen.
	 *
	 * Two tabs over one settings group and one option row. §4.1: more than one
	 * group of settings becomes tabs on the single page, never a second submenu
	 * entry.
	 *
	 * No settings_errors() call of its own. This screen is a child of
	 * options-general.php, and wp-admin/admin-header.php loads options-head.php
	 * for every such screen, which calls settings_errors() already - on whichever
	 * tab the save came back to. A second call here would print "Settings saved."
	 * twice.
	 *
	 * @return void
	 */
	public static function render_page() {
		if ( ! current_user_can( self::capability() ) ) {
			return;
		}

		$tab = WP_PostViews_Settings::current_tab();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Post Views Settings', 'wp-postviews' ); ?></h1>

			<nav class="nav-tab-wrapper">
				<?php foreach ( WP_PostViews_Settings::tabs() as $slug => $label ) : ?>
					<?php
					$tab_url = add_query_arg(
						array(
							'page' => self::PAGE,
							'tab'  => $slug,
						),
						admin_url( 'options-general.php' )
					);
					?>
					<a href="<?php echo esc_url( $tab_url ); ?>"
						class="nav-tab<?php echo $slug === $tab ? ' nav-tab-active' : ''; ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<form method="post" action="options.php">
				<?php
				settings_fields( WP_PostViews_Settings::GROUP );

				/*
				 * The tab is carried through the save, so options.php sends the
				 * browser back to the tab it was submitted from rather than to the
				 * first one. settings_fields() has already emitted a
				 * _wp_http_referer built from REQUEST_URI; this one comes after it
				 * and wins, because PHP keeps the last value for a repeated name.
				 */
				printf(
					'<input type="hidden" name="_wp_http_referer" value="%s" />',
					esc_url(
						add_query_arg(
							array(
								'page' => self::PAGE,
								'tab'  => $tab,
							),
							admin_url( 'options-general.php' )
						)
					)
				);

				// There is no use_ajax field when nothing is cached, and a key the
				// form omits keeps its stored value, so pin it off here - on the
				// tab that owns the row, not on both.
				if ( 'settings' === $tab && ! WP_PostViews_Settings::using_cache() ) {
					?>
					<input type="hidden" name="<?php echo esc_attr( WP_PostViews_Options::OPTION . '[use_ajax]' ); ?>" value="0" />
					<?php
				}

				do_settings_sections( WP_PostViews_Settings::tab_page( $tab ) );

				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
