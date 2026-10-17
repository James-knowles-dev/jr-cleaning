<?php
/**
 * Block: CTA strip (navy rounded card)
 */
$heading = trim( (string) get_sub_field( 'heading' ) );
$text    = trim( (string) get_sub_field( 'text' ) );
// Skip rendering an empty CTA (no heading, text or buttons).
if ( '' === $heading && '' === $text && ! have_rows( 'buttons' ) ) { return; }
?>
<section class="section cta">
	<div class="primary-container">
		<div class="cta__card">
			<div class="cta__body">
				<?php if ( $heading ) : ?><h2><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
				<?php if ( $text ) : ?><p><?php echo esc_html( $text ); ?></p><?php endif; ?>
			</div>
			<?php if ( have_rows( 'buttons' ) ) : ?>
				<div class="cta__actions">
					<?php while ( have_rows( 'buttons' ) ) : the_row();
						$link  = get_sub_field( 'link' );
						$style = get_sub_field( 'style' ) ?: 'primary';
						if ( $link ) : ?>
							<a class="button button--<?php echo esc_attr( $style ); ?>" href="<?php echo esc_url( $link['url'] ); ?>" target="<?php echo esc_attr( $link['target'] ?: '_self' ); ?>"<?php echo ( '_blank' === ( $link['target'] ?? '' ) ) ? ' rel="noopener"' : ''; ?>><?php echo esc_html( $link['title'] ); ?></a>
						<?php endif;
					endwhile; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
