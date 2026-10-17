<?php
/**
 * Bulk report upload: attaching a morning's PDFs in one pass.
 *
 * Reports are the product the partner programme actually delivers, and until now the
 * only way to file one was to open a partner's profile, find the right job row among
 * however many they have, and use the file input on it. One report, one page load, one
 * scroll through a table. A day's cleans is a dozen of those, every day, and it is the
 * single biggest time cost in running the programme.
 *
 * This screen takes the whole folder at once, works out which job each file belongs to,
 * and shows the operator the pairings BEFORE anything is written. That confirm step is
 * not politeness — a misfiled report sends photographs of the inside of one customer's
 * home to a different estate agent, which is the worst thing this system could do. The
 * matcher is therefore allowed to be uncertain, and says so, rather than guessing.
 *
 * HOW MATCHING WORKS. Filenames in the wild look like:
 *
 *     2026-08-14 WR11 4RT end of tenancy.pdf
 *     14-08-2026_47-bridge-street_WR114RT.pdf
 *     WR11 4RT 14 Aug 26.pdf
 *
 * so both a date and a postcode are pulled out of the name and scored against the open
 * jobs. A postcode is worth more than a date because two cleans on one day are common
 * and two cleans at one postcode on one day are not. Both agreeing is a confident
 * match; one alone is offered but not pre-selected.
 *
 * STAGING. Files are held between the upload and the confirm in a private subdirectory
 * of the reports store, under generated names, indexed by a batch id in a transient.
 * Nothing in the confirm request supplies a path — it supplies a batch id and an index,
 * and the path is looked up. See jrc_bulk_staged_path() for why that matters.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** How long an un-confirmed batch survives before it is swept up. */
const JRC_BULK_BATCH_TTL = 2 * HOUR_IN_SECONDS;

/**
 * Where staged files live.
 *
 * Inside the reports directory, so it inherits the .htaccess deny and the reasoning
 * behind it — a staged report is exactly as sensitive as a filed one.
 */
function jrc_bulk_staging_dir() {
	$dir = trailingslashit( jrc_reports_dir() ) . '_staging';
	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}
	return $dir;
}

/** A staged batch, or null. */
function jrc_bulk_batch( $batch_id ) {
	$batch_id = sanitize_key( $batch_id );
	if ( ! $batch_id ) {
		return null;
	}
	$batch = get_transient( 'jrc_bulk_' . $batch_id );
	return is_array( $batch ) ? $batch : null;
}

/**
 * Resolve one staged file to an absolute path, or '' if it is not ours.
 *
 * The confirm request names a file by batch id and index — never by path. This function
 * is the only thing that turns those into a filename, it reads that filename from the
 * batch we wrote ourselves, and it still runs the result through basename() and a
 * realpath() containment check before handing it out.
 *
 * That is three redundant checks for a value the user cannot influence, which is the
 * point: the file this resolves to is about to be passed to jrc_job_report_store(),
 * which trusts its caller completely. This is the caller doing the work that trust
 * assumes.
 */
function jrc_bulk_staged_path( $batch, $index ) {
	$index = (int) $index;
	if ( empty( $batch['files'][ $index ]['stored'] ) ) {
		return '';
	}

	$dir  = jrc_bulk_staging_dir();
	$path = trailingslashit( $dir ) . basename( (string) $batch['files'][ $index ]['stored'] );

	$real     = realpath( $path );
	$real_dir = realpath( $dir );

	if ( ! $real || ! $real_dir || 0 !== strpos( $real, trailingslashit( $real_dir ) ) ) {
		return '';
	}

	return $real;
}

/** Delete a batch's staged files and its index. */
function jrc_bulk_discard( $batch_id ) {
	$batch = jrc_bulk_batch( $batch_id );
	if ( ! $batch ) {
		return;
	}
	foreach ( array_keys( (array) ( $batch['files'] ?? array() ) ) as $i ) {
		$path = jrc_bulk_staged_path( $batch, $i );
		if ( $path && file_exists( $path ) ) {
			@unlink( $path );
		}
	}
	delete_transient( 'jrc_bulk_' . sanitize_key( $batch_id ) );
}

