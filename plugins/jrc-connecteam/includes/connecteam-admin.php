<?php
/**
 * Connecteam admin: settings, the dry-run preview, and the sync log.
 *
 * The preview tab is the important one and the reason this screen exists in the shape
 * it does. Three questions cannot be answered from the API documentation — whether the
 * partner custom field is actually populated, whether shift titles name staff, and what
 * the real data looks like at all — and every one of them is answerable in about a
 * minute by looking at a table of real shifts next to what they would become.
 *
 * So the preview writes nothing, ever. It is safe to point at a live account before any
 * decision has been made, which is the only honest way to make those decisions.
 *
 * @package jrc-connecteam
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function () {
	add_submenu_page(
		'jrc-partner',
		__( 'Connecteam', 'jrc-connecteam' ),
		__( 'Connecteam', 'jrc-connecteam' ),
		'manage_options',
		'jrc-connecteam',
		'jrc_ct_admin_screen'
	);
}, 40 );

/** Tab URL helper. */
function jrc_ct_tab_url( $tab ) {
	return admin_url( 'admin.php?page=jrc-connecteam&tab=' . rawurlencode( $tab ) );
}

// ---------------------------------------------------------------------------
// Actions
// ---------------------------------------------------------------------------

add_action( 'admin_post_jrc_ct_save', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'jrc-connecteam' ), 403 );
	}
	check_admin_referer( 'jrc_ct_save' );

	$in      = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- each field sanitised below.
	$changes = array(
		'region'             => in_array( ( $in['region'] ?? '' ), array( 'global', 'au' ), true ) ? $in['region'] : 'global',
		'scheduler_id'       => sanitize_text_field( $in['scheduler_id'] ?? '' ),
		'partner_field_id'   => sanitize_text_field( $in['partner_field_id'] ?? '' ),
		'partner_field_by'   => in_array( ( $in['partner_field_by'] ?? '' ), array( 'code', 'user_id' ), true ) ? $in['partner_field_by'] : 'code',
		'notes_field_id'     => sanitize_text_field( $in['notes_field_id'] ?? '' ),
		'value_field_id'     => sanitize_text_field( $in['value_field_id'] ?? '' ),
		'building_field_id'  => sanitize_text_field( $in['building_field_id'] ?? '' ),
		'use_shift_titles'   => empty( $in['use_shift_titles'] ) ? 0 : 1,
		'enabled'            => empty( $in['enabled'] ) ? 0 : 1,
		'import_unlinked'    => empty( $in['import_unlinked'] ) ? 0 : 1,
		'complete_sets_date' => empty( $in['complete_sets_date'] ) ? 0 : 1,
	);

	// An empty key field means "leave it alone", so a saved key is not wiped by an
	// administrator who opened the form to change something else. The field is rendered
	// blank with the stored value masked beside it, never pre-filled.
	$posted_key = trim( (string) ( $in['api_key'] ?? '' ) );
	if ( '' !== $posted_key ) {
		$changes['api_key'] = sanitize_text_field( $posted_key );
		// A new key may belong to a different account entirely.
		jrc_ct_flush_cache();
	}

	jrc_ct_update_settings( $changes );
	jrc_ct_log( 'Settings saved.', 'info' );

	wp_safe_redirect( add_query_arg( 'saved', 1, jrc_ct_tab_url( 'settings' ) ) );
	exit;
} );

add_action( 'admin_post_jrc_ct_test', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'jrc-connecteam' ), 403 );
	}
	check_admin_referer( 'jrc_ct_test' );

	jrc_ct_flush_cache();
	$r = jrc_ct_test_connection();

	if ( is_wp_error( $r ) ) {
		jrc_ct_log( 'Connection test failed: ' . $r->get_error_message(), 'error' );
		wp_safe_redirect( add_query_arg( 'tested', 'fail', jrc_ct_tab_url( 'settings' ) ) );
		exit;
	}

	jrc_ct_log( 'Connection test succeeded.', 'info' );
	wp_safe_redirect( add_query_arg( 'tested', 'ok', jrc_ct_tab_url( 'settings' ) ) );
	exit;
} );

