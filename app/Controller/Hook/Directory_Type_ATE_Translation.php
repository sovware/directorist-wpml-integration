<?php

namespace Directorist_WPML_Integration\Controller\Hook;

use Directorist_WPML_Integration\Helper\Response;
use Directorist_WPML_Integration\Helper\WPML_Helper;
use WPML\TM\API\Jobs;

class Directory_Type_ATE_Translation {

    const PACKAGE_KIND      = 'Directorist Directory';
    const PACKAGE_KIND_SLUG = 'directorist-directory';
    const PACKAGE_NAME      = 'directory-type-';
    const MAP_OPTION_PREFIX = 'directorist_wpml_directory_type_ate_map_';

    /**
     * Directory builder term meta fields whose scalar strings should be sent to ATE.
     *
     * @var string[]
     */
    private $translatable_meta_keys = [
        'submit_button_label',
        'terms_privacy_label',
        'terms_label',
        'terms_name',
        'terms_link',
        'privacy_label',
        'privacy_name',
        'privacy_link',
        'similar_listings_title',
        'submission_form_fields',
        'search_form_fields',
        'single_listing_header',
        'single_listings_contents',
        'listings_card_grid_view',
        'listings_card_list_view',
    ];

    /**
     * Array leaf keys that represent user-facing builder text.
     *
     * @var string[]
     */
    private $translatable_leaf_keys = [
        'label',
        'placeholder',
        'title',
        'text',
        'name',
        'description',
        'button_text',
        'button_label',
        'price_range_label',
        'price_unit_field_label',
    ];

    /**
     * Constructor.
     *
     * @return void
     */
    public function __construct() {
        add_action( 'directorist_after_update_directory_type', [ $this, 'register_directory_type_package' ], 30, 1 );
        add_action( 'wpml_register_string_packages', [ $this, 'register_directory_type_packages' ] );
        add_action( 'wpml_save_external', [ $this, 'apply_package_translation_to_directory_type' ], 20, 3 );
    }

    /**
     * Prepare an ATE package job for a directory type and target language.
     *
     * @param int    $directory_type_id Directory type term ID.
     * @param string $target_language Target language code.
     * @param string $return_url URL to return to after editing.
     *
     * @return Response
     */
    public function prepare_translation_job( $directory_type_id, $target_language, $return_url = '' ) {
        $response = new Response();

        if ( ! $this->is_available() ) {
            $response->message = __( 'WPML Advanced Translation Editor and String Translation must be active before translating directory builder strings.', 'directorist-wpml-integration' );
            return $response;
        }

        $source_term_id = $this->get_original_directory_type_id( $directory_type_id );
        $term           = get_term( $source_term_id, ATBDP_DIRECTORY_TYPE );

        if ( ! $term || is_wp_error( $term ) ) {
            $response->message = __( 'The directory type is not valid.', 'directorist-wpml-integration' );
            return $response;
        }

        if ( empty( $target_language ) ) {
            $response->message = __( 'Translation language code is required.', 'directorist-wpml-integration' );
            return $response;
        }

        $package = $this->register_directory_type_package( $source_term_id );

        if ( ! $package || empty( $package->ID ) ) {
            $response->message = __( 'Could not register the directory builder strings with WPML.', 'directorist-wpml-integration' );
            return $response;
        }

        $translated_term_id = $this->ensure_directory_type_translation( $source_term_id, $target_language );

        if ( ! $translated_term_id ) {
            $response->message = __( 'Could not create or find the translated directory type.', 'directorist-wpml-integration' );
            return $response;
        }

        $job_id = $this->create_ate_job( $package, $source_term_id, $target_language );

        if ( ! $job_id ) {
            $response->message = __( 'Could not create the WPML Advanced Translation Editor job.', 'directorist-wpml-integration' );
            return $response;
        }

        $return_url = $return_url ? $return_url : admin_url( 'edit.php?post_type=at_biz_dir&page=atbdp-directory-types' );

        $response->success = true;
        $response->message = __( 'The directory builder translation job is ready.', 'directorist-wpml-integration' );
        $response->data    = [
            'job_id'              => $job_id,
            'translation_term_id' => $translated_term_id,
            'edit_link'           => class_exists( Jobs::class ) ? Jobs::getEditUrl( $return_url, $job_id ) : admin_url( 'admin.php?page=' . WPML_TM_FOLDER . '/menu/translations-queue.php&job_id=' . $job_id ),
        ];

        return $response;
    }

