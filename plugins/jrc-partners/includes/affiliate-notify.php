<?php
/**
 * Partner notifications.
 *
 * The programme tracked plenty and told nobody. The reports tab counts what a partner
 * has not seen, badges it on the account nav and keeps a per-user "last seen" — all of
 * which only fires if the branch manager decides to log in, which they do not do
 * speculatively. They ring up and ask where the report is.
 *
 * Three emails close that loop:
 *
 *   report  — a photo report has been attached to one of their cleans.
 *   tier    — their tier changed, and with it their rates.
 *   digest  — a monthly summary, so the account has a reason to exist between jobs.
 *
 * Each is individually switchable by the partner on My Account → Account details
 * (see includes/affiliate-account.php).
 *
 * DELIBERATELY NOT LINKED DIRECTLY TO A REPORT. jrc_report_url() signs its link with
 * wp_create_nonce(), which is bound to the *current* user and expires inside 24 hours.
 * A report attached by an administrator would therefore be signed for the administrator
 * and be stale by the time anyone read the email. These emails link to the reports tab
 * and let WooCommerce handle the login round trip, which is also the honest thing to do
 * with a document containing photographs of the inside of somebody's home.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// ---------------------------------------------------------------------------
// Preferences
// ---------------------------------------------------------------------------

/**
 * The notification types a partner can switch on and off.
 *
 * @return array<string,array{label:string,help:string,default:bool}>
 */
function jrc_notify_types() {
	return apply_filters( 'jrc_notify_types', array(
		'report' => array(
			'label'   => 'New report available',
			'help'    => 'Email me when a photo report is added to one of my cleans.',
			'default' => true,
		),
		'tier'   => array(
			'label'   => 'Tier changes',
			'help'    => 'Email me when my tier — and the discounts that come with it — changes.',
			'default' => true,
		),
		'digest' => array(
			'label'   => 'Monthly summary',
			'help'    => 'A short round-up at the start of each month: cleans completed, new reports, progress to the next tier.',
			'default' => true,
		),
		'request' => array(
			'label'   => 'Clean request confirmations',
			'help'    => 'Email me a copy whenever I request a clean from my account.',
			'default' => true,
		),
	) );
}

/** All stored preferences for one partner, defaults filled in. */
function jrc_notify_prefs( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$stored  = get_user_meta( $user_id, 'jrc_notify_prefs', true );
	$stored  = is_array( $stored ) ? $stored : array();

	$out = array();
	foreach ( jrc_notify_types() as $key => $def ) {
		// array_key_exists, not isset: a stored false is a decision, not an absence.
		$out[ $key ] = array_key_exists( $key, $stored ) ? (bool) $stored[ $key ] : (bool) $def['default'];
	}
	return $out;
}

/** Has this partner asked for this kind of email? */
function jrc_notify_enabled( $user_id, $type ) {
	$prefs = jrc_notify_prefs( $user_id );
	return ! empty( $prefs[ $type ] );
}

/**
 * Where a notification should go.
 *
 * Branches routinely want reports landing in a shared lettings inbox rather than the
 * negotiator's personal account, so Account details carries an optional override. It
 * applies to report and digest mail — the things a colleague might need to pick up —
 * and never to tier changes or request confirmations, which are about the account
 * itself and belong with the account holder.
 */
function jrc_notify_to( $user_id, $type = '' ) {
	$user = get_userdata( $user_id );
	if ( ! $user || ! is_email( $user->user_email ) ) {
		return '';
	}

	if ( in_array( $type, array( 'report', 'digest' ), true ) ) {
		$override = (string) get_user_meta( $user_id, 'jrc_reports_email', true );
		if ( is_email( $override ) ) {
			return $override;
		}
	}

	return $user->user_email;
}

