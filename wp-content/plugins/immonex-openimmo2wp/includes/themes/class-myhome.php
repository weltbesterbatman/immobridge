<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * MyHome-specific processing.
 */
class MyHome extends Theme_Base {

	public
		$theme_class_slug = 'myhome';

	protected
		$property_post_type = 'estate',
		$theme_agent_user_roles = array( 'agent' ),
		$acf_available = false;

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 3.7
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->acf_available = function_exists( 'update_field' );

		$this->initial_widgets = array(
			'mh-property-sidebar' => array(
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
			'post_attachments' => array()
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_post_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_property_custom_field_data', array( $this, 'save_custom_field_data_acf' ), 10, 3 );
		add_filter( 'immonex_oi2wp_add_grouped_post_meta', array( $this, 'add_custom_meta_details_acf' ), 10, 4 );
		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );

		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 15, 2 );
	} // __construct

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 * Add excerpt if not set already.
	 *
	 * @since 3.7
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
	 * Add custom field data using ACF.
	 *
	 * @since 3.7
	 *
	 * @param array $data Custom field data.
	 * @param SimpleXMLElement $immobilie XML node of the related property object.
	 * @param int $post_id ID of the related property post record.
	 *
	 * @return mixed[]|bool Unchanged meta data or false if field data COULD be saved via ACF.
	 */
	public function save_custom_field_data_acf( $data, $immobilie, $post_id ) {
		if ( ! $this->acf_available ) {
			// Seems that ACF has not been installed or embedded yet.
			$this->plugin->log->add( __( 'Function update_field not available, additional features could not be saved. (Seems that ACF has not been installed or embedded yet.)', 'immonex-openimmo2wp' ), 'debug' );
			return $data;
		}

		update_field( $data['meta_key'], $data['meta_value'], $post_id );

		// DON'T save this field data using the original WP functions.
		return false;
	} // save_custom_field_data_acf

	/**
	 * Convert and save theme custom data in theme-specific format (ACF).
	 *
	 * @since 3.7
	 *
	 * @param mixed $grouped_meta_data Associative array of a meta data group.
	 * @param int $post_id ID of the related property post record.
	 * @param string $meta_key Meta key under which the serialized group array will be stored.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return mixed|bool Unchanged grouped meta data or false for additional features group.
	 */
	public function add_custom_meta_details_acf( $grouped_meta_data, $post_id, $meta_key, $immobilie ) {
		if ( 'estate_additional_features' !== $meta_key ) return $grouped_meta_data;

		if ( ! $this->acf_available ) {
			// Seems that ACF has not been installed or embedded yet.
			$this->plugin->log->add( __( 'Function update_field not available, additional features could not be saved. (Seems that ACF has not been installed or embedded yet.)', 'immonex-openimmo2wp' ), 'debug' );
			return false;
		}

		$custom_meta = array();

		if ( count( $grouped_meta_data ) > 0 ) {
			foreach ( $grouped_meta_data as $key => $data ) {
				$custom_meta[] = array(
					'estate_additional_feature_name' => $key,
					'estate_additional_feature_value' => str_replace( "\n", " |\n", $data['value'] )
				);
			}

			update_field( 'estate_additional_features', $custom_meta, $post_id );
		}

		// DON'T save the original data.
		return false;
	} // add_custom_meta_details_acf

	/**
	 * Check if attachment is a video URL (YouTube/Vimeo) or a floor plan and perform
	 * the related processing steps.
	 *
	 * @since 3.7
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
				if ( $this->acf_available ) {
					update_field( 'estate_video', $url, $post_id );
				} else {
					add_post_meta( $post_id, 'estate_video', $url, true );
				}

				// DON'T import this attachment.
				return false;
			} elseif ( $this->plugin->string_utils->is_virtual_tour_url( $url, apply_filters( $this->plugin->plugin_prefix . 'additional_virtual_tour_url_parts', array() ) ) ) {
				// Save virtual tour embed code.
				$embed_code = $this->get_virtual_tour_embed_code( apply_filters( $this->plugin->plugin_prefix . 'virtual_tour_embed_code_args', array( 'url' => $url ), 5 ) );
				if ( $this->acf_available ) {
					update_field( 'virtual_tour', $embed_code, $post_id );
				} else {
					add_post_meta( $post_id, 'virtual_tour', $embed_code, true );
				}

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
	 * Save the property address and/or coordinates (post meta) for geocoding.
	 *
	 * @since 3.7
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

		$location = array(
			'address' => $address,
			'lat' => 0,
			'lng' => 0
		);

		if ( $geodata['publishing_approved'] ) {
			$street_taxonomy = get_taxonomy( 'street' ) ? 'street' : false;
			if ( ! $street_taxonomy ) $street_taxonomy = get_taxonomy( 'strasse' ) ? 'strasse' : false;
			$street_taxonomy = apply_filters( $this->plugin->plugin_prefix . 'myhome_street_taxonomy', $street_taxonomy );

			if ( $street_taxonomy ) {
				// Save street as taxonomy term.
				$term = get_term_by( 'name', $geodata['street'], $street_taxonomy );
				if ( $term ) {
					$term_id = $term->term_id;
				} else {
					// Insert new street term first.
					$term = wp_insert_term( $geodata['street'], $street_taxonomy );
					if ( is_wp_error( $term ) ) {
						$this->plugin->log->add( wp_sprintf( __( 'Inserting a new street term failed (%s / %s)', 'immonex-openimmo2wp' ), $geodata['street'], $term->get_error_message() ), 'debug' );
					} else {
						$term_id = $term['term_id'];
					}
				}

				wp_set_object_terms( $post_id, (int) $term_id, $street_taxonomy, false );
			}
		}

		if ( $geodata['publishing_approved'] && $geodata['lat'] && $geodata['lng'] ) {
			$location['lat'] = $geodata['lat'];
			$location['lng'] = $geodata['lng'];
		} elseif (
			$this->plugin->plugin_options['geo_always_use_coordinates'] &&
			$geodata['lat'] && $geodata['lng']
		) {
			$location['lat'] = $geodata['lat'];
			$location['lng'] = $geodata['lng'];
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
				$location['lat'] = $geo['lat'];
				$location['lng'] = $geo['lng'];

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

		if ( $location['lat'] && $location['lng'] ) {
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), $location['lat'] . ', ' . $location['lng'] ), 'debug' );
		}

		if ( $this->acf_available ) {
			update_field( 'estate_location', $location, $post_id );
		} else {
			add_post_meta( $post_id, 'estate_location', $location, true );
		}
	} // save_property_location

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 3.7
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
	 * @since 3.7
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['post_images'][$post_id] ) ) {
			$this->temp['post_images'][$post_id] = $this->check_attachment_ids( $this->temp['post_images'][$post_id] );

			// Save gallery data.
			if ( $this->acf_available ) {
				update_field( 'estate_gallery', $this->temp['post_images'][$post_id], $post_id );
			} else {
				add_post_meta( $post_id, 'estate_gallery', $this->temp['post_images'][$post_id], true );
			}

			unset( $this->temp['post_images'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['property_floor_plans']['ids'][$post_id] ) ) {
			// Save floor plan data.
			$this->temp['property_floor_plans']['ids'][$post_id] = $this->check_attachment_ids( $this->temp['property_floor_plans']['ids'][$post_id] );

			$floor_plans = array();

			foreach ( $this->temp['property_floor_plans']['ids'][$post_id] as $cnt => $att_id ) {
				$attachment = wp_prepare_attachment_for_js( $att_id );

				$floor_plans[] = array(
					'estate_plans_name' => $attachment['title'] ? $attachment['title'] : __( 'Floor Plan', 'immonex-openimmo2wp' ),
					'estate_plans_image' => $att_id
				);
			}

			if ( $this->acf_available ) {
				update_field( 'estate_plans', $floor_plans, $post_id );
			} else {
				$this->plugin->log->add( __( 'Function update_field not available, floor plan data could not be saved. (Seems that ACF has not been installed or embedded yet.)', 'immonex-openimmo2wp' ), 'debug' );
			}

			unset( $this->temp['property_floor_plans']['ids'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['post_attachments'][$post_id] ) ) {
			$this->temp['post_attachments'][$post_id] = $this->check_attachment_ids( $this->temp['post_attachments'][$post_id] );

			$file_attachments = array();

			foreach ( $this->temp['post_attachments'][$post_id] as $cnt => $att_id ) {
				$attachment = wp_prepare_attachment_for_js( $att_id );

				$file_attachments[] = array(
					'estate_attachment_name' => $attachment['title'],
					'estate_attachment_file' => $att_id
				);
			}

			// Save file attachment data.
			if ( $this->acf_available ) {
				update_field( 'estate_attachments', $file_attachments, $post_id );
			} else {
				$this->plugin->log->add( __( 'Function update_field not available, file attachment data could not be saved. (Seems that ACF has not been installed or embedded yet.)', 'immonex-openimmo2wp' ), 'debug' );
			}

			unset( $this->temp['post_attachments'][$post_id] );
			$this->save_temp_theme_data();
		}
	} // save_attachment_data

	/**
	 * Try to determine/save the property agent user.
	 *
	 * @since 3.7
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

		$user = $this->get_agent_user( $immobilie, array( 'role__in' => $this->theme_agent_user_roles ), true, $author_id );
		if ( $user ) {
			if ( $user->ID !== $author_id ) {
				// Save new author.
				$this->update_post_author( $post->ID, $user->ID );
			}
		}
	} // save_agent

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 3.7
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
	 * @since 3.7
	 *
	 * @param mixed $fields Original fields array.
	 *
	 * @return array Extended fields array.
	 */
	public function extend_fields( $fields ) {
		$fields = array_merge( $fields, array(
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

} // class MyHome
?>