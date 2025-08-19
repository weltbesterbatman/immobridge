<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * WPCasa-specific processing (plugin version).
 */
class WPCasa_Plugin extends Theme_Base {

	public
		$theme_class_slug = 'wpcasa_plugin';

	protected
		$property_post_type = 'listing';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 1.8
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		$this->temp = array(
			'post_images' => array(),
			'company' => ''
		);

		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_xml_data_before_import', array( $this, 'temp_save_company_data' ) );
		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_post_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'set_agent_as_post_author' ), 10, 2 );

		add_action( 'immonex_oi2wp_start_import_process', array( $this, 'add_import_filters' ) );
		add_action( 'immonex_oi2wp_import_process_finished', array( $this, 'remove_import_filters' ) );
		add_action( 'immonex_oi2wp_attachment_added', array( $this, 'add_attachment_data' ), 10, 3 );
		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_meta_data' ), 10, 2 );
	} // __construct

	/**
	 * Temporary save the main company name mentioned in the XML import file.
	 *
	 * @since 1.8
	 *
	 * @param SimpleXMLElement $openimmo_xml XML element of complete import data.
	 *
	 * @return SimpleXMLElement Unchanged XML data.
	 */
	public function temp_save_company_data( $openimmo_xml ) {
		$this->temp['company'] = (string) $openimmo_xml->anbieter->firma;
		$this->save_temp_theme_data();

		return $openimmo_xml;
	} // temp_save_company_data

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
			$post_data['post_content'] .= "\n" . $this->plugin->multilang_get_string_translation( $this->theme_options['add_description_content'] );
		}

		if ( ! isset( $post_data['post_excerpt'] ) || ! $post_data['post_excerpt'] ) {
			$post_data['post_excerpt'] = $this->plugin->string_utils->get_excerpt( $post_data['post_content'], 120, '...' );
		}

		return $post_data;
	} // add_post_content

	/**
	 * Add import specific filters.
	 *
	 * @since 2.5.8 beta
	 *
	 * @param string $dir Current import unzip dir.
	 */
	public function add_import_filters( $dir ) {
		add_filter( 'wpsight_geolocation_enabled', array( $this, 'disable_wpcasa_geolocation' ), 10, 2 );
	} // add_import_filters

	/**
	 * Remove import specific filters.
	 *
	 * @since 2.5.8 beta
	 *
	 * @param string $dir Current import unzip dir.
	 */
	public function remove_import_filters( $dir ) {
		remove_filter( 'wpsight_geolocation_enabled', array( $this, 'disable_wpcasa_geolocation' ), 10, 2 );
	} // remove_import_filters

	/**
	 * Disable WPCasa gelocation methods.
	 *
	 * @since 2.5.8 beta
	 *
	 * @param bool $enable_geolocation Geolocation enabled?
	 *
	 * @return bool Always false.
	 */
	function disable_wpcasa_geolocation( $enable_geolocation ) {
		return false;
	}

	/**
	 * Collect property attachment IDs for later processing.
	 *
	 * @since 1.8
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
				// Add image ID.
				if ( ! isset( $this->temp['post_images'][$p->post_parent] ) ) {
					$this->temp['post_images'][$p->post_parent] = array();
				}
				$this->temp['post_images'][$p->post_parent][] = $att_id;
				$this->save_temp_theme_data();
			}
		}
	} // add_attachment_data

	/**
	 * Save the property address or coordinates (post meta) for geocoding.
	 *
	 * @since 1.8
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

		// Delete + add instead of update due to an occasional strange behaviour
		// of WPCasa on saving the map address.
		//delete_post_meta( $post_id, '_map_address' );
		update_post_meta( $post_id, '_map_address', $address );

		if ( $lat && $lng ) {
			$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), "$lat, $lng" ), 'debug' );
			update_post_meta( $post_id, '_map_location', $lat . ',' . $lng );
			update_post_meta( $post_id, '_map_geo', $lat . ',' . $lng );
			update_post_meta( $post_id, '_geolocation_lat', $lat );
			update_post_meta( $post_id, '_geolocation_long', $lng );
			update_post_meta( $post_id, '_geolocated', 1 );
		}
	} // save_property_location

	/**
	 * Save additional property data (contact, gallery etc.).
	 *
	 * @since 1.8
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_meta_data( $post_id, $immobilie ) {
		if ( $user_info = $this->get_agent_user( $immobilie ) ) {
			// Author/Agent user assigned: add user meta data to property.
			$author_data = array(
				'email' => $user_info->user_email,
				'url' => $user_info->user_url
			);

			$author_meta_fields = array( 'first_name', 'last_name', 'description', 'agent_logo_id', 'agent_logo', 'company', 'phone', 'twitter', 'facebook' );

			foreach ( $author_meta_fields as $field_name ) {
				$field_value = get_the_author_meta( $field_name, $user_info->ID );
				if ( $field_value ) $author_data[$field_name] = $field_value;
			}

			if ( isset( $author_data['first_name'] ) && isset( $author_data['first_name'] ) )
				$author_data['name'] = trim( $author_data['first_name'] . ' ' . $author_data['last_name'] );
			else
				$author_data['name'] = '';

			if ( $author_data['name'] ) add_post_meta( $post_id, '_agent_name', $author_data['name'], true );
			if ( isset( $author_data['company'] ) && $author_data['company'] ) add_post_meta( $post_id, '_agent_company', $author_data['company'], true );
			if ( isset( $author_data['description'] ) && $author_data['description'] ) add_post_meta( $post_id, '_agent_description', $author_data['description'], true );
			if ( isset( $author_data['phone'] ) && $author_data['phone'] ) add_post_meta( $post_id, '_agent_phone', $author_data['phone'], true );
			if ( isset( $author_data['url'] ) && $author_data['url'] ) add_post_meta( $post_id, '_agent_website', $author_data['url'], true );
			if ( isset( $author_data['twitter'] ) && $author_data['twitter'] ) add_post_meta( $post_id, '_agent_twitter', $author_data['twitter'], true );
			if ( isset( $author_data['facebook'] ) && $author_data['facebook'] ) add_post_meta( $post_id, '_agent_facebook', $author_data['facebook'], true );
			if ( isset( $author_data['agent_logo'] ) && $author_data['agent_logo'] ) add_post_meta( $post_id, '_agent_logo', $author_data['agent_logo'], true );
		} else {
			$agent_data = $this->get_agent_data( $immobilie );

			// No specific agent user assigned: add XML based contact data to property.
			$company = isset( $immobilie->kontaktperson->firma ) ? (string) $immobilie->kontaktperson->firma : $this->temp['company'];
			$url = isset( $immobilie->kontaktperson->url ) ? (string) $immobilie->kontaktperson->url : '';

			if ( $agent_data['name'] ) add_post_meta( $post_id, '_agent_name', $agent_data['name'], true );
			if ( $agent_data['phone'] ) add_post_meta( $post_id, '_agent_phone', $agent_data['phone'], true );
			if ( $company ) add_post_meta( $post_id, '_agent_company', $company, true );
			if ( $url ) add_post_meta( $post_id, '_agent_website', $url, true );
		}

		if ( trim( $this->theme_options['remark_not_exact_location'] ) ) {
			$geodata = $this->get_property_geodata( $immobilie );
			if ( ! $geodata['publishing_approved'] ) add_post_meta( $post_id, '_map_note', $this->plugin->multilang_get_string_translation( $this->theme_options['remark_not_exact_location'] ), true );
		}

		if ( ! empty( $this->temp['post_images'][$post_id] ) ) {
			$this->temp['post_images'][$post_id] = $this->check_attachment_ids( $this->temp['post_images'][$post_id] );

			if ( count( $this->temp['post_images'][$post_id] ) > 0 ) {
				$gallery_images = array();
				foreach ( $this->temp['post_images'][$post_id] as $att_id ) {
					$gallery_images[$att_id] = wp_get_attachment_url( $att_id );
				}

				// Save property gallery data.
				add_post_meta( $post_id, '_gallery', $gallery_images, true );
			}

			unset( $this->temp['post_images'][$post_id] );
		}

		// Header display/type.
		add_post_meta( $post_id, '_header_display', $this->theme_options['header_display'], true );

		if ( isset( $this->theme_options['header_filter'] ) && $this->theme_options['header_filter'] ) {
			add_post_meta( $post_id, '_header_filter', 'on', true );
		}
	} // save_meta_data

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
				'name' => $this->theme_class_slug . '_remark_not_exact_location',
				'type' => 'textarea',
				'label' => __( 'Note regarding property location', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'This text will be displayed if the publishing of the complete property address has not been approved.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_header_display',
				'type' => 'select',
				'label' => 'Header Display',
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Select the header type to display <strong>(supported themes only, e.g. WPCasa London)</strong>.', 'immonex-openimmo2wp' ),
					'options' => array(
						'' => __( 'Do not display', 'immonex-openimmo2wp' ),
						'featured_image' => __( 'Featured image &amp; title', 'immonex-openimmo2wp' ),
						'image_slider' => __( 'Image slider', 'immonex-openimmo2wp' ),
						'tagline' => __( 'Tagline &amp; background (to define by additional filter function)', 'immonex-openimmo2wp' )
					)
				)
			),
			array(
				'name' => $this->theme_class_slug . '_header_filter',
				'type' => 'checkbox',
				'label' => __( 'Header Filter', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Remove filter effect from header images.', 'immonex-openimmo2wp' )
				)
			),
		) );

		return $fields;
	} // extend_fields

} // class WPCasa_Plugin
