<?php
/**
 * 301 redirects for retired pages whose content now lives on the new templates.
 *
 * @package jrc-site
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'template_redirect', function () {
	$map = array(
		'wizard'                  => '/quote/',
		'short-and-long-term-let' => '/service/holiday-let-cleaning/',
		'coverage'                => '/areas/#coverage',
		'products'                => '/',
		'service/carpet-cleaning' => '/service/add-on-services/#carpet',
		'service/window-cleaning' => '/service/add-on-services/#window',
		'service/oven-cleaning'   => '/service/add-on-services/#oven',

		// The services used to be WooCommerce products at /product/{slug}/ on the
		// old site. They are now a `service` CPT, so every old URL 404s and any
		// ranking or backlink pointing at them is lost without these.
		// /product/ itself is still WooCommerce's base for the physical products
		// (soap, air fresheners), so only these four specific slugs are mapped.
		'product/end-of-tenancy-clean'                  => '/service/end-of-tenancy-clean/',
		'product/communal-area-and-flat-block-cleaning'  => '/service/communal-area-and-flat-block-cleaning/',
		'product/office-and-commercial-cleaning'         => '/service/office-and-commercial-cleaning/',
		'product/holiday-let-cleaning'                   => '/service/holiday-let-cleaning/',

		// The three specialist services were also separate products on the old site
		// and are now sections of the add-on services page. The `service/…` variants
		// are mapped above; these are the live `/product/…` ones.
		'product/carpet-cleaning' => '/service/add-on-services/#carpet',
		'product/oven-cleaning'   => '/service/add-on-services/#oven',
		'product/window-cleaning' => '/service/add-on-services/#window',

		// The old site's single blog post, now migrated. Its auto-generated slug was
		// "462-2"; point that at the real article rather than the oven section.
		// WordPress also stores _wp_old_slug for it, but an explicit rule is clearer
		// and survives a slug change on the post.
		'462-2' => '/behind-the-shine-professional-oven-restoration/',
	);

	// The old site had a `product_area` taxonomy giving a live page per town
	// (/area/evesham/, /area/cheltenham/ and so on). That taxonomy does not exist
	// on this build, so every one of those URLs would 404 at launch. Point them at
	// the areas page until, or unless, dedicated town pages are built.
	$legacy_areas = array(
		'cheltenham', 'cotswolds', 'evesham', 'kidderminster', 'malvern',
		'redditch', 'stratford', 'warwick', 'worcester',
	);
	foreach ( $legacy_areas as $town ) {
		$map[ 'area/' . $town ] = '/areas/';
	}
	$req  = wp_unslash( $_SERVER['REQUEST_URI'] ?? '' );
	$path = strtolower( trim( (string) wp_parse_url( $req, PHP_URL_PATH ), '/' ) );

	if ( ! isset( $map[ $path ] ) ) {
		return;
	}

	$target = $map[ $path ];
	$query  = wp_parse_url( $req, PHP_URL_QUERY );

	// Carry the query string across so utm_*/gclid attribution survives the hop.
	// Some targets already end in a #fragment, which must stay last.
	if ( $query ) {
		$fragment = '';
		$hash     = strpos( $target, '#' );
		if ( false !== $hash ) {
			$fragment = substr( $target, $hash );
			$target   = substr( $target, 0, $hash );
		}
		$target .= ( false === strpos( $target, '?' ) ? '?' : '&' ) . $query . $fragment;
	}

	wp_safe_redirect( home_url( $target ), 301 );
	exit;
}, 1 );

/**
 * Stop WordPress guessing a destination for URLs it cannot find.
 *
 * `redirect_guess_404_permalink()` fuzzy-matches an unknown path against post slugs
 * and 301s to whatever looks closest. On this site it was sending /area/evesham/ to
 * an *image file* (uploads/2025/09/evesham-1.jpg) and /area/worcester/ to a single
 * case study — permanent redirects to the wrong content, which is worse than a 404
 * because search engines cache them and users land somewhere baffling.
 *
 * Explicit redirects above handle the URLs that should move. Anything else should
 * honestly 404 and let the 404 template do its job.
 */
add_filter( 'do_redirect_guess_404_permalink', '__return_false' );

/**
 * Catch-all for any other legacy /product/{slug}/ URL whose slug now belongs to a
 * service. Covers old links the explicit map above doesn't list (renamed slugs,
 * campaign URLs, anything still linked externally).
 *
 * Guarded by is_404() so it costs nothing on normal requests and can never
 * shadow a real WooCommerce product — if the product resolves, we never get here.
 */
add_action( 'template_redirect', function () {
	if ( ! is_404() ) {
		return;
	}

	$path = strtolower( trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' ) );
	if ( 0 !== strpos( $path, 'product/' ) ) {
		return;
	}

	$slug = sanitize_title( basename( $path ) );
	if ( '' === $slug ) {
		return;
	}

	$service = get_posts( array(
		'post_type'        => 'service',
		'name'             => $slug,
		'post_status'      => 'publish',
		'numberposts'      => 1,
		'fields'           => 'ids',
		'suppress_filters' => false,
	) );

	if ( $service ) {
		wp_safe_redirect( get_permalink( $service[0] ), 301 );
		exit;
	}
}, 2 );
