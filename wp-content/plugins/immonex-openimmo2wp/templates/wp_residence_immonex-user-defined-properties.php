<?php
/**
 * Widget template (immonex User-defined Properties) for the WP Residence theme.
 */
?>
<div class="immonex-widget immonex-widget-wp_residence<?php if ( isset( $instance['class'] ) && $instance['class'] ) echo ' ' . $instance['class']; ?>">
	<?php if ( ! empty( $title ) ) { ?>
	<div class="immonex-widget-title clearfix">
		<?php echo $args['before_title'] . $title . $args['after_title']; ?>
	</div>
	<?php } ?>
	<?php
		foreach( $display_meta as $key => $value ) {
			if ( false !== strpos( $value, "\n" ) ) {
				// Value seems to be a continuous text: apply WP content filter.
				$value = apply_filters( 'the_content', $value );
			}
	?>
	<div class="listing_detail">
		<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) { ?>
		<?php _e( $value, 'immonex-openimmo2wp' ); ?>
		<?php } else { ?>
		<strong><?php echo $key; ?>:</strong>
		<?php _e( $value, 'immonex-openimmo2wp' ); ?>
		<?php } ?>
	</div>
	<?php
		}
	?>
</div>