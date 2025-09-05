<?php 
// Load CSS.
function load_css() {
  // Third Party CSS Libraries
  wp_enqueue_style("slick",get_template_directory_uri()."/resources/slick/slick.css");
  // Custom CSS
  wp_enqueue_style("main",get_template_directory_uri()."/main.css");
}
add_action("wp_enqueue_scripts","load_css");

// Load javascript.
function load_js() {
  wp_deregister_script('jquery');
  // Third Party JS Libraries
  
  wp_enqueue_script("jquery",get_template_directory_uri()."/resources/jquery.js",array(),null,true);
  wp_enqueue_script( 'FontAwesome', 'https://kit.fontawesome.com/2e07949954.js', '', '', true );
  wp_enqueue_script("slick",get_template_directory_uri()."/resources/slick/slick.min.js",array("jquery"),null,true);
  wp_enqueue_script("anime",get_template_directory_uri()."/resources/anime.min.js",array(),null,true);
  wp_enqueue_script("visible",get_template_directory_uri()."/resources/jquery.visible.min.js",array(),null,true);
  // Custom JS
  wp_enqueue_script("script",get_template_directory_uri()."/script.js",array("jquery"),null,true);
  wp_localize_script( 'script', 'ajax_variable', array('ajax_url'=> admin_url( 'admin-ajax.php' )) );
  
  // WooCommerce AJAX support
  if (class_exists('WooCommerce')) {
    wp_localize_script('script', 'wc_add_to_cart_params', array(
      'ajax_url' => admin_url('admin-ajax.php'),
      'wc_ajax_url' => WC_AJAX::get_endpoint("%%endpoint%%"),
      'i18n_view_cart' => esc_attr__('View cart', 'woocommerce'),
      'cart_url' => apply_filters('woocommerce_add_to_cart_redirect', wc_get_cart_url(), null),
      'is_cart' => is_cart(),
      'cart_redirect_after_add' => get_option('woocommerce_cart_redirect_after_add')
    ));
  }
}
add_action("wp_enqueue_scripts","load_js");