<?php
/**
 * The "not sure what you need?" card that closes a card grid.
 *
 * `.post-card--cta` had been styled in app/scss/layout/_blog.scss since the theme was
 * built and rendered by nothing — the markup it was written for never landed. Rather
 * than delete a designed component, this is it: one partial, used by the blog index
 * and the search results, so the rules have a caller and the grids have an ending.
 *
 * @package jrcleaning
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<a class="post-card post-card--cta" href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">
	<div class="post-card__body">
		<h2>Not sure what you need?</h2>
		<p>Tell us about the property and we will tell you which clean fits — and what it costs.</p>
		<span class="post-card__more">Get in touch →</span>
	</div>
</a>
