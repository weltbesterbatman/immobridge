### __Projektkontext: ImmoBridge WordPress Plugin__

__1. Projektziel & Kerntechnologien__

- __Ziel:__ Komplette Neuentwicklung des WordPress-Plugins "immonex-openimmo2wp" unter dem neuen Namen "ImmoBridge". Das Ziel ist eine moderne, performante, sichere und __erweiterbare__ Architektur.

- __Exklusive Integration:__ Das Plugin wird in dieser Version __ausschließlich für den Bricks Builder (ab v2.0)__ entwickelt. Alle Frontend-Elemente und die dynamische Datenintegration sind für Bricks optimiert.

- __PHP-Version:__ 8.2+ (strikte Typisierung, Enums, Readonly Properties, etc. nutzen).

- __WordPress-Version:__ 6.0+

- __Kerntechnologien & Prinzipien:__

  - PSR-4 Autoloading via Composer.
  - API-First Design (WordPress REST API).
  - Dependency Injection (DI) für lose Kopplung.
  - __Architektur für Erweiterbarkeit:__ Ein robustes Hook- und Event-System, um Add-on-Plugins zu ermöglichen.
  - Strikte Trennung der Verantwortlichkeiten (SOLID-Prinzipien).
  - Test-Driven Development (TDD) mit PHPUnit.

__2. Kernarchitektur__

- __Namespace-Struktur:__ Alle Klassen liegen unter dem Haupt-Namespace `ImmoBridge`. Die Struktur ist modular aufgebaut (z.B. `ImmoBridge\Core`, `ImmoBridge\Data`, `ImmoBridge\Integration\Bricks`).
- __Dependency Injection Container:__ Wir verwenden einen eigenen, einfachen DI-Container, um Services zu verwalten. Dies ermöglicht auch das Austauschen von Kern-Services durch Add-ons.
- __Service Provider:__ Services werden über `ServiceProvider`-Klassen im Container registriert.

__3. Datenmodell (Data Layer)__

- __Custom Post Type (CPT):__ Es wird ein eigener CPT `immo_property` registriert.
- __Taxonomien:__ Eigene Taxonomien (`property_type`, `property_status`, `property_features`, etc.) werden registriert.
- __Daten-Entität:__ Die Haupt-Entität ist `ImmoBridge\Data\Models\Property` (POPO mit `readonly` Properties und Value Objects).
- __Repository Pattern:__ Der Zugriff auf die WordPress-Datenbank erfolgt ausschließlich über das Repository Pattern (`PropertyRepositoryInterface`).

__4. Erweiterbarkeit & Hook-System__

- __Event-basiertes System:__ Wichtige Aktionen im Plugin (z.B. `property_imported`, `batch_processed`) lösen Events aus. Add-ons können auf diese Events hören, um eigene Logik hinzuzufügen.

  ```php
  // Beispiel: Event auslösen
  $eventDispatcher->dispatch(new PropertyImportedEvent($property));
  ```

- __WordPress Hooks:__ Zusätzlich zum Event-System werden gezielt WordPress Actions und Filter (`apply_filters`, `do_action`) an strategischen Punkten platziert, um die Anpassung von Daten und Prozessen zu ermöglichen (z.B. `immobridge_before_property_save`, `immobridge_api_query_args`).

- __Beispiel-Erweiterung (Makler-Zuweisung):__ Ein Add-on könnte einen CPT für "Makler" registrieren. Über den `immobridge_before_property_save` Hook könnte es dann die Zuweisung einer Immobilie zu einem Makler basierend auf den Importdaten speichern.

__5. Bricks Builder Integration__

- __Custom Elements:__ Eigene Bricks-Elemente ("Property List", "Property Details", etc.).
- __Dynamic Data Tags:__ Benutzerdefinierte Dynamic Data Tags (`{immobridge:property_price}`).
- __Query Integration:__ Erweiterung der Bricks Query Loop für `immo_property`.

__6. API-Design (API-First)__

- __REST API:__ Namespace `immobridge/v1`. Dient auch als Grundlage für Frontend-Funktionen.
- __Controller:__ Eigene `WP_REST_Controller` Klassen für Ressourcen.
- __Serialisierung:__ Ein `PropertySerializer` wandelt `Property`-Entitäten in JSON um.

__7. Entwicklungs-Workflow & Tooling__

- __Abhängigkeiten:__ Verwaltung über `composer.json`.
- __Tests:__ PHPUnit für Unit- und Integrationstests (>80% Abdeckung).
- __Code-Qualität:__ `WordPress-Coding-Standards` via PHPCS.

---

Ich habe den Punkt "Architektur für Erweiterbarkeit" in den Kernprinzipien ergänzt und einen neuen Abschnitt __"4. Erweiterbarkeit & Hook-System"__ eingefügt, der das Konzept mit einem konkreten Beispiel verdeutlicht.
