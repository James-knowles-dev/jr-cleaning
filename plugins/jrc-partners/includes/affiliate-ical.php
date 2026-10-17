<?php
/**
 * iCalendar feed for a partner's cleans.
 *
 * The calendar tab is a good month grid, but it lives on this site and estate agents
 * live in Outlook. A subscription URL puts their cleans in the diary they already have
 * open, and updates itself as the schedule changes.
 *
 * AUTHENTICATION. Calendar clients poll unauthenticated and cannot be sent through a
 * login, so a nonce is no use here — the URL itself has to be the credential. Hence a
 * 40-character random token in user meta rather than anything derived from the user id,
 * and a "generate a new link" button that invalidates the old one. The feed carries
 * property addresses, so the UI says plainly that anyone holding the link can read the
 * schedule.
 *
 * The per-job "add to calendar" download is a different case: the partner is logged in
 * at that point, so it uses an ownership check and a nonce like the report download.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** How long a clean is assumed to take, absent an end time in the data. */
function jrc_ical_duration() {
	return (int) apply_filters( 'jrc_ical_duration_minutes', 120 );
}

/**
 * The partner's subscription token, creating one on first use.
 *
 * @param int  $user_id Affiliate.
 * @param bool $create  Generate one if none exists.
 */
function jrc_ical_token( $user_id = 0, $create = true ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$token   = (string) get_user_meta( $user_id, 'jrc_ical_token', true );

	if ( '' === $token && $create ) {
		$token = wp_generate_password( 40, false, false );
		update_user_meta( $user_id, 'jrc_ical_token', $token );
	}

	return $token;
}

/** The subscription URL. */
function jrc_ical_url( $user_id = 0 ) {
	$token = jrc_ical_token( $user_id );
	return $token ? add_query_arg( 'jrc_ical', $token, home_url( '/' ) ) : '';
}

/**
 * Replace the token, breaking every existing subscription.
 *
 * Offered because a subscription URL, once pasted into a shared calendar or forwarded
 * on, cannot be taken back any other way.
 */
function jrc_ical_regenerate( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	delete_user_meta( $user_id, 'jrc_ical_token' );
	return jrc_ical_token( $user_id );
}

add_action( 'wp_loaded', function () {
	if ( empty( $_POST['jrc_ical_regenerate'] ) ) {
		return;
	}
	$user_id = get_current_user_id();
	if ( ! $user_id || ! jrc_is_affiliate( $user_id ) ) {
		return;
	}
	if ( ! isset( $_POST['jrc_ical_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jrc_ical_nonce'] ) ), 'jrc_ical_' . $user_id ) ) {
		wp_die( esc_html__( 'That form has expired. Reload the page and try again.', 'jrc-partners' ), 403 );
	}

	jrc_ical_regenerate( $user_id );

	$back = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'cleaning-calendar' )
		: home_url( '/my-account/cleaning-calendar/' );

	wp_safe_redirect( add_query_arg( 'ical', 'new', $back ) . '#subscribe' );
	exit;
} );

// ---------------------------------------------------------------------------
// Building the file
// ---------------------------------------------------------------------------

/** Escape a value for a TEXT property (RFC 5545 §3.3.11). */
function jrc_ics_escape( $text ) {
	$text = (string) $text;
	$text = str_replace( array( "\\", ';', ',' ), array( "\\\\", '\;', '\\,' ), $text );
	$text = preg_replace( '/\r\n|\r|\n/', '\\n', $text );
	return $text;
}

/**
 * Fold a content line to 75 octets (RFC 5545 §3.1).
 *
 * Counted in bytes, not characters, and the split is guarded so it never lands inside a
 * multi-byte sequence — an address with an é in it would otherwise arrive as mojibake
 * in half the calendar clients that read it.
 */
