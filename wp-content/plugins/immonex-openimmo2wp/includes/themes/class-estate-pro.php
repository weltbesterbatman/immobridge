<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * Estate-Pro-specific processing.
 */
class Estate_Pro extends Theme_Base {

	public
		$theme_class_slug = 'estate-pro';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 2.5
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->temp = array(
			'post_images' => array()
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_post_content' ), 10, 2 );

		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_attachment_data' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_defaults' ), 15, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 20, 2 );
	} // __construct

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 * Add excerpt if not set already.
	 *
	 * @since 2.5
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
	 * Determine/Save the property coordinates.
	 *
	 * @since 2.5
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie );
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

		if ( $lat && $lng ) {
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), "$lat, $lng" ), 'debug' );
			add_post_meta( $post_id, '_estate_property_lat', $lat, true );
			add_post_meta( $post_id, '_estate_property_long', $lng, true );
		}
	} // save_property_location

	/**
	 * Save default custom fields.
	 *
	 * @since 2.5
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_defaults( $post_id, $immobilie ) {
		$is_sale = 'true' == strtolower( (string) $immobilie->objektkategorie->vermarktungsart['KAUF'] ) ||	'1' == (string) $immobilie->objektkategorie->vermarktungsart['KAUF'];

		$default_values = array(
			'_estate_p_price' => __( 'Price on request', 'immonex-openimmo2wp' ),
			'_estate_p_colorpickers' => $is_sale ? '#1b9bff' : '#ff461b',
			'_estate_p_icon' => 'fa fa-file-text-o',
			'_estate_p_price' => '0',
			'_estate_p_area' => '0',
			'_estate_p_room_q' => '0',
			'_estate_p_bathroom_q' => '0'
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
	 * @since 2.5.1 beta
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

			if ( in_array( strtoupper( $fileinfo['extension'] ), $valid_image_formats ) ) {
				if ( ! isset( $this->temp['post_images'][$p->post_parent] ) ) {
					$this->temp['post_images'][$p->post_parent] = array();
				}
				$this->temp['post_images'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			}
		}
	} // add_attachment_data

	/**
	 * Save property gallery list (attachment IDs as serialized array).
	 *
	 * @since 2.5.1 beta
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_attachment_data( $post_id, $immobilie ) {
		if ( ! empty( $this->temp['post_images'][$post_id] ) ) {
			$this->temp['post_images'][$post_id] = $this->check_attachment_ids( $this->temp['post_images'][$post_id] );

			if ( count( $this->temp['post_images'][$post_id] ) > 0 ) {
				$gallery_images = array();

				foreach ( $this->temp['post_images'][$post_id] as $att_id ) {
					$gallery_images[] = array(
						'image_id' => $att_id,
						'image' => wp_get_attachment_url( $att_id )
					);
				}

				add_post_meta( $post_id, 'wiki_test_repeat_group', $gallery_images, true );
				unset( $this->temp['post_images'][$post_id] );
				$this->save_temp_theme_data();
			}
		}
	} // save_attachment_data

	/**
	 * Try to determine/save the property agent.
	 *
	 * @since 2.5
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

		if ( $user && $user->ID !== $author_id ) {
			// Set new author.
			$this->update_post_author( $post->ID, $user->ID );
		}

		$agent = $this->get_agent( $immobilie, 'agent', array( 'email' => '_estate_agents_yt' ), array(), true );
		if ( $agent ) {
			// Save agent ID.
			add_post_meta( $post_id, '_estate_select_agent', $agent->ID, true );
		}
	} // save_agent

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 2.5
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
	 * @since 2.5
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

} // class Estate_Pro
