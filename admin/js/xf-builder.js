/**
 * Xtreme Forms — Drag-and-Drop Builder
 * Pure vanilla JS, no dependencies.
 *
 * @package Xtreme Forms
 */

/* global xfBuilderData */

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
      id:          makeId(),
      type:        type,
      label:       labelForType(type),
      placeholder: '',
      required:    false,
      options:     defaultOptionsForType(type),
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
    },

    // ── DOM refs ────────────────────────────────────────────────────────

    els: {},

    // ── Init ────────────────────────────────────────────────────────────

    init: function () {
      this.els.palette     = document.getElementById('xfb-palette');
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
      return {
        id:          f.id || makeId(),
        type:        builderType,
        label:       f.label || labelForType(builderType),
        placeholder: f.placeholder || '',
        required:    !!f.required,
        options:     Array.isArray(f.options) ? f.options : [],
      };
    },

    // ── Get current page ─────────────────────────────────────────────────

    currentPageObj: function () {
      var id = this.state.currentPage;
      return this.state.pages.find(function (p) { return p.id === id; }) || this.state.pages[0];
    },

    // ── Render palette ───────────────────────────────────────────────────

    renderPalette: function () {
      var self = this;
      this.els.palette.innerHTML = '';

      FIELD_TYPES.forEach(function (ft) {
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

      // Show/hide empty hint.
      if (this.els.emptyHint) {
        this.els.emptyHint.style.display = fields.length === 0 ? '' : 'none';
      }

      // Top drop gap.
      inner.appendChild(this.makeDropGap(0));

      fields.forEach(function (field, idx) {
        var card = self.renderField(field);
        inner.appendChild(card);
        inner.appendChild(self.makeDropGap(idx + 1));
      });

      // Render settings panel.
      this.renderSettings(
        fields.find(function (f) { return f.id === self.state.selectedFieldId; }) || null
      );
    },

    // Create a drop gap element.
    makeDropGap: function (index) {
      var gap = document.createElement('div');
      gap.className = 'xfb-drop-gap';
      gap.dataset.dropIndex = index;
      return gap;
    },

    // ── Render a single field card ───────────────────────────────────────

    renderField: function (field) {
      var self = this;
      var card = document.createElement('div');
      card.className = 'xfb-field-card' + (field.id === this.state.selectedFieldId ? ' selected' : '');
      card.dataset.fieldId = field.id;
      card.draggable = true;

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

        var copyBtn = document.createElement('button');
        copyBtn.type = 'button';
        copyBtn.textContent = 'Copy';
        copyBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          self.duplicateField(field.id);
        });
        toolbar.appendChild(copyBtn);

        var delBtn = document.createElement('button');
        delBtn.type = 'button';
        delBtn.className = 'xfb-toolbar-delete';
        delBtn.textContent = 'Delete';
        delBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          self.deleteField(field.id);
        });
        toolbar.appendChild(delBtn);

        // Required toggle on the right.
        var reqLabel = document.createElement('label');
        reqLabel.className = 'xfb-toolbar-required';
        var reqCb = document.createElement('input');
        reqCb.type = 'checkbox';
        reqCb.checked = field.required;
        reqCb.addEventListener('change', function (e) {
          e.stopPropagation();
          self.updateFieldProp(field.id, 'required', reqCb.checked);
        });
        reqLabel.appendChild(reqCb);
        reqLabel.appendChild(document.createTextNode(' Required'));
        toolbar.appendChild(reqLabel);

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
          var inp = document.createElement('input');
          inp.type = 'text';
          inp.disabled = true;
          inp.placeholder = field.placeholder || 'Enter text…';
          wrap.appendChild(inp);
          break;
        }
        case 'textarea': {
          var ta = document.createElement('textarea');
          ta.disabled = true;
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
          var checkOpts = field.options && field.options.length ? field.options : ['Option 1', 'Option 2'];
          checkOpts.forEach(function (o) {
            var li = document.createElement('li');
            li.className = 'xfb-option-item';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.disabled = true;
            var span = document.createElement('span');
            span.textContent = o;
            li.appendChild(cb);
            li.appendChild(span);
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

      // Placeholder (textbox, textarea).
      if (field.type === 'textbox' || field.type === 'textarea') {
        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label" for="xfbs-placeholder">Placeholder</label>';
        html += '<input class="xfb-sp-input" id="xfbs-placeholder" type="text" data-prop="placeholder" value="' + this.esc(field.placeholder) + '">';
        html += '</div>';
      }

      // Options (dropdown, radio, checkbox).
      if (field.type === 'dropdown' || field.type === 'radio' || field.type === 'checkbox') {
        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label" for="xfbs-options">Options <span style="font-weight:400;text-transform:none;font-size:10px;color:#9ca3af;">(one per line)</span></label>';
        html += '<textarea class="xfb-sp-textarea" id="xfbs-options" data-prop="options" rows="5">' +
                this.esc((field.options || []).join('\n')) +
                '</textarea>';
        html += '<div class="xfb-sp-hint">Enter each option on its own line.</div>';
        html += '</div>';
      }

      // Dropdown placeholder text.
      if (field.type === 'dropdown') {
        html += '<div class="xfb-sp-field">';
        html += '<label class="xfb-sp-label" for="xfbs-placeholder">Placeholder</label>';
        html += '<input class="xfb-sp-input" id="xfbs-placeholder" type="text" data-prop="placeholder" value="' + this.esc(field.placeholder) + '">';
        html += '<div class="xfb-sp-hint">The blank/default choice shown first.</div>';
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

      html += '</div>'; // .xfb-sp-inner

      body.innerHTML = html;

      // Bind live update events.
      var inputs = body.querySelectorAll('[data-prop]');
      inputs.forEach(function (inp) {
        var prop = inp.dataset.prop;
        var eventType = (inp.tagName === 'TEXTAREA' || inp.type === 'text') ? 'input' : 'change';
        inp.addEventListener(eventType, function () {
          var value;
          if (prop === 'required') {
            value = inp.checked;
          } else if (prop === 'options') {
            value = inp.value.split('\n').map(function (s) { return s.trim(); }).filter(Boolean);
          } else {
            value = inp.value;
          }
          self.updateFieldProp(field.id, prop, value);
        });
      });
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

    updateFieldProp: function (fieldId, prop, value) {
      var pg = this.currentPageObj();
      var field = pg.fields.find(function (f) { return f.id === fieldId; });
      if (!field) return;

      field[prop] = value;

      // Re-render only the specific field card in the canvas — do NOT
      // rebuild the full settings panel, which would destroy the focused
      // input and lose the user's cursor position.
      var existing = this.els.canvasInner.querySelector('[data-field-id="' + fieldId + '"]');
      if (existing) {
        var updated = this.renderField(field);
        existing.parentNode.replaceChild(updated, existing);
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
              id:          f.id,
              type:        legacyType,
              label:       f.label,
              placeholder: f.placeholder,
              required:    f.required,
              options:     f.options || [],
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
