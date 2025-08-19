<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * Freehold-Progression-specific processing.
 */
class Freehold_Progression extends Theme_Base {

	public
		$theme_class_slug = 'freehold-progression';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 5.0.0
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->initial_widgets = array(
			'progression-studios-sidebar-property-index' => array(
				'immonex_user_defined_properties_widget' => array(
					array(
						'title' => __( 'Energy Pass', 'immonex-openimmo2wp' ),
						'display_mode' => 'include',
						'display_groups' => 'epass',
						'type' => 'name_value',
						'item_div_classes' => ''
					)
				),
				'immonex_property_attachments_widget' => array(
					array(
						'title' => __( 'Downloads & Links', 'immonex-openimmo2wp' ),
						'file_types' => '',
						'icon_size' => 32,
						'item_div_classes' => ''
					)
				)
			)
		);

		$this->temp = array(
			'post_images' => array()
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
	} // __construct

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 * Add excerpt if not set already.
	 *
	 * @since 5.0.0
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
	 * @since 5.0.0
	 *
	 * @param mixed $grouped_meta_data Associative array of a meta data group.
	 * @param int $post_id ID of the related property post record.
	 * @param string $meta_key Meta key under which the serialized group array will be stored.
	 *
	 * @return mixed|bool Unchanged grouped meta data or false for amenities group.
	 */
	public function add_custom_meta_details( $grouped_meta_data, $post_id, $meta_key ) {
		if ( 'progression_custom_additional' !== $meta_key ) {
			return $grouped_meta_data;
		}

		$custom_meta = array();

		if ( ! empty( $grouped_meta_data ) ) {
			foreach ( $grouped_meta_data as $key => $data ) {
				$custom_meta[] = array(
					'title'       => $key,
					'description' => str_replace( PHP_EOL, ' | ', $data['value'] )
				);
			}

			add_post_meta( $post_id, $meta_key, $custom_meta, true );
		}

		// DON'T save the original data.
		return false;
	} // add_custom_meta_details

	/**
	 * Check attachment type and perform related processing steps.
	 *
	 * @since 5.0.0
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
			if ( 'http' !== substr( $url, 0, 4 ) ) {
				return $attachment;
			}

			$video = $this->plugin->string_utils->is_video_url( $url );

			if ( $video ) {
				// Attachment is an URL of a YouTube or Vimeo video.
				add_post_meta( $post_id, 'pyre_video', $url, true );

				// No further processing of video URLs.
				return false;
			}
		}

		return $attachment;
	} // check_attachment

	/**
	 * Save location related property metadata.
	 *
	 * @since 5.0.0
	 *
	 * @param string $post_id Property ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie );

		if ( $geodata['address_geocode'] && ! $geodata['address_geocode_is_coordinates'] ) {
			$this->plugin->log->add( wp_sprintf( __( 'Property address (Geocoding): %s', 'immonex-openimmo2wp' ), $geodata['address_geocode'] ), 'debug' );
			add_post_meta( $post_id, 'pyre_full_address', $geodata['address_geocode'], true );
		}

		if ( $geodata['publishing_approved'] ) {
			if ( $geodata['street'] ) add_post_meta( $post_id, 'pyre_address', $geodata['street'], true );
		} else {
			$this->plugin->log->add( __( 'Property address NOT approved for publishing', 'immonex-openimmo2wp' ), 'debug' );
		}

		if ( $geodata['postcode'] ) add_post_meta( $post_id, 'pyre_zip', $geodata['postcode'], true );
		if ( $geodata['city_raw'] ) add_post_meta( $post_id, 'pyre_city', $geodata['city_raw'], true );
		if ( $geodata['state'] ) add_post_meta( $post_id, 'pyre_state', $geodata['state'], true );
	} // save_property_location

	/**
	 * Collect extra data for property attachments.
	 *
	 * @since 5.0.0
	 *
	 * @param string $att_id Attachment ID.
	 * @param array $valid_image_formats Array of valid image file format suffixes.
	 * @param array $valid_misc_formats Array of valid non-image file format suffixes.
	 */
	public function add_attachment_data( $att_id, $valid_image_formats, $valid_misc_formats ) {
		$p = get_post( $att_id );

		if ( $p ) {
			$fileinfo = pathinfo( get_attached_file( $att_id ) );
			if ( ! isset( $fileinfo['extension'] ) ) {
				return;
			}

			// Remove counter from filename for comparison (floor plans etc).
			$filename = $this->get_plain_basename( $fileinfo['filename'] );

			if ( in_array( strtoupper( $fileinfo['extension'] ), $valid_image_formats ) ) {
				// Remember property image ID for later processing.
				if ( ! isset( $this->temp['post_images'][ $p->post_parent ] ) ) {
					$this->temp['post_images'][ $p->post_parent ] = array();
				}
				$this->temp['post_images'][ $p->post_parent ][] = $att_id;
				$this->save_temp_theme_data();
			}
		}
	} // add_attachment_data

	/**
	 * Save extra data of property attachments as serialized array in
	 * theme-specific format.
	 *
	 * @since 5.0.0
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['post_images'][$post_id] ) ) {
			// Save images for gallery.
			$this->temp['post_images'][ $post_id ] = $this->check_attachment_ids( $this->temp['post_images'][ $post_id ] );

			$post_att_data = array();

			foreach ( $this->temp['post_images'][ $post_id ] as $att_id ) {
				$p = get_post( $att_id );
				$img_src = wp_get_attachment_image_src( $att_id, 'full' ) ;
				$post_att_data[ $att_id ] = $img_src[0];
			}

			if ( ! empty( $post_att_data ) ) {
				add_post_meta( $post_id, 'pyre_gallery', $post_att_data, true );
			}

			unset( $this->temp['post_images'][$post_id] );
			$this->save_temp_theme_data();
		}
	} // save_attachment_data

	/**
	 * Try to determine/save the property agent.
	 *
	 * @since 5.0.0
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

		$args = array(
			'meta_query' => array(
				'key'     => 'progression_disable_agent_avlar',
				'compare' => 'NOT EXISTS'
			)
		);
		$user = $this->get_agent_user( $immobilie, $args, true, $author_id );

		if ( $user ) {
			if ( $user->ID !== $author_id ) {
				// Save new author.
				$this->update_post_author( $post->ID, $user->ID );
			}

			// Save property agent ID as custom field.
			add_post_meta( $post_id, 'pyre_agent', $user->ID, true );
		}

		if ( ! $user || $this->theme_options['disable_agent_box'] ) {
			add_post_meta( $post_id, 'pyre_no_agent', 'on', true );
		}
	} // save_agent

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 5.0.0
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
	 * @since 5.0.0
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
				'label' => __( 'Additional Content for Property Descriptions', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => wp_sprintf(
						__( 'This content will be appended to the <strong>main description text</strong> of every imported property. This is especially useful for adding <a href="%s" class="immonex-doc-link" target="_blank">widgets by shortcode</a>.', 'immonex-openimmo2wp' ),
						'https://docs.immonex.de/openimmo2wp/#/widgets/per-shortcode'
					)
				)
			),
			array(
				'name' => $this->theme_class_slug . '_disable_agent_box',
				'type' => 'checkbox',
				'label' => __( 'Disable Agent Box', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Generally deactivate the display of the agent info/contact boxes on property detail pages.', 'immonex-openimmo2wp' )
				)
			),
		) );

		return $fields;
	} // extend_fields

} // class Freehold_Progression
