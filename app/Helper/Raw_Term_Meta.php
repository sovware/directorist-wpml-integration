<?php

namespace Directorist_WPML_Integration\Helper;

class Raw_Term_Meta {

    /**
     * Read serialized term meta without invoking metadata translation filters.
     *
     * @param int    $term_id  Term ID.
     * @param string $meta_key Metadata key.
     * @return array
     */
    public static function get_values( $term_id, $meta_key ) {
        $term_id = (int) $term_id;
        $cache   = wp_cache_get( $term_id, 'term_meta' );

        if ( false === $cache ) {
            $meta_cache = update_meta_cache( 'term', [ $term_id ] );
            $cache      = isset( $meta_cache[ $term_id ] ) ? $meta_cache[ $term_id ] : [];
        }

        foreach ( array_keys( $cache ) as $stored_key ) {
            if ( $stored_key !== $meta_key && 0 === strcasecmp( $stored_key, $meta_key ) ) {
                global $wpdb;

                // Let the database resolve legacy key casing and preserve the
                // order of rows when multiple spellings match its collation.
                return $wpdb->get_col(
                    $wpdb->prepare(
                        "SELECT meta_value FROM {$wpdb->termmeta} WHERE term_id = %d AND meta_key = %s ORDER BY meta_id ASC",
                        $term_id,
                        $meta_key
                    )
                );
            }
        }

        return isset( $cache[ $meta_key ] ) ? $cache[ $meta_key ] : [];
    }
}
