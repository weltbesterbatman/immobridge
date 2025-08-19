<?php
/**
 * Service Provider Interface
 *
 * @package ImmoBridge
 * @subpackage Core\Container
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Core\Container;

/**
 * Service Provider Interface
 *
 * Defines the contract for service providers that register services in the DI container.
 *
 * @since 1.0.0
 */
interface ServiceProviderInterface
{
    /**
     * Register services in the container
     *
     * @param Container $container The dependency injection container
     */
    public function register(Container $container): void;

    /**
     * Boot services after all providers have been registered
     *
     * @param Container $container The dependency injection container
     */
    public function boot(Container $container): void;
}
