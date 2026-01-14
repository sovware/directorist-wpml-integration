# Translation Examples by Content Type

This document provides practical code examples for translating different types of Directorist content with WPML.

## 1. Normal Strings (Labels, Placeholders, Descriptions)

### Challenge
Static text strings in form fields need translation based on current language.

### Solution
Register string with WPML, then translate on every render.

### Code Example

```php
/**
 * Translate field label
 */
add_filter( 'directorist_form_field_data', function( $field_data ) {
    // Get directory ID
    $directory_id = 1; // Get from form instance or request
    $field_key = $field_data['field_key'];
    $field_key_slug = sanitize_key( sanitize_title( $field_key ) );
    
    // Register string name
    $string_name = sprintf( 
        'add_listing_dir_%d_field_%s_label', 
        $directory_id, 
        $field_key_slug 
    );
    
    // Register with WPML (happens once)
    do_action( 
        'wpml_register_single_string', 
        'directorist-wpml-integration',  // Domain
        $string_name,                    // String name
        $field_data['label']             // Original value
    );
    
    // Translate (happens every render)
    $translated = apply_filters( 
        'wpml_translate_single_string', 
        $field_data['label'],            // Original (fallback)
        'directorist-wpml-integration',   // Domain
        $string_name                     // String name
    );
    
    // Update field data
    $field_data['label'] = $translated;
    
    return $field_data;
}, 10, 1 );
```

### String Name Format
```
add_listing_dir_{directory_id}_field_{field_key}_{property}
```

**Examples:**
- `add_listing_dir_1_field_title_label`
- `add_listing_dir_1_field_title_placeholder`
- `add_listing_dir_1_field_title_description`

---

## 2. Arrays (Field Options)

### Challenge
Select, radio, and checkbox fields have arrays of options that need translation.

### Solution
Loop through options array, register and translate each option.

### Code Example

```php
/**
 * Translate field options
 */
add_filter( 'directorist_form_field_data', function( $field_data ) {
    if ( empty( $field_data['options'] ) || ! is_array( $field_data['options'] ) ) {
        return $field_data;
    }
    
    $directory_id = 1;
    $field_key_slug = sanitize_key( sanitize_title( $field_data['field_key'] ) );
    
    foreach ( $field_data['options'] as $option_key => $option ) {
        // Handle array format: ['option_label' => 'Label', 'option_value' => 'value']
        if ( is_array( $option ) && ! empty( $option['option_label'] ) ) {
            $option_label = $option['option_label'];
            $option_value = $option['option_value'] ?? $option_key;
            $option_value_slug = sanitize_key( sanitize_title( $option_value ) );
            
            // Generate string name
            $string_name = sprintf( 
                'add_listing_dir_%d_field_%s_option_%s', 
                $directory_id, 
                $field_key_slug, 
                $option_value_slug 
            );
            
            // Register
            do_action( 
                'wpml_register_single_string', 
                'directorist-wpml-integration', 
                $string_name, 
                $option_label 
            );
            
            // Translate
            $translated = apply_filters( 
                'wpml_translate_single_string', 
                $option_label, 
                'directorist-wpml-integration', 
                $string_name 
            );
            
            // Update option
            if ( $translated !== $option_label ) {
                $field_data['options'][ $option_key ]['option_label'] = $translated;
            }
        }
        // Handle string format
        elseif ( is_string( $option ) ) {
            $option_slug = sanitize_key( sanitize_title( $option ) );
            
            $string_name = sprintf( 
                'add_listing_dir_%d_field_%s_option_%s', 
                $directory_id, 
                $field_key_slug, 
                $option_slug 
            );
            
            do_action( 
                'wpml_register_single_string', 
                'directorist-wpml-integration', 
                $string_name, 
                $option 
            );
            
            $translated = apply_filters( 
                'wpml_translate_single_string', 
                $option, 
                'directorist-wpml-integration', 
                $string_name 
            );
            
            if ( $translated !== $option ) {
                $field_data['options'][ $option_key ] = $translated;
            }
        }
    }
    
    return $field_data;
}, 10, 1 );
```

### String Name Format
```
add_listing_dir_{directory_id}_field_{field_key}_option_{option_value}
```

**Examples:**
- `add_listing_dir_1_field_category_option_restaurant`
- `add_listing_dir_1_field_category_option_cafe`

---

## 3. Dynamic Values (Term Meta, Post Meta)

