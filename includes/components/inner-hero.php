<?php

// Gather data with minimal repeated calls
$parent_id = wp_get_post_parent_id( get_the_ID() );
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

$acf_heading = $acf_get( 'heading' );
$acf_level   = $acf_get( 'heading_level' );
$acf_bg      = $acf_get( 'background_colour' );

// args override ACF
$heading       = ! empty( $args_from_var['heading'] ) ? $args_from_var['heading'] : $acf_heading;
$heading_level = ! empty( $args_from_var['heading_level'] ) ? $args_from_var['heading_level'] : $acf_level;
$colour_picker = ! empty( $args_from_var['background_colour'] ) ? $args_from_var['background_colour'] : $acf_bg;

// Archive-specific defaults when no explicit heading provided
if ( empty( $heading ) ) {
    $current_template = '';
    if ( ! empty( $GLOBALS['template'] ) ) {
        $current_template = basename( $GLOBALS['template'] );
    }

    if ( is_home() || is_post_type_archive( 'post' ) || is_page_template( 'page-template-index.php' ) || 'index.php' === $current_template ) {
        $heading = __( 'Blog', 'jrcleaning' );
    } elseif ( ( function_exists( 'is_shop' ) && is_shop() ) || is_post_type_archive( 'product' ) ) {
        $heading = __( 'Shop', 'jrcleaning' );
    }
}

// Normalize heading level to safe values
$allowed_levels = array( 'h1','h2','h3','h4','h5','h6' );
$heading_level = strtolower( trim( (string) $heading_level ) );
if ( ! in_array( $heading_level, $allowed_levels, true ) ) {
    $heading_level = 'h1';
}

// Visibility flags with sensible defaults
$show_heading = true;
if ( is_array( $args_from_var ) ) {
    if ( array_key_exists( 'show_heading', $args_from_var ) ) {
        $show_heading = (bool) $args_from_var['show_heading'];
    }
}


// Ensure a default background colour
$colour_picker = ! empty( $colour_picker ) ? $colour_picker : '#143458';

?>

<section class="inner-hero-block" style="background-color: <?php echo esc_attr( $colour_picker ); ?>;">
    <div class="primary-container">
        <div class="row-wrapper">
            
            <div class="inner-hero-block__breadcrumb">
                    <?php
                    // Output breadcrumbs: prefer Yoast SEO, fallback to basic
                    if ( function_exists('yoast_breadcrumb') ) {
                        yoast_breadcrumb('<p id="breadcrumbs">','</p>');
                    } else {
                        // Basic fallback: Home > Current
                        echo '<p id="breadcrumbs">';
                        echo '<a href="' . esc_url(home_url('/')) . '">' . esc_html__('Home', 'jrcleaning') . '</a>';
                        if ( is_singular() ) {
                            echo ' &gt; ' . esc_html(get_the_title());
                        } elseif ( is_archive() ) {
                            echo ' &gt; ' . esc_html(get_the_archive_title());
                        } elseif ( is_search() ) {
                            echo ' &gt; ' . esc_html__('Search', 'jrcleaning');
                        }
                        echo '</p>';
                    }
                    ?>
            </div>

            <?php if ( $show_heading && ! empty( $heading ) ) : ?>
                <<?php echo esc_attr( $heading_level ); ?> class="inner-hero-block__heading"><?php echo esc_html( $heading ); ?></<?php echo esc_attr( $heading_level ); ?>>
            <?php endif; ?>
        </div>
    </div>
</section>