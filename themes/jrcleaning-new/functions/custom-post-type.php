<?php
/**
 * Custom post types.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function jrc_case_studies_cpt() {
	$labels = array(
		'name'               => __( 'Case Studies', 'jrcleaning' ),
		'singular_name'      => __( 'Case Study', 'jrcleaning' ),
		'menu_name'          => __( 'Case Studies', 'jrcleaning' ),
		'add_new_item'       => __( 'Add New Case Study', 'jrcleaning' ),
		'edit_item'          => __( 'Edit Case Study', 'jrcleaning' ),
		'all_items'          => __( 'All Case Studies', 'jrcleaning' ),
		'not_found'          => __( 'No case studies found.', 'jrcleaning' ),
	);

	register_post_type( 'case_studies', array(
		'labels'       => $labels,
		'public'       => true,
		'has_archive'  => true,
		'rewrite'      => array( 'slug' => 'case-studies' ),
		'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-portfolio',
	) );
}
add_action( 'init', 'jrc_case_studies_cpt' );

/**
 * Services CPT, each cleaning service is its own entry.
 * Archive: /services/   Singles: /service/{slug}
 */
function jrc_services_cpt() {
	$labels = array(
		'name'               => __( 'Services', 'jrcleaning' ),
		'singular_name'      => __( 'Service', 'jrcleaning' ),
		'menu_name'          => __( 'Services', 'jrcleaning' ),
		'add_new_item'       => __( 'Add New Service', 'jrcleaning' ),
		'edit_item'          => __( 'Edit Service', 'jrcleaning' ),
		'all_items'          => __( 'All Services', 'jrcleaning' ),
		'not_found'          => __( 'No services found.', 'jrcleaning' ),
	);

	register_post_type( 'service', array(
		'labels'       => $labels,
		'public'       => true,
		'has_archive'  => 'services',
		'rewrite'      => array( 'slug' => 'service', 'with_front' => false ),
		'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt', 'page-attributes' ),
		'show_in_rest' => true,
		'menu_icon'    => 'dashicons-sparkles',
		'menu_position'=> 5,
	) );
}
add_action( 'init', 'jrc_services_cpt' );

/**
 * Order the Services archive by menu_order (Add-on Services sits last).
 */
function jrc_services_archive_order( $query ) {
	if ( is_admin() || ! $query->is_main_query() ) { return; }
	if ( $query->is_post_type_archive( 'service' ) ) {
		$query->set( 'orderby', array( 'menu_order' => 'ASC', 'title' => 'ASC' ) );
	}
}
add_action( 'pre_get_posts', 'jrc_services_archive_order' );

/**
 * Keep the plumbing out of search results.
 *
 * Both CPTs are `public`, so services and case studies are searchable already — that
 * part needed no help. What did need help is everything WooCommerce adds: My Account,
 * Cart, Checkout and the partner endpoints hanging off them are ordinary pages as far
 * as WP_Query is concerned, so a search for "account" returned the login screen above
 * the services someone was actually looking for.
 *
 * Excluded by ID rather than by slug: the slugs are editable in WooCommerce settings,
 * and a rename would silently switch this off.
 */
function jrc_tune_search( $query ) {
	if ( is_admin() || ! $query->is_main_query() || ! $query->is_search() ) {
		return;
	}

	$hide = array();

	foreach ( array( 'myaccount', 'cart', 'checkout' ) as $page ) {
		$id = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( $page ) : 0;
		if ( $id > 0 ) {
			$hide[] = $id;
		}
	}

	/**
	 * Filter the pages hidden from search.
	 *
	 * @param int[] $hide Post IDs.
	 */
	$hide = array_filter( (array) apply_filters( 'jrc_search_exclude', $hide ) );

	if ( $hide ) {
		$query->set( 'post__not_in', array_merge( (array) $query->get( 'post__not_in' ), $hide ) );
	}
}
add_action( 'pre_get_posts', 'jrc_tune_search' );

/**
 * Friendlier browser/SEO title for the Services archive ("Services Archive" -> "Our Cleaning Services").
 */
add_filter( 'wpseo_title', function ( $t ) {
	return is_post_type_archive( 'service' ) ? 'Our Cleaning Services - ' . get_bloginfo( 'name' ) : $t;
} );
add_filter( 'document_title_parts', function ( $parts ) {
	if ( is_post_type_archive( 'service' ) ) { $parts['title'] = 'Our Cleaning Services'; }
	return $parts;
} );
