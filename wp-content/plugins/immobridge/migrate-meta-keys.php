<?php
/**
 * Migration Script: Convert Meta Keys for Bricks Builder Visibility
 *
 * This script migrates existing property meta data from underscore-prefixed keys
 * (e.g., _immobridge_price) to clean keys (e.g., price) to make them visible
 * to Bricks Builder's Dynamic Data picker.
 *
 * @package ImmoBridge
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Migrate property meta keys from old format to new format
 */
function immobridge_migrate_meta_keys(): void
{
    // Meta key mapping: old_key => new_key
    $metaKeyMapping = [
        '_immobridge_type' => 'type',
        '_immobridge_status' => 'status',
        '_immobridge_price' => 'price',
        '_immobridge_price_type' => 'price_type',
        '_immobridge_living_area' => 'living_area',
        '_immobridge_total_area' => 'total_area',
        '_immobridge_rooms' => 'rooms',
        '_immobridge_bedrooms' => 'bedrooms',
        '_immobridge_bathrooms' => 'bathrooms',
        '_immobridge_address' => 'address',
        '_immobridge_city' => 'city',
        '_immobridge_zip_code' => 'zip_code',
        '_immobridge_country' => 'country',
        '_immobridge_latitude' => 'latitude',
        '_immobridge_longitude' => 'longitude',
        '_immobridge_openimmo_id' => 'openimmo_id',
        '_immobridge_imported_at' => 'imported_at',
        '_immobridge_images' => 'images',
        '_immobridge_documents' => 'documents',
    ];

    // Get all property posts
    $properties = get_posts([
        'post_type' => 'property',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields' => 'ids'
    ]);

    $migratedCount = 0;
    $totalProperties = count($properties);

    echo "<h2>ImmoBridge Meta Key Migration</h2>\n";
    echo "<p>Found {$totalProperties} properties to migrate...</p>\n";
    echo "<div style='background: #f1f1f1; padding: 10px; margin: 10px 0; font-family: monospace;'>\n";

    foreach ($properties as $propertyId) {
        $propertyTitle = get_the_title($propertyId);
        echo "Processing: #{$propertyId} - {$propertyTitle}<br>\n";
        
        $migratedFields = 0;
        
        foreach ($metaKeyMapping as $oldKey => $newKey) {
            $value = get_post_meta($propertyId, $oldKey, true);
            
            if ($value !== '' && $value !== false) {
                // Add the new meta key
                update_post_meta($propertyId, $newKey, $value);
                
                // Keep the old meta key for backward compatibility (don't delete)
                // This ensures existing functionality continues to work
                
                $migratedFields++;
                echo "  ✓ {$oldKey} → {$newKey}<br>\n";
            }
        }
        
        if ($migratedFields > 0) {
            $migratedCount++;
            echo "  → Migrated {$migratedFields} fields<br>\n";
        } else {
            echo "  → No fields to migrate<br>\n";
        }
        
        echo "<br>\n";
        
        // Flush output buffer to show progress in real-time
        if (ob_get_level()) {
            ob_flush();
        }
        flush();
    }

    echo "</div>\n";
    echo "<h3>Migration Complete!</h3>\n";
    echo "<p><strong>Summary:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Total properties processed: {$totalProperties}</li>\n";
    echo "<li>Properties with migrated data: {$migratedCount}</li>\n";
    echo "<li>Meta fields are now visible to Bricks Builder</li>\n";
    echo "<li>Old meta keys preserved for backward compatibility</li>\n";
    echo "</ul>\n";
    
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; margin: 10px 0;'>\n";
    echo "<strong>Next Steps:</strong><br>\n";
    echo "1. Test Bricks Builder Dynamic Data picker - fields should now be visible<br>\n";
    echo "2. Verify property data displays correctly in frontend<br>\n";
    echo "3. Consider removing this migration script after successful testing<br>\n";
    echo "</div>\n";
}

/**
 * Run migration if accessed directly with proper authentication
 */
if (isset($_GET['run_migration']) && $_GET['run_migration'] === 'true') {
    // Security check - only allow for administrators
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions to run migration.');
    }
    
    // Add nonce verification for security
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce($_GET['_wpnonce'], 'immobridge_migration')) {
        wp_die('Security check failed.');
    }
    
    // Set longer execution time for large datasets
    set_time_limit(300); // 5 minutes
    
    // Start output buffering for real-time progress
    if (ob_get_level() == 0) {
        ob_start();
    }
    
    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>ImmoBridge Migration</title></head><body>\n";
    
    immobridge_migrate_meta_keys();
    
    echo "</body></html>\n";
    
    if (ob_get_level()) {
        ob_end_flush();
    }
    
    exit;
}

// Display migration interface if accessed directly
if (basename($_SERVER['PHP_SELF']) === 'migrate-meta-keys.php') {
    // Security check - only allow for administrators
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient permissions to access migration script.');
    }
    
    $nonce = wp_create_nonce('immobridge_migration');
    $migrationUrl = add_query_arg([
        'run_migration' => 'true',
        '_wpnonce' => $nonce
    ]);
    
    echo "<!DOCTYPE html>\n";
    echo "<html><head><title>ImmoBridge Migration</title></head><body>\n";
    echo "<h1>ImmoBridge Meta Key Migration</h1>\n";
    echo "<p>This script will migrate existing property meta data to make it visible to Bricks Builder.</p>\n";
    echo "<p><strong>What it does:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Converts underscore-prefixed meta keys (e.g., _immobridge_price) to clean keys (e.g., price)</li>\n";
    echo "<li>Preserves original meta keys for backward compatibility</li>\n";
    echo "<li>Makes property fields visible in Bricks Builder Dynamic Data picker</li>\n";
    echo "</ul>\n";
    echo "<p><strong>Warning:</strong> This operation will modify your database. Please backup your database before proceeding.</p>\n";
    echo "<p><a href='{$migrationUrl}' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 3px;'>Start Migration</a></p>\n";
    echo "</body></html>\n";
    exit;
}
