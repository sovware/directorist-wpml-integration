<?php

namespace Directorist_WPML_Integration\Controller\Hook;

class Listing_Count_Filter {

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct() {
        // Ensure all WP_Query instances for listings have suppress_filters = false
        // This runs early to catch count queries before WPML filters them
        add_action( 'pre_get_posts', [ $this, 'ensure_count_queries_are_filtered' ], 5, 1 );
        
        // Since the count functions don't have filters, we intercept via pre_get_posts
        // and translate term IDs in tax_query before the query runs
    }

    /**
     * Ensure count queries are filtered by language
     * This runs early to catch all queries before WPML filters them
     * 
     * @param \WP_Query $query
     * @return void
     */
    public function ensure_count_queries_are_filtered( $query ) {
        // Check if WPML is active
        if ( ! function_exists( 'apply_filters' ) || ! has_filter( 'wpml_current_language' ) ) {
            return;
        }

        // Only on frontend (allow AJAX requests)
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        // Only for Directorist listing post type
        if ( ! isset( $query->query_vars['post_type'] ) || ATBDP_POST_TYPE !== $query->query_vars['post_type'] ) {
            return;
        }

        // Ensure WPML can filter this query
        $query->query_vars['suppress_filters'] = false;

        // If this looks like a count query (fields = ids, posts_per_page = -1)
        // Translate taxonomy term IDs in tax_query to current language
        if ( isset( $query->query_vars['fields'] ) && 'ids' === $query->query_vars['fields'] ) {
            if ( isset( $query->query_vars['posts_per_page'] ) && -1 == $query->query_vars['posts_per_page'] ) {
                // Translate term IDs in tax_query
                if ( ! empty( $query->query_vars['tax_query'] ) && is_array( $query->query_vars['tax_query'] ) ) {
                    $query->query_vars['tax_query'] = $this->translate_tax_query_terms( $query->query_vars['tax_query'] );
                }
            }
        }
    }

    /**
     * Translate taxonomy query terms to current language
     * 
     * @param array $tax_query Tax query array
     * @return array Modified tax query
     */
    private function translate_tax_query_terms( $tax_query ) {
        // Check if WPML is active
        if ( ! has_filter( 'wpml_current_language' ) || ! has_filter( 'wpml_object_id' ) ) {
            return $tax_query;
        }

        $current_language = apply_filters( 'wpml_current_language', null );
        
        if ( empty( $current_language ) ) {
            return $tax_query;
        }

        foreach ( $tax_query as $key => $query ) {
            if ( ! is_array( $query ) || empty( $query['terms'] ) ) {
                continue;
            }

            $taxonomy = isset( $query['taxonomy'] ) ? $query['taxonomy'] : '';
            
            if ( empty( $taxonomy ) ) {
                continue;
            }

            // Only translate Directorist taxonomies
            if ( ! in_array( $taxonomy, [ ATBDP_CATEGORY, ATBDP_LOCATION, ATBDP_TAGS, ATBDP_TYPE ] ) ) {
                continue;
            }

            // Translate term IDs to current language
            $terms = $query['terms'];
            
            if ( ! is_array( $terms ) ) {
                $terms = [ $terms ];
            }

            $translated_terms = [];
            
            foreach ( $terms as $term ) {
                if ( is_numeric( $term ) ) {
                    // Term ID - translate it
                    $translated_term_id = apply_filters( 
                        'wpml_object_id', 
                        $term, 
                        $taxonomy, 
                        false, 
                        $current_language 
                    );
                    
                    if ( $translated_term_id ) {
                        $translated_terms[] = $translated_term_id;
                    }
                }
            }

            if ( ! empty( $translated_terms ) ) {
                $tax_query[ $key ]['terms'] = $translated_terms;
            } else {
                // No translated terms found, set to empty to avoid showing all
                $tax_query[ $key ]['terms'] = [ -1 ]; // Will return no results
            }
        }

        return $tax_query;
    }

}

