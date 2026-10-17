<?php
/**
 * Job records: storage, migration and querying.
 *
 * Jobs used to live as a serialised array in the `jrc_affiliate_jobs` user meta key.
 * That was fine while the programme was one channel and a handful of accounts, but the
 * Activity screen needs to filter, sort, aggregate and export across every affiliate,
 * and the Calendar needs a date range query. Neither is possible against serialised
 * meta without loading and unserialising every user on every request.
 *
 * So jobs now live in their own table. jrc_affiliate_jobs() keeps its original
 * signature and return shape, so the existing dashboard and admin screens carry on
 * working unchanged while everything new queries the table directly.
 *
 * `source` and `external_ref` exist for the scheduling-system integration: an importer
 * calls jrc_job_upsert_external() with the remote id and repeated syncs update in place
 * rather than duplicating. Manually entered jobs use source 'manual'.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'JRC_JOBS_DB_VERSION', '1.3.0' );

/** Fully-qualified table name. */
function jrc_jobs_table() {
	global $wpdb;
	return $wpdb->prefix . 'jrc_jobs';
}

/** Create or upgrade the table. Runs on admin_init, cheap after the first pass. */
function jrc_jobs_install() {
	if ( get_option( 'jrc_jobs_db_version' ) === JRC_JOBS_DB_VERSION ) {
		return;
	}

	global $wpdb;
	$table   = jrc_jobs_table();
	$charset = $wpdb->get_charset_collate();

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	// NB: dbDelta is fussy — two spaces after PRIMARY KEY, one space between KEY and
	// its name, lowercase types. Do not reformat.
	dbDelta( "CREATE TABLE $table (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		user_id bigint(20) unsigned NOT NULL DEFAULT 0,
		job_date date DEFAULT NULL,
		scheduled_date datetime DEFAULT NULL,
		job_type varchar(100) NOT NULL DEFAULT '',
		address varchar(255) NOT NULL DEFAULT '',
		scope text NULL,
		value decimal(10,2) NOT NULL DEFAULT 0.00,
		status varchar(32) NOT NULL DEFAULT '',
		free_bonus varchar(32) NOT NULL DEFAULT '',
		report_file varchar(191) NOT NULL DEFAULT '',
		report_name varchar(255) NOT NULL DEFAULT '',
		report_uploaded datetime DEFAULT NULL,
		source varchar(32) NOT NULL DEFAULT 'manual',
		external_ref varchar(191) NOT NULL DEFAULT '',
		scheduled_end datetime DEFAULT NULL,
		external_url varchar(255) NOT NULL DEFAULT '',
		external_meta longtext NULL,
		created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
		PRIMARY KEY  (id),
		KEY user_id (user_id),
		KEY job_date (job_date),
		KEY scheduled_date (scheduled_date),
		KEY scheduled_end (scheduled_end),
		KEY status (status),
		KEY source_ref (source,external_ref)
	) $charset;" );

	update_option( 'jrc_jobs_db_version', JRC_JOBS_DB_VERSION );
	jrc_jobs_migrate_from_meta();
}
add_action( 'admin_init', 'jrc_jobs_install' );

/**
 * One-time migration of the serialised user-meta rows into the table.
 *
 * The original meta is deliberately left in place rather than deleted — it is the only
 * copy of this data and costs nothing to keep. `jrc_affiliate_jobs_migrated` marks the
 * user as done so a second run cannot duplicate rows.
 */
function jrc_jobs_migrate_from_meta() {
	if ( get_option( 'jrc_jobs_migrated' ) ) {
		return 0;
	}

	$users = get_users( array(
		'meta_query' => array( array( 'key' => 'jrc_affiliate_jobs', 'compare' => 'EXISTS' ) ),
		'fields'     => 'ID',
	) );

	$moved = 0;
	foreach ( $users as $uid ) {
		if ( get_user_meta( $uid, 'jrc_affiliate_jobs_migrated', true ) ) {
			continue;
		}
		$rows = get_user_meta( $uid, 'jrc_affiliate_jobs', true );
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( jrc_job_insert( $uid, $row ) ) {
					$moved++;
				}
			}
		}
		update_user_meta( $uid, 'jrc_affiliate_jobs_migrated', 1 );
	}

	update_option( 'jrc_jobs_migrated', 1 );
	return $moved;
}

