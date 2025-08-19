<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * brings-online.com themes (Immobilia 2.0, ImmoMobil, Property) specific processing.
 */
class Brings_Online extends Theme_Base {

	public
		$theme_class_slug = 'brings-online',
		$theme_properties;

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
			'post_images' => array()
		);

		parent::__construct( $plugin );

		$this->theme_properties = $supported_theme_properties;

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
		add_filter( 'immonex_oi2wp_add_property_custom_field', array( $this, 'check_sold_rented_state' ), 10, 3 );
		add_filter( 'immonex_oi2wp_add_property_custom_field', array( $this, 'create_excerpt' ), 10, 3 );

		if ( $this->theme_options['delete_references'] ) {
			add_action( 'immonex_oi2wp_before_property_processing', array( $this, 'maybe_delete_reference' ) );
		}
		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'maybe_convert_reference' ), 30, 2 );

		if ( $this->theme_options['every_property_on_start_page'] ) {
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'add_start_page_listing' ), 10, 2 );
		}

		if ( $this->theme_options['days_new'] > 0 ) {
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'add_new_label' ), 10, 2 );
		}
	} // __construct

	/**
	 * Classify the property by sqm.
	 *
	 * @since 1.0
	 *
	 * @param string $term The size/sqm taxonomy term.
	 * @param SimpleXMLElement $immobilie Current property object.
	 * @param array $mapping Current mapping data.
	 * @param int $post_id ID of the related property post record.
	 *
	 * @return $term (Possibly modified) Taxonomy term.
	 */
	public function set_property_size_cluster( $term, $immobilie, $mapping, $post_id ) {
		$plugin = $this->plugin;
		$taxonomy = $mapping['dest'];
		if ( 'size' !== $taxonomy || ! taxonomy_exists( $taxonomy ) ) return $term;

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
	 * @since 1.5.7
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
		if ( 'rooms' !== $taxonomy || ! taxonomy_exists( $taxonomy ) ) return $term;

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
	 * @since 3.0
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
		if ( 'price' !== $taxonomy || ! taxonomy_exists( $taxonomy ) ) return $term;

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
	 * Change "Verkauft" to "Vermietet" if property is to be rented.
	 *
	 * @since 1.0
	 *
	 * @param array $custom_field_data Original custom field data.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param int|string $post_id Property post ID.
	 *
	 * @return array (Possibly) modified custom field data.
	 */
	public function check_sold_rented_state( $custom_field_data, $immobilie, $post_id ) {
		if (
			'bor_prop-sale' === $custom_field_data['mapping_destination'] &&
			(
				'true' == strtolower( (string) $immobilie->objektkategorie->vermarktungsart['MIETE'] ) ||
				'1' == (string) $immobilie->objektkategorie->vermarktungsart['MIETE'] ||
				'true' == strtolower( (string) $immobilie->objektkategorie->vermarktungsart['MIETE_PACHT'] ) ||
				'1' == (string) $immobilie->objektkategorie->vermarktungsart['MIETE_PACHT']
			)
		) {
			$custom_field_data['meta_value'] = 'Vermietet';
		}

		return $custom_field_data;
	} // check_sold_rented_state

	/**
	 * Shorten description texts that will be used for the start page and the meta description.
	 *
	 * @since 1.0
	 *
	 * @param array $custom_field_data Original custom field data.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param int|string $post_id Property post ID.
	 *
	 * @return array (Possibly) modified custom field data.
	 */
	public function create_excerpt( $custom_field_data, $immobilie, $post_id ) {
		if (
			'_boT_top-shorttext' === $custom_field_data['mapping_destination'] ||
			'_boT_meta-description' === $custom_field_data['mapping_destination']
		) {
			$custom_field_data['meta_value'] = $this->plugin->string_utils->get_excerpt( $custom_field_data['meta_value'], 120, '...' );
		}

		return $custom_field_data;
	} // create_excerpt

	/**
	 * Convert and save theme custom data.
	 *
	 * @since 1.0
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
	 * @since 1.0
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie );

		$address = $geodata['address_geocode'];
		$lat = false;
		$lng = false;

		if ( $geodata['publishing_approved'] ) {
			$address = trim( $geodata['street'] . "<br>\n" . $geodata['city'] );
			$this->plugin->log->add( wp_sprintf( __( 'Property address: %s', 'immonex-openimmo2wp' ), str_replace( "<br>\n", ', ', $address ) ), 'debug' );

			if ( $geodata['lat'] && $geodata['lng'] ) {
				$lat = $geodata['lat'];
				$lng = $geodata['lng'];
			}
		} else {
			$address = $this->plugin->multilang_get_string_translation( $this->theme_options['address_publishing_not_approved_message'] );
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

		if ( $address ) {
			// Save display address.
			add_post_meta( $post_id, '_boP_prop-address', $address, true );
		}

		if ( $geodata['address_geocode'] ) {
			$theme_name = $this->theme_properties['plain_name'];

			switch ( $theme_name ) {
				case 'immobilia':
					$iframe_width = 286;
					$iframe_height = 240;
					break;
				case 'immomobil':
					$iframe_width = 359;
					$iframe_height = 349;
					break;
				case 'property':
					$iframe_width = 464;
					$iframe_height = 350;
					break;
				default:
					$iframe_width = 425;
					$iframe_height = 350;
			}

			// Save Google map code.
			$gmap_html_code = str_replace( '{iframe_width}', $iframe_width, $this->theme_options['gmap_html_template'] );
			$gmap_html_code = str_replace( '{iframe_height}', $iframe_height, $gmap_html_code );
			$gmap_html_code = str_replace( '{address}', $this->plugin->string_utils::urlencode_special( $geodata['address_geocode'] ), $gmap_html_code );
			$gmap_html_code = str_replace( array( "\r\n", "\r", "\n" ), '', $gmap_html_code );
			$gmap_html_code = str_replace( "\t", ' ', $gmap_html_code );
			$gmap_html_code = preg_replace( '/{\ }*/', ' ', $gmap_html_code );
			add_post_meta( $post_id, '_boP_prop-geolink', $gmap_html_code, true );

			if ( ! $lat || ! $lng ) {
				// Get property location coordinates via geocoding.
				$this->plugin->log->add( wp_sprintf(
					__( 'Property address (Geocoding): %s (ISO2: %s)', 'immonex-openimmo2wp' ),
					$geodata['address_geocode'],
					$geodata['country_code_iso2'] ? $geodata['country_code_iso2'] : __( 'none', 'immonex-openimmo2wp' )
				), 'debug' );
				$geo = $this->geocode( $geodata['address_geocode'], $geodata['publishing_approved'] ? false : true , $geodata['country_code_iso2'], $post_id );
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
					$geocoding_status = $this->get_geocoding_status( $geodata['address_geocode'] , $geodata['country_code_iso2'] );
					$this->plugin->log->add( wp_sprintf( __( 'Geocoding failed (%s)', 'immonex-openimmo2wp' ), $geocoding_status ? $geocoding_status : __( 'unknown reason', 'immonex-openimmo2wp' ) ), 'debug' );
				}
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
				// Remember property image ID for later processing.
				if ( ! isset( $this->temp['post_images'][$p->post_parent] ) ) {
					$this->temp['post_images'][$p->post_parent] = array();
				}
				$this->temp['post_images'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			} elseif ( in_array( strtoupper( $fileinfo['extension'] ), $valid_misc_formats ) ) {
				if (
					'PDF' === strtoupper( $fileinfo['extension'] ) &&
					! get_post_meta( $p->post_parent, '_boP_prop-pdf', true )
				) {
					// Property PDF attachment does not exist yet - add it.
					add_post_meta( $p->post_parent, '_boP_prop-pdf', wp_get_attachment_url( $att_id ), true );
				}
			}
		}
	} // add_attachment_data

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
		$theme_name = $this->theme_properties['plain_name'];

		if ( ! empty( $this->temp['post_images'][$post_id] ) ) {
			$this->temp['post_images'][$post_id] = $this->check_attachment_ids( $this->temp['post_images'][$post_id] );

			$post_att_data = array(); // Images for "old" gallery.
			$post_att_data_new = array(); // Images for "new" gallery.

			$img_cnt = 0;
			foreach ( $this->temp['post_images'][$post_id] as $att_id ) {
				$p = get_post( $att_id );
				$fileinfo = pathinfo( get_attached_file( $att_id ) );
				$img_src = wp_get_attachment_image_src( $att_id, 'large' ) ;

				if (
					false === strpos( strtolower( $p->post_title ), 'energie' ) &&
					false === strpos( strtolower( $fileinfo['filename'] ), 'energie' )
				) {
					if ( $img_cnt > 0 || version_compare( $this->theme_version, '2.0', '>' ) ) {
						// DON'T include the first image in the gallery for theme version prior to 2.0.
						$post_att_data[] = array(
							'd' => $p->post_title,
							'i' => $img_src[0]
						);
					}

					if ( class_exists( 'bo_ImageBox' ) ) {
						// Collect data for an additional (new) gallery for newer theme versions.
						$img_src = wp_get_attachment_image_src( $att_id, 'full' ) ;

						$post_att_data_new[] = array(
							'url' => $img_src[0],
							'alt' => $p->post_title,
							'title' => $p->post_title,
							'caption' => ''
						);
					}

					if ( 0 == $img_cnt ) {
						// Set first image as top image (post meta field).
						add_post_meta( $post_id, '_boT_top-image', $img_src[0], true );
					}

					$img_cnt++;
				} else {
					// Very likely the energy pass image: save as separate meta field.
					add_post_meta( $p->post_parent, '_boP_prop-ausweis', $img_src[0], true );
				}
			}

			if ( count( $post_att_data_new ) > 0 ) {
				add_post_meta( $post_id, 'new_image_data', $post_att_data_new, true );
			} elseif ( count( $post_att_data ) > 0 ) {
				add_post_meta( $post_id, 'image_data', $post_att_data, true );
			}

			unset( $this->temp['post_images'][$post_id] );
			$this->save_temp_theme_data();
		}
	} // save_attachment_data

	/**
	 * Mark property to be displayed on the start page.
	 *
	 * @since 1.0
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function add_start_page_listing( $post_id, $immobilie ) {
		add_post_meta( $post_id, '_boT_top-image-active', 'yes', true );
		add_post_meta( $post_id, '_boT_home-prop-active', 'yes', true );
	} // add_start_page_listing

	/**
	 * Mark property as new if date of last modification is within given timeframe.
	 *
	 * @since 1.0
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function add_new_label( $post_id, $immobilie ) {
		if (
			isset( $immobilie->verwaltung_techn->stand_vom ) &&
			$this->theme_options['days_new'] > 0 &&
			strtotime( (string) $immobilie->verwaltung_techn->stand_vom ) >= strtotime( '-' . $this->theme_options['days_new'] . ' days' )
		) {
			if ( 'property' === $this->theme_properties['plain_name'] ) {
				$field = 'bor_prop-new';
				$value = version_compare( $this->theme_version, '2.0', '>=' ) ? $this->plugin->multilang_get_string_translation( $this->theme_options['new_label'] ) : 'yes';
			} else {
				$field = 'bor_prop-marker';
				$value = $this->plugin->multilang_get_string_translation( $this->theme_options['new_label'] );
			}

			add_post_meta( $post_id, $field, $value, true );
		}
	} // add_new_label

	/**
	 * Convert post type of reference properties.
	 *
	 * @since 4.7.0
	 *
	 * @param int|string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	function maybe_convert_reference( $post_id, $immobilie ) {
		if ( ! get_post_meta( $post_id, '_immonex_is_reference', true ) ) return;

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
		$theme_name = $this->theme_properties['plain_name'];

		$cluster_taxonomy_options = array(
			'size' => __( 'Property Size', 'immonex-openimmo2wp' ),
			'rooms' => __( 'Rooms', 'immonex-openimmo2wp' )
		);
		if ( taxonomy_exists( 'price' ) ) $cluster_taxonomy_options['price'] = __( 'Price', 'immonex-openimmo2wp' );

		$theme_fields = array(
			array(
				'name' => $this->theme_class_slug . '_every_property_on_start_page',
				'type' => 'checkbox',
				'label' => __( 'Display every imported property on start page', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array()
			)
		);

		if ( 'property' === $theme_name ) {
			$theme_fields[] = array(
				'name' => $this->theme_class_slug . '_show_properties_on_map',
				'type' => 'checkbox',
				'label' => __( 'Overview Map', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Show marker for every imported property on the overview map (Google Map)', 'immonex-openimmo2wp' )
				)
			);
		}

		$theme_fields[] = array(
			'name' => $this->theme_class_slug . '_use_custom_value_clusters',
			'type' => 'checkbox_group',
			'label' => __( 'Use custom value clusters', 'immonex-openimmo2wp' ),
			'section' => 'ext_section_' . $this->theme_class_slug . '_general',
			'args' => array(
				'options' => $cluster_taxonomy_options,
				'description' => __( 'Enable automatic property classification based on custom value clusters for these taxonomies. For example, all properties with appropriate sizes will be linked with respective terms like "up to 100&nbsp;m²" or "100&nbsp;m² - 200&nbsp;m²" if these have been manually created before.', 'immonex-openimmo2wp' )
			)
		);
		$theme_fields[] = array(
			'name' => $this->theme_class_slug . '_delete_references',
			'type' => 'checkbox',
			'label' => __( 'Delete References', 'immonex-openimmo2wp' ),
			'section' => 'ext_section_' . $this->theme_class_slug . '_general',
			'args' => array(
				'description' => __( '<strong>Enable</strong> if reference properties shall be considered on deletion (only manual deletion <strong>otherwise</strong>).', 'immonex-openimmo2wp' )
			)
		);

		if ( ! in_array( $theme_name, array( 'immobilia', 'immomobil', 'property' ) ) ) {
			$temp_name = $this->plugin->get_plain_theme_name( $theme->parent_theme );
			if ( $temp_name ) $theme_name = $temp_name;
		}

		$theme_fields[] = array(
			'name' => $this->theme_class_slug . '_days_new',
			'type' => 'text',
			'label' => __( 'Mark properties as new', 'immonex-openimmo2wp' ),
			'section' => 'ext_section_' . $this->theme_class_slug . '_general',
			'args' => array(
				'class' => 'small-text',
				'field_suffix' => __( 'days', 'immonex-openimmo2wp' ),
				'description' => __( 'This number is related to the date of the <strong>last update</strong> of the property record (theme/version dependent, 0 = disabled).', 'immonex-openimmo2wp' ),
				'min' => 0
			)
		);
		$theme_fields[] = array(
			'name' => $this->theme_class_slug . '_new_label',
			'type' => 'text',
			'label' => __( 'New label', 'immonex-openimmo2wp' ),
			'section' => 'ext_section_' . $this->theme_class_slug . '_general',
			'args' => array(
				'class' => 'small-text',
				'description' => __( 'Text for the new label (theme/version dependent)', 'immonex-openimmo2wp' )
			)
		);

		$theme_fields[] = array(
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
		);

		if ( 'property' !== $theme_name || version_compare( $this->theme_version, '4.0', '<' ) ) {
			$theme_fields[] = array(
				'name' => $this->theme_class_slug . '_gmap_html_template',
				'type' => 'textarea',
				'label' => __( 'Google Map Code Template', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Embed code for the Google Map to display on the property detail page. Use the placeholders <strong>{address}</strong>, <strong>{iframe_width}</strong> and <strong>{iframe_height}</strong> here.', 'immonex-openimmo2wp' )
				)
			);
		}

		$theme_fields[] = array(
			'name' => $this->theme_class_slug . '_address_publishing_not_approved_message',
			'type' => 'textarea',
			'label' => __( 'Note regarding property location', 'immonex-openimmo2wp' ),
			'section' => 'ext_section_' . $this->theme_class_slug . '_general',
			'args' => array(
				'description' => __( 'This text will be displayed if the publishing of the complete property address has not been approved.', 'immonex-openimmo2wp' )
			)
		);

		return array_merge( $fields, $theme_fields );
	} // extend_fields

	/**
	 * Get taxonomy value clusters for classifying/grouping properties (e.g. size, rooms).
	 *
	 * @since 1.5.7
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

} // class Brings_Online
