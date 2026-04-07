<?php
/**
 * Settings admin page.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- GET parameters on this admin display page are read-only filter params.

$xf_settings_tab  = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
$xf_settings_tabs = array(
	'general'       => array( 'label' => __( 'General', 'xtreme-forms' ), 'icon' => 'dashicons-admin-settings' ),
	'tags'          => array( 'label' => __( 'Tags', 'xtreme-forms' ), 'icon' => 'dashicons-tag' ),
	'email-log'     => array( 'label' => __( 'Email Log', 'xtreme-forms' ), 'icon' => 'dashicons-email-alt' ),
	'spam-log'      => array( 'label' => __( 'Spam Log', 'xtreme-forms' ), 'icon' => 'dashicons-shield-alt' ),
	'import-export' => array( 'label' => __( 'Import / Export', 'xtreme-forms' ), 'icon' => 'dashicons-database-import' ),
	'audit-log'     => array( 'label' => __( 'Audit Log', 'xtreme-forms' ), 'icon' => 'dashicons-list-view' ),
);
if ( ! array_key_exists( $xf_settings_tab, $xf_settings_tabs ) ) {
	$xf_settings_tab = 'general';
}

$settings   = get_option( 'xtremeforms_settings', array() );
$recipients = $settings['recipients'] ?? get_option( 'admin_email', '' );
$anonymize  = ! empty( $settings['anonymize_ip'] ) && '1' === (string) $settings['anonymize_ip'];
$from_name  = $settings['email_from_name'] ?? get_bloginfo( 'name' );
$from_email = $settings['email_from'] ?? get_option( 'admin_email', '' );

// reCAPTCHA.
$rc_enabled    = ! empty( $settings['recaptcha_enabled'] ) && '1' === (string) $settings['recaptcha_enabled'];
$rc_site_key   = $settings['recaptcha_site_key'] ?? '';
$rc_secret_key = $settings['recaptcha_secret_key'] ?? '';
$rc_threshold  = isset( $settings['recaptcha_threshold'] ) ? (float) $settings['recaptcha_threshold'] : 0.5;

// Turnstile.
$ts_enabled    = ! empty( $settings['turnstile_enabled'] ) && '1' === (string) $settings['turnstile_enabled'];
$ts_site_key   = $settings['turnstile_site_key'] ?? '';
$ts_secret_key = $settings['turnstile_secret_key'] ?? '';
$ts_theme      = $settings['turnstile_theme'] ?? 'auto';
$ts_size       = $settings['turnstile_size'] ?? 'normal';
$ts_active     = $ts_enabled && '' !== $ts_site_key && '' !== $ts_secret_key;

// Spam blocklists.
$domain_blocklist  = $settings['spam_domain_blocklist'] ?? '';
$keyword_blocklist = $settings['spam_keyword_blocklist'] ?? '';

// Duplicate detection.
$dup_behavior      = $settings['duplicate_behavior'] ?? 'silent_flag';
$dup_block_message = $settings['duplicate_block_message'] ?? '';
$behavior_options  = array(
	'silent_flag' => __( 'Silent Flag — save lead normally but mark as duplicate', 'xtreme-forms' ),
	'block'       => __( 'Block Resubmission — reject the duplicate and show a message to the visitor', 'xtreme-forms' ),
	'merge'       => __( 'Merge — update the original lead with non-empty values from the new submission', 'xtreme-forms' ),
);

// Retention.
$retention_days  = isset( $settings['retention_days'] ) ? (int) $settings['retention_days'] : '';
$next_purge_time = XF_GDPR::get_next_purge_time();

// GDPR nonce for erasure AJAX.
$gdpr_nonce = wp_create_nonce( 'xf_gdpr_nonce' );

// Notices.
$notice_html = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'xtreme-forms' ) . '</p></div>';
}
if ( ! empty( $_GET['xf_site_toggled'] ) && is_multisite() ) {
	$disabled_now = '1' === sanitize_text_field( wp_unslash( $_GET['xf_site_disabled'] ?? '0' ) );
	if ( $disabled_now ) {
		$notice_html .= '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'Xtreme Forms has been disabled for this site.', 'xtreme-forms' ) . '</p></div>';
	} else {
		$notice_html .= '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Xtreme Forms has been re-enabled for this site.', 'xtreme-forms' ) . '</p></div>';
	}
}
if ( ! empty( $_GET['error'] ) && 'retention_min' === sanitize_key( $_GET['error'] ) ) {
	$notice_html .= '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Retention period must be at least 1 day.', 'xtreme-forms' ) . '</p></div>';
}
if ( ! empty( $_GET['recaptcha_warning'] ) ) {
	$rc_warn_type = sanitize_key( $_GET['recaptcha_warning'] );
	if ( 'missing' === $rc_warn_type ) {
		$notice_html .= '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'reCAPTCHA is enabled but one or both keys are missing. Verification is inactive until both keys are provided.', 'xtreme-forms' ) . '</p></div>';
	} elseif ( 'invalid' === $rc_warn_type ) {
		$notice_html .= '<div class="notice notice-warning is-dismissible"><p>' . esc_html__( 'One or both reCAPTCHA keys appear invalid. Please verify your keys at the Google reCAPTCHA console.', 'xtreme-forms' ) . '</p></div>';
	}
}

// Inline reCAPTCHA key validation.
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
<div class="wrap xf-wrap">
	<div class="xf-page-header">
		<h1 class="xf-page-title"><?php esc_html_e( 'Settings', 'xtreme-forms' ); ?></h1>
	</div>

	<div class="xf-hub-tabs">
		<?php foreach ( $xf_settings_tabs as $xf_tab_slug => $xf_tab_info ) : ?>
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtreme-forms-settings', 'tab' => $xf_tab_slug ), admin_url( 'admin.php' ) ) ); ?>"
			   class="xf-hub-tab<?php echo $xf_settings_tab === $xf_tab_slug ? ' active' : ''; ?>">
				<span class="dashicons <?php echo esc_attr( $xf_tab_info['icon'] ); ?>"></span>
				<?php echo esc_html( $xf_tab_info['label'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>

	<?php echo wp_kses_post( $notice_html ); ?>

<?php if ( 'general' === $xf_settings_tab ) : ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="xf-settings-form">
		<input type="hidden" name="action" value="xf_save_settings">
		<?php wp_nonce_field( 'xf_save_settings' ); ?>

		<!-- ── Email Notifications ───────────────────────────────────────────── -->
		<div class="xf-settings-card">
			<h2>
				<span class="dashicons dashicons-email-alt" style="font-size:18px;vertical-align:middle;margin-right:8px;color:var(--xf-teal);"></span>
				<?php esc_html_e( 'Email Notifications', 'xtreme-forms' ); ?>
			</h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="recipients"><?php esc_html_e( 'Global Recipients', 'xtreme-forms' ); ?></label>
						</th>
						<td>
							<input type="text" id="recipients" name="recipients" value="<?php echo esc_attr( $recipients ); ?>" class="regular-text" placeholder="email@example.com, another@example.com">
							<p class="description"><?php esc_html_e( 'Comma-separated. Individual forms can override this.', 'xtreme-forms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="email_from_name"><?php esc_html_e( 'From Name', 'xtreme-forms' ); ?></label>
						</th>
						<td>
							<input type="text" id="email_from_name" name="email_from_name" value="<?php echo esc_attr( $from_name ); ?>" class="regular-text">
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="email_from"><?php esc_html_e( 'From Email', 'xtreme-forms' ); ?></label>
						</th>
						<td>
							<input type="email" id="email_from" name="email_from" value="<?php echo esc_attr( $from_email ); ?>" class="regular-text">
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- ── Bot Protection ───────────────────────────────────────────────── -->
		<div class="xf-settings-card">
			<h2>
				<span class="dashicons dashicons-shield-alt" style="font-size:18px;vertical-align:middle;margin-right:8px;color:var(--xf-teal);"></span>
				<?php esc_html_e( 'Bot Protection', 'xtreme-forms' ); ?>
			</h2>

			<!-- reCAPTCHA v3 -->
			<h3 class="xf-settings-subsection-title"><?php esc_html_e( 'Google reCAPTCHA v3', 'xtreme-forms' ); ?></h3>
			<p class="description" style="margin-bottom:12px;">
				<?php esc_html_e( 'Score-based verification, invisible to users. Must also be enabled per-form in the form builder.', 'xtreme-forms' ); ?>
			</p>
			<?php if ( 'missing' === $rc_inline_warn ) : ?>
				<div class="notice notice-warning inline" style="margin-bottom:12px;"><p><?php esc_html_e( 'reCAPTCHA is enabled but one or both keys are missing.', 'xtreme-forms' ); ?></p></div>
			<?php elseif ( 'invalid' === $rc_inline_warn ) : ?>
				<div class="notice notice-warning inline" style="margin-bottom:12px;"><p><?php esc_html_e( 'One or both reCAPTCHA keys appear invalid. Please verify at the Google reCAPTCHA console.', 'xtreme-forms' ); ?></p></div>
			<?php endif; ?>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable reCAPTCHA v3', 'xtreme-forms' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="recaptcha_enabled" value="1" <?php checked( $rc_enabled ); ?>>
								<?php esc_html_e( 'Enable globally', 'xtreme-forms' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="recaptcha_site_key"><?php esc_html_e( 'Site Key', 'xtreme-forms' ); ?></label></th>
						<td>
							<input type="text" id="recaptcha_site_key" name="recaptcha_site_key" value="<?php echo esc_attr( $rc_site_key ); ?>" class="regular-text" placeholder="6Lc...">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="recaptcha_secret_key"><?php esc_html_e( 'Secret Key', 'xtreme-forms' ); ?></label></th>
						<td>
							<input type="password" id="recaptcha_secret_key" name="recaptcha_secret_key" value="<?php echo esc_attr( $rc_secret_key ); ?>" class="regular-text" autocomplete="new-password" placeholder="6Lc...">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="recaptcha_threshold"><?php esc_html_e( 'Score Threshold', 'xtreme-forms' ); ?></label></th>
						<td>
							<input type="number" id="recaptcha_threshold" name="recaptcha_threshold" value="<?php echo esc_attr( $rc_threshold ); ?>" min="0.1" max="0.9" step="0.1" class="small-text">
							<p class="description"><?php esc_html_e( 'Scores below this are rejected. Default: 0.5. Range: 0.1–0.9.', 'xtreme-forms' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="xf-settings-divider"></div>

			<!-- Cloudflare Turnstile -->
			<?php if ( $ts_active ) : ?>
				<div class="xf-turnstile-active-banner">
					<svg xmlns="http://www.w3.org/2000/svg" aria-label="Cloudflare" role="img" viewBox="0 0 512 512" width="32" height="32" style="flex-shrink:0;">
						<rect width="512" height="512" rx="15%" fill="#ffffff"/>
						<path fill="#f38020" d="M331 326c11-26-4-38-19-38l-148-2c-4 0-4-6 1-7l150-2c17-1 37-15 43-33 0 0 10-21 9-24a97 97 0 0 0-187-11c-38-25-78 9-69 46-48 3-65 46-60 72 0 1 1 2 3 2h274c1 0 3-1 3-3z"/>
						<path fill="#faae40" d="M381 224c-4 0-6-1-7 1l-5 21c-5 16 3 30 20 31l32 2c4 0 4 6-1 7l-33 1c-36 4-46 39-46 39 0 2 0 3 2 3h113l3-2a81 81 0 0 0-78-103"/>
					</svg>
					<div>
						<div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
							<svg width="14" height="14" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="8" fill="#16a34a"/><path d="M4.5 8.5L7 11L11.5 5.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
							<strong style="font-size:13px;color:#92400e;"><?php esc_html_e( 'Cloudflare Protected', 'xtreme-forms' ); ?></strong>
						</div>
						<p style="margin:0;font-size:12px;color:#78350f;"><?php esc_html_e( 'Turnstile is active — all forms are shielded from bots.', 'xtreme-forms' ); ?></p>
					</div>
				</div>
			<?php endif; ?>

			<h3 class="xf-settings-subsection-title"><?php esc_html_e( 'Cloudflare Turnstile', 'xtreme-forms' ); ?></h3>
			<p class="description" style="margin-bottom:12px;">
				<?php esc_html_e( 'Free, privacy-friendly CAPTCHA alternative. Requires no user interaction for most visitors and works on all forms automatically.', 'xtreme-forms' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Enable Turnstile', 'xtreme-forms' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="turnstile_enabled" value="1" <?php checked( $ts_enabled ); ?>>
								<?php esc_html_e( 'Enable for all forms globally', 'xtreme-forms' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="turnstile_site_key"><?php esc_html_e( 'Site Key', 'xtreme-forms' ); ?></label></th>
						<td>
							<input type="text" id="turnstile_site_key" name="turnstile_site_key" value="<?php echo esc_attr( $ts_site_key ); ?>" class="regular-text" placeholder="0x4AAAAAAA...">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="turnstile_secret_key"><?php esc_html_e( 'Secret Key', 'xtreme-forms' ); ?></label></th>
						<td>
							<input type="password" id="turnstile_secret_key" name="turnstile_secret_key" value="<?php echo esc_attr( $ts_secret_key ); ?>" class="regular-text" autocomplete="new-password" placeholder="0x4AAAAAAA...">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="turnstile_theme"><?php esc_html_e( 'Widget Theme', 'xtreme-forms' ); ?></label></th>
						<td>
							<select id="turnstile_theme" name="turnstile_theme">
								<option value="auto" <?php selected( $ts_theme, 'auto' ); ?>><?php esc_html_e( 'Auto', 'xtreme-forms' ); ?></option>
								<option value="light" <?php selected( $ts_theme, 'light' ); ?>><?php esc_html_e( 'Light', 'xtreme-forms' ); ?></option>
								<option value="dark" <?php selected( $ts_theme, 'dark' ); ?>><?php esc_html_e( 'Dark', 'xtreme-forms' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="turnstile_size"><?php esc_html_e( 'Widget Size', 'xtreme-forms' ); ?></label></th>
						<td>
							<select id="turnstile_size" name="turnstile_size">
								<option value="normal" <?php selected( $ts_size, 'normal' ); ?>><?php esc_html_e( 'Normal', 'xtreme-forms' ); ?></option>
								<option value="compact" <?php selected( $ts_size, 'compact' ); ?>><?php esc_html_e( 'Compact', 'xtreme-forms' ); ?></option>
								<option value="flexible" <?php selected( $ts_size, 'flexible' ); ?>><?php esc_html_e( 'Flexible (full width)', 'xtreme-forms' ); ?></option>
							</select>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- ── Spam Blocklists ───────────────────────────────────────────────── -->
		<div class="xf-settings-card">
			<h2>
				<span class="dashicons dashicons-dismiss" style="font-size:18px;vertical-align:middle;margin-right:8px;color:var(--xf-teal);"></span>
				<?php esc_html_e( 'Spam Blocklists', 'xtreme-forms' ); ?>
			</h2>
			<p class="description" style="margin-bottom:16px;">
				<?php esc_html_e( 'Applied to all forms. Domain matching is exact. Keyword matching is case-insensitive against all text fields.', 'xtreme-forms' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="spam_domain_blocklist"><?php esc_html_e( 'Email Domain Blocklist', 'xtreme-forms' ); ?></label></th>
						<td>
							<textarea id="spam_domain_blocklist" name="spam_domain_blocklist" rows="5" class="large-text" placeholder="spam.com&#10;disposablemail.org"><?php echo esc_textarea( $domain_blocklist ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One domain per line.', 'xtreme-forms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="spam_keyword_blocklist"><?php esc_html_e( 'Keyword Blocklist', 'xtreme-forms' ); ?></label></th>
						<td>
							<textarea id="spam_keyword_blocklist" name="spam_keyword_blocklist" rows="5" class="large-text" placeholder="buy cheap&#10;casino"><?php echo esc_textarea( $keyword_blocklist ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One keyword per line.', 'xtreme-forms' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- ── Duplicate Lead Detection ─────────────────────────────────────── -->
		<div class="xf-settings-card">
			<h2>
				<span class="dashicons dashicons-controls-repeat" style="font-size:18px;vertical-align:middle;margin-right:8px;color:var(--xf-teal);"></span>
				<?php esc_html_e( 'Duplicate Lead Detection', 'xtreme-forms' ); ?>
			</h2>
			<p class="description" style="margin-bottom:16px;">
				<?php esc_html_e( 'When a submission arrives with an email address already in the database, choose how to handle it.', 'xtreme-forms' ); ?>
			</p>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row">
							<label for="duplicate_behavior"><?php esc_html_e( 'Duplicate Behavior', 'xtreme-forms' ); ?></label>
						</th>
						<td>
							<select id="duplicate_behavior" name="duplicate_behavior" class="regular-text" onchange="xlToggleDupMessage(this.value)">
								<?php foreach ( $behavior_options as $val => $label ) : ?>
									<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $dup_behavior, $val ); ?>>
										<?php echo esc_html( $label ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr id="xf-dup-block-message-row" style="<?php echo 'block' === $dup_behavior ? '' : 'display:none;'; ?>">
						<th scope="row">
							<label for="duplicate_block_message"><?php esc_html_e( 'Block Message', 'xtreme-forms' ); ?></label>
						</th>
						<td>
							<textarea id="duplicate_block_message" name="duplicate_block_message" rows="3" class="large-text"><?php echo esc_textarea( $dup_block_message ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Message shown when a duplicate submission is blocked. Leave blank for the default message.', 'xtreme-forms' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- ── Privacy & GDPR ───────────────────────────────────────────────── -->
		<div class="xf-settings-card">
			<h2>
				<span class="dashicons dashicons-privacy" style="font-size:18px;vertical-align:middle;margin-right:8px;color:var(--xf-teal);"></span>
				<?php esc_html_e( 'Privacy & GDPR', 'xtreme-forms' ); ?>
			</h2>
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'IP Anonymization', 'xtreme-forms' ); ?></th>
						<td>
							<label class="xf-checkbox-inline">
								<input type="checkbox" id="anonymize_ip" name="anonymize_ip" value="1" <?php checked( $anonymize ); ?>>
								<?php esc_html_e( 'Anonymize IP addresses stored with new leads', 'xtreme-forms' ); ?>
							</label>
							<p class="description"><?php esc_html_e( 'IPv4: last octet zeroed. IPv6: last 80 bits zeroed. Existing records are NOT retroactively changed.', 'xtreme-forms' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="retention_days"><?php esc_html_e( 'Auto-delete Leads', 'xtreme-forms' ); ?></label></th>
						<td>
							<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
								<span><?php esc_html_e( 'Delete leads older than', 'xtreme-forms' ); ?></span>
								<input type="number" id="retention_days" name="retention_days" value="<?php echo esc_attr( (string) $retention_days ); ?>" step="1" class="small-text" placeholder="—">
								<span><?php esc_html_e( 'days', 'xtreme-forms' ); ?></span>
							</div>
							<p class="description" style="margin-top:6px;">
								<?php esc_html_e( 'Leave blank to disable. Purge runs daily and permanently deletes leads plus all associated notes, tags, and email log entries.', 'xtreme-forms' ); ?>
							</p>
							<?php if ( $next_purge_time ) : ?>
								<p class="description">
									<?php printf( esc_html__( 'Next purge: %s', 'xtreme-forms' ), '<strong>' . esc_html( $next_purge_time ) . '</strong>' ); ?>
								</p>
							<?php elseif ( $retention_days ) : ?>
								<p class="description" style="color:#FFC107;"><?php esc_html_e( 'Purge cron not scheduled. Save settings to reschedule.', 'xtreme-forms' ); ?></p>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="xf-erase-email"><?php esc_html_e( 'Right to Erasure', 'xtreme-forms' ); ?></label></th>
						<td>
							<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
								<input type="email" id="xf-erase-email" class="regular-text" placeholder="person@example.com">
								<button type="button" id="xf-erase-btn" class="button button-link-delete">
									<?php esc_html_e( 'Erase All Data', 'xtreme-forms' ); ?>
								</button>
							</div>
							<p class="description"><?php esc_html_e( 'Permanently deletes all leads, notes, tags, and email log entries for the given address. Cannot be undone.', 'xtreme-forms' ); ?></p>
							<div id="xf-erase-result" style="margin-top:8px;display:none;" class="notice inline">
								<p id="xf-erase-result-msg"></p>
							</div>
						</td>
					</tr>
				</tbody>
			</table>
		</div>

		<!-- Single save button -->
		<div class="xf-settings-save-bar">
			<?php submit_button( __( 'Save Settings', 'xtreme-forms' ), 'primary xf-btn-primary', 'xf_save_all_settings', false ); ?>
		</div>

	</form>

	<script>
	function xlToggleDupMessage( val ) {
		var row = document.getElementById( 'xf-dup-block-message-row' );
		if ( row ) {
			row.style.display = ( 'block' === val ) ? '' : 'none';
		}
	}
	</script>

<?php elseif ( 'tags' === $xf_settings_tab ) : ?>
	<div class="xf-hub-tab-content">
		<?php require XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-tags.php'; ?>
	</div>
<?php elseif ( 'email-log' === $xf_settings_tab ) : ?>
	<div class="xf-hub-tab-content">
		<?php require XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-email-log.php'; ?>
	</div>
<?php elseif ( 'spam-log' === $xf_settings_tab ) : ?>
	<div class="xf-hub-tab-content">
		<?php require XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-spam-log.php'; ?>
	</div>
<?php elseif ( 'import-export' === $xf_settings_tab ) : ?>
	<div class="xf-hub-tab-content">
		<?php require XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-import-export.php'; ?>
	</div>
<?php elseif ( 'audit-log' === $xf_settings_tab ) : ?>
	<div class="xf-hub-tab-content">
		<?php require XTREMEFORMS_PLUGIN_DIR . 'admin/partials/xf-admin-audit-log.php'; ?>
	</div>
<?php endif; ?>

</div><!-- .xf-wrap -->
