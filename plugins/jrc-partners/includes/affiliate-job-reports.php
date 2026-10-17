<?php
/**
 * The report library: the per-clean PDF report attached to each job.
 *
 * JR Cleaning issues a photo report after every clean — what was cleaned, with
 * pictures. Those reports are what an estate agent forwards to a landlord or tenant, so
 * each affiliate needs to collect their own from their account area.
 *
 * PRIVACY. These documents carry a property address and photographs of the inside of
 * somebody's home. They must never sit on a guessable public URL, so:
 *
 *   1. Files live in uploads/jrc-reports/ under a random 32-character filename. Even
 *      with directory protection defeated, a URL cannot be guessed or enumerated.
 *   2. That directory gets an .htaccess deny and an index.php. Production is Apache so
 *      the deny applies; Local runs nginx and ignores .htaccess entirely, which is
 *      exactly why the random filename above is the primary defence and not the
 *      belt-and-braces one.
 *   3. Nothing is ever linked directly. Downloads go through the template_redirect
 *      handler below, which checks the logged-in user actually owns the job before
 *      streaming a byte.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Only PDFs. The report is a document, not an image gallery. */
function jrc_report_allowed_types() {
	return apply_filters( 'jrc_report_allowed_types', array( 'pdf' => 'application/pdf' ) );
}

/** Absolute path to the protected store, creating and hardening it on first use. */
function jrc_reports_dir() {
	$up  = wp_upload_dir();

	/**
	 * Filter where report PDFs are stored.
	 *
	 * The default sits under uploads/, protected by .htaccess plus random filenames. If
	 * the host serves static files from Nginx (Cloudways does, in front of Apache), the
	 * .htaccess deny is never consulted and the random name is the only protection. To
	 * take the files out of the web root entirely, point this at a private folder — on
	 * Cloudways, the application's private_html — and move the existing files across:
	 *
	 *     add_filter( 'jrc_reports_dir', function () {
	 *         return dirname( ABSPATH ) . '/private_html/jrc-reports';
	 *     } );
	 *
	 * Downloads always stream through PHP, so nothing else needs to change.
	 *
	 * @param string $dir Absolute path, no trailing slash.
	 */
	$dir = untrailingslashit( (string) apply_filters( 'jrc_reports_dir', trailingslashit( $up['basedir'] ) . 'jrc-reports' ) );

	if ( ! is_dir( $dir ) ) {
		wp_mkdir_p( $dir );
	}

	// Written once. Apache honours this; nginx does not, hence the random filenames.
	$ht = $dir . '/.htaccess';
	if ( ! file_exists( $ht ) ) {
		file_put_contents( $ht, "Order Deny,Allow\nDeny from all\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n" );
	}
	$idx = $dir . '/index.php';
	if ( ! file_exists( $idx ) ) {
		file_put_contents( $idx, "<?php // Silence is golden.\n" );
	}

	return $dir;
}

/** Fetch one job row, or null. */
function jrc_job_get( $id ) {
	global $wpdb;
	$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . jrc_jobs_table() . ' WHERE id = %d', (int) $id ) );
	return $row ?: null;
}

/**
 * Store an uploaded PDF against a job.
 *
 * @param int   $job_id Job.
 * @param array $file   One entry from $_FILES.
 * @return true|WP_Error
 */
