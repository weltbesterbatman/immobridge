<?php
/**
 * Plugin Name: ImmoBridge Test
 * Description: Simple test to check if basic plugin loading works
 * Version: 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Simple test
add_action('admin_notices', function() {
    echo '<div class="notice notice-success"><p><strong>ImmoBridge Test:</strong> Plugin loaded successfully! PHP Version: ' . PHP_VERSION . '</p></div>';
});

// Test if we can register a simple post type
add_action('init', function() {
    register_post_type('test_property', [
        'labels' => [
            'name' => 'Test Properties',
            'singular_name' => 'Test Property',
        ],
        'public' => true,
        'show_ui' => true,
        'menu_icon' => 'dashicons-building',
    ]);
});
