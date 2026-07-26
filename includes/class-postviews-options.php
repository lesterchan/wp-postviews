<?php
/**
 * Option storage for WP-PostViews.
 *
 * Everything the plugin configures already lived in a single wp_options row,
 * views_options, so 2.0.0 does not consolidate anything - it just puts one
 * accessor in front of the row instead of a get_option() call at each of the
 * dozen sites that used to read it. The keys are deliberately left flat and
 * unchanged: themes and other plugins read this option directly, and there is
 * no row count to win by nesting them.
 *
 * One companion row is kept separate: views_version. It is read to decide
 * whether views_options needs migrating, so it cannot live inside the thing
 * being migrated.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the views_options row.
 */
class PostViews_Options {

	/**
	 * Name of the settings row.
	 *
	 * @var string
	 */
	const OPTION = 'views_options';

	/**
	 * Row holding the version the stored data was last migrated to.
	 *
	 * @var string
	 */
	const VERSION_OPTION = 'views_version';

	/**
	 * Runtime cache, so a page render reads the row once rather than per lookup.
	 *
	 * @var array|null
	 */
	protected static $cache = null;

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		// On 'init' rather than 'admin_init': the 2.0.0 migration changes how
		// templates are stored, and until it has run a legacy template renders
		// with its backslashes showing. Waiting for an administrator to load a
		// screen would leave that visible to every front end visitor in the
		// meantime. The version gate means this writes at most once.
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 1 );

		// Before 2.0.0 every read went straight to get_option(), so anything
		// that wrote the row - another plugin, WP-CLI, a migration script -
		// took effect immediately in the same request. The runtime cache below
		// would otherwise keep serving the value from before the write.
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
	}

	/**
	 * Default value for every key.
	 *
	 * These mirror the pre-2.0.0 activation routine exactly. Changing any of
	 * them silently changes what a fresh install looks like.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'count'                => 1,
			'exclude_bots'         => 0,
			'display_home'         => 0,
			'display_single'       => 0,
			'display_page'         => 0,
			'display_archive'      => 0,
			'display_search'       => 0,
			'display_other'        => 0,
			'use_ajax'             => 1,
			'template'             => self::default_template( 'template' ),
			'most_viewed_template' => self::default_template( 'most_viewed_template' ),
		);
	}

	/**
	 * The stock markup for one of the two templates.
	 *
	 * Kept in one place so the defaults, the activation routine and the
	 * "Restore Default Template" buttons cannot drift apart.
	 *
	 * Only the bare word "views" is translatable. Putting the whole template
	 * through __() would hand phpcbf a string full of %TOKEN% placeholders,
	 * which it rewrites into numbered sprintf arguments - changing the msgid
	 * and showing users "%1$VIEW_COUNT%".
	 *
	 * @param string $key Either 'template' or 'most_viewed_template'.
	 * @return string
	 */
	public static function default_template( $key ) {
		if ( 'most_viewed_template' === $key ) {
			return '<li><a href="%POST_URL%"  title="%POST_TITLE%">%POST_TITLE%</a> - %VIEW_COUNT% ' . __( 'views', 'wp-postviews' ) . '</li>';
		}

		return '%VIEW_COUNT% ' . __( 'views', 'wp-postviews' );
	}

	/**
	 * The whole option with defaults merged in.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null === self::$cache ) {
			$stored = get_option( self::OPTION, array() );
			if ( ! is_array( $stored ) ) {
				$stored = array();
			}
			self::$cache = array_merge( self::defaults(), $stored );
		}

		return self::$cache;
	}

	/**
	 * Read one setting.
	 *
	 * @param string $key           Option key.
	 * @param mixed  $default_value Returned when the key is absent.
	 * @return mixed
	 */
	public static function get( $key, $default_value = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default_value;
	}

	/**
	 * Read one setting as an integer.
	 *
	 * @param string $key Option key.
	 * @return int
	 */
	public static function get_int( $key ) {
		return (int) self::get( $key, 0 );
	}

	/**
	 * Replace the whole option.
	 *
	 * @param array $values Full option array.
	 * @return bool
	 */
	public static function save( $values ) {
		self::$cache = array_merge( self::defaults(), (array) $values );

		return update_option( self::OPTION, self::$cache );
	}

	/**
	 * Drop the runtime cache. Needed after a migration writes the row.
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = null;
	}

	/**
	 * Seed the row on activation.
	 *
	 * @return void
	 */
	public static function install() {
		add_option( self::OPTION, self::defaults() );
		self::flush();
		self::maybe_upgrade();
	}

	/**
	 * Run any outstanding data migrations and record the version.
	 *
	 * Activation does not fire on plugin *update*, which is the usual reason a
	 * migration never runs, so this is driven from 'init' as well.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$installed = get_option( self::VERSION_OPTION );

		if ( WP_POSTVIEWS_VERSION === $installed ) {
			return;
		}

		// Gated on the recorded version rather than on "do the stored templates
		// still contain backslashes". A template with no apostrophe in it is
		// indistinguishable before and after, so a content check would rerun
		// the migration forever on some sites and never mark others done.
		if ( empty( $installed ) || version_compare( $installed, '2.0.0', '<' ) ) {
			self::migrate_template_slashes();
		}

		update_option( self::VERSION_OPTION, WP_POSTVIEWS_VERSION );
	}

	/**
	 * Unslash the two stored templates, once.
	 *
	 * Up to 1.78.1 the settings screen wrote $_POST straight through without
	 * wp_unslash(), so templates were stored slashed and every read path undid
	 * that with stripslashes(). 2.0.0 unslashes on save instead, which means
	 * the read paths must stop stripping - and that in turn means the rows
	 * already on disk have to be corrected once.
	 *
	 * Doing it the other way round, keeping stripslashes() on read, would eat a
	 * backslash the user genuinely wanted: a template containing a Windows path
	 * arrives unslashed, gets stripped again on output and loses the separator.
	 *
	 * @return void
	 */
	public static function migrate_template_slashes() {
		$stored = get_option( self::OPTION );

		if ( ! is_array( $stored ) ) {
			return;
		}

		$changed = false;
		foreach ( array( 'template', 'most_viewed_template' ) as $key ) {
			if ( isset( $stored[ $key ] ) && is_string( $stored[ $key ] ) ) {
				$unslashed = stripslashes( $stored[ $key ] );
				if ( $unslashed !== $stored[ $key ] ) {
					$stored[ $key ] = $unslashed;
					$changed        = true;
				}
			}
		}

		if ( $changed ) {
			update_option( self::OPTION, $stored );
			self::flush();
		}
	}
}
