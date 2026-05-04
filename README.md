# Directorist WPML Integration

Official WPML integration extension for [Directorist](https://directorist.com) that helps build multilingual directory sites.

## Key Features

- Syncs Directorist listings, directory types, categories, locations, and tags across WPML languages.
- Makes Directorist settings, search forms, widgets, blocks, sorting options, and email templates translatable.
- Registers Directorist directory type builder metadata in `wpml-config.xml` for WPML translation handling.
- Syncs structural directory type settings across translations while keeping directory builder text translatable.
- Tested with WordPress 6.9, PHP 7.4+, Directorist, and current WPML core/addons.

## Changelog

### 2.2.2 - May 04, 2026

- Added expanded WPML config coverage for Directorist directory type metadata.
- Added a WPML translation shortcut inside the Directory Builder edit screen.
- Added runtime sync for copy-only directory type settings after Directory Builder saves.
- Registered custom single listing page term meta as a WPML page ID field.
- Fixed taxonomy language filtering in search form terms.
- Updated WordPress.org release metadata and readme formatting.

For full details, see `readme.txt` or the [plugin repository](https://github.com/sovware/directorist-wpml-integration).
