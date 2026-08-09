<?php
/**
 * Option storage for WP-PostViews.
 *
 * Two rows, and only two: wp_postviews_options for everything a site owner can
 * change, and wp_postviews_version for the two upgrade markers. The markers sit
 * in their own row rather than inside the settings because a sanitize callback
 * is a function from what the form posted to what gets stored, and the settings
 * form never posts an upgrade marker - anything living in that array has to be
 * rescued from the stored value on every save, which is a step somebody
 * eventually forgets.
 *
 * Both names changed with 2.0.0. views_options and views_version were named
 * after the "views" noun rather than after the plugin, which is a name any
 * plugin could have taken; they are folded into the prefixed rows and deleted.
 * The two shared, unprefixed rows WP-Stats and five siblings met in are folded
 * in at the same time - see legacy_stats_display() for why an absent one has to
 * mean on.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads and writes the wp_postviews_options row.
 */
class WP_PostViews_Options {

	/**
	 * Name of the settings row.
	 *
	 * @var string
	 */
	const OPTION = 'wp_postviews_options';

	/**
	 * Row holding the 'plugin' and 'db' upgrade markers, and nothing else.
	 *
	 * @var string
	 */
	const VERSION = 'wp_postviews_version';

	/**
	 * The pre-2.0.0 settings row.
	 *
	 * @var string
	 */
	const LEGACY_OPTION = 'views_options';

	/**
	 * The pre-2.0.0 version marker, which held a plugin version string.
	 *
	 * @var string
	 */
	const LEGACY_VERSION = 'views_version';

	/**
	 * WP-Stats' shared, unprefixed toggle row, which seven plugins wrote into.
	 *
	 * @var string
	 */
	const LEGACY_STATS_DISPLAY = 'stats_display';

	/**
	 * WP-Stats' shared, unprefixed row saying how many "most" entries to list.
	 *
	 * @var string
	 */
	const LEGACY_STATS_MOST_LIMIT = 'stats_mostlimit';

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
		// On 'init' rather than 'admin_init': the 2.0.0 migration moves the row
		// itself, and until it has run the plugin is reading defaults over a
		// name nothing has written. Waiting for an administrator to load a
		// screen would leave every front end visitor looking at a stock template
		// in the meantime. The marker gate means this writes at most once.
		add_action( 'init', array( __CLASS__, 'maybe_upgrade' ), 1 );

		// Before 2.0.0 every read went straight to get_option(), so anything
		// that wrote the row - another plugin, WP-CLI, a migration script -
		// took effect immediately in the same request. The runtime cache below
		// would otherwise keep serving the value from before the write.
		add_action( 'add_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'flush' ) );
	}

	/**
	 * Keys the plugin used to store and no longer does.
	 *
	 * Dropped wherever the row is written, so a retired setting cannot survive
	 * the "keep what the screen did not render" merge in the sanitiser, nor ride
	 * in on the legacy row the 2.0.0 migration folds in.
	 *
	 * The six display_* keys were a per-context matrix - home, single, page,
	 * archive, search, other, each of them "everyone / registered only / never".
	 * It is one filter now, wp_postviews_should_display, so there is nothing left
	 * to store.
	 *
	 * @return array
	 */
	public static function retired_keys() {
		return array(
			'display_home',
			'display_single',
			'display_page',
			'display_archive',
			'display_search',
			'display_other',
		);
	}

