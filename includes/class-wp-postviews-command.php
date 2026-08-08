<?php
/**
 * The WP-CLI command.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reports how often posts have been read, from the command line.
 *
 * Registered as `wp postviews` rather than `wp wp-postviews`. A `wp-` prefix is
 * a wordpress.org directory convention for keeping one plugin's download page
 * apart from another's, not a command-naming one, and after the `wp` binary it
 * only stutters; WP-CLI's own ecosystem names commands after the brand -- `wp
 * wc`, `wp yoast`, `wp jetpack`. WP-CLI gives a collision to whichever plugin
 * registered last and a bare noun is claimable by anyone; that is the accepted
 * trade for a word as distinctive as `postviews`.
 *
 * **This command reads and never writes, and the omission is deliberate.** The
 * plugin's admin adds a sortable Views column and nothing else -- there is no
 * screen anywhere that edits a count. A `set` or `reset` subcommand would be a
 * power the browser has never given anybody, invented so the command looks more
 * substantial, and a view count that can be typed is no longer a record of
 * anything. `wp post meta update <id> views <n>` is still there for whoever
 * genuinely needs it, and says plainly that it is reaching past the plugin.
 */
class WP_PostViews_Command extends WP_CLI_Command {

	/**
	 * List the most viewed posts.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<number>]
	 * : How many posts to list.
	 * ---
	 * default: 10
	 * ---
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - count
	 *   - ids
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     # The ten most read posts.
	 *     $ wp postviews list
	 *
	 *     # The top fifty, as JSON.
	 *     $ wp postviews list --limit=50 --format=json
	 *
	 * @subcommand list
	 *
	 * @param array $args       Positional arguments. Unused.
	 * @param array $assoc_args Flags passed to the command.
	 * @return void
	 */
	public function list_( $args, $assoc_args ) {
		unset( $args );

		$limit  = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 10;
		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';

		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'publish',
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				// Filtering on a count above zero rather than on the meta key
				// existing, because **this plugin seeds `views` at zero when a
				// post is published** -- WP_PostViews_Core::seed_views_meta() is
				// hooked for exactly that. Every post therefore has the key, and
				// "has been viewed" is a question about the value.
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Selecting the viewed posts is the whole query.
					'viewed' => array(
						'key'     => 'views',
						'value'   => 0,
						'compare' => '>',
						'type'    => 'NUMERIC',
					),
				),
				'orderby'        => array( 'viewed' => 'DESC' ),
			)
		);

		if ( empty( $posts ) ) {
			// Nothing viewed yet is an answer, not a failure.
			WP_CLI::success( __( 'No posts have been viewed yet.', 'wp-postviews' ) );

			return;
		}

		$rows = array();

		foreach ( $posts as $post_id ) {
			$rows[] = array(
				'id'    => (int) $post_id,
				'title' => get_the_title( $post_id ),
				'views' => (int) get_post_meta( $post_id, 'views', true ),
			);
		}

		$this->print_rows( $format, $rows, array( 'id', 'title', 'views' ) );
	}

	/**
	 * Show one post's view count.
	 *
	 * ## OPTIONS
	 *
	 * <post-id>
	 * : The post to read.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp postviews get 42
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Flags passed to the command. Unused.
	 * @return void
	 */
	public function get( $args, $assoc_args ) {
		unset( $assoc_args );

		$post_id = (int) $args[0];

		if ( $post_id <= 0 || ! get_post_status( $post_id ) ) {
			/* translators: %d: the post id that was asked for. */
			WP_CLI::error( sprintf( __( 'No post with id %d.', 'wp-postviews' ), $post_id ) );
		}

		WP_CLI::log( (string) (int) get_post_meta( $post_id, 'views', true ) );
	}

	/**
	 * Print rows, reducing to the first column when ids were asked for.
	 *
	 * WP_CLI\Utils\format_items() hands `ids` straight to implode(), so a row
	 * that is an associative array comes out as the word `Array` and a PHP
	 * warning -- which is what `--format=ids` did here until it was run against
	 * a real WP-CLI rather than reasoned about. Reducing to the first column is
	 * what makes the documented `| xargs` pipes work.
	 *
	 * @param string $format Requested output format.
	 * @param array  $rows   Rows to print.
	 * @param array  $fields Columns, in order. The first is the id.
	 * @return void
	 */
	private function print_rows( $format, array $rows, array $fields ) {
		if ( 'ids' === $format ) {
			WP_CLI\Utils\format_items( $format, wp_list_pluck( $rows, $fields[0] ), $fields );

			return;
		}

		WP_CLI\Utils\format_items( $format, $rows, $fields );
	}
}
