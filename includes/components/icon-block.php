<?php

   $heading = get_sub_field('heading');
   $heading_level = get_sub_field('heading_level');

?>

<section class="icon-block">

    <?php if( $heading ): ?>
        <<?php echo esc_attr( $heading_level ); ?> class="icon-block__heading">
            <?php echo esc_html( $heading ); ?>
        </<?php echo esc_attr( $heading_level ); ?>>
    <?php endif; ?>

    <div class="icon-block__blocks">

            <?php
            // Correctly use the field name in have_rows()
            if( have_rows('icon_cards') ): 

                // Loop through rows.
                while( have_rows('icon_cards') ) : the_row();

                $heading = get_sub_field('heading');
                $button = get_sub_field('button');
                $icon = get_sub_field('icon');
                $text = get_sub_field('text')?>

                <div class="icon-block__card">

                    <?php if( $icon ): ?>
                        <div class="icon-block__card-icon">
                            <img src="<?php echo esc_url( $icon['url'] ); ?>" alt="<?php echo esc_attr( $icon['alt'] ); ?>" />
                        </div>
                    <?php endif; ?>

                    <?php if( $heading ): ?>
                        <h5 class="icon-block__card-heading"><?php echo esc_html( $heading ); ?></h5>
                    <?php endif; ?>

                    <div class="icon-block__card-text"><?php echo $text; ?></div>

                    <?php if( $button ): 
                        $button_url = $button['url'];
                        $button_title = $button['title'];
                        $button_target = $button['target'] ? $button['target'] : '_self'; ?>
                        <a class="icon-block__card-button" href="<?php echo esc_url( $button_url ); ?>" target="<?php echo esc_attr( $button_target ); ?>">
                            <?php echo esc_html( $button_title ); ?>
                        </a>
                    <?php endif; ?>
                </div><?php
                // End loop.
                endwhile;

            // No value.
            else :
                return;
            endif;
            ?>

    </div>

</section>
