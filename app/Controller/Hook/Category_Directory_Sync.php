<?php

namespace Directorist_WPML_Integration\Controller\Hook;



class Category_Directory_Sync {

    /**
     * Constructor.
     *
     * Registers term‑creation and term‑edit hooks for the listing category
     * taxonomy so we can mirror directory assignments from the original term.
     */
    public function __construct() {
        // When a translated category is created or edited, sync its directory meta.
        add_action( 'created_' . ATBDP_CATEGORY, [ $this, 'sync_category_directory' ], 20, 2 );
        add_action( 'edited_' . ATBDP_CATEGORY, [ $this, 'sync_category_directory' ], 20, 2 );
    }

    /**
     * Check if WPML core is active enough for our integration.
     *
     * @return bool
     */
    private function is_wpml_active() {
        return defined( 'ICL_SITEPRESS_VERSION' )
            && function_exists( 'apply_filters' )
            && has_filter( 'wpml_current_language' )
            && has_filter( 'wpml_default_language' )
            && has_filter( 'wpml_object_id' );
    }


    public function sync_category_directory( $term_id, $tt_id ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
        // Ensure Directorist helper function and WPML are available.
        if ( ! function_exists( 'directorist_update_category_directory' ) ) {
            return;
        }

        if ( ! $this->is_wpml_active() ) {
            return;
        }

        // WPML languages.
        $current_lang = apply_filters( 'wpml_current_language', null );
        $default_lang = apply_filters( 'wpml_default_language', null );

        // Nothing to sync if language context is missing or we are in default language.
        if ( empty( $current_lang ) || $current_lang === $default_lang ) {
            return;
        }

        $term_id       = (int) $term_id;
        $original_term = apply_filters( 'wpml_object_id', $term_id, ATBDP_CATEGORY, true, $default_lang );
        $original_term = (int) $original_term;

        // If WPML reports this term as its own original, there is nothing to sync.
        if ( ! $original_term || $original_term === $term_id ) {
            return;
        }

        // Get directory IDs from the original language category.
        $orig_dir_ids = (array) get_term_meta( $original_term, '_directory_type', true );
        if ( empty( $orig_dir_ids ) ) {
            return;
        }

        // Translate each directory ID into the current language.
        $translated_dir_ids = [];

        foreach ( $orig_dir_ids as $orig_dir_id ) {
            $orig_dir_id = (int) $orig_dir_id;
            if ( ! $orig_dir_id ) {
                continue;
            }

            $translated_dir_id = apply_filters(
                'wpml_object_id',
                $orig_dir_id,
                ATBDP_DIRECTORY_TYPE,
                true,
                $current_lang
            );

            if ( $translated_dir_id ) {
                $translated_dir_ids[] = (int) $translated_dir_id;
            }
        }

        $translated_dir_ids = array_unique( array_filter( $translated_dir_ids ) );

        if ( empty( $translated_dir_ids ) ) {
            return;
        }

        // Update the translated category with the translated directory IDs.
        // We do NOT append here; we want the translated term’s `_directory_type`
        // to mirror the original term in its own language.
        directorist_update_category_directory( $term_id, $translated_dir_ids, false );
    }
}

