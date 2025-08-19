<?php
namespace immonex\OpenImmo2Wp\themes;

/**
 * Base class for theme/plugin import classes.
 */
abstract class Theme_Base {

	const
		THEME_TEMP_FILENAME = 'theme_temp';

	public
		$theme_class_slug;

	protected
		$plugin,
		$type = 'theme',
		$theme_name,
		$theme_display_name,
		$theme_version = '',
		$theme_options = array(),
		$property_post_type,
		$max_attachment_title_length = 32,
		$initial_widgets = array(),
		$temp = array(),
		$theme_temp_file,
		$supported = array();

	public
		$override_widget_theme_name = false;

	/**
	 * The constructor - Woohoo!
	 *
	 * @since 1.0
	 *
	 * @param immonex_OpenImmo2WP $plugin Main plugin object.
	 * @param array|bool $supported_theme_properties Properties used to identify the current theme/plugin.
	 */
	public function __construct( $plugin, $supported_theme_properties = false ) {
		$this->plugin = $plugin;
		$this->set_theme_options();

		if (
			false === $supported_theme_properties ||
			! isset( $supported_theme_properties['type'] ) ||
			'theme' === $supported_theme_properties['type'] )
		{
			$theme = wp_get_theme();
			if ( ! isset( $this->theme_name ) || $this->theme_name !== $theme->theme_parent ) $this->theme_name = $theme->name;
			$this->theme_version = $this->get_plain_version_number( $theme->parent() ? $theme->parent()->Version : $theme->Version );
		} elseif (
			isset( $supported_theme_properties['type'] ) &&
			'plugin' === $supported_theme_properties['type']
		) {
			$plugin = get_plugin_data( trailingslashit( WP_PLUGIN_DIR ) . $supported_theme_properties['main_plugin_file'] );
			$this->theme_name = $plugin['Name'];
			$this->theme_version = $this->get_plain_version_number( $plugin['Version'] );
			$this->type = 'plugin';
		}

		$this->theme_display_name = isset( $supported_theme_properties['display_name'] ) && $supported_theme_properties['display_name'] ? $supported_theme_properties['display_name'] : $this->theme_name;

		$this->theme_temp_file = trailingslashit( apply_filters( "{$this->plugin->plugin_prefix}working_dir", '' ) ) . self::THEME_TEMP_FILENAME;
		$this->load_temp_theme_data();

		if ( $this->property_post_type ) {
			$this->plugin->set_property_post_type( $this->property_post_type );
			add_filter( 'immonex_oi2wp_set_property_post_type', array( $this, 'set_property_post_type' ) );
		}

		if ( $this->max_attachment_title_length ) add_filter( 'immonex_property_attachment_widget_config', array( $this, 'set_max_attachment_title_length' ) );

		add_filter( 'body_class', array( $this, 'add_body_classes' ) );

		add_action( $this->plugin->plugin_prefix . 'attachment_added', array( $this, 'reset_attachment_order' ), 10, 4 );
		add_action( $this->plugin->plugin_prefix . 'import_file_processed', array( $this, 'delete_temp_theme_data' ), 15, 2 );

		if ( count( $this->initial_widgets ) > 0 ) add_action( 'admin_init', array( $this, 'install_initial_theme_widgets' ), 20 );

		if ( method_exists( $this, 'extend_sections' ) ) {
			// Add theme-specific options to main plugin configuration page.
			add_filter( $this->plugin->plugin_slug . '_option_tabs', array( $this, 'add_theme_options_tab' ), 20 );
			add_filter( $this->plugin->plugin_slug . '_option_sections', array( $this, 'extend_sections' ), 20 );
			add_filter( $this->plugin->plugin_slug . '_option_fields', array( $this, 'extend_fields' ), 20 );
		}
	} // __construct

	/**
	 * Check if the theme supports the function/data with the given key.
	 *
	 * @since 4.9.37-beta
	 *
	 * @param string $key Function/Data key.
	 *
	 * @return bool True if supported.
	 */
	public function supports( $key ) {
		$supported = apply_filters(
			"{$this->plugin->plugin_prefix}theme_supports",
			$this->supported,
			$this->theme_class_slug,
			$this->theme_name,
			$this->theme_version
		);

		if ( empty( $supported ) ) {
			return false;
		}

		if ( ! is_array( $supported ) ) {
			$supported = array( $supported );
		}

		return in_array( $key, $supported );
	} // supports

	/**
	 * Add base plugin options with keys beginning with the theme slug to
	 * the theme options array.
	 *
	 * @since 1.5
	 */
	public function set_theme_options() {
		if ( is_array( $this->plugin->plugin_options ) && count( $this->plugin->plugin_options ) > 0 ) {
			// Add base plugin options with keys beginning with the theme slug to
			// the theme options array.
			foreach ( $this->plugin->plugin_options as $key => $value ) {
				if ( $this->theme_class_slug === substr( $key, 0, strlen( $this->theme_class_slug ) ) ) {
					$this->theme_options[substr( $key, strlen( $this->theme_class_slug ) + 1 )] = $value;
				}
			}
		}
	} // set_theme_options

	/**
	 * Set a custom name for the property post type before import.
	 *
	 * @since 1.9
	 *
	 * @param string $post_type Post type name.
	 *
	 * @return string New post type name.
	 */
	public function set_property_post_type( $post_type ) {
		return $this->property_post_type;
	} // set_property_post_type

	/**
	 * Set the maximum length of attachment titles in immonex property attachments widget.
	 *
	 * @since 2.5
	 *
	 * @param mixed $widget_config Associative array of widget config data.
	 *
	 * @return mixed Update widget config data array.
	 */
	public function set_max_attachment_title_length( $widget_config ) {
		$widget_config['max_attachment_title_length'] = $this->max_attachment_title_length;

		return $widget_config;
	} // set_max_attachment_title_length

