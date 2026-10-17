<?php
/**
 * Case studies archive.
 *
 * @package jrcleaning
 */
get_header();
?>
<?php get_template_part( 'template-parts/inner-hero' ); ?>

<div class="primary-container">
	<?php if ( have_posts() ) : ?>
		<div class="case-grid">
			<?php while ( have_posts() ) : the_post(); ?>
				<a class="case-card" href="<?php the_permalink(); ?>">
					<?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'large' ); endif; ?>
					<div class="case-card__overlay">
						<h2><?php the_title(); ?></h2>
						<?php $client = get_field( 'client_name' ); if ( $client ) : ?><span><?php echo esc_html( $client ); ?></span><?php endif; ?>
					</div>
				</a>
			<?php endwhile; ?>
		</div>
		<div class="pagination"><?php echo paginate_links( array( 'prev_text' => '‹', 'next_text' => '›' ) ); ?></div>
	<?php else : ?>
		<div class="section"><p class="lead">No case studies yet.</p></div>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
