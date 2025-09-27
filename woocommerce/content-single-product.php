<?php
/**
 * The template for displaying product content in the single-product.php template
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/content-single-product.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see     https://woo.com/document/template-structure/
 * @package WooCommerce\Templates
 * @version 3.6.0
 */

defined( 'ABSPATH' ) || exit;

global $product;

/**
 * Hook: woocommerce_before_single_product.
 *
 * @hooked woocommerce_output_all_notices - 10
 */
do_action( 'woocommerce_before_single_product' );

if ( post_password_required() ) {
    echo get_the_password_form(); // WPCS: XSS ok.
    return;
}
?>
<div id="product-<?php the_ID(); ?>" <?php wc_product_class( '', $product ); ?>>
    <div class="single-product">
        <div class="product main">

            <?php
            /**
             * Hook: woocommerce_before_single_product_summary.
                 *
                 * @hooked woocommerce_show_product_sale_flash - 10
             */
            do_action( 'woocommerce_before_single_product_summary' );
            ?>
                <?php
                // Manually output product main image if not shown
                if ( $product && $product->get_image_id() ) {
                    echo '<div class="custom-product-image">' . $product->get_image() . '</div>';
                }
                    // Fallback: show first gallery image if no featured image
                    else {
                        $attachment_ids = $product->get_gallery_image_ids();
                        if ( !empty($attachment_ids) ) {
                            $gallery_img = wp_get_attachment_image( $attachment_ids[0], 'woocommerce_single' );
                            echo '<div class="custom-product-image fallback-gallery-image">' . $gallery_img . '</div>';
                        }
                    }
                ?>

            <div class="summary entry-summary">
                        <?php
                        /**
                         * Hook: woocommerce_single_product_summary.
                         *
                         * @hooked woocommerce_template_single_title - 5
                         * @hooked woocommerce_template_single_rating - 10
                         * @hooked woocommerce_template_single_excerpt - 20
                         * @hooked woocommerce_template_single_add_to_cart - 30
                         * @hooked woocommerce_template_single_sharing - 50
                         * @hooked WC_Structured_Data::generate_product_data() - 60
                         */
                        // Check if product has 'service' term in 'product_categories'
                        if ( has_term( 'service', 'product_cat', $product->get_id() ) ) {
                            // Remove add to cart button for service products
                            remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );
                            // Output summary template functions up to excerpt
                            woocommerce_template_single_title();
                            woocommerce_template_single_rating();
                            woocommerce_template_single_excerpt();
                            // Output custom enquire button after excerpt
                            $service_slug = $product->get_slug();
                            
                            // Check ACF field to determine contact URL
                            $use_wizard = get_field('wizard_or_contact', $product->get_id());
                            if ( $use_wizard ) {
                                $contact_url = esc_url( home_url( "/wizard?service={$service_slug}" ) );
                            } else {
                                $contact_url = esc_url( home_url( "/contact?service={$service_slug}" ) );
                            }
                            ?>
                            <a href="<?php echo $contact_url; ?>" class="button enquire-now-button">Enquire Now</a>
                            <?php
                            // Output the rest of the summary template functions except add to cart
                            woocommerce_template_single_sharing();
                            // Structured data output is handled by WooCommerce hooks; no manual call needed.
                        } else {
                            do_action( 'woocommerce_single_product_summary' );
                        }
                        ?>
            </div>
        </div>
    </div>

    <?php
    /**
     * Re-enable product data tabs, upsells and related products.
     */
    do_action( 'woocommerce_after_single_product_summary' );
    ?>
</div>


<?php do_action( 'woocommerce_after_single_product' ); ?>


<section class="testimonials-block">
    <div class="testimonials-block__content">
        
        <h2 class="testimonials-block__heading">Tesimonials</h2>


        <?php if (have_rows('testimonials', 'options')): ?>
            <?php
                // Collect testimonials into array
                $testimonials = array();
                while (have_rows('testimonials', 'options')) {
                    the_row();
                    $testimonials[] = array(
                        'stars' => get_sub_field('stars'),
                        'review' => get_sub_field('review'),
                        'client_name' => get_sub_field('client_name'),
                    );
                }
                $testimonial_count = count($testimonials);
                // Note: The 'owl-carousel' class is used for Slick Carousel initialization in JS.
                $slider_class = $testimonial_count > 3 ? ' owl-carousel' : '';
            ?>
            <div class="testimonials-slider-wrapper">
              <div class="testimonials-block__grid<?php echo $slider_class; ?>">
                <?php foreach ($testimonials as $testimonial) { ?>
                    <div class="testimonial">
                        <?php if ($testimonial['stars']): ?>
                            <div class="testimonial__stars">
                                <?php for ($i = 0; $i < $testimonial['stars']; $i++): ?>
                                    <img src="<?php echo esc_url(get_template_directory_uri() . '/images/star.svg'); ?>" alt="Star" width="20" height="20" />
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($testimonial['review']): ?>
                            <div class="testimonial__review"><?php echo $testimonial['review']; ?></div>
                        <?php endif; ?>

                        <?php if ($testimonial['client_name']): ?>
                            <p class="testimonial__client-name">- <?php echo esc_html($testimonial['client_name']); ?></p>
                        <?php endif; ?>
                    </div>
                <?php } ?>
              </div>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php

    $heading = get_sub_field('heading');
    $subheading = get_sub_field('subheading');

    $email_address = get_field('email_address', 'options');
    $phone_number = get_field('phone_number', 'options');
    $map_embed = get_field('map_embed', 'options');

?>

<section class="contact-details-block">

    <div class="contact-details-block__content">

        <div class="contact-details-block__headings">

            <h2 class="contact-details-block__heading">Get in Touch</h2>
            <p class="contact-details-block__subheading">We’re here to assist you with any inquiries. Reach out today for personalized service!</p>

        </div>

        <div class="contact-details-block__info">

            <?php if ($email_address): ?>

                <div class="contact-details-block__item">

                    <div class="contact-details-block__item-label">
                        <img src="<?php echo esc_url(get_theme_file_uri('/images/mail.svg')); ?>" alt="Email Icon" width="20" height="20">
                        <p>Email</p>
                    </div>

                    <a class="contact-details-block__item-link" href="mailto:<?php echo antispambot($email_address); ?>"><?php echo antispambot($email_address); ?></a>
                    
                </div>

            <?php endif; ?>

            <?php if ($phone_number): ?>

                <div class="contact-details-block__item">

                    <div class="contact-details-block__item-label">
                        <img src="<?php echo esc_url(get_theme_file_uri('/images/phone.svg')); ?>" alt="Phone Icon" width="20" height="20">
                        <p>Phone</p>
                    </div>

                    <a class="contact-details-block__item-link" href="tel:<?php echo preg_replace('/\s+/', '', $phone_number); ?>"><?php echo esc_html($phone_number); ?></a>

                </div>

            <?php endif; ?>

        </div>

    </div>

    <?php if ($map_embed): ?>

        <div class="contact-details-block__map">
            <?php echo $map_embed; ?>
        </div>

    <?php endif; ?>

</section>