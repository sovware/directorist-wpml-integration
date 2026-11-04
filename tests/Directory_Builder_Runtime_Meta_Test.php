<?php

namespace {
	define( 'ATBDP_POST_TYPE', 'at_biz_dir' );
	define( 'ATBDP_DIRECTORY_TYPE', 'atbdp_listing_types' );
	define( 'ICL_SITEPRESS_VERSION', 'test' );

	$GLOBALS['listing_meta'] = array( 127 => 115, 184 => 144 );
	$GLOBALS['listing_terms'] = array( 127 => array( 115 ), 184 => array( 144 ) );
	$GLOBALS['listing_ui_meta'] = array();

	function add_action() {
		return true;
	}

	function add_filter() {
		return true;
	}

	function has_action() {
		return true;
	}

	function has_filter() {
		return true;
	}

	function apply_filters( $hook, $value ) {
		if ( 'wpml_default_language' === $hook ) {
			return 'en';
		}

		if ( 'wpml_current_language' === $hook ) {
			return 'nl';
		}

		// Simulate the runtime package reader returning no separate package
		// translation. Completed target metadata must remain authoritative.
		return $value;
	}

	function get_post_type( $post_id ) {
		return isset( $GLOBALS['listing_meta'][ $post_id ] ) ? ATBDP_POST_TYPE : '';
	}

	function get_post_meta( $post_id, $key, $single = false ) {
		if ( '_directorist_wpml_page_ui_strings' === $key ) {
			return isset( $GLOBALS['listing_ui_meta'][ $post_id ] ) ? $GLOBALS['listing_ui_meta'][ $post_id ] : '';
		}

		return isset( $GLOBALS['listing_meta'][ $post_id ] ) ? $GLOBALS['listing_meta'][ $post_id ] : '';
	}

	function update_post_meta( $post_id, $key, $value ) {
		$GLOBALS['listing_meta'][ $post_id ] = (int) $value;
		return true;
	}

	function wp_get_object_terms( $post_id ) {
		return isset( $GLOBALS['listing_terms'][ $post_id ] ) ? $GLOBALS['listing_terms'][ $post_id ] : array();
	}

	function wp_set_object_terms( $post_id, $terms ) {
		$GLOBALS['listing_terms'][ $post_id ] = array_map( 'intval', $terms );
		return $GLOBALS['listing_terms'][ $post_id ];
	}

	function term_exists( $term_id ) {
		return in_array( (int) $term_id, array( 115, 144 ), true ) ? (int) $term_id : 0;
	}

	function get_term( $term_id ) {
		return (object) array(
			'term_id'  => (int) $term_id,
			'name'     => 115 === (int) $term_id ? 'General' : 'Algemeen',
			'taxonomy' => ATBDP_DIRECTORY_TYPE,
		);
	}

	function is_wp_error() {
		return false;
	}

	function absint( $value ) {
		return abs( (int) $value );
	}

	function update_term_meta( $term_id, $meta_key, $value ) {
		$GLOBALS['wpdb']->meta[ (int) $term_id ][ $meta_key ] = $value;
		return true;
	}

	function clean_term_cache() {
		return true;
	}

	function maybe_unserialize( $value ) {
		$unserialized = @unserialize( $value );

		return false === $unserialized && 'b:0;' !== $value ? $value : $unserialized;
	}

	function sanitize_title( $value ) {
		return trim( preg_replace( '/[^a-z0-9_]+/', '-', strtolower( (string) $value ) ), '-' );
	}

	function sanitize_key( $value ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
	}

	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}

	function get_bloginfo() {
		return 'UTF-8';
	}

	function admin_url( $path = '' ) {
		return 'https://example.test/wp-admin/' . ltrim( $path, '/' );
	}

	function home_url( $path = '' ) {
		return 'https://example.test/' . ltrim( $path, '/' );
	}

	class Runtime_Meta_Test_Database {
		public $termmeta = 'wp_termmeta';
		public $prefix   = 'wp_';
		public $meta     = array();

		public function prepare( $query, ...$args ) {
			return $args;
		}

		public function get_var( $args ) {
			$term_id  = isset( $args[0] ) ? (int) $args[0] : 0;
			$meta_key = isset( $args[1] ) ? (string) $args[1] : '';

			return isset( $this->meta[ $term_id ][ $meta_key ] ) ? serialize( $this->meta[ $term_id ][ $meta_key ] ) : null;
		}

		public function get_col( $args ) {
			$value = $this->get_var( $args );

			return null === $value ? array() : array( $value );
		}
	}
}

namespace Directorist_WPML_Integration\Helper {
	class WPML_Helper {
		public static function get_element_translations( $element_id, $element_type ) {
			if ( ATBDP_DIRECTORY_TYPE === $element_type ) {
				return array(
					'en' => (object) array( 'term_id' => 115, 'element_id' => 115, 'original' => true ),
					'nl' => (object) array( 'term_id' => 144, 'element_id' => 144 ),
				);
			}

			return array(
				'en' => (object) array( 'element_id' => 127, 'original' => true ),
				'nl' => (object) array( 'element_id' => 184 ),
			);
		}

