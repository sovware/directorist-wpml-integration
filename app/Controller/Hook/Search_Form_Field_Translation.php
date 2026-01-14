<?php
/**
 * Search Form Field Translation Integration
 * 
 * Makes Directorist Search Form field labels fully translatable with WPML String Translation.
 * Handles dynamic search form fields like Review, Tags, and other filter fields.
 * 
 * @package Directorist_WPML_Integration
 * @since 2.1.6
 */

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Search_Form_Field_Translation {

    /**
     * WPML String Translation Domain
     * 
     * @var string
     */
    const WPML_DOMAIN = 'directorist-wpml-integration';

    /**
     * Constructor
     * 
     * Registers hooks for search form field translation.
     * 
     * @return void
     */
    public function __construct() {
        // Modify the $args array before extract() is called in Helper::get_template
        add_filter( 'directorist_template', [ $this, 'modify_template_args_before_extract' ], 10, 2 );
        
        // Use output buffering to translate hardcoded strings and ensure labels are translated
        add_action( 'before_directorist_template_loaded', [ $this, 'start_output_buffer' ], 10, 3 );
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
     * Get directory ID from current context
     * 
     * @return int Directory ID
     */
    private function get_directory_id_from_context() {
        // Try to get from backtrace (get the searchform instance from the call stack)
        $backtrace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 15 );
        foreach ( $backtrace as $frame ) {
            if ( ! empty( $frame['object'] ) && is_object( $frame['object'] ) ) {
                $class_name = get_class( $frame['object'] );
                if ( 'Directorist\Directorist_Listing_Search_Form' === $class_name ) {
                    if ( ! empty( $frame['object']->listing_type ) ) {
                        return (int) $frame['object']->listing_type;
                    }
                }
            }
        }
        
        // Fallback: get default directory
        if ( function_exists( 'directorist_get_default_directory' ) ) {
            return (int) directorist_get_default_directory();
        }
        
        return 0;
    }

    /**
     * Modify template args before extract() is called
     * 
     * This intercepts the $args array in Helper::get_template before extract() is called.
     * We modify $args['data'] directly. Since extract() uses the $args array, modifications should work.
     * 
     * @param string $template Template name
     * @param array $args Template arguments
     * @return string Template name (unchanged)
     */
    public function modify_template_args_before_extract( $template, $args ) {
        // Only process search form field templates
        if ( strpos( $template, 'search-form/fields/' ) === false && strpos( $template, 'search-form/custom-fields/' ) === false ) {
            return $template;
        }

        if ( ! $this->is_wpml_active() ) {
            return $template;
        }

        // Check if this is a search form field template
        if ( empty( $args['data'] ) || ! is_array( $args['data'] ) ) {
            return $template;
        }

        // Get directory ID from searchform instance
        $directory_id = 0;
        if ( ! empty( $args['searchform'] ) && ! empty( $args['searchform']->listing_type ) ) {
            $directory_id = (int) $args['searchform']->listing_type;
        } else {
            $directory_id = $this->get_directory_id_from_context();
        }
        
        if ( empty( $directory_id ) ) {
            return $template;
        }

        // Translate the field data
        $translated_field_data = $this->translate_single_field( $args['data'], $directory_id );
        
        // Modify $args['data'] - output buffering will handle final translation
        if ( ! empty( $translated_field_data ) && $translated_field_data !== $args['data'] ) {
            $args['data'] = $translated_field_data;
        }
        
        return $template;
    }

    /**
     * Translate a single field's label and other translatable strings
     * 
     * @param array $field_data Field data array
     * @param int $directory_id Directory type ID
     * @return array Translated field data
     */
    private function translate_single_field( $field_data, $directory_id ) {
        if ( empty( $field_data ) || ! is_array( $field_data ) ) {
            return $field_data;
        }

        // Get widget name to identify the field type
        $widget_name = ! empty( $field_data['widget_name'] ) ? $field_data['widget_name'] : '';
        
        if ( empty( $widget_name ) ) {
            return $field_data;
        }

        $widget_slug = $this->safe_slug( $widget_name );

        // Translate field label
        if ( ! empty( $field_data['label'] ) && is_string( $field_data['label'] ) ) {
            $string_name = sprintf( 'search_form_dir_%d_field_%s_label', $directory_id, $widget_slug );
            
            $this->register_wpml_string( $string_name, $field_data['label'] );
            $translated = $this->translate_wpml_string( $field_data['label'], $string_name );
            
            if ( ! empty( $translated ) && $translated !== $field_data['label'] ) {
                $field_data['label'] = $translated;
            }
        }

        // Translate field placeholder
        if ( ! empty( $field_data['placeholder'] ) && is_string( $field_data['placeholder'] ) ) {
            $string_name = sprintf( 'search_form_dir_%d_field_%s_placeholder', $directory_id, $widget_slug );
            
            $this->register_wpml_string( $string_name, $field_data['placeholder'] );
            $translated = $this->translate_wpml_string( $field_data['placeholder'], $string_name );
            
            if ( ! empty( $translated ) && $translated !== $field_data['placeholder'] ) {
                $field_data['placeholder'] = $translated;
            }
        }

        // Translate field description if exists
        if ( ! empty( $field_data['description'] ) && is_string( $field_data['description'] ) ) {
            $string_name = sprintf( 'search_form_dir_%d_field_%s_description', $directory_id, $widget_slug );
            
            $this->register_wpml_string( $string_name, $field_data['description'] );
            $translated = $this->translate_wpml_string( $field_data['description'], $string_name );
            
            if ( ! empty( $translated ) && $translated !== $field_data['description'] ) {
                $field_data['description'] = $translated;
            }
        }

        // Translate pricing field specific placeholders
        if ( $widget_name === 'pricing' ) {
            // Translate min placeholder
            if ( ! empty( $field_data['price_range_min_placeholder'] ) && is_string( $field_data['price_range_min_placeholder'] ) ) {
                $string_name = sprintf( 'search_form_dir_%d_field_%s_min_placeholder', $directory_id, $widget_slug );
                
                $this->register_wpml_string( $string_name, $field_data['price_range_min_placeholder'] );
                $translated = $this->translate_wpml_string( $field_data['price_range_min_placeholder'], $string_name );
                
                if ( ! empty( $translated ) && $translated !== $field_data['price_range_min_placeholder'] ) {
                    $field_data['price_range_min_placeholder'] = $translated;
                }
            } elseif ( empty( $field_data['price_range_min_placeholder'] ) ) {
                // If empty, set default "Min" and translate it
                $default_min = __( 'Min', 'directorist' );
                $string_name = sprintf( 'search_form_dir_%d_field_%s_min_placeholder', $directory_id, $widget_slug );
                
                $this->register_wpml_string( $string_name, $default_min );
                $translated = $this->translate_wpml_string( $default_min, $string_name );
                
                if ( ! empty( $translated ) ) {
                    $field_data['price_range_min_placeholder'] = $translated;
                }
            }

            // Translate max placeholder
            if ( ! empty( $field_data['price_range_max_placeholder'] ) && is_string( $field_data['price_range_max_placeholder'] ) ) {
                $string_name = sprintf( 'search_form_dir_%d_field_%s_max_placeholder', $directory_id, $widget_slug );
                
                $this->register_wpml_string( $string_name, $field_data['price_range_max_placeholder'] );
                $translated = $this->translate_wpml_string( $field_data['price_range_max_placeholder'], $string_name );
                
                if ( ! empty( $translated ) && $translated !== $field_data['price_range_max_placeholder'] ) {
                    $field_data['price_range_max_placeholder'] = $translated;
                }
            } elseif ( empty( $field_data['price_range_max_placeholder'] ) ) {
                // If empty, set default "Max" and translate it
                $default_max = __( 'Max', 'directorist' );
                $string_name = sprintf( 'search_form_dir_%d_field_%s_max_placeholder', $directory_id, $widget_slug );
                
                $this->register_wpml_string( $string_name, $default_max );
                $translated = $this->translate_wpml_string( $default_max, $string_name );
                
                if ( ! empty( $translated ) ) {
                    $field_data['price_range_max_placeholder'] = $translated;
                }
            }
        }

        // Translate radius search field specific strings
        if ( $widget_name === 'radius_search' ) {
            // Add min/max placeholders for radius search
            if ( empty( $field_data['radius_min_placeholder'] ) ) {
                $default_min = __( 'Min', 'directorist' );
                $string_name = sprintf( 'search_form_dir_%d_field_%s_min_placeholder', $directory_id, $widget_slug );
                
                $this->register_wpml_string( $string_name, $default_min );
                $translated = $this->translate_wpml_string( $default_min, $string_name );
                
                if ( ! empty( $translated ) ) {
                    $field_data['radius_min_placeholder'] = $translated;
                }
            }

            if ( empty( $field_data['radius_max_placeholder'] ) ) {
                $default_max = __( 'Max', 'directorist' );
                $string_name = sprintf( 'search_form_dir_%d_field_%s_max_placeholder', $directory_id, $widget_slug );
                
                $this->register_wpml_string( $string_name, $default_max );
                $translated = $this->translate_wpml_string( $default_max, $string_name );
                
                if ( ! empty( $translated ) ) {
                    $field_data['radius_max_placeholder'] = $translated;
                }
            }
        }

        return $field_data;
    }

    /**
     * Register string with WPML
     * 
     * @param string $string_name String name/context
     * @param string $string_value String value
     * @return void
     */
    private function register_wpml_string( $string_name, $string_value ) {
        if ( ! function_exists( 'icl_register_string' ) ) {
            return;
        }
        
        if ( is_string( $string_value ) && ! empty( $string_value ) ) {
            icl_register_string( self::WPML_DOMAIN, $string_name, $string_value );
        }
    }

    /**
     * Translate WPML string
     * 
     * @param string $string_value Original string value
     * @param string $string_name String name/context
     * @return string Translated string
     */
    private function translate_wpml_string( $string_value, $string_name ) {
        if ( ! function_exists( 'apply_filters' ) ) {
            return $string_value;
        }
        
        return apply_filters(
            'wpml_translate_single_string',
            $string_value,
            self::WPML_DOMAIN,
            $string_name
        );
    }

    /**
     * Start output buffer for search form templates to translate hardcoded strings
     * 
     * @param string $template Template name
     * @param string $file Template file path
     * @param array $args Template arguments
     * @return void
     */
    public function start_output_buffer( $template, $file, $args ) {
        if ( ! $this->is_wpml_active() ) {
            return;
        }

        // Only process search form field templates
        if ( strpos( $template, 'search-form/fields/' ) === false && strpos( $template, 'search-form/custom-fields/' ) === false ) {
            return;
        }

        // Get directory ID
        $directory_id = 0;
        if ( ! empty( $args['searchform'] ) && ! empty( $args['searchform']->listing_type ) ) {
            $directory_id = (int) $args['searchform']->listing_type;
        } else {
            $directory_id = $this->get_directory_id_from_context();
        }

        if ( empty( $directory_id ) ) {
            return;
        }

        // Get field data
        $field_data = ! empty( $args['data'] ) ? $args['data'] : [];
        if ( empty( $field_data ) ) {
            return;
        }

        // Translate field data and store for output replacement
        $translated_field_data = $this->translate_single_field( $field_data, $directory_id );
        
        // Store translation info for output buffering
        $GLOBALS['_directorist_wpml_template_translation'] = [
            'template' => $template,
            'directory_id' => $directory_id,
            'original_data' => $field_data,
            'translated_data' => $translated_field_data,
        ];

        // Start output buffering with callback
        ob_start( [ $this, 'translate_template_output' ] );
    }

    /**
     * Translate template output - replaces labels and hardcoded strings
     * 
     * @param string $output Buffered template output
     * @return string Translated output
     */
    public function translate_template_output( $output ) {
        if ( empty( $output ) || ! $this->is_wpml_active() ) {
            return $output;
        }

        $translation_info = ! empty( $GLOBALS['_directorist_wpml_template_translation'] ) 
            ? $GLOBALS['_directorist_wpml_template_translation'] 
            : null;

        if ( empty( $translation_info ) ) {
            return $output;
        }

        $template = $translation_info['template'];
        $directory_id = $translation_info['directory_id'];
        $original_data = $translation_info['original_data'];
        $translated_data = $translation_info['translated_data'];

        // Replace field labels in output
        if ( ! empty( $original_data['label'] ) && ! empty( $translated_data['label'] ) ) {
            if ( $original_data['label'] !== $translated_data['label'] ) {
                // Replace label in various HTML contexts
                $escaped_original = esc_html( $original_data['label'] );
                $escaped_translated = esc_html( $translated_data['label'] );
                
                // Replace in label tags
                $output = str_replace( 
                    '>' . $escaped_original . '<', 
                    '>' . $escaped_translated . '<', 
                    $output 
                );
                
                // Also try unescaped version
                $output = str_replace( 
                    '>' . $original_data['label'] . '<', 
                    '>' . $translated_data['label'] . '<', 
                    $output 
                );
            }
        }

        // Handle radius_search "Min" and "Max" placeholders
        if ( strpos( $template, 'search-form/fields/radius_search' ) !== false ) {
            $min_string = __( 'Min', 'directorist' );
            $max_string = __( 'Max', 'directorist' );
            
            $min_string_name = sprintf( 'search_form_dir_%d_field_radius_search_min_placeholder', $directory_id );
            $max_string_name = sprintf( 'search_form_dir_%d_field_radius_search_max_placeholder', $directory_id );
            
            $this->register_wpml_string( $min_string_name, $min_string );
            $this->register_wpml_string( $max_string_name, $max_string );
            
            $translated_min = $this->translate_wpml_string( $min_string, $min_string_name );
            $translated_max = $this->translate_wpml_string( $max_string, $max_string_name );
            
            // Replace hardcoded "Min" and "Max" in placeholder attributes
            if ( $translated_min !== $min_string ) {
                $output = str_replace( 'placeholder="Min"', 'placeholder="' . esc_attr( $translated_min ) . '"', $output );
            }
            if ( $translated_max !== $max_string ) {
                $output = str_replace( 'placeholder="Max"', 'placeholder="' . esc_attr( $translated_max ) . '"', $output );
            }
        }

        // Clean up global
        unset( $GLOBALS['_directorist_wpml_template_translation'] );

        return $output;
    }

    /**
     * Create safe slug from string
     * 
     * @param string $string Input string
     * @return string Safe slug
     */
    private function safe_slug( $string ) {
        return sanitize_key( str_replace( [ ' ', '-', '_' ], '_', strtolower( $string ) ) );
    }
}