### Challenge
Form configuration is stored in database (term meta) as serialized JSON, not hardcoded.

### Solution
Extract values from database, register and translate at runtime.

### Code Example

```php
/**
 * Translate dynamic form fields from term meta
 */
add_filter( 'directorist_form_field_data', function( $field_data ) {
    // Field data comes from term meta: submission_form_fields
    // This is already extracted by Directorist, we just translate it
    
    $directory_id = 1; // Get from form instance
    $field_key = $field_data['field_key'];
    
    // All field properties are dynamic (from database)
    $properties_to_translate = ['label', 'placeholder', 'description'];
    
    foreach ( $properties_to_translate as $property ) {
        if ( ! empty( $field_data[ $property ] ) && is_string( $field_data[ $property ] ) ) {
            $field_key_slug = sanitize_key( sanitize_title( $field_key ) );
            $string_name = sprintf( 
                'add_listing_dir_%d_field_%s_%s', 
                $directory_id, 
                $field_key_slug, 
                $property 
            );
            
            // Register
            do_action( 
                'wpml_register_single_string', 
                'directorist-wpml-integration', 
                $string_name, 
                $field_data[ $property ] 
            );
            
            // Translate
            $translated = apply_filters( 
                'wpml_translate_single_string', 
                $field_data[ $property ], 
                'directorist-wpml-integration', 
                $string_name 
            );
            
            // Update
            if ( $translated !== $field_data[ $property ] ) {
                $field_data[ $property ] = $translated;
            }
        }
    }
    
    return $field_data;
}, 10, 1 );
```

### Key Points
- Values come from `get_term_meta( $directory_id, 'submission_form_fields', true )`
- Directorist extracts and provides via filter
- We translate at runtime, not modify database

---

## 4. Checkboxes

### Challenge
Checkbox fields have labels and option values that need translation.

### Solution
Translate checkbox label and each checkbox option.

### Code Example

```php
/**
 * Translate checkbox field
 */
add_filter( 'directorist_form_field_data', function( $field_data ) {
    // Check if checkbox field
    if ( $field_data['widget_name'] !== 'checkbox' ) {
        return $field_data;
    }
    
    $directory_id = 1;
    $field_key_slug = sanitize_key( sanitize_title( $field_data['field_key'] ) );
    
    // Translate checkbox label
    if ( ! empty( $field_data['label'] ) ) {
        $string_name = sprintf( 
            'add_listing_dir_%d_field_%s_label', 
            $directory_id, 
            $field_key_slug 
        );
        
        do_action( 
            'wpml_register_single_string', 
            'directorist-wpml-integration', 
            $string_name, 
            $field_data['label'] 
        );
        
        $field_data['label'] = apply_filters( 
            'wpml_translate_single_string', 
            $field_data['label'], 
            'directorist-wpml-integration', 
            $string_name 
        );
    }
    
    // Translate checkbox options
    if ( ! empty( $field_data['options'] ) && is_array( $field_data['options'] ) ) {
        foreach ( $field_data['options'] as $key => $option ) {
            if ( is_array( $option ) && isset( $option['option_label'] ) ) {
                $option_value = $option['option_value'] ?? $key;
                $option_slug = sanitize_key( sanitize_title( $option_value ) );
                
                $string_name = sprintf( 
                    'add_listing_dir_%d_field_%s_option_%s', 
                    $directory_id, 
                    $field_key_slug, 
                    $option_slug 
                );
                
                do_action( 
                    'wpml_register_single_string', 
                    'directorist-wpml-integration', 
                    $string_name, 
                    $option['option_label'] 
                );
                
                $translated = apply_filters( 
                    'wpml_translate_single_string', 
                    $option['option_label'], 
                    'directorist-wpml-integration', 
                    $string_name 
                );
                
                if ( $translated !== $option['option_label'] ) {
                    $field_data['options'][ $key ]['option_label'] = $translated;
                }
            }
        }
    }
    
    return $field_data;
}, 10, 1 );
```

---

## 5. Post Taxonomies (Directory Types)

### Challenge
Directory type names need translation in frontend and backend.

### Solution
Hook into `get_terms` and `term_name` filters to translate term names.

### Code Example

