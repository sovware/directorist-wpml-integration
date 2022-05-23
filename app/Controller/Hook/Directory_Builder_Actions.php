<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Directory_Builder_Actions {

    public static $instance = null;

     /**
     * Constuctor
     * 
     * @return void
     */
    public function __construct() {
        add_action( 'directorist_before_set_default_directory_type', [ $this, 'before_update_default_directory_type' ], 20, 1 );
        add_action( 'directorist_after_set_default_directory_type', [ $this, 'after_set_default_directory_type' ], 20, 1 );
    }

    /**
     * Before update default directory type
     * 
     * @param int $directory_type_id
     * @return void
     */
    public function before_update_default_directory_type( $directory_type_id = 0 ) {

        $current_language = apply_filters( 'wpml_current_language', NULL );

        set_transient( 'directorist_wpml_integration:current_language', $current_language );

        do_action( 'wpml_switch_language', 'all' );

    }

    /**
     * After set default directory type
     * 
     * @param int $directory_type_id
     * 
     * @return void
     */
    public function after_update_default_directory_type( $directory_type_id = 0 ) {

        $current_language  = apply_filters( 'wpml_current_language', NULL );
        $previous_language = get_transient( 'directorist_wpml_integration:current_language' );
        $current_language  = ( ! empty( $previous_language ) ) ? $previous_language : $current_language;

        do_action( 'wpml_switch_language', $current_language );

        delete_transient( 'directorist_wpml_integration:current_language' );

        $element_type   = apply_filters( 'wpml_element_type', ATBDP_DIRECTORY_TYPE );
        $translation_id = apply_filters( 'wpml_element_trid', NULL, $directory_type_id, $element_type );
        $translations   = apply_filters( 'wpml_get_element_translations', NULL, $translation_id, $element_type );

        if ( empty( $translations ) ) {
            return;
        }

        foreach( $translations as $translation ) {
            update_term_meta( $translation->term_id, '_default', true );
        }
    }
}