/**
 * Sweep staged files whose batch has expired.
 *
 * A transient can vanish without warning (object cache flush, manual purge), which
 * would otherwise strand the PDFs on disk forever. Anything older than the TTL is
 * unreachable by definition, so age alone is a safe test.
 */
function jrc_bulk_sweep() {
	$dir = jrc_bulk_staging_dir();
	$now = time();

	foreach ( (array) glob( trailingslashit( $dir ) . '*.pdf' ) as $file ) {
		if ( is_file( $file ) && ( $now - filemtime( $file ) ) > JRC_BULK_BATCH_TTL ) {
			@unlink( $file );
		}
	}
}

// ---------------------------------------------------------------------------
// Reading a filename
// ---------------------------------------------------------------------------

/**
 * Pull a UK postcode out of a filename.
 *
 * Deliberately loose on the space: "WR114RT", "WR11 4RT" and "WR11-4RT" are all the
 * same postcode and all three turn up. Normalised to no-space uppercase so comparison
 * is a straight string match.
 *
 * @return string Normalised postcode, or ''.
 */
function jrc_bulk_extract_postcode( $text ) {
	$text = strtoupper( (string) $text );

	// Standard UK format. The [-_ ]? handles the separator that filenames pick up.
	//
	// NB: explicit lookarounds, NOT \b. Underscore is a word character, so \b does not
	// match between "_" and "W" — which silently broke "..._WR114RT.pdf", one of the
	// commonest shapes a filename takes and one of the examples in this file's own
	// header. The lookarounds below say what was actually meant: not adjacent to
	// another letter or digit.
	if ( preg_match( '/(?<![A-Z0-9])([A-Z]{1,2}[0-9][A-Z0-9]?)[-_ ]?([0-9][A-Z]{2})(?![A-Z0-9])/', $text, $m ) ) {
		return $m[1] . $m[2];
	}

	return '';
}

/**
 * Pull a date out of a filename.
 *
 * Ordered most-specific first. The four-digit-year forms are unambiguous; the
 * two-digit-year form is not, so it is tried last and assumed to be this century.
 *
 * NB: d-m-Y is assumed over m-d-Y throughout. This is a UK business whose staff write
 * dates the British way — but it means an American-formatted file would be read wrong,
 * which is exactly the sort of thing the confirm screen exists to catch.
 *
 * @return string Y-m-d, or ''.
 */
function jrc_bulk_extract_date( $text ) {
	$text = (string) $text;

	$try = function ( $y, $m, $d ) {
		$y = (int) $y;
		$m = (int) $m;
		$d = (int) $d;
		if ( $y < 100 ) {
			$y += 2000;
		}
		return checkdate( $m, $d, $y ) ? sprintf( '%04d-%02d-%02d', $y, $m, $d ) : '';
	};

	// (?<!\d) / (?!\d) rather than \b, for the same reason as the postcode above:
	// underscore is a word character, so \b never fires against "2026_47".

	// 2026-08-14 / 2026_08_14 / 20260814
	if ( preg_match( '/(?<!\d)(20\d{2})[-_.]?(\d{2})[-_.]?(\d{2})(?!\d)/', $text, $m ) ) {
		$d = $try( $m[1], $m[2], $m[3] );
		if ( $d ) { return $d; }
	}

	// 14-08-2026 / 14.08.2026 / 14_08_2026
	if ( preg_match( '/(?<!\d)(\d{1,2})[-_.](\d{1,2})[-_.](20\d{2})(?!\d)/', $text, $m ) ) {
		$d = $try( $m[3], $m[2], $m[1] );
		if ( $d ) { return $d; }
	}

	// 14 Aug 2026 / 14-August-26
	if ( preg_match( '/(?<![A-Za-z0-9])(\d{1,2})[-_. ]([A-Za-z]{3,9})[-_. ](\d{2,4})(?!\d)/', $text, $m ) ) {
		$month = date_parse( $m[2] )['month'] ?? 0;
		if ( $month ) {
			$d = $try( $m[3], $month, $m[1] );
			if ( $d ) { return $d; }
		}
	}

	// 14-08-26 — last, because 08-14-26 is equally plausible to a regex.
	if ( preg_match( '/(?<!\d)(\d{1,2})[-_.](\d{1,2})[-_.](\d{2})(?!\d)/', $text, $m ) ) {
		$d = $try( $m[3], $m[2], $m[1] );
		if ( $d ) { return $d; }
	}

	return '';
}

