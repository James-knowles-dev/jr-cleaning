<?php
/**
 * Manual matching: attach an unattributed Connecteam shift to a partner account by hand.
 *
 * Most of JR's shifts carry no Affiliate Code — the office names the client in the shift
 * title instead ("SBK office", "Hillfox"). Rather than make someone go back into
 * Connecteam and fill in a code on every shift, the Preview screen offers a Match button
 * that records the link here, in WordPress:
 *
 *   shift  one shift id  -> user id   ("this booking belongs to them")
 *   title  shift title   -> user id   ("every shift titled 'SBK office' is theirs")
 *
 * Both are consulted only when the Affiliate Code field gives no partner, so a code filled
 * in at Connecteam's end always wins. A shift match beats a title rule.
 *
 * Every match is re-checked against an approved partner account at the moment it is
 * used, so deleting or un-approving a partner never leaves jobs pointing at them.
 *
 * Stored in one non-autoloaded option: this is read by the sync and one admin screen.
 *
 * @package jrc-connecteam
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

const JRC_CT_MATCH_OPTION = 'jrc_connecteam_matches';

/** All stored matches: array( 'shift' => array( id => uid ), 'title' => array( key => array( uid, label ) ) ). */
function jrc_ct_matches() {
	$m = get_option( JRC_CT_MATCH_OPTION, array() );
	$m = is_array( $m ) ? $m : array();
	return array(
		'shift' => (array) ( $m['shift'] ?? array() ),
		'title' => (array) ( $m['title'] ?? array() ),
	);
}

function jrc_ct_save_matches( $m ) {
	update_option( JRC_CT_MATCH_OPTION, $m, false );
}

/** Title key: case, spacing and punctuation differences should not split one client into two. */
function jrc_ct_title_key( $title ) {
	$t = strtolower( trim( (string) $title ) );
	$t = preg_replace( '/[^a-z0-9]+/', ' ', $t );
	return trim( preg_replace( '/\s+/', ' ', $t ) );
}

/**
 * The manual match for a shift, if any.
 *
 * @return array|null array( user_id, matched_on ) or null.
 */
function jrc_ct_manual_match( $shift ) {
	$m  = jrc_ct_matches();
	$id = (string) ( $shift['id'] ?? '' );

	if ( $id && ! empty( $m['shift'][ $id ] ) && jrc_is_affiliate( (int) $m['shift'][ $id ] ) ) {
		return array( 'user_id' => (int) $m['shift'][ $id ], 'matched_on' => 'manual match' );
	}

	$key = jrc_ct_title_key( $shift['title'] ?? '' );
	if ( '' !== $key && ! empty( $m['title'][ $key ][0] ) && jrc_is_affiliate( (int) $m['title'][ $key ][0] ) ) {
		return array( 'user_id' => (int) $m['title'][ $key ][0], 'matched_on' => 'title rule' );
	}

	return null;
}

/** Partner accounts for the picker, sorted by name. */
function jrc_ct_partner_choices() {
	$users = get_users( array(
		'meta_key'   => 'jrc_is_affiliate',
		'meta_value' => '1',
		'orderby'    => 'display_name',
		'order'      => 'ASC',
	) );

	$out = array();
	foreach ( $users as $u ) {
		$p       = function_exists( 'jrc_affiliate_profile' ) ? jrc_affiliate_profile( $u->ID ) : array();
		$company = (string) get_user_meta( $u->ID, 'billing_company', true );
		$out[]   = array(
			'id'      => (int) $u->ID,
			'name'    => $u->display_name,
			'company' => $company,
			'email'   => $u->user_email,
			'code'    => (string) get_user_meta( $u->ID, 'jrc_affiliate_code', true ),
			'type'    => trim( ( $p['channel_label'] ?? '' ) . ( ! empty( $p['label'] ) ? ' · ' . $p['label'] : '' ) ),
		);
	}
	return $out;
}

