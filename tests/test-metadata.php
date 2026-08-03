<?php
/**
 * WP-PostViews' half of the metadata contract.
 *
 * The contract itself is Plugin_Metadata_TestCase, a byte-identical copy of
 * _standards/templates/helper-metadata-testcase.php that every one of the
 * nineteen plugins carries. Everything shared lives there. What is left here is
 * what a machine cannot derive from the directory, plus the few assertions that
 * are genuinely about this plugin: the two option rows it owns and nothing
 * else, the five readme tags, the licence block and the Donations paragraph.
 *
 * @package WP-PostViews
 */

/**
 * The shared contract, plus what only WP-PostViews can answer.
 */
class WP_PostViews_Metadata_Test extends Plugin_Metadata_TestCase {

	/**
	 * The version this release ships.
	 *
	 * Written out rather than read from WP_POSTVIEWS_VERSION, so a bump has to
	 * be made here as well and cannot happen by accident.
	 *
	 * @return string
	 */
	protected function expected_version() {
		return '2.0.0';
	}

	/**
	 * The prefix every class the plugin declares carries.
	 *
	 * @return string
	 */
	protected function class_prefix() {
		return 'WP_PostViews';
	}

	/**
	 * Everything a site owner updating from the released version would notice.
	 *
	 * Four renamed filters with no shim behind them, the two renamed option
	 * rows, the settings screen that moved, the three undocumented functions
	 * that became class methods, the renamed widget class, and the Display
	 * Options that are gone along with the filter that replaces them.
	 *
	 * @return string[]
	 */
	protected function upgrade_notice_subjects() {
		return array(
			'6.8',
			'8.2',
			'the_views',
			'wp_postviews_the_views',
			'wp_postviews_should_count',
			'wp_postviews_increment_views',
			'wp_postviews_increment_views_ajax',
			'wp_postviews_should_display',
			'views_options',
			'wp_postviews_options',
			'views_version',
			'wp_postviews_version',
			'postviews-options.php',
			'options-general.php?page=wp-postviews',
			'should_views_be_displayed()',
			'postviews_round_number()',
			'snippet_text()',
			'WP_Widget_PostViews',
			'WP_PostViews_Widget',
			'display_archive',
			'display_search',
		);
	}

	/**
	 * WP-PostViews is one of the seven sharing the WP-Stats surface.
	 *
	 * @return bool
	 */
	protected function wp_stats_family() {
		return true;
	}

	/**
	 * The unprefixed WP-Stats rows WP-PostViews reads but does not own.
	 *
	 * @return string[]
	 */
	protected function shared_wp_stats_rows() {
		return array(
			WP_PostViews_Options::LEGACY_STATS_DISPLAY,
			WP_PostViews_Options::LEGACY_STATS_MOST_LIMIT,
		);
	}

	/**
	 * Write the rows uninstall is expected to remove.
	 *
	 * @return void
	 */
	protected function seed_option_rows() {
		WP_PostViews_Options::save( WP_PostViews_Options::defaults() );
		WP_PostViews_Options::update_markers();
	}

	/**
	 * Write the wp_postviews_version marker row.
	 *
	 * @return void
	 */
	protected function write_version_row() {
		WP_PostViews_Options::update_markers();
	}

	/**
	 * Round-trip the settings sanitiser.
	 *
	 * @param array $input What the settings form is pretending to have posted.
	 * @return array
	 */
	protected function sanitize_settings( array $input ) {
		return (array) WP_PostViews_Settings::sanitize( $input );
	}

	/**
	 * A real settings key to send through the sanitiser beside the poison.
	 *
	 * @return array
	 */
	protected function settings_fixture() {
		return array( 'count' => '2' );
	}

	/**
	 * Register the admin script and, when it is in use, the counter's.
	 *
	 * The counter only enqueues on a single post that is being counted, so the
	 * request has to be put into that shape first or the handle never appears.
	 *
	 * @return void
	 */
	protected function register_plugin_assets() {
		WP_PostViews_Admin::enqueue_scripts();

		add_filter( 'wp_postviews_should_count', '__return_true' );

		$post_id = $this->make_post( array(), 5 );

		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );

		WP_PostViews_Counter::enqueue();
	}

	/**
	 * Exactly five tags, as §3.2 requires.
	 *
	 * The listing shows five and silently ignores the rest, so a sixth is work
	 * that does nothing.
	 *
	 * @return void
	 */
	public function test_the_readme_carries_exactly_five_tags() {
		$tags = array_filter( array_map( 'trim', explode( ',', $this->readme_field( 'Tags' ) ) ) );

		$this->assertCount( 5, $tags, 'wordpress.org shows five tags: ' . $this->readme_field( 'Tags' ) );
	}

	/**
	 * The licence statement does not contradict itself.
	 *
	 * The header says "or later" and composer.json says GPL-2.0-or-later, so the
	 * GPL block below them has to be the "or later" variant too.
	 *
	 * @return void
	 */
	public function test_the_gpl_block_is_the_or_later_variant() {
		$this->assertSame( 'GPLv2 or later', $this->header_field( 'License' ), 'The header licence is the or-later variant.' );
		$this->assertStringContainsString(
			'either version 2 of the License, or',
			$this->plugin_file(),
			'The GPL block is the v2-only variant, which contradicts the header above it.'
		);
	}

	/**
	 * Donations is the last h3 of the Description, with the agreed wording.
	 *
	 * @return void
	 */
	public function test_donations_closes_the_description() {
		$readme      = $this->readme();
		$start       = (int) strpos( $readme, '## Description' );
		$description = substr( $readme, $start, (int) strpos( $readme, '## Usage' ) - $start );

		preg_match_all( '/^### .+$/m', $description, $matches );

		$this->assertNotEmpty( $matches[0], 'The Description carries no h3 at all.' );
		$this->assertSame( '### Donations', rtrim( end( $matches[0] ) ), 'Donations must be the last h3 of the Description.' );
		$this->assertStringContainsString(
			'I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.',
			$description,
			'The Donations wording has drifted.'
		);
		$this->assertStringNotContainsString( '* I spent most', $description, 'Donations is a paragraph, not a bullet.' );
	}

	/**
	 * Exactly two option rows, and they are the two §2.1 names.
	 *
	 * The shared uninstall test proves nothing survives deletion; this proves
	 * nothing extra was created in the first place. WP-PostViews keeps no
	 * volatile data, so two rows is the whole of its footprint - a third
	 * appearing is a row somebody added without adding it to uninstall.
	 *
	 * @return void
	 */
	public function test_the_plugin_owns_exactly_two_option_rows() {
		$this->seed_option_rows();

		$rows = $this->stored_option_names();
		sort( $rows );

		$this->assertSame(
			array( WP_PostViews_Options::OPTION, WP_PostViews_Options::VERSION ),
			$rows,
			'§2.1 allows a settings row and a marker row, plus volatile data this plugin does not keep.'
		);
	}
}
