<?php
/**
 * Block: Image block
 */
$image   = get_sub_field( 'image' );
$full    = get_sub_field( 'full_width' );
$caption = get_sub_field( 'caption' );
if ( ! $image ) { return; }
?>
<section class="section image-block <?php echo $full ? 'image-block--full' : ''; ?>">
	<div class="<?php echo $full ? '' : 'primary-container'; ?>">
		<figure class="image-block__figure">
			<?php jrc_image_tag( $image, array( 'fallback_alt' => $caption ? $caption : get_the_title() ) ); ?>
			<?php if ( $caption ) : ?><figcaption><?php echo esc_html( $caption ); ?></figcaption><?php endif; ?>
		</figure>
	</div>
</section>
