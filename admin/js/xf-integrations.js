/* global xtremeFormsIntegrationsData, xtremeFormsIntegrationsI18n */
( function () {
	'use strict';

	var data    = window.xtremeFormsIntegrationsData || {};
	var i18n    = window.xtremeFormsIntegrationsI18n || {};
	var ajaxUrl = data.ajaxUrl || '';
	var nonce   = data.nonce   || '';

	function t( key, fallback ) {
		return ( i18n && i18n[ key ] ) || fallback;
	}

	// ── Toggle panels ────────────────────────────────────────────────────────

	document.querySelectorAll( '.xf-integration-toggle-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var slug  = btn.getAttribute( 'data-integration' );
			var panel = document.getElementById( 'xf-int-panel-' + slug );
			if ( ! panel ) return;

			var isOpen = panel.style.display !== 'none';
			panel.style.display = isOpen ? 'none' : '';
			btn.setAttribute( 'aria-expanded', isOpen ? 'false' : 'true' );
			btn.textContent = isOpen
				? t( 'configure', 'Configure' )
				: t( 'close', 'Close' );
		} );
	} );

	// ── Save forms ───────────────────────────────────────────────────────────

	document.querySelectorAll( '.xf-integration-form' ).forEach( function ( form ) {
		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();

			var slug    = form.getAttribute( 'data-integration' );
			var msgEl   = form.querySelector( '.xf-integration-save-msg' );
			var saveBtn = form.querySelector( '[type="submit"]' );
			var origTxt = saveBtn ? saveBtn.textContent : '';

			if ( saveBtn ) {
				saveBtn.disabled    = true;
				saveBtn.textContent = t( 'saving', 'Saving…' );
			}

			// Collect form field values into a plain object.
			var data    = {};
			var enabled = form.querySelector( '[name="enabled"]' );
			data.enabled = ( enabled && enabled.checked ) ? '1' : '0';

			form.querySelectorAll( 'input:not([name="enabled"]), select' ).forEach( function ( el ) {
				if ( el.name ) {
					data[ el.name ] = el.value;
				}
			} );

			// Build FormData for the AJAX request.
			var fd = new FormData();
			fd.append( 'action',      'xtremeforms_save_integration' );
			fd.append( 'nonce',       nonce );
			fd.append( 'integration', slug );
			Object.keys( data ).forEach( function ( k ) {
				fd.append( 'data[' + k + ']', data[ k ] );
			} );

			fetch( ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( json ) {
					if ( msgEl ) {
						msgEl.style.display = '';
						msgEl.className     = 'xf-integration-save-msg ' + ( json.success ? 'success' : 'error' );
						msgEl.textContent   = json.success
							? ( json.data && json.data.message ? json.data.message : t( 'saved', 'Saved.' ) )
							: ( json.data ? json.data : t( 'saveFailed', 'Save failed.' ) );
						setTimeout( function () { msgEl.style.display = 'none'; }, 3500 );
					}
					// Update status badge if enabled was toggled.
					var card   = form.closest( '.xf-integration-card' );
					var badge  = card ? card.querySelector( '.xf-integration-status' ) : null;
					if ( badge ) {
						if ( data.enabled === '1' && json.success ) {
							badge.textContent = t( 'connected', 'Connected' );
							badge.classList.add( 'connected' );
						} else if ( json.success ) {
							badge.textContent = t( 'notConnected', 'Not Connected' );
							badge.classList.remove( 'connected' );
						}
					}
				} )
				.catch( function () {
					if ( msgEl ) {
						msgEl.style.display = '';
						msgEl.className     = 'xf-integration-save-msg error';
						msgEl.textContent   = t( 'requestFailed', 'Request failed.' );
					}
				} )
				.finally( function () {
					if ( saveBtn ) {
						saveBtn.disabled    = false;
						saveBtn.textContent = origTxt;
					}
				} );
		} );
	} );

	// ── Test connection ──────────────────────────────────────────────────────

	document.querySelectorAll( '.xf-integration-test-btn' ).forEach( function ( btn ) {
		btn.addEventListener( 'click', function () {
			var slug  = btn.getAttribute( 'data-integration' );
			var form  = btn.closest( '.xf-integration-form' );
			var msgEl = form ? form.querySelector( '.xf-integration-save-msg' ) : null;

			var origTxt     = btn.textContent;
			btn.disabled    = true;
			btn.textContent = t( 'testing', 'Testing…' );

			var fd = new FormData();
			fd.append( 'action',      'xtremeforms_test_integration' );
			fd.append( 'nonce',       nonce );
			fd.append( 'integration', slug );

			fetch( ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( json ) {
					if ( msgEl ) {
						msgEl.style.display = '';
						msgEl.className     = 'xf-integration-save-msg ' + ( json.success ? 'success' : 'error' );
						msgEl.textContent   = json.success
							? ( json.data && json.data.message ? json.data.message : t( 'connectionSuccess', 'Connection successful!' ) )
							: ( json.data ? json.data : t( 'testFailed', 'Test failed.' ) );
						setTimeout( function () { msgEl.style.display = 'none'; }, 4000 );
					}
				} )
				.catch( function () {
					if ( msgEl ) {
						msgEl.style.display = '';
						msgEl.className     = 'xf-integration-save-msg error';
						msgEl.textContent   = t( 'requestFailed', 'Request failed.' );
					}
				} )
				.finally( function () {
					btn.disabled    = false;
					btn.textContent = origTxt;
				} );
		} );
	} );

}() );
