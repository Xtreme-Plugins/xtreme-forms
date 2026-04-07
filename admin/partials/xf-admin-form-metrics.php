<?php
/**
 * Form Performance Metrics page (Feature 4.2).
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification -- GET parameters on this admin display page are read-only filter params.

$metrics = XF_Analytics::form_performance_metrics();
$forms = XF_Forms::get_all_forms();
?>
<div class="wrap xf-wrap">
	<h1 class="xf-page-title">
		<?php esc_html_e( 'Form Performance Metrics', 'xtreme-forms' ); ?>
	</h1>

	<?php if ( empty( $forms ) ) : ?>
		<div class="xf-card">
			<div class="xf-empty-state">
				<span class="dashicons dashicons-feedback xf-empty-icon"></span>
				<p><?php esc_html_e( 'No forms created yet. Create a form to start tracking performance metrics.', 'xtreme-forms' ); ?></p>
				<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'xtremeleads-forms', 'xf_action' => 'new' ), admin_url( 'admin.php' ) ) ); ?>"
				 class="button xf-btn-primary">
					<?php esc_html_e( 'Create Your First Form', 'xtreme-forms' ); ?>
				</a>
			</div>
		</div>
	<?php else : ?>

	<!-- Metrics Comparison Table -->
	<div class="xf-card">
		<div class="xf-card-header">
			<h2><?php esc_html_e( 'Form Comparison', 'xtreme-forms' ); ?></h2>
			<p class="xf-card-description">
				<?php esc_html_e( 'Compare performance across all forms. Click column headers to sort. Default sort: Submissions (highest first).', 'xtreme-forms' ); ?>
			</p>
		</div>
		<div class="xf-card-body xf-form-metrics-table-wrap">
			<?php
			// Pagination: 25 rows per page.
			$per_page = 25;
			$total_forms = count( $metrics );
			$total_pages = max( 1, (int) ceil( $total_forms / $per_page ) );
			$current_page = isset( $_GET['metrics_page'] ) ? max( 1, absint( $_GET['metrics_page'] ) ) : 1;
			$offset = ( $current_page - 1 ) * $per_page;
			$page_metrics = array_slice( $metrics, $offset, $per_page );
			?>
			<table class="xf-table xf-metrics-table" id="xf-metrics-comparison-table">
				<thead>
					<tr>
						<th class="xf-col-form-name xf-sortable xf-sort-asc" data-col="form_name">
							<?php esc_html_e( 'Form', 'xtreme-forms' ); ?>
							<span class="xf-sort-arrow">↑</span>
						</th>
						<th class="xf-col-views xf-sortable" data-col="views">
							<?php esc_html_e( 'Views', 'xtreme-forms' ); ?>
							<span class="xf-sort-arrow"></span>
						</th>
						<th class="xf-col-submissions xf-sortable xf-col-sort-active xf-sort-desc" data-col="submissions">
							<?php esc_html_e( 'Submissions', 'xtreme-forms' ); ?>
							<span class="xf-sort-arrow">↓</span>
						</th>
						<th class="xf-col-conversion xf-sortable" data-col="conversion_rate">
							<?php esc_html_e( 'Conversion Rate', 'xtreme-forms' ); ?>
							<span class="xf-sort-arrow"></span>
						</th>
						<th class="xf-col-avg-time xf-sortable" data-col="avg_seconds">
							<?php esc_html_e( 'Avg. Time-to-Submit', 'xtreme-forms' ); ?>
							<span class="xf-sort-arrow"></span>
						</th>
					</tr>
				</thead>
				<tbody id="xf-metrics-tbody">
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
							<td class="xf-col-form-name">
								<strong><?php echo esc_html( $m['form_name'] ); ?></strong>
								<div class="xf-form-meta">
									<code><?php echo esc_html( sprintf( '[xtremeleads id="%d"]', $m['form_id'] ) ); ?></code>
								</div>
							</td>
							<td class="xf-col-views"><?php echo esc_html( number_format_i18n( $m['views'] ) ); ?></td>
							<td class="xf-col-submissions"><?php echo esc_html( number_format_i18n( $m['submissions'] ) ); ?></td>
							<td class="xf-col-conversion">
								<?php echo esc_html( $rate_display ); ?>
								<?php if ( $rate_warning ) : ?>
									<span class="xf-warning-icon dashicons dashicons-warning"
										 title="<?php esc_attr_e( 'Submission count exceeds recorded impressions — impression data may be incomplete', 'xtreme-forms' ); ?>"
										 aria-label="<?php esc_attr_e( 'Warning: submission count exceeds recorded impressions', 'xtreme-forms' ); ?>">
									</span>
								<?php endif; ?>
							</td>
							<td class="xf-col-avg-time"><?php echo esc_html( $avg_display ); ?></td>
						</tr>
					<?php endforeach; ?>
					<?php if ( empty( $page_metrics ) ) : ?>
						<tr class="xf-no-data-row">
							<td colspan="5">
								<?php esc_html_e( 'No forms found.', 'xtreme-forms' ); ?>
							</td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>

				<?php if ( $total_pages > 1 ) : ?>
				<!-- Server-side pagination fallback (hidden when JS is active). -->
				<div class="xf-metrics-pagination xf-pagination" id="xf-metrics-pagination-server">
					<?php
					$base_url = add_query_arg( array( 'page' => 'xtremeleads-form-metrics' ), admin_url( 'admin.php' ) );
					$page_links = paginate_links(
						array(
							'base' => add_query_arg( 'metrics_page', '%#%', $base_url ),
							'format' => '',
							'prev_text' => '&laquo; ' . __( 'Previous', 'xtreme-forms' ),
							'next_text' => __( 'Next', 'xtreme-forms' ) . ' &raquo;',
							'total' => $total_pages,
							'current' => $current_page,
						)
					);
					echo wp_kses_post( $page_links );
					?>
				</div>
			<?php endif; ?>

			<!--
				Client-side pagination container — populated and managed by xf-dashboard.js.
				This is always present so JS can always find and update pagination controls,
				even on page 1 (where server-side pagination may not render).
			-->
			<div id="xf-metrics-pagination"
				 class="xf-metrics-pagination xf-pagination xf-pagination-js"
				 style="display:none;"
				 aria-label="<?php esc_attr_e( 'Form metrics table page navigation', 'xtreme-forms' ); ?>">
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
