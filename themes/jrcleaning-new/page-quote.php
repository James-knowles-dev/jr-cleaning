<?php
/**
 * Template Name: Quote Wizard
 * A native multi-page Gravity Form (Service → Property → Your details), styled to
 * match the design (service radios as cards, frequency/size as chips), with
 * auto-advance on the service step. Set the form ID in Theme Settings → quote_form_id
 * (the "Quote Wizard" form, created automatically).
 *
 * @package jrcleaning
 */
get_header();
$form_id = function_exists( 'get_field' ) ? get_field( 'quote_form_id', 'options' ) : '';
?>
<div class="quote">
	<div class="primary-container quote__crumbs">
		<nav class="inner-hero__crumbs" aria-label="Breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span class="sep">›</span> <span aria-current="page">Get a free quote</span>
		</nav>
	</div>

	<div class="quote__body">
		<div class="quote__top">
			<h1>Get your free quote</h1>
		</div>

		<div class="quote__grid">
			<div class="quote__panel">
				<?php
				if ( $form_id && function_exists( 'do_shortcode' ) ) {
					echo do_shortcode( '[gravityform id="' . intval( $form_id ) . '" title="false" description="false" ajax="true"]' );
				} else {
					echo '<p class="quote__hint">Quote form not configured, set <strong>quote_form_id</strong> in Theme Settings.</p>';
				}
				?>
			</div>

			<aside class="quote__summary">
				<h3>Free, no-obligation</h3>
				<ul class="quote__assure">
					<li><span>✓</span>No contracts to trap you</li>
					<li><span>✓</span>No hidden costs, a clear, fair price</li>
					<li><span>✓</span>A real person replies within 1 working day</li>
					<li><span>✓</span>Photo report after every clean</li>
				</ul>
				<p class="quote__reassure">We'll only ever use these details to get you a quote.</p>
				<a class="quote__coverage" href="<?php echo esc_url( home_url( '/areas/' ) ); ?>#coverage">Not sure we reach you? Check your postcode <span aria-hidden="true">→</span></a>
			</aside>
		</div>
	</div>
</div>

<?php get_footer(); ?>
