<?php
/**
 * Flexible content loader. Renders the "blocks" field, mapping each layout to a
 * partial in includes/components/{layout}.php (underscores -> hyphens).
 *
 * @package jrcleaning
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( have_rows( 'blocks' ) ) :
	while ( have_rows( 'blocks' ) ) : the_row();

		$layout = get_row_layout();
		if ( empty( $layout ) ) {
			continue;
		}

		// ACF already constrains layout names to [a-z0-9_], and locate_template() only
		// ever resolves inside the theme — but this is the one path in the theme that
		// builds a filename out of stored data, so it validates locally rather than
		// relying on an upstream guarantee holding. Anything with a separator, a dot or
		// a traversal segment in it fails the pattern and is skipped.
		if ( ! preg_match( '/^[a-z0-9_-]+$/i', $layout ) ) {
			continue;
		}

		$partial = str_replace( '_', '-', $layout );
		$file    = locate_template( 'includes/components/' . $partial . '.php' );

		if ( $file ) {
			include $file;
		}

	endwhile;
endif;
