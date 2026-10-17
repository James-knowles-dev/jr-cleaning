<?php
/**
 * Branch details and email preferences on My Account → Account details.
 *
 * The Account details screen was stock WooCommerce: a name, a display name, an email
 * and a password. Nothing on it described the *branch*, which meant the office address
 * and the inbox reports should land in were held in somebody's memory or an old email
 * thread, and there was nowhere for a partner to say "not that one, this one".
 *
 * Everything here is added to WooCommerce's existing form rather than a form of its
 * own, so it saves in the same submission and inherits core's nonce
 * (`save-account-details-nonce`), which WooCommerce verifies before
 * woocommerce_save_account_details fires. Adding a second nonce would suggest a second
 * trust boundary that does not exist.
 *
 * @package jrc-partners
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * The branch fields, as key => label/type.
 *
 * @return array<string,array{label:string,type:string,help:string}>
 */
function jrc_branch_fields() {
	return array(
		'jrc_branch_name'    => array(
			'label' => 'Branch or company name',
			'type'  => 'text',
			'help'  => '',
		),
		'jrc_branch_phone'   => array(
			'label' => 'Office phone',
			'type'  => 'tel',
			'help'  => '',
		),
		'jrc_branch_address' => array(
			'label' => 'Office address',
			'type'  => 'textarea',
			'help'  => 'Where your agency price list and any paperwork should be sent.',
		),
		'jrc_reports_email'  => array(
			'label' => 'Send reports to',
			'type'  => 'email',
			'help'  => 'Optional. Leave blank to use your account email. Useful if photo reports and the monthly summary should land in a shared lettings inbox.',
		),
	);
}

/**
 * Render the branch and notification blocks inside WooCommerce's account form.
 *
 * Only for partners: a retail customer buying a bottle of surface spray has no branch
 * and no notifications to configure, and an empty fieldset is worse than none.
 */
add_action( 'woocommerce_edit_account_form', function () {
	if ( ! function_exists( 'jrc_is_affiliate' ) || ! jrc_is_affiliate() ) {
		return;
	}

	$user_id = get_current_user_id();
	?>
	<fieldset class="jrc-account-block">
		<legend>Your branch</legend>
		<p class="jrc-account-block__intro">So we know where to send your price list, and who should get your reports.</p>

		<?php foreach ( jrc_branch_fields() as $key => $field ) :
			$value = (string) get_user_meta( $user_id, $key, true );
			$id    = esc_attr( $key );
			?>
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="<?php echo $id; ?>"><?php echo esc_html( $field['label'] ); ?></label>
				<?php if ( 'textarea' === $field['type'] ) : ?>
					<textarea
						class="woocommerce-Input woocommerce-Input--textarea input-text"
						name="<?php echo $id; ?>"
						id="<?php echo $id; ?>"
						rows="3"><?php echo esc_textarea( $value ); ?></textarea>
				<?php else : ?>
					<input
						type="<?php echo esc_attr( $field['type'] ); ?>"
						class="woocommerce-Input woocommerce-Input--text input-text"
						name="<?php echo $id; ?>"
						id="<?php echo $id; ?>"
						value="<?php echo esc_attr( $value ); ?>"
						<?php echo $field['help'] ? 'aria-describedby="' . $id . '-help"' : ''; ?> />
				<?php endif; ?>
				<?php if ( $field['help'] ) : ?>
					<span class="jrc-account-block__help" id="<?php echo $id; ?>-help"><?php echo esc_html( $field['help'] ); ?></span>
				<?php endif; ?>
			</p>
		<?php endforeach; ?>
	</fieldset>

	<fieldset class="jrc-account-block">
		<legend>Emails from us</legend>
		<p class="jrc-account-block__intro">Everything here is off or on per email — turning one off never affects the others.</p>

		<?php
		$prefs = jrc_notify_prefs( $user_id );
		foreach ( jrc_notify_types() as $key => $def ) :
			$id = 'jrc_notify_' . esc_attr( $key );
			?>
			<label class="jrc-notify-option" for="<?php echo $id; ?>">
				<input
					type="checkbox"
					name="jrc_notify[<?php echo esc_attr( $key ); ?>]"
					id="<?php echo $id; ?>"
					value="1"
					<?php checked( ! empty( $prefs[ $key ] ) ); ?> />
				<span class="jrc-notify-option__body">
					<strong><?php echo esc_html( $def['label'] ); ?></strong>
					<span><?php echo esc_html( $def['help'] ); ?></span>
				</span>
			</label>
		<?php endforeach; ?>

		<?php
		// A hidden marker so the save handler can tell "every box unticked" apart from
		// "this form did not carry the notification block at all" — unchecked boxes
		// post nothing, so without it a partner could never turn the last one off.
		?>
		<input type="hidden" name="jrc_notify_present" value="1" />
	</fieldset>
	<?php
} );

