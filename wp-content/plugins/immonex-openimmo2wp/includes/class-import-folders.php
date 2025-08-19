<?php
/**
 * Class Import_Folders
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Import folder related methods.
 */
class Import_Folders {

	const DEFAULT_WORKING_FOLDER_NAME          = 'immonex-openimmo-import';
	const DEFAULT_USER_IMPORT_BASE_FOLDER_NAME = 'users';

	/**
	 * Array of bootstrap data
	 *
	 * @var mixed[]
	 */
	private $data;

	/**
	 * Utility objects
	 *
	 * @var object[]
	 */
	private $utils;

	/**
	 * Prefix for hook names etc.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * WP upload directory information
	 *
	 * @var string[]
	 */
	private $upload_dir;

	/**
	 * Current pending import file list (false before first directory scan)
	 *
	 * @var \SplFileInfo[]|bool
	 */
	private $files = false;

	/**
	 * Alternative file list for status updates (false before first directory scan)
	 *
	 * @var mixed[]|bool
	 */
	private $files_status = false;

	/**
	 * Currently processed import file (full path or array with plain folder name and filename)
	 *
	 * @var string|string[]
	 */
	public $file_in_processing = '';

	/**
	 * Constructor
	 *
	 * @since 5.0.0
	 *
	 * @param mixed[]  $bootstrap_data Plugin bootstrap data.
	 * @param object[] $utils          Utility objects.
	 */
	public function __construct( $bootstrap_data, $utils ) {
		$this->data       = $bootstrap_data;
		$this->prefix     = $bootstrap_data['plugin_prefix'];
		$this->utils      = $utils;
		$this->upload_dir = wp_upload_dir( null, true, true );

		add_action( "{$this->prefix}import_zip_file_in_processing", [ $this, 'set_file_in_processing' ] );
		add_action( "{$this->prefix}import_zip_file_processed", [ $this, 'remove_processed_file' ] );

		add_filter( "{$this->prefix}working_folder_name", [ $this, 'get_working_folder_name' ], 5 );
		add_filter( "{$this->prefix}working_dir", [ $this, 'get_working_dir' ], 5 );
		add_filter( "{$this->prefix}working_url", [ $this, 'get_working_url' ], 5 );
		add_filter( "{$this->prefix}global_import_dir", [ $this, 'get_working_dir' ], 5 );
		add_filter( "{$this->prefix}user_import_base_folder_name", [ $this, 'get_user_import_base_folder_name' ], 5 );
		add_filter( "{$this->prefix}user_import_base_dir", [ $this, 'get_user_import_base_dir' ], 5 );
		add_filter( "{$this->prefix}keyed_import_folders", [ $this, 'get_keyed_folders' ], 5, 2 );
		add_filter( "{$this->prefix}import_zip_files", [ $this, 'get_import_zip_files' ], 5, 4 );
		add_filter( "{$this->prefix}plain_import_folder", [ $this, 'get_plain_folder' ], 5, 2 );
	} // __construct

	/**
	 * Return the default working folder name to be used as a subdirectory
	 * of the upload basedir (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string $folder_name Original folder name or empty string.
	 *
	 * @return string Working folder name.
	 */
	public function get_working_folder_name( $folder_name ) {
		return self::DEFAULT_WORKING_FOLDER_NAME;
	} // get_working_folder_name

	/**
	 * Generate and return the working directory where the import files are
	 * being processed (filter callback). This folder is identical with the default
	 * "global" import base directory.
	 *
	 * @since 5.0.0
	 *
	 * @param string $path Original path or empty string.
	 *
	 * @return string Working/Import base directory (full path).
	 */
	public function get_working_dir( $path ) {
		$working_folder_name = apply_filters( "{$this->prefix}working_folder_name", '' );

		$working_dir = $this->upload_dir['basedir'] . DIRECTORY_SEPARATOR . $working_folder_name;

		if ( '/' !== DIRECTORY_SEPARATOR ) {
			$working_dir = str_replace( '/', DIRECTORY_SEPARATOR, $working_dir );
		}

		return $working_dir;
	} // get_working_dir

