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
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Form saved successfully.', 'xtreme-forms' ) . '</p></div>';
} elseif ( ! empty( $save_errors ) && is_array( $save_errors ) ) {
	$error_items = implode( '</li><li>', array_map( 'esc_html', $save_errors ) );
	$notice_html = '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Please fix the following errors:', 'xtreme-forms' ) . '</strong></p><ul><li>' . $error_items . '</li></ul></div>';
}

$field_types = array(
	'text'     => __( 'Text', 'xtreme-forms' ),
	'email'    => __( 'Email', 'xtreme-forms' ),
	'phone'    => __( 'Phone', 'xtreme-forms' ),
	'textarea' => __( 'Textarea', 'xtreme-forms' ),
	'dropdown' => __( 'Dropdown', 'xtreme-forms' ),
	'checkbox' => __( 'Checkbox', 'xtreme-forms' ),
	'radio'    => __( 'Radio', 'xtreme-forms' ),
	'hidden'   => __( 'Hidden Field', 'xtreme-forms' ),
	'date'     => __( 'Date', 'xtreme-forms' ),
);

$fields_json      = wp_json_encode( $fields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT );
$submit_label     = $settings['submit_label'] ?? '';
$redirect_url     = $settings['redirect_url'] ?? '';
$thank_you_msg    = $settings['thank_you_message'] ?? '';
$email_recipients = $settings['email_recipients'] ?? '';
$ar_enabled       = ! empty( $settings['auto_responder_enabled'] ) && '1' === (string) $settings['auto_responder_enabled'];
$ar_subject       = $settings['auto_responder_subject'] ?? '';
$ar_body          = $settings['auto_responder_body'] ?? '';
$ar_reply_to      = $settings['auto_responder_reply_to'] ?? '';
// Consent checkbox.
$consent_enabled = ! empty( $settings['consent_enabled'] ) && '1' === (string) $settings['consent_enabled'];
$consent_label   = $settings['consent_label'] ?? '';
$consent_url     = $settings['consent_url'] ?? '';
// reCAPTCHA per-form.
$form_recaptcha = ! empty( $settings['recaptcha_enabled'] ) && '1' === (string) $settings['recaptcha_enabled'];
// Scheduling.
$countdown_enabled  = ! empty( $settings['countdown_timer_enabled'] ) && '1' === (string) $settings['countdown_timer_enabled'];
$closed_message_val = $form ? ( $form->closed_message ?? '' ) : '';
// Convert MySQL datetime to datetime-local format (YYYY-MM-DDTHH:MM) for the HTML input.
$activate_at_val = ( $form && ! empty( $form->activate_at ) && '0000-00-00 00:00:00' !== $form->activate_at )
	? str_replace( ' ', 'T', substr( $form->activate_at, 0, 16 ) )
	: '';
$expire_at_val   = ( $form && ! empty( $form->expire_at ) && '0000-00-00 00:00:00' !== $form->expire_at )
	? str_replace( ' ', 'T', substr( $form->expire_at, 0, 16 ) )
	: '';

$page_title = $is_edit
	? /* translators: %s: form name */ sprintf( __( 'Edit Form: %s', 'xtreme-forms' ), esc_html( $form->name ) )
	: __( 'Add New Form', 'xtreme-forms' );

$shortcode_hint = $is_edit
	? '<code class="xf-shortcode">[xtremeleads id="' . esc_attr( $form_id ) . '"]</code>'
	: '';
