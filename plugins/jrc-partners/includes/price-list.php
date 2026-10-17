<?php
/**
 * Custom price lists: each account's own price list, on screen and as a branded PDF.
 *
 * Replaces the agency-specific price list documents the office used to issue by hand.
 *
 * MODEL
 * -----
 * There is ONE standard price list, kept on Partner Programme → Price List (an ACF
 * options page). It follows the shape of the existing documents:
 *
 *   sections   Itemised services, grouped (e.g. "Regular cleaning", "Carpets").
 *   guide      Guide prices by property size, one column for End of Tenancy and one
 *              for Sparkle cleans.
 *   addons     Add-on extras.
 *
 * Each account's list is that standard list with the account's agreed pricing applied,
 * set on the account's user profile:
 *
 *   jrc_pl_mode       programme | percent | standard
 *                       programme  the account's programme discount (its "bookings &
 *                                  referrals" rate) — the default, and what the hand-made
 *                                  agency lists did
 *                       percent    a custom percentage just for the price list
 *                       standard   standard prices, no discount
 *   jrc_pl_percent    used when mode is `percent`
 *   jrc_pl_overrides  array( item key => price ) — individual agreed prices, which win
 *                     over any percentage
 *   jrc_pl_note       optional line printed on this account's list
 *
 * So pricing per account can be a percentage, individual prices, or both, without the
 * client having had to choose one up front.
 *
 * ITEM KEYS
 * ---------
 * Overrides are matched to items by a key built from the item's section and name (see
 * jrc_pl_item_key()). ACF repeater rows have no stable id, and a key the office can read
 * is easier to reason about than a hidden one. The cost: renaming an item on the
 * standard list drops any override set against the old name. The profile screen lists
 * orphaned overrides so that never happens silently.
 *
 * Display and download only. Nothing here takes a booking or a payment.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ---------------------------------------------------------------------------
// Standard list: options page + fields
// ---------------------------------------------------------------------------

add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}
	acf_add_options_page( array(
		'page_title'  => __( 'Standard Price List', 'jrc-partners' ),
		'menu_title'  => __( 'Price List', 'jrc-partners' ),
		'menu_slug'   => 'jrc-price-list',
		'parent_slug' => 'jrc-partner',
		'capability'  => 'manage_options',
		'redirect'    => false,
	) );
} );

add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$price = function ( $key, $label, $width = '15' ) {
		return array(
			'key'     => 'field_jrc_pl_' . $key,
			'label'   => $label,
			'name'    => $key,
			'type'    => 'number',
			'min'     => 0,
			'step'    => 0.01,
			'prepend' => '£',
			'wrapper' => array( 'width' => $width ),
		);
	};

	// Shared shape for an itemised line, used by both service sections and add-ons.
	$item_fields = function ( $prefix ) use ( $price ) {
		return array(
			array( 'key' => "field_jrc_pl_{$prefix}_name", 'label' => 'Item', 'name' => 'name', 'type' => 'text', 'required' => 1, 'wrapper' => array( 'width' => '30' ) ),
			array( 'key' => "field_jrc_pl_{$prefix}_note", 'label' => 'Detail', 'name' => 'note', 'type' => 'text', 'wrapper' => array( 'width' => '30' ) ),
			array_merge( $price( 'price', 'Price' ), array( 'key' => "field_jrc_pl_{$prefix}_price" ) ),
			array( 'key' => "field_jrc_pl_{$prefix}_from", 'label' => '"From"', 'name' => 'from', 'type' => 'true_false', 'ui' => 1, 'wrapper' => array( 'width' => '10' ) ),
			array( 'key' => "field_jrc_pl_{$prefix}_unit", 'label' => 'Unit', 'name' => 'unit', 'type' => 'text', 'placeholder' => 'per hour', 'wrapper' => array( 'width' => '15' ) ),
		);
	};

	acf_add_local_field_group( array(
		'key'      => 'group_jrc_price_list',
		'title'    => 'Standard Price List',
		'location' => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'jrc-price-list' ) ) ),
		'fields'   => array(
			array(
				'key'     => 'field_jrc_pl_msg',
				'label'   => '',
				'type'    => 'message',
				'message' => 'This is the standard price list every partner account\'s list is built from. Each account sees these prices with its own agreed pricing applied — '
					. 'set that on the account\'s user profile under <strong>Price list</strong>. Leave a price blank to show "POA".<br><br>'
					. '<strong>Renaming an item</strong> drops any individual price set for it on an account; the account\'s profile will flag it.',
			),

			array( 'key' => 'field_jrc_pl_tab_head', 'label' => 'Heading & notes', 'type' => 'tab' ),
			array( 'key' => 'field_jrc_pl_title', 'label' => 'Title', 'name' => 'pl_title', 'type' => 'text', 'default_value' => 'Price List', 'wrapper' => array( 'width' => '50' ) ),
			array( 'key' => 'field_jrc_pl_contact', 'label' => 'Contact line', 'name' => 'pl_contact', 'type' => 'text', 'instructions' => 'Printed in the PDF footer, e.g. phone · email · website.', 'wrapper' => array( 'width' => '50' ) ),
			array( 'key' => 'field_jrc_pl_intro', 'label' => 'Introduction', 'name' => 'pl_intro', 'type' => 'textarea', 'rows' => 3 ),
			array( 'key' => 'field_jrc_pl_footer', 'label' => 'Terms & small print', 'name' => 'pl_footer', 'type' => 'textarea', 'rows' => 4, 'instructions' => 'One point per line. Printed at the end of the list.' ),
			array(
				'key'           => 'field_jrc_pl_rounding',
				'label'         => 'Round discounted prices',
				'name'          => 'pl_rounding',
				'type'          => 'select',
				'choices'       => array( '0' => 'To the penny', '0.5' => 'To the nearest 50p', '1' => 'To the nearest £1', '5' => 'To the nearest £5' ),
				'default_value' => '0',
				'instructions'  => 'Applied after an account\'s percentage discount. Individual agreed prices are never rounded.',
				'wrapper'       => array( 'width' => '50' ),
			),

			array( 'key' => 'field_jrc_pl_tab_services', 'label' => 'Services', 'type' => 'tab' ),
			array(
				'key'          => 'field_jrc_pl_sections',
				'label'        => 'Service sections',
				'name'         => 'pl_sections',
				'type'         => 'repeater',
				'layout'       => 'block',
				'button_label' => 'Add section',
				'collapsed'    => 'field_jrc_pl_section_title',
				'sub_fields'   => array(
					array( 'key' => 'field_jrc_pl_section_title', 'label' => 'Section heading', 'name' => 'title', 'type' => 'text', 'required' => 1 ),
					array(
						'key'          => 'field_jrc_pl_section_items',
						'label'        => 'Items',
						'name'         => 'items',
						'type'         => 'repeater',
						'layout'       => 'table',
						'button_label' => 'Add item',
						'sub_fields'   => $item_fields( 'svc' ),
					),
				),
			),

			array( 'key' => 'field_jrc_pl_tab_guide', 'label' => 'Guide prices', 'type' => 'tab' ),
			array( 'key' => 'field_jrc_pl_guide_title', 'label' => 'Heading', 'name' => 'pl_guide_title', 'type' => 'text', 'default_value' => 'Guide prices by property size' ),
			array( 'key' => 'field_jrc_pl_guide_note', 'label' => 'Note under the table', 'name' => 'pl_guide_note', 'type' => 'text', 'placeholder' => 'Guide prices — your final quote depends on condition and scope.' ),
			array(
				'key'          => 'field_jrc_pl_guide',
				'label'        => 'Property sizes',
				'name'         => 'pl_guide',
				'type'         => 'repeater',
				'layout'       => 'table',
				'button_label' => 'Add property size',
				'sub_fields'   => array(
					array( 'key' => 'field_jrc_pl_guide_size', 'label' => 'Property size', 'name' => 'size', 'type' => 'text', 'required' => 1, 'placeholder' => '2 bed flat', 'wrapper' => array( 'width' => '40' ) ),
					array_merge( $price( 'eot', 'End of tenancy', '30' ), array( 'key' => 'field_jrc_pl_guide_eot' ) ),
					array_merge( $price( 'sparkle', 'Sparkle clean', '30' ), array( 'key' => 'field_jrc_pl_guide_sparkle' ) ),
				),
			),

			array( 'key' => 'field_jrc_pl_tab_addons', 'label' => 'Add-ons', 'type' => 'tab' ),
			array(
				'key'          => 'field_jrc_pl_addons',
				'label'        => 'Add-ons',
				'name'         => 'pl_addons',
				'type'         => 'repeater',
				'layout'       => 'table',
				'button_label' => 'Add add-on',
				'sub_fields'   => $item_fields( 'addon' ),
			),
		),
	) );
} );

// ---------------------------------------------------------------------------
// Pricing
// ---------------------------------------------------------------------------

/** Stable key for one priced cell, used to match per-account overrides. */
function jrc_pl_item_key( $group, $name, $column = '' ) {
	return sanitize_key( $group ) . ':' . sanitize_title( $name ) . ( $column ? ':' . sanitize_key( $column ) : '' );
}

