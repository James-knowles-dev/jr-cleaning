<?php
/**
 * Connecteam API client: credentials, transport, and the rate-limit budget.
 *
 * JR Cleaning schedule their cleaners in Connecteam. Until now WordPress has had no
 * idea any of that exists — the partner calendar shows whatever an administrator
 * typed into a profile screen, which is why 29 of 93 jobs have no scheduled slot at
 * all. This file is the bottom layer of closing that gap.
 *
 * THE RATE LIMIT IS THE DESIGN CONSTRAINT, not an afterthought. Connecteam's smallest
 * plan allows 5 requests a minute and 100 a DAY across the whole integration. A naive
 * poller that walks a year of shifts a page at a time would exhaust a day's budget
 * before lunch and then silently fail every other call the site makes — including an
 * administrator clicking "test connection" and wondering why it is broken.
 *
 * So every request goes through jrc_ct_request(), which:
 *
 *   1. Keeps its own count of what has been spent this minute and today, seeded from
 *      the x-ratelimit-* headers whenever the API tells us the truth.
 *   2. Refuses background work once the daily budget drops below a reserve, so a cron
 *      job can never starve an administrator out of the admin screens.
 *   3. Backs off exponentially on 429 rather than hammering a closed door.
 *
 * The reserve in point 2 is the important one. Background and interactive requests are
 * not equally valuable: a missed reconciliation run costs nothing (the next one picks
 * the same shifts up), whereas an administrator who cannot load the settings page has
 * no way to diagnose why.
 *
 * CREDENTIALS. The key is read from a constant first and an option second. wp-config.php
 * is Cloudways-owned and excluded from deploys (see deploy/rsync-exclude.txt), so the
 * constant cannot be relied on alone — but where it is available it keeps the key out
 * of the database entirely, which is the better place for it not to be.
 *
 * @package jrc-connecteam
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Where settings live. Not autoloaded: read only by the sync and the admin screens. */
const JRC_CT_OPTION = 'jrc_connecteam_settings';

/** Rolling record of what the rate limit has left. */
const JRC_CT_BUDGET_OPTION = 'jrc_connecteam_budget';

/**
 * Daily requests held back for interactive use.
 *
 * On the 100/day plan this leaves a quarter of the budget for a human. It is a
 * proportion rather than a fixed number so it stays sensible on the 10,000/day plans,
 * where 25 reserved requests would be meaningless.
 */
function jrc_ct_reserve_fraction() {
	return (float) apply_filters( 'jrc_ct_reserve_fraction', 0.25 );
}

// ---------------------------------------------------------------------------
// Settings
// ---------------------------------------------------------------------------

/** Everything configurable about the integration, defaults filled in. */
function jrc_ct_settings() {
	$stored = jrc_ct_read_settings_row();

	return wp_parse_args( $stored, array(
		'api_key'          => '',
		'region'           => 'global',   // global | au
		'scheduler_id'     => '',
		'partner_field_id' => '',         // Job custom field carrying the partner reference
		'partner_field_by' => 'code',     // code | user_id
		// Shift custom field holding notes the partner is MEANT to read. Empty means no
		// notes are imported at all — see the header of connecteam-map.php for why
		// shift.notes is never used for this.
		'notes_field_id'   => '',
		// Custom field (shift or job) holding the price of the clean. Empty = never import
		// a value; whatever was typed in WordPress stays.
		'value_field_id'   => '',
		// Custom field naming the building a shift is for (School accounts' site map).
		'building_field_id' => '',
		// Off until someone has looked at real shift titles and confirmed they do not
		// name staff. Job titles are used regardless; this is only the fallback.
		'use_shift_titles' => 0,
		'webhook_secret'   => '',
		'webhook_id'       => '',
		'enabled'          => 0,          // master switch for writing anything
		'import_unlinked'  => 1,          // keep JR's own work in the table too
		'complete_sets_date' => 1,        // a completed shift sets job_date
		'last_sync'        => 0,
		'last_error'       => '',
	) );
}

