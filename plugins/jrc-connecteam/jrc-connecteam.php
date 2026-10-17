<?php
/**
 * Plugin Name:       JR Cleaning — Connecteam Sync
 * Plugin URI:        https://jrcleaning.org
 * Description:       Two-way sync between Connecteam's shift scheduler and the partner platform's job records, over the REST webhook and a nightly reconciliation run.
 * Version:           1.0.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Requires Plugins:  jrc-partners
 * Author:            James Knowles
 * Text Domain:       jrc-connecteam
 *
 * @package jrc-connecteam
 *
 * Text domain matches the folder slug, which is the only form just-in-time loading can
 * resolve. WooCommerce is not named in Requires Plugins because jrc-partners already
 * requires it and WordPress walks the chain — naming it twice would just mean two
 * places to keep correct.
 *
 * WHY THIS IS SEPARATE FROM jrc-partners
 * --------------------------------------
 * This is an integration with a third-party SaaS, and third-party integrations are the
 * part of a system most likely to be switched off: the contract lapses, the API version
 * changes, the office moves to different scheduling software. Keeping it apart means
 * that can be done with a single click, without touching the partner platform that the
 * business actually runs on.
 *
 * It depends on jrc-partners one-way — it writes through jrc_job_upsert_external() and
 * reads jrc_affiliate_by_code(). `Requires Plugins` above makes WordPress enforce that
 * (6.5+): it will not let this activate without jrc-partners, and deactivating
 * jrc-partners deactivates this too. The runtime guard below is the belt to that
 * braces, for the case where the dependency is present but a rename or a partial
 * deploy has left the functions themselves missing.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'JRCCT_VERSION', '1.0.0' );
define( 'JRCCT_DIR', plugin_dir_path( __FILE__ ) );
define( 'JRCCT_URL', plugin_dir_url( __FILE__ ) );

/**
 * Is the partner platform actually there to write into?
 *
 * Checked against the two functions this plugin genuinely calls, rather than against a
 * version constant — what matters is whether the integration points exist, not what
 * the other plugin calls itself.
 */
function jrcct_dependency_met() {
	return function_exists( 'jrc_job_upsert_external' ) && function_exists( 'jrc_affiliate_by_code' );
}

/**
 * Load on plugins_loaded rather than at file scope: the dependency check has to run
 * after every plugin has had a chance to declare its functions, and load order between
 * two active plugins is alphabetical, not dependency-aware.
 */
add_action( 'plugins_loaded', function () {
	if ( ! jrcct_dependency_met() ) {
		add_action( 'admin_notices', function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-error"><p><strong>JR Cleaning — Connecteam Sync</strong> is inactive: it needs the <strong>JR Cleaning — Partner Platform</strong> plugin, which is not available. Shifts are not being synced.</p></div>';
		} );
		return;
	}

	// Order matches the old theme loader: the client is the transport, the mapper reads
	// affiliate records, the sync writes jobs, the admin screen drives all three.
	foreach ( array( 'connecteam-client.php', 'connecteam-map.php', 'connecteam-match.php', 'connecteam-merge.php', 'connecteam-sync.php', 'connecteam-admin.php' ) as $file ) {
		$path = JRCCT_DIR . 'includes/' . $file;
		if ( file_exists( $path ) ) {
			require_once $path;
		}
	}
} );

/**
 * Deactivation: stop the nightly reconciliation.
 *
 * The webhook registered at Connecteam's end is deliberately left in place. Deleting it
 * is a remote, destructive call that needs a working API key, and a deactivation is
 * often temporary — re-registering on every reactivation would churn the integration.
 * Remove it from the settings screen when the integration is genuinely being retired.
 */
register_deactivation_hook( __FILE__, function () {
	if ( defined( 'JRC_CT_CRON_HOOK' ) ) {
		wp_clear_scheduled_hook( JRC_CT_CRON_HOOK );
	}
} );
