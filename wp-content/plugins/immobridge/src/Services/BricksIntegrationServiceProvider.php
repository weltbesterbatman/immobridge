<?php

declare(strict_types=1);

namespace ImmoBridge\Services;

use ImmoBridge\Core\Container\ServiceProviderInterface;
use ImmoBridge\Core\Container\Container;

/**
 * Bricks Builder Integration Service Provider
 * 
 * Handles integration with Bricks Builder theme including:
 * - Dynamic Data tags for OpenImmo fields
 * - Custom elements registration
 * - Template system integration
 * 
 * @package ImmoBridge\Services
 * @since 1.0.0
 */
final class BricksIntegrationServiceProvider implements ServiceProviderInterface
{
    private Container $container;
    
    public function register(Container $container): void
    {
        $this->container = $container;
        
        if (!$this->isBricksActive()) {
            return;
        }
        
        add_action('init', [$this, 'initBricksIntegration'], 20);
        add_filter('bricks/dynamic_data/register_tags', [$this, 'registerDynamicDataTags']);
        add_filter('bricks/dynamic_data/render_tag', [$this, 'render_dynamic_data_tag'], 10, 3);
    }

    public function boot(Container $container): void
    {
        // Bootstrapping logic can be added here if needed
    }
    
    private function isBricksActive(): bool
    {
        return defined('BRICKS_VERSION');
    }
    
    public function initBricksIntegration(): void
    {
        add_post_type_support('immo_property', 'bricks');
    }
    
    public function registerDynamicDataTags(array $tags): array
    {
        $property_tags = [
            'immobridge_property_title' => ['label' => 'Titel'],
            'immobridge_property_description' => ['label' => 'Beschreibung'],
            'immobridge_openimmo_id' => ['label' => 'OpenImmo ID'],
            'immobridge_property_price' => ['label' => 'Preis'],
            'immobridge_property_price_formatted' => ['label' => 'Preis (Formatiert)'],
            'immobridge_property_rent' => ['label' => 'Miete'],
            'immobridge_property_additional_costs' => ['label' => 'Nebenkosten'],
            'immobridge_property_living_area' => ['label' => 'Wohnfläche'],
            'immobridge_property_total_area' => ['label' => 'Gesamtfläche'],
            'immobridge_property_address' => ['label' => 'Adresse'],
            'immobridge_property_city' => ['label' => 'Stadt'],
            'immobridge_property_postal_code' => ['label' => 'PLZ'],
            'immobridge_property_country' => ['label' => 'Land'],
            'immobridge_property_rooms' => ['label' => 'Zimmer'],
            'immobridge_property_bedrooms' => ['label' => 'Schlafzimmer'],
            'immobridge_property_bathrooms' => ['label' => 'Badezimmer'],
            'immobridge_property_type' => ['label' => 'Objekttyp'],
            'immobridge_property_status' => ['label' => 'Status'],
            'immobridge_property_features' => ['label' => 'Ausstattung'],
            'immobridge_property_equipment' => ['label' => 'Einrichtung'],
            'immobridge_property_contact_name' => ['label' => 'Kontaktperson'],
            'immobridge_property_contact_phone' => ['label' => 'Telefon'],
            'immobridge_property_contact_email' => ['label' => 'E-Mail'],
            'immobridge_property_energy_class' => ['label' => 'Energieeffizienzklasse'],
            'immobridge_property_energy_consumption' => ['label' => 'Energieverbrauch'],
            'immobridge_property_featured_image' => ['label' => 'Beitragsbild'],
            'immobridge_property_gallery' => ['label' => 'Bildergalerie'],
        ];

        foreach ($property_tags as $key => $tag) {
            $tags[$key] = [
                'label'    => $tag['label'],
                'group'    => 'ImmoBridge',
            ];
        }

        return $tags;
    }

    public function render_dynamic_data_tag($content, $tag, $post)
    {
        if (strpos($tag, 'immobridge_') !== 0) {
            return $content;
        }

        if (!$post || get_post_type($post) !== 'immo_property') {
            return $content;
        }

        $post_id = $post->ID;
        $field_key = str_replace('immobridge_property_', '', $tag);
        
        // Map tag to meta key
        $meta_keys = [
            'title' => 'object_title',
            'description' => 'description',
            'openimmo_id' => 'openimmo_obid',
            'price' => 'price_value',
            'price_formatted' => 'price_value',
            'rent' => 'price_value', // Assuming rent is also in price_value
            'additional_costs' => 'additional_costs',
            'living_area' => 'living_area',
            'total_area' => 'total_area',
            'address' => 'address_street', // Simplified, might need concatenation
            'city' => 'address_city',
            'postal_code' => 'address_postal_code',
            'country' => 'address_country',
            'rooms' => 'number_of_rooms',
            'bedrooms' => 'number_of_bedrooms',
            'bathrooms' => 'number_of_bathrooms',
            'type' => 'property_type',
            'status' => 'object_status',
            'features' => 'features', // Assuming this is a comma-separated string or array
            'equipment' => 'equipment',
            'contact_name' => 'contact_name',
            'contact_phone' => 'contact_phone',
            'contact_email' => 'contact_email',
            'energy_class' => 'energy_efficiency_class',
            'energy_consumption' => 'energy_consumption_value',
            'featured_image' => '_thumbnail_id',
            'gallery' => 'gallery_images', // Assuming this is an array of attachment IDs
        ];

        if (!isset($meta_keys[$field_key])) {
            return $content;
        }

        $meta_key = $meta_keys[$field_key];
        $value = get_post_meta($post_id, $meta_key, true);

        if (empty($value)) {
            // Fallback for title and description
            if ($field_key === 'title') return get_the_title($post_id);
            if ($field_key === 'description') return get_the_content($post_id);
            return '';
        }

        // Format specific fields
        switch ($tag) {
            case 'immobridge_property_price_formatted':
                return number_format((float)$value, 2, ',', '.') . ' €';
            
            case 'immobridge_property_living_area':
            case 'immobridge_property_total_area':
                return $value . ' m²';

            case 'immobridge_property_energy_consumption':
                return $value . ' kWh/(m²·a)';

            case 'immobridge_property_featured_image':
                return wp_get_attachment_url((int)$value);

            case 'immobridge_property_gallery':
                if (is_array($value)) {
                    $image_urls = array_map('wp_get_attachment_url', $value);
                    // Bricks gallery expects an array of image objects
                    $gallery = [];
                    foreach($value as $id) {
                        $gallery[] = ['id' => $id, 'url' => wp_get_attachment_url($id)];
                    }
                    return $gallery;
                }
                return [];

            case 'immobridge_property_address':
                 $street = get_post_meta($post_id, 'address_street', true);
                 $city = get_post_meta($post_id, 'address_city', true);
                 $zip = get_post_meta($post_id, 'address_postal_code', true);
                 return "$street, $zip $city";

            default:
                return is_array($value) ? implode(', ', $value) : (string) $value;
        }
    }
}
