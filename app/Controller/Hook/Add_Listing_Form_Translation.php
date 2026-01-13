<?php
/**
 * Add Listing Form Translation Integration
 * 
 * Makes Directorist Add Listing Form fully translatable with WPML String Translation.
 * Handles dynamic form fields and sections created via Directorist Form Builder.
 * 
 * @package Directorist_WPML_Integration
 * @since 2.1.5
 */

namespace Directorist_WPML_Integration\Controller\Hook;

class Add_Listing_Form_Translation {

    /**
     * WPML String Translation Domain
     * 
     * @var string
     */
    const WPML_DOMAIN = 'directorist-wpml-integration';

    /**
     * Store section translations for output replacement
     * 
     * @var array
     */
    private static $section_translations = [];

    /**
     * Constructor
     * 
     * Registers hooks for field and section translation.
     * 
     * @return void
     */
    public function __construct() {
        // Translate field data (labels, placeholders, descriptions, options)
        add_filter( 'directorist_form_field_data', [ $this, 'translate_field_data' ], 10, 1 );
        
        // Translate section labels and store for output replacement
        add_filter( 'directorist_section_template', [ $this, 'translate_section_label' ], 10, 2 );
        
        // Replace section labels in template output (fallback for cases where filter doesn't persist)
        add_filter( 'atbdp_add_listing_page_template', [ $this, 'translate_section_labels_in_output' ], 10, 2 );
    }

    /**
     * Check if WPML is active
     * 
     * @return bool
     */
    private function is_wpml_active() {
        return (
            defined( 'ICL_SITEPRESS_VERSION' ) &&
            function_exists( 'do_action' ) &&
            function_exists( 'apply_filters' )
        );
    }

    /**
     * Get current directory type ID
     * 
     * Extracts directory_id from ListingForm instance in field_data.
     * 
     * @param array $field_data Field data array
     * @return int Directory type ID, 0 if not found
     */
    private function get_directory_id_from_field_data( $field_data ) {
        // Check if ListingForm instance is available in field_data
        if ( ! empty( $field_data['form'] ) && is_object( $field_data['form'] ) ) {
            if ( method_exists( $field_data['form'], 'get_current_listing_type' ) ) {
                $directory_id = $field_data['form']->get_current_listing_type();
                if ( ! empty( $directory_id ) ) {
                    return (int) $directory_id;
                }
            }
        }

        // Fallback: Try to get from global context
        if ( ! empty( $_REQUEST['directory_type'] ) ) {
            $directory_type = sanitize_text_field( wp_unslash( $_REQUEST['directory_type'] ) );
            if ( is_numeric( $directory_type ) ) {
                return (int) $directory_type;
            } else {
                $term = get_term_by( 'slug', $directory_type, 'atbdp_listing_types' );
                if ( $term ) {
                    return (int) $term->term_id;
                }
            }
        }

        return 0;
    }

    /**
     * Get directory ID from section template args
     * 
     * @param array $args Section template arguments
     * @return int Directory type ID, 0 if not found
     */
    private function get_directory_id_from_section_args( $args ) {
        // Check if ListingForm instance is available in args
        if ( ! empty( $args['listing_form'] ) && is_object( $args['listing_form'] ) ) {
            if ( method_exists( $args['listing_form'], 'get_current_listing_type' ) ) {
                $directory_id = $args['listing_form']->get_current_listing_type();
                if ( ! empty( $directory_id ) ) {
                    return (int) $directory_id;
                }
            }
        }

        return 0;
    }

    /**
     * Generate safe slug for string naming
     * 
     * @param string $text Text to slugify
     * @return string Safe slug
     */
    private function safe_slug( $text ) {
        return sanitize_key( sanitize_title( $text ) );
    }

