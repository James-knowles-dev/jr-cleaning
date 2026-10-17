<?php
/**
 * Block: Inner hero (breadcrumb + page title). Reuses the styled template part.
 */
$heading = get_sub_field( 'heading' );
$eyebrow = get_sub_field( 'eyebrow' );
$show    = get_sub_field( 'show_heading' );

set_query_var( 'jrc_inner_hero', array(
	'heading'      => $heading ? $heading : get_the_title(),
	'eyebrow'      => $eyebrow,
	'show_heading' => ( null === $show ) ? true : (bool) $show,
) );
get_template_part( 'template-parts/inner-hero' );
