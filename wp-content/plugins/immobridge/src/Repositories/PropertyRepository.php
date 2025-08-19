<?php
/**
 * Property Repository
 *
 * @package ImmoBridge
 * @subpackage Repositories
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Repositories;

use ImmoBridge\Entities\Property;
use ImmoBridge\Entities\Enums\PropertyType;
use ImmoBridge\Entities\Enums\PropertyStatus;
use Carbon\Carbon;
use WP_Query;
use WP_Post;

/**
 * Property Repository
 *
 * Handles all property data operations using WordPress APIs.
 * Implements the Repository pattern for clean data access abstraction.
 *
 * @implements RepositoryInterface<Property>
 * @since 1.0.0
 */
final class PropertyRepository implements RepositoryInterface
{
    private const POST_TYPE = 'property';

    /**
     * Find property by ID
     *
     * @param int $id Property ID
     * @return Property|null
     */
    public function findById(int $id): ?Property
    {
        $post = get_post($id);
        
        if (!$post || $post->post_type !== self::POST_TYPE) {
            return null;
        }

        return Property::fromPost($post);
    }

    /**
     * Find property by OpenImmo ID
     *
     * @param string $openImmoId OpenImmo identifier
     * @return Property|null
     */
    public function findByOpenImmoId(string $openImmoId): ?Property
    {
        $query = new WP_Query([
            'post_type' => self::POST_TYPE,
            'meta_query' => [
                [
                    'key' => '_immobridge_openimmo_id',
                    'value' => $openImmoId,
                    'compare' => '=',
                ],
            ],
            'posts_per_page' => 1,
            'post_status' => 'any',
        ]);

        if (!$query->have_posts()) {
            return null;
        }

        return Property::fromPost($query->posts[0]);
    }

    /**
     * Find all properties
     *
     * @param array<string, mixed> $criteria Search criteria
     * @param array<string, string> $orderBy Order by criteria
     * @param int|null $limit Limit results
     * @param int|null $offset Offset results
     * @return array<Property>
     */
    public function findAll(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $args = $this->buildQueryArgs($criteria, $orderBy, $limit, $offset);
        $query = new WP_Query($args);

        return array_map(
            fn(WP_Post $post) => Property::fromPost($post),
            $query->posts
        );
    }

    /**
     * Find one property by criteria
     *
     * @param array<string, mixed> $criteria Search criteria
     * @return Property|null
     */
    public function findOneBy(array $criteria): ?Property
    {
        $properties = $this->findBy($criteria, [], 1);
        return $properties[0] ?? null;
    }

    /**
     * Find properties by criteria
     *
     * @param array<string, mixed> $criteria Search criteria
     * @param array<string, string> $orderBy Order by criteria
     * @param int|null $limit Limit results
     * @param int|null $offset Offset results
     * @return array<Property>
     */
    public function findBy(array $criteria, array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        return $this->findAll($criteria, $orderBy, $limit, $offset);
    }

    /**
     * Find properties by type
     *
     * @param PropertyType $type Property type
     * @param int|null $limit Limit results
     * @return array<Property>
     */
    public function findByType(PropertyType $type, ?int $limit = null): array
    {
        return $this->findBy(['type' => $type->value], [], $limit);
    }

    /**
     * Find properties by status
     *
     * @param PropertyStatus $status Property status
     * @param int|null $limit Limit results
     * @return array<Property>
     */
    public function findByStatus(PropertyStatus $status, ?int $limit = null): array
    {
        return $this->findBy(['status' => $status->value], [], $limit);
    }

    /**
     * Find properties by location
     *
     * @param string $city City name
     * @param string|null $zipCode ZIP code
     * @param int|null $limit Limit results
     * @return array<Property>
     */
    public function findByLocation(string $city, ?string $zipCode = null, ?int $limit = null): array
    {
        $criteria = ['city' => $city];
        if ($zipCode !== null) {
            $criteria['zip_code'] = $zipCode;
        }
        
        return $this->findBy($criteria, [], $limit);
    }

    /**
     * Save property
     *
     * @param Property $property Property to save
     * @return Property Saved property
     * @throws \RuntimeException
     */
    public function save(Property $property): Property
    {
        $postData = [
            'post_type' => self::POST_TYPE,
            'post_title' => $property->title,
            'post_content' => $property->description,
            'post_status' => $property->status->isActive() ? 'publish' : 'draft',
            'meta_input' => $this->buildMetaData($property),
        ];

        if ($property->id !== null) {
            // Update existing property
            $postData['ID'] = $property->id;
            $postId = wp_update_post($postData, true);
        } else {
            // Create new property
            $postId = wp_insert_post($postData, true);
        }

        if (is_wp_error($postId)) {
            throw new \RuntimeException('Failed to save property: ' . $postId->get_error_message());
        }

        // Set taxonomies
        $this->setTaxonomies($postId, $property);

        // Return updated property
        return $this->findById($postId) ?? throw new \RuntimeException('Failed to retrieve saved property');
    }

