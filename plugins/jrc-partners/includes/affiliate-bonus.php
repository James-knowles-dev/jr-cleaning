<?php
/**
 * "Your chosen free bonus" — Estate Agent accounts pick one perk that applies to every
 * full end-of-tenancy clean they book.
 *
 * The spec has this auto-applied as a £0 line item at WooCommerce checkout. That isn't
 * possible here: cleans are not sold through the site, there is no cart for them, and
 * WooCommerce carries no orders at all. So the choice is stored and then surfaced at
 * the three points where a person actually acts on it —
 *
 *   1. the affiliate's own dashboard, so they can see and change it;
 *   2. the quote-wizard notification, when a quote comes in under their referral code,
 *      so whoever prices it knows the bonus is owed before quoting;
 *   3. the job record in admin, so whoever schedules the clean sees it.
 *
 * If cleans ever do become purchasable, jrc_free_bonus_for_user() is the single call a
 * checkout hook would need.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** The three perks an Estate Agent can choose between. */
function jrc_free_bonus_options() {
	return apply_filters( 'jrc_free_bonus_options', array(
		'oven'    => 'Oven cleaning',
		'windows' => 'External window cleaning',
		'sparkle' => 'Post-viewing sparkle clean',
	) );
}

/**
 * The stored choice for a user, or '' when none is set or the account isn't eligible.
 *
 * Eligibility is re-checked on read rather than only on save: an account moved to
 * Blacklisted after choosing a bonus must stop receiving it immediately, without
 * anyone having to remember to clear the field.
 */
function jrc_free_bonus_for_user( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id || ! jrc_affiliate_has_free_bonus( $user_id ) ) {
		return '';
	}
	$choice = (string) get_user_meta( $user_id, 'jrc_affiliate_free_bonus', true );
	return array_key_exists( $choice, jrc_free_bonus_options() ) ? $choice : '';
}

/** Human label for a stored choice. */
function jrc_free_bonus_label( $key ) {
	$opts = jrc_free_bonus_options();
	return $opts[ $key ] ?? '';
}

/** Save from the affiliate's own dashboard. */
add_action( 'template_redirect', function () {
	if ( empty( $_POST['jrc_free_bonus_nonce'] ) ) {
		return;
	}
	$uid = get_current_user_id();
	if ( ! $uid || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jrc_free_bonus_nonce'] ) ), 'jrc_free_bonus_' . $uid ) ) {
		return;
	}
	if ( ! jrc_affiliate_has_free_bonus( $uid ) ) {
		return; // Blacklisted accounts cannot set one, even by posting directly.
	}

	$choice = sanitize_key( wp_unslash( $_POST['jrc_free_bonus'] ?? '' ) );
	if ( array_key_exists( $choice, jrc_free_bonus_options() ) ) {
		update_user_meta( $uid, 'jrc_affiliate_free_bonus', $choice );
	}

	wp_safe_redirect( add_query_arg( 'bonus-saved', '1', wc_get_account_endpoint_url( 'affiliate' ) ) );
	exit;
} );

/**
 * Find the affiliate behind a referral code, so an incoming quote can be attributed.
 *
 * @return int User ID, or 0.
 */
function jrc_affiliate_by_code( $code ) {
	$code = trim( (string) $code );
	if ( '' === $code ) {
		return 0;
	}
	$users = get_users( array(
		'meta_key'   => 'jrc_affiliate_code',
		'meta_value' => $code,
		'number'     => 1,
		'fields'     => 'ID',
	) );
	$uid = $users ? (int) $users[0] : 0;

	// Only an approved partner. A code left behind on an account whose approval was
	// withdrawn must not keep collecting enquiries (with the customer's contact details)
	// or Connecteam jobs; unmatched work stays unattributed for the office to deal with.
	return ( $uid && jrc_is_affiliate( $uid ) ) ? $uid : 0;
}

/**
 * Append the owed bonus to the quote-wizard notification.
 *
 * This is the closest thing the site has to "applied at checkout": the moment an
 * enquiry arrives carrying a referral code, whoever prices it is told what is owed.
 */
add_filter( 'gform_notification', function ( $notification, $form, $entry ) {
	if ( empty( $entry ) || ! is_array( $notification ) ) {
		return $notification;
	}

	// Look for a referral code in any field on the entry.
	$code = '';
	foreach ( (array) $form['fields'] as $field ) {
		$label = strtolower( (string) ( $field->label ?? '' ) );
		if ( false !== strpos( $label, 'referral' ) || false !== strpos( $label, 'referral code' ) ) {
			$code = trim( (string) rgar( $entry, (string) $field->id ) );
			break;
		}
	}
	if ( '' === $code ) {
		return $notification;
	}

	$uid = jrc_affiliate_by_code( $code );
	if ( ! $uid ) {
		return $notification;
	}

	$bonus = jrc_free_bonus_for_user( $uid );
	$user  = get_userdata( $uid );
	$lines = array(
		'Referred by: ' . ( $user ? $user->display_name : '#' . $uid ) . ' (' . $code . ')',
	);
	if ( $bonus ) {
		$lines[] = 'Free bonus owed on a full end of tenancy clean: ' . jrc_free_bonus_label( $bonus );
	}

	$notification['message'] .= "\n\n<hr />\n<p><strong>" . implode( '<br />', array_map( 'esc_html', $lines ) ) . '</strong></p>';
	return $notification;
}, 10, 3 );