	/**
	 * Try to determine the property agent (WP user) and set it as post author, if successful.
	 *
	 * @since 1.0
	 *
	 * @param array $post_data Current post data.
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param array $user_args Arguments for user search (optional).
	 *
	 * @return array (Possibly) modified property data to store as post record.
	 */
	public function set_agent_as_post_author( $post_data, $immobilie, $user_args = array() ) {
		$agent_data = $this->get_agent_data( $immobilie );
		$name_contact = $agent_data['name'];
		$email_contact = $agent_data['email'];

		if ( $name_contact ) {
			$this->plugin->log->add( wp_sprintf( __( 'Contact person (Agent): %s', 'immonex-openimmo2wp' ), $name_contact ), 'debug' );

			$user = $this->get_agent_user( $immobilie, $user_args, true, isset( $post_data['post_author'] ) ? $post_data['post_author'] : false );
			if ( $user ) $post_data['post_author'] = $user->ID;
		}

		return $post_data;
	} // set_agent_as_post_author

	/**
	 * Try to determine the property agent (WP user).
	 *
	 * @since 2.2
	 *
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param array $user_args Arguments for user search (optional).
	 * @param bool $add_log_entry Add log entry if user has been found (optional)?
	 * @param int|bool $current_user_id ID if a user is already assigned.
	 *
	 * @return object|bool User object or false if not available
	 */
	public function get_agent_user( $immobilie, $user_args = array(), $add_log_entry = false, $current_user_id = false ) {
		$current_import_folder = $this->plugin->current_import_folder;

		if ( 'global' !== $current_import_folder ) {
			/**
			 * Property is being imported via a user import folder: Let's see if a name of
			 * a WP user matches.
			 */
			$slashpos = strrpos( $current_import_folder, DIRECTORY_SEPARATOR );
			if ( false !== $slashpos ) $current_import_folder = substr( $current_import_folder, $slashpos + 1 );

			$user = get_user_by( 'login', $current_import_folder );

			if ( $user ) {
				if ( $add_log_entry && $user->ID != $current_user_id ) $this->plugin->log->add( wp_sprintf( __( 'WP user found by matching login name with the user import folder name: %1$s (ID %2$s)', 'immonex-openimmo2wp' ), $user->display_name, $user->ID ), 'debug' );
				return $user;
			}
		}

		$agent_data = $this->get_agent_data( $immobilie );
		$users = get_users( apply_filters( "{$this->plugin->plugin_prefix}get_agent_users_args", $user_args ) );

		$best_matching_user = false;
		$user_match_score = array();
		$similarity = 0;

		if ( count( $users ) > 0 ) {
			foreach ( $users as $user ) {
				$user_match_score[$user->ID] = 0;

				// Loop through found WordPress users...
				similar_text( $agent_data['name'], $user->display_name, $similarity );
				similar_text( $agent_data['name_plain'], $user->display_name, $similarity_plain_name );
				if ( $similarity_plain_name > $similarity ) $similarity = $similarity_plain_name;

				if ( $similarity >= 85 ) {
					// ...and add two "score points" if name similarity is greater or equal than 85 %...
					if ( $add_log_entry && $user->ID != $current_user_id ) $this->plugin->log->add( wp_sprintf( __( 'WP user found by name similarity: %1$s (ID %2$s)', 'immonex-openimmo2wp' ), $user->display_name, $user->ID ), 'debug' );
					$user_match_score[$user->ID] += 2;
					if ( ! $best_matching_user || $user_match_score[$user->ID] > $user_match_score[$best_matching_user->ID] ) $best_matching_user = $user;
				}

				foreach ( array( $agent_data['email'], $agent_data['email_company'] ) as $email ) {
					if ( $email == strtolower( $user->user_email ) ) {
						// ...or one if an email address matches.
						if ( $add_log_entry && $user->ID != $current_user_id ) $this->plugin->log->add( wp_sprintf( __( 'WP user found by email address: %1$s (ID %2$s)', 'immonex-openimmo2wp' ), $user->display_name, $user->ID ), 'debug' );
						$user_match_score[$user->ID]++;
						if ( ! $best_matching_user || $user_match_score[$user->ID] > $user_match_score[$best_matching_user->ID] ) $best_matching_user = $user;
						break;
					}
				}
			}
		}

		if ( $best_matching_user && $add_log_entry && $best_matching_user->ID !== $current_user_id ) {
			$this->plugin->log->add(
				wp_sprintf(
					__( 'Best matching user: %1$s (ID %2$s)', 'immonex-openimmo2wp' ),
					$best_matching_user->display_name,
					$best_matching_user->ID
				),
				'debug'
			);
		}

		return $best_matching_user;
	} // get_agent_user

