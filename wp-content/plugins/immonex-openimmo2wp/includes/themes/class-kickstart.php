<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * Kickstart-specific processing.
 */
class Kickstart extends Theme_Base {

	/**
	 * immonex Kickstart Team related names/keys.
	 */
	const
		AGENT_POST_TYPE_NAME = 'inx_agent',
		AGENCY_POST_TYPE_NAME = 'inx_agency',
		PRIMARY_AGENT_META_KEY = '_inx_team_agent_primary',
		AGENT_FIRST_NAME_META_KEY = '_inx_team_agent_first_name',
		AGENT_LAST_NAME_META_KEY = '_inx_team_agent_last_name',
		AGENT_EMAIL_META_KEY = '_inx_team_agent_email',
		AGENT_ID_META_KEY = '_inx_team_agent_id',
		AGENCY_ID_META_KEY = '_inx_team_agency_id';

	public
		$theme_class_slug = 'kickstart';

	protected
		$property_post_type = 'inx_property';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 3.9
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->supported = array( 'references' );

		$this->temp = array(
			'unique_custom_field_meta' => array(),
			'post_images' => array(),
			'post_attachments' => array(),
			'property_links' => array(),
			'property_floor_plans' => array(
				'filenames' => array(),
				'ids' => array()
			),
			'property_epass_images' => array(
				'filenames' => array(),
				'ids' => array()
			),
			'local_videos' => array(
				'filenames' => array(),
				'ids' => array()
			),
			'videos' => array()
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		if ( $this->theme_options['disable_reference_deletion'] ) {
			add_filter( 'immonex_oi2wp_delete_property', array( $this, 'prevent_reference_deletion' ), 10, 2 );
		}

		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_post_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_property_custom_field', array( $this, 'add_custom_field_meta' ), 10, 3 );
		add_filter( 'immonex_oi2wp_add_grouped_post_meta', array( $this, 'add_custom_meta_details' ), 10, 3 );
		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );
		add_filter( 'immonex_oi2wp_taxonomy_parent_term_name', array( $this, 'maybe_add_location_parent' ), 10, 5 );
		add_filter( 'immonex_oi2wp_add_property_taxonomy_term', array( $this, 'maybe_disable_regional_addition_import' ), 10, 4 );
		add_filter( 'immonex_oi2wp_add_property_taxonomy_term', array( $this, 'maybe_extend_main_location_term' ), 15, 4 );
		add_filter( 'immonex_oi2wp_add_property_taxonomy_term', array( $this, 'maybe_modify_sold_term' ), 20, 4 );
		add_filter( 'immonex_oi2wp_current_post_id', array( $this, 'maybe_modify_current_post_id' ) );

