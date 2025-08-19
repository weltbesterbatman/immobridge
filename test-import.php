<?php
/**
 * Test script to trigger ImmoBridge import directly
 */

// Load WordPress
require_once('wp-config.php');
require_once('wp-load.php');

// Check if we're in WordPress admin context
if (!is_admin()) {
    define('WP_ADMIN', true);
}

// Load the importer
use ImmoBridge\Services\OpenImmoImporter;

echo "=== ImmoBridge Import Test ===\n";
echo "Testing image path resolution fix...\n\n";

// Check if ZIP file exists
$zipPath = wp_upload_dir()['basedir'] . '/immobridge/in/openimmo-import-data.zip';
if (!file_exists($zipPath)) {
    echo "ERROR: ZIP file not found at: $zipPath\n";
    exit(1);
}

echo "Found ZIP file: " . basename($zipPath) . "\n";
echo "File size: " . number_format(filesize($zipPath)) . " bytes\n\n";

try {
    // Create importer instance
    $importer = new OpenImmoImporter();
    
    // Run import with images enabled and update existing properties
    echo "Starting import...\n";
    $results = $importer->importFromDirectory($zipPath, true, true);
    
    echo "\n=== IMPORT RESULTS ===\n";
    echo "Imported: " . $results['imported'] . "\n";
    echo "Updated: " . $results['updated'] . "\n";
    echo "Errors: " . $results['errors'] . "\n";
    
    echo "\n=== IMPORT LOG ===\n";
    foreach ($results['log'] as $logEntry) {
        echo $logEntry . "\n";
    }
    
    // Check if any images were successfully imported
    echo "\n=== IMAGE IMPORT CHECK ===\n";
    $attachments = get_posts([
        'post_type' => 'attachment',
        'post_mime_type' => 'image',
        'posts_per_page' => 10,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    echo "Recent image attachments found: " . count($attachments) . "\n";
    foreach ($attachments as $attachment) {
        echo "- " . $attachment->post_title . " (ID: " . $attachment->ID . ")\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== Test completed ===\n";
