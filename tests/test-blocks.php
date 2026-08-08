<?php
/**
 * Tests for the blocks.
 *
 * @package WP-PostViews
 */

/**
 * The block, and the promise that it is an addition rather than a replacement.
 *
 * Most of what is worth asserting here is not "the block renders" -- that is
 * one line -- but the four things a later change could quietly break:
 *
 * * the shortcode still works, because it sits in published posts everywhere;
 * * the block and the shortcode render the *same* markup, because they are
 *   meant to share one renderer and nothing else checks that they still do;
 * * neither entry point is implemented in terms of the other, which is what
 *   stops the shortcode's parsing quirks leaking into the block;
 * * rendering does not count. This plugin's whole subject is a number that goes
 *   up, and the one entry point an author triggers repeatedly while writing is
 *   the editor preview.
 */
class WP_PostViews_Blocks_Test extends WP_PostViews_TestCase {

	/**
	 * The shortcode table as it stood before a test edited it.
	 *
	 * @var array
	 */
	private $shortcodes;

	/**
	 * Snapshots the global state these tests deliberately break.
	 *
	 * Two tests below unregister the shortcode or the block on purpose, to
	 * prove neither entry point is implemented in terms of the other. Both
	 * registries are process-global and WP_UnitTestCase restores neither, so
	 * without this the first such test silently disarms every test that runs
	 * after it -- and they fail with `[views]` rendering as literal text, which
	 * reads as a broken shortcode rather than a leaky fixture.
	 */
	public function set_up() {
		parent::set_up();

		$this->shortcodes = $GLOBALS['shortcode_tags'];

		$this->restore_blocks();
	}

	/**
	 * Puts both registries back.
	 */
	public function tear_down() {
		$GLOBALS['shortcode_tags'] = $this->shortcodes;

		$this->restore_blocks();

		parent::tear_down();
	}

