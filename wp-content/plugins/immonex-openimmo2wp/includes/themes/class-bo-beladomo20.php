<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * BO-Beladomo20-specific processing.
 */
class BO_Beladomo20 extends Theme_Base {

	public
		$theme_class_slug = 'bo-beladomo20';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 2.7
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->temp = array(
			'floor_plans' => array(
				'filenames' => array(),
				'ids' => array()
			),
			'panorama_images' => array(
				'filenames' => array(),
				'ids' => array()
			),
			'post_images' => array(),
			'post_documents' => array()
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options['size_clusters'] = array(
			array( 'min' => 0, 'max' => 49, 'title' => __( 'smaller than 50', 'immonex-openimmo2wp' ) ),
			array( 'min' => 50, 'max' => 74 ),
			array( 'min' => 75, 'max' => 99 ),
			array( 'min' => 100, 'max' => 124 ),
			array( 'min' => 125, 'max' => 149 ),
			array( 'min' => 150, 'max' => 174 ),
			array( 'min' => 175, 'max' => 199 ),
			array( 'min' => 200, 'max' => 250 ),
			array( 'min' => 251, 'max' => 9999, 'title' => __( 'larger than 250', 'immonex-openimmo2wp' ) )
		);

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );

		add_filter( 'immonex_oi2wp_add_property_taxonomy_term', array( $this, 'set_property_size_cluster' ), 10, 4 );
		if ( in_array( 'rooms', $this->theme_options['use_custom_value_clusters'] ) ) {
			add_action( 'immonex_oi2wp_add_property_taxonomy_term', array( $this, 'set_property_room_cluster' ), 10, 4 );
		}
		if ( in_array( 'price', $this->theme_options['use_custom_value_clusters'] ) ) {
			add_action( 'immonex_oi2wp_add_property_taxonomy_term', array( $this, 'set_property_price_cluster' ), 10, 4 );
		}

		add_filter( 'immonex_oi2wp_add_grouped_post_meta', array( $this, 'add_custom_data' ), 10, 3 );
		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'set_agent_as_post_author' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_description_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_property_custom_field', array( $this, 'create_excerpt' ), 10, 3 );
		add_filter( 'immonex_oi2wp_add_property_custom_field', array( $this, 'maybe_convert_sold_state' ), 10, 3 );
		add_filter( 'immonex_oi2wp_add_property_custom_field', array( $this, 'maybe_adjust_provision_destination' ), 10, 3 );

