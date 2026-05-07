/* global xtremeFormsAdminData, xtremeFormsEmailLogI18n */
(function () {
	'use strict';

	var i18n = window.xtremeFormsEmailLogI18n || {};

	function t( key, fallback ) {
		return ( i18n && i18n[ key ] ) || fallback;
	}

	document.querySelectorAll('.xf-resend-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirm( t( 'confirmResend', 'Resend this email to the original recipient?' ) )) {
				return;
			}

			btn.disabled = true;
			btn.textContent = t( 'sending', 'Sending…' );

			const fd = new FormData();
			fd.append('action', 'xtremeforms_resend_email');
			fd.append('nonce', xtremeFormsAdminData.nonce);
			fd.append('log_id', btn.dataset.logId);

			fetch(xtremeFormsAdminData.ajaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res.success) {
						btn.textContent = t( 'sent', 'Sent ✓' );
						btn.style.color = '#28A745';
					} else {
						btn.disabled = false;
						btn.textContent = t( 'resend', 'Resend' );
						alert((res.data && res.data.message) || t( 'resendFailed', 'Resend failed.' ));
					}
				})
				.catch(function () {
					btn.disabled = false;
					btn.textContent = t( 'resend', 'Resend' );
				});
		});
	});
}());
