<?php
/**
 * License (Pro add-on activation) tab — Settings page.
 *
 * The free plugin owns this UI. Activation does NOT unlock anything in the
 * free plugin. The stored license is read by the optional Xtreme Forms Pro
 * add-on (sold from xtremeplugins.com) to enable its own features.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
}

$license_data    = Xtremeforms_License::get_data();
$license_active  = Xtremeforms_License::is_active();
$license_key     = (string) $license_data['key'];
$license_status  = (string) $license_data['status'];
$license_plan    = (string) $license_data['plan'];
$license_expires = $license_data['expires_at'] ?? null;
$masked_key      = '' !== $license_key ? Xtremeforms_License::mask( $license_key ) : '';

$status_label = $license_active
	? __( 'Active', 'xtreme-forms' )
	: ( 'expired' === $license_status
		? __( 'Expired', 'xtreme-forms' )
		: ( 'invalid' === $license_status
			? __( 'Invalid', 'xtreme-forms' )
			: __( 'Not activated', 'xtreme-forms' ) ) );

$status_color = $license_active ? '#16a34a' : ( 'inactive' === $license_status ? '#6b7280' : '#dc2626' );
?>
<div class="xf-settings-card">
	<h2>
		<span class="dashicons dashicons-admin-network" style="font-size:18px;vertical-align:middle;margin-right:8px;color:var(--xf-teal);"></span>
		<?php esc_html_e( 'Xtreme Forms Pro — License', 'xtreme-forms' ); ?>
	</h2>

	<p style="margin:0 0 16px;color:#4b5563;line-height:1.55;">
		<?php esc_html_e( 'Xtreme Forms is free, and every feature documented on the WordPress.org listing is included with no caps. An optional Pro add-on sold from xtremeplugins.com adds priority routing, webhook retry queue, advanced analytics, and priority support. If you have purchased a Pro license, enter it below to activate the add-on for this site.', 'xtreme-forms' ); ?>
	</p>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th scope="row">
					<label for="xf-license-key"><?php esc_html_e( 'License Key', 'xtreme-forms' ); ?></label>
				</th>
				<td>
					<?php if ( $license_active ) : ?>
						<input type="text" id="xf-license-key" class="regular-text code" value="<?php echo esc_attr( $masked_key ); ?>" readonly>
						<button type="button" id="xf-license-deactivate" class="button button-secondary"><?php esc_html_e( 'Deactivate', 'xtreme-forms' ); ?></button>
					<?php else : ?>
						<input type="text" id="xf-license-key" class="regular-text code" value="" placeholder="XXXX-XXXX-XXXX-XXXX" autocomplete="off">
						<button type="button" id="xf-license-activate" class="button button-primary"><?php esc_html_e( 'Activate', 'xtreme-forms' ); ?></button>
					<?php endif; ?>
					<p class="description" id="xf-license-feedback" style="min-height:1.4em;"></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'xtreme-forms' ); ?></th>
				<td>
					<span id="xf-license-status" style="display:inline-block;padding:3px 10px;border-radius:999px;font-weight:600;font-size:12px;color:#fff;background:<?php echo esc_attr( $status_color ); ?>;">
						<?php echo esc_html( $status_label ); ?>
					</span>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Plan', 'xtreme-forms' ); ?></th>
				<td>
					<code id="xf-license-plan" style="font-size:13px;"><?php echo esc_html( $license_plan ); ?></code>
				</td>
			</tr>
			<?php if ( ! empty( $license_expires ) ) : ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Renews / Expires', 'xtreme-forms' ); ?></th>
				<td>
					<code style="font-size:13px;"><?php echo esc_html( $license_expires ); ?></code>
				</td>
			</tr>
			<?php endif; ?>
		</tbody>
	</table>

	<?php if ( ! $license_active ) : ?>
		<div style="margin-top:20px;padding:20px;border:1px solid #e5e7eb;border-radius:8px;background:#f9fafb;">
			<h3 style="margin:0 0 8px;font-size:15px;color:#111827;">
				<?php esc_html_e( "Don't have a Pro license yet?", 'xtreme-forms' ); ?>
			</h3>
			<p style="margin:0 0 14px;color:#4b5563;font-size:13px;line-height:1.55;">
				<?php esc_html_e( 'Get monthly or yearly Pro plans at xtremeplugins.com.', 'xtreme-forms' ); ?>
			</p>
			<a href="https://xtremeplugins.com/plugins/xtreme-forms" target="_blank" rel="noopener noreferrer" class="button button-primary">
				<?php esc_html_e( 'View Pricing →', 'xtreme-forms' ); ?>
			</a>
		</div>
	<?php endif; ?>
</div>

<?php
ob_start();
?>
( function () {
	var ajax = ( typeof xtremeFormsAdminData !== 'undefined' && xtremeFormsAdminData.ajaxUrl )
		? xtremeFormsAdminData.ajaxUrl
		: ( typeof ajaxurl !== 'undefined' ? ajaxurl : '/wp-admin/admin-ajax.php' );
	var nonce = ( typeof xtremeFormsAdminData !== 'undefined' && xtremeFormsAdminData.nonce )
		? xtremeFormsAdminData.nonce
		: '';

	var keyInput    = document.getElementById( 'xf-license-key' );
	var activateBtn = document.getElementById( 'xf-license-activate' );
	var deactivateBtn = document.getElementById( 'xf-license-deactivate' );
	var feedback    = document.getElementById( 'xf-license-feedback' );

	function setFeedback( msg, isError ) {
		if ( ! feedback ) return;
		feedback.textContent = msg || '';
		feedback.style.color = isError ? '#b91c1c' : '#047857';
	}

	function post( body, cb ) {
		var fd = new FormData();
		Object.keys( body ).forEach( function ( k ) { fd.append( k, body[ k ] ); } );
		fd.append( 'nonce', nonce );
		fetch( ajax, { method: 'POST', credentials: 'same-origin', body: fd } )
			.then( function ( r ) { return r.json(); } )
			.then( function ( j ) { cb( null, j ); } )
			.catch( function ( e ) { cb( e ); } );
	}

	if ( activateBtn ) {
		activateBtn.addEventListener( 'click', function () {
			var key = ( keyInput.value || '' ).trim();
			if ( ! key ) {
				setFeedback( '<?php echo esc_js( __( 'Please enter a license key.', 'xtreme-forms' ) ); ?>', true );
				return;
			}
			activateBtn.disabled = true;
			activateBtn.textContent = '<?php echo esc_js( __( 'Activating…', 'xtreme-forms' ) ); ?>';
			setFeedback( '' );

			post( { action: 'xtremeforms_activate_license', license_key: key }, function ( err, res ) {
				activateBtn.disabled = false;
				activateBtn.textContent = '<?php echo esc_js( __( 'Activate', 'xtreme-forms' ) ); ?>';
				if ( err || ! res || ! res.success ) {
					setFeedback( ( res && res.data && res.data.message ) || '<?php echo esc_js( __( 'Activation failed. Please try again.', 'xtreme-forms' ) ); ?>', true );
					return;
				}
				setFeedback( ( res.data && res.data.message ) || '<?php echo esc_js( __( 'License activated.', 'xtreme-forms' ) ); ?>', false );
				// Reload so the panel re-renders in the activated state.
				setTimeout( function () { window.location.reload(); }, 600 );
			} );
		} );
	}

	if ( deactivateBtn ) {
		deactivateBtn.addEventListener( 'click', function () {
			if ( ! window.confirm( '<?php echo esc_js( __( 'Deactivate the license on this site?', 'xtreme-forms' ) ); ?>' ) ) {
				return;
			}
			deactivateBtn.disabled = true;
			deactivateBtn.textContent = '<?php echo esc_js( __( 'Deactivating…', 'xtreme-forms' ) ); ?>';
			setFeedback( '' );

			post( { action: 'xtremeforms_deactivate_license' }, function ( err, res ) {
				deactivateBtn.disabled = false;
				deactivateBtn.textContent = '<?php echo esc_js( __( 'Deactivate', 'xtreme-forms' ) ); ?>';
				if ( err || ! res || ! res.success ) {
					setFeedback( ( res && res.data && res.data.message ) || '<?php echo esc_js( __( 'Deactivation failed. Please try again.', 'xtreme-forms' ) ); ?>', true );
					return;
				}
				setFeedback( ( res.data && res.data.message ) || '<?php echo esc_js( __( 'License deactivated.', 'xtreme-forms' ) ); ?>', false );
				setTimeout( function () { window.location.reload(); }, 600 );
			} );
		} );
	}
} )();
<?php
$xtremeforms_inline_js = ob_get_clean();
wp_add_inline_script( 'xtremeforms-admin', $xtremeforms_inline_js, 'after' );
?>
