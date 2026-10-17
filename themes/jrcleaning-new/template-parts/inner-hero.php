<?php
/**
 * Inner hero: light breadcrumb + page title band.
 * Pass args via set_query_var('jrc_inner_hero', [...]) before get_template_part,
 * or it falls back to the current queried object.
 *
 * @package jrcleaning
 */
$args     = get_query_var( 'jrc_inner_hero' );
$args     = is_array( $args ) ? $args : array();
$heading  = $args['heading'] ?? '';
$eyebrow  = $args['eyebrow'] ?? '';
$show_h   = array_key_exists( 'show_heading', $args ) ? (bool) $args['show_heading'] : true;

if ( '' === $heading ) {
	if ( is_singular() ) {
		$heading = get_the_title();
	} elseif ( is_home() ) {
		// The posts page is neither singular nor an archive, so without this branch
		// the blog index renders with no <h1> at all.
		$posts_page = (int) get_option( 'page_for_posts' );
		$heading    = $posts_page ? get_the_title( $posts_page ) : __( 'Blog', 'jrcleaning' );
	} elseif ( is_archive() ) {
		$heading = wp_strip_all_tags( get_the_archive_title() );
	} elseif ( is_search() ) {
		$heading = 'Search results';
	} elseif ( is_404() ) {
		$heading = 'Page not found';
	}
}
?>
<section class="inner-hero">
	<div class="primary-container">
		<nav class="inner-hero__crumbs" aria-label="Breadcrumb">
			<?php
			if ( function_exists( 'yoast_breadcrumb' ) ) {
				yoast_breadcrumb( '<span class="inner-hero__crumbs-inner">', '</span>' );
			} else {
				echo '<a href="' . esc_url( home_url( '/' ) ) . '">Home</a>';
				if ( $heading ) {
					echo ' <span class="sep">›</span> <span aria-current="page">' . esc_html( $heading ) . '</span>';
				}
			}
			?>
		</nav>
		<?php if ( $eyebrow ) : ?><span class="eyebrow inner-hero__eyebrow"><?php echo esc_html( $eyebrow ); ?></span><?php endif; ?>
		<?php
		if ( $show_h && $heading ) :
			// Share the hero block's counter so a page using both an inner hero and a
			// hero block ships exactly one <h1> — whichever renders first wins it.
			$GLOBALS['jrc_hero_count'] = ( $GLOBALS['jrc_hero_count'] ?? 0 ) + 1;
			$ih_tag = tag_escape( 1 === $GLOBALS['jrc_hero_count'] ? 'h1' : 'h2' );
			?>
			<<?php echo $ih_tag; ?> class="inner-hero__title"><?php echo esc_html( $heading ); ?></<?php echo $ih_tag; ?>>
		<?php endif; ?>
	</div>
</section>
