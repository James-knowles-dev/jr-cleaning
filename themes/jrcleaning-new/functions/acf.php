<?php
/**
 * ACF: options page + json sync path.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Theme Settings options page.
add_action( 'acf/init', function () {
	if ( function_exists( 'acf_add_options_page' ) ) {
		acf_add_options_page( array(
			'page_title' => __( 'Theme Settings', 'jrcleaning' ),
			'menu_title' => __( 'Theme Settings', 'jrcleaning' ),
			'menu_slug'  => 'theme-settings',
			// Admins only. Theme Settings holds the Bark widget field, which is output
			// as raw HTML in the footer — `edit_posts` would expose that to every
			// Contributor, Author and Editor as a site-wide script injection.
			'capability' => 'manage_options',
			'redirect'   => false,
		) );
	}
} );

// Save ACF JSON inside the theme.
add_filter( 'acf/settings/save_json', function ( $path ) {
	return JRC_DIR . '/acf-json';
} );

add_filter( 'acf/settings/load_json', function ( $paths ) {
	$paths[] = JRC_DIR . '/acf-json';
	return $paths;
} );