	/**
	 * Get an agent with a matching name or mail address.
	 *
	 * @since 2.6
	 *
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param string $post_type Name of the custom post type.
	 * @param mixed $compare_meta Associative array of custom fields to be used for name/email comparison.
	 * @param mixed $additional_args Additional arguments for the agent query (optional).
	 * @param bool $add_log_entry Add log entry if an agent has been found (optional)?
	 * @param bool $check_import_folder Only find agents with a matching import folder meta value (optional)?
	 * @param bool $force_name_match Force matching or similar name? Email address alone is not sufficient in this case (optional).
	 *
	 * @return mixed|bool Associative array of agent data or false if not found.
	 */
	public function get_agent( $immobilie, $post_type, $compare_meta, $additional_args = array(), $add_log_entry = false, $check_import_folder = false, $force_name_match = false ) {
		$agents = $this->get_agent_posts_multilang( $post_type, false, $additional_args, $check_import_folder );
		$agent_data_search = $this->get_agent_data( $immobilie );

		$best_matching_agent = false;
		$agent_match_score = array();

		if ( count( $agents ) > 0 ) {
			foreach ( $agents as $agent ) {
				$agent_match_score[$agent->ID] = 0;
			}

			foreach ( $agents as $agent ) {
				// Loop through all agents...
				$exact_match = false;
				$similarity = 0;
				$similarity_plain_name = 0;

				if (
					! empty( $compare_meta['first_name'] ) &&
					! empty( $compare_meta['last_name'] )
				) {
					$first_name = get_post_meta( $agent->ID, $compare_meta['first_name'], true );
					$last_name = get_post_meta( $agent->ID, $compare_meta['last_name'], true );

					if (
						(
							$first_name &&
							$agent_data_search['first_name'] &&
							$first_name === $agent_data_search['first_name']
						) && (
							$last_name &&
							$agent_data_search['last_name'] &&
							$last_name === $agent_data_search['last_name']
						)
					) {
						$exact_match = true;
					}
				}

				if ( ! $exact_match ) {
					$agent_name_compare = ! empty( $compare_meta['name'] ) ? get_post_meta( $agent->ID, $compare_meta['name'], true ) : $agent->post_title;

					if ( $agent_name_compare && $agent_data_search['name'] ) {
						similar_text( $agent_data_search['name'], $agent_name_compare, $similarity );
					}
					if ( $agent_name_compare && $agent_data_search['name_plain'] ) {
						similar_text( $agent_data_search['name_plain'], $agent_name_compare, $similarity_plain_name );
					}

					if ( $similarity_plain_name > $similarity ) {
						$similarity = $similarity_plain_name;
					}
				}

				if ( $exact_match ) {
					// ...and add three "score points" if first and last names exactly match...
					if ( $add_log_entry ) $this->plugin->log->add( wp_sprintf( __( 'Agent found by exact name match: %1$s (ID %2$s)', 'immonex-openimmo2wp' ), "{$first_name} {$last_name}", $agent->ID ), 'debug' );
					$agent_match_score[$agent->ID] += 3;
					if (
						! $best_matching_agent ||
						$agent_match_score[$agent->ID] > $agent_match_score[$best_matching_agent->ID]
					) {
						$best_matching_agent = $agent;
					}
				} elseif ( $similarity >= 85 ) {
					// ...and add two "score points" if name similarity is greater or equal than 85 %...
					if ( $add_log_entry ) $this->plugin->log->add( wp_sprintf( __( 'Agent found by name similarity: %1$s (ID %2$s)', 'immonex-openimmo2wp' ), $agent->post_title ? $agent->post_title : __( 'no name specified', 'immonex-openimmo2wp' ), $agent->ID ), 'debug' );
					$agent_match_score[$agent->ID] += 2;
					if (
						! $best_matching_agent ||
						$agent_match_score[$agent->ID] > $agent_match_score[$best_matching_agent->ID]
					) {
						$best_matching_agent = $agent;
					}
				}

				if ( $force_name_match && $agent_match_score[$agent->ID] === 0 ) {
					// Skip this agent due to name mismatch (exact match or similar name required).
					continue;
				}

				$agent_email_compare = ! empty( $compare_meta['email'] ) ? get_post_meta( $agent->ID, $compare_meta['email'], true ) : false;

				foreach ( array( $agent_data_search['email'], $agent_data_search['email_company'] ) as $email ) {
					if ( $agent_email_compare && $email == strtolower( $agent_email_compare ) ) {
						// ...or one if an email address matches.
						if ( $add_log_entry ) $this->plugin->log->add( wp_sprintf( __( 'Agent found by email address: %1$s (ID %2$s)', 'immonex-openimmo2wp' ), $agent->post_title ? $agent->post_title : __( 'no name specified', 'immonex-openimmo2wp' ), $agent->ID ), 'debug' );
						$agent_match_score[$agent->ID]++;
						if (
							! $best_matching_agent ||
							$agent_match_score[$agent->ID] > $agent_match_score[$best_matching_agent->ID]
						) {
							$best_matching_agent = $agent;
						}
						break;
					}
				}
			}
		}

		if ( $best_matching_agent && $add_log_entry ) {
			$this->plugin->log->add(
				wp_sprintf(
					__( 'Best matching agent: %1$s (ID %2$s)', 'immonex-openimmo2wp' ),
					$best_matching_agent->post_title ? $best_matching_agent->post_title : __( 'no name specified', 'immonex-openimmo2wp' ),
					$best_matching_agent->ID
				),
				'debug'
			);
		}

		return $best_matching_agent;
	} // get_agent

	/**
	 * Get all agent posts in a specified or the current import language.
	 *
	 * @since 2.4
	 *
	 * @param string $post_type Name of the custom post type.
	 * @param string $language ISO2 language code (optional).
	 * @param mixed $additional_args Additional query args (optional).
	 * @param bool $check_import_folder Only find agents with a matching import folder meta value (optional)?
	 *
	 * @return mixed|bool Associative array of agents or false if not found.
	 */
	public function get_agent_posts_multilang( $post_type, $language = false, $additional_args = array(), $check_import_folder = false ) {
		global $wpdb;

		if ( ! $language ) {
			$language = $this->plugin->current_import_language;
		}

		$default_args = array(
			'post_type' => $post_type,
			'posts_per_page' => -1,
			'lang' => '',
			'meta_query' => array()
		);

		$args = apply_filters( "{$this->plugin->plugin_prefix}get_agents_args", array_merge( $default_args, $additional_args ) );

		if ( $check_import_folder ) {
			$current_import_folder = $this->plugin->current_import_folder;

			$args['meta_query'][] = array(
				'key' => '_immonex_import_folder',
				'value' => $current_import_folder
			);
		}

		$agents_raw = get_posts( $args );
		$agents = array();

		if ( count( $agents_raw ) > 0 ) {
			foreach ( $agents_raw as $agent ) {
				$agent_language = false;

				if ( function_exists( 'pll_get_post_language' ) ) {
					// Polylang available.
					$agent_language = pll_get_post_language( $agent->ID );
				} else {
					// WPML possibly available.
					$wpml_args = array( 'element_id' => $agent->ID, 'element_type' => $post_type );
					$agent_language = apply_filters( 'wpml_element_language_code', null, $wpml_args );
				}

				if ( $agent_language === $language || ! $agent_language ) {
					// Term in current language found via Polylang/WPML or no language given.
					$agents[] = $agent;
				}

			}
		}

		return $agents;
	} // get_agent_posts_multilang

	/**
	 * Add theme/parent theme name info classes to body classes.
	 *
	 * @since 2.4
	 *
	 * @param string[] $classes Current class list.
	 *
	 * @return string[] Extended class list.
	 */
	public function add_body_classes( $classes ) {
		$theme_names = $this->plugin->theme_names;

		$theme_base_name = $this->plugin->string_utils->slugify( $this->plugin->theme_names['theme_name'] );
		$theme_body_class = "imnx-th-{$theme_base_name}";

		$theme_version = str_replace( '.', '-', $this->theme_version );
		$theme_version_body_class = $theme_version ? "{$theme_body_class}-{$theme_version}" : null;

		$theme_major_version = (int) $theme_version;
		$theme_major_version_body_class = $theme_major_version ? "{$theme_body_class}-{$theme_major_version}" : null;

		$parent_theme_body_class = $this->plugin->theme_names['parent_theme_name'] ?
			'imnx-pth-' . $this->plugin->string_utils->slugify( $this->plugin->theme_names['parent_theme_name'] ) :
			"imnx-pth-{$theme_base_name}";
		$parent_theme_major_version_body_class = $theme_major_version ? "{$parent_theme_body_class}-{$theme_major_version}" : null;

		$classes = array_merge(
			$classes,
			array( $theme_body_class, $theme_version_body_class, $theme_major_version_body_class, $parent_theme_body_class, $parent_theme_major_version_body_class )
		);

		return array_unique(
			array_filter( $classes )
		);
	} // add_body_classes

