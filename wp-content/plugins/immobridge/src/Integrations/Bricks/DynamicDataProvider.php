<?php
/**
 * Bricks Dynamic Data Provider
 *
 * @package ImmoBridge
 * @subpackage Integrations\Bricks
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Integrations\Bricks;

use ImmoBridge\Services\MappingService;

class DynamicDataProvider
{
    private MappingService $mappingService;

    public function __construct(MappingService $mappingService)
    {
        $this->mappingService = $mappingService;
    }

    public function register(): void
    {
        add_filter('bricks/dynamic_data/register_tags', [$this, 'register_dynamic_tags']);
    }

    public function register_dynamic_tags($tags)
    {
        if ($this->mappingService->hasError()) {
            return $tags;
        }

        $mappings = $this->mappingService->getMappings();
        $group = 'immobridge'; // Group name in Bricks editor

        foreach ($mappings as $mapping) {
            if ($mapping['type'] !== 'custom_field' || empty($mapping['destination'])) {
                continue;
            }

            $key = $mapping['destination'];
            $label = !empty($mapping['title de']) ? $mapping['title de'] : (!empty($mapping['title']) ? $mapping['title'] : ucfirst(str_replace('_', ' ', $key)));

            $tags["immobridge_{$key}"] = [
                'label' => $label,
                'group' => $group,
                'render_callback' => [$this, 'render_meta_field'],
                'controls' => [
                    'meta_key' => [
                        'type' => 'hidden',
                        'default' => $key,
                    ],
                ],
            ];
        }
        
        // Add a specific tag for the gallery
        $tags['immobridge_gallery'] = [
            'label' => __('Property Gallery', 'immobridge'),
            'group' => $group,
            'render_callback' => [$this, 'render_gallery_field'],
        ];

        return $tags;
    }

    public function render_meta_field($tag, $post, $context, $args)
    {
        $meta_key = $args['meta_key'] ?? '';

        if (empty($meta_key) || !$post) {
            return '';
        }

        return get_post_meta($post->ID, $meta_key, true);
    }
    
    public function render_gallery_field($tag, $post, $context, $args)
    {
        if (!$post) {
            return [];
        }
        
        $ids_string = get_post_meta($post->ID, 'immobridge_gallery_image_ids', true);
        
        if (empty($ids_string)) {
            return [];
        }
        
        // Bricks expects an array of attachment IDs for gallery elements
        return explode(',', $ids_string);
    }
}
