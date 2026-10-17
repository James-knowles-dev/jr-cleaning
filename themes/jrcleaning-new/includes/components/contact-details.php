<?php
/**
 * Block: Contact details (info + optional Gravity Form + map)
 */
$heading = get_sub_field( 'heading' );
$text    = get_sub_field( 'text' );
$map     = get_sub_field( 'map_url' );
$form_id = get_sub_field( 'form_id' );
?>
<section class="section contact-details">
	<div class="primary-container contact-details__grid">
		<div class="contact-details__info">
			<?php if ( $heading ) : ?><h2><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
			<?php if ( $text ) : ?><p class="lead"><?php echo esc_html( $text ); ?></p><?php endif; ?>

			<?php if ( have_rows( 'items' ) ) : ?>
				<div class="contact-details__items">
					<?php while ( have_rows( 'items' ) ) : the_row();
						$label = get_sub_field( 'label' );
						$value = get_sub_field( 'value' );
						$link  = get_sub_field( 'link' );
					?>
						<div class="contact-details__item">
							<span class="contact-details__label"><?php echo esc_html( $label ); ?></span>
							<?php if ( $link ) : ?>
								<a class="contact-details__value is-link" href="<?php echo esc_url( $link ); ?>"><?php echo esc_html( $value ); ?></a>
							<?php else : ?>
								<span class="contact-details__value"><?php echo esc_html( $value ); ?></span>
							<?php endif; ?>
						</div>
					<?php endwhile; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="contact-details__form">
			<?php if ( $form_id && function_exists( 'do_shortcode' ) ) : ?>
				<div class="contact-details__form-inner"><?php echo do_shortcode( '[gravityform id="' . intval( $form_id ) . '" title="false" description="false" ajax="true"]' ); ?></div>
			<?php elseif ( $map ) : ?>
				<div class="contact-details__map"><iframe title="<?php echo esc_attr( $heading ? $heading . " map" : "Map" ); ?>" src="<?php echo esc_url( $map ); ?>" width="100%" height="100%" style="border:0;" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe></div>
			<?php endif; ?>
		</div>
	</div>
</section>
