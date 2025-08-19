<?php
/**
 * Plugin Uninstaller
 *
 * @package ImmoBridge
 * @subpackage Core
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Core;

/**
 * Plugin Uninstaller
 *
 * Handles complete plugin removal including database cleanup,
 * file deletion, and option removal.
 *
 * @since 1.0.0
 */
final class Uninstaller
{
    /**
     * Uninstall the plugin
     *
     * This method is called when the plugin is deleted via WordPress admin.
     * It performs a complete cleanup of all plugin data.
     */
    public static function uninstall(): void
    {
        // Verify uninstall request
        if (!defined('WP_UNINSTALL_PLUGIN')) {
            exit;
        }

        // Check permissions
        if (!current_user_can('activate_plugins')) {
            return;
        }

        $uninstaller = new self();
        $uninstaller->performUninstall();
    }

    /**
     * Perform the actual uninstall process
     */
    private function performUninstall(): void
    {
        $this->clearScheduledEvents();
        $this->deleteCustomPostTypes();
        $this->deleteDatabaseTables();
        $this->deleteOptions();
        $this->deleteTransients();
        $this->deleteUploadedFiles();
        $this->clearCache();
        
        do_action('immobridge_uninstalled');
    }

    /**
     * Clear all scheduled events
     */
    private function clearScheduledEvents(): void
    {
        wp_clear_scheduled_hook('immobridge_cleanup_imports');
        wp_clear_scheduled_hook('immobridge_cleanup_cache');
        wp_clear_scheduled_hook('immobridge_auto_import');
    }

    /**
     * Delete all custom post types and their data
     */
    private function deleteCustomPostTypes(): void
    {
        global $wpdb;

        // Get all property posts
        $propertyPosts = get_posts([
            'post_type' => 'property',
            'numberposts' => -1,
            'post_status' => 'any',
            'fields' => 'ids',
        ]);

        // Delete each property post and its metadata
        foreach ($propertyPosts as $postId) {
            // Delete post meta
            $wpdb->delete($wpdb->postmeta, ['post_id' => $postId]);
            
            // Delete the post
            wp_delete_post($postId, true);
        }

        // Delete custom taxonomies and their terms
        $taxonomies = ['property_type', 'property_status', 'property_location'];
        foreach ($taxonomies as $taxonomy) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'fields' => 'ids',
            ]);

            if (!is_wp_error($terms)) {
                foreach ($terms as $termId) {
                    wp_delete_term($termId, $taxonomy);
                }
            }
        }
    }

    /**
     * Delete custom database tables
     */
    private function deleteDatabaseTables(): void
    {
        global $wpdb;

        $tables = [
            $wpdb->prefix . 'immobridge_import_logs',
            $wpdb->prefix . 'immobridge_property_meta',
        ];

        foreach ($tables as $table) {
            $wpdb->query("DROP TABLE IF EXISTS {$table}");
        }
    }

    /**
     * Delete all plugin options
     */
    private function deleteOptions(): void
    {
        global $wpdb;

        // Delete specific options
        $options = [
            'immobridge_settings',
            'immobridge_bricks_settings',
            'immobridge_api_settings',
            'immobridge_activated',
            'immobridge_deactivated',
            'immobridge_activation_time',
            'immobridge_deactivation_time',
            'immobridge_db_version',
        ];

        foreach ($options as $option) {
            delete_option($option);
        }

        // Delete all options starting with 'immobridge_'
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                'immobridge_%'
            )
        );
    }

    /**
     * Delete all plugin transients
     */
    private function deleteTransients(): void
    {
        global $wpdb;

        // Delete all plugin-specific transients
        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
                '_transient_immobridge_%',
                '_transient_timeout_immobridge_%'
            )
        );
    }

    /**
     * Delete uploaded files and directories
     */
    private function deleteUploadedFiles(): void
    {
        $uploadDir = wp_upload_dir();
        $pluginDir = $uploadDir['basedir'] . '/immobridge';

        if (file_exists($pluginDir)) {
            $this->deleteDirectory($pluginDir);
        }
    }

    /**
     * Recursively delete a directory and its contents
     *
     * @param string $dir Directory path to delete
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }
        
        rmdir($dir);
    }

    /**
     * Clear all plugin-related cache
     */
    private function clearCache(): void
    {
        // Clear object cache if available
        if (function_exists('wp_cache_flush_group')) {
            wp_cache_flush_group('immobridge');
        }

        // Clear any external cache plugins
        if (function_exists('wp_cache_clear_cache')) {
            wp_cache_clear_cache();
        }

        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }

        if (function_exists('wp_rocket_clean_domain')) {
            wp_rocket_clean_domain();
        }
    }
}
