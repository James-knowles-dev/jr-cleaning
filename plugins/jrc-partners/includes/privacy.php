<?php
/**
 * Personal data: export, erasure, and the privacy-policy text.
 *
 * The jobs table holds two kinds of personal data:
 *
 *   1. Partner accounts' own work — addresses and scopes of cleans they booked. That is
 *      a business record about a WordPress user, exported for them on request but not
 *      erased: invoices and tier history depend on it, so an erasure request retains it
 *      and says so (WordPress shows the reason to the administrator).
 *
 *   2. Website enquiries (source 'quote') from members of the public — their contact
 *      details live in external_meta, the property address on the row. These are
 *      erasable: the contact details are removed and the address replaced.
 *
 * Both hooks run from Tools → Export / Erase Personal Data, keyed on email address.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Rows handled per page by the exporter and eraser. */
const JRC_PRIVACY_PAGE = 100;

/**
 * Website enquiries whose stored contact email matches.
 *
 * The LIKE narrows the scan; the exact comparison on the decoded meta is what decides,
 * so "ann@x.com" never matches "joann@x.com".
 *
 * @return object[]
 */
function jrc_privacy_enquiries_for( $email, $page ) {
	global $wpdb;

	$email = strtolower( trim( (string) $email ) );
	if ( '' === $email ) {
		return array();
	}

	$rows = $wpdb->get_results( $wpdb->prepare(
		'SELECT * FROM ' . jrc_jobs_table() . " WHERE source = 'quote' AND external_meta LIKE %s ORDER BY id ASC LIMIT %d OFFSET %d",
		'%' . $wpdb->esc_like( $email ) . '%',
		JRC_PRIVACY_PAGE,
		( max( 1, (int) $page ) - 1 ) * JRC_PRIVACY_PAGE
	) );

	return array_values( array_filter( (array) $rows, function ( $r ) use ( $email ) {
		$m = jrc_job_meta( $r );
		return isset( $m['contact_email'] ) && strtolower( (string) $m['contact_email'] ) === $email;
	} ) );
}

/** Exporter. */
function jrc_privacy_export( $email, $page = 1 ) {
	$items = array();
	$done  = true;

	// Partner account: their own job records.
	$user = get_user_by( 'email', $email );
	if ( $user && jrc_is_affiliate( $user->ID ) ) {
		$jobs = jrc_jobs_query( array(
			'user_id' => $user->ID,
			'limit'   => JRC_PRIVACY_PAGE,
			'offset'  => ( max( 1, (int) $page ) - 1 ) * JRC_PRIVACY_PAGE,
			'orderby' => 'id',
			'order'   => 'ASC',
		) );
		foreach ( (array) $jobs as $j ) {
			$items[] = array(
				'group_id'    => 'jrc-partner-jobs',
				'group_label' => __( 'Partner jobs', 'jrc-partners' ),
				'item_id'     => 'jrc-job-' . (int) $j->id,
				'data'        => array(
					array( 'name' => __( 'Date of clean', 'jrc-partners' ), 'value' => (string) $j->job_date ),
					array( 'name' => __( 'Scheduled', 'jrc-partners' ), 'value' => (string) $j->scheduled_date ),
					array( 'name' => __( 'Service', 'jrc-partners' ), 'value' => (string) $j->job_type ),
					array( 'name' => __( 'Address', 'jrc-partners' ), 'value' => (string) $j->address ),
					array( 'name' => __( 'Scope', 'jrc-partners' ), 'value' => (string) $j->scope ),
					array( 'name' => __( 'Status', 'jrc-partners' ), 'value' => (string) $j->status ),
				),
			);
		}
		if ( count( (array) $jobs ) === JRC_PRIVACY_PAGE ) {
			$done = false;
		}
	}

	// Website enquiries made with this email.
	foreach ( jrc_privacy_enquiries_for( $email, $page ) as $r ) {
		$m       = jrc_job_meta( $r );
		$items[] = array(
			'group_id'    => 'jrc-enquiries',
			'group_label' => __( 'Website enquiries', 'jrc-partners' ),
			'item_id'     => 'jrc-job-' . (int) $r->id,
			'data'        => array(
				array( 'name' => __( 'Received', 'jrc-partners' ), 'value' => (string) $r->created_at ),
				array( 'name' => __( 'Service', 'jrc-partners' ), 'value' => (string) $r->job_type ),
				array( 'name' => __( 'Property address', 'jrc-partners' ), 'value' => (string) $r->address ),
				array( 'name' => __( 'Contact details', 'jrc-partners' ), 'value' => (string) ( $m['contact'] ?? '' ) ),
				array( 'name' => __( 'Notes', 'jrc-partners' ), 'value' => (string) ( $m['enquiry_notes'] ?? '' ) ),
				array( 'name' => __( 'Status', 'jrc-partners' ), 'value' => (string) $r->status ),
			),
		);
	}
	// The filtered page can come back short even when more rows exist, so continue
	// whenever the unfiltered scan could have had more.
	if ( jrc_privacy_scan_was_full( $email, $page ) ) {
		$done = false;
	}

	return array( 'data' => $items, 'done' => $done );
}