	/**
	 * Generate and return the working directory URL required for attachment
	 * processing (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string $url Original URL or empty string.
	 *
	 * @return string Working directory URL.
	 */
	public function get_working_url( $url ) {
		$working_folder_name = apply_filters( "{$this->prefix}working_folder_name", '' );

		return $this->utils['string']->unify_dirsep( $this->upload_dir['baseurl'], 1 ) . $working_folder_name;
	} // get_working_url

	/**
	 * Return the default user import base folder name to be used as a subdirectory
	 * of the global import directory (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string $folder_name Original folder name or empty string.
	 *
	 * @return string User import base folder name.
	 */
	public function get_user_import_base_folder_name( $folder_name ) {
		return self::DEFAULT_USER_IMPORT_BASE_FOLDER_NAME;
	} // get_user_import_base_folder_name

	/**
	 * Generate and return the base directory for user-based import folders (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string $path Original path or empty string.
	 *
	 * @return string User import base directory (full path).
	 */
	public function get_user_import_base_dir( $path ) {
		$global_import_dir            = apply_filters( "{$this->prefix}global_import_dir", '' );
		$user_import_base_folder_name = apply_filters( "{$this->prefix}user_import_base_folder_name", '' );

		return $this->utils['string']->unify_dirsep( $global_import_dir, 1 ) . $user_import_base_folder_name;
	} // get_user_import_base_dir

	/**
	 * Generate lists of ZIP archives pending to be imported in all import folders
	 * or the specified folder only (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param \SplFileInfo[]|string[] $files        Import ZIP files or empty array.
	 * @param string|bool             $single_dir   Scan a specific directory ONLY (optional).
	 * @param string|bool             $return_type  Optional type of list to return: "file_info" (default),
	 *                                              "paths", "status" or "count".
	 * @param bool                    $force_update Update file lists if true (optional, false by default).
	 *
	 * @return \SplFileInfo[]|string[]|int List of ZIP files or number of files only.
	 */
	public function get_import_zip_files( $files, $single_dir = false, $return_type = 'file_info', $force_update = false ) {
		if ( ! $return_type ) {
			$return_type = 'file_info';
		}

		if ( ! $force_update && ! $single_dir && false !== $this->files ) {
			switch ( $return_type ) {
				case 'paths' :
					return array_keys( $this->files );
				case 'status' :
					return $this->files_status;
				case 'count' :
					return count( $this->files );
				default :
					return $this->files;
			}
		}

		$folders = $single_dir ? [ $single_dir ] : $this->get_folders();

		if ( empty( $folders ) ) {
			return [];
		}

		$params = [
			'scope'           => 'files',
			'file_extensions' => [ 'zip' ],
			'order_by'        => 'mtime asc',
		];

		$file_list        = $this->utils['local_fs']->scan_dir( $folders, $params );
		$file_list_status = [];

		if ( ! empty( $file_list ) ) {
			/**
			 * Generate an alternative file list for status display purposes.
			 */
			foreach ( $file_list as $path => $file_info ) {
				$file  = [
					'folder' => apply_filters( "{$this->prefix}plain_import_folder", '', $path ),
					'file'   => $file_info->getBasename(),
				];

				if ( $this->compare_file_in_processing( $file, $this->file_in_processing ) ) {
					$file['processing'] = true;
				}

				$file_list_status[] = $file;
			}
		}

		if ( ! $single_dir ) {
			$this->files        = $file_list;
			$this->files_status = $file_list_status;
		}

		switch ( $return_type ) {
			case 'paths' :
				return array_keys( $file_list );
			case 'status' :
				return $file_list_status;
			case 'count' :
				return count( $file_list );
			default :
				return $file_list;
		}
	} // get_import_zip_files

