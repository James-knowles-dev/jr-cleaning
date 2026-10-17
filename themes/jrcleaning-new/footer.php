<?php
/**
 * Footer + closing markup.
 *
 * @package jrcleaning
 */

$logo     = function_exists( 'get_field' ) ? get_field( 'site_logo_white', 'options' ) : null;
$email    = function_exists( 'get_field' ) ? get_field( 'contact_email', 'options' ) : '';
$location = function_exists( 'get_field' ) ? get_field( 'contact_location', 'options' ) : '';
$about    = function_exists( 'get_field' ) ? get_field( 'footer_about', 'options' ) : '';
$socials  = function_exists( 'get_field' ) ? get_field( 'socials', 'options' ) : array();

$phone    = jrc_phone();
$location = $location ? $location : 'Evesham, Worcestershire';
$about    = $about ? $about : 'Trained, trustworthy cleaning across Worcestershire, Gloucestershire & Warwickshire. Spotless, every time.';
$fallback_logo = JRC_URI . '/images/jr-logo-white.png';

// Pre-footer CTA (global). Editable in Theme Settings.
$cta_heading = function_exists( 'get_field' ) ? get_field( 'footer_cta_heading', 'options' ) : '';
$cta_text    = function_exists( 'get_field' ) ? get_field( 'footer_cta_text', 'options' ) : '';
$bark_code   = function_exists( 'get_field' ) ? get_field( 'bark_widget', 'options' ) : '';
$show_cta    = function_exists( 'get_field' ) ? get_field( 'footer_cta_show', 'options' ) : true;
$show_cta    = ( null === $show_cta || '' === $show_cta ) ? true : (bool) $show_cta; // default on until explicitly disabled
$cta_heading = $cta_heading ? $cta_heading : 'Ready for a spotless space?';
$cta_text    = $cta_text ? $cta_text : 'Trained staff, guaranteed standards and a photo report after every clean.';
$phone_tel   = preg_replace( '/\s+/', '', $phone );
$bark_url    = 'https://www.bark.com/en/gb/company/jr-cleaning-amp-sons-ltd/ZOy4n/';
?>
</main><!-- /.content -->

<?php if ( $show_cta ) : ?>
<section class="footer-cta">
	<div class="primary-container">
		<div class="footer-cta__card">
			<div class="footer-cta__body">
				<h2><?php echo esc_html( $cta_heading ); ?></h2>
				<p><?php echo esc_html( $cta_text ); ?></p>
				<div class="footer-cta__actions">
					<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="button button--primary">Contact us →</a>
					<a href="tel:<?php echo esc_attr( $phone_tel ); ?>" class="button button--ghost">Or call <?php echo esc_html( $phone ); ?></a>
				</div>
			</div>
			<div class="footer-cta__widget">
				<?php if ( $bark_code ) : ?>
					<?php
						// Raw third-party embed code (Bark widget: <script>/<iframe>). Set only on the
						// admin-only Theme Settings options page, so it is trusted and output verbatim.
						echo $bark_code; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- trusted admin-only embed markup.
						?>
				<?php else : ?>
					<a class="bark-card" href="<?php echo esc_url( $bark_url ); ?>" target="_blank" rel="noopener">
						<div class="bark-card__panel">
							<span class="bark-card__logo"><span class="bark-card__tri"></span>bark</span>
							<div class="bark-card__stars" aria-label="Rated 5 out of 5">★★★★★</div>
						</div>
						<div class="bark-card__meta">
							<div class="bark-card__name">J R Cleaning &amp; Sons Ltd</div>
							<span class="bark-card__link">View our reviews →</span>
						</div>
					</a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</section>
<?php endif; ?>

<footer class="site-footer">
	<div class="primary-container">
		<div class="site-footer__grid">

			<div class="site-footer__about">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-footer__brand">
					<?php if ( $logo && ! empty( $logo['url'] ) ) : ?>
						<?php jrc_image_tag( $logo, array( 'alt' => 'J R Cleaning & Sons' ) ); ?>
					<?php else : ?>
						<img src="<?php echo esc_url( $fallback_logo ); ?>" alt="J R Cleaning &amp; Sons" width="311" height="297" loading="lazy" decoding="async" />
					<?php endif; ?>
				</a>
				<p><?php echo esc_html( $about ); ?></p>
				<?php if ( $socials ) : ?>
					<div class="site-footer__socials">
						<?php foreach ( $socials as $s ) : if ( empty( $s['url'] ) ) { continue; } ?>
							<a href="<?php echo esc_url( $s['url'] ); ?>" target="_blank" rel="noopener" aria-label="<?php echo esc_attr( $s['platform'] ); ?>"><?php echo esc_html( ucfirst( $s['platform'] ) ); ?></a>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="site-footer__col">
				<h2 class="site-footer__col-title">Company</h2>
				<?php
				if ( has_nav_menu( 'footer_menu_1' ) ) {
					wp_nav_menu( array( 'theme_location' => 'footer_menu_1', 'container' => false, 'menu_class' => 'site-footer__menu', 'depth' => 1 ) );
				}
				?>
			</div>

			<div class="site-footer__col">
				<h2 class="site-footer__col-title">Services</h2>
				<?php
				if ( has_nav_menu( 'footer_menu_2' ) ) {
					wp_nav_menu( array( 'theme_location' => 'footer_menu_2', 'container' => false, 'menu_class' => 'site-footer__menu', 'depth' => 1 ) );
				}
				?>
			</div>

			<div class="site-footer__col">
				<h2 class="site-footer__col-title">Get in touch</h2>
				<a href="tel:<?php echo esc_attr( preg_replace( '/\s+/', '', $phone ) ); ?>"><?php echo esc_html( $phone ); ?></a>
				<?php if ( $email ) : ?>
					<a href="mailto:<?php echo esc_attr( $email ); ?>" class="is-accent">Email us</a>
				<?php endif; ?>
				<span class="site-footer__loc"><?php echo esc_html( $location ); ?></span>
			</div>

		</div>

		<div class="site-footer__bottom">
			<span>&copy; <?php echo esc_html( wp_date( 'Y' ) ); ?> <?php bloginfo( 'name' ); ?> Ltd. All rights reserved.</span>
		</div>
	</div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
