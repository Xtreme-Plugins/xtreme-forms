/**
 * Xtreme Forms — Settings page scripts.
 *
 * Extracted from inline <script> blocks in admin/partials/xf-admin-settings.php
 * for WordPress.org Plugin Check compliance.
 */

(function() {
	var tabs = document.querySelectorAll('#xf-bot-tabs .xf-bot-tab');
	tabs.forEach(function(btn) {
		btn.addEventListener('click', function() {
			var target = this.dataset.tab;
			// Update panels
			document.querySelectorAll('.xf-bot-panel').forEach(function(p) {
				p.style.display = 'none';
			});
			document.getElementById('xf-bot-panel-' + target).style.display = 'block';
			// Update tab styles
			tabs.forEach(function(t) {
				var isActive = t.dataset.tab === target;
				var color = t.dataset.tab === 'turnstile' ? '#f38020' : '#4285F4';
				t.style.color = isActive ? color : '#52525b';
				t.style.borderBottomColor = isActive ? color : 'transparent';
			});
		});
	});
})();

function xlToggleDupMessage( val ) {
	var row = document.getElementById( 'xf-dup-block-message-row' );
	if ( row ) {
		row.style.display = ( 'block' === val ) ? '' : 'none';
	}
}
