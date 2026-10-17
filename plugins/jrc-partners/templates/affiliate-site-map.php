<?php
/**
 * Partner tab (School accounts): the site map.
 *
 * A map of the campus with a pin per building, beside a list of building cards. Each card
 * says when and how often the building is cleaned, the next cleans and recent reports.
 * The cards are real markup, not built by the script, so everything here still works if
 * the map cannot load — the script only adds the map and the pin <-> card linking.
 *
 * @package jrc-partners
 */
if ( ! defined( 'ABSPATH' ) || empty( $aff ) ) { return; }

$site      = jrc_school_site_data();
$buildings = $site['buildings'];
$pinned    = array_filter( $buildings, function ( $b ) { return null !== $b['lat'] && null !== $b['lng']; } );
?>
<div class="sm">
	<?php if ( ! $buildings ) : ?>
		<div class="aff-card aff-card--note">
			<p>Your site map is being set up. Once we've added your buildings you'll see each one here, with when it's cleaned and how often.</p>
		</div>
		<?php return; ?>
	<?php endif; ?>

	<p class="sm__intro">Tap a building on the map, or in the list, to see when it's cleaned.</p>

	<div class="sm__layout">
		<div class="sm__mapwrap">
			<div id="sm-map" class="sm__map" role="region" aria-label="Map of your school's buildings"
				data-site="<?php echo esc_attr( wp_json_encode( array( 'view' => $site['view'], 'buildings' => array_values( array_map( function ( $b ) { return array( 'id' => $b['id'], 'name' => $b['name'], 'lat' => $b['lat'], 'lng' => $b['lng'], 'soon' => ! empty( $b['next'] ) ); }, $buildings ) ) ) ) ); ?>">
				<?php if ( ! $pinned ) : ?>
					<p class="sm__nomap">Building locations haven't been placed on the map yet.</p>
				<?php endif; ?>
			</div>
		</div>

		<ol class="sm__list">
			<?php foreach ( $buildings as $i => $b ) :
				// The usual type of clean for this building is said once, not on every line;
				// only a different one (a Saturday deep clean, say) is spelled out.
				$types  = array_count_values( array_column( array_merge( $b['next'], $b['recent'] ), 'type' ) );
				arsort( $types );
				$common = $types ? (string) key( $types ) : '';
				$odd    = function ( $c ) use ( $common ) {
					return $c['type'] !== $common ? ' <span class="sm-b__muted">· ' . esc_html( $c['type'] ) . '</span>' : '';
				};
				?>
				<li class="sm-b" id="sm-b-<?php echo esc_attr( $b['id'] ); ?>" data-building="<?php echo esc_attr( $b['id'] ); ?>">
					<button type="button" class="sm-b__head" aria-expanded="<?php echo 0 === $i ? 'true' : 'false'; ?>" aria-controls="sm-b-body-<?php echo esc_attr( $b['id'] ); ?>">
						<span class="sm-b__pin" aria-hidden="true"><?php echo (int) $i + 1; ?></span>
						<span class="sm-b__name"><?php echo esc_html( $b['name'] ); ?></span>
						<span class="sm-b__when">
							<?php
							if ( $b['next'] ) {
								echo esc_html( 'Next: ' . $b['next'][0]['date'] . ', ' . strtok( $b['next'][0]['time'], '–' ) );
							} else {
								echo 'No cleans booked';
							}
							?>
						</span>
					</button>

					<div class="sm-b__body" id="sm-b-body-<?php echo esc_attr( $b['id'] ); ?>" <?php echo 0 === $i ? '' : 'hidden'; ?>>
						<div class="sm-b__col">
						<div class="sm-b__freq">
							<span class="sm-b__label">How often</span>
							<?php if ( $b['lines'] ) : ?>
								<ul class="sm-b__lines">
									<?php foreach ( $b['lines'] as $line ) : ?><li><?php echo esc_html( $line ); ?></li><?php endforeach; ?>
								</ul>
							<?php else : ?>
								<p class="sm-b__muted">No regular pattern yet<?php echo $b['next'] ? ' — see the cleans below' : ''; ?>.</p>
							<?php endif; ?>
							<?php if ( $b['rate'] ) : ?><p class="sm-b__rate"><?php echo esc_html( $b['rate'] . ( $common ? ' · ' . strtolower( $common ) : '' ) ); ?></p><?php endif; ?>
						</div>

						<?php if ( $b['notes'] ) : ?>
							<p class="sm-b__notes"><?php echo esc_html( $b['notes'] ); ?></p>
						<?php endif; ?>
						</div>

						<div class="sm-b__col">
						<?php if ( $b['next'] ) : ?>
							<span class="sm-b__label">Coming up</span>
							<ul class="sm-b__cleans">
								<?php foreach ( $b['next'] as $c ) : ?>
									<li><strong><?php echo esc_html( $c['date'] ); ?></strong> <?php echo esc_html( $c['time'] ); ?><?php echo $odd( $c ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the closure. ?></li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						</div>

						<div class="sm-b__col">
						<?php if ( $b['recent'] ) : ?>
							<span class="sm-b__label">Recently cleaned</span>
							<ul class="sm-b__cleans">
								<?php foreach ( $b['recent'] as $c ) : ?>
									<li>
										<strong><?php echo esc_html( $c['date'] ); ?></strong><?php echo $odd( $c ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the closure. ?>
										<?php if ( ! empty( $c['report'] ) ) : ?> · <a href="<?php echo esc_url( $c['report'] ); ?>">Report</a><?php endif; ?>
									</li>
								<?php endforeach; ?>
							</ul>
						<?php endif; ?>

						<?php if ( $b['request_url'] ) : ?>
							<a class="sm-b__req" href="<?php echo esc_url( $b['request_url'] ); ?>">Request a clean for <?php echo esc_html( $b['name'] ); ?></a>
						<?php endif; ?>
						</div>
					</div>
				</li>
			<?php endforeach; ?>
		</ol>
	</div>

	<?php if ( $site['unlinked'] ) : ?>
		<div class="aff-card sm__other">
			<span class="aff-card__eyebrow">Other cleans at your school</span>
			<p class="sm-b__muted">Booked but not yet linked to a building.</p>
			<ul class="sm-b__cleans">
				<?php foreach ( $site['unlinked'] as $c ) : ?>
					<li><strong><?php echo esc_html( $c['date'] ); ?></strong> <?php echo esc_html( $c['time'] ); ?> <span class="sm-b__muted">· <?php echo esc_html( $c['type'] ); ?></span></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
</div>
