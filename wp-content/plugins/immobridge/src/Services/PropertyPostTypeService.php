<?php
/**
 * Property Post Type Service
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
 * Property Post Type Service
 *
 * Handles registration and management of the property custom post type.
 *
 * @since 1.0.0
 */
final class PropertyPostTypeService
{
    private const POST_TYPE = 'property';

    /**
     * Get all meta fields for properties
     *
     * @return array<string, array{type: string, description: string}>
     */
    private function getMetaFields(): array
    {
        return [
            'type' => [
                'type' => 'string',
                'description' => __('Property type (apartment, house, etc.)', 'immobridge')
            ],
            'status' => [
                'type' => 'string',
                'description' => __('Property status (available, sold, etc.)', 'immobridge')
            ],
            'price' => [
                'type' => 'number',
                'description' => __('Property price', 'immobridge')
            ],
            'price_type' => [
                'type' => 'string',
                'description' => __('Price type (sale or rent)', 'immobridge')
            ],
            'living_area' => [
                'type' => 'number',
                'description' => __('Living area in square meters', 'immobridge')
            ],
            'total_area' => [
                'type' => 'number',
                'description' => __('Total area in square meters', 'immobridge')
            ],
            'rooms' => [
                'type' => 'integer',
                'description' => __('Number of rooms', 'immobridge')
            ],
            'bedrooms' => [
                'type' => 'integer',
                'description' => __('Number of bedrooms', 'immobridge')
            ],
            'bathrooms' => [
                'type' => 'integer',
                'description' => __('Number of bathrooms', 'immobridge')
            ],
            'address' => [
                'type' => 'string',
                'description' => __('Property address', 'immobridge')
            ],
            'city' => [
                'type' => 'string',
                'description' => __('City', 'immobridge')
            ],
            'zip_code' => [
                'type' => 'string',
                'description' => __('ZIP code', 'immobridge')
            ],
            'country' => [
                'type' => 'string',
                'description' => __('Country', 'immobridge')
            ],
            'latitude' => [
                'type' => 'number',
                'description' => __('Latitude coordinate', 'immobridge')
            ],
            'longitude' => [
                'type' => 'number',
                'description' => __('Longitude coordinate', 'immobridge')
            ],
            'openimmo_id' => [
                'type' => 'string',
                'description' => __('OpenImmo ID', 'immobridge')
            ],
            'imported_at' => [
                'type' => 'string',
                'description' => __('Import timestamp', 'immobridge')
            ],
            'images' => [
                'type' => 'string',
                'description' => __('Property images (JSON)', 'immobridge')
            ],
            'documents' => [
                'type' => 'string',
                'description' => __('Property documents (JSON)', 'immobridge')
            ],
        ];
    }

    /**
     * Register meta fields for REST API and Bricks Builder visibility
     */
    public function registerMetaFields(): void
    {
        foreach ($this->getMetaFields() as $field => $config) {
            register_post_meta(self::POST_TYPE, $field, [
                'show_in_rest' => true,
                'single' => true,
                'type' => $config['type'],
                'description' => $config['description'],
                'auth_callback' => function() {
                    return current_user_can('edit_posts');
                },
                'sanitize_callback' => function($value) use ($config) {
                    return match($config['type']) {
                        'integer' => (int) $value,
                        'number' => (float) $value,
                        default => sanitize_text_field($value)
                    };
                }
            ]);
        }
    }

