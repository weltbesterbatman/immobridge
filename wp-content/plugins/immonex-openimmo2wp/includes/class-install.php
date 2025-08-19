<?php
/**
 * Class Install
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Plugin installation/activation related methods.
 */
class Install {

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
	 * @param object[] $utils Utility objects.
	 */
	public function __construct( $bootstrap_data, $utils ) {
		$this->data   = $bootstrap_data;
		$this->prefix = $bootstrap_data['plugin_prefix'];
		$this->utils  = $utils;
	} // __construct

	/**
	 * Set theme/plugin-specific option values that contain content to be translated.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed[] $plugin_options Plugin options.
	 */
	public function insert_translated_default_option_values( &$plugin_options ) {
		if ( empty( $plugin_options ) ) {
			return;
		}

		foreach ( $plugin_options as $option_name => $option_value ) {
			if ( 'INSERT_TRANSLATED_DEFAULT_VALUE' === $option_value ) {
				switch ( $option_name ) {
					case 'brings-online_address_publishing_not_approved_message' :
					case 'bo-beladomo_address_publishing_not_approved_message' :
					case 'bo-beladomo20_address_publishing_not_approved_message' :
					case 'bo-immobilia18_address_publishing_not_approved_message' :
					case 'bo-home_address_publishing_not_approved_message' :
						$translated_value = __( "We'll tell you the exact address on request.", 'immonex-openimmo2wp' );
						break;
					case 'brings-online_new_label' :
					case 'bo-beladomo_new_label' :
					case 'bo-beladomo20_new_label' :
					case 'bo-immobilia18_new_label' :
					case 'bo-home_new_label' :
						$translated_value = __( 'NEW', 'immonex-openimmo2wp' );
						break;
					case 'bo-beladomo_agent_box_headline' :
					case 'bo-beladomo20_agent_box_headline' :
					case 'bo-immobilia18_agent_box_headline' :
						$translated_value = __( 'Agent', 'immonex-openimmo2wp' );
						break;
					case 'realhomes_default_title_nameless_floor_plans' :
						$translated_value = __( 'Floor Plan', 'immonex-openimmo2wp' );
						break;
					case 'realplaces_default_title_nameless_floor_plans' :
						$translated_value = __( 'Floor Plan', 'immonex-openimmo2wp' );
						break;
					case 'hometown_address_publishing_not_approved_message' :
						$translated_value = __( "We'll tell you the exact address on request.", 'immonex-openimmo2wp' );
						break;
					case 'freehold_add_description_groups' :
						$translated_value = wp_sprintf( 'additional_details:%s,epass:%s,prices:%s', __( 'Further Details', 'immonex-openimmo2wp' ), __( 'Energy Pass', 'immonex-openimmo2wp' ), __( 'Additional Information on Prices' , 'immonex-openimmo2wp' ) );
						break;
					case 'cozy_description_sections' :
						$translated_value = wp_sprintf( "group:areas:%s\ngroup:prices:%s\nproperty_description:%s\namenity_description:%s\ngroup:amenities_features\nlocation_description:%s\ngroup:location\nfloor_plans:%s\nmisc_description:%s\ngroup:condition\ngroup:misc\ngroup:epass:%s\ndownloads_links:%s",
							__( 'Further Area Information', 'immonex-openimmo2wp' ),
							__( 'Further Price Information', 'immonex-openimmo2wp' ),
							_x( 'Property', 'Objekt', 'immonex-openimmo2wp' ),
							__( 'Amenities and Features', 'immonex-openimmo2wp' ),
							__( 'Location' , 'immonex-openimmo2wp' ),
							__( 'Floor Plans' , 'immonex-openimmo2wp' ),
							__( 'Miscellaneous', 'immonex-openimmo2wp' ),
							__( 'Energy Pass', 'immonex-openimmo2wp' ),
							__( 'Downloads & Links', 'immonex-openimmo2wp' )
						);
						break;
					case 'wp-listings_add_description_content' :
						$translated_value = wp_sprintf(
							'[immonex_widget name="immonex_User_Defined_Properties_Widget" display_mode="exclude" display_groups="epass"]' . "\n" .
							'[immonex_widget name="immonex_User_Defined_Properties_Widget" title="%1$s" display_mode="include" display_groups="epass"]' . "\n" .
							'[immonex_widget name="immonex_Property_Attachments_Widget" title="%2$s"]',
							__( 'Energy Pass', 'immonex-openimmo2wp' ),
							__( 'Downloads & Links', 'immonex-openimmo2wp' )
						);
						break;
					case 'realia_plugin_add_description_content' :
					case 'realeswp_add_description_content' :
						$translated_value = wp_sprintf(
							'[immonex_widget name="immonex_User_Defined_Properties_Widget" title="%1$s" display_mode="exclude" display_groups="epass"]' . "\n" .
							'[immonex_widget name="immonex_User_Defined_Properties_Widget" title="%2$s" display_mode="include" display_groups="epass"]' . "\n" .
							'[immonex_widget name="immonex_Property_Attachments_Widget" title="%3$s"]',
							__( 'Details', 'immonex-openimmo2wp' ),
							__( 'Energy Pass', 'immonex-openimmo2wp' ),
							__( 'Downloads & Links', 'immonex-openimmo2wp' )
						);
						break;
					case 'wpcasa_plugin_remark_not_exact_location' :
						$translated_value = __( 'The marker does not show the exact property location.', 'immonex-openimmo2wp' );
						break;
					default :
						$translated_value = '';
				}

				$plugin_options[ $option_name ] = $translated_value;
			}
		}
	} // insert_translated_default_option_values

