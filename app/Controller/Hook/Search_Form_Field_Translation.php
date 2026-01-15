<?php
/**
 * Search Form Field Translation Integration
 * 
 * Makes Directorist Search Form field labels fully translatable with WPML String Translation.
 * Handles dynamic search form fields like Review, Tags, and other filter fields.
 * 
 * Uses filter-based translation (no output buffering, no globals, no extract hacks, no debug_backtrace)
 * for 100% reliability in both normal page loads and AJAX requests.
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
        // Hook into directorist_template filter to translate field data
        // This runs in Helper::get_template() and we modify $args['data'] directly
        // Location: trait-uri-helper.php line 63
        // We have access to $args['searchform'] here, so we can get directory_id
        add_filter( 'directorist_template', [ $this, 'apply_translated_field_data' ], 10, 2 );
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
     * Get directory ID from searchform instance or context
     * 
     * @param object|null $searchform SearchForm instance
     * @return int Directory ID
     */
    private function get_directory_id( $searchform = null ) {
        // First priority: Get from searchform instance
        if ( ! empty( $searchform ) && is_object( $searchform ) ) {
            if ( ! empty( $searchform->listing_type ) ) {
                return (int) $searchform->listing_type;
            }
        }

        // Second priority: During AJAX, get from POST data
        if ( wp_doing_ajax() && ! empty( $_POST['listing_type'] ) ) {
            $listing_type_slug = sanitize_text_field( $_POST['listing_type'] );
            if ( function_exists( 'get_term_by' ) && function_exists( 'ATBDP_TYPE' ) ) {
                $term = get_term_by( 'slug', $listing_type_slug, ATBDP_TYPE );
                if ( $term && ! is_wp_error( $term ) ) {
                    return (int) $term->term_id;
                }
            }
        }

        // Fallback: Get default directory
        if ( function_exists( 'directorist_get_default_directory' ) ) {
            return (int) directorist_get_default_directory();
        }

        return 0;
    }


    /**
     * Apply translated field data in directorist_template filter
     * 
     * Hook Location: Helper::get_template() line 63
     * Filter: apply_filters( 'directorist_template', $template, $args )
     * 
     * This filter receives $args which contains $args['data'] = $field_data and $args['searchform'].
     * We translate the field_data here (now that we have directory_id from searchform) and replace $args['data'].
     * 
     * @param string $template Template name
     * @param array $args Template arguments (contains 'data' => $field_data, 'searchform' => $searchform)
     * @return string Template name (unchanged)
     */
    public function apply_translated_field_data( $template, $args ) {
        // Only process search form field templates
        if ( strpos( $template, 'search-form/fields/' ) === false && strpos( $template, 'search-form/custom-fields/' ) === false ) {
            return $template;
        }

        if ( ! $this->is_wpml_active() ) {
            return $template;
        }

        // Check if this is a search form field template with field data
        if ( empty( $args['data'] ) || ! is_array( $args['data'] ) ) {
            return $template;
        }

        // Get directory ID from searchform instance (available in $args)
        $directory_id = 0;
        if ( ! empty( $args['searchform'] ) && is_object( $args['searchform'] ) ) {
            if ( ! empty( $args['searchform']->listing_type ) ) {
                $directory_id = (int) $args['searchform']->listing_type;
            }
        } else {
            $directory_id = $this->get_directory_id();
        }

        if ( empty( $directory_id ) ) {
            return $template;
        }

        // Translate the field data (now that we have directory_id)
        $translated_field_data = $this->translate_single_field( $args['data'], $directory_id );

        // Replace $args['data'] with translated version
        $args['data'] = $translated_field_data;

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

        $widget_name = ! empty( $field_data['widget_name'] ) ? $field_data['widget_name'] : '';
        if ( empty( $widget_name ) ) {
            return $field_data;
        }

        $widget_slug = $this->safe_slug( $widget_name );

        // Translate common field properties
        $field_data = $this->translate_field_property( $field_data, 'label', $directory_id, $widget_slug );
        $field_data = $this->translate_field_property( $field_data, 'placeholder', $directory_id, $widget_slug );
        $field_data = $this->translate_field_property( $field_data, 'description', $directory_id, $widget_slug );

        // Translate field options (for select, radio, checkbox fields)
        if ( ! empty( $field_data['options'] ) && is_array( $field_data['options'] ) ) {
            $field_data = $this->translate_field_options( $field_data, $directory_id, $widget_slug );
        }

        // Translate field-specific properties
        if ( $widget_name === 'pricing' ) {
            $field_data = $this->translate_pricing_field( $field_data, $directory_id, $widget_slug );
        } elseif ( $widget_name === 'radius_search' ) {
            $field_data = $this->translate_radius_search_field( $field_data, $directory_id, $widget_slug );
        }

        return $field_data;
    }

    /**
     * Translate field options array
     * 
     * @param array $field_data Field data array
     * @param int $directory_id Directory type ID
     * @param string $widget_slug Widget slug
     * @return array Modified field data
     */
    private function translate_field_options( $field_data, $directory_id, $widget_slug ) {
        if ( empty( $field_data['options'] ) || ! is_array( $field_data['options'] ) ) {
            return $field_data;
        }

        $translated_options = [];
        foreach ( $field_data['options'] as $key => $option ) {
            if ( is_array( $option ) ) {
                // Handle nested option arrays (e.g., ['value' => 'x', 'label' => 'y'])
                if ( ! empty( $option['label'] ) && is_string( $option['label'] ) ) {
                    // Use option value for stable naming, fallback to index
                    $option_identifier = ! empty( $option['value'] ) ? $option['value'] : $key;
                    $string_name = sprintf( 'search_form_dir_%d_field_%s_option_%s', $directory_id, $widget_slug, $this->safe_slug( $option_identifier ) );
                    $this->register_wpml_string( $string_name, $option['label'] );
                    $translated_label = $this->translate_wpml_string( $option['label'], $string_name );
                    
                    if ( ! empty( $translated_label ) && $translated_label !== $option['label'] ) {
                        $option['label'] = $translated_label;
                    }
                }
                $translated_options[ $key ] = $option;
            } elseif ( is_string( $option ) ) {
                // Handle simple option arrays (e.g., ['option1', 'option2'])
                // Use option value for stable naming
                $option_identifier = $option;
                $string_name = sprintf( 'search_form_dir_%d_field_%s_option_%s', $directory_id, $widget_slug, $this->safe_slug( $option_identifier ) );
                $this->register_wpml_string( $string_name, $option );
                $translated_option = $this->translate_wpml_string( $option, $string_name );
                
                $translated_options[ $key ] = ! empty( $translated_option ) && $translated_option !== $option 
                    ? $translated_option 
                    : $option;
            } else {
                $translated_options[ $key ] = $option;
            }
        }

        $field_data['options'] = $translated_options;
        return $field_data;
    }

    /**
     * Translate a single field property (label, placeholder, description)
     * 
     * @param array $field_data Field data array
     * @param string $property Property name (label, placeholder, description)
     * @param int $directory_id Directory type ID
     * @param string $widget_slug Widget slug
     * @return array Modified field data
     */
    private function translate_field_property( $field_data, $property, $directory_id, $widget_slug ) {
        if ( empty( $field_data[ $property ] ) || ! is_string( $field_data[ $property ] ) ) {
            return $field_data;
        }

        $string_name = sprintf( 'search_form_dir_%d_field_%s_%s', $directory_id, $widget_slug, $property );
        $this->register_wpml_string( $string_name, $field_data[ $property ] );
        $translated = $this->translate_wpml_string( $field_data[ $property ], $string_name );

        if ( ! empty( $translated ) && $translated !== $field_data[ $property ] ) {
            $field_data[ $property ] = $translated;
        }

        return $field_data;
    }

    /**
     * Translate pricing field specific placeholders
     * 
     * @param array $field_data Field data array
     * @param int $directory_id Directory type ID
     * @param string $widget_slug Widget slug
     * @return array Modified field data
     */
    private function translate_pricing_field( $field_data, $directory_id, $widget_slug ) {
        $field_data = $this->translate_min_max_placeholder( $field_data, 'price_range_min_placeholder', 'Min', $directory_id, $widget_slug );
        $field_data = $this->translate_min_max_placeholder( $field_data, 'price_range_max_placeholder', 'Max', $directory_id, $widget_slug );
        return $field_data;
    }

    /**
     * Translate radius search field specific strings
     * 
     * @param array $field_data Field data array
     * @param int $directory_id Directory type ID
     * @param string $widget_slug Widget slug
     * @return array Modified field data
     */
    private function translate_radius_search_field( $field_data, $directory_id, $widget_slug ) {
        if ( empty( $field_data['radius_min_placeholder'] ) ) {
            $field_data = $this->translate_min_max_placeholder( $field_data, 'radius_min_placeholder', 'Min', $directory_id, $widget_slug );
        }
        if ( empty( $field_data['radius_max_placeholder'] ) ) {
            $field_data = $this->translate_min_max_placeholder( $field_data, 'radius_max_placeholder', 'Max', $directory_id, $widget_slug );
        }
        return $field_data;
    }

    /**
     * Translate min/max placeholder with default fallback
     * 
     * @param array $field_data Field data array
     * @param string $property Property name
     * @param string $default Default value if property is empty
     * @param int $directory_id Directory type ID
     * @param string $widget_slug Widget slug
     * @return array Modified field data
     */
    private function translate_min_max_placeholder( $field_data, $property, $default, $directory_id, $widget_slug ) {
        $value = ! empty( $field_data[ $property ] ) && is_string( $field_data[ $property ] ) 
            ? $field_data[ $property ] 
            : __( $default, 'directorist' );

        $string_name = sprintf( 'search_form_dir_%d_field_%s_%s', $directory_id, $widget_slug, str_replace( [ 'price_range_', 'radius_' ], '', $property ) );
        $this->register_wpml_string( $string_name, $value );
        $translated = $this->translate_wpml_string( $value, $string_name );

        if ( ! empty( $translated ) ) {
            $field_data[ $property ] = $translated;
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
        if ( ! function_exists( 'do_action' ) ) {
            return;
        }
        
        if ( is_string( $string_value ) && ! empty( $string_value ) ) {
            do_action( 'wpml_register_single_string', self::WPML_DOMAIN, $string_name, $string_value );
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
     * Create safe slug from string
     * 
     * @param string $string Input string
     * @return string Safe slug
     */
    private function safe_slug( $string ) {
        return sanitize_key( str_replace( [ ' ', '-', '_' ], '_', strtolower( $string ) ) );
    }
}