/** Did the LIKE scan for this page return a full page (so another may follow)? */
function jrc_privacy_scan_was_full( $email, $page ) {
	global $wpdb;
	$n = (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM ' . jrc_jobs_table() . " WHERE source = 'quote' AND external_meta LIKE %s",
		'%' . $wpdb->esc_like( strtolower( trim( (string) $email ) ) ) . '%'
	) );
	return $n > max( 1, (int) $page ) * JRC_PRIVACY_PAGE;
}

/** Eraser. */
function jrc_privacy_erase( $email, $page = 1 ) {
	global $wpdb;

	$removed  = false;
	$retained = false;
	$messages = array();

	// Always page 1: each erased row stops matching, so the next batch slides up.
	foreach ( jrc_privacy_enquiries_for( $email, 1 ) as $r ) {
		$m = jrc_job_meta( $r );
		unset( $m['contact'], $m['contact_email'], $m['enquiry_notes'] );

		$wpdb->update(
			jrc_jobs_table(),
			array(
				'address'       => '[removed at the enquirer\'s request]',
				'external_meta' => $m ? wp_json_encode( $m ) : null,
				'updated_at'    => current_time( 'mysql' ),
			),
			array( 'id' => (int) $r->id )
		);
		$removed = true;
	}

	$user = get_user_by( 'email', $email );
	if ( $user && jrc_is_affiliate( $user->ID ) && 1 === (int) $page ) {
		$retained   = true;
		$messages[] = __( 'Partner job records were retained: they are business records needed for invoicing and tier history. Remove them manually from the user profile if they are no longer required.', 'jrc-partners' );
	}

	return array(
		'items_removed'  => $removed,
		'items_retained' => $retained,
		'messages'       => $messages,
		'done'           => ! jrc_privacy_enquiries_for( $email, 1 ),
	);
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
	$exporters['jrc-partners'] = array(
		'exporter_friendly_name' => __( 'JR Cleaning jobs and enquiries', 'jrc-partners' ),
		'callback'               => 'jrc_privacy_export',
	);
	return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
	$erasers['jrc-partners'] = array(
		'eraser_friendly_name' => __( 'JR Cleaning jobs and enquiries', 'jrc-partners' ),
		'callback'             => 'jrc_privacy_erase',
	);
	return $erasers;
} );

/** Suggested privacy-policy text (Settings → Privacy → Policy Guide). */
add_action( 'admin_init', function () {
	if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
		return;
	}
	wp_add_privacy_policy_content(
		__( 'JR Cleaning Partner Platform', 'jrc-partners' ),
		wp_kses_post( wpautop( __(
			"When you send an enquiry through our website we store the property address, the service you asked about and your contact details so we can quote and book the work.\n\n" .
			"If your enquiry arrives through a referral link or with a referral code from one of our partners (for example an estate agent), the partner can see that an enquiry was made for that property and its status. Your name, phone number, email address and notes are not shared with the partner.\n\n" .
			"Partners who hold an account with us can see the jobs they booked or referred, including property addresses and the photo reports for those cleans.",
			'jrc-partners'
		) ) )
	);
} );

/**
 * One-off: move contact details out of `scope` on enquiries stored before the pipeline
 * started writing them to external_meta. Partners can see scope; they should never
 * have seen these lines. Direct update rather than jrc_job_update(): nothing about the
 * job has changed, so it must not appear on the timeline.
 */
add_action( 'admin_init', function () {
	if ( get_option( 'jrc_quote_privacy_migrated' ) ) {
		return;
	}

	global $wpdb;
	$rows = $wpdb->get_results(
		'SELECT id, scope, external_meta FROM ' . jrc_jobs_table() . " WHERE source = 'quote' AND ( scope LIKE '%Contact: %' OR scope LIKE '%Notes: %' )"
	);

	foreach ( (array) $rows as $r ) {
		$keep    = array();
		$contact = '';
		$notes   = array();
		$in_note = false;

		foreach ( preg_split( '/\r\n|\r|\n/', (string) $r->scope ) as $line ) {
			if ( $in_note ) {
				// Notes were always the last part of the scope, and can span lines.
				$notes[] = $line;
			} elseif ( 0 === strpos( $line, 'Contact: ' ) ) {
				$contact = substr( $line, 9 );
			} elseif ( 0 === strpos( $line, 'Notes: ' ) ) {
				$notes[] = substr( $line, 7 );
				$in_note = true;
			} else {
				$keep[] = $line;
			}
		}

		$m = jrc_job_meta( $r );
		if ( '' !== $contact ) {
			$m['contact'] = $contact;
			if ( preg_match( '/[^\s·]+@[^\s·]+/', $contact, $em ) ) {
				$m['contact_email'] = strtolower( sanitize_email( $em[0] ) );
			}
		}
		if ( $notes ) {
			$m['enquiry_notes'] = sanitize_textarea_field( implode( "\n", $notes ) );
		}

		$wpdb->update(
			jrc_jobs_table(),
			array(
				'scope'         => implode( "\n", $keep ),
				'external_meta' => $m ? wp_json_encode( map_deep( $m, 'sanitize_text_field' ) ) : null,
			),
			array( 'id' => (int) $r->id )
		);
	}

	update_option( 'jrc_quote_privacy_migrated', 1, false );
} );