/**
 * The stored settings, straight from the database.
 *
 * Deliberately not get_option(): with a persistent object cache (Redis on Cloudways),
 * a copy cached under one key namespace can outlive a write made under another — a
 * WP-CLI run, a different cache salt — and the next save would merge the stale copy
 * and write it back. That once wiped the webhook secret on live, silently turning every
 * delivery away. This is read on admin screens, syncs and webhooks only, so one small
 * query per request is nothing.
 */
function jrc_ct_read_settings_row( $fresh = false ) {
	static $cache = null;
	if ( null !== $cache && ! $fresh ) {
		return $cache;
	}
	global $wpdb;
	$raw   = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s LIMIT 1", JRC_CT_OPTION ) );
	$val   = null === $raw ? array() : maybe_unserialize( $raw );
	$cache = is_array( $val ) ? $val : array();
	return $cache;
}

/** Save a subset of settings, leaving the rest alone. */
function jrc_ct_update_settings( array $changes ) {
	// Merge onto what the database holds now, never onto a cached copy.
	$next = array_merge( wp_parse_args( jrc_ct_read_settings_row( true ), jrc_ct_settings() ), $changes );
	// autoload false: this is read on two admin screens and a cron, never on a front-end
	// request, and it holds a credential.
	// update_option() skips the write when the new value equals its CACHED copy, so the
	// cached copy goes first.
	wp_cache_delete( JRC_CT_OPTION, 'options' );
	$ok = update_option( JRC_CT_OPTION, $next, false );
	wp_cache_delete( JRC_CT_OPTION, 'options' );
	jrc_ct_read_settings_row( true );
	return $ok;
}

/**
 * The API key.
 *
 * Constant wins, so a site that can define one keeps the key out of the database.
 */
function jrc_ct_api_key() {
	if ( defined( 'JRC_CONNECTEAM_API_KEY' ) && JRC_CONNECTEAM_API_KEY ) {
		return (string) JRC_CONNECTEAM_API_KEY;
	}
	$s = jrc_ct_settings();
	return (string) $s['api_key'];
}

/** Is the key coming from wp-config rather than the database? */
function jrc_ct_key_is_constant() {
	return defined( 'JRC_CONNECTEAM_API_KEY' ) && JRC_CONNECTEAM_API_KEY;
}

/** Regional base URL. Connecteam host Australian accounts separately. */
function jrc_ct_base_url() {
	$s = jrc_ct_settings();
	return 'au' === $s['region']
		? 'https://api-au.connecteam.com'
		: 'https://api.connecteam.com';
}

/** Enough configured to talk to the API at all? */
function jrc_ct_configured() {
	return '' !== jrc_ct_api_key();
}

/** Enough configured, and switched on, to write rows? */
function jrc_ct_active() {
	$s = jrc_ct_settings();
	return jrc_ct_configured() && ! empty( $s['enabled'] ) && ! empty( $s['scheduler_id'] );
}

// ---------------------------------------------------------------------------
// Rate-limit budget
// ---------------------------------------------------------------------------

/**
 * What we believe is left of the rate limit.
 *
 * Two sources, in order of trust: the headers Connecteam returned on the last call,
 * and our own count since then. The headers are authoritative but only arrive after a
 * request, so between calls we decrement locally and re-sync on the next response.
 *
 * @return array{minute_remaining:int,minute_reset:int,day_remaining:int,day_reset:int,day_limit:int,known:bool}
 */
function jrc_ct_budget() {
	$b = get_option( JRC_CT_BUDGET_OPTION, array() );
	$b = is_array( $b ) ? $b : array();

	$b = wp_parse_args( $b, array(
		'minute_remaining' => 5,
		'minute_reset'     => 0,
		'day_remaining'    => 100,
		'day_reset'        => 0,
		'day_limit'        => 100,
		'known'            => false,
	) );

	$now = time();

	// A window that has already reset is a full window again. Assuming the smallest
	// plan's allowance until the API says otherwise is the safe direction to be wrong in.
	if ( $b['minute_reset'] && $now >= $b['minute_reset'] ) {
		$b['minute_remaining'] = max( (int) $b['minute_remaining'], 5 );
		$b['minute_reset']     = 0;
	}
	if ( $b['day_reset'] && $now >= $b['day_reset'] ) {
		$b['day_remaining'] = (int) $b['day_limit'];
		$b['day_reset']     = 0;
	}

	return $b;
}

