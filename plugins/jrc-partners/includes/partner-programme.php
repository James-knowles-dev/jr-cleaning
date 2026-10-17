<?php
/**
 * Partner programme data: channels and the Estate Agent Bonus Program tiers.
 * Channels are keyed by their page slug under /partners/.
 *
 * Lives in the plugin rather than the theme because it is programme configuration —
 * the rates, tiers and channel definitions the partner platform runs on — not markup.
 * The theme reads it through function_exists() guards, so switching theme can never
 * take the programme's own data with it. The tier CARD MARKUP stays in the theme
 * (jrc_render_tiers(), functions/partners.php): that part genuinely is presentation.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Channels the site actively promotes.
 *
 * All three channels are live. Each has its own sub-types with their own rates and
 * December-draw eligibility — see jrc_affiliate_subtypes(). To pull a channel back off
 * the front end without losing the accounts on it, filter `jrc_active_partner_channels`
 * rather than editing this list; affiliate records keep resolving their labels either
 * way, and `jrc_active_partner_channels()` is what the front end lists.
 *
 * @return string[]
 */
function jrc_active_partner_channel_slugs() {
	return apply_filters( 'jrc_active_partner_channels', array( 'estate-agents', 'property-management', 'holiday-let-management' ) );
}

/** Channels to surface on the front end, keyed by slug. */
function jrc_active_partner_channels() {
	$active = jrc_active_partner_channel_slugs();
	return array_intersect_key( jrc_partner_channels(), array_flip( $active ) );
}

function jrc_partner_channels() {
	// Prefer editable data from Theme Settings.
	if ( function_exists( 'have_rows' ) && have_rows( 'partner_channels', 'options' ) ) {
		$out = array();
		while ( have_rows( 'partner_channels', 'options' ) ) {
			the_row();
			$slug = sanitize_title( get_sub_field( 'slug' ) );
			if ( ! $slug ) { continue; }
			$why = array();
			if ( have_rows( 'why' ) ) {
				while ( have_rows( 'why' ) ) { the_row(); $why[] = array( 't' => get_sub_field( 't' ), 'd' => get_sub_field( 'd' ) ); }
			}
			$out[ $slug ] = array(
				'num'   => get_sub_field( 'num' ),
				'name'  => get_sub_field( 'name' ),
				'sub'   => get_sub_field( 'sub' ),
				'short' => get_sub_field( 'short' ),
				'intro' => get_sub_field( 'intro' ),
				'value' => get_sub_field( 'value' ),
				'why'   => $why,
			);
		}
		if ( $out ) { return $out; }
	}

	return array(
		'estate-agents' => array(
			'num'   => '01',
			'name'  => 'Estate Agents',
			'sub'   => 'Estate Agent Bonus Program',
			'short' => 'A three-tier bonus structure for branches that use us regularly, on direct work orders and referrals alike.',
			'intro' => 'We value the ongoing relationships we build with local estate agents who consistently choose us for their cleaning. To show our appreciation there is a three-tier bonus structure, with eligibility based on the work orders your branch issues directly plus the client referrals you send us.',
			'value' => 'Better rates the more you book',
			'why'   => array(
				array( 't' => 'How eligibility is measured', 'd' => 'Your tier reflects the bookings your branch generates over a rolling year, counting direct work orders and successful referrals together.' ),
				array( 't' => 'Discounts that apply three ways', 'd' => 'To services the branch books itself, to any customer you refer, and from Tier 2 upwards to cleans at your own home.' ),
				array( 't' => 'Your own agency price list', 'd' => 'Your price list sits in your partner account with your agency\'s discounted rates already applied, so nobody works them out on the day. Download it as a branded PDF to send on to landlords and tenants.' ),
				array( 't' => 'Reviewed, not reset', 'd' => 'Tiers run on a rolling twelve months, so a quiet month does not cost you your rate. As your volume grows we move you up.' ),
				array( 't' => 'Protects every deposit', 'd' => 'A 99.6% check-out pass rate keeps tenants, landlords and your own reputation intact.' ),
				array( 't' => 'Proof on every job', 'd' => 'A photo report and sanitisation certificate after each clean, which you can forward straight to a landlord or tenant.' ),
			),
		),
		'property-management' => array(
			'num'   => '02',
			'name'  => 'Property Management',
			'sub'   => 'Portfolio turnover & voids',
			'short' => 'Steady, repeat volume across your managed portfolio, handled by one reliable team.',
			'intro' => 'Managed portfolios generate a steady stream of turnover and void cleans across many units. One reliable team for all of them, with rewards that grow as your volume does.',
			'value' => 'Steady repeat volume across a portfolio',
			'why'   => array(
				array( 't' => 'Steady repeat volume', 'd' => 'Turnovers and voids across your buildings add up to dependable, recurring work.' ),
				array( 't' => 'One point of contact', 'd' => 'A single trusted team for every property, never subcontracted.' ),
				array( 't' => 'Consistent standards', 'd' => 'The same checklist and photo report on every unit, every time.' ),
			),
		),
		'holiday-let-management' => array(
			'num'   => '03',
			'name'  => 'Holiday Let Management',
			'sub'   => 'Changeover cleans',
			'short' => 'A clean between every guest, the highest frequency and the most repeatable of all.',
			'intro' => 'A fresh clean between every guest, smaller jobs, the highest frequency, and the most repeatable of all. Laundry handled too, collected and back within 24 hours.',
			'value' => 'The highest clean frequency of the three',
			'why'   => array(
				array( 't' => 'Highest clean frequency', 'd' => 'A clean between every stay means the most frequent, repeatable referrals.' ),
				array( 't' => 'Laundry sorted too', 'd' => 'Fresh linen and towels collected and returned within 24 hours.' ),
				array( 't' => 'Protects your reviews', 'd' => 'Guest-ready turnarounds, on time, so every review stays five stars.' ),
			),
		),
	);
}

