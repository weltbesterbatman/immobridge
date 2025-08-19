<?php
/**
 * Plugin Name: ImmoBridge Debug Simple
 * Description: Simplified version for debugging
 * Version: 1.0.0-debug
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Simple debug plugin to test basic functionality
add_action('init', function() {
    // Register custom post type
    register_post_type('property', [
        'labels' => [
            'name' => 'Properties',
            'singular_name' => 'Property',
            'add_new' => 'Add New Property',
            'add_new_item' => 'Add New Property',
            'edit_item' => 'Edit Property',
            'new_item' => 'New Property',
            'view_item' => 'View Property',
            'search_items' => 'Search Properties',
            'not_found' => 'No properties found',
            'not_found_in_trash' => 'No properties found in trash',
            'menu_name' => 'Properties Debug'
        ],
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-building',
        'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
        'show_in_rest' => true,
    ]);
    
    error_log('ImmoBridge Debug Simple: Custom post type registered');
});

add_action('admin_notices', function() {
    echo '<div class="notice notice-success"><p>ImmoBridge Debug Simple is active and working!</p></div>';
});
