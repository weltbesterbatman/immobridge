<?php
/**
 * Property Taxonomy Service
 *
 * @package ImmoBridge
 * @subpackage Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Services;

use ImmoBridge\Entities\Enums\PropertyType;
use ImmoBridge\Entities\Enums\PropertyStatus;

/**
 * Property Taxonomy Service
 *
 * Handles registration and management of property-related taxonomies.
 *
 * @since 1.0.0
 */
final class PropertyTaxonomyService
{
    private const POST_TYPE = 'property';

    /**
     * Register all property taxonomies
     */
    public function register(): void
    {
        $this->registerPropertyTypeTaxonomy();
        $this->registerPropertyStatusTaxonomy();
        $this->registerPropertyLocationTaxonomy();
        $this->registerPropertyFeaturesTaxonomy();
    }

    /**
     * Register property type taxonomy
     */
    private function registerPropertyTypeTaxonomy(): void
    {
        $labels = [
            'name' => __('Property Types', 'immobridge'),
            'singular_name' => __('Property Type', 'immobridge'),
            'search_items' => __('Search Property Types', 'immobridge'),
            'all_items' => __('All Property Types', 'immobridge'),
            'parent_item' => __('Parent Property Type', 'immobridge'),
            'parent_item_colon' => __('Parent Property Type:', 'immobridge'),
            'edit_item' => __('Edit Property Type', 'immobridge'),
            'update_item' => __('Update Property Type', 'immobridge'),
            'add_new_item' => __('Add New Property Type', 'immobridge'),
            'new_item_name' => __('New Property Type Name', 'immobridge'),
            'menu_name' => __('Property Types', 'immobridge'),
        ];

        $args = [
            'labels' => $labels,
            'description' => __('Property type classification', 'immobridge'),
            'public' => true,
            'publicly_queryable' => true,
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true,
            'rest_base' => 'property-types',
            'show_tagcloud' => true,
            'show_in_quick_edit' => true,
            'show_admin_column' => true,
            'rewrite' => [
                'slug' => 'property-type',
                'with_front' => false,
                'hierarchical' => true,
            ],
            'query_var' => true,
            'capabilities' => [
                'manage_terms' => 'manage_property_types',
                'edit_terms' => 'edit_property_types',
                'delete_terms' => 'delete_property_types',
                'assign_terms' => 'assign_property_types',
            ],
        ];

        register_taxonomy('property_type', self::POST_TYPE, $args);
    }

    /**
     * Register property status taxonomy
     */
    private function registerPropertyStatusTaxonomy(): void
    {
        $labels = [
            'name' => __('Property Status', 'immobridge'),
            'singular_name' => __('Property Status', 'immobridge'),
            'search_items' => __('Search Property Status', 'immobridge'),
            'all_items' => __('All Property Status', 'immobridge'),
            'edit_item' => __('Edit Property Status', 'immobridge'),
            'update_item' => __('Update Property Status', 'immobridge'),
            'add_new_item' => __('Add New Property Status', 'immobridge'),
            'new_item_name' => __('New Property Status Name', 'immobridge'),
            'menu_name' => __('Property Status', 'immobridge'),
        ];

        $args = [
            'labels' => $labels,
            'description' => __('Property availability status', 'immobridge'),
            'public' => true,
            'publicly_queryable' => true,
            'hierarchical' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true,
            'rest_base' => 'property-status',
            'show_tagcloud' => true,
            'show_in_quick_edit' => true,
            'show_admin_column' => true,
            'rewrite' => [
                'slug' => 'property-status',
                'with_front' => false,
            ],
            'query_var' => true,
            'capabilities' => [
                'manage_terms' => 'manage_property_status',
                'edit_terms' => 'edit_property_status',
                'delete_terms' => 'delete_property_status',
                'assign_terms' => 'assign_property_status',
            ],
        ];

        register_taxonomy('property_status', self::POST_TYPE, $args);
    }

