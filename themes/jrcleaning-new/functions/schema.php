<?php
/**
 * Structured data (JSON-LD): LocalBusiness + per-service Service schema.
 * Complements Yoast (which handles WebPage/BreadcrumbList/Organization).
 *
 * @package jrcleaning
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** UK phone -> E.164 (07507 657223 -> +447507657223). */
function jrc_phone_e164() {
	$p = jrc_phone();
	$d = preg_replace( '/\D+/', '', $p );
	if ( 0 === strpos( $d, '0' ) ) { $d = '44' . substr( $d, 1 ); }
	return '+' . $d;
}

/**
 * Areas served as typed AdministrativeArea nodes rather than bare strings, which
 * is what Google's local parsers expect.
 *
 * @return array<int,array<string,string>>
 */
function jrc_area_served() {
	$areas = array( 'Worcestershire', 'Gloucestershire', 'Warwickshire' );
	return array_map(
		function ( $name ) {
			return array( '@type' => 'AdministrativeArea', 'name' => $name );
		},
		$areas
	);
}

/** The LocalBusiness node, built from Theme Settings + the Service CPT. */
function jrc_local_business_node() {
	$opt = function ( $name, $fallback = '' ) {
		$v = function_exists( 'get_field' ) ? get_field( $name, 'options' ) : '';
		return '' !== trim( (string) $v ) ? trim( (string) $v ) : $fallback;
	};

	$address = array(
		'@type'           => 'PostalAddress',
		'addressLocality' => $opt( 'contact_locality', 'Evesham' ),
		'addressRegion'   => $opt( 'contact_region', 'Worcestershire' ),
		'addressCountry'  => 'GB',
	);

	// Google needs streetAddress + postalCode for LocalBusiness rich results; both
	// are optional here so an incomplete address never emits empty properties.
	$street   = $opt( 'contact_street' );
	$postcode = $opt( 'contact_postcode' );
	if ( $street ) { $address['streetAddress'] = $street; }
	if ( $postcode ) { $address['postalCode'] = $postcode; }

	// HouseCleaningService is a subtype of LocalBusiness. Naming the specific type
	// tells Google what the business actually does rather than leaving it to infer.
	$node = array(
		'@type'      => array( 'HouseCleaningService', 'LocalBusiness' ),
		'@id'        => home_url( '/#business' ),
		'name'       => get_bloginfo( 'name' ),
		'url'        => home_url( '/' ),
		'telephone'  => jrc_phone_e164(),
		'priceRange' => '££',
		'address'    => $address,
		'areaServed' => jrc_area_served(),
	);

	// Aggregate rating, from Theme Settings. Only emitted when both a score and a
	// review count are set: Google treats a rating with no reviewCount as invalid
	// structured data, and inventing either is a manual-action risk.
	$rating_val   = $opt( 'reviews_rating' );
	$rating_count = $opt( 'reviews_count' );
	if ( is_numeric( $rating_val ) && (int) $rating_count > 0 ) {
		$node['aggregateRating'] = array(
			'@type'       => 'AggregateRating',
			'ratingValue' => (string) (float) $rating_val,
			'reviewCount' => (int) $rating_count,
			'bestRating'  => '5',
			'worstRating' => '1',
		);
	}

	$lat = $opt( 'contact_lat' );
	$lng = $opt( 'contact_lng' );
	if ( is_numeric( $lat ) && is_numeric( $lng ) ) {
		$node['geo'] = array(
			'@type'     => 'GeoCoordinates',
			'latitude'  => (float) $lat,
			'longitude' => (float) $lng,
		);
	}

	$email = function_exists( 'get_field' ) ? get_field( 'contact_email', 'options' ) : '';
	if ( $email ) { $node['email'] = $email; }

	$logo = function_exists( 'get_field' ) ? get_field( 'site_logo', 'options' ) : null;
	$node['image'] = ( $logo && ! empty( $logo['url'] ) ) ? $logo['url'] : ( JRC_URI . '/images/jr-logo-navy.png' );

	// Socials.
	$same = array();
	if ( function_exists( 'have_rows' ) && have_rows( 'socials', 'options' ) ) {
		while ( have_rows( 'socials', 'options' ) ) {
			the_row();
			$u = get_sub_field( 'url' );
			if ( $u ) { $same[] = $u; }
		}
	}
	if ( $same ) { $node['sameAs'] = $same; }

	// Services -> makesOffer.
	$offers = array();
	foreach ( get_posts( array( 'post_type' => 'service', 'numberposts' => -1, 'orderby' => 'menu_order', 'order' => 'ASC' ) ) as $s ) {
		$offers[] = array( '@type' => 'Offer', 'itemOffered' => array( '@type' => 'Service', 'name' => $s->post_title ) );
	}
	if ( $offers ) { $node['makesOffer'] = $offers; }

	// Office hours, from the same definition as the Contact page fallback
	// (jrc_default_office_hours()). NB: these are the hours the office is contactable,
	// not the hours we clean — holiday let changeovers run seven days a week, which the
	// holiday let page says explicitly.
	$node['openingHoursSpecification'] = jrc_default_office_hours()['schema'];

	return $node;
}

