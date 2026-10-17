<?php
/**
 * Service block: related links. Same renderer as the page builder's links block, so the
 * two cannot drift apart.
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

$jrc_links_tpl = locate_template( 'includes/components/links.php' );
if ( $jrc_links_tpl ) {
	include $jrc_links_tpl;
}
