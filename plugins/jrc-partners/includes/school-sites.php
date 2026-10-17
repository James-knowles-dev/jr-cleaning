<?php
/**
 * School sites: buildings on a map, each showing when and how often it is cleaned.
 *
 * A high school is not one address. Prince Henry's in Evesham has a main building, a
 * science block, a sports complex, a sixth form centre and so on, each cleaned on its own
 * days at its own times. School accounts get a Site map tab: an OpenStreetMap map of the
 * campus with a marker per building, and a panel per building with its cleaning pattern
 * ("Mon, Wed, Fri · 16:00–18:00"), the next cleans and recent reports.
 *
 * DATA (user meta on the school's account)
 *   jrc_school_buildings  list of { id, name, aliases[], lat, lng, notes, schedule_note }
 *   jrc_school_map        { lat, lng, zoom } — where the map opens
 *
 * WHICH BUILDING A CLEAN IS FOR
 *   The office picks it in a "Building" custom field on the Connecteam shift. The
 *   Connecteam integration stores that as external_meta['building']; a clean requested
 *   through the account form stores the building the school chose. It is matched to a
 *   building by name or by one of its aliases, ignoring case and punctuation, so
 *   "Science block" and "SCIENCE BLOCK" are the same building.
 *
 * FREQUENCY is worked out from the cleans themselves (jrc_school_schedule()), so it is
 * always what is actually booked, not what someone remembered to type. A building can
 * carry a schedule note ("Daily in term time") that replaces the summary line.
 *
 * MAP TILES come from openstreetmap.org, loaded by the visitor's browser (no cookies;
 * their IP reaches OSM). Leaflet is loaded from cdnjs, only on the Site map tab and on a
 * school's admin profile.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const JRC_LEAFLET_VER = '1.9.4';

/** Is this account a School? */
function jrc_is_school( $user_id = 0 ) {
	$p = jrc_affiliate_profile( $user_id );
	return 'school' === ( $p['view'] ?? '' );
}

/** Normalised text for matching building names: case, punctuation and spacing ignored. */
function jrc_school_norm( $text ) {
	return trim( preg_replace( '/[^a-z0-9]+/', ' ', strtolower( (string) $text ) ) );
}

/**
 * A school's buildings, cleaned up.
 *
 * @return array[] each: id, name, aliases (array), lat, lng (float|null), notes, schedule_note
 */
function jrc_school_buildings( $user_id = 0 ) {
	$user_id = $user_id ? $user_id : get_current_user_id();
	$raw     = get_user_meta( $user_id, 'jrc_school_buildings', true );
	$out     = array();
	foreach ( (array) $raw as $b ) {
		$name = trim( (string) ( $b['name'] ?? '' ) );
		if ( '' === $name ) {
			continue;
		}
		$out[] = array(
			'id'            => sanitize_key( $b['id'] ?? '' ) ?: sanitize_title( $name ),
			'name'          => $name,
			'aliases'       => array_values( array_filter( array_map( 'trim', (array) ( $b['aliases'] ?? array() ) ) ) ),
			'lat'           => is_numeric( $b['lat'] ?? null ) ? (float) $b['lat'] : null,
			'lng'           => is_numeric( $b['lng'] ?? null ) ? (float) $b['lng'] : null,
			'notes'         => trim( (string) ( $b['notes'] ?? '' ) ),
			'schedule_note' => trim( (string) ( $b['schedule_note'] ?? '' ) ),
		);
	}
	return $out;
}

/** Where the map opens. Falls back to the buildings, then to Evesham. */
function jrc_school_map_view( $user_id = 0, $buildings = null ) {
	$user_id   = $user_id ? $user_id : get_current_user_id();
	$view      = (array) get_user_meta( $user_id, 'jrc_school_map', true );
	$buildings = null === $buildings ? jrc_school_buildings( $user_id ) : $buildings;

	if ( is_numeric( $view['lat'] ?? null ) && is_numeric( $view['lng'] ?? null ) ) {
		return array( 'lat' => (float) $view['lat'], 'lng' => (float) $view['lng'], 'zoom' => max( 12, min( 19, (int) ( $view['zoom'] ?? 17 ) ) ) );
	}
	$pins = array_filter( $buildings, function ( $b ) { return null !== $b['lat'] && null !== $b['lng']; } );
	if ( $pins ) {
		$lat = array_sum( array_column( $pins, 'lat' ) ) / count( $pins );
		$lng = array_sum( array_column( $pins, 'lng' ) ) / count( $pins );
		return array( 'lat' => $lat, 'lng' => $lng, 'zoom' => 17 );
	}
	return array( 'lat' => 52.0920, 'lng' => -1.9470, 'zoom' => 14 ); // Evesham.
}

