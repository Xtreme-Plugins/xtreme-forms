<?php
/**
 * Settings admin page.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
}

// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Capability check enforced above; page callback is also registered with 'manage_options'. GET params on this page are read-only tab routing / notice flags (all sanitized on read).

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
$next_purge_time = Xtremeforms_GDPR::get_next_purge_time();

// GDPR nonce for erasure AJAX.
$gdpr_nonce = wp_create_nonce( 'xtremeforms_gdpr_nonce' );

// Notices.
$notice_html = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Settings saved.', 'xtreme-forms' ) . '</p></div>';
}
if ( ! empty( $_GET['xtremeforms_site_toggled'] ) && is_multisite() ) {
	$disabled_now = '1' === sanitize_text_field( wp_unslash( $_GET['xtremeforms_site_disabled'] ?? '0' ) );
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

	<?php if ( $rc_enabled ) : ?>
	<div style="display:flex;align-items:flex-start;gap:12px;background:#fffbeb;border:1px solid #fcd34d;border-radius:8px;padding:14px 16px;margin-bottom:16px;">
		<svg width="18" height="18" viewBox="0 0 20 20" fill="none" style="flex-shrink:0;margin-top:1px;"><circle cx="10" cy="10" r="9" stroke="#f59e0b" stroke-width="1.5"/><path d="M10 6v5M10 13.5v.5" stroke="#f59e0b" stroke-width="1.75" stroke-linecap="round"/></svg>
		<p style="margin:0;font-size:13px;color:#78350f;line-height:1.55;">
			<strong><?php esc_html_e( 'Attention reCAPTCHA users:', 'xtreme-forms' ); ?></strong>
			<?php esc_html_e( ' Google attempts to make all reCAPTCHA users migrate to reCAPTCHA Enterprise, meaning Google charges you for API calls exceeding the free tier. Xtreme Forms supports ', 'xtreme-forms' ); ?>
			<a href="https://xtremeplugins.com/plugins/xtreme-forms#turnstile" target="_blank" rel="noopener noreferrer" style="color:#92400e;font-weight:600;"><?php esc_html_e( 'Cloudflare Turnstile', 'xtreme-forms' ); ?></a><?php esc_html_e( ', and we recommend it unless you have reasons to use reCAPTCHA.', 'xtreme-forms' ); ?>
		</p>
	</div>
	<?php endif; ?>

<?php if ( 'general' === $xf_settings_tab ) : ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="xf-settings-form">
		<input type="hidden" name="action" value="xtremeforms_save_settings">
		<?php wp_nonce_field( 'xtremeforms_save_settings' ); ?>

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

			<?php
			// Determine which tab to open by default: prefer whichever provider is active, else Cloudflare.
			$bot_default_tab = ( $rc_enabled && ! $ts_active ) ? 'recaptcha' : 'turnstile';
			?>

			<!-- Tab nav -->
			<div id="xf-bot-tabs" style="display:flex;gap:0;border-bottom:2px solid #e4e4e7;margin-bottom:20px;">
				<button type="button" data-tab="turnstile" class="xf-bot-tab <?php echo $bot_default_tab === 'turnstile' ? 'xf-bot-tab--active' : ''; ?>"
					style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px 10px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:600;color:<?php echo $bot_default_tab === 'turnstile' ? '#f38020' : '#52525b'; ?>;border-bottom:2px solid <?php echo $bot_default_tab === 'turnstile' ? '#f38020' : 'transparent'; ?>;margin-bottom:-2px;transition:color .15s,border-color .15s;">
					<!-- Cloudflare logo -->
					<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="18" height="18" style="flex-shrink:0;">
						<path fill="#f38020" d="M331 326c11-26-4-38-19-38l-148-2c-4 0-4-6 1-7l150-2c17-1 37-15 43-33 0 0 10-21 9-24a97 97 0 0 0-187-11c-38-25-78 9-69 46-48 3-65 46-60 72 0 1 1 2 3 2h274c1 0 3-1 3-3z"/>
						<path fill="#faae40" d="M381 224c-4 0-6-1-7 1l-5 21c-5 16 3 30 20 31l32 2c4 0 4 6-1 7l-33 1c-36 4-46 39-46 39 0 2 0 3 2 3h113l3-2a81 81 0 0 0-78-103"/>
					</svg>
					<?php esc_html_e( 'Cloudflare Turnstile', 'xtreme-forms' ); ?>
					<?php if ( $ts_active ) : ?>
						<span style="display:inline-flex;align-items:center;justify-content:center;width:7px;height:7px;background:#16a34a;border-radius:50%;flex-shrink:0;" title="<?php esc_attr_e( 'Active', 'xtreme-forms' ); ?>"></span>
					<?php endif; ?>
				</button>
				<button type="button" data-tab="recaptcha" class="xf-bot-tab <?php echo $bot_default_tab === 'recaptcha' ? 'xf-bot-tab--active' : ''; ?>"
					style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px 10px;border:none;background:none;cursor:pointer;font-size:13px;font-weight:600;color:<?php echo $bot_default_tab === 'recaptcha' ? '#4285F4' : '#52525b'; ?>;border-bottom:2px solid <?php echo $bot_default_tab === 'recaptcha' ? '#4285F4' : 'transparent'; ?>;margin-bottom:-2px;transition:color .15s,border-color .15s;">
					<!-- Google G logo -->
					<svg viewBox="0 0 24 24" width="16" height="16" style="flex-shrink:0;"><path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/><path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/><path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/><path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/></svg>
					<?php esc_html_e( 'Google reCAPTCHA v3', 'xtreme-forms' ); ?>
					<?php if ( $rc_enabled ) : ?>
						<span style="display:inline-flex;align-items:center;justify-content:center;width:7px;height:7px;background:#16a34a;border-radius:50%;flex-shrink:0;" title="<?php esc_attr_e( 'Active', 'xtreme-forms' ); ?>"></span>
					<?php endif; ?>
				</button>
			</div>

			<!-- Tab: Cloudflare Turnstile -->
			<div id="xf-bot-panel-turnstile" class="xf-bot-panel" style="display:<?php echo $bot_default_tab === 'turnstile' ? 'block' : 'none'; ?>;">
				<?php if ( $ts_active ) : ?>
					<div class="xf-turnstile-active-banner" style="margin-bottom:16px;">
						<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512" width="28" height="28" style="flex-shrink:0;">
							<path fill="#f38020" d="M331 326c11-26-4-38-19-38l-148-2c-4 0-4-6 1-7l150-2c17-1 37-15 43-33 0 0 10-21 9-24a97 97 0 0 0-187-11c-38-25-78 9-69 46-48 3-65 46-60 72 0 1 1 2 3 2h274c1 0 3-1 3-3z"/>
							<path fill="#faae40" d="M381 224c-4 0-6-1-7 1l-5 21c-5 16 3 30 20 31l32 2c4 0 4 6-1 7l-33 1c-36 4-46 39-46 39 0 2 0 3 2 3h113l3-2a81 81 0 0 0-78-103"/>
						</svg>
						<div>
							<div style="display:flex;align-items:center;gap:6px;margin-bottom:2px;">
								<svg width="13" height="13" viewBox="0 0 16 16" fill="none"><circle cx="8" cy="8" r="8" fill="#16a34a"/><path d="M4.5 8.5L7 11L11.5 5.5" stroke="white" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
								<strong style="font-size:13px;color:#92400e;"><?php esc_html_e( 'Cloudflare Protected', 'xtreme-forms' ); ?></strong>
							</div>
							<p style="margin:0;font-size:12px;color:#78350f;"><?php esc_html_e( 'Turnstile is active — all forms are shielded from bots.', 'xtreme-forms' ); ?></p>
						</div>
					</div>
				<?php endif; ?>
				<p class="description" style="margin-bottom:4px;">
					<?php esc_html_e( "Turnstile is Cloudflare's smart CAPTCHA alternative, which confirms web visitors are real and blocks unwanted bots without slowing down web experiences for real users.", 'xtreme-forms' ); ?>
				</p>
				<p style="margin-bottom:16px;">
					<a href="https://xtremeplugins.com/plugins/xtreme-forms#turnstile" target="_blank" rel="noopener noreferrer" style="font-size:13px;"><?php esc_html_e( 'Cloudflare Turnstile integration', 'xtreme-forms' ); ?></a>
					<span style="color:#d1d5db;margin:0 6px;">·</span>
					<a href="https://www.cloudflare.com/application-services/products/turnstile/" target="_blank" rel="noopener noreferrer" style="font-size:13px;color:#9ca3af;">cloudflare.com
						<svg width="10" height="10" fill="none" viewBox="0 0 12 12" style="vertical-align:middle;margin-left:2px;"><path d="M2 10L10 2M10 2H4M10 2v6" stroke="#9ca3af" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/></svg>
					</a>
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
						<?php if ( $ts_site_key || $ts_secret_key ) : ?>
						<tr>
							<th scope="row"></th>
							<td>
								<button type="button" id="xf-remove-turnstile-keys" class="button button-secondary" style="color:#dc2626;border-color:#fca5a5;" onclick="
									if(confirm('<?php echo esc_js( __( 'Remove Turnstile keys and disable Turnstile?', 'xtreme-forms' ) ); ?>')) {
										document.getElementById('turnstile_site_key').value = '';
										document.getElementById('turnstile_secret_key').value = '';
										document.querySelector('[name=turnstile_enabled]').checked = false;
										document.querySelector('#xf-remove-turnstile-keys').closest('form').submit();
									}
								"><?php esc_html_e( 'Remove Keys', 'xtreme-forms' ); ?></button>
							</td>
						</tr>
						<?php endif; ?>
					</tbody>
				</table>
			</div>

			<!-- Tab: Google reCAPTCHA v3 -->
			<div id="xf-bot-panel-recaptcha" class="xf-bot-panel" style="display:<?php echo $bot_default_tab === 'recaptcha' ? 'block' : 'none'; ?>;">
				<p class="description" style="margin-bottom:16px;">
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
			</div>

			<?php
			ob_start();
			?>
			(function() {
				var tabs = document.querySelectorAll('#xf-bot-tabs .xf-bot-tab');
				tabs.forEach(function(btn) {
					btn.addEventListener('click', function() {
						var target = this.dataset.tab;
						// Update panels
						document.querySelectorAll('.xf-bot-panel').forEach(function(p) {
							p.style.display = 'none';
						});
						document.getElementById('xf-bot-panel-' + target).style.display = 'block';
						// Update tab styles
						tabs.forEach(function(t) {
							var isActive = t.dataset.tab === target;
							var color = t.dataset.tab === 'turnstile' ? '#f38020' : '#4285F4';
							t.style.color = isActive ? color : '#52525b';
							t.style.borderBottomColor = isActive ? color : 'transparent';
						});
					});
				});
			})();
			<?php
			$xtremeforms_inline_js1 = ob_get_clean();
			wp_add_inline_script( 'xtremeforms-admin', $xtremeforms_inline_js1, 'after' );
			?>
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
							<select id="duplicate_behavior" name="duplicate_behavior" class="regular-text" onchange="xtremeformsToggleDupMessage(this.value)">
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
									<?php
									// translators: %s: date and time of the next scheduled purge run.
									printf( esc_html__( 'Next purge: %s', 'xtreme-forms' ), '<strong>' . esc_html( $next_purge_time ) . '</strong>' );
									?>
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
			<?php submit_button( __( 'Save Settings', 'xtreme-forms' ), 'primary xf-btn-primary', 'xtremeforms_save_all_settings', false ); ?>
		</div>

	</form>

	<?php
	ob_start();
	?>
	function xtremeformsToggleDupMessage( val ) {
		var row = document.getElementById( 'xf-dup-block-message-row' );
		if ( row ) {
			row.style.display = ( 'block' === val ) ? '' : 'none';
		}
	}
	<?php
	$xtremeforms_inline_js2 = ob_get_clean();
	wp_add_inline_script( 'xtremeforms-admin', $xtremeforms_inline_js2, 'after' );
	?>

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
