# WP-PostViews
Contributors: GamerZ  
Donate link: https://lesterchan.net/site/donation/  
Tags: views, hits, counter, postviews  
Requires at least: 4.0  
Tested up to: 4.7  
Stable tag: 1.76  

Enables you to display how many times a post/page had been viewed.

## Description

### Usage
1. Open `wp-content/themes/<YOUR THEME NAME>/index.php`
2. You may place it in archive.php, single.php, post.php or page.php also.
3. Find: `<?php while (have_posts()) : the_post(); ?>`
4. Add Anywhere Below It (The Place You Want The Views To Show): `<?php if(function_exists('the_views')) { the_views(); } ?>`
5. Or you can use the shortcode `[views]` or `[views id="1"]` (where 1 is the post ID) in a post
6. Go to `WP-Admin -> Settings -> PostViews` to configure the plugin.

### Build Status
[![Build Status](https://travis-ci.org/lesterchan/wp-postviews.svg?branch=master)](https://travis-ci.org/lesterchan/wp-postviews)

### Development
[https://github.com/lesterchan/wp-postviews/](https://github.com/lesterchan/wp-postviews/ "https://github.com/lesterchan/wp-postviews/")

### Translations
[http://dev.wp-plugins.org/browser/wp-postviews/i18n/](http://dev.wp-plugins.org/browser/wp-postviews/i18n/ "http://dev.wp-plugins.org/browser/wp-postviews/i18n/")

### Credits
* Plugin icon by [Iconmoon](http://www.icomoon.io) from [Flaticon](http://www.flaticon.com)

### Donations
I spent most of my free time creating, updating, maintaining and supporting these plugins, if you really love my plugins and could spare me a couple of bucks, I will really appreciate it. If not feel free to use it without any obligations.

## Changelog
### Version 1.76
* NEW: Added postviews_should_count filter
* FIXED: Change to (int) from intval() and use sanitize_key() with it.

### Version 1.75
* NEW: Use WP_Query() for most/least viewed posts

### Version 1.74
* NEW: Bump WordPress 4.7
* NEW: Template variable %POST_CATEGORY_ID%. It returns Post's Category ID. If you are using Yoast SEO Plugin, it will return the priority Category ID. Props @FunFrog-BY

### Version 1.73
* FIXED: In preview mode, don't count views

### Version 1.72
* NEW: Add %POST_THUMBNAIL% to template variables

### Version 1.71
* FIXED: Notices in Widget Constructor for WordPress 4.3

### Version 1.70
* FIXED: Integration with WP-Stats

### Version 1.69
* NEW: Shortcode `[views]` or [views id="POST_ID"]` to embed view count into post
* NEW: Added template variable `%VIEW_COUNT_ROUNDED%` to support rounded view count like 10.1k or 11.2M

### Version 1.68
* NEW: Added action hook 'postviews_increment_views' and 'postviews_increment_views_ajax'
* NEW: Allow custom post type to be chosen under the widget

### Version 1.67
* NEW: Allow user to not use AJAX to update the views even though WP_CACHE is true

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

## Upgrade Notice

N/A

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
* Or pass in the variables to the URL: `http://yoursite.com/?v_sortby=views&v_orderby=desc`
* You can replace DESC  with ASC if you want the least viewed posts.

### To Display Updating View Count With LiteSpeed Cache
Use: `<div id="postviews_lscwp"></div>` to replace `<?php if(function_exists('the_views')) { the_views(); } ?>`.
NOTE: The id can be changed, but the div id and the ajax function must match.
Replace the ajax query in `wp-content/plugins/wp-postviews/postviews-cache.js` with

```javascript
jQuery.ajax({
    type:"GET",
    url:viewsCacheL10n.admin_ajax_url,
    data:"postviews_id="+viewsCacheL10n.post_id+"&action=postviews",
    cache:!1,
    success:function(data) {
        if(data) {
            jQuery('#postviews_lscwp').html(data+' views');
        }
   }
});
```

Purge the cache to use the updated pages.