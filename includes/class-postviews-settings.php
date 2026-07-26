<?php
/**
 * The Settings > PostViews screen.
 *
 * Replaces the hand-rolled form that posted back to itself and processed
 * $_POST inline. The Settings API does the nonce, the capability check, the
 * redirect and the "Settings saved" notice, so none of that is repeated here.
 *
 * The screen slug changed with 2.0.0. It used to be the literal plugin file
 * path, wp-postviews/postviews-options.php, which is how menu pages were
 * registered before add_options_page() grew a proper slug argument, and it
 * meant WordPress include()d the file to render the page. Any bookmark to the
 * old URL needs updating.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the setting and renders the options screen.
 */
class PostViews_Settings {

	/**
	 * Settings group the screen posts under.
	 *
	 * @var string
	 */
	const GROUP = 'views_options_group';

	/**
	 * Menu slug.
	 *
	 * @var string
	 */
	const SLUG = 'wp-postviews';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Add the Settings submenu entry.
	 *
	 * @return void
	 */
	public static function add_menu() {
		$hook = add_options_page(
			__( 'PostViews', 'wp-postviews' ),
			__( 'PostViews', 'wp-postviews' ),
			'manage_options',
			self::SLUG,
			array( __CLASS__, 'render' )
		);

		add_action( 'load-' . $hook, array( __CLASS__, 'enqueue' ) );
	}

	/**
	 * Register the one setting.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::GROUP,
			PostViews_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => PostViews_Options::defaults(),
			)
		);
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
			plugins_url( 'postviews-admin.js', WP_POSTVIEWS_MAIN_FILE ),
			array(),
			WP_POSTVIEWS_VERSION,
			true
		);
		wp_localize_script(
			'wp-postviews-admin',
			'postviewsAdminL10n',
			array(
				'defaults' => array(
					'template'             => PostViews_Options::default_template( 'template' ),
					'most_viewed_template' => PostViews_Options::default_template( 'most_viewed_template' ),
				),
			)
		);
	}

	/**
	 * Validate what the screen submitted.
	 *
	 * Merges into the stored value rather than replacing it, so a key the form
	 * did not render - and there is one, use_ajax when WP_CACHE is off - keeps
	 * whatever it had.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$current = PostViews_Options::all();

		if ( ! is_array( $input ) ) {
			return $current;
		}

		foreach ( array( 'count', 'display_home', 'display_single', 'display_page', 'display_archive', 'display_search', 'display_other' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				// Every one of these is a three way choice.
				$current[ $key ] = min( 2, max( 0, (int) $input[ $key ] ) );
			}
		}

		foreach ( array( 'exclude_bots', 'use_ajax' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$current[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
			}
		}

		foreach ( array( 'template', 'most_viewed_template' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$current[ $key ] = wp_kses_post( trim( $input[ $key ] ) );
			}
		}

		// No flush needed here: update_option() fires update_option_views_options
		// once the sanitised value is stored, and PostViews_Options listens for it.
		return $current;
	}

	/**
	 * The three way "who sees this" choice used by every display option.
	 *
	 * @param string $never_label Wording of the third choice, which differs per context.
	 * @return array
	 */
	protected static function display_choices( $never_label ) {
		return array(
			0 => __( 'Display to everyone', 'wp-postviews' ),
			1 => __( 'Display to registered users only', 'wp-postviews' ),
			2 => $never_label,
		);
	}

