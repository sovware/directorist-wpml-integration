# Directorist WPML Integration - Developer Documentation

## Overview

The **Directorist WPML Integration** plugin provides comprehensive multilingual support for Directorist, a powerful WordPress directory plugin. This integration enables full translation of dynamic form fields, sections, taxonomy terms, and other Directorist components using WPML's String Translation system.

## What Problem Does This Solve?

Directorist uses a dynamic form builder system where form fields and sections are stored in term meta (`submission_form_fields`) for each directory type. These fields are not static PHP strings, making traditional translation methods ineffective.

**Key Challenges Solved:**

1. **Dynamic Form Fields**: Directorist form fields are stored in database term meta, not hardcoded in templates
2. **Multi-Directory Support**: Each directory type can have different form fields, requiring context-aware translation
3. **Runtime Translation**: Fields must be translated at runtime when forms are rendered, not at compile time
4. **WPML Compatibility**: Integration with WPML's String Translation system without overriding Directorist templates

## Supported Areas of Translation

### ✅ Fully Supported

- **Add Listing Form Fields**
  - Field labels
  - Field placeholders
  - Field descriptions
  - Field options (select, radio, checkbox)
  - Custom field properties (e.g., pricing field labels)

- **Add Listing Form Sections**
  - Section labels
  - Section navigation labels

- **Directory Types**
  - Directory type names (frontend and backend)
  - Directory type translations in language switchers

- **Tags**
  - Tag names (frontend and backend)

- **Query Filtering**
  - Listings filtered by current language
  - Accurate listing counts per language
  - Taxonomy term translation in queries

- **Permalinks**
  - Translated page URLs
  - Language-aware navigation

### 🔄 Architecture Approach

**Important**: This plugin does **NOT** override Directorist templates. Instead, it uses WordPress hooks and filters to intercept data at runtime and apply translations. This ensures:

- Compatibility with future Directorist updates
- No template conflicts
- Clean separation of concerns
- Easy maintenance

## Plugin Structure

```
directorist-wpml-integration/
├── app/
│   ├── Controller/
│   │   └── Hook/
│   │       ├── Add_Listing_Form_Translation.php  # Form field/section translation
│   │       ├── Directory_Translation.php          # Directory type/tag translation
│   │       ├── Query_Filtering.php               # Language-based query filtering
│   │       └── Filter_Permalinks.php             # URL translation
│   └── Helper/
│       └── WPML_Helper.php                       # WPML utility functions
└── docs/                                         # This documentation
```

## Quick Start

1. **Installation**: Activate both Directorist and WPML plugins
2. **Configuration**: No configuration needed - translations work automatically
3. **Translation**: Use WPML String Translation interface to translate strings
4. **Testing**: Switch languages and verify translations appear correctly

## Documentation Index

- [WPML Overview](./WPML-OVERVIEW.md) - Understanding WPML String Translation
- [Directorist Architecture](./DIRECTORIST-ADD-LISTING-ARCHITECTURE.md) - How Directorist forms work
- [Integration Hooks](./INTEGRATION-HOOKS.md) - All hooks and filters used
- [Add Listing Translation](./ADD-LISTING-TRANSLATION.md) - Form translation details
- [String Key Strategy](./STRING-KEY-STRATEGY.md) - Naming conventions
- [Admin Usage](./ADMIN-USAGE.md) - How to translate strings
- [Troubleshooting](./TROUBLESHOOTING.md) - Common issues and solutions
- [Developer Notes](./DEVELOPER-NOTES.md) - Coding standards and best practices

## Requirements

- WordPress 5.0+
- Directorist 7.0+
- WPML Multilingual CMS 4.0+
- WPML String Translation (required for string translation)

## Support

For issues, feature requests, or contributions, please refer to the plugin repository or contact support.

---

**Version**: 2.1.5+  
**Last Updated**: 2024
