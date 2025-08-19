<?php
/**
 * Class WP_Bootstrap
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

/**
 * Register plugin-specific menus etc.
 */
class WP_Bootstrap {

	/**
	 * Array of bootstrap data
	 *
	 * @var mixed[]
	 */
	private $data;

	/**
	 * Prefix for custom post type and taxonomy names
	 *
	 * @var string
	 */
	private $prefix;

	/**
	 * Main plugin object
	 *
	 * @var \immonex\OpenImmo2Wp\OpenImmo2WP
	 */
	private $plugin;

	/**
	 * Constructor
	 *
	 * @since 5.0.0
	 *
	 * @param mixed[]                      $bootstrap_data Plugin bootstrap data.
	 * @param \immonex\Kickstart\Kickstart $plugin Main plugin object.
	 */
	public function __construct( $bootstrap_data, $plugin ) {
		$this->data   = is_array( $bootstrap_data ) ? $bootstrap_data : array();
		$this->prefix = $bootstrap_data['plugin_prefix'];
		$this->plugin = $plugin;

		add_action( 'admin_menu', array( $this, 'add_menu_items' ) );
	} // __construct

	/**
	 * Create a custom backend main menu item.
	 *
	 * @since 5.0.0
	 */
	public function add_menu_items() {
		$oi2wp_menu_open = ( isset( $_GET['page'] ) ) ? false !== strpos( sanitize_key( $_GET['page'] ), 'openimmo2wp' ) : false;
		$import_file_count = apply_filters( $this->prefix . 'import_zip_files', array(), false, 'count' );
		//$import_file_count = 0;

		add_menu_page(
			'',
			'OpenImmo2WP' . ( ! $oi2wp_menu_open && $import_file_count ? ' <span class="awaiting-mod">' . $import_file_count . '</span>' : '' ),
			'edit_posts',
			'openimmo2wp',
			array( $this->plugin->settings_helper, 'render_page' ),
			'dashicons-update',
			6
		);

		$submenu_items = apply_filters(
			$this->prefix . 'submenu_items',
			array(
				array(
					'openimmo2wp',
					$this->data['plugin_name'] . ' - ' . __( 'Manual Import', 'immonex-openimmo2wp' ),
					__( 'Import', 'immonex-openimmo2wp' ) . ( $import_file_count ? ' <span class="awaiting-mod">' . $import_file_count . '</span>' : '' ),
					'edit_posts',
					'openimmo2wp',
					array( $this->plugin->settings_helper, 'render_page' ),
					110
				),
			)
		);

		if ( is_array( $submenu_items ) && count( $submenu_items ) > 0 ) {
			usort(
				$submenu_items,
				function ( $a, $b ) {
					if ( $a[6] === $b[6] ) {
						return 0;
					}
					return $a[6] < $b[6] ? -1 : 1;
				}
			);

			foreach ( $submenu_items as $item ) {
				call_user_func_array( 'add_submenu_page', $item );
			}
		}
	} // add_menu_items

} // class WP_Bootstrap
