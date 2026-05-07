/**
 * Xtreme Forms Dashboard JS (Sprint 4 — Feature 4.1 + 4.2)
 *
 * Handles:
 *  - Leads-over-time line chart with selectable date ranges + custom date picker
 *  - Leads-by-form bar chart
 *  - Client-side sortable form comparison table
 *
 * Depends on: Chart.js (xf-chartjs), xtremeFormsDashboardData (wp_localize_script)
 */
/* global xtremeFormsDashboardData, xtremeFormsDashboardInitialData, xtremeFormsFormMetricsData, Chart */

(function () {
	'use strict';

	// ── Shared state ──────────────────────────────────────────────────────────

	let lineChart = null;
	let barChart  = null;

	const cfg    = window.xtremeFormsDashboardData || {};
	const i18n   = cfg.i18n   || {};
	const ajaxUrl = cfg.ajaxUrl || '';
	// Per-endpoint nonces (each analytics endpoint requires its own nonce action).
	const nonces  = cfg.nonces  || {};
	// Legacy fallback — used for any endpoint not in the per-endpoint map.
	const nonce   = cfg.nonce   || '';
	const init    = window.xtremeFormsDashboardInitialData || {};
	const isFormMetrics = !! cfg.isFormMetrics;

	// ── Utility: fetch wrapper with error handling ────────────────────────────

	async function apiFetch( action, body = {} ) {
		const fd = new FormData();
		fd.append( 'action', action );
		// Use the per-endpoint nonce if available, otherwise fall back to the generic nonce.
		fd.append( 'nonce', nonces[ action ] || nonce );
		for ( const [ k, v ] of Object.entries( body ) ) {
			fd.append( k, v );
		}
		const resp = await fetch( ajaxUrl, { method: 'POST', body: fd } );
		if ( ! resp.ok ) {
			throw new Error( `HTTP ${ resp.status }` );
		}
		const json = await resp.json();
		if ( ! json.success ) {
			throw new Error( json.data?.message || i18n.error );
		}
		return json.data;
	}

	// ── Chart.js default options ──────────────────────────────────────────────

	const CHART_DEFAULTS = {
		responsive: true,
		maintainAspectRatio: false,
		plugins: {
			legend: { display: false },
			tooltip: { enabled: true },
		},
	};

	const COLOR_PRIMARY = '#1A73E8';
	const COLOR_ACCENT  = '#FF6B35';

	// ── Line Chart (Leads Over Time) ──────────────────────────────────────────

	function initLineChart() {
		const canvas = document.getElementById( 'xf-line-chart' );
		if ( ! canvas ) return;

		// Load 30-day range by default.
		loadLineChart( '30d' );

		// Range tab buttons.
		const tabs = document.querySelectorAll( '#xf-line-range-tabs .xf-range-tab' );
		tabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				tabs.forEach( function ( t ) { t.classList.remove( 'xf-range-tab-active' ); } );
				tab.classList.add( 'xf-range-tab-active' );

				const range = tab.dataset.range;

				if ( 'custom' === range ) {
					document.getElementById( 'xf-custom-range' ).style.display = 'flex';
				} else {
					document.getElementById( 'xf-custom-range' ).style.display = 'none';
					loadLineChart( range );
				}
			} );
		} );

		// Custom range apply button.
		const applyBtn = document.getElementById( 'xf-custom-range-apply' );
		if ( applyBtn ) {
			applyBtn.addEventListener( 'click', function () {
				const fromInput = document.getElementById( 'xf-custom-from' );
				const toInput   = document.getElementById( 'xf-custom-to' );
				const errEl     = document.getElementById( 'xf-custom-range-error' );

				const from = fromInput ? fromInput.value : '';
				const to   = toInput   ? toInput.value   : '';

				// Client-side validation: end date must not be before start date.
				if ( from && to && new Date( to ) < new Date( from ) ) {
					errEl.textContent = i18n.invalidDateRange;
					errEl.style.display = 'inline';
					return; // Do NOT fire request.
				}
				errEl.style.display = 'none';

				loadLineChart( 'custom', from, to );
			} );
		}
	}

	async function loadLineChart( range, dateFrom = '', dateTo = '' ) {
		const canvas    = document.getElementById( 'xf-line-chart' );
		const errEl     = document.getElementById( 'xf-line-chart-error' );
		const noDataEl  = document.getElementById( 'xf-line-chart-no-data' );
		const emptyEl   = document.getElementById( 'xf-line-chart-empty' );

		if ( ! canvas ) return;

		// Show loading state.
		canvas.style.display = 'none';
		if ( errEl   ) errEl.style.display = 'none';
		if ( noDataEl ) noDataEl.style.display = 'none';

		try {
			const body = { range };
			if ( 'custom' === range ) {
				body.date_from = dateFrom;
				body.date_to   = dateTo;
			}

			const data = await apiFetch( 'xtremeforms_chart_leads_over_time', body );

			const labels = data.labels  || [];
			const values = data.data    || [];

			// Determine if there's any non-zero data.
			const hasData = values.some( function ( v ) { return v > 0; } );

			if ( emptyEl ) emptyEl.style.display = 'none';
			canvas.style.display = 'block';

			if ( ! hasData && noDataEl ) {
				// Show flat zero line + "No data for this period" label.
				noDataEl.style.display = 'block';
			}

			renderLineChart( canvas, labels, values );

		} catch ( err ) {
			// Show visible inline error message in place of chart.
			canvas.style.display = 'none';
			if ( errEl ) {
				errEl.querySelector( '.xf-chart-error-msg' ).textContent = i18n.error;
				errEl.style.display = 'flex';
			}
		}
	}

	function renderLineChart( canvas, labels, values ) {
		if ( lineChart ) {
			lineChart.destroy();
		}

		lineChart = new Chart( canvas, {
			type: 'line',
			data: {
				labels,
				datasets: [ {
					label: i18n.leadsOverTime || 'Leads',
					data: values,
					borderColor: COLOR_PRIMARY,
					backgroundColor: COLOR_PRIMARY + '22',
					fill: true,
					tension: 0.3,
					pointBackgroundColor: COLOR_PRIMARY,
					pointRadius: 3,
				} ],
			},
			options: {
				...CHART_DEFAULTS,
				scales: {
					y: {
						beginAtZero: true,
						ticks: { stepSize: 1, precision: 0 },
					},
					x: {
						ticks: { maxRotation: 45 },
					},
				},
			},
		} );
	}

	// ── Bar Chart (Leads by Form) ─────────────────────────────────────────────

	function initBarChart() {
		const canvas = document.getElementById( 'xf-bar-chart' );
		if ( ! canvas ) return;

		// Load all-time data by default.
		loadBarChart();

		// Wire up date-range tab buttons for the bar chart.
		const barTabs = document.querySelectorAll( '#xf-bar-range-tabs .xf-range-tab' );
		barTabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				barTabs.forEach( function ( t ) { t.classList.remove( 'xf-range-tab-active' ); } );
				tab.classList.add( 'xf-range-tab-active' );

				const range = tab.dataset.range;

				if ( 'custom' === range ) {
					document.getElementById( 'xf-bar-custom-range' ).style.display = 'flex';
				} else {
					const customRange = document.getElementById( 'xf-bar-custom-range' );
					if ( customRange ) customRange.style.display = 'none';

					if ( 'all' === range ) {
						loadBarChart();
					} else {
						const days = parseInt( range, 10 );
						const toDate   = new Date();
						const fromDate = new Date( Date.now() - days * 24 * 60 * 60 * 1000 );
						loadBarChart( formatDate( fromDate ), formatDate( toDate ) );
					}
				}
			} );
		} );

		// Wire up custom range Apply button for the bar chart.
		const barApplyBtn = document.getElementById( 'xf-bar-custom-range-apply' );
		if ( barApplyBtn ) {
			barApplyBtn.addEventListener( 'click', function () {
				const fromInput = document.getElementById( 'xf-bar-custom-from' );
				const toInput   = document.getElementById( 'xf-bar-custom-to' );
				const errEl     = document.getElementById( 'xf-bar-custom-range-error' );

				const from = fromInput ? fromInput.value : '';
				const to   = toInput   ? toInput.value   : '';

				if ( from && to && new Date( to ) < new Date( from ) ) {
					if ( errEl ) {
						errEl.textContent = i18n.invalidDateRange;
						errEl.style.display = 'inline';
					}
					return;
				}
				if ( errEl ) errEl.style.display = 'none';

				loadBarChart( from, to );
			} );
		}
	}

	/**
	 * Format a Date object as YYYY-MM-DD for use in date inputs / API requests.
	 *
	 * @param {Date} d
	 * @returns {string}
	 */
	function formatDate( d ) {
		const y  = d.getFullYear();
		const m  = String( d.getMonth() + 1 ).padStart( 2, '0' );
		const dy = String( d.getDate() ).padStart( 2, '0' );
		return `${ y }-${ m }-${ dy }`;
	}

	async function loadBarChart( dateFrom = '', dateTo = '' ) {
		const canvas  = document.getElementById( 'xf-bar-chart' );
		const errEl   = document.getElementById( 'xf-bar-chart-error' );
		const emptyEl = document.getElementById( 'xf-bar-chart-empty' );

		if ( ! canvas ) return;

		canvas.style.display = 'none';
		if ( errEl ) errEl.style.display = 'none';

		try {
			const body = {};
			if ( dateFrom ) body.date_from = dateFrom;
			if ( dateTo   ) body.date_to   = dateTo;

			const data = await apiFetch( 'xtremeforms_chart_leads_by_form', body );

			if ( emptyEl ) emptyEl.style.display = 'none';
			canvas.style.display = 'block';

			renderBarChart( canvas, data.labels || [], data.values || [] );

		} catch ( err ) {
			canvas.style.display = 'none';
			if ( errEl ) {
				const msgEl = errEl.querySelector( '.xf-chart-error-msg' );
				if ( msgEl ) msgEl.textContent = i18n.error;
				errEl.style.display = 'flex';
			}
		}
	}

	function renderBarChart( canvas, labels, values ) {
		if ( barChart ) {
			barChart.destroy();
		}

		barChart = new Chart( canvas, {
			type: 'bar',
			data: {
				labels,
				datasets: [ {
					label: i18n.leadsByForm || 'Leads',
					data: values,
					backgroundColor: COLOR_ACCENT + 'cc',
					borderColor: COLOR_ACCENT,
					borderWidth: 1,
					borderRadius: 4,
				} ],
			},
			options: {
				...CHART_DEFAULTS,
				scales: {
					y: {
						beginAtZero: true,
						ticks: { stepSize: 1, precision: 0 },
					},
					x: {
						ticks: { maxRotation: 45 },
					},
				},
			},
		} );
	}

	// ── Form Comparison Table (client-side sort + pagination) ─────────────────

	const TABLE_PER_PAGE = 25;

	/**
	 * Initialise the form metrics comparison table with sortable columns and
	 * client-side pagination. Pagination controls are rendered below the table
	 * and update when a column is sorted so the user always sees the correct
	 * page-slice of the full sorted dataset.
	 */
	function initMetricsTable() {
		const table = document.getElementById( 'xf-metrics-comparison-table' );
		if ( ! table ) return;

		// All metrics passed from PHP into window.xtremeFormsFormMetricsData for full-dataset sorting.
		const allData = window.xtremeFormsFormMetricsData || [];

		let sortCol  = 'submissions';
		let sortDir  = 'desc'; // Default: submissions descending.
		let currentPage = 1;

		const thead        = table.querySelector( 'thead' );
		const tbody        = document.getElementById( 'xf-metrics-tbody' );
		const paginationEl = document.getElementById( 'xf-metrics-pagination' );
		if ( ! thead || ! tbody ) return;

		// Hide the server-side pagination fallback — JS pagination takes over.
		const serverPagination = document.getElementById( 'xf-metrics-pagination-server' );
		if ( serverPagination ) serverPagination.style.display = 'none';

		/** Re-sort, reset to page 1, and redraw table + pagination. */
		function applySort( col ) {
			if ( sortCol === col ) {
				sortDir = sortDir === 'asc' ? 'desc' : 'asc';
			} else {
				sortCol = col;
				sortDir = 'desc';
			}
			currentPage = 1;
			redraw();
		}

		/** Render the table body and pagination for the current sort + page. */
		function redraw() {
			const sorted = sortData( allData, sortCol, sortDir );
			renderMetricsTablePage( tbody, sorted, currentPage );
			renderPagination( paginationEl, sorted.length, currentPage, function ( page ) {
				currentPage = page;
				redraw();
			} );
			updateSortArrows();
		}

		/** Update column header arrows to reflect active sort. */
		function updateSortArrows() {
			thead.querySelectorAll( '.xf-sortable' ).forEach( function ( h ) {
				h.classList.remove( 'xf-col-sort-active', 'xf-sort-asc', 'xf-sort-desc' );
				const arrow = h.querySelector( '.xf-sort-arrow' );
				if ( arrow ) arrow.textContent = '';
			} );
			const activeTh = thead.querySelector( '[data-col="' + sortCol + '"]' );
			if ( activeTh ) {
				activeTh.classList.add( 'xf-col-sort-active', 'xf-sort-' + sortDir );
				const arrow = activeTh.querySelector( '.xf-sort-arrow' );
				if ( arrow ) arrow.textContent = sortDir === 'asc' ? '↑' : '↓';
			}
		}

		// Attach click listeners to sortable headers.
		thead.querySelectorAll( '.xf-sortable' ).forEach( function ( th ) {
			th.addEventListener( 'click', function () {
				applySort( th.dataset.col );
			} );
		} );

		// Initial render — set default arrow and draw first page.
		redraw();
	}

	function sortData( data, col, dir ) {
		return [ ...data ].sort( function ( a, b ) {
			let va = a[ col ];
			let vb = b[ col ];

			// Nulls always last regardless of sort direction.
			const aNull = va === null || va === undefined || va === '';
			const bNull = vb === null || vb === undefined || vb === '';
			if ( aNull && bNull ) return 0;
			if ( aNull ) return 1;
			if ( bNull ) return -1;

			if ( typeof va === 'string' && typeof vb === 'string' ) {
				va = va.toLowerCase();
				vb = vb.toLowerCase();
			}

			if ( va < vb ) return dir === 'asc' ? -1 : 1;
			if ( va > vb ) return dir === 'asc' ? 1  : -1;
			return 0;
		} );
	}

	/**
	 * Render one page of the metrics table rows.
	 *
	 * @param {HTMLElement} tbody    The <tbody> element to populate.
	 * @param {Array}       data     Full sorted dataset.
	 * @param {number}      page     1-based page number.
	 */
	function renderMetricsTablePage( tbody, data, page ) {
		const start    = ( page - 1 ) * TABLE_PER_PAGE;
		const pageData = data.slice( start, start + TABLE_PER_PAGE );

		tbody.innerHTML = '';

		if ( ! data.length ) {
			const tr = document.createElement( 'tr' );
			const td = document.createElement( 'td' );
			td.colSpan   = 5;
			td.textContent = 'No forms found.';
			tr.classList.add( 'xf-no-data-row' );
			tr.appendChild( td );
			tbody.appendChild( tr );
			return;
		}

		pageData.forEach( function ( m ) {
			const tr = document.createElement( 'tr' );
			tr.dataset.formId = m.form_id;

			// Conversion rate display.
			let rateHtml;
			if ( null === m.conversion_rate || undefined === m.conversion_rate || '' === m.conversion_rate ) {
				rateHtml = '—';
			} else {
				const rateStr = parseFloat( m.conversion_rate ).toFixed( 2 ) + '%';
				if ( m.conversion_rate_warning ) {
					rateHtml = rateStr + ' <span class="xf-warning-icon dashicons dashicons-warning" title="' +
						escHtml( ( window.xtremeFormsDashboardData && window.xtremeFormsDashboardData.i18n.conversionWarning ) || '' ) +
						'"></span>';
				} else {
					rateHtml = rateStr;
				}
			}

			// Avg time display.
			let avgHtml;
			if ( null === m.avg_seconds || undefined === m.avg_seconds || '' === m.avg_seconds ) {
				avgHtml = '—';
			} else {
				const sec = parseFloat( m.avg_seconds );
				if ( sec >= 60 ) {
					const mins = Math.floor( sec / 60 );
					const secs = Math.round( sec % 60 );
					avgHtml = mins + 'm ' + secs + 's';
				} else {
					avgHtml = Math.round( sec ) + 's';
				}
			}

			tr.innerHTML = '<td class="xf-col-form-name"><strong>' + escHtml( m.form_name ) +
				'</strong><div class="xf-form-meta"><code>[xtreme_forms id="' + escHtml( String( m.form_id ) ) + '"]</code></div></td>' +
				'<td class="xf-col-views">' + escHtml( String( m.views ) ) + '</td>' +
				'<td class="xf-col-submissions">' + escHtml( String( m.submissions ) ) + '</td>' +
				'<td class="xf-col-conversion">' + rateHtml + '</td>' +
				'<td class="xf-col-avg-time">' + avgHtml + '</td>';

			tbody.appendChild( tr );
		} );
	}

	/**
	 * Render pagination navigation controls.
	 *
	 * @param {HTMLElement|null} container  Element to render nav into (may be null — skip).
	 * @param {number}           total      Total items in the full dataset.
	 * @param {number}           current    Current 1-based page.
	 * @param {Function}         onPage     Callback( pageNumber ) invoked on page click.
	 */
	function renderPagination( container, total, current, onPage ) {
		if ( ! container ) return;

		const totalPages = Math.ceil( total / TABLE_PER_PAGE );

		// Hide controls when everything fits on one page.
		if ( totalPages <= 1 ) {
			container.innerHTML = '';
			container.style.display = 'none';
			return;
		}

		container.style.display = 'flex';

		const start = ( current - 1 ) * TABLE_PER_PAGE + 1;
		const end   = Math.min( current * TABLE_PER_PAGE, total );

		let html = '<span class="xf-pagination-label">' +
			escHtml( start + '–' + end + ' of ' + total + ' forms' ) +
			'</span>';

		html += '<span class="xf-pagination-controls">';

		// Previous button.
		if ( current > 1 ) {
			html += '<button class="button xf-page-btn" data-page="' + ( current - 1 ) + '" aria-label="Previous page">&laquo; Prev</button>';
		} else {
			html += '<button class="button xf-page-btn" disabled aria-disabled="true">&laquo; Prev</button>';
		}

		// Page number buttons — show up to 5 around the current page.
		const pageWindow = 2;
		const pageMin    = Math.max( 1, current - pageWindow );
		const pageMax    = Math.min( totalPages, current + pageWindow );

		if ( pageMin > 1 ) {
			html += '<button class="button xf-page-btn" data-page="1">1</button>';
			if ( pageMin > 2 ) {
				html += '<span class="xf-pagination-ellipsis">…</span>';
			}
		}

		for ( let p = pageMin; p <= pageMax; p++ ) {
			if ( p === current ) {
				html += '<button class="button button-primary xf-page-btn xf-page-current" data-page="' + p + '" aria-current="page">' + p + '</button>';
			} else {
				html += '<button class="button xf-page-btn" data-page="' + p + '">' + p + '</button>';
			}
		}

		if ( pageMax < totalPages ) {
			if ( pageMax < totalPages - 1 ) {
				html += '<span class="xf-pagination-ellipsis">…</span>';
			}
			html += '<button class="button xf-page-btn" data-page="' + totalPages + '">' + totalPages + '</button>';
		}

		// Next button.
		if ( current < totalPages ) {
			html += '<button class="button xf-page-btn" data-page="' + ( current + 1 ) + '" aria-label="Next page">Next &raquo;</button>';
		} else {
			html += '<button class="button xf-page-btn" disabled aria-disabled="true">Next &raquo;</button>';
		}

		html += '</span>';

		container.innerHTML = html;

		// Attach click handlers.
		container.querySelectorAll( '.xf-page-btn[data-page]' ).forEach( function ( btn ) {
			btn.addEventListener( 'click', function () {
				const page = parseInt( btn.dataset.page, 10 );
				if ( ! isNaN( page ) ) {
					onPage( page );
				}
			} );
		} );
	}

	function escHtml( str ) {
		const div = document.createElement( 'div' );
		div.appendChild( document.createTextNode( str ) );
		return div.innerHTML;
	}

	// ── Init ──────────────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! isFormMetrics ) {
			// Dashboard page.
			if ( typeof Chart !== 'undefined' ) {
				initLineChart();
				initBarChart();
			}
		} else {
			// Form metrics page.
			initMetricsTable();
		}
	} );

} )();
