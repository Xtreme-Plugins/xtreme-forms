/**
 * Xtreme Forms — Lead detail admin page scripts.
 *
 * Extracted from inline <script> in admin/partials/xf-admin-lead-detail.php
 * for WordPress.org Plugin Check compliance. Per-render data and translatable
 * strings are exposed by the partial via wp_localize_script.
 */

(function () {
	'use strict';

	var data = window.xfLeadDetailData || {};
	var i18n = window.xfLeadDetailI18n || {};
	var leadId = data.leadId || 0;
	var nonce = data.nonce || '';
	var ajaxUrl = data.ajaxUrl || '';

	function post( payload, callback ) {
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
		xhr.onload = function () {
			try {
				var json = JSON.parse( xhr.responseText );
				callback( null, json );
			} catch ( e ) {
				callback( new Error( 'Invalid JSON' ), null );
			}
		};
		xhr.onerror = function () { callback( new Error( 'Network error' ), null ); };
		var params = Object.keys( payload ).map( function ( k ) {
			return encodeURIComponent( k ) + '=' + encodeURIComponent( payload[ k ] );
		} ).join( '&' );
		xhr.send( params );
	}

	function esc( str ) {
		var d = document.createElement( 'div' );
		d.appendChild( document.createTextNode( String( str ) ) );
		return d.innerHTML;
	}

	function formatDate( utcStr ) {
		if ( ! utcStr ) return '';
		// WordPress stores UTC datetimes; display as-is.
		return utcStr.replace( 'T', ' ' );
	}

	function showFeedback( el, msg, isError ) {
		el.textContent = msg;
		el.style.display = 'block';
		el.className = 'xf-feedback-msg ' + ( isError ? 'xf-feedback-error' : 'xf-feedback-success' );
		setTimeout( function () { el.style.display = 'none'; }, 4000 );
	}

	// ── Status ──────────────────────────────────────────────────────────────

	var statusSelect = document.getElementById( 'xf-status-select' );
	var saveStatusBtn = document.getElementById( 'xf-save-status' );
	var statusFeedback = document.querySelector( '.xf-status-feedback' );
	var statusBadge = document.getElementById( 'xf-detail-status-badge' );

	if ( saveStatusBtn ) {
		saveStatusBtn.addEventListener( 'click', function () {
			var newStatus = statusSelect.value;
			saveStatusBtn.disabled = true;
			saveStatusBtn.textContent = i18n.saving || '';

			post( {
				action: 'xf_update_status',
				nonce: nonce,
				lead_id: leadId,
				status: newStatus
			}, function ( err, res ) {
				saveStatusBtn.disabled = false;
				saveStatusBtn.textContent = i18n.saveStatus || '';
				if ( err || ! res || ! res.success ) {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : ( i18n.errorUpdatingStatus || '' );
					showFeedback( statusFeedback, msg, true );
					return;
				}
				var d = res.data;
				showFeedback( statusFeedback, i18n.statusUpdated || '', false );

				// Update badge without page reload.
				if ( statusBadge ) {
					statusBadge.textContent = d.status_label;
					statusBadge.className = 'xf-status-badge xf-status-' + esc( d.status );
				}

				// Append to activity timeline (no page reload needed).
				appendActivity( ( i18n.statusChanged || '' ) + ': ' + esc( d.status_label ), 'status_change' );
			} );
		} );
	}

	// ── Assignment ──────────────────────────────────────────────────────────

	var assignSelect = document.getElementById( 'xf-assignee-select' );
	var saveAssignBtn = document.getElementById( 'xf-save-assignee' );
	var assignFeedback = document.querySelector( '.xf-assign-feedback' );

	if ( saveAssignBtn ) {
		saveAssignBtn.addEventListener( 'click', function () {
			saveAssignBtn.disabled = true;
			saveAssignBtn.textContent = i18n.saving || '';

			post( {
				action: 'xf_assign_lead',
				nonce: nonce,
				lead_id: leadId,
				assigned_to: assignSelect.value
			}, function ( err, res ) {
				saveAssignBtn.disabled = false;
				saveAssignBtn.textContent = i18n.saveAssignment || '';
				if ( err || ! res || ! res.success ) {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : ( i18n.errorSavingAssignment || '' );
					showFeedback( assignFeedback, msg, true );
					return;
				}
				var d = res.data;
				var feedbackMsg = i18n.assignmentSaved || '';
				if ( d.email_warning ) {
					feedbackMsg += ' ' + d.email_warning;
				}
				showFeedback( assignFeedback, feedbackMsg, !! d.email_warning );
				// Update activity without page reload.
				var assignLabel = ( i18n.assignedTo || '' ) + ': ' + esc( d.assignee_name );
				appendActivity( assignLabel, 'assignment' );
			} );
		} );
	}

	// ── Notes ────────────────────────────────────────────────────────────────

	var noteTextarea = document.getElementById( 'xf-note-textarea' );
	var submitNoteBtn = document.getElementById( 'xf-submit-note' );
	var noteError = document.querySelector( '.xf-note-error' );
	var notesList = document.getElementById( 'xf-notes-list' );
	var notesEmpty = document.getElementById( 'xf-notes-empty' );

	if ( submitNoteBtn ) {
		submitNoteBtn.addEventListener( 'click', function () {
			var content = noteTextarea.value;

			if ( ! content.trim() ) {
				noteError.textContent = i18n.noteEmpty || '';
				noteError.style.display = 'block';
				return;
			}
			noteError.style.display = 'none';

			submitNoteBtn.disabled = true;
			submitNoteBtn.textContent = i18n.saving || '';

			post( {
				action: 'xf_add_note',
				nonce: nonce,
				lead_id: leadId,
				note_content: content
			}, function ( err, res ) {
				submitNoteBtn.disabled = false;
				submitNoteBtn.textContent = i18n.addNote || '';

				if ( err || ! res || ! res.success ) {
					var msg = ( res && res.data && res.data.message ) ? res.data.message : ( i18n.errorAddingNote || '' );
					noteError.textContent = msg;
					noteError.style.display = 'block';
					return;
				}

				var note = res.data.note;
				if ( notesEmpty ) {
					notesEmpty.style.display = 'none';
				}

				var dateStr = note.created_at ? note.created_at : '';
				var noteEl = document.createElement( 'div' );
				noteEl.className = 'xf-note-item';
				noteEl.innerHTML =
					'<div class="xf-note-meta">' +
					'<strong>' + esc( note.author_name ) + '</strong>' +
					'<time class="xf-note-time">' + esc( dateStr ) + '</time>' +
					'</div>' +
					'<div class="xf-note-content">' + esc( note.content ) + '</div>';
				notesList.appendChild( noteEl );

				noteTextarea.value = '';
				appendActivity( i18n.noteAdded || '', 'note_added' );
			} );
		} );
	}

	// ── Tags ─────────────────────────────────────────────────────────────────

	var tagInput = document.getElementById( 'xf-tag-input' );
	var tagSuggestions = document.getElementById( 'xf-tag-suggestions' );
	var tagsList = document.getElementById( 'xf-tags-list' );
	var tagFeedback = document.querySelector( '.xf-tag-feedback' );
	var _suggestTimer = null;

	function addTagPill( tag ) {
		// Prevent duplicate pills.
		var existing = tagsList.querySelector( '[data-tag-id="' + tag.id + '"]' );
		if ( existing ) return;

		var pill = document.createElement( 'span' );
		pill.className = 'xf-tag-pill';
		pill.setAttribute( 'data-tag-id', tag.id );
		pill.innerHTML = esc( tag.name ) +
			'<button type="button" class="xf-tag-remove" data-tag-id="' + esc( tag.id ) + '" aria-label="' + esc( i18n.removeTag || '' ) + ': ' + esc( tag.name ) + '">&times;</button>';
		tagsList.appendChild( pill );
		bindRemoveTag( pill.querySelector( '.xf-tag-remove' ) );
	}

	function bindRemoveTag( btn ) {
		btn.addEventListener( 'click', function () {
			var tagId = parseInt( btn.getAttribute( 'data-tag-id' ), 10 );
			post( {
				action: 'xf_remove_tag',
				nonce: nonce,
				lead_id: leadId,
				tag_id: tagId
			}, function ( err, res ) {
				if ( err || ! res || ! res.success ) {
					showFeedback( tagFeedback, i18n.errorRemovingTag || '', true );
					return;
				}
				var pill = tagsList.querySelector( '[data-tag-id="' + tagId + '"]' );
				if ( pill ) pill.remove();
				showFeedback( tagFeedback, i18n.tagRemoved || '', false );
				appendActivity( i18n.tagRemoved || '', 'tag_removed' );
			} );
		} );
	}

	// Bind existing remove buttons.
	if ( tagsList ) {
		tagsList.querySelectorAll( '.xf-tag-remove' ).forEach( bindRemoveTag );
	}

	if ( tagInput ) {
		tagInput.addEventListener( 'input', function () {
			clearTimeout( _suggestTimer );
			var query = tagInput.value.trim();
			if ( query.length < 1 ) {
				tagSuggestions.hidden = true;
				return;
			}
			_suggestTimer = setTimeout( function () {
				post( {
					action: 'xf_search_tags',
					nonce: nonce,
					query: query
				}, function ( err, res ) {
					if ( err || ! res || ! res.success ) {
						tagSuggestions.hidden = true;
						return;
					}
					var tags = res.data.tags || [];
					tagSuggestions.innerHTML = '';
					if ( ! tags.length ) {
						var li = document.createElement( 'li' );
						li.className = 'xf-suggestion-empty';
						li.textContent = i18n.noTagsFound || '';
						tagSuggestions.appendChild( li );
					} else {
						tags.forEach( function ( tag ) {
							var li = document.createElement( 'li' );
							li.className = 'xf-suggestion-item';
							li.setAttribute( 'role', 'option' );
							li.setAttribute( 'data-tag-id', tag.id );
							li.textContent = tag.name;
							li.addEventListener( 'click', function () {
								applyTag( tag );
								tagSuggestions.hidden = true;
								tagInput.value = '';
							} );
							tagSuggestions.appendChild( li );
						} );
					}
					tagSuggestions.hidden = false;
				} );
			}, 200 );
		} );

		tagInput.addEventListener( 'keydown', function ( e ) {
			if ( e.key === 'Escape' ) {
				tagSuggestions.hidden = true;
			}
		} );

		document.addEventListener( 'click', function ( e ) {
			if ( ! tagInput.contains( e.target ) && ! tagSuggestions.contains( e.target ) ) {
				tagSuggestions.hidden = true;
			}
		} );
	}

	function applyTag( tag ) {
		post( {
			action: 'xf_apply_tag',
			nonce: nonce,
			lead_id: leadId,
			tag_id: tag.id
		}, function ( err, res ) {
			if ( err || ! res || ! res.success ) {
				showFeedback( tagFeedback, i18n.errorApplyingTag || '', true );
				return;
			}
			addTagPill( res.data.tag );
			showFeedback( tagFeedback, i18n.tagAdded || '', false );
			appendActivity( i18n.tagAdded || '', 'tag_added' );
		} );
	}

	// ── Activity append helper ────────────────────────────────────────────────

	function appendActivity( label, type ) {
		var list = document.getElementById( 'xf-activity-list' );
		if ( ! list ) return;

		var emptyMsg = list.querySelector( '.xf-na' );
		if ( emptyMsg ) emptyMsg.remove();

		var now = new Date();
		var dateStr = now.getFullYear() + '-' + pad( now.getMonth() + 1 ) + '-' + pad( now.getDate() ) +
			' ' + pad( now.getHours() ) + ':' + pad( now.getMinutes() ) + ':' + pad( now.getSeconds() );

		var item = document.createElement( 'div' );
		item.className = 'xf-activity-item xf-activity-' + esc( type );
		item.innerHTML =
			'<div class="xf-activity-dot"></div>' +
			'<div class="xf-activity-body">' +
			'<div class="xf-activity-label">' + esc( label ) + '</div>' +
			'<time class="xf-activity-time">' + esc( dateStr ) + '</time>' +
			'</div>';
		list.appendChild( item );
	}

	function pad( n ) { return n < 10 ? '0' + n : String( n ); }

})();
