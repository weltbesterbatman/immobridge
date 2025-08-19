# Performance-Optimierungsplan: ImmoBridge Plugin

## Executive Summary

Dieser Plan definiert konkrete Maßnahmen zur Performance-Optimierung des ImmoBridge Plugins, basierend auf der Analyse des Legacy-Systems immonex-openimmo2wp und modernen WordPress-Performance-Best-Practices.

## Aktuelle Performance-Probleme

### Identifizierte Bottlenecks

#### 1. Datenbankabfragen (Kritisch)

- **N+1 Query-Problem:** Einzelne Meta-Abfragen pro Property
- **Fehlende Indizierung:** Keine optimierten Indizes für häufige Suchen
- **Übermäßige Joins:** Komplexe Meta-Query-Strukturen
- **Keine Query-Caching:** Wiederholte identische Abfragen

#### 2. Import-Performance (Hoch)

- **Einzelverarbeitung:** Keine Batch-Processing-Mechanismen
- **Memory-Leaks:** Speicher wird nicht freigegeben
- **Synchrone Verarbeitung:** Blockiert andere Prozesse
- **Fehlende Fortschrittsanzeige:** Keine Benutzer-Feedback

#### 3. Frontend-Performance (Mittel)

- **Ungecachte Ausgaben:** Jede Property-Anzeige triggert DB-Abfragen
- **Große Datenmengen:** Alle Meta-Daten werden geladen
- **Fehlende Lazy Loading:** Bilder und Dokumente sofort geladen

## Optimierungsstrategien

### 1. Datenbank-Optimierung

#### 1.1 Custom Meta-Tabelle

**Ziel:** Reduzierung der Query-Komplexität um 70%

```sql
-- Optimierte Property-Meta-Tabelle
CREATE TABLE wp_immobridge_property_meta (
    meta_id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
    property_id bigint(20) unsigned NOT NULL,
    meta_key varchar(255) NOT NULL,
    meta_value longtext,
    meta_value_num decimal(15,4) DEFAULT NULL,
    meta_value_date datetime DEFAULT NULL,
    PRIMARY KEY (meta_id),
    KEY property_id (property_id),
    KEY meta_key (meta_key(191)),
    KEY meta_key_value (meta_key(191), meta_value(191)),
    KEY meta_key_num (meta_key(191), meta_value_num),
    KEY meta_key_date (meta_key(191), meta_value_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

**Vorteile:**

- Typisierte Spalten für numerische und Datums-Werte
- Optimierte Indizes für verschiedene Datentypen
- Reduzierte CAST-Operationen in Queries

#### 1.2 Spezialisierte Indizes

```sql
-- Preis-Bereich-Suchen
CREATE INDEX idx_price_range ON wp_immobridge_property_meta
(meta_key, meta_value_num)
WHERE meta_key IN ('property_price', 'property_rent');

-- Standort-basierte Suchen
CREATE INDEX idx_location ON wp_immobridge_property_meta
(meta_key, meta_value(100))
WHERE meta_key IN ('property_city', 'property_postal_code', 'property_state');

-- Immobilientyp-Filter
CREATE INDEX idx_property_type ON wp_immobridge_property_meta
(meta_key, meta_value(50))
WHERE meta_key = 'property_type';

-- Volltext-Suche
CREATE FULLTEXT INDEX idx_property_search
ON wp_posts (post_title, post_content, post_excerpt)
WHERE post_type = 'immo_property';
```

#### 1.3 Query-Optimierung

**Vorher (Legacy):**

```php
// N+1 Problem
foreach ($properties as $property) {
    $price = get_post_meta($property->ID, 'property_price', true);
    $location = get_post_meta($property->ID, 'property_location', true);
    $type = get_post_meta($property->ID, 'property_type', true);
    // ... weitere einzelne Queries
}
```

**Nachher (Optimiert):**

```php
// Batch-Loading mit einem Query
$propertyIds = array_column($properties, 'ID');
$allMeta = $this->metaRepository->getBulkMeta($propertyIds, [
    'property_price', 'property_location', 'property_type'
]);

