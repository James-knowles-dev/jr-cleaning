<?php
/**
 * Getting Connecteam shifts into the jobs table, and keeping them there.
 *
 * Two paths, deliberately overlapping:
 *
 *   WEBHOOK        Connecteam pushes shift_created / shift_updated / shift_deleted the
 *                  moment they happen. Fast, and costs nothing against the rate limit
 *                  because it is inbound. But Connecteam retries a failed delivery
 *                  exactly three times and then gives up forever, so a deploy, a brief
 *                  outage or a PHP fatal during those three attempts loses the change
 *                  permanently.
 *
 *   RECONCILIATION A scheduled sweep of a date window, sorted by updated_at descending.
 *                  Slower and costs requests, but it is the thing that makes the
 *                  webhook's three-strikes rule survivable.
 *
 * Both funnel into jrc_ct_upsert_shift(), which writes through jrc_job_upsert_external()
 * — idempotent on (source, external_ref) — so the same shift arriving down both paths
 * converges on one row instead of racing to make two.
 *
 * DELETION IS NEVER A DELETION. A shift removed in Connecteam soft-cancels the WP row.
 * jrc_job_delete() cascades into jrc_job_report_delete(), which unlinks the PDF photo
 * report — so honouring a remote delete literally would destroy photographs of the
 * inside of a customer's home because somebody tidied a rota. Cancelled rows keep their
 * report and stay in the partner's history, which is also what the partner expects.
 *
 * @package jrc-connecteam
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const JRC_CT_CRON_HOOK   = 'jrc_connecteam_reconcile';
const JRC_CT_LOG_OPTION  = 'jrc_connecteam_log';

// ---------------------------------------------------------------------------
// Log
// ---------------------------------------------------------------------------

/**
 * Append to the sync log.
 *
 * An option ring buffer rather than a table: this is an operational breadcrumb trail
 * read by one person on one screen, and a table would need installing, migrating and
 * pruning to hold information nobody queries.
 */
function jrc_ct_log( $message, $level = 'info', $context = array() ) {
	$log = get_option( JRC_CT_LOG_OPTION, array() );
	$log = is_array( $log ) ? $log : array();

	array_unshift( $log, array(
		'at'      => current_time( 'mysql' ),
		'level'   => $level,
		'message' => (string) $message,
		'context' => $context,
	) );

	if ( count( $log ) > 60 ) {
		$log = array_slice( $log, 0, 60 );
	}

	update_option( JRC_CT_LOG_OPTION, $log, false );

	if ( 'error' === $level ) {
		jrc_ct_update_settings( array( 'last_error' => (string) $message ) );
	}
}

/** The log, newest first. */
function jrc_ct_log_entries() {
	$log = get_option( JRC_CT_LOG_OPTION, array() );
	return is_array( $log ) ? $log : array();
}

// ---------------------------------------------------------------------------
// Upsert
// ---------------------------------------------------------------------------

/** The existing row for a Connecteam shift id, or null. */
function jrc_ct_existing_row( $shift_id ) {
	global $wpdb;
	return $wpdb->get_row( $wpdb->prepare(
		'SELECT * FROM ' . jrc_jobs_table() . ' WHERE source = %s AND external_ref = %s LIMIT 1',
		JRC_CT_SOURCE,
		(string) $shift_id
	) );
}

/**
 * Import one shift.
 *
 * @param array $shift      Shift object from the API or a webhook.
 * @param array $jobs_index Flattened job index.
 * @param array $args       dry_run, note.
 * @return array{action:string,job_id:int,user_id:int,warnings:array,attribution:array,row:array}
 */