/** The raw standard list from the options page, normalised. */
function jrc_pl_standard() {
	$get = function ( $name ) {
		return function_exists( 'get_field' ) ? get_field( $name, 'options' ) : null;
	};
	$num = function ( $v ) {
		return ( null === $v || '' === $v ) ? null : (float) $v;
	};
	$item = function ( $row ) use ( $num ) {
		return array(
			'name'  => trim( (string) ( $row['name'] ?? '' ) ),
			'note'  => trim( (string) ( $row['note'] ?? '' ) ),
			'price' => $num( $row['price'] ?? null ),
			'from'  => ! empty( $row['from'] ),
			'unit'  => trim( (string) ( $row['unit'] ?? '' ) ),
		);
	};

	$sections = array();
	foreach ( (array) $get( 'pl_sections' ) as $sec ) {
		$title = trim( (string) ( $sec['title'] ?? '' ) );
		$items = array();
		foreach ( (array) ( $sec['items'] ?? array() ) as $row ) {
			$it = $item( $row );
			if ( '' !== $it['name'] ) {
				$it['key'] = jrc_pl_item_key( 'svc', $title . '-' . $it['name'] );
				$items[]   = $it;
			}
		}
		if ( '' !== $title && $items ) {
			$sections[] = array( 'title' => $title, 'items' => $items );
		}
	}

	$guide = array();
	foreach ( (array) $get( 'pl_guide' ) as $row ) {
		$size = trim( (string) ( $row['size'] ?? '' ) );
		if ( '' === $size ) {
			continue;
		}
		$guide[] = array(
			'size'        => $size,
			'eot'         => $num( $row['eot'] ?? null ),
			'sparkle'     => $num( $row['sparkle'] ?? null ),
			'eot_key'     => jrc_pl_item_key( 'guide', $size, 'eot' ),
			'sparkle_key' => jrc_pl_item_key( 'guide', $size, 'sparkle' ),
		);
	}

	$addons = array();
	foreach ( (array) $get( 'pl_addons' ) as $row ) {
		$it = $item( $row );
		if ( '' !== $it['name'] ) {
			$it['key'] = jrc_pl_item_key( 'addon', $it['name'] );
			$addons[]  = $it;
		}
	}

	$footer = trim( (string) $get( 'pl_footer' ) );

	return array(
		'title'       => trim( (string) $get( 'pl_title' ) ) ?: 'Price List',
		'intro'       => trim( (string) $get( 'pl_intro' ) ),
		'contact'     => trim( (string) $get( 'pl_contact' ) ),
		'footer'      => $footer ? array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $footer ) ) ) ) : array(),
		'rounding'    => (float) ( $get( 'pl_rounding' ) ?: 0 ),
		'sections'    => $sections,
		'guide_title' => trim( (string) $get( 'pl_guide_title' ) ) ?: 'Guide prices by property size',
		'guide_note'  => trim( (string) $get( 'pl_guide_note' ) ),
		'guide'       => $guide,
		'addons'      => $addons,
	);
}

