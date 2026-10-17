<?php
/**
 * Block: Testimonials
 */
$heading = get_sub_field( 'heading' );
if ( ! have_rows( 'items' ) ) { return; }
?>
<section class="section testimonials">
	<div class="primary-container">
		<?php if ( $heading ) : ?>
			<div class="section-head is-center"><h2><?php echo esc_html( $heading ); ?></h2></div>
		<?php endif; ?>
		<div class="testimonials__slider" data-slider role="region" aria-roledescription="carousel" aria-label="<?php echo esc_attr( $heading ?: 'Customer testimonials' ); ?>">
			<div class="testimonials__track">
				<?php while ( have_rows( 'items' ) ) : the_row();
					$stars = (int) get_sub_field( 'stars' );
					$quote = get_sub_field( 'quote' );
					$name  = get_sub_field( 'name' );
					$stars = $stars > 0 ? $stars : 5;
				?>
					<figure class="testimonials__card">
						<div class="stars-row"><?php echo esc_html( str_repeat( '★', $stars ) ); ?></div>
						<blockquote><?php echo esc_html( $quote ); ?></blockquote>
						<figcaption><?php echo esc_html( $name ); ?></figcaption>
					</figure>
				<?php endwhile; ?>
			</div>
			<div class="testimonials__nav">
				<button type="button" class="testimonials__arrow" data-dir="prev" aria-label="Previous testimonials">←</button>
				<button type="button" class="testimonials__arrow" data-dir="next" aria-label="Next testimonials">→</button>
			</div>
		</div>
	</div>
</section>
