# WP-PostViews
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: views, hits, counter, postviews  
Requires at least: 6.8  
Tested up to: 7.0  
Stable tag: 2.0.0  
Requires PHP: 8.2  
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enables you to display how many times a post/page had been viewed.

## Description

### Usage
1. Open `wp-content/themes/<YOUR THEME NAME>/index.php`
2. You may place it in archive.php, single.php, post.php or page.php also.
3. Find: `<?php while (have_posts()) : the_post(); ?>`
4. Add Anywhere Below It (The Place You Want The Views To Show): `<?php if(function_exists('the_views')) { the_views(); } ?>`
5. Or you can use the shortcode `[views]` or `[views id="1"]` (where 1 is the post ID) in a post
6. Go to `WP-Admin -> Settings -> PostViews` to configure the plugin.

### Development
[https://github.com/lesterchan/wp-postviews/](https://github.com/lesterchan/wp-postviews/ "https://github.com/lesterchan/wp-postviews/")

### Credits
* Plugin icon by [Iconmoon](https://www.icomoon.io) from [Flaticon](https://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog
### 2.0.0
* BREAKING: Requires WordPress 6.8 and PHP 8.2, up from 6.0 and 7.4. A site on an older stack will not be offered the update.
* BREAKING: The `the_views` filter is now `wp_postviews_the_views`. The template tag `the_views()` is unchanged.
* BREAKING: The `postviews_should_count` filter is now `wp_postviews_should_count`.
* BREAKING: The `postviews_increment_views` and `postviews_increment_views_ajax` actions are now `wp_postviews_increment_views` and `wp_postviews_increment_views_ajax`.
* BREAKING: The settings are stored in `wp_postviews_options` instead of `views_options`, and the upgrade markers in `wp_postviews_version` instead of `views_version`. Both are migrated automatically.
* BREAKING: The WP-Stats toggles move out of the shared `stats_display` and `stats_mostlimit` rows into this plugin's own settings, and the shared rows are deleted. Update all seven WP-Stats plugins together.
* BREAKING: The widget class `WP_Widget_PostViews` is now `WP_PostViews_Widget`. Configured widgets are unaffected.
* BREAKING: The AJAX action a cached page posts to is now `wp_postviews` instead of `postviews`.
* NEW: Restructured into `includes/` classes. The template tags — `the_views()`, `get_most_viewed()`, `get_least_viewed()`, and the category and tag variants — are unchanged and keep working exactly as before.
* NEW: The options screen is rebuilt on the WordPress Settings API and no longer loads jQuery.
* NEW: The WP-Stats section, and how many entries its most viewed lists carry, are now settings on Settings → PostViews.
* CHANGED: WP-Stats now receives one Views section rather than three separately toggled panels, so the most viewed pages list appears alongside the posts list.
* FIXED: `%VIEW_COUNT_ROUNDED%` picked its unit before rounding, so 999,950 displayed as "1000K" instead of "1M" and 999,999,999 as "1000M" instead of "1B".
* FIXED: Titles were truncated on bytes rather than characters, because the multibyte branch was gated on `MB_OVERLOAD_STRING`, which PHP 8.0 removed. A CJK title cut mid-character disappeared from the most/least viewed lists entirely.
* FIXED: The AJAX endpoint recorded views against post IDs that do not exist, letting anyone add rows to `wp_postmeta` indefinitely.
* FIXED: `?v_orderby=ASC` was compared against lowercase only, so an uppercase direction — which is what the FAQ tells you to use — silently fell back to descending, the opposite of what was asked for.
* FIXED: The widget silently discarded changes made in the block widget editor and the customizer, because it required a hidden form field neither of them sends.
* FIXED: The widget warned about undefined array keys when rendered from the block widget editor or the customizer.
* FIXED: Uninstalling on a network of more than 100 sites left options and view counts behind on every site after the hundredth, and reported success. Network activation could fatal on the removed `wp_get_sites()`.
* NOTE: The settings screen moved from `options-general.php?page=wp-postviews/postviews-options.php` to `options-general.php?page=wp-postviews`. Update any bookmark; the Settings → PostViews menu item is unchanged.
* NOTE: Templates are now stored unslashed. Existing templates are migrated automatically the first time 2.0.0 loads.
* NOTE: `should_views_be_displayed()`, `postviews_round_number()` and `snippet_text()` are no longer global functions. They were never documented; they are now `WP_PostViews_Display::should_be_displayed()`, `WP_PostViews_Display::round_number()` and `WP_PostViews_Display::snippet_text()`.

### 1.78.1
* NEW: WordPress 7.0

### 1.78
* NEW: Add %POST_THUMBNAIL_URL% to template variables

### 1.77
* NEW: Use Vanilla JS. Props @JiveDig
* NEW: Bump to WordPress 6.2
* NEW: Support views under fields for Rest API. Props @vitro-mod

### 1.76.1
* NEW: Add Post Author in views template
* NEW: Bump for WordPress 5.3

### 1.76
* NEW: Added postviews_should_count filter
* FIXED: Change to (int) from intval() and use sanitize_key() with it.

### 1.75
* NEW: Use WP_Query() for most/least viewed posts

### 1.74
* NEW: Bump WordPress 4.7
* NEW: Template variable %POST_CATEGORY_ID%. It returns Post's Category ID. If you are using Yoast SEO Plugin, it will return the priority Category ID. Props @FunFrog-BY

### 1.73
* FIXED: In preview mode, don't count views

### 1.72
* NEW: Add %POST_THUMBNAIL% to template variables

### 1.71
* FIXED: Notices in Widget Constructor for WordPress 4.3

### 1.70
* FIXED: Integration with WP-Stats

### 1.69
* NEW: Shortcode `[views]` or [views id="POST_ID"]` to embed view count into post
* NEW: Added template variable `%VIEW_COUNT_ROUNDED%` to support rounded view count like 10.1k or 11.2M

### 1.68
* NEW: Added action hook 'postviews_increment_views' and 'postviews_increment_views_ajax'
* NEW: Allow custom post type to be chosen under the widget

### 1.67
* NEW: Allow user to not use AJAX to update the views even though WP_CACHE is true

### 1.66
* NEW: Supports MultiSite Network Activation
* NEW: Add %POST_DATE% and %POST_TIME% to template variables
* NEW: Add China isearch engines bots
* NEW: Ability to pass in an array of post types for get_most/least_*() functions. Props Leo Plaw.
* FIXED: Moved uninstall to uninstall.php and hence fix missing nonce. Props Julio Potier.
* FIXED: Notices and better way to get views from meta. Props daankortenbach.
* FIXED: No longer needing add_post_meta() if update_post_meta() fails.

### 1.65
* FIXED: Views not showing in WP-Admin if "Display Options" is not set to "Display to everyone"

## Upgrade Notice

### 2.0.0
2.0.0 is the first release since 1.78.1 and it renames things. Your view counts, your templates and your settings all carry over on their own — nothing below asks you to re-enter anything — but a handful of names your theme or another plugin may be using have changed, and one of them is likely enough to matter that it comes first.

**If your view counts stop appearing after the update, this is why.** The filter called `the_views` is now called `wp_postviews_the_views`. That old name was far too generic for a filter belonging to one plugin, and it had been public since 1.78.1, which means it is very probably sitting in a theme somewhere — in `functions.php`, in a child theme, or in a snippet a developer added years ago to wrap the count in your own markup. Search your theme for `the_views` and look at what you find. A line like `add_filter( 'the_views', … )` needs the name changed to `wp_postviews_the_views`; nothing else about it changes, and the arguments it receives are identical. A call to `the_views()` with brackets is the template tag, not the filter — leave that exactly as it is, it still works. The safe way to check is to look at a post before and after: if the count is there but your custom wrapper, prefix or wording has gone, you have found a filter that needs renaming. There is no compatibility shim, so the old name does nothing at all and fails silently rather than warning you.

Three more hooks were renamed the same way, and for the same reason. `postviews_should_count` is now `wp_postviews_should_count`. `postviews_increment_views` and `postviews_increment_views_ajax` are now `wp_postviews_increment_views` and `wp_postviews_increment_views_ajax`. If you have never heard of any of these, you are not using them and there is nothing to do.

**WordPress 6.8 and PHP 8.2 are now the minimum**, up from 6.0 and 7.4. This is the one that will stop you before anything else does: a site on an older WordPress or an older PHP is simply not offered the update, so if you cannot see 2.0.0 on your Plugins screen, check those two numbers first and ask your host about PHP.

**If you also use WP-Stats, update all seven plugins together.** WP-PostViews, WP-Stats, WP-Polls, WP-PostRatings, WP-UserOnline, WP-EMail and WP-DownloadManager used to share two unlabelled settings rows, and each of them now keeps its own copy and deletes the shared one. Whichever you update first takes those rows away from the rest, so updating them piecemeal leaves the others reading settings that are no longer there. Nothing is lost either way — a plugin that finds the shared row gone assumes its section should still be shown — but the tidy way is to update all seven in one go. The view section is switched on or off on Settings → PostViews now, not on the WP-Stats options screen, and the same screen sets how many entries the most viewed lists carry. One other change to expect there: WP-Stats used to draw three separately switchable panels for this plugin and now receives a single Views section, so the most viewed **pages** list appears next to the posts list even if you had only ever asked for posts. Untick the whole section on Settings → PostViews if you would rather not see it.

**The settings screen has moved.** It was `options-general.php?page=wp-postviews/postviews-options.php` and it is now `options-general.php?page=wp-postviews`. Update any bookmark. The Settings → PostViews menu item is where it always was.

**Two settings rows were renamed**, from `views_options` and `views_version` to `wp_postviews_options` and `wp_postviews_version`. The migration runs by itself the first time 2.0.0 loads and deletes the old rows afterwards, so there is nothing for you to do — unless you have code, a WP-CLI script or an export that reads `views_options` directly, in which case point it at the new name.

Finally, three functions that were never documented are gone: `should_views_be_displayed()`, `postviews_round_number()` and `snippet_text()`. They are `WP_PostViews_Display::should_be_displayed()`, `::round_number()` and `::snippet_text()` now. The widget class `WP_Widget_PostViews` is `WP_PostViews_Widget`, which matters only if you were instantiating it yourself; widgets you have already placed in a sidebar are untouched.

## Screenshots

1. PostViews
2. Admin - PostViews Options

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
* You can replace DESC  with ASC if you want the least viewed posts.

### To Display Updating View Count With LiteSpeed Cache
Use: `<div id="postviews_lscwp"></div>` to replace `<?php if(function_exists('the_views')) { the_views(); } ?>`.
NOTE: The id can be changed, but the div id and the script must match.

`js/wp-postviews-cache.js` already posts the view and receives the new count back, so you only need to write that count into your div. Add this to your theme, or to a small script of your own enqueued after `wp-postviews-cache`:

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
