<?php
/**
 * Gravity Forms enquiries into the jobs table.
 *
 * Every quote and contact form on the site ends its life in the Gravity Forms entry
 * list and an email. That is a dead end: the Activity screen, the CSV export, the
 * partner dashboards and the SLA figures all read the jobs table, so an enquiry that
 * never reaches it is invisible to every report the business actually looks at. It also
 * means nobody can answer "how many of the enquiries from estate agents turn into
 * work?", which is the one number that says whether the partner programme pays.
 *
 * So a submission writes a job row with source 'quote' and status 'Enquiry'. It joins
 * the same queue as everything else and moves through the same statuses, which means
 * the timeline in includes/affiliate-job-events.php gives you enquiry-to-booking
 * conversion for free.
 *
 * TIER SAFETY, same rule as the partner request form. A row created here NEVER carries
 * a job_date. jrc_affiliate_data() counts the rolling twelve months on job_date, so a
 * quote that set one would let anybody promote a branch to Tier 3 by submitting the
 * public form thirty six times under their own referral code. job_date is set by JR
 * when the work is done. See the matching note in includes/affiliate-requests.php.
 *
 * IDEMPOTENCY. Gravity Forms can fire gform_after_submission more than once for a
 * single entry — a resubmitted AJAX page, a payment callback, an entry edited in
 * admin. Rows are written through jrc_job_upsert_external() keyed on the entry id, so
 * a repeat updates in place rather than duplicating the enquiry.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** The status a form submission carries until somebody picks it up. */
const JRC_ENQUIRY_STATUS = 'Enquiry';

/**
 * An enquiry is not confirmed work.
 *
 * Without this the dashboard's "booked in" counter and the calendar's upcoming panel
 * would present an unread web form as a confirmed clean.
 */
add_filter( 'jrc_unconfirmed_statuses', function ( $statuses ) {
	$statuses[] = JRC_ENQUIRY_STATUS;
	return $statuses;
} );

/** Give the new status a partner-facing meaning on the timeline. */
add_filter( 'jrc_job_status_meaning', function ( $map ) {
	$map['enquiry'] = 'Enquiry received from the website';
	return $map;
} );

/**
 * Which forms feed the pipeline.
 *
 * The contact and quote forms are enquiries about work. The partner registration form
 * deliberately is not — it is somebody asking to join the programme, not asking for a
 * clean, and turning it into a job would put a signup in the cleaning schedule.
 *
 * @return int[]
 */
function jrc_quote_pipeline_forms() {
	$ids = array();

	if ( function_exists( 'get_field' ) ) {
		foreach ( array( 'contact_form_id', 'quote_form_id' ) as $key ) {
			$id = (int) get_field( $key, 'options' );
			if ( $id ) {
				$ids[] = $id;
			}
		}
	}

	/**
	 * Filter the Gravity Forms IDs that create job rows.
	 *
	 * @param int[] $ids Form IDs.
	 */
	return array_values( array_unique( array_filter( (array) apply_filters( 'jrc_quote_pipeline_forms', $ids ) ) ) );
}

/**
 * Work out which field on a form holds which piece of information.
 *
 * Matched on the field label rather than a stored field id, because form IDs and field
 * IDs change whenever somebody rebuilds a form in the Gravity Forms editor — and they
 * do, without telling anyone. A label match survives that; a hardcoded `field_12`
 * silently starts reading the wrong answer. The same approach is already used by the
 * referral-code scan in includes/affiliate-bonus.php.
 *
 * The `type` check comes first where it is decisive: a Gravity Forms `email` field is
 * an email address whatever the label says.
 *
 * @param array $form Gravity Forms form array.
 * @return array<string,string> role => field id
 */
