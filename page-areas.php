<?php
/* Template Name: Areas Archive */

get_header();

?>

<main class="content">
    <?php include(get_template_directory().'/includes/acf.php'); ?>

        <?php
        $areas = get_terms(array(
            'taxonomy' => 'product_area',
            'hide_empty' => false,
        ));
        if (!empty($areas) && !is_wp_error($areas)) {
            echo '<div class="area-cards primary-container default-center">';
            foreach ($areas as $area) {
                $image = get_field('image', 'product_area_' . $area->term_id);
                $img_html = '';
                if ($image && isset($image['url'])) {
                    $img_html = '<img src="' . esc_url($image['url']) . '" alt="' . esc_attr($image['alt'] ?? $area->name) . '" class="area-image">';
                }
                $link = get_term_link($area);
                echo '<a href="' . esc_url($link) . '" title="' . esc_attr($area->name) . '" class="area-card" data-aos="fade-up">';
                echo $img_html;
                echo '<div class="title-button">';
                echo '<h3>' . esc_html($area->name) . '</h3>';
                echo '<p class="button">Read More</p>';
                echo '</div>';
                echo '</a>';
            }
            echo '</div>';
        }
        ?>
</main>

<?php

get_footer();
