<?php
/**
 * XtremeLeads Dashboard — Overview page (Feature 4.1).
 *
 * All chart data is loaded asynchronously via AJAX after page render.
 * Empty states are displayed inline when there are no leads/forms.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification -- Read-only display page; all data rendered server-side via WP functions.

// Fetch initial KPI data server-side for instant render (no FOUC).
$kpi_all_time = XL_Analytics::count_leads_all_time();
$kpi_this_month = XL_Analytics::count_leads_this_month();
$kpi_this_week = XL_Analytics::count_leads_this_week();
$funnel_data = XL_Analytics::leads_by_status();
$top_pages = XL_Analytics::top_source_pages( 10 );
$top_forms = XL_Analytics::top_forms( 5 );
$utm_data = XL_Analytics::utm_breakdown();

$total_leads = $kpi_all_time;
$has_forms = ! empty( XL_Forms::get_all_forms() );

$add_form_url = add_query_arg( array( 'page' => 'xtremeleads-forms', 'xl_action' => 'new' ), admin_url( 'admin.php' ) );
$leads_url = add_query_arg( array( 'page' => 'xtremeleads-leads' ), admin_url( 'admin.php' ) );
?>
<div class="wrap xl-wrap xl-dashboard-wrap">
	<h1 class="xl-page-title">
		<?php esc_html_e( 'XtremeLeads Dashboard', 'xtremeleads' ); ?>
		<a href="<?php echo esc_url( $leads_url ); ?>" class="button xl-btn-secondary">
			<?php esc_html_e( 'View All Leads', 'xtremeleads' ); ?>
		</a>
	</h1>

	<!-- ── KPI Tiles ──────────────────────────────────────────────────────── -->
	<div class="xl-kpi-row">
		<div class="xl-kpi-tile">
			<div class="xl-kpi-icon"><span class="dashicons dashicons-email-alt"></span></div>
			<div class="xl-kpi-body">
				<span class="xl-kpi-label"><?php esc_html_e( 'All Time', 'xtremeleads' ); ?></span>
				<span class="xl-kpi-value" id="xl-kpi-all-time"><?php echo esc_html( number_format_i18n( $kpi_all_time ) ); ?></span>
				<span class="xl-kpi-sublabel"><?php esc_html_e( 'Total Leads', 'xtremeleads' ); ?></span>
			</div>
		</div>
		<div class="xl-kpi-tile">
			<div class="xl-kpi-icon"><span class="dashicons dashicons-calendar-alt"></span></div>
			<div class="xl-kpi-body">
				<span class="xl-kpi-label"><?php esc_html_e( 'This Month', 'xtremeleads' ); ?></span>
				<span class="xl-kpi-value" id="xl-kpi-this-month"><?php echo esc_html( number_format_i18n( $kpi_this_month ) ); ?></span>
				<span class="xl-kpi-sublabel"><?php echo esc_html( wp_date( 'F Y' ) ); ?></span>
			</div>
		</div>
		<div class="xl-kpi-tile">
			<div class="xl-kpi-icon"><span class="dashicons dashicons-clock"></span></div>
			<div class="xl-kpi-body">
				<span class="xl-kpi-label"><?php esc_html_e( 'This Week', 'xtremeleads' ); ?></span>
				<span class="xl-kpi-value" id="xl-kpi-this-week"><?php echo esc_html( number_format_i18n( $kpi_this_week ) ); ?></span>
				<span class="xl-kpi-sublabel"><?php esc_html_e( 'Since Monday', 'xtremeleads' ); ?></span>
			</div>
		</div>
	</div>

	<!-- ── Charts Row ─────────────────────────────────────────────────────── -->
	<div class="xl-charts-row">

		<!-- Leads Over Time (line chart) -->
		<div class="xl-card xl-chart-card xl-chart-card-line">
			<div class="xl-card-header">
				<h2><?php esc_html_e( 'Leads Over Time', 'xtremeleads' ); ?></h2>
				<div class="xl-chart-controls">
					<div class="xl-range-tabs" id="xl-line-range-tabs" role="tablist">
						<button type="button" class="xl-range-tab" data-range="7d" role="tab"><?php esc_html_e( 'Last 7 Days', 'xtremeleads' ); ?></button>
						<button type="button" class="xl-range-tab xl-range-tab-active" data-range="30d" role="tab"><?php esc_html_e( 'Last 30 Days', 'xtremeleads' ); ?></button>
						<button type="button" class="xl-range-tab" data-range="90d" role="tab"><?php esc_html_e( 'Last 90 Days', 'xtremeleads' ); ?></button>
						<button type="button" class="xl-range-tab" data-range="custom" role="tab"><?php esc_html_e( 'Custom', 'xtremeleads' ); ?></button>
					</div>
					<div class="xl-custom-range" id="xl-custom-range" style="display:none;">
						<label for="xl-custom-from" class="screen-reader-text"><?php esc_html_e( 'Start date', 'xtremeleads' ); ?></label>
						<input type="date" id="xl-custom-from" class="xl-date-input" placeholder="<?php esc_attr_e( 'Start date', 'xtremeleads' ); ?>">
						<span class="xl-date-sep">—</span>
						<label for="xl-custom-to" class="screen-reader-text"><?php esc_html_e( 'End date', 'xtremeleads' ); ?></label>
						<input type="date" id="xl-custom-to" class="xl-date-input" placeholder="<?php esc_attr_e( 'End date', 'xtremeleads' ); ?>">
						<button type="button" id="xl-custom-range-apply" class="button button-small"><?php esc_html_e( 'Apply', 'xtremeleads' ); ?></button>
						<span class="xl-date-error" id="xl-custom-range-error" style="display:none;color:var(--xl-danger)"></span>
					</div>
				</div>
			</div>
			<div class="xl-chart-body" id="xl-line-chart-body">
				<?php if ( $total_leads > 0 ) : ?>
					<canvas id="xl-line-chart" aria-label="<?php esc_attr_e( 'Leads over time chart', 'xtremeleads' ); ?>" role="img"></canvas>
					<div class="xl-chart-error" id="xl-line-chart-error" style="display:none;">
						<span class="dashicons dashicons-warning"></span>
						<span class="xl-chart-error-msg"><?php esc_html_e( 'Failed to load chart data. Please try again.', 'xtremeleads' ); ?></span>
					</div>
					<div class="xl-chart-no-data" id="xl-line-chart-no-data" style="display:none;">
						<span><?php esc_html_e( 'No data for this period', 'xtremeleads' ); ?></span>
					</div>
				<?php else : ?>
					<div class="xl-empty-state" id="xl-line-chart-empty">
						<span class="dashicons dashicons-chart-line xl-empty-icon"></span>
						<p><?php esc_html_e( 'No submissions yet — your leads-over-time chart will appear here.', 'xtremeleads' ); ?></p>
						<a href="<?php echo esc_url( $add_form_url ); ?>" class="button xl-btn-primary"><?php esc_html_e( 'Create Your First Form', 'xtremeleads' ); ?></a>
					</div>
					<!-- Hidden canvas for when data arrives after first submission -->
					<canvas id="xl-line-chart" style="display:none;" aria-label="<?php esc_attr_e( 'Leads over time chart', 'xtremeleads' ); ?>" role="img"></canvas>
					<div class="xl-chart-error" id="xl-line-chart-error" style="display:none;"></div>
					<div class="xl-chart-no-data" id="xl-line-chart-no-data" style="display:none;"></div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Leads by Form (bar chart) -->
		<div class="xl-card xl-chart-card xl-chart-card-bar">
			<div class="xl-card-header">
				<h2><?php esc_html_e( 'Leads by Form', 'xtremeleads' ); ?></h2>
				<div class="xl-chart-controls">
					<div class="xl-range-tabs" id="xl-bar-range-tabs" role="tablist">
						<button type="button" class="xl-range-tab xl-range-tab-active" data-range="all" role="tab"><?php esc_html_e( 'All Time', 'xtremeleads' ); ?></button>
						<button type="button" class="xl-range-tab" data-range="30d" role="tab"><?php esc_html_e( 'Last 30 Days', 'xtremeleads' ); ?></button>
						<button type="button" class="xl-range-tab" data-range="90d" role="tab"><?php esc_html_e( 'Last 90 Days', 'xtremeleads' ); ?></button>
						<button type="button" class="xl-range-tab" data-range="custom" role="tab"><?php esc_html_e( 'Custom', 'xtremeleads' ); ?></button>
					</div>
					<div class="xl-custom-range" id="xl-bar-custom-range" style="display:none;">
						<label for="xl-bar-custom-from" class="screen-reader-text"><?php esc_html_e( 'Start date', 'xtremeleads' ); ?></label>
						<input type="date" id="xl-bar-custom-from" class="xl-date-input" placeholder="<?php esc_attr_e( 'Start date', 'xtremeleads' ); ?>">
						<span class="xl-date-sep">—</span>
						<label for="xl-bar-custom-to" class="screen-reader-text"><?php esc_html_e( 'End date', 'xtremeleads' ); ?></label>
						<input type="date" id="xl-bar-custom-to" class="xl-date-input" placeholder="<?php esc_attr_e( 'End date', 'xtremeleads' ); ?>">
						<button type="button" id="xl-bar-custom-range-apply" class="button button-small"><?php esc_html_e( 'Apply', 'xtremeleads' ); ?></button>
						<span class="xl-date-error" id="xl-bar-custom-range-error" style="display:none;color:var(--xl-danger)"></span>
					</div>
				</div>
			</div>
			<div class="xl-chart-body" id="xl-bar-chart-body">
				<?php if ( $has_forms ) : ?>
					<canvas id="xl-bar-chart" aria-label="<?php esc_attr_e( 'Leads by form bar chart', 'xtremeleads' ); ?>" role="img"></canvas>
					<div class="xl-chart-error" id="xl-bar-chart-error" style="display:none;">
						<span class="dashicons dashicons-warning"></span>
						<span class="xl-chart-error-msg"><?php esc_html_e( 'Failed to load chart data. Please try again.', 'xtremeleads' ); ?></span>
					</div>
				<?php else : ?>
					<div class="xl-empty-state" id="xl-bar-chart-empty">
						<span class="dashicons dashicons-chart-bar xl-empty-icon"></span>
						<p><?php esc_html_e( 'No active forms yet — your leads-by-form chart will appear here.', 'xtremeleads' ); ?></p>
						<a href="<?php echo esc_url( $add_form_url ); ?>" class="button xl-btn-primary"><?php esc_html_e( 'Create Your First Form', 'xtremeleads' ); ?></a>
					</div>
					<!-- Hidden canvas -->
					<canvas id="xl-bar-chart" style="display:none;" aria-label="<?php esc_attr_e( 'Leads by form bar chart', 'xtremeleads' ); ?>" role="img"></canvas>
					<div class="xl-chart-error" id="xl-bar-chart-error" style="display:none;"></div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- ── Bottom Row: Funnel + Top Pages + Top Forms ─────────────────────── -->
	<div class="xl-bottom-row">

		<!-- Status Conversion Funnel -->
		<div class="xl-card xl-funnel-card">
			<div class="xl-card-header">
				<h2><?php esc_html_e( 'Conversion Funnel', 'xtremeleads' ); ?></h2>
			</div>
			<div class="xl-card-body">
				<?php if ( $total_leads > 0 ) : ?>
					<table class="xl-funnel-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Status', 'xtremeleads' ); ?></th>
								<th><?php esc_html_e( 'Leads', 'xtremeleads' ); ?></th>
								<th><?php esc_html_e( '% of Total', 'xtremeleads' ); ?></th>
								<th><?php esc_html_e( 'Bar', 'xtremeleads' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $funnel_data as $item ) : ?>
								<tr>
									<td>
										<span class="xl-status-badge xl-status-<?php echo esc_attr( $item['status'] ); ?>">
											<?php echo esc_html( $item['label'] ); ?>
										</span>
									</td>
									<td class="xl-funnel-count"><?php echo esc_html( number_format_i18n( $item['count'] ) ); ?></td>
									<td class="xl-funnel-pct"><?php echo esc_html( number_format( $item['percentage'], 2 ) ); ?>%</td>
									<td class="xl-funnel-bar-cell">
										<div class="xl-funnel-bar-track">
											<div class="xl-funnel-bar xl-funnel-bar-<?php echo esc_attr( $item['status'] ); ?>"
												style="width:<?php echo esc_attr( min( 100, $item['percentage'] ) ); ?>%"></div>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<!-- Always show funnel with all statuses at 0% even with no data -->
					<div class="xl-empty-state-inline">
						<p><?php esc_html_e( 'Your conversion funnel will appear here once you capture leads.', 'xtremeleads' ); ?></p>
						<a href="<?php echo esc_url( $add_form_url ); ?>"><?php esc_html_e( 'Create a lead capture form', 'xtremeleads' ); ?></a>
					</div>
					<table class="xl-funnel-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Status', 'xtremeleads' ); ?></th>
								<th><?php esc_html_e( 'Leads', 'xtremeleads' ); ?></th>
								<th><?php esc_html_e( '% of Total', 'xtremeleads' ); ?></th>
								<th><?php esc_html_e( 'Bar', 'xtremeleads' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $funnel_data as $item ) : ?>
								<tr>
									<td>
										<span class="xl-status-badge xl-status-<?php echo esc_attr( $item['status'] ); ?>">
											<?php echo esc_html( $item['label'] ); ?>
										</span>
									</td>
									<td class="xl-funnel-count">0</td>
									<td class="xl-funnel-pct">0.00%</td>
									<td class="xl-funnel-bar-cell">
										<div class="xl-funnel-bar-track">
											<div class="xl-funnel-bar xl-funnel-bar-<?php echo esc_attr( $item['status'] ); ?>" style="width:0%"></div>
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
		<div class="xl-card xl-top-pages-card">
			<div class="xl-card-header">
				<h2><?php esc_html_e( 'Top Source Pages', 'xtremeleads' ); ?></h2>
			</div>
			<div class="xl-card-body">
				<?php if ( ! empty( $top_pages ) ) : ?>
					<ol class="xl-top-list">
						<?php foreach ( $top_pages as $page_item ) : ?>
							<li class="xl-top-list-item">
								<span class="xl-top-list-url" title="<?php echo esc_attr( $page_item['source_url'] ); ?>">
									<?php echo esc_html( wp_parse_url( $page_item['source_url'], PHP_URL_PATH ) ?: $page_item['source_url'] ); ?>
								</span>
								<span class="xl-badge xl-badge-count"><?php echo esc_html( number_format_i18n( $page_item['count'] ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php else : ?>
					<div class="xl-empty-state-inline">
						<span class="dashicons dashicons-admin-links xl-empty-icon-sm"></span>
						<p><?php esc_html_e( 'Top source pages will appear here once leads are captured.', 'xtremeleads' ); ?></p>
					</div>
				<?php endif; ?>
			</div>
		</div>

		<!-- Top Performing Forms -->
		<div class="xl-card xl-top-forms-card">
			<div class="xl-card-header">
				<h2><?php esc_html_e( 'Top Performing Forms', 'xtremeleads' ); ?></h2>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-form-metrics' ), admin_url( 'admin.php' ) ) ); ?>" class="xl-card-link"><?php esc_html_e( 'View All →', 'xtremeleads' ); ?></a>
			</div>
			<div class="xl-card-body">
				<?php if ( ! empty( $top_forms ) ) : ?>
					<ol class="xl-top-list">
						<?php foreach ( $top_forms as $form_item ) : ?>
							<li class="xl-top-list-item">
								<span class="xl-top-list-name"><?php echo esc_html( $form_item['form_name'] ); ?></span>
								<span class="xl-badge xl-badge-count"><?php echo esc_html( number_format_i18n( $form_item['count'] ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ol>
				<?php else : ?>
					<div class="xl-empty-state-inline">
						<span class="dashicons dashicons-feedback xl-empty-icon-sm"></span>
						<p><?php esc_html_e( 'Top-performing forms will appear here once leads are captured.', 'xtremeleads' ); ?></p>
						<a href="<?php echo esc_url( $add_form_url ); ?>"><?php esc_html_e( 'Create your first form', 'xtremeleads' ); ?></a>
					</div>
				<?php endif; ?>
			</div>
		</div>
	</div>

	<!-- ── UTM Breakdown ──────────────────────────────────────────────────── -->
	<div class="xl-card xl-utm-card">
		<div class="xl-card-header">
			<h2><?php esc_html_e( 'UTM Source Breakdown', 'xtremeleads' ); ?></h2>
		</div>
		<div class="xl-card-body xl-utm-tables-row">

			<?php
			$utm_sections = array(
				'source' => __( 'Source (utm_source)', 'xtremeleads' ),
				'medium' => __( 'Medium (utm_medium)', 'xtremeleads' ),
				'campaign' => __( 'Campaign (utm_campaign)', 'xtremeleads' ),
			);
			foreach ( $utm_sections as $key => $label ) :
				$section = $utm_data[ $key ] ?? array( 'rows' => array(), 'total_attributed' => 0, 'has_more' => false );
				$rows = $section['rows'] ?? array();
				$total = (int) ( $section['total_attributed'] ?? 0 );
				?>
				<div class="xl-utm-section">
					<h3 class="xl-utm-section-title"><?php echo esc_html( $label ); ?></h3>
					<?php if ( ! empty( $rows ) ) : ?>
						<table class="xl-utm-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Value', 'xtremeleads' ); ?></th>
									<th><?php esc_html_e( 'Leads', 'xtremeleads' ); ?></th>
									<th><?php esc_html_e( '%', 'xtremeleads' ); ?></th>
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
							<p class="xl-utm-view-all">
								<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-form-metrics' ), admin_url( 'admin.php' ) ) ); ?>">
									<?php esc_html_e( 'View all →', 'xtremeleads' ); ?>
								</a>
							</p>
						<?php endif; ?>
					<?php else : ?>
						<div class="xl-empty-state-inline">
							<p><?php esc_html_e( 'No UTM-attributed leads yet. UTM data will appear here when leads arrive with UTM parameters.', 'xtremeleads' ); ?></p>
						</div>
					<?php endif; ?>
				</div>
			<?php endforeach; ?>
		</div>
	</div>

</div><!-- .xl-dashboard-wrap -->

<script type="text/javascript">
/* Dashboard initial data — passed to xl-dashboard.js to avoid redundant AJAX on load */
window.xlDashboardInitialData = {
	hasLeads: <?php echo $total_leads > 0 ? 'true' : 'false'; ?>,
	hasForms: <?php echo $has_forms ? 'true' : 'false'; ?>,
};
</script>
