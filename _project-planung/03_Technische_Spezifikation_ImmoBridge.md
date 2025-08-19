# Technische Spezifikation: ImmoBridge Plugin

## Projektübersicht

ImmoBridge ist ein modernes WordPress-Plugin für die Integration von OpenImmo XML-Daten, entwickelt nach aktuellen PHP 8.2+ Standards und WordPress Best Practices.

## Systemanforderungen

### Mindestanforderungen

- **WordPress:** 6.0+
- **PHP:** 8.0+
- **MySQL:** 5.7+ oder MariaDB 10.3+
- **Memory Limit:** 256MB
- **Max Execution Time:** 300s (für große Imports)

### Empfohlene Anforderungen

- **WordPress:** 6.3+
- **PHP:** 8.2+
- **MySQL:** 8.0+ oder MariaDB 10.6+
- **Memory Limit:** 512MB
- **Object Cache:** Redis/Memcached

## Plugin-Architektur

### Namespace-Struktur

```php
ImmoBridge\
├── Core\                    # Kern-Framework
│   ├── Plugin.php          # Haupt-Plugin-Klasse
│   ├── Container.php       # DI Container
│   ├── ServiceProvider.php # Service Provider Base
│   └── Bootstrap.php       # Plugin-Initialisierung
├── Data\                   # Datenmodell & Persistierung
│   ├── Models\            # Domain Models
│   ├── Repositories\      # Data Access Layer
│   ├── Validators\        # Datenvalidierung
│   └── Migrations\        # Schema-Migrationen
├── Import\                # Import-System
│   ├── Parser\           # XML-Parser
│   ├── Mapper\           # Daten-Mapping
│   ├── Processor\        # Batch-Processing
│   └── Validators\       # Import-Validierung
├── API\                  # REST API
│   ├── Controllers\      # API-Controller
│   ├── Middleware\       # Request/Response-Middleware
│   ├── Serializers\      # Daten-Serialisierung
│   └── Validators\       # API-Validierung
├── Admin\                # WordPress Admin
│   ├── Controllers\      # Admin-Controller
│   ├── Views\           # Admin-Templates
│   ├── Assets\          # CSS/JS Assets
│   └── Security\        # Nonce/CSRF-Schutz
├── Integration\          # Third-Party-Integrationen
│   ├── Themes\          # Theme-Adapter
│   ├── Builders\        # Page Builder Integration
│   └── Plugins\         # Plugin-Kompatibilität
├── Utils\               # Utilities & Helpers
│   ├── Cache\           # Caching-System
│   ├── Logger\          # Logging-Framework
│   ├── Sanitizer\       # Input/Output-Sanitization
│   └── Validator\       # Allgemeine Validierung
└── Events\              # Event-System
    ├── Listeners\       # Event-Listener
    └── Dispatchers\     # Event-Dispatcher
```

## Kern-Komponenten

### 1. Plugin-Bootstrap

```php
namespace ImmoBridge\Core;

final class Plugin {
    private const VERSION = '1.0.0';
    private const MIN_PHP_VERSION = '8.0';
    private const MIN_WP_VERSION = '6.0';

    private Container $container;
    private array $serviceProviders = [];

    public function __construct() {
        $this->container = new Container();
        $this->registerServiceProviders();
    }

    public function boot(): void {
        if (!$this->meetsRequirements()) {
            add_action('admin_notices', [$this, 'showRequirementsNotice']);
            return;
        }

        $this->bootServiceProviders();
        $this->registerHooks();
    }

    private function registerServiceProviders(): void {
        $this->serviceProviders = [
            DataServiceProvider::class,
            ImportServiceProvider::class,
            APIServiceProvider::class,
            AdminServiceProvider::class,
            IntegrationServiceProvider::class,
        ];

        foreach ($this->serviceProviders as $provider) {
            $instance = new $provider();
            $instance->register($this->container);
        }
    }
}
```

### 2. Dependency Injection Container

```php
namespace ImmoBridge\Core;

class Container implements ContainerInterface {
    private array $bindings = [];
    private array $instances = [];
    private array $singletons = [];

    public function bind(string $abstract, callable|string $concrete): void {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, callable|string $concrete): void {
        $this->bind($abstract, $concrete);
        $this->singletons[$abstract] = true;
    }

    public function get(string $id): mixed {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        $instance = $this->resolve($id);

        if (isset($this->singletons[$id])) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    private function resolve(string $abstract): mixed {
        if (!isset($this->bindings[$abstract])) {
            return $this->autowire($abstract);
        }

        $concrete = $this->bindings[$abstract];

        return is_callable($concrete)
            ? $concrete($this)
            : $this->get($concrete);
    }

    private function autowire(string $class): object {
        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if (!$constructor) {
            return new $class();
        }

        $dependencies = [];
        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type && !$type->isBuiltin()) {
                $dependencies[] = $this->get($type->getName());
            }
        }

        return new $class(...$dependencies);
    }
}
```

## Datenmodell

### 1. Property Entity

```php
namespace ImmoBridge\Data\Models;

class Property {
    public function __construct(
        public readonly ?int $id,
        public readonly string $openImmoId,
        public readonly string $title,
        public readonly string $description,
        public readonly PropertyType $type,
        public readonly PropertyStatus $status,
        public readonly PropertyCategory $category,
        public readonly Price $price,
        public readonly Location $location,
        public readonly Specifications $specifications,
        public readonly Contact $contact,
        public readonly PropertyImages $images,
        public readonly PropertyDocuments $documents,
        public readonly PropertyFeatures $features,
        public readonly DateTime $createdAt,
        public readonly DateTime $updatedAt,
        public readonly ?DateTime $availableFrom = null
    ) {}

    public function toArray(): array {
        return [
            'id' => $this->id,
            'openimmo_id' => $this->openImmoId,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'status' => $this->status->value,
            'category' => $this->category->value,
            'price' => $this->price->toArray(),
            'location' => $this->location->toArray(),
            'specifications' => $this->specifications->toArray(),
            'contact' => $this->contact->toArray(),
            'images' => $this->images->toArray(),
            'documents' => $this->documents->toArray(),
            'features' => $this->features->toArray(),
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt->format('Y-m-d H:i:s'),
            'available_from' => $this->availableFrom?->format('Y-m-d'),
        ];
    }
}
```

