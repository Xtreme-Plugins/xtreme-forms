/**
 * Xtreme Forms Dashboard JS (Sprint 4 — Feature 4.1 + 4.2)
 *
 * Handles:
 *  - Leads-over-time line chart with selectable date ranges + custom date picker
 *  - Leads-by-form bar chart
 *  - Client-side sortable form comparison table
 *
 * Depends on: Chart.js (xf-chartjs), xtremeformsDashboardData (wp_localize_script)
 */
/* global xtremeformsDashboardData, xtremeformsDashboardInitialData, xtremeformsFormMetricsData, Chart */

(function () {
	'use strict';

	// ── Shared state ──────────────────────────────────────────────────────────

	let lineChart = null;
	let barChart  = null;

	const cfg    = window.xtremeformsDashboardData || {};
	const i18n   = cfg.i18n   || {};
	const ajaxUrl = cfg.ajaxUrl || '';
	// Per-endpoint nonces (each analytics endpoint requires its own nonce action).
	const nonces  = cfg.nonces  || {};
	// Legacy fallback — used for any endpoint not in the per-endpoint map.
	const nonce   = cfg.nonce   || '';
	const init    = window.xtremeformsDashboardInitialData || {};
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
		const legend  = document.getElementById( 'xf-leads-donut-legend' );

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

			renderBarChart( canvas, data.labels || [], data.values || [], legend );

		} catch ( err ) {
			canvas.style.display = 'none';
			if ( legend ) legend.innerHTML = '';
			if ( errEl ) {
				const msgEl = errEl.querySelector( '.xf-chart-error-msg' );
				if ( msgEl ) msgEl.textContent = i18n.error;
				errEl.style.display = 'flex';
			}
		}
	}

	// Vibrant palette for the Leads-by-Form donut, in the order forms arrive
	// (most leads → least). Mirrors AUDIENCE_PALETTE so the dashboard reads
	// consistently across the donut charts.
	const LEADS_BY_FORM_PALETTE = [
		'#0ABAB5', // teal
		'#FF6B35', // orange
		'#3b82f6', // blue
		'#a855f7', // purple
		'#22c55e', // green
		'#ec4899', // pink
		'#eab308', // amber
		'#64748b', // slate
	];

	function renderBarChart( canvas, labels, values, legend ) {
		const total    = values.reduce( function ( a, b ) { return a + ( +b || 0 ); }, 0 );
		const totalEl  = document.getElementById( 'xf-leads-donut-total' );
		if ( totalEl ) totalEl.textContent = total.toLocaleString();

		const colors = labels.map( function ( _l, i ) {
			return LEADS_BY_FORM_PALETTE[ i % LEADS_BY_FORM_PALETTE.length ];
		} );

		if ( barChart ) {
			barChart.data.labels                     = labels;
			barChart.data.datasets[ 0 ].data         = values;
			barChart.data.datasets[ 0 ].backgroundColor = colors;
			barChart.update();
		} else {
			barChart = new Chart( canvas, {
				type: 'doughnut',
				data: {
					labels,
					datasets: [ {
						data: values,
						backgroundColor: colors,
						borderColor: '#ffffff',
						borderWidth: 2,
						hoverOffset: 6,
					} ],
				},
				options: {
					responsive: false, // fixed pixel sizing — guaranteed render
					maintainAspectRatio: false,
					cutout: '70%',
					animation: { animateRotate: true, duration: 600, easing: 'easeOutCubic' },
					plugins: {
						legend: { display: false },
						tooltip: {
							backgroundColor: '#0f172a',
							titleColor: '#fff',
							bodyColor: '#cbd5e1',
							padding: 10,
							cornerRadius: 6,
							displayColors: false,
							callbacks: {
								label: function ( ctx ) {
									const t = total || 1;
									const p = ( ( ctx.parsed / t ) * 100 ).toFixed( 1 );
									return ctx.label + ': ' + ctx.parsed.toLocaleString() + ' (' + p + '%)';
								},
							},
						},
					},
				},
			} );
		}

		// Side legend: colored dot + form name on the left, count (pct%) on the right.
		if ( legend ) {
			if ( ! labels.length ) {
				legend.innerHTML =
					'<li class="xf-leads-donut-legend-empty">' +
						escHtml( i18n.noLeadsByForm || 'No submissions yet.' ) +
					'</li>';
			} else {
				let html = '';
				labels.forEach( function ( label, i ) {
					const count = +values[ i ] || 0;
					const pct   = total > 0 ? ( count / total * 100 ) : 0;
					const pctTxt = pct.toFixed( pct >= 10 ? 1 : 1 );
					html +=
						'<li class="xf-leads-donut-legend-row" data-idx="' + i + '">' +
							'<span class="xf-leads-donut-swatch" style="background:' + colors[ i ] + '"></span>' +
							'<span class="xf-leads-donut-legend-label">' + escHtml( label ) + '</span>' +
							'<span class="xf-leads-donut-legend-count">' +
								count.toLocaleString() +
								' <span class="xf-leads-donut-legend-pct">(' + pctTxt + '%)</span>' +
							'</span>' +
						'</li>';
				} );
				legend.innerHTML = html;

				// Hover legend row → highlight wedge (mirrors audience-mini behaviour).
				legend.querySelectorAll( '.xf-leads-donut-legend-row' ).forEach( function ( row ) {
					const idx = parseInt( row.dataset.idx, 10 );
					row.addEventListener( 'mouseenter', function () {
						if ( ! barChart ) return;
						barChart.setActiveElements( [ { datasetIndex: 0, index: idx } ] );
						if ( barChart.tooltip ) barChart.tooltip.setActiveElements( [ { datasetIndex: 0, index: idx } ], { x: 0, y: 0 } );
						barChart.update();
					} );
					row.addEventListener( 'mouseleave', function () {
						if ( ! barChart ) return;
						barChart.setActiveElements( [] );
						if ( barChart.tooltip ) barChart.tooltip.setActiveElements( [], { x: 0, y: 0 } );
						barChart.update();
					} );
				} );
			}
		}
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

		// All metrics passed from PHP into window.xtremeformsFormMetricsData for full-dataset sorting.
		const allData = window.xtremeformsFormMetricsData || [];

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
						escHtml( ( window.xtremeformsDashboardData && window.xtremeformsDashboardData.i18n.conversionWarning ) || '' ) +
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

	// ── Audience Insights — 3 compact mini-donuts ───────────────────────────

	// Vibrant palette used in the order rows arrive (most → least).
	const AUDIENCE_PALETTE = [
		'#0ABAB5', // teal
		'#3b82f6', // blue
		'#a855f7', // purple
		'#FF6B35', // orange
		'#22c55e', // green
		'#ec4899', // pink
		'#eab308', // amber
		'#64748b', // slate
	];

	const audienceCharts = {}; // view → Chart instance
	let   audienceData   = null;
	let   audienceRange  = 'all';

	function initAudienceChart() {
		// Range tabs (All / 30d / 90d).
		const rangeTabs = document.querySelectorAll( '#xf-audience-range-tabs .xf-range-tab' );
		if ( ! rangeTabs.length ) return;

		rangeTabs.forEach( function ( tab ) {
			tab.addEventListener( 'click', function () {
				rangeTabs.forEach( function ( t ) { t.classList.remove( 'xf-range-tab-active' ); } );
				tab.classList.add( 'xf-range-tab-active' );
				audienceRange = tab.dataset.range || 'all';
				loadAudience();
			} );
		} );

		// Click on a mini panel cycles through its top segment (subtle interactivity hint).
		document.querySelectorAll( '.xf-audience-mini' ).forEach( function ( mini ) {
			mini.addEventListener( 'click', function ( e ) {
				if ( e.target.closest( 'a, button' ) ) return;
				const view = mini.dataset.view;
				if ( ! audienceData || ! audienceData[ view ] ) return;
				const rows = audienceData[ view ];
				if ( rows.length < 2 ) return;
				// Rotate the array so the highlighted segment changes on click.
				audienceData[ view ] = rows.slice( 1 ).concat( rows.slice( 0, 1 ) );
				renderMini( view );
			} );
		} );

		loadAudience();
	}

	async function loadAudience() {
		const errEl    = document.getElementById( 'xf-audience-error' );
		const emptyEl  = document.getElementById( 'xf-audience-empty' );
		if ( errEl )   errEl.style.display = 'none';
		if ( emptyEl ) emptyEl.style.display = 'none';

		try {
			audienceData = await apiFetch( 'xtremeforms_user_agent_report', { range: audienceRange } );

			if ( ! audienceData || ( audienceData.total || 0 ) === 0 ) {
				hideMinis();
				if ( emptyEl ) emptyEl.style.display = 'flex';
				return;
			}
			showMinis();
			[ 'device', 'browser', 'os' ].forEach( renderMini );
		} catch ( e ) {
			hideMinis();
			if ( errEl ) {
				const msg = errEl.querySelector( '.xf-audience-error-msg' );
				if ( msg && e && e.message ) msg.textContent = e.message;
				errEl.style.display = 'flex';
			}
		}
	}

	function hideMinis() {
		document.querySelectorAll( '.xf-audience-mini' ).forEach( function ( m ) { m.style.visibility = 'hidden'; } );
	}
	function showMinis() {
		document.querySelectorAll( '.xf-audience-mini' ).forEach( function ( m ) { m.style.visibility = 'visible'; } );
	}

	function renderMini( view ) {
		const canvas = document.getElementById( 'xf-audience-donut-' + view );
		if ( ! canvas || ! audienceData ) return;

		const rows  = audienceData[ view ] || [];
		const total = audienceData.total   || 0;

		// Center stat = top segment.
		const top    = rows[ 0 ] || null;
		const pctEl  = document.querySelector( '[data-pct-for="' + view + '"]' );
		const topEl  = document.querySelector( '[data-top-for="' + view + '"]' );
		const legend = document.querySelector( '[data-legend-for="' + view + '"]' );

		if ( ! top ) {
			if ( pctEl ) pctEl.textContent = '0%';
			if ( topEl ) topEl.textContent = '—';
			if ( legend ) legend.innerHTML = '';
			return;
		}

		const topPct = top.percentage != null
			? Math.round( top.percentage )
			: ( total > 0 ? Math.round( top.count / total * 100 ) : 0 );

		if ( pctEl ) pctEl.textContent = topPct + '%';
		if ( topEl ) topEl.textContent = top.label;

		const labels = rows.map( function ( r ) { return r.label; } );
		const values = rows.map( function ( r ) { return r.count; } );
		const colors = rows.map( function ( _r, i ) { return AUDIENCE_PALETTE[ i % AUDIENCE_PALETTE.length ]; } );

		if ( audienceCharts[ view ] ) {
			audienceCharts[ view ].data.labels                     = labels;
			audienceCharts[ view ].data.datasets[ 0 ].data         = values;
			audienceCharts[ view ].data.datasets[ 0 ].backgroundColor = colors;
			audienceCharts[ view ].update();
		} else {
			audienceCharts[ view ] = new Chart( canvas, {
				type: 'doughnut',
				data: {
					labels,
					datasets: [ {
						data: values,
						backgroundColor: colors,
						borderColor: '#ffffff',
						borderWidth: 2,
						hoverOffset: 6,
					} ],
				},
				options: {
					responsive: false, // fixed pixel sizing — guaranteed render
					maintainAspectRatio: false,
					cutout: '68%',
					animation: { animateRotate: true, duration: 600, easing: 'easeOutCubic' },
					plugins: {
						legend: { display: false },
						tooltip: {
							backgroundColor: '#0f172a',
							titleColor: '#fff',
							bodyColor: '#cbd5e1',
							padding: 10,
							cornerRadius: 6,
							displayColors: false,
							callbacks: {
								label: function ( ctx ) {
									const t = audienceData.total || 1;
									const p = ( ( ctx.parsed / t ) * 100 ).toFixed( 1 );
									return ctx.label + ': ' + ctx.parsed.toLocaleString() + ' (' + p + '%)';
								},
							},
						},
					},
				},
			} );
		}

		// Mini-legend rows (top 3).
		if ( legend ) {
			let html = '';
			rows.slice( 0, 3 ).forEach( function ( r, i ) {
				const color  = colors[ i ];
				const pct    = r.percentage != null ? r.percentage : ( total > 0 ? r.count / total * 100 : 0 );
				const pctTxt = pct.toFixed( pct >= 10 ? 0 : 1 );
				html +=
					'<li class="xf-audience-mini-legend-row" data-view="' + view + '" data-idx="' + i + '">' +
						'<span class="xf-audience-swatch" style="background:' + color + '"></span>' +
						'<span class="xf-audience-mini-legend-label">' + escHtml( r.label ) + '</span>' +
						'<span class="xf-audience-mini-legend-pct">' + pctTxt + '%</span>' +
					'</li>';
			} );
			legend.innerHTML = html;

			// Hover legend row → highlight wedge.
			legend.querySelectorAll( '.xf-audience-mini-legend-row' ).forEach( function ( row ) {
				const idx = parseInt( row.dataset.idx, 10 );
				row.addEventListener( 'mouseenter', function () {
					const c = audienceCharts[ view ];
					if ( ! c ) return;
					c.setActiveElements( [ { datasetIndex: 0, index: idx } ] );
					if ( c.tooltip ) c.tooltip.setActiveElements( [ { datasetIndex: 0, index: idx } ], { x: 0, y: 0 } );
					c.update();
				} );
				row.addEventListener( 'mouseleave', function () {
					const c = audienceCharts[ view ];
					if ( ! c ) return;
					c.setActiveElements( [] );
					if ( c.tooltip ) c.tooltip.setActiveElements( [], { x: 0, y: 0 } );
					c.update();
				} );
			} );
		}
	}

	// ── Copy-shortcode buttons (Top Performing Forms list) ────────────────────

	function initShortcodeCopy() {
		const buttons = document.querySelectorAll( '.xf-shortcode-copy' );
		if ( ! buttons.length ) return;

		buttons.forEach( function ( btn ) {
			btn.addEventListener( 'click', function ( ev ) {
				ev.preventDefault();
				ev.stopPropagation();

				const code = btn.dataset.shortcode || '';
				if ( ! code ) return;

				copyToClipboard( code ).then( function ( ok ) {
					flashCopyState( btn, ok );
				} );
			} );
		} );
	}

	/**
	 * Copy a string to the clipboard. Uses the async Clipboard API when
	 * available; falls back to a hidden textarea + document.execCommand for
	 * older browsers and for admin pages served over plain HTTP (the
	 * Clipboard API requires a secure context).
	 *
	 * @param {string} text
	 * @returns {Promise<boolean>} resolves with true on success.
	 */
	function copyToClipboard( text ) {
		if ( navigator.clipboard && window.isSecureContext ) {
			return navigator.clipboard.writeText( text ).then(
				function () { return true; },
				function () { return fallbackCopy( text ); }
			);
		}
		return Promise.resolve( fallbackCopy( text ) );
	}

	function fallbackCopy( text ) {
		const ta = document.createElement( 'textarea' );
		ta.value      = text;
		ta.setAttribute( 'readonly', '' );
		ta.style.position = 'fixed';
		ta.style.top  = '-1000px';
		ta.style.left = '-1000px';
		document.body.appendChild( ta );
		ta.select();
		let ok = false;
		try {
			ok = document.execCommand( 'copy' );
		} catch ( _e ) {
			ok = false;
		}
		document.body.removeChild( ta );
		return ok;
	}

	/**
	 * Briefly swap the clipboard icon for a check / cross to confirm the copy.
	 */
	function flashCopyState( btn, ok ) {
		const icon = btn.querySelector( '.dashicons' );
		if ( ! icon ) return;

		const original = icon.className;
		btn.classList.add( ok ? 'xf-shortcode-copy-ok' : 'xf-shortcode-copy-err' );
		icon.className = 'dashicons ' + ( ok ? 'dashicons-yes' : 'dashicons-no' );

		window.setTimeout( function () {
			icon.className = original;
			btn.classList.remove( 'xf-shortcode-copy-ok', 'xf-shortcode-copy-err' );
		}, 1400 );
	}

	// ── Init ──────────────────────────────────────────────────────────────────

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( ! isFormMetrics ) {
			// Dashboard page.
			if ( typeof Chart !== 'undefined' ) {
				initLineChart();
				initBarChart();
				initAudienceChart();
			}
			initShortcodeCopy();
		} else {
			// Form metrics page.
			initMetricsTable();
		}
	} );

} )();