/**
 * Jobs a report could plausibly belong to.
 *
 * Everything completed or booked in a generous window either side of today, with the
 * partner resolved. Fetched once per batch, not once per file.
 */
function jrc_bulk_candidate_jobs() {
	$rows = jrc_jobs_query( array(
		'date_field' => 'job_date',
		'date_from'  => gmdate( 'Y-m-d', strtotime( '-18 months', current_time( 'timestamp' ) ) ),
		'date_to'    => gmdate( 'Y-m-d', strtotime( '+1 month', current_time( 'timestamp' ) ) ),
		'orderby'    => 'job_date',
		'order'      => 'DESC',
		'limit'      => 2000,
	) );

	// Jobs with no job_date yet — a clean done this morning and not written up — are
	// invisible to a job_date range query but are exactly what a report arrives for.
	$undated = jrc_jobs_query( array(
		'status'  => '',
		'orderby' => 'id',
		'order'   => 'DESC',
		'limit'   => 500,
	) );

	$by_id = array();
	foreach ( array_merge( (array) $rows, (array) $undated ) as $r ) {
		$by_id[ (int) $r->id ] = $r;
	}

	return $by_id;
}

/**
 * Score every candidate job against one filename and return the best matches.
 *
 * @return array{best:?object,score:int,confident:bool,why:string,alternatives:array}
 */
function jrc_bulk_match_file( $filename, $jobs ) {
	$postcode = jrc_bulk_extract_postcode( $filename );
	$date     = jrc_bulk_extract_date( $filename );

	$scored = array();

	foreach ( $jobs as $job ) {
		$score = 0;
		$why   = array();

		if ( $postcode ) {
			$job_pc = jrc_bulk_extract_postcode( $job->address );
			if ( $job_pc && $job_pc === $postcode ) {
				$score += 60;
				$why[]  = 'postcode';
			}
		}

		if ( $date && $job->job_date && gmdate( 'Y-m-d', strtotime( $job->job_date ) ) === $date ) {
			$score += 35;
			$why[]  = 'date';
		}

		// A scheduled date is weaker evidence than the job date but better than nothing
		// for a clean that has not been written up yet.
		if ( $date && ! $job->job_date && $job->scheduled_date
			&& gmdate( 'Y-m-d', strtotime( $job->scheduled_date ) ) === $date ) {
			$score += 25;
			$why[]  = 'scheduled date';
		}

		// A distinctive word from the address appearing in the filename. Short words are
		// skipped — "the", "road" and "flat" match everything and mean nothing.
		if ( $job->address ) {
			$slug_name = strtolower( preg_replace( '/[^a-z0-9]+/i', ' ', $filename ) );
			foreach ( preg_split( '/[^a-z0-9]+/i', strtolower( $job->address ) ) as $word ) {
				if ( strlen( $word ) >= 5 && false !== strpos( $slug_name, $word ) ) {
					$score += 10;
					$why[]  = 'address';
					break;
				}
			}
		}

		if ( $score > 0 ) {
			$scored[] = array( 'job' => $job, 'score' => $score, 'why' => $why );
		}
	}

	usort( $scored, function ( $a, $b ) {
		return $b['score'] <=> $a['score'];
	} );

	if ( ! $scored ) {
		return array( 'best' => null, 'score' => 0, 'confident' => false, 'why' => '', 'alternatives' => array() );
	}

	$top = $scored[0];

	// Confident means two independent signals agree AND nothing else scores as highly.
	// The tie check is the important half: two cleans at the same postcode on the same
	// day are rare, but that is precisely when a wrong guess does the damage.
	$tied      = isset( $scored[1] ) && $scored[1]['score'] === $top['score'];
	$confident = $top['score'] >= 85 && ! $tied;

	return array(
		'best'         => $top['job'],
		'score'        => $top['score'],
		'confident'    => $confident,
		'why'          => $tied ? 'More than one job matches equally well' : implode( ' + ', $top['why'] ),
		'alternatives' => array_slice( $scored, 0, 6 ),
	);
}