### 2. Enums & Value Objects

```php
enum PropertyType: string {
    case RESIDENTIAL = 'residential';
    case COMMERCIAL = 'commercial';
    case LAND = 'land';
    case INVESTMENT = 'investment';
    case PARKING = 'parking';
    case STORAGE = 'storage';
}

enum PropertyStatus: string {
    case AVAILABLE = 'available';
    case RESERVED = 'reserved';
    case SOLD = 'sold';
    case RENTED = 'rented';
    case WITHDRAWN = 'withdrawn';
}

enum PropertyCategory: string {
    case APARTMENT = 'apartment';
    case HOUSE = 'house';
    case OFFICE = 'office';
    case RETAIL = 'retail';
    case WAREHOUSE = 'warehouse';
    case PLOT = 'plot';
}

class Price {
    public function __construct(
        public readonly float $amount,
        public readonly Currency $currency,
        public readonly PriceType $type,
        public readonly ?float $pricePerSqm = null,
        public readonly ?float $additionalCosts = null,
        public readonly ?float $commission = null
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException('Price amount cannot be negative');
        }
    }

    public function toArray(): array {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency->value,
            'type' => $this->type->value,
            'price_per_sqm' => $this->pricePerSqm,
            'additional_costs' => $this->additionalCosts,
            'commission' => $this->commission,
        ];
    }
}

class Location {
    public function __construct(
        public readonly string $street,
        public readonly string $houseNumber,
        public readonly string $city,
        public readonly string $postalCode,
        public readonly string $state,
        public readonly Country $country,
        public readonly ?Coordinates $coordinates = null,
        public readonly ?string $district = null,
        public readonly ?string $region = null
    ) {}

    public function getFullAddress(): string {
        return sprintf(
            '%s %s, %s %s, %s',
            $this->street,
            $this->houseNumber,
            $this->postalCode,
            $this->city,
            $this->country->getDisplayName()
        );
    }
}

class Specifications {
    public function __construct(
        public readonly ?float $livingArea = null,
        public readonly ?float $totalArea = null,
        public readonly ?float $plotArea = null,
        public readonly ?int $rooms = null,
        public readonly ?int $bedrooms = null,
        public readonly ?int $bathrooms = null,
        public readonly ?int $floors = null,
        public readonly ?int $buildYear = null,
        public readonly ?EnergyClass $energyClass = null,
        public readonly ?HeatingType $heatingType = null,
        public readonly ?bool $furnished = null,
        public readonly ?bool $balcony = null,
        public readonly ?bool $terrace = null,
        public readonly ?bool $garden = null,
        public readonly ?bool $garage = null,
        public readonly ?int $parkingSpaces = null
    ) {}
}
```

### 3. Custom Post Types & Taxonomien

```php
namespace ImmoBridge\Data\Models;

class PropertyPostType {
    public const POST_TYPE = 'immo_property';

    public function register(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => $this->getLabels(),
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_nav_menus' => true,
            'show_in_admin_bar' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'properties'],
            'capability_type' => 'post',
            'has_archive' => true,
            'hierarchical' => false,
            'menu_position' => 5,
            'menu_icon' => 'dashicons-building',
            'supports' => [
                'title',
                'editor',
                'thumbnail',
                'excerpt',
                'custom-fields',
                'revisions'
            ],
            'show_in_rest' => true,
            'rest_base' => 'properties',
            'rest_controller_class' => PropertiesController::class,
        ]);
    }

    private function getLabels(): array {
        return [
            'name' => __('Properties', 'immobridge'),
            'singular_name' => __('Property', 'immobridge'),
            'menu_name' => __('Properties', 'immobridge'),
            'add_new' => __('Add New Property', 'immobridge'),
            'add_new_item' => __('Add New Property', 'immobridge'),
            'edit_item' => __('Edit Property', 'immobridge'),
            'new_item' => __('New Property', 'immobridge'),
            'view_item' => __('View Property', 'immobridge'),
            'search_items' => __('Search Properties', 'immobridge'),
            'not_found' => __('No properties found', 'immobridge'),
            'not_found_in_trash' => __('No properties found in trash', 'immobridge'),
        ];
    }
}

class PropertyTaxonomies {
    public const PROPERTY_TYPE = 'property_type';
    public const PROPERTY_STATUS = 'property_status';
    public const PROPERTY_CATEGORY = 'property_category';
    public const PROPERTY_LOCATION = 'property_location';
    public const PROPERTY_FEATURES = 'property_features';

    public function register(): void {
        $this->registerPropertyType();
        $this->registerPropertyStatus();
        $this->registerPropertyCategory();
        $this->registerPropertyLocation();
        $this->registerPropertyFeatures();
    }

    private function registerPropertyType(): void {
        register_taxonomy(self::PROPERTY_TYPE, PropertyPostType::POST_TYPE, [
            'labels' => $this->getTypeLabels(),
            'hierarchical' => false,
            'public' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud' => false,
            'show_in_rest' => true,
            'rest_base' => 'property-types',
        ]);
    }
}
```

## Import-System

### 1. XML-Parser-Architektur

