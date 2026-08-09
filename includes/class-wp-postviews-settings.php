<?php
/**
 * The one registered setting, its sections, its fields and its sanitiser.
 *
 * WP_PostViews_Admin owns the menu and the screen; everything the Settings API
 * needs to know about what that screen contains lives here. It replaces the
 * hand-rolled form that posted back to itself and processed $_POST inline: the
 * Settings API does the nonce, the capability check, the redirect and the
 * "Settings saved" notice, so none of that is repeated anywhere.
 *
 * Every row is registered with add_settings_section() and add_settings_field()
 * and drawn by do_settings_sections(), so the form-table markup, the label
 * pairing and the row order all come from core rather than from a hand written
 * table.
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
 * Registers the setting, its sections, its fields and its sanitiser.
 */
class WP_PostViews_Settings {

	/**
	 * Settings group the screen posts under, which is the settings row name.
	 *
	 * The same string as WP_PostViews_Options::OPTION deliberately: one setting,
	 * one group, and nothing to keep in step. It was views_options_group, a third
	 * name for the same thing.
	 *
	 * @var string
	 */
	const GROUP = 'wp_postviews_options';

	/**
	 * Section holding the counting rows.
	 *
	 * @var string
	 */
	const SECTION_GENERAL = 'wp_postviews_general';

	/**
	 * Section holding the two templates.
	 *
	 * @var string
	 */
	const SECTION_TEMPLATES = 'wp_postviews_templates';