	/**
	 * Return the "plain" folder name ("global" or "users/username") for the given path (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string $plain_folder Original plain folder name or empty string.
	 * @param string $path         Full import file/folder path.
	 *
	 * @return string Plain import folder name.
	 */
	public function get_plain_folder( $plain_folder, $path ) {
		if ( $this->utils['wp_fs']->is_dir( $path ) ) {
			$path = $this->utils['string']->unify_dirsep( $path, 1 );
		}

		$user_import_base_dir = $this->utils['string']->unify_dirsep( apply_filters( "{$this->prefix}user_import_base_dir", '' ), 1 );

		if ( substr( $path, 0, strlen( $user_import_base_dir ) ) === $user_import_base_dir ) {
			$folder = explode( DIRECTORY_SEPARATOR, substr( $path, strlen( $user_import_base_dir ) ) )[0];
			return self::DEFAULT_USER_IMPORT_BASE_FOLDER_NAME . DIRECTORY_SEPARATOR . $folder;
		}

		return 'global';
	} // get_plain_folder

	/**
	 * Compile a list of all folders that can contain import ZIP files (possibly
	 * modified by a filter function).
	 *
	 * @since 5.0.0
	 *
	 * @return string[]|null Import folder list.
	 */
	private function get_folders() {
		$include_global_subfolders = Registry::get( 'include_global_subfolders' );

		if ( is_null( $include_global_subfolders ) ) {
			return;
		}

		$global_import_dir    = apply_filters( "{$this->prefix}global_import_dir", '' );
		$user_import_base_dir = apply_filters( "{$this->prefix}user_import_base_dir", '' );
		$import_folders       = [ $global_import_dir ];

		$unzip_dir = '';
		if ( ! empty( $this->file_in_processing ) ) {
			if ( is_array( $this->file_in_processing ) && ! empty( $this->file_in_processing['file'] ) ) {
				$unzip_dir = $this->utils['string']::get_plain_unzip_folder_name( $this->file_in_processing['file'] );
			} elseif ( is_string( $this->file_in_processing ) ) {
				$unzip_dir = $this->utils['string']::get_plain_unzip_folder_name( $this->file_in_processing );
			}
		}

		$exclude = $unzip_dir ? [ '_*', $unzip_dir ] : [ '_*' ];

		if ( $include_global_subfolders ) {
			$params            = [
				'scope'        => 'folders',
				'exclude'      => array_merge( $exclude, [ 'archive', 'mappings', 'users' ] ),
				'return_paths' => true,
			];
			$global_subfolders = $this->utils['local_fs']->scan_dir( $global_import_dir, $params );

			if ( ! empty( $global_subfolders ) ) {
				$import_folders = array_merge( $import_folders, $global_subfolders );
			}
		}

		$params = [
			'scope'        => 'folders',
			'exclude'      => $exclude,
			'return_paths' => true,
		];

		$user_import_folders = $this->utils['local_fs']->scan_dir( $user_import_base_dir, $params );

		if ( ! empty( $user_import_folders ) ) {
			$import_folders = array_merge( $import_folders, $user_import_folders );
		}

		$filtered_folders = apply_filters( "{$this->prefix}import_folders", $import_folders );
		if (
			is_array( $filtered_folders )
			&& ! empty( array_diff( $filtered_folders, $import_folders ) )
		) {
			foreach ( $filtered_folders as $i => $dir ) {
				if ( ! is_dir( $dir ) ) {
					unset( $filtered_folders[ $i ] );
				}
			}

			if ( ! empty( $filtered_folders ) ) {
				$import_folders = $filtered_folders;
			}
		}

		return array_unique( $import_folders );
	} // get_folders

