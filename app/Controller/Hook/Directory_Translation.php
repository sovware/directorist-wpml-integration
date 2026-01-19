<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Directory_Translation {

    /**
     * WPML String Translation Domain
     * 
     * @var string
     */
    const WPML_DOMAIN = 'directorist-wpml-integration';

    public function __construct() {
        // Modify the WPML admin toolbar language switcher URLs
        add_filter( 'wpml_admin_language_switcher_items', [ $this, 'modify_language_switcher_url' ], 10, 1 );
        
        // Translate directory type names in templates (before array_reduce)
        add_filter( 'directorist_directories_for_template', [ $this, 'translate_directory_names' ], 10, 2 );
        
        // Translate directory type names in the final array structure (after array_reduce)
        // This is a custom filter we'll add support for, but also hook into get_terms results
        add_filter( 'get_terms', [ $this, 'translate_directory_terms' ], 10, 4 );
        
        // Translate directory type names in admin areas (term name filter)
        add_filter( 'term_name', [ $this, 'translate_term_name' ], 10, 2 );
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

        // Skip registration on WPML String Translation admin page to avoid conflicts
        if ( is_admin() && ! empty( $_GET['page'] ) && strpos( $_GET['page'], 'wpml-string-translation' ) !== false ) {
            return;
        }

        // Prevent registration during AJAX requests that might trigger WPML processing
        if ( wp_doing_ajax() && ! empty( $_REQUEST['action'] ) && strpos( $_REQUEST['action'], 'wpml' ) !== false ) {
            return;
        }

        // Use output buffering to catch and suppress WPML Gutenberg integration warnings
        ob_start();
        
        // Suppress warnings during registration
        $error_level = error_reporting();
        error_reporting( $error_level & ~E_WARNING );
        
        try {
            // Use WPML String Translation function
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
            
            // Discard any output (including warnings) from WPML's code
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
        // This ensures we never lose the name even if translation is empty
        if ( empty( $translated ) || $translated === $value ) {
            return $value;
        }

        return $translated;
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
     * Translate directory type names in templates
     * 
     * Hook: directorist_directories_for_template
     * Priority: 10
     * 
     * This hook is called when Directorist retrieves directory types for display in templates.
     * We translate the 'name' field in the returned array.
     * 
     * @param array $directories Array of WP_Term objects
     * @param array $args Arguments passed to directorist_get_directories()
     * @return array Filtered directories with translated names
     */
    public function translate_directory_names( $directories, $args ) {
        // Safety checks
        if ( empty( $directories ) || ! is_array( $directories ) ) {
            return $directories;
        }

        if ( ! $this->is_wpml_active() ) {
            return $directories;
        }

        // Check if this is the directory type taxonomy
        // The directories array contains WP_Term objects from the directory type taxonomy
        if ( empty( $directories ) ) {
            return $directories;
        }

        // Get first term to check taxonomy
        $first_term = reset( $directories );
        if ( ! is_object( $first_term ) || ! isset( $first_term->taxonomy ) ) {
            return $directories;
        }

        // Only translate if it's the directory type taxonomy
        // Check if constant exists, otherwise check taxonomy name
        $is_directory_type = false;
        if ( defined( 'ATBDP_DIRECTORY_TYPE' ) ) {
            $is_directory_type = ( $first_term->taxonomy === ATBDP_DIRECTORY_TYPE );
        } elseif ( defined( 'ATBDP_TYPE' ) ) {
            $is_directory_type = ( $first_term->taxonomy === ATBDP_TYPE );
        } else {
            // Fallback: check by taxonomy name
            $is_directory_type = ( $first_term->taxonomy === 'atbdp_listing_types' );
        }

        if ( ! $is_directory_type ) {
            return $directories;
        }

        // Translate each directory name
        foreach ( $directories as $directory ) {
            if ( ! is_object( $directory ) || ! isset( $directory->name ) || ! isset( $directory->term_id ) ) {
                continue;
            }

            $original_name = $directory->name;
            if ( empty( $original_name ) || ! is_string( $original_name ) ) {
                continue;
            }

            // Generate string name for WPML
            $string_name = sprintf( 'directory_type_%d_name', $directory->term_id );
            
            // Register string with WPML
            $this->register_wpml_string( $string_name, $original_name );
            
            // Translate the name
            $translated_name = $this->translate_wpml_string( $original_name, $string_name );
            
            // Update the term name in the object
            if ( ! empty( $translated_name ) && $translated_name !== $original_name ) {
                $directory->name = $translated_name;
            }
        }

        return $directories;
    }

    /**
     * Translate directory type and tag terms when retrieved via get_terms()
     * 
     * Hook: get_terms
     * Priority: 10
     * 
     * This hook translates directory type and tag term names when they're retrieved
     * directly via get_terms() or get_term() functions.
     * 
     * @param array|WP_Error $terms Array of term objects or WP_Error
     * @param array $taxonomies Array of taxonomy names
     * @param array $args Query arguments
     * @param WP_Term_Query $term_query The term query object
     * @return array|WP_Error Filtered terms with translated names
     */
    public function translate_directory_terms( $terms, $taxonomies, $args, $term_query ) {
        // Safety checks
        if ( empty( $terms ) || is_wp_error( $terms ) || ! is_array( $terms ) ) {
            return $terms;
        }

        if ( ! $this->is_wpml_active() ) {
            return $terms;
        }

        // Check if directory type or tags taxonomy is in the query
        $is_target_taxonomy = false;
        $taxonomy_type = '';
        
        if ( ! empty( $taxonomies ) && is_array( $taxonomies ) ) {
            foreach ( $taxonomies as $taxonomy ) {
                // Check for directory type
                if ( defined( 'ATBDP_DIRECTORY_TYPE' ) && $taxonomy === ATBDP_DIRECTORY_TYPE ) {
                    $is_target_taxonomy = true;
                    $taxonomy_type = 'directory_type';
                    break;
                } elseif ( defined( 'ATBDP_TYPE' ) && $taxonomy === ATBDP_TYPE ) {
                    $is_target_taxonomy = true;
                    $taxonomy_type = 'directory_type';
                    break;
                } elseif ( $taxonomy === 'atbdp_listing_types' ) {
                    $is_target_taxonomy = true;
                    $taxonomy_type = 'directory_type';
                    break;
                }
                // Check for tags
                elseif ( defined( 'ATBDP_TAGS' ) && $taxonomy === ATBDP_TAGS ) {
                    $is_target_taxonomy = true;
                    $taxonomy_type = 'tag';
                    break;
                } elseif ( $taxonomy === 'at_biz_dir-tags' ) {
                    $is_target_taxonomy = true;
                    $taxonomy_type = 'tag';
                    break;
                }
            }
        }

        if ( ! $is_target_taxonomy ) {
            return $terms;
        }

        // Translate each term name
        foreach ( $terms as $term ) {
            if ( ! is_object( $term ) || ! isset( $term->name ) || ! isset( $term->term_id ) ) {
                continue;
            }

            // Double-check taxonomy from term object
            if ( isset( $term->taxonomy ) ) {
                if ( defined( 'ATBDP_DIRECTORY_TYPE' ) && $term->taxonomy === ATBDP_DIRECTORY_TYPE ) {
                    $taxonomy_type = 'directory_type';
                } elseif ( defined( 'ATBDP_TYPE' ) && $term->taxonomy === ATBDP_TYPE ) {
                    $taxonomy_type = 'directory_type';
                } elseif ( $term->taxonomy === 'atbdp_listing_types' ) {
                    $taxonomy_type = 'directory_type';
                } elseif ( defined( 'ATBDP_TAGS' ) && $term->taxonomy === ATBDP_TAGS ) {
                    $taxonomy_type = 'tag';
                } elseif ( $term->taxonomy === 'at_biz_dir-tags' ) {
                    $taxonomy_type = 'tag';
                } else {
                    continue; // Skip if not a target taxonomy
                }
            }

            $original_name = $term->name;
            if ( empty( $original_name ) || ! is_string( $original_name ) ) {
                continue;
            }

            // Generate string name for WPML based on taxonomy type
            if ( $taxonomy_type === 'tag' ) {
                $string_name = sprintf( 'tag_%d_name', $term->term_id );
            } else {
                $string_name = sprintf( 'directory_type_%d_name', $term->term_id );
            }
            
            // Register string with WPML
            $this->register_wpml_string( $string_name, $original_name );
            
            // Translate the name
            $translated_name = $this->translate_wpml_string( $original_name, $string_name );
            
            // Update the term name in the object
            if ( ! empty( $translated_name ) && $translated_name !== $original_name ) {
                $term->name = $translated_name;
            }
        }

        return $terms;
    }

    /**
     * Translate term name for directory types and tags in admin areas
     * 
     * Hook: term_name
     * Priority: 10
     * 
     * This hook translates term names when displayed in admin areas,
     * such as in admin columns, category/location forms, etc.
     * 
     * @param string $name Term name
     * @param object $term Term object
     * @return string Translated term name
     */
    public function translate_term_name( $name, $term ) {
        // Safety checks
        if ( empty( $name ) || ! is_string( $name ) ) {
            return $name;
        }

        if ( empty( $term ) || ! is_object( $term ) ) {
            return $name;
        }

        if ( ! $this->is_wpml_active() ) {
            return $name;
        }

        // Check if this is a directory type or tag term
        $taxonomy_type = '';
        if ( isset( $term->taxonomy ) ) {
            if ( defined( 'ATBDP_DIRECTORY_TYPE' ) && $term->taxonomy === ATBDP_DIRECTORY_TYPE ) {
                $taxonomy_type = 'directory_type';
            } elseif ( defined( 'ATBDP_TYPE' ) && $term->taxonomy === ATBDP_TYPE ) {
                $taxonomy_type = 'directory_type';
            } elseif ( $term->taxonomy === 'atbdp_listing_types' ) {
                $taxonomy_type = 'directory_type';
            } elseif ( defined( 'ATBDP_TAGS' ) && $term->taxonomy === ATBDP_TAGS ) {
                $taxonomy_type = 'tag';
            } elseif ( $term->taxonomy === 'at_biz_dir-tags' ) {
                $taxonomy_type = 'tag';
            }
        }

        if ( empty( $taxonomy_type ) ) {
            return $name;
        }

        // Check if term_id exists
        if ( ! isset( $term->term_id ) || empty( $term->term_id ) ) {
            return $name;
        }

        // Generate string name for WPML based on taxonomy type
        if ( $taxonomy_type === 'tag' ) {
            $string_name = sprintf( 'tag_%d_name', $term->term_id );
        } else {
            $string_name = sprintf( 'directory_type_%d_name', $term->term_id );
        }
        
        // Register string with WPML
        $this->register_wpml_string( $string_name, $name );
        
        // Translate the name
        $translated = $this->translate_wpml_string( $name, $string_name );
        
        return $translated;
    }

    public function modify_language_switcher_url( $languages_links ) {
        foreach ( $languages_links as $lang_code => &$lang_data ) {
            $url_parts = wp_parse_url( $lang_data['url'] );
            
            if ( isset( $url_parts['query'] ) ) {
                parse_str( $url_parts['query'], $query_vars );

                if (
                    isset( $query_vars['post_type'] ) &&
                    $query_vars['post_type'] === 'at_biz_dir' &&
                    isset( $query_vars['page'] ) &&
                    $query_vars['page'] === 'atbdp-directory-types' &&
                    ! empty( $query_vars['listing_type_id'] )
                ) {
                    $translations = WPML_Helper::get_element_translations( $query_vars['listing_type_id'], ATBDP_DIRECTORY_TYPE );

                    if ( empty( $translations ) ) {
                        continue;
                    }

                    if ( isset( $translations[ $lang_code ] ) ) {
                        $translation_id = $translations[ $lang_code ]->element_id;

                        // Change the listing_type_id of the URL to the translation_id
                        $lang_data['url'] = add_query_arg( array(
                            'listing_type_id' => $translation_id,
                        ), $lang_data['url'] );
                    }
                }
            }
        }
        
        return $languages_links;
    }
}

