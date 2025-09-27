<?php

$heading = get_sub_field('heading');
$background_image = get_sub_field('background_image');
$text = get_sub_field('text');

?>

<section class="cta-block" <?php if ($background_image): ?>style="background-image:linear-gradient(rgba(0,0,0,0.8), rgba(0,0,0,0.8)), url(<?php echo esc_url($background_image); ?>);"<?php endif; ?>>
    <div class="cta-block__content">
        <?php if ($heading): ?>
            <h2 class="cta-block__heading"><?php echo esc_html($heading); ?></h2>
        <?php endif; ?>

        <?php if ($text): ?>
            <div class="cta-block__text"><?php echo $text; ?></div>
        <?php endif; ?>

        <?php if (have_rows('buttons')): ?>
            <div class="cta-block__buttons">
                <?php while (have_rows('buttons')) {
                    the_row();
                    $button = get_sub_field('button');
                    $button_style = get_sub_field('button_style');
                ?>
                    <?php if ($button) {
                        $button_url = $button['url'];
                        $button_title = $button['title'];
                        $button_target = $button['target'] ? $button['target'] : '_self';
                    ?>
                        <a class="button button--<?php echo $button_style; ?>" href="<?php echo esc_url($button_url); ?>" target="<?php echo esc_attr($button_target); ?>"><?php echo esc_html($button_title); ?></a>
                    <?php } ?>
                <?php } ?>
            </div>
        <?php endif; ?>
    </div>
</section>
