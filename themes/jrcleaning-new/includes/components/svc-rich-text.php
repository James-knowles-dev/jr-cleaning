<?php
/**
 * Service block: rich text (on-brand prose; lists render as teal-tick checklists).
 */
$content = get_sub_field( 'content' );
if ( ! $content && ! have_rows( 'buttons' ) ) { return; }
$anchor = sanitize_title( (string) get_sub_field( 'anchor' ) );
?>
<section class="section--tight svc-rich-wrap"<?php echo $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="primary-container">
		<?php if ( $content ) : ?><div class="svc-rich rte"><?php echo wp_kses_post( $content ); ?></div><?php endif; ?>
		<?php jrc_render_buttons( 'svc-actions' ); ?>
	</div>
</section>
