<?php
$plugin_infos = apply_filters( 'immonex-openimmo2wp_plugin_infos', [] );

do_action( 'immonex_oi2wp_render_option_page_header' );
?>

<style>
	body.settings_page_immonex_oi2wp_new_options_location p {
		margin-top: 0;
		font-size: 14px;
	}

	#toplevel_page_openimmo2wp:hover #arrow {
		display: none !important;
	}

	#toplevel_page_openimmo2wp:not(:hover) #arrow {
		display: none;
		position: absolute;
		top: 8px;
		right: -10px;
		margin-left: 9px;
		color: #FF7117;
		font-size: 32px;
		font-weight: 700;
		animation: slide1 1s ease-in-out infinite;
	}

	@keyframes slide1 {
		0%,
		100% {
			transform: translate(0, 0);
		}

		50% {
			transform: translate(10px, 0);
		}
	}

	@media screen and (max-width: 782px) {
		#toplevel_page_openimmo2wp:not(:hover) #arrow {
			top: 13px;
		}
	}
</style>

<script>
	(function ($) {
		$(document).ready(function () {
			$("#arrow").appendTo("#toplevel_page_openimmo2wp").show();
		});
	})(jQuery);
</script>

<div id="arrow">&larr;</div>

<div style="margin-bottom: 48px">
	<h2 style="margin-top: 0"><?php _e( 'Watch out!', 'immonex-openimmo2wp' ); ?></h2>

	<p>
		<?php _e( 'With version 5, a separate item was added to the dashboard menu for OpenImmo2WP.', 'immonex-openimmo2wp' ); ?>
	</p>

	<p>
		<?php
		echo wp_sprintf(
			__( 'The plugin options have been moved to <a href="%s">OpenImmo2WP &rarr; Settings</a>.', 'immonex-openimmo2wp' ),
			admin_url( 'admin.php?page=immonex-openimmo2wp_settings' )
		);
		?>
	</p>

	<p style="margin-bottom: 0">
		<?php
		echo wp_sprintf(
			__( 'Manual imports can now be started here: <a href="%s">OpenImmo2WP &rarr; Import</a>', 'immonex-openimmo2wp' ),
			admin_url( 'admin.php?page=openimmo2wp' )
		);
		?>
	</p>
</div>

<?php do_action( 'immonex_oi2wp_render_option_page_footer' ); ?>