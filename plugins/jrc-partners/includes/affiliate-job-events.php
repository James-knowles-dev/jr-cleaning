<?php
/**
 * Job status history: the timeline behind "is it booked yet?".
 *
 * A job row carries one `status` and one `updated_at`. That answers where a clean is
 * now and nothing else — not when it was confirmed, not how long it sat as Requested
 * before anybody looked at it, not whether it was cancelled and reinstated. The
 * partner dashboard could therefore show a status and never explain it, which is
 * exactly the gap the phone call fills.
 *
 * So every status change is appended to its own table. This is the same shape as
 * includes/affiliate-tier-history.php, with one deliberate difference: tier history
 * lives in user meta because it is short, per-user and read whenever the user is
 * loaded. Job events are per-job, unbounded, and want to be queried ACROSS jobs for
 * the SLA figures at the bottom of this file — that is a table, not serialised meta.
 *
 * WHERE EVENTS COME FROM. Nothing in this file hooks a form or a screen. jrc_job_insert()
 * and jrc_job_update() in includes/affiliate-jobs.php fire `jrc_job_status_changed`,
 * and every write path in the theme already routes through those two — the profile
 * screen, the partner request form, the external upsert, and anything added later.
 * One listener therefore covers all of them, the same way jrc_affiliate_tier_meta_watch()
 * covers every tier write by hooking the meta update rather than the profile save.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'JRC_JOB_EVENTS_DB_VERSION', '1.0.0' );

/** Fully-qualified table name. */
function jrc_job_events_table() {
	global $wpdb;
	return $wpdb->prefix . 'jrc_job_events';
}

/** Create or upgrade the table. Runs on admin_init, cheap after the first pass. */
function jrc_job_events_install() {
	if ( get_option( 'jrc_job_events_db_version' ) === JRC_JOB_EVENTS_DB_VERSION ) {
		return;
	}

	global $wpdb;
	$table   = jrc_job_events_table();
	$charset = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// NB: dbDelta is fussy — two spaces after PRIMARY KEY, one space between KEY and
	// its name, lowercase types. Do not reformat.
	dbDelta( "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		job_id bigint(20) unsigned NOT NULL DEFAULT 0,
		status_from varchar(32) NOT NULL DEFAULT '',
		status_to varchar(32) NOT NULL DEFAULT '',
		changed_by bigint(20) unsigned NOT NULL DEFAULT 0,
		changed_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		note varchar(255) NOT NULL DEFAULT '',
		PRIMARY KEY  (id),
		KEY job_id (job_id),
		KEY changed_at (changed_at),
		KEY status_to (status_to)
	) $charset;" );

	update_option( 'jrc_job_events_db_version', JRC_JOB_EVENTS_DB_VERSION );
}
add_action( 'admin_init', 'jrc_job_events_install' );

/**
 * Is the table there yet?
 *
 * Installation runs on admin_init, so on a fresh deploy a partner could in principle
 * submit a request through the front end before any administrator has loaded a screen.
 * Recording would then be a SQL error on an otherwise successful booking. An option
 * read is cached by WordPress and costs nothing, so check rather than risk it.
 */
function jrc_job_events_ready() {
	return (bool) get_option( 'jrc_job_events_db_version' );
}

// ---------------------------------------------------------------------------
// Recording
// ---------------------------------------------------------------------------

/**
 * Append one status change.
 *
 * @param int    $job_id Job.
 * @param string $from   Status before ('' for the row's first status).
 * @param string $to     Status after.
 * @param string $note   Optional free text, e.g. how the change was made.
 * @return int New event id, or 0.
 */
function jrc_job_event_record( $job_id, $from, $to, $note = '' ) {
	global $wpdb;

	$job_id = (int) $job_id;
	$from   = sanitize_text_field( (string) $from );
	$to     = sanitize_text_field( (string) $to );

	if ( ! $job_id || ! jrc_job_events_ready() ) {
		return 0;
	}

	// A save that did not touch the status is not an event. jrc_job_update() rewrites
	// every column on every profile save, so without this a partner's timeline would
	// fill with "Booked → Booked" the moment anyone edited an address.
	if ( $from === $to ) {
		return 0;
	}

	$ok = $wpdb->insert( jrc_job_events_table(), array(
		'job_id'      => $job_id,
		'status_from' => $from,
		'status_to'   => $to,
		'changed_by'  => get_current_user_id(),
		'changed_at'  => current_time( 'mysql' ),
		'note'        => sanitize_text_field( $note ),
	) );

	return $ok ? (int) $wpdb->insert_id : 0;
}

/**
 * The single listener. See the file header for why this is the only one needed.
 *
 * @param int    $job_id Job.
 * @param string $from   Previous status.
 * @param string $to     New status.
 * @param string $note   Context supplied by the caller.
 */
