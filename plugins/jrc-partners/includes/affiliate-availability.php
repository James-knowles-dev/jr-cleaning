<?php
/**
 * Booking availability: turning the request form into something with a diary behind it.
 *
 * "Request a clean" accepted any future date and a morning/afternoon preference. That
 * made it a wishlist — a partner could ask for Christmas Eve, or for the fourth clean
 * on a day already carrying three, and the first anyone knew was when the office rang
 * back to say no. Every one of those calls is the thing the dashboard exists to avoid.
 *
 * Availability is decided by three things, cheapest check first:
 *
 *   1. The booking window. Minimum notice and how far ahead requests are accepted,
 *      both from Partner Programme → Settings → Booking capacity.
 *   2. Blocked dates. Individual days closed by hand on the Availability screen —
 *      bank holidays, the Christmas shutdown, a day the vans are off the road.
 *   3. Capacity. How many cleans are already scheduled that day against the daily
 *      limit. 0 means unlimited, which is exactly how the form behaved before.
 *
 * ENFORCED SERVER-SIDE, always. The form disables unavailable dates in the browser and
 * that is a courtesy, not a control — includes/affiliate-requests.php calls
 * jrc_date_availability() before it writes a row, so a hand-crafted POST is refused the
 * same as a click would have been.
 *
 * WHAT COUNTS TOWARDS CAPACITY. Scheduled, non-cancelled work across every partner,
 * plus jobs with no partner at all (a direct booking taken over the phone occupies a
 * van exactly as a referred one does). Enquiries and requests count too — an
 * unconfirmed request is still a claim on the day, and letting six partners all request
 * the same Friday because none of them was confirmed yet would defeat the point.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Option holding the hand-blocked dates: Y-m-d => reason. */
const JRC_BLOCKED_DATES_OPTION = 'jrc_blocked_dates';

/**
 * Every blocked date.
 *
 * Past dates are pruned on read rather than on a schedule: the list is small, this is
 * the only reader, and a cron to tidy an option is more moving parts than the problem
 * deserves.
 *
 * @return array<string,string> Y-m-d => reason
 */
function jrc_blocked_dates() {
	$dates = get_option( JRC_BLOCKED_DATES_OPTION, array() );
	if ( ! is_array( $dates ) ) {
		return array();
	}

	$today = current_time( 'Y-m-d' );
	$out   = array();
	foreach ( $dates as $ymd => $reason ) {
		if ( (string) $ymd >= $today ) {
			$out[ (string) $ymd ] = (string) $reason;
		}
	}

	ksort( $out );
	return $out;
}

/** Replace the blocked-date list. */
function jrc_set_blocked_dates( array $dates ) {
	$clean = array();
	foreach ( $dates as $ymd => $reason ) {
		// Both a format check and a real-date check: '2026-02-31' matches the pattern
		// and is not a day.
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $ymd ) ) {
			continue;
		}
		list( $y, $m, $d ) = array_map( 'intval', explode( '-', $ymd ) );
		if ( ! checkdate( $m, $d, $y ) ) {
			continue;
		}
		$clean[ $ymd ] = sanitize_text_field( (string) $reason );
	}
	ksort( $clean );
	return update_option( JRC_BLOCKED_DATES_OPTION, $clean, false );
}

/** How many cleans one day can hold. 0 = unlimited. */
function jrc_daily_capacity() {
	return max( 0, (int) jrc_aff_setting( 'aff_daily_capacity' ) );
}

/** Earliest and latest dates a partner may request, as Y-m-d. */
function jrc_booking_window() {
	$now  = current_time( 'timestamp' );
	$lead = max( 0, (int) jrc_aff_setting( 'aff_booking_lead_days' ) );
	$far  = max( 1, (int) jrc_aff_setting( 'aff_booking_horizon_days' ) );

	return array(
		'min' => gmdate( 'Y-m-d', $now + ( $lead * DAY_IN_SECONDS ) ),
		'max' => gmdate( 'Y-m-d', $now + ( $far * DAY_IN_SECONDS ) ),
	);
}

/**
 * How many cleans are already on a range of days.
 *
 * Counted in one grouped query rather than one per day — the availability calendar
 * asks about a whole month at a time.
 *
 * @return array<string,int> Y-m-d => count
 */
