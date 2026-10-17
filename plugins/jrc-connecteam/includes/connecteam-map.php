<?php
/**
 * Turning a Connecteam shift into a JR Cleaning job row.
 *
 * This file holds every decision about what crosses the boundary, and it is the file to
 * read before changing anything about the integration. The transport in
 * connecteam-client.php is replaceable plumbing; these rules are the product.
 *
 * ── WHAT NEVER CROSSES ───────────────────────────────────────────────────────
 *
 * 1. `assignedUserIds` — the cleaners on the shift. The client's instruction was
 *    explicit: partners see the date, the location and the notes, never the staff. The
 *    strongest way to honour that is not to store it. A column that exists is a column
 *    that eventually gets rendered, exported, or put in an iCal feed by someone who did
 *    not read this comment. Connecteam remains where JR look up who is on a job, which
 *    is correct — it is their staff tool.
 *
 * 2. `shift.notes` — Connecteam's notes field is written BY staff FOR staff. Real
 *    examples carry key-safe codes, access instructions and remarks about customers.
 *    The `scope` column it would naturally map to is the single most exposed field in
 *    this system: it appears in the partner jobs table, the calendar modal, the partner
 *    CSV export, and the iCalendar DESCRIPTION — the last of which sits on an
 *    unauthenticated URL the partner may have pasted into a shared team calendar.
 *
 *    Notes reach a partner only from a shift custom field the office fills in
 *    deliberately, configured as `notes_field_id`. One obvious box meaning "the estate
 *    agent will read this", separate from the internal one. Until that field exists,
 *    nothing is imported and `scope` keeps whatever a human wrote.
 *
 * ── WHAT IS PRESERVED RATHER THAN OVERWRITTEN ────────────────────────────────
 *
 * jrc_job_update() rewrites every column it knows about, because the profile screen
 * legitimately needs to clear a field by emptying it. An importer has the opposite
 * requirement: Connecteam has no opinion about `value` (the price JR charged) or
 * `free_bonus`, and passing '' for them would silently zero data staff had typed.
 * jrc_ct_merge_existing() carries those forward. This is the single most likely way
 * for this integration to destroy data, and it is why the upsert does a read first.
 *
 * ── job_date ─────────────────────────────────────────────────────────────────
 *
 * `job_date` drives the rolling twelve-month count, which drives tier, which drives the
 * discount a branch receives. It is set only on a genuine transition to Completed, and
 * never cleared once set. See jrc_ct_map_shift().
 *
 * @package jrc-connecteam
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** The `source` value every row from this integration carries. */
const JRC_CT_SOURCE = 'connecteam';

/**
 * Statuses this integration owns.
 *
 * A row whose status is not in this list was changed by a human after import, and the
 * reconciler leaves it alone rather than arguing with them — see jrc_ct_should_write_status().
 */
function jrc_ct_owned_statuses() {
	return array( 'Pending', 'Booked', 'Completed', 'Cancelled' );
}

/** Give the imported statuses partner-facing wording on the job timeline. */
add_filter( 'jrc_job_status_meaning', function ( $map ) {
	// 'Pending' already means "with our team to schedule", which is exactly what an
	// unpublished Connecteam shift is, so it is left as it stands.
	return $map;
} );

// ---------------------------------------------------------------------------
// Times
// ---------------------------------------------------------------------------

/**
 * Convert a Connecteam Unix timestamp into a MySQL datetime in the site's timezone.
 *
 * Connecteam sends seconds (not milliseconds) plus a `timezone` string per shift. The
 * jobs table stores wall-clock local time — that is what jrc_jobs_month_by_day() buckets
 * on and what date_i18n() renders — so the instant has to be resolved into the site's
 * zone, not the shift's. A shift scheduled 09:00 in Europe/London stores as 09:00; the
 * same instant would store as 10:00 if the site were in Paris, which is correct.
 *
 * @param int $unix_seconds Epoch seconds.
 * @return string 'Y-m-d H:i:s', or '' if the input is unusable.
 */