function jrc_job_report_attach( $job_id, $file ) {
	if ( empty( $file['tmp_name'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
		return new WP_Error( 'no_file', 'No file was received.' );
	}
	if ( ! empty( $file['error'] ) ) {
		return new WP_Error( 'upload_error', 'The upload failed (code ' . (int) $file['error'] . ').' );
	}

	// is_uploaded_file() has now vouched for the path. 'upload' mode below keeps
	// move_uploaded_file() as the thing that actually moves it.
	return jrc_job_report_store( $job_id, $file['tmp_name'], $file['name'], 'upload' );
}

/**
 * Store a PDF against a job, from a path that has ALREADY been established as safe.
 *
 * Split out of jrc_job_report_attach() for the bulk screen, which stages files in the
 * protected directory across two requests: by the time the operator confirms a match,
 * the file is no longer a PHP upload and is_uploaded_file() correctly refuses it.
 *
 * SECURITY. This function does not and cannot check where $source came from — that is
 * the caller's job, and there are exactly two callers. jrc_job_report_attach() proves
 * it with is_uploaded_file(); includes/affiliate-bulk-reports.php proves it by having
 * written the file itself into a directory it controls, under a name it generated, and
 * by rejecting anything whose resolved path escapes that directory. Do not call this
 * with a path derived from a request without doing the same.
 *
 * @param int    $job_id Job.
 * @param string $source Absolute path to the PDF.
 * @param string $name   Original filename, kept for display.
 * @param string $mode   'upload' — a live PHP upload, moved with move_uploaded_file().
 *                       'move'   — a file we already own; moved, leaving nothing behind.
 *                       'copy'   — a file we already own; left where it is.
 * @return true|WP_Error
 */
function jrc_job_report_store( $job_id, $source, $name, $mode = 'move' ) {
	$job = jrc_job_get( $job_id );
	if ( ! $job ) {
		return new WP_Error( 'no_job', 'That job no longer exists.' );
	}
	if ( ! $source || ! file_exists( $source ) ) {
		return new WP_Error( 'no_file', 'No file was received.' );
	}

	// Trust the sniffed type, not the extension or the browser-supplied mime.
	$check = wp_check_filetype_and_ext( $source, $name, jrc_report_allowed_types() );
	if ( empty( $check['ext'] ) || 'pdf' !== strtolower( $check['ext'] ) ) {
		return new WP_Error( 'not_pdf', 'Reports must be PDF files.' );
	}

	$dir      = jrc_reports_dir();
	$stored   = wp_generate_password( 32, false, false ) . '.pdf';
	$target   = trailingslashit( $dir ) . $stored;

	if ( 'upload' === $mode ) {
		// Not rename(): PHP's upload tmp dir is routinely on a different filesystem from
		// wp-content/uploads, where rename() fails outright. move_uploaded_file() handles
		// that case and re-verifies the file is a genuine upload.
		$ok = @move_uploaded_file( $source, $target );
	} elseif ( 'copy' === $mode ) {
		$ok = @copy( $source, $target );
	} else {
		// Same cross-device problem, same solution: fall back to copy + unlink when
		// rename() cannot span the two locations.
		$ok = @rename( $source, $target );
		if ( ! $ok && @copy( $source, $target ) ) {
			@unlink( $source );
			$ok = true;
		}
	}

	if ( ! $ok ) {
		return new WP_Error( 'move_failed', 'The file could not be saved.' );
	}
	@chmod( $target, 0640 );

	// Replacing an existing report leaves the old file behind otherwise.
	$had_report = ! empty( $job->report_file );
	jrc_job_report_delete( $job_id, false );

	global $wpdb;
	$wpdb->update(
		jrc_jobs_table(),
		array(
			'report_file'     => $stored,
			'report_name'     => sanitize_file_name( $name ),
			'report_uploaded' => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		),
		array( 'id' => (int) $job_id )
	);

	/**
	 * A report has landed against a job.
	 *
	 * Fired here rather than from the profile-save loop so that any future path that
	 * attaches a report — an importer, the REST endpoint, a bulk upload screen —
	 * notifies the partner without having to remember to.
	 *
	 * @param int    $job_id  Job the report belongs to.
	 * @param object $job     The job row as it was BEFORE the update.
	 * @param bool   $replaced Whether this overwrote an existing report.
	 */
	do_action( 'jrc_job_report_attached', (int) $job_id, $job, $had_report );

	return true;
}

/**
 * Remove a job's report.
 *
 * @param int  $job_id      Job.
 * @param bool $clear_row   Also blank the columns (false when about to overwrite them).
 */
function jrc_job_report_delete( $job_id, $clear_row = true ) {
	$job = jrc_job_get( $job_id );
	if ( ! $job || empty( $job->report_file ) ) {
		return false;
	}

	// basename() guards against a stored value that somehow contains traversal.
	$path = trailingslashit( jrc_reports_dir() ) . basename( $job->report_file );
	if ( file_exists( $path ) ) {
		@unlink( $path );
	}

	if ( $clear_row ) {
		global $wpdb;
		$wpdb->update(
			jrc_jobs_table(),
			array( 'report_file' => '', 'report_name' => '', 'report_uploaded' => null ),
			array( 'id' => (int) $job_id )
		);
	}
	return true;
}

/** Can this user download this job's report? */
function jrc_can_download_report( $job, $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id || ! $job || empty( $job->report_file ) ) {
		return false;
	}
	// Staff who can see every account, or the affiliate the job belongs to.
	if ( current_user_can( jrc_reports_cap() ) ) {
		return true;
	}
	return (int) $job->user_id === (int) $user_id;
}

/** Signed-ish download URL. The nonce is per-user, so a shared link is useless. */
function jrc_report_url( $job_id ) {
	return wp_nonce_url(
		add_query_arg( 'jrc_report', (int) $job_id, home_url( '/' ) ),
		'jrc_report_' . (int) $job_id
	);
}

/**
 * Stream a report to the browser after checking ownership.
 *
 * Runs on template_redirect so WordPress and the current user are loaded but nothing
 * has been output yet.
 */
add_action( 'template_redirect', function () {
	if ( empty( $_GET['jrc_report'] ) ) {
		return;
	}

	$job_id = (int) $_GET['jrc_report'];

	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'jrc_report_' . $job_id ) ) {
		wp_die( esc_html__( 'That download link has expired. Go back to your reports and try again.', 'jrc-partners' ), 403 );
	}

	$job = jrc_job_get( $job_id );
	if ( ! jrc_can_download_report( $job ) ) {
		// Same message whether the job is missing or simply not theirs — no probing.
		wp_die( esc_html__( 'That report is not available.', 'jrc-partners' ), 404 );
	}

	$path = trailingslashit( jrc_reports_dir() ) . basename( $job->report_file );
	if ( ! file_exists( $path ) ) {
		wp_die( esc_html__( 'That report is no longer on file. Please get in touch and we will re-send it.', 'jrc-partners' ), 404 );
	}

	// A human-readable name, not the random storage name.
	$nice = $job->report_name ? $job->report_name : sprintf(
		'JR-Cleaning-report-%s-%s.pdf',
		$job->job_date ? gmdate( 'Y-m-d', strtotime( $job->job_date ) ) : 'undated',
		sanitize_title( $job->job_type ?: 'clean' )
	);
	// Strips quotes, control characters and path separators, so the name cannot break
	// out of the quoted parameter below or inject a header.
	$nice = sanitize_file_name( $nice );

	nocache_headers();
	header( 'Content-Type: application/pdf' );

	// RFC 6266. The quoted `filename` is the ASCII fallback for old clients; percent
	// encoding is only legal in `filename*`, which is why it is not applied to both —
	// a browser saves the quoted form verbatim, so an encoded name there lands on disk
	// as literal %C3%A9 rather than the character it stands for.
	header(
		'Content-Disposition: attachment; filename="' . $nice . '"; '
		. "filename*=UTF-8''" . rawurlencode( $nice )
	);
	header( 'Content-Length: ' . filesize( $path ) );
	header( 'X-Content-Type-Options: nosniff' );
	readfile( $path );
	exit;
} );

