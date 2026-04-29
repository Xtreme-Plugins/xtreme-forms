/**
 * Xtreme Forms — Email log admin page scripts.
 *
 * Extracted from inline <script> in admin/partials/xf-admin-email-log.php
 * for WordPress.org Plugin Check compliance. Translatable strings are
 * exposed by the partial via wp_localize_script.
 */

(function () {
	'use strict';

	var i18n = window.xfEmailLogI18n || {};

	document.querySelectorAll('.xf-resend-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirm(i18n.confirmResend || '')) {
				return;
			}

			btn.disabled = true;
			btn.textContent = i18n.sending || '';

			const fd = new FormData();
			fd.append('action', 'xf_resend_email');
			fd.append('nonce', xfAdminData.nonce);
			fd.append('log_id', btn.dataset.logId);

			fetch(xfAdminData.ajaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res.success) {
						btn.textContent = i18n.sent || '';
						btn.style.color = '#28A745';
					} else {
						btn.disabled = false;
						btn.textContent = i18n.resend || '';
						alert((res.data && res.data.message) || i18n.resendFailed || '');
					}
				})
				.catch(function () {
					btn.disabled = false;
					btn.textContent = i18n.resend || '';
				});
		});
	});
}());
