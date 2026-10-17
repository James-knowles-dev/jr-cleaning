<?php
/**
 * Partner programme presentation.
 *
 * The programme DATA — channels, tiers, rates — lives in the jrc-partners plugin
 * (includes/partner-programme.php), because it is configuration the business runs on
 * rather than markup. Only the rendering is here.
 *
 * Every call into the plugin is guarded. With the plugin deactivated the partner
 * marketing pages must still render — just without the tier table — rather than
 * fataling the front end of the whole site.
 *
 * @package jrcleaning
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Channels to show on the partner marketing pages, or an empty array when the
 * jrc-partners plugin is not providing any.
 *
 * This has its OWN name rather than conditionally declaring jrc_active_partner_channels()
 * as a fallback, and that is the whole point of it.
 *
 * A `if ( ! function_exists( 'jrc_active_partner_channels' ) )` stub here looks safe,
 * because in a normal request plugins load before themes and the guard is a settled
 * answer. Plugin ACTIVATION is not a normal request: activate_plugin() include()s the
 * plugin from inside an admin request where the theme has ALREADY loaded. The guard was
 * therefore false, the theme declared the stub, the plugin then declared the real one,
 * and activation died on "Cannot redeclare jrc_active_partner_channels()".
 *
 * A name the plugin never uses cannot collide, in any load order.
 *
 * @return array<string,array> Keyed by channel slug.
 */
function jrc_display_channels() {
	return function_exists( 'jrc_active_partner_channels' ) ? jrc_active_partner_channels() : array();
}

/**
 * Render the tier cards: requirement, standard perks, and December perks where the
 * tier has them.
 */
function jrc_render_tiers() {
	if ( ! function_exists( 'jrc_partner_tiers' ) ) {
		return;
	}

	echo '<div class="tier-grid">';

	foreach ( jrc_partner_tiers() as $t ) {
		echo '<div class="tier-card' . ( ! empty( $t['top'] ) ? ' is-top' : '' ) . '">';

		if ( ! empty( $t['top'] ) ) {
			echo '<span class="tier-card__badge">Best rates</span>';
		}

		echo '<div class="tier-card__name">' . esc_html( $t['tier'] ) . '</div>';
		echo '<div class="tier-card__qual">' . esc_html( $t['qual'] ) . '</div>';

		if ( ! empty( $t['requirement'] ) ) {
			echo '<p class="tier-card__req">' . esc_html( $t['requirement'] ) . '</p>';
		}

		if ( ! empty( $t['perks'] ) ) {
			echo '<ul class="tier-card__perks">';
			foreach ( $t['perks'] as $perk ) {
				echo '<li>' . esc_html( $perk ) . '</li>';
			}
			echo '</ul>';
		}

		if ( ! empty( $t['december'] ) ) {
			echo '<div class="tier-card__december">';
			echo '<span class="tier-card__december-label">Plus every December</span>';
			echo '<ul class="tier-card__perks tier-card__perks--december">';
			foreach ( $t['december'] as $perk ) {
				echo '<li>' . esc_html( $perk ) . '</li>';
			}
			echo '</ul></div>';
		}

		echo '</div>';
	}

	echo '</div>';
}

