<?php
/**
 * Partner tab: the report library.
 *
 * Every clean gets a PDF photo report — what was cleaned, with pictures. This is where
 * the branch collects theirs. Links go through jrc_report_url(), never at the file, so
 * ownership is checked before anything is served.
 *
 * Filtering happens in SQL (jrc_reports_query) rather than by slicing an array of every
 * report the partner has ever had. Once a branch is two years in, the question they
 * arrive with is "where is the one for 14 Bridge Street", and paging through fifteen at
 * a time is not an answer.
 *
 * @package jrc-partners
 */
if ( ! defined( 'ABSPATH' ) || empty( $aff ) ) { return; }

$uid       = get_current_user_id();
$last_seen = jrc_reports_last_seen( $uid );

// Counted before the tab is marked as seen, or nothing is ever new. Counted in SQL
// across the whole library, not just this page, so a filtered view still tells the
// partner how many they have not looked at.
$new_total = jrc_reports_new_count( $uid );

// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only filters.
$search   = isset( $_GET['rq'] ) ? sanitize_text_field( wp_unslash( $_GET['rq'] ) ) : '';
$month    = isset( $_GET['rm'] ) ? sanitize_text_field( wp_unslash( $_GET['rm'] ) ) : '';
$job_type = isset( $_GET['rt'] ) ? sanitize_text_field( wp_unslash( $_GET['rt'] ) ) : '';
$paged    = isset( $_GET['r'] ) ? max( 1, (int) $_GET['r'] ) : 1;
// phpcs:enable WordPress.Security.NonceVerification.Recommended

$months    = jrc_reports_months( $uid );
$all_types = jrc_reports_job_types( $uid );

// A month or type that isn't in this partner's own data is dropped rather than passed
// to the query — it can only come from a hand-edited URL, and silently returning
// nothing reads as a bug.
if ( '' !== $month && ! isset( $months[ $month ] ) ) { $month = ''; }
if ( '' !== $job_type && ! in_array( $job_type, $all_types, true ) ) { $job_type = ''; }

$per_page  = (int) apply_filters( 'jrc_affiliate_reports_per_page', 15 );
$result    = jrc_reports_query( $uid, array(
	'search'   => $search,
	'month'    => $month,
	'job_type' => $job_type,
	'paged'    => $paged,
	'per_page' => $per_page,
) );

$rows      = $result['rows'];
$total     = $result['total'];
$pages     = $result['pages'];
$paged     = $result['paged'];
$filtering = ( '' !== $search || '' !== $month || '' !== $job_type );

// Is there anything at all, filters aside? Distinguishes "no reports yet" from
// "nothing matched", which need very different copy.
$library_empty = ! $filtering && ! $total;

$base = jrc_affiliate_tab_url( isset( $tab_slug ) ? $tab_slug : 'reports' );

