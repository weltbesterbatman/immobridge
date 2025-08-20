<?php
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/import-error.log');
error_reporting(E_ALL);

require_once __DIR__ . '/src/Services/OpenImmoImporter.php';

use ImmoBridge\Services\OpenImmoImporter;

// Database connection details
$db_host = '127.0.0.1:8889';
$db_name = 'wp_immonexbrickswplocal_db';
$db_user = 'wp_immonexbrickswplocal_user';
$db_password = 'wp_immonexbrickswplocal_pw';

// Print connection details
echo "Attempting to connect with the following details:\n";
echo "Host: $db_host\n";
echo "Database: $db_name\n";
echo "User: $db_user\n";

// Connect to the database
$host_parts = explode(':', $db_host);
$host = $host_parts[0];
$port = $host_parts[1] ?? 3306;

echo "Connecting to MySQL server at $host:$port\n";

$mysqli = new mysqli($host, $db_user, $db_password, $db_name, $port);

if ($mysqli->connect_errno) {
    die("Database connection failed: " . $mysqli->connect_error . "\n");
}

echo "Database connection successful.\n";

$importer = new OpenImmoImporter();

$xmlFilePath = __DIR__ . '/../../uploads/immobridge/in/openimmo-import-data.zip';
$baseDir = dirname($xmlFilePath);

$result = $importer->importBatch($xmlFilePath, 0, 5, true, true, $baseDir);

echo "Import Result:\n";
print_r($result);

echo "\nImported Properties:\n";
$result = $mysqli->query("SELECT ID, post_title FROM wp_posts WHERE post_type = 'property' ORDER BY post_date DESC LIMIT 5");

if ($result) {
    while ($property = $result->fetch_object()) {
        echo "ID: {$property->ID}\n";
        echo "Title: {$property->post_title}\n";
        
        $meta_query = $mysqli->prepare("SELECT meta_key, meta_value FROM wp_postmeta WHERE post_id = ? AND meta_key IN ('cf_zip_code', 'cf_city', 'cf_address', 'gallery_images')");
        $meta_query->bind_param('i', $property->ID);
        $meta_query->execute();
        $meta_result = $meta_query->get_result();
        $meta_data = $meta_result->fetch_all(MYSQLI_ASSOC);
        
        $meta_values = array_column($meta_data, 'meta_value', 'meta_key');
        
        echo "ZIP Code: " . ($meta_values['cf_zip_code'] ?? 'N/A') . "\n";
        echo "City: " . ($meta_values['cf_city'] ?? 'N/A') . "\n";
        echo "Address: " . ($meta_values['cf_address'] ?? 'N/A') . "\n";
        echo "Images: " . ($meta_values['gallery_images'] ?? 'N/A') . "\n";
        echo "---\n";
    }
    $result->close();
} else {
    echo "No properties found or query failed.\n";
}

$mysqli->close();
