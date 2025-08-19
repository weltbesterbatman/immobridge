<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * Zoner-specific processing.
 */
class Zoner extends Theme_Base {

	public
		$theme_class_slug = 'zoner';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 2.8
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->initial_widgets = array(
			'property' => array(
				'immonex_user_defined_properties_widget' => array(
					array(
						'title' => __( 'Energy Pass', 'immonex-openimmo2wp' ),
						'display_mode' => 'include',
						'display_groups' => 'epass',
						'type' => 'name_value',
						'item_div_classes' => ''
					),
					array(
						'title' => __( 'Further Details', 'immonex-openimmo2wp' ),
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
			'property_floor_plans' => array(
				'filenames' => array(),
				'ids' => array()
			),
			'property_video_urls' => array(),
			'property_file_attachments' => array(),
			'gallery_images' => array()
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_post_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );

		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_defaults' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 15, 2 );

		if ( $this->theme_options['user_listing_quotas'] ) {
			add_filter( 'immonex_oi2wp_property_xml_before_import', array( $this, 'check_listing_quota' ) );
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'check_featured_quota' ), 20, 2 );
		}
	} // __construct

	/**
	 * Check for available property related users and their package quotas.
	 *
	 * @since 2.8
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

			if ( ! $this->_zoner_is_user_limit_properties( $user->ID ) ) {
				// Property quota for this user exceeded: skip property.
				$this->plugin->log->add( __( 'Maximum number of properties reached, skipping property.', 'immonex-openimmo2wp' ), 'info' );
				return false;
			}
		}

		return $immobilie;
	} // check_listing_quota

	/**
	 * Check user quota for featured properties, remove featured status if exceeded.
	 *
	 * @since 2.8
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function check_featured_quota( $post_id, $immobilie ) {
		// Don't decrease the available listings counter on updated properties or agent payment custom field set to 1.
		if (
			is_array( $this->temp['updated_property_ids'] ) &&
			in_array( $post_id, $this->temp['updated_property_ids'] )
		) return;

		$is_featured = get_post_meta( $post_id, '_zoner_is_featured', true );
		if ( ! $is_featured ) return;

		$post = get_post( $post_id );
		$user_id = $post && $post->post_author ? $post->post_author : false;

		if ( ! $this->_zoner_is_user_limit_featured_properties( $user_id ) ) {
			delete_post_meta( $post_id, '_zoner_is_featured' );
			$this->plugin->log->add( __( 'Maximum number of featured listings reached, featured status removed.', 'immonex-openimmo2wp' ), 'info' );
		}
	} // check_featured_quota

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 * Shorten excerpt if needed.
	 *
	 * @since 2.8
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
	 * @since 2.8
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

			if ( $video || 'FILMLINK' === (string) $attachment['gruppe'] ) {
				// Attachment is an URL of an external video, remember its URL for later processing.
				if ( ! isset( $this->temp['property_video_urls'][$post_id] ) ) {
					$this->temp['property_video_urls'][$post_id] = array();
				}
				$this->temp['property_video_urls'][$post_id][] = $url;
				$this->save_temp_theme_data();

				// No further processing of video URLs.
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
				// Attachment ist a floor plan image, remember its filename for later processing.
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
	 * @since 2.8
	 *
	 * @param string $post_id Property ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie );
		$lat = false;
		$lng = false;
		$address_publishing_status_logged = false;

		if ( $geodata['publishing_approved'] ) {
			add_post_meta( $post_id, '_zoner_address', $geodata['street'], true );
		}

		if ( isset( $geodata['country_data']['ISO 3166-1 2 Letter Code'] ) ) {
			// Save the property country as 2 letter ISO code.
			add_post_meta( $post_id, '_zoner_country', strtoupper( $geodata['country_data']['ISO 3166-1 2 Letter Code'] ), true );
		}

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

		if ( ! $geodata['publishing_approved'] && ! $address_publishing_status_logged ) {
			$this->plugin->log->add( __( 'Property address NOT approved for publishing', 'immonex-openimmo2wp' ), 'debug' );
		}

		if ( $lat && $lng ) {
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), "$lat, $lng" ), 'debug' );
			add_post_meta( $post_id, '_zoner_lat', $lat, true );
			add_post_meta( $post_id, '_zoner_lng', $lng, true );
			add_post_meta( $post_id, '_zoner_geo_location', array( 'lat' => $lat, 'lng' => $lng ), true );
		}
	} // save_property_location

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 2.8
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
				// Regular image attachment (gallery).
				if ( ! isset( $this->temp['gallery_images'][$p->post_parent] ) ) {
					$this->temp['gallery_images'][$p->post_parent];
				}
				$this->temp['gallery_images'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			}
		}
	} // add_attachment_data

	/**
	 * Save attachment related data.
	 *
	 * @since 2.8
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['gallery_images'][$post_id] )	) {
			// Save property gallery list (attachment IDs as serialized array).
			$this->temp['gallery_images'][$post_id] = $this->check_attachment_ids( $this->temp['gallery_images'][$post_id] );

			if ( count( $this->temp['gallery_images'][$post_id] ) > 0 ) {
				$gallery = array();
				foreach ( $this->temp['gallery_images'][$post_id] as $att_id ) {
					$gallery[$att_id] = wp_get_attachment_url( $att_id );
				}

				// Save gallery images as associative array.
				add_post_meta( $post_id, '_zoner_gallery', $gallery, true );
				unset( $this->temp['gallery_images'][$post_id] );
				$this->save_temp_theme_data();
			}
		}

		if ( ! empty( $this->temp['property_file_attachments'][$post_id] ) ) {
			$this->temp['property_file_attachments'][$post_id] = $this->check_attachment_ids( $this->temp['property_file_attachments'][$post_id] );

			if ( count( $this->temp['property_file_attachments'][$post_id] ) > 0 ) {
				$files = array();
				foreach ( $this->temp['property_file_attachments'][$post_id] as $att_id ) {
					$files[$att_id] = wp_get_attachment_url( $att_id );
				}

				// Save file attachments as associative array.
				add_post_meta( $post_id, '_zoner_files', $files, true );
				unset( $this->temp['property_file_attachments'][$post_id] );
				$this->save_temp_theme_data();
			}
		}

		if ( ! empty( $this->temp['property_floor_plans']['ids'][$post_id] ) ) {
			$this->temp['property_floor_plans']['ids'][$post_id] = $this->check_attachment_ids( $this->temp['property_floor_plans']['ids'][$post_id] );

			if ( count( $this->temp['property_floor_plans']['ids'][$post_id] ) > 0 ) {
				$plans = array();
				foreach ( $this->temp['property_floor_plans']['ids'][$post_id] as $att_id ) {
					$plans[$att_id] = wp_get_attachment_url( $att_id );
				}

				// Save floor plan images as associative array.
				add_post_meta( $post_id, '_zoner_plans', $plans, true );
				unset( $this->temp['property_floor_plans']['filenames'][$post_id] );
				unset( $this->temp['property_floor_plans']['ids'][$post_id] );
				$this->save_temp_theme_data();
			}
		}

		if ( ! empty( $this->temp['property_video_urls'][$post_id] ) ) {
			$videos = array();
			foreach ( $this->temp['property_video_urls'][$post_id] as $url ) {
				$videos[] = array( '_zoner_link_video' => $url );
			}

			// Save property video URLs as associative array.
			add_post_meta( $post_id, '_zoner_videos', $videos, true );
			unset( $this->temp['property_video_urls'][$post_id] );
			$this->save_temp_theme_data();
		}
	} // save_attachment_data

	/**
	 * Save additional (default) property meta data.
	 *
	 * @since 2.8
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_defaults( $post_id, $immobilie ) {
		$is_sale = 'true' == strtolower( (string) $immobilie->objektkategorie->vermarktungsart['KAUF'] ) ||	'1' == (string) $immobilie->objektkategorie->vermarktungsart['KAUF'];

		// Meta key => default value.
		$default_values = array(
			'_zoner_country' => 'DE',
			'_zoner_show_on_map' => 'on',
			'_zoner_condition' => 0,
			'_zoner_payment' => $is_sale ? 0 : 1, // Select monthly payment for rentals.
			'_zoner_currency' => 'EUR',
			'_zoner_price_format' => 1
		);

		if ( $this->theme_options['allow_user_rating'] ) $default_values['_zoner_allow_raiting'] = 'on';

		$default_values = apply_filters( $this->plugin->plugin_prefix . $this->theme_class_slug . '_defaults', $default_values );

		foreach ( $default_values as $meta_key => $value ) {
			add_post_meta( $post_id, $meta_key, $value, true );
		}
	} // save_defaults

	/**
	 * Try to determine/save the property agent.
	 *
	 * @since 2.8
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
		}
	} // save_agent

	/**
	 * Check if the current number or a user's property posts is within the related package limits.
	 * (derived from theme source method in .../themes/zoner/includes/admin/classes/zoner.class-membership.php)
	 *
	 * @since 2.8
	 *
	 * @param int|string $user_id ID of the WP user to check.
	 *
	 * @return bool Property count within user/package limits?
	 */
	private function _zoner_is_user_limit_properties( $user_id ) {
		global $zoner, $zoner_config, $prefix, $wpdb;

		if ( ! isset( $zoner ) || ! $zoner || ! isset( $zoner_config ) || ! $zoner_config ) {
			$this->plugin->log->add( __( "Zoner theme not available, user's property quota cant't be checked.", 'immonex-openimmo2wp' ), 'error' );
			return false;
		}

		$is_user_property_not_limit = false;

		$curr_user_role = $this->_zoner_get_current_user_role( $user_id );
		$curr_user_id = $user_id;
		$package_id = get_user_meta( $curr_user_id, $prefix . 'package_id', true );
		$valid_thru = get_user_meta( $curr_user_id, $prefix . 'valid_thru', true );

		$pack_property_limit = -1;
		$user_property_limit = -1;

		$full_list_user_property = array(
			'post_type' => 'property',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'author' => $curr_user_id
		);

		$property_found = new \WP_Query( $full_list_user_property );
		$user_property_limit = (int) $property_found->found_posts;

		if ( $package_id ) {
			$package_info = $zoner->membership->zoner_get_package_info_by_id( $package_id );

			if ( $package_info->is_unlim_properties === 'off' ) {
				$pack_property_limit = (int) $package_info->limit_properties;
			} else {
				$pack_property_limit = 0;
			}

			if ( ! empty( $valid_thru ) && ( $valid_thru >= current_time( 'mysql' ) ) ) {
				if ( $pack_property_limit == 0 ) {
					$is_user_property_not_limit = true;
				} else {
					if ( $pack_property_limit > $user_property_limit ) {
						$is_user_property_not_limit = true;
					}
				}
			} else {
				if ( $pack_property_limit == 0 ) {
					$is_user_property_not_limit = true;
				} else {
					if ( $pack_property_limit > $user_property_limit )
						$is_user_property_not_limit = true;
				}
			}
		} else {
			if ( isset( $zoner_config['free-unlimited-properties'] ) && ( $zoner_config['free-unlimited-properties'] == 0 ) ) {
				$pack_property_limit = esc_attr( (int) $zoner_config['free-limit-properties'] );

				if ( $pack_property_limit > $user_property_limit ) $is_user_property_not_limit = true;
			} else {
				$is_user_property_not_limit = true;
			}
		}

		if ( esc_attr( $zoner_config['paid-type-properties'] ) == 1 ) $is_user_property_not_limit = true;

		$is_paid_system = ( ! empty( $zoner_config['paid-system'] ) && $zoner_config['paid-system'] == 1 );
		if ( ! $is_paid_system ) $is_user_property_not_limit = true;

		return $is_user_property_not_limit;
	} // _zoner_is_user_limit_properties

