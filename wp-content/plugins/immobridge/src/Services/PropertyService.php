<?php
/**
 * Property Service
 *
 * @package ImmoBridge
 * @subpackage Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Services;

use ImmoBridge\Entities\Property;
use ImmoBridge\Entities\Enums\PropertyType;
use ImmoBridge\Entities\Enums\PropertyStatus;
use ImmoBridge\Repositories\PropertyRepository;
use Carbon\Carbon;

/**
 * Property Service
 *
 * Provides business logic for property operations including validation,
 * transformation, and complex queries.
 *
 * @since 1.0.0
 */
final class PropertyService
{
    public function __construct(
        private readonly PropertyRepository $repository
    ) {
    }

    /**
     * Get property by ID
     *
     * @param int $id Property ID
     * @return Property|null
     */
    public function getById(int $id): ?Property
    {
        return $this->repository->findById($id);
    }

    /**
     * Get property by OpenImmo ID
     *
     * @param string $openImmoId OpenImmo identifier
     * @return Property|null
     */
    public function getByOpenImmoId(string $openImmoId): ?Property
    {
        return $this->repository->findByOpenImmoId($openImmoId);
    }

    /**
     * Get all properties with optional filtering
     *
     * @param array<string, mixed> $filters Filters to apply
     * @param int $page Page number (1-based)
     * @param int $perPage Items per page
     * @return array{properties: array<Property>, total: int, pages: int}
     */
    public function getAll(array $filters = [], int $page = 1, int $perPage = 20): array
    {
        $criteria = $this->buildCriteria($filters);
        $orderBy = $this->buildOrderBy($filters);
        $offset = ($page - 1) * $perPage;

        $properties = $this->repository->findAll($criteria, $orderBy, $perPage, $offset);
        $total = $this->repository->count($criteria);
        $pages = (int) ceil($total / $perPage);

        return [
            'properties' => $properties,
            'total' => $total,
            'pages' => $pages,
        ];
    }

    /**
     * Get active properties (available and reserved)
     *
     * @param int $limit Maximum number of properties
     * @return array<Property>
     */
    public function getActive(int $limit = 20): array
    {
        $activeStatuses = array_map(fn(PropertyStatus $status) => $status->value, PropertyStatus::getActive());
        
        return $this->repository->findBy(
            ['status' => $activeStatuses],
            ['date' => 'DESC'],
            $limit
        );
    }

    /**
     * Get featured properties
     *
     * @param int $limit Maximum number of properties
     * @return array<Property>
     */
    public function getFeatured(int $limit = 10): array
    {
        return $this->repository->findBy(
            ['featured' => true, 'status' => PropertyStatus::AVAILABLE->value],
            ['date' => 'DESC'],
            $limit
        );
    }

    /**
     * Search properties by text
     *
     * @param string $searchTerm Search term
     * @param int $limit Maximum number of properties
     * @return array<Property>
     */
    public function search(string $searchTerm, int $limit = 20): array
    {
        // This would typically use a more sophisticated search implementation
        // For now, we'll search in title and description
        $query = new \WP_Query([
            'post_type' => 'property',
            's' => $searchTerm,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
        ]);

        return array_map(
            fn(\WP_Post $post) => Property::fromPost($post),
            $query->posts
        );
    }

    /**
     * Get properties by location
     *
     * @param string $city City name
     * @param string|null $zipCode ZIP code
     * @param int $limit Maximum number of properties
     * @return array<Property>
     */
    public function getByLocation(string $city, ?string $zipCode = null, int $limit = 20): array
    {
        return $this->repository->findByLocation($city, $zipCode, $limit);
    }

    /**
     * Get properties by type
     *
     * @param PropertyType $type Property type
     * @param int $limit Maximum number of properties
     * @return array<Property>
     */
    public function getByType(PropertyType $type, int $limit = 20): array
    {
        return $this->repository->findByType($type, $limit);
    }

