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

use ImmoBridge\Services\MappingService;

class PropertyMetaBox
{
    private const POST_TYPE = 'property';
    private ?MappingService $mappingService = null;

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
                echo '<td><input type="text" id="' . esc_attr($key) . '" name="' . esc_attr($key) . '" value="' . esc_attr($value) . '" class="widefat" readonly></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '<p class="description">' . __('Diese Felder werden automatisch aus den OpenImmo-Importdaten befüllt.', 'immobridge') . '</p>';
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
     * Dynamically loads fields from the mapping file
     */
    private function get_meta_fields(): array
    {
        if ($this->mappingService === null) {
            $this->mappingService = new MappingService();
        }

        if ($this->mappingService->hasError()) {
            // Fallback to hardcoded fields if mapping service fails
            return $this->get_fallback_meta_fields();
        }

        $mappings = $this->mappingService->getMappings();
        $groupedFields = [];

        foreach ($mappings as $mapping) {
            if ($mapping['type'] !== 'custom_field' || empty($mapping['destination'])) {
                continue;
            }

            $group = $mapping['group'] ?? 'Allgemein';
            $key = $mapping['destination'];
            $label = !empty($mapping['title de']) ? $mapping['title de'] : (!empty($mapping['title']) ? $mapping['title'] : ucfirst(str_replace('_', ' ', $key)));

            if (!isset($groupedFields[$group])) {
                $groupedFields[$group] = [
                    'label' => $group,
                    'fields' => [],
                ];
            }

            $groupedFields[$group]['fields'][$key] = $label;
        }

        return $groupedFields;
    }

    /**
     * Fallback meta fields if mapping service fails
     */
    private function get_fallback_meta_fields(): array
    {
        return [
            'Adresse' => [
                'label' => __('Adresse', 'immobridge'),
                'fields' => [
                    'immobridge_geo_strasse' => __('Straße', 'immobridge'),
                    'immobridge_geo_hausnummer' => __('Hausnummer', 'immobridge'),
                    'immobridge_geo_plz' => __('PLZ', 'immobridge'),
                    'immobridge_geo_ort' => __('Ort', 'immobridge'),
                    'immobridge_geo_bundesland' => __('Bundesland', 'immobridge'),
                    'immobridge_geo_land' => __('Land', 'immobridge'),
                ],
            ],
            'Preise' => [
                'label' => __('Preise', 'immobridge'),
                'fields' => [
                    'immobridge_preise_kaufpreis' => __('Kaufpreis', 'immobridge'),
                    'immobridge_preise_nettokaltmiete' => __('Nettokaltmiete', 'immobridge'),
                    'immobridge_preise_kaltmiete' => __('Kaltmiete', 'immobridge'),
                    'immobridge_preise_warmmiete' => __('Warmmiete', 'immobridge'),
                    'immobridge_preise_kaution' => __('Kaution', 'immobridge'),
                ],
            ],
            'Flächen' => [
                'label' => __('Flächen', 'immobridge'),
                'fields' => [
                    'immobridge_flaechen_wohnflaeche' => __('Wohnfläche', 'immobridge'),
                    'immobridge_flaechen_gesamtflaeche' => __('Gesamtfläche', 'immobridge'),
                    'immobridge_flaechen_anzahl_zimmer' => __('Anzahl Zimmer', 'immobridge'),
                    'immobridge_flaechen_anzahl_schlafzimmer' => __('Anzahl Schlafzimmer', 'immobridge'),
                    'immobridge_flaechen_anzahl_badezimmer' => __('Anzahl Badezimmer', 'immobridge'),
                ],
            ],
        ];
    }
}