```php
namespace ImmoBridge\Import\Parser;

interface ParserInterface {
    public function parse(string $filePath): PropertyCollection;
    public function validate(string $filePath): ValidationResult;
    public function getMetadata(string $filePath): ImportMetadata;
    public function supports(string $filePath): bool;
}

class OpenImmoXmlParser implements ParserInterface {
    private const SUPPORTED_VERSIONS = ['1.2.7', '1.2.8', '1.3.0'];

    public function __construct(
        private readonly SchemaValidator $validator,
        private readonly LoggerInterface $logger,
        private readonly PropertyFactory $propertyFactory
    ) {}

    public function parse(string $filePath): PropertyCollection {
        $this->logger->info('Starting XML parsing', ['file' => $filePath]);

        $validationResult = $this->validate($filePath);
        if (!$validationResult->isValid()) {
            throw new InvalidXmlException($validationResult->getErrors());
        }

        $properties = new PropertyCollection();
        $reader = new XMLReader();
        $reader->open($filePath);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'immobilie') {
                $propertyXml = $reader->readOuterXML();
                $property = $this->parseProperty($propertyXml);
                $properties->add($property);
            }
        }

        $reader->close();
        $this->logger->info('XML parsing completed', ['count' => $properties->count()]);

        return $properties;
    }

    private function parseProperty(string $xmlString): Property {
        $dom = new DOMDocument();
        $dom->loadXML($xmlString);

        $propertyData = $this->extractPropertyData($dom);
        return $this->propertyFactory->create($propertyData);
    }
}

class SchemaValidator {
    private const SCHEMA_URLS = [
        '1.2.7' => 'http://www.openimmo.de/schema/openimmo_127.xsd',
        '1.2.8' => 'http://www.openimmo.de/schema/openimmo_128.xsd',
        '1.3.0' => 'http://www.openimmo.de/schema/openimmo_130.xsd',
    ];

    public function validate(string $xmlPath): ValidationResult {
        $version = $this->detectVersion($xmlPath);

        if (!isset(self::SCHEMA_URLS[$version])) {
            return ValidationResult::failure(['Unsupported OpenImmo version: ' . $version]);
        }

        $dom = new DOMDocument();
        $dom->load($xmlPath);

        $errors = [];
        $originalErrorHandler = libxml_use_internal_errors(true);

        $isValid = $dom->schemaValidate(self::SCHEMA_URLS[$version]);

        if (!$isValid) {
            $errors = array_map(
                fn($error) => $error->message,
                libxml_get_errors()
            );
        }

        libxml_use_internal_errors($originalErrorHandler);

        return $isValid
            ? ValidationResult::success()
            : ValidationResult::failure($errors);
    }
}
```

### 2. Mapping-System

```php
namespace ImmoBridge\Import\Mapper;

class MappingConfiguration {
    private array $mappings = [];

    public static function fromJson(string $jsonPath): self {
        $data = json_decode(file_get_contents($jsonPath), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new InvalidMappingException('Invalid JSON mapping file');
        }

        return new self($data['mappings']);
    }

    public function getMapping(string $source): ?FieldMapping {
        return $this->mappings[$source] ?? null;
    }

    public function getAllMappings(): array {
        return $this->mappings;
    }
}

class FieldMapping {
    public function __construct(
        public readonly string $source,
        public readonly string $target,
        public readonly DataType $type,
        public readonly mixed $default = null,
        public readonly bool $required = false,
        public readonly array $validation = [],
        public readonly ?string $transformer = null
    ) {}
}

class PropertyMapper {
    public function __construct(
        private readonly MappingConfiguration $config,
        private readonly TransformerRegistry $transformers,
        private readonly ValidatorInterface $validator
    ) {}

    public function map(array $xmlData): Property {
        $mappedData = [];

        foreach ($this->config->getAllMappings() as $mapping) {
            $value = $this->extractValue($xmlData, $mapping->source);

            if ($value === null && $mapping->required) {
                throw new MappingException("Required field missing: {$mapping->source}");
            }

            $transformedValue = $this->transformValue($value, $mapping);
            $mappedData[$mapping->target] = $transformedValue;
        }

        $validationResult = $this->validator->validate($mappedData);

        if (!$validationResult->isValid()) {
            throw new ValidationException($validationResult->getErrors());
        }

        return Property::fromArray($mappedData);
    }

    private function transformValue(mixed $value, FieldMapping $mapping): mixed {
        if ($mapping->transformer) {
            $transformer = $this->transformers->get($mapping->transformer);
            return $transformer->transform($value);
        }

        return match ($mapping->type) {
            DataType::STRING => (string) $value,
            DataType::INTEGER => (int) $value,
            DataType::FLOAT => (float) $value,
            DataType::BOOLEAN => (bool) $value,
            DataType::ENUM => $this->transformEnum($value, $mapping),
            DataType::MONEY => $this->transformMoney($value, $mapping),
            DataType::DATE => $this->transformDate($value),
            default => $value
        };
    }
}
```

### 3. Batch-Processing

```php
namespace ImmoBridge\Import\Processor;

class BatchProcessor {
    private const DEFAULT_BATCH_SIZE = 50;
    private const MAX_EXECUTION_TIME = 300;

    public function __construct(
        private readonly PropertyRepositoryInterface $repository,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
        private readonly CacheInterface $cache
    ) {}

    public function process(PropertyCollection $properties, ProcessingOptions $options): ProcessingResult {
        $batchSize = $options->getBatchSize() ?? self::DEFAULT_BATCH_SIZE;
        $batches = $properties->chunk($batchSize);

        $result = new ProcessingResult();
        $startTime = time();

        foreach ($batches as $batchIndex => $batch) {
            if (time() - $startTime > self::MAX_EXECUTION_TIME) {
                $this->scheduleRemainingBatches($batches, $batchIndex, $options);
                break;
            }

            $batchResult = $this->processBatch($batch, $batchIndex);
            $result->merge($batchResult);

            $this->eventDispatcher->dispatch(
                new BatchProcessedEvent($batchIndex, $batchResult)
            );
        }

        return $result;
    }

    private function processBatch(PropertyCollection $batch, int $batchIndex): BatchResult {
        $this->logger->info("Processing batch {$batchIndex}", ['size' => $batch->count()]);

        $result = new BatchResult($batchIndex);

        foreach ($batch as $property) {
            try {
                $existingProperty = $this->repository->findByOpenImmoId($property->openImmoId);

                if ($existingProperty) {
                    $updatedProperty = $this->updateProperty($existingProperty, $property);
                    $result->addUpdated($updatedProperty);
                } else {
                    $newProperty = $this->repository->save($property);
                    $result->addCreated($newProperty);
                }

            } catch (Exception $e) {
                $this->logger->error('Property processing failed', [
                    'openimmo_id' => $property->openImmoId,
                    'error' => $e->getMessage()
                ]);
                $result->addError($property, $e);
            }
        }

        return $result;
    }
}
```

