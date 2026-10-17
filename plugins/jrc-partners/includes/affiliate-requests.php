<?php
/**
 * "Request a clean": letting a partner book work from their own account.
 *
 * Until now the referral loop was open at the front. A partner got a ?ref= link to hand
 * to a customer, and their account showed them work that had already happened — but an
 * agent with a move-out on Friday still had to phone or email, and those requests
 * arrived across three inboxes with nothing joining them up.
 *
 * A request writes straight into the jobs table with status "Requested" and source
 * "partner", which puts it in the same queue, the same admin screen and the same export
 * as everything else. The schema already carried `source` for exactly this.
 *
 * TIER SAFETY. A request deliberately sets `scheduled_date` and leaves `job_date` NULL.
 * jrc_affiliate_data() counts a job towards the rolling twelve months on `job_date`, so
 * if a request carried one, a partner could promote themselves to Tier 3 by submitting
 * thirty six forms. The date of the clean is set by JR when the work is done, which is
 * both the correct meaning of the column and the thing that makes this safe.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** The status a partner-submitted job carries until the office confirms it. */
const JRC_REQUEST_STATUS = 'Requested';

/**
 * Statuses that are not yet confirmed work.
 *
 * jrc_jobs_upcoming() skips these so the "booked in" counter on the dashboard and the
 * calendar does not present a request nobody has looked at yet as a confirmed booking.
 */
function jrc_unconfirmed_statuses() {
	return (array) apply_filters( 'jrc_unconfirmed_statuses', array( JRC_REQUEST_STATUS ) );
}

/**
 * What a partner can ask for.
 *
 * Built from the Service CPT so the form never drifts from what the site actually sells;
 * the static list is the fallback for a fresh install with no services published yet.
 *
 * @return string[]
 */
function jrc_request_job_types() {
	$types = array();

	foreach ( get_posts( array(
		'post_type'        => 'service',
		'post_status'      => 'publish',
		'numberposts'      => 50,
		'orderby'          => 'menu_order title',
		'order'            => 'ASC',
		'fields'           => 'ids',
		'suppress_filters' => false,
	) ) as $id ) {
		$types[] = get_the_title( $id );
	}

	if ( ! $types ) {
		$types = array(
			'End of tenancy clean',
			'Communal area and flat block cleaning',
			'Office and commercial cleaning',
			'Holiday let cleaning',
			'Add-on services',
		);
	}

	return apply_filters( 'jrc_request_job_types', $types );
}

/** Time-of-day preferences offered on the form. */
function jrc_request_time_slots() {
	return array(
		'any'       => 'Any time',
		'morning'   => 'Morning',
		'afternoon' => 'Afternoon',
	);
}

/** Errors and submitted values from a failed attempt, held just long enough to redisplay. */
function jrc_request_flash( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$flash   = get_transient( 'jrc_req_flash_' . $user_id );
	if ( $flash ) {
		delete_transient( 'jrc_req_flash_' . $user_id );
	}
	return is_array( $flash ) ? $flash : array();
}

/**
 * Handle the form.
 *
 * On wp_loaded rather than template_redirect: WooCommerce account endpoints run inside
 * the main query, and handling the post before the page is resolved means the redirect
 * happens without rendering a screen that is about to be thrown away.
 */
