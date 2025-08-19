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
        
        // Only initialize if Bricks theme is active
        if (!$this->isBricksActive()) {
            return;
        }
        
        // Register hooks for Bricks integration
        add_action('init', [$this, 'initBricksIntegration'], 20);
        add_filter('bricks/dynamic_data/providers', [$this, 'registerDynamicDataProvider']);
        add_filter('bricks/dynamic_data/tags', [$this, 'registerDynamicDataTags']);
        add_action('bricks/builder/before_save_post', [$this, 'validatePropertyTemplate']);
    }

    public function boot(Container $container): void
    {
        // Bootstrapping logic can be added here if needed
    }
    
    /**
     * Check if Bricks theme is active
     */
    private function isBricksActive(): bool
    {
        return defined('BRICKS_VERSION') || 
               get_template() === 'bricks' || 
               wp_get_theme()->get('Name') === 'Bricks';
    }
    
    /**
     * Initialize Bricks Builder integration
     */
    public function initBricksIntegration(): void
    {
        // Register custom post type support in Bricks
        $this->registerPostTypeSupport();
        
        // Add custom CSS classes for property elements
        add_filter('bricks/element/classes', [$this, 'addPropertyElementClasses'], 10, 2);
        
        // Register custom query loops for properties
        add_filter('bricks/query/run', [$this, 'customPropertyQuery'], 10, 2);
    }
    
    /**
     * Register ImmoBridge as Dynamic Data provider
     */
    public function registerDynamicDataProvider(array $providers): array
    {
        $providers['immobridge'] = [
            'label' => __('ImmoBridge Properties', 'immobridge'),
            'description' => __('OpenImmo property data fields', 'immobridge'),
            'icon' => 'fas fa-home',
            'post_types' => ['immo_property']
        ];
        
        return $providers;
    }
    
    /**
     * Register Dynamic Data tags for OpenImmo fields
     */
    public function registerDynamicDataTags(array $tags): array
    {
        $groups = [
            'immobridge_general' => __('ImmoBridge: Allgemein', 'immobridge'),
            'immobridge_price' => __('ImmoBridge: Preis', 'immobridge'),
            'immobridge_area' => __('ImmoBridge: Flächen', 'immobridge'),
            'immobridge_address' => __('ImmoBridge: Adresse', 'immobridge'),
            'immobridge_features' => __('ImmoBridge: Merkmale', 'immobridge'),
            'immobridge_contact' => __('ImmoBridge: Kontakt', 'immobridge'),
            'immobridge_energy' => __('ImmoBridge: Energie', 'immobridge'),
            'immobridge_media' => __('ImmoBridge: Medien', 'immobridge'),
        ];

        foreach ($groups as $key => $label) {
            add_filter("bricks/dynamic_data/groups/{$key}", function() use ($label) {
                return $label;
            });
        }

        $property_tags = [
            // Allgemein
            'immobridge_property_title' => ['group' => 'immobridge_general', 'label' => __('Titel', 'immobridge'), 'callback' => [$this, 'getPropertyTitle']],
            'immobridge_property_description' => ['group' => 'immobridge_general', 'label' => __('Beschreibung', 'immobridge'), 'callback' => [$this, 'getPropertyDescription']],
            'immobridge_openimmo_id' => ['group' => 'immobridge_general', 'label' => __('OpenImmo ID', 'immobridge'), 'callback' => [$this, 'getOpenImmoId']],

            // Preis
            'immobridge_property_price' => ['group' => 'immobridge_price', 'label' => __('Preis', 'immobridge'), 'callback' => [$this, 'getPropertyPrice']],
            'immobridge_property_price_formatted' => ['group' => 'immobridge_price', 'label' => __('Preis (Formatiert)', 'immobridge'), 'callback' => [$this, 'getPropertyPriceFormatted']],
            'immobridge_property_rent' => ['group' => 'immobridge_price', 'label' => __('Miete', 'immobridge'), 'callback' => [$this, 'getPropertyRent']],
            'immobridge_property_additional_costs' => ['group' => 'immobridge_price', 'label' => __('Nebenkosten', 'immobridge'), 'callback' => [$this, 'getPropertyAdditionalCosts']],

            // Flächen
            'immobridge_property_living_area' => ['group' => 'immobridge_area', 'label' => __('Wohnfläche', 'immobridge'), 'callback' => [$this, 'getPropertyLivingArea']],
            'immobridge_property_total_area' => ['group' => 'immobridge_area', 'label' => __('Gesamtfläche', 'immobridge'), 'callback' => [$this, 'getPropertyTotalArea']],

            // Adresse
            'immobridge_property_address' => ['group' => 'immobridge_address', 'label' => __('Adresse', 'immobridge'), 'callback' => [$this, 'getPropertyAddress']],
            'immobridge_property_city' => ['group' => 'immobridge_address', 'label' => __('Stadt', 'immobridge'), 'callback' => [$this, 'getPropertyCity']],
            'immobridge_property_postal_code' => ['group' => 'immobridge_address', 'label' => __('PLZ', 'immobridge'), 'callback' => [$this, 'getPropertyPostalCode']],
            'immobridge_property_country' => ['group' => 'immobridge_address', 'label' => __('Land', 'immobridge'), 'callback' => [$this, 'getPropertyCountry']],

            // Merkmale
            'immobridge_property_rooms' => ['group' => 'immobridge_features', 'label' => __('Zimmer', 'immobridge'), 'callback' => [$this, 'getPropertyRooms']],
            'immobridge_property_bedrooms' => ['group' => 'immobridge_features', 'label' => __('Schlafzimmer', 'immobridge'), 'callback' => [$this, 'getPropertyBedrooms']],
            'immobridge_property_bathrooms' => ['group' => 'immobridge_features', 'label' => __('Badezimmer', 'immobridge'), 'callback' => [$this, 'getPropertyBathrooms']],
            'immobridge_property_type' => ['group' => 'immobridge_features', 'label' => __('Objekttyp', 'immobridge'), 'callback' => [$this, 'getPropertyType']],
            'immobridge_property_status' => ['group' => 'immobridge_features', 'label' => __('Status', 'immobridge'), 'callback' => [$this, 'getPropertyStatus']],
            'immobridge_property_features' => ['group' => 'immobridge_features', 'label' => __('Ausstattung', 'immobridge'), 'callback' => [$this, 'getPropertyFeatures']],
            'immobridge_property_equipment' => ['group' => 'immobridge_features', 'label' => __('Einrichtung', 'immobridge'), 'callback' => [$this, 'getPropertyEquipment']],

            // Kontakt
            'immobridge_property_contact_name' => ['group' => 'immobridge_contact', 'label' => __('Kontaktperson', 'immobridge'), 'callback' => [$this, 'getPropertyContactName']],
            'immobridge_property_contact_phone' => ['group' => 'immobridge_contact', 'label' => __('Telefon', 'immobridge'), 'callback' => [$this, 'getPropertyContactPhone']],
            'immobridge_property_contact_email' => ['group' => 'immobridge_contact', 'label' => __('E-Mail', 'immobridge'), 'callback' => [$this, 'getPropertyContactEmail']],

            // Energie
            'immobridge_property_energy_class' => ['group' => 'immobridge_energy', 'label' => __('Energieeffizienzklasse', 'immobridge'), 'callback' => [$this, 'getPropertyEnergyClass']],
            'immobridge_property_energy_consumption' => ['group' => 'immobridge_energy', 'label' => __('Energieverbrauch', 'immobridge'), 'callback' => [$this, 'getPropertyEnergyConsumption']],

            // Medien
            'immobridge_property_featured_image' => ['group' => 'immobridge_media', 'label' => __('Beitragsbild', 'immobridge'), 'callback' => [$this, 'getPropertyFeaturedImage']],
            'immobridge_property_gallery' => ['group' => 'immobridge_media', 'label' => __('Bildergalerie', 'immobridge'), 'callback' => [$this, 'getPropertyGallery']],
        ];

        foreach ($property_tags as $key => $tag) {
            $tags[$key] = [
                'label'    => $tag['label'],
                'group'    => $tag['group'],
                'provider' => 'immobridge',
                'callback' => $tag['callback'],
            ];
        }

        return $tags;
    }
    
    /**
     * Register post type support for Bricks Builder
     */
    private function registerPostTypeSupport(): void
    {
        // Enable Bricks Builder for property post type
        add_post_type_support('immo_property', 'bricks');
        
        // Add property post type to Bricks query loop options
        add_filter('bricks/query/post_types', function($post_types) {
            $post_types['immo_property'] = __('Properties', 'immobridge');
            return $post_types;
        });
    }
    
    /**
     * Add custom CSS classes for property elements
     */
    public function addPropertyElementClasses(array $classes, array $element): array
    {
        if (get_post_type() === 'immo_property') {
            $classes[] = 'immobridge-property-element';
            
            // Add specific classes based on element type
            if (isset($element['name'])) {
                $classes[] = 'immobridge-' . $element['name'];
            }
        }
        
        return $classes;
    }
    
    /**
     * Custom query for properties
     */
    public function customPropertyQuery($results, $query_vars)
    {
        if (isset($query_vars['post_type']) && $query_vars['post_type'] === 'immo_property') {
            // Add custom query modifications for properties
            $query_vars['meta_query'] = $query_vars['meta_query'] ?? [];
            
            // Only show published properties
            $query_vars['meta_query'][] = [
                'key' => '_immo_status',
                'value' => 'active',
                'compare' => '='
            ];
        }
        
        return $results;
    }
    
    /**
     * Validate property template before saving
     */
    public function validatePropertyTemplate($post_id): void
    {
        if (get_post_type($post_id) === 'immo_property') {
            // Add validation logic for property templates
            $this->validatePropertyFields($post_id);
        }
    }
    
    /**
     * Validate required property fields
     */
    private function validatePropertyFields(int $post_id): void
    {
        $required_fields = [
            'cf_title',
            'cf_price',
            'cf_living_area',
            'cf_address'
        ];
        
        foreach ($required_fields as $field) {
            $value = get_post_meta($post_id, $field, true);
            if (empty($value)) {
                add_action('admin_notices', function() use ($field) {
                    echo '<div class="notice notice-warning"><p>';
                    printf(__('Warning: Required property field %s is missing.', 'immobridge'), $field);
                    echo '</p></div>';
                });
            }
        }
    }
    
    // Dynamic Data callback methods
    
    public function getPropertyTitle($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_title', true) ?: get_the_title($post_id);
    }
    
    public function getPropertyDescription($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_description', true) ?: get_the_content();
    }
    
    public function getPropertyPrice($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_price', true) ?: '';
    }
    
    public function getPropertyPriceFormatted($post_id = null): string
    {
        $price = $this->getPropertyPrice($post_id);
        if (empty($price)) {
            return '';
        }
        
        return number_format((float)$price, 0, ',', '.') . ' €';
    }
    
    public function getPropertyRent($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_rent', true) ?: '';
    }
    
    public function getPropertyAdditionalCosts($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_additional_costs', true) ?: '';
    }
    
    public function getPropertyLivingArea($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        $area = get_post_meta($post_id, 'cf_living_area', true);
        return $area ? $area . ' m²' : '';
    }
    
    public function getPropertyRooms($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_rooms', true) ?: '';
    }
    
    public function getPropertyBedrooms($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_bedrooms', true) ?: '';
    }
    
    public function getPropertyBathrooms($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_bathrooms', true) ?: '';
    }
    
    public function getPropertyAddress($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_address', true) ?: '';
    }
    
    public function getPropertyCity($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_city', true) ?: '';
    }
    
    public function getPropertyPostalCode($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_zip_code', true) ?: '';
    }
    
    public function getPropertyCountry($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_country', true) ?: '';
    }
    
    public function getPropertyType($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_property_type', true) ?: '';
    }
    
    public function getPropertyStatus($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_status', true) ?: '';
    }
    
    public function getPropertyContactName($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_contact_name', true) ?: '';
    }
    
    public function getPropertyContactPhone($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_contact_phone', true) ?: '';
    }
    
    public function getPropertyContactEmail($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_contact_email', true) ?: '';
    }
    
    public function getPropertyFeaturedImage($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        $image_id = get_post_thumbnail_id($post_id);
        return $image_id ? wp_get_attachment_url($image_id) : '';
    }
    
    public function getPropertyGallery($post_id = null): array
    {
        $post_id = $post_id ?: get_the_ID();
        $gallery_ids = get_post_meta($post_id, 'cf_images', true);
        
        if (empty($gallery_ids)) {
            return [];
        }
        
        $gallery = [];
        foreach (explode(',', $gallery_ids) as $image_id) {
            $image_id = (int)trim($image_id);
            if ($image_id) {
                $gallery[] = [
                    'id' => $image_id,
                    'url' => wp_get_attachment_url($image_id),
                    'alt' => get_post_meta($image_id, '_wp_attachment_image_alt', true)
                ];
            }
        }
        
        return $gallery;
    }
    
    public function getPropertyEnergyClass($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_energy_class', true) ?: '';
    }
    
    public function getPropertyEnergyConsumption($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        $consumption = get_post_meta($post_id, 'cf_energy_consumption', true);
        return $consumption ? $consumption . ' kWh/(m²·a)' : '';
    }
    
    public function getPropertyFeatures($post_id = null): array
    {
        $post_id = $post_id ?: get_the_ID();
        $features = get_post_meta($post_id, 'cf_features', true);
        return $features ? explode(',', $features) : [];
    }
    
    public function getPropertyEquipment($post_id = null): array
    {
        $post_id = $post_id ?: get_the_ID();
        $equipment = get_post_meta($post_id, 'cf_equipment', true);
        return $equipment ? explode(',', $equipment) : [];
    }

    public function getOpenImmoId($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, 'cf_openimmo_id', true) ?: '';
    }

    public function getPropertyTotalArea($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        $area = get_post_meta($post_id, 'cf_total_area', true);
        return $area ? $area . ' m²' : '';
    }
}