/**
 * Validate before WooCommerce saves anything.
 *
 * @param WP_Error $errors Collector WooCommerce checks after this runs.
 */
add_action( 'woocommerce_save_account_details_errors', function ( $errors ) {
	if ( ! function_exists( 'jrc_is_affiliate' ) || ! jrc_is_affiliate() ) {
		return;
	}

	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- WooCommerce verifies save-account-details-nonce before this hook.
	$reports_email = isset( $_POST['jrc_reports_email'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['jrc_reports_email'] ) ) ) : '';

	if ( '' !== $reports_email && ! is_email( $reports_email ) ) {
		$errors->add( 'jrc_reports_email', __( 'Please enter a valid address for reports, or leave it blank to use your account email.', 'jrc-partners' ) );
	}
}, 10, 1 );

/**
 * Persist the branch fields and the notification preferences.
 */
add_action( 'woocommerce_save_account_details', function ( $user_id ) {
	if ( ! function_exists( 'jrc_is_affiliate' ) || ! jrc_is_affiliate( $user_id ) ) {
		return;
	}

	// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce verifies save-account-details-nonce before this hook.
	foreach ( jrc_branch_fields() as $key => $field ) {
		if ( ! isset( $_POST[ $key ] ) ) {
			continue;
		}
		$raw = wp_unslash( $_POST[ $key ] );

		if ( 'textarea' === $field['type'] ) {
			$value = sanitize_textarea_field( $raw );
		} elseif ( 'email' === $field['type'] ) {
			$value = sanitize_email( $raw );
			// Validation above has already rejected a malformed address, so anything
			// left that is not an email is a deliberate blank.
			$value = is_email( $value ) ? $value : '';
		} else {
			$value = sanitize_text_field( $raw );
		}

		update_user_meta( $user_id, $key, $value );
	}

	if ( ! empty( $_POST['jrc_notify_present'] ) ) {
		$posted = isset( $_POST['jrc_notify'] ) && is_array( $_POST['jrc_notify'] )
			? array_map( 'sanitize_key', array_keys( wp_unslash( $_POST['jrc_notify'] ) ) )
			: array();

		$prefs = array();
		foreach ( array_keys( jrc_notify_types() ) as $key ) {
			$prefs[ $key ] = in_array( $key, $posted, true );
		}
		update_user_meta( $user_id, 'jrc_notify_prefs', $prefs );
	}
	// phpcs:enable WordPress.Security.NonceVerification.Missing
} );

/**
 * Show the branch details on the WP user profile screen too.
 *
 * Read-only there on purpose: the partner owns this data, and an admin overwriting the
 * inbox their reports go to without them knowing is exactly the kind of quiet change
 * that ends in "we never got it".
 */
add_action( 'show_user_profile', 'jrc_branch_profile_readout' );
add_action( 'edit_user_profile', 'jrc_branch_profile_readout' );
function jrc_branch_profile_readout( $user ) {
	if ( ! current_user_can( 'edit_users' ) || ! jrc_is_affiliate( $user->ID ) ) {
		return;
	}

	$any = false;
	foreach ( array_keys( jrc_branch_fields() ) as $key ) {
		if ( get_user_meta( $user->ID, $key, true ) ) {
			$any = true;
			break;
		}
	}
	?>
	<h2>Branch details</h2>
	<p class="description" style="margin:-8px 0 10px;">Set by the partner on My&nbsp;Account → Account details. Shown here for reference.</p>
	<table class="form-table" role="presentation">
		<?php if ( ! $any ) : ?>
			<tr><td colspan="2" style="color:#646970;">Nothing filled in yet.</td></tr>
		<?php else : ?>
			<?php foreach ( jrc_branch_fields() as $key => $field ) :
				$value = (string) get_user_meta( $user->ID, $key, true );
				if ( '' === $value ) { continue; } ?>
				<tr>
					<th><?php echo esc_html( $field['label'] ); ?></th>
					<td><?php echo nl2br( esc_html( $value ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		<tr>
			<th>Emails they receive</th>
			<td>
				<?php
				$prefs = jrc_notify_prefs( $user->ID );
				$on    = array();
				foreach ( jrc_notify_types() as $key => $def ) {
					if ( ! empty( $prefs[ $key ] ) ) {
						$on[] = $def['label'];
					}
				}
				echo $on ? esc_html( implode( ', ', $on ) ) : '<span style="color:#646970;">None — all switched off.</span>';
				?>
			</td>
		</tr>
	</table>
	<?php
}
