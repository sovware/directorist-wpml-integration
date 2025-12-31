<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Directory_Translation {

    public function __construct() {
        // Modify the WPML admin toolbar language switcher URLs
        add_filter( 'wpml_admin_language_switcher_items', [ $this, 'modify_language_switcher_url' ], 10, 1 );
    }

    public function modify_language_switcher_url( $languages_links ) {
        foreach ( $languages_links as $lang_code => &$lang_data ) {
            $url_parts = wp_parse_url( $lang_data['url'] );
            
            if ( isset( $url_parts['query'] ) ) {
                parse_str( $url_parts['query'], $query_vars );

                if (
                    isset( $query_vars['post_type'] ) &&
                    $query_vars['post_type'] === 'at_biz_dir' &&
                    isset( $query_vars['page'] ) &&
                    $query_vars['page'] === 'atbdp-directory-types' &&
                    ! empty( $query_vars['listing_type_id'] )
                ) {
                    $translations = WPML_Helper::get_element_translations( $query_vars['listing_type_id'], ATBDP_DIRECTORY_TYPE );

                    if ( empty( $translations ) ) {
                        continue;
                    }

                    if ( isset( $translations[ $lang_code ] ) ) {
                        $translation_id = $translations[ $lang_code ]->element_id;

                        // Change the listing_type_id of the URL to the translation_id
                        $lang_data['url'] = add_query_arg( array(
                            'listing_type_id' => $translation_id,
                        ), $lang_data['url'] );
                    }
                }
            }
        }
        
        return $languages_links;
    }
}

