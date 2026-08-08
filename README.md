# WP-PostViews
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: views, hits, counter, postviews, statistics  
Requires at least: 6.8  
Tested up to: 7.0  
Stable tag: 2.0.0  
Requires PHP: 8.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enables you to display how many times a post/page had been viewed.

## Description

WP-PostViews counts how many times each post, page or custom post type has been read and gives you somewhere to show the number. The count is kept as post meta, so it sorts, queries and exports like anything else WordPress stores about a post.

### Features

* A view count on any post, page or custom post type, printed by a template tag or by the `[views]` shortcode.
* Two templates you edit yourself, with tokens for the count, the title, the date, the excerpt, the thumbnail, the author and more.
* Choose who is counted -- everyone, guests only or logged in users only -- and leave known robots out.
* Template tags and a widget for the most and least viewed posts, optionally within a category or a tag.
* A sortable Views column on the post and page list tables.
* The count on the REST API as a `views` field, and an AJAX counting path for sites behind a page cache.
* A section on the WP-Stats page when that plugin is installed.

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Installation

1. Install and activate the plugin. Counting starts immediately; you do not have to do anything for the numbers to be recorded.
1. Show the count where you want it: type `[views]` into a post, add the **Post Views** block, add the widget, or call `the_views()` from your theme.
1. Go to `WP-Admin -> Settings -> WP-PostViews` to choose who is counted and how the number reads.

## Usage

The simplest way, and the only one that works in a block theme without editing template files, is the shortcode. Put it in the post or page whose count you want shown:

* `[views]` shows the count for the post it appears in.
* `[views id="1"]` shows the count for post 1, wherever you put it.

To show the count on every post automatically, a classic theme calls the template tag from `index.php`, `archive.php`, `single.php` or `page.php`, anywhere inside the loop:

```php
<?php if ( function_exists( 'the_views' ) ) { the_views(); } ?>
```

The settings live at **WP-Admin -> Settings -> WP-PostViews**, on two tabs. **Settings** is where you choose who gets counted and whether WP-Stats is offered a Views section; **Templates** is where you edit the markup a count is rendered with.

Where the count appears is decided by where your theme calls `the_views()` or where you put the shortcode. To hide it somewhere in particular, answer the `wp_postviews_should_display` filter:

```php
add_filter( 'wp_postviews_should_display', function ( $show ) {
	return ! is_archive() && ! is_search();
} );
```

### Showing The View Count In A Block

One block is available in the editor, under **Widgets**:

* **Post Views** — the view count, rendered with the template from the Templates tab. Leave **Post ID** at zero to show the count of the post the block is in, which is what an empty `[views]` does, or set it to another post's ID to show that post's count instead.

It renders on the server, so the block preview in the editor is the real number rather than an approximation, and the count keeps rising in every post showing it without anything being re-saved. Previewing the block in the editor does not count a view.

**The shortcode still works and is not going anywhere.** `[views]` and `[views id="1"]` behave exactly as they always have, and a post already containing one needs no change. The block calls the same code the shortcode calls, so the two render identically — use whichever suits the post.

### WP-CLI
```
wp postviews list
wp postviews list --limit=50 --format=json
wp postviews get 42
```

The command reads and never writes. No screen in this plugin edits a view count, so the command does not offer one either — `wp post meta update <id> views <n>` is still there for whoever genuinely needs it, and says plainly that it is reaching past the plugin.

### REST API
```
POST /wp-json/postviews/v1/post/<id>/view
```

**Reading a count needs no route of its own.** The count is already published as a read-only `views` field on the core post resource, so `/wp-json/wp/v2/posts/<id>` answers with the post and its count together, and a list of posts carries every count in one response.

What the core field cannot do is write, which is what this route is for: it counts a view, and it exists for sites serving cached pages, where the view has to be reported after the page is delivered. It takes the same `wp_postviews_nonce` the counting script is given, as a `nonce` parameter.

**It is refused unless the site defers counting** — that is, unless it has a page cache and has turned the AJAX counting path on. Otherwise the view has already been counted while the page rendered, and counting again here would record every view twice.

**A refusal answers 403** — a bad nonce, or a site that counts views while the page renders. A post that does not exist is 404.

**This route is an addition.** The `admin-ajax.php` `wp_postviews` action is unchanged and still supported.

## Frequently Asked Questions

### How To View Stats With Widgets?
* Go to `WP-Admin -> Appearance -> Widgets`
* The widget name is Views.

### To Display Least Viewed Posts

```php
<?php if (function_exists('get_least_viewed')): ?>
	<ul>
		<?php get_least_viewed(); ?>
	</ul>
<?php endif; ?>
```

