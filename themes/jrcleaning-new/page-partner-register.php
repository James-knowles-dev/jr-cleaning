<?php
/**
 * Template Name: Partner Registration
 * "Become a partner", channel selector + Gravity Form.
 *
 * SETUP: add a Hidden field to the partner Gravity Form with CSS class `qp-channel`;
 * the selected channel is written into it before submit. Set the form ID in
 * Theme Settings → "partner_form_id".
 *
 * @package jrcleaning
 */
get_header();
$form_id = get_field( 'partner_form_id', 'options' );
$preset  = isset( $_GET['channel'] ) ? sanitize_text_field( wp_unslash( $_GET['channel'] ) ) : '';

// Driven by the active channels, so the selector disappears when the programme runs
// a single channel rather than offering a choice of one.
$labels = array();
foreach ( jrc_display_channels() as $slug => $c ) {
	$labels[ $slug ] = ! empty( $c['name'] ) ? $c['name'] : $slug;
}
if ( ! $labels ) {
	$labels = array( 'estate-agents' => 'Estate Agents' );
}
// Always have a channel selected so attribution is never lost; default to the first.
if ( ! isset( $labels[ $preset ] ) ) { $preset = key( $labels ); }
?>

<section class="section--tight" style="padding-bottom:0;">
	<div class="primary-container primary-container--narrow">
		<nav class="inner-hero__crumbs" aria-label="Breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span class="sep">›</span>
			<a href="<?php echo esc_url( home_url( '/partners/' ) ); ?>">Partners</a> <span class="sep">›</span>
			<span aria-current="page">Become a partner</span>
		</nav>
	</div>
</section>

<section class="section--tight">
	<div class="primary-container primary-container--narrow">
		<h1 class="partner-reg__title"><?php echo esc_html( get_field( 'rg_heading' ) ?: 'Become a partner' ); ?></h1>
		<p class="partner-reg__intro lead"><?php echo esc_html( get_field( 'rg_intro' ) ?: "Tell us a little about your business and we'll set up your affiliate account. A real person replies within one working day, no call centres, no pressure." ); ?></p>

		<div class="partner-reg__card">
			<?php // With one channel there is no choice to make, so the selector is hidden
			// and the single value is still written into the form by the script below. ?>
			<?php if ( count( $labels ) > 1 ) : ?>
			<div class="partner-reg__label" id="partner-reg-channel-label">Which channel fits you?</div>
			<div class="partner-reg__chips" role="radiogroup" aria-labelledby="partner-reg-channel-label">
				<?php foreach ( $labels as $slug => $label ) : $selected = ( $preset === $slug ); ?>
					<button type="button"
						class="partner-reg__chip<?php echo $selected ? ' is-selected' : ''; ?>"
						role="radio"
						aria-checked="<?php echo $selected ? 'true' : 'false'; ?>"
						tabindex="<?php echo $selected ? '0' : '-1'; ?>"
						data-channel="<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $label ); ?></button>
				<?php endforeach; ?>
			</div>
			<?php endif; ?>

			<?php if ( $form_id && function_exists( 'do_shortcode' ) ) : ?>
				<div class="partner-reg__form"><?php echo do_shortcode( '[gravityform id="' . intval( $form_id ) . '" title="false" description="false" ajax="true"]' ); ?></div>
			<?php else : ?>
				<p class="text-muted">Partner form not configured yet, set <strong>partner_form_id</strong> in Theme Settings (Gravity Forms).</p>
			<?php endif; ?>

			<div class="partner-reg__assure">
				<span><span class="dot"></span>No cost to join</span>
				<span><span class="dot"></span>Reply within 1 working day</span>
			</div>
		</div>
	</div>
</section>

<script>
(function () {
	function setHidden(val) {
		var wrap = document.querySelector('.qp-channel');
		if (!wrap) { return; }
		var f = wrap.querySelector('input, select, textarea');
		if (f) { f.value = val; }
	}

	var chips = Array.prototype.slice.call(document.querySelectorAll('.partner-reg__chip'));

	// Single-channel programme: no selector is rendered, so write the one value straight in.
	if (!chips.length) {
		setHidden(<?php echo wp_json_encode( reset( $labels ) ); ?>);
		return;
	}

	// Keep the visual state, the ARIA state and the roving tabindex in step, so
	// the group behaves like a native radio group for keyboard and screen readers.
	function select(chip, focus) {
		chips.forEach(function (x) {
			var on = x === chip;
			x.classList.toggle('is-selected', on);
			x.setAttribute('aria-checked', on ? 'true' : 'false');
			x.setAttribute('tabindex', on ? '0' : '-1');
		});
		setHidden(chip.getAttribute('data-channel'));
		if (focus) { chip.focus(); }
	}

	chips.forEach(function (c, i) {
		c.addEventListener('click', function () { select(c, false); });

		// Arrow keys move between options; Space/Enter picks the focused one.
		c.addEventListener('keydown', function (e) {
			var next = null;
			if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { next = chips[(i + 1) % chips.length]; }
			else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { next = chips[(i - 1 + chips.length) % chips.length]; }
			else if (e.key === ' ' || e.key === 'Enter') { next = c; }
			if (next) { e.preventDefault(); select(next, true); }
		});

		if (c.classList.contains('is-selected')) { setHidden(c.getAttribute('data-channel')); }
	});
})();
</script>

<?php get_footer(); ?>