/** Record what the API just told us about the budget. */
function jrc_ct_absorb_headers( $headers ) {
	$get = function ( $name ) use ( $headers ) {
		if ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
			$v = $headers[ $name ] ?? null;
		} else {
			$v = is_array( $headers ) ? ( $headers[ $name ] ?? null ) : null;
		}
		return ( null === $v || '' === $v ) ? null : (int) $v;
	};

	$b   = jrc_ct_budget();
	$any = false;

	foreach ( array(
		'x-ratelimit-minute-remaining' => 'minute_remaining',
		'x-ratelimit-minute-reset'     => 'minute_reset',
		'x-ratelimit-day-remaining'    => 'day_remaining',
		'x-ratelimit-day-reset'        => 'day_reset',
		'x-ratelimit-day-limit'        => 'day_limit',
	) as $header => $key ) {
		$v = $get( $header );
		if ( null !== $v ) {
			$b[ $key ] = $v;
			$any       = true;
		}
	}

	if ( $any ) {
		$b['known'] = true;
	}

	update_option( JRC_CT_BUDGET_OPTION, $b, false );
	return $b;
}

/** Spend one request locally, for the window between responses. */
function jrc_ct_spend() {
	$b = jrc_ct_budget();
	$b['minute_remaining'] = max( 0, (int) $b['minute_remaining'] - 1 );
	$b['day_remaining']    = max( 0, (int) $b['day_remaining'] - 1 );
	update_option( JRC_CT_BUDGET_OPTION, $b, false );
}

/**
 * May a request of this priority go ahead?
 *
 * @param string $priority 'interactive' (a person is waiting) or 'background' (cron).
 * @return true|WP_Error
 */
function jrc_ct_budget_allows( $priority = 'interactive' ) {
	$b = jrc_ct_budget();

	if ( $b['minute_remaining'] < 1 ) {
		$wait = $b['minute_reset'] ? max( 1, $b['minute_reset'] - time() ) : 60;
		return new WP_Error( 'jrc_ct_minute_budget', sprintf(
			/* translators: %d: seconds */
			__( 'Connecteam rate limit reached for this minute. Try again in %d seconds.', 'jrc-connecteam' ),
			$wait
		) );
	}

	if ( 'background' === $priority ) {
		$reserve = (int) ceil( $b['day_limit'] * jrc_ct_reserve_fraction() );
		if ( $b['day_remaining'] <= $reserve ) {
			return new WP_Error( 'jrc_ct_day_reserve', sprintf(
				/* translators: 1: remaining requests, 2: reserved requests */
				__( 'Connecteam daily budget down to %1$d requests; the last %2$d are held back for use in the admin. Background sync will resume tomorrow.', 'jrc-connecteam' ),
				$b['day_remaining'],
				$reserve
			) );
		}
	}

	if ( $b['day_remaining'] < 1 ) {
		return new WP_Error( 'jrc_ct_day_budget', __( 'Connecteam daily rate limit reached. It resets at midnight UTC.', 'jrc-connecteam' ) );
	}

	return true;
}

// ---------------------------------------------------------------------------
// Transport
// ---------------------------------------------------------------------------

/**
 * One API call.
 *
 * @param string $method GET/POST/PUT/DELETE.
 * @param string $path   Path beginning with a slash, e.g. '/scheduler/v1/schedulers'.
 * @param array  $args   query, body, priority, retries, timeout.
 * @return array|WP_Error Decoded response body.
 */
