<?php
/**
 * Widget template (immonex User-defined Properties) for the Hometown theme.
 */
?>
<div class="immonex-widget immonex-widget-hometown<?php if ( isset( $instance['class'] ) && $instance['class'] ) echo ' ' . $instance['class']; ?>">
	<?php if ( ! empty( $title ) ) { ?>
	<div class="immonex-widget-title clearfix">
		<?php echo $args['before_title'] . $title . $args['after_title']; ?>
	</div>
	<?php } ?>
	<ul class="table-list">
		<?php
			foreach( $display_meta as $key => $value ) {
				if ( false !== strpos( $value, "\n" ) ) {
					// Value seems to be a continuous text: apply WP content filter.
					$value = apply_filters( 'the_content', $value );
				}
		?>
		<li>
			<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) { ?>
			<strong><?php _e( $value, 'immonex-openimmo2wp' ); ?></strong>
			<?php } else { ?>
			<strong><?php echo $key; ?></strong>
			<span><?php _e( $value, 'immonex-openimmo2wp' ); ?></span>
			<?php } ?>
		</li>
		<?php
			}
		?>
	</ul>
</div>