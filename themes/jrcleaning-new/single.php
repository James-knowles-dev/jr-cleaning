<?php
/**
 * Single blog post.
 *
 * @package jrcleaning
 */
get_header();
?>
<?php get_template_part( 'template-parts/inner-hero' ); ?>

<?php while ( have_posts() ) : the_post(); ?>
	<article class="single-post-body rte">
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="image-block__figure"><?php the_post_thumbnail( 'large' ); ?></figure>
		<?php endif; ?>
		<?php the_content(); ?>
	</article>

	<?php
	// The theme ships no comments.php, so this renders WordPress's default list and
	// form. Guarded rather than omitted: without it, switching comments on in
	// Settings → Discussion produces a post that accepts comments and displays none,
	// which reads as a bug. Discussion is off site-wide today, so this is inert.
	if ( comments_open() || get_comments_number() ) {
		echo '<div class="single-post-body">';
		comments_template();
		echo '</div>';
	}
	?>
<?php endwhile; ?>

<?php get_footer(); ?>