/**
 * FAQPage node built from the service page's own `faq` content blocks, so the
 * structured data can never drift out of sync with the visible accordion —
 * Google requires the answer text to be present on the page.
 *
 * NB: since August 2023 Google only shows FAQ rich results for well-known
 * government and health sites, so this is unlikely to win snippets on its own.
 * It is still worth emitting: it is valid structured data, it is used by other
 * search engines and by AI answer engines, and it costs nothing to keep current.
 *
 * @param int $post_id Service post to read blocks from.
 * @return array|null The FAQPage node, or null when the page has no questions.
 */
function jrc_faq_node( $post_id ) {
	if ( ! function_exists( 'have_rows' ) ) {
		return null;
	}

	$questions = array();

	if ( have_rows( 'service_blocks', $post_id ) ) {
		while ( have_rows( 'service_blocks', $post_id ) ) {
			the_row();
			if ( 'faq' !== get_row_layout() ) {
				continue;
			}

			$items = get_sub_field( 'items' );
			foreach ( (array) $items as $item ) {
				$q = trim( (string) ( $item['question'] ?? '' ) );
				$a = trim( wp_strip_all_tags( (string) ( $item['answer'] ?? '' ) ) );

				if ( '' === $q || '' === $a ) {
					continue;
				}

				$questions[] = array(
					'@type'          => 'Question',
					'name'           => $q,
					'acceptedAnswer' => array(
						'@type' => 'Answer',
						'text'  => $a,
					),
				);
			}
		}
	}

	if ( ! $questions ) {
		return null;
	}

	return array(
		'@type'      => 'FAQPage',
		'@id'        => get_permalink( $post_id ) . '#faq',
		'mainEntity' => $questions,
	);
}

add_action( 'wp_head', function () {
	$graph = array();

	if ( is_front_page() || ( function_exists( 'is_page' ) && is_page( 'contact' ) ) ) {
		$graph[] = jrc_local_business_node();
	}

	if ( is_singular( 'service' ) ) {
		$id   = get_the_ID();
		$desc = function_exists( 'get_field' ) ? get_field( 'intro', $id ) : '';
		if ( ! $desc ) { $desc = get_the_excerpt( $id ); }
		$graph[] = array(
			'@type'       => 'Service',
			'name'        => get_the_title( $id ),
			'serviceType' => get_the_title( $id ),
			'description' => wp_strip_all_tags( $desc ),
			'areaServed'  => jrc_area_served(),
			'url'         => get_permalink( $id ),
			'provider'    => array( '@type' => 'LocalBusiness', '@id' => home_url( '/#business' ), 'name' => get_bloginfo( 'name' ) ),
		);

		$faq = jrc_faq_node( $id );
		if ( $faq ) {
			$graph[] = $faq;
		}
	}

	if ( empty( $graph ) ) { return; }

	$data = array( '@context' => 'https://schema.org', '@graph' => $graph );
	echo "\n" . '<script type="application/ld+json">' . wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . '</script>' . "\n";
}, 20 );
