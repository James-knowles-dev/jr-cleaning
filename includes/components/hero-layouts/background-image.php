<?php
$heading = get_sub_field('heading');
$heading_level = get_sub_field('heading_level');
$sub_heading = get_sub_field('sub_heading');
$background_image = get_sub_field('background_image');

$bg_url = '';
if ( $background_image ) {
    if ( is_array( $background_image ) && ! empty( $background_image['url'] ) ) {
        $bg_url = $background_image['url'];
    } elseif ( is_numeric( $background_image ) ) {
        $src = wp_get_attachment_image_src( $background_image, 'full' );
        if ( ! empty( $src[0] ) ) {
            $bg_url = $src[0];
        }
    } else {
        $bg_url = $background_image;
    }
}
$bg_style = '';
if ( $bg_url ) {
    $bg_style = ' style="background-image: linear-gradient(rgba(0,0,0,0.25), rgba(0,0,0,0.25)), url(\'' . esc_url( $bg_url ) . '\'); background-size: cover; background-position: center center; background-repeat: no-repeat;"';
}
?>

<section class="hero-block<?php echo $bg_url ? ' has-background-image' : ''; ?>"<?php echo $bg_style; ?>>
    <div class="primary-container">
        <div class="container-wrapper">
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
    </div>
</section>