add_action( 'jrc_job_status_changed', function ( $job_id, $from, $to, $note = '' ) {
	jrc_job_event_record( $job_id, $from, $to, $note );
}, 10, 4 );

/**
 * Drop a job's events when the job goes.
 *
 * Orphaned rows would otherwise skew every SLA average, because those queries join on
 * nothing — they read the events table alone.
 */
add_action( 'jrc_job_deleted', function ( $job_id ) {
	global $wpdb;
	if ( ! jrc_job_events_ready() ) {
		return;
	}
	$wpdb->delete( jrc_job_events_table(), array( 'job_id' => (int) $job_id ), array( '%d' ) );
} );

// ---------------------------------------------------------------------------
// Reading
// ---------------------------------------------------------------------------

/**
 * One job's history, oldest first.
 *
 * @param int $job_id Job.
 * @param int $limit  Safety ceiling; a job with more history than this has a problem
 *                    that a longer list would not solve.
 * @return array<int,object>
 */
function jrc_job_events( $job_id, $limit = 50 ) {
	global $wpdb;

	$job_id = (int) $job_id;
	if ( ! $job_id || ! jrc_job_events_ready() ) {
		return array();
	}

	return (array) $wpdb->get_results( $wpdb->prepare(
		'SELECT * FROM ' . jrc_job_events_table() . ' WHERE job_id = %d ORDER BY changed_at ASC, id ASC LIMIT %d',
		$job_id,
		(int) $limit
	) );
}

/**
 * Events for many jobs in one query, keyed by job id.
 *
 * The jobs tab renders fifteen rows and the calendar a month's worth. Calling
 * jrc_job_events() per row would be fifteen to forty queries on a page that currently
 * runs two, so the templates fetch in bulk and index the result.
 *
 * @param int[] $job_ids Jobs.
 * @return array<int,array<int,object>>
 */
function jrc_job_events_for( array $job_ids ) {
	global $wpdb;

	$job_ids = array_values( array_unique( array_filter( array_map( 'intval', $job_ids ) ) ) );
	if ( ! $job_ids || ! jrc_job_events_ready() ) {
		return array();
	}

	// Placeholders built from a count, never from the values — the ids are already
	// integers, but this keeps the query prepared rather than interpolated.
	$in   = implode( ',', array_fill( 0, count( $job_ids ), '%d' ) );
	$rows = $wpdb->get_results( $wpdb->prepare(
		'SELECT * FROM ' . jrc_job_events_table() . " WHERE job_id IN ($in) ORDER BY changed_at ASC, id ASC",
		$job_ids
	) );

	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[ (int) $r->job_id ][] = $r;
	}
	return $out;
}

/**
 * When a job first reached a given status, as a timestamp, or 0.
 *
 * Uses the FIRST occurrence deliberately: a job cancelled and reinstated was still
 * confirmed on the original date, and an SLA measured to the second confirmation would
 * flatter the response time.
 */
function jrc_job_first_reached( $events, $status ) {
	foreach ( (array) $events as $e ) {
		if ( 0 === strcasecmp( $e->status_to, $status ) ) {
			return strtotime( $e->changed_at );
		}
	}
	return 0;
}

// ---------------------------------------------------------------------------
// Presentation
// ---------------------------------------------------------------------------

/**
 * What each status means to a partner, in their words rather than ours.
 *
 * The raw values are an internal workflow vocabulary ("Pending" means pending on OUR
 * side, which reads to a branch manager as though they have something to do).
 */
function jrc_job_status_meaning() {
	return apply_filters( 'jrc_job_status_meaning', array(
		'requested' => 'You asked us for this clean',
		'pending'   => 'With our team to schedule',
		'booked'    => 'Confirmed and in the diary',
		'completed' => 'Clean finished',
		'cancelled' => 'Cancelled',
	) );
}

/** One status's partner-facing description, or '' if we have nothing to add. */
function jrc_job_status_meaning_for( $status ) {
	$map = jrc_job_status_meaning();
	return (string) ( $map[ strtolower( trim( (string) $status ) ) ] ?? '' );
}

/**
 * Render a job's timeline.
 *
 * Returns markup rather than echoing so the calendar can collect it into the modal
 * buffer alongside everything else.
 *
 * @param array $events From jrc_job_events() / jrc_job_events_for().
 * @param array $args   `created` — the job's created_at, used for the opening entry.
 * @return string
 */