function jrc_scheduled_counts( $from, $to ) {
	global $wpdb;

	$table = jrc_jobs_table();

	// Cancelled work has released its slot. Everything else — Enquiry, Requested,
	// Pending, Booked, Completed — is a claim on the day. See the file header.
	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT DATE(scheduled_date) AS d, COUNT(*) AS n
		FROM $table
		WHERE scheduled_date IS NOT NULL
			AND DATE(scheduled_date) BETWEEN %s AND %s
			AND status <> 'Cancelled'
		GROUP BY DATE(scheduled_date)",
		$from,
		$to
	) );

	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[ (string) $r->d ] = (int) $r->n;
	}
	return $out;
}

/**
 * Can a clean be requested for this date?
 *
 * @param string $ymd    Y-m-d.
 * @param array  $counts Optional pre-fetched counts from jrc_scheduled_counts(), so the
 *                       calendar can test a month without a query per day.
 * @return array{available:bool,reason:string,booked:int,capacity:int,blocked:bool}
 */
function jrc_date_availability( $ymd, $counts = null ) {
	$ymd      = (string) $ymd;
	$capacity = jrc_daily_capacity();

	$out = array(
		'available' => true,
		'reason'    => '',
		'booked'    => 0,
		'capacity'  => $capacity,
		'blocked'   => false,
	);

	if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $ymd ) ) {
		return array( 'available' => false, 'reason' => 'That is not a date we recognise.', 'booked' => 0, 'capacity' => $capacity, 'blocked' => false );
	}

	// 1. Window.
	$window = jrc_booking_window();
	if ( $ymd < $window['min'] ) {
		$lead = max( 0, (int) jrc_aff_setting( 'aff_booking_lead_days' ) );
		$out['available'] = false;
		$out['reason']    = $lead
			? sprintf( 'We need at least %d %s notice — the earliest we can take is %s.', $lead, _n( 'day', 'days', $lead, 'jrc-partners' ), date_i18n( 'j F', strtotime( $window['min'] ) ) )
			: 'That date has already passed.';
		return $out;
	}
	if ( $ymd > $window['max'] ) {
		$out['available'] = false;
		$out['reason']    = sprintf( 'We are not taking bookings beyond %s yet. Give us a ring and we will pencil it in.', date_i18n( 'j F Y', strtotime( $window['max'] ) ) );
		return $out;
	}

	// 2. Blocked by hand.
	$blocked = jrc_blocked_dates();
	if ( isset( $blocked[ $ymd ] ) ) {
		$out['available'] = false;
		$out['blocked']   = true;
		$out['reason']    = $blocked[ $ymd ] ? $blocked[ $ymd ] : 'We are not working that day.';
		return $out;
	}

	// 3. Capacity.
	if ( null === $counts ) {
		$counts = jrc_scheduled_counts( $ymd, $ymd );
	}
	$out['booked'] = (int) ( $counts[ $ymd ] ?? 0 );

	if ( $capacity > 0 && $out['booked'] >= $capacity ) {
		$out['available'] = false;
		$out['reason']    = 'That day is fully booked.';
	}

	/**
	 * Filter one day's availability.
	 *
	 * The seam for anything this file does not model — a second van coming on stream
	 * for the summer, a partner with a standing allocation, an integration with a real
	 * scheduling system.
	 *
	 * @param array  $out Availability result.
	 * @param string $ymd Y-m-d.
	 */
	return (array) apply_filters( 'jrc_date_availability', $out, $ymd );
}

/**
 * The next N dates a partner could actually book, from a starting point.
 *
 * Offered on the request form when somebody picks a full day, because "that day is
 * fully booked" with no alternative is where a web form loses to a phone call.
 *
 * @return string[] Y-m-d
 */
function jrc_next_available_dates( $from = '', $limit = 3 ) {
	$window = jrc_booking_window();
	$start  = $from && $from >= $window['min'] ? $from : $window['min'];

	$counts = jrc_scheduled_counts( $start, $window['max'] );
	$out    = array();

	$cursor = strtotime( $start );
	$end    = strtotime( $window['max'] );

	while ( $cursor <= $end && count( $out ) < $limit ) {
		$ymd = gmdate( 'Y-m-d', $cursor );
		$a   = jrc_date_availability( $ymd, $counts );
		if ( ! empty( $a['available'] ) ) {
			$out[] = $ymd;
		}
		$cursor += DAY_IN_SECONDS;
	}

	return $out;
}

