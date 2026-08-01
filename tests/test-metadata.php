<?php
/**
 * The checks every one of the nineteen plugins carries.
 *
 * They are about the things a release gets wrong quietly: a readme header that
 * lost its line breaks and renders as one run-on paragraph on wordpress.org, a
 * version that agrees with itself in two places out of three, an option row that
 * outlives the plugin that made it. None of them need a browser and none of them
 * need judgement, so they belong in the suite rather than in a pre-release
 * checklist nobody reads.
 *
 * @package WP-PostViews
 */

/**
 * Plugin metadata, layout and the option rows.
 */
class WP_PostViews_Metadata_Test extends WP_PostViews_TestCase {

	/**
	 * The nine README header fields, in order.
	 *
	 * @var array
	 */
	private static $header_fields = array(
		'Contributors:',
		'Donate link:',
		'Tags:',
		'Requires at least:',
		'Tested up to:',
		'Stable tag:',
		'Requires PHP:',
		'License:',
		'License URI:',
	);

	/**
	 * The h2 headings a README may carry, in the order they must appear.
	 *
	 * @var array
	 */
	private static $sections = array(
		'## Description',
		'## Usage',
		'## Frequently Asked Questions',
		'## Screenshots',
		'## Changelog',
		'## Upgrade Notice',
	);

	/**
	 * The README, as shipped.
	 *
	 * @return string
	 */
	private function readme() {
		return (string) file_get_contents( dirname( __DIR__ ) . '/README.md' );
	}

	/**
	 * The main plugin file, as shipped.
	 *
	 * @return string
	 */
	private function plugin_file() {
		return (string) file_get_contents( dirname( __DIR__ ) . '/wp-postviews.php' );
	}

	/**
	 * One value from the README header.
	 *
	 * @param string $field Field name, including the colon.
	 * @return string
	 */
	private function readme_field( $field ) {
		preg_match( '/^' . preg_quote( $field, '/' ) . '\s*(.+?)\s*$/m', $this->readme(), $matches );

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	/**
	 * One value from the plugin file header.
	 *
	 * @param string $field Field name, including the colon.
	 * @return string
	 */
	private function header_field( $field ) {
		preg_match( '/^\s*\*\s*' . preg_quote( $field, '/' ) . '\s*(.+?)\s*$/m', $this->plugin_file(), $matches );

		return isset( $matches[1] ) ? $matches[1] : '';
	}

	// --- the README header ------------------------------------------------

	/**
	 * Eight of the nine header lines end in two spaces, and the ninth does not.
	 *
	 * Markdown turns a line ending in two spaces into a line break. Without them
	 * wordpress.org renders the whole header as one paragraph, which is the single
	 * most common thing to get wrong in a plugin readme.
	 *
	 * @return void
	 */
	public function test_every_readme_header_line_keeps_its_line_break() {
		$lines  = explode( "\n", $this->readme() );
		$header = array();

		foreach ( array_slice( $lines, 1 ) as $line ) {
			if ( '' === trim( $line ) ) {
				break;
			}

			$header[] = $line;
		}

		$this->assertCount( 9, $header, 'the README header should be exactly nine fields' );

		foreach ( self::$header_fields as $index => $field ) {
			$this->assertStringStartsWith( $field, trim( $header[ $index ] ), 'header field ' . ( $index + 1 ) . ' is out of order' );
		}

		foreach ( array_slice( $header, 0, 8 ) as $line ) {
			$this->assertStringEndsWith( '  ', $line, trim( $line ) . ' has lost its two trailing spaces' );
		}

		$last = $header[8];
		$this->assertSame( rtrim( $last ), $last, 'the last header line must not have trailing spaces' );
	}

	/**
	 * Exactly five tags, as §3.2 requires.
	 *
	 * @return void
	 */
	public function test_the_readme_carries_exactly_five_tags() {
		$tags = array_filter( array_map( 'trim', explode( ',', $this->readme_field( 'Tags:' ) ) ) );

		$this->assertCount( 5, $tags, 'wordpress.org shows five tags: ' . $this->readme_field( 'Tags:' ) );
	}

	/**
	 * Every URL in the metadata points at lesterchan.net or the GNU licence.
	 *
	 * @return void
	 */
	public function test_canonical_lesterchan_urls() {
		$this->assertSame( 'https://lesterchan.net/portfolio/programming/php/', $this->header_field( 'Plugin URI:' ) );
		$this->assertSame( 'https://lesterchan.net', $this->header_field( 'Author URI:' ) );
		$this->assertSame( 'https://lesterchan.net/site/donation/', $this->readme_field( 'Donate link:' ) );
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $this->readme_field( 'License URI:' ) );
		$this->assertSame( 'https://www.gnu.org/licenses/gpl-2.0.html', $this->header_field( 'License URI:' ) );
	}

