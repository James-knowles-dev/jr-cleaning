<?php
/**
 * What a partner can take away with them: their own job list as CSV, and a month of
 * photo reports as a single zip.
 *
 * The admin side has had a CSV export since the Activity screen was built. The partner
 * — whose data it is — had no way to get any of it out, which meant "can you send me a
 * list of everything you did for us last year" was a manual job for the office. And a
 * lettings manager assembling a month-end pack was downloading fourteen PDFs one at a
 * time.
 *
 * Both handlers follow the pattern already established by the report download in
 * includes/affiliate-job-reports.php: a nonce-signed query argument picked up on
 * template_redirect, ownership enforced in PHP, nothing served straight off disk.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Signed URL for the partner's own CSV. */
function jrc_partner_csv_url() {
	return wp_nonce_url( add_query_arg( 'jrc_export', 'jobs', home_url( '/' ) ), 'jrc_partner_export' );
}

/**
 * Signed URL for a zip of reports.
 *
 * @param string $month Y-m, or 'all'.
 */
function jrc_reports_zip_url( $month = 'all' ) {
	return wp_nonce_url(
		add_query_arg( 'jrc_reports_zip', $month, home_url( '/' ) ),
		'jrc_reports_zip'
	);
}

/** Is bundling available on this host? */
function jrc_can_zip() {
	return class_exists( 'ZipArchive' );
}

// ---------------------------------------------------------------------------
// CSV of the partner's own jobs
// ---------------------------------------------------------------------------

add_action( 'template_redirect', function () {
	if ( empty( $_GET['jrc_export'] ) || 'jobs' !== $_GET['jrc_export'] ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'jrc_partner_export' ) ) {
		wp_die( esc_html__( 'That download link has expired. Go back and try again.', 'jrc-partners' ), 403 );
	}

	$user_id = get_current_user_id();
	if ( ! jrc_is_affiliate( $user_id ) ) {
		wp_die( esc_html__( 'That export is not available.', 'jrc-partners' ), 404 );
	}

	// Hard-wired to the current user. There is deliberately no way to pass an id here:
	// the admin export at admin_post_jrc_export_jobs is the screen for looking across
	// accounts, and it has its own capability check.
	$rows = jrc_jobs_query( array(
		'user_id' => $user_id,
		'orderby' => 'job_date',
		'order'   => 'DESC',
		'limit'   => 5000,
	) );

	$show_money = jrc_affiliate_show_money();

	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=jr-cleaning-my-jobs-' . gmdate( 'Y-m-d' ) . '.csv' );
	header( 'X-Content-Type-Options: nosniff' );

	$out = fopen( 'php://output', 'w' );

	// Same UTF-8 BOM as the admin export, for the same reason: Excel on Windows.
	fwrite( $out, "\xEF\xBB\xBF" );

	$head = array( 'Date of clean', 'Scheduled', 'Service', 'Address', 'Scope', 'Status', 'Report on file' );
	if ( $show_money ) {
		array_splice( $head, 5, 0, array( 'Value' ) );
	}
	fputcsv( $out, $head );

	foreach ( $rows as $r ) {
		$line = array(
			$r->job_date,
			$r->scheduled_date,
			$r->job_type,
			$r->address,
			preg_replace( '/\s+/', ' ', (string) $r->scope ),
			$r->status,
			$r->report_file ? 'Yes' : 'No',
		);
		if ( $show_money ) {
			array_splice( $line, 5, 0, array( (float) $r->value ? number_format( (float) $r->value, 2, '.', '' ) : '' ) );
		}
		// jrc_csv_cell() neutralises a leading =, +, - or @ so a spreadsheet cannot be
		// talked into treating an address as a formula.
		fputcsv( $out, array_map( 'jrc_csv_cell', $line ) );
	}

	fclose( $out );
	exit;
} );

// ---------------------------------------------------------------------------
// Zip of reports
// ---------------------------------------------------------------------------