function jrc_quote_field_map( $form ) {
	$map = array();

	// Longest/most specific patterns first — 'postcode' must win before 'code', and
	// 'property address' before a bare 'address'.
	$patterns = array(
		'address'  => array( 'property address', 'address of', 'address', 'property' ),
		'postcode' => array( 'postcode', 'post code', 'zip' ),
		'job_type' => array( 'service', 'what do you need', 'type of clean', 'kind of clean', 'job type' ),
		'date'     => array( 'preferred date', 'when do you need', 'date you need', 'date' ),
		'name'     => array( 'your name', 'full name', 'name' ),
		'phone'    => array( 'phone', 'telephone', 'mobile', 'contact number' ),
		'email'    => array( 'email' ),
		'referral' => array( 'referral code', 'referral', 'referred by' ),
		'notes'    => array( 'anything else', 'more detail', 'details', 'message', 'notes' ),
	);

	foreach ( (array) ( $form['fields'] ?? array() ) as $field ) {
		$id    = (string) ( $field->id ?? '' );
		$type  = strtolower( (string) ( $field->type ?? '' ) );
		$label = strtolower( trim( (string) ( $field->label ?? '' ) ) );

		if ( '' === $id ) {
			continue;
		}

		// An admin label, where set, is the deliberate machine-readable name and beats
		// any guess from the visible label.
		$admin = strtolower( trim( (string) ( $field->adminLabel ?? '' ) ) );
		if ( $admin && isset( $patterns[ $admin ] ) && ! isset( $map[ $admin ] ) ) {
			$map[ $admin ] = $id;
			continue;
		}

		// Decisive by type.
		if ( 'email' === $type && ! isset( $map['email'] ) ) {
			$map['email'] = $id;
			continue;
		}
		if ( 'phone' === $type && ! isset( $map['phone'] ) ) {
			$map['phone'] = $id;
			continue;
		}

		foreach ( $patterns as $role => $needles ) {
			if ( isset( $map[ $role ] ) ) {
				continue;
			}
			foreach ( $needles as $needle ) {
				if ( false !== strpos( $label, $needle ) ) {
					$map[ $role ] = $id;
					break 2;
				}
			}
		}
	}

	/**
	 * Filter the resolved field map for one form.
	 *
	 * The escape hatch for a form whose labels do not read the way this function
	 * guesses — return role => field id and the guesswork is bypassed entirely.
	 *
	 * @param array $map  role => field id.
	 * @param array $form Gravity Forms form array.
	 */
	return (array) apply_filters( 'jrc_quote_field_map', $map, $form );
}

/**
 * Read one mapped value out of an entry.
 *
 * Gravity Forms stores composite fields (name, address) as `id.subid`, so a bare id
 * comes back empty for those. Falling back to a scan of the sub-inputs and joining
 * them is what makes a Name or Address field usable without special-casing each type.
 */
function jrc_quote_entry_value( $entry, $map, $role ) {
	if ( empty( $map[ $role ] ) ) {
		return '';
	}

	$id  = (string) $map[ $role ];
	$val = trim( (string) rgar( $entry, $id ) );

	if ( '' !== $val ) {
		return $val;
	}

	// Composite: gather every `id.n` part in order.
	$parts = array();
	foreach ( (array) $entry as $key => $value ) {
		if ( 0 === strpos( (string) $key, $id . '.' ) && '' !== trim( (string) $value ) ) {
			$parts[ (string) $key ] = trim( (string) $value );
		}
	}
	if ( ! $parts ) {
		return '';
	}

	// Sort by the numeric sub-id so an address arrives as street, town, county,
	// postcode rather than in hash order.
	uksort( $parts, function ( $a, $b ) {
		return (float) substr( $a, strpos( $a, '.' ) + 1 ) <=> (float) substr( $b, strpos( $b, '.' ) + 1 );
	} );

	return implode( ', ', $parts );
}

/**
 * Turn a submission into a job row.
 */
