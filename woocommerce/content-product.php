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
			 * Product image
			 */
			echo $product->get_image( 'woocommerce_single', [ 'alt' => get_the_title() ] );
			?>
		</div>
		<div class="custom-product-card-content">
			<h2 class="custom-product-card-title"><?php the_title(); ?></h2>
			<div class="custom-product-card-price">
				<?php echo $product->get_price_html(); ?>
				<span class="custom-product-card-vat">(inc VAT)</span>
			</div>
		</div>
		<div class="custom-product-card-footer">
			<span class="custom-product-card-button">View Details</span>
		</div>
	</a>
</li>
