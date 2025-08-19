<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * Realia-specific processing (plugin version).
 */
class Realia_Plugin extends Theme_Base {

	public
		$theme_class_slug = 'realia_plugin';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 1.9
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->temp = array(
			'updated_property_ids' => array(),
			'property_floor_plans' => array(
				'filenames' => array(),
				'ids' => array()
			),
			'current_agency' => array(),
			'main_image_cnt' => array(),
			'gallery_images' => array()
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		$theme = wp_get_theme();
		$theme_version = $theme->parent() ? $theme->parent()->Version : $theme->Version;

		if ( in_array( 'Realia', array( $theme->name, $theme_parent ) ) && version_compare( $theme_version, '4', '>=' ) ) {
			$this->override_widget_theme_name = 'realia4';
		} elseif ( in_array( 'Preston', array( $theme->name, $theme_parent ) ) ) {
			$this->override_widget_theme_name = 'preston';
		}

		// DEPRECATED
		add_filter( 'immonex_oi2wp_xml_data_before_import', array( $this, 'get_current_agency' ) );
		if ( $this->theme_options['consider_agency_on_deletion'] ) {
			add_filter( 'immonex_oi2wp_full_import_properties_to_delete', array( $this, 'filter_properties_to_delete' ) );
		}

		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_description_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_grouped_post_meta', array( $this, 'add_custom_meta_details' ), 10, 3 );
		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );

		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 15, 2 );

		if ( $this->theme_options['user_listing_quotas'] ) {
			add_filter( 'immonex_oi2wp_property_xml_before_import', array( $this, 'check_listing_quota' ) );
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

		$user = $this->get_agent_user( $immobilie, array(), false );

		if ( $user && method_exists( 'Realia_Packages', 'get_remaining_properties_count_for_user' ) ) {
			$existing_properties = $this->plugin->get_property_by_openimmo_obid( (string) $immobilie->verwaltung_techn->openimmo_obid, true );
			if ( count( $existing_properties ) > 0 ) {
				// Property to be updated found, ignore quota.
				$this->temp['updated_property_ids'][] = $existing_properties[0]->ID;
				$this->save_temp_theme_data();
				return $immobilie;
			}

			$listings_available = \Realia_Packages::get_remaining_properties_count_for_user( $user->ID );
			if ( $listings_available == 0 ) {
				// Property quota for this user exceeded: skip property.
				$this->plugin->log->add( __( 'Maximum number of properties reached or package not active, skipping property.', 'immonex-openimmo2wp' ), 'info' );
				return false;
			}
		} elseif ( $user ) {
			// Theme quota check function not available: skip property.
			$this->plugin->log->add( __( "Check function missing, current user quota can't be determined. Skipping property.", 'immonex-openimmo2wp' ), 'error' );
			return false;
		}

		return $immobilie;
	} // check_listing_quota

	/**
	 * DEPRECATED: Get the agency whose properties are currently being processed.
	 *
	 * @since 1.9
	 *
	 * @param SimpleXMLElement $xml_data The whole XML tree of the current import file.
	 *
	 * @return SimpleXMLElement Unchanged XML data.
	 */
	public function get_current_agency( $xml_data ) {
		if ( isset( $xml_data->anbieter->firma ) ) {
			$this->temp['current_agency'] = array(
				'name' => (string) $xml_data->anbieter->firma,
				'id' => $this->_get_agency_id( (string) $xml_data->anbieter->firma )
			);
		} else {
			$this->temp['current_agency'] = array();
		}
		$this->save_temp_theme_data();

		return $xml_data;
	} // get_current_agency

	/**
	 * DEPRECATED: Filter properties to delete (before full imports) that dont't belong to
	 * the current agency.
	 *
	 * @since 1.9
	 *
	 * @param array $properties Array of property SimpleXML elements.
	 *
	 * @return array Array of filtered properties.
	 */
	public function filter_properties_to_delete( $properties ) {
		if ( empty( $this->temp['current_agency']['id'] ) ) {
			// Agency ID of current import file could not be determined: DON'T delete ANY property.
			$this->plugin->log->add( __( 'Agency could not be determined - no properties are being deleted.', 'immonex-openimmo2wp' ), 'info' );
			return array();
		}

		if ( is_array( $properties ) && count( $properties ) > 0 ) {
			foreach ( $properties as $i => $property ) {
				$property_agencies = array();

				$property_agents = get_post_meta( $property->ID, 'property_agents', true );
				if ( count( $property_agents ) > 0 ) {
					foreach ( $property_agents as $agent_id ) {
						$agent_agencies = get_post_meta( $agent_id, 'agent_agencies', true );
						if ( $agent_agencies && count( $agent_agencies ) > 0 ) $property_agencies = array_unique( array_merge( $property_agencies, $agent_agencies ) );
					}
				};

				if ( ! in_array( $this->temp['current_agency']['id'], $property_agencies ) ) {
					// Property does not belong to current agency, don't delete it.
					unset( $properties[$i] );
					$this->plugin->log->add( wp_sprintf( __( 'Skipping property: %s (ID %s) - not related to current agency', 'immonex-openimmo2wp' ), $property->post_title, $property->ID ), 'info' );
				}
			}

			$properties = array_values( $properties );
		}

		return $properties;
	} // filter_properties_to_delete

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 *
	 * @since 1.9
	 *
	 * @param array $post_data Current post data.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return array (Possibly) modified property data to store as post record.
	 */
	public function add_description_content( $post_data, $immobilie ) {
		if ( trim( $this->theme_options['add_description_content'] ) ) {
			$post_data['post_content'] .= "\n" . $this->theme_options['add_description_content'];
		}

		return $post_data;
	} // add_description_content

	/**
	 * Convert and save theme custom data.
	 *
	 * @since 1.9
	 *
	 * @param array $grouped_meta_data Associative array of a meta data group.
	 * @param int $post_id ID of the related property post record.
	 * @param string $meta_key Meta key under which the serialized group array will be stored.
	 */
	public function add_custom_meta_details( $grouped_meta_data, $post_id, $meta_key ) {
		if ( 'property_public_facilities' !== $meta_key ) return $grouped_meta_data;

		$public_facilities = array();

		if ( count( $grouped_meta_data ) > 0 ) {
			foreach ( $grouped_meta_data as $key => $data ) {
				$public_facilities[] = array(
					'property_public_facilities_key' => $key,
					'property_public_facilities_value' => $data['value']
				);
			}

			add_post_meta( $post_id, 'property_public_facilities_group', $public_facilities, true );
		}

		// DON'T save the original data.
		return false;
	} // add_custom_meta_details

	/**
	 * Check attachment type and perform related processing steps.
	 *
	 * @since 1.9
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

			if ( $this->plugin->string_utils->is_video_url( $url ) || 'FILMLINK' === (string) $attachment['gruppe'] ) {
				// Attachment is an URL of an external video, save it as custom field.
				add_post_meta( $post_id, 'property_video', $url, true );

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
		} elseif ( 'TITELBILD' === (string) $attachment['gruppe'] ) {
			// This is the main property image, remember its array index.
			$this->temp['main_image_cnt'][$post_id] = isset( $this->temp['gallery_images'][$post_id] ) ? count( $this->temp['gallery_images'][$post_id] ) : 0;
			$this->save_temp_theme_data();
		}

		return $attachment;
	} // check_attachment

	/**
	 * Save the property address or coordinates (post meta) for geocoding.
	 *
	 * @since 1.9
	 *
	 * @param string $post_id Property ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie );
		$geo_coordinates = false;
		$address_publishing_status_logged = false;

		if ( $geodata['publishing_approved'] && $geodata['lat'] && $geodata['lng'] ) {
			$geo_coordinates = array(
				'lat' => $geodata['lat'],
				'lng' => $geodata['lng']
			);
		} elseif (
			$this->plugin->plugin_options['geo_always_use_coordinates'] &&
			$geodata['lat'] && $geodata['lng']
		) {
			$geo_coordinates = array(
				'lat' => $geodata['lat'],
				'lng' => $geodata['lng']
			);
			$this->plugin->log->add( __( 'Property address NOT approved for publishing, but usable coordinates available and publishing permitted.', 'immonex-openimmo2wp' ), 'debug' );
			$address_publishing_status_logged = true;
		} else {
			$this->plugin->log->add( wp_sprintf(
				__( 'Property address (Geocoding): %s (ISO2: %s)', 'immonex-openimmo2wp' ),
				$geodata['address_geocode'],
				$geodata['country_code_iso2'] ? $geodata['country_code_iso2'] : __( 'none', 'immonex-openimmo2wp' )
			), 'debug' );
			$geo_coordinates = $this->geocode( $geodata['address_geocode'], $geodata['publishing_approved'] ? false : true, $geodata['country_code_iso2'], $post_id );
			if ( false === $geo_coordinates ) {
				$geocoding_failed = true;
			} else {
				$this->plugin->log->add( wp_sprintf(
					__( 'Geocoding result%s: %s%s', 'immonex-openimmo2wp' ),
					! empty( $geo_coordinates['provider'] ) ? ' (' . $geo_coordinates['provider'] . ')' : '',
					$geo_coordinates['lat'] . ', ' . $geo_coordinates['lng'],
					$geo_coordinates['from_cache'] ? ' ' . __( '(cache)', 'immonex-openimmo2wp' ) : ''
				), 'debug' );
			}

			$this->plugin->log->add( wp_sprintf( __( 'Property address/city (Geocoding): %s', 'immonex-openimmo2wp' ), $geodata['address_geocode'] ), 'debug' );
		}

		if ( $geodata['publishing_approved'] ) {
			add_post_meta( $post_id, 'property_address', $geodata['street'], true );
		} elseif ( ! $address_publishing_status_logged ) {
			$this->plugin->log->add( __( 'Property address NOT approved for publishing', 'immonex-openimmo2wp' ), 'debug' );
		}

		if ( $geo_coordinates && is_array( $geo_coordinates ) ) {
			add_post_meta( $post_id, 'property_map_location_latitude', $geo_coordinates['lat'], true );
			add_post_meta( $post_id, 'property_map_location_longitude', $geo_coordinates['lng'], true );
			add_post_meta( $post_id, 'property_map_location', array( 'latitude' => $geo_coordinates['lat'], 'longitude' => $geo_coordinates['lng'] ), true );
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), $geo_coordinates['lat'] . ', ' . $geo_coordinates['lng'] ), 'debug' );
		} elseif ( isset( $geocoding_failed ) && $geocoding_failed ) {
			$geocoding_status = $this->get_geocoding_status( $geodata['address_geocode'], $geodata['country_code_iso2'] );
			$this->plugin->log->add( wp_sprintf( __( 'Geocoding failed (%s)', 'immonex-openimmo2wp' ), $geocoding_status ? $geocoding_status : __( 'unknown reason', 'immonex-openimmo2wp' ) ), 'debug' );
		}
	} // save_property_location

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 1.9
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
			$floor_plans = $this->get_extended_filenames( $this->temp['property_floor_plans']['filenames'][$p->post_parent] );

			if ( ! empty( $floor_plans ) && in_array( $filename, $floor_plans, true ) ) {
				// Remember floor plan attachment ID, exclude from gallery.
				if ( ! isset( $this->temp['property_floor_plans']['ids'][$p->post_parent] ) ) {
					$this->temp['property_floor_plans']['ids'][$p->post_parent] = array();
				}
				$this->temp['property_floor_plans']['ids'][$p->post_parent][] = $att_id;
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
	 * Save property gallery image as serialized array.
	 *
	 * @since 1.9
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['gallery_images'][$post_id] ) ) {
			$this->temp['gallery_images'][$post_id] = $this->check_attachment_ids( $this->temp['gallery_images'][$post_id] );

			$property_gallery = array();

			foreach ( $this->temp['gallery_images'][$post_id] as $i => $att_id ) {
				$img_src = wp_get_attachment_image_src( $att_id, 'large' ) ;
				$property_gallery[$att_id] = $img_src[0];

				if (
					(
						isset( $this->temp['main_image_cnt'][$post_id] ) &&
						$this->temp['main_image_cnt'][$post_id] == $i
					) || (
						! isset( $this->temp['main_image_cnt'][$post_id] ) &&
						0 == $i
					)
				) {
					// Set main or first image as slider image.
					add_post_meta( $post_id, 'property_slider_image_id', $att_id, true );
					add_post_meta( $post_id, 'property_slider_image', wp_get_attachment_url( $att_id ), true );
				}
			}

			add_post_meta( $post_id, 'property_gallery', $property_gallery, true );
			unset( $this->temp['gallery_images'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['property_floor_plans']['ids'][$post_id] ) ) {
			// Save floor plan data.
			$this->temp['property_floor_plans']['ids'][$post_id] = $this->check_attachment_ids( $this->temp['property_floor_plans']['ids'][$post_id] );

			$floor_plans = array();

			foreach ( $this->temp['property_floor_plans']['ids'][$post_id] as $i => $att_id ) {
				$floor_plans[$att_id] = wp_get_attachment_url( $att_id );
			}

			add_post_meta( $post_id, 'property_plans', $floor_plans, true );
		}
	} // save_attachment_data

	/**
	 * Try to determine/save the property agent.
	 *
	 * @since 1.9
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

		if ( $name_contact ) {
			$this->plugin->log->add( wp_sprintf( __( 'Contact person (Agent): %s', 'immonex-openimmo2wp' ), $name_contact ), 'debug' );
			add_post_meta( $post_id, 'property_contact_name', $name_contact, true );
		}

		if ( $agent_data['phone'] ) add_post_meta( $post_id, 'property_contact_phone', $agent_data['phone'], true );

		$user = $this->get_agent_user( $immobilie, array(), true, $author_id );

		if ( $user ) {
			if ( $user->ID !== $author_id ) {
				// Save new author.
				$this->update_post_author( $post->ID, $user->ID );
			}

			$user_agent_id = get_user_meta( $user->ID, 'user_agent_object', true );
			// Save related agent ID if given.
			if ( $user_agent_id ) {
				$this->plugin->log->add( wp_sprintf( __( 'Assigning agent linked to user (ID: %d).', 'immonex-openimmo2wp' ), $user_agent_id ), 'debug' );
				add_post_meta( $post_id, 'property_agents', array( $user_agent_id ), true );
				return;
			}
		}

		$agent = $this->get_agent( $immobilie, 'agent', array( 'email' => 'agent_email' ), array(), true );
		if ( $agent ) {
			// Save agent ID as array.
			add_post_meta( $post_id, 'property_agents', array( $agent->ID ), true );
		}
	} // save_agent

	/**
	 * Try to determine the ID of the currently processed agency.
	 *
	 * @since 1.9
	 *
	 * @param string $post_id Property post ID.
	 *
	 * @return int|boolean Agency post ID or false if not existing.
	 */
	private function _get_agency_id( $name ) {
		$agency_id = false;

		$agencies = get_posts( array(
			'post_type' => 'agency'
		) );

		if ( count( $agencies ) > 0 ) {
			$similarity = 0;
			foreach ( $agencies as $agency ) {
				// Loop through all agency posts...
				similar_text( $name, $agency->post_title, $similarity );
				if ( $similarity >= 85 ) {
					/// ...and assign the current agency if name similarity
					// is greater or equal than 85 %...
					$agency_id = $agency->ID;
					break;
				}
			}
		}

		return $agency_id;
	} // _get_agency_id

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 1.9
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
	 * @since 1.9
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
					'description' => __( 'Check number of available listings per user during import.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_consider_agency_on_deletion', // DEPRECATED
				'type' => 'checkbox',
				'label' => __( 'Consider agency on property deletion (full imports)', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'If set, only properties that belong to the agency named in the import XML file will be deleted before <strong>full imports</strong>.', 'immonex-openimmo2wp' ) .
						'<br>' . __( '<strong>Heads up!</strong> This option is <strong>deprecated</strong> and will be removed in the near future, please use considerably more reliable <strong>user-related import folders</strong> when dealing with multiple importing agencies.', 'immonex-openimmo2wp' )
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

} // class Realia_Plugin
