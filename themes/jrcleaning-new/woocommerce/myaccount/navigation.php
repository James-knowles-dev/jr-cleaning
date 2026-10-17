<?php
/**
 * My Account navigation — theme override.
 *
 * Overridden for one reason: the partner tabs added by the jrc-partners plugin append an
 * unread-count badge to the "Reports" label, and core runs every label through
 * esc_html(), which rendered the badge markup as literal text in the nav.
 *
 * wp_kses() with a one-tag allowlist keeps that escaping honest — a label is still
 * unable to introduce a link, a script or an attribute of its own — while letting the
 * badge through. Everything else matches core.
 *
 * @see     https://woocommerce.com/document/template-structure/
 * @package jrcleaning
 * @version 9.3.0 Based on WooCommerce templates/myaccount/navigation.php.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

do_action( 'woocommerce_before_account_navigation' );

/** Tags a menu label may carry: the count badge, and nothing else. */
$jrc_label_tags = array(
	'span' => array( 'class' => array() ),
);
?>

<nav class="woocommerce-MyAccount-navigation" aria-label="<?php esc_attr_e( 'Account pages', 'woocommerce' ); ?>">
	<ul>
		<?php foreach ( wc_get_account_menu_items() as $endpoint => $label ) : ?>
			<li class="<?php echo esc_attr( wc_get_account_menu_item_classes( $endpoint ) ); ?>">
				<a href="<?php echo esc_url( wc_get_account_endpoint_url( $endpoint ) ); ?>" <?php echo wc_is_current_account_menu_item( $endpoint ) ? 'aria-current="page"' : ''; ?>>
					<?php echo wp_kses( $label, $jrc_label_tags ); ?>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</nav>

<?php do_action( 'woocommerce_after_account_navigation' ); ?>
