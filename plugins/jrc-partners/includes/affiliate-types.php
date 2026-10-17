<?php
/**
 * Account types and sub-types for the partner programme.
 *
 * The programme used to be a single channel (estate agents) with three tiers. It now
 * has three channels, each with sub-types that behave differently: some are tiered,
 * some carry a flat rate, some are excluded from the December draw, one gets nothing
 * at all. Rather than scatter those rules through the dashboard templates, every
 * behavioural difference is declared once in jrc_affiliate_subtypes() and read back
 * through jrc_affiliate_profile().
 *
 * Numeric values come from the Partner Programme Settings page, never from here.
 *
 * User meta:
 *   jrc_affiliate_channel  estate-agents | property-management | holiday-let-management
 *   jrc_affiliate_subtype  one of the sub-type slugs below
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Every sub-type, keyed by channel then sub-type slug.
 *
 * Keys per sub-type:
 *   label      Admin/display name.
 *   tiered     Uses the three-tier progression and its thresholds.
 *   rates      null to inherit the tier's percentages; array(customer, affiliate) for
 *              a flat rate that ignores tier; or array(1 => array(c, a), 2 => …, 3 => …)
 *              for a tiered account that progresses at its own percentages.
 *   payout     What the second rate means: 'discount' (off cleans at the account
 *              holder's own home — the Estate Agent model), 'commission' (cash on
 *              completed work booked through the programme) or 'none'.
 *   december   Whether the account is eligible for the 1 December prize draw.
 *   free_bonus Whether the "chosen free bonus" selector applies.
 *   view       Dashboard layout, see jrc_affiliate_view_sections():
 *              full | simple | commission | discount | basic.
 *   default    Marks the sub-type assigned when none is stored.
 */
