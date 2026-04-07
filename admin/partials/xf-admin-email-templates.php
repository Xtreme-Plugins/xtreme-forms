<?php
/**
 * Email Templates admin page.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification -- Filter parameters on this admin display page are read-only GET params — no nonce required for display-only filtering.

$template = XF_Email_Templates::get_template();
$notice = '';

if ( ! empty( $_GET['updated'] ) ) {
	$notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Email template settings saved.', 'xtreme-forms' ) . '</p></div>';
}

if ( ! empty( $_GET['test_sent'] ) ) {
	$notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Test email sent successfully.', 'xtreme-forms' ) . '</p></div>';
}

if ( ! empty( $_GET['test_failed'] ) ) {
	$notice = '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Test email failed to send. Check your WordPress email configuration.', 'xtreme-forms' ) . '</p></div>';
}

$merge_tags_desc = implode( ', ', array(
	'<code>{{lead_name}}</code>',
	'<code>{{lead_email}}</code>',
	'<code>{{lead_phone}}</code>',
	'<code>{{form_name}}</code>',
	'<code>{{site_url}}</code>',
	'<code>{{site_name}}</code>',
	'<code>{{submission_date}}</code>',
	'<code>{{source_url}}</code>',
	'<code>{{lead_id}}</code>',
) );
?>
<div class="wrap xf-wrap">
	<h1 class="xf-page-title"><?php esc_html_e( 'Email Templates', 'xtreme-forms' ); ?></h1>

	<?php echo wp_kses_post( $notice ); ?>

	<div class="xf-two-col-layout">
		<!-- ── Editor ────────────────────────────────────────────────────── -->
		<div class="xf-main-col">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="xf-template-form">
				<input type="hidden" name="action" value="xf_save_email_template">
				<?php wp_nonce_field( 'xf_save_email_template' ); ?>

				<div class="xf-settings-card">
					<h2><?php esc_html_e( 'Template Design', 'xtreme-forms' ); ?></h2>

					<table class="form-table" role="presentation">
						<tbody>
							<!-- Logo Upload -->
							<tr>
								<th scope="row">
									<label for="xf_logo_upload"><?php esc_html_e( 'Logo', 'xtreme-forms' ); ?></label>
								</th>
								<td>
									<?php if ( $template['logo_url'] ) : ?>
										<div class="xf-logo-preview" style="margin-bottom:12px;">
											<img src="<?php echo esc_url( $template['logo_url'] ); ?>" alt="<?php esc_attr_e( 'Current logo', 'xtreme-forms' ); ?>" style="max-height:60px;max-width:240px;display:block;border:1px solid #DEE2E6;padding:4px;border-radius:4px;">
										</div>
									<?php endif; ?>

									<input type="file" id="xf_logo_upload" name="xf_logo_file" accept="image/jpeg,image/png,image/gif,image/webp" style="margin-bottom:8px;display:block;">
									<input type="hidden" name="xf_logo_url" id="xf_logo_url" value="<?php echo esc_attr( $template['logo_url'] ); ?>">

									<p class="description">
										<?php esc_html_e( 'Accepted: JPEG, PNG, GIF, WebP. Maximum size: 2 MB. Leave blank to use no logo.', 'xtreme-forms' ); ?>
									</p>

									<?php if ( $template['logo_url'] ) : ?>
										<label class="xf-checkbox-inline" style="margin-top:8px;display:block;">
											<input type="checkbox" name="xf_remove_logo" value="1">
											<?php esc_html_e( 'Remove current logo', 'xtreme-forms' ); ?>
										</label>
									<?php endif; ?>

									<div id="xf-logo-error" class="xf-field-error" style="display:none;color:#DC3545;margin-top:4px;"></div>
								</td>
							</tr>

							<!-- Header Color -->
							<tr>
								<th scope="row">
									<label for="xf_header_color"><?php esc_html_e( 'Header Background Color', 'xtreme-forms' ); ?></label>
								</th>
								<td>
									<div style="display:flex;align-items:center;gap:12px;">
										<input type="color" id="xf_header_color" name="xf_header_color" value="<?php echo esc_attr( $template['header_color'] ); ?>" style="width:48px;height:36px;padding:2px;border-radius:4px;border:1px solid #DEE2E6;cursor:pointer;">
										<input type="text" id="xf_header_color_hex" name="xf_header_color_hex_display" value="<?php echo esc_attr( $template['header_color'] ); ?>" maxlength="7" style="width:100px;" placeholder="#1A73E8" pattern="#[0-9A-Fa-f]{6}" title="<?php esc_attr_e( 'Hex color value, e.g. #1A73E8', 'xtreme-forms' ); ?>">
									</div>
									<p class="description"><?php esc_html_e( 'Default: Electric Blue (#1A73E8)', 'xtreme-forms' ); ?></p>
								</td>
							</tr>

							<!-- Email Subject -->
							<tr>
								<th scope="row">
									<label for="xf_subject"><?php esc_html_e( 'Email Subject', 'xtreme-forms' ); ?></label>
								</th>
								<td>
									<input type="text" id="xf_subject" name="xf_subject" value="<?php echo esc_attr( $template['subject'] ); ?>" class="large-text">
									<p class="description"><?php esc_html_e( 'Merge tags supported.', 'xtreme-forms' ); ?></p>
								</td>
							</tr>

							<!-- Body Text -->
							<tr>
								<th scope="row">
									<label for="xf_body_text"><?php esc_html_e( 'Body / Intro Text', 'xtreme-forms' ); ?></label>
								</th>
								<td>
									<textarea id="xf_body_text" name="xf_body_text" rows="4" class="large-text"><?php echo esc_textarea( $template['body_text'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Introduction paragraph shown above the submitted field values. Merge tags supported.', 'xtreme-forms' ); ?></p>
								</td>
							</tr>

							<!-- Footer Text -->
							<tr>
								<th scope="row">
									<label for="xf_footer_text"><?php esc_html_e( 'Footer Text', 'xtreme-forms' ); ?></label>
								</th>
								<td>
									<textarea id="xf_footer_text" name="xf_footer_text" rows="3" class="large-text"><?php echo esc_textarea( $template['footer_text'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Footer shown at the bottom of all notification emails.', 'xtreme-forms' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Merge Tags Reference -->
				<div class="xf-settings-card">
					<h2><?php esc_html_e( 'Available Merge Tags', 'xtreme-forms' ); ?></h2>
					<p class="description"><?php /* translators: %s: comma-separated list of merge tag codes */ printf( esc_html__( 'Use these tags in the subject, body, and footer fields: %s', 'xtreme-forms' ), wp_kses_post( $merge_tags_desc ) ); ?></p>
					<table class="widefat striped" style="margin-top:12px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Tag', 'xtreme-forms' ); ?></th>
								<th><?php esc_html_e( 'Description', 'xtreme-forms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td><code>{{lead_name}}</code></td><td><?php esc_html_e( 'Lead\'s detected name from form fields', 'xtreme-forms' ); ?></td></tr>
							<tr><td><code>{{lead_email}}</code></td><td><?php esc_html_e( 'Lead\'s email address', 'xtreme-forms' ); ?></td></tr>
							<tr><td><code>{{lead_phone}}</code></td><td><?php esc_html_e( 'Lead\'s phone number', 'xtreme-forms' ); ?></td></tr>
							<tr><td><code>{{form_name}}</code></td><td><?php esc_html_e( 'Name of the form submitted', 'xtreme-forms' ); ?></td></tr>
							<tr><td><code>{{site_name}}</code></td><td><?php esc_html_e( 'Your WordPress site name', 'xtreme-forms' ); ?></td></tr>
							<tr><td><code>{{site_url}}</code></td><td><?php esc_html_e( 'Your WordPress site URL', 'xtreme-forms' ); ?></td></tr>
							<tr><td><code>{{submission_date}}</code></td><td><?php esc_html_e( 'Date and time of the submission', 'xtreme-forms' ); ?></td></tr>
							<tr><td><code>{{source_url}}</code></td><td><?php esc_html_e( 'URL of the page where the form was submitted', 'xtreme-forms' ); ?></td></tr>
							<tr><td><code>{{lead_id}}</code></td><td><?php esc_html_e( 'Internal lead ID', 'xtreme-forms' ); ?></td></tr>
						</tbody>
					</table>
				</div>

				<div class="xf-form-actions">
					<?php submit_button( __( 'Save Template', 'xtreme-forms' ), 'primary xf-btn-primary', 'submit', false ); ?>
					<button type="button" id="xf-send-test-email" class="button xf-btn-secondary" style="margin-left:8px;">
						<?php esc_html_e( 'Send Test Email', 'xtreme-forms' ); ?>
					</button>
					<span id="xf-test-email-result" style="margin-left:12px;font-style:italic;"></span>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
