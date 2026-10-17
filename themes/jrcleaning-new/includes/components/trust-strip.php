<?php
/**
 * Block: Trust strip (navy stat band)
 */
if ( ! have_rows( 'stats' ) ) { return; }
?>
<section class="trust-strip">
	<div class="primary-container trust-strip__row">
		<?php while ( have_rows( 'stats' ) ) : the_row();
			$num = get_sub_field( 'number' );
			$suf = get_sub_field( 'suffix' );
			$lab = get_sub_field( 'label' );
		?>
			<div class="trust-strip__cell">
				<div class="trust-strip__n"><?php echo esc_html( $num ); ?><?php if ( $suf ) : ?><span><?php echo esc_html( $suf ); ?></span><?php endif; ?></div>
				<div class="trust-strip__l"><?php echo esc_html( $lab ); ?></div>
			</div>
		<?php endwhile; ?>
	</div>
</section>
