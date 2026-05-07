<?php
/**
 * Spam Log admin page.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Local variables in admin partial scope.

$nonce     = wp_create_nonce( 'xtremeforms_spam_log_nonce' );
$all_forms = XF_Forms::get_all_forms();
$reasons   = XF_Spam::get_reason_labels();

// Filter form GET reads — only honoured when the inline nonce verifies.
// When the user follows a normal admin link (no nonce yet), filters default to off.
$filter_nonce_ok = isset( $_GET['_xf_nonce'] )
	&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_xf_nonce'] ) ), 'xtremeforms_filter_spam_log' );

$filter_reason = ( $filter_nonce_ok && isset( $_GET['rejection_reason'] ) )
	? sanitize_key( wp_unslash( $_GET['rejection_reason'] ) )
	: '';

$filter_form = ( $filter_nonce_ok && isset( $_GET['filter_form'] ) )
	? absint( $_GET['filter_form'] )
	: 0;

// Pagination is non-destructive and self-validating (absint), so it does not require nonce verification.
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$current_page  = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

$log_data = XF_Spam::get_log(
	array(
		'page'             => $current_page,
		'rejection_reason' => $filter_reason,
		'form_id'          => $filter_form,
	)
);

$items = $log_data['items'];
$total = $log_data['total'];
$pages = $log_data['pages'];
?>
<div class="wrap xf-wrap" id="xf-spam-log-page">
	<div class="xf-page-header">
		<h1 class="xf-page-title"><?php esc_html_e( 'Spam Log', 'xtreme-forms' ); ?></h1>
	</div>

	<div class="xf-card" style="margin-bottom:16px;">
		<p><?php esc_html_e( 'Submissions blocked by spam protection are recorded here. Spam log entries do not contain full submission payloads — only metadata and the rejection reason.', 'xtreme-forms' ); ?></p>
	</div>

	<!-- Filters -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom:16px;">
		<input type="hidden" name="page" value="xtreme-forms-spam-log">
		<?php wp_nonce_field( 'xtremeforms_filter_spam_log', '_xf_nonce' ); ?>
		<select name="rejection_reason" style="margin-right:8px;">
			<option value=""><?php esc_html_e( 'All Reasons', 'xtreme-forms' ); ?></option>
			<?php foreach ( $reasons as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $filter_reason, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<select name="filter_form" style="margin-right:8px;">
			<option value=""><?php esc_html_e( 'All Forms', 'xtreme-forms' ); ?></option>
			<?php foreach ( $all_forms as $form ) : ?>
				<option value="<?php echo esc_attr( $form->id ); ?>" <?php selected( $filter_form, $form->id ); ?>>
					<?php echo esc_html( $form->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php submit_button( __( 'Filter', 'xtreme-forms' ), 'secondary', 'submit', false ); ?>
	</form>

	<!-- Bulk clear -->
	<div style="margin-bottom:16px;">
		<button type="button" id="xf-spam-clear-all-btn" class="button button-link-delete">
			<?php esc_html_e( 'Clear Entire Spam Log', 'xtreme-forms' ); ?>
		</button>
		<span id="xf-spam-clear-msg" style="margin-left:12px;"></span>
	</div>

	<?php if ( empty( $items ) ) : ?>
		<div class="xf-card">
			<p><?php esc_html_e( 'No spam submissions recorded.', 'xtreme-forms' ); ?></p>
		</div>
	<?php else : ?>
		<div class="xf-card">
			<p style="margin-bottom:12px;">
				<?php
				/* translators: %d: total count */
				printf( esc_html( _n( '%d blocked submission recorded.', '%d blocked submissions recorded.', $total, 'xtreme-forms' ) ), esc_html( number_format_i18n( $total ) ) );
				?>
			</p>
			<table class="wp-list-table widefat striped" id="xf-spam-log-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Date', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Form', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Rejection Reason', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Email', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Source URL', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'xtreme-forms' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $items as $item ) :
					$form_name = '';
					foreach ( $all_forms as $f ) {
						if ( (int) $f->id === (int) $item->form_id ) {
							$form_name = $f->name;
							break;
						}
					}
					if ( '' === $form_name ) {
						/* translators: %d: form ID */
						$form_name = sprintf( __( 'Form #%d', 'xtreme-forms' ), $item->form_id );
					}
					$reason_label = $reasons[ $item->rejection_reason ] ?? ucfirst( str_replace( '_', ' ', $item->rejection_reason ) );
					?>
					<tr data-entry-id="<?php echo esc_attr( $item->id ); ?>">
						<td><?php echo esc_html( $item->created_at ); ?></td>
						<td><?php echo esc_html( $form_name ); ?></td>
						<td>
							<span class="xf-badge 
							<?php
							switch ( $item->rejection_reason ) {
								case 'honeypot':
										echo 'xf-badge-secondary';
									break;
								case 'time_gate':
										echo 'xf-badge-warning';
									break;
								case 'recaptcha':
										echo 'xf-badge-info';
									break;
								case 'blocklist':
										echo 'xf-badge-danger';
									break;
								default:
										echo 'xf-badge-secondary';
							}
							?>
							">
								<?php echo esc_html( $reason_label ); ?>
							</span>
						</td>
						<td><?php echo esc_html( $item->submitted_email ); ?></td>
						<td><a href="<?php echo esc_url( $item->source_url ); ?>" target="_blank" rel="noopener noreferrer" style="word-break:break-all;"><?php echo esc_html( $item->source_url ); ?></a></td>
						<td>
							<button type="button" class="button button-link-delete xf-spam-delete-entry"
								data-id="<?php echo esc_attr( $item->id ); ?>">
								<?php esc_html_e( 'Delete', 'xtreme-forms' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>

			<!-- Pagination -->
			<?php if ( $pages > 1 ) : ?>
				<div style="margin-top:16px;">
					<?php
					for ( $p = 1; $p <= $pages; $p++ ) {
						$url = add_query_arg(
							array(
								'page'             => 'xtreme-forms-spam-log',
								'paged'            => $p,
								'rejection_reason' => $filter_reason,
								'filter_form'      => $filter_form,
								'_xf_nonce'        => wp_create_nonce( 'xtremeforms_filter_spam_log' ),
							),
							admin_url( 'admin.php' )
						);
						printf(
							'<a href="%s" class="button%s" style="margin-right:4px;">%d</a>',
							esc_url( $url ),
							( $p === $current_page ? ' button-primary' : '' ),
							esc_html( $p )
						);
					}
					?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>
</div>

<?php
wp_enqueue_script(
	'xtremeforms-spam-log',
	XTREMEFORMS_PLUGIN_URL . 'admin/js/xf-spam-log.js',
	array( 'xtremeforms-admin' ),
	XTREMEFORMS_VERSION,
	true
);
wp_localize_script(
	'xtremeforms-spam-log',
	'xtremeFormsSpamLogData',
	array(
		'nonce'   => $nonce,
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	)
);
wp_localize_script(
	'xtremeforms-spam-log',
	'xtremeFormsSpamLogI18n',
	array(
		'confirmDeleteEntry' => __( 'Permanently delete this spam log entry?', 'xtreme-forms' ),
		'deleteFailed'       => __( 'Delete failed.', 'xtreme-forms' ),
		'confirmClearAll'    => __( 'Permanently clear the entire spam log? This cannot be undone.', 'xtreme-forms' ),
		'clearing'           => __( 'Clearing…', 'xtreme-forms' ),
		'cleared'            => __( 'Spam log cleared.', 'xtreme-forms' ),
		'clearFailed'        => __( 'Clear failed.', 'xtreme-forms' ),
	)
);
?>
