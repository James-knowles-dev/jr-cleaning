<?php
/**
 * Small, low-risk hardening that WordPress leaves off by default.
 *
 * Lives in the jrc-site must-use plugin rather than wp-config.php, which is
 * Cloudways-owned and excluded from deploys, and rather than the theme, which can be
 * switched (see ../jrc-site.php).
 *
 * @package jrc-site
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Stop the REST API listing users to the public.
 *
 * /wp-json/wp/v2/users publishes every author's login slug, which is half of what a
 * password-guessing attack needs — and every author on this site is an administrator.
 * Logged-in users who can list users (the block editor, admin screens) are unaffected.
 */
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( current_user_can( 'list_users' ) ) {
		return $endpoints;
	}
	foreach ( array( '/wp/v2/users', '/wp/v2/users/(?P<id>[\d]+)' ) as $route ) {
		unset( $endpoints[ $route ] );
	}
	return $endpoints;
} );

/**
 * No author archives.
 *
 * The site has no multi-author blog, so /author/<login>/ and ?author=N exist only as a
 * way to read login names off the URL. Both go to the home page instead.
 */
add_action( 'template_redirect', function () {
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only redirect.
	if ( is_author() || ( isset( $_GET['author'] ) && ! is_admin() ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 1 );

// Keep login names out of oEmbed responses too.
add_filter( 'oembed_response_data', function ( $data ) {
	unset( $data['author_name'], $data['author_url'] );
	return $data;
} );

/**
 * Baseline security headers on front-end responses.
 *
 * Deliberately no Content-Security-Policy: GTM, Gravity Forms and the analytics tags
 * load scripts from several origins, and a CSP that is not tuned against all of them
 * breaks the forms. HSTS without includeSubDomains or preload, so nothing else on the
 * domain is affected and it can be withdrawn by simply removing it.
 */
add_action( 'send_headers', function () {
	if ( headers_sent() ) {
		return;
	}
	header( 'X-Content-Type-Options: nosniff' );
	header( 'X-Frame-Options: SAMEORIGIN' );
	header( 'Referrer-Policy: strict-origin-when-cross-origin' );
	header( 'Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()' );
	if ( is_ssl() ) {
		header( 'Strict-Transport-Security: max-age=31536000' );
	}
} );
