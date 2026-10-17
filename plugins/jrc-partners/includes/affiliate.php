<?php
/**
 * Affiliate programme: WooCommerce My Account dashboard + data model.
 *
 * Data lives in user meta (set on the WP user edit screen, or by automation later):
 *   jrc_is_affiliate     (bool)   approved affiliate
 *   jrc_affiliate_tier   (1|2|3)  current tier
 *   jrc_affiliate_code   (string) referral code
 *   jrc_affiliate_channel(string) estate-agents | property-management | holiday-let-management
 *   jrc_affiliate_jobs   (array)  each: [date, type, address, scope, value, status]
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Bookings needed in a rolling 12 months for each tier of the Estate Agent Bonus
 * Program. Counts direct work orders from the branch and successful referrals
 * together, which is how the programme measures eligibility.
 */
function jrc_affiliate_thresholds() {
	// Editable on the Partner Programme Settings page; the defaults registered in
	// jrc_aff_setting_defaults() are the original 2 / 12 / 36.
	return array(
		1 => (int) jrc_aff_setting( 'aff_tier1_threshold' ),
		2 => (int) jrc_aff_setting( 'aff_tier2_threshold' ),
		3 => (int) jrc_aff_setting( 'aff_tier3_threshold' ),
	);
}

/** Is the user an approved affiliate? */
function jrc_is_affiliate( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return $user_id && get_user_meta( $user_id, 'jrc_is_affiliate', true );
}

/*
 * jrc_affiliate_jobs() now lives in includes/affiliate-jobs.php, reading the
 * {prefix}jrc_jobs table. It keeps this file's original signature and return shape, so
 * everything below is unaffected by the move off user meta.
 */

/** Build the affiliate display bundle. */
function jrc_affiliate_data( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$tier    = (int) get_user_meta( $user_id, 'jrc_affiliate_tier', true );
	$tier    = max( 1, min( 3, $tier ? $tier : 1 ) );
	$jobs    = jrc_affiliate_jobs( $user_id );

	$total  = count( $jobs );
	$cutoff = time() - YEAR_IN_SECONDS;
	$last12 = 0;
	$completed_value = 0.0; // Total value of completed referrals (all time).
	foreach ( $jobs as $j ) {
		$ts = ! empty( $j['date'] ) ? strtotime( $j['date'] ) : 0;
		if ( $ts && $ts >= $cutoff ) { $last12++; }
		if ( ! empty( $j['status'] ) && 0 === strcasecmp( trim( $j['status'] ), 'Completed' ) ) {
			$completed_value += (float) preg_replace( '/[^0-9.]/', '', (string) ( $j['value'] ?? '' ) );
		}
	}

	$tiers = function_exists( 'jrc_partner_tiers' ) ? jrc_partner_tiers() : array();
	$td    = isset( $tiers[ $tier - 1 ] ) ? $tiers[ $tier - 1 ] : array();

	// Sub-type decides whether the tier's percentages apply at all. Property Management
	// and Holiday Let accounts carry a flat rate from the settings page; Holiday Let
	// Management/Portfolio accounts keep tier progression but at their own percentages.
	$profile = function_exists( 'jrc_affiliate_profile' ) ? jrc_affiliate_profile( $user_id ) : array();

	// Realised money: apply the effective rates to completed referrals.
	$cust_pct = (float) preg_replace( '/[^0-9.]/', '', (string) ( $td['customer'] ?? '' ) );
	$aff_pct  = (float) preg_replace( '/[^0-9.]/', '', (string) ( $td['affiliate'] ?? '' ) );

	// Flat or per-tier own rates override the Estate Agent tier percentages.
	$own = jrc_affiliate_rates_for_tier( $profile, $tier );
	if ( $own ) {
		list( $cust_pct, $aff_pct ) = $own;
		$td['customer']  = $cust_pct ? $cust_pct . '%' : '-';
		$td['affiliate'] = $aff_pct ? $aff_pct . '%' : '-';
	}
	$saved    = $completed_value * $cust_pct / 100;
	$earned   = $completed_value * $aff_pct / 100;

	$th             = jrc_affiliate_thresholds();
	$tiered         = ! isset( $profile['tiered'] ) || $profile['tiered'];
	$next_tier      = ( $tiered && $tier < 3 ) ? $tier + 1 : 0;
	$next_threshold = $next_tier ? $th[ $next_tier ] : 0;
	$needed         = $next_threshold ? max( 0, $next_threshold - $last12 ) : 0;
	$pct            = $next_threshold ? min( 100, (int) round( $last12 / $next_threshold * 100 ) ) : 100;

	// What the next tier actually changes, so the progress bar has a payoff attached
	// rather than just counting jobs.
	$nd = ( $next_tier && isset( $tiers[ $next_tier - 1 ] ) ) ? $tiers[ $next_tier - 1 ] : array();

	// A tiered account on its own percentages (Holiday Let Management / Portfolio) must
	// show ITS next-tier rates, not the Estate Agent ones it would never receive.
	$next_own = $next_tier ? jrc_affiliate_rates_for_tier( $profile, $next_tier ) : null;
	if ( $next_own ) {
		$nd['customer']  = $next_own[0] ? $next_own[0] . '%' : '';
		$nd['affiliate'] = $next_own[1] ? $next_own[1] . '%' : '';
		// Higher tiers left blank inherit the current rate; promising "10%, up from 10%"
		// is worse than saying nothing.
		if ( $next_own[0] == $cust_pct ) { // phpcs:ignore Universal.Operators.StrictComparisons -- floats from settings.
			$nd['customer'] = '';
		}
		if ( $next_own[1] == $aff_pct ) { // phpcs:ignore Universal.Operators.StrictComparisons
			$nd['affiliate'] = '';
		}
	}

	return array(
		'tier'                => $tier,
		'tier_label'          => isset( $td['tier'] ) ? $td['tier'] : 'Tier ' . $tier,
		'next_label'          => isset( $nd['tier'] ) ? $nd['tier'] : ( $next_tier ? 'Tier ' . $next_tier : '' ),
		'next_customer'       => isset( $nd['customer'] ) ? $nd['customer'] : '',
		'next_affiliate'      => isset( $nd['affiliate'] ) ? $nd['affiliate'] : '',
		'aff_pct'             => $aff_pct,
		'customer_discount'   => isset( $td['customer'] ) ? $td['customer'] : '',
		'affiliate_discount'  => isset( $td['affiliate'] ) ? $td['affiliate'] : '',
		'code'                => get_user_meta( $user_id, 'jrc_affiliate_code', true ),
		'channel'             => get_user_meta( $user_id, 'jrc_affiliate_channel', true ),
		'jobs'                => $jobs,
		'total'               => $total,
		'last12'              => $last12,
		'next_tier'           => $next_tier,
		'next_threshold'      => $next_threshold,
		'needed'              => $needed,
		'pct'                 => $pct,
		'completed_value'     => $completed_value,
		'saved'               => $saved,
		'earned'              => $earned,
		'profile'             => $profile,
	);
}

