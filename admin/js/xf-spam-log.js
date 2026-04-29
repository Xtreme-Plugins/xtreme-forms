/**
 * Xtreme Forms — Spam log admin page scripts.
 *
 * Extracted from inline <script> in admin/partials/xf-admin-spam-log.php
 * for WordPress.org Plugin Check compliance. Per-render data and translatable
 * strings are exposed by the partial via wp_localize_script.
 */

(function(){
	var data = window.xfSpamLogData || {};
	var i18n = window.xfSpamLogI18n || {};
	var nonce = data.nonce || '';
	var ajaxUrl = data.ajaxUrl || '';

	// Delete single entry.
	document.querySelectorAll('.xf-spam-delete-entry').forEach(function(btn) {
		btn.addEventListener('click', function() {
			if (!confirm(i18n.confirmDelete || '')) return;
			var id = btn.getAttribute('data-id');
			var fd = new FormData();
			fd.append('action', 'xf_spam_log_delete');
			fd.append('nonce', nonce);
			fd.append('entry_id', id);
			fetch(ajaxUrl, {method:'POST', body:fd})
				.then(function(r){ return r.json(); })
				.then(function(resp) {
					if (resp.success) {
						var row = document.querySelector('[data-entry-id="'+id+'"]');
						if (row) row.remove();
					} else {
						alert((resp.data && resp.data.message) || i18n.deleteFailed || '');
					}
				});
		});
	});

	// Clear all.
	var clearBtn = document.getElementById('xf-spam-clear-all-btn');
	if (clearBtn) {
		clearBtn.addEventListener('click', function() {
			if (!confirm(i18n.confirmClearAll || '')) return;
			var msg = document.getElementById('xf-spam-clear-msg');
			msg.textContent = i18n.clearing || '';
			var fd = new FormData();
			fd.append('action', 'xf_spam_log_clear');
			fd.append('nonce', nonce);
			fetch(ajaxUrl, {method:'POST', body:fd})
				.then(function(r){ return r.json(); })
				.then(function(resp) {
					if (resp.success) {
						msg.textContent = i18n.spamLogCleared || '';
						msg.style.color = '#28A745';
						setTimeout(function(){ location.reload(); }, 800);
					} else {
						msg.textContent = i18n.clearFailed || '';
						msg.style.color = '#DC3545';
					}
				});
		});
	}
})();
