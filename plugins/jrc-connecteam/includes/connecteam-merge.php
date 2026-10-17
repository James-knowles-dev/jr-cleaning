<?php
/**
 * One clean, one job — even when Connecteam has a shift per cleaner.
 *
 * When the office puts two or four people on a job, Connecteam creates one shift PER
 * PERSON: same job, same title, same address, same start and end, near-identical ids.
 * Imported one row per shift, a four-person communal clean showed four times in the
 * partner's calendar and job list — and, once completed, would have counted four times
 * towards their tier.
 *
 * So shifts are grouped by their SLOT — start, end, job and shift title — and a slot is
 * one job row:
 *
 *   external_ref                 the first shift seen for the slot (the "primary")
 *   external_meta['merged']      every other shift id in the slot
 *   external_meta['shift_status'] each shift's own status, so the row can show the
 *                                furthest any cleaner has got
 *   external_meta['slot']        the slot key, so a moved shift can be spotted
 *
 * Once a shift belongs to a job it stays with it through edits (an address or title filled
 * in later changes the job, not its membership). Only a cleaner's shift moved to a
 * different TIME leaves the group and becomes, or joins, a job of its own. Removing one cleaner's
 * shift leaves the job in place; only removing the last one cancels it.
 *
 * @package jrc-connecteam
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Normalised text for slot comparison: case, punctuation and spacing do not split a slot. */
function jrc_ct_norm( $text ) {
	return trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( (string) $text ) ) );
}

/**
 * The slot key for a mapped row.
 *
 * @param array $row  jrc_ct_map_shift()['row'] or an equivalent built from a DB row.
 * @param array $meta Its external_meta.
 */
function jrc_ct_slot_key( $row, $meta ) {
	// Deliberately NOT the address: the office often fills it in days after creating the
	// shifts, and an edit is not a different clean. Same start, end, job and shift title
	// is the same clean.
	return md5( implode( '|', array(
		(string) ( $row['scheduled_date'] ?? '' ),
		(string) ( $row['scheduled_end'] ?? '' ),
		jrc_ct_norm( $meta['job_title'] ?? ( $row['type'] ?? '' ) ),
		jrc_ct_norm( $meta['shift_title'] ?? '' ),
		// Two buildings cleaned at the same time under the same title are two cleans.
		jrc_ct_norm( $meta['building'] ?? '' ),
	) ) );
}

/** Same start and end time: the test for whether a known shift still belongs to its job. */
function jrc_ct_same_time( $db_row, $row ) {
	return (string) $db_row->scheduled_date === (string) ( $row['scheduled_date'] ?? '' )
		&& (string) $db_row->scheduled_end === (string) ( $row['scheduled_end'] ?? '' );
}

/** Slot key of a stored jobs-table row, always computed from its current values. */
function jrc_ct_row_slot_key( $db_row ) {
	$meta = jrc_job_meta( $db_row );
	return jrc_ct_slot_key( array(
		'scheduled_date' => $db_row->scheduled_date,
		'scheduled_end'  => $db_row->scheduled_end,
		'type'           => $db_row->job_type,
		'address'        => $db_row->address,
	), $meta );
}

/**
 * The row a shift belongs to, and whether it is that row's primary shift.
 *
 * @return array array( row|null, is_primary )
 */
function jrc_ct_find_row_for_shift( $shift_id ) {
	global $wpdb;
	$t = jrc_jobs_table();

	$row = $wpdb->get_row( $wpdb->prepare(
		"SELECT * FROM $t WHERE source = %s AND external_ref = %s LIMIT 1",
		JRC_CT_SOURCE, (string) $shift_id
	) );
	if ( $row ) {
		return array( $row, true );
	}

	// Merged shift: its id sits in the JSON list. Shift ids are hex, so a LIKE on the
	// quoted id is exact; the decode below confirms it rather than trusting the match.
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM $t WHERE source = %s AND external_meta LIKE %s",
		JRC_CT_SOURCE, '%' . $wpdb->esc_like( '"' . $shift_id . '"' ) . '%'
	) );
	foreach ( $rows as $r ) {
		$m = jrc_job_meta( $r );
		if ( in_array( (string) $shift_id, array_map( 'strval', (array) ( $m['merged'] ?? array() ) ), true ) ) {
			return array( $r, false );
		}
	}
	return array( null, false );
}

