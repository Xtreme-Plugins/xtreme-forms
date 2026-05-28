/**
 * Xtreme Forms — Drag-and-Drop Builder
 * Pure vanilla JS, no dependencies.
 *
 * @package Xtreme Forms
 */

/* global xtremeformsBuilderData */

(function () {
  'use strict';

  // ── Field type definitions ───────────────────────────────────────────────

  var SVG = {
    header:   '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h16M4 12h10M4 18h6"/></svg>',
    textbox:  '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M7 12h10M7 12v0"/><path d="M7 9.5v5"/></svg>',
    textarea: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M7 9h10M7 13h7"/><path d="M18 16l2 2-2 2" opacity=".5"/></svg>',
    dropdown: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="7" width="18" height="10" rx="2"/><path d="M15 12l-3 3-3-3"/></svg>',
    radio:    '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4" fill="currentColor" stroke="none"/></svg>',
    checkbox: '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="3"/><path d="M7 12l4 4 6-6"/></svg>',
    date:     '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><circle cx="8" cy="15" r="1" fill="currentColor" stroke="none"/><circle cx="12" cy="15" r="1" fill="currentColor" stroke="none"/></svg>',
    file:     '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/></svg>',
    zipcode:  '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s7-7.5 7-13a7 7 0 1 0-14 0c0 5.5 7 13 7 13z"/><circle cx="12" cy="9" r="2.5"/></svg>',
    slider:   '<svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><line x1="3" y1="12" x2="21" y2="12"/><circle cx="14" cy="12" r="3.5" fill="currentColor" stroke="none"/></svg>',
  };

  var FIELD_TYPES = [
    { type: 'header',   label: 'Header',         icon: SVG.header   },
    { type: 'textbox',  label: 'Textbox',         icon: SVG.textbox  },
    { type: 'textarea', label: 'Text Area',       icon: SVG.textarea },
    { type: 'dropdown', label: 'Dropdown',        icon: SVG.dropdown },
    { type: 'radio',    label: 'Single Choice',   icon: SVG.radio    },
    { type: 'checkbox', label: 'Multiple Choice', icon: SVG.checkbox },
    { type: 'date',     label: 'Date Picker',     icon: SVG.date     },
    { type: 'file',     label: 'File Upload',     icon: SVG.file     },
    { type: 'zipcode',  label: 'Zip Code',        icon: SVG.zipcode  },
    { type: 'slider',   label: 'Slider',          icon: SVG.slider   },
  ];

  // Map from builder type to the v1 type names the PHP handler recognises.
  var TYPE_MAP_TO_LEGACY = {
    header:   'header',
    textbox:  'text',
    textarea: 'textarea',
    dropdown: 'dropdown',
    radio:    'radio',
    checkbox: 'checkbox',
    date:     'date',
    file:     'file',
    zipcode:  'zipcode',
    slider:   'slider',
  };

  // Map legacy v1 types to builder types.
  var TYPE_MAP_FROM_LEGACY = {
    text:     'textbox',
    email:    'textbox',
    phone:    'textbox',
    textarea: 'textarea',
    dropdown: 'dropdown',
    radio:    'radio',
    checkbox: 'checkbox',
    date:     'date',
    file:     'file',
    header:   'header',
    hidden:   'textbox',
    zipcode:  'zipcode',
    slider:   'slider',
  };

  // ── Tiny ID generator ────────────────────────────────────────────────────

  function makeId() {
    return 'f_' + Math.random().toString(36).substr(2, 8);
  }

  function makePageId() {
    return 'page_' + Math.random().toString(36).substr(2, 6);
  }

  // ── Default field factory ────────────────────────────────────────────────

  function makeField(type) {
    var defaults = {
      id:           makeId(),
      type:         type,
      label:        labelForType(type),
      placeholder:  '',
      subtitle:     '',
      required:     false,
      options:      defaultOptionsForType(type),
      defaultValue: type === 'slider' ? '0' : '',
      columns:      '1',
      quantity:     false,
      min:          type === 'slider' ? '0'  : '',
      max:          type === 'slider' ? '10' : '',
      step:         type === 'slider' ? '1'  : '',
      float:        false,
      width:        '100',
      rows:         type === 'textarea' ? '4' : '1',
    };
    return defaults;
  }

  function labelForType(type) {
    var map = {
      header:   'Section Header',
      textbox:  'Text Field',
      textarea: 'Message',
      dropdown: 'Select an option',
      radio:    'Choose one',
      checkbox: 'Select all that apply',
      date:     'Select a date',
      file:     'Upload a file',
      zipcode:  'Zip Code',
      slider:   'Choose a value',
    };
    return map[type] || 'Field';
  }

  function defaultOptionsForType(type) {
    if (type === 'dropdown' || type === 'radio' || type === 'checkbox') {
      return ['Option 1', 'Option 2', 'Option 3'];
    }
    return [];
  }

  // ── XFBuilder object ─────────────────────────────────────────────────────

  var XFBuilder = {

    state: {
      pages:           [{ id: 'page-1', name: 'Page 1', fields: [] }],
      currentPage:     'page-1',
      selectedFieldId: null,
      dragging:        null,  // { source: 'palette'|'canvas', type?, fieldId? }
      activeDropGap:   null,  // index of currently highlighted drop gap
      paletteQuery:    '',
    },

    // ── DOM refs ────────────────────────────────────────────────────────

    els: {},

    // ── Init ────────────────────────────────────────────────────────────

    init: function () {
      this.els.palette     = document.getElementById('xfb-palette');
      this.els.paletteSearch = document.getElementById('xfb-palette-search');
      this.els.paletteEmpty  = document.getElementById('xfb-palette-empty');
      this.els.pageTabs    = document.getElementById('xfb-page-tabs');
      this.els.canvasInner = document.getElementById('xfb-canvas-inner');
      this.els.canvas      = document.getElementById('xfb-canvas');
      this.els.emptyHint   = document.getElementById('xfb-empty-hint');
      this.els.settingsPanel  = document.getElementById('xfb-settings-panel');
      this.els.settingsBody   = document.getElementById('xfb-settings-body');
      this.els.fieldsInput    = document.getElementById('xf-fields-json');

      if (!this.els.palette || !this.els.canvasInner || !this.els.fieldsInput) {
        return; // Not on builder page.
      }

      // Parse existing fields.
      var raw = this.els.fieldsInput.value || '[]';
      this.parseExistingFields(raw);

      // Render palette.
      this.renderPalette();

      // Palette search binding.
      var selfInit = this;
      if (this.els.paletteSearch) {
        this.els.paletteSearch.addEventListener('input', function () {
          selfInit.state.paletteQuery = this.value || '';
          selfInit.renderPalette();
        });
      }

      // Render initial state.
      this.renderPageTabs();
      this.renderCanvas();

      // Canvas drag-over / drop listeners.
      var self = this;
      this.els.canvas.addEventListener('dragover', function (e) {
        self.onCanvasDragOver(e);
      });
      this.els.canvas.addEventListener('drop', function (e) {
        self.onCanvasDrop(e);
      });
      this.els.canvas.addEventListener('dragleave', function (e) {
        // Only clear if leaving the canvas entirely.
        if (!self.els.canvas.contains(e.relatedTarget)) {
          self.clearDropPlaceholders();
          self.state.dragging = null;
        }
      });

      // Sync on form submit.
      var form = document.getElementById('xf-form-builder');
      if (form) {
        form.addEventListener('submit', function () {
          self.syncToInput();
        });
      }
    },

    // ── Parse existing fields ────────────────────────────────────────────

    parseExistingFields: function (raw) {
      var data;
      try {
        data = JSON.parse(raw);
      } catch (e) {
        data = [];
      }

      if (!data) {
        data = [];
      }

      // v2 format: { v: 2, pages: [...] }
      if (data && typeof data === 'object' && !Array.isArray(data) && data.v === 2 && Array.isArray(data.pages)) {
        this.state.pages = data.pages.map(function (pg) {
          return {
            id:     pg.id || makePageId(),
            name:   pg.name || 'Page',
            fields: (pg.fields || []).map(function (f) { return XFBuilder.normaliseField(f); }),
          };
        });
        if (this.state.pages.length === 0) {
          this.state.pages = [{ id: 'page-1', name: 'Page 1', fields: [] }];
        }
        this.state.currentPage = this.state.pages[0].id;
        return;
      }

      // v1 format: flat array.
      if (Array.isArray(data)) {
        var fields = data.map(function (f) { return XFBuilder.normaliseField(f); });
        this.state.pages = [{ id: 'page-1', name: 'Page 1', fields: fields }];
        this.state.currentPage = 'page-1';
        return;
      }

      // Fallback.
      this.state.pages = [{ id: 'page-1', name: 'Page 1', fields: [] }];
      this.state.currentPage = 'page-1';
    },

    // Ensure a field object from any source has builder-normalised properties.
    normaliseField: function (f) {
      var builderType = TYPE_MAP_FROM_LEGACY[f.type] || f.type || 'textbox';
      var isSlider    = builderType === 'slider';
      return {
        id:           f.id || makeId(),
        type:         builderType,
        label:        typeof f.label === 'string' ? f.label : labelForType(builderType),
        placeholder:  f.placeholder || '',
        subtitle:     typeof f.subtitle === 'string' ? f.subtitle : '',
        required:     !!f.required,
        options:      Array.isArray(f.options) ? f.options : [],
        defaultValue: typeof f.defaultValue === 'string'
          ? f.defaultValue
          : (f.default_value != null ? String(f.default_value) : (isSlider ? '0' : '')),
        columns:      String(f.columns || '1'),
        quantity:     !!f.quantity,
        min:          f.min != null && f.min !== '' ? String(f.min) : (isSlider ? '0'  : ''),
        max:          f.max != null && f.max !== '' ? String(f.max) : (isSlider ? '10' : ''),
        step:         f.step != null && f.step !== '' ? String(f.step) : (isSlider ? '1'  : ''),
        float:        !!f.float,
        width:        String(f.width || '100'),
        rows:         String(f.rows || (f.type === 'textarea' ? '4' : '1')),
      };
    },

    // ── Get current page ─────────────────────────────────────────────────

    currentPageObj: function () {
      var id = this.state.currentPage;
      return this.state.pages.find(function (p) { return p.id === id; }) || this.state.pages[0];
    },

    getFieldById: function (fieldId) {
      var pg = this.currentPageObj();
      return pg.fields.find(function (f) { return f.id === fieldId; }) || null;
    },

    // ── Render palette ───────────────────────────────────────────────────

    renderPalette: function () {
      var self = this;
      this.els.palette.innerHTML = '';

      var q = (this.state.paletteQuery || '').trim().toLowerCase();
      var visible = FIELD_TYPES.filter(function (ft) {
        if (!q) return true;
        return ft.label.toLowerCase().indexOf(q) !== -1 ||
               ft.type.toLowerCase().indexOf(q) !== -1;
      });

      visible.forEach(function (ft) {
        var item = document.createElement('div');
        item.className = 'xfb-palette-item';
        item.draggable = true;
        item.dataset.type = ft.type;
        item.title = 'Drag to add or click to append';

        item.innerHTML =
          '<span class="xfb-pi-icon">' + ft.icon + '</span>' +
          '<span class="xfb-pi-label">' + ft.label + '</span>' +
          '<div class="xfb-pi-preview">' + self.palettePreview(ft.type) + '</div>';

        item.addEventListener('dragstart', function (e) {
          self.onPaletteDragStart(ft.type, e);
        });
        item.addEventListener('dragend', function () {
          self.clearDropPlaceholders();
          self.state.dragging = null;
        });
        item.addEventListener('click', function () {
          var pg = self.currentPageObj();
          self.addField(ft.type, pg.fields.length);
        });

        self.els.palette.appendChild(item);
      });

      if (this.els.paletteEmpty) {
        this.els.paletteEmpty.hidden = visible.length > 0;
      }
    },

    palettePreview: function (type) {
      switch (type) {
        case 'header':
          return '<div class="xfb-pi-heading">Section Title</div>';
        case 'textbox':
          return '<input type="text" placeholder="Enter text..." disabled>';
        case 'textarea':
          return '<textarea placeholder="Enter text..." disabled></textarea>';
        case 'dropdown':
          return '<select disabled><option>Choose...</option></select>';
        case 'radio':
          return '<div class="xfb-pi-radio-row"><input type="radio" disabled><span>Option A</span></div>' +
                 '<div class="xfb-pi-radio-row"><input type="radio" disabled><span>Option B</span></div>';
        case 'checkbox':
          return '<div class="xfb-pi-check-row"><input type="checkbox" disabled><span>Option A</span></div>' +
                 '<div class="xfb-pi-check-row"><input type="checkbox" disabled><span>Option B</span></div>';
        case 'date':
          return '<input type="date" disabled>';
        case 'file':
          return '<span class="xfb-pi-file-btn">Choose File</span>';
        case 'zipcode':
          return '<input type="text" placeholder="12345" disabled>';
        case 'slider':
          return '<input type="range" min="0" max="10" value="4" disabled style="width:100%;">';
        default:
          return '';
      }
    },

    // ── Render page tabs ─────────────────────────────────────────────────

    renderPageTabs: function () {
      var self = this;
      var container = this.els.pageTabs;
      container.innerHTML = '';

      this.state.pages.forEach(function (pg) {
        var tab = document.createElement('button');
        tab.type = 'button';
        tab.className = 'xfb-page-tab' + (pg.id === self.state.currentPage ? ' active' : '');
        tab.dataset.pageId = pg.id;

        var nameSpan = document.createElement('span');
        nameSpan.textContent = pg.name;
        tab.appendChild(nameSpan);

        if (self.state.pages.length > 1) {
          var del = document.createElement('span');
          del.className = 'xfb-page-tab-del';
          del.title = 'Delete page';
          del.textContent = '×';
          del.addEventListener('click', function (e) {
            e.stopPropagation();
            self.deletePage(pg.id);
          });
          tab.appendChild(del);
        }

        tab.addEventListener('click', function (e) {
          if (e.target.classList.contains('xfb-page-tab-del')) return;
          self.switchPage(pg.id);
        });

        container.appendChild(tab);
      });

      var addBtn = document.createElement('button');
      addBtn.type = 'button';
      addBtn.className = 'xfb-add-page-btn';
      addBtn.textContent = '+ Add Page';
      addBtn.addEventListener('click', function () {
        self.addPage();
      });
      container.appendChild(addBtn);
    },

    // ── Render canvas ────────────────────────────────────────────────────

    renderCanvas: function () {
      var self = this;
      var inner = this.els.canvasInner;
      inner.innerHTML = '';

      var pg = this.currentPageObj();
      var fields = pg.fields;

      // Show/hide empty hint + mark the inner as a "form container" when populated.
      if (this.els.emptyHint) {
        this.els.emptyHint.style.display = fields.length === 0 ? '' : 'none';
      }
      inner.classList.toggle('has-fields', fields.length > 0);

      // Top drop gap.
      inner.appendChild(this.makeDropGap(0));

      var submitFloated = this.getSubmitLayout().float;

      fields.forEach(function (field, idx) {
        var card = self.renderField(field);
        inner.appendChild(card);

        // If this field AND the next are both floated, the drop gap between them
        // must be invisible so they float side-by-side. The next "element" after
        // the last field is the submit card, so factor in its float state too —
        // that lets the submit button inline with the last row of floated fields.
        var nextField   = fields[idx + 1];
        var nextFloated = nextField ? !!nextField.float : submitFloated;
        var inline      = !!(field.float && nextFloated);
        inner.appendChild(self.makeDropGap(idx + 1, inline));
      });

      // Submit button preview at the bottom.
      inner.appendChild(this.renderSubmitCard());

      // Render settings panel.
      this.renderSettings(
        fields.find(function (f) { return f.id === self.state.selectedFieldId; }) || null
      );
    },

    // Create a drop gap element.
    // inline=true: hidden gap between two consecutive floated fields.
    makeDropGap: function (index, inline) {
      var gap = document.createElement('div');
      gap.className = 'xfb-drop-gap' + (inline ? ' xfb-drop-gap-inline' : '');
      gap.dataset.dropIndex = index;
      return gap;
    },

    // ── Submit button preview card ───────────────────────────────────────

    // Read submit layout hidden inputs from the DOM.
    getSubmitLayout: function () {
      return {
        float:     (document.getElementById('xf-submit-float')       || {}).value === '1',
        width:     (document.getElementById('xf-submit-width')       || {}).value || '100',
        align:     (document.getElementById('xf-submit-align')       || {}).value || 'left',
        bgColor:   (document.getElementById('xf-submit-bg-color')    || {}).value || '#1A73E8',
        textColor: (document.getElementById('xf-submit-text-color')  || {}).value || '#ffffff',
        btnSize:   (document.getElementById('xf-submit-btn-size')    || {}).value || 'md',
        fullWidth: (document.getElementById('xf-submit-full-width')  || {}).value === '1',
      };
    },

    renderSubmitCard: function () {
      var self    = this;
      var label   = (document.getElementById('submit_label') || {}).value || 'Submit';
      var layout  = this.getSubmitLayout();
      var selected = this.state.selectedFieldId === '__submit__';

      var wrap = document.createElement('div');
      wrap.className = 'xfb-submit-preview' + (selected ? ' selected' : '') + (layout.float ? ' xfb-field-floating' : '');
      if (layout.float) {
        wrap.style.width = layout.width + '%';
      }

      // Apply alignment via text-align on the wrapper (works with inline-block button).
      if (!layout.float) {
        wrap.style.textAlign = layout.align || 'left';
      }

      var btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'xfb-submit-btn-preview xfb-btn-size-' + (layout.btnSize || 'md');
      btn.textContent = label.trim() || 'Submit';
      btn.style.background = layout.bgColor;
      btn.style.color      = layout.textColor;
      if (layout.fullWidth) { btn.style.width = '100%'; btn.style.display = 'block'; }
      wrap.appendChild(btn);

      if (layout.float) {
        var badge = document.createElement('span');
        badge.className = 'xfb-width-badge';
        badge.textContent = layout.width + '%';
        wrap.appendChild(badge);
      }

      // Affordance hint is now CSS-only (appears on hover via .xfb-submit-hint
      // ::after content) so the canvas stays clean by default.

      wrap.addEventListener('click', function () {
        self.state.selectedFieldId = '__submit__';
        // Deselect field cards.
        self.els.canvasInner.querySelectorAll('.xfb-field-card.selected').forEach(function (el) {
          el.classList.remove('selected');
        });
        self.renderSubmitSettings();
      });

      return wrap;
    },

    renderSubmitSettings: function () {
      var self   = this;
      var panel  = this.els.settingsPanel;
      var body   = this.els.settingsBody;
      panel.classList.remove('hidden');

      var labelInput  = document.getElementById('submit_label');
      var floatInput  = document.getElementById('xf-submit-float');
      var widthInput  = document.getElementById('xf-submit-width');
      var alignInput  = document.getElementById('xf-submit-align');

      var layout = this.getSubmitLayout();
      var currentLabel = (labelInput ? labelInput.value.trim() : '') || 'Submit';

      var html = '<div class="xfb-sp-inner">';
      html += '<div class="xfb-sp-title">Submit Button</div>';

      // Label.
      html += '<div class="xfb-sp-field">';
      html += '<label class="xfb-sp-label" for="xfbs-submit-label">Button Text</label>';
      html += '<input class="xfb-sp-input" id="xfbs-submit-label" type="text" value="' + this.esc(currentLabel) + '" placeholder="Submit">';
      html += '</div>';

      // Colors — two rows, each: swatch (triggers native picker) + hex input side by side.
      html += '<div class="xfb-sp-field">';
      html += '<label class="xfb-sp-label">Colors</label>';

      // Background row.
      html += '<div class="xfb-color-row">';
      html += '<div class="xfb-color-swatch-wrap">';
      html += '<input type="color" id="xfbs-submit-bg" value="' + this.esc(layout.bgColor) + '" class="xfb-color-input">';
      html += '<div class="xfb-color-swatch" style="background:' + this.esc(layout.bgColor) + ';"></div>';
      html += '</div>';
      html += '<input type="text" id="xfbs-submit-bg-hex" class="xfb-hex-input" value="' + this.esc(layout.bgColor) + '" placeholder="#1A73E8" maxlength="7" spellcheck="false">';
      html += '<span class="xfb-color-row-label">Background</span>';
      html += '</div>';

      // Text color row.
      html += '<div class="xfb-color-row" style="margin-top:8px;">';
      html += '<div class="xfb-color-swatch-wrap">';
      html += '<input type="color" id="xfbs-submit-text" value="' + this.esc(layout.textColor) + '" class="xfb-color-input">';
      html += '<div class="xfb-color-swatch" style="background:' + this.esc(layout.textColor) + ';"></div>';
      html += '</div>';
      html += '<input type="text" id="xfbs-submit-text-hex" class="xfb-hex-input" value="' + this.esc(layout.textColor) + '" placeholder="#ffffff" maxlength="7" spellcheck="false">';
      html += '<span class="xfb-color-row-label">Text</span>';
      html += '</div>';

      html += '</div>';

      html += '<hr class="xfb-sp-divider">';

      // Alignment (when not floated).
      html += '<div class="xfb-sp-field" id="xfbs-align-wrap"' + (layout.float ? ' style="display:none;"' : '') + '>';
      html += '<label class="xfb-sp-label">Alignment</label>';
      html += '<div style="display:flex;gap:6px;">';
      ['left','center','right'].forEach(function (a) {
        var active = layout.align === a ? ' xfb-align-active' : '';
        html += '<button type="button" class="xfb-align-btn' + active + '" data-align="' + a + '">' + a.charAt(0).toUpperCase() + a.slice(1) + '</button>';
      });
      html += '</div>';
      html += '</div>';

      html += '<hr class="xfb-sp-divider">';
      html += '<div class="xfb-sp-section-title">Layout</div>';

      // Button Size presets — control visual size (padding/font).
      var currentBtnSize = layout.btnSize || 'md';
      html += '<div class="xfb-sp-field">';
      html += '<label class="xfb-sp-label">Button Size</label>';
      html += '<div style="display:flex;gap:4px;flex-wrap:wrap;">';
      [['sm','Small'],['md','Medium'],['lg','Large'],['xl','XL']].forEach(function (pair) {
        var active = currentBtnSize === pair[0] ? ' xfb-align-active' : '';
        html += '<button type="button" class="xfb-width-preset xfb-submit-btnsize-btn' + active + '" data-btnsize="' + pair[0] + '">' + pair[1] + '</button>';
      });
      html += '</div>';
      html += '</div>';

      // Full width toggle.
      html += '<div class="xfb-sp-toggle-row">';
      html += '<span class="xfb-sp-toggle-label">Full width</span>';
      html += '<label class="xfb-toggle">';
      html += '<input type="checkbox" id="xfbs-submit-fullwidth"' + (layout.fullWidth ? ' checked' : '') + '>';
      html += '<span class="xfb-toggle-track"></span>';
      html += '<span class="xfb-toggle-thumb"></span>';
      html += '</label>';
      html += '</div>';

      // Layout width presets — Full, 1/2, 1/3, 1/4.
      var currentSize = layout.float ? String(layout.width) : 'auto';
      html += '<div class="xfb-sp-field">';
      html += '<label class="xfb-sp-label">Width</label>';
      html += '<div style="display:flex;gap:4px;flex-wrap:wrap;">';
      [['100','Full'],['50','1/2'],['33','1/3'],['25','1/4']].forEach(function (pair) {
        var active = currentSize === pair[0] ? ' xfb-align-active' : '';
        html += '<button type="button" class="xfb-width-preset xfb-submit-size-btn' + active + '" data-size="' + pair[0] + '">' + pair[1] + '</button>';
      });
      html += '</div>';
      html += '</div>';

      // Float toggle (side-by-side with another element).
      html += '<div class="xfb-sp-toggle-row" style="margin-top:8px;">';
      html += '<span class="xfb-sp-toggle-label">Float (side-by-side)</span>';
      html += '<label class="xfb-toggle">';
      html += '<input type="checkbox" id="xfbs-submit-float"' + (layout.float ? ' checked' : '') + '>';
      html += '<span class="xfb-toggle-track"></span>';
      html += '<span class="xfb-toggle-thumb"></span>';
      html += '</label>';
      html += '</div>';

      html += '</div>';
      body.innerHTML = html;

      // Helper: refresh the submit card in canvas.
      function refreshCard() {
        var existing = self.els.canvasInner.querySelector('.xfb-submit-preview');
        if (existing) {
          existing.parentNode.replaceChild(self.renderSubmitCard(), existing);
        }
      }

      // Label.
      var inp = body.querySelector('#xfbs-submit-label');
      if (inp && labelInput) {
        inp.addEventListener('input', function () {
          labelInput.value = inp.value;
          var livePreview = body.querySelector('#xfbs-btn-live-preview');
          if (livePreview) livePreview.textContent = inp.value || 'Submit';
          refreshCard();
        });
        inp.focus();
      }

      // Color pickers + popup logic.
      var bgInput   = body.querySelector('#xfbs-submit-bg');
      var textInput = body.querySelector('#xfbs-submit-text');
      var bgHidden   = document.getElementById('xf-submit-bg-color');
      var textHidden = document.getElementById('xf-submit-text-color');

      // Helper: normalise hex value — prepend # if missing, return null if invalid.
      function normaliseHex(val) {
        val = val.trim();
        if (/^[0-9a-fA-F]{6}$/.test(val)) val = '#' + val;
        return /^#[0-9a-fA-F]{6}$/.test(val) ? val : null;
      }

      function syncColors(source) {
        var bg   = bgInput   ? bgInput.value   : layout.bgColor;
        var text = textInput ? textInput.value : layout.textColor;
        if (bgHidden)   bgHidden.value   = bg;
        if (textHidden) textHidden.value = text;
        // Keep hex text inputs in sync with color pickers (skip if they were the source).
        var bgHexEl   = body.querySelector('#xfbs-submit-bg-hex');
        var textHexEl = body.querySelector('#xfbs-submit-text-hex');
        if (bgHexEl   && source !== 'bghex')   bgHexEl.value   = bg;
        if (textHexEl && source !== 'texthex') textHexEl.value = text;
        // Update swatches.
        var bgSwatch   = body.querySelector('#xfbs-submit-bg + .xfb-color-swatch');
        var textSwatch = body.querySelector('#xfbs-submit-text + .xfb-color-swatch');
        if (bgSwatch)   bgSwatch.style.background = bg;
        if (textSwatch) textSwatch.style.background = text;
        refreshCard();
      }

      if (bgInput)   bgInput.addEventListener('input',   function () { syncColors('picker'); });
      if (textInput) textInput.addEventListener('input', function () { syncColors('picker'); });

      // Hex text inputs → sync to native picker (with auto # prepend).
      var bgHexInp   = body.querySelector('#xfbs-submit-bg-hex');
      var textHexInp = body.querySelector('#xfbs-submit-text-hex');
      if (bgHexInp) bgHexInp.addEventListener('input', function () {
        var hex = normaliseHex(bgHexInp.value);
        if (hex) {
          bgHexInp.value = hex;
          if (bgInput) bgInput.value = hex;
          syncColors('bghex');
        }
      });
      if (textHexInp) textHexInp.addEventListener('input', function () {
        var hex = normaliseHex(textHexInp.value);
        if (hex) {
          textHexInp.value = hex;
          if (textInput) textInput.value = hex;
          syncColors('texthex');
        }
      });

      // Button size preset buttons (Small/Medium/Large/XL).
      var btnSizeInput = document.getElementById('xf-submit-btn-size');
      body.querySelectorAll('.xfb-submit-btnsize-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          body.querySelectorAll('.xfb-submit-btnsize-btn').forEach(function (b) { b.classList.remove('xfb-align-active'); });
          btn.classList.add('xfb-align-active');
          if (btnSizeInput) btnSizeInput.value = btn.dataset.btnsize;
          refreshCard();
        });
      });

      // Width preset buttons (Full/1/2/1/3/1/4).
      var alignWrap = body.querySelector('#xfbs-align-wrap');
      body.querySelectorAll('.xfb-submit-size-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          body.querySelectorAll('.xfb-submit-size-btn').forEach(function (b) { b.classList.remove('xfb-align-active'); });
          btn.classList.add('xfb-align-active');
          var size = btn.dataset.size;
          var floatCbEl = body.querySelector('#xfbs-submit-float');
          // All width presets enable float so they behave like floated blocks.
          if (floatInput) floatInput.value = '1';
          if (widthInput) widthInput.value = size;
          if (floatCbEl) floatCbEl.checked = true;
          if (alignWrap) alignWrap.style.display = 'none';
          refreshCard();
        });
      });

      // Full width toggle.
      var fullWidthInput = document.getElementById('xf-submit-full-width');
      var fullWidthCb    = body.querySelector('#xfbs-submit-fullwidth');
      if (fullWidthCb) {
        fullWidthCb.addEventListener('change', function () {
          if (fullWidthInput) fullWidthInput.value = fullWidthCb.checked ? '1' : '0';
          refreshCard();
        });
      }

      // Float toggle.
      var floatCb = body.querySelector('#xfbs-submit-float');
      if (floatCb) {
        floatCb.addEventListener('change', function () {
          if (floatInput) floatInput.value = floatCb.checked ? '1' : '0';
          if (alignWrap) alignWrap.style.display = floatCb.checked ? 'none' : '';
          refreshCard();
        });
      }

      // Alignment buttons.
      body.querySelectorAll('.xfb-align-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
          body.querySelectorAll('.xfb-align-btn').forEach(function (b) { b.classList.remove('xfb-align-active'); });
          btn.classList.add('xfb-align-active');
          if (alignInput) alignInput.value = btn.dataset.align;
          refreshCard();
        });
      });
    },

    // ── Render a single field card ───────────────────────────────────────

    renderField: function (field) {
      var self = this;
      var card = document.createElement('div');
      card.className = 'xfb-field-card' + (field.id === this.state.selectedFieldId ? ' selected' : '') + (field.float ? ' xfb-field-floating' : '');
      card.dataset.fieldId = field.id;
      card.draggable = true;
      if (field.float) {
        card.style.width = field.width + '%';
      }

      // Drag handle.
      var handle = document.createElement('span');
      handle.className = 'xfb-drag-handle';
      handle.textContent = '⠿';
      handle.title = 'Drag to reorder';
      card.appendChild(handle);

      // Field preview area.
      var preview = document.createElement('div');
      preview.className = 'xfb-field-preview';
      preview.appendChild(this.renderFieldPreview(field));
      card.appendChild(preview);

      // Toolbar (only when selected).
      if (field.id === this.state.selectedFieldId) {
        var toolbar = document.createElement('div');
        toolbar.className = 'xfb-field-toolbar';

        var ICONS = {
          copy:   '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>',
          trash:  '<svg xmlns="http://www.w3.org/2000/svg" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a2 2 0 0 1 2-2h2a2 2 0 0 1 2 2v2"/></svg>',
        };

        var copyBtn = document.createElement('button');
        copyBtn.type = 'button';
        copyBtn.className = 'xfb-tb-btn xfb-tb-copy';
        copyBtn.title = 'Duplicate field';
        copyBtn.setAttribute('aria-label', 'Duplicate field');
        copyBtn.innerHTML = ICONS.copy;
        copyBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          self.duplicateField(field.id);
        });
        toolbar.appendChild(copyBtn);

        var delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'xfb-tb-btn xfb-tb-delete';
        delBtn.title = 'Delete field';
        delBtn.setAttribute('aria-label', 'Delete field');
        delBtn.innerHTML = ICONS.trash;
        delBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          self.deleteField(field.id);
        });
        toolbar.appendChild(delBtn);

        card.appendChild(toolbar);
      }

      // Click to select.
      card.addEventListener('click', function (e) {
        if (e.target.closest('.xfb-field-toolbar')) return;
        self.selectField(field.id);
      });

      // Drag events for canvas reorder.
      card.addEventListener('dragstart', function (e) {
        self.onFieldDragStart(field.id, e);
      });
      card.addEventListener('dragend', function () {
        self.clearDropPlaceholders();
        self.state.dragging = null;
        // Remove dragging-source style.
        var draggingCard = self.els.canvasInner.querySelector('.dragging-source');
        if (draggingCard) draggingCard.classList.remove('dragging-source');
      });

      return card;
    },

    // Render the actual form element preview for a field.
    renderFieldPreview: function (field) {
      var wrap = document.createElement('div');

      if (field.type === 'header') {
        var h3 = document.createElement('h3');
        h3.className = 'xfb-heading-preview';
        h3.textContent = field.label || 'Section Header';
        wrap.appendChild(h3);
        if (field.subtitle && field.subtitle.trim() !== '') {
          var sub = document.createElement('p');
          sub.className = 'xfb-subtitle-preview';
          sub.textContent = field.subtitle;
          wrap.appendChild(sub);
        }
        return wrap;
      }

      // Label.
      var labelEl = document.createElement('div');
      labelEl.className = 'xfb-field-label';
      labelEl.textContent = field.label || labelForType(field.type);
      if (field.required) {
        var star = document.createElement('span');
        star.className = 'xfb-required';
        star.textContent = '*';
        labelEl.appendChild(star);
      }
      wrap.appendChild(labelEl);

      switch (field.type) {
        case 'textbox': {
          var rows = parseInt(field.rows || '1', 10) || 1;
          if (rows > 1) {
            // Multi-line textbox → show as textarea in preview.
            var inp = document.createElement('textarea');
            inp.disabled = true;
            inp.rows = rows;
            inp.placeholder = field.placeholder || 'Enter text…';
            inp.style.resize = 'none';
          } else {
            var inp = document.createElement('input');
            inp.type = 'text';
            inp.disabled = true;
            inp.placeholder = field.placeholder || 'Enter text…';
          }
          wrap.appendChild(inp);
          break;
        }
        case 'textarea': {
          var ta = document.createElement('textarea');
          ta.disabled = true;
          ta.rows = parseInt(field.rows || '4', 10) || 4;
          ta.placeholder = field.placeholder || 'Enter text…';
          wrap.appendChild(ta);
          break;
        }
        case 'dropdown': {
          var sel = document.createElement('select');
          sel.disabled = true;
          var defOpt = document.createElement('option');
          defOpt.textContent = field.placeholder || '— Select —';
          sel.appendChild(defOpt);
          var opts = field.options && field.options.length ? field.options : ['Option 1', 'Option 2'];
          opts.forEach(function (o) {
            var opt = document.createElement('option');
            opt.textContent = o;
            if (field.defaultValue && o === field.defaultValue) {
              opt.selected = true;
            }
            sel.appendChild(opt);
          });
          wrap.appendChild(sel);
          break;
        }
        case 'radio': {
          var ul = document.createElement('ul');
          ul.className = 'xfb-options-list';
          var radioOpts = field.options && field.options.length ? field.options : ['Option 1', 'Option 2'];
          radioOpts.forEach(function (o) {
            var li = document.createElement('li');
            li.className = 'xfb-option-item';
            var rb = document.createElement('input');
            rb.type = 'radio';
            rb.disabled = true;
            var span = document.createElement('span');
            span.textContent = o;
            li.appendChild(rb);
            li.appendChild(span);
            ul.appendChild(li);
          });
          wrap.appendChild(ul);
          break;
        }
        case 'checkbox': {
          var cbUl = document.createElement('ul');
          cbUl.className = 'xfb-options-list';
          var cols = Math.max(1, Math.min(4, parseInt(field.columns || '1', 10) || 1));
          if (cols > 1) {
            cbUl.classList.add('xfb-cols-' + cols);
          }
          var qtyOn    = !!field.quantity;
          var checkOpts = field.options && field.options.length ? field.options : ['Option 1', 'Option 2'];
          checkOpts.forEach(function (o) {
            var li = document.createElement('li');
            li.className = 'xfb-option-item';
            if (qtyOn) {
              // Builder preview: every option shows the stepper so the layout is
              // clearly previewed at design time (live form still hides steppers
              // until the user checks an option).
              li.classList.add('xfb-qty-row-preview');
              li.innerHTML =
                '<span class="xfb-qty-label-preview">' +
                  o.replace(/&/g, '&amp;').replace(/</g, '&lt;') +
                '</span>' +
                '<span class="xfb-qty-stepper-preview">' +
                  '<span class="xfb-qty-btn-preview">−</span>' +
                  '<span class="xfb-qty-val-preview">1</span>' +
                  '<span class="xfb-qty-btn-preview">+</span>' +
                '</span>';
            } else {
              var cb = document.createElement('input');
              cb.type = 'checkbox';
              cb.disabled = true;
              var span = document.createElement('span');
              span.textContent = o;
              li.appendChild(cb);
              li.appendChild(span);
            }
            cbUl.appendChild(li);
          });
          wrap.appendChild(cbUl);
          break;
        }
        case 'date': {
          var dateInp = document.createElement('input');
          dateInp.type = 'date';
          dateInp.disabled = true;
          wrap.appendChild(dateInp);
          break;
        }
        case 'file': {
          var fileInp = document.createElement('input');
          fileInp.type = 'file';
          fileInp.disabled = true;
          wrap.appendChild(fileInp);
          break;
        }
        case 'zipcode': {
          var zipInp = document.createElement('input');
          zipInp.type = 'text';
          zipInp.disabled = true;
          zipInp.placeholder = field.placeholder || '12345';
          zipInp.inputMode = 'numeric';
          zipInp.maxLength = 10;
          wrap.appendChild(zipInp);
          break;
        }
        case 'slider': {
          var sMin  = parseFloat(field.min)  || 0;
          var sMax  = parseFloat(field.max);
          if (!isFinite(sMax) || sMax <= sMin) sMax = sMin + 10;
          var sStep = parseFloat(field.step) || 1;
          var sDef  = parseFloat(field.defaultValue);
          if (!isFinite(sDef)) sDef = sMin;
          if (sDef < sMin) sDef = sMin;
          if (sDef > sMax) sDef = sMax;

          var row = document.createElement('div');
          row.className = 'xfb-slider-preview';

          var minLbl = document.createElement('span');
          minLbl.className = 'xfb-slider-edge';
          minLbl.textContent = String(sMin);

          var rng = document.createElement('input');
          rng.type = 'range';
          rng.disabled = true;
          rng.min = String(sMin);
          rng.max = String(sMax);
          rng.step = String(sStep);
          rng.value = String(sDef);
          rng.style.flex = '1';

          var maxLbl = document.createElement('span');
          maxLbl.className = 'xfb-slider-edge';
          maxLbl.textContent = String(sMax);

          var valBubble = document.createElement('span');
          valBubble.className = 'xfb-slider-value';
          valBubble.textContent = String(sDef);

          row.appendChild(minLbl);
          row.appendChild(rng);
          row.appendChild(maxLbl);
          row.appendChild(valBubble);
          wrap.appendChild(row);
          break;
        }
        default: {
          var defInp = document.createElement('input');
          defInp.type = 'text';
          defInp.disabled = true;
          defInp.placeholder = field.placeholder || '';
          wrap.appendChild(defInp);
        }
      }

      return wrap;
    },

    // ── Render settings panel ────────────────────────────────────────────

    renderSettings: function (field) {
      var self = this;
      var panel = this.els.settingsPanel;
      var body  = this.els.settingsBody;

      if (!field) {
        panel.classList.add('hidden');
        body.innerHTML = '';
        return;
      }

      panel.classList.remove('hidden');

      var html = '<div class="xfb-sp-inner">';
      html += '<div class="xfb-sp-title">Field Settings</div>';

      // Field type indicator.
      html += '<div class="xfb-sp-field">';
      html += '<span class="xfb-sp-label">Type</span>';
      html += '<div style="font-size:12px;color:#6b7280;padding:4px 0;">' + this.typeLabel(field.type) + '</div>';
      html += '</div>';

      // Label (not for header — header uses label as the heading text).
      html += '<div class="xfb-sp-field">';
      html += '<label class="xfb-sp-label" for="xfbs-label">';
      html += field.type === 'header' ? 'Heading Text' : 'Label';
      html += '</label>';
      html += '<input class="xfb-sp-input" id="xfbs-label" type="text" data-prop="label" value="' + this.esc(field.label) + '">';
      html += '</div>';

      // Subtitle (header only) — small caption text below the heading.
      if (field.type === 'header') {
        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label" for="xfbs-subtitle">Subtitle <span class="xfb-sp-optional">(optional)</span></label>';
        html += '<textarea class="xfb-sp-input" id="xfbs-subtitle" data-prop="subtitle" rows="2" placeholder="A short description, shown under the heading">' + this.esc(field.subtitle || '') + '</textarea>';
        html += '<div class="xfb-sp-hint">Rendered in a smaller, lighter font under the heading.</div>';
        html += '</div>';
      }

      // Placeholder (textbox, textarea, zipcode).
      if (field.type === 'textbox' || field.type === 'textarea' || field.type === 'zipcode') {
        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label" for="xfbs-placeholder">Placeholder</label>';
        html += '<input class="xfb-sp-input" id="xfbs-placeholder" type="text" data-prop="placeholder" value="' + this.esc(field.placeholder) + '">';
        html += '</div>';
      }

      // Options (dropdown, radio, checkbox) — plain one-per-line textarea.
      if (field.type === 'dropdown' || field.type === 'radio' || field.type === 'checkbox') {
        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label" for="xfbs-options">Options <span style="font-weight:400;text-transform:none;font-size:10px;color:#9ca3af;">(one per line)</span></label>';
        html += '<textarea class="xfb-sp-textarea" id="xfbs-options" data-prop="options" rows="5">' +
                this.esc((field.options || []).join('\n')) +
                '</textarea>';
        html += '<div class="xfb-sp-hint">Enter each option on its own line.</div>';
        html += '</div>';
      }

      // Dropdown-specific extras: default selected option + placeholder.
      if (field.type === 'dropdown') {
        var dropOpts = (field.options || []).filter(function (o) { return o !== ''; });
        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label" for="xfbs-default-value">Default Selected Option</label>';
        html += '<select class="xfb-sp-input" id="xfbs-default-value" data-prop="defaultValue">';
        html += '<option value=""' + (!field.defaultValue ? ' selected' : '') + '>— None (use placeholder) —</option>';
        dropOpts.forEach(function (o) {
          var esc = self.esc(o);
          html += '<option value="' + esc + '"' + (o === field.defaultValue ? ' selected' : '') + '>' + esc + '</option>';
        });
        html += '</select>';
        html += '<div class="xfb-sp-hint">Pre-selects this option when the form is shown.</div>';
        html += '</div>';

        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label" for="xfbs-placeholder">Placeholder</label>';
        html += '<input class="xfb-sp-input" id="xfbs-placeholder" type="text" data-prop="placeholder" value="' + this.esc(field.placeholder) + '">';
        html += '<div class="xfb-sp-hint">The blank/default choice shown first (when no default is set).</div>';
        html += '</div>';
      }

      // Slider settings (value range, default, increment).
      if (field.type === 'slider') {
        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label">Value Range</label>';
        html += '<div class="xfb-slider-range">';
        html += '<div class="xfb-slider-range-col"><input class="xfb-sp-input" type="number" data-prop="min" value="' + this.esc(field.min) + '" placeholder="0"><span class="xfb-slider-range-hint">Minimum</span></div>';
        html += '<div class="xfb-slider-range-col"><input class="xfb-sp-input" type="number" data-prop="max" value="' + this.esc(field.max) + '" placeholder="10"><span class="xfb-slider-range-hint">Maximum</span></div>';
        html += '</div>';
        html += '</div>';

        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label" for="xfbs-slider-default">Default Value</label>';
        html += '<input class="xfb-sp-input" id="xfbs-slider-default" type="number" data-prop="defaultValue" value="' + this.esc(field.defaultValue) + '" placeholder="0">';
        html += '</div>';

        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label" for="xfbs-slider-step">Increment</label>';
        html += '<input class="xfb-sp-input" id="xfbs-slider-step" type="number" data-prop="step" value="' + this.esc(field.step) + '" placeholder="1" min="0" step="any">';
        html += '<div class="xfb-sp-hint">How much the slider moves per step (e.g. 0.5, 1, 5).</div>';
        html += '</div>';
      }

      // Columns layout — Multiple Choice (checkbox) only.
      if (field.type === 'checkbox') {
        var colVal = String(field.columns || '1');
        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label">Columns</label>';
        html += '<div class="xfb-col-presets" style="display:flex;gap:4px;flex-wrap:wrap;">';
        html += '<button type="button" class="xfb-width-preset xfb-col-preset' + (colVal === '1' ? ' is-active' : '') + '" data-col="1">1</button>';
        html += '<button type="button" class="xfb-width-preset xfb-col-preset' + (colVal === '2' ? ' is-active' : '') + '" data-col="2">2</button>';
        html += '<button type="button" class="xfb-width-preset xfb-col-preset' + (colVal === '3' ? ' is-active' : '') + '" data-col="3">3</button>';
        html += '<button type="button" class="xfb-width-preset xfb-col-preset' + (colVal === '4' ? ' is-active' : '') + '" data-col="4">4</button>';
        html += '</div>';
        html += '<div class="xfb-sp-hint">How many columns to lay the options out in.</div>';
        html += '</div>';
      }

      html += '<hr class="xfb-sp-divider">';

      // Required toggle.
      if (field.type !== 'header') {
        html += '<div class="xfb-sp-toggle-row">';
        html += '<span class="xfb-sp-toggle-label">Required</span>';
        html += '<label class="xfb-toggle">';
        html += '<input type="checkbox" id="xfbs-required" data-prop="required"' + (field.required ? ' checked' : '') + '>';
        html += '<span class="xfb-toggle-track"></span>';
        html += '<span class="xfb-toggle-thumb"></span>';
        html += '</label>';
        html += '</div>';
      }

      // Quantity toggle — Multiple Choice (checkbox) only.
      // When on, each checked option turns into a − / value / + stepper.
      if (field.type === 'checkbox') {
        html += '<div class="xfb-sp-toggle-row">';
        html += '<span class="xfb-sp-toggle-label">Quantity</span>';
        html += '<label class="xfb-toggle">';
        html += '<input type="checkbox" id="xfbs-quantity" data-prop="quantity"' + (field.quantity ? ' checked' : '') + '>';
        html += '<span class="xfb-toggle-track"></span>';
        html += '<span class="xfb-toggle-thumb"></span>';
        html += '</label>';
        html += '</div>';
        html += '<div class="xfb-sp-hint">Replaces each checked option with a − / + stepper. Default count is 1.</div>';
      }

      // Lines (height) — textbox and textarea only.
      if (field.type === 'textbox' || field.type === 'textarea') {
        var rowVal = parseInt(field.rows || '1', 10) || 1;
        html += '<hr class="xfb-sp-divider">';
        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label" for="xfbs-rows">Height (lines)</label>';
        html += '<div style="display:flex;gap:10px;align-items:center;">';
        html += '<input class="xfb-rows-slider" id="xfbs-rows" type="range" min="1" max="12" step="1" data-prop="rows" value="' + rowVal + '" style="flex:1;">';
        html += '<span class="xfb-rows-display" id="xfbs-rows-val">' + rowVal + ' line' + (rowVal === 1 ? '' : 's') + '</span>';
        html += '</div>';
        html += '<div style="display:flex;gap:4px;margin-top:6px;">';
        html += '<button type="button" class="xfb-width-preset xfb-rows-preset" data-val="1">1</button>';
        html += '<button type="button" class="xfb-width-preset xfb-rows-preset" data-val="2">2</button>';
        html += '<button type="button" class="xfb-width-preset xfb-rows-preset" data-val="3">3</button>';
        html += '<button type="button" class="xfb-width-preset xfb-rows-preset" data-val="4">4</button>';
        html += '<button type="button" class="xfb-width-preset xfb-rows-preset" data-val="6">6</button>';
        html += '<button type="button" class="xfb-width-preset xfb-rows-preset" data-val="8">8</button>';
        html += '</div>';
        html += '</div>';
      }

      // Layout: float toggle + width %.
      html += '<hr class="xfb-sp-divider">';
      html += '<div class="xfb-sp-section-title">Layout</div>';
      html += '<div class="xfb-sp-toggle-row">';
      html += '<span class="xfb-sp-toggle-label">Float (side-by-side)</span>';
      html += '<label class="xfb-toggle">';
      html += '<input type="checkbox" id="xfbs-float" data-prop="float"' + (field.float ? ' checked' : '') + '>';
      html += '<span class="xfb-toggle-track"></span>';
      html += '<span class="xfb-toggle-thumb"></span>';
      html += '</label>';
      html += '</div>';
      html += '<div class="xfb-sp-field" id="xfbs-width-wrap"' + (field.float ? '' : ' style="display:none;"') + '>';
      html += '<label class="xfb-sp-label">Width</label>';
      html += '<div style="display:flex;gap:4px;flex-wrap:wrap;">';
      html += '<button type="button" class="xfb-width-preset" data-val="100">Full</button>';
      html += '<button type="button" class="xfb-width-preset" data-val="50">1/2</button>';
      html += '<button type="button" class="xfb-width-preset" data-val="33">1/3</button>';
      html += '<button type="button" class="xfb-width-preset" data-val="25">1/4</button>';
      html += '</div>';
      html += '<div class="xfb-sp-hint">Use 1/2 + 1/2 to place two fields side by side.</div>';
      html += '</div>';

      html += '</div>'; // .xfb-sp-inner

      body.innerHTML = html;

      // Bind live update events.
      var inputs = body.querySelectorAll('[data-prop]');
      inputs.forEach(function (inp) {
        var prop = inp.dataset.prop;
        // Use 'input' for text/textarea/number so changes fire on every keystroke.
        // Use 'change' only for checkboxes/selects.
        var eventType = (inp.type === 'checkbox' || inp.tagName === 'SELECT') ? 'change' : 'input';
        inp.addEventListener(eventType, function () {
          var value;
          if (prop === 'required' || prop === 'float' || prop === 'quantity') {
            value = inp.checked;
          } else if (prop === 'options') {
            value = inp.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
          } else {
            value = inp.value;
          }
          self.updateFieldProp(field.id, prop, value);
        });
      });

      // Show/hide width row when float is toggled.
      var floatCb   = body.querySelector('#xfbs-float');
      var widthWrap = body.querySelector('#xfbs-width-wrap');
      if (floatCb && widthWrap) {
        floatCb.addEventListener('change', function () {
          widthWrap.style.display = floatCb.checked ? '' : 'none';
        });
      }

      // Width preset buttons — directly update the field prop (no number input).
      body.querySelectorAll('.xfb-width-preset:not(.xfb-rows-preset):not(.xfb-col-preset)').forEach(function (btn) {
        btn.addEventListener('click', function () {
          self.updateFieldProp(field.id, 'width', btn.dataset.val);
        });
      });

      // Column preset buttons (Multiple Choice only).
      body.querySelectorAll('.xfb-col-preset').forEach(function (btn) {
        btn.addEventListener('click', function () {
          body.querySelectorAll('.xfb-col-preset').forEach(function (b) { b.classList.remove('is-active'); });
          btn.classList.add('is-active');
          self.updateFieldProp(field.id, 'columns', btn.dataset.col);
        });
      });

      // When options list is edited on a dropdown, refresh the Default Selected
      // picker so it mirrors the live list (and drop any default that was removed).
      var optsInput = body.querySelector('#xfbs-options');
      var defaultSel = body.querySelector('#xfbs-default-value');
      if (field.type === 'dropdown' && optsInput && defaultSel) {
        optsInput.addEventListener('input', function () {
          var opts = optsInput.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
          var cur  = self.getFieldById(field.id);
          var currentDefault = cur ? cur.defaultValue : '';
          // Rebuild the select options.
          defaultSel.innerHTML = '';
          var none = document.createElement('option');
          none.value = '';
          none.textContent = '— None (use placeholder) —';
          defaultSel.appendChild(none);
          opts.forEach(function (o) {
            var opt = document.createElement('option');
            opt.value = o;
            opt.textContent = o;
            defaultSel.appendChild(opt);
          });
          // Preserve the current default if it still exists; otherwise clear.
          if (currentDefault && opts.indexOf(currentDefault) !== -1) {
            defaultSel.value = currentDefault;
          } else {
            defaultSel.value = '';
            if (currentDefault) {
              self.updateFieldProp(field.id, 'defaultValue', '');
            }
          }
        });
      }

      // Rows slider.
      var rowsSlider  = body.querySelector('#xfbs-rows');
      var rowsDisplay = body.querySelector('#xfbs-rows-val');
      if (rowsSlider) {
        rowsSlider.addEventListener('input', function () {
          var v = parseInt(rowsSlider.value, 10);
          if (rowsDisplay) rowsDisplay.textContent = v + ' line' + (v === 1 ? '' : 's');
        });
        // Preset row buttons.
        body.querySelectorAll('.xfb-rows-preset').forEach(function (btn) {
          btn.addEventListener('click', function () {
            rowsSlider.value = btn.dataset.val;
            rowsSlider.dispatchEvent(new Event('input', { bubbles: true }));
          });
        });
      }
    },

    typeLabel: function (type) {
      var found = FIELD_TYPES.find(function (ft) { return ft.type === type; });
      return found ? found.label : type;
    },

    esc: function (str) {
      return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    },

    // ── Palette drag ─────────────────────────────────────────────────────

    onPaletteDragStart: function (type, e) {
      this.state.dragging = { source: 'palette', type: type };
      e.dataTransfer.effectAllowed = 'copy';
      e.dataTransfer.setData('text/plain', 'palette:' + type);
    },

    // ── Canvas field drag (reorder) ───────────────────────────────────────

    onFieldDragStart: function (fieldId, e) {
      this.state.dragging = { source: 'canvas', fieldId: fieldId };
      e.dataTransfer.effectAllowed = 'move';
      e.dataTransfer.setData('text/plain', 'canvas:' + fieldId);
      // Mark card visually.
      var self = this;
      setTimeout(function () {
        var card = self.els.canvasInner.querySelector('[data-field-id="' + fieldId + '"]');
        if (card) card.classList.add('dragging-source');
      }, 0);
    },

    // ── Canvas dragover ──────────────────────────────────────────────────

    onCanvasDragOver: function (e) {
      if (!this.state.dragging) return;
      e.preventDefault();
      e.dataTransfer.dropEffect = this.state.dragging.source === 'palette' ? 'copy' : 'move';

      var idx = this.getDropIndex(e.clientY);
      if (idx !== this.state.activeDropGap) {
        this.showDropPlaceholder(idx);
        this.state.activeDropGap = idx;
      }
    },

    // ── Canvas drop ──────────────────────────────────────────────────────

    onCanvasDrop: function (e) {
      e.preventDefault();
      var dragging = this.state.dragging;
      if (!dragging) return;

      var idx = this.state.activeDropGap !== null ? this.state.activeDropGap : this.getDropIndex(e.clientY);
      this.clearDropPlaceholders();
      this.state.activeDropGap = null;

      if (dragging.source === 'palette') {
        this.addField(dragging.type, idx);
      } else if (dragging.source === 'canvas') {
        this.moveField(dragging.fieldId, idx);
      }

      this.state.dragging = null;
    },

    // ── Get drop index from cursor Y ─────────────────────────────────────

    getDropIndex: function (cursorY) {
      var gaps = this.els.canvasInner.querySelectorAll('.xfb-drop-gap');
      if (!gaps.length) return 0;

      var bestIdx = 0;
      var bestDist = Infinity;

      gaps.forEach(function (gap) {
        var rect = gap.getBoundingClientRect();
        var midY = rect.top + rect.height / 2;
        var dist = Math.abs(cursorY - midY);
        if (dist < bestDist) {
          bestDist = dist;
          bestIdx = parseInt(gap.dataset.dropIndex, 10);
        }
      });

      return bestIdx;
    },

    showDropPlaceholder: function (index) {
      var gaps = this.els.canvasInner.querySelectorAll('.xfb-drop-gap');
      gaps.forEach(function (gap) {
        if (parseInt(gap.dataset.dropIndex, 10) === index) {
          gap.classList.add('active');
        } else {
          gap.classList.remove('active');
        }
      });
    },

    clearDropPlaceholders: function () {
      if (!this.els.canvasInner) return;
      this.els.canvasInner.querySelectorAll('.xfb-drop-gap.active').forEach(function (gap) {
        gap.classList.remove('active');
      });
    },

    // ── Field operations ─────────────────────────────────────────────────

    addField: function (type, atIndex) {
      var pg = this.currentPageObj();
      var field = makeField(type);

      var safeIndex = (atIndex === undefined || atIndex === null || atIndex > pg.fields.length)
        ? pg.fields.length
        : Math.max(0, atIndex);

      pg.fields.splice(safeIndex, 0, field);
      this.state.selectedFieldId = field.id;
      this.renderPageTabs();
      this.renderCanvas();
      this.syncToInput();
    },

    moveField: function (fieldId, toIndex) {
      var pg = this.currentPageObj();
      var fromIdx = pg.fields.findIndex(function (f) { return f.id === fieldId; });
      if (fromIdx === -1) return;

      var field = pg.fields.splice(fromIdx, 1)[0];

      // Adjust target index after removal.
      var adjustedIdx = toIndex > fromIdx ? toIndex - 1 : toIndex;
      adjustedIdx = Math.max(0, Math.min(adjustedIdx, pg.fields.length));

      pg.fields.splice(adjustedIdx, 0, field);
      this.renderCanvas();
      this.syncToInput();
    },

    duplicateField: function (fieldId) {
      var pg = this.currentPageObj();
      var idx = pg.fields.findIndex(function (f) { return f.id === fieldId; });
      if (idx === -1) return;

      var original = pg.fields[idx];
      var copy = JSON.parse(JSON.stringify(original));
      copy.id = makeId();

      pg.fields.splice(idx + 1, 0, copy);
      this.state.selectedFieldId = copy.id;
      this.renderCanvas();
      this.syncToInput();
    },

    deleteField: function (fieldId) {
      var pg = this.currentPageObj();
      var idx = pg.fields.findIndex(function (f) { return f.id === fieldId; });
      if (idx === -1) return;


      pg.fields.splice(idx, 1);

      if (this.state.selectedFieldId === fieldId) {
        this.state.selectedFieldId = null;
      }
      this.renderCanvas();
      this.syncToInput();
    },

    selectField: function (fieldId) {
      if (this.state.selectedFieldId === fieldId) {
        this.state.selectedFieldId = null;
      } else {
        this.state.selectedFieldId = fieldId;
      }
      this.renderCanvas();
    },

    // Pulse-highlight a card to signal it changed.
    flashCard: function (el) {
      el.classList.remove('xfb-card-flash');
      void el.offsetWidth; // force reflow so animation restarts
      el.classList.add('xfb-card-flash');
    },

    // Full card replace with entrance flash.
    replaceCard: function (existing, field) {
      var updated = this.renderField(field);
      existing.parentNode.replaceChild(updated, existing);
      this.flashCard(updated);
      return updated;
    },

    updateFieldProp: function (fieldId, prop, value) {
      var self = this;
      var pg   = this.currentPageObj();
      var field = pg.fields.find(function (f) { return f.id === fieldId; });
      if (!field) return;

      field[prop] = value;

      var existing = this.els.canvasInner.querySelector('[data-field-id="' + fieldId + '"]');
      if (!existing) { this.syncToInput(); return; }

      // ── Targeted DOM patches (no full re-render, no lost focus) ───────────

      if (prop === 'label') {
        var labelEl = existing.querySelector('.xfb-field-label');
        if (labelEl) {
          // Preserve the required star if present.
          var star = labelEl.querySelector('.xfb-required');
          labelEl.textContent = value || labelForType(field.type);
          if (star) labelEl.appendChild(star);
          this.flashCard(existing);
        } else {
          this.replaceCard(existing, field);
        }

      } else if (prop === 'placeholder') {
        var inputEl = existing.querySelector('input:not([type=checkbox]):not([type=radio]), textarea, select');
        if (inputEl) {
          inputEl.placeholder = value;
          this.flashCard(existing);
        }

      } else if (prop === 'required') {
        var labelEl2 = existing.querySelector('.xfb-field-label');
        if (labelEl2) {
          var existingStar = labelEl2.querySelector('.xfb-required');
          if (value && !existingStar) {
            var newStar = document.createElement('span');
            newStar.className = 'xfb-required';
            newStar.textContent = ' *';
            newStar.setAttribute('aria-hidden', 'true');
            labelEl2.appendChild(newStar);
          } else if (!value && existingStar) {
            existingStar.remove();
          }
        }
        this.flashCard(existing);

      } else if (prop === 'rows') {
        var rows = Math.max(1, parseInt(value, 10) || 1);
        var previewEl = existing.querySelector('textarea, input[type=text]');
        if (previewEl) {
          if (rows > 1 && previewEl.tagName === 'INPUT') {
            // Switch input → textarea: needs full replace.
            this.replaceCard(existing, field);
          } else if (rows <= 1 && previewEl.tagName === 'TEXTAREA') {
            // Switch textarea → input: needs full replace.
            this.replaceCard(existing, field);
          } else if (previewEl.tagName === 'TEXTAREA') {
            // Update rows in-place — browser resizes textarea naturally.
            previewEl.rows = rows;
            this.flashCard(existing);
          }
        }

      } else if (prop === 'float' || prop === 'width') {
        // Structural change — full replace needed.
        this.replaceCard(existing, field);

      } else if (prop === 'options' || prop === 'defaultValue' || prop === 'columns' || prop === 'min' || prop === 'max' || prop === 'step') {
        // Options / default / columns change — re-render the preview part only.
        var previewWrap = existing.querySelector('.xfb-field-preview');
        if (previewWrap) {
          previewWrap.innerHTML = '';
          previewWrap.appendChild(this.renderFieldPreview(field));
          this.flashCard(existing);
        } else {
          this.replaceCard(existing, field);
        }

      } else {
        // Fallback: full replace.
        this.replaceCard(existing, field);
      }

      this.syncToInput();
    },

    // ── Multi-page ───────────────────────────────────────────────────────

    addPage: function () {
      var num = this.state.pages.length + 1;
      var page = {
        id:     makePageId(),
        name:   'Page ' + num,
        fields: [],
      };
      this.state.pages.push(page);
      this.state.currentPage = page.id;
      this.state.selectedFieldId = null;
      this.renderPageTabs();
      this.renderCanvas();
      this.syncToInput();
    },

    switchPage: function (pageId) {
      if (this.state.currentPage === pageId) return;
      this.state.currentPage = pageId;
      this.state.selectedFieldId = null;
      this.renderPageTabs();
      this.renderCanvas();
    },

    deletePage: function (pageId) {
      if (this.state.pages.length <= 1) {
        window.alert('Cannot delete the only page.');
        return;
      }

      var idx = this.state.pages.findIndex(function (p) { return p.id === pageId; });
      if (idx === -1) return;

      this.state.pages.splice(idx, 1);

      if (this.state.currentPage === pageId) {
        this.state.currentPage = this.state.pages[Math.max(0, idx - 1)].id;
      }
      this.state.selectedFieldId = null;
      this.renderPageTabs();
      this.renderCanvas();
      this.syncToInput();
    },

    // ── Serialize ────────────────────────────────────────────────────────

    serialize: function () {
      var pages = this.state.pages.map(function (pg) {
        return {
          id:     pg.id,
          name:   pg.name,
          fields: pg.fields.map(function (f) {
            // Convert builder types back to legacy PHP-understood types.
            var legacyType = TYPE_MAP_TO_LEGACY[f.type] || f.type;
            var out = {
              id:           f.id,
              type:         legacyType,
              label:        f.label,
              placeholder:  f.placeholder,
              subtitle:     f.subtitle || '',
              required:     f.required,
              options:      f.options || [],
              defaultValue: f.defaultValue || '',
              columns:      String(f.columns || '1'),
              quantity:     !!f.quantity,
              min:          f.min != null ? String(f.min) : '',
              max:          f.max != null ? String(f.max) : '',
              step:         f.step != null ? String(f.step) : '',
              float:        !!f.float,
              width:        String(f.width || '100'),
              rows:         String(f.rows || '1'),
            };
            return out;
          }),
        };
      });

      return { v: 2, pages: pages };
    },

    // ── Sync to hidden input ──────────────────────────────────────────────

    syncToInput: function () {
      if (this.els.fieldsInput) {
        this.els.fieldsInput.value = JSON.stringify(this.serialize());
      }
    },

  }; // XFBuilder

  // ── Boot ─────────────────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', function () {
    XFBuilder.init();
  });

  // Expose for debugging.
  window.XFBuilder = XFBuilder;

}());