/** The building name a job is for, as recorded on it ('' when none). */
function jrc_school_job_building_name( $job ) {
	$meta = function_exists( 'jrc_job_meta' ) ? jrc_job_meta( $job ) : array();
	if ( ! empty( $meta['building'] ) ) {
		return (string) $meta['building'];
	}
	// Older imports: the field is in the generic custom-field list under its own name.
	foreach ( (array) ( $meta['fields'] ?? array() ) as $name => $value ) {
		if ( 'building' === jrc_school_norm( $name ) ) {
			return (string) $value;
		}
	}
	return '';
}

/** Which of $buildings a job belongs to (building id, or ''). */
function jrc_school_job_building( $job, $buildings ) {
	$want = jrc_school_norm( jrc_school_job_building_name( $job ) );
	if ( '' === $want ) {
		return '';
	}
	foreach ( $buildings as $b ) {
		$names = array_merge( array( $b['name'] ), $b['aliases'] );
		foreach ( $names as $n ) {
			if ( jrc_school_norm( $n ) === $want ) {
				return $b['id'];
			}
		}
	}
	return '';
}

/**
 * Jobs in a window, grouped by building id ('' = not linked to a building).
 *
 * @return array building id => object[] (ascending by scheduled time)
 */
function jrc_school_jobs_by_building( $user_id, $buildings, $from_ts, $to_ts ) {
	$rows = jrc_jobs_query( array(
		'user_id'    => (int) $user_id,
		'date_field' => 'scheduled_date',
		'date_from'  => gmdate( 'Y-m-d', $from_ts ),
		'date_to'    => gmdate( 'Y-m-d', $to_ts ),
		'orderby'    => 'scheduled_date',
		'order'      => 'ASC',
		'limit'      => 2000,
	) );
	$out = array();
	foreach ( $buildings as $b ) {
		$out[ $b['id'] ] = array();
	}
	$out[''] = array();
	foreach ( $rows as $r ) {
		if ( empty( $r->scheduled_date ) ) {
			continue;
		}
		$out[ jrc_school_job_building( $r, $buildings ) ][] = $r;
	}
	return $out;
}

/**
 * The cleaning pattern of a set of jobs.
 *
 * Groups non-cancelled cleans by start–end time, then lists the weekdays that time recurs
 * on in at least two different weeks — so a one-off deep clean does not make the summary
 * claim a weekly slot. Mon–Fri collapses to "Weekdays", all seven to "Every day".
 *
 * @param object[] $jobs Jobs over a window (ideally ~12 weeks either side of today).
 * @return array lines (string[]), per_week (float), next (object[]), recent (object[])
 */
