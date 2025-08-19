<?php
/**
 * Plugin Name:       immonex OpenImmo2WP
 * Plugin URI:        https://plugins.inveris.de/wordpress-plugins/immonex-openimmo2wp/
 * Description:       Automated import of OpenImmo-XML property data into WordPress websites.
 * Version:           5.3.21-beta
 * Text Domain:       immonex-openimmo2wp
 * Domain Path:       /languages
 * Requires at least: 5.5
 * Requires PHP:      7.4
 * Author:            inveris OHG / immonex
 * Author URI:        https://plugins.inveris.de/
 * License:           immonex Plugin License V2
 * License URI:       https://plugins.inveris.de/eula/
 *
 * @package immonex\OpenImmo2Wp
 */

namespace immonex\OpenImmo2Wp;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Initialize autoloaders (Composer AND WP/plugin-specific).
 */
require_once __DIR__ . '/autoload.php';

// Make plugin main instance accessible via WP-CLI.
global $immonex_openimmo2wp;

/**
 * Instantiate plugin main class.
 */
$immonex_openimmo2wp = new OpenImmo2WP( basename( __FILE__, '.php' ) );
$immonex_openimmo2wp->init();

if ( defined( 'WP_CLI' ) && WP_CLI ) {
  new Cli_Commands( $immonex_openimmo2wp );
}
