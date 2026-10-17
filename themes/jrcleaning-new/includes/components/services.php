<?php
/**
 * Block: Services grid
 */
$eyebrow  = get_sub_field( 'eyebrow' );
$heading  = get_sub_field( 'heading' );
$intro    = get_sub_field( 'intro' );
$show_cta = get_sub_field( 'show_cta' );
?>
<section class="section services">
	<div class="primary-container">
		<div class="section-head">
			<?php if ( $eyebrow ) : ?><span class="eyebrow"><?php echo esc_html( $eyebrow ); ?></span><?php endif; ?>
			<?php if ( $heading ) : ?><h2><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
			<?php if ( $intro ) : ?><p><?php echo esc_html( $intro ); ?></p><?php endif; ?>
		</div>

		<div class="services__grid">
			<?php if ( have_rows( 'cards' ) ) : while ( have_rows( 'cards' ) ) : the_row();
				$icon  = get_sub_field( 'icon' );
				$title = trim( (string) get_sub_field( 'title' ) );
				$text  = trim( (string) get_sub_field( 'text' ) );
				$link  = get_sub_field( 'link' );
				// Skip blank cards (no title, text or icon).
				if ( '' === $title && '' === $text && ! $icon ) { continue; }
				$tag   = $link ? 'a' : 'div';
			?>
				<<?php echo $tag; ?> class="services__card<?php echo $icon ? '' : ' services__card--no-icon'; ?>"<?php if ( $link ) : ?> href="<?php echo esc_url( $link['url'] ); ?>" target="<?php echo esc_attr( $link['target'] ?: '_self' ); ?>"<?php echo ( '_blank' === ( $link['target'] ?? '' ) ) ? ' rel="noopener"' : ''; ?><?php endif; ?>>
					<?php if ( $icon ) : ?><span class="services__ico"><?php jrc_image_tag( $icon, array( 'fallback_alt' => $title ) ); ?></span><?php endif; ?>
					<?php if ( $title ) : ?><h3><?php echo esc_html( $title ); ?></h3><?php endif; ?>
					<?php if ( $text ) : ?><p><?php echo esc_html( $text ); ?></p><?php endif; ?>
				</<?php echo $tag; ?>>
			<?php endwhile; endif; ?>

			<?php if ( $show_cta ) : ?>
				<a class="services__card services__card--cta" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">
					<span class="services__ico services__ico--plus">+</span>
					<div>
						<h3>Not sure what you need?</h3>
						<p>Get in touch and we'll help you choose. →</p>
					</div>
				</a>
			<?php endif; ?>
		</div>

		<div class="slider-dots js-slider-dots" role="tablist" aria-label="Services slide navigation"></div>
	</div>
</section>