function jrc_ct_upsert_shift( $shift, $jobs_index, $args = array() ) {
	$args = wp_parse_args( $args, array(
		'dry_run' => false,
		'note'    => 'Connecteam sync',
	) );

	$s        = jrc_ct_settings();
	$shift_id = (string) ( $shift['id'] ?? '' );

	$result = array(
		'action'      => 'skipped',
		'job_id'      => 0,
		'user_id'     => 0,
		'warnings'    => array(),
		'attribution' => array( 'user_id' => 0, 'matched_on' => '', 'raw' => '' ),
		'row'         => array(),
		'shift_id'    => $shift_id,
	);

	if ( '' === $shift_id ) {
		$result['warnings'][] = 'Shift had no id.';
		return $result;
	}

	// Which job this shift belongs to: as its primary shift, or merged into a job for
	// another cleaner's shift in the same slot. See connecteam-merge.php.
	list( $existing, $is_primary ) = jrc_ct_find_row_for_shift( $shift_id );
	$mapped   = jrc_ct_map_shift( $shift, $jobs_index, $is_primary ? $existing : null );
	$own_status = jrc_ct_derive_status( $shift );
	$slot       = jrc_ct_slot_key( $mapped['row'], (array) ( $mapped['row']['external_meta'] ?? array() ) );
	$mapped['row']['external_meta']['slot'] = $slot;

	$result['warnings']    = $mapped['warnings'];
	$result['attribution'] = $mapped['attribution'];
	$result['user_id']     = $mapped['user_id'];
	$result['row']         = $mapped['row'];
	$result['job_title']   = $mapped['job_title'];
	$result['shift_title'] = $mapped['shift_title'];

    // A shift with no usable start time is not a booking. Importing it would put a row
    // on nobody's calendar and count against nothing.
	if ( '' === $mapped['row']['scheduled_date'] ) {
		$result['action'] = 'skipped_no_time';
		return $result;
	}

	// Unattributed work: JR's own jobs, or a partner reference that matched nothing.
	// Imported with user_id 0 when configured, which keeps the admin booking calendar
	// and the daily capacity figures honest. No partner can see a user_id 0 row —
	// every partner-facing query is scoped by user_id.
	if ( ! $mapped['user_id'] && empty( $s['import_unlinked'] ) ) {
		$result['action'] = 'skipped_unlinked';
		return $result;
	}

	// ---- one clean, one job -------------------------------------------------
	// A merged shift whose slot still matches only contributes its status and, at most,
	// an attribution. One that has moved leaves the group and is treated as new.
	if ( $existing && ! $is_primary ) {
		if ( jrc_ct_same_time( $existing, $mapped['row'] ) ) {
			$result['job_id'] = (int) $existing->id;
			if ( $args['dry_run'] ) {
				$result['action'] = 'would_merge';
				return $result;
			}
			$em       = jrc_job_meta( $existing );
			$statuses = (array) ( $em['shift_status'] ?? array() );
			$statuses[ $shift_id ] = $own_status;
			jrc_ct_write_group( $existing, (array) ( $em['merged'] ?? array() ), $statuses, $mapped['user_id'] );
			jrc_ct_group_value( (int) $existing->id, $mapped['row'], $shift_id );
			$result['action'] = 'merged';
			return $result;
		}
		if ( ! $args['dry_run'] ) {
			jrc_ct_release_shift( $existing, $shift_id );
		}
		$existing = null;
	}

	// The primary shift always drives its job — a time, title or address change moves the
	// job with it. Other cleaners' shifts that did not move split off on their own sync
	// (see the same-time test above), so nothing is lost either way.
	$em_existing = $existing ? jrc_job_meta( $existing ) : array();

	// A new shift for a slot that already has a job: another cleaner on the same clean.
	if ( ! $existing ) {
		$slot_row = jrc_ct_find_slot_row( $mapped['row'], (array) $mapped['row']['external_meta'] );
		if ( $slot_row ) {
			$result['job_id'] = (int) $slot_row->id;
			if ( $args['dry_run'] ) {
				$result['action'] = 'would_merge';
				return $result;
			}
			$sm       = jrc_job_meta( $slot_row );
			$merged   = (array) ( $sm['merged'] ?? array() );
			$merged[] = $shift_id;
			$statuses = (array) ( $sm['shift_status'] ?? array() );
			$statuses[ (string) $slot_row->external_ref ] = $statuses[ (string) $slot_row->external_ref ] ?? (string) $slot_row->status;
			$statuses[ $shift_id ] = $own_status;
			jrc_ct_write_group( $slot_row, $merged, $statuses, $mapped['user_id'] );
			jrc_ct_group_value( (int) $slot_row->id, $mapped['row'], $shift_id );
			$result['action'] = 'merged';
			return $result;
		}
	}

	// Primary update: keep the group's bookkeeping, which the mapper knows nothing about.
	$merged_ids = array_values( (array) ( $em_existing['merged'] ?? array() ) );
	$statuses   = (array) ( $em_existing['shift_status'] ?? array() );
	$statuses[ $shift_id ] = $own_status;
	$mapped['row']['external_meta']['merged']       = $merged_ids;
	$mapped['row']['external_meta']['shift_status'] = $statuses;
	$mapped['row']['external_meta']['staff_count']  = 1 + count( $merged_ids );
	// Per-shift values (see jrc_ct_group_value()); the primary records its own.
	$shift_vals = (array) ( $em_existing['shift_value'] ?? array() );
	$shift_vals[ $shift_id ] = (string) ( $mapped['row']['external_meta']['own_value'] ?? '' );
	$mapped['row']['external_meta']['shift_value'] = $shift_vals;
	if ( $merged_ids && ( ! $existing || jrc_ct_should_write_status( $existing->status ) ) ) {
		$mapped['row']['status'] = jrc_ct_combined_status( $statuses );
		if ( 'Completed' === $mapped['row']['status'] && empty( $mapped['row']['date'] ) && ! empty( $mapped['row']['scheduled_date'] ) ) {
			$mapped['row']['date'] = gmdate( 'Y-m-d', strtotime( $mapped['row']['scheduled_date'] ) );
		}
	}
	$result['row'] = $mapped['row'];

	if ( $args['dry_run'] ) {
		$result['action'] = $existing ? 'would_update' : 'would_create';
		$result['job_id'] = $existing ? (int) $existing->id : 0;
		return $result;
	}

	// Updated by row id rather than by external_ref: after a merge or a promotion the
	// row's reference may be another cleaner's shift id.
	if ( $existing ) {
		jrc_job_update( (int) $existing->id, $mapped['row'], $args['note'] );
		$job_id = (int) $existing->id;
	} else {
		$job_id = jrc_job_upsert_external( $mapped['user_id'], JRC_CT_SOURCE, $shift_id, $mapped['row'] );
	}

	if ( ! $job_id ) {
		$result['action']     = 'failed';
		$result['warnings'][] = 'The row could not be written.';
		return $result;
	}

	// jrc_job_upsert_external() only sets user_id on insert — an update leaves it alone,
	// because jrc_job_update() unsets the column deliberately so an edit cannot move a
	// job between partners by accident. Re-attribution is a real thing here though: a
	// job gets its partner field filled in days after the shift was created. So it is
	// applied explicitly, and only ever 0 -> real, never real -> different.
	if ( $existing && $mapped['user_id'] && (int) $existing->user_id !== $mapped['user_id'] ) {
		if ( 0 === (int) $existing->user_id ) {
			global $wpdb;
			$wpdb->update( jrc_jobs_table(), array( 'user_id' => $mapped['user_id'] ), array( 'id' => $job_id ), array( '%d' ), array( '%d' ) );
			jrc_ct_log( sprintf( 'Shift %s attributed to user %d.', $shift_id, $mapped['user_id'] ), 'info' );
		} else {
			$result['warnings'][] = sprintf(
				'Connecteam now points this job at user %d but it is already attributed to user %d; left unchanged.',
				$mapped['user_id'],
				(int) $existing->user_id
			);
		}
	}

	// The deep link back, so an administrator can open the shift in Connecteam.
	if ( ! empty( $s['scheduler_id'] ) ) {
		global $wpdb;
		$wpdb->update(
			jrc_jobs_table(),
			array( 'external_url' => esc_url_raw( 'https://app.connecteam.com/index.html#/scheduler/' . rawurlencode( $s['scheduler_id'] ) ) ),
			array( 'id' => $job_id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	$result['action'] = $existing ? 'updated' : 'created';
	$result['job_id'] = $job_id;

	return $result;
}

/**
 * Soft-cancel the row behind a deleted shift. See the file header.
 */
function jrc_ct_cancel_shift( $shift_id ) {
	list( $existing ) = jrc_ct_find_row_for_shift( $shift_id );
	if ( ! $existing ) {
		return false;
	}

	// One cleaner taken off a multi-person clean: the clean is still happening. Only
	// the last shift in the group cancels the job.
	$released = jrc_ct_release_shift( $existing, (string) $shift_id );
	if ( 'released' === $released ) {
		return true;
	}
	if ( 'none' === $released ) {
		return false;
	}
	$shift_id = (string) $existing->external_ref;

	if ( 0 === strcasecmp( (string) $existing->status, 'Cancelled' ) ) {
		return true;   // already there; do not write a duplicate timeline entry
	}

	jrc_job_patch(
		(int) $existing->id,
		array(
			'status'       => 'Cancelled',
			'source'       => JRC_CT_SOURCE,
			'external_ref' => (string) $shift_id,
		),
		'Removed from the Connecteam schedule'
	);

	return true;
}

// ---------------------------------------------------------------------------
// Windowed sync
// ---------------------------------------------------------------------------

/**
 * Import every shift overlapping a window.
 *
 * @param int   $from Unix seconds.
 * @param int   $to   Unix seconds.
 * @param array $args dry_run, priority, max_pages.
 * @return array|WP_Error Summary with per-shift detail.
 */
function jrc_ct_sync_window( $from, $to, $args = array() ) {
	$args = wp_parse_args( $args, array(
		'dry_run'   => false,
		'priority'  => 'interactive',
		'max_pages' => 6,
	) );

	if ( ! jrc_ct_configured() ) {
		return new WP_Error( 'jrc_ct_no_key', __( 'No Connecteam API key is configured.', 'jrc-connecteam' ) );
	}

	$jobs_index = jrc_ct_jobs_index();
	if ( is_wp_error( $jobs_index ) ) {
		return $jobs_index;
	}

	$shifts = jrc_ct_shifts( $from, $to, array(
		'priority'  => $args['priority'],
		'max_pages' => $args['max_pages'],
	) );
	if ( is_wp_error( $shifts ) ) {
		return $shifts;
	}

	$summary = array(
		'from'        => $from,
		'to'          => $to,
		'dry_run'     => (bool) $args['dry_run'],
		'total'       => count( $shifts ),
		'created'     => 0,
		'updated'     => 0,
		'skipped'     => 0,
		'failed'      => 0,
		'unlinked'    => 0,
		'merged'      => 0,
		'rows'        => array(),
	);

	foreach ( jrc_ct_order_shifts( $shifts ) as $shift ) {
		$r = jrc_ct_upsert_shift( $shift, $jobs_index, array( 'dry_run' => $args['dry_run'] ) );

		switch ( $r['action'] ) {
			case 'created':
			case 'would_create':
				$summary['created']++;
				break;
			case 'updated':
			case 'would_update':
				$summary['updated']++;
				break;
			case 'merged':
			case 'would_merge':
				// Another cleaner's shift on a clean already counted: folded into that job.
				$summary['merged']++;
				break;
			case 'failed':
				$summary['failed']++;
				break;
			default:
				$summary['skipped']++;
		}

		if ( ! $r['user_id'] && ! in_array( $r['action'], array( 'merged', 'would_merge' ), true ) ) {
			$summary['unlinked']++;
		}

		$summary['rows'][] = $r;
	}

	if ( ! $args['dry_run'] ) {
		// Safety net: fold any duplicate rows a mid-sync ordering edge case left behind.
		jrc_ct_dedupe_existing( false, true );
		jrc_ct_update_settings( array( 'last_sync' => time(), 'last_error' => '' ) );
		jrc_ct_log( sprintf(
			'Sync %s to %s: %d created, %d updated, %d skipped, %d unlinked.',
			gmdate( 'Y-m-d', $from ),
			gmdate( 'Y-m-d', $to ),
			$summary['created'],
			$summary['updated'],
			$summary['skipped'],
			$summary['unlinked']
		), $summary['failed'] ? 'warning' : 'info' );
	}

	return $summary;
}

// ---------------------------------------------------------------------------
// Scheduled sync
// ---------------------------------------------------------------------------

/**
 * How far either side of today each scheduled sync looks.
 *
 * Backwards far enough to catch a shift marked complete after the fact; forwards far
 * enough to cover the booking horizon. Narrow on purpose — on the smallest plan the
 * whole day's allowance is 100 requests, and a window twice this size costs twice as
 * much for information that has not changed.
 */
function jrc_ct_reconcile_window() {
	return (array) apply_filters( 'jrc_ct_reconcile_window', array(
		'back'    => 21,
		'forward' => 90,
	) );
}

/**
 * When the scheduled sync runs, in the site's local time.
 *
 * The client's schedule: hourly from 12:00 to 19:00 on weekdays (the hours the office is
 * booking and changing the rota), and once at midnight on Saturday and Sunday.
 *
 * Keyed by ISO day (1 = Monday … 7 = Sunday) to the hours that run. The last weekday
 * run is 19:00 and the weekend one is 00:00, both well before the 03:00 tier
 * recalculation, so a tier run always sees the latest completions.
 *
 * @return array<int,int[]>
 */
function jrc_ct_run_slots() {
	$weekday = range( 12, 19 );
	return (array) apply_filters( 'jrc_ct_run_slots', array(
		1 => $weekday,
		2 => $weekday,
		3 => $weekday,
		4 => $weekday,
		5 => $weekday,
		6 => array( 0 ),
		7 => array( 0 ),
	) );
}

/**
 * The next slot strictly after $after (a Unix timestamp), as a Unix timestamp.
 *
 * Worked in wp_timezone() rather than gmt_offset so the hours stay put across the
 * BST/GMT change — a fixed offset would run everything an hour out for half the year.
 *
 * @return int 0 if no slot exists in the coming fortnight (an empty schedule).
 */
function jrc_ct_next_run( $after = 0 ) {
	$after = $after ? (int) $after : time();
	$slots = jrc_ct_run_slots();
	$tz    = wp_timezone();

	// Start from the top of the next hour and walk forward an hour at a time.
	$t = ( new DateTimeImmutable( '@' . $after ) )->setTimezone( $tz );
	$t = $t->setTime( (int) $t->format( 'G' ), 0, 0 )->modify( '+1 hour' );

	for ( $i = 0; $i < 24 * 14; $i++ ) {
		$day  = (int) $t->format( 'N' );
		$hour = (int) $t->format( 'G' );
		if ( in_array( $hour, array_map( 'intval', (array) ( $slots[ $day ] ?? array() ) ), true ) ) {
			return $t->getTimestamp();
		}
		$t = $t->modify( '+1 hour' );
	}
	return 0;
}

/** Book the next run, replacing whatever is queued. */
function jrc_ct_schedule_next( $after = 0 ) {
	wp_clear_scheduled_hook( JRC_CT_CRON_HOOK );
	$next = jrc_ct_next_run( $after );
	if ( $next ) {
		wp_schedule_single_event( $next, JRC_CT_CRON_HOOK );
	}
	return $next;
}

/**
 * Keep exactly one run queued while the integration is active.
 *
 * Single events that re-book themselves rather than a recurring schedule: WordPress
 * recurrences are fixed intervals, and "hourly 12–19 on weekdays, midnight at weekends"
 * is not one. The sentinel replaces the old once-a-day recurring event on the first
 * request after deploy.
 */
add_action( 'init', function () {
	$event = wp_get_scheduled_event( JRC_CT_CRON_HOOK );

	if ( ! jrc_ct_active() ) {
		if ( $event ) {
			wp_clear_scheduled_hook( JRC_CT_CRON_HOOK );
		}
		return;
	}

	$stale = $event && false !== $event->schedule; // the old recurring 'daily' event
	if ( ! $event || $stale || get_option( 'jrc_ct_schedule_v' ) !== '2' ) {
		jrc_ct_schedule_next();
		update_option( 'jrc_ct_schedule_v', '2', false );
	}
} );

add_action( JRC_CT_CRON_HOOK, function () {
	if ( ! jrc_ct_active() ) {
		return;
	}

	// Book the next slot first, so a sync that dies part-way never stops the schedule.
	jrc_ct_schedule_next( time() );

	$w   = jrc_ct_reconcile_window();
	$now = current_time( 'timestamp' );

	$result = jrc_ct_sync_window(
		$now - ( (int) $w['back'] * DAY_IN_SECONDS ),
		$now + ( (int) $w['forward'] * DAY_IN_SECONDS ),
		array( 'priority' => 'background', 'max_pages' => 4 )
	);

	if ( is_wp_error( $result ) ) {
		jrc_ct_log( 'Scheduled sync failed: ' . $result->get_error_message(), 'error' );
	}
} );

add_action( 'switch_theme', function () {
	$ts = wp_next_scheduled( JRC_CT_CRON_HOOK );
	if ( $ts ) {
		wp_unschedule_event( $ts, JRC_CT_CRON_HOOK );
	}
} );

// ---------------------------------------------------------------------------
// Webhook
// ---------------------------------------------------------------------------

/**
 * The receiving endpoint.
 *
 * A REST route rather than a rewrite rule: it gets a stable, documented URL to paste
 * into Connecteam, it is exempt from page caching, and permission_callback lets the
 * signature check happen before any handler code runs.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'jrc/v1', '/connecteam/webhook', array(
		'methods'             => 'POST',
		'callback'            => 'jrc_ct_webhook_handle',
		// Public by design — Connecteam is not a logged-in WordPress user. Authentication
		// is the shared secret checked below, which is why that check is mandatory rather
		// than best-effort.
		'permission_callback' => 'jrc_ct_webhook_authorise',
	) );
} );

/** The URL to paste into Connecteam. */
function jrc_ct_webhook_url() {
	return rest_url( 'jrc/v1/connecteam/webhook' );
}

/**
 * Verify the shared secret.
 *
 * Connecteam signs with the `secretKey` given at registration. The exact header has
 * varied across their docs, so several are accepted; whichever arrives is compared in
 * constant time. A plain-equality check here would leak the secret one byte at a time
 * to anyone willing to time the responses.
 *
 * Refusing when no secret is configured is deliberate: an unauthenticated endpoint that
 * writes rows into a customer database is not something to leave open because setup was
 * not finished.
 */
function jrc_ct_webhook_authorise( WP_REST_Request $request ) {
	$s      = jrc_ct_settings();
	$secret = (string) $s['webhook_secret'];

	if ( '' === $secret ) {
		return new WP_Error( 'jrc_ct_no_secret', __( 'Webhook is not configured.', 'jrc-connecteam' ), array( 'status' => 403 ) );
	}

	$candidates = array();
	foreach ( array( 'x-connecteam-signature', 'x-webhook-signature', 'x-secret-key', 'x-api-key', 'authorization' ) as $header ) {
		$value = $request->get_header( $header );
		if ( $value ) {
			$candidates[] = trim( preg_replace( '/^Bearer\s+/i', '', $value ) );
		}
	}

	foreach ( $candidates as $candidate ) {
		// Either the raw secret, or an HMAC of the body with it — accept both so a change
		// in Connecteam's signing scheme does not silently drop every event.
		if ( hash_equals( $secret, $candidate ) ) {
			return true;
		}
		$expected = hash_hmac( 'sha256', (string) $request->get_body(), $secret );
		if ( hash_equals( $expected, $candidate ) || hash_equals( 'sha256=' . $expected, $candidate ) ) {
			return true;
		}
	}

	// The endpoint is public, so anyone can hit it. Log a rejection at most once every
	// ten minutes: the log is a 60-entry ring buffer, and an unthrottled write per bad
	// request would let a stranger flush the real entries out and hammer wp_options.
	if ( ! get_transient( 'jrc_ct_bad_sig_logged' ) ) {
		set_transient( 'jrc_ct_bad_sig_logged', 1, 10 * MINUTE_IN_SECONDS );
		jrc_ct_log( 'Rejected a webhook with a missing or wrong signature (further rejections in the next 10 minutes are not logged).', 'warning' );
	}
	return new WP_Error( 'jrc_ct_bad_signature', __( 'Invalid signature.', 'jrc-connecteam' ), array( 'status' => 401 ) );
}

/**
 * Handle a delivery.
 *
 * Always answers 200 once the signature is good, even when individual shifts fail.
 * Connecteam retries three times and then discards the event forever — so a transient
 * problem with one shift must not cost us the other nine in the same payload. The
 * the scheduled sync is what picks up anything dropped here, and the log records it.
 */
function jrc_ct_webhook_handle( WP_REST_Request $request ) {
	$body = $request->get_json_params();
	$body = is_array( $body ) ? $body : array();

	$event = (string) ( $body['eventType'] ?? '' );
	$data  = (array) ( $body['data'] ?? array() );

	if ( ! jrc_ct_active() ) {
		jrc_ct_log( 'Webhook received while the integration is switched off; ignored.', 'warning', array( 'event' => $event ) );
		return new WP_REST_Response( array( 'ok' => true, 'ignored' => true ), 200 );
	}

	$handled = 0;

	if ( 'shift_deleted' === $event ) {
		foreach ( (array) ( $data['ids'] ?? array() ) as $id ) {
			if ( jrc_ct_cancel_shift( (string) $id ) ) {
				$handled++;
			}
		}
		jrc_ct_log( sprintf( 'Webhook: %d shift(s) cancelled.', $handled ), 'info' );
		return new WP_REST_Response( array( 'ok' => true, 'handled' => $handled ), 200 );
	}

	if ( 'shift_created' === $event || 'shift_updated' === $event ) {
		$shifts = (array) ( $data['shifts'] ?? array() );
		if ( ! $shifts ) {
			return new WP_REST_Response( array( 'ok' => true, 'handled' => 0 ), 200 );
		}

		// Cached, so a burst of webhooks does not re-fetch the job list per event and
		// burn the rate limit that the webhooks were supposed to save.
		$jobs_index = jrc_ct_jobs_index();
		if ( is_wp_error( $jobs_index ) ) {
			jrc_ct_log( 'Webhook could not load the job list: ' . $jobs_index->get_error_message(), 'error' );
			// 200 regardless: a retry in ten seconds will hit the same problem, and the
			// the scheduled sync will fix it properly.
			return new WP_REST_Response( array( 'ok' => true, 'deferred' => true ), 200 );
		}

		foreach ( jrc_ct_order_shifts( $shifts ) as $shift ) {
			$r = jrc_ct_upsert_shift( $shift, $jobs_index, array( 'note' => 'Connecteam webhook' ) );
			if ( in_array( $r['action'], array( 'created', 'updated' ), true ) ) {
				$handled++;
			}
			if ( $r['warnings'] ) {
				jrc_ct_log( 'Webhook shift ' . $r['shift_id'] . ': ' . implode( ' ', $r['warnings'] ), 'warning' );
			}
		}

		jrc_ct_dedupe_existing( false, true );
		jrc_ct_log( sprintf( 'Webhook %s: %d of %d shift(s) written.', $event, $handled, count( $shifts ) ), 'info' );
		return new WP_REST_Response( array( 'ok' => true, 'handled' => $handled ), 200 );
	}

	// Availability events and anything Connecteam adds later. Acknowledged so they are
	// not retried, and logged so an unexpected event type is visible rather than silent.
	jrc_ct_log( 'Webhook event ignored: ' . ( $event ? $event : '(none)' ), 'info' );
	return new WP_REST_Response( array( 'ok' => true, 'ignored' => true ), 200 );
}

/**
 * Register the webhook with Connecteam.
 *
 * Called from the settings screen rather than automatically: registering a webhook is
 * a change to their account, and doing it as a side effect of saving a form is the kind
 * of thing that leaves three stale endpoints behind after a week of testing.
 */
function jrc_ct_register_webhook() {
	$s = jrc_ct_settings();

	if ( empty( $s['scheduler_id'] ) ) {
		return new WP_Error( 'jrc_ct_no_scheduler', __( 'Choose a scheduler first.', 'jrc-connecteam' ) );
	}

	$secret = (string) $s['webhook_secret'];
	if ( '' === $secret ) {
		$secret = wp_generate_password( 40, false, false );
		jrc_ct_update_settings( array( 'webhook_secret' => $secret ) );
	}

	$response = jrc_ct_request( 'POST', '/settings/v1/webhooks', array(
		'body' => array(
			'name'        => 'JR Cleaning website (' . wp_parse_url( home_url(), PHP_URL_HOST ) . ')',
			'url'         => jrc_ct_webhook_url(),
			'featureType' => 'shift_scheduler',
			'objectId'    => (int) $s['scheduler_id'],
			// v2 fires once per shift; v1 fires once per assigned user, so a three-cleaner
			// shift would arrive three times and be written three times.
			'eventTypes'  => array( 'shift_created', 'shift_updated', 'shift_deleted' ),
			'secretKey'   => $secret,
		),
	) );

	if ( is_wp_error( $response ) ) {
		jrc_ct_log( 'Webhook registration failed: ' . $response->get_error_message(), 'error' );
		return $response;
	}

	$id = (string) ( $response['data']['webhookId'] ?? ( $response['data']['id'] ?? '' ) );
	jrc_ct_update_settings( array( 'webhook_id' => $id ) );
	jrc_ct_log( 'Webhook registered' . ( $id ? ' (id ' . $id . ')' : '' ) . '.', 'info' );

	return $response;
}
