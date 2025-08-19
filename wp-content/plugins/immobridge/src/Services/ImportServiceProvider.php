<?php
/**
 * Import Service Provider
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
 * Import Service Provider
 *
 * Registers import-related services in the dependency injection container.
 *
 * @since 1.0.0
 */
final class ImportServiceProvider implements ServiceProviderInterface
{
    /**
     * Register services in the container
     *
     * @param Container $container The dependency injection container
     */
    public function register(Container $container): void
    {
        // TODO: Register import services
        // - OpenImmo XML Parser
        // - Import Manager
        // - File Handler
        // - Image Processor
    }

    /**
     * Boot services after all providers have been registered
     *
     * @param Container $container The dependency injection container
     */
    public function boot(Container $container): void
    {
        // TODO: Boot import services
        // - Register import hooks
        // - Schedule import tasks
    }
}
