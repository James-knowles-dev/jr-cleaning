<?php
/**
 * Single case study.
 *
 * @package jrcleaning
 */
get_header();

while ( have_posts() ) : the_post();
	$client = get_field( 'client_name' );
	$date   = get_field( 'date_completed' );
	set_query_var( 'jrc_inner_hero', array( 'eyebrow' => 'Case study', 'heading' => get_the_title() ) );
	get_template_part( 'template-parts/inner-hero' );
	?>
	<article class="case-study-body rte">
		<?php if ( $client || $date ) : ?>
			<p class="lead">
				<?php if ( $client ) : ?><strong>Client:</strong> <?php echo esc_html( $client ); ?><?php endif; ?>
				<?php if ( $date ) : ?> &nbsp;·&nbsp; <strong>Completed:</strong> <?php echo esc_html( $date ); ?><?php endif; ?>
			</p>
		<?php endif; ?>
		<?php if ( has_post_thumbnail() ) : ?>
			<figure class="image-block__figure"><?php the_post_thumbnail( 'large' ); ?></figure>
		<?php endif; ?>
		<?php the_content(); ?>
	</article>
<?php endwhile;

get_footer();
