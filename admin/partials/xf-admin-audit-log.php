<?php
/**
 * Audit Log admin page — read-only, append-only.
 *
 * @package Xtreme Forms
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Filter parameters on this admin display page are read-only GET params — no nonce required for display-only filtering.

$per_page    = 50;
$paged       = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
$filter_type = isset( $_GET['action_type'] ) ? sanitize_key( $_GET['action_type'] ) : '';
$filter_user = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;

$result = XF_Audit_Log::get_entries(
	array(
		'action_type' => $filter_type,
		'user_id'     => $filter_user,
		'per_page'    => $per_page,
		'paged'       => $paged,
	)
);

$entries     = $result['entries'];
$total       = $result['total'];
$total_pages = ceil( $total / $per_page );

$action_types = XF_Audit_Log::get_all_action_types();

$base_url = add_query_arg(
	array(
		'page'        => 'xtreme-forms-audit-log',
		'action_type' => $filter_type,
		'user_id'     => $filter_user,
	),
	admin_url( 'admin.php' )
);
?>
<div class="wrap xf-wrap">
	<h1 class="xf-page-title"><?php esc_html_e( 'Audit Log', 'xtreme-forms' ); ?></h1>
	<p class="description"><?php esc_html_e( 'A read-only, append-only record of all significant Xtreme Forms actions. Entries cannot be edited or deleted.', 'xtreme-forms' ); ?></p>

	<!-- Filter bar -->
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="xf-filter-form" style="margin:16px 0;display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
		<input type="hidden" name="page" value="xtreme-forms-audit-log">

		<div>
			<label for="xf-filter-action-type" style="display:block;margin-bottom:4px;font-weight:600;"><?php esc_html_e( 'Action Type', 'xtreme-forms' ); ?></label>
			<select id="xf-filter-action-type" name="action_type">
				<option value=""><?php esc_html_e( '— All Types —', 'xtreme-forms' ); ?></option>
				<?php foreach ( $action_types as $slug ) : ?>
					<option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $filter_type, $slug ); ?>>
						<?php echo esc_html( XF_Audit_Log::get_action_label( $slug ) ); ?>
					</option>
				<?php endforeach; ?>
			</select>
		</div>

		<div>
			<button type="submit" class="button"><?php esc_html_e( 'Filter', 'xtreme-forms' ); ?></button>
			<?php if ( $filter_type || $filter_user ) : ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=xtreme-forms-audit-log' ) ); ?>" class="button"><?php esc_html_e( 'Reset', 'xtreme-forms' ); ?></a>
			<?php endif; ?>
		</div>
	</form>

	<p style="color:#6C757D;font-size:13px;">
		<?php
		/* translators: %d: total number of log entries */
		printf( esc_html( _n( '%d entry found.', '%d entries found.', $total, 'xtreme-forms' ) ), (int) $total );
		?>
	</p>

	<table class="wp-list-table widefat fixed striped xf-table xf-audit-log-table">
		<thead>
			<tr>
				<th style="width:60px;"><?php esc_html_e( 'ID', 'xtreme-forms' ); ?></th>
				<th style="width:170px;"><?php esc_html_e( 'Timestamp (UTC)', 'xtreme-forms' ); ?></th>
				<th style="width:150px;"><?php esc_html_e( 'User', 'xtreme-forms' ); ?></th>
				<th style="width:200px;"><?php esc_html_e( 'Action', 'xtreme-forms' ); ?></th>
				<th style="width:80px;"><?php esc_html_e( 'Record ID', 'xtreme-forms' ); ?></th>
				<th><?php esc_html_e( 'Context', 'xtreme-forms' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php if ( empty( $entries ) ) : ?>
				<tr>
					<td colspan="6" style="text-align:center;padding:24px;color:#6C757D;">
						<?php esc_html_e( 'No audit log entries found.', 'xtreme-forms' ); ?>
					</td>
				</tr>
			<?php else : ?>
				<?php foreach ( $entries as $entry ) : ?>
					<?php
					$ctx       = json_decode( $entry->context, true );
					$ctx_parts = array();
					if ( is_array( $ctx ) ) {
						foreach ( $ctx as $k => $v ) {
							if ( is_scalar( $v ) ) {
								$ctx_parts[] = '<strong>' . esc_html( $k ) . ':</strong> ' . esc_html( (string) $v );
							}
						}
					}
					$ctx_html = ! empty( $ctx_parts ) ? implode( ' &bull; ', $ctx_parts ) : '—';

					// ISO-8601 timestamp display.
					$ts_raw  = $entry->created_at; // stored as UTC MySQL datetime.
					$ts_iso  = str_replace( ' ', 'T', $ts_raw ) . 'Z';
					$ts_disp = esc_html( $ts_raw );
					?>
					<tr>
						<td><code><?php echo esc_html( (string) $entry->id ); ?></code></td>
						<td>
							<time datetime="<?php echo esc_attr( $ts_iso ); ?>" title="<?php echo esc_attr( $ts_iso ); ?>">
								<?php echo esc_html( $ts_disp ); ?>
							</time>
						</td>
						<td><?php echo esc_html( $entry->user_display ); ?></td>
						<td>
							<span class="xf-audit-action-badge xf-audit-action-<?php echo esc_attr( $entry->action_type ); ?>">
								<?php echo esc_html( XF_Audit_Log::get_action_label( $entry->action_type ) ); ?>
							</span>
							<br><small style="color:#6C757D;font-family:monospace;"><?php echo esc_html( $entry->action_type ); ?></small>
						</td>
						<td>
							<?php if ( $entry->record_id > 0 ) : ?>
								<code><?php echo esc_html( (string) $entry->record_id ); ?></code>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td style="font-size:12px;"><?php echo wp_kses_post( $ctx_html ); ?></td>
					</tr>
				<?php endforeach; ?>
			<?php endif; ?>
		</tbody>
	</table>

	<!-- Pagination -->
	<?php if ( $total_pages > 1 ) : ?>
		<div class="tablenav bottom" style="margin-top:12px;">
			<div class="tablenav-pages">
				<?php
				$pagination_args = array(
					'base'      => add_query_arg( 'paged', '%#%', $base_url ),
					'format'    => '',
					'total'     => $total_pages,
					'current'   => $paged,
					'prev_text' => '&laquo; ' . esc_html__( 'Previous', 'xtreme-forms' ),
					'next_text' => esc_html__( 'Next', 'xtreme-forms' ) . ' &raquo;',
				);
				echo wp_kses_post( paginate_links( $pagination_args ) );
				?>
			</div>
		</div>
	<?php endif; ?>
</div>
