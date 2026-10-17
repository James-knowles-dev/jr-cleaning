<?php
/**
 * Service block: feature (title + intro on the left, "what's included" tick grid on the right).
 */
$title   = trim( (string) get_sub_field( 'title' ) );
$intro   = trim( (string) get_sub_field( 'intro' ) );
$anchor  = sanitize_title( (string) get_sub_field( 'anchor' ) );
$eyebrow = trim( (string) get_sub_field( 'eyebrow' ) );
$cta     = get_sub_field( 'cta' );
if ( '' === $title && ! have_rows( 'items' ) && ! have_rows( 'buttons' ) ) { return; }
?>
<section class="section--tight svc-feature"<?php echo $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="primary-container">
		<div class="svc-feature__grid">
			<div class="svc-feature__intro">
				<?php if ( $eyebrow ) : ?><span class="eyebrow"><?php echo esc_html( $eyebrow ); ?></span><?php endif; ?>
				<?php if ( $title ) : ?><h2 class="svc-feature__title"><?php echo esc_html( $title ); ?></h2><?php endif; ?>
				<?php if ( $intro ) : ?><p class="svc-feature__lead"><?php echo esc_html( $intro ); ?></p><?php endif; ?>
				<?php if ( $cta && ! empty( $cta['url'] ) ) : ?>
					<a class="button button--primary svc-feature__cta" href="<?php echo esc_url( $cta['url'] ); ?>"<?php echo ( '_blank' === ( $cta['target'] ?? '' ) ) ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $cta['title'] ? $cta['title'] : 'Get a free quote' ); ?></a>
				<?php endif; ?>
				<?php jrc_render_buttons( 'svc-actions' ); ?>
			</div>

			<?php if ( have_rows( 'items' ) ) : ?>
				<ul class="svc-feature__list">
					<?php while ( have_rows( 'items' ) ) : the_row();
						$item = trim( (string) get_sub_field( 'item' ) );
						if ( '' === $item ) { continue; }
					?>
						<li><span class="svc-feature__tick" aria-hidden="true"></span><?php echo esc_html( $item ); ?></li>
					<?php endwhile; ?>
				</ul>
			<?php endif; ?>
		</div>
	</div>
</section>
