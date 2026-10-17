<?php
/**
 * Google Tag Manager container.
 *
 * Normally GTM4WP (duracelltomi-google-tag-manager) injects the container itself, and it
 * still supplies the dataLayer this site relies on — page attributes and the WooCommerce
 * ecommerce events. What it stopped doing is loading the container: its container-ID
 * setting came back empty, so the plugin emitted the dataLayer and its ecommerce JS but
 * never the gtm.js request. GTM therefore saw no tag at all, while the page still looked
 * like it had analytics on it.
 *
 * This file hardcodes the container so it can't be lost to a settings row again. It is
 * deliberately defensive on two points:
 *
 *  1. If GTM4WP's container ID is ever filled back in, this bows out. Two containers with
 *     the same ID on one page means every conversion counted twice.
 *  2. It only fires on the live host. Staging runs identical code, and it is behind Basic
 *     Auth and noindex — tags firing there would push junk sessions into the same GA
 *     property we're trying to get clean numbers out of.
 *
 * @package jrc-site
 */

defined( 'ABSPATH' ) || exit;

/**
 * GTM4WP's "container code off" placement value (GTM4WP_PLACEMENT_OFF in the plugin).
 * Mirrored here so this file has no load-order dependency on the plugin.
 */
define( 'JRC_GTM4WP_PLACEMENT_OFF', 3 );

/**
 * The container ID. Filterable so it can be swapped without touching this file.
 */
function jrc_gtm_id() {
	return (string) apply_filters( 'jrc_gtm_id', 'GTM-MF6GJS4N' );
}

/**
 * Hosts allowed to fire the container. Add a host here (or via the filter) to enable
 * tracking somewhere new — staging is intentionally absent.
 */
function jrc_gtm_allowed_hosts() {
	return (array) apply_filters( 'jrc_gtm_allowed_hosts', array( 'jrcleaning.org', 'www.jrcleaning.org' ) );
}

/**
 * Whether to print the container on this request.
 */
function jrc_gtm_enabled() {
	if ( '' === jrc_gtm_id() ) {
		return false;
	}

	// Never in wp-admin, on the login screen, or in a REST/AJAX/cron context.
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return false;
	}

	// Don't double-load — but only stand down if GTM4WP is actually going to print the
	// container. Having an ID saved is not enough: this site had the ID filled in with
	// the placement set to OFF, so the plugin emitted the dataLayer and its ecommerce JS
	// while never requesting gtm.js. Checking the ID alone would leave the page with no
	// container at all, which is the bug this file exists to fix.
	$gtm4wp = get_option( 'gtm4wp-options' );
	if ( is_array( $gtm4wp ) && ! empty( $gtm4wp['gtm-code'] ) ) {
		$placement = isset( $gtm4wp['gtm-code-placement'] ) ? (int) $gtm4wp['gtm-code-placement'] : 0;
		if ( JRC_GTM4WP_PLACEMENT_OFF !== $placement ) {
			return false;
		}
	}

	$host = strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	if ( ! in_array( $host, array_map( 'strtolower', jrc_gtm_allowed_hosts() ), true ) ) {
		return false;
	}

	return (bool) apply_filters( 'jrc_gtm_enabled', true );
}

/**
 * Container snippet, as high in <head> as the theme can put it.
 *
 * Priority 1 on wp_head is the earliest hook available without editing header.php. GTM's
 * own guidance is "as high as possible"; what actually matters is that it precedes the
 * tags it needs to fire, which it does here.
 */
function jrc_gtm_head() {
	if ( ! jrc_gtm_enabled() ) {
		return;
	}
	$id = esc_js( jrc_gtm_id() );
	?>
<!-- Google Tag Manager -->
<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
})(window,document,'script','dataLayer','<?php echo $id; ?>');</script>
<!-- End Google Tag Manager -->
	<?php
}
add_action( 'wp_head', 'jrc_gtm_head', 1 );

/**
 * The <noscript> iframe, immediately after the opening <body> tag.
 */
function jrc_gtm_body() {
	if ( ! jrc_gtm_enabled() ) {
		return;
	}
	$id = rawurlencode( jrc_gtm_id() );
	?>
<!-- Google Tag Manager (noscript) -->
<noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo esc_attr( $id ); ?>"
height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
<!-- End Google Tag Manager (noscript) -->
	<?php
}
add_action( 'wp_body_open', 'jrc_gtm_body' );
