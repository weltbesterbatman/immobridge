<?php
/**
 * Class OpenImmo2WP
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Main plugin class.
 */
class OpenImmo2WP extends \immonex\WordPressFreePluginCore\V2_4_1\Base {

	const DEBUG_MODE_LOG_RECIPIENT   = 'pluginsupport@inveris.de';
	const PLUGIN_NAME                = 'immonex OpenImmo2WP';
	const PLUGIN_PREFIX              = 'immonex_oi2wp_';
	const PUBLIC_PREFIX              = 'immonex-oi2wp-';
	const TEXTDOMAIN                 = 'immonex-openimmo2wp';
	const PLUGIN_VERSION             = '5.3.21-beta';
	const PLUGIN_HOME_URL            = 'https://plugins.inveris.de/wordpress-plugins/immonex-openimmo2wp/';
	const PLUGIN_VERSION_BYNAME      = 'Poke';
	const PLUGIN_DOC_URLS            = array(
		'de' => 'https://docs.immonex.de/openimmo2wp/',
	);
	const PLUGIN_SUPPORT_URLS        = array(
		'de' => 'https://plugins.inveris.de/support/',
	);
	const PLUGIN_DEV_URLS            = array(
		'de' => 'https://immonex.dev/',
	);
	const OPTIONS_LINK_MENU_LOCATION = 'openimmo2wp';
	const DEFAULT_PROPERTY_POST_TYPE = 'property';

	const DELETE_CORRUPT_ZIP_ARCHIVES_IF_OLDER_THAN = '-3 hours'; // Value always starts with a minus sign.
	const KILLSWITCH_DURATION_MINUTES               = 10;
	const SKIP_PROPERTIES_UNAVAILABLE_LANGUAGES     = true;
	const MAPPING_BACKUPS_MAX_AGE_MONTHS            = 6;

	/**
	 * Updates and Licensing
	 */
	const FREE_LICENSE            = false;
	const EDD_ENABLE_AUTO_UPDATES = true;
	const EDD_SL_AUTHOR           = 'inveris';
	const EDD_SL_STORE_URL        = 'https://plugins.inveris.de';
	const EDD_SL_ITEM_ID          = 62;
	const EDD_SL_ITEM_NAME        = 'immonex OpenImmo2WP';

	/**
	 * Licensing Instance
	 *
	 * @var object
	 */
	private $licensing;

	/**
	 * "Killswitch" Option Name
	 *
	 * @var string
	 */
	private $killswitch_option_name;

	/**
	 * "Killswitch" Flag
	 *
	 * @var bool
	 */
	private $killswitch = false;

	/**
	 * Legacy Compatibility Object
	 *
	 * @var \immonex\OpenImmo2Wp\Legacy
	 */
	private $legacy;

	/**
	 * Security Object
	 *
	 * @var \immonex\OpenImmo2Wp\Security
	 */
	private $security;

	/**
	 * Template Folders
	 *
	 * @var string[]
	 */
	private $template_folders;

	/**
	 * Import Folders Object
	 *
	 * @var \immonex\OpenImmo2Wp\Import_Folders
	 */
	private $import_folders;

	/**
	 * Archive Folder Object
	 *
	 * @var \immonex\OpenImmo2Wp\Archive_Folder
	 */
	private $archive_folder;

	/**
	 * Mapping Folders Object
	 *
	 * @var \immonex\OpenImmo2Wp\Mapping_Folders
	 */
	private $mapping_folders;

	/**
	 * Process Resources Object
	 *
	 * @var \immonex\OpenImmo2Wp\Process_Resources
	 */
	private $process_resources;

	/**
	 * Property Time Object
	 *
	 * @var \immonex\OpenImmo2Wp\Property_Time
	 */
	private $property_time;

	/**
	 * Import Language Object
	 *
	 * @var \immonex\OpenImmo2Wp\Import_Language
	 */
	private $import_language;

	/**
	 * Taxonomy Utils Object
	 *
	 * @var \immonex\OpenImmo2Wp\Taxonomy_Utils
	 */
	private $taxonomy_utils;

	/**
	 * Temporary Options Object
	 *
	 * @var \immonex\OpenImmo2Wp\Temporary_Options
	 */
	private $temp_options;

	/**
	 * Mapping Entries
	 *
	 * @var mixed[]
	 */
	private $mappings = array();

	/**
	 * Mapping Error Flag
	 *
	 * @var bool
	 */
	private $mapping_error = false;

	/**
	 * Current Import Process Error Flag
	 *
	 * @var bool
	 */
	private $import_process_running_error = false;

	/**
	 * Mapping Files
	 *
	 * @var string[]
	 */
	private $mapping_files = array();

	/**
	 * WP-Cron Process Flag
	 *
	 * @var bool
	 */
	private $is_wpcron_process = false;

	/**
	 * immonex Cron Process Flag
	 *
	 * @var bool
	 */
	private $is_immonex_cron_process = false;

	/**
	 * Current Process Property Insert Counter
	 *
	 * @var int
	 */
	private $current_property_insert_count = 0;

	/**
	 * Current Process processed Attachments Counter
	 *
	 * @var int
	 */
	private $current_processed_attachments_count = 0;

	/**
	 * Current Process deleted Properties Counter
	 * (Relevant for full imports only.)
	 *
	 * @var int
	 */
	private $current_deleted_properties_count = 0;

	/**
	 * Current Process processed XML files
	 *
	 * @var string[]
	 */
	private $current_processed_xml_files = array();

	/**
	 * Current Status Option
	 *
	 * @var string
	 */
	private $current_status_option;

	/**
	 * Current Status File
	 *
	 * @var string
	 */
	private $current_status_file;

	/**
	 * Current Import ZIP File
	 *
	 * @var string
	 */
	private $current_import_zip_file;

	/**
	 * Current Import XML File
	 *
	 * @var string
	 */
	private $current_import_xml_file;

	/**
	 * Current Mapping File
	 *
	 * @var string
	 */
	private $current_mapping_file;

	/**
	 * Current Process OpenImmo ANID
	 *
	 * @var string|bool
	 */
	private $current_openimmo_anid = false;

	/**
	 * Processing Errors
	 *
	 * @var string[]
	 */
	private $processing_errors;

	/**
	 * Property Post Type
	 *
	 * @var string
	 */
	private $property_post_type;

	/**
	 * Current Post ID
	 *
	 * @var int|bool
	 */
	private $current_post_id = false;

	/**
	 * Current Property Main Image
	 *
	 * @var mixed[]|bool
	 */
	private $current_property_main_image = false;

	/**
	 * Geocoding Providers
	 *
	 * @var mixed[]
	 */
	private $geocoding_providers = array(
		'nominatim'   => 'Nominatim (OpenStreetMap)',
		'photon'      => 'Photon (OpenStreetMap)',
		'google_maps' => 'Google Maps',
		'bing_maps'   => 'Bing Maps'
	);

	/**
	 * Names of supported Themes AND Real Estate PLUGINS (lowercase)
	 *
	 * @var mixed[]
	 */
	private $supported_themes = array(
		'generic'              => array(
			'plain_name'   => 'generic',
			'display_name' => 'Generic',
			'class'        => 'Generic' // Theme Class Slug: generic
		),
		'wpcasa'               => array(
			'plain_name'   => 'wpcasa',
			'display_name' => 'wpCasa',
			'class'        => 'Wpcasa' // Theme Class Slug: wpcasa
		),
		'immobilia'            => array(
			'plain_name'   => 'immobilia',
			'display_name' => 'Immobilia',
			'alias'        => 'bo-immobilia',
			'class'        => 'Brings_Online' // Theme Class Slug: brings-online
		),
		'immomobil'            => array(
			'plain_name'   => 'immomobil',
			'display_name' => 'ImmoMobil',
			'alias'        => 'bo-immomobil',
			'class'        => 'Brings_Online' // Theme Class Slug: brings-online
		),
		'property'             => array(
			'plain_name'   => 'property',
			'display_name' => 'Property',
			'alias'        => 'bo property',
			'class'        => 'Brings_Online' // Theme Class Slug: brings-online
		),
		'bo-beladomo'          => array(
			'plain_name'   => 'bo-beladomo',
			'display_name' => 'BO-Beladomo',
			'class'        => 'BO_Beladomo' // Theme Class Slug: bo-beladomo
		),
		'bo-beladomo20'        => array(
			'plain_name'   => 'bo-beladomo20',
			'display_name' => 'BO-Beladomo20',
			'class'        => 'BO_Beladomo20' // Theme Class Slug: bo-beladomo20
		),
		'bo-immobilia18'       => array(
			'plain_name'   => 'bo-immobilia18',
			'display_name' => 'BO-Immobilia18',
			'class'        => 'BO_Immobilia18' // Theme Class Slug: bo-immobilia18
		),
		'bo-home'              => array(
			'plain_name'   => 'bo-home',
			'display_name' => 'BO HOME',
			'class'        => 'BO_HOME' // Theme Class Slug: bo-home
		),
		'realia'               => array(
			'plain_name'   => 'realia',
			'display_name' => 'Realia',
			'class'        => 'Realia', // Theme Class Slug: realia
			'max_version'  => '3.9'
		),
		'shandora'             => array(
			'plain_name'   => 'shandora',
			'display_name' => 'Shandora',
			'class'        => 'Shandora' // Theme Class Slug: shandora
		),
		'hometown'             => array(
			'plain_name'   => 'hometown',
			'display_name' => 'Hometown',
			'alias'        => 'hometown theme',
			'class'        => 'Hometown' // Theme Class Slug: hometown
		),
		'realhomes theme'      => array(
			'plain_name'   => 'realhomes theme',
			'display_name' => 'Real Homes',
			'alias'        => 'realhomes',
			'class'        => 'RealHomes' // Theme Class Slug: realhomes
		),
		'real places'          => array(
			'plain_name'   => 'real places',
			'display_name' => 'Real Places',
			'class'        => 'RealPlaces' // Theme Class Slug: realplaces
		),
		'wp residence'         => array(
			'plain_name'   => 'wp residence',
			'alias'        => 'wpresidence',
			'display_name' => 'WP Residence',
			'class'        => 'WP_Residence' // : wp-residence
		),
		'wp estate'            => array(
			'plain_name'   => 'wp estate',
			'alias'        => 'wpestate',
			'display_name' => 'WP Estate',
			'class'        => 'WP_Estate' // Theme Class Slug: wp-estate
		),
		'realty'               => array(
			'plain_name'   => 'realty',
			'display_name' => 'Realty',
			'class'        => 'Realty' // Theme Class Slug: realty
		),
		'freehold'             => array(
			'plain_name'   => 'freehold',
			'display_name' => 'Freehold',
			'class'        => 'Freehold' // Theme Class Slug: freehold
		),
		'freehold progression' => array(
			'plain_name'   => 'freehold progression',
			'display_name' => 'Freehold Progression',
			'class'        => 'Freehold_Progression' // Theme Class Slug: freehold-progression
		),
		'estate pro'           => array(
			'plain_name'   => 'estate pro',
			'display_name' => 'Estate Pro',
			'class'        => 'Estate_Pro' // Theme Class Slug: estate-pro
		),
		'realeswp'             => array(
			'plain_name'   => 'realeswp',
			'display_name' => 'Reales WP',
			'class'        => 'RealesWP' // Theme Class Slug: realeswp
		),
		'cozy'                 => array(
			'plain_name'   => 'cozy',
			'display_name' => 'Cozy',
			'class'        => 'Cozy' // Theme Class Slug: cozy
		),
		'zoner'                => array(
			'plain_name'   => 'zoner',
			'display_name' => 'Zoner',
			'class'        => 'Zoner' // Theme Class Slug: zoner
		),
		'houzez'               => array(
			'plain_name'   => 'houzez',
			'display_name' => 'Houzez',
			'class'        => 'Houzez' // Theme Class Slug: houzez
		),
		'myhome'               => array(
			'plain_name'   => 'myhome',
			'display_name' => 'MyHome',
			'class'        => 'MyHome' // Theme Class Slug: myhome
		),
		'wp-listings'          => array(
			'plain_name'       => 'wp-listings',
			'display_name'     => 'WP Listings',
			'class'            => 'WP_listings', // Plugin Class Slug: wp-listings
			'type'             => 'plugin',
			'main_plugin_file' => 'wp-listings/plugin.php'
		),
		'wpcasa_plugin'        => array(
			'plain_name'       => 'wpcasa_plugin',
			'display_name'     => 'WPCasa (Plugin)',
			'class'            => 'WPCasa_Plugin', // Plugin Class Slug: wpcasa_plugin
			'type'             => 'plugin',
			'main_plugin_file' => 'wpcasa/wpcasa.php'
		),
		'realia_plugin'        => array(
			'plain_name'       => 'realia_plugin',
			'display_name'     => 'Realia (Plugin)',
			'class'            => 'Realia_Plugin', // Plugin Class Slug: realia_plugin
			'type'             => 'plugin',
			'main_plugin_file' => 'realia/realia.php'
		),
		'inventor-properties'  => array(
			'plain_name'       => 'inventor',
			'display_name'     => 'Inventor Properties',
			'class'            => 'Inventor_Properties', // Plugin Class Slug: inventor-properties
			'type'             => 'plugin',
			'main_plugin_file' => 'inventor-properties/inventor-properties.php'
		),
		'kickstart'            => array(
			'plain_name'       => 'kickstart',
			'display_name'     => 'immonex Kickstart',
			'class'            => 'Kickstart', // Plugin Class Slug: kickstart
			'type'             => 'plugin',
			'main_plugin_file' => 'immonex-kickstart/immonex-kickstart.php'
		)
	);

	/**
	 * Theme Import Instance
	 *
	 * @var Object|bool
	 */
	private $theme = false;

	/**
	 * Compatibility Flags
	 *
	 * @var mixed[]
	 */
	private $compat_flags = array(
		'status_as_file'                            => false, // < 4.1
		'full_xml_before_import'                    => false, // < 4.1
		'wp_query_for_obid'                         => false, // < 4.1
		'detect_multiple_properties_with_same_obid' => false // < 4.1
	);

	/**
	 * Plugin Options
	 *
	 * @var mixed[]
	 */
	protected $plugin_options = array(
		'plugin_version'                                   => false,
		'previous_plugin_version'                          => false,
		'mapping_file'                                     => '',
		'import_log_recipient_email'                       => '',
		'debug_log_recipient_email'                        => '',
		'keep_archive_files_days'                          => 30,
		'enable_auto_import'                               => false,
		'include_global_subfolders'                        => false,
		'disable_full_imports'                             => false,
		'full_import_mode'                                 => 'delete_part_update_changed',
		'review_imported_properties'                       => 'none',
		'max_image_attachments_per_property'               => 0,
		'max_script_exec_time'                             => Process_Resources::DEFAULT_MAX_SCRIPT_EXEC_TIME,
		'max_script_run_property_cnt'                      => Process_Resources::DEFAULT_MAX_SCRIPT_RUN_PROPERTY_CNT,
		'max_script_run_deleted_properties_cnt'            => Process_Resources::MAX_SCRIPT_RUN_DELETED_PROPERTIES_CNT,
		'max_script_run_attachment_cnt'                    => Process_Resources::DEFAULT_MAX_SCRIPT_RUN_ATTACHMENT_CNT,
		'stall_check_time_minutes'                         => Process_Resources::STALL_CHECK_TIME_MINUTES,
		'default_geocoding_provider'                       => 'nominatim',
		'google_maps_api_key'                              => '',
		'bing_maps_api_key'                                => '',
		'geo_always_use_coordinates'                       => false,
		'unsupported_theme_notice_displayed'               => false,
		'deferred_admin_notices'                           => array(),
		'license_key'                                      => '',
		'license_date_created'                             => '',
		'license_renewal_url'                              => '',
		'license_status'                                   => '',
		'edd_license_key'                                  => '', // DEPRECATED
		'edd_license_status'                               => 'invalid', // DEPRECATED
		/**
		 * Theme/Plugin-specific options (prefix with the theme CLASS slug) - must be set here, NOT in the theme classes!
		 * PLEASE NOTE: Some default values are set in the plugin activation method due to translation reasons.
		 */
		// Generic
		'generic_property_post_type'                       => self::DEFAULT_PROPERTY_POST_TYPE,
		'generic_agent_post_type'                          => '',
		'generic_agent_post_id_cf'                         => '',
		'generic_agent_meta_name'                          => '',
		'generic_agent_meta_first_name'                    => '',
		'generic_agent_meta_last_name'                     => '',
		'generic_agent_meta_email'                         => '',
		// brings-online.com Themes
		'brings-online_every_property_on_start_page'       => true,
		'brings-online_show_properties_on_map'             => true,
		'brings-online_use_custom_value_clusters'          => array( 'size' ),
		'brings-online_delete_references'                  => false,
		'brings-online_days_new'                           => 14,
		'brings-online_new_label'                          => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'brings-online_add_description_content'            => '',
		'brings-online_gmap_html_template'                 => '<iframe
	width="{iframe_width}"
	height="{iframe_height}"
	frameborder="0"
	scrolling="no"
	marginheight="0"
	marginwidth="0"
	src="https://maps.google.com/maps?q={address}&amp;z=13&amp;ie=UTF8&amp;output=embed">
</iframe>',
		'brings-online_address_publishing_not_approved_message' => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		// BO-Beladomo (brings-online.com)
		'bo-beladomo_use_custom_value_clusters'            => array( 'size', 'price' ),
		'bo-beladomo_delete_references'                    => false,
		'bo-beladomo_days_new'                             => 14,
		'bo-beladomo_new_label'                            => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'bo-beladomo_add_description_content'              => '',
		'bo-beladomo_show_properties_on_map'               => true,
		'bo-beladomo_gmap_zoom'                            => 10,
		'bo-beladomo_gmap_height'                          => '410px',
		'bo-beladomo_show_agent'                           => true,
		'bo-beladomo_agent_box_headline'                   => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'bo-beladomo_show_contact_form'                    => true,
		'bo-beladomo_address_publishing_not_approved_message' => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		// BO-Beladomo20 (brings-online.com)
		'bo-beladomo20_use_custom_value_clusters'          => array( 'size', 'price' ),
		'bo-beladomo20_delete_references'                  => false,
		'bo-beladomo20_days_new'                           => 14,
		'bo-beladomo20_new_label'                          => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'bo-beladomo20_add_description_content'            => '',
		'bo-beladomo20_show_properties_on_map'             => true,
		'bo-beladomo20_map_position'                       => 'bottom',
		'bo-beladomo20_gmap_zoom'                          => 10,
		'bo-beladomo20_show_agent'                         => true,
		'bo-beladomo20_show_contact_form'                  => true,
		'bo-beladomo20_address_publishing_not_approved_message' => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		// BO-Immobilia18 (brings-online.com)
		'bo-immobilia18_use_custom_value_clusters'         => array( 'size', 'price' ),
		'bo-immobilia18_delete_references'                 => false,
		'bo-immobilia18_days_new'                          => 14,
		'bo-immobilia18_new_label'                         => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'bo-immobilia18_show_properties_on_map'            => true,
		'bo-immobilia18_add_properties_to_slider'          => true,
		'bo-immobilia18_gmap_zoom'                         => 10,
		'bo-immobilia18_show_agent'                        => true,
		'bo-immobilia18_agent_box_headline'                => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'bo-immobilia18_show_contact_form'                 => true,
		'bo-immobilia18_address_publishing_not_approved_message' => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'bo-immobilia18_add_description_content'           => '',
		// BO-HOME (brings-online.com)
		'bo-home_use_custom_value_clusters'                => array( 'size', 'price' ),
		'bo-home_delete_references'                        => false,
		'bo-home_days_new'                                 => 14,
		'bo-home_new_label'                                => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'bo-home_add_description_content'                  => '',
		'bo-home_show_properties_on_map'                   => true,
		'bo-home_gmap_zoom'                                => 16,
		'bo-home_show_agent'                               => true,
		'bo-home_address_publishing_not_approved_message'  => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		// Realia
		'realia_consider_agency_on_deletion'               => false, // DEPRECATED
		'realia_add_description_content'                   => '',
		// Shandora
		'shandora_add_description_content'                 => '',
		// Hometown
		'hometown_add_description_content'                 => '',
		'hometown_address_publishing_not_approved_message' => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		// Real Homes
		'realhomes_user_listing_quotas'                    => false,
		'realhomes_add_every_property_to_slider'           => true,
		'realhomes_add_page_banner_image'                  => false,
		'realhomes_gallery_slider_type'                    => '',
		'realhomes_hide_header_search_form'                => 0,
		'realhomes_default_title_nameless_floor_plans'     => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'realhomes_add_description_content'                => '',
		// Real Places
		'realplaces_add_every_property_to_slider'          => true,
		'realplaces_add_page_banner_image'                 => false,
		'realplaces_default_title_nameless_floor_plans'    => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'realplaces_add_description_content'               => '',
		// WP Residence
		'wp-residence_user_listing_quotas'                 => false,
		'wp-residence_add_every_property_to_slider'        => false,
		'wp-residence_add_description_content'             => '',
		'wp-residence_header_type'                         => 0,
		'wp-residence_sidebar_option'                      => 'global',
		'wp-residence_sidebar_select'                      => '',
		'wp-residence_google_map_zoom_level'               => 16,
		'wp-residence_google_street_view'                  => false,
		'wp-residence_google_street_view_camera_angle'     => 0,
		// WP Estate
		'wp-estate_user_listing_quotas'                    => false,
		'wp-estate_add_every_property_to_slider'           => false,
		'wp-estate_disable_full_imports'                   => false, // DEPRECATED
		'wp-estate_add_description_content'                => '',
		'wp-estate_sidebar_option'                         => 'global',
		'wp-estate_sidebar_select'                         => '',
		'wp-estate_google_map_zoom_level'                  => 16,
		'wp-estate_google_street_view'                     => false,
		'wp-estate_google_street_view_camera_angle'        => 0,
		// Realty
		'realty_user_listing_quotas'                       => false,
		'realty_add_description_content'                   => '',
		// Freehold
		'freehold_mark_every_property_as_featured'         => false,
		'freehold_mark_every_property_homepage'            => false,
		'freehold_add_description_content'                 => '',
		'freehold_add_description_groups'                  => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'freehold_youtube_embed_code'                      => '<iframe width="560" height="315" src="https://www.youtube.com/embed/{video_id}" frameborder="0" allowfullscreen></iframe>',
		'freehold_vimeo_embed_code'                        => '<iframe src="https://player.vimeo.com/video/{video_id}?color=ffffff&title=0&byline=0&portrait=0" width="500" height="281" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>',
		'freehold_custom_video_embed_code'                 => '',
		// Freehold Progression
		'freehold-progression_add_description_content'     => '',
		'freehold-progression_disable_agent_box'           => false,
		// Estate Pro
		'estate-pro_add_description_content'               => '',
		// Reales WP
		'realeswp_user_listing_quotas'                     => false,
		'realeswp_add_description_content'                 => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		// Cozy
		'cozy_description_sections'                        => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'cozy_add_description_content'                     => '',
		// Zoner
		'zoner_user_listing_quotas'                        => false,
		'zoner_add_description_content'                    => '',
		'zoner_allow_user_rating'                          => false,
		// Houzez
		'houzez_user_listing_quotas'                       => false,
		'houzez_enable_map'                                => true,
		'houzez_enable_street_view'                        => false,
		'houzez_add_every_property_to_slider'              => true,
		'houzez_add_description_content'                   => '',
		// MyHome
		'myhome_add_description_content'                   => '',
		// WP Listings
		'wp-listings_add_description_content'              => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'wp-listings_gallery_code'                         => '[gallery]',
		'wp-listings_map_embed_template'                   => '<iframe
	width="100%"
	height="300px"
	frameborder="0"
	scrolling="no"
	marginheight="0"
	marginwidth="0"
	style="overflow:hidden; width:100%; height:300px"
	src="http://maps.google.com/maps?q={address}&amp;z=13&amp;ie=UTF8&amp;output=embed">
</iframe>',
		'wp-listings_youtube_embed_code'                   => '<iframe width="560" height="315" src="https://www.youtube.com/embed/{video_id}" frameborder="0" allowfullscreen></iframe>',
		'wp-listings_vimeo_embed_code'                     => '<iframe src="https://player.vimeo.com/video/{video_id}?color=ffffff&title=0&byline=0&portrait=0" width="500" height="281" frameborder="0" webkitallowfullscreen mozallowfullscreen allowfullscreen></iframe>',
		'wp-listings_custom_video_embed_code'              => '',
		// WPCasa (Plugin)
		'wpcasa_plugin_add_description_content'            => '',
		'wpcasa_plugin_remark_not_exact_location'          => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		'wpcasa_plugin_header_display'                     => 'image_slider',
		'wpcasa_plugin_header_filter'                      => '',
		// Realia (Plugin)
		'realia_plugin_user_listing_quotas'                => false,
		'realia_plugin_consider_agency_on_deletion'        => false, // DEPRECATED
		'realia_plugin_add_description_content'            => 'INSERT_TRANSLATED_DEFAULT_VALUE',
		// Inventor Properties
		'inventor-properties_user_listing_quotas'          => false,
		'inventor-properties_listing_banner'               => 'banner_featured_image',
		'inventor-properties_listing_banner_map_zoom'      => '12',
		'inventor-properties_listing_banner_map_type'      => 'ROADMAP',
		'inventor-properties_listing_banner_map_marker'    => true,
		'inventor-properties_enable_street_view'           => false,
		'inventor-properties_enable_inside_view'           => false,
		'inventor-properties_add_description_content'      => '',
		// immonex Kickstart
		'kickstart_disable_reference_deletion'             => true,
		'kickstart_enable_auto_contacts'                   => true,
		'kickstart_add_description_content'                => '',
		'kickstart_save_regional_addition_as'              => 'location_child'
	);

	/**
	 * Log Instance
	 *
	 * @var \inveris_Simple_Logger
	 */
	public $log = false;

	/**
	 * Here we go!
	 *
	 * @since 1.0
	 *
	 * @param string $plugin_slug Plugin name slug.
	 */
	public function __construct( $plugin_slug ) {
		// Option name for the "killswitch".
		$this->killswitch_option_name = "{$plugin_slug}_killswitch";

		parent::__construct( $plugin_slug, self::TEXTDOMAIN );

		// Directory/URL related objects.
		$this->import_folders = new Import_Folders( $this->bootstrap_data, $this->utils );
		$this->archive_folder = new Archive_Folder( $this->bootstrap_data, $this->utils );
		$this->mapping_folders = new Mapping_Folders( $this->bootstrap_data, $this->utils );

		$this->process_resources = new Process_Resources( $this->bootstrap_data, $this->utils );
		$this->legacy = new Legacy( $this->bootstrap_data, $this->utils );
		$this->security = new Security( $this->bootstrap_data, $this->utils );
		$this->property_time = new Property_Time( $this->bootstrap_data, $this->utils );
		$this->temp_options = new Temporary_Options( $this->bootstrap_data, $this->utils );

		$this->import_language = new Import_Language( $this->bootstrap_data, $this->utils );
		$this->taxonomy_utils = new Taxonomy_Utils( $this->bootstrap_data, $this->utils );

		// Set the (default) custom post type for properties.
		$this->set_property_post_type( self::DEFAULT_PROPERTY_POST_TYPE );

		// Set up custom backend menus etc.
		new WP_Bootstrap( $this->bootstrap_data, $this );

		$this->licensing = new \immonex\WordPressPluginLicensing\V1_2_3\Licensing( $this, __FILE__ );
	} // __construct

	/**
	 * Return option or special values.
	 *
	 * @since 1.0
	 *
	 * @param string $option_key Option key.
	 *
	 * @return mixed Option value or false if not existent.
	 */
	public function __get( $option_key ) {
		switch ( $option_key ) {
			case 'log' :
				return $this->log;
			case 'template_folders':
				return $this->template_folders;
			case 'import_dir' :
				return apply_filters( "{$this->plugin_prefix}working_dir", '' );
			case 'archive_dir' :
				return apply_filters( "{$this->plugin_prefix}archive_dir", '' );
			case 'property_post_type' :
				return $this->property_post_type;
			case 'theme_class_slug' :
				return $this->theme ? $this->theme->theme_class_slug : false;
			case 'override_widget_theme_name' :
				return $this->theme ? $this->theme->override_widget_theme_name : false;
			case 'enable_multilang' :
				return apply_filters( "{$this->plugin_prefix}enable_multilang", true );
			case 'current_import_language' :
				return apply_filters( "{$this->plugin_prefix}current_import_language", '' );
			case 'import_basedir' :
				return apply_filters( "{$this->plugin_prefix}working_dir", '' );
			case 'import_url' :
				return apply_filters( "{$this->plugin_prefix}working_url", '' );
			case 'user_import_dir_name' :
				return apply_filters( "{$this->plugin_prefix}user_import_base_folder_name", '' );
			case 'current_import_status' :
				return $this->_get_current_import_status();
			case 'current_import_folder' :
				return apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $this->current_import_zip_file );
			case 'valid_attachment_image_file_formats' :
				return apply_filters( "{$this->plugin_prefix}image_file_formats", array() );
			case 'valid_attachment_video_file_formats' :
				return apply_filters( "{$this->plugin_prefix}video_file_formats", array() );
			case 'valid_attachment_misc_file_formats' :
				return apply_filters( "{$this->plugin_prefix}misc_file_formats", array() );
			case 'valid_attachment_file_formats' :
				return apply_filters( "{$this->plugin_prefix}valid_attachment_file_formats", array() );
			case 'theme_names' :
				$theme = wp_get_theme();
				return array(
					'theme_name' => $this->get_plain_theme_name( $theme->name ),
					'parent_theme_name' => $this->get_plain_theme_name( $theme->parent_theme )
				);
			case 'current_post_id' :
				return apply_filters( "{$this->plugin_prefix}current_post_id", $this->current_post_id ? $this->current_post_id : get_the_ID() );
			case 'current_openimmo_anid' :
				return $this->current_openimmo_anid;
			case 'pending_import_zip_files' :
				return apply_filters( "{$this->plugin_prefix}import_zip_files", array(), false, 'paths' );
			case 'current_import_zip_file' :
				return $this->current_import_zip_file;
			case 'current_import_xml_file' :
				return $this->current_import_xml_file;
			case 'current_mapping_file' :
				return $this->current_mapping_file;
			case 'geocoding_providers' :
				return $this->geocoding_providers;
			case 'force_slug_language_tags' :
				return apply_filters( "{$this->plugin_prefix}force_slug_language_tags", false );
			case 'mapping_folders' :
				return apply_filters( "{$this->plugin_prefix}mapping_folders", [] );
			case 'backend_dialog_texts' :
				return $this->_get_option_page_status_contents( 'dialog_texts' );
			case 'spinner_image_url' :
				return $this->_get_option_page_status_contents( 'spinner_image_url' );
			case 'spinner_image' :
				return $this->_get_option_page_status_contents( 'spinner_image' );
		}

