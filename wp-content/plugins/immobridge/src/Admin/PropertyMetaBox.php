<?php
/**
 * Property Meta Box
 *
 * @package ImmoBridge
 * @subpackage Admin
 * @since 1.1.0
 */

declare(strict_types=1);

namespace ImmoBridge\Admin;

class PropertyMetaBox
{
    private const POST_TYPE = 'property';

    /**
     * Add meta box to property post type
     */
    public function add(): void
    {
        add_meta_box(
            'immobridge_property_details',
            __('Property Details', 'immobridge'),
            [$this, 'render'],
            self::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render the meta box content
     */
    public function render(\WP_Post $post): void
    {
        // Security nonce
        wp_nonce_field('immobridge_save_property_meta', 'immobridge_meta_nonce');

        $meta = get_post_meta($post->ID);
        $fields = $this->get_meta_fields();

        echo '<table class="form-table"><tbody>';

        foreach ($fields as $group => $group_data) {
            echo '<tr><th colspan="2"><strong>' . esc_html($group_data['label']) . '</strong></th></tr>';
            foreach ($group_data['fields'] as $key => $label) {
                $value = $meta[$key][0] ?? '';
                echo '<tr>';
                echo '<th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th>';
                echo '<td><input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="widefat"></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    /**
     * Save meta box data
     */
    public function save(int $post_id): void
    {
        if (!isset($_POST['immobridge_meta_nonce']) || !wp_verify_nonce($_POST['immobridge_meta_nonce'], 'immobridge_save_property_meta')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = $this->get_meta_fields();
        foreach ($fields as $group_data) {
            foreach ($group_data['fields'] as $key => $label) {
                if (isset($_POST[$key])) {
                    update_post_meta($post_id, $key, sanitize_text_field($_POST[$key]));
                }
            }
        }
    }

    /**
     * Get the structure of meta fields to display
     */
    private function get_meta_fields(): array
    {
        return [
            'location' => [
                'label' => __('Location', 'immobridge'),
                'fields' => [
                    'cf_address' => __('Address', 'immobridge'),
                    'cf_house_number' => __('House Number', 'immobridge'),
                    'cf_zip_code' => __('ZIP Code', 'immobridge'),
                    'cf_city' => __('City', 'immobridge'),
                    'cf_state' => __('State', 'immobridge'),
                    'cf_country' => __('Country', 'immobridge'),
                ],
            ],
            'prices' => [
                'label' => __('Prices', 'immobridge'),
                'fields' => [
                    'cf_purchase_price' => __('Purchase Price', 'immobridge'),
                    'cf_rent_cold' => __('Cold Rent', 'immobridge'),
                    'cf_rent_warm' => __('Warm Rent', 'immobridge'),
                    'cf_deposit' => __('Deposit', 'immobridge'),
                    'cf_commission_buyer' => __('Buyer Commission', 'immobridge'),
                ],
            ],
            'areas' => [
                'label' => __('Areas', 'immobridge'),
                'fields' => [
                    'cf_living_area' => __('Living Area', 'immobridge'),
                    'cf_total_area' => __('Total Area', 'immobridge'),
                    'cf_plot_area' => __('Plot Area', 'immobridge'),
                    'cf_rooms' => __('Rooms', 'immobridge'),
                    'cf_bedrooms' => __('Bedrooms', 'immobridge'),
                    'cf_bathrooms' => __('Bathrooms', 'immobridge'),
                ],
            ],
            'condition' => [
                'label' => __('Condition & Energy', 'immobridge'),
                'fields' => [
                    'cf_condition' => __('Condition', 'immobridge'),
                    'cf_year_built' => __('Year Built', 'immobridge'),
                    'cf_last_renovation' => __('Last Renovation', 'immobridge'),
                    'cf_energy_certificate_type' => __('Energy Certificate Type', 'immobridge'),
                    'cf_energy_consumption' => __('Energy Consumption', 'immobridge'),
                    'cf_energy_efficiency_class' => __('Energy Efficiency Class', 'immobridge'),
                ],
            ],
        ];
    }
}
