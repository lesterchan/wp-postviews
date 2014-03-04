# WP-PostViews
Contributors: GamerZ  
Donate link: http://lesterchan.net/site/donation/  
Tags: views, hits, counter, postviews  
Requires at least: 2.8  
Tested up to: 3.8  
Stable tag: 1.66  

Enables you to display how many times a post/page had been viewed.

## Description

### Previous Versions
* [WP-PostViews 1.40 For WordPress 2.7.x](http://downloads.wordpress.org/plugin/wp-postviews.1.40.zip "WP-PostViews 1.40 For WordPress 2.7.x")
* [WP-PostViews 1.31 For WordPress 2.3.x, 2.5.x And 2.6.x](http://downloads.wordpress.org/plugin/wp-postviews.1.31.zip "WP-PostViews 1.31 For WordPress 2.3.x, 2.5.x And 2.6.x")
* [WP-PostViews 1.11 For WordPress 2.1.x And 2.2.x](http://downloads.wordpress.org/plugin/wp-postviews.1.11.zip "WP-PostViews 1.11 For WordPress 2.1.x And 2.2.x")
* [WP-PostViews 1.02 For WordPress 2.0.x](http://downloads.wordpress.org/plugin/wp-postviews.1.02.zip "WP-PostViews 1.02 For WordPress 2.0.x")

### Development
* [http://dev.wp-plugins.org/browser/wp-postviews/](http://dev.wp-plugins.org/browser/wp-postviews/ "http://dev.wp-plugins.org/browser/wp-postviews/")

### Translations
* [http://dev.wp-plugins.org/browser/wp-postviews/i18n/](http://dev.wp-plugins.org/browser/wp-postviews/i18n/ "http://dev.wp-plugins.org/browser/wp-postviews/i18n/")

### Support Forums
* [http://forums.lesterchan.net/index.php?board=16.0](http://forums.lesterchan.net/index.php?board=16.0 "http://forums.lesterchan.net/index.php?board=16.0")

### Credits
* WP-Cache/WP-SuperCache Compatibility By [Thaya Kareeson](http://omninoggin.com/ "Thaya Kareeson")
* __ngetext() by [Anna Ozeritskaya](http://hweia.ru/ "Anna Ozeritskaya")
* Right To Left Language Support by [Kambiz R. Khojasteh](http://persian-programming.com/ "Kambiz R. Khojasteh")
* Options To Display Views On Certain Places by [David Potter](http://dpotter.net/Technical/ "David Potter")

### Donations
* I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appericiate it. If not feel free to use it without any obligations.

## Changelog
### Version 1.66
* NEW: Supports MultiSite Network Activation
* NEW: Add %POST_DATE% and %POST_TIME% to template variables
* NEW: Add China isearch engines bots
* NEW: Ability to pass in an array of post types for get_most/least_*() functions. Props Leo Plaw.
* FIXED: Moved uninstall to uninstall.php and hence fix missing nonce. Props Julio Potier.
* FIXED: Notices and better way to get views from meta. Props daankortenbach.
* FIXED: No longer needing add_post_meta() if update_post_meta() fails.

### Version 1.65 (02-06-2013)
* FIXED: Views not showing in WP-Admin if "Display Options" is not set to "Display to everyone"

### Version 1.64 (31-05-2013)
* NEW: Using Enqueue Script For WP_CACHE Enabled Sites. Props Crowd Favourite
* FIXED: Viewcounter always display eventhough "display to registered users only" is set

### Version 1.63 (07-05-2013)
* NEW: Added nonce To PostViews Options Admin Page

### Version 1.62 (29-11-2012)
* NEW: Add "Views" Column To Manage Pages In WP-Admin
* NEW: Add Sortable "Views" Column To Manage Posts/Pages In WP-Admin

### Version 1.61 (21-05-2012)
* FIXED: Move AJAX Request to wp-admin/admin-ajax.php

### Version 1.60 (18-02-2011)
* NEW: Added Views Count To Edit Posts Screen
* FIXED: Removed Global $post

### Version 1.50 (15-06-2009)
* NEW: Works For WordPress 2.8 Only
* NEW: Uses jQuery Framework
* NEW: Added In Most Viewed Pages To WP-Stats
* NEW: Use _n() Instead Of __ngettext() And _n_noop() Instead Of __ngettext_noop()
* FIXED: Uses $_SERVER['PHP_SELF'] With plugin_basename(__FILE__) Instead Of Just $_SERVER['REQUEST_URI']
* NEW: Uses New Widget Class From WordPress
* NEW: Merge Widget Code To wp-postviews.php And Remove wp-postviews-widget.php
* NEW: Added get_most_viewed_tag() And get_least_viewed_tag()
* FIXED: Ensure That Post Is Not A Revision
* FIXED: Multiple Loops Filtered Not Cleared

### Version 1.40 (12-12-2008)
* NEW: Works For WordPress 2.7 Only
* NEW: Options To Display Views On Certain Places by David Potter
* NEW: Right To Left Language Support by Kambiz R. Khojasteh
* NEW: Output Of the_views() Applied To "the_views" Filter by Kambiz R. Khojasteh
* NEW: Called postviews_textdomain() In views_init() by Kambiz R. Khojasteh
* NEW: Uses plugins_url() And site_url()
* NEW: Added get_least_viewed() And get_least_viewed_category() By JBrinx
* FIXED: "views" Custom Field Gets Created Now When Post Is Published

### Version 1.31 (16-07-2008)
* NEW: Works For WordPress 2.6
* NEW: Renamed GET Variables sortby To v_sortby And orderby To v_orderby
* NEW: Better Translation Using __ngetext() by Anna Ozeritskaya
* FIXED: Able To Use v_sortby And v_orderby in query_posts()

### Version 1.30 (01-06-2008)
* NEW: Uses /wp-postviews/ Folder Instead Of /postviews/
* NEW: Uses wp-postviews.php Instead Of postviews.php
* NEW: Uses wp-postviews-widget.php Instead Of postviews-widget.php
* NEW: Uses number_format_i18n() Instead Of number_format()
* NEW: Option To Exclude Bots Views In 'WP-Admin -> Settings -> Post Views'
* NEW: Added Most Viewed Template
* NEW: Change The Way WP-PostViews Count Views
* NEW: Should Work With WP-Cache Or WP-SuperCache

### Version 1.20 (01-10-2007)
* NEW: Works For WordPress 2.3 Only
* NEW: Most Viewed Widget Added
* NEW: Ability To Uninstall WP-PostViews
* NEW: Uses WP-Stats Filter To Add Stats Into WP-Stats Page

### Version 1.11 (01-06-2007)
* FIXED: Wrong URL For Page Under Most Viewed Posts Listing

### Version 1.10 (01-02-2007)
* NEW: Works For WordPress 2.1 Only
* NEW: Localization WP-PostViews
* NEW: Added Function To Get Most Viewed Post By Category ID
* FIXED: Views Not Counting In Some Cases

### Version 1.02 (01-10-2006)
* NEW: Change In get_most_viewed() To Accommodate Latest Version Of WP-Stats

### Version 1.01 (01-07-2006)
* NEW: Added Get Total Views Function
* FIXED: Modified Get Most Viewed Post Function

### Version 1.00 (01-03-2006)
* NEW: Initial Release

## Installation

1. Open `wp-content/plugins` Folder
2. Put: `Folder: wp-postviews`
3. Activate `WP-PostViews` Plugin
4. Go to `WP-Admin -> Settings -> PostViews` to configure the plugin.

### Usage
1. Open `wp-content/themes/<YOUR THEME NAME>/index.php`
2. You may place it in archive.php, single.php, post.php or page.php also.
3. Find: `<?php while (have_posts()) : the_post(); ?>`
4. Add Anywhere Below It (The Place You Want The Views To Show): `<?php if(function_exists('the_views')) { the_views(); } ?>`

## Upgrading

1. Deactivate `wp-postviews` Plugin
2. Open `wp-content/plugins` Folder
3. Put/Overwrite: `Folder: wp-postviews`
4. Activate `WP-PostViews` Plugin

## Upgrade Notice

N/A

## Screenshots

1. PostViews

## Frequently Asked Questions

### How To View Stats With Widgets?
* Go to `WP-Admin -> Appearance -> Widgets`
* The widget name is Views.

### How To View Stats (Outside WP Loop)

### To Display Least Viewed Posts
* Use:
<code>
<?php if (function_exists('get_least_viewed')): ?>
	<ul>
		<?php get_least_viewed(); ?>
	</ul>
<?php endif; ?>
</code>
* The first value you pass in is the post type that you want. If you want to get every post types, just use 'both'. It also supports PHP array: example `array('post', 'page')`.
* The second value you pass in is the maximum number of post you want to get.
* Default: get_least_viewed('both', 10);

### To Display Most Viewed Posts
* Use:
<code>
<?php if (function_exists('get_most_viewed')): ?>
	<ul>
		<?php get_most_viewed(); ?>
	</ul>
<?php endif; ?>
</code>
* The first value you pass in is the post type that you want. If you want to get every post types, just use 'both'. It also supports PHP array: example `array('post', 'page')`.
* The second value you pass in is the maximum number of post you want to get.
* Default: get_most_viewed('both', 10);

### To Display Least Viewed Posts By Tag
* Use:
<code>
<?php if (function_exists('get_least_viewed_tag')): ?>
	<ul>
		<?php get_least_viewed_tag(); ?>
	</ul>
<?php endif; ?>
</code>
* The first value you pass in is the tag id.
* The second value you pass in is the post type that you want. If you want to get every post types, just use 'both'. It also supports PHP array: example `array('post', 'page')`.
* The third value you pass in is the maximum number of post you want to get.
* Default: get_least_viewed_tag(1, 'both', 10);

### To Display Most Viewed Posts By Tag
* Use:
<code>
<?php if (function_exists('get_most_viewed_tag')): ?>
	<ul>
		<?php get_most_viewed_tag(); ?>
	</ul>
<?php endif; ?>
</code>
* The first value you pass in is the tag id.
* The second value you pass in is the post type that you want. If you want to get every post types, just use 'both'. It also supports PHP array: example `array('post', 'page')`.
* The third value you pass in is the maximum number of post you want to get.
* Default: get_most_viewed_tag(1, 'both', 10);

### To Display Least Viewed Posts For A Category
* Use:
<code>
<?php if (function_exists('get_least_viewed_category')): ?>
	<ul>
		<?php get_least_viewed_category(); ?>
	</ul>
<?php endif; ?>
</code>
* The first value you pass in is the category id.
* The second value you pass in is the post type that you want. If you want to get every post types, just use 'both'. It also supports PHP array: example `array('post', 'page')`.
* The third value you pass in is the maximum number of post you want to get.
* Default: get_least_viewed_category(1, 'both', 10);

### To Display Most Viewed Posts For A Category
* Use:
<code>
<?php if (function_exists('get_most_viewed_category')): ?>
	<ul>
		<?php get_most_viewed_category(); ?>
	</ul>
<?php endif; ?>
</code>
* The first value you pass in is the category id.
* The second value you pass in is the post type that you want. If you want to get every post types, just use 'both'. It also supports PHP array: example `array('post', 'page')`.
* The third value you pass in is the maximum number of post you want to get.
* Default: get_most_viewed_category(1, 'both', 10);

### To Sort Most/Least Viewed Posts
* You can use: `<?php query_posts( array( 'meta_key' => 'views', 'orderby' => 'meta_value_num', 'order' => 'DESC' ) ); ?>`
* Or pass in the variables to the URL: `http://yoursite.com/?v_sortby=views&v_orderby=desc`
* You can replace DESC  with ASC if you want the least viewed posts.