/** An existing row occupying the same slot, if any (the target for a new cleaner's shift). */
function jrc_ct_find_slot_row( $row, $meta, $exclude_id = 0 ) {
	global $wpdb;
	if ( empty( $row['scheduled_date'] ) ) {
		return null;
	}
	$t   = jrc_jobs_table();
	$key = jrc_ct_slot_key( $row, $meta );

	$cands = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM $t WHERE source = %s AND scheduled_date = %s AND id <> %d",
		JRC_CT_SOURCE, $row['scheduled_date'], (int) $exclude_id
	) );
	foreach ( $cands as $c ) {
		if ( jrc_ct_row_slot_key( $c ) === $key ) {
			return $c;
		}
	}
	return null;
}

/**
 * The status a merged job shows: the furthest any of its shifts has got.
 *
 * @param array $statuses shift id => status.
 */
function jrc_ct_combined_status( $statuses ) {
	$statuses = array_values( (array) $statuses );
	foreach ( array( 'Completed', 'Booked', 'Pending' ) as $s ) {
		if ( in_array( $s, $statuses, true ) ) {
			return $s;
		}
	}
	return $statuses ? 'Cancelled' : 'Pending';
}

/**
 * Write a merged row's bookkeeping: merged ids, per-shift statuses, cleaner count, and
 * the combined status — respecting a status a human set by hand.
 *
 * @param object $db_row      Current row.
 * @param array  $merged      Merged (non-primary) shift ids.
 * @param array  $statuses    shift id => status, primary included.
 * @param int    $user_id     Attribution to apply if the row has none (0 = leave).
 * @param string $primary_ref Primary shift id to set (defaults to the current one).
 */
function jrc_ct_write_group( $db_row, $merged, $statuses, $user_id = 0, $primary_ref = '' ) {
	global $wpdb;
	$t    = jrc_jobs_table();
	$meta = jrc_job_meta( $db_row );

	$merged = array_values( array_unique( array_map( 'strval', $merged ) ) );
	$meta['merged']       = $merged;
	$meta['shift_status'] = $statuses;
	$meta['staff_count']  = 1 + count( $merged );
	$meta['slot']         = jrc_ct_row_slot_key( $db_row );

	$data = array( 'external_meta' => wp_json_encode( $meta ) );
	$fmt  = array( '%s' );

	if ( $primary_ref && $primary_ref !== (string) $db_row->external_ref ) {
		$data['external_ref'] = $primary_ref;
		$fmt[]                = '%s';
	}

	// Attribution only ever 0 -> someone, never moved between partners (same rule as the sync).
	if ( $user_id && 0 === (int) $db_row->user_id ) {
		$data['user_id'] = (int) $user_id;
		$fmt[]           = '%d';
	}

	$wpdb->update( $t, $data, array( 'id' => (int) $db_row->id ), $fmt, array( '%d' ) );

	// Status through jrc_job_update() so the timeline and tier date logic stay in one place.
	$combined = jrc_ct_combined_status( $statuses );
	if ( jrc_ct_should_write_status( $db_row->status ) && $combined !== (string) $db_row->status ) {
		$fresh = jrc_job_get( (int) $db_row->id );
		$date  = $fresh->job_date;
		if ( 'Completed' === $combined && ! $date && $fresh->scheduled_date ) {
			$date = gmdate( 'Y-m-d', strtotime( $fresh->scheduled_date ) );
		}
		jrc_job_patch( (int) $db_row->id, array(
			'date'   => $date,
			'status' => $combined,
			'source' => JRC_CT_SOURCE,
		), 'Connecteam sync' );
	}
}