    /**
     * Register all source-language directory types as WPML string packages.
     *
     * @return void
     */
    public function register_directory_type_packages() {
        if ( ! $this->is_available() || ! defined( 'ATBDP_DIRECTORY_TYPE' ) ) {
            return;
        }

        $terms = get_terms(
            [
                'taxonomy'   => ATBDP_DIRECTORY_TYPE,
                'hide_empty' => false,
            ]
        );

        if ( is_wp_error( $terms ) || empty( $terms ) ) {
            return;
        }

        foreach ( $terms as $term ) {
            if ( (int) $term->term_id !== (int) $this->get_original_directory_type_id( $term->term_id ) ) {
                continue;
            }

            $this->register_directory_type_package( $term->term_id );
        }
    }

    /**
     * Register one source directory type as a WPML package.
     *
     * @param int $directory_type_id Directory type term ID.
     *
     * @return \WPML_Package|null
     */
    public function register_directory_type_package( $directory_type_id ) {
        if ( ! $this->is_available() || empty( $directory_type_id ) ) {
            return null;
        }

        $source_term_id = $this->get_original_directory_type_id( $directory_type_id );
        $term           = get_term( $source_term_id, ATBDP_DIRECTORY_TYPE );

        if ( ! $term || is_wp_error( $term ) ) {
            return null;
        }

        $package_data = $this->get_package_data( $term );
        $strings      = $this->get_directory_type_strings( $term );

        do_action( 'wpml_start_string_package_registration', $package_data );

        foreach ( $strings as $string_name => $string ) {
            do_action(
                'wpml_register_string',
                $string['value'],
                $string_name,
                $package_data,
                $string['title'],
                'LINE'
            );
        }

        do_action( 'wpml_delete_unused_package_strings', $package_data );

        $package = new \WPML_Package( $package_data );

        if ( ! empty( $package->ID ) ) {
            $source_language = $this->get_directory_type_language( $source_term_id );
            $package_tm      = new \WPML_Package_TM( $package );

            $package_tm->set_language_details( $source_language );
            $package->set_strings_language( $source_language );
            update_option( self::MAP_OPTION_PREFIX . $package->ID, $this->pluck_string_targets( $strings ), false );
        }

        return $package;
    }

    /**
     * Apply completed package translations back to the translated directory term meta.
     *
     * @param string   $element_type_prefix WPML element type prefix.
     * @param \stdClass $job Translation job.
     * @param callable $decoder WPML field decoder.
     *
     * @return void
     */
    public function apply_package_translation_to_directory_type( $element_type_prefix, $job, $decoder ) {
        if ( 'package' !== $element_type_prefix || empty( $job->original_doc_id ) || empty( $job->language_code ) ) {
            return;
        }

        $package = new \WPML_Package( $job->original_doc_id );

        if ( empty( $package->ID ) || self::PACKAGE_KIND_SLUG !== $package->kind_slug ) {
            return;
        }

        $source_term_id = $this->get_term_id_from_package( $package );

        if ( ! $source_term_id ) {
            return;
        }

        $translated_term_id = $this->ensure_directory_type_translation( $source_term_id, $job->language_code );

        if ( ! $translated_term_id ) {
            return;
        }

        $map = get_option( self::MAP_OPTION_PREFIX . $package->ID, [] );

        if ( empty( $map ) || ! is_array( $map ) ) {
            $map = $this->pluck_string_targets( $this->get_directory_type_strings( get_term( $source_term_id, ATBDP_DIRECTORY_TYPE ) ) );
        }

        foreach ( $job->elements as $field ) {
            if ( empty( $field->field_translate ) || empty( $map[ $field->field_type ] ) ) {
                continue;
            }

            $translated_value = call_user_func( $decoder, $field->field_data_translated, $field->field_format );
            $this->apply_translated_value( $translated_term_id, $source_term_id, $map[ $field->field_type ], $translated_value );
        }
    }

    /**
     * Check whether WPML package jobs can be used.
     *
     * @return bool
     */
    private function is_available() {
        return defined( 'ICL_SITEPRESS_VERSION' )
            && defined( 'WPML_ST_VERSION' )
            && defined( 'WPML_TM_VERSION' )
            && class_exists( '\WPML_Package' )
            && class_exists( '\WPML_Package_TM' )
            && class_exists( '\WPML_TM_Translation_Batch' )
            && class_exists( '\WPML_TM_Translation_Batch_Element' )
            && class_exists( '\WPML_TM_ATE_Status' )
            && method_exists( '\WPML_TM_ATE_Status', 'is_enabled_and_activated' )
            && \WPML_TM_ATE_Status::is_enabled_and_activated()
            && defined( 'ATBDP_DIRECTORY_TYPE' );
    }