/** Does the standard list have anything in it yet? */
function jrc_pl_is_empty( $list ) {
	return ! $list['sections'] && ! $list['guide'] && ! $list['addons'];
}

/** Every override key the standard list currently has, for spotting orphans. */
function jrc_pl_all_keys( $list ) {
	$keys = array();
	foreach ( $list['sections'] as $sec ) {
		foreach ( $sec['items'] as $it ) {
			$keys[ $it['key'] ] = $sec['title'] . ' — ' . $it['name'];
		}
	}
	foreach ( $list['guide'] as $g ) {
		$keys[ $g['eot_key'] ]     = $list['guide_title'] . ' — ' . $g['size'] . ' (End of tenancy)';
		$keys[ $g['sparkle_key'] ] = $list['guide_title'] . ' — ' . $g['size'] . ' (Sparkle)';
	}
	foreach ( $list['addons'] as $it ) {
		$keys[ $it['key'] ] = 'Add-ons — ' . $it['name'];
	}
	return $keys;
}

/**
 * The percentage discount an account's price list carries.
 *
 * @return float 0–100
 */
function jrc_pl_percent_for_user( $user_id ) {
	$mode = (string) get_user_meta( $user_id, 'jrc_pl_mode', true );

	if ( 'standard' === $mode ) {
		return 0.0;
	}
	if ( 'percent' === $mode ) {
		return max( 0.0, min( 100.0, (float) get_user_meta( $user_id, 'jrc_pl_percent', true ) ) );
	}

	// Programme rate: what the account gets on the work it books, at its current tier.
	$profile = jrc_affiliate_profile( $user_id );
	$tier    = max( 1, min( 3, (int) get_user_meta( $user_id, 'jrc_affiliate_tier', true ) ?: 1 ) );
	$own     = jrc_affiliate_rates_for_tier( $profile, $tier );
	if ( $own ) {
		return (float) $own[0];
	}
	$tiers = function_exists( 'jrc_partner_tiers' ) ? jrc_partner_tiers() : array();
	return (float) preg_replace( '/[^0-9.]/', '', (string) ( $tiers[ $tier - 1 ]['customer'] ?? '' ) );
}

