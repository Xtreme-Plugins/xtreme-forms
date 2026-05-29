/**
 * Xtreme Forms Public Form JavaScript
 * Handles: AJAX form submission, client-side validation, thank-you/redirect,
 *          impression beacon (IntersectionObserver), and UTM cookie capture.
 *
 * @package Xtreme Forms
 */
/* global xtremeformsPublicData */
(function () {
	'use strict';

	var data              = window.xtremeformsPublicData || {};
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
		var forms = document.querySelectorAll('.xf-form');
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
		this.wrap       = form.closest('.xf-form-wrap');
		this.submitBtn  = form.querySelector('.xf-btn-submit');
		this.formId     = form.querySelector('[name="xtremeforms_form_id"]')
			? parseInt(form.querySelector('[name="xtremeforms_form_id"]').value, 10)
			: 0;
		// Record render time for avg-time-to-submit metric.
		this.renderTime = Date.now();

		this._bindEvents();
		this._setupInputEnhancements();
	}

	XLForm.prototype._bindEvents = function () {
		var self = this;
		this.form.addEventListener('submit', function (e) {
			e.preventDefault();
			self._submit();
		});
	};

	/**
	 * Lightweight UX enhancements applied per-form once at init time.
	 *
	 *  - Phone fields: live-format as US-style `(XXX) XXX-XXXX` while the user
	 *    types. International / non-10-digit inputs fall through unmodified so
	 *    we never corrupt a legitimate +44/+48/etc number. The placeholder is
	 *    set to a US-style example only when the field has no other placeholder
	 *    already configured by the form admin.
	 *
	 *  - Date fields: clicking anywhere on the input opens the browser's date
	 *    picker via `showPicker()` (Chrome, Edge, modern Firefox/Safari).
	 *    Without this, the picker only opens on the small calendar icon at the
	 *    far right of the input, which most users don't realise is clickable.
	 */
	XLForm.prototype._setupInputEnhancements = function () {
		var phoneInputs = this.form.querySelectorAll('input[type="tel"]');
		phoneInputs.forEach(function (input) {
			if (!input.placeholder) {
				input.placeholder = '(555) 123-4567';
			}
			input.setAttribute('maxlength', '20');

			var formatPhone = function () {
				// Preserve everything from the very first '+' onward as an
				// international number — don't reformat it.
				if (input.value.indexOf('+') === 0) {
					return;
				}
				var digits = input.value.replace(/\D/g, '').slice(0, 10);
				var out;
				if (digits.length === 0) {
					out = '';
				} else if (digits.length < 4) {
					out = '(' + digits;
				} else if (digits.length < 7) {
					out = '(' + digits.slice(0, 3) + ') ' + digits.slice(3);
				} else {
					out = '(' + digits.slice(0, 3) + ') ' + digits.slice(3, 6) + '-' + digits.slice(6);
				}
				if (out !== input.value) {
					input.value = out;
				}
			};
			input.addEventListener('input', formatPhone);
			input.addEventListener('blur',  formatPhone);
			// Format any value that's pre-populated (browser autofill etc.).
			if (input.value) {
				formatPhone();
			}
		});

		var dateInputs = this.form.querySelectorAll('input[type="date"]');
		dateInputs.forEach(function (input) {
			// Clicking anywhere on the input opens the native picker on
			// browsers that support it. We skip when the user is interacting
			// with the calendar indicator itself (which already opens it).
			input.addEventListener('click', function () {
				if (typeof input.showPicker === 'function') {
					try { input.showPicker(); } catch (_e) { /* permission errors are fine */ }
				}
			});
			// Keyboard: Enter / Space also opens the picker.
			input.addEventListener('keydown', function (ev) {
				if ((ev.key === 'Enter' || ev.key === ' ') && typeof input.showPicker === 'function') {
					ev.preventDefault();
					try { input.showPicker(); } catch (_e) { /* ignore */ }
				}
			});
		});
	};

	XLForm.prototype._validateClientSide = function () {
		var self     = this;
		var valid    = true;
		var firstErr = null;

		this._clearErrors();

		// Check all required fields.
		this.form.querySelectorAll('.xf-field-wrap[data-required="1"]').forEach(function (wrap) {
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
			var wrap = inp.closest('.xf-field-wrap');
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
		wrap.classList.add('xf-field-error');
		var errId = 'xf-error-' + fieldId;
		var errEl = document.getElementById(errId);
		if (!errEl) {
			errEl           = document.createElement('span');
			errEl.id        = errId;
			errEl.className = 'xf-field-error-msg';
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
		this.submitBtn.classList.add('xf-submitting');

		// reCAPTCHA v3: obtain token before submitting.
		// Only loaded on pages where reCAPTCHA is enabled for this form.
		if (recaptchaEnabled && recaptchaSiteKey && window.grecaptcha) {
			window.grecaptcha.ready(function () {
				window.grecaptcha.execute(recaptchaSiteKey, { action: 'xtremeforms_submit' }).then(function (token) {
					// Inject token into hidden field.
					var tokenField = self.form.querySelector('#xf-recaptcha-token-' + self.formId);
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

		// Append xf_submit_duration: seconds from form render to submit.
		var submitDuration = Math.round( (Date.now() - this.renderTime) / 1000 );
		if (submitDuration > 0) {
			params.push('xf_submit_duration=' + submitDuration);
		}

		// Append xf_utm_cookie: read the xf_utm first-party cookie for UTM fallback.
		var utmCookieVal = '';
		var cookiePairs  = document.cookie.split(';');
		for (var ci = 0; ci < cookiePairs.length; ci++) {
			var cp = cookiePairs[ci].trim();
			if (cp.indexOf('xf_utm=') === 0) {
				try {
					utmCookieVal = decodeURIComponent(cp.substring(7));
				} catch (e) {}
				break;
			}
		}
		if (utmCookieVal) {
			params.push('xf_utm_cookie=' + encodeURIComponent(utmCookieVal));
		}

		// Note: the per-form nonce is already in the hidden xf_nonce field.

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
				action:            'xtremeforms_do_form_redirect',
				xf_form_id:        String(responseData.form_id || this.formId),
				xf_redirect_nonce: responseData.redirect_nonce
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
		thankYou.className = 'xf-thank-you';
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
				var errId = 'xf-error-' + fieldId;

				// Prefer data-field-id attribute on wrapper (most reliable).
				var wrap = self.form.querySelector('[data-field-id="' + fieldId + '"]');

				if (!wrap) {
					// Fallback: find input by name (covers text/email/phone/textarea/select/radio).
					var inp = self.form.querySelector(
						'[name="xf_field[' + fieldId + ']"], ' +
						'[name="xf_field[' + fieldId + '][]"]'
					);
					if (inp) {
						wrap = inp.closest('.xf-field-wrap');
					}
				}

				if (!wrap) {
					// Last resort: find by pre-rendered error element ID.
					var existingErr = document.getElementById(errId);
					if (existingErr) {
						wrap = existingErr.closest('.xf-field-wrap');
					}
				}

				if (wrap) {
					wrap.classList.add('xf-field-error');

					var errEl = document.getElementById(errId);
					if (!errEl) {
						errEl = document.createElement('span');
						errEl.id        = errId;
						errEl.className = 'xf-field-error-msg';
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
		var existing = this.wrap.querySelector('.xf-form-global-error');
		if (existing) existing.remove();

		var el = document.createElement('div');
		el.className = 'xf-form-global-error';
		el.setAttribute('role', 'alert');
		el.textContent = msg;
		this.form.insertAdjacentElement('beforebegin', el);
	};

	XLForm.prototype._clearErrors = function () {
		// Remove field errors.
		this.form.querySelectorAll('.xf-field-error').forEach(function (el) {
			el.classList.remove('xf-field-error');
		});
		this.form.querySelectorAll('.xf-field-error-msg').forEach(function (el) {
			el.textContent = '';
		});

		// Remove global error.
		var global = this.wrap.querySelector('.xf-form-global-error');
		if (global) global.remove();
	};

	XLForm.prototype._enableSubmit = function () {
		this.submitBtn.disabled    = false;
		this.submitBtn.textContent = i18n.submit || 'Submit';
		this.submitBtn.classList.remove('xf-submitting');
	};

	XLForm.prototype._escapeHtml = function (str) {
		var d = document.createElement('div');
		d.appendChild(document.createTextNode(String(str)));
		return d.innerHTML;
	};

	// ── UTM Cookie Capture ───────────────────────────────────────────────────
	//
	// Reads UTM parameters from the current page URL and stores them in a
	// first-party cookie (xf_utm) so that the form submission handler can send
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
				document.cookie = 'xf_utm=' + encodeURIComponent(JSON.stringify(utmData)) +
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
				var beaconBody = 'action=xtremeforms_track_impression' +
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
					fd.append('action',  'xtremeforms_track_impression');
					fd.append('nonce',   impressionNonce);
					fd.append('form_id', String(formId));
					fd.append('post_id', String(postId));
					fetch(ajaxUrl, {method: 'POST', body: fd, keepalive: true}).catch(function () {});
				}
			});
		}, {threshold: 0.5});

		// Observe every form wrapper currently in the DOM.
		var wraps = document.querySelectorAll('.xf-form-wrap[data-form-id]');
		wraps.forEach(function (wrap) {
			observer.observe(wrap);
		});
	}

	// ── Init ─────────────────────────────────────────────────────────────────

	/**
	 * Initialise every slider field: live-update the value bubble + fill gradient.
	 */
	function initSliders() {
		var sliders = document.querySelectorAll('[data-xf-slider]');
		sliders.forEach(function (wrap) {
			var input  = wrap.querySelector('[data-xf-slider-input]');
			var bubble = wrap.querySelector('[data-xf-slider-value]');
			if (!input) return;

			var update = function () {
				var min  = parseFloat(input.min)  || 0;
				var max  = parseFloat(input.max)  || 100;
				var val  = parseFloat(input.value);
				if (isNaN(val)) val = min;
				var pct  = max > min ? ((val - min) / (max - min)) * 100 : 0;
				input.style.setProperty('--xf-slider-fill', pct + '%');
				if (bubble) bubble.textContent = input.value;
			};

			update();
			input.addEventListener('input',  update);
			input.addEventListener('change', update);
		});
	}

	/**
	 * Quantity-mode multiple choice.
	 * Each row has: checkbox + label + stepper (− value +) + hidden input.
	 * Toggling the checkbox shows/hides the stepper and enables/disables the
	 * hidden input. Decrementing past 1 unchecks the row. Incrementing has no
	 * upper bound (form authors can constrain visually if needed).
	 */
	function initQtyRows() {
		var rows = document.querySelectorAll('[data-xf-qty-row]');
		rows.forEach(function (row) {
			var cb      = row.querySelector('[data-xf-qty-cb]');
			var stepper = row.querySelector('[data-xf-qty-stepper]');
			var valEl   = row.querySelector('[data-xf-qty-val]');
			var dec     = row.querySelector('[data-xf-qty-dec]');
			var inc     = row.querySelector('[data-xf-qty-inc]');
			var hidden  = row.querySelector('[data-xf-qty-input]');
			if (!cb || !stepper || !valEl || !dec || !inc || !hidden) return;

			function setChecked(checked, qty) {
				if (typeof qty !== 'number' || qty < 1) qty = 1;
				cb.checked = checked;
				if (checked) {
					row.classList.add('is-checked');
					stepper.hidden = false;
					hidden.disabled = false;
					hidden.value = qty;
					valEl.textContent = qty;
				} else {
					row.classList.remove('is-checked');
					stepper.hidden = true;
					hidden.disabled = true;
					hidden.value = 1;
					valEl.textContent = 1;
				}
			}

			cb.addEventListener('change', function () {
				setChecked(cb.checked, 1);
			});

			inc.addEventListener('click', function () {
				var n = parseInt(hidden.value, 10);
				if (isNaN(n) || n < 1) n = 1;
				n += 1;
				hidden.value = n;
				valEl.textContent = n;
			});

			dec.addEventListener('click', function () {
				var n = parseInt(hidden.value, 10);
				if (isNaN(n) || n < 1) n = 1;
				if (n <= 1) {
					setChecked(false);
				} else {
					n -= 1;
					hidden.value = n;
					valEl.textContent = n;
				}
			});
		});
	}

	function xtremeformsPublicInit() {
		initForms();
		initSliders();
		initQtyRows();
		initUtmCookie();
		initImpressionTracking();
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', xtremeformsPublicInit);
	} else {
		xtremeformsPublicInit();
	}

})();
