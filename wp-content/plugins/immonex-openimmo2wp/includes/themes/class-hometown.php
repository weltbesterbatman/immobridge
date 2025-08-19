<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * Hometown-specific processing.
 */
class Hometown extends Theme_Base {

	public
		$theme_class_slug = 'hometown';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 1.1.3
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->initial_widgets = array(
			'sidebar-left' => array(
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
		add_filter( 'immonex_oi2wp_add_grouped_post_meta', array( $this, 'add_custom_meta_details' ), 10, 3 );
		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );

		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 15, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'do_final_processing_steps' ), 20, 2 );
	} // __construct

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 * Add excerpt if not set already.
	 *
	 * @since 1.1.3
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
	 * @since 1.1.3
	 *
	 * @param array $grouped_meta_data Associative array of a meta data group.
	 * @param int $post_id ID of the related property post record.
	 * @param string $meta_key Meta key under which the serialized group array will be stored.
	 */
	public function add_custom_meta_details( $grouped_meta_data, $post_id, $meta_key ) {
		if ( '_meta_detail' !== $meta_key ) return $grouped_meta_data;

		$custom_meta = array();

		if ( count( $grouped_meta_data ) > 0 ) {
			foreach ( $grouped_meta_data as $key => $data ) {
				$stack_id = count( $custom_meta );

				$custom_meta[] = array(
					'stack_id' => $stack_id,
					'template_id' => 'detail',
					'stack_title' => $key,
					'detail' => str_replace( "\n", " |\n", $data['value'] )
				);
			}

			add_post_meta( $post_id, $meta_key, $custom_meta, true );
		}

		// DON'T save the original data.
		return false;
	} // add_custom_meta_details

	/**
	 * Check if attachment is a video URL (YouTube/Vimeo) or a floor plan and perform
	 * the related processing steps.
	 *
	 * @since 3.5
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
				// Save video URL as post meta.
				add_post_meta( $post_id, '_meta_video_url', $url, true );

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
	 * @since 1.1.3
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

		$location = array( $geodata['address_geocode'] );

		if ( $geo_coordinates && is_array( $geo_coordinates ) ) {
			$location = array_merge( $location, array( $geo_coordinates['lat'], $geo_coordinates['lng'] ) );
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), $geo_coordinates['lat'] . ', ' . $geo_coordinates['lng'] ), 'debug' );
 		} elseif ( isset( $geocoding_failed ) && $geocoding_failed ) {
			$geocoding_status = $this->get_geocoding_status( $geodata['address_geocode'], $geodata['country_code_iso2'] );
			$this->plugin->log->add( wp_sprintf( __( 'Geocoding failed (%s)', 'immonex-openimmo2wp' ), $geocoding_status ? $geocoding_status : __( 'unknown reason', 'immonex-openimmo2wp' ) ), 'debug' );
		}

		add_post_meta( $post_id, '_meta_location', $location, true );
		if ( $geodata['publishing_approved'] ) {
			add_post_meta( $post_id, '_meta_location_text', $geodata['address_output'], true );
		} else {
			add_post_meta( $post_id, '_meta_location_text', $this->plugin->multilang_get_string_translation( $this->theme_options['address_publishing_not_approved_message'] ) );
		}
	} // save_property_location

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 1.1.3
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

			// Remove counter from filename for comparison (floor plans).
			$filename = $this->get_plain_basename( $fileinfo['filename'] );

			// Possibly extend filename arrays by sanitized versions.
			$floor_plans = $this->get_extended_filenames( $this->temp['property_floor_plans']['filenames'][$p->post_parent] );

			if ( ! empty( $floor_plans ) && in_array( $filename, $floor_plans, true )	) {
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
	 * @since 1.1.3
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['post_images'][$post_id] ) ) {
			$this->temp['post_images'][$post_id] = $this->check_attachment_ids( $this->temp['post_images'][$post_id] );

			add_post_meta( $post_id, '_meta_gallery', $this->temp['post_images'][$post_id], true );
			unset( $this->temp['post_images'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['post_attachments'][$post_id] ) ) {
			// Prepare and save property file attachment meta data.
			$this->temp['post_attachments'][$post_id] = $this->check_attachment_ids( $this->temp['post_attachments'][$post_id] );

			$property_attachments = array();

			foreach ( $this->temp['post_attachments'][$post_id] as $attachment_post_id ) {
				$attachment = get_post( $attachment_post_id );

				if ($attachment) {
					$property_attachments[] = array(
						'stack_id' => count($property_attachments),
						'template_id' => 'attachment',
						'stack_title' => $attachment->post_title,
						'file' => $attachment_post_id
					);
				}
			}

			add_post_meta( $post_id, '_meta_attachment', $property_attachments, true );

			unset( $this->temp['post_attachments'][$post_id] );
			$this->save_temp_theme_data();
		}

		if ( ! empty( $this->temp['property_floor_plans']['ids'][$post_id] ) ) {
			// Save floor plan IDs array.
			$this->temp['property_floor_plans']['ids'][$post_id] = $this->check_attachment_ids( $this->temp['property_floor_plans']['ids'][$post_id] );

			add_post_meta( $post_id, '_meta_floorplan', $this->temp['property_floor_plans']['ids'][$post_id], true );

			unset( $this->temp['property_floor_plans']['ids'][$post_id] );
			$this->save_temp_theme_data();
		}

		// External Video available? Set thumbnail as video image if so.
		$video_url = get_post_meta( $post_id, '_meta_video_url', true );
		if ( $video_url ) {
			$thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );
			if ( $thumbnail_id ) add_post_meta( $post_id, '_meta_video_thumb', $thumbnail_id, true );
		}
	} // save_attachment_data

	/**
	 * Try to determine/save the property agent.
	 *
	 * @since 1.1.3
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

		$agent = $this->get_agent( $immobilie, 'agent', array( 'email' => '_meta_email' ), array(), true );
		if ( $agent ) {
			// Save agent ID (as serialized array for theme versions < 2.0 and >= 2.3).
			add_post_meta( $post_id, '_meta_agent', version_compare( $this->theme_version, '2.0', '<' ) || version_compare( $this->theme_version, '2.3', '>=' ) ? array( (string) $agent->ID ) : $agent->ID, true );
		}
	} // save_agent

	/**
	 * Do the final processing steps for a property object (e.g. check price custom field).
	 *
	 * @since 2.1.3 beta
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function do_final_processing_steps( $post_id, $immobilie ) {
		$defaults = array(
			'_meta_bedroom' => 0,
			'_meta_bathroom' => 0,
			'_meta_price' => 0,
			'_meta_area' => 0
		);

		foreach ( $defaults as $meta_name => $meta_value ) {
			if ( ! get_post_meta( $post_id, $meta_name, true ) ) {
				add_post_meta( $post_id, $meta_name, $meta_value, true );
			}
		}
	} // do_final_processing_steps

	/**
	 * Add the main property image to the start page slider.
	 *
	 * @since 1.2.2
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function add_property_to_slider( $post_id, $immobilie ) {
		$thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );

		if ( $thumbnail_id ) {
			add_post_meta( $post_id, 'REAL_HOMES_add_in_slider', 'yes', true );
			add_post_meta( $post_id, 'REAL_HOMES_slider_image', $thumbnail_id, true );
		}
	} // add_property_to_slider

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 1.1.3
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
	 * @since 1.1.3
	 *
	 * @param array $fields Original fields array.
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
		) );

		return $fields;
	} // extend_fields

} // class Hometown