function jrc_affiliate_subtypes( $channel = '' ) {
	$blacklist_all = 'all' === jrc_aff_setting( 'aff_blacklist_scope' );

	$all = array(

		'estate-agents' => array(
			'standard' => array(
				'label'      => 'Standard',
				'tiered'     => true,
				'rates'      => null,
				'payout'     => 'discount',
				'december'   => true,
				'free_bonus' => true,
				'view'       => 'full',
				'default'    => true,
			),
			'blacklisted' => array(
				'label' => 'Blacklisted',
				// Scope is a client decision, so it is a setting rather than a guess:
				// "free_bonus" withdraws only the new perk, "all" withdraws the tier
				// discounts too, which is why `tiered` and `rates` follow it.
				'tiered'     => ! $blacklist_all,
				'rates'      => $blacklist_all ? array( 0, 0 ) : null,
				'payout'     => $blacklist_all ? 'none' : 'discount',
				'december'   => (bool) jrc_aff_setting( 'aff_blacklist_december' ),
				'free_bonus' => false,
				'view'       => 'full',
				'note'       => 'This account is not eligible for the free bonus.',
			),
		),

		'property-management' => array(
			'small' => array(
				'label'      => 'Small',
				'tiered'     => false,
				'rates'      => array( jrc_aff_setting_num( 'aff_pm_small_customer' ), jrc_aff_setting_num( 'aff_pm_small_affiliate' ) ),
				'payout'     => 'discount',
				'december'   => (bool) jrc_aff_setting( 'aff_pm_small_december' ),
				'free_bonus' => false,
				'view'       => 'simple',
				'default'    => true,
			),
			'large' => array(
				'label'      => 'Large',
				'tiered'     => false,
				'rates'      => array( jrc_aff_setting_num( 'aff_pm_large_customer' ), jrc_aff_setting_num( 'aff_pm_large_affiliate' ) ),
				'payout'     => 'discount',
				'december'   => (bool) jrc_aff_setting( 'aff_pm_large_december' ),
				'free_bonus' => false,
				'view'       => 'full',
			),
			'commission' => array(
				'label' => 'Commission',
				// Earns cash on completed work booked through the programme. The second
				// rate is the commission, not an own-home discount.
				'tiered'     => false,
				'rates'      => array( jrc_aff_setting_num( 'aff_pm_comm_customer' ), jrc_aff_setting_num( 'aff_pm_comm_rate' ) ),
				'payout'     => 'commission',
				'december'   => (bool) jrc_aff_setting( 'aff_pm_comm_december' ),
				'free_bonus' => false,
				'view'       => 'commission',
			),
			'discount' => array(
				'label' => 'Discount',
				// Discounted pricing on the work it books, in place of any commission.
				'tiered'     => false,
				'rates'      => array( jrc_aff_setting_num( 'aff_pm_disc_customer' ), 0 ),
				'payout'     => 'none',
				'december'   => (bool) jrc_aff_setting( 'aff_pm_disc_december' ),
				'free_bonus' => false,
				'view'       => 'discount',
			),
		),

		'holiday-let-management' => array(
			'single-owner' => array(
				'label'      => 'Single Owner',
				'tiered'     => false,
				'rates'      => array( 0, 0 ),
				'payout'     => 'none',
				'december'   => false,
				'free_bonus' => false,
				'view'       => 'basic',
				'default'    => true,
				'note'       => 'Single Owner accounts see their package and job history. No discounts apply.',
			),
			'management-company' => array(
				'label'      => 'Management Company',
				// Tiered like Estate Agents, at its own per-tier percentages.
				'tiered'     => true,
				'rates'      => jrc_aff_tier_rates( 'aff_hl_mgmt' ),
				'payout'     => 'discount',
				'december'   => true,
				'free_bonus' => false,
				'view'       => 'full',
			),
			'portfolio-owner' => array(
				'label' => 'Portfolio Owner',
				// Mirrors the Management Company structure but at its own percentages,
				// and is deliberately excluded from the December draw.
				'tiered'     => true,
				'rates'      => jrc_aff_tier_rates( 'aff_hl_portfolio' ),
				'payout'     => 'discount',
				'december'   => false,
				'free_bonus' => false,
				'view'       => 'full',
			),
		),

		// Commercial clients (office blocks, shops, schools): a customer account, not a
		// referral partner. No tiers, discounts, commission, free bonus or December draw —
		// just their reports, calendar and the request form.
		'commercial' => array(
			'standard' => array(
				'label'      => 'Commercial client',
				'tiered'     => false,
				'rates'      => array( 0, 0 ),
				'payout'     => 'none',
				'december'   => false,
				'free_bonus' => false,
				'view'       => 'commercial',
				'default'    => true,
				'hide_tabs'  => array( 'referred-jobs', 'price-list' ),
			),
		),

		// Schools and colleges: a commercial-style client account (no tiers, discounts,
		// commission or December draw) with a Site map of its buildings, each showing when
		// and how often it is cleaned. See school-sites.php.
		'education' => array(
			'school' => array(
				'label'      => 'School',
				'tiered'     => false,
				'rates'      => array( 0, 0 ),
				'payout'     => 'none',
				'december'   => false,
				'free_bonus' => false,
				'view'       => 'school',
				'default'    => true,
				'hide_tabs'  => array( 'referred-jobs', 'price-list' ),
			),
		),
	);

	$all = apply_filters( 'jrc_affiliate_subtypes', $all );

	if ( $channel ) {
		return isset( $all[ $channel ] ) ? $all[ $channel ] : array();
	}
	return $all;
}

/** The sub-type applied when an account has none stored. */
function jrc_affiliate_default_subtype( $channel ) {
	foreach ( jrc_affiliate_subtypes( $channel ) as $slug => $def ) {
		if ( ! empty( $def['default'] ) ) {
			return $slug;
		}
	}
	$keys = array_keys( jrc_affiliate_subtypes( $channel ) );
	return $keys ? $keys[0] : '';
}

/** Stored channel, falling back to estate agents (the only channel before this phase). */
function jrc_affiliate_channel( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$channel = (string) get_user_meta( $user_id, 'jrc_affiliate_channel', true );
	$known   = array_keys( jrc_affiliate_subtypes() );
	return in_array( $channel, $known, true ) ? $channel : 'estate-agents';
}

/** Stored sub-type, validated against the channel. */
function jrc_affiliate_subtype( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$channel = jrc_affiliate_channel( $user_id );
	$subtype = (string) get_user_meta( $user_id, 'jrc_affiliate_subtype', true );
	$valid   = jrc_affiliate_subtypes( $channel );

	// An account whose channel changed can hold a sub-type that no longer applies;
	// fall back rather than render a dashboard from a mismatched definition.
	return isset( $valid[ $subtype ] ) ? $subtype : jrc_affiliate_default_subtype( $channel );
}

