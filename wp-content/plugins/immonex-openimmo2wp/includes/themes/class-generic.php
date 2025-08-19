<?php
namespace immonex\OpenImmo2Wp\themes;

if ( ! class_exists( 'Generic' ) ) {

	/**
	 * Generic theme processing.
	 */
	class Generic extends Theme_Base {

		public $theme_class_slug = 'generic';

		private	$cpt_args = array(
			'public' => true,
			'_builtin' => false
		);

		/**
		 * The constructor.
		 *
		 * @since 4.9.27-beta
		 *
		 * @param immonex_OpenImmo2WP $plugin Main plugin object.
		 * @param array $supported_theme_properties Associative array of extended theme properties (plain name, aliases etc.).
		 */
		public function __construct( $plugin, $supported_theme_properties ) {
			parent::__construct( $plugin, $supported_theme_properties );

			$this->theme_options = apply_filters( $this->plugin->plugin_prefix . 'theme_options', $this->theme_options );

			$this->property_post_type = $this->theme_options['property_post_type'];

			add_filter( 'immonex_oi2wp_set_property_post_type', array( $this, 'check_property_post_type' ), 5 );

			add_action( 'immonex_oi2wp_handle_property_location', array( $this, 'save_property_location' ), 10, 2 );
			add_action( 'immonex_oi2wp_property_imported', array( $this, 'save_agent' ), 10, 2 );
		} // __construct

		/**
		 * Check the given CPT name and adjust it if invalid (also in plugin options).
		 *
		 * @since 4.9.27-beta
		 *
		 * @param string $cpt_name CPT name.
		 *
		 * @return string Original or updated CPT name.
		 */
		public function check_property_post_type( $cpt_name ) {
			$cpt_names = get_post_types( $this->cpt_args, 'names' );

			if ( in_array( $cpt_name, $cpt_names ) || empty( $cpt_names ) ) {
				return $cpt_name;
			}

			$alternative_cpt_name = $this->autodetect_post_type( $cpt_names, 'property' );
			if ( $alternative_cpt_name ) {
				$plugin_options = $this->plugin->plugin_options;
				$plugin_options['generic_property_post_type'] = $alternative_cpt_name;
				update_option( $this->plugin->plugin_options_name, $plugin_options );

				return $alternative_cpt_name;
			}

			return $cpt_name;
		} // check_property_post_type

		/**
		 * Save the property address and/or coordinates (post meta) for geocoding.
		 *
		 * @since 4.9.27-beta
		 *
		 * @param string $post_id Property ID.
		 * @param SimpleXMLElement $immobilie XML node of a property object.
		 */
		public function save_property_location( $post_id, $immobilie ) {
			$geodata = $this->get_property_geodata( $immobilie, true );

			if ( ! $geodata['lat'] || ! $geodata['lng'] ) {
				$this->plugin->log->add( wp_sprintf(
					__( 'Property address (Geocoding): %s (ISO2: %s)', 'immonex-openimmo2wp' ),
					$geodata['address_geocode'],
					$geodata['country_code_iso2'] ? $geodata['country_code_iso2'] : __( 'none', 'immonex-openimmo2wp' )
				), 'debug' );
				$geo = $this->geocode(
					$geodata['address_geocode'],
					$geodata['publishing_approved'] ? false : true,
					$geodata['country_code_iso2'],
					$post_id
				);
				if ( $geo ) {
					$geodata['lat'] = $geo['lat'];
					$geodata['lng'] = $geo['lng'];
					$this->plugin->log->add( wp_sprintf(
						__( 'Geocoding result%s: %s%s', 'immonex-openimmo2wp' ),
						! empty( $geo['provider'] ) ? ' (' . $geo['provider'] . ')' : '',
						$geo['lat'] . ', ' . $geo['lng'],
						$geo['from_cache'] ? ' ' . __( '(cache)', 'immonex-openimmo2wp' ) : ''
					), 'debug' );
				} else {
					$geocoding_status = $this->get_geocoding_status( $geodata['address_geocode'], $geodata['country_code_iso2'] );
					$this->plugin->log->add( wp_sprintf( __( 'Geocoding failed (%s)', 'immonex-openimmo2wp' ), $geocoding_status ? $geocoding_status : __( 'unknown reason', 'immonex-openimmo2wp' ) ), 'debug' );
				}
			}

			if ( ! $geodata['publishing_approved'] ) {
				$this->plugin->log->add( __( 'Property address NOT approved for publishing', 'immonex-openimmo2wp' ), 'debug' );
			}

			if ( $geodata['lat'] && $geodata['lng'] ) {
				$this->plugin->log->add( wp_sprintf( __( 'Property geo coordinates: %s', 'immonex-openimmo2wp' ), "{$geodata['lat']}, {$geodata['lng']}" ), 'debug' );
			}

			add_post_meta( $post_id, '_immonex_property_geo', $geodata, true );
		} // save_property_location

		/**
		 * Try to determine/save the property user/agent.
		 *
		 * @since 5.3.7-beta
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
				// Set user as author and assign it to property.
				$this->update_post_author( $post->ID, $user->ID );
			}

			if (
				! $this->theme_options['agent_post_type']
				|| ! $this->theme_options['agent_post_id_cf']
			) {
				return;
			}

			$agent_meta = array(
				'name' => $this->theme_options['agent_meta_name'],
				'first_name' => $this->theme_options['agent_meta_first_name'],
				'last_name' => $this->theme_options['agent_meta_last_name'],
				'email' => $this->theme_options['agent_meta_email']
			);

			$agent = $this->get_agent( $immobilie, $this->theme_options['agent_post_type'], $agent_meta, array(), true );

			if ( $agent ) {
				// Save agent ID.
				add_post_meta( $post_id, sanitize_key( $this->theme_options['agent_post_id_cf'] ), $agent->ID, true );
			}
		} // save_agent

		/**
		 * Add configuration sections to the theme options tab.
		 *
		 * @since 4.9.27-beta
		 *
		 * @param mixed $sections Original sections array.
		 *
		 * @return array Extended sections array.
		 */
		public function extend_sections( $sections ) {
			$sections['ext_section_' . $this->theme_class_slug . '_general'] = array(
				'tab' => 'ext_tab_' . $this->theme_class_slug,
				'description' => __( 'With <em>generic</em> themes, the post types for real estate properties and – if available – agents can be defined individually.', 'immonex-openimmo2wp' )
			);
			$sections['ext_section_' . $this->theme_class_slug . '_agent_cf'] = array(
				'tab' => 'ext_tab_' . $this->theme_class_slug,
				'title' => __( 'Agent Post Custom Fields', 'immonex-openimmo2wp' ),
				'description' => __( 'If an agent post type is selected above, the <strong>names</strong> of the corresponding custom fields for the automated assignment during import can <strong>optionally</strong> be stated in the following fields.', 'immonex-openimmo2wp' )
			);

			return $sections;
		} // extend_sections

		/**
		 * Add configuration fields to an options section of the the theme options tab.
		 *
		 * @since 4.9.27-beta
		 *
		 * @param mixed $fields Original fields array.
		 *
		 * @return array Extended fields array.
		 */
		public function extend_fields( $fields ) {
			$custom_post_types = get_post_types( $this->cpt_args, 'objects' );
			$post_type_options = array();

			if ( ! empty( $custom_post_types ) ) {
				foreach ( $custom_post_types as $cpt_name => $cpt ) {
					$post_type_options[ $cpt_name ] = "{$cpt->label} [{$cpt_name}]";
				}

				$cpt_names = array_keys( $custom_post_types );
				if ( ! in_array( $this->theme_options['property_post_type'], $cpt_names ) ) {
					$this->theme_options['property_post_type'] = $this->autodetect_post_type( $cpt_names, 'property' );
				}
				if ( ! $this->theme_options['property_post_type'] ) {
					$this->theme_options['property_post_type'] = $cpt_names[0];
				}

				if ( ! $this->theme_options['agent_post_type'] ) {
					$this->theme_options['agent_post_type'] = $this->autodetect_post_type( $cpt_names, 'agent' );
				}
			}

			$fields = array_merge( $fields, array(
				array(
					'name' => $this->theme_class_slug . '_property_post_type',
					'type' => 'select',
					'label' => __( 'Property Post Type', 'immonex-openimmo2wp' ),
					'section' => 'ext_section_' . $this->theme_class_slug . '_general',
					'args' => array(
						'description' => '',
						'options' => $post_type_options,
						'value' => $this->theme_options['property_post_type']
					)
				),
				array(
					'name' => $this->theme_class_slug . '_agent_post_type',
					'type' => 'select',
					'label' => __( 'Agent Post Type', 'immonex-openimmo2wp' ),
					'section' => 'ext_section_' . $this->theme_class_slug . '_general',
					'args' => array(
						'description' => '',
						'options' => array_merge(
							/* translators: "none option" for no agent post type selection. */
							array( '' => __( 'none', 'immonex-openimmo2wp' ) ),
							$post_type_options
						),
						'value' => $this->theme_options['agent_post_type']
					)
				),
				array(
					'name' => $this->theme_class_slug . '_agent_post_id_cf',
					'type' => 'text',
					'label' => __( 'Agent Post ID Custom Field', 'immonex-openimmo2wp' ),
					'section' => 'ext_section_' . $this->theme_class_slug . '_general',
					'args' => array(
						'description' => __( 'Name of the <strong>property post</strong> custom field in which the agent post ID should be stored (if available).', 'immonex-openimmo2wp' ),
						'value' => $this->theme_options['agent_post_id_cf']
					)
				),
				array(
					'name' => $this->theme_class_slug . '_agent_meta_name',
					'type' => 'text',
					'label' => __( 'Name CF', 'immonex-openimmo2wp' ),
					'section' => 'ext_section_' . $this->theme_class_slug . '_agent_cf',
					'args' => array(
						'description' => wp_sprintf(
							/* translators: %1$s = information contained in the custom field, %2$s = optional default value. */
							__( 'Custom field that contains %1$s%2$s.', 'immonex-openimmo2wp' ),
							__( "the agent's <strong>full name</strong>", 'immonex-openimmo2wp' ),
							' (' . __( 'defaults to the <strong>post title</strong> if empty', 'immonex-openimmo2wp' ) . ')'
						),
						'value' => $this->theme_options['agent_meta_name']
					)
				),
				array(
					'name' => $this->theme_class_slug . '_agent_meta_first_name',
					'type' => 'text',
					'label' => __( 'First Name CF', 'immonex-openimmo2wp' ),
					'section' => 'ext_section_' . $this->theme_class_slug . '_agent_cf',
					'args' => array(
						'description' => wp_sprintf(
							/* translators: %1$s = information contained in the custom field, %2$s = optional default value. */
							__( 'Custom field that contains %1$s%2$s.', 'immonex-openimmo2wp' ),
							__( "the agent's <strong>first name</strong>", 'immonex-openimmo2wp' ),
							''
						),
						'value' => $this->theme_options['agent_meta_first_name']
					)
				),
				array(
					'name' => $this->theme_class_slug . '_agent_meta_last_name',
					'type' => 'text',
					'label' => __( 'Last Name CF', 'immonex-openimmo2wp' ),
					'section' => 'ext_section_' . $this->theme_class_slug . '_agent_cf',
					'args' => array(
						'description' => wp_sprintf(
							/* translators: %1$s = information contained in the custom field, %2$s = optional default value. */
							__( 'Custom field that contains %1$s%2$s.', 'immonex-openimmo2wp' ),
							__( "the agent's <strong>last name</strong>", 'immonex-openimmo2wp' ),
							''
						),
						'value' => $this->theme_options['agent_meta_last_name']
					)
				),
				array(
					'name' => $this->theme_class_slug . '_agent_meta_email',
					'type' => 'text',
					'label' => __( 'Email CF', 'immonex-openimmo2wp' ),
					'section' => 'ext_section_' . $this->theme_class_slug . '_agent_cf',
					'args' => array(
						'description' => wp_sprintf(
							/* translators: %1$s = information contained in the custom field, %2$s = optional default value. */
							__( 'Custom field that contains %1$s%2$s.', 'immonex-openimmo2wp' ),
							__( "the agent's <strong>email address</strong>", 'immonex-openimmo2wp' ),
							''
						),
						'value' => $this->theme_options['agent_meta_email']
					)
				)
			) );

			return $fields;
		} // extend_fields

		/**
		 * Autodetect the custom post type for properties or agents.
		 *
		 * @since 5.3.7-beta
		 *
		 * @param string[]|bool $cpt_names CPT names or false to retrieve them.
		 * @param string $type Type of post type to detect (property or agent).
		 *
		 * @return string Matching CPT name or empty string if not found.
		 */
		private function autodetect_post_type( $cpt_names, $type ) {
			if ( empty( $cpt_names ) ) {
				$cpt_names = get_post_types( $this->cpt_args, 'names' );
			}

			if ( empty( $cpt_names ) ) {
				return '';
			}

			$keywords = array(
				'property' => array( 'property', 'properties', 'realestate', 'real_estate', 'real-estate', 'immobilie' ),
				'agent' => array( 'agent', 'agents', 'estate_agent', 'real_estate_agent', 'makler', 'immobilienmakler' )
			);

			foreach ( $keywords[ $type ] as $keyword ) {
				foreach ( $cpt_names as $cpt_name ) {
					if ( false !== stripos( $cpt_name, $keyword ) ) {
						return $cpt_name;
					}
				}
			}

			return '';
		} // autodetect_post_type

	} // class Generic

}
