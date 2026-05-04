=== Directorist - WPML Integration ===
Contributors: wpwax
Tags: directory, directorist, directorist wpml, wpml
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 2.2.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Directorist WPML Integration connects Directorist with WPML so you can build multilingual directory websites.

== Description ==

[DOC](https://directorist.com/documentation/directorist/directorist-wpml-translation-guide/directory-type-translation/) | [Contact](https://directorist.com/contact/) | [Other Extensions](https://directorist.com/extensions/)

Want to make your directory multilingual and reach visitors in multiple languages? Directorist WPML Integration connects Directorist and WPML so directory listings, taxonomies, settings, forms, widgets, blocks, and emails can work correctly across WPML languages.

WPML provides an interface for professional content translation. This plugin adds the Directorist-specific compatibility layer needed for multilingual directory sites.

Useful links:

* Join Our FB Community: [Directorist Community](https://www.facebook.com/groups/directorist)
* Official Facebook Page: [Like and Follow on Facebook](https://www.facebook.com/directorist)
* Official Twitter handle: [Follow on Twitter](https://twitter.com/wpdirectorist)
* Official YouTube Channel: [Follow on YouTube](https://www.youtube.com/c/wpWax)
* Official Support: [Contact](https://directorist.com/dashboard/)

== Requirements ==

The following plugins must be installed in order to translate Directorist content:

1. WPML Multilingual CMS - paid
2. Directorist - WordPress Business Directory Plugin with Classified Ads Listings - free
3. Directorist WPML Integration - free

== Recommended ==

These WPML addons are recommended for the best multilingual experience:

1. WPML String Translation
2. WPML Media Translation

== Features at a Glance ==

* Create or translate directory listings in multiple languages.
* Translate Directorist categories, locations, tags, and directory types.
* Make all listings/archive pages multilingual.
* Make the Directorist dashboard multilingual.
* Translate Directorist settings strings.
* Translate Directorist search form, add listing form, widgets, blocks, sorting options, and email templates.
* Sync directory type structural settings across translations while keeping text fields translatable.

== Contribute to Directorist WPML Integration ==

If you want to contribute to the project, you're welcome to help. The full source code is available on [GitHub](https://github.com/sovware/directorist-wpml-integration). If you find an issue, feel free to submit a bug report.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/` directory or install the plugin through the WordPress plugins screen.
2. Activate Directorist and WPML Multilingual CMS.
3. Activate Directorist WPML Integration through the WordPress Plugins screen.
4. Configure your languages from WPML.
5. Translate Directorist content from WPML and Directorist's multilingual controls as needed.

== Changelog ==

= 2.2.2 - May 04, 2026 =

* Added: WPML `wpml-config.xml` coverage for Directorist directory type term meta, including directory builder form/layout fields, terms/privacy labels, sidebar settings, similar listing settings, and custom single listing page IDs.
* Added: WPML translation shortcut inside the Directorist Directory Builder edit screen for existing directory types.
* Added: Runtime synchronization for copy-only directory type settings so structural options stay aligned across translated directory types after saving the Directorist Directory Builder.
* Improved: Custom single listing page handling by registering the directory type `single_listing_page` term meta as a translatable page ID for WPML.
* Improved: Gutenberg block translation configuration format to validate cleanly against WPML's installed XML schema.
* Fixed: Search form taxonomy language filtering now uses WPML's normalized taxonomy element type before checking term language details.
* Fixed: Release metadata now matches the plugin version and WordPress.org readme requirements.

= 2.2.1 - Feb 05, 2026 =

* Added: Built-in synchronization of Directorist category `_directory_type` meta across WPML languages, based on WPML's recommended workaround (no extra code snippet needed).
* Added: WPML config to copy the `_default` directory type flag across translations, ensuring a proper default directory type per language.
* Improved: Overall WPML compatibility for directory types, categories, search form fields, and settings strings on WordPress 6.8 and the latest WPML versions.
* Fixed: New translatable data is available for translation and displays correctly on the front-end.
* Fixed: Data saved in posts remains translatable and displays correctly on the front-end.
* Fixed: Data saved in taxonomies remains translatable and displays correctly on the front-end.
* Fixed: Front-end strings are translatable with WPML String Translation and display correctly.
* Fixed: Email sending process so content is translated and sent in the user's preferred language.

= 2.1.4 - Jun 09, 2025 =

* Added: Directorist as a required dependency.
* Added: Translation support for Claim Listing settings.

= 2.0.0 - Nov 17, 2024 =

* Added: Directorist compatibility.