	/**
	 * Section holding this plugin's half of the WP-Stats contract.
	 *
	 * @var string
	 */
	const SECTION_WPSTATS = 'wp_postviews_wpstats';

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register' ) );
	}

	/**
	 * The tabs on the settings screen, in the order they are drawn.
	 *
	 * Two, because the templates are long free-text fields with a list of
	 * allowed variables above each of them, and putting them beside four
	 * one-line selects left the settings a site owner actually changes below a
	 * screenful of markup.
	 *
	 * Named for what they hold and nothing else: the h1 above already says which
	 * plugin's screen this is.
	 *
	 * @return array
	 */
	public static function tabs() {
		return array(
			'settings'  => __( 'Settings', 'wp-postviews' ),
			'templates' => __( 'Templates', 'wp-postviews' ),
		);
	}

	/**
	 * Which tab is being shown.
	 *
	 * Public because WP_PostViews_Admin draws the screen and this class owns
	 * what is on it: §4.2 splits them, and the tab is the one fact both halves
	 * need.
	 *
	 * @return string
	 */
	public static function current_tab() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Choosing which tab to draw; nothing is read from the request beyond that and nothing is written.
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';

		return array_key_exists( $tab, self::tabs() ) ? $tab : 'settings';
	}

	/**
	 * The Settings API page a tab's sections are registered against.
	 *
	 * Each tab is its own settings page as far as do_settings_sections() is
	 * concerned, which is what stops one tab drawing the other's fields. There
	 * is still one register_setting(), one group and one option row: the split
	 * is in the drawing, not in the storage.
	 *
	 * @param string $tab Tab slug.
	 * @return string
	 */
	public static function tab_page( $tab ) {
		return WP_PostViews_Admin::PAGE . '-' . $tab;
	}

	/**
	 * Register the one setting, and the sections and fields that edit it.
	 *
	 * @return void
	 */
	public static function register() {
		$settings  = self::tab_page( 'settings' );
		$templates = self::tab_page( 'templates' );

		register_setting(
			self::GROUP,
			WP_PostViews_Options::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => WP_PostViews_Options::defaults(),
			)
		);

		// No title, so do_settings_sections() emits the table straight after the
		// tab nav the way the screen has always looked. No callback either.
		add_settings_section( self::SECTION_GENERAL, __( 'Counting', 'wp-postviews' ), '', $settings );

		add_settings_field(
			'count',
			__( 'Count Views From:', 'wp-postviews' ),
			array( __CLASS__, 'field_select' ),
			$settings,
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
			$settings,
			self::SECTION_GENERAL,
			array(
				'label_for' => 'views-exclude_bots',
				'key'       => 'exclude_bots',
				'choices'   => self::yes_no_choices(),
			)
		);

		// Without a page cache the setting has no effect, so the row is not
		// offered at all; render_page() pins the stored value off instead.
		if ( self::using_cache() ) {
			add_settings_field(
				'use_ajax',
				__( 'Use AJAX To Update Views:', 'wp-postviews' ),
				array( __CLASS__, 'field_select' ),
				$settings,
				self::SECTION_GENERAL,
				array(
					'label_for'   => 'views-use_ajax',
					'key'         => 'use_ajax',
					'choices'     => self::yes_no_choices(),
					'description' => __( 'You have caching enabled for your WordPress installation, by default WP-PostViews will use AJAX to update the view count. However in some cases, you might not want it.', 'wp-postviews' ),
				)
			);
		}

		add_settings_section(
			self::SECTION_WPSTATS,
			__( 'WP-Stats', 'wp-postviews' ),
			array( __CLASS__, 'wpstats_section' ),
			$settings
		);

		add_settings_field(
			'stats_display',
			__( 'Show A Views Section On The Stats Page?', 'wp-postviews' ),
			array( __CLASS__, 'field_stats_display' ),
			$settings,
			self::SECTION_WPSTATS,
			array( 'label_for' => 'views-stats_display' )
		);

		add_settings_field(
			'stats_most_limit',
			__( 'Number Of Entries In Each Most Viewed List:', 'wp-postviews' ),
			array( __CLASS__, 'field_stats_most_limit' ),
			$settings,
			self::SECTION_WPSTATS,
			array( 'label_for' => 'views-stats_most_limit' )
		);

		add_settings_section(
			self::SECTION_TEMPLATES,
			'',
			array( __CLASS__, 'templates_section' ),
			$templates
		);

		add_settings_field(
			'template',
			__( 'Views Template:', 'wp-postviews' ),
			array( __CLASS__, 'field_template' ),
			$templates,
			self::SECTION_TEMPLATES,
			array(
				'key'       => 'template',
				'id'        => 'views-template-template',
				'type'      => 'text',
				'label_for' => 'views-template-template',
				'tokens'    => array( 'VIEW_COUNT', 'VIEW_COUNT_ROUNDED' ),
			)
		);

		add_settings_field(
			'most_viewed_template',
			__( 'Most Viewed Template:', 'wp-postviews' ),
			array( __CLASS__, 'field_template' ),
			$templates,
			self::SECTION_TEMPLATES,
			array(
				'key'       => 'most_viewed_template',
				'id'        => 'views-template-most_viewed_template',
				'type'      => 'textarea',
				'label_for' => 'views-template-most_viewed_template',
				'tokens'    => array(
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
				),
			)
		);
	}

	/**
	 * Whether a page cache is in play.
	 *
	 * Public because it decides two things that have to agree: whether the
	 * use_ajax row is registered at all, and whether the screen pins the stored
	 * value off in its place.
	 *
	 * @return bool
	 */
	public static function using_cache() {
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
	 * Validate what the screen submitted.
	 *
	 * Merges into the stored value rather than replacing it, so a key this
	 * submission did not carry keeps whatever it had. That is not a nicety: the
	 * screen is two tabs posting disjoint sets of fields into one option row, so
	 * a callback that returned only what it was handed would empty the Templates
	 * tab every time somebody saved the Settings tab, and the other way round.
	 * The Settings API hands a sanitize callback the submitted array and nothing
	 * else; it has no idea another tab exists.
	 *
	 * Every key is therefore absent-means-unchanged, with no exceptions -
	 * including the stats_display checkbox, which posts nothing at all when it
	 * is unticked and so is paired with a hidden zero in field_stats_display().
	 * Reading absence as "off" instead would have been correct on a one page
	 * screen and silently switches the WP-Stats section off the first time
	 * anyone saves the Templates tab.
	 *
	 * @param mixed $input Submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$current = WP_PostViews_Options::all();

		if ( ! is_array( $input ) ) {
			return $current;
		}

		if ( isset( $input['count'] ) ) {
			// A three way choice: everyone, guests only, registered users only.
			$current['count'] = min( 2, max( 0, (int) $input['count'] ) );
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

		// Stored as a bool, which is what WP_PostViews_WPStats reads. Absent means
		// "the Templates tab was saved", not "unticked" - see the hidden zero in
		// field_stats_display() for how an unticked box still posts.
		if ( isset( $input['stats_display'] ) ) {
			$current['stats_display'] = ! empty( $input['stats_display'] );
		}

		if ( isset( $input['stats_most_limit'] ) ) {
			$current['stats_most_limit'] = max( 1, (int) $input['stats_most_limit'] );
		}

		// A key the screen no longer renders keeps its stored value, which is what
		// use_ajax relies on - so a key the plugin has deliberately retired has to
		// be taken back out, or the merge above would carry it forever.
		$current = array_diff_key( $current, array_flip( WP_PostViews_Options::retired_keys() ) );

		// No flush needed here: update_option() fires update_option_wp_postviews_options
		// once the sanitised value is stored, and WP_PostViews_Options listens for it.
		return $current;
	}

	/**
	 * Field callback: a select bound to one option key.
	 *
	 * @param array $args Field args: key, choices, and an optional description.
	 * @return void
	 */
	public static function field_select( $args ) {
		$key      = $args['key'];
		$selected = WP_PostViews_Options::get_int( $key );
		?>
		<select name="<?php echo esc_attr( WP_PostViews_Options::OPTION . '[' . $key . ']' ); ?>" id="views-<?php echo esc_attr( $key ); ?>" size="1">
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
	 * that pairing is the whole contract with js/wp-postviews-admin.js.
	 *
	 * @param array $args Field args: key, id, and type of text or textarea.
	 * @return void
	 */
	public static function field_template( $args ) {
		$key   = $args['key'];
		$id    = $args['id'];
		$name  = WP_PostViews_Options::OPTION . '[' . $key . ']';
		$value = WP_PostViews_Options::get( $key, '' );

		if ( 'textarea' === $args['type'] ) {
			?>
			<textarea class="large-text code" rows="12" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>"><?php echo esc_textarea( $value ); ?></textarea>
			<?php
		} else {
			?>
			<input type="text" class="large-text code" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( $value ); ?>" />
			<?php
		}

		self::token_list( isset( $args['tokens'] ) ? (array) $args['tokens'] : array() );
		?>
		<p>
			<button type="button" class="button" data-postviews-reset="<?php echo esc_attr( $key ); ?>" data-postviews-target="<?php echo esc_attr( $id ); ?>">
				<?php esc_html_e( 'Restore Default Template', 'wp-postviews' ); ?>
			</button>
		</p>
		<?php
	}

	/**
	 * Field callback: whether to offer a section to the WP-Stats page.
	 *
	 * The hidden zero in front of the checkbox is what makes the row tab-safe. A
	 * checkbox posts nothing when it is unticked, so the sanitiser cannot tell
	 * "unticked on the Settings tab" from "the Templates tab was saved" unless
	 * something is always posted alongside it. The hidden field is always
	 * posted; the checkbox overrides it when it is ticked, because PHP takes the
	 * last value for a repeated name.
	 *
	 * @return void
	 */
	public static function field_stats_display() {
		?>
		<input type="hidden" name="<?php echo esc_attr( WP_PostViews_Options::OPTION . '[stats_display]' ); ?>" value="0" />
		<input type="checkbox" id="views-stats_display"
			name="<?php echo esc_attr( WP_PostViews_Options::OPTION . '[stats_display]' ); ?>"
			value="1" <?php checked( (bool) WP_PostViews_Options::get( 'stats_display' ) ); ?> />
		<p class="description">
			<?php esc_html_e( 'WP-PostViews owns this setting now. Before 2.0.0 it lived in a row shared with WP-Stats and five other plugins, where whichever plugin saved last wrote the whole thing.', 'wp-postviews' ); ?>
		</p>
		<?php
	}

	/**
	 * Field callback: how many entries each most viewed listing carries.
	 *
	 * @return void
	 */
	public static function field_stats_most_limit() {
		?>
		<input type="number" class="small-text" min="1" step="1" id="views-stats_most_limit"
			name="<?php echo esc_attr( WP_PostViews_Options::OPTION . '[stats_most_limit]' ); ?>"
			value="<?php echo esc_attr( WP_PostViews_Options::get_int( 'stats_most_limit' ) ); ?>" />
		<?php
	}

	/**
	 * Section callback: what the Templates tab is for.
	 *
	 * @return void
	 */
	public static function templates_section() {
		?>
		<p>
			<?php
			printf(
				/* translators: %s: the the_views() template tag, wrapped in a code element. */
				esc_html__( 'The markup a view count is rendered with. The theme files must contain a call to %s for the first of these to appear at all; the second is used by the most viewed listings and the widget.', 'wp-postviews' ),
				'<code>the_views()</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Section callback: what the WP-Stats section contains.
	 *
	 * @return void
	 */
	public static function wpstats_section() {
		?>
		<p>
			<?php esc_html_e( 'If WP-Stats is installed, WP-PostViews offers it one section holding the total view count and the most viewed posts and pages. These settings do nothing without WP-Stats.', 'wp-postviews' ); ?>
		</p>
		<?php
	}

	/**
	 * The tokens a template accepts, listed under the field.
	 *
	 * Under it, in the field cell, and not in the heading cell beside it: a hint
	 * belongs with the control it describes, which is where WordPress puts one
	 * and where the other four plugins with a token list already had it. This
	 * screen listed them in the heading cell as a bulleted column instead, so one
	 * setting read four different ways across the set.
	 *
	 * The tokens are markup rather than placeholders in a translatable string:
	 * phpcbf reads %VIEW_COUNT% as a printf placeholder and rewrites it to
	 * %1$VIEW_COUNT%, which changes the msgid and shows the mangled form to the
	 * user.
	 *
	 * @param array $tokens Token names, without the surrounding percent signs.
	 * @return void
	 */
	protected static function token_list( $tokens ) {
		if ( empty( $tokens ) ) {
			return;
		}

		echo '<p class="description">' . esc_html__( 'Allowed variables:', 'wp-postviews' ) . ' ';

		foreach ( $tokens as $token ) {
			echo '<code>%' . esc_html( $token ) . '%</code> ';
		}

		echo '</p>';
	}
}
