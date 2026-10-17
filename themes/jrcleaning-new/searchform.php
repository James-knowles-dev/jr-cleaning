<?php
/**
 * Search form.
 *
 * Overrides the core default, which is unstyled and labels its field "Search for:".
 *
 * @package jrcleaning
 */

// Unique per instance: the form appears on the results page and could appear again in
// a footer or a 404, and duplicate ids break the label association for every copy
// after the first.
$jrc_search_id = 'search-' . wp_unique_id();
?>
<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
	<label class="screen-reader-text" for="<?php echo esc_attr( $jrc_search_id ); ?>">
		<?php esc_html_e( 'Search this site', 'jrcleaning' ); ?>
	</label>
	<input
		type="search"
		id="<?php echo esc_attr( $jrc_search_id ); ?>"
		class="search-form__field"
		placeholder="Search services, areas, articles…"
		value="<?php echo esc_attr( get_search_query() ); ?>"
		name="s"
	/>
	<button type="submit" class="button button--primary search-form__submit">
		<?php esc_html_e( 'Search', 'jrcleaning' ); ?>
	</button>
</form>
