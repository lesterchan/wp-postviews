<?php
/**
 * The Settings > PostViews screen.
 *
 * Replaces the hand-rolled form that posted back to itself and processed
 * $_POST inline. The Settings API does the nonce, the capability check, the
 * redirect and the "Settings saved" notice, so none of that is repeated here.
 *
 * Every row is registered with add_settings_section() and add_settings_field()
 * and drawn by do_settings_sections(), so the form-table markup, the label
 * pairing and the row order all come from core rather than from a hand written
 * table in render().
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
	 * Section holding the counting and template rows.
	 *
	 * @var string
	 */
	const SECTION_GENERAL = 'views_general';

	/**
	 * Section holding the per context display matrix.
	 *
	 * @var string
	 */
	const SECTION_DISPLAY = 'views_display';

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
	 * Register the one setting, and the sections and fields that edit it.
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

		// No title, so do_settings_sections() emits the table straight after the
		// h1 the way the screen has always looked. No callback either.
		add_settings_section( self::SECTION_GENERAL, '', '', self::SLUG );

		add_settings_field(
			'count',
			__( 'Count Views From:', 'wp-postviews' ),
			array( __CLASS__, 'field_select' ),
			self::SLUG,
			self::SECTION_GENERAL,
			array(
				'label_for' => 'views-count',
				'key'       => 'count',
				'choices'   => array(
					0 => __( 'Everyone', 'wp-postviews' ),
					1 => __( 'Guests Only', 'wp-postviews' ),
					2 => __( 'Registered Users Only', 'wp-postviews' ),
				),
			)
		);

		add_settings_field(
			'exclude_bots',
			__( 'Exclude Bot Views:', 'wp-postviews' ),
			array( __CLASS__, 'field_select' ),
			self::SLUG,
			self::SECTION_GENERAL,
			array(
				'label_for' => 'views-exclude_bots',
				'key'       => 'exclude_bots',
				'choices'   => self::yes_no_choices(),
			)
		);

		// Without a page cache the setting has no effect, so the row is not
		// offered at all; render() pins the stored value off instead.
		if ( self::using_cache() ) {
			add_settings_field(
				'use_ajax',
				__( 'Use AJAX To Update Views:', 'wp-postviews' ),
				array( __CLASS__, 'field_select' ),
				self::SLUG,
				self::SECTION_GENERAL,
				array(
					'label_for'   => 'views-use_ajax',
					'key'         => 'use_ajax',
					'choices'     => self::yes_no_choices(),
					'description' => __( 'You have caching enabled for your WordPress installation, by default WP-PostViews will use AJAX to update the view count. However in some cases, you might not want it.', 'wp-postviews' ),
				)
			);
		}

		add_settings_field(
			'template',
			self::template_title( __( 'Views Template:', 'wp-postviews' ), 'views-template-template', array( 'VIEW_COUNT', 'VIEW_COUNT_ROUNDED' ) ),
			array( __CLASS__, 'field_template' ),
			self::SLUG,
			self::SECTION_GENERAL,
			array(
				'key'  => 'template',
				'id'   => 'views-template-template',
				'type' => 'text',
			)
		);

		add_settings_field(
			'most_viewed_template',
			self::template_title(
				__( 'Most Viewed Template:', 'wp-postviews' ),
				'views-template-most_viewed_template',
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
			),
			array( __CLASS__, 'field_template' ),
			self::SLUG,
			self::SECTION_GENERAL,
			array(
				'key'  => 'most_viewed_template',
				'id'   => 'views-template-most_viewed_template',
				'type' => 'textarea',
			)
		);

		add_settings_section(
			self::SECTION_DISPLAY,
			__( 'Display Options', 'wp-postviews' ),
			array( __CLASS__, 'display_section' ),
			self::SLUG
		);

		$contexts = array(
			'display_home'    => array( __( 'Home Page:', 'wp-postviews' ), __( "Don't display on home page", 'wp-postviews' ) ),
			'display_single'  => array( __( 'Single Posts:', 'wp-postviews' ), __( "Don't display on single posts", 'wp-postviews' ) ),
			'display_page'    => array( __( 'Pages:', 'wp-postviews' ), __( "Don't display on pages", 'wp-postviews' ) ),
			'display_archive' => array( __( 'Archive Pages:', 'wp-postviews' ), __( "Don't display on archive pages", 'wp-postviews' ) ),
			'display_search'  => array( __( 'Search Pages:', 'wp-postviews' ), __( "Don't display on search pages", 'wp-postviews' ) ),
			'display_other'   => array( __( 'Other Pages:', 'wp-postviews' ), __( "Don't display on other pages", 'wp-postviews' ) ),
		);

		foreach ( $contexts as $key => $labels ) {
			list( $label, $never_label ) = $labels;

			add_settings_field(
				$key,
				$label,
				array( __CLASS__, 'field_select' ),
				self::SLUG,
				self::SECTION_DISPLAY,
				array(
					'label_for' => 'views-' . $key,
					'key'       => $key,
					'choices'   => self::display_choices( $never_label ),
				)
			);
		}
	}

	/**
	 * Whether a page cache is in play.
	 *
	 * @return bool
	 */
	protected static function using_cache() {
		return defined( 'WP_CACHE' ) && WP_CACHE;
	}

	/**
	 * The two choices every toggle on this screen offers.
	 *
	 * @return array
	 */
	protected static function yes_no_choices() {
		return array(
			0 => __( 'No', 'wp-postviews' ),
			1 => __( 'Yes', 'wp-postviews' ),
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
	 * Field callback: a select bound to one option key.
	 *
	 * @param array $args Field args: key, choices, and an optional description.
	 * @return void
	 */
	public static function field_select( $args ) {
		$key      = $args['key'];
		$selected = PostViews_Options::get_int( $key );
		?>
		<select name="<?php echo esc_attr( PostViews_Options::OPTION . '[' . $key . ']' ); ?>" id="views-<?php echo esc_attr( $key ); ?>" size="1">
			<?php foreach ( $args['choices'] as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>"<?php selected( $value, $selected ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
		if ( ! empty( $args['description'] ) ) {
			echo '<p class="description">' . esc_html( $args['description'] ) . '</p>';
		}
	}

	/**
	 * Field callback: a template input plus its "Restore Default Template"
	 * button.
	 *
	 * The button carries the option key and the field id as data attributes;
	 * that pairing is the whole contract with postviews-admin.js.
	 *
	 * @param array $args Field args: key, id, and type of text or textarea.
	 * @return void
	 */
	public static function field_template( $args ) {
		$key   = $args['key'];
		$id    = $args['id'];
		$name  = PostViews_Options::OPTION . '[' . $key . ']';
		$value = PostViews_Options::get( $key, '' );

		if ( 'textarea' === $args['type'] ) {
			?>
			<textarea class="large-text code" rows="12" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
			<?php
		} else {
			?>
			<input type="text" class="large-text code" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<?php
		}
		?>
		<p>
			<button type="button" class="button" data-postviews-reset="<?php echo esc_attr( $key ); ?>" data-postviews-target="<?php echo esc_attr( $id ); ?>">
				<?php esc_html_e( 'Restore Default Template', 'wp-postviews' ); ?>
			</button>
		</p>
		<?php
	}

	/**
	 * The heading cell for a template field: its label, then the tokens the
	 * template accepts.
	 *
	 * Core prints a field title as given, and do_settings_fields() wraps it in a
	 * label element when label_for is set. These titles carry a list, which has
	 * no business inside a label, so they bring their own label element and the
	 * fields leave label_for unset.
	 *
	 * The tokens are markup rather than placeholders in a translatable string:
	 * phpcbf reads %VIEW_COUNT% as a printf placeholder and rewrites it to
	 * %1$VIEW_COUNT%, which changes the msgid and shows the mangled form to the
	 * user.
	 *
	 * @param string $label  Visible label.
	 * @param string $id     Field the label points at.
	 * @param array  $tokens Token names, without the surrounding percent signs.
	 * @return string
	 */
	protected static function template_title( $label, $id, $tokens ) {
		$title  = '<label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
		$title .= '<p>' . esc_html__( 'Allowed Variables:', 'wp-postviews' ) . '</p>';
		$title .= '<ul>';
		foreach ( $tokens as $token ) {
			$title .= '<li><code>%' . esc_html( $token ) . '%</code></li>';
		}
		$title .= '</ul>';

		return $title;
	}

	/**
	 * Section callback: what the display matrix does.
	 *
	 * @return void
	 */
	public static function display_section() {
		?>
		<p>
			<?php
			printf(
				/* translators: %s: the the_views() template tag, wrapped in a code element. */
				esc_html__( 'These options specify where the view counts should be displayed and to whom. By default view counts will be displayed to all visitors. Note that the theme files must contain a call to %s in order for any view count to be displayed.', 'wp-postviews' ),
				'<code>the_views()</code>'
			);
			?>
		</p>
		<?php
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

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Post Views Options', 'wp-postviews' ); ?></h1>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::GROUP );

				// There is no use_ajax field when nothing is cached, and a key the
				// form omits keeps its stored value, so pin it off here.
				if ( ! self::using_cache() ) {
					?>
					<input type="hidden" name="<?php echo esc_attr( PostViews_Options::OPTION . '[use_ajax]' ); ?>" value="0" />
					<?php
				}

				do_settings_sections( self::SLUG );

				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