/**
 * Point already-imported rows at a partner.
 *
 * Only ever moves a row from nobody (user 0) to someone — the same rule the sync follows —
 * so a manual match cannot take a job away from a partner it was already credited to.
 *
 * @param string $shift_id Match exactly this shift, or '' to match by title.
 * @param string $title_key jrc_ct_title_key() of the title, when matching by title.
 * @return int Rows changed.
 */
function jrc_ct_apply_match_to_rows( $user_id, $shift_id = '', $title_key = '' ) {
	global $wpdb;
	$table = jrc_jobs_table();
	$ids   = array();

	if ( '' !== $shift_id ) {
		$ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT id FROM $table WHERE source = %s AND external_ref = %s AND user_id = 0",
			JRC_CT_SOURCE, $shift_id
		) );
	} elseif ( '' !== $title_key ) {
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, external_meta FROM $table WHERE source = %s AND user_id = 0",
			JRC_CT_SOURCE
		) );
		foreach ( $rows as $r ) {
			$meta = function_exists( 'jrc_job_meta' ) ? jrc_job_meta( $r ) : array();
			if ( jrc_ct_title_key( $meta['shift_title'] ?? '' ) === $title_key ) {
				$ids[] = (int) $r->id;
			}
		}
	}

	foreach ( $ids as $id ) {
		$wpdb->update( $table, array( 'user_id' => (int) $user_id ), array( 'id' => (int) $id ), array( '%d' ), array( '%d' ) );
	}
	return count( $ids );
}

/**
 * Undo: put rows matched to $user_id back to nobody.
 *
 * Rows whose Affiliate Code names a partner are left alone — they were never this
 * match's doing.
 */
function jrc_ct_unapply_match( $user_id, $shift_id = '', $title_key = '' ) {
	global $wpdb;
	$table   = jrc_jobs_table();
	$s       = jrc_ct_settings();
	$changed = 0;

	$rows = $wpdb->get_results( $wpdb->prepare(
		"SELECT id, external_ref, external_meta FROM $table WHERE source = %s AND user_id = %d",
		JRC_CT_SOURCE, (int) $user_id
	) );
	foreach ( $rows as $r ) {
		$meta = function_exists( 'jrc_job_meta' ) ? jrc_job_meta( $r ) : array();
		$hit  = '' !== $shift_id
			? (string) $r->external_ref === $shift_id
			: jrc_ct_title_key( $meta['shift_title'] ?? '' ) === $title_key;
		if ( ! $hit ) {
			continue;
		}
		// Credited by the code field in Connecteam: not ours to undo.
		$fields = (array) ( $meta['fields'] ?? array() );
		$code   = $s['partner_field_id'] ? jrc_ct_job_field_value( array( 'customFields' => array_map( function ( $n, $v ) {
			return array( 'name' => $n, 'value' => $v );
		}, array_keys( $fields ), $fields ) ), $s['partner_field_id'] ) : '';
		if ( '' !== $code && function_exists( 'jrc_affiliate_by_code' ) && (int) jrc_affiliate_by_code( $code ) === (int) $user_id ) {
			continue;
		}
		$wpdb->update( $table, array( 'user_id' => 0 ), array( 'id' => (int) $r->id ), array( '%d' ), array( '%d' ) );
		$changed++;
	}
	return $changed;
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------

/** Back to the preview the administrator was looking at. */
function jrc_ct_match_redirect( $args ) {
	$back = isset( $_POST['return'] ) ? wp_unslash( $_POST['return'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	$back = $back ? wp_validate_redirect( $back, jrc_ct_tab_url( 'preview' ) ) : jrc_ct_tab_url( 'preview' );
	wp_safe_redirect( add_query_arg( $args, $back ) );
	exit;
}

add_action( 'admin_post_jrc_ct_match', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'jrc-connecteam' ), 403 );
	}
	check_admin_referer( 'jrc_ct_match' );

	$user_id  = (int) ( $_POST['user_id'] ?? 0 );
	$shift_id = sanitize_text_field( wp_unslash( $_POST['shift_id'] ?? '' ) );
	$title    = sanitize_text_field( wp_unslash( $_POST['shift_title'] ?? '' ) );
	$by_title = ! empty( $_POST['by_title'] ) && '' !== jrc_ct_title_key( $title );

	if ( ! $user_id || ! jrc_is_affiliate( $user_id ) || '' === $shift_id ) {
		jrc_ct_match_redirect( array( 'matched' => 'fail' ) );
	}

	$m = jrc_ct_matches();
	$m['shift'][ $shift_id ] = $user_id;
	if ( $by_title ) {
		$m['title'][ jrc_ct_title_key( $title ) ] = array( $user_id, $title );
	}
	jrc_ct_save_matches( $m );

	$n = jrc_ct_apply_match_to_rows( $user_id, $shift_id );
	if ( $by_title ) {
		$n += jrc_ct_apply_match_to_rows( $user_id, '', jrc_ct_title_key( $title ) );
	}

	$u = get_userdata( $user_id );
	jrc_ct_log( sprintf(
		'Shift %s matched to %s%s; %d imported job(s) updated.',
		$shift_id,
		$u ? $u->display_name : 'user ' . $user_id,
		$by_title ? ' (and every shift titled "' . $title . '")' : '',
		$n
	), 'info' );

	jrc_ct_match_redirect( array( 'matched' => 'ok', 'rows' => $n ) );
} );