function jrc_job_timeline_html( $events, $args = array() ) {
	$events = (array) $events;

	if ( ! $events ) {
		return '';
	}

	$out = '<ol class="aff-timeline">';

	foreach ( $events as $e ) {
		$to      = (string) $e->status_to;
		$slug    = sanitize_html_class( strtolower( str_replace( ' ', '-', $to ) ) );
		$ts      = strtotime( $e->changed_at );
		$meaning = jrc_job_status_meaning_for( $to );

		$out .= '<li class="aff-timeline__item aff-timeline__item--' . esc_attr( $slug ) . '">'
			. '<span class="aff-timeline__dot" aria-hidden="true"></span>'
			. '<span class="aff-timeline__body">'
			. '<span class="aff-timeline__status">' . esc_html( $to ) . '</span>'
			. ( $meaning ? '<span class="aff-timeline__meaning">' . esc_html( $meaning ) . '</span>' : '' )
			// The machine-readable datetime is the full stamp; the visible text is the
			// friendly form. A screen reader gets the same information either way.
			. '<time class="aff-timeline__when" datetime="' . esc_attr( gmdate( 'c', $ts ) ) . '">'
			. esc_html( date_i18n( 'j M Y, H:i', $ts ) )
			. '</time>'
			. ( $e->note ? '<span class="aff-timeline__note">' . esc_html( $e->note ) . '</span>' : '' )
			. '</span>'
			. '</li>';
	}

	$out .= '</ol>';

	return $out;
}

// ---------------------------------------------------------------------------
// SLA
// ---------------------------------------------------------------------------

/**
 * How long requests are taking to confirm.
 *
 * This is the figure the programme is actually judged on: a partner who submits a
 * request wants to know somebody looked at it. Measured from the first `Requested`
 * event to the first `Booked` event on the same job.
 *
 * Median as well as mean, because one request that sat over a bank holiday weekend
 * drags an average badly and would otherwise be the number on the screen.
 *
 * @param array $args `days` — how far back to look. `user_id` — one partner, or 0 for all.
 * @return array{sample:int,mean_hours:float,median_hours:float,worst_hours:float,open:int}
 */
function jrc_job_sla_stats( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args( $args, array(
		'days'    => 90,
		'user_id' => 0,
	) );

	$none = array( 'sample' => 0, 'mean_hours' => 0.0, 'median_hours' => 0.0, 'worst_hours' => 0.0, 'open' => 0 );

	if ( ! jrc_job_events_ready() ) {
		return $none;
	}

	$events = jrc_job_events_table();
	$jobs   = jrc_jobs_table();
	$since  = gmdate( 'Y-m-d H:i:s', strtotime( '-' . max( 1, (int) $args['days'] ) . ' days', current_time( 'timestamp' ) ) );

	// Earliest Requested and earliest Booked per job, in one pass. MIN() over a
	// conditional beats two self-joins and keeps the whole thing to a single query.
	$where = 'e.changed_at >= %s';
	$prep  = array( $since );

	if ( $args['user_id'] ) {
		$where .= ' AND j.user_id = %d';
		$prep[] = (int) $args['user_id'];
	}

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT e.job_id,
			MIN(CASE WHEN e.status_to = 'Requested' THEN e.changed_at END) AS requested_at,
			MIN(CASE WHEN e.status_to = 'Booked'    THEN e.changed_at END) AS booked_at
		FROM $events e
		INNER JOIN $jobs j ON j.id = e.job_id
		WHERE $where
		GROUP BY e.job_id",
		$prep
	) );

	$hours = array();
	$open  = 0;

	foreach ( (array) $rows as $r ) {
		if ( ! $r->requested_at ) {
			continue;
		}
		if ( ! $r->booked_at ) {
			$open++;   // Requested and still not confirmed.
			continue;
		}
		$delta = strtotime( $r->booked_at ) - strtotime( $r->requested_at );
		if ( $delta >= 0 ) {
			$hours[] = $delta / HOUR_IN_SECONDS;
		}
	}

	if ( ! $hours ) {
		$none['open'] = $open;
		return $none;
	}

	sort( $hours );
	$n   = count( $hours );
	$mid = (int) floor( $n / 2 );

	return array(
		'sample'       => $n,
		'mean_hours'   => array_sum( $hours ) / $n,
		'median_hours' => 0 === $n % 2 ? ( $hours[ $mid - 1 ] + $hours[ $mid ] ) / 2 : $hours[ $mid ],
		'worst_hours'  => end( $hours ),
		'open'         => $open,
	);
}

/** "4 hours", "2 days" — a duration a human reads without converting it. */
function jrc_job_duration_label( $hours ) {
	$hours = (float) $hours;

	if ( $hours < 1 ) {
		$mins = max( 1, (int) round( $hours * 60 ) );
		return $mins . ' ' . _n( 'minute', 'minutes', $mins, 'jrc-partners' );
	}
	if ( $hours < 48 ) {
		$h = (int) round( $hours );
		return $h . ' ' . _n( 'hour', 'hours', $h, 'jrc-partners' );
	}
	$d = (int) round( $hours / 24 );
	return $d . ' ' . _n( 'day', 'days', $d, 'jrc-partners' );
}