/**
 * Resolved behaviour for one account. This is what templates and bonus logic read.
 *
 * @return array channel, subtype, label, tiered, rates, payout, december, free_bonus, view, note
 */
function jrc_affiliate_profile( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$channel = jrc_affiliate_channel( $user_id );
	$subtype = jrc_affiliate_subtype( $user_id );
	$defs    = jrc_affiliate_subtypes( $channel );
	$def     = isset( $defs[ $subtype ] ) ? $defs[ $subtype ] : array();

	return array(
		'channel'       => $channel,
		'channel_label' => jrc_account_channel_name( $channel ),
		'subtype'       => $subtype,
		'label'         => $def['label'] ?? '',
		'tiered'        => (bool) ( $def['tiered'] ?? true ),
		'rates'         => $def['rates'] ?? null,
		'payout'        => $def['payout'] ?? 'discount',
		'december'      => (bool) ( $def['december'] ?? false ),
		'free_bonus'    => (bool) ( $def['free_bonus'] ?? false ),
		'view'          => $def['view'] ?? 'full',
		'note'          => $def['note'] ?? '',
		'hide_tabs'     => (array) ( $def['hide_tabs'] ?? array() ),
	);
}

/** Convenience predicates, so templates don't re-derive the profile each time. */
function jrc_affiliate_has_free_bonus( $user_id = 0 ) {
	$p = jrc_affiliate_profile( $user_id );
	return $p['free_bonus'];
}

function jrc_affiliate_in_december_draw( $user_id = 0 ) {
	$p = jrc_affiliate_profile( $user_id );
	return $p['december'];
}

/** Flat sub-type list for admin dropdowns: "Estate Agents — Blacklisted". */
function jrc_affiliate_subtype_choices() {
	$out = array();
	foreach ( jrc_affiliate_subtypes() as $channel => $subs ) {
		$cl = jrc_account_channel_name( $channel );
		foreach ( $subs as $slug => $def ) {
			$out[ $channel . ':' . $slug ] = $cl . ' — ' . $def['label'];
		}
	}
	return $out;
}

/**
 * Per-tier rates for a tiered sub-type with its own percentages.
 *
 * Reads {$prefix}_t{n}_customer / _affiliate. A blank tier inherits the tier below, and
 * Tier 1 falls back to the original flat {$prefix}_customer / _affiliate settings, so an
 * account that was on a flat rate keeps exactly that rate until someone fills in the
 * higher tiers.
 *
 * @return array 1 => array( customer, affiliate ), 2 => …, 3 => …
 */
function jrc_aff_tier_rates( $prefix ) {
	$out  = array();
	$prev = array(
		jrc_aff_setting_num( $prefix . '_customer' ),
		jrc_aff_setting_num( $prefix . '_affiliate' ),
	);
	for ( $t = 1; $t <= 3; $t++ ) {
		$c = jrc_aff_setting( "{$prefix}_t{$t}_customer" );
		$a = jrc_aff_setting( "{$prefix}_t{$t}_affiliate" );
		$prev = array(
			( null === $c || '' === $c ) ? $prev[0] : (float) $c,
			( null === $a || '' === $a ) ? $prev[1] : (float) $a,
		);
		$out[ $t ] = $prev;
	}
	return $out;
}

/**
 * The (customer, affiliate) percentages a profile carries at a given tier.
 *
 * @return array|null array( customer, affiliate ), or null when the account inherits
 *                    the Estate Agent tier percentages.
 */
function jrc_affiliate_rates_for_tier( $profile, $tier ) {
	$rates = $profile['rates'] ?? null;
	if ( ! is_array( $rates ) ) {
		return null;
	}
	// Per-tier map: keyed 1..3.
	if ( isset( $rates[1] ) && is_array( $rates[1] ) ) {
		$tier = max( 1, min( 3, (int) $tier ) );
		return array_map( 'floatval', $rates[ $tier ] );
	}
	return array_map( 'floatval', $rates );
}