* The first value you pass in is the post type that you want. If you want to get every post types, just use 'both'. It also supports PHP array: example `array('post', 'page')`.
* The second value you pass in is the maximum number of post you want to get.
* Default: get_least_viewed('both', 10);

### To Display Most Viewed Posts

```php
<?php if (function_exists('get_most_viewed')): ?>
	<ul>
		<?php get_most_viewed(); ?>
	</ul>
<?php endif; ?>
```

* The first value you pass in is the post type that you want. If you want to get every post types, just use 'both'. It also supports PHP array: example `array('post', 'page')`.
* The second value you pass in is the maximum number of post you want to get.
* Default: get_most_viewed('both', 10);

### To Display Least Viewed Posts By Tag

```php
<?php if (function_exists('get_least_viewed_tag')): ?>
	<ul>
		<?php get_least_viewed_tag(); ?>
	</ul>
<?php endif; ?>
```

* The first value you pass in is the tag id.
* The second value you pass in is the post type that you want. If you want to get every post types, just use 'both'. It also supports PHP array: example `array('post', 'page')`.
* The third value you pass in is the maximum number of post you want to get.
* Default: get_least_viewed_tag(1, 'both', 10);

### To Display Most Viewed Posts By Tag

```php
<?php if (function_exists('get_most_viewed_tag')): ?>
	<ul>
		<?php get_most_viewed_tag(); ?>
	</ul>
<?php endif; ?>
```

* The first value you pass in is the tag id.
* The second value you pass in is the post type that you want. If you want to get every post types, just use 'both'. It also supports PHP array: example `array('post', 'page')`.
* The third value you pass in is the maximum number of post you want to get.
* Default: get_most_viewed_tag(1, 'both', 10);

### To Display Least Viewed Posts For A Category

```php
<?php if (function_exists('get_least_viewed_category')): ?>
	<ul>
		<?php get_least_viewed_category(); ?>
	</ul>
<?php endif; ?>
```

* The first value you pass in is the category id.
* The second value you pass in is the post type that you want. If you want to get every post types, just use 'both'. It also supports PHP array: example `array('post', 'page')`.
* The third value you pass in is the maximum number of post you want to get.
* Default: get_least_viewed_category(1, 'both', 10);

### To Display Most Viewed Posts For A Category

```php
<?php if (function_exists('get_most_viewed_category')): ?>
	<ul>
		<?php get_most_viewed_category(); ?>
	</ul>
<?php endif; ?>
```

* The first value you pass in is the category id.
* The second value you pass in is the post type that you want. If you want to get every post types, just use 'both'. It also supports PHP array: example `array('post', 'page')`.
* The third value you pass in is the maximum number of post you want to get.
* Default: get_most_viewed_category(1, 'both', 10);

### To Sort Most/Least Viewed Posts
* You can use: `<?php query_posts( array( 'meta_key' => 'views', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) ); ?>`
* Or pass in the variables to the URL: `https://yoursite.com/?v_sortby=views&v_orderby=desc`
* You can replace DESC with ASC if you want the least viewed posts.

### To Display Updating View Count With LiteSpeed Cache
Use: `<div id="postviews_lscwp"></div>` to replace `<?php if(function_exists('the_views')) { the_views(); } ?>`.
NOTE: The id can be changed, but the div id and the script must match.

The plugin's `js/wp-postviews-cache.js` already posts the view and receives the new count back, so you only need to write that count into your div. Add this to your theme, or to a small script of your own enqueued after `wp-postviews-cache`:

```javascript
document.addEventListener( 'postviews:updated', function ( event ) {
	const target = document.getElementById( 'postviews_lscwp' );

	if ( target ) {
		target.textContent = event.detail.views + ' views';
	}
} );
```

Purge the cache to use the updated pages.

### To Get Views With REST API
You can obtain the number of post views by adding `views` to your `_fields` parameter:
`/wp/v2/posts?_fields=views,title`

## Screenshots

1. Settings -> WP-PostViews, which chooses whose views are counted and how they are recorded
2. The Templates tab, holding the wording the count is written into
3. The count in a post, placed with the shortcode
4. The Most Viewed widget

