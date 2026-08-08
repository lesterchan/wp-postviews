<?php
/**
 * Block registration.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers the plugin's blocks and renders them.
 *
 * **The block is added beside the shortcode, never in place of it.** `[views]`
 * stays registered, documented and supported, and nothing here deprecates it.
 * This plugin has shipped for fifteen years and that shortcode sits in an
 * unknowable number of published posts; a block that replaced it would break
 * every one of those pages on update.
 *
 * The block is dynamic. Its `save` returns null in JavaScript, so what lands in
 * post_content is the block comment and its attributes and no markup at all --
 * every view re-renders through the callback below. For a view count that is
 * not merely tidy: a count saved into post_content would be the number as it
 * stood the day the post was edited, and would never change again.
 *
 * **Neither entry point calls the other.** The block does not run
 * `do_shortcode()` and the shortcode does not ask this class for anything. They
 * are siblings over WP_PostViews_Display::render_views(), which is where the
 * rendering lives. Routing the block through `do_shortcode()` would make it
 * inherit the shortcode's attribute parsing, and would break the block outright
 * the day anybody unregistered the shortcode.
 *
 * **Rendering is not counting.** The callback reads the stored number and
 * returns the template around it. Recording a view is WP_PostViews_Counter's
 * job, hung off `wp_head` or a POST to `admin-ajax.php`, and a block previewing
 * itself through core's /wp/v2/block-renderer/ route reaches neither -- so an
 * author dragging the block around a draft cannot inflate anybody's figures.
 *
 * **One class registers all of a plugin's blocks**, rather than a class per
 * block: a class whose entire body is a single
 * `register_block_type_from_metadata()` call would be a file per block for no
 * gain. This plugin has one block today because it has one chunk-shaped
 * shortcode.
 */
class WP_PostViews_Blocks {

	/**
	 * Blocks this plugin registers, as build directory => render callback.
	 *
	 * The keys are directory names under `build/`, which is what
	 * `register_block_type_from_metadata()` reads: the name, title, attributes
	 * and script handle all come out of the `block.json` copied there by the
	 * build, so this class never restates any of them.
	 *
	 * @return array<string, callable>
	 */
	private static function blocks() {
		return array(
			'views' => array( __CLASS__, 'render_views' ),
		);
	}

	/**
	 * Hooks block registration.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
	}

	/**
	 * Registers every block against its built metadata.
	 *
	 * A missing `build/` directory means the plugin was installed without its
	 * build step having run -- a git checkout rather than a release zip, since
	 * `bin/build` runs before the deploy copies anything. Registering a block
	 * whose script cannot be enqueued gives an editor that silently fails to
	 * load it, so this returns instead and leaves the shortcode working.
	 *
	 * @return void
	 */
	public static function register() {
		foreach ( self::blocks() as $directory => $callback ) {
			$metadata = WP_POSTVIEWS_DIR . 'build/' . $directory;

			if ( ! file_exists( $metadata . '/block.json' ) ) {
				continue;
			}

			register_block_type_from_metadata(
				$metadata,
				array( 'render_callback' => $callback )
			);
		}
	}

	/**
	 * Renders the `wp-postviews/views` block.
	 *
	 * The attribute arrives typed, because block.json declares it: `id` is
	 * already an integer here where the shortcode's is the string a user typed.
	 * Both are cast in the shared renderer, so the two entry points cannot
	 * disagree about what `id="0"` means.
	 *
	 * @param array $attributes Block attributes.
	 *
	 * @return string
	 */
	public static function render_views( $attributes ) {
		$attributes = wp_parse_args( $attributes, array( 'id' => 0 ) );

		return WP_PostViews_Display::render_views( $attributes['id'] );
	}
}
