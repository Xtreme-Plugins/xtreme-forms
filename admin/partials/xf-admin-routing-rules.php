<?php
/**
 * Email Routing Rules admin page.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Filter parameters on this admin display page are read-only GET params — no nonce required for display-only filtering.

$rules     = XF_Routing_Rules::get_all_rules();
$all_forms = XF_Forms::get_all_forms();
$mode      = XF_Routing_Rules::get_mode();

$notice = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Routing rules saved.', 'xtreme-forms' ) . '</p></div>';
}
?>
<div class="wrap xf-wrap">
	<div class="xf-page-header">
		<h1 class="xf-page-title"><?php esc_html_e( 'Email Routing Rules', 'xtreme-forms' ); ?></h1>
	</div>

	<?php echo wp_kses_post( $notice ); ?>

	<p class="description" style="margin-bottom:16px;">
		<?php esc_html_e( 'Define rules to route new lead notifications to specific email addresses based on which form was submitted or the value of a particular field.', 'xtreme-forms' ); ?>
		<br>
		<?php esc_html_e( 'When a matching rule is found, the notification is sent to the rule\'s recipient instead of the global/per-form recipient. Drag rows to reorder — order matters in "Match First" mode.', 'xtreme-forms' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="xf-routing-rules-form">
		<input type="hidden" name="action" value="xf_save_routing_rules">
		<?php wp_nonce_field( 'xf_save_routing_rules' ); ?>

		<!-- Evaluation Mode -->
		<div class="xf-settings-card" style="margin-bottom:24px;">
			<h2><?php esc_html_e( 'Evaluation Mode', 'xtreme-forms' ); ?></h2>
			<fieldset>
				<label style="display:block;margin-bottom:8px;">
					<input type="radio" name="routing_mode" value="match_first" <?php checked( $mode, 'match_first' ); ?>>
					<strong><?php esc_html_e( 'Match First', 'xtreme-forms' ); ?></strong>
					&mdash; <?php esc_html_e( 'Stop at the first matching rule and send notification only to that rule\'s recipient.', 'xtreme-forms' ); ?>
				</label>
				<label style="display:block;">
					<input type="radio" name="routing_mode" value="match_all" <?php checked( $mode, 'match_all' ); ?>>
					<strong><?php esc_html_e( 'Match All', 'xtreme-forms' ); ?></strong>
					&mdash; <?php esc_html_e( 'Evaluate all rules; send notifications to every matched recipient.', 'xtreme-forms' ); ?>
				</label>
			</fieldset>
		</div>

		<!-- Rules Table -->
		<div class="xf-settings-card">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
				<h2 style="margin:0;"><?php esc_html_e( 'Rules', 'xtreme-forms' ); ?></h2>
				<button type="button" id="xf-add-rule" class="button xf-btn-secondary">
					+ <?php esc_html_e( 'Add Rule', 'xtreme-forms' ); ?>
				</button>
			</div>

			<table class="widefat xf-routing-table" id="xf-rules-table" style="table-layout:fixed;">
				<thead>
					<tr>
						<th style="width:32px;"></th>
						<th style="width:130px;"><?php esc_html_e( 'Condition Type', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Form', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Field ID', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Field Value', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Notify Recipient', 'xtreme-forms' ); ?></th>
						<th style="width:60px;"><?php esc_html_e( 'Active', 'xtreme-forms' ); ?></th>
						<th style="width:72px;"><?php esc_html_e( 'Actions', 'xtreme-forms' ); ?></th>
					</tr>
				</thead>
				<tbody id="xf-rules-body">
					<?php if ( empty( $rules ) ) : ?>
						<tr id="xf-no-rules-row">
							<td colspan="8" style="text-align:center;color:#6C757D;padding:24px;">
								<?php esc_html_e( 'No routing rules yet. Click "Add Rule" to create one.', 'xtreme-forms' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rules as $idx => $rule ) : ?>
							<?php
							$rule_id       = (int) $rule->id;
							$cond_type     = $rule->condition_type ?? 'form';
							$rule_form_id  = (int) $rule->form_id;
							$field_id_val  = esc_attr( $rule->field_id ?? '' );
							$field_val_val = esc_attr( $rule->field_value ?? '' );
							$recipient_val = esc_attr( $rule->recipient_email ?? '' );
							$is_active_val = (int) ( $rule->is_active ?? 1 );
							?>
							<tr class="xf-rule-row" data-rule-id="<?php echo esc_attr( $rule_id ); ?>">
								<td class="xf-drag-handle" style="cursor:grab;text-align:center;color:#6C757D;font-size:18px;" title="<?php esc_attr_e( 'Drag to reorder', 'xtreme-forms' ); ?>">&#8597;</td>
								<td>
									<select name="rules[<?php echo esc_attr( $idx ); ?>][condition_type]" class="xf-cond-type-select" style="width:100%;">
										<option value="form" <?php selected( $cond_type, 'form' ); ?>><?php esc_html_e( 'Form', 'xtreme-forms' ); ?></option>
										<option value="field_value" <?php selected( $cond_type, 'field_value' ); ?>><?php esc_html_e( 'Field Value', 'xtreme-forms' ); ?></option>
									</select>
									<input type="hidden" name="rules[<?php echo esc_attr( $idx ); ?>][id]" value="<?php echo esc_attr( $rule_id ); ?>">
								</td>
								<td>
									<select name="rules[<?php echo esc_attr( $idx ); ?>][form_id]" style="width:100%;">
										<option value=""><?php esc_html_e( '— Select Form —', 'xtreme-forms' ); ?></option>
										<?php foreach ( $all_forms as $f ) : ?>
											<option value="<?php echo esc_attr( $f->id ); ?>" <?php selected( $rule_form_id, (int) $f->id ); ?>>
												<?php echo esc_html( $f->name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td class="xf-field-id-col" style="<?php echo 'field_value' !== $cond_type ? 'opacity:0.3;pointer-events:none;' : ''; ?>">
									<input type="text" name="rules[<?php echo esc_attr( $idx ); ?>][field_id]" value="<?php echo esc_attr( $field_id_val ); ?>" placeholder="<?php esc_attr_e( 'e.g. service_interest', 'xtreme-forms' ); ?>" style="width:100%;">
								</td>
								<td class="xf-field-val-col" style="<?php echo 'field_value' !== $cond_type ? 'opacity:0.3;pointer-events:none;' : ''; ?>">
									<input type="text" name="rules[<?php echo esc_attr( $idx ); ?>][field_value]" value="<?php echo esc_attr( $field_val_val ); ?>" placeholder="<?php esc_attr_e( 'Exact value (case-sensitive)', 'xtreme-forms' ); ?>" style="width:100%;">
								</td>
								<td>
									<input type="email" name="rules[<?php echo esc_attr( $idx ); ?>][recipient_email]" value="<?php echo esc_attr( $recipient_val ); ?>" placeholder="notify@example.com" style="width:100%;" required>
								</td>
								<td style="text-align:center;">
									<input type="checkbox" name="rules[<?php echo esc_attr( $idx ); ?>][is_active]" value="1" <?php checked( $is_active_val, 1 ); ?>>
								</td>
								<td>
									<button type="button" class="button xf-remove-rule" style="color:#DC3545;border-color:#DC3545;"><?php esc_html_e( 'Remove', 'xtreme-forms' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="xf-form-actions">
			<?php submit_button( __( 'Save Routing Rules', 'xtreme-forms' ), 'primary xf-btn-primary', 'submit', false ); ?>
		</div>
	</form>
</div>

<!-- Row template (hidden) -->
<template id="xf-rule-row-tpl">
	<tr class="xf-rule-row">
		<td class="xf-drag-handle" style="cursor:grab;text-align:center;color:#6C757D;font-size:18px;" title="<?php esc_attr_e( 'Drag to reorder', 'xtreme-forms' ); ?>">&#8597;</td>
		<td>
			<select name="rules[__IDX__][condition_type]" class="xf-cond-type-select" style="width:100%;">
				<option value="form"><?php esc_html_e( 'Form', 'xtreme-forms' ); ?></option>
				<option value="field_value"><?php esc_html_e( 'Field Value', 'xtreme-forms' ); ?></option>
			</select>
			<input type="hidden" name="rules[__IDX__][id]" value="0">
		</td>
		<td>
			<select name="rules[__IDX__][form_id]" style="width:100%;">
				<option value=""><?php esc_html_e( '— Select Form —', 'xtreme-forms' ); ?></option>
				<?php foreach ( $all_forms as $f ) : ?>
					<option value="<?php echo esc_attr( $f->id ); ?>"><?php echo esc_html( $f->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td class="xf-field-id-col" style="opacity:0.3;pointer-events:none;">
			<input type="text" name="rules[__IDX__][field_id]" value="" placeholder="<?php esc_attr_e( 'e.g. service_interest', 'xtreme-forms' ); ?>" style="width:100%;">
		</td>
		<td class="xf-field-val-col" style="opacity:0.3;pointer-events:none;">
			<input type="text" name="rules[__IDX__][field_value]" value="" placeholder="<?php esc_attr_e( 'Exact value (case-sensitive)', 'xtreme-forms' ); ?>" style="width:100%;">
		</td>
		<td>
			<input type="email" name="rules[__IDX__][recipient_email]" value="" placeholder="notify@example.com" style="width:100%;" required>
		</td>
		<td style="text-align:center;">
			<input type="checkbox" name="rules[__IDX__][is_active]" value="1" checked>
		</td>
		<td>
			<button type="button" class="button xf-remove-rule" style="color:#DC3545;border-color:#DC3545;"><?php esc_html_e( 'Remove', 'xtreme-forms' ); ?></button>
		</td>
	</tr>
</template>

<script>
(function () {
	'use strict';

	let ruleCounter = <?php echo count( $rules ); ?>;
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
				row.innerHTML = '<td colspan="8" style="text-align:center;color:#6C757D;padding:24px;"><?php echo esc_js( __( 'No routing rules yet. Click "Add Rule" to create one.', 'xtreme-forms' ) ); ?></td>';
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
</script>