add_action( 'wp_loaded', function () {
	if ( empty( $_POST['jrc_request_clean'] ) ) {
		return;
	}

	$user_id = get_current_user_id();

	if ( ! $user_id || ! jrc_is_affiliate( $user_id ) ) {
		return;
	}

	if ( ! isset( $_POST['jrc_request_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jrc_request_nonce'] ) ), 'jrc_request_clean_' . $user_id ) ) {
		wp_die( esc_html__( 'That form has expired. Go back, reload the page and try again.', 'jrc-partners' ), 403 );
	}

	$back = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'request-clean' )
		: home_url( '/my-account/request-clean/' );

	// Two guards, doing different jobs. The short one absorbs a double-click or a
	// refresh-resubmit; the daily cap stops a scripted or stuck client filling the
	// jobs table overnight.
	if ( get_transient( 'jrc_req_throttle_' . $user_id ) ) {
		wp_safe_redirect( add_query_arg( 'requested', 'throttled', $back ) );
		exit;
	}

	$today     = current_time( 'Y-m-d' );
	$day_key   = 'jrc_req_count_' . $user_id . '_' . $today;
	$day_count = (int) get_transient( $day_key );
	if ( $day_count >= (int) apply_filters( 'jrc_request_daily_cap', 20 ) ) {
		wp_safe_redirect( add_query_arg( 'requested', 'capped', $back ) );
		exit;
	}

	$in = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each field sanitised below.

	$fields = array(
		'address'       => sanitize_text_field( $in['jrc_req_address'] ?? '' ),
		'job_type'      => sanitize_text_field( $in['jrc_req_type'] ?? '' ),
		'date'          => sanitize_text_field( $in['jrc_req_date'] ?? '' ),
		'slot'          => sanitize_key( $in['jrc_req_slot'] ?? 'any' ),
		'contact_name'  => sanitize_text_field( $in['jrc_req_contact_name'] ?? '' ),
		'contact_phone' => sanitize_text_field( $in['jrc_req_contact_phone'] ?? '' ),
		'contact_email' => sanitize_email( $in['jrc_req_contact_email'] ?? '' ),
		'notes'         => sanitize_textarea_field( $in['jrc_req_notes'] ?? '' ),
		'building'      => sanitize_key( $in['jrc_req_building'] ?? '' ),
	);

	// ---- validation -------------------------------------------------------
	$errors = array();

	if ( '' === $fields['address'] ) {
		$errors['address'] = 'Please tell us which property needs cleaning.';
	}

	$allowed_types = jrc_request_job_types();
	if ( ! in_array( $fields['job_type'], $allowed_types, true ) ) {
		$errors['job_type'] = 'Please choose the kind of clean you need.';
	}

	if ( ! isset( jrc_request_time_slots()[ $fields['slot'] ] ) ) {
		$fields['slot'] = 'any';
	}

	$date_ts = $fields['date'] ? strtotime( $fields['date'] ) : 0;
	if ( ! $date_ts ) {
		$errors['date'] = 'Please give us a date you would like the clean done.';
	} elseif ( gmdate( 'Y-m-d', $date_ts ) < $today ) {
		$errors['date'] = 'That date has already passed. Please pick a date from today onwards.';
	} elseif ( function_exists( 'jrc_date_availability' ) ) {
		// The form greys these dates out, but that is a courtesy in the browser and not
		// a control — this is the check that actually decides.
		// See includes/affiliate-availability.php.
		$avail = jrc_date_availability( gmdate( 'Y-m-d', $date_ts ) );
		if ( empty( $avail['available'] ) ) {
			$errors['date'] = $avail['reason'];

			// "Fully booked" on its own sends the partner to the phone. Offering the
			// next few open days keeps the request in the form.
			$next = jrc_next_available_dates( gmdate( 'Y-m-d', $date_ts ), 3 );
			if ( $next ) {
				$errors['date'] .= ' Next free: ' . implode( ', ', array_map(
					function ( $d ) { return date_i18n( 'j M', strtotime( $d ) ); },
					$next
				) ) . '.';
			}
		}
	}

	if ( '' !== $fields['contact_email'] && ! is_email( $fields['contact_email'] ) ) {
		$errors['contact_email'] = 'That contact email does not look right.';
	}

	if ( $errors ) {
		set_transient( 'jrc_req_flash_' . $user_id, array( 'errors' => $errors, 'values' => $fields ), 5 * MINUTE_IN_SECONDS );
		wp_safe_redirect( add_query_arg( 'requested', 'error', $back ) . '#request' );
		exit;
	}

	// ---- store ------------------------------------------------------------
	$slots = jrc_request_time_slots();

	// A slot, not a time. Storing 09:00 for "morning" would show a precision on the
	// calendar that nobody promised, so mornings land at 09:00 and afternoons at 13:00
	// with the request itself spelling out that it is a preference.
	$hour = 'afternoon' === $fields['slot'] ? '13:00:00' : '09:00:00';

	$scope = array();
	$scope[] = 'Requested by the branch through their account.';
	$scope[] = 'Preferred: ' . date_i18n( 'j F Y', $date_ts ) . ' (' . $slots[ $fields['slot'] ] . ')';
	if ( $fields['contact_name'] || $fields['contact_phone'] || $fields['contact_email'] ) {
		$contact = array_filter( array( $fields['contact_name'], $fields['contact_phone'], $fields['contact_email'] ) );
		$scope[] = 'Contact at the property: ' . implode( ' · ', $contact );
	}
	if ( $fields['notes'] ) {
		$scope[] = 'Notes: ' . $fields['notes'];
	}

	// School accounts: the building chosen, checked against the account's own list.
	$building_name = '';
	if ( $fields['building'] && function_exists( 'jrc_school_buildings' ) ) {
		foreach ( jrc_school_buildings( $user_id ) as $sb ) {
			if ( $sb['id'] === $fields['building'] ) {
				$building_name = $sb['name'];
				array_unshift( $scope, 'Building: ' . $building_name );
			}
		}
	}

	$job_id = jrc_job_insert( $user_id, array(
		// job_date stays empty. See the note at the top of this file.
		'date'           => '',
		'scheduled_date' => gmdate( 'Y-m-d', $date_ts ) . ' ' . $hour,
		'type'           => $fields['job_type'],
		'address'        => $fields['address'],
		'scope'          => implode( "\n", $scope ),
		'status'         => JRC_REQUEST_STATUS,
		'source'         => 'partner',
	) + ( $building_name ? array( 'external_meta' => array( 'building' => $building_name ) ) : array() ) );

	if ( ! $job_id ) {
		set_transient(
			'jrc_req_flash_' . $user_id,
			array( 'errors' => array( 'general' => 'Something went wrong saving that. Please try again, or give us a ring.' ), 'values' => $fields ),
			5 * MINUTE_IN_SECONDS
		);
		wp_safe_redirect( add_query_arg( 'requested', 'error', $back ) . '#request' );
		exit;
	}

	set_transient( 'jrc_req_throttle_' . $user_id, 1, 20 );
	set_transient( $day_key, $day_count + 1, DAY_IN_SECONDS );

	/**
	 * A partner has requested a clean.
	 *
	 * @param int   $job_id  The job row created.
	 * @param int   $user_id Requesting partner.
	 * @param array $fields  Sanitised form values.
	 */
	do_action( 'jrc_clean_requested', $job_id, $user_id, $fields );

	wp_safe_redirect( add_query_arg( 'requested', '1', $back ) . '#request' );
	exit;
} );