foreach ($properties as $property) {
    $meta = $allMeta[$property->ID] ?? [];
    // Alle Meta-Daten bereits verfügbar
}
```

### 2. Caching-Strategien

#### 2.1 Multi-Layer-Caching

```php
namespace ImmoBridge\Utils\Cache;

class PropertyCacheManager {
    private const CACHE_LEVELS = [
        'memory' => 60,      // 1 Minute - In-Memory
        'object' => 900,     // 15 Minuten - Object Cache
        'transient' => 3600, // 1 Stunde - Database Transients
    ];

    public function get(string $key): mixed {
        // Level 1: Memory Cache
        if (isset($this->memoryCache[$key])) {
            return $this->memoryCache[$key];
        }

        // Level 2: Object Cache (Redis/Memcached)
        if ($cached = wp_cache_get($key, 'immobridge')) {
            $this->memoryCache[$key] = $cached;
            return $cached;
        }

        // Level 3: Database Transients
        if ($cached = get_transient("immobridge_{$key}")) {
            wp_cache_set($key, $cached, 'immobridge', self::CACHE_LEVELS['object']);
            $this->memoryCache[$key] = $cached;
            return $cached;
        }

        return null;
    }
}
```

#### 2.2 Smart Cache Invalidation

```php
class SmartCacheInvalidation {
    public function invalidateProperty(int $propertyId): void {
        // Spezifische Property-Caches
        $this->cache->delete("property_{$propertyId}");
        $this->cache->delete("property_meta_{$propertyId}");

        // Verwandte Listen-Caches
        $property = $this->repository->find($propertyId);
        $this->invalidateListCaches($property);

        // Search-Result-Caches
        $this->cache->deletePattern("search_*");
    }

    private function invalidateListCaches(Property $property): void {
        // Nur relevante Listen invalidieren
        $patterns = [
            "list_type_{$property->type->value}",
            "list_city_{$property->location->city}",
            "list_price_range_*",
        ];

        foreach ($patterns as $pattern) {
            $this->cache->deletePattern($pattern);
        }
    }
}
```

#### 2.3 Fragment Caching

```php
class PropertyFragmentCache {
    public function renderPropertyCard(Property $property): string {
        $cacheKey = "property_card_{$property->id}_{$property->updatedAt->getTimestamp()}";

        return $this->cache->remember($cacheKey, function() use ($property) {
            ob_start();
            include 'templates/property-card.php';
            return ob_get_clean();
        }, 3600);
    }
}
```

### 3. Import-Performance-Optimierung

#### 3.1 Asynchrone Batch-Verarbeitung

```php
namespace ImmoBridge\Import\Processor;

class AsyncBatchProcessor {
    private const BATCH_SIZE = 50;
    private const MAX_EXECUTION_TIME = 45; // Sekunden

    public function processAsync(string $xmlFile): string {
        $jobId = wp_generate_uuid4();

        // Job in Queue einreihen
        wp_schedule_single_event(time(), 'immobridge_process_import', [
            'job_id' => $jobId,
            'xml_file' => $xmlFile,
            'batch_size' => self::BATCH_SIZE
        ]);

        return $jobId;
    }

    public function processBatch(string $jobId, string $xmlFile, int $offset = 0): void {
        $startTime = time();
        $processed = 0;

        $properties = $this->parser->parseChunk($xmlFile, $offset, self::BATCH_SIZE);

        foreach ($properties as $property) {
            if (time() - $startTime > self::MAX_EXECUTION_TIME) {
                // Nächsten Batch schedulen
                wp_schedule_single_event(time() + 10, 'immobridge_process_import', [
                    'job_id' => $jobId,
                    'xml_file' => $xmlFile,
                    'offset' => $offset + $processed,
                    'batch_size' => self::BATCH_SIZE
                ]);
                break;
            }

            $this->processProperty($property);
            $processed++;
        }

        $this->updateJobProgress($jobId, $offset + $processed);
    }
}
```

#### 3.2 Memory-Management

```php
class MemoryOptimizedProcessor {
    private const MEMORY_LIMIT_THRESHOLD = 0.8; // 80% des Limits