	/**
	 * Replace the maximum execution time option by the default value if 0.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed[] $plugin_options Plugin options.
	 */
	public function maybe_update_max_exec_time( &$plugin_options ) {
		if ( 0 === (int) $plugin_options['max_script_exec_time'] ) {
			$plugin_options['max_script_exec_time'] = Process_Resources::DEFAULT_MAX_SCRIPT_EXEC_TIME;
		}
	} // maybe_update_max_exec_time

	/**
	 * Create required folders.
	 *
	 * @since 5.0.0
	 *
	 * @return mixed[] Result and message on error.
	 */
	public function create_folders() {
		$working_dir       = apply_filters( "{$this->prefix}working_dir", '' );
		$global_import_dir = apply_filters( "{$this->prefix}global_import_dir", '' );

		if ( $working_dir === $global_import_dir ) {
			$required = [
				[
					'name'          => __( 'working folder', 'immonex-openimmo2wp' )
						. '/' . __( 'global import folder', 'immonex-openimmo2wp' ),
					'path'          => $working_dir,
					'copy_htaccess' => true,
				],
			];
		} else {
			$required = [
				[
					'name'          => __( 'working folder', 'immonex-openimmo2wp' ),
					'path'          => $working_dir,
					'copy_htaccess' => true,
				],
				[
					'name'          => __( 'global import folder', 'immonex-openimmo2wp' ),
					'path'          => $global_import_dir,
					'copy_htaccess' => true,
				],
			];
		}


		$failed   = [];
		$required = array_merge(
			$required,
			[
				[
					'name'          => __( 'user import base folder', 'immonex-openimmo2wp' ),
					'path'          => apply_filters( "{$this->prefix}user_import_base_dir", '' ),
					'copy_htaccess' => true,
				],
				[
					'name'          => __( 'archive folder', 'immonex-openimmo2wp' ),
					'path'          => apply_filters( "{$this->prefix}archive_dir", '' ),
					'copy_htaccess' => false,
				],
				[
					'name'          => __( 'main mapping folder', 'immonex-openimmo2wp' ),
					'path'          => apply_filters( "{$this->prefix}main_mapping_dir", '' ),
					'copy_htaccess' => false,
				],
			]
		);

		foreach ( $required as $folder ) {
			if ( ! trim( $folder['path'] ) ) {
				$failed[] = $folder;
				continue;
			}

			if ( ! is_dir( $folder['path'] ) ) {
				$created = wp_mkdir_p( $folder['path'] );

				if ( $created ) {
					$this->utils['wp_fs']->chmod( $folder['path'], 0775, false );
				} else {
					$failed[] = wp_sprintf( '%s (%s)', $folder['name'], $folder['path'] );
				}
			}

			if ( file_exists( trailingslashit( $folder['path'] ) . 'index.php' ) ) {
				$this->utils['wp_fs']->delete( trailingslashit( $folder['path'] ) . 'index.php', true );
			}
			if ( ! file_exists( trailingslashit( $folder['path'] ) . 'index.html' ) ) {
				$this->utils['wp_fs']->copy( trailingslashit( $this->data['plugin_fs_dir'] ) . 'assets/index.html', trailingslashit( $folder['path'] ) . 'index.html', true );
			}

			if ( $folder['copy_htaccess'] && ! file_exists( trailingslashit( $folder['path'] ) . '.htaccess' ) ) {
				$this->utils['wp_fs']->copy( trailingslashit( $this->data['plugin_fs_dir'] ) . 'assets/htaccess', trailingslashit( $folder['path'] ) . '.htaccess', true );
			}
		}

		return [
			'success'   => empty( $failed ),
			'error_msg' => ! empty( $failed ) ?
				wp_sprintf(
					__( 'The following required directories could not be created: %s', 'immonex-openimmo2wp' ),
					implode( ', ', $failed )
				) :
				'',
		];
	} // create_folders