add_action( 'admin_post_jrc_ct_unmatch', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'jrc-connecteam' ), 403 );
	}
	check_admin_referer( 'jrc_ct_unmatch' );

	$shift_id = sanitize_text_field( wp_unslash( $_POST['shift_id'] ?? '' ) );
	$title    = sanitize_text_field( wp_unslash( $_POST['shift_title'] ?? '' ) );
	$key      = jrc_ct_title_key( $title );
	$m        = jrc_ct_matches();
	$n        = 0;

	// Undo whichever match is giving this shift its partner: the shift's own first,
	// otherwise the title rule (which also releases every other shift under that title).
	if ( $shift_id && isset( $m['shift'][ $shift_id ] ) ) {
		$uid = (int) $m['shift'][ $shift_id ];
		unset( $m['shift'][ $shift_id ] );
		// A title rule for the same partner would immediately re-match it; drop that too.
		if ( $key && isset( $m['title'][ $key ] ) && (int) $m['title'][ $key ][0] === $uid ) {
			unset( $m['title'][ $key ] );
			$n += jrc_ct_unapply_match( $uid, '', $key );
		}
		$n += jrc_ct_unapply_match( $uid, $shift_id );
	} elseif ( $key && isset( $m['title'][ $key ] ) ) {
		$uid = (int) $m['title'][ $key ][0];
		unset( $m['title'][ $key ] );
		// Shift-level matches to the same partner under this title were made alongside it.
		$n += jrc_ct_unapply_match( $uid, '', $key );
	}
	jrc_ct_save_matches( $m );

	jrc_ct_log( sprintf( 'Match removed for shift %s ("%s"); %d imported job(s) released.', $shift_id, $title, $n ), 'info' );
	jrc_ct_match_redirect( array( 'unmatched' => 'ok', 'rows' => $n ) );
} );

// ---------------------------------------------------------------------------
// Picker (rendered once on the Preview tab)
// ---------------------------------------------------------------------------

