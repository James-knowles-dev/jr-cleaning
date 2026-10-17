<?php
/**
 * Service block: numbered "how it works" steps.
 */
$heading = trim( (string) get_sub_field( 'heading' ) );
$anchor  = sanitize_title( (string) get_sub_field( 'anchor' ) );
if ( ! have_rows( 'items' ) && ! have_rows( 'buttons' ) ) { return; }
?>
<section class="section--tight svc-steps"<?php echo $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="primary-container">
		<?php if ( $heading ) : ?><h2 class="svc-steps__heading"><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
		<ol class="svc-steps__grid">
			<?php $i = 0; while ( have_rows( 'items' ) ) : the_row();
				$t = trim( (string) get_sub_field( 'title' ) );
				$x = trim( (string) get_sub_field( 'text' ) );
				if ( '' === $t && '' === $x ) { continue; }
				$i++;
			?>
				<li class="svc-steps__step">
					<span class="svc-steps__num"><?php echo esc_html( $i ); ?></span>
					<div class="svc-steps__body">
						<?php if ( $t ) : ?><h3><?php echo esc_html( $t ); ?></h3><?php endif; ?>
						<?php if ( $x ) : ?><p><?php echo esc_html( $x ); ?></p><?php endif; ?>
					</div>
				</li>
			<?php endwhile; ?>
		</ol>
		<?php jrc_render_buttons( 'svc-actions' ); ?>
	</div>
</section>
