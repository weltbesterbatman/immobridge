<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * IMPress Listings (formerly WP Listings) specific processing (plugin).
 */
class WP_Listings extends Theme_Base {

	public
		$theme_class_slug = 'wp-listings';

	protected
		$property_post_type = 'listing';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 1.7
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_description_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );
		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'set_agent_as_post_author' ), 10, 2 );

		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_gallery_code' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'do_final_processing_steps' ), 10, 2 );
	} // __construct

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 *
	 * @since 1.7
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
	 * Check attachment type and perform related processing steps.
	 *
	 * @since 1.7
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
			if ( 'http' !== substr( $url, 0, 4 ) ) {
				return $attachment;
			}

			$embed_code = '';
			$video = $this->plugin->string_utils->is_video_url( $url );

			if ( $video ) {
				// YouTube or Vimeo video.
				$embed_code = $this->theme_options[$video['type'] . '_embed_code'];
				// Replace video ID variable.
				$embed_code = str_replace( '{video_id}', $video['id'], $embed_code );
			} elseif ( 'FILMLINK' === (string) $attachment['gruppe'] ) {
				$embed_code = $this->theme_options['custom_video_embed_code'];
			}

			if ( $embed_code ) {
				// Replace URL variable and save video embed code as post meta.
				$embed_code = str_replace( '{video_url}', $url, $embed_code );
				add_post_meta( $post_id, '_listing_video', $embed_code, true );

				// No further processing of video URLs.
				return false;
			}
		}

		return $attachment;
	} // check_attachment

	/**
	 * Save the property address (post meta) if approved for publishing and the
	 * Google maps code.
	 *
	 * @since 1.7
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_property_location( $post_id, $immobilie ) {
		$geodata = $this->get_property_geodata( $immobilie );

		if ( $geodata['publishing_approved'] ) {
			$address = trim( $geodata['street'] );
			$this->plugin->log->add( wp_sprintf( __( 'Property address: %s', 'immonex-openimmo2wp' ), $address ), 'debug' );
		} else {
			$address = false;
			$this->plugin->log->add( wp_sprintf( __( 'Property address NOT approved for publishing, term used for geocoding: %s', 'immonex-openimmo2wp' ), $geodata['address_geocode'] ), 'debug' );
		}

		if ( $address ) {
			// Save display address.
			add_post_meta( $post_id, '_listing_address', $address, true );
		}

		if ( isset( $geodata['country_data']['Common Name'] ) ) {
			// Save country.
			$translate = array(
				'Germany' => 'Deutschland',
				'Austria' => 'Österreich',
				'Switzerland' => 'Schweiz'
			);

			$country = isset( $translate[$geodata['country_data']['Common Name']] ) ? $translate[$geodata['country_data']['Common Name']] : $geodata['country_data']['Common Name'];

			add_post_meta( $post_id, '_listing_country', $country, true );
		}

		if ( trim( $this->theme_options['map_embed_template'] ) && $geodata['address_geocode'] ) {
			// Save map code.
			if ( $geodata['publishing_approved'] && $geodata['lat'] && ! $geodata['lng'] ) {
				$lat = $geodata['lat'];
				$lng = $geodata['lng'];
			} elseif (
				$this->plugin->plugin_options['geo_always_use_coordinates'] &&
				$geodata['lat'] && $geodata['lng']
			) {
				$lat = $geodata['lat'];
				$lng = $geodata['lng'];
				$this->plugin->log->add( __( 'Property address NOT approved for publishing, but usable coordinates available and publishing permitted.', 'immonex-openimmo2wp' ), 'debug' );
			} else {
				$this->plugin->log->add( wp_sprintf(
					__( 'Property address (Geocoding): %s (ISO2: %s)', 'immonex-openimmo2wp' ),
					$geodata['address_geocode'],
					$geodata['country_code_iso2'] ? $geodata['country_code_iso2'] : __( 'none', 'immonex-openimmo2wp' )
				), 'debug' );
				$geo_coordinates = $this->geocode( $geodata['address_geocode'], $geodata['publishing_approved'] ? false : true, $geodata['country_code_iso2'], $post_id );
				if ( false !== $geo_coordinates ) {
					$lat = $geo_coordinates['lat'];
					$lng = $geo_coordinates['lng'];
					$this->plugin->log->add( wp_sprintf(
						__( 'Geocoding result%s: %s%s', 'immonex-openimmo2wp' ),
						! empty( $geo_coordinates['provider'] ) ? ' (' . $geo_coordinates['provider'] . ')' : '',
						$geo_coordinates['lat'] . ', ' . $geo_coordinates['lng'],
						$geo_coordinates['from_cache'] ? ' ' . __( '(cache)', 'immonex-openimmo2wp' ) : ''
					), 'debug' );
				} else {
					$geocoding_status = $this->get_geocoding_status( $geodata['address_geocode'], $geodata['country_code_iso2'] );
					$this->plugin->log->add( wp_sprintf( __( 'Geocoding failed (%s)', 'immonex-openimmo2wp' ), $geocoding_status ? $geocoding_status : __( 'unknown reason', 'immonex-openimmo2wp' ) ), 'debug' );
				}
			}

			$map_html_code = $this->theme_options['map_embed_template'];
			$map_html_code = str_replace( '{address}', $this->plugin->string_utils::urlencode_special( $geodata['address_geocode'] ), $map_html_code );
			$map_html_code = str_replace( '{lat}', $lat, $map_html_code );
			$map_html_code = str_replace( '{lng}', $lng, $map_html_code );
			$map_html_code = str_replace( array( "\r\n", "\r", "\n" ), '', $map_html_code );
			$map_html_code = str_replace( "\t", ' ', $map_html_code );
			$map_html_code = preg_replace( '/{\ }*/', ' ', $map_html_code );
			add_post_meta( $post_id, '_listing_map', $map_html_code, true );
		}
	} // save_property_location

	/**
	 * Save the property gallery code as custom field.
	 *
	 * @since 1.7
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function save_gallery_code( $post_id, $immobilie ) {
		$gallery_code = $this->theme_options['gallery_code'];

		if ( $gallery_code ) {
			add_post_meta( $post_id, '_listing_gallery', $gallery_code, true );
		}
	} // save_gallery_code

	/**
	 * Do the final processing steps for a property object.
	 *
	 * @since 2.3
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function do_final_processing_steps( $post_id, $immobilie ) {
		$property_types = wp_get_post_terms( $post_id, 'property-types', array( 'fields' => 'names' ) );
		if ( is_array( $property_types ) && count( $property_types ) > 0 ) {
			// Repeat property type (taxonomy term) as custom field.
			add_post_meta( $post_id, '_listing_proptype', $property_types[0], true );
		}

		// Price on request?
		$hide_price = get_post_meta( $post_id, '_listing_hide_price', true );
		if ( $hide_price ) add_post_meta( $post_id, '_listing_price_alt', __( 'Price on request', 'immonex-openimmo2wp' ), true );
	} // do_final_processing_steps

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 1.7
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
	 * @since 1.7
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
				'name' => $this->theme_class_slug . '_gallery_code',
				'type' => 'textarea',
				'label' => __( 'Gallery Code', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Code (e.g. shortcode) for the gallery section to display on the property detail page.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_map_embed_template',
				'type' => 'textarea',
				'label' => __( 'Map Embed Template', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Embed code for the map to display on the property detail page. Use the placeholders <strong>{address}</strong>, <strong>{lat}</strong> and <strong>{lng}</strong> here.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_youtube_embed_code',
				'type' => 'textarea',
				'label' => __( 'YouTube Embed Code', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Code for embedding YouTube videos, use {video_id} <strong>OR</strong> {video_url} as placeholders.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_vimeo_embed_code',
				'type' => 'textarea',
				'label' => __( 'Vimeo Embed Code', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Code for embedding Vimeo videos, use {video_id} <strong>OR</strong> {video_url} as placeholders.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_custom_video_embed_code',
				'type' => 'textarea',
				'label' => __( 'Custom Video Embed Code', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Code for embedding videos of alternative providers, use {video_id} <strong>OR</strong> {video_url} as placeholders.', 'immonex-openimmo2wp' )
				)
			)
		) );

		return $fields;
	} // extend_fields

} // class WP_Listings
