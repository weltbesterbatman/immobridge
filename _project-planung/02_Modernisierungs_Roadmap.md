# Modernisierungs-Roadmap: Von immonex-openimmo2wp zu ImmoBridge

## Executive Summary

Diese Roadmap definiert den strategischen Migrationspfad von der Legacy-Architektur des immonex-openimmo2wp Plugins zu einer modernen, PSR-4-konformen und testbaren ImmoBridge-Lösung.

## Migrationsstrategie

### Ansatz: Vollständige Neuarchitektur

- **Grund:** Legacy-Code ist zu tief verwurzelt für inkrementelle Refactoring
- **Vorteil:** Moderne Best Practices von Grund auf
- **Risiko:** Komplexe Datenmigration erforderlich

### Backward Compatibility

- **Datenkompatibilität:** 100% - Bestehende Immobiliendaten bleiben erhalten
- **API-Kompatibilität:** Begrenzt - Neue Hook-Namen und -Strukturen
- **Theme-Kompatibilität:** Adapter-Pattern für Legacy-Themes

## Phase 1: Grundlagen-Modernisierung (Wochen 1-3)

### 1.1 Projekt-Setup und Tooling

**Priorität:** Kritisch
**Aufwand:** 3-5 Tage

#### Aufgaben:

```bash
# Composer-Integration
composer init
composer require --dev phpunit/phpunit
composer require --dev squizlabs/php_codesniffer
composer require psr/container
composer require psr/log
```

#### Deliverables:

- `composer.json` mit Abhängigkeiten
- PSR-4 Autoloading-Konfiguration
- PHPUnit-Setup
- Code-Standards-Konfiguration (PHPCS)

### 1.2 Namespace-Architektur

**Priorität:** Kritisch
**Aufwand:** 2-3 Tage

#### Neue Namespace-Struktur:

```php
ImmoBridge\
├── Core\
│   ├── Plugin.php
│   ├── Container.php
│   └── ServiceProvider.php
├── Import\
│   ├── Parser\
│   ├── Mapper\
│   └── Processor\
├── Data\
│   ├── Models\
│   ├── Repositories\
│   └── Validators\
├── Admin\
│   ├── Controllers\
│   ├── Views\
│   └── Assets\
├── API\
│   ├── Endpoints\
│   └── Middleware\
├── Integration\
│   ├── Themes\
│   └── Builders\
└── Utils\
    ├── Logger\
    └── Cache\
```

### 1.3 Dependency Injection Container

**Priorität:** Hoch
**Aufwand:** 3-4 Tage

#### Container-Implementation:

```php
namespace ImmoBridge\Core;

class Container implements ContainerInterface {
    private array $services = [];
    private array $instances = [];

    public function bind(string $abstract, callable $concrete): void
    public function singleton(string $abstract, callable $concrete): void
    public function get(string $id): mixed
    public function has(string $id): bool
}
```

#### Service Provider Pattern:

```php
abstract class ServiceProvider {
    abstract public function register(Container $container): void;
    abstract public function boot(): void;
}
```

## Phase 2: Kern-Architektur (Wochen 4-6)

### 2.1 Custom Post Types & Taxonomien

**Priorität:** Kritisch
**Aufwand:** 5-7 Tage

#### Eigene CPT-Definition:

```php
namespace ImmoBridge\Data\Models;

class PropertyPostType {
    public const POST_TYPE = 'immo_property';

    public function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => $this->getLabels(),
            'public' => true,
            'has_archive' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'show_in_rest' => true,
            'rest_base' => 'properties',
        ]);
    }
}
```

#### Taxonomien-System:

```php
enum PropertyType: string {
    case RESIDENTIAL = 'residential';
    case COMMERCIAL = 'commercial';
    case LAND = 'land';
    case INVESTMENT = 'investment';
}

enum PropertyStatus: string {
    case FOR_SALE = 'for_sale';
    case FOR_RENT = 'for_rent';
    case SOLD = 'sold';
    case RENTED = 'rented';
}
```

### 2.2 Datenmodell-Refactoring

**Priorität:** Hoch
**Aufwand:** 4-6 Tage

#### Property Entity:

```php
namespace ImmoBridge\Data\Models;

class Property {
    public function __construct(
        public readonly int $id,
        public readonly string $title,
        public readonly string $description,
        public readonly PropertyType $type,
        public readonly PropertyStatus $status,
        public readonly Price $price,
        public readonly Location $location,
        public readonly Specifications $specs,
        public readonly array $images = [],
        public readonly array $documents = []
    ) {}
}
```

#### Value Objects:

