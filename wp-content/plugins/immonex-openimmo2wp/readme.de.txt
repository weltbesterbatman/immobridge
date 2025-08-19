=== immonex OpenImmo2WP ===
Contributors: inveris
Tags: immobilien, openimmo, import, immobilienmakler, immomakler, immonex
Requires at least: 5.5
Tested up to: 6.9
Stable Tag: 5.3.11
Requires PHP: 7.4

Automatisierter Import von OpenImmo-XML-basierten Immobiliendaten in WordPress-Websites.

== Beschreibung ==

WordPress ist in Verbindung mit einem professionellen Immobilien-Theme die perfekte Basis für die Umsetzung anspruchsvoller Internet-Angebote für Immobilienmakler und Immobilien-Portale.

Dieses Plugin ist eine kompakte Lösung für den Import und die regelmäßige Aktualisierung von Immobiliendaten im OpenImmo-XML-Format in WordPress-Websites und -Portale, die auf einem der folgenden Immobilien-Themes und -Frontend-Plugins basieren.

Unterstützte Plugins:

* immonex Kickstart (+ Add-ons)

Unterstützte Immobilien-Themes:

* BO-Beladomo20
* BO HOME
* Freehold Progression
* Houzez
* MyHome
* Reales WP
* RealHomes
* RealPlaces
* WP Estate
* WP Residence

Veraltet, aber noch unterstützt (letzte Version):

* BO-Beladomo (2.6.9)
* BO-Immobilia (4.2)
* BO-Immobilia18 (1.2.4)
* BO-ImmoMobil (2.3.2)
* BO Property (4.0.1)
* Estate Pro (1.1)
* Freehold (2.8.0)
* Hometown (2.9.0)
* IMPress Listings/WP Listings
* Preston (1.2.0)
* Realia (3.1.5)
* Realia (pluginbasiert, 4.3.1)
* Realty (3.0)
* Shandora (2.3.1)
* Superlist (Inventor Framework, 2.8.0)
* wpCasa (1.3.9)
* WPCasa® (1.2.8) – WPCasa ist eine Marke von inveris.
* Zoner (4.2.0)

immonex OpenImmo2WP ist einfach und flexibel:

* Kein überflüssiger Schnickschnack: Installation und Grundkonfiguration sind in wenigen Minuten erledigt, die Besonderheiten der unterstützten Themes werden automatisch berücksichtigt.
* Verknüpfung mit System: Direkt einsetzbare "Mapping-Tabellen" für die Zuordnung der OpenImmo-Daten zu den entsprechenden WordPress/Theme-Feldern sind enthalten.
* Mappings können per Tabellenkalkulation – ohne Programmierkenntnisse – an die individuellen Strukturen der eigenen Website angepasst werden.
* Noch mehr Flexibilität bieten diverse Filter- und Action-Hooks, über die der Importvorgang auf WordPress-konforme Art bis ins kleinste Detail angepasst werden kann.
* Einsetzbar in Mehrbenutzer-Umgebungen wie bspw. Immobilien-Portalen: Import über separate Ordner mit automatischer Zuordnung von Benutzern/Agenturen sowie Prüfung und Aktualisierung von Angebotskontingenten bei Themes, die Abonnement-Pakete unterstützen.

Der OpenImmo-Standard beinhaltet eine Vielzahl an Eigenschaften für die detaillierte Beschreibung von Immobilien. Im Regelfall sind WordPress-Immobilien-Themes nicht für die Darstellung einer solch großen Bandbreite an Daten ausgelegt. Damit trotzdem alle wichtigen Informationen ohne Zusatzaufwand in die Online-Exposés eingebunden werden können, bringt das Plugin zwei eigene Widgets für folgende Aufgaben mit:

* Anzeige importierter Objekteigenschaften, die **nicht** einem theme-spezifischen Feld zugewiesen wurden
* Ausgabe von Listen mit Dateianhängen (z. B. PDF-Grundrisse)

Die Darstellung der Widgets passt sich der jeweiligen Theme-Optik an. Bei Themes ohne passende Widgetbereiche besteht die Möglichkeit, die Widgets per automatisch ergänztem Shortcode an die Objektbeschreibung anzuhängen.

