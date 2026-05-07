/**
 * Xtreme Forms Admin JavaScript
 * Handles: Form Builder, Leads Inbox, Inline Status Change, Bulk Actions
 *
 * @package Xtreme Forms
 */
/* global xfAdminData, xfBuilderData */
(function () {
	'use strict';

	// ── Utilities ────────────────────────────────────────────────────────────

	var adminData = window.xfAdminData || {};
	var ajaxUrl   = adminData.ajaxUrl  || '';
	var nonce     = adminData.nonce    || '';
	var i18n      = adminData.i18n     || {};
	var pluginVer = adminData.version  || '';

	// ── Version chip — injected into every Xtreme Forms admin page header ────

	function injectVersionChip() {
		if ( ! pluginVer ) {
			return;
		}
		var headers = document.querySelectorAll( '.xf-wrap .xf-page-header' );
		headers.forEach( function ( header ) {
			if ( header.querySelector( '.xf-version-chip' ) ) {
				return;
			}
			var title = header.querySelector( '.xf-page-title' );
			if ( ! title ) {
				return;
			}
			var chip = document.createElement( 'span' );
			chip.className = 'xf-version-chip';
			chip.title = ( i18n.pluginVersion || 'Plugin version' ) + ' ' + pluginVer;
			chip.setAttribute( 'aria-label', chip.title );
			chip.innerHTML =
				'<span class="xf-version-chip-label">Xtreme Forms</span>' +
				'<span class="xf-version-chip-num">v' + esc( pluginVer ) + '</span>';
			title.appendChild( chip );
		} );
	}

	function esc( str ) {
		var d = document.createElement( 'div' );
		d.appendChild( document.createTextNode( String( str ) ) );
		return d.innerHTML;
	}

	function post( data, callback ) {
		var xhr = new XMLHttpRequest();
		xhr.open( 'POST', ajaxUrl, true );
		xhr.setRequestHeader( 'Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8' );
		xhr.onload = function () {
			try {
				var json = JSON.parse( xhr.responseText );
				callback( null, json );
			} catch ( e ) {
				callback( new Error( 'Invalid JSON response' ), null );
			}
		};
		xhr.onerror = function () {
			callback( new Error( 'Network error' ), null );
		};
		var params = Object.keys( data ).map( function ( k ) {
			return encodeURIComponent( k ) + '=' + encodeURIComponent( data[ k ] );
		} ).join( '&' );
		xhr.send( params );
	}

	// ── Leads Inbox ──────────────────────────────────────────────────────────

	var inbox = {
		init: function () {
			// Select all checkbox.
			var selectAll = document.getElementById( 'xf-select-all' );
			if ( selectAll ) {
				selectAll.addEventListener( 'change', function () {
					var cbs = document.querySelectorAll( 'input[name="lead_ids[]"]' );
					cbs.forEach( function ( cb ) { cb.checked = selectAll.checked; } );
				} );
			}

			// Validate bulk-action form submission.
			var bulkForm = document.getElementById( 'xf-leads-form' );
			if ( bulkForm ) {
				bulkForm.addEventListener( 'submit', function ( e ) {
					var action = document.getElementById( 'xf-bulk-action-select' );
					if ( ! action || ! action.value ) {
						e.preventDefault();
						alert( i18n.noItemsSelected || 'Please select an action.' );
						return;
					}
					var checked = bulkForm.querySelectorAll( 'input[name="lead_ids[]"]:checked' );
					if ( checked.length === 0 ) {
						e.preventDefault();
						alert( i18n.noItemsSelected || 'No items selected.' );
						return;
					}
					// Confirm delete.
					if ( action.value === 'delete' ) {
						if ( ! confirm( i18n.confirmDelete || 'Delete selected leads?' ) ) {
							e.preventDefault();
						}
					}
				} );
			}

			// Row click → navigate to lead detail page.
			var rows = document.querySelectorAll( '.xf-lead-row' );
			rows.forEach( function ( row ) {
				row.addEventListener( 'click', function ( e ) {
					// Don't navigate if clicking a checkbox, link, button, or inline status element.
					if ( e.target.closest( 'input, a, button, select, .xf-inline-status-wrap' ) ) {
						return;
					}
					var leadId = parseInt( row.dataset.leadId, 10 );
					if ( leadId ) {
						var url = new URL( window.location.href );
						url.searchParams.set( 'xf_action', 'view' );
						url.searchParams.set( 'lead_id', leadId );
						window.location.href = url.toString();
					}
				} );
			} );

			// Inline status change (click badge → show select).
			inbox._initInlineStatus();

			// Per-row Resend Notification.
			inbox._initRowResend();

			// Shortcode copy on click (for forms list page).
			document.querySelectorAll( '.xf-shortcode' ).forEach( function ( el ) {
				el.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					if ( navigator.clipboard ) {
						navigator.clipboard.writeText( el.textContent ).then( function () {
							var orig = el.title;
							el.title = 'Copied!';
							setTimeout( function () { el.title = orig; }, 1500 );
						} );
					}
				} );
			} );
		},

		_initInlineStatus: function () {
			document.querySelectorAll( '.xf-inline-status-badge' ).forEach( function ( badge ) {
				badge.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					var wrap   = badge.closest( '.xf-inline-status-wrap' );
					var select = wrap ? wrap.querySelector( '.xf-inline-status-select' ) : null;
					if ( select ) {
						badge.style.display = 'none';
						select.style.display = 'inline-block';
						select.focus();
					}
				} );
			} );

			document.querySelectorAll( '.xf-inline-status-select' ).forEach( function ( select ) {
				var leadId = parseInt( select.dataset.leadId, 10 );

				select.addEventListener( 'change', function () {
					var newStatus = select.value;
					var wrap      = select.closest( '.xf-inline-status-wrap' );
					var badge     = wrap ? wrap.querySelector( '.xf-inline-status-badge' ) : null;

					post( {
						action:  'xf_update_status',
						nonce:   nonce,
						lead_id: leadId,
						status:  newStatus
					}, function ( err, res ) {
						if ( err || ! res || ! res.success ) {
							alert( ( res && res.data && res.data.message ) || ( i18n.error || 'Error.' ) );
							// Revert.
							if ( badge ) {
								badge.style.display = '';
								select.style.display = 'none';
							}
							return;
						}
						// Update badge.
						if ( badge ) {
							var d = res.data;
							badge.textContent = d.status_label;
							badge.className   = 'xf-status-badge xf-status-' + d.status + ' xf-inline-status-badge';
							badge.style.display = '';
						}
						select.style.display = 'none';
					} );
				} );

				select.addEventListener( 'blur', function () {
					var wrap  = select.closest( '.xf-inline-status-wrap' );
					var badge = wrap ? wrap.querySelector( '.xf-inline-status-badge' ) : null;
					if ( badge ) {
						badge.style.display = '';
						select.style.display = 'none';
					}
				} );
			} );
		},

		_initRowResend: function () {
			document.querySelectorAll( '.xf-row-resend' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					var leadId = parseInt( btn.dataset.leadId, 10 );
					if ( ! leadId ) {
						return;
					}
					if ( ! confirm( ( i18n.confirmResend || 'Resend the notification email for this lead to the form’s configured recipients?' ) ) ) {
						return;
					}
					var originalHTML = btn.innerHTML;
					btn.disabled = true;
					btn.textContent = i18n.sending || 'Sending…';

					post( {
						action:    'xf_resend_lead_notification',
						nonce:     nonce,
						lead_id:   leadId,
						recipient: ''
					}, function ( err, res ) {
						btn.disabled = false;
						btn.innerHTML = originalHTML;

						if ( err || ! res || ! res.success ) {
							var msg = ( res && res.data && res.data.message ) || ( i18n.error || 'Error resending email.' );
							inbox._showToast( msg, true );
							return;
						}
						inbox._showToast( res.data.message || ( i18n.resent || 'Email resent.' ), false );

						// Brief success pulse.
						btn.classList.add( 'xf-row-resend-success' );
						setTimeout( function () {
							btn.classList.remove( 'xf-row-resend-success' );
						}, 1500 );
					} );
				} );
			} );
		},

		_showToast: function ( msg, isError ) {
			var toast = document.getElementById( 'xf-toast' );
			if ( ! toast ) {
				toast = document.createElement( 'div' );
				toast.id = 'xf-toast';
				toast.className = 'xf-toast';
				document.body.appendChild( toast );
			}
			toast.textContent = msg;
			toast.className = 'xf-toast' + ( isError ? ' xf-toast-error' : ' xf-toast-success' ) + ' xf-toast-show';
			clearTimeout( inbox._toastTimer );
			inbox._toastTimer = setTimeout( function () {
				toast.classList.remove( 'xf-toast-show' );
			}, 3500 );
		},

		_toastTimer: null
	};

	// ── Form Builder ─────────────────────────────────────────────────────────

	var builder = {
		canvas:      null,
		jsonInput:   null,
		fields:      [],
		fieldTypes:  {},
		i18n:        {},
		_dragSrc:    null,
		_counter:    0,

		init: function () {
			var builderData = window.xfBuilderData;
			if ( ! builderData ) return;

			this.canvas    = document.getElementById( 'xf-fields-canvas' );
			this.jsonInput = document.getElementById( 'xf-fields-json' );
			if ( ! this.canvas || ! this.jsonInput ) return;

			this.fieldTypes = builderData.fieldTypes || {};
			this.i18n       = builderData.i18n       || {};
			this.fields     = JSON.parse( JSON.stringify( builderData.fields || [] ) );
			this._counter   = this.fields.length;

			// Render existing fields.
			this.renderAll();

			// Add-field buttons.
			document.querySelectorAll( '.xf-add-field-btn' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					builder.addField( btn.dataset.type );
				} );
			} );

			// Form submit: serialize fields.
			var form = document.getElementById( 'xf-form-builder' );
			if ( form ) {
				form.addEventListener( 'submit', function ( e ) {
					if ( ! builder.validate() ) {
						e.preventDefault();
					} else {
						builder.serialize();
					}
				} );
			}
		},

		addField: function ( type ) {
			this._counter++;
			var field = {
				id:                'field_' + Date.now() + '_' + this._counter,
				type:              type,
				label:             '',
				placeholder:       '',
				required:          false,
				default_value:     '',
				options:           ( this._needsOptions( type ) ? [ 'Option 1' ] : [] ),
				conditional_logic: { enabled: false, logic: 'and', conditions: [] }
			};
			this.fields.push( field );
			this.removeEmpty();
			this.canvas.appendChild( this.buildFieldEl( field ) );
			this.serialize();
		},

		removeEmpty: function () {
			var empty = this.canvas.querySelector( '.xf-canvas-empty' );
			if ( empty ) empty.remove();
		},

		renderAll: function () {
			this.canvas.innerHTML = '';
			if ( this.fields.length === 0 ) {
				this.canvas.innerHTML = '<div class="xf-canvas-empty"><span class="dashicons dashicons-plus-alt"></span><p>' + esc( this.canvas.dataset.emptyLabel || 'No fields yet.' ) + '</p></div>';
				return;
			}
			var self = this;
			this.fields.forEach( function ( field ) {
				self.canvas.appendChild( self.buildFieldEl( field ) );
			} );
		},

		buildFieldEl: function ( field ) {
			var self    = this;
			var el      = document.createElement( 'div' );
			el.className       = 'xf-field-item';
			el.dataset.fieldId = field.id;
			el.dataset.fieldType = field.type;
			el.draggable = true;

			var typeLabel = self.fieldTypes[ field.type ] || field.type;
			var hasOpts   = self._needsOptions( field.type );
			var isHidden  = field.type === 'hidden';

			var headerHTML = '<div class="xf-field-header">' +
				'<span class="xf-drag-handle dashicons dashicons-move" title="Drag to reorder"></span>' +
				'<span class="xf-field-type-label">' + esc( typeLabel ) + '</span>' +
				'<div class="xf-field-header-actions">' +
				'<button type="button" class="xf-field-toggle" aria-expanded="true"><span class="dashicons dashicons-arrow-up-alt2"></span></button>' +
				'<button type="button" class="xf-field-delete"><span class="dashicons dashicons-trash"></span></button>' +
				'</div></div>';

			var bodyHTML = '<div class="xf-field-body">';

			if ( isHidden ) {
				bodyHTML += '<div class="xf-hidden-note">' + esc( self.i18n.hiddenNote || 'Hidden fields are invisible to visitors.' ) + '</div>';
				bodyHTML += '<div class="xf-field-row">' +
					'<div class="xf-field-col">' +
					'<label class="xf-label">' + esc( self.i18n.labelPlaceholder || 'Label' ) + '</label>' +
					'<input type="text" class="xf-input xf-input-full xf-field-label-input" placeholder="' + esc( self.i18n.labelPlaceholder || 'Field Label' ) + '" value="' + esc( field.label ) + '">' +
					'</div>' +
					'<div class="xf-field-col">' +
					'<label class="xf-label">' + esc( self.i18n.defaultValue || 'Default Value' ) + '</label>' +
					'<input type="text" class="xf-input xf-input-full xf-field-default-input" value="' + esc( field.default_value || '' ) + '">' +
					'</div></div>';
			} else {
				bodyHTML += '<div class="xf-field-row">' +
					'<div class="xf-field-col">' +
					'<label class="xf-label">' + esc( self.i18n.labelPlaceholder || 'Label' ) + '</label>' +
					'<input type="text" class="xf-input xf-input-full xf-field-label-input" placeholder="' + esc( self.i18n.labelPlaceholder || 'Field Label' ) + '" value="' + esc( field.label ) + '">' +
					'</div>' +
					'<div class="xf-field-col xf-col-required">' +
					'<label class="xf-label xf-checkbox-inline">' +
					'<input type="checkbox" class="xf-field-required-input"' + ( field.required ? ' checked' : '' ) + '> ' + esc( self.i18n.required || 'Required' ) + '</label>' +
					'</div></div>';

				if ( ! hasOpts && field.type !== 'date' ) {
					bodyHTML += '<div class="xf-field-row">' +
						'<div class="xf-field-col">' +
						'<label class="xf-label">' + esc( self.i18n.placeholder || 'Placeholder' ) + '</label>' +
						'<input type="text" class="xf-input xf-input-full xf-field-placeholder-input" value="' + esc( field.placeholder || '' ) + '">' +
						'</div></div>';
				}

				if ( hasOpts ) {
					bodyHTML += self._buildOptionsHTML( field.options || [] );
				}

				// Sprint 6: Conditional logic section.
				bodyHTML += self._buildCondLogicHTML( field );
			}

			bodyHTML += '</div>';
			el.innerHTML = headerHTML + bodyHTML;

			// Toggle.
			el.querySelector( '.xf-field-toggle' ).addEventListener( 'click', function () {
				var body     = el.querySelector( '.xf-field-body' );
				var icon     = el.querySelector( '.xf-field-toggle .dashicons' );
				var expanded = this.getAttribute( 'aria-expanded' ) === 'true';
				this.setAttribute( 'aria-expanded', ! expanded );
				body.classList.toggle( 'xf-collapsed', expanded );
				icon.className = expanded ? 'dashicons dashicons-arrow-down-alt2' : 'dashicons dashicons-arrow-up-alt2';
			} );

			// Delete.
			el.querySelector( '.xf-field-delete' ).addEventListener( 'click', function () {
				if ( confirm( self.i18n.confirmDelete || 'Delete this field?' ) ) {
					self.fields = self.fields.filter( function ( f ) { return f.id !== field.id; } );
					el.remove();
					if ( self.canvas.children.length === 0 ) {
						self.canvas.innerHTML = '<div class="xf-canvas-empty"><span class="dashicons dashicons-plus-alt"></span><p>' + esc( self.canvas.dataset.emptyLabel || 'No fields yet.' ) + '</p></div>';
					}
					self.serialize();
				}
			} );

			// Input changes.
			el.querySelectorAll( '.xf-field-label-input' ).forEach( function ( inp ) {
				inp.addEventListener( 'input', function () {
					self._updateField( field.id, 'label', inp.value );
				} );
			} );

			var phInput = el.querySelector( '.xf-field-placeholder-input' );
			if ( phInput ) {
				phInput.addEventListener( 'input', function () {
					self._updateField( field.id, 'placeholder', phInput.value );
				} );
			}

			var reqInput = el.querySelector( '.xf-field-required-input' );
			if ( reqInput ) {
				reqInput.addEventListener( 'change', function () {
					self._updateField( field.id, 'required', reqInput.checked );
				} );
			}

			var defInput = el.querySelector( '.xf-field-default-input' );
			if ( defInput ) {
				defInput.addEventListener( 'input', function () {
					self._updateField( field.id, 'default_value', defInput.value );
				} );
			}

			// Options editor.
			if ( hasOpts ) {
				self._bindOptionsEditor( el, field );
			}

			// Sprint 6: Conditional logic editor (non-hidden fields only).
			if ( ! isHidden ) {
				self._bindCondLogicEditor( el, field );
			}

			// Drag-and-drop.
			self._bindDragEvents( el );

			return el;
		},

		_buildOptionsHTML: function ( options ) {
			var self = this;
			var html = '<div class="xf-options-editor">';
			html += '<span class="xf-options-editor-label">' + esc( self.i18n.optionsLabel || 'Options' ) + '</span>';
			html += '<div class="xf-options-list">';
			options.forEach( function ( opt ) {
				html += '<div class="xf-option-row">' +
					'<input type="text" class="xf-option-input" value="' + esc( opt ) + '" placeholder="' + esc( self.i18n.optionPlaceholder || 'Option text…' ) + '">' +
					'<button type="button" class="xf-btn-remove-option" aria-label="' + esc( self.i18n.removeOption || 'Remove option' ) + '">&times;</button>' +
					'</div>';
			} );
			html += '</div>';
			html += '<button type="button" class="xf-btn-add-option">+ ' + esc( self.i18n.addOption || 'Add Option' ) + '</button>';
			html += '<p class="xf-options-error" style="display:none"></p>';
			html += '</div>';
			return html;
		},

		_bindOptionsEditor: function ( el, field ) {
			var self = this;
			var list = el.querySelector( '.xf-options-list' );
			var addBtn = el.querySelector( '.xf-btn-add-option' );

			function readOptions() {
				var inputs = list.querySelectorAll( '.xf-option-input' );
				var opts = [];
				inputs.forEach( function ( inp ) { if ( inp.value.trim() ) opts.push( inp.value ); } );
				self._updateField( field.id, 'options', opts );
			}

			function addRemoveListeners() {
				list.querySelectorAll( '.xf-btn-remove-option' ).forEach( function ( btn ) {
					btn.onclick = function () {
						btn.closest( '.xf-option-row' ).remove();
						readOptions();
					};
				} );
				list.querySelectorAll( '.xf-option-input' ).forEach( function ( inp ) {
					inp.oninput = readOptions;
				} );
			}

			addRemoveListeners();

			if ( addBtn ) {
				addBtn.addEventListener( 'click', function () {
					var row = document.createElement( 'div' );
					row.className = 'xf-option-row';
					row.innerHTML = '<input type="text" class="xf-option-input" placeholder="' + esc( self.i18n.optionPlaceholder || 'Option text…' ) + '">' +
						'<button type="button" class="xf-btn-remove-option" aria-label="' + esc( self.i18n.removeOption || 'Remove option' ) + '">&times;</button>';
					list.appendChild( row );
					row.querySelector( '.xf-option-input' ).focus();
					addRemoveListeners();
				} );
			}
		},

		// Sprint 6: Build the conditional logic section HTML for a field.
		_buildCondLogicHTML: function ( field ) {
			var self = this;
			var cl = field.conditional_logic || {};
			var enabled = !!cl.enabled;
			var logic = cl.logic || 'and';
			var conditions = Array.isArray( cl.conditions ) ? cl.conditions : [];
			var i18n = self.i18n;
			var ops = window.xfBuilderData.condOperators || {};

			var html = '<div class="xf-cond-logic-section">';
			html += '<hr style="margin:12px 0;border-color:#DEE2E6;">';
			html += '<div class="xf-field-row">';
			html += '<label class="xf-label xf-checkbox-inline xf-cond-toggle-label">';
			html += '<input type="checkbox" class="xf-cond-enabled-input"' + ( enabled ? ' checked' : '' ) + '> ';
			html += esc( i18n.enableCondLogic || 'Enable conditional logic' );
			html += '</label></div>';

			// Conditional logic details (shown/hidden based on enabled toggle).
			html += '<div class="xf-cond-details"' + ( enabled ? '' : ' style="display:none;"' ) + '>';

			// Logic selector (AND/OR).
			html += '<div class="xf-field-row xf-cond-logic-row">';
			html += '<label class="xf-label">' + esc( i18n.condLogicDesc || 'Show this field when:' ) + '</label>';
			html += '<select class="xf-cond-logic-select xf-input xf-input-full">';
			html += '<option value="and"' + ( logic === 'and' ? ' selected' : '' ) + '>' + esc( i18n.condLogicAnd || 'ALL conditions are met (AND)' ) + '</option>';
			html += '<option value="or"' + ( logic === 'or' ? ' selected' : '' ) + '>' + esc( i18n.condLogicOr || 'ANY condition is met (OR)' ) + '</option>';
			html += '</select></div>';

			// Live preview text.
			var previewText = logic === 'or'
				? ( i18n.condLogicPreviewOr || 'Show this field when ANY of the following conditions are met:' )
				: ( i18n.condLogicPreviewAnd || 'Show this field when ALL of the following conditions are met:' );
			html += '<p class="xf-cond-preview xf-help-text" style="font-style:italic;">' + esc( previewText ) + '</p>';

			// Conditions list.
			html += '<div class="xf-cond-conditions-list">';
			conditions.forEach( function ( cond ) {
				html += self._buildConditionRowHTML( field.id, cond, ops, i18n );
			} );
			html += '</div>';

			html += '<button type="button" class="xf-btn-add-condition button">+ ' + esc( i18n.addCondition || 'Add Condition' ) + '</button>';
			html += '</div>';
			html += '</div>';
			return html;
		},

		// Build a single condition row HTML.
		_buildConditionRowHTML: function ( fieldId, cond, ops, i18n ) {
			var self = this;
			cond = cond || {};
			var trigId = cond.triggerFieldId || '';
			var operator = cond.operator || 'equals';
			var value = cond.value || '';

			// Build trigger field dropdown — all fields except self.
			var triggerOpts = '<option value="">' + esc( i18n.selectTriggerField || '— Select trigger field —' ) + '</option>';
			self.fields.forEach( function ( f ) {
				if ( f.id === fieldId || f.type === 'hidden' ) return;
				var label = f.label || f.id;
				triggerOpts += '<option value="' + esc( f.id ) + '"' + ( f.id === trigId ? ' selected' : '' ) + '>' + esc( label ) + '</option>';
			} );

			// Build operator dropdown.
			var opOpts = '';
			Object.keys( ops ).forEach( function ( opKey ) {
				opOpts += '<option value="' + esc( opKey ) + '"' + ( opKey === operator ? ' selected' : '' ) + '>' + esc( ops[ opKey ] ) + '</option>';
			} );

			var needsValue = ( operator !== 'not_empty' && operator !== 'is_empty' );

			var html = '<div class="xf-cond-row" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">';
			html += '<select class="xf-cond-trigger-select xf-input" style="flex:2;">' + triggerOpts + '</select>';
			html += '<select class="xf-cond-op-select xf-input" style="flex:2;">' + opOpts + '</select>';
			html += '<input type="text" class="xf-cond-value-input xf-input" style="flex:2;' + ( needsValue ? '' : 'display:none;' ) + '" placeholder="' + esc( i18n.condValue || 'Value' ) + '" value="' + esc( value ) + '">';
			html += '<button type="button" class="xf-btn-remove-condition" aria-label="' + esc( i18n.removeCondition || 'Remove condition' ) + '" style="flex:0;color:#DC3545;font-size:18px;background:none;border:none;cursor:pointer;padding:0 4px;">&times;</button>';
			html += '</div>';
			return html;
		},

		// Bind conditional logic editor events to an existing field element.
		_bindCondLogicEditor: function ( el, field ) {
			var self = this;
			var i18n = self.i18n;
			var ops  = window.xfBuilderData.condOperators || {};

			var section     = el.querySelector( '.xf-cond-logic-section' );
			if ( ! section ) return;

			var enabledCb   = section.querySelector( '.xf-cond-enabled-input' );
			var details     = section.querySelector( '.xf-cond-details' );
			var logicSelect = section.querySelector( '.xf-cond-logic-select' );
			var preview     = section.querySelector( '.xf-cond-preview' );
			var condList    = section.querySelector( '.xf-cond-conditions-list' );
			var addCondBtn  = section.querySelector( '.xf-btn-add-condition' );

			function getCondLogic() {
				var conds = [];
				condList.querySelectorAll( '.xf-cond-row' ).forEach( function ( row ) {
					var trig = row.querySelector( '.xf-cond-trigger-select' );
					var op   = row.querySelector( '.xf-cond-op-select' );
					var val  = row.querySelector( '.xf-cond-value-input' );
					if ( trig && op ) {
						conds.push( {
							triggerFieldId: trig.value,
							operator:       op.value,
							value:          val ? val.value : '',
						} );
					}
				} );
				return {
					enabled:    enabledCb && enabledCb.checked,
					logic:      logicSelect ? logicSelect.value : 'and',
					conditions: conds,
				};
			}

			function updateFieldData() {
				var cl = getCondLogic();
				self._updateField( field.id, 'conditional_logic', cl );
			}

			function updatePreview() {
				if ( ! preview || ! logicSelect ) return;
				var previewText = logicSelect.value === 'or'
					? ( i18n.condLogicPreviewOr || 'Show this field when ANY of the following conditions are met:' )
					: ( i18n.condLogicPreviewAnd || 'Show this field when ALL of the following conditions are met:' );
				preview.textContent = previewText;
			}

			function bindRowEvents( row ) {
				var opSel  = row.querySelector( '.xf-cond-op-select' );
				var valInp = row.querySelector( '.xf-cond-value-input' );
				var rmBtn  = row.querySelector( '.xf-btn-remove-condition' );

				if ( opSel ) {
					opSel.addEventListener( 'change', function () {
						var needsVal = ( opSel.value !== 'not_empty' && opSel.value !== 'is_empty' );
						if ( valInp ) valInp.style.display = needsVal ? '' : 'none';
						updateFieldData();
					} );
				}
				if ( valInp ) {
					valInp.addEventListener( 'input', updateFieldData );
				}
				var trigSel = row.querySelector( '.xf-cond-trigger-select' );
				if ( trigSel ) {
					trigSel.addEventListener( 'change', updateFieldData );
				}
				if ( rmBtn ) {
					rmBtn.addEventListener( 'click', function () {
						row.remove();
						updateFieldData();
					} );
				}
			}

			// Bind existing condition rows.
			condList.querySelectorAll( '.xf-cond-row' ).forEach( bindRowEvents );

			// Enable/disable toggle.
			if ( enabledCb ) {
				enableddCb: enabledCb.addEventListener( 'change', function () {
					if ( details ) details.style.display = enabledCb.checked ? '' : 'none';
					updateFieldData();
				} );
			}

			// Logic select — updates preview immediately.
			if ( logicSelect ) {
				logicSelect.addEventListener( 'change', function () {
					updatePreview();
					updateFieldData();
				} );
			}

			// Add condition button.
			if ( addCondBtn ) {
				addCondBtn.addEventListener( 'click', function () {
					var rowHtml = self._buildConditionRowHTML( field.id, {}, ops, i18n );
					var tmp = document.createElement( 'div' );
					tmp.innerHTML = rowHtml;
					var row = tmp.firstElementChild;
					if ( row ) {
						condList.appendChild( row );
						bindRowEvents( row );
						updateFieldData();
					}
				} );
			}
		},

		_bindDragEvents: function ( el ) {
			var self = this;
			el.addEventListener( 'dragstart', function ( e ) {
				self._dragSrc = el;
				el.classList.add( 'xf-dragging' );
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData( 'text/plain', el.dataset.fieldId );
			} );
			el.addEventListener( 'dragend', function () {
				el.classList.remove( 'xf-dragging' );
				document.querySelectorAll( '.xf-field-item' ).forEach( function ( item ) {
					item.classList.remove( 'xf-drag-over' );
				} );
			} );
			el.addEventListener( 'dragover', function ( e ) {
				e.preventDefault();
				e.dataTransfer.dropEffect = 'move';
				if ( self._dragSrc && self._dragSrc !== el ) {
					el.classList.add( 'xf-drag-over' );
				}
			} );
			el.addEventListener( 'dragleave', function () {
				el.classList.remove( 'xf-drag-over' );
			} );
			el.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
				el.classList.remove( 'xf-drag-over' );
				if ( self._dragSrc && self._dragSrc !== el ) {
					var items  = Array.from( self.canvas.querySelectorAll( '.xf-field-item' ) );
					var srcIdx = items.indexOf( self._dragSrc );
					var dstIdx = items.indexOf( el );

					if ( srcIdx < dstIdx ) {
						el.after( self._dragSrc );
					} else {
						el.before( self._dragSrc );
					}

					var removed = self.fields.splice( srcIdx, 1 )[ 0 ];
					self.fields.splice( dstIdx, 0, removed );
					self.serialize();
				}
				self._dragSrc = null;
			} );
		},

		_needsOptions: function ( type ) {
			return [ 'dropdown', 'checkbox', 'radio' ].indexOf( type ) !== -1;
		},

		_updateField: function ( id, key, value ) {
			var field = this.fields.find( function ( f ) { return f.id === id; } );
			if ( field ) {
				field[ key ] = value;
				this.serialize();
			}
		},

		validate: function () {
			var self       = this;
			var valid      = true;
			var firstErrEl = null;

			self.canvas.querySelectorAll( '.xf-options-error' ).forEach( function ( el ) {
				el.style.display = 'none';
				el.textContent   = '';
			} );

			self.fields.forEach( function ( field ) {
				if ( self._needsOptions( field.type ) ) {
					var opts = ( field.options || [] ).filter( function ( o ) { return o.trim() !== ''; } );
					if ( opts.length === 0 ) {
						valid = false;
						var fieldEl = self.canvas.querySelector( '[data-field-id="' + field.id + '"]' );
						if ( fieldEl ) {
							var errEl = fieldEl.querySelector( '.xf-options-error' );
							if ( errEl ) {
								errEl.textContent   = self.i18n.optionsRequired || 'This field type requires at least one option.';
								errEl.style.display = 'block';
								if ( ! firstErrEl ) { firstErrEl = errEl; }
							}
						}
					}
				}
			} );

			if ( firstErrEl ) {
				firstErrEl.scrollIntoView( { behavior: 'smooth', block: 'center' } );
			}

			return valid;
		},

		serialize: function () {
			if ( this.jsonInput ) {
				this.jsonInput.value = JSON.stringify( this.fields );
			}
		}
	};

	// ── Dashboard interactivity (count-up, clickable funnel rows) ─────────────

	var dashboard = {
		init: function () {
			dashboard._initCountUp();
			dashboard._initFunnelRows();
		},

		_initCountUp: function () {
			var els = document.querySelectorAll( '.xf-countup' );
			if ( ! els.length ) {
				return;
			}
			// Respect reduced-motion preference.
			if ( window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches ) {
				return;
			}
			els.forEach( function ( el ) {
				var target = parseInt( el.getAttribute( 'data-target' ), 10 );
				if ( isNaN( target ) || target <= 0 ) {
					return;
				}
				var duration = Math.min( 1400, 400 + target * 8 );
				var start    = performance.now();
				var format   = function ( n ) { return n.toLocaleString(); };
				el.textContent = '0';
				function frame( now ) {
					var t = Math.min( 1, ( now - start ) / duration );
					// easeOutCubic
					var eased = 1 - Math.pow( 1 - t, 3 );
					el.textContent = format( Math.round( eased * target ) );
					if ( t < 1 ) {
						requestAnimationFrame( frame );
					}
				}
				requestAnimationFrame( frame );
			} );
		},

		_initFunnelRows: function () {
			document.querySelectorAll( '.xf-funnel-row-clickable' ).forEach( function ( row ) {
				var href = row.getAttribute( 'data-href' );
				if ( ! href ) return;
				row.addEventListener( 'click', function () {
					window.location.href = href;
				} );
				row.addEventListener( 'keydown', function ( e ) {
					if ( e.key === 'Enter' || e.key === ' ' ) {
						e.preventDefault();
						window.location.href = href;
					}
				} );
			} );
		}
	};

	// ── Init ─────────────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', function () {
		injectVersionChip();
		inbox.init();
		builder.init();
		dashboard.init();
	} );

	// Expose for legacy usage.
	window.XLAdmin = {};

})();
