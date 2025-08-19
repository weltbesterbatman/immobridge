<?php
/**
 * Widget template (immonex User-defined Properties) for the Zoner theme.
 */
?>
<div class="immonex-widget immonex-widget-zoner<?php if ( isset( $instance['class'] ) && $instance['class'] ) echo ' ' . $instance['class']; ?>">
	<?php if ( ! empty( $title ) ) : ?>
	<div class="immonex-widget-title clearfix">
		<?php echo $args['before_title'] . $title . $args['after_title']; ?>
	</div>
	<?php endif; ?>
	<dl class="immonex-property-details">
		<?php
			foreach( $display_meta as $key => $value ) :
				if ( false !== strpos( $value, "\n" ) ) {
					// Value seems to be a continuous text: apply WP content filter.
					$value = apply_filters( 'the_content', $value );
				}
		?>
		<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) : ?>
		<dd class="property-details-value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></dd>
		<?php else : ?>
		<dt class="property-details-label"><?php echo $key; ?>:</dt><dd class="property-details-value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></dd>
		<?php endif; ?>
		<?php
			endforeach;
		?>
	</dl>
</div>