	/**
	 * The Contributors field names one person and no one else.
	 *
	 * @return void
	 */
	public function test_contributors_is_gamerz_only() {
		$this->assertSame( 'GamerZ', $this->readme_field( 'Contributors:' ) );
	}

	/**
	 * The text domain is the slug, and the translations live in /languages.
	 *
	 * @return void
	 */
	public function test_text_domain_is_the_plugin_slug() {
		$this->assertSame( 'wp-postviews', $this->header_field( 'Text Domain:' ) );
		$this->assertSame( '/languages', $this->header_field( 'Domain Path:' ) );
		$this->assertSame( 'wp-postviews', WP_POSTVIEWS_SLUG );
	}

	/**
	 * The version agrees in the header, the README and the constant.
	 *
	 * @return void
	 */
	public function test_version_matches_everywhere() {
		$this->assertSame( WP_POSTVIEWS_VERSION, $this->header_field( 'Version:' ) );
		$this->assertSame( WP_POSTVIEWS_VERSION, $this->readme_field( 'Stable tag:' ) );
	}

	/**
	 * The supported floors agree between the header and the README.
	 *
	 * @return void
	 */
	public function test_requires_headers_match_readme() {
		$this->assertSame( '6.8', $this->header_field( 'Requires at least:' ) );
		$this->assertSame( '6.8', $this->readme_field( 'Requires at least:' ) );
		$this->assertSame( '8.2', $this->header_field( 'Requires PHP:' ) );
		$this->assertSame( '8.2', $this->readme_field( 'Requires PHP:' ) );
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
		$this->assertSame( 'GPLv2 or later', $this->header_field( 'License:' ) );
		$this->assertStringContainsString(
			'either version 2 of the License, or',
			$this->plugin_file(),
			'the GPL block is the v2-only variant, which contradicts the header above it'
		);
	}

	// --- the README body --------------------------------------------------

	/**
	 * The level two headings are the canonical set, in the canonical order.
	 *
	 * Level three headings are free; this is only about the h2s.
	 *
	 * @return void
	 */
	public function test_readme_sections_are_the_canonical_set() {
		preg_match_all( '/^## .+$/m', $this->readme(), $matches );

		$found = array_map( 'rtrim', $matches[0] );

		foreach ( $found as $heading ) {
			$this->assertContains( $heading, self::$sections, $heading . ' is not one of the canonical headings' );
		}

		$expected = array_values(
			array_filter(
				self::$sections,
				static function ( $section ) use ( $found ) {
					return in_array( $section, $found, true );
				}
			)
		);

		$this->assertSame( $expected, $found, 'the sections are out of order' );

		foreach ( array( '## Description', '## Changelog', '## Upgrade Notice' ) as $required ) {
			$this->assertContains( $required, $found, $required . ' is missing' );
		}
	}

	/**
	 * Donations is the last h3 of the Description, with the agreed wording.
	 *
	 * @return void
	 */
	public function test_donations_closes_the_description() {
		$readme      = $this->readme();
		$description = substr( $readme, strpos( $readme, '## Description' ), strpos( $readme, '## Usage' ) - strpos( $readme, '## Description' ) );

		preg_match_all( '/^### .+$/m', $description, $matches );

		$this->assertNotEmpty( $matches[0], 'the Description carries no h3 at all' );
		$this->assertSame( '### Donations', rtrim( end( $matches[0] ) ), 'Donations must be the last h3 of the Description' );
		$this->assertStringContainsString(
			'I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.',
			$description,
			'the Donations wording has drifted'
		);
		$this->assertStringNotContainsString( '* I spent most', $description, 'Donations is a paragraph, not a bullet' );
	}

	/**
	 * Every changelog bullet opens with one of the five allowed prefixes.
	 *
	 * @return void
	 */
	public function test_changelog_prefixes_are_canonical() {
		$readme    = $this->readme();
		$changelog = substr( $readme, strpos( $readme, '## Changelog' ) );
		$end       = strpos( $changelog, '## Upgrade Notice' );
		$changelog = false === $end ? $changelog : substr( $changelog, 0, $end );

		preg_match_all( '/^\* (.*)$/m', $changelog, $matches );

		$this->assertNotEmpty( $matches[1], 'the changelog has no entries at all' );

		foreach ( $matches[1] as $entry ) {
			$this->assertMatchesRegularExpression(
				'/^(BREAKING|NEW|CHANGED|FIXED|NOTE): /',
				$entry,
				'changelog entry does not start with an allowed prefix: ' . $entry
			);
		}
	}

