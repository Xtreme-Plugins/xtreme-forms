<?php
/**
 * Form Builder admin page.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Filter parameters on this admin display page are read-only GET params — no nonce required for display-only filtering.

$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
$is_edit = $form_id > 0;
$form    = $is_edit ? XF_Forms::get_form( $form_id ) : null;

if ( $is_edit && ! $form ) {
	wp_die( esc_html__( 'Form not found.', 'xtreme-forms' ) );
}

$fields   = $form ? XF_Forms::decode_fields( $form ) : array();
$settings = $form ? XF_Forms::decode_settings( $form ) : array();

// Retrieve any validation errors from previous save attempt.
$transient_key = 'xf_form_errors_' . get_current_user_id();
$save_errors   = get_transient( $transient_key );
delete_transient( $transient_key );

$notice_html = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice_html = '<div class="xf-notice xf-notice-success"><p>' . esc_html__( 'Form saved successfully.', 'xtreme-forms' ) . '</p></div>';
} elseif ( ! empty( $save_errors ) && is_array( $save_errors ) ) {
	$error_items = implode( '</li><li>', array_map( 'esc_html', $save_errors ) );
	$notice_html = '<div class="xf-notice xf-notice-error"><p><strong>' . esc_html__( 'Please fix the following errors:', 'xtreme-forms' ) . '</strong></p><ul><li>' . $error_items . '</li></ul></div>';
}

$field_types = array(
	'text'     => array( 'label' => __( 'Text', 'xtreme-forms' ),         'icon' => 'dashicons-edit' ),
	'email'    => array( 'label' => __( 'Email', 'xtreme-forms' ),        'icon' => 'dashicons-email-alt' ),
	'phone'    => array( 'label' => __( 'Phone', 'xtreme-forms' ),        'icon' => 'dashicons-phone' ),
	'textarea' => array( 'label' => __( 'Textarea', 'xtreme-forms' ),     'icon' => 'dashicons-editor-paragraph' ),
	'dropdown' => array( 'label' => __( 'Dropdown', 'xtreme-forms' ),     'icon' => 'dashicons-menu' ),
	'checkbox' => array( 'label' => __( 'Checkbox', 'xtreme-forms' ),     'icon' => 'dashicons-yes-alt' ),
	'radio'    => array( 'label' => __( 'Radio', 'xtreme-forms' ),        'icon' => 'dashicons-marker' ),
	'hidden'   => array( 'label' => __( 'Hidden Field', 'xtreme-forms' ), 'icon' => 'dashicons-hidden' ),
	'date'     => array( 'label' => __( 'Date', 'xtreme-forms' ),         'icon' => 'dashicons-calendar-alt' ),
);

// Flatten labels for JS.
$field_type_labels = array();
foreach ( $field_types as $type => $def ) {
	$field_type_labels[ $type ] = $def['label'];
}

$fields_json      = wp_json_encode( $fields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT );
$submit_label     = $settings['submit_label'] ?? '';
$redirect_url     = $settings['redirect_url'] ?? '';
$thank_you_msg    = $settings['thank_you_message'] ?? '';
$email_recipients = $settings['email_recipients'] ?? '';
$ar_enabled       = ! empty( $settings['auto_responder_enabled'] ) && '1' === (string) $settings['auto_responder_enabled'];
$ar_subject       = $settings['auto_responder_subject'] ?? '';
$ar_body          = $settings['auto_responder_body'] ?? '';
$ar_reply_to      = $settings['auto_responder_reply_to'] ?? '';
$consent_enabled  = ! empty( $settings['consent_enabled'] ) && '1' === (string) $settings['consent_enabled'];
$consent_label    = $settings['consent_label'] ?? '';
$consent_url      = $settings['consent_url'] ?? '';
$form_recaptcha   = ! empty( $settings['recaptcha_enabled'] ) && '1' === (string) $settings['recaptcha_enabled'];
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
		<input type="hidden" name="action" value="xf_save_form">
		<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
		<?php wp_nonce_field( 'xf_save_form' ); ?>
		<input type="hidden" name="xf_fields" id="xf-fields-json" value="<?php echo esc_attr( $fields_json ); ?>">

		<div class="xf-builder-layout">

			<!-- ── Left: Tabbed Settings Panel ─────────────────────────────── -->
			<aside class="xf-builder-sidebar">

				<!-- Tab nav -->
				<div class="xf-builder-tabs" role="tablist">
					<button type="button" class="xf-btab xf-btab-active" data-tab="general"    role="tab" aria-selected="true"><?php esc_html_e( 'General', 'xtreme-forms' ); ?></button>
					<button type="button" class="xf-btab" data-tab="notify"     role="tab"><?php esc_html_e( 'Email', 'xtreme-forms' ); ?></button>
					<button type="button" class="xf-btab" data-tab="gdpr"       role="tab"><?php esc_html_e( 'GDPR', 'xtreme-forms' ); ?></button>
					<button type="button" class="xf-btab" data-tab="schedule"   role="tab"><?php esc_html_e( 'Schedule', 'xtreme-forms' ); ?></button>
					<button type="button" class="xf-btab" data-tab="spam"       role="tab"><?php esc_html_e( 'Spam', 'xtreme-forms' ); ?></button>
				</div>

				<!-- TAB: General -->
				<div class="xf-btab-panel" id="xf-tab-general">

					<div class="xf-form-row">
						<label for="form_name" class="xf-label">
							<?php esc_html_e( 'Form Name', 'xtreme-forms' ); ?>
							<span class="xf-required">*</span>
						</label>
						<input type="text" id="form_name" name="form_name"
							value="<?php echo esc_attr( $form ? $form->name : '' ); ?>"
							class="xf-input" required
							placeholder="<?php esc_attr_e( 'e.g. Contact Form', 'xtreme-forms' ); ?>">
					</div>

					<div class="xf-form-row">
						<label for="submit_label" class="xf-label"><?php esc_html_e( 'Submit Button Label', 'xtreme-forms' ); ?></label>
						<input type="text" id="submit_label" name="submit_label"
							value="<?php echo esc_attr( $submit_label ); ?>"
							class="xf-input"
							placeholder="<?php esc_attr_e( 'Submit', 'xtreme-forms' ); ?>">
					</div>

					<hr class="xf-divider">

					<!-- After Submit -->
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
							<?php esc_html_e( 'Activation Date & Time', 'xtreme-forms' ); ?>
						</label>
						<input type="datetime-local" id="activate_at" name="activate_at"
							value="<?php echo esc_attr( $activate_at_val ); ?>"
							class="xf-input xf-input-datetime">
						<p class="xf-input-hint"><?php esc_html_e( 'Leave blank to publish immediately. Uses site timezone.', 'xtreme-forms' ); ?></p>
					</div>

					<div class="xf-form-row">
						<label for="expire_at" class="xf-label">
							<span class="dashicons dashicons-calendar-alt" style="font-size:13px;width:13px;height:13px;vertical-align:middle;margin-right:3px;color:var(--xf-danger);"></span>
							<?php esc_html_e( 'Expiration Date & Time', 'xtreme-forms' ); ?>
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

			</aside><!-- .xf-builder-sidebar -->

			<!-- ── Right: Form Canvas ───────────────────────────────────────── -->
			<div class="xf-builder-canvas">

				<!-- Field Type Palette -->
				<div class="xf-palette-strip">
					<span class="xf-palette-label"><?php esc_html_e( 'Add Field', 'xtreme-forms' ); ?></span>
					<div class="xf-field-type-list">
						<?php foreach ( $field_types as $type => $def ) : ?>
							<button type="button"
								class="xf-add-field-btn"
								data-type="<?php echo esc_attr( $type ); ?>"
								title="<?php /* translators: %s: field type label */ echo esc_attr( sprintf( __( 'Add %s field', 'xtreme-forms' ), $def['label'] ) ); ?>">
								<span class="dashicons <?php echo esc_attr( $def['icon'] ); ?>"></span>
								<span><?php echo esc_html( $def['label'] ); ?></span>
							</button>
						<?php endforeach; ?>
					</div>
				</div>

				<!-- Canvas -->
				<div class="xf-canvas-card">
					<div class="xf-canvas-header">
						<h3><?php esc_html_e( 'Form Fields', 'xtreme-forms' ); ?></h3>
						<span class="xf-text-xs xf-text-muted"><?php esc_html_e( 'Drag to reorder · Click to configure', 'xtreme-forms' ); ?></span>
					</div>
					<div id="xf-fields-canvas" class="xf-fields-canvas"
						data-empty-label="<?php esc_attr_e( 'No fields yet. Use the Add Field bar above to get started.', 'xtreme-forms' ); ?>">
						<?php if ( empty( $fields ) ) : ?>
							<div class="xf-canvas-empty">
								<div class="xf-canvas-empty-icon">
									<span class="dashicons dashicons-plus-alt"></span>
								</div>
								<p><?php esc_html_e( 'No fields yet.', 'xtreme-forms' ); ?></p>
								<p class="xf-text-xs xf-text-muted"><?php esc_html_e( 'Use the "Add Field" bar above to get started.', 'xtreme-forms' ); ?></p>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Save Bar -->
				<div class="xf-builder-save-bar">
					<div class="xf-builder-save-bar-inner">
						<button type="submit" class="xf-btn xf-btn-primary">
							<span class="dashicons dashicons-saved"></span>
							<?php esc_html_e( 'Save Form', 'xtreme-forms' ); ?>
						</button>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtreme-forms-forms' ), admin_url( 'admin.php' ) ) ); ?>" class="xf-btn xf-btn-ghost">
							<?php esc_html_e( 'Cancel', 'xtreme-forms' ); ?>
						</a>
					</div>
				</div>

			</div><!-- .xf-builder-canvas -->

		</div><!-- .xf-builder-layout -->
	</form>
