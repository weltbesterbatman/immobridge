<?php
/**
 * API Service Provider
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
 * API Service Provider
 *
 * Registers REST API services in the dependency injection container.
 *
 * @since 1.0.0
 */
final class ApiServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services in the container
     *
     * @param Container $container The dependency injection container
     */
    public function register(Container $container): void
    {
        // TODO: Register API services
        $container->singleton('api.controller', function () {
            return new class {
                public function registerRoutes(): void {
                    // TODO: Implement REST API routes
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
        // TODO: Boot API services
    }
}