## Repository Pattern

### 1. Property Repository

```php
namespace ImmoBridge\Data\Repositories;

interface PropertyRepositoryInterface {
    public function find(int $id): ?Property;
    public function findByOpenImmoId(string $openImmoId): ?Property;
    public function save(Property $property): Property;
    public function delete(int $id): bool;
    public function findByCriteria(SearchCriteria $criteria): PropertyCollection;
    public function count(SearchCriteria $criteria): int;
    public function exists(string $openImmoId): bool;
}

class WordPressPropertyRepository implements PropertyRepositoryInterface {
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        private readonly PropertyHydrator $hydrator
    ) {}

    public function find(int $id): ?Property {
        $cacheKey = "property_{$id}";

        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        $post = get_post($id);

        if (!$post || $post->post_type !== PropertyPostType::POST_TYPE) {
            return null;
        }

        $property = $this->hydrator->hydrate($post);
        $this->cache->set($cacheKey, $property, 3600);

        return $property;
    }

    public function save(Property $property): Property {
        $postData = [
            'post_title' => $property->title,
            'post_content' => $property->description,
            'post_type' => PropertyPostType::POST_TYPE,
            'post_status' => 'publish',
            'meta_input' => $this->prepareMetaData($property),
        ];

        if ($property->id) {
            $postData['ID'] = $property->id;
            $postId = wp_update_post($postData);
        } else {
            $postId = wp_insert_post($postData);
        }

        if (is_wp_error($postId)) {
            throw new RepositoryException('Failed to save property: ' . $postId->get_error_message());
        }

        $this->updateTaxonomies($postId, $property);
        $this->cache->delete("property_{$postId}");

        return $this->find($postId);
    }

    public function findByCriteria(SearchCriteria $criteria): PropertyCollection {
        $queryArgs = [
            'post_type' => PropertyPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $criteria->getLimit(),
            'offset' => $criteria->getOffset(),
            'orderby' => $criteria->getOrderBy(),
            'order' => $criteria->getOrder(),
            'no_found_rows' => !$criteria->needsTotal(),
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ];

        if ($criteria->hasMetaQuery()) {
            $queryArgs['meta_query'] = $this->buildMetaQuery($criteria);
        }

        if ($criteria->hasTaxQuery()) {
            $queryArgs['tax_query'] = $this->buildTaxQuery($criteria);
        }

        $query = new WP_Query($queryArgs);

        return $this->hydrator->hydrateCollection($query->posts);
    }
}
```

### 2. Property Hydrator

```php
namespace ImmoBridge\Data\Repositories;

class PropertyHydrator {
    public function __construct(
        private readonly MetaDataExtractor $metaExtractor,
        private readonly TaxonomyExtractor $taxonomyExtractor
    ) {}

    public function hydrate(WP_Post $post): Property {
        $metaData = $this->metaExtractor->extract($post->ID);
        $taxonomyData = $this->taxonomyExtractor->extract($post->ID);

        return new Property(
            id: $post->ID,
            openImmoId: $metaData['openimmo_id'],
            title: $post->post_title,
            description: $post->post_content,
            type: PropertyType::from($taxonomyData['property_type']),
            status: PropertyStatus::from($taxonomyData['property_status']),
            category: PropertyCategory::from($taxonomyData['property_category']),
            price: $this->hydratePrice($metaData),
            location: $this->hydrateLocation($metaData),
            specifications: $this->hydrateSpecifications($metaData),
            contact: $this->hydrateContact($metaData),
            images: $this->hydrateImages($post->ID),
            documents: $this->hydrateDocuments($post->ID),
            features: $this->hydrateFeatures($taxonomyData),
            createdAt: new DateTime($post->post_date),
            updatedAt: new DateTime($post->post_modified),
            availableFrom: $this->parseDate($metaData['available_from'] ?? null)
        );
    }

    public function hydrateCollection(array $posts): PropertyCollection {
        $properties = new PropertyCollection();

        foreach ($posts as $post) {
            $properties->add($this->hydrate($post));
        }

        return $properties;
    }
}
```

## REST API-Spezifikation

### 1. API-Endpoints

```php
namespace ImmoBridge\API\Controllers;

class PropertiesController extends WP_REST_Controller {
    protected string $namespace = 'immobridge/v1';
    protected string $rest_base = 'properties';

    public function __construct(
        private readonly PropertyRepositoryInterface $repository,
        private readonly PropertySerializer $serializer,
        private readonly RequestValidator $validator
    ) {}

    public function register_routes(): void {
        // GET /wp-json/immobridge/v1/properties
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_items'],
            'permission_callback' => [$this, 'get_items_permissions_check'],
            'args' => $this->get_collection_params(),
        ]);

        // GET /wp-json/immobridge/v1/properties/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'get_item'],
            'permission_callback' => [$this, 'get_item_permissions_check'],
            'args' => ['id' => ['validate_callback' => 'is_numeric']],
        ]);

        // POST /wp-json/immobridge/v1/properties
        register_rest_route($this->namespace, '/' . $this->rest_base, [
            'methods' => WP_REST_Server::CREATABLE,
            'callback' => [$this, 'create_item'],
            'permission_callback' => [$this, 'create_item_permissions_check'],
            'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::CREATABLE),
        ]);

        // PUT /wp-json/immobridge/v1/properties/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            'methods' => WP_REST_Server::EDITABLE,
            'callback' => [$this, 'update_item'],
            'permission_callback' => [$this, 'update_item_permissions_check'],
            'args' => $this->get_endpoint_args_for_item_schema(WP_REST_Server::EDITABLE),
        ]);

        // DELETE /wp-json/immobridge/v1/properties/{id}
        register_rest_route($this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
            'methods' => WP_REST_Server::DELETABLE,
            'callback' => [$this, 'delete_item'],
            'permission_callback' => [$this, 'delete_item_permissions_check'],
            'args' => ['id' => ['validate_callback' => 'is_numeric']],
        ]);

        // GET /wp-json/immobridge/v1/properties/search
        register_rest_route($this->namespace, '/' . $this->rest_base . '/search', [
            'methods' => WP_REST_Server::READABLE,
            'callback' => [$this, 'search_properties'],
            'permission_callback' => [$this, 'get_items_permissions_check'],
            'args' => $this->get_search_params(),
        ]);
    }

    public function get_items(WP_REST_Request $request): WP_REST_Response {
        $criteria = SearchCriteria::fromRequest($request);
        $properties = $this->repository->findByCriteria($criteria);

        $data = [];
        foreach ($properties as $property) {
            $data[] = $this->serializer->serialize($property);
        }

        return new WP_REST_Response([
            'data' => $data,
            'meta' => [
                'total' => $this->repository->count($criteria),
                'page' => $criteria->getPage(),
                'per_page' => $criteria->getLimit(),
            ]
        ]);
    }
}
```