function jrc_ct_time_to_local( $unix_seconds ) {
	$ts = (int) $unix_seconds;
	if ( $ts <= 0 ) {
		return '';
	}

	// Guard against a caller that sent milliseconds: anything past the year 5138 in
	// seconds is certainly a millisecond value, and storing it would put the job
	// thousands of years out where nobody would ever see it again.
	if ( $ts > 100000000000 ) {
		$ts = (int) round( $ts / 1000 );
	}

	try {
		$dt = new DateTime( '@' . $ts );
		$dt->setTimezone( wp_timezone() );
		return $dt->format( 'Y-m-d H:i:s' );
	} catch ( Exception $e ) {
		return '';
	}
}

// ---------------------------------------------------------------------------
// Status
// ---------------------------------------------------------------------------

/**
 * Work out a JR status from a Connecteam shift.
 *
 * Connecteam has no single status field. It has `isPublished` and a `statuses` array of
 * per-user actions (accepted, rejected, checked_in, completed, claimed…). With multiple
 * cleaners on one shift those actions can disagree, so the rule is "the furthest anyone
 * has got", which is what a customer asking "is my clean done?" means.
 *
 * @param array $shift Shift object.
 * @return string One of jrc_ct_owned_statuses().
 */
function jrc_ct_derive_status( $shift ) {
	$actions = array();

	foreach ( (array) ( $shift['statuses'] ?? array() ) as $entry ) {
		// The shape has moved between webhook v1 and v2; accept a bare string or an
		// object with a type/status key rather than depending on one of them.
		if ( is_string( $entry ) ) {
			$actions[] = strtolower( $entry );
			continue;
		}
		if ( is_array( $entry ) ) {
			foreach ( array( 'status', 'type', 'statusType', 'action' ) as $key ) {
				if ( ! empty( $entry[ $key ] ) && is_string( $entry[ $key ] ) ) {
					$actions[] = strtolower( $entry[ $key ] );
					break;
				}
			}
		}
	}

	if ( in_array( 'completed', $actions, true ) ) {
		return 'Completed';
	}

	// An unpublished shift is a draft rota. It is real work being planned, but nobody
	// has committed to it, so it maps to Pending rather than Booked — the difference
	// the partner reads as "with our team to schedule" versus "in the diary".
	if ( empty( $shift['isPublished'] ) ) {
		return 'Pending';
	}

	return 'Booked';
}

/**
 * Should the importer overwrite the status currently on the row?
 *
 * Only when the existing status is one this integration set. If somebody in the office
 * has moved a job to a status Connecteam does not model — a bespoke workflow state, or
 * the 'Requested'/'Enquiry' values that belong to the partner request form and the quote
 * pipeline — that is a human decision and the reconciler does not undo it every night.
 */
function jrc_ct_should_write_status( $existing_status ) {
	$existing = trim( (string) $existing_status );
	if ( '' === $existing ) {
		return true;
	}
	return in_array( $existing, jrc_ct_owned_statuses(), true );
}

// ---------------------------------------------------------------------------
// Partner attribution
// ---------------------------------------------------------------------------

/**
 * Read the configured custom field off a Connecteam Job.
 *
 * Custom field values arrive as a list of objects rather than a map, and the id key has
 * appeared as both `customFieldId` and `id` across doc revisions, so both are accepted.
 *
 * @return string Raw value, or ''.
 */
