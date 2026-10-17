<?php
/**
 * Block: Split (image + content), also used for "why us".
 */
$eyebrow = get_sub_field( 'eyebrow' );
$heading = get_sub_field( 'heading' );
$text    = get_sub_field( 'text' );
$image   = get_sub_field( 'image' );
$side    = get_sub_field( 'image_side' ) ?: 'left';

// The media panel crops to a fixed ratio with object-fit: cover. That is fine for a
// landscape photo, but a portrait one loses most of its height from the middle — so
// a portrait image gets a squarer panel and keeps the top and bottom of the frame.
$img_w    = is_array( $image ) ? (int) ( $image['width'] ?? 0 ) : 0;
$img_h    = is_array( $image ) ? (int) ( $image['height'] ?? 0 ) : 0;
$portrait = ( $img_w && $img_h && $img_h > $img_w );
?>
<section class="section split split--img-<?php echo esc_attr( $side ); ?><?php echo $portrait ? ' split--portrait' : ''; ?>">
	<div class="primary-container split__grid">
		<div class="split__media">
			<?php if ( $image ) : ?>
				<?php jrc_image_tag( $image, array( 'fallback_alt' => $heading ) ); ?>
			<?php endif; ?>
		</div>
		<div class="split__content">
			<?php if ( $eyebrow ) : ?><span class="eyebrow"><?php echo esc_html( $eyebrow ); ?></span><?php endif; ?>
			<?php if ( $heading ) : ?><h2><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
			<?php if ( $text ) : ?><div class="rte split__text"><?php echo wp_kses_post( $text ); ?></div><?php endif; ?>

			<?php if ( have_rows( 'bullets' ) ) : ?>
				<div class="split__bullets">
					<?php while ( have_rows( 'bullets' ) ) : the_row();
						$strong = get_sub_field( 'strong' );
						$btext  = get_sub_field( 'text' );
					?>
						<div class="split__bullet">
							<span class="ck">✓</span>
							<div><?php if ( $strong ) : ?><strong><?php echo esc_html( $strong ); ?></strong> <?php endif; ?><span><?php echo esc_html( $btext ); ?></span></div>
						</div>
					<?php endwhile; ?>
				</div>
			<?php endif; ?>

			<?php if ( have_rows( 'buttons' ) ) : ?>
				<div class="split__buttons">
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