	/**
	 * Render a select bound to one option key.
	 *
	 * @param string $key     Option key.
	 * @param array  $choices value => label.
	 * @return void
	 */
	protected static function select( $key, $choices ) {
		$selected = PostViews_Options::get_int( $key );
		?>
		<select name="<?php echo esc_attr( PostViews_Options::OPTION . '[' . $key . ']' ); ?>" id="views-<?php echo esc_attr( $key ); ?>" size="1">
			<?php foreach ( $choices as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $value, $selected ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render a list of the tokens a template accepts.
	 *
	 * The tokens are emitted as markup rather than being interpolated into a
	 * translatable string: phpcbf reads %VIEW_COUNT% as a printf placeholder
	 * and rewrites it to %1$VIEW_COUNT%, which changes the msgid and shows the
	 * mangled form to the user.
	 *
	 * @param array $tokens Token names, without the surrounding percent signs.
	 * @return void
	 */
	protected static function token_list( $tokens ) {
		echo '<p>' . esc_html__( 'Allowed Variables:', 'wp-postviews' ) . '</p>';
		echo '<ul>';
		foreach ( $tokens as $token ) {
			echo '<li><code>%' . esc_html( $token ) . '%</code></li>';
		}
		echo '</ul>';
	}

	/**
	 * Render the screen.
	 *
	 * @return void
	 */
	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$using_cache = defined( 'WP_CACHE' ) && WP_CACHE;
		$option      = PostViews_Options::OPTION;
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Post Views Options', 'wp-postviews' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="views-count"><?php esc_html_e( 'Count Views From:', 'wp-postviews' ); ?></label></th>
						<td>
							<?php
							self::select(
								'count',
								array(
									0 => __( 'Everyone', 'wp-postviews' ),
									1 => __( 'Guests Only', 'wp-postviews' ),
									2 => __( 'Registered Users Only', 'wp-postviews' ),
								)
							);
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="views-exclude_bots"><?php esc_html_e( 'Exclude Bot Views:', 'wp-postviews' ); ?></label></th>
						<td>
							<?php
							self::select(
								'exclude_bots',
								array(
									0 => __( 'No', 'wp-postviews' ),
									1 => __( 'Yes', 'wp-postviews' ),
								)
							);
							?>
						</td>
					</tr>
					<?php if ( $using_cache ) : ?>
						<tr>
							<th scope="row"><label for="views-use_ajax"><?php esc_html_e( 'Use AJAX To Update Views:', 'wp-postviews' ); ?></label></th>
							<td>
								<?php
								self::select(
									'use_ajax',
									array(
										0 => __( 'No', 'wp-postviews' ),
										1 => __( 'Yes', 'wp-postviews' ),
									)
								);
								?>
								<p class="description">
									<?php esc_html_e( 'You have caching enabled for your WordPress installation, by default WP-PostViews will use AJAX to update the view count. However in some cases, you might not want it.', 'wp-postviews' ); ?>
								</p>
							</td>
						</tr>
					<?php else : ?>
						<input type="hidden" name="<?php echo esc_attr( $option . '[use_ajax]' ); ?>" value="0" />
					<?php endif; ?>
					<tr>
						<th scope="row">
							<label for="views-template-template"><?php esc_html_e( 'Views Template:', 'wp-postviews' ); ?></label>
							<?php self::token_list( array( 'VIEW_COUNT', 'VIEW_COUNT_ROUNDED' ) ); ?>
						</th>
						<td>
							<input type="text" class="large-text code" id="views-template-template" name="<?php echo esc_attr( $option . '[template]' ); ?>" value="<?php echo esc_attr( PostViews_Options::get( 'template', '' ) ); ?>" />
							<p>
								<button type="button" class="button" data-postviews-reset="template" data-postviews-target="views-template-template">
									<?php esc_html_e( 'Restore Default Template', 'wp-postviews' ); ?>
								</button>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="views-template-most_viewed_template"><?php esc_html_e( 'Most Viewed Template:', 'wp-postviews' ); ?></label>
							<?php
							self::token_list(
								array(
									'VIEW_COUNT',
									'VIEW_COUNT_ROUNDED',
									'POST_TITLE',
									'POST_DATE',
									'POST_TIME',
									'POST_EXCERPT',
									'POST_CONTENT',
									'POST_URL',
									'POST_THUMBNAIL',
									'POST_THUMBNAIL_URL',
									'POST_CATEGORY_ID',
									'POST_AUTHOR',
								)
							);
							?>
						</th>
						<td>
							<textarea class="large-text code" rows="12" id="views-template-most_viewed_template" name="<?php echo esc_attr( $option . '[most_viewed_template]' ); ?>"><?php echo esc_textarea( PostViews_Options::get( 'most_viewed_template', '' ) ); ?></textarea>
							<p>
								<button type="button" class="button" data-postviews-reset="most_viewed_template" data-postviews-target="views-template-most_viewed_template">
									<?php esc_html_e( 'Restore Default Template', 'wp-postviews' ); ?>
								</button>
							</p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Display Options', 'wp-postviews' ); ?></h2>
				<p>
					<?php
					printf(
						/* translators: %s: the the_views() template tag, wrapped in a code element. */
						esc_html__( 'These options specify where the view counts should be displayed and to whom. By default view counts will be displayed to all visitors. Note that the theme files must contain a call to %s in order for any view count to be displayed.', 'wp-postviews' ),
						'<code>the_views()</code>'
					);
					?>
				</p>

				<table class="form-table" role="presentation">
					<?php
					$contexts = array(
						'display_home'    => array( __( 'Home Page:', 'wp-postviews' ), __( "Don't display on home page", 'wp-postviews' ) ),
						'display_single'  => array( __( 'Single Posts:', 'wp-postviews' ), __( "Don't display on single posts", 'wp-postviews' ) ),
						'display_page'    => array( __( 'Pages:', 'wp-postviews' ), __( "Don't display on pages", 'wp-postviews' ) ),
						'display_archive' => array( __( 'Archive Pages:', 'wp-postviews' ), __( "Don't display on archive pages", 'wp-postviews' ) ),
						'display_search'  => array( __( 'Search Pages:', 'wp-postviews' ), __( "Don't display on search pages", 'wp-postviews' ) ),
						'display_other'   => array( __( 'Other Pages:', 'wp-postviews' ), __( "Don't display on other pages", 'wp-postviews' ) ),
					);
					foreach ( $contexts as $key => $labels ) :
						list( $label, $never_label ) = $labels;
						?>
						<tr>
							<th scope="row"><label for="views-<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
							<td><?php self::select( $key, self::display_choices( $never_label ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