### 2. API-Serialisierung

```php
namespace ImmoBridge\API\Serializers;

class PropertySerializer {
    public function serialize(Property $property): array {
        return [
            'id' => $property->id,
            'type' => 'property',
            'attributes' => [
                'openimmo_id' => $property->openImmoId,
                'title' => $property->title,
                'description' => $property->description,
                'property_type' => $property->type->value,
                'status' => $property->status->value,
                'category' => $property->category->value,
                'price' => $this->serializePrice($property->price),
                'location' => $this->serializeLocation($property->location),
                'specifications' => $this->serializeSpecifications($property->specifications),
                'created_at' => $property->createdAt->format(DateTime::ISO8601),
                'updated_at' => $property->updatedAt->format(DateTime::ISO8601),
                'available_from' => $property->availableFrom?->format('Y-m-d'),
            ],
            'relationships' => [
                'images' => [
                    'data' => $this->serializeImages($property->images)
                ],
                'documents' => [
                    'data' => $this->serializeDocuments($property->documents)
                ],
                'contact' => [
                    'data' => $this->serializeContact($property->contact)
                ]
            ],
            'links' => [
                'self' => rest_url("immobridge/v1/properties/{$property->id}"),
                'edit' => admin_url("post.php?post={$property->id}&action=edit"),
            ]
        ];
    }
}
```

### 3. API-Middleware

```php
namespace ImmoBridge\API\Middleware;

class RateLimitMiddleware {
    private const DEFAULT_LIMIT = 100;
    private const DEFAULT_WINDOW = 3600; // 1 Stunde

    public function handle(WP_REST_Request $request, callable $next): WP_REST_Response {
        $clientId = $this->getClientId($request);
        $key = "rate_limit_{$clientId}";

        $current = (int) get_transient($key);

        if ($current >= self::DEFAULT_LIMIT) {
            return new WP_REST_Response([
                'error' => 'Rate limit exceeded',
                'retry_after' => self::DEFAULT_WINDOW
            ], 429);
        }

        set_transient($key, $current + 1, self::DEFAULT_WINDOW);

        return $next($request);
    }
}

class AuthenticationMiddleware {
    public function handle(WP_REST_Request $request, callable $next): WP_REST_Response {
        if (!$this->isAuthenticated($request)) {
            return new WP_REST_Response([
                'error' => 'Authentication required'
            ], 401);
        }

        return $next($request);
    }
}
```

## Caching-System

### 1. Cache-Architektur

```php
namespace ImmoBridge\Utils\Cache;

interface CacheInterface {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): bool;
    public function delete(string $key): bool;
    public function flush(): bool;
    public function remember(string $key, callable $callback, int $ttl = 3600): mixed;
}

class WordPressCacheAdapter implements CacheInterface {
    private const CACHE_GROUP = 'immobridge';

    public function get(string $key): mixed {
        return wp_cache_get($key, self::CACHE_GROUP);
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool {
        return wp_cache_set($key, $value, self::CACHE_GROUP, $ttl);
    }

    public function remember(string $key, callable $callback, int $ttl = 3600): mixed {
        $value = $this->get($key);

        if ($value !== false) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }
}

class PropertyCacheManager {
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger
    ) {}

    public function cacheProperty(Property $property): void {
        $key = "property_{$property->id}";
        $this->cache->set($key, $property, 3600);

        // Cache auch für OpenImmo-ID
        $openImmoKey = "property_openimmo_{$property->openImmoId}";
        $this->cache->set($openImmoKey, $property->id, 3600);
    }

    public function invalidateProperty(int $propertyId): void {
        $this->cache->delete("property_{$propertyId}");

        // Invalidiere auch verwandte Caches
        $this->cache->delete("property_search_*");
        $this->cache->delete("property_count_*");
    }
}
```

### 2. Query-Caching

```php
class QueryCacheManager {
    private const CACHE_TTL = 1800; // 30 Minuten

    public function cacheQuery(string $queryHash, mixed $result): void {
        $key = "query_{$queryHash}";
        $this->cache->set($key, $result, self::CACHE_TTL);
    }

    public function getCachedQuery(string $queryHash): mixed {
        $key = "query_{$queryHash}";
        return $this->cache->get($key);
    }

    public function generateQueryHash(SearchCriteria $criteria): string {
        return md5(serialize($criteria->toArray()));
    }
}
```

## Event-System

### 1. Event-Dispatcher

```php
namespace ImmoBridge\Events;

interface EventDispatcherInterface {
    public function dispatch(EventInterface $event): void;
    public function addListener(string $eventName, callable $listener, int $priority = 10): void;
    public function removeListener(string $eventName, callable $listener): void;
}

class WordPressEventDispatcher implements EventDispatcherInterface {
    public function dispatch(EventInterface $event): void {
        $eventName = $event->getName();
        do_action("immobridge_{$eventName}", $event);
    }

    public function addListener(string $eventName, callable $listener, int $priority = 10): void {
        add_action("immobridge_{$eventName}", $listener, $priority);
    }
}
```

