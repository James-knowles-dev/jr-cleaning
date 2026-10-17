<?php
/**
 * Plugin Name: JR Cleaning — Site Behaviour
 * Description: Legacy 301 redirects, security hardening and the GTM container. Must-use, so none of it disappears when the theme is switched.
 * Author:      James Knowles
 * Version:     1.0.0
 *
 * WHY A MUST-USE PLUGIN
 * ---------------------
 * These used to live in the theme. None of them is presentation:
 *
 *   redirects.php  the 301s that carry the old site's rankings and backlinks
 *   hardening.php  user-enumeration blocks and baseline security headers
 *   analytics.php  the GTM container
 *
 * and DISALLOW_FILE_EDIT, below. Activating another theme — even briefly, to preview a
 * redesign — would have switched every one of them off without a word: old URLs 404ing,
 * the file editor back on, tracking gone. A must-use plugin cannot be deactivated from
 * the admin and loads whatever the theme.
 *
 * DEPLOYMENT: wp-content/mu-plugins/jrc-site.php AND wp-content/mu-plugins/jrc-site/
 * must both be deployed. The theme shows an admin notice if this file is missing.
 *
 * @package jrc-site
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JRC_SITE_LOADED', '1.0.0' );

/**
 * Disable the admin theme/plugin file editor.
 *
 * A compromised admin account otherwise gets arbitrary PHP execution through
 * Appearance → Theme File Editor. This normally belongs in wp-config.php, but that file
 * is Cloudways-owned and excluded from deploys, so defining it here is what actually
 * reaches staging and production. map_meta_cap() reads the constant at call time.
 */
if ( ! defined( 'DISALLOW_FILE_EDIT' ) ) {
	define( 'DISALLOW_FILE_EDIT', true );
}

foreach ( array( 'hardening.php', 'redirects.php', 'analytics.php' ) as $jrc_site_file ) {
	$jrc_site_path = __DIR__ . '/jrc-site/' . $jrc_site_file;
	if ( file_exists( $jrc_site_path ) ) {
		require_once $jrc_site_path;
	}
}
unset( $jrc_site_file, $jrc_site_path );