```php
class Price {
    public function __construct(
        public readonly float $amount,
        public readonly Currency $currency,
        public readonly PriceType $type
    ) {}
}

class Location {
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly string $postalCode,
        public readonly string $country,
        public readonly ?Coordinates $coordinates = null
    ) {}
}
```

### 2.3 Repository Pattern

**Priorität:** Hoch
**Aufwand:** 3-4 Tage

```php
namespace ImmoBridge\Data\Repositories;

interface PropertyRepositoryInterface {
    public function find(int $id): ?Property;
    public function findByOpenImmoId(string $openImmoId): ?Property;
    public function save(Property $property): int;
    public function delete(int $id): bool;
    public function findByCriteria(SearchCriteria $criteria): PropertyCollection;
}

class WordPressPropertyRepository implements PropertyRepositoryInterface {
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {}
}
```

## Phase 3: Import-System-Modernisierung (Wochen 7-9)

### 3.1 XML-Parser-Refactoring

**Priorität:** Hoch
**Aufwand:** 4-5 Tage

#### Parser-Interface:

```php
namespace ImmoBridge\Import\Parser;

interface ParserInterface {
    public function parse(string $filePath): PropertyCollection;
    public function validate(string $filePath): ValidationResult;
    public function getMetadata(string $filePath): ImportMetadata;
}

class OpenImmoXmlParser implements ParserInterface {
    public function __construct(
        private readonly SchemaValidator $validator,
        private readonly LoggerInterface $logger
    ) {}
}
```

#### Schema-Validierung:

```php
class SchemaValidator {
    private const OPENIMMO_SCHEMA_URL = 'http://www.openimmo.de/schema/openimmo_127.xsd';

    public function validate(string $xmlPath): ValidationResult {
        $dom = new DOMDocument();
        $dom->load($xmlPath);

        return $dom->schemaValidate(self::OPENIMMO_SCHEMA_URL)
            ? ValidationResult::success()
            : ValidationResult::failure($this->getErrors());
    }
}
```

### 3.2 Mapping-System-Modernisierung

**Priorität:** Hoch
**Aufwand:** 5-6 Tage

#### JSON-basierte Mappings:

```json
{
  "version": "1.0",
  "mappings": {
    "immobilie.objektkategorie.nutzungsart": {
      "target": "property_type",
      "type": "enum",
      "enum_class": "PropertyType",
      "default": "residential",
      "required": true
    },
    "immobilie.preise.kaufpreis": {
      "target": "purchase_price",
      "type": "money",
      "currency_field": "immobilie.preise.waehrung",
      "validation": {
        "min": 0,
        "max": 999999999
      }
    }
  }
}
```

#### Mapper-Implementation:

```php
namespace ImmoBridge\Import\Mapper;

class PropertyMapper {
    public function __construct(
        private readonly MappingConfiguration $config,
        private readonly ValidatorInterface $validator
    ) {}

    public function map(array $xmlData): Property {
        $mappedData = [];

        foreach ($this->config->getMappings() as $mapping) {
            $value = $this->extractValue($xmlData, $mapping->getSource());
            $mappedData[$mapping->getTarget()] = $this->transformValue($value, $mapping);
        }

        return $this->validator->validate($mappedData);
    }
}
```

### 3.3 Batch-Processing-System

**Priorität:** Mittel
**Aufwand:** 3-4 Tage

```php
namespace ImmoBridge\Import\Processor;

class BatchProcessor {
    private const BATCH_SIZE = 50;

    public function __construct(
        private readonly PropertyRepositoryInterface $repository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    public function processBatch(PropertyCollection $properties): ProcessingResult {
        $batches = $properties->chunk(self::BATCH_SIZE);

        foreach ($batches as $batch) {
            $this->processSingleBatch($batch);
            $this->eventDispatcher->dispatch(new BatchProcessedEvent($batch));
        }
    }
}
```

## Phase 4: API-First Design (Wochen 10-12)

### 4.1 REST API-Endpoints

**Priorität:** Hoch
**Aufwand:** 6-8 Tage

#### API-Struktur:

```php
namespace ImmoBridge\API\Endpoints;

class PropertiesController extends WP_REST_Controller {
    protected string $namespace = 'immobridge/v1';
    protected string $rest_base = 'properties';

    public function register_routes(): void {
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_items'],
            'permission_callback' => [$this, 'get_items_permissions_check'],
            'args' => $this->get_collection_params(),
        ]);
    }
}
```

#### API-Response-Format:

