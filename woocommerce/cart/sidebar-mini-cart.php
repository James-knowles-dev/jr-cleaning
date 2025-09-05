<?php
/**
 * Sidebar Mini-cart
 *
 * Custom sidebar cart template for the theme
 *
 * @package PixelBase
 */

defined( 'ABSPATH' ) || exit;

do_action( 'woocommerce_before_mini_cart' ); ?>

<div class="cart-sidebar" id="cart-sidebar">
    <div class="cart-sidebar-overlay"></div>
    <div class="cart-sidebar-content">
        <div class="cart-sidebar-header">
            <h3>Your Basket</h3>
            <button class="cart-sidebar-close" aria-label="Close cart">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>

        <div class="cart-sidebar-body">
            <?php if ( ! WC()->cart->is_empty() ) : ?>
                <ul class="woocommerce-mini-cart cart_list product_list_widget">
                    <?php
                    do_action( 'woocommerce_before_mini_cart_contents' );

                    foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
                        $_product   = apply_filters( 'woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key );
                        $product_id = apply_filters( 'woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key );

                        if ( $_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters( 'woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key ) ) {
                            $product_name      = apply_filters( 'woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key );
                            $thumbnail         = apply_filters( 'woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key );
                            $product_price     = apply_filters( 'woocommerce_cart_item_price', WC()->cart->get_product_price( $_product ), $cart_item, $cart_item_key );
                            $product_permalink = apply_filters( 'woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink( $cart_item ) : '', $cart_item, $cart_item_key );
                            ?>
                            <li class="woocommerce-mini-cart-item mini_cart_item">
                                <div class="cart-item-image">
                                    <?php if ( empty( $product_permalink ) ) : ?>
                                        <?php echo $thumbnail; ?>
                                    <?php else : ?>
                                        <a href="<?php echo esc_url( $product_permalink ); ?>">
                                            <?php echo $thumbnail; ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="cart-item-details">
                                    <div class="cart-item-name">
                                        <?php if ( empty( $product_permalink ) ) : ?>
                                            <?php echo wp_kses_post( $product_name ); ?>
                                        <?php else : ?>
                                            <a href="<?php echo esc_url( $product_permalink ); ?>">
                                                <?php echo wp_kses_post( $product_name ); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php echo wc_get_formatted_cart_item_data( $cart_item ); ?>
                                    
                                    <div class="cart-item-quantity-price">
                                        <span class="quantity">
                                            <?php echo sprintf( '%s × %s', $cart_item['quantity'], $product_price ); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="cart-item-remove">
                                    <?php
                                    echo apply_filters(
                                        'woocommerce_cart_item_remove_link',
                                        sprintf(
                                            '<a href="%s" class="remove remove_from_cart_button" aria-label="%s" data-product_id="%s" data-cart_item_key="%s" data-product_sku="%s">&times;</a>',
                                            esc_url( wc_get_cart_remove_url( $cart_item_key ) ),
                                            esc_attr( sprintf( __( 'Remove %s from cart', 'woocommerce' ), wp_strip_all_tags( $product_name ) ) ),
                                            esc_attr( $product_id ),
                                            esc_attr( $cart_item_key ),
                                            esc_attr( $_product->get_sku() )
                                        ),
                                        $cart_item_key
                                    );
                                    ?>
                                </div>
                            </li>
                            <?php
                        }
                    }

                    do_action( 'woocommerce_mini_cart_contents' );
                    ?>
                </ul>
            <?php else : ?>
                <div class="cart-empty">
                    <p class="woocommerce-mini-cart__empty-message"><?php esc_html_e( 'Your basket is currently empty.', 'woocommerce' ); ?></p>
                    <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="button continue-shopping">
                        <?php esc_html_e( 'Continue Shopping', 'woocommerce' ); ?>
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <?php if ( ! WC()->cart->is_empty() ) : ?>
        <div class="cart-sidebar-footer">
            <div class="cart-sidebar-total">
                <?php do_action( 'woocommerce_widget_shopping_cart_total' ); ?>
            </div>

            <div class="cart-sidebar-buttons">
                <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="button view-cart">
                    <?php esc_html_e( 'View Cart', 'woocommerce' ); ?>
                </a>
                <a href="<?php echo esc_url( wc_get_checkout_url() ); ?>" class="button checkout">
                    <?php esc_html_e( 'Checkout', 'woocommerce' ); ?>
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php do_action( 'woocommerce_after_mini_cart' ); ?>
