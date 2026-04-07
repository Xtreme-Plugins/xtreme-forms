<?php
/**
 * Xtreme Forms Dashboard — Overview page (Feature 4.1).
 *
 * All chart data is loaded asynchronously via AJAX after page render.
 * Empty states are displayed inline when there are no leads/forms.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Read-only display page; all data rendered server-side via WP functions.

// Fetch initial KPI data server-side for instant render (no FOUC).
$kpi_all_time   = XF_Analytics::count_leads_all_time();
$kpi_this_month = XF_Analytics::count_leads_this_month();
$kpi_this_week  = XF_Analytics::count_leads_this_week();
$funnel_data    = XF_Analytics::leads_by_status();
$top_pages      = XF_Analytics::top_source_pages( 10 );
$top_forms      = XF_Analytics::top_forms( 5 );
$utm_data       = XF_Analytics::utm_breakdown();

$total_leads = $kpi_all_time;
$has_forms   = ! empty( XF_Forms::get_all_forms() );

$add_form_url = add_query_arg(
	array(
		'page'      => 'xtremeleads-forms',
		'xf_action' => 'new',
	),
	admin_url( 'admin.php' )
);
$leads_url    = add_query_arg( array( 'page' => 'xtremeleads-leads' ), admin_url( 'admin.php' ) );
?>
<div class="wrap xf-wrap xf-dashboard-wrap">
	<h1 class="xf-page-title">
		<?php esc_html_e( 'Xtreme Forms Dashboard', 'xtreme-forms' ); ?>
		<a href="<?php echo esc_url( $leads_url ); ?>" class="button xf-btn-secondary">
			<?php esc_html_e( 'View All Leads', 'xtreme-forms' ); ?>
		</a>
	</h1>

	<!-- ── KPI Tiles ──────────────────────────────────────────────────────── -->
	<div class="xf-kpi-row">
		<div class="xf-kpi-tile">
			<div class="xf-kpi-icon"><span class="dashicons dashicons-email-alt"></span></div>
			<div class="xf-kpi-body">
				<span class="xf-kpi-label"><?php esc_html_e( 'All Time', 'xtreme-forms' ); ?></span>
				<span class="xf-kpi-value" id="xf-kpi-all-time"><?php echo esc_html( number_format_i18n( $kpi_all_time ) ); ?></span>
				<span class="xf-kpi-sublabel"><?php esc_html_e( 'Total Leads', 'xtreme-forms' ); ?></span>
			</div>
		</div>
		<div class="xf-kpi-tile">
			<div class="xf-kpi-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
			<div class="xf-kpi-body">
				<span class="xf-kpi-label"><?php esc_html_e( 'This Month', 'xtreme-forms' ); ?></span>
				<span class="xf-kpi-value" id="xf-kpi-this-month"><?php echo esc_html( number_format_i18n( $kpi_this_month ) ); ?></span>
				<span class="xf-kpi-sublabel"><?php echo esc_html( wp_date( 'F Y' ) ); ?></span>
			</div>
		</div>
		<div class="xf-kpi-tile">
			<div class="xf-kpi-icon"><span class="dashicons dashicons-clock"></span></div>
			<div class="xf-kpi-body">
				<span class="xf-kpi-label"><?php esc_html_e( 'This Week', 'xtreme-forms' ); ?></span>
				<span class="xf-kpi-value" id="xf-kpi-this-week"><?php echo esc_html( number_format_i18n( $kpi_this_week ) ); ?></span>
				<span class="xf-kpi-sublabel"><?php esc_html_e( 'Since Monday', 'xtreme-forms' ); ?></span>
			</div>
		</div>
	</div>

	<!-- ── Charts Row ─────────────────────────────────────────────────────── -->
	<div class="xf-charts-row">

		<!-- Leads Over Time (line chart) -->
		<div class="xf-card xf-chart-card xf-chart-card-line">
			<div class="xf-card-header">
				<h2><?php esc_html_e( 'Leads Over Time', 'xtreme-forms' ); ?></h2>
				<div class="xf-chart-controls">
					<div class="xf-range-tabs" id="xf-line-range-tabs" role="tablist">
						<button type="button" class="xf-range-tab" data-range="7d" role="tab"><?php esc_html_e( 'Last 7 Days', 'xtreme-forms' ); ?></button>
						<button type="button" class="xf-range-tab xf-range-tab-active" data-range="30d" role="tab"><?php esc_html_e( 'Last 30 Days', 'xtreme-forms' ); ?></button>
						<button type="button" class="xf-range-tab" data-range="90d" role="tab"><?php esc_html_e( 'Last 90 Days', 'xtreme-forms' ); ?></button>
						<button type="button" class="xf-range-tab" data-range="custom" role="tab"><?php esc_html_e( 'Custom', 'xtreme-forms' ); ?></button>
					</div>
					<div class="xf-custom-range" id="xf-custom-range" style="display:none;">
						<label for="xf-custom-from" class="screen-reader-text"><?php esc_html_e( 'Start date', 'xtreme-forms' ); ?></label>
						<input type="date" id="xf-custom-from" class="xf-date-input" placeholder="<?php esc_attr_e( 'Start date', 'xtreme-forms' ); ?>">
						<span class="xf-date-sep">—</span>
						<label for="xf-custom-to" class="screen-reader-text"><?php esc_html_e( 'End date', 'xtreme-forms' ); ?></label>
						<input type="date" id="xf-custom-to" class="xf-date-input" placeholder="<?php esc_attr_e( 'End date', 'xtreme-forms' ); ?>">
						<button type="button" id="xf-custom-range-apply" class="button button-small"><?php esc_html_e( 'Apply', 'xtreme-forms' ); ?></button>
						<span class="xf-date-error" id="xf-custom-range-error" style="display:none;color:var(--xf-danger)"></span>
					</div>
				</div>
			</div>
			<div class="xf-chart-body" id="xf-line-chart-body">
				<?php if ( $total_leads > 0 ) : ?>
					<canvas id="xf-line-chart" aria-label="<?php esc_attr_e( 'Leads over time chart', 'xtreme-forms' ); ?>" role="img"></canvas>
					<div class="xf-chart-error" id="xf-line-chart-error" style="display:none;">
						<span class="dashicons dashicons-warning"></span>
						<span class="xf-chart-error-msg"><?php esc_html_e( 'Failed to load chart data. Please try again.', 'xtreme-forms' ); ?></span>
					</div>
					<div class="xf-chart-no-data" id="xf-line-chart-no-data" style="display:none;">
						<span><?php esc_html_e( 'No data for this period', 'xtreme-forms' ); ?></span>
					</div>
				<?php else : ?>
					<div class="xf-empty-state" id="xf-line-chart-empty">
						<span class="dashicons dashicons-chart-line xf-empty-icon"></span>
						<p><?php esc_html_e( 'No submissions yet — your leads-over-time chart will appear here.', 'xtreme-forms' ); ?></p>
						<a href="<?php echo esc_url( $add_form_url ); ?>" class="button xf-btn-primary"><?php esc_html_e( 'Create Your First Form', 'xtreme-forms' ); ?></a>
					</div>
					<!-- Hidden canvas for when data arrives after first submission -->
					<canvas id="xf-line-chart" style="display:none;" aria-label="<?php esc_attr_e( 'Leads over time chart', 'xtreme-forms' ); ?>" role="img"></canvas>
					<div class="xf-chart-error" id="xf-line-chart-error" style="display:none;"></div>
					<div class="xf-chart-no-data" id="xf-line-chart-no-data" style="display:none;"></div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Leads by Form (bar chart) -->
		<div class="xf-card xf-chart-card xf-chart-card-bar">
			<div class="xf-card-header">
				<h2><?php esc_html_e( 'Leads by Form', 'xtreme-forms' ); ?></h2>
				<div class="xf-chart-controls">
					<div class="xf-range-tabs" id="xf-bar-range-tabs" role="tablist">
						<button type="button" class="xf-range-tab xf-range-tab-active" data-range="all" role="tab"><?php esc_html_e( 'All Time', 'xtreme-forms' ); ?></button>
						<button type="button" class="xf-range-tab" data-range="30d" role="tab"><?php esc_html_e( 'Last 30 Days', 'xtreme-forms' ); ?></button>
						<button type="button" class="xf-range-tab" data-range="90d" role="tab"><?php esc_html_e( 'Last 90 Days', 'xtreme-forms' ); ?></button>
						<button type="button" class="xf-range-tab" data-range="custom" role="tab"><?php esc_html_e( 'Custom', 'xtreme-forms' ); ?></button>
					</div>
					<div class="xf-custom-range" id="xf-bar-custom-range" style="display:none;">
						<label for="xf-bar-custom-from" class="screen-reader-text"><?php esc_html_e( 'Start date', 'xtreme-forms' ); ?></label>
						<input type="date" id="xf-bar-custom-from" class="xf-date-input" placeholder="<?php esc_attr_e( 'Start date', 'xtreme-forms' ); ?>">
						<span class="xf-date-sep">—</span>
						<label for="xf-bar-custom-to" class="screen-reader-text"><?php esc_html_e( 'End date', 'xtreme-forms' ); ?></label>
						<input type="date" id="xf-bar-custom-to" class="xf-date-input" placeholder="<?php esc_attr_e( 'End date', 'xtreme-forms' ); ?>">
						<button type="button" id="xf-bar-custom-range-apply" class="button button-small"><?php esc_html_e( 'Apply', 'xtreme-forms' ); ?></button>
						<span class="xf-date-error" id="xf-bar-custom-range-error" style="display:none;color:var(--xf-danger)"></span>
					</div>
				</div>
			</div>
			<div class="xf-chart-body" id="xf-bar-chart-body">
				<?php if ( $has_forms ) : ?>
					<canvas id="xf-bar-chart" aria-label="<?php esc_attr_e( 'Leads by form bar chart', 'xtreme-forms' ); ?>" role="img"></canvas>
					<div class="xf-chart-error" id="xf-bar-chart-error" style="display:none;">
						<span class="dashicons dashicons-warning"></span>
						<span class="xf-chart-error-msg"><?php esc_html_e( 'Failed to load chart data. Please try again.', 'xtreme-forms' ); ?></span>
					</div>
				<?php else : ?>
					<div class="xf-empty-state" id="xf-bar-chart-empty">
						<span class="dashicons dashicons-chart-bar xf-empty-icon"></span>
						<p><?php esc_html_e( 'No active forms yet — your leads-by-form chart will appear here.', 'xtreme-forms' ); ?></p>
						<a href="<?php echo esc_url( $add_form_url ); ?>" class="button xf-btn-primary"><?php esc_html_e( 'Create Your First Form', 'xtreme-forms' ); ?></a>
					</div>
					<!-- Hidden canvas -->
					<canvas id="xf-bar-chart" style="display:none;" aria-label="<?php esc_attr_e( 'Leads by form bar chart', 'xtreme-forms' ); ?>" role="img"></canvas>
					<div class="xf-chart-error" id="xf-bar-chart-error" style="display:none;"></div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- ── Bottom Row: Funnel + Top Pages + Top Forms ─────────────────────── -->
	<div class="xf-bottom-row">

		<!-- Status Conversion Funnel -->
		<div class="xf-card xf-funnel-card">
			<div class="xf-card-header">
				<h2><?php esc_html_e( 'Conversion Funnel', 'xtreme-forms' ); ?></h2>
			</div>
			<div class="xf-card-body">
				<?php if ( $total_leads > 0 ) : ?>
					<table class="xf-funnel-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Status', 'xtreme-forms' ); ?></th>
								<th><?php esc_html_e( 'Leads', 'xtreme-forms' ); ?></th>
								<th><?php esc_html_e( '% of Total', 'xtreme-forms' ); ?></th>
								<th><?php esc_html_e( 'Bar', 'xtreme-forms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $funnel_data as $item ) : ?>
								<tr>
									<td>
										<span class="xf-status-badge xf-status-<?php echo esc_attr( $item['status'] ); ?>">
											<?php echo esc_html( $item['label'] ); ?>
										</span>
									</td>
									<td class="xf-funnel-count"><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></td>
									<td class="xf-funnel-pct"><?php echo esc_html( number_format( $item['percentage'], 2 ) ); ?>%</td>
									<td class="xf-funnel-bar-cell">
										<div class="xf-funnel-bar-track">
											<div class="xf-funnel-bar xf-funnel-bar-<?php echo esc_attr( $item['status'] ); ?>"
												style="width:<?php echo esc_attr( min( 100, $item['percentage'] ) ); ?>%"></div>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<!-- Always show funnel with all statuses at 0% even with no data -->
					<div class="xf-empty-state-inline">
						<p><?php esc_html_e( 'Your conversion funnel will appear here once you capture leads.', 'xtreme-forms' ); ?></p>
						<a href="<?php echo esc_url( $add_form_url ); ?>"><?php esc_html_e( 'Create a lead capture form', 'xtreme-forms' ); ?></a>
					</div>
					<table class="xf-funnel-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Status', 'xtreme-forms' ); ?></th>
								<th><?php esc_html_e( 'Leads', 'xtreme-forms' ); ?></th>
								<th><?php esc_html_e( '% of Total', 'xtreme-forms' ); ?></th>
								<th><?php esc_html_e( 'Bar', 'xtreme-forms' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $funnel_data as $item ) : ?>
								<tr>
									<td>
										<span class="xf-status-badge xf-status-<?php echo esc_attr( $item['status'] ); ?>">
											<?php echo esc_html( $item['label'] ); ?>
										</span>
									</td>
									<td class="xf-funnel-count">0</td>
									<td class="xf-funnel-pct">0.00%</td>
									<td class="xf-funnel-bar-cell">
										<div class="xf-funnel-bar-track">
											<div class="xf-funnel-bar xf-funnel-bar-<?php echo esc_attr( $item['status'] ); ?>" style="width:0%"></div>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<!-- Top Source Pages -->
		<div class="xf-card xf-top-pages-card">
			<div class="xf-card-header">
				<h2><?php esc_html_e( 'Top Source Pages', 'xtreme-forms' ); ?></h2>
			</div>
			<div class="xf-card-body">
				<?php if ( ! empty( $top_pages ) ) : ?>
					<ol class="xf-top-list">
						<?php foreach ( $top_pages as $page_item ) : ?>
							<li class="xf-top-list-item">
								<span class="xf-top-list-url" title="<?php echo esc_attr( $page_item['source_url'] ); ?>">
									<?php echo esc_html( wp_parse_url( $page_item['source_url'], PHP_URL_PATH ) ?: $page_item['source_url'] ); ?>
								</span>
								<span class="xf-badge xf-badge-count"><?php echo esc_html( number_format_i18n( $page_item['count'] ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php else : ?>
					<div class="xf-empty-state-inline">
						<span class="dashicons dashicons-admin-links xf-empty-icon-sm"></span>
						<p><?php esc_html_e( 'Top source pages will appear here once leads are captured.', 'xtreme-forms' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Top Performing Forms -->
		<div class="xf-card xf-top-forms-card">
			<div class="xf-card-header">
				<h2><?php esc_html_e( 'Top Performing Forms', 'xtreme-forms' ); ?></h2>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-form-metrics' ), admin_url( 'admin.php' ) ) ); ?>" class="xf-card-link"><?php esc_html_e( 'View All →', 'xtreme-forms' ); ?></a>
			</div>
			<div class="xf-card-body">
				<?php if ( ! empty( $top_forms ) ) : ?>
					<ol class="xf-top-list">
						<?php foreach ( $top_forms as $form_item ) : ?>
							<li class="xf-top-list-item">
								<span class="xf-top-list-name"><?php echo esc_html( $form_item['form_name'] ); ?></span>
								<span class="xf-badge xf-badge-count"><?php echo esc_html( number_format_i18n( $form_item['count'] ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php else : ?>
					<div class="xf-empty-state-inline">
						<span class="dashicons dashicons-feedback xf-empty-icon-sm"></span>
						<p><?php esc_html_e( 'Top-performing forms will appear here once leads are captured.', 'xtreme-forms' ); ?></p>
						<a href="<?php echo esc_url( $add_form_url ); ?>"><?php esc_html_e( 'Create your first form', 'xtreme-forms' ); ?></a>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- ── UTM Breakdown ──────────────────────────────────────────────────── -->
	<div class="xf-card xf-utm-card">
		<div class="xf-card-header">
			<h2><?php esc_html_e( 'UTM Source Breakdown', 'xtreme-forms' ); ?></h2>
		</div>
		<div class="xf-card-body xf-utm-tables-row">

			<?php
			$utm_sections = array(
				'source'   => __( 'Source (utm_source)', 'xtreme-forms' ),
				'medium'   => __( 'Medium (utm_medium)', 'xtreme-forms' ),
				'campaign' => __( 'Campaign (utm_campaign)', 'xtreme-forms' ),
			);
			foreach ( $utm_sections as $key => $label ) :
				$section = $utm_data[ $key ] ?? array(
					'rows'             => array(),
					'total_attributed' => 0,
					'has_more'         => false,
				);
				$rows    = $section['rows'] ?? array();
				$total   = (int) ( $section['total_attributed'] ?? 0 );
				?>
				<div class="xf-utm-section">
					<h3 class="xf-utm-section-title"><?php echo esc_html( $label ); ?></h3>
					<?php if ( ! empty( $rows ) ) : ?>
						<table class="xf-utm-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Value', 'xtreme-forms' ); ?></th>
									<th><?php esc_html_e( 'Leads', 'xtreme-forms' ); ?></th>
									<th><?php esc_html_e( '%', 'xtreme-forms' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $rows as $row ) : ?>
									<tr>
										<td><?php echo esc_html( $row['value'] ); ?></td>
										<td><?php echo esc_html( number_format_i18n( $row['count'] ) ); ?></td>
										<td><?php echo esc_html( number_format( $row['percentage'], 2 ) ); ?>%</td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
						<?php if ( $section['has_more'] ) : ?>
							<p class="xf-utm-view-all">
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-form-metrics' ), admin_url( 'admin.php' ) ) ); ?>">
									<?php esc_html_e( 'View all →', 'xtreme-forms' ); ?>
								</a>
							</p>
						<?php endif; ?>
					<?php else : ?>
						<div class="xf-empty-state-inline">
							<p><?php esc_html_e( 'No UTM-attributed leads yet. UTM data will appear here when leads arrive with UTM parameters.', 'xtreme-forms' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

</div><!-- .xf-dashboard-wrap -->

<script type="text/javascript">
/* Dashboard initial data — passed to xf-dashboard.js to avoid redundant AJAX on load */
window.xlDashboardInitialData = {
	hasLeads: <?php echo $total_leads > 0 ? 'true' : 'false'; ?>,
	hasForms: <?php echo $has_forms ? 'true' : 'false'; ?>,
};
</script>
