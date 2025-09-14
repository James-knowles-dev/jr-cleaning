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
</main>


<?php

get_footer();
