(function () {
	'use strict';

	// ── Bot/Spam Protection tabs ────────────────────────────────────────────
	var tabs = document.querySelectorAll( '#xf-bot-tabs .xf-bot-tab' );
	tabs.forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var target = this.dataset.tab;
			// Update panels.
			document.querySelectorAll( '.xf-bot-panel' ).forEach( function ( p ) {
				p.style.display = 'none';
			} );
			var panel = document.getElementById( 'xf-bot-panel-' + target );
			if ( panel ) panel.style.display = 'block';
			// Update tab styles.
			tabs.forEach( function ( t ) {
				var isActive = t.dataset.tab === target;
				var color = t.dataset.tab === 'turnstile' ? '#f38020' : '#4285F4';
				t.style.color = isActive ? color : '#52525b';
				t.style.borderBottomColor = isActive ? color : 'transparent';
			} );
		} );
	} );

	// ── Duplicate-behavior visibility toggle ─────────────────────────────────
	// Originally invoked via inline onchange="xlToggleDupMessage(this.value)".
	// We bind it programmatically here; the inline attribute calls a global of the same name (kept below).
	function toggleDupMessage( val ) {
		var row = document.getElementById( 'xf-dup-block-message-row' );
		if ( row ) {
			row.style.display = ( 'block' === val ) ? '' : 'none';
		}
	}

	// Expose globally so existing inline onchange="xlToggleDupMessage(this.value)" still works.
	window.xlToggleDupMessage = toggleDupMessage;

	var dupSelect = document.getElementById( 'duplicate_behavior' );
	if ( dupSelect ) {
		dupSelect.addEventListener( 'change', function () {
			toggleDupMessage( dupSelect.value );
		} );
	}
} )();
