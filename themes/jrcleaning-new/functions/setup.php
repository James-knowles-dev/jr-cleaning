<?php
/**
 * Theme setup: supports, menus, sidebars, head cleanup.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

function jrc_setup() {
	/*
	 * Register the text domain declared in style.css.
	 *
	 * Without this the ~50 __() / _e() calls in functions/ were dead code: they returned
	 * their input unchanged, because nothing had ever told WordPress where to find a
	 * translation for the `jrcleaning` domain. Just-in-time loading does not cover it —
	 * that only searches wp-content/languages/themes/, never a theme's own folder.
	 *
	 * WHAT IS AND IS NOT TRANSLATED, deliberately:
	 *
	 *   Wrapped:     the partner-programme layer (the jrc-partners plugin, the CPT labels,
	 *                validation and error messages). That code is generic — a partner
	 *                platform, not cleaning-specific — and is the part worth reusing or
	 *                white-labelling, so its strings stay translatable.
	 *
	 *   Not wrapped: marketing copy in the page templates and components ("Contact us",
	 *                "Ready for a spotless space?"). It is client-specific copy for one
	 *                UK business, editable in ACF where it matters, and wrapping it would
	 *                hand a translator several hundred strings nobody will ever translate.
	 *
	 * That boundary is a decision, not an oversight. If the site ever genuinely needs a
	 * second language, wrap the templates then and regenerate the .pot.
	 */
	load_theme_textdomain( 'jrcleaning', JRC_DIR . '/languages' );

	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails', array( 'post', 'page', 'case_studies' ) );
	add_theme_support( 'html5', array( 'search-form', 'gallery', 'caption', 'style', 'script' ) );

	// WooCommerce
	add_theme_support( 'woocommerce' );

	register_nav_menus( array(
		'primary'       => __( 'Primary Menu', 'jrcleaning' ),
		'footer_menu_1' => __( 'Footer, Company', 'jrcleaning' ),
		'footer_menu_2' => __( 'Footer, Services', 'jrcleaning' ),
	) );
}
add_action( 'after_setup_theme', 'jrc_setup' );

// Add excerpt to pages.
add_action( 'init', function () {
	add_post_type_support( 'page', 'excerpt' );
} );

// Allow SVG uploads, but only for users who can manage the site (admins).
// SVGs can carry scripts, so we don't expose this to lower-privilege editors/authors.
add_filter( 'upload_mimes', function ( $mimes ) {
	if ( current_user_can( 'manage_options' ) ) {
		$mimes['svg']  = 'image/svg+xml';
		$mimes['svgz'] = 'image/svg+xml';
	}
	return $mimes;
} );

// Baseline SVG sanitisation on upload: strip <script>, on* handlers and javascript: URIs.
// NB: this is a safety net, not a full sanitiser — for untrusted input install a dedicated
// library (e.g. enshrined/svg-sanitize or the Safe SVG plugin).
add_filter( 'wp_handle_upload_prefilter', function ( $file ) {
	if ( ! empty( $file['type'] ) && 'image/svg+xml' === $file['type'] && ! empty( $file['tmp_name'] ) ) {
		$svg = file_get_contents( $file['tmp_name'] );
		if ( false !== $svg ) {
			$clean = preg_replace( '#<script[\s\S]*?</script>#i', '', $svg );
			$clean = preg_replace( '/\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $clean );
			$clean = preg_replace( '/(href|xlink:href)\s*=\s*("|\')\s*javascript:[^"\']*("|\')/i', '', $clean );
			if ( null !== $clean && $clean !== $svg ) {
				file_put_contents( $file['tmp_name'], $clean );
			}
		}
	}
	return $file;
} );

// Remove WooCommerce default breadcrumb (we render our own inner-hero breadcrumb).
add_action( 'init', function () {
	remove_action( 'woocommerce_before_main_content', 'woocommerce_breadcrumb', 20 );
} );

// Tidy <head>.
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_generator' );
remove_action( 'wp_head', 'wlwmanifest_link' );
remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 );

// Body classes for state hooks.
add_filter( 'body_class', function ( $classes ) {
	if ( is_front_page() ) {
		$classes[] = 'is-home';
	}
	return $classes;
} );

/**
 * UK English locale on the front end (en-GB) so the lang attribute and OG locale
 * are correct for a UK business. Admin stays on the installed locale.
 */
add_filter( 'locale', function ( $locale ) {
	return is_admin() ? $locale : 'en_GB';
} );