	/**
	 * Default value for every key.
	 *
	 * These mirror the pre-2.0.0 activation routine, apart from the two WP-Stats
	 * keys, which had no default of their own before because they lived in
	 * somebody else's row, and the six retired_keys(), which are not stored at
	 * all any more. Changing any of them silently changes what a fresh install
	 * looks like.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'count'                => 1,
			'exclude_bots'         => 0,
			'use_ajax'             => 1,
			'template'             => self::default_template( 'template' ),
			'most_viewed_template' => self::default_template( 'most_viewed_template' ),
			// Whether WP-PostViews contributes a section to the WP-Stats page,
			// and how many entries its "most viewed" lists carry. Owned here
			// rather than in the row seven plugins used to write into at once,
			// so no plugin can read or clobber another's toggle. On by default,
			// which is what the shared row defaulted to.
			'stats_display'        => true,
			'stats_most_limit'     => 10,
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
			return '<li><a href="%POST_URL%" title="%POST_TITLE%">%POST_TITLE%</a> - %VIEW_COUNT% ' . __( 'views', 'wp-postviews' ) . '</li>';
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
	 * Retired keys are dropped here rather than only in the sanitiser, because
	 * this is the path the 2.0.0 migration writes through and register_setting()
	 * has not run by then: maybe_upgrade() is on 'init' and the Settings API
	 * registration is on 'admin_init', so the sanitize_option_ filter that would
	 * otherwise clean them is not attached yet, and on the front end never is.
	 *
	 * The two templates are filtered here for exactly the same reason, and it is
	 * the more serious half of it. Three places echo them raw, each carrying a
	 * phpcs:ignore justified by "kses'd on save" -- and that was true only of the
	 * Settings API path. The 2.0.0 migration lands here instead, and the row it
	 * folds in comes from a release that stored `trim( $_POST[...] )` with no
	 * filtering of any kind, so a hostile template on a site upgrading from
	 * 1.78.1 was copied across verbatim and echoed to every visitor by a plugin
	 * that believed it had been cleaned. WP-CLI, cron, a restored backup and any
	 * other caller reach the same door.
	 *
	 * `update_option()` declines to write a value equal to the one `get_option()`
	 * would return, and `register_setting()` is passed a `default`, which installs
	 * a `default_option_wp_postviews_options` filter answering with the shipped
	 * defaults for a row that does not exist. So a migration whose result happens
	 * to equal the defaults -- the commonest install there is -- writes nothing at
	 * all, the row is never created, and the markers are stamped complete either
	 * way, so the upgrade can never run again. Core's own `add_option()` fallback
	 * sits immediately below that comparison and is unreachable once the two
	 * compare equal.
	 *
	 * It was held off by hook order alone: this runs on `init` and the filter is
	 * registered on `admin_init`. One `show_in_rest`, one registration moved
	 * earlier, or any third-party `default_option_*` filter reaches it, silently,
	 * after the legacy row has already been deleted.
	 *
	 * And it was hidden by an accident. `filter_templates()` runs kses, which
	 * collapsed a doubled space that the most viewed template's default carried
	 * before 2.0.0 -- so the value written differed from the defaults by that one
	 * character, the comparison found a difference, and the row was written. The
	 * migration test that pins this passed because of a typo in a template.
	 *
	 * Passing an explicit default to `get_option()` defeats the registered one --
	 * `filter_default_option()` returns early when a default was passed -- which is
	 * what lets an absent row be told apart from a defaulted one and added
	 * outright. `add_option()` runs the sanitize callback exactly as
	 * `update_option()` does, so nothing else about the stored value changes.
	 *
	 * @param array $values Full option array.
	 * @return bool
	 */
	public static function save( $values ) {
		$values      = array_diff_key( (array) $values, array_flip( self::retired_keys() ) );
		$values      = self::filter_templates( $values );
		self::$cache = array_merge( self::defaults(), $values );

		if ( false === get_option( self::OPTION, false ) ) {
			return add_option( self::OPTION, self::$cache );
		}

		return update_option( self::OPTION, self::$cache );
	}

	/**
	 * The settings whose value is echoed as markup.
	 *
	 * @since 2.0.0
	 *
	 * @return string[]
	 */
	public static function template_keys() {
		return array( 'template', 'most_viewed_template' );
	}

