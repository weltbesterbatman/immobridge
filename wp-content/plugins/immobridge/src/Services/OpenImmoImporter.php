<?php
/**
 * OpenImmo XML Importer
 *
 * @package ImmoBridge
 * @subpackage Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Services;

use DOMDocument;
use DOMXPath;
use WP_Error;

/**
 * OpenImmo XML Importer Class
 *
 * Handles the import of OpenImmo XML files into WordPress properties
 *
 * @since 1.0.0
 */
class OpenImmoImporter
{
    private array $importLog = [];
    private int $importedCount = 0;
    private int $updatedCount = 0;
    private int $errorCount = 0;

    /**
     * Import OpenImmo file (XML or ZIP)
     *
     * @param string $filePath Path to the XML or ZIP file
     * @param bool $importImages Whether to import images
     * @param bool $updateExisting Whether to update existing properties
     * @return array Import results
     */
    public function importFile(string $filePath, bool $importImages = true, bool $updateExisting = false): array
    {
        $this->resetCounters();
        $this->log('Starting OpenImmo import from: ' . basename($filePath));

        if (!file_exists($filePath)) {
            $this->log('Error: File not found - ' . $filePath, 'error');
            return $this->getResults();
        }

        // Check if it's a ZIP file
        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        if ($fileExtension === 'zip') {
            return $this->importZipFile($filePath, $importImages, $updateExisting);
        } else {
            return $this->importXmlFile($filePath, $importImages, $updateExisting);
        }
    }

    /**
     * Import OpenImmo file from directory workflow
     *
     * @param string $filePath Path to the file in the 'in' directory
     * @param bool $importImages Whether to import images
     * @param bool $updateExisting Whether to update existing properties
     * @return array Import results
     */
    public function importFromDirectory(string $filePath, bool $importImages = true, bool $updateExisting = false): array
    {
        // Increase limits for large imports
        $this->increaseResourceLimits();
        
        $this->resetCounters();
        $filename = basename($filePath);
        $this->log('Starting directory-based import for: ' . $filename);

        if (!file_exists($filePath)) {
            $this->log('Error: File not found - ' . $filePath, 'error');
            return $this->getResults();
        }

        // Get directory paths
        $uploadDir = wp_upload_dir();
        $baseDir = $uploadDir['basedir'] . '/immobridge';
        $processingDir = $baseDir . '/processing';
        $archiveDir = $baseDir . '/archive';
        $errorDir = $baseDir . '/error';

        // Move file to processing directory
        $processingPath = $processingDir . '/' . $filename;
        if (!rename($filePath, $processingPath)) {
            $this->log('Error: Could not move file to processing directory', 'error');
            return $this->getResults();
        }

        $this->log('File moved to processing directory');

        try {
            // Import the file
            $results = $this->importFile($processingPath, $importImages, $updateExisting);
            
            // Determine destination based on results
            if ($this->errorCount === 0 && ($this->importedCount > 0 || $this->updatedCount > 0)) {
                // Success - move to archive
                $finalPath = $archiveDir . '/' . $filename;
                if (rename($processingPath, $finalPath)) {
                    $this->log('File archived successfully');
                } else {
                    $this->log('Warning: Could not move file to archive directory', 'warning');
                }
            } else {
                // Error or no changes - move to error directory
                $finalPath = $errorDir . '/' . $filename;
                if (rename($processingPath, $finalPath)) {
                    $this->log('File moved to error directory due to import issues');
                    
                    // Create error log file
                    $errorLogPath = $errorDir . '/' . pathinfo($filename, PATHINFO_FILENAME) . '_error.log';
                    file_put_contents($errorLogPath, implode("\n", $this->importLog));
                } else {
                    $this->log('Warning: Could not move file to error directory', 'warning');
                }
            }

            // Update import statistics
            update_option('immobridge_last_import', current_time('mysql'));
            update_option('immobridge_import_log', implode("\n", $this->importLog));

            $this->log('Directory-based import completed. Imported: ' . $this->importedCount . ', Updated: ' . $this->updatedCount . ', Errors: ' . $this->errorCount);

            return $results;

        } catch (\Exception $e) {
            $this->log('Fatal error during import: ' . $e->getMessage(), 'error');
            
            // Move file to error directory
            $errorPath = $errorDir . '/' . $filename;
            if (file_exists($processingPath)) {
                rename($processingPath, $errorPath);
                
                // Create error log file
                $errorLogPath = $errorDir . '/' . pathinfo($filename, PATHINFO_FILENAME) . '_error.log';
                file_put_contents($errorLogPath, implode("\n", $this->importLog));
            }

            return $this->getResults();
        }
    }