</div><!-- .xf-form-builder-wrap -->

<!-- Field editor JS template (used by xf-admin.js) -->
<script type="text/template" id="xf-field-template">
<div class="xf-field-item" data-field-id="{{fieldId}}" data-field-type="{{type}}" draggable="true">
	<div class="xf-field-header">
		<span class="xf-drag-handle dashicons dashicons-move" title="<?php esc_attr_e( 'Drag to reorder', 'xtreme-forms' ); ?>"></span>
		<span class="xf-field-type-label">{{typeLabel}}</span>
		<div class="xf-field-header-actions">
			<button type="button" class="xf-field-toggle" aria-expanded="true" aria-label="<?php esc_attr_e( 'Toggle field settings', 'xtreme-forms' ); ?>">
				<span class="dashicons dashicons-arrow-up-alt2"></span>
			</button>
			<button type="button" class="xf-field-delete" aria-label="<?php esc_attr_e( 'Delete field', 'xtreme-forms' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</div>
	</div>
	<div class="xf-field-body">
		<div class="xf-field-row">
			<div class="xf-field-col">
				<label class="xf-label"><?php esc_html_e( 'Label', 'xtreme-forms' ); ?></label>
				<input type="text" class="xf-input xf-input-full xf-field-label-input" placeholder="<?php esc_attr_e( 'Field Label', 'xtreme-forms' ); ?>" value="{{label}}">
			</div>
			<div class="xf-field-col xf-col-required">
				<label class="xf-label"><?php esc_html_e( 'Required', 'xtreme-forms' ); ?></label>
				<label class="xf-toggle-wrap">
					<span class="xf-toggle">
						<input type="checkbox" class="xf-field-required-input" {{requiredChecked}}>
						<span class="xf-toggle-track"></span>
						<span class="xf-toggle-thumb"></span>
					</span>
				</label>
			</div>
		</div>
		{{placeholderRow}}
		{{optionsSection}}
		{{hiddenDefaultRow}}
	</div>
