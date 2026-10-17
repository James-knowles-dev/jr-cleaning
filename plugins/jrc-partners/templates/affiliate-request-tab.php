<?php
/**
 * Partner tab: request a clean.
 *
 * Posts to includes/affiliate-requests.php, which writes the row and redirects back
 * here. Nothing is stored from this template.
 *
 * @package jrc-partners
 */
if ( ! defined( 'ABSPATH' ) || empty( $aff ) ) { return; }

$uid   = get_current_user_id();
$flash = jrc_request_flash( $uid );
$err   = $flash['errors'] ?? array();
$val   = $flash['values'] ?? array();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only; the POST that produced it was verified.
$state = isset( $_GET['requested'] ) ? sanitize_key( wp_unslash( $_GET['requested'] ) ) : '';

/** Repopulate a field after a validation failure. */
$v = function ( $key, $default = '' ) use ( $val ) {
	return isset( $val[ $key ] ) ? (string) $val[ $key ] : $default;
};

/** Render the error for one field, if it has one. */
$e = function ( $key ) use ( $err ) {
	if ( empty( $err[ $key ] ) ) {
		return '';
	}
	return '<span class="aff-form__err" id="' . esc_attr( 'err-' . $key ) . '">' . esc_html( $err[ $key ] ) . '</span>';
};
?>
<div class="aff-dash">
	<div class="aff-card aff-form" id="request">
		<span class="aff-card__eyebrow">Request a clean</span>
		<p class="aff-form__intro">
			Send us the property and when you need it done. It goes straight into our jobs
			list and someone will come back to confirm the slot — usually the same working day.
			Nothing here is a fixed booking until we confirm it.
		</p>

		<?php if ( '1' === $state ) : ?>
			<div class="aff-notice aff-notice--ok" role="status">
				<strong>Got it.</strong> Your request is with the team and shows as
				<em>Requested</em> on your calendar. We have emailed you a copy.
			</div>
		<?php elseif ( 'throttled' === $state ) : ?>
			<div class="aff-notice aff-notice--warn" role="status">
				That looked like a double submission, so we only kept the first one. Check your
				<a href="<?php echo esc_url( jrc_affiliate_tab_url( 'cleaning-calendar' ) ); ?>">calendar</a>
				before sending it again.
			</div>
		<?php elseif ( 'capped' === $state ) : ?>
			<div class="aff-notice aff-notice--warn" role="status">
				That is a lot of requests in one day. Give us a ring on the number in the footer
				and we will take the rest over the phone.
			</div>
		<?php elseif ( 'error' === $state && ! empty( $err['general'] ) ) : ?>
			<div class="aff-notice aff-notice--error" role="alert"><?php echo esc_html( $err['general'] ); ?></div>
		<?php elseif ( 'error' === $state ) : ?>
			<div class="aff-notice aff-notice--error" role="alert">
				There is something to fix below.
			</div>
		<?php endif; ?>

		<form method="post" class="aff-form__form" novalidate>
			<?php wp_nonce_field( 'jrc_request_clean_' . $uid, 'jrc_request_nonce' ); ?>
			<input type="hidden" name="jrc_request_clean" value="1" />

			<div class="aff-form__grid">

				<p class="aff-form__row aff-form__row--wide<?php echo isset( $err['address'] ) ? ' has-error' : ''; ?>">
					<label for="req-address">Property address <span class="aff-form__req" aria-hidden="true">*</span></label>
					<input type="text" id="req-address" name="jrc_req_address" required
						value="<?php echo esc_attr( $v( 'address' ) ); ?>"
						autocomplete="off"
						<?php echo isset( $err['address'] ) ? 'aria-invalid="true" aria-describedby="err-address"' : ''; ?> />
					<?php echo $e( 'address' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>
				</p>

				<?php
				// School accounts: which building. Preselected from the Site map's
				// "Request a clean for …" link (?building=<id>).
				$req_buildings = ( function_exists( 'jrc_is_school' ) && jrc_is_school() ) ? jrc_school_buildings() : array();
				if ( $req_buildings ) :
					$req_b = $v( 'building', isset( $_GET['building'] ) ? sanitize_key( wp_unslash( $_GET['building'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
					?>
					<p class="aff-form__row">
						<label for="req-building">Building</label>
						<select id="req-building" name="jrc_req_building">
							<option value="">Not a specific building / several</option>
							<?php foreach ( $req_buildings as $rb ) : ?>
								<option value="<?php echo esc_attr( $rb['id'] ); ?>" <?php selected( $req_b, $rb['id'] ); ?>><?php echo esc_html( $rb['name'] ); ?></option>
							<?php endforeach; ?>
						</select>
					</p>
				<?php endif; ?>

				<p class="aff-form__row<?php echo isset( $err['job_type'] ) ? ' has-error' : ''; ?>">
					<label for="req-type">What is needed <span class="aff-form__req" aria-hidden="true">*</span></label>
					<select id="req-type" name="jrc_req_type" required
						<?php echo isset( $err['job_type'] ) ? 'aria-invalid="true" aria-describedby="err-job_type"' : ''; ?>>
						<option value="">Choose a service…</option>
						<?php foreach ( jrc_request_job_types() as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>" <?php selected( $v( 'job_type' ), $type ); ?>>
								<?php echo esc_html( $type ); ?>
							</option>
						<?php endforeach; ?>
					</select>
					<?php echo $e( 'job_type' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>
				</p>

				<?php
				// Availability, so the picker can refuse a closed or full day before the
				// partner submits. The server checks again regardless — see
				// includes/affiliate-availability.php.
				$has_avail   = function_exists( 'jrc_booking_window' );
				$req_window  = $has_avail ? jrc_booking_window() : array( 'min' => current_time( 'Y-m-d' ), 'max' => '' );
				$req_closed  = $has_avail ? jrc_unavailable_dates_map() : array();
				?>
				<p class="aff-form__row<?php echo isset( $err['date'] ) ? ' has-error' : ''; ?>">
					<label for="req-date">Date you need it <span class="aff-form__req" aria-hidden="true">*</span></label>
					<input type="date" id="req-date" name="jrc_req_date" required
						min="<?php echo esc_attr( $req_window['min'] ); ?>"
						<?php if ( ! empty( $req_window['max'] ) ) : ?>max="<?php echo esc_attr( $req_window['max'] ); ?>"<?php endif; ?>
						data-closed="<?php echo esc_attr( wp_json_encode( $req_closed ) ); ?>"
						aria-describedby="req-date-help<?php echo isset( $err['date'] ) ? ' err-date' : ''; ?>"
						value="<?php echo esc_attr( $v( 'date' ) ); ?>"
						<?php echo isset( $err['date'] ) ? 'aria-invalid="true"' : ''; ?> />
					<span class="aff-form__help" id="req-date-help" data-role="date-status">
						<?php if ( $req_closed ) : ?>
							Some dates are closed or fully booked — pick one and we will tell you straight away.
						<?php else : ?>
							Tell us the day you need it and we will confirm the slot.
						<?php endif; ?>
					</span>
					<?php echo $e( 'date' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>
				</p>

				<p class="aff-form__row">
					<label for="req-slot">Preferred time</label>
					<select id="req-slot" name="jrc_req_slot">
						<?php foreach ( jrc_request_time_slots() as $key => $label ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $v( 'slot', 'any' ), $key ); ?>>
								<?php echo esc_html( $label ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</p>

				<p class="aff-form__row aff-form__row--wide aff-form__legend">
					<span>Who is at the property</span>
					<small>Optional, but it saves a round of phone calls about keys and access.</small>
				</p>

				<p class="aff-form__row">
					<label for="req-contact-name">Contact name</label>
					<input type="text" id="req-contact-name" name="jrc_req_contact_name"
						value="<?php echo esc_attr( $v( 'contact_name' ) ); ?>" autocomplete="off" />
				</p>

				<p class="aff-form__row">
					<label for="req-contact-phone">Contact phone</label>
					<input type="tel" id="req-contact-phone" name="jrc_req_contact_phone"
						value="<?php echo esc_attr( $v( 'contact_phone' ) ); ?>" autocomplete="off" />
				</p>

				<p class="aff-form__row aff-form__row--wide<?php echo isset( $err['contact_email'] ) ? ' has-error' : ''; ?>">
					<label for="req-contact-email">Contact email</label>
					<input type="email" id="req-contact-email" name="jrc_req_contact_email"
						value="<?php echo esc_attr( $v( 'contact_email' ) ); ?>" autocomplete="off"
						<?php echo isset( $err['contact_email'] ) ? 'aria-invalid="true" aria-describedby="err-contact_email"' : ''; ?> />
					<?php echo $e( 'contact_email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in the closure. ?>
				</p>

				<p class="aff-form__row aff-form__row--wide">
					<label for="req-notes">Anything we should know</label>
					<textarea id="req-notes" name="jrc_req_notes" rows="4"
						placeholder="Key safe code, parking, which rooms, whether the property is furnished…"><?php echo esc_textarea( $v( 'notes' ) ); ?></textarea>
				</p>

			</div>

			<div class="aff-form__actions">
				<button type="submit" class="button button--primary">Send request</button>
				<span class="aff-form__note">We will confirm by email before anything is booked in.</span>
			</div>
		</form>
	</div>
</div>