add_action( 'template_redirect', function () {
	if ( empty( $_GET['jrc_reports_zip'] ) ) {
		return;
	}

	if ( ! is_user_logged_in() ) {
		auth_redirect();
	}
	if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'jrc_reports_zip' ) ) {
		wp_die( esc_html__( 'That download link has expired. Go back to your reports and try again.', 'jrc-partners' ), 403 );
	}

	$user_id = get_current_user_id();
	if ( ! jrc_is_affiliate( $user_id ) ) {
		wp_die( esc_html__( 'That download is not available.', 'jrc-partners' ), 404 );
	}

	if ( ! jrc_can_zip() ) {
		wp_die( esc_html__( 'Bundled downloads are not available on this server. Please download the reports individually.', 'jrc-partners' ), 501 );
	}

	$month = sanitize_text_field( wp_unslash( $_GET['jrc_reports_zip'] ) );
	$month = preg_match( '/^\d{4}-\d{2}$/', $month ) ? $month : 'all';

	$result = jrc_reports_query( $user_id, array(
		'month'    => 'all' === $month ? '' : $month,
		'per_page' => (int) apply_filters( 'jrc_reports_zip_max', 200 ),
		'paged'    => 1,
	) );

	if ( ! $result['rows'] ) {
		wp_die( esc_html__( 'There are no reports to bundle for that period.', 'jrc-partners' ), 404 );
	}

	$dir = jrc_reports_dir();
	$zip = new ZipArchive();

	// wp_tempnam() lives in wp-admin/includes/file.php, which is not loaded on the
	// front end — and this handler runs on template_redirect. Pulling it in is what
	// core itself does when a front-end path needs the file helpers.
	if ( ! function_exists( 'wp_tempnam' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}

	// Built as a temporary file rather than streamed: ZipArchive writes to a file
	// handle, and assembling in memory would put a month of PDFs into PHP's limit.
	$tmp = wp_tempnam( 'jrc-reports-' . $month . '.zip' );
	if ( ! $tmp || true !== $zip->open( $tmp, ZipArchive::OVERWRITE ) ) {
		if ( $tmp ) {
			@unlink( $tmp );
		}
		wp_die( esc_html__( 'The bundle could not be created. Please try again.', 'jrc-partners' ), 500 );
	}

	$used  = array();
	$added = 0;

	foreach ( $result['rows'] as $r ) {
		$path = trailingslashit( $dir ) . basename( $r->report_file );
		if ( ! file_exists( $path ) ) {
			continue;
		}

		// A readable name inside the archive, not the random storage name. Prefixed
		// with the date so the pack sorts chronologically when it is unzipped.
		$base = sanitize_file_name( sprintf(
			'%s %s %s',
			$r->job_date ? gmdate( 'Y-m-d', strtotime( $r->job_date ) ) : 'undated',
			$r->job_type ?: 'Clean',
			$r->address ?: ''
		) );
		$base = trim( preg_replace( '/-+/', '-', $base ), '-' ) ?: 'report';

		// Two cleans at the same address on the same day would otherwise silently
		// overwrite one another inside the archive.
		$name = $base . '.pdf';
		$n    = 2;
		while ( isset( $used[ strtolower( $name ) ] ) ) {
			$name = $base . '-' . $n . '.pdf';
			$n++;
		}
		$used[ strtolower( $name ) ] = true;

		if ( $zip->addFile( $path, $name ) ) {
			$added++;
		}
	}

	$zip->close();

	if ( ! $added ) {
		@unlink( $tmp );
		wp_die( esc_html__( 'None of those reports could be read. Please get in touch and we will re-send them.', 'jrc-partners' ), 404 );
	}

	$label = 'all' === $month ? 'all' : gmdate( 'Y-m', strtotime( $month . '-01' ) );

	nocache_headers();
	header( 'Content-Type: application/zip' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( 'jr-cleaning-reports-' . $label . '.zip' ) . '"' );
	header( 'Content-Length: ' . filesize( $tmp ) );
	header( 'X-Content-Type-Options: nosniff' );

	readfile( $tmp );
	@unlink( $tmp );
	exit;
} );
