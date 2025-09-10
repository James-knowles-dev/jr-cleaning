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


<?php get_footer( 'shop' ); ?>
