<?php
/**
 * Block and Widget Translation Integration
 * 
 * Makes Directorist Gutenberg Blocks and Elementor Widgets fully translatable with WPML String Translation.
 * Translates block attributes and widget settings that are stored in block/widget configurations.
 * 
 * @package Directorist_WPML_Integration
 * @since 2.1.7
 */

namespace Directorist_WPML_Integration\Controller\Hook;

class Block_Widget_Translation {

    /**
     * WPML String Translation Domain
     * 
     * @var string
     */
    const WPML_DOMAIN = 'directorist-wpml-integration';

    /**
     * Translatable block attributes by block type
     * 
     * @var array
     */
    private static $block_attributes = [
        'directorist/search-listing' => [
            'search_bar_title',
            'search_bar_sub_title',
            'more_filters_text',
            'reset_filters_text',
            'apply_filters_text',
        ],
        'directorist/all-listing' => [
            'header_title',
        ],
        'directorist/search-result' => [
            'header_title',
        ],
        'directorist/category' => [
            'header_title',
        ],
        'directorist/location' => [
            'header_title',
        ],
        'directorist/tag' => [
            'header_title',
        ],
        'directorist/account-button' => [
            'title',
            'text',
        ],
        'directorist/search-modal' => [
            'title',
            'text',
        ],
    ];