/**
 * Which dashboard sections each view shows, in order.
 *
 * The dashboard template renders a section only when its key is listed here, so every
 * account type's view is declared in one place rather than as conditions scattered
 * through the markup. Tier-only sections (expiry, progress) additionally require the
 * account to be tiered.
 *
 *   rates     Tier / rate card          code      Referral code card
 *   share     Referral link + message   stats     Job counters
 *   earnings  Commission earned         savings   Money saved on discounted work
 *   expiry    Twelve-month expiry       progress  Progress to next tier
 *   bonus     Free bonus / note         december  December perks
 *   package   Holiday-let package       property  Cleans carried out at the property
 *   how       FAQ                       cta       Request-a-clean strip
 */
function jrc_affiliate_view_sections( $view ) {
	$views = array(
		'full'       => array( 'rates', 'code', 'share', 'stats', 'expiry', 'progress', 'bonus', 'december', 'how', 'cta' ),
		'simple'     => array( 'rates', 'code', 'stats', 'bonus', 'december', 'how', 'cta' ),
		'commission' => array( 'rates', 'code', 'share', 'earnings', 'bonus', 'december', 'how', 'cta' ),
		'discount'   => array( 'rates', 'code', 'share', 'savings', 'bonus', 'december', 'how', 'cta' ),
		'basic'      => array( 'package', 'property', 'bonus', 'cta' ),
		'commercial' => array( 'property', 'cta' ),
		'school'     => array( 'buildings', 'property', 'cta' ),
	);
	$views = apply_filters( 'jrc_affiliate_view_sections', $views );
	return $views[ $view ] ?? $views['full'];
}

/**
 * The package a Single Owner holiday-let account is on, as set on their user profile.
 *
 * @return array name, lines (array of strings), frequency
 */
function jrc_affiliate_package( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$lines   = (string) get_user_meta( $user_id, 'jrc_affiliate_package_items', true );
	return array(
		'name'      => (string) get_user_meta( $user_id, 'jrc_affiliate_package_name', true ),
		'frequency' => (string) get_user_meta( $user_id, 'jrc_affiliate_package_frequency', true ),
		'lines'     => array_values( array_filter( array_map( 'trim', preg_split( '/\R/', $lines ) ) ) ),
	);
}

/**
 * What the partner area is called for this account.
 *
 * The tab used to read "Estate Agent Bonus Program" for every account, which was wrong
 * for a holiday-let owner who has no bonus at all.
 *
 * @return array label (nav), title (page heading)
 */
function jrc_affiliate_area_names( $user_id = 0 ) {
	$p = jrc_affiliate_profile( $user_id );

	if ( 'basic' === $p['view'] ) {
		$names = array( 'label' => 'My property', 'title' => 'Your holiday let' );
	} elseif ( 'commercial' === $p['view'] ) {
		$names = array( 'label' => 'My account', 'title' => 'Your cleaning account' );
	} elseif ( 'school' === $p['view'] ) {
		$names = array( 'label' => 'My school', 'title' => 'Your school' );
	} elseif ( 'estate-agents' === $p['channel'] ) {
		$names = array( 'label' => 'Bonus Program', 'title' => 'Estate Agent Bonus Program' );
	} elseif ( 'commission' === $p['payout'] ) {
		$names = array( 'label' => 'Partner account', 'title' => 'Your commission' );
	} else {
		$names = array( 'label' => 'Partner account', 'title' => $p['channel_label'] . ' partner account' );
	}
	return apply_filters( 'jrc_affiliate_area_names', $names, $p );
}

/**
 * Display name for an account channel.
 *
 * The partner marketing channels (jrc_partner_channels(), editable in Theme Settings)
 * name the referral channels. Account-only channels such as Commercial are deliberately
 * not marketing channels — they must not appear on the public partner pages — so they
 * are named here instead.
 */
function jrc_account_channel_name( $slug ) {
	$channels = function_exists( 'jrc_partner_channels' ) ? jrc_partner_channels() : array();
	if ( ! empty( $channels[ $slug ]['name'] ) ) {
		return $channels[ $slug ]['name'];
	}
	$own = apply_filters( 'jrc_account_only_channels', array( 'commercial' => 'Commercial', 'education' => 'Schools & colleges' ) );
	return $own[ $slug ] ?? ucwords( str_replace( '-', ' ', (string) $slug ) );
}

/** Every channel an account can be on, slug => name, marketing ones first. */
function jrc_account_channel_choices() {
	$out = array();
	foreach ( array_keys( jrc_affiliate_subtypes() ) as $slug ) {
		$out[ $slug ] = jrc_account_channel_name( $slug );
	}
	return $out;
}