// ---------------------------------------------------------------------------
// Who hears about it
// ---------------------------------------------------------------------------

/** Tell the office, with everything needed to action it without opening the site. */
add_action( 'jrc_clean_requested', function ( $job_id, $user_id, $fields ) {
	$account = jrc_report_account( $user_id );
	$branch  = (string) get_user_meta( $user_id, 'jrc_branch_name', true );
	$who     = $branch ? $branch . ' (' . $account['name'] . ')' : $account['name'];
	$user    = get_userdata( $user_id );
	$slots   = jrc_request_time_slots();

	$rows = array(
		'Branch'     => $who,
		'Tier'       => $account['tier'] ? 'Tier ' . $account['tier'] : '—',
		'Property'   => $fields['address'],
		'Service'    => $fields['job_type'],
		'Wanted'     => date_i18n( 'l j F Y', strtotime( $fields['date'] ) ) . ' — ' . ( $slots[ $fields['slot'] ] ?? 'Any time' ),
		'Contact'    => implode( ' · ', array_filter( array( $fields['contact_name'], $fields['contact_phone'], $fields['contact_email'] ) ) ) ?: '—',
		'Notes'      => $fields['notes'] ?: '—',
		'Booked by'  => $user ? $user->user_email : '—',
	);

	$body = '<p style="margin:0 0 16px;">A partner has requested a clean from their account. It is already in the jobs table as <strong>' . esc_html( JRC_REQUEST_STATUS ) . '</strong>.</p>';
	$body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="border-collapse:collapse;">';
	foreach ( $rows as $label => $value ) {
		$body .= '<tr>'
			. '<td style="padding:9px 12px 9px 0;border-bottom:1px solid #e9ebf0;font-family:Helvetica,Arial,sans-serif;font-size:13px;color:#6b7088;white-space:nowrap;vertical-align:top;">' . esc_html( $label ) . '</td>'
			. '<td style="padding:9px 0;border-bottom:1px solid #e9ebf0;font-family:Helvetica,Arial,sans-serif;font-size:14.5px;color:#3a3f57;">' . nl2br( esc_html( $value ) ) . '</td>'
			. '</tr>';
	}
	$body .= '</table>';

	$reply_to = ( $user && is_email( $user->user_email ) ) ? array( 'Reply-To: ' . $user->user_email ) : array();

	jrc_notify_send(
		$user_id,
		'', // Internal mail: not a partner preference, always sent.
		'Clean requested — ' . $fields['address'],
		jrc_notify_shell( 'New clean request', $body, array(
			'text' => 'Open the jobs screen',
			'url'  => admin_url( 'admin.php?page=jrc-partner' ),
		) ),
		array(
			'to'      => jrc_notify_office_email(),
			'headers' => $reply_to,
		)
	);
}, 10, 3 );

