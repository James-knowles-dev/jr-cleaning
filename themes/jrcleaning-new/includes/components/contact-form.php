<?php
/**
 * Block: Contact form (Gravity Form embed)
 */
$heading = trim( (string) get_sub_field( 'heading' ) );
$text    = trim( (string) get_sub_field( 'text' ) );
$form_id = get_sub_field( 'form_id' );

// Only treat the form as usable if it exists and is active (not trashed/inactive).
$form_ok = $form_id && function_exists( 'do_shortcode' );
if ( $form_ok && class_exists( 'GFAPI' ) ) {
	$gf = GFAPI::get_form( intval( $form_id ) );
	$form_ok = ( $gf && ! empty( $gf['is_active'] ) && empty( $gf['is_trash'] ) );
}

// Nothing to show? Don't render an empty block.
if ( ! $form_ok && '' === $heading && '' === $text ) { return; }
?>
<section class="section contact-form-block">
	<div class="contact-form-block__inner">
		<?php if ( $heading ) : ?><div class="section-head is-center"><h2><?php echo esc_html( $heading ); ?></h2><?php if ( $text ) : ?><p><?php echo esc_html( $text ); ?></p><?php endif; ?></div><?php endif; ?>
		<?php if ( $form_ok ) : ?>
			<div class="contact-form-block__form"><?php echo do_shortcode( '[gravityform id="' . intval( $form_id ) . '" title="false" description="false" ajax="true"]' ); ?></div>
		<?php endif; ?>
	</div>
</section>
