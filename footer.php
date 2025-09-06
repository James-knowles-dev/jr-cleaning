<?php
  $site_logo = get_field('site_logo', 'options');
  $privacy_notice = get_field('privacy_notice', 'options');
  $cookie_notice = get_field('cookie_notice', 'options');
  $footer_form_id = get_field('footer_form_id', 'options');
?>

<footer class="footer">
  <div class="primary-container">
    <div class="footer__main">
        <div class="footer__menus">
          <?php
          // Footer Menu 1
          if (has_nav_menu('footer_menu_1')) { ?>
            <div class="footer__menu-column">
              <h6 class="footer__menu-title">Quick Links</h6>
              <?php wp_nav_menu(array(
                'theme_location' => 'footer_menu_1',
                'menu_class' => 'footer__menu footer__menu--1',
                'container' => false,
                'depth' => 1,
              )); ?>
            </div>
          <?php }
          
          // Footer Menu 2
          if (has_nav_menu('footer_menu_2')) { ?>
            <div class="footer__menu-column">
              <h6 class="footer__menu-title">Services</h6>
              <?php wp_nav_menu(array(
                'theme_location' => 'footer_menu_2',
                'menu_class' => 'footer__menu footer__menu--2',
                'container' => false,
                'depth' => 1,
              )); ?>
            </div>
          <?php }
          
          // Footer Menu 3
          if (has_nav_menu('footer_menu_3')) { ?>
            <div class="footer__menu-column">
              <?php wp_nav_menu(array(
                'theme_location' => 'footer_menu_3',
                'menu_class' => 'footer__menu footer__menu--3',
                'container' => false,
                'depth' => 1,
              )); ?>
            </div>
          <?php } ?>
        </div>
        
        <?php if ($footer_form_id) { ?>
          <div class="footer__form">
            <div class="footer__form-content">
              <?php echo do_shortcode('[gravityform id="'. $footer_form_id . '" title="false"]'); ?>
            </div>
          </div>
        <?php } ?>
    </div>
    <div class="footer__copyright">
      <div class="footer__copyright-content">    
       <p class="footer__text"> &copy; <?php echo date('Y'); ?> <?php echo get_bloginfo('name'); ?>. All rights reserved. </p>
       <?php if( $cookie_notice ): 
            $cookie_notice_url = $cookie_notice['url'];
            $cookie_notice_title = $cookie_notice['title'];
            $cookie_notice_target = $cookie_notice['target'] ? $cookie_notice['target'] : '_self'; ?>
           <a class="footer__link" href="<?php echo esc_url( $cookie_notice_url ); ?>" target="<?php echo esc_attr( $cookie_notice_target ); ?>"><?php echo esc_html( $cookie_notice_title ); ?></a>
          <?php endif; ?>        
        <?php if( $privacy_notice ): 
            $privacy_notice_url = $privacy_notice['url'];
            $privacy_notice_title = $privacy_notice['title'];
            $privacy_notice_target = $privacy_notice['target'] ? $privacy_notice['target'] : '_self'; ?>
            <a class="footer__link" href="<?php echo esc_url( $privacy_notice_url ); ?>" target="<?php echo esc_attr( $privacy_notice_target ); ?>"><?php echo esc_html( $privacy_notice_title ); ?></a>
          <?php endif; ?>
        </div>
        <div class="footer__right">
          <div class="footer__socials">
              <?php if (have_rows('socials', 'option')) {
                while (have_rows('socials', 'option')) {
                  the_row();        
                  $url = get_sub_field('url'); 
                  $platform = get_sub_field('platform'); 
                  if ($url && $platform) {
                    switch ($platform) {
                        case 'facebook':
                            echo '<a href="' . esc_url($url) . '" target="_blank" class="footer__social footer__social--facebook" title="Facebook">'.
                                    file_get_contents("wp-content/themes/jrcleaning/images/socials/facebook.svg")
                                  .'</a>';
                            break;
                        case 'twitter':
                            echo '<a href="' . esc_url($url) . '" target="_blank" class="footer__social footer__social--twitter" title="X">'.
                                    file_get_contents("wp-content/themes/jrcleaning/images/socials/twitter.svg")
                                  .'</a>';
                            break;
                        case 'linkedin':
                            echo '<a href="' . esc_url($url) . '" target="_blank" class="footer__social footer__social--linkedin" title="linked In">'.
                                    file_get_contents("wp-content/themes/jrcleaning/images/socials/linkedin.svg")
                                  .'</a>';
                            break;
                        case 'pinterest':
                            echo '<a href="' . esc_url($url) . '" target="_blank" class="footer__social footer__social--pinterest" title="Pinterest">'.
                                    file_get_contents("wp-content/themes/jrcleaning/images/socials/pinterest.svg")
                                  .'</a>';
                            break;
                        case 'youtube':
                            echo '<a href="' . esc_url($url) . '" target="_blank" class="footer__social footer__social--youtube" title="Youtube">'.
                                    file_get_contents("wp-content/themes/jrcleaning/images/socials/youtube.svg")
                                  .'</a>';
                            break;
                        case 'tiktok':
                            echo '<a href="' . esc_url($url) . '" target="_blank" class="footer__social footer__social--tiktok" title="Tiktok">'.
                                    file_get_contents("wp-content/themes/jrcleaning/images/socials/tiktok.svg")
                                    .'</a>';
                            break;
                        case 'instagram':
                            echo '<a href="' . esc_url($url) . '" target="_blank" class="footer__social footer__social--instagram" title="instagram">'.
                                    file_get_contents("wp-content/themes/jrcleaning/images/socials/instagram.svg")
                                    .'</a>';
                            break;
                    }
                  }
                }
              } ?>
          </div>
          <p class="footer__signature">Made by <a href="https://pixelkicks.co.uk" title="Pixel Kicks"
              target="_blank"><?php echo file_get_contents("wp-content/themes/jrcleaning/images/pixelkicks.svg"); ?></a>
          </p>
        </div>
    </div>
  </div>
</footer>
<?php 
// Output Footer Tags from ACF options
if( have_rows('footer_tags', 'option') ): 
  while( have_rows('footer_tags', 'option') ): the_row(); 
    $script = get_sub_field('script');
    if( $script ): ?>
      <?php echo $script; ?>
    <?php endif;
  endwhile;
endif; 
?>
<?php wp_footer(); ?>
</body>
</html>