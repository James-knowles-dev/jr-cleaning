<?php
/**
 * Block: Text block (centered prose)
 */
$content = get_sub_field( 'content' );
if ( ! $content ) { return; }
?>
<section class="section text-block">
	<div class="text-block__inner rte"><?php echo wp_kses_post( $content ); ?></div>
</section>