/** Pager link that keeps the current filters. */
$link = function ( $n ) use ( $base, $search, $month, $job_type ) {
	$args = array_filter( array(
		'rq' => $search,
		'rm' => $month,
		'rt' => $job_type,
		'r'  => $n > 1 ? $n : '',
	), function ( $v ) { return '' !== $v && null !== $v; } );

	return esc_url( ( $args ? add_query_arg( $args, $base ) : $base ) . '#reports' );
};
?>
<div class="aff-dash">
	<div class="aff-card aff-reports" id="reports">
		<span class="aff-card__eyebrow">Your reports</span>

		<?php if ( $library_empty ) : ?>

			<p class="aff-reports__empty">
				No reports yet. After each clean we issue a photo report showing what was covered,
				and it will appear here for you to download and forward on.
			</p>

		<?php else : ?>

			<p class="aff-reports__intro">
				A photo report is issued after every clean, showing what was covered.
				<?php if ( $new_total ) : ?>
					<strong><?php echo esc_html( $new_total ); ?> new</strong> since you last looked.
				<?php endif; ?>
				Forward them straight to a landlord or tenant.
			</p>

			<?php // GET, so a filtered view is a URL the partner can bookmark or send to a colleague. ?>
			<form class="aff-filter" method="get" action="<?php echo esc_url( $base ); ?>" role="search">
				<div class="aff-filter__field aff-filter__field--grow">
					<label class="aff-filter__label" for="rep-q">Search</label>
					<input type="search" id="rep-q" name="rq" value="<?php echo esc_attr( $search ); ?>"
						placeholder="Address, service or notes…" />
				</div>

				<?php if ( $months ) : ?>
					<div class="aff-filter__field">
						<label class="aff-filter__label" for="rep-m">Month</label>
						<select id="rep-m" name="rm">
							<option value="">Any month</option>
							<?php foreach ( $months as $ym => $label ) : ?>
								<option value="<?php echo esc_attr( $ym ); ?>" <?php selected( $month, $ym ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<?php if ( count( $all_types ) > 1 ) : ?>
					<div class="aff-filter__field">
						<label class="aff-filter__label" for="rep-t">Service</label>
						<select id="rep-t" name="rt">
							<option value="">Any service</option>
							<?php foreach ( $all_types as $type ) : ?>
								<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $job_type, $type ); ?>>
									<?php echo esc_html( $type ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</div>
				<?php endif; ?>

				<div class="aff-filter__actions">
					<button type="submit" class="button button--primary button--sm">Filter</button>
					<?php if ( $filtering ) : ?>
						<a class="aff-filter__clear" href="<?php echo esc_url( $base . '#reports' ); ?>">Clear</a>
					<?php endif; ?>
				</div>
			</form>

			<?php if ( ! $total ) : ?>

				<p class="aff-reports__empty">
					Nothing matched that.
					<a href="<?php echo esc_url( $base . '#reports' ); ?>">Show all reports</a>.
				</p>

			<?php else : ?>

				<div class="aff-reports__bar">
					<p class="aff-reports__count">
						<?php if ( $pages > 1 ) : ?>
							Showing <?php echo esc_html( ( $paged - 1 ) * $per_page + 1 ); ?>&ndash;<?php echo esc_html( ( $paged - 1 ) * $per_page + count( $rows ) ); ?>
							of <?php echo esc_html( number_format_i18n( $total ) ); ?>
						<?php else : ?>
							<?php echo esc_html( number_format_i18n( $total ) ); ?>
							<?php echo 1 === $total ? 'report' : 'reports'; ?>
						<?php endif; ?>
						<?php echo $filtering ? 'matching your filters.' : ', newest first.'; ?>
					</p>

					<?php
					// Bundling is offered for a month, because that is the unit a
					// lettings manager assembles a pack in. "Everything" is only offered
					// when there is little enough of it to be a sensible download.
					if ( jrc_can_zip() ) :
						$zip_month = $month ? $month : '';
						?>
						<?php if ( $zip_month ) : ?>
							<a class="button button--outline button--sm aff-reports__zip"
								href="<?php echo esc_url( jrc_reports_zip_url( $zip_month ) ); ?>">
								Download <?php echo esc_html( $months[ $zip_month ] ); ?> as a zip
							</a>
						<?php elseif ( $total <= 60 && ! $filtering ) : ?>
							<a class="button button--outline button--sm aff-reports__zip"
								href="<?php echo esc_url( jrc_reports_zip_url( 'all' ) ); ?>">
								Download all <?php echo esc_html( $total ); ?> as a zip
							</a>
						<?php elseif ( $months ) : ?>
							<span class="aff-reports__ziphint">Pick a month above to download it as a single zip.</span>
						<?php endif; ?>
					<?php endif; ?>
				</div>

				<ul class="aff-reports__list">
					<?php foreach ( $rows as $r ) :
						$is_new = jrc_report_is_new( $r, $last_seen ); ?>
						<li class="aff-reports__item<?php echo $is_new ? ' is-new' : ''; ?>">
							<span class="aff-reports__icon" aria-hidden="true">PDF</span>

							<span class="aff-reports__meta">
								<strong class="aff-reports__title">
									<?php echo esc_html( $r->job_type ?: 'Clean' ); ?>
									<?php if ( $is_new ) : ?><span class="aff-reports__new">New</span><?php endif; ?>
								</strong>
								<?php if ( $r->address ) : ?>
									<span class="aff-reports__addr"><?php echo esc_html( $r->address ); ?></span>
								<?php endif; ?>
								<span class="aff-reports__date">
									<?php echo esc_html( $r->job_date ? date_i18n( 'j F Y', strtotime( $r->job_date ) ) : 'Date not recorded' ); ?>
									<?php if ( $r->report_uploaded ) : ?>
										&middot; added <?php echo esc_html( human_time_diff( strtotime( $r->report_uploaded ), current_time( 'timestamp' ) ) ); ?> ago
									<?php endif; ?>
								</span>
							</span>

							<a class="button button--outline aff-reports__dl" href="<?php echo esc_url( jrc_report_url( $r->id ) ); ?>">
								Download
							</a>
						</li>
					<?php endforeach; ?>
				</ul>

				<?php if ( $pages > 1 ) : ?>
					<nav class="aff-pager" aria-label="Report pages">
						<?php if ( $paged > 1 ) : ?>
							<a class="aff-pager__step" href="<?php echo $link( $paged - 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" rel="prev">&larr; Newer</a>
						<?php else : ?>
							<span class="aff-pager__step is-off">&larr; Newer</span>
						<?php endif; ?>

						<ol class="aff-pager__pages">
							<?php
							// Windowed, like the jobs pager: first two, last two, and a
							// couple either side of where you are.
							$shown = array();
							for ( $n = 1; $n <= $pages; $n++ ) {
								if ( $n <= 2 || $n > $pages - 2 || abs( $n - $paged ) <= 1 ) {
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
									<?php if ( $n === $paged ) : ?>
										<span class="aff-pager__page is-current" aria-current="page"><?php echo esc_html( $n ); ?></span>
									<?php else : ?>
										<a class="aff-pager__page" href="<?php echo $link( $n ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>"><?php echo esc_html( $n ); ?></a>
									<?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ol>

						<?php if ( $paged < $pages ) : ?>
							<a class="aff-pager__step" href="<?php echo $link( $paged + 1 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" rel="next">Older &rarr;</a>
						<?php else : ?>
							<span class="aff-pager__step is-off">Older &rarr;</span>
						<?php endif; ?>
					</nav>
				<?php endif; ?>

			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
<?php
// Now that the badges have been rendered, this visit counts as seen.
jrc_reports_mark_seen( $uid );
