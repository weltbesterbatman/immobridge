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

use ImmoBridge\Admin\PropertyMetaBox;
use ImmoBridge\Core\Container\Container;
use ImmoBridge\Core\Container\ServiceProviderInterface;

final class AdminServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->singleton(OpenImmoImporter::class);
        $container->alias('admin.importer', OpenImmoImporter::class);
    }

    public function boot(Container $container): void
    {
        add_action('admin_menu', fn() => $this->addAdminMenu($container));
        add_action('admin_init', fn() => $this->registerSettings());
        add_action('admin_enqueue_scripts', fn($hook) => $this->enqueueAdminAssets($hook));

        // Register Meta Box for property post type
        $propertyMetaBox = new PropertyMetaBox();
        add_action('add_meta_boxes', [$propertyMetaBox, 'add']);
        add_action('save_post', [$propertyMetaBox, 'save']);

        add_action('wp_ajax_immobridge_start_import', fn() => $this->ajax_start_import());
        add_action('wp_ajax_immobridge_process_batch', fn() => $this->ajax_process_batch());
        add_action('admin_post_immobridge_delete_all', fn() => $this->handleDeleteAllAction());
        add_action('admin_post_immobridge_flush_permalinks', fn() => $this->handleFlushPermalinks());
        add_action('admin_notices', fn() => $this->showTemplateNotice());
    }

    private function registerSettings(): void
    {
        register_setting('immobridge_settings', 'immobridge_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitizeSettings'],
            'default' => []
        ]);
    }

    public function sanitizeSettings(?array $input): array
    {
        $sanitized = [];
        $input = $input ?? [];
        $sanitized['image_import'] = isset($input['image_import']);
        $sanitized['update_existing'] = isset($input['update_existing']);
        return $sanitized;
    }

    private function addAdminMenu(Container $container): void
    {
        add_menu_page(__('ImmoBridge', 'immobridge'), __('ImmoBridge', 'immobridge'), 'manage_options', 'immobridge', fn() => $this->renderDashboardPage($container), 'dashicons-building', 25);
        add_submenu_page('immobridge', __('OpenImmo Import', 'immobridge'), __('Import', 'immobridge'), 'manage_options', 'immobridge-import', fn() => $this->renderImportPage($container));
    }

    private function renderDashboardPage(Container $container): void
    {
        // Omitted for brevity
    }

    private function renderImportPage(Container $container): void
    {
        $uploadDir = wp_upload_dir();
        $inDir = $uploadDir['basedir'] . '/immobridge/in';

        echo '<div class="wrap">';
        echo '<h1>' . __('OpenImmo Import', 'immobridge') . '</h1>';

        $this->showImportResults();

        echo '<div id="immobridge-import-progress-container" style="display: none; margin-bottom: 20px; background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">';
        echo '<h2>' . __('Import in Progress...', 'immobridge') . '</h2>';
        echo '<progress id="immobridge-import-progress" value="0" max="100" style="width: 100%; height: 30px;"></progress>';
        echo '<p id="immobridge-import-status-text" style="font-weight: bold;"></p>';
        echo '<ul id="immobridge-import-log" style="height: 200px; overflow-y: scroll; background: #f6f7f7; padding: 10px; border: 1px solid #ddd; font-family: monospace; font-size: 12px;"></ul>';
        echo '</div>';

        $files = is_dir($inDir) ? glob($inDir . '/*.{xml,zip}', GLOB_BRACE) : [];
        if (!empty($files)) {
            echo '<div style="background: #fff; padding: 20px; border: 1px solid #ccd0d4; border-radius: 4px;">';
            echo '<h2>' . __('Files Ready for Import', 'immobridge') . '</h2>';
            echo '<table class="wp-list-table widefat fixed striped">';
            echo '<thead><tr><th>Filename</th><th>Size</th><th>Modified</th><th>Actions</th></tr></thead><tbody>';
            foreach ($files as $file) {
                $filename = basename($file);
                echo '<tr>';
                echo '<td><strong>' . esc_html($filename) . '</strong></td>';
                echo '<td>' . size_format(filesize($file)) . '</td>';
                echo '<td>' . date_i18n(get_option('date_format') . ' ' . get_option('time_format'), filemtime($file)) . '</td>';
                echo '<td><button class="button button-primary immobridge-import-button" data-file="' . esc_attr($filename) . '" data-nonce="' . wp_create_nonce('immobridge_ajax_import') . '">' . __('Import Now', 'immobridge') . '</button></td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            echo '</div>';
        } else {
            echo '<p>' . sprintf(__('No OpenImmo files found. Please upload files to: <code>%s</code>', 'immobridge'), $inDir) . '</p>';
        }

        echo '<div class="immobridge-delete-section" style="margin-top: 40px; background: #fff; border: 1px solid #d63638; color: #d63638; padding: 20px; border-radius: 4px;">';
        echo '<h2>' . __('Alle Immobiliendaten löschen', 'immobridge') . '</h2>';
        echo '<p class="description">' . __('Diese Aktion löscht dauerhaft alle importierten Immobilien, Bilder und zugehörigen Daten. Diese Aktion kann nicht rückgängig gemacht werden.', 'immobridge') . '</p>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '" onsubmit="return confirm(\'' . __('Sind Sie sicher, dass Sie alle Immobiliendaten löschen möchten? Diese Aktion kann nicht rückgängig gemacht werden.', 'immobridge') . '\');">';
        wp_nonce_field('immobridge_delete_all', 'immobridge_delete_all_nonce');
        echo '<input type="hidden" name="action" value="immobridge_delete_all">';
        submit_button(__('Alle Immobiliendaten löschen', 'immobridge'), 'delete');
        echo '</form>';
        echo '</div>';

        echo '</div>'; // .wrap
    }

    public function ajax_start_import() {
        check_ajax_referer('immobridge_ajax_import', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);

        $filename = sanitize_file_name($_POST['file']);
        $filePath = wp_upload_dir()['basedir'] . '/immobridge/in/' . $filename;
        if (!file_exists($filePath)) wp_send_json_error(['message' => 'File not found.']);

        $importer = new OpenImmoImporter();
        $job_id = 'immobridge_import_job';
        delete_transient($job_id);

        $xml_file_path = $filePath;
        $base_dir = dirname($filePath);
        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($fileExtension === 'zip') {
            $tempDir = wp_upload_dir()['basedir'] . '/immobridge-temp-' . uniqid();
            wp_mkdir_p($tempDir);
            
            $zip = new \ZipArchive();
            if ($zip->open($filePath) !== TRUE) {
                wp_send_json_error(['message' => 'Could not open ZIP file.']);
            }
            $zip->extractTo($tempDir);
            $zip->close();

            // Recursively find the first XML file
            $xml_file_path = '';
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($tempDir));
            foreach ($iterator as $file) {
                if (strtolower($file->getExtension()) === 'xml') {
                    $xml_file_path = $file->getPathname();
                    break;
                }
            }

            if (empty($xml_file_path)) {
                (new OpenImmoImporter())->deleteDirectory($tempDir);
                wp_send_json_error(['message' => 'No XML file found in ZIP archive.']);
            }
            $base_dir = dirname($xml_file_path);
        }

        $total_items = $importer->countPropertiesInFile($xml_file_path);
        if ($total_items === 0) wp_send_json_error(['message' => 'No properties found in the file.']);

        set_transient($job_id, [
            'xml_file' => $xml_file_path,
            'base_dir' => $base_dir,
            'is_zip' => ($fileExtension === 'zip'),
            'total' => $total_items, 
            'processed' => 0, 
            'log' => []
        ], HOUR_IN_SECONDS);

        wp_send_json_success(['job_id' => $job_id, 'total' => $total_items, 'status' => 'starting']);
    }

    public function ajax_process_batch() {
        check_ajax_referer('immobridge_ajax_import', 'nonce');
        if (!current_user_can('manage_options')) wp_send_json_error(['message' => 'Permission denied.']);

        $job_id = 'immobridge_import_job';
        $job_data = get_transient($job_id);

        if (!$job_data) {
            wp_send_json_success(['status' => 'complete']);
            return;
        }

        $importer = new OpenImmoImporter();
        $settings = get_option('immobridge_settings', []);
        $result = $importer->importBatch($job_data['xml_file'], $job_data['processed'], 5, $settings['image_import'] ?? true, $settings['update_existing'] ?? false, $job_data['base_dir']);
        
        $job_data['processed'] += $result['processed_in_batch'];
        $job_data['log'] = array_merge($job_data['log'], $result['log']);
        
        set_transient($job_id, $job_data, HOUR_IN_SECONDS);

        if ($job_data['processed'] >= $job_data['total']) {
            delete_transient($job_id);
            if ($job_data['is_zip'] && is_dir($job_data['base_dir'])) {
                (new OpenImmoImporter())->deleteDirectory($job_data['base_dir']);
            }
            wp_send_json_success(['status' => 'complete', 'processed' => $job_data['processed'], 'log' => $job_data['log']]);
        } else {
            wp_send_json_success(['status' => 'running', 'processed' => $job_data['processed'], 'total' => $job_data['total'], 'log' => $job_data['log']]);
        }
    }

    public function handleDeleteAllAction() {
        if (!isset($_POST['immobridge_delete_all_nonce']) || !wp_verify_nonce($_POST['immobridge_delete_all_nonce'], 'immobridge_delete_all')) {
            wp_die(__('Security check failed.', 'immobridge'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'immobridge'));
        }
        
        global $wpdb;
        
        $properties = get_posts(['post_type' => 'property', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids']);
        $deletedAttachments = [];
        
        // First, collect all attachments from properties
        foreach ($properties as $propertyId) {
            $attachments = get_attached_media('', $propertyId);
            foreach ($attachments as $attachment) {
                $deletedAttachments[] = $attachment->ID;
                // Try to delete attachment (file + DB entry)
                wp_delete_attachment($attachment->ID, true);
            }
            wp_delete_post($propertyId, true);
        }
        
        // Find ALL ImmoBridge attachments by meta field (including orphaned ones without files)
        // This ensures we catch "ghost" entries where the file was deleted but DB entry remains
        $immobridgeAttachments = get_posts([
            'post_type' => 'attachment',
            'posts_per_page' => -1,
            'post_status' => 'any',
            'fields' => 'ids',
            'meta_query' => [
                'relation' => 'OR',
                [
                    'key' => '_immobridge_imported',
                    'compare' => 'EXISTS',
                ],
                [
                    'key' => '_immobridge_property_id',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);
        
        foreach ($immobridgeAttachments as $attachmentId) {
            if (in_array($attachmentId, $deletedAttachments)) {
                continue; // Already processed
            }
            
            // Double-check: Only delete if it's really an ImmoBridge image
            $hasImmoBridgeMeta = get_post_meta($attachmentId, '_immobridge_imported', true) !== '' || 
                                 get_post_meta($attachmentId, '_immobridge_property_id', false) !== false;
            
            if ($hasImmoBridgeMeta) {
                // Try normal deletion first (handles file deletion if file exists)
                // This will delete the file if it exists, but may not remove DB entry if file is missing
                wp_delete_attachment($attachmentId, true);
                
                // ALWAYS force delete the DB entry to ensure "ghost" entries are removed
                // This is safe because we've already verified it's an ImmoBridge image
                $this->forceDeleteAttachment($attachmentId);
                
                $deletedAttachments[] = $attachmentId;
            }
        }
        
        // Also clean up attachments that reference deleted properties via meta
        // (in case some attachments weren't caught in the previous loop)
        if (!empty($properties)) {
            $orphanedByMeta = get_posts([
                'post_type' => 'attachment',
                'posts_per_page' => -1,
                'post_status' => 'any',
                'fields' => 'ids',
                'meta_query' => [
                    [
                        'key' => '_immobridge_property_id',
                        'value' => $properties,
                        'compare' => 'IN',
                    ],
                ],
            ]);
            
            foreach ($orphanedByMeta as $attachmentId) {
                if (!in_array($attachmentId, $deletedAttachments)) {
                    // Try normal deletion first
                    wp_delete_attachment($attachmentId, true);
                    // Always force delete DB entry to ensure cleanup
                    $this->forceDeleteAttachment($attachmentId);
                }
            }
        }
        
        wp_redirect(admin_url('admin.php?page=immobridge-import&deleted=true'));
        exit;
    }
    
    /**
     * Force delete attachment from database even if file doesn't exist
     * This removes "ghost" entries from the media library
     *
     * @param int $attachmentId Attachment post ID
     */
    private function forceDeleteAttachment(int $attachmentId): void
    {
        global $wpdb;
        
        // Check if attachment still exists (might have been deleted by wp_delete_attachment)
        $attachment = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'attachment'",
            $attachmentId
        ));
        
        if (!$attachment) {
            return; // Already deleted
        }
        
        // Delete all post meta for this attachment (use direct SQL to ensure it works)
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE post_id = %d",
            $attachmentId
        ));
        
        // Delete the attachment post itself
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->posts} WHERE ID = %d",
            $attachmentId
        ));
        
        // Clean up any term relationships
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->term_relationships} WHERE object_id = %d",
            $attachmentId
        ));
        
        // Clean cache aggressively
        clean_post_cache($attachmentId);
        wp_cache_delete($attachmentId, 'posts');
        wp_cache_delete($attachmentId, 'post_meta');
        
        // Force cache flush for attachments
        wp_cache_flush_group('posts');
        wp_cache_flush_group('post_meta');
    }

    private function showImportResults() {
        if (isset($_GET['deleted']) && $_GET['deleted'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Alle Immobiliendaten wurden erfolgreich gelöscht.', 'immobridge') . '</strong></p></div>';
        }
        if (isset($_GET['permalinks_flushed']) && $_GET['permalinks_flushed'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('Permalinks wurden erfolgreich aktualisiert.', 'immobridge') . '</strong></p></div>';
        }
    }

    /**
     * Handle permalink flush action
     */
    public function handleFlushPermalinks(): void {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'immobridge_flush_permalinks')) {
            wp_die(__('Security check failed.', 'immobridge'));
        }
        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'immobridge'));
        }
        
        flush_rewrite_rules(false);
        wp_redirect(admin_url('admin.php?page=immobridge-import&permalinks_flushed=true'));
        exit;
    }

    /**
     * Show admin notice about Bricks template assignment
     */
    private function showTemplateNotice(): void {
        $screen = get_current_screen();
        if (!$screen || strpos($screen->id, 'immobridge') === false) {
            return;
        }

        // Check if Bricks is active
        if (!defined('BRICKS_VERSION')) {
            return;
        }

        // Check if single property template exists and is assigned
        $templateAssigned = $this->checkPropertyTemplateAssigned();
        
        if (!$templateAssigned) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . __('ImmoBridge: Bricks Template zuweisen', 'immobridge') . '</strong></p>';
            echo '<p>' . __('Damit die Detailansicht von Immobilien funktioniert, muss ein Bricks-Template zugewiesen werden:', 'immobridge') . '</p>';
            echo '<ol>';
            echo '<li>' . __('Gehe zu <strong>Bricks → Templates</strong>', 'immobridge') . '</li>';
            echo '<li>' . __('Importiere das Template aus <code>wp-content/plugins/immobridge/templates/bricks/property-detail-template.json</code>', 'immobridge') . '</li>';
            echo '<li>' . __('Weise das Template als <strong>Single Template</strong> für den Post-Type <code>property</code> zu', 'immobridge') . '</li>';
            echo '</ol>';
            echo '<p>';
            echo '<a href="' . admin_url('edit.php?post_type=bricks_template') . '" class="button button-primary">' . __('Zu Bricks Templates', 'immobridge') . '</a> ';
            echo '<a href="' . wp_nonce_url(admin_url('admin-post.php?action=immobridge_flush_permalinks'), 'immobridge_flush_permalinks') . '" class="button">' . __('Permalinks aktualisieren', 'immobridge') . '</a>';
            echo '</p>';
            echo '</div>';
        }
    }

    /**
     * Check if a Bricks template is assigned for single property pages
     */
    private function checkPropertyTemplateAssigned(): bool {
        if (!defined('BRICKS_VERSION')) {
            return false;
        }

        // Check if there's a template assigned for single property
        $templates = get_posts([
            'post_type' => 'bricks_template',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => 'bricks_template_type',
                    'value' => 'content',
                ],
            ],
        ]);

        foreach ($templates as $template) {
            $conditions = get_post_meta($template->ID, 'bricks_template_conditions', true);
            if (is_array($conditions)) {
                foreach ($conditions as $condition) {
                    if (isset($condition['type']) && $condition['type'] === 'single' && 
                        isset($condition['postType']) && $condition['postType'] === 'property') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    private function enqueueAdminAssets(string $hook): void
    {
        if (strpos($hook, 'immobridge-import') === false) return;

        wp_add_inline_script('jquery-core', "
            jQuery(document).ready(function($) {
                let job_id = null;
                let nonce = null;
                let total_items = 0;

                $('.immobridge-import-button').on('click', function(e) {
                    e.preventDefault();
                    const button = $(this);
                    job_id = null;
                    nonce = button.data('nonce');
                    
                    button.prop('disabled', true).text('Starting...');
                    $('.immobridge-import-button').not(button).prop('disabled', true);
                    $('#immobridge-import-progress-container').show();
                    $('#immobridge-import-log').empty();

                    $.post(ajaxurl, {
                        action: 'immobridge_start_import',
                        nonce: nonce,
                        file: button.data('file')
                    }).done(response => {
                        if (response.success) {
                            job_id = response.data.job_id;
                            total_items = response.data.total;
                            $('#immobridge-import-status-text').text('Import started for ' + total_items + ' items...');
                            processBatch();
                        } else {
                            alert('Error: ' + response.data.message);
                            button.prop('disabled', false).text('Import Now');
                            $('.immobridge-import-button').not(button).prop('disabled', false);
                        }
                    });
                });

                function processBatch() {
                    if (!job_id) return;

                    $.post(ajaxurl, {
                        action: 'immobridge_process_batch',
                        nonce: nonce,
                        job_id: job_id
                    }).done(function(response) {
                        if (response.success) {
                            const data = response.data;
                            const processed_items = data.processed;
                            const progress = total_items > 0 ? (processed_items / total_items) * 100 : 0;
                            $('#immobridge-import-progress').val(progress);
                            $('#immobridge-import-status-text').text('Processed ' + processed_items + ' of ' + total_items + ' items...');
                            
                            if(data.log) {
                                data.log.forEach(function(line) {
                                    $('#immobridge-import-log').append('<li>' + line + '</li>');
                                });
                                $('#immobridge-import-log').scrollTop($('#immobridge-import-log')[0].scrollHeight);
                            }

                            if (data.status === 'running') {
                                processBatch();
                            } else {
                                $('#immobridge-import-status-text').text('Import complete! Processed ' + processed_items + ' items.');
                                alert('Import complete!');
                                window.location.reload();
                            }
                        } else {
                            alert('An error occurred: ' + response.data.message);
                            $('.immobridge-import-button').prop('disabled', false).text('Import Now');
                        }
                    }).fail(function(jqXHR, textStatus, errorThrown) {
                        alert('A critical error occurred during the import.');
                        $('.immobridge-import-button').prop('disabled', false).text('Import Now');
                    });
                }
            });
        ");
    }
}
