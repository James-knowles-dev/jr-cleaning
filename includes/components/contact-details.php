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

            <?php if ($heading): ?>
                <h2 class="contact-details-block__heading"><?php echo esc_html($heading); ?></h2>
            <?php endif; ?>

            <?php if ($subheading): ?>
                <p class="contact-details-block__subheading"><?php echo esc_html($subheading); ?></p>
            <?php endif; ?>

        </div>

        <div class="contact-details-block__info">

            <?php if ($email_address): ?>

                <div class="contact-details-block__item">

                    <div class="contact-details-block__item-label">
                        <img src="<?php echo esc_url(get_theme_file_uri('/images/mail.svg')); ?>" alt="Email Icon">
                        <p>Email</p>
                    </div>

                    <a class="contact-details-block__item-link" href="mailto:<?php echo antispambot($email_address); ?>"><?php echo antispambot($email_address); ?></a>
                    
                </div>

            <?php endif; ?>

            <?php if ($phone_number): ?>

                <div class="contact-details-block__item">

                    <div class="contact-details-block__item-label">
                        <img src="<?php echo esc_url(get_theme_file_uri('/images/phone.svg')); ?>" alt="Phone Icon">
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