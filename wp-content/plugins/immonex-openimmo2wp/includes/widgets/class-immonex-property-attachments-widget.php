<?php
/**
 * Class immonex_Property_Attachments_Widget
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp\Widgets;

/**
 * Adds immonex_Property_Attachments_Widget widget.
 */
class immonex_Property_Attachments_Widget extends \WP_Widget {

	const
		ICONS_DIR = 'assets/filetype_icons/';

	/**
	 * Define some minor important config defaults that can be overridden
	 * by a WP filter if needed.
	 */
	private
		$config = array(
			'max_attachment_title_length' => 32,
			'trunc_attachment_title_suffix' => '…',
			'link_target' => '_blank'
		);

	/**
	 * Register widget with WordPress.
	 */
	public function __construct() {
		parent::__construct(
			'immonex_property_attachments_widget',
			'immonex: ' . __( 'File Attachments and Links', 'immonex-openimmo2wp' ),
			array(
				'description' => __( 'List of linked property file attachments (e.g. PDF) and links to external websites.', 'immonex-openimmo2wp' )
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
			$immonex_openimmo2wp->property_post_type &&
			$immonex_openimmo2wp->property_post_type !== get_post_type( $immonex_openimmo2wp->current_post_id )
		) return;

		$this->config = apply_filters( 'immonex_property_attachment_widget_config', $this->config );

		$title = apply_filters( 'widget_title', isset( $instance['title'] ) ? $instance['title'] : '' );
		$file_types = array();
		if ( isset( $instance['file_types'] ) && trim( $instance['file_types'] ) ) {
			// Split file types (from widget configuration) to be displayed.
			$types_temp = explode( ',', $instance['file_types'] );
			foreach ( $types_temp as $key => $value ) {
				if ( trim( $value ) ) $file_types[] = trim( strtolower( $value ) );
			}
		}

		// Get property attachments.
		$att_args = array(
			'post_parent' => $immonex_openimmo2wp->current_post_id,
			'post_type' => 'attachment'
		);
		$children = get_children( $att_args );
		if ( ! $children || ! is_array( $children ) ) $children = array();

		$links = get_post_meta( $immonex_openimmo2wp->current_post_id, '_immonex_links', true );
		if ( ! $links || ! is_array( $links ) ) $links = array();

		if ( count( $children ) > 0 || count( $links ) > 0 ) {
			$attachments = array();

			// Filter attachments.
			foreach ( $children as $child ) {
				if (
					substr( $child->post_mime_type, 0, 5 ) !== 'image' &&
					false === stripos( $child->post_mime_type, 'video' ) &&
					(
						count( $file_types ) === 0 ||
						in_array( $child->post_mime_type, $file_types )
					)
				) {
					$attachments[] = $child;
				}
			}

			// Merge link array.
			$attachments = array_merge( $attachments, $links );

			if ( count( $attachments ) > 0 ) {
				if ( empty( $instance['icon_size'] ) ) {
					// Get available filetype icon sizes.
					$icon_sizes = glob( plugin_dir_path( dirname( dirname( dirname( __FILE__ ) ) ) . '/immonex-openimmo2wp.php' ) . self::ICONS_DIR . '*' );
					sort( $icon_sizes );
					$instance['icon_size'] = basename( $icon_sizes[count( $icon_sizes ) - 1] );
				}

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

		// Get available filetype icon sizes.
		$icon_sizes = glob( plugin_dir_path( dirname( dirname( dirname( __FILE__ ) ) ) . '/immonex-openimmo2wp.php' ) . self::ICONS_DIR . '*' );
		sort( $icon_sizes );

		$instance = wp_parse_args( (array) $instance, array(
			'file_types' => '',
			'icon_size' => '32',
			'item_div_classes' => 'span4'
		) );
		$title = isset( $instance['title'] ) ? $instance['title'] : __( 'Downloads & Links', 'immonex-openimmo2wp' );
?>
<p>
	<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title', 'immonex-openimmo2wp' ); ?>:</label>
	<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
</p>
<p style="margin-bottom:0">
	<label for="<?php echo $this->get_field_id( 'file_types' ); ?>"><?php _e( 'File Types', 'immonex-openimmo2wp' ); ?>:</label>
	<input class="widefat" id="<?php echo $this->get_field_id( 'file_types' ); ?>" name="<?php echo $this->get_field_name( 'file_types' ); ?>" type="text" value="<?php echo esc_attr( $instance['file_types'] ); ?>">
</p>
<p class="description"><?php _e( 'Comma separated list of MIME types (e.g. "application/pdf" for PDF files), empty = display all types of files <strong>except images and videos</strong>.', 'immonex-openimmo2wp' ); ?></p>
<p style="margin-top:0">
	<label for="<?php echo $this->get_field_id( 'icon_size' ); ?>"><?php _e( 'Icon Size', 'immonex-openimmo2wp' ); ?>:</label>
	<select class="widefat" id="<?php echo $this->get_field_id( 'icon_size' ); ?>" name="<?php echo $this->get_field_name( 'icon_size' ); ?>">
			<option value="0"<?php if ( 0 == $instance['icon_size'] ) echo ' selected'; ?>><?php _e( 'no icons', 'immonex-openimmo2wp' ); ?></option>
<?php
		foreach ( $icon_sizes as $path ) {
			$size = basename( $path );
?>
			<option value="<?php echo $size; ?>"<?php if ( $size == $instance['icon_size'] ) echo ' selected'; ?>><?php echo "$size x $size px"; ?></option>
<?php
		}
?>
	</select>
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
		// Get available filetype icon sizes.
		$icon_size_directories = glob( plugin_dir_path( dirname( dirname( dirname( __FILE__ ) ) ) . '/immonex-openimmo2wp.php' ) . self::ICONS_DIR . '*' );
		$icon_sizes = array( 0 );
		foreach ( $icon_size_directories as $path ) {
			$icon_sizes[] = basename( $path );
		}
		sort( $icon_sizes );

		$instance = array();
		$instance['title'] = ( ! empty( $new_instance['title'] ) ) ? sanitize_text_field( $new_instance['title'] ) : '';
		$instance['file_types'] = ( ! empty( $new_instance['file_types'] ) ) ? sanitize_text_field( $new_instance['file_types'] ) : '';
		$instance['icon_size'] = in_array( $new_instance['icon_size'], $icon_sizes ) ? $new_instance['icon_size'] : $icon_sizes[count( $icon_sizes ) - 1];
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

} // class immonex_Property_Attachments_Widget