## Changelog
### 2.0.0
* FIXED: The two templates are echoed as markup on the strength of being `wp_kses_post()`'d when saved — and that was true only of the settings screen. The 2.0.0 migration writes through a different door, and the row it folds in comes from a release that stored the field with no filtering at all, so a hostile template on a site upgrading from 1.78.1 was carried across verbatim and echoed to every visitor. The filtering now happens where the row is written, so WP-CLI, cron, a restored backup and another plugin all pass through it too
* FIXED: The deferred counting endpoint checked only that an ID named *something*, and every row in `wp_posts` answers to that — revisions, autosaves, auto-drafts, attachments, trashed posts, drafts, menu items, reusable blocks. An unauthenticated caller could walk the ID space writing a view row against each one, and read back the count of unpublished posts while doing it. It now requires a real, publicly viewable post, which is what the other counting path already knew
* FIXED: A view count that is not a number — the meta key is unprefixed, so anyone who can edit a post can type one into Custom Fields — turned every listing containing that post into a fatal error on PHP 8, because both count tokens are built whether or not the template uses them
* NEW: An editor block, **Post Views**, under Widgets. It renders on the server through the same code the shortcode uses, so a block and a shortcode showing the same post produce the same markup, and previewing it in the editor never counts a view. The `[views]` shortcode is unchanged and still supported — nothing needs converting, and posts already containing it keep working.
* NEW: A `wp postviews` WP-CLI command — `list` and `get`. It reads and never writes.
* NEW: A `postviews/v1` REST API carrying one route, for counting a view from a cached page. Reading a count is already a `views` field on the core post resource. The `admin-ajax.php` `wp_postviews` action is unchanged and still supported.
* BREAKING: Requires WordPress 6.8 and PHP 8.2, up from 6.0 and 7.4.
* BREAKING: The `the_views` filter is now `wp_postviews_the_views`. The template tag `the_views()` is unchanged.
* BREAKING: The `postviews_should_count` filter is now `wp_postviews_should_count`.
* BREAKING: The `postviews_increment_views` and `postviews_increment_views_ajax` actions are now `wp_postviews_increment_views` and `wp_postviews_increment_views_ajax`.
* BREAKING: The settings are stored in `wp_postviews_options` instead of `views_options`, and the upgrade markers in `wp_postviews_version` instead of `views_version`. Both are migrated automatically.
* BREAKING: The WP-Stats toggles move out of the shared `stats_display` and `stats_mostlimit` rows into this plugin's own settings, and the shared rows are deleted. Update all seven WP-Stats plugins together.
* BREAKING: The widget class `WP_Widget_PostViews` is now `WP_PostViews_Widget`. Configured widgets are unaffected.
* BREAKING: The AJAX action a cached page posts to is now `wp_postviews` instead of `postviews`.
* BREAKING: The six Display Options settings — home page, single posts, pages, archives, searches and other pages — are removed, and the stored keys are dropped on upgrade. Their replacement is the `wp_postviews_should_display` filter.
* NEW: Restructured into `includes/` classes. The template tags — `the_views()`, `get_most_viewed()`, `get_least_viewed()`, and the category and tag variants — are unchanged and keep working exactly as before.
* NEW: The options screen is rebuilt on the WordPress Settings API and no longer loads jQuery.
* NEW: The WP-Stats section, and how many entries its most viewed lists carry, are now settings on Settings -> WP-PostViews.
* NEW: The `wp_postviews_should_display` filter decides whether a count is shown. It is read by `the_views()` only; the `[views]` shortcode and the admin Views column are explicit requests and ignore it.
* CHANGED: The settings screen is two tabs, Settings and Templates, over one settings group and one option row.
* CHANGED: The settings screen is titled "Post Views Settings", matching the other settings screens in this family.
* CHANGED: WP-Stats now receives one Views section rather than three separately toggled panels, so the most viewed pages list appears alongside the posts list.
* FIXED: `%VIEW_COUNT_ROUNDED%` picked its unit before rounding, so 999,950 displayed as "1000K" instead of "1M" and 999,999,999 as "1000M" instead of "1B".
* FIXED: Titles were truncated on bytes rather than characters, because the multibyte branch was gated on `MB_OVERLOAD_STRING`, which PHP 8.0 removed. A CJK title cut mid-character disappeared from the most/least viewed lists entirely.
* FIXED: The AJAX endpoint recorded views against post IDs that do not exist, letting anyone add rows to `wp_postmeta` indefinitely.
* FIXED: `?v_orderby=ASC` was compared against lowercase only, so an uppercase direction — which is what the FAQ tells you to use — silently fell back to descending, the opposite of what was asked for.
* FIXED: The widget silently discarded changes made in the block widget editor and the customizer, because it required a hidden form field neither of them sends.
* FIXED: The widget warned about undefined array keys when rendered from the block widget editor or the customizer.
* FIXED: Uninstalling on a network of more than 100 sites left options and view counts behind on every site after the hundredth, and reported success. Network activation could fatal on the removed `wp_get_sites()`.
* NOTE: The settings screen moved from `options-general.php?page=wp-postviews/postviews-options.php` to `options-general.php?page=wp-postviews`. Update any bookmark; the Settings -> WP-PostViews menu item is where it always was.
* NOTE: Templates are now stored unslashed. Existing templates are migrated automatically the first time 2.0.0 loads.
* NOTE: `should_views_be_displayed()`, `postviews_round_number()` and `snippet_text()` are no longer global functions. They were never documented; they are now `WP_PostViews_Display::should_be_displayed()`, `WP_PostViews_Display::round_number()` and `WP_PostViews_Display::snippet_text()`.