function jrc_ct_job_field_value( $job, $field_id ) {
	if ( '' === (string) $field_id ) {
		return '';
	}

	// The setting may hold the field's numeric ID or its name as shown in Connecteam
	// ("Affiliate Code"). The name is accepted because Connecteam has no endpoint that
	// lists shift custom fields, so the settings screen cannot offer a dropdown and an
	// administrator naturally types what they can see.
	$wanted = strtolower( trim( (string) $field_id ) );

	foreach ( (array) ( $job['customFields'] ?? array() ) as $field ) {
		$id   = (string) ( $field['customFieldId'] ?? ( $field['id'] ?? '' ) );
		$name = strtolower( trim( (string) ( $field['name'] ?? '' ) ) );
		if ( $id !== trim( (string) $field_id ) && ( '' === $name || $name !== $wanted ) ) {
			continue;
		}
		$value = $field['value'] ?? '';
		// Dropdowns can return an array of selected options; take the first meaningful one.
		if ( is_array( $value ) ) {
			$value = reset( $value );
			if ( is_array( $value ) ) {
				$value = $value['label'] ?? ( $value['value'] ?? '' );
			}
		}
		return trim( (string) $value );
	}

	return '';
}

/**
 * Which WordPress partner does this shift belong to?
 *
 * Never the Connecteam users on the shift — they are JR's cleaners, and a partner is an
 * estate agent branch who is not in Connecteam at all. The partner reference is a
 * custom field, looked for in this order:
 *
 *   1. The SHIFT. This is where JR's account actually has it: "Affiliate Code" is a
 *      shift custom field, filled in per booking. Checked first so a one-off booking for
 *      a partner can be attributed without touching the job.
 *   2. The JOB, then its parent jobs. Sub-jobs inherit, so a branch-level job carrying
 *      the code covers every property under it.
 *
 * @param array  $job         Job object from the flattened index.
 * @param array  $jobs_index  The full index, for the parent walk.
 * @param array  $shift       The shift itself.
 * @return array{user_id:int,matched_on:string,raw:string}
 */
function jrc_ct_resolve_partner( $job, $jobs_index, $shift = array() ) {
	$s     = jrc_ct_settings();
	$field = (string) $s['partner_field_id'];
	$none  = array( 'user_id' => 0, 'matched_on' => '', 'raw' => '' );

	if ( '' === $field ) {
		return $none;
	}

	$raw   = is_array( $shift ) ? jrc_ct_shift_field_value( $shift, $field ) : '';
	$depth = '' !== $raw ? 'shift' : '';

	// Walk up the sub-job chain until a value turns up. Bounded: a cycle in the data
	// (a job whose parent is itself) would otherwise hang the import.
	$seen    = array();
	$current = '' === $raw && is_array( $job ) ? $job : null;

	while ( is_array( $current ) && count( $seen ) < 10 ) {
		$id = (string) ( $current['jobId'] ?? '' );
		if ( '' === $id || isset( $seen[ $id ] ) ) {
			break;
		}
		$seen[ $id ] = true;

		$raw = jrc_ct_job_field_value( $current, $field );
		if ( '' !== $raw ) {
			$depth = count( $seen ) > 1 ? 'parent job' : 'job';
			break;
		}

		$parent_id = (string) ( $current['_parentId'] ?? ( $current['parentId'] ?? '' ) );
		$current   = $parent_id && isset( $jobs_index[ $parent_id ] ) ? $jobs_index[ $parent_id ] : null;
	}

	if ( '' === $raw ) {
		return $none;
	}

	// The field holds either a referral code or a raw WP user ID, per the setting.
	if ( 'user_id' === $s['partner_field_by'] ) {
		$uid = (int) $raw;
		// Confirm the user exists AND is an approved affiliate. Without this, a typo in
		// the field would attribute a customer's address to whichever unrelated account
		// happens to hold that ID.
		if ( $uid > 0 && jrc_is_affiliate( $uid ) ) {
			return array( 'user_id' => $uid, 'matched_on' => $depth . ' user id', 'raw' => $raw );
		}
		return array( 'user_id' => 0, 'matched_on' => '', 'raw' => $raw );
	}

	$uid = function_exists( 'jrc_affiliate_by_code' ) ? jrc_affiliate_by_code( $raw ) : 0;
	if ( $uid ) {
		return array( 'user_id' => $uid, 'matched_on' => $depth . ' code', 'raw' => $raw );
	}

	// A value that matches nothing is worth surfacing on the preview screen — it is
	// usually a typo in Connecteam, and silently importing it unlinked hides that.
	return array( 'user_id' => 0, 'matched_on' => '', 'raw' => $raw );
}