	/**
	 * Reset the "menu_order" field of the given attachment (possibly manually set via WP backend).
	 *
	 * @since 4.3
	 */
	public function reset_attachment_order( $att_id, $valid_image_formats, $valid_misc_formats, $valid_video_formats ) {
		if ( apply_filters( "{$this->plugin->plugin_prefix}reset_attachment_order", true ) ) {
			wp_update_post( array(
				'ID' => $att_id,
				'menu_order' => 0
			) );
		}
	} // reset_attachment_order

	/**
	 * Delete the temporary file for theme-specific temporary data.
	 *
	 * @since 1.5.1
	 *
	 * @return bool true on successful deletion.
	 */
	public function delete_temp_theme_data() {
		return file_exists( $this->theme_temp_file ) ? unlink( $this->theme_temp_file ) : false;
	} // delete_temp_theme_data

	/**
	 * Install initial theme widgets.
	 *
	 * @since 3.7
	 * @access protected
	 */
	public function install_initial_theme_widgets() {
		$widget_init_done_option_name = 'immonex_' . $this->theme_class_slug . '_initial_widgets_installed';

		if (
			isset( $_GET['immonex_init_theme_widgets'] ) || (
				! $this->plugin->plugin_options['previous_plugin_version'] &&
				! get_option( $widget_init_done_option_name )
			)
		) {
			$active_widgets = get_option( 'sidebars_widgets' );

			foreach ( $this->initial_widgets as $sidebar => $widgets ) {
				if ( ! isset( $active_widgets[$sidebar] ) ) continue;

				$init_widgets_already_in_sidebar = array();

				foreach ( $widgets as $widget_name => $widget_options_sets ) {
					$current_id = 0;
					// Get highest ID (count) of the current widget (all sidebars).
					foreach ( $active_widgets as $sidebar_name => $sidebar_widgets ) {
						if ( is_array( $sidebar_widgets ) && count( $sidebar_widgets ) > 0 ) {
							foreach ( $sidebar_widgets as $sidebar_widget_name ) {
								if ( substr( $sidebar_widget_name, 0, strlen( $widget_name ) ) === $widget_name ) {
									$init_widgets_already_in_sidebar[] = $widget_name;
									$cnt = substr( $sidebar_widget_name, strrpos( $sidebar_widget_name, '-' ) + 1 );
									if ( (int) $cnt ) $current_id = $cnt;
								}
							}
						}
					}

					if ( ! in_array( $widget_name, $init_widgets_already_in_sidebar ) ) {
						foreach ( $widget_options_sets as $widget_options ) {
							/**
							 * Add current widget/option set to assigned sidebar.
							 * (Widgets can be added multiple times.)
							 */
							$current_id++;

							$active_widgets[$sidebar][] = $widget_name . "-{$current_id}";

							$widget_options_name = 'widget_' . $widget_name;
							$new_widget_options = get_option( $widget_options_name );
							if ( ! $new_widget_options || ! is_array( $new_widget_options ) ) $new_widget_options = array();
							$new_widget_options[$current_id] = $widget_options;
							update_option( $widget_options_name, $new_widget_options );
						}
					}
				}
			}

			// Save (possibly) extended widget options.
			update_option( 'sidebars_widgets', $active_widgets );
			update_option( $widget_init_done_option_name, 1, false );
		}
	} // install_initial_theme_widgets

	/**
	 * Add a settings tab for theme/plugin-specific options.
	 *
	 * @since 5.0.0
	 *
	 * @param mixed $tabs Original tabs array.
	 *
	 * @return array Extended tabs array.
	 */
	public function add_theme_options_tab( $tabs ) {
		$extended_tabs = array_merge( $tabs, array(
			'ext_tab_' . $this->theme_class_slug => array(
				'title' => $this->theme_display_name,
				'attributes' => array(
					'badge' => $this->type
				)
			)
		) );

		return $extended_tabs;
	} // add_theme_options_tab

	/**
	 * Update a post author via direct DB query to bypass possibly set filters.
	 *
	 * @since 4.1
	 * @access protected
	 *
	 * @param int|string $post_id ID of the post to be updated.
	 * @param int|string $author_id Author ID.
	 */
	protected function update_post_author( $post_id, $author_id ) {
		global $wpdb;

		$result = $wpdb->update( $wpdb->posts, array( 'post_author' => $author_id ), array( 'ID' => $post_id ) );
		clean_post_cache( $post_id );
	} // update_post_author