	/**
	 * Every hook this release renamed is named in the Upgrade Notice.
	 *
	 * A rename with no shim fails silently, so the notice is the only warning a
	 * site owner ever gets.
	 *
	 * @return void
	 */
	public function test_the_upgrade_notice_names_every_renamed_hook() {
		$readme = $this->readme();
		$notice = substr( $readme, strpos( $readme, '## Upgrade Notice' ) );

		foreach ( array( 'wp_postviews_the_views', 'wp_postviews_should_count', 'wp_postviews_increment_views', 'wp_postviews_increment_views_ajax' ) as $hook ) {
			$this->assertStringContainsString( $hook, $notice, $hook . ' is not mentioned in the Upgrade Notice' );
		}

		$this->assertStringContainsString( '6.8', $notice, 'the raised WordPress floor is not in the Upgrade Notice' );
		$this->assertStringContainsString( '8.2', $notice, 'the raised PHP floor is not in the Upgrade Notice' );
	}

	// --- what ships -------------------------------------------------------

	/**
	 * No script the plugin registers depends on jQuery.
	 *
	 * Both halves matter. A source grep alone passes a dependency array built at
	 * runtime, and a deps check alone passes a file that reaches for the global
	 * without declaring it.
	 *
	 * @return void
	 */
	public function test_no_jquery_is_enqueued() {
		WP_PostViews_Admin::enqueue_scripts();

		add_filter( 'wp_postviews_should_count', '__return_true' );
		$post_id = $this->make_post( array(), 5 );
		$this->set_context( array( 'is_single', 'is_singular' ), $post_id );
		WP_PostViews_Counter::enqueue();

		$handles = array( 'wp-postviews-admin' );
		if ( WP_PostViews_Counter::using_ajax() ) {
			$handles[] = 'wp-postviews-cache';
		}

		$scripts = wp_scripts();

		foreach ( $handles as $handle ) {
			$this->assertArrayHasKey( $handle, $scripts->registered, $handle . ' was not registered' );
			$this->assertSame( array(), $scripts->registered[ $handle ]->deps, $handle . ' declares a dependency; it should have none' );
		}

		// Comments stripped first. Both scripts carry a docblock recording that
		// they replaced a jQuery call in 1.78.1, and the history of the fix is
		// not the fix being absent. Match code, not English.
		foreach ( glob( dirname( __DIR__ ) . '/js/*.js' ) as $file ) {
			$code = (string) file_get_contents( $file );
			$code = (string) preg_replace( '#/\*.*?\*/|//[^\n]*#s', '', $code );

			$this->assertDoesNotMatchRegularExpression( '/\bjQuery\b|\$\(/', $code, basename( $file ) . ' uses jQuery' );
		}
	}

	/**
	 * Every shipped directory carries a silence-is-golden index.php.
	 *
	 * The iterator prunes rather than filters. A plain filter descends into
	 * node_modules and vendor before discarding them, which is slow enough to
	 * look like a hang.
	 *
	 * @return void
	 */
	public function test_every_directory_has_an_index_php() {
		$root = dirname( __DIR__ );
		// artifacts/ is Playwright's: traces, screenshots and the stored admin
		// session from a local run. It is gitignored and never shipped, so it
		// has no index.php and no business being walked here.
		$ignore = array( 'node_modules', 'vendor', '.git', '.github', 'languages', 'artifacts' );

		$directories = array( $root );

		$iterator = new RecursiveIteratorIterator(
			new RecursiveCallbackFilterIterator(
				new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ),
				static function ( $file ) use ( $ignore ) {
					return ! $file->isDir() || ! in_array( $file->getFilename(), $ignore, true );
				}
			),
			RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $file ) {
			if ( $file->isDir() ) {
				$directories[] = $file->getPathname();
			}
		}