function jrc_ct_request( $method, $path, $args = array() ) {
	$args = wp_parse_args( $args, array(
		'query'    => array(),
		'body'     => null,
		'priority' => 'interactive',
		'retries'  => 2,
		'timeout'  => 20,
	) );

	if ( ! jrc_ct_configured() ) {
		return new WP_Error( 'jrc_ct_no_key', __( 'No Connecteam API key is configured.', 'jrc-connecteam' ) );
	}

	$allowed = jrc_ct_budget_allows( $args['priority'] );
	if ( is_wp_error( $allowed ) ) {
		return $allowed;
	}

	$url = jrc_ct_base_url() . $path;
	if ( $args['query'] ) {
		// Array parameters are sent comma-joined (jobIds=a,b) rather than as
		// http_build_query's jobIds[0]=a — see jrc_ct_flatten_query().
		$url = add_query_arg( jrc_ct_flatten_query( $args['query'] ), $url );
	}

	$request = array(
		'method'  => strtoupper( $method ),
		'timeout' => (int) $args['timeout'],
		'headers' => array(
			'X-API-KEY' => jrc_ct_api_key(),
			'Accept'    => 'application/json',
		),
	);

	if ( null !== $args['body'] ) {
		$request['headers']['Content-Type'] = 'application/json';
		$request['body']                    = wp_json_encode( $args['body'] );
	}

	$attempt = 0;

	do {
		$response = wp_remote_request( $url, $request );
		jrc_ct_spend();

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		jrc_ct_absorb_headers( wp_remote_retrieve_headers( $response ) );

		if ( 429 !== $code ) {
			break;
		}

		// Exponential backoff, but never inside a web request a person is waiting on —
		// a 30-second sleep on an admin screen looks like a hung site. Interactive
		// callers get the error and can press the button again.
		$attempt++;
		if ( 'background' !== $args['priority'] || $attempt > (int) $args['retries'] ) {
			return new WP_Error( 'jrc_ct_rate_limited', __( 'Connecteam returned 429 (rate limited).', 'jrc-connecteam' ) );
		}
		sleep( min( 30, (int) pow( 2, $attempt ) * 5 ) );

	} while ( $attempt <= (int) $args['retries'] );

	$raw  = wp_remote_retrieve_body( $response );
	$data = json_decode( $raw, true );

	if ( $code < 200 || $code >= 300 ) {
		// Connecteam returns a JSON error body; surface its message rather than a bare
		// status code, because "422 Unprocessable" tells an administrator nothing.
		$message = '';
		if ( is_array( $data ) ) {
			$message = (string) ( $data['message'] ?? $data['detail'] ?? '' );
			if ( '' === $message && ! empty( $data['errors'] ) ) {
				$message = wp_json_encode( $data['errors'] );
			}
		}
		$message = $message ? $message : substr( wp_strip_all_tags( $raw ), 0, 300 );

		return new WP_Error(
			'jrc_ct_http_' . $code,
			sprintf( 'Connecteam API error %d: %s', $code, $message ),
			array( 'status' => $code )
		);
	}

	if ( null === $data && '' !== trim( $raw ) ) {
		return new WP_Error( 'jrc_ct_bad_json', __( 'Connecteam returned a response that was not valid JSON.', 'jrc-connecteam' ) );
	}

	return is_array( $data ) ? $data : array();
}

/**
 * Flatten a query array into scalar values add_query_arg() can send as-is.
 *
 * Arrays are comma-joined (jobIds=a,b). add_query_arg() would otherwise render
 * array( 'jobIds' => array( 'a', 'b' ) ) as jobIds[0]=a&jobIds[1]=b, which the API reads
 * as one parameter named "jobIds[0]". Booleans become 'true'/'false'; null and empty
 * strings are dropped. No call in this plugin passes an array today — if one is added,
 * check Connecteam accepts the comma form for that endpoint.
 */
function jrc_ct_flatten_query( array $query ) {
	$out = array();
	foreach ( $query as $key => $value ) {
		if ( is_bool( $value ) ) {
			$out[ $key ] = $value ? 'true' : 'false';
			continue;
		}
		if ( is_array( $value ) ) {
			$out[ $key ] = implode( ',', array_map( 'strval', $value ) );
			continue;
		}
		if ( null === $value || '' === $value ) {
			continue;
		}
		$out[ $key ] = $value;
	}
	return $out;
}