/**
 * Whether to show monetary columns on the partner-facing tables.
 *
 * The Bonus Program pays no cash commission — `affiliate_discount` is a discount on the
 * agent's own home, not money owed — so this is off. An earlier version multiplied job
 * value by the rate and printed a total that read like a payment due.
 *
 * It lives here rather than in a template because the job table has since moved to its
 * own tab; a local variable in one template is not something another can rely on.
 */
function jrc_affiliate_show_money( $user_id = 0 ) {
	// Commission accounts are the exception: money genuinely is owed to them, so the
	// tables show it.
	$profile = function_exists( 'jrc_affiliate_profile' ) ? jrc_affiliate_profile( $user_id ) : array();
	$default = 'commission' === ( $profile['payout'] ?? '' );
	return (bool) apply_filters( 'jrc_affiliate_show_money', $default, $profile );
}

/** Format a number as GBP, dropping the decimals for whole amounts. */
function jrc_affiliate_money( $n ) {
	$n = (float) $n;
	return '£' . number_format( $n, 0.0 !== fmod( $n, 1 ) ? 2 : 0 );
}

/**
 * The partner area's tabs.
 *
 * Each is a WooCommerce My Account endpoint with its own template. Splitting them out
 * of one long dashboard means a partner can go straight to their calendar or their job
 * history instead of scrolling past everything else to reach it.
 *
 * Keys are the endpoint slug, and therefore the URL: /my-account/<slug>/.
 */
function jrc_affiliate_tabs() {
	return apply_filters( 'jrc_affiliate_tabs', array(
		'affiliate' => array(
			'label'    => __( 'Bonus Program', 'jrc-partners' ),
			'title'    => 'Estate Agent Bonus Program',
			'template' => 'affiliate-dashboard.php',
		),
		'request-clean' => array(
			'label'    => __( 'Request a clean', 'jrc-partners' ),
			'title'    => 'Request a clean',
			'template' => 'affiliate-request-tab.php',
		),
		'reports' => array(
			'label'    => __( 'Reports', 'jrc-partners' ),
			'title'    => 'Your reports',
			'template' => 'affiliate-reports-tab.php',
		),
		'cleaning-calendar' => array(
			'label'    => __( 'Calendar', 'jrc-partners' ),
			'title'    => 'Your cleaning calendar',
			'template' => 'affiliate-calendar.php',
		),
		'referred-jobs' => array(
			'label'    => __( 'Referred jobs', 'jrc-partners' ),
			'title'    => 'Referred jobs',
			'template' => 'affiliate-jobs-tab.php',
		),
		'price-list' => array(
			'label'    => __( 'Price list', 'jrc-partners' ),
			'title'    => 'Your price list',
			'template' => 'affiliate-price-list.php',
		),
		// Only for School accounts (see 'only_views' in jrc_affiliate_tabs_for_user()).
		'site-map' => array(
			'label'      => __( 'Site map', 'jrc-partners' ),
			'title'      => 'Your site map',
			'template'   => 'affiliate-site-map.php',
			'only_views' => array( 'school' ),
		),
	) );
}

/**
 * The tabs as the logged-in account should see them.
 *
 * jrc_affiliate_tabs() stays account-agnostic because it also registers the rewrite
 * endpoints, before anyone is necessarily logged in. Labels and titles that depend on
 * the account type are applied here instead.
 */
function jrc_affiliate_tabs_for_user( $user_id = 0 ) {
	$tabs  = jrc_affiliate_tabs();
	$names = function_exists( 'jrc_affiliate_area_names' ) ? jrc_affiliate_area_names( $user_id ) : array();

	if ( isset( $tabs['affiliate'] ) && $names ) {
		$tabs['affiliate']['label'] = $names['label'];
		$tabs['affiliate']['title'] = $names['title'];
	}

	$profile = function_exists( 'jrc_affiliate_profile' ) ? jrc_affiliate_profile( $user_id ) : array();
	if ( 'basic' === ( $profile['view'] ?? '' ) && isset( $tabs['referred-jobs'] ) ) {
		$tabs['referred-jobs']['label'] = __( 'Cleans', 'jrc-partners' );
		$tabs['referred-jobs']['title'] = 'Cleans at your property';
	}

	// Account types that do not get every tab (e.g. Commercial: no job history or price list).
	foreach ( (array) ( $profile['hide_tabs'] ?? array() ) as $hidden ) {
		unset( $tabs[ $hidden ] );
	}
	// Tabs that exist for one kind of account only (e.g. the School site map).
	foreach ( $tabs as $slug => $tab ) {
		if ( ! empty( $tab['only_views'] ) && ! in_array( $profile['view'] ?? '', (array) $tab['only_views'], true ) ) {
			unset( $tabs[ $slug ] );
		}
	}
	// Site map sits right after the account home.
	if ( isset( $tabs['site-map'], $tabs['affiliate'] ) ) {
		$tabs = array( 'affiliate' => $tabs['affiliate'], 'site-map' => $tabs['site-map'] ) + $tabs;
	}

	return $tabs;
}

/**
 * URL of one partner tab.
 *
 * Templates paginate against this rather than naming an endpoint directly — the job
 * table's pager was still pointing at the Bonus Program tab after the table moved to
 * its own, which sent anyone paging back to the wrong screen.
 */
function jrc_affiliate_tab_url( $slug ) {
	if ( ! function_exists( 'wc_get_account_endpoint_url' ) ) {
		return '';
	}
	$tabs = jrc_affiliate_tabs();
	return isset( $tabs[ $slug ] ) ? wc_get_account_endpoint_url( $slug ) : '';
}

/**
 * Register the endpoints.
 *
 * The flush sentinel carries a suffix as well as the theme version: adding an endpoint
 * needs a rewrite flush, and the version alone would not change on a deploy that only
 * touches this file.
 */