function jrc_school_schedule( $jobs ) {
	$now      = current_time( 'timestamp' );
	$slots    = array(); // "H:i–H:i" => [ weekday => [ iso-week => true ] ]
	$next     = array();
	$recent   = array();
	$upcoming = 0;
	$horizon  = $now + 56 * DAY_IN_SECONDS;

	foreach ( $jobs as $j ) {
		if ( 0 === strcasecmp( (string) $j->status, 'Cancelled' ) ) {
			continue;
		}
		$ts   = strtotime( $j->scheduled_date );
		$time = date( 'H:i', $ts ) . ( ! empty( $j->scheduled_end ) ? '–' . date( 'H:i', strtotime( $j->scheduled_end ) ) : '' ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- stored as local wall-clock time.
		$slots[ $time ][ (int) date( 'N', $ts ) ][ date( 'o-W', $ts ) ] = true; // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date

		if ( $ts >= $now ) {
			$next[] = $j;
			if ( $ts <= $horizon ) {
				$upcoming++;
			}
		} elseif ( 0 === strcasecmp( (string) $j->status, 'Completed' ) || $j->report_file ) {
			$recent[] = $j;
		}
	}

	$names = array( 1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun' );
	$lines = array();
	ksort( $slots );
	$order = array(); // line => sort key, so lines read in week order (Mon… before Sat).

	foreach ( $slots as $time => $days ) {
		$recurring = array();
		foreach ( $days as $dow => $weeks ) {
			if ( count( $weeks ) >= 2 ) {
				$recurring[] = $dow;
			}
		}
		if ( ! $recurring ) {
			continue;
		}
		sort( $recurring );
		if ( array( 1, 2, 3, 4, 5, 6, 7 ) === $recurring ) {
			$label = 'Every day';
		} elseif ( array( 1, 2, 3, 4, 5 ) === $recurring ) {
			$label = 'Weekdays';
		} else {
			$label = implode( ', ', array_map( function ( $d ) use ( $names ) { return $names[ $d ]; }, $recurring ) );
		}
		$line           = $label . ' · ' . $time;
		$lines[]        = $line;
		$order[ $line ] = sprintf( '%d-%s', $recurring[0], $time );
	}
	usort( $lines, function ( $x, $y ) use ( $order ) { return strcmp( $order[ $x ], $order[ $y ] ); } );

	return array(
		'lines'    => $lines,
		'per_week' => round( $upcoming / 8, 1 ),
		'next'     => array_slice( $next, 0, 5 ),
		'recent'   => array_slice( array_reverse( $recent ), 0, 3 ),
	);
}

/** "about 5 cleans a week" / "about 2 a month" / '' */
function jrc_school_rate_label( $per_week ) {
	if ( $per_week >= 1 ) {
		$n = (int) round( $per_week );
		return 'About ' . $n . ' ' . ( 1 === $n ? 'clean' : 'cleans' ) . ' a week';
	}
	if ( $per_week > 0 ) {
		$n = max( 1, (int) round( $per_week * 4.3 ) );
		return 'About ' . $n . ' ' . ( 1 === $n ? 'clean' : 'cleans' ) . ' a month';
	}
	return '';
}

/**
 * Everything the Site map needs, per building, ready for the template and the script.
 *
 * @return array view (lat,lng,zoom), buildings[], unlinked (int), request_url
 */
function jrc_school_site_data( $user_id = 0 ) {
	$user_id   = $user_id ? $user_id : get_current_user_id();
	$buildings = jrc_school_buildings( $user_id );
	$now       = current_time( 'timestamp' );
	$grouped   = jrc_school_jobs_by_building( $user_id, $buildings, $now - 28 * DAY_IN_SECONDS, $now + 84 * DAY_IN_SECONDS );
	$req_url   = jrc_affiliate_tab_url( 'request-clean' );

	$fmt_job = function ( $j ) {
		$out = array(
			'date'   => date_i18n( 'D j M', strtotime( $j->scheduled_date ) ),
			'time'   => date_i18n( 'H:i', strtotime( $j->scheduled_date ) ) . ( ! empty( $j->scheduled_end ) ? '–' . date_i18n( 'H:i', strtotime( $j->scheduled_end ) ) : '' ),
			'type'   => (string) ( $j->job_type ?: 'Clean' ),
			'status' => (string) $j->status,
		);
		if ( ! empty( $j->report_file ) && function_exists( 'jrc_report_url' ) ) {
			$out['report'] = html_entity_decode( jrc_report_url( (int) $j->id ) );
		}
		return $out;
	};

	$out = array();
	foreach ( $buildings as $b ) {
		$sched = jrc_school_schedule( $grouped[ $b['id'] ] ?? array() );
		$out[] = array(
			'id'          => $b['id'],
			'name'        => $b['name'],
			'lat'         => $b['lat'],
			'lng'         => $b['lng'],
			'notes'       => $b['notes'],
			'lines'       => $b['schedule_note'] ? array( $b['schedule_note'] ) : $sched['lines'],
			'rate'        => jrc_school_rate_label( $sched['per_week'] ),
			'next'        => array_map( $fmt_job, $sched['next'] ),
			'recent'      => array_map( $fmt_job, $sched['recent'] ),
			'request_url' => $req_url ? add_query_arg( 'building', $b['id'], $req_url ) : '',
		);
	}

	$unlinked_future = array_filter( $grouped[''] ?? array(), function ( $j ) use ( $now ) {
		return strtotime( $j->scheduled_date ) >= $now && 0 !== strcasecmp( (string) $j->status, 'Cancelled' );
	} );

	return array(
		'view'      => jrc_school_map_view( $user_id, $buildings ),
		'buildings' => $out,
		'unlinked'  => array_map( $fmt_job, array_slice( array_values( $unlinked_future ), 0, 5 ) ),
	);
}

// ---------------------------------------------------------------------------
// Leaflet
// ---------------------------------------------------------------------------

/** @param bool $in_footer false in the admin, where the editor script runs inline as the page renders. */
function jrc_school_enqueue_leaflet( $in_footer = true ) {
	$base = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/' . JRC_LEAFLET_VER . '/';
	wp_enqueue_style( 'leaflet', $base . 'leaflet.min.css', array(), JRC_LEAFLET_VER );
	wp_enqueue_script( 'leaflet', $base . 'leaflet.min.js', array(), JRC_LEAFLET_VER, $in_footer );
}

add_action( 'wp_enqueue_scripts', function () {
	// Not is_wc_endpoint_url(): WooCommerce only knows its own endpoints, and this tab is
	// registered with add_rewrite_endpoint(), so check the query var directly.
	global $wp;
	if ( ! function_exists( 'is_account_page' ) || ! is_account_page() || ! isset( $wp->query_vars['site-map'] ) || ! jrc_is_school() ) {
		return;
	}
	jrc_school_enqueue_leaflet();
	$js = JRCP_DIR . 'assets/site-map.js';
	if ( file_exists( $js ) ) {
		wp_enqueue_script( 'jrcp-site-map', JRCP_URL . 'assets/site-map.js', array( 'leaflet' ), filemtime( $js ), true );
	}
}, 20 );

// ---------------------------------------------------------------------------
// Admin: buildings editor on the school's user profile
// ---------------------------------------------------------------------------

add_action( 'admin_enqueue_scripts', function ( $hook ) {
	if ( ! in_array( $hook, array( 'user-edit.php', 'profile.php' ), true ) || ! current_user_can( 'edit_users' ) ) {
		return;
	}
	$uid = isset( $_GET['user_id'] ) ? (int) $_GET['user_id'] : get_current_user_id(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	if ( 'education' === jrc_affiliate_channel( $uid ) ) {
		jrc_school_enqueue_leaflet( false );
	}
} );

function jrc_school_profile_fields( $user ) {
	if ( ! current_user_can( 'edit_users' ) || 'education' !== jrc_affiliate_channel( $user->ID ) ) {
		return;
	}
	$buildings = jrc_school_buildings( $user->ID );
	$view      = jrc_school_map_view( $user->ID, $buildings );
	?>
	<h2 id="jrc-school-site">School site map</h2>
	<?php wp_nonce_field( 'jrc_school_' . $user->ID, 'jrc_school_nonce' ); ?>
	<input type="hidden" name="jrc_school_buildings_json" id="jrc-school-json" value="<?php echo esc_attr( wp_json_encode( $buildings ) ); ?>" />
	<input type="hidden" name="jrc_school_map_json" id="jrc-school-view" value="<?php echo esc_attr( wp_json_encode( $view ) ); ?>" />
	<table class="form-table" role="presentation">
		<tr>
			<th>Buildings</th>
			<td>
				<p class="description" style="max-width:62em;margin-bottom:8px;">
					Each building shows on the school's <strong>Site map</strong>. Add a building, then click the map to place its pin (drag to move).
					A clean is linked to a building by the <strong>Building</strong> field on the Connecteam shift — it must match the name here or one of its aliases (case and punctuation don't matter).
					The map opens wherever you leave it when you save.
				</p>
				<div id="jrc-school-map" style="height:380px;max-width:900px;border:1px solid #c3c4c7;border-radius:4px;"></div>
				<p class="description jrc-school-hint" style="margin:6px 0 10px;">&nbsp;</p>
				<table class="widefat striped" style="max-width:900px;" id="jrc-school-rows">
					<thead><tr><th style="width:24%">Building</th><th style="width:22%">Also called (comma separated)</th><th style="width:22%">Schedule note (optional)</th><th>Notes for the school</th><th style="width:118px">Pin</th><th style="width:30px"></th></tr></thead>
					<tbody></tbody>
				</table>
				<p><button type="button" class="button" id="jrc-school-add">+ Add building</button></p>
			</td>
		</tr>
	</table>
	<script>
	( function () {
		var json = document.getElementById( 'jrc-school-json' ), viewIn = document.getElementById( 'jrc-school-view' );
		var rows = document.querySelector( '#jrc-school-rows tbody' ), hint = document.querySelector( '.jrc-school-hint' );
		var data = []; try { data = JSON.parse( json.value ) || []; } catch ( e ) { data = []; }
		var view = JSON.parse( viewIn.value ), placing = null, markers = {};
		if ( typeof L === 'undefined' ) { hint.textContent = 'The map could not load (no internet?) — buildings can still be edited, pins kept as they are.'; }
		var map = typeof L !== 'undefined' ? L.map( 'jrc-school-map' ).setView( [ view.lat, view.lng ], view.zoom ) : null;
		if ( map ) {
			L.tileLayer( 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' } ).addTo( map );
			map.on( 'click', function ( e ) {
				if ( placing === null ) { return; }
				data[ placing ].lat = +e.latlng.lat.toFixed( 6 ); data[ placing ].lng = +e.latlng.lng.toFixed( 6 );
				placing = null; hint.innerHTML = '&nbsp;'; draw();
			} );
			map.on( 'moveend', function () { var c = map.getCenter(); viewIn.value = JSON.stringify( { lat: +c.lat.toFixed( 6 ), lng: +c.lng.toFixed( 6 ), zoom: map.getZoom() } ); } );
		}
		function slug( s ) { return ( s || '' ).toLowerCase().replace( /[^a-z0-9]+/g, '-' ).replace( /^-|-$/g, '' ); }
		function esc( s ) { var d = document.createElement( 'div' ); d.textContent = s == null ? '' : s; return d.innerHTML; }
		function sync() { json.value = JSON.stringify( data ); }
		function draw() {
			rows.innerHTML = '';
			Object.keys( markers ).forEach( function ( k ) { map && map.removeLayer( markers[ k ] ); } ); markers = {};
			data.forEach( function ( b, i ) {
				var tr = document.createElement( 'tr' );
				tr.innerHTML = '<td><input type="text" class="widefat" data-f="name" value="' + esc( b.name ) + '" placeholder="e.g. Science Block" /></td>'
					+ '<td><input type="text" class="widefat" data-f="aliases" value="' + esc( ( b.aliases || [] ).join( ', ' ) ) + '" placeholder="Other names, e.g. Block B" /></td>'
					+ '<td><input type="text" class="widefat" data-f="schedule_note" value="' + esc( b.schedule_note ) + '" placeholder="Worked out from cleans" /></td>'
					+ '<td><input type="text" class="widefat" data-f="notes" value="' + esc( b.notes ) + '" placeholder="e.g. Access via side gate" /></td>'
					+ '<td><button type="button" class="button button-small" data-place>' + ( b.lat != null ? 'Move pin' : 'Place pin' ) + '</button></td>'
					+ '<td><button type="button" class="button-link" data-del aria-label="Remove building" style="color:#b32d2e;font-size:18px;">&times;</button></td>';
				tr.querySelectorAll( 'input' ).forEach( function ( inp ) {
					inp.addEventListener( 'input', function () {
						var f = inp.dataset.f;
						b[ f ] = 'aliases' === f ? inp.value.split( ',' ).map( function ( s ) { return s.trim(); } ).filter( Boolean ) : inp.value;
						if ( 'name' === f && ! b.fixedId ) { b.id = slug( inp.value ); }
						if ( 'name' === f && markers[ i ] ) { markers[ i ].setTooltipContent( esc( inp.value ) ); }
						sync();
					} );
				} );
				tr.querySelector( '[data-place]' ).addEventListener( 'click', function () { placing = i; hint.textContent = 'Click the map where "' + ( b.name || 'this building' ) + '" is.'; } );
				tr.querySelector( '[data-del]' ).addEventListener( 'click', function () { if ( confirm( 'Remove ' + ( b.name || 'this building' ) + '?' ) ) { data.splice( i, 1 ); draw(); } } );
				rows.appendChild( tr );
				if ( map && b.lat != null ) {
					var m = L.marker( [ b.lat, b.lng ], { draggable: true } ).addTo( map ).bindTooltip( esc( b.name ), { permanent: true, direction: 'top', offset: [ -14, -14 ] } );
					m.on( 'dragend', function () { var p = m.getLatLng(); b.lat = +p.lat.toFixed( 6 ); b.lng = +p.lng.toFixed( 6 ); sync(); } );
					markers[ i ] = m;
				}
			} );
			sync();
		}
		data.forEach( function ( b ) { b.fixedId = !! b.id; } );
		document.getElementById( 'jrc-school-add' ).addEventListener( 'click', function () {
			data.push( { id: '', name: '', aliases: [], lat: null, lng: null, notes: '', schedule_note: '' } );
			draw(); placing = data.length - 1; hint.textContent = 'Name the building, then click the map where it is.';
			var inputs = rows.querySelectorAll( 'input[data-f="name"]' ); inputs[ inputs.length - 1 ].focus();
		} );
		draw();
	} )();
	</script>
	<?php
}
add_action( 'show_user_profile', 'jrc_school_profile_fields', 25 );
add_action( 'edit_user_profile', 'jrc_school_profile_fields', 25 );

function jrc_school_save_profile( $user_id ) {
	if ( ! current_user_can( 'edit_user', $user_id ) || ! current_user_can( 'edit_users' ) ) {
		return;
	}
	if ( ! isset( $_POST['jrc_school_nonce'], $_POST['jrc_school_buildings_json'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['jrc_school_nonce'] ) ), 'jrc_school_' . $user_id ) ) {
		return;
	}

	$in  = json_decode( wp_unslash( $_POST['jrc_school_buildings_json'] ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- decoded then sanitised field by field.
	$out = array();
	$ids = array();
	foreach ( (array) $in as $b ) {
		$name = sanitize_text_field( $b['name'] ?? '' );
		if ( '' === $name ) {
			continue;
		}
		$id = sanitize_key( $b['id'] ?? '' ) ?: sanitize_title( $name );
		while ( isset( $ids[ $id ] ) ) {
			$id .= '-2';
		}
		$ids[ $id ] = true;
		$lat   = is_numeric( $b['lat'] ?? null ) ? max( -90, min( 90, (float) $b['lat'] ) ) : null;
		$lng   = is_numeric( $b['lng'] ?? null ) ? max( -180, min( 180, (float) $b['lng'] ) ) : null;
		$out[] = array(
			'id'            => $id,
			'name'          => $name,
			'aliases'       => array_values( array_filter( array_map( 'sanitize_text_field', (array) ( $b['aliases'] ?? array() ) ) ) ),
			'lat'           => $lat,
			'lng'           => $lng,
			'notes'         => sanitize_text_field( $b['notes'] ?? '' ),
			'schedule_note' => sanitize_text_field( $b['schedule_note'] ?? '' ),
		);
	}
	update_user_meta( $user_id, 'jrc_school_buildings', $out );

	$view = json_decode( wp_unslash( $_POST['jrc_school_map_json'] ?? '' ), true ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	if ( is_array( $view ) && is_numeric( $view['lat'] ?? null ) && is_numeric( $view['lng'] ?? null ) ) {
		update_user_meta( $user_id, 'jrc_school_map', array(
			'lat'  => (float) $view['lat'],
			'lng'  => (float) $view['lng'],
			'zoom' => max( 12, min( 19, (int) ( $view['zoom'] ?? 17 ) ) ),
		) );
	}
}
add_action( 'personal_options_update', 'jrc_school_save_profile' );
add_action( 'edit_user_profile_update', 'jrc_school_save_profile' );