?>
<div class="wrap xf-wrap xf-form-builder-wrap">
	<h1 class="xf-page-title">
		<?php echo esc_html( $page_title ); ?>
		<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-forms' ), admin_url( 'admin.php' ) ) ); ?>" class="page-title-action">
			&laquo; <?php esc_html_e( 'Back to Forms', 'xtreme-forms' ); ?>
		</a>
		<?php if ( $shortcode_hint ) : ?>
			<span class="xf-shortcode-hint"><?php esc_html_e( 'Shortcode:', 'xtreme-forms' ); ?> <?php echo wp_kses_post( $shortcode_hint ); ?></span>
		<?php endif; ?>
	</h1>

	<?php echo wp_kses_post( $notice_html ); ?>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="xf-form-builder" novalidate>
		<input type="hidden" name="action" value="xf_save_form">
		<input type="hidden" name="form_id" value="<?php echo esc_attr( $form_id ); ?>">
		<?php wp_nonce_field( 'xf_save_form' ); ?>

		<!-- Hidden JSON field for field definitions -->
		<input type="hidden" name="xf_fields" id="xf-fields-json" value="<?php echo esc_attr( $fields_json ); ?>">

		<div class="xf-builder-layout">

			<!-- Left: Field Type Palette -->
			<div class="xf-builder-palette">
				<div class="xf-palette-card">
					<h3><?php esc_html_e( 'Form Settings', 'xtreme-forms' ); ?></h3>

					<div class="xf-field-group">
						<label for="form_name" class="xf-label"><?php esc_html_e( 'Form Name', 'xtreme-forms' ); ?> <span class="xf-required">*</span></label>
						<input type="text" id="form_name" name="form_name" value="<?php echo esc_attr( $form ? $form->name : '' ); ?>" class="xf-input xf-input-full" required placeholder="<?php esc_attr_e( 'e.g. Contact Form', 'xtreme-forms' ); ?>">
					</div>

					<div class="xf-field-group">
						<label for="submit_label" class="xf-label"><?php esc_html_e( 'Submit Button Label', 'xtreme-forms' ); ?></label>
						<input type="text" id="submit_label" name="submit_label" value="<?php echo esc_attr( $submit_label ); ?>" class="xf-input xf-input-full" placeholder="<?php esc_attr_e( 'Submit', 'xtreme-forms' ); ?>">
					</div>

					<div class="xf-field-group">
						<label for="redirect_url" class="xf-label"><?php esc_html_e( 'Redirect URL (after submit)', 'xtreme-forms' ); ?></label>
						<input type="url" id="redirect_url" name="redirect_url" value="<?php echo esc_attr( $redirect_url ); ?>" class="xf-input xf-input-full" placeholder="https://">
						<p class="xf-help-text"><?php esc_html_e( 'Leave blank to show a thank-you message instead.', 'xtreme-forms' ); ?></p>
					</div>

					<div class="xf-field-group">
						<label for="thank_you_message" class="xf-label"><?php esc_html_e( 'Thank-You Message', 'xtreme-forms' ); ?></label>
						<textarea id="thank_you_message" name="thank_you_message" class="xf-textarea xf-input-full" rows="3" placeholder="<?php esc_attr_e( 'Thank you! Your submission has been received.', 'xtreme-forms' ); ?>"><?php echo esc_textarea( $thank_you_msg ); ?></textarea>
					</div>

					<div class="xf-field-group">
						<label for="email_recipients" class="xf-label"><?php esc_html_e( 'Override Email Recipients', 'xtreme-forms' ); ?></label>
						<input type="text" id="email_recipients" name="email_recipients" value="<?php echo esc_attr( $email_recipients ); ?>" class="xf-input xf-input-full" placeholder="<?php esc_attr_e( 'email@example.com, another@example.com', 'xtreme-forms' ); ?>">
						<p class="xf-help-text"><?php esc_html_e( 'Comma-separated. Leave blank to use global recipients.', 'xtreme-forms' ); ?></p>
					</div>

					<!-- Auto-Responder -->
					<hr style="margin:16px 0;border-color:#DEE2E6;">
					<div class="xf-field-group">
						<label class="xf-label xf-checkbox-inline" style="font-weight:600;">
							<input type="checkbox" id="auto_responder_enabled" name="auto_responder_enabled" value="1" <?php checked( $ar_enabled ); ?>>
							<?php esc_html_e( 'Enable Auto-Responder Email', 'xtreme-forms' ); ?>
						</label>
						<p class="xf-help-text"><?php esc_html_e( 'Send an automatic confirmation email to the lead\'s submitted email address.', 'xtreme-forms' ); ?></p>
					</div>

					<div id="xf-auto-responder-fields" style="<?php echo $ar_enabled ? '' : 'display:none;'; ?>">
						<div class="xf-field-group">
							<label for="auto_responder_subject" class="xf-label"><?php esc_html_e( 'Auto-Responder Subject', 'xtreme-forms' ); ?></label>
							<input type="text" id="auto_responder_subject" name="auto_responder_subject" value="<?php echo esc_attr( $ar_subject ); ?>" class="xf-input xf-input-full" placeholder="<?php esc_attr_e( 'Thank you for contacting us', 'xtreme-forms' ); ?>">
							<p class="xf-help-text"><?php esc_html_e( 'Merge tags: {{lead_name}}, {{form_name}}, {{site_name}}, etc.', 'xtreme-forms' ); ?></p>
						</div>
						<div class="xf-field-group">
							<label for="auto_responder_body" class="xf-label"><?php esc_html_e( 'Auto-Responder Message', 'xtreme-forms' ); ?></label>
							<textarea id="auto_responder_body" name="auto_responder_body" class="xf-textarea xf-input-full" rows="5" placeholder="<?php esc_attr_e( 'Thank you for your submission. We will be in touch soon.', 'xtreme-forms' ); ?>"><?php echo esc_textarea( $ar_body ); ?></textarea>
							<p class="xf-help-text"><?php esc_html_e( 'Body text of the auto-responder email. Merge tags supported.', 'xtreme-forms' ); ?></p>
						</div>
						<div class="xf-field-group">
							<label for="auto_responder_reply_to" class="xf-label"><?php esc_html_e( 'Reply-To Address', 'xtreme-forms' ); ?></label>
							<input type="email" id="auto_responder_reply_to" name="auto_responder_reply_to" value="<?php echo esc_attr( $ar_reply_to ); ?>" class="xf-input xf-input-full" placeholder="reply@yourcompany.com">
							<p class="xf-help-text"><?php esc_html_e( 'Optional. When the lead replies to the auto-responder email, their reply will be directed here.', 'xtreme-forms' ); ?></p>
							<div id="xf-reply-to-error" style="display:none;color:#DC3545;font-size:12px;margin-top:4px;"></div>
						</div>
					</div>
				</div>

				<!-- GDPR Consent Checkbox -->
				<div class="xf-palette-card">
					<h3><?php esc_html_e( 'GDPR Consent Checkbox', 'xtreme-forms' ); ?></h3>
					<div class="xf-field-group">
						<label class="xf-label xf-checkbox-inline" style="font-weight:600;">
							<input type="checkbox" id="consent_enabled" name="consent_enabled" value="1" <?php checked( $consent_enabled ); ?> onchange="document.getElementById('xf-consent-fields').style.display=this.checked?'':'none';">
							<?php esc_html_e( 'Show consent checkbox on this form', 'xtreme-forms' ); ?>
						</label>
					</div>
					<div id="xf-consent-fields" style="<?php echo $consent_enabled ? '' : 'display:none;'; ?>">
						<div class="xf-field-group">
							<label for="consent_label" class="xf-label"><?php esc_html_e( 'Consent Label Text', 'xtreme-forms' ); ?></label>
							<textarea id="consent_label" name="consent_label" class="xf-textarea xf-input-full" rows="2" placeholder="<?php esc_attr_e( 'I agree to the Privacy Policy', 'xtreme-forms' ); ?>"><?php echo esc_textarea( $consent_label ); ?></textarea>
						</div>
						<div class="xf-field-group">
							<label for="consent_url" class="xf-label"><?php esc_html_e( 'Privacy Policy URL (optional)', 'xtreme-forms' ); ?></label>
							<input type="url" id="consent_url" name="consent_url" value="<?php echo esc_attr( $consent_url ); ?>" class="xf-input xf-input-full" placeholder="https://example.com/privacy-policy">
						</div>
					</div>
				</div>

				<!-- Form Scheduling -->
				<div class="xf-palette-card">
					<h3><?php esc_html_e( 'Form Scheduling', 'xtreme-forms' ); ?></h3>
					<div class="xf-field-group">
						<label for="activate_at" class="xf-label"><?php esc_html_e( 'Activation Date &amp; Time', 'xtreme-forms' ); ?></label>
						<input type="datetime-local" id="activate_at" name="activate_at" value="<?php echo esc_attr( $activate_at_val ); ?>" class="xf-input xf-input-full">
						<p class="xf-help-text"><?php esc_html_e( 'Leave blank to make the form available immediately. Uses site timezone.', 'xtreme-forms' ); ?></p>
					</div>
					<div class="xf-field-group">
						<label for="expire_at" class="xf-label"><?php esc_html_e( 'Expiration Date &amp; Time', 'xtreme-forms' ); ?></label>
						<input type="datetime-local" id="expire_at" name="expire_at" value="<?php echo esc_attr( $expire_at_val ); ?>" class="xf-input xf-input-full">
						<p class="xf-help-text"><?php esc_html_e( 'Leave blank for no expiry. Uses site timezone.', 'xtreme-forms' ); ?></p>
					</div>
					<div class="xf-field-group">
						<label for="closed_message" class="xf-label"><?php esc_html_e( 'Closed / Unavailable Message', 'xtreme-forms' ); ?></label>
						<textarea id="closed_message" name="closed_message" class="xf-textarea xf-input-full" rows="2" placeholder="<?php esc_attr_e( 'This form is currently unavailable.', 'xtreme-forms' ); ?>"><?php echo esc_textarea( $closed_message_val ); ?></textarea>
						<p class="xf-help-text"><?php esc_html_e( 'Shown when the form is outside its active scheduling window.', 'xtreme-forms' ); ?></p>
					</div>
					<div class="xf-field-group">
						<label class="xf-label xf-checkbox-inline">
							<input type="checkbox" id="countdown_timer_enabled" name="countdown_timer_enabled" value="1" <?php checked( $countdown_enabled ); ?>>
							<?php esc_html_e( 'Show countdown timer before activation', 'xtreme-forms' ); ?>
						</label>
						<p class="xf-help-text"><?php esc_html_e( 'Displays a live days/hours/minutes/seconds countdown above the closed message until the form activates. Requires an Activation Date.', 'xtreme-forms' ); ?></p>
					</div>
				</div>

				<!-- reCAPTCHA per-form -->
				<div class="xf-palette-card">
					<h3><?php esc_html_e( 'Spam Protection', 'xtreme-forms' ); ?></h3>
					<div class="xf-field-group">
						<label class="xf-label xf-checkbox-inline">
							<input type="checkbox" id="form_recaptcha_enabled" name="form_recaptcha_enabled" value="1" <?php checked( $form_recaptcha ); ?>>
							<?php esc_html_e( 'Enable reCAPTCHA v3 on this form', 'xtreme-forms' ); ?>
						</label>
						<?php
						$global_settings_fb = get_option( 'xtremeforms_settings', array() );
						if ( empty( $global_settings_fb['recaptcha_site_key'] ) || empty( $global_settings_fb['recaptcha_secret_key'] ) ) :
							?>
							<p class="xf-help-text" style="color:#FFC107;"><?php esc_html_e( 'reCAPTCHA keys not configured in Settings.', 'xtreme-forms' ); ?></p>
						<?php endif; ?>
					</div>
				</div>

				<div class="xf-palette-card">
					<h3><?php esc_html_e( 'Add Fields', 'xtreme-forms' ); ?></h3>
					<p class="xf-help-text"><?php esc_html_e( 'Click a field type to add it to your form.', 'xtreme-forms' ); ?></p>
					<div class="xf-field-type-list">
						<?php foreach ( $field_types as $type => $label ) : ?>
							<button type="button"
									class="xf-add-field-btn"
									data-type="<?php echo esc_attr( $type ); ?>"
									aria-label="<?php /* translators: %s: field type label */ echo esc_attr( sprintf( __( 'Add %s field', 'xtreme-forms' ), $label ) ); ?>">
								<span class="xf-field-type-icon xf-icon-<?php echo esc_attr( $type ); ?>"></span>
								<?php echo esc_html( $label ); ?>
							</button>
						<?php endforeach; ?>
					</div>
				</div>
			</div>

			<!-- Right: Form Canvas -->
			<div class="xf-builder-canvas">
				<div class="xf-canvas-card">
					<div class="xf-canvas-header">
						<h3><?php esc_html_e( 'Form Fields', 'xtreme-forms' ); ?></h3>
						<p class="xf-help-text"><?php esc_html_e( 'Drag to reorder. Click a field to configure it.', 'xtreme-forms' ); ?></p>
					</div>

					<div id="xf-fields-canvas" class="xf-fields-canvas" data-empty-label="<?php esc_attr_e( 'No fields yet. Add a field type from the left panel.', 'xtreme-forms' ); ?>">
						<?php if ( empty( $fields ) ) : ?>
							<div class="xf-canvas-empty">
								<span class="dashicons dashicons-plus-alt"></span>
								<p><?php esc_html_e( 'No fields yet. Add a field type from the left panel.', 'xtreme-forms' ); ?></p>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="xf-builder-actions">
					<button type="submit" class="button button-primary xf-btn-save">
						<?php esc_html_e( 'Save Form', 'xtreme-forms' ); ?>
					</button>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-forms' ), admin_url( 'admin.php' ) ) ); ?>" class="button xf-btn-secondary">
						<?php esc_html_e( 'Cancel', 'xtreme-forms' ); ?>
					</a>
				</div>
			</div>

		</div><!-- .xf-builder-layout -->
	</form>
