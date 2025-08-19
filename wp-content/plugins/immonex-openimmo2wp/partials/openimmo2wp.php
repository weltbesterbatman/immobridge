<?php
global $immonex_openimmo2wp;

$license_active         = $immonex_openimmo2wp->engage;
$dialog_texts           = $immonex_openimmo2wp->backend_dialog_texts;
$current_status         = $immonex_openimmo2wp->current_import_status;
$global_import_dir      = preg_replace(
	'/wp-content.*/',
	'<strong>$0</strong>',
	apply_filters( 'immonex_oi2wp_global_import_dir', '' )
);

$user_import_base_folder_name = apply_filters( 'immonex_oi2wp_user_import_base_folder_name', '' );
$user_import_base_dir         = preg_replace(
	"/(wp-content.*)({$user_import_base_folder_name})/",
	'<strong>$1</strong><strong class="user-import-base-folder-name">$2</strong>',
	apply_filters( 'immonex_oi2wp_user_import_base_dir', '', true )
);
$user_import_folders          = apply_filters( 'immonex_oi2wp_keyed_import_folders', array(), true );
$pending_import_files         = apply_filters( 'immonex_oi2wp_import_zip_files', array(), false, 'status' );
$button_disabled              = $immonex_openimmo2wp->killswitch ? true : false;
$import_process_running       = ! empty( array_filter( $pending_import_files, function ( $import_file ) { return ! empty( $import_file['processing'] ); } ) );

if ( $immonex_openimmo2wp->get_killswitch() ) {
	$button_text     = $dialog_texts['import_stopped'];
	$button_disabled = true;
} elseif ( $import_process_running ) {
	// Import running, prepare some data for pre-rendering the page.
	if ( isset( $current_status['token'] ) ) {
		$button_text = $dialog_texts['import_running'];
		$button_disabled = true;
	} else {
		$button_text = $dialog_texts['resume_import'];
	}
} else {
	$button_text = $dialog_texts['start_import'];
	if ( 0 === count( $pending_import_files ) ) {
		$button_disabled = true;
	}
}

$file_process_status_wrap = <<<EOT
	<div id="file-process-status-wrap">
		<progress id="process-progress" max="100" value="0"></progress>
		<div id="process-scope"></div>
		<div id="process-current-property"></div>
		<div id="process-current-attachment"></div>
	</div>
EOT;