		foreach ( $directories as $directory ) {
			$this->assertFileExists( $directory . '/index.php', str_replace( $root, '', $directory ) . '/ has no index.php' );
		}
	}

	/**
	 * The plugin ships no RTL stylesheet and registers no rtl style data.
	 *
	 * A second sheet is a second thing to keep in step, and it only ever existed
	 * because the first one used physical properties. WP-PostViews ships no CSS at
	 * all, so this is a guard against one arriving with an -rtl twin.
	 *
	 * @return void
	 */
	public function test_no_rtl_stylesheet_is_registered() {
		$root = dirname( __DIR__ );

		$this->assertEmpty( glob( $root . '/*-rtl.css' ), 'an RTL stylesheet is still shipped' );
		$this->assertEmpty( glob( $root . '/css/*-rtl.css' ), 'an RTL stylesheet is still shipped' );

		WP_PostViews_Admin::enqueue_scripts();

		foreach ( array( 'wp-postviews', 'wp-postviews-admin' ) as $handle ) {
			$this->assertFalse( wp_styles()->get_data( $handle, 'rtl' ), $handle . ' declares rtl style data' );
		}
	}

	// --- the option rows --------------------------------------------------

	/**
	 * Uninstalling leaves no row of this plugin's behind.
	 *
	 * Runs on a network too, where uninstall.php loops over every site and the
	 * single-site path is never taken.
	 *
	 * @return void
	 */
	public function test_uninstall_removes_every_option_row() {
		global $wpdb;

		WP_PostViews_Options::save( WP_PostViews_Options::defaults() );
		WP_PostViews_Options::update_markers();

		$second = null;
		if ( is_multisite() ) {
			$second = self::factory()->blog->create();

			switch_to_blog( $second );
			WP_PostViews_Options::install();
			restore_current_blog();
		}

		$this->run_uninstall();

		$surviving = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp\\_postviews\\_%'" );

		$this->assertSame( array(), $surviving, 'uninstall left rows behind: ' . implode( ', ', (array) $surviving ) );

		if ( is_multisite() ) {
			switch_to_blog( $second );
			$surviving = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp\\_postviews\\_%'" );
			restore_current_blog();

			$this->assertSame( array(), $surviving, 'uninstall stopped at the first site of the network' );
		}
	}

	/**
	 * The marker row holds exactly 'plugin' and 'db'.
	 *
	 * @return void
	 */
	public function test_version_row_holds_exactly_plugin_and_db() {
		WP_PostViews_Options::update_markers();

		$markers = get_option( WP_PostViews_Options::VERSION );

		$this->assertIsArray( $markers, 'the marker row should be an array' );
		$this->assertSame( array( 'plugin', 'db' ), array_keys( $markers ), 'the marker row carries exactly two keys' );
		$this->assertSame( WP_POSTVIEWS_VERSION, $markers['plugin'] );
		$this->assertSame( WP_POSTVIEWS_DB_VERSION, $markers['db'] );
	}

	/**
	 * The sanitizer never writes an upgrade marker into the settings row.
	 *
	 * This is the regression guard for the bug wp-useronline shipped: with the
	 * markers inside the settings array, every save had to rescue them by hand,
	 * and the one that forgot made the upgrade re-run on every request. It fails
	 * the moment somebody moves a marker back in.
	 *
	 * @return void
	 */
	public function test_settings_sanitizer_never_stores_version_markers() {
		$clean = WP_PostViews_Settings::sanitize(
			array(
				'count'      => '2',
				'version'    => '9.9.9',
				'db_version' => '99',
				'versions'   => array( 'plugin' => '9.9.9' ),
			)
		);

		foreach ( array( 'version', 'db_version', 'versions' ) as $key ) {
			$this->assertArrayNotHasKey( $key, $clean, $key . ' reached the settings row' );
		}

		WP_PostViews_Options::save( $clean );
		WP_PostViews_Options::update_markers();

		$this->assertSame( array( 'plugin', 'db' ), array_keys( (array) get_option( WP_PostViews_Options::VERSION ) ) );
	}

	/**
	 * Exactly two option rows are autoloaded, and they are the two §2.1 names.
	 *
	 * @return void
	 */
	public function test_the_plugin_owns_exactly_two_option_rows() {
		global $wpdb;

		WP_PostViews_Options::save( WP_PostViews_Options::defaults() );
		WP_PostViews_Options::update_markers();

		$rows = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'wp\\_postviews\\_%' ORDER BY option_name" );

		$this->assertSame(
			array( WP_PostViews_Options::OPTION, WP_PostViews_Options::VERSION ),
			$rows,
			'§2.1 allows a settings row and a marker row, plus volatile data this plugin does not keep'
		);
	}
	/**
	 * The plugin root, whatever the checkout is called.
	 *
	 * @return string
	 */
	protected function metadata_root() {
		return dirname( __DIR__ );
	}

	/**
	 * Every PHP file the plugin ships, concatenated.
	 *
	 * @return string
	 */
	protected function metadata_source() {
		$source = '';

		foreach ( (array) glob( $this->metadata_root() . '/*.php' ) as $file ) {
			$source .= (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own source in a test.
		}

		foreach ( (array) glob( $this->metadata_root() . '/includes/*.php' ) as $file ) {
			$source .= (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own source in a test.
		}

		return $this->without_comments( $source );
	}

	/**
	 * The same source with every comment removed.
	 *
	 * A test that greps the source for a call it does not want finds the comment
	 * explaining why the call is absent, and fails the plugin for documenting
	 * itself. wp-sweep says "There is no load_plugin_textdomain() call" and was
	 * failed for saying so. Tokenising is the only honest way to tell code from
	 * prose about code.
	 *
	 * @param string $source PHP source.
	 * @return string
	 */
	protected function without_comments( $source ) {
		$code = '';

		foreach ( token_get_all( $source ) as $token ) {
			if ( is_array( $token ) ) {
				if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
					continue;
				}
				$code .= $token[1];
				continue;
			}

			$code .= $token;
		}

		return $code;
	}

	/**
	 * The GPL text ships with the plugin.
	 *
	 * @return void
	 */
	public function test_the_gpl_licence_is_shipped() {
		$licence = (string) file_get_contents( $this->metadata_root() . '/LICENSE' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own licence in a test.

		$this->assertStringContainsString( 'GNU GENERAL PUBLIC LICENSE', $licence, 'The GPL text must ship with the plugin.' );
		$this->assertStringContainsString( 'Version 2, June 1991', $licence, 'The licence must be GPLv2, matching the plugin header.' );
	}

	/**
	 * The plugin header fields appear in the canonical order.
	 *
	 * @return void
	 */
	public function test_the_plugin_header_fields_are_in_the_canonical_order() {
		$expected = array(
			'Plugin Name',
			'Plugin URI',
			'Description',
			'Version',
			'Requires at least',
			'Requires PHP',
			'Author',
			'Author URI',
			'License',
			'License URI',
			'Text Domain',
			'Domain Path',
		);

		// The main file is named for the directory, which is what wordpress.org
		// installs it as.
		$main = $this->metadata_root() . '/' . basename( $this->metadata_root() ) . '.php';
		$this->assertFileExists( $main, 'The main plugin file is named after the plugin directory.' );

		preg_match( '#^<\?php\s*/\*\*(.+?)\*/#s', (string) file_get_contents( $main ), $matches ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading the plugin's own source in a test.
		$this->assertNotEmpty( $matches, 'The plugin file must open with a docblock header.' );

		preg_match_all( '/^\s*\*\s*([A-Z][A-Za-z ]*?):\s/m', $matches[1], $fields );

		$this->assertSame( $expected, $fields[1], 'Plugin header fields must appear in the canonical order.' );
	}

	/**
	 * The plugin leaves loading its translations to WordPress.
	 *
	 * @return void
	 */
	public function test_the_plugin_does_not_load_its_own_textdomain() {
		// WordPress has loaded translations for wordpress.org plugins itself
		// since 4.6, so a load_plugin_textdomain() call is dead weight that
		// also fires before the plugin is on the translation server.
		$this->assertStringNotContainsString(
			'load_plugin_textdomain',
			$this->metadata_source(),
			'WordPress loads the textdomain itself since 4.6.'
		);
	}

	/**
	 * No build, editor or translation artefacts ship.
	 *
	 * @return void
	 */
	public function test_no_abandoned_build_or_translation_artefacts_ship() {
		$root = $this->metadata_root();

		$this->assertFileDoesNotExist( $root . '/.travis.yml', 'CI is GitHub Actions.' );
		$this->assertFileDoesNotExist( $root . '/.wp-env.override.json', 'A personal wp-env override must not ship.' );
		$this->assertDirectoryDoesNotExist( $root . '/languages', 'translate.wordpress.org builds the catalogue.' );
		$this->assertDirectoryDoesNotExist( $root . '/.idea', 'Editor settings must not ship.' );

		foreach ( array( 'pot', 'po', 'mo' ) as $extension ) {
			$this->assertSame(
				array(),
				(array) glob( $root . '/*.' . $extension ),
				"No .{$extension} files: translate.wordpress.org builds the catalogue."
			);
		}
	}
}