### 2. Event-Definitionen

```php
namespace ImmoBridge\Events;

class PropertyImportedEvent implements EventInterface {
    public function __construct(
        public readonly Property $property,
        public readonly ImportContext $context
    ) {}

    public function getName(): string {
        return 'property_imported';
    }
}

class BatchProcessedEvent implements EventInterface {
    public function __construct(
        public readonly int $batchIndex,
        public readonly BatchResult $result
    ) {}

    public function getName(): string {
        return 'batch_processed';
    }
}

class ImportCompletedEvent implements EventInterface {
    public function __construct(
        public readonly ImportResult $result,
        public readonly ImportStatistics $statistics
    ) {}

    public function getName(): string {
        return 'import_completed';
    }
}
```

## Bricks Builder Integration

### 1. Custom Elements

```php
namespace ImmoBridge\Integration\Builders\Bricks;

class PropertyListElement extends \Bricks\Element {
    public $category = 'immobridge';
    public $name = 'property-list';
    public $icon = 'fas fa-home';
    public $css_selector = '.immobridge-property-list';

    public function get_label(): string {
        return __('Property List', 'immobridge');
    }

    public function set_controls(): void {
        // Query Controls
        $this->controls['query_type'] = [
            'tab' => 'content',
            'group' => 'query',
            'label' => __('Query Type', 'immobridge'),
            'type' => 'select',
            'options' => [
                'recent' => __('Recent Properties', 'immobridge'),
                'featured' => __('Featured Properties', 'immobridge'),
                'by_type' => __('By Property Type', 'immobridge'),
                'by_location' => __('By Location', 'immobridge'),
                'custom' => __('Custom Query', 'immobridge'),
            ],
            'default' => 'recent',
        ];

        // Layout Controls
        $this->controls['layout'] = [
            'tab' => 'content',
            'group' => 'layout',
            'label' => __('Layout', 'immobridge'),
            'type' => 'select',
            'options' => [
                'grid' => __('Grid', 'immobridge'),
                'list' => __('List', 'immobridge'),
                'carousel' => __('Carousel', 'immobridge'),
            ],
            'default' => 'grid',
        ];

        // Display Controls
        $this->controls['show_price'] = [
            'tab' => 'content',
            'group' => 'display',
            'label' => __('Show Price', 'immobridge'),
            'type' => 'checkbox',
            'default' => true,
        ];
    }

    public function render(): void {
        $settings = $this->settings;
        $properties = $this->getProperties($settings);

        echo "<div {$this->render_attributes('_root')}>";

        foreach ($properties as $property) {
            $this->renderProperty($property, $settings);
        }

        echo '</div>';
    }

    private function getProperties(array $settings): PropertyCollection {
        $criteria = $this->buildSearchCriteria($settings);
        $repository = Container::getInstance()->get(PropertyRepositoryInterface::class);

        return $repository->findByCriteria($criteria);
    }
}

class PropertyDetailElement extends \Bricks\Element {
    public $category = 'immobridge';
    public $name = 'property-detail';
    public $icon = 'fas fa-info-circle';

    public function get_label(): string {
        return __('Property Details', 'immobridge');
    }

    public function set_controls(): void {
        $this->controls['property_id'] = [
            'tab' => 'content',
            'label' => __('Property ID', 'immobridge'),
            'type' => 'number',
            'description' => __('Leave empty to use current post ID', 'immobridge'),
        ];

        $this->controls['fields'] = [
            'tab' => 'content',
            'label' => __('Fields to Display', 'immobridge'),
            'type' => 'checkbox',
            'options' => [
                'price' => __('Price', 'immobridge'),
                'location' => __('Location', 'immobridge'),
                'specifications' => __('Specifications', 'immobridge'),
                'features' => __('Features', 'immobridge'),
                'contact' => __('Contact', 'immobridge'),
            ],
            'multiple' => true,
            'default' => ['price', 'location', 'specifications'],
        ];
    }
}
```

### 2. Dynamic Data Tags

```php
namespace ImmoBridge\Integration\Builders\Bricks;

class PropertyDynamicData {
    private const TAG_PREFIX = 'property_';

    public function __construct(
        private readonly PropertyRepositoryInterface $repository
    ) {}

    public function register(): void {
        add_filter('bricks/dynamic_data/render_tag', [$this, 'render_tag'], 10, 3);
        add_filter('bricks/dynamic_data/render_content', [$this, 'render_content'], 10, 2);
        add_filter('bricks/dynamic_data/get_tags', [$this, 'get_tags']);
    }

    public function get_tags(): array {
        return [
            'property_title' => __('Property Title', 'immobridge'),
            'property_price' => __('Property Price', 'immobridge'),
            'property_price_formatted' => __('Property Price (Formatted)', 'immobridge'),
            'property_location' => __('Property Location', 'immobridge'),
            'property_address' => __('Full Address', 'immobridge'),
            'property_rooms' => __('Number of Rooms', 'immobridge'),
            'property_area' => __('Living Area', 'immobridge'),
            'property_type' => __('Property Type', 'immobridge'),
            'property_status' => __('Property Status', 'immobridge'),
            'property_features' => __('Property Features', 'immobridge'),
            'property_contact_name' => __('Contact Name', 'immobridge'),
            'property_contact_phone' => __('Contact Phone', 'immobridge'),
            'property_contact_email' => __('Contact Email', 'immobridge'),
        ];
    }

    public function render_tag(string $tag, \Bricks\Post $post, string $context): string {
        if (strpos($tag, self::TAG_PREFIX) !== 0) {
            return $tag;
        }

        $property = $this->repository->find($post->ID);

        if (!$property) {
            return '';
        }

        $field = substr($tag, strlen(self::TAG_PREFIX));

        return match ($field) {
            'title' => $property->title,
            'price' => (string) $property->price->amount,
            'price_formatted' => $this->formatPrice($property->price),
            'location' => $property->location->city,
            'address' => $property->location->getFullAddress(),
            'rooms' => (string) ($property->specifications->rooms ?? ''),
            'area' => (string) ($property->specifications->livingArea ?? ''),
            'type' => $property->type->value,
            'status' => $property->status->value,
            'features' => $this->formatFeatures($property->features),
            'contact_name' => $property->contact->name,
            'contact_phone' => $property->contact->phone,
            'contact_email' => $property->contact->email,
            default => ''
        };
    }
}
```

