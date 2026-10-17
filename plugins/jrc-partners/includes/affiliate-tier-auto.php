<?php
/**
 * Recalculating partner tiers on a schedule.
 *
 * Thresholds are configurable and jobs are queryable by rolling window, but tier has
 * always been an integer somebody types into a profile screen. So the number that sets
 * a branch's discount rate drifts away from the work that earns it, in both directions:
 * a branch that hits twelve bookings stays on Tier 1 until a human notices, and a
 * branch whose bookings have aged out of the window keeps a rate it no longer
 * qualifies for. Neither is discovered until somebody queries an invoice.
 *
 * This closes the loop once a day. It writes through update_user_meta(), which means
 * the existing machinery in includes/affiliate-tier-history.php picks the change up,
 * records it and fires jrc_affiliate_tier_changed — so the partner gets the same email
 * they would have got if an administrator had done it by hand. Nothing here has to know
 * how history or notification work.
 *
 * TWO DELIBERATE BRAKES, because this moves real money:
 *
 *   1. It is OFF until switched on (Partner Programme → Settings → Automation), and the
 *      preview screen shows exactly what the first run would do before anyone commits.
 *   2. Demotion is separately switchable and off by default. Promoting somebody early
 *      is a discount you did not have to give; demoting them wrongly is an invoice
 *      dispute and a phone call. They are not the same risk and should not share a
 *      switch.
 *
 * HOW JOBS ARE COUNTED. Every job with a job_date inside the last twelve months,
 * whatever its status. That is what jrc_affiliate_data() counts for `last12` and what
 * the progress bar on the dashboard shows, so this agrees with the number the partner
 * is looking at. It does NOT agree with the "How the Bonus Program works" copy, which
 * says pending and cancelled work does not count — that discrepancy is inherited and
 * documented in includes/affiliate-tier-history.php. Reconcile both together or
 * neither; a recalculation that used a different rule from the visible progress bar
 * would be the worst of the three options.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const JRC_TIER_CRON_HOOK = 'jrc_recalc_tiers';

/** Is automatic recalculation switched on? */
function jrc_auto_tier_enabled() {
	return (bool) jrc_aff_setting( 'aff_auto_tier' );
}

/** May it lower a tier as well as raise one? */
function jrc_auto_tier_demote_enabled() {
	return (bool) jrc_aff_setting( 'aff_auto_tier_demote' );
}

/**
 * Bookings counted towards this partner's tier, over the rolling window.
 *
 * Deliberately mirrors jrc_affiliate_data()'s `last12`. See the file header.
 */
function jrc_affiliate_booking_count( $user_id ) {
	$cutoff = current_time( 'timestamp' ) - YEAR_IN_SECONDS;
	$n      = 0;

	foreach ( jrc_affiliate_jobs( (int) $user_id ) as $j ) {
		$ts = ! empty( $j['date'] ) ? strtotime( $j['date'] ) : 0;
		if ( $ts && $ts >= $cutoff ) {
			$n++;
		}
	}

	return $n;
}

/**
 * The tier a partner's booking count entitles them to.
 *
 * Walks the thresholds from the top down and takes the first one met, so the order of
 * jrc_affiliate_thresholds() does not matter and a mis-set threshold (tier 2 above
 * tier 3, say) degrades to something sane rather than to tier 1.
 *
 * @return int 1-3.
 */
function jrc_affiliate_calculated_tier( $user_id ) {
	$count      = jrc_affiliate_booking_count( $user_id );
	$thresholds = jrc_affiliate_thresholds();

	arsort( $thresholds );

	foreach ( $thresholds as $tier => $needed ) {
		if ( $needed > 0 && $count >= $needed ) {
			return max( 1, min( 3, (int) $tier ) );
		}
	}

	return 1;
}

/**
 * What a recalculation would do to one account, without doing it.
 *
 * @return array{user_id:int,current:int,calculated:int,count:int,action:string,reason:string}
 */
