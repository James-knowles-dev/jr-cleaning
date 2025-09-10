<?php

// Register Custom Post Type: Case Studies
function jrcase_studies_post_type() {
	$labels = array(
		'name'                  => _x('Case Studies', 'Post Type General Name', 'jrcleaning'),
		'singular_name'         => _x('Case Study', 'Post Type Singular Name', 'jrcleaning'),
		'menu_name'             => __('Case Studies', 'jrcleaning'),
		'name_admin_bar'        => __('Case Study', 'jrcleaning'),
		'add_new'               => __('Add New', 'jrcleaning'),
		'add_new_item'          => __('Add New Case Study', 'jrcleaning'),
		'new_item'              => __('New Case Study', 'jrcleaning'),
		'edit_item'             => __('Edit Case Study', 'jrcleaning'),
		'view_item'             => __('View Case Study', 'jrcleaning'),
		'all_items'             => __('All Case Studies', 'jrcleaning'),
		'search_items'          => __('Search Case Studies', 'jrcleaning'),
		'not_found'             => __('No case studies found.', 'jrcleaning'),
		'not_found_in_trash'    => __('No case studies found in Trash.', 'jrcleaning'),
	);

	$args = array(
		'labels'                => $labels,
		'public'                => true,
		'has_archive'           => true,
		'rewrite'               => array('slug' => 'case-studies'),
		'supports'              => array('title', 'editor', 'thumbnail', 'excerpt'),
		'show_in_rest'          => true,
		'menu_icon'             => 'dashicons-portfolio',
	);

	register_post_type('case_studies', $args);
}
add_action('init', 'jrcase_studies_post_type');