    /**
     * Register property location taxonomy
     */
    private function registerPropertyLocationTaxonomy(): void
    {
        $labels = [
            'name' => __('Locations', 'immobridge'),
            'singular_name' => __('Location', 'immobridge'),
            'search_items' => __('Search Locations', 'immobridge'),
            'all_items' => __('All Locations', 'immobridge'),
            'parent_item' => __('Parent Location', 'immobridge'),
            'parent_item_colon' => __('Parent Location:', 'immobridge'),
            'edit_item' => __('Edit Location', 'immobridge'),
            'update_item' => __('Update Location', 'immobridge'),
            'add_new_item' => __('Add New Location', 'immobridge'),
            'new_item_name' => __('New Location Name', 'immobridge'),
            'menu_name' => __('Locations', 'immobridge'),
        ];

        $args = [
            'labels' => $labels,
            'description' => __('Property location classification', 'immobridge'),
            'public' => true,
            'publicly_queryable' => true,
            'hierarchical' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true,
            'rest_base' => 'property-locations',
            'show_tagcloud' => true,
            'show_in_quick_edit' => true,
            'show_admin_column' => true,
            'rewrite' => [
                'slug' => 'location',
                'with_front' => false,
                'hierarchical' => true,
            ],
            'query_var' => true,
            'capabilities' => [
                'manage_terms' => 'manage_property_locations',
                'edit_terms' => 'edit_property_locations',
                'delete_terms' => 'delete_property_locations',
                'assign_terms' => 'assign_property_locations',
            ],
        ];

        register_taxonomy('property_location', self::POST_TYPE, $args);
    }

    /**
     * Register property features taxonomy
     */
    private function registerPropertyFeaturesTaxonomy(): void
    {
        $labels = [
            'name' => __('Property Features', 'immobridge'),
            'singular_name' => __('Property Feature', 'immobridge'),
            'search_items' => __('Search Property Features', 'immobridge'),
            'all_items' => __('All Property Features', 'immobridge'),
            'edit_item' => __('Edit Property Feature', 'immobridge'),
            'update_item' => __('Update Property Feature', 'immobridge'),
            'add_new_item' => __('Add New Property Feature', 'immobridge'),
            'new_item_name' => __('New Property Feature Name', 'immobridge'),
            'menu_name' => __('Features', 'immobridge'),
            'popular_items' => __('Popular Features', 'immobridge'),
            'separate_items_with_commas' => __('Separate features with commas', 'immobridge'),
            'add_or_remove_items' => __('Add or remove features', 'immobridge'),
            'choose_from_most_used' => __('Choose from the most used features', 'immobridge'),
            'not_found' => __('No features found.', 'immobridge'),
        ];

        $args = [
            'labels' => $labels,
            'description' => __('Property features and amenities', 'immobridge'),
            'public' => true,
            'publicly_queryable' => true,
            'hierarchical' => false,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_rest' => true,
            'rest_base' => 'property-features',
            'show_tagcloud' => true,
            'show_in_quick_edit' => true,
            'show_admin_column' => true,
            'rewrite' => [
                'slug' => 'property-feature',
                'with_front' => false,
            ],
            'query_var' => true,
            'capabilities' => [
                'manage_terms' => 'manage_property_features',
                'edit_terms' => 'edit_property_features',
                'delete_terms' => 'delete_property_features',
                'assign_terms' => 'assign_property_features',
            ],
        ];

        register_taxonomy('property_features', self::POST_TYPE, $args);
    }

    /**
     * Create default taxonomy terms
     */
    public function createDefaultTerms(): void
    {
        // Create default property type terms
        foreach (PropertyType::cases() as $type) {
            if (!term_exists($type->value, 'property_type')) {
                wp_insert_term($type->getLabel(), 'property_type', [
                    'slug' => $type->value,
                    'description' => sprintf(__('%s properties', 'immobridge'), $type->getLabel()),
                ]);
            }
        }

        // Create default property status terms
        foreach (PropertyStatus::cases() as $status) {
            if (!term_exists($status->value, 'property_status')) {
                wp_insert_term($status->getLabel(), 'property_status', [
                    'slug' => $status->value,
                    'description' => sprintf(__('Properties with %s status', 'immobridge'), $status->getLabel()),
                ]);
            }
        }

        // Create default feature terms
        $defaultFeatures = [
            'balcony' => __('Balcony', 'immobridge'),
            'terrace' => __('Terrace', 'immobridge'),
            'garden' => __('Garden', 'immobridge'),
            'parking' => __('Parking', 'immobridge'),
            'garage' => __('Garage', 'immobridge'),
            'elevator' => __('Elevator', 'immobridge'),
            'basement' => __('Basement', 'immobridge'),
            'attic' => __('Attic', 'immobridge'),
            'fireplace' => __('Fireplace', 'immobridge'),
            'pool' => __('Pool', 'immobridge'),
            'sauna' => __('Sauna', 'immobridge'),
            'air_conditioning' => __('Air Conditioning', 'immobridge'),
            'heating' => __('Heating', 'immobridge'),
            'furnished' => __('Furnished', 'immobridge'),
            'pets_allowed' => __('Pets Allowed', 'immobridge'),
        ];

        foreach ($defaultFeatures as $slug => $name) {
            if (!term_exists($slug, 'property_features')) {
                wp_insert_term($name, 'property_features', [
                    'slug' => $slug,
                ]);
            }
        }
    }
}