function jrc_auto_tier_preview_user( $user_id ) {
	$user_id = (int) $user_id;
	$current = max( 1, min( 3, (int) get_user_meta( $user_id, 'jrc_affiliate_tier', true ) ?: 1 ) );
	$count   = jrc_affiliate_booking_count( $user_id );

	$out = array(
		'user_id'    => $user_id,
		'current'    => $current,
		'calculated' => $current,
		'count'      => $count,
		'action'     => 'none',
		'reason'     => '',
	);

	// Flat-rate accounts (Property Management, Holiday Let sub-types) do not progress
	// through tiers at all — their rates come from the settings page, so recalculating
	// a tier for them would change a number nothing reads and email them about it.
	$profile = function_exists( 'jrc_affiliate_profile' ) ? jrc_affiliate_profile( $user_id ) : array();
	if ( isset( $profile['tiered'] ) && ! $profile['tiered'] ) {
		$out['reason'] = 'Flat-rate account — not tiered';
		return $out;
	}

	$calculated         = jrc_affiliate_calculated_tier( $user_id );
	$out['calculated']  = $calculated;

	if ( $calculated === $current ) {
		$out['reason'] = 'Already correct';
		return $out;
	}

	if ( $calculated > $current ) {
		$out['action'] = 'promote';
		$out['reason'] = sprintf( '%d bookings in the last 12 months', $count );
		return $out;
	}

	$out['action'] = jrc_auto_tier_demote_enabled() ? 'demote' : 'blocked';
	$out['reason'] = jrc_auto_tier_demote_enabled()
		? sprintf( 'Down to %d bookings in the last 12 months', $count )
		: 'Would drop a tier, but demotion is switched off';

	return $out;
}

/** What a recalculation would do across every approved partner. */
function jrc_auto_tier_preview() {
	$out = array();
	foreach ( jrc_affiliate_user_ids() as $uid ) {
		$out[] = jrc_auto_tier_preview_user( $uid );
	}
	return $out;
}

/**
 * Apply the recalculation.
 *
 * @param bool $dry_run Work everything out and change nothing.
 * @return array{checked:int,promoted:int,demoted:int,blocked:int,changes:array}
 */
function jrc_auto_tier_run( $dry_run = false ) {
	$result = array( 'checked' => 0, 'promoted' => 0, 'demoted' => 0, 'blocked' => 0, 'changes' => array() );

	foreach ( jrc_auto_tier_preview() as $row ) {
		$result['checked']++;

		if ( 'blocked' === $row['action'] ) {
			$result['blocked']++;
			continue;
		}
		if ( 'promote' !== $row['action'] && 'demote' !== $row['action'] ) {
			continue;
		}

		$result[ 'promote' === $row['action'] ? 'promoted' : 'demoted' ]++;
		$result['changes'][] = $row;

		if ( $dry_run ) {
			continue;
		}

		// The whole point of writing through meta: affiliate-tier-history.php is
		// listening on updated_user_meta and will record the change and email the
		// partner without this file knowing either exists.
		update_user_meta( $row['user_id'], 'jrc_affiliate_tier', $row['calculated'] );
	}

	if ( ! $dry_run ) {
		update_option( 'jrc_auto_tier_last_run', array(
			'at'      => current_time( 'mysql' ),
			'checked' => $result['checked'],
			'changed' => $result['promoted'] + $result['demoted'],
		) );
	}

	return $result;
}

// ---------------------------------------------------------------------------
// Schedule
// ---------------------------------------------------------------------------

/**
 * Keep the cron event in step with the setting.
 *
 * Checked on init rather than only on save, so switching the setting off in the
 * database, restoring a backup or importing options cannot leave a scheduled job
 * running against a disabled feature.
 */
add_action( 'init', function () {
	$scheduled = wp_next_scheduled( JRC_TIER_CRON_HOOK );

	if ( jrc_auto_tier_enabled() && ! $scheduled ) {
		// 03:00 site time: after midnight so "the last twelve months" has rolled over
		// for the new day, and well clear of the morning admin traffic.
		$first = strtotime( 'tomorrow 03:00', current_time( 'timestamp' ) );
		wp_schedule_event( $first - ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ), 'daily', JRC_TIER_CRON_HOOK );
		return;
	}

	if ( ! jrc_auto_tier_enabled() && $scheduled ) {
		wp_unschedule_event( $scheduled, JRC_TIER_CRON_HOOK );
	}
} );

add_action( JRC_TIER_CRON_HOOK, function () {
	// Re-checked at run time as well as at schedule time: an event already in the queue
	// fires even if the setting was turned off after it was queued.
	if ( ! jrc_auto_tier_enabled() ) {
		return;
	}
	jrc_auto_tier_run( false );
} );

// No switch_theme hook: this lived in the theme once, and unscheduling on a theme switch
// made sense then. As a plugin it must keep running whatever the theme; the plugin's
// deactivation hook (jrc-partners.php) is what clears the schedule now.

// ---------------------------------------------------------------------------
// Preview screen
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
	add_submenu_page(
		'jrc-partner',
		__( 'Tier recalculation', 'jrc-partners' ),
		__( 'Tiers', 'jrc-partners' ),
		'manage_options',
		'jrc-tiers',
		'jrc_auto_tier_screen'
	);
}, 20 );

/** Run-now, from the preview screen. */
add_action( 'admin_post_jrc_run_tiers', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'jrc-partners' ), 403 );
	}
	check_admin_referer( 'jrc_run_tiers' );

	$r = jrc_auto_tier_run( false );

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'jrc-tiers', 'ran' => 1, 'changed' => $r['promoted'] + $r['demoted'] ),
		admin_url( 'admin.php' )
	) );
	exit;
} );

