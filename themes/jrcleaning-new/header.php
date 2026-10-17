<?php
/**
 * Header + opening markup.
 *
 * @package jrcleaning
 */

$logo       = function_exists( 'get_field' ) ? get_field( 'site_logo', 'options' ) : null;
$phone      = jrc_phone();
$phone_tel  = preg_replace( '/\s+/', '', $phone );
$fallback_logo = JRC_URI . '/images/jr-logo-navy.png';
$account_url   = function_exists( 'wc_get_page_permalink' ) ? wc_get_page_permalink( 'myaccount' ) : home_url( '/my-account/' );
$account_label = is_user_logged_in() ? 'My account' : 'Account';
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="screen-reader-text" href="#main">Skip to content</a>

<header class="site-header" id="site-header">
	<div class="primary-container site-header__bar">
		<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="site-header__brand" aria-label="J R Cleaning &amp; Sons home">
			<?php if ( $logo && ! empty( $logo['url'] ) ) : ?>
				<?php jrc_image_tag( $logo, array( 'alt' => $logo['alt'] ? $logo['alt'] : 'J R Cleaning & Sons', 'priority' => true ) ); ?>
			<?php else : ?>
				<img src="<?php echo esc_url( $fallback_logo ); ?>" alt="J R Cleaning &amp; Sons" width="311" height="297" />
			<?php endif; ?>
		</a>

		<nav class="site-header__nav" aria-label="Primary">
			<?php
			wp_nav_menu( array(
				'theme_location' => 'primary',
				'container'      => false,
				'menu_class'     => 'site-nav',
				'depth'          => 2,
				'fallback_cb'    => false,
			) );
			?>
		</nav>

		<div class="site-header__actions">
			<a href="tel:<?php echo esc_attr( $phone_tel ); ?>" class="site-header__phone">
				<span class="dot"></span><?php echo esc_html( $phone ); ?>
			</a>
			<a href="<?php echo esc_url( $account_url ); ?>" class="site-header__account" aria-label="<?php echo esc_attr( $account_label ); ?>" title="<?php echo esc_attr( $account_label ); ?>">
				<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
			</a>
			<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="button button--primary site-header__cta">Contact us</a>
		</div>

		<button class="site-header__burger" aria-label="Open menu" aria-expanded="false" id="jrc-burger">
			<span></span><span></span>
		</button>
	</div>
</header>

<?php // Mobile nav lives OUTSIDE <header> so its fixed positioning is relative to the
// viewport (the header's backdrop-filter would otherwise become its containing block). ?>
<div class="mobile-nav" id="jrc-mobile-nav" aria-hidden="true">
	<?php
	wp_nav_menu( array(
		'theme_location' => 'primary',
		'container'      => false,
		'menu_class'     => 'mobile-nav__menu',
		'depth'          => 2,
		'fallback_cb'    => false,
	) );
	?>
	<div class="mobile-nav__actions">
		<a href="<?php echo esc_url( $account_url ); ?>" class="button button--outline"><?php echo esc_html( $account_label ); ?></a>
		<a href="tel:<?php echo esc_attr( $phone_tel ); ?>" class="button button--outline"><?php echo esc_html( $phone ); ?></a>
		<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="button button--primary">Contact us</a>
	</div>
</div>

<?php // tabindex="-1" so the skip link above actually moves keyboard focus here.
// Without it browsers scroll to #main but leave focus at the top of the page, and
// the next Tab walks back through the whole header the user just skipped. ?>
<main class="content" id="main" tabindex="-1">