/** Apply a percentage and the configured rounding to one standard price. */
function jrc_pl_apply( $price, $pct, $rounding ) {
	if ( null === $price ) {
		return null;
	}
	$out = $price * ( 1 - $pct / 100 );
	if ( $pct > 0 && $rounding > 0 ) {
		$out = round( $out / $rounding ) * $rounding;
	}
	return round( $out, 2 );
}

/**
 * One account's price list: the standard list with its pricing applied.
 *
 * Every priced cell carries `standard` (the list price) and `price` (what this account
 * pays), so the screen and the PDF render from exactly the same figures.
 *
 * @return array The jrc_pl_standard() shape plus account, percent, note, date.
 */
function jrc_price_list_for_user( $user_id = 0 ) {
	$user_id   = $user_id ? $user_id : get_current_user_id();
	$list      = jrc_pl_standard();
	$pct       = jrc_pl_percent_for_user( $user_id );
	$overrides = (array) get_user_meta( $user_id, 'jrc_pl_overrides', true );
	$round     = $list['rounding'];

	$resolve = function ( $key, $standard ) use ( $overrides, $pct, $round ) {
		if ( isset( $overrides[ $key ] ) && '' !== $overrides[ $key ] ) {
			return array( (float) $overrides[ $key ], true );
		}
		return array( jrc_pl_apply( $standard, $pct, $round ), false );
	};

	foreach ( $list['sections'] as &$sec ) {
		foreach ( $sec['items'] as &$it ) {
			$it['standard']           = $it['price'];
			list( $it['price'], $it['agreed'] ) = $resolve( $it['key'], $it['standard'] );
		}
		unset( $it );
	}
	unset( $sec );

	foreach ( $list['guide'] as &$g ) {
		$g['eot_standard']     = $g['eot'];
		$g['sparkle_standard'] = $g['sparkle'];
		list( $g['eot'] )      = $resolve( $g['eot_key'], $g['eot_standard'] );
		list( $g['sparkle'] )  = $resolve( $g['sparkle_key'], $g['sparkle_standard'] );
	}
	unset( $g );

	foreach ( $list['addons'] as &$it ) {
		$it['standard']           = $it['price'];
		list( $it['price'], $it['agreed'] ) = $resolve( $it['key'], $it['standard'] );
	}
	unset( $it );

	$user = get_userdata( $user_id );
	$org  = $user ? ( get_user_meta( $user_id, 'billing_company', true ) ?: $user->display_name ) : '';

	$list['account']   = $org;
	$list['user_id']   = $user_id;
	$list['percent']   = $pct;
	$list['has_agreed'] = (bool) array_filter( $overrides, function ( $v ) { return '' !== $v; } );
	$list['note']      = trim( (string) get_user_meta( $user_id, 'jrc_pl_note', true ) );
	$list['date']      = date_i18n( 'F Y' );

	return apply_filters( 'jrc_price_list_for_user', $list, $user_id );
}

