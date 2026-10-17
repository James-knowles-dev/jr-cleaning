<?php
/**
 * Affiliate dashboard (rendered inside WooCommerce My Account → Affiliate).
 * Expects $aff from jrc_affiliate_data().
 *
 * @package jrc-partners
 */
if ( ! defined( 'ABSPATH' ) || empty( $aff ) ) { return; }

// Channel, sub-type and every behavioural difference come from one resolved profile,
// so this template never re-derives who the account is or what it is entitled to.
$profile       = ! empty( $aff['profile'] ) ? $aff['profile'] : jrc_affiliate_profile();
$channel_label = $profile['channel_label'] ?? '';
$subtype_label = $profile['label'] ?? '';
$has_reward    = $aff['affiliate_discount'] && '-' !== $aff['affiliate_discount'] && '—' !== $aff['affiliate_discount'];

/**
 * The Estate Agent Bonus Program pays no cash commission: `affiliate_discount` is a
 * discount the agent gets on cleans at their OWN home, and `customer_discount` is the
 * discount for the branch and the customers it refers.
 *
 * So there is deliberately no money owed anywhere on this dashboard. An earlier
 * version multiplied referred job value by the rate and showed "£212.25", which
 * read as a payment due. Left available behind a filter in case the programme ever
 * gains a cash element.
 */
$show_money = jrc_affiliate_show_money();

