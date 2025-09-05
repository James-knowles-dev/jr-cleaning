<?php
/*
** Don't put any extra code inside this file. Instead, create a new file 
** inside the functions directory, and then append it to the below list.
** This is to help keep everything readable and easily editable.  
*/
$functions_includes = array(
	'/custom-post-type.php',
	'/setup.php',		
	'/enqueue.php',
	'/acf.php',
	'/filter-blog.php',	
	'/reading-time.php',
	'/ajax-action.php',
	'/woocommerce.php',
);

foreach ( $functions_includes as $file ) {
	$filepath = locate_template( 'functions' . $file );
	if ( ! $filepath ) {
		trigger_error( sprintf( 'Error locating /inc%s for inclusion', $file ), E_USER_ERROR );
	}
	require_once $filepath;
}

function remove_auto_sizes_css_fix($add_auto_sizes) {return false;}
add_filter('wp_img_tag_add_auto_sizes', 'remove_auto_sizes_css_fix');

// Completely hide all WooCommerce admin notices
add_action( 'admin_head', function() {
    remove_all_actions( 'admin_notices', 10 );
    remove_all_actions( 'all_admin_notices', 10 );
});
