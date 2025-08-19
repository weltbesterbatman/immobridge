<?php
/**
 * Class Mapping_Folders
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Mapping folder(s) related methods.
 */
class Mapping_Folders {

	const DEFAULT_MAPPING_FOLDER_NAME = 'mappings';

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
	 * Constructor
	 *
	 * @since 5.0.0
	 *
	 * @param mixed[]  $bootstrap_data Plugin bootstrap data.
	 * @param object[] $utils          Utility objects.
	 */
	public function __construct( $bootstrap_data, $utils ) {
		$this->data   = $bootstrap_data;
		$this->prefix = $bootstrap_data['plugin_prefix'];
		$this->utils  = $utils;

		add_filter( "{$this->prefix}mapping_folder_name", [ $this, 'get_mapping_folder_name' ], 5 );
		add_filter( "{$this->prefix}main_mapping_dir", [ $this, 'get_main_mapping_dir' ], 5 );
		add_filter( "{$this->prefix}mapping_folders", [ $this, 'get_folders' ], 5 );
		add_filter( "{$this->prefix}mapping_files", [ $this, 'get_files' ], 5, 2 );
		add_filter( "{$this->prefix}current_mapping_file", [ $this, 'get_current_file' ] );
	} // __construct

	/**
	 * Return the default mapping folder name to be used as a subdirectory
	 * of the global import directory (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string $folder_name Original folder name or empty string.
	 *
	 * @return string Mapping folder name.
	 */
	public function get_mapping_folder_name( $folder_name ) {
		return self::DEFAULT_MAPPING_FOLDER_NAME;
	} // get_mapping_folder_name

	/**
	 * Return the full path to the main mapping folder (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string $path Original folder path or empty string.
	 *
	 * @return string Main mapping folder (full path).
	 */
	public function get_main_mapping_dir( $path ) {
		$global_import_dir   = apply_filters( "{$this->prefix}global_import_dir", '' );
		$mapping_folder_name = apply_filters( "{$this->prefix}mapping_folder_name", '' );

		return $this->utils['string']->unify_dirsep( $global_import_dir, 1 ) . $mapping_folder_name;
	} // get_main_mapping_dir

	/**
	 * Return a list of all mapping folders (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string[] $mapping_folders Original mapping folder list or empty array.
	 *
	 * @return string[] Mapping folder list (full paths).
	 */
	public function get_folders( $mapping_folders ) {
		return [ apply_filters( "{$this->prefix}main_mapping_dir", '' ) ];
	} // get_folders

	/**
	 * Generate lists of available mapping CSV files.
	 *
	 * @since 5.0.0
	 *
	 * @param string[]        $files   Mapping CSV files or empty array.
	 * @param string|string[] $exclude Single or multiple filenames that should be excluded (optional).
	 *
	 * @return string[] Mapping files (full paths).
	 */
	public function get_files( $files, $exclude = '' ) {
		if ( ! is_array( $exclude ) ) {
			$exclude = [ $exclude ];
			if ( ! in_array( '_*', $exclude ) ) {
				$exclude[] = '_*';
			}
		}

		$folders = $this->utils['local_fs']->validate_dir_list(
			apply_filters( "{$this->prefix}mapping_folders", [] )
		);
		if ( empty( $folders ) ) {
			return [];
		}

		$params = [
			'scope'           => 'files',
			'file_extensions' => [ 'csv' ],
			'exclude'         => $exclude,
			'order_by'        => 'basename asc',
			'return_paths'    => true,
		];

		return $this->utils['local_fs']->scan_dir( $folders, $params );
	} // get_files

	/**
	 * Evaluate and return the full path of the current mapping file based on the
	 * given filename and the mapping folders list.
	 *
	 * @since 5.0.0
	 *
	 * @param string $filename Mapping filename (optional).
	 *
	 * @return string|false Full path to the current mapping file or false if not found.
	 */
	public function get_current_file( $filename = '' ) {
		if ( ! $filename ) {
			$filename = Filename_Utils::get_plain_basename( Registry::get( 'mapping_file' ) );
		}

		if ( ! $filename ) {
			return false;
		}

		$mapping_folders = $this->utils['local_fs']->validate_dir_list(
			apply_filters( "{$this->prefix}mapping_folders", [] )
		);
		if ( empty( $mapping_folders ) ) {
			return false;
		}

		$current_file = false;
		foreach ( $mapping_folders as $dir ) {
			if ( file_exists( trailingslashit( $dir ) . $filename ) ) {
				$current_file = $this->utils['string']->unify_dirsep( $dir, 1 ) . $filename;
			}
		}

		return $current_file;
	} // get_current_file

} // class Mapping_Folders
