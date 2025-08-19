<?php
/**
 * Widget template (immonex User-defined Properties) for the MyHome theme.
 */
?>
<div class="immonex-widget<?php if ( isset( $instance['type'] ) ) echo ' immonex-widget-' . $instance['type']; ?> immonex-widget-myhome<?php if ( isset( $instance['class'] ) && $instance['class'] ) echo ' ' . $instance['class']; ?>">
	<?php if ( ! empty( $title ) ) : ?>
	<div class="immonex-widget-title clearfix">
		<?php echo $args['before_title'] . $title . $args['after_title']; ?>
	</div>
	<?php endif; ?>
	<div class="mh-estate__list">
		<ul class="immonex-property-details mh-estate__list__inner">
			<?php
				foreach( $display_meta as $key => $value ) :
					if ( false !== strpos( $value, "\n" ) ) {
						// Value seems to be a continuous text: add breaks.
						$value = nl2br( $value );
					}
			?>
			<li class="mh-estate__list__element">
				<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) : ?>
				<span class="property-details-value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></span>
				<?php else : ?>
				<strong><span class="property-details-label"><?php echo $key; ?>:</span></strong> <span class="property-details-value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></span>
				<?php endif; ?>
			</li>
			<?php
				endforeach;
			?>
		</ul>
	</div>
</div>