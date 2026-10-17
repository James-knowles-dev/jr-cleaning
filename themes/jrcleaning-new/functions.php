<?php
/**
 * J R Cleaning (Friendly), theme bootstrap.
 *
 * @package jrcleaning
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JRC_VERSION', '1.0.0' );
define( 'JRC_DIR', get_template_directory() );
define( 'JRC_URI', get_template_directory_uri() );

/*
 * Redirects, security hardening, the GTM container and DISALLOW_FILE_EDIT used to be
 * loaded here. They now live in the must-use plugin wp-content/mu-plugins/jrc-site.php
 * so that switching theme cannot switch them off. If that plugin is missing — a deploy
 * that skipped mu-plugins/ — say so loudly rather than let the SEO redirects vanish.
 */
if ( ! defined( 'JRC_SITE_LOADED' ) ) {
	add_action( 'admin_notices', function () {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>JR Cleaning:</strong> the <code>mu-plugins/jrc-site.php</code> must-use plugin is not loaded. Legacy 301 redirects, security hardening and Google Tag Manager are all OFF until it is deployed.</p></div>';
	} );
}

// Load theme function files.
$jrc_includes = array(
	'functions/helpers.php',
	'functions/setup.php',
	'functions/enqueue.php',
	'functions/custom-post-type.php',
	'functions/acf.php',
	'functions/acf-fields.php',
	'functions/acf-page-fields.php',
	'functions/partners.php',
	'functions/schema.php',
	'functions/woocommerce.php',
);

/*
 * THE PARTNER PLATFORM IS NOT LOADED HERE ANY MORE.
 *
 * The affiliate/partner layer and the Connecteam integration used to be ~10,000 lines
 * of this list. They now live in two plugins:
 *
 *   wp-content/plugins/jrc-partners/    partner accounts, jobs, reports, tiers,
 *                                       availability, requests, exports, and the
 *                                       {prefix}jrc_jobs / jrc_job_events tables
 *   wp-content/plugins/jrc-connecteam/  the Connecteam shift-scheduler sync
 *
 * They moved because none of it is presentation: it owns customer data, database
 * tables, My Account endpoints and cron jobs, all of which have to outlive any theme.
 *
 * The theme still renders partner MARKETING pages (page-partners.php,
 * page-partner-channel.php, page-partner-account.php, page-partner-register.php) and
 * may override any partner screen by placing a file of the same name in includes/ —
 * jrcp_locate_template() checks the theme before the plugin's own copy.
 *
 * Every theme-side call into the plugin is wrapped in function_exists(), so the site
 * still renders with the plugins deactivated.
 */

foreach ( $jrc_includes as $jrc_file ) {
	$path = JRC_DIR . '/' . $jrc_file;
	if ( file_exists( $path ) ) {
		require_once $path;
	}
}