```php
/**
 * Translate directory type names
 */
add_filter( 'get_terms', function( $terms, $taxonomies, $args, $term_query ) {
    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return $terms;
    }
    
    // Check if directory type taxonomy
    $is_directory_type = false;
    foreach ( $taxonomies as $taxonomy ) {
        if ( $taxonomy === 'atbdp_listing_types' || 
             ( defined( 'ATBDP_DIRECTORY_TYPE' ) && $taxonomy === ATBDP_DIRECTORY_TYPE ) ) {
            $is_directory_type = true;
            break;
        }
    }
    
    if ( ! $is_directory_type ) {
        return $terms;
    }
    
    // Translate each term
    foreach ( $terms as $term ) {
        if ( ! is_object( $term ) || ! isset( $term->name ) ) {
            continue;
        }
        
        $original_name = $term->name;
        
        // Generate string name
        $string_name = sprintf( 'directory_type_%d_name', $term->term_id );
        
        // Register
        do_action( 
            'wpml_register_single_string', 
            'directorist-wpml-integration', 
            $string_name, 
            $original_name 
        );
        
        // Translate
        $translated = apply_filters( 
            'wpml_translate_single_string', 
            $original_name, 
            'directorist-wpml-integration', 
            $string_name 
        );
        
        // Update term name
        if ( $translated !== $original_name ) {
            $term->name = $translated;
        }
    }
    
    return $terms;
}, 10, 4 );

/**
 * Translate directory type names in admin
 */
add_filter( 'term_name', function( $name, $term ) {
    if ( empty( $term ) || ! is_object( $term ) ) {
        return $name;
    }
    
    // Check if directory type
    if ( isset( $term->taxonomy ) && 
         ( $term->taxonomy === 'atbdp_listing_types' || 
           ( defined( 'ATBDP_DIRECTORY_TYPE' ) && $term->taxonomy === ATBDP_DIRECTORY_TYPE ) ) ) {
        
        $string_name = sprintf( 'directory_type_%d_name', $term->term_id );
        
        do_action( 
            'wpml_register_single_string', 
            'directorist-wpml-integration', 
            $string_name, 
            $name 
        );
        
        $translated = apply_filters( 
            'wpml_translate_single_string', 
            $name, 
            'directorist-wpml-integration', 
            $string_name 
        );
        
        return $translated;
    }
    
    return $name;
}, 10, 2 );
```

### String Name Format
```
directory_type_{term_id}_name
```

**Examples:**
- `directory_type_1_name`
- `directory_type_2_name`

---

## 6. Categories

### Challenge
Category names need translation in listings, filters, and admin.

### Solution
Hook into `get_terms` filter for category taxonomy.

### Code Example

```php
/**
 * Translate category names
 */
add_filter( 'get_terms', function( $terms, $taxonomies, $args, $term_query ) {
    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return $terms;
    }
    
    // Check if category taxonomy
    $is_category = false;
    foreach ( $taxonomies as $taxonomy ) {
        if ( $taxonomy === 'atbdp_category' || 
             ( defined( 'ATBDP_CATEGORY' ) && $taxonomy === ATBDP_CATEGORY ) ) {
            $is_category = true;
            break;
        }
    }
    
    if ( ! $is_category ) {
        return $terms;
    }
    
    // Translate each category
    foreach ( $terms as $term ) {
        if ( ! is_object( $term ) || ! isset( $term->name ) ) {
            continue;
        }
        
        $original_name = $term->name;
        
        // Generate string name
        $string_name = sprintf( 'category_%d_name', $term->term_id );
        
        // Register
        do_action( 
            'wpml_register_single_string', 
            'directorist-wpml-integration', 
            $string_name, 
            $original_name 
        );
        
        // Translate
        $translated = apply_filters( 
            'wpml_translate_single_string', 
            $original_name, 
            'directorist-wpml-integration', 
            $string_name 
        );
        
        // Update term name
        if ( $translated !== $original_name ) {
            $term->name = $translated;
        }
    }
    
    return $terms;
}, 10, 4 );
```

### String Name Format
```
category_{term_id}_name
```

**Note**: Categories can also use WPML's native taxonomy translation. This example shows string translation approach.

---

## 7. Tags

### Challenge
Tag names need translation similar to categories.

### Code Example

