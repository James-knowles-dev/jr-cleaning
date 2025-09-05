<?php
$heading = get_sub_field( 'heading' );
$heading_level = get_sub_field( 'heading_level' );
$sub_heading = get_sub_field( 'sub_heading' );
?>

<section class="hero-block images-carousel-text">
    <div class="primary-container">
        <div class="hero-block__content">
            <<?php echo esc_attr( $heading_level ); ?> class="hero-block__heading"><?php echo esc_html( $heading ); ?></<?php echo esc_attr( $heading_level ); ?>>

            <?php if ( $sub_heading ) : ?>
                <p class="hero-block__subheading"><?php echo esc_html( $sub_heading ); ?></p>
            <?php endif; ?>

            <?php if ( have_rows( 'buttons' ) ) : ?>
                <div class="hero-block__buttons">
                    <?php while ( have_rows( 'buttons' ) ) : the_row();
                        $button = get_sub_field( 'button' );
                        $button_style = get_sub_field( 'button_style' );

                        if ( $button ) :
                            $button_url = $button['url'];
                            $button_title = $button['title'];
                            $button_target = $button['target'] ? $button['target'] : '_self';
                    ?>
                        <a class="button button--<?php echo esc_attr( $button_style ); ?>" href="<?php echo esc_url( $button_url ); ?>" target="<?php echo esc_attr( $button_target ); ?>"><?php echo esc_html( $button_title ); ?></a>
                    <?php
                        endif;
                    endwhile; ?>
                </div>
            <?php endif; ?>
        </div>

        <?php
        $image_count = 0;
        ob_start();

        if ( have_rows( 'images_carousel' ) ) {
            while ( have_rows( 'images_carousel' ) ) {
                the_row();
                $image = get_sub_field( 'image' );
                $image_src = wp_get_attachment_image_src( $image['id'], 'medium' )[0];
                $image_count++; ?>
                <div class="hero-block__image-wrapper">
                    <img class="hero-block__image" src="<?php echo esc_url( $image_src ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" />
                </div>
            <?php
            }
        }

        $images_html = ob_get_clean(); ?>

        <div class="hero-block__images hero-block__images--carousel<?php echo ( $image_count > 1 ) ? ' owl-carousel' : ''; ?>">
            <?php echo $images_html; ?>
        </div>
    </div>
</section>