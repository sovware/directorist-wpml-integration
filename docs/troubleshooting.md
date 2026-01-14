# Troubleshooting Guide

This guide covers common issues and their solutions when using Directorist WPML Integration.

## Common Issues

### 1. Strings Not Showing in WPML String Translation

**Symptoms**:
- Strings don't appear in WPML → String Translation
- Domain `directorist-wpml-integration` is empty
- No strings registered

**Possible Causes**:
1. Strings haven't been registered yet
2. WPML String Translation not active
3. Plugin not active
4. Cache issues

**Solutions**:

**Step 1**: Verify Prerequisites
```php
// Check WPML is active
if ( ! defined( 'ICL_SITEPRESS_VERSION' ) ) {
    // WPML not active
}

// Check String Translation is active
if ( ! function_exists( 'icl_register_string' ) ) {
    // String Translation not active
}
```

**Step 2**: Trigger String Registration
1. Visit Add Listing page in default language
2. Navigate through all form sections
3. Submit form (or preview)
4. Check WPML String Translation again

**Step 3**: Clear Caches
1. Clear WordPress cache (if using caching plugin)
2. Clear WPML cache: WPML → Support → Clear Cache
3. Clear browser cache

**Step 4**: Check Plugin Activation
1. Verify Directorist WPML Integration is active
2. Verify Directorist plugin is active
3. Verify WPML plugins are active

**Debug Code**:
```php
// Add to functions.php temporarily
add_action( 'wp_footer', function() {
    if ( current_user_can( 'manage_options' ) ) {
        echo '<pre>';
        echo 'WPML Active: ' . ( defined( 'ICL_SITEPRESS_VERSION' ) ? 'Yes' : 'No' ) . "\n";
        echo 'String Translation Active: ' . ( function_exists( 'icl_register_string' ) ? 'Yes' : 'No' ) . "\n";
        echo '</pre>';
    }
} );
```

---

### 2. Translations Not Applying

**Symptoms**:
- Strings are translated in WPML admin
- Translations don't appear on frontend
- Original strings still showing

**Possible Causes**:
1. Translation not marked as "Complete"
2. Wrong language selected
3. Cache issues
4. String name mismatch

**Solutions**:

**Step 1**: Verify Translation Status
1. Go to WPML → String Translation
2. Check translation status is "Complete" (green checkmark)
3. If "In Progress", click to complete

**Step 2**: Check Current Language
1. Verify current language matches translation language
2. Use language switcher to switch languages
3. Check URL parameter: `?lang=es` (for Spanish)

**Step 3**: Clear Caches
1. Clear WordPress cache
2. Clear WPML cache
3. Clear browser cache
4. Hard refresh: Ctrl+F5 (Windows) or Cmd+Shift+R (Mac)

**Step 4**: Verify String Name
1. Check string name matches exactly
2. Verify directory ID is correct
3. Check field key matches

**Debug Code**:
```php
// Add to functions.php temporarily
add_filter( 'directorist_form_field_data', function( $field_data ) {
    if ( current_user_can( 'manage_options' ) && ! empty( $field_data['label'] ) ) {
        $directory_id = 1; // Your directory ID
        $field_key = $field_data['field_key'];
        $string_name = sprintf( 'add_listing_dir_%d_field_%s_label', $directory_id, sanitize_key( $field_key ) );
        
        $translated = apply_filters( 'wpml_translate_single_string', $field_data['label'], 'directorist-wpml-integration', $string_name );
        
        error_log( "String: $string_name" );
        error_log( "Original: " . $field_data['label'] );
        error_log( "Translated: " . $translated );
    }
    return $field_data;
}, 999 );
```

---

### 3. Wrong Strings Being Translated

**Symptoms**:
- Wrong translations appearing
- Translations from different directory types mixing
- Incorrect field translations

**Possible Causes**:
1. String name collision
2. Wrong directory ID
3. Cache showing old translations

**Solutions**:

**Step 1**: Verify Directory ID
```php
// Check directory ID is correct
add_filter( 'directorist_form_field_data', function( $field_data ) {
    if ( current_user_can( 'manage_options' ) ) {
        $directory_id = $this->get_directory_id_from_field_data( $field_data );
        error_log( "Directory ID: " . $directory_id );
        error_log( "Field Key: " . $field_data['field_key'] );
    }
    return $field_data;
}, 999 );
```

**Step 2**: Check String Names
1. Go to WPML → String Translation
2. Search for the string name
3. Verify directory ID in string name matches
4. Check for duplicate strings

**Step 3**: Clear Translation Cache
1. WPML → Support → Clear Cache
2. Clear WordPress cache
3. Clear browser cache

---

### 4. Listings Not Filtering by Language

**Symptoms**:
- All language listings showing
- Count shows wrong number
- Listings from other languages visible

