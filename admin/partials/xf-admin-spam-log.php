<?php
/**
 * Spam Log admin page.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification -- GET parameters on this admin display page are read-only filter params.

$nonce = wp_create_nonce( 'xf_spam_log_nonce' );
$all_forms = XF_Forms::get_all_forms();
$reasons = XF_Spam::get_reason_labels();

// Current filter state from GET.
$filter_reason = isset( $_GET['rejection_reason'] ) ? sanitize_key( wp_unslash( $_GET['rejection_reason'] ) ) : '';
$filter_form = isset( $_GET['filter_form'] ) ? absint( $_GET['filter_form'] ) : 0;
$current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;

$log_data = XF_Spam::get_log( array(
	'page' => $current_page,
	'rejection_reason' => $filter_reason,
	'form_id' => $filter_form,
) );

$items = $log_data['items'];
$total = $log_data['total'];
$pages = $log_data['pages'];
?>
<div class="wrap xf-wrap" id="xf-spam-log-page">
	<h1 class="xf-page-title"><?php esc_html_e( 'Spam Log', 'xtreme-forms' ); ?></h1>

	<div class="xf-card" style="margin-bottom:16px;">
		<p><?php esc_html_e( 'Submissions blocked by spam protection are recorded here. Spam log entries do not contain full submission payloads — only metadata and the rejection reason.', 'xtreme-forms' ); ?></p>
	</div>

	<!-- Filters -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin-bottom:16px;">
		<input type="hidden" name="page" value="xtremeleads-spam-log">
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
				<?php foreach ( $items as $item ) :
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
							<span class="xf-badge <?php
								switch ( $item->rejection_reason ) {
									case 'honeypot': echo 'xf-badge-secondary'; break;
									case 'time_gate': echo 'xf-badge-warning'; break;
									case 'recaptcha': echo 'xf-badge-info'; break;
									case 'blocklist': echo 'xf-badge-danger'; break;
									default: echo 'xf-badge-secondary';
								}
							?>">
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
						$url = add_query_arg( array(
							'page' => 'xtremeleads-spam-log',
							'paged' => $p,
							'rejection_reason' => $filter_reason,
							'filter_form' => $filter_form,
						), admin_url( 'admin.php' ) );
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

<script>
(function(){
	var nonce = <?php echo wp_json_encode( $nonce ); ?>;
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

	// Delete single entry.
	document.querySelectorAll('.xf-spam-delete-entry').forEach(function(btn) {
		btn.addEventListener('click', function() {
			if (!confirm('<?php echo esc_js( __( 'Permanently delete this spam log entry?', 'xtreme-forms' ) ); ?>')) return;
			var id = btn.getAttribute('data-id');
			var fd = new FormData();
			fd.append('action', 'xf_spam_log_delete');
			fd.append('nonce', nonce);
			fd.append('entry_id', id);
			fetch(ajaxUrl, {method:'POST', body:fd})
				.then(function(r){ return r.json(); })
				.then(function(resp) {
					if (resp.success) {
						var row = document.querySelector('[data-entry-id="'+id+'"]');
						if (row) row.remove();
					} else {
						alert((resp.data && resp.data.message) || '<?php echo esc_js( __( 'Delete failed.', 'xtreme-forms' ) ); ?>');
					}
				});
		});
	});

	// Clear all.
	var clearBtn = document.getElementById('xf-spam-clear-all-btn');
	if (clearBtn) {
		clearBtn.addEventListener('click', function() {
			if (!confirm('<?php echo esc_js( __( 'Permanently clear the entire spam log? This cannot be undone.', 'xtreme-forms' ) ); ?>')) return;
			var msg = document.getElementById('xf-spam-clear-msg');
			msg.textContent = '<?php echo esc_js( __( 'Clearing…', 'xtreme-forms' ) ); ?>';
			var fd = new FormData();
			fd.append('action', 'xf_spam_log_clear');
			fd.append('nonce', nonce);
			fetch(ajaxUrl, {method:'POST', body:fd})
				.then(function(r){ return r.json(); })
				.then(function(resp) {
					if (resp.success) {
						msg.textContent = '<?php echo esc_js( __( 'Spam log cleared.', 'xtreme-forms' ) ); ?>';
						msg.style.color = '#28A745';
						setTimeout(function(){ location.reload(); }, 800);
					} else {
						msg.textContent = '<?php echo esc_js( __( 'Clear failed.', 'xtreme-forms' ) ); ?>';
						msg.style.color = '#DC3545';
					}
				});
		});
	}
})();
</script>
