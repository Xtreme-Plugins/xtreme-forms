<?php
/**
 * Webhooks admin page — CRUD interface + delivery log.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- GET parameters on this admin display page are read-only filter params.

$webhooks  = XF_Webhooks::get_all();
$all_forms = XF_Forms::get_all_forms();
$nonce     = wp_create_nonce( 'xf_webhook_nonce' );

$notice_html = '';
if ( ! empty( $_GET['updated'] ) ) {
	$notice_html = '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Webhook saved.', 'xtreme-forms' ) . '</p></div>';
}
?>
<div class="wrap xf-wrap" id="xf-webhooks-page">
	<div class="xf-page-header">
		<h1 class="xf-page-title"><?php esc_html_e( 'Webhooks', 'xtreme-forms' ); ?></h1>
		<div class="xf-header-actions">
			<button type="button" class="xf-btn xf-btn-primary" id="xf-add-webhook-btn">
				+ <?php esc_html_e( 'Add Webhook', 'xtreme-forms' ); ?>
			</button>
		</div>
	</div>
	<?php echo wp_kses_post( $notice_html ); ?>

	<!-- Webhook editor (hidden by default) -->
	<div id="xf-webhook-editor" class="xf-card" style="display:none;margin-bottom:24px;">
		<h2 id="xf-webhook-editor-title"><?php esc_html_e( 'Add Webhook', 'xtreme-forms' ); ?></h2>
		<input type="hidden" id="xf-webhook-id" value="0">

		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th scope="row"><label for="xf-wh-name"><?php esc_html_e( 'Name', 'xtreme-forms' ); ?></label></th>
					<td>
						<input type="text" id="xf-wh-name" class="regular-text" placeholder="<?php esc_attr_e( 'My CRM Webhook', 'xtreme-forms' ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="xf-wh-url"><?php esc_html_e( 'Endpoint URL', 'xtreme-forms' ); ?></label></th>
					<td>
						<input type="url" id="xf-wh-url" class="large-text" placeholder="https://example.com/webhook">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Trigger Events', 'xtreme-forms' ); ?></th>
					<td>
						<label style="display:block;margin-bottom:8px;">
							<input type="checkbox" id="xf-wh-event-new-lead" value="new_lead" checked>
							<?php esc_html_e( 'New Lead', 'xtreme-forms' ); ?>
						</label>
						<label style="display:block;">
							<input type="checkbox" id="xf-wh-event-status-change" value="status_change">
							<?php esc_html_e( 'Status Change', 'xtreme-forms' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Form Filter', 'xtreme-forms' ); ?></th>
					<td>
						<p class="description" style="margin-bottom:8px;"><?php esc_html_e( 'Leave all unchecked to receive events from all forms.', 'xtreme-forms' ); ?></p>
						<?php foreach ( $all_forms as $form ) : ?>
							<label style="display:block;margin-bottom:4px;">
								<input type="checkbox" class="xf-wh-form-filter" value="<?php echo esc_attr( $form->id ); ?>">
								<?php echo esc_html( $form->name ); ?>
							</label>
						<?php endforeach; ?>
						<?php if ( empty( $all_forms ) ) : ?>
							<p class="description"><?php esc_html_e( 'No forms found.', 'xtreme-forms' ); ?></p>
						<?php endif; ?>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Active', 'xtreme-forms' ); ?></th>
					<td>
						<label>
							<input type="checkbox" id="xf-wh-active" value="1" checked>
							<?php esc_html_e( 'Enable this webhook', 'xtreme-forms' ); ?>
						</label>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Custom Headers', 'xtreme-forms' ); ?></th>
					<td>
						<p class="description" style="margin-bottom:8px;"><?php esc_html_e( 'Add custom request headers (e.g., Authorization tokens). Header values are stored securely and never exposed in front-end HTML.', 'xtreme-forms' ); ?></p>
						<div id="xf-wh-headers-list"></div>
						<button type="button" class="button" id="xf-add-header-btn" style="margin-top:8px;">
							+ <?php esc_html_e( 'Add Header', 'xtreme-forms' ); ?>
						</button>
						<div id="xf-header-content-type-warning" class="notice notice-warning inline" style="display:none;margin-top:8px;">
							<p><?php esc_html_e( 'Note: Adding a "Content-Type" header will override the default "application/json" value.', 'xtreme-forms' ); ?></p>
						</div>
					</td>
				</tr>
			</tbody>
		</table>

		<div style="margin-top:16px;">
			<button type="button" class="button button-primary xf-btn-primary" id="xf-save-webhook-btn"><?php esc_html_e( 'Save Webhook', 'xtreme-forms' ); ?></button>
			<button type="button" class="button" id="xf-cancel-webhook-btn" style="margin-left:8px;"><?php esc_html_e( 'Cancel', 'xtreme-forms' ); ?></button>
			<span id="xf-webhook-save-msg" style="margin-left:12px;"></span>
		</div>
	</div>

	<!-- Webhooks list -->
	<div class="xf-card" style="margin-bottom:24px;">
		<h2><?php esc_html_e( 'Configured Webhooks', 'xtreme-forms' ); ?></h2>

		<?php if ( empty( $webhooks ) ) : ?>
			<p><?php esc_html_e( 'No webhooks configured yet. Click "Add Webhook" to create one.', 'xtreme-forms' ); ?></p>
		<?php else : ?>
			<table class="wp-list-table widefat striped" id="xf-webhooks-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Name', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'URL', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Events', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Forms', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Status', 'xtreme-forms' ); ?></th>
						<th><?php esc_html_e( 'Actions', 'xtreme-forms' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
				foreach ( $webhooks as $wh ) :
					$events       = json_decode( $wh->trigger_events, true ) ?: array();
					$form_ids     = json_decode( $wh->form_ids, true ) ?: array();
					$form_names   = empty( $form_ids ) ? __( 'All forms', 'xtreme-forms' ) : implode(
						', ',
						array_map(
							function ( $fid ) use ( $all_forms ) {
								foreach ( $all_forms as $f ) {
									if ( (int) $f->id === (int) $fid ) {
										return esc_html( $f->name );
									}
								}
								return '#' . $fid;
							},
							$form_ids
						)
					);
					$event_labels = array_map(
						function ( $e ) {
							return 'new_lead' === $e ? __( 'New Lead', 'xtreme-forms' ) : __( 'Status Change', 'xtreme-forms' );
						},
						$events
					);
					?>
					<tr data-webhook-id="<?php echo esc_attr( $wh->id ); ?>">
						<td><strong><?php echo esc_html( $wh->name ); ?></strong></td>
						<td><code style="word-break:break-all;"><?php echo esc_html( $wh->url ); ?></code></td>
						<td><?php echo esc_html( implode( ', ', $event_labels ) ); ?></td>
						<td><?php echo esc_html( $form_names ); ?></td>
						<td>
							<?php if ( $wh->is_active ) : ?>
								<span class="xf-badge xf-badge-success"><?php esc_html_e( 'Active', 'xtreme-forms' ); ?></span>
							<?php else : ?>
								<span class="xf-badge xf-badge-secondary"><?php esc_html_e( 'Inactive', 'xtreme-forms' ); ?></span>
							<?php endif; ?>
						</td>
						<td>
							<button type="button" class="button xf-wh-edit-btn" data-id="<?php echo esc_attr( $wh->id ); ?>"><?php esc_html_e( 'Edit', 'xtreme-forms' ); ?></button>
							<button type="button" class="button xf-wh-test-btn" data-id="<?php echo esc_attr( $wh->id ); ?>"><?php esc_html_e( 'Test Fire', 'xtreme-forms' ); ?></button>
							<button type="button" class="button xf-wh-log-btn" data-id="<?php echo esc_attr( $wh->id ); ?>"><?php esc_html_e( 'View Log', 'xtreme-forms' ); ?></button>
							<button type="button" class="button button-link-delete xf-wh-delete-btn" data-id="<?php echo esc_attr( $wh->id ); ?>"><?php esc_html_e( 'Delete', 'xtreme-forms' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<!-- Test fire result -->
	<div id="xf-test-fire-result" class="xf-card" style="display:none;margin-bottom:24px;">
		<h2><?php esc_html_e( 'Test Fire Result', 'xtreme-forms' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr><th><?php esc_html_e( 'Status', 'xtreme-forms' ); ?></th><td id="xf-tf-status"></td></tr>
				<tr><th><?php esc_html_e( 'HTTP Code', 'xtreme-forms' ); ?></th><td id="xf-tf-http-code"></td></tr>
				<tr><th><?php esc_html_e( 'Response', 'xtreme-forms' ); ?></th><td><pre id="xf-tf-response" style="white-space:pre-wrap;word-break:break-all;max-height:200px;overflow:auto;"></pre></td></tr>
				<tr id="xf-tf-error-row" style="display:none;"><th><?php esc_html_e( 'Error', 'xtreme-forms' ); ?></th><td id="xf-tf-error" style="color:#DC3545;"></td></tr>
			</tbody>
		</table>
	</div>

	<!-- Delivery Log -->
	<div id="xf-delivery-log-wrap" class="xf-card" style="display:none;">
		<h2><?php esc_html_e( 'Delivery Log', 'xtreme-forms' ); ?> <span id="xf-log-webhook-name"></span></h2>
		<div id="xf-delivery-log-content">
			<p><?php esc_html_e( 'Loading…', 'xtreme-forms' ); ?></p>
		</div>
		<div id="xf-log-pagination" style="margin-top:12px;"></div>
	</div>

</div><!-- .xf-wrap -->

<?php
/*
 * Webhooks page bootstrap data + i18n strings.
 *
 * The dedicated JS/CSS files (admin/js/xf-webhooks.js, admin/css/xf-webhooks.css)
 * are enqueued via the shared admin enqueue function. Per-render data and
 * translatable strings are attached here so the WordPress.org Plugin Check
 * sees no inline <script>/<style> tags in the rendered HTML.
 */
