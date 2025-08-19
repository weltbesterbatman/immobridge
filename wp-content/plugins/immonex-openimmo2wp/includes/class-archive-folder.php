<?php
/**
 * Class Archive_Folder
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Archive folder related methods.
 */
class Archive_Folder {

	const DEFAULT_ARCHIVE_FOLDER_NAME = 'archive';

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
	 * @param mixed[] $bootstrap_data Plugin bootstrap data.
     * @param object[] $utils         Utility objects.
     */
    public function __construct( $bootstrap_data, $utils ) {
        $this->data   = $bootstrap_data;
        $this->prefix = $bootstrap_data['plugin_prefix'];
        $this->utils  = $utils;

		add_filter( "{$this->prefix}archive_folder_name", array( $this, 'get_archive_folder_name' ), 5 );
		add_filter( "{$this->prefix}archive_dir", [ $this, 'get_archive_dir' ], 5 );
	} // __construct

	/**
	 * Return the default archive folder name to be used as a subdirectory
	 * of the global import directory (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string $folder_name Original folder name or empty string.
	 *
	 * @return string Archive folder name.
	 */
	public function get_archive_folder_name( $folder_name ) {
		return self::DEFAULT_ARCHIVE_FOLDER_NAME;
	} // get_archive_folder_name

	/**
	 * Return the full path to the archive folder (filter callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string $path Original folder path or empty string.
	 *
	 * @return string Archive folder (full path).
	 */
	public function get_archive_dir( $path ) {
		$global_import_dir   = apply_filters( "{$this->prefix}global_import_dir", '' );
		$archive_folder_name = apply_filters( "{$this->prefix}archive_folder_name", '' );

		return $this->utils['string']->unify_dirsep( $global_import_dir, 1 ) . $archive_folder_name;
	} // get_archive_dir

} // class Archive_Folder