/** Normalise a loose job array into table columns. */
function jrc_job_normalise( $row ) {
	$row = wp_parse_args( (array) $row, array(
		'date' => '', 'scheduled_date' => '', 'type' => '', 'address' => '',
		'scope' => '', 'value' => '', 'status' => '', 'free_bonus' => '',
		'source' => 'manual', 'external_ref' => '',
		// Added with the Connecteam integration. A manually-entered job leaves both
		// empty, which is why they are not part of the "is this row blank?" test in
		// jrc_job_insert() — an end time with nothing else is not a job.
		'scheduled_end' => '', 'external_url' => '',
	) );

	$date = $row['date'] ? gmdate( 'Y-m-d', strtotime( $row['date'] ) ) : null;
	$sched = $row['scheduled_date'] ? gmdate( 'Y-m-d H:i:s', strtotime( $row['scheduled_date'] ) ) : null;
	$end   = $row['scheduled_end'] ? gmdate( 'Y-m-d H:i:s', strtotime( $row['scheduled_end'] ) ) : null;

	// Values were typed by hand as "£300", "300", "£1,200" — strip to a number.
	$value = (float) preg_replace( '/[^0-9.]/', '', (string) $row['value'] );

	return array(
		'user_id'        => 0,
		'job_date'       => $date,
		'scheduled_date' => $sched,
		'job_type'       => sanitize_text_field( $row['type'] ),
		'address'        => sanitize_text_field( $row['address'] ),
		'scope'          => sanitize_textarea_field( $row['scope'] ),
		'value'          => $value,
		'status'         => sanitize_text_field( $row['status'] ),
		'free_bonus'     => sanitize_key( $row['free_bonus'] ),
		'source'         => sanitize_key( $row['source'] ) ?: 'manual',
		'external_ref'   => sanitize_text_field( $row['external_ref'] ),
		'scheduled_end'  => $end,
		'external_url'   => esc_url_raw( $row['external_url'] ),
	) + jrc_job_normalise_meta( $row );
}

/**
 * The `external_meta` column, only when the caller supplied it.
 *
 * Extra detail an importer carries from the source system (Connecteam's shift title,
 * tags and custom fields) — shown to staff, never to partners. Returned only when the
 * row has the key, because jrc_job_update() writes every column normalise() returns:
 * a profile-screen save, which knows nothing about this column, would otherwise wipe it.
 *
 * @return array Empty, or array( 'external_meta' => JSON string ).
 */
function jrc_job_normalise_meta( $row ) {
	if ( ! array_key_exists( 'external_meta', (array) $row ) ) {
		return array();
	}
	$meta = $row['external_meta'];
	if ( is_string( $meta ) ) {
		$meta = json_decode( $meta, true );
	}
	$meta = is_array( $meta ) ? map_deep( $meta, 'sanitize_text_field' ) : array();
	return array( 'external_meta' => $meta ? wp_json_encode( $meta ) : null );
}

/**
 * One-line staff summary of a job's external detail, e.g.
 * "test shift · Shift tags: Bonus shift · Affiliate Code: JRC-TANNER · 1 staff".
 */
function jrc_job_meta_summary( $job ) {
	$m     = jrc_job_meta( $job );
	$parts = array();
	if ( ! empty( $m['shift_title'] ) ) {
		$parts[] = $m['shift_title'];
	}
	// Website enquiries: the enquirer's details, kept out of the partner-visible scope.
	if ( ! empty( $m['contact'] ) ) {
		$parts[] = 'Contact: ' . $m['contact'];
	}
	if ( ! empty( $m['enquiry_notes'] ) ) {
		$parts[] = 'Notes: ' . $m['enquiry_notes'];
	}
	foreach ( (array) ( $m['fields'] ?? array() ) as $name => $value ) {
		$parts[] = $name . ': ' . $value;
	}
	if ( isset( $m['staff_count'] ) ) {
		$parts[] = (int) $m['staff_count'] . ' staff';
	}
	if ( ! empty( $m['open_shift'] ) ) {
		$parts[] = 'open shift';
	}
	return implode( ' · ', $parts );
}

/** Decode a row's external_meta. */
function jrc_job_meta( $job ) {
	$raw = is_object( $job ) ? ( $job->external_meta ?? '' ) : ( $job['external_meta'] ?? '' );
	$out = $raw ? json_decode( (string) $raw, true ) : array();
	return is_array( $out ) ? $out : array();
}

