/**
 * Xtreme Forms — Email templates admin page scripts.
 *
 * Extracted from inline <script> in admin/partials/xf-admin-email-templates.php
 * for WordPress.org Plugin Check compliance. Per-render translatable strings
 * are exposed by the partial via wp_localize_script.
 */

(function () {
	'use strict';

	var i18n = window.xfEmailTemplatesI18n || {};

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
				logoError.textContent = i18n.invalidFileType || '';
				logoError.style.display = 'block';
				logoInput.value = '';
				return;
			}

			if (file.size > MAX_SIZE) {
				logoError.textContent = i18n.fileTooLarge || '';
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
			testResult.textContent = i18n.sending || '';
			testResult.style.color = '#6C757D';

			const fd = new FormData();
			fd.append('action', 'xf_send_test_email');
			fd.append('nonce', xfAdminData.nonce);

			fetch(xfAdminData.ajaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					testBtn.disabled = false;
					if (res.success) {
						testResult.textContent = res.data.message || i18n.testEmailSent || '';
						testResult.style.color = '#28A745';
					} else {
						testResult.textContent = (res.data && res.data.message) || i18n.sendFailed || '';
						testResult.style.color = '#DC3545';
					}
				})
				.catch(function () {
					testBtn.disabled = false;
					testResult.textContent = i18n.requestFailed || '';
					testResult.style.color = '#DC3545';
				});
		});
	}
}());