    /**
     * Register string with WPML
     * 
     * @param string $name String name/ID
     * @param string $value String value
     * @return void
     */
    private function register_wpml_string( $name, $value ) {
        if ( ! $this->is_wpml_active() || empty( $value ) || ! is_string( $value ) ) {
            return;
        }

        // Skip registration on WPML String Translation admin page to avoid Gutenberg processing conflicts
        if ( is_admin() && ! empty( $_GET['page'] ) && strpos( $_GET['page'], 'wpml-string-translation' ) !== false ) {
            return;
        }

        // Prevent registration during AJAX requests that might trigger WPML Gutenberg processing
        if ( wp_doing_ajax() && ! empty( $_REQUEST['action'] ) && strpos( $_REQUEST['action'], 'wpml' ) !== false ) {
            return;
        }

        // Use output buffering to catch and suppress WPML Gutenberg integration warnings
        // These warnings are from WPML's own buggy code (class-wpml-gutenberg-config-option.php line 228)
        // where it tries to access $key_config['attr']['name'] but 'attr' key doesn't exist
        // This happens when WPML processes Gutenberg block configurations during string registration
        ob_start();
        
        // Suppress warnings during registration
        $error_level = error_reporting();
        error_reporting( $error_level & ~E_WARNING );
        
        try {
            // Use WPML String Translation function
            // Note: This uses the standard WPML API
            if ( function_exists( 'icl_register_string' ) ) {
                icl_register_string( self::WPML_DOMAIN, $name, $value );
            } else {
                // Fallback to action hook if function doesn't exist
                do_action( 'wpml_register_single_string', self::WPML_DOMAIN, $name, $value );
            }
        } catch ( \Exception $e ) {
            // Silently handle any exceptions during registration
            // WPML will handle duplicate registrations gracefully
        } finally {
            // Restore original error reporting level
            error_reporting( $error_level );
            
            // Discard any output (including warnings) from WPML's buggy code
            ob_end_clean();
        }
    }

    /**
     * Translate string via WPML
     * 
     * @param string $value Original string value
     * @param string $name String name/ID
     * @return string Translated string or original if no translation
     */
    private function translate_wpml_string( $value, $name ) {
        if ( ! $this->is_wpml_active() || empty( $value ) || ! is_string( $value ) ) {
            return $value;
        }

        // Use WPML String Translation filter hook
        $translated = apply_filters( 'wpml_translate_single_string', $value, self::WPML_DOMAIN, $name );

        // If translation is empty or same as original, return original
        // This ensures we never lose the label even if translation is empty
        if ( empty( $translated ) || $translated === $value ) {
            return $value;
        }

        return $translated;
    }