    /**
     * Import OpenImmo ZIP file
     *
     * @param string $zipPath Path to the ZIP file
     * @param bool $importImages Whether to import images
     * @param bool $updateExisting Whether to update existing properties
     * @return array Import results
     */
    public function importZipFile(string $zipPath, bool $importImages = true, bool $updateExisting = false): array
    {
        $this->log('Processing ZIP file: ' . basename($zipPath));

        // Create temporary directory for extraction
        $tempDir = wp_upload_dir()['basedir'] . '/immobridge-temp-' . uniqid();
        if (!wp_mkdir_p($tempDir)) {
            $this->log('Error: Could not create temporary directory', 'error');
            return $this->getResults();
        }

        // Extract ZIP file
        $zip = new \ZipArchive();
        $result = $zip->open($zipPath);
        
        if ($result !== TRUE) {
            $this->log('Error: Could not open ZIP file - Error code: ' . $result, 'error');
            return $this->getResults();
        }

        if (!$zip->extractTo($tempDir)) {
            $this->log('Error: Could not extract ZIP file', 'error');
            $zip->close();
            return $this->getResults();
        }

        $zip->close();
        $this->log('ZIP file extracted to: ' . $tempDir);

        // Find XML files in extracted directory
        $xmlFiles = $this->findXmlFiles($tempDir);
        
        if (empty($xmlFiles)) {
            $this->log('Error: No XML files found in ZIP archive', 'error');
            $this->cleanupTempDir($tempDir);
            return $this->getResults();
        }

        $this->log('Found ' . count($xmlFiles) . ' XML file(s) in ZIP archive');

        // Process each XML file
        foreach ($xmlFiles as $xmlFile) {
            $this->log('Processing XML file: ' . basename($xmlFile));
            $this->importXmlFile($xmlFile, $importImages, $updateExisting, $tempDir);
        }

        // Cleanup temporary directory
        $this->cleanupTempDir($tempDir);

        // Update import statistics
        update_option('immobridge_last_import', current_time('mysql'));
        update_option('immobridge_import_log', implode("\n", $this->importLog));

        $this->log('ZIP import completed. Imported: ' . $this->importedCount . ', Updated: ' . $this->updatedCount . ', Errors: ' . $this->errorCount);

        return $this->getResults();
    }

    /**
     * Import OpenImmo XML file
     *
     * @param string $filePath Path to the XML file
     * @param bool $importImages Whether to import images
     * @param bool $updateExisting Whether to update existing properties
     * @param string|null $baseDir Base directory for relative image paths
     * @return array Import results
     */
    public function importXmlFile(string $filePath, bool $importImages = true, bool $updateExisting = false, ?string $baseDir = null): array
    {
        if (!file_exists($filePath)) {
            $this->log('Error: XML file not found - ' . $filePath, 'error');
            return $this->getResults();
        }

        // Load and validate XML
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        
        if (!$dom->load($filePath)) {
            $errors = libxml_get_errors();
            $this->log('Error: Invalid XML file - ' . implode(', ', array_map(fn($e) => $e->message, $errors)), 'error');
            return $this->getResults();
        }

        $xpath = new DOMXPath($dom);
        
        // Find all immobilie elements
        $properties = $xpath->query('//immobilie');
        
        if ($properties->length === 0) {
            $this->log('Warning: No properties found in XML file', 'warning');
            return $this->getResults();
        }

        $this->log('Found ' . $properties->length . ' properties in XML file');

        // Process each property
        foreach ($properties as $propertyNode) {
            try {
                $this->importProperty($propertyNode, $xpath, $importImages, $updateExisting, $baseDir);
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->log('Error importing property: ' . $e->getMessage(), 'error');
            }
        }

        return $this->getResults();
    }

