<?php
/**
 * Widget template (immonex User-defined Properties) for the Houzez theme.
 */
?>
<div class="immonex-widget immonex-widget-houzez<?php if ( isset( $instance['class'] ) && $instance['class'] ) echo ' ' . $instance['class']; ?>">
	<?php if ( ! empty( $title ) ) : ?>
	<div class="immonex-widget-title clearfix">
		<?php echo $args['before_title'] . $title . $args['after_title']; ?>
	</div>
	<?php endif; ?>
	<ul class="immonex-property-details">
		<?php
			foreach( $display_meta as $key => $value ) :
				if ( false !== strpos( $value, "\n" ) ) {
					// Value seems to be a continuous text: apply WP content filter.
					$value = apply_filters( 'the_content', $value );
				}
		?>
		<li>
			<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) : ?>
			<span class="property-details-value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></span>
			<?php else : ?>
			<span class="property-details-label"><?php echo $key; ?>:</span> <span class="property-details-value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></span>
			<?php endif; ?>
		</li>
		<?php
			endforeach;
		?>
	</ul>
</div>