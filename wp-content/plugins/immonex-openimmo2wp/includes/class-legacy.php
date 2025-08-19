<?php
/**
 * Class Legacy
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Compatibility with older plugin versions.
 */
class Legacy {

	/**
	 * Array of bootstrap data
	 *
	 * @var mixed[]
	 */
	private $data;

	/**
	 * Utility objects
	 *
	 * @var object[]
	 */
	private $utils;

	/**
	 * Prefix for hook names etc.
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Constructor
	 *
	 * @since 5.0.0
	 *
	 * @param mixed[] $bootstrap_data Plugin bootstrap data.
	 * @param object[] $utils Utility objects.
	 */
	public function __construct( $bootstrap_data, $utils ) {
		$this->data   = $bootstrap_data;
		$this->prefix = $bootstrap_data['plugin_prefix'];
		$this->utils  = $utils;

		add_action( 'admin_menu', array( $this, 'add_new_options_location_info_page' ) );
	} // __construct

	/**
	 * Add a settings link leading to a info page regarding the new options location.
	 *
	 * @since 5.0.0
	 */
	public function add_new_options_location_info_page() {
		$plugin_options_access_capability = apply_filters(
			// @codingStandardsIgnoreLine
			"{$this->data['plugin_slug']}_plugin_options_access_capability",
			OpenImmo2WP::DEFAULT_PLUGIN_OPTIONS_ACCESS_CAPABILITY
		);

		if ( empty( $plugin_options_access_capability ) ) {
			return;
		}

		add_options_page(
			__( 'New OpenImmo2WP Menu Position', 'immonex-openimmo2wp' ),
			'OpenImmo Import',
			$plugin_options_access_capability,
			"{$this->data['plugin_prefix']}new_options_location",
			array( $this->utils['settings'], 'render_page' )
		);
	} // add_new_options_location_info_page

	/**
	 * Trim all OpenImmo OBIDs of properties imported with older plugin versions.
	 *
	 * @since 5.0.0
	 */
	public function trim_obids() {
		global $wpdb;

		if ( empty( $wpdb ) ) {
			return;
		}

		$query_result = $wpdb->get_results(
			$wpdb->prepare( "UPDATE {$wpdb->postmeta} SET meta_value = LTRIM(RTRIM(meta_value)) WHERE meta_key = %s", '_openimmo_obid' )
		);
	} // trim_obids

} // class Legacy