/** Format a price cell: "£120", "From £85", "£25 per hour", "POA". */
function jrc_pl_format( $price, $from = false, $unit = '' ) {
	if ( null === $price ) {
		return 'POA';
	}
	$out = jrc_affiliate_money( $price );
	if ( $from ) {
		$out = 'From ' . $out;
	}
	if ( $unit ) {
		$out .= ' ' . $unit;
	}
	return $out;
}

// ---------------------------------------------------------------------------
// PDF download
// ---------------------------------------------------------------------------

/**
 * Download URL. The nonce is per-user, so a forwarded link is useless to anyone else —
 * the PDF itself is what gets forwarded.
 *
 * @param int $user_id Staff only: whose list to render. Partners always get their own.
 */
function jrc_price_list_pdf_url( $user_id = 0 ) {
	$args = array( 'jrc_price_list' => 'pdf' );
	if ( $user_id ) {
		$args['user'] = (int) $user_id;
	}
	return wp_nonce_url( add_query_arg( $args, home_url( '/' ) ), 'jrc_price_list_pdf' );
}

add_action( 'template_redirect', function () {
	if ( empty( $_GET['jrc_price_list'] ) || 'pdf' !== $_GET['jrc_price_list'] ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'jrc_price_list_pdf' ) ) {
		wp_die( esc_html__( 'That download link has expired. Go back to your price list and try again.', 'jrc-partners' ), 403 );
	}

	// Staff may render any account's list (to check it before the partner sees it);
	// everyone else only ever gets their own, whatever `user` says.
	$user_id = get_current_user_id();
	if ( ! empty( $_GET['user'] ) && current_user_can( 'edit_user', (int) $_GET['user'] ) ) {
		$user_id = (int) $_GET['user'];
	} elseif ( ! jrc_is_affiliate( $user_id ) ) {
		wp_die( esc_html__( 'Price lists are available to partner accounts.', 'jrc-partners' ), 403 );
	}

	$list = jrc_price_list_for_user( $user_id );
	if ( jrc_pl_is_empty( $list ) ) {
		wp_die( esc_html__( 'Your price list is still being prepared. Please check back shortly.', 'jrc-partners' ), 404 );
	}

	$pdf = jrc_price_list_pdf( $list );
	if ( is_wp_error( $pdf ) ) {
		wp_die( esc_html( $pdf->get_error_message() ), 500 );
	}

	$name = sanitize_file_name( 'JR-Cleaning-price-list' . ( $list['account'] ? '-' . sanitize_title( $list['account'] ) : '' ) . '.pdf' );

	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode( $name ) );
	header( 'Content-Length: ' . strlen( $pdf ) );
	header( 'X-Content-Type-Options: nosniff' );
	echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput -- binary PDF.
	exit;
} );

