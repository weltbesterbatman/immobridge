<?php
/**
 * Plugin Name: ImmoBridge
 * Plugin URI: https://github.com/weltbesterbatman/immobridge
 * Description: Modern WordPress plugin for OpenImmo real estate data integration with Bricks Builder support
 * Version: 1.0.0-dev
 * Author: Martin Rauer
 * Author URI: https://github.com/weltbesterbatman
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: immobridge
 * Domain Path: /languages
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 8.0
 * Network: false
 *
 * @package ImmoBridge
 * @author Martin Rauer
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge;

use ImmoBridge\Core\Plugin;
use ImmoBridge\Core\Activator;
use ImmoBridge\Core\Deactivator;
use ImmoBridge\Core\Uninstaller;
use Throwable;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('IMMOBRIDGE_VERSION', '1.0.0-dev');
define('IMMOBRIDGE_PLUGIN_FILE', __FILE__);
define('IMMOBRIDGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('IMMOBRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));
define('IMMOBRIDGE_PLUGIN_BASENAME', plugin_basename(__FILE__));

// Composer autoloader
if (file_exists(IMMOBRIDGE_PLUGIN_DIR . 'vendor/autoload.php')) {
    require_once IMMOBRIDGE_PLUGIN_DIR . 'vendor/autoload.php';
} else {
    // Show admin notice if composer dependencies are missing
    add_action('admin_notices', function (): void {
        printf(
            '<div class="notice notice-error"><p><strong>ImmoBridge Error:</strong> Composer dependencies not installed. Please run "composer install" in the plugin directory.</p></div>'
        );
    });
    return;
}

// Global plugin instance
$GLOBALS['immobridge'] = null;

/**
 * Initialize the plugin
 */
add_action('plugins_loaded', function (): void {
    try {
        // Initialize the main plugin class
        $plugin = new Plugin();
        $plugin->init();
        
        // Store global reference
        $GLOBALS['immobridge'] = $plugin;
        
        error_log('ImmoBridge: Plugin initialized successfully with service-oriented architecture');
        
    } catch (Throwable $e) {
        // Log error and show admin notice
        error_log('ImmoBridge Plugin Error: ' . $e->getMessage());
        error_log('ImmoBridge Stack Trace: ' . $e->getTraceAsString());
        
        add_action('admin_notices', function () use ($e): void {
            printf(
                '<div class="notice notice-error"><p><strong>ImmoBridge Error:</strong> %s</p></div>',
                esc_html($e->getMessage())
            );
        });
    }
}, 10);

/**
 * Plugin activation hook
 */
register_activation_hook(__FILE__, function (): void {
    try {
        // Check if composer dependencies are available
        if (!file_exists(IMMOBRIDGE_PLUGIN_DIR . 'vendor/autoload.php')) {
            wp_die(
                'ImmoBridge activation failed: Composer dependencies not installed. Please run "composer install" in the plugin directory.',
                'Plugin Activation Error',
                ['back_link' => true]
            );
        }
        
        $activator = new Activator();
        $activator->activate();
        
        error_log('ImmoBridge: Plugin activated successfully');
        
    } catch (Throwable $e) {
        error_log('ImmoBridge activation error: ' . $e->getMessage());
        wp_die(
            sprintf(
                'ImmoBridge activation failed: %s',
                esc_html($e->getMessage())
            ),
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }
});

/**
 * Plugin deactivation hook
 */
register_deactivation_hook(__FILE__, function (): void {
    try {
        $deactivator = new Deactivator();
        $deactivator->deactivate();
        
        error_log('ImmoBridge: Plugin deactivated successfully');
        
    } catch (Throwable $e) {
        error_log('ImmoBridge deactivation error: ' . $e->getMessage());
    }
});

/**
 * Plugin uninstall hook
 */
register_uninstall_hook(__FILE__, [Uninstaller::class, 'uninstall']);

/**
 * Get the main plugin instance
 *
 * @return Plugin|null
 */
function immobridge(): ?Plugin {
    return $GLOBALS['immobridge'] ?? null;
}

/**
 * Get the plugin container
 *
 * @return \ImmoBridge\Core\Container\Container|null
 */
function immobridge_container(): ?\ImmoBridge\Core\Container\Container {
    $plugin = immobridge();
    return $plugin?->getContainer();
}
