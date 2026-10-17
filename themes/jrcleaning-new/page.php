<?php
/**
 * Default page template, renders the ACF "blocks" flexible content.
 * WooCommerce cart/checkout/account pages fall back to the_content().
 *
 * @package jrcleaning
 */
get_header();
?>

<?php if ( function_exists( 'is_cart' ) && ( is_cart() || is_checkout() || is_account_page() ) ) : ?>
	<?php if ( is_account_page() ) { set_query_var( 'jrc_inner_hero', array( 'show_heading' => false ) ); } ?>
	<?php get_template_part( 'template-parts/inner-hero' ); ?>
	<div class="primary-container section">
		<?php while ( have_posts() ) : the_post(); ?>
			<?php the_content(); ?>
		<?php endwhile; ?>
	</div>
<?php else : ?>
	<?php
	while ( have_posts() ) : the_post();
		if ( have_rows( 'blocks' ) ) {
			include locate_template( 'includes/blocks-loader.php' );
		} else {
			get_template_part( 'template-parts/inner-hero' );
			if ( get_the_content() ) {
				echo '<div class="primary-container section"><div class="rte">';
				the_content();
				echo '</div></div>';
			}
		}
	endwhile;
	?>
<?php endif; ?>

<?php get_footer(); ?>