/** Where clean requests and other internal notices go. */
function jrc_notify_office_email() {
	$email = function_exists( 'get_field' ) ? (string) get_field( 'contact_email', 'options' ) : '';
	if ( ! is_email( $email ) ) {
		$email = get_option( 'admin_email' );
	}
	return apply_filters( 'jrc_notify_office_email', $email );
}

// ---------------------------------------------------------------------------
// Sending
// ---------------------------------------------------------------------------

/**
 * Wrap content in the branded HTML shell.
 *
 * Table-based and inline-styled on purpose: Outlook — which is what estate agents read
 * mail in — still does not support a <style> block reliably, and this is not the place
 * to find out.
 *
 * @param string $heading Big line at the top of the card.
 * @param string $body    Already-escaped HTML for the body.
 * @param array  $cta     Optional { text, url }.
 * @param string $footnote Optional small print under the card.
 */
function jrc_notify_shell( $heading, $body, $cta = array(), $footnote = '' ) {
	$site  = get_bloginfo( 'name' );
	$navy  = '#143458';
	$teal  = '#13b5b1';
	$ink   = '#3a3f57';
	$muted = '#6b7088';
	$line  = '#e9ebf0';

	$button = '';
	if ( ! empty( $cta['url'] ) && ! empty( $cta['text'] ) ) {
		$button = '<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:26px 0 0;">'
			. '<tr><td style="border-radius:14px;background:' . $teal . ';">'
			. '<a href="' . esc_url( $cta['url'] ) . '" style="display:inline-block;padding:14px 26px;font-family:Helvetica,Arial,sans-serif;font-size:16px;font-weight:700;color:#06403f;text-decoration:none;border-radius:14px;">'
			. esc_html( $cta['text'] ) . '</a>'
			. '</td></tr></table>';
	}

	$prefs_url = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'edit-account' )
		: home_url( '/my-account/' );

	return '<!doctype html><html><body style="margin:0;padding:0;background:#f7f8fa;">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f7f8fa;padding:28px 12px;">'
		. '<tr><td align="center">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="max-width:580px;background:#ffffff;border-radius:20px;overflow:hidden;border:1px solid ' . $line . ';">'

		// Header band.
		. '<tr><td style="background:' . $navy . ';padding:22px 30px;">'
		. '<span style="font-family:Helvetica,Arial,sans-serif;font-size:15px;font-weight:700;color:#ffffff;letter-spacing:.02em;">' . esc_html( $site ) . '</span>'
		. '<span style="font-family:Helvetica,Arial,sans-serif;font-size:12px;color:rgba(255,255,255,.65);float:right;padding-top:3px;">Partner programme</span>'
		. '</td></tr>'

		// Body.
		. '<tr><td style="padding:32px 30px 34px;">'
		. '<h1 style="margin:0 0 14px;font-family:Helvetica,Arial,sans-serif;font-size:23px;line-height:1.28;color:' . $navy . ';">' . esc_html( $heading ) . '</h1>'
		. '<div style="font-family:Helvetica,Arial,sans-serif;font-size:15px;line-height:1.62;color:' . $ink . ';">' . $body . '</div>'
		. $button
		. '</td></tr>'

		// Footer.
		. '<tr><td style="padding:18px 30px 26px;border-top:1px solid ' . $line . ';font-family:Helvetica,Arial,sans-serif;font-size:12.5px;line-height:1.6;color:' . $muted . ';">'
		. ( $footnote ? '<p style="margin:0 0 10px;">' . $footnote . '</p>' : '' )
		. '<p style="margin:0;">You are getting this because your branch is on the ' . esc_html( $site ) . ' partner programme. '
		. '<a href="' . esc_url( $prefs_url ) . '" style="color:#0d8380;">Choose which emails you get</a>.</p>'
		. '</td></tr>'

		. '</table></td></tr></table></body></html>';
}

