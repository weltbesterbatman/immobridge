<?php
/**
 * Widget template (immonex User-defined Properties) for WPCasa (plugin) based themes.
 */
?>
<div class="immonex-widget immonex-widget-wpcasa_plugin clear<?php if ( isset( $instance['class'] ) && $instance['class'] ) echo ' ' . $instance['class']; ?>">
<?php
if ( ! empty( $title ) ) {
?>
	<h3 class="widget-title"><?php echo $title; ?></h3>
<?php
}
?>
	<div class="wpsight-listing-details">
		<?php
			foreach( $display_meta as $key => $value ) {
				if ( false !== strpos( $value, "\n" ) ) {
					// Value seems to be a continuous text: apply WP content filter.
					$value = apply_filters( 'the_content', $value );
				}
		?>
		<span class="listing-details-detail">
			<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) { ?>
			<div class="listing-details-value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></div>
			<?php } else { ?>
			<span class="listing-details-label"><?php echo $key; ?></span>
			<span class="listing-details-value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></span>
			<?php } ?>
		</span>
		<?php
			}
		?>
	</div>
</div>
