<?php
/**
 * Plumbing that is neither counting nor display: the ?v_sortby= query vars,
 * seeding the meta key on publish, and the REST field.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query var sorting, publish hook and REST exposure.
 */
class WP_PostViews_Core {

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'maybe_sort_by_views' ) );

		add_action( 'publish_post', array( __CLASS__, 'seed_views_meta' ) );
		add_action( 'publish_page', array( __CLASS__, 'seed_views_meta' ) );

		add_action( 'rest_api_init', array( __CLASS__, 'register_rest_field' ) );
	}

	/**
	 * Expose ?v_sortby=views&v_orderby=asc to the front end.
	 *
	 * @param array $public_query_vars Registered public query vars.
	 * @return array
	 */
	public static function query_vars( $public_query_vars ) {
		$public_query_vars[] = 'v_sortby';
		$public_query_vars[] = 'v_orderby';

		return $public_query_vars;
	}

	/**
	 * Attach or detach the sorting filters for a query.
	 *
	 * The detach half matters as much as the attach half: these are global
	 * filters, so leaving them on after the sorted query has run would join
	 * every later query on the request to postmeta.
	 *
	 * @param WP_Query $query The query being prepared.
	 * @return void
	 */
	public static function maybe_sort_by_views( $query ) {
		if ( 'views' === $query->get( 'v_sortby' ) ) {
			add_filter( 'posts_fields', array( __CLASS__, 'posts_fields' ) );
			add_filter( 'posts_join', array( __CLASS__, 'posts_join' ) );
			add_filter( 'posts_where', array( __CLASS__, 'posts_where' ) );
			add_filter( 'posts_orderby', array( __CLASS__, 'posts_orderby' ) );

			return;
		}

		remove_filter( 'posts_fields', array( __CLASS__, 'posts_fields' ) );
		remove_filter( 'posts_join', array( __CLASS__, 'posts_join' ) );
		remove_filter( 'posts_where', array( __CLASS__, 'posts_where' ) );
		remove_filter( 'posts_orderby', array( __CLASS__, 'posts_orderby' ) );
	}

	/**
	 * Select the view count alongside the post columns.
	 *
	 * @param string $content The SELECT list.
	 * @return string
	 */
	public static function posts_fields( $content ) {
		global $wpdb;

		return $content . ", ($wpdb->postmeta.meta_value+0) AS views";
	}

	/**
	 * Join postmeta.
	 *
	 * @param string $content The JOIN clause.
	 * @return string
	 */
	public static function posts_join( $content ) {
		global $wpdb;

		return $content . " LEFT JOIN $wpdb->postmeta ON $wpdb->postmeta.post_id = $wpdb->posts.ID";
	}

	/**
	 * Restrict the joined rows to the views key.
	 *
	 * @param string $content The WHERE clause.
	 * @return string
	 */
	public static function posts_where( $content ) {
		global $wpdb;

		return $content . " AND $wpdb->postmeta.meta_key = 'views'";
	}

	/**
	 * Order by the selected view count.
	 *
	 * The incoming clause is discarded rather than appended to: sorting by view
	 * count is the whole point of the query var, so it replaces whatever
	 * ordering was in place. The parameter list is therefore empty rather than
	 * naming an argument nothing reads - PHP passes the clause and the query
	 * object in and a callback simply ignores the extras.
	 *
	 * @return string
	 */
	public static function posts_orderby() {
		$orderby = strtolower( trim( (string) get_query_var( 'v_orderby' ) ) );

		// Validated against a fixed pair rather than escaped: this is
		// interpolated into ORDER BY, where neither prepare() nor an escape
		// helper can bind an identifier or a direction.
		if ( ! in_array( $orderby, array( 'asc', 'desc' ), true ) ) {
			$orderby = 'desc';
		}

		return " views $orderby";
	}

	/**
	 * Give a newly published post a zero view count.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	public static function seed_views_meta( $post_id ) {
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}

		// The $unique flag is what stops a republish resetting a real count.
		add_post_meta( $post_id, 'views', 0, true );
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
	 * Expose the count as a `views` field on the REST post resource.
	 *
	 * @return void
	 */
	public static function register_rest_field() {
		register_rest_field(
			'post',
			'views',
			array(
				'get_callback' => array( __CLASS__, 'rest_get_views' ),
				'schema'       => array(
					'description' => __( 'The number of times the post has been viewed.', 'wp-postviews' ),
					'type'        => 'integer',
					'context'     => array( 'view', 'edit', 'embed' ),
					'readonly'    => true,
				),
			)
		);
	}

	/**
	 * REST callback for the views field.
	 *
	 * @param array $post The post, as prepared for the response.
	 * @return int
	 */
	public static function rest_get_views( $post ) {
		return (int) get_post_meta( $post['id'], 'views', true );
	}
}
