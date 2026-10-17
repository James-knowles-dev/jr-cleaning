<?php
/**
 * Service block: image.
 *
 * Reuses the `image-block` classes from the page builder so there is one set of
 * styles for both, rather than a parallel implementation that drifts.
 */
$image   = get_sub_field( 'image' );
$caption = trim( (string) get_sub_field( 'caption' ) );
$full    = (bool) get_sub_field( 'full_width' );
$anchor  = sanitize_title( (string) get_sub_field( 'anchor' ) );

if ( ! $image && ! have_rows( 'buttons' ) ) {
	return;
}

// A portrait photo stretched to the full container width becomes a screen-height
// slab, so cap its width and centre it instead. Landscape fills the container.
$w = is_array( $image ) ? (int) ( $image['width'] ?? 0 ) : 0;
$h = is_array( $image ) ? (int) ( $image['height'] ?? 0 ) : 0;
$portrait = ( $w && $h && $h > $w );
?>
<section class="section--tight image-block svc-image<?php echo $full ? ' image-block--full' : ''; ?><?php echo $portrait ? ' svc-image--portrait' : ''; ?>"<?php echo $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="<?php echo $full ? '' : 'primary-container'; ?>">
		<?php if ( $image ) : ?>
		<figure class="image-block__figure">
			<?php
			jrc_image_tag(
				$image,
				array(
					'size'         => 'large',
					'fallback_alt' => $caption ? $caption : get_the_title(),
					'sizes'        => $portrait
						? '(min-width: 700px) 520px, 100vw'
						: '(min-width: 1180px) 1120px, 100vw',
				)
			);
			?>
			<?php if ( $caption ) : ?>
				<figcaption><?php echo esc_html( $caption ); ?></figcaption>
			<?php endif; ?>
		</figure>
		<?php endif; ?>
		<?php jrc_render_buttons( 'svc-actions' ); ?>
	</div>
</section>
