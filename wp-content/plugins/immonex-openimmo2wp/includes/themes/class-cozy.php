<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * Cozy-specific processing.
 */
class Cozy extends Theme_Base {

	public
		$theme_class_slug = 'cozy';

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
			'property_floor_plans' => array(
				'filenames' => array(),
				'ids' => array()
			),
			'property_description_elements' => array(),
			'gallery_images' => array()
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_grouped_post_meta', array( $this, 'add_custom_meta_details' ), 10, 3 );

		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_defaults' ), 15, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 20, 2 );
		add_filter( 'immonex_oi2wp_property_imported', array( $this, 'save_post_content' ), 30, 2 );

		if ( ! is_admin() ) add_filter( 'get_post_metadata', array( $this, 'filter_property_description' ), 100, 4 );

		add_filter( 'wt_cozy_amenities', array( $this, 'extend_cozy_amenities' ) );
	} // __construct

	/**
	 * Check attachment type and perform related processing steps.
	 *
	 * @since 1.4
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
				// Attachment is an URL of an external video: save as custom field.
				add_post_meta( $post_id, '_wt_property_video_url', $url, true );

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
	 * Convert and save theme custom data for property description.
	 *
	 * @since 2.7
	 *
	 * @param mixed $grouped_meta_data Associative array of a meta data group.
	 * @param int $post_id ID of the related property post record.
	 * @param string $meta_key Meta key under which the serialized group array will be stored.
	 *
	 * @return mixed|bool Unchanged grouped meta data or false for amenities group.
	 */
	public function add_custom_meta_details( $grouped_meta_data, $post_id, $meta_key ) {
		if ( 'cozy_property_description' !== $meta_key ) return $grouped_meta_data;

		$custom_meta = array();

		if ( count( $grouped_meta_data ) > 0 ) {
			// Temporary save property description elements (will be compiled into final
			// description before finishing the property import).
			foreach ( $grouped_meta_data as $key => $data ) {
				if ( ! isset( $this->temp['property_description_elements'][$post_id] ) ) {
					$this->temp['property_description_elements'][$post_id] = array();
				}
				$this->temp['property_description_elements'][$post_id][$key] = $data['value'];
			}

			$this->save_temp_theme_data();
		}

		// DON'T save the original data.
		return false;
	} // add_custom_meta_details

	/**
	 * Determine/Save the property coordinates.
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

		if ( ! $geodata['publishing_approved'] && ! $address_publishing_status_logged ) {
			$this->plugin->log->add( __( 'Property address NOT approved for publishing', 'immonex-openimmo2wp' ), 'debug' );
		}

		if ( isset( $geodata['country_data']['Common Name'] ) ) add_post_meta( $post_id, '_wt_property_country', $geodata['country_data']['Common Name'], true );
		if ( $address ) add_post_meta( $post_id, '_wt_property_address', $address, true );

		if ( $lat && $lng ) {
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), "$lat, $lng" ), 'debug' );
			add_post_meta( $post_id, '_wt_property_map_latitude', $lat, true );
			add_post_meta( $post_id, '_wt_property_map_longitude', $lng, true );
			add_post_meta( $post_id, '_wt_property_map', array( 'latitude' => $lat, 'longitude' => $lng ), true );
		}
	} // save_property_location

	/**
	 * Save default custom fields.
	 *
	 * @since 2.7
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_defaults( $post_id, $immobilie ) {
		$default_values = array(
			'_wt_property_offmarket' => 'no'
		);

		foreach ( $default_values as $meta_key => $meta_value ) {
			if ( ! get_post_meta( $post_id, $meta_key, true ) ) {
				add_post_meta( $post_id, $meta_key, $meta_value, true );
			}
		}
	} // save_defaults

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 2.7
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

			// Remove counter etc. from filename for comparison.
			$filename = $this->get_plain_basename( $fileinfo['filename'] );

			$filenames = ! empty( $this->temp['property_floor_plans']['filenames'][$p->post_parent] ) ?
				$this->get_extended_filenames( $this->temp['property_floor_plans']['filenames'][$p->post_parent] ) :
				array();

			if ( ! empty( $filenames ) && in_array( $filename, $filenames, true )	) {
				// Remember floor plan attachment ID, exclude from gallery.
				if ( ! isset( $this->temp['property_floor_plans']['ids'][$p->post_parent] ) ) {
					$this->temp['property_floor_plans']['ids'][$p->post_parent] = array();
				}
				$this->temp['property_floor_plans']['ids'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			} elseif ( in_array( strtoupper( $fileinfo['extension'] ), $valid_image_formats ) ) {
				if ( ! isset( $this->temp['gallery_images'][$p->post_parent] ) ) {
					$this->temp['gallery_images'][$p->post_parent] = array();
				}
				$this->temp['gallery_images'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			}
		}
	} // add_attachment_data

	/**
	 * Save property gallery list (attachment IDs/URLs as array).
	 *
	 * @since 2.7
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['gallery_images'][$post_id] )	) {
			$this->temp['gallery_images'][$post_id] = $this->check_attachment_ids( $this->temp['gallery_images'][$post_id] );

			if ( count( $this->temp['gallery_images'][$post_id] ) > 0 ) {
				$gallery_images = array();

				foreach ( $this->temp['gallery_images'][$post_id] as $att_id ) {
					$gallery_images[$att_id] = wp_get_attachment_url( $att_id );
				}

				add_post_meta( $post_id, '_wt_property_slider', $gallery_images, true );
				unset( $this->temp['gallery_images'][$post_id] );
				$this->save_temp_theme_data();
			}
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
		$agent = $this->get_agent( $immobilie, 'agent', array( 'email' => '_wt_agent_email' ), array(), true );
		if ( $agent ) {
			add_post_meta( $post_id, '_wt_property_author', $agent->ID, true );
			if ( $agency_id = get_post_meta( $agent->ID, '_wt_agent_agency', true ) ) add_post_meta( $post_id, '_wt_property_agency', $agency_id, true );
		}
	} // save_agent

	/**
	 * Add extra content to property main descriptions (custom field) during import.
	 * Add excerpt if not set already.
	 *
	 * @since 2.7
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_post_content( $post_id, $immobilie ) {
		$section_lines = explode( "\n", $this->plugin->multilang_get_string_translation( $this->theme_options['description_sections'] ) );
		$sections = array();

		if ( count( $section_lines ) ) {
			// Convert section text to array (section_name => Section Title each).
			foreach ( $section_lines as $line ) {
				if ( false !== strpos( $line, ':' ) ) {
					$section_raw = explode( ':', $line );
					if ( in_array( count( $section_raw ), array( 2, 3 ) ) ) {
						if ( 'group' === strtolower( $section_raw[0] ) ) {
							// Section will be replaced by a shortcode (immonex user-defined properties widget) later.
							$sections[] = array(
								'type' => strtolower( $section_raw[0] ),
								'name' => trim( $section_raw[1] ),
								'title' => isset( $section_raw[2] ) && $section_raw[2] ? trim( $section_raw[2] ) : ''
							);
						} elseif ( 'downloads_links' === strtolower( $section_raw[0] ) ) {
							// Section will be replaced by a shortcode (immonex property attachments widget) later.
							$sections[] = array(
								'type' => strtolower( $section_raw[0] ),
								'name' => '',
								'title' => isset( $section_raw[1] ) && $section_raw[1] ? trim( $section_raw[1] ) : ''
							);
						} else {
							// Section will be replaced by the value of an imported XML element later.
							$sections[] = array(
								'type' => 'string',
								'name' => trim( $section_raw[0] ),
								'title' => isset( $section_raw[1] ) && $section_raw[1] ? trim( $section_raw[1] ) : ''
							);
						}
					}
				} else {
					// Section will be replaced by the value of an imported XML element later.
					$sections[] = array(
						'type' => 'string',
						'name' => trim( $line ),
						'title' => ''
					);
				}
			}
		}

		$section_template = array(
			'wrap' => '<div class="imnx-property-content-section {section_name}">{section}</div>',
			'title' => "\n" . '<h3 class="section-title">{title}</h3>',
			'content' => "\n{content}"
		);
		$plugin = $this->plugin;
		$section_template = apply_filters( $this->plugin->plugin_prefix . 'cozy_property_description_section_template', $section_template );

		$floor_plan_template = array(
			'wrap' => '<div class="imnx-cozy-floor-plans">{plans}</div>'. "\n",
			'plan' => '<div class="imnx-cozy-floor-plan">{img}</div>' . "\n"
		);
		$floor_plan_template = apply_filters( $this->plugin->plugin_prefix . 'cozy_property_description_floor_plan_template', $floor_plan_template );

		// Make property description elements extendable/modifiable by filter functions.
		$property_description_elements = apply_filters(
			$this->plugin->plugin_prefix . 'cozy_property_description_elements',
			! empty( $this->temp['property_description_elements'][$post_id] ) ? $this->temp['property_description_elements'][$post_id] : array(),
			$post_id
		);

		if ( ! empty( $this->temp['property_floor_plans']['ids'][$post_id] ) ) {
			$floor_plans_html = '';

			foreach ( $this->temp['property_floor_plans']['ids'][$post_id] as $att_id ) {
				$attachment = get_post( $att_id );
				$image_attrs = wp_get_attachment_image_src( $att_id, 'large' );
				if ( $image_attrs ) {
					$img_tag = wp_sprintf ( '<img src="%s" width="%s" height="%s" alt="%s">' . "\n", $image_attrs[0], $image_attrs[1], $image_attrs[2], $attachment->post_title );
					$floor_plans_html .= str_replace( array( '{img}', '{title}' ), array( $img_tag, $attachment->post_title ), $floor_plan_template['plan'] );
				}
			}

			$property_description_elements['floor_plans'] = str_replace( '{plans}', $floor_plans_html, $floor_plan_template['wrap'] );
		}

		$description = '';

		if ( count( $sections ) > 0 ) {
			foreach ( $sections as $section ) {
				$temp_title_template = str_replace( '{section_name}', $section['name'], $section_template['title'] );
				$temp_content_template = str_replace( '{section_name}', $section['name'], $section_template['content'] );

				if ( 'group' === $section['type'] ) {
					// Section type "group": Add an immonex User-defined Properties widget.
					$description .= wp_sprintf( '[immonex_widget name="immonex_User_Defined_Properties_Widget" title="%1$s" display_mode="include" display_groups="%2$s" class="%2$s"]', $section['title'], $section['name'] );
				} elseif ( 'downloads_links' === $section['type'] ) {
					// Section type "downloads_links": Add an immonex Property Attachments widget.
					$description .= wp_sprintf( '[immonex_widget name="immonex_Property_Attachments_Widget" title="%s"]', $section['title'] );
				} elseif (
					isset( $property_description_elements[$section['name']] ) &&
					$section_content = trim( $property_description_elements[$section['name']] )
				) {
					// Section type "string": Add a string based on an template filled with imported custom field data.
					$is_wrapped = '<' === $section_content[0];
					// Add <p> tag if section content is not alreay wrapped by another container tag.
					if ( ! $is_wrapped ) $section_content = '<p>' . $section_content . "</p>";

					// Create section HTML based on a template.
					$temp_content = '';
					if ( $section['title'] ) $temp_content .= str_replace( '{title}', $section['title'], $temp_title_template );
					$temp_content .= str_replace( '{content}', $section_content, $temp_content_template );

					$description .= str_replace( array( '{section_name}', '{section}' ), array( $section['name'], $temp_content ), $section_template['wrap'] );
				}
			}
		}

		if ( trim( $this->theme_options['add_description_content'] ) ) {
			$description .= "\n" . $this->plugin->multilang_get_string_translation( $this->theme_options['add_description_content'] );
		}

		// Save property description as custom field.
		add_post_meta( $post_id, '_wt_property_desc', trim( $description ), true );

		// Generate and save the excerpt.
		$post = get_post( $post_id );
		$post->post_excerpt = $this->plugin->string_utils->get_excerpt( $description, 120, '...' );
	} // save_post_content

	/**
	 * Apply the_content filter to Cozy property description custom field.
	 *
	 * @since 2.7
	 *
	 * @param null|array|string $value The value get_metadata() should return a single metadata value,
	 *			or an array of values.
	 * @param int $post_id Post ID.
	 * @param string $meta_key Meta key.
	 * @param string|array $single Meta value or an array of values.
	 *
	 * @return array Amenities array extended by respective mapped OpenImmo elements.
	 */
	public function filter_property_description( $value, $post_id, $meta_key, $single ) {
		$desc_meta_key = '_wt_property_desc';

		if ( isset( $meta_key ) && $meta_key === $desc_meta_key ) {
			remove_filter( 'get_post_metadata', array( $this, 'filter_property_description' ), 100 );
			$current_meta = get_post_meta( $post_id, $desc_meta_key, true );
			add_filter('get_post_metadata', array( $this, 'filter_property_description' ), 100, 4 );

			return apply_filters( 'the_content', $current_meta );
		}

		return $value;
	} // filter_property_description

	/**
	 * Extend the list of the Cozy "amenities" usually displayed below the property image gallery.
	 *
	 * @since 2.7
	 *
	 * @param array $cozy_amenities Current list of amenities as sub arrays (title, value, unit).
	 *
	 * @return array Amenities array extended by respective mapped OpenImmo elements.
	 */
	public function extend_cozy_amenities( $cozy_amenities ) {
		$imported_amenities = get_post_meta( get_the_ID(), '_cozy_amenities', true );

		if ( $imported_amenities && count( $imported_amenities ) > 0 ) {
			foreach ( $imported_amenities as $title => $element ) {
				$cozy_amenities[] = array( $title, $element['value'], '' );
			}
		}

		return $cozy_amenities;
	} // extend_cozy_amenities

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 2.7
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
	 * @since 2.7
	 *
	 * @param mixed $fields Original fields array.
	 *
	 * @return array Extended fields array.
	 */
	public function extend_fields( $fields ) {
		$fields = array_merge( $fields, array(
			array(
				'name' => $this->theme_class_slug . '_description_sections',
				'type' => 'textarea',
				'label' => __( 'Property description sections', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'List of sections (property description texts or mapping groups) to be concatenated into the main property description field, one per line in format  "description_name[:Title (optional)]" or "group:group_name[:Title (optional)]" (sections available by default: property_description, amenity_description, location_description, misc_description, floor_plans, downloads_links; group names can be found in the mapping table).', 'immonex-openimmo2wp' ),
					'class' => 'large-text'
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

} // class Cozy
