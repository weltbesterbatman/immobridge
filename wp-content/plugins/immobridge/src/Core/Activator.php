<?php
/**
 * Plugin Activator
 *
 * @package ImmoBridge
 * @subpackage Core
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Core;

/**
 * Plugin Activator
 *
 * Handles plugin activation tasks such as creating database tables,
 * setting default options, and flushing rewrite rules.
 *
 * @since 1.0.0
 */
final class Activator
{
    /**
     * Activate the plugin
     *
     * @throws \RuntimeException
     */
    public function activate(): void
    {
        $this->checkRequirements();
        $this->createDatabaseTables();
        $this->setDefaultOptions();
        $this->createUploadDirectories();
        $this->scheduleEvents();
        
        // Flush rewrite rules to ensure custom post type URLs work
        flush_rewrite_rules();
        
        // Create default taxonomy terms
        $this->createDefaultTaxonomyTerms();
        
        // Set activation flag
        update_option('immobridge_activated', true);
        update_option('immobridge_activation_time', time());
        
        do_action('immobridge_activated');
    }

    /**
     * Check system requirements
     *
     * @throws \RuntimeException
     */
    private function checkRequirements(): void
    {
        // Check PHP version
        if (version_compare(PHP_VERSION, '8.2.0', '<')) {
            throw new \RuntimeException('ImmoBridge requires PHP 8.2 or higher.');
        }

        // Check WordPress version
        global $wp_version;
        if (version_compare($wp_version, '6.8', '<')) {
            throw new \RuntimeException('ImmoBridge requires WordPress 6.8 or higher.');
        }

        // Check required PHP extensions
        $requiredExtensions = ['dom', 'libxml', 'simplexml', 'json', 'mbstring'];
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                throw new \RuntimeException("Required PHP extension '{$extension}' is not loaded.");
            }
        }

        // Check if uploads directory is writable
        $uploadDir = wp_upload_dir();
        if (!wp_is_writable($uploadDir['basedir'])) {
            throw new \RuntimeException('WordPress uploads directory is not writable.');
        }
    }

    /**
     * Create database tables
     */
    private function createDatabaseTables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Import logs table
        $table_name = $wpdb->prefix . 'immobridge_import_logs';
        $sql = "CREATE TABLE $table_name (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            import_id varchar(36) NOT NULL,
            source_file varchar(255) NOT NULL,
            status enum('pending','processing','completed','failed') NOT NULL DEFAULT 'pending',
            total_properties int(11) unsigned NOT NULL DEFAULT 0,
            processed_properties int(11) unsigned NOT NULL DEFAULT 0,
            created_properties int(11) unsigned NOT NULL DEFAULT 0,
            updated_properties int(11) unsigned NOT NULL DEFAULT 0,
            skipped_properties int(11) unsigned NOT NULL DEFAULT 0,
            error_count int(11) unsigned NOT NULL DEFAULT 0,
            error_messages longtext,
            started_at datetime DEFAULT NULL,
            completed_at datetime DEFAULT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY import_id (import_id),
            KEY status (status),
            KEY created_at (created_at)
        ) $charset_collate;";

        // Property metadata table for extended attributes
        $table_name_meta = $wpdb->prefix . 'immobridge_property_meta';
        $sql_meta = "CREATE TABLE $table_name_meta (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            property_id bigint(20) unsigned NOT NULL,
            meta_key varchar(255) NOT NULL,
            meta_value longtext,
            meta_type enum('string','integer','float','boolean','array','object') NOT NULL DEFAULT 'string',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY property_id (property_id),
            KEY meta_key (meta_key),
            KEY property_meta (property_id, meta_key),
            FOREIGN KEY (property_id) REFERENCES {$wpdb->posts}(ID) ON DELETE CASCADE
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        dbDelta($sql_meta);

        // Store database version
        update_option('immobridge_db_version', '1.0.0');
    }

    /**
     * Set default plugin options
     */
    private function setDefaultOptions(): void
    {
        $defaultOptions = [
            'immobridge_settings' => [
                'import_enabled' => true,
                'auto_import' => false,
                'import_schedule' => 'hourly',
                'max_import_time' => 300, // 5 minutes
                'batch_size' => 50,
                'image_import' => true,
                'image_resize' => true,
                'image_max_width' => 1920,
                'image_max_height' => 1080,
                'seo_enabled' => true,
                'cache_enabled' => true,
                'cache_duration' => 3600, // 1 hour
                'debug_mode' => false,
            ],
            'immobridge_bricks_settings' => [
                'elements_enabled' => true,
                'dynamic_data_enabled' => true,
                'custom_css_enabled' => true,
            ],
            'immobridge_api_settings' => [
                'rest_api_enabled' => true,
                'api_rate_limit' => 100, // requests per minute
                'api_cache_enabled' => true,
            ],
        ];

        foreach ($defaultOptions as $option_name => $option_value) {
            if (!get_option($option_name)) {
                add_option($option_name, $option_value);
            }
        }
    }

    /**
     * Create upload directories for import workflow
     */
    private function createUploadDirectories(): void
    {
        $uploadDir = wp_upload_dir();
        $baseDir = $uploadDir['basedir'] . '/immobridge';
        
        $directories = [
            $baseDir,
            $baseDir . '/in',           // Incoming files for import
            $baseDir . '/processing',   // Files currently being processed
            $baseDir . '/archive',      // Successfully imported files
            $baseDir . '/error',        // Files that failed to import
            $baseDir . '/images',       // Extracted property images
            $baseDir . '/documents',    // Property documents/attachments
            $baseDir . '/temp',         // Temporary extraction directory
            $baseDir . '/logs',         // Import log files
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                wp_mkdir_p($dir);
                
                // Create .htaccess for security (except for 'in' directory which needs to be accessible)
                if (basename($dir) !== 'in') {
                    $htaccess = $dir . '/.htaccess';
                    if (!file_exists($htaccess)) {
                        file_put_contents($htaccess, "deny from all\n");
                    }
                }
                
                // Create index.php for security
                $index = $dir . '/index.php';
                if (!file_exists($index)) {
                    file_put_contents($index, "<?php\n// Silence is golden.\n");
                }
            }
        }
        
        // Create a README file in the 'in' directory with instructions
        $readmeFile = $baseDir . '/in/README.txt';
        if (!file_exists($readmeFile)) {
            $readmeContent = "ImmoBridge Import Directory\n";
            $readmeContent .= "==========================\n\n";
            $readmeContent .= "Place your OpenImmo XML or ZIP files in this directory for import.\n\n";
            $readmeContent .= "Supported file formats:\n";
            $readmeContent .= "- .xml (OpenImmo XML files)\n";
            $readmeContent .= "- .zip (ZIP archives containing XML and images)\n\n";
            $readmeContent .= "Files will be automatically moved to:\n";
            $readmeContent .= "- /processing/ while being imported\n";
            $readmeContent .= "- /archive/ after successful import\n";
            $readmeContent .= "- /error/ if import fails\n\n";
            $readmeContent .= "Directory: " . $baseDir . "/in/\n";
            $readmeContent .= "Created: " . date('Y-m-d H:i:s') . "\n";
            
            file_put_contents($readmeFile, $readmeContent);
        }
    }

    /**
     * Schedule recurring events
     */
    private function scheduleEvents(): void
    {
        // Schedule import cleanup (daily)
        if (!wp_next_scheduled('immobridge_cleanup_imports')) {
            wp_schedule_event(time(), 'daily', 'immobridge_cleanup_imports');
        }

        // Schedule cache cleanup (hourly)
        if (!wp_next_scheduled('immobridge_cleanup_cache')) {
            wp_schedule_event(time(), 'hourly', 'immobridge_cleanup_cache');
        }

        // Schedule auto import (if enabled)
        $settings = get_option('immobridge_settings', []);
        if (!empty($settings['auto_import']) && !wp_next_scheduled('immobridge_auto_import')) {
            $schedule = $settings['import_schedule'] ?? 'hourly';
            wp_schedule_event(time(), $schedule, 'immobridge_auto_import');
        }
    }

    /**
     * Create default taxonomy terms
     */
    private function createDefaultTaxonomyTerms(): void
    {
        // This will be called after taxonomies are registered
        add_action('init', function (): void {
            $taxonomyService = new \ImmoBridge\Services\PropertyTaxonomyService();
            $taxonomyService->createDefaultTerms();
        }, 20);
    }
}
