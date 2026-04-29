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
			replyToErr.textContent = (window.xfFormSettingsI18n && xfFormSettingsI18n.invalidReplyTo) || 'Please enter a valid reply-to email address.';
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