```json
{
  "data": {
    "id": 123,
    "type": "property",
    "attributes": {
      "title": "Moderne Wohnung in München",
      "description": "...",
      "property_type": "residential",
      "price": {
        "amount": 450000,
        "currency": "EUR",
        "type": "purchase"
      },
      "location": {
        "street": "Musterstraße 123",
        "city": "München",
        "postal_code": "80331"
      }
    },
    "relationships": {
      "images": {
        "data": [{ "type": "attachment", "id": 456 }]
      }
    }
  }
}
```

### 4.2 GraphQL-Integration (Optional)

**Priorität:** Niedrig
**Aufwand:** 4-5 Tage

```php
namespace ImmoBridge\API\GraphQL;

class PropertyType {
    public static function getDefinition(): array {
        return [
            'name' => 'Property',
            'fields' => [
                'id' => ['type' => Type::nonNull(Type::id())],
                'title' => ['type' => Type::string()],
                'price' => ['type' => PriceType::getType()],
                'location' => ['type' => LocationType::getType()],
            ]
        ];
    }
}
```

## Phase 5: Performance-Optimierung (Wochen 13-14)

### 5.1 Caching-Layer

**Priorität:** Hoch
**Aufwand:** 3-4 Tage

```php
namespace ImmoBridge\Utils\Cache;

class PropertyCache {
    private const CACHE_GROUP = 'immobridge_properties';
    private const DEFAULT_TTL = 3600; // 1 Stunde

    public function get(string $key): mixed {
        return wp_cache_get($key, self::CACHE_GROUP);
    }

    public function set(string $key, mixed $value, int $ttl = self::DEFAULT_TTL): bool {
        return wp_cache_set($key, $value, self::CACHE_GROUP, $ttl);
    }
}
```

### 5.2 Query-Optimierung

**Priorität:** Hoch
**Aufwand:** 2-3 Tage

```php
class OptimizedPropertyRepository {
    public function findByCriteria(SearchCriteria $criteria): PropertyCollection {
        // Optimierte WP_Query mit Meta-Query-Optimierung
        $query = new WP_Query([
            'post_type' => PropertyPostType::POST_TYPE,
            'meta_query' => $this->buildOptimizedMetaQuery($criteria),
            'posts_per_page' => $criteria->getLimit(),
            'no_found_rows' => true, // Performance-Boost
            'update_post_meta_cache' => false, // Verhindert N+1
        ]);

        return $this->hydrateProperties($query->posts);
    }
}
```

### 5.3 Database-Indizierung

**Priorität:** Mittel
**Aufwand:** 1-2 Tage

```sql
-- Optimierte Indizes für Property-Suche
CREATE INDEX idx_property_type ON wp_postmeta (meta_key, meta_value)
WHERE meta_key = 'property_type';

CREATE INDEX idx_property_price ON wp_postmeta (meta_key, CAST(meta_value AS DECIMAL))
WHERE meta_key = 'property_price';

CREATE INDEX idx_property_location ON wp_postmeta (meta_key, meta_value)
WHERE meta_key IN ('property_city', 'property_postal_code');
```

## Phase 6: Sicherheits-Hardening (Wochen 15-16)

### 6.1 Input-Validierung & Sanitization

**Priorität:** Kritisch
**Aufwand:** 4-5 Tage

```php
namespace ImmoBridge\Data\Validators;

class PropertyValidator {
    public function validate(array $data): ValidationResult {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'price.amount' => ['required', 'numeric', 'min:0'],
            'location.postal_code' => ['required', 'regex:/^\d{5}$/'],
            'property_type' => ['required', 'enum:PropertyType'],
        ];

        return $this->validateAgainstRules($data, $rules);
    }
}
```

### 6.2 Nonce & CSRF-Schutz

**Priorität:** Kritisch
**Aufwand:** 2-3 Tage

```php
namespace ImmoBridge\Admin\Security;

class NonceManager {
    private const NONCE_ACTION = 'immobridge_admin_action';

    public function createNonce(string $action): string {
        return wp_create_nonce(self::NONCE_ACTION . '_' . $action);
    }

    public function verifyNonce(string $nonce, string $action): bool {
        return wp_verify_nonce($nonce, self::NONCE_ACTION . '_' . $action);
    }
}
```

### 6.3 Output-Escaping

**Priorität:** Kritisch
**Aufwand:** 2-3 Tage

```php
namespace ImmoBridge\Utils;

class OutputSanitizer {
    public static function escapeHtml(string $content): string {
        return esc_html($content);
    }

    public static function escapeAttribute(string $content): string {
        return esc_attr($content);
    }

    public static function escapeUrl(string $url): string {
        return esc_url($url);
    }
}
```

