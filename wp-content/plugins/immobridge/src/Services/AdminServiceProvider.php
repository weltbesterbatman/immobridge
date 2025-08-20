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

        add_action('wp_ajax_immobridge_start_import', fn() => $this->ajax_start_import());
        add_action('wp_ajax_immobridge_process_batch', fn() => $this->ajax_process_batch());
        add_action('admin_post_immobridge_delete_all', fn() => $this->handleDeleteAllAction());
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
        echo '<h2>' . __('Delete All Property Data', 'immobridge') . '</h2>';
        echo '<p class="description">' . __('This will permanently delete all imported properties, images, and associated data. This action cannot be undone.', 'immobridge') . '</p>';
        echo '<form method="post" action="' . admin_url('admin-post.php') . '" onsubmit="return confirm(\'' . __('Are you sure you want to delete all property data? This cannot be undone.', 'immobridge') . '\');">';
        wp_nonce_field('immobridge_delete_all', 'immobridge_delete_all_nonce');
        echo '<input type="hidden" name="action" value="immobridge_delete_all">';
        submit_button(__('Delete All Data', 'immobridge'), 'delete');
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
        $properties = get_posts(['post_type' => 'property', 'posts_per_page' => -1, 'post_status' => 'any', 'fields' => 'ids']);
        foreach ($properties as $propertyId) {
            $attachments = get_attached_media('', $propertyId);
            foreach ($attachments as $attachment) {
                wp_delete_attachment($attachment->ID, true);
            }
            wp_delete_post($propertyId, true);
        }
        wp_redirect(admin_url('admin.php?page=immobridge-import&deleted=true'));
        exit;
    }

    private function showImportResults() {
        if (isset($_GET['deleted']) && $_GET['deleted'] === 'true') {
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . __('All property data has been successfully deleted.', 'immobridge') . '</strong></p></div>';
        }
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