/**
 * Remove a shift from its group (moved elsewhere, or deleted).
 *
 * @return string 'released' (others remain), 'last' (it was the only shift) or 'none'.
 */
function jrc_ct_release_shift( $db_row, $shift_id ) {
	$meta     = jrc_job_meta( $db_row );
	$merged   = array_values( array_diff( array_map( 'strval', (array) ( $meta['merged'] ?? array() ) ), array( (string) $shift_id ) ) );
	$statuses = (array) ( $meta['shift_status'] ?? array() );
	unset( $statuses[ (string) $shift_id ] );

	$is_primary = (string) $db_row->external_ref === (string) $shift_id;
	if ( $is_primary && ! $merged ) {
		return 'last';
	}
	if ( ! $is_primary && count( $merged ) === count( (array) ( $meta['merged'] ?? array() ) ) ) {
		return 'none';
	}

	$primary = (string) $db_row->external_ref;
	if ( $is_primary ) {
		// Another cleaner's shift takes over as the row's reference.
		$primary = array_shift( $merged );
	}
	jrc_ct_write_group( $db_row, $merged, $statuses, 0, $primary );
	return 'released';
}

// ---------------------------------------------------------------------------
// One-off clean-up of rows imported before merging existed
// ---------------------------------------------------------------------------

/**
 * Collapse existing duplicate rows into one per slot.
 *
 * Keeps, per slot: a row attributed to a partner if there is one, otherwise the oldest.
 * A duplicate is only removed when it carries nothing of its own — no report, no price,
 * and a status the integration owns. Anything else is left in place and reported, so
 * nothing staff typed or uploaded can be lost.
 *
 * @return array counts: slots, merged, kept_for_review
 */
function jrc_ct_dedupe_existing( $dry_run = true, $quiet = false ) {
	global $wpdb;
	$t    = jrc_jobs_table();
	$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $t WHERE source = %s ORDER BY id ASC", JRC_CT_SOURCE ) );

	$groups = array();
	foreach ( $rows as $r ) {
		if ( empty( $r->scheduled_date ) ) {
			continue;
		}
		$groups[ jrc_ct_row_slot_key( $r ) ][] = $r;
	}

	$out = array( 'slots' => 0, 'merged' => 0, 'kept_for_review' => 0, 'review_ids' => array() );

	foreach ( $groups as $members ) {
		if ( count( $members ) < 2 ) {
			continue;
		}
		$out['slots']++;

		usort( $members, function ( $a, $b ) {
			$pa = (int) $a->user_id > 0 ? 0 : 1;
			$pb = (int) $b->user_id > 0 ? 0 : 1;
			return $pa !== $pb ? $pa - $pb : (int) $a->id - (int) $b->id;
		} );
		$keep = array_shift( $members );

		$km       = jrc_job_meta( $keep );
		$merged   = array_map( 'strval', (array) ( $km['merged'] ?? array() ) );
		$statuses = (array) ( $km['shift_status'] ?? array() );
		$statuses[ (string) $keep->external_ref ] = $statuses[ (string) $keep->external_ref ] ?? (string) $keep->status;
		$uid      = 0;

		foreach ( $members as $dup ) {
			// A value only blocks a merge when it disagrees with the job being kept.
			$value_ok = ! (float) $dup->value || ! (float) $keep->value || abs( (float) $dup->value - (float) $keep->value ) < 0.005;
			$safe = ! $dup->report_file && $value_ok
				&& jrc_ct_should_write_status( $dup->status )
				&& ( 0 === (int) $dup->user_id || (int) $dup->user_id === (int) $keep->user_id || 0 === (int) $keep->user_id );
			if ( ! $safe ) {
				$out['kept_for_review']++;
				$out['review_ids'][] = (int) $dup->id;
				continue;
			}
			$dm        = jrc_job_meta( $dup );
			$merged[]  = (string) $dup->external_ref;
			$merged    = array_merge( $merged, array_map( 'strval', (array) ( $dm['merged'] ?? array() ) ) );
			$statuses[ (string) $dup->external_ref ] = (string) $dup->status;
			if ( (int) $dup->user_id && ! $uid ) {
				$uid = (int) $dup->user_id;
			}
			if ( (float) $dup->value && ! (float) $keep->value && ! $dry_run ) {
				jrc_ct_group_value( (int) $keep->id, array( 'value' => $dup->value ) );
				$keep->value = $dup->value;
			}
			$out['merged']++;
			if ( ! $dry_run ) {
				jrc_job_delete( (int) $dup->id );
			}
		}

		if ( ! $dry_run ) {
			jrc_ct_write_group( jrc_job_get( (int) $keep->id ), $merged, $statuses, $uid );
		}
	}

	// Quiet mode (the after-sync safety net) only logs when it actually merged something.
	if ( ! $dry_run && ( ! $quiet || $out['merged'] || $out['kept_for_review'] ) ) {
		jrc_ct_log( sprintf( 'Duplicate clean-up: %d slot(s), %d duplicate row(s) merged, %d left for review.', $out['slots'], $out['merged'], $out['kept_for_review'] ), 'info' );
	}
	return $out;
}

