<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Option_Translation {
	
    /**
     * Constructor
     * 
     * @return void
     */
    function __construct() {
        // Filter get_directorist_option to return translated values
        add_filter( 'directorist_option', [ $this, 'translate_option_value' ], 10, 2 );
    }

    /**
     * Translate option value when retrieved
     * 
     * @param mixed $value Option value
     * @param string $key Option key
     * @return mixed Translated value
     */
    public function translate_option_value( $value, $key ) {
        // Only translate string values
        if ( ! is_string( $value ) || empty( $value ) ) {
            return $value;
        }

        // Build the translation key (matches Settings_Registration format)
        $translation_key = 'atbdp_option_' . $key;
        
        // Translate the value
        $translated = WPML_Helper::translate_option( $translation_key, $value );
        
        return $translated;
    }
}

