<?php
/**
 * Email Routing Rules admin page.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification -- Filter parameters on this admin display page are read-only GET params — no nonce required for display-only filtering.

$rules = XL_Routing_Rules::get_all_rules();
$all_forms = XL_Forms::get_all_forms();
$mode = XL_Routing_Rules::get_mode();

$notice = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Routing rules saved.', 'xtremeleads' ) . '</p></div>';
}
?>
<div class="wrap xl-wrap">
	<h1 class="xl-page-title"><?php esc_html_e( 'Email Routing Rules', 'xtremeleads' ); ?></h1>

	<?php echo wp_kses_post( $notice ); ?>

	<p class="description" style="margin-bottom:16px;">
		<?php esc_html_e( 'Define rules to route new lead notifications to specific email addresses based on which form was submitted or the value of a particular field.', 'xtremeleads' ); ?>
		<br>
		<?php esc_html_e( 'When a matching rule is found, the notification is sent to the rule\'s recipient instead of the global/per-form recipient. Drag rows to reorder — order matters in "Match First" mode.', 'xtremeleads' ); ?>
	</p>

	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="xl-routing-rules-form">
		<input type="hidden" name="action" value="xl_save_routing_rules">
		<?php wp_nonce_field( 'xl_save_routing_rules' ); ?>

		<!-- Evaluation Mode -->
		<div class="xl-settings-card" style="margin-bottom:24px;">
			<h2><?php esc_html_e( 'Evaluation Mode', 'xtremeleads' ); ?></h2>
			<fieldset>
				<label style="display:block;margin-bottom:8px;">
					<input type="radio" name="routing_mode" value="match_first" <?php checked( $mode, 'match_first' ); ?>>
					<strong><?php esc_html_e( 'Match First', 'xtremeleads' ); ?></strong>
					&mdash; <?php esc_html_e( 'Stop at the first matching rule and send notification only to that rule\'s recipient.', 'xtremeleads' ); ?>
				</label>
				<label style="display:block;">
					<input type="radio" name="routing_mode" value="match_all" <?php checked( $mode, 'match_all' ); ?>>
					<strong><?php esc_html_e( 'Match All', 'xtremeleads' ); ?></strong>
					&mdash; <?php esc_html_e( 'Evaluate all rules; send notifications to every matched recipient.', 'xtremeleads' ); ?>
				</label>
			</fieldset>
		</div>

		<!-- Rules Table -->
		<div class="xl-settings-card">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
				<h2 style="margin:0;"><?php esc_html_e( 'Rules', 'xtremeleads' ); ?></h2>
				<button type="button" id="xl-add-rule" class="button xl-btn-secondary">
					+ <?php esc_html_e( 'Add Rule', 'xtremeleads' ); ?>
				</button>
			</div>

			<table class="widefat xl-routing-table" id="xl-rules-table" style="table-layout:fixed;">
				<thead>
					<tr>
						<th style="width:32px;"></th>
						<th style="width:130px;"><?php esc_html_e( 'Condition Type', 'xtremeleads' ); ?></th>
						<th><?php esc_html_e( 'Form', 'xtremeleads' ); ?></th>
						<th><?php esc_html_e( 'Field ID', 'xtremeleads' ); ?></th>
						<th><?php esc_html_e( 'Field Value', 'xtremeleads' ); ?></th>
						<th><?php esc_html_e( 'Notify Recipient', 'xtremeleads' ); ?></th>
						<th style="width:60px;"><?php esc_html_e( 'Active', 'xtremeleads' ); ?></th>
						<th style="width:72px;"><?php esc_html_e( 'Actions', 'xtremeleads' ); ?></th>
					</tr>
				</thead>
				<tbody id="xl-rules-body">
					<?php if ( empty( $rules ) ) : ?>
						<tr id="xl-no-rules-row">
							<td colspan="8" style="text-align:center;color:#6C757D;padding:24px;">
								<?php esc_html_e( 'No routing rules yet. Click "Add Rule" to create one.', 'xtremeleads' ); ?>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rules as $idx => $rule ) : ?>
							<?php
							$rule_id = (int) $rule->id;
							$cond_type = $rule->condition_type ?? 'form';
							$rule_form_id = (int) $rule->form_id;
							$field_id_val = esc_attr( $rule->field_id ?? '' );
							$field_val_val = esc_attr( $rule->field_value ?? '' );
							$recipient_val = esc_attr( $rule->recipient_email ?? '' );
							$is_active_val = (int) ( $rule->is_active ?? 1 );
							?>
							<tr class="xl-rule-row" data-rule-id="<?php echo esc_attr( $rule_id ); ?>">
								<td class="xl-drag-handle" style="cursor:grab;text-align:center;color:#6C757D;font-size:18px;" title="<?php esc_attr_e( 'Drag to reorder', 'xtremeleads' ); ?>">&#8597;</td>
								<td>
									<select name="rules[<?php echo esc_attr( $idx ); ?>][condition_type]" class="xl-cond-type-select" style="width:100%;">
										<option value="form" <?php selected( $cond_type, 'form' ); ?>><?php esc_html_e( 'Form', 'xtremeleads' ); ?></option>
										<option value="field_value" <?php selected( $cond_type, 'field_value' ); ?>><?php esc_html_e( 'Field Value', 'xtremeleads' ); ?></option>
									</select>
									<input type="hidden" name="rules[<?php echo esc_attr( $idx ); ?>][id]" value="<?php echo esc_attr( $rule_id ); ?>">
								</td>
								<td>
									<select name="rules[<?php echo esc_attr( $idx ); ?>][form_id]" style="width:100%;">
										<option value=""><?php esc_html_e( '— Select Form —', 'xtremeleads' ); ?></option>
										<?php foreach ( $all_forms as $f ) : ?>
											<option value="<?php echo esc_attr( $f->id ); ?>" <?php selected( $rule_form_id, (int) $f->id ); ?>>
												<?php echo esc_html( $f->name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
								<td class="xl-field-id-col" style="<?php echo 'field_value' !== $cond_type ? 'opacity:0.3;pointer-events:none;' : ''; ?>">
									<input type="text" name="rules[<?php echo esc_attr( $idx ); ?>][field_id]" value="<?php echo esc_attr( $field_id_val ); ?>" placeholder="<?php esc_attr_e( 'e.g. service_interest', 'xtremeleads' ); ?>" style="width:100%;">
								</td>
								<td class="xl-field-val-col" style="<?php echo 'field_value' !== $cond_type ? 'opacity:0.3;pointer-events:none;' : ''; ?>">
									<input type="text" name="rules[<?php echo esc_attr( $idx ); ?>][field_value]" value="<?php echo esc_attr( $field_val_val ); ?>" placeholder="<?php esc_attr_e( 'Exact value (case-sensitive)', 'xtremeleads' ); ?>" style="width:100%;">
								</td>
								<td>
									<input type="email" name="rules[<?php echo esc_attr( $idx ); ?>][recipient_email]" value="<?php echo esc_attr( $recipient_val ); ?>" placeholder="notify@example.com" style="width:100%;" required>
								</td>
								<td style="text-align:center;">
									<input type="checkbox" name="rules[<?php echo esc_attr( $idx ); ?>][is_active]" value="1" <?php checked( $is_active_val, 1 ); ?>>
								</td>
								<td>
									<button type="button" class="button xl-remove-rule" style="color:#DC3545;border-color:#DC3545;"><?php esc_html_e( 'Remove', 'xtremeleads' ); ?></button>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>

		<div class="xl-form-actions">
			<?php submit_button( __( 'Save Routing Rules', 'xtremeleads' ), 'primary xl-btn-primary', 'submit', false ); ?>
		</div>
	</form>
</div>

<!-- Row template (hidden) -->
<template id="xl-rule-row-tpl">
	<tr class="xl-rule-row">
		<td class="xl-drag-handle" style="cursor:grab;text-align:center;color:#6C757D;font-size:18px;" title="<?php esc_attr_e( 'Drag to reorder', 'xtremeleads' ); ?>">&#8597;</td>
		<td>
			<select name="rules[__IDX__][condition_type]" class="xl-cond-type-select" style="width:100%;">
				<option value="form"><?php esc_html_e( 'Form', 'xtremeleads' ); ?></option>
				<option value="field_value"><?php esc_html_e( 'Field Value', 'xtremeleads' ); ?></option>
			</select>
			<input type="hidden" name="rules[__IDX__][id]" value="0">
		</td>
		<td>
			<select name="rules[__IDX__][form_id]" style="width:100%;">
				<option value=""><?php esc_html_e( '— Select Form —', 'xtremeleads' ); ?></option>
				<?php foreach ( $all_forms as $f ) : ?>
					<option value="<?php echo esc_attr( $f->id ); ?>"><?php echo esc_html( $f->name ); ?></option>
				<?php endforeach; ?>
			</select>
		</td>
		<td class="xl-field-id-col" style="opacity:0.3;pointer-events:none;">
			<input type="text" name="rules[__IDX__][field_id]" value="" placeholder="<?php esc_attr_e( 'e.g. service_interest', 'xtremeleads' ); ?>" style="width:100%;">
		</td>
		<td class="xl-field-val-col" style="opacity:0.3;pointer-events:none;">
			<input type="text" name="rules[__IDX__][field_value]" value="" placeholder="<?php esc_attr_e( 'Exact value (case-sensitive)', 'xtremeleads' ); ?>" style="width:100%;">
		</td>
		<td>
			<input type="email" name="rules[__IDX__][recipient_email]" value="" placeholder="notify@example.com" style="width:100%;" required>
		</td>
		<td style="text-align:center;">
			<input type="checkbox" name="rules[__IDX__][is_active]" value="1" checked>
		</td>
		<td>
			<button type="button" class="button xl-remove-rule" style="color:#DC3545;border-color:#DC3545;"><?php esc_html_e( 'Remove', 'xtremeleads' ); ?></button>
		</td>
	</tr>
</template>

<script>
(function () {
	'use strict';

	let ruleCounter = <?php echo count( $rules ); ?>;
	const tbody = document.getElementById('xl-rules-body');
	const tpl = document.getElementById('xl-rule-row-tpl');
	const noRulesRow = document.getElementById('xl-no-rules-row');

	// ── Add rule ────────────────────────────────────────────────────────────
	document.getElementById('xl-add-rule').addEventListener('click', function () {
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
		if (e.target.classList.contains('xl-remove-rule')) {
			e.target.closest('tr').remove();
			renumberRows();
			if (tbody.querySelectorAll('.xl-rule-row').length === 0) {
				const row = document.createElement('tr');
				row.id = 'xl-no-rules-row';
				row.innerHTML = '<td colspan="8" style="text-align:center;color:#6C757D;padding:24px;"><?php echo esc_js( __( 'No routing rules yet. Click "Add Rule" to create one.', 'xtremeleads' ) ); ?></td>';
				tbody.appendChild(row);
			}
		}
	});

	// ── Condition type toggle ───────────────────────────────────────────────
	function initRow(row) {
		const sel = row.querySelector('.xl-cond-type-select');
		if (sel) {
			sel.addEventListener('change', function () { updateFieldCols(row, sel.value); });
			updateFieldCols(row, sel.value);
		}
	}

	function updateFieldCols(row, condType) {
		const fieldIdCol = row.querySelector('.xl-field-id-col');
		const fieldValCol = row.querySelector('.xl-field-val-col');
		const isFieldVal = condType === 'field_value';

		if (fieldIdCol) { fieldIdCol.style.opacity = isFieldVal ? '1' : '0.3'; fieldIdCol.style.pointerEvents = isFieldVal ? '' : 'none'; }
		if (fieldValCol) { fieldValCol.style.opacity = isFieldVal ? '1' : '0.3'; fieldValCol.style.pointerEvents = isFieldVal ? '' : 'none'; }
	}

	// Init existing rows.
	document.querySelectorAll('.xl-rule-row').forEach(initRow);

	// ── Renumber field names for correct POST order ─────────────────────────
	function renumberRows() {
		document.querySelectorAll('#xl-rules-body .xl-rule-row').forEach(function (row, idx) {
			row.querySelectorAll('[name]').forEach(function (el) {
				el.name = el.name.replace(/rules\[\d+\]/, 'rules[' + idx + ']');
			});
		});
	}

	// ── Drag-and-drop reorder ───────────────────────────────────────────────
	let dragRow = null;

	tbody.addEventListener('dragstart', function (e) {
		dragRow = e.target.closest('.xl-rule-row');
		if (dragRow) {
			dragRow.style.opacity = '0.5';
			e.dataTransfer.effectAllowed = 'move';
		}
	});

	tbody.addEventListener('dragover', function (e) {
		e.preventDefault();
		const target = e.target.closest('.xl-rule-row');
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
	document.querySelectorAll('.xl-drag-handle').forEach(function (handle) {
		handle.closest('tr').setAttribute('draggable', 'true');
	});

	tbody.addEventListener('mouseover', function (e) {
		const handle = e.target.closest('.xl-drag-handle');
		if (handle) handle.closest('tr').setAttribute('draggable', 'true');
	});
}());
</script>