	/**
	 * Copy mapping files from the plugin folder to the main mapping folder.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed[] $plugin_options Plugin options.
	 *
	 * @return mixed[] Result, optional notice, new mapping option value and message on error.
	 */
	public function copy_mapping_files( $plugin_options ) {
		$org_mapping_dir      = trailingslashit( $this->data['plugin_fs_dir'] ) . apply_filters( "{$this->prefix}mapping_folder_name", '' );
		$main_mapping_dir     = trailingslashit( apply_filters( "{$this->prefix}main_mapping_dir", '' ) );
		$org_mapping_files    = $this->utils['local_fs']->scan_dir( $org_mapping_dir, [ 'file_extensions' => [ 'csv' ] ] );
		$notice			      = '';
		$mapping_option_value = '';
		$errors               = [];
		$copy_error           = false;

		if ( empty( $org_mapping_files ) ) {
			return [
				'success'        => false,
				'notice'         => $notice,
				'mapping_option' => $mapping_option_value,
				'error_msg'      => __( 'No mapping files available!', 'immonex-openimmo2wp' ),
			];
		}

		foreach ( $org_mapping_files as $org_file ) {
			$new_mapping_file = $main_mapping_dir . $org_file->getFilename();

			if (
				file_exists( $new_mapping_file ) &&
				$org_file->getSize() !== filesize( $new_mapping_file )
			) {
				 /**
				  * Mapping file exists and filesize differs: Create a backup.
				  */
				$backup_file = $main_mapping_dir . date_i18n( 'Y-m-d_H_i_' ) . $org_file->getFilename();

				if ( $this->utils['wp_fs']->move( $new_mapping_file, $backup_file, true ) ) {
					if (
						basename( $new_mapping_file ) === $plugin_options['mapping_file']
						&& ! apply_filters( "{$this->prefix}suppress_deferred_admin_notices", false )
					) {
						/**
						 * The currently selected mapping file has been updated, the related
						 * option value should be updated.
						 */
						$mapping_option_value = basename( $backup_file );

						$notice = wp_sprintf(
							__( 'The mapping file <strong>%s</strong> has been updated, a backup of the previous file has been created (<strong>%s</strong>).', 'immonex-openimmo2wp' ),
							basename( $new_mapping_file ),
							basename( $backup_file )
						);
					}
				} else {
					$errors[] = wp_sprintf( __( "The mapping file backup could not be created: %s", 'immonex-openimmo2wp' ), basename( $backup_file ) );
				}
			}

			if ( ! $copy_error && ! $this->utils['wp_fs']->copy( (string) $org_file, $new_mapping_file, true ) ) {
				$errors[]   = __( 'The mapping files could not be copied/updated.', 'immonex-openimmo2wp' );
				$copy_error = true;
			}
		}

		return [
			'success'              => empty( $errors ),
			'notice'               => $notice,
			'mapping_option_value' => $mapping_option_value,
			'error_msg'            => ! empty( $errors ) ?
				wp_sprintf(
					__( 'The following errors occured when copying/backing up the mapping files: %s', 'immonex-openimmo2wp' ),
					implode( ', ', $errors )
				) :
				'',
		];
	} // copy_mapping_files

} // class Install