## Admin-Interface

### 1. Admin-Controller

```php
namespace ImmoBridge\Admin\Controllers;

class AdminController {
    public function __construct(
        private readonly Container $container,
        private readonly NonceManager $nonceManager
    ) {}

    public function register(): void {
        add_action('admin_menu', [$this, 'addAdminMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addAdminMenu(): void {
        add_menu_page(
            __('ImmoBridge', 'immobridge'),
            __('ImmoBridge', 'immobridge'),
            'manage_options',
            'immobridge',
            [$this, 'renderDashboard'],
            'dashicons-building',
            30
        );

        add_submenu_page(
            'immobridge',
            __('Import', 'immobridge'),
            __('Import', 'immobridge'),
            'manage_options',
            'immobridge-import',
            [$this, 'renderImportPage']
        );

        add_submenu_page(
            'immobridge',
            __('Settings', 'immobridge'),
            __('Settings', 'immobridge'),
            'manage_options',
            'immobridge-settings',
            [$this, 'renderSettingsPage']
        );
    }

    public function renderDashboard(): void {
        $statistics = $this->container->get(StatisticsService::class);
        $recentImports = $this->container->get(ImportHistoryService::class);

        include IMMOBRIDGE_PLUGIN_DIR . 'templates/admin/dashboard.php';
    }
}
```

### 2. Settings-API

```php
namespace ImmoBridge\Admin\Settings;

class SettingsManager {
    private const OPTION_GROUP = 'immobridge_settings';
    private const OPTION_NAME = 'immobridge_options';

    public function register(): void {
        register_setting(
            self::OPTION_GROUP,
            self::OPTION_NAME,
            [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitizeSettings'],
                'default' => $this->getDefaultSettings(),
            ]
        );

        $this->registerSections();
        $this->registerFields();
    }

    private function registerSections(): void {
        add_settings_section(
            'general',
            __('General Settings', 'immobridge'),
            [$this, 'renderGeneralSection'],
            'immobridge-settings'
        );

        add_settings_section(
            'import',
            __('Import Settings', 'immobridge'),
            [$this, 'renderImportSection'],
            'immobridge-settings'
        );
    }

    public function sanitizeSettings(array $input): array {
        $sanitized = [];

        // Import-Einstellungen
        $sanitized['import_schedule'] = sanitize_text_field($input['import_schedule'] ?? 'manual');
        $sanitized['batch_size'] = absint($input['batch_size'] ?? 50);
        $sanitized['auto_publish'] = (bool) ($input['auto_publish'] ?? false);

        // API-Einstellungen
        $sanitized['api_enabled'] = (bool) ($input['api_enabled'] ?? true);
        $sanitized['api_rate_limit'] = absint($input['api_rate_limit'] ?? 100);

        // Cache-Einstellungen
        $sanitized['cache_enabled'] = (bool) ($input['cache_enabled'] ?? true);
        $sanitized['cache_ttl'] = absint($input['cache_ttl'] ?? 3600);

        return $sanitized;
    }
}
```

## Sicherheits-Framework

### 1. Input-Validierung

```php
namespace ImmoBridge\Utils\Validator;

class InputValidator {
    private array $rules = [];

    public function validate(array $data, array $rules): ValidationResult {
        $errors = [];

        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;

            foreach ($fieldRules as $rule) {
                if (!$this->validateRule($value, $rule)) {
                    $errors[$field][] = $this->getErrorMessage($field, $rule);
                }
            }
        }

        return empty($errors)
            ? ValidationResult::success()
            : ValidationResult::failure($errors);
    }

    private function validateRule(mixed $value, string $rule): bool {
        return match ($rule) {
            'required' => !empty($value),
            'string' => is_string($value),
            'numeric' => is_numeric($value),
            'email' => is_email($value),
            'url' => filter_var($value, FILTER_VALIDATE_URL) !== false,
            default => $this->validateCustomRule($value, $rule)
        };
    }
}

class PropertyValidator {
    public function __construct(
        private readonly InputValidator $validator
    ) {}

    public function validate(array $data): ValidationResult {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'openimmo_id' => ['required', 'string', 'unique:openimmo_id'],
            'price.amount' => ['required', 'numeric', 'min:0'],
            'price.currency' => ['required', 'enum:Currency'],
            'location.postal_code' => ['required', 'regex:/^\d{5}$/'],
            'location.city' => ['required', 'string', 'max:100'],
            'property_type' => ['required', 'enum:PropertyType'],
            'property_status' => ['required', 'enum:PropertyStatus'],
        ];

        return $this->validator->validate($data, $rules);
    }
}
```

### 2. Output-Sanitization

```php
namespace ImmoBridge\Utils\Sanitizer;

class OutputSanitizer {
    public static function html(string $content): string {
        return wp_kses_post($content);
    }

    public static function text(string $content): string {
        return esc_html($content);
    }

    public static function attribute(string $content): string {
        return esc_attr($content);
    }

    public static function url(string $url): string {
        return esc_url($url);
    }

    public static function json(mixed $data): string {
        return wp_json_encode($data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    }
}
```

### 3. Nonce-Management

```php
namespace ImmoBridge\Admin\Security;

class NonceManager {
    private const NONCE_ACTION = 'immobridge_admin';
    private const NONCE_FIELD = '_immobridge_nonce';

    public function create(string $action): string {
        return wp_create_nonce(self::NONCE_ACTION . '_' . $action);
    }

    public function verify(string $nonce, string $action): bool {
        return wp_verify_nonce($nonce, self::NONCE_ACTION . '_' . $action);
    }

    public function field(string $action): string {
        return wp_nonce_field(
            self::NONCE_ACTION . '_' . $action,
            self::NONCE_FIELD,
            true,
            false
        );
    }

    public function verifyRequest(WP_REST_Request $request, string $action): bool {
        $nonce = $request->get_header('X-WP-Nonce');
        return $this->verify($nonce, $action);
    }
}
```

