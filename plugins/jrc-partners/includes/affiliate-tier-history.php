<?php
/**
 * Tier history and the rolling-window warning.
 *
 * A partner's tier was a single integer in user meta, set by hand on the profile screen.
 * That is enough to price a job and nothing else: it cannot answer "since when?", it
 * leaves no audit trail when somebody disputes their rate, and — the reason this file
 * exists — it gives no warning before the twelve-month window quietly drops a booking
 * out of the count and the tier with it. The first a branch knew about a demotion was a
 * different number on an invoice.
 *
 * So every change is appended to a history, and the dashboard can say both "Tier 2
 * since March 2026" and "2 bookings drop out of your count on 3 April".
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** User meta key holding the append-only history. */
const JRC_TIER_HISTORY_META = 'jrc_affiliate_tier_history';

/**
 * The recorded tier history, oldest first.
 *
 * @return array<int,array{tier:int,date:string,by:int}>
 */
function jrc_affiliate_tier_history( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$hist    = get_user_meta( $user_id, JRC_TIER_HISTORY_META, true );
	return is_array( $hist ) ? $hist : array();
}

/**
 * When the current tier started, as a mysql datetime, or '' if never recorded.
 *
 * Reads the last entry rather than searching for the current tier: a branch that went
 * 1 → 2 → 1 is on Tier 1 *since the second change*, not since the account opened.
 */
function jrc_affiliate_tier_since( $user_id = 0 ) {
	$hist = jrc_affiliate_tier_history( $user_id );
	if ( ! $hist ) {
		return '';
	}
	$last = end( $hist );
	return isset( $last['date'] ) ? (string) $last['date'] : '';
}

/**
 * Record a tier and, when it is a genuine change, announce it.
 *
 * @param int  $user_id Affiliate.
 * @param int  $tier    The tier now in force.
 * @param bool $quiet   Record without firing the change action. Used for the first
 *                      entry on an account that predates this file — that is a backfill,
 *                      not a promotion, and emailing everyone about it on deploy day
 *                      would be a bad first impression of the notification system.
 */
function jrc_affiliate_tier_record( $user_id, $tier, $quiet = false ) {
	$user_id = (int) $user_id;
	$tier    = max( 1, min( 3, (int) $tier ) );
	$hist    = jrc_affiliate_tier_history( $user_id );

	$last = $hist ? end( $hist ) : null;
	$old  = $last ? (int) $last['tier'] : 0;

	if ( $old === $tier ) {
		return false;
	}

	$hist[] = array(
		'tier' => $tier,
		'date' => current_time( 'mysql' ),
		'by'   => get_current_user_id(),
	);

	// Trimmed from the front: this is a display aid and an audit note, not an archive,
	// and unbounded meta on a hot key is how user meta rows get to a megabyte.
	if ( count( $hist ) > 40 ) {
		$hist = array_slice( $hist, -40 );
	}

	update_user_meta( $user_id, JRC_TIER_HISTORY_META, $hist );

	if ( ! $quiet && $old ) {
		/**
		 * A partner's tier changed.
		 *
		 * @param int $user_id Affiliate.
		 * @param int $old     Previous tier (0 if this is the first recorded entry).
		 * @param int $new     New tier.
		 */
		do_action( 'jrc_affiliate_tier_changed', $user_id, $old, $tier );
	}

	return true;
}

/**
 * Catch every tier write, wherever it comes from.
 *
 * Hooking the meta write rather than editing jrc_affiliate_save_profile() means an
 * importer, a WP-CLI command or a future automatic promotion is covered too — and
 * update_user_meta() does not fire these when the value is unchanged, so a profile
 * save that touched something else never looks like a promotion.
 */
function jrc_affiliate_tier_meta_watch( $meta_id, $object_id, $meta_key, $meta_value ) {
	if ( 'jrc_affiliate_tier' !== $meta_key ) {
		return;
	}
	// An account that has never had a history entry is being seen for the first time.
	// Record where it stands without announcing it as a change.
	$first = ! jrc_affiliate_tier_history( $object_id );
	jrc_affiliate_tier_record( $object_id, $meta_value, $first );
}
add_action( 'updated_user_meta', 'jrc_affiliate_tier_meta_watch', 10, 4 );
add_action( 'added_user_meta', 'jrc_affiliate_tier_meta_watch', 10, 4 );

/**
 * Bookings about to fall out of the rolling twelve months, and whether losing them
 * costs the partner their tier.
 *
 * NB: this counts jobs the same way jrc_affiliate_data() counts `last12` — every job
 * with a date inside the window, regardless of status. That is deliberate: a warning
 * that disagreed with the number printed right next to it would be worse than no
 * warning at all.
 *
 * It is worth knowing that the two disagree with the copy. The "How the Bonus Program
 * works" list in includes/affiliate-dashboard.php tells the partner that "a job showing
 * as Pending, Booked or Cancelled does not count towards your tier yet", but the
 * counter in jrc_affiliate_data() has never applied a status filter. Changing that
 * would move real accounts between tiers, so it is left alone here and matched rather
 * than silently corrected — but the copy and the code do need reconciling, one way or
 * the other.
 *
 * @param int $user_id Affiliate.
 * @param int $days    How far ahead to look. 60 days is roughly a quarter's notice.
 * @return array{count:int,date:string,at_risk:bool,after:int,threshold:int,tier:int}
 */
function jrc_affiliate_tier_expiry( $user_id = 0, $days = 60 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$tier    = max( 1, min( 3, (int) get_user_meta( $user_id, 'jrc_affiliate_tier', true ) ?: 1 ) );

	$none = array(
		'count'     => 0,
		'date'      => '',
		'at_risk'   => false,
		'after'     => 0,
		'threshold' => 0,
		'tier'      => $tier,
	);

	// Flat-rate accounts do not progress through tiers, so nothing can expire from
	// under them.
	$profile = function_exists( 'jrc_affiliate_profile' ) ? jrc_affiliate_profile( $user_id ) : array();
	if ( isset( $profile['tiered'] ) && ! $profile['tiered'] ) {
		return $none;
	}

	$now     = current_time( 'timestamp' );
	$cutoff  = $now - YEAR_IN_SECONDS;             // Anything older already fell out.
	$horizon = $cutoff + ( (int) $days * DAY_IN_SECONDS );

	$in_window = 0;
	$dropping  = 0;
	$soonest   = 0;

	foreach ( jrc_affiliate_jobs( $user_id ) as $j ) {
		$ts = ! empty( $j['date'] ) ? strtotime( $j['date'] ) : 0;
		if ( ! $ts || $ts < $cutoff ) {
			continue;
		}
		$in_window++;
		if ( $ts <= $horizon ) {
			$dropping++;
			// The job leaves the window a year and a day after it happened.
			$leaves = $ts + YEAR_IN_SECONDS;
			if ( ! $soonest || $leaves < $soonest ) {
				$soonest = $leaves;
			}
		}
	}

	if ( ! $dropping ) {
		return $none;
	}

	$thresholds = jrc_affiliate_thresholds();
	$threshold  = (int) ( $thresholds[ $tier ] ?? 0 );
	$after      = $in_window - $dropping;

	return array(
		'count'     => $dropping,
		'date'      => $soonest ? gmdate( 'Y-m-d', $soonest ) : '',
		'at_risk'   => $threshold > 0 && $after < $threshold,
		'after'     => $after,
		'threshold' => $threshold,
		'tier'      => $tier,
	);
}