</div>
</script>

<script>
var xfBuilderData = {
	fields: <?php echo wp_json_encode( $fields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT ); ?>,
	fieldTypes: <?php echo wp_json_encode( $field_type_labels, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT ); ?>,
	i18n: {
		addOption:          <?php echo wp_json_encode( __( 'Add Option', 'xtreme-forms' ) ); ?>,
		optionPlaceholder:  <?php echo wp_json_encode( __( 'Option text…', 'xtreme-forms' ) ); ?>,
		removeOption:       <?php echo wp_json_encode( __( 'Remove option', 'xtreme-forms' ) ); ?>,
		labelPlaceholder:   <?php echo wp_json_encode( __( 'Field Label', 'xtreme-forms' ) ); ?>,
		placeholder:        <?php echo wp_json_encode( __( 'Placeholder', 'xtreme-forms' ) ); ?>,
		defaultValue:       <?php echo wp_json_encode( __( 'Default Value', 'xtreme-forms' ) ); ?>,
		hiddenNote:         <?php echo wp_json_encode( __( 'Hidden fields are invisible to visitors.', 'xtreme-forms' ) ); ?>,
		optionsRequired:    <?php echo wp_json_encode( __( 'This field type requires at least one option.', 'xtreme-forms' ) ); ?>,
		confirmDelete:      <?php echo wp_json_encode( __( 'Delete this field?', 'xtreme-forms' ) ); ?>,
		required:           <?php echo wp_json_encode( __( 'Required', 'xtreme-forms' ) ); ?>,
		optionsLabel:       <?php echo wp_json_encode( __( 'Options', 'xtreme-forms' ) ); ?>,
		conditionalLogic:   <?php echo wp_json_encode( __( 'Conditional Logic', 'xtreme-forms' ) ); ?>,
		enableCondLogic:    <?php echo wp_json_encode( __( 'Enable conditional logic for this field', 'xtreme-forms' ) ); ?>,
		condLogicDesc:      <?php echo wp_json_encode( __( 'Show this field only when:', 'xtreme-forms' ) ); ?>,
		condLogicAnd:       <?php echo wp_json_encode( __( 'ALL conditions are met (AND)', 'xtreme-forms' ) ); ?>,
		condLogicOr:        <?php echo wp_json_encode( __( 'ANY condition is met (OR)', 'xtreme-forms' ) ); ?>,
		condLogicPreviewAnd:<?php echo wp_json_encode( __( 'Show this field when ALL of the following conditions are met:', 'xtreme-forms' ) ); ?>,
		condLogicPreviewOr: <?php echo wp_json_encode( __( 'Show this field when ANY of the following conditions are met:', 'xtreme-forms' ) ); ?>,
		addCondition:       <?php echo wp_json_encode( __( 'Add Condition', 'xtreme-forms' ) ); ?>,
		removeCondition:    <?php echo wp_json_encode( __( 'Remove condition', 'xtreme-forms' ) ); ?>,
		selectTriggerField: <?php echo wp_json_encode( __( '— Select trigger field —', 'xtreme-forms' ) ); ?>,
		condValue:          <?php echo wp_json_encode( __( 'Value', 'xtreme-forms' ) ); ?>,
		noOtherFields:      <?php echo wp_json_encode( __( 'No other fields available to use as triggers.', 'xtreme-forms' ) ); ?>,
	},
	condOperators: <?php echo wp_json_encode( array(
		'equals'     => __( 'equals', 'xtreme-forms' ),
		'not_equals' => __( 'does not equal', 'xtreme-forms' ),
		'contains'   => __( 'contains', 'xtreme-forms' ),
		'not_empty'  => __( 'is not empty', 'xtreme-forms' ),
		'is_empty'   => __( 'is empty', 'xtreme-forms' ),
	) ); ?>,
};
</script>

<script>
(function () {
	'use strict';

	// ── Builder Tab Switcher ───────────────────────────────────────────────
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

	// ── Auto-Responder toggle ──────────────────────────────────────────────
	var arCheckbox = document.getElementById('auto_responder_enabled');
	var arFields   = document.getElementById('xf-auto-responder-fields');
	if (arCheckbox && arFields) {
		arCheckbox.addEventListener('change', function () {
			arFields.style.display = arCheckbox.checked ? '' : 'none';
		});
	}

	// ── GDPR Consent toggle ────────────────────────────────────────────────
	var consentCb     = document.getElementById('consent_enabled');
	var consentFields = document.getElementById('xf-consent-fields');
	if (consentCb && consentFields) {
		consentCb.addEventListener('change', function () {
			consentFields.style.display = consentCb.checked ? '' : 'none';
		});
	}

	// ── Reply-To validation ────────────────────────────────────────────────
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
			if (replyToErr.style.display !== 'none') validateReplyTo();
		});
	}

	if (formEl) {
		formEl.addEventListener('submit', function (e) {
			if (!validateReplyTo()) { e.preventDefault(); replyToInput.focus(); }
		});
	}

	// ── Shortcode Copy ─────────────────────────────────────────────────────
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
</script>