## Performance-Optimierung

### 1. Database-Schema

```sql
-- Optimierte Meta-Tabelle für Properties
CREATE TABLE IF NOT EXISTS wp_immobridge_property_meta (
    meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    property_id bigint(20) unsigned NOT NULL,
    meta_key varchar(255) NOT NULL,
    meta_value longtext,
    PRIMARY KEY (meta_id),
    KEY property_id (property_id),
    KEY meta_key (meta_key(191)),
    KEY meta_key_value (meta_key(191), meta_value(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optimierte Indizes für häufige Abfragen
CREATE INDEX idx_property_type ON wp_immobridge_property_meta (meta_key, meta_value(50))
WHERE meta_key = 'property_type';

CREATE INDEX idx_property_price ON wp_immobridge_property_meta (meta_key, CAST(meta_value AS DECIMAL(15,2)))
WHERE meta_key = 'property_price';

CREATE INDEX idx_property_location ON wp_immobridge_property_meta (meta_key, meta_value(100))
WHERE meta_key IN ('property_city', 'property_postal_code', 'property_state');

-- Volltext-Index für Suche
CREATE FULLTEXT INDEX idx_property_search ON wp_posts (post_title, post_content)
WHERE post_type = 'immo_property';
```

### 2. Query-Optimierung

```php
namespace ImmoBridge\Data\Repositories;

class OptimizedPropertyRepository extends WordPressPropertyRepository {
    public function findByCriteria(SearchCriteria $criteria): PropertyCollection {
        $cacheKey = $this->generateCacheKey($criteria);

        if ($cached = $this->cache->get($cacheKey)) {
            return $cached;
        }

        // Optimierte Query mit minimalen Meta-Abfragen
        $queryArgs = [
            'post_type' => PropertyPostType::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $criteria->getLimit(),
            'offset' => $criteria->getOffset(),
            'orderby' => $criteria->getOrderBy(),
            'order' => $criteria->getOrder(),
            'no_found_rows' => !$criteria->needsTotal(),
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
            'suppress_filters' => false,
        ];

        // Verwende Custom Meta-Tabelle für bessere Performance
        if ($criteria->hasMetaQuery()) {
            $queryArgs['meta_query'] = $this->buildOptimizedMetaQuery($criteria);
        }

        $query = new WP_Query($queryArgs);
        $properties = $this->hydrator->hydrateCollection($query->posts);

        $this->cache->set($cacheKey, $properties, 1800);

        return $properties;
    }

    private function buildOptimizedMetaQuery(SearchCriteria $criteria): array {
        $metaQuery = ['relation' => 'AND'];

        // Verwende EXISTS-Queries für bessere Performance
        foreach ($criteria->getMetaFilters() as $filter) {
            $metaQuery[] = [
                'key' => $filter->getKey(),
                'value' => $filter->getValue(),
                'compare' => $filter->getOperator(),
                'type' => $filter->getType(),
            ];
        }

        return $metaQuery;
    }
}
```

## Testing-Framework

### 1. Unit-Tests

```php
namespace ImmoBridge\Tests\Unit\Data\Models;

class PropertyTest extends TestCase {
    public function testPropertyCreation(): void {
        $property = new Property(
            id: 1,
            openImmoId: 'TEST_001',
            title: 'Test Property',
            description: 'Test Description',
            type: PropertyType::RESIDENTIAL,
            status: PropertyStatus::AVAILABLE,
            category: PropertyCategory::APARTMENT,
            price: new Price(250000, Currency::EUR, PriceType::PURCHASE),
            location: new Location('Test St', '123', 'Test City', '12345', 'Test State', Country::GERMANY),
            specifications: new Specifications(livingArea: 120.5, rooms: 4),
            contact: new Contact('John Doe', 'john@example.com', '+49123456789'),
            images: new PropertyImages([]),
            documents: new PropertyDocuments([]),
            features: new PropertyFeatures([]),
            createdAt: new DateTime(),
            updatedAt: new DateTime()
        );

        $this->assertEquals(1, $property->id);
        $this->assertEquals('TEST_001', $property->openImmoId);
        $this->assertEquals(PropertyType::RESIDENTIAL, $property->type);
    }

    public function testPriceValidation(): void {
        $this->expectException(InvalidArgumentException::class);

        new Price(-1000, Currency::EUR, PriceType::PURCHASE);
    }
}
```

### 2. Integration-Tests

```php
namespace ImmoBridge\Tests\Integration\Import;

class ImportProcessTest extends WP_UnitTestCase {
    private Container $container;
    private ImportService $importService;

    protected function setUp(): void {
        parent::setUp();

        $this->container = new Container();
        $this->setupContainer();
        $this->importService = $this->container->get(ImportService::class);
    }

    public function testCompleteImportProcess(): void {
        $xmlFile = $this->getTestXmlFile();

        $result = $this->importService->import($xmlFile);

        $this->assertTrue($result->isSuccess());
        $this->assertCount(5, $result->getImportedProperties());

        // Verify database state
        $properties = get_posts([
            'post_type' => PropertyPostType::POST_TYPE,
            'numberposts' => -1,
        ]);

        $this->assertCount(5, $properties);
    }

    public function testImportWithInvalidXml(): void {
        $invalidXmlFile = $this->getInvalidXmlFile();

        $this->expectException(InvalidXmlException::class);
        $this->importService->import($invalidXmlFile);
    }
}
```

## Konfiguration & Deployment

### 1. Composer-Konfiguration

```json
{
    "name": "immobridge/immobridge",
    "description": "Modern WordPress plugin for OpenImmo XML integration",
    "type": "wordpress-plugin",
    "license
```