function jrc_ct_render_match_picker() {
	$partners = jrc_ct_partner_choices();
	$return   = remove_query_arg( array( 'matched', 'unmatched', 'rows' ), wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
	?>
	<dialog id="jrc-ct-match" style="width:min(640px,94vw);padding:0;border:1px solid #c3c4c7;border-radius:6px;box-shadow:0 12px 40px rgba(0,0,0,.25);">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:0;">
			<?php wp_nonce_field( 'jrc_ct_match' ); ?>
			<input type="hidden" name="action" value="jrc_ct_match" />
			<input type="hidden" name="shift_id" value="" />
			<input type="hidden" name="shift_title" value="" />
			<input type="hidden" name="return" value="<?php echo esc_attr( $return ); ?>" />

			<div style="padding:16px 20px;border-bottom:1px solid #dcdcde;">
				<h2 style="margin:0 0 4px;">Match to a partner account</h2>
				<p class="description jrc-ct-match__shift" style="margin:0;"></p>
			</div>

			<div style="padding:14px 20px;">
				<input type="search" class="jrc-ct-match__search regular-text" placeholder="Search name, company, email or code" style="width:100%;margin-bottom:10px;" />
				<?php if ( ! $partners ) : ?>
					<p>No approved partner accounts yet. Approve one on its user profile (Affiliate programme → Approved affiliate).</p>
				<?php else : ?>
					<div class="jrc-ct-match__list" style="max-height:340px;overflow:auto;border:1px solid #dcdcde;border-radius:4px;">
						<?php foreach ( $partners as $p ) :
							$hay = strtolower( implode( ' ', array( $p['name'], $p['company'], $p['email'], $p['code'] ) ) );
							?>
							<label class="jrc-ct-match__item" data-hay="<?php echo esc_attr( $hay ); ?>" style="display:flex;gap:10px;align-items:flex-start;padding:9px 12px;border-bottom:1px solid #f0f0f1;cursor:pointer;">
								<input type="radio" name="user_id" value="<?php echo (int) $p['id']; ?>" required style="margin-top:3px;" />
								<span>
									<strong><?php echo esc_html( $p['name'] ); ?></strong><?php echo $p['company'] && $p['company'] !== $p['name'] ? ' — ' . esc_html( $p['company'] ) : ''; ?>
									<br /><span class="description" style="font-size:12px;">
										<?php echo esc_html( implode( ' · ', array_filter( array( $p['code'], $p['type'], $p['email'] ) ) ) ); ?>
									</span>
								</span>
							</label>
						<?php endforeach; ?>
						<p class="jrc-ct-match__none description" style="display:none;padding:10px 12px;margin:0;">No partner matches that search.</p>
					</div>
				<?php endif; ?>

				<label class="jrc-ct-match__bytitle" style="display:block;margin-top:12px;">
					<input type="checkbox" name="by_title" value="1" checked />
					Also match every shift titled <strong class="jrc-ct-match__title"></strong>, now and in future
				</label>
				<p class="description" style="margin:6px 0 0;">
					Matched jobs appear in the partner's account straight away. An Affiliate Code filled in on a shift in Connecteam always takes priority.
				</p>
			</div>

			<div style="padding:12px 20px;border-top:1px solid #dcdcde;display:flex;justify-content:flex-end;gap:8px;background:#f6f7f7;">
				<button type="button" class="button jrc-ct-match__cancel">Cancel</button>
				<button type="submit" class="button button-primary" <?php disabled( ! $partners ); ?>>Match</button>
			</div>
		</form>
	</dialog>

	<form id="jrc-ct-unmatch" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:none;">
		<?php wp_nonce_field( 'jrc_ct_unmatch' ); ?>
		<input type="hidden" name="action" value="jrc_ct_unmatch" />
		<input type="hidden" name="shift_id" value="" />
		<input type="hidden" name="shift_title" value="" />
		<input type="hidden" name="return" value="<?php echo esc_attr( $return ); ?>" />
	</form>

	<script>
	( function () {
		var dlg = document.getElementById( 'jrc-ct-match' );
		if ( ! dlg ) { return; }
		var form = dlg.querySelector( 'form' );
		var search = dlg.querySelector( '.jrc-ct-match__search' );
		var items = dlg.querySelectorAll( '.jrc-ct-match__item' );
		var none = dlg.querySelector( '.jrc-ct-match__none' );

		document.addEventListener( 'click', function ( e ) {
			var b = e.target.closest( '.jrc-ct-match-btn' );
			if ( b ) {
				form.shift_id.value = b.dataset.shift;
				form.shift_title.value = b.dataset.title || '';
				dlg.querySelector( '.jrc-ct-match__shift' ).textContent = b.dataset.label;
				dlg.querySelector( '.jrc-ct-match__title' ).textContent = '“' + ( b.dataset.title || '' ) + '”';
				dlg.querySelector( '.jrc-ct-match__bytitle' ).style.display = b.dataset.title ? '' : 'none';
				form.by_title.checked = !! b.dataset.title;
				form.querySelectorAll( 'input[name="user_id"]' ).forEach( function ( r ) { r.checked = false; } );
				search.value = ''; filter();
				dlg.showModal(); search.focus();
				return;
			}
			var u = e.target.closest( '.jrc-ct-unmatch-btn' );
			if ( u ) {
				e.preventDefault();
				if ( ! confirm( 'Remove this match? Jobs it attributed go back to unattributed and disappear from the partner’s account.' ) ) { return; }
				var f = document.getElementById( 'jrc-ct-unmatch' );
				f.shift_id.value = u.dataset.shift; f.shift_title.value = u.dataset.title || ''; f.submit();
			}
		} );
		dlg.querySelector( '.jrc-ct-match__cancel' ).addEventListener( 'click', function () { dlg.close(); } );

		function filter() {
			var q = search.value.trim().toLowerCase(), shown = 0;
			items.forEach( function ( it ) {
				var ok = ! q || it.dataset.hay.indexOf( q ) !== -1;
				it.style.display = ok ? 'flex' : 'none'; if ( ok ) { shown++; }
			} );
			if ( none ) { none.style.display = shown ? 'none' : 'block'; }
		}
		if ( search ) { search.addEventListener( 'input', filter ); }
	} )();
	</script>
	<?php
}

// ---------------------------------------------------------------------------
// The same picker on Partner Programme → Activity
// ---------------------------------------------------------------------------

/** A Match button on each unassigned Connecteam job in the Activity table. */
add_filter( 'jrc_activity_match_control', function ( $html, $row ) {
	if ( ! current_user_can( 'manage_options' ) || (int) $row->user_id || JRC_CT_SOURCE !== $row->source || '' === (string) $row->external_ref ) {
		return $html;
	}
	$meta  = function_exists( 'jrc_job_meta' ) ? jrc_job_meta( $row ) : array();
	$title = (string) ( $meta['shift_title'] ?? '' );
	$label = ( $row->scheduled_date ? date_i18n( 'D j M, H:i', strtotime( $row->scheduled_date ) ) : '' )
		. ' — ' . ( $row->job_type ?: 'Clean' ) . ( $title ? ' — ' . $title : '' );

	return sprintf(
		'<button type="button" class="button button-small jrc-ct-match-btn" style="margin-top:4px;" data-shift="%s" data-title="%s" data-label="%s">Match to a partner</button>',
		esc_attr( $row->external_ref ),
		esc_attr( $title ),
		esc_attr( $label )
	);
}, 10, 2 );

/** Render the picker once below the Activity table, only when a row needs it. */
add_action( 'jrc_activity_after_table', function ( $rows ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	// do_action() hands over a lone object instead of a one-element array (a WordPress
	// back-compat quirk), so a filtered table with a single job arrives unwrapped.
	$rows = is_object( $rows ) ? array( $rows ) : (array) $rows;
	foreach ( $rows as $r ) {
		if ( is_object( $r ) && ! (int) $r->user_id && JRC_CT_SOURCE === $r->source ) {
			jrc_ct_render_match_picker();
			return;
		}
	}
} );

/** Confirmation on the Activity screen after matching from it. */
add_action( 'admin_notices', function () {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notices.
	if ( ( $_GET['page'] ?? '' ) !== 'jrc-partner' ) {
		return;
	}
	if ( isset( $_GET['matched'] ) ) {
		echo 'ok' === $_GET['matched']
			? '<div class="notice notice-success is-dismissible"><p>Matched. ' . (int) ( $_GET['rows'] ?? 0 ) . ' job(s) now show in the partner\'s account.</p></div>'
			: '<div class="notice notice-error is-dismissible"><p>Could not match — choose an approved partner account.</p></div>';
	}
	if ( isset( $_GET['unmatched'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Match removed. ' . (int) ( $_GET['rows'] ?? 0 ) . ' job(s) are unassigned again.</p></div>';
	}
	// phpcs:enable
} );