/**
 * Unavailable dates inside the booking window, for the date picker.
 *
 * Only the exceptions are sent to the browser, not a list of every valid day — on a
 * six-month horizon that is a couple of dozen strings instead of a hundred and eighty.
 *
 * @return array<string,string> Y-m-d => reason
 */
function jrc_unavailable_dates_map() {
	$window = jrc_booking_window();
	$counts = jrc_scheduled_counts( $window['min'], $window['max'] );
	$out    = array();

	$cursor = strtotime( $window['min'] );
	$end    = strtotime( $window['max'] );

	while ( $cursor <= $end ) {
		$ymd = gmdate( 'Y-m-d', $cursor );
		$a   = jrc_date_availability( $ymd, $counts );
		if ( empty( $a['available'] ) ) {
			$out[ $ymd ] = $a['reason'];
		}
		$cursor += DAY_IN_SECONDS;
	}

	return $out;
}

// ---------------------------------------------------------------------------
// Admin screen
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
	add_submenu_page(
		'jrc-partner',
		__( 'Availability', 'jrc-partners' ),
		__( 'Availability', 'jrc-partners' ),
		'manage_options',
		'jrc-availability',
		'jrc_availability_screen'
	);
}, 30 );

add_action( 'admin_post_jrc_save_availability', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'jrc-partners' ), 403 );
	}
	check_admin_referer( 'jrc_save_availability' );

	$month = isset( $_POST['month'] ) ? sanitize_text_field( wp_unslash( $_POST['month'] ) ) : current_time( 'Y-m' );
	$month = preg_match( '/^\d{4}-\d{2}$/', $month ) ? $month : current_time( 'Y-m' );

	$existing = jrc_blocked_dates();

	// Only this month's days are rewritten. The form posts one month at a time, so
	// merging rather than replacing is what stops saving March from unblocking April.
	foreach ( array_keys( $existing ) as $ymd ) {
		if ( 0 === strpos( $ymd, $month . '-' ) ) {
			unset( $existing[ $ymd ] );
		}
	}

	$posted  = isset( $_POST['blocked'] ) && is_array( $_POST['blocked'] ) ? wp_unslash( $_POST['blocked'] ) : array();
	$reasons = isset( $_POST['reason'] ) && is_array( $_POST['reason'] ) ? wp_unslash( $_POST['reason'] ) : array();

	foreach ( $posted as $ymd ) {
		$ymd = sanitize_text_field( $ymd );
		if ( 0 !== strpos( $ymd, $month . '-' ) ) {
			continue;   // Never trust a posted date to belong to the month being saved.
		}
		$existing[ $ymd ] = sanitize_text_field( $reasons[ $ymd ] ?? '' );
	}

	jrc_set_blocked_dates( $existing );

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'jrc-availability', 'm' => $month, 'saved' => 1 ),
		admin_url( 'admin.php' )
	) );
	exit;
} );

