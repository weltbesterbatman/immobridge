<?php
/**
 * Default widget template (immonex: User-defined Properties).
 */

$theme   = wp_get_theme();
$classes = array(
	'immonex-widget',
	'immonex-widget-default',
	'theme-' . str_replace( ' ', '-', strtolower( $theme->name ) ),
);

if ( $theme->parent_theme && $theme->parent_theme !== $theme->name ) {
	$classes[] = ' theme-' . strtolower( $theme->parent_theme );
}

if ( isset( $instance['class'] ) && $instance['class'] ) {
	$classes[] = $instance['class'];
};
?>
<div class="<?php echo implode( $classes ); ?>">
	<?php if ( ! empty( $title ) ) : ?>
	<div class="immonex-widget-title clearfix">
		<?php echo $args['before_title'] . $title . $args['after_title']; ?>
	</div>
	<?php endif; ?>
	<table class="immonex-property-details">
		<tbody>
			<?php
			foreach( $display_meta as $key => $value ) :
				if ( false !== strpos( $value, "\n" ) ) {
					// Value seems to be a continuous text: apply WP content filter.
					$value = apply_filters( 'the_content', $value );
				}
			?>
			<tr>
				<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) { ?>
				<td class="property-details-value-only"><?php _e( $value, 'immonex-openimmo2wp' ); ?></td>
				<?php } else { ?>
				<td class="property-details-label"><?php echo $key; ?>:</td>
				<td class="property-details-value"><?php _e( $value, 'immonex-openimmo2wp' ); ?></td>
				<?php } ?>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>