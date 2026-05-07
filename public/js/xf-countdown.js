/**
 * Xtreme Forms — Countdown Timer + Scheduled Form Reveal (Sprint 6)
 *
 * Counts down to a future `activateAt` timestamp.
 * When the countdown reaches zero the timer is hidden and the live form is
 * revealed — both without a page reload.
 *
 * If JS is disabled, the static closed message is displayed and this script
 * never runs (graceful degradation handled by the PHP shortcode).
 *
 * Loaded with `defer` attribute — no render-blocking.
 *
 * @package Xtreme Forms
 */
/* global xtremeFormsCountdownData */
(function () {
	'use strict';

	var dataMap = (typeof xtremeFormsCountdownData !== 'undefined' && typeof xtremeFormsCountdownData === 'object')
		? xtremeFormsCountdownData
		: {};

	/**
	 * Pad a number to two digits.
	 * @param {number} n
	 * @returns {string}
	 */
	function pad(n) {
		return String(n).padStart(2, '0');
	}

	/**
	 * Initialise all countdown timer elements on the page.
	 */
	function initCountdowns() {
		var containers = document.querySelectorAll('.xf-countdown-wrap');
		containers.forEach(function (wrap) {
			var formId    = wrap.dataset.formId;
			var formData  = dataMap[formId];
			if (!formData) {
				return;
			}

			var activateAt  = new Date(formData.activateAt).getTime();
			var formWrap    = document.getElementById('xf-form-wrap-' + formId);
			var timerEl     = wrap.querySelector('.xf-countdown-timer');

			if (isNaN(activateAt)) {
				// Invalid timestamp — hide timer, show form if present.
				wrap.style.display = 'none';
				if (formWrap) {
					formWrap.style.display = '';
				}
				return;
			}

			var tick = function () {
				var now       = Date.now();
				var remaining = activateAt - now;

				if (remaining <= 0) {
					// Time's up: hide timer, reveal form.
					wrap.style.display = 'none';
					if (formWrap) {
						// Remove the closed message sibling (if any) and show the form container.
						var closedMsg = document.getElementById('xf-closed-msg-' + formId);
						if (closedMsg) {
							closedMsg.style.display = 'none';
						}
						formWrap.style.display = '';
					}
					return; // stop ticking.
				}

				var totalSeconds = Math.floor(remaining / 1000);
				var days    = Math.floor(totalSeconds / 86400);
				var hours   = Math.floor((totalSeconds % 86400) / 3600);
				var minutes = Math.floor((totalSeconds % 3600) / 60);
				var seconds = totalSeconds % 60;

				if (timerEl) {
					timerEl.innerHTML =
						'<span class="xf-cd-days">'    + pad(days)    + '<em>' + (formData.i18n.days    || 'd') + '</em></span>' +
						'<span class="xf-cd-hours">'   + pad(hours)   + '<em>' + (formData.i18n.hours   || 'h') + '</em></span>' +
						'<span class="xf-cd-minutes">' + pad(minutes) + '<em>' + (formData.i18n.minutes || 'm') + '</em></span>' +
						'<span class="xf-cd-seconds">' + pad(seconds) + '<em>' + (formData.i18n.seconds || 's') + '</em></span>';
				}

				setTimeout(tick, 1000);
			};

			// Start immediately.
			tick();
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initCountdowns);
	} else {
		initCountdowns();
	}
}());