    /**
     * Constructor
     * 
     * Registers hooks for block and widget translation.
     * 
     * @return void
     */
    public function __construct() {
        // Translate Gutenberg block attributes BEFORE rendering (modifies block data)
        // This hook runs before the block is rendered, allowing us to modify attributes
        add_filter( 'render_block_data', [ $this, 'translate_block_attributes' ], 10, 2 );
        
        // Also hook into render_block as fallback for content replacement
        add_filter( 'render_block', [ $this, 'translate_block_content' ], 10, 2 );
        
        // Translate Elementor widget content
        add_filter( 'elementor/widget/render_content', [ $this, 'translate_elementor_widget' ], 10, 2 );
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
     * Translate Gutenberg block attributes
     * 
     * Hook: render_block_data
     * Location: WordPress core block data processing (BEFORE rendering)
     * 
     * Modifies block attributes before the block is rendered.
     * This ensures translated attributes are passed to directorist_block_render_callback.
     * 
     * @param array $parsed_block The parsed block data
     * @param array $source_block The original block data
     * @return array Modified block data with translated attributes
     */
    public function translate_block_attributes( $parsed_block, $source_block ) {
        if ( ! $this->is_wpml_active() ) {
            return $parsed_block;
        }

        // Only process Directorist blocks
        if ( empty( $parsed_block['blockName'] ) || strpos( $parsed_block['blockName'], 'directorist/' ) !== 0 ) {
            return $parsed_block;
        }

        $block_name = $parsed_block['blockName'];
        
        // Check if this block has translatable attributes
        if ( empty( self::$block_attributes[ $block_name ] ) ) {
            return $parsed_block;
        }

        // Get block attributes
        if ( empty( $parsed_block['attrs'] ) || ! is_array( $parsed_block['attrs'] ) ) {
            return $parsed_block;
        }

        // Translate each translatable attribute
        $translatable_attrs = self::$block_attributes[ $block_name ];
        
        foreach ( $translatable_attrs as $attr_key ) {
            if ( ! isset( $parsed_block['attrs'][ $attr_key ] ) ) {
                continue;
            }
            
            $attr_value = $parsed_block['attrs'][ $attr_key ];
            
            // Handle rich-text attributes (may contain HTML)
            if ( $attr_key === 'text' && ( $block_name === 'directorist/account-button' || $block_name === 'directorist/search-modal' ) ) {
                // For rich-text, extract text content for translation while preserving HTML structure
                $text_content = $this->extract_text_from_richtext( $attr_value );
                if ( ! empty( $text_content ) ) {
                    $string_name = sprintf( 'block_%s_attr_%s', str_replace( '/', '_', $block_name ), $attr_key );
                    $this->register_wpml_string( $string_name, $text_content );
                    $translated_text = $this->translate_wpml_string( $text_content, $string_name );
                    
                    if ( ! empty( $translated_text ) && $translated_text !== $text_content ) {
                        // Replace text content in rich-text HTML
                        $parsed_block['attrs'][ $attr_key ] = $this->replace_text_in_richtext( $attr_value, $text_content, $translated_text );
                    }
                }
            } elseif ( is_string( $attr_value ) && ! empty( $attr_value ) ) {
                // Regular string attributes
                $string_name = sprintf( 'block_%s_attr_%s', str_replace( '/', '_', $block_name ), $attr_key );
                $this->register_wpml_string( $string_name, $attr_value );
                $translated = $this->translate_wpml_string( $attr_value, $string_name );
                
                if ( ! empty( $translated ) && $translated !== $attr_value ) {
                    // Modify the attribute directly in block data
                    // This will be passed to directorist_block_render_callback
                    $parsed_block['attrs'][ $attr_key ] = $translated;
                }
            }
        }

        return $parsed_block;
    }

    /**
     * Translate block content as fallback
     * 
     * Hook: render_block
     * 
     * This is a fallback in case render_block_data doesn't catch all cases.
     * Replaces translated strings in the rendered HTML output.
     * 
     * @param string $block_content The block content
     * @param array $block The full block, including name and attributes
     * @return string Translated block content
     */
    public function translate_block_content( $block_content, $block ) {
        if ( ! $this->is_wpml_active() ) {
            return $block_content;
        }

        // Only process Directorist blocks
        if ( empty( $block['blockName'] ) || strpos( $block['blockName'], 'directorist/' ) !== 0 ) {
            return $block_content;
        }

        $block_name = $block['blockName'];
        
        if ( empty( self::$block_attributes[ $block_name ] ) ) {
            return $block_content;
        }

        // Get original attributes from block
        $attributes = ! empty( $block['attrs'] ) ? $block['attrs'] : [];
        if ( empty( $attributes ) ) {
            return $block_content;
        }

        // Replace any remaining untranslated strings in content
        $translatable_attrs = self::$block_attributes[ $block_name ];
        
        foreach ( $translatable_attrs as $attr_key ) {
            if ( ! empty( $attributes[ $attr_key ] ) && is_string( $attributes[ $attr_key ] ) ) {
                $string_name = sprintf( 'block_%s_attr_%s', str_replace( '/', '_', $block_name ), $attr_key );
                $translated = $this->translate_wpml_string( $attributes[ $attr_key ], $string_name );
                
                if ( ! empty( $translated ) && $translated !== $attributes[ $attr_key ] ) {
                    $block_content = $this->replace_string_in_content( $block_content, $attributes[ $attr_key ], $translated );
                }
            }
        }

        return $block_content;
    }

    /**
     * Translate Elementor widget content
     * 
     * Hook: elementor/widget/render_content
     * Location: Elementor widget rendering
     * 
     * Translates widget settings before the widget is rendered.
     * 
     * @param string $widget_content The widget content
     * @param object $widget The widget instance
     * @return string Translated widget content
     */
    public function translate_elementor_widget( $widget_content, $widget ) {
        if ( ! $this->is_wpml_active() ) {
            return $widget_content;
        }

        // Only process Directorist widgets
        $widget_name = $widget->get_name();
        if ( empty( $widget_name ) || strpos( $widget_name, 'directorist-' ) !== 0 ) {
            return $widget_content;
        }

        // Get widget settings
        $settings = $widget->get_settings_for_display();
        if ( empty( $settings ) || ! is_array( $settings ) ) {
            return $widget_content;
        }

        // Translate common translatable settings
        $translatable_settings = [
            'title',
            'text',
            'header_title',
            'search_bar_title',
            'search_bar_sub_title',
            'more_filters_text',
            'reset_filters_text',
            'apply_filters_text',
        ];

        foreach ( $translatable_settings as $setting_key ) {
            if ( ! empty( $settings[ $setting_key ] ) && is_string( $settings[ $setting_key ] ) ) {
                // Register and translate the setting value
                $string_name = sprintf( 'elementor_widget_%s_setting_%s', str_replace( '-', '_', $widget_name ), $setting_key );
                $this->register_wpml_string( $string_name, $settings[ $setting_key ] );
                $translated = $this->translate_wpml_string( $settings[ $setting_key ], $string_name );
                
                if ( ! empty( $translated ) && $translated !== $settings[ $setting_key ] ) {
                    // Replace the setting value in widget content
                    $widget_content = $this->replace_string_in_content( $widget_content, $settings[ $setting_key ], $translated );
                }
            }
        }

        return $widget_content;
    }

    /**
     * Extract text content from rich-text HTML
     * 
     * @param string $richtext Rich-text HTML content
     * @return string Plain text content
     */
    private function extract_text_from_richtext( $richtext ) {
        if ( empty( $richtext ) || ! is_string( $richtext ) ) {
            return '';
        }
        
        // Strip HTML tags but preserve text content
        $text = wp_strip_all_tags( $richtext );
        
        // Decode HTML entities
        $text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
        
        return trim( $text );
    }

    /**
     * Replace text content in rich-text HTML while preserving HTML structure
     * 
     * @param string $richtext Original rich-text HTML
     * @param string $original_text Original text content
     * @param string $translated_text Translated text content
     * @return string Rich-text HTML with translated text
     */
    private function replace_text_in_richtext( $richtext, $original_text, $translated_text ) {
        if ( empty( $richtext ) || empty( $original_text ) || empty( $translated_text ) ) {
            return $richtext;
        }
        
        // Simple replacement: replace the text content while preserving HTML tags
        // This is a basic implementation - for complex HTML, WPML's built-in block translation handles it better
        $text_content = wp_strip_all_tags( $richtext );
        
        if ( $text_content === $original_text ) {
            // If the rich-text only contains the text (no HTML), replace directly
            return $translated_text;
        }
        
        // If HTML structure exists, replace text within HTML tags
        // Use regex to replace text content while preserving tags
        $pattern = '/>([^<]*' . preg_quote( $original_text, '/' ) . '[^<]*)</';
        $replacement = '>' . str_replace( $original_text, $translated_text, '$1' ) . '<';
        $result = preg_replace( $pattern, $replacement, $richtext );
        
        return $result !== null ? $result : $richtext;
    }

    /**
     * Replace string in content (handles HTML entities and escaped versions)
     * 
     * @param string $content Original content
     * @param string $original Original string to replace
     * @param string $translated Translated string
     * @return string Content with replaced strings
     */
    private function replace_string_in_content( $content, $original, $translated ) {
        if ( empty( $original ) || empty( $translated ) || $original === $translated ) {
            return $content;
        }

        // Replace plain text
        $content = str_replace( $original, $translated, $content );
        
        // Replace HTML entities
        $original_entities = htmlentities( $original, ENT_QUOTES, 'UTF-8' );
        $translated_entities = htmlentities( $translated, ENT_QUOTES, 'UTF-8' );
        if ( $original_entities !== $original ) {
            $content = str_replace( $original_entities, $translated_entities, $content );
        }
        
        // Replace escaped versions
        $original_escaped = esc_html( $original );
        $translated_escaped = esc_html( $translated );
        if ( $original_escaped !== $original ) {
            $content = str_replace( $original_escaped, $translated_escaped, $content );
        }
        
        // Replace attribute escaped versions
        $original_attr = esc_attr( $original );
        $translated_attr = esc_attr( $translated );
        if ( $original_attr !== $original ) {
            $content = str_replace( $original_attr, $translated_attr, $content );
        }

        return $content;
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
}
