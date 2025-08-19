<?php
/**
 * Delete All Properties Script
 *
 * This script safely removes all existing property posts and their meta data
 * to prepare for a clean import with the new data structure.
 *
 * @package ImmoBridge
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Delete all property posts and their associated data
 */
function immobridge_delete_all_properties(): void
{
    // Get all property posts
    $properties = get_posts([
        'post_type' => 'property',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids'
    ]);

    $deletedCount = 0;
    $totalProperties = count($properties);

    echo "<h2>ImmoBridge Property Cleanup</h2>\n";
    echo "<p>Found {$totalProperties} properties to delete...</p>\n";
    echo "<div style='background: #f1f1f1; padding: 10px; margin: 10px 0; font-family: monospace;'>\n";

    foreach ($properties as $propertyId) {
        $propertyTitle = get_the_title($propertyId);
        echo "Deleting: #{$propertyId} - {$propertyTitle}<br>\n";
        
        // Delete all meta data first
        $metaKeys = get_post_meta($propertyId);
        foreach ($metaKeys as $metaKey => $values) {
            delete_post_meta($propertyId, $metaKey);
        }
        
        // Delete the post permanently
        $result = wp_delete_post($propertyId, true);
        
        if ($result) {
            $deletedCount++;
            echo "  ✓ Successfully deleted<br>\n";
        } else {
            echo "  ✗ Failed to delete<br>\n";
        }
        
        echo "<br>\n";
        
        // Flush output buffer to show progress in real-time
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    // Also clean up any orphaned taxonomy terms
    $taxonomies = ['property_type', 'property_status', 'property_location', 'property_features'];
    foreach ($taxonomies as $taxonomy) {
        if (taxonomy_exists($taxonomy)) {
            $terms = get_terms([
                'taxonomy' => $taxonomy,
                'hide_empty' => false,
                'fields' => 'ids'
            ]);
            
            foreach ($terms as $termId) {
                wp_delete_term($termId, $taxonomy);
            }
            echo "Cleaned up taxonomy: {$taxonomy}<br>\n";
        }
    }

    echo "</div>\n";
    echo "<h3>Cleanup Complete!</h3>\n";
    echo "<p><strong>Summary:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Total properties processed: {$totalProperties}</li>\n";
    echo "<li>Successfully deleted: {$deletedCount}</li>\n";
    echo "<li>Taxonomy terms cleaned up</li>\n";
    echo "<li>Database is now ready for fresh import</li>\n";
    echo "</ul>\n";
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin: 10px 0;'>\n";
    echo "<strong>Next Steps:</strong><br>\n";
    echo "1. Go to WordPress Admin → ImmoBridge → Import<br>\n";
    echo "2. Import your OpenImmo data<br>\n";
    echo "3. Check Bricks Builder for field visibility<br>\n";
    echo "</div>\n";
}

/**
 * Run cleanup if accessed directly with proper authentication
 */
if (isset($_GET['run_cleanup']) && $_GET['run_cleanup'] === 'true') {
    // Security check - only allow for administrators
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions to run cleanup.');
    }
    
    // Add nonce verification for security
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'immobridge_cleanup')) {
        wp_die('Security check failed.');
    }
    
    // Set longer execution time for large datasets
    set_time_limit(300); // 5 minutes
    
    // Start output buffering for real-time progress
    if (ob_get_level() == 0) {
        ob_start();
    }
    
    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>ImmoBridge Property Cleanup</title></head><body>\n";
    
    immobridge_delete_all_properties();
    
    echo "</body></html>\n";
    
    if (ob_get_level()) {
        ob_end_flush();
    }
    
    exit;
}

// Display cleanup interface if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'delete-properties.php') {
    // Security check - only allow for administrators
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions to access cleanup script.');
    }
    
    $nonce = wp_create_nonce('immobridge_cleanup');
    $cleanupUrl = add_query_arg([
        'run_cleanup' => 'true',
        '_wpnonce' => $nonce
    ]);
    
    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>ImmoBridge Property Cleanup</title></head><body>\n";
    echo "<h1>ImmoBridge Property Cleanup</h1>\n";
    echo "<p>This script will permanently delete all existing property posts and their meta data.</p>\n";
    echo "<p><strong>What it does:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Deletes all property posts (all statuses)</li>\n";
    echo "<li>Removes all associated meta data</li>\n";
    echo "<li>Cleans up taxonomy terms</li>\n";
    echo "<li>Prepares database for fresh import</li>\n";
    echo "</ul>\n";
    echo "<p><strong>Warning:</strong> This operation cannot be undone. Please backup your database before proceeding.</p>\n";
    echo "<p><a href='{$cleanupUrl}' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;' onclick='return confirm(\"Are you sure you want to delete all properties? This cannot be undone!\")'>Delete All Properties</a></p>\n";
    echo "</body></html>\n";
    exit;
}
