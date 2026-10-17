<?php
/**
 * Service block: FAQ / accordion.
 */
$heading = get_sub_field( 'heading' );
if ( ! have_rows( 'items' ) && ! have_rows( 'buttons' ) ) { return; }
?>
<section class="section--tight">
	<div class="primary-container">
	<div class="svc-faq">
		<?php if ( $heading ) : ?><h2 class="svc-faq__heading"><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
		<div class="svc-faq__list" data-faq>
			<?php while ( have_rows( 'items' ) ) : the_row();
				$q = get_sub_field( 'question' );
				$a = get_sub_field( 'answer' );
			?>
				<div class="svc-faq__item">
					<button type="button" class="svc-faq__q" aria-expanded="false">
						<span><?php echo esc_html( $q ); ?></span>
						<i class="svc-faq__chev" aria-hidden="true"></i>
					</button>
					<div class="svc-faq__a">
						<div class="svc-faq__a-inner rte"><?php echo wp_kses_post( $a ); ?></div>
					</div>
				</div>
			<?php endwhile; ?>
		</div>
		<?php jrc_render_buttons( 'svc-actions' ); ?>
	</div>
	</div>
</section>
