<?php
/**
 * Widget template (immonex User-defined Properties) for the Inventor Properties plugin.
 */
?>
<div class="immonex-widget immonex-widget-inventor-properties widget-inner<?php if ( isset( $instance['class'] ) && $instance['class'] ) echo ' ' . $instance['class']; ?>">
	<?php if ( ! empty( $title ) ) { ?>
	<div class="immonex-widget-title clearfix">
		<?php echo $args['before_title'] . $title . $args['after_title']; ?>
	</div>
	<?php } ?>

	<ul>
		<?php
			foreach( $display_meta as $key => $value ) {
				if ( false !== strpos( $value, "\n" ) ) {
					// Value seems to be a continuous text: apply WP content filter.
					$value = apply_filters( 'the_content', $value );
				}
		?>
		<li>
			<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) { ?>
			<div class="value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></div>
			<?php } else { ?>
			<div class="key"><?php echo $key; ?></div>
			<div class="value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></div>
			<?php } ?>
		</li>
		<?php
			}
		?>
	</ul>
</div>