// ---------------------------------------------------------------------------
// "New" tracking
// ---------------------------------------------------------------------------

/**
 * Reports uploaded since the affiliate last opened their reports tab.
 *
 * The client's ask was somewhere to download "their new reports", so new has to mean
 * something. It is tracked per user rather than per report: simple, and it survives a
 * report being replaced with a corrected version.
 */
function jrc_reports_last_seen( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	return (string) get_user_meta( $user_id, 'jrc_reports_last_seen', true );
}

function jrc_reports_mark_seen( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	update_user_meta( $user_id, 'jrc_reports_last_seen', current_time( 'mysql' ) );
}

/** Is this job's report new to the affiliate? */
function jrc_report_is_new( $job, $last_seen ) {
	if ( empty( $job->report_uploaded ) ) {
		return false;
	}
	// Never opened the tab: everything on file is new.
	return ! $last_seen || strtotime( $job->report_uploaded ) > strtotime( $last_seen );
}

/** Jobs with a report, newest report first. */
function jrc_reports_for_user( $user_id, $limit = 500 ) {
	global $wpdb;
	return $wpdb->get_results( $wpdb->prepare(
		'SELECT * FROM ' . jrc_jobs_table() . " WHERE user_id = %d AND report_file != '' ORDER BY report_uploaded DESC, job_date DESC LIMIT %d",
		(int) $user_id, (int) $limit
	) );
}

/**
 * How many reports the affiliate hasn't seen yet — drives the nav badge.
 *
 * Counted in SQL rather than by walking jrc_reports_for_user(): this runs on the
 * account menu filter, so every My Account page render was pulling up to 500 full job
 * rows out of the database to produce a single integer.
 */
function jrc_reports_new_count( $user_id = 0 ) {
	global $wpdb;

	$user_id = $user_id ? $user_id : get_current_user_id();
	if ( ! $user_id ) {
		return 0;
	}

	$seen  = jrc_reports_last_seen( $user_id );
	$table = jrc_jobs_table();

	// Never opened the tab: every report on file is new, which is what
	// jrc_report_is_new() does with an empty $last_seen.
	if ( ! $seen ) {
		return (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM ' . $table . " WHERE user_id = %d AND report_file != '' AND report_uploaded IS NOT NULL",
			(int) $user_id
		) );
	}

	return (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM ' . $table . " WHERE user_id = %d AND report_file != '' AND report_uploaded > %s",
		(int) $user_id,
		$seen
	) );
}

