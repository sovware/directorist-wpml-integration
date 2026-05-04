<?php

namespace Directorist_WPML_Integration\Controller\Hook;

class Directory_Builder_ATE_Translation_Button {

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_action( 'directorist_before_directory_type_edited', [ $this, 'render_button' ], 20 );
    }

    /**
     * Render the WPML ATE entry point on the Directorist Directory Builder screen.
     *
     * @return void
     */
    public function render_button() {
        if ( ! $this->can_render() ) {
            return;
        }

        $term_id   = ! empty( $_GET['listing_type_id'] ) ? absint( wp_unslash( $_GET['listing_type_id'] ) ) : 0;
        $term      = get_term( $term_id, ATBDP_DIRECTORY_TYPE );
        $languages = $this->get_target_languages( $term_id );

        if ( ! $term || is_wp_error( $term ) || empty( $languages ) ) {
            return;
        }

        ?>
        <div class="directorist-wpml-builder-ate-panel" data-directory-type-id="<?php echo esc_attr( $term_id ); ?>">
            <div class="directorist-wpml-builder-ate-panel__content">
                <strong><?php esc_html_e( 'Translate this directory with WPML Advanced Translation Editor', 'directorist-wpml-integration' ); ?></strong>
                <span><?php esc_html_e( 'Send the builder strings from General, forms, search, and layouts to WPML ATE.', 'directorist-wpml-integration' ); ?></span>
            </div>

            <div class="directorist-wpml-builder-ate-panel__actions">
                <select class="directorist-wpml-builder-ate-panel__language" aria-label="<?php esc_attr_e( 'Translation language', 'directorist-wpml-integration' ); ?>">
                    <?php foreach ( $languages as $language_code => $language ) : ?>
                        <option value="<?php echo esc_attr( $language_code ); ?>">
                            <?php echo esc_html( $language['translated_name'] ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" class="button button-primary directorist-wpml-builder-ate-button">
                    <?php esc_html_e( 'Translate in ATE', 'directorist-wpml-integration' ); ?>
                </button>
            </div>
        </div>
        <?php
    }

    /**
     * Check if the button should be shown.
     *
     * @return bool
     */
    private function can_render() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return false;
        }

        if ( empty( $_GET['page'] ) || 'atbdp-directory-types' !== sanitize_key( wp_unslash( $_GET['page'] ) ) ) {
            return false;
        }

        if ( empty( $_GET['action'] ) || 'edit' !== sanitize_key( wp_unslash( $_GET['action'] ) ) ) {
            return false;
        }

        if ( empty( $_GET['listing_type_id'] ) ) {
            return false;
        }

        return defined( 'ICL_SITEPRESS_VERSION' )
            && defined( 'WPML_ST_VERSION' )
            && defined( 'WPML_TM_VERSION' )
            && defined( 'ATBDP_DIRECTORY_TYPE' )
            && class_exists( '\WPML_TM_ATE_Status' )
            && method_exists( '\WPML_TM_ATE_Status', 'is_enabled_and_activated' )
            && \WPML_TM_ATE_Status::is_enabled_and_activated();
    }

    /**
     * Get available target languages for the current directory type.
     *
     * @param int $term_id Directory type term ID.
     *
     * @return array
     */
    private function get_target_languages( $term_id ) {
        $languages       = apply_filters( 'wpml_active_languages', null, 'orderby=name&order=asc' );
        $source_language = $this->get_source_language( $term_id );

        if ( empty( $languages ) || ! is_array( $languages ) ) {
            return [];
        }

        if ( empty( $source_language ) ) {
            $source_language = apply_filters( 'wpml_default_language', null );
        }

        unset( $languages[ $source_language ] );

        return $languages;
    }

    /**
     * Get the original language code for a directory type.
     *
     * @param int $term_id Directory type term ID.
     *
     * @return string
     */
    private function get_source_language( $term_id ) {
        $wpml_element_type = apply_filters( 'wpml_element_type', ATBDP_DIRECTORY_TYPE );
        $language_info     = apply_filters(
            'wpml_element_language_details',
            null,
            [
                'element_id'   => $term_id,
                'element_type' => $wpml_element_type,
            ]
        );

        if ( ! empty( $language_info->source_language_code ) ) {
            return $language_info->source_language_code;
        }

        return ! empty( $language_info->language_code ) ? $language_info->language_code : '';
    }
}
