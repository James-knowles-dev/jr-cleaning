<?php
/**
 * Blog index / fallback archive.
 *
 * @package jrcleaning
 */
get_header();
?>

<?php get_template_part( 'template-parts/inner-hero' ); ?>

<div class="primary-container">
	<?php if ( have_posts() ) : ?>
		<div class="post-grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<a class="post-card" href="<?php the_permalink(); ?>">
					<div class="post-card__media">
						<?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'medium_large' ); else : ?>
							<img src="<?php echo esc_url( JRC_URI . '/images/placeholder.png' ); ?>" alt="" width="1200" height="800" loading="lazy" decoding="async" />
						<?php endif; ?>
					</div>
					<div class="post-card__body">
						<?php
						$cat = get_the_category();
						if ( $cat ) : ?><span class="post-card__meta"><?php echo esc_html( $cat[0]->name ); ?></span><?php endif; ?>
						<h2><?php the_title(); ?></h2>
						<span class="post-card__more">Read more →</span>
					</div>
				</a>
			<?php endwhile; ?>

			<?php
			// Closes the grid with somewhere to go. Only on the last page — a CTA halfway
			// through a paginated archive interrupts the list rather than ending it.
			if ( ! get_next_posts_link() ) {
				get_template_part( 'template-parts/card-cta' );
			}
			?>
		</div>

		<div class="pagination">
			<?php echo paginate_links( array( 'prev_text' => '‹', 'next_text' => '›' ) ); ?>
		</div>
	<?php else : ?>
		<div class="section"><p class="lead">No posts found.</p></div>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
