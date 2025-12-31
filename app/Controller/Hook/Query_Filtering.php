<?php

namespace Directorist_WPML_Integration\Controller\Hook;

class Query_Filtering {

    /**
     * Constructor
     * 
     * @return void
     */
    public function __construct() {
        // Filter listing queries to show only current language
        add_action( 'pre_get_posts', [ $this, 'filter_listing_queries' ], 10, 1 );
        
        // Ensure suppress_filters is false for Directorist queries
        add_filter( 'directorist_all_listings_query_arguments', [ $this, 'ensure_wpml_filtering' ], 10, 1 );
        add_filter( 'directorist_dashboard_query_arguments', [ $this, 'ensure_wpml_filtering' ], 10, 1 );
        
        // Filter DB queries before they're executed
        add_filter( 'directorist_listings_query_results', [ $this, 'filter_query_results' ], 10, 1 );
    }

    /**
     * Filter listing queries to show only current language
     * 
     * @param \WP_Query $query
     * @return void
     */
    public function filter_listing_queries( $query ) {
        // Check if WPML is active
        if ( ! function_exists( 'apply_filters' ) || ! has_filter( 'wpml_current_language' ) ) {
            return;
        }

        // Only filter on frontend (allow AJAX requests)
        if ( is_admin() && ! wp_doing_ajax() ) {
            return;
        }

        // Only filter Directorist listing queries
        if ( ! isset( $query->query_vars['post_type'] ) || ATBDP_POST_TYPE !== $query->query_vars['post_type'] ) {
            return;
        }

        // Don't filter if suppress_filters is true (but we'll set it to false via filter)
        if ( ! empty( $query->query_vars['suppress_filters'] ) ) {
            $query->query_vars['suppress_filters'] = false;
        }

        // Translate taxonomy term IDs in tax_query to current language
        if ( ! empty( $query->query_vars['tax_query'] ) && is_array( $query->query_vars['tax_query'] ) ) {
            $query->query_vars['tax_query'] = $this->translate_tax_query_terms( $query->query_vars['tax_query'] );
        }

        // WPML will automatically filter by current language when suppress_filters is false
        // This ensures WPML's query filtering hooks are applied
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

            // Skip if not a Directorist taxonomy
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
                } else {
                    // Term slug - get translated slug
                    $term_obj = get_term_by( 'slug', $term, $taxonomy );
                    
                    if ( $term_obj ) {
                        $translated_term_id = apply_filters( 
                            'wpml_object_id', 
                            $term_obj->term_id, 
                            $taxonomy, 
                            false, 
                            $current_language 
                        );
                        
                        if ( $translated_term_id ) {
                            $translated_term = get_term( $translated_term_id, $taxonomy );
                            
                            if ( $translated_term && ! is_wp_error( $translated_term ) ) {
                                // Use ID if field is term_id, slug if field is slug
                                if ( isset( $query['field'] ) && 'slug' === $query['field'] ) {
                                    $translated_terms[] = $translated_term->slug;
                                } else {
                                    $translated_terms[] = $translated_term_id;
                                }
                            }
                        }
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

    /**
     * Ensure WPML filtering is enabled for Directorist queries
     * 
     * @param array $args Query arguments
     * @return array Modified query arguments
     */
    public function ensure_wpml_filtering( $args ) {
        // Ensure suppress_filters is false so WPML can filter
        if ( isset( $args['suppress_filters'] ) && $args['suppress_filters'] ) {
            $args['suppress_filters'] = false;
        }

        // If post_type is Directorist listing, ensure WPML filtering
        if ( isset( $args['post_type'] ) && ATBDP_POST_TYPE === $args['post_type'] ) {
            $args['suppress_filters'] = false;
        }

        return $args;
    }

    /**
     * Filter query results to ensure only current language listings
     * 
     * @param object $results Query results object
     * @return object Filtered results
     */
    public function filter_query_results( $results ) {
        if ( empty( $results->ids ) || ! is_array( $results->ids ) ) {
            return $results;
        }

        // Check if WPML is active
        if ( ! has_filter( 'wpml_current_language' ) || ! has_filter( 'wpml_element_language_details' ) ) {
            return $results;
        }

        $current_language = apply_filters( 'wpml_current_language', null );
        
        if ( empty( $current_language ) ) {
            return $results;
        }

        // Filter IDs to only include listings in current language
        $filtered_ids = [];
        
        foreach ( $results->ids as $listing_id ) {
            $language_info = apply_filters( 
                'wpml_element_language_details', 
                null, 
                [
                    'element_id'   => $listing_id,
                    'element_type' => ATBDP_POST_TYPE
                ]
            );

            if ( ! empty( $language_info ) && $language_info->language_code === $current_language ) {
                $filtered_ids[] = $listing_id;
            }
        }

        // Update results
        $results->ids = $filtered_ids;
        $results->total = count( $filtered_ids );
        
        // Recalculate total pages if needed
        if ( ! empty( $results->per_page ) && $results->per_page > 0 ) {
            $results->total_pages = ceil( $results->total / $results->per_page );
        }

        return $results;
    }
}

