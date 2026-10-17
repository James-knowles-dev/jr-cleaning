<?php
/**
 * Partner tab: cleaning calendar (month grid of their own scheduled cleans).
 *
 * Rendered inside WooCommerce My Account. Expects $aff from jrc_affiliate_data().
 *
 * @package jrc-partners
 */
if ( ! defined( 'ABSPATH' ) || empty( $aff ) ) { return; }

$profile = ! empty( $aff['profile'] ) ? $aff['profile'] : jrc_affiliate_profile();
?>
<div class="aff-dash">
	<?php
	// Month, week or day. A partner with a handful of cleans thinks in months; a school
	// with ten buildings cleaned daily needs a week or a single day to be readable. The
	// grid collapses to a day-by-day agenda on narrow screens — see _affiliate.scss.
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only navigation.
	$uid       = get_current_user_id();
	$today_ymd = current_time( 'Y-m-d' );
	$cal_view  = isset( $_GET['cal_view'] ) && in_array( $_GET['cal_view'], array( 'month', 'week', 'day' ), true )
		? sanitize_key( $_GET['cal_view'] )
		: ( 'school' === ( $profile['view'] ?? '' ) ? 'week' : 'month' );
	$cal_anchor = isset( $_GET['cal_d'] ) ? strtotime( sanitize_text_field( wp_unslash( $_GET['cal_d'] ) ) ) : false;
	if ( ! $cal_anchor && isset( $_GET['cal'] ) ) {
		// Old ?cal=YYYY-MM links open that month.
		$cal_anchor = strtotime( sanitize_text_field( wp_unslash( $_GET['cal'] ) ) . '-01' );
		$cal_view   = isset( $_GET['cal_view'] ) ? $cal_view : 'month';
	}
	// phpcs:enable
	$cal_anchor = $cal_anchor ? $cal_anchor : strtotime( $today_ymd );

	if ( 'month' === $cal_view ) {
		$cal_start = strtotime( gmdate( 'Y-m-01', $cal_anchor ) );
		$cal_end   = strtotime( '+1 month -1 day', $cal_start );
		$cal_prev  = strtotime( '-1 month', $cal_start );
		$cal_next  = strtotime( '+1 month', $cal_start );
		$cal_label = date_i18n( 'F Y', $cal_start );
		$cal_what  = date_i18n( 'F', $cal_start );
	} elseif ( 'week' === $cal_view ) {
		$cal_start = strtotime( '-' . ( (int) gmdate( 'N', $cal_anchor ) - 1 ) . ' days', $cal_anchor );
		$cal_end   = strtotime( '+6 days', $cal_start );
		$cal_prev  = strtotime( '-7 days', $cal_start );
		$cal_next  = strtotime( '+7 days', $cal_start );
		$cal_label = date_i18n( 'j', $cal_start ) . ( gmdate( 'm', $cal_start ) !== gmdate( 'm', $cal_end ) ? ' ' . date_i18n( 'M', $cal_start ) : '' ) . ' – ' . date_i18n( 'j M Y', $cal_end );
		$cal_what  = 'this week';
	} else {
		$cal_start = $cal_anchor;
		$cal_end   = $cal_anchor;
		$cal_prev  = strtotime( '-1 day', $cal_anchor );
		$cal_next  = strtotime( '+1 day', $cal_anchor );
		$cal_label = date_i18n( 'l j F Y', $cal_anchor );
		$cal_what  = 'on ' . date_i18n( 'l j F', $cal_anchor );
	}

	$rows = jrc_jobs_query( array(
		'user_id'    => $uid,
		'date_field' => 'scheduled_date',
		'date_from'  => gmdate( 'Y-m-d', $cal_start ),
		'date_to'    => gmdate( 'Y-m-d', $cal_end ),
		'orderby'    => 'scheduled_date',
		'order'      => 'ASC',
		'limit'      => 2000,
	) );
	$byday = array();
	foreach ( $rows as $r ) {
		if ( $r->scheduled_date ) {
			$byday[ gmdate( 'Y-m-d', strtotime( $r->scheduled_date ) ) ][] = $r;
		}
	}
	$range_n = count( $rows );

	// Status history for every clean on screen, in one query rather than one per modal.
	$cal_events = function_exists( 'jrc_job_events_for' ) ? jrc_job_events_for( array_map( 'intval', wp_list_pluck( $rows, 'id' ) ) ) : array();

	$up_total = jrc_jobs_upcoming_count( $uid );
	$cal_base = jrc_affiliate_tab_url( isset( $tab_slug ) ? $tab_slug : 'cleaning-calendar' );
	$cal_link = function ( $args ) use ( $cal_base, $cal_view, $cal_anchor ) {
		$args = array_merge( array( 'cal_view' => $cal_view, 'cal_d' => gmdate( 'Y-m-d', $cal_anchor ) ), $args );
		return esc_url( add_query_arg( $args, $cal_base ) . '#calendar' );
	};

	$modals = '';
	// One clean: the chip in the grid / agenda, plus its detail panel (collected and
	// printed once after the table — a dialog inside a table cell cannot cover the page).
	$render_job = function ( $job ) use ( &$modals, $profile, $cal_events ) {
		$st  = sanitize_key( $job->status );
		$mid = 'job-' . (int) $job->id;
		$cal_building = ( 'school' === ( $profile['view'] ?? '' ) && function_exists( 'jrc_school_job_building_name' ) ) ? jrc_school_job_building_name( $job ) : '';
		$end = ( ! empty( $job->scheduled_end ) && strtotime( $job->scheduled_end ) > strtotime( $job->scheduled_date ) ) ? date_i18n( 'H:i', strtotime( $job->scheduled_end ) ) : '';
		// An anchor, not a button: :target opens the panel with no JavaScript at all.
		?>
		<a class="aff-cal__ev aff-cal__ev--<?php echo esc_attr( $st ); ?>"
			href="#<?php echo esc_attr( $mid ); ?>"
			aria-label="<?php echo esc_attr( sprintf(
				'%s at %s, %s. View details.',
				$job->job_type,
				date_i18n( 'H:i', strtotime( $job->scheduled_date ) ),
				$cal_building ? $cal_building : ( $job->address ? $job->address : 'address not recorded' )
			) ); ?>">
			<span class="aff-cal__time"><?php echo esc_html( date_i18n( 'H:i', strtotime( $job->scheduled_date ) ) . ( $end ? '–' . $end : '' ) ); ?></span>
			<span class="aff-cal__type"><?php echo esc_html( $job->job_type ); ?></span>
			<?php if ( $cal_building ) : ?>
				<span class="aff-cal__addr"><?php echo esc_html( $cal_building ); ?></span>
			<?php elseif ( $job->address ) : ?>
				<span class="aff-cal__addr"><?php echo esc_html( $job->address ); ?></span>
			<?php endif; ?>
			<span class="aff-cal__st"><?php echo esc_html( $job->status ); ?></span>
		</a>
		<?php
		ob_start();
		?>
		<div class="aff-modal" id="<?php echo esc_attr( $mid ); ?>" role="dialog" aria-modal="true" aria-labelledby="<?php echo esc_attr( $mid ); ?>-t">
								<a class="aff-modal__scrim" href="#calendar" tabindex="-1" aria-hidden="true"></a>
								<div class="aff-modal__box" role="document">
									<a class="aff-modal__close" href="#calendar" aria-label="Close">&times;</a>

									<span class="aff-modal__eyebrow aff-modal__eyebrow--<?php echo esc_attr( $st ); ?>"><?php echo esc_html( $job->status ); ?></span>
									<h3 class="aff-modal__title" id="<?php echo esc_attr( $mid ); ?>-t"><?php echo esc_html( $job->job_type ?: 'Clean' ); ?></h3>

									<dl class="aff-modal__meta">
										<dt>When</dt>
										<dd>
											<?php echo esc_html( date_i18n( 'l j F Y', strtotime( $job->scheduled_date ) ) ); ?>
											at <?php echo esc_html( date_i18n( 'H:i', strtotime( $job->scheduled_date ) ) ); ?>
											<?php
											// An end time only exists on jobs imported from the scheduling
											// system; a hand-entered job has a start and nothing else.
											if ( ! empty( $job->scheduled_end ) && strtotime( $job->scheduled_end ) > strtotime( $job->scheduled_date ) ) :
												?>
												&ndash; <?php echo esc_html( date_i18n( 'H:i', strtotime( $job->scheduled_end ) ) ); ?>
											<?php endif; ?>
										</dd>

										<?php if ( $job->address ) : ?>
											<dt>Property</dt>
											<dd><?php echo esc_html( $job->address ); ?></dd>
										<?php endif; ?>

										<?php if ( $job->job_date && $job->job_date !== gmdate( 'Y-m-d', strtotime( $job->scheduled_date ) ) ) : ?>
											<dt>Job date</dt>
											<dd><?php echo esc_html( date_i18n( 'j F Y', strtotime( $job->job_date ) ) ); ?></dd>
										<?php endif; ?>

										<?php if ( ! empty( $job->free_bonus ) && function_exists( 'jrc_free_bonus_label' ) ) : ?>
											<dt>Free bonus</dt>
											<dd><?php echo esc_html( jrc_free_bonus_label( $job->free_bonus ) ); ?></dd>
										<?php endif; ?>
									</dl>

									<?php if ( trim( (string) $job->scope ) !== '' ) : ?>
										<div class="aff-modal__scope">
											<h4>What the clean covered</h4>
											<p><?php echo nl2br( esc_html( $job->scope ) ); ?></p>
										</div>
									<?php endif; ?>

									<?php
									// The status badge at the top says where this clean is. The
									// timeline says how it got there, which is the actual question
									// behind "any news on Friday?".
									$job_timeline = function_exists( 'jrc_job_timeline_html' )
										? jrc_job_timeline_html( $cal_events[ (int) $job->id ] ?? array() )
										: '';
									if ( $job_timeline ) : ?>
										<div class="aff-modal__history">
											<h4>Progress</h4>
											<?php echo $job_timeline; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in jrc_job_timeline_html(). ?>
										</div>
									<?php endif; ?>

									<div class="aff-modal__actions">
										<?php if ( ! empty( $job->report_file ) ) : ?>
											<a class="button button--primary" href="<?php echo esc_url( jrc_report_url( $job->id ) ); ?>">Download report</a>
										<?php else : ?>
											<span class="aff-modal__noreport">The photo report for this clean isn't available yet.</span>
										<?php endif; ?>
										<?php // A single .ics for people who want one clean in their diary without subscribing to the lot. ?>
										<a class="button button--outline" href="<?php echo esc_url( jrc_ics_job_url( $job->id ) ); ?>" download>Add to calendar</a>
										<a class="button button--outline" href="#calendar">Close</a>
									</div>
								</div>
							</div>
		<?php
		$modals .= ob_get_clean();
	};
	?>
	<div class="aff-card aff-cal aff-cal--<?php echo esc_attr( $cal_view ); ?>" id="calendar">
		<div class="aff-cal__head">
			<div>
				<span class="aff-card__eyebrow">Your cleaning calendar</span>
				<p class="aff-cal__count">
					<?php if ( $range_n ) : ?>
						<strong><?php echo esc_html( number_format_i18n( $range_n ) ); ?></strong>
						<?php echo esc_html( ( 1 === $range_n ? 'clean' : 'cleans' ) . ( 'month' === $cal_view ? ' in ' : ' ' ) . $cal_what ); ?>
					<?php else : ?>
						Nothing scheduled <?php echo esc_html( ( 'month' === $cal_view ? 'in ' : '' ) . $cal_what ); ?>
					<?php endif; ?>
					<?php if ( $up_total ) : ?>
						&middot; <?php echo esc_html( number_format_i18n( $up_total ) ); ?> booked in overall
					<?php endif; ?>
				</p>
			</div>

			<div class="aff-cal__nav">
				<span class="aff-cal__views" role="group" aria-label="Calendar view">
					<?php foreach ( array( 'month' => 'Month', 'week' => 'Week', 'day' => 'Day' ) as $v => $vl ) : ?>
						<a class="aff-cal__view<?php echo $v === $cal_view ? ' is-current' : ''; ?>" href="<?php echo $cal_link( array( 'cal_view' => $v ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" <?php echo $v === $cal_view ? 'aria-current="page"' : ''; ?>><?php echo esc_html( $vl ); ?></a>
					<?php endforeach; ?>
				</span>
				<a class="aff-cal__step" href="<?php echo $cal_link( array( 'cal_d' => gmdate( 'Y-m-d', $cal_prev ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" rel="prev" aria-label="Previous <?php echo esc_attr( $cal_view ); ?>">&larr;</a>
				<span class="aff-cal__month"><?php echo esc_html( $cal_label ); ?></span>
				<a class="aff-cal__step" href="<?php echo $cal_link( array( 'cal_d' => gmdate( 'Y-m-d', $cal_next ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" rel="next" aria-label="Next <?php echo esc_attr( $cal_view ); ?>">&rarr;</a>
				<?php if ( $today_ymd < gmdate( 'Y-m-d', $cal_start ) || $today_ymd > gmdate( 'Y-m-d', $cal_end ) ) : ?>
					<a class="aff-cal__today" href="<?php echo $cal_link( array( 'cal_d' => $today_ymd ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">Today</a>
				<?php endif; ?>
			</div>
		</div>

		<?php if ( 'day' === $cal_view ) :
			$list = $byday[ gmdate( 'Y-m-d', $cal_anchor ) ] ?? array();
			?>
			<ol class="aff-cal__agenda">
				<?php foreach ( $list as $job ) : ?>
					<li><?php $render_job( $job ); ?></li>
				<?php endforeach; ?>
			</ol>

		<?php else :
			// Month and week share the Monday-first grid; the month shows three cleans a
			// day and links the rest through to that day.
			$grid_start = 'month' === $cal_view ? strtotime( '-' . ( (int) gmdate( 'N', $cal_start ) - 1 ) . ' days', $cal_start ) : $cal_start;
			$grid_end   = 'month' === $cal_view ? strtotime( '+' . ( 7 - (int) gmdate( 'N', $cal_end ) ) . ' days', $cal_end ) : $cal_end;
			$cap        = 'month' === $cal_view ? 3 : PHP_INT_MAX;
			?>
			<table class="aff-cal__grid">
				<caption class="screen-reader-text">Cleans scheduled <?php echo esc_html( $cal_label ); ?></caption>
				<thead>
					<tr>
						<?php for ( $i = 0; $i < 7; $i++ ) :
							$hd = strtotime( '+' . $i . ' days', $grid_start ); ?>
							<th scope="col"><abbr title="<?php echo esc_attr( date_i18n( 'l', $hd ) ); ?>"><?php echo esc_html( 'week' === $cal_view ? date_i18n( 'D j', $hd ) : date_i18n( 'D', $hd ) ); ?></abbr></th>
						<?php endfor; ?>
					</tr>
				</thead>
				<tbody>
					<tr>
					<?php
					$col = 0;
					for ( $ts = $grid_start; $ts <= $grid_end; $ts = strtotime( '+1 day', $ts ) ) {
						if ( 7 === $col ) {
							echo '</tr><tr>';
							$col = 0;
						}
						$ymd     = gmdate( 'Y-m-d', $ts );
						$outside = 'month' === $cal_view && gmdate( 'm', $ts ) !== gmdate( 'm', $cal_start );
						if ( $outside ) {
							echo '<td class="aff-cal__day is-blank"></td>';
							$col++;
							continue;
						}
						$list     = $byday[ $ymd ] ?? array();
						$is_today = $ymd === $today_ymd;
						$classes  = 'aff-cal__day' . ( $is_today ? ' is-today' : '' ) . ( $list ? '' : ' is-empty' );
						?>
						<td class="<?php echo esc_attr( $classes ); ?>">
							<a class="aff-cal__daynum" href="<?php echo $cal_link( array( 'cal_view' => 'day', 'cal_d' => $ymd ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>" aria-label="<?php echo esc_attr( date_i18n( 'l j F', $ts ) . ', ' . count( $list ) . ' ' . ( 1 === count( $list ) ? 'clean' : 'cleans' ) . '. Show the day.' ); ?>">
								<span class="aff-cal__dayname"><?php echo esc_html( date_i18n( 'D', $ts ) ); ?></span>
								<span class="aff-cal__n"><?php echo esc_html( gmdate( 'j', $ts ) ); ?></span>
								<?php if ( $is_today ) : ?><span class="screen-reader-text">(today)</span><?php endif; ?>
							</a>
							<?php
							foreach ( array_slice( $list, 0, $cap ) as $job ) {
								$render_job( $job );
							}
							if ( count( $list ) > $cap ) :
								?>
								<a class="aff-cal__more" href="<?php echo $cal_link( array( 'cal_view' => 'day', 'cal_d' => $ymd ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>">+<?php echo (int) ( count( $list ) - $cap ); ?> more</a>
							<?php endif; ?>
						</td>
						<?php
						$col++;
					}
					?>
					</tr>
				</tbody>
			</table>
		<?php endif; ?>

		<?php
		// Detail panels live outside the table so they can cover the page.
		echo $modals; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- assembled from escaped parts above.
		?>

		<?php if ( ! $range_n ) : ?>
			<p class="aff-cal__empty">No cleans scheduled <?php echo esc_html( ( 'month' === $cal_view ? 'this month' : $cal_what ) ); ?>. Once one is booked in it will appear here with its date and time.</p>
		<?php endif; ?>
	</div>

	<?php
	// ---- subscription -------------------------------------------------------
	// The grid above is only useful to someone who thinks to come and look at it.
	// This is the same data in the diary they already have open.
	$ical_url = jrc_ical_url( $uid );
	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
	$ical_new = isset( $_GET['ical'] ) && 'new' === $_GET['ical'];
	?>
	<div class="aff-card aff-subscribe" id="subscribe">
		<span class="aff-card__eyebrow">Put this in your own calendar</span>

		<?php if ( $ical_new ) : ?>
			<p class="aff-notice aff-notice--ok" role="status">
				<strong>New link created.</strong> The old one has stopped working — update any
				calendar you had subscribed to it.
			</p>
		<?php endif; ?>

		<p class="aff-subscribe__intro">
			Subscribe once and your cleans appear in Outlook, Google Calendar or Apple Calendar,
			updating on their own as the schedule changes. Use <em>subscribe to calendar</em> or
			<em>add by URL</em> in your calendar app — not <em>import</em>, which only copies the
			cleans that exist today.
		</p>

		<div class="aff-share__field">
			<label class="aff-share__label" for="aff-ical">Your calendar link</label>
			<div class="aff-share__row">
				<input type="text" id="aff-ical" class="aff-share__input" value="<?php echo esc_url( $ical_url ); ?>" readonly onclick="this.select()" />
				<button type="button" class="aff-code__copy aff-code__copy--sm" data-copy="<?php echo esc_attr( $ical_url ); ?>">Copy</button>
			</div>
		</div>

		<p class="aff-subscribe__warn">
			Treat this link like a password. It works without signing in — that is the only way
			calendar apps can read it — so anyone you send it to can see your cleans and the
			addresses they are at.
		</p>

		<form method="post" class="aff-subscribe__reset">
			<?php wp_nonce_field( 'jrc_ical_' . $uid, 'jrc_ical_nonce' ); ?>
			<input type="hidden" name="jrc_ical_regenerate" value="1" />
			<button type="submit" class="button button--outline button--sm">Create a new link</button>
			<span class="aff-subscribe__note">Stops the current link working, everywhere it has been used.</span>
		</form>
	</div>

</div>
