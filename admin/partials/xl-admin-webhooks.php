<?php
/**
 * Webhooks admin page — CRUD interface + delivery log.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

$webhooks = XL_Webhooks::get_all();
$all_forms = XL_Forms::get_all_forms();
$nonce = wp_create_nonce( 'xl_webhook_nonce' );

$notice_html = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Webhook saved.', 'xtremeleads' ) . '</p></div>';
}
?>
<div class="wrap xl-wrap" id="xl-webhooks-page">
	<h1 class="xl-page-title"><?php esc_html_e( 'Webhooks', 'xtremeleads' ); ?></h1>
	<?php echo wp_kses_post( $notice_html ); ?>

	<div class="xl-card" style="margin-bottom:24px;">
		<p><?php esc_html_e( 'Configure outbound webhook endpoints that receive lead data when events occur. Each webhook sends a POST request with a JSON payload.', 'xtremeleads' ); ?></p>
		<button type="button" class="button button-primary xl-btn-primary" id="xl-add-webhook-btn">
			+ <?php esc_html_e( 'Add Webhook', 'xtremeleads' ); ?>
		</button>
	</div>

	<!-- Webhook editor (hidden by default) -->
	<div id="xl-webhook-editor" class="xl-card" style="display:none;margin-bottom:24px;">
		<h2 id="xl-webhook-editor-title"><?php esc_html_e( 'Add Webhook', 'xtremeleads' ); ?></h2>
		<input type="hidden" id="xl-webhook-id" value="0">

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="xl-wh-name"><?php esc_html_e( 'Name', 'xtremeleads' ); ?></label></th>
					<td>
						<input type="text" id="xl-wh-name" class="regular-text" placeholder="<?php esc_attr_e( 'My CRM Webhook', 'xtremeleads' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="xl-wh-url"><?php esc_html_e( 'Endpoint URL', 'xtremeleads' ); ?></label></th>
					<td>
						<input type="url" id="xl-wh-url" class="large-text" placeholder="https://example.com/webhook">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Trigger Events', 'xtremeleads' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:8px;">
							<input type="checkbox" id="xl-wh-event-new-lead" value="new_lead" checked>
							<?php esc_html_e( 'New Lead', 'xtremeleads' ); ?>
						</label>
						<label style="display:block;">
							<input type="checkbox" id="xl-wh-event-status-change" value="status_change">
							<?php esc_html_e( 'Status Change', 'xtremeleads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Form Filter', 'xtremeleads' ); ?></th>
					<td>
						<p class="description" style="margin-bottom:8px;"><?php esc_html_e( 'Leave all unchecked to receive events from all forms.', 'xtremeleads' ); ?></p>
						<?php foreach ( $all_forms as $form ) : ?>
							<label style="display:block;margin-bottom:4px;">
								<input type="checkbox" class="xl-wh-form-filter" value="<?php echo esc_attr( $form->id ); ?>">
								<?php echo esc_html( $form->name ); ?>
							</label>
						<?php endforeach; ?>
						<?php if ( empty( $all_forms ) ) : ?>
							<p class="description"><?php esc_html_e( 'No forms found.', 'xtremeleads' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Active', 'xtremeleads' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="xl-wh-active" value="1" checked>
							<?php esc_html_e( 'Enable this webhook', 'xtremeleads' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Custom Headers', 'xtremeleads' ); ?></th>
					<td>
						<p class="description" style="margin-bottom:8px;"><?php esc_html_e( 'Add custom request headers (e.g., Authorization tokens). Header values are stored securely and never exposed in front-end HTML.', 'xtremeleads' ); ?></p>
						<div id="xl-wh-headers-list"></div>
						<button type="button" class="button" id="xl-add-header-btn" style="margin-top:8px;">
							+ <?php esc_html_e( 'Add Header', 'xtremeleads' ); ?>
						</button>
						<div id="xl-header-content-type-warning" class="notice notice-warning inline" style="display:none;margin-top:8px;">
							<p><?php esc_html_e( 'Note: Adding a "Content-Type" header will override the default "application/json" value.', 'xtremeleads' ); ?></p>
						</div>
					</td>
				</tr>
			</tbody>
		</table>

		<div style="margin-top:16px;">
			<button type="button" class="button button-primary xl-btn-primary" id="xl-save-webhook-btn"><?php esc_html_e( 'Save Webhook', 'xtremeleads' ); ?></button>
			<button type="button" class="button" id="xl-cancel-webhook-btn" style="margin-left:8px;"><?php esc_html_e( 'Cancel', 'xtremeleads' ); ?></button>
			<span id="xl-webhook-save-msg" style="margin-left:12px;"></span>
		</div>
	</div>

	<!-- Webhooks list -->
	<div class="xl-card" style="margin-bottom:24px;">
		<h2><?php esc_html_e( 'Configured Webhooks', 'xtremeleads' ); ?></h2>

		<?php if ( empty( $webhooks ) ) : ?>
			<p><?php esc_html_e( 'No webhooks configured yet. Click "Add Webhook" to create one.', 'xtremeleads' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat striped" id="xl-webhooks-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'xtremeleads' ); ?></th>
						<th><?php esc_html_e( 'URL', 'xtremeleads' ); ?></th>
						<th><?php esc_html_e( 'Events', 'xtremeleads' ); ?></th>
						<th><?php esc_html_e( 'Forms', 'xtremeleads' ); ?></th>
						<th><?php esc_html_e( 'Status', 'xtremeleads' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'xtremeleads' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php foreach ( $webhooks as $wh ) :
					$events = json_decode( $wh->trigger_events, true ) ?: array();
					$form_ids = json_decode( $wh->form_ids, true ) ?: array();
					$form_names = empty( $form_ids ) ? __( 'All forms', 'xtremeleads' ) : implode( ', ', array_map( function( $fid ) use ( $all_forms ) {
						foreach ( $all_forms as $f ) {
							if ( (int) $f->id === (int) $fid ) return esc_html( $f->name );
						}
						return '#' . $fid;
					}, $form_ids ) );
					$event_labels = array_map( function( $e ) {
						return 'new_lead' === $e ? __( 'New Lead', 'xtremeleads' ) : __( 'Status Change', 'xtremeleads' );
					}, $events );
					?>
					<tr data-webhook-id="<?php echo esc_attr( $wh->id ); ?>">
						<td><strong><?php echo esc_html( $wh->name ); ?></strong></td>
						<td><code style="word-break:break-all;"><?php echo esc_html( $wh->url ); ?></code></td>
						<td><?php echo esc_html( implode( ', ', $event_labels ) ); ?></td>
						<td><?php echo esc_html( $form_names ); ?></td>
						<td>
							<?php if ( $wh->is_active ) : ?>
								<span class="xl-badge xl-badge-success"><?php esc_html_e( 'Active', 'xtremeleads' ); ?></span>
							<?php else : ?>
								<span class="xl-badge xl-badge-secondary"><?php esc_html_e( 'Inactive', 'xtremeleads' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button xl-wh-edit-btn" data-id="<?php echo esc_attr( $wh->id ); ?>"><?php esc_html_e( 'Edit', 'xtremeleads' ); ?></button>
							<button type="button" class="button xl-wh-test-btn" data-id="<?php echo esc_attr( $wh->id ); ?>"><?php esc_html_e( 'Test Fire', 'xtremeleads' ); ?></button>
							<button type="button" class="button xl-wh-log-btn" data-id="<?php echo esc_attr( $wh->id ); ?>"><?php esc_html_e( 'View Log', 'xtremeleads' ); ?></button>
							<button type="button" class="button button-link-delete xl-wh-delete-btn" data-id="<?php echo esc_attr( $wh->id ); ?>"><?php esc_html_e( 'Delete', 'xtremeleads' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<!-- Test fire result -->
	<div id="xl-test-fire-result" class="xl-card" style="display:none;margin-bottom:24px;">
		<h2><?php esc_html_e( 'Test Fire Result', 'xtremeleads' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr><th><?php esc_html_e( 'Status', 'xtremeleads' ); ?></th><td id="xl-tf-status"></td></tr>
				<tr><th><?php esc_html_e( 'HTTP Code', 'xtremeleads' ); ?></th><td id="xl-tf-http-code"></td></tr>
				<tr><th><?php esc_html_e( 'Response', 'xtremeleads' ); ?></th><td><pre id="xl-tf-response" style="white-space:pre-wrap;word-break:break-all;max-height:200px;overflow:auto;"></pre></td></tr>
				<tr id="xl-tf-error-row" style="display:none;"><th><?php esc_html_e( 'Error', 'xtremeleads' ); ?></th><td id="xl-tf-error" style="color:#DC3545;"></td></tr>
			</tbody>
		</table>
	</div>

	<!-- Delivery Log -->
	<div id="xl-delivery-log-wrap" class="xl-card" style="display:none;">
		<h2><?php esc_html_e( 'Delivery Log', 'xtremeleads' ); ?> <span id="xl-log-webhook-name"></span></h2>
		<div id="xl-delivery-log-content">
			<p><?php esc_html_e( 'Loading…', 'xtremeleads' ); ?></p>
		</div>
		<div id="xl-log-pagination" style="margin-top:12px;"></div>
	</div>

</div><!-- .xl-wrap -->

<style>
.xl-header-row { display:flex; gap:8px; margin-bottom:8px; align-items:center; }
.xl-header-row input { flex:1; }
.xl-header-row .xl-remove-header { color:#DC3545; cursor:pointer; background:none; border:none; font-size:18px; line-height:1; }
</style>

<script>
(function() {
	var nonce = <?php echo wp_json_encode( $nonce ); ?>;
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var allForms = <?php echo wp_json_encode( array_map( function( $f ) { return array( 'id' => (int) $f->id, 'name' => $f->name ); }, $all_forms ) ); ?>;

	var editor = document.getElementById('xl-webhook-editor');
	var addBtn = document.getElementById('xl-add-webhook-btn');
	var saveBtn = document.getElementById('xl-save-webhook-btn');
	var cancelBtn = document.getElementById('xl-cancel-webhook-btn');
	var addHeaderBtn = document.getElementById('xl-add-header-btn');
	var headersList = document.getElementById('xl-wh-headers-list');
	var ctWarning = document.getElementById('xl-header-content-type-warning');

	function openEditor( webhookData ) {
		document.getElementById('xl-webhook-editor-title').textContent = webhookData ? '<?php echo esc_js( __( 'Edit Webhook', 'xtremeleads' ) ); ?>' : '<?php echo esc_js( __( 'Add Webhook', 'xtremeleads' ) ); ?>';
		document.getElementById('xl-webhook-id').value = webhookData ? webhookData.id : 0;
		document.getElementById('xl-wh-name').value = webhookData ? webhookData.name : '';
		document.getElementById('xl-wh-url').value = webhookData ? webhookData.url : '';

		var events = webhookData ? (JSON.parse(webhookData.trigger_events) || []) : ['new_lead'];
		document.getElementById('xl-wh-event-new-lead').checked = events.indexOf('new_lead') > -1;
		document.getElementById('xl-wh-event-status-change').checked = events.indexOf('status_change') > -1;

		var formIds = webhookData ? (JSON.parse(webhookData.form_ids) || []) : [];
		document.querySelectorAll('.xl-wh-form-filter').forEach(function(cb) {
			cb.checked = formIds.indexOf(parseInt(cb.value)) > -1;
		});

		document.getElementById('xl-wh-active').checked = webhookData ? !!parseInt(webhookData.is_active) : true;

		// Populate headers.
		headersList.innerHTML = '';
		var headers = webhookData ? (JSON.parse(webhookData.custom_headers) || []) : [];
		headers.forEach(function(h) { addHeaderRow(h.name, h.value); });

		editor.style.display = '';
		editor.scrollIntoView({behavior:'smooth', block:'start'});
		document.getElementById('xl-webhook-save-msg').textContent = '';
	}

	function closeEditor() {
		editor.style.display = 'none';
	}

	function addHeaderRow(name, value) {
		name = name || '';
		value = value || '';
		var row = document.createElement('div');
		row.className = 'xl-header-row';
		row.innerHTML = '<input type="text" placeholder="<?php echo esc_js( __( 'Header Name', 'xtremeleads' ) ); ?>" value="' + escAttr(name) + '" class="xl-header-name" maxlength="256">'
			+ '<input type="text" placeholder="<?php echo esc_js( __( 'Header Value', 'xtremeleads' ) ); ?>" value="' + escAttr(value) + '" class="xl-header-value" maxlength="256">'
			+ '<button type="button" class="xl-remove-header" title="<?php echo esc_js( __( 'Remove', 'xtremeleads' ) ); ?>">&times;</button>';
		row.querySelector('.xl-remove-header').addEventListener('click', function() { row.remove(); checkCtWarning(); });
		row.querySelector('.xl-header-name').addEventListener('input', checkCtWarning);
		headersList.appendChild(row);
	}

	function checkCtWarning() {
		var names = Array.from(headersList.querySelectorAll('.xl-header-name')).map(function(i){ return i.value.trim().toLowerCase(); });
		ctWarning.style.display = names.indexOf('content-type') > -1 ? '' : 'none';
	}

	function escAttr(str) {
		return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}

	addBtn.addEventListener('click', function() { openEditor(null); });
	cancelBtn.addEventListener('click', closeEditor);
	addHeaderBtn.addEventListener('click', function() { addHeaderRow('', ''); });

	saveBtn.addEventListener('click', function() {
		var msg = document.getElementById('xl-webhook-save-msg');
		msg.textContent = '<?php echo esc_js( __( 'Saving…', 'xtremeleads' ) ); ?>';

		var events = [];
		if (document.getElementById('xl-wh-event-new-lead').checked) events.push('new_lead');
		if (document.getElementById('xl-wh-event-status-change').checked) events.push('status_change');

		var formIds = Array.from(document.querySelectorAll('.xl-wh-form-filter:checked')).map(function(c){ return parseInt(c.value); });

		var headers = [];
		document.querySelectorAll('.xl-header-row').forEach(function(row) {
			var name = row.querySelector('.xl-header-name').value.trim();
			var val = row.querySelector('.xl-header-value').value;
			if (name) headers.push({name: name, value: val});
		});

		var data = new FormData();
		data.append('action', 'xl_webhook_save');
		data.append('nonce', nonce);
		data.append('webhook[id]', document.getElementById('xl-webhook-id').value);
		data.append('webhook[name]', document.getElementById('xl-wh-name').value);
		data.append('webhook[url]', document.getElementById('xl-wh-url').value);
		data.append('webhook[trigger_events]', JSON.stringify(events));
		data.append('webhook[form_ids]', JSON.stringify(formIds));
		data.append('webhook[is_active]', document.getElementById('xl-wh-active').checked ? '1' : '0');
		data.append('webhook[custom_headers]', JSON.stringify(headers));

		fetch(ajaxUrl, {method:'POST', body:data})
			.then(function(r){ return r.json(); })
			.then(function(resp) {
				if (resp.success) {
					msg.textContent = '<?php echo esc_js( __( 'Saved!', 'xtremeleads' ) ); ?>';
					msg.style.color = '#28A745';
					setTimeout(function(){ location.reload(); }, 800);
				} else {
					msg.textContent = (resp.data && resp.data.message) || '<?php echo esc_js( __( 'Save failed.', 'xtremeleads' ) ); ?>';
					msg.style.color = '#DC3545';
				}
			})
			.catch(function() {
				msg.textContent = '<?php echo esc_js( __( 'Network error.', 'xtremeleads' ) ); ?>';
				msg.style.color = '#DC3545';
			});
	});

	// Edit buttons.
	document.querySelectorAll('.xl-wh-edit-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var id = btn.getAttribute('data-id');
			var fd = new FormData();
			fd.append('action', 'xl_webhook_get');
			fd.append('nonce', nonce);
			fd.append('webhook_id', id);
			fetch(ajaxUrl, {method:'POST', body:fd}).then(function(r){ return r.json(); }).then(function(resp) {
				if (resp.success && resp.data.webhook) {
					openEditor(resp.data.webhook);
				}
			});
		});
	});

	// Delete buttons.
	document.querySelectorAll('.xl-wh-delete-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			if (!confirm('<?php echo esc_js( __( 'Delete this webhook and its delivery log? This cannot be undone.', 'xtremeleads' ) ); ?>')) return;
			var id = btn.getAttribute('data-id');
			var fd = new FormData();
			fd.append('action', 'xl_webhook_delete');
			fd.append('nonce', nonce);
			fd.append('webhook_id', id);
			fetch(ajaxUrl, {method:'POST', body:fd}).then(function(r){ return r.json(); }).then(function(resp) {
				if (resp.success) {
					var row = document.querySelector('[data-webhook-id="'+id+'"]');
					if (row) row.remove();
				} else {
					alert((resp.data && resp.data.message) || '<?php echo esc_js( __( 'Delete failed.', 'xtremeleads' ) ); ?>');
				}
			});
		});
	});

	// Test fire buttons.
	document.querySelectorAll('.xl-wh-test-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var id = btn.getAttribute('data-id');
			var orig = btn.textContent;
			btn.textContent = '<?php echo esc_js( __( 'Sending…', 'xtremeleads' ) ); ?>';
			btn.disabled = true;

			var fd = new FormData();
			fd.append('action', 'xl_webhook_test');
			fd.append('nonce', nonce);
			fd.append('webhook_id', id);

			fetch(ajaxUrl, {method:'POST', body:fd})
				.then(function(r){ return r.json(); })
				.then(function(resp) {
					btn.textContent = orig;
					btn.disabled = false;
					var result = document.getElementById('xl-test-fire-result');
					result.style.display = '';
					if (resp.success) {
						var d = resp.data;
						document.getElementById('xl-tf-status').textContent = d.status || '';
						document.getElementById('xl-tf-http-code').textContent = d.http_code || '0';
						document.getElementById('xl-tf-response').textContent = d.response_body || '';
						var errRow = document.getElementById('xl-tf-error-row');
						if (d.error_message) {
							errRow.style.display = '';
							document.getElementById('xl-tf-error').textContent = d.error_message;
						} else {
							errRow.style.display = 'none';
						}
					} else {
						document.getElementById('xl-tf-status').textContent = 'error';
						document.getElementById('xl-tf-http-code').textContent = '0';
						document.getElementById('xl-tf-response').textContent = '';
						var errRow2 = document.getElementById('xl-tf-error-row');
						errRow2.style.display = '';
						document.getElementById('xl-tf-error').textContent = (resp.data && resp.data.message) || '<?php echo esc_js( __( 'Test fire failed.', 'xtremeleads' ) ); ?>';
					}
					result.scrollIntoView({behavior:'smooth', block:'start'});
				})
				.catch(function(e) {
					btn.textContent = orig;
					btn.disabled = false;
					alert('<?php echo esc_js( __( 'Network error. Test fire could not be sent.', 'xtremeleads' ) ); ?>');
				});
		});
	});

	// Delivery log buttons.
	document.querySelectorAll('.xl-wh-log-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var id = btn.getAttribute('data-id');
			var row = document.querySelector('[data-webhook-id="'+id+'"]');
			var name = row ? row.querySelector('strong').textContent : '#'+id;
			document.getElementById('xl-log-webhook-name').textContent = '— ' + name;
			loadLog(id, 1);
		});
	});

	function loadLog(webhookId, page) {
		var logWrap = document.getElementById('xl-delivery-log-wrap');
		var logContent = document.getElementById('xl-delivery-log-content');
		var logPager = document.getElementById('xl-log-pagination');
		logWrap.style.display = '';
		logContent.innerHTML = '<p><?php echo esc_js( __( 'Loading…', 'xtremeleads' ) ); ?></p>';
		logPager.innerHTML = '';

		var fd = new FormData();
		fd.append('action', 'xl_webhook_log');
		fd.append('nonce', nonce);
		fd.append('webhook_id', webhookId);
		fd.append('page', page);

		fetch(ajaxUrl, {method:'POST', body:fd})
			.then(function(r){ return r.json(); })
			.then(function(resp) {
				if (!resp.success) {
					logContent.innerHTML = '<p style="color:#DC3545;"><?php echo esc_js( __( 'Failed to load log.', 'xtremeleads' ) ); ?></p>';
					return;
				}
				var d = resp.data;
				if (!d.items || d.items.length === 0) {
					logContent.innerHTML = '<p><?php echo esc_js( __( 'No log entries found.', 'xtremeleads' ) ); ?></p>';
					return;
				}
				var html = '<table class="wp-list-table widefat striped"><thead><tr>'
					+ '<th><?php echo esc_js( __( 'Time', 'xtremeleads' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Recipient URL', 'xtremeleads' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Lead ID', 'xtremeleads' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Event', 'xtremeleads' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Status', 'xtremeleads' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'HTTP Code', 'xtremeleads' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Retry?', 'xtremeleads' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Response', 'xtremeleads' ) ); ?></th>'
					+ '</tr></thead><tbody>';

				d.items.forEach(function(entry) {
					var leadLabel = entry.lead_id == 0 ? '<?php echo esc_js( __( 'test', 'xtremeleads' ) ); ?>' : entry.lead_id;
					var statusClass = entry.status === 'sent' ? 'color:#28A745;' : 'color:#DC3545;';
					var retryLabel = parseInt(entry.is_retry) ? ('<?php echo esc_js( __( 'Retry', 'xtremeleads' ) ); ?> #' + (entry.original_attempt_id || '')) : '—';
					var urlDisplay = entry.url ? '<code style="font-size:11px;word-break:break-all;">' + esc(entry.url) + '</code>' : '<em>—</em>';
					html += '<tr>'
						+ '<td>' + esc(entry.delivered_at) + '</td>'
						+ '<td>' + urlDisplay + '</td>'
						+ '<td>' + esc(leadLabel) + '</td>'
						+ '<td>' + esc(entry.trigger_type) + '</td>'
						+ '<td style="' + statusClass + '"><strong>' + esc(entry.status) + '</strong></td>'
						+ '<td>' + esc(entry.http_code) + '</td>'
						+ '<td>' + esc(retryLabel) + '</td>'
						+ '<td><code style="font-size:11px;word-break:break-all;">' + esc(entry.response_body || '') + '</code></td>'
						+ '</tr>';
				});
				html += '</tbody></table>';
				logContent.innerHTML = html;

				// Pagination.
				if (d.pages > 1) {
					var pHtml = '';
					for (var p = 1; p <= d.pages; p++) {
						pHtml += '<button type="button" class="button' + (p === page ? ' button-primary' : '') + '" data-page="' + p + '" style="margin-right:4px;">' + p + '</button>';
					}
					logPager.innerHTML = pHtml;
					logPager.querySelectorAll('button').forEach(function(pb) {
						pb.addEventListener('click', function() { loadLog(webhookId, parseInt(pb.getAttribute('data-page'))); });
					});
				}

				logWrap.scrollIntoView({behavior:'smooth', block:'start'});
			});
	}

	function esc(str) {
		return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}
})();
</script>
