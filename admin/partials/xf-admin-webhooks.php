<?php
/**
 * Webhooks admin page — CRUD interface + delivery log.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- GET parameters on this admin display page are read-only filter params.

$webhooks  = XF_Webhooks::get_all();
$all_forms = XF_Forms::get_all_forms();
$nonce     = wp_create_nonce( 'xf_webhook_nonce' );

$notice_html = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Webhook saved.', 'xtreme-forms' ) . '</p></div>';
}
?>
<div class="wrap xf-wrap" id="xf-webhooks-page">
	<h1 class="xf-page-title"><?php esc_html_e( 'Webhooks', 'xtreme-forms' ); ?></h1>
	<?php echo wp_kses_post( $notice_html ); ?>

	<div class="xf-card" style="margin-bottom:24px;">
		<p><?php esc_html_e( 'Configure outbound webhook endpoints that receive lead data when events occur. Each webhook sends a POST request with a JSON payload.', 'xtreme-forms' ); ?></p>
		<button type="button" class="button button-primary xf-btn-primary" id="xf-add-webhook-btn">
			+ <?php esc_html_e( 'Add Webhook', 'xtreme-forms' ); ?>
		</button>
	</div>

	<!-- Webhook editor (hidden by default) -->
	<div id="xf-webhook-editor" class="xf-card" style="display:none;margin-bottom:24px;">
		<h2 id="xf-webhook-editor-title"><?php esc_html_e( 'Add Webhook', 'xtreme-forms' ); ?></h2>
		<input type="hidden" id="xf-webhook-id" value="0">

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="xf-wh-name"><?php esc_html_e( 'Name', 'xtreme-forms' ); ?></label></th>
					<td>
						<input type="text" id="xf-wh-name" class="regular-text" placeholder="<?php esc_attr_e( 'My CRM Webhook', 'xtreme-forms' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="xf-wh-url"><?php esc_html_e( 'Endpoint URL', 'xtreme-forms' ); ?></label></th>
					<td>
						<input type="url" id="xf-wh-url" class="large-text" placeholder="https://example.com/webhook">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Trigger Events', 'xtreme-forms' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:8px;">
							<input type="checkbox" id="xf-wh-event-new-lead" value="new_lead" checked>
							<?php esc_html_e( 'New Lead', 'xtreme-forms' ); ?>
						</label>
						<label style="display:block;">
							<input type="checkbox" id="xf-wh-event-status-change" value="status_change">
							<?php esc_html_e( 'Status Change', 'xtreme-forms' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Form Filter', 'xtreme-forms' ); ?></th>
					<td>
						<p class="description" style="margin-bottom:8px;"><?php esc_html_e( 'Leave all unchecked to receive events from all forms.', 'xtreme-forms' ); ?></p>
						<?php foreach ( $all_forms as $form ) : ?>
							<label style="display:block;margin-bottom:4px;">
								<input type="checkbox" class="xf-wh-form-filter" value="<?php echo esc_attr( $form->id ); ?>">
								<?php echo esc_html( $form->name ); ?>
							</label>
						<?php endforeach; ?>
						<?php if ( empty( $all_forms ) ) : ?>
							<p class="description"><?php esc_html_e( 'No forms found.', 'xtreme-forms' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Active', 'xtreme-forms' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="xf-wh-active" value="1" checked>
							<?php esc_html_e( 'Enable this webhook', 'xtreme-forms' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Custom Headers', 'xtreme-forms' ); ?></th>
					<td>
						<p class="description" style="margin-bottom:8px;"><?php esc_html_e( 'Add custom request headers (e.g., Authorization tokens). Header values are stored securely and never exposed in front-end HTML.', 'xtreme-forms' ); ?></p>
						<div id="xf-wh-headers-list"></div>
						<button type="button" class="button" id="xf-add-header-btn" style="margin-top:8px;">
							+ <?php esc_html_e( 'Add Header', 'xtreme-forms' ); ?>
						</button>
						<div id="xf-header-content-type-warning" class="notice notice-warning inline" style="display:none;margin-top:8px;">
							<p><?php esc_html_e( 'Note: Adding a "Content-Type" header will override the default "application/json" value.', 'xtreme-forms' ); ?></p>
						</div>
					</td>
				</tr>
			</tbody>
		</table>

		<div style="margin-top:16px;">
			<button type="button" class="button button-primary xf-btn-primary" id="xf-save-webhook-btn"><?php esc_html_e( 'Save Webhook', 'xtreme-forms' ); ?></button>
			<button type="button" class="button" id="xf-cancel-webhook-btn" style="margin-left:8px;"><?php esc_html_e( 'Cancel', 'xtreme-forms' ); ?></button>
			<span id="xf-webhook-save-msg" style="margin-left:12px;"></span>
		</div>
	</div>

	<!-- Webhooks list -->
	<div class="xf-card" style="margin-bottom:24px;">
		<h2><?php esc_html_e( 'Configured Webhooks', 'xtreme-forms' ); ?></h2>

		<?php if ( empty( $webhooks ) ) : ?>
			<p><?php esc_html_e( 'No webhooks configured yet. Click "Add Webhook" to create one.', 'xtreme-forms' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat striped" id="xf-webhooks-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'URL', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Events', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Forms', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'xtreme-forms' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $webhooks as $wh ) :
					$events       = json_decode( $wh->trigger_events, true ) ?: array();
					$form_ids     = json_decode( $wh->form_ids, true ) ?: array();
					$form_names   = empty( $form_ids ) ? __( 'All forms', 'xtreme-forms' ) : implode(
						', ',
						array_map(
							function ( $fid ) use ( $all_forms ) {
								foreach ( $all_forms as $f ) {
									if ( (int) $f->id === (int) $fid ) {
										return esc_html( $f->name );
									}
								}
								return '#' . $fid;
							},
							$form_ids
						)
					);
					$event_labels = array_map(
						function ( $e ) {
							return 'new_lead' === $e ? __( 'New Lead', 'xtreme-forms' ) : __( 'Status Change', 'xtreme-forms' );
						},
						$events
					);
					?>
					<tr data-webhook-id="<?php echo esc_attr( $wh->id ); ?>">
						<td><strong><?php echo esc_html( $wh->name ); ?></strong></td>
						<td><code style="word-break:break-all;"><?php echo esc_html( $wh->url ); ?></code></td>
						<td><?php echo esc_html( implode( ', ', $event_labels ) ); ?></td>
						<td><?php echo esc_html( $form_names ); ?></td>
						<td>
							<?php if ( $wh->is_active ) : ?>
								<span class="xf-badge xf-badge-success"><?php esc_html_e( 'Active', 'xtreme-forms' ); ?></span>
							<?php else : ?>
								<span class="xf-badge xf-badge-secondary"><?php esc_html_e( 'Inactive', 'xtreme-forms' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button xf-wh-edit-btn" data-id="<?php echo esc_attr( $wh->id ); ?>"><?php esc_html_e( 'Edit', 'xtreme-forms' ); ?></button>
							<button type="button" class="button xf-wh-test-btn" data-id="<?php echo esc_attr( $wh->id ); ?>"><?php esc_html_e( 'Test Fire', 'xtreme-forms' ); ?></button>
							<button type="button" class="button xf-wh-log-btn" data-id="<?php echo esc_attr( $wh->id ); ?>"><?php esc_html_e( 'View Log', 'xtreme-forms' ); ?></button>
							<button type="button" class="button button-link-delete xf-wh-delete-btn" data-id="<?php echo esc_attr( $wh->id ); ?>"><?php esc_html_e( 'Delete', 'xtreme-forms' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<!-- Test fire result -->
	<div id="xf-test-fire-result" class="xf-card" style="display:none;margin-bottom:24px;">
		<h2><?php esc_html_e( 'Test Fire Result', 'xtreme-forms' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr><th><?php esc_html_e( 'Status', 'xtreme-forms' ); ?></th><td id="xf-tf-status"></td></tr>
				<tr><th><?php esc_html_e( 'HTTP Code', 'xtreme-forms' ); ?></th><td id="xf-tf-http-code"></td></tr>
				<tr><th><?php esc_html_e( 'Response', 'xtreme-forms' ); ?></th><td><pre id="xf-tf-response" style="white-space:pre-wrap;word-break:break-all;max-height:200px;overflow:auto;"></pre></td></tr>
				<tr id="xf-tf-error-row" style="display:none;"><th><?php esc_html_e( 'Error', 'xtreme-forms' ); ?></th><td id="xf-tf-error" style="color:#DC3545;"></td></tr>
			</tbody>
		</table>
	</div>

	<!-- Delivery Log -->
	<div id="xf-delivery-log-wrap" class="xf-card" style="display:none;">
		<h2><?php esc_html_e( 'Delivery Log', 'xtreme-forms' ); ?> <span id="xf-log-webhook-name"></span></h2>
		<div id="xf-delivery-log-content">
			<p><?php esc_html_e( 'Loading…', 'xtreme-forms' ); ?></p>
		</div>
		<div id="xf-log-pagination" style="margin-top:12px;"></div>
	</div>

</div><!-- .xf-wrap -->

<style>
.xf-header-row { display:flex; gap:8px; margin-bottom:8px; align-items:center; }
.xf-header-row input { flex:1; }
.xf-header-row .xf-remove-header { color:#DC3545; cursor:pointer; background:none; border:none; font-size:18px; line-height:1; }
</style>

<script>
(function() {
	var nonce = <?php echo wp_json_encode( $nonce ); ?>;
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;
	var allForms = 
	<?php
	echo wp_json_encode(
		array_map(
			function ( $f ) {
				return array(
					'id'   => (int) $f->id,
					'name' => $f->name,
				);
			},
			$all_forms
		)
	);
	?>
	;

	var editor = document.getElementById('xf-webhook-editor');
	var addBtn = document.getElementById('xf-add-webhook-btn');
	var saveBtn = document.getElementById('xf-save-webhook-btn');
	var cancelBtn = document.getElementById('xf-cancel-webhook-btn');
	var addHeaderBtn = document.getElementById('xf-add-header-btn');
	var headersList = document.getElementById('xf-wh-headers-list');
	var ctWarning = document.getElementById('xf-header-content-type-warning');

	function openEditor( webhookData ) {
		document.getElementById('xf-webhook-editor-title').textContent = webhookData ? '<?php echo esc_js( __( 'Edit Webhook', 'xtreme-forms' ) ); ?>' : '<?php echo esc_js( __( 'Add Webhook', 'xtreme-forms' ) ); ?>';
		document.getElementById('xf-webhook-id').value = webhookData ? webhookData.id : 0;
		document.getElementById('xf-wh-name').value = webhookData ? webhookData.name : '';
		document.getElementById('xf-wh-url').value = webhookData ? webhookData.url : '';

		var events = webhookData ? (JSON.parse(webhookData.trigger_events) || []) : ['new_lead'];
		document.getElementById('xf-wh-event-new-lead').checked = events.indexOf('new_lead') > -1;
		document.getElementById('xf-wh-event-status-change').checked = events.indexOf('status_change') > -1;

		var formIds = webhookData ? (JSON.parse(webhookData.form_ids) || []) : [];
		document.querySelectorAll('.xf-wh-form-filter').forEach(function(cb) {
			cb.checked = formIds.indexOf(parseInt(cb.value)) > -1;
		});

		document.getElementById('xf-wh-active').checked = webhookData ? !!parseInt(webhookData.is_active) : true;

		// Populate headers.
		headersList.innerHTML = '';
		var headers = webhookData ? (JSON.parse(webhookData.custom_headers) || []) : [];
		headers.forEach(function(h) { addHeaderRow(h.name, h.value); });

		editor.style.display = '';
		editor.scrollIntoView({behavior:'smooth', block:'start'});
		document.getElementById('xf-webhook-save-msg').textContent = '';
	}

	function closeEditor() {
		editor.style.display = 'none';
	}

	function addHeaderRow(name, value) {
		name = name || '';
		value = value || '';
		var row = document.createElement('div');
		row.className = 'xf-header-row';
		row.innerHTML = '<input type="text" placeholder="<?php echo esc_js( __( 'Header Name', 'xtreme-forms' ) ); ?>" value="' + escAttr(name) + '" class="xf-header-name" maxlength="256">'
			+ '<input type="text" placeholder="<?php echo esc_js( __( 'Header Value', 'xtreme-forms' ) ); ?>" value="' + escAttr(value) + '" class="xf-header-value" maxlength="256">'
			+ '<button type="button" class="xf-remove-header" title="<?php echo esc_js( __( 'Remove', 'xtreme-forms' ) ); ?>">&times;</button>';
		row.querySelector('.xf-remove-header').addEventListener('click', function() { row.remove(); checkCtWarning(); });
		row.querySelector('.xf-header-name').addEventListener('input', checkCtWarning);
		headersList.appendChild(row);
	}

	function checkCtWarning() {
		var names = Array.from(headersList.querySelectorAll('.xf-header-name')).map(function(i){ return i.value.trim().toLowerCase(); });
		ctWarning.style.display = names.indexOf('content-type') > -1 ? '' : 'none';
	}

	function escAttr(str) {
		return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
	}

	addBtn.addEventListener('click', function() { openEditor(null); });
	cancelBtn.addEventListener('click', closeEditor);
	addHeaderBtn.addEventListener('click', function() { addHeaderRow('', ''); });

	saveBtn.addEventListener('click', function() {
		var msg = document.getElementById('xf-webhook-save-msg');
		msg.textContent = '<?php echo esc_js( __( 'Saving…', 'xtreme-forms' ) ); ?>';

		var events = [];
		if (document.getElementById('xf-wh-event-new-lead').checked) events.push('new_lead');
		if (document.getElementById('xf-wh-event-status-change').checked) events.push('status_change');

		var formIds = Array.from(document.querySelectorAll('.xf-wh-form-filter:checked')).map(function(c){ return parseInt(c.value); });

		var headers = [];
		document.querySelectorAll('.xf-header-row').forEach(function(row) {
			var name = row.querySelector('.xf-header-name').value.trim();
			var val = row.querySelector('.xf-header-value').value;
			if (name) headers.push({name: name, value: val});
		});

		var data = new FormData();
		data.append('action', 'xf_webhook_save');
		data.append('nonce', nonce);
		data.append('webhook[id]', document.getElementById('xf-webhook-id').value);
		data.append('webhook[name]', document.getElementById('xf-wh-name').value);
		data.append('webhook[url]', document.getElementById('xf-wh-url').value);
		data.append('webhook[trigger_events]', JSON.stringify(events));
		data.append('webhook[form_ids]', JSON.stringify(formIds));
		data.append('webhook[is_active]', document.getElementById('xf-wh-active').checked ? '1' : '0');
		data.append('webhook[custom_headers]', JSON.stringify(headers));

		fetch(ajaxUrl, {method:'POST', body:data})
			.then(function(r){ return r.json(); })
			.then(function(resp) {
				if (resp.success) {
					msg.textContent = '<?php echo esc_js( __( 'Saved!', 'xtreme-forms' ) ); ?>';
					msg.style.color = '#28A745';
					setTimeout(function(){ location.reload(); }, 800);
				} else {
					msg.textContent = (resp.data && resp.data.message) || '<?php echo esc_js( __( 'Save failed.', 'xtreme-forms' ) ); ?>';
					msg.style.color = '#DC3545';
				}
			})
			.catch(function() {
				msg.textContent = '<?php echo esc_js( __( 'Network error.', 'xtreme-forms' ) ); ?>';
				msg.style.color = '#DC3545';
			});
	});

	// Edit buttons.
	document.querySelectorAll('.xf-wh-edit-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var id = btn.getAttribute('data-id');
			var fd = new FormData();
			fd.append('action', 'xf_webhook_get');
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
	document.querySelectorAll('.xf-wh-delete-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			if (!confirm('<?php echo esc_js( __( 'Delete this webhook and its delivery log? This cannot be undone.', 'xtreme-forms' ) ); ?>')) return;
			var id = btn.getAttribute('data-id');
			var fd = new FormData();
			fd.append('action', 'xf_webhook_delete');
			fd.append('nonce', nonce);
			fd.append('webhook_id', id);
			fetch(ajaxUrl, {method:'POST', body:fd}).then(function(r){ return r.json(); }).then(function(resp) {
				if (resp.success) {
					var row = document.querySelector('[data-webhook-id="'+id+'"]');
					if (row) row.remove();
				} else {
					alert((resp.data && resp.data.message) || '<?php echo esc_js( __( 'Delete failed.', 'xtreme-forms' ) ); ?>');
				}
			});
		});
	});

	// Test fire buttons.
	document.querySelectorAll('.xf-wh-test-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var id = btn.getAttribute('data-id');
			var orig = btn.textContent;
			btn.textContent = '<?php echo esc_js( __( 'Sending…', 'xtreme-forms' ) ); ?>';
			btn.disabled = true;

			var fd = new FormData();
			fd.append('action', 'xf_webhook_test');
			fd.append('nonce', nonce);
			fd.append('webhook_id', id);

			fetch(ajaxUrl, {method:'POST', body:fd})
				.then(function(r){ return r.json(); })
				.then(function(resp) {
					btn.textContent = orig;
					btn.disabled = false;
					var result = document.getElementById('xf-test-fire-result');
					result.style.display = '';
					if (resp.success) {
						var d = resp.data;
						document.getElementById('xf-tf-status').textContent = d.status || '';
						document.getElementById('xf-tf-http-code').textContent = d.http_code || '0';
						document.getElementById('xf-tf-response').textContent = d.response_body || '';
						var errRow = document.getElementById('xf-tf-error-row');
						if (d.error_message) {
							errRow.style.display = '';
							document.getElementById('xf-tf-error').textContent = d.error_message;
						} else {
							errRow.style.display = 'none';
						}
					} else {
						document.getElementById('xf-tf-status').textContent = 'error';
						document.getElementById('xf-tf-http-code').textContent = '0';
						document.getElementById('xf-tf-response').textContent = '';
						var errRow2 = document.getElementById('xf-tf-error-row');
						errRow2.style.display = '';
						document.getElementById('xf-tf-error').textContent = (resp.data && resp.data.message) || '<?php echo esc_js( __( 'Test fire failed.', 'xtreme-forms' ) ); ?>';
					}
					result.scrollIntoView({behavior:'smooth', block:'start'});
				})
				.catch(function(e) {
					btn.textContent = orig;
					btn.disabled = false;
					alert('<?php echo esc_js( __( 'Network error. Test fire could not be sent.', 'xtreme-forms' ) ); ?>');
				});
		});
	});

	// Delivery log buttons.
	document.querySelectorAll('.xf-wh-log-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var id = btn.getAttribute('data-id');
			var row = document.querySelector('[data-webhook-id="'+id+'"]');
			var name = row ? row.querySelector('strong').textContent : '#'+id;
			document.getElementById('xf-log-webhook-name').textContent = '— ' + name;
			loadLog(id, 1);
		});
	});

	function loadLog(webhookId, page) {
		var logWrap = document.getElementById('xf-delivery-log-wrap');
		var logContent = document.getElementById('xf-delivery-log-content');
		var logPager = document.getElementById('xf-log-pagination');
		logWrap.style.display = '';
		logContent.innerHTML = '<p><?php echo esc_js( __( 'Loading…', 'xtreme-forms' ) ); ?></p>';
		logPager.innerHTML = '';

		var fd = new FormData();
		fd.append('action', 'xf_webhook_log');
		fd.append('nonce', nonce);
		fd.append('webhook_id', webhookId);
		fd.append('page', page);

		fetch(ajaxUrl, {method:'POST', body:fd})
			.then(function(r){ return r.json(); })
			.then(function(resp) {
				if (!resp.success) {
					logContent.innerHTML = '<p style="color:#DC3545;"><?php echo esc_js( __( 'Failed to load log.', 'xtreme-forms' ) ); ?></p>';
					return;
				}
				var d = resp.data;
				if (!d.items || d.items.length === 0) {
					logContent.innerHTML = '<p><?php echo esc_js( __( 'No log entries found.', 'xtreme-forms' ) ); ?></p>';
					return;
				}
				var html = '<table class="wp-list-table widefat striped"><thead><tr>'
					+ '<th><?php echo esc_js( __( 'Time', 'xtreme-forms' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Recipient URL', 'xtreme-forms' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Lead ID', 'xtreme-forms' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Event', 'xtreme-forms' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Status', 'xtreme-forms' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'HTTP Code', 'xtreme-forms' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Retry?', 'xtreme-forms' ) ); ?></th>'
					+ '<th><?php echo esc_js( __( 'Response', 'xtreme-forms' ) ); ?></th>'
					+ '</tr></thead><tbody>';

				d.items.forEach(function(entry) {
					var leadLabel = entry.lead_id == 0 ? '<?php echo esc_js( __( 'test', 'xtreme-forms' ) ); ?>' : entry.lead_id;
					var statusClass = entry.status === 'sent' ? 'color:#28A745;' : 'color:#DC3545;';
					var retryLabel = parseInt(entry.is_retry) ? ('<?php echo esc_js( __( 'Retry', 'xtreme-forms' ) ); ?> #' + (entry.original_attempt_id || '')) : '—';
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