// ---------------------------------------------------------------------------
// Mapping
// ---------------------------------------------------------------------------

/**
 * Is a shift title safe to show a partner?
 *
 * Titles are free text written by the office. If they name staff ("EOT clean — Dave &
 * Sam") that lands in `job_type`, which is partner-visible everywhere `scope` is. There
 * is no reliable way to detect a name, so this is a switch rather than a heuristic: the
 * preview screen shows the real titles and an administrator decides.
 */
function jrc_ct_use_shift_titles() {
	$s = jrc_ct_settings();
	return ! empty( $s['use_shift_titles'] );
}

/**
 * Map one shift onto the array jrc_job_upsert_external() expects.
 *
 * @param array $shift      Shift object.
 * @param array $jobs_index Flattened job index from jrc_ct_jobs_index().
 * @param array $existing   The current wp_jrc_jobs row, or null for a new one.
 * @return array{row:array,user_id:int,attribution:array,warnings:array}
 */
function jrc_ct_map_shift( $shift, $jobs_index, $existing = null ) {
	$s        = jrc_ct_settings();
	$warnings = array();

	$job_id = (string) ( $shift['jobId'] ?? '' );
	$job    = $job_id && isset( $jobs_index[ $job_id ] ) ? $jobs_index[ $job_id ] : array();

	if ( $job_id && ! $job ) {
		$warnings[] = 'Shift references job ' . $job_id . ', which was not in the job list.';
	}

	$attribution = jrc_ct_resolve_partner( $job, $jobs_index, $shift );

	// No code in Connecteam: fall back to a match made by hand on the Preview screen.
	// The raw code (if any, e.g. a typo) is kept so the screen still shows it.
	if ( ! $attribution['user_id'] && function_exists( 'jrc_ct_manual_match' ) ) {
		$manual = jrc_ct_manual_match( $shift );
		if ( $manual ) {
			$attribution = array_merge( $attribution, $manual );
		}
	}

	// ---- what the clean is called -----------------------------------------
	// The job title is the safer default: it names the property or the client, and is
	// reused across shifts, so it gets read and corrected. A shift title is per-shift
	// free text nobody reviews.
	$job_title   = trim( (string) ( $job['title'] ?? '' ) );
	$shift_title = trim( (string) ( $shift['title'] ?? '' ) );

	$type = $job_title;
	if ( '' === $type && jrc_ct_use_shift_titles() ) {
		$type = $shift_title;
	}
	if ( '' === $type ) {
		$type = 'Clean';
	}

	// ---- where -------------------------------------------------------------
	// The shift's own location is the property. It sits under locationData.gps.address
	// (the importer used to look for locationData.address, which never exists, so every
	// imported job came in with no address). `isReferencedToJob` means the shift just
	// uses its job's location, so the job's address is used instead.
	$location = (array) ( $shift['locationData'] ?? array() );
	$address  = empty( $location['isReferencedToJob'] )
		? trim( (string) ( $location['gps']['address'] ?? ( $location['address'] ?? '' ) ) )
		: '';
	if ( '' === $address ) {
		$address = trim( (string) ( $job['gps']['address'] ?? '' ) );
	}
	// A parent job's address is the branch office, not the property, so it is not
	// inherited the way the partner reference is — a wrong address is worse than none.

	// ---- when --------------------------------------------------------------
	$start = jrc_ct_time_to_local( $shift['startTime'] ?? 0 );
	$end   = jrc_ct_time_to_local( $shift['endTime'] ?? 0 );

	if ( '' === $start ) {
		$warnings[] = 'Shift has no usable start time.';
	}
	if ( $start && $end && $end <= $start ) {
		$warnings[] = 'Shift end time is not after its start; end ignored.';
		$end        = '';
	}

	// ---- status ------------------------------------------------------------
	$status = jrc_ct_derive_status( $shift );
	if ( $existing && ! jrc_ct_should_write_status( $existing->status ) ) {
		$status = (string) $existing->status;
	}

	// ---- job_date ----------------------------------------------------------
	// Tier-affecting. Set only when the work is genuinely finished, and never cleared:
	// a shift reopened in Connecteam must not retract a partner's booking count and
	// silently demote them.
	$job_date = $existing && $existing->job_date ? $existing->job_date : '';
	if ( empty( $s['complete_sets_date'] ) ) {
		// Configured off: the field stays whatever a human made it.
		$job_date = $existing && $existing->job_date ? $existing->job_date : '';
	} elseif ( 'Completed' === $status && '' === $job_date && $start ) {
		$job_date = gmdate( 'Y-m-d', strtotime( $start ) );
	}

	// ---- notes -------------------------------------------------------------
	// Only ever from the designated customer-facing custom field. See the file header
	// for why shift.notes is not touched.
	$scope = $existing ? (string) $existing->scope : '';
	if ( ! empty( $s['notes_field_id'] ) ) {
		$customer_note = jrc_ct_shift_field_value( $shift, $s['notes_field_id'] );
		if ( '' !== $customer_note ) {
			$scope = $customer_note;
		}
	}

	// ---- value ---------------------------------------------------------------
	// The price of the clean, from the configured custom field on the shift, else its
	// job. Left unset when blank, so jrc_ct_merge_existing() keeps a value typed in WP.
	$value = '';
	if ( ! empty( $s['value_field_id'] ) ) {
		$raw = jrc_ct_shift_field_value( $shift, $s['value_field_id'] );
		if ( '' === $raw && $job ) {
			$raw = jrc_ct_job_field_value( $job, $s['value_field_id'] );
		}
		$value = jrc_ct_parse_money( $raw );
		if ( '' === $value && '' !== $raw ) {
			$warnings[] = 'Value field "' . $raw . '" is not an amount; ignored.';
		}
	}

	$row = array(
		'date'           => $job_date,
		'scheduled_date' => $start,
		'scheduled_end'  => $end,
		'type'           => $type,
		'address'        => $address,
		'scope'          => $scope,
		'status'         => $status,
		'source'         => JRC_CT_SOURCE,
		'external_ref'   => (string) ( $shift['id'] ?? '' ),
		'external_meta'  => jrc_ct_shift_meta( $shift, $job ),
	);
	if ( '' !== $value ) {
		$row['value'] = $value;
	}
	// The building, for School accounts (see jrc-partners school-sites.php).
	if ( ! empty( $s['building_field_id'] ) ) {
		$building = jrc_ct_shift_field_value( $shift, $s['building_field_id'] );
		if ( '' === $building && $job ) {
			$building = jrc_ct_job_field_value( $job, $s['building_field_id'] );
		}
		if ( '' !== $building ) {
			$row['external_meta']['building'] = $building;
		}
	}

	// What THIS shift says, before jrc_ct_merge_existing() fills a blank from the job:
	// the merge logic needs to tell "no value here" from "inherited".
	$row['external_meta']['own_value'] = $value;

	return array(
		'row'         => jrc_ct_merge_existing( $row, $existing ),
		'user_id'     => (int) $attribution['user_id'],
		'attribution' => $attribution,
		'warnings'    => $warnings,
		'job_title'   => $job_title,
		'shift_title' => $shift_title,
	);
}

