<?php

namespace Directorist_WPML_Integration\Controller\Hook;

class Add_Listing_Form_Translation {

    /**
     * Store translated section labels
     * 
     * @var array
     */
    private static $translated_sections = [];

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct() {
        // Filter form field data before rendering (this is the actual filter that exists)
        add_filter( 'directorist_form_field_data', [ $this, 'translate_field_data' ], 10, 1 );
        
        // Hook into section template to translate section labels
        add_filter( 'directorist_section_template', [ $this, 'translate_section_in_template' ], 20, 2 );
        
        // Filter section data before template rendering
        add_filter( 'directorist_form_section_data', [ $this, 'translate_section_data' ], 10, 1 );
    }

    /**
     * Translate field data (label, placeholder, description)
     * 
     * @param array $field_data Field data array
     * @return array Translated field data
     */
    public function translate_field_data( $field_data ) {
        if ( empty( $field_data ) || ! is_array( $field_data ) ) {
            return $field_data;
        }

        // Check if WPML is active
        if ( ! function_exists( 'icl_register_string' ) || ! function_exists( 'wpml_translate_single_string' ) ) {
            return $field_data;
        }

        $string_context = 'Directorist Add Listing Form';
        $field_key = ! empty( $field_data['field_key'] ) ? $field_data['field_key'] : '';

        // Translate label
        if ( ! empty( $field_data['label'] ) ) {
            $string_name = 'directorist_form_field_' . $field_key;
            icl_register_string( $string_context, $string_name, $field_data['label'] );
            $translated = wpml_translate_single_string( $field_data['label'], $string_context, $string_name );
            if ( ! empty( $translated ) ) {
                $field_data['label'] = $translated;
            }
        }

        // Translate placeholder
        if ( ! empty( $field_data['placeholder'] ) ) {
            $string_name = 'directorist_form_field_placeholder_' . $field_key;
            icl_register_string( $string_context, $string_name, $field_data['placeholder'] );
            $translated = wpml_translate_single_string( $field_data['placeholder'], $string_context, $string_name );
            if ( ! empty( $translated ) ) {
                $field_data['placeholder'] = $translated;
            }
        }

        // Translate description
        if ( ! empty( $field_data['description'] ) ) {
            $string_name = 'directorist_form_field_description_' . $field_key;
            icl_register_string( $string_context, $string_name, $field_data['description'] );
            $translated = wpml_translate_single_string( $field_data['description'], $string_context, $string_name );
            if ( ! empty( $translated ) ) {
                $field_data['description'] = $translated;
            }
        }

        return $field_data;
    }

    /**
     * Translate section label
     * 
     * @param string $label Original label
     * @param array $section_data Section data
     * @return string Translated label
     */
    public function translate_section_label( $label, $section_data = [] ) {
        if ( empty( $label ) ) {
            return $label;
        }

        // Check if WPML is active
        if ( ! function_exists( 'icl_register_string' ) || ! function_exists( 'wpml_translate_single_string' ) ) {
            return $label;
        }

        // Create unique string name
        $section_key = ! empty( $section_data['key'] ) ? $section_data['key'] : sanitize_key( $label );
        $string_name = 'directorist_form_section_' . $section_key;
        $string_context = 'Directorist Add Listing Form';

        // Register string with WPML
        icl_register_string( $string_context, $string_name, $label );

        // Translate string
        $translated = wpml_translate_single_string( $label, $string_context, $string_name );

        return ! empty( $translated ) ? $translated : $label;
    }

    /**
     * Translate section data before template rendering
     * This filter may not exist, but we'll try it as a primary method
     * 
     * @param array $section_data Section data
     * @return array Translated section data
     */
    public function translate_section_data( $section_data ) {
        if ( empty( $section_data ) || ! is_array( $section_data ) ) {
            return $section_data;
        }

        // Check if WPML is active
        if ( ! function_exists( 'icl_register_string' ) || ! function_exists( 'wpml_translate_single_string' ) ) {
            return $section_data;
        }

        // Translate section label
        if ( ! empty( $section_data['label'] ) ) {
            $section_key = ! empty( $section_data['key'] ) ? $section_data['key'] : sanitize_key( $section_data['label'] );
            $string_name = 'directorist_form_section_' . $section_key;
            $string_context = 'Directorist Add Listing Form';
            $original_label = $section_data['label'];

            // Register string with WPML
            icl_register_string( $string_context, $string_name, $original_label );

            // Translate string
            $translated = wpml_translate_single_string( $original_label, $string_context, $string_name );

            if ( ! empty( $translated ) && $translated !== $original_label ) {
                $section_data['label'] = $translated;
                // Store for later use
                self::$translated_sections[ $original_label ] = $translated;
            }
        }

        return $section_data;
    }

    /**
     * Translate section in template args
     * Store translation and modify args (may not persist, but we try)
     * 
     * @param bool $load_section Whether to load section
     * @param array $args Template arguments
     * @return bool
     */
    public function translate_section_in_template( $load_section, $args ) {
        if ( empty( $args['section_data'] ) || ! is_array( $args['section_data'] ) ) {
            return $load_section;
        }

        // Check if WPML is active
        if ( ! function_exists( 'icl_register_string' ) || ! function_exists( 'wpml_translate_single_string' ) ) {
            return $load_section;
        }

        // Translate section label
        if ( ! empty( $args['section_data']['label'] ) ) {
            $original_label = $args['section_data']['label'];
            
            // Check if we already have translation stored
            if ( isset( self::$translated_sections[ $original_label ] ) ) {
                $args['section_data']['label'] = self::$translated_sections[ $original_label ];
                return $load_section;
            }

            $section_key = ! empty( $args['section_data']['key'] ) ? $args['section_data']['key'] : sanitize_key( $original_label );
            $string_name = 'directorist_form_section_' . $section_key;
            $string_context = 'Directorist Add Listing Form';

            // Register string with WPML
            icl_register_string( $string_context, $string_name, $original_label );

            // Translate string
            $translated = wpml_translate_single_string( $original_label, $string_context, $string_name );

            if ( ! empty( $translated ) && $translated !== $original_label ) {
                // Store translation
                self::$translated_sections[ $original_label ] = $translated;
                // Try to modify args (may not work due to pass-by-value, but worth trying)
                $args['section_data']['label'] = $translated;
            }
        }

        return $load_section;
    }
}

