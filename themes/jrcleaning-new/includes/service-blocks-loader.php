<?php
/**
 * Renders the Service CPT "service_blocks" flexible content.
 * Maps each layout to includes/components/svc-{layout}.php (underscores -> hyphens).
 *
 * @package jrcleaning
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( have_rows( 'service_blocks' ) ) :
	while ( have_rows( 'service_blocks' ) ) : the_row();
		$layout = get_row_layout();
		if ( empty( $layout ) ) { continue; }
		// Same local validation as includes/blocks-loader.php — see the note there.
		if ( ! preg_match( '/^[a-z0-9_-]+$/i', $layout ) ) { continue; }
		$file = locate_template( 'includes/components/svc-' . str_replace( '_', '-', $layout ) . '.php' );
		if ( $file ) { include $file; }
	endwhile;
endif;