    /**
     * Find XML files in directory recursively
     */
    private function findXmlFiles(string $directory): array
    {
        $xmlFiles = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'xml') {
                $xmlFiles[] = $file->getPathname();
            }
        }

        return $xmlFiles;
    }

    /**
     * Clean up temporary directory
     */
    private function cleanupTempDir(string $tempDir): void
    {
        if (is_dir($tempDir)) {
            $this->deleteDirectory($tempDir);
            $this->log('Cleaned up temporary directory');
        }
    }

    /**
     * Recursively delete directory
     */
    private function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        return rmdir($dir);
    }

    /**
     * Import a single property from XML node
     */
    private function importProperty(\DOMElement $propertyNode, DOMXPath $xpath, bool $importImages, bool $updateExisting, ?string $baseDir = null): void
    {
        // Extract basic property data
        $propertyData = $this->extractPropertyData($propertyNode, $xpath);
        
        if (empty($propertyData['property_id'])) {
            throw new \Exception('Property ID is required but not found');
        }

        // Check if property already exists
        $existingPost = $this->findExistingProperty($propertyData['property_id']);
        
        if ($existingPost && !$updateExisting) {
            $this->log('Skipping existing property: ' . $propertyData['property_id']);
            return;
        }

        // Prepare post data
        $postData = [
            'post_type' => 'property',
            'post_status' => 'publish',
            'post_title' => $propertyData['title'] ?? 'Property ' . $propertyData['property_id'],
            'post_content' => $propertyData['description'] ?? '',
        ];

        if ($existingPost) {
            $postData['ID'] = $existingPost->ID;
            $postId = wp_update_post($postData);
            $this->updatedCount++;
            $this->log('Updated property: ' . $propertyData['property_id']);
        } else {
            $postId = wp_insert_post($postData);
            $this->importedCount++;
            $this->log('Imported new property: ' . $propertyData['property_id']);
        }

        if (is_wp_error($postId)) {
            throw new \Exception('Failed to create/update post: ' . $postId->get_error_message());
        }

        // Save all meta data
        $this->savePropertyMeta($postId, $propertyData);
        
        // Set taxonomies
        $this->setPropertyTaxonomies($postId, $propertyData);

        // Import images if requested
        if ($importImages && !empty($propertyData['images'])) {
            $this->importPropertyImages($postId, $propertyData['images'], $baseDir);
        }
    }

    /**
     * Extract property data from XML node
     */
    private function extractPropertyData(\DOMElement $propertyNode, DOMXPath $xpath): array
    {
        $data = [];

        // Basic identification
        $data['property_id'] = $this->getXPathValue($xpath, './/objektnr_extern', $propertyNode);
        $data['object_number'] = $this->getXPathValue($xpath, './/objektnr_intern', $propertyNode);
        
        // Title and description
        $data['title'] = $this->getXPathValue($xpath, './/objekttitel', $propertyNode);
        $data['description'] = $this->getXPathValue($xpath, './/objektbeschreibung', $propertyNode);

        // Areas and spaces
        $data['living_space'] = $this->getXPathValue($xpath, './/wohnflaeche', $propertyNode);
        $data['total_space'] = $this->getXPathValue($xpath, './/gesamtflaeche', $propertyNode);
        $data['plot_area'] = $this->getXPathValue($xpath, './/grundstuecksflaeche', $propertyNode);
        
        // Rooms
        $data['rooms'] = $this->getXPathValue($xpath, './/anzahl_zimmer', $propertyNode);
        $data['bedrooms'] = $this->getXPathValue($xpath, './/anzahl_schlafzimmer', $propertyNode);
        $data['bathrooms'] = $this->getXPathValue($xpath, './/anzahl_badezimmer', $propertyNode);

        // Building data
        $data['year_built'] = $this->getXPathValue($xpath, './/baujahr', $propertyNode);
        $data['condition'] = $this->getXPathValue($xpath, './/zustand', $propertyNode);
        $data['heating_type'] = $this->getXPathValue($xpath, './/heizungsart', $propertyNode);

        // Energy data
        $data['energy_certificate'] = $this->getXPathValue($xpath, './/energieausweis', $propertyNode);
        $data['energy_value'] = $this->getXPathValue($xpath, './/energieverbrauchkennwert', $propertyNode);

        // Prices
        $data['purchase_price'] = $this->getXPathValue($xpath, './/kaufpreis', $propertyNode);
        $data['rent_cold'] = $this->getXPathValue($xpath, './/kaltmiete', $propertyNode);
        $data['rent_warm'] = $this->getXPathValue($xpath, './/warmmiete', $propertyNode);
        $data['additional_costs'] = $this->getXPathValue($xpath, './/nebenkosten', $propertyNode);
        $data['deposit'] = $this->getXPathValue($xpath, './/kaution', $propertyNode);
        $data['commission'] = $this->getXPathValue($xpath, './/provision', $propertyNode);

        // Location
        $data['street'] = $this->getXPathValue($xpath, './/strasse', $propertyNode);
        $data['house_number'] = $this->getXPathValue($xpath, './/hausnummer', $propertyNode);
        $data['zip_code'] = $this->getXPathValue($xpath, './/plz', $propertyNode);
        $data['city'] = $this->getXPathValue($xpath, './/ort', $propertyNode);
        $data['district'] = $this->getXPathValue($xpath, './/ortsteil', $propertyNode);
        $data['state'] = $this->getXPathValue($xpath, './/bundesland', $propertyNode);
        $data['country'] = $this->getXPathValue($xpath, './/land', $propertyNode);
        $data['latitude'] = $this->getXPathValue($xpath, './/breitengrad', $propertyNode);
        $data['longitude'] = $this->getXPathValue($xpath, './/laengengrad', $propertyNode);

        // Technical features
        $data['floor'] = $this->getXPathValue($xpath, './/etage', $propertyNode);
        $data['floors_total'] = $this->getXPathValue($xpath, './/anzahl_etagen', $propertyNode);
        $data['balcony'] = $this->getBooleanValue($xpath, './/balkon', $propertyNode);
        $data['terrace'] = $this->getBooleanValue($xpath, './/terrasse', $propertyNode);
        $data['garden'] = $this->getBooleanValue($xpath, './/garten', $propertyNode);
        $data['garage'] = $this->getBooleanValue($xpath, './/garage', $propertyNode);
        $data['elevator'] = $this->getBooleanValue($xpath, './/fahrstuhl', $propertyNode);
        $data['barrier_free'] = $this->getBooleanValue($xpath, './/barrierefrei', $propertyNode);
        $data['pets_allowed'] = $this->getBooleanValue($xpath, './/haustiere', $propertyNode);

        // Property type and status
        $data['property_type'] = $this->getXPathValue($xpath, './/objektart', $propertyNode);
        $data['property_status'] = $this->getXPathValue($xpath, './/vermarktungsart', $propertyNode);

        // Images
        $data['images'] = $this->extractImages($xpath, $propertyNode);

        return array_filter($data, fn($value) => $value !== null && $value !== '');
    }

    /**
     * Get value from XPath query
     */
    private function getXPathValue(DOMXPath $xpath, string $query, \DOMElement $context = null): ?string
    {
        $nodes = $xpath->query($query, $context);
        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
    }

    /**
     * Get boolean value from XPath query
     */
    private function getBooleanValue(DOMXPath $xpath, string $query, \DOMElement $context = null): ?string
    {
        $value = $this->getXPathValue($xpath, $query, $context);
        if ($value === null) return null;
        
        $value = strtolower($value);
        return in_array($value, ['true', '1', 'ja', 'yes']) ? 'yes' : 'no';
    }

    /**
     * Extract images from property node
     */
    private function extractImages(DOMXPath $xpath, \DOMElement $propertyNode): array
    {
        $images = [];
        $imageNodes = $xpath->query('.//anhang[@gruppe="BILD"]', $propertyNode);
        
        foreach ($imageNodes as $imageNode) {
            $imageData = [
                'url' => $this->getXPathValue($xpath, './/daten', $imageNode),
                'title' => $this->getXPathValue($xpath, './/anhangtitel', $imageNode),
                'description' => $this->getXPathValue($xpath, './/anmerkung', $imageNode),
            ];
            
            if (!empty($imageData['url'])) {
                $images[] = $imageData;
            }
        }
        
        return $images;
    }

    /**
     * Find existing property by ID
     */
    private function findExistingProperty(string $propertyId): ?\WP_Post
    {
        $posts = get_posts([
            'post_type' => 'property',
            'meta_key' => '_immobridge_property_id',
            'meta_value' => $propertyId,
            'posts_per_page' => 1,
            'post_status' => 'any'
        ]);

        return !empty($posts) ? $posts[0] : null;
    }

    /**
     * Save property meta data using new clean meta keys for Bricks Builder visibility
     */
    private function savePropertyMeta(int $postId, array $data): void
    {
        // Map OpenImmo data to our clean meta field structure
        $metaMapping = [
            // Basic property data
            'property_id' => 'openimmo_id',
            'living_space' => 'living_area',
            'total_space' => 'total_area',
            'rooms' => 'rooms',
            'bedrooms' => 'bedrooms',
            'bathrooms' => 'bathrooms',
            
            // Price data - determine if sale or rent
            'purchase_price' => 'price',
            'rent_cold' => 'price',
            'rent_warm' => 'price',
            
            // Location data
            'street' => 'address',
            'zip_code' => 'zip_code',
            'city' => 'city',
            'country' => 'country',
            'latitude' => 'latitude',
            'longitude' => 'longitude',
        ];

        // Save mapped fields with clean keys
        foreach ($metaMapping as $sourceField => $targetField) {
            if (isset($data[$sourceField]) && !empty($data[$sourceField])) {
                update_post_meta($postId, $targetField, $data[$sourceField]);
            }
        }

        // Determine property type and status
        if (!empty($data['property_type'])) {
            update_post_meta($postId, 'type', $this->mapPropertyType($data['property_type']));
        }

        if (!empty($data['property_status'])) {
            update_post_meta($postId, 'status', $this->mapPropertyStatus($data['property_status']));
        }

        // Determine price type (sale or rent)
        if (!empty($data['purchase_price'])) {
            update_post_meta($postId, 'price_type', 'sale');
        } elseif (!empty($data['rent_cold']) || !empty($data['rent_warm'])) {
            update_post_meta($postId, 'price_type', 'rent');
        }

        // Build full address
        $addressParts = array_filter([
            $data['street'] ?? '',
            $data['house_number'] ?? ''
        ]);
        if (!empty($addressParts)) {
            update_post_meta($postId, 'address', implode(' ', $addressParts));
        }

        // Save images as JSON
        if (!empty($data['images'])) {
            $imageUrls = array_column($data['images'], 'url');
            update_post_meta($postId, 'images', json_encode($imageUrls));
        }

        // Set import timestamp
        update_post_meta($postId, 'imported_at', current_time('mysql'));

        // Also save with old keys for backward compatibility
        $this->savePropertyMetaLegacy($postId, $data);
    }

    /**
     * Save property meta data with legacy keys for backward compatibility
     */
    private function savePropertyMetaLegacy(int $postId, array $data): void
    {
        $metaFields = [
            'property_id', 'object_number', 'living_space', 'total_space', 'plot_area', 'rooms', 'bedrooms', 'bathrooms',
            'year_built', 'condition', 'heating_type', 'energy_certificate', 'energy_value',
            'purchase_price', 'rent_cold', 'rent_warm', 'additional_costs', 'deposit', 'commission', 'price_per_sqm',
            'street', 'house_number', 'zip_code', 'city', 'district', 'state', 'country', 'latitude', 'longitude',
            'floor', 'floors_total', 'balcony', 'terrace', 'garden', 'garage', 'parking_spaces', 'elevator',
            'barrier_free', 'pets_allowed', 'furnished', 'kitchen', 'cellar', 'attic'
        ];

        foreach ($metaFields as $field) {
            if (isset($data[$field])) {
                update_post_meta($postId, '_immobridge_' . $field, $data[$field]);
            }
        }
    }

    /**
     * Map OpenImmo property type to our enum values
     */
    private function mapPropertyType(string $openImmoType): string
    {
        $typeMapping = [
            'wohnung' => 'apartment',
            'haus' => 'house',
            'grundstueck' => 'land',
            'buero_praxen' => 'office',
            'einzelhandel' => 'retail',
            'gastgewerbe' => 'restaurant',
            'hallen_lager_prod' => 'warehouse',
            'land_und_forstwirtschaft' => 'agricultural',
            'parken' => 'parking',
            'sonstige' => 'other'
        ];

        return $typeMapping[strtolower($openImmoType)] ?? 'other';
    }

    /**
     * Map OpenImmo property status to our enum values
     */
    private function mapPropertyStatus(string $openImmoStatus): string
    {
        $statusMapping = [
            'kauf' => 'for_sale',
            'miete_pacht' => 'for_rent',
            'erbpacht' => 'for_rent',
            'leasing' => 'for_rent'
        ];

        return $statusMapping[strtolower($openImmoStatus)] ?? 'available';
    }

    /**
     * Set property taxonomies
     */
    private function setPropertyTaxonomies(int $postId, array $data): void
    {
        // Set property type
        if (!empty($data['property_type'])) {
            wp_set_object_terms($postId, $data['property_type'], 'property_type');
        }

        // Set property status
        if (!empty($data['property_status'])) {
            wp_set_object_terms($postId, $data['property_status'], 'property_status');
        }

        // Set location
        if (!empty($data['city'])) {
            wp_set_object_terms($postId, $data['city'], 'property_location');
        }
    }

    /**
     * Import property images
     */
    private function importPropertyImages(int $postId, array $images, ?string $baseDir = null): void
    {
        foreach ($images as $index => $imageData) {
            try {
                $imageUrl = $imageData['url'];
                
                // If we have a base directory and the URL is relative, construct absolute path
                if ($baseDir && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                    // Handle relative paths from ZIP archives
                    $absolutePath = $baseDir . '/' . ltrim($imageUrl, '/');
                    
                    // Check if file exists at the constructed path
                    if (file_exists($absolutePath)) {
                        $imageUrl = $absolutePath;
                        $this->log('Resolved relative image path: ' . $imageUrl);
                    } else {
                        // Try to find the image in subdirectories (common in OpenImmo ZIP files)
                        $foundPath = $this->findImageInSubdirectories($baseDir, basename($imageUrl));
                        if ($foundPath) {
                            $imageUrl = $foundPath;
                            $this->log('Found image in subdirectory: ' . $imageUrl);
                        } else {
                            $this->log('Image file not found at: ' . $absolutePath, 'warning');
                            $this->log('Also searched subdirectories for: ' . basename($imageUrl), 'warning');
                            continue;
                        }
                    }
                }
                
                $attachmentId = $this->importImage($imageUrl, $postId, $imageData['title'] ?? '');
                
                if ($attachmentId && $index === 0) {
                    // Set first image as featured image
                    set_post_thumbnail($postId, $attachmentId);
                    $this->log('Set featured image for property: ' . $postId);
                }
                
                if ($attachmentId) {
                    $this->log('Successfully imported image: ' . basename($imageUrl));
                }
            } catch (\Exception $e) {
                $this->log('Error importing image: ' . $e->getMessage(), 'error');
            }
        }
    }
    /**
     * Find image file in subdirectories of the base directory
     */
    private function findImageInSubdirectories(string $baseDir, string $filename): ?string
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === $filename) {
                $this->log('Found image file: ' . $file->getPathname());
                return $file->getPathname();
            }
        }

        return null;
    }

    /**
     * Import a single image
     */
    private function importImage(string $imageUrl, int $postId, string $title = ''): ?int
    {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        // Check if it's a local file path
        if (file_exists($imageUrl) && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
            $this->log('Processing local image file: ' . $imageUrl);
            
            // For local files, we need to copy them to WordPress uploads directory
            $uploadDir = wp_upload_dir();
            $filename = basename($imageUrl);
            
            // Generate unique filename to avoid conflicts
            $pathInfo = pathinfo($filename);
            $baseName = $pathInfo['filename'];
            $extension = $pathInfo['extension'] ?? '';
            $counter = 1;
            
            while (file_exists($uploadDir['path'] . '/' . $filename)) {
                $filename = $baseName . '_' . $counter . ($extension ? '.' . $extension : '');
                $counter++;
            }
            
            $targetPath = $uploadDir['path'] . '/' . $filename;
            $targetUrl = $uploadDir['url'] . '/' . $filename;
            
            $this->log('Copying image to: ' . $targetPath);
            
            // Copy file to uploads directory
            if (copy($imageUrl, $targetPath)) {
                $this->log('Image copied successfully');
                
                // Get file type
                $fileType = wp_check_filetype($filename);
                
                // Create attachment data
                $attachmentData = [
                    'guid' => $targetUrl,
                    'post_mime_type' => $fileType['type'],
                    'post_title' => $title ?: pathinfo($filename, PATHINFO_FILENAME),
                    'post_content' => '',
                    'post_status' => 'inherit'
                ];
                
                // Insert attachment
                $attachmentId = wp_insert_attachment($attachmentData, $targetPath, $postId);
                
                if (!is_wp_error($attachmentId)) {
                    $this->log('Attachment created with ID: ' . $attachmentId);
                    
                    // Generate attachment metadata
                    $attachmentMetadata = wp_generate_attachment_metadata($attachmentId, $targetPath);
                    wp_update_attachment_metadata($attachmentId, $attachmentMetadata);
                    
                    // Update the attachment URL in the database
                    wp_update_post([
                        'ID' => $attachmentId,
                        'guid' => $targetUrl
                    ]);
                    
                    $this->log('Image import completed successfully');
                    return $attachmentId;
                } else {
                    $this->log('Failed to create attachment: ' . $attachmentId->get_error_message(), 'error');
                }
            } else {
                $this->log('Failed to copy image file from: ' . $imageUrl, 'error');
            }
            
            return null;
        }
        
        // For URLs, use the standard WordPress function
        $this->log('Processing remote image URL: ' . $imageUrl);
        $attachmentId = media_sideload_image($imageUrl, $postId, $title, 'id');
        
        if (is_wp_error($attachmentId)) {
            $this->log('Failed to import remote image: ' . $attachmentId->get_error_message(), 'error');
            return null;
        }
        
        $this->log('Remote image imported successfully with ID: ' . $attachmentId);
        return $attachmentId;
    }

    /**
     * Increase resource limits for large imports
     */
    private function increaseResourceLimits(): void
    {
        // Increase execution time limit (0 = no limit)
        if (function_exists('set_time_limit')) {
            set_time_limit(0);
            $this->log('Execution time limit removed for import process');
        }
        
        // Increase memory limit to 512MB if current limit is lower
        $currentLimit = wp_convert_hr_to_bytes(ini_get('memory_limit'));
        $desiredLimit = 512 * 1024 * 1024; // 512MB
        
        if ($currentLimit < $desiredLimit) {
            ini_set('memory_limit', '512M');
            $this->log('Memory limit increased to 512MB for import process');
        }
        
        // Suppress EXIF warnings that can cause instability
        if (function_exists('error_reporting')) {
            $originalErrorReporting = error_reporting();
            error_reporting($originalErrorReporting & ~E_WARNING);
            $this->log('EXIF warnings suppressed to prevent import instability');
        }
        
        // Disable WordPress automatic image resizing for large images during import
        add_filter('big_image_size_threshold', '__return_false');
        $this->log('Large image auto-resizing disabled during import');
    }

    /**
     * Reset import counters
     */
    private function resetCounters(): void
    {
        $this->importLog = [];
        $this->importedCount = 0;
        $this->updatedCount = 0;
        $this->errorCount = 0;
    }

    /**
     * Log import message
     */
    private function log(string $message, string $level = 'info'): void
    {
        $timestamp = current_time('Y-m-d H:i:s');
        $logEntry = "[{$timestamp}] [{$level}] {$message}";
        $this->importLog[] = $logEntry;
        
        // Also log to WordPress debug log for debugging
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('ImmoBridge Import: ' . $logEntry);
        }
    }

    /**
     * Get import results
     */
    private function getResults(): array
    {
        return [
            'imported' => $this->importedCount,
            'updated' => $this->updatedCount,
            'errors' => $this->errorCount,
            'log' => $this->importLog
        ];
    }
}
