=== immonex OpenImmo2WP ===
Contributors: inveris
Tags: real estate, openimmo, import, realtors, immonex
Requires at least: 5.5
Tested up to: 6.9
Stable Tag: 5.3.11
Requires PHP: 7.4

Automatized import of OpenImmo-XML real estate data into WordPress sites.

== Description ==

WordPress in combination with a professional real estate theme is a perfect base for developing sophisticated websites for real estate agencies as well as real estate portals.

This plugin is a lightweight solution for importing and frequently updating OpenImmo XML real estate data into WordPress sites/portals based on one of the following real estate themes and frontend plugins.

Supported Plugins:

* immonex Kickstart (+ Add-ons)

Supported Real Estate Themes:

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

Deprecated, but still supported (last version):

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
* Realia (plugin-based, 4.3.1)
* Realty (3.0)
* Shandora (2.3.1)
* Superlist (Inventor Framework, 2.8.0)
* wpCasa (1.3.9)
* WPCasa® (1.2.8) – WPCasa is a brand of inveris.
* Zoner (4.2.0)

immonex OpenImmo2WP is easy and flexible:

* No unnecessary overhead: Installation and base configuration are done in a few minutes, the specialities of the supported themes are consideres automatically.
* Connection in a systematic way: Ready-to-use "mapping tables" for the the assignment of the OpenImmo data to the corresponding WordPress/theme fields are included.
* Mappings can be customized to fit the individual website structures without programming skills.
* Even more flexibility is available through various filter and action hooks over which the import process can be adapted right down to the last detail in a WordPress-compliant way.
* Usable in multi-user environments such as real estate portals: Importing via separate folders with automatic user/agent assignment and listing quota validation/updates for themes that support subscription based packages.

The OpenImmo standard includes a lot of details for describing properties. In most cases, WordPress real estate themes are not really designed for displaying such a broad bandwidth of data. To solve this issue without extra effort, the plugin includes two widgets for including all relevant information in the online exposés:

* display of imported property details that have **not** been assigned to a theme-specific field
* display of file attachment lists (e.g. groundplans as PDF)

The widget layout adapts to the current theme look automatically. On themes without suitable widget areas, it's also possible to add widgets by shortcode to the property description during the import process.

