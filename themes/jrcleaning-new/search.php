<?php
/**
 * Search results.
 *
 * Without this file WordPress fell back to index.php, which renders every result as a
 * blog card regardless of what it is — a service and a case study both arriving as
 * "post with a category". Results here carry what they actually are, because on a site
 * whose most valuable pages are services, "End of tenancy clean" turning up looking
 * like a blog post is the difference between a search that converts and one that
 * doesn't.
 *
 * @package jrcleaning
 */
get_header();
?>

<?php get_template_part( 'template-parts/inner-hero' ); ?>

<div class="primary-container">

	<div class="search-summary">
		<?php if ( have_posts() ) : ?>
			<p class="lead">
				<?php
				global $wp_query;
				$found = (int) $wp_query->found_posts;
				printf(
					/* translators: 1: number of results, 2: search term */
					esc_html( _n( '%1$s result for %2$s', '%1$s results for %2$s', $found, 'jrcleaning' ) ),
					esc_html( number_format_i18n( $found ) ),
					'<strong>' . esc_html( get_search_query() ) . '</strong>'
				);
				?>
			</p>
		<?php else : ?>
			<p class="lead">
				Nothing matched <strong><?php echo esc_html( get_search_query() ); ?></strong>.
				Try a different word, or tell us what you need and we will point you at it.
			</p>
		<?php endif; ?>

		<?php get_search_form(); ?>
	</div>

	<?php if ( have_posts() ) : ?>
		<div class="post-grid">
			<?php
			while ( have_posts() ) : the_post();
				// What kind of thing this is, in the reader's words rather than the
				// post type's. Falls back to the registered label for anything not
				// listed, so a new CPT is described rather than unlabelled.
				$jrc_kind = array(
					'service'      => 'Service',
					'case_studies' => 'Case study',
					'post'         => 'Article',
					'page'         => 'Page',
					'product'      => 'Shop',
				);
				$jrc_type  = get_post_type();
				$jrc_label = $jrc_kind[ $jrc_type ] ?? '';
				if ( '' === $jrc_label ) {
					$obj       = get_post_type_object( $jrc_type );
					$jrc_label = $obj ? $obj->labels->singular_name : '';
				}
				?>
				<a class="post-card" href="<?php the_permalink(); ?>">
					<div class="post-card__media">
						<?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'medium_large' ); else : ?>
							<img src="<?php echo esc_url( JRC_URI . '/images/placeholder.png' ); ?>" alt="" width="1200" height="800" loading="lazy" decoding="async" />
						<?php endif; ?>
					</div>
					<div class="post-card__body">
						<?php if ( $jrc_label ) : ?>
							<span class="post-card__meta"><?php echo esc_html( $jrc_label ); ?></span>
						<?php endif; ?>
						<h2><?php the_title(); ?></h2>
						<?php if ( has_excerpt() ) : ?>
							<p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 22 ) ); ?></p>
						<?php endif; ?>
						<span class="post-card__more">Read more →</span>
					</div>
				</a>
			<?php endwhile; ?>

			<?php
			// The CTA card. See includes/components/services.php for the same idea on the
			// services grid — a results page that ends in a dead stop is a page the reader
			// leaves from.
			get_template_part( 'template-parts/card-cta' );
			?>
		</div>

		<div class="pagination">
			<?php echo paginate_links( array( 'prev_text' => '‹', 'next_text' => '›' ) ); ?>
		</div>

	<?php else : ?>
		<div class="section">
			<div class="post-grid">
				<?php get_template_part( 'template-parts/card-cta' ); ?>
			</div>
		</div>
	<?php endif; ?>
</div>

<?php get_footer(); ?>
