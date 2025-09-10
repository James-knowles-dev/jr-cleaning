<?php

// Custom WooCommerce Wrapper
remove_action('woocommerce_before_main_content', 'woocommerce_output_content_wrapper', 10);
remove_action('woocommerce_after_main_content', 'woocommerce_output_content_wrapper_end', 10);

add_action('woocommerce_before_main_content', function() {
    echo '<main class="site-main"><div class="container">';
}, 10);

add_action('woocommerce_after_main_content', function() {
    echo '</div></main>';
}, 10);

//Disable WooCommerce Default Styles
add_filter('woocommerce_enqueue_styles', '__return_empty_array');

//Clean Up Product Page Tabs
add_filter('woocommerce_product_tabs', function($tabs) {
    unset($tabs['reviews']);
    unset($tabs['additional_information']);
    return $tabs;
}, 98);

//Remove Additional Info and SKU
add_filter('wc_product_sku_enabled', '__return_false');
remove_action('woocommerce_single_product_summary', 'woocommerce_template_single_meta', 40);

//Enable Shortcodes in Descriptions
add_filter('woocommerce_short_description', 'do_shortcode');
add_filter('the_content', 'do_shortcode');

//Show Cart on All Pages
add_action('wp_footer', function() {
    if (is_cart() || is_checkout()) return;
    wc_get_template('cart/sidebar-mini-cart.php');
});

//Force AJAX Add to Cart for Archives
add_filter('woocommerce_product_add_to_cart_ajax', '__return_true');

// AJAX handler for cart count
add_action('wp_ajax_get_cart_count', 'get_cart_count_ajax');
add_action('wp_ajax_nopriv_get_cart_count', 'get_cart_count_ajax');

function get_cart_count_ajax() {
    if (class_exists('WooCommerce')) {
        $cart_count = WC()->cart->get_cart_contents_count();
        wp_send_json_success(array('count' => $cart_count));
    } else {
        wp_send_json_error('WooCommerce not available');
    }
}

// AJAX handler for sidebar cart content
add_action('wp_ajax_get_sidebar_cart_content', 'get_sidebar_cart_content_ajax');
add_action('wp_ajax_nopriv_get_sidebar_cart_content', 'get_sidebar_cart_content_ajax');

function get_sidebar_cart_content_ajax() {
    if (!class_exists('WooCommerce')) {
        wp_send_json_error('WooCommerce not available');
        return;
    }
    
    $cart_content = '';
    $cart_footer = '';
    
    // Get cart body content
    ob_start();
    if (!WC()->cart->is_empty()) {
        echo '<ul class="woocommerce-mini-cart cart_list product_list_widget">';
        foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
            $_product   = apply_filters('woocommerce_cart_item_product', $cart_item['data'], $cart_item, $cart_item_key);
            $product_id = apply_filters('woocommerce_cart_item_product_id', $cart_item['product_id'], $cart_item, $cart_item_key);

            if ($_product && $_product->exists() && $cart_item['quantity'] > 0 && apply_filters('woocommerce_widget_cart_item_visible', true, $cart_item, $cart_item_key)) {
                $product_name      = apply_filters('woocommerce_cart_item_name', $_product->get_name(), $cart_item, $cart_item_key);
                $thumbnail         = apply_filters('woocommerce_cart_item_thumbnail', $_product->get_image(), $cart_item, $cart_item_key);
                $product_price     = apply_filters('woocommerce_cart_item_price', WC()->cart->get_product_price($_product), $cart_item, $cart_item_key);
                $product_permalink = apply_filters('woocommerce_cart_item_permalink', $_product->is_visible() ? $_product->get_permalink($cart_item) : '', $cart_item, $cart_item_key);
                ?>
                <li class="woocommerce-mini-cart-item mini_cart_item">
                    <div class="cart-item-image">
                        <?php if (empty($product_permalink)) : ?>
                            <?php echo $thumbnail; ?>
                        <?php else : ?>
                            <a href="<?php echo esc_url($product_permalink); ?>">
                                <?php echo $thumbnail; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                    
                    <div class="cart-item-details">
                        <div class="cart-item-name">
                            <?php if (empty($product_permalink)) : ?>
                                <?php echo wp_kses_post($product_name); ?>
                            <?php else : ?>
                                <a href="<?php echo esc_url($product_permalink); ?>">
                                    <?php echo wp_kses_post($product_name); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                        
                        <?php echo wc_get_formatted_cart_item_data($cart_item); ?>
                        
                        <div class="cart-item-quantity-price">
                            <span class="quantity">
                                <?php echo sprintf('%s × %s', $cart_item['quantity'], $product_price); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="cart-item-remove">
                        <?php
                        echo apply_filters(
                            'woocommerce_cart_item_remove_link',
                            sprintf(
                                '<a href="%s" class="remove remove_from_cart_button" aria-label="%s" data-product_id="%s" data-cart_item_key="%s" data-product_sku="%s">&times;</a>',
                                esc_url(wc_get_cart_remove_url($cart_item_key)),
                                esc_attr(sprintf(__('Remove %s from cart', 'woocommerce'), wp_strip_all_tags($product_name))),
                                esc_attr($product_id),
                                esc_attr($cart_item_key),
                                esc_attr($_product->get_sku())
                            ),
                            $cart_item_key
                        );
                        ?>
                    </div>
                </li>
                <?php
            }
        }
        echo '</ul>';
    } else {
        echo '<div class="cart-empty">';
        echo '<p class="woocommerce-mini-cart__empty-message">' . esc_html__('Your basket is currently empty.', 'woocommerce') . '</p>';
        echo '<a href="' . esc_url(wc_get_page_permalink('shop')) . '" class="button continue-shopping">' . esc_html__('Continue Shopping', 'woocommerce') . '</a>';
        echo '</div>';
    }
    $cart_content = ob_get_clean();
    
    // Get cart footer content
    if (!WC()->cart->is_empty()) {
        ob_start();
        echo '<div class="cart-sidebar-total">';
        do_action('woocommerce_widget_shopping_cart_total');
        echo '</div>';
        echo '<div class="cart-sidebar-buttons">';
        echo '<a href="' . esc_url(wc_get_cart_url()) . '" class="button view-cart">' . esc_html__('View Cart', 'woocommerce') . '</a>';
        echo '<a href="' . esc_url(wc_get_checkout_url()) . '" class="button checkout">' . esc_html__('Checkout', 'woocommerce') . '</a>';
        echo '</div>';
        $cart_footer = ob_get_clean();
    }
    
    wp_send_json_success(array(
        'content' => $cart_content,
        'footer' => $cart_footer
    ));
}

