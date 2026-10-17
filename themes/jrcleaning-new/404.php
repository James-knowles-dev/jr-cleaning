<?php
/**
 * 404 page.
 *
 * @package jrcleaning
 */
get_header();
?>
<section class="error-404">
	<div class="error-404__code">404</div>
	<h1>We couldn't find that page.</h1>
	<p>It may have moved or no longer exists. Let's get you back to a spotless start.</p>
	<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="button button--primary">Back to home</a>
</section>
<?php get_footer(); ?>