function jrc_auto_tier_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	$enabled = jrc_auto_tier_enabled();
	$demote  = jrc_auto_tier_demote_enabled();
	$rows    = jrc_auto_tier_preview();
	$last    = get_option( 'jrc_auto_tier_last_run' );
	$next    = wp_next_scheduled( JRC_TIER_CRON_HOOK );

	$changes = array_values( array_filter( $rows, function ( $r ) {
		return in_array( $r['action'], array( 'promote', 'demote', 'blocked' ), true );
	} ) );
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Tier recalculation', 'jrc-partners' ); ?></h1>

		<?php // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only notice. ?>
		<?php if ( ! empty( $_GET['ran'] ) ) : ?>
			<div class="notice notice-success is-dismissible">
				<p><?php printf( esc_html__( 'Recalculation complete. %d account(s) changed.', 'jrc-partners' ), (int) ( $_GET['changed'] ?? 0 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?></p>
			</div>
		<?php endif; ?>

		<p class="description" style="max-width:70em;">
			Tiers are worked out from bookings with a job date in the last twelve months —
			the same count as the progress bar on a partner's dashboard. This screen always
			shows what a run would do <strong>right now</strong>, whether or not the daily
			job is switched on.
		</p>

		<table class="widefat" style="max-width:52em;margin:16px 0;">
			<tbody>
				<tr>
					<th style="width:16em;">Daily recalculation</th>
					<td>
						<?php if ( $enabled ) : ?>
							<span style="color:#0c5f5c;font-weight:600;">On</span>
							<?php if ( $next ) : ?>
								— next run <?php echo esc_html( date_i18n( 'j M Y, H:i', $next + ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) ); ?>
							<?php endif; ?>
						<?php else : ?>
							<span style="color:#8a6d00;font-weight:600;">Off</span> —
							turn it on under <a href="<?php echo esc_url( admin_url( 'admin.php?page=affiliate-settings' ) ); ?>">Settings → Automation</a>.
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th>Demotion</th>
					<td><?php echo $demote ? 'Allowed — tiers can go down as well as up.' : 'Blocked — a run will only ever raise a tier.'; ?></td>
				</tr>
				<tr>
					<th>Last run</th>
					<td>
						<?php if ( is_array( $last ) && ! empty( $last['at'] ) ) : ?>
							<?php echo esc_html( date_i18n( 'j M Y, H:i', strtotime( $last['at'] ) ) ); ?>
							— <?php echo (int) $last['checked']; ?> checked, <?php echo (int) $last['changed']; ?> changed
						<?php else : ?>
							Never
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>

		<h2><?php esc_html_e( 'What a run would change now', 'jrc-partners' ); ?></h2>

		<?php if ( ! $changes ) : ?>
			<p>Every partner account is already on the tier its bookings entitle it to.</p>
		<?php else : ?>
			<table class="widefat striped" style="max-width:64em;">
				<thead>
					<tr>
						<th>Partner</th>
						<th>Bookings (12m)</th>
						<th>Current</th>
						<th>Calculated</th>
						<th>Action</th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $changes as $r ) :
						$u = get_userdata( $r['user_id'] ); ?>
						<tr>
							<td>
								<a href="<?php echo esc_url( get_edit_user_link( $r['user_id'] ) ); ?>">
									<?php echo esc_html( $u ? $u->display_name : '#' . $r['user_id'] ); ?>
								</a>
							</td>
							<td><?php echo (int) $r['count']; ?></td>
							<td>Tier <?php echo (int) $r['current']; ?></td>
							<td><strong>Tier <?php echo (int) $r['calculated']; ?></strong></td>
							<td>
								<?php if ( 'promote' === $r['action'] ) : ?>
									<span style="color:#0c5f5c;font-weight:600;">Promote</span>
								<?php elseif ( 'demote' === $r['action'] ) : ?>
									<span style="color:#b32d2e;font-weight:600;">Demote</span>
								<?php else : ?>
									<span style="color:#8a6d00;">Blocked</span>
								<?php endif; ?>
								<br /><span class="description"><?php echo esc_html( $r['reason'] ); ?></span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:18px;">
				<?php wp_nonce_field( 'jrc_run_tiers' ); ?>
				<input type="hidden" name="action" value="jrc_run_tiers" />
				<button class="button button-primary"
					onclick="return confirm('This changes partner tiers and emails the partners affected. Continue?');">
					Apply these changes now
				</button>
				<span class="description" style="margin-left:8px;">
					Blocked rows stay as they are.
				</span>
			</form>
		<?php endif; ?>
	</div>
	<?php
}
