<?php
/**
 * Enqueue styles and scripts.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Does this request actually involve WooCommerce — the shop, the cart, the checkout,
 * or any My Account endpoint (which is where the partner area lives)?
 *
 * One predicate, two consumers: it decides whether the theme loads its own shop and
 * partner CSS (app.css), and whether WooCommerce's core assets are allowed to stay.
 * They have to agree — loading app.css without Woo's own stylesheet, or vice versa,
 * gives a half-styled account page — so they read from the same function rather than
 * from two copies of the same list that can drift apart.
 *
 * Memoised: called from two hooks at different priorities, and the block/shortcode
 * scan at the bottom is not free.
 */
function jrc_needs_woo() {
	static $needs = null;

	if ( null !== $needs ) {
		return $needs;
	}

	if ( ! function_exists( 'is_woocommerce' ) ) {
		$needs = false;
		return $needs;
	}

	// Any page that genuinely needs WooCommerce's own styling/behaviour. The affiliate
	// dashboard lives on a My Account endpoint, so is_account_page() covers it.
	$needs = is_woocommerce()
		|| is_cart()
		|| is_checkout()
		|| is_account_page()
		|| is_wc_endpoint_url();

	// Blocks in page content could still need Woo — bail out if the post uses any
	// WooCommerce block or shortcode.
	if ( ! $needs && is_singular() ) {
		$post = get_post();
		if ( $post && (
			has_shortcode( $post->post_content, 'products' ) ||
			has_shortcode( $post->post_content, 'product_page' ) ||
			has_shortcode( $post->post_content, 'add_to_cart' ) ||
			false !== strpos( $post->post_content, 'wp:woocommerce/' )
		) ) {
			$needs = true;
		}
	}

	return $needs;
}

/**
 * Is this one of the partner marketing pages?
 *
 * The four templates that use the channel grid, tier table and account-feature tour.
 * Matched on template rather than slug so renaming a page in the admin cannot silently
 * unstyle it.
 */
function jrc_needs_partners() {
	static $needs = null;

	if ( null === $needs ) {
		$needs = is_page_template( array(
			'page-partners.php',
			'page-partner-channel.php',
			'page-partner-register.php',
			'page-partner-account.php',
		) );
	}

	return $needs;
}

/**
 * Will a Gravity Form actually render on this request?
 *
 * Three ways one can get onto a page, so all three are checked:
 *   1. a template that embeds a form directly (contact, quote, partner registration);
 *   2. an ACF flexible-content block that embeds one (contact_form, contact_details);
 *   3. a [gravityform] shortcode typed into the content.
 *
 * The block check reads the raw `blocks` meta rather than get_field(): for flexible
 * content ACF stores just the ordered list of layout names there, so this is one cached
 * meta read instead of hydrating every field on the page to answer a yes/no question.
 */
function jrc_needs_forms() {
	static $needs = null;

	if ( null !== $needs ) {
		return $needs;
	}

	// The quote wizard's own stylesheet is declared dependent on this one, and WordPress
	// silently skips a stylesheet whose dependency was never registered. Deriving the
	// answer from jrc_needs_quote() rather than repeating 'page-quote.php' in the list
	// below means the two can never disagree.
	if ( jrc_needs_quote() ) {
		$needs = true;
		return $needs;
	}

	$needs = is_page_template( array(
		'page-contact.php',
		'page-partner-register.php',
	) );

	if ( ! $needs && is_singular() ) {
		$post = get_post();

		if ( $post ) {
			$layouts = get_post_meta( $post->ID, 'blocks', true );
			if ( is_array( $layouts ) && array_intersect( array( 'contact_form', 'contact_details' ), $layouts ) ) {
				$needs = true;
			}

			if ( ! $needs && has_shortcode( (string) $post->post_content, 'gravityform' ) ) {
				$needs = true;
			}
		}
	}

	return $needs;
}

/** The Areas / coverage page: county grid, town pills, map, coverage checker. */
function jrc_needs_areas() {
	static $needs = null;

	if ( null === $needs ) {
		$needs = is_page_template( 'page-areas.php' );
	}

	return $needs;
}

/**
 * The multi-step quote wizard.
 *
 * Note this page loads forms.css as well: quote.css holds only the wizard-specific
 * layer (stepper, cards, chips), while the underlying input and label styling is the
 * same layout/_gravity-forms.scss every other form on the site uses.
 */
function jrc_needs_quote() {
	static $needs = null;

	if ( null === $needs ) {
		$needs = is_page_template( 'page-quote.php' );
	}

	return $needs;
}

