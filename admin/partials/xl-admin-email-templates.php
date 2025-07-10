<?php
/**
 * Email Templates admin page.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

$template = XL_Email_Templates::get_template();
$notice = '';

if ( ! empty( $_GET['updated'] ) ) {
	$notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Email template settings saved.', 'xtremeleads' ) . '</p></div>';
}

if ( ! empty( $_GET['test_sent'] ) ) {
	$notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Test email sent successfully.', 'xtremeleads' ) . '</p></div>';
}

if ( ! empty( $_GET['test_failed'] ) ) {
	$notice = '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Test email failed to send. Check your WordPress email configuration.', 'xtremeleads' ) . '</p></div>';
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
<div class="wrap xl-wrap">
	<h1 class="xl-page-title"><?php esc_html_e( 'Email Templates', 'xtremeleads' ); ?></h1>

	<?php echo wp_kses_post( $notice ); ?>

	<div class="xl-two-col-layout">
		<!-- ── Editor ────────────────────────────────────────────────────── -->
		<div class="xl-main-col">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" id="xl-template-form">
				<input type="hidden" name="action" value="xl_save_email_template">
				<?php wp_nonce_field( 'xl_save_email_template' ); ?>

				<div class="xl-settings-card">
					<h2><?php esc_html_e( 'Template Design', 'xtremeleads' ); ?></h2>

					<table class="form-table" role="presentation">
						<tbody>
							<!-- Logo Upload -->
							<tr>
								<th scope="row">
									<label for="xl_logo_upload"><?php esc_html_e( 'Logo', 'xtremeleads' ); ?></label>
								</th>
								<td>
									<?php if ( $template['logo_url'] ) : ?>
										<div class="xl-logo-preview" style="margin-bottom:12px;">
											<img src="<?php echo esc_url( $template['logo_url'] ); ?>" alt="<?php esc_attr_e( 'Current logo', 'xtremeleads' ); ?>" style="max-height:60px;max-width:240px;display:block;border:1px solid #DEE2E6;padding:4px;border-radius:4px;">
										</div>
									<?php endif; ?>

									<input type="file" id="xl_logo_upload" name="xl_logo_file" accept="image/jpeg,image/png,image/gif,image/webp" style="margin-bottom:8px;display:block;">
									<input type="hidden" name="xl_logo_url" id="xl_logo_url" value="<?php echo esc_attr( $template['logo_url'] ); ?>">

									<p class="description">
										<?php esc_html_e( 'Accepted: JPEG, PNG, GIF, WebP. Maximum size: 2 MB. Leave blank to use no logo.', 'xtremeleads' ); ?>
									</p>

									<?php if ( $template['logo_url'] ) : ?>
										<label class="xl-checkbox-inline" style="margin-top:8px;display:block;">
											<input type="checkbox" name="xl_remove_logo" value="1">
											<?php esc_html_e( 'Remove current logo', 'xtremeleads' ); ?>
										</label>
									<?php endif; ?>

									<div id="xl-logo-error" class="xl-field-error" style="display:none;color:#DC3545;margin-top:4px;"></div>
								</td>
							</tr>

							<!-- Header Color -->
							<tr>
								<th scope="row">
									<label for="xl_header_color"><?php esc_html_e( 'Header Background Color', 'xtremeleads' ); ?></label>
								</th>
								<td>
									<div style="display:flex;align-items:center;gap:12px;">
										<input type="color" id="xl_header_color" name="xl_header_color" value="<?php echo esc_attr( $template['header_color'] ); ?>" style="width:48px;height:36px;padding:2px;border-radius:4px;border:1px solid #DEE2E6;cursor:pointer;">
										<input type="text" id="xl_header_color_hex" name="xl_header_color_hex_display" value="<?php echo esc_attr( $template['header_color'] ); ?>" maxlength="7" style="width:100px;" placeholder="#1A73E8" pattern="#[0-9A-Fa-f]{6}" title="<?php esc_attr_e( 'Hex color value, e.g. #1A73E8', 'xtremeleads' ); ?>">
									</div>
									<p class="description"><?php esc_html_e( 'Default: Electric Blue (#1A73E8)', 'xtremeleads' ); ?></p>
								</td>
							</tr>

							<!-- Email Subject -->
							<tr>
								<th scope="row">
									<label for="xl_subject"><?php esc_html_e( 'Email Subject', 'xtremeleads' ); ?></label>
								</th>
								<td>
									<input type="text" id="xl_subject" name="xl_subject" value="<?php echo esc_attr( $template['subject'] ); ?>" class="large-text">
									<p class="description"><?php esc_html_e( 'Merge tags supported.', 'xtremeleads' ); ?></p>
								</td>
							</tr>

							<!-- Body Text -->
							<tr>
								<th scope="row">
									<label for="xl_body_text"><?php esc_html_e( 'Body / Intro Text', 'xtremeleads' ); ?></label>
								</th>
								<td>
									<textarea id="xl_body_text" name="xl_body_text" rows="4" class="large-text"><?php echo esc_textarea( $template['body_text'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Introduction paragraph shown above the submitted field values. Merge tags supported.', 'xtremeleads' ); ?></p>
								</td>
							</tr>

							<!-- Footer Text -->
							<tr>
								<th scope="row">
									<label for="xl_footer_text"><?php esc_html_e( 'Footer Text', 'xtremeleads' ); ?></label>
								</th>
								<td>
									<textarea id="xl_footer_text" name="xl_footer_text" rows="3" class="large-text"><?php echo esc_textarea( $template['footer_text'] ); ?></textarea>
									<p class="description"><?php esc_html_e( 'Footer shown at the bottom of all notification emails.', 'xtremeleads' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>

				<!-- Merge Tags Reference -->
				<div class="xl-settings-card">
					<h2><?php esc_html_e( 'Available Merge Tags', 'xtremeleads' ); ?></h2>
					<p class="description"><?php printf( esc_html__( 'Use these tags in the subject, body, and footer fields: %s', 'xtremeleads' ), wp_kses_post( $merge_tags_desc ) ); ?></p>
					<table class="widefat striped" style="margin-top:12px;">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Tag', 'xtremeleads' ); ?></th>
								<th><?php esc_html_e( 'Description', 'xtremeleads' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<tr><td><code>{{lead_name}}</code></td><td><?php esc_html_e( 'Lead\'s detected name from form fields', 'xtremeleads' ); ?></td></tr>
							<tr><td><code>{{lead_email}}</code></td><td><?php esc_html_e( 'Lead\'s email address', 'xtremeleads' ); ?></td></tr>
							<tr><td><code>{{lead_phone}}</code></td><td><?php esc_html_e( 'Lead\'s phone number', 'xtremeleads' ); ?></td></tr>
							<tr><td><code>{{form_name}}</code></td><td><?php esc_html_e( 'Name of the form submitted', 'xtremeleads' ); ?></td></tr>
							<tr><td><code>{{site_name}}</code></td><td><?php esc_html_e( 'Your WordPress site name', 'xtremeleads' ); ?></td></tr>
							<tr><td><code>{{site_url}}</code></td><td><?php esc_html_e( 'Your WordPress site URL', 'xtremeleads' ); ?></td></tr>
							<tr><td><code>{{submission_date}}</code></td><td><?php esc_html_e( 'Date and time of the submission', 'xtremeleads' ); ?></td></tr>
							<tr><td><code>{{source_url}}</code></td><td><?php esc_html_e( 'URL of the page where the form was submitted', 'xtremeleads' ); ?></td></tr>
							<tr><td><code>{{lead_id}}</code></td><td><?php esc_html_e( 'Internal lead ID', 'xtremeleads' ); ?></td></tr>
						</tbody>
					</table>
				</div>

				<div class="xl-form-actions">
					<?php submit_button( __( 'Save Template', 'xtremeleads' ), 'primary xl-btn-primary', 'submit', false ); ?>
					<button type="button" id="xl-send-test-email" class="button xl-btn-secondary" style="margin-left:8px;">
						<?php esc_html_e( 'Send Test Email', 'xtremeleads' ); ?>
					</button>
					<span id="xl-test-email-result" style="margin-left:12px;font-style:italic;"></span>
				</div>
			</form>
		</div>
	</div>
</div>

<script>
(function () {
	'use strict';

	// ── Color picker sync ──────────────────────────────────────────────────
	const colorPicker = document.getElementById('xl_header_color');
	const colorHex = document.getElementById('xl_header_color_hex');

	if (colorPicker && colorHex) {
		colorPicker.addEventListener('input', function () {
			colorHex.value = colorPicker.value.toUpperCase();
		});
		colorHex.addEventListener('input', function () {
			const val = colorHex.value.trim();
			if (/^#[0-9A-Fa-f]{6}$/.test(val)) {
				colorPicker.value = val;
				// Update actual hidden input used by form.
				document.querySelector('[name="xl_header_color"]').value = val;
			}
		});
		// Sync the actual POST field name.
		colorPicker.name = 'xl_header_color';
		colorHex.name = 'xl_header_color_hex_display';
	}

	// ── Logo file validation ───────────────────────────────────────────────
	const logoInput = document.getElementById('xl_logo_upload');
	const logoError = document.getElementById('xl-logo-error');
	const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
	const MAX_SIZE = 2 * 1024 * 1024; // 2 MB

	if (logoInput && logoError) {
		logoInput.addEventListener('change', function () {
			const file = logoInput.files[0];
			logoError.style.display = 'none';
			logoError.textContent = '';

			if (!file) return;

			if (!ALLOWED_TYPES.includes(file.type)) {
				logoError.textContent = '<?php echo esc_js( __( 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.', 'xtremeleads' ) ); ?>';
				logoError.style.display = 'block';
				logoInput.value = '';
				return;
			}

			if (file.size > MAX_SIZE) {
				logoError.textContent = '<?php echo esc_js( __( 'File is too large. Maximum size is 2 MB.', 'xtremeleads' ) ); ?>';
				logoError.style.display = 'block';
				logoInput.value = '';
				return;
			}
		});

		// Block form submission if logo error is visible.
		document.getElementById('xl-template-form').addEventListener('submit', function (e) {
			if (logoError.style.display !== 'none' && logoError.textContent !== '') {
				e.preventDefault();
				logoError.focus();
			}
		});
	}

	// ── Send Test Email ────────────────────────────────────────────────────
	const testBtn = document.getElementById('xl-send-test-email');
	const testResult = document.getElementById('xl-test-email-result');

	if (testBtn && typeof xlAdminData !== 'undefined') {
		testBtn.addEventListener('click', function () {
			testBtn.disabled = true;
			testResult.textContent = '<?php echo esc_js( __( 'Sending…', 'xtremeleads' ) ); ?>';
			testResult.style.color = '#6C757D';

			const fd = new FormData();
			fd.append('action', 'xl_send_test_email');
			fd.append('nonce', xlAdminData.nonce);

			fetch(xlAdminData.ajaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					testBtn.disabled = false;
					if (res.success) {
						testResult.textContent = res.data.message || '<?php echo esc_js( __( 'Test email sent.', 'xtremeleads' ) ); ?>';
						testResult.style.color = '#28A745';
					} else {
						testResult.textContent = (res.data && res.data.message) || '<?php echo esc_js( __( 'Send failed.', 'xtremeleads' ) ); ?>';
						testResult.style.color = '#DC3545';
					}
				})
				.catch(function () {
					testBtn.disabled = false;
					testResult.textContent = '<?php echo esc_js( __( 'Request failed.', 'xtremeleads' ) ); ?>';
					testResult.style.color = '#DC3545';
				});
		});
	}
}());
</script>