	/**
	 * Return a "keyed" variant of the import folder list (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string[] $folders           Empty array.
	 * @param bool     $user_folders_only Omit global import folder if true (false by default).
	 *
	 * @return string[] Keyed import folder list.
	 */
	public function get_keyed_folders( $folders, $user_folders_only = false ) {
		$folders = $this->get_folders();

		if ( empty( $folders ) ) {
			return [];
		}

		$keyed_folders    = [];
		$import_zip_files = apply_filters( "{$this->prefix}import_zip_files", [], false, 'status' );

		if ( ! empty( $import_zip_files ) ) {
			foreach ( $import_zip_files as $import_file ) {
				if ( $user_folders_only && 'global' === $import_file['folder'] ) {
					continue;
				}

				// Folders with pending files should be on top of the list.
				if ( ! isset( $keyed_folders[ $import_file['folder'] ] ) ) {
					$keyed_folders[ $import_file['folder'] ] = '';
				}
			}
		}

		foreach ( $folders as $path ) {
			$plain_folder = apply_filters( "{$this->prefix}plain_import_folder", '', $path );

			if ( $user_folders_only && 'global' === $plain_folder ) {
				continue;
			}

			$keyed_folders[ $plain_folder ] = $path;
		}

		return $keyed_folders;
	} // get_keyed_folders

	/**
	 * Set the import ZIP archive currently in processing (action callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string|string[] $path Current import file (full path as string or array with plain folder and filename).
	 */
	public function set_file_in_processing( $path ) {
		$this->file_in_processing = $path;

		if ( ! empty( $this->files_status ) ) {
			if ( is_array( $path ) ) {
				$file_in_processing = [
					'folder' => $path['folder'],
					'file'   => $path['file'],
				];
			} else {
				$file_in_processing = [
					'folder' => apply_filters( "{$this->prefix}plain_import_folder", '', $path ),
					'file'   => basename( $path ),
				];
			}

			array_walk(
				$this->files_status,
				function( &$file_item, $index, $file_in_processing ) {
					if (
						! isset( $file_item['processing'] )
						&& $file_item['folder'] === $file_in_processing['folder']
						&& $file_item['file'] === $file_in_processing['file']
					) {
						$file_item['processing'] = true;
					} elseif ( isset( $file_item['processing'] ) ) {
						unset( $file_item['processing'] );
					}
				},
				$file_in_processing
			);
		}
	} // set_file_in_processing

	/**
	 * Remove a processed file from the lists (action callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string $path Processed import file (full path).
	 */
	public function remove_processed_file( $path ) {
		if ( $this->compare_file_in_processing( $this->file_in_processing, $path ) ) {
			$this->file_in_processing = '';
		}

		if ( ! empty( $this->files ) && isset( $this->files[ $path ] ) ) {
			unset( $this->files[ $path ] );

			if ( is_array( $path ) ) {
				$processed_file = [
					'folder' => $path['folder'],
					'file'   => $path['file'],
				];
			} else {
				$processed_file = [
					'folder' => apply_filters( "{$this->prefix}plain_import_folder", '', $path ),
					'file'   => basename( $path ),
				];
			}

			$this->files_status = array_filter(
				$this->files_status,
				function( $file_item ) use ( $processed_file ) {
					return $file_item['folder'] !== $processed_file['folder']
						|| $file_item['file'] !== $processed_file['file'];
				}
			);
		}
	} // remove_processed_file

	/**
	 * Compare file definitions (path strings and/or folder/filename arrays).
	 *
	 * @since 5.0.0
	 *
	 * @param string $a File A.
	 * @param string $a File B.
	 *
	 * @return bool True if both file definitions are equal.
	 */
	private function compare_file_in_processing( $a, $b ) {
		if ( is_string( $a ) ) {
			$a = [
				'folder' => apply_filters( "{$this->prefix}plain_import_folder", '', $a ),
				'file'   => basename( $a ),
			];
		}

		if ( is_string( $b ) ) {
			$b = [
				'folder' => apply_filters( "{$this->prefix}plain_import_folder", '', $b ),
				'file'   => basename( $b ),
			];
		}

		return $a['folder'] === $b['folder'] && $a['file'] === $b['file'];
	} // compare_file_in_processing

} // class Import_Folders
