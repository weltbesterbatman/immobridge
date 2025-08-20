<?php
/**
 * Bricks Integration Service Provider
 *
 * @package ImmoBridge
 * @subpackage Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Services;

use ImmoBridge\Core\Container\Container;
use ImmoBridge\Core\Container\ServiceProviderInterface;
use ImmoBridge\Integrations\Bricks\DynamicDataProvider;

final class BricksIntegrationServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // Register the Dynamic Data Provider
        $container->singleton(DynamicDataProvider::class, function ($container) {
            $mappingService = $container->get(MappingService::class);
            return new DynamicDataProvider($mappingService);
        });
    }

    public function boot(Container $container): void
    {
        // Boot the integration only if Bricks Builder is active
        add_action('after_setup_theme', function () use ($container) {
            if (defined('BRICKS_VERSION')) {
                $dynamicDataProvider = $container->get(DynamicDataProvider::class);
                $dynamicDataProvider->register();
            }
        });
    }
}
