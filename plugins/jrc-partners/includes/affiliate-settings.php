<?php
/**
 * Affiliate Programme Settings: the ACF options page holding every rate and threshold.
 *
 * Before this existed the tier thresholds sat hardcoded in jrc_affiliate_thresholds()
 * and the percentages in jrc_partner_tiers(). Both are now editable, so the client can
 * revise the programme without a deploy.
 *
 * Everything reads through jrc_aff_setting(), which falls back to the previous
 * hardcoded value when a field is blank. An empty options page therefore behaves
 * exactly as the programme did before, which is what makes this safe to ship ahead of
 * the client confirming their final figures.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Defaults, used whenever the matching ACF field is empty.
 *
 * These are the values the programme ran on before the settings page existed, so
 * nothing changes until someone deliberately edits a field.
 */
function jrc_aff_setting_defaults() {
	return array(
		// Estate Agent tier thresholds — bookings in a rolling 12 months.
		'aff_tier1_threshold'       => 2,
		'aff_tier2_threshold'       => 12,
		'aff_tier3_threshold'       => 36,

		// Blacklisted Estate Agents. Spec leaves the scope open pending the client;
		// 'free_bonus' is the conservative reading (only the new perk is withdrawn).
		'aff_blacklist_scope'       => 'free_bonus',
		'aff_blacklist_december'    => 0,

		// Property Management. Threshold is "number of properties managed" until the
		// client says otherwise — see jrc_affiliate_subtypes() where it is applied.
		'aff_pm_threshold'          => 25,
		'aff_pm_small_customer'     => 10,
		'aff_pm_small_affiliate'    => 0,
		'aff_pm_small_december'     => 0,
		'aff_pm_large_customer'     => 15,
		'aff_pm_large_affiliate'    => 15,
		'aff_pm_large_december'     => 1,
		// Commission / Discount. Placeholder figures until the client confirms them.
		'aff_pm_comm_customer'      => 0,
		'aff_pm_comm_rate'          => 10,
		'aff_pm_comm_december'      => 0,
		'aff_pm_disc_customer'      => 15,
		'aff_pm_disc_december'      => 0,

		// Holiday Lets.
		'aff_hl_mgmt_customer'      => 10,
		'aff_hl_mgmt_affiliate'     => 15,
		'aff_hl_portfolio_customer' => 10,
		'aff_hl_portfolio_affiliate'=> 10,
		// Per-tier overrides. Blank (null) inherits the tier below, and Tier 1 inherits
		// the flat figures above — see jrc_aff_tier_rates().
		'aff_hl_mgmt_t1_customer'        => null,
		'aff_hl_mgmt_t1_affiliate'       => null,
		'aff_hl_mgmt_t2_customer'        => null,
		'aff_hl_mgmt_t2_affiliate'       => null,
		'aff_hl_mgmt_t3_customer'        => null,
		'aff_hl_mgmt_t3_affiliate'       => null,
		'aff_hl_portfolio_t1_customer'   => null,
		'aff_hl_portfolio_t1_affiliate'  => null,
		'aff_hl_portfolio_t2_customer'   => null,
		'aff_hl_portfolio_t2_affiliate'  => null,
		'aff_hl_portfolio_t3_customer'   => null,
		'aff_hl_portfolio_t3_affiliate'  => null,

		// Price lists. Rounding applied after an account's discount; 0 = to the penny.
		'pl_rounding'               => 0,

		// Automation. Both default OFF: tier changes move real money, so nothing starts
		// adjusting rates on a deploy. See includes/affiliate-tier-auto.php.
		'aff_auto_tier'             => 0,
		'aff_auto_tier_demote'      => 0,

		// Booking capacity. 0 means unlimited, which is how the request form behaved
		// before includes/affiliate-availability.php existed.
		'aff_daily_capacity'        => 0,
		'aff_booking_lead_days'     => 0,
		'aff_booking_horizon_days'  => 180,
	);
}

/**
 * Read one programme setting.
 *
 * @param string $key     Field name from jrc_aff_setting_defaults().
 * @param mixed  $default Optional override for the registered default.
 * @return mixed
 */
