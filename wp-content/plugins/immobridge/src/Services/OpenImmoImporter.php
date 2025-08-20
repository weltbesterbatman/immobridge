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
use SimpleXMLElement;

class OpenImmoImporter
{
    private MappingService $mappingService;
    private array $importLog = [];
    private int $importedCount = 0;
    private int $updatedCount = 0;
    private int $errorCount = 0;

    public function countPropertiesInFile(string $filePath): int
    {
        if (!file_exists($filePath)) return 0;

        $fileExtension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $xmlContent = '';

        if ($fileExtension === 'zip') {
            $zip = new \ZipArchive();
            if ($zip->open($filePath) !== TRUE) return 0;
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $filename = $zip->getNameIndex($i);
                if (strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'xml') {
                    $xmlContent = $zip->getFromIndex($i);
                    break;
                }
            }
            $zip->close();
        } else {
            $xmlContent = file_get_contents($filePath);
        }

        if (empty($xmlContent)) return 0;

        $dom = new DOMDocument();
        if (!@$dom->loadXML($xmlContent)) return 0;

        $xpath = new DOMXPath($dom);
        $properties = $xpath->query('//immobilie');
        return $properties->length;
    }

    public function importBatch(string $xmlFilePath, int $offset, int $length, bool $importImages, bool $updateExisting, string $baseDir): array
    {
        $this->mappingService = new MappingService();
        if ($this->mappingService->hasError()) {
            $this->log('Error: Could not load mapping file.', 'error');
            return $this->getResults();
        }

        $this->resetCounters();
        if (!file_exists($xmlFilePath)) {
            $this->log('Error: XML file not found - ' . $xmlFilePath, 'error');
            return $this->getResults();
        }

        $xmlContent = file_get_contents($xmlFilePath);
        if (empty($xmlContent)) {
            $this->log('Error: No XML content found.', 'error');
            return $this->getResults();
        }

        $dom = new DOMDocument();
        if (!@$dom->loadXML($xmlContent)) {
            $this->log('Error: Invalid XML content', 'error');
            return $this->getResults();
        }

        $xpath = new DOMXPath($dom);
        $properties = $xpath->query('//immobilie');
        $nodesToProcess = new \LimitIterator(new \IteratorIterator($properties), $offset, $length);

        $processedInBatch = 0;
        foreach ($nodesToProcess as $propertyNode) {
            try {
                $this->importProperty($propertyNode, $xpath, $importImages, $updateExisting, $baseDir);
                $processedInBatch++;
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->log('Error importing property: ' . $e->getMessage(), 'error');
                $processedInBatch++;
            }
        }
        
        $results = $this->getResults();
        $results['processed_in_batch'] = $processedInBatch;
        return $results;
    }

    private function importProperty(\DOMElement $propertyNode, DOMXPath $xpath, bool $importImages, bool $updateExisting, ?string $baseDir = null): void
    {
        $propertyData = $this->extractPropertyData($propertyNode, $xpath);
        if (empty($propertyData['openimmo_id'])) throw new \Exception('Property ID is required but not found');

        $existingPost = $this->findExistingProperty($propertyData['openimmo_id']);
        if ($existingPost && !$updateExisting) {
            $this->log('Skipping existing property: ' . $propertyData['property_id']);
            return;
        }

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
            $this->log('Updated property: ' . $propertyData['openimmo_id']);
        } else {
            $postId = wp_insert_post($postData);
            $this->importedCount++;
            $this->log('Imported new property: ' . $propertyData['openimmo_id']);
        }

        if (is_wp_error($postId)) throw new \Exception('Failed to create/update post: ' . $postId->get_error_message());

        $this->savePropertyMeta($postId, $propertyData);
        $this->setPropertyTaxonomies($postId, $propertyData);
        if ($importImages && !empty($propertyData['images'])) {
            $this->importPropertyImages($postId, $propertyData['images'], $baseDir);
        }
    }
    
    private function extractPropertyData(\DOMElement $propertyNode, DOMXPath $xpath): array
    {
        $data = [];
        $simpleXmlNode = simplexml_import_dom($propertyNode);
        $data['__simplexml__'] = $simpleXmlNode;

        // Basic data
        $data['openimmo_id'] = $this->getXPathValue($xpath, './/verwaltung_techn/objektnr_extern', $propertyNode);
        if (empty($data['openimmo_id'])) {
            $data['openimmo_id'] = $this->getXPathValue($xpath, './/verwaltung_techn/openimmo_obid', $propertyNode);
        }
        $data['title'] = $this->getXPathValue($xpath, './/freitexte/objekttitel', $propertyNode);
        
        // Concatenate description fields
        $description_parts = [];
        $description_parts[] = $this->getXPathValue($xpath, './/freitexte/objektbeschreibung', $propertyNode);
        $description_parts[] = $this->getXPathValue($xpath, './/freitexte/lage', $propertyNode);
        $description_parts[] = $this->getXPathValue($xpath, './/freitexte/ausstatt_beschr', $propertyNode);
        $description_parts[] = $this->getXPathValue($xpath, './/freitexte/sonstige_angaben', $propertyNode);
        $data['description'] = implode("\n\n", array_filter($description_parts));

        $data['images'] = $this->extractImages($xpath, $propertyNode);

        // Process mappings from CSV
        foreach ($this->mappingService->getMappings() as $mapping) {
            if ($mapping['type'] === 'custom_field') {
                $value = $this->mappingService->getElementValue($simpleXmlNode, $mapping);
                if ($value !== false) {
                    $key = $mapping['destination'];
                    $data[$key] = $value;
                }
            }
        }

        return array_filter($data, fn($value) => $value !== null && $value !== '');
    }
    
    private function getXPathValue(DOMXPath $xpath, string $query, ?\DOMElement $context = null): ?string
    {
        $nodes = $xpath->query($query, $context);
        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
    }

    private function extractImages(DOMXPath $xpath, \DOMElement $propertyNode): array
    {
        $images = [];
        // Query for all attachments that are images, including TITELBILD, BILD, GRUNDRISS etc.
        $imageNodes = $xpath->query('.//anhang[daten/pfad and contains("image/", format)] | .//anhang[@gruppe="TITELBILD" or @gruppe="BILD" or @gruppe="GRUNDRISS"]', $propertyNode);
        foreach ($imageNodes as $imageNode) {
            $path = $this->getXPathValue($xpath, './/daten/pfad', $imageNode);
            if ($path) {
                $images[] = [
                    'url' => $path,
                    'group' => $imageNode->getAttribute('gruppe'),
                ];
            }
        }
        return $images;
    }

    private function findExistingProperty(string $propertyId): ?\WP_Post
    {
        $posts = get_posts(['post_type' => 'property', 'meta_key' => 'openimmo_id', 'meta_value' => $propertyId, 'posts_per_page' => 1, 'post_status' => 'any']);
        return !empty($posts) ? $posts[0] : null;
    }

    private function savePropertyMeta(int $postId, array $data): void
    {
        $core_fields = ['openimmo_id', 'title', 'description', 'images', '__simplexml__'];

        foreach ($data as $key => $value) {
            if (!in_array($key, $core_fields)) {
                // The cf_ prefix is now expected to be in the mapping destination
                update_post_meta($postId, $key, $value);
            } else if ($key === 'openimmo_id') {
                update_post_meta($postId, $key, $value);
            }
        }
    }

    private function setPropertyTaxonomies(int $postId, array $data): void
    {
        if (!isset($data['__simplexml__'])) {
            return;
        }
        $simpleXmlNode = $data['__simplexml__'];

        foreach ($this->mappingService->getMappings() as $mapping) {
            if ($mapping['type'] === 'taxonomy') {
                $value = $this->mappingService->getElementValue($simpleXmlNode, $mapping);

                if ($value !== false && $value !== '0' && $value !== '') {
                    $term_name = $mapping['title de'] ?? $mapping['title'] ?? $value;
                    $taxonomy = $mapping['destination'];

                    if (!taxonomy_exists($taxonomy)) {
                        continue;
                    }

                    $parent_term_name = $mapping['parent de'] ?? $mapping['parent'] ?? null;
                    $parent_id = 0;

                    if ($parent_term_name) {
                        $parent_term = get_term_by('name', $parent_term_name, $taxonomy);
                        if ($parent_term) {
                            $parent_id = $parent_term->term_id;
                        } else {
                            $new_parent = wp_insert_term($parent_term_name, $taxonomy);
                            if (!is_wp_error($new_parent)) {
                                $parent_id = $new_parent['term_id'];
                            }
                        }
                    }

                    $term = get_term_by('name', $term_name, $taxonomy);
                    if (!$term) {
                        $new_term = wp_insert_term($term_name, $taxonomy, ['parent' => $parent_id]);
                        if (!is_wp_error($new_term)) {
                            wp_set_object_terms($postId, $new_term['term_id'], $taxonomy, true);
                        }
                    } else {
                        wp_set_object_terms($postId, $term->term_id, $taxonomy, true);
                    }
                }
            }
        }
    }

    private function importPropertyImages(int $postId, array $images, ?string $baseDir = null): void
    {
        $gallery_ids = [];
        $featured_image_id = 0;

        // First, find the designated featured image
        $featured_image_path = '';
        foreach ($images as $imageData) {
            if (strtoupper($imageData['group']) === 'TITELBILD') {
                $featured_image_path = $imageData['url'];
                break;
            }
        }

        foreach ($images as $imageData) {
            $imageUrl = $imageData['url'];
            if ($baseDir && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $imageUrl = str_replace('\\', '/', $baseDir . '/' . ltrim($imageUrl, '/\\'));
            }

            $attachmentId = $this->importImage($imageUrl, $postId, basename($imageUrl));

            if ($attachmentId) {
                $gallery_ids[] = $attachmentId;
                // Check if the current image is the one we designated as the featured image
                if ($imageUrl === $featured_image_path) {
                    $featured_image_id = $attachmentId;
                }
            }
        }

        // Set the featured image
        if ($featured_image_id) {
            set_post_thumbnail($postId, $featured_image_id);
        } elseif (!empty($gallery_ids) && !has_post_thumbnail($postId)) {
            // Fallback: if no featured image was explicitly set, use the first one from the gallery
            set_post_thumbnail($postId, $gallery_ids[0]);
        }

        // Save all gallery image IDs (including featured image) for Bricks
        if (!empty($gallery_ids)) {
            update_post_meta($postId, 'immobridge_gallery_image_ids', implode(',', $gallery_ids));
        }
    }

    private function importImage(string $imagePath, int $postId, string $title = ''): ?int
    {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $is_remote = filter_var($imagePath, FILTER_VALIDATE_URL);
        error_log('[ImmoBridge Image Log] 1. Starting import for: ' . $imagePath . ($is_remote ? ' (remote)' : ' (local)'));

        if (!$is_remote && !file_exists($imagePath)) {
            error_log('[ImmoBridge Image Log] 2. ERROR: Local file does not exist at path: ' . $imagePath);
            return null;
        }

        $temp_file = $this->create_temp_file($imagePath, $is_remote);

        if (is_wp_error($temp_file)) {
            error_log('[ImmoBridge Image Log] 2. ERROR: download_url() failed for remote file. Error: ' . $temp_file->get_error_message());
            return null;
        }

        if (!$temp_file) {
            error_log('[ImmoBridge Image Log] 2. ERROR: create_temp_file() returned false.');
            return null;
        }
        error_log('[ImmoBridge Image Log] 2. Temp file created at: ' . $temp_file);

        $file_array = [
            'name' => basename($imagePath),
            'tmp_name' => $temp_file
        ];

        $description = $title ?: '';
        error_log('[ImmoBridge Image Log] 3. Calling media_handle_sideload() with filename: ' . $file_array['name']);

        $attachmentId = media_handle_sideload($file_array, $postId, $description);

        if (is_wp_error($attachmentId)) {
            @unlink($temp_file);
            error_log('[ImmoBridge Image Log] 4. ERROR: media_handle_sideload() failed. Error: ' . $attachmentId->get_error_message());
            return null;
        }

        error_log('[ImmoBridge Image Log] 4. SUCCESS: Image imported. Attachment ID: ' . $attachmentId);
        // The temp file is automatically deleted by media_handle_sideload on success.
        return $attachmentId;
    }

    private function create_temp_file(string $source_file, bool $is_remote)
    {
        if ($is_remote) {
            error_log('[ImmoBridge Image Log] create_temp_file: Is remote, calling download_url for ' . $source_file);
            return download_url($source_file);
        }

        if (!file_exists($source_file)) {
            $this->log('Source file for temporary copy missing: ' . $source_file, 'error');
            error_log('[ImmoBridge Image Log] create_temp_file: ERROR: Source file does not exist: ' . $source_file);
            return false;
        }

        $source_file_info = pathinfo($source_file);
        $temp_dir = get_temp_dir();
        $temp_file = trailingslashit($temp_dir) . uniqid('immobridge_') . '_' . $source_file_info['basename'];
        
        error_log('[ImmoBridge Image Log] create_temp_file: Attempting to copy ' . $source_file . ' to ' . $temp_file);
        $result = copy($source_file, $temp_file);

        if (!$result) {
            $this->log('Temporary copy could not be created: ' . $temp_file, 'error');
            error_log('[ImmoBridge Image Log] create_temp_file: ERROR: copy() failed.');
            return false;
        }

        error_log('[ImmoBridge Image Log] create_temp_file: Successfully copied to temp file.');
        return $result ? $temp_file : false;
    }

    public function deleteDirectory(string $dir): bool
    {
        if (!is_dir($dir)) return false;
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        return rmdir($dir);
    }

    private function log(string $message, string $level = 'info'): void
    {
        $this->importLog[] = "[{$level}] {$message}";
    }

    private function getResults(): array
    {
        return ['imported' => $this->importedCount, 'updated' => $this->updatedCount, 'errors' => $this->errorCount, 'log' => $this->importLog];
    }

    private function resetCounters(): void
    {
        $this->importLog = [];
        $this->importedCount = 0;
        $this->updatedCount = 0;
        $this->errorCount = 0;
    }
}