    /**
     * Register the property custom post type
     */
    public function register(): void
    {
        $labels = [
            'name' => __('Properties', 'immobridge'),
            'singular_name' => __('Property', 'immobridge'),
            'menu_name' => __('Properties', 'immobridge'),
            'name_admin_bar' => __('Property', 'immobridge'),
            'add_new' => __('Add New', 'immobridge'),
            'add_new_item' => __('Add New Property', 'immobridge'),
            'new_item' => __('New Property', 'immobridge'),
            'edit_item' => __('Edit Property', 'immobridge'),
            'view_item' => __('View Property', 'immobridge'),
            'all_items' => __('All Properties', 'immobridge'),
            'search_items' => __('Search Properties', 'immobridge'),
            'parent_item_colon' => __('Parent Properties:', 'immobridge'),
            'not_found' => __('No properties found.', 'immobridge'),
            'not_found_in_trash' => __('No properties found in Trash.', 'immobridge'),
            'featured_image' => __('Property Image', 'immobridge'),
            'set_featured_image' => __('Set property image', 'immobridge'),
            'remove_featured_image' => __('Remove property image', 'immobridge'),
            'use_featured_image' => __('Use as property image', 'immobridge'),
            'archives' => __('Property Archives', 'immobridge'),
            'insert_into_item' => __('Insert into property', 'immobridge'),
            'uploaded_to_this_item' => __('Uploaded to this property', 'immobridge'),
            'filter_items_list' => __('Filter properties list', 'immobridge'),
            'items_list_navigation' => __('Properties list navigation', 'immobridge'),
            'items_list' => __('Properties list', 'immobridge'),
        ];

        $args = [
            'labels' => $labels,
            'description' => __('Real estate properties managed by ImmoBridge', 'immobridge'),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => 'immobridge',
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'show_in_rest' => true,
            'rest_base' => 'properties',
            'rest_controller_class' => 'WP_REST_Posts_Controller',
            'menu_position' => 20,
            'menu_icon' => 'dashicons-building',
            'capability_type' => 'post',
            'map_meta_cap' => true,
            'hierarchical' => false,
            'supports' => [
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'custom-fields',
                'revisions',
                'page-attributes',
            ],
            'has_archive' => true,
            'rewrite' => [
                'slug' => 'properties',
                'with_front' => false,
                'pages' => true,
                'feeds' => true,
            ],
            'query_var' => true,
            'can_export' => true,
            'delete_with_user' => false,
            'template' => [
                ['core/group', [
                    'layout' => ['type' => 'constrained']
                ], [
                    ['core/heading', [
                        'level' => 1,
                        'placeholder' => __('Property Title', 'immobridge')
                    ]],
                    ['core/paragraph', [
                        'placeholder' => __('Property description...', 'immobridge')
                    ]],
                ]]
            ],
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Add meta boxes for property data
     */
    public function addMetaBoxes(): void
    {
        // Property Details Meta Box
        add_meta_box(
            'immobridge_property_details',
            __('Property Details', 'immobridge'),
            [$this, 'renderPropertyDetailsMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        // Location Meta Box
        add_meta_box(
            'immobridge_property_location',
            __('Location', 'immobridge'),
            [$this, 'renderLocationMetaBox'],
            self::POST_TYPE,
            'normal',
            'high'
        );

        // Images & Documents Meta Box
        add_meta_box(
            'immobridge_property_media',
            __('Images & Documents', 'immobridge'),
            [$this, 'renderMediaMetaBox'],
            self::POST_TYPE,
            'normal',
            'default'
        );

        // OpenImmo Data Meta Box
        add_meta_box(
            'immobridge_openimmo_data',
            __('OpenImmo Data', 'immobridge'),
            [$this, 'renderOpenImmoMetaBox'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render property details meta box
     *
     * @param \WP_Post $post Current post object
     */
    public function renderPropertyDetailsMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('immobridge_property_details', 'immobridge_property_details_nonce');

        $type = get_post_meta($post->ID, 'type', true);
        $status = get_post_meta($post->ID, 'status', true);
        $price = get_post_meta($post->ID, 'price', true);
        $priceType = get_post_meta($post->ID, 'price_type', true);
        $livingArea = get_post_meta($post->ID, 'living_area', true);
        $totalArea = get_post_meta($post->ID, 'total_area', true);
        $rooms = get_post_meta($post->ID, 'rooms', true);
        $bedrooms = get_post_meta($post->ID, 'bedrooms', true);
        $bathrooms = get_post_meta($post->ID, 'bathrooms', true);

        echo '<table class="form-table">';
        
        // Property Type
        echo '<tr>';
        echo '<th><label for="immobridge_type">' . __('Property Type', 'immobridge') . '</label></th>';
        echo '<td>';
        echo '<select id="immobridge_type" name="immobridge_type" class="regular-text">';
        echo '<option value="">' . __('Select Type', 'immobridge') . '</option>';
        foreach (PropertyType::getAll() as $value => $label) {
            $selected = selected($type, $value, false);
            echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        // Property Status
        echo '<tr>';
        echo '<th><label for="immobridge_status">' . __('Status', 'immobridge') . '</label></th>';
        echo '<td>';
        echo '<select id="immobridge_status" name="immobridge_status" class="regular-text">';
        echo '<option value="">' . __('Select Status', 'immobridge') . '</option>';
        foreach (PropertyStatus::getAll() as $value => $label) {
            $selected = selected($status, $value, false);
            echo "<option value=\"{$value}\" {$selected}>{$label}</option>";
        }
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        // Price
        echo '<tr>';
        echo '<th><label for="immobridge_price">' . __('Price', 'immobridge') . '</label></th>';
        echo '<td>';
        echo '<input type="number" id="immobridge_price" name="immobridge_price" value="' . esc_attr($price) . '" class="regular-text" step="0.01" />';
        echo '<select id="immobridge_price_type" name="immobridge_price_type" style="margin-left: 10px;">';
        echo '<option value="sale"' . selected($priceType, 'sale', false) . '>' . __('Sale', 'immobridge') . '</option>';
        echo '<option value="rent"' . selected($priceType, 'rent', false) . '>' . __('Rent', 'immobridge') . '</option>';
        echo '</select>';
        echo '</td>';
        echo '</tr>';

        // Living Area
        echo '<tr>';
        echo '<th><label for="immobridge_living_area">' . __('Living Area (m²)', 'immobridge') . '</label></th>';
        echo '<td><input type="number" id="immobridge_living_area" name="immobridge_living_area" value="' . esc_attr($livingArea) . '" class="regular-text" step="0.01" /></td>';
        echo '</tr>';

        // Total Area
        echo '<tr>';
        echo '<th><label for="immobridge_total_area">' . __('Total Area (m²)', 'immobridge') . '</label></th>';
        echo '<td><input type="number" id="immobridge_total_area" name="immobridge_total_area" value="' . esc_attr($totalArea) . '" class="regular-text" step="0.01" /></td>';
        echo '</tr>';

        // Rooms
        echo '<tr>';
        echo '<th><label for="immobridge_rooms">' . __('Rooms', 'immobridge') . '</label></th>';
        echo '<td><input type="number" id="immobridge_rooms" name="immobridge_rooms" value="' . esc_attr($rooms) . '" class="small-text" min="0" /></td>';
        echo '</tr>';

        // Bedrooms
        echo '<tr>';
        echo '<th><label for="immobridge_bedrooms">' . __('Bedrooms', 'immobridge') . '</label></th>';
        echo '<td><input type="number" id="immobridge_bedrooms" name="immobridge_bedrooms" value="' . esc_attr($bedrooms) . '" class="small-text" min="0" /></td>';
        echo '</tr>';

        // Bathrooms
        echo '<tr>';
        echo '<th><label for="immobridge_bathrooms">' . __('Bathrooms', 'immobridge') . '</label></th>';
        echo '<td><input type="number" id="immobridge_bathrooms" name="immobridge_bathrooms" value="' . esc_attr($bathrooms) . '" class="small-text" min="0" /></td>';
        echo '</tr>';

        echo '</table>';
    }

    /**
     * Render location meta box
     *
     * @param \WP_Post $post Current post object
     */
    public function renderLocationMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('immobridge_property_location', 'immobridge_property_location_nonce');

        $address = get_post_meta($post->ID, 'address', true);
        $city = get_post_meta($post->ID, 'city', true);
        $zipCode = get_post_meta($post->ID, 'zip_code', true);
        $country = get_post_meta($post->ID, 'country', true);
        $latitude = get_post_meta($post->ID, 'latitude', true);
        $longitude = get_post_meta($post->ID, 'longitude', true);

        echo '<table class="form-table">';
        
        // Address
        echo '<tr>';
        echo '<th><label for="immobridge_address">' . __('Address', 'immobridge') . '</label></th>';
        echo '<td><input type="text" id="immobridge_address" name="immobridge_address" value="' . esc_attr($address) . '" class="large-text" /></td>';
        echo '</tr>';

        // City
        echo '<tr>';
        echo '<th><label for="immobridge_city">' . __('City', 'immobridge') . '</label></th>';
        echo '<td><input type="text" id="immobridge_city" name="immobridge_city" value="' . esc_attr($city) . '" class="regular-text" /></td>';
        echo '</tr>';

        // ZIP Code
        echo '<tr>';
        echo '<th><label for="immobridge_zip_code">' . __('ZIP Code', 'immobridge') . '</label></th>';
        echo '<td><input type="text" id="immobridge_zip_code" name="immobridge_zip_code" value="' . esc_attr($zipCode) . '" class="regular-text" /></td>';
        echo '</tr>';

        // Country
        echo '<tr>';
        echo '<th><label for="immobridge_country">' . __('Country', 'immobridge') . '</label></th>';
        echo '<td><input type="text" id="immobridge_country" name="immobridge_country" value="' . esc_attr($country) . '" class="regular-text" /></td>';
        echo '</tr>';

        // Coordinates
        echo '<tr>';
        echo '<th><label for="immobridge_latitude">' . __('Coordinates', 'immobridge') . '</label></th>';
        echo '<td>';
        echo '<input type="number" id="immobridge_latitude" name="immobridge_latitude" value="' . esc_attr($latitude) . '" class="regular-text" step="any" placeholder="' . __('Latitude', 'immobridge') . '" />';
        echo '<input type="number" id="immobridge_longitude" name="immobridge_longitude" value="' . esc_attr($longitude) . '" class="regular-text" step="any" placeholder="' . __('Longitude', 'immobridge') . '" style="margin-left: 10px;" />';
        echo '</td>';
        echo '</tr>';

        echo '</table>';
    }

    /**
     * Render media meta box
     *
     * @param \WP_Post $post Current post object
     */
    public function renderMediaMetaBox(\WP_Post $post): void
    {
        wp_nonce_field('immobridge_property_media', 'immobridge_property_media_nonce');

        $images = get_post_meta($post->ID, 'images', true);
        $documents = get_post_meta($post->ID, 'documents', true);

        $images = $images ? json_decode($images, true) : [];
        $documents = $documents ? json_decode($documents, true) : [];

        echo '<div class="immobridge-media-section">';
        
        // Images section
        echo '<h4>' . __('Images', 'immobridge') . '</h4>';
        echo '<div id="immobridge-images-container">';
        foreach ($images as $index => $image) {
            echo '<div class="immobridge-image-item">';
            echo '<input type="url" name="immobridge_images[]" value="' . esc_attr($image) . '" class="large-text" placeholder="' . __('Image URL', 'immobridge') . '" />';
            echo '<button type="button" class="button remove-image">' . __('Remove', 'immobridge') . '</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '<button type="button" id="add-image" class="button">' . __('Add Image', 'immobridge') . '</button>';

        echo '<hr style="margin: 20px 0;" />';

        // Documents section
        echo '<h4>' . __('Documents', 'immobridge') . '</h4>';
        echo '<div id="immobridge-documents-container">';
        foreach ($documents as $index => $document) {
            echo '<div class="immobridge-document-item">';
            echo '<input type="url" name="immobridge_documents[]" value="' . esc_attr($document) . '" class="large-text" placeholder="' . __('Document URL', 'immobridge') . '" />';
            echo '<button type="button" class="button remove-document">' . __('Remove', 'immobridge') . '</button>';
            echo '</div>';
        }
        echo '</div>';
        echo '<button type="button" id="add-document" class="button">' . __('Add Document', 'immobridge') . '</button>';

        echo '</div>';

        // Add JavaScript for dynamic fields
        echo '<script>
        jQuery(document).ready(function($) {
            $("#add-image").click(function() {
                $("#immobridge-images-container").append(
                    \'<div class="immobridge-image-item">\' +
                    \'<input type="url" name="immobridge_images[]" value="" class="large-text" placeholder="' . __('Image URL', 'immobridge') . '" />\' +
                    \'<button type="button" class="button remove-image">' . __('Remove', 'immobridge') . '</button>\' +
                    \'</div>\'
                );
            });

            $(document).on("click", ".remove-image", function() {
                $(this).parent().remove();
            });

            $("#add-document").click(function() {
                $("#immobridge-documents-container").append(
                    \'<div class="immobridge-document-item">\' +
                    \'<input type="url" name="immobridge_documents[]" value="" class="large-text" placeholder="' . __('Document URL', 'immobridge') . '" />\' +
                    \'<button type="button" class="button remove-document">' . __('Remove', 'immobridge') . '</button>\' +
                    \'</div>\'
                );
            });

            $(document).on("click", ".remove-document", function() {
                $(this).parent().remove();
            });
        });
        </script>';

        echo '<style>
        .immobridge-image-item, .immobridge-document-item {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .immobridge-media-section h4 {
            margin-top: 0;
        }
        </style>';
    }

    /**
     * Render OpenImmo data meta box
     *
     * @param \WP_Post $post Current post object
     */
    public function renderOpenImmoMetaBox(\WP_Post $post): void
    {
        $openImmoId = get_post_meta($post->ID, 'openimmo_id', true);
        $importedAt = get_post_meta($post->ID, 'imported_at', true);

        echo '<table class="form-table">';
        
        // OpenImmo ID
        echo '<tr>';
        echo '<th><label for="immobridge_openimmo_id">' . __('OpenImmo ID', 'immobridge') . '</label></th>';
        echo '<td><input type="text" id="immobridge_openimmo_id" name="immobridge_openimmo_id" value="' . esc_attr($openImmoId) . '" class="regular-text" readonly /></td>';
        echo '</tr>';

        // Import Date
        if ($importedAt) {
            echo '<tr>';
            echo '<th>' . __('Imported At', 'immobridge') . '</th>';
            echo '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($importedAt))) . '</td>';
            echo '</tr>';
        }

        echo '</table>';
    }

    /**
     * Save meta box data
     *
     * @param int $postId Post ID
     */
    public function saveMetaBoxes(int $postId): void
    {
        // Verify nonces
        if (!wp_verify_nonce($_POST['immobridge_property_details_nonce'] ?? '', 'immobridge_property_details')) {
            return;
        }

        if (!wp_verify_nonce($_POST['immobridge_property_location_nonce'] ?? '', 'immobridge_property_location')) {
            return;
        }

        if (!wp_verify_nonce($_POST['immobridge_property_media_nonce'] ?? '', 'immobridge_property_media')) {
            return;
        }

        // Check permissions
        if (!current_user_can('edit_post', $postId)) {
            return;
        }

        // Save property details
        $fields = [
            'immobridge_type' => 'type',
            'immobridge_status' => 'status',
            'immobridge_price' => 'price',
            'immobridge_price_type' => 'price_type',
            'immobridge_living_area' => 'living_area',
            'immobridge_total_area' => 'total_area',
            'immobridge_rooms' => 'rooms',
            'immobridge_bedrooms' => 'bedrooms',
            'immobridge_bathrooms' => 'bathrooms',
            'immobridge_address' => 'address',
            'immobridge_city' => 'city',
            'immobridge_zip_code' => 'zip_code',
            'immobridge_country' => 'country',
            'immobridge_latitude' => 'latitude',
            'immobridge_longitude' => 'longitude',
            'immobridge_openimmo_id' => 'openimmo_id',
        ];

        foreach ($fields as $field => $metaKey) {
            if (isset($_POST[$field])) {
                $value = sanitize_text_field($_POST[$field]);
                update_post_meta($postId, $metaKey, $value);
            }
        }

        // Save images
        if (isset($_POST['immobridge_images'])) {
            $images = array_filter(array_map('esc_url_raw', $_POST['immobridge_images']));
            update_post_meta($postId, 'images', json_encode($images));
        }

        // Save documents
        if (isset($_POST['immobridge_documents'])) {
            $documents = array_filter(array_map('esc_url_raw', $_POST['immobridge_documents']));
            update_post_meta($postId, 'documents', json_encode($documents));
        }
    }
}