    /**
     * Translate field data
     * 
     * Hook: directorist_form_field_data
     * Priority: 10
     * 
     * Translates:
     * - Field label
     * - Field placeholder
     * - Field description
     * - Field options (for select/radio/checkbox)
     * 
     * @param array $field_data Field data array
     * @return array Translated field data
     */
    public function translate_field_data( $field_data ) {
        // Safety checks
        if ( empty( $field_data ) || ! is_array( $field_data ) ) {
            return $field_data;
        }

        if ( ! $this->is_wpml_active() ) {
            return $field_data;
        }

        // Get directory ID
        $directory_id = $this->get_directory_id_from_field_data( $field_data );
        if ( empty( $directory_id ) ) {
            return $field_data;
        }

        // Get field key
        $field_key = ! empty( $field_data['field_key'] ) ? $field_data['field_key'] : '';
        if ( empty( $field_key ) ) {
            return $field_data;
        }

        $field_key_slug = $this->safe_slug( $field_key );

        // Translate field label
        if ( ! empty( $field_data['label'] ) && is_string( $field_data['label'] ) ) {
            $string_name = sprintf( 'add_listing_dir_%d_field_%s_label', $directory_id, $field_key_slug );
            
            $this->register_wpml_string( $string_name, $field_data['label'] );
            $translated = $this->translate_wpml_string( $field_data['label'], $string_name );
            
            if ( ! empty( $translated ) && $translated !== $field_data['label'] ) {
                $field_data['label'] = $translated;
            }
        }

        // Translate field placeholder
        if ( ! empty( $field_data['placeholder'] ) && is_string( $field_data['placeholder'] ) ) {
            $string_name = sprintf( 'add_listing_dir_%d_field_%s_placeholder', $directory_id, $field_key_slug );
            
            $this->register_wpml_string( $string_name, $field_data['placeholder'] );
            $translated = $this->translate_wpml_string( $field_data['placeholder'], $string_name );
            
            if ( ! empty( $translated ) && $translated !== $field_data['placeholder'] ) {
                $field_data['placeholder'] = $translated;
            }
        }

        // Translate field description
        if ( ! empty( $field_data['description'] ) && is_string( $field_data['description'] ) ) {
            $string_name = sprintf( 'add_listing_dir_%d_field_%s_description', $directory_id, $field_key_slug );
            
            $this->register_wpml_string( $string_name, $field_data['description'] );
            $translated = $this->translate_wpml_string( $field_data['description'], $string_name );
            
            if ( ! empty( $translated ) && $translated !== $field_data['description'] ) {
                $field_data['description'] = $translated;
            }
        }

        // Translate custom field properties (e.g., pricing field: price_unit_field_label, price_range_label)
        // These are field-specific properties that contain translatable strings
        // Pattern: properties ending with _label, _placeholder, or _text are likely translatable
        $custom_property_patterns = [ '_label', '_placeholder', '_text', '_title', '_description' ];
        
        foreach ( $field_data as $property_key => $property_value ) {
            // Skip standard properties we already handle
            if ( in_array( $property_key, [ 'label', 'placeholder', 'description', 'options', 'field_key', 'widget_name', 'widget_group', 'form', 'value' ] ) ) {
                continue;
            }
            
            // Skip non-string values or empty values
            if ( ! is_string( $property_value ) || empty( $property_value ) ) {
                continue;
            }
            
            // Check if property name suggests it's translatable
            $is_translatable = false;
            foreach ( $custom_property_patterns as $pattern ) {
                if ( strpos( $property_key, $pattern ) !== false ) {
                    $is_translatable = true;
                    break;
                }
            }
            
            // Also check known custom properties
            $known_custom_properties = [
                'price_unit_field_label',
                'price_range_label',
                'price_range_placeholder',
                'price_unit_field_placeholder',
            ];
            
            if ( $is_translatable || in_array( $property_key, $known_custom_properties ) ) {
                $string_name = sprintf( 'add_listing_dir_%d_field_%s_%s', $directory_id, $field_key_slug, $this->safe_slug( $property_key ) );
                
                $this->register_wpml_string( $string_name, $property_value );
                $translated = $this->translate_wpml_string( $property_value, $string_name );
                
                if ( ! empty( $translated ) && $translated !== $property_value ) {
                    $field_data[ $property_key ] = $translated;
                }
            }
        }

        // Translate field options (for select, radio, checkbox fields)
        if ( ! empty( $field_data['options'] ) && is_array( $field_data['options'] ) ) {
            foreach ( $field_data['options'] as $option_key => $option ) {
                // Handle array format: ['option_label' => 'Label', 'option_value' => 'value']
                if ( is_array( $option ) && ! empty( $option['option_label'] ) ) {
                    $option_label = $option['option_label'];
                    $option_value = ! empty( $option['option_value'] ) ? $option['option_value'] : $option_key;
                    $option_value_slug = $this->safe_slug( $option_value );
                    
                    $string_name = sprintf( 
                        'add_listing_dir_%d_field_%s_option_%s', 
                        $directory_id, 
                        $field_key_slug, 
                        $option_value_slug 
                    );
                    
                    $this->register_wpml_string( $string_name, $option_label );
                    $translated = $this->translate_wpml_string( $option_label, $string_name );
                    
                    if ( ! empty( $translated ) && $translated !== $option_label ) {
                        $field_data['options'][ $option_key ]['option_label'] = $translated;
                    }
                } 
                // Handle simple string format
                elseif ( is_string( $option ) ) {
                    $option_slug = $this->safe_slug( $option );
                    
                    $string_name = sprintf( 
                        'add_listing_dir_%d_field_%s_option_%s', 
                        $directory_id, 
                        $field_key_slug, 
                        $option_slug 
                    );
                    
                    $this->register_wpml_string( $string_name, $option );
                    $translated = $this->translate_wpml_string( $option, $string_name );
                    
                    if ( ! empty( $translated ) && $translated !== $option ) {
                        $field_data['options'][ $option_key ] = $translated;
                    }
                }
            }
        }

        return $field_data;
    }

