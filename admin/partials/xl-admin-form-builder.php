<?php
/**
 * Form Builder admin page.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

$form_id = isset( $_GET['form_id'] ) ? absint( $_GET['form_id'] ) : 0;
$is_edit = $form_id > 0;
$form = $is_edit ? XL_Forms::get_form( $form_id ) : null;

if ( $is_edit && ! $form ) {
	wp_die( esc_html__( 'Form not found.', 'xtremeleads' ) );
}

$fields = $form ? XL_Forms::decode_fields( $form ) : array();
$settings = $form ? XL_Forms::decode_settings( $form ) : array();

// Retrieve any validation errors from previous save attempt.
$transient_key = 'xl_form_errors_' . get_current_user_id();
$save_errors = get_transient( $transient_key );
delete_transient( $transient_key );

$notice_html = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Form saved successfully.', 'xtremeleads' ) . '</p></div>';
} elseif ( ! empty( $save_errors ) && is_array( $save_errors ) ) {
	$error_items = implode( '</li><li>', array_map( 'esc_html', $save_errors ) );
	$notice_html = '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Please fix the following errors:', 'xtremeleads' ) . '</strong></p><ul><li>' . $error_items . '</li></ul></div>';
}

$field_types = array(
	'text' => __( 'Text', 'xtremeleads' ),
	'email' => __( 'Email', 'xtremeleads' ),
	'phone' => __( 'Phone', 'xtremeleads' ),
	'textarea' => __( 'Textarea', 'xtremeleads' ),
	'dropdown' => __( 'Dropdown', 'xtremeleads' ),
	'checkbox' => __( 'Checkbox', 'xtremeleads' ),
	'radio' => __( 'Radio', 'xtremeleads' ),
	'hidden' => __( 'Hidden Field', 'xtremeleads' ),
	'date' => __( 'Date', 'xtremeleads' ),
);

$fields_json = wp_json_encode( $fields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT );
$submit_label = $settings['submit_label'] ?? '';
$redirect_url = $settings['redirect_url'] ?? '';
$thank_you_msg = $settings['thank_you_message'] ?? '';
$email_recipients = $settings['email_recipients'] ?? '';
$ar_enabled = ! empty( $settings['auto_responder_enabled'] ) && '1' === (string) $settings['auto_responder_enabled'];
$ar_subject = $settings['auto_responder_subject'] ?? '';
$ar_body = $settings['auto_responder_body'] ?? '';
$ar_reply_to = $settings['auto_responder_reply_to'] ?? '';
// Consent checkbox.
$consent_enabled = ! empty( $settings['consent_enabled'] ) && '1' === (string) $settings['consent_enabled'];
$consent_label = $settings['consent_label'] ?? '';
$consent_url = $settings['consent_url'] ?? '';
// reCAPTCHA per-form.
$form_recaptcha = ! empty( $settings['recaptcha_enabled'] ) && '1' === (string) $settings['recaptcha_enabled'];
// Scheduling.
$countdown_enabled = ! empty( $settings['countdown_timer_enabled'] ) && '1' === (string) $settings['countdown_timer_enabled'];
$closed_message_val = $form ? ( $form->closed_message ?? '' ) : '';
// Convert MySQL datetime to datetime-local format (YYYY-MM-DDTHH:MM) for the HTML input.
$activate_at_val = ( $form && ! empty( $form->activate_at ) && '0000-00-00 00:00:00' !== $form->activate_at )
	? str_replace( ' ', 'T', substr( $form->activate_at, 0, 16 ) )
	: '';
$expire_at_val = ( $form && ! empty( $form->expire_at ) && '0000-00-00 00:00:00' !== $form->expire_at )
	? str_replace( ' ', 'T', substr( $form->expire_at, 0, 16 ) )
	: '';

$page_title = $is_edit
	? /* translators: %s: form name */ sprintf( __( 'Edit Form: %s', 'xtremeleads' ), esc_html( $form->name ) )
	: __( 'Add New Form', 'xtremeleads' );

$shortcode_hint = $is_edit
	? '<code class="xl-shortcode">[xtremeleads id="' . esc_attr( $form_id ) . '"]</code>'
	: '';
