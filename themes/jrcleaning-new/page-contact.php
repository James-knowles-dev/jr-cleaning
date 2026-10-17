<?php
/**
 * Template Name: Contact
 * Follows the design: heading + intro, form card + contact sidebar.
 * Auto-applies to the Page with slug "contact".
 *
 * @package jrcleaning
 */
get_header();

$phone     = jrc_phone();
$phone_tel = preg_replace( '/\s+/', '', $phone );
$email     = get_field( 'contact_email', 'options' ) ?: 'info@jrcleaning.org';
$location  = get_field( 'contact_location', 'options' ) ?: 'Evesham, Worcestershire';
$form_id   = get_field( 'contact_form_id', 'options' ) ?: 1;

// Service-area map (editable: Contact page → Map embed code; falls back to the shared
// default; a blank value hides it). Sanitising lives in jrc_map_embed_html() so the
// Contact and Areas pages share one allowlist.
$ct_map_html = jrc_map_embed_html( get_field( 'ct_map_embed' ) );

$heading   = get_field( 'ct_heading' ) ?: 'Get in touch';
$intro     = get_field( 'ct_intro' ) ?: "Questions, bookings or just a chat about what you need, drop us a message and a real person from our Evesham team will get back to you within one working day.";
// Office hours. The fallback shares one definition with the schema's opening hours.
$hours     = get_field( 'ct_hours' );
if ( ! $hours ) {
	$hours = jrc_default_office_hours()['display'];
}
?>

<section class="section--tight" style="padding-bottom:0;">
	<div class="primary-container">
		<nav class="inner-hero__crumbs" aria-label="Breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span class="sep">›</span> <span aria-current="page">Contact</span>
		</nav>
	</div>
</section>

<section class="section--tight contact-page">
	<div class="primary-container">
		<h1 class="contact-page__title"><?php echo esc_html( $heading ); ?></h1>
		<p class="contact-page__intro lead"><?php echo esc_html( $intro ); ?></p>

		<div class="contact-page__grid">
			<div class="contact-page__form">
				<?php
				if ( $form_id && function_exists( 'do_shortcode' ) ) {
					echo do_shortcode( '[gravityform id="' . intval( $form_id ) . '" title="false" description="false" ajax="true"]' );
				} else {
					echo '<p class="text-muted">Contact form not configured, set <strong>contact_form_id</strong> in Theme Settings.</p>';
				}
				?>
			</div>

			<aside class="contact-page__side">
				<div class="contact-card contact-card--details">
					<div class="contact-card__label">Talk to us</div>
					<ul class="contact-card__details">
						<li>
							<span class="contact-card__det-label">Phone</span>
							<a class="contact-card__det-val" href="tel:<?php echo esc_attr( $phone_tel ); ?>"><?php echo esc_html( $phone ); ?></a>
						</li>
						<?php if ( $email ) : ?>
							<li>
								<span class="contact-card__det-label">Email</span>
								<a class="contact-card__det-val" href="mailto:<?php echo esc_attr( $email ); ?>"><?php echo esc_html( $email ); ?></a>
							</li>
						<?php endif; ?>
						<?php if ( $location ) : ?>
							<li>
								<span class="contact-card__det-label">Based in</span>
								<span class="contact-card__det-val"><?php echo esc_html( $location ); ?></span>
							</li>
						<?php endif; ?>
					</ul>
				</div>

<?php if ( $ct_map_html ) : ?>
				<div class="contact-card contact-card--map">
					<div class="contact-card__label">Our service area</div>
					<div class="contact-card__map"><?php echo $ct_map_html; // phpcs:ignore WordPress.Security.EscapeOutput ?></div>
					<a class="contact-card__link" href="<?php echo esc_url( home_url( '/areas/' ) ); ?>#coverage">Check we cover your postcode <span aria-hidden="true">→</span></a>
				</div>
				<?php endif; ?>

				<div class="contact-card">
					<div class="contact-card__label">Office hours</div>
					<div class="contact-card__hours">
						<?php foreach ( $hours as $h ) :
							$closed = ( 0 === strcasecmp( trim( $h['time'] ), 'Closed' ) );
						?>
							<div<?php echo $closed ? ' class="is-muted"' : ''; ?>><span><?php echo esc_html( $h['day'] ); ?></span><strong><?php echo esc_html( $h['time'] ); ?></strong></div>
						<?php endforeach; ?>
					</div>
				</div>
			</aside>
		</div>
	</div>
</section>

<?php get_footer(); ?>