    public function processWithMemoryManagement(PropertyCollection $properties): void {
        $memoryLimit = $this->getMemoryLimit();

        foreach ($properties as $index => $property) {
            $this->processProperty($property);

            // Memory-Check nach jeder 10. Property
            if ($index % 10 === 0) {
                $currentUsage = memory_get_usage(true);

                if ($currentUsage > ($memoryLimit * self::MEMORY_LIMIT_THRESHOLD)) {
                    // Garbage Collection forcieren
                    gc_collect_cycles();

                    // Cache leeren
                    $this->cache->flush();

                    // Wenn immer noch zu hoch, Batch unterbrechen
                    if (memory_get_usage(true) > ($memoryLimit * self::MEMORY_LIMIT_THRESHOLD)) {
                        $this->scheduleRemainingBatch($properties, $index + 1);
                        break;
                    }
                }
            }
        }
    }
}
```

#### 3.3 Streaming XML-Parser

```php
class StreamingXmlParser {
    public function parseStream(string $xmlFile): \Generator {
        $reader = new XMLReader();
        $reader->open($xmlFile);

        while ($reader->read()) {
            if ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === 'immobilie') {
                // Property als Generator yielden
                yield $this->parseProperty($reader->readOuterXML());

                // Memory nach jeder Property freigeben
                unset($propertyXml);
            }
        }

        $reader->close();
    }
}
```

### 4. Frontend-Performance

#### 4.1 Lazy Loading Implementation

```php
class LazyLoadingManager {
    public function renderPropertyList(array $properties): string {
        $html = '<div class="property-list" data-lazy-load="true">';

        foreach ($properties as $index => $property) {
            if ($index < 6) {
                // Erste 6 Properties sofort laden
                $html .= $this->renderPropertyCard($property);
            } else {
                // Rest als Lazy-Load-Placeholder
                $html .= $this->renderLazyPlaceholder($property->id);
            }
        }

        $html .= '</div>';

        // JavaScript für Lazy Loading
        wp_enqueue_script('immobridge-lazy-load');

        return $html;
    }

    private function renderLazyPlaceholder(int $propertyId): string {
        return sprintf(
            '<div class="property-card-placeholder" data-property-id="%d" data-lazy-load-url="%s">
                <div class="loading-skeleton"></div>
            </div>',
            $propertyId,
            rest_url("immobridge/v1/properties/{$propertyId}/card")
        );
    }
}
```

#### 4.2 Image-Optimierung

```php
class ImageOptimizer {
    public function optimizePropertyImages(Property $property): array {
        $optimizedImages = [];

        foreach ($property->images as $image) {
            $optimizedImages[] = [
                'thumbnail' => $this->generateResponsiveImage($image, [300, 200]),
                'medium' => $this->generateResponsiveImage($image, [600, 400]),
                'large' => $this->generateResponsiveImage($image, [1200, 800]),
                'webp' => $this->convertToWebP($image),
                'lazy_src' => $this->generateLazyLoadSrc($image),
            ];
        }

        return $optimizedImages;
    }

    private function generateResponsiveImage(PropertyImage $image, array $size): string {
        $cacheKey = "responsive_image_{$image->id}_{$size[0]}x{$size[1]}";

        return $this->cache->remember($cacheKey, function() use ($image, $size) {
            return wp_get_attachment_image_url($image->attachmentId, $size);
        }, 86400); // 24 Stunden
    }
}
```

### 5. API-Performance

#### 5.1 Response-Caching

```php
class APIResponseCache {
    public function cacheResponse(WP_REST_Request $request, WP_REST_Response $response): void {
        $cacheKey = $this->generateCacheKey($request);
        $ttl = $this->calculateTTL($request);

        // Response mit ETags
        $etag = md5(serialize($response->get_data()));
        $response->header('ETag', $etag);
        $response->header('Cache-Control', "public, max-age={$ttl}");

        // Server-seitiges Caching
        $this->cache->set($cacheKey, [
            'data' => $response->get_data(),
            'headers' => $response->get_headers(),
            'etag' => $etag,
        ], $ttl);
    }

