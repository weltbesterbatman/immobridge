<?php
namespace immonex\OpenImmo2Wp;

/**
 * "Cleanup" related methods.
 */
class Cleanup {

	/**
	 * Perform cleanup tasks after the processing of an import archive has
	 * been completed.
	 *
	 * @since 4.7.0
	 *
	 * @return string[] List of deleted (outdated/expired) files.
	 */
	public static function cleanup_after_import() {
		$deleted = array();

		$deleted_files = self::delete_outdated_archive_files();
		if ( ! empty( $deleted_files ) ) {
			$deleted = $deleted_files;
		}

		$deleted_files = self::delete_outdated_mapping_backups();
		if ( ! empty( $deleted_files ) ) {
			$deleted = array_merge( $deleted, $deleted_files );
		}

		return $deleted;
	} // cleanup_after_import

	/**
	 * Delete outdated/expired archive files.
	 *
	 * @since 4.7.0
	 *
	 * @return string[]|bool List of deleted files or false if disabled or error.
	 */
	public static function delete_outdated_archive_files() {
		$archive_dir = apply_filters( Registry::get( 'plugin_prefix' ) . 'archive_dir', '' );
		if ( ! $archive_dir ) {
			return false;
		}

		$max_file_age_days = Registry::get( 'archive_files_max_age_days' );
		if ( 0 === (int) $max_file_age_days ) {
			return false;
		}
		$older_than_ts = strtotime( "-{$max_file_age_days} days", current_time( 'timestamp' ) );

		$fs = Registry::get( 'wp_filesystem' );
		if ( ! $fs ) {
			return false;
		}

		$log = Registry::get( 'log' );
		$import_running = Registry::get( 'plugin' )->current_import_status ? true : false;

		$params = [
			'scope'           => 'files',
			'file_extensions' => [ 'zip', 'log' ],
			'mtime'           => "<{$older_than_ts}",
			'return_paths'    => true,
		];

		$deleted = array();
		$outdated = Registry::get( 'local_fs_utils' )->scan_dir( $archive_dir, $params );

		if ( count( $outdated ) > 0 ) {
			foreach ( $outdated as $file ) {
				$delete_success = $fs->delete( $file );

				if ( $delete_success ) {
					$deleted[] = $file;
				}

				if ( $log && $import_running ) {
					if ( $delete_success ) {
						$log->add(
							wp_sprintf(
								__( 'Expired archive/log file deleted: %s', 'immonex-openimmo2wp' ),
								basename( $file )
							),
							'debug'
						);
					} else {
						$log->add(
							wp_sprintf(
								__( 'Expired archive/log file could not be deleted: %s', 'immonex-openimmo2wp' ),
								basename( $file )
							),
							'error'
						);
					}
				}
			}
		}

		return $deleted;
	} // delete_outdated_archive_files

	/**
	 * Delete outdated backup mapping files.
	 *
	 * @since 4.7.0
	 *
	 * @return string[]|bool List of deleted files or false if disabled or error.
	 */
	public static function delete_outdated_mapping_backups() {
		$main_mapping_dir = apply_filters( Registry::get( 'plugin_prefix' ) . 'main_mapping_dir', '' );
		if ( ! $main_mapping_dir ) {
			return false;
		}

		$max_file_age_months = Registry::get( 'mapping_backups_max_age_months' );
		if ( 0 === (int) $max_file_age_months ) {
			return false;
		}
		$older_than_ts = strtotime( "-{$max_file_age_months} months", current_time( 'timestamp' ) );

		$fs = Registry::get( 'wp_filesystem' );
		if ( ! $fs ) {
			return false;
		}

		$log = Registry::get( 'log' );
		$import_running = Registry::get( 'plugin' )->current_import_status ? true : false;
		$current_mapping_file = Filename_Utils::get_plain_basename( Registry::get( 'mapping_file' ) );

		$params = [
			'scope'            => 'files',
			'file_extensions'  => [ 'csv' ],
			'mtime'            => "<{$older_than_ts}",
			'filename_ts_mode' => 'only',
			'return_paths'     => true,
		];

		$deleted = array();
		$outdated = Registry::get( 'local_fs_utils' )->scan_dir( $main_mapping_dir, $params );

		if ( count( $outdated ) > 0 ) {
			foreach ( $outdated as $file ) {
				if ( Filename_Utils::get_plain_basename( $file ) === $current_mapping_file ) {
					/**
					 * Prevent all backups of the currently selected mapping table
					 * from being deleted.
					 */
					continue;
				}

				$delete_success = $fs->delete( $file );
				if ( $delete_success ) {
					$deleted[] = $file;
				}

				if ( $log && $import_running ) {
					if ( $delete_success ) {
						$log->add(
							wp_sprintf(
								__( 'Outdated mapping backup file deleted: %s', 'immonex-openimmo2wp' ),
								basename( $file )
							),
							'debug'
						);
					} else {
						$log->add(
							wp_sprintf(
								__( 'Outdated mapping backup file could not be deleted: %s', 'immonex-openimmo2wp' ),
								basename( $file )
							),
							'error'
						);
					}
				}
			}
		}

		return $deleted;
	} // delete_outdated_mapping_backups

} // class Cleanup
