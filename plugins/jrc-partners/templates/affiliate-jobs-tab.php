<?php
/**
 * Partner tab: referred jobs, paginated.
 *
 * Rendered inside WooCommerce My Account. Expects $aff from jrc_affiliate_data().
 *
 * @package jrc-partners
 */
if ( ! defined( 'ABSPATH' ) || empty( $aff ) ) { return; }

$profile    = ! empty( $aff['profile'] ) ? $aff['profile'] : jrc_affiliate_profile();
$show_money = jrc_affiliate_show_money();
// A Single Owner holiday let books cleans for their own property — nothing is "referred".
$jobs_heading = 'basic' === ( $profile['view'] ?? '' ) ? 'Cleans at your property' : 'Referred jobs';
$reward_head  = 'commission' === ( $profile['payout'] ?? '' ) ? 'Commission' : 'Your reward';
?>
<div class="aff-dash">
	<?php
	// 15 a page. A branch with a couple of years of history had every row on one screen,
	// which buried the recent work the partner actually came to look at.
	$per_page   = (int) apply_filters( 'jrc_affiliate_jobs_per_page', 15 );
	$all_jobs   = $aff['jobs'];

	// Newest first, before paging — otherwise page 2 is a different sort order.
	usort( $all_jobs, function ( $a, $b ) { return strtotime( $b['date'] ?? '' ) <=> strtotime( $a['date'] ?? '' ); } );

	$jobs_total = count( $all_jobs );
	$jobs_pages = max( 1, (int) ceil( $jobs_total / $per_page ) );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only pager.
	$jobs_page  = isset( $_GET['jobs'] ) ? max( 1, min( $jobs_pages, (int) $_GET['jobs'] ) ) : 1;
	$rows       = array_slice( $all_jobs, ( $jobs_page - 1 ) * $per_page, $per_page );

	// Status history for the fifteen rows on this page, in one query. Fetched after
	// paging, not before — there is no point loading history for jobs nobody can see.
	$row_events = function_exists( 'jrc_job_events_for' )
		? jrc_job_events_for( wp_list_pluck( $rows, 'id' ) )
		: array();

	$jobs_base = jrc_affiliate_tab_url( isset( $tab_slug ) ? $tab_slug : 'referred-jobs' );
	$jobs_link  = function ( $n ) use ( $jobs_base ) {
		// #jobs so paging returns the reader to the table, not the top of the dashboard.
		return esc_url( ( 1 === $n ? remove_query_arg( 'jobs', $jobs_base ) : add_query_arg( 'jobs', $n, $jobs_base ) ) . '#jobs' );
	};
	?>
	<div class="aff-jobs" id="jobs">
		<h3><?php echo esc_html( $jobs_heading ); ?></h3>
		<?php if ( $all_jobs ) : ?>
			<div class="aff-jobs__bar">
				<p class="aff-jobs__count">
					<?php if ( $jobs_pages > 1 ) : ?>
						Showing <?php echo esc_html( ( $jobs_page - 1 ) * $per_page + 1 ); ?>&ndash;<?php echo esc_html( ( $jobs_page - 1 ) * $per_page + count( $rows ) ); ?>
						of <?php echo esc_html( number_format_i18n( $jobs_total ) ); ?>, newest first.
					<?php else : ?>
						<?php echo esc_html( number_format_i18n( $jobs_total ) ); ?> <?php echo 1 === $jobs_total ? 'job' : 'jobs'; ?>, newest first.
					<?php endif; ?>
				</p>

				<?php // The office has had a CSV export since the Activity screen was built; this is the same data, scoped to the account asking for it. ?>
				<a class="button button--outline button--sm aff-jobs__export" href="<?php echo esc_url( jrc_partner_csv_url() ); ?>">
					Export as CSV
				</a>
			</div>
			<div class="aff-jobs__scroll">
				<table class="aff-jobs__table">
					<thead><tr><th>Date</th><th>Job</th><th>Address</th><th>Scope</th><?php if ( $show_money ) : ?><th>Value</th><th><?php echo esc_html( $reward_head ); ?></th><?php endif; ?><th>Status</th></tr></thead>
					<tbody>
						<?php
						foreach ( $rows as $j ) :
							$d = ! empty( $j['date'] ) ? date_i18n( 'j M Y', strtotime( $j['date'] ) ) : '—';
							$status = $j['status'] ?: '—';
							$sclass = sanitize_html_class( strtolower( str_replace( ' ', '-', $status ) ) );
						?>
							<?php
							// Reward is only realised on completed jobs, and only once the
							// tier carries a rate — Tier 1 is client-savings only.
							$is_done   = 0 === strcasecmp( trim( (string) $status ), 'Completed' );
							$job_value = (float) preg_replace( '/[^0-9.]/', '', (string) ( $j['value'] ?? '' ) );
							$job_reward = ( $is_done && $aff['aff_pct'] > 0 && $job_value > 0 )
								? jrc_affiliate_money( $job_value * $aff['aff_pct'] / 100 )
								: '—';
							?>
							<tr>
								<td><?php echo esc_html( $d ); ?></td>
								<td><?php echo esc_html( $j['type'] ?: '—' ); ?></td>
								<td class="aff-jobs__addr"><?php echo esc_html( ( $j['address'] ?? '' ) ?: '—' ); ?></td>
								<?php // Scope is multi-line free text, so preserve the line breaks the admin typed. ?>
								<td class="aff-jobs__scope"><?php echo ( $j['scope'] ?? '' ) ? nl2br( esc_html( $j['scope'] ) ) : '—'; ?></td>
								<?php if ( $show_money ) : ?>
									<td><?php echo esc_html( $j['value'] ?: '—' ); ?></td>
								<?php endif; ?>
								<?php if ( $show_money ) : ?>
									<td<?php echo ( '—' !== $job_reward ) ? ' class="is-accent"' : ''; ?>><?php echo esc_html( $job_reward ); ?></td>
								<?php endif; ?>
								<td>
									<span class="aff-status aff-status--<?php echo esc_attr( $sclass ); ?>"><?php echo esc_html( $status ); ?></span>
									<?php
									// <details> rather than a modal: this is a table of fifteen rows
									// and a partner comparing two of them wants both open at once,
									// which a dialog cannot do. It also needs no JavaScript.
									$row_timeline = function_exists( 'jrc_job_timeline_html' )
										? jrc_job_timeline_html( $row_events[ (int) ( $j['id'] ?? 0 ) ] ?? array() )
										: '';
									if ( $row_timeline ) : ?>
										<details class="aff-jobs__history">
											<summary>History</summary>
											<?php echo $row_timeline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in jrc_job_timeline_html(). ?>
										</details>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>

			<?php if ( $jobs_pages > 1 ) : ?>
				<nav class="aff-pager" aria-label="Referred jobs pages">
					<?php if ( $jobs_page > 1 ) : ?>
						<a class="aff-pager__step" href="<?php echo $jobs_link( $jobs_page - 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" rel="prev">&larr; Newer</a>
					<?php else : ?>
						<span class="aff-pager__step is-off">&larr; Newer</span>
					<?php endif; ?>

					<ol class="aff-pager__pages">
						<?php
						// Windowed: first, last, and a couple either side of the current page,
						// so a long history doesn't produce a wall of numbers.
						$shown = array();
						for ( $n = 1; $n <= $jobs_pages; $n++ ) {
							if ( $n <= 2 || $n > $jobs_pages - 2 || abs( $n - $jobs_page ) <= 1 ) {
								$shown[] = $n;
							}
						}
						$prev_n = 0;
						foreach ( $shown as $n ) :
							if ( $prev_n && $n - $prev_n > 1 ) : ?>
								<li class="aff-pager__gap" aria-hidden="true">&hellip;</li>
							<?php endif;
							$prev_n = $n; ?>
							<li>
								<?php if ( $n === $jobs_page ) : ?>
									<span class="aff-pager__page is-current" aria-current="page"><?php echo esc_html( $n ); ?></span>
								<?php else : ?>
									<a class="aff-pager__page" href="<?php echo $jobs_link( $n ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo esc_html( $n ); ?></a>
								<?php endif; ?>
							</li>
						<?php endforeach; ?>
					</ol>

					<?php if ( $jobs_page < $jobs_pages ) : ?>
						<a class="aff-pager__step" href="<?php echo $jobs_link( $jobs_page + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" rel="next">Older &rarr;</a>
					<?php else : ?>
						<span class="aff-pager__step is-off">Older &rarr;</span>
					<?php endif; ?>
				</nav>
			<?php endif; ?>

		<?php else : ?>
			<p class="aff-jobs__empty">No referred jobs logged yet. Once a clean booked with your code is completed, it'll appear here.</p>
		<?php endif; ?>
	</div>

</div>
