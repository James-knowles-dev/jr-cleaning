<?php
/**
 * Template Name: Partner Channel
 * Per-channel landing page. The channel is resolved from the page slug
 * (estate-agents | property-management | holiday-let-management).
 *
 * @package jrcleaning
 */
$channels = jrc_display_channels();
$slug     = get_queried_object()->post_name;

/*
 * Resolve the channel BEFORE get_header(), because the no-channel case redirects and a
 * redirect after get_header() is a "headers already sent" warning, not a redirect.
 *
 * No channels at all means the jrc-partners plugin is inactive: there is no channel to
 * describe, so send the visitor to the partners overview rather than render a page of
 * empty headings.
 */
if ( ! $channels ) {
	wp_safe_redirect( home_url( '/partners/' ) );
	exit;
}

$ch        = $channels[ $slug ] ?? reset( $channels );
$register  = add_query_arg( 'channel', rawurlencode( $slug ), home_url( '/partners/register/' ) ); // carry the channel into the form
$phone_tel = jrc_phone_tel();
$others    = array_diff_key( $channels, array( $slug => true ) );

get_header();
?>

<section class="section--tight" style="padding-bottom:0;">
	<div class="primary-container">
		<nav class="inner-hero__crumbs" aria-label="Breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span class="sep">›</span>
			<a href="<?php echo esc_url( home_url( '/partners/' ) ); ?>">Partners</a> <span class="sep">›</span>
			<span aria-current="page"><?php echo esc_html( $ch['name'] ); ?></span>
		</nav>
	</div>
</section>

<!-- hero -->
<section class="section--tight">
	<div class="primary-container service-detail__hero-grid">
		<div class="service-detail__intro">
			<?php
			// `name` is the short label used in menus and cards; `sub` carries the
			// programme name. Lead with the programme in the heading, since that is
			// what the page is actually about.
			$ch_title = ! empty( $ch['sub'] ) ? $ch['sub'] : $ch['name'];
			?>
			<span class="eyebrow">For <?php echo esc_html( $ch['name'] ); ?></span>
			<h1><?php echo esc_html( $ch_title ); ?></h1>
			<p class="lead"><?php echo esc_html( $ch['intro'] ); ?></p>
			<div class="service-detail__buttons">
				<a href="<?php echo esc_url( $register ); ?>" class="button button--primary">Become a partner →</a>
				<a href="tel:<?php echo esc_attr( $phone_tel ); ?>" class="button button--outline">Talk to us</a>
			</div>
		</div>
		<aside class="service-detail__card">
			<div class="service-detail__card-eyebrow">What this channel gives you</div>
			<div class="channel-value"><?php echo esc_html( $ch['value'] ); ?></div>
			<?php if ( ! empty( $ch['short'] ) ) : ?>
				<p class="channel-short"><?php echo esc_html( $ch['short'] ); ?></p>
			<?php endif; ?>
		</aside>
	</div>
</section>

<!-- why refer us -->
<section class="section section--paper">
	<div class="primary-container">
		<div class="section-head" style="margin-bottom:36px;"><h2>Why refer us</h2></div>
		<div class="forwho-grid">
			<?php foreach ( $ch['why'] as $w ) : ?>
				<div class="forwho-card">
					<span class="forwho-card__ico" aria-hidden="true"><i></i></span>
					<h3><?php echo esc_html( $w['t'] ); ?></h3>
					<p><?php echo esc_html( $w['d'] ); ?></p>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- tiers -->
<section class="section">
	<div class="primary-container">
		<div class="section-head is-center"><h2>The three tiers</h2><p>Eligibility is based on direct work orders and referrals combined, over a rolling twelve months.</p></div>
		<?php jrc_render_tiers(); ?>
	</div>
</section>

<?php // Only rendered when there is more than one active channel; with estate agents
// alone this would be an empty "Other partner channels" heading. ?>
<?php if ( $others ) : ?>
<section class="section section--paper">
	<div class="primary-container">
		<div class="section-head" style="margin-bottom:28px;"><h2>Other partner channels</h2></div>
		<div class="other-channels">
			<?php foreach ( $others as $oslug => $o ) : ?>
				<a class="other-channel" href="<?php echo esc_url( home_url( '/partners/' . $oslug . '/' ) ); ?>">
					<div>
						<div class="other-channel__name"><?php echo esc_html( $o['name'] ); ?></div>
						<div class="other-channel__sub"><?php echo esc_html( $o['sub'] ); ?></div>
					</div>
					<span class="other-channel__more">Explore →</span>
				</a>
			<?php endforeach; ?>
		</div>
	</div>
</section>
<?php endif; ?>

<?php get_footer(); ?>
