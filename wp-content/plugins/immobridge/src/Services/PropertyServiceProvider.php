<?php
/**
 * Property Service Provider
 *
 * @package ImmoBridge
 * @subpackage Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Services;

use ImmoBridge\Core\Container\Container;
use ImmoBridge\Core\Container\ServiceProviderInterface;
use ImmoBridge\Repositories\PropertyRepository;
use ImmoBridge\Services\PropertyService;

/**
 * Property Service Provider
 *
 * Registers all property-related services in the dependency injection container.
 *
 * @since 1.0.0
 */
final class PropertyServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services in the container
     *
     * @param Container $container The dependency injection container
     */
    public function register(Container $container): void
    {
        // Register Property Repository
        $container->singleton(PropertyRepository::class);
        $container->alias('property.repository', PropertyRepository::class);

        // Register Property Service
        $container->singleton(PropertyService::class, function (Container $container) {
            return new PropertyService(
                $container->get(PropertyRepository::class)
            );
        });
        $container->alias('property.service', PropertyService::class);

        // Register Custom Post Type Service
        $container->singleton(PropertyPostTypeService::class);
        $container->alias('property.post_type', PropertyPostTypeService::class);

        // Register Taxonomy Service
        $container->singleton(PropertyTaxonomyService::class);
        $container->alias('property.taxonomy', PropertyTaxonomyService::class);
    }

    /**
     * Boot services after all providers have been registered
     *
     * @param Container $container The dependency injection container
     */
    public function boot(Container $container): void
    {
        // Register custom post type
        add_action('init', function () use ($container): void {
            $postTypeService = $container->get('property.post_type');
            $postTypeService->register();
            $postTypeService->registerMetaFields();
        });

        // Register taxonomies
        add_action('init', function () use ($container): void {
            $taxonomyService = $container->get('property.taxonomy');
            $taxonomyService->register();
        });

        // Add meta boxes
        add_action('add_meta_boxes', function () use ($container): void {
            $postTypeService = $container->get('property.post_type');
            $postTypeService->addMetaBoxes();
        });

        // Save post meta
        add_action('save_post', function (int $postId) use ($container): void {
            if (get_post_type($postId) !== 'property') {
                return;
            }

            $postTypeService = $container->get('property.post_type');
            $postTypeService->saveMetaBoxes($postId);
        });
    }
}
