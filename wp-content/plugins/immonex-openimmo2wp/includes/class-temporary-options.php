<?php
/**
 * Class Temporary_Options
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Methods related to temporary options.
 */
class Temporary_Options {

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
	 * @since 5.0.14
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
	 * Get a temporary option (current blog based for multisite installs), used
	 * for current status data and the like in most cases. Expired options will
	 * be deleted automatically, same for data that does not belong to the currently
	 * processed XML file.
	 *
	 * @since 5.0.14
	 *
	 * @param string $option_name             Option name (plugin slug will be added as prefix if omitted).
	 * @param string $current_import_xml_file Current import XML file.
	 * @param bool   $omit_checks             False if the relation to the currently processed XML and the expiry
	 *                                        file shall not be checked.
	 *
	 * @return mixed[]|bool Option data array or false if not found or expired.
	 */
	public function get( $option_name, $current_import_xml_file, $omit_checks = false ) {
		if ( substr( $option_name, 0, strlen( $this->data['plugin_slug'] ) ) !== $this->data['plugin_slug'] ) {
			// Add plugin slug als option name prefix.
			$option_name = "{$this->data['plugin_slug']}_{$option_name}";
		}

		wp_cache_delete( 'alloptions', 'options' );
		$option = is_multisite() ?
			get_blog_option( get_current_blog_id(), $option_name ) :
			get_option( $option_name );

		if (
			! $omit_checks
			&& (
				(
					$current_import_xml_file
					&& (
						! isset( $option['meta']['xml_file'] )
						|| $option['meta']['xml_file'] !== $current_import_xml_file
					)
				)
				|| (
					isset( $option['meta']['expires'] )
					&& (int) $option['meta']['expires'] > 0
					&& (int) $option['meta']['expires'] < time()
				)
			)
		) {
			// Delete temporary option if outdated or if it isn't related to the
			// currently processed XML file
			$this->delete( $option_name );
			return false;
		}

		return $option && isset( $option['data'] ) ? $option['data'] : $option;
	} // get

	/**
	 * Save/Update a temporary option
	 *
	 * @since 5.0.14
	 *
	 * @param string      $option_name             Option name (plugin slug will be added as prefix if omitted).
	 * @param mixed[]     $values                  Option values as associative array.
	 * @param string      $current_import_xml_file Current import XML file.
	 * @param bool|string $expires                 Expiry time (timestamp) or false if the option should
	 *                                             never expire (default).
	 *
	 * @return bool Update result.
	 */
	public function update( $option_name, $values, $current_import_xml_file, $expires = false ) {
		if ( ! is_array( $values ) ) {
			return false;
		}

		if ( substr( $option_name, 0, strlen( $this->data['plugin_slug'] ) ) !== $this->data['plugin_slug'] ) {
			// Add plugin slug als option name prefix.
			$option_name = "{$this->data['plugin_slug']}_{$option_name}";
		}

		$data = [
			'meta' => [
				'xml_file' => $current_import_xml_file,
				'expires' => (int) $expires,
			],
			'data' => $values,
		];

		if ( is_multisite() ) {
			$update_result = update_blog_option( get_current_blog_id(), $option_name, $data );
		} else {
			$update_result = update_option( $option_name, $data, false );
		}

		return $update_result;
	} // update

	/**
	 * Delete one or multiple temporary option.
	 *
	 * @since 5.0.14
	 *
	 * @param string[]|string $option_names Option names (plugin slug will be added as prefix if omitted).
	 *
	 * @return bool Delete result.
	 */
	public function delete( $option_names ) {
		if ( ! is_array( $option_names ) ) {
			$option_names = [ $option_names ];
		}

		$success = true;

		foreach ( $option_names as $option_name ) {
			if ( substr( $option_name, 0, strlen( $this->data['plugin_slug'] ) ) !== $this->data['plugin_slug'] ) {
				// Add plugin slug als option name prefix.
				$option_name = "{$this->data['plugin_slug']}_{$option_name}";
			}

			if ( is_multisite() ) {
				$delete_result = delete_blog_option( get_current_blog_id(), $option_name );
			} else {
				$delete_result = delete_option( $option_name );
			}

			if ( ! $delete_result ) {
				$success = false;
			}
		}

		return $success;
	} // delete

} // class Temporary_Options