wp_localize_script(
	'xf-webhooks',
	'xfWebhooksData',
	array(
		'nonce'    => $nonce,
		'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
		'allForms' => array_map(
			function ( $f ) {
				return array(
					'id'   => (int) $f->id,
					'name' => $f->name,
				);
			},
			$all_forms
		),
	)
);

wp_localize_script(
	'xf-webhooks',
	'xfWebhooksI18n',
	array(
		'editWebhook'      => __( 'Edit Webhook', 'xtreme-forms' ),
		'addWebhook'       => __( 'Add Webhook', 'xtreme-forms' ),
		'headerName'       => __( 'Header Name', 'xtreme-forms' ),
		'headerValue'      => __( 'Header Value', 'xtreme-forms' ),
		'remove'           => __( 'Remove', 'xtreme-forms' ),
		'saving'           => __( 'Saving…', 'xtreme-forms' ),
		'saved'            => __( 'Saved!', 'xtreme-forms' ),
		'saveFailed'       => __( 'Save failed.', 'xtreme-forms' ),
		'networkError'     => __( 'Network error.', 'xtreme-forms' ),
		'confirmDelete'    => __( 'Delete this webhook and its delivery log? This cannot be undone.', 'xtreme-forms' ),
		'deleteFailed'     => __( 'Delete failed.', 'xtreme-forms' ),
		'sending'          => __( 'Sending…', 'xtreme-forms' ),
		'testFireFailed'   => __( 'Test fire failed.', 'xtreme-forms' ),
		'networkErrorTest' => __( 'Network error. Test fire could not be sent.', 'xtreme-forms' ),
		'loading'          => __( 'Loading…', 'xtreme-forms' ),
		'failedLoadLog'    => __( 'Failed to load log.', 'xtreme-forms' ),
		'noLogEntries'     => __( 'No log entries found.', 'xtreme-forms' ),
		'colTime'          => __( 'Time', 'xtreme-forms' ),
		'colRecipientUrl'  => __( 'Recipient URL', 'xtreme-forms' ),
		'colLeadId'        => __( 'Lead ID', 'xtreme-forms' ),
		'colEvent'         => __( 'Event', 'xtreme-forms' ),
		'colStatus'        => __( 'Status', 'xtreme-forms' ),
		'colHttpCode'      => __( 'HTTP Code', 'xtreme-forms' ),
		'colRetry'         => __( 'Retry?', 'xtreme-forms' ),
		'colResponse'      => __( 'Response', 'xtreme-forms' ),
		'test'             => __( 'test', 'xtreme-forms' ),
		'retry'            => __( 'Retry', 'xtreme-forms' ),
	)
);
?>
