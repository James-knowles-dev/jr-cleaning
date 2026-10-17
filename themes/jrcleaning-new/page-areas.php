<?php
/**
 * Template Name: Areas
 * Areas coverage page, content editable via ACF (Areas page field group),
 * with the original copy/towns as fallback defaults.
 *
 * @package jrcleaning
 */
get_header();

// Counties, from ACF, else the original defaults.
$counties = array();
if ( have_rows( 'ar_counties' ) ) {
	while ( have_rows( 'ar_counties' ) ) {
		the_row();
		$towns = array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) get_sub_field( 'towns' ) ) ) );
		$counties[] = array( 'name' => get_sub_field( 'name' ), 'shape' => get_sub_field( 'shape' ) ?: 'circle', 'towns' => $towns );
	}
}
if ( ! $counties ) {
	$counties = array(
		array( 'name' => 'Worcestershire', 'shape' => 'circle', 'towns' => array( 'Evesham', 'Worcester', 'Pershore', 'Malvern', 'Droitwich', 'Redditch', 'Broadway' ) ),
		array( 'name' => 'Gloucestershire', 'shape' => 'square', 'towns' => array( 'Cheltenham', 'Gloucester', 'Tewkesbury', 'Winchcombe', 'Stow-on-the-Wold' ) ),
		array( 'name' => 'Warwickshire', 'shape' => 'diamond', 'towns' => array( 'Stratford-upon-Avon', 'Warwick', 'Leamington Spa', 'Alcester', 'Henley-in-Arden' ) ),
	);
}

$phone      = jrc_phone();
$phone_tel  = preg_replace( '/\s+/', '', $phone );
$split_img  = has_post_thumbnail() ? get_the_post_thumbnail_url( null, 'large' ) : '';

// Feed the postcode checker (app/js/coverage.js) from the editable field, so the
// served-area list is actually controlled from the admin rather than hardcoded JS.
wp_add_inline_script( 'jrc-script', 'window.JRC_COVERAGE = ' . wp_json_encode( jrc_coverage_map() ) . ';', 'before' );

$ar_heading = get_field( 'ar_heading' ) ?: 'Trusted across three counties, based in Evesham.';
$ar_intro   = get_field( 'ar_intro' ) ?: "We cover Worcestershire, Gloucestershire and Warwickshire. Not sure if we reach you? Pop your postcode in and we'll confirm within a working day.";
$ar_band_t  = get_field( 'ar_band_title' ) ?: 'Check we cover your postcode';
$ar_band_s  = get_field( 'ar_band_sub' ) ?: "Instant answer below. We'll confirm exact timing when you book.";
// Coverage map. Same shared default as the Contact page, overridable per page.
$ar_map_html = jrc_map_embed_html( get_field( 'ar_map_embed' ) );

$ar_split_h = get_field( 'ar_split_heading' ) ?: 'Local enough to care, big enough to deliver.';
$ar_split_t = get_field( 'ar_split_text' ) ?: "From our base in Evesham we reach most of the region within the hour. Town not on the list? It's worth asking, we're growing fast and add new areas as our team expands.";
?>

<section class="areas__head section--tight">
	<div class="primary-container">
		<nav class="inner-hero__crumbs" aria-label="Breadcrumb">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>">Home</a> <span class="sep">›</span> <span aria-current="page">Areas</span>
		</nav>
		<h1 class="areas__title"><?php echo esc_html( $ar_heading ); ?></h1>
		<p class="areas__intro lead"><?php echo esc_html( $ar_intro ); ?></p>
	</div>
</section>

<!-- counties -->
<section class="section--tight" aria-labelledby="areas-counties-heading">
	<div class="primary-container">
		<h2 id="areas-counties-heading" class="screen-reader-text">Towns we cover, by county</h2>
		<div class="areas__counties">
			<?php foreach ( $counties as $c ) : ?>
				<div class="county-card">
					<div class="county-card__head">
						<h3><?php echo esc_html( $c['name'] ); ?></h3>
					</div>
					<div class="county-card__towns">
						<?php foreach ( $c['towns'] as $town ) : ?>
							<span class="town-pill"><?php echo esc_html( $town ); ?></span>
						<?php endforeach; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>

<!-- postcode checker (after the town list: "town not listed? check your postcode") -->
<section id="coverage" class="areas__check section--tight">
	<div class="primary-container">
		<div class="areas__check-band">
		<div class="coverage__head">
			<h2><?php echo esc_html( $ar_band_t ); ?></h2>
			<p>Town not listed above? Pop your postcode in and we'll tell you straight away.</p>
		</div>

		<div class="coverage__card">
			<label class="coverage__label" for="cv-postcode">Your postcode</label>
			<div class="coverage__row">
				<input type="text" id="cv-postcode" class="coverage__input" placeholder="e.g. WR11 1AA" autocomplete="postal-code" />
				<button type="button" id="cv-check" class="coverage__btn">Check</button>
			</div>

			<div class="coverage__results" aria-live="polite">
			<div class="coverage__result coverage__result--yes" id="cv-yes">
				<p class="coverage__result-title">Good news, we cover <span id="cv-area">your area</span>.</p>
				<p>Let's get you a free, no-obligation quote. A real person from our Evesham team replies within one working day.</p>
				<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="button button--navy">Contact us →</a>
			</div>

			<div class="coverage__result coverage__result--no" id="cv-no">
				<p class="coverage__result-title">Sorry, that's just outside our area right now.</p>
				<p>We currently cover Worcestershire, Gloucestershire and Warwickshire. We're growing fast though, so it's always worth a quick call to check.</p>
				<a href="tel:<?php echo esc_attr( $phone_tel ); ?>" class="button button--navy">Call <?php echo esc_html( $phone ); ?></a>
			</div>

			<div class="coverage__result coverage__result--invalid" id="cv-invalid">
				<p class="coverage__result-title">That doesn't look like a UK postcode</p>
				<p>Please check it and try again, for example, WR11 1AA.</p>
			</div>
			</div>

			<p class="coverage__note"><?php echo esc_html( $ar_band_s ); ?></p>
		</div>
		</div>
	</div>
</section>

<!-- coverage note split -->
<section class="section">
	<?php
	// The left panel shows the coverage map — a map is a better fit beside "local
	// enough to care" than a photograph, and it means the section is never an empty
	// navy slab. Falls back to the featured image, then to nothing at all.
	$note_media = $ar_map_html ? 'map' : ( $split_img ? 'image' : '' );
	?>
	<div class="primary-container areas__note<?php echo $note_media ? '' : ' areas__note--no-media'; ?>">
		<?php if ( 'map' === $note_media ) : ?>
			<div class="areas__note-media areas__note-media--map"><?php echo $ar_map_html; // phpcs:ignore WordPress.Security.EscapeOutput -- sanitised in jrc_map_embed_html(). ?></div>
		<?php elseif ( 'image' === $note_media ) : ?>
			<div class="areas__note-media" style="background-image:url('<?php echo esc_url( $split_img ); ?>');" role="img" aria-label="<?php echo esc_attr( get_the_title() . ' service area' ); ?>"></div>
		<?php endif; ?>
		<div class="areas__note-content">
			<h2><?php echo esc_html( $ar_split_h ); ?></h2>
			<p><?php echo esc_html( $ar_split_t ); ?></p>
			<div class="areas__note-buttons">
				<a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="button button--primary">Contact us →</a>
				<a href="tel:<?php echo esc_attr( $phone_tel ); ?>" class="button button--outline">Call <?php echo esc_html( $phone ); ?></a>
			</div>
		</div>
	</div>
</section>

<?php get_footer(); ?>
