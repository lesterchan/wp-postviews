<?php
### Variables Variables Variables
$base_name = plugin_basename( 'wp-postviews/postviews-options.php' );
$base_page = 'admin.php?page='.$base_name;
$id = ( isset($_GET['id'] ) ? (int) sanitize_key( $_GET['id'] ) : 0 );
$mode = ( isset($_GET['mode'] ) ? sanitize_key( trim( $_GET['mode'] ) ) : '' );
$text = '';

### Form Processing
if(!empty($_POST['Submit'] )) {
	check_admin_referer( 'wp-postviews_options' );
	$views_options = array(
		  'count'                   => (int) sanitize_key( views_options_parse('views_count') )
		, 'exclude_bots'            => (int) sanitize_key( views_options_parse('views_exclude_bots') )
		, 'display_home'            => (int) sanitize_key( views_options_parse('views_display_home') )
		, 'display_single'          => (int) sanitize_key( views_options_parse('views_display_single') )
		, 'display_page'            => (int) sanitize_key( views_options_parse('views_display_page') )
		, 'display_archive'         => (int) sanitize_key( views_options_parse('views_display_archive') )
		, 'display_search'          => (int) sanitize_key( views_options_parse('views_display_search') )
		, 'display_other'           => (int) sanitize_key( views_options_parse('views_display_other') )
		, 'use_ajax'                => (int) sanitize_key( views_options_parse('views_use_ajax') )
		, 'template'                => wp_kses_post( trim( views_options_parse('views_template_template') ) )
		, 'most_viewed_template'    => wp_kses_post( trim( views_options_parse('views_template_most_viewed') ) )
	);
	$update_views_queries = array();
	$update_views_text = array();
	$update_views_queries[] = update_option( 'views_options', $views_options );
	$update_views_text[] = __( 'Post Views Options', 'wp-postviews' );
	$i = 0;

	foreach( $update_views_queries as $update_views_query ) {
		if( $update_views_query ) {
			$text .= '<p style="color: green;">' . $update_views_text[$i] . ' ' . __( 'Updated', 'wp-postviews' ) . '</p>';
		}
		$i++;
	}
	if( empty( $text ) ) {
		$text = '<p style="color: red;">' . __( 'No Post Views Option Updated', 'wp-postviews' ) . '</p>';
	}
}

$views_options = get_option( 'views_options' );

// Default
if( !isset ( $views_options['use_ajax'] ) ) {
	$views_options['use_ajax'] = 1;
}
?>
<script type="text/javascript">
	/* <![CDATA[*/
	function views_default_templates(template) {
		var default_template;
		switch(template) {
			case 'template':
				default_template = "<?php _e( '%VIEW_COUNT% views', 'wp-postviews' ); ?>";
				break;
			case 'most_viewed':
				default_template = "<li><a href=\"%POST_URL%\"  title=\"%POST_TITLE%\">%POST_TITLE%</a> - %VIEW_COUNT% <?php _e( 'views', 'wp-postviews' ); ?></li>";
				break;
		}
		jQuery("#views_template_" + template).val(default_template);
	}
	/* ]]> */
