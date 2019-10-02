<?php
/*
Plugin Name: WP-PostViews
Plugin URI: https://lesterchan.net/portfolio/programming/php/
Description: Enables you to display how many times a post/page had been viewed.
Version: 1.76.1
Author: Lester 'GaMerZ' Chan
Author URI: https://lesterchan.net
Text Domain: wp-postviews
*/


/*
	Copyright 2017  Lester Chan  (email : lesterchan@gmail.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/


### Create Text Domain For Translations
add_action( 'plugins_loaded', 'postviews_textdomain' );
function postviews_textdomain() {
	load_plugin_textdomain( 'wp-postviews', false, dirname( plugin_basename( __FILE__ ) ) );
}


### Function: Post Views Option Menu
add_action('admin_menu', 'postviews_menu');
function postviews_menu() {
	if (function_exists('add_options_page')) {
		add_options_page(__('PostViews', 'wp-postviews'), __('PostViews', 'wp-postviews'), 'manage_options', 'wp-postviews/postviews-options.php') ;
	}
}


### Function: Calculate Post Views
add_action( 'wp_head', 'process_postviews' );
function process_postviews() {
	global $user_ID, $post;
	if ( is_int( $post ) ) {
		$post = get_post( $post );
	}
	if ( ! wp_is_post_revision( $post ) && ! is_preview() ) {
		if ( is_single() || is_page() ) {
			$id = (int) $post->ID;
			$views_options = get_option( 'views_options' );
			if ( !$post_views = get_post_meta( $post->ID, 'views', true ) ) {
				$post_views = 0;
			}
			$should_count = false;
			switch( (int) $views_options['count'] ) {
				case 0:
					$should_count = true;
					break;
				case 1:
					if( empty( $_COOKIE[ USER_COOKIE ] ) && (int) $user_ID === 0 ) {
						$should_count = true;
					}
					break;
				case 2:
					if( (int) $user_ID > 0 ) {
						$should_count = true;
					}
					break;
			}
			if ( isset( $views_options['exclude_bots'] ) && (int) $views_options['exclude_bots'] === 1 ) {
				$bots = array(
					'Google Bot' => 'google'
					, 'MSN' => 'msnbot'
					, 'Alex' => 'ia_archiver'
					, 'Lycos' => 'lycos'
					, 'Ask Jeeves' => 'jeeves'
					, 'Altavista' => 'scooter'
					, 'AllTheWeb' => 'fast-webcrawler'
					, 'Inktomi' => 'slurp@inktomi'
					, 'Turnitin.com' => 'turnitinbot'
					, 'Technorati' => 'technorati'
					, 'Yahoo' => 'yahoo'
					, 'Findexa' => 'findexa'
					, 'NextLinks' => 'findlinks'
					, 'Gais' => 'gaisbo'
					, 'WiseNut' => 'zyborg'
					, 'WhoisSource' => 'surveybot'
					, 'Bloglines' => 'bloglines'
					, 'BlogSearch' => 'blogsearch'
					, 'PubSub' => 'pubsub'
					, 'Syndic8' => 'syndic8'
					, 'RadioUserland' => 'userland'
					, 'Gigabot' => 'gigabot'
					, 'Become.com' => 'become.com'
					, 'Baidu' => 'baiduspider'
					, 'so.com' => '360spider'
					, 'Sogou' => 'spider'
					, 'soso.com' => 'sosospider'
					, 'Yandex' => 'yandex'
				);
				$useragent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? $_SERVER['HTTP_USER_AGENT'] : '';
				foreach ( $bots as $name => $lookfor ) {
					if ( ! empty( $useragent ) && ( false !== stripos( $useragent, $lookfor ) ) ) {
						$should_count = false;
						break;
					}
				}
			}
			$should_count = apply_filters( 'postviews_should_count', $should_count, $id );
			if( $should_count && ( ( isset( $views_options['use_ajax'] ) && (int) $views_options['use_ajax'] === 0 ) || ( !defined( 'WP_CACHE' ) || !WP_CACHE ) ) ) {
				update_post_meta( $id, 'views', $post_views + 1 );
				do_action( 'postviews_increment_views', $post_views + 1 );
			}
		}
	}
}


### Function: Calculate Post Views With WP_CACHE Enabled
add_action('wp_enqueue_scripts', 'wp_postview_cache_count_enqueue');
function wp_postview_cache_count_enqueue() {
	global $user_ID, $post;

	if ( !defined( 'WP_CACHE' ) || !WP_CACHE ) {
		return;
	}

	$views_options = get_option( 'views_options' );

	if ( isset( $views_options['use_ajax'] ) && (int) $views_options['use_ajax'] === 0 ) {
		return;
	}

	if ( !wp_is_post_revision( $post ) && ( is_single() || is_page() ) ) {
		$should_count = false;
		switch( (int) $views_options['count'] ) {
			case 0:
				$should_count = true;
				break;
			case 1:
				if ( empty( $_COOKIE[USER_COOKIE] ) && (int) $user_ID === 0) {
					$should_count = true;
				}
				break;
			case 2:
				if ( (int) $user_ID > 0 ) {
					$should_count = true;
				}
				break;
		}

		$should_count = apply_filters( 'postviews_should_count', $should_count, (int) $post->ID );
		if ( $should_count ) {
			wp_enqueue_script( 'wp-postviews-cache', plugins_url( 'postviews-cache.js', __FILE__ ), array( 'jquery' ), '1.68', true );
			wp_localize_script( 'wp-postviews-cache', 'viewsCacheL10n', array( 'admin_ajax_url' => admin_url( 'admin-ajax.php' ), 'post_id' => (int) $post->ID ) );
		}
	}
}


### Function: Determine If Post Views Should Be Displayed (By: David Potter)
function should_views_be_displayed($views_options = null) {
	if ($views_options == null) {
		$views_options = get_option('views_options');
	}
	$display_option = 0;
	if (is_home()) {
		if (array_key_exists('display_home', $views_options)) {
			$display_option = $views_options['display_home'];
		}
	} elseif (is_single()) {
		if (array_key_exists('display_single', $views_options)) {
			$display_option = $views_options['display_single'];
		}
	} elseif (is_page()) {
		if (array_key_exists('display_page', $views_options)) {
			$display_option = $views_options['display_page'];
		}
	} elseif (is_archive()) {
		if (array_key_exists('display_archive', $views_options)) {
			$display_option = $views_options['display_archive'];
		}
	} elseif (is_search()) {
		if (array_key_exists('display_search', $views_options)) {
			$display_option = $views_options['display_search'];
		}
	} else {
		if (array_key_exists('display_other', $views_options)) {
			$display_option = $views_options['display_other'];
		}
	}
	return (($display_option == 0) || (($display_option == 1) && is_user_logged_in()));
}


### Function: Display The Post Views
function the_views($display = true, $prefix = '', $postfix = '', $always = false) {
	$post_views = (int) get_post_meta( get_the_ID(), 'views', true );
	$views_options = get_option('views_options');
	if ($always || should_views_be_displayed($views_options)) {
		$output = $prefix.str_replace( array( '%VIEW_COUNT%', '%VIEW_COUNT_ROUNDED%' ), array( number_format_i18n( $post_views ), postviews_round_number( $post_views) ), stripslashes( $views_options['template'] ) ).$postfix;
		if($display) {
			echo apply_filters('the_views', $output);
		} else {
			return apply_filters('the_views', $output);
		}
	}
	elseif (!$display) {
		return '';
	}
}

### Function: Short Code For Inserting Views Into Posts
add_shortcode( 'views', 'views_shortcode' );
function views_shortcode( $atts ) {
	$attributes = shortcode_atts( array( 'id' => 0 ), $atts );
	$id = (int) $attributes['id'];
	if( $id === 0) {
		$id = get_the_ID();
	}
	$views_options = get_option( 'views_options' );
	$post_views = (int) get_post_meta( $id, 'views', true );
	$output = str_replace( array( '%VIEW_COUNT%', '%VIEW_COUNT_ROUNDED%' ), array( number_format_i18n( $post_views ), postviews_round_number( $post_views) ), stripslashes( $views_options['template'] ) );

	return apply_filters( 'the_views', $output );
}


### Function: Display Least Viewed Page/Post
if ( ! function_exists( 'get_least_viewed' ) ) {
	function get_least_viewed( $mode = '', $limit = 10, $chars = 0, $display = true ) {
		$views_options = get_option( 'views_options' );
		$output = '';

		$least_viewed = new WP_Query( array(
			'post_type'         => ( empty( $mode ) || $mode === 'both' ) ? 'any' : $mode,
			'posts_per_page'    => $limit,
			'orderby'           => 'meta_value_num',
			'order'             => 'asc',
			'meta_key'          => 'views',
		) );
		if ( $least_viewed->have_posts() ) {
			while ( $least_viewed->have_posts() ) {
				$least_viewed->the_post();

				// Post Views.
				$post_views = get_post_meta( get_the_ID(), 'views', true );

				// Post Title.
				$post_title = get_the_title();
				if ( $chars > 0 ) {
					$post_title = snippet_text( $post_title, $chars );
				}

				// Post First Category.
				$categories = get_the_category();
				$post_category_id = 0;
				if ( ! empty( $categories ) ) {
					$post_category_id = $categories[0]->term_id;
				}

				$temp = stripslashes( $views_options['most_viewed_template'] );
				$temp = str_replace( '%VIEW_COUNT%', number_format_i18n( $post_views ), $temp );
				$temp = str_replace( '%VIEW_COUNT_ROUNDED%', postviews_round_number( $post_views ), $temp );
				$temp = str_replace( '%POST_TITLE%', $post_title, $temp );
				$temp = str_replace( '%POST_EXCERPT%', get_the_excerpt(), $temp );
				$temp = str_replace( '%POST_CONTENT%', get_the_content(), $temp );
				$temp = str_replace( '%POST_URL%', get_permalink(), $temp );
				$temp = str_replace( '%POST_DATE%', get_the_time( get_option( 'date_format' ) ), $temp );
				$temp = str_replace( '%POST_TIME%', get_the_time( get_option( 'time_format' ) ), $temp );
				$temp = str_replace( '%POST_THUMBNAIL%', get_the_post_thumbnail( null,'thumbnail',true ), $temp);
				$temp = str_replace( '%POST_CATEGORY_ID%', $post_category_id, $temp );
				$temp = str_replace( '%POST_AUTHOR%', get_the_author(), $temp );
				$output .= $temp;
			}

			wp_reset_postdata();
		}  else {
			$output = '<li>' . __( 'N/A', 'wp-postviews' ) . '</li>' . "\n";
		}

		if( $display ) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Most Viewed Page/Post
if ( ! function_exists( 'get_most_viewed' ) ) {
	function get_most_viewed( $mode = '', $limit = 10, $chars = 0, $display = true ) {
		$views_options = get_option( 'views_options' );
		$output = '';

		$most_viewed = new WP_Query( array(
			'post_type'         => ( empty( $mode ) || $mode === 'both' ) ? 'any' : $mode,
			'posts_per_page'    => $limit,
			'orderby'           => 'meta_value_num',
			'order'             => 'desc',
			'meta_key'          => 'views',
		) );
		if ( $most_viewed->have_posts() ) {
			while ( $most_viewed->have_posts() ) {
				$most_viewed->the_post();

				// Post Views.
				$post_views = get_post_meta( get_the_ID(), 'views', true );

				// Post Title.
				$post_title = get_the_title();
				if ( $chars > 0 ) {
					$post_title = snippet_text( $post_title, $chars );
				}

				// Post First Category.
				$categories = get_the_category();
				$post_category_id = 0;
				if ( ! empty( $categories ) ) {
					$post_category_id = $categories[0]->term_id;
				}

				$temp = stripslashes( $views_options['most_viewed_template'] );
				$temp = str_replace( '%VIEW_COUNT%', number_format_i18n( $post_views ), $temp );
				$temp = str_replace( '%VIEW_COUNT_ROUNDED%', postviews_round_number( $post_views ), $temp );
				$temp = str_replace( '%POST_TITLE%', $post_title, $temp );
				$temp = str_replace( '%POST_EXCERPT%', get_the_excerpt(), $temp );
				$temp = str_replace( '%POST_CONTENT%', get_the_content(), $temp );
				$temp = str_replace( '%POST_URL%', get_permalink(), $temp );
				$temp = str_replace( '%POST_DATE%', get_the_time( get_option( 'date_format' ) ), $temp );
				$temp = str_replace( '%POST_TIME%', get_the_time( get_option( 'time_format' ) ), $temp );
				$temp = str_replace( '%POST_THUMBNAIL%', get_the_post_thumbnail( null,'thumbnail',true ), $temp);
				$temp = str_replace( '%POST_CATEGORY_ID%', $post_category_id, $temp );
				$temp = str_replace( '%POST_AUTHOR%', get_the_author(), $temp );
				$output .= $temp;
			}

			wp_reset_postdata();
		}  else {
			$output = '<li>' . __( 'N/A', 'wp-postviews' ) . '</li>' . "\n";
		}

		if( $display ) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Least Viewed Page/Post By Category ID
if ( ! function_exists( 'get_least_viewed_category' ) ) {
	function get_least_viewed_category( $category_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true ) {
		$views_options = get_option( 'views_options' );
		$output = '';

		$least_viewed = new WP_Query( array(
			'post_type'         => ( empty( $mode ) || $mode === 'both' ) ? 'any' : $mode,
			'posts_per_page'    => $limit,
			'category__in'      => (array) $category_id,
			'orderby'           => 'meta_value_num',
			'order'             => 'asc',
			'meta_key'          => 'views',
		) );
		if ( $least_viewed->have_posts() ) {
			while ( $least_viewed->have_posts() ) {
				$least_viewed->the_post();

				// Post Views.
				$post_views = get_post_meta( get_the_ID(), 'views', true );

				// Post Title.
				$post_title = get_the_title();
				if ( $chars > 0 ) {
					$post_title = snippet_text( $post_title, $chars );
				}

				// Post First Category.
				$categories = get_the_category();
				$post_category_id = 0;
				if ( ! empty( $categories ) ) {
					$post_category_id = $categories[0]->term_id;
				}

				$temp = stripslashes( $views_options['most_viewed_template'] );
				$temp = str_replace( '%VIEW_COUNT%', number_format_i18n( $post_views ), $temp );
				$temp = str_replace( '%VIEW_COUNT_ROUNDED%', postviews_round_number( $post_views ), $temp );
				$temp = str_replace( '%POST_TITLE%', $post_title, $temp );
				$temp = str_replace( '%POST_EXCERPT%', get_the_excerpt(), $temp );
				$temp = str_replace( '%POST_CONTENT%', get_the_content(), $temp );
				$temp = str_replace( '%POST_URL%', get_permalink(), $temp );
				$temp = str_replace( '%POST_DATE%', get_the_time( get_option( 'date_format' ) ), $temp );
				$temp = str_replace( '%POST_TIME%', get_the_time( get_option( 'time_format' ) ), $temp );
				$temp = str_replace( '%POST_THUMBNAIL%', get_the_post_thumbnail( null,'thumbnail',true ), $temp);
				$temp = str_replace( '%POST_CATEGORY_ID%', $post_category_id, $temp );
				$temp = str_replace( '%POST_AUTHOR%', get_the_author(), $temp );
				$output .= $temp;
			}

			wp_reset_postdata();
		}  else {
			$output = '<li>' . __( 'N/A', 'wp-postviews' ) . '</li>' . "\n";
		}

		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Most Viewed Page/Post By Category ID
if ( ! function_exists( 'get_most_viewed_category' ) ) {
	function get_most_viewed_category( $category_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true ) {
		$views_options = get_option( 'views_options' );
		$output = '';

		$most_viewed = new WP_Query( array(
			'post_type'         => ( empty( $mode ) || $mode === 'both' ) ? 'any' : $mode,
			'posts_per_page'    => $limit,
			'category__in'      => (array) $category_id,
			'orderby'           => 'meta_value_num',
			'order'             => 'desc',
			'meta_key'          => 'views',
		) );
		if ( $most_viewed->have_posts() ) {
			while ( $most_viewed->have_posts() ) {
				$most_viewed->the_post();

				// Post Views.
				$post_views = get_post_meta( get_the_ID(), 'views', true );

				// Post Title.
				$post_title = get_the_title();
				if ( $chars > 0 ) {
					$post_title = snippet_text( $post_title, $chars );
				}

				// Post First Category.
				$categories = get_the_category();
				$post_category_id = 0;
				if ( ! empty( $categories ) ) {
					$post_category_id = $categories[0]->term_id;
				}

				$temp = stripslashes( $views_options['most_viewed_template'] );
				$temp = str_replace( '%VIEW_COUNT%', number_format_i18n( $post_views ), $temp );
				$temp = str_replace( '%VIEW_COUNT_ROUNDED%', postviews_round_number( $post_views ), $temp );
				$temp = str_replace( '%POST_TITLE%', $post_title, $temp );
				$temp = str_replace( '%POST_EXCERPT%', get_the_excerpt(), $temp );
				$temp = str_replace( '%POST_CONTENT%', get_the_content(), $temp );
				$temp = str_replace( '%POST_URL%', get_permalink(), $temp );
				$temp = str_replace( '%POST_DATE%', get_the_time( get_option( 'date_format' ) ), $temp );
				$temp = str_replace( '%POST_TIME%', get_the_time( get_option( 'time_format' ) ), $temp );
				$temp = str_replace( '%POST_THUMBNAIL%', get_the_post_thumbnail( null,'thumbnail',true ), $temp);
				$temp = str_replace( '%POST_CATEGORY_ID%', $post_category_id, $temp );
				$temp = str_replace( '%POST_AUTHOR%', get_the_author(), $temp );
				$output .= $temp;
			}

			wp_reset_postdata();
		}  else {
			$output = '<li>' . __( 'N/A', 'wp-postviews' ) . '</li>' . "\n";
		}

		if ( $display ) {
			echo $output;
		} else {
			return $output;
		}
	}
}

### Function: Display Least Viewed Page/Post By Tag ID
if ( ! function_exists( 'get_least_viewed_tag' ) ) {
	function get_least_viewed_tag( $tag_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true ) {
		$views_options = get_option( 'views_options' );
		$output = '';

		$least_viewed = new WP_Query( array(
			'post_type'         => ( empty( $mode ) || $mode === 'both' ) ? 'any' : $mode,
			'posts_per_page'    => $limit,
			'tag__in'           => (array) $tag_id,
			'orderby'           => 'meta_value_num',
			'order'             => 'asc',
			'meta_key'          => 'views',
		) );
		if ( $least_viewed->have_posts() ) {
			while ( $least_viewed->have_posts() ) {
				$least_viewed->the_post();

				// Post Views.
				$post_views = get_post_meta( get_the_ID(), 'views', true );

				// Post Title.
				$post_title = get_the_title();
				if ( $chars > 0 ) {
					$post_title = snippet_text( $post_title, $chars );
				}

				// Post First Category.
				$categories = get_the_category();
				$post_category_id = 0;
				if ( ! empty( $categories ) ) {
					$post_category_id = $categories[0]->term_id;
				}

				$temp = stripslashes( $views_options['most_viewed_template'] );
				$temp = str_replace( '%VIEW_COUNT%', number_format_i18n( $post_views ), $temp );
				$temp = str_replace( '%VIEW_COUNT_ROUNDED%', postviews_round_number( $post_views ), $temp );
				$temp = str_replace( '%POST_TITLE%', $post_title, $temp );
				$temp = str_replace( '%POST_EXCERPT%', get_the_excerpt(), $temp );
				$temp = str_replace( '%POST_CONTENT%', get_the_content(), $temp );
				$temp = str_replace( '%POST_URL%', get_permalink(), $temp );
				$temp = str_replace( '%POST_DATE%', get_the_time( get_option( 'date_format' ) ), $temp );
				$temp = str_replace( '%POST_TIME%', get_the_time( get_option( 'time_format' ) ), $temp );
				$temp = str_replace( '%POST_THUMBNAIL%', get_the_post_thumbnail( null,'thumbnail',true ), $temp);
				$temp = str_replace( '%POST_CATEGORY_ID%', $post_category_id, $temp );
				$temp = str_replace( '%POST_AUTHOR%', get_the_author(), $temp );
				$output .= $temp;
			}

			wp_reset_postdata();
		}  else {
			$output = '<li>' . __( 'N/A', 'wp-postviews' ) . '</li>' . "\n";
		}

		if ( $display ) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Most Viewed Page/Post By Tag ID
if ( ! function_exists( 'get_most_viewed_tag' ) ) {
	function get_most_viewed_tag( $tag_id = 0, $mode = '', $limit = 10, $chars = 0, $display = true ) {
		$views_options = get_option( 'views_options' );
		$output = '';

		$most_viewed = new WP_Query( array(
			'post_type'         => ( empty( $mode ) || $mode === 'both' ) ? 'any' : $mode,
			'posts_per_page'    => $limit,
			'tag__in'           => (array) $tag_id,
			'orderby'           => 'meta_value_num',
			'order'             => 'desc',
			'meta_key'          => 'views',
		) );
		if ( $most_viewed->have_posts() ) {
			while ( $most_viewed->have_posts() ) {
				$most_viewed->the_post();

				// Post Views.
				$post_views = get_post_meta( get_the_ID(), 'views', true );

				// Post Title.
				$post_title = get_the_title();
				if ( $chars > 0 ) {
					$post_title = snippet_text( $post_title, $chars );
				}

				// Post First Category.
				$categories = get_the_category();
				$post_category_id = 0;
				if ( ! empty( $categories ) ) {
					$post_category_id = $categories[0]->term_id;
				}

				$temp = stripslashes( $views_options['most_viewed_template'] );
				$temp = str_replace( '%VIEW_COUNT%', number_format_i18n( $post_views ), $temp );
				$temp = str_replace( '%VIEW_COUNT_ROUNDED%', postviews_round_number( $post_views ), $temp );
				$temp = str_replace( '%POST_TITLE%', $post_title, $temp );
				$temp = str_replace( '%POST_EXCERPT%', get_the_excerpt(), $temp );
				$temp = str_replace( '%POST_CONTENT%', get_the_content(), $temp );
				$temp = str_replace( '%POST_URL%', get_permalink(), $temp );
				$temp = str_replace( '%POST_DATE%', get_the_time( get_option( 'date_format' ) ), $temp );
				$temp = str_replace( '%POST_TIME%', get_the_time( get_option( 'time_format' ) ), $temp );
				$temp = str_replace( '%POST_THUMBNAIL%', get_the_post_thumbnail( null,'thumbnail',true ), $temp);
				$temp = str_replace( '%POST_CATEGORY_ID%', $post_category_id, $temp );
				$temp = str_replace( '%POST_AUTHOR%', get_the_author(), $temp );
				$output .= $temp;
			}

			wp_reset_postdata();
		}  else {
			$output = '<li>' . __( 'N/A', 'wp-postviews' ) . '</li>' . "\n";
		}

		if($display) {
			echo $output;
		} else {
			return $output;
		}
	}
}


### Function: Display Total Views
if(!function_exists('get_totalviews')) {
	function get_totalviews($display = true) {
		global $wpdb;
		$total_views = (int) $wpdb->get_var("SELECT SUM(meta_value+0) FROM $wpdb->postmeta WHERE meta_key = 'views'" );
		if($display) {
			echo number_format_i18n($total_views);
		} else {
			return $total_views;
		}
	}
}


### Function: Snippet Text
if(!function_exists('snippet_text')) {
	function snippet_text($text, $length = 0) {
		if (defined('MB_OVERLOAD_STRING')) {
		  $text = @html_entity_decode($text, ENT_QUOTES, get_option('blog_charset'));
			 if (mb_strlen($text) > $length) {
				return htmlentities(mb_substr($text,0,$length), ENT_COMPAT, get_option('blog_charset')).'...';
			 } else {
				return htmlentities($text, ENT_COMPAT, get_option('blog_charset'));
			 }
		} else {
			$text = @html_entity_decode($text, ENT_QUOTES, get_option('blog_charset'));
			 if (strlen($text) > $length) {
				return htmlentities(substr($text,0,$length), ENT_COMPAT, get_option('blog_charset')).'...';
			 } else {
				return htmlentities($text, ENT_COMPAT, get_option('blog_charset'));
			 }
		}
	}
}


### Function: Modify Default WordPress Listing To Make It Sorted By Post Views
function views_fields($content) {
	global $wpdb;
	$content .= ", ($wpdb->postmeta.meta_value+0) AS views";
	return $content;
}
function views_join($content) {
	global $wpdb;
	$content .= " LEFT JOIN $wpdb->postmeta ON $wpdb->postmeta.post_id = $wpdb->posts.ID";
	return $content;
}
function views_where($content) {
	global $wpdb;
	$content .= " AND $wpdb->postmeta.meta_key = 'views'";
	return $content;
}
function views_orderby($content) {
	$orderby = trim(addslashes(get_query_var('v_orderby')));
	if(empty($orderby) || ($orderby != 'asc' && $orderby != 'desc')) {
		$orderby = 'desc';
	}
	$content = " views $orderby";
	return $content;
}


### Function: Add Views Custom Fields
add_action('publish_post', 'add_views_fields');
add_action('publish_page', 'add_views_fields');
function add_views_fields($post_ID) {
	global $wpdb;
	if(!wp_is_post_revision($post_ID)) {
		add_post_meta($post_ID, 'views', 0, true);
	}
}


### Function: Views Public Variables
add_filter('query_vars', 'views_variables');
function views_variables($public_query_vars) {
	$public_query_vars[] = 'v_sortby';
	$public_query_vars[] = 'v_orderby';
	return $public_query_vars;
}


### Function: Sort Views Posts
add_action('pre_get_posts', 'views_sorting');
function views_sorting($local_wp_query) {
	if($local_wp_query->get('v_sortby') == 'views') {
		add_filter('posts_fields', 'views_fields');
		add_filter('posts_join', 'views_join');
		add_filter('posts_where', 'views_where');
		add_filter('posts_orderby', 'views_orderby');
	} else {
		remove_filter('posts_fields', 'views_fields');
		remove_filter('posts_join', 'views_join');
		remove_filter('posts_where', 'views_where');
		remove_filter('posts_orderby', 'views_orderby');
	}
}


### Function: Plug Into WP-Stats
add_action( 'plugins_loaded', 'postviews_wp_stats' );
function postviews_wp_stats() {
	add_filter( 'wp_stats_page_admin_plugins', 'postviews_page_admin_general_stats' );
	add_filter( 'wp_stats_page_admin_most', 'postviews_page_admin_most_stats' );
	add_filter( 'wp_stats_page_plugins', 'postviews_page_general_stats' );
	add_filter( 'wp_stats_page_most', 'postviews_page_most_stats' );
}


### Function: Add WP-PostViews General Stats To WP-Stats Page Options
function postviews_page_admin_general_stats($content) {
	$stats_display = get_option('stats_display');
	if ( (int) $stats_display['views'] === 1 ) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_views" value="views" checked="checked" />&nbsp;&nbsp;<label for="wpstats_views">'.__('WP-PostViews', 'wp-postviews').'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_views" value="views" />&nbsp;&nbsp;<label for="wpstats_views">'.__('WP-PostViews', 'wp-postviews').'</label><br />'."\n";
	}
	return $content;
}


### Function: Add WP-PostViews Top Most/Highest Stats To WP-Stats Page Options
function postviews_page_admin_most_stats($content) {
	$stats_display = get_option('stats_display');
	$stats_mostlimit = (int) get_option('stats_mostlimit');
	if ( (int) $stats_display['viewed_most_post'] === 1 ) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_viewed_most_post" value="viewed_most_post" checked="checked" />&nbsp;&nbsp;<label for="wpstats_viewed_most_post">'.sprintf(_n('%s Most Viewed Post', '%s Most Viewed Posts', $stats_mostlimit, 'wp-postviews'), number_format_i18n($stats_mostlimit)).'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_viewed_most_post" value="viewed_most_post" />&nbsp;&nbsp;<label for="wpstats_viewed_most_post">'.sprintf(_n('%s Most Viewed Post', '%s Most Viewed Posts', $stats_mostlimit, 'wp-postviews'), number_format_i18n($stats_mostlimit)).'</label><br />'."\n";
	}
	if ( (int) $stats_display['viewed_most_page'] === 1 ) {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_viewed_most_page" value="viewed_most_page" checked="checked" />&nbsp;&nbsp;<label for="wpstats_viewed_most_page">'.sprintf(_n('%s Most Viewed Page', '%s Most Viewed Pages', $stats_mostlimit, 'wp-postviews'), number_format_i18n($stats_mostlimit)).'</label><br />'."\n";
	} else {
		$content .= '<input type="checkbox" name="stats_display[]" id="wpstats_viewed_most_page" value="viewed_most_page" />&nbsp;&nbsp;<label for="wpstats_viewed_most_page">'.sprintf(_n('%s Most Viewed Page', '%s Most Viewed Pages', $stats_mostlimit, 'wp-postviews'), number_format_i18n($stats_mostlimit)).'</label><br />'."\n";
	}
	return $content;
}


### Function: Add WP-PostViews General Stats To WP-Stats Page
function postviews_page_general_stats($content) {
	$stats_display = get_option('stats_display');
	if ( (int) $stats_display['views'] === 1 ) {
		$content .= '<p><strong>'.__('WP-PostViews', 'wp-postviews').'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= '<li>'.sprintf(_n('<strong>%s</strong> view was generated.', '<strong>%s</strong> views were generated.', get_totalviews(false), 'wp-postviews'), number_format_i18n(get_totalviews(false))).'</li>'."\n";
		$content .= '</ul>'."\n";
	}
	return $content;
}


### Function: Add WP-PostViews Top Most/Highest Stats To WP-Stats Page
function postviews_page_most_stats($content) {
	$stats_display = get_option('stats_display');
	$stats_mostlimit = (int) get_option('stats_mostlimit');
	if ( (int) $stats_display['viewed_most_post'] === 1 ) {
		$content .= '<p><strong>'.sprintf(_n('%s Most Viewed Post', '%s Most Viewed Posts', $stats_mostlimit, 'wp-postviews'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= get_most_viewed('post', $stats_mostlimit, 0, false);
		$content .= '</ul>'."\n";
	}
	if($stats_display['viewed_most_page'] == 1) {
		$content .= '<p><strong>'.sprintf(_n('%s Most Viewed Page', '%s Most Viewed Pages', $stats_mostlimit, 'wp-postviews'), number_format_i18n($stats_mostlimit)).'</strong></p>'."\n";
		$content .= '<ul>'."\n";
		$content .= get_most_viewed('page', $stats_mostlimit, 0, false);
		$content .= '</ul>'."\n";
	}
	return $content;
}


### Function: Increment Post Views
add_action( 'wp_ajax_postviews', 'increment_views' );
add_action( 'wp_ajax_nopriv_postviews', 'increment_views' );
function increment_views() {
	if ( empty( $_GET['postviews_id'] ) ) {
		return;
	}

	if ( !defined( 'WP_CACHE' ) || ! WP_CACHE ) {
		return;
	}

	$views_options = get_option( 'views_options' );

	if ( isset( $views_options['use_ajax'] ) && (int) $views_options['use_ajax'] === 0 ) {
		return;
	}

	$post_id = (int) sanitize_key( $_GET['postviews_id'] );
	if( $post_id > 0 ) {
		$post_views = get_post_custom( $post_id );
		$post_views = (int) $post_views['views'][0];
		update_post_meta( $post_id, 'views', ( $post_views + 1 ) );
		do_action( 'postviews_increment_views_ajax', ( $post_views + 1 ) );
		echo ( $post_views + 1 );
		exit();
	}
}

### Function Show Post Views Column in WP-Admin
add_action('manage_posts_custom_column', 'add_postviews_column_content');
add_filter('manage_posts_columns', 'add_postviews_column');
add_action('manage_pages_custom_column', 'add_postviews_column_content');
add_filter('manage_pages_columns', 'add_postviews_column');
function add_postviews_column($defaults) {
	$defaults['views'] = __( 'Views', 'wp-postviews' );
	return $defaults;
}


### Functions Fill In The Views Count
function add_postviews_column_content($column_name) {
	if ($column_name === 'views' ) {
		if ( function_exists('the_views' ) ) {
			the_views( true, '', '', true );
		}
	}
}


### Function Sort Columns
add_filter( 'manage_edit-post_sortable_columns', 'sort_postviews_column');
add_filter( 'manage_edit-page_sortable_columns', 'sort_postviews_column' );
function sort_postviews_column( $defaults ) {
	$defaults['views'] = 'views';
	return $defaults;
}
add_action('pre_get_posts', 'sort_postviews');
function sort_postviews($query) {
	if ( ! is_admin() ) {
		return;
	}
	$orderby = $query->get('orderby');
	if ( 'views' === $orderby ) {
		$query->set( 'meta_key', 'views' );
		$query->set( 'orderby', 'meta_value_num' );
	}
}

### Function: Round Numbers To K (Thousand), M (Million) or B (Billion)
function postviews_round_number( $number, $min_value = 1000, $decimal = 1 ) {
	if( $number < $min_value ) {
		return number_format_i18n( $number );
	}
	$alphabets = array( 1000000000 => 'B', 1000000 => 'M', 1000 => 'K' );
	foreach( $alphabets as $key => $value )
		if( $number >= $key ) {
			return round( $number / $key, $decimal ) . '' . $value;
		}
}


### Class: WP-PostViews Widget
 class WP_Widget_PostViews extends WP_Widget {
	// Constructor
	public function __construct() {
		$widget_ops = array('description' => __('WP-PostViews views statistics', 'wp-postviews'));
		parent::__construct('views', __('Views', 'wp-postviews'), $widget_ops);
	}

	// Display Widget
	public function widget($args, $instance) {
		$title = apply_filters('widget_title', esc_attr($instance['title']));
		$type = esc_attr($instance['type']);
		$mode = esc_attr($instance['mode']);
		$limit = (int) $instance['limit'];
		$chars = (int) $instance['chars'];
		$cat_ids = explode(',', esc_attr($instance['cat_ids']));
		echo $args['before_widget'] . $args['before_title'] . $title . $args['after_title'];
		echo '<ul>'."\n";
		switch($type) {
			case 'least_viewed':
				get_least_viewed($mode, $limit, $chars);
				break;
			case 'most_viewed':
				get_most_viewed($mode, $limit, $chars);
				break;
			case 'most_viewed_category':
				get_most_viewed_category($cat_ids, $mode, $limit, $chars);
				break;
			case 'least_viewed_category':
				get_least_viewed_category($cat_ids, $mode, $limit, $chars);
				break;
		}
		echo '</ul>'."\n";
		echo  $args['after_widget'];
	}

	// When Widget Control Form Is Posted
	public function update($new_instance, $old_instance) {
		if (!isset($new_instance['submit'])) {
			return false;
		}
		$instance = $old_instance;
		$instance['title'] = strip_tags($new_instance['title']);
		$instance['type'] = strip_tags($new_instance['type']);
		$instance['mode'] = strip_tags($new_instance['mode']);
		$instance['limit'] = (int) $new_instance['limit'];
		$instance['chars'] = (int) $new_instance['chars'];
		$instance['cat_ids'] = strip_tags($new_instance['cat_ids']);
		return $instance;
	}

	// DIsplay Widget Control Form
	public function form($instance) {
		$instance = wp_parse_args((array) $instance, array('title' => __('Views', 'wp-postviews'), 'type' => 'most_viewed', 'mode' => '', 'limit' => 10, 'chars' => 200, 'cat_ids' => '0'));
		$title = esc_attr($instance['title']);
		$type = esc_attr($instance['type']);
		$mode = trim(esc_attr($instance['mode']));
		$limit = (int) $instance['limit'];
		$chars = (int) $instance['chars'];
		$cat_ids = esc_attr($instance['cat_ids']);
		$post_types = get_post_types(array(
			'public' => true
		));
?>
		<p>
			<label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:', 'wp-postviews'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('type'); ?>"><?php _e('Statistics Type:', 'wp-postviews'); ?>
				<select name="<?php echo $this->get_field_name('type'); ?>" id="<?php echo $this->get_field_id('type'); ?>" class="widefat">
					<option value="least_viewed"<?php selected('least_viewed', $type); ?>><?php _e('Least Viewed', 'wp-postviews'); ?></option>
					<option value="least_viewed_category"<?php selected('least_viewed_category', $type); ?>><?php _e('Least Viewed By Category', 'wp-postviews'); ?></option>
					<optgroup>&nbsp;</optgroup>
					<option value="most_viewed"<?php selected('most_viewed', $type); ?>><?php _e('Most Viewed', 'wp-postviews'); ?></option>
					<option value="most_viewed_category"<?php selected('most_viewed_category', $type); ?>><?php _e('Most Viewed By Category', 'wp-postviews'); ?></option>
				</select>
			</label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('mode'); ?>"><?php _e('Include Views From:', 'wp-postviews'); ?>
				<select name="<?php echo $this->get_field_name('mode'); ?>" id="<?php echo $this->get_field_id('mode'); ?>" class="widefat">
					<option value=""<?php selected('', $mode); ?>><?php _e('All', 'wp-postviews'); ?></option>
					<?php if($post_types > 0): ?>
						<?php foreach($post_types as $post_type): ?>
							<option value="<?php echo $post_type; ?>"<?php selected($post_type, $mode); ?>><?php printf(__('%s Only', 'wp-postviews'), ucfirst($post_type)); ?></option>
						<?php endforeach; ?>
					<?php endif; ?>
				</select>
			</label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('limit'); ?>"><?php _e('No. Of Records To Show:', 'wp-postviews'); ?> <input class="widefat" id="<?php echo $this->get_field_id('limit'); ?>" name="<?php echo $this->get_field_name('limit'); ?>" type="text" value="<?php echo $limit; ?>" /></label>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('chars'); ?>"><?php _e('Maximum Post Title Length (Characters):', 'wp-postviews'); ?> <input class="widefat" id="<?php echo $this->get_field_id('chars'); ?>" name="<?php echo $this->get_field_name('chars'); ?>" type="text" value="<?php echo $chars; ?>" /></label><br />
			<small><?php _e('<strong>0</strong> to disable.', 'wp-postviews'); ?></small>
		</p>
		<p>
			<label for="<?php echo $this->get_field_id('cat_ids'); ?>"><?php _e('Category IDs:', 'wp-postviews'); ?> <span style="color: red;">*</span> <input class="widefat" id="<?php echo $this->get_field_id('cat_ids'); ?>" name="<?php echo $this->get_field_name('cat_ids'); ?>" type="text" value="<?php echo $cat_ids; ?>" /></label><br />
			<small><?php _e('Separate mutiple categories with commas.', 'wp-postviews'); ?></small>
		</p>
		<p style="color: red;">
			<small><?php _e('* If you are not using any category statistics, you can ignore it.', 'wp-postviews'); ?></small>
		<p>
		<input type="hidden" id="<?php echo $this->get_field_id('submit'); ?>" name="<?php echo $this->get_field_name('submit'); ?>" value="1" />
<?php
	}
}


### Function: Init WP-PostViews Widget
add_action( 'widgets_init', 'widget_views_init' );
function widget_views_init() {
	register_widget( 'WP_Widget_PostViews' );
}


### Function: Post Views Options
register_activation_hook( __FILE__, 'views_activation' );
function views_activation( $network_wide ) {
	// Add Options
	$option_name = 'views_options';
	$option = array(
		'count' => 1,
		'exclude_bots' => 0,
		'display_home' => 0,
		'display_single' => 0,
		'display_page' => 0,
		'display_archive' => 0,
		'display_search' => 0,
		'display_other' => 0,
		'use_ajax' => 1,
		'template' => __( '%VIEW_COUNT% views', 'wp-postviews' ),
		'most_viewed_template' => '<li><a href="%POST_URL%"  title="%POST_TITLE%">%POST_TITLE%</a> - %VIEW_COUNT% '.__('views', 'wp-postviews').'</li>'
	);

	if ( is_multisite() && $network_wide ) {
		$ms_sites = function_exists( 'get_sites' ) ? get_sites() : wp_get_sites();

		if( 0 < count( $ms_sites ) ) {
			foreach ( $ms_sites as $ms_site ) {
				$blog_id = class_exists( 'WP_Site' ) ? $ms_site->blog_id : $ms_site['blog_id'];
				switch_to_blog( $blog_id );
				add_option( $option_name, $option );
				restore_current_blog();
			}
		}
	} else {
		add_option( $option_name, $option );
	}
}

### Function: Parse View Options
function views_options_parse( $key ) {
	return ! empty( $_POST[ $key ] ) ? $_POST[ $key ] : null;
}
