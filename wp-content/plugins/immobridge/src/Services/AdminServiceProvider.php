<?php
/**
 * Admin Service Provider
 *
 * @package ImmoBridge
 * @subpackage Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Services;

use ImmoBridge\Core\Container\Container;
use ImmoBridge\Core\Container\ServiceProviderInterface;

/**
 * Admin Service Provider
 *
 * Registers admin-related services in the dependency injection container.
 *
 * @since 1.0.0
 */
final class AdminServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services in the container
     *
     * @param Container $container The dependency injection container
     */
    public function register(Container $container): void
    {
        // Register OpenImmo Importer
        $container->singleton(OpenImmoImporter::class);
        $container->alias('admin.importer', OpenImmoImporter::class);
    }

    /**
     * Boot services after all providers have been registered
     *
     * @param Container $container The dependency injection container
     */
    public function boot(Container $container): void
    {
        // Add admin menu
        add_action('admin_menu', function () use ($container): void {
            $this->addAdminMenu($container);
        });

        // Handle import actions
        add_action('admin_post_immobridge_import', function () use ($container): void {
            $this->handleImportAction($container);
        });

        // Enqueue admin assets
        add_action('admin_enqueue_scripts', function (string $hook): void {
            $this->enqueueAdminAssets($hook);
        });

        // Register settings
        add_action('admin_init', function (): void {
            $this->registerSettings();
        });
    }

    /**
     * Register plugin settings
     */
    private function registerSettings(): void
    {
        register_setting(
            'immobridge_settings',
            'immobridge_settings',
            [
                'type' => 'array',
                'description' => 'ImmoBridge main settings.',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => []
            ]
        );
    }

    /**
     * Sanitize settings input
     */
    public function sanitizeSettings(?array $input): array
    {
        $sanitized = [];

        // Handle null input (when no settings are submitted)
        if ($input === null) {
            $input = [];
        }

        // For checkboxes: if the key exists in input, it's checked (true), otherwise it's unchecked (false)
        // This is important because unchecked checkboxes are not sent in the form data
        $sanitized['auto_import'] = isset($input['auto_import']) && $input['auto_import'] ? true : false;
        $sanitized['image_import'] = isset($input['image_import']) && $input['image_import'] ? true : false;
        $sanitized['update_existing'] = isset($input['update_existing']) && $input['update_existing'] ? true : false;

        return $sanitized;
    }

    /**
     * Add admin menu pages
     */
    private function addAdminMenu(Container $container): void
    {
        // Add main menu page
        add_menu_page(
            __('ImmoBridge', 'immobridge'),
            __('ImmoBridge', 'immobridge'),
            'manage_options',
            'immobridge',
            function () use ($container): void {
                $this->renderDashboardPage($container);
            },
            'dashicons-building',
            25
        );

        // Add import submenu
        add_submenu_page(
            'immobridge',
            __('OpenImmo Import', 'immobridge'),
            __('Import', 'immobridge'),
            'manage_options',
            'immobridge-import',
            function () use ($container): void {
                $this->renderImportPage($container);
            }
        );

        // Add settings submenu
        add_submenu_page(
            'immobridge',
            __('Settings', 'immobridge'),
            __('Settings', 'immobridge'),
            'manage_options',
            'immobridge-settings',
            function (): void {
                $this->renderSettingsPage();
            }
        );
    }

    /**
     * Render dashboard page
     */
    private function renderDashboardPage(Container $container): void
    {
        $propertyCount = wp_count_posts('property');
        $lastImport = get_option('immobridge_last_import', '');
        $importLog = get_option('immobridge_import_log', '');

        echo '<div class="wrap">';
        echo '<h1>' . __('ImmoBridge Dashboard', 'immobridge') . '</h1>';
        
        echo '<div class="immobridge-dashboard">';
        
        // Statistics cards
        echo '<div class="immobridge-stats">';
        echo '<div class="stat-card">';
        echo '<h3>' . __('Total Properties', 'immobridge') . '</h3>';
        echo '<div class="stat-number">' . ($propertyCount->publish ?? 0) . '</div>';
        echo '</div>';
        
        echo '<div class="stat-card">';
        echo '<h3>' . __('Last Import', 'immobridge') . '</h3>';
        echo '<div class="stat-text">' . ($lastImport ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($lastImport)) : __('Never', 'immobridge')) . '</div>';
        echo '</div>';
        echo '</div>';

        // Quick actions
        echo '<div class="immobridge-actions">';
        echo '<h2>' . __('Quick Actions', 'immobridge') . '</h2>';
        echo '<a href="' . admin_url('admin.php?page=immobridge-import') . '" class="button button-primary">' . __('Import OpenImmo Data', 'immobridge') . '</a>';
        echo '<a href="' . admin_url('edit.php?post_type=property') . '" class="button">' . __('Manage Properties', 'immobridge') . '</a>';
        echo '<a href="' . admin_url('wp-content/plugins/immobridge/migrate-meta-keys.php') . '" class="button" target="_blank">' . __('Run Migration Script', 'immobridge') . '</a>';
        echo '</div>';

        // Recent import log
        if ($importLog) {
            echo '<div class="immobridge-log">';
            echo '<h2>' . __('Recent Import Log', 'immobridge') . '</h2>';
            echo '<textarea readonly rows="10" style="width: 100%; font-family: monospace;">' . esc_textarea($importLog) . '</textarea>';
            echo '</div>';
        }

        echo '</div>';
        echo '</div>';

        // Add dashboard styles
        echo '<style>
        .immobridge-dashboard { margin-top: 20px; }
        .immobridge-stats { display: flex; gap: 20px; margin-bottom: 30px; }
        .stat-card { background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px; min-width: 200px; }
        .stat-card h3 { margin: 0 0 10px 0; color: #646970; font-size: 14px; }
        .stat-number { font-size: 32px; font-weight: bold; color: #1d2327; }
        .stat-text { font-size: 16px; color: #1d2327; }
        .immobridge-actions { margin-bottom: 30px; }
        .immobridge-actions .button { margin-right: 10px; }
        .immobridge-log textarea { background: #f6f7f7; border: 1px solid #ddd; }
        </style>';
    }

    /**
     * Render import page
     */
    private function renderImportPage(Container $container): void
    {
        $uploadDir = wp_upload_dir();
        $importBaseDir = $uploadDir['basedir'] . '/immobridge';
        $inDir = $importBaseDir . '/in';
        $processingDir = $importBaseDir . '/processing';
        $archiveDir = $importBaseDir . '/archive';
        $errorDir = $importBaseDir . '/error';

        echo '<div class="wrap">';
        echo '<h1>' . __('OpenImmo Import', 'immobridge') . '</h1>';

        // Show import results if any
        if (isset($_GET['import_result'])) {
            $this->showImportResults();
        }

        // Import directory info
        echo '<div class="immobridge-import-info">';
        echo '<h2>' . __('Import Directory', 'immobridge') . '</h2>';
        echo '<p>' . sprintf(__('Upload your OpenImmo XML or ZIP files to: <code>%s</code>', 'immobridge'), $inDir) . '</p>';
        echo '<p>' . __('Files will be automatically processed and moved to the appropriate directories.', 'immobridge') . '</p>';
        
        // Directory status
        echo '<div class="immobridge-directory-status">';
        echo '<div class="status-grid">';
        
        $directories = [
            'in' => ['path' => $inDir, 'label' => __('Incoming Files', 'immobridge'), 'color' => '#0073aa'],
            'processing' => ['path' => $processingDir, 'label' => __('Processing', 'immobridge'), 'color' => '#f56e28'],
            'archive' => ['path' => $archiveDir, 'label' => __('Archived', 'immobridge'), 'color' => '#00a32a'],
            'error' => ['path' => $errorDir, 'label' => __('Errors', 'immobridge'), 'color' => '#d63638']
        ];
        
        foreach ($directories as $key => $dir) {
            $fileCount = 0;
            if (is_dir($dir['path'])) {
                $files = glob($dir['path'] . '/*.{xml,zip}', GLOB_BRACE);
                $fileCount = count($files);
            }
            
            echo '<div class="status-card" style="border-left: 4px solid ' . $dir['color'] . ';">';
            echo '<h3>' . $dir['label'] . '</h3>';
            echo '<div class="file-count">' . $fileCount . ' ' . __('files', 'immobridge') . '</div>';
            echo '</div>';
        }
        
        echo '</div>';
        echo '</div>';
        echo '</div>';

        // Available files for import
        if (is_dir($inDir)) {
            $files = glob($inDir . '/*.{xml,zip}', GLOB_BRACE);
            if (!empty($files)) {
                echo '<div class="immobridge-available-files">';
                echo '<h2>' . __('Files Ready for Import', 'immobridge') . '</h2>';
                
                echo '<form method="post" action="' . admin_url('admin-post.php') . '">';
                wp_nonce_field('immobridge_import', 'immobridge_import_nonce');
                echo '<input type="hidden" name="action" value="immobridge_import">';
                
                echo '<table class="wp-list-table widefat fixed striped">';
                echo '<thead>';
                echo '<tr>';
                echo '<th scope="col" class="manage-column">' . __('Filename', 'immobridge') . '</th>';
                echo '<th scope="col" class="manage-column">' . __('Size', 'immobridge') . '</th>';
                echo '<th scope="col" class="manage-column">' . __('Modified', 'immobridge') . '</th>';
                echo '<th scope="col" class="manage-column">' . __('Actions', 'immobridge') . '</th>';
                echo '</tr>';
                echo '</thead>';
                echo '<tbody>';
                
                foreach ($files as $file) {
                    $filename = basename($file);
                    $filesize = size_format(filesize($file));
                    $modified = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($file));
                    
                    echo '<tr>';
                    echo '<td><strong>' . esc_html($filename) . '</strong></td>';
                    echo '<td>' . $filesize . '</td>';
                    echo '<td>' . $modified . '</td>';
                    echo '<td>';
                    echo '<a href="' . admin_url('admin-post.php?action=immobridge_import&file=' . urlencode($filename) . '&_wpnonce=' . wp_create_nonce('immobridge_import')) . '" class="button button-primary">' . __('Import Now', 'immobridge') . '</a>';
                    echo '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody>';
                echo '</table>';
                echo '</form>';
                echo '</div>';
            } else {
                echo '<div class="immobridge-no-files">';
                echo '<h2>' . __('No Files Available', 'immobridge') . '</h2>';
                echo '<p>' . sprintf(__('No OpenImmo files found in the import directory. Please upload files to: <code>%s</code>', 'immobridge'), $inDir) . '</p>';
                echo '</div>';
            }
        }

        // Processing and archived files
        $this->renderFileHistory($processingDir, $archiveDir, $errorDir);

        // Import settings
        echo '<div class="immobridge-import-settings">';
        echo '<h2>' . __('Import Settings', 'immobridge') . '</h2>';
        echo '<form method="post" action="options.php">';
        settings_fields('immobridge_settings');
        
        $settings = get_option('immobridge_settings', []);
        
        echo '<table class="form-table">';
        echo '<tr>';
        echo '<th scope="row">' . __('Auto Import', 'immobridge') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="immobridge_settings[auto_import]" value="1" ' . checked($settings['auto_import'] ?? false, true, false) . '> ' . __('Automatically process files in import directory', 'immobridge') . '</label>';
        echo '<p class="description">' . __('When enabled, files will be automatically imported every hour.', 'immobridge') . '</p>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Import Images', 'immobridge') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="immobridge_settings[image_import]" value="1" ' . checked($settings['image_import'] ?? true, true, false) . '> ' . __('Import property images', 'immobridge') . '</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Update Existing', 'immobridge') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="immobridge_settings[update_existing]" value="1" ' . checked($settings['update_existing'] ?? false, true, false) . '> ' . __('Update existing properties with same OpenImmo ID', 'immobridge') . '</label>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';
        
        submit_button(__('Save Settings', 'immobridge'));
        echo '</form>';
        echo '</div>';

        echo '</div>';
    }

    /**
     * Render settings page
     */
    private function renderSettingsPage(): void
    {
        echo '<div class="wrap">';
        echo '<h1>' . __('ImmoBridge Settings', 'immobridge') . '</h1>';
        
        echo '<form method="post" action="options.php">';
        settings_fields('immobridge_settings');
        do_settings_sections('immobridge_settings');
        
        echo '<table class="form-table">';
        
        echo '<tr>';
        echo '<th scope="row">' . __('Default Import Settings', 'immobridge') . '</th>';
        echo '<td>';
        echo '<label><input type="checkbox" name="immobridge_default_import_images" value="1" ' . checked(get_option('immobridge_default_import_images', 1), 1, false) . '> ' . __('Import images by default', 'immobridge') . '</label><br>';
        echo '<label><input type="checkbox" name="immobridge_default_update_existing" value="1" ' . checked(get_option('immobridge_default_update_existing', 0), 1, false) . '> ' . __('Update existing properties by default', 'immobridge') . '</label>';
        echo '</td>';
        echo '</tr>';
        
        echo '</table>';
        
        submit_button();
        echo '</form>';
        echo '</div>';
    }

    /**
     * Handle import action
     */
    private function handleImportAction(Container $container): void
    {
        // Verify nonce for both POST and GET requests
        $nonce = $_REQUEST['immobridge_import_nonce'] ?? $_REQUEST['_wpnonce'] ?? null;
        if (!wp_verify_nonce($nonce, 'immobridge_import')) {
            wp_die(__('Security check failed.', 'immobridge'));
        }

        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'immobridge'));
        }

        $importer = $container->get('admin.importer');
        $results = null;

        // Handle directory-based file import
        if (isset($_GET['file'])) {
            $filename = sanitize_file_name($_GET['file']);
            $uploadDir = wp_upload_dir();
            $inDir = $uploadDir['basedir'] . '/immobridge/in';
            $filePath = $inDir . '/' . $filename;
            
            if (file_exists($filePath)) {
                $settings = get_option('immobridge_settings', []);
                $importImages = $settings['image_import'] ?? true;
                $updateExisting = $settings['update_existing'] ?? false;
                
                // Debug logging
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log('ImmoBridge Admin: Retrieved settings: ' . print_r($settings, true));
                    error_log('ImmoBridge Admin: Import images setting: ' . ($importImages ? 'true' : 'false'));
                    error_log('ImmoBridge Admin: Update existing setting: ' . ($updateExisting ? 'true' : 'false'));
                    error_log('ImmoBridge Admin: Starting import for file: ' . $filename);
                }
                
                $results = $importer->importFromDirectory($filePath, $importImages, $updateExisting);
            }
        }

        // Redirect with results
        $redirect_url = admin_url('admin.php?page=immobridge-import');
        
        if ($results) {
            $redirect_url = add_query_arg([
                'import_result' => 'success',
                'imported' => $results['imported'] ?? 0,
                'updated' => $results['updated'] ?? 0,
                'errors' => $results['errors'] ?? 0
            ], $redirect_url);
        } else {
            $redirect_url = add_query_arg('import_result', 'error', $redirect_url);
        }

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Show import results
     */
    private function showImportResults(): void
    {
        $result = $_GET['import_result'] ?? '';
        
        if ($result === 'success') {
            $imported = intval($_GET['imported'] ?? 0);
            $updated = intval($_GET['updated'] ?? 0);
            $errors = intval($_GET['errors'] ?? 0);
            
            echo '<div class="notice notice-success is-dismissible">';
            echo '<p><strong>' . __('Import completed successfully!', 'immobridge') . '</strong></p>';
            echo '<ul>';
            if ($imported > 0) {
                echo '<li>' . sprintf(_n('%d property imported', '%d properties imported', $imported, 'immobridge'), $imported) . '</li>';
            }
            if ($updated > 0) {
                echo '<li>' . sprintf(_n('%d property updated', '%d properties updated', $updated, 'immobridge'), $updated) . '</li>';
            }
            if ($errors > 0) {
                echo '<li>' . sprintf(_n('%d error occurred', '%d errors occurred', $errors, 'immobridge'), $errors) . '</li>';
            }
            echo '</ul>';
            echo '</div>';
        } elseif ($result === 'error') {
            echo '<div class="notice notice-error is-dismissible">';
            echo '<p><strong>' . __('Import failed!', 'immobridge') . '</strong></p>';
            echo '<p>' . __('Please check the file format and try again.', 'immobridge') . '</p>';
            echo '</div>';
        }
    }

    /**
     * Render file history (processing, archive, error directories)
     */
    private function renderFileHistory(string $processingDir, string $archiveDir, string $errorDir): void
    {
        echo '<div class="immobridge-file-history">';
        echo '<h2>' . __('File History', 'immobridge') . '</h2>';
        
        // Processing files
        if (is_dir($processingDir)) {
            $processingFiles = glob($processingDir . '/*.{xml,zip}', GLOB_BRACE);
            if (!empty($processingFiles)) {
                echo '<h3>' . __('Currently Processing', 'immobridge') . '</h3>';
                echo '<ul class="file-list processing">';
                foreach ($processingFiles as $file) {
                    $filename = basename($file);
                    $modified = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($file));
                    echo '<li><span class="filename">' . esc_html($filename) . '</span> <span class="date">(' . $modified . ')</span></li>';
                }
                echo '</ul>';
            }
        }
        
        // Recent archived files (last 10)
        if (is_dir($archiveDir)) {
            $archivedFiles = glob($archiveDir . '/*.{xml,zip}', GLOB_BRACE);
            if (!empty($archivedFiles)) {
                // Sort by modification time, newest first
                usort($archivedFiles, function($a, $b) {
                    return filemtime($b) - filemtime($a);
                });
                
                echo '<h3>' . __('Recently Imported (Last 10)', 'immobridge') . '</h3>';
                echo '<ul class="file-list archived">';
                foreach (array_slice($archivedFiles, 0, 10) as $file) {
                    $filename = basename($file);
                    $modified = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($file));
                    echo '<li><span class="filename">' . esc_html($filename) . '</span> <span class="date">(' . $modified . ')</span></li>';
                }
                echo '</ul>';
            }
        }
        
        // Error files
        if (is_dir($errorDir)) {
            $errorFiles = glob($errorDir . '/*.{xml,zip}', GLOB_BRACE);
            if (!empty($errorFiles)) {
                echo '<h3>' . __('Failed Imports', 'immobridge') . '</h3>';
                echo '<ul class="file-list errors">';
                foreach ($errorFiles as $file) {
                    $filename = basename($file);
                    $modified = date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($file));
                    echo '<li><span class="filename">' . esc_html($filename) . '</span> <span class="date">(' . $modified . ')</span></li>';
                }
                echo '</ul>';
            }
        }
        
        echo '</div>';
    }

    /**
     * Enqueue admin assets
     */
    private function enqueueAdminAssets(string $hook): void
    {
        // Only load on our admin pages
        if (strpos($hook, 'immobridge') === false) {
            return;
        }

        // Enqueue WordPress media scripts for file uploads
        wp_enqueue_media();
        
        // Add custom admin styles
        wp_add_inline_style('wp-admin', '
            /* Import Directory Status */
            .immobridge-import-info { background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px; border-radius: 4px; }
            .immobridge-directory-status { margin-top: 20px; }
            .status-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-top: 15px; }
            .status-card { background: #fff; padding: 15px; border: 1px solid #ccd0d4; border-radius: 4px; text-align: center; }
            .status-card h3 { margin: 0 0 10px 0; font-size: 14px; color: #646970; }
            .file-count { font-size: 24px; font-weight: bold; color: #1d2327; }
            
            /* Available Files Table */
            .immobridge-available-files { background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px; border-radius: 4px; }
            .immobridge-no-files { background: #fff; padding: 40px; border: 1px solid #ccd0d4; margin-top: 20px; border-radius: 4px; text-align: center; }
            .immobridge-no-files h2 { color: #646970; margin-bottom: 10px; }
            
            /* File History */
            .immobridge-file-history { background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px; border-radius: 4px; }
            .file-list { list-style: none; padding: 0; margin: 10px 0; }
            .file-list li { padding: 8px 0; border-bottom: 1px solid #f0f0f1; display: flex; justify-content: space-between; align-items: center; }
            .file-list li:last-child { border-bottom: none; }
            .file-list .filename { font-weight: 500; }
            .file-list .date { color: #646970; font-size: 13px; }
            .file-list.processing li { border-left: 3px solid #f56e28; padding-left: 10px; }
            .file-list.archived li { border-left: 3px solid #00a32a; padding-left: 10px; }
            .file-list.errors li { border-left: 3px solid #d63638; padding-left: 10px; }
            
            /* Import Settings */
            .immobridge-import-settings { background: #fff; padding: 20px; border: 1px solid #ccd0d4; margin-top: 20px; border-radius: 4px; }
            
            /* Responsive adjustments */
            @media (max-width: 782px) {
                .status-grid { grid-template-columns: 1fr; }
                .file-list li { flex-direction: column; align-items: flex-start; }
                .file-list .date { margin-top: 5px; }
            }
        ');
    }
}