Wenn [WPML](https://wpml.org/) oder [Polylang](https://polylang.wordpress.com/) als Übersetzungs-Management-Lösung zum Einsatz kommen, können mehrsprachige Inhalte in Kombination mit einem Erweiterungs-Plugin https://plugins.inveris.de/wordpress-plugins/immonex-openimmo2wp-multilang/) importiert werden.

Die im aktuellen OpenImmo-Standard definierten Gebäude-Energieausweis-Eigenschaften gem. Energie-Einsparverordnung (EnEV) bzw. Gebäudeenergiegesetz (GEG) werden unterstützt. Für die ansprechende grafische Darstellung der Energieklassen ist eine passende Erweiterung verfügbar:

[immonex Energy Scale Pro](https://plugins.inveris.de/wordpress-plugins/immonex-energy-scale-pro/)

= immonex® =

**immonex** ist eine Dachmarke für diverse immobilienbezogene Softwarelösungen und Dienste mit einem Fokus auf deutschsprachige Märkte/Nutzer.

= OpenImmo® =

[OpenImmo-XML](http://openimmo.de/) ist der De-facto-Standard für den Austausch von Immobiliendaten in den deutschsprachigen Ländern und wird hier von allen gängigen Softwarelösungen und Portalen für Immobilien-Profis durch entsprechende Import/Export-Schnittstellen unterstützt.

= Primäre Funktionen =

* Import/Aktualisierung von OpenImmo-Immobiliendaten in WordPress-Websites auf Basis diverser Immobilien-Themes und Frontend-Plugins
* Übertragungsformat: ZIP-Archive mit Dateien im XML- (Objektdaten) und JPG-Format (Bilder) sowie beliebige weitere Dateianhänge (z. B. PDF)
* automatische Zuordnung von Grundrissen, Video-URLs, virtuellen Touren etc. (sofern vom Theme unterstützt)
* flexible Zuordnung von Objekteigenschaften per Mapping-Tabelle (individuell anpassbar ohne Programmierkenntnisse per OpenOffice/LibreOffice Calc oder Google Tabellen)
* Voll- oder Teilabgleich
* Import über separate Ordner mit automatischer Zuordnung von Benutzer/Ansprechpartner/Agentur in Mehrbenutzer-Umgebungen wie bspw. Immobilien-Portalen
* benutzerbezogene Prüfung/Aktualisierung von Anzeigen-Kontingenten für Themes, die Abonnement-Pakete unterstützen
* Import mehrsprachiger Inhalte (via WPML oder Polylang) in Kombination mit einem Erweiterungsplugin: [immonex OpenImmo2WP Multilang](https://plugins.inveris.de/wordpress-plugins/immonex-openimmo2wp-multilang/)
* Unterstützung der Energieausweis-Daten (z. B. gem. EnEV 2014/GEG) (grafische Anzeige mit automatischer Datenübernahme per Erweiterungsplugin möglich: [immonex Energy Scale Pro](https://plugins.inveris.de/wordpress-plugins/immonex-energy-scale-pro/))
* automatische Zuordnung von Objekt-Ansprechpartnern und -Agenturen (themeabhängig)
* Berücksichtigung der jeweiligen Besonderheiten des eingesetzten Themes
* themespezifische Konfigurationsoptionen
* automatisierter Import per WP-Cron
* Import- und Debug-Protokolle per E-Mail
* Widgets für die gruppierbare Ausgabe von individuellen Immobilien-Eigenschaften sowie Dateianhängen in Online-Exposés
* Möglichkeit zur Anhängen beliebiger Widgets an die Objektbeschreibung (per Shortcode) bei Themes ohne passende Widget-Bereiche
* Archivierung von verarbeiteten Import-Dateien und -Protokollen (inkl. automatischer Bereinigung alter Dateien)
* individuelle Anpassung des Importvorgangs inkl. Modifizierung der Daten per WordPress-Filter und Action-Hooks
* automatische Fortsetzung von unterbrochenen Importvorgängen
* manueller Start und Fortsetzung von Importvorgängen jederzeit möglich
* Immobilien-Standort-Geocodierung mit OpenStreetMap, Google Maps API oder Bing Maps API
* optimiert für Webspace-Umgebungen mit begrenzten Ressourcen (Speicher, Script-Laufzeit)
* kontinuierliche Weiterentwicklung (z. B. Anpassung an neue OpenImmo- und WordPress-Versionen)

= Systemvoraussetzungen =

* PHP >= 7.4
* WordPress >= 5.5
* unterstütztes Immobilien-Theme oder Frontend-Plugin (siehe Liste oben)

== Installation ==

1. WordPress-Backend: Plugins > Installieren > Plugin hochladen *
2. Plugin-ZIP-Datei auswählen und Installieren-Button klicken
3. Plugin nach erfolgreicher Installation aktivieren
4. Grundkonfiguration unter OpenImmo2WP > Einstellungen vornehmen
5. Lizenzschlüssel im Tab "Lizenz" hinterlegen und Lizenz aktivieren. That's it!

* Alternativ: Plugin-ZIP-Datei entpacken und ins Verzeichnis `wp-content/plugins` kopieren, anschließend das Plugin im WordPress-Backend unter Plugins > Installierte Plugins aktivieren.

Im Rahmen der Aktivierung werden die folgenden Ordner angelegt:

`wp-content/uploads/immonex-openimmo-import`: 
In diesen Ordner werden die zu importierenden ZIP-Dateien hochgeladen. Diese enthalten die Immobiliendaten im OpenImmo-XML-Format sowie die zugehörigen Medienanhänge (Bilder oder weitere Dateien).

`wp-content/uploads/immonex-openimmo-import/archive`: 
Sofern die Archivierung aktiviert ist, werden hier bereits verarbeitete ZIP-Archive sowie Importprotokolle gespeichert.

`wp-content/uploads/immonex-openimmo-import/mappings`: 
Hier sind eine oder mehrere Mapping-Definitionsdateien (CSV-Format) hinterlegt, mit denen die Zuordnung der OpenImmo-Daten zu den entsprechenden themespezifischen Feldern beim Import festgelegt wird. Direkt einsetzbare Mapping-Dateien für Standard-Installationen aller unterstützter Themes werden bereits bei der Plugin-Aktivierung hierher kopiert.

= Dokumentation =

Eine detaillierte Plugin-Dokumentation (Installation, Einrichtung, Einbindung, individuelle Anpassung, Entwicklung etc.) ist hier verfügbar:

https://docs.immonex.de/openimmo2wp/

== Changelog ==

= 5.3.21-beta =
* Veröffentlichungsdatum: 06.08.2025
* Option zur Festlegung der Prüfzeit für "unkontrollierte" Abbrüche von Importprozessen ergänzt.
* Erweiterte Validierung/Bereinigung von Geo-Koordinaten umgesetzt.
* Anpassung von Provisionsbezeichnungen bei Mietobjekten ergänzt.
* DB-Abfragen optimiert.
* Automatisierte Anpassung von Taxonomie-Begriffen verbessert (immonex Kickstart).
* BO-Beladomo20- und WpResidence-Theme-Unterstützung aktualisiert.
* Aktualisierung von Immobilienbeiträgen mit dem Status "privat" standardmäßig aktiviert.
* Abhängigkeiten aktualisiert.

= 5.3.11 =
* Veröffentlichungsdatum: 29.01.2025
* Workaround zur Korrektur eines Houzez-Theme-Bugs ergänzt (leere Objekt-IDs).
* Kickstart-Mapping-Tabelle aktualisiert.
* Verarbeitung von Panorama-Fotos/Videos überarbeitet.
* Ermittlung von Objekt-IDs erweitert.
* Import von AreaButler-URLs ergänzt.
* Erkennung von Giraffe360-URLs ergänzt (virtuelle Touren).
* Abhängigkeiten aktualisiert.
* Kompatibilität mit WordPress 6.8 und PHP 8.3 verifiziert.

= 5.3.0 "Poke" =
* Veröffentlichungsdatum: 23.07.2024
* Mapping-Abfrageoptionen ergänzt: empty, exists, missing, empty_or_missing.
* Speicherung von Gebäudeenergieklassen und zugehörigen IDs in dedizierten benutzerdefinierten Feldern.
* Neuladen von Übersetzungen bei Sprachwechseln während des Imports hinzugefügt.
* AVIF zur Liste unterstützter Bildformate hinzugefügt.
* Custom Field für den Status der Immobilien-Beiträge ergänzt.
* Import von Grundrissen und Energieskalen im PDF-Format für immonex Kickstart überarbeitet.
* Standard-Vorgabewerte für immonex Kickstart erweitert.
* Mapping-Tabellen erweitert/überarbeitet (u. a. GEG-Angaben).
* Mapping-Fehler beim Abfragen bestimmter numerischer Werte behoben.
* RealHomes-Theme-Unterstützung aktualisiert.
* Verarbeitung von Video-URLs verbessert.

Siehe changelog.txt für die komplette Versionshistorie.