/** And confirm it back to the partner, so they have it in writing. */
add_action( 'jrc_clean_requested', function ( $job_id, $user_id, $fields ) {
	$slots = jrc_request_time_slots();

	$body  = '<p style="margin:0 0 14px;">Hi ' . esc_html( jrc_notify_greeting_name( $user_id ) ) . ',</p>';
	$body .= '<p style="margin:0 0 14px;">We have got your request and it is with the team. Someone will confirm the slot with you shortly — until then it shows as <strong>Requested</strong> on your calendar.</p>';

	$body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:20px 0 4px;background:#f7f8fa;border-radius:14px;">'
		. '<tr><td style="padding:16px 18px;font-family:Helvetica,Arial,sans-serif;font-size:14.5px;line-height:1.7;color:#3a3f57;">'
		. '<strong style="color:#143458;">' . esc_html( $fields['job_type'] ) . '</strong><br>'
		. esc_html( $fields['address'] ) . '<br>'
		. '<span style="color:#6b7088;">' . esc_html( date_i18n( 'l j F Y', strtotime( $fields['date'] ) ) . ' — ' . ( $slots[ $fields['slot'] ] ?? 'Any time' ) ) . '</span>'
		. '</td></tr></table>';

	$url = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'cleaning-calendar' )
		: home_url( '/my-account/cleaning-calendar/' );

	jrc_notify_send(
		$user_id,
		'request',
		'We have your request — ' . $fields['address'],
		jrc_notify_shell( 'Request received', $body, array( 'text' => 'View your calendar', 'url' => $url ) )
	);
}, 20, 3 );

// ---------------------------------------------------------------------------
// Keep unconfirmed work out of the "booked in" counts
// ---------------------------------------------------------------------------

/**
 * jrc_jobs_upcoming() already drops cancelled work. A request nobody has looked at yet
 * is not a booking either, so it is filtered here rather than inside that function —
 * this file owns the concept, and the filter keeps the two decoupled.
 */
add_filter( 'jrc_jobs_upcoming_rows', function ( $rows ) {
	$skip = array_map( 'strtolower', jrc_unconfirmed_statuses() );
	return array_values( array_filter( $rows, function ( $r ) use ( $skip ) {
		return ! in_array( strtolower( trim( (string) $r->status ) ), $skip, true );
	} ) );
} );
