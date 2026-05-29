<?php
/**
 * Form Builder admin page — drag-and-drop visual builder.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'You do not have permission to access this page.', 'xtreme-forms' ) );
}

// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Capability check enforced above; page callback is also registered with 'manage_options'. GET params on this page are read-only navigation params (form_id, template slug).

$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
$is_edit = $form_id > 0;
$form    = $is_edit ? Xtremeforms_Forms::get_form( $form_id ) : null;

if ( $is_edit && ! $form ) {
	wp_die( esc_html__( 'Form not found.', 'xtreme-forms' ) );
}

$fields   = $form ? Xtremeforms_Forms::decode_fields( $form ) : array();
$settings = $form ? Xtremeforms_Forms::decode_settings( $form ) : array();

// ── Template loading (only for new forms with a template slug in the URL) ──
$xf_template_slug = ( ! $is_edit && isset( $_GET['xtremeforms_template'] ) ) ? sanitize_key( $_GET['xtremeforms_template'] ) : '';

if ( $xf_template_slug && ! $is_edit ) {
	$tpl_data = xtremeforms_get_form_template( $xf_template_slug );
	if ( $tpl_data ) {
		$fields   = $tpl_data['fields'];
		$settings = array_merge( $settings, $tpl_data['settings'] );
	}
}
$xf_template_name = ( $xf_template_slug && ! $is_edit && isset( $tpl_data ) && $tpl_data ) ? $tpl_data['name'] : '';

// Retrieve any validation errors from previous save attempt.
$transient_key = 'xtremeforms_form_errors_' . get_current_user_id();
$save_errors   = get_transient( $transient_key );
delete_transient( $transient_key );

$notice_html = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice_html = '<div class="xf-notice xf-notice-success"><p>' . esc_html__( 'Form saved successfully.', 'xtreme-forms' ) . '</p></div>';
} elseif ( ! empty( $save_errors ) && is_array( $save_errors ) ) {
	$error_items = implode( '</li><li>', array_map( 'esc_html', $save_errors ) );
	$notice_html = '<div class="xf-notice xf-notice-error"><p><strong>' . esc_html__( 'Please fix the following errors:', 'xtreme-forms' ) . '</strong></p><ul><li>' . $error_items . '</li></ul></div>';
}

// Form settings values.
$center_form      = ! empty( $settings['center_form'] ) && '1' === (string) $settings['center_form'];
$submit_label     = $settings['submit_label'] ?? '';
$redirect_url     = $settings['redirect_url'] ?? '';
$thank_you_msg    = $settings['thank_you_message'] ?? '';
$email_recipients = $settings['email_recipients'] ?? '';
$email_subject    = $settings['email_subject'] ?? '';
$ar_enabled       = ! empty( $settings['auto_responder_enabled'] ) && '1' === (string) $settings['auto_responder_enabled'];
$ar_subject       = $settings['auto_responder_subject'] ?? '';
$ar_body          = $settings['auto_responder_body'] ?? '';
$ar_reply_to      = $settings['auto_responder_reply_to'] ?? '';
$consent_enabled  = ! empty( $settings['consent_enabled'] ) && '1' === (string) $settings['consent_enabled'];
$consent_label    = $settings['consent_label'] ?? '';
$consent_url      = $settings['consent_url'] ?? '';
$form_recaptcha   = ! empty( $settings['recaptcha_enabled'] ) && '1' === (string) $settings['recaptcha_enabled'];
$remove_background = ! empty( $settings['remove_background'] ) && '1' === (string) $settings['remove_background'];
$countdown_enabled  = ! empty( $settings['countdown_timer_enabled'] ) && '1' === (string) $settings['countdown_timer_enabled'];
$closed_message_val = $form ? ( $form->closed_message ?? '' ) : '';
$activate_at_val    = ( $form && ! empty( $form->activate_at ) && '0000-00-00 00:00:00' !== $form->activate_at )
	? str_replace( ' ', 'T', substr( $form->activate_at, 0, 16 ) )
	: '';
$expire_at_val      = ( $form && ! empty( $form->expire_at ) && '0000-00-00 00:00:00' !== $form->expire_at )
	? str_replace( ' ', 'T', substr( $form->expire_at, 0, 16 ) )
	: '';

$page_title    = $is_edit
	? /* translators: %s: form name */ sprintf( __( 'Edit Form: %s', 'xtreme-forms' ), esc_html( $form->name ) )
	: __( 'Add New Form', 'xtreme-forms' );
