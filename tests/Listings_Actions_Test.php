<?php

namespace {
	define( 'ATBDP_POST_TYPE', 'at_biz_dir' );
	define( 'ATBDP_DIRECTORY_TYPE', 'atbdp_listing_types' );

	$GLOBALS['listing_meta'] = [
		127 => 115,
		154 => 115,
		184 => 130,
		185 => 130,
		198 => 0,
		200 => 0,
	];
	$GLOBALS['listing_terms'] = [
		127 => [ 115 ],
		154 => [ 115 ],
		184 => [ 130 ],
		185 => [ 130 ],
		198 => [],
		200 => [],
	];

	function get_post_type( $post_id ) {
		return isset( $GLOBALS['listing_meta'][ $post_id ] ) ? ATBDP_POST_TYPE : '';
	}

	function wp_is_post_autosave( $post_id ) {
		return false;
	}

	function wp_is_post_revision( $post_id ) {
		return false;
	}

	function get_post_meta( $post_id, $key, $single = false ) {
		if ( '_directorist_wpml_page_ui_strings' === $key ) {
			return isset( $GLOBALS['listing_ui_meta'][ $post_id ] ) ? $GLOBALS['listing_ui_meta'][ $post_id ] : '';
		}
		return isset( $GLOBALS['listing_meta'][ $post_id ] ) ? $GLOBALS['listing_meta'][ $post_id ] : '';
	}

	function update_post_meta( $post_id, $key, $value ) {
		if ( '_directorist_wpml_page_ui_strings' === $key ) {
			$GLOBALS['listing_ui_meta'][ $post_id ] = $value;
			++$GLOBALS['ui_meta_writes'];
			return true;
		}
		$GLOBALS['listing_meta'][ $post_id ] = (int) $value;
	}

	function wp_get_object_terms( $post_id, $taxonomy, $args = [] ) {
		return isset( $GLOBALS['listing_terms'][ $post_id ] ) ? $GLOBALS['listing_terms'][ $post_id ] : [];
	}

	function wp_set_object_terms( $post_id, $terms, $taxonomy, $append = false ) {
		$GLOBALS['listing_terms'][ $post_id ] = array_map( 'intval', $terms );
	}

	function is_wp_error( $value ) {
		return false;
	}

	function term_exists( $term_id, $taxonomy = '' ) {
		return in_array( (int) $term_id, [ 115, 130, 135 ], true ) ? (int) $term_id : 0;
	}

	function absint( $value ) {
		return abs( (int) $value );
	}

	function sanitize_key( $key ) {
		return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
	}

	function sanitize_title( $value ) {
		return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $value ) ), '-' );
	}

	function wp_strip_all_tags( $value ) {
		return strip_tags( (string) $value );
	}

	function get_bloginfo( $key ) {
		return 'UTF-8';
	}

	function add_action( $hook, $callback = null ) {
		$GLOBALS['registered_actions'][ $hook ] = $callback;
		return true;
	}

	class WP_Post {
		public $ID;
		public $post_type;

		public function __construct( $id, $post_type = ATBDP_POST_TYPE ) {
			$this->ID        = $id;
			$this->post_type = $post_type;
		}
	}

	function delete_post_meta( $post_id, $key ) {
		if ( ! isset( $GLOBALS['listing_ui_meta'][ $post_id ] ) ) {
			return false;
		}
		unset( $GLOBALS['listing_ui_meta'][ $post_id ] );
		++$GLOBALS['ui_meta_writes'];
		return true;
	}

	function maybe_unserialize( $value ) {
		return $value;
	}

	class Listing_UI_Test_Database {
		public $termmeta = 'termmeta';
		public $layouts  = array();

		public function prepare( $query, $term_id, $meta_key ) {
			return $meta_key;
		}

		public function get_var( $meta_key ) {
			return isset( $this->layouts[ $meta_key ] ) ? $this->layouts[ $meta_key ] : array();
		}
	}

	function add_filter() {
		return true;
	}
}

namespace Directorist_WPML_Integration\Helper {
	class WPML_Helper {
		public static function get_element_translations( $element_id, $element_type ) {
			if ( ATBDP_DIRECTORY_TYPE === $element_type && in_array( (int) $element_id, [ 115, 130, 135 ], true ) ) {
				return [
					'en' => (object) [ 'term_id' => 115, 'element_id' => 115, 'original' => true ],
					'fr' => (object) [ 'term_id' => 130, 'element_id' => 130 ],
					'de' => (object) [ 'term_id' => 135, 'element_id' => 135 ],
				];
			}

			$families = [
				127 => [ 'en' => 127, 'fr' => 184, 'de' => 200 ],
				154 => [ 'en' => 154, 'fr' => 185, 'de' => 198 ],
			];

			foreach ( $families as $translations ) {
				if ( in_array( (int) $element_id, $translations, true ) ) {
					$result = [];
					foreach ( $translations as $language => $post_id ) {
						$result[ $language ] = (object) [
							'element_id' => $post_id,
							'original'   => 'en' === $language,
						];
					}

					return $result;
				}
			}

			return [];
		}

