<?php
namespace immonex\OpenImmo2Wp\themes;

use immonex\OpenImmo2Wp\Property_Grouping;

/**
 * Houzez-specific processing.
 */
class Houzez extends Theme_Base {

	const DEFAULT_ISO_CURRENCY = 'EUR';

	public
		$theme_class_slug = 'houzez';

	private
		$houzez_user_roles = array( 'houzez_agent', 'houzez_manager', 'houzez_owner', 'houzez_seller', 'houzez_agency' );

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 3.2
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->initial_widgets = array(
			'single-property' => array(
				'immonex_user_defined_properties_widget' => array(
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
			'post_images' => array(),
			'post_attachments' => array(),
			'area_parent_cities' => array(),
			'max_image_attachments' => 0
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_post_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_grouped_post_meta', array( $this, 'add_custom_meta_details' ), 10, 3 );
		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );
		add_filter( 'immonex_oi2wp_insert_taxonomy_term', array( $this, 'add_area_parent' ), 20, 2 );
		add_filter( 'immonex_oi2wp_custom_max_image_attachments_per_property', array( $this, 'set_max_image_attachments' ) );

		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'maybe_update_sub_units' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 15, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'do_final_processing_steps' ), 20, 2 );
		add_action( 'immonex_oi2wp_import_file_processed', array( $this, 'save_area_parents' ) );

