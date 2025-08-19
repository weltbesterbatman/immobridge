<?php
/**
 * Standalone script to delete all properties
 * Run this from the WordPress root directory
 */

// Load WordPress
require_once('wp-config.php');
require_once('wp-load.php');

// Check if we're running from command line or have admin privileges
if (php_sapi_name() !== 'cli' && (!is_user_logged_in() || !current_user_can('manage_options'))) {
    die('Access denied. Run from command line or login as admin.');
}

echo "Starting property deletion...\n";

// Get all property posts
$properties = get_posts([
    'post_type' => 'property',
    'post_status' => 'any',
    'numberposts' => -1,
    'fields' => 'ids'
]);

$deletedCount = 0;
$totalProperties = count($properties);

echo "Found {$totalProperties} properties to delete...\n";

foreach ($properties as $propertyId) {
    $propertyTitle = get_the_title($propertyId);
    echo "Deleting: #{$propertyId} - {$propertyTitle}\n";
    
    // Delete all meta data first
    $metaKeys = get_post_meta($propertyId);
    foreach ($metaKeys as $metaKey => $values) {
        delete_post_meta($propertyId, $metaKey);
    }
    
    // Delete the post permanently
    $result = wp_delete_post($propertyId, true);
    
    if ($result) {
        $deletedCount++;
        echo "  ✓ Successfully deleted\n";
    } else {
        echo "  ✗ Failed to delete\n";
    }
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
        echo "Cleaned up taxonomy: {$taxonomy}\n";
    }
}

echo "\nCleanup Complete!\n";
echo "Summary:\n";
echo "- Total properties processed: {$totalProperties}\n";
echo "- Successfully deleted: {$deletedCount}\n";
echo "- Taxonomy terms cleaned up\n";
echo "- Database is now ready for fresh import\n";
