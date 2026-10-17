<?php
/**
 * Plugin Name:       JR Cleaning — Partner Platform
 * Plugin URI:        https://jrcleaning.org
 * Description:       The Estate Agent Bonus Program: partner accounts, jobs, reports, tiers, availability, requests and exports. Extracted from the theme so the platform and its data survive a redesign.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  woocommerce
 * Author:            James Knowles
 * Text Domain:       jrc-partners
 *
 * @package jrc-partners
 *
 * REQUIRES PLUGINS
 * ----------------
 * The whole partner area is a set of WooCommerce My Account endpoints, so WooCommerce
 * is not optional — without it there is nowhere for any of this to render. WordPress
 * 6.5+ enforces the header: it will not activate this plugin without WooCommerce, and
 * deactivating WooCommerce deactivates this (and, through its own header, the
 * Connecteam plugin) rather than leaving a half-working partner area behind.
 *
 * TEXT DOMAIN
 * -----------
 * `jrc-partners`, matching the folder slug, because that is the only thing WordPress
 * will look for. Just-in-time loading resolves a domain to
 * wp-content/languages/plugins/<domain>-<locale>.mo, so the `jrcleaning` domain this
 * code carried while it lived in the theme could never have resolved from a plugin —
 * it would have depended on the theme happening to be active and loading it first,
 * which is exactly the coupling extracting this was meant to remove.
 *
 * No Domain Path header and no load_plugin_textdomain() call: neither is needed when
 * the domain matches the slug and no translations ship inside the plugin.
 *
 * WHY THIS IS A PLUGIN AND NOT PART OF THE THEME
 * ----------------------------------------------
 * This layer owns two custom database tables, a set of WooCommerce My Account
 * endpoints, three cron jobs and live customer data. None of that is presentation.
 * While it lived in the theme, switching or rebuilding the theme would have taken the
 * partner platform with it — endpoints 404ing, cron silently stopping, and the tables
 * left orphaned with real partner records still in them. Themes are replaceable;
 * this is not.
 *
 * The boundary is deliberately one-way: the theme may call into this plugin (always
 * behind function_exists(), so the site still renders with the plugin off), and this
 * plugin never calls back into the theme. The one thing it does defer to the theme is
 * TEMPLATES — see jrcp_locate_template() below.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JRCP_VERSION', '1.0.0' );
define( 'JRCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'JRCP_URL', plugin_dir_url( __FILE__ ) );

/**
 * Find a partner template, theme first.
 *
 * Replaces the bare locate_template() this code used when it lived in the theme. The
 * theme keeps the right to override any partner screen by dropping a file of the same
 * name into its own includes/ folder; if it doesn't, the plugin's own copy is used, so
 * the partner area keeps working under any theme — including a fresh install of
 * Twenty Twenty-Five while a redesign is in progress.
 *
 * @param string $name Bare filename, e.g. 'affiliate-dashboard.php'.
 * @return string Absolute path, or '' when neither location has it.
 */
function jrcp_locate_template( $name ) {
	$name = basename( $name );

	// Theme override wins.
	$theme = locate_template( array( 'includes/' . $name, 'partner/' . $name ) );
	if ( $theme ) {
		return $theme;
	}

	$own = JRCP_DIR . 'templates/' . $name;
	return file_exists( $own ) ? $own : '';
}

/**
 * Load order matters here, so it is explicit rather than a glob.
 *
 * partner-programme.php is first: it defines the channels and tiers that the settings,
 * types and account layers read while they are registering. Everything after affiliate.php
 * hooks into it rather than the other way round.
 */
$jrcp_includes = array(
	'includes/partner-programme.php',
	'includes/affiliate-settings.php',
	'includes/affiliate-types.php',
	'includes/affiliate-jobs.php',
	'includes/affiliate-job-events.php',
	'includes/affiliate-bonus.php',
	'includes/affiliate-job-reports.php',
	'includes/affiliate-admin.php',
	'includes/affiliate.php',
	'includes/price-list.php',
	'includes/school-sites.php',

	'includes/affiliate-tier-history.php',
	'includes/affiliate-notify.php',
	'includes/affiliate-account.php',
	'includes/affiliate-availability.php',
	'includes/affiliate-requests.php',
	'includes/affiliate-ical.php',
	'includes/affiliate-exports.php',
	'includes/affiliate-bulk-reports.php',

	'includes/affiliate-quote-pipeline.php',
	'includes/affiliate-tier-auto.php',
	'includes/privacy.php',
);

foreach ( $jrcp_includes as $jrcp_file ) {
	$jrcp_path = JRCP_DIR . $jrcp_file;
	if ( file_exists( $jrcp_path ) ) {
		require_once $jrcp_path;
	}
}
unset( $jrcp_includes, $jrcp_file, $jrcp_path );

/**
 * Activation.
 *
 * The table installers still run on admin_init as well (they are cheap, version-gated
 * option reads) so an upgrade that adds a column applies on the next admin page load
 * without anyone having to deactivate and reactivate. This hook is what makes a FIRST
 * activation correct: the tables and the My Account endpoints both exist before anyone
 * visits the front end, rather than waiting for an administrator to load a screen.
 */
register_activation_hook( __FILE__, function () {
	if ( function_exists( 'jrc_jobs_install' ) ) {
		jrc_jobs_install();
	}
	if ( function_exists( 'jrc_job_events_install' ) ) {
		jrc_job_events_install();
	}

	/*
	 * Register the My Account endpoints BEFORE flushing.
	 *
	 * affiliate.php adds these on `init`, but `init` fired earlier in this same request
	 * — before this plugin was loaded at all, because activation happens from the admin
	 * long after init. So at this moment the rewrite rules contain none of them, and a
	 * bare flush_rewrite_rules() would happily write that empty set to the database and
	 * 404 the entire partner area until something flushed again.
	 *
	 * Registering them here first means the flush writes the rules we actually want.
	 */
	if ( function_exists( 'jrc_affiliate_tabs' ) ) {
		foreach ( array_keys( jrc_affiliate_tabs() ) as $slug ) {
			add_rewrite_endpoint( $slug, EP_ROOT | EP_PAGES );
		}
	}

	// Belt to that braces: clearing the sentinel makes the check in affiliate.php flush
	// once more on the next request, when init has genuinely run with this plugin loaded.
	delete_option( 'jrc_aff_flush' );

	flush_rewrite_rules();
} );

/**
 * Deactivation: drop the rewrite rules and stop the cron jobs.
 *
 * Tables and user meta are deliberately left alone — deactivating a plugin must never
 * destroy customer data. Removing the data is an uninstall decision, and a destructive
 * one, so it is left to a human.
 */
register_deactivation_hook( __FILE__, function () {
	// The nightly tier run, and the daily partner digest email.
	$hooks = array( 'jrc_affiliate_digest' );
	if ( defined( 'JRC_TIER_CRON_HOOK' ) ) {
		$hooks[] = JRC_TIER_CRON_HOOK;
	}

	foreach ( $hooks as $hook ) {
		// Clears every scheduled instance, not just the next one — wp_unschedule_event()
		// on a single timestamp leaves duplicates behind if one was ever double-booked.
		wp_clear_scheduled_hook( $hook );
	}

	flush_rewrite_rules();
} );