	/**
	 * Run the template settings through kses.
	 *
	 * The invariant the raw echoes depend on, held by the storage layer rather
	 * than by one of the several ways a value can arrive at it.
	 *
	 * @since 2.0.0
	 *
	 * @param array $values Option array, whole or partial.
	 * @return array
	 */
	public static function filter_templates( $values ) {
		foreach ( self::template_keys() as $key ) {
			if ( isset( $values[ $key ] ) && is_scalar( $values[ $key ] ) ) {
				$values[ $key ] = wp_kses_post( trim( (string) $values[ $key ] ) );
			}
		}

		return $values;
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
	 * The two upgrade markers, normalised.
	 *
	 * Always exactly those two keys whatever the row holds, so no caller has to
	 * guard against a partial or absent value.
	 *
	 * @return array
	 */
	public static function markers() {
		$stored = get_option( self::VERSION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		return array(
			'plugin' => isset( $stored['plugin'] ) ? (string) $stored['plugin'] : '',
			'db'     => isset( $stored['db'] ) ? (string) $stored['db'] : '',
		);
	}

	/**
	 * Record both markers in one write.
	 *
	 * One write, both markers, at the end of the upgrade routine, so a
	 * half-finished upgrade never records itself as complete.
	 *
	 * @return void
	 */
	public static function update_markers() {
		update_option(
			self::VERSION,
			array(
				'plugin' => WP_POSTVIEWS_VERSION,
				'db'     => WP_POSTVIEWS_DB_VERSION,
			)
		);
	}

	/**
	 * Every option row this plugin owns, for uninstall.
	 *
	 * The two legacy names are included so uninstalling after an upgrade that
	 * never ran still clears them. The shared WP-Stats rows are deliberately
	 * absent: they were never this plugin's alone, and a sibling that has not
	 * upgraded yet is still reading them.
	 *
	 * @return array
	 */
	public static function all_option_names() {
		return array(
			self::OPTION,
			self::VERSION,
			self::LEGACY_OPTION,
			self::LEGACY_VERSION,
			'widget_views',
			// Retired long ago, but still on disk wherever it was written.
			'widget_views_most_viewed',
		);
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
	 * Run any outstanding data migration and record the markers.
	 *
	 * Activation does not fire on plugin *update*, which is the usual reason a
	 * migration never runs, so this is driven from 'init' as well.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		$markers = self::markers();

		if ( WP_POSTVIEWS_VERSION === $markers['plugin'] && WP_POSTVIEWS_DB_VERSION === $markers['db'] ) {
			return;
		}

		// Gated on the recorded marker rather than on "do the stored templates
		// still contain backslashes". A template with no apostrophe in it is
		// indistinguishable before and after, so a content check would rerun
		// the migration forever on some sites and never mark others done.
		if ( '' === $markers['plugin'] || version_compare( $markers['plugin'], '2.0.0', '<' ) ) {
			self::migrate();
		}

		self::update_markers();
	}

	/**
	 * Fold the four pre-2.0.0 rows into wp_postviews_options and delete them.
	 *
	 * The row views_options held the settings under a name that belonged to no
	 * plugin in particular. views_version held a plugin version string, which the
	 * marker row replaces. And stats_display and stats_mostlimit were shared with
	 * WP-Stats and five other plugins, so each of the seven keeps its own copy of
	 * them now.
	 *
	 * @return void
	 */
	public static function migrate() {
		self::flush();

		// Seeded from all() rather than from defaults(), so a site that has
		// already been migrated - or one that wrote the new row by hand - is not
		// reset by a second run.
		$values = self::all();

		$legacy_row = get_option( self::LEGACY_OPTION, null );
		if ( is_array( $legacy_row ) ) {
			$values = array_merge( $values, $legacy_row );
		}

		$values = self::migrate_template_slashes( $values );
		$values = self::migrate_stats_settings( $values );

		self::save( $values );

		foreach ( array( self::LEGACY_OPTION, self::LEGACY_VERSION, self::LEGACY_STATS_DISPLAY, self::LEGACY_STATS_MOST_LIMIT ) as $legacy_name ) {
			delete_option( $legacy_name );
		}
	}

	/**
	 * Unslash the two stored templates, once.
	 *
	 * Up to 1.78.1 the settings screen wrote $_POST straight through without
	 * wp_unslash(), so templates were stored slashed and every read path undid
	 * that with stripslashes(). 2.0.0 unslashes on save instead, which means the
	 * read paths must stop stripping - and that in turn means the rows already
	 * on disk have to be corrected once.
	 *
	 * Doing it the other way round, keeping stripslashes() on read, would eat a
	 * backslash the user genuinely wanted: a template containing a Windows path
	 * arrives unslashed, gets stripped again on output and loses the separator.
	 *
	 * Which is why this consults the legacy marker rather than just stripping.
	 * An install that ran an earlier 2.0.0 build already stores its templates
	 * unslashed, and stripping those a second time is exactly the bug above.
	 *
	 * @param array $values Settings assembled so far.
	 * @return array
	 */
	public static function migrate_template_slashes( $values ) {
		$legacy_version = get_option( self::LEGACY_VERSION, '' );

		if ( is_string( $legacy_version ) && '' !== $legacy_version && version_compare( $legacy_version, '2.0.0', '>=' ) ) {
			return $values;
		}

		foreach ( array( 'template', 'most_viewed_template' ) as $key ) {
			if ( isset( $values[ $key ] ) && is_string( $values[ $key ] ) ) {
				$values[ $key ] = stripslashes( $values[ $key ] );
			}
		}

		return $values;
	}

	/**
	 * Take this plugin's share of the two rows it used to hold jointly.
	 *
	 * @param array $values Settings assembled so far.
	 * @return array
	 */
	public static function migrate_stats_settings( $values ) {
		// An ABSENT row means a sibling upgraded first and deleted it, not that
		// the site switched this block off: all seven migrations delete these
		// two, so only the first one to run ever sees them. Reading absence as
		// an opt-out would make the views block vanish from the WP-Stats page,
		// silently and with nothing in any log to explain it, on every site that
		// happened to update WP-Stats before WP-PostViews. The worst case under
		// this rule is a block the owner switches off again.
		$values['stats_display'] = self::legacy_stats_display( get_option( self::LEGACY_STATS_DISPLAY, null ) );

		// The limit is only a number and its default is a sensible one, so an
		// absent row simply leaves the default in place.
		$legacy_limit = get_option( self::LEGACY_STATS_MOST_LIMIT, null );
		if ( null !== $legacy_limit && false !== $legacy_limit && '' !== $legacy_limit ) {
			$values['stats_most_limit'] = max( 1, (int) $legacy_limit );
		}

		return $values;
	}

	/**
	 * Whether the shared WP-Stats row had the views block switched on.
	 *
	 * The row is an array keyed per plugin, not a bare boolean: a site chose
	 * which blocks to show, and casting a non-empty array to bool would turn
	 * back on a block its owner had deliberately switched off. Both array shapes
	 * are in the wild - array( 'views' => 1 ), which is what the checkbox posts
	 * once WP-Stats has normalised it, and array( 'views' ), which is what a
	 * bare name="stats_display[]" posts - so both are read here.
	 *
	 * @param mixed $legacy The stats_display row as stored, or null if absent.
	 * @return bool
	 */
	public static function legacy_stats_display( $legacy ) {
		if ( null === $legacy ) {
			// A sibling has already migrated it away. Absent means on.
			return true;
		}

		if ( ! is_array( $legacy ) ) {
			return (bool) $legacy;
		}

		if ( array_key_exists( 'views', $legacy ) ) {
			return (bool) $legacy['views'];
		}

		return in_array( 'views', $legacy, true );
	}
}