// ---------------------------------------------------------------------------
// Screen
// ---------------------------------------------------------------------------

add_action( 'admin_menu', function () {
	add_submenu_page(
		'jrc-partner',
		__( 'Bulk reports', 'jrc-partners' ),
		__( 'Bulk reports', 'jrc-partners' ),
		jrc_reports_cap(),
		'jrc-bulk-reports',
		'jrc_bulk_reports_screen'
	);
}, 25 );

/** Step 1 → 2: take the files, stage them, work out the matches. */
add_action( 'admin_post_jrc_bulk_stage', function () {
	if ( ! current_user_can( jrc_reports_cap() ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'jrc-partners' ), 403 );
	}
	check_admin_referer( 'jrc_bulk_stage' );

	jrc_bulk_sweep();

	if ( empty( $_FILES['reports']['name'] ) || ! is_array( $_FILES['reports']['name'] ) ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'jrc-bulk-reports', 'err' => 'none' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	$dir     = jrc_bulk_staging_dir();
	$files   = array();
	$skipped = 0;

	foreach ( $_FILES['reports']['name'] as $i => $original ) {
		$tmp = $_FILES['reports']['tmp_name'][ $i ] ?? '';

		if ( '' === $original || ! $tmp || ! is_uploaded_file( $tmp ) || ! empty( $_FILES['reports']['error'][ $i ] ) ) {
			$skipped++;
			continue;
		}

		// Sniffed, not trusted from the extension — the same rule as the single-file
		// path, applied before anything is written anywhere.
		$check = wp_check_filetype_and_ext( $tmp, $original, jrc_report_allowed_types() );
		if ( empty( $check['ext'] ) || 'pdf' !== strtolower( $check['ext'] ) ) {
			$skipped++;
			continue;
		}

		$stored = wp_generate_password( 32, false, false ) . '.pdf';
		if ( ! @move_uploaded_file( $tmp, trailingslashit( $dir ) . $stored ) ) {
			$skipped++;
			continue;
		}
		@chmod( trailingslashit( $dir ) . $stored, 0640 );

		$files[] = array(
			// Kept only for display and for the report's friendly name. Never used to
			// build a path.
			'original' => sanitize_file_name( $original ),
			'stored'   => $stored,
		);
	}

	if ( ! $files ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'jrc-bulk-reports', 'err' => 'none' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	$batch_id = wp_generate_password( 16, false, false );
	set_transient( 'jrc_bulk_' . $batch_id, array(
		'files'   => $files,
		'skipped' => $skipped,
		'by'      => get_current_user_id(),
		'at'      => time(),
	), JRC_BULK_BATCH_TTL );

	wp_safe_redirect( add_query_arg( array( 'page' => 'jrc-bulk-reports', 'batch' => $batch_id ), admin_url( 'admin.php' ) ) );
	exit;
} );

/** Step 2 → 3: file the confirmed pairings. */
add_action( 'admin_post_jrc_bulk_commit', function () {
	if ( ! current_user_can( jrc_reports_cap() ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'jrc-partners' ), 403 );
	}
	check_admin_referer( 'jrc_bulk_commit' );

	$batch_id = isset( $_POST['batch'] ) ? sanitize_key( wp_unslash( $_POST['batch'] ) ) : '';
	$batch    = jrc_bulk_batch( $batch_id );

	if ( ! $batch ) {
		wp_safe_redirect( add_query_arg( array( 'page' => 'jrc-bulk-reports', 'err' => 'expired' ), admin_url( 'admin.php' ) ) );
		exit;
	}

	// A batch belongs to whoever uploaded it. Two people filing reports at once would
	// otherwise be able to commit each other's files.
	if ( (int) ( $batch['by'] ?? 0 ) !== get_current_user_id() ) {
		wp_die( esc_html__( 'That batch belongs to someone else.', 'jrc-partners' ), 403 );
	}

	$pairs  = isset( $_POST['job'] ) && is_array( $_POST['job'] ) ? wp_unslash( $_POST['job'] ) : array();
	$filed  = 0;
	$failed = 0;

	foreach ( $pairs as $index => $job_id ) {
		$job_id = (int) $job_id;
		$index  = (int) $index;

		if ( ! $job_id ) {
			continue;   // "Skip this file".
		}

		$path = jrc_bulk_staged_path( $batch, $index );
		if ( ! $path ) {
			$failed++;
			continue;
		}

		$name = $batch['files'][ $index ]['original'] ?? 'report.pdf';

		// 'move', not 'copy': the staged file has no further purpose once filed, and
		// leaving it behind would keep a second copy of a private document on disk.
		$res = jrc_job_report_store( $job_id, $path, $name, 'move' );

		if ( is_wp_error( $res ) ) {
			$failed++;
			continue;
		}
		$filed++;
	}

	// Whatever is left is a file the operator chose to skip. Discarding the batch
	// removes those too, which is the intent — they can be re-uploaded.
	jrc_bulk_discard( $batch_id );

	wp_safe_redirect( add_query_arg(
		array( 'page' => 'jrc-bulk-reports', 'filed' => $filed, 'failed' => $failed ),
		admin_url( 'admin.php' )
	) );
	exit;
} );

