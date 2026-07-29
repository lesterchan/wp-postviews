<?php
/**
 * The Views widget.
 *
 * The class was called WP_Widget_PostViews up to 1.78.1. The id_base it
 * registers, 'views', is deliberately unchanged: that string is what names the
 * widget_views option row, so altering it would orphan every configured widget
 * on every site.
 *
 * @package WP-PostViews
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lists the most or least viewed posts.
 */
class WP_PostViews_Widget extends WP_Widget {

	/**
	 * Register the widget.
	 */
	public function __construct() {
		parent::__construct(
			'views',
			__( 'Views', 'wp-postviews' ),
			array(
				'description'                 => __( 'WP-PostViews views statistics', 'wp-postviews' ),
				'customize_selective_refresh' => true,
			)
		);
	}

	/**
	 * Default instance values.
	 *
	 * @return array
	 */
	protected function defaults() {
		return array(
			'title'   => __( 'Views', 'wp-postviews' ),
			'type'    => 'most_viewed',
			'mode'    => '',
			'limit'   => 10,
			'chars'   => 200,
			'cat_ids' => '0',
		);
	}

	/**
	 * Render the widget.
	 *
	 * @param array $args     Sidebar arguments.
	 * @param array $instance Saved widget settings.
	 * @return void
	 */
	public function widget( $args, $instance ) {
		// The block based widget editor and the customizer both hand over only
		// the keys the user touched, so the defaults are merged in here rather
		// than assumed present.
		$instance = wp_parse_args( (array) $instance, $this->defaults() );

		$title = apply_filters( 'widget_title', $instance['title'], $instance, $this->id_base );

		$cat_ids = array_filter( array_map( 'absint', explode( ',', (string) $instance['cat_ids'] ) ) );
		if ( empty( $cat_ids ) ) {
			$cat_ids = array( 0 );
		}

		$listing = array(
			'mode'  => (string) $instance['mode'],
			'limit' => (int) $instance['limit'],
			'chars' => (int) $instance['chars'],
		);

		switch ( $instance['type'] ) {
			case 'least_viewed':
				$listing['order'] = 'asc';
				break;
			case 'most_viewed_category':
				$listing['order']    = 'desc';
				$listing['category'] = $cat_ids;
				break;
			case 'least_viewed_category':
				$listing['order']    = 'asc';
				$listing['category'] = $cat_ids;
				break;
			case 'most_viewed':
			default:
				$listing['order'] = 'desc';
				break;
		}

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sidebar chrome supplied by the theme; escaping it would print the wrapper instead of opening it.
		if ( '' !== $title ) {
			// Core already registers esc_html on the widget_title filter, so
			// this is a second pass. It is kept because the filter is public
			// and a theme can remove that callback, and it costs nothing:
			// esc_html() passes $double_encode = false, so escaping an already
			// escaped string is a no-op rather than turning &#038; into
			// &amp;#038;.
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sidebar chrome supplied by the theme; the title between them is escaped.
		}
		echo '<ul>' . "\n";
		// The listing is rendered from the user's own template, which is run
		// through wp_kses_post() when it is saved.
		echo WP_PostViews_Query::render( $listing ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Stored template, filtered through wp_kses_post() by WP_PostViews_Settings on save.
		echo '</ul>' . "\n";
		echo $args['after_widget']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Sidebar chrome supplied by the theme; escaping it would print the wrapper instead of closing it.
	}

	/**
	 * Persist a submitted instance.
	 *
	 * 1.78.1 bailed out unless a hidden field called 'submit' was present. That
	 * was a WordPress 2.x idiom, and it meant the block based widget editor and
	 * the customizer - neither of which sends it - silently discarded every
	 * change. The field and the check are both gone.
	 *
	 * @param array $new_instance Submitted values.
	 * @param array $old_instance Previously saved values.
	 * @return array
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = wp_parse_args( (array) $old_instance, $this->defaults() );

		$instance['title']   = isset( $new_instance['title'] ) ? wp_strip_all_tags( $new_instance['title'] ) : $instance['title'];
		$instance['mode']    = isset( $new_instance['mode'] ) ? sanitize_key( $new_instance['mode'] ) : $instance['mode'];
		$instance['limit']   = isset( $new_instance['limit'] ) ? (int) $new_instance['limit'] : $instance['limit'];
		$instance['chars']   = isset( $new_instance['chars'] ) ? (int) $new_instance['chars'] : $instance['chars'];
		$instance['cat_ids'] = isset( $new_instance['cat_ids'] ) ? implode( ',', array_map( 'absint', explode( ',', (string) $new_instance['cat_ids'] ) ) ) : $instance['cat_ids'];

		$types = array( 'least_viewed', 'least_viewed_category', 'most_viewed', 'most_viewed_category' );
		if ( isset( $new_instance['type'] ) && in_array( $new_instance['type'], $types, true ) ) {
			$instance['type'] = $new_instance['type'];
		}

		return $instance;
	}

	/**
	 * Render the widget's settings form.
	 *
	 * @param array $instance Saved widget settings.
	 * @return void
	 */
	public function form( $instance ) {
		$instance   = wp_parse_args( (array) $instance, $this->defaults() );
		$post_types = get_post_types( array( 'public' => true ) );
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'wp-postviews' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['title'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>"><?php esc_html_e( 'Statistics Type:', 'wp-postviews' ); ?></label>
			<select name="<?php echo esc_attr( $this->get_field_name( 'type' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'type' ) ); ?>" class="widefat">
				<option value="least_viewed"<?php selected( 'least_viewed', $instance['type'] ); ?>><?php esc_html_e( 'Least Viewed', 'wp-postviews' ); ?></option>
				<option value="least_viewed_category"<?php selected( 'least_viewed_category', $instance['type'] ); ?>><?php esc_html_e( 'Least Viewed By Category', 'wp-postviews' ); ?></option>
				<option value="most_viewed"<?php selected( 'most_viewed', $instance['type'] ); ?>><?php esc_html_e( 'Most Viewed', 'wp-postviews' ); ?></option>
				<option value="most_viewed_category"<?php selected( 'most_viewed_category', $instance['type'] ); ?>><?php esc_html_e( 'Most Viewed By Category', 'wp-postviews' ); ?></option>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>"><?php esc_html_e( 'Include Views From:', 'wp-postviews' ); ?></label>
			<select name="<?php echo esc_attr( $this->get_field_name( 'mode' ) ); ?>" id="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>" class="widefat">
				<option value=""<?php selected( '', $instance['mode'] ); ?>><?php esc_html_e( 'All', 'wp-postviews' ); ?></option>
				<?php foreach ( $post_types as $post_type ) : ?>
					<option value="<?php echo esc_attr( $post_type ); ?>"<?php selected( $post_type, $instance['mode'] ); ?>>
						<?php
						printf(
							/* translators: %s: post type name. */
							esc_html__( '%s Only', 'wp-postviews' ),
							esc_html( ucfirst( $post_type ) )
						);
						?>
					</option>
				<?php endforeach; ?>
			</select>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>"><?php esc_html_e( 'No. Of Records To Show:', 'wp-postviews' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'limit' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'limit' ) ); ?>" type="number" min="1" value="<?php echo esc_attr( $instance['limit'] ); ?>" />
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'chars' ) ); ?>"><?php esc_html_e( 'Maximum Post Title Length (Characters):', 'wp-postviews' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'chars' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'chars' ) ); ?>" type="number" min="0" value="<?php echo esc_attr( $instance['chars'] ); ?>" />
			<small><?php esc_html_e( '0 to disable.', 'wp-postviews' ); ?></small>
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'cat_ids' ) ); ?>"><?php esc_html_e( 'Category IDs:', 'wp-postviews' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'cat_ids' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'cat_ids' ) ); ?>" type="text" value="<?php echo esc_attr( $instance['cat_ids'] ); ?>" />
			<small><?php esc_html_e( 'Separate multiple categories with commas. Only used by the two "By Category" statistics types.', 'wp-postviews' ); ?></small>
		</p>
		<?php
	}
}