do_action( 'immonex_oi2wp_render_option_page_header' );
?>
<div id="immonex-manual-import-and-folders"<?php if ( empty( $pending_import_files ) ) echo ' class="no-pending-files"'; ?>>

	<?php if ( $license_active ) : ?>
	<div id="process-control-button-wrap">
		<button id="process-control-button" class="button button-primary"<?php if ( $button_disabled ) echo ' disabled'; ?>><?php echo $button_text; ?></button>
	</div>

	<div id="overall-process-result"<?php if ( $import_process_running || empty( $pending_import_files ) ) echo ' class="immonex-plugin-options__infobox immonex-plugin-options__infobox--is-notice"'; ?>>
		<?php echo $import_process_running ? '* ' . $dialog_texts['resume_info'] : $dialog_texts['no_pending_files_info']; ?>
	</div>
	<?php else : ?>
	<div class="immonex-plugin-options__infobox immonex-plugin-options__infobox--is-warning">
		<p>
		<?php
		echo wp_sprintf(
			__( 'Please activate the plugin license under <a href="%s">OpenImmo2WP &rarr; Settings &rarr; License</a>.', 'immonex-openimmo2wp' ),
			admin_url( "admin.php?page=immonex-openimmo2wp_settings&tab=tab_license" )
		);
		?>
		</p>
	</div>
	<?php endif; ?>

	<?php
	if ( $license_active && ! $immonex_openimmo2wp->enable_auto_import ) {
		echo wp_sprintf(
			'<div class="immonex-plugin-options__infobox immonex-plugin-options__infobox--is-warning"><p>%s (&rarr; <a href="%s">%s</a>).</p></div>',
			__( '<strong>Automated</strong> import processing is currently <strong>not</strong> enabled', 'immonex-openimmo2wp' ),
			admin_url( 'admin.php?page=immonex-openimmo2wp_settings' ),
			__( 'Settings', 'immonex-openimmo2wp' )
		);
	}
	?>

	<div id="import-ajax-debug">
		<div style="margin-bottom:.5em; font-size:90%; font-weight:bold">DEBUG</div>
		<p><?php _e( 'During import, the following additional output has been generated server-side. In most cases, these messages are PHP warnings (if WP_DEBUG is enabled), but they could also point to possible errors.', 'immonex-openimmo2wp' ); ?></p>
		<p class="debug-contents"></p>
	</div>

	<h2 style="margin-top:2em">
		<?php _e( 'Global', 'immonex-openimmo2wp' ); ?>
		<a href="https://docs.immonex.de/openimmo2wp/#/grundlagen/ordner?id=global" class="immonex-doc-link" target="_blank" aria-label="<?php _e( 'Documentation', 'immonex-openimmo2wp' ); ?>"></a>
	</h2>

	<div class="folder-section">
		<div class="primary-import-folder"><?php echo $global_import_dir; ?></div>
		<ul class="import-folder-list" name="global"><?php
			if ( ! empty( $pending_import_files ) ) :
				foreach ( $pending_import_files as $i => $import_file ) :
					if ( 'global' === $import_file['folder'] ) :
						$class = ! empty( $import_file['processing'] ) ? 'is-current is-processing' : '';
						if ( ! $class && ! $import_process_running && 0 === $i ) {
							$class = 'is-current';
						}
						?>
			<li name="<?php echo $import_file['file']; ?>"<?php if ( $class ) echo ' class="' . $class . '"'; ?>>
				<div class="bullet-spinner-wrap">
					<div class="proc-bullet"></div>
					<div class="proc-spinner"><?php echo $immonex_openimmo2wp->spinner_image; ?></div>
				</div>
				<div class="info-wrap">
					<div class="proc-order"><?php echo $i + 1; ?></div>
					<div class="proc-filename"><?php echo $import_file['file']; ?></div>
						<?php
						if ( ! empty( $import_file['processing'] ) ) {
							echo $file_process_status_wrap;
							$file_process_status_wrap = '';
						}
						?>
				</div>
			</li>
						<?php
					endif;
				endforeach;
			endif;
			?></ul>
	</div>

	<h2>
		<?php _e( 'User related', 'immonex-openimmo2wp' ); ?>
		<a href="https://docs.immonex.de/openimmo2wp/#/grundlagen/ordner?id=benutzerbezogen" class="immonex-doc-link" target="_blank" aria-label="<?php _e( 'Documentation', 'immonex-openimmo2wp' ); ?>"></a>
	</h2>

	<div class="folder-section">
		<div class="import-folder"><?php echo $user_import_base_dir; echo DIRECTORY_SEPARATOR !== substr( $user_import_base_dir, -1 ) ? DIRECTORY_SEPARATOR : ''; ?><code>wp-user-login-name</code></div>

		<div id="user-import-folder-file-lists"<?php if ( empty( $user_import_folders ) ) echo ' class="no-user-import-folders"'; ?>>
			<?php
			if ( ! empty( $user_import_folders ) ) :
				foreach ( $user_import_folders as $plain_folder => $path ) :
					$username = explode( DIRECTORY_SEPARATOR, $plain_folder )[1];
					?>
			<div class="import-folder dashicons-before dashicons-admin-users"><code><?php echo $username; ?></code></div>
			<ul class="import-folder-list" name="<?php echo $plain_folder; ?>">
					<?php
					if ( ! empty( $pending_import_files ) ) :
						foreach ( $pending_import_files as $i => $import_file ) :
							if ( $plain_folder === $import_file['folder'] ) :
								$class = ! empty( $import_file['processing'] ) ? 'is-current is-processing' : '';
								if ( ! $class && ! $import_process_running && 0 === $i ) {
									$class = 'is-current';
								}
								?>
				<li name="<?php echo $import_file['file']; ?>"<?php if ( $class ) echo ' class="' . $class . '"'; ?>>
					<div class="bullet-spinner-wrap">
						<div class="proc-bullet"></div>
						<div class="proc-spinner"><?php echo $immonex_openimmo2wp->spinner_image; ?></div>
					</div>
					<div class="info-wrap">
						<div class="proc-order"><?php echo $i + 1; ?></div>
						<div class="proc-filename"><?php echo $import_file['file']; ?></div>
								<?php
								if ( ! empty( $import_file['processing'] ) ) {
									echo $file_process_status_wrap;
									$file_process_status_wrap = '';
								}
								?>
					</div>
				</li>
								<?php
							endif;
						endforeach;
					endif;
					?>
			</ul>
					<?php
				endforeach;
			endif;
			?>

			<p id="no-user-import-folders-info">
				<?php
				echo wp_sprintf(
					__( 'No <a href="%1$s" class="immonex-doc-link" target="_blank">user import folders</a> exist yet. These must be created <strong>manually</strong> as subdirectory of the <span class="user-import-base-folder-name">users</span> folder above, whereby the names must match the <strong>login names</strong> of the related <a href="%2$s"> WP users</a>.', 'immonex-openimmo2wp' ),
					'https://docs.immonex.de/openimmo2wp/#/grundlagen/ordner?id=benutzerbezogen',
					get_admin_url( null, 'users.php' )
				);
				?>
			</p>
		</div>
	</div>

	<?php echo $file_process_status_wrap; ?>

</div>
<?php
do_action( 'immonex_oi2wp_render_option_page_footer' );
