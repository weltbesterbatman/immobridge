<?php
/**
 * Frontend Service Provider
 *
 * @package ImmoBridge
 * @subpackage Services
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Services;

use ImmoBridge\Core\Container\Container;
use ImmoBridge\Core\Container\ServiceProviderInterface;

/**
 * Frontend Service Provider
 *
 * Registers frontend-related services in the dependency injection container.
 *
 * @since 1.0.0
 */
final class FrontendServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services in the container
     *
     * @param Container $container The dependency injection container
     */
    public function register(Container $container): void
    {
        // TODO: Register frontend services
        $container->singleton('frontend.assets', function () {
            return new class {
                public function enqueue(): void {
                    // TODO: Implement frontend asset enqueueing
                }
            };
        });
    }

    /**
     * Boot services after all providers have been registered
     *
     * @param Container $container The dependency injection container
     */
    public function boot(Container $container): void
    {
        // TODO: Boot frontend services
    }
}