</script>
<?php if( !empty( $text ) ) { echo '<div id="message" class="updated fade"><p>' . $text . '</p></div>'; } ?>
<form method="post" action="<?php echo admin_url( 'admin.php?page=' . plugin_basename( __FILE__ ) ); ?>">
<?php wp_nonce_field( 'wp-postviews_options' ); ?>
<div class="wrap">
	<h2><?php _e( 'Post Views Options', 'wp-postviews' ); ?></h2>
	<table class="form-table">
		 <tr>
			<td valign="top" width="30%"><strong><?php _e( 'Count Views From:', 'wp-postviews' ); ?></strong></td>
			<td valign="top">
				<select name="views_count" size="1">
					<option value="0"<?php selected( '0', $views_options['count'] ); ?>><?php _e( 'Everyone', 'wp-postviews' ); ?></option>
					<option value="1"<?php selected( '1', $views_options['count'] ); ?>><?php _e( 'Guests Only', 'wp-postviews' ); ?></option>
					<option value="2"<?php selected( '2', $views_options['count'] ); ?>><?php _e( 'Registered Users Only', 'wp-postviews' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td valign="top" width="30%"><strong><?php _e( 'Exclude Bot Views:', 'wp-postviews' ); ?></strong></td>
			<td valign="top">
				<select name="views_exclude_bots" size="1">
					<option value="0"<?php selected( '0', $views_options['exclude_bots'] ); ?>><?php _e( 'No', 'wp-postviews' ); ?></option>
					<option value="1"<?php selected( '1', $views_options['exclude_bots'] ); ?>><?php _e( 'Yes', 'wp-postviews' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td valign="top" width="30%"><strong><?php _e( 'Use AJAX To Update Views:', 'wp-postviews' ); ?></strong></td>
			<td valign="top">
				<select name="views_use_ajax" size="1">
					<option value="0"<?php selected( '0', $views_options['use_ajax'] ); ?>><?php _e( 'No', 'wp-postviews' ); ?></option>
					<option value="1"<?php selected( '1', $views_options['use_ajax'] ); ?>><?php _e( 'Yes', 'wp-postviews' ); ?></option>
				</select>
				<p>
					<?php _e( 'You have caching enabled for your WordPress installation, by default WP-PostViews will use AJAX to update the view count. However in some cases, you might not want it.', 'wp-postviews' ); ?>
				</p>
			</td>
		</tr>
		<tr>
			<td valign="top">
				<strong><?php _e( 'Views Template:', 'wp-postviews' ); ?></strong><br /><br />
				<?php _e( 'Allowed Variables:', 'wp-postviews' ); ?><br />
				- %VIEW_COUNT%<br />
				- %VIEW_COUNT_ROUNDED%<br /><br />
				<input type="button" name="RestoreDefault" value="<?php _e( 'Restore Default Template', 'wp-postviews' ); ?>" onclick="views_default_templates( 'template' );" class="button" />
			</td>
			<td valign="top">
				<input type="text" id="views_template_template" name="views_template_template" size="70" value="<?php echo htmlspecialchars(stripslashes($views_options['template'] )); ?>" />
			</td>
		</tr>
		<tr>
			<td valign="top">
				<strong><?php _e( 'Most Viewed Template:', 'wp-postviews' ); ?></strong><br /><br />
				<?php _e( 'Allowed Variables:', 'wp-postviews' ); ?><br />
				- %VIEW_COUNT%<br />
				- %VIEW_COUNT_ROUNDED%<br />
				- %POST_TITLE%<br />
				- %POST_DATE%<br />
				- %POST_TIME%<br />
				- %POST_EXCERPT%<br />
				- %POST_CONTENT%<br />
				- %POST_URL%<br />
				- %POST_THUMBNAIL%<br />
				- %POST_CATEGORY_ID%<br /><br />
				<input type="button" name="RestoreDefault" value="<?php _e( 'Restore Default Template', 'wp-postviews' ); ?>" onclick="views_default_templates( 'most_viewed' );" class="button" />
			</td>
			<td valign="top">
				<textarea cols="80" rows="15"  id="views_template_most_viewed" name="views_template_most_viewed"><?php echo htmlspecialchars(stripslashes($views_options['most_viewed_template'] )); ?></textarea>
			</td>
		</tr>
	</table>
	<h3><?php _e( 'Display Options', 'wp-postviews' ); ?></h3>
	<p><?php _e( 'These options specify where the view counts should be displayed and to whom. 	By default view counts will be displayed to all visitors. Note that the theme files must contain a call to <code>the_views()</code> in order for any view count to be displayed.', 'wp-postviews' ); ?></p>
	<table class="form-table">
		<tr>
			<td valign="top"><strong><?php _e( 'Home Page:', 'wp-postviews' ); ?></strong></td>
			<td>
				<select name="views_display_home" size="1">
					<option value="0"<?php selected( '0', $views_options['display_home'] ); ?>><?php _e( 'Display to everyone', 'wp-postviews' ); ?></option>
					<option value="1"<?php selected( '1', $views_options['display_home'] ); ?>><?php _e( 'Display to registered users only', 'wp-postviews' ); ?></option>
					<option value="2"<?php selected( '2', $views_options['display_home'] ); ?>><?php _e( 'Don\'t display on home page', 'wp-postviews' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td valign="top"><strong><?php _e( 'Single Posts:', 'wp-postviews' ); ?></strong></td>
			<td>
				<select name="views_display_single" size="1">
					<option value="0"<?php selected( '0', $views_options['display_single'] ); ?>><?php _e( 'Display to everyone', 'wp-postviews' ); ?></option>
					<option value="1"<?php selected( '1', $views_options['display_single'] ); ?>><?php _e( 'Display to registered users only', 'wp-postviews' ); ?></option>
					<option value="2"<?php selected( '2', $views_options['display_single'] ); ?>><?php _e( 'Don\'t display on single posts', 'wp-postviews' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td valign="top"><strong><?php _e( 'Pages:', 'wp-postviews' ); ?></strong></td>
			<td>
				<select name="views_display_page" size="1">
					<option value="0"<?php selected( '0', $views_options['display_page'] ); ?>><?php _e( 'Display to everyone', 'wp-postviews' ); ?></option>
					<option value="1"<?php selected( '1', $views_options['display_page'] ); ?>><?php _e( 'Display to registered users only', 'wp-postviews' ); ?></option>
					<option value="2"<?php selected( '2', $views_options['display_page'] ); ?>><?php _e( 'Don\'t display on pages', 'wp-postviews' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td valign="top"><strong><?php _e( 'Archive Pages:', 'wp-postviews' ); ?></strong></td>
			<td>
				<select name="views_display_archive" size="1">
					<option value="0"<?php selected( '0', $views_options['display_archive'] ); ?>><?php _e( 'Display to everyone', 'wp-postviews' ); ?></option>
					<option value="1"<?php selected( '1', $views_options['display_archive'] ); ?>><?php _e( 'Display to registered users only', 'wp-postviews' ); ?></option>
					<option value="2"<?php selected( '2', $views_options['display_archive'] ); ?>><?php _e( 'Don\'t display on archive pages', 'wp-postviews' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td valign="top"><strong><?php _e( 'Search Pages:', 'wp-postviews' ); ?></strong></td>
			<td>
				<select name="views_display_search" size="1">
					<option value="0"<?php selected( '0', $views_options['display_search'] ); ?>><?php _e( 'Display to everyone', 'wp-postviews' ); ?></option>
					<option value="1"<?php selected( '1', $views_options['display_search'] ); ?>><?php _e( 'Display to registered users only', 'wp-postviews' ); ?></option>
					<option value="2"<?php selected( '2', $views_options['display_search'] ); ?>><?php _e( 'Don\'t display on search pages', 'wp-postviews' ); ?></option>
				</select>
			</td>
		</tr>
		<tr>
			<td valign="top"><strong><?php _e( 'Other Pages:', 'wp-postviews' ); ?></strong></td>
			<td>
				<select name="views_display_other" size="1">
					<option value="0"<?php selected( '0', $views_options['display_other'] ); ?>><?php _e( 'Display to everyone', 'wp-postviews' ); ?></option>
					<option value="1"<?php selected( '1', $views_options['display_other'] ); ?>><?php _e( 'Display to registered users only', 'wp-postviews' ); ?></option>
					<option value="2"<?php selected( '2', $views_options['display_other'] ); ?>><?php _e( 'Don\'t display on other pages', 'wp-postviews' ); ?></option>
				</select>
			</td>
		</tr>
	</table>
	<p class="submit">
		<input type="submit" name="Submit" class="button-primary" value="<?php _e( 'Save Changes', 'wp-postviews' ); ?>" />
	</p>
</div>
</form>
