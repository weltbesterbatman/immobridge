<?php
/**
 * Widget template (immonex User-defined Properties) for the Freehold Progression theme.
 */

$value_only = isset( $instance['type'] ) && 'value_only' === $instance['type'];
$classes    = array( 'immonex-widget', 'immonex-widget-freehold-progression' );
if ( $value_only ) {
	$classes[] = 'immonex-widget-value-only';
}
if ( isset( $instance['class'] ) && $instance['class'] ) {
	$classes[] = $instance['class'];
}
?>
<div class="<?php echo implode( ' ', $classes ); ?>">
	<?php if ( ! empty( $title ) ) : ?>
	<div class="immonex-widget-title clearfix">
		<?php echo $args['before_title'] . $title . $args['after_title']; ?>
	</div>
	<?php endif; ?>
	<ul class="progression-single-additional-info immonex-property-details">
		<?php
		foreach( $display_meta as $key => $value ) :
			if ( false !== strpos( $value, "\n" ) ) {
				// Value seems to be a continuous text: apply WP content filter.
				$value = apply_filters( 'the_content', $value );
			}
			?>
		<li>
			<?php if ( $value_only ) : ?>
			<span class="additional-pro-first-pro"><?php _e( $value, 'immonex-openimmo2wp' ); ?></span>
			<?php else : ?>
			<span class="additional-pro-first-pro"><?php echo $key; ?></span>
			<span class="additional-pro-last-pro"><?php _e( $value, 'immonex-openimmo2wp' ); ?></span>
			<?php endif; ?>
		</li>
			<?php
		endforeach;
		?>
	</ul>
</div>