    /**
     * Get properties by price range
     *
     * @param float|null $minPrice Minimum price
     * @param float|null $maxPrice Maximum price
     * @param int $limit Maximum number of properties
     * @return array<Property>
     */
    public function getByPriceRange(?float $minPrice = null, ?float $maxPrice = null, int $limit = 20): array
    {
        $metaQuery = [];

        if ($minPrice !== null) {
            $metaQuery[] = [
                'key' => '_immobridge_price',
                'value' => $minPrice,
                'type' => 'NUMERIC',
                'compare' => '>=',
            ];
        }

        if ($maxPrice !== null) {
            $metaQuery[] = [
                'key' => '_immobridge_price',
                'value' => $maxPrice,
                'type' => 'NUMERIC',
                'compare' => '<=',
            ];
        }

        if (empty($metaQuery)) {
            return [];
        }

        $query = new \WP_Query([
            'post_type' => 'property',
            'meta_query' => $metaQuery,
            'posts_per_page' => $limit,
            'post_status' => 'publish',
            'orderby' => 'meta_value_num',
            'meta_key' => '_immobridge_price',
            'order' => 'ASC',
        ]);

        return array_map(
            fn(\WP_Post $post) => Property::fromPost($post),
            $query->posts
        );
    }

    /**
     * Create a new property
     *
     * @param array<string, mixed> $data Property data
     * @return Property Created property
     * @throws \InvalidArgumentException
     */
    public function create(array $data): Property
    {
        $this->validatePropertyData($data);

        $property = Property::fromOpenImmoData($data);
        return $this->repository->save($property);
    }

    /**
     * Update an existing property
     *
     * @param int $id Property ID
     * @param array<string, mixed> $data Updated property data
     * @return Property|null Updated property or null if not found
     * @throws \InvalidArgumentException
     */
    public function update(int $id, array $data): ?Property
    {
        $existingProperty = $this->repository->findById($id);
        if ($existingProperty === null) {
            return null;
        }

        $this->validatePropertyData($data);

        // Create updated property with new data
        $updatedProperty = new Property(
            id: $existingProperty->id,
            openImmoId: $data['openimmo_id'] ?? $existingProperty->openImmoId,
            title: $data['title'] ?? $existingProperty->title,
            description: $data['description'] ?? $existingProperty->description,
            type: isset($data['type']) ? PropertyType::from($data['type']) : $existingProperty->type,
            status: isset($data['status']) ? PropertyStatus::from($data['status']) : $existingProperty->status,
            price: $data['price'] ?? $existingProperty->price,
            priceType: $data['price_type'] ?? $existingProperty->priceType,
            livingArea: $data['living_area'] ?? $existingProperty->livingArea,
            totalArea: $data['total_area'] ?? $existingProperty->totalArea,
            rooms: $data['rooms'] ?? $existingProperty->rooms,
            bedrooms: $data['bedrooms'] ?? $existingProperty->bedrooms,
            bathrooms: $data['bathrooms'] ?? $existingProperty->bathrooms,
            address: $data['address'] ?? $existingProperty->address,
            city: $data['city'] ?? $existingProperty->city,
            zipCode: $data['zip_code'] ?? $existingProperty->zipCode,
            country: $data['country'] ?? $existingProperty->country,
            latitude: $data['latitude'] ?? $existingProperty->latitude,
            longitude: $data['longitude'] ?? $existingProperty->longitude,
            images: $data['images'] ?? $existingProperty->images,
            documents: $data['documents'] ?? $existingProperty->documents,
            metadata: array_merge($existingProperty->metadata, $data['metadata'] ?? []),
            createdAt: $existingProperty->createdAt,
            updatedAt: Carbon::now(),
            importedAt: $existingProperty->importedAt,
        );

        return $this->repository->save($updatedProperty);
    }

