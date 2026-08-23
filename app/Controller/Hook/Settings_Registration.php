<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Settings_Registration {
	
    /**
     * Constructor
     * 
     * @return void
     */
    function __construct() {
        add_action( 'updated_option', [ $this, 'register_setting_string' ], 10, 3 );
    }

    /**
     * Register Directorist setting with WPML when option is updated
     * 
     * @param string $option_name
     * @param mixed $old_value
     * @param mixed $value
     * @return void
     */
    public function register_setting_string( $option_name, $old_value, $value ) {
        // Handle atbdp_option array (main settings array)
        if ( $option_name === 'atbdp_option' && is_array( $value ) ) {
            foreach ( $value as $key => $val ) {
                if ( is_string( $val ) && ! empty( $val ) ) {
                    $full_key = 'atbdp_option_' . $key;
                    WPML_Helper::register_setting_string( $full_key, $val );
                }
            }
        }
        // Handle other atbdp_ prefixed options
        elseif ( strpos( $option_name, 'atbdp_' ) === 0 ) {
            WPML_Helper::register_setting_string( $option_name, $value );
        }
    }
}

