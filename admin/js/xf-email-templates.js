/* global xtremeFormsAdminData, xtremeFormsEmailTemplatesI18n */
(function () {
	'use strict';

	function i18n( key, fallback ) {
		return ( window.xtremeFormsEmailTemplatesI18n && xtremeFormsEmailTemplatesI18n[ key ] ) || fallback;
	}

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
				logoError.textContent = i18n( 'invalidFileType', 'Invalid file type. Please upload a JPEG, PNG, GIF, or WebP image.' );
				logoError.style.display = 'block';
				logoInput.value = '';
				return;
			}

			if (file.size > MAX_SIZE) {
				logoError.textContent = i18n( 'fileTooLarge', 'File is too large. Maximum size is 2 MB.' );
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

	if (testBtn && typeof xtremeFormsAdminData !== 'undefined') {
		testBtn.addEventListener('click', function () {
			testBtn.disabled = true;
			testResult.textContent = i18n( 'sending', 'Sending…' );
			testResult.style.color = '#6C757D';

			const fd = new FormData();
			fd.append('action', 'xtremeforms_send_test_email');
			fd.append('nonce', xtremeFormsAdminData.nonce);

			fetch(xtremeFormsAdminData.ajaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					testBtn.disabled = false;
					if (res.success) {
						testResult.textContent = res.data.message || i18n( 'testEmailSent', 'Test email sent.' );
						testResult.style.color = '#28A745';
					} else {
						testResult.textContent = (res.data && res.data.message) || i18n( 'sendFailed', 'Send failed.' );
						testResult.style.color = '#DC3545';
					}
				})
				.catch(function () {
					testBtn.disabled = false;
					testResult.textContent = i18n( 'requestFailed', 'Request failed.' );
					testResult.style.color = '#DC3545';
				});
		});
	}
}());