function jrc_availability_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
	$month = isset( $_GET['m'] ) ? sanitize_text_field( wp_unslash( $_GET['m'] ) ) : current_time( 'Y-m' );
	$month = preg_match( '/^\d{4}-\d{2}$/', $month ) ? $month : current_time( 'Y-m' );

	$start    = strtotime( $month . '-01' );
	$days_in  = (int) gmdate( 't', $start );
	$first_d  = (int) gmdate( 'N', $start );
	$capacity = jrc_daily_capacity();
	$blocked  = jrc_blocked_dates();
	$counts   = jrc_scheduled_counts( gmdate( 'Y-m-01', $start ), gmdate( 'Y-m-t', $start ) );
	$today    = current_time( 'Y-m-d' );

	$nav = function ( $ym ) {
		return esc_url( add_query_arg( array( 'page' => 'jrc-availability', 'm' => $ym ), admin_url( 'admin.php' ) ) );
	};
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Availability', 'jrc-partners' ); ?></h1>

		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice. ?>
		<?php if ( ! empty( $_GET['saved'] ) ) : ?>
			<div class="notice notice-success is-dismissible"><p>Availability saved.</p></div>
		<?php endif; ?>

		<p class="description" style="max-width:70em;">
			Tick a day to close it to partner requests. The number on each day is how many
			cleans are already scheduled.
			<?php if ( $capacity > 0 ) : ?>
				The daily limit is <strong><?php echo (int) $capacity; ?></strong> — days at the
				limit close themselves and do not need ticking.
			<?php else : ?>
				There is no daily limit set, so only ticked days are closed.
				Set one under <a href="<?php echo esc_url( admin_url( 'admin.php?page=affiliate-settings' ) ); ?>">Settings → Booking capacity</a>.
			<?php endif; ?>
		</p>

		<p>
			<a class="button" href="<?php echo $nav( gmdate( 'Y-m', strtotime( '-1 month', $start ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">&larr; <?php echo esc_html( date_i18n( 'F Y', strtotime( '-1 month', $start ) ) ); ?></a>
			<strong style="margin:0 12px;font-size:15px;"><?php echo esc_html( date_i18n( 'F Y', $start ) ); ?></strong>
			<a class="button" href="<?php echo $nav( gmdate( 'Y-m', strtotime( '+1 month', $start ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo esc_html( date_i18n( 'F Y', strtotime( '+1 month', $start ) ) ); ?> &rarr;</a>
		</p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'jrc_save_availability' ); ?>
			<input type="hidden" name="action" value="jrc_save_availability" />
			<input type="hidden" name="month" value="<?php echo esc_attr( $month ); ?>" />

			<table class="widefat" style="max-width:60em;table-layout:fixed;">
				<thead>
					<tr>
						<?php foreach ( array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ) as $dow ) : ?>
							<th style="text-align:center;"><?php echo esc_html( $dow ); ?></th>
						<?php endforeach; ?>
					</tr>
				</thead>
				<tbody>
					<tr>
					<?php
					for ( $b = 1; $b < $first_d; $b++ ) {
						echo '<td style="background:#f6f7f7;"></td>';
					}
					$col = $first_d - 1;

					for ( $day = 1; $day <= $days_in; $day++ ) {
						if ( 7 === $col ) {
							echo '</tr><tr>';
							$col = 0;
						}
						$ymd     = $month . '-' . str_pad( (string) $day, 2, '0', STR_PAD_LEFT );
						$booked  = (int) ( $counts[ $ymd ] ?? 0 );
						$is_past = $ymd < $today;
						$is_full = $capacity > 0 && $booked >= $capacity;
						$is_blk  = isset( $blocked[ $ymd ] );

						$bg = '#fff';
						if ( $is_past )      { $bg = '#f6f7f7'; }
						elseif ( $is_blk )   { $bg = '#fcf0f1'; }
						elseif ( $is_full )  { $bg = '#fdf8e8'; }
						?>
						<td style="vertical-align:top;height:92px;background:<?php echo esc_attr( $bg ); ?>;">
							<strong><?php echo (int) $day; ?></strong>
							<span style="color:#666;font-size:11px;">
								<?php echo (int) $booked; ?><?php echo $capacity > 0 ? '/' . (int) $capacity : ''; ?>
							</span>

							<?php if ( $is_past ) : ?>
								<br /><span style="color:#999;font-size:11px;">past</span>
							<?php else : ?>
								<br />
								<label style="font-size:11px;">
									<input type="checkbox" name="blocked[]" value="<?php echo esc_attr( $ymd ); ?>" <?php checked( $is_blk ); ?> />
									Closed
								</label>
								<input type="text" name="reason[<?php echo esc_attr( $ymd ); ?>]"
									value="<?php echo esc_attr( $blocked[ $ymd ] ?? '' ); ?>"
									placeholder="Reason (optional)"
									style="width:100%;font-size:11px;padding:2px 4px;margin-top:2px;" />
							<?php endif; ?>
						</td>
						<?php
						$col++;
					}
					while ( $col < 7 ) { echo '<td style="background:#f6f7f7;"></td>'; $col++; }
					?>
					</tr>
				</tbody>
			</table>

			<p style="margin-top:16px;">
				<button class="button button-primary">Save <?php echo esc_html( date_i18n( 'F Y', $start ) ); ?></button>
				<span class="description" style="margin-left:8px;">Saving only affects the month shown.</span>
			</p>
		</form>
	</div>
	<?php
}
