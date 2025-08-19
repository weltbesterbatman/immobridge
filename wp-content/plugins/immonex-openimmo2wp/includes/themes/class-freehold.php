<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * Freehold-specific processing.
 */
class Freehold extends Theme_Base {

	public
		$theme_class_slug = 'freehold';

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 1.5
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
	 */
	public function __construct( $plugin, $supported_theme_properties ) {
		parent::__construct( $plugin, $supported_theme_properties );

		$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

		add_filter( 'immonex_oi2wp_add_property_post_data', array( $this, 'add_post_content' ), 10, 2 );
		add_filter( 'immonex_oi2wp_attachment_before_import', array( $this, 'check_attachment' ), 10, 2 );

		add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'add_additional_details' ), 15, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 15, 2 );
		add_action( 'immonex_oi2wp_property_imported', array( $this, 'add_special_terms' ), 15, 2 );
	} // __construct

	/**
	 * Add extra content to property main descriptions (post data) during import.
	 * Shorten excerpt if needed.
	 *
	 * @since 1.5
	 *
	 * @param array $post_data Current post data.
	 * @param SimpleXMLElement $immobilie XML node of the related property object.
	 *
	 * @return array (Possibly) modified property data to store as post record.
	 */
	public function add_post_content( $post_data, $immobilie ) {
		if ( trim( $this->theme_options['add_description_content'] ) ) {
			$post_data['post_content'] .= "\n" . $this->plugin->multilang_get_string_translation( $this->theme_options['add_description_content'] );
		}

		if ( isset( $post_data['post_excerpt'] ) && $post_data['post_excerpt'] )
			$excerpt = $post_data['post_excerpt'];
		else
			$excerpt = $post_data['post_content'];

		$post_data['post_excerpt'] = $this->plugin->string_utils->get_excerpt( $excerpt, 120, '...' );

		return $post_data;
	} // add_post_content

	/**
	 * Check attachment type and perform related processing steps.
	 *
	 * @since 1.5
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

			$embed_code = '';
			$video = $this->plugin->string_utils->is_video_url( $url );

			if ( $video ) {
				// Attachment is an URL of a YouTube or Vimeo video.
				$embed_code = $this->theme_options[$video['type'] . '_embed_code'];
				// Replace video ID variable.
				$embed_code = str_replace( '{video_id}', $video['id'], $embed_code );
			} elseif ( 'FILMLINK' === (string) $attachment['gruppe'] ) {
				$embed_code = $this->theme_options['custom_video_embed_code'];
			}

			if ( $embed_code ) {
				// Replace URL variable and save video embed code as post meta.
				$embed_code = str_replace( '{video_url}', $url, $embed_code );
				add_post_meta( $post_id, 'pyre_video_embed_code', $embed_code, true );

				// No further processing of video URLs.
				return false;
			}
		}

		return $attachment;
	} // check_attachment

	/**
	 * Save location related property metadata.
	 *
	 * @since 1.5
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
	 * Try to determine/save the property agent.
	 *
	 * @since 1.5
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
				'key' => 'is_agent',
				'value' => 'yes'
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
	} // save_agent

	/**
	 * Add additional details to main post content.
	 *
	 * @since 1.5
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function add_additional_details( $post_id, $immobilie ) {
		// Get names and titles of groups to add from the plugin theme options.
		$groups_raw = explode( ',', $this->plugin->multilang_get_string_translation( $this->theme_options['add_description_groups'] ) );
		if ( ! $groups_raw || 0 === count( $groups_raw ) ) return;

		$groups = array();
		foreach ( $groups_raw as $group_raw ) {
			if ( false !== strpos( $group_raw, ':' ) ) {
				// Group string contains a title: split it.
				$group_temp = explode( ':', $group_raw );
				$groups[trim( $group_temp[0] )] = trim( $group_temp[1] );
			} else {
				$groups[trim( $group_raw )] = ucwords( trim( $group_raw ) );
			}
		}

		$immonex_custom_fields = get_post_meta( $post_id, '_immonex_custom_fields', true );

		if ( $immonex_custom_fields && count( $immonex_custom_fields ) > 0 ) {
			$group_keys = array_keys( $groups );
			$details = array();
			$details_html = '';

			foreach ( $immonex_custom_fields as $title => $field_data ) {
				if ( in_array( $field_data['group'], $group_keys ) ) {
					$details[$field_data['group']][$title] = $field_data['value'];
				}
			}

			foreach ( $groups as $group => $section_title ) {
				if ( count( $details[$group] ) > 0 ) {
					$group_section_html = "\n" . '<h5 class="additional-features-headline">' . $section_title . "</h5>\n";
					$group_section_html .= '<ul class="additional-features">' . "\n";

					foreach ( $details[$group] as $item_title => $item_value ) {
						$group_section_html .= "\t<li>" . $item_title . ': <span class="additional-features-value">' . __( $item_value, 'immonex-openimmo2wp' ) . "</span></li>\n";
					}

					$group_section_html .= "</ul>\n";
					$details_html .= $group_section_html;
				}
			}

			if ( ! empty( $details_html ) ) {
				$post = get_post( $post_id );

				if ( $post ) {
					$post_update = array(
						'ID' => $post->ID,
						'post_content' => $post->post_content . $details_html
					);

					wp_update_post( $post_update );
				}
			}
		}
	} // add_additional_details

	/**
	 * Add special taxonomy terms to the current property.
	 *
	 * @since 1.5
	 *
	 * @param string $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 */
	public function add_special_terms( $post_id, $immobilie ) {
		if ( $this->theme_options['mark_every_property_as_featured'] ) {
			wp_set_object_terms( $post_id, array( 'featured', 'widget' ), 'property_type', true );
		}

		if ( $this->theme_options['mark_every_property_homepage'] ) {
			wp_set_object_terms( $post_id, 'homepage', 'property_type', true );
		}
	} // add_special_terms

	/**
	 * Add configuration sections to the theme options tab.
	 *
	 * @since 1.5
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
	 * @since 1.5
	 *
	 * @param array $fields Original fields array.
	 *
	 * @return array Extended fields array.
	 */
	public function extend_fields( $fields ) {
		$fields = array_merge( $fields, array(
			array(
				'name' => $this->theme_class_slug . '_mark_every_property_as_featured',
				'type' => 'checkbox',
				'label' => __( 'Mark every imported property as featured', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'If activated, every imported property will appear in the start page slider and the featured properties list.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_mark_every_property_homepage',
				'type' => 'checkbox',
				'label' => __( 'Display every imported property on homepage', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'If activated, every imported property will appear in the homepage listings <strong>if the related theme option is set</strong>.', 'immonex-openimmo2wp' )
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
				'name' => $this->theme_class_slug . '_add_description_groups',
				'type' => 'text',
				'label' => __( 'Mapping groups in property description', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => wp_sprintf(
						__( 'Comma-separated list of <a href="%s" class="immonex-doc-link" target="_blank">mapping groups</a> to add as separate sections after the main property description, format <code>group_name:Title</code> (default: <code>additional_details:Further Details,epass:Energy Efficiency,prices:Additional Information on Prices</code>).', 'immonex-openimmo2wp' ),
						'https://docs.immonex.de/openimmo2wp/#/mapping/tabellen?id=group'
					),
					'class' => 'large-text'
				)
			),
			array(
				'name' => $this->theme_class_slug . '_youtube_embed_code',
				'type' => 'textarea',
				'label' => __( 'YouTube Embed Code', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Code for embedding YouTube videos, use <code>{video_id}</code> <strong>OR</strong> <code>{video_url}</code> as placeholders.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_vimeo_embed_code',
				'type' => 'textarea',
				'label' => __( 'Vimeo Embed Code', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Code for embedding Vimeo videos, use <code>{video_id}</code> <strong>OR</strong> <code>{video_url}</code> as placeholders.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => $this->theme_class_slug . '_custom_video_embed_code',
				'type' => 'textarea',
				'label' => __( 'Custom Video Embed Code', 'immonex-openimmo2wp' ),
				'section' => 'ext_section_' . $this->theme_class_slug . '_general',
				'args' => array(
					'description' => __( 'Code for embedding videos of alternative providers, use <code>{video_id}</code> <strong>OR</strong> <code>{video_url}</code> as placeholders.', 'immonex-openimmo2wp' )
				)
			)
		) );

		return $fields;
	} // extend_fields

} // class Freehold