// Add cart fragments for AJAX updates
add_filter('woocommerce_add_to_cart_fragments', 'header_cart_count_fragment');

function header_cart_count_fragment($fragments) {
    $cart_count = WC()->cart->get_cart_contents_count();
    
    ob_start();
    // Always show cart count, even if 0
    echo '<span class="cart-count">' . esc_html($cart_count) . '</span>';
    $fragments['.cart-count'] = ob_get_clean();
    
    ob_start();
    // Always show count in mobile menu
    echo 'Shopping Cart (' . esc_html($cart_count) . ')';
    $fragments['.mobile-woo-cart-icon span'] = ob_get_clean();
    
    return $fragments;
}

//Reload Cart on Quantity Change
add_action('wp_footer', function() {
    if (is_cart()) : ?>
        <script>
            jQuery(document).ready(function($){
                $('div.woocommerce').on('change', '.qty', function(){
                    $('[name="update_cart"]').trigger('click');
                });
            });
        </script>
    <?php endif;
});

//Remove WooCommerce Mini Cart Empty Message
add_action('wp_footer', function() {
    if (is_cart() || is_checkout()) return;
    ?>
    <script>
        jQuery(document).ready(function($){
            // Remove empty message from mini cart
            $('.woocommerce-mini-cart__empty-message').remove();
        });
    </script>
    <?php
});

// Register "Areas" taxonomy for WooCommerce Products
function my_custom_product_areas_taxonomy() {

    $labels = array(
        'name'              => _x( 'Areas', 'taxonomy general name', 'textdomain' ),
        'singular_name'     => _x( 'Area', 'taxonomy singular name', 'textdomain' ),
        'search_items'      => __( 'Search Areas', 'textdomain' ),
        'all_items'         => __( 'All Areas', 'textdomain' ),
        'parent_item'       => __( 'Parent Area', 'textdomain' ),
        'parent_item_colon' => __( 'Parent Area:', 'textdomain' ),
        'edit_item'         => __( 'Edit Area', 'textdomain' ),
        'update_item'       => __( 'Update Area', 'textdomain' ),
        'add_new_item'      => __( 'Add New Area', 'textdomain' ),
        'new_item_name'     => __( 'New Area Name', 'textdomain' ),
        'menu_name'         => __( 'Areas', 'textdomain' ),
    );

    $args = array(
        'hierarchical'      => true, // true = category-like, false = tag-like
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'area' ),
    );

    register_taxonomy( 'product_area', array( 'product' ), $args );

}
add_action( 'init', 'my_custom_product_areas_taxonomy' );

remove_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images', 20 );