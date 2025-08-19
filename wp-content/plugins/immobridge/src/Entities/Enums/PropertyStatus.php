<?php
/**
 * Property Status Enum
 *
 * @package ImmoBridge
 * @subpackage Entities\Enums
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Entities\Enums;

/**
 * Property Status Enum
 *
 * Defines all possible property statuses according to OpenImmo standard.
 *
 * @since 1.0.0
 */
enum PropertyStatus: string
{
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case SOLD = 'sold';
    case RENTED = 'rented';
    case WITHDRAWN = 'withdrawn';
    case INACTIVE = 'inactive';
    case REFERENCE = 'reference';

    /**
     * Get human-readable label
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::AVAILABLE => __('Available', 'immobridge'),
            self::RESERVED => __('Reserved', 'immobridge'),
            self::SOLD => __('Sold', 'immobridge'),
            self::RENTED => __('Rented', 'immobridge'),
            self::WITHDRAWN => __('Withdrawn', 'immobridge'),
            self::INACTIVE => __('Inactive', 'immobridge'),
            self::REFERENCE => __('Reference', 'immobridge'),
        };
    }

    /**
     * Get CSS class for status styling
     */
    public function getCssClass(): string
    {
        return match ($this) {
            self::AVAILABLE => 'status-available',
            self::RESERVED => 'status-reserved',
            self::SOLD => 'status-sold',
            self::RENTED => 'status-rented',
            self::WITHDRAWN => 'status-withdrawn',
            self::INACTIVE => 'status-inactive',
            self::REFERENCE => 'status-reference',
        };
    }

    /**
     * Get color code for status
     */
    public function getColor(): string
    {
        return match ($this) {
            self::AVAILABLE => '#28a745', // Green
            self::RESERVED => '#ffc107', // Yellow
            self::SOLD => '#dc3545', // Red
            self::RENTED => '#17a2b8', // Blue
            self::WITHDRAWN => '#6c757d', // Gray
            self::INACTIVE => '#6c757d', // Gray
            self::REFERENCE => '#007bff', // Primary blue
        };
    }

    /**
     * Get all property statuses as array
     *
     * @return array<string, string>
     */
    public static function getAll(): array
    {
        $statuses = [];
        foreach (self::cases() as $case) {
            $statuses[$case->value] = $case->getLabel();
        }
        return $statuses;
    }

    /**
     * Get active statuses (properties that should be displayed)
     *
     * @return array<PropertyStatus>
     */
    public static function getActive(): array
    {
        return [
            self::AVAILABLE,
            self::RESERVED,
        ];
    }

    /**
     * Get inactive statuses (properties that should be hidden)
     *
     * @return array<PropertyStatus>
     */
    public static function getInactive(): array
    {
        return [
            self::SOLD,
            self::RENTED,
            self::WITHDRAWN,
            self::INACTIVE,
        ];
    }

    /**
     * Check if status is active (should be displayed)
     */
    public function isActive(): bool
    {
        return in_array($this, self::getActive(), true);
    }

    /**
     * Check if status is inactive (should be hidden)
     */
    public function isInactive(): bool
    {
        return in_array($this, self::getInactive(), true);
    }

    /**
     * Check if status indicates property is no longer available
     */
    public function isUnavailable(): bool
    {
        return in_array($this, [self::SOLD, self::RENTED, self::WITHDRAWN], true);
    }
}