/**
 * Put shifts that are a job's primary first.
 *
 * When a whole multi-cleaner job is rescheduled, the primary must move the job before
 * the other cleaners' shifts are checked against it — otherwise each would look like a
 * cleaner moved away from the job and split off into a new one.
 */
function jrc_ct_order_shifts( $shifts ) {
	global $wpdb;
	$refs = array_flip( (array) $wpdb->get_col( $wpdb->prepare(
		'SELECT external_ref FROM ' . jrc_jobs_table() . ' WHERE source = %s',
		JRC_CT_SOURCE
	) ) );
	$first = array();
	$rest  = array();
	foreach ( (array) $shifts as $sh ) {
		if ( isset( $refs[ (string) ( $sh['id'] ?? '' ) ] ) ) {
			$first[] = $sh;
		} else {
			$rest[] = $sh;
		}
	}
	return array_merge( $first, $rest );
}

/**
 * A merged cleaner's shift supplies the job's value only if the job has none — the value
 * is per clean, so it is taken once, never added up across the cleaners on it.
 */
function jrc_ct_group_value( $job_id, $row, $shift_id = '' ) {
	global $wpdb;
	if ( '' !== (string) $shift_id && isset( $row['external_meta'] ) ) {
		$row['value'] = (string) ( $row['external_meta']['own_value'] ?? '' );
	}
	$has = isset( $row['value'] ) && '' !== (string) $row['value'] && (float) $row['value'];

	if ( '' === (string) $shift_id ) {
		// Clean-up path: only ever fill an empty value.
		if ( $has ) {
			$wpdb->query( $wpdb->prepare(
				'UPDATE ' . jrc_jobs_table() . ' SET value = %f WHERE id = %d AND value = 0',
				(float) $row['value'], (int) $job_id
			) );
		}
		return;
	}

	// Remember what each shift says, so the office can put the price on whichever
	// cleaner's shift they like — they cannot tell which one WordPress calls primary.
	$job  = jrc_job_get( (int) $job_id );
	$meta = jrc_job_meta( $job );
	$vals = (array) ( $meta['shift_value'] ?? array() );
	$vals[ (string) $shift_id ] = $has ? (string) $row['value'] : '';
	$meta['shift_value'] = $vals;
	$wpdb->update( jrc_jobs_table(), array( 'external_meta' => wp_json_encode( $meta ) ), array( 'id' => (int) $job_id ), array( '%s' ), array( '%d' ) );

	// The primary's own value wins; otherwise this shift's, being the latest said.
	$primary_val = (string) ( $vals[ (string) $job->external_ref ] ?? '' );
	if ( '' === $primary_val && $has ) {
		$wpdb->update( jrc_jobs_table(), array( 'value' => (float) $row['value'] ), array( 'id' => (int) $job_id ), array( '%f' ), array( '%d' ) );
	}
}

