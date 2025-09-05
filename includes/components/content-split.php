<section class="content-split">
    <div class="primary-container">
        <?php if( have_rows('5050_image_text') ) {
            while( have_rows('5050_image_text') ) { 
            the_row();
            $video_or_image = get_sub_field('video_or_image'); 
            $left_or_right = get_sub_field('left_or_right');

            $heading = get_sub_field('heading');
            $heading_level = get_sub_field('heading_level');
            $text = get_sub_field('text');
            $button = get_sub_field('button');
            $image = get_sub_field('image');
            $video_id = get_sub_field('video_id');

            $video_image_cls = '';
            if ( $video_or_image == true ) {
                $video_image_cls = ' has-video';
            }
            ?>
                <div class="content-split__item content-split__item--media-right<?php echo $video_image_cls; ?> <?php echo $left_or_right ? 'right' : 'left'; ?>">
                    <div class="content-split__content">
                        <div class="content-split__text">
                            <<?php echo esc_attr($heading_level); ?> class="content-split__heading"><?php echo esc_html($heading); ?></<?php echo esc_attr($heading_level); ?>>
                            <div class="content-split__description"> <?php echo $text; ?> </div>
                            <?php if( have_rows('buttons') ){ ?>
                                <div class="content-split__buttons">                                
                                    <?php while( have_rows('buttons') ) {
                                        the_row();
                                        $button = get_sub_field('button');
                                        $button_style = get_sub_field('button_style');
                                        ?>
                                        <?php if( $button ) {
                                            $button_url = $button['url'];
                                            $button_title = $button['title'];
                                            $button_target = $button['target'] ? $button['target'] : '_self'; ?>
                                            <a class="button button--<?php echo $button_style; ?>" href="<?php echo esc_url( $button_url ); ?>" target="<?php echo esc_attr( $button_target ); ?>"><?php echo esc_html( $button_title ); ?></a>
                                        <?php } ?>
                                    <?php } ?> 
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                    <div class="content-split__media">
                        <?php if( !$video_or_image ){
                            $image_count = 0;                            
                            ob_start();                            
                            if( have_rows('images') ) {
                                while( have_rows('images') ) {
                                    the_row();
                                    $image = get_sub_field('image');
                                    $image_src = wp_get_attachment_image_src($image['id'], 'medium')[0];
                                    $image_count++; ?>
                                    <div class="content-split__image-wrapper">
                                        <img class="content-split__image" src="<?php echo esc_url($image_src); ?>" alt="<?php echo esc_attr($image['alt']); ?>" />
                                    </div>
                                    <?php
                                }
                            }
                            $images_html = ob_get_clean(); ?>
                            <div class="content-split__images content-split__images--carousel<?php echo ($image_count > 1) ? ' owl-carousel' : ''; ?>">
                                <?php echo $images_html; ?>
                            </div>
                        <?php } ?>
                        <?php if( $video_or_image && $video_id ){ ?>   
                            <div class="content-split__video">
                                <iframe class="content-split__iframe" 
                                        src="https://www.youtube.com/embed/<?php echo $video_id ?>?si=MVFssA6018aT9IYg&amp;autoplay=1&amp;controls=0&amp;mute=1&amp;modestbranding=1&amp;rel=0" 
                                        allow="accelerometer; autoplay; encrypted-media; picture-in-picture;" 
                                        allowfullscreen>
                                </iframe>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php  }
        } ?>
    </div>
</section>