// Which sections this account type sees — declared per view in jrc_affiliate_view_sections().
$sections = jrc_affiliate_view_sections( $profile['view'] ?? 'full' );
$tiered   = ! empty( $profile['tiered'] );
$payout   = $profile['payout'] ?? 'discount';
$show     = function ( $key ) use ( $sections, $tiered ) {
	if ( in_array( $key, array( 'expiry', 'progress' ), true ) && ! $tiered ) {
		return false;
	}
	return in_array( $key, $sections, true );
};
$is_ea    = 'estate-agents' === ( $profile['channel'] ?? '' );
// Second rate's label: an own-home discount for most accounts, cash for commission ones.
$aff_rate_label = 'commission' === $payout ? 'Commission on completed work' : ( $is_ea ? 'Your own home' : 'Your own property' );
$cust_rate_label = $is_ea ? 'Branch &amp; referrals' : ( 'discount' === ( $profile['view'] ?? '' ) ? 'Your discount on all work' : 'Your bookings &amp; referrals' );
?>
<div class="aff-dash aff-dash--<?php echo esc_attr( $profile['view'] ?? 'full' ); ?>">

	<?php if ( $show( 'buildings' ) && function_exists( 'jrc_school_site_data' ) ) :
		// School accounts: each building and its next clean, linking to the Site map.
		$school = jrc_school_site_data();
		?>
		<div class="aff-card aff-card--buildings">
			<div class="bld__head">
				<span class="aff-card__eyebrow">Your buildings</span>
				<?php if ( $school['buildings'] ) : ?>
					<a class="bld__all" href="<?php echo esc_url( jrc_affiliate_tab_url( 'site-map' ) ); ?>">Open the site map →</a>
				<?php endif; ?>
			</div>
			<?php if ( $school['buildings'] ) : ?>
				<ul class="bld">
					<?php foreach ( $school['buildings'] as $bi => $b ) : ?>
						<li>
							<a class="bld__tile" href="<?php echo esc_url( jrc_affiliate_tab_url( 'site-map' ) . '#sm-b-' . $b['id'] ); ?>">
								<span class="bld__pin" aria-hidden="true"><?php echo (int) $bi + 1; ?></span>
								<span class="bld__name"><?php echo esc_html( $b['name'] ); ?></span>
								<span class="bld__freq"><?php echo esc_html( $b['lines'] ? implode( ' · ', $b['lines'] ) : 'No regular pattern yet' ); ?></span>
								<span class="bld__next">
									<?php echo $b['next'] ? esc_html( 'Next: ' . $b['next'][0]['date'] . ', ' . strtok( $b['next'][0]['time'], '–' ) ) : 'No cleans booked'; ?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php else : ?>
				<p class="aff-card__note">We're setting up your buildings — they'll appear here and on your site map shortly.</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $show( 'package' ) ) :
		$package = jrc_affiliate_package();
		?>
		<div class="aff-card aff-card--package">
			<span class="aff-card__eyebrow">Your package</span>
			<?php if ( $package['name'] || $package['lines'] ) : ?>
				<div class="aff-tier">
					<span class="aff-tier__name"><?php echo esc_html( $package['name'] ?: 'Holiday let cleaning' ); ?></span>
				</div>
				<?php if ( $package['frequency'] ) : ?>
					<span class="aff-tier__since"><?php echo esc_html( $package['frequency'] ); ?></span>
				<?php endif; ?>
				<?php if ( $package['lines'] ) : ?>
					<ul class="aff-package__list">
						<?php foreach ( $package['lines'] as $line ) : ?>
							<li><?php echo esc_html( $line ); ?></li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			<?php else : ?>
				<p class="aff-card__note">We're setting up the details of your package — they'll appear here shortly.</p>
			<?php endif; ?>
			<?php if ( $channel_label ) : ?><span class="aff-card__channel"><?php echo esc_html( $channel_label ); ?><?php echo $subtype_label ? ' · ' . esc_html( $subtype_label ) : ''; ?></span><?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $show( 'property' ) ) :
		// The cleans carried out at the property, most recent first. The full history
		// lives on the Cleans tab; this is the at-a-glance version.
		$recent    = array_slice( $aff['jobs'], 0, 5 );
		// The job-history tab is hidden for some account types (Commercial, School);
		// send those to their calendar instead of a "not part of your account" page.
		$jobs_url  = isset( jrc_affiliate_tabs_for_user()['referred-jobs'] ) ? jrc_affiliate_tab_url( 'referred-jobs' ) : jrc_affiliate_tab_url( 'cleaning-calendar' );
		$upcoming  = 0;
		$completed = 0;
		foreach ( $aff['jobs'] as $j ) {
			$st = strtolower( trim( (string) ( $j['status'] ?? '' ) ) );
			if ( 'completed' === $st ) {
				$completed++;
			} elseif ( in_array( $st, array( 'booked', 'pending', 'scheduled' ), true ) ) {
				$upcoming++;
			}
		}
		?>
		<div class="aff-stats">
			<div class="aff-stat">
				<div class="aff-stat__n"><?php echo esc_html( $completed ); ?></div>
				<div class="aff-stat__l">Cleans completed</div>
			</div>
			<div class="aff-stat">
				<div class="aff-stat__n"><?php echo esc_html( $upcoming ); ?></div>
				<div class="aff-stat__l">Booked or pending</div>
			</div>
			<div class="aff-stat">
				<div class="aff-stat__n"><?php echo esc_html( $aff['last12'] ); ?></div>
				<div class="aff-stat__l">In the last 12 months</div>
			</div>
		</div>

		<div class="aff-card aff-card--property">
			<span class="aff-card__eyebrow"><?php echo in_array( $profile['view'] ?? '', array( 'commercial', 'school' ), true ) ? 'Recent cleans' : 'Recent cleans at your property'; ?></span>
			<?php if ( $recent ) : ?>
				<ul class="aff-property__list">
					<?php foreach ( $recent as $j ) :
						$d      = ! empty( $j['date'] ) ? date_i18n( 'j M Y', strtotime( $j['date'] ) ) : '—';
						$status = trim( (string) ( $j['status'] ?? '' ) );
						?>
						<li>
							<span class="aff-property__date"><?php echo esc_html( $d ); ?></span>
							<?php
							// Schools: the building says more than the school's own address on every line.
							$where = ( 'school' === ( $profile['view'] ?? '' ) && function_exists( 'jrc_school_job_building_name' ) && ! empty( $j['id'] ) )
								? jrc_school_job_building_name( jrc_job_get( (int) $j['id'] ) )
								: '';
							$where = $where ?: ( $j['address'] ?? '' );
							?>
							<span class="aff-property__type"><?php echo esc_html( $j['type'] ?: 'Clean' ); ?><?php echo $where ? ' — ' . esc_html( $where ) : ''; ?></span>
							<?php if ( $status ) : ?>
								<span class="aff-status aff-status--<?php echo esc_attr( sanitize_html_class( strtolower( $status ) ) ); ?>"><?php echo esc_html( $status ); ?></span>
							<?php endif; ?>
						</li>
					<?php endforeach; ?>
				</ul>
				<?php if ( count( $aff['jobs'] ) > count( $recent ) && $jobs_url ) : ?>
					<p class="aff-card__note"><a href="<?php echo esc_url( $jobs_url ); ?>"><?php echo isset( jrc_affiliate_tabs_for_user()['referred-jobs'] ) ? esc_html( 'See all ' . count( $aff['jobs'] ) . ' cleans' ) : 'See them all on your calendar'; ?> →</a></p>
				<?php endif; ?>
			<?php else : ?>
				<p class="aff-card__note">No cleans logged yet. Once we've been out, each visit appears here with its report.</p>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $show( 'rates' ) || $show( 'code' ) ) : ?>
	<div class="aff-dash__top">
		<?php if ( $show( 'rates' ) ) : ?>
		<div class="aff-card aff-card--tier">
			<?php if ( $tiered ) : ?>
				<span class="aff-card__eyebrow">Your current tier</span>
				<div class="aff-tier">
					<span class="aff-tier__name"><?php echo esc_html( $aff['tier_label'] ); ?></span>
					<?php if ( 3 === $aff['tier'] ) : ?><span class="aff-tier__badge">Top tier</span><?php endif; ?>
				</div>
				<?php
				// Answers "since when?", which the tier on its own never could — and gives
				// anyone querying their rate something to point at.
				$tier_since = jrc_affiliate_tier_since();
				if ( $tier_since ) :
					?>
					<span class="aff-tier__since">Since <?php echo esc_html( date_i18n( 'F Y', strtotime( $tier_since ) ) ); ?></span>
				<?php endif; ?>
			<?php else : ?>
				<span class="aff-card__eyebrow"><?php echo 'commission' === $payout ? 'Your commission' : 'Your rates'; ?></span>
				<div class="aff-tier">
					<span class="aff-tier__name"><?php echo esc_html( $subtype_label ? $subtype_label . ' account' : 'Partner account' ); ?></span>
				</div>
			<?php endif; ?>
			<div class="aff-tier__discounts">
				<div class="aff-tier__row"><span><?php echo $cust_rate_label; // phpcs:ignore WordPress.Security.EscapeOutput -- static string with entity. ?></span><strong><?php echo esc_html( $aff['customer_discount'] ?: '—' ); ?></strong></div>
				<?php if ( 'none' !== $payout ) : ?>
					<div class="aff-tier__row"><span><?php echo esc_html( $aff_rate_label ); ?></span><strong class="is-accent"><?php echo esc_html( $has_reward ? $aff['affiliate_discount'] : ( $tiered ? 'Not at this tier' : '—' ) ); ?></strong></div>
				<?php endif; ?>
			</div>
		</div>
		<?php endif; ?>

		<?php if ( $show( 'code' ) ) : ?>
		<div class="aff-card aff-card--code">
			<span class="aff-card__eyebrow">Your referral code</span>
			<?php if ( $aff['code'] ) : ?>
				<?php
				// A shareable link is more use than a bare code: the client does not have
				// to remember to quote it. `?ref=` is picked up by app/js/affiliate.js.
				$ref_link = add_query_arg( 'ref', $aff['code'], home_url( '/quote/' ) );
				$ref_msg  = sprintf(
					'I can recommend J R Cleaning for this. Use my referral code %1$s and you will get a discount on the clean: %2$s',
					$aff['code'],
					$ref_link
				);
				?>
				<div class="aff-code">
					<code id="aff-code"><?php echo esc_html( $aff['code'] ); ?></code>
					<button type="button" class="aff-code__copy" data-copy="<?php echo esc_attr( $aff['code'] ); ?>">Copy</button>
				</div>
				<p class="aff-card__note">Clients who quote this code are credited to you.</p>
			<?php else : ?>
				<p class="aff-card__note">No code assigned yet — we'll be in touch to set this up.</p>
			<?php endif; ?>
			<?php if ( $channel_label ) : ?><span class="aff-card__channel"><?php echo esc_html( $channel_label ); ?><?php echo $subtype_label ? ' · ' . esc_html( $subtype_label ) : ''; ?></span><?php endif; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php
	// Sharing tools sit on their own row rather than inside the code card: they are
	// taller than the tier card's content, so nesting them stretched the top grid and
	// left a large void beside them.
	if ( $show( 'share' ) && $aff['code'] ) :
		?>
		<div class="aff-card aff-card--share">
			<span class="aff-card__eyebrow">Share your referral</span>
			<p class="aff-share__intro">Send the link and the code is applied for them automatically, so there is nothing for your client to remember.</p>

			<div class="aff-share__grid">
				<div class="aff-share__field">
					<label class="aff-share__label" for="aff-link">Your referral link</label>
					<div class="aff-share__row">
						<input type="text" id="aff-link" class="aff-share__input" value="<?php echo esc_url( $ref_link ); ?>" readonly onclick="this.select()" />
						<button type="button" class="aff-code__copy aff-code__copy--sm" data-copy="<?php echo esc_attr( $ref_link ); ?>">Copy</button>
					</div>
				</div>

				<div class="aff-share__field">
					<label class="aff-share__label" for="aff-msg">A message you can send as-is</label>
					<div class="aff-share__row">
						<textarea id="aff-msg" class="aff-share__input aff-share__input--area" rows="4" readonly onclick="this.select()"><?php echo esc_textarea( $ref_msg ); ?></textarea>
						<button type="button" class="aff-code__copy aff-code__copy--sm" data-copy="<?php echo esc_attr( $ref_msg ); ?>">Copy</button>
					</div>
				</div>
			</div>
		</div>
	<?php endif; ?>

	<?php
	// Three equal counters on one row. "Clients have saved" used to sit alone in a
	// full-width band, which left a lot of empty card for one number.
	?>
	<?php if ( $show( 'stats' ) || $show( 'earnings' ) || $show( 'savings' ) ) : ?>
	<div class="aff-stats">
		<div class="aff-stat">
			<div class="aff-stat__n"><?php echo esc_html( $aff['total'] ); ?></div>
			<div class="aff-stat__l">Jobs referred (all time)</div>
		</div>
		<div class="aff-stat">
			<div class="aff-stat__n"><?php echo esc_html( $aff['last12'] ); ?></div>
			<div class="aff-stat__l">In the last 12 months</div>
		</div>
		<div class="aff-stat">
			<?php if ( $show( 'earnings' ) ) : ?>
				<div class="aff-stat__n"><?php echo esc_html( jrc_affiliate_money( $aff['earned'] ) ); ?></div>
				<div class="aff-stat__l">Commission earned, <?php echo esc_html( $aff['affiliate_discount'] && '-' !== $aff['affiliate_discount'] ? $aff['affiliate_discount'] : '—' ); ?> of <?php echo esc_html( jrc_affiliate_money( $aff['completed_value'] ) ); ?> of completed work</div>
			<?php elseif ( $show( 'savings' ) ) : ?>
				<div class="aff-stat__n"><?php echo esc_html( jrc_affiliate_money( $aff['saved'] ) ); ?></div>
				<div class="aff-stat__l">Saved so far, <?php echo esc_html( $aff['customer_discount'] ?: '—' ); ?> off <?php echo esc_html( jrc_affiliate_money( $aff['completed_value'] ) ); ?> of completed work</div>
			<?php elseif ( $show_money ) : ?>
				<div class="aff-stat__n"><?php echo esc_html( jrc_affiliate_money( $aff['saved'] ) ); ?></div>
				<div class="aff-stat__l">Your clients have saved, <?php echo esc_html( $aff['customer_discount'] ?: '—' ); ?> off <?php echo esc_html( jrc_affiliate_money( $aff['completed_value'] ) ); ?> of completed cleans</div>
			<?php else : ?>
				<div class="aff-stat__n"><?php echo esc_html( $aff['customer_discount'] ?: '—' ); ?></div>
				<div class="aff-stat__l"><?php echo $is_ea ? 'Discount for the branch and referred customers' : 'Discount on the work you book and refer'; ?></div>
			<?php endif; ?>
		</div>
	</div>
	<?php endif; ?>

	<?php if ( $show( 'earnings' ) ) : ?>
		<div class="aff-card aff-card--note">
			<p>Commission is worked out on completed work only and paid as agreed with you. Jobs that are booked or pending appear on the Referred jobs tab and count once they are done.</p>
		</div>
	<?php endif; ?>

	<?php
	// Progress gets its own full-width row: a wide bar reads better than a narrow one,
	// and the next-tier list made this taller than the counters beside it.
	?>
	<?php
	// The rolling window used to expire bookings in silence: the first a branch knew
	// about a demotion was a different rate on an invoice. Warn while there is still
	// time to do something about it.
	$expiry = $show( 'expiry' ) ? jrc_affiliate_tier_expiry() : array( 'count' => 0 );
	if ( $expiry['count'] ) :
		?>
		<div class="aff-notice aff-notice--<?php echo $expiry['at_risk'] ? 'warn' : 'info'; ?> aff-notice--expiry" role="status">
			<strong>
				<?php
				printf(
					/* translators: 1: number of bookings, 2: date */
					esc_html( _n(
						'%1$d booking drops out of your twelve-month count on %2$s.',
						'%1$d bookings drop out of your twelve-month count on %2$s.',
						$expiry['count'],
						'jrc-partners'
					) ),
					(int) $expiry['count'],
					esc_html( $expiry['date'] ? date_i18n( 'j F', strtotime( $expiry['date'] ) ) : '' )
				);
				?>
			</strong>
			<?php if ( $expiry['at_risk'] ) : ?>
				That would leave you on <?php echo esc_html( $expiry['after'] ); ?>,
				below the <?php echo esc_html( $expiry['threshold'] ); ?> needed to hold
				<?php echo esc_html( $aff['tier_label'] ); ?>. Another booking before then keeps your rate where it is.
			<?php else : ?>
				You would still be on <?php echo esc_html( $expiry['after'] ); ?>, which holds
				<?php echo esc_html( $aff['tier_label'] ); ?> comfortably.
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php if ( $show( 'progress' ) ) : ?>
	<div class="aff-stat aff-stat--progress">
		<?php if ( $aff['next_tier'] ) : ?>
				<?php // Grouped so the two-column grid gets exactly two children: bar, then payoff. ?>
				<div class="aff-progress__main">
					<div class="aff-progress__top">
						<span><?php echo esc_html( $aff['needed'] ); ?> more to reach <?php echo esc_html( $aff['next_label'] ); ?></span>
						<span class="aff-progress__pct"><?php echo esc_html( $aff['pct'] ); ?>%</span>
					</div>
					<div class="aff-progress__bar"><span style="width:<?php echo esc_attr( $aff['pct'] ); ?>%"></span></div>
					<div class="aff-stat__l">Progress to your next tier (rolling 12 months)</div>
				</div>

				<?php // Without this the bar is just a number — show what reaching it actually changes. ?>
				<?php if ( $aff['next_customer'] || $aff['next_affiliate'] ) : ?>
					<ul class="aff-next">
						<?php if ( $aff['next_customer'] ) : ?>
							<li>Your clients would save <strong><?php echo esc_html( $aff['next_customer'] ); ?></strong>
								<?php if ( $aff['customer_discount'] && $aff['next_customer'] !== $aff['customer_discount'] ) : ?>
									<span class="aff-next__from">up from <?php echo esc_html( $aff['customer_discount'] ); ?></span>
								<?php endif; ?>
							</li>
						<?php endif; ?>
						<?php if ( $aff['next_affiliate'] && '-' !== $aff['next_affiliate'] && '—' !== $aff['next_affiliate'] ) : ?>
							<li>Cleans at your own home would be <strong class="is-accent"><?php echo esc_html( $aff['next_affiliate'] ); ?></strong>
								<?php if ( $has_reward ) : ?>
									<span class="aff-next__from">up from <?php echo esc_html( $aff['affiliate_discount'] ); ?></span>
								<?php else : ?>
									<span class="aff-next__from">not available at your current tier</span>
								<?php endif; ?>
							</li>
						<?php endif; ?>
					</ul>
				<?php endif; ?>
		<?php else : ?>
			<div class="aff-stat__n aff-stat__n--sm">You're at the top tier 🎉</div>
			<div class="aff-stat__l">Keep the volume up to hold these rates</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>

	<?php if ( $show( 'bonus' ) && ! empty( $profile['free_bonus'] ) ) :
		$chosen = jrc_free_bonus_for_user();
		?>
		<div class="aff-card aff-card--bonus">
			<span class="aff-card__eyebrow">Your chosen free bonus</span>
			<p class="aff-bonus__intro">Pick one. We add it free to every full end-of-tenancy clean your branch books, with nothing to claim on the day. You can change it whenever you like.</p>

			<?php if ( ! empty( $_GET['bonus-saved'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<p class="aff-bonus__saved" role="status">Saved. <?php echo esc_html( jrc_free_bonus_label( $chosen ) ); ?> now applies to your end-of-tenancy cleans.</p>
			<?php endif; ?>

			<form method="post" class="aff-bonus__form">
				<?php wp_nonce_field( 'jrc_free_bonus_' . get_current_user_id(), 'jrc_free_bonus_nonce' ); ?>
				<div class="aff-bonus__options">
					<?php foreach ( jrc_free_bonus_options() as $key => $label ) : ?>
						<label class="aff-bonus__option">
							<input type="radio" name="jrc_free_bonus" value="<?php echo esc_attr( $key ); ?>" <?php checked( $chosen, $key ); ?> />
							<span><?php echo esc_html( $label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
				<button type="submit" class="button button--primary aff-bonus__save">Save my choice</button>
			</form>
		</div>
	<?php elseif ( $show( 'bonus' ) && ! empty( $profile['note'] ) ) : ?>
		<div class="aff-card aff-card--note">
			<p><?php echo esc_html( $profile['note'] ); ?></p>
		</div>
	<?php endif; ?>

	<?php
	// December perks only exist from Tier 2, so show them where they apply and tell a
	// Tier 1 branch what reaching Tier 2 unlocks.
	$tier_data = jrc_partner_tiers();
	$my_tier   = $tier_data[ $aff['tier'] - 1 ] ?? array();
	$dec       = $my_tier['december'] ?? array();
	?>
	<?php if ( $show( 'december' ) && $dec && ! empty( $profile['december'] ) ) : ?>
		<div class="aff-card aff-card--december">
			<span class="aff-card__eyebrow">Every December</span>
			<ul class="tier-card__perks tier-card__perks--december">
				<?php foreach ( $dec as $perk ) : ?>
					<li><?php echo esc_html( $perk ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<?php // Answering these here stops them arriving by phone one at a time. ?>
	<?php if ( $show( 'how' ) && ! $is_ea ) : ?>
	<div class="aff-how">
		<h3>How your account works</h3>
		<dl class="aff-how__list">
			<?php if ( $tiered ) : ?>
				<dt>How my tier is worked out</dt>
				<dd>On the bookings you send us over a rolling twelve months, counting work you book directly and successful referrals together. As your volume grows we move you up, and each tier carries a better rate.</dd>
			<?php else : ?>
				<dt>How my rates are set</dt>
				<dd>Your rates are agreed for your account type and do not depend on volume. If your portfolio changes size, get in touch and we will review which account suits you.</dd>
			<?php endif; ?>

			<?php if ( 'commission' === $payout ) : ?>
				<dt>How commission works</dt>
				<dd>You earn the commission shown above on every completed job booked through your account or with your referral code. It is calculated on completed work only.</dd>
			<?php elseif ( 'discount' === ( $profile['view'] ?? '' ) ) : ?>
				<dt>How the discount works</dt>
				<dd>Your account receives discounted pricing on all the work it books, in place of commission. The discount is already applied on your price list.</dd>
			<?php endif; ?>

			<dt>Your price list</dt>
			<dd>Your price list, with your rates already applied, is on the <a href="<?php echo esc_url( jrc_affiliate_tab_url( 'price-list' ) ); ?>">Price list</a> tab. You can download it as a PDF to send on to your own customers.</dd>

			<dt>When a booking counts</dt>
			<dd>Once the clean is complete. A job showing as Pending, Booked or Cancelled does not count yet.</dd>
		</dl>
	</div>
	<?php endif; ?>

	<?php if ( $show( 'how' ) && $is_ea ) : ?>
	<div class="aff-how">
		<h3>How the Bonus Program works</h3>
		<dl class="aff-how__list">
			<dt>How my tier is worked out</dt>
			<dd>On the bookings your branch generates over a rolling twelve months, counting direct work orders and successful client referrals together. Tier 1 also asks that you list us in your contractor directory and move-out documentation.</dd>

			<dt>What the discounts apply to</dt>
			<dd>Three things: services the branch books itself, any customer you refer to us, and from Tier 2 upwards, cleans at your own home. The branch and referral rate is the same figure.</dd>

			<dt>Your agency price list</dt>
			<dd>Your agency's price list, with your rates already applied, is on the <a href="<?php echo esc_url( jrc_affiliate_tab_url( 'price-list' ) ); ?>">Price list</a> tab — download it as a PDF to send on to landlords and tenants.</dd>

			<dt>How a booking gets credited to the branch</dt>
			<dd>Either the branch issues the work order directly, or your customer quotes your referral code, or they arrive through your referral link, which applies the code for them. If someone forgets, tell us and we will credit it.</dd>

			<dt>When a booking counts</dt>
			<dd>Once the clean is complete. A job showing as Pending, Booked or Cancelled does not count towards your tier yet, which is why the totals here can differ from what you have sent us this month.</dd>

			<dt>Do tiers get reset?</dt>
			<dd>No. The twelve months roll, so a quiet month does not cost you your rate, and as your volume grows we move you up.</dd>
		</dl>
	</div>
	<?php endif; ?>

	<?php if ( $show( 'cta' ) ) : ?>
	<div class="aff-cta-strip">
		<div>
			<strong>Got a property that needs doing?</strong>
			<span>Send it over from here and it goes straight into our jobs list.</span>
		</div>
		<a class="button button--primary" href="<?php echo esc_url( jrc_affiliate_tab_url( 'request-clean' ) ); ?>">Request a clean →</a>
	</div>
	<?php endif; ?>

	<p class="aff-dash__foot"><?php echo $tiered ? 'Questions about your tier or your rates?' : ( 'basic' === ( $profile['view'] ?? '' ) ? 'Questions about your package?' : 'Questions about your rates?' ); ?> <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Get in touch</a>.</p>
</div>