## Upgrade Notice

### 2.0.0

Requires WordPress 6.8 and PHP 8.2.

View counts, templates and settings all carry over on their own.

**If your view counts stop appearing, this is why: `the_views` is now `wp_postviews_the_views`.** That name was far too generic for a filter belonging to one plugin, and it had been public since 1.78.1, so it is very probably sitting in a theme somewhere. Search for `the_views`: a line like `add_filter( 'the_views', … )` needs the new name and nothing else, with identical arguments. A call to `the_views()` with brackets is the template tag and is unchanged. There is no shim, so the old name fails silently — the symptom is the count appearing but your custom wrapper, prefix or wording gone.

Three more were renamed the same way: `postviews_should_count` is now `wp_postviews_should_count`, and `postviews_increment_views` and `postviews_increment_views_ajax` are now `wp_postviews_increment_views` and `wp_postviews_increment_views_ajax`.

**Update all seven WP-Stats plugins together.** WP-PostViews, WP-Stats, WP-Polls, WP-PostRatings, WP-UserOnline, WP-EMail and WP-DownloadManager shared two unprefixed rows; each keeps its own copy now and deletes the shared ones, so whichever you update first takes them from the rest. A missing row means "show", so a section you had hidden may reappear. The views section is toggled on **Settings -> WP-PostViews** now, which also sets how many entries the most viewed lists carry. WP-Stats receives one Views section rather than three separately switchable panels, so the most viewed *pages* list appears beside the posts list even if you had only ever asked for posts; untick the whole section if you would rather not see it.

**The settings screen is `options-general.php?page=wp-postviews`**, not `options-general.php?page=wp-postviews/postviews-options.php`. The Settings -> WP-PostViews menu item is where it always was.

**Two rows are renamed** on the first load after updating — `views_options` to `wp_postviews_options` and `views_version` to `wp_postviews_version` — and the old rows are deleted afterwards. Point any code, WP-CLI script or export reading `views_options` at the new name.

**Three undocumented functions are gone.** `should_views_be_displayed()`, `postviews_round_number()` and `snippet_text()` are now `WP_PostViews_Display::should_be_displayed()`, `::round_number()` and `::snippet_text()`. The widget class `WP_Widget_PostViews` is `WP_PostViews_Widget`, which matters only if you were instantiating it yourself; widgets already placed in a sidebar are untouched.

**The Display Options settings are gone, and counts you had hidden will start appearing.** The six rows — Home Page, Single Posts, Pages, Archive Pages, Search Pages, Other Pages, each of them "Display to everyone / Display to registered users only / Don't display" — are removed, and the stored `display_home`, `display_single`, `display_page`, `display_archive`, `display_search` and `display_other` keys are dropped on upgrade. If you had set any of them to anything other than "Display to everyone", that choice is not carried over: a site that hid the count on archives or on search results will show it there from the first page load after updating.

Restore the gate with the `wp_postviews_should_display` filter, which is the documented replacement and receives `true`. Return false to hide the count. All the conditional tags are available, so the old settings map across one for one:

```php
add_filter( 'wp_postviews_should_display', function ( $show ) {
	// "Don't display on archive pages" and "Don't display on search pages".
	if ( is_archive() || is_search() ) {
		return false;
	}

	// "Display to registered users only", for wherever you had chosen it.
	if ( is_home() ) {
		return is_user_logged_in();
	}

	return $show;
} );
```

`WP_PostViews_Display::should_be_displayed()` is unchanged as a public method and now returns what that filter answers, so anything calling it directly keeps working. The `[views]` shortcode and the admin Views column deliberately do not consult the filter: both are explicit requests for the number.

**The settings screen is two tabs now**, Settings and Templates, at the same URL. The two template fields moved to the Templates tab; everything else stayed. It is still one option row and one Save Changes button per tab, and saving one tab does not disturb the other.
