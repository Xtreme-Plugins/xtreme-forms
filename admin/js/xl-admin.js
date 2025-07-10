/**
 * XtremeLeads Admin JavaScript
 * Handles: Form Builder, Leads Inbox, Inline Status Change, Bulk Actions
 *
 * @package XtremeLeads
 */
/* global xlAdminData, xlBuilderData */
(function () {
	'use strict';

	// ── Utilities ────────────────────────────────────────────────────────────

	var adminData = window.xlAdminData || {};
	var ajaxUrl   = adminData.ajaxUrl  || '';
	var nonce     = adminData.nonce    || '';
	var i18n      = adminData.i18n     || {};

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
			var selectAll = document.getElementById( 'xl-select-all' );
			if ( selectAll ) {
				selectAll.addEventListener( 'change', function () {
					var cbs = document.querySelectorAll( 'input[name="lead_ids[]"]' );
					cbs.forEach( function ( cb ) { cb.checked = selectAll.checked; } );
				} );
			}

			// Validate bulk-action form submission.
			var bulkForm = document.getElementById( 'xl-leads-form' );
			if ( bulkForm ) {
				bulkForm.addEventListener( 'submit', function ( e ) {
					var action = document.getElementById( 'xl-bulk-action-select' );
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
			var rows = document.querySelectorAll( '.xl-lead-row' );
			rows.forEach( function ( row ) {
				row.addEventListener( 'click', function ( e ) {
					// Don't navigate if clicking a checkbox, link, button, or inline status element.
					if ( e.target.closest( 'input, a, button, select, .xl-inline-status-wrap' ) ) {
						return;
					}
					var leadId = parseInt( row.dataset.leadId, 10 );
					if ( leadId ) {
						var url = new URL( window.location.href );
						url.searchParams.set( 'xl_action', 'view' );
						url.searchParams.set( 'lead_id', leadId );
						window.location.href = url.toString();
					}
				} );
			} );

			// Inline status change (click badge → show select).
			inbox._initInlineStatus();

			// Shortcode copy on click (for forms list page).
			document.querySelectorAll( '.xl-shortcode' ).forEach( function ( el ) {
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
			document.querySelectorAll( '.xl-inline-status-badge' ).forEach( function ( badge ) {
				badge.addEventListener( 'click', function ( e ) {
					e.stopPropagation();
					var wrap   = badge.closest( '.xl-inline-status-wrap' );
					var select = wrap ? wrap.querySelector( '.xl-inline-status-select' ) : null;
					if ( select ) {
						badge.style.display = 'none';
						select.style.display = 'inline-block';
						select.focus();
					}
				} );
			} );

			document.querySelectorAll( '.xl-inline-status-select' ).forEach( function ( select ) {
				var leadId = parseInt( select.dataset.leadId, 10 );

				select.addEventListener( 'change', function () {
					var newStatus = select.value;
					var wrap      = select.closest( '.xl-inline-status-wrap' );
					var badge     = wrap ? wrap.querySelector( '.xl-inline-status-badge' ) : null;

					post( {
						action:  'xl_update_status',
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
							badge.className   = 'xl-status-badge xl-status-' + d.status + ' xl-inline-status-badge';
							badge.style.display = '';
						}
						select.style.display = 'none';
					} );
				} );

				select.addEventListener( 'blur', function () {
					var wrap  = select.closest( '.xl-inline-status-wrap' );
					var badge = wrap ? wrap.querySelector( '.xl-inline-status-badge' ) : null;
					if ( badge ) {
						badge.style.display = '';
						select.style.display = 'none';
					}
				} );
			} );
		}
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
			var builderData = window.xlBuilderData;
			if ( ! builderData ) return;

			this.canvas    = document.getElementById( 'xl-fields-canvas' );
			this.jsonInput = document.getElementById( 'xl-fields-json' );
			if ( ! this.canvas || ! this.jsonInput ) return;

			this.fieldTypes = builderData.fieldTypes || {};
			this.i18n       = builderData.i18n       || {};
			this.fields     = JSON.parse( JSON.stringify( builderData.fields || [] ) );
			this._counter   = this.fields.length;

			// Render existing fields.
			this.renderAll();

			// Add-field buttons.
			document.querySelectorAll( '.xl-add-field-btn' ).forEach( function ( btn ) {
				btn.addEventListener( 'click', function () {
					builder.addField( btn.dataset.type );
				} );
			} );

			// Form submit: serialize fields.
			var form = document.getElementById( 'xl-form-builder' );
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
			var empty = this.canvas.querySelector( '.xl-canvas-empty' );
			if ( empty ) empty.remove();
		},

		renderAll: function () {
			this.canvas.innerHTML = '';
			if ( this.fields.length === 0 ) {
				this.canvas.innerHTML = '<div class="xl-canvas-empty"><span class="dashicons dashicons-plus-alt"></span><p>' + esc( this.canvas.dataset.emptyLabel || 'No fields yet.' ) + '</p></div>';
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
			el.className       = 'xl-field-item';
			el.dataset.fieldId = field.id;
			el.dataset.fieldType = field.type;
			el.draggable = true;

			var typeLabel = self.fieldTypes[ field.type ] || field.type;
			var hasOpts   = self._needsOptions( field.type );
			var isHidden  = field.type === 'hidden';

			var headerHTML = '<div class="xl-field-header">' +
				'<span class="xl-drag-handle dashicons dashicons-move" title="Drag to reorder"></span>' +
				'<span class="xl-field-type-label">' + esc( typeLabel ) + '</span>' +
				'<div class="xl-field-header-actions">' +
				'<button type="button" class="xl-field-toggle" aria-expanded="true"><span class="dashicons dashicons-arrow-up-alt2"></span></button>' +
				'<button type="button" class="xl-field-delete"><span class="dashicons dashicons-trash"></span></button>' +
				'</div></div>';

			var bodyHTML = '<div class="xl-field-body">';

			if ( isHidden ) {
				bodyHTML += '<div class="xl-hidden-note">' + esc( self.i18n.hiddenNote || 'Hidden fields are invisible to visitors.' ) + '</div>';
				bodyHTML += '<div class="xl-field-row">' +
					'<div class="xl-field-col">' +
					'<label class="xl-label">' + esc( self.i18n.labelPlaceholder || 'Label' ) + '</label>' +
					'<input type="text" class="xl-input xl-input-full xl-field-label-input" placeholder="' + esc( self.i18n.labelPlaceholder || 'Field Label' ) + '" value="' + esc( field.label ) + '">' +
					'</div>' +
					'<div class="xl-field-col">' +
					'<label class="xl-label">' + esc( self.i18n.defaultValue || 'Default Value' ) + '</label>' +
					'<input type="text" class="xl-input xl-input-full xl-field-default-input" value="' + esc( field.default_value || '' ) + '">' +
					'</div></div>';
			} else {
				bodyHTML += '<div class="xl-field-row">' +
					'<div class="xl-field-col">' +
					'<label class="xl-label">' + esc( self.i18n.labelPlaceholder || 'Label' ) + '</label>' +
					'<input type="text" class="xl-input xl-input-full xl-field-label-input" placeholder="' + esc( self.i18n.labelPlaceholder || 'Field Label' ) + '" value="' + esc( field.label ) + '">' +
					'</div>' +
					'<div class="xl-field-col xl-col-required">' +
					'<label class="xl-label xl-checkbox-inline">' +
					'<input type="checkbox" class="xl-field-required-input"' + ( field.required ? ' checked' : '' ) + '> ' + esc( self.i18n.required || 'Required' ) + '</label>' +
					'</div></div>';

				if ( ! hasOpts && field.type !== 'date' ) {
					bodyHTML += '<div class="xl-field-row">' +
						'<div class="xl-field-col">' +
						'<label class="xl-label">' + esc( self.i18n.placeholder || 'Placeholder' ) + '</label>' +
						'<input type="text" class="xl-input xl-input-full xl-field-placeholder-input" value="' + esc( field.placeholder || '' ) + '">' +
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
			el.querySelector( '.xl-field-toggle' ).addEventListener( 'click', function () {
				var body     = el.querySelector( '.xl-field-body' );
				var icon     = el.querySelector( '.xl-field-toggle .dashicons' );
				var expanded = this.getAttribute( 'aria-expanded' ) === 'true';
				this.setAttribute( 'aria-expanded', ! expanded );
				body.classList.toggle( 'xl-collapsed', expanded );
				icon.className = expanded ? 'dashicons dashicons-arrow-down-alt2' : 'dashicons dashicons-arrow-up-alt2';
			} );

			// Delete.
			el.querySelector( '.xl-field-delete' ).addEventListener( 'click', function () {
				if ( confirm( self.i18n.confirmDelete || 'Delete this field?' ) ) {
					self.fields = self.fields.filter( function ( f ) { return f.id !== field.id; } );
					el.remove();
					if ( self.canvas.children.length === 0 ) {
						self.canvas.innerHTML = '<div class="xl-canvas-empty"><span class="dashicons dashicons-plus-alt"></span><p>' + esc( self.canvas.dataset.emptyLabel || 'No fields yet.' ) + '</p></div>';
					}
					self.serialize();
				}
			} );

			// Input changes.
			el.querySelectorAll( '.xl-field-label-input' ).forEach( function ( inp ) {
				inp.addEventListener( 'input', function () {
					self._updateField( field.id, 'label', inp.value );
				} );
			} );

			var phInput = el.querySelector( '.xl-field-placeholder-input' );
			if ( phInput ) {
				phInput.addEventListener( 'input', function () {
					self._updateField( field.id, 'placeholder', phInput.value );
				} );
			}

			var reqInput = el.querySelector( '.xl-field-required-input' );
			if ( reqInput ) {
				reqInput.addEventListener( 'change', function () {
					self._updateField( field.id, 'required', reqInput.checked );
				} );
			}

			var defInput = el.querySelector( '.xl-field-default-input' );
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
			var html = '<div class="xl-options-editor">';
			html += '<span class="xl-options-editor-label">' + esc( self.i18n.optionsLabel || 'Options' ) + '</span>';
			html += '<div class="xl-options-list">';
			options.forEach( function ( opt ) {
				html += '<div class="xl-option-row">' +
					'<input type="text" class="xl-option-input" value="' + esc( opt ) + '" placeholder="' + esc( self.i18n.optionPlaceholder || 'Option text…' ) + '">' +
					'<button type="button" class="xl-btn-remove-option" aria-label="' + esc( self.i18n.removeOption || 'Remove option' ) + '">&times;</button>' +
					'</div>';
			} );
			html += '</div>';
			html += '<button type="button" class="xl-btn-add-option">+ ' + esc( self.i18n.addOption || 'Add Option' ) + '</button>';
			html += '<p class="xl-options-error" style="display:none"></p>';
			html += '</div>';
			return html;
		},

		_bindOptionsEditor: function ( el, field ) {
			var self = this;
			var list = el.querySelector( '.xl-options-list' );
			var addBtn = el.querySelector( '.xl-btn-add-option' );

			function readOptions() {
				var inputs = list.querySelectorAll( '.xl-option-input' );
				var opts = [];
				inputs.forEach( function ( inp ) { if ( inp.value.trim() ) opts.push( inp.value ); } );
				self._updateField( field.id, 'options', opts );
			}

			function addRemoveListeners() {
				list.querySelectorAll( '.xl-btn-remove-option' ).forEach( function ( btn ) {
					btn.onclick = function () {
						btn.closest( '.xl-option-row' ).remove();
						readOptions();
					};
				} );
				list.querySelectorAll( '.xl-option-input' ).forEach( function ( inp ) {
					inp.oninput = readOptions;
				} );
			}

			addRemoveListeners();

			if ( addBtn ) {
				addBtn.addEventListener( 'click', function () {
					var row = document.createElement( 'div' );
					row.className = 'xl-option-row';
					row.innerHTML = '<input type="text" class="xl-option-input" placeholder="' + esc( self.i18n.optionPlaceholder || 'Option text…' ) + '">' +
						'<button type="button" class="xl-btn-remove-option" aria-label="' + esc( self.i18n.removeOption || 'Remove option' ) + '">&times;</button>';
					list.appendChild( row );
					row.querySelector( '.xl-option-input' ).focus();
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
			var ops = window.xlBuilderData.condOperators || {};

			var html = '<div class="xl-cond-logic-section">';
			html += '<hr style="margin:12px 0;border-color:#DEE2E6;">';
			html += '<div class="xl-field-row">';
			html += '<label class="xl-label xl-checkbox-inline xl-cond-toggle-label">';
			html += '<input type="checkbox" class="xl-cond-enabled-input"' + ( enabled ? ' checked' : '' ) + '> ';
			html += esc( i18n.enableCondLogic || 'Enable conditional logic' );
			html += '</label></div>';

			// Conditional logic details (shown/hidden based on enabled toggle).
			html += '<div class="xl-cond-details"' + ( enabled ? '' : ' style="display:none;"' ) + '>';

			// Logic selector (AND/OR).
			html += '<div class="xl-field-row xl-cond-logic-row">';
			html += '<label class="xl-label">' + esc( i18n.condLogicDesc || 'Show this field when:' ) + '</label>';
			html += '<select class="xl-cond-logic-select xl-input xl-input-full">';
			html += '<option value="and"' + ( logic === 'and' ? ' selected' : '' ) + '>' + esc( i18n.condLogicAnd || 'ALL conditions are met (AND)' ) + '</option>';
			html += '<option value="or"' + ( logic === 'or' ? ' selected' : '' ) + '>' + esc( i18n.condLogicOr || 'ANY condition is met (OR)' ) + '</option>';
			html += '</select></div>';

			// Live preview text.
			var previewText = logic === 'or'
				? ( i18n.condLogicPreviewOr || 'Show this field when ANY of the following conditions are met:' )
				: ( i18n.condLogicPreviewAnd || 'Show this field when ALL of the following conditions are met:' );
			html += '<p class="xl-cond-preview xl-help-text" style="font-style:italic;">' + esc( previewText ) + '</p>';

			// Conditions list.
			html += '<div class="xl-cond-conditions-list">';
			conditions.forEach( function ( cond ) {
				html += self._buildConditionRowHTML( field.id, cond, ops, i18n );
			} );
			html += '</div>';

			html += '<button type="button" class="xl-btn-add-condition button">+ ' + esc( i18n.addCondition || 'Add Condition' ) + '</button>';
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

			var html = '<div class="xl-cond-row" style="display:flex;gap:6px;align-items:center;margin-bottom:6px;">';
			html += '<select class="xl-cond-trigger-select xl-input" style="flex:2;">' + triggerOpts + '</select>';
			html += '<select class="xl-cond-op-select xl-input" style="flex:2;">' + opOpts + '</select>';
			html += '<input type="text" class="xl-cond-value-input xl-input" style="flex:2;' + ( needsValue ? '' : 'display:none;' ) + '" placeholder="' + esc( i18n.condValue || 'Value' ) + '" value="' + esc( value ) + '">';
			html += '<button type="button" class="xl-btn-remove-condition" aria-label="' + esc( i18n.removeCondition || 'Remove condition' ) + '" style="flex:0;color:#DC3545;font-size:18px;background:none;border:none;cursor:pointer;padding:0 4px;">&times;</button>';
			html += '</div>';
			return html;
		},

		// Bind conditional logic editor events to an existing field element.
		_bindCondLogicEditor: function ( el, field ) {
			var self = this;
			var i18n = self.i18n;
			var ops  = window.xlBuilderData.condOperators || {};

			var section     = el.querySelector( '.xl-cond-logic-section' );
			if ( ! section ) return;

			var enabledCb   = section.querySelector( '.xl-cond-enabled-input' );
			var details     = section.querySelector( '.xl-cond-details' );
			var logicSelect = section.querySelector( '.xl-cond-logic-select' );
			var preview     = section.querySelector( '.xl-cond-preview' );
			var condList    = section.querySelector( '.xl-cond-conditions-list' );
			var addCondBtn  = section.querySelector( '.xl-btn-add-condition' );

			function getCondLogic() {
				var conds = [];
				condList.querySelectorAll( '.xl-cond-row' ).forEach( function ( row ) {
					var trig = row.querySelector( '.xl-cond-trigger-select' );
					var op   = row.querySelector( '.xl-cond-op-select' );
					var val  = row.querySelector( '.xl-cond-value-input' );
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
				var opSel  = row.querySelector( '.xl-cond-op-select' );
				var valInp = row.querySelector( '.xl-cond-value-input' );
				var rmBtn  = row.querySelector( '.xl-btn-remove-condition' );

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
				var trigSel = row.querySelector( '.xl-cond-trigger-select' );
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
			condList.querySelectorAll( '.xl-cond-row' ).forEach( bindRowEvents );

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
				el.classList.add( 'xl-dragging' );
				e.dataTransfer.effectAllowed = 'move';
				e.dataTransfer.setData( 'text/plain', el.dataset.fieldId );
			} );
			el.addEventListener( 'dragend', function () {
				el.classList.remove( 'xl-dragging' );
				document.querySelectorAll( '.xl-field-item' ).forEach( function ( item ) {
					item.classList.remove( 'xl-drag-over' );
				} );
			} );
			el.addEventListener( 'dragover', function ( e ) {
				e.preventDefault();
				e.dataTransfer.dropEffect = 'move';
				if ( self._dragSrc && self._dragSrc !== el ) {
					el.classList.add( 'xl-drag-over' );
				}
			} );
			el.addEventListener( 'dragleave', function () {
				el.classList.remove( 'xl-drag-over' );
			} );
			el.addEventListener( 'drop', function ( e ) {
				e.preventDefault();
				el.classList.remove( 'xl-drag-over' );
				if ( self._dragSrc && self._dragSrc !== el ) {
					var items  = Array.from( self.canvas.querySelectorAll( '.xl-field-item' ) );
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

			self.canvas.querySelectorAll( '.xl-options-error' ).forEach( function ( el ) {
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
							var errEl = fieldEl.querySelector( '.xl-options-error' );
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

	// ── Init ─────────────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', function () {
		inbox.init();
		builder.init();
	} );

	// Expose for legacy usage.
	window.XLAdmin = {};

})();