/**
 * Read a custom field off a SHIFT (as opposed to a job).
 *
 * Shift custom fields use `id`, job custom fields use `customFieldId`. Both are accepted
 * in both readers rather than maintaining two near-identical functions that drift.
 */
function jrc_ct_shift_field_value( $shift, $field_id ) {
	return jrc_ct_job_field_value( $shift, $field_id );
}

/**
 * Carry forward every column Connecteam has no opinion about.
 *
 * Without this, an import would blank the price staff typed and the free bonus they
 * selected, because jrc_job_update() writes every column jrc_job_normalise() returns and
 * an absent key normalises to empty. See the file header.
 */
function jrc_ct_merge_existing( array $row, $existing ) {
	if ( ! $existing ) {
		return $row;
	}

	// `value` is what JR charged. Connecteam does not carry it and never will.
	if ( ! isset( $row['value'] ) || '' === $row['value'] ) {
		$row['value'] = (float) $existing->value ? (string) $existing->value : '';
	}

	// The partner's chosen perk, set on the WordPress side.
	if ( empty( $row['free_bonus'] ) ) {
		$row['free_bonus'] = (string) $existing->free_bonus;
	}

	// An address typed by staff beats an empty one from Connecteam.
	if ( '' === $row['address'] && $existing->address ) {
		$row['address'] = (string) $existing->address;
	}

	return $row;
}

