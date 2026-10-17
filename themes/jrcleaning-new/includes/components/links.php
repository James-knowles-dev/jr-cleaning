<?php
/**
 * Block: related links.
 *
 * Built for internal linking, so the anchor text is editable per link and the target is
 * stored as a post ID — see jrc_resolve_link_row(). Unpublished targets are dropped
 * rather than rendered as dead links.
 *
 * Shared by the page builder ("blocks") and the service pages ("service_blocks").
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$heading = trim( (string) get_sub_field( 'heading' ) );
$intro   = trim( (string) get_sub_field( 'intro' ) );
$anchor  = sanitize_title( (string) get_sub_field( 'anchor' ) );
$style   = get_sub_field( 'style' ) ?: 'cards';

// Resolve first: a block whose links all point at drafts should not print an empty shell.
$items = array();
if ( have_rows( 'items' ) ) {
	while ( have_rows( 'items' ) ) {
		the_row();
		$row = jrc_resolve_link_row();
		if ( $row ) {
			$items[] = $row;
		}
	}
}

if ( empty( $items ) && ! have_rows( 'buttons' ) ) {
	return;
}
?>
<section class="section--tight jrc-links jrc-links--<?php echo esc_attr( $style ); ?>"<?php echo $anchor ? ' id="' . esc_attr( $anchor ) . '"' : ''; ?>>
	<div class="primary-container">

		<?php if ( $heading || $intro ) : ?>
			<div class="jrc-links__head">
				<?php if ( $heading ) : ?><h2><?php echo esc_html( $heading ); ?></h2><?php endif; ?>
				<?php if ( $intro ) : ?><p><?php echo esc_html( $intro ); ?></p><?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $items && 'list' === $style ) : ?>
			<ul class="jrc-links__list">
				<?php foreach ( $items as $item ) : ?>
					<li>
						<a href="<?php echo esc_url( $item['url'] ); ?>"<?php echo '_blank' === $item['target'] ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $item['label'] ); ?></a>
						<?php if ( '' !== $item['desc'] ) : ?><span class="jrc-links__desc"><?php echo esc_html( $item['desc'] ); ?></span><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>

		<?php elseif ( $items ) : ?>
			<ul class="jrc-links__grid">
				<?php foreach ( $items as $item ) : ?>
					<li class="jrc-links__card">
						<a class="jrc-links__link" href="<?php echo esc_url( $item['url'] ); ?>"<?php echo '_blank' === $item['target'] ? ' target="_blank" rel="noopener"' : ''; ?>>
							<span class="jrc-links__label"><?php echo esc_html( $item['label'] ); ?></span>
							<span class="jrc-links__arrow" aria-hidden="true">&rarr;</span>
						</a>
						<?php if ( '' !== $item['desc'] ) : ?><p class="jrc-links__desc"><?php echo esc_html( $item['desc'] ); ?></p><?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		<?php endif; ?>

		<?php jrc_render_buttons( 'svc-actions' ); ?>
	</div>
</section>