/**
 * Send one notification, honouring the partner's preferences.
 *
 * @param int    $user_id Recipient.
 * @param string $type    A key from jrc_notify_types(), or '' to bypass the preference
 *                        check for internal mail that is not a partner notification.
 * @param string $subject Subject line.
 * @param string $html    Body HTML, from jrc_notify_shell().
 * @param array  $args    to (override), headers.
 * @return bool
 */
function jrc_notify_send( $user_id, $type, $subject, $html, $args = array() ) {
	if ( $type && ! jrc_notify_enabled( $user_id, $type ) ) {
		return false;
	}

	$to = ! empty( $args['to'] ) ? $args['to'] : jrc_notify_to( $user_id, $type );
	if ( ! is_email( $to ) ) {
		return false;
	}

	$headers = isset( $args['headers'] ) ? (array) $args['headers'] : array();

	// A plain-text alternative rather than an HTML-only message: it is the single
	// cheapest thing you can do for deliverability, and Outlook's reading pane is not
	// the only place this gets opened.
	$alt = trim( html_entity_decode( wp_strip_all_tags( str_replace( array( '</p>', '<br>', '<br/>', '<br />', '</tr>', '</li>' ), "\n", $html ) ), ENT_QUOTES, 'UTF-8' ) );
	$alt = preg_replace( "/\n{3,}/", "\n\n", $alt );

	$type_filter = function () { return 'text/html'; };
	$alt_filter  = function ( $phpmailer ) use ( $alt ) { $phpmailer->AltBody = $alt; };

	add_filter( 'wp_mail_content_type', $type_filter );
	add_action( 'phpmailer_init', $alt_filter );

	$sent = wp_mail( $to, $subject, $html, $headers );

	remove_action( 'phpmailer_init', $alt_filter );
	remove_filter( 'wp_mail_content_type', $type_filter );

	return (bool) $sent;
}

/** Every approved affiliate, as user IDs. */
function jrc_affiliate_user_ids() {
	return get_users( array(
		'meta_key'   => 'jrc_is_affiliate', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'meta_value' => '1',                // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
		'fields'     => 'ids',
		'number'     => -1,
	) );
}

/** A partner's first name, or something usable if they never set one. */
function jrc_notify_greeting_name( $user_id ) {
	$u = get_userdata( $user_id );
	if ( ! $u ) {
		return 'there';
	}
	if ( $u->first_name ) {
		return $u->first_name;
	}
	$branch = (string) get_user_meta( $user_id, 'jrc_branch_name', true );
	return $branch ? $branch : ( $u->display_name ? $u->display_name : 'there' );
}

// ---------------------------------------------------------------------------
// 1. Report ready
// ---------------------------------------------------------------------------

/**
 * Tell the partner their photo report is on file.
 *
 * @param int    $job_id   Job.
 * @param object $job      The job row before the report was attached.
 * @param bool   $replaced Whether this overwrote an earlier report.
 */
add_action( 'jrc_job_report_attached', function ( $job_id, $job, $replaced ) {
	if ( ! $job || empty( $job->user_id ) ) {
		return;
	}

	$user_id = (int) $job->user_id;
	if ( ! jrc_is_affiliate( $user_id ) ) {
		return;
	}

	$where = $job->address ? $job->address : 'your recent clean';
	$what  = $job->job_type ? $job->job_type : 'Clean';
	$when  = $job->job_date ? date_i18n( 'j F Y', strtotime( $job->job_date ) ) : '';

	$heading = $replaced
		? 'An updated report for ' . $where
		: 'Your report for ' . $where . ' is ready';

	$body  = '<p style="margin:0 0 14px;">Hi ' . esc_html( jrc_notify_greeting_name( $user_id ) ) . ',</p>';
	$body .= '<p style="margin:0 0 14px;">';
	$body .= $replaced
		? 'We have replaced the photo report for this clean with a corrected version. The new one is in your account now.'
		: 'The photo report for this clean is now in your account. It shows what was covered, with pictures, so you can forward it straight to the landlord or tenant.';
	$body .= '</p>';

	$body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:20px 0 4px;background:#f7f8fa;border-radius:14px;">'
		. '<tr><td style="padding:16px 18px;font-family:Helvetica,Arial,sans-serif;font-size:14.5px;line-height:1.7;color:#3a3f57;">'
		. '<strong style="color:#143458;">' . esc_html( $what ) . '</strong><br>'
		. esc_html( $where )
		. ( $when ? '<br><span style="color:#6b7088;">Cleaned ' . esc_html( $when ) . '</span>' : '' )
		. '</td></tr></table>';

	$url = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'reports' )
		: home_url( '/my-account/reports/' );

	jrc_notify_send(
		$user_id,
		'report',
		$replaced
			? 'Updated report — ' . $where
			: 'Your photo report for ' . $where . ' is ready',
		jrc_notify_shell(
			$heading,
			$body,
			array( 'text' => 'Open your reports', 'url' => $url ),
			'You will be asked to sign in — reports contain photographs of the inside of a property, so they are never sent as an open link.'
		)
	);
}, 10, 3 );