    /**
     * Translate section label
     * 
     * Hook: directorist_section_template
     * Priority: 10
     * 
     * Translates section labels in the template arguments.
     * Note: Modifies $args by reference where possible, but also stores
     * translation for output replacement if needed.
     * 
     * @param bool $load_section Whether to load the section
     * @param array $args Template arguments containing 'section_data' and 'listing_form'
     * @return bool Whether to load the section (unchanged)
     */
    public function translate_section_label( $load_section, $args ) {
        // Safety checks
        if ( empty( $args ) || ! is_array( $args ) ) {
            return $load_section;
        }

        if ( empty( $args['section_data'] ) || ! is_array( $args['section_data'] ) ) {
            return $load_section;
        }

        if ( ! $this->is_wpml_active() ) {
            return $load_section;
        }

        // Get directory ID
        $directory_id = $this->get_directory_id_from_section_args( $args );
        if ( empty( $directory_id ) ) {
            return $load_section;
        }

        // Translate section label
        if ( ! empty( $args['section_data']['label'] ) && is_string( $args['section_data']['label'] ) ) {
            $original_label = $args['section_data']['label'];
            
            // Generate section slug from label or use section key if available
            $section_slug = ! empty( $args['section_data']['key'] ) 
                ? $this->safe_slug( $args['section_data']['key'] ) 
                : $this->safe_slug( $original_label );
            
            $string_name = sprintf( 'add_listing_dir_%d_section_%s_label', $directory_id, $section_slug );
            
            // Register and translate
            $this->register_wpml_string( $string_name, $original_label );
            $translated = $this->translate_wpml_string( $original_label, $string_name );
            
            // Store translation for output replacement (since $args is passed by value)
            // Always store even if same, so output replacement can use it
            $translation_key = sprintf( '%d_%s', $directory_id, $section_slug );
            self::$section_translations[ $translation_key ] = [
                'original' => $original_label,
                'translated' => $translated,
                'string_name' => $string_name,
            ];
            
            // Try to modify args (may not persist due to pass-by-value, but worth trying)
            // Only modify if translation exists and is different
            if ( $translated !== $original_label && ! empty( $translated ) ) {
                $args['section_data']['label'] = $translated;
            }
        }

        return $load_section;
    }