(function () {
	'use strict';

	// ── Color picker sync ──────────────────────────────────────────────────
	const colorPicker = document.getElementById('xf_header_color');
	const colorHex = document.getElementById('xf_header_color_hex');

	if (colorPicker && colorHex) {
		colorPicker.addEventListener('input', function () {
			colorHex.value = colorPicker.value.toUpperCase();
		});
		colorHex.addEventListener('input', function () {
			const val = colorHex.value.trim();
			if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
				colorPicker.value = val;
				// Update actual hidden input used by form.
				document.querySelector('[name="xf_header_color"]').value = val;
			}
		});
		// Sync the actual POST field name.
		colorPicker.name = 'xf_header_color';
		colorHex.name = 'xf_header_color_hex_display';
	}

	// ── Logo file validation ───────────────────────────────────────────────
	const logoInput = document.getElementById('xf_logo_upload');
	const logoError = document.getElementById('xf-logo-error');
	const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
	const MAX_SIZE = 2 * 1024 * 1024; // 2 MB

	if (logoInput && logoError) {
		logoInput.addEventListener('change', function () {
			const file = logoInput.files[0];
			logoError.style.display = 'none';
			logoError.textContent = '';

			if (!file) return;

			if (!ALLOWED_TYPES.includes(file.type)) {
				logoError.textContent = '<?php echo esc_js( __( 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.', 'xtreme-forms' ) ); ?>';
				logoError.style.display = 'block';
				logoInput.value = '';
				return;
			}

			if (file.size > MAX_SIZE) {
				logoError.textContent = '<?php echo esc_js( __( 'File is too large. Maximum size is 2 MB.', 'xtreme-forms' ) ); ?>';
				logoError.style.display = 'block';
				logoInput.value = '';
				return;
			}
		});

		// Block form submission if logo error is visible.
		document.getElementById('xf-template-form').addEventListener('submit', function (e) {
			if (logoError.style.display !== 'none' && logoError.textContent !== '') {
				e.preventDefault();
				logoError.focus();
			}
		});
	}

	// ── Send Test Email ────────────────────────────────────────────────────
	const testBtn = document.getElementById('xf-send-test-email');
	const testResult = document.getElementById('xf-test-email-result');

	if (testBtn && typeof xfAdminData !== 'undefined') {
		testBtn.addEventListener('click', function () {
			testBtn.disabled = true;
			testResult.textContent = '<?php echo esc_js( __( 'Sending…', 'xtreme-forms' ) ); ?>';
			testResult.style.color = '#6C757D';

			const fd = new FormData();
			fd.append('action', 'xf_send_test_email');
			fd.append('nonce', xfAdminData.nonce);

			fetch(xfAdminData.ajaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					testBtn.disabled = false;
					if (res.success) {
						testResult.textContent = res.data.message || '<?php echo esc_js( __( 'Test email sent.', 'xtreme-forms' ) ); ?>';
						testResult.style.color = '#28A745';
					} else {
						testResult.textContent = (res.data && res.data.message) || '<?php echo esc_js( __( 'Send failed.', 'xtreme-forms' ) ); ?>';
						testResult.style.color = '#DC3545';
					}
				})
				.catch(function () {
					testBtn.disabled = false;
					testResult.textContent = '<?php echo esc_js( __( 'Request failed.', 'xtreme-forms' ) ); ?>';
					testResult.style.color = '#DC3545';
				});
		});
	}
}());
</script>