function jrc_partner_tiers() {
	if ( function_exists( 'have_rows' ) && have_rows( 'partner_tiers', 'options' ) ) {
		$out = array();
		while ( have_rows( 'partner_tiers', 'options' ) ) {
			the_row();
			$perks = array();
			if ( have_rows( 'perks' ) ) {
				while ( have_rows( 'perks' ) ) { the_row(); $perks[] = get_sub_field( 'perk' ); }
			}
			$december = array();
			if ( have_rows( 'december' ) ) {
				while ( have_rows( 'december' ) ) { the_row(); $december[] = get_sub_field( 'perk' ); }
			}
			$out[] = array(
				'tier'        => get_sub_field( 'tier' ),
				'qual'        => get_sub_field( 'qual' ),
				'requirement' => get_sub_field( 'requirement' ),
				'customer'    => get_sub_field( 'customer' ),
				'affiliate'   => get_sub_field( 'affiliate' ),
				'perks'       => array_filter( $perks ),
				'december'    => array_filter( $december ),
				'top'         => (bool) get_sub_field( 'top' ),
			);
		}
		if ( $out ) { return $out; }
	}

	/*
	 * Estate Agent Bonus Program.
	 *
	 * `customer`  = discount for the branch and the customers it refers.
	 * `affiliate` = discount for an agent booking for their OWN home. This is a
	 *               discount on their personal cleans, not commission on referred
	 *               work — the affiliate dashboard labels it accordingly.
	 *
	 * Eligibility is measured on direct work orders from the branch plus successful
	 * client referrals, combined.
	 */
	return array(
		array(
			'tier'        => 'Tier 1',
			'qual'        => '2 bookings a year',
			'requirement' => 'List us in your contractor directory and move-out documentation, and generate at least 2 bookings a year, directly or by referral.',
			'customer'    => '10%',
			'affiliate'   => '-',
			'perks'       => array(
				'10% off all services for the branch',
				'10% off for every customer you refer',
				'Agency price list with your rates applied',
			),
			'december'    => array(),
			'top'         => false,
		),
		array(
			'tier'        => 'Tier 2',
			'qual'        => '1 booking a month on average',
			'requirement' => 'Average one booking a month across direct work orders and referrals.',
			'customer'    => '10%',
			'affiliate'   => '15%',
			'perks'       => array(
				'10% off all services for the branch',
				'10% off for every customer you refer',
				'15% off for agents booking their own home',
				'Agency price list with your rates applied',
			),
			'december'    => array(
				'Every agent at the branch entered into our 1 December prize draw',
				'£130 of personal service credit for the winner',
				'Equal odds for all eligible Tier 2 and Tier 3 agents',
			),
			'top'         => false,
		),
		array(
			'tier'        => 'Tier 3',
			'qual'        => '3 bookings a month on average',
			'requirement' => 'Average three bookings a month across direct work orders and referrals.',
			'customer'    => '15%',
			'affiliate'   => '30%',
			'perks'       => array(
				'15% off all services for the branch',
				'15% off for every customer you refer',
				'30% off for agents booking their own home',
				'Agency price list with your rates applied',
			),
			'december'    => array(
				'Every agent at the branch entered into our 1 December prize draw',
				'£130 of personal service credit for the winner',
				'£130 of service credit for the branch on 1 December, shared at the office manager\'s discretion',
			),
			'top'         => true,
		),
	);
}
