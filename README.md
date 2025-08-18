# ImmoBridge

Modern WordPress plugin for OpenImmo real estate data integration with Bricks Builder support.

## Overview

ImmoBridge is a complete modernization of the legacy `immonex-openimmo2wp` plugin, built with modern PHP 8.2+ features and architectural patterns. It provides seamless integration between OpenImmo XML data and WordPress, with exclusive optimization for Bricks Builder v2.0+.

## Features

- **Modern PHP 8.2+**: Utilizes enums, readonly properties, constructor property promotion, and match expressions
- **PSR-4 Autoloading**: Clean namespace organization with Composer autoloading
- **Dependency Injection**: Custom DI container with auto-wiring capabilities
- **Repository Pattern**: Clean data access abstraction layer
- **Service Provider Architecture**: Modular service registration and bootstrapping
- **Custom Post Types & Taxonomies**: Structured property data management
- **REST API**: Full API endpoints for property data access
- **Bricks Builder Integration**: Custom elements and dynamic data tags
- **Performance Optimized**: Caching, query optimization, and lazy loading
- **Security Hardened**: Input validation, output escaping, and CSRF protection

## Requirements

- **WordPress**: 6.8+
- **PHP**: 8.2+
- **Bricks Builder**: 2.0+ (recommended)
- **Required PHP Extensions**: dom, libxml, simplexml, json, mbstring

## Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/weltbesterbatman/immobridge.git
   ```

2. Install dependencies:

   ```bash
   cd immobridge
   composer install
   ```

3. Activate the plugin in WordPress admin

## Development

### Project Structure

```
src/
├── Core/                    # Core plugin functionality
│   ├── Container/          # Dependency injection container
│   ├── Plugin.php          # Main plugin class
│   ├── Activator.php       # Plugin activation
│   ├── Deactivator.php     # Plugin deactivation
│   └── Uninstaller.php     # Plugin uninstallation
├── Entities/               # Domain entities
│   ├── Enums/             # Enum definitions
│   └── Property.php        # Property entity
├── Repositories/           # Data access layer
│   ├── RepositoryInterface.php
│   └── PropertyRepository.php
├── Services/               # Business logic layer
│   ├── PropertyService.php
│   ├── PropertyPostTypeService.php
│   ├── PropertyTaxonomyService.php
│   └── *ServiceProvider.php
├── Controllers/            # API controllers
├── Admin/                  # Admin interface
├── Frontend/               # Frontend functionality
├── Integrations/           # Third-party integrations
│   ├── Bricks/            # Bricks Builder integration
│   └── OpenImmo/          # OpenImmo XML processing
└── Utils/                  # Utility classes
```

### Development Commands

```bash
# Run tests
composer test

# Run PHPStan analysis
composer phpstan

# Check coding standards
composer phpcs

# Fix coding standards
composer phpcbf

# Generate test coverage
composer test:coverage
```

## Architecture

### Dependency Injection

The plugin uses a custom PSR-11 compliant dependency injection container with auto-wiring capabilities:

```php
// Service registration
$container->singleton(PropertyRepository::class);
$container->bind(PropertyService::class, function($container) {
    return new PropertyService($container->get(PropertyRepository::class));
});

// Service resolution
$propertyService = $container->get(PropertyService::class);
```

### Service Providers

Services are organized using the Service Provider pattern:

```php
final class PropertyServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        // Register services
    }

    public function boot(Container $container): void
    {
        // Boot services and register hooks
    }
}
```

### Entity System

Properties are represented as immutable entities with modern PHP features:

```php
final readonly class Property implements JsonSerializable
{
    public function __construct(
        public ?int $id = null,
        public string $openImmoId = '',
        public PropertyType $type = PropertyType::APARTMENT,
        public PropertyStatus $status = PropertyStatus::AVAILABLE,
        // ... other properties
    ) {}
}
```

## License

GPL v2 or later

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Ensure all tests pass
6. Submit a pull request

## Support

For support and documentation, please visit the [GitHub repository](https://github.com/weltbesterbatman/immobridge).
