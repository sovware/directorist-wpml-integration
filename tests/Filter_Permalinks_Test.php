<?php

function add_filter() {
}

function has_filter() {
	global $test_has_wpml_filter;

	return $test_has_wpml_filter;
}

function is_admin() {
	global $test_is_admin;

	return $test_is_admin;
}

function get_directorist_option( $name ) {
	if ( 'add_listing_page' === $name ) {
		return 1225;
	}

	if ( 'search_result_page' === $name ) {
		return 1241;
	}

	return 0;
}

function apply_filters( $hook, $value, ...$args ) {
	if ( 'wpml_current_language' === $hook ) {
		global $test_current_language;

		return $test_current_language;
	}

	if ( 'wpml_default_language' === $hook ) {
		return 'es';
	}

	if ( 'wpml_object_id' === $hook && 1225 === (int) $value ) {
		return 1291;
	}

	if ( 'wpml_object_id' === $hook && 1241 === (int) $value ) {
		return 1282;
	}

	return $value;
}

function get_post_status( $post_id ) {
	return in_array( (int) $post_id, [ 1282, 1291 ], true ) ? 'publish' : false;
}

function get_permalink( $post_id ) {
	if ( 1291 === (int) $post_id ) {
		return 'https://example.com/en/add-a-listing/';
	}

	if ( 1282 === (int) $post_id ) {
		return 'https://example.com/en/directory-results/';
	}

	return false;
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function add_query_arg( $query_args, $url ) {
	$query = parse_url( $url, PHP_URL_QUERY );
	parse_str( (string) $query, $existing_args );
	$args = array_merge( $existing_args, $query_args );
	$base = strtok( $url, '?' );

	return $base . ( $args ? '?' . http_build_query( $args ) : '' );
}

require_once dirname( __DIR__ ) . '/app/Controller/Hook/Filter_Permalinks.php';

use Directorist_WPML_Integration\Controller\Hook\Filter_Permalinks;

function assert_same( $expected, $actual, $message ) {
	if ( $expected === $actual ) {
		return;
	}

	fwrite( STDERR, $message . PHP_EOL );
	fwrite( STDERR, 'Expected: ' . var_export( $expected, true ) . PHP_EOL );
	fwrite( STDERR, 'Actual:   ' . var_export( $actual, true ) . PHP_EOL );
	exit( 1 );
}

$test_has_wpml_filter  = true;
$test_is_admin         = false;
$test_current_language = 'en';
$permalinks           = new Filter_Permalinks();

assert_same(
	'https://example.com/en/add-a-listing/',
	$permalinks->filter_add_listing_page_url( 'https://example.com/en/' ),
	'The Add Listing URL must resolve to the translated page.'
);

assert_same(
	'https://example.com/en/add-a-listing/?plan=3&listing_type=general-directory-en',
	$permalinks->filter_add_listing_page_url( 'https://example.com/en/?plan=3&listing_type=general-directory-en' ),
	'Pricing plan and directory query arguments must survive translated Add Listing URL resolution.'
);

assert_same(
	'https://example.com/en/directory-results/',
	$permalinks->filter_divi_home_search_result_base_url( 'https://example.com/en/' ),
	'The translated Divi Homepage Search route must use the translated Directorist Search Result page.'
);

$test_current_language = 'es';

assert_same(
	'https://example.com/',
	$permalinks->filter_divi_home_search_result_base_url( 'https://example.com/' ),
	'The default-language Divi Homepage Search route must remain unchanged.'
);

$test_current_language = 'en';

$test_is_admin = true;

assert_same(
	'https://example.com/en/?plan_id=3',
	$permalinks->filter_add_listing_page_url( 'https://example.com/en/?plan_id=3' ),
	'Admin requests must not rewrite Add Listing URLs.'
);

echo "Filter_Permalinks tests passed.\n";
