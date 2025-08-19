<?php
/**
 * Property Entity
 *
 * @package ImmoBridge
 * @subpackage Entities
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Entities;

use ImmoBridge\Entities\Enums\PropertyType;
use ImmoBridge\Entities\Enums\PropertyStatus;
use Carbon\Carbon;
use JsonSerializable;

/**
 * Property Entity
 *
 * Represents a real estate property with all its attributes and metadata.
 * Uses modern PHP 8.2+ features including readonly properties and enums.
 *
 * @since 1.0.0
 */
final readonly class Property implements JsonSerializable
{
    /**
     * Property constructor with promoted properties
     *
     * @param int|null $id WordPress post ID
     * @param string $openImmoId Unique OpenImmo identifier
     * @param string $title Property title
     * @param string $description Property description
     * @param PropertyType $type Property type
     * @param PropertyStatus $status Property status
     * @param float|null $price Property price
     * @param string|null $priceType Price type (rent, sale, etc.)
     * @param float|null $livingArea Living area in square meters
     * @param float|null $totalArea Total area in square meters
     * @param int|null $rooms Number of rooms
     * @param int|null $bedrooms Number of bedrooms
     * @param int|null $bathrooms Number of bathrooms
     * @param string|null $address Property address
     * @param string|null $city City
     * @param string|null $zipCode ZIP code
     * @param string|null $country Country
     * @param float|null $latitude Latitude coordinate
     * @param float|null $longitude Longitude coordinate
     * @param array<string> $images Array of image URLs
     * @param array<string> $documents Array of document URLs
     * @param array<string, mixed> $metadata Additional metadata
     * @param Carbon|null $createdAt Creation timestamp
     * @param Carbon|null $updatedAt Last update timestamp
     * @param Carbon|null $importedAt Import timestamp
     */
    public function __construct(
        public ?int $id = null,
        public string $openImmoId = '',
        public string $title = '',
        public string $description = '',
        public PropertyType $type = PropertyType::APARTMENT,
        public PropertyStatus $status = PropertyStatus::AVAILABLE,
        public ?float $price = null,
        public ?string $priceType = null,
        public ?float $livingArea = null,
        public ?float $totalArea = null,
        public ?int $rooms = null,
        public ?int $bedrooms = null,
        public ?int $bathrooms = null,
        public ?string $address = null,
        public ?string $city = null,
        public ?string $zipCode = null,
        public ?string $country = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public array $images = [],
        public array $documents = [],
        public array $metadata = [],
        public ?Carbon $createdAt = null,
        public ?Carbon $updatedAt = null,
        public ?Carbon $importedAt = null,
    ) {
    }

    /**
     * Create a new Property instance from WordPress post
     *
     * @param \WP_Post $post WordPress post object
     * @return self
     */
    public static function fromPost(\WP_Post $post): self
    {
        $meta = get_post_meta($post->ID);
        
        return new self(
            id: $post->ID,
            openImmoId: $meta['_immobridge_openimmo_id'][0] ?? '',
            title: $post->post_title,
            description: $post->post_content,
            type: PropertyType::tryFrom($meta['_immobridge_type'][0] ?? '') ?? PropertyType::APARTMENT,
            status: PropertyStatus::tryFrom($meta['_immobridge_status'][0] ?? '') ?? PropertyStatus::AVAILABLE,
            price: isset($meta['_immobridge_price'][0]) ? (float) $meta['_immobridge_price'][0] : null,
            priceType: $meta['_immobridge_price_type'][0] ?? null,
            livingArea: isset($meta['_immobridge_living_area'][0]) ? (float) $meta['_immobridge_living_area'][0] : null,
            totalArea: isset($meta['_immobridge_total_area'][0]) ? (float) $meta['_immobridge_total_area'][0] : null,
            rooms: isset($meta['_immobridge_rooms'][0]) ? (int) $meta['_immobridge_rooms'][0] : null,
            bedrooms: isset($meta['_immobridge_bedrooms'][0]) ? (int) $meta['_immobridge_bedrooms'][0] : null,
            bathrooms: isset($meta['_immobridge_bathrooms'][0]) ? (int) $meta['_immobridge_bathrooms'][0] : null,
            address: $meta['_immobridge_address'][0] ?? null,
            city: $meta['_immobridge_city'][0] ?? null,
            zipCode: $meta['_immobridge_zip_code'][0] ?? null,
            country: $meta['_immobridge_country'][0] ?? null,
            latitude: isset($meta['_immobridge_latitude'][0]) ? (float) $meta['_immobridge_latitude'][0] : null,
            longitude: isset($meta['_immobridge_longitude'][0]) ? (float) $meta['_immobridge_longitude'][0] : null,
            images: isset($meta['_immobridge_images'][0]) ? json_decode($meta['_immobridge_images'][0], true) : [],
            documents: isset($meta['_immobridge_documents'][0]) ? json_decode($meta['_immobridge_documents'][0], true) : [],
            metadata: isset($meta['_immobridge_metadata'][0]) ? json_decode($meta['_immobridge_metadata'][0], true) : [],
            createdAt: Carbon::parse($post->post_date),
            updatedAt: Carbon::parse($post->post_modified),
            importedAt: isset($meta['_immobridge_imported_at'][0]) ? Carbon::parse($meta['_immobridge_imported_at'][0]) : null,
        );
    }

    /**
     * Create a new Property instance from OpenImmo XML data
     *
     * @param array<string, mixed> $data OpenImmo data array
     * @return self
     */
    public static function fromOpenImmoData(array $data): self
    {
        return new self(
            openImmoId: $data['openimmo_id'] ?? '',
            title: $data['title'] ?? '',
            description: $data['description'] ?? '',
            type: PropertyType::tryFrom($data['type'] ?? '') ?? PropertyType::APARTMENT,
            status: PropertyStatus::tryFrom($data['status'] ?? '') ?? PropertyStatus::AVAILABLE,
            price: isset($data['price']) ? (float) $data['price'] : null,
            priceType: $data['price_type'] ?? null,
            livingArea: isset($data['living_area']) ? (float) $data['living_area'] : null,
            totalArea: isset($data['total_area']) ? (float) $data['total_area'] : null,
            rooms: isset($data['rooms']) ? (int) $data['rooms'] : null,
            bedrooms: isset($data['bedrooms']) ? (int) $data['bedrooms'] : null,
            bathrooms: isset($data['bathrooms']) ? (int) $data['bathrooms'] : null,
            address: $data['address'] ?? null,
            city: $data['city'] ?? null,
            zipCode: $data['zip_code'] ?? null,
            country: $data['country'] ?? null,
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            images: $data['images'] ?? [],
            documents: $data['documents'] ?? [],
            metadata: $data['metadata'] ?? [],
            importedAt: Carbon::now(),
        );
    }

    /**
     * Get formatted price with currency
     */
    public function getFormattedPrice(): string
    {
        if ($this->price === null) {
            return __('Price on request', 'immobridge');
        }

        $currency = $this->metadata['currency'] ?? '€';
        $formatted = number_format($this->price, 0, ',', '.');
        
        return match ($this->priceType) {
            'rent' => sprintf(__('%s %s/month', 'immobridge'), $formatted, $currency),
            'sale' => sprintf(__('%s %s', 'immobridge'), $formatted, $currency),
            default => sprintf(__('%s %s', 'immobridge'), $formatted, $currency),
        };
    }

    /**
     * Get full address as string
     */
    public function getFullAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->zipCode,
            $this->city,
            $this->country,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Check if property has coordinates
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Get primary image URL
     */
    public function getPrimaryImage(): ?string
    {
        return $this->images[0] ?? null;
    }

    /**
     * Get all images except primary
     *
     * @return array<string>
     */
    public function getSecondaryImages(): array
    {
        return array_slice($this->images, 1);
    }

    /**
     * Check if property has images
     */
    public function hasImages(): bool
    {
        return !empty($this->images);
    }

    /**
     * Check if property has documents
     */
    public function hasDocuments(): bool
    {
        return !empty($this->documents);
    }

    /**
     * Get property permalink
     */
    public function getPermalink(): string
    {
        if ($this->id === null) {
            return '';
        }

        return get_permalink($this->id) ?: '';
    }

    /**
     * Convert to array for JSON serialization
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'openimmo_id' => $this->openImmoId,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'type_label' => $this->type->getLabel(),
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'price' => $this->price,
            'price_formatted' => $this->getFormattedPrice(),
            'price_type' => $this->priceType,
            'living_area' => $this->livingArea,
            'total_area' => $this->totalArea,
            'rooms' => $this->rooms,
            'bedrooms' => $this->bedrooms,
            'bathrooms' => $this->bathrooms,
            'address' => $this->address,
            'city' => $this->city,
            'zip_code' => $this->zipCode,
            'country' => $this->country,
            'full_address' => $this->getFullAddress(),
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'has_coordinates' => $this->hasCoordinates(),
            'images' => $this->images,
            'primary_image' => $this->getPrimaryImage(),
            'documents' => $this->documents,
            'metadata' => $this->metadata,
            'permalink' => $this->getPermalink(),
            'created_at' => $this->createdAt?->toISOString(),
            'updated_at' => $this->updatedAt?->toISOString(),
            'imported_at' => $this->importedAt?->toISOString(),
        ];
    }

    /**
     * JSON serialization
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