    /**
     * Delete property
     *
     * @param Property $property Property to delete
     * @return bool Success status
     */
    public function delete(Property $property): bool
    {
        if ($property->id === null) {
            return false;
        }

        return $this->deleteById($property->id);
    }

    /**
     * Delete property by ID
     *
     * @param int $id Property ID
     * @return bool Success status
     */
    public function deleteById(int $id): bool
    {
        $result = wp_delete_post($id, true);
        return $result !== false && $result !== null;
    }

    /**
     * Count properties
     *
     * @param array<string, mixed> $criteria Search criteria
     * @return int
     */
    public function count(array $criteria = []): int
    {
        $args = $this->buildQueryArgs($criteria);
        $args['fields'] = 'ids';
        
        $query = new WP_Query($args);
        return $query->found_posts;
    }

    /**
     * Check if property exists
     *
     * @param int $id Property ID
     * @return bool
     */
    public function exists(int $id): bool
    {
        return $this->findById($id) !== null;
    }

    /**
     * Build WP_Query arguments from criteria
     *
     * @param array<string, mixed> $criteria Search criteria
     * @param array<string, string> $orderBy Order by criteria
     * @param int|null $limit Limit results
     * @param int|null $offset Offset results
     * @return array<string, mixed>
     */
    private function buildQueryArgs(array $criteria = [], array $orderBy = [], ?int $limit = null, ?int $offset = null): array
    {
        $args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'posts_per_page' => $limit ?? -1,
        ];

        if ($offset !== null) {
            $args['offset'] = $offset;
        }

        // Build meta query
        $metaQuery = [];
        foreach ($criteria as $key => $value) {
            if (in_array($key, ['type', 'status', 'price', 'living_area', 'total_area', 'rooms', 'city', 'zip_code'], true)) {
                $metaQuery[] = [
                    'key' => '_immobridge_' . $key,
                    'value' => $value,
                    'compare' => is_array($value) ? 'IN' : '=',
                ];
            }
        }

        if (!empty($metaQuery)) {
            $args['meta_query'] = $metaQuery;
        }

        // Build order by
        if (!empty($orderBy)) {
            $orderByField = array_key_first($orderBy);
            $orderDirection = $orderBy[$orderByField];
            
            if ($orderByField === 'price' || $orderByField === 'living_area') {
                $args['meta_key'] = '_immobridge_' . $orderByField;
                $args['orderby'] = 'meta_value_num';
                $args['order'] = strtoupper($orderDirection);
            } else {
                $args['orderby'] = $orderByField;
                $args['order'] = strtoupper($orderDirection);
            }
        } else {
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
        }

        return $args;
    }

    /**
     * Build meta data array for property
     *
     * @param Property $property Property entity
     * @return array<string, mixed>
     */
    private function buildMetaData(Property $property): array
    {
        return [
            '_immobridge_openimmo_id' => $property->openImmoId,
            '_immobridge_type' => $property->type->value,
            '_immobridge_status' => $property->status->value,
            '_immobridge_price' => $property->price,
            '_immobridge_price_type' => $property->priceType,
            '_immobridge_living_area' => $property->livingArea,
            '_immobridge_total_area' => $property->totalArea,
            '_immobridge_rooms' => $property->rooms,
            '_immobridge_bedrooms' => $property->bedrooms,
            '_immobridge_bathrooms' => $property->bathrooms,
            '_immobridge_address' => $property->address,
            '_immobridge_city' => $property->city,
            '_immobridge_zip_code' => $property->zipCode,
            '_immobridge_country' => $property->country,
            '_immobridge_latitude' => $property->latitude,
            '_immobridge_longitude' => $property->longitude,
            '_immobridge_images' => json_encode($property->images),
            '_immobridge_documents' => json_encode($property->documents),
            '_immobridge_metadata' => json_encode($property->metadata),
            '_immobridge_imported_at' => $property->importedAt?->toDateTimeString(),
        ];
    }

    /**
     * Set taxonomies for property
     *
     * @param int $postId Post ID
     * @param Property $property Property entity
     */
    private function setTaxonomies(int $postId, Property $property): void
    {
        // Set property type taxonomy
        wp_set_object_terms($postId, $property->type->value, 'property_type');
        
        // Set property status taxonomy
        wp_set_object_terms($postId, $property->status->value, 'property_status');
        
        // Set location taxonomy if city is available
        if ($property->city !== null) {
            wp_set_object_terms($postId, $property->city, 'property_location');
        }
    }
}