function jrc_aff_setting( $key, $default = null ) {
	static $cache = array();
	static $generation = 0;

	$now = (int) ( $GLOBALS['jrc_aff_setting_generation'] ?? 0 );
	if ( $now !== $generation ) {
		$cache      = array();
		$generation = $now;
	}

	if ( array_key_exists( $key, $cache ) ) {
		return $cache[ $key ];
	}

	$defaults = jrc_aff_setting_defaults();
	$fallback = null !== $default ? $default : ( $defaults[ $key ] ?? null );

	$value = function_exists( 'get_field' ) ? get_field( $key, 'options' ) : null;

	// A blank string means "not set" — but 0 is a legitimate rate, so only an empty
	// string or null falls back.
	if ( null === $value || '' === $value ) {
		$value = $fallback;
	}

	$cache[ $key ] = $value;
	return $value;
}

/**
 * Drop the per-request cache.
 *
 * Reads are cached for the request, so without this the admin screen that just saved a
 * rate would carry on reporting the old one until the next page load.
 */
function jrc_aff_setting_flush() {
	// The cache lives in a static inside jrc_aff_setting(); bump a global the function
	// checks rather than reaching into it.
	$GLOBALS['jrc_aff_setting_generation'] = ( $GLOBALS['jrc_aff_setting_generation'] ?? 0 ) + 1;
}
add_action( 'acf/save_post', function ( $post_id ) {
	if ( 'options' === $post_id ) {
		jrc_aff_setting_flush();
	}
}, 20 );

/** Numeric helper — settings come back from ACF as strings. */
function jrc_aff_setting_num( $key, $default = null ) {
	return (float) jrc_aff_setting( $key, $default );
}

// ---------------------------------------------------------------------------
// Options page
// ---------------------------------------------------------------------------

add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_options_page' ) ) {
		return;
	}

	// Sits under the "Partner Programme" menu registered in affiliate-admin.php.
	// It used to be the top-level page itself, but ACF adds options-page menus late on
	// admin_menu, so anything adding a submenu to it registered against a parent that
	// did not exist yet and silently vanished. Owning the parent ourselves removes the
	// ordering dependency entirely.
	acf_add_options_page( array(
		'page_title'  => __( 'Partner Programme Settings', 'jrc-partners' ),
		'menu_title'  => __( 'Settings', 'jrc-partners' ),
		'menu_slug'   => 'affiliate-settings',
		'parent_slug' => 'jrc-partner',
		'capability'  => 'manage_options',
		'redirect'    => false,
	) );
} );