/** Insert a job. Returns the new id, or 0. */
function jrc_job_insert( $user_id, $row ) {
	global $wpdb;
	$data            = jrc_job_normalise( $row );
	$data['user_id'] = (int) $user_id;
	$now             = current_time( 'mysql' );
	$data['created_at'] = $now;
	$data['updated_at'] = $now;

	// A row with nothing in it is how the admin UI signals "delete me" — don't store it.
	if ( ! $data['job_date'] && '' === $data['job_type'] && '' === $data['address'] && '' === $data['scope'] && ! $data['value'] ) {
		return 0;
	}

	$ok = $wpdb->insert( jrc_jobs_table(), $data );
	if ( ! $ok ) {
		return 0;
	}

	$new_id = (int) $wpdb->insert_id;

	// A new row starting life at a status is the first entry on its timeline. Fired
	// from '' so the reader can tell an opening state from a later transition.
	if ( '' !== $data['status'] ) {
		/** @see includes/affiliate-job-events.php */
		do_action( 'jrc_job_status_changed', $new_id, '', $data['status'], '' );
	}

	return $new_id;
}

/**
 * Update a job by id.
 *
 * Reads the current status before writing so a change can be announced. This is the
 * choke point every edit path goes through — the profile screen, the external upsert,
 * the bulk screens — which is what lets includes/affiliate-job-events.php get away
 * with a single listener instead of hooking each of them.
 *
 * @param int    $id   Job.
 * @param array  $row  Loose job array.
 * @param string $note Optional context recorded against the status change.
 */
function jrc_job_update( $id, $row, $note = '' ) {
	global $wpdb;

	$id   = (int) $id;
	$data = jrc_job_normalise( $row );
	unset( $data['user_id'] );
	$data['updated_at'] = current_time( 'mysql' );

	// One extra read per edit. Cheap, and the alternative — diffing after the fact —
	// cannot tell "changed to Booked" from "was already Booked".
	$before = $wpdb->get_var( $wpdb->prepare(
		'SELECT status FROM ' . jrc_jobs_table() . ' WHERE id = %d',
		$id
	) );

	$ok = (bool) $wpdb->update( jrc_jobs_table(), $data, array( 'id' => $id ) );

	// $wpdb->update() returns 0 rows affected when the values are byte-identical, which
	// is not a failure — but it is also not a status change, and the guard above has
	// already established that. Compare regardless of what update() reported.
	if ( null !== $before && (string) $before !== (string) $data['status'] ) {
		do_action( 'jrc_job_status_changed', $id, (string) $before, (string) $data['status'], $note );
	}

	return $ok;
}

/**
 * Change some fields of a job, keeping every other column as it is.
 *
 * jrc_job_update() writes every column jrc_job_normalise() returns, so calling it with a
 * partial row blanks whatever was left out — job_date to NULL, value to 0, source back
 * to 'manual'. This reads the current row and overlays the changes, which is what almost
 * every caller outside the profile screen actually means. external_meta is left alone
 * unless `external_meta` is one of the changes.
 *
 * @param int    $id      Job.
 * @param array  $changes Loose-row keys to change: date, scheduled_date, scheduled_end,
 *                        type, address, scope, value, status, free_bonus, source,
 *                        external_ref, external_url, external_meta.
 * @param string $note    Optional context recorded against a status change.
 * @return bool False when the job does not exist or the write failed.
 */
function jrc_job_patch( $id, array $changes, $note = '' ) {
	$job = jrc_job_get( (int) $id );
	if ( ! $job ) {
		return false;
	}

	$row = array(
		'date'           => $job->job_date,
		'scheduled_date' => $job->scheduled_date,
		'scheduled_end'  => $job->scheduled_end,
		'type'           => $job->job_type,
		'address'        => $job->address,
		'scope'          => $job->scope,
		'value'          => $job->value,
		'status'         => $job->status,
		'free_bonus'     => $job->free_bonus,
		'source'         => $job->source,
		'external_ref'   => $job->external_ref,
		'external_url'   => $job->external_url,
	);

	return jrc_job_update( (int) $id, array_merge( $row, $changes ), $note );
}

/** Delete a job by id. */
function jrc_job_delete( $id ) {
	global $wpdb;
	$id = (int) $id;
	$ok = (bool) $wpdb->delete( jrc_jobs_table(), array( 'id' => $id ) );

	if ( $ok ) {
		/**
		 * A job row has gone.
		 *
		 * @param int $id The deleted job's id.
		 */
		do_action( 'jrc_job_deleted', $id );
	}

	return $ok;
}

