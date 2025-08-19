<?php
/**
 * Widget template (immonex User-defined Properties) for the WP Estate theme.
 */
?>
<div class="immonex-widget immonex-widget-wp_estate<?php if ( isset( $instance['class'] ) && $instance['class'] ) echo ' ' . $instance['class']; ?>">
	<?php if ( ! empty( $title ) ) { ?>
	<div class="immonex-widget-title">
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
	<div class="prop_details">
		<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) { ?>
		<?php _e( $value, 'immonex-openimmo2wp' ); ?>
		<?php } else { ?>
		<span class="title_feature_listing"><?php echo $key; ?>:</span>
		<?php _e( $value, 'immonex-openimmo2wp' ); ?>
		<?php } ?>
	</div>
	<?php
		}
	?>
</div>