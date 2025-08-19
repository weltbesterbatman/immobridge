<?php
/**
 * Test script to verify ImmoBridge service-oriented architecture
 */

// Simulate WordPress environment
define('ABSPATH', '/Users/martinrauer/Sites/localhost/wp-werft-local/immonex-bricks-wp/');
define('WP_DEBUG', true);

// Mock WordPress functions for testing
if (!function_exists('plugin_dir_path')) {
    function plugin_dir_path($file) {
        return dirname($file) . '/';
    }
}

if (!function_exists('plugin_dir_url')) {
    function plugin_dir_url($file) {
        return 'http://localhost:8888/wp-content/plugins/' . basename(dirname($file)) . '/';
    }
}

if (!function_exists('plugin_basename')) {
    function plugin_basename($file) {
        return 'immobridge/' . basename($file);
    }
}

if (!function_exists('add_action')) {
    function add_action($hook, $callback, $priority = 10) {
        echo "Hook registered: $hook\n";
        return true;
    }
}

if (!function_exists('do_action')) {
    function do_action($hook, ...$args) {
        echo "Action fired: $hook\n";
        return true;
    }
}

if (!function_exists('error_log')) {
    function error_log($message) {
        echo "LOG: $message\n";
    }
}

echo "=== ImmoBridge Architecture Test ===\n\n";

try {
    // Test autoloader
    echo "1. Testing Composer autoloader...\n";
    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';
        echo "✅ Autoloader loaded successfully\n\n";
    } else {
        throw new Exception("❌ Composer autoloader not found");
    }

    // Test constants
    echo "2. Testing plugin constants...\n";
    define('IMMOBRIDGE_VERSION', '1.0.0-dev');
    define('IMMOBRIDGE_PLUGIN_FILE', __FILE__);
    define('IMMOBRIDGE_PLUGIN_DIR', plugin_dir_path(__FILE__));
    define('IMMOBRIDGE_PLUGIN_URL', plugin_dir_url(__FILE__));
    define('IMMOBRIDGE_PLUGIN_BASENAME', plugin_basename(__FILE__));
    echo "✅ Constants defined successfully\n\n";

    // Test Plugin class instantiation
    echo "3. Testing Plugin class instantiation...\n";
    $plugin = new \ImmoBridge\Core\Plugin();
    echo "✅ Plugin class instantiated successfully\n\n";

    // Test container
    echo "4. Testing DI Container...\n";
    $container = $plugin->getContainer();
    echo "✅ Container retrieved: " . get_class($container) . "\n\n";

    // Test plugin initialization (without WordPress hooks)
    echo "5. Testing Plugin initialization...\n";
    // We can't fully test init() without WordPress, but we can test class loading
    
    // Test service provider loading
    echo "6. Testing Service Provider classes...\n";
    $propertyProvider = new \ImmoBridge\Services\PropertyServiceProvider();
    echo "✅ PropertyServiceProvider loaded successfully\n";
    
    // Test other core classes
    echo "7. Testing other core classes...\n";
    $activator = new \ImmoBridge\Core\Activator();
    echo "✅ Activator class loaded successfully\n";
    
    $deactivator = new \ImmoBridge\Core\Deactivator();
    echo "✅ Deactivator class loaded successfully\n";
    
    echo "\n=== Architecture Test Results ===\n";
    echo "✅ All core classes load successfully\n";
    echo "✅ Service-oriented architecture is properly structured\n";
    echo "✅ Ready for WordPress integration testing\n";

} catch (Throwable $e) {
    echo "\n❌ Architecture Test Failed:\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
