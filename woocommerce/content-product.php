<?php
/**
 * Custom product card for shop/archive loop
 */

defined( 'ABSPATH' ) || exit;

global $product;
if ( empty( $product ) || ! $product->is_visible() ) {
	return;
}
?>
<li <?php wc_product_class( '', $product ); ?>>
	<a href="<?php the_permalink(); ?>" class="custom-product-card-link">
		<div class="custom-product-card-image">
			<?php
			/**
			 * Product image - try featured image first, then first gallery image
			 */
			$image_html = '';
			$image_id = $product->get_image_id();
			
			// If no featured image, get the first gallery image
			if ( ! $image_id ) {
				$gallery_image_ids = $product->get_gallery_image_ids();
				if ( ! empty( $gallery_image_ids ) ) {
					$image_id = $gallery_image_ids[0];
				}
			}
			
			// Display the image or placeholder
			if ( $image_id ) {
				$image_html = wp_get_attachment_image( $image_id, 'woocommerce_single', false, [
					'alt' => get_the_title(),
					'class' => 'attachment-woocommerce_single size-woocommerce_single'
				] );
			} else {
				$image_html = wc_placeholder_img( 'woocommerce_single' );
			}
			
			echo $image_html;
			?>
		</div>
		<div class="custom-product-card-content">
			<h2 class="custom-product-card-title"><?php the_title(); ?></h2>
			<div class="custom-product-card-price">
				<?php echo $product->get_price_html(); ?>
			</div>
		</div>
		<div class="custom-product-card-footer">
			<span class="custom-product-card-button">View Details</span>
		</div>
	</a>
</li>
