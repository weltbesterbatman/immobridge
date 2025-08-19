# Projekt-Regeln für ImmoBridge

Diese Regeln definieren die Standards und Konventionen für die Entwicklung des ImmoBridge WordPress-Plugins.

### 1. Sprache & Version

- **Sprache & Version:** Der gesamte PHP-Code **muss** für **PHP 8.2+** geschrieben werden.
- **Strikte Typisierung:** `declare(strict_types=1);` **muss** am Anfang jeder PHP-Datei stehen.
- **Moderne Features:** Moderne PHP-Features wie `Enums`, `readonly properties` und `match`-Ausdrücke **sollen** bevorzugt verwendet werden.

### 2. Coding Standards

- **PSR-12:** Der Code **muss** dem **PSR-12** Coding Style Guide befolgen.
- **WordPress Standards:** Zusätzlich **müssen** die **WordPress Coding Standards** (insbesondere für Benennungen von Hooks und die Verwendung von WordPress-Funktionen) eingehalten werden.

### 3. Architektur

- **Namespace:** Alle Klassen **müssen** sich an die definierte **`ImmoBridge\...` Namespace-Struktur** halten.
- **Dependency Injection:** Abhängigkeiten **dürfen nicht** direkt instanziiert werden (`new Service()`). Sie **müssen** über den **Dependency Injection Container** aufgelöst werden.
- **Repository Pattern:** Jeglicher Datenbankzugriff **muss** über das **Repository Pattern** erfolgen. Direkte `WP_Query` oder `$wpdb`-Aufrufe in der Geschäftslogik sind verboten.

### 4. Dateien & Benennung

- **Dateinamen:** Dateinamen **müssen** den Klassennamen entsprechen (z.B. `PropertyRepository.php` für die Klasse `PropertyRepository`).
- **Interfaces:** Interfaces **müssen** mit dem Suffix `Interface` benannt werden (z.B. `PropertyRepositoryInterface`).
- **Traits:** Traits **müssen** mit dem Suffix `Trait` benannt werden (z.B. `CacheableTrait`).

### 5. Dokumentation

- **PHPDoc:** Jede Klasse, Methode und Funktion **muss** einen vollständigen **PHPDoc-Block** haben, der den Zweck, die Parameter (`@param`) und den Rückgabewert (`@return`) beschreibt.

### 6. Sicherheit

- **Input-Validierung:** Alle externen Daten (z.B. aus `$_POST`, `$_GET`, API-Requests) **müssen** validiert und escaped werden.
- **Autorisierung:** Alle Aktionen im Admin-Bereich **müssen** durch Nonces und Capability-Checks (`current_user_can()`) abgesichert werden.

### 7. Testing

- **Testabdeckung:** Für neue, komplexe Funktionalität (z.B. Services, Daten-Transformationen) **sollen** begleitende **PHPUnit-Tests** geschrieben werden.

### 8. GitHub Commits

- **Commit-Nachrichten:** Commit-Nachrichten **sollen** dem **Conventional Commits** Standard folgen (z.B. `feat: Add property CPT`, `fix: Correct XML parsing error`, `docs: Update readme`).

### 9. Login Wordpress Backend

* URL: https://immonex-bricks-wp.local:8890/wp-admin/plugins.php
* 
* User: admin
* Password: Admin-123456

**URL zur Datenbank**

http://localhost:8888/phpMyAdmin5/index.php?route=/database/structure&db=wp_immonexbrickswplocal_db

Host der Datenbank: localhost:8889

### 10. Aktualisierung der Datei  07_Projekt_Roadmap.md

* Nach allen Änderungen und Anpasungen wird das Dokument aktalisiert und an den tatsächlichen Projketstand angepasst
* Die Version des Dokuments ist zu aktualieren


### 11. Chat Sprache ist Deutsch