/* global xtremeFormsWebhooksData, xtremeFormsWebhooksI18n */
(function() {
	'use strict';

	var data    = window.xtremeFormsWebhooksData || {};
	var i18n    = window.xtremeFormsWebhooksI18n || {};
	var nonce   = data.nonce   || '';
	var ajaxUrl = data.ajaxUrl || '';

	function t( key, fallback ) {
		return ( i18n && i18n[ key ] ) || fallback;
	}

	var editor = document.getElementById('xf-webhook-editor');
	var addBtn = document.getElementById('xf-add-webhook-btn');
	var saveBtn = document.getElementById('xf-save-webhook-btn');
	var cancelBtn = document.getElementById('xf-cancel-webhook-btn');
	var addHeaderBtn = document.getElementById('xf-add-header-btn');
	var headersList = document.getElementById('xf-wh-headers-list');
	var ctWarning = document.getElementById('xf-header-content-type-warning');

	function openEditor( webhookData ) {
		document.getElementById('xf-webhook-editor-title').textContent = webhookData ? t( 'editWebhook', 'Edit Webhook' ) : t( 'addWebhook', 'Add Webhook' );
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
		row.innerHTML = '<input type="text" placeholder="' + escAttr( t( 'headerName', 'Header Name' ) ) + '" value="' + escAttr(name) + '" class="xf-header-name" maxlength="256">'
			+ '<input type="text" placeholder="' + escAttr( t( 'headerValue', 'Header Value' ) ) + '" value="' + escAttr(value) + '" class="xf-header-value" maxlength="256">'
			+ '<button type="button" class="xf-remove-header" title="' + escAttr( t( 'remove', 'Remove' ) ) + '">&times;</button>';
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

	if ( addBtn ) addBtn.addEventListener('click', function() { openEditor(null); });
	if ( cancelBtn ) cancelBtn.addEventListener('click', closeEditor);
	if ( addHeaderBtn ) addHeaderBtn.addEventListener('click', function() { addHeaderRow('', ''); });

	if ( saveBtn ) saveBtn.addEventListener('click', function() {
		var msg = document.getElementById('xf-webhook-save-msg');
		msg.textContent = t( 'saving', 'Saving…' );

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

		var payload = new FormData();
		payload.append('action', 'xtremeforms_webhook_save');
		payload.append('nonce', nonce);
		payload.append('webhook[id]', document.getElementById('xf-webhook-id').value);
		payload.append('webhook[name]', document.getElementById('xf-wh-name').value);
		payload.append('webhook[url]', document.getElementById('xf-wh-url').value);
		payload.append('webhook[trigger_events]', JSON.stringify(events));
		payload.append('webhook[form_ids]', JSON.stringify(formIds));
		payload.append('webhook[is_active]', document.getElementById('xf-wh-active').checked ? '1' : '0');
		payload.append('webhook[custom_headers]', JSON.stringify(headers));

		fetch(ajaxUrl, {method:'POST', body:payload})
			.then(function(r){ return r.json(); })
			.then(function(resp) {
				if (resp.success) {
					msg.textContent = t( 'saved', 'Saved!' );
					msg.style.color = '#28A745';
					setTimeout(function(){ location.reload(); }, 800);
				} else {
					msg.textContent = (resp.data && resp.data.message) || t( 'saveFailed', 'Save failed.' );
					msg.style.color = '#DC3545';
				}
			})
			.catch(function() {
				msg.textContent = t( 'networkError', 'Network error.' );
				msg.style.color = '#DC3545';
			});
	});

	// Edit buttons.
	document.querySelectorAll('.xf-wh-edit-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var id = btn.getAttribute('data-id');
			var fd = new FormData();
			fd.append('action', 'xtremeforms_webhook_get');
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
			if (!confirm( t( 'confirmDelete', 'Delete this webhook and its delivery log? This cannot be undone.' ) )) return;
			var id = btn.getAttribute('data-id');
			var fd = new FormData();
			fd.append('action', 'xtremeforms_webhook_delete');
			fd.append('nonce', nonce);
			fd.append('webhook_id', id);
			fetch(ajaxUrl, {method:'POST', body:fd}).then(function(r){ return r.json(); }).then(function(resp) {
				if (resp.success) {
					var row = document.querySelector('[data-webhook-id="'+id+'"]');
					if (row) row.remove();
				} else {
					alert((resp.data && resp.data.message) || t( 'deleteFailed', 'Delete failed.' ));
				}
			});
		});
	});

	// Test fire buttons.
	document.querySelectorAll('.xf-wh-test-btn').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var id = btn.getAttribute('data-id');
			var orig = btn.textContent;
			btn.textContent = t( 'sending', 'Sending…' );
			btn.disabled = true;

			var fd = new FormData();
			fd.append('action', 'xtremeforms_webhook_test');
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
						document.getElementById('xf-tf-error').textContent = (resp.data && resp.data.message) || t( 'testFireFailed', 'Test fire failed.' );
					}
					result.scrollIntoView({behavior:'smooth', block:'start'});
				})
				.catch(function() {
					btn.textContent = orig;
					btn.disabled = false;
					alert( t( 'networkErrorTestFire', 'Network error. Test fire could not be sent.' ) );
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
		logContent.innerHTML = '<p>' + escAttr( t( 'loading', 'Loading…' ) ) + '</p>';
		logPager.innerHTML = '';

		var fd = new FormData();
		fd.append('action', 'xtremeforms_webhook_log');
		fd.append('nonce', nonce);
		fd.append('webhook_id', webhookId);
		fd.append('page', page);

		fetch(ajaxUrl, {method:'POST', body:fd})
			.then(function(r){ return r.json(); })
			.then(function(resp) {
				if (!resp.success) {
					logContent.innerHTML = '<p style="color:#DC3545;">' + escAttr( t( 'failedToLoadLog', 'Failed to load log.' ) ) + '</p>';
					return;
				}
				var d = resp.data;
				if (!d.items || d.items.length === 0) {
					logContent.innerHTML = '<p>' + escAttr( t( 'noLogEntries', 'No log entries found.' ) ) + '</p>';
					return;
				}
				var html = '<table class="wp-list-table widefat striped"><thead><tr>'
					+ '<th>' + escAttr( t( 'time', 'Time' ) ) + '</th>'
					+ '<th>' + escAttr( t( 'recipientUrl', 'Recipient URL' ) ) + '</th>'
					+ '<th>' + escAttr( t( 'leadId', 'Lead ID' ) ) + '</th>'
					+ '<th>' + escAttr( t( 'event', 'Event' ) ) + '</th>'
					+ '<th>' + escAttr( t( 'status', 'Status' ) ) + '</th>'
					+ '<th>' + escAttr( t( 'httpCode', 'HTTP Code' ) ) + '</th>'
					+ '<th>' + escAttr( t( 'retry', 'Retry?' ) ) + '</th>'
					+ '<th>' + escAttr( t( 'response', 'Response' ) ) + '</th>'
					+ '</tr></thead><tbody>';

				d.items.forEach(function(entry) {
					var leadLabel = entry.lead_id == 0 ? t( 'test', 'test' ) : entry.lead_id;
					var statusClass = entry.status === 'sent' ? 'color:#28A745;' : 'color:#DC3545;';
					var retryLabel = parseInt(entry.is_retry) ? ( t( 'retryLabel', 'Retry' ) + ' #' + (entry.original_attempt_id || '')) : '—';
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
