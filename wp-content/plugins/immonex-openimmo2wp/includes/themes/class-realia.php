<?php
/**
 * Realia-specific processing.
 */
class Realia extends Theme_Base {

	const
		MAX_LENGTH_OPTIONAL_TITLE = 19;

	public
		$theme_class_slug = 'realia';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 1.0
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->temp = array(
			'current_agency' => array(),
			'post_images' => array()
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_xml_data_before_import', array( $this, 'get_current_agency' ) );
		if ( $this->theme_options['consider_agency_on_deletion'] ) {
			add_filter( 'immonex_oi2wp_full_import_properties_to_delete', array( $this, 'filter_properties_to_delete' ) );
		}

		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_description_content' ), 10, 2 );
		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_shortened_title' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 15, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_property_meta_field_list' ), 20, 2 );
	} // __construct

	/**
	 * DEPRECATED: Get the agency whose properties are currently being processed.
	 *
	 * @since 1.0
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
	 * @since 1.0
	 *
	 * @param array $properties Array of property SimpleXML elements.
	 *
	 * @return array Array of filtered properties.
	 */
	public function filter_properties_to_delete( $properties ) {
		if ( ! isset( $this->temp['current_agency']['id'] ) || ! $this->temp['current_agency']['id'] ) {
			// Agency ID of current import file could not be determined: DON'T delete ANY property.
			$this->plugin->log->add( __( 'Agency could not be determined - no properties are being deleted.', 'immonex-openimmo2wp' ), 'info' );
			return array();
		}

		if ( is_array( $properties ) && count( $properties ) > 0 ) {
			foreach ( $properties as $i => $property ) {
				$property_agencies = get_post_meta( $property->ID, '_property_agencies' );
				if ( is_array( $property_agencies ) && count( $property_agencies ) > 0 ) {
					// Flatten deserialized property agencies array.
					$property_agencies_temp = array();
					foreach ( $property_agencies as $agency ) {
						$property_agencies_temp[] = $agency[0];
					}
					$property_agencies = $property_agencies_temp;
				} else {
					$property_agencies = array();
				}

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
	 * @since 1.0
	 *
	 * @param array $post_data Current post data.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return array (Possibly) modified property data to store as post record.
	 */
	public function add_description_content( $post_data, $immobilie ) {
		if ( trim( $this->theme_options['add_description_content'] ) ) {
			$post_data['post_content'] .= "\n" . $this->plugin->multilang_get_string_translation( $this->theme_options['add_description_content'] );
		}

		return $post_data;
	} // add_description_content

	/**
	 * Save the property address or coordinates (post meta) for geocoding.
	 *
	 * @since 1.0
	 *
	 * @param string $post_id Property ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie );
		$geo_coordinates = false;

		if ( $geodata['publishing_approved'] ) {
			if ( $geodata['lat'] && $geodata['lng'] ) {
				$geo_coordinates = array(
					'lat' => $geodata['lat'],
					'lng' => $geodata['lng']
				);
			} else {
				$this->plugin->log->add( wp_sprintf(
					__( 'Property address (Geocoding): %s (ISO2: %s)', 'immonex-openimmo2wp' ),
					$geodata['address_geocode'],
					$geodata['country_code_iso2'] ? $geodata['country_code_iso2'] : __( 'none', 'immonex-openimmo2wp' )
				), 'debug' );
				$geo_coordinates = $this->geocode( $geodata['address_geocode'], false, $geodata['country_code_iso2'], $post_id );
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
			}
		} elseif (
			$this->plugin->plugin_options['geo_always_use_coordinates'] &&
			$geodata['lat'] && $geodata['lng']
		) {
			$geo_coordinates = array(
				'lat' => $geodata['lat'],
				'lng' => $geodata['lng']
			);
			$this->plugin->log->add( __( 'Property address NOT approved for publishing, but usable coordinates available and publishing permitted.', 'immonex-openimmo2wp' ), 'debug' );
		} else {
			$address = $geodata['city'];
			$this->plugin->log->add( wp_sprintf( __( 'Property address NOT approved for publishing, term used for geocoding: %s', 'immonex-openimmo2wp' ), $address ), 'debug' );

			$geo_coordinates = $this->geocode( $address, true, $geodata['country_code_iso2'], $post_id );
			if ( false !== $geo_coordinates ) {
				$this->plugin->log->add( wp_sprintf(
					__( 'Geocoding result%s: %s%s', 'immonex-openimmo2wp' ),
					! empty( $geo_coordinates['provider'] ) ? ' (' . $geo_coordinates['provider'] . ')' : '',
					$geo_coordinates['lat'] . ', ' . $geo_coordinates['lng'],
					$geo_coordinates['from_cache'] ? ' ' . __( '(cache)', 'immonex-openimmo2wp' ) : ''
				), 'debug' );
			}
		}

		if ( $geo_coordinates && is_array( $geo_coordinates ) ) {
			add_post_meta( $post_id, '_property_latitude', $geo_coordinates['lat'], true );
			add_post_meta( $post_id, '_property_longitude', $geo_coordinates['lng'], true );
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), $geo_coordinates['lat'] . ', ' . $geo_coordinates['lng'] ), 'debug' );
		} elseif ( isset( $geocoding_failed ) && $geocoding_failed ) {
			$geocoding_status = $this->get_geocoding_status( $address, $geodata['country_code_iso2'] );
			$this->plugin->log->add( wp_sprintf( __( 'Geocoding failed (%s)', 'immonex-openimmo2wp' ), $geocoding_status ? $geocoding_status : __( 'unknown reason', 'immonex-openimmo2wp' ) ), 'debug' );
		}
	} // save_property_location

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 1.0
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

			if ( in_array( strtoupper( $fileinfo['extension'] ), $valid_image_formats ) ) {
				if ( ! isset( $this->temp['post_images'][$p->post_parent] ) ) {
					$this->temp['post_images'][$p->post_parent] = array();
				}
				$this->temp['post_images'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			}
		}
	} // add_attachment_data

	/**
	 * Maybe save a shorter title for widgets and grid layouts.
	 *
	 * @since 1.0
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_shortened_title( $post_id, $immobilie ) {
		if ( strlen( (string) $immobilie->freitexte->objekttitel ) > self::MAX_LENGTH_OPTIONAL_TITLE ) {
			add_post_meta( $post_id, '_property_title', $this->plugin->string_utils->get_excerpt( (string) $immobilie->freitexte->objekttitel, self::MAX_LENGTH_OPTIONAL_TITLE, '…' ), true );
		}
	} // save_shortened_title

	/**
	 * Save extra data of property attachments as serialized array in
	 * theme-specific format.
	 *
	 * @since 1.0
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['post_images'][$post_id] ) ) {
			$this->temp['post_images'][$post_id] = $this->check_attachment_ids( $this->temp['post_images'][$post_id] );

			$post_att_data = array();

			foreach ( $this->temp['post_images'][$post_id] as $i => $att_id ) {
				$img_src = wp_get_attachment_image_src( $att_id, 'large' ) ;
				$post_att_data[] = array( 'imgurl' => $img_src[0] );

				if ( 0 == $i ) {
					// Set first image as slider image (post meta field).
					add_post_meta( $post_id, '_property_slider_image', $img_src[0], true );
				}
			}

			add_post_meta( $post_id, '_property_slides', $post_att_data, true );
			unset( $this->temp['post_images'][$post_id] );
			$this->save_temp_theme_data();
		}
	} // save_attachment_data

	/**
	 * Add a list of property meta fields... as meta field...
	 *
	 * @since 1.0
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_meta_field_list( $post_id, $immobilie ) {
		$custom_fields = get_post_meta( $post_id );

		if ( count( $custom_fields ) > 0 ) {
			$field_list = array();
			foreach ( $custom_fields as $meta_key => $value ) {
				if ( '_property' === substr( $meta_key, 0, 9 ) ) {
					$field_list[] = $meta_key;
				}
			}

			if ( count( $field_list ) > 0 ) {
				add_post_meta( $post_id, '_property_meta_fields', $field_list, true );
			}
		}
	} // save_property_meta_field_list

	/**
	 * Try to determine/save the property agent.
	 *
	 * @since 1.0
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

		if ( $name_contact ) $this->plugin->log->add( wp_sprintf( __( 'Contact person (Agent): %s', 'immonex-openimmo2wp' ), $name_contact ), 'debug' );

		$user = $this->get_agent_user( $immobilie, array(), true, $author_id );

		if ( $user ) {
			if ( $user->ID !== $author_id ) {
				// Save new author.
				$this->update_post_author( $post->ID, $user->ID );
			}
		}

		$agent = $this->get_agent( $immobilie, 'agent', array( 'email' => '_agent_email' ), array(), true );
		if ( $agent ) {
			// Save agent ID as array.
			add_post_meta( $post_id, '_property_agents', array( $agent->ID ), true );

			$agent_agency_id = get_post_meta( $agent->ID, '_agent_agency', true );
			if ( $agent_agency_id ) {
				// Save related agency ID as array.
				add_post_meta( $post_id, '_property_agencies', array( $agent_agency_id ), true );
				$this->plugin->log->add( wp_sprintf( __( 'Agency related to agent assigned to property, ID: %d', 'immonex-openimmo2wp' ), $agent_agency_id ), 'debug' );
			}
		}
	} // save_agent

	/**
	 * Try to determine the ID of the currently processed agency.
	 *
	 * @since 1.0
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
	 * @since 1.0
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
	 * @since 1.0
	 *
	 * @param array $fields Original fields array.
	 *
	 * @return array Extended fields array.
	 */
	public function extend_fields( $fields ) {
		$fields = array_merge( $fields, array(
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

} // class Realia
