<?php
/**
 * Services archive (/services/), matches the design: heading + intro,
 * horizontal service rows, then a "How it works" process band.
 *
 * @package jrcleaning
 */
get_header();
$sa_heading = get_field( 'services_heading', 'options' ) ?: 'All-in-one cleaning, tailored to your space.';
$sa_intro   = get_field( 'services_intro', 'options' ) ?: 'From a single deep clean to a full scheduled contract. One trusted team, one clear point of contact.';
?>

<section class="services-archive__head">
	<div class="primary-container">
		<nav class="inner-hero__crumbs" aria-label="Breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span class="sep">›</span> <span aria-current="page">Services</span>
		</nav>
		<h1 class="services-archive__title"><?php echo esc_html( $sa_heading ); ?></h1>
		<p class="services-archive__intro lead"><?php echo esc_html( $sa_intro ); ?></p>
	</div>
</section>

<section class="services-archive__rows">
	<div class="primary-container">
		<div class="service-rows">
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post();
				$sub     = get_field( 'sub' );
				$popular = get_field( 'popular' );
				$desc    = get_field( 'intro' );
				if ( ! $desc ) { $desc = get_the_excerpt(); }
			?>
				<a class="service-row" href="<?php the_permalink(); ?>">
					<div class="service-row__head">
						<h2 class="service-row__title"><?php the_title(); ?></h2>
						<?php if ( $popular ) : ?>
							<span class="service-row__tag">Most popular</span>
						<?php elseif ( $sub ) : ?>
							<span class="service-row__sub"><?php echo esc_html( $sub ); ?></span>
						<?php endif; ?>
					</div>
					<div class="service-row__desc"><?php echo esc_html( wp_trim_words( $desc, 34 ) ); ?></div>
					<span class="service-row__btn">View details <span aria-hidden="true">→</span></span>
				</a>
			<?php endwhile; endif; ?>
		</div>

		<?php
		// Without this, any service beyond posts_per_page is silently unreachable
		// from the archive. paginate_links() returns null when there's only one page.
		$sa_pagination = paginate_links( array(
			'prev_text' => '‹',
			'next_text' => '›',
			'type'      => 'plain',
		) );
		if ( $sa_pagination ) : ?>
			<nav class="pagination" aria-label="Services pagination"><?php echo wp_kses_post( $sa_pagination ); ?></nav>
		<?php endif; ?>
	</div>
</section>

<!-- How it works -->
<section class="section--tight">
	<div class="primary-container">
		<div class="process">
			<h2 class="process__heading">How it works: start to spotless</h2>
			<?php
			$steps = array();
			if ( have_rows( 'services_steps', 'options' ) ) {
				while ( have_rows( 'services_steps', 'options' ) ) { the_row(); $steps[] = array( get_sub_field( 'title' ), get_sub_field( 'text' ) ); }
			}
			if ( ! $steps ) {
				$steps = array(
					array( 'Enquire', 'Tell us about the job in 60 seconds and we confirm we cover your area.' ),
					array( 'Clear quote', 'A fair, fixed price from a real person, with no hidden costs and no pressure.' ),
					array( 'We clean', 'Our trained, DBS-checked team makes your space spotless to standard.' ),
					array( 'Photo report', 'You get a clear report and proof of the result after every visit.' ),
				);
			}
			?>
			<ol class="process__grid">
				<?php $i = 0; foreach ( $steps as $s ) : $i++; ?>
					<li class="process__step">
						<span class="process__num"><?php echo esc_html( $i ); ?></span>
						<div class="process__body">
							<h3><?php echo esc_html( $s[0] ); ?></h3>
							<p><?php echo esc_html( $s[1] ); ?></p>
						</div>
					</li>
				<?php endforeach; ?>
			</ol>
			<div class="process__cta">
				<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="button button--primary">Contact us →</a>
			</div>
		</div>
	</div>
</section>

<?php get_footer(); ?>