add_action( 'init', function () {
	foreach ( array_keys( jrc_affiliate_tabs() ) as $slug ) {
		add_rewrite_endpoint( $slug, EP_ROOT | EP_PAGES );
	}

	/*
	 * v6: the Site map tab. v5: the Price list tab. v4 was the theme -> plugin move.
	 *
	 * The suffix is what actually forces the flush here: JRCP_VERSION is '1.0.0' and so
	 * was JRC_VERSION, so the sentinel would have been byte-identical to the one already
	 * in the database from when this code lived in the theme — the flush would have been
	 * skipped and every partner tab would 404. Deploys rsync files and never activate a
	 * plugin, so this line is what heals staging and production.
	 */
	$sentinel = JRCP_VERSION . ':tabs-v6-site-map';
	if ( get_option( 'jrc_aff_flush' ) !== $sentinel ) {
		flush_rewrite_rules( false );
		update_option( 'jrc_aff_flush', $sentinel );
	}
} );

/** Trim the My Account nav to the tabs this site uses. */
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	unset( $items['orders'], $items['downloads'], $items['edit-address'] );
	return $items;
}, 5 );

/** Insert the partner tabs, in order, just above Logout. */
add_filter( 'woocommerce_account_menu_items', function ( $items ) {
	if ( ! jrc_is_affiliate() ) {
		return $items;
	}

	$tabs = wp_list_pluck( jrc_affiliate_tabs_for_user(), 'label' );

	// A count beside "Reports" is the whole point of tracking what's been seen.
	$unread = function_exists( 'jrc_reports_new_count' ) ? jrc_reports_new_count() : 0;
	if ( $unread && isset( $tabs['reports'] ) ) {
		$tabs['reports'] .= ' <span class="aff-nav-count">' . (int) $unread . '</span>';
	}

	$new = array();
	foreach ( $items as $key => $label ) {
		if ( 'customer-logout' === $key ) {
			foreach ( $tabs as $slug => $tab_label ) {
				$new[ $slug ] = $tab_label;
			}
		}
		$new[ $key ] = $label;
	}

	// No logout item to anchor against — append rather than lose them.
	foreach ( $tabs as $slug => $tab_label ) {
		if ( ! isset( $new[ $slug ] ) ) {
			$new[ $slug ] = $tab_label;
		}
	}

	return $new;
} );

/** Titles and renderers, wired per tab so adding one needs no new plumbing. */
foreach ( array_keys( jrc_affiliate_tabs() ) as $jrc_tab_slug ) {

	add_filter( 'woocommerce_endpoint_' . $jrc_tab_slug . '_title', function () use ( $jrc_tab_slug ) {
		$tabs = jrc_affiliate_tabs_for_user();
		return $tabs[ $jrc_tab_slug ]['title'] ?? '';
	} );

	add_action( 'woocommerce_account_' . $jrc_tab_slug . '_endpoint', function () use ( $jrc_tab_slug ) {
		if ( ! jrc_is_affiliate() ) {
			echo '<p class="aff-empty">Your branch is not set up on the Bonus Program yet. <a href="' . esc_url( home_url( '/partners/register/' ) ) . '">Join the program</a> or get in touch and we will set you up.</p>';
			return;
		}

		// A tab hidden for this account type is closed, not just missing from the menu —
		// the endpoint URL still exists for everyone.
		$mine = jrc_affiliate_tabs_for_user();
		if ( ! isset( $mine[ $jrc_tab_slug ] ) ) {
			echo '<p class="aff-empty">This section is not part of your account. <a href="' . esc_url( jrc_affiliate_tab_url( 'affiliate' ) ) . '">Back to your account</a>.</p>';
			return;
		}

		$tabs     = jrc_affiliate_tabs();
		$template = $tabs[ $jrc_tab_slug ]['template'] ?? '';

		// Theme override first, then the plugin's own copy — see jrcp_locate_template().
		// This used to be a bare locate_template(), which resolved only inside the theme
		// and would therefore render nothing at all under a different theme.
		$file = $template ? jrcp_locate_template( $template ) : '';
		if ( ! $file ) {
			return;
		}

		// $tab_slug lets a template build links back into the tab it is rendering in,
		// without hardcoding a slug that a filter on jrc_affiliate_tabs() could change.
		$tab_slug = $jrc_tab_slug;
		$aff      = jrc_affiliate_data();
		include $file;
	} );
}
unset( $jrc_tab_slug );

