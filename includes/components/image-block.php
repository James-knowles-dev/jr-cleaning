<?php
    $full_width = get_sub_field('full_width');
    $image = get_sub_field('image');
    $caption = get_sub_field('caption');
    $max_height = get_sub_field('max_height');
?>

<section class="image-block <?php if ( $full_width ) { echo 'full-width'; } ?>">

    <?php if ( $image ) : ?>
        <div class="image-block__wrapper">

            <img class="image-block__image" style="max-height: <?php echo esc_attr( $max_height ); ?>px" src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" />

            <?php if ( $caption ) : ?>

                <blockquote class="image-block__caption"><?php echo esc_html( $caption ); ?></blockquote>

            <?php endif; ?>
        </div>

    <?php endif; ?>

</section>