	/**
	 * Returns the block registry to exactly the registered blocks.
	 *
	 * Unregisters before registering rather than registering conditionally:
	 * the plugin has already registered on `init` by the time any test runs,
	 * and registering a second time is a doing_it_wrong notice that the suite
	 * fails on.
	 *
	 * @return void
	 */
	private function restore_blocks() {
		foreach ( array( 'wp-postviews/views' ) as $name ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $name ) ) {
				unregister_block_type( $name );
			}
		}

		WP_PostViews_Blocks::register();
	}

	// --- registration ----------------------------------------------------

	/**
	 * The block registers, under the prefixed name.
	 *
	 * The `wp-` prefix is deliberate and is the one place the naming rule for
	 * commands and namespaces does not carry: those drop it, because a
	 * collision there is survivable and visible. A block name is written into
	 * post_content and stays there for the life of the post, so a collision
	 * would render another plugin's block inside somebody's published posts.
	 *
	 * @return void
	 */
	public function test_the_block_registers_under_the_prefixed_name() {
		$registry = WP_Block_Type_Registry::get_instance();

		$this->assertTrue( $registry->is_registered( 'wp-postviews/views' ), 'The views block registers.' );

		$this->assertFalse( $registry->is_registered( 'postviews/views' ), 'The unprefixed name is not also claimed.' );
	}

	/**
	 * The block is dynamic, so it carries a render callback.
	 *
	 * Without one a block saves its markup into post_content, and the whole
	 * reason a shortcode and a block can share a renderer is that neither does.
	 * For a view count it is worse than untidy: the saved markup would be the
	 * number as it stood when the post was last edited, frozen for good.
	 *
	 * @return void
	 */
	public function test_the_block_is_dynamic() {
		$this->assertIsCallable(
			WP_Block_Type_Registry::get_instance()->get_registered( 'wp-postviews/views' )->render_callback,
			'The views block renders server-side.'
		);
	}

	/**
	 * The attributes come from block.json rather than from PHP.
	 *
	 * `[views]` takes exactly one attribute, so the block takes exactly one
	 * too: anything the shortcode can be asked for, the block can be asked for.
	 *
	 * @return void
	 */
	public function test_the_block_declares_the_shortcodes_one_attribute() {
		$attributes = WP_Block_Type_Registry::get_instance()->get_registered( 'wp-postviews/views' )->attributes;

		$this->assertArrayHasKey( 'id', $attributes, 'The block takes an id.' );
		$this->assertSame( 'number', $attributes['id']['type'], 'The id arrives typed, unlike a shortcode attribute.' );
		$this->assertSame( 0, $attributes['id']['default'], 'And defaults to zero, meaning the post it sits in.' );
	}

	// --- the shortcode survives -------------------------------------------

	/**
	 * Adding the block did not unregister the shortcode.
	 *
	 * If this ever fails, the block has stopped being an addition and become a
	 * replacement, and every published post holding `[views]` renders literal
	 * text.
	 *
	 * @return void
	 */
	public function test_the_shortcode_is_still_registered() {
		$this->assertTrue( shortcode_exists( 'views' ), 'The views shortcode survives the block.' );
	}

	// --- the block and the shortcode agree --------------------------------

	/**
	 * The block and the shortcode render the same count identically.
	 *
	 * This is the assertion the whole design rests on. Two entry points that
	 * merely both work can drift; two that produce byte-identical markup are
	 * demonstrably going through one renderer.
	 *
	 * @return void
	 */
	public function test_the_block_and_the_shortcode_render_the_same_markup() {
		$post_id = $this->make_post( array(), 4321 );

		$block     = WP_PostViews_Blocks::render_views( array( 'id' => $post_id ) );
		$shortcode = do_shortcode( '[views id="' . $post_id . '"]' );

		$this->assertStringContainsString( '4,321', $block, 'The block rendered the stored count.' );
		$this->assertSame( $shortcode, $block, 'And it is what the shortcode renders.' );
	}

	/**
	 * They agree about the template too, not only about the number.
	 *
	 * The template is a stored setting, so a renderer that read it in one entry
	 * point and hard-coded the wording in the other would still agree on the
	 * default. Changing it is what tells the two apart.
	 *
	 * @return void
	 */
	public function test_both_entry_points_use_the_stored_template() {
		$post_id = $this->make_post( array(), 7 );

		$this->set_options( array( 'template' => 'read %VIEW_COUNT% times' ) );
		WP_PostViews_Options::flush();

		$block = WP_PostViews_Blocks::render_views( array( 'id' => $post_id ) );

		$this->assertSame( 'read 7 times', $block, 'The block renders through the stored template.' );
		$this->assertSame( do_shortcode( '[views id="' . $post_id . '"]' ), $block, 'And so does the shortcode.' );
	}

	/**
	 * An id of zero means the post being rendered, in both entry points.
	 *
	 * Zero is the block's default and an empty `[views]`'s default, so the two
	 * have to mean the same thing or an attributeless block and an
	 * attributeless shortcode show different posts' counts.
	 *
	 * @return void
	 */
	public function test_a_zero_id_means_the_current_post_in_both() {
		$post_id = $this->make_post( array(), 88 );

		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		$block = WP_PostViews_Blocks::render_views( array() );

		$this->assertStringContainsString( '88', $block, 'An attributeless block reads the post being rendered.' );
		$this->assertSame( do_shortcode( '[views]' ), $block, 'And an attributeless shortcode reads the same one.' );
	}

	/**
	 * Both ignore the display gate.
	 *
	 * `wp_postviews_should_display` governs the theme's call to the_views().
	 * Putting a block into a post is as explicit a request for the number as
	 * typing the shortcode, so neither consults it -- and a guard that lived in
	 * only one of the two would make them disagree.
	 *
	 * @return void
	 */
	public function test_neither_entry_point_consults_the_display_gate() {
		$post_id = $this->make_post( array(), 13 );

		$this->hide_views();

		$this->assertStringContainsString( '13', WP_PostViews_Blocks::render_views( array( 'id' => $post_id ) ), 'The block renders despite the gate.' );
		$this->assertStringContainsString( '13', do_shortcode( '[views id="' . $post_id . '"]' ), 'And so does the shortcode.' );
	}

	// --- neither is implemented in terms of the other ---------------------

	/**
	 * The block does not render by running the shortcode.
	 *
	 * Routing a block through do_shortcode() would make it inherit shortcode
	 * parsing it has no way to produce, and would break it outright the day
	 * anybody unregistered the shortcode. So: unregister the shortcode, and
	 * assert the block carries on rendering.
	 *
	 * @return void
	 */
	public function test_the_block_renders_with_the_shortcode_unregistered() {
		$post_id = $this->make_post( array(), 55 );

		remove_shortcode( 'views' );

		$this->assertStringContainsString( '55', WP_PostViews_Blocks::render_views( array( 'id' => $post_id ) ), 'The block does not need the shortcode.' );
	}

	/**
	 * The shortcode does not render by running the block.
	 *
	 * The other direction of the same rule, and the one a later "tidy-up" is
	 * likelier to break, because making the shortcode a thin wrapper over the
	 * block reads as removing duplication.
	 *
	 * @return void
	 */
	public function test_the_shortcode_renders_with_the_block_unregistered() {
		$post_id = $this->make_post( array(), 66 );

		unregister_block_type( 'wp-postviews/views' );

		$this->assertStringContainsString( '66', do_shortcode( '[views id="' . $post_id . '"]' ), 'The shortcode does not need the block.' );
	}

	// --- rendering is not counting ----------------------------------------

	/**
	 * Rendering the block does not increment anybody's view count.
	 *
	 * The reason this is worth a test of its own rather than a comment: the
	 * editor renders a block preview on every attribute change, so a renderer
	 * that counted would let an author inflate a post's figures by dragging the
	 * block about, and the inflated number is indistinguishable from real
	 * traffic once it is in postmeta.
	 *
	 * The absence is structural -- counting hangs off `wp_head` and an
	 * `admin-ajax.php` POST, and neither fires for a block render -- but
	 * structure is exactly what a later change moves.
	 *
	 * @return void
	 */
	public function test_rendering_the_block_does_not_count_a_view() {
		$post_id = $this->make_post( array(), 100 );

		WP_PostViews_Blocks::render_views( array( 'id' => $post_id ) );
		WP_PostViews_Blocks::render_views( array( 'id' => $post_id ) );
		do_blocks( '<!-- wp:wp-postviews/views {"id":' . $post_id . '} /-->' );

		$this->assertSame( 100, (int) get_post_meta( $post_id, 'views', true ), 'Three renders later the count has not moved.' );
	}

	/**
	 * Nor does the shortcode, which is the same promise from the other side.
	 *
	 * @return void
	 */
	public function test_rendering_the_shortcode_does_not_count_a_view() {
		$post_id = $this->make_post( array(), 100 );

		do_shortcode( '[views id="' . $post_id . '"]' );

		$this->assertSame( 100, (int) get_post_meta( $post_id, 'views', true ), 'The shortcode reads the count and leaves it alone.' );
	}

	// --- rendering through the block parser -------------------------------

	/**
	 * A post holding the block comment renders the count.
	 *
	 * The tests above call the callback directly, which does not prove the
	 * registration wired it to the name that gets saved into post_content.
	 * This goes through do_blocks(), the way a published post does.
	 *
	 * @return void
	 */
	public function test_a_saved_block_renders_through_the_block_parser() {
		$post_id = $this->make_post( array(), 9012 );

		$rendered = do_blocks( '<!-- wp:wp-postviews/views {"id":' . $post_id . '} /-->' );

		$this->assertStringContainsString( '9,012', $rendered, 'The saved block renders its count.' );
	}
}
