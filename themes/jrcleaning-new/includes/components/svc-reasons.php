<?php
/**
 * Service block: "why choose us" reason cards.
 */
$heading = trim( (string) get_sub_field( 'heading' ) );
$intro   = trim( (string) get_sub_field( 'intro' ) );
$anchor  = sanitize_title( (string) get_sub_field( 'anchor' ) );
if ( ! have_rows( 'items' ) && ! have_rows( 'buttons' ) ) { return; }
?>
<section class="section section--paper svc-reasons"<?php echo $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="primary-container">
		<div class="section-head">
			<?php if ( $heading ) : ?><h2><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
			<?php if ( $intro ) : ?><p><?php echo esc_html( $intro ); ?></p><?php endif; ?>
		</div>
		<div class="svc-reasons__grid">
			<?php while ( have_rows( 'items' ) ) : the_row();
				$t = trim( (string) get_sub_field( 'title' ) );
				$x = trim( (string) get_sub_field( 'text' ) );
				if ( '' === $t && '' === $x ) { continue; }
			?>
				<div class="svc-reasons__card">
					<?php if ( $t ) : ?><h3><span class="svc-reasons__tick" aria-hidden="true"></span><?php echo esc_html( $t ); ?></h3><?php endif; ?>
					<?php if ( $x ) : ?><p><?php echo esc_html( $x ); ?></p><?php endif; ?>
				</div>
			<?php endwhile; ?>
		</div>
		<?php jrc_render_buttons( 'svc-actions' ); ?>
	</div>
</section>