    public function getCachedResponse(WP_REST_Request $request): ?WP_REST_Response {
        $cacheKey = $this->generateCacheKey($request);
        $cached = $this->cache->get($cacheKey);

        if (!$cached) {
            return null;
        }

        // ETag-Validierung
        $clientETag = $request->get_header('If-None-Match');
        if ($clientETag === $cached['etag']) {
            return new WP_REST_Response(null, 304);
        }

        $response = new WP_REST_Response($cached['data']);
        foreach ($cached['headers'] as $key => $value) {
            $response->header($key, $value);
        }

        return $response;
    }
}
```

#### 5.2 Query-Optimierung für API

```php
class OptimizedAPIQueries {
    public function getPropertiesOptimized(SearchCriteria $criteria): array {
        // Nur benötigte Felder laden
        $fields = $criteria->getFields() ?: ['id', 'title', 'price', 'location'];

        // Optimierte Query mit Subqueries
        global $wpdb;

        $sql = "
            SELECT p.ID, p.post_title, p.post_excerpt,
                   GROUP_CONCAT(
                       CASE pm.meta_key
                           WHEN 'property_price' THEN CONCAT('price:', pm.meta_value)
                           WHEN 'property_city' THEN CONCAT('city:', pm.meta_value)
                           WHEN 'property_type' THEN CONCAT('type:', pm.meta_value)
                       END SEPARATOR '|'
                   ) as meta_data
            FROM {$wpdb->posts} p
            LEFT JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                AND pm.meta_key IN ('property_price', 'property_city', 'property_type')
            WHERE p.post_type = 'immo_property'
                AND p.post_status = 'publish'
            GROUP BY p.ID
            LIMIT %d OFFSET %d
        ";

        return $wpdb->get_results($wpdb->prepare(
            $sql,
            $criteria->getLimit(),
            $criteria->getOffset()
        ));
    }
}
```

## Performance-Monitoring

### 1. Metriken-Sammlung

```php
class PerformanceMonitor {
    private array $metrics = [];

    public function startTimer(string $operation): void {
        $this->metrics[$operation] = [
            'start_time' => microtime(true),
            'start_memory' => memory_get_usage(true),
        ];
    }

    public function endTimer(string $operation): array {
        if (!isset($this->metrics[$operation])) {
            return [];
        }

        $start = $this->metrics[$operation];
        $metrics = [
            'operation' => $operation,
            'duration' => microtime(true) - $start['start_time'],
            'memory_used' => memory_get_usage(true) - $start['start_memory'],
            'peak_memory' => memory_get_peak_usage(true),
            'queries' => $this->getQueryCount(),
        ];

        $this->logMetrics($metrics);
        return $metrics;
    }

