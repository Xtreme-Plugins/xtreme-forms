/**
 * XtremeLeads Public Form JavaScript
 * Handles: AJAX form submission, client-side validation, thank-you/redirect,
 *          impression beacon (IntersectionObserver), and UTM cookie capture.
 *
 * @package XtremeLeads
 */
/* global xlPublicData */
(function () {
	'use strict';

	var data              = window.xlPublicData || {};
	var ajaxUrl           = data.ajaxUrl          || '';
	var nonce             = data.nonce            || '';
	var impressionNonce   = data.impressionNonce  || '';
	var postId            = parseInt( data.postId || '0', 10 );
	var i18n              = data.i18n             || {};
	var recaptchaEnabled  = data.recaptchaEnabled === '1';
	var recaptchaSiteKey  = data.recaptchaSiteKey || '';

	/**
	 * Initialize all forms on the page.
	 */
	function initForms() {
		var forms = document.querySelectorAll('.xl-form');
		forms.forEach(function (form) {
			new XLForm(form);
		});
	}

	/**
	 * XLForm constructor — manages a single form instance.
	 *
	 * @param {HTMLFormElement} form
	 */
	function XLForm(form) {
		this.form       = form;
		this.wrap       = form.closest('.xl-form-wrap');
		this.submitBtn  = form.querySelector('.xl-btn-submit');
		this.formId     = form.querySelector('[name="xl_form_id"]')
			? parseInt(form.querySelector('[name="xl_form_id"]').value, 10)
			: 0;
		// Record render time for avg-time-to-submit metric.
		this.renderTime = Date.now();

		this._bindEvents();
	}

	XLForm.prototype._bindEvents = function () {
		var self = this;
		this.form.addEventListener('submit', function (e) {
			e.preventDefault();
			self._submit();
		});
	};

	XLForm.prototype._validateClientSide = function () {
		var self     = this;
		var valid    = true;
		var firstErr = null;

		this._clearErrors();

		// Check all required fields.
		this.form.querySelectorAll('.xl-field-wrap[data-required="1"]').forEach(function (wrap) {
			var fieldId  = wrap.dataset.fieldId;
			var isEmpty  = false;

			// Checkbox group: at least one must be checked.
			var checkboxes = wrap.querySelectorAll('input[type="checkbox"]');
			if (checkboxes.length > 0) {
				isEmpty = !wrap.querySelector('input[type="checkbox"]:checked');
			} else {
				// Radio group: at least one must be selected.
				var radios = wrap.querySelectorAll('input[type="radio"]');
				if (radios.length > 0) {
					isEmpty = !wrap.querySelector('input[type="radio"]:checked');
				} else {
					var inp = wrap.querySelector('input:not([type="hidden"]), textarea, select');
					isEmpty = !inp || inp.value.trim() === '';
				}
			}

			if (isEmpty) {
				valid = false;
				self._showFieldErrorEl(wrap, fieldId, i18n.fieldRequired || 'This field is required.');
				if (!firstErr) { firstErr = wrap; }
			}
		});

		// Email format validation.
		this.form.querySelectorAll('input[type="email"]').forEach(function (inp) {
			var val = inp.value.trim();
			if (!val) { return; } // Already caught by required check.
			var wrap = inp.closest('.xl-field-wrap');
			if (!wrap) { return; }
			var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
			if (!emailRegex.test(val)) {
				valid = false;
				var fieldId = wrap.dataset.fieldId;
				self._showFieldErrorEl(wrap, fieldId, i18n.invalidEmail || 'Please enter a valid email address.');
				if (!firstErr) { firstErr = wrap; }
			}
		});

		if (firstErr) {
			firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
			var firstInp = firstErr.querySelector('input:not([type="hidden"]), textarea, select');
			if (firstInp) { firstInp.focus(); }
		}

		return valid;
	};

	XLForm.prototype._showFieldErrorEl = function (wrap, fieldId, msg) {
		wrap.classList.add('xl-field-error');
		var errId = 'xl-error-' + fieldId;
		var errEl = document.getElementById(errId);
		if (!errEl) {
			errEl           = document.createElement('span');
			errEl.id        = errId;
			errEl.className = 'xl-field-error-msg';
			errEl.setAttribute('role', 'alert');
			wrap.appendChild(errEl);
		}
		errEl.textContent = msg;
		// Accessibility: link input to error.
		var inp = wrap.querySelector('input:not([type="hidden"]), textarea, select');
		if (inp && !inp.getAttribute('aria-describedby')) {
			inp.setAttribute('aria-describedby', errId);
		}
	};

	XLForm.prototype._submit = function () {
		var self = this;

		// Client-side validation before AJAX (HTML5 required + format checks).
		if (!this._validateClientSide()) {
			return;
		}

		// Disable submit.
		this.submitBtn.disabled = true;
		this.submitBtn.textContent = i18n.submitting || 'Submitting…';
		this.submitBtn.classList.add('xl-submitting');

		// reCAPTCHA v3: obtain token before submitting.
		// Only loaded on pages where reCAPTCHA is enabled for this form.
		if (recaptchaEnabled && recaptchaSiteKey && window.grecaptcha) {
			window.grecaptcha.ready(function () {
				window.grecaptcha.execute(recaptchaSiteKey, { action: 'xl_submit' }).then(function (token) {
					// Inject token into hidden field.
					var tokenField = self.form.querySelector('#xl-recaptcha-token-' + self.formId);
					if (tokenField) {
						tokenField.value = token;
					}
					self._doSend();
				}).catch(function () {
					// Token fetch failed — submit anyway (server will handle).
					self._doSend();
				});
			});
		} else {
			self._doSend();
		}
	};

	XLForm.prototype._doSend = function () {
		var self = this;

		// Build form data.
		var formData = new FormData(this.form);

		// Convert to URL-encoded string for XHR.
		var params = [];
		formData.forEach(function (value, key) {
			params.push(encodeURIComponent(key) + '=' + encodeURIComponent(value));
		});

		// Append xl_submit_duration: seconds from form render to submit.
		var submitDuration = Math.round( (Date.now() - this.renderTime) / 1000 );
		if (submitDuration > 0) {
			params.push('xl_submit_duration=' + submitDuration);
		}

		// Append xl_utm_cookie: read the xl_utm first-party cookie for UTM fallback.
		var utmCookieVal = '';
		var cookiePairs  = document.cookie.split(';');
		for (var ci = 0; ci < cookiePairs.length; ci++) {
			var cp = cookiePairs[ci].trim();
			if (cp.indexOf('xl_utm=') === 0) {
				try {
					utmCookieVal = decodeURIComponent(cp.substring(7));
				} catch (e) {}
				break;
			}
		}
		if (utmCookieVal) {
			params.push('xl_utm_cookie=' + encodeURIComponent(utmCookieVal));
		}

		// Note: the per-form nonce is already in the hidden xl_nonce field.

		var xhr = new XMLHttpRequest();
		xhr.open('POST', ajaxUrl, true);
		xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');

		xhr.onload = function () {
			self._enableSubmit();
			try {
				var res = JSON.parse(xhr.responseText);
				if (res.success) {
					self._handleSuccess(res.data);
				} else {
					self._handleError(res.data);
				}
			} catch (e) {
				self._showGlobalError(i18n.errorGeneric || 'Something went wrong. Please try again.');
			}
		};

		xhr.onerror = function () {
			self._enableSubmit();
			self._showGlobalError(i18n.errorGeneric || 'Something went wrong. Please try again.');
		};

		xhr.send(params.join('&'));
	}; // end _doSend

	XLForm.prototype._handleSuccess = function (responseData) {
		if (responseData.redirect_url && responseData.redirect_nonce) {
			// Server-side redirect via wp_safe_redirect() — avoids bypassing WordPress URL validation.
			var redirectForm = document.createElement('form');
			redirectForm.method = 'POST';
			redirectForm.action = ajaxUrl;
			redirectForm.style.display = 'none';

			var redirectFields = {
				action:            'xl_do_form_redirect',
				xl_form_id:        String(responseData.form_id || this.formId),
				xl_redirect_nonce: responseData.redirect_nonce
			};

			var redirectInputKeys = Object.keys(redirectFields);
			var redirectData = redirectFields;
			redirectInputKeys.forEach(function (key) {
				var input   = document.createElement('input');
				input.type  = 'hidden';
				input.name  = key;
				input.value = redirectData[key];
				redirectForm.appendChild(input);
			});

			document.body.appendChild(redirectForm);
			redirectForm.submit();
			return;
		}

		// Show inline thank-you (replace form).
		var thankYou = document.createElement('div');
		thankYou.className = 'xl-thank-you';
		thankYou.setAttribute('role', 'status');
		thankYou.innerHTML = '<p>' + this._escapeHtml(responseData.thank_you || 'Thank you! Your submission has been received.') + '</p>';

		this.wrap.innerHTML = '';
		this.wrap.appendChild(thankYou);

		// Scroll thank-you into view.
		thankYou.scrollIntoView({ behavior: 'smooth', block: 'center' });
	};

	XLForm.prototype._handleError = function (errorData) {
		if (!errorData) {
			this._showGlobalError(i18n.errorGeneric || 'Something went wrong. Please try again.');
			return;
		}

		// Field-level errors.
		if (errorData.errors && typeof errorData.errors === 'object') {
			var self         = this;
			var firstErrEl   = null;

			Object.keys(errorData.errors).forEach(function (fieldId) {
				var msg   = errorData.errors[fieldId];
				var errId = 'xl-error-' + fieldId;

				// Prefer data-field-id attribute on wrapper (most reliable).
				var wrap = self.form.querySelector('[data-field-id="' + fieldId + '"]');

				if (!wrap) {
					// Fallback: find input by name (covers text/email/phone/textarea/select/radio).
					var inp = self.form.querySelector(
						'[name="xl_field[' + fieldId + ']"], ' +
						'[name="xl_field[' + fieldId + '][]"]'
					);
					if (inp) {
						wrap = inp.closest('.xl-field-wrap');
					}
				}

				if (!wrap) {
					// Last resort: find by pre-rendered error element ID.
					var existingErr = document.getElementById(errId);
					if (existingErr) {
						wrap = existingErr.closest('.xl-field-wrap');
					}
				}

				if (wrap) {
					wrap.classList.add('xl-field-error');

					var errEl = document.getElementById(errId);
					if (!errEl) {
						errEl = document.createElement('span');
						errEl.id        = errId;
						errEl.className = 'xl-field-error-msg';
						errEl.setAttribute('role', 'alert');
						wrap.appendChild(errEl);
					}
					errEl.textContent = msg;

					// Link input to error for accessibility.
					var inp = wrap.querySelector('input:not([type="hidden"]), textarea, select');
					if (inp && !inp.getAttribute('aria-describedby')) {
						inp.setAttribute('aria-describedby', errId);
					}

					if (!firstErrEl) firstErrEl = wrap;
				}
			});

			if (firstErrEl) {
				firstErrEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
				var inp2 = firstErrEl.querySelector('input:not([type="hidden"]), textarea, select');
				if (inp2) inp2.focus();
			}
		}

		// Show global message.
		if (errorData.message) {
			this._showGlobalError(errorData.message);
		}
	};

	XLForm.prototype._showGlobalError = function (msg) {
		var existing = this.wrap.querySelector('.xl-form-global-error');
		if (existing) existing.remove();

		var el = document.createElement('div');
		el.className = 'xl-form-global-error';
		el.setAttribute('role', 'alert');
		el.textContent = msg;
		this.form.insertAdjacentElement('beforebegin', el);
	};

	XLForm.prototype._clearErrors = function () {
		// Remove field errors.
		this.form.querySelectorAll('.xl-field-error').forEach(function (el) {
			el.classList.remove('xl-field-error');
		});
		this.form.querySelectorAll('.xl-field-error-msg').forEach(function (el) {
			el.textContent = '';
		});

		// Remove global error.
		var global = this.wrap.querySelector('.xl-form-global-error');
		if (global) global.remove();
	};

	XLForm.prototype._enableSubmit = function () {
		this.submitBtn.disabled    = false;
		this.submitBtn.textContent = i18n.submit || 'Submit';
		this.submitBtn.classList.remove('xl-submitting');
	};

	XLForm.prototype._escapeHtml = function (str) {
		var d = document.createElement('div');
		d.appendChild(document.createTextNode(String(str)));
		return d.innerHTML;
	};

	// ── UTM Cookie Capture ───────────────────────────────────────────────────
	//
	// Reads UTM parameters from the current page URL and stores them in a
	// first-party cookie (xl_utm) so that the form submission handler can send
	// them as a fallback when the form is submitted from a page without UTMs
	// (e.g., a landing page UTM captured here, form on a different page).

	function initUtmCookie() {
		var utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
		var params  = new URLSearchParams(window.location.search);
		var utmData = {};
		var hasUtm  = false;

		utmKeys.forEach(function (key) {
			var val = params.get(key);
			if (val) {
				utmData[key] = val;
				hasUtm = true;
			}
		});

		if (hasUtm) {
			// Store for 30 days so UTMs from the landing page persist to the form page.
			var expires = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toUTCString();
			try {
				document.cookie = 'xl_utm=' + encodeURIComponent(JSON.stringify(utmData)) +
					'; expires=' + expires + '; path=/; SameSite=Lax';
			} catch (e) {}
		}
	}

	// ── Impression Beacon ─────────────────────────────────────────────────────
	//
	// Uses IntersectionObserver (threshold ≥ 50%) to detect when a form enters
	// the visitor's viewport.  Fires at most ONE impression per form per page
	// load via a non-blocking sendBeacon (or fetch + keepalive:true as fallback).
	// Does NOT use jQuery and does NOT delay DOMContentLoaded.

	function initImpressionTracking() {
		if (!ajaxUrl || !impressionNonce) { return; }
		if (!('IntersectionObserver' in window)) { return; }

		// Track which form IDs have already fired an impression this page load.
		var firedForms = {};

		var observer = new IntersectionObserver(function (entries) {
			entries.forEach(function (entry) {
				// Only fire when the form is at least 50% visible.
				if (!entry.isIntersecting || entry.intersectionRatio < 0.5) { return; }

				var wrap   = entry.target;
				var formId = parseInt(wrap.dataset.formId || '0', 10);

				if (!formId || firedForms[formId]) { return; }

				// Mark as fired immediately to prevent double-beaconing.
				firedForms[formId] = true;
				// Unobserve so future scroll events don't re-trigger.
				observer.unobserve(wrap);

				// Build the POST body as application/x-www-form-urlencoded.
				var beaconBody = 'action=xl_track_impression' +
					'&nonce='   + encodeURIComponent(impressionNonce) +
					'&form_id=' + encodeURIComponent(String(formId)) +
					'&post_id=' + encodeURIComponent(String(postId));

				// Prefer sendBeacon (truly fire-and-forget, survives page unload).
				if (navigator.sendBeacon) {
					// Wrap in a Blob so the browser sends Content-Type:
					// application/x-www-form-urlencoded — required by admin-ajax.php.
					var blob = new Blob([beaconBody], {type: 'application/x-www-form-urlencoded'});
					navigator.sendBeacon(ajaxUrl, blob);
				} else {
					// Fallback: fetch with keepalive:true (non-blocking).
					var fd = new FormData();
					fd.append('action',  'xl_track_impression');
					fd.append('nonce',   impressionNonce);
					fd.append('form_id', String(formId));
					fd.append('post_id', String(postId));
					fetch(ajaxUrl, {method: 'POST', body: fd, keepalive: true}).catch(function () {});
				}
			});
		}, {threshold: 0.5});

		// Observe every form wrapper currently in the DOM.
		var wraps = document.querySelectorAll('.xl-form-wrap[data-form-id]');
		wraps.forEach(function (wrap) {
			observer.observe(wrap);
		});
	}

	// ── Init ─────────────────────────────────────────────────────────────────

	function xlPublicInit() {
		initForms();
		initUtmCookie();
		initImpressionTracking();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', xlPublicInit);
	} else {
		xlPublicInit();
	}

})();
