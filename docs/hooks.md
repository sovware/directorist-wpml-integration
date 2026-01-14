# Integration Hooks Reference

This document provides a complete reference of all WordPress hooks and filters used by the Directorist WPML Integration plugin.

## Hook Categories

1. **Form Translation Hooks** - Translate form fields and sections
2. **Taxonomy Translation Hooks** - Translate directory types and tags
3. **Query Filtering Hooks** - Filter queries by language
4. **Permalink Hooks** - Translate URLs and page links
5. **WPML API Hooks** - WPML-specific hooks

---

## Form Translation Hooks

### `directorist_form_field_data`

**Purpose**: Translate field data (labels, placeholders, descriptions, options) before field widget renders.

**Hook Type**: Filter  
**Priority**: 10  
**Parameters**: 
- `$field_data` (array) - Field configuration array

**Return**: Modified `$field_data` array

**Used In**: `Add_Listing_Form_Translation::translate_field_data()`

**Example**:
```php
add_filter( 'directorist_form_field_data', function( $field_data ) {
    // $field_data contains:
    // - 'field_key' => 'title'
    // - 'label' => 'Listing Title'
    // - 'placeholder' => 'Enter title'
    // - 'description' => 'Help text'
    // - 'options' => [...] (for select/radio fields)
    
    // Translate label
    $field_data['label'] = translate_string( $field_data['label'] );
    
    return $field_data;
}, 10, 1 );
```

**Field Data Structure**:
```php
[
    'field_key' => 'title',
    'widget_name' => 'text',
    'label' => 'Listing Title',
    'placeholder' => 'Enter listing title',
    'description' => 'The title of your listing',
    'required' => true,
    'options' => [], // For select/radio/checkbox
    'form' => $form_instance, // ListingForm object
]
```

---

### `directorist_section_template`

**Purpose**: Translate section labels before section template renders.

**Hook Type**: Filter  
**Priority**: 10  
**Parameters**:
- `$load_section` (bool) - Whether to load the section
- `$args` (array) - Template arguments

**Return**: `$load_section` (bool) - Unchanged

**Used In**: `Add_Listing_Form_Translation::translate_section_label()`

**Example**:
```php
add_filter( 'directorist_section_template', function( $load_section, $args ) {
    // $args contains:
    // - 'section_data' => ['key' => 'basic_info', 'label' => 'Basic Information']
    // - 'listing_form' => $form_instance
    
    $label = $args['section_data']['label'];
    $args['section_data']['label'] = translate_string( $label );
    
    return $load_section; // Must return bool
}, 10, 2 );
```

**Note**: This filter may not persist due to pass-by-value. See `atbdp_add_listing_page_template` for fallback.

---

### `atbdp_add_listing_page_template`

**Purpose**: Replace section labels in rendered HTML output (fallback mechanism).

**Hook Type**: Filter  
**Priority**: 10  
**Parameters**:
- `$template_output` (string) - Rendered HTML
- `$args` (array) - Template arguments

**Return**: Modified HTML string

**Used In**: `Add_Listing_Form_Translation::translate_section_labels_in_output()`

**Example**:
```php
add_filter( 'atbdp_add_listing_page_template', function( $output, $args ) {
    // Replace section labels in HTML
    $output = str_replace( 
        'Basic Information', 
        'Información Básica', 
        $output 
    );
    
    return $output;
}, 10, 2 );
```

**When to Use**: When `directorist_section_template` doesn't persist due to pass-by-value limitations.

---

## Taxonomy Translation Hooks

### `directorist_directories_for_template`

**Purpose**: Translate directory type names when retrieved for template display.

**Hook Type**: Filter  
**Priority**: 10  
**Parameters**:
- `$directories` (array) - Array of WP_Term objects
- `$args` (array) - Query arguments

**Return**: Modified `$directories` array with translated names

**Used In**: `Directory_Translation::translate_directory_names()`

**Example**:
```php
add_filter( 'directorist_directories_for_template', function( $directories, $args ) {
    foreach ( $directories as $directory ) {
        // $directory is WP_Term object
        $directory->name = translate_string( $directory->name );
    }
    return $directories;
}, 10, 2 );
```

---

### `get_terms`

**Purpose**: Translate directory type and tag term names when retrieved via `get_terms()`.

**Hook Type**: Filter  
**Priority**: 10  
**Parameters**:
- `$terms` (array|WP_Error) - Array of term objects
- `$taxonomies` (array) - Taxonomy names
- `$args` (array) - Query arguments
- `$term_query` (WP_Term_Query) - Term query object