function jrc_ics_fold( $line ) {
	if ( strlen( $line ) <= 75 ) {
		return $line;
	}

	$out   = '';
	$chunk = '';
	$len   = 0;

	foreach ( preg_split( '//u', $line, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
		$size = strlen( $char );
		// 74 for continuation lines: the leading space counts towards the 75.
		$max = ( '' === $out ) ? 75 : 74;
		if ( $len + $size > $max ) {
			$out  .= ( '' === $out ? '' : "\r\n " ) . $chunk;
			$chunk = '';
			$len   = 0;
		}
		$chunk .= $char;
		$len   += $size;
	}

	if ( '' !== $chunk ) {
		$out .= ( '' === $out ? '' : "\r\n " ) . $chunk;
	}

	return $out;
}

/** UTC stamp in iCalendar's basic format. */
function jrc_ics_utc( $mysql_datetime ) {
	try {
		$dt = new DateTime( $mysql_datetime, wp_timezone() );
	} catch ( Exception $e ) {
		return '';
	}
	$dt->setTimezone( new DateTimeZone( 'UTC' ) );
	return $dt->format( 'Ymd\THis\Z' );
}

/**
 * Turn job rows into a VCALENDAR document.
 *
 * @param array  $rows  Rows from jrc_jobs_query().
 * @param string $title Calendar name shown in the client.
 * @return string
 */
function jrc_ics_document( $rows, $title ) {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );
	$now  = gmdate( 'Ymd\THis\Z' );

	$lines = array(
		'BEGIN:VCALENDAR',
		'VERSION:2.0',
		'PRODID:-//' . jrc_ics_escape( get_bloginfo( 'name' ) ) . '//Partner cleans//EN',
		'CALSCALE:GREGORIAN',
		'METHOD:PUBLISH',
		'X-WR-CALNAME:' . jrc_ics_escape( $title ),
		'X-WR-TIMEZONE:' . jrc_ics_escape( wp_timezone_string() ),
		// Outlook and Google both honour this as a polling hint. Cleans move about, so
		// an hour is a reasonable compromise between freshness and hammering the site.
		'X-PUBLISHED-TTL:PT1H',
		'REFRESH-INTERVAL;VALUE=DURATION:PT1H',
	);

	foreach ( $rows as $r ) {
		if ( empty( $r->scheduled_date ) ) {
			continue;
		}

		$start = jrc_ics_utc( $r->scheduled_date );
		if ( ! $start ) {
			continue;
		}

		// A real end time where we have one. Rows imported from Connecteam carry the
		// shift's actual finish, so a 45-minute oven clean stops looking like a two-hour
		// block in the partner's diary. jrc_ical_duration() remains the fallback for
		// manually-entered jobs, which have a start and nothing else.
		if ( ! empty( $r->scheduled_end ) && strtotime( $r->scheduled_end ) > strtotime( $r->scheduled_date ) ) {
			$end = jrc_ics_utc( $r->scheduled_end );
		} else {
			$end_ts = strtotime( $r->scheduled_date ) + ( jrc_ical_duration() * MINUTE_IN_SECONDS );
			$end    = jrc_ics_utc( gmdate( 'Y-m-d H:i:s', $end_ts ) );
		}

		$summary = trim( ( $r->job_type ?: 'Clean' ) . ( $r->address ? ' — ' . $r->address : '' ) );

		$description = array();
		if ( $r->status ) {
			$description[] = 'Status: ' . $r->status;
		}
		if ( $r->scope ) {
			$description[] = $r->scope;
		}

		$lines[] = 'BEGIN:VEVENT';
		// Stable across regenerations so an edited clean updates in place rather than
		// arriving as a second entry.
		$lines[] = 'UID:jrc-job-' . (int) $r->id . '@' . $host;
		$lines[] = 'DTSTAMP:' . $now;
		$lines[] = 'DTSTART:' . $start;
		$lines[] = 'DTEND:' . $end;
		$lines[] = 'SUMMARY:' . jrc_ics_escape( $summary );
		if ( $r->address ) {
			$lines[] = 'LOCATION:' . jrc_ics_escape( $r->address );
		}
		if ( $description ) {
			$lines[] = 'DESCRIPTION:' . jrc_ics_escape( implode( "\n", $description ) );
		}
		// Cancelled work is published as cancelled rather than dropped, so a client that
		// already has the event removes it instead of leaving a ghost in the diary.
		$lines[] = 'STATUS:' . ( 0 === strcasecmp( (string) $r->status, 'Cancelled' ) ? 'CANCELLED' : 'CONFIRMED' );
		if ( $r->updated_at ) {
			$lines[] = 'LAST-MODIFIED:' . jrc_ics_utc( $r->updated_at );
		}
		$lines[] = 'END:VEVENT';
	}

	$lines[] = 'END:VCALENDAR';

	$folded = array_map( 'jrc_ics_fold', $lines );

	// CRLF throughout, and a trailing one: some clients reject a file without it.
	return implode( "\r\n", $folded ) . "\r\n";
}

