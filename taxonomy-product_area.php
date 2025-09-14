<?php
/**
 * Template for displaying products in a custom taxonomy archive (Areas).
 *
 * File: taxonomy-product_area.php
 * Location: your-theme/taxonomy-product_area.php
 *
 * This works like WooCommerce category archives.
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' ); ?>

<?php

// Gather data with minimal repeated calls
$args_from_var = get_query_var( 'inner_hero_args' );
if ( ! is_array( $args_from_var ) ) {
    $args_from_var = array();
}

// Helper: prefer get_sub_field() (flexible / block context) then fall back to get_field()
$acf_get = function( $key ) {
    $value = get_sub_field( $key );
    if ( empty( $value ) ) {
        $value = get_field( $key, get_the_ID() );
    }
    return $value;
};

$acf_bg      = $acf_get( 'background_colour' );

$colour_picker = ! empty( $args_from_var['background_colour'] ) ? $args_from_var['background_colour'] : $acf_bg;


// Ensure a default background colour
$colour_picker = ! empty( $colour_picker ) ? $colour_picker : '#143458';

?>

<section class="inner-hero-block" style="background-color: <?php echo esc_attr( $colour_picker ); ?>;">
    <div class="primary-container">
        <div class="row-wrapper">
            
            <div class="inner-hero-block__breadcrumb">
                <?php
                // Manual fallback if needed:
                echo '<p id="breadcrumbs"><a href="' . home_url() . '">Home</a> / <a href="' . home_url('/areas/') . '">Areas</a> / ' . single_term_title('', false) . '</p>';
                ?>
            </div>


            <h1 class="inner-hero-block__heading">
                <?php single_term_title(); ?>
            </h1>
        </div>
    </div>
</section>

    <?php
    /**
     * Hook: woocommerce_before_main_content.
     *
     * @see woocommerce_output_content_wrapper - 10 (outputs opening divs for content)
     * @see woocommerce_breadcrumb - 20
     */
    do_action( 'woocommerce_before_main_content' );
    ?>




    <header class="woocommerce-products-header">
        <?php if ( apply_filters( 'woocommerce_show_page_title', true ) ) : ?>
        <?php endif; ?>

        <?php
        // Optional: show Area description if set
        $term_description = term_description();
        if ( $term_description ) {
            echo '<div class="taxonomy-description">' . $term_description . '</div>';
        }
        ?>
    </header>

    <?php if ( woocommerce_product_loop() ) : ?>

        <?php
        /**
         * Hook: woocommerce_before_shop_loop.
         *
         * @see woocommerce_output_all_notices - 10
         * @see woocommerce_result_count - 20
         * @see woocommerce_catalog_ordering - 30
         */
        do_action( 'woocommerce_before_shop_loop' );
        ?>

        <?php woocommerce_product_loop_start(); ?>

            <?php if ( wc_get_loop_prop( 'total' ) ) : ?>
                <?php while ( have_posts() ) : ?>
                    <?php the_post(); ?>

                    <?php wc_get_template_part( 'content', 'product' ); ?>

                <?php endwhile; ?>
            <?php endif; ?>

        <?php woocommerce_product_loop_end(); ?>

        <?php
        /**
         * Hook: woocommerce_after_shop_loop.
         *
         * @see woocommerce_pagination - 10
         */
        do_action( 'woocommerce_after_shop_loop' );
        ?>

    <?php else : ?>

        <?php
        /**
         * Hook: woocommerce_no_products_found.
         *
         * @see wc_no_products_found - 10
         */
        do_action( 'woocommerce_no_products_found' );
        ?>

    <?php endif; ?>

    <?php
    /**
     * Hook: woocommerce_after_main_content.
     *
     * @see woocommerce_output_content_wrapper_end - 10 (outputs closing divs for content)
     */
    do_action( 'woocommerce_after_main_content' );
    ?>

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


<?php get_footer( 'shop' ); ?>