		return parent::__get( $option_key );
	} // __get

	/**
	 * Set the property custom post type.
	 *
	 * @since 1.9
	 *
	 * @param string $post_type_name Custom post type name.
	 */
	public function set_property_post_type( $post_type_name ) {
		$this->property_post_type = $post_type_name;
	} // set_property_post_type

	/**
	 * Perform core initialization tasks.
	 *
	 * @since 1.0
	 */
	public function init_base() {
		parent::init_base();

		/**
		 * Include additional admin functions for image manipulation and file/media management.
		 */
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		if ( ! function_exists( 'wp_terms_checklist' ) ) {
			include ABSPATH . 'wp-admin/includes/template.php';
		}

		add_action( 'init', array( $this, 'run_immonex_cron' ), 200 );

		if ( is_admin() ) {
			add_action( 'admin_init', array( $this, 'process' ), 200 );
		}

		/**
		 * Add filter hooks for retrieving/modifying allowed property
		 * attachment file formats.
		 */
		add_filter( "{$this->plugin_prefix}image_file_formats", array( 'immonex\OpenImmo2Wp\Attachment_Utils', 'get_valid_image_file_formats' ) );
		add_filter( "{$this->plugin_prefix}video_file_formats", array( 'immonex\OpenImmo2Wp\Attachment_Utils', 'get_valid_video_file_formats' ) );
		add_filter( "{$this->plugin_prefix}misc_file_formats", array( 'immonex\OpenImmo2Wp\Attachment_Utils', 'get_valid_misc_file_formats' ) );
		add_filter( "{$this->plugin_prefix}valid_attachment_file_formats", array( 'immonex\OpenImmo2Wp\Attachment_Utils', 'get_valid_file_formats' ) );
	} // init_base

	/**
	 * Initialize the plugin (common).
	 *
	 * @since 1.0
	 *
	 * @param bool $fire_before_hook Flag to indicate if an action hook should fire
	 *                               before the actual method execution (optional,
	 *                               true by default).
	 * @param bool $fire_after_hook  Flag to indicate if an action hook should fire
	 *                               after the actual method execution (optional,
	 *                               true by default).
	 */
	public function init_plugin( $fire_before_hook = true, $fire_after_hook = true ) {
		parent::init_plugin( true, false );

		// Make compatibility flags modifiable by filter functions.
		$this->compat_flags = apply_filters( $this->plugin_prefix . 'compat_flags', $this->compat_flags );

		if ( 1 === (int) $this->plugin_options['review_imported_properties'] ) {
			$this->plugin_options['review_imported_properties'] = 'all';
		} elseif ( empty( $this->plugin_options['review_imported_properties'] ) ) {
			$this->plugin_options['review_imported_properties'] = 'none';
		}

		$this->killswitch = $this->get_killswitch();

		// Include the logger class for logging events during the import process.
		require_once( trailingslashit( $this->plugin_dir ) . 'lib/inveris-simple-logger.php' );

		// Include a utility class for retrieving/converting country ISO codes.
		require_once( trailingslashit( $this->plugin_dir ) . 'lib/inveris-iso-countries/inveris-iso-countries.php' );

		// Plugin template directory.
		$plugin_template_dir = trailingslashit( $this->plugin_dir ) . 'templates';

		/**
		 * Check if a custom plugin template directory (name: plugin_slug/templates) exists in
		 * the (child) theme folder.
		 */
		if ( is_child_theme() )
			$theme_dir = get_stylesheet_directory();
		else
			$theme_dir = get_template_directory();

		if ( file_exists( trailingslashit( $theme_dir ) . trailingslashit( $this->plugin_slug ) . 'templates' ) )
			$custom_template_dir = trailingslashit( $theme_dir ) . trailingslashit( $this->plugin_slug ) . 'templates';
		else
			$custom_template_dir = false;

		// Generate the list of available template directories (modifiable by filter function).
		$template_folders = array();
		if ( $custom_template_dir ) $template_folders[] = $custom_template_dir;
		$template_folders[] = $plugin_template_dir;
		$this->template_folders = apply_filters( $this->plugin_prefix . 'template_folders', $template_folders );

		$working_dir = apply_filters( "{$this->plugin_prefix}working_dir", '' );

		if ( file_exists( $working_dir ) ) {
			try {
				// Initialize the logger if the plugin has been activated already.
				$this->log = new \inveris_Simple_Logger( trailingslashit( $working_dir ) . 'current_log' );

				// Logger shall use local time instead of UTC.
				$this->log->set_timezone( get_option( 'timezone_string' ) );
			} catch ( \Exception $e ) {
				$this->add_admin_notice( wp_sprintf( __( "Error on initializing the logger - import is not possible (%s)", 'immonex-openimmo2wp' ), $e->getMessage() ), 'error' );
			}
		}

		if ( $this->compat_flags['status_as_file'] ) {
			// Set name for current status file (data for resuming imports).
			$this->current_status_file = trailingslashit( $working_dir ) . 'current';
		} else {
			// Set name for current status temporary option.
			$this->current_status_option = $this->plugin_slug . '_current_status';
		}

		// Add 2 minute WP-Cron interval.
		add_filter( 'cron_schedules', array( $this, 'add_wp_cron_intervals' ) );

		add_action( 'wp_ajax_perform_import', array( $this, 'ajax_import' ) );
		add_action( 'wp_ajax_get_current_import_status', array( $this, 'ajax_status' ) );
		add_action( 'before_delete_post', array( $this, 'delete_post_media' ), 5 );

		$is_admin = current_user_can( 'activate_plugins' );
		$is_admin_debug = $is_admin && $this->is_debug();

		if (
			! isset( $_GET['immonex_cron'] ) &&
			! $is_admin_debug &&
			! wp_get_schedule( $this->plugin_prefix . 'do_frequently' )
		) {
			// Schedule automatic import via WP-Cron.
			wp_schedule_event( time(), '2minutes', $this->plugin_prefix . 'do_frequently' );
		}

		if ( $is_admin_debug ) {
			// No auto-imports during admin backend sessions.
			wp_clear_scheduled_hook( $this->plugin_prefix . 'do_frequently' );
		} else {
			// Add additional WP-Cron based method for processing frequent tasks.
			if ( ! $is_admin_debug ) add_action( $this->plugin_prefix . 'do_frequently', array( $this, 'do_frequently' ), 100 );
		}

		// Maybe set property featured image.
		add_action( $this->plugin_prefix . 'attachment_added', array( $this, 'maybe_set_property_featured_image' ), 10, 4 );

		/**
		 * Initialize the theme support.
		 */
		$supported_theme = $this->_check_theme();

		if ( ! $supported_theme && isset( $this->supported_themes['generic'] ) ) {
			$supported_theme = $this->supported_themes['generic'];
		}

		if ( $supported_theme ) {
			$class_slug = str_replace( '_', '-', $this->string_utils::slugify( $supported_theme['class'] ) );
			$class_name = '\immonex\OpenImmo2Wp\themes\\' . $supported_theme['class'];

			if ( class_exists( $class_name ) ) {
				$this->theme = new $class_name( $this, $supported_theme );

				if (
					! $this->theme->override_widget_theme_name &&
					! empty( $supported_theme['alias'] )
				) {
					// Always override the widget theme name (prefix) by default if a theme alias exists.
					$this->theme->override_widget_theme_name = str_replace( ' ', '_', $supported_theme['plain_name'] );
				}

				if ( ! $this->plugin_options['mapping_file'] ) {
					$this->plugin_options['mapping_file'] = basename( $this->mapping_folders->get_current_file( "{$this->theme->theme_class_slug}.csv" ) );
					if ( ! $this->plugin_options['mapping_file'] ) {
						$this->plugin_options['mapping_file'] = basename( $this->mapping_folders->get_current_file( 'generic.csv' ) );
					}
				}
			}
		}

		// Fetch mapping definition files.
		$this->mapping_files = apply_filters(
			"{$this->plugin_prefix}mapping_files",
			array(),
			'wpcasa' === $this->theme_class_slug ? 'wpcasa.csv' : ''
		);

		if ( ! $this->plugin_options['mapping_file'] && ! empty( $this->mapping_files ) ) {
			$this->plugin_options['mapping_file'] = reset( $this->mapping_files );
		}

		add_action( 'after_switch_theme', array( $this, 'check_for_supported_theme' ) );

		if ( $this->enable_multilang ) {
			// Reload plugin/theme configuration when the current language is switched.
			add_action( "{$this->plugin_prefix}set_current_import_language", array( $this, 'reload_plugin_options' ), 15 );

			// Strip language tags from term names.
			add_filter( 'get_term', array( $this, 'strip_language_tags' ), 20, 2 );
		}

		// Get current post ID from query.
		add_action( 'parse_query', array( $this, 'get_post_id_from_query' ) );

		add_shortcode( 'immonex_widget', array( $this, 'shortcode_immonex_widget' ) );

		/**
		 * Add data to the global registry (work in progress).
		 */

		$registry = array(
			'plugin' => $this,
			'plugin_prefix' => $this->plugin_prefix,
			'mapping_file' => $this->plugin_options['mapping_file'],
			'mapping_backups_max_age_months' => self::MAPPING_BACKUPS_MAX_AGE_MONTHS,
			'archive_files_max_age_days' => $this->plugin_options['keep_archive_files_days'],
			'include_global_subfolders' => $this->plugin_options['include_global_subfolders'],
			'max_script_exec_time' => $this->plugin_options['max_script_exec_time'],
			'max_script_run_deleted_properties_cnt' => $this->plugin_options['max_script_run_deleted_properties_cnt'],
			'log' => $this->log,
			'wp_filesystem' => $this->wp_filesystem
		);
		foreach ( $registry as $key => $data ) {
			Registry::set( $key, $data );
		}

		foreach ( $this->utils as $key => $util_instance ) {
			Registry::set( "{$key}_utils", $util_instance );
		}

		do_action( 'immonex_core_after_init', $this->plugin_slug );
	} // init_plugin

	/**
	 * Initialize the plugin (admin/backend only).
	 *
	 * @since 1.0
	 *
	 * @param bool $fire_before_hook Flag to indicate if an action hook should fire
	 *                               before the actual method execution (optional,
	 *                               true by default).
	 * @param bool $fire_after_hook  Flag to indicate if an action hook should fire
	 *                               after the actual method execution (optional,
	 *                               true by default).
	 */
	public function init_plugin_admin( $fire_before_hook = true, $fire_after_hook = true ) {
		parent::init_plugin_admin( true, false );

		if (
			! empty( $_GET['uso'] ) &&
			in_array( $_GET['uso'], array( 'enable_killswitch', 'disable_killswitch' ) )
		) {
			if ( 'enable_killswitch' === $_GET['uso'] ) {
				$this->killswitch = strtotime( '+ ' . SELF::KILLSWITCH_DURATION_MINUTES . ' minutes', current_time( 'timestamp' ) );
				update_option( $this->killswitch_option_name, $this->killswitch, false );
			} else {
				$this->killswitch = false;
				delete_option( $this->killswitch_option_name );
			}
			wp_cache_flush();
		}

		if ( $this->killswitch ) {
			add_action( $this->plugin_slug . '_option_page_extended_infos', array( $this, 'display_killswitch_info' ) );
		}

		do_action( 'immonex_core_after_init_admin', $this->plugin_slug );
	} // init_plugin_admin

	/**
	 * Enqueue and localize backend JavaScript and CSS code (callback).
	 *
	 * @since 5.0.0
	 *
	 * @param string $hook_suffix The current admin page.
	 */
	public function admin_scripts_and_styles( $hook_suffix ) {
		parent::admin_scripts_and_styles( $hook_suffix );

		$status_contents = $this->_get_option_page_status_contents();

		wp_localize_script(
			$this->backend_js_handle,
			self::PLUGIN_PREFIX . 'manual_import',
			array(
				'license_active' => $this->engage,
				'dialog_texts' => $status_contents['dialog_texts'],
				'spinner_image_url' => $status_contents['spinner_image_url'],
				'spinner_image' => $status_contents['spinner_image']
			)
		);
	} // admin_scripts_and_styles

	/**
	 * Display info on the plugin options page if the "Killswitch" is active
	 * (action hook callback).
	 *
	 * @since 5.0.0
	 */
	public function display_killswitch_info() {
		echo wp_sprintf(
			'<div style="margin-bottom:16px; padding: 8px; color:#FFF; background-color:#E00; font-size:14px; font-weight:bold">%s (%s)</div>',
			__( 'KILLSWITCH ACTIVE!', 'immonex-openimmo2wp' ),
			date_i18n( 'H:i:s', $this->killswitch )
		);
	} // display_killswitch_info

	/**
	 * Load and register widgets.
	 *
	 * @since 1.0
	 */
	public function init_plugin_widgets() {
		 // Widget for displaying user-defined properties.
		register_widget( __NAMESPACE__ . '\Widgets\immonex_User_Defined_Properties_Widget' );

		// Widget for displaying property attachments.
		register_widget( __NAMESPACE__ . '\Widgets\immonex_Property_Attachments_Widget' );
	} // init_plugin_widgets

	/**
	 * Save the current post/page ID via action hook to avoid possible
	 * problems, e. g. when widgets are being embedded outside the loop.
	 *
	 * @since 1.7
	 */
	public function get_post_id_from_query() {
		global $wp_query;

		if ( isset( $wp_query->queried_object->ID ) ) $this->current_post_id = $wp_query->queried_object->ID;
	} // get_post_id_from_query

	/**
	 * Add a dismissable admin notice if no supported theme is active.
	 *
	 * @since 1.1.4 beta
	 */
	public function check_for_supported_theme() {
		if ( $this->theme ) {
			$supported_theme_active = true;
		} else {
			$supported_theme_active = false !== $this->_check_theme() ? true : false;
		}

		if ( ! $supported_theme_active ) {
			// No supported theme (parent/child) or plugin active: add a dismissable admin notice.
			$supported_theme_names = array();
			$supported_plugin_names = array();
			foreach ( $this->supported_themes as $supported_theme => $theme_properties ) {
				if ( 'generic' === $supported_theme ) {
					continue;
				}

				if ( ! isset( $theme_properties['type'] ) || 'theme' === $theme_properties['type'] ) {
					$supported_theme_names[] = $theme_properties['display_name'];
				} elseif ( 'plugin' === $theme_properties['type'] ) {
					$supported_plugin_names[] = $theme_properties['display_name'];
				}
			}
			$theme_names = implode( ', ', $supported_theme_names );
			$plugin_names = implode( ', ', $supported_plugin_names );
			if ( ! apply_filters( $this->plugin_prefix . 'suppress_deferred_admin_notices', false ) ) {
				$this->add_deferred_admin_notice( wp_sprintf( __( '%s is in use in combination with a <strong>not officially supported real estate theme or plugin</strong> (supported <strong>themes</strong>: %s; supported <strong>plugins</strong>: %s).', 'immonex-openimmo2wp' ), self::PLUGIN_NAME, $theme_names, $plugin_names ), 'warning' );
			}
		}
	} // check_for_supported_theme

	/**
	 * Generate widget output for shortcode based inclusion.
	 *
	 * @since 1.0
	 *
	 * @param array $atts Shortcode attributes.
	 *
	 * @return string Widget output.
	 */
	public function shortcode_immonex_widget( $atts ) {
		global $wp_widget_factory;

		$widget_class = isset( $atts[ 'name' ] ) ? __NAMESPACE__ . '\\Widgets\\' . esc_html( $atts[ 'name' ] ) : false;
		if ( ! $widget_class ) return '<p>' . __( 'The widget (class) name is missing.', 'immonex-openimmo2wp' ) . '</p>';
		if ( ! is_a( $wp_widget_factory->widgets[$widget_class], 'WP_Widget' ) ) {
			return '<p>' . wp_sprintf( __( '%s: Widget class not found. Make sure that this widget exists and the class name is correct.', 'immonex-openimmo2wp' ), '<strong>' . $widget_class . '</strong>') . '</p>';
		}

		$args = array(
			'widget_id' => 'arbitrary-instance-' . uniqid(),
			'is_shortcode_output' => true
		);
		if ( isset( $atts['before_widget'] ) ) $args['before_widget'] = $atts['before_widget'];
		if ( isset( $atts['after_widget'] ) ) $args['after_widget'] = $atts['after_widget'];
		if ( isset( $atts['before_title'] ) ) $args['before_title'] = $atts['before_title'];
		if ( isset( $atts['after_title'] ) ) $args['after_title'] = $atts['after_title'];

		ob_start();
		the_widget( $widget_class, $atts, $args );
		$widget_output = ob_get_contents();
		ob_end_clean();

		return $widget_output;
	} // shortcode_immonex_widget

	/**
	 * Perform activation tasks for a single site: Create directories, copy mapping files
	 * and schedule a frequent import event on plugin activation.
	 *
	 * @since 1.9.13
	 *
	 * @param bool $fire_before_hook Flag to indicate if an action hook should fire
	 *                               before the actual method execution (optional,
	 *                               true by default).
	 * @param bool $fire_after_hook  Flag to indicate if an action hook should fire
	 *                               after the actual method execution (optional,
	 *                               true by default).
	 */
	protected function activate_plugin_single_site( $fire_before_hook = true, $fire_after_hook = true ) {
		parent::activate_plugin_single_site( true, false );

		$this->legacy->trim_obids();

		$install = new Install( $this->bootstrap_data, $this->utils );

		$install->insert_translated_default_option_values( $this->plugin_options );
		$install->maybe_update_max_exec_time( $this->plugin_options );

		$result = $install->create_folders();
		if ( ! $result['success'] ) {
			$this->br_trigger_error( $result['error_msg'], E_USER_ERROR );
		}

		$result = $install->copy_mapping_files( $this->plugin_options );
		if ( ! $result['success'] ) {
			$this->br_trigger_error( $result['error_msg'], E_USER_ERROR );
		}
		if ( $result['mapping_option_value'] ) {
			$this->plugin_options['mapping_file'] = $result['mapping_option_value'];
		}
		if ( $result['notice'] ) {
			/**
			 * Add a "deferred" admin notice if a backed up file matches the currently selected
			 * mapping table (will be displayed once after the redirect).
			 */
			$this->add_deferred_admin_notice( self::PLUGIN_NAME . ": {$result['notice']}", 'info' );
		}

		// Unschedule all previously set import events.
		if ( wp_get_schedule( $this->plugin_prefix . 'do_frequently' ) ) {
			wp_clear_scheduled_hook( $this->plugin_prefix . 'do_frequently' );
		}

		// The next one will be re-scheduled in init because of the "random schedule
		// vanishing effect".
		wp_schedule_event( time(), '2minutes', $this->plugin_prefix . 'do_frequently' );

		if ( ! $this->plugin_options['unsupported_theme_notice_displayed'] ) {
			// Check if a supported theme is active ONCE.
			$this->check_for_supported_theme();
			$this->plugin_options['unsupported_theme_notice_displayed'] = true;
		}

		// Transfer deprecated EDD license information to new option elements once, if required.
		$this->plugin_options = $this->licensing->maybe_transfer_license_data( $this->plugin_options );

		update_option( $this->plugin_options_name, $this->plugin_options );

		do_action( 'immonex_core_after_activation', $this->plugin_slug );
	} // activate_plugin_single_site

	/**
	 * Unschedule frequent import on plugin deactivation.
	 *
	 * @since 1.0
	 */
	public function deactivate_plugin() {
		parent::deactivate_plugin();

		wp_clear_scheduled_hook( $this->plugin_prefix . 'do_frequently' );
	} // deactivate_plugin

	/**
	 * Add WP-Cron interval(s).
	 *
	 * @since 1.0
	 */
	public function add_wp_cron_intervals( $schedules ) {
		$schedules['2minutes'] = array(
			'interval' => 120, // seconds
			'display' => __( 'Every 2 Minutes', 'immonex-openimmo2wp' )
		);

		return $schedules;
	} // add_wp_cron_intervals

	/**
	 * Reload the plugin/theme options on language switches during runtime
	 * (action callback).
	 *
	 * @since 1.5
	 *
	 * @param string $language ISO2 code of new language.
	 *
	 * @return string Unchanged language code.
	 */
	public function reload_plugin_options( $language ) {
		if ( $this->theme ) {
			$option_cache_deleted = wp_cache_flush();
			$this->plugin_options = $this->fetch_plugin_options( apply_filters( "{$this->plugin_prefix}default_plugin_options", $this->plugin_options ) );
			$this->theme->set_theme_options();
		}
	} // reload_plugin_options

	/**
	 * If enabled, perform processing of import files frequently.
	 *
	 * @since 1.0
	 */
	public function do_frequently() {
		$this->is_wpcron_process = true;

		if ( ! $this->current_import_zip_file ) {
			$this->security->delete_unallowed_files_from_import_folders();
		}

		if (
			$this->plugin_options['enable_auto_import'] &&
			apply_filters( "{$this->plugin_prefix}import_zip_files", 0, false, 'count' ) > 0
		) {
			$this->process( 'import' );
		}
	} // do_frequently

	/**
	 * Resume processing of import files.
	 *
	 * @since 1.0
	 */
	public function run_immonex_cron() {
		if ( ! isset( $_GET['immonex_cron'] ) ) return;

		$this->is_immonex_cron_process = true;

		wp_clear_scheduled_hook( $this->plugin_prefix . 'do_frequently' );

		if (
			( isset( $_GET['action'] ) && 'import' === $_GET['action'] ) &&
			apply_filters( "{$this->plugin_prefix}import_zip_files", 0, false, 'count' ) > 0
		) {
			$this->log->add( __( 'Script run restarted by immonex Cron.', 'immonex-openimmo2wp' ), 'debug' );
			$this->process( 'import' );
		}
	} // run_immonex_cron

	/**
	 * Perform property image related tasks when an attachment is added (callback).
	 *
	 * @since 1.0
	 *
	 * @param string|int $att_id Attachment ID.
	 */
	public function add_property_attachment( $att_id ) {
		// Mark attachment as imported image.
		add_post_meta( $att_id, '_is_immonex_import_attachment', '1', true );
		if ( $this->enable_multilang ) {
			// Set attachment language.
			do_action( 'immonex_oi2wp_multilang_set_attachment_language', $att_id, false, $this->current_import_language );
		}
	} // add_property_attachment

	/**
	 * Maybe set the featured image (thumbnail) of the property.
	 *
	 * @since 1.0
	 *
	 * @param string|int $att_id Attachment ID.
	 * @param string[] $valid_image_file_formats Valid image file format extensions.
	 * @param string[] $valid_misc_file_formats Valid miscellaneous file format extensions.
	 * @param string[] $valid_video_file_formats Valid video file format extensions.
	 */
	public function maybe_set_property_featured_image( $att_id, $valid_image_file_formats, $valid_misc_file_formats, $valid_video_file_formats ) {
		$p = get_post( $att_id );
		if ( ! $p ) {
			return;
		}

		$thumbnail_id = get_post_meta( $p->post_parent, '_thumbnail_id', true );
		$org_path_or_url = get_post_meta( $att_id, '_immonex_import_attachment_org_path_or_url', true );
		$set_featured_image = false;

		if (
			isset( $this->current_property_main_image['path_or_url'] ) &&
			$org_path_or_url === $this->current_property_main_image['path_or_url']
		) {
			$set_featured_image = true;
		} elseif ( ! $thumbnail_id || $thumbnail_id <= 0 ) { // Special condition due to strange behaviours where thumbnail ID -3 is returned...
			// Property has no thumbnail yet, let's see if we can add one.
			$mime = $this->string_utils->get_mime_type_parts( get_post_mime_type( $att_id ) );
			$fileinfo = pathinfo( get_attached_file( $att_id ) );

			if (
				! empty( $mime ) &&
				in_array( strtoupper( $mime['subtype'] ), $this->valid_attachment_image_file_formats ) ||
				(
					isset( $fileinfo['extension'] ) &&
					in_array( strtoupper( $fileinfo['extension'] ), $this->valid_attachment_image_file_formats )
				)
			) {
				$set_featured_image = true;
			}
		}

		if ( $set_featured_image ) {
			// If it has a valid format, set the first OR main/title property image as featured post image.
			update_post_meta( $p->post_parent, '_thumbnail_id', $att_id );
			do_action( $this->plugin_prefix . 'featured_post_image_added', $att_id );
		}
	} // maybe_set_property_featured_image

	/**
	 * Delete associated images upon property post deletion (callback).
	 *
	 * @since 1.0
	 *
	 * @param int $post_id Property Post ID.
	 */
	public function delete_post_media( $post_id ) {
		if ( isset( $post_id ) && $post_id > 0 && ! is_array( $post_id ) ) {
			$args = array(
				'post_type' => 'attachment',
				'posts_per_page' => -1,
				'post_status' => 'any',
				'post_parent' => $post_id
			);
			if ( $this->enable_multilang ) $args['lang'] = '';

			$args = apply_filters( $this->plugin_prefix . 'delete_property_media_args', $args, $post_id );

			if ( false === $args ) {
				$this->log->add( __( 'Deletion of property attachments prevented by a filter function.', 'immonex-openimmo2wp' ), 'debug' );
				return;
			}

			$attachments = get_posts( $args );

			foreach ( $attachments as $attachment ) {
				if ( false === wp_delete_attachment( $attachment->ID, true ) ) {
					$this->log->add( wp_sprintf( __( 'Error on deleting an image attachment: Attachment ID %s', 'immonex-openimmo2wp' ), $attachment->ID ), 'error' );
				}
			}
		}
	} // delete_post_media

	/**
	 * Invoke import process by AJAX call.
	 *
	 * @since 1.0
	 */
	public function ajax_import() {
		$this->process( 'import' );

		if ( $this->import_process_running_error ) {
			// Another import process is already running, ask user if the resumption
			// should be forced.
			echo json_encode( array(
				'status' => 'error',
				'import_process_already_running' => 1
			) );
		} elseif ( count( $this->processing_errors ) > 0 ) {
			// Errors during import: return error data.
			echo json_encode( array(
				'status' => 'error',
				'messages' => $this->processing_errors,
				'import_process_already_running' => 0
			) );
		} else {
			// Return remaining pending files (if any) after import is completed.
			$pending_files = apply_filters( "{$this->plugin_prefix}import_zip_files", array(), false, 'status' );

			echo json_encode( array(
				'status' => 'completed',
				'pending' => $pending_files,
				'cnt_pending_files' => count( $pending_files )
			) );
		}

		exit;
	} // ajax_import

	/**
	 * Send current import status data as JSON.
	 *
	 * @since 1.0
	 */
	public function ajax_status() {
		$status = $this->_get_current_import_status();

		if ( ! $status ) {
			$status = array(
				'status' => $this->killswitch ? 'killswitch' : 'inactive',
			);
		}

		$status['pending'] = apply_filters( "{$this->plugin_prefix}import_zip_files", array(), false, 'status' );
		if ( 1 === count( $status['pending'] ) ) {
			$status['pending'] = array_merge( array(), $status['pending'] );
		}
		$status['cnt_pending_files'] = count( $status['pending'] );

		echo json_encode( $status );

		exit;
	} // ajax_status

	/**
	 * Process core functions.
	 *
	 * @since 1.0
	 *
	 * @param string $action Action to perform.
	 */
	public function process( $action = false ) {
		$this->processing_errors = array();

		if ( ! $this->log || ! $this->log->is_available ) {
			$this->processing_errors[] = __( 'Logger not available', 'immonex-openimmo2wp' );
			return;
		}

		if ( ! $this->engage ) {
			$this->processing_errors[] = wp_sprintf(
				__( 'Plugin license not active, please activate under <a href="%s">OpenImmo2WP &rarr; Settings &rarr; License</a>!', 'immonex-openimmo2wp' ),
				admin_url( "admin.php?page={$this->plugin_slug}_settings&tab=tab_license" )
			);
			return;
		}

		if ( ! $action ) {
			if ( isset( $_REQUEST['action'] ) ) $action = $_REQUEST['action'];
		}

		if ( $action ) {
			$working_dir = apply_filters( "{$this->plugin_prefix}working_dir", '' );
			$archive_dir = apply_filters( "{$this->plugin_prefix}archive_dir", '' );

			switch ( $action ) {
				case 'reset' :
					$this->_reset();
					$current_url = str_replace(
						'&action=reset',
						'',
						( empty( $_SERVER['HTTPS'] ) ? 'http' : 'https' ) . "://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}"
					);
					wp_redirect( $current_url );
					exit;
				case 'import' :
					if ( $this->killswitch ) {
						// Killswitch active: don't perform imports!
						exit;
					}

					$this->process_resources->start_timekeeping();

					// Load current mappings.
					$this->_fetch_mappings( $this->plugin_options['mapping_file'] );

					// Set the current import language based on WP locale or translation engine setting (via filter).
					do_action( "{$this->plugin_prefix}set_current_import_language", substr( get_locale(), 0, 2 ) );

					// Eventually switch the current user locale to the system default locale by default.
					switch_to_locale( get_locale() );

					// Get or set the current import token (only one import should run at a time).
					$token = isset( $_REQUEST['token'] ) && ! empty( $_REQUEST['token'] ) ? $_REQUEST['token'] : uniqid();

					$current_status = $this->_get_current_import_status();
					$force_resumption = isset( $_REQUEST['force_resumption'] ) && (
						(
							$_REQUEST['force_resumption'] == 1 &&
							! isset( $current_status['token'] )
						) || (
							$_REQUEST['force_resumption'] == 2
						)
					);

					$import_zip_files = apply_filters( "{$this->plugin_prefix}import_zip_files", array() );

					if (
						$current_status &&
						isset( $current_status['dir'] ) &&
						! in_array( strtolower( basename( $current_status['dir'] ) ), array_map( array( get_class( $this->string_utils ), 'get_plain_unzip_folder_name' ), array_keys( $import_zip_files ) ) )
					) {
						// ZIP archive of current import directory doesn't exist anymore:
						// Delete the directory, process the current log and reset the import.
						$error_message = wp_sprintf( __( 'Current import ZIP archive not available anymore - import canceled (%s).', 'immonex-openimmo2wp' ), $current_status['dir'] );
						$this->processing_errors[] = $error_message;
						$this->log->add( $error_message, 'fatal' );

						$log_file = wp_sprintf(
							'%s/%s__failed__%s.log',
							$archive_dir,
							date_i18n( 'Ymd_Hi' ),
							Filename_Utils::get_plain_basename( basename( $current_status['dir'] ) )
						);
						$this->_process_logs( $log_file );
						$current_status = false;
						$this->_reset( false );

						// Refresh list of pending import files.
						$import_zip_files = apply_filters( "{$this->plugin_prefix}import_zip_files", array(), false, false, true );
					}

					if ( count( $import_zip_files ) > 0 ) {
						foreach ( $import_zip_files as $zip_file_info ) {
							$zip_file = $zip_file_info->getRealpath();
							do_action( $this->plugin_prefix . 'start_processing_zip_archive', $zip_file );

							if ( $current_status ) {
								// Check the token if resumption is not forced.
								if ( ( ! isset( $force_resumption ) || ! $force_resumption ) &&	( isset( $current_status['token'] ) && $token !== $current_status['token'] ) ) {
									// Prevent multiple simultaneous import process runs by checking the token.
									$this->processing_errors[] = __( 'Another import process is currently running.', 'immonex-openimmo2wp' );
									$this->import_process_running_error = true;
									break;
								}

								// Current status data available: Resume previous import.
								$unzip_dir = $current_status['dir'];
								$subdir_based_on_file = $working_dir . DIRECTORY_SEPARATOR . $this->string_utils::get_plain_unzip_folder_name( $zip_file );

								if ( $unzip_dir == $subdir_based_on_file ) {
									// Generate archive and log filenames.
									$archive_file = wp_sprintf(
										'%s/%s__%s__%s',
										$archive_dir,
										date_i18n( 'Ymd_Hi' ),
										str_replace( DIRECTORY_SEPARATOR, '_', apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $zip_file ) ),
										Filename_Utils::get_plain_basename( basename( $zip_file ) )
									);
									$log_file = substr( $archive_file, 0, -4 ) . '.log';
								} else {
									continue;
								}
							} else {
								// Reset import log and (re)start logging (for each import ZIP file).
								$this->log->reset();
								$this->log->add( wp_sprintf( __( 'Plugin Version: %s', 'immonex-openimmo2wp' ), self::PLUGIN_VERSION ), 'debug' );
								$this->log->add( wp_sprintf( __( 'Plugin FS Folder: %s', 'immonex-openimmo2wp' ), dirname( __DIR__ ) ), 'debug' );
								$this->log->add( wp_sprintf( __( 'Import Folder: %s', 'immonex-openimmo2wp' ), apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $zip_file ) ), 'debug' );
								$this->log->add( wp_sprintf( __( 'Working Directory: %s', 'immonex-openimmo2wp' ), $working_dir ), 'debug' );
								$this->log->add( wp_sprintf( __( 'Global Import Directory: %s', 'immonex-openimmo2wp' ), apply_filters( "{$this->plugin_prefix}global_import_dir", '' ) ), 'debug' );
								$this->log->add( wp_sprintf( __( 'Archive Directory: %s', 'immonex-openimmo2wp' ), $archive_dir ), 'debug' );
								$this->log->add( wp_sprintf( __( 'Website/Local Timezone: %s', 'immonex-openimmo2wp' ), get_option( 'timezone_string' ) ), 'debug' );
							}

							if ( $this->mapping_error ) {
								// Stop here on fatal mapping error.
								$error_message = wp_sprintf( __( 'Mapping error: %s', 'immonex-openimmo2wp' ), $this->mapping_error );
								$this->processing_errors[] = $error_message;
								$this->log->add( $error_message, 'fatal' );
								$this->_process_logs( $log_file );
								break;
							}

							if ( false === $current_status ) {
								// New import.
								// Generate extraction directory name.
								$subdir_name = $this->string_utils::get_plain_unzip_folder_name( $zip_file );
								$unzip_dir = $working_dir . DIRECTORY_SEPARATOR . $subdir_name;
								$plain_import_folder = apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $zip_file );

								// Generate archive and log filenames.
								$archive_file = wp_sprintf(
									'%s/%s__%s__%s',
									$archive_dir,
									date_i18n( 'Ymd_Hi' ),
									str_replace( DIRECTORY_SEPARATOR, '_', $plain_import_folder ),
									Filename_Utils::get_plain_basename( basename( $zip_file ) )
								);
								$log_file = substr( $archive_file, 0, -4 ) . '.log';

								$process_zip_file = apply_filters(
									$this->plugin_prefix . 'zip_archive_before_import',
									$zip_file,
									array(
										'import_folder' => $plain_import_folder,
										'subdir_name' => $subdir_name,
										'unzip_dir' => $unzip_dir,
										'archive_file' => $archive_file,
										'log_file' => $log_file
									)
								);

								if ( ! $process_zip_file ) {
									// Cancel processing the current import ZIP archive here
									// if set to false by the previous filter.
									$this->log->reset();
									continue;
								}

								// Save initial import status data.
								$zip_file_mtime = $zip_file_info->getMTime();
								$status = array(
									'token' => $token,
									'status' => 'processing',
									'pending' => apply_filters( "{$this->plugin_prefix}import_zip_files", array(), false, 'status' ),
									'file' => basename( $zip_file ),
									'file_mtime' => $zip_file_mtime ? date_i18n( 'Y-m-d H:i:s', $zip_file_mtime ) : '',
									'folder' => apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $zip_file ),
									'dir' => $unzip_dir,
									'dir_basename' => basename( $unzip_dir ),
									'import_type' => '',
									'property_title' => '',
									'current_property' => '',
									'cnt_next_property' => 0,
									'total_cnt_properties' => 0,
									'cnt_next_attachment' => 0,
									'total_cnt_attachments' => 0,
									'processed_xml_files' => array(),
									'cnt_current_agency' => 1,
									'logged_agencies' => array()
								);

								$this->_save_current_import_status( $status );

								$this->log->add( wp_sprintf( __( 'Unzipping %s', 'immonex-openimmo2wp' ), basename( $zip_file ) ), 'info' );

								/**
								 * Extract import ZIP file to temporary directory.
								 * Create unzip folder first due to compatibility/permissions reasons concerning some hosting environments.
								 */
								wp_mkdir_p( $unzip_dir );
								chmod( $unzip_dir, 0775 );

								$result = unzip_file( $zip_file, $unzip_dir );
								$this->wp_filesystem->chmod( $unzip_dir, 0664, true );

								if ( true === $result ) {
									$this->log->add( wp_sprintf( __( 'Successfully unzipped %s', 'immonex-openimmo2wp' ), basename( $zip_file ) ), 'debug' );

									$this->_extract_nested_zip_files( $unzip_dir );
									$this->security->delete_unallowed_files_from_import_folders( $unzip_dir );
								} else {
									// Error on unzipping.
									if ( is_wp_error( $result ) ) {
										if ( 'incompatible_archive' === $result->get_error_code() ) {
											// FTP transfer may not be complete yet.
											$unzip_error_msg = wp_sprintf( __( 'Error on unzipping %s (possibly file transfer not complete yet): %s', 'immonex-openimmo2wp' ), $zip_file, $result->get_error_message() );
										} else {
											$unzip_error_msg = wp_sprintf( __( 'Error on unzipping %s: %s', 'immonex-openimmo2wp' ), $zip_file, $this->general_utils->get_error_description( $result ) );
										}
									} else {
										$unzip_error_msg = wp_sprintf( __( 'Error on unzipping achive file: %s', 'immonex-openimmo2wp' ), $zip_file );
									}

									$this->processing_errors[] = $unzip_error_msg;
									$this->log->add( $unzip_error_msg, 'fatal' );

									$mtime = $this->_get_mtime( $zip_file );
									if ( $mtime && $mtime < strtotime( apply_filters( $this->plugin_prefix . 'delete_zip_file_if_older_than', self::DELETE_CORRUPT_ZIP_ARCHIVES_IF_OLDER_THAN ) ) ) {
										// Corrupt ZIP archive is older than given timeframe: Delete it and process the log.
										if ( $this->wp_filesystem->delete( $zip_file ) ) {
											$this->log->add( wp_sprintf( __( 'Corrupt/Unprocessable ZIP archive deleted: %s', 'immonex-openimmo2wp' ), $zip_file ), 'debug' );
										} else {
											$this->log->add( wp_sprintf( __( 'Corrupt/Unprocessable ZIP archive could not be deleted: %s', 'immonex-openimmo2wp' ), $zip_file ), 'debug' );
										}
										if ( $this->is_debug() ) {
											$log_file = substr( $zip_file, 0, -4 ) . '.log';
										}
										$this->_process_logs( $log_file );
									}

									// Delete temporary extraction directory.
									$this->wp_filesystem->delete( $unzip_dir, true );

									// Delete the current status file or option.
									$this->_delete_current_import_status();

									// Always stop imports on fatal unzip errors.
									break;
								}
							}

							do_action( "{$this->plugin_prefix}import_zip_file_in_processing", $zip_file );

							// Remember the current ZIP file path/name for (possibly) assigning a user later.
							$this->current_import_zip_file = $zip_file;

							// Remember the already processed XML files (if multiple) of the current import ZIP archive.
							$this->current_processed_xml_files = isset( $current_status['processed_xml_files'] ) ? $current_status['processed_xml_files'] : array();

							// Perform property import.
							$this->_import( $unzip_dir, isset( $current_status['cnt_next_property'] ) ? $current_status['cnt_next_property'] : false, isset( $current_status['cnt_next_attachment'] ) ? $current_status['cnt_next_attachment'] : 0, $token );

							$this->log->add( '--', 'info' );

							// Delete temporary extraction directory.
							if ( $this->wp_filesystem->delete( $unzip_dir, true ) ) {
								$this->log->add( wp_sprintf( __( 'Extraction directory successfully deleted: %s', 'immonex-openimmo2wp' ), $unzip_dir ), 'debug' );
							} else {
								$this->log->add( wp_sprintf( __( 'Extraction directory could not be deleted: %s', 'immonex-openimmo2wp' ), $unzip_dir ), 'error' );
							}

							if ( $this->is_debug() ) {
								// Debug mode: no archiving/cleanup.
								$log_file = substr( $zip_file, 0, -4 ) . '.log';
							} else {
								if ( $this->plugin_options['keep_archive_files_days'] > 0 ) {
									// Move ZIP file to archive directory.
									if ( $this->wp_filesystem->move( $zip_file, $archive_file, true ) ) {
										$this->log->add( wp_sprintf( __( 'ZIP archive moved to archive directory: %s', 'immonex-openimmo2wp' ), basename( $archive_file ) ), 'info' );
									} else {
										$this->log->add( wp_sprintf( __( 'ZIP archive could not be moved to archive directory: %s', 'immonex-openimmo2wp' ), basename( $zip_file ) ), 'error' );
									}
								} else {
									// No archiving: delete ZIP file.
									if ( $this->wp_filesystem->delete( $zip_file ) ) {
										$this->log->add( wp_sprintf( __( 'ZIP archive deleted: %s', 'immonex-openimmo2wp' ), basename( $archive_file ) ), 'info' );
									} else {
										$this->log->add( wp_sprintf( __( 'ZIP archive could not be deleted: %s', 'immonex-openimmo2wp' ), basename( $zip_file ) ), 'error' );
									}
								}

								try {
									// Delete outdated archive and mapping backup files etc.
									Cleanup::cleanup_after_import();
								} catch ( \Exception $e ) {
									$this->log->add( wp_sprintf( __( 'Cleanup error after completed import process: %s', 'immonex-openimmo2wp' ), $e->getMessage() ), 'error' );
								}

								// Generate log filename.
								$log_file = substr( $archive_file, 0, -4 ) . '.log';
							}

							// Import completed: delete current status option or file.
							$this->_delete_current_import_status();

							$current_status = false;

							// Save/send logs.
							$this->_process_logs( $log_file );

							do_action( "{$this->plugin_prefix}import_zip_file_processed", $zip_file );
						}
					}

					if ( 0 === count( $this->processing_errors ) ) {
						$this->add_admin_notice( __( 'Import completed successfully!', 'immonex-openimmo2wp' ), 'info' );
					} else {
						$this->add_admin_notice( wp_sprintf( __( 'Errors during import: %s', 'immonex-openimmo2wp' ), implode( ', ', $this->processing_errors ) ), 'error' );
					}

					// Reset the default language after import.
					do_action( "{$this->plugin_prefix}set_current_import_language", substr( get_locale(), 0, 2 ) );

					// Maybe restore the user locale.
					restore_current_locale();
			}
		}
	} // process

	/**
	 * Get a theme name without version etc.
	 *
	 * @since 1.5.2
	 *
	 * @param string $theme_name Theme name.
	 * @param bool $lowercase Convert string to lowercase.
	 *
	 * @return string Plain theme name.
	 */
	public function get_plain_theme_name( $theme_name, $lowercase = true ) {
		if ( empty( $theme_name ) ) return '';

		// Strip version numbers from theme names for comparison.
		$version_regex = '/[\/]?[vV]?((?<![0-9])[0-9]$|[0-9]{1,2}\.)([0-9]{1,2}(\.[0-9]{1,2})?(\.[0-9]{1,2})?)?$/';
		$theme_name = trim( preg_replace( $version_regex, '', $theme_name ) );
		if ( $lowercase ) $theme_name = strtolower( $theme_name );

		return preg_replace( $version_regex, '', $theme_name );
	} // get_plain_theme_name

	/**
	 * Filter out empty or null values.
	 *
	 * @since 2.0.2 beta
	 *
	 * @param mixed $value Value.
	 * @param string $mapping_source Mapping source string.
	 * @param string $type Data type.
	 *
	 * @return mixed Unchanged value or false if empty/0 etc.
	 */
	public function filter_empty_values( $value, $mapping_source, $type ) {
		$mapping_exceptions = apply_filters( $this->plugin_prefix . 'filter_zero_values_mapping_exceptions', array( 'geo->etage' ) );
		$mapping_source_split = $this->_split_element( $mapping_source );

		$ignore_value_defs = array(
			'0',
			'false',
			'empty',
			'missing',
			'empty_or_missing',
			'exists'
		);

		if (
			true === $value ||
			in_array( $mapping_source, $mapping_exceptions ) ||
			in_array( $mapping_source_split['node'], $mapping_exceptions ) ||
			'objektkategorie->objektart' === substr( $mapping_source, 0, 26 ) ||
			(
				false !== $mapping_source_split['node_value_is'] &&
				in_array( strtolower( (string) $mapping_source_split['node_value_is'] ), $ignore_value_defs, true )
			)
		) {
			return $value;
		}

		$special_filters = apply_filters( $this->plugin_prefix . 'filter_zero_values_special', array( '0.0', '0.00', '0,0', '0,00' ) );

		$value_check = str_replace( '&nbsp;', ' ', $value );
		$value_check = trim( preg_replace('/(%|€|EUR|Euro|m²|qm)$/', '', $value_check ) );

		if (
			'0' == $value_check ||
			(
				'' === $value_check &&
				'taxonomy_data' !== $type
			) || (
				is_numeric( $value_check ) &&
				0 == $value_check
			) || (
				count( $special_filters ) > 0 &&
				in_array( $value_check, $special_filters )
			)
		) {
			return false;
		}

		return $value;
	} // filter_empty_values

	/**
	 * Stop current import via global import folder if scope is "full".
	 *
	 * @since 2.2 beta
	 *
	 * @param SimpleXMLElement $openimmo_xml Full OpenImmo data of current XML file.
	 *
	 * @return SimpleXMLElement|bool Unchanged XML data or false on full imports.
	 */
	public function disable_full_imports( $openimmo_xml ) {
		if ( 'VOLL' == strtoupper( (string) $openimmo_xml->uebertragung['umfang'] ) ) {
			$openimmo_xml = false;
			$this->log->add( __( 'Full imports are not permitted via the global import folder. Import of this file will be skipped.', 'immonex-openimmo2wp' ), 'error' );
		}

		return $openimmo_xml;
	} // disable_full_imports

	/**
	 * Fetch property data based on its OpenImmo OBID.
	 *
	 * @since 1.0
	 *
	 * @param string $obid Unique OpenImmo object ID.
	 * @param bool $quiet Suppress log entries?
	 *
	 * @return WP_Post[] Array of matching property post objects.
	 */
	public function get_property_by_openimmo_obid( $obid, $quiet = false ) {
		global $wpdb;

		if ( ! trim( $obid ) ) return array();
		$property_import_folder = apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $this->current_import_zip_file );

		if ( $this->compat_flags['wp_query_for_obid'] ) {
			$meta_query = array(
				'relation' => 'AND',
				array(
					'key' => '_is_immonex_import_property',
					'compare' => 'EXISTS'
				),
				array(
					'key' => '_openimmo_obid',
					'value' => trim( $obid )
				)
			);

			$meta_query[] = array(
				'key' => '_immonex_import_folder',
				'value' => $property_import_folder
			);

			$args = array(
				'post_type' => $this->property_post_type ? $this->property_post_type : self::DEFAULT_PROPERTY_POST_TYPE,
				'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ),
				'meta_query' => $meta_query,
				'numberposts' => -1
			);

			if ( $this->enable_multilang ) $args['lang'] = '';

			// Fetch the property posts with the given OBID (if existing).
			$properties = get_posts( $args );
			$count = count( $properties );
		} else {
			$query = $wpdb->prepare(
				"SELECT p.ID FROM $wpdb->posts p
					INNER JOIN $wpdb->postmeta pm1 ON p.ID = pm1.post_id AND pm1.meta_key = '_is_immonex_import_property' AND pm1.meta_value = '1'
					INNER JOIN $wpdb->postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = '_immonex_import_folder' AND pm2.meta_value = %s
					INNER JOIN $wpdb->postmeta pm3 ON p.ID = pm3.post_id AND pm3.meta_key = '_openimmo_obid' AND pm3.meta_value = %s
					WHERE p.post_type = %s AND p.post_status IN ( 'publish', 'draft', 'pending', 'future', 'private' )" .
					( ! $this->compat_flags['detect_multiple_properties_with_same_obid'] ? ' LIMIT 1' : '' ),
				$property_import_folder,
				trim( $obid ),
				$this->property_post_type ? $this->property_post_type : self::DEFAULT_PROPERTY_POST_TYPE
			);

			$property_ids = $wpdb->get_results( $query );

			$properties = array();
			if ( count( $property_ids ) > 0 ) {
				foreach ( $property_ids as $property ) {
					$properties[] = get_post( $property->ID );
				}
			}
			$count = count( $properties );
		}

		if ( 0 === $count ) {
			// No properties with OBID custom field found, try alternative backup source: MIME type in post record
			// (add current import folder if user-related).
			$query = $wpdb->prepare(
				"SELECT ID FROM $wpdb->posts WHERE post_type = %s AND post_mime_type = %s AND post_status IN ( 'publish', 'draft', 'pending', 'future', 'private' )",
				$this->property_post_type ? $this->property_post_type : self::DEFAULT_PROPERTY_POST_TYPE,
				sanitize_text_field( trim( $obid ) . ( 'global' !== $property_import_folder ? $property_import_folder : '' ) )
			);

			$property_ids = $wpdb->get_results( $query );

			$properties = array();
			if ( count( $property_ids ) > 0 ) {
				foreach ( $property_ids as $property ) {
					$properties[] = get_post( $property->ID );
				}
			}
			$count = count( $properties );
		};

		if ( ! $quiet ) {
			if ( $this->compat_flags['detect_multiple_properties_with_same_obid'] && $count > 1 ) {
				$this->log->add( wp_sprintf( __( 'Multiple properties with the same OpenImmo OBID found: %s', 'immonex-openimmo2wp' ), trim( $obid ) ), 'debug' );
			} elseif ( 0 === $count ) {
				$this->log->add( wp_sprintf( __( 'Property with OpenImmo OBID %s does not exist (yet)', 'immonex-openimmo2wp' ), trim( $obid ) ), 'debug' );
			}
		}

		return $properties;
	} // get_property_by_openimmo_obid

	/**
	 * Get translation of a specific string translated with Polylang.
	 *
	 * @since 2.4.7 beta
	 *
	 * @param string $string String to get translation for.
	 *
	 * @return string Translated string or original string if nonexistent.
	 */
	public function multilang_get_string_translation( $string ) {
		return ( $this->enable_multilang && function_exists( 'pll_translate_string' ) ) ?
			pll_translate_string( $string, $this->current_import_language ) : $string;
	} // multilang_get_string_translation

	/**
	 * Strip language tags from term names.
	 *
	 * @since 3.0
	 *
	 * @param int|WP_Term $term Term object or ID.
	 * @param string $taxonomy Taxonomy slug.
	 *
	 * @return string Translated string or original string if nonexistent.
	 */
	function strip_language_tags( $term, $taxonomy ) {
		if (
			! is_admin() &&
			is_object( $term ) &&
			false !== strpos( $term->name, '@' )
		) {
			$term->name = trim( substr( $term->name, 0, strpos( $term->name, '@' ) ) );
		}

		return $term;
	} // strip_language_tags

	/**
	 * Register plugin settings.
	 *
	 * @since 1.0
	 */
	public function register_plugin_settings() {
		parent::register_plugin_settings();

		$doc_urls = array(
			'mapping_type' => 'https://docs.immonex.de/openimmo2wp/#/installation-einrichtung/plugin-optionen?id=mapping-typ',
			'mapping' => 'https://docs.immonex.de/openimmo2wp/#/mapping/tabellen',
			'global_folder' => 'https://docs.immonex.de/openimmo2wp/#/grundlagen/ordner?id=global',
			'user_folders' => 'https://docs.immonex.de/openimmo2wp/#/grundlagen/ordner?id=benutzerbezogen',
			'archive_folder' => 'https://docs.immonex.de/openimmo2wp/#/grundlagen/ordner?id=archiv'
		);

		// Tabs (extendable by filter function).
		$tabs = array(
			'tab_general' => array(
				'title' => __( 'General', 'immonex-openimmo2wp' )
			),
			'tab_script_resources' => array(
				'title' => __( 'Script Resources', 'immonex-openimmo2wp' )
			),
			'tab_geocoding' => array(
				'title' => __( 'Geocoding', 'immonex-openimmo2wp' )
			)
		);

		$tabs = apply_filters( $this->plugin_slug . '_option_tabs', $tabs );

		foreach ( $tabs as $id => $tab ) {
			$this->settings_helper->add_tab( $id, $tab['title'], isset( $tab['content'] ) ? $tab['content'] : '', isset( $tab['attributes'] ) ? $tab['attributes'] : array() );
		}

		// Sections (extendable by filter function).
		$sections = apply_filters( $this->plugin_slug . '_option_sections', array(
			'section_general' => array(
				'title' => '',
				'description' => '',
				'tab' => 'tab_general'
			),
			'section_script_resources' => array(
				'title' => '',
				'description' => __( 'Long-running imports will be splitted up into multiple script runs to prevent timeouts or server errors that mostly occur if not enough memory is available (e.g. during image processing).', 'immonex-openimmo2wp' ),
				'tab' => 'tab_script_resources'
			),
			'section_geocoding' => array(
				'title' => '',
				'description' => wp_sprintf(
					__( 'If no property location coordinates are transferred, these will be determined via geocoding during the import process. For this purpose, this plugin supports the basically free, %1$s based services <strong>Nominatim</strong> (%2$s) and %3$s kindly provided by %4$s. However, the usage of theses services <strong>could</strong> be limited or restricted anytime. Alternatively, the commercial %5$s or %6$s can be used. In the latter case, providing at least one suitable API key below is required.', 'immonex-openimmo2wp' ),
					'<a href="https://www.openstreetmap.org/" target="_blank">OpenStreetMap</a>',
					'<a href="https://operations.osmfoundation.org/policies/nominatim/" target="_blank">' . __( 'Usage Policy', 'immonex-openimmo2wp' ) . '</a>',
					'<a href="https://photon.komoot.io/" target="_blank">Photon</a>',
					'<a href="https://www.komoot.de/" target="_blank">komoot</a>',
					'<a href="https://developers.google.com/maps/documentation/geocoding/get-api-key" target="_blank">Google Maps Geocoding API</a>',
					'<a href="https://msdn.microsoft.com/de-de/library/ff428642.aspx" target="_blank">Bing Maps API</a> by Microsoft'
				),
				'tab' => 'tab_geocoding'
			)
		) );
		foreach ( $sections as $id => $section ) {
			$this->settings_helper->add_section( $id, isset( $section['title'] ) ? $section['title'] : '', isset( $section['description'] ) ? $section['description'] : '', $section['tab'] );
		}

		$options_mapping = array();
		foreach ( $this->mapping_files as $file ) {
			$filename = basename( $file );
			$filename_display = strtolower( basename( $file, '.csv' ) );
			$options_mapping[$filename] = $filename_display;
		}

		$mapping_folders = $this->string_utils->shorten_paths( apply_filters( "{$this->plugin_prefix}mapping_folders", array() ) );
		$mapping_desc = wp_sprintf(
			__( '<a href="%1$s" class="immonex-doc-link" target="_blank">Mapping tables</a> are <strong>CSV files</strong> located in %2$s: <code>%3$s</code>', 'immonex-openimmo2wp' ),
			$doc_urls['mapping'],
			count( $mapping_folders ) > 1 ? __( 'these folders', 'immonex-openimmo2wp' ) : __( 'this folder', 'immonex-openimmo2wp' ),
			count( $mapping_folders ) > 1 ? implode( '</code>, <code>', $mapping_folders ) : $mapping_folders[0],
		);

		$archive_folder = $this->string_utils->shorten_paths( apply_filters( "{$this->plugin_prefix}archive_dir", '' ) );

		// Fields (extendable by filter function).
		$fields = apply_filters( $this->plugin_slug . '_option_fields', array(
			array(
				'name' => 'mapping_file',
				'type' => 'select',
				'label' => __( 'Mapping Type', 'immonex-openimmo2wp' ),
				'section' => 'section_general',
				'args' => array(
					'description' => $mapping_desc,
					'options' => $options_mapping,
					'doc_url' => $doc_urls['mapping_type']
				)
			),
			array(
				'name' => 'enable_auto_import',
				'type' => 'checkbox',
				'label' => __( 'Enable Auto-Import', 'immonex-openimmo2wp' ),
				'section' => 'section_general',
				'args' => array(
					'description' => wp_sprintf(
						__( 'Activate to process <strong>OpenImmo ZIP archives</strong> in <a href="%1$s">all import folders</a> frequently.', 'immonex-openimmo2wp' ),
						admin_url( "admin.php?page=openimmo2wp" )
					)
				)
			),
			array(
				'name' => 'include_global_subfolders',
				'type' => 'checkbox',
				'label' => __( 'Include Subfolders', 'immonex-openimmo2wp' ),
				'section' => 'section_general',
				'args' => array(
					'description' => wp_sprintf(
						__( 'Activate if ZIP archives in <strong>subfolders</strong> of the <a href="%s" class="immonex-doc-link" target="_blank">global import folder</a> shall be processed, too. Special folders (<code>mappings</code>, <code>users</code> and <code>archive</code>) and directories with names starting with underscores are not affected by this setting.', 'immonex-openimmo2wp' ),
						$doc_urls['global_folder']
					)
				)
			),
			array(
				'name' => 'disable_full_imports',
				'type' => 'checkbox',
				'label' => __( 'Disable Global Full Imports', 'immonex-openimmo2wp' ),
				'section' => 'section_general',
				'args' => array(
					'description' => wp_sprintf(
						__( 'Activate this option to disable full imports via the <a href="%1$s" class="immonex-doc-link" target="_blank">global import folder</a>. (Full imports via <a href="%2$s" class="immonex-doc-link" target="_blank">user-specific import folders</a> are always permitted.)', 'immonex-openimmo2wp' ),
						$doc_urls['global_folder'],
						$doc_urls['user_folders']
					)
				)
			),
			array(
				'name' => 'full_import_mode',
				'type' => 'select',
				'label' => __( 'Full Import Mode', 'immonex-openimmo2wp' ),
				'section' => 'section_general',
				'args' => array(
					'description' => __( '<strong>delete partly / update changed only</strong>: delete existing properties if not listed in the import file (anymore), update if last update date is lower than the one of the new version<br>
						<strong>delete partly / update all</strong>: delete existing properties if not listed in the import file (anymore), always update listed properties<br>
						<strong>delete all / insert all</strong>: delete all existing properties, newly import all properties listed in the import file</strong>', 'immonex-openimmo2wp' ),
					'options' => array(
						'delete_part_update_changed' => __( 'delete partly / update changed only', 'immonex-openimmo2wp' ),
						'delete_part_update_all' => __( 'delete partly / update all', 'immonex-openimmo2wp' ),
						'delete_all_insert_all' => __( 'delete all / insert all', 'immonex-openimmo2wp' )
					)
				)
			),
			array(
				'name' => 'review_imported_properties',
				'type' => 'select',
				'label' => __( 'Review imported Properties', 'immonex-openimmo2wp' ),
				'section' => 'section_general',
				'args' => array(
					'description' => __( 'The post status of newly imported and/or updated properties is being set to "<em>Pending Review</em>" if the respective option is selected.', 'immonex-openimmo2wp' ),
					'options' => array(
						'none' => __( 'none', 'immonex-openimmo2wp' ),
						'new' => __( 'new properties only', 'immonex-openimmo2wp' ),
						'all' => __( 'all properties (new and updated)', 'immonex-openimmo2wp' )
					)
				)
			),
			array(
				'name' => 'import_log_recipient_email',
				'type' => 'email',
				'label' => __( 'Import Log Recipient', 'immonex-openimmo2wp' ),
				'section' => 'section_general',
				'args' => array(
					'description' => __( 'Email address a summary shall be sent to after each processing of an import file.', 'immonex-openimmo2wp' )
				)
			),
			array(
				'name' => 'debug_log_recipient_email',
				'type' => 'email_list',
				'label' => __( 'Debug Log Recipient', 'immonex-openimmo2wp' ),
				'section' => 'section_general',
				'args' => array(
					'description' => wp_sprintf(
						__( 'Email address a log with extended debug information shall be sent to. (Debug logs are also saved in the <a href="%1$s" class="immonex-doc-link" target="_blank">archive folder</a> <code>%2$s</code> if archiving is enabled.)', 'immonex-openimmo2wp' ),
						$doc_urls['archive_folder'],
						$archive_folder
					)
				)
			),
			array(
				'name' => 'keep_archive_files_days',
				'type' => 'text',
				'label' => __( 'Archive Import Files', 'immonex-openimmo2wp' ),
				'section' => 'section_general',
				'args' => array(
					'field_suffix' => __( 'Days', 'immonex-openimmo2wp' ),
					'description' => wp_sprintf(
						__( 'Timeframe to keep processed import files in the <a href="%1$s" class="immonex-doc-link" target="_blank">archive folder</a>: <code>%2$s</code> (max. 100, 0 = no archiving)', 'immonex-openimmo2wp' ),
						$doc_urls['archive_folder'],
						$archive_folder
					),
					'class' => 'small-text',
					'min' => 0,
					'max' => 100
				)
			),
			array(
				'name' => 'max_script_exec_time',
				'type' => 'text',
				'label' => __( 'Max. Script Execution Time', 'immonex-openimmo2wp' ),
				'section' => 'section_script_resources',
				'args' => array(
					'field_suffix' => __( 'Seconds', 'immonex-openimmo2wp' ),
					'description' => wp_sprintf(
						__( 'Maximum execution time for <strong>automated imports</strong>: %s - %s seconds (default: %s)', 'immonex-openimmo2wp' ),
						number_format( Process_Resources::MAX_SCRIPT_EXEC_TIME_MIN, 0, '', '.' ),
						number_format( Process_Resources::MAX_SCRIPT_EXEC_TIME_MAX, 0, '', '.' ),
						number_format( Process_Resources::DEFAULT_MAX_SCRIPT_EXEC_TIME, 0, '', '.' )
					),
					'class' => 'small-text',
					'default_if_empty' => Process_Resources::DEFAULT_MAX_SCRIPT_EXEC_TIME,
					'min' => Process_Resources::MAX_SCRIPT_EXEC_TIME_MIN,
					'max' => Process_Resources::MAX_SCRIPT_EXEC_TIME_MAX
				)
			),
			array(
				'name' => 'max_script_run_property_cnt',
				'type' => 'text',
				'label' => __( 'Max. Number of Records', 'immonex-openimmo2wp' ),
				'section' => 'section_script_resources',
				'args' => array(
					'description' => __( 'Maximum number of properties that will be imported per script run (0 = no limit).', 'immonex-openimmo2wp' ),
					'class' => 'small-text',
					'min' => 0
				)
			),
			array(
				'name' => 'max_script_run_deleted_properties_cnt',
				'type' => 'text',
				'label' => __( 'Max. Number of Records to be deleted', 'immonex-openimmo2wp' ),
				'section' => 'section_script_resources',
				'args' => array(
					'description' => wp_sprintf( __( 'Maximum number of properties that will be deleted <strong>before the processing of full imports</strong> per script run (max. %u).', 'immonex-openimmo2wp' ), Process_Resources::MAX_SCRIPT_RUN_DELETED_PROPERTIES_CNT ),
					'class' => 'small-text',
					'min' => 0,
					'max' => Process_Resources::MAX_SCRIPT_RUN_DELETED_PROPERTIES_CNT,
					'default_if_empty' => Process_Resources::MAX_SCRIPT_RUN_DELETED_PROPERTIES_CNT
				)
			),
			array(
				'name' => 'max_image_attachments_per_property',
				'type' => 'text',
				'label' => __( 'Max. Images per Property', 'immonex-openimmo2wp' ),
				'section' => 'section_script_resources',
				'args' => array(
					'description' => __( 'The maximum number of <strong>image</strong> attachments that will be imported <strong>per property</strong> (0 = no limit).', 'immonex-openimmo2wp' ),
					'min' => 0,
					'class' => 'small-text'
				)
			),
			array(
				'name' => 'max_script_run_attachment_cnt',
				'type' => 'text',
				'label' => __( 'Max. Attachments per Script Run', 'immonex-openimmo2wp' ),
				'section' => 'section_script_resources',
				'args' => array(
					'description' => __( 'Maximum number of attachments (<strong>all allowed file types</strong>) that will be processed per <strong>script run</strong> (0 = no limit).', 'immonex-openimmo2wp' ),
					'class' => 'small-text',
					'min' => 0
				)
			),
			array(
				'name' => 'stall_check_time_minutes',
				'type' => 'text',
				'label' => __( 'Stall Check Time', 'immonex-openimmo2wp' ),
				'section' => 'section_script_resources',
				'args' => array(
					'field_suffix' => __( 'Minutes', 'immonex-openimmo2wp' ),
					'description' => wp_sprintf(
						__( 'Time after which a possibly uncontrolled aborted import process can be restarted automatically: %u - %u minutes (default: %u)', 'immonex-openimmo2wp' ),
						number_format( Process_Resources::STALL_CHECK_TIME_MINUTES_MIN, 0, '', '.' ),
						number_format( Process_Resources::STALL_CHECK_TIME_MINUTES_MAX, 0, '', '.' ),
						number_format( Process_Resources::STALL_CHECK_TIME_MINUTES, 0, '', '.' )
					),
					'class' => 'small-text',
					'default_if_empty' => Process_Resources::STALL_CHECK_TIME_MINUTES,
					'min' => Process_Resources::STALL_CHECK_TIME_MINUTES_MIN,
					'max' => Process_Resources::STALL_CHECK_TIME_MINUTES_MAX
				)
			),
			array(
				'name' => 'default_geocoding_provider',
				'type' => 'select',
				'label' => __( 'Default Geocoding Provider', 'immonex-openimmo2wp' ),
				'section' => 'section_geocoding',
				'args' => array(
					'description' => __( 'Select the preferred provider for turning property addresses into geo coordinates. (The following ones will be used automatically if no geocoding is possible with the default one.)', 'immonex-openimmo2wp' ),
					'options' => $this->geocoding_providers
				)
			),
			array(
				'name' => 'google_maps_api_key',
				'type' => 'text',
				'label' => __( 'Google Maps API Key', 'immonex-openimmo2wp' ),
				'section' => 'section_geocoding',
				'args' => array(
					'description' => wp_sprintf( __( 'You can find information about getting a suitable API key on the respective <a href="%s" target="_blank">Google Developers page</a>.', 'immonex-openimmo2wp' ), 'https://developers.google.com/maps/documentation/geocoding/get-api-key' )
				)
			),
			array(
				'name' => 'bing_maps_api_key',
				'type' => 'text',
				'label' => __( 'Bing Maps API Key', 'immonex-openimmo2wp' ),
				'section' => 'section_geocoding',
				'args' => array(
					'description' => wp_sprintf( __( 'Get your personal API key in the <a href="%s" target="_blank">Bing Maps Dev Center</a>.', 'immonex-openimmo2wp' ), 'https://www.bingmapsportal.com/' )
				)
			),
			array(
				'name' => 'geo_always_use_coordinates',
				'type' => 'checkbox',
				'label' => __( 'Always use Coordinates', 'immonex-openimmo2wp' ),
				'section' => 'section_geocoding',
				'args' => array(
					'description' => __( 'Always use the <strong>submitted</strong> geo coordinates of properties (for map markers etc.), even if publishing the full address is not permitted.', 'immonex-openimmo2wp' )
				)
			)
		) );

		foreach ( $fields as $field ) {
			$args = isset( $field['args'] ) && is_array( $field['args'] ) ? $field['args'] : array();
			if (
				! isset( $args['value'] ) &&
				isset( $this->plugin_options[$field['name']] ) &&
				( empty( $args['option_name'] ) || $this->plugin_options_name === $args['option_name'] )
			) {
				$args['value'] = $this->plugin_options[$field['name']];
			}

			$this->settings_helper->add_field(
				$field['name'],
				$field['type'],
				$field['label'],
				$field['section'],
				$args
			);
		}
	} // register_plugin_settings

	/**
	 * Sanitize and validate plugin options on save.
	 *
	 * @since 1.0
	 *
	 * @param array $input Submitted form data.
	 *
	 * @return array Valid inputs.
	 */
	public function sanitize_plugin_options( $input ) {
		if ( empty( $input ) ) {
			return;
		}

		$current_tab = $this->settings_helper->get_current_tab();

		$valid = array();

		if (
			$this->theme &&
			'ext_tab_' . $this->theme->theme_class_slug === $current_tab &&
			method_exists( $this->theme, 'validate_theme_options' )
		) {
			// Validation of theme-specific options is being performed inside the theme object.
			$this->theme->validate_theme_options( $input, $valid );
		}

		return parent::sanitize_plugin_options( array_merge( $input, $valid ) );
	} // sanitize_plugin_options

	/**
	 * Fetch/Reset the current "Killswitch" flag status and return its status or expiry time.
	 *
	 * @since 5.0.0
	 *
	 * @return bool|int Killswitch expiry timestamp or false if not set.
	 */
	public function get_killswitch() {
		// "Killswitch" (expiry time) for stopping running import processes.
		$killswitch = get_option( $this->killswitch_option_name );

		if ( $killswitch && $killswitch < current_time( 'timestamp' ) ) {
			delete_option( $this->killswitch_option_name );
			$killswitch = false;
		}

		return $killswitch;
	} // get_killswitch

	/**
	 * Stop and reset the current import process.
	 *
	 * @param bool $reset_log Also reset/delete the raw log data? (true by default)
	 *
	 * @since 4.7.2b
	 * @access private
	 */
	private function _reset( $reset_log = true ) {
		if ( ! is_admin() || ! current_user_can( 'activate_plugins' ) ) return;

		$current_status = $this->_get_current_import_status();

		if ( $current_status ) {
			if (
				isset( $current_status['dir'] ) &&
				file_exists( $current_status['dir'] )
			) {
				// Delete the currently processed unzip directory.
				$this->wp_filesystem->delete( $current_status['dir'], true );
			}

			// Delete the current status file or option as well as related
			// temporary data.
			$this->_delete_current_import_status();
		}

		if ( $reset_log ) {
			// Delete temporary raw log data.
			try {
				$this->log->destroy();
			} catch ( \Exception $e ) {
				$this->add_admin_notice( wp_sprintf( __( 'Logger error: %s', 'immonex-openimmo2wp' ), $e->getMessage() ), 'error' );
			}
		}

		$this->add_admin_notice( __( 'Current import process resetted!', 'immonex-openimmo2wp' ), 'updated' );
	} // _reset

	/**
	 * Perform property data import.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param string $dir Temporary import directory.
	 * @param int/bool $cnt_start_property Number of property to start the import with.
	 * @param int/bool $cnt_start_attachment Number of attachment to start the import with.
	 * @param string $token Token of current import process.
	 */
	private function _import( $dir, $cnt_start_property = false, $cnt_start_attachment = false, $token = false ) {
		if ( (int) $this->plugin_options['max_script_exec_time'] > 0 ) {
			set_time_limit( (int) $this->plugin_options['max_script_exec_time'] );
		}

		do_action( $this->plugin_prefix . 'start_import_process', $dir );

		$import_files = $this->_get_import_xml_files( $dir );

		if ( count( $import_files ) > 0 ) {

			$filter_zero_values = apply_filters( $this->plugin_prefix . 'filter_zero_values', true );
			if ( $filter_zero_values ) {
				add_filter( $this->plugin_prefix . 'get_element_value', array( $this, 'filter_empty_values' ), 10, 3 );
			}

			if (
				'global' === apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $this->current_import_zip_file ) &&
				$this->plugin_options['disable_full_imports']
			) {
				// Disable full imports via the global import folder if respective plugin option is active.
				add_filter( $this->plugin_prefix . 'xml_data_before_import', array( $this, 'disable_full_imports' ) );
			}

			if ( is_plugin_active( 'wpcasa-polylang/wpcasa-polylang.php' ) ) {
				// Remove an unnecessary hook action of the wpCasa Polylang support plugin.
				global $wpsight_polylang;
				if ( is_object( $wpsight_polylang ) ) remove_action( 'add_attachment', array( $wpsight_polylang, 'polylang_wpsight_add_attachment' ) );
			}

			// Set the name of the main post type for properties (may be changed by the active theme object).
			$this->property_post_type = apply_filters( $this->plugin_prefix . 'set_property_post_type', self::DEFAULT_PROPERTY_POST_TYPE );

			// Reset the counter for imported properties and the list of processed XML files.
			$this->current_property_insert_count = 0;
			if ( false === $cnt_start_property ) {
				$this->current_processed_xml_files = array();
				$max_script_memory_usage = $this->string_utils->get_bytes( ini_get( 'memory_limit' ) );

				$this->log->add( wp_sprintf( __( 'Max. Script Execution Time: %s', 'immonex-openimmo2wp' ), $this->plugin_options['max_script_exec_time'] > 0 ? $this->plugin_options['max_script_exec_time'] . ' s' : __( 'unlimited', 'immonex-openimmo2wp' ) ), 'debug' );
				$this->log->add( wp_sprintf( __( 'Max. Script Memory Usage: %s', 'immonex-openimmo2wp' ), $max_script_memory_usage > 0 ? number_format( floor( $max_script_memory_usage / 1024 ), 0, ',', '.' ) . ' kb' : __( 'unknown', 'immonex-openimmo2wp' ) ), 'debug' );
				$this->log->add( wp_sprintf( __( 'Max. Records per Script Run: %s', 'immonex-openimmo2wp' ), $this->plugin_options['max_script_run_property_cnt'] > 0 ? $this->plugin_options['max_script_run_property_cnt'] : __( 'unlimited', 'immonex-openimmo2wp' ) ), 'debug' );
				$this->log->add( wp_sprintf( __( 'Max. Records to be deleted per Script Run: %s', 'immonex-openimmo2wp' ), $this->process_resources->get_num_of_properties_to_be_deleted() ), 'debug' );
				$this->log->add( wp_sprintf( __( 'Max. Attachments per Script Run: %s', 'immonex-openimmo2wp' ), $this->plugin_options['max_script_run_attachment_cnt'] > 0 ? $this->plugin_options['max_script_run_attachment_cnt'] : __( 'unlimited', 'immonex-openimmo2wp' ) ), 'debug' );
				$this->log->add( wp_sprintf( __( 'Max. Image Attachments per Property: %s', 'immonex-openimmo2wp' ), $this->plugin_options['max_image_attachments_per_property'] > 0 ? $this->plugin_options['max_image_attachments_per_property'] : __( 'unlimited', 'immonex-openimmo2wp' ) ), 'debug' );
				$this->log->add( wp_sprintf( __( '%u XML files in archive', 'immonex-openimmo2wp' ), count( $import_files ) ), 'debug' );
			}

			$languages = false;
			if ( $this->enable_multilang ) {
				// Get available languages from Multilang plugin - if used.
				$languages = apply_filters( $this->plugin_prefix . 'multilang_get_languages', $languages );
			}

			$cnt_xml_file = 0;
			foreach ( $import_files as $xml_file ) {
				// Skip already processed XML files.
				if ( in_array( $xml_file, $this->current_processed_xml_files ) ) {
					$this->log->add( wp_sprintf( __( 'Skipping already processed XML file: %s', 'immonex-openimmo2wp' ), basename( $xml_file ) ), 'debug' );
					$cnt_xml_file++;
					continue;
				}

				// Remember the current XML file.
				$this->current_import_xml_file = $xml_file;

				if ( false === $cnt_start_property ) {
					do_action( $this->plugin_prefix . 'before_import_file_processing', basename( $xml_file ) );
					$this->log->add( wp_sprintf( __( 'XML file is being processed: %s', 'immonex-openimmo2wp' ), basename( $xml_file ) ), 'info' );
				}

				// Read XML file.
				// MAYBE BREAKING CHANGE: file path instead of file contents (string) handed to filter.
				$source_xml_file = apply_filters( $this->plugin_prefix . 'raw_xml_before_import', $xml_file );

				// Create processing versions of source XML file on first script run.
				if ( file_exists( $source_xml_file ) ) {
					$xml_proc_files = Text_File_Utils::create_processing_xml_files( $source_xml_file, apply_filters( $this->plugin_prefix . 'proc_files_chunked_write', true ) );

					if ( is_array( $xml_proc_files ) && isset( $xml_proc_files['result'] ) ) {
						if ( 'SUCCESS' === $xml_proc_files['result'] ) {
							// Create a SimpleXML object of the "stripped" XML data (contains only the
							// FIRST property element <immobilie> of each agency).
							$openimmo_xml_stripped = @simplexml_load_file( $xml_proc_files['file_proc_stripped'] );
							if ( false === $openimmo_xml_stripped ) {
								$this->log->add( __( 'Error parsing processing XML files', 'immonex-openimmo2wp' ), 'fatal' );
								continue;
							}

							if ( false === $cnt_start_property ) {
								$this->log->add( __( 'Processing XML files successfully created', 'immonex-openimmo2wp' ), 'debug' );
							}
						} else {
							$this->log->add( $xml_proc_files['message'] . ': ' . $xml_proc_files['file'], 'fatal' );
							continue;
						}
					} else {
						$this->log->add( __( 'Unknown error on creating processing XML files', 'immonex-openimmo2wp' ), 'fatal' );
						continue;
					}
				} else {
					$this->log->add( wp_sprintf( __( 'Source XML file not found (%s)', 'immonex-openimmo2wp' ), $source_xml_file ), 'fatal' );
					continue;
				}

				$openimmo_xml = false;
				$current_status = $this->_get_current_import_status();

				$openimmo_xml = new XML_Reader( $xml_proc_files['file_proc_full'], $this->log );

				if ( ! $openimmo_xml || ! $openimmo_xml->xml_loaded ) {
					$this->log->add( wp_sprintf( __( 'Unable to open or parse XML file (%s)', 'immonex-openimmo2wp' ), $xml_proc_files['file_proc_full'] ), 'fatal' );
					continue;
				}

				if ( ! $openimmo_xml->has_element( 'anbieter' ) ) {
					$this->log->add( __( 'OpenImmo node "anbieter" not found', 'immonex-openimmo2wp' ), 'fatal' );
					continue;
				}

				if ( ! empty( $current_status['total_cnt_properties'] )	) {
					// Get total number of properties from current status data.
					$cnt_properties = $current_status['total_cnt_properties'];
				} else {
					// Count properties in XML data.
					$cnt_properties = $openimmo_xml->count_elements( 'immobilie' );
					$this->_save_current_import_status( array( 'total_cnt_properties' => $cnt_properties ), true );
				}

				// Fetch a list of all property OpenImmo IDs with status "CHANGE".
				$changed_or_new_property_obids = $this->temp_options->get( 'changed_obids', $this->current_import_xml_file );
				if ( ! $changed_or_new_property_obids ) {
					// Data is not available yet or outdated: create it and save it as temporary option.
					$changed_or_new_property_obids = $openimmo_xml->get_obids( 'ADD,CHANGE' );
					$this->temp_options->update( 'changed_obids', $changed_or_new_property_obids, $this->current_import_xml_file, strtotime( '+3 hours' ) );
				}

				// Fetch a number indexed array of some property data (for status display issues).
				$property_infos = $this->temp_options->get( 'property_infos', $this->current_import_xml_file );
				if ( ! $property_infos ) {
					// Data is not available yet or outdated: create it and save it as temporary option.
					$property_infos = array();
					$cnt = 0;

					while ( $immobilie = $openimmo_xml->get_next_element( 'immobilie' ) ) {
						if ( -1 === $immobilie ) {
							// XML error.
							continue 2;
						}

						// Property counters start with 1.
						$cnt++;
						$property_infos[$cnt] = array(
							'title' => trim( (string) $immobilie->freitexte->objekttitel ) ? sanitize_text_field( trim( (string) $immobilie->freitexte->objekttitel ) ) : __( 'no title', 'immonex-openimmo2wp' ),
							'cnt_attachments' => isset( $immobilie->anhaenge ) ? count( $immobilie->anhaenge->anhang ) : 0,
							'action' => 'DELETE' === (string) $immobilie->verwaltung_techn->aktion['aktionart'] ? __( 'Delete', 'immonex-openimmo2wp' ) : __( 'New/Update', 'immonex-openimmo2wp' )
						);
					}

					$this->temp_options->update( 'property_infos', $property_infos, $this->current_import_xml_file, strtotime( '+3 hours' ) );

					$openimmo_xml->rewind();
				}

				if (
					isset( $current_status['import_type'] ) &&
					$current_status['import_type']
				) {
					// Get the import scope (partial/full) from current status data.
					$import_type = $current_status['import_type'];
				} else {
					// Get (and save) the import scope from XML data.
					$uebertragung = $openimmo_xml->get_next_element( 'uebertragung', true );
					if ( -1 === $uebertragung ) {
						// XML error.
						continue;
					}

					$import_type = isset( $uebertragung['umfang'] ) && 'VOLL' === strtoupper( (string) $uebertragung['umfang'] ) ? 'full' : 'partial';

					$this->_save_current_import_status( array(
						'import_type' => $import_type
					), true );
				}

				$resumed_import_log_message = false;

				// Skip the following checks etc. if a previous import is being resumed.
				if ( false === $cnt_start_property ) {
					if ( $this->compat_flags['full_xml_before_import'] ) {
						// COMPATIBILITY/DEPRECATED: Load full processing XML file as SimpleXML object.
						$xml_data_before_import = simplexml_load_file( $xml_proc_files['file_proc_full'] );
					} else {
						// COMPATIBILITY/DEPRECATED: Use already existing "stripped" SimpleXML object (contains
						// only the FIRST property element <immobilie> of each agency).
						$xml_data_before_import = $openimmo_xml_stripped;
					}

					// Make whole XML data modifiable by filter function ON FIRST SCRIPT RUN.
					// MAYBE BREAKING CHANGE: XML data without property elements handed to filter.
					$xml_data_before_import = apply_filters( $this->plugin_prefix . 'xml_data_before_import', $xml_data_before_import );

					if ( ! $xml_data_before_import ) continue;

					$this->log->add( wp_sprintf( __( 'Properties in this file: %u', 'immonex-openimmo2wp' ), $cnt_properties ), 'debug' );

					if ( 'full' === $import_type ) {
						// Full import: delete all previously imported properties first.
						$this->log->add( __( 'Import scope: Full', 'immonex-openimmo2wp' ), 'info' );
						$this->log->add( wp_sprintf( __( 'Full import mode: %s', 'immonex-openimmo2wp' ), $this->plugin_options['full_import_mode'] ), 'debug' );
						$this->_delete_all_import_properties( $dir, $cnt_properties, $token, false, $changed_or_new_property_obids );
					} else {
						$this->log->add( __( 'Import scope: Partial', 'immonex-openimmo2wp' ), 'info' );
					}
				} elseif (
					'full' === $import_type &&
					in_array( $cnt_start_property, array( 0, 1 ) ) &&
					! $cnt_start_attachment
				) {
					// Resumed full import (during deletion of old properties): Maybe there are more properties to delete.
					$this->log->add( __( 'Previous import resumed', 'immonex-openimmo2wp' ) . ' (1)', 'debug' );
					$resumed_import_log_message = true;
					$this->_delete_all_import_properties( $dir, $cnt_properties, $token, true, $changed_or_new_property_obids );
				}

				/**
				 * Collect agency identifier elements (ANID or company as fallback
				 * on buggy XML files).
				 */
				$agencies   = $openimmo_xml_stripped->xpath( "//*[anbieter]" );
				$agency_ids = [];
				if ( ! empty( $agencies ) ) {
					$cnt = 0;
					foreach ( $agencies[0]->anbieter as $agency ) {
						$cnt++;
						if ( (string) $agency->openimmo_anid ) {
							$agency_ids[ $cnt ]['openimmo_anid'] = trim( (string) $agency->openimmo_anid );
						} elseif ( (string) $agency->firma ) {
							$agency_ids[ $cnt ]['firma'] = trim( (string) $agency->firma );
						}
					}
				}

				$openimmo_xml->rewind();

				$cnt_agency = 0;
				$cnt_property = 0;

				while ( $openimmo_xml->goto_next_element( 'immobilie,anbieter' ) ) { // main loop
					$skipped = false;

					if ( 'anbieter' === $openimmo_xml->current_element_name ) {
						$cnt_agency++;
						if ( $cnt_agency < $current_status['cnt_current_agency'] ) continue;

						$anbieter = false;
						$openimmo_anid = '';

						if ( ! empty( $agency_ids[ $cnt_agency ]['openimmo_anid'] ) ) {
							// Get "stripped" version of agency record by ANID from previously loaded XML file.
							$openimmo_anid = $agency_ids[ $cnt_agency ]['openimmo_anid'];
							$anbieter = $openimmo_xml_stripped->xpath( "//*[openimmo_anid='{$openimmo_anid}'][1]" );
							if ( -1 === $openimmo_xml->goto_next_element( 'openimmo_anid' ) ) {
								$this->log->add( __( 'Invalid XML data', 'immonex-openimmo2wp' ) . ' [1]', 'error' );
								continue;
							}
						} elseif ( ! empty( $agency_ids[ $cnt_agency ]['firma'] ) ) {
							$this->log->add(
								wp_sprintf(
									__( 'Invalid XML data: ANID missing (company: %s)', 'immonex-openimmo2wp' ),
									$agency_ids[ $cnt_agency ]['firma']
								),
								'debug'
							);
							$firma = $agency_ids[ $cnt_agency ]['firma'];
							$anbieter = $openimmo_xml_stripped->xpath( "//*[firma='{$firma}'][1]" );
							if ( -1 === $openimmo_xml->goto_next_element( 'firma' ) ) {
								$this->log->add( __( 'Invalid XML data', 'immonex-openimmo2wp' ) . ' [2]', 'error' );
								continue;
							}
						}
						if ( $anbieter && is_array( $anbieter ) ) {
							$anbieter = $anbieter[0];
						} else {
							$this->log->add( __( 'Unable to determine agency due to invalid XML data', 'immonex-openimmo2wp' ), 'error'	);
							continue;
						}

						// The following "legacy" filter hook is only relevant for retrieving agency data, not for modifying (anymore).
						$anbieter = apply_filters( $this->plugin_prefix . 'import_agency_xml_before_import', $anbieter );

						$this->current_openimmo_anid = $openimmo_anid;

						// Log current agency ONCE.
						if ( $anbieter && ! in_array( trim( (string) $anbieter->firma ), $current_status['logged_agencies'] ) )  {
							$agency_name = trim( (string) $anbieter->firma );

							$this->log->add( '--', 'debug' );
							$this->log->add( wp_sprintf( __( 'Agency: %s', 'immonex-openimmo2wp' ), $agency_name ? $agency_name : __( 'not specified', 'immonex-openimmo2wp' ) ), 'info' );
							$this->log->add( wp_sprintf( 'OpenImmo ANID: %s', $openimmo_anid ? $openimmo_anid : __( 'not specified', 'immonex-openimmo2wp' ) ), 'info' );

							$current_status['logged_agencies'][] = trim( (string) $anbieter->firma );
							$this->_save_current_import_status( array(
								'logged_agencies' => $current_status['logged_agencies']
							), true );
						}
					} elseif ( 'immobilie' === $openimmo_xml->current_element_name ) {
						$cnt_property++;

						// Skip already processed properties one by one.
						if ( $cnt_property < $cnt_start_property ) continue;

						$immobilie = $openimmo_xml->get_current_element();
						if ( -1 === $immobilie ) continue;

						do_action( $this->plugin_prefix . 'before_property_processing', $immobilie );

						// The OpenImmo OBID is (should be) a unique property identifier.
						$obid = apply_filters(
							$this->plugin_prefix . 'property_obid',
							$immobilie->verwaltung_techn->openimmo_obid ?
								trim( (string) $immobilie->verwaltung_techn->openimmo_obid ) :
								trim( (string) $immobilie->verwaltung_techn->objektnr_intern ),
							$immobilie
						);
						if ( $obid ) {
							$immobilie->verwaltung_techn->openimmo_obid = $obid;
						}

						if ( ! $cnt_start_attachment ) {
							if (
								false === $resumed_import_log_message &&
								false !== $cnt_start_property &&
								$cnt_xml_file == 0 &&
								$cnt_property == $cnt_start_property
							) {
								$this->log->add( __( 'Previous import resumed', 'immonex-openimmo2wp' ) . ' (2)', 'debug' );
							}
							$this->log->add( '--', '*' );

							$this->log->add( wp_sprintf( __( 'Property %u of %u: %s', 'immonex-openimmo2wp' ), $cnt_property, $cnt_properties, $property_infos[$cnt_property]['title'] ), 'info' );
							if ( $obid ) {
								$this->log->add( wp_sprintf( 'OpenImmo OBID: %s', $obid ), 'debug' );
							} else {
								$this->log->add( __( 'OpenImmo OBID missing, skipping property.', 'immonex-openimmo2wp' ), 'info' );
								continue;
							}

							// Apply filters to current property XML node.
							$immobilie = apply_filters( $this->plugin_prefix . 'property_xml_before_import',
								$immobilie,
								array(
									'zip_file' => $this->current_import_zip_file,
									'import_folder' => apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $this->current_import_zip_file ),
									'subdir_name' => $this->string_utils::get_plain_unzip_folder_name( $this->current_import_zip_file ),
									'unzip_dir' => $dir
								)
							);
							if ( ! $immobilie ) {
								$this->log->add( __( 'Property skipped', 'immonex-openimmo2wp' ), 'debug' );
								continue;
							}

							if ( 'REFERENZ' === (string) $immobilie->verwaltung_techn->aktion['aktionart'] ) {
								$this->log->add( __( 'Reference Property', 'immonex-openimmo2wp' ), 'debug' );
							}

							// Update current property data (for info purposes only) and attachment data in current status file/option.
							$this->_save_current_import_status( array(
								'property_title' => $property_infos[$cnt_property]['title'],
								'current_property' => wp_sprintf( __( '%s of %s (%s)', 'immonex-openimmo2wp' ), $cnt_property, $cnt_properties, $property_infos[$cnt_property]['action'] . ': ' . $property_infos[$cnt_property]['title'] ),
								'cnt_next_attachment' => 0,
								'total_cnt_attachments' => $property_infos[$cnt_property]['cnt_attachments']
							), true );
						} else {
							// Apply filters to current property XML node (resumed imports).
							$immobilie = apply_filters( $this->plugin_prefix . 'property_xml_before_import',
								$immobilie,
								array(
									'zip_file' => $this->current_import_zip_file,
									'import_folder' => apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $this->current_import_zip_file ),
									'subdir_name' => $this->string_utils::get_plain_unzip_folder_name( $this->current_import_zip_file ),
									'unzip_dir' => $dir
								)
							);
						}

						if ( $this->enable_multilang ) $translation_id = false;

						$property_action = $immobilie->verwaltung_techn->aktion['aktionart'];
						if ( ! $property_action ) $property_action = 'ADD';

						switch ( $property_action ) {
							case 'DELETE' :
								// Delete property (possibly multiple posts if translations exist).
								$this->log->add( wp_sprintf( __( 'Action: %s', 'immonex-openimmo2wp' ), __( 'Delete', 'immonex-openimmo2wp' ) ), 'info' );
								$properties = $this->get_property_by_openimmo_obid( $obid );

								$delete_result = false;

								foreach ( $properties as $property ) {
									// Delete all properties with given OpenImmo OBID - SHOULD be only one.
									if ( isset( $property->ID ) ) {
										$delete_result = $this->_delete_property( $property->ID, $property->post_title );
									}
								}

								break;
							case 'SKIP' :
								// immonex-specific special status for property elements to be skipped.
								$this->log->add( __( 'Special status SKIP recognized: skipping property.', 'immonex-openimmo2wp' ), 'debug' );
								break;
							case 'REFERENZ' :
								// Reference properties are only supported by selected themes/plugins.
								if ( ! $this->theme->supports( 'references' ) ) break;
							case 'ADD' :
							case 'CHANGE' :
							default : // Default only due to various buggy OpenImmo implementations.
								$property_locale = ! empty( $immobilie->verwaltung_techn->sprache ) ?
									str_replace( '-', '_', trim( (string) $immobilie->verwaltung_techn->sprache ) ) :
									get_locale();
								$property_language = substr( $property_locale, 0, 2 );

								if (
									$this->enable_multilang &&
									is_array( $languages )
								) {

									if ( in_array( $property_language, $languages ) ) {
										// Valid property language: Change the current import language accordingly.
										do_action( "{$this->plugin_prefix}set_current_import_language", $property_language );

										// Switch the current user locale to the property locale during import.
										switch_to_locale( $property_locale );
									} elseif ( self::SKIP_PROPERTIES_UNAVAILABLE_LANGUAGES ) {
										$this->log->add( wp_sprintf( __( 'Language not available (property skipped): %s', 'immonex-openimmo2wp' ), $property_language ), 'debug' );
										$skipped = true;
										break;
									}
								}

								if ( ! $cnt_start_attachment ) {
									do_action( $this->plugin_prefix . 'before_property_import', $immobilie );

									// Add or update property.
									$this->log->add( wp_sprintf( __( 'Action: %s', 'immonex-openimmo2wp' ), __( 'New/Update', 'immonex-openimmo2wp' ) ), 'info' );

									/**
									 * Reset or delete existing property first (on partial AND full imports to
									 * prevent duplicates caused by server errors). Exception: Full import with mode
									 * "delete_part_update_changed" enabled.
									 */
									$keep_property_id = false;
									$keep_property_status = false;
									$properties = $this->get_property_by_openimmo_obid( $obid );

									if ( isset( $properties[0]->ID ) ) {
										if ( $this->is_debug() ) {
											$this->log->add( "got property ID {$properties[0]->ID}", 'debug' );
										}

										if (
											! $cnt_start_attachment &&
											'full' === $import_type &&
											'delete_part_update_changed' === $this->plugin_options['full_import_mode'] // Update changed properties only.
										) {
											$new_property_date_ts = apply_filters( "{$this->plugin_prefix}property_last_update_ts", false, $immobilie );
											$property_time_cmp = $this->property_time->compare_update_times( $new_property_date_ts, $properties[0]->post_date );

											if ( ! $property_time_cmp['is_newer'] ) {
												$this->log->add(
													wp_sprintf(
														__( 'The last property update time (%s) is before or equal to the existing property\'s (%s): skipping import/update.', 'immonex-openimmo2wp' ),
														$property_time_cmp['cmp_new'],
														$property_time_cmp['cmp_existing']
													),
													'info'
												);
												$skipped = true;
												break;
											}
										}

										// Reset the property to be updated and remember its ID, keep post type, MIME type (backup location of OpenImmo OBID),
										// title, name and publishing date as well as unchanged attachments.
										$keep_property_id = $properties[0]->ID;

										// Check if the attachment order has changed in comparison with the exiting property.
										$existing_property_xml_source = get_post_meta( $keep_property_id, '_immonex_property_xml_source', true );
										if ( $existing_property_xml_source ) {
											$existing_property = new \SimpleXMLElement( $existing_property_xml_source );
											$existing_property_attachment_list = Attachment_Utils::get_list( $existing_property );
											$current_property_attachment_list = Attachment_Utils::get_list( $immobilie );
											$same_attachment_order = Attachment_Utils::is_same_order( $existing_property_attachment_list, $current_property_attachment_list );

											if ( $this->is_debug() ) {
												$this->log->add( "checked existing attachments", 'debug' );
											}
										} else {
											$same_attachment_order = false;
										}

										if ( ! $same_attachment_order ) {
											// Reset all attachments if order has changed.
											$this->log->add( __( 'Resetting all attachments due to changed order.', 'immonex-openimmo2wp' ) . ' [1]', 'debug' );
											$reset_all_attachments = true;
										} else {
											$reset_all_attachments = false;
										}

										$reset_attachment_data = $this->_get_property_attachments_to_reset( $keep_property_id, $immobilie->anhaenge, dirname( $xml_file ), $reset_all_attachments );
										if ( $this->is_debug() ) {
											$this->log->add( "determined attachments to reset", 'debug' );
										}

										$keep_featured_image = in_array( get_post_meta( $keep_property_id, '_thumbnail_id', true ), $reset_attachment_data['keep'] );
										$reset_post_special_args = apply_filters( $this->plugin_prefix . 'reset_post_special_args', array(
											'delete_attachment_ids' => $reset_attachment_data['delete'],
											'keep_featured_image' => $keep_featured_image
										) );

										// Save attachment reset IDs as temporary option for later use.
										$this->temp_options->update( 'reset_attachment_data', $reset_attachment_data, $this->current_import_xml_file, strtotime( '+1 hour' ) );

										$overwrite_post_defaults = apply_filters( $this->plugin_prefix . 'reset_post_overwrite_defaults', array(
											'post_type' => $this->property_post_type,
											'post_mime_type' => $properties[0]->post_mime_type,
											'post_title' => $properties[0]->post_title,
											'post_name' => $properties[0]->post_name,
											'post_date' => $properties[0]->post_date
										), $properties[0] );
										$reset_post_exclude_meta = apply_filters( $this->plugin_prefix . 'reset_post_exclude_meta', array(
											'_is_immonex_import_property',
											'_openimmo_obid',
											'_immonex_import_folder',
											'_immonex_is_reference',
											'_immonex_translation_id'
										) );
										$reset_post_taxonomies = apply_filters( $this->plugin_prefix . 'reset_post_taxonomies', get_taxonomies() );
										$reset_post_id = $this->general_utils->reset_post( $keep_property_id, $reset_post_taxonomies, $overwrite_post_defaults, $reset_post_exclude_meta, $reset_post_special_args );
										if ( $this->is_debug() ) {
											$this->log->add( "resetted post", 'debug' );
										}

										if ( ! is_wp_error( $reset_post_id ) ) {
											$this->log->add( wp_sprintf( __( 'Keeping Property ID: %s', 'immonex-openimmo2wp' ), $keep_property_id ), 'debug' );

											if ( in_array( $this->plugin_options['review_imported_properties'], array( 'none', 'new' ) ) ) {
												// Keep the property status on updates if properties to be updated shall NOT be reviewed.
												$keep_property_status = $properties[0]->post_status;
												if ( 'publish' !== $keep_property_status ) $this->log->add( wp_sprintf( __( 'Keeping Property Status: %s', 'immonex-openimmo2wp' ), $keep_property_status ), 'debug' );
											}
										} else {
											// Existing property post could not be resetted: delete it.
											$this->log->add( wp_sprintf( __( 'Error resetting property post (%1$s), ID: %2$s. Deleting it.', 'immonex-openimmo2wp' ), $reset_post_id->get_error_message(), $keep_property_id ), 'debug' );
											$this->_delete_property( $keep_property_id, $property_infos[$cnt_property]['title'], 'debug' );
											$keep_property_id = false;
										}
									}

									// Get property data that will be stored in the post record.
									$post_raw = $this->_get_property_post_data( $immobilie, $cnt_property, $keep_property_status );
									if ( ! $post_raw ) {
										$skipped = true;
										break;
									}

									// Apply filters to property post data.
									$post = apply_filters( $this->plugin_prefix . 'add_property_post_data', $post_raw, $immobilie );

									if ( $this->enable_multilang ) {
										$this->log->add( wp_sprintf( __( 'Property language: %s', 'immonex-openimmo2wp' ), $property_language ), 'debug' );
									}

									if ( $post ) {
										if ( false !== $keep_property_id ) {
											// Update an existing property post.
											$post_id = wp_update_post( array_merge( array( 'ID' => $keep_property_id ), $post ), true );
										} else {
											// Create a new property post.
											$post_id = wp_insert_post( $post, true );
										}
									}

									if ( $post_id && ! is_wp_error( $post_id ) ) {
										if ( false !== $keep_property_id ) {
											$this->log->add( wp_sprintf( __( 'Property updated, Post ID: %s', 'immonex-openimmo2wp' ), $post_id ), 'debug' );
											add_post_meta( $post_id, '_immonex_post_status', 'updated' );
										} else {
											$this->log->add( wp_sprintf( __( 'Property created, Post ID: %s', 'immonex-openimmo2wp' ), $post_id ), 'debug' );
											add_post_meta( $post_id, '_immonex_post_status', 'new' );
										}

										// Save global/user import folder information for each property.
										$property_import_folder = apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $this->current_import_zip_file );
										add_post_meta( $post_id, '_immonex_import_folder', addslashes( $property_import_folder ), true );
									}
								} else {
									// Resume importing of images/attachments first: Get already existing property.
									$properties = $this->get_property_by_openimmo_obid( $obid, true );
									if ( count( $properties ) > 0 ) $post_id = $properties[0]->ID; else $post_id = false;
									$this->log->add( wp_sprintf( __( 'Resuming image/attachment import, Post ID: %s', 'immonex-openimmo2wp' ), $post_id ? $post_id : __( 'not available', 'immonex-openimmo2wp' ) ), 'debug' );
								}

								if ( $post_id && ! is_wp_error( $post_id ) ) {
									if ( ! $cnt_start_attachment ) {
										if ( $cnt_property <= $cnt_properties ) {
											// Update next property number, total property count and current status
											// file/option immediately after post has been saved.
											$this->_save_current_import_status( array(
												'cnt_next_property' => $cnt_property,
												'total_cnt_properties' => $cnt_properties
											), true );
										}

										// Get property attributes that will be stored as taxonomies.
										$terms = $this->_get_property_taxonomy_data( $immobilie, $post_id );
										if ( count( $terms ) > 0 ) {
											foreach ( $terms as $term ) {
												// Insert a taxonomy term for the current property.
												$result = wp_set_object_terms( $post_id, (int) $term['term_id'], $term['taxonomy'], true );
												if ( is_wp_error( $result ) ) {
													$this->log->add(
														wp_sprintf(
															__( 'Error on saving taxonomy data (%s): %s', 'immonex-openimmo2wp' ),
															$term['taxonomy'],
															$result->get_error_message()
														),
														'error'
													);
												}
											}
										}

										// Get property attributes that will be stored as custom fields (attribute-value pairs).
										$custom_fields = $this->_get_property_custom_fields( $immobilie, $post_id );
										if ( count( $custom_fields ) > 0 ) {
											$unique_fields = array();
											$grouped_custom_fields = array();

											foreach ( $custom_fields as $field ) {
												if ( $field['unique'] ) {
													// Add a unique custom field for the current property.
													if (
														( isset( $field['join_multiple_values'] ) && $field['join_multiple_values'] ) &&
														isset( $unique_fields[$field['meta_key']] )
													) {
														if (
															trim( $unique_fields[$field['meta_key']] ) &&
															trim( $field['meta_value'] )
														) {
															$unique_fields[$field['meta_key']] .= $field['join_divider'] . $field['meta_value'];
															// Remove comma before values that look like street numbers.
															$temp_value = preg_replace( '/,\ ([0-9\-\ ]{1,3}(\ |,|\z))/', ' $1$2', $unique_fields[$field['meta_key']] );
															// Remove comma after values that look like zip codes.
															$unique_fields[$field['meta_key']] = preg_replace( '/([0-9]{4,5}),/', '$1', $temp_value );
														}
													} else {
														$unique_fields[$field['meta_key']] = $field['meta_value'];
													}
												} else {
													// Other custom data ("meta groups") will be saved as serialized array(s) later.
													$field_group = trim( $field['mapping_destination'] ) ? trim( $field['mapping_destination'] ) : '_immonex_custom_fields';

													if ( $field['mapping_parent'] )
														$key = $field['mapping_parent'];
													elseif ( $field['meta_key'] != $field_group )
														$key = $field['meta_key'];
													else
														$key = uniqid();

													if (
														$field['join_multiple_values'] &&
														isset( $grouped_custom_fields[$field_group][$key] )
													) {
														if (
															trim( $grouped_custom_fields[$field_group][$key]['value'] ) &&
															trim( $field['meta_value'] )
														) {
															$grouped_custom_fields[$field_group][$key]['value'] .= $field['join_divider'] . $field['meta_value'];
															$grouped_custom_fields[$field_group][$key]['value_before_filter'] .= $field['join_divider'] . $field['meta_value_before_filter'];

															// Remove comma before values that look like street numbers.
															$temp_value = preg_replace( '/,\ ([0-9\-\ ]{1,3}(\ |,|\z))/', ' $1$2', $grouped_custom_fields[$field_group][$key]['value']);
															$temp_value_before_filter = preg_replace( '/,\ ([0-9\-\ ]{1,3})(,)?/', ' $1$2', $grouped_custom_fields[$field_group][$key]['value_before_filter']);

															// Remove comma after values that look like zip codes.
															$grouped_custom_fields[$field_group][$key]['value'] = preg_replace( '/([0-9]{4,5}),/', '$1', $temp_value );
															$grouped_custom_fields[$field_group][$key]['value_before_filter'] = preg_replace( '/([0-9]{4,5}),/', '$1', $temp_value_before_filter );
														}
													} else {
														$grouped_custom_fields[$field_group][$key] = array(
															'mapping_source' => $field['mapping_source'],
															'name' => $field['meta_name'],
															'group' => $field['meta_group'],
															'value' => $field['meta_value'],
															'value_before_filter' => $field['meta_value_before_filter']
														);
													}
												}

												// Remember translation ID for linking translations later.
												if ( $this->enable_multilang && '_immonex_translation_id' === $field['meta_key'] ) $translation_id = trim( $field['meta_value'] );
											}

											if ( $this->enable_multilang && ( ! isset( $translation_id ) || ! $translation_id ) ) {
												// Translation ID hasn't been set, generate a random one and add it as unique custom field.
												$translation_id = uniqid();
												$unique_fields['_immonex_translation_id'] = $translation_id;
											}

											if ( ! isset( $unique_fields['_immonex_is_reference'] ) ) {
												$unique_fields['_immonex_is_reference'] = get_post_meta( $post_id, '_immonex_is_reference', true );
												if ( ! $unique_fields['_immonex_is_reference'] ) {
													$unique_fields['_immonex_is_reference'] = ( 'REFERENZ' === (string) $immobilie->verwaltung_techn->aktion['aktionart'] ? 1 : 0 ) ||
														(
															! empty( $immobilie->xpath( '//verwaltung_objekt/user_defined_simplefield[@feldname="referenz"]' ) ) &&
															in_array(
																strtolower( (string) $immobilie->xpath( '//verwaltung_objekt/user_defined_simplefield[@feldname="referenz"]' )[0] ), // onOffice style
																array( '1', 'true' )
															)
														) ||
														(
															! empty( $immobilie->xpath( '//verwaltung_techn/user_defined_simplefield[@feldname="showAsReference"]' ) ) &&
															in_array(
																strtolower( (string) $immobilie->xpath( '//verwaltung_techn/user_defined_simplefield[@feldname="showAsReference"]' )[0] ), // Flowfact style
																array( '1', 'true' )
															)
														);
												}
											}

											if ( ! isset( $unique_fields['_immonex_is_sold'] ) ) {
												$unique_fields['_immonex_is_sold'] = ( 'VERKAUFT' === (string) $immobilie->zustand_angaben->verkaufstatus['stand'] ? 1 : 0 ) ||
													(
														! empty( $immobilie->xpath( '//verwaltung_objekt/user_defined_simplefield[@feldname="verkauft"]' ) ) &&
														in_array(
															strtolower( (string) $immobilie->xpath( '//verwaltung_objekt/user_defined_simplefield[@feldname="verkauft"]' )[0] ), // onOffice style
															array( '1', 'true' )
														)
													);
											}

											if ( ! isset( $unique_fields['_immonex_is_reserved'] ) ) {
												$unique_fields['_immonex_is_reserved'] = ( 'RESERVIERT' === (string) $immobilie->zustand_angaben->verkaufstatus['stand'] ? 1 : 0 ) ||
													(
														! empty( $immobilie->xpath( '//verwaltung_objekt/user_defined_simplefield[@feldname="reserviert"]' ) ) &&
														in_array(
															strtolower( (string) $immobilie->xpath( '//verwaltung_objekt/user_defined_simplefield[@feldname="reserviert"]' )[0] ), // onOffice style
															array( '1', 'true' )
														)
													);
											}

											if ( ! isset( $unique_fields['_immonex_is_available'] ) ) {
												if (
													$unique_fields['_immonex_is_reference'] ||
													$unique_fields['_immonex_is_sold'] ||
													$unique_fields['_immonex_is_reserved']
												) {
													$unique_fields['_immonex_is_available'] = 0;
												} else {
													$unique_fields['_immonex_is_available'] = 1;
												}
											}

											Integrations\AreaButler::add_ab_fields( $immobilie, $this->plugin_prefix, $unique_fields );

											if ( count( $unique_fields ) > 0 ) {
												foreach ( $unique_fields as $meta_key => $meta_value ) {
													// Save unique custom field one by one.
													update_post_meta( $post_id, $meta_key, $meta_value );
												}
											}

											/**
											 * Process grouped meta data (custom fields saved as serialized array).
											 */
											if ( count( $grouped_custom_fields ) > 0 ) {
												foreach ( $grouped_custom_fields as $meta_key => $grouped_meta_data ) {
													$grouped_meta_data = apply_filters( $this->plugin_prefix . 'add_grouped_post_meta', $grouped_meta_data, $post_id, $meta_key, $immobilie );
													// Save custom fields array (including information about mapping group data etc.).
													if ( $grouped_meta_data ) add_post_meta( $post_id, $meta_key, $grouped_meta_data, true );
												}
											}
										}

										if (
											! $keep_property_status &&
											isset( $unique_fields['_immonex_group_master'] ) &&
											'invisible' === $unique_fields['_immonex_group_master']
										) {
											// Set status "pending" for group master properties that should not be visible.
											$post_id = wp_update_post( array( 'ID' => $post_id, 'post_status' => 'pending' ), true );
											$this->log->add( __( 'Group master property transferred as "not visible": post status set to "pending".', 'immonex-openimmo2wp' ), 'debug' ) ;
										}

										if ( $this->enable_multilang ) {
											$this->log->add( wp_sprintf( __( 'Translation ID (%s): %s', 'immonex-openimmo2wp' ), strtoupper( $this->current_import_language ), $translation_id ), 'debug' ) ;
											// Set property post language and link translations.
											do_action( $this->plugin_prefix . 'multilang_set_property_language', $post_id, $translation_id, $this->current_import_language );
										}

										// Save the property's XML source.
										$property_xml_source = $immobilie->asXML();
										add_post_meta( $post_id, '_immonex_property_xml_source', $property_xml_source, true );
										do_action( $this->plugin_prefix . 'property_xml_source_saved', $post_id, $property_xml_source );

										// Save geo coordinates in generic custom fields.
										if ( ! empty( (string) $immobilie->geo->geokoordinaten['breitengrad'] ) ) {
											$lat = (string) $this->geo_utils->validate_coords( (string) $immobilie->geo->geokoordinaten['breitengrad'], 'lat' );

											if ( $lat && ! get_post_meta( $post_id, '_immonex_real_lat', true ) ) {
												update_post_meta( $post_id, '_immonex_real_lat', $lat );
											}
											if ( $lat && ! get_post_meta( $post_id, '_immonex_lat', true ) ) {
												update_post_meta( $post_id, '_immonex_lat', $lat );
											}
										}
										if ( ! empty( (string) $immobilie->geo->geokoordinaten['laengengrad'] ) ) {
											$lng = (string) $this->geo_utils->validate_coords( (string) $immobilie->geo->geokoordinaten['laengengrad'], 'lng' );

											if ( $lng && ! get_post_meta( $post_id, '_immonex_real_lng', true ) ) {
												update_post_meta( $post_id, '_immonex_real_lng', $lng );
											}
											if ( $lng && ! get_post_meta( $post_id, '_immonex_lng', true ) ) {
												update_post_meta( $post_id, '_immonex_lng', $lng );
											}
										}
									}

									// Save the property's energy class (+ ID) in a dedicated custom field, if available.
									$this->_add_energy_class( $post_id, $immobilie );

									// Import property attachments (if available).
									$cnt_attachments = isset( $immobilie->anhaenge ) ? count( $immobilie->anhaenge->anhang ) : 0;
									if ( $this->is_debug() ) {
										$this->log->add( "cnt_attachments {$cnt_attachments} / cnt_start_attachment {$cnt_start_attachment}", 'debug' );
									}
									if ( $cnt_start_attachment <= $cnt_attachments ) {
										if ( ! isset( $reset_attachment_data ) || ! $reset_attachment_data ) {
											// Load temporary option containing IDs of attachments that already exist.
											$reset_attachment_data = $this->temp_options->get( 'reset_attachment_data', $this->current_import_xml_file );
										}

										if ( ! empty( $immobilie->anhaenge ) ) {
											$this->_process_property_attachments( $post_id, $immobilie->anhaenge, dirname( $xml_file ), $cnt_property, $cnt_properties, $cnt_start_attachment, $token, $current_status, $reset_attachment_data );
										}
									}

									// Delete variable and option holding attachment reset data.
									$reset_attachment_data = false;
									$delete_result = $this->temp_options->delete( 'reset_attachment_data' );

									do_action( $this->plugin_prefix . 'property_imported', $post_id, $immobilie );
								} else {
									$this->log->add( wp_sprintf( __( 'Property post could not be saved%s', 'immonex-openimmo2wp' ), is_wp_error( $post_id ) ? ': ' . $post_id->get_error_message() : '' ), 'fatal' );
									if ( isset( $post ) ) $this->log->add( serialize( $post ), 'fatal' );
									global $wpdb;
									if ( isset( $wpdb->last_error ) && $wpdb->last_error ) $this->log->add( wp_sprintf( __( 'MySQL error: %s', 'immonex-openimmo2wp' ), $wpdb->last_error ), 'debug' );
								}
						}

						if ( ! $skipped ) {
							if ( $this->is_debug() ) {
								$this->log->add( '--', 'debug' );
								$this->log->add( wp_sprintf( __( 'Current Execution Time: %u s', 'immonex-openimmo2wp' ), $this->process_resources->get_exec_time() ), 'debug' );
								$this->log->add( wp_sprintf( __( 'Current Memory Usage: %s MB', 'immonex-openimmo2wp' ), number_format( memory_get_usage( true ) / 1048576, 2, ',', '.' ) ), 'debug' );
								$this->log->add( wp_sprintf( __( 'Memory Peak Usage (allocated): %s MB', 'immonex-openimmo2wp' ), number_format( memory_get_peak_usage( false ) / 1048576, 2, ',', '.' ) ), 'debug' );
								$this->log->add( wp_sprintf( __( 'Memory Peak Usage (real): %s MB', 'immonex-openimmo2wp' ), number_format( memory_get_peak_usage( true ) / 1048576, 2, ',', '.' ) ), 'debug' );
							}

							$this->current_property_insert_count++;
						}

						$cnt_start_attachment = 0;

						if ( $cnt_property < $cnt_properties ) {
							// Replace the current property data with the data of the NEXT property.
							$this->_save_current_import_status( array(
								'property_title' => isset( $property_infos[$cnt_property + 1] ) ? $property_infos[$cnt_property + 1]['title'] : __( 'not available yet', 'immonex-openimmo2wp' ),
								'current_property' => wp_sprintf( __( '%s of %s (%s)', 'immonex-openimmo2wp' ), $cnt_property + 1, $cnt_properties, isset( $property_infos[$cnt_property + 1] ) ? $property_infos[$cnt_property + 1]['action'] . ': ' . $property_infos[$cnt_property + 1]['title'] : __( 'not available yet', 'immonex-openimmo2wp' ) ),
								'cnt_next_property' => $cnt_property + 1,
								'cnt_next_attachment' => 0,
								'total_cnt_attachments' => isset( $property_infos[$cnt_property + 1] ) ? $property_infos[$cnt_property + 1]['cnt_attachments'] : 0,
								'cnt_current_agency' => $cnt_agency
							), true );

							// Check script execution time after every property, restart if necessary.
							$this->_check_script_resources( $dir, $cnt_property + 1, $cnt_properties, $cnt_start_attachment, isset( $property_infos[$cnt_property + 1] ) ? $property_infos[$cnt_property + 1]['cnt_attachments'] : 0, $token );
						}
					}

				} // main loop

				// Remember processed XML file.
				$this->current_processed_xml_files[] = $xml_file;

				// Reset current XML file status values.
				$cnt_start_property = false;
				$this->_save_current_import_status( array(
					'import_type' => '',
					'property_title' => '',
					'current_property' => '',
					'cnt_next_property' => 0,
					'total_cnt_properties' => 0,
					'cnt_next_attachment' => 0,
					'total_cnt_attachments' => 0,
					'processed_xml_files' => $this->current_processed_xml_files
				), true );

				// Delete the current OBID and property info temporary option.
				$this->temp_options->delete( array( 'property_infos', 'changed_obids' ) );

				$cnt_xml_file++;

				do_action( $this->plugin_prefix . 'import_file_processed', basename( $xml_file ) );
			}

		} else {
			$this->log->add( __( 'No XML files found!', 'immonex-openimmo2wp' ), 'debug' );
		}

		do_action( $this->plugin_prefix . 'import_process_finished', $dir );
	} // _import

	/**
	 * Check script execution time and stop/restart if necessary.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param string $dir Currently processed import directory.
	 * @param int $cnt_property Number of currently/last processed property.
	 * @param int $cnt_properties Total number of properties in the current XML file.
	 * @param int $cnt_attachment Number of last processed attachment.
	 * @param int $cnt_attachments Total number of attachments (current property).
	 * @param string $token Token of current import process.
	 */
	private function _check_script_resources( $dir, $cnt_property, $cnt_properties, $cnt_attachment, $cnt_attachments, $token ) {
		$max_properties_to_delete_per_run = $this->process_resources->get_num_of_properties_to_be_deleted();

		if (
			(
				! empty( $this->plugin_options['max_script_run_property_cnt'] ) &&
				$this->current_property_insert_count >= $this->plugin_options['max_script_run_property_cnt'] &&
				$cnt_property <= $cnt_properties
			) || (
				$this->current_deleted_properties_count >= $max_properties_to_delete_per_run
			) || (
				! empty( $this->plugin_options['max_script_run_attachment_cnt'] ) &&
				$this->current_processed_attachments_count + 1 > $this->plugin_options['max_script_run_attachment_cnt']
			) || $this->process_resources->exec_time_expired()
		) {
			/**
			 * Max. script execution time OR max. record/attachment count reached: save current import status and stop here.
			 */
			$pending_files = apply_filters( "{$this->plugin_prefix}import_zip_files", array(), false, 'status', true );

			$status_update = array(
				'token' => $token,
				'status' => 'processing',
				'dir' => $dir,
				'dir_basename' => basename( $dir ),
				'cnt_next_property' => $cnt_property,
				'total_cnt_properties' => $cnt_properties,
				'cnt_next_attachment' => $cnt_attachment,
				'total_cnt_attachments' => $cnt_attachments,
				'processed_xml_files' => $this->current_processed_xml_files,
				'pending' => $pending_files,
				'cnt_pending_files' => count( $pending_files )
			);

			$this->_save_current_import_status( $status_update, true );
			// Reload status data for inclusion of additional status infos.
			$status = $this->_get_current_import_status();

			$this->log->add( '--', 'debug' );
			$this->log->add( __( 'Import is being interrupted and resumed due to script resources.', 'immonex-openimmo2wp' ), 'debug' );
			if ( $this->killswitch ) {
				$this->log->add( wp_sprintf(
					__( 'NO automatic resumption due to KILLSWITCH! (%s)', 'immonex-openimmo2wp' ),
					date_i18n( 'H:i:s', $this->killswitch )
				), 'debug' );
			}
			$this->log->add( '--', 'debug' );

			if ( $this->killswitch ) {
				exit;
			}

			if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
				// Import process is controlled by AJAX calls: Return current status data...
				echo json_encode( $status );
			} elseif ( $this->is_wpcron_process || $this->is_immonex_cron_process ) {
				// ...or hand over (or continue) the process to immonex Cron...
				$immonex_cron_url = site_url();
				$immonex_cron_params = array(
					'immonex_cron' => 1,
					'action' => 'import',
					'token' => $token
				);
				wp_remote_get( add_query_arg( $immonex_cron_params, $immonex_cron_url ), array( 'timeout' => 30, 'blocking' => true, 'sslverify' => apply_filters( 'https_local_ssl_verify', true ) ) );
			} else {
				// ...or restart the script using a self redirect if NOT started via WP-Cron.
				$protocol = ( ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 ) ? 'https://' : 'http://';
				$location = $protocol . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
				if ( false === strpos( $location, 'action=' ) ) {
					$location .= ( false === strpos( $location, '?' ) ? '?' : '&' ) . 'action=import';
				}
				if ( false === strpos( $location, 'token=' ) ) {
					$location .= ( false === strpos( $location, '?' ) ? '?' : '&' ) . 'token=' . $token;
				}
				wp_redirect( $location );
			}

			exit;
		}
	} // _check_script_resources

	/**
	 * Save current import status data.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param array $data Current status data.
	 * @param bool $update Update existing data?
	 *
	 * @return New/Updated status data.
	 */
	private function _save_current_import_status( $status_data, $update = false ) {
		if ( $this->compat_flags['status_as_file'] ) {
			if ( $update ) {
				// Update existing data with given array.
				if ( file_exists( $this->current_status_file ) ) {
					$status = unserialize( file_get_contents( $this->current_status_file ) );

					if ( is_array( $status ) ) {
						$status_data = array_merge( $status, $status_data );
					}
				}
			}

			$f = fopen( $this->current_status_file, 'w' );
			fwrite( $f, serialize( $status_data ) );
			fclose( $f );
		} else {
			if ( $update ) {
				// Update existing data with given array.
				$status = $this->temp_options->get( $this->current_status_option, $this->current_import_xml_file, true );

				if ( is_array( $status ) ) {
					$status_data = array_merge( $status, $status_data );
				}
			}

			$status_data['last_update'] = time();
			$this->temp_options->update( $this->current_status_option, $status_data, $this->current_import_xml_file );
		}

		return $status_data;
	} // _save_current_import_status

	/**
	 * Check if a running import is to be resumed.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @return array|bool Current status data or false if no import is running.
	 */
	private function _get_current_import_status() {
		$stall_check_time = $this->process_resources->get_stall_check_time();

		if ( $this->compat_flags['status_as_file'] ) {
			if ( ! file_exists( $this->current_status_file ) ) return false;

			$status = unserialize( file_get_contents( $this->current_status_file ) );

			if ( $status && isset( $status['dir'] ) && isset( $status['token'] ) ) {
				if ( $this->_get_mtime( $this->current_status_file ) < strtotime( $stall_check_time ) ) {
					// Maybe the script crashed in a previous run: Reset the token if the status file
					// is older than the given timeframe.
					unset( $status['token'] );
				}

			}
		} else {
			$status = $this->temp_options->get( $this->current_status_option, $this->current_import_xml_file, true );
			if ( ! $status ) return false;

			if (
				isset( $status['last_update'] ) &&
				(int) $status['last_update'] < strtotime( $stall_check_time )
			) {
				/**
				 * Maybe the script crashed in a previous run: Reset the token if the
				 * last update of the status option is longer ago than the given timeframe.
				 */
				unset( $status['token'] );
			}
		}

		if ( ! empty( $status['folder'] ) && ! empty( $status['file'] ) ) {
			do_action(
				"{$this->plugin_prefix}import_zip_file_in_processing",
				array(
					'folder' => $status['folder'],
					'file' => $status['file']
				)
			);
		}

		if ( ! isset( $status['current_property'] ) ) $status['current_property'] = '';

		if (
			isset( $status['cnt_next_attachment'] ) &&
			isset( $status['total_cnt_attachments'] ) &&
			$status['cnt_next_attachment'] > $status['total_cnt_attachments']
		) {
			$current_attachment_cnt = $status['total_cnt_attachments'];
		} else {
			$current_attachment_cnt = $status['cnt_next_attachment'];
		}

		$status['current_attachment'] = ( $status['cnt_next_property'] > 0 && trim( $status['property_title'] ) ) &&
			$status['total_cnt_attachments'] ?
			wp_sprintf(
				__( '%s of %s', 'immonex-openimmo2wp' ),
				$current_attachment_cnt > 0 ? $current_attachment_cnt : 1,
				$status['total_cnt_attachments']
			) :	'-';

		return $status;
	} // _get_current_import_status

	/**
	 * Return status info contents used on the options page (manual imports).
	 *
	 * @since 5.0.0
	 *
	 * @param string|bool $key Array key or false for all contents (default).
	 *
	 * @return string|string[] Content string(s).
	 */
	private function _get_option_page_status_contents( $key = false ) {
		$contents = array(
			'dialog_texts' => array(
				'start_import' => __( 'Start Import', 'immonex-openimmo2wp' ),
				'resume_import' => __( 'Resume Import', 'immonex-openimmo2wp' ) . ' *',
				'import_running' => __( 'Import running...', 'immonex-openimmo2wp' ) . ' *',
				'import_stopped' => __( 'Import stopped!', 'immonex-openimmo2wp' ),
				'import_aborted' => __( 'Import aborted!', 'immonex-openimmo2wp' ),
				'processing' => __( 'Processing import files...', 'immonex-openimmo2wp' ),
				'full_import' => __( 'full import', 'immonex-openimmo2wp' ),
				'partial_import' => __( 'partial import', 'immonex-openimmo2wp' ),
				'success_message' => __( 'Import completed successfully', 'immonex-openimmo2wp' ),
				'error_message' => __( 'Errors during import', 'immonex-openimmo2wp' ) . ' ' .
					'<a href="https://docs.immonex.de/openimmo2wp/#/optimierung-problemloesung/manueller-openimmo-import" class="dashicons-before dashicons-info" target="_blank" aria-label="Info"></a>',
				'fatal_error_invalid_status' => __( 'Fatal Error: Invalid Status', 'immonex-openimmo2wp' ) . ' ' .
					'<a href="https://docs.immonex.de/openimmo2wp/#/optimierung-problemloesung/manueller-openimmo-import?id=serverfehler-server-error" class="dashicons-before dashicons-info" target="_blank" aria-label="Info"></a>',
				'server_error' => __( 'Server Error', 'immonex-openimmo2wp' ) . ' ' .
					'<a href="https://docs.immonex.de/openimmo2wp/#/optimierung-problemloesung/manueller-openimmo-import?id=schwerwiegender-fehler-ung%c3%bcltiger-status-fatal-error-invalid-status" class="dashicons-before dashicons-info" target="_blank" aria-label="Info"></a>',
				'property' => __( 'Property', 'immonex-openimmo2wp' ),
				'attachment' => __( 'Attachment', 'immonex-openimmo2wp' ),
				'resumption_prompt' => __( 'Another import process is already running, force resumption now?', 'immonex-openimmo2wp' ),
				'resume_info' => wp_sprintf(
					__( 'If the import process seems to be interrupted, it can be resumed <strong>%d minutes</strong> after the last status change.', 'immonex-openimmo2wp' ),
					$this->process_resources->get_stall_check_time( true )
				),
				'no_pending_files_info' => wp_sprintf(
					__( 'Currently there are no import files pending processing.<br><br>Transfer <strong>OpenImmo-XML ZIP archives</strong> either to the <strong>global</strong> (single realtor agency) or <strong>user-based</strong> (multiple agencies/sources) import folders listed below (&rarr; <a href="%s" target="_blank">Documentation</a>).', 'immonex-openimmo2wp' ),
					'https://docs.immonex.de/openimmo2wp/#/installation-einrichtung/uebertragung'
				)
			),
			'spinner_image_url' => plugins_url( 'assets/wpspin_light.gif', $this->plugin_main_file ),
			'spinner_image' => wp_sprintf (
				'<img src="%s" alt="Spinner">',
				plugins_url( 'assets/wpspin_light.gif', $this->plugin_main_file )
			)
		);

		return $key && isset( $contents[ $key ] ) ? $contents[ $key ] : $contents;
	} // _get_option_page_status_contents

	/**
	 * Delete the current import status and related temporary data files/options.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param array $data Current status data.
	 */
	private function _delete_current_import_status() {
		if ( $this->compat_flags['status_as_file'] ) {
			if ( $this->wp_filesystem->delete( $this->current_status_file ) ) {
				$this->log->add( wp_sprintf( __( 'Current status file deleted: %s', 'immonex-openimmo2wp' ), basename( $this->current_status_file ) ), 'debug' );
			} else {
				$this->log->add( wp_sprintf( __( 'Current status file could not be deleted: %s', 'immonex-openimmo2wp' ), basename( $this->current_status_file ) ), 'error' );
			}
		} else {
			$delete_result = $this->temp_options->delete( $this->current_status_option );

			if ( $delete_result ) {
				$this->log->add( wp_sprintf( __( 'Current status option deleted: %s', 'immonex-openimmo2wp' ), $this->current_status_option ), 'debug' );
			} else {
				$this->log->add( wp_sprintf( __( 'Current status option could not be deleted: %s', 'immonex-openimmo2wp' ), $this->current_status_option ), 'error' );
			}

			// Delete the current OBID and property info temporary options etc.
			$this->temp_options->delete( array( 'property_infos', 'changed_obids', 'reset_attachment_data' ) );
			$this->wp_filesystem->delete( trailingslashit( apply_filters( "{$this->plugin_prefix}working_dir", '' ) ) . \immonex\OpenImmo2Wp\themes\Theme_Base::THEME_TEMP_FILENAME );
		}
	} // _delete_current_import_status

	/**
	 * Delete all or selected (based on full import mode) properties that have been imported by this plugin.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param string $dir Temporary import directory.
	 * @param int $cnt_properties Number or property records in the current import file.
	 * @param string $token Token of current import process.
	 * @param bool $resumed_import Is this a resumed import?
	 * @param string[] $changed_or_new_property_obids Array of property OpenImmo IDs with
	 *   status "CHANGE".
	 */
	private function _delete_all_import_properties( $dir, $cnt_properties, $token, $resumed_import = false, $changed_or_new_property_obids = array() ) {
		if ( false === $resumed_import ) {
			$this->log->add( '--', '*' );
			$this->log->add( __( 'Existing properties are being deleted.', 'immonex-openimmo2wp' ), 'info' );
		}

		$property_import_folder = apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $this->current_import_zip_file );
		$meta_query = array(
			array(
				'key' => '_immonex_import_folder',
				'value' => $property_import_folder
			)
		);

		$max_properties_to_delete_per_run = $this->process_resources->get_num_of_properties_to_be_deleted();

		$args = array(
			'post_type' => $this->property_post_type ? $this->property_post_type : self::DEFAULT_PROPERTY_POST_TYPE,
			'post_status' => array( 'publish', 'draft', 'pending', 'future', 'private' ),
			'posts_per_page' => Process_Resources::MAX_SCRIPT_RUN_DELETED_PROPERTIES_CNT,
			'meta_query' => $meta_query
		);
		if ( $this->enable_multilang ) $args['lang'] = '';

		$delete_properties = array();
		$page = 0;

		do {
			$args['offset'] = Process_Resources::MAX_SCRIPT_RUN_DELETED_PROPERTIES_CNT * $page;
			$this->log->add( wp_sprintf( __( 'Retrieving properties with offset %d.', 'immonex-openimmo2wp' ), $args['offset'] ), 'debug' );

			$properties = get_posts( $args );
			$this->log->add( wp_sprintf( __( 'Records to check: %d', 'immonex-openimmo2wp' ), count( $properties ) ), 'debug' );

			$this->_save_current_import_status( array(
				'current_property' => wp_sprintf( __( 'Determining properties to be deleted (offset: %u)...', 'immonex-openimmo2wp' ), $args['offset'] )
			), true );

			if ( count( $properties ) > 0 ) {
				foreach ( $properties as $property ) {
					$obid = get_post_meta( $property->ID, '_openimmo_obid', true );
					if ( ! $obid ) {
						$obid = $property->post_mime_type;
					}

					if (
						! $obid ||
						! get_post_meta( $property->ID, '_is_immonex_import_property', true )
					) {
						// Property has been created manually or by third party software, don't delete it.
						$this->log->add( wp_sprintf( __( 'Skipping property: %s (ID %s) - manually created', 'immonex-openimmo2wp' ), $property->post_title, $property->ID ), 'info' );
					} elseif (
						apply_filters( $this->plugin_prefix . 'delete_property', true, $property->ID ) &&
						(
							'delete_all_insert_all' === $this->plugin_options['full_import_mode'] ||
							! in_array( $obid, $changed_or_new_property_obids )
						)
					) {
						// Property is not listed in XML file: mark for deletion.
						$delete_properties[] = $property;
					}

					if ( count( $delete_properties ) === $max_properties_to_delete_per_run ) {
						$page--;
						break;
					}
				}

				$page++;
			}
		} while (
			count( $properties ) > 0 &&
			count( $delete_properties ) < $max_properties_to_delete_per_run
		);

		$properties = $delete_properties;

		if ( count( $properties ) > 0 ) {
			$properties = apply_filters( $this->plugin_prefix . 'full_import_properties_to_delete', array_values( $properties ) );

			$this->log->add( wp_sprintf( __( 'Number of properties to delete in this batch: %u', 'immonex-openimmo2wp' ), count( $properties ) ), 'debug' );

			if ( count( $properties ) > 0 ) {
				foreach ( $properties as $property ) {
					$this->_save_current_import_status( array(
						'current_property' => wp_sprintf( __( 'Existing properties are being deleted (%s)...', 'immonex-openimmo2wp' ), sanitize_text_field( (string) $property->post_title ) )
					), true );

					$this->_delete_property( $property->ID, $property->post_title );

					// Update the number of deleted properties (full import) during this script run.
					$this->current_deleted_properties_count++;

					// Check script execution time after every deleted property, restart if necessary.
					$this->_check_script_resources( $dir, 0, $cnt_properties, 0, 0, $token );
				}
			}
		} else {
			$this->log->add( __( 'No properties to delete...', 'immonex-openimmo2wp' ), 'info' );
		}
	} // _delete_all_import_properties

	/**
	 * Delete a single property.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param int $post_id Property post ID.
	 * @param string $title Property title (for log entry).
	 * @param string $log_level Log level (default: info).
	 * @param bool $log_entry Create a log entry?
	 *
	 * @return bool True on successful deletion, false on failure.
	 */
	private function _delete_property( $post_id, $title = false, $log_level = 'info', $log_entry = true ) {
		if ( ! isset( $post_id ) || ! $post_id ) return false;

		$delete = apply_filters( $this->plugin_prefix . 'delete_property', true, $post_id );
		if ( ! $delete ) return false;

		do_action( $this->plugin_prefix . 'before_property_post_deletion', $post_id );

		$property_xml_source = get_post_meta( $post_id, '_immonex_property_xml_source', true );
		if ( $property_xml_source ) {
			$immobilie = new \SimpleXMLElement( $property_xml_source );
			do_action( $this->plugin_prefix . 'before_property_deletion', $immobilie );
		}

		$result = wp_delete_post( $post_id, true );

		if ( false !== $result ) {
			if ( $log_entry ) $this->log->add( wp_sprintf( __( 'Property deleted: %s (ID %s)', 'immonex-openimmo2wp' ), $title, $post_id ), $log_level );

			if ( $property_xml_source ) {
				do_action( $this->plugin_prefix . 'property_deleted', $post_id, $immobilie );
			}

			return true;
		} else {
			$this->log->add( wp_sprintf( __( 'Error on deleting a property: Post ID %s', 'immonex-openimmo2wp' ), $post_id ), 'error' );
		}

		return $result;
	} // _delete_property

	/**
	 * Extract XML property data that will be stored as post.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param int $cnt_property Number of current property.
	 * @param string|bool $post_status Set given post status (optional).
	 *
	 * @return array Property data to store as post.
	 */
	private function _get_property_post_data( $immobilie, $cnt_property, $post_status = false ) {
		$default_post_status = $this->plugin_options['review_imported_properties'] && 'none' !== $this->plugin_options['review_imported_properties'] ? 'pending' : 'publish';

		$post_data = array(
			'post_status' => $post_status ? $post_status : $default_post_status,
			'post_type' => $this->property_post_type ? $this->property_post_type : self::DEFAULT_PROPERTY_POST_TYPE,
			'comment_status' => 'closed'
		);

		$default_author_id = $this->_get_default_property_post_author();
		if ( $default_author_id ) $post_data['post_author'] = $default_author_id;

		foreach ( $this->mappings as $mapping ) {
			// Loop through all mappings, only the ones with type "post" will be processed.
			if ( 'post' === $mapping['type'] ) {
				// Multiple values shall be combined for this mapping on a "+" or "#" at the end of the source string.
				if ( '+' === $mapping['source'][strlen( $mapping['source'] ) - 1] ) {
					$join_multiple_values = true;
					$join_divider = 0 === strpos( $mapping['source'], 'freitexte' ) ? "\n\n" : ', ';
				} elseif ( '#' === $mapping['source'][strlen( $mapping['source'] ) - 1] )  {
					$join_multiple_values = true;
					$join_divider = ' ';
				} else {
					$join_multiple_values = false;
				}

				// Pass element value through a filter function?
				$filter_function = isset( $mapping['filter'] ) ? $mapping['filter'] : false;

				// Fetch + filter element value (if available) and assign it to the post data array.
				$element_value = $this->_get_element_value( $immobilie, $mapping['source'], $filter_function, true, 'post_data', $mapping );
				if ( is_string( $element_value ) ) $element_value = trim( $element_value );
				$element_value = apply_filters( $this->plugin_prefix . 'add_post_data_element', $element_value, $immobilie, $mapping );

				if ( $element_value ) {
					if ( $join_multiple_values && isset( $post_data[$mapping['dest']] ) ) {
						// Combine multiple values in one field.
						$post_data[$mapping['dest']] .= $join_divider . $element_value;
					} else {
						$post_data[$mapping['dest']] = $element_value;
					}
				}
			}
		}

		if ( ! isset( $post_data['post_content'] ) ) $post_data['post_content'] = '';

		// Save OpenImmo OBID additionally in post MIME type field (used in cases when the
		// respective custom field could not be saved or is not available). Add user import
		// folder if the global import folder is NOT used.
		if ( isset( $immobilie->verwaltung_techn->openimmo_obid ) ) {
			$backup_obid = trim( (string) $immobilie->verwaltung_techn->openimmo_obid );
			$property_import_folder = apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $this->current_import_zip_file );

			$post_data['post_mime_type'] = sanitize_text_field( $backup_obid . ( 'global' !== $property_import_folder ? $property_import_folder : '' ) );
		}

		if ( ! apply_filters( $this->plugin_prefix . 'reverse_property_post_time_order', false ) ) {
			$post_date_base_time = '00:00:' . date( 's' );
		} else {
			$post_date_base_time = '05:00:' . date( 's' );
		}

		$base_time_ts = apply_filters( "{$this->plugin_prefix}property_last_update_ts", false, $immobilie );
		$extra_minute = true;

		if ( $base_time_ts ) {
			// Define property post creation date based on XML data.
			$base_date = date_i18n( 'Y-m-d', $base_time_ts );

			if ( '00:00:00' === date( 'H:i:s', $base_time_ts ) ) {
				$post_date_ts = strtotime( "{$base_date} {$post_date_base_time}" );
				$this->log->add( wp_sprintf( __( 'Property creation date (XML/filter): %s', 'immonex-openimmo2wp' ), $base_date ), 'debug' );
			} else {
				$post_date_ts = $base_time_ts;
				$extra_minute = false;
				$this->log->add( wp_sprintf( __( 'Property creation date/time (XML/filter): %s', 'immonex-openimmo2wp' ), date_i18n( 'Y-m-d H:i:s', $base_time_ts ) ), 'debug' );
			}
		} else {
			$post_date_ts = time();
		}

		if ( $extra_minute ) {
			/**
			 * Add (default) or subtract (reversed property post time order) an extra minute
			 * per property to the post date/time if only a date is stated in the XML data.
			 * (Otherwise the post navigation might not work properly later.)
			 */
			$post_date_ts = strtotime( ( '00:00:' === substr( $post_date_base_time, 0, 6 ) ? '+' : '-' ) . $cnt_property . ' minutes', $post_date_ts );
		}

		$post_date = date_i18n( 'Y-m-d H:i:s', $post_date_ts );
		$post_data['post_date'] = $post_date;
		$post_date_gmt = $this->_get_gmt_time( $post_data['post_date'] );
		$post_data['post_date_gmt'] = $post_date_gmt;
		$this->log->add( wp_sprintf( __( 'Property post date (local / GMT): %s / %s', 'immonex-openimmo2wp' ), $post_date, $post_date_gmt ), 'debug' );

		if (
			( isset( $post_data['post_title'] ) && trim( $post_data['post_title'] ) ) ||
			'DELETE' === (string) $immobilie->verwaltung_techn->aktion['aktionart']
		) {
			if ( isset( $post_data['post_title'] ) ) {
				// Sanitize title.
				$post_data['post_title'] = sanitize_text_field( $post_data['post_title'] );
			} else {
				$post_data['post_title'] = '';
			}

			if ( empty( $post_data['post_title'] ) ) {
				$post_data['post_title'] = __( 'not available', 'immonex-openimmo2wp' );
				if ( 'DELETE' === (string) $immobilie->verwaltung_techn->aktion['aktionart'] ) {
					$post_data['post_title'] .= ' (DELETE)';
				}
			}

			foreach ( array( 'post_title', 'post_content' ) as $key ) {
				// Convert special characters, tabs and spaces preceding line breaks in content strings.
				$post_data[ $key ] = preg_replace( '/[\t ]+(\r)?\n/', PHP_EOL, $post_data[ $key ]);
				// DEPRECATED
				// $post_data[ $key ] = preg_replace( '/([\xB0-\xB4\xB9-\xBC\xBF-\xC5\xC8-\xCE\xD9-\xDF](\r)?\n)/u', '$1' . PHP_EOL, $post_data[ $key ]);
			}

			return $post_data;
		} else {
			// Don't import properties without title!
			$this->log->add( __( 'Title missing - skipping property!', 'immonex-openimmo2wp' ), 'info' );
			return false;
		}
	} // _get_property_post_data

	/**
	 * Maybe set a property post author based on the import folder name.
	 *
	 * @since 2.3
	 * @access private
	 *
	 * @return int|bool Author ID or false if no author could be determined.
	 */
	private function _get_default_property_post_author() {
		// Default author = admin.
		$admins = get_users( array(
			'role' => 'Administrator',
			'orderby' => 'ID'
		) );
		$default_author = count( $admins ) > 0 ? $admins[0] : false;

		$current_import_folder = apply_filters( "{$this->plugin_prefix}plain_import_folder", '', $this->current_import_zip_file );

		if ( 'global' === $current_import_folder ) {
			// Use default author if global import folder is used.
			if ( $default_author ) $this->log->add( wp_sprintf( __( 'Assigning default post author (admin): %s', 'immonex-openimmo2wp' ), $default_author->display_name ), 'debug' );
			return $default_author ? $default_author->ID : false;
		}

		$slashpos = strrpos( $current_import_folder, DIRECTORY_SEPARATOR );
		if ( false !== $slashpos ) $current_import_folder = substr( $current_import_folder, $slashpos + 1 );

		$user = get_user_by( 'login', $current_import_folder );

		if ( $user ) {
			$this->log->add( wp_sprintf( __( 'Assigning post author (WP user) based on the import folder name: %s', 'immonex-openimmo2wp' ), $user->display_name ), 'debug' );
			return $user->ID;
		} else {
			// Also use default author if no user exists whose login name matches the import folder name.
			if ( $default_author ) $this->log->add( wp_sprintf( __( 'No user with login name %s found, assigning default post author (admin): %s', 'immonex-openimmo2wp' ), $current_import_folder, $default_author->display_name ), 'debug' );
			return $default_author ? $default_author->ID : false;
		}

		return false;
	} // _get_default_property_post_author

	/**
	 * Extract XML property data that will be stored as taxonomy terms.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param int $post_id Property Post ID.
	 *
	 * @return array Property data to store as taxonomy terms.
	 */
	private function _get_property_taxonomy_data( $immobilie, $post_id = false ) {
		$terms = array();

		foreach ( $this->mappings as $mapping ) {
			// Loop through all mappings, only the ones with type "taxonomy" will be processed.
			if ( 'taxonomy' === $mapping['type'] ) {
				$mapping = apply_filters( $this->plugin_prefix . 'tax_mapping', $mapping, $immobilie, $post_id );
				$taxonomy = $mapping['dest'];

				// Fetch taxonomy data, continue if not available.
				$taxonomy_data = get_taxonomy( $taxonomy );
				if ( ! $taxonomy_data ) continue;

				// Pass element value through a filter function?
				$filter_function = isset( $mapping['filter'] ) ? $mapping['filter'] : false;

				$element_value = $this->_get_element_value( $immobilie, $mapping['source'], $filter_function, true, 'taxonomy_data', $mapping );
				$mapping_title = $this->get_multilang_mapping_value( $mapping, 'title', $this->current_import_language );

				if ( ( false !== $element_value && '0' !== $element_value ) && $mapping_title ) {
					// Title specified in mapping: Set it as value instead of element value.
					$element_value = $mapping_title;
				}

				if (
					$element_value &&
					(
						is_string( $element_value ) ||
						is_int( $element_value ) ||
						is_float( $element_value )
					)
				) {
					// Apply WP filters to new taxonomy term.
					$element_values = apply_filters( $this->plugin_prefix . 'add_property_taxonomy_term', $element_value, $immobilie, $mapping, $post_id, $terms );

					/**
					 * Single values will be converted to a single element array. (The array approach makes
					 * it possible to split single values into multiple elements that will be imported separately
					 * by a filter function.)
					 */
					if ( ! is_array( $element_values ) ) $element_values = array( $element_values );

					if ( count( $element_values ) > 0 ) {
						foreach ( $element_values as $element_value ) {
							if ( $element_value ) {
								$parent_id = false;

								if ( $taxonomy_data->hierarchical ) {
									$parent_term_name = apply_filters( $this->plugin_prefix . 'taxonomy_parent_term_name', '', $element_value, $immobilie, $mapping, $post_id );

									if ( ! $parent_term_name ) {
										$parent_term_name = $this->get_multilang_mapping_value( $mapping, 'parent', $this->current_import_language );
									}

									if ( $parent_term_name ) {
										/**
										 * For hierarchical taxonomies: Term shall be added as a sub term
										 * of another one...
										 */
										$term_data = $this->taxonomy_utils->get_term_multilang( $parent_term_name, $taxonomy, false, false );

										if ( empty( $term_data ) ) {
											$term_data = $this->taxonomy_utils->insert_term( $parent_term_name, $taxonomy, false, 'parent', $mapping, $immobilie );
										}

										if ( is_array( $term_data ) && isset( $term_data['term_id'] ) ) {
											$parent_id = $term_data['term_id'];
										} elseif ( is_int( $term_data ) ) {
											$parent_id = $term_data;
										} elseif ( 'skip' === $term_data ) {
											$this->log->add( wp_sprintf( __( 'Skipped inserting a taxonomy parent term: %s', 'immonex-openimmo2wp' ), "{$parent_term_name}, {$taxonomy}" ), 'debug' );
										} else {
											$this->log->add( wp_sprintf( __( 'Error on determining/inserting a taxonomy parent term: %s', 'immonex-openimmo2wp' ) . ' (1)', is_wp_error( $term_data ) ? $term_data->get_error_message() : "{$parent_term_name}, {$taxonomy}" ), 'debug' );
										}
									}
								}

								$term_data = $this->taxonomy_utils->get_term_multilang( $element_value, $taxonomy, false, $parent_id );

								if ( empty( $term_data ) ) {
									$term_data = $this->taxonomy_utils->insert_term( $element_value, $taxonomy, $parent_id, $mapping_title ? 'title' : 'import_value', $mapping, $immobilie );
								}
							} else {
								$term_data = false;
							}

							if ( is_array( $term_data ) && isset( $term_data['term_id'] ) ) {
								$term_id = $term_data['term_id'];
							} elseif ( is_int( $term_data ) ) {
								$term_id = $term_data;
							} elseif ( 'skip' === $term_data ) {
								$this->log->add( wp_sprintf( __( 'Skipped inserting a taxonomy term: %s', 'immonex-openimmo2wp' ), $element_value . ', ' . $taxonomy ), 'debug' );
							} elseif ( false !== $term_data ) {
								$this->log->add( wp_sprintf( __( 'Error on determining/inserting a taxonomy term: %s', 'immonex-openimmo2wp' ) . ' (2)', is_wp_error( $term_data ) ? $term_data->get_error_message() : $element_value . ', ' . $taxonomy ), 'debug' );
							}

							if ( isset( $term_id ) && $term_id ) {
								$term = array(
									'term_id' => (int) $term_id,
									'taxonomy' => $taxonomy
								);

								// Add property term to term array.
								$terms[] = $term;
							}
						}
					}
				}
			}
		}

		return $terms;
	} // _get_property_taxonomy_data

	/**
	 * Get a mapping title or caption in a given language.
	 *
	 * @since 1.0
	 *
	 * @param array $mapping Current mapping array (line).
	 * @param string $field Mapping field (column) name.
	 * @param string $language Language as ISO2 code.
	 *
	 * @return string|bool Field value in given or default language, false on unknown field.
	 */
	public function get_multilang_mapping_value( $mapping, $field, $language ) {
		if (
			'en' !== $language &&
			isset( $mapping["$field $language"] ) &&
			'' !== trim( $mapping["$field $language"] )
		) {
			return $mapping["$field $language"];
		}

		if (
			isset( $mapping[$field] ) &&
			'' !== trim( $mapping[$field] )
		) {
			return $mapping[$field];
		}

		return false;
	} // get_multilang_mapping_value

	/**
	 * Extract XML property data that will be stored as custom fields.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param SimpleXMLElement $immobilie XML node of a property object.
	 * @param int $post_id Property Post ID.
	 *
	 * @return array Property data to store as custom fields.
	 */
	private function _get_property_custom_fields( $immobilie, $post_id ) {
		$custom_fields = array();
		$is_sale = 'true' === strtolower( (string) $immobilie->objektkategorie->vermarktungsart['KAUF'] ) ||
			'1' === (string) $immobilie->objektkategorie->vermarktungsart['KAUF'];

		foreach ( $this->mappings as $mapping ) {
			// Loop through all mappings, only the ones with type "custom_field" will be processed.
			if ( 'custom_field' === $mapping['type'] ) {
				$unique = false;
				if (
					isset( $mapping['dest'] ) &&
					$mapping['dest']
				) {
					if ( '+' !== $mapping['dest'][strlen( $mapping['dest'] ) - 1] ) {
						// Add data as unique custom field (post meta).
						$unique = true;
					} else {
						// Add data as part of a grouped custom field (serialized array).
						$mapping['dest'] = substr( $mapping['dest'], 0, -1 );
					}
				}

				// Multiple values shall be combined for this mapping on a "+" or "#" at the end of the source string.
				if ( '+' === $mapping['source'][strlen( $mapping['source'] ) - 1] ) {
					$join_multiple_values = true;
					$join_divider = 0 === strpos( $mapping['source'], 'freitexte' ) ? "\n\n" : ', ';
				} elseif ( '#' === $mapping['source'][strlen( $mapping['source'] ) - 1] )  {
					$join_multiple_values = true;
					$join_divider = ' ';
				} else {
					$join_multiple_values = false;
					$join_divider = '';
				}

				$mapping_title = $this->get_multilang_mapping_value( $mapping, 'title', $this->current_import_language );
				$mapping_parent = $this->get_multilang_mapping_value( $mapping, 'parent', $this->current_import_language );

				if ( isset( $mapping['dest'] ) && $mapping['dest'] ) {
					$meta_key = $mapping['dest'];
				} elseif ( $join_multiple_values && $mapping_parent ) {
					$meta_key = $mapping_parent;
				} elseif ( $mapping_title ) {
					$meta_key = $mapping_title;
				} else {
					continue;
				}

				// Pass element value through an internal filter function?
				$filter_function = isset( $mapping['filter'] ) ? $mapping['filter'] : false;

				/**
				 * Always retrieve translated version of element value (internal filter functions)
				 * if the output does NOT take place over an immonex widget.
				 */
				$force_translation = isset( $mapping['dest'] ) && $mapping['dest'];

				$element_value = $this->_get_element_value( $immobilie, $mapping['source'], $filter_function, $force_translation, 'custom_field', $mapping );

				if ( ! $is_sale && preg_match( '/preise-\>(innen|aussen)_courtage/', $mapping['source'] ) ) {
					$mapping_title = str_replace(
						array( __( 'Seller', 'immonex-openimmo2wp' ), __( 'Buyer', 'immonex-openimmo2wp' ) ),
						array( __( 'Landlord', 'immonex-openimmo2wp' ), __( 'Tenant', 'immonex-openimmo2wp' ) ),
						$mapping_title
					);
					$mapping_parent = str_replace(
						array( __( 'Seller', 'immonex-openimmo2wp' ), __( 'Buyer', 'immonex-openimmo2wp' ) ),
						array( __( 'Landlord', 'immonex-openimmo2wp' ), __( 'Tenant', 'immonex-openimmo2wp' ) ),
						$mapping_parent
					);
				}

				if ( $filter_function ) {
					$element_value_before_filter = $this->_get_element_value( $immobilie, $mapping['source'], false, $force_translation, 'custom_field', $mapping );
				} else {
					$element_value_before_filter = $element_value;
				}

				if (
					false !== $element_value &&
					(
						! empty( $mapping['dest'] ) ||
						! empty( $mapping['parent'] ) ||
						! empty( $mapping['parent ' . $this->current_import_language] )
					) &&
					false !== $mapping_title
				) {
					$element_value = $mapping_title;
					$element_value_before_filter = $mapping_title;
				}

				if ( false !== $element_value ) {
					$custom_field_data = array(
						'mapping_source' => $mapping['source'],
						'mapping_destination' => isset( $mapping['dest'] ) ? $mapping['dest'] : '',
						'mapping_parent' => $mapping_parent,
						'meta_key' => $meta_key,
						'meta_value' => $element_value,
						'meta_value_before_filter' => $element_value_before_filter,
						'meta_name' => isset( $mapping['name'] ) ? $mapping['name'] : false,
						'meta_group' => isset( $mapping['group'] ) ? $mapping['group'] : false,
						'unique' => $unique,
						'join_multiple_values' => $join_multiple_values,
						'join_divider' => $join_divider
					);

					// Add custom field (filters applied before).
					$custom_field = apply_filters( $this->plugin_prefix . 'add_property_custom_field', $custom_field_data, $immobilie, $post_id );
					if ( $custom_field ) $custom_fields[] = $custom_field;
				}
			}
		}

		// Handle the property location (address or coordinates).
		do_action( $this->plugin_prefix . 'handle_property_location', $post_id, $immobilie );

		// Add unique OpenImmo property ID (OBID) to custom fields array.
		$custom_field = array(
			'meta_key' => '_openimmo_obid',
			'meta_value' => trim( (string) $immobilie->verwaltung_techn->openimmo_obid ),
			'unique' => true
		);
		$custom_fields[] = apply_filters( $this->plugin_prefix . 'add_property_custom_field', $custom_field, $immobilie, $post_id );

		if ( $this->current_openimmo_anid ) {
			// Add OpenImmo agency ID (ANID) to custom fields array, if available.
			$custom_field = array(
				'meta_key' => '_openimmo_anid',
				'meta_value' => $this->current_openimmo_anid,
				'unique' => true
			);
			$custom_fields[] = apply_filters( $this->plugin_prefix . 'add_property_custom_field', $custom_field, $immobilie, $post_id );
		}

		/**
		 * Determine and save property grouping data.
		 */

		$grouping_fields = Property_Grouping::get_grouping_fields( $immobilie );

		if ( count( $grouping_fields ) > 0 ) {
			foreach ( $grouping_fields as $meta_key => $meta_value ) {
				$custom_field = array(
					'meta_key' => $meta_key,
					'meta_value' => $meta_value,
					'unique' => true
				);

				if ( '_immonex_group_id' === $meta_key && $meta_value ) {
					$this->log->add( wp_sprintf( __( 'Group Identifier: %s', 'immonex-openimmo2wp' ), $meta_value ), 'info' );
				} elseif (
					'_immonex_group_master' === $meta_key &&
					in_array( $meta_value, array( 'visible', 'invisible' ) )
				) {
					$this->log->add( wp_sprintf(
						__( 'Group Master Object (%s)', 'immonex-openimmo2wp' ),
						'visible' === $meta_value ? __( 'visible', 'immonex-openimmo2wp' ) : __( 'NOT VISIBLE', 'immonex-openimmo2wp' )
					), 'info' );
				}

				$custom_fields[] = apply_filters( $this->plugin_prefix . 'add_property_custom_field', $custom_field, $immobilie, $post_id );
			}
		}

		// Add import meta infos for the property (CAN'T be overridden by a filter function).
		$custom_fields[] = array(
			'meta_key' => '_immonex_import_meta',
			'meta_value' => array(
				'zip_file' => $this->current_import_zip_file,
				'xml_file' => $this->current_import_xml_file,
				'mapping_file' => $this->current_mapping_file,
				'timestamp' => time(),
				'date' => date_i18n( 'Y-m-d' ),
				'time' => date_i18n( 'H:i:s' )
			),
			'unique' => true
		);

		// Mark property as imported by this plugin (CAN'T be overridden by a filter function).
		$custom_fields[] = array(
			'meta_key' => '_is_immonex_import_property',
			'meta_value' => '1',
			'unique' => true
		);

		return $custom_fields;
	} // _get_property_custom_fields

	/**
	 * Add a property's energy class and a related ID in two custom fields,
	 * if available.
	 *
	 * @since 5.2.3-beta
	 * @access private
	 *
	 * @param int $post_id Property post ID.
	 * @param SimpleXMLElement $immobilie Property XML node.
	 */
	private function _add_energy_class( $post_id, $immobilie ) {
		$class = (string) $immobilie->zustand_angaben->energiepass->wertklasse;

		if ( ! $class ) {
			$class_node = $immobilie->xpath( '//zustand_angaben/user_defined_simplefield[@feldname="epass_wertklasse"]' );
			if ( ! empty( $class_node ) ) {
				$class = (string) $immobilie->xpath( '//zustand_angaben/user_defined_simplefield[@feldname="epass_wertklasse"]' )[0];
			}
		}

		$class_ids = [
			'A+' => 5,
			'A'  => 10,
			'B'  => 20,
			'C'  => 30,
			'D'  => 40,
			'E'  => 50,
			'F'  => 60,
			'G'  => 70,
			'H'  => 80,
		];

		$class_id = $class && isset( $class_ids[ $class ] ) ? $class_ids[ $class ] : '0';

		update_post_meta( $post_id, '_immonex_energy_class', $class );
		update_post_meta( $post_id, '_immonex_energy_class_id', $class_id );
	} // _add_energy_class

	/**
	 * Process property images.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param int $post_id Property post ID.
	 * @param SimpleXMLElement $anhaenge XML node of property attachments.
	 * @param string $dir XML file directory (may be a subfolder of the current unzip dir!).
	 * @param int $cnt_property Number of currently processed property.
	 * @param int $cnt_properties Total number of properties in the current XML file.
	 * @param int $cnt_start_attachment Number of attachment to be processed next (resumed import).
	 * @param string $token Token of current import process.
	 * @param mixed[] $current_status Array of current import status data.
	 * @param mixed[]|bool $reset_attachment_data Array of attachment IDs/paths/URLs that already have been imported etc.
	 *   previous runs.
	 */
	private function _process_property_attachments( $post_id, $anhaenge, $dir, $cnt_property, $cnt_properties, $cnt_start_attachment, $token, $current_status = false, $reset_attachment_data = false ) {
		if ( ! $current_status ) $current_status = $this->_get_current_import_status();
		if ( ! $dir ) $dir = $current_status['dir'];

		$image_unzip_dir = substr( $current_status['dir'], strrpos( $current_status['dir'], DIRECTORY_SEPARATOR ) );
		if ( strlen( $dir ) > strlen( $current_status['dir'] ) ) {
			$image_unzip_dir .= substr( $dir, strlen( $current_status['dir'] ) );
		}

		add_action( 'add_attachment', array( $this, 'add_property_attachment' ), 5 );

		$this->current_property_main_image = apply_filters( $this->plugin_prefix . 'property_main_image_by_xml', Attachment_Utils::get_main_image_from_xml( $anhaenge ) );

		$cnt_attachment = 1;
		$cnt_attachments = array(
			'images' => 0,
			'videos' => 0,
			'misc_files' => 0
		);
		$cnt_all_attachments = ! empty( $anhaenge->anhang ) ? count( $anhaenge->anhang ) : 0;

		foreach ( $anhaenge->anhang as $anhang ) {
			$skip = false;
			$error = false;
			$is_url = isset( $anhang->daten->pfad ) && 'http' === strtolower( substr( (string) $anhang->daten->pfad, 0, 4 ) )
				|| isset( $anhang->anhangtitel ) && 'http' === strtolower( substr( (string) $anhang->anhangtitel, 0, 4 ) );
			$is_remote_video = isset( $anhang['gruppe'] ) && strtoupper( $anhang['gruppe'] ) === 'FILMLINK' && $is_url;
			$is_link = $is_remote_video || isset( $anhang['gruppe'] ) && in_array( strtoupper( $anhang['gruppe'] ), array( 'LINKS', 'ANBOBJURL' ) );

			if ( ! $is_link ) {
				$format = Attachment_Utils::get_attachment_format_from_xml( $anhang, $this->valid_attachment_file_formats );
				// Count attachments based on file formats.
				if ( in_array( $format, $this->valid_attachment_image_file_formats ) ) $cnt_attachments['images']++;
				elseif ( in_array( $format, $this->valid_attachment_video_file_formats ) ) $cnt_attachments['videos']++;
				elseif ( in_array( $format, $this->valid_attachment_misc_file_formats ) ) $cnt_attachments['misc_files']++;
			}

			if ( $cnt_start_attachment && $cnt_attachment < $cnt_start_attachment ) {
				// Skip already processed images/attachments.
				$skip = true;
			}

			if ( ! $skip ) {
				$anhang = apply_filters( $this->plugin_prefix . 'attachment_before_import', $anhang, $post_id, $cnt_attachment, $cnt_all_attachments, $anhaenge->anhang );
				if ( ! $anhang ) {
					// Attachment import must have been canceled via a filter function: skip it.
					$skip = true;
					$this->log->add( wp_sprintf( __( 'Skipping attachment %d of %d (filter function).', 'immonex-openimmo2wp' ), $cnt_attachment, $cnt_all_attachments ), 'debug' );
				}
			}

			if ( ! $skip ) {
				/**
				 * Make maximum number of image attachments per property definable by filter functions
				 * (e.g. in theme support classes).
				 */
				$max_image_attachments = apply_filters( $this->plugin_prefix . 'custom_max_image_attachments_per_property', $this->plugin_options['max_image_attachments_per_property'] );
				if (
					$this->plugin_options['max_image_attachments_per_property'] > 0 &&
					$max_image_attachments > $this->plugin_options['max_image_attachments_per_property']
				) {
					// Limit the (bigger) custom number to the one set in the plugin configuration.
					$max_image_attachments = $this->plugin_options['max_image_attachments_per_property'];
				}

				if (
					$max_image_attachments > 0 &&
					in_array( strtoupper( $format ), $this->valid_attachment_image_file_formats ) &&
					$cnt_attachments['images'] > $max_image_attachments
				) {
					$skip = true;
					$this->log->add( wp_sprintf( __( 'Max. number of image attachments reached (%d): skipping attachment.', 'immonex-openimmo2wp' ), $max_image_attachments ), 'info' );
				}
			}

			if ( ! $skip ) {
				$org_path_or_url = false;

				if (
					isset( $anhang->daten->pfad ) &&
					trim( (string) $anhang->daten->pfad )
				) {
					$org_path_or_url = trim( (string) $anhang->daten->pfad );
				} elseif (
					isset( $anhang->anhangtitel ) &&
					'http' === substr( strtolower( trim( (string) $anhang->anhangtitel ) ), 0, 4 )
				) {
					$org_path_or_url = trim( (string) $anhang->anhangtitel );
				}

				if ( ! $org_path_or_url ) {
					$this->log->add( __( 'Skipping attachment due to missing path/filename', 'immonex-openimmo2wp' ), 'error' );
					$skip = true;
				}
			}

			if ( ! $skip && $reset_attachment_data && in_array( $org_path_or_url, array_keys( $reset_attachment_data['xml_exclude'] ) ) ) {
				$this->log->add( wp_sprintf( __( 'Skipping already existent attachment: %s', 'immonex-openimmo2wp' ), $org_path_or_url ), 'debug' );
				// Perform attachment actions although it has not been reprocessed.
				do_action( $this->plugin_prefix . 'attachment_added',
					$reset_attachment_data['xml_exclude'][$org_path_or_url], // --> Attachment ID
					$this->valid_attachment_image_file_formats,
					$this->valid_attachment_misc_file_formats,
					$this->valid_attachment_video_file_formats );
				$skip = true;
			}

			if ( ! $skip ) {
				$att_post_data = array();

				if ( $is_link ) {
					// Save/Update property links in a special custom field.
					$property_links = get_post_meta( $post_id, '_immonex_links', true );
					if ( ! $property_links || ! is_array( $property_links ) ) $property_links = array();

					$url = false;
					if ( isset( $anhang->daten->pfad ) && 'http' === strtolower( substr( (string) $anhang->daten->pfad, 0, 4 ) ) ) {
						$url = (string) $anhang->daten->pfad;
					} elseif ( isset( $anhang->anhangtitel ) && 'http' === strtolower( substr( (string) $anhang->anhangtitel, 0, 4 ) ) ) {
						$url = (string) $anhang->anhangtitel;
					}

					$this->log->add( wp_sprintf( 'Link: %s', $url ), 'debug' );

					if ( isset( $anhang->anhangtitel ) && (string) $anhang->anhangtitel ) {
						$title = (string) $anhang->anhangtitel;
					} else {
						// Set URL as as title.
						$title = trim( $url );
					}

					if ( $this->string_utils->is_virtual_tour_url( $title, apply_filters( $this->plugin_prefix . 'additional_virtual_tour_url_parts', array() ) ) ) {
						// Link seems to lead to a virtual tour: adjust the title.
						$title = __( 'Virtual Tour', 'immonex-openimmo2wp' );
					}

					$property_links[] = array(
						'url' => $url,
						'title' => $title
					);

					$property_links = apply_filters( $this->plugin_prefix . 'update_property_links', $property_links, $post_id );

					update_post_meta( $post_id, '_immonex_links', $property_links );
				} elseif ( in_array( strtoupper( $format ), $this->valid_attachment_file_formats ) ) {
					if ( preg_match( '/^http[s]?:/i', (string) $org_path_or_url ) ) {
						$attachment_file = $org_path_or_url;
						$url = $attachment_file;
						$attachment_exists = $this->general_utils->remote_file_exists( $url );
						$is_remote = true;
					} else {
						$attachment_file = $dir . DIRECTORY_SEPARATOR . $org_path_or_url;
						// Generate file URL for import/processing.
						$url = apply_filters( "{$this->plugin_prefix}working_url", '' ) . $image_unzip_dir . DIRECTORY_SEPARATOR . basename( $attachment_file );
						$attachment_exists = file_exists( $attachment_file );
						$is_remote = false;
					}

					$this->log->add(
						wp_sprintf(
							__( 'Attachment %u of %u: %s', 'immonex-openimmo2wp' ) . ( $is_remote ? ' (remote)' : '' ),
							$cnt_attachment,
							$cnt_all_attachments,
							basename( $attachment_file )
						),
						'debug'
					);

					if ( $attachment_exists ) {
						$this->log->add( wp_sprintf( __( 'Attachment URL: %s', 'immonex-openimmo2wp' ), $url ), 'debug' );
						if ( isset( $anhang->anhangtitel ) && $anhang->anhangtitel ) {
							$desc = (string) $anhang->anhangtitel;

							// Save attachment description as excerpt (caption), too.
							$att_post_data = array(
								'post_excerpt' => $desc
							);
						} elseif ( ! in_array( strtoupper( $format ), $this->valid_attachment_image_file_formats ) ) {
							// Set (part of) filename as description if attachment is NOT an image.
							$desc = basename( $attachment_file );
							if ( strlen( $desc ) > 12 ) $desc = substr( $desc, 0, 12 ) . '...';
						} else $desc = '';

						$status = $this->_get_current_import_status();

						// Check/Set the number of import attempts for the current image/attachment.
						if (
							isset( $status['current_attachment_attempts'] ) &&
							$cnt_property == $status['current_attachment_attempts']['cnt_property'] &&
							$cnt_attachment == $status['current_attachment_attempts']['cnt_attachment']
						) {
							$attempts = $status['current_attachment_attempts']['attempts'] + 1;
						} else {
							$attempts = 1;
						}
						// Save next attachment info and number of current attempt for this image/attachment.
						$this->_save_current_import_status( array(
							'cnt_next_attachment' => $cnt_attachment,
							'current_attachment_attempts' => array(
								'cnt_property' => $cnt_property,
								'cnt_attachment' => $cnt_attachment,
								'attempts' => $attempts
							)
						), true );

						if ( $this->process_resources->is_within_attachment_import_attempt_limit( $attempts ) ) {
							$is_image = in_array( strtoupper( $format ), $this->valid_attachment_image_file_formats );

							if ( $is_image ) {
								if ( $att_id = Attachment_Utils::check_existing_image_attachment( $post_id, $attachment_file, is_array( $reset_attachment_data ) ? $reset_attachment_data['keep'] : array() ) ) {
									$this->log->add( wp_sprintf( __( 'An already existing image attachment (ID %s) has been deleted before reimport: %s', 'immonex-openimmo2wp' ), $att_id, basename( $attachment_file ) ), 'debug' );
								}
							}

							$tmp = $this->_create_temp_file( $attachment_file, $is_remote );

							if ( ! $is_remote && ! $tmp ) {
								$error = true;
								$this->log->add( wp_sprintf( __( 'Error on creating a temporary copy of: %s', 'immonex-openimmo2wp' ), basename( $attachment_file ) ), 'error' );
							} elseif ( is_wp_error( $tmp ) ) {
								$error = true;
								$this->log->add( wp_sprintf( __( 'Error downloading a remote file: %s', 'immonex-openimmo2wp' ), $tmp->get_error_message() ), 'error' );
							}

							if ( ! $error && $is_image ) {
								// Check for valid image file.
								$image_mime_types = array( 'image/jpeg', 'image/png','image/gif' );

								if ( function_exists( 'exif_imagetype' ) && false === @exif_imagetype( $tmp ) ) {
									$error = true;
								} elseif ( function_exists( 'finfo_open' ) ) {
									$fileinfo = finfo_open( FILEINFO_MIME_TYPE );
									if ( ! in_array( finfo_file( $fileinfo, $tmp ), $image_mime_types ) ) {
										$error = true;
									}
								} elseif (
									function_exists( 'mime_content_type' )
									&& ! in_array( mime_content_type( $tmp ), $image_mime_types )
								) {
									$error = true;
								} elseif ( ! @getimagesize( $tmp ) ) {
									$error = true;
								}

								if ( $error ) {
									$cnt_attachments['images']--;
									$this->log->add( wp_sprintf( __( 'Attachment is not a valid image file: %s', 'immonex-openimmo2wp' ), basename( $attachment_file ) ), 'error' );
								}
							}

							if ( ! $error ) {
								$att_filename = apply_filters(
									$this->plugin_prefix . 'attachment_filename',
									Attachment_Utils::maybe_add_suffix( Attachment_Utils::get_url_basename( $url ), $format ),
									array(
										'count' => $cnt_attachment,
										'org_path' => $org_path_or_url,
										'format' => $format,
										'description' => $desc,
										'att_post_data' => $att_post_data,
										'property_post_id' => $post_id,
									)
								);

								$file_array = array(
									'name' => $att_filename,
									'tmp_name' => $tmp
								);
								$org_file_size = filesize( $tmp );

								// Import image/attachment.
								$att_id = media_handle_sideload( $file_array, $post_id, $desc, $att_post_data );

								if ( is_wp_error( $att_id ) ) {
									$this->wp_filesystem->delete( $attachment_file );
									$this->log->add( wp_sprintf( __( 'Error on importing an %s: %s', 'immonex-openimmo2wp' ), $is_image ? _x( 'image', 'Error on importing an ...', 'immonex-openimmo2wp' ) : _x( 'file attachment', 'Error on importing an ...', 'immonex-openimmo2wp' ), $att_id->get_error_message() ), 'error' );
								} else {
									if ( isset( $att_post_data['post_excerpt'] ) && $att_post_data['post_excerpt'] ) {
										// Save attachment description as image alt text, too.
										add_post_meta( $att_id, '_wp_attachment_image_alt', $att_post_data['post_excerpt'], true );
									}

									// Save original filename/URL (XML) and file size for later comparison.
									add_post_meta( $att_id, '_immonex_import_attachment_org_path_or_url', $org_path_or_url, true );
									add_post_meta( $att_id, '_immonex_import_attachment_org_file_size', $org_file_size, true );

									if ( isset( $anhang->check ) && 'MD5' === strtoupper( $anhang->check['ctype'] ) ) {
										// Save attachment MD5 check hash.
										$this->log->add( wp_sprintf( 'MD5 Hash: %s', (string) $anhang->check ), 'debug' );
										add_post_meta( $att_id, '_immonex_import_attachment_md5_check_hash', (string) $anhang->check, true );
									}

									// Save attachment group and XML source.
									add_post_meta( $att_id, '_immonex_import_attachment_group', (string) $anhang['gruppe'], true );
									add_post_meta( $att_id, '_immonex_import_attachment_xml_source', $anhang->asXML(), true );

									do_action( $this->plugin_prefix . 'attachment_added',
										$att_id,
										$this->valid_attachment_image_file_formats,
										$this->valid_attachment_misc_file_formats,
										$this->valid_attachment_video_file_formats );

									$this->log->add( wp_sprintf( __( 'Attachment processed (ID %s)', 'immonex-openimmo2wp' ), $att_id ), 'debug' );
								}

								$this->current_processed_attachments_count++;
							}
						} else {
							// Max. number of import attempts exceeded: skip this file.
							$error = true;
							$this->log->add( wp_sprintf( __( 'Error on importing the image/attachment (max. retries exceeded): %s', 'immonex-openimmo2wp' ), basename( $attachment_file ) ), 'error' );
						}
					} else {
						$error = true;
						$this->log->add( __( 'Attachment file not found', 'immonex-openimmo2wp' ), 'error' );
					}
				}
			}

			$cnt_attachment++;

			if ( $cnt_attachment <= count( $anhaenge->anhang ) + 1 ) {
				// Update next attachment number in current status file/option.
				$this->_save_current_import_status( array( 'cnt_next_attachment' => $cnt_attachment ), true );

				if ( ! $skip && ! $error ) {
					$this->_check_script_resources( $current_status['dir'], $cnt_property, $cnt_properties, $cnt_attachment, count( $anhaenge->anhang ), $token );
				}
			}
		}

		$args = array(
			'post_type' => 'attachment',
			'numberposts' => -1,
			'post_status' => 'any',
			'post_parent' => $post_id,
			'orderby' => 'ID',
			'order' => 'ASC',
			'lang' => ''
		);

		$attachments = get_posts( $args );
		if ( count( $attachments ) > 0 ) {
			// Add menu order value to all property attachments.
			foreach ( $attachments as $i => $att ) {
				$att->menu_order = $i;
				wp_update_post( $att );
			}
		}

		remove_action( 'add_attachment', array( $this, 'add_property_attachment' ) );
	} // _process_property_attachments

	/**
	 * Read and set up the mappings.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param string $maping_file Mapping file.
	 */
	private function _fetch_mappings( $mapping_file ) {
		$this->current_mapping_file = $this->mapping_folders->get_current_file( $mapping_file );

		if ( $this->current_mapping_file ) {
			$raw_mappings = array();

			if ( ! (bool) preg_match( '//u', file_get_contents( $this->current_mapping_file ) ) ) {
				$this->mapping_error = __( 'Mapping file encoding is not proper UTF-8.', 'immonex-openimmo2wp' );
				return;
			}

			$f = fopen( $this->current_mapping_file, 'r' );
			$row = 0;
			while ( false !== ( $row_values = fgetcsv( $f, 1000, ',', '"' ) ) ) {
				// Loop through mapping file lines (ignore empty and comment lines).
				if ( empty( $row_values[0] ) || '#' === $row_values[0][0] ) {
					continue;
				}

				$row++;
				if ( 1 === $row ) {
					// First line: split column types and continue.
					$column_types = $row_values;
					continue;
				}

				$row_values_named = array();
				foreach ( $row_values as $i_row => $value ) {
					// Create a mapping record of attribute-value pairs.
					if ( isset( $column_types[$i_row] ) ) $row_values_named[strtolower( $column_types[$i_row] )] = trim( $value );
				}

				$raw_mappings[] = $row_values_named;
			}
			fclose( $f );

			if ( count( $raw_mappings ) > 0 ) {
				$this->mappings = array();

				$cnt = 0;
				foreach ( $raw_mappings as $i => $mapping ) {
					if ( ! isset( $mapping['type'] ) || ! isset( $mapping['source'] ) ) continue;

					// Loop through "raw mappings" and create the real mapping table.
					$this->mappings[$cnt] = array(
						'type' => $mapping['type'],
						'source' => $mapping['source'],
					);

					$this->mappings[$cnt]['name'] = isset( $mapping['name'] ) && $mapping['name'] ? $mapping['name'] : '';
					$this->mappings[$cnt]['group'] = isset( $mapping['group'] ) && $mapping['group'] ? $mapping['group'] : '';
					$this->mappings[$cnt]['dest'] = isset( $mapping['destination'] ) && $mapping['destination'] ? $mapping['destination'] : '';
					if ( isset( $mapping['filter'] ) && $mapping['filter'] ) {
						// Split internal filter function declaration (function name, parameters...)
						$filter_temp = explode( ':', $mapping['filter'] );
						$this->mappings[$cnt]['filter'] = array( 'function' => $filter_temp[0] );

						$parameters = array();
						if ( count( $filter_temp ) > 1 ) {
							foreach ( $filter_temp as $param_cnt => $parameter ) {
								if ( 0 == $param_cnt || '' === trim( $parameter ) ) continue;
								$parameters[] = $parameter;
							}
						}
						$this->mappings[$cnt]['filter']['parameters'] = $parameters;
					}

					foreach ( $mapping as $field => $value ) {
						if (
							'title' == substr( $field, 0, 5 ) ||
							'parent' == substr( $field, 0, 6 )
						) {
							// Add all title and parent fields (including multiple languages).
							$this->mappings[$cnt][$field] = $mapping[$field];
						}
					}

					$cnt++;
				}
			} else {
				$this->mapping_error = __( 'No regular mappings found', 'immonex-openimmo2wp' );
			}
		} else {
			$available_mapping_files = ! empty( $this->mapping_files ) ?
				implode( ', ', array_map( 'basename', $this->mapping_files ) ) :
				__( 'none', 'immonex-openimmo2wp' );

			$this->mapping_error = wp_sprintf(
				__( 'Mapping file not found: %s (available files: %s)', 'immonex-openimmo2wp' ),
				$mapping_file,
				$available_mapping_files
			);
		}
	} // _fetch_mappings

	/**
	 * Extract nested ZIP archives.
	 *
	 * @since 1.8.2
	 * @access private
	 *
	 * @param string Main import directory (absolute path).
	 *
	 * @return array List of ZIP files.
	 */
	private function _extract_nested_zip_files( $dir ) {
		if ( ! $dir ) return;

		$zip_files = apply_filters( "{$this->plugin_prefix}import_zip_files", array(), $dir, true );

		if ( count( $zip_files ) > 0 ) {
			foreach ( $zip_files as $zip_file ) {
				$result = unzip_file( $zip_file, $dir );

				if ( true === $result ) {
					$this->log->add( wp_sprintf( __( 'Successfully unzipped nested ZIP archive %s', 'immonex-openimmo2wp' ), basename( $zip_file ) ), 'debug' );
				} else {
					// Error on unzipping.
					if ( is_wp_error( $result ) ) {
						$this->log->add( wp_sprintf( __( 'Error unzipping nested ZIP archive %s: %s', 'immonex-openimmo2wp' ), basename( $zip_file ), $result->get_error_message() ) );
					} else {
						$this->log->add( wp_sprintf( __( 'Error unzipping nested ZIP archive: %s', 'immonex-openimmo2wp' ), basename( $zip_file ) ) );
					}
				}

				$this->wp_filesystem->delete( $zip_file );
			}
		}
	} // _extract_nested_zip_files

	/**
	 * Fetch list of XML archives pending to be imported.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param string $dir Import directory (optional).
	 *
	 * @return array List of XML files.
	 */
	private function _get_import_xml_files( $dir ) {
		if ( empty( $dir ) ) {
			$dir = apply_filters( "{$this->plugin_prefix}working_dir", '' );
		}

		$xml_files = $this->general_utils->glob_recursive( $dir . '/*.[xX][mM][lL]' );

		if ( count( $xml_files ) > 0 ) {
			foreach ( $xml_files as $i => $file ) {
				if ( 'proc_' === substr( basename( $file ), 0, 5 ) ) {
					// Exclude generated processing XML files.
					unset( $xml_files[$i] );
				}
			}
		}

		usort( $xml_files, array( $this, '_sort_by_mtime' ) );

		return $xml_files;
	} // _get_import_xml_files

	/**
	 * Save/mail the logs.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param string $log_file Filename for storing the log.
	 * @param bool $debug_log_only Only send the debug log.
	 */
	private function _process_logs( $log_file, $debug_log_only = false ) {
		if ( $this->is_debug() ) {
			$import_log_recipient = ! empty( $this->plugin_options['import_log_recipient_email'] ) ? self::DEBUG_MODE_LOG_RECIPIENT : false;
			$debug_log_recipient = ! empty( $this->plugin_options['debug_log_recipient_email'] ) ? self::DEBUG_MODE_LOG_RECIPIENT : false;
		} else {
			$import_log_recipient = ! empty( $this->plugin_options['import_log_recipient_email'] ) ? sanitize_email( $this->plugin_options['import_log_recipient_email'] ) : false;
			$debug_log_recipient = ! empty( $this->plugin_options['debug_log_recipient_email'] ) ? sanitize_email( $this->plugin_options['debug_log_recipient_email'] ) : false;
		}

		$admin_log_levels = array( 'info', 'debug', 'error', 'fatal' );
		if ( $log_file && ( $this->plugin_options['keep_archive_files_days'] > 0 || $this->is_debug() ) ) $this->log->save( $log_file, $admin_log_levels, true, true );

		$mail_subject = self::PLUGIN_NAME . ' ' . __( 'Import Log', 'immonex-openimmo2wp' );
		$mail_headers = apply_filters( $this->plugin_prefix . 'mail_headers', array() );

		$template_data = array(
			'preset' => 'admin_info'
		);

		if ( ! $debug_log_only && $import_log_recipient ) {
			// Send info log (e.g. for real-estate agent).
			$body_txt = $this->log->get_log( array( 'info', 'error', 'fatal' ), false, false );
			$mail_body = array( 'html' => nl2br( $body_txt ), 'txt' => $body_txt );

			$this->mail_utils->send( $import_log_recipient, $mail_subject, $mail_body, $mail_headers, array(), $template_data );
		}

		if ( $debug_log_recipient ) {
			// Send debug log (Admin).
			$body_txt = $this->log->get_log( $admin_log_levels, true, true );
			$mail_body = array( 'html' => nl2br( $body_txt ), 'txt' => $body_txt );

			$this->mail_utils->send( $debug_log_recipient, $mail_subject, $mail_body, $mail_headers, array( $log_file ), $template_data );
		}

		// Delete temporary raw log data.
		try {
			$this->log->destroy();
		} catch ( \Exception $e ) {
			$this->processing_errors[] = $e->getMessage();
		}
	} // _process_logs

	/**
	 * Get a file's modification OR creation time, whichever is more recent.
	 *
	 * @since 4.2
	 * @access private
	 *
	 * @param string $file Full path to file.
	 * @param bool $log Add log entry on error (optional, true by default).
	 *
	 * @return int|bool UNIX Timestamp of last modification or false on error.
	 */
	private function _get_mtime( $file, $log = true ) {
		$ts = $this->local_fs_utils->get_mtime( $file );
		if ( $ts ) {
			return $ts;
		}

		if ( $log && $this->_get_current_import_status() ) {
			$this->log->add( wp_sprintf( __( 'Determining file modification time failed for %s', 'immonex-openimmo2wp' ), $file ), 'error' );
		}

		return false;
	} // _get_mtime

	/**
	 * Compare file modification times (callback for sort function).
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param string $file1 Name of file 1.
	 * @param string $file2 Name of file 2.
	 *
	 * @return int Comparison result (-1, 0, 1).
	 */
	private function _sort_by_mtime( $file1, $file2 ) {
		$time1 = $this->_get_mtime( $file1 );
		$time2 = $this->_get_mtime( $file2 );

		if ( $time1 == $time2 ) return 0;

		return ( $time1 < $time2 ) ? -1 : 1;
	} // _sort_by_mtime

	/**
	 * Convert local date/time to GMT.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param string $time Date and time.
	 *
	 * @return string Formatted GMT time.
	 */
	private function _get_gmt_time( $time ) {
		if ( strlen( $time ) < 19 ) $time .= ' 00:00:00';
		$gmt_offset = get_option( 'gmt_offset' );
		return date( 'Y-m-d H:i:s', strtotime( $time . ( $gmt_offset >= 0 ? '+' : '-' ) . $gmt_offset ) );
	} // _get_gmt_time

	/**
	 * Get XML node/attribute value and apply filters.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param SimpleXMLElement $xml XML document or node.
	 * @param string $element XML element/attribute path.
	 * @param string $filter Name of filter function (optional).
	 * @param bool $force_translation Always get the translated element value (internal filter functions, optional).
	 * @param string $type Data type (optional).
	 * @param mixed $mapping Complete mapping data (optional).
	 *
	 * @return string|bool Node/attribute value.
	 */
	private function _get_element_value( $xml, $element, $filter = false, $force_translation = true, $type = false, $mapping = false ) {
		$xml_namespaces = $xml->getDocNamespaces();
		if ( isset( $xml_namespaces[''] ) && $xml_namespaces[''] ) {
			// XML document contains a default namespace declaration: use it for
			// the following XPath query.
			$xml->registerXPathNamespace( 'oi', $xml_namespaces[''] );
			$ns = 'oi:';
		} else {
			$ns = '';
		}

		if (
			$filter &&
			! empty( $filter['function'] ) &&
			method_exists( '\immonex\OpenImmo2Wp\Import_Content_Filters', 'filter_' . $filter['function'] )
		) {
			$filter_args = array(
				'force_translation' => $force_translation,
				'mapping_parameters' => ! empty( $filter['parameters'] ) ?
					$filter['parameters'] : array()
			);
			$filter_function = 'filter_' . $filter['function'];
		} else {
			$filter = false;
		}

		// Check if value shall be replaced by a mapped title.
		$replace_value_by_title = false;
		if (
			$this->get_multilang_mapping_value( $mapping, 'title', $this->current_import_language ) &&
			(
				! empty( $mapping['dest'] ) ||
				$this->get_multilang_mapping_value( $mapping, 'parent', $this->current_import_language )
			)
		) {
			$replace_value_by_title = true;
		}

		$xpath = '';
		$xqueries = array();
		$path = explode( '->', $element );
		if ( count( $path ) > 0 ) {
			// Loop through element parts to create an XPath query.
			foreach ( $path as $i => $node ) {
				$element_split = $this->_split_element( $node );
				$xpath .= "/$ns" . $element_split['node'];
			}

			$special_node_value = strtolower( (string) $element_split['node_value_is'] );

			$exists = 'exists' === $special_node_value;
			$not_empty = 'not_empty' === $special_node_value;
			$empty = 'empty' === $special_node_value;
			$missing = 'missing' === $special_node_value;
			$empty_or_missing = 'empty_or_missing' === $special_node_value;

			if ( ! $missing && ! $exists && ! $empty_or_missing ) {
				if ( $empty || $empty_or_missing ) {
					// Only regard nodes that are empty or contain 0 or false.
					$xqueries[] = 'not(text()) or text()="0" or text()="false"';
				} elseif ( in_array( $special_node_value, array( '0', 'false' ), true ) ) {
					$xqueries[] = 'text()="0" or text()="false"';
				} elseif ( in_array( $special_node_value, array( '1', 'true' ), true ) ) {
					$xqueries[] = 'text()="1" or text()="true"';
				} elseif ( $not_empty ) {
					// Only regard nodes that are NOT EMPTY (= child node(s) exist).
					$xqueries[] = 'node()';
				} elseif ( false !== $element_split['node_value_is'] ) {
					// Only regard nodes that MATCH a given value.
					$xqueries[] = "text()='" . $element_split['node_value_is'] . "'";
				} elseif ( false !== $element_split['node_value_is_not'] ) {
					// Only regard nodes that do NOT match a given value.
					$xqueries[] = "text()!='" . $element_split['node_value_is_not'] . "'";
				} elseif ( $element_split['node_value_contains'] ) {
					// Only regard nodes that CONTAIN a given value.
					$xqueries[] = "contains(., '" . $element_split['node_value_contains'] . "')";
				} elseif ( $element_split['node_value_does_not_contain'] ) {
					// Only regard nodes that do NOT contain a given value.
					$xqueries[] = "not(contains(., '" . $element_split['node_value_does_not_contain'] . "'))";
				}
			}

			if ( $element_split['attribute'] ) {
				// Attribute given: check if exists.
				if ( ! empty( $element_split['attribute_value'] ) ) {
					// Regard attribute value if given...
					if ( 'contains' === $element_split['attribute_compare'] ) {
						$xtemp = 'contains(@' . $element_split['attribute'] . ", '" . $element_split['attribute_value'] . "'" . ')';
					} elseif ( 'not contains' === $element_split['attribute_compare'] ) {
						$xtemp = 'not(contains(@' . $element_split['attribute'] . ", '" . $element_split['attribute_value'] . "'" . '))';
					} else {
						$xtemp = '@' . $element_split['attribute'] . "{$element_split['attribute_compare']}'" . $element_split['attribute_value'] . "'";
						if ( in_array( $element_split['attribute_value'], array( 'true', 'false' ), true ) ) {
							// Also check for 1 and 0 if true or false given as values.
							$xtemp .= ' or @' . $element_split['attribute'];
							$xtemp .= "{$element_split['attribute_compare']}'" . ( 'true' == $element_split['attribute_value'] ? '1' : '0' ) . "'";
						}
					}
				} else {
					// ...otherwise, just check for "not false or empty".
					$xtemp = '@' . $element_split['attribute'] . "!='false' and @" . $element_split['attribute'] . "!='0' and @" . $element_split['attribute'] . "!=''";
				}
				$xqueries[] = $xtemp;
			}

			if ( count( $xqueries ) > 0 ) {
				// Add subqueries to XPath.
				foreach ( $xqueries as $i => $query ) {
					$xpath .= "[{$query}]";
				}
			}
		}
		if ( $xpath[0] == '/' ) $xpath = substr( $xpath, 1 );

		$data = $xml->xpath( $xpath );

		if ( ( $missing || $empty_or_missing ) && empty( $data ) ) {
			// "Missing node" matches.
			return $filter ? Import_Content_Filters::$filter_function( '0', $xml, $filter_args ) : '0';
		} elseif ( $missing && ! empty( $data ) ) {
			// Node found: no "missing" match.
			return false;
		} elseif ( empty( $data ) ) {
			// Node not found.
			return false;
		} elseif ( ( $data[0] && $data[0]->attributes() ) && ! $element_split['wildcard'] ) {
			// Node found, attributes exist.
			if (
				$element_split['attribute'] &&
				(
					false !== $element_split['attribute_value'] &&
					strlen( $element_split['attribute_value'] ) > 0
				)
			) {
				// Attribute and ATTRIBUTE VALUE given and existing...
				$node_value = $exists ? '1' : (string) $data[0];

				if ( $empty_or_missing && ! empty( $node_value ) ) {
					return false;
				}

				if (
					$filter &&
					'' !== $node_value &&
					(
						'not_empty' !== $element_split['node_value_is'] ||
						! in_array( strtolower( (string) $node_value ), array( '0', 'false' ), true )
					)
				) {
					$node_value = Import_Content_Filters::$filter_function( $node_value, $xml, $filter_args );
				}

				if ( in_array( $node_value, array( 'true', 'false' ), true ) ) {
					// Convert "true" and "false" strings to boolean.
					$node_value = $node_value === 'true';
				} elseif (
					$replace_value_by_title &&
					(
						'' === $node_value ||
						$element_split['node_value_is'] === $node_value
					)
				) {
					// Attribute value will be replaced by a given title, even if empty: return true.
					$node_value = true;
				}

				return apply_filters( $this->plugin_prefix . 'get_element_value', $node_value, $element, $type );
			} elseif ( $element_split['attribute'] ) {
				// Only attribute given: return attribute value.
				$value = (string) $data[0][$element_split['attribute']];
				// Convert true, false, 0 and 1 to boolean.
				if ( in_array( $value, array( 'true', 'false', '1', '0' ) ) ) $value = in_array( $value, array( 'true', '1' ) );

				if ( $filter ) {
					$value = Import_Content_Filters::$filter_function( $value, $xml, $filter_args );
				}

				if ( '' === $value && $replace_value_by_title ) {
					// Element value will be replaced by a given title, even if empty: return true.
					$value = true;
				}

				return apply_filters( $this->plugin_prefix . 'get_element_value', $value, $element, $type );
			} else {
				// Only nodes WITHOUT attributes shall be regarded: return false.
				return false;
			}
		} else {
			// No attribute given or wildcard element: return node value.
			$node_value = $exists ? '1' : trim( (string) $data[0] );

			if ( $empty_or_missing && ! empty( $node_value ) ) {
				return false;
			}

			if ( in_array( $node_value, array( 'true', 'false' ), true ) ) {
				// Convert "true" and "false" strings to boolean.
				$node_value = $node_value === 'true';
			}

			if (
				$filter &&
				'' !== $node_value &&
				(
					'not_empty' !== $element_split['node_value_is'] ||
					! in_array( strtolower( (string) $node_value ), array( '0', 'false' ), true )
				)
			) {
				$node_value = Import_Content_Filters::$filter_function( $node_value, $xml, $filter_args );
			}

			if (
				'' === $node_value &&
				! in_array( $special_node_value, array( 'empty', 'empty_or_missing', 'exists' ), true ) &&
				( empty( $mapping['source'] ) || 'objektkategorie->objektart' !== substr( $mapping['source'], 0, 26 ) )
			) {
				// Generally ignore empty nodes, except for mapped property type terms.
				$node_value = false;
			}

			if ( ! empty( $mapping['source'] ) ) {
				if ( 'geo->geokoordinaten:breitengrad' === substr( $mapping['source'], 0, 31 ) ) {
					$node_value = $this->geo_utils->validate_coords( $node_value, 'lat' );
				}
				if ( 'geo->geokoordinaten:laengengrad' === substr( $mapping['source'], 0, 31 ) ) {
					$node_value = $this->geo_utils->validate_coords( $node_value, 'lng' );
				}
			}

			return apply_filters( $this->plugin_prefix . 'get_element_value', $node_value, $element, $type );
		}
	} // _get_element_value

	/**
	 * Split XML element declaration into its parts.
	 *
	 * @since 1.0
	 * @access private
	 *
	 * @param string $element Node/value + attribute/value combination.
	 *
	 * @return array Splitted XML element parts (node, attribute etc.).
	 */
	private function _split_element( $element ) {
		$attribute = false;
		$attribute_value = false;
		$attribute_compare = '=';
		$node_value_contains = false;
		$node_value_does_not_contain = false;
		$node_value_is = false;
		$node_value_is_not = false;
		$join_multiple_values = false;

		if ( in_array( $element[strlen( $element ) - 1], array( '+', '#' ) ) ) {
			/**
			 * Values of multiple mapping entries shall be combined in one
			 * custom field.
			 */
			$join_multiple_values = true;
			$element = substr( $element, 0, -1 );
		}

		if ( strpos( $element, ':' ) ) {
			// Split node, attribute and attribute value.
			$temp = explode( ':', $element );
			$node = $temp[0];
			$attribute = $temp[1];
			if ( isset( $temp[2] ) ) $attribute_value = $temp[2];
			else $attribute_value = false;
		} else {
			$node = $element;
		}

		if ( $node[strlen( $node ) - 1] === '*' ) {
			// Wildcard element: Mapping is valid no matter which attributes exist.
			$wildcard = true;
			$node = substr( $node, 0, -1 );
		} else {
			$wildcard = false;
		}

		if ( false !== strpos( $node, '!~' ) ) {
			// Node value shall NOT contain a given term.
			$temp = explode( '!~', $node );
			$node = $temp[0];
			$node_value_does_not_contain = trim( $temp[1] );
		} elseif ( false !== strpos( $node, '!=' ) ) {
			// Node value shall NOT match a given term.
			$temp = explode( '!=', $node );
			$node = $temp[0];
			$node_value_is_not = trim( $temp[1] );
		} elseif ( false !== strpos( $node, '~' ) ) {
			// Node value shall contain a given term.
			$temp = explode( '~', $node );
			$node = $temp[0];
			$node_value_contains = trim( $temp[1] );
		} elseif ( false !== strpos( $node, '=' ) ) {
			// Node value shall match a given term.
			$temp = explode( '=', $node );
			$node = $temp[0];
			$node_value_is = trim( $temp[1] );
		}

		if ( ! empty( $attribute_value ) ) {
			if ( '!~' === substr( $attribute_value[0], 0, 2 ) ) {
				$attribute_compare = 'not contains';
				$attribute_value = substr( $attribute_value, 2 );
			} elseif ( '!' === $attribute_value[0] ) {
				$attribute_compare = '!=';
				$attribute_value = substr( $attribute_value, 1 );
			} elseif ( '~' === $attribute_value[0] ) {
				$attribute_compare = 'contains';
				$attribute_value = substr( $attribute_value, 1 );
			}
		}

		return array(
			'node' => $node,
			'wildcard' => $wildcard,
			'attribute' => $attribute,
			'attribute_value' => $attribute_value,
			'attribute_compare' => $attribute_compare,
			'node_value_contains' => $node_value_contains,
			'node_value_does_not_contain' => $node_value_does_not_contain,
			'node_value_is' => $node_value_is,
			'node_value_is_not' => $node_value_is_not,
			'join_multiple_values' => $join_multiple_values
		);
	} // _split_element

	/**
	 * Check if a supported theme is active.
	 *
	 * @since 1.3
	 * @access private
	 *
	 * @return array|bool Active theme properties if supported, false otherwise.
	 */
	private function _check_theme() {
		$supported_themes = apply_filters( $this->plugin_prefix . 'supported_themes', $this->supported_themes );

		// Get aliases of supported themes (if any) and plugins.
		$theme_aliases = array();
		$plugins = array();
		foreach ( $supported_themes as $theme_name => $theme_properties ) {
			if (
				( ! isset( $theme_properties['type'] ) || 'theme' === $theme_properties['type'] ) &&
				isset( $theme_properties['alias'] )
			) {
				if ( is_array( $theme_properties['alias'] ) ) {
					foreach ( $theme_properties['alias'] as $alias ) {
						$theme_aliases[$alias] = $theme_name;
					}
				} else {
					$theme_aliases[$theme_properties['alias']] = $theme_name;
				}
			} elseif ( isset( $theme_properties['type'] ) && 'plugin' === $theme_properties['type'] ) {
				$plugins[$theme_name] = $theme_properties;
			}
		}

		$theme = wp_get_theme();
		// Strip version numbers from theme names for comparison.
		$theme_name = $this->get_plain_theme_name( $theme->name );
		$parent_theme_name = $this->get_plain_theme_name( $theme->parent_theme );

		// If a child theme is in use, the version of the parent theme will
		// be used for comparison anyway.
		$theme_version = $theme->parent() ? $theme->parent()->Version : $theme->Version;

		if ( isset( $supported_themes[$theme_name] ) )
			$supported_theme = $supported_themes[$theme_name];
		elseif ( isset( $supported_themes[$parent_theme_name] ) )
			$supported_theme = $supported_themes[$parent_theme_name];
		elseif ( isset( $theme_aliases[$theme_name] ) )
			$supported_theme = $supported_themes[$theme_aliases[$theme_name]];
		elseif ( isset( $theme_aliases[$parent_theme_name] ) )
			$supported_theme = $supported_themes[$theme_aliases[$parent_theme_name]];
		else
			$supported_theme = false;

		if ( $supported_theme ) {
			if (
				// Check for required theme version.
				(
					isset( $supported_theme['min_version'] ) &&
					$supported_theme['min_version'] &&
					1 === version_compare( $supported_theme['min_version'], $theme_version ) // Theme version < required min. version.
				) || (
					isset( $supported_theme['max_version'] ) &&
					$supported_theme['max_version'] &&
					-1 === version_compare( $supported_theme['max_version'], $theme_version ) // Theme version > required max. version.
				)
			) {
				// Theme not in required version.
				$supported_theme = false;
			}
		}

		if ( false === $supported_theme && count( $plugins ) > 0 ) {
			// Check if a supported real estate plugin is installed and active.
			foreach ( $plugins as $plugin_name => $plugin_properties ) {
				if ( is_plugin_active( $plugin_properties['main_plugin_file'] ) ) {
					$supported_theme = $plugin_properties;
					break;
				}
			}
		}

		return $supported_theme;
	} // _check_theme

	/**
	 * Copy a file and generate/return a unique name.
	 *
	 * @since 2.4.5 beta
	 * @access private
	 *
	 * @param string $source_file Source file name (incl. absolute path).
	 * @param bool $is_remote Download file from external server?
	 *
	 * @return string|bool Name of temporary file (incl. absolute path).
	 */
	private function _create_temp_file( $source_file, $is_remote ) {
		if ( $is_remote ) {
			return download_url( $source_file );
		} else {
			if ( ! file_exists( $source_file ) ) {
				$this->log->add( wp_sprintf( __( 'Source file for temporary copy missing: %s', 'immonex-openimmo2wp' ), $source_file ), 'error' );
				return false;
			}

			$source_file_info = pathinfo( $source_file );
			$temp_file = trailingslashit( $source_file_info['dirname'] ) . uniqid() . '_' . $source_file_info['basename'];
			$result = $this->wp_filesystem->copy( $source_file, $temp_file, true, 0664 );

			if ( ! $result ) {
				// Copy via WP filesystem method was not successful, try standard PHP copy
				// command instead.
				$result = copy( $source_file, $temp_file );

				if ( ! $result ) $this->log->add( wp_sprintf( __( 'Temporary copy could not be created: %s', 'immonex-openimmo2wp' ), $temp_file ), 'error' );
			}
			return $result ? $temp_file : false;
		}
	} // _create_temp_file

	/**
	 * Compare existing attachments with XML attachment data and generate lists
	 * of files that shall and shall NOT be deleted during property post resets.
	 *
	 * @since 4.2
	 * @access private
	 *
	 * @param string|int $post_id The related property post's ID.
	 * @param SimpleXMLElement $anhaenge Property's attachment XML elements.
	 * @param string $dir Directory containing the local attachments.
	 * @param bool $reset_all Generally reset all attachments (optional).
	 *
	 * @return string[] Array containing the post ID and three sub arrays (keep/delete IDs/pahts/URLs).
	 */
	private function _get_property_attachments_to_reset( $post_id, $anhaenge, $dir, $reset_all = false ) {
		$keep_attachment_ids = array();
		$keep_attachment_org_paths_or_urls = array();
		$existing_attachment_ids = array();
		$exclude_xml_attachment_path_or_url = array();
		$existing_attachments = array();
		$existing_attachments_cmp_list = array();
		$xml_attachments_cmp_list = array();

		$args = array(
			'post_type' => 'attachment',
			'numberposts' => -1,
			'post_status' => 'any',
			'post_parent' => $post_id,
			'orderby' => 'ID',
			'order' => 'ASC',
			'lang' => ''
		);

		$attachments = get_posts( $args );

		// Perform an additional parent ID filtering (WPML bug).
		$attachments = array_filter(
			get_posts( $args ),
			function( $att ) use ( $post_id ) {
				return (int) $att->post_parent === (int) $post_id;
			}
		);

		if ( ! empty( $attachments ) ) {
			foreach ( $attachments as $att ) {
				$existing_attachment_ids[] = $att->ID;

				$file = get_attached_file( $att->ID, true );
				if ( ! $file ) continue;

				$org_path_or_url = get_post_meta( $att->ID, '_immonex_import_attachment_org_path_or_url', true );
				$mtime = $this->_get_mtime( $file );

				$existing_attachments[$att->ID] = array(
					'file' => $file,
					'filename' => $this->string_utils::get_plain_filename( basename( $file ) ),
					'org_path_or_url' => $org_path_or_url,
					'org_size' => get_post_meta( $att->ID, '_immonex_import_attachment_org_file_size', true ),
					'size' => (int) @filesize( $file ),
					'mtime' => date( 'Y-m-d_H_i_s', $mtime ? $mtime : time() ),
					'md5' => get_post_meta( $att->ID, '_immonex_import_attachment_md5_check_hash', true )
				);

				/**
				 * Generate a separate list of EXISTING attachment original filenames
				 * for sort order comparison.
				 */
				if ( ! $reset_all ) $existing_attachments_cmp_list[] = $org_path_or_url;
			}
		}

		$xml_attachments = ! empty( $anhaenge ) ? $this->_get_property_attachment_list_via_xml( $anhaenge, $dir ) : array();

		if ( ! $reset_all ) {
			if ( count( $xml_attachments ) > 0 ) {
				/**
				 * Generate a separate list of attachments listed in the XML FILE
				 * for sort order comparison.
				 */
				foreach ( $xml_attachments as $xml_att ) {
					$xml_attachments_cmp_list[] = $xml_att['org_path_or_url'];
				}
			}

			$same_attachment_order = Attachment_Utils::is_same_order( $existing_attachments_cmp_list, $xml_attachments_cmp_list );

			if ( ! $same_attachment_order ) {
				// Reset all attachments if order of EXISTING attachments does not match XML data.
				$this->log->add( __( 'Resetting all attachments due to changed order.', 'immonex-openimmo2wp' ) . ' [2]', 'debug' );
				$reset_all = true;
			}
		}

		if (
			! $reset_all &&
			count( $existing_attachments ) > 0 &&
			count( $xml_attachments ) > 0
		) {
			foreach ( $existing_attachments as $ex_id => $ex_att ) {
				foreach ( $xml_attachments as $xml_att ) {
					if ( ! $ex_att['org_path_or_url'] || in_array( $ex_att['org_path_or_url'], $keep_attachment_org_paths_or_urls ) ) break;

					if (
						( $xml_att['check'] && 'MD5' === $xml_att['check']['type'] ) &&
						$ex_att['md5'] === $xml_att['check']['value']
					) {
						/**
						 * MD5 check hash of existing attachment is the same as the one
						 * in the attachment XML data: keept attachment, exclude XML attachment.
						 */
						$keep_attachment_ids[] = $ex_id;
						$keep_attachment_org_paths_or_urls[] = $ex_att['org_path_or_url'];
						if ( ! in_array( $ex_id, $exclude_xml_attachment_path_or_url ) ) {
							$exclude_xml_attachment_path_or_url[$xml_att['org_path_or_url']] = $ex_id;
						}
						if ( $this->is_debug() ) $this->log->add( wp_sprintf(
							__( 'Keeping existing attachment due to equal MD5 check hash (ID: %s, Filenames WP/XML: %s / %s, Sizes WP/XML: %u / %u, Hash: %s)', 'immonex-openimmo2wp' ),
							$ex_id,
							$ex_att['filename'],
							$xml_att['filename'],
							$ex_att['size'],
							$xml_att['size'],
							$xml_att['check']['value'] ), 'debug' );

						break;
					} elseif (
						( $xml_att['check'] && 'DATETIME' === $xml_att['check']['type'] ) &&
						$ex_att['mtime'] >= strtotime( $xml_att['check']['value'] ) &&
						$ex_att['org_path_or_url'] === $xml_att['org_path_or_url']
					) {
						// Existing file is newer than check date/time given in
						// attachment XML data: keep attachment, exclude XML attachment.
						$keep_attachment_ids[] = $ex_id;
						$keep_attachment_org_paths_or_urls[] = $ex_att['org_path_or_url'];
						if ( ! in_array( $ex_id, $exclude_xml_attachment_path_or_url ) ) {
							$exclude_xml_attachment_path_or_url[$xml_att['org_path_or_url']] = $ex_id;
						}
						if ( $this->is_debug() ) $this->log->add( wp_sprintf(
							__( 'Keeping existing attachment due to newer file modification time (ID: %s, Filenames WP/XML: %s / %s, Sizes WP/XML: %u / %u, Times WP/XML: %s / %s)', 'immonex-openimmo2wp' ),
							$ex_id,
							$ex_att['filename'],
							$xml_att['filename'],
							$ex_att['size'],
							$xml_att['size'],
							date_i18n( 'Y-m-dTH:i:s', $ex_att['mtime'] ),
							$xml_att['check']['value'] ), 'debug' );

						break;
					} elseif (
						$ex_att['org_path_or_url'] === $xml_att['org_path_or_url'] &&
						(int) $ex_att['org_size'] === (int) $xml_att['size']
					) {
						// Filename and size match.
						$keep_attachment_ids[] = $ex_id;
						$keep_attachment_org_paths_or_urls[] = $ex_att['org_path_or_url'];
						if ( ! in_array( $ex_id, $exclude_xml_attachment_path_or_url ) ) {
							$exclude_xml_attachment_path_or_url[$xml_att['org_path_or_url']] = $ex_id;
						}
						if ( $this->is_debug() ) $this->log->add( wp_sprintf(
							__( 'Keeping existing attachment due to matching file name/path/URL and size (ID: %s, Path/URL WP/XML: %s / %s, Sizes WP/XML: %u / %u)', 'immonex-openimmo2wp' ),
							$ex_id,
							$ex_att['org_path_or_url'],
							$xml_att['org_path_or_url'],
							$ex_att['org_size'],
							$xml_att['size'] ), 'debug' );

						break;
					} elseif (
						$ex_att['size'] === $xml_att['size'] &&
						$ex_att['filename'] === $xml_att['filename']
					) {
						// File size and name (without counter) exactly match:
						// keep WP attachment, exclude XML attachment.
						$keep_attachment_ids[] = $ex_id;
						$keep_attachment_org_paths_or_urls[] = $ex_att['org_path_or_url'];
						if ( ! in_array( $ex_id, $exclude_xml_attachment_path_or_url ) ) {
							$exclude_xml_attachment_path_or_url[$xml_att['org_path_or_url']] = $ex_id;
						}
						if ( $this->is_debug() ) $this->log->add( wp_sprintf(
							__( 'Keeping existing attachment due to matching file name and size (ID: %s, Filenames WP/XML: %s / %s, Sizes WP/XML: %u / %u)', 'immonex-openimmo2wp' ),
							$ex_id,
							$ex_att['filename'],
							$xml_att['filename'],
							$ex_att['size'],
							$xml_att['size'] ), 'debug' );

						break;
					}
				}
			}
		}

		// Generate a list of IDs of attachments that shall be deleted on reset.
		$delete_attachment_ids = array_diff( $existing_attachment_ids, $keep_attachment_ids );

		return array(
			'post_id' => $post_id,
			'keep' => $keep_attachment_ids,
			'delete' => $delete_attachment_ids,
			'xml_exclude' => $exclude_xml_attachment_path_or_url
		);
	} // _get_property_attachments_to_reset

	/**
	 * Get a list of property attachment filenames and sizes out of its XML data.
	 *
	 * @since 4.2
	 * @access private
	 *
	 * @param SimpleXMLElement $anhaenge Property's attachment XML elements.
	 * @param string $dir Directory containing the local attachments.
	 *
	 * @return mixed[] Array of attachment core data.
	 */
	private function _get_property_attachment_list_via_xml( $anhaenge, $dir ) {
		$attachments = array();

		foreach ( $anhaenge->anhang as $anhang ) {
			$format = Attachment_Utils::get_attachment_format_from_xml( $anhang, $this->valid_attachment_file_formats, true );
			if ( ! $format ) continue;

			if ( isset( $anhang->daten->pfad ) ) {
				$file_or_url = trim( (string) $anhang->daten->pfad );
			} elseif (
				isset( $anhang->anhangtitel ) &&
				'http' === substr( strtolower( trim( (string) $anhang->anhangtitel ) ), 0, 4 )
			) {
				$file_or_url = trim( (string) $anhang->anhangtitel );
			} else {
				$file_or_url = false;
			}

			if ( $file_or_url ) {
				$is_remote = 'http' === substr( strtolower( $file_or_url ), 0, 4 );

				if ( isset( $anhang->check ) ) {
					$check = array(
						'value' => (string) $anhang->check,
						'type' => strtoupper( $anhang->check['ctype'] )
					);
				} else {
					$check = false;
				}

				// Generate the filename for comparison.
				if ( $is_remote ) {
					$basename = Attachment_Utils::get_url_basename( $file_or_url );
				} else {
					$basename = basename( $file_or_url );
				}
				$filename = $this->string_utils::get_plain_filename( $basename );
				$filename = Attachment_Utils::maybe_add_suffix( $filename, $format );

				$attachments[] = array(
					'org_path_or_url' => $file_or_url,
					'filename' => $filename,
					'size' => $is_remote ?
						(int) $this->general_utils->get_remote_filesize( $file_or_url ) :
						(int) @filesize( trailingslashit( $dir ) . $file_or_url ),
					'check' => $check
				);
			}
		}

		return $attachments;
	} // _get_property_attachment_list_via_xml

} // class OpenImmo2WP