```php
/**
 * Translate tag names
 */
add_filter( 'get_terms', function( $terms, $taxonomies, $args, $term_query ) {
    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        return $terms;
    }
    
    // Check if tag taxonomy
    $is_tag = false;
    foreach ( $taxonomies as $taxonomy ) {
        if ( $taxonomy === 'at_biz_dir-tags' || 
             ( defined( 'ATBDP_TAGS' ) && $taxonomy === ATBDP_TAGS ) ) {
            $is_tag = true;
            break;
        }
    }
    
    if ( ! $is_tag ) {
        return $terms;
    }
    
    // Translate each tag
    foreach ( $terms as $term ) {
        if ( ! is_object( $term ) || ! isset( $term->name ) ) {
            continue;
        }
        
        $original_name = $term->name;
        $string_name = sprintf( 'tag_%d_name', $term->term_id );
        
        do_action( 
            'wpml_register_single_string', 
            'directorist-wpml-integration', 
            $string_name, 
            $original_name 
        );
        
        $translated = apply_filters( 
            'wpml_translate_single_string', 
            $original_name, 
            'directorist-wpml-integration', 
            $string_name 
        );
        
        if ( $translated !== $original_name ) {
            $term->name = $translated;
        }
    }
    
    return $terms;
}, 10, 4 );
```

### String Name Format
```
tag_{term_id}_name
```

---

## Complete Helper Class Example

```php
<?php
/**
 * Complete translation helper class
 */
class Directorist_WPML_Translator {
    
    const WPML_DOMAIN = 'directorist-wpml-integration';
    
    /**
     * Register string with WPML
     */
    private function register_string( $name, $value ) {
        if ( empty( $value ) || ! is_string( $value ) ) {
            return;
        }
        
        // Skip on WPML admin page
        if ( is_admin() && ! empty( $_GET['page'] ) && 
             strpos( $_GET['page'], 'wpml-string-translation' ) !== false ) {
            return;
        }
        
        // Suppress WPML warnings
        ob_start();
        $error_level = error_reporting();
        error_reporting( $error_level & ~E_WARNING );
        
        try {
            if ( function_exists( 'icl_register_string' ) ) {
                icl_register_string( self::WPML_DOMAIN, $name, $value );
            } else {
                do_action( 'wpml_register_single_string', self::WPML_DOMAIN, $name, $value );
            }
        } catch ( \Exception $e ) {
            // Silently handle
        } finally {
            error_reporting( $error_level );
            ob_end_clean();
        }
    }
    
    /**
     * Translate string
     */
    private function translate_string( $value, $name ) {
        if ( empty( $value ) || ! is_string( $value ) ) {
            return $value;
        }
        
        $translated = apply_filters( 
            'wpml_translate_single_string', 
            $value, 
            self::WPML_DOMAIN, 
            $name 
        );
        
        // Return original if translation is empty or same
        if ( empty( $translated ) || $translated === $value ) {
            return $value;
        }
        
        return $translated;
    }
    
    /**
     * Safe slug generation
     */
    private function safe_slug( $text ) {
        return sanitize_key( sanitize_title( $text ) );
    }
    
    /**
     * Translate field property
     */
    public function translate_field_property( $value, $directory_id, $field_key, $property ) {
        $field_key_slug = $this->safe_slug( $field_key );
        $string_name = sprintf( 
            'add_listing_dir_%d_field_%s_%s', 
            $directory_id, 
            $field_key_slug, 
            $property 
        );
        
        $this->register_string( $string_name, $value );
        return $this->translate_string( $value, $string_name );
    }
    
    /**
     * Translate term name
     */
    public function translate_term_name( $name, $term_id, $taxonomy_type = 'directory_type' ) {
        $string_name = sprintf( '%s_%d_name', $taxonomy_type, $term_id );
        
        $this->register_string( $string_name, $name );
        return $this->translate_string( $name, $string_name );
    }
}
```

---

## Best Practices Summary

1. **Always Register Before Translating**: Registration happens once, translation every render
2. **Use Unique String Names**: Include directory ID, field key, property
3. **Provide Fallbacks**: Return original if translation is empty
4. **Handle Edge Cases**: Check for empty values, wrong types, missing keys
5. **Suppress WPML Warnings**: Use output buffering for registration
6. **Check WPML Active**: Verify WPML is installed before using hooks
7. **Use Appropriate Hooks**: Choose hooks that provide the right data at the right time

---

## Related Documentation

- [String Key Strategy](./STRING-KEY-STRATEGY.md) - Naming conventions
- [Integration Hooks](./INTEGRATION-HOOKS.md) - Complete hook reference
- [Add Listing Translation](./ADD-LISTING-TRANSLATION.md) - Implementation details