function jrc_assets() {
	// Cache-bust on every rebuild: version = file modified time (falls back to theme version).
	$css_ver = file_exists( JRC_DIR . '/main.css' ) ? filemtime( JRC_DIR . '/main.css' ) : JRC_VERSION;
	$js_ver  = file_exists( JRC_DIR . '/script.js' ) ? filemtime( JRC_DIR . '/script.js' ) : JRC_VERSION;

	// Fonts are self-hosted (see app/scss/base/_fonts.scss) rather than pulled from
	// fonts.googleapis.com: it removes a render-blocking third-party DNS+TLS round trip
	// from the critical path, and avoids sending visitor IPs to Google (GDPR).

	// Main compiled stylesheet (includes the @font-face declarations).
	wp_enqueue_style( 'jrc-main', JRC_URI . '/main.css', array(), $css_ver );

	// Shop + partner-area styles. The affiliate dashboard alone was ~1,300 lines of
	// SCSS that no logged-out visitor could ever see, so it compiles separately and
	// loads only where it applies. Declared dependent on jrc-main so it always lands
	// after it in the cascade, whatever order WordPress prints the queue in.
	if ( jrc_needs_woo() && file_exists( JRC_DIR . '/app.css' ) ) {
		wp_enqueue_style( 'jrc-app', JRC_URI . '/app.css', array( 'jrc-main' ), filemtime( JRC_DIR . '/app.css' ) );
	}

	// Partner marketing pages, and Gravity Forms overrides. Same reasoning as app.css:
	// page-specific CSS that most visitors never see, so it is not in main.css. Both
	// depend on jrc-main so they land after it in the cascade whatever order the queue
	// is printed in.
	if ( jrc_needs_partners() && file_exists( JRC_DIR . '/partners.css' ) ) {
		wp_enqueue_style( 'jrc-partners', JRC_URI . '/partners.css', array( 'jrc-main' ), filemtime( JRC_DIR . '/partners.css' ) );
	}

	if ( jrc_needs_forms() && file_exists( JRC_DIR . '/forms.css' ) ) {
		wp_enqueue_style( 'jrc-forms', JRC_URI . '/forms.css', array( 'jrc-main' ), filemtime( JRC_DIR . '/forms.css' ) );
	}

	if ( jrc_needs_areas() && file_exists( JRC_DIR . '/areas.css' ) ) {
		wp_enqueue_style( 'jrc-areas', JRC_URI . '/areas.css', array( 'jrc-main' ), filemtime( JRC_DIR . '/areas.css' ) );
	}

	// Depends on jrc-forms, not jrc-main: the wizard's rules override the shared form
	// styling, so it has to be printed after it, and naming the real dependency is what
	// guarantees that rather than relying on the order these ifs happen to run in.
	if ( jrc_needs_quote() && file_exists( JRC_DIR . '/quote.css' ) ) {
		wp_enqueue_style( 'jrc-quote', JRC_URI . '/quote.css', array( 'jrc-main', 'jrc-forms' ), filemtime( JRC_DIR . '/quote.css' ) );
	}

	// Scripts.
	//
	// Deliberately not localised: the theme registers no wp_ajax_ handler and nothing in
	// app/js reads an ajaxUrl, so a `jrcData` object was dead weight — and the nonce in
	// it made every page's HTML user- and time-variant, which quietly defeats full-page
	// caching. If AJAX is added later, localise it on that page only.
	wp_enqueue_script( 'jrc-script', JRC_URI . '/script.js', array(), $js_ver, true );
}
add_action( 'wp_enqueue_scripts', 'jrc_assets' );

/**
 * Preload the two font files needed for the first paint (body + heading regular/bold).
 * The rest load normally via @font-face; font-display:swap means text is never invisible.
 */
add_action( 'wp_head', function () {
	$preload = array(
		'/fonts/hanken-grotesk-var-latin.woff2',
		'/fonts/plus-jakarta-sans-var-latin.woff2',
	);
	foreach ( $preload as $font ) {
		if ( file_exists( JRC_DIR . $font ) ) {
			echo '<link rel="preload" href="' . esc_url( JRC_URI . $font ) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		}
	}
	// Keep in step with $navy in app/scss/vars.scss.
	echo '<meta name="theme-color" content="#143458">' . "\n";
}, 1 );

/**
 * WooCommerce ships ~127KB of CSS and ~100KB of jQuery-dependent JS on every request,
 * including pages with no shop content at all. This site's shop is a handful of products
 * plus the My Account/affiliate area, so load Woo's assets only where they're used.
 *
 * Runs at priority 99 so it fires after WooCommerce has registered everything.
 */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_woocommerce' ) || jrc_needs_woo() ) {
		return;
	}

	foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen', 'wc-blocks-style', 'wc-blocks-style-packages-style' ) as $handle ) {
		wp_dequeue_style( $handle );
		wp_deregister_style( $handle );
	}

	$scripts = array( 'woocommerce', 'wc-add-to-cart', 'wc-cart-fragments', 'wc-jquery-blockui', 'wc-js-cookie', 'sourcebuster-js', 'wc-order-attribution' );

	// GTM4WP's WooCommerce integration only emits shop dataLayer events (product
	// impressions, add-to-cart, checkout steps) — there is nothing for it to track
	// on a content page. It is also the sole reason jQuery loads there, so dropping
	// it takes ~85KB of render-blocking jQuery with it. The main GTM container and
	// the generic ecommerce script are untouched, so pageviews and conversions
	// still fire normally.
	$scripts[] = 'gtm4wp-woocommerce';

	foreach ( $scripts as $handle ) {
		wp_dequeue_script( $handle );
		wp_deregister_script( $handle );
	}
}, 99 );

/**
 * jquery-migrate is a development shim for deprecated jQuery 1.x APIs. It is
 * render-blocking in <head> and nothing on the front end needs it.
 */
add_action( 'wp_default_scripts', function ( $scripts ) {
	if ( is_admin() || empty( $scripts->registered['jquery'] ) ) {
		return;
	}
	$scripts->registered['jquery']->deps = array_diff(
		$scripts->registered['jquery']->deps,
		array( 'jquery-migrate' )
	);
} );