		public static function get_element_trid() {
			return 616;
		}

		public static function get_language_info( $element_id, $element_type ) {
			$language = in_array( (int) $element_id, array( 144, 184 ), true ) ? 'nl' : 'en';

			return (object) array(
				'language_code'        => $language,
				'source_language_code' => 'en' === $language ? null : 'en',
			);
		}
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/app/Controller/Hook/Directory_Builder_String_Package.php';
	require_once dirname( __DIR__ ) . '/app/Controller/Hook/Listings_Actions.php';

	use Directorist_WPML_Integration\Controller\Hook\Directory_Builder_String_Package;
	use Directorist_WPML_Integration\Controller\Hook\Listings_Actions;

	function assert_runtime_same( $expected, $actual, $message ) {
		if ( $expected === $actual ) {
			return;
		}

		fwrite( STDERR, $message . PHP_EOL );
		fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
		fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
		exit( 1 );
	}

	$source_header = array(
		array(
			'placeholderKey' => 'quick-widgets-placeholder',
			'selectedWidgets' => array(
				array(
					'widget_name' => 'bookmark',
					'widget_key'  => 'bookmark',
					'label'       => 'Bookmark',
					'icon'        => 'la la-heart-o',
					'options'     => array(
						'title'  => 'Bookmark Settings',
						'fields' => array(
							'label' => array( 'label' => 'Label', 'value' => 'Bookmark' ),
							'icon'  => array( 'label' => 'Icon', 'value' => 'la la-heart-o' ),
						),
					),
				),
				array(
					'widget_name' => 'title',
					'widget_key'  => 'title',
					'label'       => 'Listing Title',
				),
			),
		),
	);

	$target_header = $source_header;
	$target_header[0]['selectedWidgets'][0]['label'] = 'Old target label';
	$target_header[0]['selectedWidgets'][0]['options']['fields']['label']['value'] = 'Old target value';
	$target_header[0]['selectedWidgets'][0]['icon'] = 'wrong-target-icon';
	$target_header[0]['selectedWidgets'][1]['label'] = 'Interne vermeldingstitel';

	$GLOBALS['wpdb'] = new Runtime_Meta_Test_Database();
	$GLOBALS['wpdb']->meta = array(
		115 => array(
			'single_listing_header'    => $source_header,
			'single_listings_contents' => array(),
			'submission_form_fields'   => array(),
		),
		144 => array(
			'single_listing_header'    => $target_header,
			'single_listings_contents' => array(),
			'submission_form_fields'   => array(),
		),
	);

	$package = new Directory_Builder_String_Package();
	$source_strings = $package->get_translatable_meta_string_map( 'single_listing_header', $source_header );
	assert_runtime_same( array( 'Bookmark', 'Bookmark' ), array_values( $source_strings ), 'Only the visitor-facing bookmark strings should enter the listing ATE payload.' );
	$GLOBALS['listing_ui_meta'][127] = $source_strings;

	$fields = array();
	foreach ( $source_strings as $key => $source_value ) {
		$field_type = 'field-_directorist_wpml_page_ui_strings-0-' . $key;
		$fields[ $field_type ] = array(
			'field_type' => $field_type,
			'data'       => 'Bladwijzer',
		);
	}

	$listings = new Listings_Actions();
	$job      = (object) array( 'original_doc_id' => 127, 'language_code' => 'nl' );
	$listings->update_directory_type_after_listing_translation( 184, $fields, $job );

	$persisted = $GLOBALS['wpdb']->meta[144]['single_listing_header'];
	assert_runtime_same( 'Bladwijzer', $persisted[0]['selectedWidgets'][0]['label'], 'The completion handler must persist the target bookmark label.' );
	assert_runtime_same( 'Listing Title', $persisted[0]['selectedWidgets'][1]['label'], 'The completion handler must discard translated internal widget captions.' );

	$filtered = $package->translate_builder_term_meta( null, 144, 'single_listing_header', true );
	assert_runtime_same( 'Bladwijzer', $filtered[0][0]['selectedWidgets'][0]['label'], 'The normal runtime filter must preserve the completed target widget label.' );
	assert_runtime_same( 'Bladwijzer', $filtered[0][0]['selectedWidgets'][0]['options']['fields']['label']['value'], 'The normal runtime filter must preserve the completed target option value.' );
	assert_runtime_same( 'la la-heart-o', $filtered[0][0]['selectedWidgets'][0]['icon'], 'The normal runtime filter must keep non-translatable configuration aligned with the source layout.' );
	assert_runtime_same( 'Listing Title', $filtered[0][0]['selectedWidgets'][1]['label'], 'The normal runtime filter must not preserve translated internal widget captions.' );

	echo "Directory Builder completion-to-runtime metadata test passed.\n";
}