// ---------------------------------------------------------------------------
// Searching the library
// ---------------------------------------------------------------------------

/**
 * Search and page one affiliate's reports in SQL.
 *
 * jrc_reports_for_user() pulls up to 500 full rows and the template used to slice that
 * array for paging. That was fine for a branch with a dozen cleans; it is the wrong
 * shape once someone is hunting for "14 Bridge Street" among two years of them, because
 * the filtering has to happen before the paging or page 2 shows the wrong window.
 *
 * @param int   $user_id Affiliate.
 * @param array $args    search, month (Y-m), job_type, paged, per_page.
 * @return array {
 *     @type array $rows  Job rows carrying a report.
 *     @type int   $total Matching rows before paging.
 *     @type int   $pages Total pages.
 *     @type int   $paged Page actually returned (clamped into range).
 * }
 */
function jrc_reports_query( $user_id, $args = array() ) {
	global $wpdb;

	$args = wp_parse_args( $args, array(
		'search'   => '',
		'month'    => '',
		'job_type' => '',
		'paged'    => 1,
		'per_page' => 15,
	) );

	$table = jrc_jobs_table();
	$where = array( 'user_id = %d', "report_file != ''" );
	$prep  = array( (int) $user_id );

	if ( '' !== $args['search'] ) {
		$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$where[] = '(address LIKE %s OR job_type LIKE %s OR scope LIKE %s OR report_name LIKE %s)';
		array_push( $prep, $like, $like, $like, $like );
	}

	// Month filters on the date of the clean, not the upload: a partner looking for
	// "March's reports" means the cleans that happened in March, even if the report
	// for the 31st was uploaded on 2 April.
	if ( '' !== $args['month'] && preg_match( '/^\d{4}-\d{2}$/', $args['month'] ) ) {
		$where[] = 'job_date BETWEEN %s AND %s';
		array_push( $prep, $args['month'] . '-01', gmdate( 'Y-m-t', strtotime( $args['month'] . '-01' ) ) );
	}

	if ( '' !== $args['job_type'] ) {
		$where[] = 'job_type = %s';
		$prep[]  = $args['job_type'];
	}

	$sql_where = implode( ' AND ', $where );

	$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE $sql_where", $prep ) );

	$per_page = max( 1, (int) $args['per_page'] );
	$pages    = max( 1, (int) ceil( $total / $per_page ) );
	$paged    = max( 1, min( $pages, (int) $args['paged'] ) );

	$page_prep = $prep;
	array_push( $page_prep, $per_page, ( $paged - 1 ) * $per_page );

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT * FROM $table WHERE $sql_where ORDER BY report_uploaded DESC, job_date DESC LIMIT %d OFFSET %d",
		$page_prep
	) );

	return array(
		'rows'  => (array) $rows,
		'total' => $total,
		'pages' => $pages,
		'paged' => $paged,
	);
}

/**
 * Months this affiliate actually has reports for, newest first.
 *
 * Drives the month dropdown. Built from the data rather than from a fixed range so the
 * filter never offers a month that returns nothing.
 *
 * @return array Y-m => "March 2026"
 */
function jrc_reports_months( $user_id ) {
	global $wpdb;

	$rows = $wpdb->get_col( $wpdb->prepare(
		'SELECT DISTINCT DATE_FORMAT(job_date, %s) FROM ' . jrc_jobs_table()
		. " WHERE user_id = %d AND report_file != '' AND job_date IS NOT NULL ORDER BY 1 DESC",
		'%Y-%m',
		(int) $user_id
	) );

	$out = array();
	foreach ( (array) $rows as $ym ) {
		if ( $ym ) {
			$out[ $ym ] = date_i18n( 'F Y', strtotime( $ym . '-01' ) );
		}
	}
	return $out;
}

/** Job types this affiliate has reports for, for the type dropdown. */
function jrc_reports_job_types( $user_id ) {
	global $wpdb;
	return array_filter( (array) $wpdb->get_col( $wpdb->prepare(
		'SELECT DISTINCT job_type FROM ' . jrc_jobs_table()
		. " WHERE user_id = %d AND report_file != '' AND job_type != '' ORDER BY job_type ASC",
		(int) $user_id
	) ) );
}

/**
 * How many reports were uploaded for this affiliate between two datetimes.
 *
 * Used by the monthly digest, which needs a count for last month without loading the
 * rows to produce it.
 */
function jrc_reports_count_between( $user_id, $from, $to ) {
	global $wpdb;
	return (int) $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM ' . jrc_jobs_table()
		. " WHERE user_id = %d AND report_file != '' AND report_uploaded BETWEEN %s AND %s",
		(int) $user_id,
		$from,
		$to
	) );
}