/** Abandon a batch without filing anything. */
add_action( 'admin_post_jrc_bulk_discard', function () {
	if ( ! current_user_can( jrc_reports_cap() ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'jrc-partners' ), 403 );
	}
	check_admin_referer( 'jrc_bulk_discard' );

	$batch_id = isset( $_POST['batch'] ) ? sanitize_key( wp_unslash( $_POST['batch'] ) ) : '';
	$batch    = jrc_bulk_batch( $batch_id );

	if ( $batch && (int) ( $batch['by'] ?? 0 ) === get_current_user_id() ) {
		jrc_bulk_discard( $batch_id );
	}

	wp_safe_redirect( add_query_arg( array( 'page' => 'jrc-bulk-reports' ), admin_url( 'admin.php' ) ) );
	exit;
} );

function jrc_bulk_reports_screen() {
	if ( ! current_user_can( jrc_reports_cap() ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
	$batch_id = isset( $_GET['batch'] ) ? sanitize_key( wp_unslash( $_GET['batch'] ) ) : '';
	$batch    = $batch_id ? jrc_bulk_batch( $batch_id ) : null;
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Bulk reports', 'jrc-partners' ); ?></h1>

		<?php
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notices.
		if ( isset( $_GET['filed'] ) ) :
			$filed  = (int) $_GET['filed'];
			$failed = (int) ( $_GET['failed'] ?? 0 );
			?>
			<div class="notice notice-<?php echo $failed ? 'warning' : 'success'; ?> is-dismissible">
				<p>
					<strong><?php echo (int) $filed; ?></strong> report<?php echo 1 === $filed ? '' : 's'; ?> filed.
					<?php if ( $failed ) : ?>
						<strong><?php echo (int) $failed; ?></strong> could not be saved and were not filed.
					<?php endif; ?>
					Partners with notifications on have been emailed.
				</p>
			</div>
		<?php endif; ?>

		<?php if ( isset( $_GET['err'] ) && 'none' === $_GET['err'] ) : ?>
			<div class="notice notice-error is-dismissible"><p>No usable PDFs in that upload. Reports must be PDF files.</p></div>
		<?php elseif ( isset( $_GET['err'] ) && 'expired' === $_GET['err'] ) : ?>
			<div class="notice notice-error is-dismissible"><p>That batch expired before it was confirmed. Upload the files again.</p></div>
		<?php endif; ?>
		<?php // phpcs:enable WordPress.Security.NonceVerification.Recommended ?>

		<?php if ( ! $batch ) : ?>

			<p class="description" style="max-width:70em;">
				Drop in a whole folder of photo reports. Each file is matched to a job by the
				<strong>postcode</strong> and <strong>date</strong> in its filename, and you get to
				check every pairing before anything is filed. Nothing is sent to a partner until
				you confirm.
			</p>

			<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'jrc_bulk_stage' ); ?>
				<input type="hidden" name="action" value="jrc_bulk_stage" />

				<div id="jrc-drop" class="jrc-drop">
					<p><strong>Drop PDFs here</strong>, or choose them below.</p>
					<input type="file" name="reports[]" id="jrc-bulk-files" accept="application/pdf" multiple />
					<p class="description" id="jrc-drop-count"></p>
				</div>

				<p style="margin-top:16px;">
					<button class="button button-primary">Match these files</button>
				</p>
			</form>

			<style>
				.jrc-drop {
					border: 2px dashed #c3c4c7; border-radius: 8px; background: #fff;
					padding: 30px; text-align: center; max-width: 52em; margin-top: 14px;
				}
				.jrc-drop.is-over { border-color: #2271b1; background: #f0f6fc; }
			</style>
			<script>
			// Drag-and-drop is a convenience over the real file input, not a replacement:
			// the input still works on its own if this never runs.
			(function () {
				var zone  = document.getElementById('jrc-drop');
				var input = document.getElementById('jrc-bulk-files');
				var count = document.getElementById('jrc-drop-count');
				if (!zone || !input) { return; }

				function show() {
					count.textContent = input.files.length
						? input.files.length + ' file(s) selected.'
						: '';
				}

				['dragenter', 'dragover'].forEach(function (ev) {
					zone.addEventListener(ev, function (e) {
						e.preventDefault(); zone.classList.add('is-over');
					});
				});
				['dragleave', 'drop'].forEach(function (ev) {
					zone.addEventListener(ev, function (e) {
						e.preventDefault(); zone.classList.remove('is-over');
					});
				});

				zone.addEventListener('drop', function (e) {
					if (!e.dataTransfer || !e.dataTransfer.files.length) { return; }
					// DataTransfer is the only way to write to a file input's FileList.
					input.files = e.dataTransfer.files;
					show();
				});

				input.addEventListener('change', show);
			})();
			</script>

		<?php else : ?>

			<?php
			$jobs    = jrc_bulk_candidate_jobs();
			$matches = array();
			foreach ( (array) $batch['files'] as $i => $f ) {
				$matches[ $i ] = jrc_bulk_match_file( $f['original'], $jobs );
			}
			$confident = count( array_filter( $matches, function ( $m ) { return $m['confident']; } ) );
			?>

			<p class="description" style="max-width:70em;">
				<strong><?php echo count( $matches ); ?></strong> file(s) staged,
				<strong><?php echo (int) $confident; ?></strong> matched confidently and pre-selected.
				Check the rest before filing.
				<?php if ( ! empty( $batch['skipped'] ) ) : ?>
					<?php echo (int) $batch['skipped']; ?> file(s) were not PDFs and were ignored.
				<?php endif; ?>
			</p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'jrc_bulk_commit' ); ?>
				<input type="hidden" name="action" value="jrc_bulk_commit" />
				<input type="hidden" name="batch" value="<?php echo esc_attr( $batch_id ); ?>" />

				<table class="widefat striped">
					<thead>
						<tr>
							<th style="width:30%;">File</th>
							<th style="width:12%;">Read as</th>
							<th>Attach to job</th>
							<th style="width:14%;">Match</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( (array) $batch['files'] as $i => $f ) :
							$m  = $matches[ $i ];
							$pc = jrc_bulk_extract_postcode( $f['original'] );
							$dt = jrc_bulk_extract_date( $f['original'] );
							?>
							<tr>
								<td><code style="word-break:break-all;"><?php echo esc_html( $f['original'] ); ?></code></td>
								<td style="font-size:12px;color:#555;">
									<?php echo $pc ? esc_html( $pc ) : '<em>no postcode</em>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?><br />
									<?php echo $dt ? esc_html( date_i18n( 'j M Y', strtotime( $dt ) ) ) : '<em>no date</em>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								</td>
								<td>
									<select name="job[<?php echo (int) $i; ?>]" style="width:100%;max-width:44em;">
										<option value="0">— Skip this file —</option>
										<?php
										// The scored candidates first, then everything else, so the
										// likely answers are at the top but the operator can still
										// reach a job the matcher never considered.
										$listed = array();
										foreach ( $m['alternatives'] as $alt ) :
											$j        = $alt['job'];
											$listed[] = (int) $j->id;
											$u        = $j->user_id ? get_userdata( $j->user_id ) : null;
											$label    = sprintf(
												'%s — %s — %s%s',
												$j->job_date ? gmdate( 'd/m/Y', strtotime( $j->job_date ) ) : ( $j->scheduled_date ? gmdate( 'd/m/Y', strtotime( $j->scheduled_date ) ) . ' (sched)' : 'undated' ),
												$j->address ? $j->address : 'no address',
												$u ? $u->display_name : 'unassigned',
												! empty( $j->report_file ) ? ' [HAS REPORT]' : ''
											);
											?>
											<option value="<?php echo (int) $j->id; ?>"
												<?php selected( $m['confident'] && $m['best'] && (int) $j->id === (int) $m['best']->id ); ?>>
												<?php echo esc_html( $label ); ?>
											</option>
										<?php endforeach; ?>

										<?php if ( $listed ) : ?>
											<option value="0" disabled>──────────</option>
										<?php endif; ?>

										<?php foreach ( $jobs as $j ) :
											if ( in_array( (int) $j->id, $listed, true ) ) { continue; }
											$u = $j->user_id ? get_userdata( $j->user_id ) : null; ?>
											<option value="<?php echo (int) $j->id; ?>">
												<?php echo esc_html( sprintf(
													'%s — %s — %s',
													$j->job_date ? gmdate( 'd/m/Y', strtotime( $j->job_date ) ) : 'undated',
													$j->address ? $j->address : 'no address',
													$u ? $u->display_name : 'unassigned'
												) ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td>
									<?php if ( $m['confident'] ) : ?>
										<span style="color:#0c5f5c;font-weight:600;">Confident</span>
									<?php elseif ( $m['best'] ) : ?>
										<span style="color:#8a6d00;font-weight:600;">Check this</span>
									<?php else : ?>
										<span style="color:#b32d2e;font-weight:600;">No match</span>
									<?php endif; ?>
									<br /><span class="description" style="font-size:11px;"><?php echo esc_html( $m['why'] ); ?></span>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<p style="margin-top:18px;">
					<button class="button button-primary">File the selected reports</button>
					<span class="description" style="margin-left:8px;">
						Partners with notifications switched on will be emailed. Files left on
						"Skip" are discarded.
					</span>
				</p>
			</form>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:8px;">
				<?php wp_nonce_field( 'jrc_bulk_discard' ); ?>
				<input type="hidden" name="action" value="jrc_bulk_discard" />
				<input type="hidden" name="batch" value="<?php echo esc_attr( $batch_id ); ?>" />
				<button class="button-link" style="color:#b32d2e;">Discard this batch</button>
			</form>

		<?php endif; ?>
	</div>
	<?php
}
