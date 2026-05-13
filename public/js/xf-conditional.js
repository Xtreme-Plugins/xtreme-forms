/**
 * Xtreme Forms — Conditional Logic Engine (Sprint 6)
 *
 * Evaluates per-field visibility rules in real time.
 * Runs with `defer` (no blocking). Zero jQuery dependency.
 *
 * Rule schema (stored in form field JSON, passed via xtremeformsCondLogicData):
 * {
 *   fieldId: "field_abc",          // target field to show/hide
 *   logic:   "and"|"or",
 *   conditions: [
 *     { triggerFieldId: "field_xyz", operator: "equals"|"not_equals"|"contains", value: "somevalue" },
 *     ...
 *   ]
 * }
 *
 * @package Xtreme Forms
 */
/* global xtremeformsCondLogicData */
(function () {
	'use strict';

	var rules = (typeof xtremeformsCondLogicData !== 'undefined' && Array.isArray(xtremeformsCondLogicData.rules))
		? xtremeformsCondLogicData.rules
		: [];

	if (!rules.length) {
		return;
	}

	/**
	 * Get the current value(s) of a form field by its xl field-id.
	 *
	 * @param {HTMLFormElement} form
	 * @param {string} fieldId
	 * @returns {string[]} Array of selected/entered values.
	 */
	function getFieldValues(form, fieldId) {
		var wrap = form.querySelector('.xf-field-wrap[data-field-id="' + fieldId + '"]');
		if (!wrap) {
			return [];
		}

		var checkboxes = wrap.querySelectorAll('input[type="checkbox"]');
		if (checkboxes.length) {
			var checked = [];
			checkboxes.forEach(function (cb) {
				if (cb.checked) {
					checked.push(cb.value);
				}
			});
			return checked;
		}

		var radios = wrap.querySelectorAll('input[type="radio"]');
		if (radios.length) {
			var selected = [];
			radios.forEach(function (r) {
				if (r.checked) {
					selected.push(r.value);
				}
			});
			return selected;
		}

		var inp = wrap.querySelector('input:not([type="hidden"]), textarea, select');
		if (inp) {
			return [inp.value];
		}

		return [];
	}

	/**
	 * Test a single condition.
	 *
	 * @param {string[]} fieldValues Current values of the trigger field.
	 * @param {object}   condition
	 * @returns {boolean}
	 */
	function testCondition(fieldValues, condition) {
		var op    = condition.operator || 'equals';
		var cv    = (condition.value || '').toLowerCase();

		switch (op) {
			case 'equals':
				return fieldValues.some(function (v) { return v.toLowerCase() === cv; });
			case 'not_equals':
				return fieldValues.every(function (v) { return v.toLowerCase() !== cv; });
			case 'contains':
				return fieldValues.some(function (v) { return v.toLowerCase().indexOf(cv) !== -1; });
			case 'not_empty':
				return fieldValues.some(function (v) { return v.trim() !== ''; });
			case 'is_empty':
				return fieldValues.every(function (v) { return v.trim() === ''; });
			default:
				return false;
		}
	}

	/**
	 * Show or hide a target field wrap.
	 * Hiding clears the value and disables inputs so they are excluded from submission.
	 *
	 * @param {HTMLElement} targetWrap
	 * @param {boolean}     show
	 */
	function toggleField(targetWrap, show) {
		if (!targetWrap) {
			return;
		}

		if (show) {
			targetWrap.style.display = '';
			targetWrap.removeAttribute('aria-hidden');
			// Re-enable all inputs inside.
			targetWrap.querySelectorAll('input, textarea, select').forEach(function (el) {
				el.disabled = false;
				// Restore required if it was originally required.
				if (el.dataset.xtremeformsWasRequired === '1') {
					el.required = true;
					el.setAttribute('aria-required', 'true');
				}
			});
		} else {
			targetWrap.style.display = 'none';
			targetWrap.setAttribute('aria-hidden', 'true');
			// Disable & clear all inputs inside.
			targetWrap.querySelectorAll('input, textarea, select').forEach(function (el) {
				// Save required state before removing it.
				if (el.required) {
					el.dataset.xtremeformsWasRequired = '1';
				}
				el.required  = false;
				el.disabled  = true;
				el.removeAttribute('aria-required');

				// Clear value.
				var type = el.type ? el.type.toLowerCase() : '';
				if (type === 'checkbox' || type === 'radio') {
					el.checked = false;
				} else {
					el.value = '';
				}
			});
		}
	}

	/**
	 * Evaluate a single rule pass across all rules for a given form.
	 * Returns true if any field visibility changed during this pass.
	 *
	 * @param {HTMLFormElement} form
	 * @returns {boolean} True if at least one field visibility changed.
	 */
	function evaluateRulesPass(form) {
		var anyChanged = false;

		rules.forEach(function (rule) {
			if (!rule.fieldId || !Array.isArray(rule.conditions) || !rule.conditions.length) {
				return;
			}

			var logic       = (rule.logic || 'and').toLowerCase();
			var conditions  = rule.conditions;
			var results     = conditions.map(function (cond) {
				var triggerValues = getFieldValues(form, cond.triggerFieldId);
				return testCondition(triggerValues, cond);
			});

			var conditionMet;
			if (logic === 'or') {
				conditionMet = results.some(Boolean);
			} else {
				conditionMet = results.every(Boolean);
			}

			var targetWrap = form.querySelector('.xf-field-wrap[data-field-id="' + rule.fieldId + '"]');
			if (!targetWrap) {
				return;
			}

			var wasHidden = targetWrap.style.display === 'none';
			var shouldShow = conditionMet;

			// Detect visibility change.
			if (wasHidden === shouldShow) {
				// Was hidden and should show, or was visible and should hide — change detected.
				anyChanged = true;
			}

			toggleField(targetWrap, conditionMet);
		});

		return anyChanged;
	}

	/**
	 * Evaluate all rules for a given form and update field visibility.
	 * Uses multi-pass evaluation to correctly resolve nested rules where
	 * field A's visibility change affects field B's condition for field C.
	 *
	 * @param {HTMLFormElement} form
	 */
	function evaluateRules(form) {
		var start = (typeof performance !== 'undefined') ? performance.now() : 0;

		// Multi-pass: keep evaluating until no more visibility changes occur.
		// This handles nested rules (A triggers B, B's value drives C's visibility).
		var maxPasses = 10; // Safety limit to prevent infinite loops.
		var passes    = 0;
		var changed   = true;

		while (changed && passes < maxPasses) {
			changed = evaluateRulesPass(form);
			passes++;
		}

		// Performance budget: < 100ms from input event.
		if (start && typeof performance !== 'undefined') {
			var elapsed = performance.now() - start;
			if (elapsed > 90) {
				console.warn('[Xtreme Forms] Conditional logic evaluation took ' + elapsed.toFixed(1) + 'ms (' + passes + ' passes)');
			}
		}
	}

	/**
	 * Bootstrap: set initial state and attach listeners on every XL form.
	 */
	function init() {
		var forms = document.querySelectorAll('.xf-form');
		forms.forEach(function (form) {
			// Set initial state.
			evaluateRules(form);

			// Listen for changes on any input within the form.
			['change', 'input', 'keyup'].forEach(function (evtName) {
				form.addEventListener(evtName, function (e) {
					// Only re-evaluate when a trigger field changes.
					var changed = e.target.closest('.xf-field-wrap');
					if (!changed) {
						return;
					}
					evaluateRules(form);
				});
			});
		});
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
}());
