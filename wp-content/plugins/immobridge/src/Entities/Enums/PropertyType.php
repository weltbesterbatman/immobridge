<?php
/**
 * Property Type Enum
 *
 * @package ImmoBridge
 * @subpackage Entities\Enums
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Entities\Enums;

/**
 * Property Type Enum
 *
 * Defines all possible property types according to OpenImmo standard.
 *
 * @since 1.0.0
 */
enum PropertyType: string
{
    case APARTMENT = 'apartment';
    case HOUSE = 'house';
    case COMMERCIAL = 'commercial';
    case OFFICE = 'office';
    case RETAIL = 'retail';
    case WAREHOUSE = 'warehouse';
    case INDUSTRIAL = 'industrial';
    case LAND = 'land';
    case GARAGE = 'garage';
    case PARKING = 'parking';
    case INVESTMENT = 'investment';
    case SPECIAL = 'special';

    /**
     * Get human-readable label
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::APARTMENT => __('Apartment', 'immobridge'),
            self::HOUSE => __('House', 'immobridge'),
            self::COMMERCIAL => __('Commercial', 'immobridge'),
            self::OFFICE => __('Office', 'immobridge'),
            self::RETAIL => __('Retail', 'immobridge'),
            self::WAREHOUSE => __('Warehouse', 'immobridge'),
            self::INDUSTRIAL => __('Industrial', 'immobridge'),
            self::LAND => __('Land', 'immobridge'),
            self::GARAGE => __('Garage', 'immobridge'),
            self::PARKING => __('Parking', 'immobridge'),
            self::INVESTMENT => __('Investment', 'immobridge'),
            self::SPECIAL => __('Special', 'immobridge'),
        };
    }

    /**
     * Get all property types as array
     *
     * @return array<string, string>
     */
    public static function getAll(): array
    {
        $types = [];
        foreach (self::cases() as $case) {
            $types[$case->value] = $case->getLabel();
        }
        return $types;
    }

    /**
     * Get property types for residential properties
     *
     * @return array<PropertyType>
     */
    public static function getResidential(): array
    {
        return [
            self::APARTMENT,
            self::HOUSE,
        ];
    }

    /**
     * Get property types for commercial properties
     *
     * @return array<PropertyType>
     */
    public static function getCommercial(): array
    {
        return [
            self::COMMERCIAL,
            self::OFFICE,
            self::RETAIL,
            self::WAREHOUSE,
            self::INDUSTRIAL,
        ];
    }

    /**
     * Check if property type is residential
     */
    public function isResidential(): bool
    {
        return in_array($this, self::getResidential(), true);
    }

    /**
     * Check if property type is commercial
     */
    public function isCommercial(): bool
    {
        return in_array($this, self::getCommercial(), true);
    }
}
