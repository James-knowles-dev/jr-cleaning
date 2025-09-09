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
            <div class="testimonials-block__grid">
                <?php while (have_rows('testimonials', 'options')) {
                    the_row();
                    $stars = get_sub_field('stars');
                    $review = get_sub_field('review');
                    $client_name = get_sub_field('client_name');
                ?>
                    <div class="testimonial">
                        <?php if ($stars): ?>
                            <div class="testimonial__stars">
                                <?php for ($i = 0; $i < $stars; $i++): ?>
                                    <img src="<?php echo esc_url(get_template_directory_uri() . '/images/star.svg'); ?>" alt="Star" />
                                <?php endfor; ?>
                            </div>
                        <?php endif; ?>

                        <?php if ($review): ?>
                            <div class="testimonial__review"><?php echo $review; ?></div>
                        <?php endif; ?>

                        <?php if ($client_name): ?>
                            <p class="testimonial__client-name">- <?php echo esc_html($client_name); ?></p>
                        <?php endif; ?>
                    </div>
                <?php } ?>
            </div>
        <?php endif; ?>
    </div>
</section>