    /**
     * Create a local ATE job for a WPML package.
     *
     * @param \WPML_Package $package WPML package.
     * @param int           $source_term_id Source term ID.
     * @param string        $target_language Target language code.
     *
     * @return int
     */
    private function create_ate_job( $package, $source_term_id, $target_language ) {
        $source_language = $this->get_directory_type_language( $source_term_id );
        $element         = new \WPML_TM_Translation_Batch_Element(
            $package->ID,
            'package',
            $source_language,
            [
                $target_language => \TranslationManagement::TRANSLATE_ELEMENT_ACTION,
            ]
        );

        $batch = new \WPML_TM_Translation_Batch(
            [ $element ],
            sprintf( 'Directorist Directory: %s', get_term_field( 'name', $source_term_id, ATBDP_DIRECTORY_TYPE ) ),
            [
                $target_language => 0,
            ]
        );

        if ( method_exists( $batch, 'setTranslationMode' ) ) {
            $batch->setTranslationMode( 'manual' );
        }

        if ( ! function_exists( 'wpml_tm_add_translation_job' ) && defined( 'WPML_TM_PATH' ) ) {
            require_once WPML_TM_PATH . '/inc/wpml-private-actions-tm.php';
        }

        do_action( 'wpml_tm_send_package_jobs', $batch, 'package', class_exists( Jobs::class ) ? Jobs::SENT_MANUALLY : null );

        global $sitepress;

        $element_type = 'package_' . $package->kind_slug;
        $trid         = $sitepress->get_element_trid( $package->ID, $element_type );

        $job_id = (int) wpml_load_core_tm()->get_translation_job_id( $trid, $target_language );

        if ( $job_id && class_exists( '\WPML_TM_Editors' ) ) {
            wpml_tm_load_old_jobs_editor()->set( $job_id, \WPML_TM_Editors::ATE );
            wpml_tm_load_job_factory()->update_job_data( $job_id, [ 'editor' => \WPML_TM_Editors::ATE ] );
        }

        return $job_id;
    }

    /**
     * Ensure a translated Directorist directory term exists.
     *
     * @param int    $source_term_id Source term ID.
     * @param string $target_language Target language code.
     *
     * @return int
     */
    private function ensure_directory_type_translation( $source_term_id, $target_language ) {
        $translations = WPML_Helper::get_element_translations( $source_term_id, ATBDP_DIRECTORY_TYPE );

        if ( ! empty( $translations[ $target_language ]->term_id ) ) {
            return (int) $translations[ $target_language ]->term_id;
        }

        $source_term = get_term( $source_term_id, ATBDP_DIRECTORY_TYPE );

        if ( ! $source_term || is_wp_error( $source_term ) ) {
            return 0;
        }

        $duplicate = WPML_Helper::create_duplicate_term(
            $source_term_id,
            ATBDP_DIRECTORY_TYPE,
            $source_term->name . ' - ' . strtoupper( $target_language )
        );

        if ( empty( $duplicate->success ) || empty( $duplicate->data['new_term_id'] ) ) {
            return 0;
        }

        $translated_term_id = (int) $duplicate->data['new_term_id'];
        $set_translation    = WPML_Helper::set_post_translation( $source_term_id, $translated_term_id, $target_language, ATBDP_DIRECTORY_TYPE );

        return ! empty( $set_translation->success ) ? $translated_term_id : 0;
    }

    /**
     * Build the package data WPML expects.
     *
     * @param \WP_Term $term Directory type term.
     *
     * @return array
     */
    private function get_package_data( $term ) {
        return [
            'kind'      => self::PACKAGE_KIND,
            'kind_slug' => self::PACKAGE_KIND_SLUG,
            'name'      => self::PACKAGE_NAME . (int) $term->term_id,
            'title'     => sprintf( 'Directorist Directory: %s', $term->name ),
            'edit_link' => admin_url( 'edit.php?post_type=at_biz_dir&page=atbdp-directory-types&listing_type_id=' . (int) $term->term_id . '&action=edit' ),
            'view_link' => '',
        ];
    }

