<?php
/**
 * Front page, renders the ACF "blocks" flexible content (hero, services, etc).
 *
 * @package jrcleaning
 */
get_header();

while ( have_posts() ) : the_post();
	if ( have_rows( 'blocks' ) ) {
		include locate_template( 'includes/blocks-loader.php' );
	} elseif ( get_the_content() ) {
		echo '<div class="primary-container section"><div class="rte">';
		the_content();
		echo '</div></div>';
	}
endwhile;

get_footer();