**Return**: Modified `$terms` array

**Used In**: `Directory_Translation::translate_directory_terms()`

**Example**:
```php
add_filter( 'get_terms', function( $terms, $taxonomies, $args, $term_query ) {
    // Check if directory type taxonomy
    if ( in_array( 'atbdp_listing_types', $taxonomies ) ) {
        foreach ( $terms as $term ) {
            $term->name = translate_string( $term->name );
        }
    }
    return $terms;
}, 10, 4 );
```

**Note**: Only processes `atbdp_listing_types` and `at_biz_dir-tags` taxonomies.

---

### `term_name`

**Purpose**: Translate term names in admin areas (admin columns, forms, etc.).

**Hook Type**: Filter  
**Priority**: 10  
**Parameters**:
- `$name` (string) - Term name
- `$term` (object) - Term object

**Return**: Translated term name

**Used In**: `Directory_Translation::translate_term_name()`

**Example**:
```php
add_filter( 'term_name', function( $name, $term ) {
    // Check if directory type or tag
    if ( $term->taxonomy === 'atbdp_listing_types' ) {
        return translate_string( $name );
    }
    return $name;
}, 10, 2 );
```

---

## Query Filtering Hooks

### `pre_get_posts`

**Purpose**: Modify WP_Query to ensure WPML language filtering is applied.

**Hook Type**: Action  
**Priority**: 10  
**Parameters**:
- `$query` (WP_Query) - Query object

**Return**: `void`

**Used In**: `Query_Filtering::filter_listing_queries()`

**Example**:
```php
add_action( 'pre_get_posts', function( $query ) {
    if ( $query->get( 'post_type' ) === 'at_biz_dir' ) {
        // Ensure suppress_filters is false for WPML
        $query->set( 'suppress_filters', false );
    }
}, 10, 1 );
```

---

### `directorist_all_listings_query_arguments`

**Purpose**: Modify query arguments before WP_Query is created for all listings page.

**Hook Type**: Filter  
**Priority**: 10  
**Parameters**:
- `$args` (array) - WP_Query arguments

**Return**: Modified `$args` array

**Used In**: `Query_Filtering::ensure_wpml_filtering()`

**Example**:
```php
add_filter( 'directorist_all_listings_query_arguments', function( $args ) {
    // Ensure WPML can filter
    $args['suppress_filters'] = false;
    return $args;
}, 10, 1 );
```

---

### `directorist_dashboard_query_arguments`

**Purpose**: Modify query arguments for user dashboard listings.

**Hook Type**: Filter  
**Priority**: 10  
**Parameters**:
- `$args` (array) - WP_Query arguments

**Return**: Modified `$args` array

**Used In**: `Query_Filtering::ensure_wpml_filtering()`

---

### `atbdp_author_listings_query_arguments`

**Purpose**: Modify query arguments for author profile listings.

**Hook Type**: Filter  
**Priority**: 10  
**Parameters**:
- `$args` (array) - WP_Query arguments

**Return**: Modified `$args` array

**Used In**: `Query_Filtering::ensure_wpml_filtering()`

---

### `directorist_listings_query_results`

**Purpose**: Filter query results to ensure only current language listings are included.

**Hook Type**: Filter  
**Priority**: 5  
**Parameters**:
- `$results` (object) - Query results object

**Return**: Modified `$results` object

**Used In**: `Query_Filtering::filter_query_results()`

**Results Object Structure**:
```php
(object) [
    'ids' => [1, 2, 3], // Post IDs
    'total' => 10, // Total count
    'total_pages' => 2, // Pagination pages
    'per_page' => 5, // Items per page
    'current_page' => 1, // Current page
]
```

**Example**:
```php
add_filter( 'directorist_listings_query_results', function( $results ) {
    // Filter IDs by current language
    $filtered_ids = [];
    foreach ( $results->ids as $id ) {
        if ( is_current_language( $id ) ) {
            $filtered_ids[] = $id;
        }
    }
    
    // Update results
    $results->ids = $filtered_ids;
    $results->total = count( $filtered_ids );
    
    return $results;
}, 5, 1 );
```

---

## Permalink Hooks

### `atbdp_listings_page_url`

**Purpose**: Filter all listings page URL to use translated page.

**Hook Type**: Filter  
**Priority**: 10  
**Parameters**:
- `$link` (string) - Page URL
- `$page_id` (int) - Page ID