If [WPML](https://wpml.org/) or [Polylang](https://polylang.wordpress.com/) are in use as translation magement solution, multilingual contents can be imported using an extension plugin (https://plugins.inveris.de/wordpress-plugins/immonex-openimmo2wp-multilang/).

Building energy efficiency details defined in the current OpenImmo standard are supported, too (EnEV/GEG). There's another extension plugin available for appealingly displaying the related energy class:

[immonex Energy Scale Pro](https://plugins.inveris.de/wordpress-plugins/immonex-energy-scale-pro/)

= immonex® =

**immonex** is an umbrella brand for various real estate related software solutions and services with a focus on german-speaking markets/users.

= OpenImmo® =

[OpenImmo-XML](http://openimmo.de/) is the de-facto standard for exchanging real estate data in the german-speaking countries. Here, it is supported by almost every common software solution and portal for real estate professionals (as import/export interfaces).

= Features =

* import/update of OpenImmo real estate data into WordPress sites based on various real estate themes and frontend plugins
* transfer format: ZIP archives containing XML (property data) and JPG files (images) as well as other file attachments (e.g. PDF)
* automatic assignment of floor plans, video URLs, virtual tours etc. (if supported by the theme)
* flexible assignment of property data using a mapping table (individually adaptable without programming skills using OpenOffice/LibreOffice Calc or Google Tables)
* full or partly import
* import via separate folders with automatic user/agent/agency assignment in multi-user environments such as real estate portals
* user-related validation and updates of listing quotas for themes that support subscription based packages
* import of multilingual contents (via WPML or Polylang) in combination with an extension plugin: [immonex OpenImmo2WP Multilang](https://plugins.inveris.de/wordpress-plugins/immonex-openimmo2wp-multilang/)
* support of energy pass data (e.g. according to German EnEV 2014/GEG) (graphical display including automatic data transfer possible using an extension plugin: [immonex Energy Scale Pro](https://plugins.inveris.de/wordpress-plugins/immonex-energy-scale-pro/))
* automatic assignment of property agents and agencies (theme-dependent)
* consideration of specialities of the theme used
* theme-specific configuration options
* automatized import by WP-Cron
* import and debug protocols by email
* widgets for the groupable display of individual property features as well as file attachments in online exposés
* ability to add any widgets to the property description (by shortcode) on themes without suitable widget areas
* archiving of processed import files and logs (including deletion of outdated files)
* individual customization of the whole import process including data manipulation possible via WordPress filter and action hooks
* automatic restart of interrupted import processes
* manual start and resumption of import processes possible anytime
* property location geocoding based on OpenStreetMap, Google Maps API or Bing Maps API
* optimized for hosting/webspace environments with limited resources (memory, script runtime)
* usable in WordPress multisite installations
* continuous further development (e.g. adaptation to new OpenImmo or WordPress versions)

= System Requirements =

* PHP >= 7.4
* WordPress >= 5.5
* supported real estate theme or frontend plugin (see list above)

== Installation ==

1. WordPress backend: Plugins > Add New > Upload Plugin *
2. Select the plugin ZIP file and click the install button.
3. Activate the plugin after successful installation.
4. Apply base settings under OpenImmo2WP > Settings.
5. Insert the key on the license tab and activate the license. That's it!

* Alternative: Unzip the plugin ZIP archive, copy it to the folder `wp-content/plugins` and activate the plugin in the WordPress backend under Plugins > Installed Plugins afterwards.

During the plugin activation, the following folders are being created:

`wp-content/uploads/immonex-openimmo-import`:
This is the folder the ZIP files to be imported have to be uploaded to. These contain real estate data in the OpenImmo XML format as well as media attachments like images or additional files.

`wp-content/uploads/immonex-openimmo-import/archive`:
If the archiving option is checked, already processed ZIP archives and the related log files are saved here.

`wp-content/uploads/immonex-openimmo-import/mappings`:
One or more mapping definition files in CSV format are available in this folder. These are used to assign the OpenImmo properties to the corresponding theme-specific fields during the import process. Mapping file suitable for the default installations of all supported themes will be copied here on plugin activation.

= Documentation =

The plugin documentation is available here (in German only):

https://docs.immonex.de/openimmo2wp/

== Changelog ==

= 5.3.21-beta =
* Release date: 2025-08-06
* Added an option for setting the check time for "uncontrolled" import process aborts.
* Added extended validation/sanitization for geo coordinates.
* Added provision title adjustments for rental properties.
* Optimized DB queries.
* Improved automatic taxonomy term adjustments (immonex Kickstart).
* Updated BO-Beladomo20 and WpResidence theme support.
* Updates of property posts with status "private" enabled by default.
* Updated dependencies.

= 5.3.11 =
* Release date: 2025-01-29
* Added a workaround to fix a Houzez theme bug (empty property IDs).
* Updated Kickstart mapping table.
* Reworked processing of panorama images/videos.
* Extended property ID determination.
* Added import of AreaButler URLs.
* Added Giraffe360 virtual tour URL recognition.
* Updated dependencies.
* Verified compatibility with WordPress 6.8 and PHP 8.3.

= 5.3.0 "Poke" =
* Release date: 2024-07-23
* Added mapping query options: empty, exists, missing, empty_or_missing.
* Added storing of property energy classes and related IDs in dedicated custom fields.
* Added translation reload on language switches during import.
* Added AVIF to the list of supported image formats.
* Added a custom field for the property post status.
* Reworked import of PDF floor plans and energy scale images for immonex Kickstart.
* Extended immonex Kickstart default values.
* Fixed a minor mapping bug on querying number values.
* Extended/Reworked all mapping tables (i. a. GEG information).
* Updated RealHomes theme support.
* Improved video URL processing.

See changelog.txt for full version history.

[Rev ID CHUPACACHE:yETbvNmLslWYtdGQuFWb0FmYyVGdzVmY0xWZ3hXV]