		add_action( 'immonex_oi2wp_taxonomy_term_inserted', array( $this, 'add_term_meta_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 4 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'maybe_modify_marketing_type_term' ), 15, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'do_final_processing_steps' ), 20, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 25, 2 );
	} // __construct

	/**
	 * Possibly prevent a reference property from being deleted (callback).
	 *
	 * @since 4.3
	 *
	 * @param bool $delete Really delete this property?
	 * @param int $post_id ID of the related property post record.
	 *
	 * @return bool If reference property, return false (= skip deletion).
	 */
	public function prevent_reference_deletion( $delete, $post_id ) {
		$is_reference = get_post_meta( $post_id, '_immonex_is_reference', true );

		if ( $is_reference ) {
			$this->plugin->log->add( __( 'Reference property is NOT being deleted.', 'immonex-openimmo2wp' ), 'info' );
		}

		return $is_reference ? false : true;
	} // prevent_reference_deletion

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 * Add excerpt if not set already.
	 *
	 * @since 3.9
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
	 * Collect meta information on unique custom fields that will be stored during
	 * the final property processing steps.
	 *
	 * @since 3.9
	 *
	 * @param mixed[] $custom_field Complete custom field data.
	 * @param SimpleXMLElement $immobilie XML node of the related property object.
	 * @param int $post_id ID of the related property post record.
	 *
	 * @return mixed[] Unchanged custom field data array.
	 */
	public function add_custom_field_meta( $custom_field, $immobilie, $post_id ) {
		if ( $custom_field['unique'] ) {
			if ( ! isset( $this->temp['unique_custom_field_meta'][$post_id] ) ) {
				$this->temp['unique_custom_field_meta'][$post_id] = array();
			}
			$this->temp['unique_custom_field_meta'][$post_id][$custom_field['meta_key']] = $custom_field;
		}

		$this->save_temp_theme_data();

		return $custom_field;
	} // add_custom_field_meta

	/**
	 * Convert and save theme custom data in theme-specific format.
	 *
	 * @since 3.9
	 *
	 * @param mixed $grouped_meta_data Associative array of a meta data group.
	 * @param int $post_id ID of the related property post record.
	 * @param string $meta_key Meta key under which the serialized group array will be stored.
	 *
	 * @return mixed|bool Unchanged grouped meta data or false for additional features group.
	 */
	public function add_custom_meta_details( $grouped_meta_data, $post_id, $meta_key ) {
		if ( '_inx_details' !== $meta_key ) return $grouped_meta_data;

		$custom_meta = array();

		if ( count( $grouped_meta_data ) > 0 ) {
			foreach ( $grouped_meta_data as $key => $data ) {
				$field_meta = array(
					'mapping_source' => $data['mapping_source'],
					'value_before_filter' => $data['value_before_filter']
				);

				$custom_meta[] = array(
					'group' => $data['group'],
					'name' => $data['name'],
					'title' => $key,
					'value' => $data['value'],
					'meta_json' => json_encode( $field_meta )
				);
			}

			add_post_meta( $post_id, $meta_key, $custom_meta, true );
		}

		// DON'T save the original data.
		return false;
	} // add_custom_meta_details

	/**
	 * Determine the attachment type and perform the related processing steps.
	 *
	 * @since 3.9
	 *
	 * @param SimpleXMLElement $attachment Attachment XML node.
	 * @param int $post_id ID of the related property post record.
	 *
	 * @return array Unchanged attachment XML node.
	 */
	public function check_attachment( $attachment, $post_id ) {
		$format = \immonex\OpenImmo2Wp\Attachment_Utils::get_attachment_format_from_xml( $attachment, null, true );

		if (
			in_array( (string) $attachment['gruppe'], array( 'FILMLINK', 'LINKS', 'PANORAMA', 'ANBOBJURL' ) ) &&
			( isset( $attachment->daten->pfad ) || isset( $attachment->anhangtitel ) )
		) {
			$url = ! empty( $attachment->daten->pfad ) ?
				(string) $attachment->daten->pfad :
				(string) $attachment->anhangtitel;

			$title = $attachment->anhangtitel && (string) $attachment->anhangtitel !== $url ?
				(string) $attachment->anhangtitel : '';

			if ( in_array( (string) $attachment['gruppe'], array( 'FILMLINK', 'PANORAMA' ), true ) && 'http' !== strtolower( substr( $url, 0, 4 ) ) ) {
				if ( ! $format ) return $attachment;

				$valid_video_file_formats = apply_filters( $this->plugin->plugin_prefix . 'video_file_formats', array() );

				if (
					! empty( $valid_video_file_formats )
					&& in_array( $format, $valid_video_file_formats, true )
				) {
					$this->temp['videos'][$post_id][] = array(
						'provider' => 'local',
						'id' => 0,
						'url' => $url,
						'title' => $title
					);

					$this->save_temp_theme_data();

					return $attachment;
				}
			}

			if ( 'http' !== strtolower( substr( $url, 0, 4 ) ) ) return $attachment;

			$external_video_data = $this->plugin->video_utils->split_video_url( $url );

			if ( ! empty( $external_video_data ) ) {
				$oembed = $this->plugin->embed_utils->get_oembed_data( $external_video_data['url'] );

				if ( ! empty( $oembed ) ) {
					$external_video_data['oembed'] = $oembed;

					if ( ! $title ) {
						$title = ! empty( $oembed->title ) ? $oembed->title : '';
					}
				}

				$this->temp['videos'][$post_id][] = array_merge(
					$external_video_data,
					array(
						'title' => $title
					)
				);
				$this->save_temp_theme_data();

				// No further processing of this URL type.
				return false;
			} elseif ( $this->plugin->string_utils->is_virtual_tour_url( $url, apply_filters( $this->plugin->plugin_prefix . 'additional_virtual_tour_url_parts', array() ) ) ) {
				// Save virtual tour embed code.
				$embed_code = $this->get_virtual_tour_embed_code( apply_filters( $this->plugin->plugin_prefix . 'virtual_tour_embed_code_args', array( 'url' => $url ), 5 ) );
				add_post_meta( $post_id, '_inx_virtual_tour_embed_code', $embed_code, true );

				// No further processing of this URL type.
				return false;
			} else {
				// Extend array of normal (usually external) links for later saving.
				if ( isset( $attachment->anhangtitel ) && (string) $attachment->anhangtitel ) {
					$title = (string) $attachment->anhangtitel;
					$title_given = true;
				} else {
					// Set URL as as title (max 16 characters).
					$title = $this->plugin->string_utils->get_excerpt( trim( $url ), 16 );
					$title_given = false;
				}

				if ( 'ANBOBJURL' === (string) $attachment['gruppe'] && ! $title_given ) {
					$title = __( 'Property Details', 'immonex-openimmo2wp' );
				}

				if ( ! isset( $this->temp['property_links'][$post_id] ) ) {
					$this->temp['property_links'][$post_id] = array();
				}

				$this->temp['property_links'][$post_id][] = array(
					'url' => $url,
					'title' => $title
				);

				$this->save_temp_theme_data();
			}
		} elseif ( in_array( (string) $attachment['gruppe'], array( 'GRUNDRISS', 'EPASS-SKALA' ) ) ) { // TODO: Move KARTEN_LAGEPLAN images to location "tab".
			$valid_file_formats = array_merge(
				apply_filters( $this->plugin->plugin_prefix . 'image_file_formats', array() ),
				array( 'PDF' )
			);

			if ( in_array( $format, $valid_file_formats, true ) ) {
				// Attachment ist a floor plan or energy pass image: remember its filename for later processing.
				$type = (string) $attachment['gruppe'] === 'EPASS-SKALA' ? 'property_epass_images' : 'property_floor_plans';

				if ( ! isset( $this->temp[$type]['filenames'][$post_id] ) ) {
					$this->temp[$type]['filenames'][$post_id] = array();
				}

				$this->temp[$type]['filenames'][$post_id][] = pathinfo( $attachment->daten->pfad, PATHINFO_BASENAME );
				$this->temp[$type]['filenames'][$post_id] = array_unique( $this->temp[$type]['filenames'][$post_id] );
				$this->save_temp_theme_data();
			}
		}

		return $attachment;
	} // check_attachment

	/**
	 * Maybe add a location term parent (districts).
	 *
	 * @since 4.3.2 beta
	 *
	 * @param string $parent_term_name Current PARENT term name.
	 * @param string $element_value Actual term name.
	 * @param SimpleXMLElement $immobilie XML node of the related property object.
	 * @param mixed[] $mapping Array of mapping data.
	 * @param string $post_id Property ID.
	 *
	 * @return string Eventually updated parent term name.
	 */
	public function maybe_add_location_parent( $parent_term_name, $element_value, $immobilie, $mapping, $post_id ) {
		if (
			'location_child' === $this->theme_options['save_regional_addition_as'] &&
			'geo->regionaler_zusatz' === $mapping['source'] &&
			'inx_location' === $mapping['dest']
		) {
			// Return main location name as parent term name.
			return trim( (string) $immobilie->geo->ort );
		}

		if (
			'location_parent' === $this->theme_options['save_regional_addition_as'] &&
			'geo->ort' === $mapping['source'] &&
			'inx_location' === $mapping['dest'] &&
			! empty( trim( (string) $immobilie->geo->regionaler_zusatz ) )
		) {
			// Return "regional addition" as parent term name.
			return trim( (string) $immobilie->geo->regionaler_zusatz );
		}

		return $parent_term_name;
	} // maybe_add_location_parent

	/**
	 * Maybe disable the import of the regional addition (immobilie > geo > regionaler_zusatz)
	 * as separate taxonomy term.
	 *
	 * @since 4.9.4-beta
	 *
	 * @param string $element_value Term name.
	 * @param SimpleXMLElement $immobilie XML node of the related property object.
	 * @param mixed[] $mapping Array of mapping data.
	 * @param string $post_id Property ID.
	 *
	 * @return mixed[] Eventually modified term name.
	 */
	public function maybe_disable_regional_addition_import( $element_value, $immobilie, $mapping, $post_id ) {
		if (
			'geo->regionaler_zusatz' === $mapping['source'] &&
			'inx_location' === $mapping['dest'] &&
			in_array(
				$this->theme_options['save_regional_addition_as'],
				array( 'location_parent', 'text_att_bracket', 'text_att_hyphen', 'ignore' )
			)
		) {
			return false;
		}

		return $element_value;
	} // maybe_disable_regional_addition_import

	/**
	 * Maybe extend the main location term by the "regional addition".
	 *
	 * @since 4.9.4-beta
	 *
	 * @param string $element_value Term name.
	 * @param SimpleXMLElement $immobilie XML node of the related property object.
	 * @param mixed[] $mapping Array of mapping data.
	 * @param string $post_id Property ID.
	 *
	 * @return mixed[] Eventually modified term name.
	 */
	public function maybe_extend_main_location_term( $element_value, $immobilie, $mapping, $post_id ) {
		if (
			'geo->ort' === $mapping['source'] &&
			'inx_location' === $mapping['dest'] &&
			! empty( trim( (string) $immobilie->geo->regionaler_zusatz ) ) &&
			in_array(
				$this->theme_options['save_regional_addition_as'],
				array( 'text_att_bracket', 'text_att_hyphen' )
			)
		) {
			$regional_addition = trim( (string) $immobilie->geo->regionaler_zusatz );
			return 'text_att_bracket' === $this->theme_options['save_regional_addition_as'] ?
				"{$element_value} ({$regional_addition})" :
				"{$element_value}-{$regional_addition}";
		}

		return $element_value;
	} // maybe_extend_main_location_term

	/**
	 * Maybe change the name of the "sold" term (rent/leasehold properties).
	 *
	 * @since 3.9.4
	 *
	 * @param string $element_value Term name.
	 * @param SimpleXMLElement $immobilie XML node of the related property object.
	 * @param mixed[] $mapping Array of mapping data.
	 * @param string $post_id Property ID.
	 *
	 * @return mixed[] Eventually modified term name.
	 */
	public function maybe_modify_sold_term( $element_value, $immobilie, $mapping, $post_id ) {
		if ( ! in_array( strtolower( $element_value ), array( 'verkauft', 'sold' ) ) ) return $element_value;

		$marketing_types = apply_filters( $this->plugin->plugin_prefix . 'marketing_type_sold_term_replacements', array(
			'MIETE' => array(
				'Sold' => 'Rented',
				'sold' => 'rented',
				'Verkauft' => 'Vermietet',
				'verkauft' => 'vermietet'
			),
			'MIETE_PACHT' => array(
				'Sold' => 'Rented',
				'sold' => 'rented',
				'Verkauft' => 'Vermietet',
				'verkauft' => 'vermietet'
			),
			'ERBPACHT' => array(
				'Sold' => 'Leased',
				'sold' => 'leased',
				'Verkauft' => 'Verpachtet',
				'verkauft' => 'verpachtet'
			),
			'LEASING' => array(
				'Sold' => 'Leased',
				'sold' => 'leased',
				'Verkauft' => 'Verleast',
				'verkauft' => 'verleast'
			)
		) );

		foreach ( $marketing_types as $type => $replace_term_names ) {
			if (
				isset( $immobilie->objektkategorie->vermarktungsart[$type] ) && (
					'true' === strtolower( (string) $immobilie->objektkategorie->vermarktungsart[$type] ) ||
					'1' === (string) $immobilie->objektkategorie->vermarktungsart[$type]
				)
			) {
				foreach ( $replace_term_names as $replace => $by ) {
					if ( $element_value === $replace ) return $by;
				}
			}
		}

		return $element_value;
	} // maybe_modify_sold_term

	/**
	 * Check if an alternative property post ID is given as GET parameter.
	 *
	 * @since 4.7.0
	 *
	 * @param int|string $post_id Current property post ID.
	 *
	 * @return int|string Alternative ID from GET parameter.
	 */
	public function maybe_modify_current_post_id( $post_id ) {
		if ( ! empty( $_GET['inx-property-id'] ) ) {
			$post_id = (int) $_GET['inx-property-id'];
		}

		return $post_id;
	} // maybe_modify_current_post_id

	/**
	 * Save meta information for new taxonomy terms.
	 *
	 * @since 3.9
	 *
	 * @param mixed[] $term_data Array of term data.
	 * @param mixed[] $meta_data Data of new term to be inserted.
	 * @param SimpleXMLElement $immobilie XML node of the related property object.
	 */
	public function add_term_meta_data( $term_data, $meta_data, $immobilie ) {
		if ( isset( $term_data['term_id'] ) ) {
			add_term_meta(
				$term_data['term_id'],
				'_inx_term_meta',
				$meta_data,
				true
			);
		}
	} // add_term_meta_data

	/**
	 * Save the property address and/or coordinates (post meta) for geocoding.
	 *
	 * @since 3.9
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

		if ( ! $geodata['address_geocode_is_coordinates'] ) {
			// Save property map address.
			add_post_meta( $post_id, '_inx_full_address', $address, true );
		}

		add_post_meta( $post_id, '_inx_street', $geodata['street'], true );

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
			add_post_meta( $post_id, '_inx_lat', $lat, true );
			add_post_meta( $post_id, '_inx_lng', $lng, true );
		}
	} // save_property_location

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 3.9
	 *
	 * @param string $att_id Attachment ID.
	 * @param mixed $valid_image_formats Array of valid image file format suffixes.
	 * @param mixed $valid_misc_formats Array of valid non-image file format suffixes.
	 * @param mixed $valid_video_formats Array of valid video file format suffixes.
	 */
	public function add_attachment_data( $att_id, $valid_image_formats, $valid_misc_formats, $valid_video_file_formats ) {
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
			$epass_images = ! empty( $this->temp['property_epass_images']['filenames'][$p->post_parent] ) ?
				$this->get_extended_filenames( $this->temp['property_epass_images']['filenames'][$p->post_parent] ) :
				array();

			$local_videos = array();
			if ( ! empty( $this->temp['videos'][$p->post_parent] ) ) {
				foreach ( $this->temp['videos'][$p->post_parent] as $video ) {
					if ( 'local' === $video['provider'] ) {
						$local_videos[] = $video['url'];
					}
				}

				if ( count( $local_videos ) > 0 ) {
					$local_videos = $this->get_extended_filenames( $local_videos );
				}
			}

			if ( ! empty( $floor_plans ) && in_array( $filename, $floor_plans, true ) ) {
				// Remember floor plan attachment ID, exclude from gallery.
				if ( ! isset( $this->temp['property_floor_plans']['ids'][$p->post_parent] ) ) {
					$this->temp['property_floor_plans']['ids'][$p->post_parent] = array();
				}
				$this->temp['property_floor_plans']['ids'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			} elseif ( ! empty( $epass_images ) && in_array( $filename, $epass_images, true ) ) {
				// Remember energy pass image attachment ID, exclude from gallery.
				if ( ! isset( $this->temp['property_epass_images']['ids'][$p->post_parent] ) ) {
					$this->temp['property_epass_images']['ids'][$p->post_parent] = array();
				}
				$this->temp['property_epass_images']['ids'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			} elseif ( ! empty( $local_videos ) && in_array( $filename, $local_videos, true ) ) {
				foreach ( $this->temp['videos'][$p->post_parent] as $i => $video ) {
					if ( $filename === $this->get_plain_basename( pathinfo( $video['url'], PATHINFO_FILENAME ) ) ) {
						// Add attachment ID of local video file and convert filename to URL.
						$this->temp['videos'][$p->post_parent][$i]['id'] = $att_id;
						$this->temp['videos'][$p->post_parent][$i]['url'] = wp_get_attachment_url( $att_id );
						break;
					}
				}

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
	 * @since 3.9
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['post_images'][$post_id] ) ) {
			$this->temp['post_images'][$post_id] = $this->check_attachment_ids( $this->temp['post_images'][$post_id] );

			// Save property gallery image IDs.
			add_post_meta( $post_id, '_inx_gallery_images', $this->temp['post_images'][$post_id], true );
			unset( $this->temp['post_images'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['post_attachments'][$post_id] ) ) {
			$this->temp['post_attachments'][$post_id] = $this->check_attachment_ids( $this->temp['post_attachments'][$post_id] );

			// Save property file attachment IDs.
			add_post_meta( $post_id, '_inx_file_attachments', $this->temp['post_attachments'][$post_id], true );
			unset( $this->temp['post_attachments'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['property_links'][$post_id] )	) {
			// Save property (usually external) links.
			add_post_meta( $post_id, '_inx_links', $this->temp['property_links'][$post_id], true );
			unset( $this->temp['property_links'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['videos'][$post_id] ) ) {
			// Save first video URL as separate custom field (legacy compatibility).
			add_post_meta( $post_id, '_inx_video_url', $this->temp['videos'][$post_id][0]['url'], true );

			add_post_meta( $post_id, '_inx_videos', $this->temp['videos'][$post_id], true );
			unset( $this->temp['videos'][$post_id] );
		}

		foreach ( array( 'property_floor_plans' => '_inx_floor_plans', 'property_epass_images' => '_inx_epass_images' ) as $type => $field_name ) {
			if ( ! empty( $this->temp[$type]['ids'][$post_id] )	) {
				// Save floor plan or energy pass image data.
				$this->temp[$type]['ids'][$post_id] = $this->check_attachment_ids( $this->temp[$type]['ids'][$post_id] );

				// Save property floor plan or energy pass image (attachment) IDs.
				add_post_meta( $post_id, $field_name, $this->temp[$type]['ids'][$post_id], true );
				unset( $this->temp[$type]['ids'][$post_id] );
				$this->save_temp_theme_data();
			}
		}
	} // save_attachment_data

	/**
	 * Maybe change the name of the sale/rent term (reference properties).
	 *
	 * @since 4.3
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function maybe_modify_marketing_type_term( $post_id, $immobilie ) {
		if ( get_post_meta( $post_id, '_immonex_is_reference', true ) ) {
			$this->maybe_replace_terms( $post_id, 'inx_marketing_type',	$this->get_reference_term_replacement_map() );
		}
	} // maybe_modify_marketing_type_term

	/**
	 * Do the final processing steps for a property object.
	 *
	 * @since 3.9
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function do_final_processing_steps( $post_id, $immobilie ) {
		$default_values = array(
			'_inx_primary_price' => 0,
			'_inx_primary_area' => 0,
			'_inx_commercial_area' => 0,
			'_inx_retail_area' => 0,
			'_inx_office_area' => 0,
			'_inx_gastronomy_area' => 0,
			'_inx_storage_area' => 0,
			'_inx_plot_area' => 0,
			'_inx_living_area' => 0,
			'_inx_usable_area' => 0,
			'_inx_basement_area' => 0,
			'_inx_attic_area' => 0,
			'_inx_misc_area' => 0,
			'_inx_garden_area' => 0,
			'_inx_total_area' => 0,
			'_inx_bedrooms' => 0,
			'_inx_living_bedrooms' => 0,
			'_inx_bathrooms' => 0,
			'_inx_total_rooms' => 0,
			'_inx_primary_units' => 0,
			'_inx_living_units' => 0,
			'_inx_commercial_units' => 0,
			'_inx_property_title' => '',
			'_inx_property_descr' => '',
			'_inx_short_descr' => '',
			'_inx_location_descr' => '',
			'_inx_features_descr' => '',
			'_inx_misc_descr' => '',
			'_inx_property_id' => '',
			'_inx_full_address' => '',
			'_inx_street' => '',
		);

		foreach ( $default_values as $meta_key => $meta_value ) {
			if ( ! get_post_meta( $post_id, $meta_key, true ) ) {
				add_post_meta( $post_id, $meta_key, $meta_value, true );
			}
		}

		// Save meta data for the property's unique custom fields.
		if ( ! empty( $this->temp['unique_custom_field_meta'][$post_id] ) ) {
			foreach ( $this->temp['unique_custom_field_meta'][$post_id] as $meta_name => $meta_data ) {
				// Field meta data: key with two underscores (e.g. __inx_fieldname).
				add_post_meta( $post_id, '_' . $meta_name, $meta_data, true );

				if ( isset( $meta_data['meta_name'] ) && $meta_data['meta_name'] ) {
					// Save an alternative field name from the mapping table that points
					// to the native field name (e.g. _alias.name > _inx_fieldname).
					add_post_meta( $post_id, trim( $meta_data['meta_name'] ), $meta_name, true );
				}
			}
		}

		// Property for sale?
		$is_sale = 'true' === strtolower( (string) $immobilie->objektkategorie->vermarktungsart['KAUF'] ) ||
			'1' === (string) $immobilie->objektkategorie->vermarktungsart['KAUF'];
		add_post_meta( $post_id, '_inx_is_sale', $is_sale ? 1 : 0, true );

		// Save the property's post ID as meta VALUE to make related queries possible.
		add_post_meta( $post_id, '_inx_post_id', $post_id, true );

		/**
		 * Remove unnecessary location parent terms.
		 */
		$location_terms = wp_get_post_terms( $post_id, 'inx_location' );
		if ( count( $location_terms ) > 0 ) {
			$parent_ids_to_delete = array();

			foreach ( $location_terms as $term ) {
				if ( $term->parent ) $parent_ids_to_delete[] = $term->parent;
			}

			if ( count( $parent_ids_to_delete ) ) {
				wp_remove_object_terms( $post_id, $parent_ids_to_delete, 'inx_location' );
			}
		}

		/**
		 * Possibly update the description of a group term if the related
		 * master property has been imported.
		 */
		$project_taxonomy = 'inx_project';
		$master_group_id = trim( (string) $immobilie->verwaltung_techn->master );
		if ( $master_group_id && taxonomy_exists( $project_taxonomy ) ) {
			$group_terms = wp_get_post_terms( $post_id, $project_taxonomy );

			if ( count( $group_terms ) > 0 ) {
				foreach ( $group_terms as $term ) {
					$master_title = trim( (string) $immobilie->freitexte->objekttitel );

					if (
						$master_group_id === $term->name &&
						$term->description !== $master_title
					) {
						wp_update_term(
							$term->term_id,
							$project_taxonomy,
							array( 'description' => $master_title )
						);
					}
				}
			}
		}
	} // do_final_processing_steps

	/**
	 * Try to determine/save the property user/agent.
	 *
	 * @since 4.7.0
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_agent( $post_id, $immobilie ) {
		$post = get_post( $post_id );
		// ID of author that has been automatically set before (e.g. based on import folder).
		$author_id = $post && $post->post_author ? $post->post_author : false;

		$primary_agent_meta_key = apply_filters(
			$this->plugin->plugin_prefix . 'primary_agent_meta_key',
			self::PRIMARY_AGENT_META_KEY
		);

		$agent_post_type_name = apply_filters(
			$this->plugin->plugin_prefix . 'agent_post_type_name',
			self::AGENT_POST_TYPE_NAME
		);

		$agent_id_meta_key = apply_filters(
			$this->plugin->plugin_prefix . 'agent_id_meta_key',
			self::AGENT_ID_META_KEY
		);

		$agency_id_meta_key = apply_filters(
			$this->plugin->plugin_prefix . 'agency_id_meta_key',
			self::AGENCY_ID_META_KEY
		);

		$agent_data = $this->get_agent_data( $immobilie );
		$name_contact = $agent_data['name'];

		if ( $name_contact ) {
			$this->plugin->log->add( wp_sprintf( __( 'Contact person (Agent): %s', 'immonex-openimmo2wp' ), $name_contact ), 'debug' );
		}

		$user_agency_id = false;
		$user = $this->get_agent_user( $immobilie, array(), true, $author_id );
		if ( $user ) {
			// Set found user as property post author.
			$this->update_post_author( $post->ID, $user->ID );

			$user_agency_id = get_user_meta( $user->ID, ltrim( $agency_id_meta_key, '_' ), true );
			$user_agent_id = get_user_meta( $user->ID, ltrim( $agent_id_meta_key, '_' ), true );

			if (
				$user_agent_id &&
				get_post_type( (int) $user_agent_id ) === $agent_post_type_name &&
				$this->assign_user_linked_agent( $user_agent_id, $user_agency_id, $post_id, $primary_agent_meta_key, $immobilie )
			) {
				// Saved related agent ID.
				return;
			}
		}

		$agent_first_name_meta_key = apply_filters(
			$this->plugin->plugin_prefix . 'agent_first_name_meta_key',
			self::AGENT_FIRST_NAME_META_KEY
		);

		$agent_last_name_meta_key = apply_filters(
			$this->plugin->plugin_prefix . 'agent_last_name_meta_key',
			self::AGENT_LAST_NAME_META_KEY
		);

		$agent_email_meta_key = apply_filters(
			$this->plugin->plugin_prefix . 'agent_email_meta_key',
			self::AGENT_EMAIL_META_KEY
		);

		$compare_meta = array(
			'first_name' => $agent_first_name_meta_key,
			'last_name' => $agent_last_name_meta_key,
			'email' => $agent_email_meta_key
		);

		$agent_id = false;
		if ( $user_agency_id ) {
			$additional_args = array(
				'meta_query' => array(
					'relation' => 'AND',
					array(
						'key' => $agency_id_meta_key,
						'value' => $user_agency_id
					)
				)
			);
		} else {
			$additional_args = array();
		}
		$agent = $this->get_agent(
			$immobilie,
			$agent_post_type_name,
			$compare_meta,
			$additional_args,
			true,
			true,
			true
		);

		if ( $agent ) {
			$agent_id = $agent->ID;
			$hook_data = array(
				'property_id' => $post_id,
				'agent_id' => $agent->ID,
				'user_id' => $user ? $user->ID : $author_id,
				'user_agency_id' => $user_agency_id,
				'import_folder' => $this->plugin->current_import_folder
			);

			try {
				$agent_id = apply_filters(
					$this->plugin->plugin_prefix . 'assign_agent',
					$agent->ID,
					$hook_data,
					$immobilie,
					dirname( $this->plugin->current_import_xml_file ),
					$this->theme_options['enable_auto_contacts']
				);
			} catch ( \Exception $e ) {
				$this->plugin->log->add(
					wp_sprintf(
						'[immonex Kickstart] ' . __( 'Error on assigning an agent: %s', 'immonex-openimmo2wp' ),
						$e->getMessage()
					),
					'error'
				);
			}
		}

		if ( ! $user && ! $agent ) {
			// Try to determine the agent ID based on the property post author's user record.
			$user_agency_id = get_user_meta( $author_id, ltrim( $agency_id_meta_key, '_' ), true );
			$user_agent_id = get_user_meta( $author_id, ltrim( $agent_id_meta_key, '_' ), true );

			if (
				$user_agent_id &&
				get_post_type( (int) $user_agent_id ) === $agent_post_type_name &&
				$this->assign_user_linked_agent( $user_agent_id, $user_agency_id, $post_id, $primary_agent_meta_key, $immobilie )
			) {
				// Agent available - related ID saved.
				return;
			}
		}

		if ( ! $agent && $this->theme_options['enable_auto_contacts'] ) {
			$hook_data = array(
				'property_id' => $post_id,
				'user_id' => $user ? $user->ID : $author_id,
				'user_agency_id' => $user_agency_id,
				'import_folder' => $this->plugin->current_import_folder,
				'is_demo' => get_post_meta( $post_id, '_immonex_is_demo', true ) ? true : false
			);

			try {
				$agent_id = apply_filters(
					$this->plugin->plugin_prefix . 'create_agent',
					false,
					$hook_data,
					$immobilie,
					dirname( $this->plugin->current_import_xml_file )
				);
			} catch ( \Exception $e ) {
				$this->plugin->log->add(
					wp_sprintf(
						'[immonex Kickstart] ' . __( 'Error on creating an agent: %s', 'immonex-openimmo2wp' ),
						$e->getMessage()
					),
					'error'
				);
			}
		}

		if ( $agent_id ) {
			add_post_meta( $post_id, $primary_agent_meta_key, $agent_id, true );

			$agency_id = get_post_meta( $agent_id, $agency_id_meta_key, true );
			if ( $agency_id ) {
				add_post_meta( $post_id, $agency_id_meta_key, $agency_id, true );
			}
		}
	} // save_agent

	/**
	 * Assign the agent ID included in a user record to a property.
	 *
	 * @since 4.9.8-beta
	 *
	 * @param string $user_agent_id Agent post ID.
	 * @param string|bool $user_agency_id Agency post ID or false if not given.
	 * @param string $post_id Property post ID.
	 * @param string $primary_agent_meta_key Name of the property custom field that holds the primary agent ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return bool True on valid agent record.
	 */
	private function assign_user_linked_agent( $user_agent_id, $user_agency_id, $post_id, $primary_agent_meta_key, $immobilie ) {
		$agent = get_post( $user_agent_id );
		if ( ! $agent ) {
			return false;
		}

		$this->plugin->log->add(
			wp_sprintf(
				__( 'Assigning agent linked to user: %s (%d)', 'immonex-openimmo2wp' ),
				$agent->post_title,
				$agent->ID
			),
			'debug'
		);

		$hook_data = array(
			'property_id' => $post_id,
			'agent_id' => $agent->ID,
			'user_id' => $user->ID,
			'user_agency_id' => $user_agency_id,
			'import_folder' => $this->plugin->current_import_folder
		);
		$agent_id = apply_filters(
			$this->plugin->plugin_prefix . 'assign_agent',
			$agent->ID,
			$hook_data,
			$immobilie,
			dirname( $this->plugin->current_import_xml_file ),
			$this->theme_options['enable_auto_contacts']
		);

		if ( $agent_id ) {
			add_post_meta( $post_id, $primary_agent_meta_key, $agent_id, true );
		}

		return true;
	} // assign_user_linked_agent

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 3.9
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
	 * @since 3.9
	 *
	 * @param mixed $fields Original fields array.
	 *
	 * @return array Extended fields array.
	 */
	public function extend_fields( $fields ) {
		$fields = array_merge( $fields, array(
			array(
				'name' => $this->theme_class_slug . '_disable_reference_deletion',
				'type' => 'checkbox',
				'label' => __( 'Disable reference deletion', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Prevent properties marked as <strong>reference objects</strong> from being deleted on import.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_enable_auto_contacts',
				'type' => 'checkbox',
				'label' => __( 'Auto contacts', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => wp_sprintf(
						__( 'Enable automatic creation and update of agents and agencies (<strong><a href="%s" target="_blank">Team Add-on</a> required</strong>).', 'immonex-openimmo2wp' ),
						'https://de.wordpress.org/plugins/immonex-kickstart-team/'
					)
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
				'name' => $this->theme_class_slug . '_save_regional_addition_as',
				'type' => 'select',
				'label' => __( 'Save "regional addition" as', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'This option applies if the OpenImmo element <code>geo &gt; regionaler_zusatz</code> is mapped to the taxonomy <code>inx_location</code> (default).', 'immonex-openimmo2wp' ),
					'options' => array(
						'location_child' => __( 'location child category: City ➞ Region', 'immonex-openimmo2wp' ),
						'location_parent' => __( 'location parent category: Region ➞ City', 'immonex-openimmo2wp' ),
						'location_main_level' => __( 'additional location term on main level', 'immonex-openimmo2wp' ),
						'text_att_bracket' => __( 'text attachment in brackets: City (Region)', 'immonex-openimmo2wp' ),
						'text_att_hyphen' => __( 'text attachment with hyphen: City-Region', 'immonex-openimmo2wp' ),
						'ignore' => __( 'ignore (special cases)', 'immonex-openimmo2wp' )
					)
				)
			)
		) );

		return $fields;
	} // extend_fields

} // class Kickstart
