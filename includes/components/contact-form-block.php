<?php
    $form_id = get_sub_field('form_id');
    
if ( $form_id ) { ?>
    <section class="contact-form-block">
        <div class="primary-container">
            <div class="contact-form-block__form">
                <?php echo do_shortcode('[gravityform id="'. $form_id . '" title="false"]'); ?>
            </div>
        </div>
    </section>
<?php } ?>