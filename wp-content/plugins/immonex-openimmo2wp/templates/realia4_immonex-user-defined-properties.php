<?php
/**
 * Widget template (immonex User-defined Properties) for the Realia theme (>= 4.0).
 */
?>
<div class="immonex-widget immonex-widget-realia4<?php if ( isset( $instance['class'] ) && $instance['class'] ) echo ' ' . $instance['class']; ?>">
	<?php if ( ! empty( $title ) ) { ?>
	<div class="immonex-widget-title clearfix">
		<?php echo $args['before_title'] . $title . $args['after_title']; ?>
	</div>
	<?php } ?>
	<ul class="immonex-property-details">
		<?php
			foreach( $display_meta as $key => $value ) {
				if ( false !== strpos( $value, "\n" ) ) {
					// Value seems to be a continuous text: apply WP content filter.
					$value = apply_filters( 'the_content', $value );
				}
		?>
		<li>
			<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) { ?>
			<div class="property-details-value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></div>
			<?php } else { ?>
			<div class="property-details-key"><?php echo $key; ?></div> <div class="property-details-value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></div>
			<?php } ?>
		</li>
		<?php
			}
		?>
	</ul>
</div>