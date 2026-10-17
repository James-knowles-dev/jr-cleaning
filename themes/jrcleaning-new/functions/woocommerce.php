<?php
/**
 * WooCommerce integration.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Wrapper markup to match the theme container.
remove_action( 'woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10 );
remove_action( 'woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10 );

add_action( 'woocommerce_before_main_content', function () {
	echo '<div class="primary-container woo-wrap"><div class="woo-wrap__inner">';
}, 10 );

add_action( 'woocommerce_after_main_content', function () {
	echo '</div></div>';
}, 10 );

// Products per row / page.
add_filter( 'loop_shop_columns', function () { return 3; } );
add_filter( 'loop_shop_per_page', function () { return 12; } );

// NB: there was a `woocommerce_add_to_cart_fragments` filter here returning a
// `span.jrc-cart-count` fragment, but the header renders an account icon and no
// cart counter, so the selector never matched anything. Removed — if a cart
// counter is ever added to header.php, restore the fragment alongside it.
