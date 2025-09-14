<!DOCTYPE html>
<html lang="en-GB">
<!-- 
    ___ _          _ _    _      _        
  / _ (_)_  _____| | | _(_) ___| | _____ 
  / /_)/ \ \/ / _ \ | |/ / |/ __| |/ / __|
/ ___/| |>  <  __/ |   <| | (__|   <\__ \
\/    |_/_/\_\___|_|_|\_\_|\___|_|\_\___/                                                  
-->  
  <head>

  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <?php wp_head(); ?>
  <?php
    $title = get_the_title();
    if (is_404()) {
        $title = "Page Not Found";
    }
    
    $title_parent = '';
    if (isset($post) && $post->post_parent) {
        $parent_title = get_the_title($post->post_parent);
        if (!empty($parent_title)) {
            $title_parent = $parent_title . " | ";
        }
    }
    
    // Ensure we have valid strings
    $title = !empty($title) ? $title : get_bloginfo("name", "display");
    $site_name = get_bloginfo("name", "display");
    $site_description = get_bloginfo("description", "display");
  ?>
  <title>
    <?php echo esc_html($title); echo " | "; echo esc_html($title_parent); echo esc_html($site_name); echo " | "; echo esc_html($site_description); ?>
  </title>
  <meta name="theme-color" content="#16204b">
    <link rel="stylesheet" type="text/css" href="<?php echo get_template_directory_uri(); ?>/resources/slick/slick.css"/>
    <!-- Removed slick-theme.css reference as file is missing -->
    <script type="text/javascript" src="//code.jquery.com/jquery-1.11.0.min.js"></script>
    <script type="text/javascript" src="//code.jquery.com/jquery-migrate-1.2.1.min.js"></script>
    <script type="text/javascript" src="<?php echo get_template_directory_uri(); ?>/resources/slick/slick.min.js"></script>
  
  <?php 
  // Output Head Tags from ACF options
  if( have_rows('head_tags', 'option') ): 
    while( have_rows('head_tags', 'option') ): the_row(); 
      $script = get_sub_field('script');
      if( $script ): ?>
        <?php echo $script; ?>
      <?php endif;
    endwhile;
  endif; 
  ?>
</head>

<body <?php body_class(); ?>>
  <?php 
  // Output After Body Tags from ACF options
  if( have_rows('after_body_tags', 'option') ): 
    while( have_rows('after_body_tags', 'option') ): the_row(); 
      $script = get_sub_field('script');
      if( $script ): ?>
        <?php echo $script; ?>
      <?php endif;
    endwhile;
  endif; 
  ?>
  <a class="screen-reader-shortcut" href="#wp-toolbar" tabindex="0">Skip to toolbar</a>
  <header>
    <?php
    $site_logo = get_field('site_logo', 'options'); ?>
    <div class="primary-container">
      <div class="header-content-wrapper">
      <?php if( !empty( $site_logo ) ): ?>
        <a href="/" class="site-logo">
          <img src="<?php echo esc_url($site_logo['url']); ?>" alt="jrcleaning" width="50" height="50" />
        </a>
      <?php endif; ?>

      <div class="header-nav-container">
        <nav>
          <?php 
          wp_nav_menu( array( 'theme_location' => 'menu' ) ); 
          ?>
        </nav>

  <?php if (is_user_logged_in()): ?>
        <div class="woo-header-icons">
          <a href="<?php echo esc_url(wc_get_account_endpoint_url('dashboard')); ?>" class="woo-account-icon" title="My Account">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M320 312C386.3 312 440 258.3 440 192C440 125.7 386.3 72 320 72C253.7 72 200 125.7 200 192C200 258.3 253.7 312 320 312zM290.3 368C191.8 368 112 447.8 112 546.3C112 562.7 125.3 576 141.7 576L498.3 576C514.7 576 528 562.7 528 546.3C528 447.8 448.2 368 349.7 368L290.3 368z"/></svg>
          </a>

            <a href="#" class="woo-cart-icon" title="Shopping Cart">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M24 48C10.7 48 0 58.7 0 72C0 85.3 10.7 96 24 96L69.3 96C73.2 96 76.5 98.8 77.2 102.6L129.3 388.9C135.5 423.1 165.3 448 200.1 448L456 448C469.3 448 480 437.3 480 424C480 410.7 469.3 400 456 400L200.1 400C188.5 400 178.6 391.7 176.5 380.3L171.4 352L475 352C505.8 352 532.2 330.1 537.9 299.8L568.9 133.9C572.6 114.2 557.5 96 537.4 96L124.7 96L124.3 94C119.5 67.4 96.3 48 69.2 48L24 48zM208 576C234.5 576 256 554.5 256 528C256 501.5 234.5 480 208 480C181.5 480 160 501.5 160 528C160 554.5 181.5 576 208 576zM432 576C458.5 576 480 554.5 480 528C480 501.5 458.5 480 432 480C405.5 480 384 501.5 384 528C384 554.5 405.5 576 432 576z"/></svg>
            <span class="cart-count"><?php echo esc_html(WC()->cart->get_cart_contents_count()); ?></span>
          </a>
        </div>
  <?php endif; ?>
      </div>

      <div class="mobile-menu">
        <div class="mobile-navigation">

            <a href="javascript:;" class="hamburger">
                <span class="hamburger__wrap">
                    <span class="hamburger__line"></span>
                    <span class="hamburger__line"></span>
                </span>
            </a>
            <div class="mobile-navigation__state" data-clickable="true">
                <div class="mobile-navigation__top"></div>
                <div class="mobile-navigation__inner">
                    <?php 
                        wp_nav_menu(
                            array(
                                'theme_location'=> 'menu',
                                'container'		=> '',
                                'menu_class'    => 'mobile-nav',
                                'depth'         => 1
                            )
                        );
                    ?>
                    
                    <?php if (is_user_logged_in()): ?>
                      <div class="mobile-woo-icons">
                        <a href="<?php echo esc_url(wc_get_account_endpoint_url('dashboard')); ?>" class="mobile-woo-account-icon" title="My Account">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M320 312C386.3 312 440 258.3 440 192C440 125.7 386.3 72 320 72C253.7 72 200 125.7 200 192C200 258.3 253.7 312 320 312zM290.3 368C191.8 368 112 447.8 112 546.3C112 562.7 125.3 576 141.7 576L498.3 576C514.7 576 528 562.7 528 546.3C528 447.8 448.2 368 349.7 368L290.3 368z"/></svg>
                            <span>My Account</span>
                        </a>

                        <a href="#" class="mobile-woo-cart-icon" title="Shopping Cart">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 640"><!--!Font Awesome Free v7.0.0 by @fontawesome - https://fontawesome.com License - https://fontawesome.com/license/free Copyright 2025 Fonticons, Inc.--><path d="M24 48C10.7 48 0 58.7 0 72C0 85.3 10.7 96 24 96L69.3 96C73.2 96 76.5 98.8 77.2 102.6L129.3 388.9C135.5 423.1 165.3 448 200.1 448L456 448C469.3 448 480 437.3 480 424C480 410.7 469.3 400 456 400L200.1 400C188.5 400 178.6 391.7 176.5 380.3L171.4 352L475 352C505.8 352 532.2 330.1 537.9 299.8L568.9 133.9C572.6 114.2 557.5 96 537.4 96L124.7 96L124.3 94C119.5 67.4 96.3 48 69.2 48L24 48zM208 576C234.5 576 256 554.5 256 528C256 501.5 234.5 480 208 480C181.5 480 160 501.5 160 528C160 554.5 181.5 576 208 576zM432 576C458.5 576 480 554.5 480 528C480 501.5 458.5 480 432 480C405.5 480 384 501.5 384 528C384 554.5 405.5 576 432 576z"/></svg>
                            <span>Shopping Cart (<?php echo esc_html(WC()->cart->get_cart_contents_count()); ?>)</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
      </div>
      </div>
    </div>
  </header>