	/**
	 * Get geographic data of a property.
	 *
	 * @since 1.0
	 * @access protected
	 *
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param bool $translate Translate country to current import language (optional)?
	 *
	 * @return array Property's geo data.
	 */
	protected function get_property_geodata( $immobilie, $translate = false ) {
		$plugin = $this->plugin;
		// Make general property address publishing approval modifiable by filter functions.
		$address_publishing_approved = apply_filters(
			"{$this->plugin->plugin_prefix}approve_property_address_publishing",
			! in_array( (string) $immobilie->verwaltung_objekt->objektadresse_freigeben, array( 'false', '0' ) )
		);

		$geodata = array(
			'publishing_approved' => $address_publishing_approved,
			'lat' => false,
			'lng' => false,
			'street_raw' => '',
			'street_number' => '',
			'street' => '',
			'postcode' => '',
			'city_raw' => '',
			'city' => '',
			'state' => '',
			'address_output' => '',
			'address_geocode' => '',
			'address_geocode_is_coordinates' => false,
			'country_data' => array()
		);

		if (
			$immobilie->geo->geokoordinaten &&
			(
				isset( $immobilie->geo->geokoordinaten['breitengrad'] ) &&
				(string) $immobilie->geo->geokoordinaten['breitengrad'] &&
				(string) $immobilie->geo->geokoordinaten['breitengrad'] != '0.0'
			) &&
			(
				isset( $immobilie->geo->geokoordinaten['laengengrad'] ) &&
				(string) $immobilie->geo->geokoordinaten['laengengrad'] &&
				(string) $immobilie->geo->geokoordinaten['laengengrad'] != '0.0'
			)
		) {
			$geodata['lat'] = (string) $this->plugin->geo_utils->validate_coords( (string) $immobilie->geo->geokoordinaten['breitengrad'], 'lat' );
			$geodata['lng'] = (string) $this->plugin->geo_utils->validate_coords( (string) $immobilie->geo->geokoordinaten['laengengrad'], 'lng' );
		}

		$street_temp = '';
		if ( isset( $immobilie->geo->strasse ) ) {
			$geodata['street_raw'] = (string) $immobilie->geo->strasse;
			$street_temp .= (string) $immobilie->geo->strasse . ' ';
		}
		if ( isset( $immobilie->geo->hausnummer ) ) {
			$geodata['street_number'] = (string) $immobilie->geo->hausnummer;
			$street_temp .= (string) $immobilie->geo->hausnummer;
		}
		$geodata['street'] = trim( $street_temp );

		if ( isset( $immobilie->geo->plz ) ) $geodata['postcode'] = (string) $immobilie->geo->plz;
		if ( isset( $immobilie->geo->ort ) ) $geodata['city_raw'] = (string) $immobilie->geo->ort;

		$city_temp = '';
		if ( isset( $immobilie->geo->plz ) ) $city_temp .= (string) $immobilie->geo->plz . ' ';
		if ( isset( $immobilie->geo->ort ) ) $city_temp .= (string) $immobilie->geo->ort;
		$geodata['city'] = trim( $city_temp );

		if ( isset( $immobilie->geo->bundesland ) ) $geodata['state'] = (string) $immobilie->geo->bundesland;

		if ( isset( $immobilie->geo->land ) ) {
			$country_data = \inveris_Iso_Countries::get_country( $immobilie->geo->land['iso_land'] );
			if ( $country_data ) $geodata['country_data'] = $country_data;
		}

		$address_geocode_temp = array();
		if ( $address_publishing_approved && $geodata['street'] ) $address_geocode_temp[] = $geodata['street'];
		if ( $geodata['city'] ) $address_geocode_temp[] = $geodata['city'];

		$geodata['address_output'] = implode( ', ', $address_geocode_temp );
		$geodata['address_output_incl_country'] = $geodata['address_output'];

		$geodata['country_data']['Common Name EN'] = isset( $geodata['country_data']['Common Name'] ) ?
			$geodata['country_data']['Common Name'] : '';

		if ( isset( $geodata['country_data']['Common Name'] ) ) {
			$country = $geodata['country_data']['Common Name'];
			$country_translated = '';

			if ( 'en' !== $this->plugin->current_import_language ) {
				$country_translated = __( $country, 'immonex-openimmo2wp' );
				if ( $country_translated === $country ) {
					$country_translated = '';
				}
			}

			if ( ! $country_translated ) {
				$country_translated = $this->get_translation( $country );
			}

			if ( $country_translated ) {
				if ( $translate ) {
					$country = $country_translated;
					$geodata['country_data']['Common Name'] = $country;
				}

				// Always add translated country version to output address.
				$geodata['address_output_incl_country'] .= ', ' . $country_translated;
			} else {
				$this->plugin->log->add( wp_sprintf( __( 'Unable to translate the country: %s', 'immonex-openimmo2wp' ), $country ), 'error' );
			}
		}

		if (
			0 === count( $address_geocode_temp ) &&
			$geodata['lat'] &&
			$geodata['lng']
		) {
			$geodata['address_geocode'] = $geodata['lat'] . ',' . $geodata['lat'];
			$geodata['address_geocode_is_coordinates'] = true;
		} else {
			$geodata['address_geocode'] = implode( ', ', $address_geocode_temp );
		}

		$geodata['country_code_iso2'] = ! empty( $country_data['ISO 3166-1 2 Letter Code'] ) ?
			strtolower( $country_data['ISO 3166-1 2 Letter Code'] ) : '';

		return $geodata;
	} // get_property_geodata

	/**
	 * Get the contact person name and mailaddress for a given property.
	 *
	 * @since 1.1
	 * @access protected
	 *
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 *
	 * @return array Contact person (agent) name.
	 */
	protected function get_agent_data( $immobilie ) {
		// Merge the name of the property contact person.
		$title = isset( $immobilie->kontaktperson->titel ) ?
			(string) $immobilie->kontaktperson->titel : '';
		$first_name = isset( $immobilie->kontaktperson->vorname ) ?
			(string) $immobilie->kontaktperson->vorname : '';
		$last_name = isset( $immobilie->kontaktperson->name ) ?
			(string) $immobilie->kontaktperson->name : '';
		$company = isset( $immobilie->kontaktperson->firma ) ?
			(string) $immobilie->kontaktperson->firma : '';

		$name_contact = trim( $first_name . ' ' . $last_name );
		$name_contact_plain = $name_contact;

		if ( $name_contact && $title ) {
			$name_contact = "{$title} {$name_contact}";
		} elseif ( ! $name_contact && $company ) {
			$name_contact = $company;
			$name_contact_plain = $name_contact;
		}

		$email_company = isset( $immobilie->kontaktperson->email_zentrale ) ? strtolower( $immobilie->kontaktperson->email_zentrale ) : '';

		if ( isset( $immobilie->kontaktperson->email_direkt ) )
			$email_contact = strtolower( $immobilie->kontaktperson->email_direkt );
		elseif ( $email_company )
			$email_contact = $email_company;
		else
			$email_contact = '';

		if ( isset( $immobilie->kontaktperson->tel_durchw ) )
			$phone_contact = strtolower( $immobilie->kontaktperson->tel_durchw );
		elseif ( isset( $immobilie->kontaktperson->tel_handy ) )
			$phone_contact = strtolower( $immobilie->kontaktperson->tel_handy );
		elseif ( isset( $immobilie->kontaktperson->tel_zentrale ) )
			$phone_contact = strtolower( $immobilie->kontaktperson->tel_zentrale );
		else
			$phone_contact = '';

		return array(
			'first_name' => $first_name,
			'last_name' => $last_name,
			'company' => $company,
			'name' => $name_contact,
			'name_plain' => $name_contact_plain,
			'email' => $email_contact,
			'email_company' => $email_company,
			'phone' => $phone_contact
		);
	} // get_agent_data

