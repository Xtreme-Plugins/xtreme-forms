<?php
/**
 * Settings admin page.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

$settings = get_option( 'xtremeleads_settings', array() );
$recipients = $settings['recipients'] ?? get_option( 'admin_email', '' );
$anonymize = ! empty( $settings['anonymize_ip'] ) && '1' === (string) $settings['anonymize_ip'];
$from_name = $settings['email_from_name'] ?? get_bloginfo( 'name' );
$from_email = $settings['email_from'] ?? get_option( 'admin_email', '' );

// reCAPTCHA.
$rc_enabled = ! empty( $settings['recaptcha_enabled'] ) && '1' === (string) $settings['recaptcha_enabled'];
$rc_site_key = $settings['recaptcha_site_key'] ?? '';
$rc_secret_key = $settings['recaptcha_secret_key'] ?? '';
$rc_threshold = isset( $settings['recaptcha_threshold'] ) ? (float) $settings['recaptcha_threshold'] : 0.5;

// Blocklists.
$domain_blocklist = $settings['spam_domain_blocklist'] ?? '';
$keyword_blocklist = $settings['spam_keyword_blocklist'] ?? '';

// Retention.
$retention_days = isset( $settings['retention_days'] ) ? (int) $settings['retention_days'] : '';
$next_purge_time = XL_GDPR::get_next_purge_time();

// GDPR nonce for erasure AJAX.
$gdpr_nonce = wp_create_nonce( 'xl_gdpr_nonce' );

$notice_html = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved successfully.', 'xtremeleads' ) . '</p></div>';
}
// Per-site opt-out toggle notice.
if ( ! empty( $_GET['xl_site_toggled'] ) && is_multisite() ) { // phpcs:ignore WordPress.Security.NonceVerification
	$disabled_now = '1' === sanitize_text_field( wp_unslash( $_GET['xl_site_disabled'] ?? '0' ) ); // phpcs:ignore WordPress.Security.NonceVerification
	if ( $disabled_now ) {
		$notice_html .= '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'XtremeLeads has been disabled for this site. The XtremeLeads menu will be hidden until re-enabled.', 'xtremeleads' ) . '</p></div>';
	} else {
		$notice_html .= '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'XtremeLeads has been re-enabled for this site.', 'xtremeleads' ) . '</p></div>';
	}
}
if ( ! empty( $_GET['error'] ) && 'retention_min' === sanitize_key( $_GET['error'] ) ) {
	$notice_html .= '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Retention period must be at least 1 day. Values of 0 or below are not allowed.', 'xtremeleads' ) . '</p></div>';
}
// reCAPTCHA key warnings (set by handle_save_settings_s5 on save).
if ( ! empty( $_GET['recaptcha_warning'] ) ) {
	$rc_warn_type = sanitize_key( $_GET['recaptcha_warning'] );
	if ( 'missing' === $rc_warn_type ) {
		$notice_html .= '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Warning: reCAPTCHA is enabled but one or both keys are blank. reCAPTCHA verification is inactive until both a Site Key and Secret Key are provided.', 'xtremeleads' ) . '</p></div>';
	} elseif ( 'invalid' === $rc_warn_type ) {
		$notice_html .= '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Warning: One or both reCAPTCHA keys appear to be invalid (keys must be at least 20 characters and contain only letters, numbers, underscores, and hyphens). Please verify your keys at the Google reCAPTCHA console.', 'xtremeleads' ) . '</p></div>';
	}
}
?>
<div class="wrap xl-wrap">
	<h1 class="xl-page-title"><?php esc_html_e( 'XtremeLeads Settings', 'xtremeleads' ); ?></h1>

	<?php echo wp_kses_post( $notice_html ); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="xl_save_settings">
		<?php wp_nonce_field( 'xl_save_settings' ); ?>

		<div class="xl-settings-card">
			<h2><?php esc_html_e( 'Email Notifications', 'xtremeleads' ); ?></h2>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="recipients"><?php esc_html_e( 'Global Recipients', 'xtremeleads' ); ?></label>
						</th>
						<td>
							<input type="text" id="recipients" name="recipients" value="<?php echo esc_attr( $recipients ); ?>" class="regular-text" placeholder="email@example.com, another@example.com">
							<p class="description"><?php esc_html_e( 'Comma-separated list of email addresses to notify on every new lead. Individual forms can override this.', 'xtremeleads' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="email_from_name"><?php esc_html_e( 'From Name', 'xtremeleads' ); ?></label>
						</th>
						<td>
							<input type="text" id="email_from_name" name="email_from_name" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="email_from"><?php esc_html_e( 'From Email', 'xtremeleads' ); ?></label>
						</th>
						<td>
							<input type="email" id="email_from" name="email_from" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text">
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="xl-settings-card">
			<h2><?php esc_html_e( 'Privacy & GDPR', 'xtremeleads' ); ?></h2>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'IP Anonymization', 'xtremeleads' ); ?></th>
						<td>
							<label class="xl-checkbox-inline">
								<input type="checkbox" id="anonymize_ip" name="anonymize_ip" value="1" <?php checked( $anonymize ); ?>>
								<?php esc_html_e( 'Anonymize IP addresses stored with new lead submissions', 'xtremeleads' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'IPv4: last octet zeroed (e.g. 192.168.1.100 → 192.168.1.0). IPv6: last 80 bits zeroed. Existing records are NOT retroactively changed.', 'xtremeleads' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<?php submit_button( __( 'Save Settings', 'xtremeleads' ), 'primary xl-btn-primary' ); ?>
	</form>

	<!-- reCAPTCHA, Spam Blocklists, Retention, Right to Erasure -->
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="xl-security-settings-form">
		<input type="hidden" name="action" value="xl_save_settings_s5">
		<?php wp_nonce_field( 'xl_save_settings' ); ?>

		<div class="xl-settings-card">
			<h2><?php esc_html_e( 'Google reCAPTCHA v3', 'xtremeleads' ); ?></h2>
			<p class="description" style="margin-bottom:16px;">
				<?php esc_html_e( 'When enabled globally and per-form, reCAPTCHA v3 verifies human submissions without user interaction. If the reCAPTCHA API is unreachable, submissions are allowed through.', 'xtremeleads' ); ?>
			</p>
			<?php
			// Inline key validation: show warning for blank keys OR keys that do not match
			// the expected reCAPTCHA v3 format ([A-Za-z0-9_\-]{20,}).
			$rc_inline_warn = '';
			if ( $rc_enabled ) {
				if ( '' === $rc_site_key || '' === $rc_secret_key ) {
					$rc_inline_warn = 'missing';
				} elseif (
					! preg_match( '/^[A-Za-z0-9_\-]{20,}$/', $rc_site_key ) ||
					! preg_match( '/^[A-Za-z0-9_\-]{20,}$/', $rc_secret_key )
				) {
					$rc_inline_warn = 'invalid';
				}
			}
			?>
			<?php if ( 'missing' === $rc_inline_warn ) : ?>
				<div class="notice notice-warning inline" id="xl-rc-missing-warn" style="margin-bottom:12px;">
					<p><?php esc_html_e( 'Warning: reCAPTCHA is enabled but Site Key or Secret Key is missing. reCAPTCHA verification is inactive until both keys are provided.', 'xtremeleads' ); ?></p>
				</div>
			<?php elseif ( 'invalid' === $rc_inline_warn ) : ?>
				<div class="notice notice-warning inline" id="xl-rc-invalid-warn" style="margin-bottom:12px;">
					<p><?php esc_html_e( 'Warning: One or both reCAPTCHA keys appear invalid (keys must be at least 20 characters using letters, numbers, underscores, and hyphens only). Please verify your keys at the Google reCAPTCHA admin console.', 'xtremeleads' ); ?></p>
				</div>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable reCAPTCHA v3', 'xtremeleads' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="recaptcha_enabled" value="1" <?php checked( $rc_enabled ); ?>>
								<?php esc_html_e( 'Enable globally (must also be enabled per-form in the form builder)', 'xtremeleads' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="recaptcha_site_key"><?php esc_html_e( 'Site Key', 'xtremeleads' ); ?></label></th>
						<td>
							<input type="text" id="recaptcha_site_key" name="recaptcha_site_key" value="<?php echo esc_attr( $rc_site_key ); ?>" class="regular-text">
							<p class="description"><?php esc_html_e( 'Your reCAPTCHA v3 Site Key from Google.', 'xtremeleads' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="recaptcha_secret_key"><?php esc_html_e( 'Secret Key', 'xtremeleads' ); ?></label></th>
						<td>
							<input type="password" id="recaptcha_secret_key" name="recaptcha_secret_key" value="<?php echo esc_attr( $rc_secret_key ); ?>" class="regular-text" autocomplete="new-password">
							<p class="description"><?php esc_html_e( 'Your reCAPTCHA v3 Secret Key. Stored securely and never exposed in front-end HTML.', 'xtremeleads' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="recaptcha_threshold"><?php esc_html_e( 'Score Threshold', 'xtremeleads' ); ?></label></th>
						<td>
							<input type="number" id="recaptcha_threshold" name="recaptcha_threshold" value="<?php echo esc_attr( $rc_threshold ); ?>" min="0.1" max="0.9" step="0.1" class="small-text">
							<p class="description"><?php esc_html_e( 'Submissions with a reCAPTCHA score below this value are rejected. Default: 0.5. Range: 0.1–0.9.', 'xtremeleads' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="xl-settings-card">
			<h2><?php esc_html_e( 'Spam Blocklists', 'xtremeleads' ); ?></h2>
			<p class="description" style="margin-bottom:16px;">
				<?php esc_html_e( 'Blocklist entries are applied to all forms. Domain matches are exact (e.g. "spam.com" does NOT block "notspam.com"). Keyword matching is case-insensitive. Supports up to 100+ entries per list.', 'xtremeleads' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="spam_domain_blocklist"><?php esc_html_e( 'Email Domain Blocklist', 'xtremeleads' ); ?></label></th>
						<td>
							<textarea id="spam_domain_blocklist" name="spam_domain_blocklist" rows="6" class="large-text" placeholder="spam.com&#10;disposablemail.org&#10;trashmail.net"><?php echo esc_textarea( $domain_blocklist ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One domain per line (or comma-separated). Duplicate entries are ignored.', 'xtremeleads' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spam_keyword_blocklist"><?php esc_html_e( 'Keyword Blocklist', 'xtremeleads' ); ?></label></th>
						<td>
							<textarea id="spam_keyword_blocklist" name="spam_keyword_blocklist" rows="6" class="large-text" placeholder="buy cheap&#10;casino&#10;free money"><?php echo esc_textarea( $keyword_blocklist ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One keyword per line (or comma-separated). Matched case-insensitively against all text field values. Duplicate entries are ignored.', 'xtremeleads' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<div class="xl-settings-card">
			<h2><?php esc_html_e( 'Data Retention Policy', 'xtremeleads' ); ?></h2>
			<p class="description" style="margin-bottom:16px;">
				<?php esc_html_e( 'Automatically delete lead records older than the configured number of days. This purge runs daily via WP-Cron and permanently deletes all associated notes, tags, and email log entries. Leave blank to disable automatic purging.', 'xtremeleads' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="retention_days"><?php esc_html_e( 'Delete leads older than (days)', 'xtremeleads' ); ?></label></th>
						<td>
							<input type="number" id="retention_days" name="retention_days" value="<?php echo esc_attr( (string) $retention_days ); ?>" min="1" step="1" class="small-text" placeholder="<?php esc_attr_e( 'e.g. 365', 'xtremeleads' ); ?>">
							<p class="description">
								<?php esc_html_e( 'Minimum value: 1 day. Leave blank to disable. Value of 0 or below is rejected.', 'xtremeleads' ); ?>
							</p>
							<?php if ( $next_purge_time ) : ?>
								<p class="description">
									<?php
									/* translators: %s: next scheduled run time */
									printf( esc_html__( 'Next scheduled purge: %s', 'xtremeleads' ), '<strong>' . esc_html( $next_purge_time ) . '</strong>' );
									?>
								</p>
							<?php elseif ( $retention_days ) : ?>
								<p class="description" style="color:#FFC107;"><?php esc_html_e( 'Purge cron not scheduled. Save settings to reschedule.', 'xtremeleads' ); ?></p>
							<?php else : ?>
								<p class="description" style="color:#6C757D;"><?php esc_html_e( 'Automatic purge is currently disabled.', 'xtremeleads' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<?php submit_button( __( 'Save Spam & GDPR Settings', 'xtremeleads' ), 'primary xl-btn-primary', 'xl_save_s5_settings' ); ?>
	</form>

	<!-- Right to Erasure Tool -->
	<div class="xl-settings-card">
		<h2><?php esc_html_e( 'Right to Erasure (GDPR)', 'xtremeleads' ); ?></h2>
		<p class="description" style="margin-bottom:16px;">
			<?php esc_html_e( 'Permanently delete all lead records, notes, tags, and email log entries associated with a given email address. This action cannot be undone.', 'xtremeleads' ); ?>
		</p>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="xl-erase-email"><?php esc_html_e( 'Email Address', 'xtremeleads' ); ?></label></th>
					<td>
						<input type="email" id="xl-erase-email" class="regular-text" placeholder="person@example.com">
						<button type="button" id="xl-erase-btn" class="button button-link-delete" style="margin-left:8px;">
							<?php esc_html_e( 'Erase All Data', 'xtremeleads' ); ?>
						</button>
					</td>
				</tr>
			</tbody>
		</table>
		<div id="xl-erase-result" style="margin-top:12px;display:none;" class="notice inline">
			<p id="xl-erase-result-msg"></p>
		</div>
	</div>

	<?php
	// Duplicate behavior settings — saved via separate action to keep these fields isolated.
	$dup_behavior = $settings['duplicate_behavior'] ?? 'silent_flag';
	$dup_block_message = $settings['duplicate_block_message'] ?? '';
	$behavior_options = array(
		'silent_flag' => __( 'Silent Flag — save lead normally but mark as duplicate', 'xtremeleads' ),
		'block' => __( 'Block Resubmission — reject the duplicate and show a message to the visitor', 'xtremeleads' ),
		'merge' => __( 'Merge — update the original lead with non-empty values from the new submission', 'xtremeleads' ),
	);
	?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="xl-duplicate-settings-form">
		<input type="hidden" name="action" value="xl_save_settings_s4">
		<?php wp_nonce_field( 'xl_save_settings' ); ?>

		<div class="xl-settings-card">
			<h2><?php esc_html_e( 'Duplicate Lead Detection', 'xtremeleads' ); ?></h2>
			<p class="description" style="margin-bottom:16px;">
				<?php esc_html_e( 'When a new submission arrives with an email address that already exists in the leads database, choose how XtremeLeads should handle it.', 'xtremeleads' ); ?>
			</p>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="duplicate_behavior"><?php esc_html_e( 'Duplicate Behavior', 'xtremeleads' ); ?></label>
						</th>
						<td>
							<select id="duplicate_behavior" name="duplicate_behavior" class="regular-text" onchange="xlToggleDupMessage(this.value)">
								<?php foreach ( $behavior_options as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $dup_behavior, $val ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e( 'Changes take effect immediately on the next submission — no cache clearing required.', 'xtremeleads' ); ?>
							</p>
						</td>
					</tr>
					<tr id="xl-dup-block-message-row" style="<?php echo 'block' === $dup_behavior ? '' : 'display:none;'; ?>">
						<th scope="row">
							<label for="duplicate_block_message"><?php esc_html_e( 'Block Message', 'xtremeleads' ); ?></label>
						</th>
						<td>
							<textarea id="duplicate_block_message" name="duplicate_block_message" rows="3" class="large-text"><?php echo esc_textarea( $dup_block_message ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Message displayed inline to the visitor when their submission is blocked as a duplicate. Leave blank to use the default message.', 'xtremeleads' ); ?>
							</p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<?php submit_button( __( 'Save Duplicate Settings', 'xtremeleads' ), 'primary xl-btn-primary', 'xl_save_dup_settings' ); ?>
	</form>

	<script>
	function xlToggleDupMessage( val ) {
		var row = document.getElementById( 'xl-dup-block-message-row' );
		if ( row ) {
			row.style.display = ( val === 'block' ) ? '' : 'none';
		}
	}
	</script>
</div>

<?php if ( is_multisite() && class_exists( 'XL_Multisite' ) ) : ?>
	<div class="xl-settings-card" style="margin-top:24px;">
		<h2><?php esc_html_e( 'Network: Per-Site Access Control', 'xtremeleads' ); ?></h2>
		<p class="description" style="margin-bottom:16px;">
			<?php esc_html_e( 'When XtremeLeads is network-activated, each site admin can disable the plugin\'s menu and data collection for their site. This allows individual sites to opt out without affecting the rest of the network.', 'xtremeleads' ); ?>
		</p>
		<?php $site_is_disabled = XL_Multisite::is_site_disabled(); ?>
		<p>
			<?php if ( $site_is_disabled ) : ?>
				<strong style="color:#DC3545;"><?php esc_html_e( 'Status: XtremeLeads is currently DISABLED for this site.', 'xtremeleads' ); ?></strong>
			<?php else : ?>
				<strong style="color:#28A745;"><?php esc_html_e( 'Status: XtremeLeads is currently ENABLED for this site.', 'xtremeleads' ); ?></strong>
			<?php endif; ?>
		</p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="xl_toggle_site_disabled">
			<?php wp_nonce_field( 'xl_toggle_site_disabled' ); ?>
			<?php if ( $site_is_disabled ) : ?>
				<button type="submit" class="button button-primary">
					<?php esc_html_e( 'Re-enable XtremeLeads for This Site', 'xtremeleads' ); ?>
				</button>
			<?php else : ?>
				<button type="submit" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Disable XtremeLeads for this site? The menu will be hidden until re-enabled.', 'xtremeleads' ) ); ?>')">
					<?php esc_html_e( 'Disable XtremeLeads for This Site', 'xtremeleads' ); ?>
				</button>
			<?php endif; ?>
		</form>
	</div>
<?php endif; ?>

<script>
(function(){
	var gdprNonce = <?php echo wp_json_encode( $gdpr_nonce ); ?>;
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

	var eraseBtn = document.getElementById('xl-erase-btn');
	if ( eraseBtn ) {
		eraseBtn.addEventListener('click', function() {
			var email = document.getElementById('xl-erase-email').value.trim();
			if ( ! email ) {
				alert('<?php echo esc_js( __( 'Please enter an email address.', 'xtremeleads' ) ); ?>');
				return;
			}
			// Basic email format check before sending.
			var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			if ( ! emailRegex.test( email ) ) {
				alert('<?php echo esc_js( __( 'Please enter a valid email address.', 'xtremeleads' ) ); ?>');
				return;
			}
			if ( ! confirm('<?php echo esc_js( __( 'Permanently delete ALL data for this email address? This cannot be undone.', 'xtremeleads' ) ); ?>') ) {
				return;
			}

			var result = document.getElementById('xl-erase-result');
			var msg = document.getElementById('xl-erase-result-msg');
			result.style.display = 'none';

			var fd = new FormData();
			fd.append('action', 'xl_gdpr_erase');
			fd.append('nonce', gdprNonce);
			fd.append('email', email);

			fetch(ajaxUrl, {method:'POST', body:fd})
				.then(function(r){ return r.json(); })
				.then(function(resp) {
					result.style.display = '';
					if (resp.success) {
						result.className = 'notice notice-success inline';
						msg.textContent = resp.data.message || '<?php echo esc_js( __( 'Data erased successfully.', 'xtremeleads' ) ); ?>';
						document.getElementById('xl-erase-email').value = '';
					} else {
						result.className = 'notice notice-error inline';
						msg.textContent = (resp.data && resp.data.message) || '<?php echo esc_js( __( 'Erasure failed.', 'xtremeleads' ) ); ?>';
					}
				})
				.catch(function() {
					result.style.display = '';
					result.className = 'notice notice-error inline';
					msg.textContent = '<?php echo esc_js( __( 'Network error.', 'xtremeleads' ) ); ?>';
				});
		});
	}
})();
</script>