**Return**: Modified URL string

**Used In**: `Filter_Permalinks::filter_all_listings_page_url()`

**Example**:
```php
add_filter( 'atbdp_listings_page_url', function( $link, $page_id ) {
    // Get translated page ID
    $translated_id = wpml_object_id( $page_id, 'page', false );
    if ( $translated_id ) {
        return get_permalink( $translated_id );
    }
    return $link;
}, 10, 2 );
```

---

### `directorist_page_id`

**Purpose**: Filter page ID to return translated page ID for current language.

**Hook Type**: Filter  
**Priority**: 10  
**Parameters**:
- `$id` (int) - Page ID

**Return**: Translated page ID

**Used In**: `Filter_Permalinks::page_id()`

---

### `wpml_ls_language_url`

**Purpose**: Modify language switcher URLs for Directorist pages.

**Hook Type**: Filter  
**Priority**: 20  
**Parameters**:
- `$url` (string) - Language URL
- `$data` (array) - Language data

**Return**: Modified URL

**Used In**: `Filter_Permalinks::filter_lang_switcher_url_for_generic_pages()`

---

## WPML API Hooks

### `wpml_register_single_string`

**Purpose**: Register a string with WPML String Translation.

**Hook Type**: Action  
**Parameters**:
- `$domain` (string) - String domain
- `$name` (string) - String name/ID
- `$value` (string) - String value

**Return**: `void`

**Example**:
```php
do_action( 'wpml_register_single_string', 'directorist-wpml-integration', 'field_label', 'Listing Title' );
```

**Alternative Function**:
```php
icl_register_string( 'directorist-wpml-integration', 'field_label', 'Listing Title' );
```

---

### `wpml_translate_single_string`

**Purpose**: Translate a registered string.

**Hook Type**: Filter  
**Parameters**:
- `$value` (string) - Original string value
- `$domain` (string) - String domain
- `$name` (string) - String name/ID

**Return**: Translated string

**Example**:
```php
$translated = apply_filters( 
    'wpml_translate_single_string', 
    'Listing Title', 
    'directorist-wpml-integration', 
    'field_label' 
);
```

---

### `wpml_current_language`

**Purpose**: Get current language code.

**Hook Type**: Filter  
**Parameters**: `null`

**Return**: Language code (string)

**Example**:
```php
$lang = apply_filters( 'wpml_current_language', null );
// Returns: 'en', 'es', 'fr', etc.
```

---

### `wpml_object_id`

**Purpose**: Get translated object ID for current language.

**Hook Type**: Filter  
**Parameters**:
- `$element_id` (int) - Original object ID
- `$element_type` (string) - Object type
- `$return_original_if_missing` (bool) - Return original if no translation
- `$language_code` (string) - Target language (optional)

**Return**: Translated object ID

**Example**:
```php
$translated_id = apply_filters( 
    'wpml_object_id', 
    123, 
    'page', 
    false, 
    'es' 
);
```

---

### `wpml_element_language_details`

**Purpose**: Get language information for an element.

**Hook Type**: Filter  
**Parameters**:
- `$null` (null) - Always null
- `$args` (array) - Arguments array

**Return**: Language details object

**Example**:
```php
$lang_info = apply_filters( 
    'wpml_element_language_details', 
    null, 
    [
        'element_id' => 123,
        'element_type' => 'post_at_biz_dir'
    ]
);
```

---

## Hook Execution Order

```
1. pre_get_posts (Action)
   ↓
2. directorist_all_listings_query_arguments (Filter)
   ↓
3. WP_Query executes
   ↓
4. directorist_listings_query_results (Filter)
   ↓
5. directorist_directories_for_template (Filter)
   ↓
6. directorist_section_template (Filter)
   ↓
7. directorist_form_field_data (Filter)
   ↓
8. atbdp_add_listing_page_template (Filter)
```

## Best Practices

1. **Use Appropriate Priority**: Lower numbers execute first. Use 5-10 for core functionality.

2. **Check WPML Active**: Always verify WPML is active before using WPML hooks.

3. **Return Original on Failure**: If translation fails, return original value.

4. **Handle Edge Cases**: Check for empty values, wrong types, missing keys.

5. **Performance**: Cache translations when possible, avoid redundant lookups.

## Related Documentation

- [Add Listing Translation](./ADD-LISTING-TRANSLATION.md) - Form translation implementation
- [Developer Notes](./DEVELOPER-NOTES.md) - Coding standards
