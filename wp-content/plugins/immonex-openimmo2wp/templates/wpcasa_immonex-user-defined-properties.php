<?php
/**
 * Widget template (immonex User-defined Properties) for wpCasa-based themes.
 */
if ( ! empty( $title ) ) {
?>
<div class="title clearfix">
	<h2><?php echo $title; ?></h2>
	<?php
		// Apply wpSight/wpCasa action hook "listing features title inside".
		do_action( 'wpsight_listing_features_title_inside', $args, $instance );
	?>
</div>
<?php
}
?>
<div class="listing-details">
	<div class="row">
		<?php
			foreach( $display_meta as $key => $value ) {
				if ( false !== strpos( $value, "\n" ) ) {
					// Value seems to be a continuous text: apply WP content filter.
					$value = apply_filters( 'the_content', $value );
				}
		?>
		<div<?php
				if ( ! isset( $instance['item_div_classes'] ) ) echo ' class="span4"';
				elseif ( ! empty( $instance['item_div_classes'] ) ) echo ' class="' . esc_attr( $instance['item_div_classes'] ) . '"';
			?>>
			<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) { ?>
			<div class="listing-details-value-only"><?php _e( $value, 'immonex-openimmo2wp' ); ?></div>
			<?php } else { ?>
			<span class="listing-details-label"><?php echo $key; ?>:</span>
			<span class="listing-details-value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></span>
			<?php } ?>
		</div>
		<?php
			}
		?>
	</div>
</div>