function jrc_affiliate_profile_fields( $user ) {
	if ( ! current_user_can( 'edit_users' ) ) { return; }
	$is      = get_user_meta( $user->ID, 'jrc_is_affiliate', true );
	$tier    = (int) get_user_meta( $user->ID, 'jrc_affiliate_tier', true );
	$tier    = $tier ? $tier : 1;
	$code    = get_user_meta( $user->ID, 'jrc_affiliate_code', true );
	$channel = get_user_meta( $user->ID, 'jrc_affiliate_channel', true );

	// Uncapped: the save deletes manual rows that were rendered and then removed, so
	// the screen has to render all of them. Only manual rows are editable here; jobs
	// from the Connecteam sync, partner requests and website enquiries are owned by
	// their own pipelines and are listed read-only below the editor.
	$jobs        = array();
	$synced_jobs = array();
	foreach ( jrc_affiliate_jobs( $user->ID, 100000 ) as $j ) {
		if ( 'manual' === ( $j['source'] ?? 'manual' ) ) {
			$jobs[] = $j;
		} else {
			$synced_jobs[] = $j;
		}
	}

	// Channel options: prefer the partner channels from Theme Settings, else the
	// three standard channels. Stored value is the slug; label is the display name.
	$channels = array();
	if ( function_exists( 'jrc_partner_channels' ) ) {
		foreach ( jrc_partner_channels() as $slug => $c ) {
			$channels[ $slug ] = ! empty( $c['name'] ) ? $c['name'] : $slug;
		}
	}
	if ( ! $channels ) {
		$channels = array(
			'estate-agents'          => 'Estate Agents',
			'property-management'    => 'Property Management',
			'holiday-let-management' => 'Holiday Let Management',
		);
	}
	// Account-only channels (e.g. Commercial) are not marketing channels, so add them here.
	if ( function_exists( 'jrc_account_channel_choices' ) ) {
		$channels += jrc_account_channel_choices();
	}
	// Keep any legacy value that isn't in the list so it's never silently dropped.
	if ( '' !== (string) $channel && ! isset( $channels[ $channel ] ) ) {
		$channels = array( $channel => $channel ) + $channels;
	}

	// Seed for the referral-code generator: surname, else last word of display name.
	$code_seed = $user->last_name;
	if ( ! $code_seed && $user->display_name ) {
		$parts     = preg_split( '/\s+/', trim( $user->display_name ) );
		$code_seed = end( $parts );
	}

	// Codes already in use (other users), so the generator can avoid collisions.
	global $wpdb;
	$existing_codes = $wpdb->get_col( $wpdb->prepare(
		"SELECT DISTINCT meta_value FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value <> '' AND user_id <> %d",
		'jrc_affiliate_code',
		$user->ID
	) );
	$existing_codes = array_values( array_map( 'strtoupper', $existing_codes ) );
	?>
	<h2>Affiliate programme</h2>
	<?php
	// Proof that this section was actually rendered for someone allowed to edit it. The
	// save handler refuses without it — see jrc_affiliate_save_profile().
	wp_nonce_field( 'jrc_aff_profile_' . $user->ID, 'jrc_aff_profile_nonce' );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th><label for="jrc_is_affiliate">Affiliate</label></th>
			<td><label><input type="checkbox" id="jrc_is_affiliate" name="jrc_is_affiliate" value="1" <?php checked( $is, '1' ); ?> /> Approved affiliate (shows the dashboard tab)</label></td>
		</tr>
		<tr>
			<th><label for="jrc_affiliate_tier">Tier</label></th>
			<td><select id="jrc_affiliate_tier" name="jrc_affiliate_tier"><?php for ( $i = 1; $i <= 3; $i++ ) { echo '<option value="' . $i . '" ' . selected( $tier, $i, false ) . '>Tier ' . $i . '</option>'; } ?></select></td>
		</tr>
		<tr>
			<th><label for="jrc_affiliate_code">Referral code</label></th>
			<td>
				<input type="text" id="jrc_affiliate_code" name="jrc_affiliate_code" value="<?php echo esc_attr( $code ); ?>" class="regular-text" />
				<button type="button" class="button" id="jrc-code-gen" data-prefix="JRC-" data-seed="<?php echo esc_attr( $code_seed ); ?>" data-existing="<?php echo esc_attr( wp_json_encode( $existing_codes ) ); ?>">Generate</button>
				<p class="description">Shared with the affiliate's clients so their bookings are credited to them.</p>
			</td>
		</tr>
		<tr>
			<th><label for="jrc_affiliate_channel">Channel</label></th>
			<td>
				<select id="jrc_affiliate_channel" name="jrc_affiliate_channel">
					<option value="">—</option>
					<?php foreach ( $channels as $slug => $label ) : ?>
						<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $channel, $slug ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
			</td>
		</tr>
		<tr>
			<th><label for="jrc_affiliate_subtype">Account type</label></th>
			<td>
				<?php
				$cur_channel = jrc_affiliate_channel( $user->ID );
				$cur_subtype = jrc_affiliate_subtype( $user->ID );
				?>
				<select id="jrc_affiliate_subtype" name="jrc_affiliate_subtype">
					<?php foreach ( jrc_affiliate_subtypes() as $ch => $subs ) : ?>
						<optgroup label="<?php echo esc_attr( $channels[ $ch ] ?? $ch ); ?>" data-channel="<?php echo esc_attr( $ch ); ?>">
							<?php foreach ( $subs as $slug => $def ) : ?>
								<option value="<?php echo esc_attr( $slug ); ?>"
									data-channel="<?php echo esc_attr( $ch ); ?>"
									<?php selected( $ch === $cur_channel && $slug === $cur_subtype ); ?>>
									<?php echo esc_html( $def['label'] ); ?>
								</option>
							<?php endforeach; ?>
						</optgroup>
					<?php endforeach; ?>
				</select>
				<p class="description">Sub-type within the channel above. Decides the dashboard view, the discount rates and December prize-draw eligibility. Rates are set under <a href="<?php echo esc_url( admin_url( 'admin.php?page=affiliate-settings' ) ); ?>">Partner Programme</a>.</p>
			</td>
		</tr>
		<tr class="jrc-bonus-row">
			<th><label for="jrc_affiliate_free_bonus">Chosen free bonus</label></th>
			<td>
				<?php $bonus_now = (string) get_user_meta( $user->ID, 'jrc_affiliate_free_bonus', true ); ?>
				<select id="jrc_affiliate_free_bonus" name="jrc_affiliate_free_bonus">
					<option value="">— none chosen —</option>
					<?php foreach ( jrc_free_bonus_options() as $k => $label ) : ?>
						<option value="<?php echo esc_attr( $k ); ?>" <?php selected( $bonus_now, $k ); ?>><?php echo esc_html( $label ); ?></option>
					<?php endforeach; ?>
				</select>
				<p class="description">
					Applies free to every full end-of-tenancy clean this account books. The partner can change it themselves from their dashboard.
					<?php if ( ! jrc_affiliate_has_free_bonus( $user->ID ) ) : ?>
						<br /><strong>Not active for this account type</strong> — a stored choice is ignored while the account is not eligible.
					<?php endif; ?>
				</p>
			</td>
		</tr>
		<?php $pkg = jrc_affiliate_package( $user->ID ); ?>
		<tr class="jrc-package-row">
			<th><label for="jrc_affiliate_package_name">Package</label></th>
			<td>
				<input type="text" id="jrc_affiliate_package_name" name="jrc_affiliate_package_name" value="<?php echo esc_attr( $pkg['name'] ); ?>" class="regular-text" placeholder="e.g. Changeover package" />
				<input type="text" name="jrc_affiliate_package_frequency" value="<?php echo esc_attr( $pkg['frequency'] ); ?>" class="regular-text" placeholder="e.g. After every guest stay" />
				<br />
				<textarea name="jrc_affiliate_package_items" rows="4" class="large-text" placeholder="One line per item included, e.g. Full linen change"><?php echo esc_textarea( implode( "\n", $pkg['lines'] ) ); ?></textarea>
				<p class="description">Shown on the dashboard of <strong>Holiday Let — Single Owner</strong> accounts: package name, how often, and what is included.</p>
			</td>
		</tr>
		<tr>
			<th><label>Referred jobs</label></th>
			<td>
				<div class="jrc-jobs">
					<?php
					// No column-header row here on purpose. Every field carries its own
					// label inside the card — see jrc_affiliate_job_row_html() for what
					// went wrong when the headings lived somewhere else.
					?>
					<?php // The rows this form rendered. Only these can be deleted by being removed. ?>
					<input type="hidden" name="jrc_aff_jobs_shown" value="<?php echo esc_attr( implode( ',', array_filter( array_map( 'intval', wp_list_pluck( $jobs, 'id' ) ) ) ) ); ?>" />
					<div id="jrc-jobs-list">
						<?php
						if ( $jobs ) {
							foreach ( $jobs as $j ) {
								echo jrc_affiliate_job_row_html( $j ); // phpcs:ignore WordPress.Security.EscapeOutput -- values escaped in helper.
							}
						} else {
							echo jrc_affiliate_job_row_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup.
						}
						?>
					</div>
					<p class="jrc-jobs__foot">
						<button type="button" class="button" id="jrc-jobs-add">+ Add job</button>
						<span class="jrc-jobs__total">Completed job value: <strong id="jrc-jobs-total">£0</strong></span>
					</p>
				</div>
				<p class="description">One row per referred clean. Clear a row and save to remove it. The customer's dashboard sorts newest-first automatically.</p>

				<?php if ( $synced_jobs ) : ?>
					<h4 style="margin:24px 0 6px;">Jobs from other sources (read-only)</h4>
					<p class="description" style="margin-top:0;">Synced from Connecteam, requested by the partner or received as website enquiries. Edit these in their own screens; saving this profile never changes them.</p>
					<table class="widefat striped" style="max-width:1100px;margin-top:8px;">
						<thead><tr><th>Date</th><th>Scheduled</th><th>Service</th><th>Address</th><th>Status</th><th>Source</th></tr></thead>
						<tbody>
							<?php foreach ( $synced_jobs as $sj ) : ?>
								<tr>
									<td><?php echo esc_html( $sj['date'] ?: '—' ); ?></td>
									<td><?php echo esc_html( $sj['scheduled_date'] ? date_i18n( 'j M Y H:i', strtotime( $sj['scheduled_date'] ) ) : '—' ); ?></td>
									<td><?php echo esc_html( $sj['type'] ?: '—' ); ?></td>
									<td><?php echo esc_html( $sj['address'] ?: '—' ); ?></td>
									<td><?php echo esc_html( $sj['status'] ?: '—' ); ?></td>
									<td><?php echo esc_html( ucfirst( (string) $sj['source'] ) ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<template id="jrc-job-tpl"><?php echo jrc_affiliate_job_row_html(); // phpcs:ignore WordPress.Security.EscapeOutput -- static markup. ?></template>

	<style>
		/* One card per job.
		   Named grid areas rather than a bare column count: adding a field then means
		   naming it in the template, which fails visibly and immediately instead of
		   silently pushing every later field into the wrong column — which is exactly
		   how the previous layout broke. */
		.jrc-jobs { max-width: 1100px; }
		.jrc-job-row {
			position: relative;
			display: grid;
			gap: 12px 14px;
			grid-template-columns: 160px 190px minmax(0, 1fr) 150px;
			grid-template-areas:
				"date  sched   type    status"
				"addr  addr    value   value"
				"scope scope   scope   report";
			align-items: start;
			padding: 16px 40px 16px 16px;
			margin-bottom: 12px;
			background: #fff;
			border: 1px solid #dcdcde;
			border-left: 3px solid #c3c4c7;
			border-radius: 4px;
		}
		.jrc-job-row:hover { border-left-color: #2271b1; }
		.jrc-job-row:focus-within { border-left-color: #2271b1; box-shadow: 0 0 0 1px #2271b1; }

		.jrc-job-f--date   { grid-area: date; }
		.jrc-job-f--sched  { grid-area: sched; }
		.jrc-job-f--type   { grid-area: type; }
		.jrc-job-f--status { grid-area: status; }
		.jrc-job-f--address{ grid-area: addr; }
		.jrc-job-f--value  { grid-area: value; }
		.jrc-job-f--scope  { grid-area: scope; }
		.jrc-job-f--report { grid-area: report; }

		.jrc-job-f { display: flex; flex-direction: column; gap: 4px; min-width: 0; }
		.jrc-job-f__label {
			font-size: 11px; font-weight: 600; text-transform: uppercase;
			letter-spacing: .04em; color: #646970; line-height: 1.4;
		}
		.jrc-job-f__label em { font-style: normal; font-weight: 400; text-transform: none; letter-spacing: 0; color: #8c8f94; }

		/* width:100% plus min-width:0 on the wrapper is what stops a long select or a
		   date input forcing its column wider than its share and pushing the card off
		   the right of the screen. */
		.jrc-job-row input[type="date"],
		.jrc-job-row input[type="datetime-local"],
		.jrc-job-row input[type="text"],
		.jrc-job-row select,
		.jrc-job-row textarea { margin: 0; width: 100%; max-width: 100%; box-sizing: border-box; }
		.jrc-job-row textarea { min-height: 54px; resize: vertical; font-size: 13px; line-height: 1.45; }

		.jrc-job-remove {
			position: absolute; top: 8px; right: 8px;
			width: 24px; height: 24px; padding: 0;
			display: flex; align-items: center; justify-content: center;
			border: 0; border-radius: 3px; background: transparent;
			color: #b32d2e; font-size: 18px; line-height: 1; cursor: pointer;
		}
		.jrc-job-remove:hover { background: #f6e7e7; color: #8a1f1f; }
		.jrc-job-remove:focus-visible { outline: 2px solid #2271b1; outline-offset: 1px; }

		.jrc-job-reportcell { display: flex; flex-direction: column; gap: 5px; font-size: 12px; }
		.jrc-job-reportcell input[type="file"] { font-size: 12px; max-width: 100%; }
		.jrc-job-report.no-report { color: #8c8f94; }
		.jrc-job-report.has-report { color: #1d2327; }
		.jrc-job-report.has-report a { font-weight: 600; }
		.jrc-job-report label { color: #b32d2e; white-space: nowrap; }

		.jrc-jobs__foot { display: flex; align-items: center; gap: 14px; flex-wrap: wrap; }
		.jrc-jobs__total { color: #50575e; font-size: 13px; }
		.jrc-jobs__total strong { color: #1d2327; font-size: 14px; }

		/* The admin content area is a good deal narrower than the viewport once the
		   sidebar is out, so this fires earlier than it looks like it should. */
		@media screen and (max-width: 1400px) {
			.jrc-job-row {
				grid-template-columns: 160px 190px minmax(0, 1fr);
				grid-template-areas:
					"date  sched  type"
					"addr  addr   status"
					"scope scope  value"
					"report report report";
			}
		}
		@media screen and (max-width: 900px) {
			.jrc-job-row {
				grid-template-columns: 1fr 1fr;
				grid-template-areas:
					"date   sched"
					"type   status"
					"addr   addr"
					"value  value"
					"scope  scope"
					"report report";
			}
		}
	</style>

	<script>
	( function () {
		var list    = document.getElementById( 'jrc-jobs-list' );
		var add     = document.getElementById( 'jrc-jobs-add' );
		var tpl     = document.getElementById( 'jrc-job-tpl' );
		var totalEl = document.getElementById( 'jrc-jobs-total' );
		if ( ! list || ! add || ! tpl ) { return; }

		// Local (not UTC) YYYY-MM-DD, so "today" is correct near midnight in the UK.
		function today() {
			var d = new Date();
			var m = String( d.getMonth() + 1 ).padStart( 2, '0' );
			var day = String( d.getDate() ).padStart( 2, '0' );
			return d.getFullYear() + '-' + m + '-' + day;
		}

		// Sum the value of every row marked Completed.
		function recalc() {
			if ( ! totalEl ) { return; }
			var total = 0;
			list.querySelectorAll( '.jrc-job-row' ).forEach( function ( row ) {
				var status = row.querySelector( 'select[name="jrc_aff_jobs[status][]"]' );
				if ( ! status || status.value !== 'Completed' ) { return; }
				var val = row.querySelector( 'input[name="jrc_aff_jobs[value][]"]' );
				var n = parseFloat( ( ( val && val.value ) || '' ).replace( /[^0-9.]/g, '' ) );
				if ( ! isNaN( n ) ) { total += n; }
			} );
			totalEl.textContent = '£' + total.toLocaleString( 'en-GB', { maximumFractionDigits: 2 } );
		}

		add.addEventListener( 'click', function () {
			var frag = tpl.content.cloneNode( true );
			var dateInput = frag.querySelector( 'input[type="date"]' );
			if ( dateInput ) { dateInput.value = today(); }
			list.appendChild( frag );
			recalc();
		} );

		list.addEventListener( 'click', function ( e ) {
			var btn = e.target.closest( '.jrc-job-remove' );
			if ( ! btn ) { return; }
			var row  = btn.closest( '.jrc-job-row' );
			var rows = list.querySelectorAll( '.jrc-job-row' );
			// Keep at least one row on screen — clear it rather than leaving an empty list.
			if ( rows.length > 1 ) {
				row.remove();
			} else {
				// Textarea too: a scope left behind made the "cleared" row non-blank, so
				// it was saved instead of removed. The hidden id stays, so the save knows
				// which job was cleared.
				row.querySelectorAll( 'input:not([type="hidden"]), select, textarea' ).forEach( function ( f ) { f.value = ''; } );
			}
			recalc();
		} );

		list.addEventListener( 'input', recalc );
		list.addEventListener( 'change', recalc );
		recalc();
	} )();

	// Referral-code generator: "JRC-" + surname, or a random token if there's no name.
	( function () {
		var btn   = document.getElementById( 'jrc-code-gen' );
		var input = document.getElementById( 'jrc_affiliate_code' );
		if ( ! btn || ! input ) { return; }
		var taken = [];
		try { taken = JSON.parse( btn.getAttribute( 'data-existing' ) || '[]' ); } catch ( e ) {}
		taken = taken.map( function ( c ) { return String( c ).toUpperCase(); } );

		btn.addEventListener( 'click', function () {
			var prefix = btn.getAttribute( 'data-prefix' ) || 'JRC-';
			var seed   = ( btn.getAttribute( 'data-seed' ) || '' ).toUpperCase().replace( /[^A-Z0-9]/g, '' );
			if ( ! seed ) {
				var chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
				for ( var i = 0; i < 5; i++ ) { seed += chars.charAt( Math.floor( Math.random() * chars.length ) ); }
			}
			// Append a number if the base code is already taken (JRC-TANNER → JRC-TANNER2…).
			var base = prefix + seed;
			var code = base;
			var n = 1;
			while ( taken.indexOf( code.toUpperCase() ) !== -1 ) { n++; code = base + n; }
			input.value = code;
			input.focus();
		} );
	} )();
	</script>
	<?php
}

/**
 * Selectable job types for the referred-jobs editor.
 * Filter `jrc_affiliate_job_types` to add or reorder options site-wide.
 *
 * @return string[]
 */
function jrc_affiliate_job_types() {
	return apply_filters( 'jrc_affiliate_job_types', array(
		'End of tenancy',
		'Holiday let changeover',
		'Domestic clean',
		'Office clean',
		'Communal area clean',
		'Deep clean',
		'Carpet clean',
		'Oven clean',
	) );
}

/**
 * Build a <select> whose current value is preselected, keeping any legacy value
 * that isn't in the standard list so saved data is never silently dropped.
 *
 * @param string   $name    Field name (e.g. "jrc_aff_jobs[type][]").
 * @param string[] $options Standard option list.
 * @param string   $current Currently stored value.
 * @return string Escaped <select> markup.
 */
function jrc_affiliate_select( $name, $options, $current ) {
	if ( '' !== $current && ! in_array( $current, $options, true ) ) {
		array_unshift( $options, $current );
	}
	$html = '<select name="' . esc_attr( $name ) . '"><option value="">—</option>';
	foreach ( $options as $opt ) {
		$html .= '<option value="' . esc_attr( $opt ) . '" ' . selected( $current, $opt, false ) . '>' . esc_html( $opt ) . '</option>';
	}
	return $html . '</select>';
}

/**
 * Markup for a single referred-job row (shared by existing rows and the JS "add" template).
 *
 * @param array $job Optional job with date/type/value/status.
 * @return string Escaped HTML for one row.
 */
function jrc_affiliate_job_row_html( $job = array() ) {
	$job = wp_parse_args( $job, array( 'id' => 0, 'date' => '', 'scheduled_date' => '', 'type' => '', 'address' => '', 'scope' => '', 'value' => '', 'status' => '', 'report_file' => '', 'report_name' => '' ) );

	$job_id = (int) ( $job['id'] ?? 0 );

	// Existing report, shown so staff can see at a glance which jobs still need one.
	$report = '';
	if ( $job_id && ! empty( $job['report_file'] ) ) {
		$report = '<span class="jrc-job-report has-report">'
			. '<a href="' . esc_url( jrc_report_url( $job_id ) ) . '">' . esc_html( $job['report_name'] ?: 'View report' ) . '</a>'
			. ' <label title="Tick and save to delete this report"><input type="checkbox" name="jrc_aff_report_delete[]" value="' . $job_id . '" /> remove</label>'
			. '</span>';
	} else {
		$report = '<span class="jrc-job-report no-report">No report yet</span>';
	}

	/**
	 * Wrap a field in its own label.
	 *
	 * Every field carries its own caption rather than relying on a header row above the
	 * grid. That is the whole point: the previous layout put seven labels in one element
	 * and nine fields in another, and when the scheduled-slot and report fields were
	 * added to the row nobody added matching columns — so from the second column on,
	 * every field sat under the wrong heading, the scope textarea was squeezed into the
	 * 90px "value" column and the status select into the 28px remove column. A label that
	 * travels with its own input cannot drift out of step with it.
	 */
	$field = function ( $class, $label, $control, $hint = '' ) {
		return '<label class="jrc-job-f jrc-job-f--' . esc_attr( $class ) . '">'
			. '<span class="jrc-job-f__label">' . esc_html( $label )
			. ( $hint ? ' <em>' . esc_html( $hint ) . '</em>' : '' )
			. '</span>'
			. $control
			. '</label>';
	};

	$scheduled_value = $job['scheduled_date'] ? gmdate( 'Y-m-d\TH:i', strtotime( $job['scheduled_date'] ) ) : '';

	return '<div class="jrc-job-row">'

		// The id is what keeps an uploaded report attached to its job across an edit.
		. '<input type="hidden" name="jrc_aff_jobs[id][]" value="' . esc_attr( $job_id ) . '" />'

		. '<button type="button" class="jrc-job-remove" aria-label="Remove this job" title="Remove this job">&times;</button>'

		. $field( 'date', 'Date of clean',
			'<input type="date" name="jrc_aff_jobs[date][]" value="' . esc_attr( $job['date'] ) . '" />' )

		// Scheduled slot drives the booking calendar; blank simply means "not scheduled".
		. $field( 'sched', 'Scheduled slot',
			'<input type="datetime-local" name="jrc_aff_jobs[scheduled][]" value="' . esc_attr( $scheduled_value ) . '" />',
			'calendar' )

		. $field( 'type', 'Service',
			jrc_affiliate_select( 'jrc_aff_jobs[type][]', jrc_affiliate_job_types(), $job['type'] ) )

		. $field( 'status', 'Status',
			jrc_affiliate_select( 'jrc_aff_jobs[status][]', array( 'Pending', 'Booked', 'Completed', 'Cancelled' ), $job['status'] ) )

		. $field( 'address', 'Address',
			'<input type="text" name="jrc_aff_jobs[address][]" value="' . esc_attr( $job['address'] ) . '" placeholder="e.g. 14 Bridge Street, Evesham" />' )

		. $field( 'value', 'Value',
			'<input type="text" name="jrc_aff_jobs[value][]" value="' . esc_attr( $job['value'] ) . '" placeholder="£140" />',
			'internal' )

		// Scope runs to a couple of sentences, so a textarea rather than a single line.
		. $field( 'scope', 'Scope',
			'<textarea name="jrc_aff_jobs[scope][]" rows="2" placeholder="A couple of sentences describing what the clean covered.">' . esc_textarea( $job['scope'] ) . '</textarea>',
			'shown to partner' )

		. '<div class="jrc-job-f jrc-job-f--report">'
			. '<span class="jrc-job-f__label">Photo report</span>'
			. '<div class="jrc-job-reportcell">'
				. '<input type="file" name="jrc_aff_report[]" accept="application/pdf" title="Attach the PDF report for this clean" />'
				. $report
			. '</div>'
		. '</div>'

		. '</div>';
}
/**
 * The profile form posts urlencoded by default, so a file input on it silently sends
 * nothing. Reports are uploaded from the job rows, hence this.
 */
add_action( 'user_edit_form_tag', function () {
	echo 'enctype="multipart/form-data"';
} );

add_action( 'show_user_profile', 'jrc_affiliate_profile_fields' );
add_action( 'edit_user_profile', 'jrc_affiliate_profile_fields' );

function jrc_affiliate_save_profile( $user_id ) {
	// The meta cap, not the primitive `edit_users`: this asks "may I edit THIS user",
	// which is the question that actually matters here. map_meta_cap() resolves it
	// against the target's role, so an editor cannot reach an administrator's profile
	// and a multisite/custom-role setup cannot widen it by accident. On single-site
	// with default roles the two are equivalent; this one stays correct when they
	// stop being equivalent.
	if ( ! current_user_can( 'edit_user', $user_id ) ) { return; }

	// The partner fields are only rendered for users who can manage other users (see
	// jrc_affiliate_profile_fields()). `edit_user` alone is not enough: every user may
	// edit THEMSELVES, and this hook also fires on a user's own profile save — so without
	// these two checks anyone who could reach their own profile screen could post
	// jrc_is_affiliate=1 and a tier, and a staff member without edit_users who simply
	// saved their own profile would wipe their partner settings and manual jobs, because
	// the fields they never saw would arrive as blanks.
	if ( ! current_user_can( 'edit_users' ) ) { return; }
	if ( ! isset( $_POST['jrc_aff_profile_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jrc_aff_profile_nonce'] ) ), 'jrc_aff_profile_' . $user_id ) ) {
		return;
	}

	update_user_meta( $user_id, 'jrc_is_affiliate', isset( $_POST['jrc_is_affiliate'] ) ? '1' : '' );
	update_user_meta( $user_id, 'jrc_affiliate_tier', max( 1, min( 3, (int) ( $_POST['jrc_affiliate_tier'] ?? 1 ) ) ) );
	update_user_meta( $user_id, 'jrc_affiliate_code', sanitize_text_field( wp_unslash( $_POST['jrc_affiliate_code'] ?? '' ) ) );
	update_user_meta( $user_id, 'jrc_affiliate_channel', sanitize_title( wp_unslash( $_POST['jrc_affiliate_channel'] ?? '' ) ) );

	// Sub-type is validated against the channel that was just saved, so a stale value
	// from a previous channel cannot stick.
	$channel_now = jrc_affiliate_channel( $user_id );
	$subtype_in  = sanitize_key( wp_unslash( $_POST['jrc_affiliate_subtype'] ?? '' ) );
	$valid_subs  = jrc_affiliate_subtypes( $channel_now );
	update_user_meta( $user_id, 'jrc_affiliate_subtype', isset( $valid_subs[ $subtype_in ] ) ? $subtype_in : jrc_affiliate_default_subtype( $channel_now ) );

	// Holiday-let package (shown to Single Owner accounts).
	update_user_meta( $user_id, 'jrc_affiliate_package_name', sanitize_text_field( wp_unslash( $_POST['jrc_affiliate_package_name'] ?? '' ) ) );
	update_user_meta( $user_id, 'jrc_affiliate_package_frequency', sanitize_text_field( wp_unslash( $_POST['jrc_affiliate_package_frequency'] ?? '' ) ) );
	update_user_meta( $user_id, 'jrc_affiliate_package_items', sanitize_textarea_field( wp_unslash( $_POST['jrc_affiliate_package_items'] ?? '' ) ) );

	// Chosen free bonus (Estate Agents only; ignored for anyone not eligible).
	$bonus_in = sanitize_key( wp_unslash( $_POST['jrc_affiliate_free_bonus'] ?? '' ) );
	update_user_meta( $user_id, 'jrc_affiliate_free_bonus', array_key_exists( $bonus_in, jrc_free_bonus_options() ) ? $bonus_in : '' );

	$jobs = array();
	$in   = isset( $_POST['jrc_aff_jobs'] ) && is_array( $_POST['jrc_aff_jobs'] )
		? wp_unslash( $_POST['jrc_aff_jobs'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each field sanitised below.
		: array();
	$dates  = isset( $in['date'] )   ? (array) $in['date']   : array();
	$types  = isset( $in['type'] )   ? (array) $in['type']   : array();
	$addrs  = isset( $in['address'] ) ? (array) $in['address'] : array();
	$scopes = isset( $in['scope'] )  ? (array) $in['scope']  : array();
	$values = isset( $in['value'] )  ? (array) $in['value']  : array();
	$stats  = isset( $in['status'] ) ? (array) $in['status'] : array();
	$scheds = isset( $in['scheduled'] ) ? (array) $in['scheduled'] : array();
	$rowids = isset( $in['id'] ) ? (array) $in['id'] : array();

	$count = max( count( $dates ), count( $types ), count( $addrs ), count( $scopes ), count( $values ), count( $stats ) );
	for ( $i = 0; $i < $count; $i++ ) {
		$row = array(
			'date'   => sanitize_text_field( $dates[ $i ] ?? '' ),
			'type'    => sanitize_text_field( $types[ $i ] ?? '' ),
			'address' => sanitize_text_field( $addrs[ $i ] ?? '' ),
			// Multi-line, so keep newlines rather than flattening with sanitize_text_field.
			'scope'   => sanitize_textarea_field( $scopes[ $i ] ?? '' ),
			'value'  => sanitize_text_field( $values[ $i ] ?? '' ),
			'status' => sanitize_text_field( $stats[ $i ] ?? '' ),
			'scheduled_date' => sanitize_text_field( $scheds[ $i ] ?? '' ),
			'id'             => (int) ( $rowids[ $i ] ?? 0 ),
		);
		// Skip fully-blank rows (how a row is "removed").
		if ( '' === $row['date'] && '' === $row['type'] && '' === $row['address'] && '' === $row['scope'] && '' === $row['value'] && '' === $row['status'] ) {
			continue;
		}
		// Preserve the submitted order: uploaded files are matched to jobs by index.
		$jobs[ $i ] = $row;
	}
	// Absent field = a form from before this guard existed; fall back to the old scope.
	$shown = isset( $_POST['jrc_aff_jobs_shown'] )
		? array_filter( array_map( 'intval', explode( ',', sanitize_text_field( wp_unslash( $_POST['jrc_aff_jobs_shown'] ) ) ) ) )
		: null;
	$sync  = jrc_jobs_sync_for_user( $user_id, $jobs, $shown );

	// Deletions first, so "remove" plus a replacement upload in one save ends up with
	// the new file rather than deleting it again.
	if ( ! empty( $_POST['jrc_aff_report_delete'] ) ) {
		foreach ( (array) wp_unslash( $_POST['jrc_aff_report_delete'] ) as $del_id ) {
			$del_job = jrc_job_get( (int) $del_id );
			if ( $del_job && (int) $del_job->user_id === (int) $user_id ) {
				jrc_job_report_delete( (int) $del_id );
			}
		}
	}

	// Attach uploads. $_FILES for name="jrc_aff_report[]" is index-aligned with the
	// job rows, and $sync['ids'] maps that index to the job it became.
	if ( ! empty( $_FILES['jrc_aff_report']['name'] ) && is_array( $_FILES['jrc_aff_report']['name'] ) ) {
		foreach ( $_FILES['jrc_aff_report']['name'] as $i => $name ) {
			if ( '' === $name || ! isset( $sync['ids'][ $i ] ) ) {
				continue;
			}
			$one = array(
				'name'     => $name,
				'type'     => $_FILES['jrc_aff_report']['type'][ $i ] ?? '',
				'tmp_name' => $_FILES['jrc_aff_report']['tmp_name'][ $i ] ?? '',
				'error'    => $_FILES['jrc_aff_report']['error'][ $i ] ?? UPLOAD_ERR_NO_FILE,
				'size'     => $_FILES['jrc_aff_report']['size'][ $i ] ?? 0,
			);
			$res = jrc_job_report_attach( $sync['ids'][ $i ], $one );
			if ( is_wp_error( $res ) ) {
				// Surfaced on the next profile load rather than lost silently.
				set_transient( 'jrc_report_err_' . $user_id, $res->get_error_message(), 60 );
			}
		}
	}
}
add_action( 'personal_options_update', 'jrc_affiliate_save_profile' );
add_action( 'edit_user_profile_update', 'jrc_affiliate_save_profile' );