/**
 * Sync the manually-entered jobs for one affiliate against what the profile screen
 * submitted: update rows that carry an id, insert those that don't, delete any the form
 * no longer contains.
 *
 * This used to delete every row and re-insert. That was fine while a job was only its
 * own field values, but a job now owns an uploaded PDF report keyed to its id — wiping
 * the rows on every profile save would have orphaned every report on the first edit.
 *
 * ONLY MANUAL ROWS ARE EVER TOUCHED. Rows from the Connecteam sync, partner requests and
 * website enquiries belong to their own pipelines. A submitted row carrying the id of
 * one of those (a stale form, a hand-edited POST) is ignored rather than inserted:
 * inserting it was how every profile save used to duplicate the synced jobs, with the
 * copies' job_date inflating the tier count.
 *
 * @param int        $user_id Affiliate.
 * @param array      $rows    Submitted rows.
 * @param int[]|null $shown   Ids of the manual rows the form actually rendered. Only
 *                            these can be deleted by being absent, so a row the form
 *                            never displayed can never be removed by a save. null keeps
 *                            the old behaviour (every manual row is in scope) for any
 *                            caller that predates it.
 * @return array inserted/updated/deleted counts, plus `ids` — the resulting job id for
 *               each submitted row, in the order they were submitted, so an uploaded
 *               file at index N can be attached to the right job.
 */
function jrc_jobs_sync_for_user( $user_id, $rows, $shown = null ) {
	global $wpdb;
	$user_id = (int) $user_id;
	$table   = jrc_jobs_table();

	$existing = $wpdb->get_col( $wpdb->prepare(
		"SELECT id FROM $table WHERE user_id = %d AND source = 'manual'", $user_id
	) );
	$existing = array_map( 'intval', (array) $existing );
	$kept     = array();
	$ids      = array();
	$ins      = 0;
	$upd      = 0;

	foreach ( (array) $rows as $i => $row ) {
		$id = isset( $row['id'] ) ? (int) $row['id'] : 0;

		if ( $id && in_array( $id, $existing, true ) ) {
			jrc_job_update( $id, $row );
			$kept[]     = $id;
			$ids[ $i ]  = $id;
			$upd++;
			continue;
		}

		// An id that is not one of this account's manual rows is a job this screen
		// does not own. Never turn it into a new row.
		if ( $id ) {
			continue;
		}

		$new_id = jrc_job_insert( $user_id, $row );
		if ( $new_id ) {
			$kept[]    = $new_id;
			$ids[ $i ] = $new_id;
			$ins++;
		}
	}

	$in_scope = null === $shown ? $existing : array_intersect( $existing, array_map( 'intval', (array) $shown ) );
	$gone     = array_diff( $in_scope, $kept );
	foreach ( $gone as $id ) {
		jrc_job_report_delete( $id );   // remove the PDF too, not just the row
		jrc_job_delete( $id );
	}

	return array( 'inserted' => $ins, 'updated' => $upd, 'deleted' => count( $gone ), 'ids' => $ids );
}

/** Kept for back-compat; the profile screen now calls jrc_jobs_sync_for_user(). */
function jrc_jobs_replace_for_user( $user_id, $rows ) {
	$r = jrc_jobs_sync_for_user( $user_id, $rows );
	return $r['inserted'] + $r['updated'];
}

/**
 * Upsert from an external scheduling system, matched on source + external_ref.
 *
 * This is the seam the booking-calendar integration hooks into: whichever system JR
 * Cleaning schedules in, its importer maps a remote booking onto a $row array and
 * calls this. Re-running a sync updates rows rather than duplicating them.
 */
function jrc_job_upsert_external( $user_id, $source, $external_ref, $row ) {
	global $wpdb;
	$source       = sanitize_key( $source );
	$external_ref = sanitize_text_field( $external_ref );

	$existing = $wpdb->get_var( $wpdb->prepare(
		'SELECT id FROM ' . jrc_jobs_table() . ' WHERE source = %s AND external_ref = %s LIMIT 1',
		$source, $external_ref
	) );

	$row['source']       = $source;
	$row['external_ref'] = $external_ref;

	if ( $existing ) {
		jrc_job_update( $existing, $row );
		return (int) $existing;
	}
	return jrc_job_insert( $user_id, $row );
}

