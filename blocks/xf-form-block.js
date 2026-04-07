/**
 * Xtreme Forms Gutenberg Form Block
 * Written without JSX — uses wp.element.createElement directly.
 * No build step required.
 *
 * Sprint 5 enhancements:
 *  - Dedicated 'xtreme-forms' block category
 *  - Width control (px / %) with validation
 *  - Alignment control (left / center / right / full)
 *  - Background colour control
 *  - Live canvas preview reflecting styling attributes
 *  - Graceful error state when selected form is unavailable
 *
 * @package Xtreme Forms
 */
/* global wp, xfBlockData */
(function (blocks, element, blockEditor, components, i18n) {
	'use strict';

	if (!blocks || !element || !blockEditor || !components) {
		return;
	}

	var el               = element.createElement;
	var Fragment         = element.Fragment;
	var __               = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var useBlockProps    = blockEditor.useBlockProps;
	var PanelBody        = components.PanelBody;
	var SelectControl    = components.SelectControl;
	var TextControl      = components.TextControl;
	var ColorPicker      = components.ColorPicker;
	var RadioControl     = components.RadioControl;
	var Notice           = components.Notice;
	var Placeholder      = components.Placeholder;

	var blockData = window.xfBlockData || {};
	var forms     = blockData.forms    || [];

	// Build options for SelectControl.
	var formOptions = [
		{ value: 0, label: __('— Select a Form —', 'xtreme-forms') }
	].concat(
		forms.map(function (form) {
			return { value: form.id, label: form.name };
		})
	);

	/**
	 * Validate width value. Returns '' if valid, or an error message string.
	 *
	 * Accepts px values (e.g. "400px") or % values (e.g. "75%").
	 * Also accepts bare numbers (treated as px).
	 * Min: 50px / 10%. Max: 2000px / 100%.
	 */
	function validateWidth(val) {
		if (!val || val === '') {
			return ''; // Empty is fine — no width restriction.
		}
		val = String(val).trim();

		if (val === '100%' || val === 'full') {
			return '';
		}

		var pxMatch  = val.match(/^(\d+(?:\.\d+)?)px?$/i);
		var pctMatch = val.match(/^(\d+(?:\.\d+)?)%$/);

		if (pxMatch) {
			var px = parseFloat(pxMatch[1]);
			if (px < 50)   { return __('Minimum width is 50px.', 'xtreme-forms'); }
			if (px > 2000) { return __('Maximum width is 2000px.', 'xtreme-forms'); }
			return '';
		}

		if (pctMatch) {
			var pct = parseFloat(pctMatch[1]);
			if (pct < 10)  { return __('Minimum width is 10%.', 'xtreme-forms'); }
			if (pct > 100) { return __('Maximum width is 100%.', 'xtreme-forms'); }
			return '';
		}

		// Non-numeric / unrecognised format.
		return __('Enter a valid width (e.g. 80%, 400px).', 'xtreme-forms');
	}

	blocks.registerBlockType('xtreme-forms/form', {
		// apiVersion 3 aligns with block.json and enables full FSE (Site Editor)
		// compatibility including template parts and page-template rendering.
		apiVersion:  3,
		title:       __('Xtreme Forms Form', 'xtreme-forms'),
		description: __('Embed a lead capture form on any page, post, or FSE template.', 'xtreme-forms'),
		icon:        'email-alt',
		category:    'xtreme-forms',
		keywords:    [
			__('lead', 'xtreme-forms'),
			__('form', 'xtreme-forms'),
			__('contact', 'xtreme-forms'),
		],
		supports: {
			html:  false,
			align: ['wide', 'full'],
			// Enable layout support for FSE themes.
			'__experimentalLayout': true,
		},
		attributes: {
			formId: {
				type:    'number',
				default: 0,
			},
			width: {
				type:    'string',
				default: '100%',
			},
			alignment: {
				type:    'string',
				default: 'left',
			},
			backgroundColor: {
				type:    'string',
				default: '',
			},
		},

		edit: function (props) {
			var attributes      = props.attributes;
			var formId          = attributes.formId;
			var width           = attributes.width;
			var alignment       = attributes.alignment;
			var backgroundColor = attributes.backgroundColor;

			var widthError      = validateWidth(width);

			// Check whether the selected form is still available.
			var formExists    = formId === 0 || forms.some(function (f) { return f.id === formId; });
			var selectedForm  = forms.find(function (f) { return f.id === formId; });

			// Build wrapper style for canvas preview.
			var wrapStyle = {
				padding:     '16px',
				border:      '2px dashed #DEE2E6',
				borderRadius: '8px',
			};

			if (backgroundColor) {
				wrapStyle.backgroundColor = backgroundColor;
			}

			var maxW = width && '' !== width ? width : '100%';
			// Don't apply invalid widths to the preview.
			if (!widthError && maxW) {
				wrapStyle.maxWidth = maxW;
			}

			if (alignment === 'center') {
				wrapStyle.marginLeft  = 'auto';
				wrapStyle.marginRight = 'auto';
			} else if (alignment === 'right') {
				wrapStyle.marginLeft = 'auto';
			} else if (alignment === 'full') {
				wrapStyle.maxWidth = '100%';
			}

			var blockProps = useBlockProps({ className: 'xf-block-editor-wrap' });

			// Inspector panel controls.
			var inspector = el(
				InspectorControls,
				{ key: 'inspector' },

				// ── Form selector ─────────────────────────────────────────────
				el(
					PanelBody,
					{ title: __('Form Settings', 'xtreme-forms'), initialOpen: true },
					el(SelectControl, {
						label:    __('Select Form', 'xtreme-forms'),
						value:    formId,
						options:  formOptions,
						onChange: function (val) {
							props.setAttributes({ formId: parseInt(val, 10) });
						},
						help: formId === 0
							? __('Choose which lead form to display.', 'xtreme-forms')
							: __('Form ID: ', 'xtreme-forms') + formId,
					})
				),

				// ── Layout / Styling ──────────────────────────────────────────
				el(
					PanelBody,
					{ title: __('Layout & Styling', 'xtreme-forms'), initialOpen: false },

					el(TextControl, {
						label:   __('Width (e.g. 80%, 400px)', 'xtreme-forms'),
						value:   width,
						onChange: function (val) {
							props.setAttributes({ width: val });
						},
						help: widthError
							? el('span', { style: { color: '#DC3545' } }, widthError)
							: __('Leave blank or set to 100% for full width.', 'xtreme-forms'),
					}),

					el(RadioControl, {
						label:    __('Alignment', 'xtreme-forms'),
						selected: alignment,
						options:  [
							{ label: __('Left', 'xtreme-forms'),   value: 'left' },
							{ label: __('Center', 'xtreme-forms'), value: 'center' },
							{ label: __('Right', 'xtreme-forms'),  value: 'right' },
							{ label: __('Full', 'xtreme-forms'),   value: 'full' },
						],
						onChange: function (val) {
							props.setAttributes({ alignment: val });
						},
					}),

					el('p', {}, el('strong', {}, __('Background Colour', 'xtreme-forms'))),
					el(ColorPicker, {
						color:  backgroundColor,
						onChangeComplete: function (colour) {
							// ColorPicker can provide hex string or object with hex property.
							var hex = (typeof colour === 'string') ? colour : (colour.hex || '');
							props.setAttributes({ backgroundColor: hex });
						},
						disableAlpha: true,
					}),
					backgroundColor
						? el(
							'button',
							{
								style:   { marginTop: '8px', fontSize: '12px', cursor: 'pointer' },
								onClick: function () {
									props.setAttributes({ backgroundColor: '' });
								},
							},
							__('Clear background colour', 'xtreme-forms')
						  )
						: null
				)
			);

			// ── Canvas preview ────────────────────────────────────────────────
			var preview;

			if (formId === 0) {
				// No form selected — show placeholder.
				preview = el(
					Placeholder,
					{
						key:   'placeholder',
						icon:  'email-alt',
						label: __('Xtreme Forms Form', 'xtreme-forms'),
					},
					el('p', { key: 'msg' }, __('Select a form to preview', 'xtreme-forms'))
				);
			} else if (!formExists) {
				// Form was deleted — non-fatal error state.
				preview = el(
					'div',
					{
						key:   'unavailable',
						style: { padding: '16px', background: '#fff3cd', borderRadius: '6px', border: '1px solid #ffc107' },
					},
					el('strong', {}, __('⚠ Form Unavailable', 'xtreme-forms')),
					el('p', {}, __('The selected form (ID: ', 'xtreme-forms') + formId + __(') no longer exists. Please choose a different form.', 'xtreme-forms'))
				);
			} else {
				// Show live accurate preview rendering the actual form fields.
				var formFields = (selectedForm && selectedForm.fields) ? selectedForm.fields : [];
				var formTitle  = selectedForm ? selectedForm.name : (__('Form #', 'xtreme-forms') + formId);

				// Render each field as a preview element.
				var fieldPreviews = formFields.map(function (field, idx) {
					var labelEl = el(
						'label',
						{
							key:   'lbl-' + idx,
							style: { display: 'block', fontWeight: '600', fontSize: '13px', marginBottom: '4px', color: '#0D1B2A' },
						},
						field.label || field.id,
						field.required ? el('span', { style: { color: '#DC3545', marginLeft: '3px' } }, '*') : null
					);

					var inputEl;
					var inputStyle = {
						width:        '100%',
						height:       '36px',
						border:       '1px solid #DEE2E6',
						borderRadius: '4px',
						padding:      '0 8px',
						fontSize:     '13px',
						background:   '#f8f9fa',
						color:        '#6C757D',
						boxSizing:    'border-box',
						pointerEvents: 'none',
					};

					switch (field.type) {
						case 'textarea':
							inputEl = el('textarea', {
								key:         'inp-' + idx,
								disabled:    true,
								placeholder: field.label || '',
								rows:        3,
								style:       Object.assign({}, inputStyle, { height: 'auto', padding: '6px 8px', resize: 'none' }),
							});
							break;
						case 'dropdown':
						case 'select':
							inputEl = el('select', { key: 'inp-' + idx, disabled: true, style: inputStyle },
								el('option', {}, field.label ? '\u2014 ' + field.label + ' \u2014' : '\u2014 Select \u2014')
							);
							break;
						case 'checkbox':
							inputEl = el('div', { key: 'inp-' + idx, style: { display: 'flex', alignItems: 'center', gap: '6px' } },
								el('input', { type: 'checkbox', disabled: true, style: { pointerEvents: 'none' } }),
								el('span', { style: { fontSize: '13px', color: '#6C757D' } }, field.label || '')
							);
							break;
						case 'radio':
							var opts = (field.options && field.options.length) ? field.options : [{label: field.label || 'Option'}];
							inputEl = el('div', { key: 'inp-' + idx, style: { display: 'flex', flexDirection: 'column', gap: '4px' } },
								opts.map(function (opt, oi) {
									return el('label', { key: 'opt-' + oi, style: { display: 'flex', alignItems: 'center', gap: '6px', fontSize: '13px', color: '#6C757D' } },
										el('input', { type: 'radio', disabled: true, style: { pointerEvents: 'none' } }),
										String(opt.label || opt)
									);
								})
							);
							break;
						case 'date':
							inputEl = el('input', { key: 'inp-' + idx, type: 'date', disabled: true, style: inputStyle });
							break;
						default:
							// text, email, phone, etc.
							inputEl = el('input', {
								key:         'inp-' + idx,
								type:        field.type === 'email' ? 'email' : (field.type === 'phone' ? 'tel' : 'text'),
								disabled:    true,
								placeholder: field.label || '',
								style:       inputStyle,
							});
					}

					return el('div', {
						key:   'field-' + idx,
						style: { marginBottom: '14px' },
					}, labelEl, inputEl);
				});

				preview = el(
					'div',
					{
						key:   'preview',
						style: {
							padding:      '20px',
							background:   backgroundColor || '#fff',
							border:       '1px solid #DEE2E6',
							borderRadius: '8px',
						}
					},
					el('div', {
						style: {
							borderBottom:  '2px solid #1A73E8',
							marginBottom:  '16px',
							paddingBottom: '10px',
						}
					},
						el('strong', { style: { fontSize: '15px', color: '#0D1B2A' } }, formTitle),
						el('small', { style: { display: 'block', color: '#6C757D', marginTop: '2px' } }, __('Form ID: ', 'xtreme-forms') + formId)
					),
					formFields.length > 0
						? el.apply(null, ['div', { key: 'fields' }].concat(fieldPreviews))
						: el('p', { key: 'no-fields', style: { color: '#6C757D', fontStyle: 'italic', fontSize: '13px' } },
							__('This form has no visible fields configured yet.', 'xtreme-forms')
						),
					el('button', {
						key:      'submit-preview',
						disabled: true,
						style:    {
							marginTop:     '8px',
							padding:       '10px 20px',
							background:    '#1A73E8',
							color:         '#fff',
							border:        'none',
							borderRadius:  '6px',
							fontSize:      '14px',
							fontWeight:    '600',
							cursor:        'not-allowed',
							opacity:       '0.8',
							pointerEvents: 'none',
						},
					}, __('Submit', 'xtreme-forms'))
				);
			}

			return el(
				Fragment,
				{},
				inspector,
				el('div', blockProps,
					el('div', { style: wrapStyle }, preview)
				)
			);
		},

		// Server-side rendered — no static save needed.
		save: function () {
			return null;
		},
	});

}(
	window.wp && window.wp.blocks,
	window.wp && window.wp.element,
	window.wp && window.wp.blockEditor,
	window.wp && window.wp.components,
	window.wp && window.wp.i18n
));