add_action( 'gform_after_submission', function ( $entry, $form ) {
	if ( empty( $entry['id'] ) || empty( $form['id'] ) ) {
		return;
	}

	$form_id = (int) $form['id'];
	if ( ! in_array( $form_id, jrc_quote_pipeline_forms(), true ) ) {
		return;
	}

	// A spam or partial entry is not an enquiry.
	if ( ! empty( $entry['status'] ) && 'active' !== $entry['status'] ) {
		return;
	}

	$map = jrc_quote_field_map( $form );

	$address  = jrc_quote_entry_value( $entry, $map, 'address' );
	$postcode = jrc_quote_entry_value( $entry, $map, 'postcode' );
	$job_type = jrc_quote_entry_value( $entry, $map, 'job_type' );
	$name     = jrc_quote_entry_value( $entry, $map, 'name' );
	$phone    = jrc_quote_entry_value( $entry, $map, 'phone' );
	$email    = jrc_quote_entry_value( $entry, $map, 'email' );
	$notes    = jrc_quote_entry_value( $entry, $map, 'notes' );
	$code     = jrc_quote_entry_value( $entry, $map, 'referral' );
	$date_raw = jrc_quote_entry_value( $entry, $map, 'date' );

	// A postcode captured separately belongs on the address — the bulk report matcher
	// in includes/affiliate-bulk-reports.php keys on it, and the office reads it.
	if ( $postcode && false === stripos( $address, $postcode ) ) {
		$address = trim( $address . ( $address ? ', ' : '' ) . $postcode );
	}

	// Nothing identifying the property at all means there is no job to create — but the
	// entry still exists in Gravity Forms, so nothing is lost by declining here.
	if ( '' === trim( $address ) && '' === trim( $email ) && '' === trim( $phone ) ) {
		return;
	}

	$user_id = $code && function_exists( 'jrc_affiliate_by_code' ) ? jrc_affiliate_by_code( $code ) : 0;

	// A preferred date is a wish, not a booking, so it goes on scheduled_date where the
	// office can see it on the calendar — never on job_date. See the file header.
	$scheduled = '';
	if ( $date_raw ) {
		$ts = strtotime( $date_raw );
		// Guard against a date field that came through as free text ("asap", "next
		// week"): strtotime() will happily interpret some of those into last century.
		if ( $ts && $ts > strtotime( '-1 year' ) ) {
			$scheduled = gmdate( 'Y-m-d 09:00:00', $ts );
		}
	}

	// The enquirer's contact details and free-text notes go in external_meta, which only
	// staff screens read — never in `scope`, which the partner's jobs tab and calendar
	// display. Referral codes are guessable and a ?ref= link carries across a browsing
	// session, so a member of the public must not have their phone and email handed to
	// an agency just because a code was attached to their enquiry.
	$scope = array( 'Website enquiry via ' . ( $form['title'] ?? ( 'form #' . $form_id ) ) . '.' );
	if ( $date_raw ) {
		$scope[] = 'Preferred: ' . $date_raw;
	}
	if ( $code ) {
		$scope[] = 'Referral code: ' . $code . ( $user_id ? '' : ' (no matching partner account)' );
	}

	$private = array_filter( array(
		'contact'       => implode( ' · ', array_filter( array( $name, $phone, $email ) ) ),
		'contact_email' => $email ? strtolower( sanitize_email( $email ) ) : '',
		'enquiry_notes' => $notes,
	) );

	$job_id = jrc_job_upsert_external( $user_id, 'quote', (string) $entry['id'], array(
		'date'           => '',           // never, see the file header
		'scheduled_date' => $scheduled,
		'type'           => $job_type ? $job_type : 'Enquiry',
		'address'        => $address,
		'scope'          => implode( "\n", $scope ),
		'value'          => '',
		'status'         => JRC_ENQUIRY_STATUS,
		'external_meta'  => $private,
	) );

	if ( ! $job_id ) {
		return;
	}

	// Let the office find the job from the entry and the entry from the job.
	gform_update_meta( (int) $entry['id'], 'jrc_job_id', $job_id );

	/**
	 * A website enquiry became a job row.
	 *
	 * @param int   $job_id  The job.
	 * @param array $entry   Gravity Forms entry.
	 * @param array $form    Gravity Forms form.
	 * @param int   $user_id Attributed partner, or 0.
	 */
	do_action( 'jrc_quote_job_created', $job_id, $entry, $form, $user_id );
}, 20, 2 );

// ---------------------------------------------------------------------------
// Conversion reporting
// ---------------------------------------------------------------------------

/**
 * Enquiry-to-work conversion, split by partner channel.
 *
 * "Unattributed" is its own row rather than being dropped: enquiries that arrive with
 * no referral code are the baseline the programme is measured against, and hiding them
 * would make every channel look better than it is.
 *
 * @param array $args `days` — window to report on.
 * @return array<string,array{channel:string,enquiries:int,converted:int,rate:float,value:float}>
 */
function jrc_quote_conversion_stats( $args = array() ) {
	global $wpdb;

	$args  = wp_parse_args( $args, array( 'days' => 90 ) );
	$table = jrc_jobs_table();
	$since = gmdate( 'Y-m-d H:i:s', strtotime( '-' . max( 1, (int) $args['days'] ) . ' days', current_time( 'timestamp' ) ) );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT j.user_id, j.status, j.value
		FROM $table j
		WHERE j.source = 'quote' AND j.created_at >= %s",
		$since
	) );

	// Channel per user, resolved once rather than per row.
	$channels = array();
	$out      = array();

	foreach ( (array) $rows as $r ) {
		$uid = (int) $r->user_id;

		if ( ! isset( $channels[ $uid ] ) ) {
			$channels[ $uid ] = $uid ? ( (string) get_user_meta( $uid, 'jrc_affiliate_channel', true ) ?: 'unknown' ) : 'unattributed';
		}
		$key = $channels[ $uid ];

		if ( ! isset( $out[ $key ] ) ) {
			$out[ $key ] = array( 'channel' => $key, 'enquiries' => 0, 'converted' => 0, 'rate' => 0.0, 'value' => 0.0 );
		}

		$out[ $key ]['enquiries']++;

		// Converted means it left the enquiry state for real work — Booked or
		// Completed. Cancelled and still-Enquiry both count as not converted.
		if ( in_array( strtolower( (string) $r->status ), array( 'booked', 'completed' ), true ) ) {
			$out[ $key ]['converted']++;
			$out[ $key ]['value'] += (float) $r->value;
		}
	}

	foreach ( $out as $key => $row ) {
		$out[ $key ]['rate'] = $row['enquiries'] ? ( $row['converted'] / $row['enquiries'] * 100 ) : 0.0;
	}

	uasort( $out, function ( $a, $b ) {
		return $b['enquiries'] <=> $a['enquiries'];
	} );

	return $out;
}
