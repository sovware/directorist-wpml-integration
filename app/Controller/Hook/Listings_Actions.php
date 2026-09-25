<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Listings_Actions {

    public static $instance = null;

    const UI_META_KEY = '_directorist_wpml_page_ui_strings';

     /**
     * Constuctor
     * 
     * @return void
     */
    public function __construct() {
        add_filter( 'directorist_show_admin_edit_listing_directory_type_nav', '__return_true', 20, 1 );
        add_filter( 'directorist_should_update_directory_type', '__return_false', 20, 1 );
        add_action( 'icl_make_duplicate', [ $this, 'update_directory_type_after_listing_duplicate' ], 20, 4 );
        add_action( 'post_updated', [ $this, 'update_directory_type_after_listing_update' ], 20, 1 );
        add_filter( 'wpml_pro_translation_completed', [ $this, 'update_directory_type_after_listing_translation' ], 20, 3 );
        add_action( 'save_post_at_biz_dir', [ $this, 'clear_listing_ui_strings_on_save' ], 40, 3 );
        add_action( 'wpml_pb_register_all_strings_for_translation', [ $this, 'clear_listing_ui_strings_before_translation' ], 40 );
        add_action( 'directorist_after_update_directory_type', [ $this, 'clear_directory_listing_ui_strings' ], 40, 1 );
    }

    /**
     * Update Directory Type After Listing Translation
     * 
     * @param int $new_post_id
     * 
     * @return int New post ID
     */
    public function update_directory_type_after_listing_translation( $new_post_id = 0, $fields = [], $job = null ) {

        if ( ATBDP_POST_TYPE !== get_post_type( $new_post_id ) ) {
            return $new_post_id;
        }

        $source_post_id = $this->get_source_listing_id( $new_post_id, $job );
        $target_language = $this->get_listing_language( $new_post_id, $job );

        if ( $source_post_id <= 0 || empty( $target_language ) ) {
            return $new_post_id;
        }

        $source_directory_id = $this->get_valid_directory_type_id( $source_post_id );
        $target_directory_id = $this->get_translated_directory_type_id( $source_directory_id, $target_language );

        if ( $target_directory_id > 0 ) {
            $this->set_listing_directory_type( $new_post_id, $target_directory_id );
        }

        return $new_post_id;
    }

    /**
     * Remove legacy Directory Builder strings when a listing is saved.
     *
     * @param int      $post_id Listing ID.
     * @param \WP_Post $post    Listing post.
     * @param bool     $update  Whether this is an update.
     * @return void
     */
    public function clear_listing_ui_strings_on_save( $post_id, $post, $update ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ATBDP_POST_TYPE !== $post->post_type ) {
            return;
        }

        $this->clear_listing_ui_strings( (int) $post_id );
    }

    /**
     * Remove stored Directory Builder text before WPML builds a listing job.
     *
     * Existing listings may still contain directory-level text collected by an older version.
     *
     * @param \WP_Post $post Source post being sent for translation.
     * @return void
     */
    public function clear_listing_ui_strings_before_translation( $post ) {
        if ( ! $post instanceof \WP_Post || ATBDP_POST_TYPE !== $post->post_type ) {
            return;
        }

        $this->clear_listing_ui_strings( $post->ID );
    }

    /**
     * Remove legacy UI payloads from listings when their directory layout changes.
     *
     * @param int $directory_id Directory type term ID.
     * @return void
     */
    public function clear_directory_listing_ui_strings( $directory_id ) {
        $directory_id = absint( $directory_id );
        if ( ! $this->is_valid_directory_type( $directory_id ) || ! $this->is_source_directory_type( $directory_id ) ) {
            return;
        }

        global $wpdb;

        $listing_ids = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT DISTINCT p.ID
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                WHERE p.post_type = %s
                    AND p.post_status NOT IN ('trash', 'auto-draft')
                    AND tt.taxonomy = %s
                    AND tt.term_id = %d",
                ATBDP_POST_TYPE,
                ATBDP_DIRECTORY_TYPE,
                $directory_id
            )
        );

        foreach ( array_map( 'absint', $listing_ids ) as $listing_id ) {
            $this->clear_listing_ui_strings( $listing_id );
        }
    }

    /**
     * Update directory type after listing duplicate
     * 
     * @param int $master_post_id
     * @param string $lang
     * @param array $post_array
     * @param int $post_id
     * 
     * @return void
     */
    public function update_directory_type_after_listing_duplicate( $master_post_id, $lang, $post_array, $post_id ) {

        if ( get_post_type( $post_id ) !== ATBDP_POST_TYPE ) {
            return;
        }

        $directory_type_meta_id = get_post_meta( $post_id, '_directory_type', true );
        $directory_type_term    = get_the_terms( $post_id, ATBDP_DIRECTORY_TYPE );
        $directory_type_id      = ( ! is_wp_error( $directory_type_term ) && ! empty( $directory_type_term ) ) ? $directory_type_term[0]->term_id : $directory_type_meta_id;
        
        $directory_type_translations = WPML_Helper::get_element_translations( $directory_type_id, ATBDP_DIRECTORY_TYPE );

        if ( empty( $directory_type_translations ) ) {
            return;
        }

        if ( empty( $directory_type_translations[ $lang ] ) ) {
            return;
        }

        $translated_directory_type_id = $directory_type_translations[ $lang ]->term_id;
        
        update_post_meta( $post_id, '_directory_type', $translated_directory_type_id );
    }

    /**
     * Update directory type meta after listing update
     * 
     * @param int $post_id
     * @return void
     */
    public function update_directory_type_after_listing_update( $post_id ) {

        if ( get_post_type( $post_id ) !== ATBDP_POST_TYPE ) {
            return;
        }

        $directory_type_id = isset( $_REQUEST['directory_type'] ) ? absint( wp_unslash( $_REQUEST['directory_type'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $source_post_id    = $this->get_source_listing_id( $post_id );

        if ( $directory_type_id <= 0 ) {
            $directory_type_id = $this->get_valid_directory_type_id( $source_post_id );
        }

        if ( $directory_type_id <= 0 ) {
            return;
        }

        $listings_translations = WPML_Helper::get_element_translations( $source_post_id, ATBDP_POST_TYPE );

        foreach ( $listings_translations as $language_key => $listing_translation ) {
            if ( empty( $listing_translation->element_id ) ) {
                continue;
            }

            $translated_directory_id = $this->get_translated_directory_type_id( $directory_type_id, $language_key );
            if ( $translated_directory_id <= 0 ) {
                continue;
            }

            $this->set_listing_directory_type( (int) $listing_translation->element_id, $translated_directory_id );
        }
    }

    /**
     * Resolve the source listing for a translated listing.
     *
     * @param int         $listing_id Listing ID.
     * @param object|null $job        WPML translation job.
     * @return int
     */
    private function get_source_listing_id( $listing_id, $job = null ) {
        if ( is_object( $job ) && ! empty( $job->original_doc_id ) ) {
            $job_source_id = (int) $job->original_doc_id;

            if ( $job_source_id > 0 && ATBDP_POST_TYPE === get_post_type( $job_source_id ) ) {
                return $job_source_id;
            }
        }

        $translations = WPML_Helper::get_element_translations( $listing_id, ATBDP_POST_TYPE );

        foreach ( $translations as $translation ) {
            if ( ! empty( $translation->original ) && ! empty( $translation->element_id ) ) {
                return (int) $translation->element_id;
            }
        }

        $language_info = WPML_Helper::get_language_info( $listing_id, ATBDP_POST_TYPE );
        if ( is_object( $language_info ) && ! empty( $language_info->source_language_code ) && ! empty( $translations[ $language_info->source_language_code ]->element_id ) ) {
            return (int) $translations[ $language_info->source_language_code ]->element_id;
        }

        return (int) $listing_id;
    }

    /**
     * Resolve the translated listing language.
     *
     * @param int         $listing_id Listing ID.
     * @param object|null $job        WPML translation job.
     * @return string
     */
    private function get_listing_language( $listing_id, $job = null ) {
        if ( is_object( $job ) && ! empty( $job->language_code ) ) {
            return sanitize_key( $job->language_code );
        }

        $language_info = WPML_Helper::get_language_info( $listing_id, ATBDP_POST_TYPE );

        return is_object( $language_info ) && ! empty( $language_info->language_code ) ? sanitize_key( $language_info->language_code ) : '';
    }

    /**
     * Get a listing's valid directory ID, preferring raw Directorist meta.
     *
     * @param int $listing_id Listing ID.
     * @return int
     */
    private function get_valid_directory_type_id( $listing_id ) {
        $directory_type_id = absint( get_post_meta( $listing_id, '_directory_type', true ) );

        if ( $this->is_valid_directory_type( $directory_type_id ) ) {
            return $directory_type_id;
        }

        $directory_terms = wp_get_object_terms(
            $listing_id,
            ATBDP_DIRECTORY_TYPE,
            [ 'fields' => 'ids' ]
        );

        if ( is_wp_error( $directory_terms ) || empty( $directory_terms ) ) {
            return 0;
        }

        $directory_type_id = absint( reset( $directory_terms ) );

        return $this->is_valid_directory_type( $directory_type_id ) ? $directory_type_id : 0;
    }

    /**
     * Map a directory term into a target WPML language.
     *
     * @param int    $directory_type_id Source directory term ID.
     * @param string $target_language   Target language code.
     * @return int
     */
    private function get_translated_directory_type_id( $directory_type_id, $target_language ) {
        $directory_type_id = absint( $directory_type_id );
        $target_language   = sanitize_key( $target_language );

        if ( ! $this->is_valid_directory_type( $directory_type_id ) || empty( $target_language ) ) {
            return 0;
        }

        $translations = WPML_Helper::get_element_translations( $directory_type_id, ATBDP_DIRECTORY_TYPE );

        if ( ! empty( $translations[ $target_language ]->term_id ) ) {
            $translated_directory_id = absint( $translations[ $target_language ]->term_id );

            return $this->is_valid_directory_type( $translated_directory_id ) ? $translated_directory_id : 0;
        }

        $language_info = WPML_Helper::get_language_info( $directory_type_id, ATBDP_DIRECTORY_TYPE );
        if ( is_object( $language_info ) && ! empty( $language_info->language_code ) && $target_language === $language_info->language_code ) {
            return $directory_type_id;
        }

        return 0;
    }

    /**
     * Check that a directory term exists in the Directorist taxonomy.
     *
     * @param int $directory_type_id Directory term ID.
     * @return bool
     */
    private function is_valid_directory_type( $directory_type_id ) {
        $directory_type_id = absint( $directory_type_id );

        return $directory_type_id > 0 && ! empty( term_exists( $directory_type_id, ATBDP_DIRECTORY_TYPE ) );
    }

    /**
     * Persist a listing's directory meta and matching taxonomy relationship.
     *
     * @param int $listing_id       Listing ID.
     * @param int $directory_type_id Directory term ID.
     * @return void
     */
    private function set_listing_directory_type( $listing_id, $directory_type_id ) {
        $listing_id        = absint( $listing_id );
        $directory_type_id = absint( $directory_type_id );

        if ( $listing_id <= 0 || ! $this->is_valid_directory_type( $directory_type_id ) ) {
            return;
        }

        if ( $directory_type_id !== absint( get_post_meta( $listing_id, '_directory_type', true ) ) ) {
            update_post_meta( $listing_id, '_directory_type', $directory_type_id );
        }

        $current_terms = wp_get_object_terms( $listing_id, ATBDP_DIRECTORY_TYPE, [ 'fields' => 'ids' ] );
        if ( is_wp_error( $current_terms ) || [ $directory_type_id ] !== array_map( 'absint', $current_terms ) ) {
            wp_set_object_terms( $listing_id, [ $directory_type_id ], ATBDP_DIRECTORY_TYPE, false );
        }
    }

    /**
     * Remove the legacy directory-level payload from a listing translation job.
     *
     * Directory Builder strings are translated once through their own WPML
     * package, not repeatedly with every listing.
     *
     * @param int $listing_id Listing ID.
     * @return void
     */
    private function clear_listing_ui_strings( $listing_id ) {
        $listing_id = absint( $listing_id );
        if ( $listing_id <= 0 || ATBDP_POST_TYPE !== get_post_type( $listing_id ) ) {
            return;
        }

        if ( delete_post_meta( $listing_id, self::UI_META_KEY ) && $this->is_source_listing( $listing_id ) ) {
            $this->mark_listing_translations_need_update( $listing_id );
        }
    }

    /**
     * Check whether a listing is the source item in its WPML translation set.
     *
     * @param int $listing_id Listing ID.
     * @return bool
     */
    private function is_source_listing( $listing_id ) {
        $language_info = WPML_Helper::get_language_info( $listing_id, ATBDP_POST_TYPE );

        return is_object( $language_info ) && empty( $language_info->source_language_code );
    }

    /**
     * Check whether a directory is the source item in its WPML translation set.
     *
     * @param int $directory_id Directory type ID.
     * @return bool
     */
    private function is_source_directory_type( $directory_id ) {
        $language_info = WPML_Helper::get_language_info( $directory_id, ATBDP_DIRECTORY_TYPE );

        return is_object( $language_info ) && empty( $language_info->source_language_code );
    }

    /**
     * Mark existing listing translations stale when their legacy UI payload is removed.
     *
     * @param int $source_listing_id Source listing ID.
     * @return void
     */
    private function mark_listing_translations_need_update( $source_listing_id ) {
        global $wpdb;

        foreach ( WPML_Helper::get_element_translations( $source_listing_id, ATBDP_POST_TYPE ) as $translation ) {
            if ( ! is_object( $translation ) || ! empty( $translation->original ) || empty( $translation->translation_id ) ) {
                continue;
            }

            $wpdb->update(
                $wpdb->prefix . 'icl_translation_status',
                [ 'needs_update' => 1 ],
                [ 'translation_id' => absint( $translation->translation_id ) ],
                [ '%d' ],
                [ '%d' ]
            );
        }
    }
}