/**
 * Query jobs.
 *
 * @param array $args user_id, status, job_type, channel, subtype, date_from, date_to,
 *                    date_field (job_date|scheduled_date), search, orderby, order,
 *                    limit, offset, count_only.
 */
function jrc_jobs_query( $args = array() ) {
	global $wpdb;

	$args = wp_parse_args( $args, array(
		'user_id'    => 0,
		'status'     => '',
		'job_type'   => '',
		'channel'    => '',
		'subtype'    => '',
		'date_from'  => '',
		'date_to'    => '',
		'date_field' => 'job_date',
		'search'     => '',
		'orderby'    => 'job_date',
		'order'      => 'DESC',
		'limit'      => 100,
		'offset'     => 0,
		'count_only' => false,
	) );

	$table = jrc_jobs_table();
	$where = array( '1=1' );
	$prep  = array();

	if ( $args['user_id'] ) {
		$where[] = 'j.user_id = %d';
		$prep[]  = (int) $args['user_id'];
	}
	if ( '' !== $args['status'] ) {
		$where[] = 'j.status = %s';
		$prep[]  = $args['status'];
	}
	if ( '' !== $args['job_type'] ) {
		$where[] = 'j.job_type = %s';
		$prep[]  = $args['job_type'];
	}

	// Only two date columns are ever valid here; anything else is a coding error, not
	// user input, but whitelist regardless since this string is interpolated.
	$date_field = in_array( $args['date_field'], array( 'job_date', 'scheduled_date' ), true ) ? $args['date_field'] : 'job_date';

	if ( $args['date_from'] ) {
		$where[] = "j.$date_field >= %s";
		$prep[]  = gmdate( 'Y-m-d', strtotime( $args['date_from'] ) );
	}
	if ( $args['date_to'] ) {
		$where[] = "j.$date_field <= %s";
		$prep[]  = gmdate( 'Y-m-d 23:59:59', strtotime( $args['date_to'] ) );
	}
	if ( '' !== $args['search'] ) {
		$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		$where[] = '(j.address LIKE %s OR j.scope LIKE %s OR j.job_type LIKE %s)';
		array_push( $prep, $like, $like, $like );
	}

	// Channel and sub-type live in user meta, so join rather than duplicate them onto
	// every job row — they change on the account, not per job.
	$join = '';
	if ( $args['channel'] ) {
		$join   .= " INNER JOIN {$wpdb->usermeta} umc ON umc.user_id = j.user_id AND umc.meta_key = 'jrc_affiliate_channel' ";
		$where[] = 'umc.meta_value = %s';
		$prep[]  = $args['channel'];
	}
	if ( $args['subtype'] ) {
		$join   .= " INNER JOIN {$wpdb->usermeta} ums ON ums.user_id = j.user_id AND ums.meta_key = 'jrc_affiliate_subtype' ";
		$where[] = 'ums.meta_value = %s';
		$prep[]  = $args['subtype'];
	}

	$sql_where = implode( ' AND ', $where );

	if ( $args['count_only'] ) {
		$sql = "SELECT COUNT(*) FROM $table j $join WHERE $sql_where";
		return (int) $wpdb->get_var( $prep ? $wpdb->prepare( $sql, $prep ) : $sql );
	}

	$allowed_orderby = array( 'job_date', 'scheduled_date', 'value', 'status', 'job_type', 'address', 'id', 'user_id' );
	$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'job_date';
	$order   = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';

	$sql = "SELECT j.* FROM $table j $join WHERE $sql_where ORDER BY j.$orderby $order LIMIT %d OFFSET %d";
	array_push( $prep, (int) $args['limit'], (int) $args['offset'] );

	return $wpdb->get_results( $wpdb->prepare( $sql, $prep ) );
}

/**
 * Back-compatible reader: the original array shape, straight off the table.
 *
 * The dashboard and the profile screen still call this, so its output must keep the
 * same keys it had when jobs were user meta.
 *
 * @param int $user_id Affiliate.
 * @param int $limit   Maximum rows. The profile screen passes a much larger number:
 *                     it saves back what it rendered, so it must render everything.
 */