add_action( 'admin_post_jrc_ct_webhook', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'jrc-connecteam' ), 403 );
	}
	check_admin_referer( 'jrc_ct_webhook' );

	$r = jrc_ct_register_webhook();
	wp_safe_redirect( add_query_arg( 'webhook', is_wp_error( $r ) ? 'fail' : 'ok', jrc_ct_tab_url( 'settings' ) ) );
	exit;
} );

add_action( 'admin_post_jrc_ct_sync', function () {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do that.', 'jrc-connecteam' ), 403 );
	}
	check_admin_referer( 'jrc_ct_sync' );

	$days_back    = max( 0, (int) ( $_POST['days_back'] ?? 21 ) );
	$days_forward = max( 1, (int) ( $_POST['days_forward'] ?? 90 ) );
	$now          = current_time( 'timestamp' );

	$r = jrc_ct_sync_window(
		$now - ( $days_back * DAY_IN_SECONDS ),
		$now + ( $days_forward * DAY_IN_SECONDS ),
		array( 'dry_run' => false, 'priority' => 'interactive' )
	);

	if ( is_wp_error( $r ) ) {
		jrc_ct_log( 'Manual sync failed: ' . $r->get_error_message(), 'error' );
		wp_safe_redirect( add_query_arg( 'synced', 'fail', jrc_ct_tab_url( 'preview' ) ) );
		exit;
	}

	wp_safe_redirect( add_query_arg( array(
		'synced'  => 'ok',
		'created' => (int) $r['created'],
		'updated' => (int) $r['updated'],
	), jrc_ct_tab_url( 'preview' ) ) );
	exit;
} );

// ---------------------------------------------------------------------------
// Screen
// ---------------------------------------------------------------------------

function jrc_ct_admin_screen() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only navigation.
	$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'settings';
	$tab = in_array( $tab, array( 'settings', 'preview', 'log' ), true ) ? $tab : 'settings';
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Connecteam', 'jrc-connecteam' ); ?></h1>

		<h2 class="nav-tab-wrapper">
			<?php foreach ( array( 'settings' => 'Settings', 'preview' => 'Preview &amp; sync', 'log' => 'Log' ) as $slug => $label ) : ?>
				<a class="nav-tab <?php echo $tab === $slug ? 'nav-tab-active' : ''; ?>"
					href="<?php echo esc_url( jrc_ct_tab_url( $slug ) ); ?>"><?php echo wp_kses_post( $label ); ?></a>
			<?php endforeach; ?>
		</h2>

		<?php
		jrc_ct_admin_notices();

		if ( 'settings' === $tab ) {
			jrc_ct_settings_tab();
		} elseif ( 'preview' === $tab ) {
			jrc_ct_preview_tab();
		} else {
			jrc_ct_log_tab();
		}
		?>
	</div>
	<?php
}

