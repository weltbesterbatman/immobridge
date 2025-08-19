<?php
/**
 * Class Security
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Security related methods.
 */
class Security {

	const UNALLOWED_FILE_TYPES = [ 'php' ];

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
	 * @since 5.0.9
	 *
	 * @param mixed[] $bootstrap_data Plugin bootstrap data.
	 * @param object[] $utils Utility objects.
	 */
	public function __construct( $bootstrap_data, $utils ) {
		$this->data   = $bootstrap_data;
		$this->prefix = $bootstrap_data['plugin_prefix'];
		$this->utils  = $utils;
	} // __construct

	/**
	 * Delete files with unallowed types (esp. PHP) from import folder(s).
	 *
	 * @since 5.0.9
	 *
	 * @param string|bool $folder Absolute path to one specific folder (optional).
	 */
	public function delete_unallowed_files_from_import_folders( $folder = false ) {
		$import_folders      = $folder ? [ $folder ] : apply_filters( "{$this->prefix}keyed_import_folders", [] );
		$files_to_be_deleted = [];

		if ( ! empty( $import_folders ) ) {
			$params = [
				'file_extensions' => self::UNALLOWED_FILE_TYPES,
				'return_paths'    => true,
			];

			$files_to_be_deleted = array_filter(
				$this->utils['local_fs']->scan_dir( array_values( $import_folders ), $params ),
				function ( $file ) {
					return basename( $file ) !== 'index.php';
				}
			);
		}

		if ( ! empty( $files_to_be_deleted ) ) {
			$not_deleted_files = [];
			$this->send_file_deletion_admin_notification( $files_to_be_deleted );

			foreach ( $files_to_be_deleted as $file ) {
				if ( ! $this->utils['wp_fs']->delete( $file ) ) {
					$not_deleted_files[] = $file;
				}
			}

			if ( ! empty( $not_deleted_files ) ) {
				$this->send_file_deletion_admin_notification( $not_deleted_files, 'error' );
			}
		}
	} // delete_unallowed_files_from_import_folders

	/**
	 * Send an admin notification mail when unallowed files have been deleted from
	 * an import folder.
	 *
	 * @since 5.0.9
	 *
	 * @param $files string[] Array of file absolute file paths.
	 * @param $type string    Type of notification mail (optional, "error" if files
	 *                        could not be deleted).
	 */
	private function send_file_deletion_admin_notification( $files, $type = '' ) {
		$recipient = get_option( 'admin_email' );
		$headers   = apply_filters( "{$this->prefix}mail_headers", [] );
		$att_files = [];

		$template_data = array(
			'preset' => 'admin_info',
		);

		switch ( $type ) {
			case 'error' :
				$subject = $this->data['plugin_name'] . ': ' . __( 'Errors deleting unallowed files from import folders', 'immonex-openimmo2wp' );
				$info1   = __( 'The following unallowed files <strong>could not</strong> be deleted from the import folders:', 'immonex-openimmo2wp' );
				$info2   = __( 'Please manually delete these files immediately for security reasons!', 'immonex-openimmo2wp' );
				break;
			default :
				$subject = $this->data['plugin_name'] . ': ' . __( 'Unallowed files deleted from import folders', 'immonex-openimmo2wp' );
				$info1   = __( 'The following files have been deleted because these file types are not allowed in <strong>import folders</strong> for security reasons:', 'immonex-openimmo2wp' );
				$info2   = __( 'The deleted files have been attached to this mail.', 'immonex-openimmo2wp' );

				foreach ( $files as $file ) {
					$att_files[ basename( $file ) . '_' ] = $file;
				}
		}

		$body_html = "<p>{$info1}</p>" . PHP_EOL
			. '<ul><li>'
			. implode( '</li>' . PHP_EOL . ' <li>', $files )
			. '</li></ul>' . PHP_EOL
			. "<p>{$info2}</p>";

		$body = array( 'html' => $body_html );

		$this->utils['mail']->send( $recipient, $subject, $body, $headers, $att_files, $template_data );
	} // send_file_deletion_admin_notification

} // class Security
