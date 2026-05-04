<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Directory_Type_Meta_Sync {

    /**
     * Directory type term meta keys that should stay in sync across translations.
     *
     * These are structural or behavior settings that should follow the source
     * directory type, while builder/content text stays translatable per language.
     *
     * @var string[]
     */
    private const COPY_META_KEYS = [
        'general_config',
        'preview_mode',
        'default_expiration',
        'new_listing_status',
        'edit_listing_status',
        'listing_terms_condition',
        'require_terms_conditions',
        'listing_privacy',
        'require_privacy',
        'privacy_is_required',
        'enable_sidebar',
        'enable_single_listing_page',
        'single_listing_page',
        'enable_similar_listings',
        'similar_listings_logics',
        'listing_from_same_author',
        'similar_listings_number_of_listings_to_show',
        'similar_listings_number_of_columns',
    ];

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_action( 'directorist_after_update_directory_type', [ $this, 'sync_copied_term_meta' ], 30, 1 );
    }

    /**
     * Sync structural directory-type term meta from the source term to its translations.
     *
     * Directorist updates directory type settings by writing term meta directly, so
     * WPML's native copy-on-term-save flow does not always run for the builder save.
     * This hook keeps copy-only settings aligned without touching translated text fields.
     *
     * @param int $directory_type_id Directory type term ID.
     * @return void
     */
    public function sync_copied_term_meta( $directory_type_id = 0 ) {
        $directory_type_id = absint( $directory_type_id );

        if ( ! $directory_type_id || ! $this->is_wpml_active() ) {
            return;
        }

        $language_info = WPML_Helper::get_language_info( $directory_type_id, ATBDP_DIRECTORY_TYPE );

        // Only sync outward from the source/original directory type.
        if ( empty( $language_info ) || ! empty( $language_info->source_language_code ) ) {
            return;
        }

        $translations = WPML_Helper::get_element_translations( $directory_type_id, ATBDP_DIRECTORY_TYPE );

        if ( empty( $translations ) || ! is_array( $translations ) ) {
            return;
        }

        foreach ( $translations as $translation ) {
            $translated_term_id = isset( $translation->term_id ) ? absint( $translation->term_id ) : 0;

            if ( ! $translated_term_id || $translated_term_id === $directory_type_id ) {
                continue;
            }

            foreach ( self::COPY_META_KEYS as $meta_key ) {
                $this->replace_term_meta( $directory_type_id, $translated_term_id, $meta_key );
            }
        }
    }

    /**
     * Replace all values for a term meta key with the source term's values.
     *
     * @param int    $source_term_id Source term ID.
     * @param int    $target_term_id Target term ID.
     * @param string $meta_key       Meta key to sync.
     * @return void
     */
    private function replace_term_meta( $source_term_id, $target_term_id, $meta_key ) {
        $source_values = get_term_meta( $source_term_id, $meta_key, false );

        delete_term_meta( $target_term_id, $meta_key );

        if ( empty( $source_values ) ) {
            return;
        }

        foreach ( $source_values as $source_value ) {
            add_term_meta( $target_term_id, $meta_key, maybe_unserialize( $source_value ) );
        }
    }

    /**
     * Check whether WPML APIs are available.
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
}