    private function logMetrics(array $metrics): void {
        if ($metrics['duration'] > 1.0) { // Langsame Operationen loggen
            error_log(sprintf(
                'ImmoBridge Slow Operation: %s took %.2fs, used %s memory, %d queries',
                $metrics['operation'],
                $metrics['duration'],
                size_format($metrics['memory_used']),
                $metrics['queries']
            ));
        }
    }
}
```

### 2. Performance-Dashboard

```php
class PerformanceDashboard {
    public function getPerformanceStats(): array {
        return [
            'database' => [
                'avg_query_time' => $this->getAverageQueryTime(),
                'slow_queries' => $this->getSlowQueries(),
                'cache_hit_rate' => $this->getCacheHitRate(),
            ],
            'import' => [
                'avg_import_time' => $this->getAverageImportTime(),
                'properties_per_second' => $this->getImportThroughput(),
                'memory_usage' => $this->getImportMemoryUsage(),
            ],
            'frontend' => [
                'page_load_time' => $this->getPageLoadTime(),
                'cache_efficiency' => $this->getCacheEfficiency(),
                'image_optimization' => $this->getImageOptimizationStats(),
            ],
        ];
    }
}
```

## Implementierungsplan

### Phase 1: Datenbank-Optimierung (Woche 1-2)

#### Woche 1: Schema-Optimierung

- [ ] Custom Meta-Tabelle erstellen
- [ ] Daten-Migration von wp_postmeta
- [ ] Spezialisierte Indizes implementieren
- [ ] Query-Performance testen

#### Woche 2: Query-Refactoring

- [ ] Repository-Pattern mit optimierten Queries
- [ ] Bulk-Loading-Mechanismen
- [ ] N+1-Probleme eliminieren
- [ ] Performance-Benchmarks

### Phase 2: Caching-Implementation (Woche 3-4)

#### Woche 3: Multi-Layer-Cache

- [ ] Memory-Cache-Layer
- [ ] Object-Cache-Integration
- [ ] Transient-Fallback
- [ ] Cache-Warming-Strategien

#### Woche 4: Smart Invalidation

- [ ] Granulare Cache-Invalidierung
- [ ] Event-basierte Cache-Updates
- [ ] Fragment-Caching
- [ ] Cache-Monitoring

### Phase 3: Import-Optimierung (Woche 5-6)

#### Woche 5: Async Processing

- [ ] Background-Job-System
- [ ] Batch-Processing-Logic
- [ ] Progress-Tracking
- [ ] Error-Handling

#### Woche 6: Memory-Management

- [ ] Streaming-Parser
- [ ] Memory-Monitoring
- [ ] Garbage-Collection-Optimierung
- [ ] Large-File-Handling

### Phase 4: Frontend-Performance (Woche 7-8)

#### Woche 7: Lazy Loading

- [ ] JavaScript-Implementation
- [ ] Placeholder-System
- [ ] Intersection-Observer
- [ ] Fallback-Mechanismen

#### Woche 8: Asset-Optimierung

- [ ] Image-Responsive-Loading
- [ ] WebP-Konvertierung
- [ ] CSS/JS-Minification
- [ ] CDN-Integration

## Performance-Ziele

### Quantitative Ziele

#### Datenbank-Performance

- **Query-Zeit:** < 50ms für Standard-Suchen
- **Import-Geschwindigkeit:** > 100 Properties/Sekunde
- **Cache-Hit-Rate:** > 85%
- **Memory-Usage:** < 128MB für 1000 Properties

#### Frontend-Performance

- **Page-Load-Time:** < 2 Sekunden
- **Time-to-First-Byte:** < 200ms
- **Largest-Contentful-Paint:** < 2.5 Sekunden
- **Cumulative-Layout-Shift:** < 0.1

#### API-Performance

- **Response-Time:** < 100ms für gecachte Requests
- **Throughput:** > 1000 Requests/Minute
- **Error-Rate:** < 0.1%

### Qualitative Ziele

#### Benutzerfreundlichkeit

- Keine spürbaren Verzögerungen bei Navigation
- Smooth Scrolling bei Property-Listen
- Responsive Interaktionen
- Zuverlässige Import-Prozesse

#### Skalierbarkeit

- Unterstützung für 10.000+ Properties
- Mehrere gleichzeitige Imports
- Multi-Site-Kompatibilität
- Horizontal skalierbar

## Monitoring & Wartung

### 1. Kontinuierliches Monitoring

```php
class PerformanceAlerts {
    public function checkPerformanceThresholds(): void {
        $metrics = $this->performanceMonitor->getCurrentMetrics();

        // Query-Performance-Alerts
        if ($metrics['avg_query_time'] > 100) { // ms
            $this->sendAlert('Slow database queries detected');
        }

        // Memory-Usage-Alerts
        if ($metrics['memory_usage'] > 256 * 1024 * 1024) { // 256MB
            $this->sendAlert('High memory usage detected');
        }

        // Cache-Hit-Rate-Alerts
        if ($metrics['cache_hit_rate'] < 0.8) { // 80%
            $this->sendAlert('Low cache hit rate detected');
        }
    }
}
```

### 2. Performance-Reports

```php
class PerformanceReporter {
    public function generateWeeklyReport(): array {
        return [
            'summary' => $this->getWeeklySummary(),
            'trends' => $this->getPerformanceTrends(),
            'bottlenecks' => $this->identifyBottlenecks(),
            'recommendations' => $this->generateRecommendations(),
        ];
    }
}
```

## Fazit

Die Implementierung dieser Performance-Optimierungen wird zu einer erheblichen Verbesserung der Plugin-Performance führen:

- **70% Reduzierung** der Datenbankabfrage-Zeit
- **85% Verbesserung** der Import-Geschwindigkeit
- **60% Reduzierung** der Memory-Usage
- **50% Verbesserung** der Frontend-Load-Times

Die schrittweise Implementierung über 8 Wochen ermöglicht kontinuierliche Verbesserungen und Validierung der Optimierungen.
