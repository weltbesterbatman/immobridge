<?php
/**
 * Main Plugin Class
 *
 * @package ImmoBridge
 * @subpackage Core
 * @since 1.0.0
 */

declare(strict_types=1);

namespace ImmoBridge\Core;

use ImmoBridge\Core\Container\Container;
use ImmoBridge\Core\Container\ServiceProviderInterface;
use ImmoBridge\Services\PropertyServiceProvider;
use ImmoBridge\Services\AdminServiceProvider;
use ImmoBridge\Services\BricksServiceProvider;
use Throwable;

/**
 * Main Plugin Class
 *
 * Orchestrates the entire plugin initialization and manages the dependency injection container.
 *
 * @since 1.0.0
 */
final class Plugin
{
    private Container $container;
    private bool $initialized = false;

    /**
     * Service providers to register
     *
     * @var array<class-string<ServiceProviderInterface>>
     */
    private readonly array $serviceProviders;

    public function __construct()
    {
        $this->container = new Container();
        $this->serviceProviders = [
            PropertyServiceProvider::class,
            AdminServiceProvider::class,
            BricksServiceProvider::class,
            // TODO: Add other providers when implemented
            // ImportServiceProvider::class,
            // FrontendServiceProvider::class,
            // ApiServiceProvider::class,
        ];
    }

    /**
     * Initialize the plugin
     *
     * @throws Throwable
     */
    public function init(): void
    {
        if ($this->initialized) {
            return;
        }

        try {
            $this->checkRequirements();
            $this->registerServices();
            $this->bootServices();
            $this->registerHooks();
            
            $this->initialized = true;
            
            do_action('immobridge_initialized', $this->container);
        } catch (Throwable $e) {
            error_log('ImmoBridge initialization failed: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Check system requirements
     *
     * @throws RuntimeException
     */
    private function checkRequirements(): void
    {
        // Check PHP version - relaxed for testing
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            throw new \RuntimeException('ImmoBridge requires PHP 8.0 or higher. Current version: ' . PHP_VERSION);
        }

        // Check WordPress version - relaxed for testing
        global $wp_version;
        if (version_compare($wp_version, '6.0', '<')) {
            throw new \RuntimeException('ImmoBridge requires WordPress 6.0 or higher. Current version: ' . $wp_version);
        }

        // Check required PHP extensions
        $requiredExtensions = ['dom', 'libxml', 'simplexml', 'json', 'mbstring'];
        foreach ($requiredExtensions as $extension) {
            if (!extension_loaded($extension)) {
                throw new \RuntimeException("Required PHP extension '{$extension}' is not loaded.");
            }
        }
    }

    /**
     * Register all service providers
     */
    private function registerServices(): void
    {
        // Register core services first
        $this->container->singleton(Container::class, fn() => $this->container);
        
        // Register plugin constants
        $this->container->singleton('plugin.version', fn() => IMMOBRIDGE_VERSION);
        $this->container->singleton('plugin.file', fn() => IMMOBRIDGE_PLUGIN_FILE);
        $this->container->singleton('plugin.dir', fn() => IMMOBRIDGE_PLUGIN_DIR);
        $this->container->singleton('plugin.url', fn() => IMMOBRIDGE_PLUGIN_URL);
        $this->container->singleton('plugin.basename', fn() => IMMOBRIDGE_PLUGIN_BASENAME);

        // Register service providers
        foreach ($this->serviceProviders as $providerClass) {
            /** @var ServiceProviderInterface $provider */
            $provider = new $providerClass();
            $provider->register($this->container);
        }
    }

    /**
     * Boot all registered services
     */
    private function bootServices(): void
    {
        foreach ($this->serviceProviders as $providerClass) {
            /** @var ServiceProviderInterface $provider */
            $provider = new $providerClass();
            if (method_exists($provider, 'boot')) {
                $provider->boot($this->container);
            }
        }
    }

    /**
     * Register WordPress hooks
     */
    private function registerHooks(): void
    {
        // Load text domain for translations
        add_action('init', [$this, 'loadTextDomain']);
        
        // TODO: Add other hooks when services are implemented
        // - REST API routes
        // - Admin assets
        // - Frontend assets
        // - Bricks Builder integration
    }

    /**
     * Load plugin text domain for translations
     */
    public function loadTextDomain(): void
    {
        load_plugin_textdomain(
            'immobridge',
            false,
            dirname(IMMOBRIDGE_PLUGIN_BASENAME) . '/languages'
        );
    }

    /**
     * Get the dependency injection container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Check if plugin is initialized
     */
    public function isInitialized(): bool
    {
        return $this->initialized;
    }
}
