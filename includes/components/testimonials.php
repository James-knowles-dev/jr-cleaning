<?php 

$heading = get_sub_field('heading');
$subheading = get_sub_field('subheading');

?>

<section class="testimonials-block">


    
    <div class="testimonials-block__content">
        <?php if ($heading): ?> 
            <h2 class="testimonials-block__heading"><?php echo esc_html($heading); ?></h2>
        <?php endif; ?>

        <?php if ($subheading): ?>
            <p class="testimonials-block__subheading"><?php echo esc_html($subheading); ?></p>
        <?php endif; ?>

        

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