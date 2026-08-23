<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\WPML_Helper;

class Option_Translation {
	
    /**
     * Options that should NOT be translated (query parameters, IDs, arrays, etc.)
     * These are used in queries and logic, not for display
     */
    private $non_translatable_keys = [
        'all_listing_page_items',
        'listing_columns',
        'listings_per_page',
        'listing_filters_fields',
        'listings_sort_by_items',
        'listings_view_as_items',
        'listing_popular_by',
        'views_for_popular',
        'average_review_for_popular',
        'listing_default_radius_distance',
        'sresult_default_radius_distance',
        'listings_map_height',
        'listing_filters_icon',
        'display_sort_by',
        'display_view_as',
        'paginate_all_listings',
        'display_listings_header',
        'search_header',
        'search_result_filters_button_display',
        'search_sort_by',
        'search_view_as',
        'disable_list_price',
        'pagination_type',
        'select_listing_map',
        'listings_display_filter',
        'home_display_filter',
        'search_result_display_filter',
        'listing_location_address',
        'sresult_location_address',
        'grid_view_as',
        'radius_search_unit',
        'order_listing_by',
        'sort_listing_by',
        'default_listing_view',
    ];

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
        // Skip if value is null (not set in database)
        if ( is_null( $value ) ) {
            return $value;
        }

        // Never translate non-string values (arrays, numbers, booleans)
        // These are used in queries and logic, not for display
        if ( ! is_string( $value ) ) {
            return $value;
        }

        // Skip empty strings
        if ( empty( $value ) ) {
            return $value;
        }

        // Skip options that are query parameters or logic values
        if ( in_array( $key, $this->non_translatable_keys, true ) ) {
            return $value;
        }

        // Skip numeric strings (they might be IDs or counts)
        if ( is_numeric( $value ) ) {
            return $value;
        }

        // Only translate string values that are meant for display
        // Build the translation key (matches Settings_Registration format)
        $translation_key = 'atbdp_option_' . $key;
        
        // Register the string with WPML if not already registered
        // This ensures strings are available for translation even if Settings_Registration hasn't run yet
        WPML_Helper::register_setting_string( $translation_key, $value );
        
        // Translate the value
        $translated = WPML_Helper::translate_option( $translation_key, $value );
        
        return $translated;
    }
}