// ---------------------------------------------------------------------------
// 2. Tier change
// ---------------------------------------------------------------------------

add_action( 'jrc_affiliate_tier_changed', function ( $user_id, $old, $new ) {
	if ( ! jrc_is_affiliate( $user_id ) ) {
		return;
	}

	$data     = jrc_affiliate_data( $user_id );
	$promoted = $new > $old;
	$label    = $data['tier_label'];

	$heading = $promoted
		? 'You have reached ' . $label
		: 'Your tier has changed to ' . $label;

	$body  = '<p style="margin:0 0 14px;">Hi ' . esc_html( jrc_notify_greeting_name( $user_id ) ) . ',</p>';
	$body .= $promoted
		? '<p style="margin:0 0 14px;">Thanks for the work you have sent our way — your branch has moved up to <strong>' . esc_html( $label ) . '</strong>. Your new rates apply from today.</p>'
		: '<p style="margin:0 0 14px;">Your branch is now on <strong>' . esc_html( $label ) . '</strong>. Your rates have been updated to match, and a new agency price list is on its way.</p>';

	$rows = '<tr><td style="padding:6px 0;font-family:Helvetica,Arial,sans-serif;font-size:14.5px;color:#3a3f57;">Your branch and the customers you refer</td>'
		. '<td align="right" style="padding:6px 0;font-family:Helvetica,Arial,sans-serif;font-size:15px;font-weight:700;color:#143458;">' . esc_html( $data['customer_discount'] ?: '—' ) . '</td></tr>';

	$own = $data['affiliate_discount'];
	$rows .= '<tr><td style="padding:6px 0;font-family:Helvetica,Arial,sans-serif;font-size:14.5px;color:#3a3f57;">Cleans at your own home</td>'
		. '<td align="right" style="padding:6px 0;font-family:Helvetica,Arial,sans-serif;font-size:15px;font-weight:700;color:#0d8380;">'
		. esc_html( ( $own && '-' !== $own && '—' !== $own ) ? $own : 'Not at this tier' )
		. '</td></tr>';

	$body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:20px 0 4px;padding:6px 18px;background:#f7f8fa;border-radius:14px;">'
		. '<tr><td colspan="2" style="padding:12px 0 6px;font-family:Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6b7088;">Your rates now</td></tr>'
		. $rows
		. '<tr><td colspan="2" style="padding:0 0 12px;"></td></tr>'
		. '</table>';

	$url = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'affiliate' )
		: home_url( '/my-account/affiliate/' );

	jrc_notify_send(
		$user_id,
		'tier',
		$promoted ? 'You have reached ' . $label : 'Your tier is now ' . $label,
		jrc_notify_shell( $heading, $body, array( 'text' => 'View your dashboard', 'url' => $url ) )
	);
}, 10, 3 );

// ---------------------------------------------------------------------------
// 3. Monthly digest
// ---------------------------------------------------------------------------