## Phase 7: Testing-Integration (Wochen 17-18)

### 7.1 Unit-Testing-Framework

**Priorität:** Mittel
**Aufwand:** 3-4 Tage

```php
namespace ImmoBridge\Tests\Unit;

class PropertyMapperTest extends TestCase {
    private PropertyMapper $mapper;
    private MappingConfiguration $config;

    protected function setUp(): void {
        $this->config = $this->createMock(MappingConfiguration::class);
        $this->mapper = new PropertyMapper($this->config, new PropertyValidator());
    }

    public function testMapBasicProperty(): void {
        $xmlData = ['immobilie' => ['objektkategorie' => ['nutzungsart' => 'WOHNEN']]];
        $property = $this->mapper->map($xmlData);

        $this->assertEquals(PropertyType::RESIDENTIAL, $property->getType());
    }
}
```

### 7.2 Integration-Tests

**Priorität:** Mittel
**Aufwand:** 4-5 Tage

```php
namespace ImmoBridge\Tests\Integration;

class ImportProcessTest extends WP_UnitTestCase {
    public function testCompleteImportProcess(): void {
        $xmlFile = $this->getTestXmlFile();
        $importer = $this->container->get(ImportService::class);

        $result = $importer->import($xmlFile);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(5, $result->getImportedProperties());
    }
}
```

## Phase 8: Bricks Builder Integration (Wochen 19-20)

### 8.1 Custom Bricks Elements

**Priorität:** Hoch
**Aufwand:** 5-6 Tage

```php
namespace ImmoBridge\Integration\Builders\Bricks;

class PropertyListElement extends \Bricks\Element {
    public $category = 'immobridge';
    public $name = 'property-list';
    public $icon = 'fas fa-home';

    public function get_label(): string {
        return 'Property List';
    }

    public function set_controls(): void {
        $this->controls['query_type'] = [
            'tab' => 'content',
            'label' => 'Query Type',
            'type' => 'select',
            'options' => [
                'recent' => 'Recent Properties',
                'featured' => 'Featured Properties',
                'custom' => 'Custom Query'
            ]
        ];
    }
}
```

### 8.2 Dynamic Data Integration

**Priorität:** Hoch
**Aufwand:** 3-4 Tage

```php
class PropertyDynamicData {
    public function register_tags(): void {
        add_filter('bricks/dynamic_data/render_tag', [$this, 'render_property_tag'], 10, 3);
        add_filter('bricks/dynamic_data/render_content', [$this, 'render_property_content'], 10, 2);
    }

    public function render_property_tag(string $tag, \Bricks\Post $post, string $context): string {
        if (strpos($tag, 'property_') === 0) {
            return $this->getPropertyData($post->ID, substr($tag, 9));
        }

        return $tag;
    }
}
```

## Phase 9: Migration & Deployment (Wochen 21-22)

### 9.1 Daten-Migration

**Priorität:** Kritisch
**Aufwand:** 4-5 Tage

```php
namespace ImmoBridge\Migration;

class LegacyDataMigrator {
    public function migrate(): MigrationResult {
        $legacyProperties = $this->findLegacyProperties();
        $migrationLog = new MigrationLog();

        foreach ($legacyProperties as $legacyProperty) {
            try {
                $modernProperty = $this->convertToModernFormat($legacyProperty);
                $this->repository->save($modernProperty);
                $migrationLog->addSuccess($legacyProperty->ID);
            } catch (Exception $e) {
                $migrationLog->addError($legacyProperty->ID, $e->getMessage());
            }
        }

        return new MigrationResult($migrationLog);
    }
}
```

### 9.2 Rollback-Strategie

**Priorität:** Hoch
**Aufwand:** 2-3 Tage

```php
class RollbackManager {
    public function createBackup(): BackupResult {
        // Vollständige Datenbank-Sicherung
        // Export aller Property-Daten
        // Konfigurationssicherung
    }

    public function rollback(string $backupId): RollbackResult {
        // Wiederherstellung der Legacy-Struktur
        // Datenrückmigration
        // Plugin-Deaktivierung
    }
}
```

## Risikomanagement

### Kritische Risiken

#### 1. Datenverlust bei Migration

- **Wahrscheinlichkeit:** Mittel
- **Impact:** Kritisch
- **Mitigation:**
  - Vollständige Backups vor Migration
  - Staging-Environment-Tests
  - Rollback-Mechanismus

#### 2. Theme-Kompatibilitätsprobleme