		if ( $this->theme_options['add_every_property_to_slider'] ) {
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'add_property_to_slider' ), 10, 2 );
		}

		if ( $this->theme_options['user_listing_quotas'] ) {
			add_filter( 'immonex_oi2wp_property_xml_before_import', array( $this, 'check_listing_quota' ) );
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'decrease_current_user_available_listings_count' ), 20, 2 );
			add_action( 'before_delete_post', array( $this, 'increase_current_user_available_listings_count' ) );
		}

		add_action( 'added_post_meta', array( $this, 'backup_property_id' ), 5, 4 );
		add_action( 'added_post_meta', array( $this, 'maybe_fix_property_id' ), 20, 4 );
		add_action( 'updated_post_meta', array( $this, 'backup_property_id' ), 5, 4 );
		add_action( 'updated_post_meta', array( $this, 'maybe_fix_property_id' ), 20, 4 );
	} // __construct

	/**
	 * Check for available property related users and their package quotas.
	 *
	 * @since 3.2
	 *
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return SimpleXMLElement|bool Original property object or false if over quota.
	 */
	public function check_listing_quota( $immobilie ) {
		// Property to be deleted, ignore quota.
		if ( 'DELETE' === strtoupper( $immobilie->verwaltung_techn->aktion['aktionart'] ) ) return $immobilie;

		$user = $this->get_agent_user( $immobilie, array( 'role__in' => $this->houzez_user_roles ), true, $author_id );

		if ( $user ) {
			$package_id = get_user_meta( $user->ID, 'package_id', true );
			if ( $package_id ) {
				// Get maximum number of image attachments per property for the user's package.
				$max_images = get_post_meta( $package_id, 'fave_package_images', true );
				$unlimited_images = get_post_meta( $package_id, 'fave_unlimited_images', true );
				if ( $max_images && ! $unlimited_images ) {
					$this->temp['max_image_attachments'] = $max_images;
					$this->save_temp_theme_data();
				}
			} else {
				// User is currently not subscribed to any membership package.
				$this->plugin->log->add( __( 'User is currently not subscribed to any membership package, skipping property.', 'immonex-openimmo2wp' ), 'info' );
				return false;
			}

			$existing_properties = $this->plugin->get_property_by_openimmo_obid( (string) $immobilie->verwaltung_techn->openimmo_obid, true );
			if ( count( $existing_properties ) > 0 ) {
				// Property to be updated found, ignore quota.
				$this->temp['updated_property_ids'][] = $existing_properties[0]->ID;
				$this->save_temp_theme_data();
				return $immobilie;
			}

			$available_listings = (int) get_user_meta( $user->ID, 'package_listings', true );
			if ( 0 === $available_listings ) {
				// Property quota for this user exceeded: skip property.
				$this->plugin->log->add( __( 'Maximum number of properties reached, skipping property.', 'immonex-openimmo2wp' ), 'info' );
				return false;
			}
		}

		return $immobilie;
	} // check_listing_quota

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 * Add excerpt if not set already.
	 *
	 * @since 3.2
	 *
	 * @param mixed $post_data Current post data.
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
	 * Convert and save theme custom data in theme-specific format.
	 *
	 * @since 3.2
	 *
	 * @param mixed $grouped_meta_data Associative array of a meta data group.
	 * @param int $post_id ID of the related property post record.
	 * @param string $meta_key Meta key under which the serialized group array will be stored.
	 *
	 * @return mixed|bool Unchanged grouped meta data or false for additional features group.
	 */
	public function add_custom_meta_details( $grouped_meta_data, $post_id, $meta_key ) {
		if ( 'additional_features' !== $meta_key ) return $grouped_meta_data;

		$custom_meta = array();

		if ( count( $grouped_meta_data ) > 0 ) {
			foreach ( $grouped_meta_data as $key => $data ) {
				$custom_meta[] = array(
					'fave_additional_feature_title' => $key,
					'fave_additional_feature_value' => str_replace( "\n", " |\n", $data['value'] )
				);
			}

			add_post_meta( $post_id, $meta_key, $custom_meta, true );
			add_post_meta( $post_id, 'fave_additional_features_enable', 'enable', true );
		} else {
			add_post_meta( $post_id, 'fave_additional_features_enable', 'disable', true );
		}

		// DON'T save the original data.
		return false;
	} // add_custom_meta_details

	/**
	 * Check if attachment is a video URL (YouTube/Vimeo) or a floor plan and perform
	 * the related processing steps.
	 *
	 * @since 3.2
	 *
	 * @param SimpleXMLElement $attachment Attachment XML node.
	 * @param int $post_id ID of the related property post record.
	 *
	 * @return array Unchanged attachment XML node.
	 */
	public function check_attachment( $attachment, $post_id ) {
		if (
			in_array( (string) $attachment['gruppe'], array( 'FILMLINK', 'LINKS', 'PANORAMA' ) ) &&
			( isset( $attachment->daten->pfad ) || isset( $attachment->anhangtitel ) )
		) {
			$url = isset( $attachment->daten->pfad ) ? (string) $attachment->daten->pfad : (string) $attachment->anhangtitel;
			if ( 'http' !== substr( $url, 0, 4 ) ) return $attachment;

			if ( $this->plugin->string_utils->is_video_url( $url ) ) {
				// Save video URL as post meta.
				add_post_meta( $post_id, 'fave_video_url', $url, true );

				// DON'T import this attachment.
				return false;
			} elseif ( $this->plugin->string_utils->is_virtual_tour_url( $url, apply_filters( $this->plugin->plugin_prefix . 'additional_virtual_tour_url_parts', array() ) ) ) {
				// Save virtual tour embed code.
				$embed_code = $this->get_virtual_tour_embed_code( apply_filters( $this->plugin->plugin_prefix . 'virtual_tour_embed_code_args', array( 'url' => $url ), 5 ) );
				add_post_meta( $post_id, 'fave_virtual_tour', $embed_code, true );

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
	 * Set the parent city for a new area.
	 *
	 * @since 4.3.2 beta
	 *
	 * @param array $term_data Data of term to be inserted.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return array Maybe updated term data.
	 */
	public function add_area_parent( $term_data, $immobilie ) {
		if ( 'property_area' === $term_data['taxonomy'] ) {
			$city_name = apply_filters( $this->plugin->plugin_prefix . 'parent_city_term_name', trim( (string) $immobilie->geo->ort ) );
			$parent_city_term = apply_filters( $this->plugin->plugin_prefix . 'term_multilang', array(), $city_name, 'property_city' );

			if ( $parent_city_term ) {
				// City found, remember area name and city term slug for later processing.
				$this->temp['area_parent_cities'][$term_data['term_value']] = $parent_city_term['slug'];
				$this->save_temp_theme_data();
			}
		}

		return $term_data;
	} // add_area_parent

	/**
	 * Set the maximum number of image attachments per property if given in
	 * package configuration.
	 *
	 * @since 3.3
	 *
	 * @param int $max_images Maximum number of image attachments per property.
	 */
	public function set_max_image_attachments( $max_images ) {
		return $this->temp['max_image_attachments'];
	} // set_max_image_attachments

	/**
	 * Save the property address and/or coordinates (post meta) for geocoding.
	 *
	 * @since 3.2
	 *
	 * @param string $post_id Property ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie, true );
		$address = $geodata['address_geocode'];
		$lat = false;
		$lng = false;
		$address_publishing_status_logged = false;

		if ( $geodata['publishing_approved'] ) {
			add_post_meta( $post_id, 'fave_property_address', $geodata['street'], true );
		}

		if ( ! $geodata['address_geocode_is_coordinates'] ) {
			// Save property map address.
			add_post_meta( $post_id, 'fave_property_map_address', $geodata['address_geocode'], true );
		}

		// Save zip code.
		if ( isset( $immobilie->geo->plz ) && $immobilie->geo->plz ) add_post_meta( $post_id, 'fave_property_zip', (string) $immobilie->geo->plz, true );

		if ( isset( $geodata['country_data']['ISO 3166-1 2 Letter Code'] ) ) {
			// Save the property country as 2 letter ISO code.
			add_post_meta( $post_id, 'fave_property_country', strtoupper( $geodata['country_data']['ISO 3166-1 2 Letter Code'] ), true );
		}

		if ( isset( $geodata['country_data']['Common Name'] ) && $geodata['country_data']['Common Name'] ) {
			$this->add_taxonomy_term( $post_id, $geodata['country_data']['Common Name'], 'property_country' );
		}

		if ( $geodata['publishing_approved'] && $geodata['lat'] && $geodata['lng'] ) {
			$lat = $geodata['lat'];
			$lng = $geodata['lng'];
		} elseif (
			$this->plugin->plugin_options['geo_always_use_coordinates'] &&
			$geodata['lat'] && $geodata['lng']
		) {
			$lat = $geodata['lat'];
			$lng = $geodata['lng'];
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
				$lat = $geo['lat'];
				$lng = $geo['lng'];
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

		if ( $lat && $lng ) {
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), "$lat, $lng" ), 'debug' );
			add_post_meta( $post_id, 'houzez_geolocation_lat', $lat, true );
			add_post_meta( $post_id, 'houzez_geolocation_long', $lng, true );
			add_post_meta( $post_id, 'fave_property_location', "$lat,$lng", true );
		}
	} // save_property_location

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 3.2
	 *
	 * @param string $att_id Attachment ID.
	 * @param mixed $valid_image_formats Array of valid image file format suffixes.
	 * @param mixed $valid_misc_formats Array of valid non-image file format suffixes.
	 */
	public function add_attachment_data( $att_id, $valid_image_formats, $valid_misc_formats ) {
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

			if ( ! empty( $floor_plans ) &&	in_array( $filename, $floor_plans, true ) ) {
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
			} elseif ( in_array( strtoupper( $fileinfo['extension'] ), $valid_misc_formats ) ) {
				// Add file attachment ID.
				if ( ! isset( $this->temp['post_attachments'][$p->post_parent] ) ) {
					$this->temp['post_attachments'][$p->post_parent] = array();
				}
				$this->temp['post_attachments'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			}
		}
	} // add_attachment_data

	/**
	 * Save property attachment meta data.
	 *
	 * @since 3.2
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['post_images'][$post_id] ) ) {
			$this->temp['post_images'][$post_id] = $this->check_attachment_ids( $this->temp['post_images'][$post_id] );

			// Save property images.
			foreach ( $this->temp['post_images'][$post_id] as $image_post_id ) {
				// Save as NON-UNIQUE single records.
				add_post_meta( $post_id, 'fave_property_images', $image_post_id, false );
			}
			unset( $this->temp['post_images'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['post_attachments'][$post_id] ) ) {
			$this->temp['post_attachments'][$post_id] = $this->check_attachment_ids( $this->temp['post_attachments'][$post_id] );

			// Save property file attachments.
			foreach ( $this->temp['post_attachments'][$post_id] as $attachment_post_id ) {
				// Save as NON-UNIQUE single records.
				add_post_meta( $post_id, 'fave_attachments', $attachment_post_id, false );
			}
			unset( $this->temp['post_attachments'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['property_floor_plans']['ids'][$post_id] ) ) {
			// Save floor plan data.
			$this->temp['property_floor_plans']['ids'][$post_id] = $this->check_attachment_ids( $this->temp['property_floor_plans']['ids'][$post_id] );

			$floor_plans = array();

			foreach ( $this->temp['property_floor_plans']['ids'][$post_id] as $cnt => $att_id ) {
				$attachment = wp_prepare_attachment_for_js( $att_id );
				$attachment_image = wp_get_attachment_image_src( $att_id, 'large' );

				$floor_plans[] = array(
					'fave_plan_title' => $attachment['title'] ? $attachment['title'] : __( 'Floor Plan', 'immonex-openimmo2wp' ),
					'fave_plan_rooms' => '',
					'fave_plan_bathrooms' => '',
					'fave_plan_price' => '',
					'fave_plan_size' => '',
					'fave_plan_image' => $attachment_image[0],
					'fave_plan_description' => ''
				);
			}

			add_post_meta( $post_id, 'fave_floor_plans_enable', 'enable', true );
			add_post_meta( $post_id, 'floor_plans', $floor_plans, true );

			unset( $this->temp['property_floor_plans']['ids'][$post_id] );
			$this->save_temp_theme_data();
		} else {
			add_post_meta( $post_id, 'fave_floor_plans_enable', 'disable', true );
		}

		// External Video available? Set thumbnail as video image if so.
		$video_url = get_post_meta( $post_id, 'fave_video_url', true );
		if ( $video_url ) {
			$thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );
			if ( $thumbnail_id ) add_post_meta( $post_id, 'fave_video_image', $thumbnail_id, true );
		}
	} // save_attachment_data

	/**
	 * Update parent (master) object children (sub unit) IDs if the property has
	 * been imported in a group context.
	 *
	 * @since 4.7.0
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function maybe_update_sub_units( $post_id, $immobilie ) {
		$group_id = get_post_meta( $post_id, '_immonex_group_id', true );
		if ( $group_id ) {
			$group_parent = get_post_meta( $post_id, '_immonex_group_master', true );

			if ( $group_parent ) {
				// Property is a group parent (master) object...
				$group_children_ids = Property_Grouping::get_children_ids( $post_id );

				if ( count( $group_children_ids ) ) {
					// ...save children IDs if already existent...
					add_post_meta( $post_id, 'fave_multi_units_ids', implode( ',', $group_children_ids ) );
				}

				if ( 'invisible' === $group_parent ) {
					// ...and possibly update the property has NOT been submitted as "visible".
					wp_update_post( array(
						'ID' => $post_id,
						'post_status' => 'pending'
					) );
				}
			} else {
				$group_parent_id = Property_Grouping::get_parent_id( $post_id );

				if ( $group_parent_id ) {
					// Property is a child object of an existing parent object: Add its ID
					// to the respective theme's children list custom fields.
					$theme_children_id_string = get_post_meta( $group_parent_id, 'fave_multi_units_ids', true );
					$theme_children_ids = $theme_children_id_string ?
						array_unique( array_map( 'trim', explode( ',', $theme_children_id_string ) ) ) :
						array();

					if ( ! in_array( $post_id, $theme_children_ids ) ) {
						$theme_children_ids[] = $post_id;
						update_post_meta(
							$group_parent_id,
							'fave_multi_units_ids',
							implode( ',', $theme_children_ids )
						);
					}
				}
			}
		}
	} // maybe_update_sub_units

	/**
	 * Try to determine/save the property agent.
	 *
	 * @since 3.2
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

		$user = $this->get_agent_user( $immobilie, array( 'role__in' => $this->houzez_user_roles ), true, $author_id );

		if ( $user ) {
			if ( $user->ID !== $author_id ) {
				// Save new author.
				$this->update_post_author( $post->ID, $user->ID );
			}

			$user_agent_id = get_user_meta( $user->ID, 'fave_author_agent_id', true );
			if ( $user_agent_id ) {
				// Save related AGENT ID if given.
				$this->plugin->log->add( wp_sprintf( __( 'Assigning agent (ID: %d) linked to user.', 'immonex-openimmo2wp' ), $user_agent_id ), 'debug' );
				add_post_meta( $post_id, 'fave_agents', $user_agent_id, true );
			}

			$user_agency_id = get_user_meta( $user->ID, 'fave_author_agency_id', true );
			// Save related AGENCY ID if given.
			if ( $user_agency_id ) {
				$this->plugin->log->add( wp_sprintf( __( 'Assigning agency (ID: %d) linked to user.', 'immonex-openimmo2wp' ), $user_agency_id ), 'debug' );
				add_post_meta( $post_id, 'fave_property_agency', $user_agency_id, true );
			}

			if ( $user_agent_id || $user_agency_id ) {
				add_post_meta( $post_id, 'fave_agent_display_option', $user_agent_id ? 'agent_info' : 'agency_info', true );
			}

			if ( $user_agent_id ) {
				return;
			}
		}

		$agent = $this->get_agent( $immobilie, 'houzez_agent', array( 'email' => 'fave_agent_email' ), array(), true );
		if ( $agent ) {
			// Save agent ID and display option.
			add_post_meta( $post_id, 'fave_agents', $agent->ID, true );
			update_post_meta( $post_id, 'fave_agent_display_option', 'agent_info' );

			$agency_id = get_post_meta( $agent->ID, 'fave_agent_agencies', true );
			if ( $agency_id ) add_post_meta( $post_id, 'fave_property_agency', $agency_id, true );
		} elseif (
			$user &&
			(
				get_user_meta( $user->ID, 'fave_author_phone', true ) ||
				get_user_meta( $user->ID, 'fave_author_mobile', true )
			)
		) {
			// Phone number(s) set in author record: display as contact information on property detail pages.
			add_post_meta( $post_id, 'fave_agent_display_option', 'author_info', true );
		} else {
			add_post_meta( $post_id, 'fave_agent_display_option', 'none', true );
		}
	} // save_agent

	/**
	 * Do the final processing steps for a property object (e.g. save
	 * additional and default property options).
	 *
	 * @since 3.2
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function do_final_processing_steps( $post_id, $immobilie ) {
		$defaults = array(
			'fave_single_top_area' => 'global',
			'fave_single_content_area' => 'global',
			'fave_property_price' => 0,
			'fave_property_map' => $this->theme_options['enable_map'] ? 1 : 0,
			'fave_property_map_street_view' => $this->theme_options['enable_street_view'] ? 'show' : 'hide',
			'fave_prop_homeslider' => 'no',
			'fave_property_size_prefix' => \immonex\OpenImmo2Wp\Import_Content_Filters::SQM_TERM,
			'fave_property_land_postfix' => \immonex\OpenImmo2Wp\Import_Content_Filters::SQM_TERM,
			'fave_featured' => 0
		);

		foreach ( $defaults as $meta_name => $meta_value ) {
			if ( ! get_post_meta( $post_id, $meta_name, true ) ) {
				add_post_meta( $post_id, $meta_name, $meta_value, true );
			}
		}

		$sec_price = get_post_meta( $post_id, 'fave_property_sec_price', true );
		if ( $sec_price ) {
			// Secondary price (per m²) set: adjust price postfix.
			update_post_meta( $post_id, 'fave_property_price_postfix', __( 'per', 'immonex-openimmo2wp' ) . '&nbsp;' . \immonex\OpenImmo2Wp\Import_Content_Filters::SQM_TERM );

			if ( ! get_post_meta( $post_id, 'fave_property_price', true ) ) {
				// Primary price not set: transfer secondary price value.
				update_post_meta( $post_id, 'fave_property_price', $sec_price );
				update_post_meta( $post_id, 'fave_property_sec_price', '' );
			}
		}

		$garage_size = get_post_meta( $post_id, 'fave_property_garage_size', true );
		if ( $garage_size && is_numeric( $garage_size ) ) {
			// Add "Car Space(s)" to the garage size statement.
			$garage_size .= ' ' . _n( 'Car Space', 'Car Spaces', (int) $garage_size, 'immonex-openimmo2wp' );
			update_post_meta( $post_id, 'fave_property_garage_size', $garage_size );
		}

		$currency = false;
		if ( ! empty( $immobilie->preise->waehrung['iso_waehrung'] ) ) {
			$currency = (string) $immobilie->preise->waehrung['iso_waehrung'];
		} elseif ( is_callable( 'houzez_option' ) ) {
			$currency = houzez_option( 'default_multi_currency' );
		}

		if ( ! $currency ) {
			$currency = self::DEFAULT_ISO_CURRENCY;
		}

		update_post_meta( $post_id, 'fave_currency', $currency );
		if ( is_callable( '\Houzez_Currencies::get_property_currency_2' ) ) {
			$currency_info = \Houzez_Currencies::get_property_currency_2( $post_id, $currency );
			if ( $currency_info ) {
				update_post_meta( $post_id, 'fave_currency_info', $currency_info );
			}
		}
	} // do_final_processing_steps

	/**
	 * Save parent cities of area taxonomy terms.
	 *
	 * @since 4.3.2 beta
	 *
	 * @param string $filename Name of processed file.
	 */
	public function save_area_parents( $filename ) {
		if ( ! empty( $this->temp['area_parent_cities'] ) ) {
			foreach ( $this->temp['area_parent_cities'] as $area_term_name => $area_parent_city_slug ) {
				$area_term = apply_filters( $this->plugin->plugin_prefix . 'term_multilang', array(), $area_term_name, 'property_area' );

				if ( $area_term ) {
					$option_name = '_houzez_property_area_' . $area_term['term_id'];
					$option_value = array( 'parent_city' => $area_parent_city_slug );
					add_option( $option_name, $option_value );
				}
			}
		}
	} // save_area_parents

	/**
	 * Add the main property image to the slider.
	 *
	 * @since 3.2
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function add_property_to_slider( $post_id, $immobilie ) {
		$thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );

		if ( $thumbnail_id ) {
			add_post_meta( $post_id, 'fave_prop_homeslider', 'yes', true );
			add_post_meta( $post_id, 'fave_prop_slider_image', $thumbnail_id, true );
		}
	} // add_property_to_slider

	/**
	 * Decrease current number of AVAILABLE properties for user.
	 *
	 * @since 3.2
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function decrease_current_user_available_listings_count( $post_id, $immobilie ) {
		// Don't increase the current listings counter on updated properties.
		if (
			is_array( $this->temp['updated_property_ids'] ) &&
			in_array( $post_id, $this->temp['updated_property_ids'] )
		) return;

		$property = get_post( $post_id );
		if ( ! $property || $property->post_type !== $this->plugin->property_post_type ) return;

		$user_id = get_post_field( 'post_author', $post_id );

		if ( $user_id ) {
			$package_id = get_user_meta( $user_id, 'package_id', true );
			if ( ! $package_id ) return;

			$available_listings = get_user_meta( $user_id, 'package_listings', true );
			if ( $available_listings > 0 ) {
				// Decrease current number of available (normal) listings for user.
				update_user_meta( $user_id, 'package_listings', $available_listings - 1, $available_listings );
				$this->plugin->log->add( wp_sprintf( __( "User's current number of available listings decreased, now: %d", 'immonex-openimmo2wp' ), $available_listings - 1 ), 'debug' );
			}

			$is_featured = get_post_meta( $post_id, 'fave_featured', true );
			if ( $is_featured ) {
				$available_featured_listings = get_user_meta( $user_id, 'package_featured_listings', true );
				if ( $available_featured_listings > 0 ) {
					// Decrease current number of available featured listings for user.
					update_user_meta( $user_id, 'package_featured_listings', $available_featured_listings - 1, $available_featured_listings );
					$this->plugin->log->add( wp_sprintf( __( "User's current number of available featured listings decreased, now: %d", 'immonex-openimmo2wp' ), $available_featured_listings - 1 ), 'debug' );
				} else {
					// No more featured listings available, remove featured status.
					update_post_meta( $post_id, 'fave_featured', 0 );
					$this->plugin->log->add( __( 'Maximum number of featured listings reached, featured status removed.', 'immonex-openimmo2wp' ), 'info' );
				}
			}
		}
	} // decrease_current_user_available_listings_count

	/**
	 * Increase current number of AVAILABLE properties for user on
	 * property post deletion.
	 *
	 * @since 3.2
	 *
	 * @param string $post_id Property post ID.
	 */
	public function increase_current_user_available_listings_count( $post_id ) {
		$user_id = get_post_field( 'post_author', $post_id );

		if ( $user_id ) {
			$package_id = get_user_meta( $user_id, 'package_id', true );

			if ( $package_id ) {
				$available_listings = (int) get_user_meta( $user_id, 'package_listings', true );
				$available_featured_listings = (int) get_user_meta( $user_id, 'package_featured_listings', true );
				$package_max_listings = (int) get_post_meta( $package_id, 'fave_package_listings', true );
				$package_max_featured_listings = (int) get_post_meta( $package_id, 'fave_package_featured_listings', true );

				// Increase current number of available (normal) listings for user.
				if ( $available_listings < $package_max_listings || -1 !== $available_listings ) {
					update_user_meta( $user_id, 'package_listings', $available_listings + 1, $available_listings );
				}

				$is_featured = get_post_meta( $post_id, 'fave_featured', true );
				if ( $is_featured && $available_featured_listings < $package_max_featured_listings ) {
					// Increase current number of featured listings for user.
					update_user_meta( $user_id, 'package_featured_listings', $available_featured_listings + 1, $available_featured_listings );
				}
			}
		}
	} // increase_current_user_available_listings_count

	/**
	 * Backup the property ID (fave_property_id) under an alternative field name
	 * (action callback workaround to fix an Houzez theme bug).
	 *
	 * @since 5.3.9-beta
	 *
	 * @param string $meta_id The meta ID after saving.
	 * @param string $post_id ID of the post the metadata is for.
	 * @param string $meta_key Metadata key.
	 * @param string $meta_value Metadata value.
	 */
	public function backup_property_id( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( 'fave_property_id' !== $meta_key  || empty( $meta_value ) ) {
			return;
		}

		if ( class_exists( 'Houzez_Post_Type_Property' ) ) {
			remove_action( 'added_post_meta', array( 'Houzez_Post_Type_Property', 'save_property_post_type' ), 10 );
			remove_action( 'updated_post_meta', array( 'Houzez_Post_Type_Property', 'save_property_post_type' ), 10 );
		}

		add_post_meta( $post_id, '_fave_property_id', $meta_value );

		if ( class_exists( 'Houzez_Post_Type_Property' ) ) {
			add_action( 'added_post_meta', array( 'Houzez_Post_Type_Property', 'save_property_post_type' ), 10, 4 );
			add_action( 'updated_post_meta', array( 'Houzez_Post_Type_Property', 'save_property_post_type' ), 10, 4 );
		}
	} // backup_property_id

	/**
	 * Maybe restore the property ID
	 * (action callback workaround to fix an Houzez theme bug).
	 *
	 * @since 5.3.9-beta
	 *
	 * @param string $meta_id The meta ID after saving.
	 * @param string $post_id ID of the post the metadata is for.
	 * @param string $meta_key Metadata key.
	 * @param string $meta_value Metadata value.
	 */
	public function maybe_fix_property_id( $meta_id, $post_id, $meta_key, $meta_value ) {
		if ( 'fave_property_id' !== $meta_key || ! empty( $meta_value ) ) {
			return;
		}

		if ( class_exists( 'Houzez_Post_Type_Property' ) ) {
			remove_action( 'added_post_meta', array( 'Houzez_Post_Type_Property', 'save_property_post_type' ), 10 );
			remove_action( 'updated_post_meta', array( 'Houzez_Post_Type_Property', 'save_property_post_type' ), 10 );
		}

		remove_action( 'added_post_meta', array( $this, 'backup_property_id' ), 5 );
		remove_action( 'added_post_meta', array( $this, 'maybe_fix_property_id' ), 20 );
		remove_action( 'updated_post_meta', array( $this, 'backup_property_id' ), 5 );
		remove_action( 'updated_post_meta', array( $this, 'maybe_fix_property_id' ), 20 );

		$backup_id = get_post_meta( $post_id, '_fave_property_id', true );
		if ( $backup_id ) {
			update_post_meta( $post_id, 'fave_property_id', $backup_id );
		}

		if ( class_exists( 'Houzez_Post_Type_Property' ) ) {
			add_action( 'added_post_meta', array( 'Houzez_Post_Type_Property', 'save_property_post_type' ), 10, 4 );
			add_action( 'updated_post_meta', array( 'Houzez_Post_Type_Property', 'save_property_post_type' ), 10, 4 );
		}

		add_action( 'added_post_meta', array( $this, 'backup_property_id' ), 5, 4 );
		add_action( 'added_post_meta', array( $this, 'maybe_fix_property_id' ), 20, 4 );
		add_action( 'updated_post_meta', array( $this, 'backup_property_id' ), 5, 4 );
		add_action( 'updated_post_meta', array( $this, 'maybe_fix_property_id' ), 20, 4 );
	} // maybe_fix_property_id

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 3.2
	 *
	 * @param mixed $sections Original sections array.
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
	 * @since 3.2
	 *
	 * @param mixed $fields Original fields array.
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
				'name' => $this->theme_class_slug . '_enable_map',
				'type' => 'checkbox',
				'label' => __( 'Enable Map', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Enable a map view for the property location on the detail pages.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_enable_street_view',
				'type' => 'checkbox',
				'label' => __( 'Enable Street View', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Enable Google Street View on the property detail pages.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_add_every_property_to_slider',
				'type' => 'checkbox',
				'label' => __( 'Add properties to slider', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'For each property, the <strong>first</strong> image attachment will be used as slider image. These should be high resolution images with the <strong>same aspect ratio</strong>.', 'immonex-openimmo2wp' )
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

} // class Houzez