add_action( 'acf/init', function () {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$pct = function ( $key, $label, $instructions = '' ) {
		return array(
			'key'          => 'field_jrc_' . $key,
			'label'        => $label,
			'name'         => $key,
			'type'         => 'number',
			'min'          => 0,
			'max'          => 100,
			'step'         => 0.5,
			'append'       => '%',
			'instructions' => $instructions,
			'wrapper'      => array( 'width' => '25' ),
		);
	};

	$yesno = function ( $key, $label, $instructions = '' ) {
		return array(
			'key'          => 'field_jrc_' . $key,
			'label'        => $label,
			'name'         => $key,
			'type'         => 'true_false',
			'ui'           => 1,
			'instructions' => $instructions,
			'wrapper'      => array( 'width' => '25' ),
		);
	};

	$tab = function ( $label ) {
		return array(
			'key'   => 'field_jrc_afftab_' . sanitize_title( $label ),
			'label' => $label,
			'type'  => 'tab',
		);
	};

	acf_add_local_field_group( array(
		'key'      => 'group_jrc_affiliate_settings',
		'title'    => 'Partner Programme Settings',
		'location' => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'affiliate-settings' ) ) ),
		'fields'   => array(

			$tab( 'Estate Agents' ),
			array(
				'key'   => 'field_jrc_aff_msg_ea',
				'label' => '',
				'type'  => 'message',
				'message' => 'Tier percentages are edited under <strong>Theme Settings &rarr; Partner tiers</strong>. This tab controls how many bookings a branch needs to reach each tier, counted over a rolling twelve months.',
			),
			array( 'key' => 'field_jrc_aff_tier1_threshold', 'label' => 'Tier 1 threshold', 'name' => 'aff_tier1_threshold', 'type' => 'number', 'min' => 0, 'append' => 'bookings / year', 'wrapper' => array( 'width' => '33' ) ),
			array( 'key' => 'field_jrc_aff_tier2_threshold', 'label' => 'Tier 2 threshold', 'name' => 'aff_tier2_threshold', 'type' => 'number', 'min' => 0, 'append' => 'bookings / year', 'wrapper' => array( 'width' => '33' ) ),
			array( 'key' => 'field_jrc_aff_tier3_threshold', 'label' => 'Tier 3 threshold', 'name' => 'aff_tier3_threshold', 'type' => 'number', 'min' => 0, 'append' => 'bookings / year', 'wrapper' => array( 'width' => '33' ) ),

			array(
				'key'     => 'field_jrc_aff_blacklist_scope',
				'label'   => 'Blacklisted agents lose',
				'name'    => 'aff_blacklist_scope',
				'type'    => 'select',
				'choices' => array(
					'free_bonus' => 'The free bonus only — tier discounts still apply',
					'all'        => 'All bonuses and discounts',
				),
				'default_value' => 'free_bonus',
				'instructions'  => 'What a Blacklisted Estate Agent account is excluded from.',
				'wrapper'       => array( 'width' => '50' ),
			),
			$yesno( 'aff_blacklist_december', 'Blacklisted in December draw?', 'Off means blacklisted branches are excluded from the prize draw.' ),

			$tab( 'Property Management' ),
			array(
				'key'          => 'field_jrc_aff_pm_threshold',
				'label'        => 'Small / Large threshold',
				'name'         => 'aff_pm_threshold',
				'type'         => 'number',
				'min'          => 0,
				'append'       => 'properties managed',
				'instructions' => 'An account at or above this figure is treated as Large. Used as guidance in the admin — the sub-type is still set manually per account.',
				'wrapper'      => array( 'width' => '50' ),
			),
			$pct( 'aff_pm_small_customer', 'Small: branch & referrals' ),
			$pct( 'aff_pm_small_affiliate', 'Small: own home' ),
			$yesno( 'aff_pm_small_december', 'Small: December draw?' ),
			$pct( 'aff_pm_large_customer', 'Large: branch & referrals' ),
			$pct( 'aff_pm_large_affiliate', 'Large: own home' ),
			$yesno( 'aff_pm_large_december', 'Large: December draw?' ),
			$pct( 'aff_pm_comm_customer', 'Commission: discount on bookings', 'Discount on work the account books or refers, if any.' ),
			$pct( 'aff_pm_comm_rate', 'Commission: commission rate', 'Paid on completed work booked through the programme.' ),
			$yesno( 'aff_pm_comm_december', 'Commission: December draw?' ),
			array( 'key' => 'field_jrc_aff_pm_spacer', 'label' => '', 'type' => 'message', 'message' => '', 'wrapper' => array( 'width' => '25' ) ),
			$pct( 'aff_pm_disc_customer', 'Discount: discount on all work', 'Applied in place of commission.' ),
			$yesno( 'aff_pm_disc_december', 'Discount: December draw?' ),

			$tab( 'Holiday Lets' ),
			array(
				'key'     => 'field_jrc_aff_msg_hl',
				'label'   => '',
				'type'    => 'message',
				'message' => '<strong>Single Owner</strong> accounts receive no discounts or bonuses and are not in the December draw, so they have no settings here. <strong>Portfolio Owner</strong> accounts are excluded from the December draw by design.',
			),
			array(
				'key'     => 'field_jrc_aff_msg_hl_tiers',
				'label'   => '',
				'type'    => 'message',
				'message' => 'Management Company and Portfolio Owner accounts move through the same tiers as Estate Agents (thresholds on the Estate Agents tab) at their own percentages. '
					. 'The first row is the Tier 1 rate. Leave a Tier 2 or Tier 3 box blank to keep the tier below\'s rate.',
			),
			$pct( 'aff_hl_mgmt_customer', 'Management Co Tier 1: bookings & referrals' ),
			$pct( 'aff_hl_mgmt_affiliate', 'Management Co Tier 1: own property' ),
			$pct( 'aff_hl_portfolio_customer', 'Portfolio Tier 1: bookings & referrals' ),
			$pct( 'aff_hl_portfolio_affiliate', 'Portfolio Tier 1: own property' ),
			$pct( 'aff_hl_mgmt_t2_customer', 'Management Co Tier 2: bookings & referrals' ),
			$pct( 'aff_hl_mgmt_t2_affiliate', 'Management Co Tier 2: own property' ),
			$pct( 'aff_hl_portfolio_t2_customer', 'Portfolio Tier 2: bookings & referrals' ),
			$pct( 'aff_hl_portfolio_t2_affiliate', 'Portfolio Tier 2: own property' ),
			$pct( 'aff_hl_mgmt_t3_customer', 'Management Co Tier 3: bookings & referrals' ),
			$pct( 'aff_hl_mgmt_t3_affiliate', 'Management Co Tier 3: own property' ),
			$pct( 'aff_hl_portfolio_t3_customer', 'Portfolio Tier 3: bookings & referrals' ),
			$pct( 'aff_hl_portfolio_t3_affiliate', 'Portfolio Tier 3: own property' ),

			$tab( 'Automation' ),
			array(
				'key'     => 'field_jrc_aff_msg_auto',
				'label'   => '',
				'type'    => 'message',
				'message' => 'Tier changes alter the discount a branch receives and email the partner automatically. '
					. 'Check <strong>Partner Programme &rarr; Tiers</strong> first — it shows exactly which accounts a run would move, before you switch anything on.',
			),
			array(
				'key'          => 'field_jrc_aff_auto_tier',
				'label'        => 'Recalculate tiers daily',
				'name'         => 'aff_auto_tier',
				'type'         => 'true_false',
				'ui'           => 1,
				'instructions' => 'Runs at 03:00 and sets each tiered account to the tier its last twelve months of bookings earns.',
				'wrapper'      => array( 'width' => '50' ),
			),
			array(
				'key'          => 'field_jrc_aff_auto_tier_demote',
				'label'        => 'Allow tiers to go down',
				'name'         => 'aff_auto_tier_demote',
				'type'         => 'true_false',
				'ui'           => 1,
				'instructions' => 'Off means a run can only ever promote. Leave off unless you are confident the job history is complete — a wrong demotion is an invoice dispute.',
				'wrapper'      => array( 'width' => '50' ),
			),

			$tab( 'Booking capacity' ),
			array(
				'key'     => 'field_jrc_aff_msg_cap',
				'label'   => '',
				'type'    => 'message',
				'message' => 'Controls what a partner may ask for on <strong>My Account &rarr; Request a clean</strong>. '
					. 'Block individual dates under <strong>Partner Programme &rarr; Availability</strong>.',
			),
			array(
				'key'          => 'field_jrc_aff_daily_capacity',
				'label'        => 'Cleans per day',
				'name'         => 'aff_daily_capacity',
				'type'         => 'number',
				'min'          => 0,
				'append'       => 'per day',
				'instructions' => 'How many scheduled cleans a single day can hold before the request form stops offering it. 0 means no limit.',
				'wrapper'      => array( 'width' => '33' ),
			),
			array(
				'key'          => 'field_jrc_aff_booking_lead_days',
				'label'        => 'Minimum notice',
				'name'         => 'aff_booking_lead_days',
				'type'         => 'number',
				'min'          => 0,
				'append'       => 'days',
				'instructions' => 'Earliest a partner may request. 0 allows same-day.',
				'wrapper'      => array( 'width' => '33' ),
			),
			array(
				'key'          => 'field_jrc_aff_booking_horizon_days',
				'label'        => 'Book no further ahead than',
				'name'         => 'aff_booking_horizon_days',
				'type'         => 'number',
				'min'          => 1,
				'append'       => 'days',
				'instructions' => 'Stops requests landing two years out where nobody will see them.',
				'wrapper'      => array( 'width' => '33' ),
			),
		),
	) );
} );