/**
 * Scheduled daily rather than monthly, and guarded per partner by the period it
 * covers. WordPress has no monthly schedule, and a monthly event that misses its slot
 * — a quiet site with no visitors to trigger wp-cron — simply never fires. This way a
 * missed run catches up the next day, and the per-period guard means nobody gets two.
 */
add_action( 'init', function () {
	if ( ! wp_next_scheduled( 'jrc_affiliate_digest' ) ) {
		wp_schedule_event( strtotime( 'tomorrow 7:00' ), 'daily', 'jrc_affiliate_digest' );
	}
} );

/** Day of the month the digest goes out. */
function jrc_digest_day() {
	return (int) apply_filters( 'jrc_digest_day', 1 );
}

/**
 * How many days after that a missed run may still catch up.
 *
 * There has to be a window, not just a floor. "Send if today is on or after the 1st"
 * is true on the 31st too, so a site whose cron had not run would deliver a summary of
 * July at the end of August — stale, and confusing enough that the partner reads it as
 * this month's numbers being wrong.
 */
function jrc_digest_grace_days() {
	return (int) apply_filters( 'jrc_digest_grace_days', 6 );
}

add_action( 'jrc_affiliate_digest', function () {
	$day   = (int) current_time( 'j' );
	$first = jrc_digest_day();

	if ( $day < $first || $day > $first + jrc_digest_grace_days() ) {
		return;
	}

	// The month just gone.
	$period = gmdate( 'Y-m', strtotime( current_time( 'Y-m' ) . '-01 -1 month' ) );

	foreach ( jrc_affiliate_user_ids() as $user_id ) {
		if ( (string) get_user_meta( $user_id, 'jrc_digest_sent', true ) === $period ) {
			continue;
		}
		// Marked before sending, not after: a partner whose mail server rejects the
		// message must not cause the whole run to retry them every day for a month.
		update_user_meta( $user_id, 'jrc_digest_sent', $period );
		jrc_send_digest( $user_id, $period );
	}
} );

/**
 * Build and send one partner's monthly summary.
 *
 * @param int    $user_id Affiliate.
 * @param string $period  Month covered, as Y-m.
 * @return bool Whether anything was sent.
 */
