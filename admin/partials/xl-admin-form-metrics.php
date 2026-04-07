<?php
/**
 * Form Performance Metrics page (Feature 4.2).
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification -- GET parameters on this admin display page are read-only filter params.

$metrics = XL_Analytics::form_performance_metrics();
$forms = XL_Forms::get_all_forms();
?>
<div class="wrap xl-wrap">
	<h1 class="xl-page-title">
		<?php esc_html_e( 'Form Performance Metrics', 'xtremeleads' ); ?>
	</h1>

	<?php if ( empty( $forms ) ) : ?>
		<div class="xl-card">
			<div class="xl-empty-state">
				<span class="dashicons dashicons-feedback xl-empty-icon"></span>
				<p><?php esc_html_e( 'No forms created yet. Create a form to start tracking performance metrics.', 'xtremeleads' ); ?></p>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-forms', 'xl_action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>"
				 class="button xl-btn-primary">
					<?php esc_html_e( 'Create Your First Form', 'xtremeleads' ); ?>
				</a>
			</div>
		</div>
	<?php else : ?>

	<!-- Metrics Comparison Table -->
	<div class="xl-card">
		<div class="xl-card-header">
			<h2><?php esc_html_e( 'Form Comparison', 'xtremeleads' ); ?></h2>
			<p class="xl-card-description">
				<?php esc_html_e( 'Compare performance across all forms. Click column headers to sort. Default sort: Submissions (highest first).', 'xtremeleads' ); ?>
			</p>
		</div>
		<div class="xl-card-body xl-form-metrics-table-wrap">
			<?php
			// Pagination: 25 rows per page.
			$per_page = 25;
			$total_forms = count( $metrics );
			$total_pages = max( 1, (int) ceil( $total_forms / $per_page ) );
			$current_page = isset( $_GET['metrics_page'] ) ? max( 1, absint( $_GET['metrics_page'] ) ) : 1;
			$offset = ( $current_page - 1 ) * $per_page;
			$page_metrics = array_slice( $metrics, $offset, $per_page );
			?>
			<table class="xl-table xl-metrics-table" id="xl-metrics-comparison-table">
				<thead>
					<tr>
						<th class="xl-col-form-name xl-sortable xl-sort-asc" data-col="form_name">
							<?php esc_html_e( 'Form', 'xtremeleads' ); ?>
							<span class="xl-sort-arrow">↑</span>
						</th>
						<th class="xl-col-views xl-sortable" data-col="views">
							<?php esc_html_e( 'Views', 'xtremeleads' ); ?>
							<span class="xl-sort-arrow"></span>
						</th>
						<th class="xl-col-submissions xl-sortable xl-col-sort-active xl-sort-desc" data-col="submissions">
							<?php esc_html_e( 'Submissions', 'xtremeleads' ); ?>
							<span class="xl-sort-arrow">↓</span>
						</th>
						<th class="xl-col-conversion xl-sortable" data-col="conversion_rate">
							<?php esc_html_e( 'Conversion Rate', 'xtremeleads' ); ?>
							<span class="xl-sort-arrow"></span>
						</th>
						<th class="xl-col-avg-time xl-sortable" data-col="avg_seconds">
							<?php esc_html_e( 'Avg. Time-to-Submit', 'xtremeleads' ); ?>
							<span class="xl-sort-arrow"></span>
						</th>
					</tr>
				</thead>
				<tbody id="xl-metrics-tbody">
					<?php foreach ( $page_metrics as $m ) : ?>
						<?php
						// Format conversion rate.
						if ( null === $m['conversion_rate'] ) {
							$rate_display = '—';
							$rate_warning = false;
						} else {
							$rate_display = number_format( $m['conversion_rate'], 2 ) . '%';
							$rate_warning = $m['conversion_rate_warning'] ?? false;
						}

						// Format avg time.
						if ( null === $m['avg_seconds'] ) {
							$avg_display = '—';
						} elseif ( $m['avg_seconds'] >= 60 ) {
							$mins = floor( $m['avg_seconds'] / 60 );
							$secs = round( $m['avg_seconds'] % 60 );
							$avg_display = sprintf( '%dm %ds', $mins, $secs );
						} else {
							$avg_display = round( $m['avg_seconds'] ) . 's';
						}
						?>
						<tr data-form-id="<?php echo esc_attr( $m['form_id'] ); ?>"
							data-views="<?php echo esc_attr( $m['views'] ); ?>"
							data-submissions="<?php echo esc_attr( $m['submissions'] ); ?>"
							data-conversion="<?php echo esc_attr( $m['conversion_rate'] ?? '' ); ?>"
							data-avg="<?php echo esc_attr( $m['avg_seconds'] ?? '' ); ?>">
							<td class="xl-col-form-name">
								<strong><?php echo esc_html( $m['form_name'] ); ?></strong>
								<div class="xl-form-meta">
									<code><?php echo esc_html( sprintf( '[xtremeleads id="%d"]', $m['form_id'] ) ); ?></code>
								</div>
							</td>
							<td class="xl-col-views"><?php echo esc_html( number_format_i18n( $m['views'] ) ); ?></td>
							<td class="xl-col-submissions"><?php echo esc_html( number_format_i18n( $m['submissions'] ) ); ?></td>
							<td class="xl-col-conversion">
								<?php echo esc_html( $rate_display ); ?>
								<?php if ( $rate_warning ) : ?>
									<span class="xl-warning-icon dashicons dashicons-warning"
										 title="<?php esc_attr_e( 'Submission count exceeds recorded impressions — impression data may be incomplete', 'xtremeleads' ); ?>"
										 aria-label="<?php esc_attr_e( 'Warning: submission count exceeds recorded impressions', 'xtremeleads' ); ?>">
									</span>
								<?php endif; ?>
							</td>
							<td class="xl-col-avg-time"><?php echo esc_html( $avg_display ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $page_metrics ) ) : ?>
						<tr class="xl-no-data-row">
							<td colspan="5">
								<?php esc_html_e( 'No forms found.', 'xtremeleads' ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

				<?php if ( $total_pages > 1 ) : ?>
				<!-- Server-side pagination fallback (hidden when JS is active). -->
				<div class="xl-metrics-pagination xl-pagination" id="xl-metrics-pagination-server">
					<?php
					$base_url = add_query_arg( array( 'page' => 'xtremeleads-form-metrics' ), admin_url( 'admin.php' ) );
					$page_links = paginate_links(
						array(
							'base' => add_query_arg( 'metrics_page', '%#%', $base_url ),
							'format' => '',
							'prev_text' => '&laquo; ' . __( 'Previous', 'xtremeleads' ),
							'next_text' => __( 'Next', 'xtremeleads' ) . ' &raquo;',
							'total' => $total_pages,
							'current' => $current_page,
						)
					);
					echo wp_kses_post( $page_links );
					?>
				</div>
			<?php endif; ?>

			<!--
				Client-side pagination container — populated and managed by xl-dashboard.js.
				This is always present so JS can always find and update pagination controls,
				even on page 1 (where server-side pagination may not render).
			-->
			<div id="xl-metrics-pagination"
				 class="xl-metrics-pagination xl-pagination xl-pagination-js"
				 style="display:none;"
				 aria-label="<?php esc_attr_e( 'Form metrics table page navigation', 'xtremeleads' ); ?>">
			</div>
		</div>
	</div>

	<?php endif; ?>
</div>

<?php
// Pass all metrics data to JS for client-side sorting (includes all pages).
$all_metrics_json = wp_json_encode(
	array_map(
		static function ( $m ) {
			return array(
				'form_id' => $m['form_id'],
				'form_name' => $m['form_name'],
				'views' => $m['views'],
				'submissions' => $m['submissions'],
				'conversion_rate' => $m['conversion_rate'],
				'conversion_rate_warning' => $m['conversion_rate_warning'] ?? false,
				'avg_seconds' => $m['avg_seconds'],
			);
		},
		$metrics
	)
);
?>
<script type="text/javascript">
window.xlFormMetricsData = <?php echo $all_metrics_json; // phpcs:ignore WordPress.Security.EscapeOutput ?>;
</script>
