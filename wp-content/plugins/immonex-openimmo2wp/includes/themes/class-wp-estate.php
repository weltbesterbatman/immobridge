<?php
namespace immonex\OpenImmo2Wp\themes;

use immonex\OpenImmo2Wp\Property_Grouping;

/**
 * WP-Estate-specific processing.
 */
class WP_Estate extends Theme_Base {

	public
		$theme_class_slug = 'wp-estate';

	protected
		$property_post_type = 'estate_property';

	private
		$property_features_list = array();

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 2.0
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->initial_widgets = array(
			'primary-widget-area' => array(
				'immonex_user_defined_properties_widget' => array(
					array(
						'title' => __( 'Energy Pass', 'immonex-openimmo2wp' ),
						'display_mode' => 'include',
						'display_groups' => 'epass',
						'type' => 'name_value',
						'item_div_classes' => ''
					),
					array(
						'title' => __( 'Other Features', 'immonex-openimmo2wp' ),
						'display_mode' => 'exclude',
						'display_groups' => 'epass',
						'type' => 'name_value',
						'item_div_classes' => ''
					)
				)
			)
		);

		$this->temp = array(
			'updated_property_ids' => array(),
			'area_parent_cities' => array(),
			'property_floor_plans' => array(
				'filenames' => array(),
				'ids' => array()
			),
			'area_parent_cities' => array()
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_post_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_grouped_post_meta', array( $this, 'add_custom_meta_details' ), 10, 3 );
		add_filter( 'immonex_oi2wp_add_property_custom_field', array( $this, 'add_meta_value_suffixes' ), 10, 3 );
		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );
		add_filter( 'immonex_oi2wp_insert_taxonomy_term', array( $this, 'add_area_parent' ), 20, 2 );

		if ( $this->theme_options['disable_full_imports'] ) {
			add_filter( 'immonex_oi2wp_xml_data_before_import', array( $this, 'disable_full_imports' ) );
		}

