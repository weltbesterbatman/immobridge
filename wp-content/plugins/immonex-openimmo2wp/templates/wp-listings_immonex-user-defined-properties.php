<?php
/**
 * Widget template (immonex User-defined Properties) for the WP Listings plugin.
 */
?>
<div class="immonex-widget immonex-widget-wp-listings<?php if ( isset( $instance['class'] ) && $instance['class'] ) echo ' ' . $instance['class']; ?>">
	<?php if ( ! empty( $title ) ) { ?>
	<div class="immonex-widget-title clearfix">
		<?php echo $args['before_title'] . $title . $args['after_title']; ?>
	</div>
	<?php } ?>
	<table class="listing-details">
		<tbody class="left">
			<?php
				$cnt_break = ceil( count( $display_meta ) / 2 ) + 1;
				$cnt = 0;

				foreach( $display_meta as $key => $value ) :
					$cnt++;

					if ( false !== strpos( $value, "\n" ) ) {
						// Value seems to be a continuous text: apply WP content filter.
						$value = apply_filters( 'the_content', $value );
					}
			?>
		<?php if ( $cnt == $cnt_break ) { ?>
		</tbody>
		<tbody class="right">
		<?php } ?>
			<tr>
				<?php if ( isset( $instance['type'] ) && 'value_only' === $instance['type'] ) { ?>
				<td><?php _e( $value, 'immonex-openimmo2wp' ); ?></td>
				<?php } else { ?>
				<td class="label"><?php echo $key; ?>:</td>
				<td><?php _e( $value, 'immonex-openimmo2wp' ); ?></td>
				<?php } ?>
			</tr>
			<?php
				endforeach;
			?>
		</tbody>
	</table>
</div>