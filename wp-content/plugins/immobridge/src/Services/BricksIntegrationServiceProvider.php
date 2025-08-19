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
        // Basic property information
        $tags['immobridge_property_title'] = [
            'label' => __('Property Title', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyTitle']
        ];
        
        $tags['immobridge_property_description'] = [
            'label' => __('Property Description', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyDescription']
        ];
        
        // Price information
        $tags['immobridge_property_price'] = [
            'label' => __('Property Price', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyPrice']
        ];
        
        $tags['immobridge_property_price_formatted'] = [
            'label' => __('Property Price (Formatted)', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyPriceFormatted']
        ];
        
        $tags['immobridge_property_rent'] = [
            'label' => __('Monthly Rent', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyRent']
        ];
        
        $tags['immobridge_property_additional_costs'] = [
            'label' => __('Additional Costs', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyAdditionalCosts']
        ];
        
        // Property details
        $tags['immobridge_property_living_area'] = [
            'label' => __('Living Area', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyLivingArea']
        ];
        
        $tags['immobridge_property_rooms'] = [
            'label' => __('Number of Rooms', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyRooms']
        ];
        
        $tags['immobridge_property_bedrooms'] = [
            'label' => __('Number of Bedrooms', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyBedrooms']
        ];
        
        $tags['immobridge_property_bathrooms'] = [
            'label' => __('Number of Bathrooms', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyBathrooms']
        ];
        
        // Location information
        $tags['immobridge_property_address'] = [
            'label' => __('Property Address', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyAddress']
        ];
        
        $tags['immobridge_property_city'] = [
            'label' => __('City', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyCity']
        ];
        
        $tags['immobridge_property_postal_code'] = [
            'label' => __('Postal Code', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyPostalCode']
        ];
        
        $tags['immobridge_property_country'] = [
            'label' => __('Country', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyCountry']
        ];
        
        // Property type and status
        $tags['immobridge_property_type'] = [
            'label' => __('Property Type', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyType']
        ];
        
        $tags['immobridge_property_status'] = [
            'label' => __('Property Status', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyStatus']
        ];
        
        // Contact information
        $tags['immobridge_property_contact_name'] = [
            'label' => __('Contact Name', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyContactName']
        ];
        
        $tags['immobridge_property_contact_phone'] = [
            'label' => __('Contact Phone', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyContactPhone']
        ];
        
        $tags['immobridge_property_contact_email'] = [
            'label' => __('Contact Email', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyContactEmail']
        ];
        
        // Images and media
        $tags['immobridge_property_featured_image'] = [
            'label' => __('Featured Image', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyFeaturedImage']
        ];
        
        $tags['immobridge_property_gallery'] = [
            'label' => __('Property Gallery', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyGallery']
        ];
        
        // Energy efficiency
        $tags['immobridge_property_energy_class'] = [
            'label' => __('Energy Efficiency Class', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyEnergyClass']
        ];
        
        $tags['immobridge_property_energy_consumption'] = [
            'label' => __('Energy Consumption', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyEnergyConsumption']
        ];
        
        // Additional features
        $tags['immobridge_property_features'] = [
            'label' => __('Property Features', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyFeatures']
        ];
        
        $tags['immobridge_property_equipment'] = [
            'label' => __('Property Equipment', 'immobridge'),
            'group' => 'immobridge',
            'provider' => 'immobridge',
            'callback' => [$this, 'getPropertyEquipment']
        ];
        
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
            '_immo_title',
            '_immo_price',
            '_immo_living_area',
            '_immo_address'
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
        return get_post_meta($post_id, '_immo_title', true) ?: get_the_title($post_id);
    }
    
    public function getPropertyDescription($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_description', true) ?: get_the_content();
    }
    
    public function getPropertyPrice($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_price', true) ?: '';
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
        return get_post_meta($post_id, '_immo_rent', true) ?: '';
    }
    
    public function getPropertyAdditionalCosts($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_additional_costs', true) ?: '';
    }
    
    public function getPropertyLivingArea($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        $area = get_post_meta($post_id, '_immo_living_area', true);
        return $area ? $area . ' m²' : '';
    }
    
    public function getPropertyRooms($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_rooms', true) ?: '';
    }
    
    public function getPropertyBedrooms($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_bedrooms', true) ?: '';
    }
    
    public function getPropertyBathrooms($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_bathrooms', true) ?: '';
    }
    
    public function getPropertyAddress($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_address', true) ?: '';
    }
    
    public function getPropertyCity($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_city', true) ?: '';
    }
    
    public function getPropertyPostalCode($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_postal_code', true) ?: '';
    }
    
    public function getPropertyCountry($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_country', true) ?: '';
    }
    
    public function getPropertyType($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_type', true) ?: '';
    }
    
    public function getPropertyStatus($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_status', true) ?: '';
    }
    
    public function getPropertyContactName($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_contact_name', true) ?: '';
    }
    
    public function getPropertyContactPhone($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_contact_phone', true) ?: '';
    }
    
    public function getPropertyContactEmail($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        return get_post_meta($post_id, '_immo_contact_email', true) ?: '';
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
        $gallery_ids = get_post_meta($post_id, '_immo_gallery', true);
        
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
        return get_post_meta($post_id, '_immo_energy_class', true) ?: '';
    }
    
    public function getPropertyEnergyConsumption($post_id = null): string
    {
        $post_id = $post_id ?: get_the_ID();
        $consumption = get_post_meta($post_id, '_immo_energy_consumption', true);
        return $consumption ? $consumption . ' kWh/(m²·a)' : '';
    }
    
    public function getPropertyFeatures($post_id = null): array
    {
        $post_id = $post_id ?: get_the_ID();
        $features = get_post_meta($post_id, '_immo_features', true);
        return $features ? explode(',', $features) : [];
    }
    
    public function getPropertyEquipment($post_id = null): array
    {
        $post_id = $post_id ?: get_the_ID();
        $equipment = get_post_meta($post_id, '_immo_equipment', true);
        return $equipment ? explode(',', $equipment) : [];
    }
}
