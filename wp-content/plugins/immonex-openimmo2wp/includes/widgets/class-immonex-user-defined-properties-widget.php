<?php
/**
 * Class immonex_User_Defined_Properties_Widget
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp\Widgets;

/**
 * Adds immonex_User_Defined_Properties_Widget widget.
 */
class immonex_User_Defined_Properties_Widget extends \WP_Widget {

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'immonex_user_defined_properties_widget',
			'immonex: ' . __( 'User-defined Properties', 'immonex-openimmo2wp' ),
			array(
				'description' => __( 'Additional name-value pairs for real estate description.', 'immonex-openimmo2wp' )
			)
		);
	} // construct

	/**
	 * Frontend display of widget.
	 *
	 * @since 1.0
	 *
	 * @see WP_Widget::widget()
	 *
	 * @param array $args Widget arguments.
	 * @param array $instance Values from database.
	 */
	public function widget( $args, $instance ) {
		global $immonex_openimmo2wp;

		if (
			! isset( $immonex_openimmo2wp )
			|| ! is_object( $immonex_openimmo2wp )
			|| ! $immonex_openimmo2wp->engage
		) {
			return;
		}

		if (
			$immonex_openimmo2wp->property_post_type &&
			$immonex_openimmo2wp->property_post_type !== get_post_type( $immonex_openimmo2wp->current_post_id )
		) {
			return;
		}

		$title = apply_filters( 'widget_title', isset( $instance['title'] ) ? $instance['title'] : '' );
		$display_mode = isset( $instance['display_mode'] ) && in_array( $instance['display_mode'], array( 'include', 'exclude' ) ) ? $instance['display_mode'] : 'include';
		$display_groups = array();
		if ( isset( $instance['display_groups'] ) && trim( $instance['display_groups'] ) ) {
			// Split field groups (from widget configuration) to be displayed or hidden.
			$groups_temp = explode( ',', $instance['display_groups'] );
			foreach ( $groups_temp as $key => $value ) {
				if ( trim( $value ) ) $display_groups[] = trim( $value );
			}
		}

		// Get property post custom fields.
		$meta = get_post_meta( $immonex_openimmo2wp->current_post_id );

		if ( is_array( $meta ) && count( $meta ) > 0 ) {
			$display_meta = array();

			// Build an array of custom fields to be displayed.
			foreach ( $meta as $key => $value ) {
				if ( '_immonex_custom_fields' === $key ) {
					// Split (immonex) custom fields based on imported data.
					$immonex_custom_fields = unserialize( $value[0] );

					if ( count( $immonex_custom_fields ) > 0 ) {
						$immonex_custom_fields_temp = array();

						foreach ( $immonex_custom_fields as $key => $value ) {
							if (
								count( $display_groups ) == 0 ||
								( 'include' == $display_mode && in_array( $value['group'], $display_groups ) ) ||
								( 'exclude' == $display_mode && ! in_array( $value['group'], $display_groups ) )
							) {
								$immonex_custom_fields_temp[$key] = $value['value'];
							}
						}

						$display_meta = array_merge( $display_meta, $immonex_custom_fields_temp );
					}
				}
			}

			if ( count( $display_meta ) > 0 ) {
				echo $args['before_widget'];

				$template = $this->_get_theme_template_file();
				if ( $template ) include( $template );

				echo $args['after_widget'];
			}
		}
	} // widget

	/**
	 * Backend widget form.
	 *
	 * @since 1.0
	 *
	 * @see WP_Widget::form()
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		global $immonex_openimmo2wp;

		if (
			! isset( $immonex_openimmo2wp )
			|| ! is_object( $immonex_openimmo2wp )
			|| ! $immonex_openimmo2wp->engage
		) {
			echo wp_sprintf(
				'<div style="margin:16px 0">%s</div>',
				$immonex_openimmo2wp->license_activation_required_text
			);
			return;
		}

		$is_classic_wpcasa = 'wpcasa' === $immonex_openimmo2wp->theme_class_slug;

		$instance = wp_parse_args( (array) $instance, array(
			'display_mode' => 'include',
			'display_groups' => '',
			'type' => 'name_value',
			'item_div_classes' => 'span4'
		) );
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Additional Details', 'immonex-openimmo2wp' );
?>
<p>
	<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title', 'immonex-openimmo2wp' ); ?>:</label>
	<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
</p>
<p>
	<label for="<?php echo $this->get_field_id( 'display_mode' ); ?>"><?php _e( 'The following groups shall be:', 'immonex-openimmo2wp' ); ?>&nbsp;</label>
	<label>
		<input type="radio" name="<?php echo $this->get_field_name( 'display_mode' ); ?>" value="include"<?php checked( $instance['display_mode'], 'include' ); ?>>
		<?php _e( 'displayed', 'immonex-openimmo2wp' ); ?>
	</label>&nbsp;
	<label>
		<input type="radio" name="<?php echo $this->get_field_name( 'display_mode' ); ?>" value="exclude"<?php checked( $instance['display_mode'], 'exclude' ); ?>>
		<?php _e( 'hidden', 'immonex-openimmo2wp' ); ?>
	</label>
</p>
<p style="margin-bottom:0">
	<label for="<?php echo $this->get_field_id( 'display_groups' ); ?>"><?php _e( 'Groups', 'immonex-openimmo2wp' ); ?>:</label>
	<input class="widefat" id="<?php echo $this->get_field_id( 'display_groups' ); ?>" name="<?php echo $this->get_field_name( 'display_groups' ); ?>" type="text" value="<?php echo esc_attr( $instance['display_groups'] ); ?>">
</p>
<p class="description" style="padding-bottom:0"><?php _e( 'Comma separated list, empty = display all groups/fields.', 'immonex-openimmo2wp' ); ?></p>
<p>
	<label for="<?php echo $this->get_field_id( 'type' ); ?>"><?php _e( 'Display Type:', 'immonex-openimmo2wp' ); ?></label><br>
	<label>
		<input type="radio" name="<?php echo $this->get_field_name( 'type' ); ?>" value="name_value"<?php checked( $instance['type'], 'name_value' ); ?>>
		<?php _e( 'Name: Value', 'immonex-openimmo2wp' ); ?>
	</label>&nbsp;
	<label>
		<input type="radio" name="<?php echo $this->get_field_name( 'type' ); ?>" value="value_only"<?php checked( $instance['type'], 'value_only' ); ?>>
		<?php _e( 'Value only', 'immonex-openimmo2wp' ); ?>
	</label>
</p>
<?php
		if ( $is_classic_wpcasa ) {
?>
<p>
	<label for="<?php echo $this->get_field_id( 'item_div_classes' ); ?>"><?php _e( 'Item DIV classes', 'immonex-openimmo2wp' ); ?>:</label>
	<input class="widefat" id="<?php echo $this->get_field_id( 'item_div_classes' ); ?>" name="<?php echo $this->get_field_name( 'item_div_classes' ); ?>" type="text" value="<?php echo esc_attr( $instance['item_div_classes'] ); ?>">
</p>
<p class="description"><?php _e( 'E. g. Bootstrap 2 grid class, default: "span4". <strong>This option currently applies to wpCasa based themes only!</strong>', 'immonex-openimmo2wp' ); ?></p>
<?php
		}
	} // form

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @since 1.0
	 *
	 * @see WP_Widget::update()
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 *
	 * @return array Sanitized values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['display_mode'] = in_array( $new_instance['display_mode'], array( 'include', 'exclude' ) ) ? $new_instance['display_mode'] : 'include';
		$instance['display_groups'] = ( ! empty( $new_instance['display_groups'] ) ) ? sanitize_text_field( $new_instance['display_groups'] ) : '';
		$instance['type'] = in_array( $new_instance['type'], array( 'name_value', 'value_only' ) ) ? $new_instance['type'] : 'name_value';
		$instance['item_div_classes'] = ( ! empty( $new_instance['item_div_classes'] ) ) ? sanitize_text_field( $new_instance['item_div_classes'] ) : '';

		return $instance;
	} // update

	/**
	 * Determine the widget template file.
	 *
	 * @since 1.2
	 * @access private
	 *
	 * @return string Widget template filename including path.
	 */
	private function _get_theme_template_file() {
		global $immonex_openimmo2wp, $wp_filesystem;

		WP_Filesystem();

		$template_folders = $immonex_openimmo2wp->template_folders;
		$template_file = false;

		if ( count( $template_folders ) > 0 ) {
			$basename = str_replace( array( 'class-', '-widget' ), array( '', '' ), basename( __FILE__, '.php' ) );
			$theme = wp_get_theme();

			$template_file = false;

			// Strip version numbers from theme names for comparison.
			$version_regex = '/[0-9]{1,2}(\.[0-9]{1,2}(\.[0-9]{1,2})?(\.[0-9]{1,2})?)?/';
			$theme_name = trim( preg_replace( $version_regex, '', $theme->name ) );
			$parent_theme_name = trim( preg_replace( $version_regex, '', $theme->parent_theme ) );

			$theme_name = str_replace( ' ' , '_', strtolower( $theme_name ) );
			$parent_theme_name = str_replace( ' ' , '_', strtolower( $parent_theme_name ) );
			$real_estate_plugin_name = $immonex_openimmo2wp->theme_class_slug; // Use this filename prefix if a real estate plugin is used instead of a theme.
			$override_widget_theme_name = $immonex_openimmo2wp->override_widget_theme_name; // Override the theme name prefix via theme/plugin class file.

			foreach ( $template_folders as $widget_template_dir ) {
				if ( $override_widget_theme_name && file_exists( trailingslashit( $widget_template_dir ) . $override_widget_theme_name . "_$basename" . '.php' ) )
					$template_file = trailingslashit( $widget_template_dir ) . $override_widget_theme_name . "_$basename" . '.php';
				elseif ( file_exists( trailingslashit( $widget_template_dir ) . $theme_name . "_$basename" . '.php' ) )
					$template_file = trailingslashit( $widget_template_dir ) . $theme_name . "_$basename" . '.php';
				elseif ( file_exists( trailingslashit( $widget_template_dir ) . $parent_theme_name . "_$basename" . '.php' ) )
					$template_file = trailingslashit( $widget_template_dir ) . $parent_theme_name . "_$basename" . '.php';
				elseif ( file_exists( trailingslashit( $widget_template_dir ) . $real_estate_plugin_name . "_$basename" . '.php' ) )
					$template_file = trailingslashit( $widget_template_dir ) . $real_estate_plugin_name . "_$basename" . '.php';
				elseif ( file_exists( trailingslashit( $widget_template_dir ) . $basename . '.php' ) )
					$template_file = trailingslashit( $widget_template_dir ) . $basename . '.php';
				elseif ( file_exists( trailingslashit( $widget_template_dir ) . "default_$basename" . '.php' ) )
					$template_file = trailingslashit( $widget_template_dir ) . "default_$basename" . '.php';

				if ( $template_file ) break;
			}
		}

		return $template_file;
	} // _get_theme_template_file

} // class immonex_User_Defined_Properties_Widget