?>
<div class="wrap xl-wrap xl-form-builder-wrap">
	<h1 class="xl-page-title">
		<?php echo esc_html( $page_title ); ?>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-forms' ), admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">
			&laquo; <?php esc_html_e( 'Back to Forms', 'xtremeleads' ); ?>
		</a>
		<?php if ( $shortcode_hint ) : ?>
			<span class="xl-shortcode-hint"><?php esc_html_e( 'Shortcode:', 'xtremeleads' ); ?> <?php echo wp_kses_post( $shortcode_hint ); ?></span>
		<?php endif; ?>
	</h1>

	<?php echo wp_kses_post( $notice_html ); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="xl-form-builder" novalidate>
		<input type="hidden" name="action" value="xl_save_form">
		<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
		<?php wp_nonce_field( 'xl_save_form' ); ?>

		<!-- Hidden JSON field for field definitions -->
		<input type="hidden" name="xl_fields" id="xl-fields-json" value="<?php echo esc_attr( $fields_json ); ?>">

		<div class="xl-builder-layout">

			<!-- Left: Field Type Palette -->
			<div class="xl-builder-palette">
				<div class="xl-palette-card">
					<h3><?php esc_html_e( 'Form Settings', 'xtremeleads' ); ?></h3>

					<div class="xl-field-group">
						<label for="form_name" class="xl-label"><?php esc_html_e( 'Form Name', 'xtremeleads' ); ?> <span class="xl-required">*</span></label>
						<input type="text" id="form_name" name="form_name" value="<?php echo esc_attr( $form ? $form->name : '' ); ?>" class="xl-input xl-input-full" required placeholder="<?php esc_attr_e( 'e.g. Contact Form', 'xtremeleads' ); ?>">
					</div>

					<div class="xl-field-group">
						<label for="submit_label" class="xl-label"><?php esc_html_e( 'Submit Button Label', 'xtremeleads' ); ?></label>
						<input type="text" id="submit_label" name="submit_label" value="<?php echo esc_attr( $submit_label ); ?>" class="xl-input xl-input-full" placeholder="<?php esc_attr_e( 'Submit', 'xtremeleads' ); ?>">
					</div>

					<div class="xl-field-group">
						<label for="redirect_url" class="xl-label"><?php esc_html_e( 'Redirect URL (after submit)', 'xtremeleads' ); ?></label>
						<input type="url" id="redirect_url" name="redirect_url" value="<?php echo esc_attr( $redirect_url ); ?>" class="xl-input xl-input-full" placeholder="https://">
						<p class="xl-help-text"><?php esc_html_e( 'Leave blank to show a thank-you message instead.', 'xtremeleads' ); ?></p>
					</div>

					<div class="xl-field-group">
						<label for="thank_you_message" class="xl-label"><?php esc_html_e( 'Thank-You Message', 'xtremeleads' ); ?></label>
						<textarea id="thank_you_message" name="thank_you_message" class="xl-textarea xl-input-full" rows="3" placeholder="<?php esc_attr_e( 'Thank you! Your submission has been received.', 'xtremeleads' ); ?>"><?php echo esc_textarea( $thank_you_msg ); ?></textarea>
					</div>

					<div class="xl-field-group">
						<label for="email_recipients" class="xl-label"><?php esc_html_e( 'Override Email Recipients', 'xtremeleads' ); ?></label>
						<input type="text" id="email_recipients" name="email_recipients" value="<?php echo esc_attr( $email_recipients ); ?>" class="xl-input xl-input-full" placeholder="<?php esc_attr_e( 'email@example.com, another@example.com', 'xtremeleads' ); ?>">
						<p class="xl-help-text"><?php esc_html_e( 'Comma-separated. Leave blank to use global recipients.', 'xtremeleads' ); ?></p>
					</div>

					<!-- Auto-Responder -->
					<hr style="margin:16px 0;border-color:#DEE2E6;">
					<div class="xl-field-group">
						<label class="xl-label xl-checkbox-inline" style="font-weight:600;">
							<input type="checkbox" id="auto_responder_enabled" name="auto_responder_enabled" value="1" <?php checked( $ar_enabled ); ?>>
							<?php esc_html_e( 'Enable Auto-Responder Email', 'xtremeleads' ); ?>
						</label>
						<p class="xl-help-text"><?php esc_html_e( 'Send an automatic confirmation email to the lead\'s submitted email address.', 'xtremeleads' ); ?></p>
					</div>

					<div id="xl-auto-responder-fields" style="<?php echo $ar_enabled ? '' : 'display:none;'; ?>">
						<div class="xl-field-group">
							<label for="auto_responder_subject" class="xl-label"><?php esc_html_e( 'Auto-Responder Subject', 'xtremeleads' ); ?></label>
							<input type="text" id="auto_responder_subject" name="auto_responder_subject" value="<?php echo esc_attr( $ar_subject ); ?>" class="xl-input xl-input-full" placeholder="<?php esc_attr_e( 'Thank you for contacting us', 'xtremeleads' ); ?>">
							<p class="xl-help-text"><?php esc_html_e( 'Merge tags: {{lead_name}}, {{form_name}}, {{site_name}}, etc.', 'xtremeleads' ); ?></p>
						</div>
						<div class="xl-field-group">
							<label for="auto_responder_body" class="xl-label"><?php esc_html_e( 'Auto-Responder Message', 'xtremeleads' ); ?></label>
							<textarea id="auto_responder_body" name="auto_responder_body" class="xl-textarea xl-input-full" rows="5" placeholder="<?php esc_attr_e( 'Thank you for your submission. We will be in touch soon.', 'xtremeleads' ); ?>"><?php echo esc_textarea( $ar_body ); ?></textarea>
							<p class="xl-help-text"><?php esc_html_e( 'Body text of the auto-responder email. Merge tags supported.', 'xtremeleads' ); ?></p>
						</div>
						<div class="xl-field-group">
							<label for="auto_responder_reply_to" class="xl-label"><?php esc_html_e( 'Reply-To Address', 'xtremeleads' ); ?></label>
							<input type="email" id="auto_responder_reply_to" name="auto_responder_reply_to" value="<?php echo esc_attr( $ar_reply_to ); ?>" class="xl-input xl-input-full" placeholder="reply@yourcompany.com">
							<p class="xl-help-text"><?php esc_html_e( 'Optional. When the lead replies to the auto-responder email, their reply will be directed here.', 'xtremeleads' ); ?></p>
							<div id="xl-reply-to-error" style="display:none;color:#DC3545;font-size:12px;margin-top:4px;"></div>
						</div>
					</div>
				</div>

				<!-- GDPR Consent Checkbox -->
				<div class="xl-palette-card">
					<h3><?php esc_html_e( 'GDPR Consent Checkbox', 'xtremeleads' ); ?></h3>
					<div class="xl-field-group">
						<label class="xl-label xl-checkbox-inline" style="font-weight:600;">
							<input type="checkbox" id="consent_enabled" name="consent_enabled" value="1" <?php checked( $consent_enabled ); ?> onchange="document.getElementById('xl-consent-fields').style.display=this.checked?'':'none';">
							<?php esc_html_e( 'Show consent checkbox on this form', 'xtremeleads' ); ?>
						</label>
					</div>
					<div id="xl-consent-fields" style="<?php echo $consent_enabled ? '' : 'display:none;'; ?>">
						<div class="xl-field-group">
							<label for="consent_label" class="xl-label"><?php esc_html_e( 'Consent Label Text', 'xtremeleads' ); ?></label>
							<textarea id="consent_label" name="consent_label" class="xl-textarea xl-input-full" rows="2" placeholder="<?php esc_attr_e( 'I agree to the Privacy Policy', 'xtremeleads' ); ?>"><?php echo esc_textarea( $consent_label ); ?></textarea>
						</div>
						<div class="xl-field-group">
							<label for="consent_url" class="xl-label"><?php esc_html_e( 'Privacy Policy URL (optional)', 'xtremeleads' ); ?></label>
							<input type="url" id="consent_url" name="consent_url" value="<?php echo esc_attr( $consent_url ); ?>" class="xl-input xl-input-full" placeholder="https://example.com/privacy-policy">
						</div>
					</div>
				</div>

				<!-- Form Scheduling -->
				<div class="xl-palette-card">
					<h3><?php esc_html_e( 'Form Scheduling', 'xtremeleads' ); ?></h3>
					<div class="xl-field-group">
						<label for="activate_at" class="xl-label"><?php esc_html_e( 'Activation Date &amp; Time', 'xtremeleads' ); ?></label>
						<input type="datetime-local" id="activate_at" name="activate_at" value="<?php echo esc_attr( $activate_at_val ); ?>" class="xl-input xl-input-full">
						<p class="xl-help-text"><?php esc_html_e( 'Leave blank to make the form available immediately. Uses site timezone.', 'xtremeleads' ); ?></p>
					</div>
					<div class="xl-field-group">
						<label for="expire_at" class="xl-label"><?php esc_html_e( 'Expiration Date &amp; Time', 'xtremeleads' ); ?></label>
						<input type="datetime-local" id="expire_at" name="expire_at" value="<?php echo esc_attr( $expire_at_val ); ?>" class="xl-input xl-input-full">
						<p class="xl-help-text"><?php esc_html_e( 'Leave blank for no expiry. Uses site timezone.', 'xtremeleads' ); ?></p>
					</div>
					<div class="xl-field-group">
						<label for="closed_message" class="xl-label"><?php esc_html_e( 'Closed / Unavailable Message', 'xtremeleads' ); ?></label>
						<textarea id="closed_message" name="closed_message" class="xl-textarea xl-input-full" rows="2" placeholder="<?php esc_attr_e( 'This form is currently unavailable.', 'xtremeleads' ); ?>"><?php echo esc_textarea( $closed_message_val ); ?></textarea>
						<p class="xl-help-text"><?php esc_html_e( 'Shown when the form is outside its active scheduling window.', 'xtremeleads' ); ?></p>
					</div>
					<div class="xl-field-group">
						<label class="xl-label xl-checkbox-inline">
							<input type="checkbox" id="countdown_timer_enabled" name="countdown_timer_enabled" value="1" <?php checked( $countdown_enabled ); ?>>
							<?php esc_html_e( 'Show countdown timer before activation', 'xtremeleads' ); ?>
						</label>
						<p class="xl-help-text"><?php esc_html_e( 'Displays a live days/hours/minutes/seconds countdown above the closed message until the form activates. Requires an Activation Date.', 'xtremeleads' ); ?></p>
					</div>
				</div>

				<!-- reCAPTCHA per-form -->
				<div class="xl-palette-card">
					<h3><?php esc_html_e( 'Spam Protection', 'xtremeleads' ); ?></h3>
					<div class="xl-field-group">
						<label class="xl-label xl-checkbox-inline">
							<input type="checkbox" id="form_recaptcha_enabled" name="form_recaptcha_enabled" value="1" <?php checked( $form_recaptcha ); ?>>
							<?php esc_html_e( 'Enable reCAPTCHA v3 on this form', 'xtremeleads' ); ?>
						</label>
						<?php
						$global_settings_fb = get_option( 'xtremeleads_settings', array() );
						if ( empty( $global_settings_fb['recaptcha_site_key'] ) || empty( $global_settings_fb['recaptcha_secret_key'] ) ) : ?>
							<p class="xl-help-text" style="color:#FFC107;"><?php esc_html_e( 'reCAPTCHA keys not configured in Settings.', 'xtremeleads' ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<div class="xl-palette-card">
					<h3><?php esc_html_e( 'Add Fields', 'xtremeleads' ); ?></h3>
					<p class="xl-help-text"><?php esc_html_e( 'Click a field type to add it to your form.', 'xtremeleads' ); ?></p>
					<div class="xl-field-type-list">
						<?php foreach ( $field_types as $type => $label ) : ?>
							<button type="button"
									class="xl-add-field-btn"
									data-type="<?php echo esc_attr( $type ); ?>"
									aria-label="<?php echo esc_attr( sprintf( __( 'Add %s field', 'xtremeleads' ), $label ) ); ?>">
								<span class="xl-field-type-icon xl-icon-<?php echo esc_attr( $type ); ?>"></span>
								<?php echo esc_html( $label ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<!-- Right: Form Canvas -->
			<div class="xl-builder-canvas">
				<div class="xl-canvas-card">
					<div class="xl-canvas-header">
						<h3><?php esc_html_e( 'Form Fields', 'xtremeleads' ); ?></h3>
						<p class="xl-help-text"><?php esc_html_e( 'Drag to reorder. Click a field to configure it.', 'xtremeleads' ); ?></p>
					</div>

					<div id="xl-fields-canvas" class="xl-fields-canvas" data-empty-label="<?php esc_attr_e( 'No fields yet. Add a field type from the left panel.', 'xtremeleads' ); ?>">
						<?php if ( empty( $fields ) ) : ?>
							<div class="xl-canvas-empty">
								<span class="dashicons dashicons-plus-alt"></span>
								<p><?php esc_html_e( 'No fields yet. Add a field type from the left panel.', 'xtremeleads' ); ?></p>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="xl-builder-actions">
					<button type="submit" class="button button-primary xl-btn-save">
						<?php esc_html_e( 'Save Form', 'xtremeleads' ); ?>
					</button>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-forms' ), admin_url( 'admin.php' ) ) ); ?>" class="button xl-btn-secondary">
						<?php esc_html_e( 'Cancel', 'xtremeleads' ); ?>
					</a>
				</div>
			</div>

		</div><!-- .xl-builder-layout -->
	</form>
</div>

<!-- Field editor templates (rendered by JS) -->
<script type="text/template" id="xl-field-template">
<div class="xl-field-item" data-field-id="{{fieldId}}" data-field-type="{{type}}" draggable="true">
	<div class="xl-field-header">
		<span class="xl-drag-handle dashicons dashicons-move" title="<?php esc_attr_e( 'Drag to reorder', 'xtremeleads' ); ?>"></span>
		<span class="xl-field-type-label">{{typeLabel}}</span>
		<div class="xl-field-header-actions">
			<button type="button" class="xl-field-toggle" aria-expanded="true" aria-label="<?php esc_attr_e( 'Toggle field settings', 'xtremeleads' ); ?>">
				<span class="dashicons dashicons-arrow-up-alt2"></span>
			</button>
			<button type="button" class="xl-field-delete" aria-label="<?php esc_attr_e( 'Delete field', 'xtremeleads' ); ?>">
				<span class="dashicons dashicons-trash"></span>
			</button>
		</div>
	</div>
	<div class="xl-field-body">
		<div class="xl-field-row">
			<div class="xl-field-col">
				<label class="xl-label"><?php esc_html_e( 'Label', 'xtremeleads' ); ?></label>
				<input type="text" class="xl-input xl-input-full xl-field-label-input" placeholder="<?php esc_attr_e( 'Field Label', 'xtremeleads' ); ?>" value="{{label}}">
			</div>
			<div class="xl-field-col xl-col-required">
				<label class="xl-label xl-checkbox-inline">
					<input type="checkbox" class="xl-field-required-input" {{requiredChecked}}> <?php esc_html_e( 'Required', 'xtremeleads' ); ?>
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
// Pass field definitions and translations to admin JS.
var xlBuilderData = {
	fields: <?php echo wp_json_encode( $fields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT ); ?>,
	fieldTypes: <?php echo wp_json_encode( $field_types, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT ); ?>,
	i18n: {
		addOption: <?php echo wp_json_encode( __( 'Add Option', 'xtremeleads' ) ); ?>,
		optionPlaceholder: <?php echo wp_json_encode( __( 'Option text…', 'xtremeleads' ) ); ?>,
		removeOption: <?php echo wp_json_encode( __( 'Remove option', 'xtremeleads' ) ); ?>,
		labelPlaceholder: <?php echo wp_json_encode( __( 'Field Label', 'xtremeleads' ) ); ?>,
		placeholder: <?php echo wp_json_encode( __( 'Placeholder', 'xtremeleads' ) ); ?>,
		defaultValue: <?php echo wp_json_encode( __( 'Default Value', 'xtremeleads' ) ); ?>,
		hiddenNote: <?php echo wp_json_encode( __( 'Hidden fields are invisible to visitors.', 'xtremeleads' ) ); ?>,
		optionsRequired: <?php echo wp_json_encode( __( 'This field type requires at least one option.', 'xtremeleads' ) ); ?>,
		confirmDelete: <?php echo wp_json_encode( __( 'Delete this field?', 'xtremeleads' ) ); ?>,
		required: <?php echo wp_json_encode( __( 'Required', 'xtremeleads' ) ); ?>,
		optionsLabel: <?php echo wp_json_encode( __( 'Options', 'xtremeleads' ) ); ?>,
		// Conditional logic i18n.
		conditionalLogic: <?php echo wp_json_encode( __( 'Conditional Logic', 'xtremeleads' ) ); ?>,
		enableCondLogic: <?php echo wp_json_encode( __( 'Enable conditional logic for this field', 'xtremeleads' ) ); ?>,
		condLogicDesc: <?php echo wp_json_encode( __( 'Show this field only when:', 'xtremeleads' ) ); ?>,
		condLogicAnd: <?php echo wp_json_encode( __( 'ALL conditions are met (AND)', 'xtremeleads' ) ); ?>,
		condLogicOr: <?php echo wp_json_encode( __( 'ANY condition is met (OR)', 'xtremeleads' ) ); ?>,
		condLogicPreviewAnd: <?php echo wp_json_encode( __( 'Show this field when ALL of the following conditions are met:', 'xtremeleads' ) ); ?>,
		condLogicPreviewOr: <?php echo wp_json_encode( __( 'Show this field when ANY of the following conditions are met:', 'xtremeleads' ) ); ?>,
		addCondition: <?php echo wp_json_encode( __( 'Add Condition', 'xtremeleads' ) ); ?>,
		removeCondition: <?php echo wp_json_encode( __( 'Remove condition', 'xtremeleads' ) ); ?>,
		selectTriggerField: <?php echo wp_json_encode( __( '— Select trigger field —', 'xtremeleads' ) ); ?>,
		condValue: <?php echo wp_json_encode( __( 'Value', 'xtremeleads' ) ); ?>,
		noOtherFields: <?php echo wp_json_encode( __( 'No other fields available to use as triggers.', 'xtremeleads' ) ); ?>,
	},
	// Condition operators for the conditional logic builder.
	condOperators: <?php echo wp_json_encode( array(
		'equals' => __( 'equals', 'xtremeleads' ),
		'not_equals' => __( 'does not equal', 'xtremeleads' ),
		'contains' => __( 'contains', 'xtremeleads' ),
		'not_empty' => __( 'is not empty', 'xtremeleads' ),
		'is_empty' => __( 'is empty', 'xtremeleads' ),
	) ); ?>,
};
</script>
<script>
(function () {
	'use strict';

	// ── Auto-Responder toggle ─────────────────────────────────────────────
	const arCheckbox = document.getElementById('auto_responder_enabled');
	const arFields = document.getElementById('xl-auto-responder-fields');

	if (arCheckbox && arFields) {
		arCheckbox.addEventListener('change', function () {
			arFields.style.display = arCheckbox.checked ? '' : 'none';
		});
	}

	// ── Reply-To validation ───────────────────────────────────────────────
	const replyToInput = document.getElementById('auto_responder_reply_to');
	const replyToErr = document.getElementById('xl-reply-to-error');
	const formEl = document.getElementById('xl-form-builder');

	function validateReplyTo() {
		if (!replyToInput || !replyToErr) return true;
		const val = replyToInput.value.trim();
		if (val === '') {
			replyToErr.style.display = 'none';
			return true;
		}
		// Basic email format check.
		const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
		if (!emailRe.test(val)) {
			replyToErr.textContent = '<?php echo esc_js( __( 'Please enter a valid reply-to email address.', 'xtremeleads' ) ); ?>';
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
			if (!validateReplyTo()) {
				e.preventDefault();
				replyToInput.focus();
			}
		});
	}
}());
</script>
