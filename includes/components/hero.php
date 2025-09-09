<?php
$heading = get_sub_field('heading');
$heading_level = get_sub_field('heading_level');
$sub_heading = get_sub_field('sub_heading');
$image = get_sub_field('image');

?>

<section class="hero-block">
    <div class="primary-container">
        <div class="container-wrapper">
            <div class="hero-block__content">
                <<?php echo esc_attr( $heading_level ); ?> class="hero-block__heading">
                    <?php echo esc_html( $heading ); ?>
                </<?php echo esc_attr( $heading_level ); ?>>

                <p class="hero-block__subheading"><?php echo esc_html( $sub_heading ); ?></p>

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
            <div class="hero-block__image">
                <?php if ( $image ) : ?>
                    <img src="<?php echo esc_url( $image['url'] ); ?>" alt="<?php echo esc_attr( $image['alt'] ); ?>" width="800" height="400" />
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>