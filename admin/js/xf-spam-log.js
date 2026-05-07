/* global xtremeFormsSpamLogData, xtremeFormsSpamLogI18n */
(function(){
	'use strict';

	var data = window.xtremeFormsSpamLogData || {};
	var i18n = window.xtremeFormsSpamLogI18n || {};
	var nonce = data.nonce || '';
	var ajaxUrl = data.ajaxUrl || '';

	function t( key, fallback ) {
		return ( i18n && i18n[ key ] ) || fallback;
	}

	// Delete single entry.
	document.querySelectorAll('.xf-spam-delete-entry').forEach(function(btn) {
		btn.addEventListener('click', function() {
			if (!confirm( t( 'confirmDeleteEntry', 'Permanently delete this spam log entry?' ) )) return;
			var id = btn.getAttribute('data-id');
			var fd = new FormData();
			fd.append('action', 'xtremeforms_spam_log_delete');
			fd.append('nonce', nonce);
			fd.append('entry_id', id);
			fetch(ajaxUrl, {method:'POST', body:fd})
				.then(function(r){ return r.json(); })
				.then(function(resp) {
					if (resp.success) {
						var row = document.querySelector('[data-entry-id="'+id+'"]');
						if (row) row.remove();
					} else {
						alert((resp.data && resp.data.message) || t( 'deleteFailed', 'Delete failed.' ));
					}
				});
		});
	});

	// Clear all.
	var clearBtn = document.getElementById('xf-spam-clear-all-btn');
	if (clearBtn) {
		clearBtn.addEventListener('click', function() {
			if (!confirm( t( 'confirmClearAll', 'Permanently clear the entire spam log? This cannot be undone.' ) )) return;
			var msg = document.getElementById('xf-spam-clear-msg');
			msg.textContent = t( 'clearing', 'Clearing…' );
			var fd = new FormData();
			fd.append('action', 'xtremeforms_spam_log_clear');
			fd.append('nonce', nonce);
			fetch(ajaxUrl, {method:'POST', body:fd})
				.then(function(r){ return r.json(); })
				.then(function(resp) {
					if (resp.success) {
						msg.textContent = t( 'cleared', 'Spam log cleared.' );
						msg.style.color = '#28A745';
						setTimeout(function(){ location.reload(); }, 800);
					} else {
						msg.textContent = t( 'clearFailed', 'Clear failed.' );
						msg.style.color = '#DC3545';
					}
				});
		});
	}
})();
