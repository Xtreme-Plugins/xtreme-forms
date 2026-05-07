<?php
/**
 * Email Log admin page.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variables in admin partial scope.

$per_page = 25;
// Pagination is non-destructive (absint clamp), no nonce required.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current_page = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;

// Filter form GET reads — only honoured when the inline nonce verifies.
$filter_nonce_ok = isset( $_GET['_xf_nonce'] )
	&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_xf_nonce'] ) ), 'xtremeforms_filter_email_log' );

$filters = array();
if ( $filter_nonce_ok && ! empty( $_GET['xf_trigger'] ) ) {
	$filters['trigger_type'] = sanitize_text_field( wp_unslash( $_GET['xf_trigger'] ) );
}
if ( $filter_nonce_ok && ! empty( $_GET['xf_log_status'] ) ) {
	$filters['status'] = sanitize_text_field( wp_unslash( $_GET['xf_log_status'] ) );
}

$result = XF_Email_Log::get_logs( $filters, $current_page, $per_page );
$logs   = $result['logs'];
$total  = $result['total'];
$pages  = (int) ceil( $total / $per_page );

$trigger_labels = XF_Email_Log::get_trigger_labels();

// Notice flags ('resent', 'resend_failed') come from the resend admin-post handler which
// includes a nonce in its redirect URL. Verify it before showing notices.
$notice_nonce_ok = isset( $_GET['_xf_notice_nonce'] )
	&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_xf_notice_nonce'] ) ), 'xtremeforms_email_log_notice' );

$notice = '';
if ( $notice_nonce_ok && ! empty( $_GET['resent'] ) ) {
	$notice = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Email resent successfully.', 'xtreme-forms' ) . '</p></div>';
}
if ( $notice_nonce_ok && ! empty( $_GET['resend_failed'] ) ) {
	$notice = '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Failed to resend email. Check your WordPress email configuration.', 'xtreme-forms' ) . '</p></div>';
}
?>
<div class="wrap xf-wrap">
	<div class="xf-page-header">
		<h1 class="xf-page-title"><?php esc_html_e( 'Email Log', 'xtreme-forms' ); ?></h1>
	</div>

	<?php echo wp_kses_post( $notice ); ?>

	<!-- Filters -->
	<form method="get" class="xf-filter-bar" style="margin-bottom:16px;display:flex;gap:12px;align-items:center;flex-wrap:wrap;">
		<input type="hidden" name="page" value="xtreme-forms-email-log">
		<?php wp_nonce_field( 'xtremeforms_filter_email_log', '_xf_nonce' ); ?>

		<select name="xf_trigger" style="height:32px;">
			<option value=""><?php esc_html_e( 'All Trigger Types', 'xtreme-forms' ); ?></option>
			<?php foreach ( $trigger_labels as $val => $label ) : ?>
				<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $filters['trigger_type'] ?? '', $val ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>

		<select name="xf_log_status" style="height:32px;">
			<option value=""><?php esc_html_e( 'All Statuses', 'xtreme-forms' ); ?></option>
			<option value="sent" <?php selected( $filters['status'] ?? '', 'sent' ); ?>><?php esc_html_e( 'Sent', 'xtreme-forms' ); ?></option>
			<option value="failed" <?php selected( $filters['status'] ?? '', 'failed' ); ?>><?php esc_html_e( 'Failed', 'xtreme-forms' ); ?></option>
			<option value="skipped" <?php selected( $filters['status'] ?? '', 'skipped' ); ?>><?php esc_html_e( 'Skipped', 'xtreme-forms' ); ?></option>
		</select>

		<?php submit_button( __( 'Filter', 'xtreme-forms' ), 'secondary', 'filter', false, array( 'style' => 'height:32px;padding:0 12px;' ) ); ?>
		<?php if ( ! empty( $filters ) ) : ?>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=xtreme-forms-email-log' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'xtreme-forms' ); ?></a>
		<?php endif; ?>

		<span style="margin-left:auto;color:#6C757D;font-size:13px;">
			<?php
			printf(
				/* translators: 1: total count */
				esc_html( _n( '%d email logged', '%d emails logged', $total, 'xtreme-forms' ) ),
				(int) $total
			);
			?>
		</span>
	</form>

	<table class="widefat xf-log-table" style="table-layout:fixed;">
		<thead>
			<tr>
				<th style="width:50px;"><?php esc_html_e( 'ID', 'xtreme-forms' ); ?></th>
				<th style="width:200px;"><?php esc_html_e( 'Recipient', 'xtreme-forms' ); ?></th>
				<th><?php esc_html_e( 'Subject', 'xtreme-forms' ); ?></th>
				<th style="width:110px;"><?php esc_html_e( 'Trigger', 'xtreme-forms' ); ?></th>
				<th style="width:70px;"><?php esc_html_e( 'Lead ID', 'xtreme-forms' ); ?></th>
				<th style="width:70px;"><?php esc_html_e( 'Status', 'xtreme-forms' ); ?></th>
				<th style="width:155px;"><?php esc_html_e( 'Sent At', 'xtreme-forms' ); ?></th>
				<th style="width:80px;"><?php esc_html_e( 'Actions', 'xtreme-forms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $logs ) ) : ?>
				<tr>
					<td colspan="8" style="text-align:center;color:#6C757D;padding:24px;">
						<?php esc_html_e( 'No email log entries found.', 'xtreme-forms' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $logs as $log ) : ?>
					<?php
					$log_id       = (int) $log->id;
					$status       = $log->status ?? 'sent';
					$status_label = ucfirst( $status );
					$status_class = 'sent' === $status ? 'xf-status-pill xf-status-new' : ( 'failed' === $status ? 'xf-status-pill xf-status-lost' : 'xf-status-pill xf-status-archived' );
					$trigger      = $log->trigger_type ?? '';
					$lead_id      = (int) $log->lead_id;
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
							<span class="xf-status-pill xf-status-contacted" style="font-size:11px;">
								<?php echo esc_html( $trigger_labels[ $trigger ] ?? ucfirst( $trigger ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $lead_id ) : ?>
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=xtremeleads&xf_action=view&lead_id=' . $lead_id ) ); ?>">
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
								<span title="<?php echo esc_attr( $log->failure_reason ); ?>" style="color:#DC3545;cursor:help;margin-left:2px;" aria-label="<?php esc_attr_e( 'Failure reason', 'xtreme-forms' ); ?>">&#9432;</span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $log->sent_at ); ?></td>
						<td>
							<button type="button"
								class="button button-small xf-resend-btn"
								data-log-id="<?php echo esc_attr( $log_id ); ?>"
								style="font-size:12px;">
								<?php esc_html_e( 'Resend', 'xtreme-forms' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $pages > 1 ) : ?>
		<div class="xf-pagination" style="margin-top:16px;text-align:right;">
			<?php
			$base_url = add_query_arg(
				array_merge(
					array( 'page' => 'xtreme-forms-email-log' ),
					array_filter(
						array(
							'xf_trigger'    => $filters['trigger_type'] ?? '',
							'xf_log_status' => $filters['status'] ?? '',
							// Carry the filter nonce on paginated URLs so filter values survive page navigation.
							'_xf_nonce'     => ! empty( $filters ) ? wp_create_nonce( 'xtremeforms_filter_email_log' ) : '',
						)
					)
				),
				admin_url( 'admin.php' )
			);

			echo wp_kses_post(
				paginate_links(
					array(
						'base'      => add_query_arg( 'paged', '%#%', $base_url ),
						'format'    => '',
						'current'   => $current_page,
						'total'     => $pages,
						'prev_text' => '&laquo;',
						'next_text' => '&raquo;',
					)
				)
			);
			?>
		</div>
	<?php endif; ?>
</div>

<?php
wp_enqueue_script(
	'xtremeforms-email-log',
	XTREMEFORMS_PLUGIN_URL . 'admin/js/xf-email-log.js',
	array( 'xtremeforms-admin' ),
	XTREMEFORMS_VERSION,
	true
);
wp_localize_script(
	'xtremeforms-email-log',
	'xtremeFormsEmailLogI18n',
	array(
		'confirmResend' => __( 'Resend this email to the original recipient?', 'xtreme-forms' ),
		'sending'       => __( 'Sending…', 'xtreme-forms' ),
		'sent'          => __( 'Sent ✓', 'xtreme-forms' ),
		'resend'        => __( 'Resend', 'xtreme-forms' ),
		'resendFailed'  => __( 'Resend failed.', 'xtreme-forms' ),
	)
);
?>