$shortcode_val = $is_edit ? '[xtreme_forms id="' . esc_attr( $form_id ) . '"]' : '';

$global_settings_fb = get_option( 'xtremeforms_settings', array() );
$recaptcha_missing  = empty( $global_settings_fb['recaptcha_site_key'] ) || empty( $global_settings_fb['recaptcha_secret_key'] );

// Encode fields for the JS builder.
$fields_json = wp_json_encode( $fields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT );

// Form name: prefer actual form name, then template name.
$form_name_val = $form ? $form->name : $xf_template_name;
?>
<div class="wrap xf-wrap xf-form-builder-wrap">

	<!-- ── Page Header ──────────────────────────────────────────────────────── -->
	<div class="xf-page-header">
		<div class="xf-flex-center xf-gap-8">
			<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtreme-forms-forms' ), admin_url( 'admin.php' ) ) ); ?>" class="xf-back-link">
				<span class="dashicons dashicons-arrow-left-alt2"></span>
				<?php esc_html_e( 'Forms', 'xtreme-forms' ); ?>
			</a>
			<span class="xf-breadcrumb-sep">/</span>
			<h1 class="xf-page-title"><?php echo esc_html( $page_title ); ?></h1>
		</div>
		<?php if ( $shortcode_val ) : ?>
			<div class="xf-header-actions">
				<div class="xf-shortcode-copy-wrap" title="<?php esc_attr_e( 'Click to copy shortcode', 'xtreme-forms' ); ?>">
					<span class="dashicons dashicons-shortcode" style="color:var(--xf-teal);font-size:14px;width:14px;height:14px;"></span>
					<code id="xf-shortcode-display"><?php echo esc_html( $shortcode_val ); ?></code>
					<button type="button" id="xf-copy-shortcode" class="xf-btn xf-btn-ghost xf-btn-xs" aria-label="<?php esc_attr_e( 'Copy shortcode', 'xtreme-forms' ); ?>">
						<span class="dashicons dashicons-admin-page"></span>
					</button>
				</div>
			</div>
		<?php endif; ?>
	</div>

	<?php echo wp_kses_post( $notice_html ); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="xf-form-builder" novalidate>
		<input type="hidden" name="action" value="xtremeforms_save_form">
		<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
		<?php wp_nonce_field( 'xtremeforms_save_form' ); ?>

		<!-- Hidden field that JS builder syncs to on every change and before submit -->
		<input type="hidden" name="xtremeforms_fields" id="xf-fields-json" value="<?php echo esc_attr( $fields_json ); ?>">

		<!-- Submit button layout settings (synced by JS) -->
		<input type="hidden" name="submit_float" id="xf-submit-float" value="<?php echo esc_attr( $settings['submit_float'] ?? '0' ); ?>">
		<input type="hidden" name="submit_width" id="xf-submit-width" value="<?php echo esc_attr( $settings['submit_width'] ?? '100' ); ?>">
		<input type="hidden" name="submit_align" id="xf-submit-align" value="<?php echo esc_attr( $settings['submit_align'] ?? 'left' ); ?>">
		<input type="hidden" name="submit_bg_color" id="xf-submit-bg-color" value="<?php echo esc_attr( $settings['submit_bg_color'] ?? '#1A73E8' ); ?>">
		<input type="hidden" name="submit_text_color" id="xf-submit-text-color" value="<?php echo esc_attr( $settings['submit_text_color'] ?? '#ffffff' ); ?>">
		<input type="hidden" name="submit_btn_size" id="xf-submit-btn-size" value="<?php echo esc_attr( $settings['submit_btn_size'] ?? 'md' ); ?>">
		<input type="hidden" name="submit_full_width" id="xf-submit-full-width" value="<?php echo esc_attr( $settings['submit_full_width'] ?? '0' ); ?>">

		<!-- ── Title bar ──────────────────────────────────────────────────────── -->
		<div class="xfb-title-bar">
			<input
				type="text"
				name="form_name"
				id="xfb-form-name"
				class="xfb-title-input"
				value="<?php echo esc_attr( $form_name_val ); ?>"
				placeholder="<?php esc_attr_e( 'Form Name…', 'xtreme-forms' ); ?>"
				required
			>
			<button type="submit" class="button button-primary xfb-save-btn">
				<span class="dashicons dashicons-saved" style="vertical-align:middle;margin-top:-2px;margin-right:4px;"></span>
				<?php esc_html_e( 'Save Form', 'xtreme-forms' ); ?>
			</button>
		</div>

		<!-- ── 3-column builder ───────────────────────────────────────────────── -->
		<div class="xf-builder-wrap">

			<!-- Left: Field palette -->
			<div class="xfb-palette">
				<div class="xfb-palette-title"><?php esc_html_e( 'Add Fields', 'xtreme-forms' ); ?></div>
				<div class="xfb-palette-search-wrap">
					<input type="search" id="xfb-palette-search" class="xfb-palette-search"
						placeholder="<?php esc_attr_e( 'Search fields…', 'xtreme-forms' ); ?>"
						autocomplete="off">
					<span class="xfb-palette-search-icon" aria-hidden="true">
						<svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
					</span>
				</div>
				<div class="xfb-palette-grid" id="xfb-palette">
					<!-- Populated by xf-builder.js -->
				</div>
				<div class="xfb-palette-empty" id="xfb-palette-empty" hidden>
					<?php esc_html_e( 'No matching fields.', 'xtreme-forms' ); ?>
				</div>
			</div>

			<!-- Center: Canvas -->
			<div class="xfb-main">
				<div class="xfb-page-tabs" id="xfb-page-tabs">
					<!-- Populated by xf-builder.js -->
				</div>
				<div class="xfb-canvas" id="xfb-canvas">
					<div class="xfb-canvas-inner" id="xfb-canvas-inner">
						<!-- Fields rendered by xf-builder.js -->
					</div>
					<div class="xfb-empty-hint" id="xfb-empty-hint">
						<div class="xfb-empty-icon">+</div>
						<p><?php esc_html_e( 'Drag fields from the left panel, or click a field type to add it.', 'xtreme-forms' ); ?></p>
					</div>
				</div>
			</div>

			<!-- Right: Field settings panel (shown when a field is selected) -->
			<div class="xfb-settings-panel hidden" id="xfb-settings-panel">
				<div id="xfb-settings-body">
					<div class="xfb-sp-empty">
						<span class="xfb-sp-empty-icon">&#x2B55;</span>
						Click a field to edit its settings.
					</div>
				</div>
			</div>

		</div><!-- .xf-builder-wrap -->

		<!-- ── Advanced form settings (collapsible) ───────────────────────────── -->
		<div class="xfb-advanced-settings" id="xfb-advanced-settings">
			<button type="button" class="xfb-advanced-toggle" id="xfb-advanced-toggle">
				<span class="dashicons dashicons-admin-settings" style="font-size:14px;width:14px;height:14px;vertical-align:middle;margin-right:5px;"></span>
				<?php esc_html_e( 'Form Settings', 'xtreme-forms' ); ?>
				<span class="xfb-advanced-arrow" id="xfb-advanced-arrow">&#x25BC;</span>
			</button>
			<div class="xfb-advanced-body" id="xfb-advanced-body" style="display:none;">
				<div class="xf-builder-layout">

					<!-- Tabbed settings sidebar -->
					<aside class="xf-builder-sidebar">

						<!-- Tab nav -->
						<div class="xf-builder-tabs" role="tablist">
							<button type="button" class="xf-btab xf-btab-active" data-tab="general"  role="tab" aria-selected="true"><?php esc_html_e( 'General', 'xtreme-forms' ); ?></button>
							<button type="button" class="xf-btab" data-tab="notify"   role="tab"><?php esc_html_e( 'Email', 'xtreme-forms' ); ?></button>
							<button type="button" class="xf-btab" data-tab="gdpr"     role="tab"><?php esc_html_e( 'GDPR', 'xtreme-forms' ); ?></button>
							<button type="button" class="xf-btab" data-tab="schedule" role="tab"><?php esc_html_e( 'Schedule', 'xtreme-forms' ); ?></button>
							<button type="button" class="xf-btab" data-tab="spam"     role="tab"><?php esc_html_e( 'Spam', 'xtreme-forms' ); ?></button>
							<button type="button" class="xf-btab" data-tab="styling"  role="tab"><?php esc_html_e( 'Styling', 'xtreme-forms' ); ?></button>
						</div>

						<!-- TAB: General -->
						<div class="xf-btab-panel" id="xf-tab-general">

							<?php /* Submit button label is configured in the right-sidebar field settings; preserve the existing value across saves. */ ?>
							<input type="hidden" name="submit_label" value="<?php echo esc_attr( $submit_label ); ?>">

							<div class="xf-form-row">
								<label class="xf-toggle-row-label">
									<label class="xfb-toggle xf-settings-toggle">
										<input type="checkbox" name="center_form" value="1"<?php checked( $center_form ); ?>>
										<span class="xfb-toggle-track"></span>
										<span class="xfb-toggle-thumb"></span>
									</label>
									<span style="font-weight:600;font-size:13px;"><?php esc_html_e( 'Center form on page', 'xtreme-forms' ); ?></span>
								</label>
								<p class="xf-input-hint" style="margin-top:4px;"><?php esc_html_e( 'Constrains the form to a max width and centers it within the content area.', 'xtreme-forms' ); ?></p>
							</div>

							<hr class="xf-divider">
							<p class="xf-section-heading"><?php esc_html_e( 'After Submission', 'xtreme-forms' ); ?></p>

							<div class="xf-form-row">
								<label for="redirect_url" class="xf-label"><?php esc_html_e( 'Redirect URL', 'xtreme-forms' ); ?></label>
								<input type="url" id="redirect_url" name="redirect_url"
									value="<?php echo esc_attr( $redirect_url ); ?>"
									class="xf-input"
									placeholder="https://">
								<p class="xf-input-hint"><?php esc_html_e( 'Leave blank to show the thank-you message below.', 'xtreme-forms' ); ?></p>
							</div>

							<div class="xf-form-row">
								<label for="thank_you_message" class="xf-label"><?php esc_html_e( 'Thank-You Message', 'xtreme-forms' ); ?></label>
								<textarea id="thank_you_message" name="thank_you_message"
									class="xf-textarea" rows="3"
									placeholder="<?php esc_attr_e( 'Thank you! Your submission has been received.', 'xtreme-forms' ); ?>"><?php echo esc_textarea( $thank_you_msg ); ?></textarea>
							</div>

							<hr class="xf-divider">

							<div class="xf-form-row">
								<label for="email_recipients" class="xf-label"><?php esc_html_e( 'Override Notification Recipients', 'xtreme-forms' ); ?></label>
								<input type="text" id="email_recipients" name="email_recipients"
									value="<?php echo esc_attr( $email_recipients ); ?>"
									class="xf-input"
									placeholder="<?php esc_attr_e( 'email@example.com, another@example.com', 'xtreme-forms' ); ?>">
								<p class="xf-input-hint"><?php esc_html_e( 'Comma-separated. Leave blank to use the global recipients from Settings.', 'xtreme-forms' ); ?></p>
							</div>

						</div><!-- #xf-tab-general -->

						<!-- TAB: Email / Notifications -->
						<div class="xf-btab-panel" id="xf-tab-notify" style="display:none;">

							<p class="xf-section-heading"><?php esc_html_e( 'Admin Notification', 'xtreme-forms' ); ?></p>

							<div class="xf-form-row">
								<label for="email_subject" class="xf-label"><?php esc_html_e( 'Custom Subject Line', 'xtreme-forms' ); ?></label>
								<input type="text" id="email_subject" name="email_subject"
									value="<?php echo esc_attr( $email_subject ); ?>"
									class="xf-input"
									placeholder="<?php esc_attr_e( 'Leave blank to use the default global subject', 'xtreme-forms' ); ?>">
								<p class="xf-input-hint">
									<?php esc_html_e( 'Override the default new-lead notification subject for this form. Merge tags supported:', 'xtreme-forms' ); ?>
									<code>{{lead_name}}</code>, <code>{{lead_email}}</code>, <code>{{form_name}}</code>, <code>{{site_name}}</code>, <code>{{lead_id}}</code>
								</p>
							</div>

							<hr class="xf-divider">
							<p class="xf-section-heading"><?php esc_html_e( 'Auto-Responder', 'xtreme-forms' ); ?></p>

							<div class="xf-form-row">
								<label class="xf-toggle-wrap">
									<span class="xf-toggle">
										<input type="checkbox" id="auto_responder_enabled" name="auto_responder_enabled" value="1" <?php checked( $ar_enabled ); ?>>
										<span class="xf-toggle-track"></span>
										<span class="xf-toggle-thumb"></span>
									</span>
									<span class="xf-toggle-label xf-fw-600"><?php esc_html_e( 'Enable Auto-Responder Email', 'xtreme-forms' ); ?></span>
								</label>
								<p class="xf-input-hint"><?php esc_html_e( 'Automatically send a confirmation email to the submitter.', 'xtreme-forms' ); ?></p>
							</div>

							<div id="xf-auto-responder-fields" <?php echo $ar_enabled ? '' : 'style="display:none;"'; ?>>
								<div class="xf-form-row">
									<label for="auto_responder_subject" class="xf-label"><?php esc_html_e( 'Subject Line', 'xtreme-forms' ); ?></label>
									<input type="text" id="auto_responder_subject" name="auto_responder_subject"
										value="<?php echo esc_attr( $ar_subject ); ?>"
										class="xf-input"
										placeholder="<?php esc_attr_e( 'Thank you for contacting us', 'xtreme-forms' ); ?>">
									<p class="xf-input-hint"><?php esc_html_e( 'Merge tags: {{lead_name}}, {{form_name}}, {{site_name}}', 'xtreme-forms' ); ?></p>
								</div>

								<div class="xf-form-row">
									<label for="auto_responder_body" class="xf-label"><?php esc_html_e( 'Message Body', 'xtreme-forms' ); ?></label>
									<textarea id="auto_responder_body" name="auto_responder_body"
										class="xf-textarea" rows="5"
										placeholder="<?php esc_attr_e( 'Thank you for your submission. We will be in touch soon.', 'xtreme-forms' ); ?>"><?php echo esc_textarea( $ar_body ); ?></textarea>
									<p class="xf-input-hint"><?php esc_html_e( 'Merge tags are supported. Plain text only.', 'xtreme-forms' ); ?></p>
								</div>

								<div class="xf-form-row">
									<label for="auto_responder_reply_to" class="xf-label"><?php esc_html_e( 'Reply-To Address', 'xtreme-forms' ); ?></label>
									<input type="email" id="auto_responder_reply_to" name="auto_responder_reply_to"
										value="<?php echo esc_attr( $ar_reply_to ); ?>"
										class="xf-input"
										placeholder="reply@yourcompany.com">
									<p class="xf-input-hint"><?php esc_html_e( 'Where replies to the confirmation email are directed.', 'xtreme-forms' ); ?></p>
									<p id="xf-reply-to-error" style="display:none;color:var(--xf-danger);font-size:12px;margin-top:4px;"></p>
								</div>
							</div>

						</div><!-- #xf-tab-notify -->

						<!-- TAB: GDPR -->
						<div class="xf-btab-panel" id="xf-tab-gdpr" style="display:none;">

							<div class="xf-form-row">
								<label class="xf-toggle-wrap">
									<span class="xf-toggle">
										<input type="checkbox" id="consent_enabled" name="consent_enabled" value="1" <?php checked( $consent_enabled ); ?>>
										<span class="xf-toggle-track"></span>
										<span class="xf-toggle-thumb"></span>
									</span>
									<span class="xf-toggle-label xf-fw-600"><?php esc_html_e( 'Show Consent Checkbox', 'xtreme-forms' ); ?></span>
								</label>
								<p class="xf-input-hint"><?php esc_html_e( 'Adds a required GDPR consent checkbox to this form.', 'xtreme-forms' ); ?></p>
							</div>

							<div id="xf-consent-fields" <?php echo $consent_enabled ? '' : 'style="display:none;"'; ?>>
								<div class="xf-form-row">
									<label for="consent_label" class="xf-label"><?php esc_html_e( 'Consent Label Text', 'xtreme-forms' ); ?></label>
									<textarea id="consent_label" name="consent_label"
										class="xf-textarea" rows="2"
										placeholder="<?php esc_attr_e( 'I agree to the Privacy Policy', 'xtreme-forms' ); ?>"><?php echo esc_textarea( $consent_label ); ?></textarea>
								</div>
								<div class="xf-form-row">
									<label for="consent_url" class="xf-label"><?php esc_html_e( 'Privacy Policy URL', 'xtreme-forms' ); ?></label>
									<input type="url" id="consent_url" name="consent_url"
										value="<?php echo esc_attr( $consent_url ); ?>"
										class="xf-input"
										placeholder="https://example.com/privacy-policy">
									<p class="xf-input-hint"><?php esc_html_e( 'Optional. Makes the label text link to your privacy policy.', 'xtreme-forms' ); ?></p>
								</div>
							</div>

						</div><!-- #xf-tab-gdpr -->

						<!-- TAB: Schedule -->
						<div class="xf-btab-panel" id="xf-tab-schedule" style="display:none;">

							<div class="xf-form-row">
								<label for="activate_at" class="xf-label">
									<span class="dashicons dashicons-calendar-alt" style="font-size:13px;width:13px;height:13px;vertical-align:middle;margin-right:3px;color:var(--xf-teal);"></span>
									<?php esc_html_e( 'Activation Date &amp; Time', 'xtreme-forms' ); ?>
								</label>
								<input type="datetime-local" id="activate_at" name="activate_at"
									value="<?php echo esc_attr( $activate_at_val ); ?>"
									class="xf-input xf-input-datetime">
								<p class="xf-input-hint"><?php esc_html_e( 'Leave blank to publish immediately. Uses site timezone.', 'xtreme-forms' ); ?></p>
							</div>

							<div class="xf-form-row">
								<label for="expire_at" class="xf-label">
									<span class="dashicons dashicons-calendar-alt" style="font-size:13px;width:13px;height:13px;vertical-align:middle;margin-right:3px;color:var(--xf-danger);"></span>
									<?php esc_html_e( 'Expiration Date &amp; Time', 'xtreme-forms' ); ?>
								</label>
								<input type="datetime-local" id="expire_at" name="expire_at"
									value="<?php echo esc_attr( $expire_at_val ); ?>"
									class="xf-input xf-input-datetime">
								<p class="xf-input-hint"><?php esc_html_e( 'Leave blank for no expiry. Uses site timezone.', 'xtreme-forms' ); ?></p>
							</div>

							<hr class="xf-divider">

							<div class="xf-form-row">
								<label for="closed_message" class="xf-label"><?php esc_html_e( 'Unavailable Message', 'xtreme-forms' ); ?></label>
								<textarea id="closed_message" name="closed_message"
									class="xf-textarea" rows="2"
									placeholder="<?php esc_attr_e( 'This form is currently unavailable.', 'xtreme-forms' ); ?>"><?php echo esc_textarea( $closed_message_val ); ?></textarea>
								<p class="xf-input-hint"><?php esc_html_e( 'Shown when the form is outside its active window.', 'xtreme-forms' ); ?></p>
							</div>

							<div class="xf-form-row">
								<label class="xf-toggle-wrap">
									<span class="xf-toggle">
										<input type="checkbox" id="countdown_timer_enabled" name="countdown_timer_enabled" value="1" <?php checked( $countdown_enabled ); ?>>
										<span class="xf-toggle-track"></span>
										<span class="xf-toggle-thumb"></span>
									</span>
									<span class="xf-toggle-label"><?php esc_html_e( 'Show Countdown Timer', 'xtreme-forms' ); ?></span>
								</label>
								<p class="xf-input-hint"><?php esc_html_e( 'Displays a live countdown above the unavailable message until the activation date.', 'xtreme-forms' ); ?></p>
							</div>

						</div><!-- #xf-tab-schedule -->

						<!-- TAB: Spam -->
						<div class="xf-btab-panel" id="xf-tab-spam" style="display:none;">

							<div class="xf-form-row">
								<label class="xf-toggle-wrap">
									<span class="xf-toggle">
										<input type="checkbox" id="form_recaptcha_enabled" name="form_recaptcha_enabled" value="1" <?php checked( $form_recaptcha ); ?>>
										<span class="xf-toggle-track"></span>
										<span class="xf-toggle-thumb"></span>
									</span>
									<span class="xf-toggle-label xf-fw-600"><?php esc_html_e( 'Enable reCAPTCHA v3', 'xtreme-forms' ); ?></span>
								</label>
								<?php if ( $recaptcha_missing ) : ?>
									<div class="xf-notice xf-notice-warning" style="margin-top:10px;">
										<p><?php esc_html_e( 'reCAPTCHA keys are not configured. Go to Settings → Spam Protection to add them.', 'xtreme-forms' ); ?></p>
									</div>
								<?php else : ?>
									<p class="xf-input-hint"><?php esc_html_e( 'Adds invisible bot protection to this form without affecting the user experience.', 'xtreme-forms' ); ?></p>
								<?php endif; ?>
							</div>

							<hr class="xf-divider">

							<div class="xf-notice xf-notice-info">
								<p><?php esc_html_e( 'Honeypot and time-gate spam protection are always active on all forms. Configure keyword blocklists in Settings → Spam Protection.', 'xtreme-forms' ); ?></p>
							</div>

						</div><!-- #xf-tab-spam -->

						<!-- TAB: Styling -->
						<div class="xf-btab-panel" id="xf-tab-styling" style="display:none;">

							<div class="xf-form-row">
								<label class="xf-toggle-row-label">
									<label class="xfb-toggle xf-settings-toggle">
										<input type="checkbox" name="remove_background" value="1"<?php checked( $remove_background ); ?>>
										<span class="xfb-toggle-track"></span>
										<span class="xfb-toggle-thumb"></span>
									</label>
									<span style="font-weight:600;font-size:13px;"><?php esc_html_e( 'Remove background', 'xtreme-forms' ); ?></span>
								</label>
								<p class="xf-input-hint" style="margin-top:4px;"><?php esc_html_e( 'Makes the form background transparent and hides the form border/shadow.', 'xtreme-forms' ); ?></p>
							</div>

						</div><!-- #xf-tab-styling -->

					</aside><!-- .xf-builder-sidebar -->

				</div><!-- .xf-builder-layout (inner) -->
			</div><!-- .xfb-advanced-body -->
		</div><!-- .xfb-advanced-settings -->

	</form>