**Possible Causes**:
1. Query filtering not working
2. WPML language filtering disabled
3. Cache issues

**Solutions**:

**Step 1**: Verify Query Filtering
```php
// Check suppress_filters is false
add_filter( 'directorist_all_listings_query_arguments', function( $args ) {
    if ( current_user_can( 'manage_options' ) ) {
        error_log( 'suppress_filters: ' . ( $args['suppress_filters'] ? 'true' : 'false' ) );
    }
    return $args;
}, 999 );
```

**Step 2**: Check WPML Language Filtering
1. WPML → Settings → Post Types Translation
2. Verify `at_biz_dir` post type is set to "Translatable"
3. Save settings

**Step 3**: Clear Caches
1. Clear WordPress cache
2. Clear WPML cache
3. Clear browser cache

**Step 4**: Verify Current Language
```php
// Check current language
$current_lang = apply_filters( 'wpml_current_language', null );
error_log( "Current Language: " . $current_lang );
```

---

### 5. Section Labels Not Translating

**Symptoms**:
- Field labels translate correctly
- Section labels don't translate
- Section labels show in original language

**Possible Causes**:
1. Section filter not persisting
2. Output replacement not working
3. Cache issues

**Solutions**:

**Step 1**: Verify Section Translation
1. Check string exists in WPML: `add_listing_dir_{id}_section_{key}_label`
2. Verify translation is complete
3. Check current language

**Step 2**: Check Output Replacement
```php
// Verify output replacement is working
add_filter( 'atbdp_add_listing_page_template', function( $output, $args ) {
    if ( current_user_can( 'manage_options' ) ) {
        error_log( "Output length: " . strlen( $output ) );
        error_log( "Contains section label: " . ( strpos( $output, 'Basic Information' ) !== false ? 'Yes' : 'No' ) );
    }
    return $output;
}, 999, 2 );
```

**Step 3**: Clear Caches
1. Clear WordPress cache
2. Clear WPML cache
3. Clear browser cache

---

### 6. Cache Issues

**Symptoms**:
- Translations not updating
- Old translations showing
- Changes not reflecting

**Solutions**:

**Clear All Caches**:
1. **WordPress Cache**: Clear via caching plugin (WP Rocket, W3 Total Cache, etc.)
2. **WPML Cache**: WPML → Support → Clear Cache
3. **Browser Cache**: Hard refresh (Ctrl+F5 or Cmd+Shift+R)
4. **Object Cache**: If using Redis/Memcached, flush it
5. **CDN Cache**: If using CDN, purge cache

**Disable Caching Temporarily**:
```php
// Add to wp-config.php temporarily
define( 'WP_CACHE', false );
```

---

### 7. Multi-Language Switching Issues

**Symptoms**:
- Language switcher not working
- URLs not translating
- Wrong language content showing

**Solutions**:

**Step 1**: Verify WPML Language Switcher
1. WPML → Languages → Language Switcher Options
2. Verify language switcher is configured
3. Check URL format settings

**Step 2**: Check Permalink Settings
1. Settings → Permalinks
2. Verify permalink structure is set
3. Click "Save Changes" to flush rewrite rules

**Step 3**: Verify Page Translations
1. Check all Directorist pages are translated
2. Verify page translations are linked in WPML
3. Check page slugs are translated

---

## Debugging Tools

### Enable Debug Mode

Add to `wp-config.php`:
```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

### Check Logs

Logs are written to: `wp-content/debug.log`

### Debug Hooks

```php
// Debug string registration
add_action( 'wpml_register_single_string', function( $domain, $name, $value ) {
    if ( $domain === 'directorist-wpml-integration' ) {
        error_log( "Registered: $name = $value" );
    }
}, 10, 3 );

// Debug string translation
add_filter( 'wpml_translate_single_string', function( $value, $domain, $name ) {
    if ( $domain === 'directorist-wpml-integration' ) {
        error_log( "Translating: $name (original: $value)" );
    }
    return $value;
}, 10, 3 );
```

## Getting Help

### Information to Provide

When reporting issues, provide:

1. **WordPress Version**: Settings → About
2. **Directorist Version**: Plugins page
3. **WPML Version**: WPML → Support
4. **Plugin Version**: Plugins page
5. **PHP Version**: WPML → Support → System Status
6. **Error Messages**: From debug.log
7. **Steps to Reproduce**: Detailed steps
8. **Screenshots**: If applicable

### Support Channels

- Plugin repository issues
- WPML support forum
- Directorist support

## Related Documentation

- [Admin Usage](./ADMIN-USAGE.md) - How to translate strings
- [Integration Hooks](./INTEGRATION-HOOKS.md) - Hook reference
- [Developer Notes](./DEVELOPER-NOTES.md) - Development guidelines
