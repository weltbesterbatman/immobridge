<?php
/**
 * Widget template (immonex User-defined Properties) for the Realty theme.
 */
$shortcode = isset( $args['is_shortcode_output'] ) && $args['is_shortcode_output'];
?>
<div class="immonex-widget immonex-widget-realty<?php if ( $shortcode ) echo ' via-shortcode'; if ( isset( $instance['class'] ) && $instance['class'] ) echo ' ' . $instance['class']; ?>">
	<?php
		if ( ! empty( $title ) ) {
			if ( $shortcode )
				echo '<h3 class="section-title"><span>' . $title . '</span></h3>';
			else
				echo $args['before_title'] . $title . $args['after_title'];
		}
	?>
	<ul class="property-details">
		<?php
			foreach( $display_meta as $key => $value ) {
				if ( false !== strpos( $value, "\n" ) ) {
					// Value seems to be a continuous text: apply WP content filter.
					$value = apply_filters( 'the_content', $value );
				}
		?>
		<li>
			<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) { ?>
			<?php _e( $value, 'immonex-openimmo2wp' ); ?>
			<?php } else { ?>
			<div class="property-details-caption"><?php echo $key; ?></div>
			<span><?php _e( $value, 'immonex-openimmo2wp' ); ?></span>
			<?php } ?>
		</li>
		<?php
			}
		?>
	</ul>
</div>