- **Wahrscheinlichkeit:** Hoch
- **Impact:** Hoch
- **Mitigation:**
  - Legacy-Adapter-Pattern
  - Schrittweise Theme-Migration
  - Dokumentierte Migration-Guides

#### 3. Performance-Regression

- **Wahrscheinlichkeit:** Mittel
- **Impact:** Mittel
- **Mitigation:**
  - Performance-Benchmarks
  - Load-Testing
  - Monitoring-Integration

### Technische Risiken

#### 1. WordPress-Kompatibilität

- **Mitigation:** WordPress Coding Standards befolgen
- **Testing:** Kompatibilitätstests mit WP 6.0+

#### 2. PHP-Version-Abhängigkeiten

- **Mitigation:** Polyfills für kritische Features
- **Testing:** Multi-Version-Testing (PHP 8.0-8.3)

## Qualitätssicherung

### Code-Quality-Metriken

```bash
# PHPStan Level 8 Compliance
vendor/bin/phpstan analyse src --level=8

# PHPCS WordPress Standards
vendor/bin/phpcs --standard=WordPress src/

# PHPUnit Coverage > 80%
vendor/bin/phpunit --coverage-html coverage/
```

### Continuous Integration

```yaml
# .github/workflows/ci.yml
name: CI
on: [push, pull_request]
jobs:
  test:
    runs-on: ubuntu-latest
    strategy:
      matrix:
        php: ["8.0", "8.1", "8.2", "8.3"]
        wordpress: ["6.0", "6.1", "6.2", "6.3"]
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: ${{ matrix.php }}
      - name: Install dependencies
        run: composer install
      - name: Run tests
        run: vendor/bin/phpunit
```

## Zeitplan & Meilensteine

### Meilenstein 1: Grundlagen (Woche 3)

- ✅ PSR-4 Autoloading
- ✅ Dependency Injection Container
- ✅ Basis-Namespace-Struktur

### Meilenstein 2: Kern-Features (Woche 6)

- ✅ Custom Post Types & Taxonomien
- ✅ Datenmodell-Refactoring
- ✅ Repository Pattern

### Meilenstein 3: Import-System (Woche 9)

- ✅ Modernisierter XML-Parser
- ✅ JSON-basierte Mappings
- ✅ Batch-Processing

### Meilenstein 4: API-Integration (Woche 12)

- ✅ REST API-Endpoints
- ✅ Bricks Builder Integration
- ✅ Dynamic Data Support

### Meilenstein 5: Produktionsreife (Woche 16)

- ✅ Sicherheits-Hardening
- ✅ Performance-Optimierung
- ✅ Testing-Coverage > 80%

### Meilenstein 6: Migration (Woche 22)

- ✅ Legacy-Daten-Migration
- ✅ Rollback-Mechanismus
- ✅ Produktions-Deployment

## Ressourcen-Planung

### Entwicklungsressourcen

- **Senior PHP Developer:** 22 Wochen (Vollzeit)
- **WordPress Specialist:** 8 Wochen (Teilzeit)
- **QA Engineer:** 4 Wochen (Teilzeit)

### Infrastruktur

- **Staging-Environment:** WordPress 6.3 + PHP 8.2
- **Testing-Environment:** Multi-Version-Matrix
- **CI/CD-Pipeline:** GitHub Actions

## Success Metrics

### Technische KPIs

- **Code Coverage:** > 80%
- **PHPStan Level:** 8
- **Performance:** < 2s Import-Zeit pro Property
- **Memory Usage:** < 128MB für 1000 Properties

### Business KPIs

- **Migration Success Rate:** > 99%
- **Theme Compatibility:** Top 5 Real Estate Themes
- **User Adoption:** > 80% der bestehenden Nutzer

## Nächste Schritte

1. **Stakeholder-Approval:** Roadmap-Genehmigung einholen
2. **Environment-Setup:** Entwicklungsumgebung vorbereiten
3. **Team-Onboarding:** Entwickler-Briefing und Tool-Setup
4. **Phase 1 Start:** Composer-Setup und Namespace-Definition

## Anhang

### Referenz-Implementierungen

- **Modern WordPress Plugin:** WooCommerce Architecture
- **PSR-4 Example:** Symfony Bundle Structure
- **DI Container:** Laravel Service Container
- **Testing:** WordPress Plugin Boilerplate

### Weiterführende Ressourcen

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [PSR-4 Autoloading Standard](https://www.php-fig.org/psr/psr-4/)
- [PHP 8.2 Features](https://www.php.net/releases/8.2/en.php)
- [Bricks Builder Developer Docs](https://academy.bricksbuilder.io/topic/developer/)