		public static function get_language_info( $element_id, $element_type ) {
			$languages = [
				115 => 'en', 130 => 'fr', 135 => 'de',
				127 => 'en', 154 => 'en', 184 => 'fr', 185 => 'fr', 198 => 'de', 200 => 'de',
			];

			return isset( $languages[ $element_id ] ) ? (object) [ 'language_code' => $languages[ $element_id ], 'source_language_code' => 'en' === $languages[ $element_id ] ? null : 'en' ] : false;
		}
	}
}

namespace {
	require_once dirname( __DIR__ ) . '/app/Controller/Hook/Listings_Actions.php';

	use Directorist_WPML_Integration\Controller\Hook\Listings_Actions;

	function assert_listing_directory( $listing_id, $directory_id, $message ) {
		$meta  = isset( $GLOBALS['listing_meta'][ $listing_id ] ) ? $GLOBALS['listing_meta'][ $listing_id ] : 0;
		$terms = isset( $GLOBALS['listing_terms'][ $listing_id ] ) ? $GLOBALS['listing_terms'][ $listing_id ] : [];

		if ( $directory_id === $meta && [ $directory_id ] === $terms ) {
			return;
		}

		fwrite( STDERR, $message . PHP_EOL );
		exit( 1 );
	}

	function assert_same( $expected, $actual, $message ) {
		if ( $expected === $actual ) {
			return;
		}

		fwrite( STDERR, $message . PHP_EOL );
		fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
		fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
		exit( 1 );
	}

	$listings = new Listings_Actions();

	foreach ( [ [ 198, 154 ], [ 200, 127 ] ] as $fixture ) {
		$job = (object) [
			'original_doc_id' => $fixture[1],
			'language_code'   => 'de',
		];

		$listings->update_directory_type_after_listing_translation( $fixture[0], [], $job );
		$listings->update_directory_type_after_listing_translation( $fixture[0], [], $job );
	}

	assert_listing_directory( 127, 115, 'English listing 127 must remain mapped to directory 115.' );
	assert_listing_directory( 154, 115, 'English listing 154 must remain mapped to directory 115.' );
	assert_listing_directory( 184, 130, 'French listing 184 must remain mapped to directory 130.' );
	assert_listing_directory( 185, 130, 'French listing 185 must remain mapped to directory 130.' );
	assert_listing_directory( 198, 135, 'German listing 198 must map both meta and taxonomy relationship to directory 135.' );
	assert_listing_directory( 200, 135, 'German listing 200 must map both meta and taxonomy relationship to directory 135.' );

	// Existing listing jobs must discard builder strings before WPML reads post meta.
	$GLOBALS['listing_ui_meta'] = array(
		127 => array( 'builder_submission_form_fields__fields__textarea__placeholder' => 'Opening hours' ),
		184 => array( 'builder_submission_form_fields__fields__textarea__placeholder' => 'Öffnungszeiten' ),
	);
	$GLOBALS['ui_meta_writes'] = 0;
	$hook_listings             = new Listings_Actions();
	$callback                  = $GLOBALS['registered_actions']['wpml_pb_register_all_strings_for_translation'];

	$callback( new WP_Post( 127 ) );
	assert_same( false, isset( $GLOBALS['listing_ui_meta'][127] ), 'A new listing job must not include legacy Directory Builder placeholders.' );
	assert_same( 1, $GLOBALS['ui_meta_writes'], 'Legacy listing UI meta must be removed once.' );

	$callback( new WP_Post( 127 ) );
	$callback( new WP_Post( 127, 'page' ) );
	$callback( (object) array( 'ID' => 127, 'post_type' => ATBDP_POST_TYPE ) );
	assert_same( 1, $GLOBALS['ui_meta_writes'], 'Repeated jobs, pages and non-post packages must not rewrite listing meta.' );

	$callback( new WP_Post( 184 ) );
	assert_same( false, isset( $GLOBALS['listing_ui_meta'][184] ), 'Translated listings must also discard copied builder strings.' );
	assert_same( 2, $GLOBALS['ui_meta_writes'], 'Removing the translated listing payload must happen once.' );

	$GLOBALS['listing_ui_meta'][154] = array( 'builder_submission_form_fields__fields__textarea__placeholder' => 'Opening hours' );
	$hook_listings->clear_listing_ui_strings_on_save( 154, new WP_Post( 154 ), true );
	assert_same( false, isset( $GLOBALS['listing_ui_meta'][154] ), 'Saving a listing must clear its legacy builder payload.' );

	$hook_listings->clear_listing_ui_strings_on_save( 127, new WP_Post( 127, 'page' ), true );
	assert_same( 3, $GLOBALS['ui_meta_writes'], 'Saving other post types must not affect listing meta.' );

	echo "Listing directory translation relationship tests passed.\n";
}
