<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * Inventor-Properties-specific processing (plugin version).
 */
class Inventor_Properties extends Theme_Base {

	public
		$theme_class_slug = 'inventor-properties';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 2.4
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
			'post_images' => array()
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_post_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_grouped_post_meta', array( $this, 'add_custom_meta_details' ), 10, 3 );
		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'set_agent_as_post_author' ), 10, 2 );
		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );
		add_filter( 'immonex_oi2wp_insert_taxonomy_term', array( $this, 'maybe_add_location_parent' ), 10, 2 );

		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 4 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_special_meta_data' ), 10, 2 );

		if ( $this->theme_options['user_listing_quotas'] ) {
			add_filter( 'immonex_oi2wp_property_xml_before_import', array( $this, 'check_listing_quota' ) );
		}
	} // __construct

	/**
	 * Check for available property related users and their package quotas.
	 *
	 * @since 2.4
	 *
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return SimpleXMLElement|bool Original property object or false if over quota.
	 */
	public function check_listing_quota( $immobilie ) {
		// Property to be deleted, ignore quota.
		if ( 'DELETE' === strtoupper( $immobilie->verwaltung_techn->aktion['aktionart'] ) ) return $immobilie;

		$user = $this->get_agent_user( $immobilie, array(), false );

		if ( $user && method_exists( 'Inventor_Packages_Logic', 'get_remaining_listings_count_for_user' ) ) {
			$existing_properties = $this->plugin->get_property_by_openimmo_obid( (string) $immobilie->verwaltung_techn->openimmo_obid, true );
			if ( count( $existing_properties ) > 0 ) {
				// Property to be updated or deleted found, ignore quota.
				$this->temp['updated_property_ids'][] = $existing_properties[0]->ID;
				$this->save_temp_theme_data();
				return $immobilie;
			}

			$listings_available = \Inventor_Packages_Logic::get_remaining_listings_count_for_user( $user->ID );
			if ( $listings_available === 0 ) {
				// Property quota for this user exceeded: skip property.
				$this->plugin->log->add( __( 'Maximum number of properties reached or package not valid, skipping property.', 'immonex-openimmo2wp' ), 'info' );
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
	 * Add extra content to property main descriptions (post data) during import.
	 * Add excerpt if not set already.
	 *
	 * @since 2.4
	 *
	 * @param array $post_data Current post data.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return array (Possibly) modified property data to store as post record.
	 */
	public function add_post_content( $post_data, $immobilie ) {
		if ( trim( $this->theme_options['add_description_content'] ) ) {
			$post_data['post_content'] .= "\n" . $this->plugin->multilang_get_string_translation( $this->theme_options['add_description_content'] );
		}

		if ( ! isset( $post_data['post_excerpt'] ) || ! $post_data['post_excerpt'] ) {
			$post_data['post_excerpt'] = $this->plugin->string_utils->get_excerpt( $post_data['post_content'], 120, '...' );
		}

		return $post_data;
	} // add_post_content

	/**
	 * Convert and save theme custom data.
	 *
	 * @since 2.4
	 *
	 * @param array $grouped_meta_data Associative array of a meta data group.
	 * @param int $post_id ID of the related property post record.
	 * @param string $meta_key Meta key under which the serialized group array will be stored.
	 */
	public function add_custom_meta_details( $grouped_meta_data, $post_id, $meta_key ) {
		if ( 'listing_property_public_facilities' !== $meta_key ) return $grouped_meta_data;

		$public_facilities = array();

		if ( count( $grouped_meta_data ) > 0 ) {
			foreach ( $grouped_meta_data as $key => $data ) {
				$public_facilities[] = array(
					'listing_property_public_facilities_key' => $key,
					'listing_property_public_facilities_value' => $data['value']
				);
			}

			add_post_meta( $post_id, 'listing_property_public_facilities', $public_facilities, true );
		}

		// DON'T save the original data.
		return false;
	} // add_custom_meta_details

	/**
	 * Check attachment type and perform related processing steps.
	 *
	 * @since 2.4
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
				// Attachment is an URL of an external video: save as post meta.
				add_post_meta( $post_id, 'listing_video', $url, true );
				add_post_meta( $post_id, 'listing_banner_video_embed', $url, true );

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
	 * Maybe add a location term parent ID (districts).
	 *
	 * @since 4.1
	 *
	 * @param mixed[] $term_data Array of term and mapping data.
	 * @param SimpleXMLElement $immobilie XML node of the related property object.
	 *
	 * @return mixed[] Eventually updated term data array.
	 */
	public function maybe_add_location_parent( $term_data, $immobilie ) {
		if (
			'geo->regionaler_zusatz' === $term_data['mapping']['source'] &&
			'locations' === $term_data['mapping']['dest']
		) {
			$locality = trim( (string) $immobilie->geo->ort );
			$parent = apply_filters( $this->plugin->plugin_prefix . 'term_multilang', array(), $locality, 'locations' );

			if ( $parent ) $term_data['args']['parent'] = $parent['term_id'];
		}

		return $term_data;
	} // maybe_add_location_parent

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 2.4
	 *
	 * @param string $att_id Attachment ID.
	 * @param array $valid_image_formats Array of valid image file format suffixes.
	 * @param array $valid_misc_formats Array of valid non-image/video file format suffixes.
	 * @param array $valid_video_formats Array of valid video file format suffixes.
	 */
	public function add_attachment_data( $att_id, $valid_image_formats, $valid_misc_formats, $valid_video_formats ) {
		$p = get_post( $att_id );

		if ( $p ) {
			$fileinfo = pathinfo( get_attached_file( $att_id ) );
			if ( ! isset( $fileinfo['extension'] ) ) return;

			// Remove counter from filename for comparison (floor plans).
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
			} elseif ( in_array( strtoupper( $fileinfo['extension'] ), $valid_image_formats ) ) {
				// Add image ID.
				if ( ! isset( $this->temp['post_images'][$p->post_parent] ) ) {
					$this->temp['post_images'][$p->post_parent] = array();
				}
				$this->temp['post_images'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			} elseif ( in_array( strtoupper( $fileinfo['extension'] ), $valid_video_formats ) ) {
				// Add ID and URL of a (local) video file for the banner, set video banner type.
				add_post_meta( $p->post_parent, 'listing_banner_video_id', $att_id, true );
				add_post_meta( $p->post_parent, 'listing_banner_video', wp_get_attachment_url( $att_id ), true );
				add_post_meta( $p->post_parent, 'listing_banner', 'banner_video', true );
			}
		}
	} // add_attachment_data

	/**
	 * Save the property address and coordinates (custom fields).
	 *
	 * @since 2.4
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie );
		$geo_coordinates = false;
		$address_publishing_status_logged = false;

		$address = $geodata['address_geocode'];

		if ( $geodata['publishing_approved'] && $geodata['lat'] && $geodata['lng'] ) {
			$geo_coordinates = array(
				'latitude' => (string) $geodata['lat'],
				'longitude' => (string) $geodata['lng']
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
			$geo = $this->geocode( $address, $geodata['publishing_approved'] ? false : true, $geodata['country_code_iso2'], $post_id );
			if ( $geo ) {
				$geo_coordinates = array(
					'latitude' => (string) $geo['lat'],
					'longitude' => (string) $geo['lng']
				);

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

		$location = array( 'address' => $address );

		if ( $geo_coordinates ) {
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s, %s', 'immonex-openimmo2wp' ), $geo_coordinates['latitude'], $geo_coordinates['longitude'] ), 'debug' );
			$location['latitude'] = $geo_coordinates['latitude'];
			$location['longitude'] = $geo_coordinates['longitude'];
			add_post_meta( $post_id, 'listing_map_location_latitude', $geo_coordinates['latitude'], true );
			add_post_meta( $post_id, 'listing_map_location_longitude', $geo_coordinates['longitude'], true );

			// Default values for Google Street View and Inside View.
			$google_geo_defaults = array(
				'zoom' => '1.0000000000000002',
				'heading' => '-18',
				'pitch' => '25'
			);

			$additional_google_geo_fields = array( 'listing_street_view', 'listing_inside_view' );

			foreach ( $additional_google_geo_fields as $base_field_name ) {
				$add_google_location = array_merge( array(
					'latitude' => $geo_coordinates['latitude'],
					'longitude' => $geo_coordinates['longitude']
				), $google_geo_defaults );
				add_post_meta( $post_id, $base_field_name . '_location', $add_google_location, true );
				add_post_meta( $post_id, $base_field_name . '_location_latitude', $add_google_location['latitude'], true );
				add_post_meta( $post_id, $base_field_name . '_location_longitude', $add_google_location['longitude'], true );
			}
		}

		add_post_meta( $post_id, 'listing_map_location_address', $address, true );
		add_post_meta( $post_id, 'listing_map_location', $location, true );
	} // save_property_location

	/**
	 * Save additional property data (amenities, category, gallery etc.).
	 *
	 * @since 2.4
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_special_meta_data( $post_id, $immobilie ) {
		// Additionally save property amenities as serialized slug array in unique custom field.
		$amenities = wp_get_post_terms( $post_id, 'property_amenities', array( 'fields' => 'slugs' ) );
		if ( is_array( $amenities ) ) add_post_meta( $post_id, 'listing_property_amenities', $amenities, true );

		// Additionally save listing categories as serialized slug array in unique custom field.
		$categories = wp_get_post_terms( $post_id, 'listing_categories', array( 'fields' => 'slugs' ) );
		if ( is_array( $categories ) ) add_post_meta( $post_id, 'listing_listing_category', $categories, true );

		// Additionally save locations as serialized slug array in unique custom field.
		$locations = wp_get_post_terms( $post_id, 'locations', array( 'fields' => 'slugs' ) );
		if ( is_array( $categories ) ) add_post_meta( $post_id, 'listing_locations', $locations, true );

		if ( ! empty( $this->temp['post_images'][$post_id] ) ) {
			$this->temp['post_images'][$post_id] = $this->check_attachment_ids( $this->temp['post_images'][$post_id] );

			if ( count( $this->temp['post_images'][$post_id] ) > 0 ) {
				// Save featured/banner/slider image data.
				$featured_image_id = $this->temp['post_images'][$post_id][0];
				add_post_meta( $post_id, 'listing_featured_image_id', $featured_image_id, true );
				update_post_meta( $post_id, 'listing_featured_image', wp_get_attachment_url( $featured_image_id ) );
				add_post_meta( $post_id, 'listing_banner_image_id', $featured_image_id, true );
				add_post_meta( $post_id, 'listing_banner_image', wp_get_attachment_url( $featured_image_id ) );
				add_post_meta( $post_id, 'listing_listing_slider_image_id', $featured_image_id, true );
				add_post_meta( $post_id, 'listing_listing_slider_image', wp_get_attachment_url( $featured_image_id ) );

				$gallery_images = array();

				foreach ( $this->temp['post_images'][$post_id] as $att_id ) {
					$gallery_images[$att_id] = wp_get_attachment_url( $att_id );
				}

				// Save property gallery data.
				add_post_meta( $post_id, 'listing_gallery', $gallery_images, true );
			}

			unset( $this->temp['post_images'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['property_floor_plans']['ids'][$post_id] ) ) {
			// Save property floor plan images.
			$this->temp['property_floor_plans']['ids'][$post_id] = $this->check_attachment_ids( $this->temp['property_floor_plans']['ids'][$post_id] );

			if ( count( $this->temp['property_floor_plans']['ids'][$post_id] ) > 0 ) {
				$floor_plans = array();

				foreach ( $this->temp['property_floor_plans']['ids'][$post_id] as $att_id ) {
					$floor_plans[$att_id] = wp_get_attachment_url( $att_id );
				}

				add_post_meta( $post_id, 'listing_property_floor_plans', $floor_plans, true );
			}

			unset( $this->temp['property_floor_plans']['ids'][$post_id] );
			$this->save_temp_theme_data();
		}

		$additional_meta_data = array(
			'listing_banner' => $this->theme_options['listing_banner'],
			'listing_banner_map_zoom' => $this->theme_options['listing_banner_map_zoom'],
			'listing_banner_map_type' => $this->theme_options['listing_banner_map_type'],
		);
		if ( $this->theme_options['listing_banner_map_marker'] ) $additional_meta_data['listing_banner_map_marker'] = 'on';
		if ( $this->theme_options['enable_street_view'] ) $additional_meta_data['listing_street_view'] = 'on';
		if ( $this->theme_options['enable_inside_view'] ) $additional_meta_data['listing_inside_view'] = 'on';

		foreach ( $additional_meta_data as $key => $value ) {
			add_post_meta( $post_id, $key, $value, true );
		}
	} // save_special_meta_data

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 2.4
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
	 * @since 2.4
	 *
	 * @param array $fields Original fields array.
	 *
	 * @return array Extended fields array.
	 */
	public function extend_fields( $fields ) {
		$enabled_banner_types = method_exists( 'Inventor_Metaboxes', 'enabled_banner_types' ) ? \Inventor_Metaboxes::enabled_banner_types() : array( 'banner_featured_image' => 'Featured Image' );
		foreach ( $enabled_banner_types as $type => $name ) {
			// Disable Google Street View and Google Inside View.
			if ( in_array( $type, array( 'banner_street_view', 'banner_inside_view' ) ) ) unset( $enabled_banner_types[$type] );
		}

		$banner_map_types = array(
			'ROADMAP' => __( 'Roadmap', 'immonex-openimmo2wp' ),
			'SATELLITE' => __( 'Satellite', 'immonex-openimmo2wp' ),
			'HYBRID' => __( 'Hybrid', 'immonex-openimmo2wp' )
		);

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
				'name' => $this->theme_class_slug . '_listing_banner',
				'type' => 'select',
				'label' => __( 'Default Banner Type', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Select the banner type to be displayed on property details pages. (Video banners will be activated automatically for properties with videos.)', 'immonex-openimmo2wp' ),
					'options' => $enabled_banner_types
				)
			),
			array(
				'name' => $this->theme_class_slug . '_listing_banner_map_zoom',
				'type' => 'text',
				'label' => __( 'Banner Map Zoom', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Enter the default zoom level if Google Map is selected as banner type.', 'immonex-openimmo2wp' ),
					'class' => 'small-text',
					'min' => 0,
					'under_min_default' => 12,
					'max' => 25,
					'over_max_default' => 12
				)
			),
			array(
				'name' => $this->theme_class_slug . '_listing_banner_map_type',
				'type' => 'select',
				'label' => __( 'Banner Map Type', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Select the map type if Google Map is selected as banner type.', 'immonex-openimmo2wp' ),
					'options' => $banner_map_types
				)
			),
			array(
				'name' => $this->theme_class_slug . '_listing_banner_map_marker',
				'type' => 'checkbox',
				'label' => __( 'Show Map Marker', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Activate if a location marker shall be displayed on Google Map banners.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_enable_street_view',
				'type' => 'checkbox',
				'label' => __( 'Enable Street View', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Activate to enable Google Street View.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_enable_inside_view',
				'type' => 'checkbox',
				'label' => __( 'Enable Inside View', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Activate to enable Google Inside View.', 'immonex-openimmo2wp' )
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

} // class Inventor_Properties