		add_action( 'immonex_oi2wp_start_import_process', array( $this, 'populate_property_features_list' ) );
		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'maybe_update_sub_units' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'do_final_property_processing_steps' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 15, 2 );
		add_action( 'inveris_base_before_post_reset', array( $this, 'delete_floor_plans' ) );
		add_action( 'immonex_oi2wp_before_property_post_deletion', array( $this, 'delete_floor_plans' ) );
		add_action( 'wp_trash_post', array( $this, 'delete_floor_plans' ) );
		add_action( 'immonex_oi2wp_import_file_processed', array( $this, 'save_area_parents_and_update_markers' ) );

		if ( $this->theme_options['user_listing_quotas'] ) {
			add_filter( 'immonex_oi2wp_property_xml_before_import', array( $this, 'check_listing_quota' ) );
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'decrease_available_listings' ), 20, 2 );
			add_action( 'before_delete_post', array( $this, 'increase_available_listings' ) );
		}

		if ( $this->theme_options['add_every_property_to_slider'] ) {
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'add_property_to_slider' ), 10, 2 );
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

		$user = $this->get_agent_user( $immobilie, array( 'role' => 'subscriber' ), false );
		$quota_check_function_prefix = version_compare( $this->theme_version, '4.0', '>=' ) ? 'wpestate_' : '';

		if ( $user && function_exists( $quota_check_function_prefix . 'get_remain_listing_user' ) ) {
			$existing_properties = $this->plugin->get_property_by_openimmo_obid( (string) $immobilie->verwaltung_techn->openimmo_obid, true );
			if ( count( $existing_properties ) > 0 ) {
				// Property to be updated or deleted found, ignore quota.
				$this->temp['updated_property_ids'][] = $existing_properties[0]->ID;
				$this->save_temp_theme_data();
				return $immobilie;
			}

			$package_id = get_user_meta( $user->ID, 'package_id', true );
			$unlimited_listings = $package_id ? get_post_meta( $package_id, 'mem_list_unl', true ) : false;
			$listings_available = call_user_func( $quota_check_function_prefix . 'get_remain_listing_user', $user->ID, $package_id ? $package_id : '' );

			if ( $package_id && $listings_available == 0 && ! $unlimited_listings ) {
				// Property quota for this user exceeded: skip property.
				$this->plugin->log->add( __( 'Maximum number of properties reached, skipping property.', 'immonex-openimmo2wp' ), 'info' );
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
	 * Set post status to "pending" if admin review is enabled.
	 *
	 * @since 2.0
	 *
	 * @param array $post_data Current post data.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return array (Possibly) modified property data to store as post record.
	 */
	public function add_post_content( $post_data, $immobilie ) {
		if ( trim( $this->theme_options['add_description_content'] ) ) {
			$post_data['post_content'] .= "\n" . $this->theme_options['add_description_content'];
		}

		if ( ! isset( $post_data['post_excerpt'] ) || ! $post_data['post_excerpt'] ) {
			$post_data['post_excerpt'] = $this->plugin->string_utils->get_excerpt( $post_data['post_content'], 120, '...' );
		}

		if ( 'yes' === get_option( 'wp_estate_admin_submission' ) ) {
			// Set post status to "pending" if admin review is enabled.
			$post_data['post_status'] = 'pending';
		}

		return $post_data;
	} // add_post_content

	/**
	 * Convert and save theme custom data.
	 *
	 * @since 2.0
	 *
	 * @param array $grouped_meta_data Associative array of a meta data group.
	 * @param int $post_id ID of the related property post record.
	 * @param string $meta_key Meta key under which the serialized group array will be stored.
	 *
	 * @return bool Always false.
	 */
	public function add_custom_meta_details( $grouped_meta_data, $post_id, $meta_key ) {
		if ( 'wpestate_amenities_features' !== $meta_key ) return $grouped_meta_data;

		if ( count( $grouped_meta_data ) > 0 ) {
			foreach ( $grouped_meta_data as $key => $data ) {
				$this->_maybe_add_to_property_features_list( $data['value'] );
				if ( version_compare( $this->theme_version, '4.0', '>=' ) ) {
					$property_meta_key = sanitize_key( substr( sanitize_title( str_replace( ' ', '_', trim( $data['value'] ) ) ), 0, 45 ) );
				} else {
					$property_meta_key = str_replace( ' ', '_', trim( $data['value'] ) );
				}

				add_post_meta( $post_id, $property_meta_key, '1', true );
			}
		}

		// DON'T save the original data.
		return false;
	} // add_custom_meta_details

	/**
	 * Extend specific meta data values.
	 *
	 * @since 2.0
	 *
	 * @param array $data Custom field data.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param int $post_id Property Post ID.
	 *
	 * @return array Maybe updated custom field data.
	 */
	public function add_meta_value_suffixes( $data, $immobilie, $post_id ) {
		if (
			isset( $data['mapping_destination'] ) &&
			in_array( $data['mapping_destination'], array( 'property_garage_size', 'property-garage-size' ) )
		) {
			$append_suffix = apply_filters( $this->plugin->plugin_prefix . 'append_meta_value_suffix', _n( 'Car Space', 'Car Spaces', (int) $data['meta_value'], 'immonex-openimmo2wp' ), $data, $immobilie, $post_id );
			if ( $append_suffix ) $data['meta_value'] .= ' ' . $append_suffix;
		}

		return $data;
	} // add_meta_value_suffixes

	/**
	 * Check if attachment is a video URL (YouTube/Vimeo) or a floor plan and perform
	 * the related processing steps.
	 *
	 * @since 2.0
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

			$video = $this->plugin->string_utils->is_video_url( $url );

			if ( $video ) {
				// Save video type and ID as post meta.
				add_post_meta( $post_id, 'embed_video_type', $video['type'], true );
				add_post_meta( $post_id, 'embed_video_id', $video['id'], true );

				// DON'T import this attachment.
				return false;
			} elseif ( $this->plugin->string_utils->is_virtual_tour_url( $url ) ) {
				// Save virtual tour embed code.
				$embed_code = $this->get_virtual_tour_embed_code( apply_filters( $this->plugin->plugin_prefix . 'virtual_tour_embed_code_args', array( 'url' => $url ), 5 ) );
				add_post_meta( $post_id, 'embed_virtual_tour', $embed_code, true );

				// DON'T import this attachment.
				return false;
			}
		} elseif (
			version_compare( $this->theme_version, '4.0', '>=' ) &&
			in_array( (string) $attachment['gruppe'], array( 'GRUNDRISS' ) ) // DEPRECATED: 'KARTEN_LAGEPLAN'
		) {
			// Floor plans are supported in theme version 4.0.0 and higher.
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
	 * @since 2.0
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
				// City found, remember area name and city term name for later processing.
				$this->temp['area_parent_cities'][$term_data['term_value']] = $parent_city_term['name'];
				$this->save_temp_theme_data();
			}
		}

		return $term_data;
	} // add_area_parent

	/**
	 * Stop current import if scope is "full".
	 *
	 * @since 2.0
	 *
	 * @param SimpleXMLElement $openimmo_xml Full OpenImmo data of current XML file.
	 *
	 * @return SimpleXMLElement|bool Unchanged XML data or false on full imports.
	 */
	public function disable_full_imports( $openimmo_xml ) {
		if ( 'VOLL' == $openimmo_xml->uebertragung['umfang'] ) {
			$openimmo_xml = false;
			$this->plugin->log->add( __( 'Full imports are not permitted. Import of this file will be skipped.', 'immonex-openimmo2wp' ), 'error' );
		}

		return $openimmo_xml;
	} // disable_full_imports

	/**
	 * Save the property address and/or coordinates (post meta) for geocoding.
	 *
	 * @since 2.0
	 *
	 * @param string $post_id Property ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie );
		$geo_coordinates = false;

		if ( $geodata['publishing_approved'] ) {
			if ( $geodata['street'] ) {
				add_post_meta( $post_id, 'property_address', $geodata['street'], true );
			}

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
			$geo_coordinates = $this->geocode( $geodata['address_geocode'], true, $geodata['country_code_iso2'], $post_id );
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
			$this->plugin->log->add( wp_sprintf( __( 'Property address NOT approved for publishing, term used for geocoding: %s', 'immonex-openimmo2wp' ), $geodata['address_geocode'] ), 'debug' );
		}

		if ( $geo_coordinates && is_array( $geo_coordinates ) ) {
			add_post_meta( $post_id, 'property_latitude', $geo_coordinates['lat'], true );
			add_post_meta( $post_id, 'property_longitude', $geo_coordinates['lng'], true );
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), $geo_coordinates['lat'] . ', ' . $geo_coordinates['lng'] ), 'debug' );
		} elseif ( isset( $geocoding_failed ) && $geocoding_failed ) {
			$geocoding_status = $this->get_geocoding_status( $geodata['address_geocode'], $geodata['country_code_iso2'] );
			$this->plugin->log->add( wp_sprintf( __( 'Geocoding failed (%s)', 'immonex-openimmo2wp' ), $geocoding_status ? $geocoding_status : __( 'unknown reason', 'immonex-openimmo2wp' ) ), 'debug' );
		}

		if ( isset( $geodata['postcode'] ) ) {
			add_post_meta( $post_id, 'property_zip', $geodata['postcode'], true );
		}

		if ( isset( $geodata['country_data']['Common Name'] ) && $geodata['country_data']['Common Name'] ) {
			add_post_meta( $post_id, 'property_country', __( $geodata['country_data']['Common Name'], 'wpestate' ), true );
		}

		add_post_meta( $post_id, 'hidden_address', $geodata['address_output_incl_country'], true );
	} // save_property_location

	/**
	 * Try to determine/save the property agent.
	 *
	 * @since 2.0
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

		$args = array( 'role' => 'subscriber' );
		$user = $this->get_agent_user( $immobilie, $args, true, $author_id );

		if ( $user ) {
			// Set user as author and assign it to property.
			$this->update_post_author( $post->ID, $user->ID );
			add_post_meta( $post_id, 'property_user', $user->ID, true );

			$user_agent_id = get_user_meta( $user->ID, 'user_agent_id', true );
			// Save related agent ID if given.
			if ( $user_agent_id ) {
				$this->plugin->log->add( wp_sprintf( __( 'Assigning agent linked to user, ID: %d', 'immonex-openimmo2wp' ), $user_agent_id ), 'debug' );
				add_post_meta( $post_id, 'property_agent', $user_agent_id, true );
				return;
			}
		}

		$agent = $this->get_agent( $immobilie, 'estate_agent', array( 'email' => 'agent_email' ), array(), true );
		if ( $agent ) {
			// Save agent ID.
			add_post_meta( $post_id, 'property_agent', $agent->ID, true );
		}
	} // save_agent

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

		$quota_check_function_prefix = version_compare( $this->theme_version, '4.0', '>=' ) ? 'wpestate_' : '';

		if (
			! function_exists( $quota_check_function_prefix . 'get_remain_listing_user' ) ||
			! function_exists( $quota_check_function_prefix . 'get_remain_featured_listing_user' ) ||
			! function_exists( $quota_check_function_prefix . 'update_listing_no' ) ||
			! function_exists( $quota_check_function_prefix . 'update_featured_listing_no' )
		) {
			// Theme quota check functions not available: skip this step.
			$this->plugin->log->add( __( "Check functions missing, current user quotas can't be updated.", 'immonex-openimmo2wp' ), 'error' );
			return;
		}

		$user_id = get_post_meta( $post_id, 'property_user', true );

		if ( $user_id ) {
			$package_id = get_user_meta( $user_id, 'package_id', true );
			$unlimited_listings = $package_id ? get_post_meta( $package_id, 'mem_list_unl', true ) : false;

			$listings_available = call_user_func( $quota_check_function_prefix . 'get_remain_listing_user', $user_id, $package_id ? $package_id : '' );
			$featured_listings_available =  call_user_func( $quota_check_function_prefix . 'get_remain_featured_listing_user', $user_id );

			if ( (int) $listings_available > -1 && ! $unlimited_listings ) {
				// Decrease number of available (normal) listings for user.
				call_user_func( $quota_check_function_prefix . 'update_listing_no', $user_id );
				$this->plugin->log->add( wp_sprintf( __( 'User max. listing count decreased, available listings now: %d', 'immonex-openimmo2wp' ), $listings_available ), 'debug' );
			}

			$is_featured = get_post_meta( $post_id, 'prop_featured', true );
			if ( $is_featured && $featured_listings_available > 0 ) {
				// Decrease number of available featured listings for user.
				update_featured_listing_no( $user_id );
				$this->plugin->log->add( wp_sprintf( __( 'User max. featured listing count decreased, available listings now: %d', 'immonex-openimmo2wp' ), $featured_listings_available ), 'debug' );
			} elseif ( $is_featured ) {
				// No more featured listings available, remove featured status.
				delete_post_meta( $post_id, 'prop_featured' );
				$this->plugin->log->add( __( 'Maximum number of featured listings reached, featured status removed.', 'immonex-openimmo2wp' ), 'info' );
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
		if ( ! $property || $property->post_type !== $this->property_post_type ) return;

		$user_id = get_post_meta( $post_id, 'property_user', true );
		if ( $user_id ) {
			$package_id = get_user_meta( $user_id, 'package_id', true );
			if ( $package_id ) {
				$unlimited_listings = get_post_meta( $package_id, 'mem_list_unl', true );
				if ( $unlimited_listings )
					$package_max_listings = -1;
				else
					$package_max_listings = get_post_meta( $package_id, 'pack_listings', true );

				$package_max_featured_listings = get_post_meta( $package_id, 'pack_featured_listings', true );
			} else {
				// User with free subscription.
				$free_unlimited_listings = get_option( 'wp_estate_free_mem_list_unl', false );
				if ( $free_unlimited_listings )
					$package_max_listings = -1;
				else
					$package_max_listings = intval( get_option( 'wp_estate_free_mem_list', 0 ) );

				$package_max_featured_listings = intval( get_option( 'wp_estate_free_feat_list', 0 ) );
			};

			$listings_available = wpestate_get_remain_listing_user( $user_id, $package_id ? $package_id : '' );
			if ( $listings_available < $package_max_listings ) {
				// Increase max. number of available (normal) listings for user (if not unlimited).
				update_user_meta( $user_id, 'package_listings', $listings_available + 1, $listings_available );
			}

			$is_featured = get_post_meta( $post_id, 'prop_featured', true );
			if ( $is_featured ) {
				$featured_listings_available = wpestate_get_remain_featured_listing_user( $user_id );
				if ( $featured_listings_available < $package_max_featured_listings ) {
					// Increase max. number of available featured listings for user.
					update_user_meta( $user_id, 'package_featured_listings', $featured_listings_available + 1, $featured_listings_available );
				}
			}
		}
	} // increase_available_listings

	/**
	 * Delete associated floor plan images.
	 *
	 * @since 3.4
	 *
	 * @param int $post_id Property Post ID.
	 */
	public function delete_floor_plans( $post_id ) {
		$post = get_post( $post_id );
		if ( $post->post_type !== $this->property_post_type ) return;

		$floor_plans = get_post_meta( $post_id, 'plan_image_attach', true );

		if ( is_array( $floor_plans ) && count( $floor_plans ) > 0 ) {
			foreach ( $floor_plans as $att_id ) {
				if ( ! wp_delete_attachment( $att_id, true ) ) {
					$this->plugin->log->add( wp_sprintf( __( 'Error on deleting a floor plan image attachment: Attachment ID %s', 'immonex-openimmo2wp' ), $att_id ), 'error' );
				}
			}
		}
	} // delete_floor_plans

	/**
	 * Save parent cities of area taxonomy terms and update Google Map marker pins.
	 *
	 * @since 2.0
	 *
	 * @param string $filename Name of processed file.
	 */
	public function save_area_parents_and_update_markers( $filename ) {
		if ( ! empty( $this->temp['area_parent_cities'] ) ) {
			foreach ( $this->temp['area_parent_cities'] as $area_term_name => $area_parent_city_name ) {
				$area_term = apply_filters( $this->plugin->plugin_prefix . 'term_multilang', array(), $area_term_name, 'property_area' );

				if ( $area_term ) {
					$option_name = 'taxonomy_' . $area_term['term_id'];
					$option_value = array( 'cityparent' => $area_parent_city_name );
					add_option( $option_name, $option_value );
				}
			}
		}

		if (
			function_exists( 'wpestate_cron_generate_pins' ) &&
			apply_filters( $this->plugin->plugin_prefix . 'wpestate_generate_pins_after_every_import', false )
		) {
			// Update Google Map marker pins if file based display is enabled.
			wpestate_cron_generate_pins();
		}
	} // save_area_parents_and_update_markers

	/**
	 * Add the main property image to the start page slider.
	 *
	 * @since 3.4
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function add_property_to_slider( $post_id, $immobilie ) {
		$properties_in_slider = get_option( 'wp_estate_theme_slider', array() );
		if ( ! is_array( $properties_in_slider ) ) {
			$properties_in_slider = array();
		}

		if ( ! in_array( $post_id, $properties_in_slider ) ) {
			$properties_in_slider[] = $post_id;
			update_option( 'wp_estate_theme_slider', $properties_in_slider );
		}

		add_post_meta( $post_id, 'property_theme_slider', '1', true );
	} // add_property_to_slider

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 2.4
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

			if ( ! empty( $floor_plans ) &&	in_array( $filename, $floor_plans, true )	) {
				// Remember floor plan attachment ID, exclude from gallery.
				if ( ! isset( $this->temp['property_floor_plans']['ids'][$p->post_parent] ) ) {
					$this->temp['property_floor_plans']['ids'][$p->post_parent] = array();
				}
				$this->temp['property_floor_plans']['ids'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			}
		}
	} // add_attachment_data

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
					add_post_meta( $post_id, 'property_subunits_list_manual', implode( ',', $group_children_ids ) );
					add_post_meta( $post_id, 'property_has_subunits', 1 );
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
					$theme_children_id_string = get_post_meta( $group_parent_id, 'property_subunits_list_manual', true );
					$theme_children_ids = $theme_children_id_string ?
						array_unique( array_map( 'trim', explode( ',', $theme_children_id_string ) ) ) :
						array();

					if ( ! in_array( $post_id, $theme_children_ids ) ) {
						$theme_children_ids[] = $post_id;
						update_post_meta(
							$group_parent_id,
							'property_subunits_list_manual',
							implode( ',', $theme_children_ids )
						);
						update_post_meta( $group_parent_id, 'property_has_subunits', 1 );
					}
				}
			}
		}
	} // maybe_update_sub_units

	/**
	 * Do the final processing steps for a property object (e.g. save
	 * additional and default property options).
	 *
	 * @since 2.0
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return array Extended tabs array.
	 */
	public function do_final_property_processing_steps( $post_id, $immobilie ) {
		global $wpdb;

		$default_values = array(
			'property_status' => 'normal',
			'property_price' => '',
			'prop_featured' => '',
			'header_type' => '0', // "global"
			'header_transparent' => 'global',
			'local_pgpr_slider_type' => 'global',
			'local_pgpr_content_type' => 'global',
			'use_floor_plans' => ! empty( $this->temp['property_floor_plans']['ids'][$post_id] ) ? '1' : '0',
			'post_show_title' => 'yes'
		);

		$theme_options = array(
			'sidebar_option' => $this->theme_options['sidebar_option'],
			'sidebar_select' => $this->theme_options['sidebar_select'],
			'page_custom_zoom' => $this->theme_options['google_map_zoom_level'],
			'property_google_view' => $this->theme_options['google_street_view'],
			'google_camera_angle' => $this->theme_options['google_street_view_camera_angle']
		);

		foreach ( array_merge( $default_values, $theme_options ) as $meta_key => $meta_value ) {
			if ( ! get_post_meta( $post_id, $meta_key, true ) ) {
				add_post_meta( $post_id, $meta_key, $meta_value, true );
			}
		}

		if (
			version_compare( $this->theme_version, '4.0', '>=' ) &&
			! empty( $this->temp['property_floor_plans']['ids'][$post_id] )
		) {
			// Save floor plan data.
			$plan_image_attach = $this->temp['property_floor_plans']['ids'][$post_id];
			$plan_image = array();
			$plan_title = array();

			foreach ( $this->temp['property_floor_plans']['ids'][$post_id] as $cnt => $att_id ) {
				$attachment = wp_prepare_attachment_for_js( $att_id );
				$attachment_image = wp_get_attachment_image_src( $att_id, 'large' );
				$plan_image[] = $attachment_image[0];
				$plan_title[] = $attachment['title'];

				// Unlink attachment from property post (exclude from gallery).
				// Use a direct update query to bypass unneccessary theme filters.
				$wpdb->update( $wpdb->posts, array( 'post_parent' => 0 ), array( 'ID' => $att_id ) );
			}

			update_post_meta( $post_id, 'plan_image_attach', $plan_image_attach );
			update_post_meta( $post_id, 'plan_image', $plan_image );
			update_post_meta( $post_id, 'plan_title', $plan_title );
		}

		/**
		 * Add default Google Map marker image (none.png) as option for property status/category if it
		 * doesn't exist yet. (Otherwise, no marker pin would be displayed for the current property.)
		 */
		$statuses = wp_get_post_terms( $post_id, 'property_action_category', array( 'fields' => 'slugs' ) );
		if ( count( $statuses ) > 0 ) $status = $statuses[0]; else $status = '';

		$categories = wp_get_post_terms( $post_id, 'property_category', array( 'fields' => 'slugs' ) );
		if ( count( $categories ) > 0 ) $category = $categories[0]; else $category = '';


		if ( $status || $category ) {
			$pin_marker_option_name = 'wp_estate_' . $category . $status;
			$pin_marker_option = get_option( $pin_marker_option_name );
			if ( ! $pin_marker_option ) {
				update_option( $pin_marker_option_name, get_template_directory_uri() . '/css/css-images/none.png' );
			}
		}

		if (
			version_compare( $this->theme_version, '4.0', '>=' ) &&
			function_exists( 'wpestate_update_hiddent_address_single' )
		) {
			wpestate_update_hiddent_address_single( $post_id );
		}
	} // do_final_property_processing_steps

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 2.0
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
	 * @since 2.0
	 *
	 * @param array $fields Original fields array.
	 *
	 * @return array Extended fields array.
	 */
	public function extend_fields( $fields ) {
		$options = array(
			'sidebar_option' => array(
				'global' => __( 'global', 'immonex-openimmo2wp' ),
				'right' => __( 'right', 'immonex-openimmo2wp' ),
				'left' => __( 'left', 'immonex-openimmo2wp' ),
				'none' => __( 'none', 'immonex-openimmo2wp' )
			)
		);

		$options['sidebar_select'][''] = __( 'global', 'immonex-openimmo2wp' );
		foreach ( $GLOBALS['wp_registered_sidebars'] as $sidebar ) {
			$options['sidebar_select'][$sidebar['id']] = ucwords( $sidebar['name'] );
		}

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
				'name' => $this->theme_class_slug . '_add_every_property_to_slider',
				'type' => 'checkbox',
				'label' => __( 'Add every imported property to the theme slider', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Requires theme version 4.0.0 or higher.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_disable_full_imports', // DEPRECATED
				'type' => 'checkbox',
				'label' => __( 'Disable Full Imports', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Activate this option if multiple agents/agencies import their data into a single WP site.', 'immonex-openimmo2wp' ) .
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
					),
					'no_sanitize' => true
				),
			),
			array(
				'name' => $this->theme_class_slug . '_sidebar_option',
				'type' => 'select',
				'label' => __( 'Sidebar placement', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => '',
					'options' => $options['sidebar_option']
				)
			),
			array(
				'name' => $this->theme_class_slug . '_sidebar_select',
				'type' => 'select',
				'label' => 'Sidebar',
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => '',
					'options' => $options['sidebar_select']
				)
			),
			array(
				'name' => $this->theme_class_slug . '_google_map_zoom_level',
				'type' => 'text',
				'label' => __( 'Google Map Zoom Level', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Default zoom level for Google Maps on detail pages of imported properties (1 - 20)', 'immonex-openimmo2wp' ),
					'class' => 'short-text',
					'min' => 1,
					'under_min_default' => 16,
					'max' => 20
				)
			),
			array(
				'name' => $this->theme_class_slug . '_google_street_view',
				'type' => 'checkbox',
				'label' => __( 'Enable Google Street View', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Enable Google Street View for every imported property.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_google_street_view_camera_angle',
				'type' => 'text',
				'label' => __( 'Google Street View Camera Angle', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Camera angle/heading to use for Google Street View on property detail pages (0 - 360).', 'immonex-openimmo2wp' ),
					'class' => 'short-text',
					'field_suffix' => __( 'Degrees', 'immonex-openimmo2wp' ),
					'min' => 0,
					'max' => 360
				)
			)
		) );

		return $fields;
	} // extend_fields

	/**
	 * Fetch current list of possible property features.
	 *
	 * @since 2.0
	 */
	public function populate_property_features_list() {
		$features_string = get_option( 'wp_estate_feature_list' );

		if ( $features_string ) {
			$this->property_features_list = array_map( 'trim', explode( ',', $features_string ) );
		}
	} // populate_property_features_list

	/**
	 * Add property feature to global list (theme option) if it doesn't exist yet.
	 *
	 * @since 2.0
	 * @access private
	 */
	private function _maybe_add_to_property_features_list( $feature ) {
		if ( ! in_array( trim( $feature ), $this->property_features_list ) ) {
			$this->property_features_list[] = trim( $feature );
		}

		$this->_save_property_features_list();
	} // _maybe_add_to_property_features_list

	/**
	 * Save the current global list of possible property features as theme option.
	 *
	 * @since 2.0
	 * @access private
	 */
	private function _save_property_features_list() {
		if ( count( $this->property_features_list ) > 0 ) {
			update_option( 'wp_estate_feature_list', implode( ",\n", $this->property_features_list ), true );
		}
	} // _save_property_features_list

} // class WP_Estate