/** Send the headers a calendar file needs and stop. */
function jrc_ics_output( $ics, $filename ) {
	nocache_headers();
	header( 'Content-Type: text/calendar; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $filename ) . '"' );
	header( 'Content-Length: ' . strlen( $ics ) );
	header( 'X-Content-Type-Options: nosniff' );
	echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- iCalendar document, escaped per RFC 5545 in jrc_ics_escape().
	exit;
}

// ---------------------------------------------------------------------------
// The subscription feed
// ---------------------------------------------------------------------------

add_action( 'template_redirect', function () {
	if ( empty( $_GET['jrc_ical'] ) ) {
		return;
	}

	$token = sanitize_text_field( wp_unslash( $_GET['jrc_ical'] ) );

	// Length-checked before the query so a junk value never reaches the database, and
	// so a short token can never be brute-forced into existence.
	if ( strlen( $token ) < 32 ) {
		wp_die( esc_html__( 'That calendar link is not valid.', 'jrc-partners' ), 404 );
	}

	$users = get_users( array(
		'meta_key'   => 'jrc_ical_token', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value' => $token,           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'number'     => 1,
		'fields'     => 'ids',
	) );

	$user_id = $users ? (int) $users[0] : 0;

	// hash_equals as well as the lookup: the meta comparison is done by MySQL, whose
	// collation is case-insensitive, so re-check the token exactly as stored.
	if ( ! $user_id || ! hash_equals( (string) get_user_meta( $user_id, 'jrc_ical_token', true ), $token ) ) {
		wp_die( esc_html__( 'That calendar link is not valid. It may have been replaced — sign in and copy the current one.', 'jrc-partners' ), 404 );
	}

	if ( ! jrc_is_affiliate( $user_id ) ) {
		wp_die( esc_html__( 'That calendar is no longer available.', 'jrc-partners' ), 404 );
	}

	// A window rather than everything: a calendar client does not need a clean from
	// three years ago, and an unbounded feed grows without limit.
	$rows = jrc_jobs_query( array(
		'user_id'    => $user_id,
		'date_field' => 'scheduled_date',
		'date_from'  => gmdate( 'Y-m-d', strtotime( '-6 months', current_time( 'timestamp' ) ) ),
		'date_to'    => gmdate( 'Y-m-d', strtotime( '+18 months', current_time( 'timestamp' ) ) ),
		'orderby'    => 'scheduled_date',
		'order'      => 'ASC',
		'limit'      => 1000,
	) );

	$branch = (string) get_user_meta( $user_id, 'jrc_branch_name', true );
	$title  = ( $branch ? $branch . ' — ' : '' ) . get_bloginfo( 'name' ) . ' cleans';

	jrc_ics_output( jrc_ics_document( $rows, $title ), 'jr-cleaning-cleans.ics' );
} );

// ---------------------------------------------------------------------------
// One-off "add to calendar"
// ---------------------------------------------------------------------------

/** Nonce-signed download URL for a single clean. */
function jrc_ics_job_url( $job_id ) {
	return wp_nonce_url(
		add_query_arg( 'jrc_ics', (int) $job_id, home_url( '/' ) ),
		'jrc_ics_' . (int) $job_id
	);
}

add_action( 'template_redirect', function () {
	if ( empty( $_GET['jrc_ics'] ) ) {
		return;
	}

	$job_id = (int) $_GET['jrc_ics'];

	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'jrc_ics_' . $job_id ) ) {
		wp_die( esc_html__( 'That link has expired. Go back to your calendar and try again.', 'jrc-partners' ), 403 );
	}

	$job = jrc_job_get( $job_id );

	// Same ownership rule as a report, and the same deliberately vague message so a
	// missing job and someone else's job are indistinguishable from outside.
	$allowed = $job && ( current_user_can( jrc_reports_cap() ) || (int) $job->user_id === get_current_user_id() );
	if ( ! $allowed || empty( $job->scheduled_date ) ) {
		wp_die( esc_html__( 'That clean is not available.', 'jrc-partners' ), 404 );
	}

	jrc_ics_output(
		jrc_ics_document( array( $job ), get_bloginfo( 'name' ) ),
		'clean-' . gmdate( 'Y-m-d', strtotime( $job->scheduled_date ) ) . '.ics'
	);
} );
