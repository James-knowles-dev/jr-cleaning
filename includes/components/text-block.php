<section class="text-block">

        <div class="text-block__blocks">

            <?php
            // Correctly use the field name in have_rows()
            if( have_rows('text_blocks') ): 

                // Loop through rows.
                while( have_rows('text_blocks') ) : the_row();

                $text_colour = get_sub_field('text_colour');
                $text = get_sub_field('text')?>

                <div class="text-block__blocks-block <?php if( $text_colour ) { echo 'blue'; } ?>">

                    <div class="text"><?php echo $text; ?></div>

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