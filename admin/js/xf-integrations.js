/**
 * Xtreme Forms — Integrations admin page scripts.
 *
 * Extracted from inline <script> in admin/partials/xf-admin-integrations.php
 * for WordPress.org Plugin Check compliance. Per-render data and translatable
 * strings are exposed by the partial via wp_localize_script.
 */

( function () {
	'use strict';

	var data = window.xfIntegrationsBootstrap || {};
	var i18n = window.xfIntegrationsI18n || {};
	var ajaxUrl = data.ajaxUrl || '';
	var nonce   = data.nonce || '';

	// Backward compatibility for any legacy code reading the prior globals.
	window.xfIntegrationsData  = data.saved || {};
	window.xfIntegrationsNonce = nonce;
	window.xfAdminAjaxUrl      = ajaxUrl;

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
				? ( i18n.configure || 'Configure' )
				: ( i18n.close || 'Close' );
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
				saveBtn.textContent = i18n.saving || '';
			}

			// Collect form field values into a plain object.
			var formData    = {};
			var enabled = form.querySelector( '[name="enabled"]' );
			formData.enabled = ( enabled && enabled.checked ) ? '1' : '0';

			form.querySelectorAll( 'input:not([name="enabled"]), select' ).forEach( function ( el ) {
				if ( el.name ) {
					formData[ el.name ] = el.value;
				}
			} );

			// Build FormData for the AJAX request.
			var fd = new FormData();
			fd.append( 'action',      'xtremeforms_save_integration' );
			fd.append( 'nonce',       nonce );
			fd.append( 'integration', slug );
			Object.keys( formData ).forEach( function ( k ) {
				fd.append( 'data[' + k + ']', formData[ k ] );
			} );

			fetch( ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' } )
				.then( function ( r ) { return r.json(); } )
				.then( function ( json ) {
					if ( msgEl ) {
						msgEl.style.display = '';
						msgEl.className     = 'xf-integration-save-msg ' + ( json.success ? 'success' : 'error' );
						msgEl.textContent   = json.success
							? ( json.data && json.data.message ? json.data.message : ( i18n.saved || '' ) )
							: ( json.data ? json.data : ( i18n.saveFailed || '' ) );
						setTimeout( function () { msgEl.style.display = 'none'; }, 3500 );
					}
					// Update status badge if enabled was toggled.
					var card   = form.closest( '.xf-integration-card' );
					var badge  = card ? card.querySelector( '.xf-integration-status' ) : null;
					if ( badge ) {
						if ( formData.enabled === '1' && json.success ) {
							badge.textContent = i18n.connected || '';
							badge.classList.add( 'connected' );
						} else if ( json.success ) {
							badge.textContent = i18n.notConnected || '';
							badge.classList.remove( 'connected' );
						}
					}
				} )
				.catch( function () {
					if ( msgEl ) {
						msgEl.style.display = '';
						msgEl.className     = 'xf-integration-save-msg error';
						msgEl.textContent   = i18n.requestFailed || '';
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
			btn.textContent = i18n.testing || '';

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
							? ( json.data && json.data.message ? json.data.message : ( i18n.connectionSuccess || '' ) )
							: ( json.data ? json.data : ( i18n.testFailed || '' ) );
						setTimeout( function () { msgEl.style.display = 'none'; }, 4000 );
					}
				} )
				.catch( function () {
					if ( msgEl ) {
						msgEl.style.display = '';
						msgEl.className     = 'xf-integration-save-msg error';
						msgEl.textContent   = i18n.requestFailed || '';
					}
				} )
				.finally( function () {
					btn.disabled    = false;
					btn.textContent = origTxt;
				} );
		} );
	} );

}() );
