<?php
namespace immonex\OpenImmo2Wp\themes;

use immonex\OpenImmo2Wp\Property_Grouping;

/**
 * Real-Places-specific processing.
 */
class RealPlaces extends Theme_Base {

	public
		$theme_class_slug = 'realplaces';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 1.8
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->initial_widgets = array(
			'property-sidebar' => array(
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
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'maybe_update_sub_units' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 15, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'do_final_processing_steps' ), 20, 2 );

		if ( $this->theme_options['add_every_property_to_slider'] ) {
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'add_property_to_slider' ), 10, 2 );
		}

		if ( $this->theme_options['add_page_banner_image'] ) {
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'add_page_banner_image' ), 10, 2 );
		}
	} // __construct

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 * Add excerpt if not set already.
	 *
	 * @since 1.8
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

		return $post_data;
	} // add_post_content

	/**
	 * Convert and save theme custom data.
	 *
	 * @since 1.8
	 *
	 * @param array $grouped_meta_data Associative array of a meta data group.
	 * @param int $post_id ID of the related property post record.
	 * @param string $meta_key Meta key under which the serialized group array will be stored.
	 */
	public function add_custom_meta_details( $grouped_meta_data, $post_id, $meta_key ) {
		if ( 'REAL_HOMES_additional_details' !== $meta_key ) return $grouped_meta_data;

		$custom_meta = array();

		if ( count( $grouped_meta_data ) > 0 ) {
			foreach ( $grouped_meta_data as $key => $data ) {
				$custom_meta[$key] = str_replace( "\n", " |\n", $data['value'] );
			}

			add_post_meta( $post_id, $meta_key, $custom_meta, true );
		}

		// DON'T save the original data.
		return false;
	} // add_custom_meta_details

	/**
	 * Check if attachment is a video URL (YouTube/Vimeo), a virtual tour URL or a
	 * floor plan and perform the related processing steps.
	 *
	 * @since 3.9.8 beta
	 *
	 * @param SimpleXMLElement $attachment Attachment XML node.
	 * @param int $post_id ID of the related property post record.
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
				add_post_meta( $post_id, 'REAL_HOMES_tour_video_url', $url, true );

				// DON'T import this attachment.
				return false;
			} elseif ( $this->plugin->string_utils->is_virtual_tour_url( $url, apply_filters( $this->plugin->plugin_prefix . 'additional_virtual_tour_url_parts', array() ) ) ) {
				// Save virtual tour embed code.
				$embed_code = $this->get_virtual_tour_embed_code( apply_filters( $this->plugin->plugin_prefix . 'virtual_tour_embed_code_args', array( 'url' => $url ), 5 ) );
				add_post_meta( $post_id, 'REAL_HOMES_360_virtual_tour', $embed_code, true );

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
	 * @since 1.8
	 *
	 * @param string $post_id Property ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie );
		$geo_coordinates = false;

		if ( 1 === preg_match( '/[a-zA-Z]/', $geodata['address_geocode'] ) ) {
			// Save property geocode address if it does NOT contain coordinates.
			add_post_meta( $post_id, 'REAL_HOMES_property_address', $geodata['address_geocode'], true );
		}

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

		if ( $geo_coordinates && is_array( $geo_coordinates ) ) {
			add_post_meta( $post_id, 'REAL_HOMES_property_location', $geo_coordinates['lat'] . ',' . $geo_coordinates['lng'], true );
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), $geo_coordinates['lat'] . ', ' . $geo_coordinates['lng'] ), 'debug' );
		} elseif ( isset( $geocoding_failed ) && $geocoding_failed ) {
			$geocoding_status = $this->get_geocoding_status( $geodata['address_geocode'], $geodata['country_code_iso2'] );
			$this->plugin->log->add( wp_sprintf( __( 'Geocoding failed (%s)', 'immonex-openimmo2wp' ), $geocoding_status ? $geocoding_status : __( 'unknown reason', 'immonex-openimmo2wp' ) ), 'debug' );
		}
	} // save_property_location

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 1.8
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
	 * @since 1.8
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
				add_post_meta( $post_id, 'REAL_HOMES_property_images', $image_post_id, false );
			}
			unset( $this->temp['post_images'][$post_id] );
		}

		if ( ! empty( $this->temp['post_attachments'][$post_id] ) ) {
			$this->temp['post_attachments'][$post_id] = $this->check_attachment_ids( $this->temp['post_attachments'][$post_id] );

			// Save property file attachments.
			foreach ( $this->temp['post_attachments'][$post_id] as $attachment_post_id ) {
				// Save as NON-UNIQUE single records.
				add_post_meta( $post_id, 'REAL_HOMES_attachments', $attachment_post_id, false );
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
					'inspiry_floor_plan_name' => $attachment['title'] ? $attachment['title'] : $this->theme_options['default_title_nameless_floor_plans'],
					'inspiry_floor_plan_price' => '',
					'inspiry_floor_plan_price_postfix' => '',
					'inspiry_floor_plan_size' => '',
					'inspiry_floor_plan_size_postfix' => '',
					'inspiry_floor_plan_bedrooms' => '',
					'inspiry_floor_plan_bathrooms' => '',
					'inspiry_floor_plan_descr' => '',
					'inspiry_floor_plan_image' => $attachment_image[0]
				);
			}

			add_post_meta( $post_id, 'inspiry_floor_plans', $floor_plans, true );
			unset( $this->temp['property_floor_plans']['ids'][$post_id] );
			$this->save_temp_theme_data();
		}

		// External Video available? Set thumbnail as video image if so.
		$video_url = get_post_meta( $post_id, 'REAL_HOMES_tour_video_url', true );
		if ( $video_url ) {
			$thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );
			if ( $thumbnail_id ) add_post_meta( $post_id, 'REAL_HOMES_tour_video_image', $thumbnail_id, true );
		}
	} // save_attachment_data

	/**
	 * Update parent post IDs of children (sub units) if the property has
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
					// ...add its ID to child property posts...
					foreach ( $group_children_ids as $child_id ) {
						wp_update_post( array(
							'ID' => $child_id,
							'post_parent' => $post_id
						) );
					}
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
					// Property is a child object of an existing parent object:
					// add parent post ID.
					wp_update_post( array(
						'ID' => $post_id,
						'post_parent' => $group_parent_id
					) );
				}
			}
		}
	} // maybe_update_sub_units

	/**
	 * Try to determine/save the property agent.
	 *
	 * @since 1.8
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

		$agent = $this->get_agent( $immobilie, 'agent', array( 'email' => 'REAL_HOMES_agent_email' ), array(), true );
		if ( $agent ) {
			// Save agent ID and display option.
			add_post_meta( $post_id, 'REAL_HOMES_agents', $agent->ID, true );
			add_post_meta( $post_id, 'REAL_HOMES_agent_display_option', 'agent_info', true );
		} else {
			add_post_meta( $post_id, 'REAL_HOMES_agent_display_option', 'none', true );
		}
	} // save_agent

	/**
	 * Do the final processing steps for a property object (e.g. save
	 * additional and default property options).
	 *
	 * @since 2.0.5
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function do_final_processing_steps( $post_id, $immobilie ) {
		$defaults = array(
			'REAL_HOMES_property_size_postfix' => \immonex\OpenImmo2Wp\Import_Content_Filters::SQM_TERM,
			'REAL_HOMES_featured' => 0
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
	 * @since 1.8
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
	 * Set the main property image as page banner image.
	 *
	 * @since 1.8
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function add_page_banner_image( $post_id, $immobilie ) {
		$thumbnail_id = get_post_meta( $post_id, '_thumbnail_id', true );

		if ( $thumbnail_id ) {
			add_post_meta( $post_id, 'REAL_HOMES_page_banner_image', $thumbnail_id, true );
		}
	} // add_page_banner_image

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 1.8
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
	 * @since 1.8
	 *
	 * @param array $fields Original fields array.
	 *
	 * @return array Extended fields array.
	 */
	public function extend_fields( $fields ) {
		$fields = array_merge( $fields, array(
			array(
				'name' => $this->theme_class_slug . '_add_every_property_to_slider',
				'type' => 'checkbox',
				'label' => __( 'Add every imported property to the start page slider', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'For each property, the <strong>first</strong> image attachment will be used as slider image. These should be high resolution images with the <strong>same aspect ratio</strong>.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_add_page_banner_image',
				'type' => 'checkbox',
				'label' => __( 'Show first property image in page banner', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'For each property, the <strong>first</strong> image attachment will be used as page banner image. These should be high resolution images with the <strong>same aspect ratio</strong>.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_default_title_nameless_floor_plans',
				'type' => 'text',
				'label' => __( 'Default Floor Plan Title', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Floor plan title to use if none is given.', 'immonex-openimmo2wp' )
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

} // class RealPlaces
