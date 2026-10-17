<?php
/**
 * My Account dashboard (theme override) — tidied for this site (no shop orders/addresses).
 * Shows an affiliate snapshot rather than links that duplicate the account nav.
 *
 * @package jrcleaning
 */
defined( 'ABSPATH' ) || exit;

$current_user = wp_get_current_user();
$aff          = ( function_exists( 'jrc_is_affiliate' ) && jrc_is_affiliate() ) ? jrc_affiliate_data() : null;
?>
<p class="account-dash__hello">Hello <strong><?php echo esc_html( $current_user->display_name ); ?></strong> — welcome to your account.</p>

<?php if ( $aff ) :
	// What the snapshot shows depends on the account type: a tier and referral code
	// mean nothing to a Commercial client or a single holiday-let owner.
	$profile  = $aff['profile'] ?? array();
	$tiered   = ! empty( $profile['tiered'] );
	$referral = ! in_array( $profile['view'] ?? '', array( 'basic', 'commercial', 'school' ), true );
	$names    = function_exists( 'jrc_affiliate_area_names' ) ? jrc_affiliate_area_names() : array( 'label' => 'affiliate dashboard' );
	?>
	<div class="account-snapshot">
		<ul class="account-snapshot__stats">
			<li>
				<span class="account-snapshot__label"><?php echo $tiered ? 'Your tier' : 'Account type'; ?></span>
				<span class="account-snapshot__val"><?php echo esc_html( $tiered ? $aff['tier_label'] : ( ( $profile['label'] ?? '' ) ?: ( $profile['channel_label'] ?? 'Partner' ) ) ); ?></span>
			</li>
			<?php if ( $referral ) : ?>
			<li>
				<span class="account-snapshot__label">Referral code</span>
				<?php if ( $aff['code'] ) : ?>
					<span class="account-snapshot__val account-snapshot__code">
						<span class="account-snapshot__code-text"><?php echo esc_html( $aff['code'] ); ?></span>
						<button type="button" class="aff-code__copy account-snapshot__copy" data-copy="<?php echo esc_attr( $aff['code'] ); ?>" aria-label="<?php echo esc_attr( 'Copy referral code ' . $aff['code'] ); ?>">Copy</button>
					</span>
				<?php else : ?>
					<span class="account-snapshot__val">—</span>
				<?php endif; ?>
			</li>
			<?php endif; ?>
			<li>
				<span class="account-snapshot__label"><?php echo $referral ? 'Referred (12 mo)' : 'Cleans (12 mo)'; ?></span>
				<span class="account-snapshot__val"><?php echo esc_html( $aff['last12'] ); ?></span>
			</li>
		</ul>
		<a class="button button--primary account-snapshot__cta" href="<?php echo esc_url( wc_get_account_endpoint_url( 'affiliate' ) ); ?>">Open <?php echo esc_html( strtolower( $names['label'] ?? 'your account' ) ); ?> →</a>
	</div>
<?php endif; ?>

<p class="account-dash__help">Need a hand? <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Get in touch with the J R Cleaning team</a>.</p>

<?php
/**
 * Keep the hook so extensions can still add to the dashboard.
 *
 * @since 2.6.0
 */
do_action( 'woocommerce_account_dashboard' );
?>