/**
 * Walk an offset-paginated list endpoint.
 *
 * Capped by $max_pages rather than running to exhaustion: on the 100/day plan an
 * unbounded loop over a large date range is the single easiest way to lose a whole
 * day's budget to one mistake.
 *
 * @param string $path      Endpoint path.
 * @param array  $query     Query parameters (limit is set here).
 * @param string $data_key  Key inside `data` holding the array, e.g. 'shifts'.
 * @param array  $args      priority, max_pages, per_page.
 * @return array|WP_Error Flat array of items.
 */
function jrc_ct_get_all( $path, $query, $data_key, $args = array() ) {
	$args = wp_parse_args( $args, array(
		'priority'  => 'interactive',
		'max_pages' => 10,
		'per_page'  => 500,   // the documented maximum; fewer requests for the same rows
	) );

	$items  = array();
	$offset = 0;

	for ( $page = 0; $page < (int) $args['max_pages']; $page++ ) {
		$response = jrc_ct_request( 'GET', $path, array(
			'query'    => array_merge( $query, array(
				'limit'  => (int) $args['per_page'],
				'offset' => $offset,
			) ),
			'priority' => $args['priority'],
		) );

		if ( is_wp_error( $response ) ) {
			// Partial results are still useful to a reconciliation run — return what we
			// have if we got anything, and let the caller decide.
			return $items ? $items : $response;
		}

		$batch = $response['data'][ $data_key ] ?? array();
		if ( ! is_array( $batch ) || ! $batch ) {
			break;
		}

		$items = array_merge( $items, $batch );

		if ( count( $batch ) < (int) $args['per_page'] ) {
			break;   // short page means we are done
		}

		// The API returns the offset to continue from; fall back to counting if absent.
		$next   = isset( $response['paging']['offset'] ) ? (int) $response['paging']['offset'] : 0;
		$offset = $next > $offset ? $next : $offset + count( $batch );
	}

	return $items;
}

// ---------------------------------------------------------------------------
// Endpoints we actually use
// ---------------------------------------------------------------------------

/**
 * Cached reads for the things that barely ever change.
 *
 * Schedulers, jobs and custom-field definitions are configuration, not data. Re-reading
 * them on every sync would spend a meaningful slice of a 100/day budget on answers that
 * were the same yesterday.
 */
function jrc_ct_cached( $key, $ttl, callable $fetch ) {
	$cache_key = 'jrc_ct_' . md5( $key );
	$hit       = get_transient( $cache_key );

	if ( false !== $hit ) {
		return $hit;
	}

	$value = $fetch();
	if ( ! is_wp_error( $value ) ) {
		set_transient( $cache_key, $value, $ttl );
		jrc_ct_remember_cache_key( $key );
	}
	return $value;
}

/** Option holding every key jrc_ct_cached() has written, so they can be cleared by name. */
const JRC_CT_CACHE_KEYS_OPTION = 'jrc_connecteam_cache_keys';

/** Record a cache key. Only runs on a cache miss, so the write is rare. */
function jrc_ct_remember_cache_key( $key ) {
	$keys = (array) get_option( JRC_CT_CACHE_KEYS_OPTION, array() );
	if ( ! in_array( $key, $keys, true ) ) {
		$keys[] = $key;
		update_option( JRC_CT_CACHE_KEYS_OPTION, $keys, false );
	}
}

/**
 * Drop every cached Connecteam read.
 *
 * Deletes each transient by name. This used to run a LIKE delete against wp_options and
 * then wp_cache_flush() — but with Redis as the object cache transients never touch
 * wp_options, so the delete did nothing and the flush emptied the ENTIRE site cache
 * (every page fragment, query and session) to clear three entries.
 */
function jrc_ct_flush_cache() {
	$keys = array_unique( array_merge(
		array( 'schedulers', 'job_custom_fields', 'jobs_index' ),
		(array) get_option( JRC_CT_CACHE_KEYS_OPTION, array() )
	) );
	foreach ( $keys as $key ) {
		delete_transient( 'jrc_ct_' . md5( (string) $key ) );
	}
}