	/**
	 * Get coordinates for a given property address.
	 *
	 * @since 1.8.6 beta
	 * @access protected
	 *
	 * @param string $address Property Address.
	 * @param bool blur Obfuscate coordinates (return random location within about 1.5 km radius around real location)?
	 * @param string|bool $country_code Country code (ISO2) or false if unavailable.
	 * @param int|bool $post_id Property post ID if available.
	 *
	 * @return array|boolean Geo coordinates of false on error.
	 */
	protected function geocode( $address, $blur = false, $country_code = false, $post_id = false ) {
		$coords = false;

		if ( isset( $this->temp['geocoding_cache'][$address] ) ) {
			$coords = $this->temp['geocoding_cache'][$address];
			$coords['from_cache'] = true;
		}

		if ( ! $coords ) {
			$providers_keys = $this->get_geocode_providers();
			$attempts = 0;

			while ( false === $coords && $attempts <= 3 ) {
				$attempts++;
				$coords = $this->plugin->geo_utils->geocode( $address, 'compact', $providers_keys['providers'], $providers_keys['keys'], $this->plugin->current_import_language, $country_code );
				if ( ! $coords ) sleep( 3 );
			}
		}

		if ( ! $coords ) {
			if ( preg_match( '/([0-9]{4,5}) ([^,]+)/', $address, $matches ) ) {
				// If failed before, try to geocode the location based on zipcode and locality name only.
				$address = $matches[0];
				$this->plugin->log->add( wp_sprintf( __( 'Geocoding with original address failed, retrying with zipcode and locality name only: %s', 'immonex-openimmo2wp' ), $address ), 'debug' );
				$coords = $this->plugin->geo_utils->geocode( $address, 'compact', $providers_keys['providers'], $providers_keys['keys'], $this->plugin->current_import_language, $country_code );
			}
		}

		if (
			is_array( $coords ) &&
			apply_filters( "{$this->plugin->plugin_prefix}enable_geocode_blurring", $blur )
		) {
			$blurring_factor = (float) apply_filters( "{$this->plugin->plugin_prefix}geocode_blurring_factor", 0.001 );

			$coords['lat'] = $coords['lat'] + rand( -5, 5 ) * $blurring_factor;
			$coords['lng'] = $coords['lng'] + rand( -5, 5 ) * $blurring_factor;
		}

		if (
			is_array( $coords ) &&
			empty( $coords['from_cache'] ) &&
			! empty( $coords['lat'] ) &&
			! empty( $coords['lng'] )
		) {
			$coords['from_cache'] = false;

			// Cache the geocoding result for this address/import process.
			$this->temp['geocoding_cache'][$address] = $coords;
			$this->save_temp_theme_data();
		}

		if (
			$post_id &&
			is_array( $coords ) &&
			! empty( $coords['lat'] ) &&
			! empty( $coords['lng'] ) && (
				! get_post_meta( $post_id, '_immonex_lat', true ) ||
				! get_post_meta( $post_id, '_immonex_lng', true )
			)
		) {
			update_post_meta( $post_id, '_immonex_lat', $coords['lat'] );
			update_post_meta( $post_id, '_immonex_lng', $coords['lng'] );
		}

		return $coords;
	} // geocode

	/**
	 * Get geocoding status information for a given address (e.g. on errors).
	 *
	 * @since 3.4.4 beta
	 * @access protected
	 *
	 * @param string $address Property Address.
	 * @param string|bool $country_code Country code (ISO2) or false if unavailable.
	 *
	 * @return string|bool Status information or false on retrieval error.
	 */
	protected function get_geocoding_status( $address, $country_code = false ) {
		$providers_keys = $this->get_geocode_providers();

		$geocoding_status = $this->plugin->geo_utils->get_geocoding_status( $address, $providers_keys['providers'], $providers_keys['keys'], $this->plugin->current_import_language, $country_code );

		return $geocoding_status;
	} // get_geocoding_status

	/**
	 * Get geocoding providers and keys.
	 *
	 * @since 3.4.4 beta
	 * @access protected
	 *
	 * @return array Associative array providers and keys.
	 */
	protected function get_geocode_providers() {
		$all_providers = array_keys( $this->plugin->geocoding_providers );

		$use_providers = array(
			// Use default geocoding provider first...
			$this->plugin->plugin_options['default_geocoding_provider']
		);

		$keys = array();

		foreach ( $all_providers as $provider ) {
			// ...then the following ones.
			if ( ! in_array( $provider, $use_providers ) ) $use_providers[] = $provider;

			// Get the related key (plugin options).
			if ( isset( $this->plugin->plugin_options["{$provider}_api_key"] ) ) $keys[$provider] = $this->plugin->plugin_options["{$provider}_api_key"];
		}

		return array(
			'providers' => $use_providers,
			'keys' => $keys
		);
	} // get_geocode_providers

	/**
	 * Get a (possibly cached) translation.
	 *
	 * @since 2.7
	 * @access protected
	 *
	 * @param string $source_string Source string to be translated.
	 * @param string $dest_language ISO-2 destination language code (optional, default = current import language).
	 *
	 * @return string|bool Translated string or false on error.
	 */
	protected function get_translation( $source_string, $dest_language = false ) {
		$source_language = 'en';
		if ( $dest_language ) $dest_language = trim( strtolower( $dest_language ) );
		else $dest_language = $this->plugin->current_import_language;
		if ( $dest_language === $source_language ) return $source_string;

		if ( isset( $this->temp['translation_cache'][$dest_language][$source_string] ) ) {
			return $this->temp['translation_cache'][$dest_language][$source_string];
		}

		$google_translate_url = wp_sprintf(
			'https://translate.googleapis.com/translate_a/single?client=gtx&sl=%s&tl=%s&dt=t&q=%s',
			$source_language,
			$dest_language,
			urlencode( $source_string )
		);
		$response = $this->plugin->general_utils->get_url_contents( $google_translate_url );

		$response_json = json_decode( $response, true );
		if ( ! $response_json || empty( $response_json[0][0][0] ) ) {
			return false;
		}

		$translation = $response_json[0][0][0];
		$this->temp['translation_cache'][$dest_language][$source_string] = $translation;
		$this->save_temp_theme_data();

		return $translation;
	} // get_translation