    /**
     * Delete property
     *
     * @param int $id Property ID
     * @return bool Success status
     */
    public function delete(int $id): bool
    {
        return $this->repository->deleteById($id);
    }

    /**
     * Get property statistics
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $stats = [
            'total' => $this->repository->count(),
            'by_status' => [],
            'by_type' => [],
            'average_price' => 0,
            'recent_imports' => 0,
        ];

        // Count by status
        foreach (PropertyStatus::cases() as $status) {
            $stats['by_status'][$status->value] = $this->repository->count(['status' => $status->value]);
        }

        // Count by type
        foreach (PropertyType::cases() as $type) {
            $stats['by_type'][$type->value] = $this->repository->count(['type' => $type->value]);
        }

        // Calculate average price (simplified)
        global $wpdb;
        $avgPrice = $wpdb->get_var("
            SELECT AVG(CAST(meta_value AS DECIMAL(10,2))) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_immobridge_price' 
            AND meta_value != '' 
            AND meta_value IS NOT NULL
        ");
        $stats['average_price'] = $avgPrice ? (float) $avgPrice : 0;

        // Count recent imports (last 24 hours)
        $yesterday = Carbon::now()->subDay()->toDateTimeString();
        $recentImports = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_immobridge_imported_at' 
            AND meta_value >= %s
        ", $yesterday));
        $stats['recent_imports'] = (int) $recentImports;

        return $stats;
    }

    /**
     * Validate property data
     *
     * @param array<string, mixed> $data Property data
     * @throws \InvalidArgumentException
     */
    private function validatePropertyData(array $data): void
    {
        // Required fields
        if (empty($data['title'])) {
            throw new \InvalidArgumentException('Property title is required');
        }

        if (empty($data['openimmo_id'])) {
            throw new \InvalidArgumentException('OpenImmo ID is required');
        }

        // Validate enums
        if (isset($data['type']) && PropertyType::tryFrom($data['type']) === null) {
            throw new \InvalidArgumentException('Invalid property type: ' . $data['type']);
        }

        if (isset($data['status']) && PropertyStatus::tryFrom($data['status']) === null) {
            throw new \InvalidArgumentException('Invalid property status: ' . $data['status']);
        }

        // Validate numeric fields
        $numericFields = ['price', 'living_area', 'total_area', 'rooms', 'bedrooms', 'bathrooms', 'latitude', 'longitude'];
        foreach ($numericFields as $field) {
            if (isset($data[$field]) && !is_numeric($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' must be numeric");
            }
        }

        // Validate arrays
        $arrayFields = ['images', 'documents', 'metadata'];
        foreach ($arrayFields as $field) {
            if (isset($data[$field]) && !is_array($data[$field])) {
                throw new \InvalidArgumentException("Field '{$field}' must be an array");
            }
        }
    }

    /**
     * Build criteria array from filters
     *
     * @param array<string, mixed> $filters Filters to apply
     * @return array<string, mixed>
     */
    private function buildCriteria(array $filters): array
    {
        $criteria = [];

        if (isset($filters['type'])) {
            $criteria['type'] = $filters['type'];
        }

        if (isset($filters['status'])) {
            $criteria['status'] = $filters['status'];
        }

        if (isset($filters['city'])) {
            $criteria['city'] = $filters['city'];
        }

        if (isset($filters['zip_code'])) {
            $criteria['zip_code'] = $filters['zip_code'];
        }

        return $criteria;
    }

    /**
     * Build order by array from filters
     *
     * @param array<string, mixed> $filters Filters to apply
     * @return array<string, string>
     */
    private function buildOrderBy(array $filters): array
    {
        $orderBy = [];

        if (isset($filters['sort_by'])) {
            $direction = $filters['sort_direction'] ?? 'DESC';
            $orderBy[$filters['sort_by']] = strtoupper($direction);
        } else {
            $orderBy['date'] = 'DESC';
        }

        return $orderBy;
    }
}