/** Result notices from the admin_post handlers. */
function jrc_ct_admin_notices() {
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only notices.
	if ( ! empty( $_GET['saved'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
	}
	if ( isset( $_GET['tested'] ) ) {
		$ok = 'ok' === $_GET['tested'];
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			$ok ? 'success' : 'error',
			$ok
				? 'Connected to Connecteam successfully.'
				: 'Could not connect. See the Log tab for the exact error.'
		);
	}
	if ( isset( $_GET['webhook'] ) ) {
		$ok = 'ok' === $_GET['webhook'];
		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			$ok ? 'success' : 'error',
			$ok ? 'Webhook registered with Connecteam.' : 'Webhook registration failed. See the Log tab.'
		);
	}
	if ( isset( $_GET['matched'] ) ) {
		echo 'ok' === $_GET['matched']
			? '<div class="notice notice-success is-dismissible"><p>Matched. ' . (int) ( $_GET['rows'] ?? 0 ) . ' imported job(s) now show in the partner\'s account; the rest follow on the next sync.</p></div>'
			: '<div class="notice notice-error is-dismissible"><p>Could not match — choose an approved partner account.</p></div>';
	}
	if ( isset( $_GET['unmatched'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Match removed. ' . (int) ( $_GET['rows'] ?? 0 ) . ' imported job(s) are unattributed again.</p></div>';
	}
	if ( isset( $_GET['synced'] ) ) {
		if ( 'ok' === $_GET['synced'] ) {
			printf(
				'<div class="notice notice-success is-dismissible"><p>Sync complete: %d created, %d updated.</p></div>',
				(int) ( $_GET['created'] ?? 0 ),
				(int) ( $_GET['updated'] ?? 0 )
			);
		} else {
			echo '<div class="notice notice-error is-dismissible"><p>Sync failed. See the Log tab.</p></div>';
		}
	}
	// phpcs:enable WordPress.Security.NonceVerification.Recommended
}

/** Mask a credential for display. */
function jrc_ct_mask( $value ) {
	$value = (string) $value;
	if ( '' === $value ) {
		return '';
	}
	if ( strlen( $value ) <= 8 ) {
		return str_repeat( '•', strlen( $value ) );
	}
	return substr( $value, 0, 4 ) . str_repeat( '•', 12 ) . substr( $value, -4 );
}

// ---------------------------------------------------------------------------
// Settings tab
// ---------------------------------------------------------------------------

function jrc_ct_settings_tab() {
	$s          = jrc_ct_settings();
	$configured = jrc_ct_configured();
	$budget     = jrc_ct_budget();

	// Only reach for the API when there is a key. Rendering the form must never be the
	// thing that spends the last of a 100/day budget.
	$schedulers = $configured ? jrc_ct_schedulers() : array();
	$job_fields = $configured ? jrc_ct_job_custom_fields() : array();
	?>

	<?php if ( $configured && $budget['known'] ) : ?>
		<div class="notice notice-info inline" style="margin:14px 0;">
			<p>
				<strong>Rate limit:</strong>
				<?php echo (int) $budget['day_remaining']; ?> of <?php echo (int) $budget['day_limit']; ?> requests left today,
				<?php echo (int) $budget['minute_remaining']; ?> left this minute.
				<?php if ( (int) $budget['day_limit'] <= 100 ) : ?>
					<br /><em>That daily figure matches Connecteam's smallest plan. Background sync holds back the
					last <?php echo (int) ceil( $budget['day_limit'] * jrc_ct_reserve_fraction() ); ?> so these screens keep working.</em>
				<?php endif; ?>
			</p>
		</div>
	<?php endif; ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<?php wp_nonce_field( 'jrc_ct_save' ); ?>
		<input type="hidden" name="action" value="jrc_ct_save" />

		<h2>Connection</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="ct-key">API key</label></th>
				<td>
					<?php if ( jrc_ct_key_is_constant() ) : ?>
						<p><code>JRC_CONNECTEAM_API_KEY</code> is defined in PHP, so the key is not stored in the database. Nothing to do here.</p>
					<?php else : ?>
						<input type="password" id="ct-key" name="api_key" value="" class="regular-text" autocomplete="off"
							placeholder="<?php echo $s['api_key'] ? esc_attr( jrc_ct_mask( $s['api_key'] ) ) : 'Paste the key'; ?>" />
						<p class="description">
							<?php if ( $s['api_key'] ) : ?>
								A key is saved. Leave this blank to keep it; type a new one to replace it.
							<?php else : ?>
								From Connecteam &rarr; Settings &rarr; API. A read-only key is enough for everything here except registering the webhook.
							<?php endif; ?>
							<br />Better still, define <code>JRC_CONNECTEAM_API_KEY</code> in PHP and it never touches the database.
						</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">Region</th>
				<td>
					<select name="region">
						<option value="global" <?php selected( $s['region'], 'global' ); ?>>Global (api.connecteam.com)</option>
						<option value="au" <?php selected( $s['region'], 'au' ); ?>>Australia (api-au.connecteam.com)</option>
					</select>
					<p class="description">UK accounts are Global.</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Scheduler</th>
				<td>
					<?php if ( is_wp_error( $schedulers ) ) : ?>
						<p class="description" style="color:#b32d2e;"><?php echo esc_html( $schedulers->get_error_message() ); ?></p>
						<input type="text" name="scheduler_id" value="<?php echo esc_attr( $s['scheduler_id'] ); ?>" class="regular-text" />
					<?php elseif ( $schedulers ) : ?>
						<select name="scheduler_id">
							<option value="">— Choose —</option>
							<?php foreach ( $schedulers as $sched ) :
								$sid = (string) ( $sched['schedulerId'] ?? ( $sched['id'] ?? '' ) ); ?>
								<option value="<?php echo esc_attr( $sid ); ?>" <?php selected( $s['scheduler_id'], $sid ); ?>>
									<?php echo esc_html( ( $sched['name'] ?? 'Scheduler' ) . ' (' . $sid . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<input type="text" name="scheduler_id" value="<?php echo esc_attr( $s['scheduler_id'] ); ?>" class="regular-text" />
						<p class="description">Save an API key and use <em>Test connection</em> to list the schedulers on the account.</p>
					<?php endif; ?>
				</td>
			</tr>
		</table>

		<h2>Attribution</h2>
		<p class="description" style="max-width:60em;">
			How a Connecteam shift is traced back to a partner account. Never through the people on the
			shift — they are JR's cleaners, and a partner is an estate agent branch who is not in Connecteam
			at all. The field below is looked for on the <strong>shift</strong> first, then on its
			<strong>job</strong> and that job's parents, so it can be filled in per booking or once on a branch job.
		</p>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Partner field</th>
				<td>
					<?php if ( is_array( $job_fields ) && $job_fields ) : ?>
						<select name="partner_field_id">
							<option value="">— None; nothing will be attributed —</option>
							<?php foreach ( $job_fields as $f )	:
								$fid = (string) ( $f['customFieldId'] ?? ( $f['id'] ?? '' ) ); ?>
								<option value="<?php echo esc_attr( $fid ); ?>" <?php selected( $s['partner_field_id'], $fid ); ?>>
									<?php echo esc_html( ( $f['name'] ?? $f['title'] ?? 'Field' ) . ' (' . $fid . ')' ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					<?php else : ?>
						<input type="text" name="partner_field_id" value="<?php echo esc_attr( $s['partner_field_id'] ); ?>" class="regular-text" placeholder="Affiliate Code" />
						<p class="description">The custom field's name exactly as it appears in Connecteam (e.g. <code>Affiliate Code</code>), or its numeric ID. Works for shift fields and job fields.</p>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">That field contains</th>
				<td>
					<label><input type="radio" name="partner_field_by" value="code" <?php checked( $s['partner_field_by'], 'code' ); ?> />
						The partner's referral code (e.g. <code>JRC-SMITH</code>)</label><br />
					<label><input type="radio" name="partner_field_by" value="user_id" <?php checked( $s['partner_field_by'], 'user_id' ); ?> />
						The WordPress user ID</label>
					<p class="description">Either way the value is checked against an approved partner account before anything is attributed.</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ct-building-field">Building field</label></th>
				<td>
					<input type="text" id="ct-building-field" name="building_field_id" value="<?php echo esc_attr( $s['building_field_id'] ); ?>" class="regular-text" placeholder="Building" />
					<p class="description" style="max-width:52em;">
						For School accounts: the custom field on the shift naming the building (e.g. <code>Building</code>). Its value is matched to the buildings on the school's
						account (by name or alias) and places the clean on that building on the school's <strong>Site map</strong>.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="ct-value-field">Value field</label></th>
				<td>
					<input type="text" id="ct-value-field" name="value_field_id" value="<?php echo esc_attr( $s['value_field_id'] ); ?>" class="regular-text" placeholder="Value" />
					<p class="description" style="max-width:52em;">
						The custom field holding the price of the clean — its name as it appears in Connecteam (e.g. <code>Value</code>) or its ID, on the shift or the job.
						Amounts like <code>£145</code>, <code>145.00</code> or <code>1,200</code> all work. It feeds the job value on <strong>Partner Programme → Activity</strong> and its CSV,
						and the commission / savings figures partners see. On a job with several cleaners the value is taken once, not added up per person, so it only needs filling in on one shift.
						A blank in Connecteam never clears a value already set in WordPress. Leave this empty to not import values.
					</p>
				</td>
			</tr>
		</table>

		<h2>What partners see</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Customer notes field (on the shift)</th>
				<td>
					<input type="text" name="notes_field_id" value="<?php echo esc_attr( $s['notes_field_id'] ); ?>" class="regular-text" placeholder="Name or ID of a dedicated custom field" />
					<p class="description" style="max-width:52em;">
						<strong>Leave blank until a dedicated field exists in Connecteam.</strong>
						Connecteam's built-in shift <em>notes</em> are written by staff for staff and routinely
						contain key-safe codes, access instructions and remarks about customers. They are never
						imported. Only the field named here reaches a partner — so it needs to be one the office
						fills in knowing the estate agent will read it.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Shift titles</th>
				<td>
					<label><input type="checkbox" name="use_shift_titles" value="1" <?php checked( $s['use_shift_titles'], 1 ); ?> />
						Fall back to the shift title when a job has no title</label>
					<p class="description" style="max-width:52em;">
						Off by default. Shift titles are free text and sometimes name the staff on the job
						("EOT clean — Dave &amp; Sam"), which would then show to the partner. Check the
						Preview tab first: it lists the real titles.
					</p>
				</td>
			</tr>
		</table>

		<h2>Import behaviour</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row">Unattributed work</th>
				<td>
					<label><input type="checkbox" name="import_unlinked" value="1" <?php checked( $s['import_unlinked'], 1 ); ?> />
						Import shifts that don't match a partner</label>
					<p class="description" style="max-width:52em;">
						Recommended. They are stored against no partner and no partner can see them, but the
						admin Booking Calendar and the daily capacity figures become accurate instead of
						showing only referred work.
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row">Completion</th>
				<td>
					<label><input type="checkbox" name="complete_sets_date" value="1" <?php checked( $s['complete_sets_date'], 1 ); ?> />
						A completed shift sets the job date</label>
					<p class="description" style="max-width:52em;">
						<strong>This is the field that drives tier, discount rates and reward figures.</strong>
						It is set once, on completion, and never cleared afterwards.
					</p>
					<?php if ( function_exists( 'jrc_auto_tier_enabled' ) && jrc_auto_tier_enabled() ) : ?>
						<div class="notice notice-warning inline" style="margin:10px 0;max-width:52em;">
							<p>
								Automatic tier recalculation is <strong>on</strong>. Once a backfill lands, partners
								may be promoted and emailed the same night.
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=jrc-tiers' ) ); ?>">Check the Tiers preview</a>
								before importing history.
							</p>
						</div>
					<?php endif; ?>
				</td>
			</tr>
			<tr>
				<th scope="row">Master switch</th>
				<td>
					<label><input type="checkbox" name="enabled" value="1" <?php checked( $s['enabled'], 1 ); ?> />
						Allow Connecteam to write jobs</label>
					<p class="description">
						Off means the Preview tab still works but nothing is ever written and the scheduled
						sync does not run. Leave it off until the preview looks right.
					</p>
				</td>
			</tr>
		</table>

		<?php submit_button( 'Save settings' ); ?>
	</form>

	<hr />

	<h2>Connection &amp; webhook</h2>
	<div style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'jrc_ct_test' ); ?>
			<input type="hidden" name="action" value="jrc_ct_test" />
			<button class="button">Test connection &amp; refresh lists</button>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'jrc_ct_webhook' ); ?>
			<input type="hidden" name="action" value="jrc_ct_webhook" />
			<button class="button" <?php disabled( empty( $s['scheduler_id'] ) ); ?>
				onclick="return confirm('This registers a webhook on the client\'s Connecteam account. Continue?');">
				Register webhook
			</button>
		</form>
	</div>

	<table class="widefat" style="max-width:60em;margin-top:14px;">
		<tbody>
			<tr>
				<th style="width:16em;">Webhook URL</th>
				<td><code><?php echo esc_html( jrc_ct_webhook_url() ); ?></code></td>
			</tr>
			<tr>
				<th>Webhook secret</th>
				<td>
					<?php echo $s['webhook_secret'] ? '<code>' . esc_html( jrc_ct_mask( $s['webhook_secret'] ) ) . '</code>' : '<em>Generated when you register the webhook.</em>'; ?>
				</td>
			</tr>
			<tr>
				<th>Last sync</th>
				<td><?php echo $s['last_sync'] ? esc_html( date_i18n( 'j M Y, H:i', (int) $s['last_sync'] ) ) : 'Never'; ?></td>
			</tr>
			<tr>
				<th>Scheduled sync</th>
				<td>
					<?php
					$next = wp_next_scheduled( JRC_CT_CRON_HOOK );
					echo $next
						? 'Next run ' . esc_html( wp_date( 'D j M Y, H:i', $next ) )
						: 'Not scheduled — needs the master switch on and a scheduler chosen.';
					?>
					<br /><span class="description">Hourly 12:00–19:00 Monday to Friday, and 00:00 on Saturday and Sunday.</span>
				</td>
			</tr>
		</tbody>
	</table>
	<?php
}

// ---------------------------------------------------------------------------
// Preview tab
// ---------------------------------------------------------------------------

function jrc_ct_preview_tab() {
	$s = jrc_ct_settings();

	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only navigation.
	$back    = isset( $_GET['back'] ) ? max( 0, (int) $_GET['back'] ) : 7;
	$forward = isset( $_GET['forward'] ) ? max( 1, (int) $_GET['forward'] ) : 14;
	$run     = ! empty( $_GET['run'] );
	// phpcs:enable

	?>
	<p class="description" style="max-width:70em;">
		A dry run: this reads Connecteam and shows what <em>would</em> happen. It never writes a row,
		so it is safe against a live account before anything has been decided. Use it to confirm the
		partner field is populated, that shift titles are safe to show, and that addresses look right.
	</p>

	<form method="get" style="margin:14px 0;display:flex;gap:10px;align-items:end;flex-wrap:wrap;">
		<input type="hidden" name="page" value="jrc-connecteam" />
		<input type="hidden" name="tab" value="preview" />
		<label>Days back<br /><input type="number" name="back" value="<?php echo (int) $back; ?>" min="0" max="365" style="width:90px;" /></label>
		<label>Days forward<br /><input type="number" name="forward" value="<?php echo (int) $forward; ?>" min="1" max="365" style="width:90px;" /></label>
		<button class="button button-primary" name="run" value="1">Run preview</button>
	</form>

	<?php
	if ( ! $run ) {
		return;
	}

	if ( ! jrc_ct_configured() ) {
		echo '<div class="notice notice-error inline"><p>No API key configured yet.</p></div>';
		return;
	}

	$now    = current_time( 'timestamp' );
	$result = jrc_ct_sync_window(
		$now - ( $back * DAY_IN_SECONDS ),
		$now + ( $forward * DAY_IN_SECONDS ),
		array( 'dry_run' => true, 'priority' => 'interactive' )
	);

	if ( is_wp_error( $result ) ) {
		printf( '<div class="notice notice-error inline"><p>%s</p></div>', esc_html( $result->get_error_message() ) );
		return;
	}

	$linked = $result['total'] - $result['unlinked'];
	?>

	<div style="display:flex;gap:14px;flex-wrap:wrap;margin:16px 0;">
		<?php foreach ( array(
			'Shifts found'   => $result['total'],
			'Would create'   => $result['created'],
			'Would update'   => $result['updated'],
			'Extra cleaners (merged)' => $result['merged'] ?? 0,
			'Matched to a partner' => $linked,
			'Unattributed'   => $result['unlinked'],
			'Skipped'        => $result['skipped'],
		) as $label => $n ) : ?>
			<div style="background:#fff;border:1px solid #dcdcde;border-radius:4px;padding:12px 18px;min-width:130px;">
				<div style="font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:#646970;"><?php echo esc_html( $label ); ?></div>
				<div style="font-size:20px;font-weight:600;"><?php echo (int) $n; ?></div>
			</div>
		<?php endforeach; ?>
	</div>

	<?php if ( $result['total'] && ! $linked && empty( $s['partner_field_id'] ) ) : ?>
		<div class="notice notice-warning inline" style="margin-bottom:14px;">
			<p>No partner field is selected yet, so nothing can be attributed. Choose one on the Settings tab.</p>
		</div>
	<?php elseif ( $result['total'] && ! $linked ) : ?>
		<div class="notice notice-warning inline" style="margin-bottom:14px;">
			<p>
				A partner field is selected but nothing matched. Check the <em>Partner field</em> column below —
				if it shows values that are not recognised, the field is populated but the codes do not match
				any partner account. If it is empty throughout, the field is not filled in on the jobs.
			</p>
		</div>
	<?php endif; ?>

	<table class="widefat striped">
		<thead>
			<tr>
				<th style="width:13%;">When</th>
				<th style="width:16%;">Job title</th>
				<th style="width:14%;">Shift title</th>
				<th style="width:19%;">Address</th>
				<th style="width:9%;">Status</th>
				<th style="width:17%;">Partner field &rarr; account</th>
				<th style="width:12%;">Action</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! $result['rows'] ) : ?>
				<tr><td colspan="7">No shifts in that window.</td></tr>
			<?php endif; ?>

			<?php foreach ( $result['rows'] as $r ) :
				$row  = $r['row'];
				$att  = $r['attribution'];
				$user = $r['user_id'] ? get_userdata( $r['user_id'] ) : null;
				?>
				<tr>
					<td>
						<?php echo $row['scheduled_date'] ? esc_html( date_i18n( 'D j M, H:i', strtotime( $row['scheduled_date'] ) ) ) : '<em>none</em>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						<?php if ( ! empty( $row['scheduled_end'] ) ) : ?>
							<br /><span class="description">to <?php echo esc_html( date_i18n( 'H:i', strtotime( $row['scheduled_end'] ) ) ); ?></span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $r['job_title'] ?: '—' ); ?></td>
					<td>
						<?php echo esc_html( $r['shift_title'] ?: '—' ); ?>
						<?php if ( $r['shift_title'] && ! jrc_ct_use_shift_titles() ) : ?>
							<br /><span class="description" style="font-size:11px;">not used</span>
						<?php endif; ?>
					</td>
					<td><?php echo esc_html( $row['address'] ?: '—' ); ?></td>
					<td>
						<?php echo esc_html( $row['status'] ); ?>
						<?php if ( isset( $row['value'] ) && '' !== $row['value'] && (float) $row['value'] ) : ?>
							<br /><span class="description">£<?php echo esc_html( number_format( (float) $row['value'], 2 ) ); ?></span>
						<?php endif; ?>
					</td>
					<td>
						<?php
						$is_manual = in_array( $att['matched_on'], array( 'manual match', 'title rule' ), true );
						$when_lbl  = ( $row['scheduled_date'] ? date_i18n( 'D j M, H:i', strtotime( $row['scheduled_date'] ) ) : '' ) . ' — ' . ( $r['job_title'] ?: 'Clean' ) . ( $r['shift_title'] ? ' — ' . $r['shift_title'] : '' );
						?>
						<?php if ( '' !== $att['raw'] ) : ?>
							<code><?php echo esc_html( $att['raw'] ); ?></code><br />
						<?php endif; ?>
						<?php if ( $user ) : ?>
							<span style="color:#0c5f5c;font-weight:600;"><?php echo esc_html( $user->display_name ); ?></span>
							<span class="description">(<?php echo esc_html( $att['matched_on'] ); ?>)</span>
							<?php if ( $is_manual ) : ?>
								<br /><a href="#" class="jrc-ct-unmatch-btn" style="font-size:12px;color:#b32d2e;"
									data-shift="<?php echo esc_attr( $r['shift_id'] ); ?>"
									data-title="<?php echo esc_attr( $r['shift_title'] ); ?>">Unmatch</a>
							<?php endif; ?>
						<?php else : ?>
							<?php if ( '' !== $att['raw'] ) : ?>
								<span style="color:#b32d2e;font-weight:600;">no matching account</span><br />
							<?php endif; ?>
							<button type="button" class="button button-small jrc-ct-match-btn"
								data-shift="<?php echo esc_attr( $r['shift_id'] ); ?>"
								data-title="<?php echo esc_attr( $r['shift_title'] ); ?>"
								data-label="<?php echo esc_attr( $when_lbl ); ?>">Match</button>
						<?php endif; ?>
					</td>
					<td>
						<?php echo esc_html( str_replace( array( 'would_', '_' ), array( '', ' ' ), $r['action'] ) ); ?>
						<?php if ( $r['warnings'] ) : ?>
							<br /><span class="description" style="color:#8a6d00;font-size:11px;"><?php echo esc_html( implode( ' ', $r['warnings'] ) ); ?></span>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<?php if ( function_exists( 'jrc_ct_render_match_picker' ) ) { jrc_ct_render_match_picker(); } ?>

	<?php if ( jrc_ct_active() ) : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top:18px;">
			<?php wp_nonce_field( 'jrc_ct_sync' ); ?>
			<input type="hidden" name="action" value="jrc_ct_sync" />
			<input type="hidden" name="days_back" value="<?php echo (int) $back; ?>" />
			<input type="hidden" name="days_forward" value="<?php echo (int) $forward; ?>" />
			<button class="button button-primary"
				onclick="return confirm('This writes <?php echo (int) ( $result['created'] + $result['updated'] ); ?> job row(s) and may email partners about new reports or tier changes. Continue?');">
				Import this window for real
			</button>
		</form>
	<?php else : ?>
		<p class="description" style="margin-top:18px;">
			The master switch is off, so nothing can be imported yet. Turn it on under Settings when the
			table above looks right.
		</p>
	<?php endif; ?>
	<?php
}

// ---------------------------------------------------------------------------
// Log tab
// ---------------------------------------------------------------------------

function jrc_ct_log_tab() {
	$entries = jrc_ct_log_entries();
	?>
	<p class="description">The last <?php echo (int) count( $entries ); ?> events. Newest first.</p>

	<table class="widefat striped" style="max-width:76em;">
		<thead><tr><th style="width:15em;">When</th><th style="width:7em;">Level</th><th>Message</th></tr></thead>
		<tbody>
			<?php if ( ! $entries ) : ?>
				<tr><td colspan="3">Nothing logged yet.</td></tr>
			<?php endif; ?>
			<?php foreach ( $entries as $e ) :
				$colour = 'error' === $e['level'] ? '#b32d2e' : ( 'warning' === $e['level'] ? '#8a6d00' : '#646970' ); ?>
				<tr>
					<td><?php echo esc_html( date_i18n( 'j M Y, H:i:s', strtotime( $e['at'] ) ) ); ?></td>
					<td style="color:<?php echo esc_attr( $colour ); ?>;font-weight:600;"><?php echo esc_html( $e['level'] ); ?></td>
					<td><?php echo esc_html( $e['message'] ); ?></td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<?php
}