    /**
     * Extract translatable strings from a directory type.
     *
     * @param \WP_Term $term Directory type term.
     *
     * @return array
     */
    private function get_directory_type_strings( $term ) {
        $strings = [];

        if ( ! $term || is_wp_error( $term ) ) {
            return $strings;
        }

        $strings['term__name'] = [
            'value'  => $term->name,
            'title'  => __( 'Directory name', 'directorist-wpml-integration' ),
            'target' => [
                'type' => 'term',
                'key'  => 'name',
            ],
        ];

        if ( '' !== trim( (string) $term->description ) ) {
            $strings['term__description'] = [
                'value'  => $term->description,
                'title'  => __( 'Directory description', 'directorist-wpml-integration' ),
                'target' => [
                    'type' => 'term',
                    'key'  => 'description',
                ],
            ];
        }

        foreach ( $this->translatable_meta_keys as $meta_key ) {
            $meta_value = get_term_meta( $term->term_id, $meta_key, true );

            if ( is_array( $meta_value ) ) {
                $this->extract_array_strings( $strings, $meta_key, $meta_value );
                continue;
            }

            if ( $this->is_translatable_string( $meta_value ) ) {
                $string_name = $this->get_string_name( [ 'meta', $meta_key ] );
                $strings[ $string_name ] = [
                    'value'  => $meta_value,
                    'title'  => $this->get_string_title( $meta_key ),
                    'target' => [
                        'type' => 'meta',
                        'key'  => $meta_key,
                    ],
                ];
            }
        }

        return $strings;
    }

    /**
     * Extract translatable strings from nested builder arrays.
     *
     * @param array  $strings Destination strings.
     * @param string $meta_key Meta key.
     * @param array  $data Current array data.
     * @param array  $path Current path.
     *
     * @return void
     */
    private function extract_array_strings( &$strings, $meta_key, array $data, array $path = [] ) {
        foreach ( $data as $key => $value ) {
            $current_path = array_merge( $path, [ (string) $key ] );

            if ( is_array( $value ) ) {
                $this->extract_array_strings( $strings, $meta_key, $value, $current_path );
                continue;
            }

            if ( ! $this->should_translate_leaf_key( $key ) || ! $this->is_translatable_string( $value ) ) {
                continue;
            }

            $string_name = $this->get_string_name( array_merge( [ 'meta', $meta_key ], $current_path ) );

            $strings[ $string_name ] = [
                'value'  => $value,
                'title'  => $this->get_string_title( implode( ' / ', array_merge( [ $meta_key ], $current_path ) ) ),
                'target' => [
                    'type' => 'meta_path',
                    'key'  => $meta_key,
                    'path' => $current_path,
                ],
            ];
        }
    }

    /**
     * Apply one translated value to a translated term.
     *
     * @param int    $translated_term_id Translated term ID.
     * @param int    $source_term_id Source term ID.
     * @param array  $target Target metadata.
     * @param string $translated_value Translated value.
     *
     * @return void
     */
    private function apply_translated_value( $translated_term_id, $source_term_id, array $target, $translated_value ) {
        if ( ! is_string( $translated_value ) ) {
            return;
        }

        if ( 'term' === $target['type'] ) {
            $args = [
                $target['key'] => $translated_value,
            ];

            if ( 'name' === $target['key'] ) {
                $term         = get_term( $translated_term_id, ATBDP_DIRECTORY_TYPE );
                $args['slug'] = wp_unique_term_slug( sanitize_title( $translated_value ), $term );
            }

            $updated = wp_update_term( $translated_term_id, ATBDP_DIRECTORY_TYPE, $args );

            if ( is_wp_error( $updated ) && 'name' === $target['key'] ) {
                $args['slug'] = sanitize_title( $translated_value . '-' . $translated_term_id );
                wp_update_term( $translated_term_id, ATBDP_DIRECTORY_TYPE, $args );
            }

            return;
        }

        if ( 'meta' === $target['type'] ) {
            update_term_meta( $translated_term_id, $target['key'], $translated_value );
            return;
        }

        if ( 'meta_path' !== $target['type'] || empty( $target['path'] ) ) {
            return;
        }

        $meta_value = get_term_meta( $translated_term_id, $target['key'], true );

        if ( ! is_array( $meta_value ) ) {
            $meta_value = get_term_meta( $source_term_id, $target['key'], true );
        }

        if ( ! is_array( $meta_value ) ) {
            return;
        }

        $this->set_array_path_value( $meta_value, $target['path'], $translated_value );
        update_term_meta( $translated_term_id, $target['key'], $meta_value );
    }