		if ( $this->theme_options['delete_references'] ) {
			add_action( 'immonex_oi2wp_before_property_processing', array( $this, 'maybe_delete_reference' ) );
		}
		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 20, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'maybe_convert_reference' ), 30, 2 );

		if ( $this->theme_options['days_new'] > 0 ) {
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'add_new_label' ), 10, 2 );
		}
	} // __construct

	/**
	 * Check attachment type and perform related processing steps.
	 *
	 * @since 2.7
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
				// Attachment is an URL of an external video: save as custom field.
				add_post_meta( $post_id, 'vimeo' === $video['type'] ? '_bo_prop_video_vimeourl' : '_bo_prop_video_url', $url, true );

				// DON'T import this attachment.
				return false;
			} elseif ( $this->plugin->string_utils->is_virtual_tour_url( $url, apply_filters( $this->plugin->plugin_prefix . 'additional_virtual_tour_url_parts', array() ) ) ) {
				// Save virtual tour embed code.
				$embed_code = $this->get_virtual_tour_embed_code( apply_filters( $this->plugin->plugin_prefix . 'virtual_tour_embed_code_args', array( 'url' => $url ), 5 ) );
				add_post_meta( $post_id, '_bo_prop_exframe', $embed_code, true );

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
				if ( ! isset( $this->temp['floor_plans']['filenames'][$post_id] ) ) {
					$this->temp['floor_plans']['filenames'][$post_id] = array();
				}
				$this->temp['floor_plans']['filenames'][$post_id][] = pathinfo( $attachment->daten->pfad, PATHINFO_BASENAME );
				$this->temp['floor_plans']['filenames'][$post_id] = array_unique( $this->temp['floor_plans']['filenames'][$post_id] );
				$this->save_temp_theme_data();
			}
		} elseif (
			in_array( (string) $attachment['gruppe'], array( 'PANORAMA' ) ) &&
			empty( $this->temp['panorama_images'] ) // The theme currently supports only ONE panorama image.
		) {
			$format = (string) $attachment->format;
			if ( false !== strpos( $format, '/' ) ) {
				// Split file format declaration.
				$temp = explode( '/', $format );
				$format = $temp[1];
			}

			if ( in_array( strtoupper( $format ), array( 'JPG', 'JPEG', 'GIF', 'PNG' ) ) ) {
				// Attachment ist a panorama image: remember its filename for later processing.
				if ( ! isset( $this->temp['panorama_images']['filenames'][$post_id] ) ) {
					$this->temp['panorama_images']['filenames'][$post_id] = array();
				}
				$this->temp['panorama_images']['filenames'][$post_id][] = pathinfo( $attachment->daten->pfad, PATHINFO_BASENAME );
				$this->temp['panorama_images']['filenames'][$post_id] = array_unique( $this->temp['panorama_images']['filenames'][$post_id] );
				$this->save_temp_theme_data();
			}
		}

		return $attachment;
	} // check_attachment

	/**
	 * Classify the property by sqm.
	 *
	 * @since 2.7
	 *
	 * @param string $term The size/sqm taxonomy term.
	 * @param SimpleXMLElement $immobilie Current property object.
	 * @param array $mapping Current mapping data.
	 * @param int $post_id ID of the related property post record.
	 *
	 * @return $term (Possibly modified) Taxonomy term.
	 */
	public function set_property_size_cluster( $term, $immobilie, $mapping, $post_id ) {
		$taxonomy = $mapping['dest'];
		if ( 'size' !== $taxonomy ) return $term;

		$term = (int) $term;

		if ( $term ) {
			$source = $mapping['source'];

			if ( isset( $immobilie->objektkategorie->objektart->zimmer ) ) $property_cat = 'room';
			elseif ( isset( $immobilie->objektkategorie->objektart->wohnung ) ) $property_cat = 'flat';
			elseif ( isset( $immobilie->objektkategorie->objektart->haus ) ) $property_cat = 'house';
			elseif ( isset( $immobilie->objektkategorie->objektart->grundstueck ) ) $property_cat = 'plot';
			elseif ( isset( $immobilie->objektkategorie->objektart->buero_praxen ) ) $property_cat = 'office';
			elseif ( isset( $immobilie->objektkategorie->objektart->einzelhandel ) ) $property_cat = 'retail';
			elseif ( isset( $immobilie->objektkategorie->objektart->gastgewerbe ) ) $property_cat = 'gastronomy';
			elseif ( isset( $immobilie->objektkategorie->objektart->hallen_lager_prod ) ) $property_cat = 'storage';
			elseif ( isset( $immobilie->objektkategorie->objektart->land_und_forstwirtschaft ) ) $property_cat = 'agriculture';
			elseif ( isset( $immobilie->objektkategorie->objektart->parken ) ) $property_cat = 'parking';
			else return false;

			// Don't save the term if the source is not related to the property category.
			switch ( $property_cat ) {
				case 'room' :
				case 'flat' :
				case 'house' :
					if ( 'flaechen->wohnflaeche' !== $source ) return false;
					break;
				case 'plot' :
					if ( 'flaechen->grundstuecksflaeche' !== $source ) return false;
					break;
				case 'office' :
					$related_spaces = array(
						'flaechen->bueroflaeche',
						'flaechen->bueroteilflaeche',
						'flaechen->nutzflaeche',
						'flaechen->gesamtflaeche'
					);
					if ( ! in_array( $source, $related_spaces ) ) return false;
					break;
				case 'retail' :
					$related_spaces = array(
						'flaechen->ladenflaeche',
						'flaechen->verkaufsflaeche',
						'flaechen->nutzflaeche',
						'flaechen->gesamtflaeche'
					);
					if ( ! in_array( $source, $related_spaces ) ) return false;
					break;
				case 'gastronomy' :
					$related_spaces = array(
						'flaechen->gastroflaeche',
						'flaechen->nutzflaeche',
						'flaechen->gesamtflaeche'
					);
					if ( ! in_array( $source, $related_spaces ) ) return false;
					break;
				case 'storage' :
					$related_spaces = array(
						'flaechen->lagerflaeche',
						'flaechen->nutzflaeche',
						'flaechen->gesamtflaeche'
					);
					if ( ! in_array( $source, $related_spaces ) ) return false;
					break;
				case 'agriculture' :
				case 'parking' :
					$related_spaces = array(
						'flaechen->nutzflaeche',
						'flaechen->gesamtflaeche'
					);
					if ( ! in_array( $source, $related_spaces ) ) return false;
					break;
			}

			if ( in_array( 'size', $this->theme_options['use_custom_value_clusters'] ) ) {
				// Use manually created size categories for property classification.
				$size_clusters = $this->_get_term_value_clusters( 'size', false );
			} elseif (
				apply_filters( $this->plugin->plugin_prefix . 'bo_enable_auto_size_clustering', true ) &&
				isset( $this->theme_options['size_clusters'] ) &&
				count( $this->theme_options['size_clusters'] ) > 0
			) {
				// Use default size clusters (defined in constructor/modifiable via hook)
				// for property classification.
				$size_clusters = $this->theme_options['size_clusters'];
			} else {
				$size_clusters = false;
			}

			if ( $size_clusters ) {
				foreach ( $size_clusters as $size_cat ) {
					if (
						(
							$term == $size_cat['min'] &&
							false === $size_cat['max'] &&
							false === $size_cat['min_max']
						) || (
							$term >= $size_cat['min'] &&
							(
								(
									false !== $size_cat['max'] &&
									$term <= $size_cat['max']
								) ||
								(
									false === $size_cat['max'] &&
									'min' === $size_cat['min_max']
								)
							)
						)
					) {
						if ( isset( $size_cat['title'] ) && $size_cat['title'] )
							$term = $size_cat['title'];
						else
							$term = $size_cat['min'] . ' - ' . $size_cat['max'];

						return $term;
					}
				}
			}
		}

		return false;
	} // set_property_size_cluster

	/**
	 * Classify the property by the number of its rooms.
	 *
	 * @since 2.7
	 *
	 * @param string $term The rooms taxonomy term.
	 * @param SimpleXMLElement $immobilie Current property object.
	 * @param array $mapping Current mapping data.
	 * @param int $post_id ID of the related property post record.
	 *
	 * @return $term (Possibly modified) Taxonomy term.
	 */
	public function set_property_room_cluster( $term, $immobilie, $mapping, $post_id ) {
		$taxonomy = $mapping['dest'];
		if ( 'rooms' !== $taxonomy ) return $term;

		$term = (int) $term;

		if ( $term ) {
			// Use manually created size categories for property classification.
			$room_clusters = $this->_get_term_value_clusters( 'rooms', false );

			if ( $room_clusters && count( $room_clusters ) > 0 ) {
				foreach ( $room_clusters as $room_cat ) {
					if (
						(
							$term == $room_cat['min'] &&
							false === $room_cat['max'] &&
							false === $room_cat['min_max']
						) || (
							$term >= $room_cat['min'] &&
							(
								(
									false !== $room_cat['max'] &&
									$term <= $room_cat['max']
								) ||
								(
									false === $room_cat['max'] &&
									'min' === $room_cat['min_max']
								)
							)
						)
					) {
						if ( isset( $room_cat['title'] ) && $room_cat['title'] )
							$term = $room_cat['title'];
						else
							$term = $room_cat['min'] . ' - ' . $room_cat['max'];

						return $term;
					}
				}
			}
		}

		return false;
	} // set_property_room_cluster

	/**
	 * Classify the property by its price.
	 *
	 * @since 2.7
	 *
	 * @param string $term The rooms taxonomy term.
	 * @param SimpleXMLElement $immobilie Current property object.
	 * @param array $mapping Current mapping data.
	 * @param int $post_id ID of the related property post record.
	 *
	 * @return $term (Possibly modified) Taxonomy term.
	 */
	public function set_property_price_cluster( $term, $immobilie, $mapping, $post_id ) {
		$taxonomy = $mapping['dest'];
		if ( 'price' !== $taxonomy ) return $term;

		$is_sale = 'true' == strtolower( (string) $immobilie->objektkategorie->vermarktungsart['KAUF'] ) ||
			'1' == (string) $immobilie->objektkategorie->vermarktungsart['KAUF'];
		if ( $is_sale && false === strpos( $mapping['source'], 'preis' ) ) return $term;

		$term = (int) $term;

		if ( $term ) {
			// Use manually created price categories for property classification.
			$price_clusters = $this->_get_term_value_clusters( 'price', true );

			if ( $price_clusters && count( $price_clusters ) > 0 ) {
				foreach ( $price_clusters as $price_cat ) {
					if (
						(
							$term == $price_cat['min'] &&
							false === $price_cat['max'] &&
							false === $price_cat['min_max']
						) || (
							$term >= $price_cat['min'] &&
							(
								(
									false !== $price_cat['max'] &&
									$term <= $price_cat['max']
								) ||
								(
									false === $price_cat['max'] &&
									'min' === $price_cat['min_max']
								)
							)
						)
					) {
						if ( isset( $price_cat['title'] ) && $price_cat['title'] )
							$term = $price_cat['title'];
						else
							$term = $price_cat['min'] . ' - ' . $price_cat['max'];

						return $term;
					}
				}
			}
		}

		return false;
	} // set_property_price_cluster

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 *
	 * @since 2.7
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
	 * Shorten description texts that will be used for the start page and the meta description.
	 *
	 * @since 2.7
	 *
	 * @param array $custom_field_data Original custom field data.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param int|string $post_id Property post ID.
	 *
	 * @return array (Possibly) modified custom field data.
	 */
	public function create_excerpt( $custom_field_data, $immobilie, $post_id ) {
		if ( '_boT_top-shorttext' === $custom_field_data['mapping_destination'] ) {
			$custom_field_data['meta_value'] = $this->plugin->string_utils->get_excerpt( $custom_field_data['meta_value'], 120, '...' );
		}

		return $custom_field_data;
	} // create_excerpt

	/**
	 * Convert state "sold" to "rented" for rental properties.
	 *
	 * @since 2.7
	 *
	 * @param array $custom_field_data Original custom field data.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param int|string $post_id Property post ID.
	 *
	 * @return array (Possibly) modified custom field data.
	 */
	public function maybe_convert_sold_state( $custom_field_data, $immobilie, $post_id ) {
		if (
			! in_array( $custom_field_data['meta_key'], array( 'bor_prop-sale', 'bor_prop-marker' ) ) ||
			strtolower( __( 'Sold', 'immonex-openimmo2wp' ) ) !== strtolower( $custom_field_data['meta_value'] )
		) {
			return $custom_field_data;
		}

		$is_sale = 'true' == strtolower( (string) $immobilie->objektkategorie->vermarktungsart['KAUF'] ) ||
			'1' == (string) $immobilie->objektkategorie->vermarktungsart['KAUF'];

		if ( ! $is_sale ) {
			$custom_field_data['meta_value'] = __( 'Rented', 'immonex-openimmo2wp' );
		}

		return $custom_field_data;
	} // maybe_convert_sold_state

	/**
	 * Change provision destination fields of rental properties.
	 *
	 * @since 4.9.26-beta
	 *
	 * @param array $custom_field_data Original custom field data.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param int|string $post_id Property post ID.
	 *
	 * @return array (Possibly) modified custom field data.
	 */
	public function maybe_adjust_provision_destination( $custom_field_data, $immobilie, $post_id ) {
		if ( ! in_array( $custom_field_data['meta_key'], array( '_boP_prop-prov2', '_boP_prop-prov4' ) ) ) {
			return $custom_field_data;
		}

		$is_sale = 'true' === strtolower( (string) $immobilie->objektkategorie->vermarktungsart['KAUF'] ) ||
			'1' === (string) $immobilie->objektkategorie->vermarktungsart['KAUF'];

		if ( $is_sale ) {
			return $custom_field_data;
		}

		$current_meta_key = $custom_field_data['meta_key'];

		switch( $current_meta_key ) {
			case '_boP_prop-prov2' : // Old: Buyer's Commission
				$custom_field_data['meta_key'] = '_boP_prop-prov1'; // New: Tenant Commission
				break;
			case '_boP_prop-prov4' : // Old: Seller Commission
				$custom_field_data['meta_key'] = '_boP_prop-prov3'; // New: Landlord Commission
		}

		return $custom_field_data;
	} // maybe_adjust_provision_destination

	/**
	 * Convert and save theme custom data.
	 *
	 * @since 2.7
	 *
	 * @param array $grouped_meta_data Associative array of a meta data group.
	 * @param int $post_id ID of the related property post record.
	 * @param string $meta_key Meta key under which the serialized group array will be stored.
	 */
	public function add_custom_data( $grouped_meta_data, $post_id, $meta_key ) {
		if ( ! in_array( $meta_key, array( 'custom_data', 'custombox_data' ) ) ) return $grouped_meta_data;

		$custom_meta = array();

		if ( count( $grouped_meta_data ) > 0 ) {
			foreach ( $grouped_meta_data as $key => $data ) {
				if ( 'custombox_data' === $meta_key ) {
					$custom_meta[] = array(
						'd' => nl2br( $data['value'] )
					);
				} else {
					$custom_meta[] = array(
						'd' => $key,
						'i' => nl2br( $data['value'] )
					);
				}
			}

			add_post_meta( $post_id, $meta_key, $custom_meta, true );
		}

		// DON'T save the original data.
		return false;
	} // add_custom_data

	/**
	 * Save the property address (post meta) if approved for publishing and the
	 * Google maps code.
	 *
	 * @since 2.7
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie, true );
		$lat = false;
		$lng = false;

		if ( $geodata['publishing_approved'] ) {
			if ( $geodata['street'] ) add_post_meta( $post_id, '_bo_prop_map_1', preg_replace( '/([0-9]{1,3})(-[0-9]{1,3})$/', '$1', $geodata['street'] ), true );

			if ( $geodata['lat'] && $geodata['lng'] ) {
				$lat = $geodata['lat'];
				$lng = $geodata['lng'];
			}

			$this->plugin->log->add( wp_sprintf( __( 'Property address: %s', 'immonex-openimmo2wp' ), $geodata['address_geocode'] ), 'debug' );
		} else {
			add_post_meta( $post_id, '_bo_prop_map_info', $this->plugin->multilang_get_string_translation( $this->theme_options['address_publishing_not_approved_message'] ) );
			$this->plugin->log->add( __( 'Property address NOT approved for publishing', 'immonex-openimmo2wp' ), 'debug' );

			if (
				$this->plugin->plugin_options['geo_always_use_coordinates'] &&
				$geodata['lat'] && $geodata['lng']
			) {
				$lat = $geodata['lat'];
				$lng = $geodata['lng'];
				$this->plugin->log->add( __( 'Property address NOT approved for publishing, but usable coordinates available and publishing permitted.', 'immonex-openimmo2wp' ), 'debug' );
			}
		}

		add_post_meta( $post_id, '_bo_prop_map_2', $geodata['city'], true );
		add_post_meta( $post_id, '_bo_prop_map_3', $geodata['country_data']['Common Name'], true );
		add_post_meta( $post_id, '_bo_prop_map_4', $this->theme_options['gmap_zoom'], true );
		add_post_meta( $post_id, '_bo_prop_map_position', $this->theme_options['map_position'], true );

		if ( $geodata['address_geocode'] && ! $lat && ! $lng ) {
			// Get property location coordinates via geocoding.
			$this->plugin->log->add( wp_sprintf(
				__( 'Property address (Geocoding): %s (ISO2: %s)', 'immonex-openimmo2wp' ),
				$geodata['address_geocode'],
				$geodata['country_code_iso2'] ? $geodata['country_code_iso2'] : __( 'none', 'immonex-openimmo2wp', $geodata['country_code_iso2'] )
			), 'debug' );
			$geo = $this->geocode( $geodata['address_geocode'], $geodata['publishing_approved'] ? false : true, $geodata['country_code_iso2'], $post_id );
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
				$geocoding_status = $this->get_geocoding_status( $geodata['address_geocode'], $geodata['address_geocode'] );
				$this->plugin->log->add( wp_sprintf( __( 'Geocoding failed (%s)', 'immonex-openimmo2wp' ), $geocoding_status ? $geocoding_status : __( 'unknown reason', 'immonex-openimmo2wp' ) ), 'debug' );
			}
		}

		if ( $this->theme_options['show_properties_on_map'] ) {
			add_post_meta( $post_id, 'bo_show_on_map', 'yes', true );
		}

		if ( $lat && $lng ) {
			$google_map_data = array(
				'latitude' => $lat,
				'longitude' => $lng,
				'elevation' => '',
				'localtion_name' => '', // Typo in THEME.
				'zoom' => 7
			);

			add_post_meta( $post_id, 'bo_prop_google_map', $google_map_data, true );
		}
	} // save_property_location

	/**
	 * Collect extra data for property attachments.
	 *
	 * @since 2.7
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

			// Remove counter from filename for comparison (floor plans etc).
			$filename = $this->get_plain_basename( $fileinfo['filename'] );

			// Possibly extend filename arrays by sanitized versions.
			$floor_plans = ! empty( $this->temp['floor_plans']['filenames'][$p->post_parent] ) ?
				$this->get_extended_filenames( $this->temp['floor_plans']['filenames'][$p->post_parent] ) :
				array();
			$panorama_images = ! empty( $this->temp['panorama_images']['filenames'][$p->post_parent] ) ?
				$this->get_extended_filenames( $this->temp['panorama_images']['filenames'][$p->post_parent] ) :
				array();

			if ( ! empty( $floor_plans ) && in_array( $filename, $floor_plans, true ) ) {
				// Remember floor plan attachment ID, exclude from gallery.
				$this->temp['floor_plans']['ids'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			} elseif ( ! empty( $panorama_images ) && in_array( $filename, $panorama_images, true )	) {
				// Remember panorama attachment ID, exclude from gallery.
				if ( ! isset( $this->temp['panorama_images']['ids'][$p->post_parent] ) ) {
					$this->temp['panorama_images']['ids'][$p->post_parent] = array();
				}
				$this->temp['panorama_images']['ids'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			} elseif ( in_array( strtoupper( $fileinfo['extension'] ), $valid_image_formats ) ) {
				// Remember property image ID for later processing.
				if ( ! isset( $this->temp['post_images'][$p->post_parent] ) ) {
					$this->temp['post_images'][$p->post_parent] = array();
				}
				$this->temp['post_images'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			} elseif ( in_array( strtoupper( $fileinfo['extension'] ), $valid_misc_formats ) ) {
				// Remember property document attachment IDs for later processing.
				if ( ! isset( $this->temp['post_documents'][$p->post_parent] ) ) {
					$this->temp['post_documents'][$p->post_parent] = array();
				}
				$this->temp['post_documents'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			}
		}
	} // add_attachment_data

	/**
	 * Save extra data of property attachments as serialized array in
	 * theme-specific format.
	 *
	 * @since 2.7
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['post_images'][$post_id] ) ) {
			// Save images for gallery.
			$this->temp['post_images'][$post_id] = $this->check_attachment_ids( $this->temp['post_images'][$post_id] );

			$post_att_data = array();

			foreach ( $this->temp['post_images'][$post_id] as $att_id ) {
				$p = get_post( $att_id );
				$url = wp_get_attachment_url( $att_id );

				$post_att_data[] = array(
					'url' => $url,
					'alt' => $p->post_title,
					'title' => $p->post_title,
					'caption' => ''
				);
			}

			if ( count( $post_att_data ) > 0 ) {
				add_post_meta( $post_id, 'new_propimage_data', $post_att_data, true );
			}

			unset( $this->temp['post_images'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['floor_plans']['ids'][$post_id] ) ) {
			// Save panorama images.
			$this->temp['floor_plans']['ids'][$post_id] = $this->check_attachment_ids( $this->temp['floor_plans']['ids'][$post_id] );

			$post_att_data = array();

			foreach ( $this->temp['floor_plans']['ids'][$post_id] as $att_id ) {
				$p = get_post( $att_id );
				$url = wp_get_attachment_url( $att_id ) ;

				$post_att_data[] = array(
					'url' => $url,
					'alt' => $p->post_title,
					'title' => $p->post_title,
					'caption' => $p->post_title
				);
			}

			if ( count( $post_att_data ) > 0 ) {
				add_post_meta( $post_id, 'groundplan_data', $post_att_data, true );
			}

			unset( $this->temp['floor_plans']['ids'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['panorama_images']['ids'][$post_id] ) ) {
			// Save panorama images.
			$this->temp['panorama_images']['ids'][$post_id] = $this->check_attachment_ids( $this->temp['panorama_images']['ids'][$post_id] );

			$post_att_data = array();

			foreach ( $this->temp['panorama_images']['ids'][$post_id] as $att_id ) {
				$p = get_post( $att_id );
				$url = wp_get_attachment_url( $att_id ) ;

				$post_att_data[] = array(
					'url' => $url,
					'alt' => $p->post_title,
					'caption' => $p->post_title
				);
			}

			// Currently, only ONE VR image is supported by the theme:
			// FIRST image is saved only!
			if ( count( $post_att_data ) > 0 ) {
				add_post_meta( $post_id, '_bo_prop_vrimg', $post_att_data[0], true );
			}

			unset( $this->temp['panorama_images']['ids'][$post_id] );
			$this->save_temp_theme_data();
		} else {
			// Save an empty VR image record to prevent warning issues.
			add_post_meta( $post_id, '_bo_prop_vrimg', array( 'url' => '', 'caption' => '' ), true );
		}

		if ( ! empty( $this->temp['post_documents'][$post_id] ) ) {
			// Save documents attached to property.
			$this->temp['post_documents'][$post_id] = $this->check_attachment_ids( $this->temp['post_documents'][$post_id] );

			$post_att_data = array();

			if ( count( $this->temp['post_documents'][$post_id] ) > 0 ) {
				foreach ( $this->temp['post_documents'][$post_id] as $att_id ) {
					$p = get_post( $att_id );
					$url = wp_get_attachment_url( $att_id ) ;

					$post_att_data[] = array(
						'url' => $url,
						'title' => $p->post_title,
						'caption' => '',
						'description' => ''
					);
				}
			}

			if ( count( $post_att_data ) > 0 ) {
				add_post_meta( $post_id, '_bo_prop_documents', $post_att_data, true );
			}

			unset( $this->temp['post_documents'][$post_id] );
			$this->save_temp_theme_data();
		}
	} // save_attachment_data

	/**
	 * Try to determine/save property agent and agency.
	 *
	 * @since 2.7
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_agent( $post_id, $immobilie ) {
		$agent = $this->get_agent( $immobilie, 'agents', array( 'email' => '_bo_team_email' ), array(), true );
		if ( $agent ) {
			add_post_meta( $post_id, '_bo_team_profile', $agent->ID, true );
		}

		add_post_meta( $post_id, '_bo_show_teamprofile', $agent && $this->theme_options['show_agent'] ? 1 : 0, true );
		add_post_meta( $post_id, '_bo_show_requestform', $this->theme_options['show_contact_form'], true );
	} // save_agent

	/**
	 * Mark property as new if date of last modification is within given timeframe.
	 *
	 * @since 2.7
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function add_new_label( $post_id, $immobilie ) {
		if (
			isset( $immobilie->verwaltung_techn->stand_vom ) &&
			$this->theme_options['days_new'] > 0 &&
			strtotime( (string) $immobilie->verwaltung_techn->stand_vom ) >= strtotime( '-' . $this->theme_options['days_new'] . ' days' ) &&
			! get_post_meta( $post_id, 'bor_prop-marker', true )
		) {
			add_post_meta( $post_id, 'bor_prop-marker', $this->plugin->multilang_get_string_translation( $this->theme_options['new_label'] ), true );
		}
	} // add_new_label

	/**
	 * Convert post type and taxonomy terms of reference properties.
	 *
	 * @since 4.7.0
	 *
	 * @param int|string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	function maybe_convert_reference( $post_id, $immobilie ) {
		if ( ! get_post_meta( $post_id, '_immonex_is_reference', true ) ) return;

		$convert_taxonomies = array(
			'offertype' => 'portfoliotype',
			'location' => 'portfoliolocation'
		);

		$post = get_post( $post_id );
		$obid = trim( (string) $immobilie->verwaltung_techn->openimmo_obid );

		$meta_query = array(
			'relation' => 'AND',
			array(
				'key' => '_is_immonex_import_property',
				'compare' => 'EXISTS'
			),
			array(
				'key' => '_openimmo_obid',
				'value' => $obid
			)
		);

		$args = array(
			'post_type' => 'portfolio',
			'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'meta_query' => $meta_query,
			'numberposts' => -1
		);

		$existing_reference_posts = get_posts( $args );
		if ( count( $existing_reference_posts ) > 0 ) {
			// Delete existing reference properties with the same OBID.
			foreach ( $existing_reference_posts as $ref_post ) {
				$result = wp_delete_post( $ref_post->ID, true );

				if ( $result ) {
					$this->plugin->log->add(
						wp_sprintf(
							__( 'Existing reference property deleted before update: %s [%s]', 'immonex-openimmo2wp' ),
							$ref_post->post_title,
							$ref_post->ID
						),
						'debug'
					);
				} else {
					$this->plugin->log->add(
						wp_sprintf(
							__( 'Existing reference property could not be deleted before update: %s [%s]', 'immonex-openimmo2wp' ),
							$ref_post->post_title,
							$ref_post->ID
						),
						'error'
					);
				}
			}
		}

		$post->post_type = 'portfolio';
		if ( wp_update_post( $post ) ) {
			$this->plugin->log->add(
				wp_sprintf(
					__( 'Imported property converted to reference (Portfolio): %s [%s]', 'immonex-openimmo2wp' ),
					$post->post_title,
					$post->ID
				),
				'info'
			);

			$marker_text = get_post_meta( $post->ID, 'bor_prop-marker', true );

			if (
				$marker_text &&
				$marker_text === $this->plugin->multilang_get_string_translation( $this->theme_options['new_label'] )
			) {
				// Remove "New" label from reference reference objects.
				$marker_text = '';
				update_post_meta( $post_id, 'bor_prop-marker', $marker_text );
			}

			if ( ! get_post_meta( $post->ID, 'bor_prop-sale', true ) ) {
				if ( $marker_text ) {
					update_post_meta( $post->ID, 'bor_prop-sale', $marker_text );
				}
			}

			foreach ( $convert_taxonomies as $source => $dest ) {
				if ( ! get_taxonomy( $source ) || ! get_taxonomy( $dest ) ) continue;

				$source_terms = wp_get_post_terms( $post_id, $source );

				if ( count( $source_terms ) > 0 ) {
					foreach ( $source_terms as $source_term ) {
						$dest_term = get_term_by( 'name', $source_term->name, $dest );
						if ( empty( $dest_term ) ) {
							$new_term = wp_insert_term( $source_term->name, $dest );
							if ( ! is_wp_error( $new_term ) ) {
								wp_set_object_terms( $post_id, (int) $new_term['term_id'], $dest, true );
							}
						} else {
							wp_set_object_terms( $post_id, (int) $dest_term->term_id, $dest, true );
						}
					}
				}
			}
		}
	} // maybe_convert_reference

	/**
	 * Delete reference properties (post type "portfolio").
	 *
	 * @since 4.7.0
	 *
	 * @param int|string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	function maybe_delete_reference( $immobilie ) {
		if ( 'DELETE' !== (string) $immobilie->verwaltung_techn->aktion['aktionart'] ) return;

		$args = array(
			'post_type' => 'portfolio',
			'post_status' => 'any',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => '_openimmo_obid',
					'value' => trim( (string) $immobilie->verwaltung_techn->openimmo_obid )
				)
			)
		);

		$references = get_posts( $args );

		if ( count( $references ) > 0 ) {
			foreach ( $references as $property ) {
				$result = wp_delete_post( $property->ID, true );

				if ( $result ) {
					$this->plugin->log->add(
						wp_sprintf(
							__( 'Reference property deleted: %s [%s]', 'immonex-openimmo2wp' ),
							$property->post_title,
							$property->ID
						),
						'info'
					);
				} else {
					$this->plugin->log->add(
						wp_sprintf(
							__( 'Reference property could not be deleted: %s [%s]', 'immonex-openimmo2wp' ),
							$property->post_title,
							$property->ID
						),
						'error'
					);
				}
			}
		}
	} // maybe_delete_reference

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 2.7
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
	 * @since 2.7
	 *
	 * @param array $fields Original fields array.
	 *
	 * @return array Extended fields array.
	 */
	public function extend_fields( $fields ) {
		$theme_fields = array(
			array(
				'name' => $this->theme_class_slug . '_use_custom_value_clusters',
				'type' => 'checkbox_group',
				'label' => __( 'Use custom value clusters', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'options' => array(
						'size' => __( 'Property Size', 'immonex-openimmo2wp' ),
						'rooms' => __( 'Rooms', 'immonex-openimmo2wp' ),
						'price' => __( 'Price', 'immonex-openimmo2wp' )
					),
					'description' => __( 'Enable automatic property classification based on custom value clusters for these taxonomies. For example, all properties with appropriate sizes will be linked with respective terms like "up to 100&nbsp;m²" or "100&nbsp;m² - 200&nbsp;m²" if these have been manually created before.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_delete_references',
				'type' => 'checkbox',
				'label' => __( 'Delete References', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( '<strong>Enable</strong> if reference properties shall be considered on deletion (only manual deletion <strong>otherwise</strong>).', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_days_new',
				'type' => 'text',
				'label' => __( 'Mark properties as new', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'class' => 'small-text',
					'field_suffix' => __( 'days', 'immonex-openimmo2wp' ),
					'description' => __( 'This number is related to the date of the <strong>last update</strong> of the property record (0 = disabled).', 'immonex-openimmo2wp' ),
					'min' => 0
				)
			),
			array(
				'name' => $this->theme_class_slug . '_new_label',
				'type' => 'text',
				'label' => __( 'New label', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'class' => 'small-text',
					'description' => __( 'Text for the new label', 'immonex-openimmo2wp' )
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
				'name' => $this->theme_class_slug . '_show_properties_on_map',
				'type' => 'checkbox',
				'label' => __( 'Overview Map', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Show marker for every imported property on the overview map (Google Map)', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_map_position',
				'type' => 'select',
				'label' => __( 'Detail Page Map Position', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'options' => array(
						'top' => __( 'Top of page', 'immonex-openimmo2wp' ),
						'bottom' => __( 'Bottom of page', 'immonex-openimmo2wp' )
					),
					'description' => __( 'Google Map position on detail pages of imported properties', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_gmap_zoom',
				'type' => 'text',
				'label' => __( 'Detail Page Map Zoom', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'class' => 'small-text',
					'description' => __( 'Default zoom factor for Google Maps on property detail pages', 'immonex-openimmo2wp' ),
					'min' => 0,
					'under_min_default' => 10,
					'max' => 22
				)
			),
			array(
				'name' => $this->theme_class_slug . '_show_agent',
				'type' => 'checkbox',
				'label' => __( 'Show agent', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Show agent on property detail pages?', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_show_contact_form',
				'type' => 'checkbox',
				'label' => __( 'Show contact form', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Show a contact form on property detail pages?', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_address_publishing_not_approved_message',
				'type' => 'textarea',
				'label' => __( 'Note regarding property location', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'This text will be displayed if the publishing of the complete property address has not been approved.', 'immonex-openimmo2wp' )
				)
			)
		);

		return array_merge( $fields, $theme_fields );
	} // extend_fields

	/**
	 * Get taxonomy value clusters for classifying/grouping properties (e.g. size, rooms).
	 *
	 * @since 2.7
	 *
	 * @param string $taxonomy Taxonomy to search for groups.
	 * @param bool $delete_dots Delete any dots from term before examining it?
	 *
	 * @return array Value clusters of specified taxonomy.
	 */
	private function _get_term_value_clusters( $taxonomy, $delete_dots = false ) {
		$max_words = array( '&lt;', '<', 'bis', 'unter', 'kleiner', 'max', 'to', 'under', 'smaller' );
		$min_words = array( '&gt;', '>', 'ab', 'über', 'mehr als', 'größer', 'min', 'more than', 'start', 'begin', 'over', 'larger' );

		$args = array( 'hide_empty' => false );
		$clusters_temp = get_terms( $taxonomy, $args );
		$clusters = array();

		if ( count( $clusters_temp ) ) {
			foreach ( $clusters_temp as $cluster ) {
				if ( $delete_dots ) $group_name = str_replace( '.', '', $cluster->name );
				else $group_name = $cluster->name;

				$num_matches = preg_match_all( '/\d+/', $group_name, $matches );
				if ( $num_matches > 0 ) {
					$min_max = false;
					if ( $num_matches !== 2 ) {
						if ( preg_match( '/^(' . implode( '|', $max_words ) . ')/i', $cluster->name ) > 0 ) $min_max = 'max';
						elseif ( preg_match( '/^(' . implode( '|', $min_words ) . ')/i', $cluster->name ) > 0 ) $min_max = 'min';
					}

					$clusters[ $cluster->term_id ] = array(
						'min' => $min_max !== 'max' ? $matches[0][0] : 0,
						'max' => isset( $matches[0][1] ) ? $matches[0][1] : ( $min_max === 'max' ? $matches[0][0] : false ),
						'title' => $cluster->name,
						'min_max' => $min_max
					);

					if (
						isset( $clusters[ $cluster->term_id ]['min'] ) &&
						false !== $clusters[ $cluster->term_id ]['max'] &&
						$clusters[ $cluster->term_id ]['min'] > $clusters[ $cluster->term_id ]['max']
					) {
						$temp = $clusters[ $cluster->term_id ]['min'];
						$clusters[ $cluster->term_id ]['min'] = $clusters[ $cluster->term_id ]['max'];
						$clusters[ $cluster->term_id ]['max'] = $temp;
					}
				}
			}
		}

		return $clusters;
	} // _get_term_value_clusters

} // class BO_Beladomo20