function jrc_send_digest( $user_id, $period ) {
	if ( ! jrc_notify_enabled( $user_id, 'digest' ) ) {
		return false;
	}

	$start = $period . '-01';
	$end   = gmdate( 'Y-m-t', strtotime( $start ) );

	$completed = jrc_jobs_query( array(
		'user_id'    => $user_id,
		'status'     => 'Completed',
		'date_from'  => $start,
		'date_to'    => $end,
		'count_only' => true,
	) );

	$reports  = jrc_reports_count_between( $user_id, $start . ' 00:00:00', $end . ' 23:59:59' );
	$upcoming = jrc_jobs_upcoming( $user_id, 6 );
	$data     = jrc_affiliate_data( $user_id );
	$expiry   = jrc_affiliate_tier_expiry( $user_id );

	// An empty digest is noise. A partner with no completed work, no new reports and
	// nothing booked does not need an email telling them so — they need a phone call.
	if ( ! $completed && ! $reports && ! $upcoming ) {
		return false;
	}

	$month_name = date_i18n( 'F', strtotime( $start ) );

	$body  = '<p style="margin:0 0 14px;">Hi ' . esc_html( jrc_notify_greeting_name( $user_id ) ) . ',</p>';
	$body .= '<p style="margin:0 0 18px;">Here is how ' . esc_html( $month_name ) . ' looked for your branch.</p>';

	// Counters.
	$cell = function ( $n, $label ) {
		return '<td width="33%" align="center" style="padding:16px 8px;font-family:Helvetica,Arial,sans-serif;">'
			. '<div style="font-size:28px;font-weight:700;color:#143458;line-height:1;">' . esc_html( $n ) . '</div>'
			. '<div style="font-size:12.5px;color:#6b7088;padding-top:6px;line-height:1.4;">' . esc_html( $label ) . '</div>'
			. '</td>';
	};

	$body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background:#f7f8fa;border-radius:14px;margin:0 0 20px;"><tr>'
		. $cell( $completed, 'cleans completed' )
		. $cell( $reports, 'new reports' )
		. $cell( count( $upcoming ), 'booked in' )
		. '</tr></table>';

	// Progress.
	if ( $data['next_tier'] ) {
		$body .= '<p style="margin:0 0 14px;"><strong>' . esc_html( $data['needed'] ) . ' more</strong> '
			. 'in a rolling twelve months takes you to ' . esc_html( $data['next_label'] ) . '. '
			. 'You are at ' . esc_html( $data['last12'] ) . ' right now.</p>';
	} else {
		$body .= '<p style="margin:0 0 14px;">You are on ' . esc_html( $data['tier_label'] ) . ', the top tier. Keep the volume up and it stays there.</p>';
	}

	// The warning that actually matters.
	if ( $expiry['count'] && $expiry['at_risk'] ) {
		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin:0 0 18px;background:#fdf3ef;border-radius:14px;border:1px solid #f1d6cb;">'
			. '<tr><td style="padding:14px 18px;font-family:Helvetica,Arial,sans-serif;font-size:14px;line-height:1.6;color:#3a3f57;">'
			. '<strong style="color:#d63638;">Heads up.</strong> '
			. esc_html( sprintf(
				/* translators: 1: number of bookings, 2: date */
				_n( '%1$d booking drops out of your twelve-month count on %2$s', '%1$d bookings drop out of your twelve-month count on %2$s', $expiry['count'], 'jrc-partners' ),
				$expiry['count'],
				$expiry['date'] ? date_i18n( 'j F', strtotime( $expiry['date'] ) ) : ''
			) )
			. esc_html( sprintf( ', which would take you to %d against the %d needed to hold your tier.', $expiry['after'], $expiry['threshold'] ) )
			. '</td></tr></table>';
	}

	// What is coming.
	if ( $upcoming ) {
		$body .= '<p style="margin:22px 0 8px;font-family:Helvetica,Arial,sans-serif;font-size:12px;font-weight:700;letter-spacing:.1em;text-transform:uppercase;color:#6b7088;">Next up</p>';
		$body .= '<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">';
		foreach ( $upcoming as $job ) {
			$body .= '<tr><td style="padding:8px 0;border-bottom:1px solid #e9ebf0;font-family:Helvetica,Arial,sans-serif;font-size:14px;color:#3a3f57;">'
				. '<strong style="color:#143458;">' . esc_html( date_i18n( 'j M, H:i', strtotime( $job->scheduled_date ) ) ) . '</strong> &nbsp;'
				. esc_html( $job->job_type ?: 'Clean' )
				. ( $job->address ? ' <span style="color:#6b7088;">— ' . esc_html( $job->address ) . '</span>' : '' )
				. '</td></tr>';
		}
		$body .= '</table>';
	}

	$url = function_exists( 'wc_get_account_endpoint_url' )
		? wc_get_account_endpoint_url( 'affiliate' )
		: home_url( '/my-account/affiliate/' );

	return jrc_notify_send(
		$user_id,
		'digest',
		$month_name . ' summary — ' . get_bloginfo( 'name' ) . ' partner programme',
		jrc_notify_shell( 'Your ' . $month_name . ' summary', $body, array( 'text' => 'Open your dashboard', 'url' => $url ) )
	);
}

/** Housekeeping: an uninstalled theme should not leave a cron event behind. */
add_action( 'switch_theme', function () {
	$ts = wp_next_scheduled( 'jrc_affiliate_digest' );
	if ( $ts ) {
		wp_unschedule_event( $ts, 'jrc_affiliate_digest' );
	}
} );
