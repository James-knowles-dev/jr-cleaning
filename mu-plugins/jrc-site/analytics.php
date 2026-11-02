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

// ---------------------------------------------------------------------------
// Confirmed form submissions
// ---------------------------------------------------------------------------

/*
 * WHY THIS EXISTS
 *
 * The September and October 2026 reports showed 14 and 31 form submissions against 5
 * and 4 genuine Gravity Forms entries. Two things were counting "form_submit":
 *
 *   1. A GTM tag on GTM's generic Form Submission trigger. Gravity Forms submits
 *      through a hidden iframe and the form carries `novalidate`, so that trigger
 *      fires on every click of Submit — including attempts Gravity Forms then rejects
 *      for a missing field.
 *   2. GA4's enhanced-measurement "Form interactions", which sends its own form_submit
 *      on every form on every page — so every contact enquiry counted twice, and each
 *      Next click in the three-page quote wizard counted as a submission.
 *
 * This pushes ONE dataLayer event per entry Gravity Forms actually accepted:
 *
 *   { event: 'jrc_form_confirmed', jrc_form_id: 1, jrc_form_name: 'Contact',
 *     jrc_lead_type: 'contact' }
 *
 * No personal data — only which form and what kind of lead. GTM turns it into a GA4
 * event (see the GTM setup notes in the README).
 *
 * Three confirmation paths, each covered once so nothing is double counted:
 *
 *   AJAX + message    Gravity Forms triggers gform_confirmation_loaded on success only.
 *                     This is how every form on the site is embedded (ajax="true").
 *   No AJAX + message The confirmation is rendered server-side; a script is appended.
 *   Redirect          The browser leaves the page; a short-lived cookie carries the
 *                     event to the next page, which pushes it and clears the cookie.
 */

/** Cookie that carries a confirmation across a redirect. */
const JRC_FORM_CONFIRMED_COOKIE = 'jrc_form_confirmed';

/**
 * Lead type for a form, from the form IDs set in Theme Settings.
 *
 * @return string contact | quote | partner | other
 */
function jrc_form_lead_type( $form_id ) {
	$form_id = (int) $form_id;
	$map     = array(
		'contact_form_id' => 'contact',
		'quote_form_id'   => 'quote',
		'partner_form_id' => 'partner',
	);
	if ( function_exists( 'get_field' ) ) {
		foreach ( $map as $option => $type ) {
			if ( $form_id && (int) get_field( $option, 'options' ) === $form_id ) {
				return $type;
			}
		}
	}
	return 'other';
}

/**
 * The dataLayer payload for a confirmed submission.
 *
 * @param array $form Gravity Forms form array.
 */
function jrc_form_confirmed_payload( $form ) {
	$id = (int) ( $form['id'] ?? 0 );
	return array(
		'event'         => 'jrc_form_confirmed',
		'jrc_form_id'   => $id,
		'jrc_form_name' => (string) ( $form['title'] ?? '' ),
		'jrc_lead_type' => jrc_form_lead_type( $id ),
	);
}

/**
 * Non-AJAX message confirmations and redirect confirmations.
 *
 * AJAX message confirmations are left alone: the footer listener below catches those
 * from gform_confirmation_loaded, and handling them here as well would count twice.
 */
add_filter( 'gform_confirmation', function ( $confirmation, $form, $entry, $ajax ) {
	// Spam is accepted silently by Gravity Forms — the bot sees the normal confirmation,
	// and gform_confirmation_loaded still fires. Mark it so the footer listener skips
	// it. The marker is deliberately bland so it tells a bot nothing.
	if ( is_array( $entry ) && ! empty( $entry['status'] ) && 'spam' === $entry['status'] ) {
		if ( is_string( $confirmation ) ) {
			$confirmation .= '<span hidden data-jrc-form="' . (int) ( $form['id'] ?? 0 ) . '" data-jrc-count="0"></span>';
		}
		return $confirmation;
	}

	$payload = jrc_form_confirmed_payload( $form );

	if ( is_array( $confirmation ) && ! empty( $confirmation['redirect'] ) ) {
		if ( ! headers_sent() ) {
			setcookie( JRC_FORM_CONFIRMED_COOKIE, rawurlencode( wp_json_encode( $payload ) ), array(
				'expires'  => time() + 5 * MINUTE_IN_SECONDS,
				'path'     => COOKIEPATH ? COOKIEPATH : '/',
				'secure'   => is_ssl(),
				'httponly' => false,   // read once by the next page's script, then cleared
				'samesite' => 'Lax',
			) );
		}
		return $confirmation;
	}

	if ( ! $ajax && is_string( $confirmation ) ) {
		$confirmation .= '<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push('
			. wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT )
			. ');</script>';
	}

	return $confirmation;
}, 20, 4 );

/**
 * Footer listener: AJAX confirmations, and the redirect cookie.
 *
 * Printed on every front-end page because it is tiny and a redirect can land
 * anywhere. Does nothing where there is neither a form nor a cookie.
 */
add_action( 'wp_footer', function () {
	if ( is_admin() ) {
		return;
	}

	// Form id => payload for each form whose lead type is known, so the listener can
	// label an AJAX confirmation without a round trip. Titles come from Gravity Forms.
	$forms = array();
	if ( class_exists( 'GFAPI' ) && function_exists( 'get_field' ) ) {
		foreach ( array( 'contact_form_id', 'quote_form_id', 'partner_form_id' ) as $option ) {
			$id = (int) get_field( $option, 'options' );
			if ( $id ) {
				$form = GFAPI::get_form( $id );
				if ( $form ) {
					$forms[ $id ] = jrc_form_confirmed_payload( $form );
				}
			}
		}
	}
	?>
<script>
(function () {
	window.dataLayer = window.dataLayer || [];
	var forms = <?php echo wp_json_encode( (object) $forms, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ); ?>;
	var cookie = '<?php echo esc_js( JRC_FORM_CONFIRMED_COOKIE ); ?>';

	// Redirect confirmations: push what the previous page left, then clear it.
	var m = document.cookie.match(new RegExp('(?:^|; )' + cookie + '=([^;]*)'));
	if (m) {
		document.cookie = cookie + '=; Max-Age=0; path=/; SameSite=Lax';
		try { window.dataLayer.push(JSON.parse(decodeURIComponent(m[1]))); } catch (e) {}
	}

	// AJAX confirmations. Gravity Forms fires this only once an entry is accepted —
	// never on a failed validation and never on the quote wizard's Next steps.
	if (!window.jQuery) { return; }
	var seen = {};
	window.jQuery(document).on('gform_confirmation_loaded', function (e, formId) {
		formId = parseInt(formId, 10);
		if (!formId || seen[formId]) { return; }
		// Spam: the confirmation (already swapped in by Gravity Forms) carries a marker.
		if (document.querySelector('[data-jrc-form="' + formId + '"][data-jrc-count="0"]')) { return; }
		seen[formId] = true;
		window.dataLayer.push(forms[formId] || {
			event: 'jrc_form_confirmed',
			jrc_form_id: formId,
			jrc_form_name: '',
			jrc_lead_type: 'other'
		});
	});
})();
</script>
	<?php
}, 100 );
