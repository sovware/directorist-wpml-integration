<?php

namespace Directorist_WPML_Integration\Controller\Hook;

class Directory_Builder_Translation_Button {

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_action( 'directorist_before_directory_type_edited', [ $this, 'render_button' ], 20 );
    }

    /**
     * Render a WPML translation shortcut on the Directorist Directory Builder screen.
     *
     * @return void
     */
    public function render_button() {
        if ( ! $this->can_render() ) {
            return;
        }

        $term_id = ! empty( $_GET['listing_type_id'] ) ? absint( wp_unslash( $_GET['listing_type_id'] ) ) : 0;
        $term    = get_term( $term_id, ATBDP_DIRECTORY_TYPE );

        if ( ! $term || is_wp_error( $term ) ) {
            return;
        }

        $translation_url = $this->get_taxonomy_translation_url();

        ?>
        <div class="directorist-wpml-builder-translation-bar">
            <div class="directorist-wpml-builder-translation-bar__content">
                <strong><?php esc_html_e( 'Translate this directory with WPML', 'directorist-wpml-integration' ); ?></strong>
                <span><?php esc_html_e( 'Open WPML translation tools for Directorist directory types.', 'directorist-wpml-integration' ); ?></span>
            </div>

            <a
                class="button button-primary directorist-wpml-builder-translation-bar__button"
                href="<?php echo esc_url( $translation_url ); ?>"
                target="_blank"
                rel="noopener noreferrer"
            >
                <?php esc_html_e( 'Open WPML Translation', 'directorist-wpml-integration' ); ?>
            </a>
        </div>
        <?php
    }

    /**
     * Check if the shortcut should be shown.
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

        return defined( 'ICL_SITEPRESS_VERSION' ) && defined( 'ATBDP_DIRECTORY_TYPE' );
    }

    /**
     * Get the WPML taxonomy translation URL for Directorist directory types.
     *
     * @return string
     */
    private function get_taxonomy_translation_url() {
        $wpml_plugin_folder = defined( 'WPML_PLUGIN_FOLDER' ) ? WPML_PLUGIN_FOLDER : 'sitepress-multilingual-cms';

        return add_query_arg(
            [
                'page'     => $wpml_plugin_folder . '/menu/taxonomy-translation.php',
                'taxonomy' => ATBDP_DIRECTORY_TYPE,
            ],
            admin_url( 'admin.php' )
        );
    }
}
