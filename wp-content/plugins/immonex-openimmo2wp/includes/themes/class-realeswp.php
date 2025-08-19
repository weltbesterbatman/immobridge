<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * RealesWP-specific processing.
 */
class RealesWP extends Theme_Base {

	public
		$theme_class_slug = 'realeswp';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 2.6
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

		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_property_custom_field', array( $this, 'add_city_to_theme_options' ), 10, 3 );
		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_post_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_grouped_post_meta', array( $this, 'add_amenities' ), 10, 3 );

		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_defaults' ), 15, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 20, 2 );

		if ( $this->theme_options['user_listing_quotas'] ) {
			add_filter( 'immonex_oi2wp_property_xml_before_import', array( $this, 'check_listing_quota' ) );
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'decrease_available_listings' ), 20, 2 );
			add_action( 'before_delete_post', array( $this, 'increase_available_listings' ) );
		}
	} // __construct

	/**
	 * Check for available property related users/agents and their package quotas.
	 *
	 * @since 2.6
	 *
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return SimpleXMLElement|bool Original property object or false if over quota.
	 */
	public function check_listing_quota( $immobilie ) {
		// Property to be deleted, ignore quota.
		if ( 'DELETE' === strtoupper( $immobilie->verwaltung_techn->aktion['aktionart'] ) ) return $immobilie;

		$agent = false;
		$user = $this->get_agent_user( $immobilie, array(), false );

		if ( $user ) {
			// WP user to be assigned as author found: check for a linked agent.
			$agent = $this->_get_user_agent( $user->ID );
		}

		if ( ! $agent ) {
			// No linked agent: check for a matching agent without user assignment.
			$agent = $this->get_agent( $immobilie, 'agent', array( 'email' => 'agent_email' ) );
		}

		if ( $agent ) {
			$existing_properties = $this->plugin->get_property_by_openimmo_obid( (string) $immobilie->verwaltung_techn->openimmo_obid, true );

			// See file libs/save_property.php in Reales WP theme directory for checking the native quota code.
			$reales_membership_settings = get_option( 'reales_membership_settings', '' );
			$payment_type = isset( $reales_membership_settings['reales_paid_field'] ) ? $reales_membership_settings['reales_paid_field'] : false;
			$agent_payment = get_post_meta( $agent->ID, 'agent_payment', true );

			if ( count( $existing_properties ) > 0 || '1' == $agent_payment ) {
				// Property to be updated found or agent payment custom field set to 1.
				$this->temp['updated_property_ids'][] = $existing_properties[0]->ID;
				$this->save_temp_theme_data();
				return $immobilie;
			}

			if ( 'membership' === $payment_type && intval( get_post_meta( $agent->ID, 'agent_plan_listings', true ) ) < 1 ) {
				// Property quota for this user/plan exceeded: skip property.
				$this->plugin->log->add( __( 'Maximum number of properties reached, skipping property.', 'immonex-openimmo2wp' ), 'info' );
				return false;
			}
		}

		return $immobilie;
	} // check_listing_quota

	/**
	 * Check attachment type and perform related processing steps.
	 *
	 * @since 2.6
	 *
	 * @param SimpleXMLElement $attachment Attachment XML node.
	 * @param int $post_id ID of the related property post record.
	 *
	 * @return mixed|bool Unchanged attachment data or false for video links.
	 */
	public function check_attachment( $attachment, $post_id ) {
		if (
			in_array( (string) $attachment['gruppe'], array( 'FILMLINK', 'LINKS' ) ) &&
			( isset( $attachment->daten->pfad ) || isset( $attachment->anhangtitel ) )
		) {
			$url = isset( $attachment->daten->pfad ) ? (string) $attachment->daten->pfad : (string) $attachment->anhangtitel;
			if ( 'http' !== substr( $url, 0, 4 ) ) return $attachment;

			$video = $this->plugin->string_utils->is_video_url( $url );

			if ( $video && in_array( $video['type'], array( 'youtube', 'vimeo' ) ) ) {
				// Attachment is an URL of an external video.
				add_post_meta( $post_id, 'property_video_source', $video['type'], true );
				add_post_meta( $post_id, 'property_video_id', $video['id'], true );

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
	 * Add extra content to property main descriptions (post data) during import.
	 * Add excerpt if not set already.
	 *
	 * @since 2.6
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
	 * Add city to theme options (if nonexistent yet).
	 *
	 * @since 2.6
	 *
	 * @param mixed $custom_field_data Associative array of a custom field data.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param int $post_id ID of the related property post record.
	 *
	 * @return mixed Unchanged custom field data array.
	 */
	public function add_city_to_theme_options( $custom_field_data, $immobilie, $post_id ) {
		if ( 'property_city' === $custom_field_data['meta_key'] ) {
			$theme_cities = get_option( 'reales_cities_settings' );

			$highest_position = 0;
			if ( count( $theme_cities ) > 0 ) {
				foreach ( $theme_cities as $key => $city ) {
					if ( $city['position'] > $highest_position ) $highest_position = $city['position'];
				}
			}

			$city_name = $custom_field_data['meta_value'];
			$city_slug = $this->plugin->string_utils->slugify( $city_name );

			if ( ! isset( $theme_cities[$city_slug] ) ) {
				$highest_position += 10;

				$theme_cities[$city_slug] = array(
					'id' => $city_slug,
					'name' => $city_name,
					'position' => $highest_position
				);

				update_option( 'reales_cities_settings', $theme_cities );
			}
		}

		return $custom_field_data;
	} // add_city_to_theme_options

	/**
	 * Add amenities to theme options (if nonexistent yet) and property (custom fields).
	 *
	 * @since 2.6
	 *
	 * @param mixed $grouped_meta_data Associative array of a meta data group.
	 * @param int $post_id ID of the related property post record.
	 * @param string $meta_key Meta key under which the serialized group array will be stored.
	 *
	 * @return mixed|bool Unchanged grouped meta data or false for amenities group.
	 */
	public function add_amenities( $grouped_meta_data, $post_id, $meta_key ) {
		if ( 'realeswp_amenities' !== $meta_key ) return $grouped_meta_data;

		if ( count( $grouped_meta_data ) > 0 ) {
			$theme_amenities = get_option( 'reales_amenity_settings' );

			$highest_position = 0;
			if ( count( $theme_amenities ) > 0 ) {
				foreach ( $theme_amenities as $key => $amenity ) {
					if ( $amenity['position'] > $highest_position ) $highest_position = $amenity['position'];
				}
			}

			foreach ( $grouped_meta_data as $key => $data ) {
				$amenity_name = $data['value'];
				$amenity_slug = $this->plugin->string_utils->slugify( $amenity_name );

				if ( ! isset( $theme_amenities[$amenity_slug] ) ) {
					$highest_position += 10;

					$theme_amenities[$amenity_slug] = array(
						'name' => $amenity_slug,
						'label' => $amenity_name,
						'icon' => 'fa fa-check',
						'position' => $highest_position
					);

					update_option( 'reales_amenity_settings', $theme_amenities );
				}

				add_post_meta( $post_id, $amenity_slug, '1', true );
			}
		}

		// DON'T save the original data.
		return false;
	} // add_amenities

	/**
	 * Determine/Save the property address and coordinates.
	 *
	 * @since 2.6
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie );
		$lat = false;
		$lng = false;
		$address_publishing_status_logged = false;

		$address = $geodata['address_geocode'];

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

		if ( $geodata['publishing_approved'] ) {
			if ( $geodata['street'] ) add_post_meta( $post_id, 'property_address', $geodata['street'], true );
		} elseif ( ! $address_publishing_status_logged ) {
			$this->plugin->log->add( __( 'Property address NOT approved for publishing', 'immonex-openimmo2wp' ), 'debug' );
		}

		if ( $lat && $lng ) {
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), "$lat, $lng" ), 'debug' );
			add_post_meta( $post_id, 'property_lat', $lat, true );
			add_post_meta( $post_id, 'property_lng', $lng, true );
		}

		if ( isset( $geodata['country_data'] ) ) {
			add_post_meta( $post_id, 'property_country', $geodata['country_data']['Common Name'], true );
		}
	} // save_property_location

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 2.6
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

			if ( ! empty( $floor_plans ) && in_array( $filename, $floor_plans, true ) ) {
				// Remember floor plan attachment ID, exclude from gallery.
				if ( ! isset( $this->temp['property_floor_plans']['ids'][$p->post_parent] ) ) {
					$this->temp['property_floor_plans']['ids'][$p->post_parent] = array();
				}
				$this->temp['property_floor_plans']['ids'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			} elseif ( in_array( strtoupper( $fileinfo['extension'] ), $valid_image_formats ) ) {
				if ( ! isset( $this->temp['post_images'][$p->post_parent] ) ) {
					$this->temp['post_images'][$p->post_parent] = array();
				}
				$this->temp['post_images'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			}
		}
	} // add_attachment_data

	/**
	 * Save property gallery list (attachment IDs as serialized array).
	 *
	 * @since 2.6
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['post_images'][$post_id] ) ) {
			// Save property gallery images.
			$this->temp['post_images'][$post_id] = $this->check_attachment_ids( $this->temp['post_images'][$post_id] );

			if ( count( $this->temp['post_images'][$post_id] ) > 0 ) {
				$gallery_images = '';

				foreach ( $this->temp['post_images'][$post_id] as $att_id ) {
					$gallery_images .= '~~~' . wp_get_attachment_url( $att_id );
				}

				add_post_meta( $post_id, 'property_gallery', $gallery_images, true );
			}

			unset( $this->temp['post_images'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['property_floor_plans']['ids'][$post_id] ) ) {
			// Save property floor plan images.
			$this->temp['property_floor_plans']['ids'][$post_id] = $this->check_attachment_ids( $this->temp['property_floor_plans']['ids'][$post_id] );

			if ( count( $this->temp['property_floor_plans']['ids'][$post_id] ) > 0 ) {
				$floor_plans = '';

				foreach ( $this->temp['property_floor_plans']['ids'][$post_id] as $cnt => $att_id ) {
					$image_src = wp_get_attachment_image_src( $att_id, 'large' );
					$floor_plans .= '~~~' . $image_src[0];
				}

				add_post_meta( $post_id, 'property_plans', $floor_plans, true );
			}

			unset( $this->temp['property_floor_plans']['ids'][$post_id] );
			$this->save_temp_theme_data();
		}
	} // save_attachment_data

	/**
	 * Save default custom fields.
	 *
	 * @since 2.6.1 beta
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_defaults( $post_id, $immobilie ) {
		$default_values = array(
			'property_featured' => ''
		);

		foreach ( $default_values as $meta_key => $meta_value ) {
			if ( ! get_post_meta( $post_id, $meta_key, true ) ) {
				add_post_meta( $post_id, $meta_key, $meta_value, true );
			}
		}
	} // save_defaults

	/**
	 * Try to determine/save the property agent.
	 *
	 * @since 2.6
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

			if ( $user_agent = $this->_get_user_agent( $user->ID ) ) {
				// Save related agent ID and return.
				$this->plugin->log->add( wp_sprintf( __( 'Assigning agent linked to user (ID: %d).', 'immonex-openimmo2wp' ), $user_agent->ID ), 'debug' );
				add_post_meta( $post_id, 'property_agent', $user_agent->ID, true );
				return;
			}
		}

		$agent = $this->get_agent( $immobilie, 'agent', array( 'email' => 'agent_email' ), array(), true );
		if ( $agent ) {
			// Save agent ID.
			add_post_meta( $post_id, 'property_agent', $agent->ID, true );
		}
	} // save_agent

	/**
	 * Decrease number of available properties for agent.
	 *
	 * @since 2.6
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function decrease_available_listings( $post_id, $immobilie ) {
		// Don't decrease the available listings counter on updated properties or agent payment custom field set to 1.
		if (
			is_array( $this->temp['updated_property_ids'] ) &&
			in_array( $post_id, $this->temp['updated_property_ids'] )
		) return;

		$agent_id = get_post_meta( $post_id, 'property_agent', true );

		if ( $agent_id ) {
			// See file libs/save_property.php in Reales WP theme directory for checking the native quota code.
			$reales_membership_settings = get_option( 'reales_membership_settings', '' );
			$payment_type = isset( $reales_membership_settings['reales_paid_field'] ) ? $reales_membership_settings['reales_paid_field'] : false;

			$is_featured = get_post_meta( $post_id, 'property_featured', true );

			if ( 'listing' === $payment_type ) {
				// Update the number of free standard submissions for agent.
				$listing_unlimited = isset( $reales_membership_settings['reales_free_submissions_unlim_field'] ) ? $reales_membership_settings['reales_free_submissions_unlim_field'] : false;
				$agent_free_listings = intval( get_post_meta( $agent_id, 'agent_free_listings', true ) );

				if ( $agent_free_listings > 0 || $listing_unlimited ) {
					if ( ! $listing_unlimited ) {
						update_post_meta( $agent_id, 'agent_free_listings', $agent_free_listings - 1 );
						$this->plugin->log->add( wp_sprintf( __( 'User max. listing count decreased, available listings now: %d', 'immonex-openimmo2wp' ), $agent_free_listings - 1 ), 'debug' );
					}
					update_post_meta( $post_id, 'payment_status', 'paid' );
				} else {
					$updated_property = array( 'ID' => $post_id, 'post_status' => 'pending' );
					wp_update_post( $updated_property );
					$this->plugin->log->add( __( 'Max. number of free listings reached, property post status set to "pending".', 'immonex-openimmo2wp' ), 'debug' );
				}

				if ( $is_featured ) {
					// Update the number of free featured submissions for agent.
					$agent_free_featured_listings = intval( get_post_meta( $agent_id, 'agent_free_featured_listings', true ) );

					if ( $agent_free_featured_listings > 0 ) {
						// Decrease number of available free featured listings for agent.
						update_post_meta( $agent_id, 'agent_free_featured_listings', $agent_free_featured_listings - 1 );
						$this->plugin->log->add( wp_sprintf( __( 'User max. featured listing count decreased, available listings now: %d', 'immonex-openimmo2wp' ), $agent_free_featured_listings - 1 ), 'debug' );
					} else {
						// No more featured listings available, remove featured status.
						delete_post_meta( $post_id, 'property_featured' );
						$this->plugin->log->add( __( 'Maximum number of featured listings reached, featured status removed.', 'immonex-openimmo2wp' ), 'info' );
					}
				}
			} elseif ( 'membership' === $payment_type ) {
				// Update the membership submissions number for agent.
				$plan_unlimited = get_post_meta( $agent_id, 'agent_plan_unlimited', true);
				$agent_plan_listings = intval( get_post_meta( $agent_id, 'agent_plan_listings', true ) );

				if ( ! $plan_unlimited ) {
					update_post_meta( $agent_id, 'agent_plan_listings', $agent_plan_listings - 1 );
					$this->plugin->log->add( wp_sprintf( __( 'User max. listing count decreased, available listings now: %d', 'immonex-openimmo2wp' ), $agent_plan_listings - 1 ), 'debug' );
				}
				update_post_meta( $post_id, 'payment_status', 'paid' );

				if ( $is_featured ) {
					$agent_plan_featured_listings = intval( get_post_meta( $agent_id, 'agent_plan_featured', true ) );

					if ( $agent_plan_featured_listings > 0 ) {
						// Decrease number of available membershiop featured listings for agent.
						update_post_meta( $agent_id, 'agent_plan_featured', $agent_plan_featured_listings - 1 );
						$this->plugin->log->add( wp_sprintf( __( 'User max. featured listing count decreased, available listings now: %d', 'immonex-openimmo2wp' ), $agent_plan_featured_listings - 1 ), 'debug' );
					} else {
						// No more featured listings available, remove featured status.
						delete_post_meta( $post_id, 'property_featured' );
						$this->plugin->log->add( __( 'Maximum number of featured listings reached, featured status removed.', 'immonex-openimmo2wp' ), 'info' );
					}
				}
			}
		}
	} // decrease_available_listings

	/**
	 * Increase number of available properties for agent.
	 *
	 * @since 2.6
	 *
	 * @param string $post_id Property post ID.
	 */
	public function increase_available_listings( $post_id ) {
		$property = get_post( $post_id );
		if ( ! $property || $property->post_type !== $this->plugin->property_post_type ) return;

		$agent_id = get_post_meta( $post_id, 'property_agent', true );

		if ( $agent_id ) {
			// See file libs/save_property.php in Reales WP theme directory for checking the native quota code.
			$agent_payment = get_post_meta( $agent_id, 'agent_payment', true );
			if ( '1' == $agent_payment ) return;

			$reales_membership_settings = get_option( 'reales_membership_settings', '' );
			$payment_type = isset( $reales_membership_settings['reales_paid_field'] ) ? $reales_membership_settings['reales_paid_field'] : '';

			$is_featured = get_post_meta( $post_id, 'property_featured', true );

			if ( 'listing' === $payment_type ) {
				// Update the number of free standard submissions for agent.
				$listing_unlimited = isset( $reales_membership_settings['reales_free_submissions_unlim_field'] ) ? $reales_membership_settings['reales_free_submissions_unlim_field'] : false;
				if ( ! $listing_unlimited && 'publish' === get_post_meta( $post_id, '_wp_trash_meta_status', true ) ) {
					$agent_free_listings = intval( get_post_meta( $agent_id, 'agent_free_listings', true ) );
					update_post_meta( $agent_id, 'agent_free_listings', $agent_free_listings + 1 );
				}

				if ( $is_featured ) {
					$agent_free_featured_listings = intval( get_post_meta( $agent_id, 'agent_free_featured_listings', true ) );
					update_post_meta( $agent_id, 'agent_free_featured_listings', $agent_free_featured_listings + 1 );
				}
			} elseif ( 'membership' === $payment_type ) {
				// Update the number of plan submissions for agent.
				$plan_id = get_post_meta( $agent_id, 'agent_plan', true );
				if ( $plan_id ) {
					$plan_max_listings = get_post_meta( $plan_id, 'membership_submissions_no', true );
					$plan_max_featured_listings = get_post_meta( $plan_id, 'membership_featured_submissions_no', true );
				} else return;

				$plan_unlimited = get_post_meta( $agent_id, 'agent_plan_unlimited', true);

				if ( ! $plan_unlimited ) {
					$agent_plan_listings = intval( get_post_meta( $agent_id, 'agent_plan_listings', true ) );
					if ( $agent_plan_listings < $plan_max_listings ) {
						update_post_meta( $agent_id, 'agent_plan_listings', $agent_plan_listings + 1 );
					}
				}

				if ( $is_featured ) {
					$agent_plan_featured_listings = intval( get_post_meta( $agent_id, 'agent_plan_featured', true ) );
					if ( $agent_plan_featured_listings < $plan_max_featured_listings ) {
						update_post_meta( $agent_id, 'agent_plan_featured', $agent_plan_featured_listings + 1 );
					}
				}
			}
		}
	} // increase_available_listings

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 2.6
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
	 * @since 2.6
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
				'name' => $this->theme_class_slug . '_add_description_content',
				'type' => 'textarea',
				'label' => __( 'Additional content for property descriptions', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'This content will be appended to the main description of every imported property. This is especially useful for <strong>adding widgets by shortcode</strong> (details in the documentation).', 'immonex-openimmo2wp' )
				)
			)
		) );

		return $fields;
	} // extend_fields

	/**
	 * Retrieve an agent post assigned to a user with a given ID.
	 *
	 * @since 2.6
	 * @access private
	 *
	 * @param string $user_id User ID.
	 *
	 * @return WP_Post|bool Agent post object or false if nonexistent.
	 */
	private function _get_user_agent( $user_id ) {
		$args = array(
			'post_type' => 'agent',
			'posts_per_page' => 1,
			'meta_key' => 'agent_user',
			'meta_value' => $user_id
		);
		$user_agent = get_posts( $args );

		return ( 1 === count( $user_agent ) ) ? $user_agent[0] : false;
	} // _get_user_agent

} // class RealesWP
