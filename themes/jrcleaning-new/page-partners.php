<?php
/**
 * Template Name: Partners / Bonus Program
 * Partners overview, follows the design. Auto-applies to the page with slug "partners".
 *
 * @package jrcleaning
 */
get_header();
$channels = jrc_display_channels();
$register = home_url( '/partners/register/' );

$po_heading = get_field( 'po_heading' ) ?: 'Estate Agent <span class="text-accent">Bonus Program</span>';
$po_intro   = get_field( 'po_intro' ) ?: 'A three-tier bonus structure for estate agents who use us regularly. The more your branch books, directly or by referral, the better the rates for the branch, for the customers you refer, and for cleans at your own home.';
$po_why     = get_field( 'po_why' );
if ( ! $po_why ) {
	$po_why = array(
		array( 'text' => 'DBS-checked, in-house team, never subcontracted' ),
		array( 'text' => 'A photo report after every clean to pass to your clients' ),
		array( 'text' => '15 years local, with a 99.6% check-out pass rate' ),
		array( 'text' => 'One trusted point of contact for every job' ),
	);
}
$po_cta_h = get_field( 'po_cta_heading' ) ?: 'Join the bonus program';
$po_cta_t = get_field( 'po_cta_text' ) ?: 'Set-up takes minutes and a real person replies within one working day.';
?>

<section class="section--tight" style="padding-bottom:0;">
	<div class="primary-container">
		<nav class="inner-hero__crumbs" aria-label="Breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span class="sep">›</span> <span aria-current="page">Partners</span>
		</nav>
	</div>
</section>

<!-- hero -->
<section class="section--tight">
	<div class="primary-container partners-hero">
		<div class="partners-hero__intro">
			<span class="tag">For estate agents</span>
			<h1><?php echo wp_kses_post( $po_heading ); ?></h1>
			<p class="lead"><?php echo esc_html( $po_intro ); ?></p>
			<div class="partners-hero__buttons">
				<a href="<?php echo esc_url( $register ); ?>" class="button button--primary">Become a partner →</a>
				<a href="<?php echo esc_url( home_url( '/partners/estate-agents/' ) ); ?>" class="button button--outline">See the tiers</a>
			</div>
		</div>
		<aside class="partners-why">
			<div class="partners-why__eyebrow">Why partner with us</div>
			<ul>
				<?php foreach ( $po_why as $w ) : ?>
					<li><span>✓</span><?php echo esc_html( $w['text'] ); ?></li>
				<?php endforeach; ?>
			</ul>
		</aside>
	</div>
</section>

<!-- channels -->
<section class="section--tight">
	<div class="primary-container">
		<?php
		// Heading and intro follow the number of active channels, so hiding a channel
		// can never leave the page claiming more routes than it lists.
		$ch_count = count( $channels );
		$ch_multi = $ch_count > 1;

		if ( $ch_multi ) {
			$numbers  = array( 2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five' );
			$ch_head  = ( $numbers[ $ch_count ] ?? $ch_count ) . ' ways to partner';
			$ch_intro = 'Pick the channel that fits your business, each one is built around work your clients already need.';
		} else {
			$ch_head  = 'Who it is for';
			$ch_intro = 'Built around the work your clients already need, on every tenancy change.';
		}

		/**
		 * With several channels the heading sits above a grid of cards. With one, a
		 * grid built for three leaves a single card marooned beside empty space — so
		 * pair it with the copy in a 50/50 instead: card left, text right.
		 */
		$ch_cards = '';
		foreach ( $channels as $slug => $c ) {
			$ch_cards .= '<a class="channel-card" href="' . esc_url( home_url( '/partners/' . $slug . '/' ) ) . '">'
				. '<span class="channel-card__num">' . esc_html( $c['num'] ) . '</span>'
				. '<h3>' . esc_html( $c['name'] ) . '</h3>'
				. '<div class="channel-card__sub">' . esc_html( $c['sub'] ) . '</div>'
				. '<p>' . esc_html( $c['short'] ) . '</p>'
				. '<span class="channel-card__more">Explore →</span>'
				. '</a>';
		}
		?>

		<?php if ( $ch_multi ) : ?>
			<div class="section-head">
				<h2><?php echo esc_html( $ch_head ); ?></h2>
				<p><?php echo esc_html( $ch_intro ); ?></p>
			</div>
			<div class="channel-grid">
				<?php echo $ch_cards; // phpcs:ignore WordPress.Security.EscapeOutput -- built with escaping above. ?>
			</div>
		<?php else : ?>
			<?php
			// Content comes first in the DOM so the section's h2 precedes the card's
			// h3; CSS places the card in the left-hand column visually.
			?>
			<div class="channel-split">
				<div class="channel-split__content">
					<h2><?php echo esc_html( $ch_head ); ?></h2>
					<p><?php echo esc_html( $ch_intro ); ?></p>
					<p>You decide who cleans a property between tenancies, so a clean is needed on every changeover. That makes the work predictable for us and the bonus predictable for you, whether the branch issues the order directly or a tenant books on your recommendation.</p>
					<ul class="channel-split__points">
						<li>No minimum volume and no contract to sign</li>
						<li>Booked as and when a tenancy ends</li>
						<li>A 99.6% check-out pass rate, so a referral never comes back on you</li>
					</ul>
					<a class="button button--primary" href="<?php echo esc_url( home_url( '/partners/estate-agents/' ) ); ?>">See the three tiers →</a>
				</div>
				<div class="channel-split__card">
					<?php echo $ch_cards; // phpcs:ignore WordPress.Security.EscapeOutput -- built with escaping above. ?>
				</div>
			</div>
		<?php endif; ?>
	</div>
</section>

<!-- how it works -->
<section class="section section--paper">
	<div class="primary-container">
		<div class="section-head is-center"><h2>How the programme works</h2></div>
		<div class="prog-steps">
			<?php
			$steps = array(
				array( '1', 'Join', 'Tell us which branch you are with and we set you up, with your agency price list ready in your account.' ),
				array( '2', 'Book or refer', 'Issue work orders directly, or share your referral code with landlords and tenants. Either way it counts towards your branch.' ),
				array( '3', 'Move up a tier', 'Volume over a rolling year moves you up, improving the rates for the branch, your referred customers and your own home.' ),
			);
			foreach ( $steps as $s ) : ?>
				<div class="prog-step">
					<span class="prog-step__num"><?php echo esc_html( $s[0] ); ?></span>
					<h3><?php echo esc_html( $s[1] ); ?></h3>
					<p><?php echo esc_html( $s[2] ); ?></p>
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

<!-- CTA -->
<section class="section" style="padding-top:0;">
	<div class="primary-container">
		<div class="partners-cta">
			<div>
				<h2><?php echo esc_html( $po_cta_h ); ?></h2>
				<p><?php echo esc_html( $po_cta_t ); ?></p>
			</div>
			<a href="<?php echo esc_url( $register ); ?>" class="button button--primary">Become a partner →</a>
		</div>
	</div>
</section>

<?php get_footer(); ?>