	/**
	 * Serialize and save theme-specific temporary data (file).
	 *
	 * @since 1.5.1
	 * @access protected
	 */
	protected function save_temp_theme_data() {
		$contents = serialize( $this->temp );
		if ( empty( $contents ) && ! empty( $this->temp ) ) {
			$this->plugin->log->add( __( 'Temporary theme data could not be serialized.', 'immonex-openimmo2wp' ), 'error' );
		}

		$result = file_put_contents( $this->theme_temp_file, $contents, LOCK_EX );
		if ( false === $result ) {
			$this->plugin->log->add( wp_sprintf( __( 'Error on saving temporary theme data to %s.', 'immonex-openimmo2wp' ), $this->theme_temp_file ), 'error' );
		}
	} // save_temp_theme_data

	/**
	 * Load and unserialize theme-specific temporary data (file).
	 *
	 * @since 1.5.1
	 */
	protected function load_temp_theme_data() {
		if ( file_exists( $this->theme_temp_file ) ) {
			$raw_temp_data = file_get_contents( $this->theme_temp_file );
			if ( false === $raw_temp_data ) {
				$this->plugin->log->add( wp_sprintf( __( 'Error on reading temporary theme data from %s.', 'immonex-openimmo2wp' ), $this->theme_temp_file ), 'error' );
			} elseif ( ! empty( $raw_temp_data ) ) {
				$temp_data = unserialize( $raw_temp_data, array( 'allowed_classes' => true ) );
				if ( false === $temp_data ) {
					$this->plugin->log->add( __( 'Temporary theme data could not be unserialized.', 'immonex-openimmo2wp' ), 'error' );
				}
				if ( ! empty( $temp_data ) ) {
					$this->temp = $temp_data;
				}
			}
		}

		if ( empty( $this->temp ) ) {
			$this->temp = array();
			$this->plugin->log->add( __( 'Empty temporary theme data.', 'immonex-openimmo2wp' ), 'debug' );
		}
	} // load_temp_theme_data

	/**
	 * Check if attachments with the given IDs exists.
	 *
	 * @since 2.1 beta
	 * @access protected
	 *
	 * @param array $att_ids Array of attachment IDs.
	 *
	 * @return array Clean and check array of valid and unique attachment IDs.
	 */
	protected function check_attachment_ids( $att_ids ) {
		$valid = array();

		if ( count( $att_ids ) > 0 ) {
			foreach ( $att_ids as $id ) {
				if ( ! in_array( $id, $valid ) && get_post( $id ) ) {
					$valid[] = $id;
				}
			}
		}

		return $valid;
	} // check_attachment_ids

	/**
	 * Extract a plain version number.
	 *
	 * @since 3.4
	 * @access protected
	 *
	 * @param string $version Version - maybe including theme name or the like.
	 *
	 * @return string Plain version number.
	 */
	protected function get_plain_version_number( $version ) {
		$version_regex = '/[0-9]{1,2}(\.[0-9]{1,2}(\.[0-9]{1,2})?)?(-beta)?/';
		$is_version = preg_match( $version_regex, $version, $matches );
		$version_plain = $is_version ? $matches[0] : '';

		return $version_plain;
	} // get_plain_version_number

	/**
	 * Generate the embed code for virtual tours.
	 *
	 * @since 3.6
	 * @access protected
	 *
	 * @param mixed[] $args Variables to replace.
	 *
	 * @return string Embed code.
	 */
	protected function get_virtual_tour_embed_code( $args ) {
		if ( ! is_array( $args ) || ! isset( $args['url'] ) ) return;

		$embed_code = apply_filters(
			"{$this->plugin->plugin_prefix}virtual_tour_embed_code",
			'<div class="immonex-virtual-tour-iframe-wrap"><iframe src="{url}" width="100%" height="450" frameborder="0" allowfullscreen mozallowfullscreen webkitallowfullscreen></iframe></div>'
		);

		foreach ( $args as $name => $value ) {
			$embed_code = str_replace( '{' . $name . '}', $value, $embed_code );
		}

		return $embed_code;
	} // get_virtual_tour_embed_code

	/**
	 * Add a taxonomy term (create if non-existent).
	 *
	 * @since 4.8
	 * @access protected
	 *
	 * @param int|string $post_id ID of the related post.
	 * @param string $term_name Term (name) to add.
	 * @param string $taxonomy Taxonomy.
	 * @param mixed[] $mapping Related mapping data (optional).
	 * @param SimpleXMLElement $immobilie XML node of a property object (optional).
	 */
	protected function add_taxonomy_term( $post_id, $term_name, $taxonomy, $mapping = array(), $immobilie = false ) {
		$term_data = apply_filters( "{$this->plugin->plugin_prefix}term_multilang", array(), $term_name, $taxonomy );
		$enable_multilang = apply_filters( "{$this->plugin->plugin_prefix}enable_multilang", true );

		if ( is_wp_error( $term_data ) ) {
			$this->plugin->log->add( wp_sprintf( __( 'Error on fetching taxonomy data: %s', 'immonex-openimmo2wp' ), $term_data->get_error_message() ), 'error' );
			return;
		}

		if ( empty( $term_data ) ) {
			$term_data = apply_filters( 'immonex_oi2wp_add_new_term', array(), $term_name, $taxonomy, false, 'import_value', $mapping, $immobilie );
		}

		if ( is_array( $term_data ) && isset( $term_data['term_id'] ) ) {
			$result = wp_set_object_terms( $post_id, (int) $term_data['term_id'], $term_data['taxonomy'], true );

			if ( is_wp_error( $result ) ) {
				$this->plugin->log->add( wp_sprintf( __( 'Error on saving taxonomy data: %s', 'immonex-openimmo2wp' ), $result->get_error_message() ), 'error' );
			}
		} elseif ( 'skip' === $term_data ) {
			$this->plugin->log->add( wp_sprintf( __( 'Skipped inserting a taxonomy term: %s', 'immonex-openimmo2wp' ), "{$term_name}, {$taxonomy}" ), 'debug' );
		} else {
			$this->plugin->log->add( wp_sprintf( __( 'Error on inserting a taxonomy term: %s', 'immonex-openimmo2wp' ) . ' (theme base)', is_wp_error( $term_data ) ? $term_data->get_error_message() : "{$term_name}, {$taxonomy}" ), 'debug' );
		}
	} // add_taxonomy_term