</div>

<!-- Field editor templates (rendered by JS) -->
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
				<label class="xf-label xf-checkbox-inline">
					<input type="checkbox" class="xf-field-required-input" {{requiredChecked}}> <?php esc_html_e( 'Required', 'xtreme-forms' ); ?>
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
var xfBuilderData = {
	fields: <?php echo wp_json_encode( $fields, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT ); ?>,
	fieldTypes: <?php echo wp_json_encode( $field_types, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT ); ?>,
	i18n: {
		addOption: <?php echo wp_json_encode( __( 'Add Option', 'xtreme-forms' ) ); ?>,
		optionPlaceholder: <?php echo wp_json_encode( __( 'Option text…', 'xtreme-forms' ) ); ?>,
		removeOption: <?php echo wp_json_encode( __( 'Remove option', 'xtreme-forms' ) ); ?>,
		labelPlaceholder: <?php echo wp_json_encode( __( 'Field Label', 'xtreme-forms' ) ); ?>,
		placeholder: <?php echo wp_json_encode( __( 'Placeholder', 'xtreme-forms' ) ); ?>,
		defaultValue: <?php echo wp_json_encode( __( 'Default Value', 'xtreme-forms' ) ); ?>,
		hiddenNote: <?php echo wp_json_encode( __( 'Hidden fields are invisible to visitors.', 'xtreme-forms' ) ); ?>,
		optionsRequired: <?php echo wp_json_encode( __( 'This field type requires at least one option.', 'xtreme-forms' ) ); ?>,
		confirmDelete: <?php echo wp_json_encode( __( 'Delete this field?', 'xtreme-forms' ) ); ?>,
		required: <?php echo wp_json_encode( __( 'Required', 'xtreme-forms' ) ); ?>,
		optionsLabel: <?php echo wp_json_encode( __( 'Options', 'xtreme-forms' ) ); ?>,
		// Conditional logic i18n.
		conditionalLogic: <?php echo wp_json_encode( __( 'Conditional Logic', 'xtreme-forms' ) ); ?>,
		enableCondLogic: <?php echo wp_json_encode( __( 'Enable conditional logic for this field', 'xtreme-forms' ) ); ?>,
		condLogicDesc: <?php echo wp_json_encode( __( 'Show this field only when:', 'xtreme-forms' ) ); ?>,
		condLogicAnd: <?php echo wp_json_encode( __( 'ALL conditions are met (AND)', 'xtreme-forms' ) ); ?>,
		condLogicOr: <?php echo wp_json_encode( __( 'ANY condition is met (OR)', 'xtreme-forms' ) ); ?>,
		condLogicPreviewAnd: <?php echo wp_json_encode( __( 'Show this field when ALL of the following conditions are met:', 'xtreme-forms' ) ); ?>,
		condLogicPreviewOr: <?php echo wp_json_encode( __( 'Show this field when ANY of the following conditions are met:', 'xtreme-forms' ) ); ?>,
		addCondition: <?php echo wp_json_encode( __( 'Add Condition', 'xtreme-forms' ) ); ?>,
		removeCondition: <?php echo wp_json_encode( __( 'Remove condition', 'xtreme-forms' ) ); ?>,
		selectTriggerField: <?php echo wp_json_encode( __( '— Select trigger field —', 'xtreme-forms' ) ); ?>,
		condValue: <?php echo wp_json_encode( __( 'Value', 'xtreme-forms' ) ); ?>,
		noOtherFields: <?php echo wp_json_encode( __( 'No other fields available to use as triggers.', 'xtreme-forms' ) ); ?>,
	},
	// Condition operators for the conditional logic builder.
	condOperators: 
	<?php
	echo wp_json_encode(
		array(
			'equals'     => __( 'equals', 'xtreme-forms' ),
			'not_equals' => __( 'does not equal', 'xtreme-forms' ),
			'contains'   => __( 'contains', 'xtreme-forms' ),
			'not_empty'  => __( 'is not empty', 'xtreme-forms' ),
			'is_empty'   => __( 'is empty', 'xtreme-forms' ),
		)
	);
	?>
	,
};
</script>
<script>
(function () {
	'use strict';

	// ── Auto-Responder toggle ─────────────────────────────────────────────
	const arCheckbox = document.getElementById('auto_responder_enabled');
	const arFields = document.getElementById('xf-auto-responder-fields');

	if (arCheckbox && arFields) {
		arCheckbox.addEventListener('change', function () {
			arFields.style.display = arCheckbox.checked ? '' : 'none';
		});
	}

	// ── Reply-To validation ───────────────────────────────────────────────
	const replyToInput = document.getElementById('auto_responder_reply_to');
	const replyToErr = document.getElementById('xf-reply-to-error');
	const formEl = document.getElementById('xf-form-builder');

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
			if (!validateReplyTo()) {
				e.preventDefault();
				replyToInput.focus();
			}
		});
	}
}());
</script>