	/**
	 * Check if the current number or a user's featured property posts is within the related package limits.
	 * (derived from theme source method in .../themes/zoner/includes/admin/classes/zoner.class-membership.php)
	 *
	 * @since 2.8
	 *
	 * @param int|string $user_id ID of the WP user to check.
	 *
	 * @return bool Property count within user/package limits?
	 */
	private function _zoner_is_user_limit_featured_properties( $user_id ) {
		global $zoner, $zoner_config, $prefix, $wpdb;

		if ( ! isset( $zoner ) || ! $zoner || ! isset( $zoner_config ) || ! $zoner_config ) {
			$this->plugin->log->add( __( "Zoner theme not available, user's property quota cant't be checked.", 'immonex-openimmo2wp' ), 'error' );
			return false;
		}

		$is_user_featured_property_not_limit = false;

		$curr_user_role = $this->_zoner_get_current_user_role( $user_id );
		$curr_user_id = $user_id;
		$package_id = get_user_meta( $curr_user_id, $prefix . 'package_id', true );
		$valid_thru = get_user_meta( $curr_user_id, $prefix . 'valid_thru', true );

		$pack_property_featured_limit = -1;
		$user_property_featured_limit = -1;

		$full_list_featured = array(
			'post_type' => 'property',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'author' => $curr_user_id,
			'meta_query' => array(
				array(
					'key' => $prefix . 'is_featured',
					'value' => 'on',
					'compare' => '=',
				)
			)
		);

		$featured_found = new \WP_Query( $full_list_featured );
		$user_property_featured_limit = (int) $featured_found->found_posts;

		if ( $package_id ) {
			$package_info = $zoner->membership->zoner_get_package_info_by_id( $package_id );

			if ( $package_info->is_unlim_featured === 'off' ) {
				$pack_property_limit = (int) $package_info->limit_featured;
			} else {
				$pack_property_limit = 0;
			}

			if ( ! empty( $valid_thru ) && ( $valid_thru >= current_time( 'mysql' ) ) ) {
				if ( $pack_property_limit == 0 ) {
					$is_user_featured_property_not_limit = true;
				} else {
					if ( $pack_property_limit > $user_property_featured_limit ) {
						$is_user_featured_property_not_limit = true;
					}
				}
			} else {
				if ( $pack_property_limit == 0 ) {
					$is_user_featured_property_not_limit = true;
				} else {
					if ( $pack_property_limit > $user_property_featured_limit ) {
						$is_user_featured_property_not_limit = true;
					}
				}
			}
		} else {
			if ( isset( $zoner_config['free-unlimited-featured'] ) && ( $zoner_config['free-unlimited-featured'] == 0 ) ) {
				$pack_property_featured_limit = esc_attr( (int) $zoner_config['free-limit-featured'] );

				if ( $pack_property_featured_limit > $user_property_featured_limit ) $is_user_featured_property_not_limit = true;
			} else {
				$is_user_featured_property_not_limit = true;
			}

		}

		if ( $zoner_config['paid-type-properties'] == 1 ) $is_user_featured_property_not_limit = true;

		$is_paid_system = ( ! empty( $zoner_config['paid-system'] ) && ( $zoner_config['paid-system'] == 1 ) );
		if ( ! $is_paid_system ) $is_user_featured_property_not_limit = true;

		return $is_user_featured_property_not_limit;
	} // _zoner_is_user_limit_featured_properties

	/**
	 * Get the role of the given user.
	 * (derived from theme source method in .../themes/zoner/includes/admin/classes/zoner.class-init.php)
	 *
	 * @since 2.8
	 *
	 * @param int|string $user_id WP user ID.
	 *
	 * @return string|bool Role name or false if not set.
	 */
	private function _zoner_get_current_user_role( $user_id ) {
		global $wp_roles;

		$user = get_user_by( 'ID', $user_id );
		if ( ! $user ) return false;

		$roles = $user->roles;
		$role = array_shift( $roles );

		return isset( $wp_roles->role_names[$role] ) ? translate_user_role( $wp_roles->role_names[$role] ) : false;
	} // _zoner_get_current_user_role

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 2.8
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
	 * @since 2.8
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
			),
			array(
				'name' => $this->theme_class_slug . '_allow_user_rating',
				'type' => 'checkbox',
				'label' => __( 'Allow User Rating', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Activate to permit users to rate imported properties.', 'immonex-openimmo2wp' )
				)
			),
		) );

		return $fields;
	} // extend_fields

} // class Zoner
