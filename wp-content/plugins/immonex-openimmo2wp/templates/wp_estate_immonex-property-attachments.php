<?php
/**
 * Widget template (immonex Property Attachments) for the WP Estate theme.
 */
?>
<div class="immonex-widget immonex-widget-wp_estate<?php if ( isset( $instance['class'] ) && $instance['class'] ) echo ' ' . $instance['class']; ?>">
	<?php if ( ! empty( $title ) ) { ?>
	<div class="immonex-widget-title">
		<?php echo $args['before_title'] . $title . $args['after_title']; ?>
	</div>
	<?php } ?>
	<?php
		foreach( $attachments as $att ) {
			if ( is_object( $att ) ) {
				// File attachment (object)
				$att_url = wp_get_attachment_url( $att->ID );
				$att_title = $att->post_title ? $att->post_title : basename( $att_url );

				if ( $instance['icon_size'] > 0 ) {
					$file_info = pathinfo( $att_url );
					$icon_filename = 'file_extension_' . strtolower( $file_info['extension'] ) . '.png';
					$att_icon = file_exists( plugin_dir_path( dirname( __FILE__ ) ) . self::ICONS_DIR . trailingslashit( $instance['icon_size'] ) . $icon_filename ) ? self::ICONS_DIR . trailingslashit( $instance['icon_size'] ) . $icon_filename : false;
				} else {
					$att_icon = false;
				}
			} else {
				// Link (array)
				$att_url = $att['url'];
				$att_title = $att['title'];
				$att_icon = $instance['icon_size'] > 0 ? self::ICONS_DIR . trailingslashit( $instance['icon_size'] ) . 'file_extension_lnk.png' : false;
				$file_info = array( 'extension' => 'Hyperlink' );
			}

			if ( strlen( $att_title ) > $this->config['max_attachment_title_length'] ) $att_title = substr( $att_title, 0, $this->config['max_attachment_title_length'] ) . $this->config['trunc_attachment_title_suffix'];

			$target = isset( $this->config['link_target'] ) && $this->config['link_target'] ? ' target="' . $this->config['link_target'] . '"' : '';
	?>
	<div>
		<div class="file-wrap file-wrap-<?php echo $instance['icon_size']; ?>">
			<?php if ( $att_icon ) { ?>
			<div class="filetype-icon"><a href="<?php echo esc_attr( $att_url ); ?>"<?php echo $target; ?>><?php if ( $att_icon ) echo '<img src="' . plugins_url( $att_icon, dirname( __FILE__ ) ) . '" alt="' . __( 'Icon', 'immonex-openimmo2wp' ) . ': ' . strtoupper( $file_info['extension'] ) . '">'; ?></a></div>
			<?php } ?>
			<div class="filename"><a href="<?php echo esc_attr( $att_url ); ?>"<?php echo $target; ?>><?php echo esc_html( $att_title ); ?></a></div>
		</div>
	</div>
	<?php
		}
	?>
</div>