/**
 * Render a price list to PDF bytes.
 *
 * dompdf ships in lib/dompdf (release build, dependencies included) so a deploy is a
 * file copy — there is no Composer step on this hosting. Remote fetching stays off:
 * the logo is inlined as a data URI and the fonts are local files inside the chroot.
 *
 * @return string|WP_Error
 */
function jrc_price_list_pdf( $list ) {
	$autoload = JRCP_DIR . 'lib/dompdf/autoload.inc.php';
	if ( ! file_exists( $autoload ) ) {
		return new WP_Error( 'no_dompdf', 'The PDF library is missing from the partner plugin.' );
	}
	require_once $autoload;

	// dompdf caches font metrics; that cache must be writable, and the plugin folder is
	// not guaranteed to be after a deploy.
	$up    = wp_upload_dir();
	$cache = trailingslashit( $up['basedir'] ) . 'jrc-pdf-cache';
	if ( ! is_dir( $cache ) ) {
		wp_mkdir_p( $cache );
		file_put_contents( $cache . '/index.php', "<?php // Silence.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
	}

	$options = new \Dompdf\Options();
	$options->setIsRemoteEnabled( false );
	$options->setIsPhpEnabled( false );
	$options->setFontDir( $cache );
	$options->setFontCache( $cache );
	$options->setTempDir( $cache );
	$options->setChroot( array( JRCP_DIR, $cache ) );
	$options->setDefaultFont( 'Hanken Grotesk' );
	$options->setDefaultPaperSize( 'A4' );

	$file = jrcp_locate_template( 'price-list-pdf.php' );
	if ( ! $file ) {
		return new WP_Error( 'no_template', 'The price list PDF template is missing.' );
	}

	$logo  = jrc_pl_logo_data_uri();
	$fonts = JRCP_DIR . 'assets/fonts/';
	ob_start();
	include $file;
	$html = ob_get_clean();

	try {
		$dompdf = new \Dompdf\Dompdf( $options );
		$dompdf->loadHtml( $html, 'UTF-8' );
		$dompdf->setPaper( 'A4', 'portrait' );
		$dompdf->render();

		// Page numbers, added after layout so the total is known.
		$canvas = $dompdf->getCanvas();
		$font   = $dompdf->getFontMetrics()->getFont( 'Hanken Grotesk' );
		$canvas->page_text( $canvas->get_width() - 92, $canvas->get_height() - 30, 'Page {PAGE_NUM} of {PAGE_COUNT}', $font, 8, array( 0.42, 0.44, 0.53 ) );

		return $dompdf->output();
	} catch ( \Throwable $e ) {
		return new WP_Error( 'pdf_failed', 'The price list PDF could not be generated: ' . $e->getMessage() );
	}
}

/** The white logo as a data URI, for the navy header band. */
function jrc_pl_logo_data_uri() {
	$candidates = array(
		get_stylesheet_directory() . '/images/jr-logo-white.png',
		get_template_directory() . '/images/jr-logo-white.png',
		JRCP_DIR . 'assets/jr-logo-white.png',
	);
	$candidates = apply_filters( 'jrc_price_list_logo_paths', $candidates );
	foreach ( $candidates as $path ) {
		if ( $path && file_exists( $path ) ) {
			return 'data:image/png;base64,' . base64_encode( file_get_contents( $path ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
	}
	return '';
}

// ---------------------------------------------------------------------------
// Front-end styles for the account tab
// ---------------------------------------------------------------------------

add_action( 'wp_enqueue_scripts', function () {
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() ) {
		return;
	}
	$css = JRCP_DIR . 'assets/partner-area.css';
	if ( file_exists( $css ) ) {
		wp_enqueue_style( 'jrcp-partner-area', JRCP_URL . 'assets/partner-area.css', array(), filemtime( $css ) );
	}
}, 20 );

// ---------------------------------------------------------------------------
// Per-account settings on the user profile
// ---------------------------------------------------------------------------

function jrc_pl_profile_fields( $user ) {
	if ( ! current_user_can( 'edit_users' ) || ! jrc_is_affiliate( $user->ID ) ) {
		return;
	}
	$mode      = (string) get_user_meta( $user->ID, 'jrc_pl_mode', true ) ?: 'programme';
	$percent   = get_user_meta( $user->ID, 'jrc_pl_percent', true );
	$note      = (string) get_user_meta( $user->ID, 'jrc_pl_note', true );
	$overrides = (array) get_user_meta( $user->ID, 'jrc_pl_overrides', true );
	$standard  = jrc_pl_standard();
	$keys      = jrc_pl_all_keys( $standard );
	$list      = jrc_price_list_for_user( $user->ID );

	// Index the account's resolved prices by key for the "currently" column.
	$now = array();
	foreach ( $list['sections'] as $sec ) {
		foreach ( $sec['items'] as $it ) {
			$now[ $it['key'] ] = array( $it['standard'], $it['price'] );
		}
	}
	foreach ( $list['guide'] as $g ) {
		$now[ $g['eot_key'] ]     = array( $g['eot_standard'], $g['eot'] );
		$now[ $g['sparkle_key'] ] = array( $g['sparkle_standard'], $g['sparkle'] );
	}
	foreach ( $list['addons'] as $it ) {
		$now[ $it['key'] ] = array( $it['standard'], $it['price'] );
	}
	$orphans = array_diff_key( array_filter( $overrides, function ( $v ) { return '' !== $v; } ), $keys );
	?>
	<h2 id="jrc-price-list">Price list</h2>
	<?php wp_nonce_field( 'jrc_pl_profile_' . $user->ID, 'jrc_pl_profile_nonce' ); ?>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="jrc_pl_mode">Pricing</label></th>
			<td>
				<select id="jrc_pl_mode" name="jrc_pl_mode">
					<option value="programme" <?php selected( $mode, 'programme' ); ?>>Programme discount (account type &amp; tier rate)</option>
					<option value="percent" <?php selected( $mode, 'percent' ); ?>>Custom percentage</option>
					<option value="standard" <?php selected( $mode, 'standard' ); ?>>Standard prices, no discount</option>
				</select>
				<input type="number" name="jrc_pl_percent" id="jrc_pl_percent" value="<?php echo esc_attr( $percent ); ?>" min="0" max="100" step="0.5" style="width:90px" /> %
				<p class="description">
					Currently <strong><?php echo esc_html( rtrim( rtrim( number_format( $list['percent'], 2 ), '0' ), '.' ) ); ?>% off</strong> the
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=jrc-price-list' ) ); ?>">standard price list</a>.
					The percentage box is only used with "Custom percentage". Individual prices below override it for that item.
				</p>
			</td>
		</tr>
		<tr>
			<th><label for="jrc_pl_note">Note on the list</label></th>
			<td>
				<input type="text" id="jrc_pl_note" name="jrc_pl_note" value="<?php echo esc_attr( $note ); ?>" class="large-text" placeholder="e.g. Rates agreed with Smith &amp; Co Lettings, valid to 31 March" />
				<p class="description">Optional. Shown on this account's price list and its PDF.</p>
			</td>
		</tr>
		<tr>
			<th>Preview</th>
			<td>
				<?php if ( jrc_pl_is_empty( $standard ) ) : ?>
					<p class="description">The standard price list is empty — add the services under <a href="<?php echo esc_url( admin_url( 'admin.php?page=jrc-price-list' ) ); ?>">Partner Programme → Price List</a> first.</p>
				<?php else : ?>
					<a class="button" href="<?php echo esc_url( jrc_price_list_pdf_url( $user->ID ) ); ?>">Download this account's PDF</a>
					<span class="description">Reflects the last saved settings.</span>
				<?php endif; ?>
			</td>
		</tr>
		<?php if ( $keys ) : ?>
		<tr>
			<th>Individual prices</th>
			<td>
				<details <?php echo $list['has_agreed'] ? 'open' : ''; ?>>
					<summary style="cursor:pointer">Set an agreed price for specific items (<?php echo esc_html( count( array_intersect_key( $overrides, $keys ) ) ); ?> set)</summary>
					<table class="widefat striped" style="max-width:820px;margin-top:10px">
						<thead><tr><th>Item</th><th>Standard</th><th>This account</th><th>Agreed price</th></tr></thead>
						<tbody>
						<?php foreach ( $keys as $key => $label ) :
							$std = $now[ $key ][0] ?? null;
							$cur = $now[ $key ][1] ?? null;
							?>
							<tr>
								<td><?php echo esc_html( $label ); ?></td>
								<td><?php echo esc_html( jrc_pl_format( $std ) ); ?></td>
								<td><?php echo esc_html( jrc_pl_format( $cur ) ); ?></td>
								<td>£ <input type="number" name="jrc_pl_overrides[<?php echo esc_attr( $key ); ?>]" value="<?php echo esc_attr( $overrides[ $key ] ?? '' ); ?>" min="0" step="0.01" style="width:100px" /></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description">Leave blank to use the percentage. Agreed prices are shown as-is, without rounding.</p>
				</details>
				<?php if ( $orphans ) : ?>
					<div class="notice notice-warning inline" style="margin-top:10px"><p>
						<strong><?php echo esc_html( count( $orphans ) ); ?> agreed price(s) no longer match an item</strong> on the standard list — probably renamed or removed. They are not shown to the partner.
						Saving this profile discards them. (<?php echo esc_html( implode( ', ', array_keys( $orphans ) ) ); ?>)
					</p></div>
				<?php endif; ?>
			</td>
		</tr>
		<?php endif; ?>
	</table>
	<?php
}
add_action( 'show_user_profile', 'jrc_pl_profile_fields', 20 );
add_action( 'edit_user_profile', 'jrc_pl_profile_fields', 20 );

function jrc_pl_save_profile( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) || ! current_user_can( 'edit_users' ) || ! isset( $_POST['jrc_pl_mode'] ) ) {
		return;
	}
	if ( ! isset( $_POST['jrc_pl_profile_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jrc_pl_profile_nonce'] ) ), 'jrc_pl_profile_' . $user_id ) ) {
		return;
	}
	$mode = sanitize_key( wp_unslash( $_POST['jrc_pl_mode'] ) );
	update_user_meta( $user_id, 'jrc_pl_mode', in_array( $mode, array( 'programme', 'percent', 'standard' ), true ) ? $mode : 'programme' );

	$pct = isset( $_POST['jrc_pl_percent'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['jrc_pl_percent'] ) ) ) : '';
	update_user_meta( $user_id, 'jrc_pl_percent', '' === $pct ? '' : max( 0, min( 100, (float) $pct ) ) );

	update_user_meta( $user_id, 'jrc_pl_note', sanitize_text_field( wp_unslash( $_POST['jrc_pl_note'] ?? '' ) ) );

	// Only keys the standard list still has survive a save, which is what clears orphans.
	$valid = jrc_pl_all_keys( jrc_pl_standard() );
	$in    = isset( $_POST['jrc_pl_overrides'] ) && is_array( $_POST['jrc_pl_overrides'] ) ? wp_unslash( $_POST['jrc_pl_overrides'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each value cast below.
	$out   = array();
	foreach ( $in as $key => $val ) {
		$key = sanitize_text_field( $key );
		$val = trim( (string) $val );
		if ( isset( $valid[ $key ] ) && '' !== $val && is_numeric( $val ) ) {
			$out[ $key ] = round( max( 0, (float) $val ), 2 );
		}
	}
	update_user_meta( $user_id, 'jrc_pl_overrides', $out );
}
add_action( 'personal_options_update', 'jrc_pl_save_profile' );
add_action( 'edit_user_profile_update', 'jrc_pl_save_profile' );
