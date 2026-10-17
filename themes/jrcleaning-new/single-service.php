<?php
/**
 * Single service (/service/{slug}), follows the design:
 * hero + navy stat card, what's included, who it's for, explore other services.
 *
 * @package jrcleaning
 */
get_header();

while ( have_posts() ) : the_post();

	// The H1 can differ from the post title: menus, cards and breadcrumbs keep the
	// short internal name, while the heading carries the phrase people search for.
	$seo_h1   = trim( (string) get_field( 'seo_h1' ) );
	$sub      = get_field( 'sub' );
	$intro    = get_field( 'intro' );
	if ( ! $intro ) { $intro = get_the_excerpt(); }
	$stat_n   = get_field( 'stat_number' );
	$stat_l   = get_field( 'stat_label' );
	$has_stat = '' !== trim( (string) $stat_n ); // so a "0" stat (e.g. "0 subcontractors") still shows
	$included = get_field( 'included' );
	$for_who  = get_field( 'for_who' );
	$body     = trim( get_the_content() );
	$phone    = jrc_phone();
	$phone_tel = preg_replace( '/\s+/', '', $phone );
	$highlights = $included ? array_slice( $included, 0, 4 ) : array();
	?>

	<!-- breadcrumb -->
	<section class="section--tight" style="padding-bottom:0;">
		<div class="primary-container">
			<nav class="inner-hero__crumbs" aria-label="Breadcrumb">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span class="sep">›</span>
				<a href="<?php echo esc_url( get_post_type_archive_link( 'service' ) ); ?>">Services</a> <span class="sep">›</span>
				<span aria-current="page"><?php the_title(); ?></span>
			</nav>
		</div>
	</section>

	<!-- hero -->
	<section class="section--tight service-detail__hero">
		<div class="primary-container service-detail__hero-grid">
			<div class="service-detail__intro">
				<?php if ( $sub ) : ?><span class="eyebrow"><?php echo esc_html( $sub ); ?></span><?php endif; ?>
				<h1><?php echo esc_html( $seo_h1 ?: get_the_title() ); ?></h1>
				<?php if ( $intro ) : ?><p class="lead"><?php echo esc_html( $intro ); ?></p><?php endif; ?>
				<div class="service-detail__buttons">
					<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="button button--primary">Contact us →</a>
					<a href="tel:<?php echo esc_attr( $phone_tel ); ?>" class="button button--outline">Call <?php echo esc_html( $phone ); ?></a>
				</div>
			</div>

			<?php if ( $has_stat || $highlights ) : ?>
				<aside class="service-detail__card">
					<div class="service-detail__card-eyebrow">Why this works</div>
					<?php if ( $has_stat ) : ?>
						<div class="service-detail__stat"><?php echo esc_html( $stat_n ); ?></div>
						<div class="service-detail__stat-label"><?php echo esc_html( $stat_l ); ?></div>
					<?php endif; ?>
					<?php if ( $highlights ) : ?>
						<ul class="service-detail__hi">
							<?php foreach ( $highlights as $h ) : ?>
								<li><span aria-hidden="true">✓</span><?php echo esc_html( $h['item'] ); ?></li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</aside>
			<?php endif; ?>
		</div>
	</section>

	<?php if ( $included ) : ?>
		<section class="section section--paper">
			<div class="primary-container">
				<div class="section-head">
					<span class="eyebrow">The detail</span>
					<h2>What's included as standard</h2>
					<p>No vague promises. Here's exactly what our team works through, every visit.</p>
				</div>
				<div class="included-grid">
					<?php foreach ( $included as $inc ) : ?>
						<div class="included-item"><span class="ck" aria-hidden="true">✓</span><span><?php echo esc_html( $inc['item'] ); ?></span></div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php if ( $for_who ) : ?>
		<section class="section">
			<div class="primary-container">
				<div class="section-head is-center"><h2>Who it's for</h2></div>
				<div class="forwho-grid">
					<?php foreach ( $for_who as $w ) : ?>
						<div class="forwho-card">
							<span class="forwho-card__ico" aria-hidden="true"><i></i></span>
							<h3><?php echo esc_html( $w['title'] ); ?></h3>
							<p><?php echo esc_html( $w['desc'] ); ?></p>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
		</section>
	<?php endif; ?>

	<?php // Supporting content: how it works, why choose us, FAQ. ?>
	<?php if ( have_rows( 'service_blocks' ) ) : ?>
		<?php include locate_template( 'includes/service-blocks-loader.php' ); ?>
	<?php elseif ( $body ) : ?>
		<section class="section--tight">
			<div class="service-detail__body rte"><?php the_content(); ?></div>
		</section>
	<?php endif; ?>

	<?php
	// Explore other services
	$others = get_posts( array( 'post_type' => 'service', 'numberposts' => 4, 'post__not_in' => array( get_the_ID() ), 'orderby' => 'menu_order', 'order' => 'ASC' ) );
	if ( $others ) : ?>
		<section class="section section--paper">
			<div class="primary-container">
				<div class="section-head" style="margin-bottom:28px;"><h2>Explore other services</h2></div>
				<div class="other-grid">
					<?php foreach ( $others as $o ) : $osub = get_field( 'sub', $o->ID ); ?>
						<a class="other-card" href="<?php echo esc_url( get_permalink( $o->ID ) ); ?>">
							<span class="other-card__title"><?php echo esc_html( $o->post_title ); ?></span>
							<?php if ( $osub ) : ?><span class="other-card__sub"><?php echo esc_html( $osub ); ?></span><?php endif; ?>
							<span class="other-card__more">View service →</span>
						</a>
					<?php endforeach; ?>
				</div>
				<div class="slider-dots js-slider-dots" role="tablist" aria-label="Other services slide navigation"></div>
			</div>
		</section>
	<?php endif; ?>

<?php endwhile; ?>

<?php get_footer(); ?>