    /**
     * Set a nested array value by path.
     *
     * @param array  $data Array data.
     * @param array  $path Path.
     * @param string $value Value.
     *
     * @return void
     */
    private function set_array_path_value( array &$data, array $path, $value ) {
        $key = array_shift( $path );

        if ( empty( $path ) ) {
            $data[ $key ] = $value;
            return;
        }

        if ( ! isset( $data[ $key ] ) || ! is_array( $data[ $key ] ) ) {
            $data[ $key ] = [];
        }

        $this->set_array_path_value( $data[ $key ], $path, $value );
    }

    /**
     * Get the source-language directory type ID for any translated directory type.
     *
     * @param int $directory_type_id Directory type term ID.
     *
     * @return int
     */
    private function get_original_directory_type_id( $directory_type_id ) {
        $wpml_element_type = apply_filters( 'wpml_element_type', ATBDP_DIRECTORY_TYPE );
        $language_info     = apply_filters(
            'wpml_element_language_details',
            null,
            [
                'element_id'   => $directory_type_id,
                'element_type' => $wpml_element_type,
            ]
        );

        if ( empty( $language_info->source_language_code ) ) {
            return (int) $directory_type_id;
        }

        $translations = WPML_Helper::get_element_translations( $directory_type_id, ATBDP_DIRECTORY_TYPE );

        foreach ( $translations as $translation ) {
            if ( empty( $translation->source_language_code ) && ! empty( $translation->term_id ) ) {
                return (int) $translation->term_id;
            }
        }

        return (int) $directory_type_id;
    }

    /**
     * Get source language for a directory type.
     *
     * @param int $directory_type_id Directory type term ID.
     *
     * @return string
     */
    private function get_directory_type_language( $directory_type_id ) {
        $wpml_element_type = apply_filters( 'wpml_element_type', ATBDP_DIRECTORY_TYPE );
        $language_info     = apply_filters(
            'wpml_element_language_details',
            null,
            [
                'element_id'   => $directory_type_id,
                'element_type' => $wpml_element_type,
            ]
        );

        if ( ! empty( $language_info->language_code ) ) {
            return $language_info->language_code;
        }

        return apply_filters( 'wpml_default_language', null );
    }

    /**
     * Keep string names as keys while extracting target maps.
     *
     * @param array $strings Registered strings.
     *
     * @return array
     */
    private function pluck_string_targets( array $strings ) {
        $targets = [];

        foreach ( $strings as $string_name => $string ) {
            if ( ! empty( $string['target'] ) ) {
                $targets[ $string_name ] = $string['target'];
            }
        }

        return $targets;
    }

    /**
     * Get term ID from package name.
     *
     * @param \WPML_Package $package WPML package.
     *
     * @return int
     */
    private function get_term_id_from_package( $package ) {
        if ( 0 !== strpos( $package->name, self::PACKAGE_NAME ) ) {
            return 0;
        }

        return absint( str_replace( self::PACKAGE_NAME, '', $package->name ) );
    }

    /**
     * Check if a leaf key is user-facing text.
     *
     * @param string|int $key Leaf key.
     *
     * @return bool
     */
    private function should_translate_leaf_key( $key ) {
        return in_array( (string) $key, $this->translatable_leaf_keys, true );
    }

    /**
     * Check whether a value is a meaningful translatable string.
     *
     * @param mixed $value Value.
     *
     * @return bool
     */
    private function is_translatable_string( $value ) {
        if ( ! is_string( $value ) ) {
            return false;
        }

        $value = trim( $value );

        if ( '' === $value ) {
            return false;
        }

        return ! is_numeric( $value );
    }

    /**
     * Build a WPML-safe string name from a path.
     *
     * @param array $parts Path parts.
     *
     * @return string
     */
    private function get_string_name( array $parts ) {
        return implode( '__', array_map( 'sanitize_key', $parts ) );
    }

    /**
     * Build a readable title for ATE.
     *
     * @param string $title Title.
     *
     * @return string
     */
    private function get_string_title( $title ) {
        return ucwords( str_replace( [ '_', '-' ], ' ', $title ) );
    }
}