function jrc_affiliate_jobs( $user_id, $limit = 500 ) {
	$rows = jrc_jobs_query( array( 'user_id' => (int) $user_id, 'limit' => max( 1, (int) $limit ), 'orderby' => 'job_date', 'order' => 'DESC' ) );

	$out = array();
	foreach ( (array) $rows as $r ) {
		$out[] = array(
			'id'             => (int) $r->id,
			'date'           => $r->job_date ? $r->job_date : '',
			'scheduled_date' => $r->scheduled_date ? $r->scheduled_date : '',
			'type'           => $r->job_type,
			'address'        => $r->address,
			'scope'          => (string) $r->scope,
			'value'          => 0.0 !== (float) $r->value ? '£' . rtrim( rtrim( number_format( (float) $r->value, 2 ), '0' ), '.' ) : '',
			'status'         => $r->status,
			'free_bonus'     => $r->free_bonus,
			'source'         => $r->source,
			'report_file'    => $r->report_file ?? '',
			'report_name'    => $r->report_name ?? '',
			'report_uploaded' => $r->report_uploaded ?? '',
		);
	}
	return $out;
}

/** Distinct statuses actually present, for filter dropdowns. */
function jrc_jobs_distinct( $column ) {
	global $wpdb;
	$allowed = array( 'status', 'job_type', 'source' );
	if ( ! in_array( $column, $allowed, true ) ) {
		return array();
	}
	return $wpdb->get_col( "SELECT DISTINCT $column FROM " . jrc_jobs_table() . " WHERE $column != '' ORDER BY $column ASC" );
}

/**
 * Scheduled cleans still to come for one affiliate.
 *
 * Powers the "Upcoming cleans" panel on the partner dashboard. Cancelled work is
 * dropped rather than shown struck through — a partner looking at their own diary
 * wants what is happening, not what isn't.
 *
 * @param int $user_id Affiliate.
 * @param int $limit   Maximum rows.
 * @return array Rows from jrc_jobs_query(), soonest first.
 */
function jrc_jobs_upcoming( $user_id, $limit = 12 ) {
	$rows = jrc_jobs_query( array(
		'user_id'    => (int) $user_id,
		'date_field' => 'scheduled_date',
		'date_from'  => current_time( 'Y-m-d' ),
		'orderby'    => 'scheduled_date',
		'order'      => 'ASC',
		'limit'      => (int) $limit + 20,
	) );

	$out = array();
	foreach ( $rows as $r ) {
		if ( ! $r->scheduled_date || 0 === strcasecmp( $r->status, 'Cancelled' ) ) {
			continue;
		}
		$out[] = $r;
	}

	/**
	 * Filter the rows before they are trimmed to the limit.
	 *
	 * Cancelled work is dropped above because that rule has always lived here.
	 * Anything else that is "on the calendar but not a confirmed booking" — a
	 * partner-submitted request, say — is filtered by whichever file owns that idea,
	 * so this function does not have to know about them.
	 *
	 * @param array $out     Candidate rows, soonest first.
	 * @param int   $user_id Affiliate.
	 */
	$out = (array) apply_filters( 'jrc_jobs_upcoming_rows', $out, (int) $user_id );

	return array_slice( $out, 0, (int) $limit );
}

/** Count of scheduled, non-cancelled cleans still to come. */
function jrc_jobs_upcoming_count( $user_id ) {
	return count( jrc_jobs_upcoming( $user_id, 200 ) );
}

/**
 * One affiliate's scheduled cleans for a given month, bucketed by day.
 *
 * @param int    $user_id Affiliate.
 * @param string $ym      Month as Y-m.
 * @return array Y-m-d => array of job rows, each day sorted by time.
 */
function jrc_jobs_month_by_day( $user_id, $ym ) {
	$start = strtotime( $ym . '-01' );
	if ( ! $start ) {
		$start = strtotime( current_time( 'Y-m' ) . '-01' );
	}

	$rows = jrc_jobs_query( array(
		'user_id'    => (int) $user_id,
		'date_field' => 'scheduled_date',
		'date_from'  => gmdate( 'Y-m-01', $start ),
		'date_to'    => gmdate( 'Y-m-t', $start ),
		'orderby'    => 'scheduled_date',
		'order'      => 'ASC',
		'limit'      => 500,
	) );

	$byday = array();
	foreach ( $rows as $r ) {
		if ( ! $r->scheduled_date ) {
			continue;
		}
		$byday[ gmdate( 'Y-m-d', strtotime( $r->scheduled_date ) ) ][] = $r;
	}
	return $byday;
}