	/**
	 * Replace taxonomy terms based on a replacement mapping array.
	 *
	 * @since 5.3.12-beta
	 * @access protected
	 *
	 * @param int|string $post_id ID of the related post.
	 * @param string $term_name Term (name) to add.
	 * @param string $taxonomy Taxonomy.
	 * @param string[] $replace_map Replacement mappings.
	 */
	protected function maybe_replace_terms( $post_id, $taxonomy, $replace_map ) {
		$terms = get_the_terms( $post_id, $taxonomy );

		if ( empty( $terms ) ) {
			return;
		}

		$term_names = array();
		foreach ( $terms as $org_term ) {
			$term_names[] = $org_term->name;
		}

		foreach ( $terms as $org_term ) {
			if (
				! isset( $replace_map[ $org_term->name ] )
				|| in_array( $replace_map[ $org_term->name ], $term_names, true )
			) {
				continue;
			}

			wp_remove_object_terms( $post_id, $org_term->term_id, $taxonomy );
			$new_term_name = $replace_map[ $org_term->name ];
			$new_term = get_term_by( 'name', $new_term_name, $taxonomy );

			if ( $new_term ) {
				wp_add_object_terms( $post_id, $new_term->term_id, $taxonomy );
			} else {
				$this->add_taxonomy_term( $post_id, $new_term_name, $taxonomy );
			}
		}
	} // maybe_replace_terms

	/**
	 * Possibly extend a filename array by sanitized versions.
	 *
	 * @since 4.9
	 * @access protected
	 *
	 * @param string[] $filenames Source filename list.
	 *
	 * @return string[] Eventually extended filename list.
	 */
	protected function get_extended_filenames( $filenames ) {
		if ( empty( $filenames ) || ! is_array( $filenames ) ) {
			return array();
		}

		// Add sanitized versions of filenames first.
		$filenames = array_merge(
			$filenames,
			array_map(
				'sanitize_file_name',
				$filenames
			)
		);

		// AFTERWARDS, add basenames.
		$filenames = array_merge(
			$filenames,
			array_map(
				function ( $filename ) {
					return pathinfo(
						\immonex\OpenImmo2Wp\Attachment_Utils::get_url_basename( $filename ),
						PATHINFO_FILENAME
					);
				},
				$filenames
			)
		);

		return array_unique( $filenames );
	} // get_extended_filenames

	/**
	 * Strip counters, other suffixes and extension from filenames.
	 *
	 * @since 5.0.0
	 * @access protected
	 *
	 * @param string $filename Source filename.
	 *
	 * @return string Cleaned base filename.
	 */
	protected function get_plain_basename( $filename ) {
		$filename = preg_replace( '/(-[0-9]{1,3})?(-scaled)?(\.[a-zA-Z]{3,4})?$/', '', $filename );

		return preg_replace( '/(-)?scaled(-)?$/', '', $filename );
	} // get_plain_basename

	/**
	 * Return reference term replacement table.
	 *
	 * @since 5.3.12-beta
	 * @access protected
	 *
	 * @param string $taxonomy Taxonomy (optional, currently only inx_marketing_type).
	 *
	 * @return string[] Replacement table (original term => replacement term).
	 */
	protected function get_reference_term_replacement_map( $taxonomy = 'inx_marketing_type' ) {
		$type = substr( $taxonomy, strpos( $taxonomy, '_' ) );

		return apply_filters(
			"{$this->plugin->plugin_prefix}{$type}_reference_term_replacements",
			array(
				_x( 'For Sale', 'reference term to replace', 'immonex-openimmo2wp' ) => _x( 'Sold', 'reference term replacement', 'immonex-openimmo2wp' ),
				_x( 'For sale', 'reference term to replace', 'immonex-openimmo2wp' ) => _x( 'sold', 'reference term replacement', 'immonex-openimmo2wp' ),
				_x( 'for sale', 'reference term to replace', 'immonex-openimmo2wp' ) => _x( 'sold', 'reference term replacement', 'immonex-openimmo2wp' ),
				_x( 'To Purchase', 'reference term to replace', 'immonex-openimmo2wp' ) => _x( 'sold', 'reference term replacement', 'immonex-openimmo2wp' ),
				_x( 'to purchase', 'reference term to replace', 'immonex-openimmo2wp' ) => _x( 'sold', 'reference term replacement', 'immonex-openimmo2wp' ),
				_x( 'For Rent', 'reference term to replace', 'immonex-openimmo2wp' ) => _x( 'Rented', 'reference term replacement', 'immonex-openimmo2wp' ),
				_x( 'For rent', 'reference term to replace', 'immonex-openimmo2wp' ) => _x( 'rented', 'reference term replacement', 'immonex-openimmo2wp' ),
				_x( 'for rent', 'reference term to replace', 'immonex-openimmo2wp' ) => _x( 'rented', 'reference term replacement', 'immonex-openimmo2wp' ),
				_x( 'For Rent', 'reference term to replace (Zur Miete)', 'immonex-openimmo2wp' ) => _x( 'rented', 'reference term replacement', 'immonex-openimmo2wp' ),
				_x( 'for Rent', 'reference term to replace (zur Miete)', 'immonex-openimmo2wp' ) => _x( 'rented', 'reference term replacement', 'immonex-openimmo2wp' ),
				_x( 'To rent', 'reference term to replace (Zu mieten)', 'immonex-openimmo2wp' ) => _x( 'rented', 'reference term replacement', 'immonex-openimmo2wp' ),
				_x( 'to rent', 'reference term to replace (zu mieten)', 'immonex-openimmo2wp' ) => _x( 'rented', 'reference term replacement', 'immonex-openimmo2wp' ),
			)
		);
	} // get_reference_term_replacement_map

} // class Theme_Base
