<?php
/**
 * Block: Hero
 */
$rating    = get_sub_field( 'rating_text' );
$heading   = get_sub_field( 'heading' );
$highlight = get_sub_field( 'highlight' );
$sub       = get_sub_field( 'subheading' );
$image     = get_sub_field( 'image' );
$stat_num  = get_sub_field( 'stat_number' );
$stat_lab  = get_sub_field( 'stat_label' );
$badge     = get_sub_field( 'badge' );
// Social-proof card shown instead of the numeric stat when set. The stat values are
// left in place so switching back is just a matter of clearing this field.
$trust     = trim( (string) get_sub_field( 'trust_text' ) );
?>
<section class="hero">
	<div class="primary-container hero__grid">
		<div class="hero__content">
			<?php if ( $rating ) : ?>
				<?php // The stars are decorative; screen readers get "Rated 5 out of 5" instead of ten repetitions of "black star". ?>
				<span class="pill"><span class="stars" aria-hidden="true">★★★★★</span><span class="screen-reader-text">Rated 5 out of 5. </span> <?php echo esc_html( $rating ); ?></span>
			<?php endif; ?>

			<?php if ( $heading ) : $GLOBALS['jrc_hero_count'] = ( $GLOBALS['jrc_hero_count'] ?? 0 ) + 1; $htag = tag_escape( 1 === $GLOBALS['jrc_hero_count'] ? 'h1' : 'h2' ); ?>
				<<?php echo $htag; ?> class="hero__heading"><?php echo esc_html( $heading ); ?> <?php if ( $highlight ) : ?><span class="accent"><?php echo esc_html( $highlight ); ?></span><?php endif; ?></<?php echo $htag; ?>>
			<?php endif; ?>

			<?php if ( $sub ) : ?>
				<p class="hero__sub lead"><?php echo esc_html( $sub ); ?></p>
			<?php endif; ?>

			<?php if ( have_rows( 'buttons' ) ) : ?>
				<div class="hero__buttons">
					<?php while ( have_rows( 'buttons' ) ) : the_row();
						$link  = get_sub_field( 'link' );
						$style = get_sub_field( 'style' ) ?: 'primary';
						if ( $link ) : ?>
							<a class="button button--<?php echo esc_attr( $style ); ?>" href="<?php echo esc_url( $link['url'] ); ?>" target="<?php echo esc_attr( $link['target'] ?: '_self' ); ?>"<?php echo ( '_blank' === ( $link['target'] ?? '' ) ) ? ' rel="noopener"' : ''; ?>><?php echo esc_html( $link['title'] ); ?></a>
						<?php endif;
					endwhile; ?>
				</div>
			<?php endif; ?>

			<?php if ( have_rows( 'ticks' ) ) : ?>
				<div class="hero__ticks">
					<?php while ( have_rows( 'ticks' ) ) : the_row(); ?>
						<span><i></i><?php echo esc_html( get_sub_field( 'text' ) ); ?></span>
					<?php endwhile; ?>
				</div>
			<?php endif; ?>
		</div>

		<div class="hero__media">
			<div class="hero__frame">
				<?php if ( $image ) : ?>
					<?php jrc_image_tag( $image, array( 'priority' => true, 'fallback_alt' => trim( $heading . ' ' . $highlight ) ) ); ?>
				<?php endif; ?>
			</div>
			<?php if ( $trust ) : ?>
				<div class="hero__card hero__card--trust">
					<div class="ico" aria-hidden="true">★</div>
					<div class="t"><?php echo esc_html( $trust ); ?></div>
				</div>
			<?php elseif ( $stat_num ) : ?>
				<div class="hero__card hero__card--stat">
					<div class="n"><?php echo esc_html( $stat_num ); ?></div>
					<div class="l"><?php echo esc_html( $stat_lab ); ?></div>
				</div>
			<?php endif; ?>
			<?php if ( $badge ) : ?>
				<div class="hero__card hero__card--badge">
					<div class="ico">✓</div>
					<div class="t"><?php echo esc_html( $badge ); ?></div>
				</div>
			<?php endif; ?>
		</div>
	</div>
</section>
