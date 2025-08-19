<?php
/**
 * Widget template (immonex User-defined Properties) for the Real Places theme.
 */

$value_only = isset( $instance['type'] ) && 'value_only' === $instance['type'];
$classes    = array( 'immonex-widget', 'immonex-widget-realplaces' );
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
	<ul class="property-additional-details-list clearfix">
		<?php
		foreach( $display_meta as $key => $value ) :
			if ( false !== strpos( $value, "\n" ) ) {
				// Value seems to be a continuous text: apply WP content filter.
				$value = apply_filters( 'the_content', $value );
			}
			?>
		<li>
			<?php if ( $value_only ) : ?>
			<?php _e( $value, 'immonex-openimmo2wp' ); ?>
			<?php else : ?>
			<dl>
				<dt><?php echo $key; ?>:</dt>
				<dd><?php _e( $value, 'immonex-openimmo2wp' ); ?></dd>
			<?php endif; ?>
		</li>
		<?php endforeach; ?>
	</ul>
</div>
