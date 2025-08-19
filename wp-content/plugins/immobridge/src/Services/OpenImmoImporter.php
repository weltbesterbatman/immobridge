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

class OpenImmoImporter
{
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

        foreach ($nodesToProcess as $propertyNode) {
            try {
                $this->importProperty($propertyNode, $xpath, $importImages, $updateExisting, $baseDir);
            } catch (\Exception $e) {
                $this->errorCount++;
                $this->log('Error importing property: ' . $e->getMessage(), 'error');
            }
        }
        
        $results = $this->getResults();
        $results['processed_in_batch'] = iterator_count($nodesToProcess);
        return $results;
    }

    private function importProperty(\DOMElement $propertyNode, DOMXPath $xpath, bool $importImages, bool $updateExisting, ?string $baseDir = null): void
    {
        $propertyData = $this->extractPropertyData($propertyNode, $xpath);
        if (empty($propertyData['property_id'])) throw new \Exception('Property ID is required but not found');

        $existingPost = $this->findExistingProperty($propertyData['property_id']);
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
            $this->log('Updated property: ' . $propertyData['property_id']);
        } else {
            $postId = wp_insert_post($postData);
            $this->importedCount++;
            $this->log('Imported new property: ' . $propertyData['property_id']);
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
        $data['property_id'] = $this->getXPathValue($xpath, './/objektnr_extern', $propertyNode);
        $data['title'] = $this->getXPathValue($xpath, './/objekttitel', $propertyNode);
        $data['description'] = $this->getXPathValue($xpath, './/objektbeschreibung', $propertyNode);
        $data['images'] = $this->extractImages($xpath, $propertyNode);
        // Extract other fields...
        return array_filter($data, fn($value) => $value !== null && $value !== '');
    }
    
    private function getXPathValue(DOMXPath $xpath, string $query, \DOMElement $context = null): ?string
    {
        $nodes = $xpath->query($query, $context);
        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
    }

    private function extractImages(DOMXPath $xpath, \DOMElement $propertyNode): array
    {
        $images = [];
        $imageNodes = $xpath->query('.//anhang[@gruppe="BILD"]', $propertyNode);
        foreach ($imageNodes as $imageNode) {
            $imageData = ['url' => $this->getXPathValue($xpath, './/daten/pfad', $imageNode)];
            if (!empty($imageData['url'])) {
                $images[] = $imageData;
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
        foreach ($data as $key => $value) {
            if ($key !== 'images') {
                update_post_meta($postId, $key, $value);
            }
        }
    }

    private function setPropertyTaxonomies(int $postId, array $data): void
    {
        // Omitted for brevity
    }

    private function importPropertyImages(int $postId, array $images, ?string $baseDir = null): void
    {
        $gallery_ids = [];
        foreach ($images as $index => $imageData) {
            $imageUrl = $imageData['url'];
            if ($baseDir && !filter_var($imageUrl, FILTER_VALIDATE_URL)) {
                $imageUrl = $baseDir . '/' . ltrim($imageUrl, '/');
            }
            $attachmentId = $this->importImage($imageUrl, $postId);
            if ($attachmentId) {
                $gallery_ids[] = $attachmentId;
                if ($index === 0) set_post_thumbnail($postId, $attachmentId);
            }
        }
        if (!empty($gallery_ids)) update_post_meta($postId, 'gallery_images', $gallery_ids);
    }

    private function importImage(string $imageUrl, int $postId, string $title = ''): ?int
    {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        $attachmentId = media_sideload_image($imageUrl, $postId, $title, 'id');
        if (is_wp_error($attachmentId)) {
            return null;
        }
        return $attachmentId;
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
