/**
 * Xtreme Forms — Routing Rules admin page scripts.
 *
 * Extracted from inline <script> in admin/partials/xf-admin-routing-rules.php
 * for WordPress.org Plugin Check compliance. Per-render data and translatable
 * strings are exposed by the partial via wp_localize_script.
 */

(function () {
	'use strict';

	var data = window.xfRoutingRulesData || {};
	var i18n = window.xfRoutingRulesI18n || {};
	var ruleCounter = parseInt( data.initialCount, 10 ) || 0;

	const tbody = document.getElementById('xf-rules-body');
	const tpl = document.getElementById('xf-rule-row-tpl');
	const noRulesRow = document.getElementById('xf-no-rules-row');

	// ── Add rule ────────────────────────────────────────────────────────────
	document.getElementById('xf-add-rule').addEventListener('click', function () {
		if (noRulesRow) noRulesRow.remove();

		const html = tpl.innerHTML.replace(/__IDX__/g, ruleCounter);
		const tmp = document.createElement('tbody');
		tmp.innerHTML = html;
		const newRow = tmp.querySelector('tr');
		tbody.appendChild(newRow);

		initRow(newRow);
		renumberRows();
		ruleCounter++;
	});

	// ── Remove rule ─────────────────────────────────────────────────────────
	tbody.addEventListener('click', function (e) {
		if (e.target.classList.contains('xf-remove-rule')) {
			e.target.closest('tr').remove();
			renumberRows();
			if (tbody.querySelectorAll('.xf-rule-row').length === 0) {
				const row = document.createElement('tr');
				row.id = 'xf-no-rules-row';
				row.innerHTML = '<td colspan="8" style="text-align:center;color:#6C757D;padding:24px;">' + ( i18n.noRules || '' ) + '</td>';
				tbody.appendChild(row);
			}
		}
	});

	// ── Condition type toggle ───────────────────────────────────────────────
	function initRow(row) {
		const sel = row.querySelector('.xf-cond-type-select');
		if (sel) {
			sel.addEventListener('change', function () { updateFieldCols(row, sel.value); });
			updateFieldCols(row, sel.value);
		}
	}

	function updateFieldCols(row, condType) {
		const fieldIdCol = row.querySelector('.xf-field-id-col');
		const fieldValCol = row.querySelector('.xf-field-val-col');
		const isFieldVal = condType === 'field_value';

		if (fieldIdCol) { fieldIdCol.style.opacity = isFieldVal ? '1' : '0.3'; fieldIdCol.style.pointerEvents = isFieldVal ? '' : 'none'; }
		if (fieldValCol) { fieldValCol.style.opacity = isFieldVal ? '1' : '0.3'; fieldValCol.style.pointerEvents = isFieldVal ? '' : 'none'; }
	}

	// Init existing rows.
	document.querySelectorAll('.xf-rule-row').forEach(initRow);

	// ── Renumber field names for correct POST order ─────────────────────────
	function renumberRows() {
		document.querySelectorAll('#xf-rules-body .xf-rule-row').forEach(function (row, idx) {
			row.querySelectorAll('[name]').forEach(function (el) {
				el.name = el.name.replace(/rules\[\d+\]/, 'rules[' + idx + ']');
			});
		});
	}

	// ── Drag-and-drop reorder ───────────────────────────────────────────────
	let dragRow = null;

	tbody.addEventListener('dragstart', function (e) {
		dragRow = e.target.closest('.xf-rule-row');
		if (dragRow) {
			dragRow.style.opacity = '0.5';
			e.dataTransfer.effectAllowed = 'move';
		}
	});

	tbody.addEventListener('dragover', function (e) {
		e.preventDefault();
		const target = e.target.closest('.xf-rule-row');
		if (target && target !== dragRow) {
			const rect = target.getBoundingClientRect();
			const next = (e.clientY - rect.top) > rect.height / 2;
			tbody.insertBefore(dragRow, next ? target.nextSibling : target);
		}
	});

	tbody.addEventListener('dragend', function () {
		if (dragRow) {
			dragRow.style.opacity = '';
			dragRow = null;
		}
		renumberRows();
	});

	// Make rows draggable via drag handle.
	document.querySelectorAll('.xf-drag-handle').forEach(function (handle) {
		handle.closest('tr').setAttribute('draggable', 'true');
	});

	tbody.addEventListener('mouseover', function (e) {
		const handle = e.target.closest('.xf-drag-handle');
		if (handle) handle.closest('tr').setAttribute('draggable', 'true');
	});
}());