    /**
     * Translate section labels in template output HTML
     * 
     * Hook: atbdp_add_listing_page_template
     * Priority: 10
     * 
     * This is a fallback method to replace section labels in the rendered HTML
     * when the filter approach doesn't persist due to pass-by-value limitations.
     * 
     * @param string $template_output The rendered template HTML
     * @param array $args Template arguments containing 'form_data'
     * @return string Modified template output
     */
    public function translate_section_labels_in_output( $template_output, $args ) {
        // Safety checks
        if ( empty( $template_output ) || ! is_string( $template_output ) ) {
            return $template_output;
        }

        if ( ! $this->is_wpml_active() ) {
            return $template_output;
        }

        // Get form_data from args
        $form_data = ! empty( $args['form_data'] ) ? $args['form_data'] : [];
        if ( empty( $form_data ) || ! is_array( $form_data ) ) {
            return $template_output;
        }

        // Get directory ID from first section or args
        $directory_id = 0;
        if ( ! empty( $args['single_directory'] ) ) {
            $directory_id = (int) $args['single_directory'];
        } elseif ( ! empty( $form_data[0] ) ) {
            // Try to extract from form_data context
            $directory_id = $this->get_directory_id_from_section_args( [ 'listing_form' => $args['listing_form'] ?? null ] );
        }

        if ( empty( $directory_id ) ) {
            return $template_output;
        }

        // Translate all section labels in output
        foreach ( $form_data as $section ) {
            if ( empty( $section['label'] ) || ! is_string( $section['label'] ) ) {
                continue;
            }

            $original_label = $section['label'];
            
            // Use section key if available, otherwise use label for slug generation
            // This ensures consistent string name matching
            $section_slug = ! empty( $section['key'] ) 
                ? $this->safe_slug( $section['key'] ) 
                : $this->safe_slug( $original_label );
            
            $string_name = sprintf( 'add_listing_dir_%d_section_%s_label', $directory_id, $section_slug );
            $translation_key = sprintf( '%d_%s', $directory_id, $section_slug );

            // Check if we already have translation stored from translate_section_label()
            $translated = null;
            if ( ! empty( self::$section_translations[ $translation_key ] ) ) {
                $translated = self::$section_translations[ $translation_key ]['translated'];
            } else {
                // Register string (WPML ignores duplicates)
                $this->register_wpml_string( $string_name, $original_label );
                
                // Get translation
                $translated = $this->translate_wpml_string( $original_label, $string_name );
                
                // Store for future use
                self::$section_translations[ $translation_key ] = [
                    'original' => $original_label,
                    'translated' => $translated,
                    'string_name' => $string_name,
                ];
            }

            // Always replace if translation exists and is different
            // translate_wpml_string() already handles empty translations by returning original
            if ( $translated !== $original_label && ! empty( $translated ) ) {
                // Prepare both escaped and unescaped versions
                $escaped_original = esc_html( $original_label );
                $escaped_translated = esc_html( $translated );
                
                // Escape special regex characters
                $regex_original = preg_quote( $original_label, '/' );
                $regex_escaped_original = preg_quote( $escaped_original, '/' );

                // Replace in navigation buttons (multistep-wizard__nav__btn)
                // Pattern accounts for icon before label: <a>...icon...label</a>
                // Label is NOT escaped in add-listing.php line 41, so match unescaped version
                $pattern_nav_unescaped = '/(<a[^>]*class="[^"]*multistep-wizard__nav__btn[^"]*"[^>]*>)(.*?)' . $regex_original . '(<\/a>)/is';
                $template_output = preg_replace( $pattern_nav_unescaped, '$1$2' . $translated . '$3', $template_output );
                
                // Also try escaped version (for safety)
                $pattern_nav_escaped = '/(<a[^>]*class="[^"]*multistep-wizard__nav__btn[^"]*"[^>]*>)(.*?)' . $regex_escaped_original . '(<\/a>)/is';
                $template_output = preg_replace( $pattern_nav_escaped, '$1$2' . $escaped_translated . '$3', $template_output );

                // Replace in section headers (directorist-content-module__title)
                // Header uses esc_html() so match escaped version
                $pattern_header = '/(<h2[^>]*class="[^"]*directorist-content-module__title[^"]*"[^>]*>)(.*?)' . $regex_escaped_original . '(<\/h2>)/is';
                $template_output = preg_replace( $pattern_header, '$1$2' . $escaped_translated . '$3', $template_output );
                
                // Also try unescaped header (for safety)
                $pattern_header_unescaped = '/(<h2[^>]*class="[^"]*directorist-content-module__title[^"]*"[^>]*>)(.*?)' . $regex_original . '(<\/h2>)/is';
                $template_output = preg_replace( $pattern_header_unescaped, '$1$2' . $translated . '$3', $template_output );

                // Fallback: Direct string replacement for any remaining instances
                // Replace unescaped version
                $template_output = str_replace( '>' . $original_label . '<', '>' . $translated . '<', $template_output );
                $template_output = str_replace( '>' . $original_label . '</', '>' . $translated . '</', $template_output );
                // Replace escaped version
                $template_output = str_replace( '>' . $escaped_original . '<', '>' . $escaped_translated . '<', $template_output );
                $template_output = str_replace( '>' . $escaped_original . '</', '>' . $escaped_translated . '</', $template_output );
            }
        }

        return $template_output;
    }
}
