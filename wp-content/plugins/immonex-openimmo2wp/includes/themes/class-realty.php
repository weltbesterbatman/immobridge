<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * Realty-specific processing.
 */
class Realty extends Theme_Base {

	public
		$theme_class_slug = 'realty';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 1.4
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->initial_widgets = array(
			'sidebar_property' => array(
				'immonex_user_defined_properties_widget' => array(
					array(
						'title' => __( 'Details', 'immonex-openimmo2wp' ),
						'display_mode' => 'exclude',
						'display_groups' => 'epass',
						'type' => 'name_value',
						'item_div_classes' => ''
					),
					array(
						'title' => __( 'Energy Pass', 'immonex-openimmo2wp' ),
						'display_mode' => 'include',
						'display_groups' => 'epass',
						'type' => 'name_value',
						'item_div_classes' => ''
					)
				)
			)
		);

		$this->temp = array(
			'updated_property_ids' => array(),
			'property_floor_plans' => array(
				'filenames' => array(),
				'ids' => array()
			),
			'gallery_images' => array(),
			'property_file_attachments' => array()
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_add_property_custom_field', array( $this, 'handle_custom_field' ), 10, 3 );
		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_post_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );

		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_additional_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 15, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_acf_taxonomy_relations' ), 20, 2 );

		if ( $this->theme_options['user_listing_quotas'] ) {
			add_filter( 'immonex_oi2wp_property_xml_before_import', array( $this, 'check_listing_quota' ) );
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'decrease_available_listings' ), 20, 2 );
			add_action( 'before_delete_post', array( $this, 'increase_available_listings' ) );
		}
	} // __construct

	/**
	 * Check for available property related users and their package quotas.
	 *
	 * @since 2.3
	 *
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return SimpleXMLElement|bool Original property object or false if over quota.
	 */
	public function check_listing_quota( $immobilie ) {
		// Property to be deleted, ignore quota.
		if ( 'DELETE' === strtoupper( $immobilie->verwaltung_techn->aktion['aktionart'] ) ) return $immobilie;

		$user = $this->get_agent_user( $immobilie, array( 'role' => 'agent' ), false );

		if ( $user ) {
			$existing_properties = $this->plugin->get_property_by_openimmo_obid( (string) $immobilie->verwaltung_techn->openimmo_obid, true );
			if ( count( $existing_properties ) > 0 ) {
				// Property to be updated found, ignore quota.
				$this->temp['updated_property_ids'][] = $existing_properties[0]->ID;
				$this->save_temp_theme_data();
				return $immobilie;
			}

			$package_id = get_user_meta( $user->ID, 'subscribed_package', true );
			$listings_available = get_user_meta( $user->ID, 'subscribed_listing_remaining', true );

			if ( $package_id && $listings_available == 0 ) {
				// Property quota for this user exceeded: skip property.
				$this->plugin->log->add( __( 'Maximum number of properties reached, skipping property.', 'immonex-openimmo2wp' ), 'info' );
				return false;
			}
		}

		return $immobilie;
	} // check_listing_quota

	/**
	 * Convert and save theme custom data.
	 *
	 * @since 1.4
	 *
	 * @param array $data Custom field data.
	 * @param SimpleXMLElement $immobilie XML node of the related property object.
	 * @param int $post_id ID of the related property post record.
	 *
	 * @return mixed[] Unchanged meta data.
	 */
	public function handle_custom_field( $data, $immobilie, $post_id ) {
		// Save an Advanced Custom Fields relation if a corresponding field exists.
		$this->_save_acf_relation( $post_id, $data['meta_key'] );

		return $data;
	} // add_custom_meta_details

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 * Shorten excerpt if needed.
	 *
	 * @since 1.4
	 *
	 * @param array $post_data Current post data.
	 * @param SimpleXMLElement $immobilie XML node of the related property object.
	 *
	 * @return array (Possibly) modified property data to store as post record.
	 */
	public function add_post_content( $post_data, $immobilie ) {
		if ( trim( $this->theme_options['add_description_content'] ) ) {
			$post_data['post_content'] .= "\n" . $this->plugin->multilang_get_string_translation( $this->theme_options['add_description_content'] );
		}

		if ( isset( $post_data['post_excerpt'] ) && $post_data['post_excerpt'] )
			$excerpt = $post_data['post_excerpt'];
		else
			$excerpt = $post_data['post_content'];

		$post_data['post_excerpt'] = $this->plugin->string_utils->get_excerpt( $excerpt, 120, '...' );

		return $post_data;
	} // add_post_content

	/**
	 * Check attachment type and perform related processing steps.
	 *
	 * @since 1.4
	 *
	 * @param SimpleXMLElement $attachment Attachment XML node.
	 * @param int $post_id ID of the related property post record.
	 */
	public function check_attachment( $attachment, $post_id ) {
		if (
			in_array( (string) $attachment['gruppe'], array( 'FILMLINK', 'LINKS' ) ) &&
			( isset( $attachment->daten->pfad ) || isset( $attachment->anhangtitel ) )
		) {
			$url = isset( $attachment->daten->pfad ) ? (string) $attachment->daten->pfad : (string) $attachment->anhangtitel;
			if ( 'http' !== substr( $url, 0, 4 ) ) return $attachment;

			$video = $this->plugin->string_utils->is_video_url( $url );

			if ( $video ) {
				// Attachment is an URL of an external video: save video type and ID as post meta.
				add_post_meta( $post_id, 'estate_property_video_provider', $video['type'], true );
				$this->_save_acf_relation( $post_id, 'estate_property_video_provider' );
				add_post_meta( $post_id, 'estate_property_video_id', $video['id'], true );
				$this->_save_acf_relation( $post_id, 'estate_property_video_id' );

				// DON'T import this attachment.
				return false;
			}
		} elseif ( in_array( (string) $attachment['gruppe'], array( 'GRUNDRISS' ) ) ) { // DEPRECATED: 'KARTEN_LAGEPLAN'
			$format = (string) $attachment->format;
			if ( false !== strpos( $format, '/' ) ) {
				// Split file format declaration.
				$temp = explode( '/', $format );
				$format = $temp[1];
			}

			if ( in_array( strtoupper( $format ), array( 'JPG', 'JPEG', 'GIF', 'PNG' ) ) ) {
				// Attachment ist a floor plan image: remember its filename for later processing.
				if ( ! isset( $this->temp['property_floor_plans']['filenames'][$post_id] ) ) {
					$this->temp['property_floor_plans']['filenames'][$post_id] = array();
				}

				$this->temp['property_floor_plans']['filenames'][$post_id][] = pathinfo( $attachment->daten->pfad, PATHINFO_BASENAME );
				$this->temp['property_floor_plans']['filenames'][$post_id] = array_unique( $this->temp['property_floor_plans']['filenames'][$post_id] );
				$this->save_temp_theme_data();
			}
		}

		return $attachment;
	} // check_attachment

	/**
	 * Save the property address and/or coordinates (post meta) for geocoding.
	 *
	 * @since 1.4
	 *
	 * @param string $post_id Property ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie );
		$geo_coordinates = false;
		$address_publishing_status_logged = false;

		$address = $geodata['address_geocode'];
		$google_maps = array( 'address' => $address );

		if ( $geodata['publishing_approved'] && $geodata['lat'] && $geodata['lng'] ) {
			$geo_coordinates = $geodata['lat'] . ',' . $geodata['lng'];
			$google_maps['lat'] = $geodata['lat'];
			$google_maps['lng'] = $geodata['lng'];
		} elseif (
			$this->plugin->plugin_options['geo_always_use_coordinates'] &&
			$geodata['lat'] && $geodata['lng']
		) {
			$geo_coordinates = $geodata['lat'] . ',' . $geodata['lng'];
			$google_maps['lat'] = $geodata['lat'];
			$google_maps['lng'] = $geodata['lng'];
			$this->plugin->log->add( __( 'Property address NOT approved for publishing, but usable coordinates available and publishing permitted.', 'immonex-openimmo2wp' ), 'debug' );
			$address_publishing_status_logged = true;
		} else {
			$this->plugin->log->add( wp_sprintf(
				__( 'Property address (Geocoding): %s (ISO2: %s)', 'immonex-openimmo2wp' ),
				$geodata['address_geocode'],
				$geodata['country_code_iso2'] ? $geodata['country_code_iso2'] : __( 'none', 'immonex-openimmo2wp' )
			), 'debug' );
			$geo = $this->geocode( $address, $geodata['publishing_approved'] ? false : true, $geodata['country_code_iso2'], $post_id );
			if ( $geo ) {
				$geo_coordinates = $geo['lat'] . ',' . $geo['lng'];
				$google_maps['lat'] = $geo['lat'];
				$google_maps['lng'] = $geo['lng'];

				$this->plugin->log->add( wp_sprintf(
					__( 'Geocoding result%s: %s%s', 'immonex-openimmo2wp' ),
					! empty( $geo['provider'] ) ? ' (' . $geo['provider'] . ')' : '',
					$geo['lat'] . ', ' . $geo['lng'],
					$geo['from_cache'] ? ' ' . __( '(cache)', 'immonex-openimmo2wp' ) : ''
				), 'debug' );
			} else {
				$geocoding_status = $this->get_geocoding_status( $address, $geodata['country_code_iso2'] );
				$this->plugin->log->add( wp_sprintf( __( 'Geocoding failed (%s)', 'immonex-openimmo2wp' ), $geocoding_status ? $geocoding_status : __( 'unknown reason', 'immonex-openimmo2wp' ) ), 'debug' );
			}
		}

		if ( ! $geodata['publishing_approved'] && ! $address_publishing_status_logged ) {
			$this->plugin->log->add( __( 'Property address NOT approved for publishing', 'immonex-openimmo2wp' ), 'debug' );
		}

		if ( $address ) add_post_meta( $post_id, 'estate_property_address', $address, true );

		if ( $geo_coordinates ) {
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), $geo_coordinates ), 'debug' );

			add_post_meta( $post_id, 'estate_property_location', $geo_coordinates, true );
			$this->_save_acf_relation( $post_id, 'estate_property_location' );

			add_post_meta( $post_id, 'estate_property_google_maps', $google_maps, true );
			$this->_save_acf_relation( $post_id, 'estate_property_google_maps' );
		}
	} // save_property_location

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 1.4
	 *
	 * @param string $att_id Attachment ID.
	 * @param array $valid_image_formats Array of valid image file format suffixes.
	 * @param array $valid_misc_formats Array of valid non-image file format suffixes.
	 */
	public function add_attachment_data( $att_id, $valid_image_formats, $valid_misc_formats ) {
		$p = get_post( $att_id );

		if ( $p ) {
			$fileinfo = pathinfo( get_attached_file( $att_id ) );
			if ( ! isset( $fileinfo['extension'] ) ) return;

			// Remove counter etc. from filename for comparison.
			$filename = $this->get_plain_basename( $fileinfo['filename'] );

			// Possibly extend filename arrays by sanitized versions.
			$floor_plans = ! empty( $this->temp['property_floor_plans']['filenames'][$p->post_parent] ) ?
				$this->get_extended_filenames( $this->temp['property_floor_plans']['filenames'][$p->post_parent] ) :
				array();

			if ( ! empty( $floor_plans ) && in_array( $filename, $floor_plans, true ) ) {
				// Remember floor plan attachment ID, exclude from gallery.
				if ( ! isset( $this->temp['property_floor_plans']['ids'][$p->post_parent] ) ) {
					$this->temp['property_floor_plans']['ids'][$p->post_parent] = array();
				}
				$this->temp['property_floor_plans']['ids'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			} elseif ( in_array( strtoupper( $fileinfo['extension'] ), $valid_misc_formats ) ) {
				// File attachment, remember its ID.
				if ( ! isset( $this->temp['property_file_attachments'][$p->post_parent] ) ) {
					$this->temp['property_file_attachments'][$p->post_parent] = array();
				}
				$this->temp['property_file_attachments'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			} elseif ( in_array( strtoupper( $fileinfo['extension'] ), $valid_image_formats ) ) {
				if ( ! isset( $this->temp['gallery_images'][$p->post_parent] ) ) {
					$this->temp['gallery_images'][$p->post_parent] = array();
				}
				$this->temp['gallery_images'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			}
		}
	} // add_attachment_data

	/**
	 * Save attachment related data.
	 *
	 * @since 1.4
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['gallery_images'][$post_id] )	) {
			// Save property gallery list (attachment IDs as serialized array).
			$this->temp['gallery_images'][$post_id] = $this->check_attachment_ids( $this->temp['gallery_images'][$post_id] );

			add_post_meta( $post_id, 'estate_property_gallery', $this->temp['gallery_images'][$post_id], true );
			$this->_save_acf_relation( $post_id, 'estate_property_gallery' );
			unset( $this->temp['gallery_images'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['property_file_attachments'][$post_id] ) ) {
			$this->temp['property_file_attachments'][$post_id] = $this->check_attachment_ids( $this->temp['property_file_attachments'][$post_id] );

			// Save file attachment data.
			foreach ( $this->temp['property_file_attachments'][$post_id] as $cnt => $att_id ) {
				add_post_meta( $post_id, 'estate_property_attachments_repeater_' . $cnt . '_estate_property_attachment', $att_id, true );
				$acf_id = $this->_get_acf_field_id( 'estate_property_attachment' );
				if ( $acf_id ) $this->_save_acf_relation( $post_id, 'estate_property_attachments_repeater_' . $cnt . '_estate_property_attachment', $acf_id );
			}

			add_post_meta( $post_id, 'estate_property_attachments_repeater', count( $this->temp['property_file_attachments'][$post_id] ), true );
			$this->_save_acf_relation( $post_id, 'estate_property_attachments_repeater' );
		}

		if ( ! empty( $this->temp['property_floor_plans']['ids'][$post_id] ) ) {
			// Save floor plan data.
			$this->temp['property_floor_plans']['ids'][$post_id] = $this->check_attachment_ids( $this->temp['property_floor_plans']['ids'][$post_id] );

			foreach ( $this->temp['property_floor_plans']['ids'][$post_id] as $cnt => $att_id ) {
				$attachment = wp_prepare_attachment_for_js( $att_id );

				add_post_meta( $post_id, 'estate_property_floor_plans_' . $cnt . '_acf_estate_floor_plan_image', $att_id, true );
				$acf_id = $this->_get_acf_field_id( 'acf_estate_floor_plan_image' );
				if ( $acf_id ) $this->_save_acf_relation( $post_id, 'estate_property_floor_plans_' . $cnt . '_acf_estate_floor_plan_image', $acf_id );

				add_post_meta( $post_id, 'estate_property_floor_plans_' . $cnt . '_acf_estate_floor_plan_title', $attachment['title'], true );
				$acf_id = $this->_get_acf_field_id( 'acf_estate_floor_plan_title' );
				if ( $acf_id ) $this->_save_acf_relation( $post_id, 'estate_property_floor_plans_' . $cnt . '_acf_estate_floor_plan_title', $acf_id );
			}

			add_post_meta( $post_id, 'estate_property_floor_plans', count( $this->temp['property_floor_plans']['ids'][$post_id] ), true );
			$this->_save_acf_relation( $post_id, 'estate_property_floor_plans' );
		}
	} // save_attachment_data

	/**
	 * Save additional (default) property meta data.
	 *
	 * @since 1.9
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_additional_data( $post_id, $immobilie ) {
		// Meta key => default value.
		$default_values = array(
			'estate_property_size_unit' => 'm2',
			'estate_property_layout' => 'theme_option_setting',
			'estate_property_contact_information' => 'all',
			'estate_property_featured' => ''
		);

		foreach ( $default_values as $meta_key => $value ) {
			$value = apply_filters( $this->plugin->plugin_prefix . 'realty_' . $meta_key, $value );
			add_post_meta( $post_id, $meta_key, $value, true );
			$this->_save_acf_relation( $post_id, $meta_key );
		}
	} // save_additional_data

	/**
	 * Try to determine/save the property agent.
	 *
	 * @since 1.4
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_agent( $post_id, $immobilie ) {
		$post = get_post( $post_id );
		// ID of author that has been automatically set before (e.g. based on import folder).
		$author_id = $post && $post->post_author ? $post->post_author : false;

		$agent_data = $this->get_agent_data( $immobilie );
		$name_contact = $agent_data['name'];
		$email_contact = $agent_data['email'];

		if ( $name_contact ) $this->plugin->log->add( wp_sprintf( __( 'Contact person (Agent): %s', 'immonex-openimmo2wp' ), $name_contact ), 'debug' );

		$args = array( 'role' => 'agent' );
		$user = $this->get_agent_user( $immobilie, $args, true, $author_id );

		if ( $user ) {
			if ( $user->ID !== $author_id ) {
				// Save new author.
				$this->update_post_author( $post->ID, $user->ID );
			}

			// Save property agent ID as custom field.
			add_post_meta( $post_id, 'estate_property_custom_agent', $user->ID, true );
			$this->_save_acf_relation( $post_id, 'estate_property_custom_agent' );
		}
	} // save_agent

	/**
	 * Save arrays of taxonomy term IDs as meta data for related ACF fields.
	 *
	 * @since 1.4
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_acf_taxonomy_relations( $post_id, $immobilie ) {
		$taxonomies = array(
			'property-location',
			'property-type',
			'property-status',
			'property-features'
		);

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_post_terms( $post_id, $taxonomy );
			if ( is_array( $terms ) && count( $terms ) > 0 ) {
				$term_ids = array();

				foreach ( $terms as $term ) {
					$term_ids[] = $term->term_id;
				}

				add_post_meta( $post_id, 'acf-' . $taxonomy, $term_ids, true );
				$this->_save_acf_relation( $post_id, 'acf-' . $taxonomy );
			}
		}
	} // save_acf_taxonomy_relations

	/**
	 * Decrease number of available properties for user.
	 *
	 * @since 2.3
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function decrease_available_listings( $post_id, $immobilie ) {
		// Don't decrease the available listings counter on updated properties.
		if (
			is_array( $this->temp['updated_property_ids'] ) &&
			in_array( $post_id, $this->temp['updated_property_ids'] )
		) return;

		$user_id = get_post_meta( $post_id, 'estate_property_custom_agent', true );

		if ( $user_id ) {
			$package_id = get_user_meta( $user_id, 'subscribed_package', true );

			if ( $package_id ) {
				$listings_available = get_user_meta( $user_id, 'subscribed_listing_remaining', true );
				$featured_listings_available = get_user_meta( $user_id, 'subscribed_featured_listing_remaining', true );

				if ( $listings_available ) {
					// Decrease number of available (normal) listings for user.
					update_user_meta( $user_id, 'subscribed_listing_remaining', $listings_available - 1, $listings_available );
					$this->plugin->log->add( wp_sprintf( __( 'User max. listing count decreased, available listings now: %d', 'immonex-openimmo2wp' ), $listings_available - 1 ), 'debug' );
				}

				if ( -1 !== (int) $featured_listings_available ) {
					$is_featured = get_post_meta( $post_id, 'estate_property_featured', true );
					if ( $is_featured && ! empty( $is_featured ) && $featured_listings_available > 0 ) {
						// Decrease number of available featured listings for user.
						update_user_meta( $user_id, 'subscribed_featured_listing_remaining', $featured_listings_available - 1, $featured_listings_available );
						$this->plugin->log->add( wp_sprintf( __( 'User max. featured listing count decreased, available listings now: %d', 'immonex-openimmo2wp' ), $featured_listings_available - 1 ), 'debug' );
					} else {
						// No more featured listings available, remove featured status.
						delete_post_meta( $post_id, 'estate_property_featured' );
						$this->plugin->log->add( __( 'Maximum number of featured listings reached, featured status removed.', 'immonex-openimmo2wp' ), 'info' );
					}
				}
			}
		}
	} // decrease_available_listings

	/**
	 * Increase number of available properties for user.
	 *
	 * @since 2.3
	 *
	 * @param string $post_id Property post ID.
	 */
	public function increase_available_listings( $post_id ) {
		$property = get_post( $post_id );
		if ( ! $property || $property->post_type !== $this->plugin->property_post_type ) return;

		$user_id = get_post_meta( $post_id, 'estate_property_custom_agent', true );
		if ( $user_id ) {
			$package_id = get_user_meta( $user_id, 'subscribed_package', true );
			if ( $package_id ) {
				$package_max_listings = get_post_meta( $package_id, 'estate_package_allowed_listings', true );
				$package_max_featured_listings = get_post_meta( $package_id, 'estate_package_allowed_featured_listings', true );
			} else return;

			$listings_available = get_user_meta( $user_id, 'subscribed_listing_remaining', true );
			if ( $listings_available < $package_max_listings ) {
				// Increase max. number of available (normal) listings for user.
				update_user_meta( $user_id, 'subscribed_listing_remaining', $listings_available + 1, $listings_available );
			}

			$is_featured = get_post_meta( $post_id, 'estate_property_featured', true );
			if ( $is_featured && ! empty( $is_featured ) ) {
				$featured_listings_available = get_user_meta( $user_id, 'subscribed_featured_listing_remaining', true );
				if ( $featured_listings_available < $package_max_featured_listings ) {
					// Increase max. number of available featured listings for user.
					update_user_meta( $user_id, 'subscribed_featured_listing_remaining', $featured_listings_available + 1, $featured_listings_available );
				}
			}
		}
	} // increase_available_listings

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 1.4
	 *
	 * @param array $sections Original sections array.
	 *
	 * @return array Extended sections array.
	 */
	public function extend_sections( $sections ) {
		$sections['ext_section_' . $this->theme_class_slug . '_general'] = array(
			'tab' => 'ext_tab_' . $this->theme_class_slug
		);

		return $sections;
	} // extend_sections

	/**
	 * Add configuration fields to an options section of the the theme options tab.
	 *
	 * @since 1.4
	 *
	 * @param array $fields Original fields array.
	 *
	 * @return array Extended fields array.
	 */
	public function extend_fields( $fields ) {
		$fields = array_merge( $fields, array(
			array(
				'name' => $this->theme_class_slug . '_user_listing_quotas',
				'type' => 'checkbox',
				'label' => __( 'User Listing Quotas', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Check and update maximum number of available listings per user during import.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_add_description_content',
				'type' => 'textarea',
				'label' => __( 'Additional content for property descriptions', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => wp_sprintf(
						__( 'This content will be appended to the <strong>main description text</strong> of every imported property. This is especially useful for adding <a href="%s" class="immonex-doc-link" target="_blank">widgets by shortcode</a>.', 'immonex-openimmo2wp' ),
						'https://docs.immonex.de/openimmo2wp/#/widgets/per-shortcode'
					)
				)
			)
		) );

		return $fields;
	} // extend_fields

	/**
	 * Save an ACF field relation (post meta).
	 *
	 * @since 1.4
	 * @access private
	 *
	 * @param string $post_id ID of the concerning post.
	 * @param string $field_slug ACF field slug.
	 * @param string $field_slug ACF field ID (optional).
	 */
	private function _save_acf_relation( $post_id, $field_slug, $field_id = false ) {
		if ( ! $field_id ) $field_id = $this->_get_acf_field_id( $field_slug );

		if ( $field_id ) add_post_meta( $post_id, '_' . $field_slug, $field_id );
	} // _save_acf_relation

	/**
	 * Get the ID of an ACF field.
	 *
	 * @since 1.4
	 * @access private
	 *
	 * @param string $field_slug ACF field slug.
	 *
	 * @return string|bool ACF field ID or false if not found.
	 */
	private function _get_acf_field_id( $field_slug ) {
		$field = $this->_get_acf_field( $field_slug );

		if ( $field ) return $field->post_name;
		else return false;
	} // _get_acf_field_id

	/**
	 * Get all ACF field data.
	 *
	 * @since 1.4
	 * @access private
	 *
	 * @param string $field_slug ACF field slug.
	 *
	 * @return array|bool ACF field data or false if not found.
	 */
	private function _get_acf_field( $field_slug ) {
		global $wpdb;

		$posts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $wpdb->posts WHERE post_excerpt = %s", $field_slug ) );

		if ( 1 === count( $posts ) ) return $posts[0];
		else return false;
	} // _get_acf_field

} // class Realty