</div><!-- .xf-form-builder-wrap -->

<?php
ob_start();
?>
var xtremeformsBuilderData = {
	fields: <?php echo wp_json_encode( $fields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT ); ?>,
};
<?php
$xtremeforms_inline_js1 = ob_get_clean();
wp_add_inline_script( 'xtremeforms-builder', $xtremeforms_inline_js1, 'before' );
ob_start();
?>
(function () {
	'use strict';

	// ── Advanced settings toggle ───────────────────────────────────────────────
	var advToggle = document.getElementById('xfb-advanced-toggle');
	var advBody   = document.getElementById('xfb-advanced-body');
	var advArrow  = document.getElementById('xfb-advanced-arrow');
	if (advToggle && advBody) {
		advToggle.addEventListener('click', function () {
			var open = advBody.style.display !== 'none';
			advBody.style.display = open ? 'none' : '';
			if (advArrow) advArrow.innerHTML = open ? '&#x25BC;' : '&#x25B2;';
		});
	}

	// ── Builder Tab Switcher ───────────────────────────────────────────────────
	document.querySelectorAll('.xf-btab').forEach(function (btn) {
		btn.addEventListener('click', function () {
			var tab = btn.dataset.tab;
			document.querySelectorAll('.xf-btab').forEach(function (b) {
				b.classList.remove('xf-btab-active');
				b.setAttribute('aria-selected', 'false');
			});
			document.querySelectorAll('.xf-btab-panel').forEach(function (p) {
				p.style.display = 'none';
			});
			btn.classList.add('xf-btab-active');
			btn.setAttribute('aria-selected', 'true');
			var panel = document.getElementById('xf-tab-' + tab);
			if (panel) panel.style.display = '';
		});
	});

	// ── Auto-Responder toggle ──────────────────────────────────────────────────
	var arCheckbox = document.getElementById('auto_responder_enabled');
	var arFields   = document.getElementById('xf-auto-responder-fields');
	if (arCheckbox && arFields) {
		arCheckbox.addEventListener('change', function () {
			arFields.style.display = arCheckbox.checked ? '' : 'none';
		});
	}

	// ── GDPR Consent toggle ────────────────────────────────────────────────────
	var consentCb     = document.getElementById('consent_enabled');
	var consentFields = document.getElementById('xf-consent-fields');
	if (consentCb && consentFields) {
		consentCb.addEventListener('change', function () {
			consentFields.style.display = consentCb.checked ? '' : 'none';
		});
	}

	// ── Reply-To validation ────────────────────────────────────────────────────
	var replyToInput = document.getElementById('auto_responder_reply_to');
	var replyToErr   = document.getElementById('xf-reply-to-error');
	var formEl       = document.getElementById('xf-form-builder');

	function validateReplyTo() {
		if (!replyToInput || !replyToErr) return true;
		var val = replyToInput.value.trim();
		if (val === '') { replyToErr.style.display = 'none'; return true; }
		var emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if (!emailRe.test(val)) {
			replyToErr.textContent = '<?php echo esc_js( __( 'Please enter a valid reply-to email address.', 'xtreme-forms' ) ); ?>';
			replyToErr.style.display = 'block';
			return false;
		}
		replyToErr.style.display = 'none';
		return true;
	}

	if (replyToInput) {
		replyToInput.addEventListener('blur', validateReplyTo);
		replyToInput.addEventListener('input', function () {
			if (replyToErr && replyToErr.style.display !== 'none') validateReplyTo();
		});
	}

	if (formEl) {
		formEl.addEventListener('submit', function (e) {
			if (!validateReplyTo()) { e.preventDefault(); replyToInput.focus(); }
		});
	}

	// ── Shortcode Copy ─────────────────────────────────────────────────────────
	var copyBtn = document.getElementById('xf-copy-shortcode');
	if (copyBtn) {
		copyBtn.addEventListener('click', function () {
			var code = document.getElementById('xf-shortcode-display');
			if (!code) return;
			navigator.clipboard.writeText(code.textContent.trim()).then(function () {
				copyBtn.innerHTML = '<span class="dashicons dashicons-yes"></span>';
				setTimeout(function () {
					copyBtn.innerHTML = '<span class="dashicons dashicons-admin-page"></span>';
				}, 1500);
			});
		});
	}

}());
<?php
$xtremeforms_inline_js2 = ob_get_clean();
wp_add_inline_script( 'xtremeforms-admin', $xtremeforms_inline_js2, 'after' );
