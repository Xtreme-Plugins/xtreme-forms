<?php
/**
 * Email Log admin page.
 *
 * @package XtremeLeads
 */

defined( 'ABSPATH' ) || exit;

$per_page = 25;
$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

$filters = array();
if ( ! empty( $_GET['xl_trigger'] ) ) {
	$filters['trigger_type'] = sanitize_text_field( wp_unslash( $_GET['xl_trigger'] ) );
}
if ( ! empty( $_GET['xl_log_status'] ) ) {
	$filters['status'] = sanitize_text_field( wp_unslash( $_GET['xl_log_status'] ) );
}

$result = XL_Email_Log::get_logs( $filters, $current_page, $per_page );
$logs = $result['logs'];
$total = $result['total'];
$pages = (int) ceil( $total / $per_page );

$trigger_labels = XL_Email_Log::get_trigger_labels();

$notice = '';
if ( ! empty( $_GET['resent'] ) ) {
	$notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Email resent successfully.', 'xtremeleads' ) . '</p></div>';
}
if ( ! empty( $_GET['resend_failed'] ) ) {
	$notice = '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to resend email. Check your WordPress email configuration.', 'xtremeleads' ) . '</p></div>';
}
?>
<div class="wrap xl-wrap">
	<h1 class="xl-page-title"><?php esc_html_e( 'Email Log', 'xtremeleads' ); ?></h1>

	<?php echo wp_kses_post( $notice ); ?>

	<!-- Filters -->
	<form method="get" class="xl-filter-bar" style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
		<input type="hidden" name="page" value="xtremeleads-email-log">

		<select name="xl_trigger" style="height:32px;">
			<option value=""><?php esc_html_e( 'All Trigger Types', 'xtremeleads' ); ?></option>
			<?php foreach ( $trigger_labels as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filters['trigger_type'] ?? '', $val ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select name="xl_log_status" style="height:32px;">
			<option value=""><?php esc_html_e( 'All Statuses', 'xtremeleads' ); ?></option>
			<option value="sent" <?php selected( $filters['status'] ?? '', 'sent' ); ?>><?php esc_html_e( 'Sent', 'xtremeleads' ); ?></option>
			<option value="failed" <?php selected( $filters['status'] ?? '', 'failed' ); ?>><?php esc_html_e( 'Failed', 'xtremeleads' ); ?></option>
			<option value="skipped" <?php selected( $filters['status'] ?? '', 'skipped' ); ?>><?php esc_html_e( 'Skipped', 'xtremeleads' ); ?></option>
		</select>

		<?php submit_button( __( 'Filter', 'xtremeleads' ), 'secondary', 'filter', false, array( 'style' => 'height:32px;padding:0 12px;' ) ); ?>
		<?php if ( ! empty( $filters ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=xtremeleads-email-log' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'xtremeleads' ); ?></a>
		<?php endif; ?>

		<span style="margin-left:auto;color:#6C757D;font-size:13px;">
			<?php
			printf(
				/* translators: 1: total count */
				esc_html( _n( '%d email logged', '%d emails logged', $total, 'xtremeleads' ) ),
				(int) $total
			);
			?>
		</span>
	</form>

	<table class="widefat xl-log-table" style="table-layout:fixed;">
		<thead>
			<tr>
				<th style="width:50px;"><?php esc_html_e( 'ID', 'xtremeleads' ); ?></th>
				<th style="width:200px;"><?php esc_html_e( 'Recipient', 'xtremeleads' ); ?></th>
				<th><?php esc_html_e( 'Subject', 'xtremeleads' ); ?></th>
				<th style="width:110px;"><?php esc_html_e( 'Trigger', 'xtremeleads' ); ?></th>
				<th style="width:70px;"><?php esc_html_e( 'Lead ID', 'xtremeleads' ); ?></th>
				<th style="width:70px;"><?php esc_html_e( 'Status', 'xtremeleads' ); ?></th>
				<th style="width:155px;"><?php esc_html_e( 'Sent At', 'xtremeleads' ); ?></th>
				<th style="width:80px;"><?php esc_html_e( 'Actions', 'xtremeleads' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr>
					<td colspan="8" style="text-align:center;color:#6C757D;padding:24px;">
						<?php esc_html_e( 'No email log entries found.', 'xtremeleads' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : ?>
					<?php
					$log_id = (int) $log->id;
					$status = $log->status ?? 'sent';
					$status_label = ucfirst( $status );
					$status_class = 'sent' === $status ? 'xl-status-pill xl-status-new' : ( 'failed' === $status ? 'xl-status-pill xl-status-lost' : 'xl-status-pill xl-status-archived' );
					$trigger = $log->trigger_type ?? '';
					$lead_id = (int) $log->lead_id;
					?>
					<tr>
						<td><?php echo esc_html( $log_id ); ?></td>
						<td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $log->recipient ); ?>">
							<?php echo esc_html( $log->recipient ); ?>
						</td>
						<td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( $log->subject ); ?>">
							<?php echo esc_html( $log->subject ); ?>
						</td>
						<td>
							<span class="xl-status-pill xl-status-contacted" style="font-size:11px;">
								<?php echo esc_html( $trigger_labels[ $trigger ] ?? ucfirst( $trigger ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $lead_id ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=xtremeleads&xl_action=view&lead_id=' . $lead_id ) ); ?>">
									#<?php echo esc_html( $lead_id ); ?>
								</a>
							<?php else : ?>
								<span style="color:#6C757D;">—</span>
							<?php endif; ?>
						</td>
						<td>
							<span class="<?php echo esc_attr( $status_class ); ?>" style="font-size:11px;">
								<?php echo esc_html( $status_label ); ?>
							</span>
							<?php if ( ! empty( $log->failure_reason ) && 'sent' !== $status ) : ?>
								<span title="<?php echo esc_attr( $log->failure_reason ); ?>" style="color:#DC3545;cursor:help;margin-left:2px;" aria-label="<?php esc_attr_e( 'Failure reason', 'xtremeleads' ); ?>">&#9432;</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $log->sent_at ); ?></td>
						<td>
							<button type="button"
								class="button button-small xl-resend-btn"
								data-log-id="<?php echo esc_attr( $log_id ); ?>"
								style="font-size:12px;">
								<?php esc_html_e( 'Resend', 'xtremeleads' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $pages > 1 ) : ?>
		<div class="xl-pagination" style="margin-top:16px;text-align:right;">
			<?php
			$base_url = add_query_arg(
				array_merge(
					array( 'page' => 'xtremeleads-email-log' ),
					array_filter( array(
						'xl_trigger' => $filters['trigger_type'] ?? '',
						'xl_log_status' => $filters['status'] ?? '',
					) )
				),
				admin_url( 'admin.php' )
			);

			echo wp_kses_post( paginate_links( array(
				'base' => add_query_arg( 'paged', '%#%', $base_url ),
				'format' => '',
				'current' => $current_page,
				'total' => $pages,
				'prev_text' => '&laquo;',
				'next_text' => '&raquo;',
			) ) );
			?>
		</div>
	<?php endif; ?>
</div>

<script>
(function () {
	'use strict';

	document.querySelectorAll('.xl-resend-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirm('<?php echo esc_js( __( 'Resend this email to the original recipient?', 'xtremeleads' ) ); ?>')) {
				return;
			}

			btn.disabled = true;
			btn.textContent = '<?php echo esc_js( __( 'Sending…', 'xtremeleads' ) ); ?>';

			const fd = new FormData();
			fd.append('action', 'xl_resend_email');
			fd.append('nonce', xlAdminData.nonce);
			fd.append('log_id', btn.dataset.logId);

			fetch(xlAdminData.ajaxUrl, { method: 'POST', body: fd })
				.then(function (r) { return r.json(); })
				.then(function (res) {
					if (res.success) {
						btn.textContent = '<?php echo esc_js( __( 'Sent ✓', 'xtremeleads' ) ); ?>';
						btn.style.color = '#28A745';
					} else {
						btn.disabled = false;
						btn.textContent = '<?php echo esc_js( __( 'Resend', 'xtremeleads' ) ); ?>';
						alert((res.data && res.data.message) || '<?php echo esc_js( __( 'Resend failed.', 'xtremeleads' ) ); ?>');
					}
				})
				.catch(function () {
					btn.disabled = false;
					btn.textContent = '<?php echo esc_js( __( 'Resend', 'xtremeleads' ) ); ?>';
				});
		});
	});
}());
</script>