/**
 * The rest of a shift's detail, kept for staff in the job's `external_meta`.
 *
 * Everything on the Connecteam shift panel that has a home in WordPress: the shift
 * title, the job it sits under, every custom field by name (Shift tags, Affiliate
 * Code, and anything added later), open/published flags and task count.
 *
 * Shown on the admin screens only. Two things stay out on purpose, per the client's
 * instruction that partners see the date, the location and agreed notes but never the
 * staff: the assigned users (only a head-count is kept) and the built-in shift notes
 * (staff-to-staff, routinely holding key-safe codes). See the file header.
 *
 * @return array
 */
function jrc_ct_shift_meta( $shift, $job = array() ) {
	$fields = array();
	foreach ( (array) ( $shift['customFields'] ?? array() ) as $f ) {
		$name = trim( (string) ( $f['name'] ?? '' ) );
		if ( '' === $name ) {
			$name = 'Field ' . (string) ( $f['customFieldId'] ?? ( $f['id'] ?? '' ) );
		}
		$value = $f['value'] ?? '';
		if ( is_array( $value ) ) {
			// Dropdowns: a list of {id, value} options.
			$labels = array();
			foreach ( $value as $opt ) {
				$labels[] = is_array( $opt ) ? (string) ( $opt['value'] ?? ( $opt['label'] ?? '' ) ) : (string) $opt;
			}
			$value = implode( ', ', array_filter( $labels, 'strlen' ) );
		}
		$value = trim( (string) $value );
		if ( '' !== $value ) {
			$fields[ $name ] = $value;
		}
	}

	$loc = (array) ( $shift['locationData'] ?? array() );

	return array_filter( array(
		'shift_title' => trim( (string) ( $shift['title'] ?? '' ) ),
		'job_title'   => trim( (string) ( $job['title'] ?? '' ) ),
		'fields'      => $fields,
		'staff_count' => count( (array) ( $shift['assignedUserIds'] ?? array() ) ),
		'open_shift'  => ! empty( $shift['isOpenShift'] ) ? 1 : 0,
		'published'   => ! empty( $shift['isPublished'] ) ? 1 : 0,
		'tasks'       => count( (array) ( $shift['tasks'] ?? array() ) ),
		'lat'         => isset( $loc['gps']['latitude'] ) ? (string) $loc['gps']['latitude'] : '',
		'lng'         => isset( $loc['gps']['longitude'] ) ? (string) $loc['gps']['longitude'] : '',
	), function ( $v ) {
		return '' !== $v && array() !== $v;
	} );
}

/**
 * "£1,200.50" / "1200" / "145.00 inc VAT" -> "1200.50". '' when there is no amount.
 *
 * Only the first number is taken, so "145 + 20 extra" is not read as 14520.
 */
function jrc_ct_parse_money( $raw ) {
	$raw = str_replace( ',', '', (string) $raw );
	if ( ! preg_match( '/\d+(?:\.\d{1,2})?/', $raw, $m ) ) {
		return '';
	}
	$n = round( (float) $m[0], 2 );
	return $n > 0 ? number_format( $n, 2, '.', '' ) : '';
}