/** Every scheduler on the account. */
function jrc_ct_schedulers( $fresh = false ) {
	if ( $fresh ) {
		delete_transient( 'jrc_ct_' . md5( 'schedulers' ) );
	}
	return jrc_ct_cached( 'schedulers', DAY_IN_SECONDS, function () {
		$r = jrc_ct_request( 'GET', '/scheduler/v1/schedulers' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		return $r['data']['schedulers'] ?? array();
	} );
}

/** Job custom-field definitions, for the settings dropdown. */
function jrc_ct_job_custom_fields( $fresh = false ) {
	if ( $fresh ) {
		delete_transient( 'jrc_ct_' . md5( 'job_custom_fields' ) );
	}
	return jrc_ct_cached( 'job_custom_fields', DAY_IN_SECONDS, function () {
		$r = jrc_ct_request( 'GET', '/jobs/v1/custom_fields' );
		if ( is_wp_error( $r ) ) {
			return $r;
		}
		// The key has moved between doc revisions; accept either shape rather than
		// breaking on a rename.
		return $r['data']['customFields'] ?? ( $r['data']['custom_fields'] ?? array() );
	} );
}

/**
 * Every job, indexed by jobId, with sub-jobs flattened.
 *
 * Sub-jobs arrive nested inside their parent. The mapper needs to look a job up by the
 * id a shift refers to, and that id may be either a parent or a child, so the tree is
 * flattened once here and each child keeps a pointer to its parent for the attribution
 * fallback in jrc_ct_resolve_partner().
 */
function jrc_ct_jobs_index( $fresh = false ) {
	if ( $fresh ) {
		delete_transient( 'jrc_ct_' . md5( 'jobs_index' ) );
	}

	return jrc_ct_cached( 'jobs_index', 6 * HOUR_IN_SECONDS, function () {
		$jobs = jrc_ct_get_all( '/jobs/v1/jobs', array( 'includeDeleted' => false ), 'jobs', array( 'max_pages' => 6 ) );
		if ( is_wp_error( $jobs ) ) {
			return $jobs;
		}

		$index = array();

		$walk = function ( $list, $parent_id ) use ( &$walk, &$index ) {
			foreach ( (array) $list as $job ) {
				if ( empty( $job['jobId'] ) ) {
					continue;
				}
				$job['_parentId']      = $parent_id;
				$children              = $job['subJobs'] ?? array();
				unset( $job['subJobs'] );   // keep the index flat and small
				$index[ $job['jobId'] ] = $job;

				if ( $children ) {
					$walk( $children, $job['jobId'] );
				}
			}
		};

		$walk( $jobs, '' );
		return $index;
	} );
}

/**
 * Shifts overlapping a window.
 *
 * @param int   $from Unix seconds.
 * @param int   $to   Unix seconds.
 * @param array $args priority, max_pages, extra query parameters.
 */
function jrc_ct_shifts( $from, $to, $args = array() ) {
	$s = jrc_ct_settings();
	if ( empty( $s['scheduler_id'] ) ) {
		return new WP_Error( 'jrc_ct_no_scheduler', __( 'No Connecteam scheduler has been selected.', 'jrc-connecteam' ) );
	}

	$query = array_merge( array(
		'startTime' => (int) $from,
		'endTime'   => (int) $to,
		'sort'      => 'updated_at',
		'order'     => 'desc',
	), (array) ( $args['query'] ?? array() ) );

	return jrc_ct_get_all(
		'/scheduler/v1/schedulers/' . rawurlencode( $s['scheduler_id'] ) . '/shifts',
		$query,
		'shifts',
		array(
			'priority'  => $args['priority'] ?? 'interactive',
			'max_pages' => $args['max_pages'] ?? 6,
		)
	);
}

/**
 * A cheap call that proves the key works.
 *
 * Deliberately the schedulers endpoint: it is the smallest useful response and it is
 * the same call the settings screen needs anyway to populate its dropdown.
 */
function jrc_ct_test_connection() {
	$r = jrc_ct_request( 'GET', '/scheduler/v1/schedulers', array( 'query' => array( 'limit' => 1 ) ) );
	if ( is_wp_error( $r ) ) {
		return $r;
	}
	return array(
		'ok'     => true,
		'budget' => jrc_ct_budget(),
	);
}
